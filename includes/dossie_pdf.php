<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function dossie_pdf_bytes(array $db, array $mot): string
{
    $settings = is_array($db['settings'] ?? null) ? $db['settings'] : [];
    $nome = trim((string) ($mot['nome'] ?? 'Motorista'));
    $empresa = trim((string) ($settings['nome'] ?? 'Transfer FLN'));
    if ($empresa === '') {
        $empresa = 'Transfer FLN';
    }
    $razao = trim((string) ($settings['razao'] ?? ''));
    $cidade = ficha_cidade($mot);
    $endereco = ficha_endereco($mot);
    $pagamento = ficha_pagamento($mot);
    $status = ficha_status_frota($mot);
    $ativo = (string) ($mot['status'] ?? 'ativo') === 'ativo';
    $email = mb_strtolower(trim((string) ($mot['email'] ?? '')));
    $login = mb_strtolower(trim((string) ($mot['usuario'] ?? $mot['email'] ?? '')));
    $hoje = date('d/m/Y');
    $relato = dossie_id($mot);
    $url = dossie_url_publica($mot);
    [$qrRaw, $qrLado] = qr_raw($url, 4);
    $logo = null;
    if (function_exists('reserva_midia_src') && function_exists('reserva_pdf_jpeg')) {
        $src = reserva_midia_src($settings, 'logo');
        if ($src === '') {
            $src = reserva_midia_src($settings, 'logo_lateral');
        }
        $logo = reserva_pdf_jpeg($src);
    }

    $navy = [0.082, 0.125, 0.200];
    $orange = [0.949, 0.420, 0.102];
    $ink = [0.122, 0.145, 0.188];
    $muted = [0.420, 0.455, 0.510];
    $line = [0.886, 0.898, 0.922];
    $green = [0.110, 0.520, 0.310];
    $greenBg = [0.890, 0.957, 0.914];
    $white = [1, 1, 1];
    $gelo = [0.965, 0.969, 0.976];
    $suave = [0.820, 0.855, 0.900];

    $pagina = new FolhaPdf();
    $pagina->ret(0, 0, 595.28, 841.89, $white);
    $pagina->ret(0, 756, 595.28, 86, $navy);
    $pagina->ret(0, 752, 595.28, 4, $orange);

    $textoX = 32.0;
    $temLogo = is_array($logo) && (int) ($logo['w'] ?? 0) > 1 && (int) ($logo['h'] ?? 0) > 1;
    if ($temLogo) {
        $pagina->ret(28, 766, 58, 58, $white);
        $escala = min(48 / (int) $logo['w'], 48 / (int) $logo['h']);
        $iw = (int) $logo['w'] * $escala;
        $ih = (int) $logo['h'] * $escala;
        $pagina->imagemNome('ImLogo', 33 + ((48 - $iw) / 2), 771 + ((48 - $ih) / 2), $iw, $ih);
        $textoX = 98;
    }
    $pagina->txt($textoX, 816, 9, dossie_corta(mb_strtoupper($empresa), 42), true, $orange);
    $pagina->txt($textoX, 796, 16, 'Dossiê do motorista', true, $white);
    $sub = $razao !== '' ? $razao : 'Ficha cadastral de uso interno';
    $pagina->txt($textoX, 778, 8, dossie_corta($sub, 62), false, $suave);
    $pagina->txt(400, 812, 8, 'Gerado em ' . $hoje, false, $suave);
    $pagina->txt(400, 798, 8, dossie_corta($relato, 28), true, $white);

    $pagina->ret(24, 616, 547, 124, $gelo);
    $pagina->ret(24, 616, 4, 124, $orange);
    $pagina->txt(40, 712, 16, dossie_corta($nome, 36), true, $navy);
    $pagina->ret(40, 686, $ativo ? 120 : 58, 16, $ativo ? $greenBg : [0.93, 0.93, 0.93]);
    $pagina->txt(48, 690, 8, $status, true, $ativo ? $green : $muted);
    $pagina->txt(40, 666, 9, 'CPF  ' . ficha_texto($mot, 'cpf', 'Não informado'), false, $ink);
    $pagina->txt(250, 666, 9, 'Telefone  ' . ficha_texto($mot, 'telefone', 'Não informado'), false, $ink);
    $pagina->txt(40, 648, 9, 'E-mail  ' . ($email !== '' ? dossie_corta($email, 46) : 'Não informado'), false, $ink);
    $pagina->txt(40, 632, 8, 'Login  ' . ($login !== '' ? dossie_corta($login, 46) : 'Não informado'), false, $muted);

    $banco = trim((string) ($mot['banco'] ?? ''));
    $agencia = trim((string) ($mot['agencia'] ?? ''));
    $conta = trim((string) ($mot['conta'] ?? ''));
    $colunas = [
        [32.0, 'Identificação', [
            ['RG', ficha_texto($mot, 'rg', 'Não informado')],
            ['Nascimento', ficha_data_txt((string) ($mot['nascimento'] ?? ''))],
            ['Na frota desde', ficha_desde_txt($mot)],
        ]],
        [214.0, 'Endereço', [
            ['Cidade / UF', $cidade !== '' ? $cidade : 'Não informada'],
            ['CEP', ficha_texto($mot, 'cep', 'Não informado')],
            ['Logradouro', $endereco !== '' ? $endereco : 'Não informado'],
        ]],
        [396.0, 'CNH', [
            ['Número', ficha_texto($mot, 'cnh', 'Não informado')],
            ['Categoria', ficha_texto($mot, 'categoria', 'Não informada')],
            ['Validade', ficha_cnh_txt((string) ($mot['cnh_validade'] ?? ''))],
        ]],
    ];
    foreach ($colunas as [$x, $titulo, $linhas]) {
        $pagina->txt($x, 592, 11, $titulo, true, $navy);
        $pagina->ret($x, 584, 150, 2, $orange);
        $y = 566.0;
        foreach ($linhas as [$rotulo, $valor]) {
            $pagina->txt($x, $y, 7.5, $rotulo, false, $muted);
            $pagina->txt($x, $y - 12, 9, dossie_corta((string) $valor, 28), true, $ink);
            $y -= 32;
        }
    }

    $pagina->txt(32, 456, 11, 'Repasse', true, $navy);
    $pagina->ret(32, 448, 150, 2, $orange);
    $repasses = [
        ['Forma', $pagamento !== '' ? $pagamento : 'Não informada'],
        ['Chave PIX', ficha_texto($mot, 'pix', 'Não informada')],
        ['Banco', $banco !== '' ? $banco : 'Não informado'],
        ['Agência / conta', trim($agencia . ($agencia !== '' && $conta !== '' ? ' · ' : '') . $conta) ?: 'Não informada'],
    ];
    foreach ($repasses as $i => [$rotulo, $valor]) {
        $x = 32 + ($i * 140);
        $pagina->borda($x, 386, 132, 52, $line);
        $pagina->txt($x + 8, 420, 7.5, $rotulo, false, $muted);
        $pagina->txt($x + 8, 402, 9, dossie_corta($valor, 22), true, $navy);
    }

    $obs = trim((string) ($mot['obs'] ?? ''));
    if ($obs !== '') {
        $pagina->txt(32, 364, 11, 'Observações', true, $navy);
        $pagina->txt(32, 346, 9, dossie_corta($obs, 110), false, $ink);
    }

    $pagina->ret(0, 0, 595.28, 128, $navy);
    $pagina->ret(0, 128, 595.28, 3, $orange);
    $pagina->txt(28, 100, 12, 'Confirmação da frota', true, $white);
    $pagina->txt(28, 82, 8, 'O QR abre a página pública e confirma que este motorista', false, $suave);
    $pagina->txt(28, 70, 8, 'pertence à operação. Endereço, PIX e documentos não saem nesse link.', false, $suave);
    $host = (string) (parse_url($url, PHP_URL_HOST) ?: 'driverflntransfer.app');
    $pagina->txt(28, 48, 8, dossie_corta($empresa . ' - ' . $host . ' - uso interno', 70), false, $suave);
    $pagina->ret(478, 28, 88, 88, $white);
    $pagina->imagem(484, 34, 76, 76);

    return dossie_pdf_montar($pagina->ops, $qrRaw, $qrLado, $temLogo ? $logo : null);
}

