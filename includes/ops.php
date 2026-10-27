<?php
declare(strict_types=1);

function base_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8091');
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $dir = rtrim($dir, '/');
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function hook_url(string $secret): string
{
    return base_url() . '/index.php?hook=' . rawurlencode($secret);
}

function short_place(string $place): string
{
    $place = trim($place);
    if (mb_strlen($place) <= 42) {
        return $place;
    }
    return mb_substr($place, 0, 40) . '…';
}

function momento_passou(string $data, string $hora): bool
{
    $hora = $hora !== '' ? $hora : '00:00';
    $quando = DateTimeImmutable::createFromFormat('Y-m-d H:i', $data . ' ' . $hora, new DateTimeZone('America/Sao_Paulo'));
    if (!$quando) {
        return false;
    }
    $agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    return $quando < $agora;
}

function agenda_legs(array $trip): array
{
    $tipo = (string) ($trip['tipo'] ?? 'ida');
    $legs = [];
    if ($tipo === 'trajetos' && is_array($trip['trajetos'] ?? null) && $trip['trajetos'] !== []) {
        foreach ($trip['trajetos'] as $i => $trecho) {
            $legs[] = [
                'data' => (string) ($trecho['data'] ?? $trip['data']),
                'hora' => (string) ($trecho['hora'] ?? ''),
                'kind' => 'ida',
                'label' => 'T' . ($i + 1),
                'place' => short_place((string) ($trecho['desembarque'] ?? $trecho['embarque'] ?? '')),
            ];
        }
    } elseif ($tipo === 'hora') {
        $legs[] = [
            'data' => (string) $trip['data'],
            'hora' => (string) ($trip['hora'] ?? ''),
            'kind' => 'ida',
            'label' => 'HORA',
            'place' => short_place((string) $trip['destino']),
        ];
    } elseif ($tipo !== 'volta') {
        $legs[] = [
            'data' => (string) $trip['data'],
            'hora' => (string) ($trip['hora'] ?? ''),
            'kind' => 'ida',
            'label' => ($trip['conjunto'] ?? '') === 'trajetos' ? 'T' . max(1, (int) ($trip['ordem'] ?? 1)) : 'IDA',
            'place' => short_place((string) $trip['destino']),
        ];
    }
    if ($tipo === 'volta' || $tipo === 'ida_volta') {
        $data = (string) ($trip['data_volta'] ?? '');
        if ($data === '') {
            $data = (string) $trip['data'];
        }
        $hora = (string) ($trip['hora_volta'] ?? '');
        if ($hora === '') {
            $hora = (string) ($trip['hora'] ?? '');
        }
        $legs[] = [
            'data' => $data,
            'hora' => $hora,
            'kind' => 'volta',
            'label' => 'VOLTA',
            'place' => short_place((string) $trip['origem']),
        ];
    }
    foreach ($legs as &$leg) {
        $leg['passou'] = momento_passou($leg['data'], $leg['hora']);
        $leg['id'] = (int) $trip['id'];
    }
    unset($leg);
    return $legs;
}

function tipo_badges(array $trip): string
{
    $tipo = (string) ($trip['tipo'] ?? 'ida');
    $conjunto = (string) ($trip['conjunto'] ?? '');
    $html = match (true) {
        $tipo === 'hora' => '<span class="tag tag-ida">Por hora</span>',
        $tipo === 'trajetos' => '<span class="tag tag-ida">Trajetos</span>',
        $tipo === 'volta' && $conjunto === 'ida_volta' => '<span class="tag tag-volta">Ida e Volta · Volta</span>',
        $tipo === 'volta' => '<span class="tag tag-volta">Volta</span>',
        $tipo === 'ida' && $conjunto === 'trajetos' => '<span class="tag tag-ida">Destino ' . max(1, (int) ($trip['ordem'] ?? 1)) . '</span>',
        $tipo === 'ida' && $conjunto === 'ida_volta' => '<span class="tag tag-ida">Ida e Volta · Ida</span>',
        $tipo === 'ida_volta' => '<span class="tag tag-ida">Ida e Volta · Ida</span> <span class="tag tag-volta">Volta</span>',
        default => '<span class="tag tag-ida">Somente Ida</span>',
    };
    if (!empty($trip['faturado'])) {
        $html .= ' <span class="tag tag-fat">Faturado</span>';
    }
    return $html;
}

function perfil_cliente(array $c): int
{
    $campos = ['nome', 'tipo', 'email', 'telefone', 'documento', 'cidade', 'endereco'];
    $ok = 0;
    foreach ($campos as $campo) {
        if (trim((string) ($c[$campo] ?? '')) !== '') {
            $ok++;
        }
    }
    return (int) round($ok / count($campos) * 100);
}

function portal_label(array $mot): string
{
    return trim((string) ($mot['senha'] ?? '')) !== '' ? 'Senha OK' : 'Pendente';
}

function slice_page(array $rows, int $per = 10): array
{
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $per));
    $page = max(1, (int) ($_GET['pag'] ?? 1));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $per;
    return [
        'rows' => array_slice($rows, $offset, $per),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'from' => $total === 0 ? 0 : $offset + 1,
        'to' => min($total, $offset + $per),
    ];
}

function pager(string $page, array $pg, array $query = []): string
{
    $query['p'] = $page;
    $mk = static function (int $n) use ($query): string {
        $query['pag'] = $n;
        return 'index.php?' . http_build_query($query);
    };
    $prev = $pg['page'] > 1 ? '<a class="btn btn-sm" href="' . e($mk($pg['page'] - 1)) . '">Anterior</a>' : '<span class="btn btn-sm" aria-disabled="true">Anterior</span>';
    $next = $pg['page'] < $pg['pages'] ? '<a class="btn btn-sm" href="' . e($mk($pg['page'] + 1)) . '">Próxima</a>' : '<span class="btn btn-sm" aria-disabled="true">Próxima</span>';
    return '<div class="pager"><span>Mostrando ' . (int) $pg['from'] . ' a ' . (int) $pg['to'] . ' de ' . (int) $pg['total'] . ' · Página ' . (int) $pg['page'] . ' de ' . (int) $pg['pages'] . '</span><span class="pager-nav">' . $prev . $next . '</span></div>';
}

function nome_motorista(array $db, mixed $id): string
{
    foreach ($db['motoristas'] as $mot) {
        if ((int) $mot['id'] === (int) $id) {
            return (string) $mot['nome'];
        }
    }
    return '—';
}

function find_lanc(array $db, int $tripId, string $papel): ?int
{
    foreach ($db['lancamentos'] as $i => $row) {
        if ((int) ($row['transfer_id'] ?? 0) === $tripId && (string) ($row['papel'] ?? '') === $papel) {
            return (int) $i;
        }
    }
    return null;
}

function sync_trip_finance(array &$db, int $tripId): void
{
    $ti = index_of($db['transfers'], $tripId);
    if ($ti === null) {
        return;
    }
    $t = $db['transfers'][$ti];
    $recI = find_lanc($db, $tripId, 'receita');
    if ((string) $t['status'] === 'cancelada') {
        if ($recI !== null && ($db['lancamentos'][$recI]['status'] ?? '') !== 'pago') {
            $db['lancamentos'][$recI]['status'] = 'cancelado';
        }
        $repI = find_lanc($db, $tripId, 'repasse');
        if ($repI !== null && ($db['lancamentos'][$repI]['status'] ?? '') !== 'pago') {
            $db['lancamentos'][$repI]['status'] = 'cancelado';
        }
        return;
    }
    $receita = [
        'tipo' => 'entrada',
        'descricao' => 'Reserva transfer #' . $t['id'],
        'categoria' => 'reserva_transfer',
        'valor' => (float) $t['valor'],
        'data' => (string) $t['data'],
        'vencimento' => (string) $t['data'],
        'status' => !empty($t['pago']) ? 'pago' : 'pendente',
        'linha' => 'Principal',
        'origem' => 'transfer',
        'meio' => (string) ($t['meio'] ?? ''),
        'baixa' => !empty($t['pago']) ? (string) ($t['pago_em'] ?: $t['data']) : '',
        'transfer_id' => (int) $t['id'],
        'papel' => 'receita',
        'cliente' => (string) $t['cliente'],
    ];
    if ($recI === null) {
        $receita['id'] = $db['seq']['lancamento']++;
        $db['lancamentos'][] = $receita;
    } else {
        $receita['id'] = $db['lancamentos'][$recI]['id'];
        if (($db['lancamentos'][$recI]['status'] ?? '') === 'pago' && empty($t['pago'])) {
            $receita['status'] = 'pago';
            $receita['baixa'] = (string) ($db['lancamentos'][$recI]['baixa'] ?? '');
            $receita['meio'] = (string) ($db['lancamentos'][$recI]['meio'] ?? '');
        }
        $db['lancamentos'][$recI] = $receita;
    }
    $repI = find_lanc($db, $tripId, 'repasse');
    $quer = (string) $t['status'] === 'concluida' && (float) ($t['repasse'] ?? 0) > 0;
    if ($quer) {
        $repasse = [
            'tipo' => 'saida',
            'descricao' => 'Repasse motorista · Reserva #' . $t['id'],
            'categoria' => 'repasse_motorista',
            'valor' => (float) $t['repasse'],
            'data' => (string) $t['data'],
            'vencimento' => (string) $t['data'],
            'status' => 'pendente',
            'linha' => 'Principal',
            'origem' => 'repasse',
            'meio' => '',
            'baixa' => '',
            'transfer_id' => (int) $t['id'],
            'papel' => 'repasse',
            'cliente' => nome_motorista($db, $t['motorista_id'] ?? 0),
        ];
        if ($repI === null) {
            $repasse['id'] = $db['seq']['lancamento']++;
            $db['lancamentos'][] = $repasse;
        } else {
            $repasse['id'] = $db['lancamentos'][$repI]['id'];
            if (($db['lancamentos'][$repI]['status'] ?? '') === 'pago') {
                $repasse['status'] = 'pago';
                $repasse['baixa'] = (string) ($db['lancamentos'][$repI]['baixa'] ?? $t['data']);
                $repasse['meio'] = (string) ($db['lancamentos'][$repI]['meio'] ?? '');
            }
            $db['lancamentos'][$repI] = $repasse;
        }
    } elseif ($repI !== null && ($db['lancamentos'][$repI]['status'] ?? '') !== 'pago') {
        $db['lancamentos'][$repI]['status'] = 'cancelado';
    }
}

