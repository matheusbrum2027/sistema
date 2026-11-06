<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Google Sheets: um arquivo (spreadsheet) por menu do painel, uma aba por submenu.
 * Credenciais: GOOGLE_SHEETS_ENABLED=1 e GOOGLE_SERVICE_ACCOUNT_B64 (JSON da conta de serviço em base64)
 * ou GOOGLE_SERVICE_ACCOUNT_JSON=caminho absoluto do .json
 * Opcional: GOOGLE_SHEETS_FOLDER_ID=pasta no Drive (compartilhe com o e-mail da conta de serviço)
 */
function sheets_ativo(): bool
{
    return env_valor('GOOGLE_SHEETS_ENABLED') === '1' && sheets_conta_servico() !== null;
}

function sheets_conta_servico(): ?array
{
    static $json = null;
    if ($json !== null) {
        return $json ?: null;
    }
    $b64 = trim(env_valor('GOOGLE_SERVICE_ACCOUNT_B64'));
    if ($b64 !== '') {
        $raw = base64_decode($b64, true);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($json) ? $json : null;
    }
    $path = trim(env_valor('GOOGLE_SERVICE_ACCOUNT_JSON'));
    if ($path !== '' && is_readable($path)) {
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }
    $json = false;
    return null;
}

function sheets_b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function sheets_token(): ?string
{
    static $cache = ['exp' => 0, 'token' => ''];
    if ($cache['exp'] > time() + 120) {
        return $cache['token'];
    }
    $sa = sheets_conta_servico();
    if ($sa === null || empty($sa['client_email']) || empty($sa['private_key'])) {
        return null;
    }
    $now = time();
    $header = sheets_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = sheets_b64url(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.file',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3500,
    ]));
    $payload = $header . '.' . $claim;
    $sig = '';
    if (!openssl_sign($payload, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        return null;
    }
    $jwt = $payload . '.' . sheets_b64url($sig);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_ca_nativo($ch);
    $body = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        return null;
    }
    $cache = ['exp' => $now + (int) ($data['expires_in'] ?? 3600), 'token' => (string) $data['access_token']];
    return $cache['token'];
}

function sheets_api(string $method, string $url, ?array $body = null, int $timeout = 45): array
{
    $token = sheets_token();
    if ($token === null) {
        throw new RuntimeException('Não foi possível autenticar no Google (conta de serviço).');
    }
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    curl_ca_nativo($ch);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $resp, true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? $json['error'] ?? '') : (string) $resp;
        throw new RuntimeException('Google API HTTP ' . $code . ($msg !== '' ? ': ' . $msg : ''));
    }
    return is_array($json) ? $json : [];
}

function sheets_nome_aba(string $titulo): string
{
    $s = preg_replace('/[\[\]\*\?\/\\\\:]/', '-', $titulo) ?? $titulo;
    $s = trim($s);
    if ($s === '') {
        $s = 'Aba';
    }
    return mb_substr($s, 0, 99);
}

function sheets_catalogo(): array
{
    $out = [];
    foreach (navigation() as $section) {
        foreach ($section['groups'] as $group) {
            $tabs = [];
            foreach ($group['items'] as $item) {
                $tabs[sheets_nome_aba((string) $item['title'])] = (string) $item['id'];
            }
            $out[(string) $group['id']] = [
                'titulo' => 'Transfer FLN · ' . (string) $group['label'],
                'tabs' => $tabs,
            ];
        }
    }
    if (isset($out['transfer'])) {
        $out['transfer']['tabs']['Grupos'] = '_grupos';
    }
    if (isset($out['sistema'])) {
        $out['sistema']['tabs']['Contadores'] = '_seq';
        $out['sistema']['tabs']['Zonas'] = '_zonas';
    }
    return $out;
}

function sheets_map_ler(array $db): array
{
    $raw = (string) ($db['settings']['sheets_arquivos'] ?? '');
    if ($raw === '') {
        return [];
    }
    $map = json_decode($raw, true);
    return is_array($map) ? $map : [];
}

function sheets_map_gravar(array &$db, array $map): void
{
    $db['settings']['sheets_arquivos'] = json_encode($map, JSON_UNESCAPED_UNICODE);
    $GLOBALS['fln_sheets_skip'] = true;
    if (store_remoto()) {
        store_gravar($db, 'sheets_map');
    } else {
        $_SESSION['db'] = $db;
    }
    db_request_cache($db);
    $GLOBALS['fln_sheets_skip'] = false;
}

