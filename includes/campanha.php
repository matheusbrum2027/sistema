<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function campanha_store_garantir(array &$db): void
{
    if (!isset($db['campanhas']) || !is_array($db['campanhas'])) {
        $db['campanhas'] = [];
    }
    if (!isset($db['seq']) || !is_array($db['seq'])) {
        $db['seq'] = [];
    }
    if (!isset($db['seq']['campanha'])) {
        $max = 0;
        foreach ($db['campanhas'] as $c) {
            $max = max($max, (int) ($c['id'] ?? 0));
        }
        $db['seq']['campanha'] = $max + 1;
    }
}

function campanha_admin(): bool
{
    return !empty($_SESSION['auth']) && (($_SESSION['role'] ?? '') === 'admin');
}

function campanha_publico_opcoes(): array
{
    return [
        'clientes' => 'Clientes cadastrados',
        'solicitacoes' => 'Solicitações de transfer',
        'motoristas' => 'Motoristas ativos',
        'manual' => 'Lista manual',
    ];
}

function campanha_destinatarios(array $db, array $campanha): array
{
    $publico = (string) ($campanha['publico'] ?? 'clientes');
    $lista = [];
    $vistos = [];

    $add = static function (string $nome, string $fone) use (&$lista, &$vistos): void {
        $num = wa_number($fone);
        if ($num === '' || isset($vistos[$num])) {
            return;
        }
        $vistos[$num] = true;
        $lista[] = [
            'nome' => $nome !== '' ? $nome : $num,
            'telefone' => $fone,
            'numero' => $num,
        ];
    };

    if ($publico === 'clientes') {
        foreach ($db['clientes'] ?? [] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $add((string) ($c['nome'] ?? ''), (string) ($c['telefone'] ?? ''));
        }
    } elseif ($publico === 'solicitacoes') {
        foreach ($db['inbox'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $add((string) ($row['nome'] ?? ''), (string) ($row['telefone'] ?? ($row['whatsapp'] ?? '')));
        }
    } elseif ($publico === 'motoristas') {
        foreach ($db['motoristas'] ?? [] as $m) {
            if (!is_array($m) || (string) ($m['status'] ?? '') !== 'ativo') {
                continue;
            }
            $add((string) ($m['nome'] ?? ''), (string) ($m['telefone'] ?? ''));
        }
    } else {
        $linhas = preg_split('/\R+/', (string) ($campanha['lista_manual'] ?? '')) ?: [];
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            if (str_contains($linha, ';') || str_contains($linha, ',')) {
                $partes = preg_split('/[;,]/', $linha) ?: [];
                $add(trim((string) ($partes[0] ?? '')), trim((string) ($partes[1] ?? $partes[0] ?? '')));
            } else {
                $add('', $linha);
            }
        }
    }

    return $lista;
}

function campanha_metricas(array $campanha, int $alcance = 0): array
{
    $envios = is_array($campanha['envios'] ?? null) ? $campanha['envios'] : [];
    $ok = 0;
    $falha = 0;
    foreach ($envios as $e) {
        if (!empty($e['ok'])) {
            $ok++;
        } else {
            $falha++;
        }
    }
    $total = max($alcance, count($envios));
    $pct = $total > 0 ? (int) round(($ok / $total) * 100) : 0;
    return [
        'alcance' => $alcance,
        'enviados' => $ok,
        'falhas' => $falha,
        'pendentes' => max(0, $alcance - count($envios)),
        'progresso' => $pct,
    ];
}

function campanha_resumo(array $db, array $campanha): array
{
    $dest = campanha_destinatarios($db, $campanha);
    $m = campanha_metricas($campanha, count($dest));
    return [
        'id' => (int) ($campanha['id'] ?? 0),
        'nome' => (string) ($campanha['nome'] ?? ''),
        'mensagem' => (string) ($campanha['mensagem'] ?? ''),
        'publico' => (string) ($campanha['publico'] ?? 'clientes'),
        'publico_label' => campanha_publico_opcoes()[(string) ($campanha['publico'] ?? 'clientes')] ?? 'Público',
        'lista_manual' => (string) ($campanha['lista_manual'] ?? ''),
        'status' => (string) ($campanha['status'] ?? 'rascunho'),
        'criado_em' => (string) ($campanha['criado_em'] ?? ''),
        'atualizado_em' => (string) ($campanha['atualizado_em'] ?? ''),
        'metricas' => $m,
    ];
}

