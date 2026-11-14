<?php
declare(strict_types=1);

define('APP', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

function deve_remover_motorista(array $m, array $idsUsados): bool
{
    $id = (int) ($m['id'] ?? 0);
    if ($id === 12) {
        return false; // motorista de acesso real
    }
    if (!empty($idsUsados[$id])) {
        return false;
    }
    $nome = mb_strtolower(trim((string) ($m['nome'] ?? '')));
    $email = mb_strtolower((string) ($m['email'] ?? ''));
    if (str_contains($nome, 'exemplo')) {
        return true;
    }
    foreach (['luis pilippe', 'bc transfer', 'rafael samogim', 'pte -', 'joão carlos ferreira', 'jhonny almeida', 'michel santana'] as $trecho) {
        if (str_contains($nome, $trecho)) {
            return true;
        }
    }
    foreach (['cuiaba', 'bctransfer', 'pernambuco', '@email.com'] as $trecho) {
        if (str_contains($email, $trecho)) {
            return true;
        }
    }
    return false;
}

function gravar_base2_vazia(array $modelo): int
{
    $vazio = normalize_db([
        'schema' => 2,
        'seq' => [
            'transfer' => 1, 'lancamento' => 1, 'motorista' => 1, 'cliente' => 1, 'veiculo' => 1,
            'grupo' => 1, 'nota' => 1, 'mot_solic' => 1, 'mensagem' => 1, 'zona' => 1,
            'fatura' => 1, 'contrato' => 1, 'automacao' => 1, 'inbox' => 1, 'aviso' => 1,
        ],
        'settings' => array_merge($modelo['settings'] ?? [], [
            'usuario' => 'admin',
            'senha' => 'FlnAdmin2026',
            'nome' => (string) (($modelo['settings']['nome'] ?? '') !== '' ? $modelo['settings']['nome'] : 'Transfer FLN'),
        ]),
        'transfers' => [],
        'clientes' => [],
        'motoristas' => [],
        'veiculos' => [],
        'lancamentos' => [],
        'grupos' => [],
        'notas' => [],
        'mot_solic' => [],
        'inbox' => [],
        'mensagens' => [],
        'zonas' => [],
        'whatsapp_envios' => new stdClass(),
        'automacoes' => [],
        'templates' => $modelo['templates'] ?? [],
        'lixo_reservas' => [],
        'atendimento' => ['por_fone' => new stdClass()],
        'chat' => ['secret' => bin2hex(random_bytes(16)), 'desde' => time(), 'conversas' => new stdClass(), 'mensagens' => []],
        'push' => [],
        'avisos' => [],
    ]);
    // fln_salvar rejeita object vazio tipado errado — garante arrays.
    $vazio['whatsapp_envios'] = [];
    $vazio['atendimento'] = ['por_fone' => []];
    if (isset($vazio['chat']['conversas']) && !is_array($vazio['chat']['conversas'])) {
        $vazio['chat']['conversas'] = [];
    }

    for ($i = 0; $i < 6; $i++) {
        $ler = store_rpc('fln_ler', ['p_base' => 2], 40);
        $versao2 = (int) ($ler['versao'] ?? 0);
        $nova = (int) store_rpc('fln_salvar', [
            'p_versao' => $versao2,
            'p_dados' => $vazio,
            'p_acao' => 'limpar_base2',
            'p_usuario' => 'sistema',
            'p_base' => 2,
        ], 90);
        if ($nova > 0) {
            return $nova;
        }
        usleep(200000 * ($i + 1));
    }
    throw new RuntimeException('Não limpou a base 2.');
}

// Remove no máx. 3 motoristas por gravação (trava do fln_salvar).
$totalRemovidos = 0;
for ($rodada = 0; $rodada < 20; $rodada++) {
    $ok = false;
    for ($t = 0; $t < 5; $t++) {
        $db = load_db(true);
        $idsUsados = [];
        foreach ($db['transfers'] as $trip) {
            foreach (['motorista_id', 'motorista_volta_id'] as $campo) {
                $mid = (int) ($trip[$campo] ?? 0);
                if ($mid > 0) {
                    $idsUsados[$mid] = true;
                }
            }
        }
        $remover = [];
        foreach ($db['motoristas'] as $m) {
            if (deve_remover_motorista($m, $idsUsados)) {
                $remover[] = (int) $m['id'];
            }
        }
        if ($remover === []) {
            $ok = true;
            break 2;
        }
        $lote = array_slice($remover, 0, 3);
        $antes = count($db['motoristas']);
        $db['motoristas'] = array_values(array_filter(
            $db['motoristas'],
            static fn(array $m): bool => !in_array((int) ($m['id'] ?? 0), $lote, true)
        ));
        try {
            save_db($db, 'limpar_ficticios');
            $totalRemovidos += $antes - count($db['motoristas']);
            echo 'base1 versao=' . store_versao() . ' removeu=' . implode(',', $lote) . "\n";
            $ok = true;
            break;
        } catch (BancoConflito) {
            usleep(200000 * ($t + 1));
        }
    }
    if (!$ok) {
        fwrite(STDERR, "conflito na limpeza da base 1\n");
        exit(1);
    }
}

$db = load_db(true);
$v2 = gravar_base2_vazia($db);
echo "total_motoristas_removidos={$totalRemovidos}\n";
echo "base2_versao={$v2}\n";
$resumo = store_rpc('fln_ler', ['p_base' => 1], 40);
$d = $resumo['dados'] ?? [];
echo 'base1 transfers=' . count($d['transfers'] ?? []) . ' motoristas=' . count($d['motoristas'] ?? []) . ' clientes=' . count($d['clientes'] ?? []) . "\n";
exit(0);
