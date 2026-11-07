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
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://pps.whatsapp.net https://*.whatsapp.net; media-src 'self'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (isset($_GET['hook'])) {
    handle_public_hook((string) $_GET['hook']);
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
        if (sheets_ativo()) {
            $dbc = load_db();
            if (!empty($dbc['settings']['sheets_sync_pendente'])) {
                sheets_sync($dbc, 'cron');
                $sheetsOk = (string) ($dbc['settings']['sheets_sync_erro'] ?? '') === '';
            }
        }
        echo json_encode(['ok' => true, 'enviados' => $enviados, 'sheets' => $sheetsOk, 'em' => date('Y-m-d H:i')]);
    } catch (Throwable $e) {
        error_log('fln cron: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'aviso') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['auth']) || (($_SESSION['role'] ?? '') !== 'admin')) {
        http_response_code(401);
        echo '{"ok":false}';
        exit;
    }
    session_write_close();
    $dbAviso = load_db();
    $desde = (int) ($_GET['desde'] ?? 0);
    $itens = [];
    $ultimo = $desde;
    foreach ($dbAviso['avisos'] ?? [] as $aviso) {
        if (!is_array($aviso)) {
            continue;
        }
        $idAviso = (int) ($aviso['id'] ?? 0);
        if ($idAviso > $ultimo) {
            $ultimo = $idAviso;
        }
        if ($idAviso > $desde) {
            $itens[] = $aviso;
        }
    }
    echo json_encode(['ok' => true, 'itens' => $itens, 'ultimo' => $ultimo], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string) ($_GET['p'] ?? '') === 'aviso_apk') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    session_write_close();
    $token = (string) ($_GET['token'] ?? '');
    $desde = (int) ($_GET['desde'] ?? 0);
    try {
        $out = com_db('aviso_apk', static function (array &$db) use ($token, $desde): array {
            return push_avisos_apk($db, $token, $desde);
        });
        if (empty($out['ok'])) {
            http_response_code(401);
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'erro' => 'indisponivel']);
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
    $precisaPost = in_array($acao, ['enviar', 'salvar_config'], true);
    if ($precisaPost && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_ok())) {
        http_response_code(403);
        echo '{"ok":false,"erro":"Atualize a página e tente de novo."}';
        exit;
    }
    if ($acao !== 'salvar_config' && $acao !== 'enviar' && $acao !== 'msgs') {
        session_write_close();
    }
    $dbChat = load_db();
    chat_store_garantir($dbChat);
    $settings = $dbChat['settings'] ?? [];
    if ($acao === 'enviar') {
        $resp = chat_enviar($dbChat, (string) ($_POST['id'] ?? ''), (string) ($_POST['texto'] ?? ''));
        session_write_close();
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'salvar_config') {
        $resp = chat_salvar_config([
            'ignorar_grupos' => ($_POST['ignorar_grupos'] ?? '') === '1',
            'ia_ativa' => ($_POST['ia_ativa'] ?? '') === '1',
            'ia_parar' => (string) ($_POST['ia_parar'] ?? 'parar'),
            'ia_parar_min' => (int) ($_POST['ia_parar_min'] ?? 30),
            'ia_pausar_manual' => (int) ($_POST['ia_pausar_manual'] ?? 5),
            'openai_key' => (string) ($_POST['openai_key'] ?? ''),
        ]);
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
if ($current !== 'entrar' && empty($_SESSION['auth'])) {
    redirect(href('entrar'));
}
if ($current === 'entrar' && !empty($_SESSION['auth'])) {
    redirect(href('agenda'));
}
if ($role === 'motorista' && $current !== 'entrar' && $current !== 'agenda') {
    redirect(href('agenda'));
}

$route = $routes[$current];
$bare = !empty($route['bare']);
$db = load_db();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // CSRF/captcha/flash precisam gravar antes de soltar o lock da sessão.
    sessao_preparar_get($current, $db);
    // Na tela de login a sessão precisa permanecer aberta: o form ainda
    // pode gravar token/captcha, e fechar cedo quebrava o CSRF do Entrar.
    if ($current !== 'entrar') {
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
    if (!$trip || (!$adminFragment && !viagem_do_motorista($trip, (int) ($_SESSION['motorista_id'] ?? 0)))) {
        http_response_code(404);
        echo 'Reserva não encontrada.';
        exit;
    }
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

require __DIR__ . '/includes/layout-top.php';
require __DIR__ . '/' . $route['view'];
require __DIR__ . '/includes/layout-bottom.php';
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
