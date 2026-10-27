<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/env.php';
carregar_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/charts.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/uazapi.php';
require_once __DIR__ . '/ops.php';
require_once __DIR__ . '/reserva_doc.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/supabase.php';

date_default_timezone_set('America/Sao_Paulo');

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function clip(string $value, int $max): string
{
    if (mb_strlen($value) <= $max) {
        return $value;
    }
    return mb_substr($value, 0, $max);
}

function one_of(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function post_date(string $key): string
{
    $s = post($key);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) {
        return '';
    }
    return $s;
}

function post_time(string $key): string
{
    $s = post($key);
    return preg_match('/^\d{2}:\d{2}$/', $s) ? $s : '';
}

function post_money(string $key): float
{
    $s = post($key);
    $s = str_replace(['R$', ' '], '', $s);
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '.', $s);
    }
    return round((float) $s, 2);
}

function brl(float $n): string
{
    return 'R$ ' . number_format($n, 2, ',', '.');
}

function brl_compact(float $n): string
{
    if (abs($n - round($n)) < 0.001) {
        return 'R$ ' . number_format($n, 0, ',', '.');
    }
    return brl($n);
}

function br_date(string $iso): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));
    return $d ? $d->format('d/m/Y') : '—';
}

function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

function href(string $page, array $query = []): string
{
    return 'index.php?' . http_build_query(array_merge(['p' => $page], $query));
}

function current_url(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
    $parts = parse_url($uri);
    $query = $parts['query'] ?? '';
    return 'index.php' . ($query !== '' ? '?' . $query : '');
}

function safe_back(string $fallback): string
{
    $back = (string) ($_POST['back'] ?? '');
    if (preg_match('/^index\.php(\?[A-Za-z0-9&=_.%\-]+)?$/', $back)) {
        return $back;
    }
    return $fallback;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = [$message, $type === 'err' ? 'err' : 'ok'];
}

function render_flash(): string
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return '';
    }
    [$message, $type] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="flash flash-' . e($type) . '" role="status">' . e((string) $message) . '</div>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $real = (string) ($_SESSION['csrf'] ?? '');
    return $real !== '' && hash_equals($real, $sent);
}

function csrf_fields(string $action): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="back" value="' . e(current_url()) . '">';
}

