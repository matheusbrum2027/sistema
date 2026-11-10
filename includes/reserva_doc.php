<?php
declare(strict_types=1);

function reserva_pode_pdf(array $trip): bool
{
    return (($_SESSION['role'] ?? 'admin') !== 'motorista');
}

/**
 * Linhas da rota para o motorista — sem valor, repasse, pagamento ou faturamento.
 * Usado no PDF e no texto do WhatsApp ao comunicar a corrida.
 */
function reserva_rota_motorista(array $db, array $trip, string $trecho = ''): array
{
    $quando = trim(br_date((string) ($trip['data'] ?? '')) . ' ' . (string) ($trip['hora'] ?? ''));
    $motoPrincipal = (string) ($trip['quem'] ?? '') === 'parceiro'
        ? 'Parceiro'
        : nome_motorista($db, $trip['motorista_id'] ?? 0);
    $extras = [];
    foreach ($trip['extras'] ?? [] as $extra) {
        $nome = nome_motorista($db, $extra);
        if ($nome !== '' && $nome !== '—') {
            $extras[] = $nome;
        }
    }
    $tipoLimpo = $trip;
    $tipoLimpo['faturado'] = false;
    $rotuloTipo = $trecho !== ''
        ? $trecho
        : trim(preg_replace('/\s+/', ' ', strip_tags(tipo_badges($tipoLimpo))) ?? '');
    $linhas = [
        'Reserva' => '#' . (int) ($trip['id'] ?? 0),
        'Trecho' => $rotuloTipo,
        'Cliente' => (string) ($trip['cliente'] ?? ''),
        'Telefone do cliente' => (string) ($trip['telefone'] ?? ''),
        'Data e hora' => $quando,
        'Embarque' => (string) ($trip['origem'] ?? ''),
        'Desembarque' => (string) ($trip['destino'] ?? ''),
        'Passageiros' => (string) ($trip['pax'] ?? ''),
        'Categoria' => (string) ((categorias_veiculo()[(string) ($trip['categoria'] ?? '')] ?? ($trip['categoria'] ?? ''))),
        'Motorista' => $motoPrincipal,
    ];
    if ($extras !== []) {
        $linhas['Equipe'] = implode(', ', $extras);
    }
    if ((string) ($trip['tipo'] ?? '') === 'hora') {
        $linhas['Horas'] = (string) ($trip['qtd_horas'] ?? '');
    }
    $obs = trim((string) ($trip['obs'] ?? ''));
    if ($obs !== '') {
        $linhas['Observações'] = $obs;
    }
    if ((string) ($trip['tipo'] ?? '') === 'trajetos' && is_array($trip['trajetos'] ?? null)) {
        foreach ($trip['trajetos'] as $i => $t) {
            $linhas['Trajeto ' . (int) ($i + 1)] = trim(
                (string) ($t['embarque'] ?? '') . ' → ' . (string) ($t['desembarque'] ?? '')
                . ' · ' . br_date((string) ($t['data'] ?? '')) . ' ' . (string) ($t['hora'] ?? '')
                . ' · ' . (int) ($t['pax'] ?? 1) . ' pax'
            );
        }
    }
    // Segurança: nunca vazar campos financeiros neste documento.
    foreach (['Valor', 'Repasse', 'Repasse do motorista', 'Seu repasse', 'Pagamento', 'Faturado', 'Cupom'] as $proibido) {
        unset($linhas[$proibido]);
    }
    return array_filter($linhas, static fn(string $v): bool => trim($v) !== '');
}

function reserva_pdf_motorista(array $db, array $trip, string $trecho = ''): string
{
    $linhas = reserva_rota_motorista($db, $trip, $trecho);
    $titulo = 'Roteiro reserva #' . (int) ($trip['id'] ?? 0);
    if ($trecho !== '') {
        $titulo .= ' · ' . $trecho;
    }
    return pdf_simples_bytes($titulo, $linhas);
}

function reserva_texto_motorista(array $db, array $trip, string $trecho, string $nomeMotorista): string
{
    $linhas = reserva_rota_motorista($db, $trip, $trecho);
    $partes = ['Olá ' . $nomeMotorista . ', nova reserva atribuída a você.'];
    if ($trecho !== '') {
        $partes[] = 'Trecho: ' . $trecho;
    }
    foreach ($linhas as $rotulo => $valor) {
        if (in_array((string) $rotulo, ['Reserva', 'Trecho', 'Motorista', 'Equipe'], true)) {
            continue;
        }
        $partes[] = $rotulo . ': ' . $valor;
    }
    $partes[] = 'Segue o PDF do roteiro (apenas rotas, sem dados financeiros).';
    return implode("\n", $partes);
}

