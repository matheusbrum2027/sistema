<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * QR Code byte mode, ECC M, versões 5 e 6. Devolve a matriz (0 branco, 1 preto).
 */
function qr_matriz(string $texto): array
{
    $bytes = array_values(unpack('C*', $texto) ?: []);
    $n = count($bytes);
    if ($n <= 84) {
        $versao = 5;
        $blocos = 2;
        $dadosBloco = 43;
        $eccBloco = 24;
    } elseif ($n <= 106) {
        $versao = 6;
        $blocos = 4;
        $dadosBloco = 27;
        $eccBloco = 16;
    } else {
        $bytes = array_slice($bytes, 0, 106);
        $versao = 6;
        $blocos = 4;
        $dadosBloco = 27;
        $eccBloco = 16;
        $n = 106;
    }

    $bits = array_merge([0, 1, 0, 0], qr_bits($n, 8));
    foreach ($bytes as $b) {
        $bits = array_merge($bits, qr_bits($b, 8));
    }
    $capacidade = $blocos * $dadosBloco * 8;
    $faltam = $capacidade - count($bits);
    for ($i = 0; $i < min(4, $faltam); $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }
    $pads = [0xEC, 0x11];
    $p = 0;
    while (count($bits) < $capacidade) {
        $bits = array_merge($bits, qr_bits($pads[$p % 2], 8));
        $p++;
    }
    $dados = [];
    for ($i = 0; $i < $blocos * $dadosBloco; $i++) {
        $dados[] = qr_byte($bits, $i * 8);
    }

    [$exp, $log] = qr_gf();
    $gerador = qr_gerador($eccBloco, $exp, $log);
    $dataBlocks = [];
    $eccBlocks = [];
    for ($b = 0; $b < $blocos; $b++) {
        $fatia = array_slice($dados, $b * $dadosBloco, $dadosBloco);
        $dataBlocks[] = $fatia;
        $eccBlocks[] = qr_rs($fatia, $eccBloco, $gerador, $exp, $log);
    }
    $fluxo = [];
    for ($i = 0; $i < $dadosBloco; $i++) {
        for ($b = 0; $b < $blocos; $b++) {
            $fluxo[] = $dataBlocks[$b][$i];
        }
    }
    for ($i = 0; $i < $eccBloco; $i++) {
        for ($b = 0; $b < $blocos; $b++) {
            $fluxo[] = $eccBlocks[$b][$i];
        }
    }
    $bitFluxo = [];
    foreach ($fluxo as $byte) {
        $bitFluxo = array_merge($bitFluxo, qr_bits($byte, 8));
    }
    $resto = $versao === 5 || $versao === 6 ? 7 : 0;
    for ($i = 0; $i < $resto; $i++) {
        $bitFluxo[] = 0;
    }

    $tamanho = 17 + 4 * $versao;
    $mod = array_fill(0, $tamanho, array_fill(0, $tamanho, -1));
    qr_fixos($mod, $versao, $tamanho);
    $melhor = null;
    $melhorNota = PHP_INT_MAX;
    for ($mascara = 0; $mascara < 8; $mascara++) {
        $copia = $mod;
        qr_formato($copia, $tamanho, $mascara);
        qr_dados($copia, $bitFluxo, $tamanho, $mascara);
        $nota = qr_penalidade($copia, $tamanho);
        if ($nota < $melhorNota) {
            $melhorNota = $nota;
            $melhor = $copia;
        }
    }
    return $melhor ?? $mod;
}

/** Bytes cinza 8 bits (0 escuro, 255 claro), com margem quieta. Sem perda, para embutir no PDF. */
function qr_raw(string $texto, int $escala = 4): array
{
    $matriz = qr_matriz($texto);
    $n = count($matriz);
    $quiet = 4;
    $lado = ($n + $quiet * 2) * $escala;
    $raw = '';
    for ($y = 0; $y < $lado; $y++) {
        $my = intdiv($y, $escala) - $quiet;
        for ($x = 0; $x < $lado; $x++) {
            $mx = intdiv($x, $escala) - $quiet;
            $on = $my >= 0 && $mx >= 0 && $my < $n && $mx < $n && ($matriz[$my][$mx] ?? 0) === 1;
            $raw .= $on ? "\x11" : "\xFF";
        }
    }
    return [$raw, $lado];
}

