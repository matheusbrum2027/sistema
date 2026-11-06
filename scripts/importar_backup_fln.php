<?php
declare(strict_types=1);

$_SERVER['SCRIPT_FILENAME'] = __FILE__;
ini_set('memory_limit', '512M');
set_time_limit(0);

require dirname(__DIR__) . '/includes/bootstrap.php';

$backup = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app-e-transporte-pro-20260525T002946Z-3-001' . DIRECTORY_SEPARATOR . 'backups';
if (!is_file($backup . DIRECTORY_SEPARATOR . 'reservas_transfer.json')) {
    $backup = 'C:\\Users\\ADMIN\\OneDrive\\Área de Trabalho\\app-e-transporte-pro-20260525T002946Z-3-001\\backups';
}

function ler_json(string $arquivo): array
{
    $dados = json_decode((string) file_get_contents($arquivo), true);
    if (!is_array($dados)) {
        throw new RuntimeException('JSON inválido: ' . $arquivo);
    }
    return $dados;
}

function hora_curta(?string $hora): string
{
    $hora = trim((string) $hora);
    if (preg_match('/^(\d{2}:\d{2})/', $hora, $m)) {
        return $m[1];
    }
    return '00:00';
}

function dia(?string $data): string
{
    $data = trim((string) $data);
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $data) ? substr($data, 0, 10) : '';
}

function txt(mixed $valor, int $max): string
{
    $texto = trim((string) $valor);
    if (mb_strlen($texto) <= $max) {
        return $texto;
    }
    return mb_substr($texto, 0, $max);
}

function status_fln(string $status): string
{
    return match ($status) {
        'concluida' => 'concluida',
        'em_andamento' => 'andamento',
        'cancelada' => 'cancelada',
        'confirmada' => 'reserva',
        default => 'pendente',
    };
}

function categoria_fln(?string $categoria): string
{
    return match ($categoria) {
        'sedan' => 'SED',
        'mini_van' => 'MINIVAN',
        'veiculo_07_lugares' => '07LUG.',
        'micro_onibus' => 'MIC.VAN',
        'van' => 'VAN',
        'hatch' => 'HAT',
        'onibus' => 'ONIBUS',
        default => 'SED',
    };
}

function meio_fln(string $texto, bool $faturado): string
{
    if ($faturado) {
        return 'faturado_empresa';
    }
    $n = mb_strtolower($texto);
    if (str_contains($n, 'pix')) {
        return 'pix';
    }
    if (str_contains($n, 'crédito') || str_contains($n, 'credito')) {
        return 'credito';
    }
    if (str_contains($n, 'débito') || str_contains($n, 'debito')) {
        return 'debito';
    }
    if (str_contains($n, 'dinheiro') || str_contains($n, 'avista') || str_contains($n, 'à vista')) {
        return 'dinheiro';
    }
    if (str_contains($n, 'boleto')) {
        return 'boleto';
    }
    return $texto !== '' ? 'outros' : '';
}

$db = load_db(true);
$antes = [
    'transfers' => count($db['transfers']),
    'clientes' => count($db['clientes']),
    'motoristas' => count($db['motoristas']),
];

$cadastros = ler_json($backup . DIRECTORY_SEPARATOR . 'cadastro_clientes.json');
$fichas = ler_json($backup . DIRECTORY_SEPARATOR . 'solicitacoes_motoristas.json');
$reservas = ler_json($backup . DIRECTORY_SEPARATOR . 'reservas_transfer.json');

$clienteDe = [];
$clientes = [];
$seqCliente = 1;
foreach ($cadastros as $c) {
    $id = $seqCliente++;
    $clienteDe[(string) $c['id']] = $id;
    $endereco = '';
    if (is_array($c['enderecos'] ?? null) && isset($c['enderecos'][0]['endereco'])) {
        $endereco = (string) $c['enderecos'][0]['endereco'];
    }
    $doc = preg_replace('/\D+/', '', (string) ($c['cpf_cnpj'] ?? '')) ?? '';
    $clientes[] = [
        'id' => $id,
        'nome' => txt($c['nome_exibicao'] ?? '', 120),
        'tipo' => strtolower((string) ($c['tipo'] ?? 'pf')) === 'pj' ? 'PJ' : 'PF',
        'email' => txt($c['email'] ?? '', 120),
        'telefone' => txt($c['telefone_1'] ?? '', 40),
        'documento' => txt($c['cpf_cnpj'] ?? '', 40),
        'cidade' => '',
        'uf' => '',
        'empresa' => '',
        'endereco' => txt($endereco, 240),
    ];
    unset($doc);
}

