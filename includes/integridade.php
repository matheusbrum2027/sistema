<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function intervalo_motorista(array $db): int
{
    $min = (int) ($db['settings']['intervalo_motorista'] ?? 120);
    return max(15, min(720, $min));
}

function motoristas_do_trecho(array $trip, string $kind): array
{
    $kind = $kind === 'volta' ? 'volta' : 'ida';
    $tipo = (string) ($trip['tipo'] ?? 'ida');
    // Registro só de volta: motorista_id (e extras) são da volta.
    // Ida e volta num único registro: volta usa motorista_volta_id quando existir.
    $principal = (int) ($trip['motorista_id'] ?? 0);
    if ($kind === 'volta') {
        if ($tipo === 'ida_volta' && (int) ($trip['motorista_volta_id'] ?? 0) > 0) {
            $principal = (int) $trip['motorista_volta_id'];
        } elseif ($tipo === 'ida' && (string) ($trip['conjunto'] ?? '') === 'ida_volta') {
            // Registro de ida do par ida/volta: não lista motoristas na perna volta.
            return [];
        }
    } elseif ($kind === 'ida' && $tipo === 'volta') {
        // Registro de volta: não lista motoristas na perna ida.
        return [];
    }
    if ((string) ($trip['quem'] ?? 'motorista') === 'parceiro') {
        return [];
    }
    $ids = $principal > 0 ? [$principal] : [];
    foreach ($trip['extras'] ?? [] as $extra) {
        $eid = (int) $extra;
        if ($eid > 0) {
            $ids[] = $eid;
        }
    }
    return array_values(array_unique($ids));
}

function ocupacoes_motoristas(array $db): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $padrao = intervalo_motorista($db) * 60;
    $porMotorista = [];
    foreach ($db['transfers'] as $trip) {
        if ((string) ($trip['status'] ?? '') === 'cancelada') {
            continue;
        }
        $duracao = $padrao;
        if ((string) ($trip['tipo'] ?? '') === 'hora') {
            $horas = (float) str_replace(',', '.', (string) ($trip['qtd_horas'] ?? ''));
            if ($horas > 0) {
                $duracao = max($padrao, (int) round($horas * 3600));
            }
        }
        foreach (agenda_legs($trip) as $leg) {
            $quando = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $leg['data'] . ' ' . ($leg['hora'] !== '' ? $leg['hora'] : '00:00'), $tz);
            if (!$quando) {
                continue;
            }
            $inicio = $quando->getTimestamp();
            foreach (motoristas_do_trecho($trip, (string) $leg['kind']) as $mid) {
                $porMotorista[$mid][] = [
                    'trip' => (int) $trip['id'],
                    'leg' => (string) $leg['label'],
                    'inicio' => $inicio,
                    'fim' => $inicio + $duracao,
                ];
            }
        }
    }
    return $porMotorista;
}

function conflitos_motoristas(array $db): array
{
    $saida = [];
    foreach (ocupacoes_motoristas($db) as $mid => $lista) {
        usort($lista, static fn(array $a, array $b): int => $a['inicio'] <=> $b['inicio']);
        $n = count($lista);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n && $lista[$j]['inicio'] < $lista[$i]['fim']; $j++) {
                if ($lista[$i]['trip'] === $lista[$j]['trip']) {
                    continue;
                }
                [$a, $b] = $lista[$i]['trip'] < $lista[$j]['trip'] ? [$lista[$i], $lista[$j]] : [$lista[$j], $lista[$i]];
                $saida[$mid . '|' . $a['trip'] . '|' . $b['trip']] = [$mid, $a, $b];
            }
        }
    }
    return $saida;
}

function referencias_quebradas(array $db): array
{
    $mot = array_flip(array_map('intval', array_column($db['motoristas'], 'id')));
    $cli = array_flip(array_map('intval', array_column($db['clientes'] ?? [], 'id')));
    $vei = array_flip(array_map('intval', array_column($db['veiculos'] ?? [], 'id')));
    $tri = array_flip(array_map('intval', array_column($db['transfers'], 'id')));
    $saida = [];
    foreach ($db['transfers'] as $t) {
        $id = (int) $t['id'];
        foreach (['motorista_id', 'motorista_volta_id'] as $campo) {
            $v = (int) ($t[$campo] ?? 0);
            if ($v > 0 && !isset($mot[$v])) {
                $saida['t' . $id . $campo] = 'A reserva #' . $id . ' ficaria com um motorista que não existe mais.';
            }
        }
        foreach ($t['extras'] ?? [] as $x) {
            if ((int) $x > 0 && !isset($mot[(int) $x])) {
                $saida['t' . $id . 'x' . (int) $x] = 'A reserva #' . $id . ' ficaria com um motorista extra que não existe mais.';
            }
        }
        $c = (int) ($t['cliente_id'] ?? 0);
        if ($c > 0 && !isset($cli[$c])) {
            $saida['t' . $id . 'c'] = 'A reserva #' . $id . ' ficaria sem o cadastro do cliente.';
        }
        $v = (int) ($t['veiculo_id'] ?? 0);
        if ($v > 0 && !isset($vei[$v])) {
            $saida['t' . $id . 'v'] = 'A reserva #' . $id . ' ficaria com um veículo que não existe mais.';
        }
    }
    foreach ($db['lancamentos'] as $l) {
        $t = (int) ($l['transfer_id'] ?? 0);
        if ($t > 0 && !isset($tri[$t])) {
            $saida['l' . (int) $l['id']] = 'O lançamento #' . (int) $l['id'] . ' ficaria sem a reserva #' . $t . '.';
        }
    }
    return $saida;
}

