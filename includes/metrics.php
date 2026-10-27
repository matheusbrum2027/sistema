<?php
declare(strict_types=1);

function today(): DateTimeImmutable
{
    static $t = null;
    if (!$t instanceof DateTimeImmutable) {
        $t = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->setTime(0, 0, 0);
    }
    return $t;
}

function mes_curto(DateTimeImmutable $d): string
{
    $m = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return $m[(int) $d->format('n')];
}

function mes_longo(DateTimeImmutable $d): string
{
    $m = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $m[(int) $d->format('n')];
}

function date_in(string $date, DateTimeImmutable $start, DateTimeImmutable $end): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10), new DateTimeZone('America/Sao_Paulo'));
    if (!$d) {
        return false;
    }
    return $d >= $start->setTime(0, 0, 0) && $d <= $end->setTime(0, 0, 0);
}

function range_bounds(string $range, DateTimeImmutable $today): array
{
    $today = $today->setTime(0, 0, 0);
    switch ($range) {
        case 'hoje':
            $start = $today;
            $prevStart = $today->modify('-1 day');
            $prevEnd = $prevStart;
            break;
        case '7d':
            $start = $today->modify('-6 days');
            $prevEnd = $start->modify('-1 day');
            $prevStart = $prevEnd->modify('-6 days');
            break;
        case '90d':
            $start = $today->modify('-89 days');
            $prevEnd = $start->modify('-1 day');
            $prevStart = $prevEnd->modify('-89 days');
            break;
        case '12m':
            $start = $today->modify('first day of this month')->modify('-11 months');
            $prevEnd = $start->modify('-1 day');
            $prevStart = $prevEnd->modify('first day of this month')->modify('-11 months');
            break;
        default:
            $start = $today->modify('-29 days');
            $prevEnd = $start->modify('-1 day');
            $prevStart = $prevEnd->modify('-29 days');
            break;
    }
    return [$start, $today, $prevStart, $prevEnd];
}

function range_phrase(string $range): string
{
    return match ($range) {
        'hoje' => 'hoje',
        '7d' => 'últimos 7 dias',
        '90d' => 'últimos 90d',
        '12m' => 'últimos 12 meses',
        default => 'últimos 30d',
    };
}

function range_short(string $range): string
{
    return match ($range) {
        'hoje' => 'HOJE',
        '7d' => '7D',
        '90d' => '90D',
        '12m' => '12M',
        default => '30D',
    };
}

function month_labels_ending(DateTimeImmutable $today, int $count): array
{
    $endMonth = $today->modify('first day of this month');
    $startMonth = $endMonth->modify('-' . ($count - 1) . ' months');
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $m = $startMonth->modify('+' . $i . ' months');
        $out[] = ['key' => $m->format('Y-m'), 'label' => mes_curto($m)];
    }
    return $out;
}

function moving_avg(array $data, int $w = 3): array
{
    $out = [];
    $n = count($data);
    for ($i = 0; $i < $n; $i++) {
        $from = max(0, $i - $w + 1);
        $slice = array_slice($data, $from, $i - $from + 1);
        $out[] = array_sum($slice) / max(1, count($slice));
    }
    return $out;
}

function pct_change(float $cur, float $prev): float
{
    if (abs($prev) < 0.00001) {
        return abs($cur) < 0.00001 ? 0.0 : 100.0;
    }
    return (($cur - $prev) / $prev) * 100;
}

function active_trips(array $transfers): array
{
    return array_values(array_filter($transfers, static fn(array $t): bool => $t['status'] !== 'cancelada'));
}

function trips_between(array $trips, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    return array_values(array_filter($trips, static fn(array $t): bool => date_in($t['data'], $start, $end)));
}

