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

$uriPedido = (string) ($_SERVER['REQUEST_URI'] ?? '');
if (preg_match('#/(?:includes|supabase|scripts|backup|\\.git)(?:/|$)#i', $uriPedido) === 1
    || preg_match('#\\.(?:sql|env|ini|md|lock)(?:\\?|$)#i', $uriPedido) === 1) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit;
}

$hospede = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
if ($hospede === 'driverflntransfer.app') {
    header('Location: https://www.driverflntransfer.app' . (string) ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

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
$httpsPainel = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
if ($httpsPainel) {
    header('Strict-Transport-Security: max-age=15552000');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://pps.whatsapp.net https://*.whatsapp.net https://basemaps.cartocdn.com https://*.basemaps.cartocdn.com https://unpkg.com; media-src 'self'; style-src 'self' https://fonts.googleapis.com https://unpkg.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' https://unpkg.com 'unsafe-inline'; connect-src 'self' https://basemaps.cartocdn.com https://*.basemaps.cartocdn.com https://api.open-meteo.com https://air-quality-api.open-meteo.com https://economia.awesomeapi.com.br; worker-src 'self' blob:; frame-src 'self' blob:; object-src 'self' blob:; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.driverflntransfer.app https://driverflntransfer.app https://accounts.google.com");
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
    $tipo = strtolower($m[1]);
    if ($chave === 'assinatura') {
        $aparada = assinatura_bin_aparada($bin);
        if ($aparada !== $bin) {
            $bin = $aparada;
            $tipo = 'image/png';
        }
    }
    header('Content-Type: ' . $tipo);
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
        $presenca = ['ok' => false, 'pulado' => true];
        $dbc = load_db(true, false, 'casca');
        try {
            $ajustes = $dbc['settings'] ?? [];
            if (function_exists('uazapi_aplicar_presenca')
                && (($ajustes['wa_status'] ?? '') === 'conectado')
                && uazapi_settings_ready($ajustes)) {
                uazapi_aplicar_presenca($ajustes);
                $presenca = [
                    'ok' => true,
                    'online' => function_exists('uazapi_dentro_horario_online')
                        ? uazapi_dentro_horario_online($ajustes)
                        : true,
                ];
            }
        } catch (Throwable) {
            $presenca = ['ok' => false];
        }
        if (sheets_ativo() && !empty($dbc['settings']['sheets_sync_pendente'])) {
            $GLOBALS['fln_menu'] = false;
            $dbc = load_db(true, true);
            sheets_sync($dbc, 'cron');
            $sheetsOk = (string) ($dbc['settings']['sheets_sync_erro'] ?? '') === '';
        }
        if (drive_conectado($dbc) && (string) ($dbc['settings']['drive_ultimo_dia'] ?? '') !== today()->format('d/m/Y')) {
            $GLOBALS['fln_menu'] = false;
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

if ((string) ($_GET['p'] ?? '') === 'clima') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!painel_clima_sessao()) {
        http_response_code(401);
        echo '{"ok":false}';
        exit;
    }
    session_write_close();
    echo json_encode(painel_clima_publico(), JSON_UNESCAPED_UNICODE);
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
        $saida = mapa_viagens(['transfers' => is_array($viagens) ? $viagens : []], $filtro, $status);
    } catch (Throwable) {
        http_response_code(503);
        echo '{"ok":false,"erro":"Mapa indisponível agora."}';
        exit;
    }
    echo json_encode($saida, JSON_UNESCAPED_UNICODE);
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
    try {
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
    } catch (Throwable) {
        http_response_code(503);
        echo '{"ok":false,"erro":"WhatsApp indisponível agora."}';
    }
    exit;
}

$routes = route_map();
$current = (string) ($_GET['p'] ?? 'agenda');
if (!isset($routes[$current]) || $current === 'abrangencia') {
    $current = 'agenda';
}
$role = (string) ($_SESSION['role'] ?? 'admin');
$paginasPublicas = ['entrar', 'seja-motorista', 'aplicativo', 'verificar'];
if (!in_array($current, $paginasPublicas, true) && empty($_SESSION['auth'])) {
    redirect(href('entrar'));
}
if ($current === 'entrar' && !empty($_SESSION['auth'])) {
    redirect(href('agenda'));
}
if ($role === 'motorista' && !in_array($current, ['entrar', 'agenda', 'seja-motorista', 'aplicativo', 'verificar'], true)) {
    redirect(href('agenda'));
}
if (!eh_master() && in_array($current, paginas_somente_master(), true)) {
    flash('Só o administrador master acessa esta tela.', 'err');
    redirect(href('agenda'));
}
if (!pode_ver_financeiro() && in_array($current, paginas_financeiras(), true)) {
    flash('A parte financeira não está liberada para este acesso.', 'err');
    redirect(href('agenda'));
}
if ((string) ($_GET['baixar'] ?? '') === 'pdf' && !fln_ritmo('pdf', 40, 3600)) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 60');
    echo 'Muitos PDFs agora. Espere um pouco e baixe de novo.';
    exit;
}
if ((string) ($_GET['fragment'] ?? '') === 'reserva' && !fln_ritmo('reserva', 80, 600)) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 30');
    echo 'Muitas reservas abertas agora. Espere um instante.';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !in_array($current, ['entrar', 'chat', 'comunicador', 'aviso'], true)
    && !fln_ritmo('gravar', 40, 600)) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 30');
    echo 'Muitos envios agora. Espere um instante e tente de novo.';
    exit;
}

