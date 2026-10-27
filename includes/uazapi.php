<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function uazapi_curl_apply_ssl($ch): void
{
    $verify = env_valor('UAZAPI_SSL_VERIFY', '1') !== '0';
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
}

function uazapi_settings_ready(array $settings): bool
{
    $base = rtrim(trim((string) ($settings['uazapi_url'] ?? '')), '/');
    $token = trim((string) ($settings['uazapi_token'] ?? ''));
    return preg_match('#^https://#i', $base) === 1 && $token !== '';
}

function uazapi_admin_token(array $settings): string
{
    $token = trim((string) ($settings['uazapi_admin_token'] ?? ''));
    if ($token === '') {
        $token = trim(env_valor('UAZAPI_ADMIN_TOKEN', ''));
    }
    return $token;
}

function uazapi_create_endpoint(array $settings): string
{
    $url = trim((string) ($settings['uazapi_create_url'] ?? ''));
    if ($url === '') {
        $url = trim(env_valor(
            'UAZAPI_CREATE_INSTANCE_URL',
            'https://grlwciflaotripbumhve.supabase.co/functions/v1/create-instance-url'
        ));
    }
    return $url;
}

function uazapi_admin_ready(array $settings): bool
{
    return uazapi_admin_token($settings) !== '' && uazapi_create_endpoint($settings) !== '';
}

function uazapi_http_json_url(string $url, string $method, array $headers, ?array $body, int $timeout = 30): array
{
    if (!function_exists('curl_init')) {
        return [false, 0, 'O PHP deste computador não tem cURL.', null];
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    uazapi_curl_apply_ssl($ch);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return [false, $code, $err !== '' ? $err : 'Sem resposta do serviço de instâncias.', null];
    }
    $json = json_decode((string) $raw, true);
    if ($code >= 200 && $code < 300) {
        return [true, $code, 'OK', is_array($json) ? $json : []];
    }
    $msg = uazapi_error_text(is_array($json) ? $json : null, $code);
    return [false, $code, $msg, is_array($json) ? $json : null];
}

function uazapi_parse_provision_response(?array $json): array
{
    $out = ['url' => '', 'token' => '', 'name' => ''];
    if ($json === null) {
        return $out;
    }
    if (isset($json['success']) && $json['success'] === false) {
        return $out;
    }
    foreach (['server_url', 'serverUrl', 'base_url', 'baseUrl'] as $key) {
        $val = trim((string) ($json[$key] ?? ''));
        if (preg_match('#^https://#i', $val)) {
            $out['url'] = rtrim($val, '/');
            break;
        }
    }
    foreach (['instance_token', 'instanceToken', 'Instance Token', 'Instance token'] as $key) {
        $val = trim((string) ($json[$key] ?? ''));
        if (strlen($val) > 20) {
            $out['token'] = $val;
            break;
        }
    }
    $flat = trim((string) ($json['token'] ?? ''));
    if ($out['token'] === '' && strlen($flat) > 40 && (str_contains($flat, '.') || strlen($flat) > 80)) {
        $out['token'] = $flat;
    }
    if ($out['token'] === '') {
        foreach ($json as $key => $val) {
            if (!is_string($key) || !is_string($val)) {
                continue;
            }
            if (preg_match('/instance.*token/i', $key) && strlen(trim($val)) > 20) {
                $out['token'] = trim($val);
                break;
            }
        }
    }
    $instance = $json['instance'] ?? null;
    if (is_array($instance)) {
        $nested = trim((string) ($instance['token'] ?? ''));
        if ($out['token'] === '' && $nested !== '') {
            $out['token'] = $nested;
        }
        $out['name'] = trim((string) ($instance['name'] ?? $out['name']));
    }
    if ($out['name'] === '') {
        $out['name'] = trim((string) ($json['name'] ?? ''));
    }
    return $out;
}

function uazapi_clear_instance_local(array &$settings): void
{
    $settings['uazapi_url'] = '';
    $settings['uazapi_token'] = '';
    $settings['uazapi_instance_name'] = '';
    $settings['wa_status'] = 'desconectado';
    $settings['wa_instance_status'] = 'disconnected';
    $settings['wa_qr'] = '';
    $settings['wa_paircode'] = '';
    $settings['wa_request_id'] = '';
    $settings['wa_profile_name'] = '';
    $settings['wa_erro'] = '';
}

function uazapi_delete_instance(array $settings): ?string
{
    if (!uazapi_settings_ready($settings)) {
        return null;
    }
    [$ok, $code, $msg] = uazapi_request('DELETE', '/instance', $settings, null);
    if ($ok || $code === 404 || $code === 202) {
        return null;
    }
    return $msg;
}

