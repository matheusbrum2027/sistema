<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function totp_base32_alfabeto(): string
{
    return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
}

function totp_base32_encode(string $bin): string
{
    $alfa = totp_base32_alfabeto();
    $bits = '';
    $len = strlen($bin);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $saida = '';
    foreach (str_split($bits, 5) as $pedaco) {
        if (strlen($pedaco) < 5) {
            $pedaco = str_pad($pedaco, 5, '0', STR_PAD_RIGHT);
        }
        $saida .= $alfa[bindec($pedaco)];
    }
    return $saida;
}

function totp_base32_decode(string $b32): string
{
    $alfa = totp_base32_alfabeto();
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
    $bits = '';
    $len = strlen($b32);
    for ($i = 0; $i < $len; $i++) {
        $pos = strpos($alfa, $b32[$i]);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $saida = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $saida .= chr(bindec($byte));
        }
    }
    return $saida;
}

function totp_secret_novo(): string
{
    return totp_base32_encode(random_bytes(20));
}

function totp_secret_valido(string $secret): bool
{
    return preg_match('/^[A-Z2-7]{16,}$/', strtoupper(preg_replace('/\s+/', '', $secret) ?? '')) === 1;
}

function totp_secret_limpo(string $secret): string
{
    return strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
}

/** Chave legível em blocos de 4. */
function totp_secret_exibir(string $secret): string
{
    $limpo = totp_secret_limpo($secret);
    return trim(chunk_split($limpo, 4, ' '));
}

function totp_codigo(string $secret, ?int $tempo = null, int $digitos = 6, int $periodo = 30): string
{
    $tempo ??= time();
    $contador = intdiv($tempo, $periodo);
    $chave = totp_base32_decode($secret);
    if ($chave === '') {
        return '';
    }
    $msg = pack('N*', 0, $contador);
    $hash = hash_hmac('sha1', $msg, $chave, true);
    $offset = ord($hash[19]) & 0x0f;
    $valor = (
        ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff)
    );
    $mod = 10 ** $digitos;
    return str_pad((string) ($valor % $mod), $digitos, '0', STR_PAD_LEFT);
}

function totp_confere(string $secret, string $codigo, int $janela = 2): bool
{
    $codigo = preg_replace('/\D+/', '', $codigo) ?? '';
    $secret = totp_secret_limpo($secret);
    if (!preg_match('/^\d{6}$/', $codigo) || !totp_secret_valido($secret)) {
        return false;
    }
    $agora = time();
    for ($i = -$janela; $i <= $janela; $i++) {
        $esperado = totp_codigo($secret, $agora + ($i * 30));
        if ($esperado !== '' && hash_equals($esperado, $codigo)) {
            return true;
        }
    }
    return false;
}

function totp_uri(string $secret, string $conta, string $emissor = 'FLN Transfer'): string
{
    $secret = totp_secret_limpo($secret);
    $label = rawurlencode($emissor . ':' . $conta);
    $q = http_build_query([
        'secret' => $secret,
        'issuer' => $emissor,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ], '', '&', PHP_QUERY_RFC3986);
    return 'otpauth://totp/' . $label . '?' . $q;
}

function totp_qr_url(string $otpauthUri, int $tamanho = 220): string
{
    $tamanho = max(160, min(320, $tamanho));
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $tamanho . 'x' . $tamanho . '&ecc=M&margin=8&data=' . rawurlencode($otpauthUri);
}

/**
 * Estados:
 * - off: desligada
 * - setup: chave gerada, ainda não confirmada (login NÃO exige 2FA)
 * - on: confirmada e ativa no login do admin
 */
function totp_estado(array $db): string
{
    $secret = totp_secret_limpo((string) ($db['settings']['totp_secret'] ?? ''));
    if (!totp_secret_valido($secret)) {
        return 'off';
    }
    if (!empty($db['settings']['verificacao_2fa'])) {
        return 'on';
    }
    return 'setup';
}

function autenticacao_2fa_pronta(array $db): bool
{
    return totp_estado($db) === 'on';
}