$db['motoristas'] = [];
$motoristaDe = [];
$acessos = [];
$seqMot = 1;
$vistos = [];
foreach ($fichas as $m) {
    $id = $seqMot++;
    $portal = (string) ($m['portal_auth_user_id'] ?? '');
    if ($portal !== '') {
        $motoristaDe[$portal] = $id;
    }
    $email = mb_strtolower(trim((string) ($m['email'] ?? '')));
    if ($email !== '' && isset($vistos[$email])) {
        $email = 'mot' . $id . '.' . $email;
    }
    if ($email !== '') {
        $vistos[$email] = true;
    }
    $row = [
        'id' => $id,
        'nome' => txt($m['nome'] ?? '', 120),
        'cpf' => txt($m['cpf'] ?? '', 20),
        'telefone' => txt($m['telefone'] ?? '', 40),
        'cnh' => txt($m['cnh'] ?? '', 20),
        'categoria' => 'B',
        'cidade' => txt($m['cidade'] ?? '', 60),
        'uf' => txt($m['estado'] ?? '', 2),
        'status' => 'ativo',
        'email' => $email,
        'criado_em' => dia($m['created_at'] ?? '') ?: today()->format('Y-m-d'),
    ];
    $row = credenciais_motorista($db, $row);
    $db['motoristas'][] = $row;
    $acessos[] = $row['nome'] . ' | ' . $row['usuario'] . ' | ' . $row['senha'];
}

$idsAntigos = [];
foreach ($db['transfers'] as $t) {
    $idsAntigos[(int) $t['id']] = true;
}