function por_id(array $lista): array
{
    $saida = [];
    foreach ($lista as $row) {
        $saida[(int) ($row['id'] ?? 0)] = $row;
    }
    return $saida;
}

function integridade_violada(array $antes, array $depois): ?string
{
    $statusOk = ['pendente', 'reserva', 'andamento', 'concluida', 'cancelada'];
    $tAntes = por_id($antes['transfers']);
    $tDepois = por_id($depois['transfers']);
    if (count($tDepois) !== count($depois['transfers'])) {
        return 'Duas reservas ficariam com o mesmo número. Nada foi salvo.';
    }
    $sumiram = array_diff_key($tAntes, $tDepois);
    if (count($sumiram) > 1) {
        return 'Esta ação removeria várias reservas de uma vez. Nada foi salvo.';
    }
    foreach ($sumiram as $id => $t) {
        if (!empty($t['pago'])) {
            return 'A reserva #' . $id . ' já foi paga e não pode ser excluída. Use Cancelar.';
        }
    }
    $motPorId = por_id($depois['motoristas']);
    foreach ($tDepois as $id => $t) {
        if (!in_array((string) ($t['status'] ?? ''), $statusOk, true)) {
            return 'Status inválido na reserva #' . $id . '.';
        }
        $antigo = $tAntes[$id] ?? null;
        foreach (array_merge([(int) ($t['motorista_id'] ?? 0), (int) ($t['motorista_volta_id'] ?? 0)], array_map('intval', $t['extras'] ?? [])) as $mid) {
            if ($mid <= 0) {
                continue;
            }
            $jaTinha = $antigo !== null && in_array($mid, array_merge([(int) ($antigo['motorista_id'] ?? 0), (int) ($antigo['motorista_volta_id'] ?? 0)], array_map('intval', $antigo['extras'] ?? [])), true);
            if (!$jaTinha && isset($motPorId[$mid]) && (string) ($motPorId[$mid]['status'] ?? '') !== 'ativo') {
                return $motPorId[$mid]['nome'] . ' está inativo e não pode receber a reserva #' . $id . '.';
            }
        }
    }

    $lAntes = por_id($antes['lancamentos']);
    $lDepois = por_id($depois['lancamentos']);
    foreach ($lAntes as $id => $l) {
        if ((string) ($l['status'] ?? '') !== 'pago') {
            continue;
        }
        $novo = $lDepois[$id] ?? null;
        if ($novo === null) {
            return 'O lançamento #' . $id . ' já foi pago e não pode ser excluído. Registre um estorno.';
        }
        if ((string) ($novo['status'] ?? '') !== 'pago' || abs((float) $novo['valor'] - (float) $l['valor']) > 0.004) {
            $ref = (int) ($l['transfer_id'] ?? 0) > 0 ? ' (reserva #' . (int) $l['transfer_id'] . ')' : '';
            return 'O lançamento #' . $id . $ref . ' já foi pago: valor e situação não podem mudar. Registre um estorno.';
        }
    }

    $refAntes = referencias_quebradas($antes);
    foreach (referencias_quebradas($depois) as $chave => $texto) {
        if (!isset($refAntes[$chave])) {
            return $texto . ' Nada foi salvo.';
        }
    }

    $confAntes = conflitos_motoristas($antes);
    foreach (conflitos_motoristas($depois) as $chave => [$mid, $a, $b]) {
        if (isset($confAntes[$chave])) {
            continue;
        }
        $nome = (string) ($motPorId[$mid]['nome'] ?? 'O motorista');
        $tz = new DateTimeZone('America/Sao_Paulo');
        $rotulo = static function (array $o) use ($tAntes, $tz): string {
            $quando = (new DateTimeImmutable('@' . $o['inicio']))->setTimezone($tz)->format('d/m \à\s H:i');
            return (isset($tAntes[$o['trip']]) ? 'a reserva #' . $o['trip'] : 'esta nova reserva') . ' (' . $quando . ')';
        };
        [$primeiro, $segundo] = isset($tAntes[$a['trip']]) ? [$a, $b] : [$b, $a];
        return 'Conflito de agenda: ' . $nome . ' já tem ' . $rotulo($primeiro) . ' e não pode assumir ' . $rotulo($segundo)
            . '. O intervalo mínimo entre viagens do mesmo motorista é de ' . intervalo_motorista($depois) . ' minutos. Escolha outro motorista ou outro horário. Nada foi salvo.';
    }
    return null;
}
