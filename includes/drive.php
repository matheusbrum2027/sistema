<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/** Escopos mínimos: criar/gerir só arquivos criados pelo app. */
function drive_scopes(): string
{
    return 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/userinfo.email';
}

function drive_oauth_configurado(): bool
{
    return trim(env_valor('GOOGLE_OAUTH_CLIENT_ID')) !== ''
        && trim(env_valor('GOOGLE_OAUTH_CLIENT_SECRET')) !== '';
}

function drive_redirect_uri(): string
{
    return rtrim(base_url(), '/') . '/index.php?drive=oauth';
}

function drive_conectado(array $db): bool
{
    $tok = trim((string) ($db['settings']['drive_refresh_token'] ?? ''));
    return $tok !== '' && !empty($db['settings']['drive_ativo']);
}

function drive_email_mascarado(array $db): string
{
    $email = trim((string) ($db['settings']['drive_email'] ?? ''));
    if ($email === '' || !str_contains($email, '@')) {
        return 'conta Google';
    }
    [$user, $dom] = explode('@', $email, 2);
    $ini = mb_substr($user, 0, 1);
    return $ini . '***@' . $dom;
}

function drive_status_texto(array $db): string
{
    if (!drive_oauth_configurado() && !drive_conectado($db)) {
        return 'Configure GOOGLE_OAUTH_CLIENT_ID e GOOGLE_OAUTH_CLIENT_SECRET no servidor para conectar com um clique.';
    }
    if (!drive_conectado($db)) {
        return 'Desconectado. Conecte uma vez; os backups diários passam a ir sozinhos para o Drive.';
    }
    $ultimo = trim((string) ($db['settings']['drive_ultimo_backup'] ?? ''));
    $pasta = trim((string) ($db['settings']['drive_ultimo_pasta'] ?? ''));
    $base = 'Conectado · ' . drive_email_mascarado($db);
    if ($ultimo === '') {
        return $base . ' · nenhum backup enviado ainda.';
    }
    return $base . ' · último: ' . $ultimo . ($pasta !== '' ? ' · ' . $pasta : '');
}

function drive_oauth_url(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['drive_oauth_state'] = $state;
    $q = http_build_query([
        'client_id' => trim(env_valor('GOOGLE_OAUTH_CLIENT_ID')),
        'redirect_uri' => drive_redirect_uri(),
        'response_type' => 'code',
        'scope' => drive_scopes(),
        'access_type' => 'offline',
        'prompt' => 'consent',
        'include_granted_scopes' => 'true',
        'state' => $state,
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $q;
}

function drive_http(string $method, string $url, string $accessToken, ?string $body = null, array $extraHeaders = [], int $timeout = 60): array
{
    $headers = array_merge([
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ], $extraHeaders);
    $ch = curl_init(google_url_com_key($url));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    curl_ca_nativo($ch);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string) $resp, true);
    return [$code, is_array($json) ? $json : null, (string) $resp, $err];
}

function drive_trocar_code(string $code): array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => trim(env_valor('GOOGLE_OAUTH_CLIENT_ID')),
            'client_secret' => trim(env_valor('GOOGLE_OAUTH_CLIENT_SECRET')),
            'redirect_uri' => drive_redirect_uri(),
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_ca_nativo($ch);
    $body = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('Google não devolveu o token de acesso.');
    }
    return $data;
}

function drive_refresh(array &$db): string
{
    $refresh = trim((string) ($db['settings']['drive_refresh_token'] ?? ''));
    if ($refresh === '') {
        throw new RuntimeException('Drive desconectado.');
    }
    $exp = (int) ($db['settings']['drive_token_exp'] ?? 0);
    $cached = trim((string) ($db['settings']['drive_access_token'] ?? ''));
    if ($cached !== '' && $exp > time() + 90) {
        return $cached;
    }
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => trim(env_valor('GOOGLE_OAUTH_CLIENT_ID')),
            'client_secret' => trim(env_valor('GOOGLE_OAUTH_CLIENT_SECRET')),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_ca_nativo($ch);
    $body = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        $db['settings']['drive_ultimo_erro'] = 'Falha ao renovar o token. Reconecte o Google Drive.';
        throw new RuntimeException('Não foi possível renovar o acesso ao Google Drive.');
    }
    $db['settings']['drive_access_token'] = (string) $data['access_token'];
    $db['settings']['drive_token_exp'] = time() + (int) ($data['expires_in'] ?? 3500);
    if (!empty($data['refresh_token'])) {
        $db['settings']['drive_refresh_token'] = (string) $data['refresh_token'];
    }
    return (string) $data['access_token'];
}