function campanha_lista(array $db): array
{
    campanha_store_garantir($db);
    $itens = [];
    foreach ($db['campanhas'] as $c) {
        if (is_array($c)) {
            $itens[] = campanha_resumo($db, $c);
        }
    }
    usort($itens, static fn(array $a, array $b): int => ($b['id'] <=> $a['id']));
    $ativas = count(array_filter($itens, static fn(array $i): bool => ($i['status'] ?? '') === 'ativa'));
    $enviados = 0;
    foreach ($itens as $i) {
        $enviados += (int) ($i['metricas']['enviados'] ?? 0);
    }
    return [
        'ok' => true,
        'itens' => $itens,
        'resumo' => [
            'total' => count($itens),
            'ativas' => $ativas,
            'enviados' => $enviados,
            'publico_opcoes' => campanha_publico_opcoes(),
            'whatsapp_ok' => uazapi_settings_ready($db['settings'] ?? []),
        ],
    ];
}

function campanha_indice(array $db, int $id): ?int
{
    foreach ($db['campanhas'] as $i => $c) {
        if (is_array($c) && (int) ($c['id'] ?? 0) === $id) {
            return (int) $i;
        }
    }
    return null;
}

function campanha_salvar(array &$db, array $dados, int $id = 0): array
{
    campanha_store_garantir($db);
    $nome = clip((string) ($dados['nome'] ?? ''), 80);
    $mensagem = clip((string) ($dados['mensagem'] ?? ''), 2000);
    $publico = (string) ($dados['publico'] ?? 'clientes');
    if (!isset(campanha_publico_opcoes()[$publico])) {
        $publico = 'clientes';
    }
    $lista = clip((string) ($dados['lista_manual'] ?? ''), 8000);
    if ($nome === '' || $mensagem === '') {
        return ['ok' => false, 'erro' => 'Informe nome e mensagem da campanha.'];
    }
    $agora = date('Y-m-d H:i');
    if ($id > 0) {
        $achou = null;
        foreach ($db['campanhas'] as $i => $c) {
            if ((int) ($c['id'] ?? 0) === $id) {
                $achou = $i;
                break;
            }
        }
        if ($achou === null) {
            return ['ok' => false, 'erro' => 'Campanha não encontrada.'];
        }
        $db['campanhas'][$achou]['nome'] = $nome;
        $db['campanhas'][$achou]['mensagem'] = $mensagem;
        $db['campanhas'][$achou]['publico'] = $publico;
        $db['campanhas'][$achou]['lista_manual'] = $lista;
        $db['campanhas'][$achou]['atualizado_em'] = $agora;
        return ['ok' => true, 'item' => campanha_resumo($db, $db['campanhas'][$achou]), 'msg' => 'Campanha atualizada.'];
    }
    $novo = [
        'id' => (int) $db['seq']['campanha']++,
        'nome' => $nome,
        'mensagem' => $mensagem,
        'publico' => $publico,
        'lista_manual' => $lista,
        'status' => 'rascunho',
        'envios' => [],
        'criado_em' => $agora,
        'atualizado_em' => $agora,
    ];
    $db['campanhas'][] = $novo;
    return ['ok' => true, 'item' => campanha_resumo($db, $novo), 'msg' => 'Campanha criada.'];
}

function campanha_status(array &$db, int $id, string $status): array
{
    campanha_store_garantir($db);
    $idx = campanha_indice($db, $id);
    if ($idx === null) {
        return ['ok' => false, 'erro' => 'Campanha não encontrada.'];
    }
    if (!in_array($status, ['rascunho', 'ativa', 'encerrada'], true)) {
        return ['ok' => false, 'erro' => 'Status inválido.'];
    }
    $db['campanhas'][$idx]['status'] = $status;
    $db['campanhas'][$idx]['atualizado_em'] = date('Y-m-d H:i');
    return ['ok' => true, 'item' => campanha_resumo($db, $db['campanhas'][$idx]), 'msg' => 'Status atualizado.'];
}