/** PNG preto e branco, com margem quieta. */
function qr_png(string $texto, int $escala = 6): string
{
    $matriz = qr_matriz($texto);
    $n = count($matriz);
    $quiet = 4;
    $lado = ($n + $quiet * 2) * $escala;
    $img = imagecreatetruecolor($lado, $lado);
    if ($img === false) {
        return '';
    }
    $branco = imagecolorallocate($img, 255, 255, 255);
    $preto = imagecolorallocate($img, 17, 24, 39);
    imagefilledrectangle($img, 0, 0, $lado, $lado, $branco);
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if (($matriz[$y][$x] ?? 0) !== 1) {
                continue;
            }
            $x0 = ($x + $quiet) * $escala;
            $y0 = ($y + $quiet) * $escala;
            imagefilledrectangle($img, $x0, $y0, $x0 + $escala - 1, $y0 + $escala - 1, $preto);
        }
    }
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    imagedestroy($img);
    return $png;
}

function qr_bits(int $valor, int $n): array
{
    $out = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $out[] = ($valor >> $i) & 1;
    }
    return $out;
}

function qr_byte(array $bits, int $i): int
{
    $v = 0;
    for ($b = 0; $b < 8; $b++) {
        $v = ($v << 1) | (int) ($bits[$i + $b] ?? 0);
    }
    return $v & 255;
}

function qr_gf(): array
{
    $exp = [];
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) {
            $x ^= 0x11d;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }
    return [$exp, $log];
}

function qr_mul(int $a, int $b, array $exp, array $log): int
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    return $exp[$log[$a] + $log[$b]];
}

function qr_gerador(int $nsym, array $exp, array $log): array
{
    $g = [1];
    for ($i = 0; $i < $nsym; $i++) {
        $prox = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $coef) {
            $prox[$j] ^= $coef;
            $prox[$j + 1] ^= qr_mul($coef, $exp[$i], $exp, $log);
        }
        $g = $prox;
    }
    return $g;
}

function qr_rs(array $dados, int $nsym, array $gerador, array $exp, array $log): array
{
    $res = array_merge($dados, array_fill(0, $nsym, 0));
    $ng = count($gerador);
    foreach ($dados as $i => $_) {
        $coef = $res[$i];
        if ($coef === 0) {
            continue;
        }
        for ($j = 0; $j < $ng; $j++) {
            $res[$i + $j] ^= qr_mul($gerador[$j], $coef, $exp, $log);
        }
    }
    return array_slice($res, -$nsym);
}

function qr_fixos(array &$m, int $versao, int $tamanho): void
{
    $finder = [
        [1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 1, 1, 1, 1, 1, 1],
    ];
    $cantos = [[0, 0], [0, $tamanho - 7], [$tamanho - 7, 0]];
    foreach ($cantos as [$y, $x]) {
        for ($dy = 0; $dy < 7; $dy++) {
            for ($dx = 0; $dx < 7; $dx++) {
                $m[$y + $dy][$x + $dx] = $finder[$dy][$dx];
            }
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $m[7][$i] = 0;
        $m[$i][7] = 0;
        $m[7][$tamanho - 1 - $i] = 0;
        $m[$i][$tamanho - 8] = 0;
        $m[$tamanho - 8][$i] = 0;
        $m[$tamanho - 1 - $i][7] = 0;
    }
    for ($i = 0; $i < $tamanho; $i++) {
        $bit = $i % 2 === 0 ? 1 : 0;
        if ($m[6][$i] < 0) {
            $m[6][$i] = $bit;
        }
        if ($m[$i][6] < 0) {
            $m[$i][6] = $bit;
        }
    }
    $centros = $versao === 5 ? [6, 30] : [6, 34];
    foreach ($centros as $cy) {
        foreach ($centros as $cx) {
            if (($cy < 9 && $cx < 9) || ($cy < 9 && $cx > $tamanho - 10) || ($cx < 9 && $cy > $tamanho - 10)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $borda = max(abs($dx), abs($dy)) === 2 || ($dx === 0 && $dy === 0);
                    $m[$cy + $dy][$cx + $dx] = $borda ? 1 : 0;
                }
            }
        }
    }
    $m[4 * $versao + 9][8] = 1;
}