function blank_trip(int $id): array
{
    return [
        'id' => $id,
        'cliente_id' => null,
        'cliente' => '',
        'telefone' => '',
        'email' => '',
        'tipo' => 'ida',
        'categoria' => 'SED',
        'origem' => '',
        'destino' => '',
        'data' => '',
        'hora' => '',
        'data_volta' => '',
        'hora_volta' => '',
        'valor' => 0.0,
        'repasse' => 0.0,
        'status' => 'pendente',
        'canal' => 'transfer',
        'pax' => 1,
        'motorista_id' => null,
        'veiculo_id' => null,
        'pago' => false,
        'pago_em' => null,
        'meio' => '',
        'faturado' => false,
        'fatura' => null,
        'faturado_em' => null,
        'contrato' => null,
        'contrato_em' => null,
        'no_funil' => true,
        'obs' => '',
        'criado_em' => today()->format('Y-m-d'),
        'linha' => 'Principal',
        'documento' => '',
        'cupom' => '',
        'quem' => 'motorista',
        'motorista_volta_id' => null,
        'extras' => [],
        'pagamentos' => [],
        'esconder_valores' => false,
        'trajetos' => [],
        'qtd_horas' => '',
        'conjunto' => '',
    ];
}

function categorias_veiculo(): array
{
    return [
        'VAN' => 'VAN (VAN)',
        'MIC.VAN' => 'MICRO-ÔNIBUS (MIC.VAN)',
        '07LUG.' => 'VEÍCULOS 07 LUGARES (07LUG.)',
        'MINIVAN' => 'MINI VAN (MINIVAN)',
        'SED' => 'SEDAN (SED)',
        'HAT' => 'HATCH (HAT)',
        'ONIBUS' => 'ÔNIBUS (ONIBUS)',
    ];
}

function meios_pagamento(): array
{
    return [
        'sinal' => 'Sinal',
        'pix' => 'PIX',
        'credito' => 'Cartão de Crédito',
        'debito' => 'Cartão de Débito',
        'dinheiro' => 'Dinheiro',
        'transferencia' => 'Transferência Bancária',
        'boleto' => 'Boleto',
        'cortesia' => 'Cortesia',
        'permuta' => 'Permuta',
        'faturado_empresa' => 'Faturado Empresa',
        'outros' => 'Outros',
    ];
}

function viagem_do_motorista(array $trip, int $motoristaId): bool
{
    if ($motoristaId <= 0) {
        return false;
    }
    if ((int) ($trip['motorista_id'] ?? 0) === $motoristaId) {
        return true;
    }
    foreach ($trip['extras'] ?? [] as $extra) {
        if ((int) $extra === $motoristaId) {
            return true;
        }
    }
    return false;
}

