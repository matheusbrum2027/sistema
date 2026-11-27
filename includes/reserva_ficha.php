<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function reserva_traco(string $valor): string
{
    $valor = trim($valor);
    return $valor === '' ? '—' : $valor;
}

function reserva_perfil(array $trip): string
{
    $tipo = (string) ($trip['tipo'] ?? '');
    $conjunto = (string) ($trip['conjunto'] ?? '');
    if ($tipo === 'hora' || $tipo === 'por_hora') {
        return 'hora';
    }
    if ($tipo === 'trajetos' || $conjunto === 'trajetos') {
        return 'trajetos';
    }
    if ($tipo === 'ida_volta') {
        return 'combinada';
    }
    if ($tipo === 'volta') {
        return 'volta';
    }
    if ($conjunto === 'ida_volta') {
        return 'ida_par';
    }
    return 'ida';
}

function reserva_tipo_rotulo(array $trip): string
{
    return match (reserva_perfil($trip)) {
        'hora' => 'Por Hora',
        'trajetos' => '2 ou mais trajetos',
        'combinada' => 'Ida e Volta',
        'volta' => 'Ida e Volta · Volta',
        'ida_par' => 'Ida e Volta · Ida',
        default => 'Somente Ida',
    };
}

function reserva_categoria_rotulo(string $cat): string
{
    $cat = trim($cat);
    $map = [
        'VAN' => 'VAN',
        'SED' => 'SEDAN',
        'SEDAN' => 'SEDAN',
        'HAT' => 'HATCH',
        'HATCH' => 'HATCH',
        'MINIVAN' => 'MINI VAN',
        'MINI VAN' => 'MINI VAN',
        'MIC.VAN' => 'MICRO-ÔNIBUS',
        'MICRO' => 'MICRO-ÔNIBUS',
        '07LUG.' => 'VEÍCULOS 07 LUGARES',
        '07LUG' => 'VEÍCULOS 07 LUGARES',
        'ONIBUS' => 'ÔNIBUS',
        'ÔNIBUS' => 'ÔNIBUS',
    ];
    return $cat === '' ? '—' : ($map[$cat] ?? $cat);
}

function reserva_status_rotulo(string $status): string
{
    return match ($status) {
        'pendente' => 'Pendente',
        'reserva', 'confirmada' => 'Confirmada',
        'andamento', 'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
        default => ($status === '' ? '—' : status_label($status)),
    };
}

function reserva_quem_rotulo(array $trip): string
{
    $q = (string) ($trip['quem_viaja'] ?? '');
    if ($q === 'eu_mesmo') {
        return 'Eu mesmo';
    }
    if ((string) ($trip['quem'] ?? '') === 'parceiro') {
        return 'Parceiro';
    }
    return 'Motorista';
}

function reserva_faturado_rotulo(array $trip): string
{
    return !empty($trip['faturado']) ? 'Sim — cobrança posterior' : 'Não';
}

function reserva_data_txt(string $iso): string
{
    $iso = trim($iso);
    if ($iso === '') {
        return '—';
    }
    $data = br_date(substr($iso, 0, 10));
    return $data === '—' ? reserva_traco($iso) : $data;
}

function reserva_hora_txt(string $hora): string
{
    $hora = trim($hora);
    return $hora === '' ? '—' : $hora;
}

function reserva_pax_txt(mixed $pax): string
{
    if ($pax === null || $pax === '') {
        return '—';
    }
    return (string) max(0, (int) $pax);
}

function reserva_criada_txt(array $trip): string
{
    $raw = trim((string) ($trip['criado_em'] ?? ''));
    if ($raw === '') {
        return '—';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return reserva_data_txt($raw);
    }
    try {
        $dt = new DateTimeImmutable($raw);
        return $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
    } catch (Throwable) {
        $data = reserva_data_txt(substr($raw, 0, 10));
        if (preg_match('/(\d{2}:\d{2})/', $raw, $m) === 1) {
            return trim($data . ' ' . $m[1]);
        }
        return $data;
    }
}

function reserva_desconto_num(array $trip): float
{
    $d = $trip['desconto'] ?? 0;
    return is_numeric($d) ? (float) $d : 0.0;
}

function reserva_desconto_rotulo(array $trip): string
{
    $n = reserva_desconto_num($trip);
    if (abs($n) < 0.0001) {
        return '0%';
    }
    if (abs($n - round($n)) < 0.001) {
        return (string) (int) round($n) . '%';
    }
    return number_format($n, 2, ',', '.') . '%';
}