function build_metrics(array $db, string $range): array
{
    $today = today();
    [$start, $end, $pStart, $pEnd] = range_bounds($range, $today);
    $trips = active_trips($db['transfers']);
    $cur = trips_between($trips, $start, $end);
    $prev = trips_between($trips, $pStart, $pEnd);
    $receita = (float) array_sum(array_column($cur, 'valor'));
    $receitaPrev = (float) array_sum(array_column($prev, 'valor'));
    $n = count($cur);
    $nPrev = count($prev);

    $mesStart = $today->modify('first day of this month');
    $mesRows = trips_between($trips, $mesStart, $today);
    $hojeDia = $today->format('Y-m-d');
    $hojeRows = array_values(array_filter($trips, static function (array $t) use ($hojeDia): bool {
        if (($t['data'] ?? '') === $hojeDia) {
            return true;
        }
        $volta = (string) (($t['data_volta'] ?? '') !== '' ? $t['data_volta'] : ($t['data'] ?? ''));
        return ($t['tipo'] ?? '') === 'ida_volta' && $volta === $hojeDia;
    }));

    $veiculos = $db['veiculos'];
    $frotaTotal = count($veiculos);
    $countV = static function (string $status) use ($veiculos): int {
        return count(array_filter($veiculos, static fn(array $v): bool => $v['status'] === $status));
    };
    $countT = static function (array $rows, array $statuses): int {
        return count(array_filter($rows, static fn(array $t): bool => in_array($t['status'], $statuses, true)));
    };

    $months = month_labels_ending($today, 12);
    $recSeries = [];
    $viaSeries = [];
    foreach ($months as $m) {
        $rows = array_values(array_filter($trips, static fn(array $t): bool => str_starts_with($t['data'], $m['key'])));
        $recSeries[] = (float) array_sum(array_column($rows, 'valor'));
        $viaSeries[] = count($rows);
    }

    $cmonths = month_labels_ending($today, 6);
    $chT = [];
    $chG = [];
    $chM = [];
    foreach ($cmonths as $m) {
        $rows = array_values(array_filter($trips, static fn(array $t): bool => str_starts_with($t['data'], $m['key'])));
        $gRows = array_values(array_filter($db['grupos'], static fn(array $g): bool => str_starts_with((string) $g['data'], $m['key'])));
        $chT[] = count(array_filter($rows, static fn(array $t): bool => ($t['canal'] ?? 'transfer') === 'transfer'));
        $chM[] = count(array_filter($rows, static fn(array $t): bool => ($t['canal'] ?? '') === 'motoristas'));
        $chG[] = count(array_filter($rows, static fn(array $t): bool => ($t['canal'] ?? '') === 'grupos')) + count($gRows);
    }

    $ultimos = $db['transfers'];
    usort($ultimos, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
    $ultimos = array_slice($ultimos, 0, 8);

    $dStart = $today->modify('-89 days');
    $dRows = trips_between($trips, $dStart, $today);
    $byDest = [];
    foreach ($dRows as $t) {
        $k = $t['destino'];
        if (!isset($byDest[$k])) {
            $byDest[$k] = ['destino' => $k, 'viagens' => 0, 'valor' => 0.0];
        }
        $byDest[$k]['viagens']++;
        $byDest[$k]['valor'] += (float) $t['valor'];
    }
    $destinos = array_values($byDest);
    usort($destinos, static function (array $a, array $b): int {
        return $b['viagens'] <=> $a['viagens'] ?: $b['valor'] <=> $a['valor'];
    });
    $destinos = array_slice($destinos, 0, 5);

    $periodoFunil = trips_between($trips, $dStart, $today);
    $leads = $periodoFunil;

    $grupos = $db['grupos'];
    usort($grupos, static fn(array $a, array $b): int => strcmp((string) $b['data'], (string) $a['data']));

    return [
        'phrase' => range_phrase($range),
        'short' => range_short($range),
        'frota_ativos' => $countV('ativo'),
        'frota_total' => $frotaTotal,
        'frota_manut' => $countV('manutencao'),
        'frota_inativos' => $countV('inativo'),
        'hoje_total' => count($hojeRows),
        'hoje_conc' => $countT($hojeRows, ['concluida']),
        'hoje_and' => $countT($hojeRows, ['andamento']),
        'hoje_pend' => $countT($hojeRows, ['pendente', 'reserva']),
        'viagens' => $n,
        'viagens_delta' => pct_change((float) $n, (float) $nPrev),
        'receita' => $receita,
        'receita_delta' => pct_change($receita, $receitaPrev),
        'receita_mes' => (float) array_sum(array_column($mesRows, 'valor')),
        'receita_hoje' => (float) array_sum(array_column($hojeRows, 'valor')),
        'ticket' => $n > 0 ? $receita / $n : 0.0,
        'utilizacao' => $frotaTotal > 0 ? ($n / $frotaTotal) * 100 : 0.0,
        'chart_labels' => array_column($months, 'label'),
        'chart_receita' => $recSeries,
        'chart_viagens' => $viaSeries,
        'chart_trend' => moving_avg($recSeries, 3),
        'canal_labels' => array_column($cmonths, 'label'),
        'canal_transfer' => $chT,
        'canal_grupos' => $chG,
        'canal_motoristas' => $chM,
        'ultimos' => $ultimos,
        'total_transfers' => count($db['transfers']),
        'grupos' => array_slice($grupos, 0, 4),
        'destinos' => $destinos,
        'funil_sol' => count($db['inbox'] ?? []),
        'funil_res' => count($leads),
        'funil_con' => count(array_filter($leads, static fn(array $t): bool => $t['status'] === 'concluida')),
    ];
}

function finance_snapshot(array $db): array
{
    $today = today();
    $mes = $today->format('Y-m');
    $receber = array_values(array_filter(
        $db['transfers'],
        static fn(array $t): bool => empty($t['pago']) && $t['status'] !== 'cancelada'
    ));
    $pagar = array_values(array_filter($db['lancamentos'], static fn(array $l): bool => $l['tipo'] === 'saida'));
    $abertos = array_values(array_filter($pagar, static fn(array $l): bool => $l['status'] !== 'pago'));
    $pagoMes = 0.0;
    foreach ($pagar as $l) {
        if ($l['status'] === 'pago' && str_starts_with((string) $l['data'], $mes)) {
            $pagoMes += (float) $l['valor'];
        }
    }
    $recebidoMes = 0.0;
    foreach ($db['transfers'] as $t) {
        if (!empty($t['pago']) && str_starts_with((string) ($t['pago_em'] ?? ''), $mes)) {
            $recebidoMes += (float) $t['valor'];
        }
    }
    $months = month_labels_ending($today, 6);
    $entradas = [];
    $saidas = [];
    $trips = active_trips($db['transfers']);
    foreach ($months as $m) {
        $erows = array_values(array_filter($trips, static fn(array $t): bool => str_starts_with($t['data'], $m['key'])));
        $srows = array_values(array_filter($pagar, static fn(array $l): bool => str_starts_with((string) $l['data'], $m['key'])));
        $entradas[] = (float) array_sum(array_column($erows, 'valor'));
        $saidas[] = (float) array_sum(array_column($srows, 'valor'));
    }
    return [
        'receber' => $receber,
        'total_receber' => (float) array_sum(array_column($receber, 'valor')),
        'pagar' => $pagar,
        'abertos' => $abertos,
        'total_pagar' => (float) array_sum(array_column($abertos, 'valor')),
        'pago_mes' => $pagoMes,
        'recebido_mes' => $recebidoMes,
        'months' => array_column($months, 'label'),
        'entradas' => $entradas,
        'saidas' => $saidas,
    ];
}

function ledger(array $db): array
{
    $rows = [];
    foreach ($db['lancamentos'] as $l) {
        $l['origem'] = 'manual';
        $rows[] = $l;
    }
    foreach ($db['transfers'] as $t) {
        if ($t['status'] === 'cancelada') {
            continue;
        }
        $rows[] = [
            'id' => 'V' . $t['id'],
            'tipo' => 'entrada',
            'descricao' => 'Viagem #' . $t['id'] . ' · ' . $t['cliente'],
            'categoria' => 'Transfer',
            'valor' => $t['valor'],
            'data' => !empty($t['pago']) ? (string) ($t['pago_em'] ?? $t['data']) : $t['data'],
            'status' => !empty($t['pago']) ? 'recebido' : 'previsto',
            'origem' => 'viagem',
            'ref' => $t['id'],
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp((string) $b['data'], (string) $a['data']));
    return $rows;
}