function reserva_linhas(array $db, array $trip): array
{
    $cats = categorias_veiculo();
    $cat = (string) ($trip['categoria'] ?? '');
    $semFat = $trip;
    $semFat['faturado'] = false;
    $linhas = [
        'Número' => '#' . (int) $trip['id'],
        'Cliente' => (string) ($trip['cliente'] ?? ''),
        'CPF/CNPJ' => (string) ($trip['documento'] ?? ''),
        'E-mail' => (string) ($trip['email'] ?? ''),
        'Telefone' => (string) ($trip['telefone'] ?? ''),
        'Tipo' => trim(preg_replace('/\s+/', ' ', strip_tags(tipo_badges($semFat))) ?? ''),
        'Categoria' => $cats[$cat] ?? $cat,
        'Embarque' => (string) ($trip['origem'] ?? ''),
        'Desembarque' => (string) ($trip['destino'] ?? ''),
        'Data' => br_date((string) ($trip['data'] ?? '')),
        'Hora' => (string) ($trip['hora'] ?? ''),
        'Passageiros' => (string) ($trip['pax'] ?? ''),
        'Cupom' => (string) ($trip['cupom'] ?? ''),
        'Motorista' => (string) ($trip['quem'] ?? '') === 'parceiro' ? 'Parceiro' : nome_motorista($db, $trip['motorista_id'] ?? 0),
        'Status' => status_label((string) ($trip['status'] ?? '')),
        'Faturado' => !empty($trip['faturado']) ? 'Sim' : 'Não',
    ];
    if ((string) ($trip['tipo'] ?? '') === 'hora') {
        $linhas['Quantidade de horas'] = (string) ($trip['qtd_horas'] ?? '');
    }
    $extras = [];
    foreach ($trip['extras'] ?? [] as $extra) {
        $extras[] = nome_motorista($db, $extra);
    }
    if ($extras !== []) {
        $linhas['Outros motoristas'] = implode(', ', $extras);
    }
    $ehMotorista = (($_SESSION['role'] ?? 'admin') === 'motorista');
    $esconde = !empty($trip['esconder_valores']);
    if ($ehMotorista) {
        unset($linhas['CPF/CNPJ'], $linhas['E-mail'], $linhas['Faturado']);
        if (!$esconde) {
            $linhas['Seu repasse'] = brl((float) ($trip['repasse'] ?? 0));
        }
    } elseif (!$esconde) {
        $linhas['Valor'] = brl((float) ($trip['valor'] ?? 0));
        $linhas['Repasse do motorista'] = brl((float) ($trip['repasse'] ?? 0));
        $pagos = [];
        foreach (meios_pagamento() as $chave => $rotulo) {
            if (isset($trip['pagamentos'][$chave])) {
                $pagos[] = $rotulo . ' ' . brl((float) $trip['pagamentos'][$chave]);
            }
        }
        if ($pagos !== []) {
            $linhas['Pagamento'] = implode(' · ', $pagos);
        }
    }
    $linhas['Observações'] = (string) ($trip['obs'] ?? '');
    return array_filter($linhas, static fn(string $v): bool => trim($v) !== '');
}

function detalhe_linha(string $rotulo, string $valor): void
{
    echo '<dt>' . e($rotulo) . '</dt>';
    echo '<dd><button type="button" class="copiar-dado no-print" data-copy="' . e($valor) . '" data-copy-keep="1" aria-label="Copiar ' . e($rotulo) . '">' . icon('copy') . '</button>';
    echo '<span>' . e($valor) . '</span>';
    echo '</dd>';
}

function reserva_detalhes(array $db, array $trip): void
{
    $trip = trip_com_obs($trip);
    echo '<dl class="detalhe-lista">';
    foreach (reserva_linhas($db, $trip) as $rotulo => $valor) {
        detalhe_linha((string) $rotulo, (string) $valor);
    }
    if ((string) ($trip['tipo'] ?? '') === 'trajetos' && is_array($trip['trajetos'] ?? null)) {
        foreach ($trip['trajetos'] as $i => $trecho) {
            $texto = (string) ($trecho['embarque'] ?? '') . ' → ' . (string) ($trecho['desembarque'] ?? '') . ' · ' . br_date((string) ($trecho['data'] ?? '')) . ' ' . (string) ($trecho['hora'] ?? '') . ' · ' . (int) ($trecho['pax'] ?? 1) . ' passageiros';
            detalhe_linha('Trajeto ' . (int) ($i + 1), $texto);
        }
    }
    echo '</dl>';
}

