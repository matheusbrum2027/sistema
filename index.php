<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    error_log(sprintf('PHP %d: %s in %s:%d', $severity, $message, $file, $line));
    return true;
});

define('APP', true);
set_time_limit(180);

require __DIR__ . '/includes/bootstrap.php';

try {
if (isset($_GET['captcha']) && $_GET['captcha'] === 'img') {
    captcha_png();
}
if (isset($_GET['captcha']) && $_GET['captcha'] === 'audio') {
    captcha_wav();
}
if (isset($_GET['captcha_novo'])) {
    captcha_novo();
    redirect(href('entrar', isset($_GET['recuperar']) ? ['recuperar' => '1'] : []));
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://pps.whatsapp.net https://*.whatsapp.net https://api.qrserver.com https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://unpkg.com; media-src 'self'; style-src 'self' https://fonts.googleapis.com https://unpkg.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' https://unpkg.com 'unsafe-inline'; connect-src 'self' https://*.tile.openstreetmap.org; worker-src 'self' blob:; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.driverflntransfer.app https://driverflntransfer.app https://accounts.google.com");
if ((string) ($_GET['p'] ?? '') === 'marca') {
    $chave = (string) ($_GET['k'] ?? '');
    if (!in_array($chave, ['logo', 'logo_lateral', 'favicon', 'assinatura'], true)) {
        http_response_code(404);
        exit;
    }
    $uri = store_midia($chave);
    if (!preg_match('#^data:(image/[a-z0-9.+-]+);base64,([a-z0-9+/=\r\n]+)$#i', $uri, $m)) {
        http_response_code(404);
        exit;
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || $bin === '') {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . strtolower($m[1]));
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $bin;
    exit;
}
if (isset($_GET['hook'])) {
    handle_public_hook((string) $_GET['hook']);
}
if ((string) ($_GET['drive'] ?? '') === 'conectar') {
    drive_oauth_start_public();
}
if ((string) ($_GET['drive'] ?? '') === 'oauth') {
    drive_oauth_callback_public();
}
if (isset($_GET['cron'])) {
    header('Content-Type: application/json; charset=utf-8');
    $chave = env_valor('FLN_CRON');
    if (strlen($chave) < 16 || !hash_equals($chave, (string) $_GET['cron'])) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
    session_write_close();
    try {
        $enviados = disparar_pendentes(40.0);
        $sheetsOk = null;
        $drive = null;
        $presenca = null;
        $dbc = load_db();
        try {
            $presenca = com_db('wa_presenca_cron', static function (array &$db): array {
                if (!function_exists('uazapi_aplicar_presenca')) {
                    return ['ok' => false, 'pulado' => true];
                }
                if ((($db['settings']['wa_status'] ?? '') !== 'conectado') || !uazapi_settings_ready($db['settings'] ?? [])) {
                    return ['ok' => false, 'pulado' => true];
                }
                uazapi_aplicar_presenca($db['settings']);
                $db['settings']['wa_presenca_em'] = time();
                return [
                    'ok' => true,
                    'online' => function_exists('uazapi_dentro_horario_online')
                        ? uazapi_dentro_horario_online($db['settings'])
                        : true,
                ];
            });
        } catch (Throwable) {
            $presenca = ['ok' => false];
        }
        if (sheets_ativo()) {
            if (!empty($dbc['settings']['sheets_sync_pendente'])) {
                sheets_sync($dbc, 'cron');
                $sheetsOk = (string) ($dbc['settings']['sheets_sync_erro'] ?? '') === '';
            }
        }
        if (drive_conectado($dbc)) {
            $drive = com_db('drive_cron', static function (array &$db): array {
                $out = drive_cron($db);
                return is_array($out) ? $out : ['ok' => true, 'pulado' => true];
            });
        }
        echo json_encode(['ok' => true, 'enviados' => $enviados, 'sheets' => $sheetsOk, 'drive' => $drive, 'presenca' => $presenca, 'em' => date('Y-m-d H:i')]);
    } catch (Throwable $e) {
        error_log('fln cron: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'aviso') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['auth']) || !in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'motorista'], true)) {
        http_response_code(401);
        echo '{"ok":false}';
        exit;
    }
    session_write_close();
    $desde = (int) ($_GET['desde'] ?? 0);
    try {
        $r = store_avisos($desde);
    } catch (Throwable) {
        http_response_code(503);
        echo '{"ok":false}';
        exit;
    }
    $papel = (string) ($_SESSION['role'] ?? 'admin');
    $mid = (int) ($_SESSION['motorista_id'] ?? 0);
    $itens = array_values(array_filter(
        is_array($r['itens'] ?? null) ? $r['itens'] : [],
        static fn(mixed $item): bool => is_array($item) && aviso_item_para($item, $papel, $mid)
    ));
    echo json_encode([
        'ok' => true,
        'itens' => $itens,
        'ultimo' => (int) ($r['ultimo'] ?? $desde),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'aviso_apk') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    session_write_close();
    $token = (string) ($_GET['token'] ?? '');
    $desde = (int) ($_GET['desde'] ?? 0);
    try {
        $out = store_rpc('fln_aviso_apk', [
            'p_token' => $token,
            'p_desde' => $desde,
            'p_base' => store_base(),
        ], 12);
        if (!is_array($out) || empty($out['ok'])) {
            http_response_code(401);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    } catch (Throwable) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'erro' => 'indisponivel']);
    }
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'analise') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!analise_admin()) {
        http_response_code(401);
        echo '{"ok":false,"erro":"Entre como administrador."}';
        exit;
    }
    $acao = (string) ($_GET['acao'] ?? 'resumo');
    $periodo = (string) ($_GET['periodo'] ?? ($_POST['periodo'] ?? '90d'));
    if (!in_array($periodo, ['30d', '90d', '12m'], true)) {
        $periodo = '90d';
    }
    if ($acao === 'gerar') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_ok()) {
            http_response_code(403);
            echo '{"ok":false,"erro":"Atualize a página e tente de novo."}';
            exit;
        }
        session_write_close();
        $out = analise_gerar(load_db(), $periodo);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    session_write_close();
    echo json_encode(['ok' => true, 'dados' => analise_coleta(load_db(), $periodo)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'mapa') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!mapa_admin()) {
        http_response_code(401);
        echo '{"ok":false,"erro":"Entre como administrador."}';
        exit;
    }
    session_write_close();
    $filtro = (string) ($_GET['filtro'] ?? 'proximas');
    $status = (string) ($_GET['status'] ?? 'todas');
    try {
        $viagens = store_mapa();
    } catch (Throwable) {
        http_response_code(503);
        echo '{"ok":false}';
        exit;
    }
    echo json_encode(mapa_viagens(['transfers' => $viagens], $filtro, $status), JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'campanha') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!campanha_admin()) {
        http_response_code(401);
        echo '{"ok":false,"erro":"Entre como administrador."}';
        exit;
    }
    $acao = (string) ($_GET['acao'] ?? 'lista');
    $precisaPost = in_array($acao, ['salvar', 'status', 'apagar', 'disparar'], true);
    if ($precisaPost && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_ok())) {
        http_response_code(403);
        echo '{"ok":false,"erro":"Atualize a página e tente de novo."}';
        exit;
    }
    try {
        if ($acao === 'lista') {
            session_write_close();
            echo json_encode(campanha_lista(load_db()), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $resp = com_db('campanha_' . $acao, static function (array &$db) use ($acao): array {
            campanha_store_garantir($db);
            if ($acao === 'salvar') {
                return campanha_salvar($db, [
                    'nome' => (string) ($_POST['nome'] ?? ''),
                    'mensagem' => (string) ($_POST['mensagem'] ?? ''),
                    'publico' => (string) ($_POST['publico'] ?? 'clientes'),
                    'lista_manual' => (string) ($_POST['lista_manual'] ?? ''),
                ], (int) ($_POST['id'] ?? 0));
            }
            if ($acao === 'status') {
                return campanha_status($db, (int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
            }
            if ($acao === 'apagar') {
                return campanha_apagar($db, (int) ($_POST['id'] ?? 0));
            }
            if ($acao === 'disparar') {
                return campanha_disparar($db, (int) ($_POST['id'] ?? 0), (int) ($_POST['lote'] ?? 15));
            }
            return ['ok' => false, 'erro' => 'Ação inválida.'];
        });
        echo json_encode(is_array($resp) ? $resp : ['ok' => false, 'erro' => 'Falha ao salvar.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('fln campanha: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['ok' => false, 'erro' => 'Não foi possível concluir a ação.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'chat') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!chat_admin()) {
        http_response_code(401);
        echo '{"ok":false,"erro":"Entre como administrador."}';
        exit;
    }
    $acao = (string) ($_GET['acao'] ?? 'lista');
    $precisaPost = in_array($acao, ['enviar', 'salvar_config', 'pausar_ia', 'retomar_ia'], true);
    if ($precisaPost && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_ok())) {
        http_response_code(403);
        echo '{"ok":false,"erro":"Atualize a página e tente de novo."}';
        exit;
    }
    if ($acao !== 'salvar_config' && $acao !== 'enviar' && $acao !== 'msgs' && $acao !== 'pausar_ia' && $acao !== 'retomar_ia') {
        session_write_close();
    }
    if (in_array($acao, ['lista', 'poll', 'estado'], true)) {
        try {
            $dbChat = store_chat_banco();
        } catch (Throwable) {
            http_response_code(503);
            echo '{"ok":false,"erro":"Sem conexão com o chat."}';
            exit;
        }
    } else {
        $dbChat = load_db(false, true);
    }
    chat_store_garantir($dbChat);
    $settings = $dbChat['settings'] ?? [];
    if ($acao === 'enviar') {
        $resp = chat_enviar($dbChat, (string) ($_POST['id'] ?? ''), (string) ($_POST['texto'] ?? ''));
        session_write_close();
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'pausar_ia' || $acao === 'retomar_ia') {
        $idChat = (string) ($_POST['id'] ?? '');
        try {
            $resp = com_db('chat_' . $acao, static function (array &$db) use ($acao, $idChat): array {
                return chat_pausar_ia($db, $idChat, $acao === 'pausar_ia');
            });
            echo json_encode(is_array($resp) ? $resp : ['ok' => false, 'erro' => 'Falha.'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'erro' => 'Não foi possível alterar a IA.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    if ($acao === 'estado_ia') {
        session_write_close();
        echo json_encode(chat_estado_ia_conversa($dbChat, (string) ($_GET['id'] ?? '')), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'salvar_config') {
        $resp = chat_salvar_config([
            'ignorar_grupos' => ($_POST['ignorar_grupos'] ?? '') === '1',
            'ia_ativa' => ($_POST['ia_ativa'] ?? '') === '1',
            'ia_parar' => (string) ($_POST['ia_parar'] ?? 'parar'),
            'ia_parar_min' => (int) ($_POST['ia_parar_min'] ?? 30),
            'ia_pausar_manual' => (int) ($_POST['ia_pausar_manual'] ?? 5),
            'mostrar_digitando' => ($_POST['mostrar_digitando'] ?? '') === '1',
            'online_ativo' => ($_POST['online_ativo'] ?? '') === '1',
            'online_inicio' => (string) ($_POST['online_inicio'] ?? '06:00'),
            'online_fim' => (string) ($_POST['online_fim'] ?? '22:00'),
            'avaliacao_auto' => ($_POST['avaliacao_auto'] ?? '') === '1',
            'openai_key' => (string) ($_POST['openai_key'] ?? ''),
        ] + (array_key_exists('ia_perm', $_POST) ? ['ia_perm' => (string) $_POST['ia_perm']] : []));
        session_write_close();
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'config') {
        echo json_encode(chat_config($settings), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'msgs') {
        $resp = chat_mensagens($dbChat, (string) ($_GET['id'] ?? ''));
        session_write_close();
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'sync') {
        echo json_encode(chat_sync($dbChat), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'poll') {
        $puxar = (string) ($_GET['puxar'] ?? '') === '1';
        echo json_encode(chat_poll($dbChat, $puxar), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'estado') {
        echo json_encode(chat_estado($dbChat), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(chat_lista($dbChat), JSON_UNESCAPED_UNICODE);
    exit;
}

$routes = route_map();
$current = (string) ($_GET['p'] ?? 'agenda');
if (!isset($routes[$current]) || $current === 'abrangencia') {
    $current = 'agenda';
}
$role = (string) ($_SESSION['role'] ?? 'admin');
$paginasPublicas = ['entrar', 'seja-motorista'];
if (!in_array($current, $paginasPublicas, true) && empty($_SESSION['auth'])) {
    redirect(href('entrar'));
}
if ($current === 'entrar' && !empty($_SESSION['auth'])) {
    redirect(href('agenda'));
}
if ($role === 'motorista' && !in_array($current, ['entrar', 'agenda', 'seja-motorista'], true)) {
    redirect(href('agenda'));
}

$route = $routes[$current];
$bare = !empty($route['bare']);
$site = !empty($route['site']);
$acessoLogin = strtolower((string) ($_GET['acesso'] ?? ''));
if (in_array($current, $paginasPublicas, true) && !preg_match('/^[a-f0-9]{32}$/', $acessoLogin)) {
    $db = [
        'settings' => ['nome' => 'Transfer FLN'],
        'motoristas' => [],
        'automacoes' => [],
        'transfers' => [],
        'avisos' => [],
    ];
} else {
    $modo = 'casca';
    $de = '';
    $ate = '';
    $idTrip = 0;
    $comFinanceiro = ['fin-dashboard', 'receber', 'relatorios'];
    $comCaixa = ['lancamentos', 'pagar'];
    $comCorridas = ['reservas', 'faturado', 'metricas', 'viagens'];
    if (in_array($current, $comFinanceiro, true)) {
        $modo = 'financeiro';
    } elseif (in_array($current, $comCaixa, true)) {
        $modo = 'caixa';
    } elseif ($current === 'agenda' && (string) ($_GET['fragment'] ?? '') === 'reserva') {
        $modo = 'uma';
        $idTrip = (int) ($_GET['id'] ?? 0);
    } elseif ($current === 'agenda') {
        $modo = 'corridas';
        [$de, $ate] = agenda_intervalo((string) ($_GET['mes'] ?? ''));
    } elseif (in_array($current, $comCorridas, true) || ($current === 'mot-cadastros' && (int) ($_GET['id'] ?? 0) > 0)) {
        $modo = 'corridas';
    }
    $db = load_db(false, false, $modo, $de, $ate, $idTrip);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // CSRF/captcha/flash precisam gravar antes de soltar o lock da sessão.
    sessao_preparar_get($current, $db);
    // Login e landing pública mantêm a sessão aberta para o CSRF do formulário.
    if (!in_array($current, $paginasPublicas, true)) {
        session_write_close();
    }
}
if ($current === 'agenda' && (string) ($_GET['fragment'] ?? '') === 'reserva') {
    $id = (int) ($_GET['id'] ?? 0);
    $isFetch = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
    // Se abriu no navegador (ex.: após Comunicar), volta para a página normal — evita tela branca.
    if (!$isFetch) {
        if ((string) ($_GET['contexto'] ?? '') === 'reservas') {
            redirect(href('reservas'));
        }
        redirect($id > 0 ? href('agenda', ['ver' => $id]) : href('agenda'));
    }
    $trip = null;
    foreach ($db['transfers'] as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            $trip = $row;
            break;
        }
    }
    $adminFragment = $role !== 'motorista';
    if (!is_array($trip) || (!$adminFragment && !viagem_do_motorista($trip, (int) ($_SESSION['motorista_id'] ?? 0)))) {
        http_response_code(404);
        echo 'Reserva não encontrada.';
        exit;
    }
    $trip = trip_com_obs($trip);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    $contexto = (string) ($_GET['contexto'] ?? 'agenda') === 'reservas' ? 'reservas' : 'agenda';
    reserva_dialogos($db, $trip, $adminFragment, $contexto);
    exit;
}
$range = (string) ($_GET['range'] ?? '30d');
if (!in_array($range, ['hoje', '7d', '30d', '90d', '12m'], true)) {
    $range = '30d';
}

if ($current === 'solicitacoes' && isset($_GET['csv']) && $role === 'admin') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="solicitacoes.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'cliente', 'telefone', 'email', 'origem', 'destino', 'data', 'hora', 'valor'], ';');
    foreach ($db['inbox'] as $row) {
        fputcsv($out, [$row['id'], $row['cliente'], $row['telefone'], $row['email'], $row['origem'], $row['destino'], $row['data'], $row['hora'], $row['valor']], ';');
    }
    fclose($out);
    exit;
}

if (!empty($site)) {
    require __DIR__ . '/' . $route['view'];
} else {
    require __DIR__ . '/includes/layout-top.php';
    require __DIR__ . '/' . $route['view'];
    require __DIR__ . '/includes/layout-bottom.php';
}
} catch (Throwable $falha) {
    error_log('fln: ' . $falha->getMessage());
    if (!headers_sent()) {
        http_response_code($falha instanceof BancoIndisponivel ? 503 : 500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $aviso = $falha instanceof BancoIndisponivel
        ? 'Sem conexão com o banco de dados agora. Nenhum dado foi perdido nem alterado. Verifique a internet e tente de novo em instantes.'
        : 'Não foi possível abrir esta tela.';
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Transfer FLN</title><p style="font-family:sans-serif;padding:24px">' . e($aviso) . ' <a href="index.php?p=agenda">Tentar de novo</a></p></html>';
    exit;
}
