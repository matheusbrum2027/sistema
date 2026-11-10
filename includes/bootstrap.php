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
require_once __DIR__ . '/chat.php';
require_once __DIR__ . '/ops.php';
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/reserva_doc.php';
require_once __DIR__ . '/pdf_simples.php';
require_once __DIR__ . '/atendimento.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/campanha.php';
require_once __DIR__ . '/mapa.php';
require_once __DIR__ . '/analise.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/integridade.php';
require_once __DIR__ . '/sheets.php';
require_once __DIR__ . '/drive.php';

date_default_timezone_set('America/Sao_Paulo');

function perf_add(string $key, float $milliseconds, int $bytes = 0): void
{
    $GLOBALS['fln_perf'][$key] = ($GLOBALS['fln_perf'][$key] ?? 0.0) + $milliseconds;
    if ($bytes > 0) {
        $GLOBALS['fln_perf'][$key . '_bytes'] = ($GLOBALS['fln_perf'][$key . '_bytes'] ?? 0) + $bytes;
    }
}

function perf_count(string $key): void
{
    $GLOBALS['fln_perf'][$key] = ($GLOBALS['fln_perf'][$key] ?? 0) + 1;
}

function perf_enabled(): bool
{
    return env_valor('FLN_PERF_LOG', '0') === '1';
}

function db_cache_completo(?bool $marcar = null): bool
{
    static $completo = false;
    if ($marcar !== null) {
        $completo = $marcar;
    }
    return $completo;
}

function db_request_cache(?array $nova = null, bool $limpar = false): ?array
{
    static $db = null;
    if ($limpar) {
        $db = null;
        db_cache_completo(false);
    } elseif ($nova !== null) {
        $db = $nova;
    }
    return $db;
}

/** Favicon = logo da empresa (mesma imagem). Retorna [href, mime]. */
function brand_favicon(array $db): array
{
    $s = is_array($db['settings'] ?? null) ? $db['settings'] : [];
    // Ordem: logo da empresa (principal), lateral, favicon legado, arquivo estático.
    $marcaV = (int) ($s['marca_v'] ?? 1);
    foreach (['logo', 'logo_lateral', 'favicon'] as $chave) {
        if (trim((string) ($s[$chave] ?? '')) === '' && !empty($s['tem_' . $chave])) {
            return ['index.php?p=marca&k=' . $chave . '&v=' . $marcaV, 'image/png'];
        }
        $href = trim((string) ($s[$chave] ?? ''));
        if ($href === '') {
            continue;
        }
        $mime = 'image/png';
        if (preg_match('#^data:(image/[a-z0-9.+-]+)#i', $href, $m)) {
            $mime = strtolower($m[1]);
        } elseif (preg_match('/\.jpe?g($|\?)/i', $href)) {
            $mime = 'image/jpeg';
        } elseif (preg_match('/\.webp($|\?)/i', $href)) {
            $mime = 'image/webp';
        } elseif (preg_match('/\.gif($|\?)/i', $href)) {
            $mime = 'image/gif';
        } elseif (preg_match('/\.svg($|\?)/i', $href)) {
            $mime = 'image/svg+xml';
        }
        return [$href, $mime];
    }
    return ['assets/brand/logo-fln.jpg', 'image/jpeg'];
}

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

/** Nunca renderize o valor real de senha/token no HTML — só status mascarado. */
function mascara_segredo(string $valor): string
{
    $valor = trim($valor);
    if ($valor === '') {
        return '';
    }
    $len = strlen($valor);
    if ($len <= 8) {
        return str_repeat('•', max(4, $len));
    }
    return substr($valor, 0, 2) . str_repeat('•', max(8, $len - 4)) . substr($valor, -2);
}

function segredo_cadastrado(string $valor): bool
{
    return trim($valor) !== '';
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

/** URL segura para voltar após POST (nunca aponta para fragmento de diálogo). */
function csrf_back_url(): string
{
    if ((string) ($_GET['fragment'] ?? '') === 'reserva') {
        $id = (int) ($_GET['id'] ?? 0);
        if ((string) ($_GET['contexto'] ?? '') === 'reservas') {
            return href('reservas');
        }
        return $id > 0 ? href('agenda', ['ver' => $id]) : href('agenda');
    }
    $url = current_url();
    $q = [];
    parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $q);
    if (isset($q['fragment'])) {
        unset($q['fragment']);
        $page = (string) ($q['p'] ?? 'agenda');
        unset($q['p']);
        return href($page, $q);
    }
    return $url;
}

function safe_back(string $fallback): string
{
    $back = (string) ($_POST['back'] ?? '');
    if (!preg_match('/^index\.php(\?[A-Za-z0-9&=_.%\-]+)?$/', $back)) {
        return $fallback;
    }
    $q = [];
    parse_str((string) (parse_url($back, PHP_URL_QUERY) ?? ''), $q);
    // Fragmento de diálogo não é página completa — evita tela branca após Comunicar etc.
    if (($q['fragment'] ?? '') !== '') {
        $id = (int) ($q['id'] ?? 0);
        if (($q['contexto'] ?? '') === 'reservas') {
            return href('reservas');
        }
        return $id > 0 ? href('agenda', ['ver' => $id]) : href('agenda');
    }
    return $back;
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
    if (isset($GLOBALS['fln_flash']) && is_array($GLOBALS['fln_flash'])) {
        [$message, $type] = $GLOBALS['fln_flash'];
        unset($GLOBALS['fln_flash']);
    } elseif (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        [$message, $type] = $_SESSION['flash'];
        unset($_SESSION['flash']);
    } else {
        return '';
    }
    return '<div class="flash flash-' . e($type) . '" role="status">' . e((string) $message) . '</div>';
}

function login_aplicar_sessao(string $role, int $motoristaId = 0): void
{
    $_SESSION['auth'] = true;
    $_SESSION['role'] = $role;
    $_SESSION['motorista_id'] = $motoristaId;
    unset($_SESSION['pending_2fa']);
    login_liberar();
    session_regenerate_id(true);
}

function login_pendente_2fa(?array $pendente = null): ?array
{
    if ($pendente !== null) {
        $_SESSION['pending_2fa'] = $pendente;
        return $pendente;
    }
    $atual = $_SESSION['pending_2fa'] ?? null;
    if (!is_array($atual) || (int) ($atual['expira'] ?? 0) < time()) {
        unset($_SESSION['pending_2fa']);
        return null;
    }
    return $atual;
}

/** Grava na sessão o que a tela GET ainda precisa antes de liberar o lock. */
function sessao_preparar_get(string $pagina, array $db): void
{
    csrf_token();
    if ($pagina === 'entrar') {
        // Bloqueio antigo de tentativas não pode travar a tela para sempre.
        if ((int) ($_SESSION['login_bloqueio'] ?? 0) > 0 && (int) $_SESSION['login_bloqueio'] <= time()) {
            unset($_SESSION['login_bloqueio'], $_SESSION['login_falhas']);
        }
        $etapa = (string) ($_GET['etapa'] ?? '');
        if ($etapa !== '2fa') {
            captcha_garantir();
        }
    }
    if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        $GLOBALS['fln_flash'] = $_SESSION['flash'];
        unset($_SESSION['flash']);
    }
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
        . '<input type="hidden" name="back" value="' . e(csrf_back_url()) . '">';
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
        $email = mb_strtolower(trim((string) ($m['email'] ?? '')));
        if ($email !== '') {
            $taken[$email] = true;
        }
    }
    $candidate = $base !== '' ? $base : 'motorista';
    $n = 2;
    while (isset($taken[mb_strtolower($candidate)])) {
        $candidate = $base . $n;
        $n++;
    }
    return $candidate;
}

function email_motorista_em_uso(array $db, string $email, int $ignoreId = 0): bool
{
    $email = mb_strtolower(trim($email));
    if ($email === '') {
        return false;
    }
    foreach ($db['motoristas'] as $m) {
        if ((int) ($m['id'] ?? 0) === $ignoreId) {
            continue;
        }
        if (mb_strtolower(trim((string) ($m['email'] ?? ''))) === $email) {
            return true;
        }
        if (mb_strtolower(trim((string) ($m['usuario'] ?? ''))) === $email) {
            return true;
        }
    }
    return false;
}