function reserva_logo(array $s): string
{
    $logo = trim((string) ($s['logo'] ?? ''));
    if ($logo === '' && !empty($s['tem_logo'])) {
        $logo = 'index.php?p=marca&k=logo&v=' . (int) ($s['marca_v'] ?? 1);
    }
    if ($logo === '') {
        $logo = trim((string) ($s['logo_lateral'] ?? ''));
    }
    if ($logo === '' && !empty($s['tem_logo_lateral'])) {
        $logo = 'index.php?p=marca&k=logo_lateral&v=' . (int) ($s['marca_v'] ?? 1);
    }
    if ($logo === '') {
        return '';
    }
    return '<img src="' . e($logo) . '" alt="Logo da empresa" class="sheet-logo">';
}

function reserva_cidade_uf(array $s): string
{
    $cidade = trim((string) ($s['cidade'] ?? ''));
    $uf = trim((string) ($s['uf'] ?? ''));
    if ($cidade !== '' && $uf !== '') {
        return $cidade . ' - ' . $uf;
    }
    return $cidade !== '' ? $cidade : $uf;
}

function reserva_rodape_texto(array $s): string
{
    $linha1 = implode(' · ', array_filter([
        trim((string) ($s['razao'] ?? '')),
        trim((string) ($s['cnpj'] ?? '')) !== '' ? 'CNPJ ' . trim((string) $s['cnpj']) : '',
        trim((string) ($s['endereco'] ?? '')),
    ]));
    $tel = trim((string) ($s['telefone'] ?? ''));
    $linha2 = implode(' · ', array_filter([
        $tel !== '' ? 'Tel +55 ' . preg_replace('/^\+?55\s*/', '', $tel) : '',
        trim((string) ($s['email'] ?? '')),
        trim((string) ($s['rep_legal'] ?? '')) !== '' ? 'Rep. legal: ' . trim((string) $s['rep_legal']) : '',
    ]));
    return trim($linha1 . "\n" . $linha2);
}

function reserva_cabecalho_empresa(array $s, string $titulo, string $kicker = 'Documento'): void
{
    $logo = reserva_logo($s);
    $marca = trim((string) ($s['nome'] ?? '')) ?: 'Transfer FLN';
    $razao = trim((string) ($s['razao'] ?? '')) ?: 'Fln Transfer Executivo e Turismo';
    $local = reserva_cidade_uf($s);
    echo '<header class="doc-banner">';
    echo '<div class="doc-banner-inner">';
    echo '<div class="doc-logo-box">';
    if ($logo !== '') {
        echo $logo;
    } else {
        echo '<div class="doc-mark" aria-hidden="true">' . e(mb_strtoupper(mb_substr($marca, 0, 3))) . '</div>';
    }
    echo '</div>';
    echo '<div class="doc-banner-copy">';
    echo '<p class="doc-kicker">' . e(mb_strtoupper($marca)) . '</p>';
    echo '<h2>' . e($razao) . '</h2>';
    if ($local !== '') {
        echo '<p class="doc-local">' . e($local) . '</p>';
    }
    echo '</div></div></header>';

    echo '<div class="doc-title-row">';
    echo '<div><p class="doc-title-kicker">' . e(mb_strtoupper($kicker)) . '</p><h3 class="doc-title">' . e($titulo) . '</h3></div>';
    echo '<em class="doc-emitido">Emitido em ' . e(today()->format('d/m/Y')) . '</em>';
    echo '</div>';

    echo '<ul class="doc-company-meta">';
    foreach ([
        'CNPJ' => (string) ($s['cnpj'] ?? ''),
        'Telefone' => (string) ($s['telefone'] ?? ''),
        'WhatsApp' => (string) ($s['whatsapp'] ?? ''),
        'E-mail' => (string) ($s['email'] ?? ''),
        'Representante' => (string) ($s['rep_legal'] ?? ''),
        'Endereço' => (string) ($s['endereco'] ?? ''),
    ] as $rotulo => $valor) {
        if (trim($valor) === '') {
            continue;
        }
        echo '<li><span>' . e($rotulo) . '</span><strong>' . e($valor) . '</strong></li>';
    }
    echo '</ul>';
}