function dossie_corta(string $texto, int $max): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    if (mb_strlen($texto) <= $max) {
        return $texto;
    }
    return mb_substr($texto, 0, $max - 1) . '...';
}

function dossie_pdf_montar(string $stream, string $qrRaw, int $qrLado, ?array $logo): string
{
    $img = gzcompress($qrRaw);
    if ($img === false) {
        $img = $qrRaw;
        $filtro = '';
    } else {
        $filtro = ' /Filter /FlateDecode';
    }
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $objs[5] = '<< /Type /XObject /Subtype /Image /Width ' . $qrLado . ' /Height ' . $qrLado
        . ' /ColorSpace /DeviceGray /BitsPerComponent 8' . $filtro
        . ' /Length ' . strlen($img) . " >>\nstream\n" . $img . "\nendstream";
    $next = 6;
    $logoRef = '';
    if (is_array($logo)) {
        $logoId = dossie_obj_imagem($objs, $next, $logo);
        if ($logoId > 0) {
            $logoRef = '/ImLogo ' . $logoId . ' 0 R ';
        }
    }
    $cid = $next++;
    $pid = $next++;
    $objs[$cid] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents ' . $cid
        . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Im1 5 0 R ' . $logoRef . '>> >> >>';
    $objs[2] = '<< /Type /Pages /Kids [' . $pid . ' 0 R] /Count 1 >>';
    ksort($objs);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = (int) max(array_keys($objs));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

/** @param array<int, string> $objs */
function dossie_obj_imagem(array &$objs, int &$next, array $imagem): int
{
    $w = (int) ($imagem['w'] ?? 0);
    $h = (int) ($imagem['h'] ?? 0);
    if ($w < 2 || $h < 2) {
        return 0;
    }
    if (!empty($imagem['jpeg']) && is_string($imagem['jpeg'])) {
        $id = $next++;
        $jpeg = $imagem['jpeg'];
        $objs[$id] = '<< /Type /XObject /Subtype /Image /Width ' . $w
            . ' /Height ' . $h
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg)
            . " >>\nstream\n" . $jpeg . "\nendstream";
        return $id;
    }
    if (empty($imagem['flate']) || !is_string($imagem['rgb'] ?? null)) {
        return 0;
    }
    $id = $next++;
    $mascara = '';
    if (!empty($imagem['mask']) && is_string($imagem['mask'])) {
        $mid = $next++;
        $mask = $imagem['mask'];
        $objs[$mid] = '<< /Type /XObject /Subtype /Image /Width ' . $w
            . ' /Height ' . $h
            . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($mask)
            . " >>\nstream\n" . $mask . "\nendstream";
        $mascara = ' /SMask ' . $mid . ' 0 R';
    }
    $rgb = (string) $imagem['rgb'];
    $objs[$id] = '<< /Type /XObject /Subtype /Image /Width ' . $w
        . ' /Height ' . $h
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($rgb)
        . $mascara
        . " >>\nstream\n" . $rgb . "\nendstream";
    return $id;
}

