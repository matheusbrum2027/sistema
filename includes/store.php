<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

final class BancoIndisponivel extends RuntimeException
{
}

final class BancoConflito extends RuntimeException
{
}

function curl_ca_nativo($ch): void
{
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
}

function store_remoto(): bool
{
    return supabase_configurado() && strlen(env_valor('FLN_SEGREDO')) >= 32;
}

function store_rpc(string $funcao, array $args, int $timeout = 12): mixed
{
    $inicio = microtime(true);
    if (!function_exists('curl_init')) {
        throw new BancoIndisponivel('O PHP deste servidor não tem cURL.');
    }
    $corpo = json_encode(
        ['p_segredo' => env_valor('FLN_SEGREDO')] + $args,
        JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($corpo === false) {
        throw new BancoIndisponivel('Não foi possível preparar os dados para gravação.');
    }
    $chave = env_valor('SUPABASE_ANON_KEY');
    $ch = curl_init(rtrim(env_valor('SUPABASE_URL'), '/') . '/rest/v1/rpc/' . $funcao);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $chave,
            'Authorization: Bearer ' . $chave,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    curl_ca_nativo($ch);
    $resposta = null;
    $code = 0;
    for ($tentativa = 0; $tentativa < 2; $tentativa++) {
        $resposta = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resposta !== false && $code > 0 && $code < 500) {
            break;
        }
        usleep(80000);
    }
    curl_close($ch);
    perf_add('store_rpc_ms', (microtime(true) - $inicio) * 1000, strlen((string) $resposta));
    perf_count('store_rpc_count');
    perf_count('store_rpc_' . preg_replace('/[^a-z0-9_]+/i', '_', $funcao));
    if ($resposta === false || $code === 0) {
        throw new BancoIndisponivel('Sem conexão com o banco de dados.');
    }
    $json = json_decode((string) $resposta, true);
    if ($code < 200 || $code >= 300) {
        $detalhe = is_array($json) ? (string) ($json['message'] ?? '') : '';
        throw new BancoIndisponivel($detalhe !== '' ? $detalhe : 'O banco de dados respondeu HTTP ' . $code . '.');
    }
    return $json;
}

function store_versao(?int $nova = null): int
{
    static $versao = 0;
    if ($nova !== null) {
        $versao = $nova;
    }
    return $versao;
}

function store_base(): int
{
    return env_valor('FLN_BASE', '1') === '2' ? 2 : 1;
}

function store_ler(bool $completo = true): ?array
{
    $r = store_rpc($completo ? 'fln_ler' : 'fln_ler_leve', ['p_base' => store_base()], 40);
    if (!is_array($r)) {
        throw new BancoIndisponivel('Resposta inválida do banco de dados.');
    }
    store_versao((int) ($r['versao'] ?? 0));
    return is_array($r['dados'] ?? null) ? $r['dados'] : null;
}

function store_quem(): string
{
    $papel = (string) ($_SESSION['role'] ?? '');
    if ($papel === 'motorista') {
        return 'motorista#' . (int) ($_SESSION['motorista_id'] ?? 0);
    }
    return $papel !== '' ? $papel : 'sistema';
}

function store_avisos(int $desde): array
{
    $r = store_rpc('fln_avisos', ['p_desde' => $desde, 'p_base' => store_base()], 12);
    if (!is_array($r)) {
        throw new BancoIndisponivel('Resposta inválida dos avisos.');
    }
    return $r;
}

function store_chat_banco(): array
{
    $r = store_rpc('fln_chat_resumo', ['p_base' => store_base()], 12);
    if (!is_array($r)) {
        throw new BancoIndisponivel('Resposta inválida do chat.');
    }
    return [
        'settings' => is_array($r['settings'] ?? null) ? $r['settings'] : [],
        'chat' => is_array($r['chat'] ?? null) ? $r['chat'] : [],
    ];
}

function store_mapa(): array
{
    $r = store_rpc('fln_mapa', ['p_base' => store_base()], 12);
    return is_array($r) ? $r : [];
}

function store_midia(string $chave): string
{
    $r = store_rpc('fln_midia', ['p_base' => store_base(), 'p_chave' => $chave], 15);
    return is_string($r) ? $r : '';
}

function store_anotacao(int $id): string
{
    if ($id <= 0 || !store_remoto()) {
        return '';
    }
    try {
        $r = store_rpc('fln_anotacao', ['p_id' => $id, 'p_base' => store_base()], 8);
    } catch (Throwable) {
        return '';
    }
    return is_string($r) ? $r : '';
}

/** A observação fica fora do documento grande. Busca só a reserva aberta. */
function trip_com_obs(array $trip): array
{
    if (array_key_exists('obs', $trip)) {
        return $trip;
    }
    $trip['obs'] = store_anotacao((int) ($trip['id'] ?? 0));
    return $trip;
}

function store_gravar(array $db, string $acao): void
{
    $nova = (int) store_rpc('fln_salvar', [
        'p_versao' => store_versao(),
        'p_dados' => $db,
        'p_acao' => $acao,
        'p_usuario' => store_quem(),
        'p_base' => store_base(),
    ], 90);
    if ($nova < 0) {
        throw new BancoConflito('Outra pessoa salvou ao mesmo tempo.');
    }
    store_versao($nova);
}

/**
 * Lê o estado atual, aplica $alterar e grava. Se outra gravação ocorrer no meio,
 * relê e aplica de novo; $alterar não pode ter efeito fora de $db.
 */
function com_db(string $acao, callable $alterar): mixed
{
    for ($tentativa = 0; $tentativa < 4; $tentativa++) {
        $db = load_db(true, true);
        $resultado = $alterar($db);
        if ($resultado === false) {
            return false;
        }
        try {
            save_db($db, $acao);
            return $resultado;
        } catch (BancoConflito) {
            db_request_cache(null, true);
            usleep(40000 * ($tentativa + 1));
        }
    }
    throw new BancoIndisponivel('O banco de dados está ocupado. Tente de novo.');
}

final class SessaoBanco implements SessionHandlerInterface
{
    private bool $leituraOk = false;

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $dados = (string) store_rpc('fln_sessao_ler', ['p_id' => $id], 8);
            $this->leituraOk = true;
            return $dados;
        } catch (Throwable) {
            // Não devolve sessão vazia: o PHP gravaria isso e apagaria o login.
            $this->leituraOk = false;
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        if (!$this->leituraOk) {
            return true;
        }
        try {
            store_rpc('fln_sessao_gravar', ['p_id' => $id, 'p_dados' => $data, 'p_segundos' => (int) ini_get('session.gc_maxlifetime')], 8);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            store_rpc('fln_sessao_apagar', ['p_id' => $id], 8);
        } catch (Throwable) {
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }
}