function uazapi_provision_instance(array &$settings): array
{
    $admin = uazapi_admin_token($settings);
    if ($admin === '') {
        return [false, 'Configure o token permanente da API em Configurações ou UAZAPI_ADMIN_TOKEN no servidor.'];
    }
    $endpoint = uazapi_create_endpoint($settings);
    if ($endpoint === '' || !preg_match('#^https://#i', $endpoint)) {
        return [false, 'URL de criação de instância inválida.'];
    }
    $slug = 'transfer-fln-' . gmdate('Ymd-His');
    $body = [
        'token' => $admin,
        'name' => $slug,
        'deviceName' => 'Transfer FLN',
    ];
    [$ok, , $msg, $json] = uazapi_http_json_url(
        $endpoint,
        'POST',
        ['Content-Type: application/json', 'Accept: application/json'],
        $body
    );
    if (!$ok) {
        return [false, $msg];
    }
    $parsed = uazapi_parse_provision_response($json);
    if ($parsed['url'] === '' || $parsed['token'] === '') {
        return [false, 'A API não devolveu server_url e token da instância. Confira o token permanente.'];
    }
    $settings['uazapi_url'] = $parsed['url'];
    $settings['uazapi_token'] = $parsed['token'];
    $settings['uazapi_instance_name'] = $parsed['name'] !== '' ? $parsed['name'] : $slug;
    return [true, 'Instância criada (' . $settings['uazapi_instance_name'] . ').'];
}

function uazapi_begin_whatsapp_qr(array &$settings): array
{
    if (uazapi_settings_ready($settings)) {
        uazapi_delete_instance($settings);
    }
    [$ok, $msg] = uazapi_provision_instance($settings);
    if (!$ok) {
        return [false, $msg, null];
    }
    return uazapi_connect_qr($settings);
}

function uazapi_request(string $method, string $path, array $settings, ?array $body = null, int $timeout = 25): array
{
    $base = rtrim(trim((string) ($settings['uazapi_url'] ?? '')), '/');
    $token = trim((string) ($settings['uazapi_token'] ?? ''));
    if (!preg_match('#^https://#i', $base) || $token === '') {
        return [false, 0, 'Informe a URL e o token da instância em Configurações → Sistema.', null];
    }
    if (!function_exists('curl_init')) {
        return [false, 0, 'O PHP deste computador não tem cURL.', null];
    }
    $ch = curl_init($base . $path);
    $headers = ['Accept: application/json', 'token: ' . $token];
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    uazapi_curl_apply_ssl($ch);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return [false, $code, $err !== '' ? $err : 'Sem resposta da UAZAPI.', null];
    }
    $json = json_decode((string) $raw, true);
    if ($code >= 200 && $code < 300) {
        return [true, $code, 'OK', is_array($json) ? $json : []];
    }
    $msg = uazapi_error_text(is_array($json) ? $json : null, $code);
    return [false, $code, $msg, is_array($json) ? $json : null];
}

function uazapi_error_text(?array $json, int $code): string
{
    if ($json !== null) {
        foreach (['error', 'message', 'response', 'info'] as $key) {
            $val = $json[$key] ?? null;
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }
    }
    return match ($code) {
        401 => 'Token da instância inválido ou expirado. Confira em Configurações.',
        404 => 'Instância não encontrada na UAZAPI.',
        409 => 'Já existe uma conexão em andamento. Use Atualizar status.',
        429 => 'Limite de requisições atingido. Aguarde e tente de novo.',
        503 => 'UAZAPI temporariamente indisponível. Tente em alguns segundos.',
        default => 'UAZAPI respondeu HTTP ' . $code . '.',
    };
}

function uazapi_normalize_qr(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (str_starts_with($raw, 'data:image')) {
        return $raw;
    }
    return 'data:image/png;base64,' . preg_replace('/\s+/', '', $raw);
}

function uazapi_find_qr_image(mixed $data, string $key = ''): string
{
    if (is_string($data)) {
        if (str_starts_with($data, 'data:image')) {
            return $data;
        }
        if (preg_match('/qr/i', $key) && strlen($data) > 40) {
            return uazapi_normalize_qr($data);
        }
        return '';
    }
    if (!is_array($data)) {
        return '';
    }
    foreach ($data as $k => $value) {
        $found = uazapi_find_qr_image($value, is_string($k) ? $k : $key);
        if ($found !== '') {
            return $found;
        }
    }
    return '';
}