function reserva_valor_base_num(array $trip): float
{
    if (isset($trip['valor_base']) && is_numeric($trip['valor_base'])) {
        return (float) $trip['valor_base'];
    }
    return (float) ($trip['valor'] ?? 0);
}

function reserva_metodo_rotulo(array $trip): string
{
    $meio = trim((string) ($trip['meio'] ?? ''));
    if ($meio === '') {
        return '—';
    }
    $mapa = function_exists('meios_pagamento') ? meios_pagamento() : [];
    return (string) ($mapa[$meio] ?? $meio);
}

function reserva_texto_html(string $texto): string
{
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $partes = preg_split('#(https?://[^\s<]+)#u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($partes)) {
        return e($texto);
    }
    $html = '';
    foreach ($partes as $i => $parte) {
        if ($i % 2 === 1) {
            $url = rtrim($parte, '.,);]');
            $resto = substr($parte, strlen($url));
            $html .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a>' . e($resto);
        } else {
            $html .= e($parte);
        }
    }
    return $html;
}

/** @param array<string, string|array{html:string}> $linhas */
function reserva_ficha_bloco(string $titulo, array $linhas): void
{
    if ($linhas === []) {
        return;
    }
    echo '<section class="ficha-bloco"><h3>' . e($titulo) . '</h3><dl class="ficha-lista">';
    foreach ($linhas as $rotulo => $valor) {
        echo '<div><dt>' . e((string) $rotulo) . '</dt><dd class="ficha-pre">';
        if (is_array($valor)) {
            echo (string) ($valor['html'] ?? '');
        } else {
            echo e((string) $valor);
        }
        echo '</dd></div>';
    }
    echo '</dl></section>';
}

function reserva_rico(string $texto): array
{
    return ['html' => reserva_texto_html($texto)];
}

/**
 * @return array{
 *   rotulo:string,
 *   criada:string,
 *   cliente:array<string, string|array{html:string}>,
 *   viagem:array<string, string>,
 *   trechos:list<array{titulo:string,linhas:array<string, string|array{html:string}>}>,
 *   valores:array<string, string|array{html:string}>,
 *   motoristas:array<string, string>,
 *   observacoes:string,
 *   pdf:array<string, mixed>
 * }
 */