function sel(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function status_label(string $status): string
{
    return match ($status) {
        'pendente' => 'Pendente',
        'reserva' => 'Reserva',
        'andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'manutencao' => 'Manutenção',
        'pago' => 'Pago',
        'aberto' => 'Em aberto',
        'recebido' => 'Recebido',
        'previsto' => 'Previsto',
        'aprovada' => 'Aprovada',
        'recusada' => 'Recusada',
        'nova' => 'Nova',
        default => $status,
    };
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

function delta_pill(float $pct): string
{
    if (abs($pct) < 0.05) {
        return '<span class="pill">0.0%</span>';
    }
    $up = $pct > 0;
    $cls = $up ? 'pill-up' : 'pill-down';
    $sign = $up ? '+' : '-';
    return '<span class="pill ' . $cls . '">' . $sign . number_format(abs($pct), 1, '.', '') . '%</span>';
}

function view_head(string $actionsHtml = '', string $subtitleHtml = ''): void
{
    global $route;
    $sub = $subtitleHtml !== '' ? $subtitleHtml : e((string) ($route['subtitle'] ?? ''));
    echo '<div class="page-head">';
    echo '<div class="page-title"><span class="page-ico">' . icon((string) $route['icon']) . '</span><div>';
    echo '<h1>' . e((string) $route['title']) . '</h1>';
    if ($sub !== '') {
        echo '<p class="lead">' . $sub . '</p>';
    }
    echo '</div></div>';
    if ($actionsHtml !== '') {
        echo '<div class="page-actions">' . $actionsHtml . '</div>';
    }
    echo '</div>';
}

function empty_box(string $iconName, string $title, string $text): string
{
    return '<p class="empty-box"><strong>' . e($title) . '.</strong> ' . e($text) . '</p>';
}

function seed_data(): array
{
    return operation_seed();
}

function slug_usuario(string $nome): string
{
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ];
    $s = strtr(mb_strtolower($nome), $map);
    $s = preg_replace('/[^a-z0-9]+/', '.', $s) ?? '';
    $s = trim($s, '.');
    return $s !== '' ? $s : 'motorista';
}

function unique_usuario(array $db, string $base, int $ignoreId): string
{
    $taken = [mb_strtolower((string) ($db['settings']['usuario'] ?? 'admin')) => true];
    foreach ($db['motoristas'] as $m) {
        if ((int) ($m['id'] ?? 0) === $ignoreId || empty($m['usuario'])) {
            continue;
        }
        $taken[mb_strtolower((string) $m['usuario'])] = true;
    }
    $candidate = $base !== '' ? $base : 'motorista';
    $n = 2;
    while (isset($taken[mb_strtolower($candidate)])) {
        $candidate = $base . $n;
        $n++;
    }
    return $candidate;
}

function credenciais_motorista(array &$db, array $mot, string $usuario = '', string $senha = ''): array
{
    $usuario = slug_usuario($usuario !== '' ? $usuario : (string) $mot['nome']);
    $mot['usuario'] = unique_usuario($db, $usuario, (int) ($mot['id'] ?? 0));
    if ($senha === '') {
        $senha = 'floripa' . (string) random_int(1000, 9999);
    }
    $mot['senha'] = $senha;
    return $mot;
}

function normalize_db(array $db): array
{
    $legivel = isset($db['transfers'], $db['settings'], $db['motoristas'])
        && is_array($db['transfers'])
        && is_array($db['settings'])
        && is_array($db['motoristas']);
    if (!$legivel) {
        return seed_data();
    }
    $db['schema'] = max(2, (int) ($db['schema'] ?? 0));
    $seed = seed_data();
    foreach ($seed['settings'] as $key => $value) {
        if (!array_key_exists($key, $db['settings'] ?? [])) {
            $db['settings'][$key] = $value;
        }
    }
    if (($db['settings']['nome'] ?? '') === 'E-Transporte.pro') {
        $db['settings']['nome'] = 'Transfer FLN';
    }
    if (trim((string) ($db['settings']['uazapi_admin_token'] ?? '')) === '') {
        $envAdmin = trim(env_valor('UAZAPI_ADMIN_TOKEN', ''));
        if ($envAdmin !== '') {
            $db['settings']['uazapi_admin_token'] = $envAdmin;
        }
    }
    if (!isset($db['whatsapp_envios']) || !is_array($db['whatsapp_envios'])) {
        $db['whatsapp_envios'] = [];
    }
    if (!isset($db['mensagens']) || !is_array($db['mensagens'])) {
        $db['mensagens'] = [];
    }
    $tplIds = array_column($db['templates'] ?? [], 'id');
    foreach ($seed['templates'] as $tpl) {
        if (!in_array($tpl['id'], $tplIds, true)) {
            $db['templates'][] = $tpl;
        }
    }
    $autoIds = array_column($db['automacoes'] ?? [], 'id');
    foreach ($seed['automacoes'] as $row) {
        if (!in_array($row['id'], $autoIds, true)) {
            $db['automacoes'][] = $row;
        }
    }
    foreach ($db['motoristas'] as &$mot) {
        if (empty($mot['usuario'])) {
            $mot = credenciais_motorista($db, $mot, '', (string) ($mot['senha'] ?? ''));
        }
    }
    unset($mot);
    return $db;
}

function load_db(): array
{
    if (!isset($_SESSION['db']) || !is_array($_SESSION['db'])) {
        $_SESSION['db'] = seed_data();
    }
    $_SESSION['db'] = normalize_db($_SESSION['db']);
    return $_SESSION['db'];
}

function wa_number(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if ($digits !== '' && !str_starts_with($digits, '55') && strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }
    return $digits;
}

function uazapi_send_text(string $base, string $token, string $number, string $text, int $timeout = 20): array
{
    $base = rtrim(trim($base), '/');
    if (!preg_match('#^https://#i', $base)) {
        return [false, 'Informe a URL da instância começando com https://'];
    }
    if ($token === '' || $number === '' || $text === '') {
        return [false, 'Token, número ou texto em branco.'];
    }
    if (!function_exists('curl_init')) {
        return [false, 'O PHP deste computador não tem cURL.'];
    }
    $ch = curl_init($base . '/send/text');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'token: ' . $token],
        CURLOPT_POSTFIELDS => json_encode(['number' => $number, 'text' => $text], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(2, $timeout),
        CURLOPT_CONNECTTIMEOUT => min(3, max(1, $timeout)),
    ]);
    uazapi_curl_apply_ssl($ch);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return [false, $err !== '' ? $err : 'Sem resposta da UAZAPI.'];
    }
    if ($code < 200 || $code >= 300) {
        $snippet = trim(strip_tags((string) $body));
        if (strlen($snippet) > 140) {
            $snippet = substr($snippet, 0, 140);
        }
        return [false, 'UAZAPI respondeu HTTP ' . $code . ($snippet !== '' ? ' — ' . $snippet : '')];
    }
    return [true, 'Enviado'];
}

