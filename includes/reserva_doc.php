<?php
declare(strict_types=1);

function reserva_pode_pdf(array $trip): bool
{
    if (($_SESSION['role'] ?? 'admin') !== 'motorista') {
        return true;
    }
    return empty($trip['esconder_valores']);
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
        'Status' => (string) ($trip['status'] ?? ''),
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
    if (reserva_pode_pdf($trip) && (($_SESSION['role'] ?? 'admin') === 'admin' || empty($trip['esconder_valores']))) {
        $linhas['Valor'] = brl((float) ($trip['valor'] ?? 0));
        $linhas['Repasse do motorista principal'] = brl((float) ($trip['repasse'] ?? 0));
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

function reserva_detalhes(array $db, array $trip): void
{
    echo '<dl class="detalhe-lista">';
    foreach (reserva_linhas($db, $trip) as $rotulo => $valor) {
        echo '<dt>' . e($rotulo) . '</dt><dd>' . e($valor) . '</dd>';
    }
    if ((string) ($trip['tipo'] ?? '') === 'trajetos' && is_array($trip['trajetos'] ?? null)) {
        foreach ($trip['trajetos'] as $i => $trecho) {
            echo '<dt>Trajeto ' . (int) ($i + 1) . '</dt><dd>' . e((string) ($trecho['embarque'] ?? '')) . ' → ' . e((string) ($trecho['desembarque'] ?? '')) . ' · ' . e(br_date((string) ($trecho['data'] ?? ''))) . ' ' . e((string) ($trecho['hora'] ?? '')) . ' · ' . (int) ($trecho['pax'] ?? 1) . ' passageiros</dd>';
        }
    }
    echo '</dl>';
}

function reserva_logo(array $s): string
{
    $logo = trim((string) ($s['logo'] ?? ''));
    if ($logo === '') {
        $logo = trim((string) ($s['logo_lateral'] ?? ''));
    }
    if ($logo === '') {
        return '';
    }
    return '<img src="' . e($logo) . '" alt="Logo da empresa" class="sheet-logo">';
}

function reserva_cabecalho_empresa(array $s, string $titulo): void
{
    echo '<header class="sheet-top">';
    echo '<div>' . reserva_logo($s);
    echo '<strong>' . e((string) ($s['razao'] ?? '')) . '</strong>';
    echo '<p>CNPJ ' . e((string) ($s['cnpj'] ?? '')) . '<br>' . e((string) ($s['endereco'] ?? '')) . '<br>Rep. legal: ' . e((string) ($s['rep_legal'] ?? '')) . '<br>' . e((string) ($s['email'] ?? '')) . ' · ' . e((string) ($s['telefone'] ?? '')) . '</p>';
    echo '</div>';
    echo '<div><strong>' . e($titulo) . '</strong><p>' . e((string) ($s['nome'] ?? '')) . '<br>Emitido em ' . e(today()->format('d/m/Y')) . '</p></div>';
    echo '</header>';
}

function reserva_folha(array $db, array $trip): void
{
    $s = $db['settings'];
    $mostraValor = (($_SESSION['role'] ?? 'admin') !== 'motorista') || empty($trip['esconder_valores']);
    echo '<article class="reserva-folha">';
    echo '<section class="folha-dados">';
    reserva_cabecalho_empresa($s, 'Reserva #' . (int) $trip['id']);
    if (trim((string) ($s['comp_principal'] ?? '')) !== '') {
        echo '<pre>' . e((string) $s['comp_principal']) . '</pre>';
    }
    echo '<h3>Dados da reserva</h3><dl class="detalhe-lista">';
    foreach (reserva_linhas($db, $trip) as $rotulo => $valor) {
        if (!$mostraValor && in_array($rotulo, ['Valor', 'Repasse do motorista principal', 'Pagamento'], true)) {
            continue;
        }
        echo '<dt>' . e($rotulo) . '</dt><dd>' . e($valor) . '</dd>';
    }
    echo '</dl>';
    if ((string) ($trip['tipo'] ?? '') === 'trajetos' && is_array($trip['trajetos'] ?? null)) {
        echo '<h3>Trajetos</h3><ol>';
        foreach ($trip['trajetos'] as $trecho) {
            echo '<li>' . e((string) ($trecho['embarque'] ?? '')) . ' → ' . e((string) ($trecho['desembarque'] ?? '')) . ' · ' . e(br_date((string) ($trecho['data'] ?? ''))) . ' ' . e((string) ($trecho['hora'] ?? '')) . ' · ' . (int) ($trecho['pax'] ?? 1) . ' passageiros</li>';
        }
        echo '</ol>';
    }
    if (trim((string) ($s['comp_rodape'] ?? '')) !== '') {
        echo '<p class="hint">' . e((string) $s['comp_rodape']) . '</p>';
    }
    echo '</section>';
    $blocos = [];
    if (!empty($s['contrato_pdf'])) {
        $titulos = [
            'contrato_modelo' => 'Contrato',
            'contrato_cancelamento' => 'Cancelamento',
            'contrato_clausulas' => 'Cláusulas',
        ];
        foreach ($titulos as $chave => $titulo) {
            $texto = trim((string) ($s[$chave] ?? ''));
            if ($texto === '') {
                continue;
            }
            $paraTexto = $trip;
            if (($_SESSION['role'] ?? 'admin') !== 'motorista') {
                $paraTexto['esconder_valores'] = false;
            }
            $blocos[] = [$titulo, apply_template($texto, $paraTexto, $db)];
        }
    }
    $ultimo = count($blocos) - 1;
    foreach ($blocos as $i => $bloco) {
        echo '<section class="folha-contrato">';
        reserva_cabecalho_empresa($s, 'Reserva #' . (int) $trip['id'] . ' · ' . $bloco[0]);
        echo '<h3>' . e($bloco[0]) . '</h3><pre>' . e($bloco[1]) . '</pre>';
        if ($i === $ultimo) {
            echo '<div class="sign"><div><span></span>Contratante<br>' . e((string) $trip['cliente']) . '</div><div><span></span>Contratada';
            if (!empty($s['assinatura'])) {
                echo '<img src="' . e((string) $s['assinatura']) . '" alt="" class="sign-img">';
            }
            echo '<br>' . e((string) ($s['assinatura_nome'] ?? $s['rep_legal'] ?? '')) . '</div></div>';
        }
        echo '</section>';
    }
    if ($blocos === [] && (!empty($s['assinatura']) || trim((string) ($s['assinatura_nome'] ?? '')) !== '')) {
        echo '<section class="folha-contrato">';
        reserva_cabecalho_empresa($s, 'Reserva #' . (int) $trip['id'] . ' · Assinatura');
        echo '<div class="sign"><div><span></span>Contratante<br>' . e((string) $trip['cliente']) . '</div><div><span></span>Contratada';
        if (!empty($s['assinatura'])) {
            echo '<img src="' . e((string) $s['assinatura']) . '" alt="" class="sign-img">';
        }
        echo '<br>' . e((string) ($s['assinatura_nome'] ?? '')) . '</div></div></section>';
    }
    echo '</article>';
}