function reserva_destaques(array $db, array $trip): void
{
    $quando = br_date((string) ($trip['data'] ?? ''));
    $hora = trim((string) ($trip['hora'] ?? ''));
    if ($hora !== '') {
        $quando = trim($quando . ' · ' . $hora);
    }
    $motorista = (string) ($trip['quem'] ?? '') === 'parceiro' ? 'Parceiro' : nome_motorista($db, $trip['motorista_id'] ?? 0);
    $status = status_label((string) ($trip['status'] ?? ''));
    echo '<div class="doc-facts">';
    echo '<div class="doc-fact"><span>Cliente</span><strong>' . e((string) ($trip['cliente'] ?? '')) . '</strong></div>';
    echo '<div class="doc-fact"><span>Data e hora</span><strong>' . e($quando) . '</strong></div>';
    echo '<div class="doc-fact doc-fact-route">';
    echo '<div class="doc-route-col"><span>Embarque</span><strong>' . e((string) ($trip['origem'] ?? '')) . '</strong></div>';
    echo '<div class="doc-route-line" aria-hidden="true"></div>';
    echo '<div class="doc-route-col"><span>Desembarque</span><strong>' . e((string) ($trip['destino'] ?? '')) . '</strong></div>';
    echo '</div>';
    echo '<div class="doc-fact"><span>Motorista</span><strong>' . e($motorista) . '</strong></div>';
    echo '<div class="doc-fact doc-fact-status"><span>Status</span><strong class="doc-status-pill">' . e($status) . '</strong></div>';
    echo '</div>';
}