function dispatch_whatsapp(array &$db, bool $manual): bool
{
    $changed = false;
    $inicio = microtime(true);
    $limite = $manual ? 20.0 : 4.0;
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $hoje = $now->format('Y-m-d');
    $amanha = $now->modify('+1 day')->format('Y-m-d');
    $cfgOk = !empty($db['settings']['whatsapp_ativo'])
        && trim((string) ($db['settings']['uazapi_url'] ?? '')) !== ''
        && trim((string) ($db['settings']['uazapi_token'] ?? '')) !== '';
    $alvos = [
        'd1' => [$amanha, 'whatsapp_d1', 'mot_d1', 'WhatsApp · 1 dia antes'],
        'dia' => [$hoje, 'whatsapp_dia', 'mot_dia', 'WhatsApp · no dia'],
    ];
    foreach ($db['transfers'] as $trip) {
        if (!in_array((string) $trip['status'], ['pendente', 'reserva', 'andamento'], true)) {
            continue;
        }
        $mid = (int) ($trip['motorista_id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }
        $mot = null;
        foreach ($db['motoristas'] as $row) {
            if ((int) $row['id'] === $mid) {
                $mot = $row;
                break;
            }
        }
        if ($mot === null) {
            continue;
        }
        foreach ($alvos as $tipo => [$dataAlvo, $autoId, $tplId, $titulo]) {
            if ((string) $trip['data'] !== $dataAlvo || !auto_on($db, $autoId)) {
                continue;
            }
            $key = (int) $trip['id'] . '|' . $tipo;
            $prev = $db['whatsapp_envios'][$key] ?? null;
            if (is_array($prev) && !empty($prev['ok'])) {
                continue;
            }
            if (!$manual && is_array($prev) && empty($prev['ok'])) {
                $tentou = strtotime((string) ($prev['em'] ?? '')) ?: 0;
                if ($tentou > 0 && (time() - $tentou) < 600) {
                    continue;
                }
            }
            $texto = apply_template((string) (template_text($db, $tplId)), $trip, $db);
            $numero = wa_number((string) ($mot['telefone'] ?? ''));
            if (!$cfgOk) {
                if (!$manual) {
                    continue;
                }
                $ok = false;
                $detalhe = 'Configure a URL e o token da UAZAPI em Configurações.';
            } elseif (strlen($numero) < 12) {
                $ok = false;
                $detalhe = 'Telefone do motorista incompleto.';
            } elseif (!$manual && (microtime(true) - $inicio) >= $limite) {
                return $changed;
            } else {
                [$ok, $detalhe] = uazapi_send_text(
                    (string) $db['settings']['uazapi_url'],
                    (string) $db['settings']['uazapi_token'],
                    $numero,
                    $texto,
                    $manual ? 12 : 3
                );
            }
            $db['whatsapp_envios'][$key] = ['ok' => $ok, 'em' => $now->format('Y-m-d H:i')];
            $db['mensagens'][] = [
                'id' => $db['seq']['mensagem']++,
                'quando' => $now->format('Y-m-d H:i'),
                'titulo' => $titulo,
                'para' => $mot['nome'] . ' · ' . ($numero !== '' ? $numero : $mot['telefone']),
                'texto' => $texto,
                'viagem' => (int) $trip['id'],
                'canal' => 'whatsapp',
                'status' => $ok ? 'enviado' : 'falha',
                'detalhe' => $detalhe,
            ];
            $changed = true;
            if ($manual) {
                $_SESSION['wa_resumo'][] = ($ok ? 'Enviado' : 'Falhou') . ' para ' . $mot['nome'] . ' (viagem #' . $trip['id'] . ', ' . ($tipo === 'd1' ? 'véspera' : 'no dia') . '). ' . $detalhe;
            }
        }
    }
    return $changed;
}

function template_text(array $db, string $id): string
{
    foreach ($db['templates'] as $row) {
        if ($row['id'] === $id) {
            return (string) $row['texto'];
        }
    }
    return '';
}

function save_db(array $db): void
{
    $_SESSION['db'] = $db;
}

function index_of(array $list, int $id): ?int
{
    foreach ($list as $i => $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return (int) $i;
        }
    }
    return null;
}

function auto_on(array $db, string $id): bool
{
    foreach ($db['automacoes'] as $row) {
        if ($row['id'] === $id) {
            return !empty($row['ativo']);
        }
    }
    return false;
}

function apply_template(string $text, array $trip, array $db): string
{
    $motorista = 'a definir';
    if (!empty($trip['motorista_id'])) {
        foreach ($db['motoristas'] as $m) {
            if ((int) $m['id'] === (int) $trip['motorista_id']) {
                $motorista = $m['nome'];
                break;
            }
        }
    }
    return strtr($text, [
        '{cliente}' => (string) $trip['cliente'],
        '{origem}' => (string) $trip['origem'],
        '{destino}' => (string) $trip['destino'],
        '{data}' => br_date((string) $trip['data']),
        '{hora}' => (string) ($trip['hora'] ?: '--:--'),
        '{valor}' => !empty($trip['esconder_valores']) ? '—' : brl((float) $trip['valor']),
        '{motorista}' => $motorista,
        '{codigo}' => '#' . $trip['id'],
    ]);
}

function queue_message(array &$db, string $templateId, array $trip, string $quando, string $prefix = ''): void
{
    $tpl = null;
    foreach ($db['templates'] as $row) {
        if ($row['id'] === $templateId) {
            $tpl = $row;
            break;
        }
    }
    if ($tpl === null) {
        return;
    }
    $db['mensagens'][] = [
        'id' => $db['seq']['mensagem']++,
        'quando' => $quando,
        'titulo' => $prefix . $tpl['titulo'],
        'para' => $trip['cliente'],
        'texto' => apply_template($tpl['texto'], $trip, $db),
        'viagem' => (int) $trip['id'],
    ];
}

function apply_status_side_effects(array &$db, array $trip, string $old, string $new): void
{
    $now = today()->format('Y-m-d') . ' ' . (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('H:i');
    if ($old !== 'reserva' && $new === 'reserva' && auto_on($db, 'confirmacao')) {
        queue_message($db, 'confirmacao', $trip, $now);
    }
    if ($old !== 'reserva' && $new === 'reserva' && auto_on($db, 'lembrete')) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $trip['data']);
        $quando = $d ? $d->modify('-1 day')->format('Y-m-d') . ' 09:00' : (string) $trip['data'];
        queue_message($db, 'lembrete', $trip, $quando, 'Agendado · ');
    }
    if ($old !== 'andamento' && $new === 'andamento' && auto_on($db, 'caminho')) {
        queue_message($db, 'caminho', $trip, $now);
    }
}