$route = $routes[$current];
$bare = !empty($route['bare']);
$site = !empty($route['site']);
$acessoLogin = strtolower((string) ($_GET['acesso'] ?? ''));
// A leitura do banco leva vários segundos. Solta a sessão antes, senão o próximo clique fica na fila.
$soltarSessaoAntes = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && !in_array($current, $paginasPublicas, true);
if ($soltarSessaoAntes) {
    sessao_preparar_get($current, []);
    session_write_close();
}
if (in_array($current, ['entrar', 'seja-motorista', 'aplicativo'], true) && !preg_match('/^[a-f0-9]{32}$/', $acessoLogin)) {
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
    } elseif ($current === 'agenda' && ((string) ($_GET['fragment'] ?? '') === 'reserva' || (string) ($_GET['baixar'] ?? '') === 'pdf')) {
        $modo = 'uma';
        $idTrip = (int) ($_GET['id'] ?? 0);
    } elseif ($current === 'agenda') {
        $modo = 'corridas';
        [$de, $ate] = agenda_intervalo((string) ($_GET['mes'] ?? ''));
    } elseif ($current === 'clientes' && (string) ($_GET['fragment'] ?? '') === 'cliente') {
        $modo = 'corridas';
    } elseif (in_array($current, $comCorridas, true) || ($current === 'mot-cadastros' && (int) ($_GET['id'] ?? 0) > 0)) {
        $modo = 'corridas';
        if (in_array($current, ['reservas', 'faturado', 'viagens'], true)) {
            $dePedido = (string) ($_GET['de'] ?? '');
            $atePedido = (string) ($_GET['ate'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dePedido) === 1) {
                $de = $dePedido;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $atePedido) === 1) {
                $ate = $atePedido;
            }
        }
    }
    $db = load_db(false, false, $modo, $de, $ate, $idTrip);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && empty($soltarSessaoAntes)) {
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
    if ($adminFragment) {
        $trip = trip_com_obs($trip);
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    $contexto = (string) ($_GET['contexto'] ?? 'agenda') === 'reservas' ? 'reservas' : 'agenda';
    reserva_dialogos($db, $trip, $adminFragment, $contexto);
    exit;
}
if ($current === 'agenda' && (string) ($_GET['baixar'] ?? '') === 'pdf') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!reserva_pode_pdf([])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Sem permissão para baixar este PDF.';
        exit;
    }
    $trip = null;
    foreach ($db['transfers'] as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            $trip = $row;
            break;
        }
    }
    if (!is_array($trip)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Reserva não encontrada.';
        exit;
    }
    $pdf = reserva_pdf_confirmacao($db, $trip);
    $arquivo = reserva_arquivo_pdf($trip);
    while (ob_get_level() > 0 && ob_end_clean()) {
    }
    if (!str_starts_with($pdf, '%PDF')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não foi possível gerar o PDF.';
        exit;
    }
    @ini_set('zlib.output_compression', '0');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . reserva_pdf_disposition($arquivo, (string) ($_GET['ver'] ?? '') === '1'));
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
if ($current === 'relatorios' && (string) ($_GET['baixar'] ?? '') === 'pdf') {
    $consulta = relatorio_consulta($db);
    $pdf = relatorio_pdf_bytes($db, $consulta);
    $arquivo = relatorio_arquivo_pdf($consulta);
    while (ob_get_level() > 0 && ob_end_clean()) {
    }
    if (!str_starts_with($pdf, '%PDF')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não foi possível gerar o PDF.';
        exit;
    }
    @ini_set('zlib.output_compression', '0');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . reserva_pdf_disposition($arquivo, (string) ($_GET['ver'] ?? '') === '1'));
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
if ($current === 'verificar') {
    require __DIR__ . '/views/verificar.php';
    exit;
}
if ($current === 'clientes' && (string) ($_GET['fragment'] ?? '') === 'cliente') {
    $cliente = ficha_cliente_por_id($db, (int) ($_GET['id'] ?? 0));
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!is_array($cliente)) {
        http_response_code(404);
        echo 'Cliente não encontrado.';
        exit;
    }
    echo ficha_cliente_dialog($db, $cliente);
    exit;
}
if ($current === 'mot-cadastros' && (string) ($_GET['baixar'] ?? '') === 'dossie') {
    $mot = ficha_motorista_por_id($db, (int) ($_GET['id'] ?? 0));
    if (!is_array($mot)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Motorista não encontrado.';
        exit;
    }
    $pdf = dossie_pdf_bytes($db, $mot);
    $arquivo = 'Dossie ' . trim((string) ($mot['nome'] ?? 'motorista')) . '.pdf';
    while (ob_get_level() > 0 && ob_end_clean()) {
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . reserva_pdf_disposition($arquivo));
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
$range = (string) ($_GET['range'] ?? '30d');
if (!in_array($range, ['hoje', '7d', '30d', '90d', '12m'], true)) {
    $range = '30d';
}
$rangeDe = '';
$rangeAte = '';
$deInformada = (string) ($_GET['de'] ?? '');
$ateInformada = (string) ($_GET['ate'] ?? '');
$zonaPeriodo = new DateTimeZone('America/Sao_Paulo');
$deOk = DateTimeImmutable::createFromFormat('!Y-m-d', $deInformada, $zonaPeriodo);
$ateOk = DateTimeImmutable::createFromFormat('!Y-m-d', $ateInformada, $zonaPeriodo);
if ($deOk instanceof DateTimeImmutable && $ateOk instanceof DateTimeImmutable && $deOk->format('Y-m-d') === $deInformada && $ateOk->format('Y-m-d') === $ateInformada) {
    if ($ateOk < $deOk) {
        [$deOk, $ateOk] = [$ateOk, $deOk];
    }
    $range = 'periodo';
    $rangeDe = $deOk->format('Y-m-d');
    $rangeAte = $ateOk->format('Y-m-d');
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

$agendaVista = 'calendario';
if ($current === 'agenda' && (string) ($_GET['fragment'] ?? '') !== 'reserva' && (string) ($_GET['baixar'] ?? '') !== 'pdf') {
    $agendaVista = agenda_vista_fixar();
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