function sheets_meta_gravar(array &$db, bool $ok, string $detalhe): void
{
    $db['settings']['sheets_sync_em'] = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i');
    $db['settings']['sheets_sync_erro'] = $ok ? '' : $detalhe;
    $db['settings']['sheets_sync_versao'] = (string) store_versao();
    $db['settings']['sheets_sync_pendente'] = !$ok;
    $GLOBALS['fln_sheets_skip'] = true;
    if (store_remoto()) {
        store_gravar($db, 'sheets_meta');
    } else {
        $_SESSION['db'] = $db;
    }
    db_request_cache($db);
    $GLOBALS['fln_sheets_skip'] = false;
}

function sheets_celula(mixed $v): string|int|float
{
    if ($v === null) {
        return '';
    }
    if (is_bool($v)) {
        return $v ? 'sim' : 'não';
    }
    if (is_int($v) || is_float($v)) {
        return $v;
    }
    if (is_array($v)) {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    $s = (string) $v;
    if (str_starts_with($s, 'data:image')) {
        return '[imagem no painel]';
    }
    if (strlen($s) > 45000) {
        return mb_substr($s, 0, 45000) . '…';
    }
    return $s;
}

function sheets_linhas_lista(array $lista, array $omitir = []): array
{
    if ($lista === []) {
        return [['(sem registros)']];
    }
    $chaves = [];
    foreach ($lista as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach (array_keys($row) as $k) {
            if (!in_array($k, $omitir, true)) {
                $chaves[$k] = true;
            }
        }
    }
    $cols = array_keys($chaves);
    sort($cols, SORT_STRING);
    $saida = [$cols];
    foreach ($lista as $row) {
        if (!is_array($row)) {
            continue;
        }
        $linha = [];
        foreach ($cols as $c) {
            $linha[] = sheets_celula($row[$c] ?? '');
        }
        $saida[] = $linha;
    }
    return $saida;
}

function sheets_linhas_kv(array $pares): array
{
    $saida = [['chave', 'valor']];
    foreach ($pares as $k => $v) {
        $saida[] = [(string) $k, sheets_celula($v)];
    }
    return $saida;
}

function sheets_motoristas_export(array $db): array
{
    $lista = [];
    foreach ($db['motoristas'] as $m) {
        $row = $m;
        if (isset($row['senha'])) {
            $row['senha'] = $row['senha'] !== '' ? '[definida no painel]' : '';
        }
        $lista[] = $row;
    }
    return sheets_linhas_lista($lista);
}

function sheets_agenda_export(array $db): array
{
    $rows = [];
    foreach ($db['transfers'] as $trip) {
        if (($trip['status'] ?? '') === 'cancelada') {
            continue;
        }
        foreach (agenda_legs($trip) as $leg) {
            $rows[] = [
                'reserva_id' => (int) $trip['id'],
                'trecho' => $leg['label'],
                'data' => $leg['data'],
                'hora' => $leg['hora'],
                'cliente' => $trip['cliente'],
                'status' => $trip['status'],
                'motorista_id' => $trip['motorista_id'],
                'origem' => $trip['origem'],
                'destino' => $trip['destino'],
                'valor' => $trip['valor'],
            ];
        }
    }
    usort($rows, static fn(array $a, array $b): int => [$a['data'], $a['hora'], $a['reserva_id']] <=> [$b['data'], $b['hora'], $b['reserva_id']]);
    return sheets_linhas_lista($rows);
}

function sheets_metricas_export(array $db): array
{
    $m = build_metrics($db, '30d');
    $linhas = [['indicador', 'valor']];
    foreach ($m as $k => $v) {
        if (is_array($v)) {
            $linhas[] = [$k, json_encode($v, JSON_UNESCAPED_UNICODE)];
        } else {
            $linhas[] = [$k, sheets_celula($v)];
        }
    }
    return $linhas;
}

function sheets_fin_dashboard(array $db): array
{
    $f = finance_snapshot($db);
    return sheets_linhas_kv([
        'total_a_receber' => brl((float) $f['total_receber']),
        'total_a_pagar' => brl((float) $f['total_pagar']),
        'pago_no_mes' => brl((float) $f['pago_mes']),
        'recebido_no_mes' => brl((float) $f['recebido_mes']),
        'viagens_a_receber' => count($f['receber']),
        'lancamentos_a_pagar' => count($f['abertos']),
    ]);
}

function sheets_receber_export(array $db): array
{
    $f = finance_snapshot($db);
    return sheets_linhas_lista($f['receber']);
}

function sheets_pagar_export(array $db): array
{
    $f = finance_snapshot($db);
    return sheets_linhas_lista($f['abertos']);
}

function sheets_faturado_export(array $db): array
{
    $lista = array_values(array_filter($db['transfers'], static fn(array $t): bool => !empty($t['faturado'])));
    return sheets_linhas_lista($lista);
}

function sheets_relatorios_export(array $db): array
{
    return sheets_linhas_lista(ledger($db));
}

function sheets_config_export(array $db): array
{
    $pares = [];
    foreach ($db['settings'] as $k => $v) {
        if (in_array($k, ['sheets_arquivos', 'logo', 'logo_lateral', 'favicon', 'assinatura', 'wa_qr'], true)) {
            continue;
        }
        if (str_starts_with($k, 'sheets_')) {
            continue;
        }
        $pares[$k] = $v;
    }
    ksort($pares);
    return sheets_linhas_kv($pares);
}

function sheets_contrato_export(array $db): array
{
    $s = $db['settings'];
    return sheets_linhas_kv([
        'contrato_pdf' => $s['contrato_pdf'] ?? false,
        'contrato_modelo' => $s['contrato_modelo'] ?? '',
        'contrato_cancelamento' => $s['contrato_cancelamento'] ?? '',
        'contrato_clausulas' => $s['contrato_clausulas'] ?? '',
    ]);
}

function sheets_automacoes_export(array $db): array
{
    $rows = [];
    foreach ($db['automacoes'] as $a) {
        $rows[] = array_merge(['tipo' => 'automacao'], $a);
    }
    foreach ($db['templates'] as $t) {
        $rows[] = array_merge(['tipo' => 'modelo'], $t);
    }
    return sheets_linhas_lista($rows);
}

function sheets_comunicador_export(array $db): array
{
    $rows = [];
    foreach ($db['mensagens'] ?? [] as $m) {
        $rows[] = array_merge(['tipo' => 'mensagem'], $m);
    }
    foreach ($db['whatsapp_envios'] ?? [] as $k => $info) {
        if (!is_array($info)) {
            continue;
        }
        $rows[] = ['tipo' => 'whatsapp_envio', 'chave' => $k] + $info;
    }
    return sheets_linhas_lista($rows);
}

function sheets_dados_aba(string $pageId, array $db): array
{
    return match ($pageId) {
        'agenda' => sheets_agenda_export($db),
        'metricas' => sheets_metricas_export($db),
        'fin-dashboard' => sheets_fin_dashboard($db),
        'lancamentos' => sheets_linhas_lista($db['lancamentos']),
        'receber' => sheets_receber_export($db),
        'faturado' => sheets_faturado_export($db),
        'pagar' => sheets_pagar_export($db),
        'relatorios' => sheets_relatorios_export($db),
        'solicitacoes' => sheets_linhas_lista($db['inbox'] ?? []),
        'reservas' => sheets_linhas_lista($db['transfers']),
        'contrato' => sheets_contrato_export($db),
        'mot-cadastros' => sheets_motoristas_export($db),
        'mot-solicitacoes' => sheets_linhas_lista($db['mot_solic'] ?? []),
        'clientes' => sheets_linhas_lista($db['clientes']),
        'veiculos' => sheets_linhas_lista($db['veiculos']),
        'configuracoes' => sheets_config_export($db),
        'automacoes' => sheets_automacoes_export($db),
        'comunicador' => sheets_comunicador_export($db),
        'anotacoes' => sheets_linhas_lista($db['notas'] ?? []),
        '_grupos' => sheets_linhas_lista($db['grupos'] ?? []),
        '_seq' => sheets_linhas_kv($db['seq'] ?? []),
        '_zonas' => sheets_linhas_lista($db['zonas'] ?? []),
        default => [['página', $pageId], ['aviso', 'Sem mapeamento dedicado; dados espelhados pelo identificador.']],
    };
}

function sheets_garantir_estrutura(array &$db, array $map): array
{
    $cat = sheets_catalogo();
    $folder = trim(env_valor('GOOGLE_SHEETS_FOLDER_ID'));
    foreach ($cat as $menuId => $info) {
        if (!isset($map[$menuId]['spreadsheetId'])) {
            $sheets = [];
            foreach (array_keys($info['tabs']) as $tabTitle) {
                $sheets[] = ['properties' => ['title' => $tabTitle]];
            }
            $created = sheets_api('POST', 'https://sheets.googleapis.com/v4/spreadsheets', [
                'properties' => ['title' => $info['titulo']],
                'sheets' => $sheets,
            ]);
            $sid = (string) ($created['spreadsheetId'] ?? '');
            if ($sid === '') {
                throw new RuntimeException('Não foi possível criar a planilha «' . $info['titulo'] . '».');
            }
            $map[$menuId] = ['spreadsheetId' => $sid, 'url' => 'https://docs.google.com/spreadsheets/d/' . $sid . '/edit'];
            if ($folder !== '') {
                sheets_api('PATCH', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($sid) . '?addParents=' . rawurlencode($folder) . '&fields=id', null);
            }
        }
        $meta = sheets_api('GET', 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($map[$menuId]['spreadsheetId']) . '?fields=sheets.properties');
        $existentes = [];
        foreach ($meta['sheets'] ?? [] as $sh) {
            $existentes[(string) ($sh['properties']['title'] ?? '')] = true;
        }
        $add = [];
        foreach (array_keys($info['tabs']) as $tabTitle) {
            if (!isset($existentes[$tabTitle])) {
                $add[] = ['addSheet' => ['properties' => ['title' => $tabTitle]]];
            }
        }
        if ($add !== []) {
            sheets_api('POST', 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($map[$menuId]['spreadsheetId']) . ':batchUpdate', [
                'requests' => $add,
            ]);
        }
    }
    sheets_map_gravar($db, $map);
    return $map;
}

function sheets_enviar_aba(string $spreadsheetId, string $tabTitle, array $values): void
{
    $range = rawurlencode($tabTitle . '!A1');
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/' . $range
        . '?valueInputOption=RAW';
    sheets_api('PUT', $url, ['values' => $values], 60);
}

function sheets_sync(array &$db, string $acao): void
{
    if (!sheets_ativo()) {
        return;
    }
    $inicio = microtime(true);
    try {
        $map = sheets_map_ler($db);
        $estrutura = (string) ($db['settings']['sheets_estrutura_versao'] ?? '');
        if ($map === [] || $estrutura !== '1') {
            $db['settings']['sheets_estrutura_versao'] = '1';
            $map = sheets_garantir_estrutura($db, $map);
            $map = sheets_map_ler($db);
        }
        $cat = sheets_catalogo();
        foreach ($cat as $menuId => $info) {
            $sid = (string) ($map[$menuId]['spreadsheetId'] ?? '');
            if ($sid === '') {
                continue;
            }
            foreach ($info['tabs'] as $tabTitle => $pageId) {
                sheets_enviar_aba($sid, $tabTitle, sheets_dados_aba($pageId, $db));
            }
        }
        sheets_meta_gravar($db, true, '');
    } catch (Throwable $e) {
        error_log('fln sheets (' . $acao . '): ' . $e->getMessage());
        try {
            sheets_meta_gravar($db, false, $e->getMessage());
        } catch (Throwable) {
        }
    } finally {
        perf_add('sheets_ms', (microtime(true) - $inicio) * 1000);
        perf_count('sheets_sync_count');
    }
}

function sheets_links_admin(array $db): array
{
    $map = sheets_map_ler($db);
    $links = [];
    foreach ($map as $menuId => $row) {
        if (!empty($row['url'])) {
            $links[$menuId] = (string) $row['url'];
        }
    }
    return $links;
}

function sheets_status_texto(array $db): string
{
    if (!sheets_ativo()) {
        return 'Integração desligada. Defina GOOGLE_SHEETS_ENABLED=1 e a conta de serviço no .env (ou no Vercel).';
    }
    $sa = sheets_conta_servico();
    $email = is_array($sa) ? (string) ($sa['client_email'] ?? '') : '';
    $sync = (string) ($db['settings']['sheets_sync_em'] ?? '');
    $erro = (string) ($db['settings']['sheets_sync_erro'] ?? '');
    $s = 'Conta de serviço: ' . ($email !== '' ? $email : '(não lida)') . '.';
    if ($sync !== '') {
        $s .= ' Último espelhamento: ' . $sync . '.';
    }
    if ($erro !== '') {
        $s .= ' Último erro: ' . $erro;
    } elseif ($sync !== '') {
        $s .= ' Planilhas atualizadas junto com o painel.';
    }
    return $s;
}