function drive_userinfo(string $accessToken): string
{
    [$code, $json] = drive_http('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', $accessToken);
    if ($code >= 200 && $code < 300 && is_array($json)) {
        return trim((string) ($json['email'] ?? ''));
    }
    return '';
}

function drive_buscar_pasta(string $accessToken, string $nome, string $parentId = 'root'): ?string
{
    $q = sprintf(
        "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false and '%s' in parents",
        str_replace("'", "\\'", $nome),
        $parentId
    );
    $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q' => $q,
        'spaces' => 'drive',
        'fields' => 'files(id,name)',
        'pageSize' => 5,
    ]);
    [$code, $json] = drive_http('GET', $url, $accessToken);
    if ($code >= 200 && $code < 300 && is_array($json)) {
        $files = $json['files'] ?? [];
        if (is_array($files) && isset($files[0]['id'])) {
            return (string) $files[0]['id'];
        }
    }
    return null;
}

function drive_criar_pasta(string $accessToken, string $nome, string $parentId = 'root'): string
{
    $existente = drive_buscar_pasta($accessToken, $nome, $parentId);
    if ($existente !== null) {
        return $existente;
    }
    [$code, $json, $raw] = drive_http(
        'POST',
        'https://www.googleapis.com/drive/v3/files?fields=id,name',
        $accessToken,
        json_encode([
            'name' => $nome,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ], JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json']
    );
    if ($code < 200 || $code >= 300 || !is_array($json) || empty($json['id'])) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : $raw;
        throw new RuntimeException('Não foi possível criar a pasta no Drive' . ($msg !== '' ? ': ' . $msg : '.'));
    }
    return (string) $json['id'];
}

function drive_upload_json(string $accessToken, string $pastaId, string $nomeArquivo, string $conteudo): string
{
    $boundary = 'fln_' . bin2hex(random_bytes(8));
    $meta = json_encode([
        'name' => $nomeArquivo,
        'parents' => [$pastaId],
    ], JSON_UNESCAPED_UNICODE);
    $body = "--{$boundary}\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $meta . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $conteudo . "\r\n"
        . "--{$boundary}--";
    [$code, $json, $raw] = drive_http(
        'POST',
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name',
        $accessToken,
        $body,
        ['Content-Type: multipart/related; boundary=' . $boundary],
        120
    );
    if ($code < 200 || $code >= 300 || !is_array($json) || empty($json['id'])) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : $raw;
        throw new RuntimeException('Falha ao enviar o backup' . ($msg !== '' ? ': ' . $msg : '.'));
    }
    return (string) $json['id'];
}

function drive_garantir_raiz(array &$db, string $accessToken): string
{
    $id = trim((string) ($db['settings']['drive_root_folder_id'] ?? ''));
    if ($id !== '') {
        return $id;
    }
    $id = drive_criar_pasta($accessToken, 'FLN Transfer Backups', 'root');
    $db['settings']['drive_root_folder_id'] = $id;
    return $id;
}

function drive_nome_pasta_dia(?DateTimeImmutable $quando = null): string
{
    $quando ??= today();
    return 'backup ' . $quando->format('d/m/Y');
}