$transfers = [];
$idsNovos = [];
foreach ($reservas as $r) {
    $numero = (int) $r['numero_reserva'];
    if ($numero <= 0 || isset($idsNovos[$numero])) {
        continue;
    }
    $idsNovos[$numero] = true;
    $tipoOrigem = (string) ($r['tipo_viagem'] ?? 'somente_ida');
    $perna = (string) ($r['perna_viagem'] ?? '');
    $trip = blank_trip($numero);
    $cid = $clienteDe[(string) ($r['cadastro_cliente_id'] ?? '')] ?? null;
    if ($cid === null) {
        $cid = $seqCliente++;
        $clientes[] = [
            'id' => $cid,
            'nome' => txt($r['nome_completo'] ?? 'Cliente', 120),
            'tipo' => 'PF',
            'email' => txt($r['email'] ?? '', 120),
            'telefone' => txt($r['telefone'] ?? '', 40),
            'documento' => txt($r['cpf_cnpj'] ?? '', 40),
            'cidade' => '',
            'uf' => '',
            'empresa' => '',
            'endereco' => '',
        ];
    }
    $faturado = !empty($r['faturado']) || str_contains(mb_strtolower((string) ($r['metodo_pagamento'] ?? '')), 'faturado');
    $pagamento = trim((string) ($r['metodo_pagamento'] ?? ''));
    $obs = trim((string) ($r['observacoes'] ?? ''));
    if ($pagamento !== '') {
        $obs = trim($obs . ($obs !== '' ? "\n" : '') . 'Pagamento: ' . $pagamento);
    }
    $trip['cliente_id'] = $cid;
    $trip['cliente'] = txt($r['nome_completo'] ?? '', 120);
    $trip['telefone'] = txt($r['telefone'] ?? '', 40);
    $trip['email'] = txt($r['email'] ?? '', 120);
    $trip['documento'] = txt($r['cpf_cnpj'] ?? '', 40);
    $trip['categoria'] = categoria_fln(isset($r['categoria_veiculo']) ? (string) $r['categoria_veiculo'] : null);
    $trip['valor'] = (float) ($r['valor_total'] ?? 0);
    $trip['repasse'] = (float) ($r['repasse_motorista'] ?? 0);
    $trip['status'] = status_fln((string) ($r['status'] ?? 'pendente'));
    $trip['faturado'] = $faturado;
    $trip['faturado_em'] = $faturado ? (dia($r['updated_at'] ?? '') ?: null) : null;
    $trip['meio'] = meio_fln($pagamento, $faturado);
    $trip['esconder_valores'] = !empty($r['esconder_valores']);
    $trip['criado_em'] = dia($r['created_at'] ?? '') ?: today()->format('Y-m-d');
    $trip['obs'] = txt($obs, 2000);
    $mid = $motoristaDe[(string) ($r['motorista_id'] ?? '')] ?? null;
    $trip['motorista_id'] = $mid;
    $trip['quem'] = 'motorista';

    if ($tipoOrigem === 'por_hora') {
        $trip['tipo'] = 'hora';
        $trip['origem'] = txt($r['por_hora_endereco_inicio'] ?? '', 240);
        $trip['destino'] = txt($r['por_hora_ponto_encerramento'] ?? ($r['por_hora_endereco_inicio'] ?? ''), 240);
        $trip['data'] = dia($r['por_hora_data'] ?? '');
        $trip['hora'] = hora_curta($r['por_hora_hora'] ?? '');
        $trip['pax'] = max(1, (int) ($r['por_hora_passageiros'] ?? 1));
        $trip['qtd_horas'] = txt($r['por_hora_qtd_horas'] ?? '', 10);
        $trip['cupom'] = txt($r['por_hora_cupom'] ?? '', 40);
        if (trim((string) ($r['por_hora_itinerario'] ?? '')) !== '') {
            $trip['obs'] = txt(trim($trip['obs'] . "\n" . $r['por_hora_itinerario']), 2000);
        }
    } elseif ($perna === 'volta') {
        $trip['tipo'] = 'ida';
        $trip['origem'] = txt($r['volta_embarque'] ?? '', 240);
        $trip['destino'] = txt($r['volta_desembarque'] ?? '', 240);
        $trip['data'] = dia($r['volta_data'] ?? '');
        $trip['hora'] = hora_curta($r['volta_hora'] ?? '');
        $trip['pax'] = max(1, (int) ($r['volta_passageiros'] ?? 1));
        $trip['cupom'] = txt($r['volta_cupom'] ?? '', 40);
    } elseif ($tipoOrigem === 'ida_volta' && $perna === '' && dia($r['volta_data'] ?? '') !== '') {
        $trip['tipo'] = 'ida_volta';
        $trip['origem'] = txt($r['ida_embarque'] ?? '', 240);
        $trip['destino'] = txt($r['ida_desembarque'] ?? '', 240);
        $trip['data'] = dia($r['ida_data'] ?? '');
        $trip['hora'] = hora_curta($r['ida_hora'] ?? '');
        $trip['data_volta'] = dia($r['volta_data'] ?? '');
        $trip['hora_volta'] = hora_curta($r['volta_hora'] ?? '');
        $trip['pax'] = max(1, (int) ($r['ida_passageiros'] ?? 1));
        $trip['cupom'] = txt($r['ida_cupom'] ?? '', 40);
    } else {
        $trip['tipo'] = 'ida';
        $trip['origem'] = txt($r['ida_embarque'] ?? '', 240);
        $trip['destino'] = txt($r['ida_desembarque'] ?? '', 240);
        $trip['data'] = dia($r['ida_data'] ?? '');
        $trip['hora'] = hora_curta($r['ida_hora'] ?? '');
        $trip['pax'] = max(1, (int) ($r['ida_passageiros'] ?? 1));
        $trip['cupom'] = txt($r['ida_cupom'] ?? '', 40);
    }
    if ($trip['data'] === '') {
        $trip['data'] = dia($r['created_at'] ?? '') ?: today()->format('Y-m-d');
    }
    $transfers[] = $trip;
}

$db['clientes'] = $clientes;
$db['transfers'] = $transfers;
$db['seq']['cliente'] = $seqCliente;
$db['seq']['motorista'] = $seqMot;
$db['seq']['transfer'] = (max(array_keys($idsNovos) ?: [0]) + 1);

$nova = (int) store_rpc('fln_salvar', [
    'p_versao' => store_versao(),
    'p_dados' => $db,
    'p_acao' => 'importar_backup',
    'p_usuario' => 'sistema',
    'p_base' => store_base(),
], 180);

file_put_contents(
    $backup . DIRECTORY_SEPARATOR . 'motoristas-acessos-migracao.txt',
    "Acessos gerados para o portal do motorista no Transfer FLN\n" . implode("\n", $acessos) . "\n"
);

echo json_encode([
    'versao' => $nova,
    'antes' => $antes,
    'depois' => [
        'clientes' => count($db['clientes']),
        'motoristas' => count($db['motoristas']),
        'transfers' => count($db['transfers']),
        'lancamentos_restantes' => count($db['lancamentos']),
    ],
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