/** Token de senha do motorista — o admin vê só uma vez (no flash de criação/reset). */
function token_senha_motorista(): string
{
    $alfa = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    $max = strlen($alfa) - 1;
    for ($i = 0; $i < 10; $i++) {
        $out .= $alfa[random_int(0, $max)];
    }
    return $out;
}

function credenciais_motorista(array &$db, array $mot, string $usuario = '', string $senha = ''): array
{
    $email = mb_strtolower(trim((string) ($mot['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mot['email'] = $email;
        // Login do portal = e-mail cadastrado.
        $mot['usuario'] = $email;
    } else {
        $base = $usuario !== '' ? $usuario : (string) ($mot['nome'] ?? 'motorista');
        if (str_contains($base, '@') && filter_var(mb_strtolower(trim($base)), FILTER_VALIDATE_EMAIL)) {
            $mot['usuario'] = mb_strtolower(trim($base));
            $mot['email'] = $mot['usuario'];
        } else {
            $mot['usuario'] = unique_usuario($db, slug_usuario($base), (int) ($mot['id'] ?? 0));
        }
    }
    if ($senha === '') {
        $senha = token_senha_motorista();
    }
    $mot['senha'] = $senha;
    if (!preg_match('/^[a-f0-9]{32}$/', strtolower((string) ($mot['acesso'] ?? '')))) {
        $mot['acesso'] = bin2hex(random_bytes(16));
    }
    return $mot;
}

function agenda_intervalo(string $mes = ''): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = today()->format('Y-m');
    }
    $cursor = DateTimeImmutable::createFromFormat('!Y-m-d', $mes . '-01', new DateTimeZone('America/Sao_Paulo'));
    if (!$cursor) {
        $cursor = today()->modify('first day of this month');
    }
    $gridStart = $cursor->modify('-' . (int) $cursor->format('w') . ' days');
    $gridFim = $gridStart->modify('+41 days');
    $hoje = today();
    $limite = $hoje->modify('+30 days');
    $de = $gridStart < $hoje ? $gridStart : $hoje;
    $ate = $gridFim > $limite ? $gridFim : $limite;
    return [$de->format('Y-m-d'), $ate->format('Y-m-d')];
}

function normalize_db(array $db): array
{
    $inicio = microtime(true);
    $menu = (string) ($db['_menu'] ?? '');
    unset($db['_menu']);
    if ($menu !== '') {
        $GLOBALS['fln_menu'] = true;
    }
    if (isset($db['transfers']['c'], $db['transfers']['r']) && is_array($db['transfers']['c']) && is_array($db['transfers']['r'])) {
        $db['transfers'] = transfers_expandir($db['transfers']);
    }
    if (!isset($db['transfers']) || !is_array($db['transfers'])) {
        $db['transfers'] = [];
    }
    if (!isset($db['lancamentos']) || !is_array($db['lancamentos'])) {
        $db['lancamentos'] = [];
    }
    $legivel = isset($db['transfers'], $db['settings'], $db['motoristas'])
        && is_array($db['transfers'])
        && is_array($db['settings'])
        && is_array($db['motoristas']);
    if (!$legivel) {
        $normalizado = seed_data();
        perf_add('normalize_ms', (microtime(true) - $inicio) * 1000);
        return $normalizado;
    }
    $db['schema'] = max(2, (int) ($db['schema'] ?? 0));
    $tplIds = array_column($db['templates'] ?? [], 'id');
    $autoIds = array_column($db['automacoes'] ?? [], 'id');
    $precisaSeed = !array_key_exists('contrato_modelo', $db['settings'])
        || !array_key_exists('wa_status', $db['settings'])
        || array_diff(['confirmacao', 'lembrete', 'caminho', 'mot_d1', 'mot_dia'], $tplIds) !== []
        || array_diff(['confirmacao', 'lembrete', 'caminho', 'resumo', 'whatsapp_criacao', 'whatsapp_d1', 'whatsapp_dia'], $autoIds) !== [];
    if ($precisaSeed) {
        $seed = seed_data();
        foreach ($seed['settings'] as $key => $value) {
            if (!array_key_exists($key, $db['settings'])) {
                $db['settings'][$key] = $value;
            }
        }
        foreach ($seed['templates'] as $tpl) {
            if (!in_array($tpl['id'], $tplIds, true)) {
                $db['templates'][] = $tpl;
            }
        }
        foreach ($seed['automacoes'] as $row) {
            if (!in_array($row['id'], $autoIds, true)) {
                $db['automacoes'][] = $row;
            }
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
    campanha_store_garantir($db);
    $GLOBALS['fln_acesso_novo'] = false;
    $GLOBALS['fln_vapid_novo'] = false;
    $GLOBALS['fln_chat_novo'] = false;
    $GLOBALS['fln_lixo_novo'] = false;
    $GLOBALS['fln_ia_operador_novo'] = false;
    foreach ([
        'notif_reserva' => true,
        'notif_solicitacao' => true,
        'notif_cancelamento' => true,
        'notif_status' => false,
        'notif_som' => true,
        'notif_vibrar' => true,
        'chat_ignorar_grupos' => true,
        'chat_ia_ativa' => true,
        'wa_mostrar_digitando' => true,
        'wa_online_ativo' => true,
        'wa_online_inicio' => '06:00',
        'wa_online_fim' => '22:00',
        'wa_avaliacao_auto' => true,
        'chat_ia_parar' => 'parar',
        'chat_ia_parar_min' => 30,
        'chat_ia_pausar_manual' => 5,
        'chat_openai_key' => '',
        'webhook_dominios' => [],
        'atendente_nome' => 'Matheus Brum',
        'atendente_fone' => '(48) 99967-2557',
        'dono_nome' => 'Matheus Brum',
        'dono_email' => 'matheusbrumds@gmail.com',
        'dono_telefone' => '(48) 99967-2557',
    ] as $chaveNotif => $valorNotif) {
        if (!array_key_exists($chaveNotif, $db['settings'])) {
            $db['settings'][$chaveNotif] = $valorNotif;
        }
    }
    if (empty($db['settings']['wa_cfg_v2'])) {
        $db['settings']['wa_mostrar_digitando'] = true;
        $db['settings']['wa_online_ativo'] = true;
        $db['settings']['wa_online_inicio'] = (string) ($db['settings']['wa_online_inicio'] ?? '06:00') ?: '06:00';
        $db['settings']['wa_online_fim'] = (string) ($db['settings']['wa_online_fim'] ?? '22:00') ?: '22:00';
        $db['settings']['wa_avaliacao_auto'] = true;
        $db['settings']['chat_ia_ativa'] = true;
        $db['settings']['wa_cfg_reserva_ok'] = true;
        $db['settings']['wa_cfg_v2'] = true;
    }
    if (function_exists('atendimento_store_garantir')) {
        atendimento_store_garantir($db);
    }
    // Normaliza chaves antigas de telefone (com/sem 9) uma vez.
    if (empty($db['settings']['wa_atend_norm_v1']) && function_exists('atendimento_fone_chave') && isset($db['atendimento']['por_fone']) && is_array($db['atendimento']['por_fone'])) {
        $novo = [];
        foreach ($db['atendimento']['por_fone'] as $foneAntigo => $ctx) {
            if (!is_array($ctx)) {
                continue;
            }
            $chave = atendimento_fone_chave((string) $foneAntigo);
            if ($chave === '') {
                continue;
            }
            $novo[$chave] = isset($novo[$chave]) && is_array($novo[$chave])
                ? array_merge($novo[$chave], $ctx)
                : $ctx;
        }
        $db['atendimento']['por_fone'] = $novo;
        $db['settings']['wa_atend_norm_v1'] = true;
    }
    if (function_exists('ia_operador_garantir')) {
        ia_operador_garantir($db);
    }
    if (!isset($db['push']) || !is_array($db['push'])) {
        $db['push'] = [];
    }
    if (!isset($db['avisos']) || !is_array($db['avisos'])) {
        $db['avisos'] = [];
    }
    chat_store_garantir($db);
    lixo_reservas_garantir($db);
    // Expira lixo no máx. 1x/dia — evita varrer a cada page load.
    $hojeLixo = today()->format('Y-m-d');
    if ((string) ($db['settings']['lixo_expurgo_em'] ?? '') !== $hojeLixo) {
        if (lixo_reservas_expirar($db) >= 0) {
            $db['settings']['lixo_expurgo_em'] = $hojeLixo;
            $GLOBALS['fln_lixo_novo'] = true;
        }
    }
    if (!isset($db['seq']) || !is_array($db['seq'])) {
        $db['seq'] = [];
    }
    if (!isset($db['seq']['aviso'])) {
        $db['seq']['aviso'] = 1;
    }
    push_vapid_garantir($db);
    foreach ($db['motoristas'] as &$mot) {
        if (empty($mot['usuario'])) {
            $mot = credenciais_motorista($db, $mot, '', (string) ($mot['senha'] ?? ''));
            $GLOBALS['fln_acesso_novo'] = true;
        } elseif (!preg_match('/^[a-f0-9]{32}$/', strtolower((string) ($mot['acesso'] ?? '')))) {
            $mot['acesso'] = bin2hex(random_bytes(16));
            $GLOBALS['fln_acesso_novo'] = true;
        }
    }
    unset($mot);
    perf_add('normalize_ms', (microtime(true) - $inicio) * 1000);
    return $db;
}

function persistir_acesso_motorista(array $db): array
{
    if (empty($GLOBALS['fln_acesso_novo']) && empty($GLOBALS['fln_vapid_novo']) && empty($GLOBALS['fln_chat_novo']) && empty($GLOBALS['fln_lixo_novo']) && empty($GLOBALS['fln_ia_operador_novo'])) {
        return $db;
    }
    $gravar = $db;
    if (!empty($GLOBALS['fln_menu'])) {
        unset($gravar['transfers'], $gravar['lancamentos']);
    }
    $GLOBALS['fln_acesso_novo'] = false;
    $GLOBALS['fln_vapid_novo'] = false;
    $GLOBALS['fln_chat_novo'] = false;
    $GLOBALS['fln_lixo_novo'] = false;
    $GLOBALS['fln_ia_operador_novo'] = false;
    try {
        save_db($gravar, 'acesso_motorista');
    } catch (Throwable $e) {
        error_log('fln acesso: ' . $e->getMessage());
    }
    return $db;
}

function load_db(bool $fresh = false, bool $completo = false, string $modo = '', string $de = '', string $ate = '', int $idTrip = 0): array
{
    if (!$fresh && $modo === '' && empty($GLOBALS['fln_menu']) && ($cache = db_request_cache()) !== null && (!$completo || db_cache_completo())) {
        perf_count('db_cache_hits');
        return $cache;
    }
    $inicio = microtime(true);
    if (store_remoto()) {
        $dados = store_ler($completo, $modo, $de, $ate, $idTrip);
        if ($dados === null) {
            $inicial = normalize_db(seed_data());
            try {
                store_gravar($inicial, 'inicializar');
            } catch (BancoConflito) {
                return load_db();
            }
            db_request_cache($inicial);
            db_cache_completo(true);
            perf_add('load_db_ms', (microtime(true) - $inicio) * 1000);
            return $inicial;
        }
        $normalizado = persistir_acesso_motorista(normalize_db($dados));
        db_request_cache($normalizado);
        db_cache_completo($completo);
        perf_add('load_db_ms', (microtime(true) - $inicio) * 1000);
        return $normalizado;
    }
    if (!isset($_SESSION['db']) || !is_array($_SESSION['db'])) {
        $_SESSION['db'] = seed_data();
    }
    $_SESSION['db'] = persistir_acesso_motorista(normalize_db($_SESSION['db']));
    db_request_cache($_SESSION['db']);
    db_cache_completo(true);
    perf_add('load_db_ms', (microtime(true) - $inicio) * 1000);
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
    // Celular BR sem o 9º dígito → padroniza (mesmo critério do atendimento).
    if (strlen($digits) === 12 && str_starts_with($digits, '55')) {
        $resto = substr($digits, 4);
        if (strlen($resto) === 8 && $resto !== '' && $resto[0] >= '6') {
            $digits = substr($digits, 0, 4) . '9' . $resto;
        }
    }
    return $digits;
}

function uazapi_send_text(string $base, string $token, string $number, string $text, int $timeout = 20): array
{
    return uazapi_send_json($base, $token, '/send/text', [
        'number' => $number,
        'text' => $text,
    ], $timeout, $text === '');
}

function uazapi_send_document(string $base, string $token, string $number, string $pdfBytes, string $docName, string $caption = '', int $timeout = 25): array
{
    if ($pdfBytes === '') {
        return [false, 'PDF vazio.'];
    }
    $file = 'data:application/pdf;base64,' . base64_encode($pdfBytes);
    return uazapi_send_json($base, $token, '/send/media', [
        'number' => $number,
        'type' => 'document',
        'file' => $file,
        'docName' => $docName !== '' ? $docName : 'reserva.pdf',
        'mimetype' => 'application/pdf',
        'text' => $caption,
    ], $timeout, false);
}

function uazapi_send_json(string $base, string $token, string $path, array $payload, int $timeout = 20, bool $bloquearTextoVazio = false): array
{
    $base = rtrim(trim($base), '/');
    if (!preg_match('#^https://#i', $base)) {
        return [false, 'Informe a URL da instância começando com https://'];
    }
    if ($token === '' || ($payload['number'] ?? '') === '') {
        return [false, 'Token ou número em branco.'];
    }
    if ($bloquearTextoVazio && trim((string) ($payload['text'] ?? '')) === '') {
        return [false, 'Texto em branco.'];
    }
    if (!function_exists('curl_init')) {
        return [false, 'O PHP deste computador não tem cURL.'];
    }
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'token: ' . $token],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(2, $timeout),
        CURLOPT_CONNECTTIMEOUT => min(5, max(1, $timeout)),
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

function whatsapp_pronto(array $db): bool
{
    return trim((string) ($db['settings']['uazapi_url'] ?? '')) !== ''
        && trim((string) ($db['settings']['uazapi_token'] ?? '')) !== '';
}

function whatsapp_configurado(array $db): bool
{
    return !empty($db['settings']['whatsapp_ativo']) && whatsapp_pronto($db);
}

function whatsapp_fila(array $db, bool $manual): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $hoje = $now->format('Y-m-d');
    $amanha = $now->modify('+1 day')->format('Y-m-d');
    $cfgOk = whatsapp_configurado($db);
    $alvos = [
        'd1' => [$amanha, 'whatsapp_d1', 'mot_d1', 'WhatsApp · 1 dia antes'],
        'dia' => [$hoje, 'whatsapp_dia', 'mot_dia', 'WhatsApp · no dia'],
    ];
    $motPorId = [];
    foreach ($db['motoristas'] as $row) {
        $motPorId[(int) $row['id']] = $row;
    }
    $fila = [];
    foreach ($db['transfers'] as $trip) {
        if (!in_array((string) $trip['status'], ['pendente', 'reserva', 'andamento'], true)) {
            continue;
        }
        foreach (agenda_legs($trip) as $leg) {
            foreach ($alvos as $tipo => $alvo) {
                if ((string) $leg['data'] !== $alvo[0] || !auto_on($db, $alvo[1]) || ($tipo === 'dia' && !empty($leg['passou']))) {
                    continue;
                }
                foreach (motoristas_do_trecho($trip, (string) $leg['kind']) as $mid) {
                    if (isset($motPorId[$mid]) && (string) ($motPorId[$mid]['status'] ?? 'ativo') === 'ativo') {
                        $fila[] = [$trip, $leg, $tipo, $motPorId[$mid]];
                    }
                }
            }
        }
    }
    $itens = [];
    $jaHoje = whatsapp_cards_enviados_hoje($db);
    foreach ($fila as [$trip, $leg, $tipo, $mot]) {
        [, , $tplId, $titulo] = $alvos[$tipo];
        $mid = (int) $mot['id'];
        if (isset($jaHoje[(int) $trip['id'] . '|' . $mid . '|' . $leg['label']])) {
            continue;
        }
        $key = (int) $trip['id'] . '|' . $tipo . '|' . $mid . '|' . $leg['data'] . ' ' . $leg['hora'] . '|' . $leg['label'];
        $prev = $db['whatsapp_envios'][$key] ?? null;
        if (is_array($prev) && !empty($prev['ok'])) {
            continue;
        }
        if (is_array($prev)) {
            $tentou = strtotime((string) ($prev['em'] ?? '')) ?: 0;
            $esperar = !empty($prev['enviando']) ? 180 : 600;
            if (!$manual && (!$cfgOk || ($tentou > 0 && (time() - $tentou) < $esperar))) {
                continue;
            }
            if ($manual && !empty($prev['enviando']) && (time() - $tentou) < 180) {
                continue;
            }
        }
        $tripLeg = $trip;
        $tripLeg['data'] = $leg['data'];
        $tripLeg['hora'] = $leg['hora'];
        if ($leg['kind'] === 'volta' && (string) ($trip['tipo'] ?? '') === 'ida_volta') {
            $tripLeg['origem'] = (string) $trip['destino'];
            $tripLeg['destino'] = (string) $trip['origem'];
        }
        $tripLeg['motorista_id'] = $mid;
        $tripLeg['esconder_valores'] = true;
        $itens[$key] = [
            'key' => $key,
            'tipo' => $tipo,
            'papel' => 'motorista',
            'titulo' => $titulo,
            'viagem' => (int) $trip['id'],
            'trecho' => (string) $leg['label'],
            'motorista' => (string) $mot['nome'],
            'nome' => (string) $mot['nome'],
            'telefone' => (string) ($mot['telefone'] ?? ''),
            'numero' => wa_number((string) ($mot['telefone'] ?? '')),
            'texto' => reserva_texto_motorista($db, $tripLeg, (string) $leg['label'], (string) $mot['nome']),
            'pdf' => true,
            'pdf_nome' => 'reserva-' . (int) $trip['id'] . '-' . preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $leg['label']) . '.pdf',
            'trip_leg' => $tripLeg,
        ];
    }
    return array_values($itens);
}

function whatsapp_enviar(array &$db, array $item, int $timeout): array
{
    if (!whatsapp_configurado($db)) {
        return [false, 'WhatsApp não enviado: configure a URL e o token da UAZAPI em Configurações e ligue o envio.'];
    }
    if (strlen($item['numero']) < 12) {
        return [false, 'Telefone do motorista incompleto.'];
    }
    // Card interativo (botões + IA só desta reserva); PDF sob demanda no botão.
    return whatsapp_enviar_manual($db, $item + ['papel' => $item['papel'] ?? 'motorista'], $timeout);
}

/** Cards já entregues hoje para a mesma reserva, motorista e trecho (criação ou lembrete). */
function whatsapp_cards_enviados_hoje(array $db): array
{
    $hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $idx = [];
    foreach ($db['whatsapp_envios'] ?? [] as $key => $prev) {
        if (!is_array($prev) || empty($prev['ok'])) {
            continue;
        }
        if (!str_starts_with((string) ($prev['em'] ?? ''), $hoje)) {
            continue;
        }
        $key = (string) $key;
        if (str_starts_with($key, 'mot:')) {
            $p = explode(':', $key);
            if (count($p) >= 4) {
                $tripId = (int) array_pop($p);
                $mid = (int) ($p[1] ?? 0);
                $label = implode(':', array_slice($p, 2));
                $idx[$tripId . '|' . $mid . '|' . $label] = true;
            }
            continue;
        }
        $partes = explode('|', $key);
        if (count($partes) >= 5) {
            $idx[(int) $partes[0] . '|' . (int) $partes[2] . '|' . $partes[4]] = true;
        }
    }
    return $idx;
}

function whatsapp_registrar(array &$db, array $item, bool $ok, string $detalhe): void
{
    $agora = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i');
    $db['whatsapp_envios'][$item['key']] = ['ok' => $ok, 'em' => $agora];
    $db['mensagens'][] = [
        'id' => $db['seq']['mensagem']++,
        'quando' => $agora,
        'titulo' => $item['titulo'],
        'para' => ($item['nome'] ?? $item['motorista']) . ' · ' . ($item['numero'] !== '' ? $item['numero'] : $item['telefone']),
        'texto' => $item['texto'],
        'viagem' => $item['viagem'],
        'canal' => 'whatsapp',
        'status' => $ok ? 'enviado' : 'falha',
        'detalhe' => $detalhe,
    ];
}

function fone_pessoa(array $pessoa): string
{
    foreach (['telefone', 'whatsapp', 'celular', 'fone'] as $chave) {
        $valor = trim((string) ($pessoa[$chave] ?? ''));
        if ($valor !== '') {
            return $valor;
        }
    }
    return '';
}

function item_comunicar_motorista(array $db, array $trip, array $mot, string $trecho, bool $opcional): array
{
    $mid = (int) ($mot['id'] ?? 0);
    $nomeMot = (string) ($mot['nome'] ?? '');
    $fone = fone_pessoa($mot);
    $tripLeg = $trip;
    $tripLeg['motorista_id'] = $mid;
    $tripLeg['esconder_valores'] = true;
    $rotulo = $trecho !== '' ? $trecho : 'Reserva';
    return [
        'key' => 'mot:' . $mid . ':' . $rotulo . ':' . (int) $trip['id'],
        'papel' => 'motorista',
        'tipo' => 'manual',
        'titulo' => 'WhatsApp · roteiro da reserva',
        'viagem' => (int) $trip['id'],
        'trecho' => $rotulo,
        'motorista' => $nomeMot,
        'nome' => $nomeMot,
        'telefone' => $fone,
        'numero' => wa_number($fone),
        'texto' => reserva_texto_motorista($db, $tripLeg, $rotulo, $nomeMot),
        'pdf' => true,
        'pdf_nome' => 'reserva-' . (int) $trip['id'] . '-' . preg_replace('/[^A-Za-z0-9_-]+/', '', $rotulo) . '.pdf',
        'trip_leg' => $tripLeg,
        'opcional' => $opcional,
    ];
}

function itens_comunicar_reserva(array $db, array $trip): array
{
    $motPorId = [];
    foreach ($db['motoristas'] as $row) {
        $motPorId[(int) $row['id']] = $row;
    }
    $itens = [];
    $vistos = [];
    $legs = agenda_legs($trip);
    foreach ($legs as $i => $leg) {
        $ids = motoristas_do_trecho($trip, (string) $leg['kind']);
        $trechos = $trip['trajetos'] ?? [];
        if (is_array($trechos) && isset($trechos[$i]) && is_array($trechos[$i])) {
            foreach ($trechos[$i]['motoristas'] ?? [] as $extraId) {
                $eid = is_array($extraId) ? (int) ($extraId['id'] ?? $extraId['motorista_id'] ?? 0) : (int) $extraId;
                if ($eid > 0) {
                    $ids[] = $eid;
                }
            }
        }
        if ($ids === []) {
            $principal = (int) ($trip['motorista_id'] ?? 0);
            if (($leg['kind'] ?? '') === 'volta' && (int) ($trip['motorista_volta_id'] ?? 0) > 0) {
                $principal = (int) $trip['motorista_volta_id'];
            }
            if ($principal > 0) {
                $ids[] = $principal;
            }
            foreach ($trip['extras'] ?? [] as $extra) {
                $eid = is_array($extra) ? (int) ($extra['id'] ?? $extra['motorista_id'] ?? 0) : (int) $extra;
                if ($eid > 0) {
                    $ids[] = $eid;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        foreach ($ids as $mid) {
            if (!isset($motPorId[$mid])) {
                continue;
            }
            $chaveUnica = $mid . '|' . $leg['label'];
            if (isset($vistos[$chaveUnica])) {
                continue;
            }
            $vistos[$chaveUnica] = true;
            $mot = $motPorId[$mid];
            $tripLeg = $trip;
            $tripLeg['data'] = $leg['data'];
            $tripLeg['hora'] = $leg['hora'];
            if ($leg['kind'] === 'volta' && (string) ($trip['tipo'] ?? '') === 'ida_volta') {
                $tripLeg['origem'] = (string) $trip['destino'];
                $tripLeg['destino'] = (string) $trip['origem'];
            } elseif ($leg['kind'] === 'volta' && (string) ($trip['tipo'] ?? '') === 'volta') {
                // Registro já é a volta: origem/destino já estão corretos.
            }
            $tripLeg['motorista_id'] = $mid;
            // Nunca incluir valores no texto ao motorista.
            $tripLeg['esconder_valores'] = true;
            $itens[] = item_comunicar_motorista($db, $tripLeg, $mot, (string) $leg['label'], false);
        }
    }
    return $itens;
}

function item_comunicar_cliente(array $db, array $trip): array
{
    $fone = (string) ($trip['telefone'] ?? '');
    $tpl = template_text($db, 'confirmacao');
    if ($tpl === '') {
        $tpl = 'Olá {cliente}, sua viagem {codigo} está confirmada para {data} às {hora}. Origem: {origem}. Destino: {destino}. Valor {valor}.';
    }
    return [
        'key' => 'cliente:' . (int) $trip['id'],
        'papel' => 'cliente',
        'tipo' => 'cliente',
        'titulo' => 'WhatsApp · confirmação ao cliente',
        'viagem' => (int) $trip['id'],
        'trecho' => 'Cliente',
        'motorista' => (string) ($trip['cliente'] ?? 'Cliente'),
        'nome' => (string) ($trip['cliente'] ?? 'Cliente'),
        'telefone' => $fone,
        'numero' => wa_number($fone),
        'texto' => apply_template($tpl, $trip, $db),
        // Cliente só entra se o admin marcar o card.
        'opcional' => true,
    ];
}

/** Destinatários possíveis (motoristas + cliente) para os cards de comunicação. */
function destinatarios_comunicar(array $db, array $trip, string $perna = ''): array
{
    $todos = itens_comunicar_reserva($db, $trip);
    $itens = $todos;
    if ($perna !== '') {
        $filtrados = array_values(array_filter($todos, static fn (array $item): bool => (string) $item['trecho'] === $perna));
        if ($filtrados !== []) {
            $itens = $filtrados;
        }
    }
    $comFoneOk = static fn (array $item): bool => strlen((string) ($item['numero'] ?? '')) >= 12;
    $motorsComFone = array_values(array_filter($itens, static fn (array $it): bool => ($it['papel'] ?? '') === 'motorista' && $comFoneOk($it)));
    // Sem motorista atribuído (ou telefone inválido): oferece escolher entre os ativos.
    if ($motorsComFone === []) {
        $rotulo = $perna !== '' ? $perna : 'Reserva';
        $ja = [];
        foreach ($itens as $it) {
            if (($it['papel'] ?? '') === 'motorista' && preg_match('/^mot:(\d+):/', (string) ($it['key'] ?? ''), $m)) {
                $ja[(int) $m[1]] = true;
            }
        }
        $extras = [];
        foreach ($db['motoristas'] as $mot) {
            if ((string) ($mot['status'] ?? 'ativo') !== 'ativo') {
                continue;
            }
            $mid = (int) ($mot['id'] ?? 0);
            if ($mid <= 0 || isset($ja[$mid])) {
                continue;
            }
            $cand = item_comunicar_motorista($db, $trip, $mot, $rotulo, true);
            if ($comFoneOk($cand)) {
                $extras[] = $cand;
            }
        }
        // Um único motorista com telefone: já deixa marcado (é o destino óbvio).
        if (count($extras) === 1) {
            $extras[0]['opcional'] = false;
        }
        $itens = array_merge(
            array_values(array_filter($itens, static fn (array $it): bool => ($it['papel'] ?? '') === 'motorista')),
            $extras
        );
    }
    $itens[] = item_comunicar_cliente($db, $trip);
    return $itens;
}

function whatsapp_enviar_manual(array &$db, array $item, int $timeout): array
{
    if (!whatsapp_pronto($db)) {
        return [false, 'Configure a URL e o token do WhatsApp em Configurações.'];
    }
    if (strlen($item['numero']) < 12) {
        $quem = (($item['papel'] ?? '') === 'cliente') ? 'cliente' : 'motorista';
        return [false, 'Telefone do ' . $quem . ' incompleto.'];
    }
    $papel = (($item['papel'] ?? '') === 'cliente') ? 'cliente' : 'motorista';
    $reservaId = (int) ($item['viagem'] ?? 0);
    $trip = null;
    if (is_array($item['trip_leg'] ?? null)) {
        $trip = $item['trip_leg'];
    } elseif ($reservaId > 0 && function_exists('atendimento_trip')) {
        $trip = atendimento_trip($db, $reservaId);
    }
    if ($trip === null || !function_exists('atendimento_enviar_card_cliente')) {
        // Fallback legado se o módulo de atendimento não estiver carregado.
        return uazapi_send_text(
            (string) $db['settings']['uazapi_url'],
            (string) $db['settings']['uazapi_token'],
            $item['numero'],
            (string) $item['texto'],
            $timeout
        );
    }
    $trip['esconder_valores'] = true;
    if ($papel === 'cliente') {
        [$ok, $det] = atendimento_enviar_card_cliente($db, $trip, (string) $item['numero']);
        return [$ok, $ok ? 'Card interativo enviado ao cliente (confirmar / PDF / atendente)' : $det];
    }
    [$ok, $det] = atendimento_enviar_card_motorista(
        $db,
        $trip,
        (string) $item['numero'],
        (string) ($item['nome'] ?? $item['motorista'] ?? ''),
        (string) ($item['trecho'] ?? '')
    );
    return [$ok, $ok ? 'Card interativo enviado ao motorista (ciência / PDF / atendente)' : $det];
}

/**
 * Comunica TODOS os motoristas ativos desta reserva (todos os trechos),
 * com card interativo (PDF sob demanda). Usado na criação e no Comunicar.
 * $forcar=false evita reenvio se já houve envio OK (anti-duplicata na criação).
 */
function comunicar_motoristas_da_reserva(array &$db, array $trip, bool $forcar = false): array
{
    if ((string) ($trip['status'] ?? '') === 'cancelada') {
        return [0, 0, ['Reserva cancelada não é comunicada.']];
    }
    $itens = itens_comunicar_reserva($db, $trip);
    if ($itens === []) {
        return [0, 0, ['Nenhum motorista ativo com telefone nesta reserva.']];
    }
    $okN = 0;
    $falhaN = 0;
    $linhas = [];
    $fones = [];
    foreach ($itens as $item) {
        $chave = (string) ($item['key'] ?? '');
        $tripId = (int) ($item['viagem'] ?? 0);
        $mid = 0;
        if (preg_match('/^mot:(\d+):/', $chave, $mMot)) {
            $mid = (int) $mMot[1];
        }
        $marca = $tripId . '|' . $mid . '|' . (string) ($item['trecho'] ?? '');
        $fone = (string) ($item['numero'] ?? '');
        $prev = $chave !== '' ? ($db['whatsapp_envios'][$chave] ?? null) : null;
        $ja = !$forcar && (
            (is_array($prev) && !empty($prev['ok']))
            || !empty($GLOBALS['fln_card_enviado'][$marca])
            || ($fone !== '' && isset($fones[$fone . '|' . $tripId . '|' . (string) ($item['trecho'] ?? '')]))
        );
        if ($ja) {
            if ($chave !== '' && !(is_array($prev) && !empty($prev['ok']))) {
                whatsapp_registrar($db, $item, true, 'Card já enviado nesta gravação.');
            }
            $linhas[] = 'JÁ ENVIADO ' . ($item['nome'] ?? '') . ' · ' . ($item['trecho'] ?? '') . ' · #' . $tripId;
            $okN++;
            continue;
        }
        $GLOBALS['fln_card_enviado'][$marca] = true;
        [$ok, $detalhe] = whatsapp_enviar_manual($db, $item, 18);
        if (!$ok) {
            unset($GLOBALS['fln_card_enviado'][$marca]);
        } elseif ($fone !== '') {
            $fones[$fone . '|' . $tripId . '|' . (string) ($item['trecho'] ?? '')] = true;
        }
        whatsapp_registrar($db, $item, $ok, $detalhe);
        if ($ok) {
            $okN++;
        } else {
            $falhaN++;
        }
        $linhas[] = ($ok ? 'OK' : 'FALHA') . ' ' . ($item['nome'] ?? '') . ' · ' . ($item['trecho'] ?? '') . ' · #' . (int) $item['viagem'] . ' — ' . $detalhe;
    }
    return [$okN, $falhaN, $linhas];
}

/** Após criar reserva(s): avisa cada motorista de cada trecho, sem valores, com PDF. */
function comunicar_reservas_novas_motoristas(array &$db, array $novas): string
{
    if ($novas === [] || !whatsapp_pronto($db)) {
        return '';
    }
    if (!auto_on($db, 'whatsapp_criacao')) {
        return '';
    }
    $okTotal = 0;
    $falhaTotal = 0;
    $detalhes = [];
    foreach ($novas as $trip) {
        if (!is_array($trip)) {
            continue;
        }
        [$okN, $falhaN, $linhas] = comunicar_motoristas_da_reserva($db, $trip);
        $okTotal += $okN;
        $falhaTotal += $falhaN;
        foreach ($linhas as $linha) {
            $detalhes[] = $linha;
        }
    }
    if ($okTotal === 0 && $falhaTotal === 0) {
        return '';
    }
    $resumo = 'Comunicado aos motoristas: ' . $okTotal . ' ok';
    if ($falhaTotal > 0) {
        $resumo .= ', ' . $falhaTotal . ' falha(s)';
    }
    $resumo .= '. ' . implode(' | ', $detalhes);
    return $resumo;
}

function comunicar_reserva(array &$db, int $id): array
{
    $i = index_of($db['transfers'], $id);
    if ($i === null) {
        return ['Reserva não encontrada.', ''];
    }
    $trip = $db['transfers'][$i];
    if ((string) ($trip['status'] ?? '') === 'cancelada') {
        return ['Reserva cancelada não é comunicada.', ''];
    }
    $perna = post('perna');
    $todos = destinatarios_comunicar($db, $trip, $perna);
    $porKey = [];
    foreach ($todos as $item) {
        $porKey[(string) $item['key']] = $item;
    }
    $escolhidos = $_POST['destino'] ?? [];
    if (!is_array($escolhidos)) {
        $escolhidos = $escolhidos !== '' ? [(string) $escolhidos] : [];
    }
    $escolhidos = array_values(array_filter(array_map('strval', $escolhidos)));
    // Sem cards (POST antigo): mantém só motoristas.
    if ($escolhidos === []) {
        $itens = array_values(array_filter($todos, static fn (array $it): bool => ($it['papel'] ?? '') === 'motorista'));
    } else {
        $itens = [];
        foreach ($escolhidos as $chave) {
            if (isset($porKey[$chave])) {
                $itens[] = $porKey[$chave];
            }
        }
    }
    if ($itens === []) {
        return ['Selecione pelo menos um destinatário (motorista ou cliente) nos cards.', ''];
    }
    $linhas = [];
    foreach ($itens as $item) {
        // Manual: sempre força reenvio (admin escolheu conscientemente).
        [$ok, $detalhe] = whatsapp_enviar_manual($db, $item, 18);
        whatsapp_registrar($db, $item, $ok, $detalhe);
        $papel = (($item['papel'] ?? '') === 'cliente') ? 'cliente' : 'motorista';
        $linhas[] = ($ok ? 'Enviado' : 'Falhou') . ' para ' . ($item['nome'] ?? $item['motorista']) . ' (' . $papel . ', reserva #' . $item['viagem'] . '). ' . $detalhe;
    }
    return [null, 'Comunicado da reserva #' . $id . ': ' . implode(' ', $linhas)];
}

function dispatch_whatsapp(array &$db, bool $manual): bool
{
    $inicio = microtime(true);
    $itens = whatsapp_fila($db, $manual);
    foreach ($itens as $item) {
        if (microtime(true) - $inicio >= 20.0) {
            break;
        }
        [$ok, $detalhe] = whatsapp_enviar($db, $item, 12);
        whatsapp_registrar($db, $item, $ok, $detalhe);
        $_SESSION['wa_resumo'][] = ($ok ? 'Enviado' : 'Falhou') . ' para ' . $item['motorista'] . ' (viagem #' . $item['viagem'] . ', ' . ($item['tipo'] === 'd1' ? 'véspera' : 'no dia') . '). ' . $detalhe;
    }
    return $itens !== [];
}

/**
 * Disparo automático: reserva os envios no banco antes de mandar, para que o painel
 * e a rotina agendada rodando juntos não enviem a mesma mensagem duas vezes.
 */
function disparar_pendentes(float $limite): int
{
    $agora = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i');
    $reservados = com_db('whatsapp_reservar', static function (array &$db) use ($agora) {
        $itens = whatsapp_fila($db, false);
        if ($itens === []) {
            return false;
        }
        foreach ($itens as $item) {
            $db['whatsapp_envios'][$item['key']] = ['ok' => false, 'em' => $agora, 'enviando' => true];
        }
        return ['itens' => $itens, 'db' => $db];
    });
    if (!is_array($reservados)) {
        return 0;
    }
    $inicio = microtime(true);
    $resultados = [];
    foreach ($reservados['itens'] as $item) {
        if (microtime(true) - $inicio >= $limite) {
            break;
        }
        $resultados[] = [$item, ...whatsapp_enviar($reservados['db'], $item, 8)];
    }
    if ($resultados === []) {
        return 0;
    }
    $atendimentoSnap = $reservados['db']['atendimento'] ?? null;
    com_db('whatsapp_resultado', static function (array &$db) use ($resultados, $atendimentoSnap): bool {
        foreach ($resultados as [$item, $ok, $detalhe]) {
            whatsapp_registrar($db, $item, (bool) $ok, (string) $detalhe);
        }
        if (is_array($atendimentoSnap)) {
            $db['atendimento'] = $atendimentoSnap;
        }
        return true;
    });
    return count($resultados);
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

function save_db(array $db, string $acao = ''): void
{
    $acaoNorm = strtolower(trim($acao));
    $semSheets = $acaoNorm === ''
        || str_starts_with($acaoNorm, 'chat_')
        || str_starts_with($acaoNorm, 'push_')
        || str_starts_with($acaoNorm, 'drive_')
        || in_array($acaoNorm, ['acesso_motorista', 'aviso_apk', 'candidatura_motorista'], true);
    if (empty($GLOBALS['fln_sheets_skip']) && sheets_ativo() && !$semSheets) {
        $db['settings']['sheets_sync_pendente'] = true;
        $db['settings']['sheets_sync_pendente_em'] = date('Y-m-d H:i');
    }
    if (store_remoto()) {
        store_gravar($db, $acao);
    } else {
        $_SESSION['db'] = $db;
    }
    db_request_cache($db);
    if (!empty($GLOBALS['fln_push_dentro'])) {
        return;
    }
    $fila = $GLOBALS['fln_avisos_enviar'] ?? [];
    $GLOBALS['fln_avisos_enviar'] = [];
    if (is_array($fila) && $fila !== []) {
        push_enviar_lista($db, $fila);
    }
}

function reservas_que_usam(array $db, string $tipo, int $id): array
{
    $saida = [];
    foreach ($db['transfers'] as $t) {
        $usa = match ($tipo) {
            'motorista' => viagem_do_motorista($t, $id),
            'cliente' => (int) ($t['cliente_id'] ?? 0) === $id,
            'veiculo' => (int) ($t['veiculo_id'] ?? 0) === $id,
            default => false,
        };
        if ($usa) {
            $saida[] = '#' . (int) $t['id'];
        }
    }
    if (count($saida) > 6) {
        $saida = array_merge(array_slice($saida, 0, 6), ['e outras ' . (count($saida) - 6)]);
    }
    return $saida;
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
        if ((string) ($row['id'] ?? '') === $id) {
            return !empty($row['ativo']);
        }
    }
    // Comunicação na criação fica ligada por padrão até o registro existir no banco.
    return $id === 'whatsapp_criacao';
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
    if ($old !== 'concluida' && $new === 'concluida' && function_exists('atendimento_marcar_concluida')) {
        atendimento_marcar_concluida($db, $trip);
    }
}

function handle_post(): void
{
    if ((string) ($_GET['p'] ?? '') === 'chat') {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $action = post('action');
    if ($action === '') {
        return;
    }

    if ($action === 'drive_oauth_start') {
        $volta = href('configuracoes') . '#google-drive';
        if (empty($_SESSION['auth']) || (($_SESSION['role'] ?? '') !== 'admin')) {
            redirect(href('entrar'));
        }
        if (!csrf_ok()) {
            flash('Atualize a página e tente de novo.', 'err');
            redirect($volta);
        }
        if (!drive_oauth_configurado()) {
            flash('Falta configurar GOOGLE_OAUTH_CLIENT_ID e GOOGLE_OAUTH_CLIENT_SECRET no servidor.', 'err');
            redirect($volta);
        }
        redirect(drive_oauth_url());
    }

    if ($action === 'candidatura_motorista') {
        $volta = href('seja-motorista') . '#inscricao';
        if (!csrf_ok()) {
            flash('Não foi possível enviar. Atualize a página e tente de novo.', 'err');
            redirect($volta);
        }
        $ult = (int) ($_SESSION['mot_solic_ts'] ?? 0);
        if ($ult > 0 && (time() - $ult) < 45) {
            flash('Aguarde alguns segundos antes de enviar outra inscrição.', 'err');
            redirect($volta);
        }
        try {
            com_db('candidatura_motorista', static function (array &$db): bool {
                [$err] = executar_acao($db, 'candidatura_motorista');
                if (is_string($err) && $err !== '') {
                    throw new RuntimeException($err);
                }
                return true;
            });
            $_SESSION['mot_solic_ts'] = time();
            flash('Inscrição recebida. Em breve o Matheus entra em contato para marcar a reunião.');
            redirect(href('seja-motorista', ['ok' => '1']) . '#inscricao');
        } catch (Throwable $e) {
            $falha = trim($e->getMessage());
            flash($falha !== '' ? $falha : 'Não foi possível enviar a inscrição.', 'err');
            redirect($volta);
        }
    }

    if ($action === 'login_2fa') {
        $volta = href('entrar', ['etapa' => '2fa']);
        if (!csrf_ok()) {
            flash('Não foi possível continuar. Atualize a página.', 'err');
            redirect(href('entrar'));
        }
        if (login_bloqueado()) {
            flash('Muitas tentativas. Aguarde alguns minutos e tente de novo.', 'err');
            redirect(href('entrar'));
        }
        $pendente = login_pendente_2fa();
        if ($pendente === null) {
            flash('A etapa 2FA expirou. Entre de novo com usuário, senha e código visual.', 'err');
            redirect(href('entrar'));
        }
        $db = load_db();
        $secret = strtoupper((string) ($db['settings']['totp_secret'] ?? ''));
        $codigo = preg_replace('/\D+/', '', post('codigo_2fa')) ?? '';
        if (!autenticacao_2fa_pronta($db) || !totp_confere($secret, $codigo)) {
            login_falhou();
            flash('Código incorreto. Use o número de 6 dígitos que aparece agora no autenticador.', 'err');
            redirect($volta);
        }
        login_aplicar_sessao((string) ($pendente['role'] ?? 'admin'), (int) ($pendente['motorista_id'] ?? 0));
        redirect(href('agenda'));
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
        $db = load_db(false, false, 'casca');
        if (!captcha_confere(post('captcha'))) {
            login_falhou();
            flash('O código de verificação visual está incorreto ou expirou.', 'err');
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
        $usuario = mb_strtolower(clip(post('usuario'), 80));
        $senha = post('senha');
        $entrou = false;
        $role = '';
        $motoristaId = 0;
        $adminUser = mb_strtolower((string) ($db['settings']['usuario'] ?? 'admin'));
        if ($usuario !== '' && $senha !== '' && $usuario === $adminUser && hash_equals((string) $db['settings']['senha'], $senha)) {
            $role = 'admin';
            $motoristaId = 0;
            $entrou = true;
        }
        if (!$entrou && $usuario !== '' && $senha !== '') {
            foreach ($db['motoristas'] as $mot) {
                if ((string) ($mot['status'] ?? '') !== 'ativo') {
                    continue;
                }
                $login = mb_strtolower(trim((string) ($mot['usuario'] ?? '')));
                $email = mb_strtolower(trim((string) ($mot['email'] ?? '')));
                $bate = $usuario === $login || ($email !== '' && $usuario === $email);
                if ($bate && hash_equals((string) ($mot['senha'] ?? ''), $senha)) {
                    $role = 'motorista';
                    $motoristaId = (int) $mot['id'];
                    $entrou = true;
                    break;
                }
            }
        }
        // E-mail de motorista tem prioridade sobre Auth do Supabase.
        if (!$entrou && $usuario !== '' && str_contains($usuario, '@') && supabase_configurado() && supabase_entrar($usuario, $senha)) {
            $role = (string) ($_SESSION['role'] ?? 'admin');
            $motoristaId = (int) ($_SESSION['motorista_id'] ?? 0);
            $entrou = true;
        }
        if ($entrou) {
            if ($role === 'admin' && autenticacao_2fa_pronta($db)) {
                $_SESSION['auth'] = false;
                $_SESSION['role'] = '';
                $_SESSION['motorista_id'] = 0;
                login_pendente_2fa([
                    'role' => 'admin',
                    'motorista_id' => 0,
                    'expira' => time() + 600,
                ]);
                session_regenerate_id(true);
                redirect(href('entrar', ['etapa' => '2fa']));
            }
            login_aplicar_sessao($role !== '' ? $role : 'admin', $motoristaId);
            redirect(href('agenda'));
        }
        login_falhou();
        $resto = max(0, (int) ($_SESSION['login_bloqueio'] ?? 0) - time());
        if ($resto > 0) {
            flash('Muitas tentativas. Aguarde ' . (int) ceil($resto / 60) . ' min e tente de novo.', 'err');
        } else {
            flash('Usuário ou senha incorretos.', 'err');
        }
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

    if (in_array($action, ['push_inscrever', 'push_remover', 'push_testar'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $saida = com_db($action, static function (array &$db) use ($action): array {
                return push_acao($db, $action);
            }, true);
            if (!is_array($saida)) {
                $saida = ['ok' => false, 'erro' => 'Não foi possível atualizar as notificações.'];
            }
            echo json_encode($saida, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('fln push acao: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['ok' => false, 'erro' => 'Não foi possível atualizar as notificações.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($action === 'inbox_abrir_conversao') {
        if (post('confirmar') !== 'sim') {
            flash('A conversão só começa depois das duas confirmações.', 'err');
            redirect(href('solicitacoes'));
        }
        $idSol = (int) post('id');
        $achou = false;
        try {
            foreach (load_db()['inbox'] ?? [] as $linha) {
                if (is_array($linha) && (int) ($linha['id'] ?? 0) === $idSol) {
                    $achou = true;
                    break;
                }
            }
        } catch (Throwable) {
            flash('Não foi possível abrir a solicitação.', 'err');
            redirect(href('solicitacoes'));
        }
        if (!$achou) {
            flash('Solicitação não encontrada.', 'err');
            redirect(href('solicitacoes'));
        }
        $_SESSION['solicitacao_liberada'] = $idSol;
        redirect(href('reservas', ['solicitacao' => $idSol]));
    }

    $acoesExcluir = ['delete_veiculo', 'delete_motorista', 'delete_cliente', 'delete_lancamento', 'delete_grupo', 'delete_nota', 'delete_automacao', 'delete_inbox', 'delete_transfer', 'delete_webhook_dominio', 'apagar_lixo', 'reset_demo', 'remove_logo', 'drive_desconectar'];
    if (in_array($action, $acoesExcluir, true) && post('confirmar') !== 'sim') {
        flash('A exclusão só é concluída depois do Sim na tela de confirmação.', 'err');
        redirect(safe_back(href('agenda')));
    }

    $falha = null;
    $msg = 'Alteração salva.';
    $precisaRascunho = in_array($action, ['add_transfer', 'update_transfer'], true);
    $postSnap = $precisaRascunho ? $_POST : null;
    // Libera o lock da sessão durante a gravação longa no banco (evita travar outras abas).
    session_write_close();
    try {
        com_db($action, static function (array &$db) use ($action, &$falha, &$msg): bool {
            $antes = $db;
            [$err, $texto] = executar_acao($db, $action);
            if ($err === null) {
                $err = integridade_violada($antes, $db);
            }
            if ($err !== null) {
                $falha = $err;
                return false;
            }
            $msg = $texto !== '' ? $texto : 'Alteração salva.';
            return true;
        });
    } catch (BancoIndisponivel $e) {
        $falha = 'Nada foi salvo: ' . $e->getMessage();
    }
    session_start();
    if ($falha !== null) {
        flash($falha, 'err');
        if ($precisaRascunho && is_array($postSnap)) {
            $_SESSION['reserva_draft'] = $postSnap;
            $_SESSION['reserva_draft_erro'] = $falha;
            $params = ['rascunho' => '1'];
            $idRascunho = (int) ($postSnap['id'] ?? 0);
            if ($idRascunho > 0) {
                $params['edit'] = $idRascunho;
            }
            $solRascunho = (int) ($postSnap['solicitacao_id'] ?? 0);
            if ($solRascunho > 0) {
                $params['solicitacao'] = $solRascunho;
            }
            redirect(href('reservas', $params));
        }
        redirect(safe_back(href('agenda')));
    }
    if ($precisaRascunho) {
        unset($_SESSION['reserva_draft'], $_SESSION['reserva_draft_erro']);
        if ((int) post('solicitacao_id') > 0) {
            unset($_SESSION['solicitacao_liberada']);
        }
    }
    flash($msg);
    redirect(safe_back(href('agenda')));
}

function executar_acao(array &$db, string $action): array
{
    $ops = ops_dispatch($db, $action);
    if (is_array($ops)) {
        return [$ops[0], $ops[1]];
    }
    return acao_legada($db, $action);
}

function acao_legada(array &$db, string $action): array
{
    $err = null;
    $msg = '';
    switch ($action) {
        case 'reset_demo':
            if (store_remoto()) {
                $err = 'A restauração de demonstração está desativada: os dados reais ficam no banco e não são apagados.';
                break;
            }
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
            $adminTok = clip(post('uazapi_admin_token'), 512);
            if ($adminTok !== '') {
                $db['settings']['uazapi_admin_token'] = $adminTok;
            }
            $createUrl = rtrim(clip(post('uazapi_create_url'), 220), '/');
            if ($createUrl !== '' && !preg_match('#^https://#i', $createUrl)) {
                $err = 'A URL de criação de instância precisa começar com https://';
                break;
            }
            $db['settings']['uazapi_create_url'] = $createUrl;
            $apiUrl = rtrim(clip(post('uazapi_url'), 160), '/');
            if ($apiUrl !== '') {
                if (!preg_match('#^https://#i', $apiUrl)) {
                    $err = 'A URL da UAZAPI precisa começar com https://';
                    break;
                }
                $db['settings']['uazapi_url'] = $apiUrl;
            }
            $instTok = clip(post('uazapi_token'), 4096);
            if ($instTok !== '') {
                $db['settings']['uazapi_token'] = $instTok;
            }
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
            $usos = reservas_que_usam($db, 'veiculo', $id);
            if ($usos !== []) {
                $err = 'Este veículo está nas reservas ' . implode(', ', $usos) . ' e não pode ser excluído. Marque-o como inativo.';
                break;
            }
            array_splice($db['veiculos'], $i, 1);
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
            $usos = reservas_que_usam($db, 'motorista', $id);
            if ($usos !== []) {
                $err = 'Este motorista está nas reservas ' . implode(', ', $usos) . ' e não pode ser excluído. Marque-o como inativo ou troque o motorista dessas reservas antes.';
                break;
            }
            array_splice($db['motoristas'], $i, 1);
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
            $usos = reservas_que_usam($db, 'cliente', (int) $db['clientes'][$i]['id']);
            if ($usos !== []) {
                $err = 'Este cliente está nas reservas ' . implode(', ', $usos) . ' e não pode ser excluído.';
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
                'id' => proximo_id_transfer($db),
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
                'id' => proximo_id_lancamento($db),
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
        case 'candidatura_motorista':
            $nome = clip(post('nome'), 80);
            $telefone = clip(post('telefone'), 30);
            if ($nome === '' || $telefone === '') {
                $err = 'Informe nome e telefone.';
                break;
            }
            $email = clip(post('email'), 80);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $err = 'Informe um e-mail válido.';
                break;
            }
            $veiculoModelo = clip(post('veiculo_modelo'), 60);
            $veiculoPlaca = mb_strtoupper(clip(post('veiculo_placa'), 12));
            $veiculoAno = clip(post('veiculo_ano'), 4);
            $veiculoCor = clip(post('veiculo_cor'), 30);
            $veiculoLugares = (int) post('veiculo_lugares');
            if ($veiculoLugares < 0 || $veiculoLugares > 20) {
                $veiculoLugares = 0;
            }
            $db['mot_solic'][] = [
                'id' => $db['seq']['mot_solic']++,
                'nome' => $nome,
                'telefone' => $telefone,
                'email' => $email,
                'cidade' => clip(post('cidade'), 60),
                'cpf' => clip(post('cpf'), 20),
                'cnh' => clip(post('cnh'), 20),
                'categoria' => one_of(post('categoria'), ['B', 'C', 'D', 'E'], 'B'),
                'parceria' => one_of(post('parceria'), ['proprio', 'terceirizado', 'ambos'], 'proprio'),
                'veiculo_modelo' => $veiculoModelo,
                'veiculo_placa' => $veiculoPlaca,
                'veiculo_ano' => $veiculoAno,
                'veiculo_cor' => $veiculoCor,
                'veiculo_lugares' => $veiculoLugares,
                'reuniao_pref' => clip(post('reuniao_pref'), 120),
                'mensagem' => clip(post('mensagem'), 500),
                'origem' => $action === 'candidatura_motorista' ? 'site' : 'manual',
                'status' => 'nova',
                'criado_em' => today()->format('Y-m-d'),
            ];
            $msg = $action === 'candidatura_motorista'
                ? 'Inscrição recebida pelo site.'
                : 'Solicitação de motorista registrada.';
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
                    'email' => (string) ($s['email'] ?? ''),
                    'cnh' => (string) ($s['cnh'] ?? ''),
                    'categoria' => (string) ($s['categoria'] ?? 'B'),
                    'cidade' => (string) ($s['cidade'] ?? ''),
                    'uf' => 'SC',
                    'cpf' => (string) ($s['cpf'] ?? ''),
                    'criado_em' => today()->format('Y-m-d'),
                    'status' => 'ativo',
                ]);
                $db['motoristas'][] = $mot;
                $placa = preg_replace('/\s+/', '', (string) ($s['veiculo_placa'] ?? '')) ?? '';
                $modelo = (string) ($s['veiculo_modelo'] ?? '');
                if (strlen($placa) >= 7 && $modelo !== '') {
                    $ano = (int) ($s['veiculo_ano'] ?? 0);
                    $lugares = (int) ($s['veiculo_lugares'] ?? 4);
                    if ($ano < 1990) {
                        $ano = (int) today()->format('Y');
                    }
                    if ($lugares < 1) {
                        $lugares = 4;
                    }
                    $db['veiculos'][] = [
                        'id' => $db['seq']['veiculo']++,
                        'apelido' => $modelo,
                        'placa' => mb_strtoupper($placa),
                        'modelo' => $modelo,
                        'ano' => $ano,
                        'cor' => (string) ($s['veiculo_cor'] ?? ''),
                        'lugares' => $lugares,
                        'motorista_id' => (int) $mot['id'],
                        'status' => 'ativo',
                        'criado_em' => today()->format('Y-m-d'),
                    ];
                }
                $msg = 'Motorista aprovado. Login: ' . $mot['usuario'] . ' · Senha-token (só esta vez): ' . $mot['senha'] . ' · Link: ' . link_motorista($mot);
            } else {
                $msg = 'Solicitação recusada.';
            }
            break;

        default:
            $err = 'Ação desconhecida.';
    }
    return [$err, $msg];
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
ini_set('session.gc_maxlifetime', '43200');
session_set_cookie_params([
    'lifetime' => 43200,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https,
]);
if (store_remoto() && getenv('VERCEL') !== false) {
    session_set_save_handler(new SessaoBanco(), true);
}
$sessionInicio = microtime(true);
session_start();
perf_add('session_ms', (microtime(true) - $sessionInicio) * 1000);

if (store_remoto()) {
    unset($_SESSION['db']);
}

if (perf_enabled()) {
    register_shutdown_function(static function (): void {
        $perf = $GLOBALS['fln_perf'] ?? [];
        $perf['total_ms'] = (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000;
        $perf['route'] = (string) ($_GET['p'] ?? (isset($_GET['cron']) ? 'cron' : 'agenda'));
        $perf['method'] = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        error_log('fln_perf ' . json_encode($perf, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    });
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