function qr_mascara_liga(int $mascara, int $y, int $x): bool
{
    return match ($mascara) {
        0 => ($y + $x) % 2 === 0,
        1 => $y % 2 === 0,
        2 => $x % 3 === 0,
        3 => ($y + $x) % 3 === 0,
        4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
        5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
        6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
        default => (($y + $x) % 2 + ($y * $x) % 3) % 2 === 0,
    };
}

function qr_dados(array &$m, array $bits, int $tamanho, int $mascara): void
{
    $i = 0;
    $total = count($bits);
    $dir = -1;
    $row = $tamanho - 1;
    for ($col = $tamanho - 1; $col > 0; $col -= 2) {
        $direita = $col <= 6 ? $col - 1 : $col;
        $par = [$direita, $direita - 1];
        while (true) {
            foreach ($par as $x) {
                if ($x < 0 || $m[$row][$x] >= 0) {
                    continue;
                }
                $bit = $i < $total ? $bits[$i] : 0;
                $i++;
                $escuro = $bit === 1;
                if (qr_mascara_liga($mascara, $row, $x)) {
                    $escuro = !$escuro;
                }
                $m[$row][$x] = $escuro ? 1 : 0;
            }
            $row += $dir;
            if ($row < 0 || $row >= $tamanho) {
                $row -= $dir;
                $dir = -$dir;
                break;
            }
        }
    }
}

function qr_formato(array &$m, int $tamanho, int $mascara): void
{
    $dado = $mascara & 7;
    $resto = $dado << 10;
    while (qr_bch_digitos($resto) - 11 >= 0) {
        $resto ^= 0b10100110111 << (qr_bch_digitos($resto) - 11);
    }
    $bits = (($dado << 10) | $resto) ^ 0b101010000010010;
    for ($i = 0; $i < 15; $i++) {
        $mod = (($bits >> $i) & 1) === 1 ? 1 : 0;
        if ($i < 6) {
            $m[$i][8] = $mod;
        } elseif ($i < 8) {
            $m[$i + 1][8] = $mod;
        } else {
            $m[$tamanho - 15 + $i][8] = $mod;
        }
    }
    for ($i = 0; $i < 15; $i++) {
        $mod = (($bits >> $i) & 1) === 1 ? 1 : 0;
        if ($i < 8) {
            $m[8][$tamanho - $i - 1] = $mod;
        } elseif ($i < 9) {
            $m[8][15 - $i] = $mod;
        } else {
            $m[8][15 - $i - 1] = $mod;
        }
    }
    $m[$tamanho - 8][8] = 1;
}

function qr_bch_digitos(int $data): int
{
    $n = 0;
    while ($data !== 0) {
        $n++;
        $data >>= 1;
    }
    return $n;
}

function qr_penalidade(array $m, int $tamanho): int
{
    $nota = 0;
    for ($y = 0; $y < $tamanho; $y++) {
        $run = 1;
        for ($x = 1; $x < $tamanho; $x++) {
            if ($m[$y][$x] === $m[$y][$x - 1]) {
                $run++;
                if ($run === 5) {
                    $nota += 3;
                } elseif ($run > 5) {
                    $nota++;
                }
            } else {
                $run = 1;
            }
        }
    }
    for ($x = 0; $x < $tamanho; $x++) {
        $run = 1;
        for ($y = 1; $y < $tamanho; $y++) {
            if ($m[$y][$x] === $m[$y - 1][$x]) {
                $run++;
                if ($run === 5) {
                    $nota += 3;
                } elseif ($run > 5) {
                    $nota++;
                }
            } else {
                $run = 1;
            }
        }
    }
    $escuros = 0;
    for ($y = 0; $y < $tamanho; $y++) {
        for ($x = 0; $x < $tamanho; $x++) {
            $escuros += $m[$y][$x] === 1 ? 1 : 0;
        }
    }
    $pct = (int) abs(($escuros * 100 / ($tamanho * $tamanho)) - 50);
    $nota += intdiv($pct, 5) * 10;
    return $nota;
}
