<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function captcha_alfabeto(): string
{
    return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789@#%&';
}

function captcha_normalizar(string $valor): string
{
    $valor = strtoupper(preg_replace('/\s+/', '', $valor) ?? '');
    return $valor;
}

function captcha_garantir(): void
{
    $atual = $_SESSION['captcha'] ?? null;
    $expira = is_array($atual) ? (int) ($atual['expira'] ?? 0) : 0;
    if (!is_array($atual) || $expira < time() || empty($atual['hash'])) {
        captcha_novo();
    }
}

function captcha_novo(): void
{
    $alfa = captcha_alfabeto();
    $texto = '';
    $max = strlen($alfa) - 1;
    for ($i = 0; $i < 5; $i++) {
        $texto .= $alfa[random_int(0, $max)];
    }
    $_SESSION['captcha'] = [
        'hash' => password_hash(captcha_normalizar($texto), PASSWORD_DEFAULT),
        'audio' => $texto,
        'expira' => time() + 180,
        'usos' => 0,
        'ver' => ((int) ($_SESSION['captcha']['ver'] ?? 0)) + 1,
    ];
}

function captcha_confere(string $digitado): bool
{
    captcha_garantir();
    $cap = $_SESSION['captcha'];
    if ((int) $cap['expira'] < time() || (int) ($cap['usos'] ?? 0) > 0) {
        captcha_novo();
        return false;
    }
    $_SESSION['captcha']['usos'] = 1;
    $ok = password_verify(captcha_normalizar($digitado), (string) $cap['hash']);
    captcha_novo();
    return $ok;
}

function login_bloqueado(): bool
{
    return (int) ($_SESSION['login_bloqueio'] ?? 0) > time();
}

function login_falhou(): void
{
    $n = (int) ($_SESSION['login_falhas'] ?? 0) + 1;
    $_SESSION['login_falhas'] = $n;
    if ($n >= 5) {
        $_SESSION['login_bloqueio'] = time() + 900;
        $_SESSION['login_falhas'] = 0;
    }
}

function login_liberar(): void
{
    unset($_SESSION['login_falhas'], $_SESSION['login_bloqueio']);
}

function captcha_fonte(): array
{
    return [
        'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
        'B' => ['11110', '10001', '10001', '11110', '10001', '10001', '11110'],
        'C' => ['01111', '10000', '10000', '10000', '10000', '10000', '01111'],
        'D' => ['11110', '10001', '10001', '10001', '10001', '10001', '11110'],
        'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
        'F' => ['11111', '10000', '10000', '11110', '10000', '10000', '10000'],
        'G' => ['01111', '10000', '10000', '10111', '10001', '10001', '01111'],
        'H' => ['10001', '10001', '10001', '11111', '10001', '10001', '10001'],
        'J' => ['00111', '00010', '00010', '00010', '00010', '10010', '01100'],
        'K' => ['10001', '10010', '10100', '11000', '10100', '10010', '10001'],
        'L' => ['10000', '10000', '10000', '10000', '10000', '10000', '11111'],
        'M' => ['10001', '11011', '10101', '10101', '10001', '10001', '10001'],
        'N' => ['10001', '11001', '10101', '10011', '10001', '10001', '10001'],
        'P' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
        'Q' => ['01110', '10001', '10001', '10001', '10101', '10010', '01101'],
        'R' => ['11110', '10001', '10001', '11110', '10100', '10010', '10001'],
        'S' => ['01111', '10000', '10000', '01110', '00001', '00001', '11110'],
        'T' => ['11111', '00100', '00100', '00100', '00100', '00100', '00100'],
        'U' => ['10001', '10001', '10001', '10001', '10001', '10001', '01110'],
        'V' => ['10001', '10001', '10001', '10001', '10001', '01010', '00100'],
        'W' => ['10001', '10001', '10001', '10101', '10101', '10101', '01010'],
        'X' => ['10001', '10001', '01010', '00100', '01010', '10001', '10001'],
        'Y' => ['10001', '10001', '01010', '00100', '00100', '00100', '00100'],
        'Z' => ['11111', '00001', '00010', '00100', '01000', '10000', '11111'],
        '2' => ['01110', '10001', '00001', '00010', '00100', '01000', '11111'],
        '3' => ['11110', '00001', '00001', '01110', '00001', '00001', '11110'],
        '4' => ['00010', '00110', '01010', '10010', '11111', '00010', '00010'],
        '5' => ['11111', '10000', '10000', '11110', '00001', '00001', '11110'],
        '6' => ['01110', '10000', '10000', '11110', '10001', '10001', '01110'],
        '7' => ['11111', '00001', '00010', '00100', '01000', '01000', '01000'],
        '8' => ['01110', '10001', '10001', '01110', '10001', '10001', '01110'],
        '9' => ['01110', '10001', '10001', '01111', '00001', '00001', '01110'],
        '@' => ['01110', '10001', '10111', '10101', '10111', '10000', '01110'],
        '#' => ['01010', '01010', '11111', '01010', '11111', '01010', '01010'],
        '%' => ['11001', '11010', '00010', '00100', '01000', '01011', '10011'],
        '&' => ['01100', '10010', '10100', '01000', '10101', '10010', '01101'],
    ];
}

