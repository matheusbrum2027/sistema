<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function dossie_pdf_bytes(array $db, array $mot): string
{
    $nome = trim((string) ($mot['nome'] ?? 'Motorista'));
    $empresa = trim((string) ($db['settings']['nome'] ?? 'Transfer FLN'));
    if ($empresa === '') {
        $empresa = 'Transfer FLN';
    }
    $cidade = ficha_cidade($mot);
    $endereco = ficha_endereco($mot);
    $pagamento = ficha_pagamento($mot);
    $status = ficha_status_frota($mot);
    $ativo = (string) ($mot['status'] ?? 'ativo') === 'ativo';
    $email = mb_strtolower(trim((string) ($mot['email'] ?? $mot['usuario'] ?? '')));
    $validade = trim((string) ($mot['cnh_validade'] ?? ''));
    $nasc = trim((string) ($mot['nascimento'] ?? ''));
    $hoje = date('d/m/Y');
    $relato = dossie_id($mot);
    $url = dossie_url_publica($mot);
    [$qrRaw, $qrLado] = qr_raw($url, 4);

    $navy = [0.106, 0.165, 0.290];
    $gold = [0.776, 0.627, 0.322];
    $ink = [0.122, 0.145, 0.188];
    $muted = [0.420, 0.455, 0.510];
    $line = [0.886, 0.898, 0.922];
    $green = [0.110, 0.520, 0.310];
    $greenBg = [0.890, 0.957, 0.914];
    $cream = [0.988, 0.965, 0.902];
    $white = [1, 1, 1];
    $paper = [1, 1, 1];

    $p1 = new FolhaPdf();
    $p1->ret(0, 0, 595.28, 841.89, $paper);
    $p1->ret(36, 788, 4, 28, $gold);
    $p1->txt(48, 798, 18, 'Dossiê do Motorista', true, $navy);
    $p1->txt(48, 784, 8, 'Relatório completo com dados cadastrais e documentos.', false, $muted);
    $p1->ret(360, 786, 198, 34, $cream);
    $p1->txt(370, 804, 8, 'Documento gerado em ' . $hoje, false, $ink);
    $p1->txt(370, 792, 8, 'ID do Relatório: ' . $relato, false, $muted);

    $p1->ret(36, 692, 72, 72, $navy);
    $p1->ret(36, 692, 4, 72, $gold);
    $p1->txt(52, 722, 16, ficha_iniciais($nome), true, $white);

    $p1->txt(122, 748, 14, $nome, true, $navy);
    $p1->ponto(122, 730, $gold);
    $p1->txt(132, 726, 9, 'CPF: ' . ficha_texto($mot, 'cpf'), false, $ink);
    $p1->ponto(122, 716, $gold);
    $p1->txt(132, 712, 9, 'Telefone: ' . ficha_texto($mot, 'telefone'), false, $ink);
    $p1->ponto(122, 702, $gold);
    $p1->txt(132, 698, 9, 'E-mail: ' . ($email !== '' ? $email : '—'), false, $ink);

    $p1->ponto(36, 674, $gold);
    $p1->txt(48, 670, 12, 'Informações principais', true, $navy);

    $celulas = [
        ['Status', $status, true],
        ['Cidade / UF', $cidade !== '' ? $cidade : '—', false],
        ['CNH', ficha_texto($mot, 'cnh'), false],
        ['Categoria CNH', ficha_texto($mot, 'categoria'), false],
        ['Validade CNH', $validade !== '' ? br_date($validade) : '—', false],
        ['IBGE município', '—', false],
        ['Tipo de pagamento', $pagamento !== '' ? $pagamento : '—', false],
        ['Telefone', ficha_texto($mot, 'telefone'), false],
        ['E-mail', $email !== '' ? $email : '—', false],
    ];
    $gx = 36.0;
    $gy = 578.0;
    $gw = 174.0;
    $gh = 42.0;
    foreach ($celulas as $i => [$rotulo, $valor, $selo]) {
        $col = $i % 3;
        $lin = intdiv($i, 3);
        $x = $gx + $col * $gw;
        $y = $gy - $lin * $gh;
        $p1->borda($x, $y, $gw, $gh, $line);
        $p1->ponto($x + 8, $y + 30, $gold);
        $p1->txt($x + 16, $y + 26, 7.5, $rotulo, false, $muted);
        if ($selo) {
            $p1->ret($x + 16, $y + 6, 78, 14, $ativo ? $greenBg : [0.95, 0.93, 0.90]);
            $p1->txt($x + 22, $y + 9, 8, $valor, true, $ativo ? $green : $muted);
        } else {
            $p1->txt($x + 16, $y + 10, 9, dossie_corta($valor, 28), true, $navy);
        }
    }

    $p1->ponto(36, 452, $gold);
    $p1->txt(48, 448, 12, 'Dados pessoais e documentos', true, $navy);
    $p1->linha(36, 438, 559, 438, $line);

    $p1->txt(36, 418, 10, 'Dados pessoais', true, $navy);
    $pessoais = [
        ['Nome completo', $nome],
        ['Data de nascimento', $nasc !== '' ? br_date($nasc) : '—'],
        ['RG', ficha_texto($mot, 'rg')],
        ['Órgão expedidor', '—'],
        ['CEP', ficha_texto($mot, 'cep')],
        ['Endereço', $endereco !== '' ? $endereco : '—'],
        ['E-mail', $email !== '' ? $email : '—'],
        ['Telefone', ficha_texto($mot, 'telefone')],
    ];
    $y = 400.0;
    foreach ($pessoais as [$rotulo, $valor]) {
        $p1->txt(36, $y, 7.5, $rotulo, false, $muted);
        $p1->txt(36, $y - 12, 9, dossie_corta($valor, 42), true, $ink);
        $y -= 22;
    }

    $docs = [
        ['Foto de perfil', ficha_doc_anexado($mot, ['foto', 'foto_perfil'])],
        ['CNH — frente', ficha_doc_anexado($mot, ['cnh_frente', 'cnh_foto'])],
        ['CNH — verso', ficha_doc_anexado($mot, ['cnh_verso'])],
        ['Comprovante de residência', ficha_doc_anexado($mot, ['comprovante', 'residencia'])],
    ];
    $p1->txt(320, 418, 10, 'Documentos', true, $navy);
    $yDoc = 398.0;
    foreach ($docs as [$rotulo, $anexo]) {
        $p1->txt(320, $yDoc, 9, $rotulo, false, $ink);
        $p1->txt(470, $yDoc, 9, $anexo ? 'Anexada' : 'Não anexada', true, $anexo ? $green : $muted);
        $yDoc -= 18;
    }

    $p1->txt(320, 312, 10, 'Dados de pagamento', true, $navy);
    $p1->borda(320, 232, 239, 70, $line);
    $p1->txt(332, 284, 7.5, 'Tipo de pagamento', false, $muted);
    $p1->txt(332, 270, 9, $pagamento !== '' ? $pagamento : '—', true, $navy);
    $p1->txt(332, 252, 7.5, 'Chave PIX / dados', false, $muted);
    $pix = trim((string) ($mot['pix'] ?? ''));
    $p1->txt(332, 238, 9, $pix !== '' ? dossie_corta($pix, 32) : '—', true, $ink);

    $p1->ponto(36, 198, $gold);
    $p1->txt(48, 194, 12, 'Informações complementares', true, $navy);
    $p1->borda(36, 132, 523, 48, $line);
    $p1->linha(36, 156, 559, 156, $line);
    $cols = [44, 150, 230, 340, 450];
    $heads = ['Nº CNH', 'Categoria', 'Validade', 'IBGE', 'Status'];
    $vals = [
        ficha_texto($mot, 'cnh'),
        ficha_texto($mot, 'categoria'),
        $validade !== '' ? br_date($validade) : '—',
        '—',
        '',
    ];
    foreach ($heads as $i => $head) {
        $p1->txt((float) $cols[$i], 164, 7.5, $head, false, $muted);
        if ($i < 4) {
            $p1->txt((float) $cols[$i], 140, 9, dossie_corta($vals[$i], 18), true, $navy);
        }
    }
    $p1->ret(450, 136, 86, 14, $ativo ? $greenBg : [0.95, 0.93, 0.90]);
    $p1->txt(456, 139, 8, $status, true, $ativo ? $green : $muted);
    $p1->txt(36, 40, 8, $empresa . ' · uso interno da operação', false, $muted);

    $p2 = new FolhaPdf();
    $p2->ret(0, 0, 595.28, 841.89, $paper);
    $p2->ret(36, 730, 523, 78, $navy);
    $p2->ret(36, 730, 5, 78, $gold);
    $p2->txt(52, 782, 12, 'Verificação de autenticidade', true, $white);
    $p2->txt(52, 766, 8, 'Digitalize o QR com a câmera. Abre a página pública para confirmar', false, [0.86, 0.89, 0.93]);
    $p2->txt(52, 754, 8, 'motorista oficial do operador — sem endereço nem documentos confidenciais.', false, [0.86, 0.89, 0.93]);
    $host = (string) (parse_url($url, PHP_URL_HOST) ?: 'driverflntransfer.app');
    $p2->txt(52, 738, 7, 'Selo digital estável · ' . $host . ' · cada leitura confirma o cadastro da frota', false, [0.73, 0.78, 0.84]);
    $p2->imagem(478, 740, 68, 68);

    return dossie_pdf_montar([$p1->ops, $p2->ops], $qrRaw, $qrLado);
}

function dossie_corta(string $texto, int $max): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    if (mb_strlen($texto) <= $max) {
        return $texto;
    }
    return mb_substr($texto, 0, $max - 1) . '...';
}

function dossie_pdf_montar(array $paginas, string $qrRaw, int $qrLado): string
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
    $kids = [];
    $next = 6;
    foreach ($paginas as $i => $stream) {
        $cid = $next++;
        $pid = $next++;
        $objs[$cid] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $xobj = $i === 1 ? ' /XObject << /Im1 5 0 R >>' : '';
        $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents ' . $cid
            . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobj . ' >> >>';
        $kids[] = $pid . ' 0 R';
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
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
        $this->ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $w, $h, $x, $y);
    }

    private function rgb(array $cor): string
    {
        return sprintf('%.3F %.3F %.3F', $cor[0], $cor[1], $cor[2]);
    }
}
