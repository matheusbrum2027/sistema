<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * PDF texto mínimo (Helvetica / WinAnsi) — sem libs externas.
 * Uso: rota do motorista (sem valores financeiros).
 */
function pdf_simples_bytes(string $titulo, array $linhas, ?string $rodape = null, ?array $imagem = null): string
{
    $imagemJpeg = is_array($imagem) ? (string) ($imagem['jpeg'] ?? '') : '';
    $imagemW = is_array($imagem) ? (int) ($imagem['w'] ?? 0) : 0;
    $imagemH = is_array($imagem) ? (int) ($imagem['h'] ?? 0) : 0;
    $temImagem = $imagemJpeg !== '' && $imagemW > 0 && $imagemH > 0;
    $blocos = [];
    $blocos[] = pdf_simples_latin1($titulo);
    $blocos[] = str_repeat('-', 48);
    foreach ($linhas as $rotulo => $valor) {
        $valor = trim((string) $valor);
        if ($valor === '') {
            continue;
        }
        if ($valor === '__pdf_assinatura__') {
            $blocos[] = ['imagem' => true];
            continue;
        }
        if (is_int($rotulo)) {
            $blocos[] = pdf_simples_latin1($valor);
        } else {
            $blocos[] = pdf_simples_latin1(trim((string) $rotulo) . ': ' . $valor);
        }
    }
    $blocos[] = '';
    $fecho = $rodape ?? 'Documento do motorista — apenas rotas, sem dados financeiros.';
    if (trim($fecho) !== '') {
        $blocos[] = pdf_simples_latin1($fecho);
    }

    $pageW = 595.28;
    $pageH = 841.89;
    $margin = 48.0;
    $fontSize = 11;
    $leading = 15.0;
    $x = $margin;
    $maxChars = 88;

    $paginasStream = [];
    $buffer = '';
    $y = $pageH - $margin - $fontSize;

    $flush = static function () use (&$paginasStream, &$buffer, &$y, $pageH, $margin, $fontSize): void {
        if ($buffer !== '') {
            $paginasStream[] = $buffer;
        }
        $buffer = '';
        $y = $pageH - $margin - $fontSize;
    };

    $desenharImagem = static function () use (&$buffer, &$y, $flush, $margin, $x, $temImagem, $imagemW, $imagemH): void {
        if (!$temImagem) {
            return;
        }
        $escala = min(210.0 / $imagemW, 62.0 / $imagemH);
        if ($escala <= 0) {
            return;
        }
        $iw = $imagemW * $escala;
        $ih = $imagemH * $escala;
        if ($y - $ih < $margin) {
            $flush();
        }
        $base = $y - $ih;
        $buffer .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $iw, $ih, $x, $base);
        $y = $base - 14;
    };

    foreach ($blocos as $linha) {
        if (is_array($linha)) {
            $desenharImagem();
            continue;
        }
        $partes = preg_split("/\n/", $linha);
        if ($partes === false || $partes === []) {
            $partes = [''];
        }
        foreach ($partes as $parte) {
            foreach (pdf_simples_quebrar($parte, $maxChars) as $pedaco) {
                if ($y < $margin) {
                    $flush();
                }
                $esc = pdf_simples_esc($pedaco);
                $buffer .= sprintf("BT /F1 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $fontSize, $x, $y, $esc);
                $y -= $leading;
            }
        }
    }
    $flush();
    if ($paginasStream === []) {
        $paginasStream[] = sprintf(
            "BT /F1 %d Tf 1 0 0 1 %.2F %.2F Tm (Reserva) Tj ET\n",
            $fontSize,
            $x,
            $pageH - $margin - $fontSize
        );
    }

    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $fontId = 3;
    $nextId = 4;
    $imgId = 0;
    if ($temImagem) {
        $imgId = $nextId++;
        $fluxo = $imagemJpeg . "\n";
        $objs[$imgId] = '<< /Type /XObject /Subtype /Image /Width ' . $imagemW
            . ' /Height ' . $imagemH
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($fluxo)
            . " >>\nstream\n" . $fluxo . 'endstream';
    }
    $pageRefs = [];
    $contentIds = [];

    foreach ($paginasStream as $stream) {
        $contentId = $nextId++;
        $contentIds[] = $contentId;
        $objs[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        $pageId = $nextId++;
        $pageRefs[] = $pageId . ' 0 R';
        $recursos = '/Font << /F1 ' . $fontId . ' 0 R >>';
        if ($imgId > 0) {
            $recursos .= ' /XObject << /Im1 ' . $imgId . ' 0 R >>';
        }
        $objs[$pageId] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents %d 0 R /Resources << %s >> >>',
            $pageW,
            $pageH,
            $contentId,
            $recursos
        );
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>';
    $objs[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    ksort($objs);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $maxId = (int) max(array_keys($objs));
    $pdf .= 'xref' . "\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

function pdf_simples_latin1(string $s): string
{
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[ \t]+/u', ' ', $s) ?? $s;
    $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
    if ($conv === false) {
        return preg_replace('/[^\x20-\x7E]/', '?', $s) ?? $s;
    }
    return $conv;
}

function pdf_simples_esc(string $s): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function pdf_simples_quebrar(string $texto, int $max): array
{
    $texto = rtrim($texto);
    if ($texto === '') {
        return [''];
    }
    if (strlen($texto) <= $max) {
        return [$texto];
    }
    $out = [];
    while ($texto !== '') {
        if (strlen($texto) <= $max) {
            $out[] = $texto;
            break;
        }
        $fatia = substr($texto, 0, $max + 1);
        $corte = strrpos($fatia, ' ');
        if ($corte === false || $corte < 12) {
            $corte = $max;
        }
        $out[] = rtrim(substr($texto, 0, $corte));
        $texto = ltrim(substr($texto, $corte));
    }
    return $out !== [] ? $out : [''];
}
