<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function supabase_configurado(): bool
{
    return str_starts_with(env_valor('SUPABASE_URL'), 'https://') && env_valor('SUPABASE_ANON_KEY') !== '';
}

function supabase_pedido(string $caminho, string $metodo, ?array $corpo, array $headers = []): array
{
    if (!function_exists('curl_init') || !supabase_configurado()) {
        return [false, 0, ''];
    }
    $url = rtrim(env_valor('SUPABASE_URL'), '/') . $caminho;
    $ch = curl_init($url);
    $base = [
        'apikey: ' . env_valor('SUPABASE_ANON_KEY'),
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => array_merge($base, $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        return [false, $code, ''];
    }
    return [true, $code, (string) $body];
}

function supabase_entrar(string $email, string $senha): bool
{
    [$ok, $code, $body] = supabase_pedido('/auth/v1/token?grant_type=password', 'POST', [
        'email' => $email,
        'password' => $senha,
    ]);
    if (!$ok || $code < 200 || $code >= 300) {
        return false;
    }
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['access_token'])) {
        return false;
    }
    $meta = $json['user']['app_metadata'] ?? [];
    $papel = (string) ($meta['papel'] ?? 'motorista');
    if (!in_array($papel, ['admin', 'motorista'], true)) {
        $papel = 'motorista';
    }
    $_SESSION['auth'] = true;
    $_SESSION['role'] = $papel;
    $_SESSION['motorista_id'] = (int) ($meta['motorista_id'] ?? 0);
    $_SESSION['supabase_user'] = (string) ($json['user']['id'] ?? '');
    $_SESSION['supabase_access'] = (string) $json['access_token'];
    $_SESSION['supabase_refresh'] = (string) ($json['refresh_token'] ?? '');
    return true;
}

function supabase_recuperar(string $email): void
{
    $base = rtrim(env_valor('APP_URL', ''), '/');
    $redirect = $base !== '' ? $base . '/index.php?p=entrar' : '';
    $corpo = ['email' => $email];
    if ($redirect !== '') {
        $corpo['redirect_to'] = $redirect;
    }
    supabase_pedido('/auth/v1/recover', 'POST', $corpo);
}

function supabase_encerrar(): void
{
    $token = (string) ($_SESSION['supabase_access'] ?? '');
    if ($token !== '' && supabase_configurado()) {
        supabase_pedido('/auth/v1/logout', 'POST', null, ['Authorization: Bearer ' . $token]);
    }
    unset($_SESSION['supabase_user'], $_SESSION['supabase_access'], $_SESSION['supabase_refresh']);
}