function montar_reservas(array &$db, ?array $current): array
{
    $nome = clip(post('cliente'), 80);
    $email = clip(post('email'), 80);
    $telefone = clip(post('telefone'), 30);
    $documento = clip(post('documento'), 20);
    if ($nome === '' || $email === '' || $telefone === '' || $documento === '') {
        return ['Preencha nome, CPF/CNPJ, e-mail e telefone do cliente.', []];
    }
    $cid = (int) post('cliente_id');
    $novo = post('origem_cliente') === 'novo';
    if (!$novo && $cid > 0 && index_of($db['clientes'], $cid) !== null) {
        $clienteId = $cid;
    } else {
        $clienteId = null;
        foreach ($db['clientes'] as $c) {
            if ($documento !== '' && (string) ($c['documento'] ?? '') === $documento) {
                $clienteId = (int) $c['id'];
                break;
            }
        }
        if ($clienteId === null && !$novo) {
            foreach ($db['clientes'] as $c) {
                if (mb_strtolower((string) $c['nome']) === mb_strtolower($nome)) {
                    $clienteId = (int) $c['id'];
                    break;
                }
            }
        }
        if ($clienteId === null && $novo) {
            $clienteId = $db['seq']['cliente']++;
            $db['clientes'][] = [
                'id' => $clienteId,
                'nome' => $nome,
                'tipo' => strlen(preg_replace('/\D+/', '', $documento) ?? '') > 11 ? 'PJ' : 'PF',
                'email' => $email,
                'telefone' => $telefone,
                'documento' => $documento,
                'cidade' => '',
                'uf' => '',
                'empresa' => '',
                'endereco' => '',
            ];
        }
    }
    $tipo = one_of(post('tipo_viagem'), ['ida', 'ida_volta', 'trajetos', 'hora'], 'ida');
    $categoria = one_of(post('categoria'), array_keys(categorias_veiculo()), 'SED');
    $pagamentos = [];
    $soma = 0.0;
    foreach (meios_pagamento() as $chave => $rotulo) {
        if (post('pag_' . $chave) === '1') {
            $parte = max(0, post_money('pag_valor_' . $chave));
            $pagamentos[$chave] = $parte;
            $soma += $parte;
        }
    }
    $manual = max(0, post_money('valor_manual'));
    $valor = $manual > 0 ? $manual : $soma;
    if ($valor <= 0) {
        return ['Informe o valor total ou o valor de cada meio de pagamento.', []];
    }
    $quem = one_of(post('quem'), ['motorista', 'parceiro'], 'motorista');
    $mid = (int) post('motorista_id');
    $motorista = ($quem === 'motorista' && $mid > 0 && index_of($db['motoristas'], $mid) !== null) ? $mid : null;
    $mvolta = (int) post('motorista_volta_id');
    $motoristaVolta = ($mvolta > 0 && index_of($db['motoristas'], $mvolta) !== null) ? $mvolta : $motorista;
    $extras = [];
    foreach ((array) ($_POST['extras'] ?? []) as $extra) {
        $eid = (int) $extra;
        if ($eid > 0 && $eid !== (int) $motorista && index_of($db['motoristas'], $eid) !== null) {
            $extras[] = $eid;
        }
    }
    $status = one_of(post('status'), ['pendente', 'reserva', 'andamento', 'concluida', 'cancelada'], (string) ($current['status'] ?? 'pendente'));
    $repasse = max(0, post_money('repasse'));
    $faturado = post('faturado') === 'sim';
    $pax = max(1, min(40, (int) post('pax') ?: 1));
    $base = $current ?? blank_trip(0);
    $base['cliente_id'] = $clienteId;
    $base['cliente'] = $nome;
    $base['email'] = $email;
    $base['telefone'] = $telefone;
    $base['documento'] = $documento;
    $base['categoria'] = $categoria;
    $base['quem'] = $quem;
    $base['motorista_id'] = $motorista;
    $base['motorista_volta_id'] = null;
    $base['extras'] = $extras;
    $base['pagamentos'] = $pagamentos;
    $base['valor'] = $valor;
    $base['repasse'] = $repasse;
    $base['status'] = $status;
    $base['pax'] = $pax;
    $obsGeral = clip(post('obs'), 800);
    $obsIda = clip(post('obs_ida'), 800);
    $base['cupom'] = clip(post('cupom'), 40);
    $base['obs'] = $obsGeral !== '' ? $obsGeral : $obsIda;
    $base['esconder_valores'] = post('esconder') === '1';
    $base['faturado'] = $faturado;
    $base['faturado_em'] = $faturado ? (string) ($base['faturado_em'] ?: today()->format('Y-m-d')) : null;
    $base['meio'] = $pagamentos === [] ? '' : (count($pagamentos) === 1 ? (string) array_key_first($pagamentos) : 'misto');
    $embarque = clip(post('embarque'), 180);
    $desembarque = clip(post('desembarque'), 180);
    $data = post_date('data');
    $hora = post_time('hora');
    if ($tipo === 'hora') {
        $inicio = clip(post('inicio'), 180);
        $fim = clip(post('encerramento'), 180);
        $dataHora = post_date('data_hora') ?: $data;
        $horaHora = post_time('hora_servico') ?: $hora;
        if ($inicio === '' || $dataHora === '') {
            return ['Informe o endereço de início e a data da hora.', []];
        }
        $paxHora = (int) post('pax_hora');
        $base['tipo'] = 'hora';
        $base['conjunto'] = '';
        $base['origem'] = $inicio;
        $base['destino'] = $fim !== '' ? $fim : $inicio;
        $base['data'] = $dataHora;
        $base['hora'] = $horaHora !== '' ? $horaHora : '00:00';
        $base['pax'] = $paxHora > 0 ? max(1, min(40, $paxHora)) : $base['pax'];
        $base['cupom'] = clip(post('cupom_hora'), 40) ?: $base['cupom'];
        $base['qtd_horas'] = clip(post('qtd_horas'), 10);
        $base['trajetos'] = [];
        $base['obs'] = clip(post('itinerario'), 800) ?: $base['obs'];
        return [null, [$base]];
    }
    if ($tipo === 'trajetos') {
        $trechos = [];
        foreach ((array) ($_POST['trajetos'] ?? []) as $bruto) {
            if (!is_array($bruto)) {
                continue;
            }
            $de = clip((string) ($bruto['embarque'] ?? ''), 180);
            $para = clip((string) ($bruto['desembarque'] ?? ''), 180);
            $dia = (string) ($bruto['data'] ?? '');
            $diaOk = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);
            if ($de === '' && $para === '' && $dia === '') {
                continue;
            }
            if ($de === '' || $para === '' || !$diaOk || $diaOk->format('Y-m-d') !== $dia) {
                return ['Cada trajeto precisa de embarque, desembarque e data.', []];
            }
            $horaTrecho = (string) ($bruto['hora'] ?? '');
            $trechos[] = [
                'embarque' => $de,
                'desembarque' => $para,
                'data' => $dia,
                'hora' => preg_match('/^\d{2}:\d{2}$/', $horaTrecho) ? $horaTrecho : '00:00',
                'pax' => max(1, min(40, (int) ($bruto['pax'] ?? 1))),
            ];
        }
        if ($trechos === []) {
            return ['Cada destino precisa de embarque, desembarque e data.', []];
        }
        if ($current === null && count($trechos) < 2) {
            return ['Inclua pelo menos dois destinos.', []];
        }
        $montarTrecho = static function (array $base, array $trecho, int $ordem, float $valorParte, float $repasseParte): array {
            $item = $base;
            $item['tipo'] = 'ida';
            $item['conjunto'] = 'trajetos';
            $item['ordem'] = $ordem;
            $item['origem'] = $trecho['embarque'];
            $item['destino'] = $trecho['desembarque'];
            $item['data'] = $trecho['data'];
            $item['hora'] = $trecho['hora'];
            $item['pax'] = $trecho['pax'];
            $item['trajetos'] = [];
            $item['qtd_horas'] = '';
            $item['valor'] = $valorParte;
            $item['repasse'] = $repasseParte;
            return $item;
        };
        if ($current !== null) {
            return [null, [$montarTrecho($base, $trechos[0], max(1, (int) ($current['ordem'] ?? 1)), $valor, $repasse)]];
        }
        $n = count($trechos);
        $restoValor = $valor;
        $restoRepasse = $repasse;
        $saida = [];
        foreach ($trechos as $i => $trecho) {
            $ultimo = $i === $n - 1;
            $parteValor = $ultimo ? round($restoValor, 2) : round($valor / $n, 2);
            $parteRepasse = $ultimo ? round($restoRepasse, 2) : round($repasse / $n, 2);
            $restoValor = round($restoValor - $parteValor, 2);
            $restoRepasse = round($restoRepasse - $parteRepasse, 2);
            $saida[] = $montarTrecho($base, $trecho, $i + 1, $parteValor, $parteRepasse);
        }
        return [null, $saida];
    }
    if ($embarque === '' || $desembarque === '' || $data === '') {
        return ['Preencha embarque, desembarque e data da ida.', []];
    }
    $base['origem'] = $embarque;
    $base['destino'] = $desembarque;
    $base['data'] = $data;
    $base['hora'] = $hora !== '' ? $hora : '00:00';
    $base['trajetos'] = [];
    $base['qtd_horas'] = '';
    if ($tipo !== 'ida_volta' || $current !== null) {
        $base['tipo'] = $current !== null && (string) ($current['tipo'] ?? '') === 'volta' ? 'volta' : 'ida';
        $base['conjunto'] = $tipo === 'ida_volta' ? 'ida_volta' : (string) ($current['conjunto'] ?? '');
        if ($tipo !== 'ida_volta') {
            $base['conjunto'] = '';
        }
        return [null, [$base]];
    }
    $voltaDe = clip(post('volta_embarque'), 180) ?: $desembarque;
    $voltaPara = clip(post('volta_desembarque'), 180) ?: $embarque;
    $dataVolta = post_date('data_volta') ?: $data;
    $horaVolta = post_time('hora_volta') ?: $base['hora'];
    $paxVolta = (int) post('pax_volta');
    $ida = $base;
    $ida['tipo'] = 'ida';
    $ida['conjunto'] = 'ida_volta';
    $ida['valor'] = round($valor / 2, 2);
    $ida['repasse'] = round($repasse / 2, 2);
    $volta = $base;
    $volta['tipo'] = 'volta';
    $volta['conjunto'] = 'ida_volta';
    $volta['origem'] = $voltaDe;
    $volta['destino'] = $voltaPara;
    $volta['data'] = $dataVolta;
    $volta['hora'] = $horaVolta;
    $volta['pax'] = $paxVolta > 0 ? max(1, min(40, $paxVolta)) : $pax;
    $volta['cupom'] = clip(post('cupom_volta'), 40);
    $volta['obs'] = clip(post('obs_volta'), 800) ?: $base['obs'];
    $volta['motorista_id'] = $motoristaVolta;
    $volta['valor'] = round($valor - $ida['valor'], 2);
    $volta['repasse'] = round($repasse - $ida['repasse'], 2);
    return [null, [$ida, $volta]];
}

function motorista_campos(): array
{
    return [
        'cpf' => clip(post('cpf'), 20),
        'rg' => clip(post('rg'), 20),
        'nascimento' => post_date('nascimento'),
        'email' => clip(post('email'), 80),
        'uf' => strtoupper(clip(post('uf'), 2)),
        'cidade' => clip(post('cidade'), 60),
        'cep' => clip(post('cep'), 12),
        'logradouro' => clip(post('logradouro'), 120),
        'numero' => clip(post('numero'), 12),
        'complemento' => clip(post('complemento'), 40),
        'bairro' => clip(post('bairro'), 60),
        'cnh' => clip(post('cnh'), 20),
        'categoria' => one_of(post('categoria'), ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'], 'B'),
        'cnh_validade' => post_date('cnh_validade'),
        'obs' => clip(post('obs'), 800),
        'pix' => clip(post('pix'), 80),
        'banco' => clip(post('banco'), 40),
        'agencia' => clip(post('agencia'), 12),
        'conta' => clip(post('conta'), 20),
    ];
}

function cliente_from_post(): array
{
    $nome = clip(post('nome'), 80);
    if ($nome === '') {
        return ['Informe o nome do cliente.', null];
    }
    return [null, [
        'nome' => $nome,
        'tipo' => one_of(post('tipo'), ['PF', 'PJ'], 'PF'),
        'email' => clip(post('email'), 80),
        'telefone' => clip(post('telefone'), 30),
        'documento' => clip(post('documento'), 20),
        'rg' => clip(post('rg'), 20),
        'nascimento' => post_date('nascimento'),
        'cidade' => clip(post('cidade'), 60),
        'uf' => strtoupper(clip(post('uf'), 2)),
        'cep' => clip(post('cep'), 12),
        'logradouro' => clip(post('logradouro'), 120),
        'numero' => clip(post('numero'), 12),
        'complemento' => clip(post('complemento'), 40),
        'bairro' => clip(post('bairro'), 60),
        'empresa' => clip(post('empresa'), 80),
        'endereco' => clip(post('endereco'), 160),
        'obs' => clip(post('obs'), 800),
    ]];
}

function save_image_setting(array &$db, string $fileKey, string $settingKey): ?string
{
    if (empty($_FILES[$fileKey]['tmp_name']) || !is_uploaded_file((string) $_FILES[$fileKey]['tmp_name'])) {
        return null;
    }
    $size = (int) ($_FILES[$fileKey]['size'] ?? 0);
    if ($size <= 0 || $size > 400000) {
        return 'A imagem precisa ter até 400 KB.';
    }
    $mime = (string) mime_content_type((string) $_FILES[$fileKey]['tmp_name']);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
        return 'Envie uma imagem PNG, JPG, WEBP ou GIF.';
    }
    $bin = file_get_contents((string) $_FILES[$fileKey]['tmp_name']);
    if ($bin === false) {
        return 'Não foi possível ler a imagem.';
    }
    $encoded = 'data:' . $mime . ';base64,' . base64_encode($bin);
    $ocupado = strlen($encoded);
    foreach (['logo', 'logo_lateral', 'favicon', 'assinatura'] as $chave) {
        if ($chave === $settingKey) {
            continue;
        }
        $ocupado += strlen((string) ($db['settings'][$chave] ?? ''));
    }
    if ($ocupado > 1200000) {
        return 'A imagem não foi salva. Remova outra logo ou a assinatura antes de enviar um arquivo deste tamanho.';
    }
    $anterior = (string) ($db['settings'][$settingKey] ?? '');
    $db['settings'][$settingKey] = $encoded;
    $salvo = $_SESSION['db'] ?? null;
    $_SESSION['db'] = $db;
    $peso = strlen(session_encode());
    if ($peso > 2000000) {
        $db['settings'][$settingKey] = $anterior;
        $_SESSION['db'] = is_array($salvo) ? $salvo : $db;
        return 'A imagem não foi salva: o armazenamento da sessão passou do limite seguro.';
    }
    return null;
}