function drive_payload_backup(array $db): string
{
    $copia = $db;
    // Não levar tokens de sessão/push desnecessários para o arquivo público do Drive.
    if (isset($copia['settings']) && is_array($copia['settings'])) {
        unset(
            $copia['settings']['drive_access_token'],
            $copia['settings']['drive_token_exp']
        );
    }
    $meta = [
        'gerado_em' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s'),
        'base' => store_base(),
        'versao_store' => store_versao(),
        'app' => 'FLN Transfer',
    ];
    return json_encode([
        'meta' => $meta,
        'estado' => $copia,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Cria/reusa a pasta do dia e envia o JSON do estado.
 * @return array{ok:bool,pasta:string,arquivo_id?:string,erro?:string}
 */
function drive_backup_executar(array &$db, string $origem = 'manual'): array
{
    if (!drive_conectado($db)) {
        return ['ok' => false, 'pasta' => '', 'erro' => 'Google Drive não está conectado.'];
    }
    try {
        $token = drive_refresh($db);
        $raiz = drive_garantir_raiz($db, $token);
        $pastaNome = drive_nome_pasta_dia();
        $pastaId = drive_criar_pasta($token, $pastaNome, $raiz);
        $agora = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d_H-i');
        $base = store_base();
        $arquivo = 'fln-base' . $base . '-' . $agora . '.json';
        $idArq = drive_upload_json($token, $pastaId, $arquivo, drive_payload_backup($db));
        $resumo = "FLN Transfer backup\nPasta: {$pastaNome}\nArquivo: {$arquivo}\nOrigem: {$origem}\nEm: "
            . (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
        drive_upload_json($token, $pastaId, 'resumo.txt', $resumo);
        $db['settings']['drive_ultimo_backup'] = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
        $db['settings']['drive_ultimo_dia'] = today()->format('d/m/Y');
        $db['settings']['drive_ultimo_pasta'] = $pastaNome;
        $db['settings']['drive_ultimo_arquivo_id'] = $idArq;
        $db['settings']['drive_ultimo_erro'] = '';
        $db['settings']['drive_ultimo_origem'] = $origem;
        return ['ok' => true, 'pasta' => $pastaNome, 'arquivo_id' => $idArq];
    } catch (Throwable $e) {
        $db['settings']['drive_ultimo_erro'] = clip($e->getMessage(), 240);
        return ['ok' => false, 'pasta' => '', 'erro' => $e->getMessage()];
    }
}

/** Roda no cron: no máximo 1 backup por dia civil (America/Sao_Paulo). */
function drive_cron(array &$db): ?array
{
    if (!drive_conectado($db)) {
        return null;
    }
    $hoje = today()->format('d/m/Y');
    if ((string) ($db['settings']['drive_ultimo_dia'] ?? '') === $hoje) {
        return ['ok' => true, 'pulado' => true, 'motivo' => 'já feito hoje'];
    }
    return drive_backup_executar($db, 'cron');
}

function drive_oauth_callback_public(): void
{
    $volta = href('configuracoes') . '#google-drive';
    $erro = trim((string) ($_GET['error'] ?? ''));
    if ($erro !== '') {
        flash('Conexão com o Google cancelada.', 'err');
        redirect($volta);
    }
    $state = (string) ($_GET['state'] ?? '');
    $esperado = (string) ($_SESSION['drive_oauth_state'] ?? '');
    unset($_SESSION['drive_oauth_state']);
    if ($esperado === '' || !hash_equals($esperado, $state)) {
        flash('Sessão da conexão expirou. Tente conectar de novo.', 'err');
        redirect($volta);
    }
    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '') {
        flash('Google não enviou o código de autorização.', 'err');
        redirect($volta);
    }
    if (empty($_SESSION['auth']) || (($_SESSION['role'] ?? '') !== 'admin')) {
        flash('Entre como administrador para concluir a conexão do Drive.', 'err');
        redirect(href('entrar'));
    }
    try {
        $tokens = drive_trocar_code($code);
        $email = drive_userinfo((string) $tokens['access_token']);
        com_db('drive_oauth_salvar', static function (array &$db) use ($tokens, $email): bool {
            $db['settings']['drive_ativo'] = true;
            $db['settings']['drive_refresh_token'] = (string) ($tokens['refresh_token'] ?? $db['settings']['drive_refresh_token'] ?? '');
            if ($db['settings']['drive_refresh_token'] === '') {
                throw new RuntimeException('O Google não enviou refresh token. Remova o acesso do app em myaccount.google.com/permissions e conecte de novo.');
            }
            $db['settings']['drive_access_token'] = (string) $tokens['access_token'];
            $db['settings']['drive_token_exp'] = time() + (int) ($tokens['expires_in'] ?? 3500);
            $db['settings']['drive_email'] = $email;
            $db['settings']['drive_conectado_em'] = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
            $db['settings']['drive_ultimo_erro'] = '';
            $token = (string) $tokens['access_token'];
            drive_garantir_raiz($db, $token);
            return true;
        });
        flash('Google Drive conectado. Os backups diários vão para pastas backup DD/MM/AAAA.');
    } catch (Throwable $e) {
        flash(clip($e->getMessage(), 200), 'err');
    }
    redirect($volta);
}

function drive_desconectar(array &$db): void
{
    foreach ([
        'drive_ativo',
        'drive_refresh_token',
        'drive_access_token',
        'drive_token_exp',
        'drive_email',
        'drive_root_folder_id',
        'drive_conectado_em',
        'drive_ultimo_backup',
        'drive_ultimo_dia',
        'drive_ultimo_pasta',
        'drive_ultimo_arquivo_id',
        'drive_ultimo_erro',
        'drive_ultimo_origem',
    ] as $k) {
        unset($db['settings'][$k]);
    }
}