function handle_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $action = post('action');
    if ($action === '') {
        return;
    }

    if ($action === 'login' || $action === 'recuperar') {
        $volta = $action === 'recuperar' ? href('entrar', ['recuperar' => '1']) : href('entrar');
        if (!csrf_ok()) {
            flash('Não foi possível continuar. Atualize a página.', 'err');
            redirect($volta);
        }
        if (login_bloqueado()) {
            flash('Muitas tentativas. Aguarde alguns minutos e tente de novo.', 'err');
            redirect($volta);
        }
        if (!captcha_confere(post('captcha'))) {
            login_falhou();
            flash('O código de verificação está incorreto ou expirou.', 'err');
            redirect($volta);
        }
        if ($action === 'recuperar') {
            $email = clip(post('usuario'), 80);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && supabase_configurado()) {
                supabase_recuperar($email);
            }
            flash('Se houver um cadastro para este e-mail, as instruções serão enviadas.');
            redirect(href('entrar'));
        }
        $db = load_db();
        $usuario = mb_strtolower(clip(post('usuario'), 80));
        $senha = post('senha');
        $entrou = false;
        if ($usuario !== '' && str_contains($usuario, '@') && supabase_configurado() && supabase_entrar($usuario, $senha)) {
            $entrou = true;
        }
        $adminUser = mb_strtolower((string) ($db['settings']['usuario'] ?? 'admin'));
        if (!$entrou && $usuario !== '' && $senha !== '' && $usuario === $adminUser && hash_equals((string) $db['settings']['senha'], $senha)) {
            $_SESSION['auth'] = true;
            $_SESSION['role'] = 'admin';
            $_SESSION['motorista_id'] = 0;
            $entrou = true;
        }
        if (!$entrou) {
            foreach ($db['motoristas'] as $mot) {
                $login = mb_strtolower((string) ($mot['usuario'] ?? ''));
                if ($usuario !== '' && $senha !== '' && $login === $usuario && ($mot['status'] ?? '') === 'ativo' && hash_equals((string) ($mot['senha'] ?? ''), $senha)) {
                    $_SESSION['auth'] = true;
                    $_SESSION['role'] = 'motorista';
                    $_SESSION['motorista_id'] = (int) $mot['id'];
                    $entrou = true;
                    break;
                }
            }
        }
        if ($entrou) {
            login_liberar();
            redirect(href('agenda'));
        }
        login_falhou();
        flash('Usuário ou senha incorretos.', 'err');
        redirect(href('entrar'));
    }
    if ($action === 'logout') {
        supabase_encerrar();
        $_SESSION['auth'] = false;
        $_SESSION['role'] = '';
        $_SESSION['motorista_id'] = 0;
        flash('Você saiu do painel.');
        redirect(href('entrar'));
    }
    if (empty($_SESSION['auth'])) {
        redirect(href('entrar'));
    }
    if (($_SESSION['role'] ?? 'admin') === 'motorista' && $action !== 'set_transfer_status') {
        flash('Seu acesso mostra apenas as suas viagens. Você pode alterar o status das reservas atribuídas a você.', 'err');
        redirect(href('agenda'));
    }
    if (!csrf_ok()) {
        flash('A sessão expirou. Tente de novo.', 'err');
        redirect(safe_back(href('agenda')));
    }

    $db = load_db();
    $err = null;
    $msg = 'Alteração salva.';
    $acoesExcluir = ['delete_veiculo', 'delete_motorista', 'delete_cliente', 'delete_lancamento', 'delete_grupo', 'delete_nota', 'delete_automacao', 'delete_inbox', 'delete_transfer', 'reset_demo', 'remove_logo'];
    if (in_array($action, $acoesExcluir, true) && post('confirmar') !== 'sim') {
        flash('A exclusão só é concluída depois do Sim na tela de confirmação.', 'err');
        redirect(safe_back(href('agenda')));
    }

    $ops = ops_dispatch($db, $action);
    if (is_array($ops)) {
        $err = $ops[0];
        if ($ops[1] !== '') {
            $msg = $ops[1];
        }
    } else switch ($action) {
        case 'reset_demo':
            uazapi_delete_instance($db['settings']);
            $db = seed_data();
            if (trim((string) ($db['settings']['uazapi_admin_token'] ?? '')) === '') {
                $envAdmin = trim(env_valor('UAZAPI_ADMIN_TOKEN', ''));
                if ($envAdmin !== '') {
                    $db['settings']['uazapi_admin_token'] = $envAdmin;
                }
            }
            $msg = 'Dados de demonstração restaurados.';
            break;

        case 'save_settings':
            $db['settings']['nome'] = clip(post('nome'), 80) ?: 'Transfer FLN';
            $db['settings']['razao'] = clip(post('razao'), 120);
            $db['settings']['cnpj'] = clip(post('cnpj'), 20);
            $db['settings']['telefone'] = clip(post('telefone'), 30);
            $db['settings']['email'] = clip(post('email'), 80);
            $db['settings']['cidade'] = clip(post('cidade'), 60);
            $db['settings']['uf'] = strtoupper(clip(post('uf'), 2));
            $db['settings']['endereco'] = clip(post('endereco'), 160);
            $db['settings']['pix'] = clip(post('pix'), 80);
            $horas = (int) post('antecedencia');
            $db['settings']['antecedencia'] = (string) max(0, min(72, $horas));
            $adminUser = slug_usuario(clip(post('usuario'), 40));
            if ($adminUser === '' || $adminUser === 'motorista') {
                $err = 'Informe o usuário do administrador.';
                break;
            }
            $db['settings']['usuario'] = $adminUser;
            $adminSenha = clip(post('senha'), 40);
            if ($adminSenha !== '') {
                $db['settings']['senha'] = $adminSenha;
            }
            $apiUrl = rtrim(clip(post('uazapi_url'), 160), '/');
            if ($apiUrl !== '' && !preg_match('#^https://#i', $apiUrl)) {
                $err = 'A URL da UAZAPI precisa começar com https://';
                break;
            }
            $db['settings']['uazapi_admin_token'] = clip(post('uazapi_admin_token'), 512);
            $createUrl = rtrim(clip(post('uazapi_create_url'), 220), '/');
            if ($createUrl !== '' && !preg_match('#^https://#i', $createUrl)) {
                $err = 'A URL de criação de instância precisa começar com https://';
                break;
            }
            $db['settings']['uazapi_create_url'] = $createUrl;
            $db['settings']['uazapi_url'] = $apiUrl;
            $db['settings']['uazapi_token'] = clip(post('uazapi_token'), 4096);
            $db['settings']['whatsapp_ativo'] = post('whatsapp_ativo') === '1';
            $msg = 'Configurações salvas.';
            break;

        case 'add_veiculo':
            $apelido = clip(post('apelido'), 40);
            $placa = strtoupper(clip(post('placa'), 8));
            $modelo = clip(post('modelo'), 40);
            $ano = (int) post('ano');
            $lugares = (int) post('lugares');
            if ($apelido === '' || strlen($placa) < 7 || $modelo === '' || $ano < 1990 || $ano > (int) today()->format('Y') + 1 || $lugares < 1 || $lugares > 60) {
                $err = 'Preencha apelido, placa, modelo, ano e lugares.';
                break;
            }
            $db['veiculos'][] = [
                'id' => $db['seq']['veiculo']++,
                'apelido' => $apelido,
                'placa' => $placa,
                'modelo' => $modelo,
                'ano' => $ano,
                'cor' => clip(post('cor'), 20),
                'lugares' => $lugares,
                'status' => one_of(post('status'), ['ativo', 'manutencao', 'inativo'], 'ativo'),
            ];
            $msg = 'Veículo cadastrado.';
            break;

        case 'delete_veiculo':
            $id = (int) post('id');
            $i = index_of($db['veiculos'], $id);
            if ($i === null) {
                $err = 'Veículo não encontrado.';
                break;
            }
            array_splice($db['veiculos'], $i, 1);
            foreach ($db['transfers'] as &$trip) {
                if ((int) ($trip['veiculo_id'] ?? 0) === $id) {
                    $trip['veiculo_id'] = null;
                }
            }
            unset($trip);
            $msg = 'Veículo removido.';
            break;

        case 'set_veiculo_status':
            $i = index_of($db['veiculos'], (int) post('id'));
            if ($i === null) {
                $err = 'Veículo não encontrado.';
                break;
            }
            $db['veiculos'][$i]['status'] = one_of(post('status'), ['ativo', 'manutencao', 'inativo'], 'ativo');
            $msg = 'Status do veículo atualizado.';
            break;

        case 'add_motorista':
            $nome = clip(post('nome'), 80);
            $telefone = clip(post('telefone'), 30);
            if ($nome === '' || $telefone === '') {
                $err = 'Informe nome e telefone do motorista.';
                break;
            }
            $mot = credenciais_motorista($db, [
                'id' => $db['seq']['motorista']++,
                'nome' => $nome,
                'telefone' => $telefone,
                'cnh' => clip(post('cnh'), 20),
                'categoria' => one_of(post('categoria'), ['B', 'C', 'D', 'E'], 'B'),
                'cidade' => clip(post('cidade'), 60),
                'status' => 'ativo',
            ], clip(post('usuario'), 40), clip(post('senha'), 40));
            $db['motoristas'][] = $mot;
            $msg = 'Motorista cadastrado. Acesso: ' . $mot['usuario'] . ' / senha ' . $mot['senha'];
            break;

        case 'delete_motorista':
            $id = (int) post('id');
            $i = index_of($db['motoristas'], $id);
            if ($i === null) {
                $err = 'Motorista não encontrado.';
                break;
            }
            array_splice($db['motoristas'], $i, 1);
            foreach ($db['transfers'] as &$trip) {
                if ((int) ($trip['motorista_id'] ?? 0) === $id) {
                    $trip['motorista_id'] = null;
                }
            }
            unset($trip);
            $msg = 'Motorista removido.';
            break;

        case 'set_motorista_status':
            $i = index_of($db['motoristas'], (int) post('id'));
            if ($i === null) {
                $err = 'Motorista não encontrado.';
                break;
            }
            $db['motoristas'][$i]['status'] = one_of(post('status'), ['ativo', 'inativo'], 'ativo');
            $msg = 'Status do motorista atualizado.';
            break;

        case 'add_cliente':
            $nome = clip(post('nome'), 80);
            if ($nome === '') {
                $err = 'Informe o nome do cliente.';
                break;
            }
            $db['clientes'][] = [
                'id' => $db['seq']['cliente']++,
                'nome' => $nome,
                'telefone' => clip(post('telefone'), 30),
                'email' => clip(post('email'), 80),
                'documento' => clip(post('documento'), 20),
                'cidade' => clip(post('cidade'), 60),
                'empresa' => clip(post('empresa'), 80),
            ];
            $msg = 'Cliente cadastrado.';
            break;

        case 'delete_cliente':
            $i = index_of($db['clientes'], (int) post('id'));
            if ($i === null) {
                $err = 'Cliente não encontrado.';
                break;
            }
            array_splice($db['clientes'], $i, 1);
            $msg = 'Cliente removido.';
            break;

        case 'add_transfer':
            $cliente = clip(post('cliente'), 80);
            $origem = clip(post('origem'), 180);
            $destino = clip(post('destino'), 180);
            $data = post_date('data');
            $hora = post_time('hora');
            $valor = post_money('valor');
            $pax = (int) post('pax');
            if ($cliente === '' || $origem === '' || $destino === '' || $data === '' || $hora === '' || $valor <= 0 || $pax < 1 || $pax > 40) {
                $err = 'Preencha cliente, origem, destino, data, hora, passageiros e valor.';
                break;
            }
            $db['transfers'][] = [
                'id' => $db['seq']['transfer']++,
                'cliente' => $cliente,
                'telefone' => clip(post('telefone'), 30),
                'origem' => $origem,
                'destino' => $destino,
                'data' => $data,
                'hora' => $hora,
                'valor' => $valor,
                'status' => 'pendente',
                'canal' => one_of(post('canal'), ['transfer', 'grupos', 'motoristas'], 'transfer'),
                'pax' => $pax,
                'motorista_id' => null,
                'veiculo_id' => null,
                'pago' => false,
                'pago_em' => null,
                'faturado' => false,
                'fatura' => null,
                'faturado_em' => null,
                'contrato' => null,
                'contrato_em' => null,
                'no_funil' => true,
                'obs' => clip(post('obs'), 300),
                'criado_em' => today()->format('Y-m-d'),
            ];
            $msg = 'Solicitação registrada.';
            break;

        case 'set_transfer_status':
            $i = index_of($db['transfers'], (int) post('id'));
            $new = one_of(post('status'), ['pendente', 'reserva', 'andamento', 'concluida', 'cancelada'], '');
            if ($i === null || $new === '') {
                $err = 'Não foi possível atualizar a viagem.';
                break;
            }
            $old = (string) $db['transfers'][$i]['status'];
            $db['transfers'][$i]['status'] = $new;
            apply_status_side_effects($db, $db['transfers'][$i], $old, $new);
            $msg = 'Status da viagem atualizado.';
            break;

        case 'assign_transfer':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                $err = 'Viagem não encontrada.';
                break;
            }
            $mid = (int) post('motorista_id');
            $vid = (int) post('veiculo_id');
            $db['transfers'][$i]['motorista_id'] = ($mid > 0 && index_of($db['motoristas'], $mid) !== null) ? $mid : null;
            $db['transfers'][$i]['veiculo_id'] = ($vid > 0 && index_of($db['veiculos'], $vid) !== null) ? $vid : null;
            $msg = 'Atribuição salva.';
            break;

        case 'receber_transfer':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                $err = 'Viagem não encontrada.';
                break;
            }
            $old = (string) $db['transfers'][$i]['status'];
            $db['transfers'][$i]['pago'] = true;
            $db['transfers'][$i]['pago_em'] = today()->format('Y-m-d');
            if (in_array($old, ['pendente', 'reserva', 'andamento'], true)) {
                $db['transfers'][$i]['status'] = 'concluida';
                apply_status_side_effects($db, $db['transfers'][$i], $old, 'concluida');
            }
            $msg = 'Recebimento registrado.';
            break;

        case 'faturar_transfer':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                $err = 'Viagem não encontrada.';
                break;
            }
            if (!empty($db['transfers'][$i]['faturado'])) {
                $err = 'Esta viagem já está faturada.';
                break;
            }
            $num = $db['seq']['fatura']++;
            $db['transfers'][$i]['faturado'] = true;
            $db['transfers'][$i]['fatura'] = 'FAT-' . today()->format('Y') . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            $db['transfers'][$i]['faturado_em'] = today()->format('Y-m-d');
            $msg = 'Fatura ' . $db['transfers'][$i]['fatura'] . ' emitida.';
            break;

        case 'emitir_contrato':
            $i = index_of($db['transfers'], (int) post('id'));
            if ($i === null) {
                $err = 'Viagem não encontrada.';
                break;
            }
            if (empty($db['transfers'][$i]['contrato'])) {
                $num = $db['seq']['contrato']++;
                $db['transfers'][$i]['contrato'] = 'CT-' . today()->format('Y') . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
                $db['transfers'][$i]['contrato_em'] = today()->format('Y-m-d');
            }
            $msg = 'Contrato ' . $db['transfers'][$i]['contrato'] . ' disponível.';
            break;

        case 'add_lancamento':
            $descricao = clip(post('descricao'), 120);
            $valor = post_money('valor');
            $data = post_date('data');
            $tipo = one_of(post('tipo'), ['entrada', 'saida'], '');
            if ($descricao === '' || $valor <= 0 || $data === '' || $tipo === '') {
                $err = 'Informe tipo, descrição, data e valor do lançamento.';
                break;
            }
            $db['lancamentos'][] = [
                'id' => $db['seq']['lancamento']++,
                'tipo' => $tipo,
                'descricao' => $descricao,
                'categoria' => clip(post('categoria'), 40) ?: ($tipo === 'entrada' ? 'Receita' : 'Despesa'),
                'valor' => $valor,
                'data' => $data,
                'vencimento' => post_date('vencimento') ?: $data,
                'status' => $tipo === 'entrada' ? 'recebido' : one_of(post('status'), ['aberto', 'pago'], 'aberto'),
            ];
            $msg = 'Lançamento registrado.';
            break;

        case 'delete_lancamento':
            $i = index_of($db['lancamentos'], (int) post('id'));
            if ($i === null) {
                $err = 'Lançamento não encontrado.';
                break;
            }
            array_splice($db['lancamentos'], $i, 1);
            $msg = 'Lançamento removido.';
            break;

        case 'baixar_lancamento':
            $i = index_of($db['lancamentos'], (int) post('id'));
            if ($i === null) {
                $err = 'Conta não encontrada.';
                break;
            }
            $db['lancamentos'][$i]['status'] = 'pago';
            $db['lancamentos'][$i]['data'] = today()->format('Y-m-d');
            $msg = 'Pagamento baixado.';
            break;

        case 'add_grupo':
            $nome = clip(post('nome'), 80);
            $data = post_date('data');
            $destino = clip(post('destino'), 180);
            $pax = (int) post('pax');
            $valor = post_money('valor');
            if ($nome === '' || $data === '' || $destino === '' || $pax < 1) {
                $err = 'Informe nome, data, destino e passageiros do grupo.';
                break;
            }
            $db['grupos'][] = [
                'id' => $db['seq']['grupo']++,
                'nome' => $nome,
                'data' => $data,
                'destino' => $destino,
                'pax' => $pax,
                'valor' => max(0, $valor),
                'status' => 'aberto',
            ];
            $msg = 'Grupo cadastrado.';
            break;

        case 'delete_grupo':
            $i = index_of($db['grupos'], (int) post('id'));
            if ($i === null) {
                $err = 'Grupo não encontrado.';
                break;
            }
            array_splice($db['grupos'], $i, 1);
            $msg = 'Grupo removido.';
            break;

        case 'add_nota':
            $titulo = clip(post('titulo'), 80);
            $texto = clip(post('texto'), 500);
            if ($titulo === '' || $texto === '') {
                $err = 'Informe título e texto da anotação.';
                break;
            }
            $db['notas'][] = [
                'id' => $db['seq']['nota']++,
                'titulo' => $titulo,
                'texto' => $texto,
                'criado_em' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i'),
            ];
            $msg = 'Anotação salva.';
            break;

        case 'delete_nota':
            $i = index_of($db['notas'], (int) post('id'));
            if ($i === null) {
                $err = 'Anotação não encontrada.';
                break;
            }
            array_splice($db['notas'], $i, 1);
            $msg = 'Anotação removida.';
            break;

        case 'toggle_automacao':
            $found = false;
            foreach ($db['automacoes'] as &$row) {
                if ((string) $row['id'] === post('id')) {
                    $row['ativo'] = empty($row['ativo']);
                    $found = true;
                    $msg = $row['ativo'] ? 'Automação ligada.' : 'Automação desligada.';
                    break;
                }
            }
            unset($row);
            if (!$found) {
                $err = 'Automação não encontrada.';
            }
            break;

        case 'save_template':
            $found = false;
            $texto = clip(post('texto'), 600);
            if ($texto === '') {
                $err = 'O modelo não pode ficar vazio.';
                break;
            }
            foreach ($db['templates'] as &$row) {
                if ($row['id'] === post('id')) {
                    $row['texto'] = $texto;
                    $found = true;
                    break;
                }
            }
            unset($row);
            if (!$found) {
                $err = 'Modelo não encontrado.';
                break;
            }
            $msg = 'Modelo atualizado.';
            break;

        case 'disparar_whatsapp':
            $_SESSION['wa_resumo'] = [];
            dispatch_whatsapp($db, true);
            $linhas = $_SESSION['wa_resumo'] ?? [];
            unset($_SESSION['wa_resumo']);
            $msg = $linhas === []
                ? 'Nenhum agendamento de hoje ou de amanhã com motorista para enviar.'
                : implode(' ', $linhas);
            break;

        case 'toggle_zona':
            $i = index_of($db['zonas'], (int) post('id'));
            if ($i === null) {
                $err = 'Cidade não encontrada.';
                break;
            }
            $db['zonas'][$i]['ativo'] = empty($db['zonas'][$i]['ativo']);
            $msg = $db['zonas'][$i]['ativo'] ? 'Cidade incluída na abrangência.' : 'Cidade removida da abrangência.';
            break;

        case 'add_mot_solic':
            $nome = clip(post('nome'), 80);
            $telefone = clip(post('telefone'), 30);
            if ($nome === '' || $telefone === '') {
                $err = 'Informe nome e telefone.';
                break;
            }
            $db['mot_solic'][] = [
                'id' => $db['seq']['mot_solic']++,
                'nome' => $nome,
                'telefone' => $telefone,
                'cidade' => clip(post('cidade'), 60),
                'cnh' => clip(post('cnh'), 20),
                'categoria' => one_of(post('categoria'), ['B', 'C', 'D', 'E'], 'B'),
                'mensagem' => clip(post('mensagem'), 300),
                'status' => 'nova',
                'criado_em' => today()->format('Y-m-d'),
            ];
            $msg = 'Solicitação de motorista registrada.';
            break;

        case 'set_mot_solic':
            $i = index_of($db['mot_solic'], (int) post('id'));
            $new = one_of(post('status'), ['aprovada', 'recusada'], '');
            if ($i === null || $new === '' || $db['mot_solic'][$i]['status'] !== 'nova') {
                $err = 'Esta solicitação já foi decidida.';
                break;
            }
            $db['mot_solic'][$i]['status'] = $new;
            if ($new === 'aprovada') {
                $s = $db['mot_solic'][$i];
                $mot = credenciais_motorista($db, [
                    'id' => $db['seq']['motorista']++,
                    'nome' => $s['nome'],
                    'telefone' => $s['telefone'],
                    'cnh' => (string) ($s['cnh'] ?? ''),
                    'categoria' => (string) ($s['categoria'] ?? 'B'),
                    'cidade' => (string) ($s['cidade'] ?? ''),
                    'uf' => 'SC',
                    'cpf' => (string) ($s['cpf'] ?? ''),
                    'criado_em' => today()->format('Y-m-d'),
                    'status' => 'ativo',
                ]);
                $db['motoristas'][] = $mot;
                $msg = 'Motorista aprovado. Acesso: ' . $mot['usuario'] . ' / senha ' . $mot['senha'];
            } else {
                $msg = 'Solicitação recusada.';
            }
            break;

        default:
            $err = 'Ação desconhecida.';
    }

    if ($err !== null) {
        flash($err, 'err');
        redirect(safe_back(href('agenda')));
    }
    save_db($db);
    flash($msg);
    redirect(safe_back(href('agenda')));
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https,
]);
session_start();

if (!isset($_SESSION['db'])) {
    $_SESSION['db'] = seed_data();
}
if (!isset($_SESSION['auth'])) {
    $_SESSION['auth'] = false;
}
if (empty($_SESSION['auth'])) {
    $_SESSION['role'] = '';
    $_SESSION['motorista_id'] = 0;
}

try {
    handle_post();
} catch (Throwable $e) {
    error_log('fln post: ' . $e->getMessage());
    flash('Não foi possível concluir esta ação. Tente novamente.', 'err');
    if (!headers_sent()) {
        header('Location: index.php?p=agenda');
        exit;
    }
}