function ops_dispatch(array &$db, string $action): ?array
{
    switch ($action) {
        case 'add_transfer':
        case 'update_transfer':
            $current = null;
            if ($action === 'update_transfer') {
                $i = index_of($db['transfers'], (int) post('id'));
                if ($i === null) {
                    return ['Viagem não encontrada.', ''];
                }
                $current = $db['transfers'][$i];
            }
            [$err, $trips] = montar_reservas($db, $current);
            if ($err !== null) {
                return [$err, ''];
            }
            $ids = [];
            foreach ($trips as $n => $trip) {
                if ($current !== null && $n === 0) {
                    $db['transfers'][index_of($db['transfers'], (int) $trip['id'])] = $trip;
                } else {
                    $trip['id'] = $db['seq']['transfer']++;
                    $trip['criado_em'] = today()->format('Y-m-d');
                    $db['transfers'][] = $trip;
                }
                $ids[] = (int) $trip['id'];
                sync_trip_finance($db, (int) $trip['id']);
            }
            if ($current !== null) {
                return [null, 'Reserva #' . $ids[0] . ' atualizada.'];
            }
            if (count($ids) > 1) {
                $nums = array_map(static fn(int $id): string => '#' . $id, $ids);
                $lista = implode(', ', $nums);
                $conjunto = (string) ($trips[0]['conjunto'] ?? '');
                if ($conjunto === 'trajetos') {
                    return [null, 'Reservas ' . $lista . ' criadas. Cada destino entrou com um número próprio.'];
                }
                return [null, 'Reservas ' . $lista . ' criadas. Ida e volta ficaram em números distintos e o valor foi dividido entre elas.'];
            }
            return [null, 'Reserva #' . $ids[0] . ' criada.'];

        case 'set_transfer_status':
            $i = index_of($db['transfers'], (int) post('id'));
            $new = one_of(post('status'), ['pendente', 'reserva', 'andamento', 'concluida', 'cancelada'], '');
            if ($i === null || $new === '') {
                return ['Não foi possível atualizar a viagem.', ''];
            }
            if (($_SESSION['role'] ?? 'admin') === 'motorista') {
                $meu = (int) ($_SESSION['motorista_id'] ?? 0);
                if (!viagem_do_motorista($db['transfers'][$i], $meu)) {
                    return ['Esta reserva não está atribuída a você.', ''];
                }
            }
            $old = (string) $db['transfers'][$i]['status'];
            $db['transfers'][$i]['status'] = $new;
            apply_status_side_effects($db, $db['transfers'][$i], $old, $new);
            sync_trip_finance($db, (int) $db['transfers'][$i]['id']);
            return [null, $new === 'cancelada' ? 'Reserva cancelada. A receita em aberto foi cancelada no financeiro.' : 'Status da viagem atualizado.'];

        case 'receber_transfer':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                return ['Viagem não encontrada.', ''];
            }
            $db['transfers'][$i]['pago'] = true;
            $db['transfers'][$i]['pago_em'] = today()->format('Y-m-d');
            $db['transfers'][$i]['meio'] = clip(post('meio'), 30) ?: 'pix';
            if (in_array((string) $db['transfers'][$i]['status'], ['pendente', 'reserva', 'andamento'], true)) {
                $old = (string) $db['transfers'][$i]['status'];
                $db['transfers'][$i]['status'] = 'concluida';
                apply_status_side_effects($db, $db['transfers'][$i], $old, 'concluida');
            }
            sync_trip_finance($db, (int) $db['transfers'][$i]['id']);
            return [null, 'Recebimento registrado.'];

        case 'faturar_transfer':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                return ['Viagem não encontrada.', ''];
            }
            $on = empty($db['transfers'][$i]['faturado']);
            $db['transfers'][$i]['faturado'] = $on;
            $db['transfers'][$i]['faturado_em'] = $on ? today()->format('Y-m-d') : null;
            if ($on && empty($db['transfers'][$i]['fatura'])) {
                $num = $db['seq']['fatura']++;
                $db['transfers'][$i]['fatura'] = 'FAT-' . today()->format('Y') . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            }
            return [null, $on ? 'Reserva marcada como faturada.' : 'Faturamento removido.'];

        case 'add_cliente':
        case 'update_cliente':
            [$err, $row] = cliente_from_post();
            if ($err !== null) {
                return [$err, ''];
            }
            if ($action === 'update_cliente') {
                $i = index_of($db['clientes'], (int) post('id'));
                if ($i === null) {
                    return ['Cliente não encontrado.', ''];
                }
                $db['clientes'][$i] = array_merge($db['clientes'][$i], $row);
                return [null, 'Cliente atualizado.'];
            }
            $row['id'] = $db['seq']['cliente']++;
            $db['clientes'][] = $row;
            return [null, 'Cliente cadastrado.'];

        case 'add_veiculo':
        case 'update_veiculo':
            $apelido = clip(post('apelido'), 40);
            $placa = strtoupper(clip(post('placa'), 8));
            $modelo = clip(post('modelo'), 40);
            $ano = (int) post('ano');
            $lugares = (int) post('lugares');
            if ($apelido === '' || strlen($placa) < 7 || $modelo === '' || $ano < 1990 || $lugares < 1) {
                return ['Preencha apelido, placa, modelo, ano e lugares.', ''];
            }
            $row = [
                'apelido' => $apelido,
                'placa' => $placa,
                'marca' => clip(post('marca'), 40),
                'modelo' => $modelo,
                'tipo' => one_of(post('tipo'), ['SED', 'VAN', 'EXEC', 'BUS'], 'SED'),
                'ano' => $ano,
                'cor' => clip(post('cor'), 20),
                'lugares' => $lugares,
                'status' => one_of(post('status'), ['ativo', 'manutencao', 'inativo'], 'ativo'),
                'precificacao' => post('precificacao') === '1',
                'valor_base' => max(0, post_money('valor_base')),
            ];
            if ($action === 'update_veiculo') {
                $i = index_of($db['veiculos'], (int) post('id'));
                if ($i === null) {
                    return ['Veículo não encontrado.', ''];
                }
                $row['id'] = $db['veiculos'][$i]['id'];
                $db['veiculos'][$i] = $row;
                return [null, 'Veículo atualizado.'];
            }
            $row['id'] = $db['seq']['veiculo']++;
            $db['veiculos'][] = $row;
            return [null, 'Veículo cadastrado.'];

        case 'add_motorista':
            $nome = clip(post('nome'), 80);
            $telefone = clip(post('telefone'), 30);
            if ($nome === '' || $telefone === '') {
                return ['Informe nome completo e telefone do motorista.', ''];
            }
            $mot = credenciais_motorista($db, array_merge(motorista_campos(), [
                'id' => $db['seq']['motorista']++,
                'nome' => $nome,
                'telefone' => $telefone,
                'status' => one_of(post('status'), ['ativo', 'inativo'], 'ativo'),
                'criado_em' => today()->format('Y-m-d'),
            ]), '', '');
            $db['motoristas'][] = $mot;
            return [null, 'Motorista cadastrado. Login do painel: ' . $mot['usuario'] . ' / senha ' . $mot['senha'] . '. Ele entra só na agenda e só altera o status das próprias reservas.'];

        case 'update_motorista':
            $i = index_of($db['motoristas'], (int) post('id'));
            if ($i === null) {
                return ['Motorista não encontrado.', ''];
            }
            $nome = clip(post('nome'), 80);
            $telefone = clip(post('telefone'), 30);
            if ($nome === '' || $telefone === '') {
                return ['Informe nome completo e telefone do motorista.', ''];
            }
            $db['motoristas'][$i] = array_merge($db['motoristas'][$i], motorista_campos(), [
                'nome' => $nome,
                'telefone' => $telefone,
                'status' => one_of(post('status'), ['ativo', 'inativo'], (string) ($db['motoristas'][$i]['status'] ?? 'ativo')),
            ]);
            if (empty($db['motoristas'][$i]['usuario']) || ($db['motoristas'][$i]['senha'] ?? '') === '') {
                $db['motoristas'][$i] = credenciais_motorista($db, $db['motoristas'][$i], (string) ($db['motoristas'][$i]['usuario'] ?? ''), (string) ($db['motoristas'][$i]['senha'] ?? ''));
            }
            return [null, 'Motorista atualizado. Acesso: ' . $db['motoristas'][$i]['usuario']];

        case 'reset_senha_motorista':
            $i = index_of($db['motoristas'], (int) post('id'));
            if ($i === null) {
                return ['Motorista não encontrado.', ''];
            }
            $senha = 'floripa' . (string) random_int(1000, 9999);
            if (empty($db['motoristas'][$i]['usuario'])) {
                $db['motoristas'][$i]['usuario'] = unique_usuario($db, slug_usuario((string) $db['motoristas'][$i]['nome']), (int) $db['motoristas'][$i]['id']);
            }
            $db['motoristas'][$i]['senha'] = $senha;
            return [null, 'Nova senha de ' . $db['motoristas'][$i]['nome'] . ': ' . $db['motoristas'][$i]['usuario'] . ' / ' . $senha];

        case 'add_lancamento':
        case 'edit_lancamento':
            $descricao = clip(post('descricao'), 120);
            $valor = post_money('valor');
            $data = post_date('data');
            $tipo = one_of(post('tipo'), ['entrada', 'saida'], '');
            if ($descricao === '' || $valor <= 0 || $data === '' || $tipo === '') {
                return ['Informe tipo, descrição, data e valor do lançamento.', ''];
            }
            $row = [
                'tipo' => $tipo,
                'descricao' => $descricao,
                'categoria' => clip(post('categoria'), 40) ?: ($tipo === 'entrada' ? 'Receita' : 'Despesa'),
                'valor' => $valor,
                'data' => $data,
                'vencimento' => post_date('vencimento') ?: $data,
                'status' => $tipo === 'entrada' ? one_of(post('status'), ['pendente', 'pago'], 'pendente') : one_of(post('status'), ['pendente', 'pago'], 'pendente'),
                'linha' => 'Principal',
                'origem' => 'manual',
                'meio' => clip(post('meio'), 30),
                'baixa' => post('status') === 'pago' ? today()->format('Y-m-d') : '',
                'transfer_id' => 0,
                'papel' => 'manual',
                'cliente' => clip(post('cliente'), 80),
            ];
            if ($action === 'edit_lancamento') {
                $i = index_of($db['lancamentos'], (int) post('id'));
                if ($i === null) {
                    return ['Lançamento não encontrado.', ''];
                }
                $row['id'] = $db['lancamentos'][$i]['id'];
                $row['transfer_id'] = (int) ($db['lancamentos'][$i]['transfer_id'] ?? 0);
                $row['papel'] = (string) ($db['lancamentos'][$i]['papel'] ?? 'manual');
                if ($row['papel'] === 'receita' && $row['transfer_id'] > 0) {
                    $ti = index_of($db['transfers'], $row['transfer_id']);
                    if ($ti !== null) {
                        $db['transfers'][$ti]['valor'] = $valor;
                        $db['transfers'][$ti]['data'] = $data;
                        $db['transfers'][$ti]['pago'] = $row['status'] === 'pago';
                        if ($row['status'] === 'pago') {
                            $db['transfers'][$ti]['pago_em'] = today()->format('Y-m-d');
                            $db['transfers'][$ti]['meio'] = $row['meio'];
                        }
                    }
                }
                $db['lancamentos'][$i] = $row;
                return [null, 'Lançamento atualizado.'];
            }
            $row['id'] = $db['seq']['lancamento']++;
            $db['lancamentos'][] = $row;
            return [null, 'Lançamento registrado.'];

        case 'baixar_lancamento':
            $i = index_of($db['lancamentos'], (int) post('id'));
            if ($i === null) {
                return ['Lançamento não encontrado.', ''];
            }
            $db['lancamentos'][$i]['status'] = 'pago';
            $db['lancamentos'][$i]['baixa'] = today()->format('Y-m-d');
            $db['lancamentos'][$i]['meio'] = clip(post('meio'), 30) ?: 'dinheiro';
            $tid = (int) ($db['lancamentos'][$i]['transfer_id'] ?? 0);
            if ($tid > 0 && ($db['lancamentos'][$i]['papel'] ?? '') === 'receita') {
                $ti = index_of($db['transfers'], $tid);
                if ($ti !== null) {
                    $db['transfers'][$ti]['pago'] = true;
                    $db['transfers'][$ti]['pago_em'] = today()->format('Y-m-d');
                    $db['transfers'][$ti]['meio'] = $db['lancamentos'][$i]['meio'];
                }
            }
            return [null, 'Baixa registrada.'];

        case 'delete_transfer':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                return ['Reserva não encontrada.', ''];
            }
            $id = (int) $db['transfers'][$i]['id'];
            array_splice($db['transfers'], $i, 1);
            $db['lancamentos'] = array_values(array_filter(
                $db['lancamentos'],
                static fn(array $row): bool => (int) ($row['transfer_id'] ?? 0) !== $id || ($row['status'] ?? '') === 'pago'
            ));
            return [null, 'Reserva #' . $id . ' excluída.'];

        case 'delete_lancamento':
            $i = index_of($db['lancamentos'], (int) post('id'));
            if ($i === null) {
                return ['Lançamento não encontrado.', ''];
            }
            array_splice($db['lancamentos'], $i, 1);
            return [null, 'Lançamento excluído.'];

        case 'save_contrato':
            $db['settings']['contrato_pdf'] = post('contrato_pdf') === '1';
            $db['settings']['contrato_modelo'] = clip(post('contrato_modelo'), 4000);
            $db['settings']['contrato_cancelamento'] = clip(post('contrato_cancelamento'), 4000);
            $db['settings']['contrato_clausulas'] = clip(post('contrato_clausulas'), 4000);
            return [null, 'Contrato salvo.'];

        case 'save_conta':
            if (post('editando') !== '1') {
                return ['Clique em Editar antes de alterar estes dados.', ''];
            }
            foreach ([
                'nome' => 80, 'razao' => 120, 'cnpj' => 20, 'telefone' => 30, 'email' => 80,
                'cidade' => 60, 'endereco' => 160, 'pix' => 80, 'rep_legal' => 80, 'whatsapp' => 30,
            ] as $key => $max) {
                if (array_key_exists($key, $_POST)) {
                    $db['settings'][$key] = clip(post($key), $max);
                }
            }
            if (array_key_exists('uf', $_POST)) {
                $db['settings']['uf'] = strtoupper(clip(post('uf'), 2));
            }
            $img = save_image_setting($db, 'logo', 'logo');
            if ($img !== null) {
                return [$img, ''];
            }
            $img = save_image_setting($db, 'logo_lateral', 'logo_lateral');
            if ($img !== null) {
                return [$img, ''];
            }
            $img = save_image_setting($db, 'favicon', 'favicon');
            if ($img !== null) {
                return [$img, ''];
            }
            return [null, 'Dados da conta salvos.'];

        case 'save_comprovante':
            if (post('editando') !== '1') {
                return ['Clique em Editar antes de alterar estes dados.', ''];
            }
            $db['settings']['comp_principal'] = clip(post('comp_principal'), 2000);
            $db['settings']['comp_rodape'] = clip(post('comp_rodape'), 2000);
            return [null, 'Comprovante salvo.'];

        case 'save_assinatura':
            if (post('editando') !== '1') {
                return ['Clique em Editar antes de alterar estes dados.', ''];
            }
            $img = save_image_setting($db, 'assinatura', 'assinatura');
            if ($img !== null) {
                return [$img, ''];
            }
            $db['settings']['assinatura_nome'] = clip(post('assinatura_nome'), 80);
            return [null, 'Assinatura salva.'];

        case 'save_pagamentos':
            if (post('editando') !== '1') {
                return ['Clique em Editar antes de alterar estes dados.', ''];
            }
            $db['settings']['pix'] = clip(post('pix'), 80);
            $db['settings']['pix_cidade'] = clip(post('pix_cidade'), 60);
            $db['settings']['pagbank_token'] = clip(post('pagbank_token'), 200);
            $db['settings']['pagbank_ativo'] = post('pagbank_ativo') === '1';
            return [null, 'Pagamentos salvos.'];

        case 'save_operacao':
            if (post('editando') !== '1') {
                return ['Clique em Editar antes de alterar estes dados.', ''];
            }
            $db['settings']['antecedencia'] = (string) max(0, min(72, (int) post('antecedencia')));
            $db['settings']['modo_simples'] = post('modo_simples') === '1';
            $adminUser = slug_usuario(clip(post('usuario'), 40));
            if ($adminUser === '' || $adminUser === 'motorista') {
                return ['Informe o usuário do administrador.', ''];
            }
            $db['settings']['usuario'] = $adminUser;
            $senha = clip(post('senha'), 40);
            if ($senha !== '') {
                $db['settings']['senha'] = $senha;
            }
            $apiUrl = rtrim(clip(post('uazapi_url'), 160), '/');
            if ($apiUrl !== '' && !preg_match('#^https://#i', $apiUrl)) {
                return ['A URL da UAZAPI precisa começar com https://', ''];
            }
            $db['settings']['uazapi_admin_token'] = clip(post('uazapi_admin_token'), 512);
            $createUrl = rtrim(clip(post('uazapi_create_url'), 220), '/');
            if ($createUrl !== '' && !preg_match('#^https://#i', $createUrl)) {
                return ['A URL de criação de instância precisa começar com https://', ''];
            }
            $db['settings']['uazapi_create_url'] = $createUrl;
            $db['settings']['uazapi_url'] = $apiUrl;
            $db['settings']['uazapi_token'] = clip(post('uazapi_token'), 4096);
            $db['settings']['whatsapp_ativo'] = post('whatsapp_ativo') === '1';
            return [null, 'Operação salva.'];

        case 'remove_logo':
            $key = one_of(post('qual'), ['logo', 'logo_lateral', 'favicon', 'assinatura'], '');
            if ($key === '') {
                return ['Imagem não encontrada.', ''];
            }
            $db['settings'][$key] = '';
            return [null, 'Imagem removida.'];

        case 'add_automacao':
        case 'update_automacao':
            $nome = clip(post('nome'), 60);
            if ($nome === '') {
                return ['Informe o nome da automação.', ''];
            }
            $tipo = one_of(post('tipo_hook'), ['Transfer Executivo', 'Motorista'], 'Transfer Executivo');
            if ($action === 'update_automacao') {
                $i = index_of($db['automacoes'], (int) post('id'));
                if ($i === null || empty($db['automacoes'][$i]['secret'])) {
                    return ['Automação não encontrada.', ''];
                }
                $db['automacoes'][$i]['nome'] = $nome;
                $db['automacoes'][$i]['tipo_hook'] = $tipo;
                return [null, 'Automação atualizada.'];
            }
            $db['automacoes'][] = [
                'id' => $db['seq']['automacao']++,
                'nome' => $nome,
                'tipo_hook' => $tipo,
                'secret' => bin2hex(random_bytes(8)),
                'ativo' => true,
            ];
            return [null, 'Automação criada. A URL do webhook está na lista.'];

        case 'delete_automacao':
            $i = index_of($db['automacoes'], (int) post('id'));
            if ($i === null || empty($db['automacoes'][$i]['secret'])) {
                return ['Automação não encontrada.', ''];
            }
            array_splice($db['automacoes'], $i, 1);
            return [null, 'Automação excluída.'];

        case 'inbox_to_reserva':
            $i = index_of($db['inbox'], (int) post('id'));
            if ($i === null) {
                return ['Solicitação não encontrada.', ''];
            }
            $s = $db['inbox'][$i];
            $trip = blank_trip($db['seq']['transfer']++);
            $trip['cliente'] = (string) $s['cliente'];
            $trip['telefone'] = (string) $s['telefone'];
            $trip['email'] = (string) $s['email'];
            $trip['origem'] = (string) $s['origem'];
            $trip['destino'] = (string) $s['destino'];
            $trip['data'] = (string) $s['data'];
            $trip['hora'] = (string) ($s['hora'] ?: '08:00');
            $trip['valor'] = (float) $s['valor'] > 0 ? (float) $s['valor'] : 1;
            $trip['tipo'] = one_of((string) ($s['tipo'] ?? 'ida'), ['ida', 'volta', 'ida_volta'], 'ida');
            $trip['obs'] = 'Veio da solicitação #' . $s['id'];
            $db['transfers'][] = $trip;
            array_splice($db['inbox'], $i, 1);
            sync_trip_finance($db, (int) $trip['id']);
            return [null, 'Solicitação virou a reserva #' . $trip['id'] . '.'];

        case 'delete_inbox':
            $i = index_of($db['inbox'], (int) post('id'));
            if ($i === null) {
                return ['Solicitação não encontrada.', ''];
            }
            array_splice($db['inbox'], $i, 1);
            return [null, 'Solicitação removida.'];

        case 'conectar_whatsapp':
            if (!uazapi_admin_ready($db['settings'])) {
                return ['Configure o token permanente da API (Seus Tokens) em Configurações antes de gerar o QR Code.', ''];
            }
            [$ok, $detalhe, $state] = uazapi_begin_whatsapp_qr($db['settings']);
            if (!$ok) {
                $db['settings']['wa_status'] = 'desconectado';
                $db['settings']['wa_erro'] = $detalhe;
                return [$detalhe, ''];
            }
            uazapi_apply_whatsapp_state($db['settings'], $state);
            return [null, $detalhe];

        case 'excluir_instancia_whatsapp':
            if (!uazapi_admin_ready($db['settings']) && !uazapi_settings_ready($db['settings'])) {
                return ['Não há instância configurada para excluir.', ''];
            }
            $erro = uazapi_delete_instance($db['settings']);
            uazapi_clear_instance_local($db['settings']);
            if ($erro !== null) {
                $db['settings']['wa_erro'] = $erro;
                return [$erro, ''];
            }
            return [null, 'Instância removida da UAZAPI e deste painel.'];

        case 'status_whatsapp':
            if (!uazapi_settings_ready($db['settings'])) {
                return ['Gere o QR Code primeiro para criar a instância.', ''];
            }
            [$ok, $detalhe, $state] = uazapi_refresh_status($db['settings']);
            if (!$ok) {
                $db['settings']['wa_erro'] = $detalhe;
                return [$detalhe, ''];
            }
            uazapi_apply_whatsapp_state($db['settings'], $state);
            return [null, $detalhe];

        default:
            return null;
    }
}