function campanha_apagar(array &$db, int $id): array
{
    campanha_store_garantir($db);
    $antes = count($db['campanhas']);
    $db['campanhas'] = array_values(array_filter(
        $db['campanhas'],
        static fn($c): bool => !is_array($c) || (int) ($c['id'] ?? 0) !== $id
    ));
    if (count($db['campanhas']) === $antes) {
        return ['ok' => false, 'erro' => 'Campanha não encontrada.'];
    }
    return ['ok' => true, 'msg' => 'Campanha removida.'];
}

function campanha_disparar(array &$db, int $id, int $lote = 15): array
{
    campanha_store_garantir($db);
    $idx = campanha_indice($db, $id);
    if ($idx === null) {
        return ['ok' => false, 'erro' => 'Campanha não encontrada.'];
    }
    $settings = $db['settings'] ?? [];
    if (!uazapi_settings_ready($settings)) {
        return ['ok' => false, 'erro' => 'WhatsApp desconectado. Conecte no Comunicador antes de disparar.'];
    }
    $dest = campanha_destinatarios($db, $db['campanhas'][$idx]);
    if ($dest === []) {
        return ['ok' => false, 'erro' => 'Nenhum destinatário neste público.'];
    }
    if (!is_array($db['campanhas'][$idx]['envios'] ?? null)) {
        $db['campanhas'][$idx]['envios'] = [];
    }
    $ja = [];
    foreach ($db['campanhas'][$idx]['envios'] as $e) {
        $ja[(string) ($e['numero'] ?? '')] = true;
    }
    $base = rtrim((string) ($settings['uazapi_url'] ?? ''), '/');
    $token = (string) ($settings['uazapi_token'] ?? '');
    $mensagem = (string) ($db['campanhas'][$idx]['mensagem'] ?? '');
    $enviadosAgora = 0;
    $falhasAgora = 0;
    $restantes = [];
    foreach ($dest as $d) {
        $num = (string) ($d['numero'] ?? '');
        if ($num === '' || isset($ja[$num])) {
            continue;
        }
        $restantes[] = $d;
    }
    $lote = max(1, min(40, $lote));
    $fatia = array_slice($restantes, 0, $lote);
    foreach ($fatia as $d) {
        $texto = str_replace(
            ['{nome}', '{cliente}'],
            [(string) $d['nome'], (string) $d['nome']],
            $mensagem
        );
        [$ok, $erro] = uazapi_send_text($base, $token, (string) $d['numero'], $texto, 18);
        $db['campanhas'][$idx]['envios'][] = [
            'numero' => (string) $d['numero'],
            'nome' => (string) $d['nome'],
            'ok' => (bool) $ok,
            'erro' => $ok ? '' : (string) $erro,
            'em' => date('Y-m-d H:i:s'),
        ];
        if ($ok) {
            $enviadosAgora++;
        } else {
            $falhasAgora++;
        }
        usleep(120000);
    }
    $db['campanhas'][$idx]['status'] = 'ativa';
    $db['campanhas'][$idx]['atualizado_em'] = date('Y-m-d H:i');
    $resumo = campanha_resumo($db, $db['campanhas'][$idx]);
    $ainda = max(0, (int) $resumo['metricas']['pendentes']);
    if ($ainda === 0) {
        $db['campanhas'][$idx]['status'] = 'encerrada';
        $resumo = campanha_resumo($db, $db['campanhas'][$idx]);
    }
    return [
        'ok' => true,
        'item' => $resumo,
        'lote' => [
            'enviados' => $enviadosAgora,
            'falhas' => $falhasAgora,
            'pendentes' => $ainda,
        ],
        'msg' => $ainda > 0
            ? "Lote enviado: {$enviadosAgora} ok, {$falhasAgora} falha(s). Ainda restam {$ainda}."
            : "Campanha concluída: {$enviadosAgora} enviados neste lote.",
    ];
}