function uazapi_extract_state(?array $json): array
{
    $state = [
        'connected' => false,
        'logged_in' => false,
        'instance_status' => 'disconnected',
        'qrcode' => '',
        'paircode' => '',
        'profile_name' => '',
        'request_id' => '',
    ];
    if ($json === null) {
        return $state;
    }
    if (isset($json['request_id']) && is_string($json['request_id'])) {
        $state['request_id'] = trim($json['request_id']);
    }
    if (array_key_exists('connected', $json)) {
        $state['connected'] = !empty($json['connected']);
    }
    if (array_key_exists('loggedIn', $json)) {
        $state['logged_in'] = !empty($json['loggedIn']);
    }
    $statusObj = $json['status'] ?? null;
    if (is_array($statusObj)) {
        if (array_key_exists('connected', $statusObj)) {
            $state['connected'] = !empty($statusObj['connected']);
        }
        if (array_key_exists('loggedIn', $statusObj)) {
            $state['logged_in'] = !empty($statusObj['loggedIn']);
        }
    }
    $instance = $json['instance'] ?? null;
    if (is_array($instance)) {
        $ist = strtolower(trim((string) ($instance['status'] ?? '')));
        if ($ist !== '') {
            $state['instance_status'] = $ist;
        }
        if ($ist === 'connected') {
            $state['connected'] = true;
        }
        $state['profile_name'] = trim((string) ($instance['profileName'] ?? ''));
        $qr = uazapi_normalize_qr((string) ($instance['qrcode'] ?? ''));
        if ($qr !== '') {
            $state['qrcode'] = $qr;
        }
        $state['paircode'] = trim((string) ($instance['paircode'] ?? ''));
    }
    if ($state['qrcode'] === '') {
        $state['qrcode'] = uazapi_find_qr_image($json);
    }
    if ($state['connected'] || $state['logged_in']) {
        $state['connected'] = true;
    }
    return $state;
}

function uazapi_map_ui_status(array $state): string
{
    if ($state['connected'] || $state['instance_status'] === 'connected') {
        return 'conectado';
    }
    if ($state['instance_status'] === 'connecting' || $state['qrcode'] !== '' || $state['paircode'] !== '') {
        return 'aguardando';
    }
    if ($state['instance_status'] === 'hibernated') {
        return 'hibernado';
    }
    return 'desconectado';
}

function uazapi_apply_whatsapp_state(array &$settings, array $state): void
{
    $settings['wa_status'] = uazapi_map_ui_status($state);
    $settings['wa_erro'] = '';
    $settings['wa_instance_status'] = $state['instance_status'];
    if ($state['connected']) {
        $settings['wa_qr'] = '';
        $settings['wa_paircode'] = '';
    } else {
        if ($state['qrcode'] !== '') {
            $settings['wa_qr'] = $state['qrcode'];
        }
        if ($state['paircode'] !== '') {
            $settings['wa_paircode'] = $state['paircode'];
        }
    }
    if ($state['request_id'] !== '') {
        $settings['wa_request_id'] = $state['request_id'];
    }
    if ($state['profile_name'] !== '') {
        $settings['wa_profile_name'] = $state['profile_name'];
    }
}

function uazapi_status_label(string $uiStatus, string $instanceStatus = ''): string
{
    if ($uiStatus === 'conectado') {
        return 'Conectado';
    }
    if ($uiStatus === 'aguardando' || $instanceStatus === 'connecting') {
        return 'Aguardando leitura do QR Code';
    }
    if ($uiStatus === 'hibernado' || $instanceStatus === 'hibernated') {
        return 'Sessão pausada (hibernada)';
    }
    return 'Desconectado';
}

function uazapi_refresh_status(array $settings): array
{
    [$ok, $code, $msg, $json] = uazapi_request('GET', '/instance/status', $settings, null);
    if (!$ok) {
        return [false, $msg, null];
    }
    $state = uazapi_extract_state($json);
    return [true, $state['connected'] ? 'WhatsApp conectado.' : 'Status atualizado.', $state];
}

function uazapi_connect_qr(array $settings): array
{
    $body = [
        'browser' => 'auto',
        'systemName' => 'Transfer FLN',
    ];
    [$ok, $code, $msg, $json] = uazapi_request('POST', '/instance/connect', $settings, $body);
    if ($code === 409) {
        return uazapi_refresh_status($settings);
    }
    if (!$ok) {
        return [false, $msg, null];
    }
    $state = uazapi_extract_state($json);
    if ($state['qrcode'] !== '') {
        return [true, 'QR Code gerado. No celular: WhatsApp → Aparelhos conectados → Conectar aparelho.', $state];
    }
    if ($state['connected']) {
        return [true, 'WhatsApp já está conectado nesta instância.', $state];
    }
    if ($state['instance_status'] === 'connecting') {
        return [true, 'Conexão iniciada. Aguarde ou use Atualizar status para buscar o QR Code.', $state];
    }
    return [true, 'Solicitação enviada à UAZAPI. Use Atualizar status.', $state];
}

/** @deprecated use uazapi_request */
function uazapi_json(string $method, string $path, array $settings, ?array $body = null): array
{
    [$ok, , $msg, $json] = uazapi_request($method, $path, $settings, $body);
    return [$ok, $msg, $json];
}