function handle_public_hook(string $secret): never
{
    $db = load_db();
    $auto = null;
    foreach ($db['automacoes'] as $row) {
        if (($row['secret'] ?? '') !== '' && hash_equals((string) $row['secret'], $secret)) {
            $auto = $row;
            break;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    if ($auto === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'erro' => 'Webhook não encontrado.']);
        exit;
    }
    if (empty($auto['ativo'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'erro' => 'Automação inativa.']);
        exit;
    }
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    $pick = static function (array $src, array $keys): string {
        foreach ($keys as $key) {
            if (isset($src[$key]) && trim((string) $src[$key]) !== '') {
                return clip((string) $src[$key], 180);
            }
        }
        return '';
    };
    $tipoHook = (string) ($auto['tipo_hook'] ?? 'Transfer Executivo');
    if ($tipoHook === 'Motorista') {
        $nome = $pick($data, ['nome', 'name']);
        $telefone = $pick($data, ['telefone', 'phone', 'whatsapp']);
        if ($nome === '' || $telefone === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Informe nome e telefone.']);
            exit;
        }
        $id = $db['seq']['mot_solic']++;
        $db['mot_solic'][] = [
            'id' => $id,
            'nome' => $nome,
            'telefone' => $telefone,
            'cidade' => $pick($data, ['cidade', 'city']),
            'cnh' => $pick($data, ['cnh']),
            'cpf' => $pick($data, ['cpf', 'documento']),
            'categoria' => 'B',
            'mensagem' => $pick($data, ['mensagem', 'message', 'obs']),
            'status' => 'nova',
            'criado_em' => today()->format('Y-m-d'),
        ];
        save_db($db);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }
    $cliente = $pick($data, ['cliente', 'nome', 'name']);
    $origem = $pick($data, ['origem', 'pickup']);
    $destino = $pick($data, ['destino', 'dropoff']);
    if ($cliente === '' || $origem === '' || $destino === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'erro' => 'Informe cliente, origem e destino.']);
        exit;
    }
    $id = $db['seq']['inbox']++;
    $db['inbox'][] = [
        'id' => $id,
        'cliente' => $cliente,
        'telefone' => $pick($data, ['telefone', 'phone', 'whatsapp']),
        'email' => $pick($data, ['email', 'e-mail']),
        'origem' => $origem,
        'destino' => $destino,
        'data' => $pick($data, ['data', 'date']),
        'hora' => $pick($data, ['hora', 'time']),
        'valor' => (float) str_replace(',', '.', $pick($data, ['valor', 'value'])),
        'tipo' => $pick($data, ['tipo', 'type']) ?: 'ida',
        'criado_em' => today()->format('Y-m-d H:i'),
    ];
    save_db($db);
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