function reserva_ficha(array $db, array $trip, bool $painelMotorista = false): array
{
    if (function_exists('trip_com_obs')) {
        $trip = trip_com_obs($trip);
    }
    $perfil = reserva_perfil($trip);
    $rotulo = reserva_tipo_rotulo($trip);
    $esconde = !empty($trip['esconder_valores']);
    $obs = trim((string) ($trip['obs'] ?? ''));
    $mensagem = trim((string) ($trip['mensagem'] ?? ''));
    $mensagemVolta = trim((string) ($trip['mensagem_volta'] ?? ''));
    $itinerario = trim((string) ($trip['itinerario'] ?? ''));
    $telefone = $painelMotorista ? '' : (string) ($trip['telefone'] ?? '');
    $email = $painelMotorista ? '' : (string) ($trip['email'] ?? '');
    $documento = $painelMotorista ? '' : (string) ($trip['documento'] ?? '');
    $cliente = $painelMotorista ? '' : (string) ($trip['cliente'] ?? '');

    $clienteBloco = [
        'Cliente' => reserva_traco($cliente),
        'CPF/CNPJ' => reserva_traco($documento),
        'Telefone' => reserva_traco($telefone),
        'Email' => reserva_traco($email),
        'Quem Viaja' => reserva_quem_rotulo($trip),
    ];
    $viagem = [
        'Tipo de Viagem' => $rotulo,
        'Categoria do veículo' => reserva_categoria_rotulo((string) ($trip['categoria'] ?? '')),
        'Status' => reserva_status_rotulo((string) ($trip['status'] ?? '')),
    ];

    $rota = static function (string $embarque, string $desembarque, string $data, string $hora, mixed $pax, string $cupom, string $mensagem): array {
        $linhas = [
            'Embarque' => reserva_traco($embarque),
            'Desembarque' => reserva_traco($desembarque),
            'Data' => reserva_data_txt($data),
            'Hora' => reserva_hora_txt($hora),
            'Passageiros' => reserva_pax_txt($pax),
            'Cupom' => reserva_traco($cupom),
        ];
        if (trim($mensagem) !== '') {
            $linhas['Mensagem'] = reserva_rico($mensagem);
        }
        return $linhas;
    };

    $trechos = [];
    $pdfExtras = [];
    $detalhesRota = [];
    $cartoes = [];
    $cartoes2 = [];

    if ($perfil === 'trajetos') {
        $paradas = is_array($trip['trajetos'] ?? null) ? array_values($trip['trajetos']) : [];
        $linhas = [];
        $pdfParadas = [];
        foreach ($paradas as $i => $trecho) {
            $n = $i + 1;
            $embarque = (string) ($trecho['embarque'] ?? '');
            $desembarque = (string) ($trecho['desembarque'] ?? '');
            $data = (string) ($trecho['data'] ?? '');
            $hora = (string) ($trecho['hora'] ?? '');
            $pax = $trecho['pax'] ?? '';
            $linhas['Trecho ' . $n . ' embarque'] = reserva_traco($embarque);
            $linhas['Trecho ' . $n . ' desembarque'] = reserva_traco($desembarque);
            $linhas['Trecho ' . $n . ' data'] = reserva_data_txt($data);
            $linhas['Trecho ' . $n . ' hora'] = reserva_hora_txt($hora);
            $linhas['Trecho ' . $n . ' passageiros'] = reserva_pax_txt($pax);
            $pdfParadas['Trecho ' . $n] = trim(reserva_traco($embarque) . ' → ' . reserva_traco($desembarque));
            $pdfParadas['Data ' . $n] = trim(reserva_data_txt($data) . ' ' . reserva_hora_txt($hora));
            $pdfParadas['Passageiros ' . $n] = reserva_pax_txt($pax);
        }
        if ($linhas === []) {
            $linhas['Trajetos'] = '—';
        }
        $trechos[] = ['titulo' => '2 ou mais trajetos', 'linhas' => $linhas];
        $primeiro = $paradas[0] ?? [];
        $cartoes = [
            ['TRAJETOS', (string) max(count($paradas), 0)],
            ['1º DATA', reserva_data_txt((string) ($primeiro['data'] ?? ''))],
            ['PASSAGEIROS', reserva_pax_txt($primeiro['pax'] ?? '')],
        ];
        if ($pdfParadas !== []) {
            $pdfExtras[] = ['titulo' => 'Trajetos / paradas', 'linhas' => $pdfParadas];
        }
    } elseif ($perfil === 'hora') {
        $linhas = [
            'Endereço Início' => reserva_traco((string) ($trip['origem'] ?? '')),
            'Ponto Encerramento' => reserva_traco((string) ($trip['destino'] ?? '')),
            'Data' => reserva_data_txt((string) ($trip['data'] ?? '')),
            'Hora' => reserva_hora_txt((string) ($trip['hora'] ?? '')),
            'Passageiros' => reserva_pax_txt($trip['pax'] ?? ''),
            'Qtd. Horas' => reserva_traco((string) ($trip['qtd_horas'] ?? '')),
            'Cupom' => reserva_traco((string) ($trip['cupom'] ?? '')),
        ];
        if ($itinerario !== '') {
            $linhas['Itinerário'] = reserva_rico($itinerario);
        }
        if ($mensagem !== '') {
            $linhas['Mensagem'] = reserva_rico($mensagem);
        }
        $trechos[] = ['titulo' => 'Por Hora', 'linhas' => $linhas];
        $cartoes = [
            ['DATA', reserva_data_txt((string) ($trip['data'] ?? ''))],
            ['HORAS', reserva_traco((string) ($trip['qtd_horas'] ?? ''))],
            ['PASSAGEIROS', reserva_pax_txt($trip['pax'] ?? '')],
        ];
        $cartoes2 = [['Horário', reserva_hora_txt((string) ($trip['hora'] ?? ''))]];
        $porHora = [
            'End. Início' => reserva_traco((string) ($trip['origem'] ?? '')),
            'Encerramento' => reserva_traco((string) ($trip['destino'] ?? '')),
        ];
        if ($itinerario !== '') {
            $porHora['Itinerário'] = $itinerario;
        }
        $porHora['Cupom'] = reserva_traco((string) ($trip['cupom'] ?? ''));
        if ($mensagem !== '') {
            $porHora['Mensagem'] = $mensagem;
        }
        $pdfExtras[] = ['titulo' => 'Detalhes por hora', 'linhas' => $porHora];
    } elseif ($perfil === 'combinada') {
        $trechos[] = [
            'titulo' => '→ Ida',
            'linhas' => $rota(
                (string) ($trip['origem'] ?? ''),
                (string) ($trip['destino'] ?? ''),
                (string) ($trip['data'] ?? ''),
                (string) ($trip['hora'] ?? ''),
                $trip['pax'] ?? '',
                (string) ($trip['cupom'] ?? ''),
                $mensagem
            ),
        ];
        $paxVolta = array_key_exists('pax_volta', $trip) && $trip['pax_volta'] !== '' && $trip['pax_volta'] !== null
            ? $trip['pax_volta']
            : ($trip['pax'] ?? '');
        $trechos[] = [
            'titulo' => '⇆ Volta',
            'linhas' => $rota(
                (string) ($trip['volta_origem'] ?? ''),
                (string) ($trip['volta_destino'] ?? ''),
                (string) ($trip['data_volta'] ?? ''),
                (string) ($trip['hora_volta'] ?? ''),
                $paxVolta,
                (string) ($trip['cupom_volta'] ?? ''),
                $mensagemVolta
            ),
        ];
        if ($itinerario !== '') {
            $trechos[] = ['titulo' => 'Itinerário', 'linhas' => ['Itinerário' => reserva_rico($itinerario)]];
        }
        $cartoes = [
            ['DATA IDA', reserva_data_txt((string) ($trip['data'] ?? ''))],
            ['DATA VOLTA', reserva_data_txt((string) ($trip['data_volta'] ?? ''))],
            ['PASSAGEIROS', reserva_pax_txt($trip['pax'] ?? '')],
        ];
        $cartoes2 = [
            ['Horário Ida', reserva_hora_txt((string) ($trip['hora'] ?? ''))],
            ['Horário Volta', reserva_hora_txt((string) ($trip['hora_volta'] ?? ''))],
        ];
        $detalhesRota = [
            'Embarque' => reserva_traco((string) ($trip['origem'] ?? '')),
            'Destino' => reserva_traco((string) ($trip['destino'] ?? '')),
            'Passageiros' => reserva_pax_txt($trip['pax'] ?? ''),
            'Cupom' => reserva_traco((string) ($trip['cupom'] ?? '')),
        ];
        if ($mensagem !== '') {
            $detalhesRota['Mensagem'] = $mensagem;
        }
        $voltaPdf = [
            'Embarque' => reserva_traco((string) ($trip['volta_origem'] ?? '')),
            'Desembarque' => reserva_traco((string) ($trip['volta_destino'] ?? '')),
            'Passageiros' => reserva_pax_txt($paxVolta),
            'Cupom' => reserva_traco((string) ($trip['cupom_volta'] ?? '')),
        ];
        if ($mensagemVolta !== '') {
            $voltaPdf['Mensagem'] = $mensagemVolta;
        }
        $pdfExtras[] = ['titulo' => 'Detalhes da volta', 'linhas' => $voltaPdf];
        if ($itinerario !== '') {
            $pdfExtras[] = ['titulo' => 'Itinerário', 'linhas' => ['Itinerário' => $itinerario]];
        }
    } else {
        $titulo = $perfil === 'volta' ? '⇆ Volta' : '→ Ida';
        $trechos[] = [
            'titulo' => $titulo,
            'linhas' => $rota(
                (string) ($trip['origem'] ?? ''),
                (string) ($trip['destino'] ?? ''),
                (string) ($trip['data'] ?? ''),
                (string) ($trip['hora'] ?? ''),
                $trip['pax'] ?? '',
                (string) ($trip['cupom'] ?? ''),
                $mensagem
            ),
        ];
        $cartaoData = $perfil === 'volta' ? 'DATA VOLTA' : 'DATA IDA';
        $cartaoHora = $perfil === 'volta' ? 'Horário Volta' : 'Horário Ida';
        $cartoes = [
            [$cartaoData, reserva_data_txt((string) ($trip['data'] ?? ''))],
            ['PASSAGEIROS', reserva_pax_txt($trip['pax'] ?? '')],
            [$cartaoHora, reserva_hora_txt((string) ($trip['hora'] ?? ''))],
        ];
        $detalhesRota = [
            'Embarque' => reserva_traco((string) ($trip['origem'] ?? '')),
            'Destino' => reserva_traco((string) ($trip['destino'] ?? '')),
            'Passageiros' => reserva_pax_txt($trip['pax'] ?? ''),
            'Cupom' => reserva_traco((string) ($trip['cupom'] ?? '')),
        ];
        if ($mensagem !== '') {
            $detalhesRota['Mensagem'] = $mensagem;
        }
    }

    $valores = [
        'Valor Base' => brl(reserva_valor_base_num($trip)),
        'Desconto' => reserva_desconto_rotulo($trip),
        'Método de Pagamento' => reserva_metodo_rotulo($trip),
        'Valor Total' => brl((float) ($trip['valor'] ?? 0)),
        'Repasse do principal' => brl((float) ($trip['repasse'] ?? 0)),
        'Faturado' => reserva_faturado_rotulo($trip),
        'Esconder valores (motorista)' => $esconde ? 'Sim' : 'Não',
    ];
    if ($painelMotorista && $esconde) {
        $valores = [
            'Valores' => ['html' => e('Os valores cobrados ao cliente não estão visíveis.')],
            'Faturado' => reserva_faturado_rotulo($trip),
            'Esconder valores (motorista)' => 'Sim',
        ];
    }

    $nomeMotorista = (string) ($trip['quem'] ?? '') === 'parceiro'
        ? 'Parceiro'
        : nome_motorista($db, $trip['motorista_id'] ?? 0);
    $motoristas = ['Motorista' => reserva_traco($nomeMotorista)];
    $extras = is_array($trip['extras'] ?? null) ? array_values($trip['extras']) : [];
    $nExtra = 0;
    foreach ($extras as $extra) {
        $eid = is_array($extra) ? (int) ($extra['id'] ?? $extra['motorista_id'] ?? 0) : (int) $extra;
        if ($eid <= 0) {
            continue;
        }
        $nExtra++;
        $repasseExtra = is_array($extra) && isset($extra['repasse']) && is_numeric($extra['repasse'])
            ? brl((float) $extra['repasse'])
            : '—';
        $motoristas['Motorista extra ' . $nExtra] = reserva_traco(nome_motorista($db, $eid));
        $motoristas['Repasse extra ' . $nExtra] = $repasseExtra;
    }

    $servico = [
        'Serviço' => 'Transfer — ' . $rotulo,
        'Categoria' => reserva_categoria_rotulo((string) ($trip['categoria'] ?? '')),
        'Telefone' => reserva_traco($telefone),
    ];
    if ($obs !== '') {
        $servico['Observações gerais'] = $obs;
    }
    $preco = [];
    if (!$esconde) {
        $preco['Valor base'] = brl(reserva_valor_base_num($trip));
        if (reserva_desconto_num($trip) > 0) {
            $preco['Desconto'] = reserva_desconto_rotulo($trip);
        }
        $preco['Valor total'] = brl((float) ($trip['valor'] ?? 0));
    }
    $detalhes = [
        'Nome' => reserva_traco($cliente),
        'CPF/CNPJ' => reserva_traco($documento),
        'Telefone' => reserva_traco($telefone),
        'Email' => reserva_traco($email),
        'Pagamento' => reserva_metodo_rotulo($trip),
        'Faturado' => reserva_faturado_rotulo($trip),
        'Status' => reserva_status_rotulo((string) ($trip['status'] ?? '')),
        'Criada em' => reserva_criada_txt($trip),
    ];
    foreach ($detalhesRota as $chave => $valor) {
        $detalhes[$chave] = $valor;
    }

    return [
        'rotulo' => $rotulo,
        'criada' => reserva_criada_txt($trip),
        'cliente' => $clienteBloco,
        'viagem' => $viagem,
        'trechos' => $trechos,
        'valores' => $valores,
        'motoristas' => $motoristas,
        'observacoes' => $obs,
        'pdf' => [
            'oculto' => $esconde,
            'frase' => 'Os valores cobrados ao cliente não estão visíveis.',
            'servico' => $servico,
            'cartoes' => $cartoes,
            'cartoes2' => $cartoes2,
            'preco' => $preco,
            'pagamento' => [
                'Forma de pagamento' => reserva_metodo_rotulo($trip),
                'Faturado' => reserva_faturado_rotulo($trip),
            ],
            'extras' => $pdfExtras,
            'detalhes' => $detalhes,
        ],
    ];
}