final class FolhaPdf
{
    public string $ops = '';

    public function ret(float $x, float $y, float $w, float $h, array $cor): void
    {
        $this->ops .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $this->rgb($cor), $x, $y, $w, $h);
    }

    public function borda(float $x, float $y, float $w, float $h, array $cor): void
    {
        $this->ops .= sprintf("%s RG 0.6 w %.2F %.2F %.2F %.2F re S\n", $this->rgb($cor), $x, $y, $w, $h);
    }

    public function linha(float $x1, float $y1, float $x2, float $y2, array $cor): void
    {
        $this->ops .= sprintf("%s RG 0.6 w %.2F %.2F m %.2F %.2F l S\n", $this->rgb($cor), $x1, $y1, $x2, $y2);
    }

    public function ponto(float $x, float $y, array $cor): void
    {
        $this->ops .= sprintf("%s rg %.2F %.2F 2.2 0 360 arc f\n", $this->rgb($cor), $x, $y);
    }

    public function txt(float $x, float $y, float $tamanho, string $texto, bool $negrito, array $cor): void
    {
        $fonte = $negrito ? 'F2' : 'F1';
        $win = pdf_simples_latin1($texto);
        $this->ops .= sprintf(
            "BT %s rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->rgb($cor),
            $fonte,
            $tamanho,
            $x,
            $y,
            pdf_simples_esc($win)
        );
    }

    public function imagem(float $x, float $y, float $w, float $h): void
    {
        $this->imagemNome('Im1', $x, $y, $w, $h);
    }

    public function imagemNome(string $nome, float $x, float $y, float $w, float $h): void
    {
        $this->ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $x, $y, $nome);
    }

    public function pilula(float $x, float $y, float $w, float $h, array $cor): void
    {
        $raio = $h / 2;
        if ($w <= $h) {
            $this->ops .= sprintf("%s rg %.2F %.2F %.2F 0 360 arc f\n", $this->rgb($cor), $x + $w / 2, $y + $raio, $raio);
            return;
        }
        $this->ret($x + $raio, $y, $w - $h, $h, $cor);
        $this->ops .= sprintf("%s rg %.2F %.2F %.2F 0 360 arc f\n", $this->rgb($cor), $x + $raio, $y + $raio, $raio);
        $this->ops .= sprintf("%s rg %.2F %.2F %.2F 0 360 arc f\n", $this->rgb($cor), $x + $w - $raio, $y + $raio, $raio);
    }

    private function rgb(array $cor): string
    {
        return sprintf('%.3F %.3F %.3F', $cor[0], $cor[1], $cor[2]);
    }
}