function operation_seed(): array
{
    $contratoModelo = "1. DAS PARTES\n1.1. O presente contrato é celebrado entre as partes abaixo qualificadas.\n1.2. O CONTRATANTE declara ter conhecimento de todas as condições do serviço contratado.\n\n2. DO SERVIÇO\n2.1. O serviço de transfer será realizado conforme trajeto, data e horário especificados neste instrumento.\n2.2. O veículo será disponibilizado com motorista profissional habilitado.\n2.3. O serviço inclui busca e transporte do grupo até o destino indicado.\n\n3. DO VALOR\n3.1. O valor do serviço será aquele especificado neste contrato.\n3.2. O pagamento deverá ser efetuado na forma acordada entre as partes.";
    $contratoCancela = "POLÍTICA DE CANCELAMENTO\n- Cancelamentos com mais de 72 horas de antecedência: reembolso integral.\n- Cancelamentos entre 48 e 72 horas: reembolso de 50%.\n- Cancelamentos com menos de 48 horas: sem reembolso.\n- No-show (não comparecimento): sem reembolso.";
    $contratoClausulas = "CLÁUSULAS ADICIONAIS\n8.1. Este contrato é regido pelas leis da República Federativa do Brasil.\n8.2. Fica eleito o foro da comarca local para dirimir quaisquer dúvidas oriundas deste contrato.\n8.3. As partes declaram ter lido e concordado com todos os termos deste contrato.\n8.4. Alterações de trajeto durante o serviço poderão acarretar cobrança adicional.\n8.5. É proibido o consumo de bebidas alcoólicas e alimentos que possam danificar o veículo.";

    $db = [
        'schema' => 2,
        'seq' => [
            'transfer' => 3784,
            'lancamento' => 1,
            'motorista' => 6,
            'cliente' => 7,
            'veiculo' => 1,
            'grupo' => 1,
            'nota' => 2,
            'mot_solic' => 1,
            'mensagem' => 1,
            'zona' => 1,
            'fatura' => 1,
            'contrato' => 1,
            'automacao' => 3,
            'inbox' => 1,
        ],
        'settings' => [
            'nome' => 'Transfer FLN',
            'razao' => 'Fln Transfer Executivo e Turismo',
            'cnpj' => '45.827.819/0001-08',
            'telefone' => '(48) 98822-2534',
            'whatsapp' => '(48) 98822-2534',
            'email' => 'financeiroflnsc@gmail.com',
            'cidade' => 'Florianópolis',
            'uf' => 'SC',
            'endereco' => 'Servidão Estação Luiz Cline, Florianópolis - SC',
            'rep_legal' => 'Matheus Blum',
            'pix' => 'financeiroflnsc@gmail.com',
            'pix_cidade' => 'Florianópolis',
            'pagbank_token' => '',
            'pagbank_ativo' => false,
            'antecedencia' => '2',
            'modo_simples' => false,
            'usuario' => 'admin',
            'senha' => 'admin123',
            'uazapi_admin_token' => '',
            'uazapi_create_url' => '',
            'uazapi_url' => '',
            'uazapi_token' => '',
            'uazapi_instance_name' => '',
            'whatsapp_ativo' => true,
            'logo' => '',
            'logo_lateral' => '',
            'favicon' => '',
            'assinatura' => '',
            'assinatura_nome' => 'Matheus Blum',
            'comp_principal' => "Fln Transfer Executivo e Turismo\nCNPJ 45.827.819/0001-08\nServidão Estação Luiz Cline\nTel +55 48 98822-2534 · financeiroflnsc@gmail.com",
            'comp_rodape' => 'Obrigado por viajar com a Fln Transfer. Guarde este comprovante.',
            'contrato_pdf' => true,
            'contrato_modelo' => $contratoModelo,
            'contrato_cancelamento' => $contratoCancela,
            'contrato_clausulas' => $contratoClausulas,
            'wa_status' => 'desconectado',
            'wa_instance_status' => 'disconnected',
            'wa_qr' => '',
            'wa_paircode' => '',
            'wa_request_id' => '',
            'wa_profile_name' => '',
            'wa_erro' => '',
        ],
        'veiculos' => [],
        'motoristas' => [
            ['id' => 1, 'nome' => 'Fábio Luiz de Oliveira', 'cpf' => '810.877.930-01', 'telefone' => '(47) 98463-0303', 'cnh' => 'SC3019281', 'categoria' => 'D', 'cidade' => 'Florianópolis', 'uf' => 'SC', 'status' => 'ativo', 'usuario' => 'fabio.oliveira', 'senha' => 'floripa2001', 'criado_em' => '2026-10-25'],
            ['id' => 2, 'nome' => 'Felipe Goulart', 'cpf' => '064.462.899-17', 'telefone' => '(47) 99125-7643', 'cnh' => '', 'categoria' => 'B', 'cidade' => 'Balneário Camboriú', 'uf' => 'SC', 'status' => 'ativo', 'usuario' => 'felipe.goulart', 'senha' => '', 'criado_em' => '2026-10-24'],
            ['id' => 3, 'nome' => 'Marcelo José Coelho', 'cpf' => '005.461.849-52', 'telefone' => '(48) 98803-0714', 'cnh' => 'SC4481200', 'categoria' => 'B', 'cidade' => 'São José', 'uf' => 'SC', 'status' => 'ativo', 'usuario' => 'marcelo.coelho', 'senha' => 'floripa2003', 'criado_em' => '2026-10-21'],
            ['id' => 4, 'nome' => 'Ricardo Nunes', 'cpf' => '392.110.609-00', 'telefone' => '(48) 98800-1001', 'cnh' => 'SC1000001', 'categoria' => 'D', 'cidade' => 'Florianópolis', 'uf' => 'SC', 'status' => 'ativo', 'usuario' => 'ricardo.nunes', 'senha' => 'floripa1001', 'criado_em' => '2026-10-20'],
            ['id' => 5, 'nome' => 'Marina Costa', 'cpf' => '281.554.908-61', 'telefone' => '(47) 98800-1002', 'cnh' => 'SC1000002', 'categoria' => 'B', 'cidade' => 'Itajaí', 'uf' => 'SC', 'status' => 'ativo', 'usuario' => 'marina.costa', 'senha' => 'floripa1002', 'criado_em' => '2026-10-18'],
        ],
        'clientes' => [
            ['id' => 1, 'nome' => 'Paulo Henrique Correa de Macedo', 'tipo' => 'PF', 'email' => 'naoinformado7@gmail.com', 'telefone' => '(51) 99288-7593', 'documento' => '392.110.609-00', 'cidade' => 'Florianópolis', 'uf' => 'SC', 'empresa' => '', 'endereco' => ''],
            ['id' => 2, 'nome' => 'MZ', 'tipo' => 'PJ', 'email' => 'sememail@gmail.com', 'telefone' => '(48) 99999-0000', 'documento' => '45.827.819/0001-08', 'cidade' => 'Florianópolis', 'uf' => 'SC', 'empresa' => 'MZ Turismo', 'endereco' => 'Centro, Florianópolis - SC'],
            ['id' => 3, 'nome' => 'Alejandra Soto Barría', 'tipo' => 'PF', 'email' => 'naoinformado7@gmail.com', 'telefone' => '(56) 9 6812-0107', 'documento' => '', 'cidade' => '', 'uf' => '', 'empresa' => '', 'endereco' => ''],
            ['id' => 4, 'nome' => 'Alex Britto', 'tipo' => 'PF', 'email' => 'naoinformado7@gmail.com', 'telefone' => '', 'documento' => '', 'cidade' => 'Florianópolis', 'uf' => 'SC', 'empresa' => '', 'endereco' => ''],
            ['id' => 5, 'nome' => 'Ana Carolina Carvalho de Farias', 'tipo' => 'PF', 'email' => 'naoinformado7@gmail.com', 'telefone' => '(49) 9923-7751', 'documento' => '123.456.789-00', 'cidade' => 'Florianópolis', 'uf' => 'SC', 'empresa' => '', 'endereco' => 'Canasvieiras, Florianópolis - SC'],
            ['id' => 6, 'nome' => 'Felipe Goulart', 'tipo' => 'PF', 'email' => 'felipe.goulart@email.com', 'telefone' => '(47) 99125-7643', 'documento' => '064.462.899-17', 'cidade' => 'Balneário Camboriú', 'uf' => 'SC', 'empresa' => '', 'endereco' => ''],
        ],
        'transfers' => [],
        'lancamentos' => [],
        'grupos' => [],
        'notas' => [
            ['id' => 1, 'titulo' => 'Embarque no aeroporto', 'texto' => 'Conferir o voo e aguardar o passageiro no desembarque de Florianópolis.', 'criado_em' => '2026-10-26 09:10'],
        ],
        'mot_solic' => [],
        'inbox' => [],
        'mensagens' => [],
        'zonas' => [],
        'whatsapp_envios' => [],
        'automacoes' => [
            ['id' => 1, 'nome' => 'Site da frota', 'tipo_hook' => 'Transfer Executivo', 'secret' => 'flnsitehook0001', 'ativo' => false],
            ['id' => 2, 'nome' => 'Operação', 'tipo_hook' => 'Transfer Executivo', 'secret' => 'flnoperacao0002', 'ativo' => true],
            ['id' => 'confirmacao', 'titulo' => 'Enviar confirmação ao reservar', 'descricao' => 'Registra a mensagem de confirmação quando uma solicitação vira reserva.', 'ativo' => true],
            ['id' => 'lembrete', 'titulo' => 'Lembrete 24 horas antes', 'descricao' => 'Agenda um lembrete para o dia anterior à viagem.', 'ativo' => true],
            ['id' => 'caminho', 'titulo' => 'Aviso de motorista a caminho', 'descricao' => 'Registra o aviso quando a viagem entra em andamento.', 'ativo' => false],
            ['id' => 'resumo', 'titulo' => 'Resumo diário de métricas', 'descricao' => 'O painel de métricas recarrega sozinho a cada 60 segundos.', 'ativo' => true],
            ['id' => 'whatsapp_d1', 'titulo' => 'WhatsApp ao motorista, 1 dia antes', 'descricao' => 'Envia o agendamento pelo WhatsApp (UAZAPI) no dia anterior à viagem.', 'ativo' => true],
            ['id' => 'whatsapp_dia', 'titulo' => 'WhatsApp ao motorista, no dia', 'descricao' => 'Envia o agendamento pelo WhatsApp (UAZAPI) no dia da viagem.', 'ativo' => true],
        ],
        'templates' => [
            ['id' => 'confirmacao', 'titulo' => 'Confirmação de embarque', 'texto' => 'Olá {cliente}, sua viagem {codigo} está confirmada para {data} às {hora}. Origem: {origem}. Destino: {destino}. Valor {valor}.'],
            ['id' => 'lembrete', 'titulo' => 'Lembrete da viagem', 'texto' => 'Olá {cliente}, lembrete da viagem {codigo} em {data} às {hora}. Saída de {origem} com destino a {destino}.'],
            ['id' => 'caminho', 'titulo' => 'Motorista a caminho', 'texto' => 'Olá {cliente}, o motorista {motorista} está a caminho do embarque em {origem}. Viagem {codigo}.'],
            ['id' => 'mot_d1', 'titulo' => 'Agenda do motorista — véspera', 'texto' => 'Olá {motorista}, amanhã você tem a viagem {codigo} às {hora}. Cliente: {cliente}. Origem: {origem}. Destino: {destino}. Valor {valor}.'],
            ['id' => 'mot_dia', 'titulo' => 'Agenda do motorista — no dia', 'texto' => 'Olá {motorista}, hoje você tem a viagem {codigo} às {hora}. Cliente: {cliente}. Embarque em {origem}, destino {destino}. Valor {valor}.'],
        ],
    ];

    $specs = [
        [3783, 1, 'ida', '2026-11-07', '10:30', '', '', 'Aeroporto de Florianópolis', 'Costão do Santinho Resort', 330.00, 'pendente', null, false, false, 0, '2026-10-26'],
        [3782, 2, 'ida', '2026-10-27', '14:10', '', '', 'FLN - Aeroporto Hercílio Luz', 'Jurerê Internacional', 102.70, 'pendente', null, true, false, 0, '2026-10-26'],
        [3781, 4, 'ida', '2026-10-27', '08:40', '', '', 'Canasvieiras', 'Aeroporto Hercílio Luz', 126.60, 'concluida', 1, false, true, 90, '2026-10-26'],
        [3780, 2, 'ida_volta', '2026-10-27', '07:15', '18:40', '', 'Centro de Florianópolis', 'Ingleses', 180.00, 'pendente', 3, false, false, 0, '2026-10-25'],
        [3779, 1, 'ida', '2026-10-26', '09:20', '', '', 'Aeroporto Hercílio Luz', 'Costão do Santinho Resort', 330.00, 'pendente', null, true, false, 0, '2026-10-20'],
        [3778, 2, 'ida', '2026-10-26', '16:00', '', '', 'FLN - Aeroporto Hercílio Luz', 'Jurerê Internacional', 102.70, 'pendente', null, true, false, 0, '2026-10-20'],
        [3777, 5, 'volta', '2026-10-25', '19:10', '', '', 'Costão do Santinho Resort', 'FLN - Aeroporto Hercílio Luz', 102.70, 'concluida', 3, true, false, 90, '2026-10-18'],
        [3776, 6, 'ida', '2026-10-20', '11:00', '', '', 'Hotel Mercure', 'Aeroporto Hercílio Luz', 140.00, 'reserva', 4, false, false, 0, '2026-10-15'],
        [3775, 2, 'ida', '2026-10-14', '08:30', '', '', 'Centro de Florianópolis', 'Aeroporto Hercílio Luz', 126.60, 'pendente', null, true, false, 0, '2026-10-10'],
        [3774, 1, 'ida_volta', '2026-10-08', '06:50', '17:20', '', 'Ingleses', 'FLN - Aeroporto Hercílio Luz', 150.00, 'pendente', null, true, false, 0, '2026-10-02'],
        [3773, 2, 'ida', '2026-10-07', '09:00', '', '', 'FLN - Aeroporto Hercílio Luz', 'Canasvieiras', 126.60, 'andamento', 1, true, false, 0, '2026-10-01'],
        [3772, 4, 'ida', '2026-10-06', '13:40', '', '', 'Aeroporto Hercílio Luz', 'Jurerê Internacional', 180.00, 'pendente', null, false, false, 0, '2026-09-30'],
        [3771, 2, 'ida_volta', '2026-10-05', '08:00', '19:00', '', 'Centro de Florianópolis', 'Ingleses', 200.00, 'concluida', 1, true, true, 80, '2026-09-28'],
        [3770, 5, 'ida', '2026-10-04', '15:10', '', '', 'Canasvieiras', 'Centro de Florianópolis', 99.30, 'pendente', null, false, false, 0, '2026-09-28'],
        [3769, 6, 'ida', '2026-09-18', '16:40', '', '', 'Rua Felipe Schmidt, 100, Centro, Florianópolis', 'Aeroporto Hercílio Luz', 160.00, 'concluida', 5, true, true, 70, '2026-09-10'],
        [3768, 6, 'ida', '2026-08-12', '16:40', '', '', 'Rua Bernardo Turnes Neto, Palhoça - SC', 'Aeroporto Hercílio Luz', 180.00, 'concluida', 4, true, true, 0, '2026-08-01'],
        [3767, 1, 'ida', '2026-05-17', '09:15', '', '', 'Rua Pedro Pinto Felipe, 87, Balneário Camboriú', 'Aeroporto Hercílio Luz', 90.00, 'concluida', 5, true, true, 0, '2026-05-10'],
    ];
    foreach ($specs as $s) {
        [$id, $cid, $tipo, $data, $hora, $horaVolta, $dataVolta, $origem, $destino, $valor, $status, $mot, $fat, $pago, $repasse, $criado] = $s;
        $cliente = $db['clientes'][$cid - 1];
        $trip = blank_trip($id);
        $trip['cliente_id'] = $cid;
        $trip['cliente'] = $cliente['nome'];
        $trip['telefone'] = $cliente['telefone'];
        $trip['email'] = $cliente['email'];
        $trip['tipo'] = $tipo;
        $trip['origem'] = $origem;
        $trip['destino'] = $destino;
        $trip['data'] = $data;
        $trip['hora'] = $hora;
        $trip['hora_volta'] = $horaVolta;
        $trip['data_volta'] = $tipo === 'ida_volta' ? ($dataVolta !== '' ? $dataVolta : $data) : '';
        $trip['valor'] = $valor;
        $trip['status'] = $status;
        $trip['motorista_id'] = $mot;
        $trip['faturado'] = $fat;
        $trip['faturado_em'] = $fat ? $criado : null;
        $trip['pago'] = $pago;
        $trip['pago_em'] = $pago ? $data : null;
        $trip['meio'] = $pago ? 'pix' : '';
        $trip['repasse'] = $repasse;
        $trip['criado_em'] = $criado;
        $trip['no_funil'] = true;
        $db['transfers'][] = $trip;
    }
    foreach ($db['transfers'] as $trip) {
        sync_trip_finance($db, (int) $trip['id']);
    }
    return $db;
}