function captcha_png(): void
{
    captcha_garantir();
    $texto = (string) ($_SESSION['captcha']['audio'] ?? '');
    $w = 228;
    $h = 76;
    $px = array_fill(0, $h, array_fill(0, $w, [244, 241, 236]));
    for ($n = 0; $n < 700; $n++) {
        $px[random_int(0, $h - 1)][random_int(0, $w - 1)] = [210, 196, 184];
    }
    $fonte = captcha_fonte();
    $x = 14;
    $chars = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($chars as $ch) {
        $gly = $fonte[$ch] ?? $fonte['#'];
        $dy = random_int(0, 8);
        $cor = [180, 62, 12];
        for ($gy = 0; $gy < 7; $gy++) {
            for ($gx = 0; $gx < 5; $gx++) {
                if (($gly[$gy][$gx] ?? '0') !== '1') {
                    continue;
                }
                for ($sy = 0; $sy < 6; $sy++) {
                    for ($sx = 0; $sx < 6; $sx++) {
                        $py = 12 + $dy + ($gy * 6) + $sy;
                        $pxi = $x + ($gx * 6) + $sx;
                        if ($py >= 0 && $py < $h && $pxi >= 0 && $pxi < $w) {
                            $px[$py][$pxi] = $cor;
                        }
                    }
                }
            }
        }
        $x += 40;
    }
    $raw = '';
    foreach ($px as $linha) {
        $raw .= "\x00";
        foreach ($linha as $rgb) {
            $raw .= chr($rgb[0]) . chr($rgb[1]) . chr($rgb[2]);
        }
    }
    $ihdr = pack('N', $w) . pack('N', $h) . "\x08\x02\x00\x00\x00";
    $png = "\x89PNG\r\n\x1a\n" . captcha_bloco('IHDR', $ihdr) . captcha_bloco('IDAT', gzcompress($raw, 6)) . captcha_bloco('IEND', '');
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $png;
    exit;
}

function captcha_bloco(string $tipo, string $dados): string
{
    return pack('N', strlen($dados)) . $tipo . $dados . pack('N', crc32($tipo . $dados));
}

function captcha_wav(): void
{
    captcha_garantir();
    $texto = (string) ($_SESSION['captcha']['audio'] ?? '');
    $rate = 8000;
    $pcm = '';
    $silencio = str_repeat("\x80", (int) ($rate * 0.12));
    $chars = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($chars as $i => $ch) {
        $freq = 280 + ((ord($ch) * 17) % 900);
        $dur = (int) ($rate * 0.18);
        for ($n = 0; $n < $dur; $n++) {
            $pcm .= chr((int) (128 + 70 * sin(2 * M_PI * $freq * $n / $rate)));
        }
        $pcm .= $silencio;
        unset($i);
    }
    $tam = strlen($pcm);
    $header = 'RIFF' . pack('V', 36 + $tam) . 'WAVE' . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) . pack('V', $rate) . pack('V', $rate) . pack('v', 1) . pack('v', 8) . 'data' . pack('V', $tam);
    header('Content-Type: audio/wav');
    header('Cache-Control: no-store');
    echo $header . $pcm;
    exit;
}