function reserva_tabela(array $linhas): void
{
    echo '<table class="doc-table"><tbody>';
    foreach ($linhas as $rotulo => $valor) {
        echo '<tr><th scope="row">' . e((string) $rotulo) . '</th><td>' . e($valor) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function reserva_assinatura(array $s, array $trip): void
{
    $nome = trim((string) ($s['assinatura_nome'] ?? ''));
    if ($nome === '') {
        $nome = trim((string) ($s['rep_legal'] ?? ''));
    }
    echo '<footer class="doc-sign">';
    echo '<div class="doc-sign-box"><p>Contratante</p><div class="doc-sign-space"></div><strong>' . e((string) ($trip['cliente'] ?? '')) . '</strong>';
    $documento = trim((string) ($trip['documento'] ?? ''));
    if ($documento !== '') {
        echo '<span>' . e($documento) . '</span>';
    }
    echo '</div>';
    echo '<div class="doc-sign-box"><p>Contratada</p>';
    $assinatura = trim((string) ($s['assinatura'] ?? ''));
    if ($assinatura === '' && !empty($s['tem_assinatura'])) {
        $assinatura = 'index.php?p=marca&k=assinatura&v=' . (int) ($s['marca_v'] ?? 1);
    }
    if ($assinatura !== '') {
        echo '<img class="doc-sign-img" src="' . e($assinatura) . '" alt="Assinatura cadastrada">';
    } else {
        echo '<div class="doc-sign-space"></div>';
    }
    echo '<strong>' . e($nome) . '</strong><span>' . e((string) ($s['razao'] ?? '')) . '</span></div>';
    echo '</footer>';
}

function reserva_folha_rodape(array $s, int $reservaId, int $pagina, int $total): void
{
    $txt = reserva_rodape_texto($s);
    echo '<footer class="doc-page-foot">';
    echo '<div class="doc-page-foot-copy">' . nl2br(e($txt)) . '</div>';
    echo '<div class="doc-page-foot-pag"><strong>Reserva #' . $reservaId . '</strong><span>Página ' . $pagina . ' de ' . $total . '</span></div>';
    echo '</footer>';
}

/** Quebra contrato em seções "1. TÍTULO" + itens "1.1. texto". */
function reserva_parse_secoes(string $texto): array
{
    $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
    if ($texto === '') {
        return [];
    }
    $secoes = [];
    if (!preg_match_all('/(?=^\d+\.\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ ].+)/mu', $texto, $anchors, PREG_OFFSET_CAPTURE) || $anchors[0] === []) {
        return [['num' => '', 'titulo' => '', 'itens' => [['num' => '', 'texto' => $texto]]]];
    }
    $pontos = $anchors[0];
    for ($i = 0, $n = count($pontos); $i < $n; $i++) {
        $ini = (int) $pontos[$i][1];
        $fim = $i + 1 < $n ? (int) $pontos[$i + 1][1] : strlen($texto);
        $bloco = trim(substr($texto, $ini, $fim - $ini));
        $linhas = preg_split("/\n+/", $bloco) ?: [];
        $cab = array_shift($linhas) ?? '';
        $num = '';
        $titulo = $cab;
        if (preg_match('/^(\d+)\.\s+(.+)$/u', $cab, $m)) {
            $num = $m[1];
            $titulo = trim($m[2]);
        }
        $itens = [];
        $atual = null;
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            if (preg_match('/^(\d+\.\d+)\.?\s+(.*)$/u', $linha, $im)) {
                if ($atual !== null) {
                    $itens[] = $atual;
                }
                $atual = ['num' => $im[1], 'texto' => trim($im[2])];
            } elseif ($atual !== null) {
                $atual['texto'] .= ' ' . $linha;
            } else {
                $itens[] = ['num' => '', 'texto' => $linha];
            }
        }
        if ($atual !== null) {
            $itens[] = $atual;
        }
        $secoes[] = ['num' => $num, 'titulo' => $titulo, 'itens' => $itens];
    }
    return $secoes;
}

/** Política de cancelamento → cards com badge. */
function reserva_parse_cancelamento(string $texto): array
{
    $linhas = preg_split("/\n+/", str_replace(["\r\n", "\r"], "\n", trim($texto))) ?: [];
    $itens = [];
    $mapa = [
        ['+72h', 'ok', '/mais de 72|acima de 72|\+72/iu'],
        ['48–72h', 'warn', '/entre 48|48\s*[àa\-–]\s*72|48–72/iu'],
        ['< 48h', 'bad', '/menos de 48|abaixo de 48|<\s*48/iu'],
        ['NO-SHOW', 'crit', '/no-?show|não comparecimento|nao comparecimento/iu'],
    ];
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        $linha = ltrim($linha, "-•* \t");
        if ($linha === '' || preg_match('/^pol[ií]tica/iu', $linha)) {
            continue;
        }
        $badge = '';
        $tone = 'warn';
        foreach ($mapa as [$b, $t, $rx]) {
            if (preg_match($rx, $linha)) {
                $badge = $b;
                $tone = $t;
                break;
            }
        }
        if ($badge === '') {
            $badge = (string) (count($itens) + 1);
        }
        $itens[] = ['badge' => $badge, 'tone' => $tone, 'texto' => $linha];
    }
    return $itens;
}

/** Cláusulas 8.1… → lista numerada. */
function reserva_parse_clausulas(string $texto): array
{
    $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
    $texto = preg_replace('/^CL[ÁA]USULAS ADICIONAIS\s*/iu', '', $texto) ?? $texto;
    $itens = [];
    if (preg_match_all('/(\d+\.\d+)\.?\s+([\s\S]*?)(?=(?:\d+\.\d+)\.? |\z)/u', $texto . ' ', $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $itens[] = ['num' => $row[1], 'texto' => trim($row[2])];
        }
    }
    if ($itens === [] && $texto !== '') {
        foreach (preg_split("/\n+/", $texto) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha !== '') {
                $itens[] = ['num' => '', 'texto' => ltrim($linha, "-•* ")];
            }
        }
    }
    return $itens;
}

