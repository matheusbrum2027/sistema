<?php
declare(strict_types=1);

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
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (isset($_GET['hook'])) {
    handle_public_hook((string) $_GET['hook']);
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
if ($role === 'admin' && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
    if (dispatch_whatsapp($db, false)) {
        save_db($db);
    }
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
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Transfer FLN</title><p>Não foi possível abrir esta tela. <a href="index.php?p=agenda">Voltar à agenda</a></p></html>';
    exit;
}