function reserva_bloco_contrato(string $titulo, string $texto): void
{
    $tituloLow = mb_strtolower($titulo);
    if (str_contains($tituloLow, 'cancel')) {
        echo '<h3 class="doc-sec-title">' . e($titulo) . '</h3>';
        echo '<p class="doc-sec-lead">POLÍTICA DE CANCELAMENTO</p>';
        echo '<div class="doc-cancel-list">';
        foreach (reserva_parse_cancelamento($texto) as $item) {
            echo '<div class="doc-cancel-item tone-' . e($item['tone']) . '">';
            echo '<span class="doc-cancel-badge">' . e($item['badge']) . '</span>';
            echo '<p>' . e($item['texto']) . '</p></div>';
        }
        echo '</div>';
        return;
    }
    if (str_contains($tituloLow, 'cláusula') || str_contains($tituloLow, 'clausula')) {
        echo '<h3 class="doc-sec-title">' . e($titulo) . '</h3>';
        echo '<p class="doc-sec-lead">CLÁUSULAS ADICIONAIS</p>';
        echo '<div class="doc-clause-list">';
        foreach (reserva_parse_clausulas($texto) as $item) {
            echo '<div class="doc-clause-item">';
            if ($item['num'] !== '') {
                echo '<span class="doc-clause-num">' . e($item['num']) . '</span>';
            }
            echo '<p>' . e($item['texto']) . '</p></div>';
        }
        echo '</div>';
        return;
    }
    echo '<h3 class="doc-sec-title">' . e($titulo) . '</h3>';
    foreach (reserva_parse_secoes($texto) as $sec) {
        echo '<section class="doc-contract-sec">';
        if ($sec['num'] !== '' || $sec['titulo'] !== '') {
            echo '<header class="doc-contract-h">';
            if ($sec['num'] !== '') {
                echo '<span class="doc-contract-num">' . e($sec['num']) . '</span>';
            }
            echo '<strong>' . e($sec['titulo']) . '</strong></header>';
        }
        echo '<div class="doc-contract-body">';
        foreach ($sec['itens'] as $item) {
            echo '<p>';
            if ($item['num'] !== '') {
                echo '<span class="doc-item-num">' . e($item['num']) . '.</span> ';
            }
            echo e($item['texto']) . '</p>';
        }
        echo '</div></section>';
    }
}

function reserva_folha(array $db, array $trip): void
{
    $trip = trip_com_obs($trip);
    $s = $db['settings'];
    $id = (int) $trip['id'];
    $blocos = [];
    if (!empty($s['contrato_pdf'])) {
        foreach ([
            'contrato_modelo' => 'Contrato',
            'contrato_cancelamento' => 'Cancelamento',
            'contrato_clausulas' => 'Cláusulas',
        ] as $chave => $titulo) {
            $texto = trim((string) ($s[$chave] ?? ''));
            if ($texto === '') {
                continue;
            }
            $blocos[] = [$titulo, apply_template($texto, $trip, $db)];
        }
    }
    $totalPaginas = 1 + count($blocos);
    $pagina = 1;

    echo '<article class="reserva-folha">';
    echo '<section class="folha-dados doc-page">';
    reserva_cabecalho_empresa($s, 'Reserva #' . $id, 'Documento');
    reserva_destaques($db, $trip);
    echo '<h3 class="doc-sec-title">Dados da reserva</h3>';
    $linhas = reserva_linhas($db, $trip);
    // PDF de confirmação: sem valores financeiros (como o modelo oficial).
    unset(
        $linhas['Repasse do motorista'],
        $linhas['Seu repasse'],
        $linhas['Faturado'],
        $linhas['Valor'],
        $linhas['Pagamento']
    );
    reserva_tabela($linhas);
    if ((string) ($trip['tipo'] ?? '') === 'trajetos' && is_array($trip['trajetos'] ?? null) && $trip['trajetos'] !== []) {
        echo '<h3 class="doc-sec-title">Trajetos</h3><ol class="doc-stops">';
        foreach ($trip['trajetos'] as $trecho) {
            echo '<li><strong>' . e((string) ($trecho['embarque'] ?? '')) . ' → ' . e((string) ($trecho['desembarque'] ?? '')) . '</strong><span>' . e(br_date((string) ($trecho['data'] ?? ''))) . ' ' . e((string) ($trecho['hora'] ?? '')) . ' · ' . (int) ($trecho['pax'] ?? 1) . ' passageiros</span></li>';
        }
        echo '</ol>';
    }
    if (trim((string) ($s['comp_rodape'] ?? '')) !== '') {
        echo '<p class="doc-foot">' . e((string) $s['comp_rodape']) . '</p>';
    }
    reserva_assinatura($s, $trip);
    reserva_folha_rodape($s, $id, $pagina, $totalPaginas);
    echo '</section>';

    $ultimo = count($blocos) - 1;
    foreach ($blocos as $i => $bloco) {
        $pagina++;
        $kicker = str_contains(mb_strtolower($bloco[0]), 'contrato') ? 'Transfer › Contrato' : 'Documento';
        echo '<section class="folha-contrato doc-page">';
        reserva_cabecalho_empresa($s, 'Reserva #' . $id . ' · ' . $bloco[0], $kicker);
        reserva_bloco_contrato($bloco[0], $bloco[1]);
        if ($i === $ultimo) {
            reserva_assinatura($s, $trip);
        }
        reserva_folha_rodape($s, $id, $pagina, $totalPaginas);
        echo '</section>';
    }
    echo '</article>';
}

function reserva_dialogos(array $db, array $trip, bool $admin, string $contexto = 'agenda'): void
{
    $trip = trip_com_obs($trip);
    $id = (int) $trip['id'];
    $legsCom = agenda_legs($trip);
    ?>
    <dialog id="dlg-ver-<?= $id ?>" class="dlg-detalhe">
      <header class="dialog-h">
        <h2>Detalhes da reserva #<?= $id ?></h2>
        <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
      </header>
      <div class="dialog-b"><?php reserva_detalhes($db, $trip); ?></div>
      <footer class="dlg-foot">
        <?php if ($admin): ?>
          <a class="btn btn-sm" href="<?= e(href('reservas', ['edit' => $id])) ?>">Editar</a>
          <?php if (count($legsCom) > 1): ?>
            <?php foreach ($legsCom as $legCom): ?>
              <button class="btn btn-sm" type="button" data-open-dialog="dlg-com-<?= $id ?>-<?= e((string) $legCom['kind']) ?>">Comunicar <?= e((string) $legCom['label']) ?></button>
            <?php endforeach; ?>
          <?php else: ?>
            <button class="btn btn-sm" type="button" data-open-dialog="dlg-com-<?= $id ?>">Comunicar corrida</button>
          <?php endif; ?>
          <?php if ($contexto === 'reservas'): ?>
            <form method="post" action="index.php" data-confirm="Enviar a reserva #<?= $id ?> de <?= e((string) $trip['cliente']) ?> para o lixo? Ela sai da agenda e do financeiro em aberto, mas pode ser restaurada em até 30 dias.">
              <?= csrf_fields('delete_transfer') ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
            </form>
          <?php else: ?>
          <form method="post" action="index.php" data-confirm="<?= !empty($trip['faturado']) ? 'Tirar o faturamento da reserva #' . $id . '?' : 'Marcar a reserva #' . $id . ' como faturada?' ?>">
            <?= csrf_fields('faturar_transfer') ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-sm" type="submit"><?= !empty($trip['faturado']) ? 'Tirar faturamento' : 'Faturar' ?></button>
          </form>
          <?php endif; ?>
        <?php else: ?>
          <form method="post" action="index.php" data-confirm="Alterar o status da reserva #<?= $id ?>?">
            <?= csrf_fields('set_transfer_status') ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <label class="field">Status
              <select name="status">
                <?php foreach (['pendente' => 'Pendente', 'reserva' => 'Reservada', 'andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'] as $k => $lab): ?>
                  <option value="<?= e($k) ?>"<?= ($trip['status'] ?? '') === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="btn btn-sm btn-primary" type="submit">Atualizar status</button>
          </form>
        <?php endif; ?>
        <?php if (reserva_pode_pdf($trip)): ?>
          <button class="btn btn-sm btn-primary" type="button" data-open-dialog="dlg-pdf-<?= $id ?>">Baixar PDF</button>
        <?php endif; ?>
      </footer>
    </dialog>
    <?php if ($admin):
    $painéisCom = count($legsCom) > 1
        ? array_map(static fn(array $leg): array => ['sufixo' => (string) $leg['kind'], 'perna' => (string) $leg['label'], 'titulo' => 'Comunicar ' . $leg['label']], $legsCom)
        : [['sufixo' => '', 'perna' => '', 'titulo' => 'Comunicar corrida']];
    foreach ($painéisCom as $painelCom):
        $destinos = destinatarios_comunicar($db, $trip, (string) $painelCom['perna']);
        $dlgId = 'dlg-com-' . $id . ($painelCom['sufixo'] !== '' ? '-' . $painelCom['sufixo'] : '');
        $podeEnviar = false;
        foreach ($destinos as $dChk) {
            if (strlen((string) ($dChk['numero'] ?? '')) >= 12) {
                $podeEnviar = true;
                break;
            }
        }
    ?>
      <dialog id="<?= e($dlgId) ?>" class="dlg-com">
        <header class="dialog-h">
          <h2><?= e((string) $painelCom['titulo']) ?> · #<?= $id ?></h2>
          <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
        </header>
        <form method="post" action="index.php" class="dialog-b com-form" data-confirm="Enviar WhatsApp aos destinatários marcados na reserva #<?= $id ?>?">
          <?= csrf_fields('comunicar_reserva') ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <?php if ($painelCom['perna'] !== ''): ?>
            <input type="hidden" name="perna" value="<?= e((string) $painelCom['perna']) ?>">
          <?php endif; ?>
          <?php
            $nMotOk = 0;
            $nMotAtrib = 0;
            foreach ($destinos as $dMeta) {
                if (($dMeta['papel'] ?? '') !== 'motorista') {
                    continue;
                }
                if (strlen((string) ($dMeta['numero'] ?? '')) >= 12) {
                    $nMotOk++;
                }
                if (empty($dMeta['opcional'])) {
                    $nMotAtrib++;
                }
            }
          ?>
          <p class="help"><?php if ($nMotAtrib > 0): ?>
            Motorista da reserva já vem marcado. Marque o cliente só se quiser avisar também.
          <?php elseif ($nMotOk > 0): ?>
            Esta reserva ainda não tem motorista vinculado — escolha quem deve receber o card. Cliente é opcional.
          <?php else: ?>
            Nenhum motorista com telefone válido. Cadastre o WhatsApp do motorista ou vincule um na reserva.
          <?php endif; ?> Cada um recebe o card no WhatsApp (confirmar, ver PDF, falar com atendente).</p>
          <div class="com-cards" role="group" aria-label="Destinatários">
            <?php foreach ($destinos as $dest): ?>
              <?php
                $okFone = strlen((string) ($dest['numero'] ?? '')) >= 12;
                $papel = (string) ($dest['papel'] ?? 'motorista');
                $marcado = $okFone && empty($dest['opcional']);
                $rotulo = $papel === 'cliente'
                    ? 'Cliente'
                    : 'Motorista' . ((string) ($dest['trecho'] ?? '') !== '' && ($dest['trecho'] ?? '') !== 'Reserva' ? ' · ' . $dest['trecho'] : '');
              ?>
              <label class="com-card com-card-<?= e($papel) ?><?= $marcado ? ' is-on' : '' ?><?= $okFone ? '' : ' is-off' ?>"<?= $okFone ? '' : ' title="Telefone incompleto"' ?>>
                <input type="checkbox" name="destino[]" value="<?= e((string) $dest['key']) ?>"<?= $marcado ? ' checked' : '' ?><?= $okFone ? '' : ' disabled' ?>>
                <span class="com-card-mark" aria-hidden="true"></span>
                <span class="com-card-papel"><?= e($rotulo) ?></span>
                <strong><?= e((string) ($dest['nome'] ?? '')) ?></strong>
                <small><?= e((string) ($dest['telefone'] ?? '') !== '' ? (string) $dest['telefone'] : 'Sem telefone') ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($nMotOk === 0): ?>
            <p class="help com-aviso">Sem motorista com WhatsApp nesta lista. Edite a reserva e arraste um motorista no engate, ou complete o telefone no cadastro.</p>
          <?php endif; ?>
          <footer class="dlg-foot com-foot">
            <button class="btn" type="button" data-close-dialog>Cancelar</button>
            <button class="btn btn-primary" type="submit"<?= $podeEnviar ? '' : ' disabled' ?>>Enviar WhatsApp</button>
          </footer>
        </form>
      </dialog>
    <?php endforeach; endif; ?>
    <?php if (reserva_pode_pdf($trip)): ?>
      <dialog id="dlg-pdf-<?= $id ?>" class="dlg-pdf">
        <header class="dialog-h no-print">
          <h2>Pré-visualização da reserva #<?= $id ?></h2>
          <div class="dialog-tools">
            <button class="btn btn-sm btn-primary" type="button" data-print data-confirm="Baixar o PDF da reserva #<?= $id ?>?">Baixar PDF</button>
            <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
          </div>
        </header>
        <div class="dialog-b"><?php reserva_folha($db, $trip); ?></div>
        <footer class="dlg-foot no-print">
          <button class="btn btn-primary" type="button" data-print data-confirm="Baixar o PDF da reserva #<?= $id ?>?">Baixar PDF</button>
          <button class="btn" type="button" data-close-dialog>Fechar</button>
        </footer>
      </dialog>
    <?php endif;
}
