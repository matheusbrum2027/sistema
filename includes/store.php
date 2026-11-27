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

function store_curl(): \CurlHandle
{
    static $ch = null;
    if ($ch instanceof \CurlHandle) {
        return $ch;
    }
    $ch = curl_init();
    $opcoes = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_FORBID_REUSE => false,
    ];
    if (defined('CURL_HTTP_VERSION_2TLS')) {
        $opcoes[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
    }
    curl_setopt_array($ch, $opcoes);
    curl_ca_nativo($ch);
    return $ch;
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
    $ch = store_curl();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim(env_valor('SUPABASE_URL'), '/') . '/rest/v1/rpc/' . $funcao,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $chave,
            'Authorization: Bearer ' . $chave,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $resposta = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

function store_leitura_dir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fln-leitura';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function store_cota_local(string $chave, int $limite): bool
{
    $hora = gmdate('Y-m-d-H');
    if (($GLOBALS['fln_cota_hora'] ?? '') !== $hora) {
        $GLOBALS['fln_cota_hora'] = $hora;
        $GLOBALS['fln_cota'] = [];
    }
    $n = (int) ($GLOBALS['fln_cota'][$chave] ?? 0) + 1;
    $GLOBALS['fln_cota'][$chave] = $n;
    return $n <= $limite;
}

/** Cópia local da última leitura. A mesma versão não volta a sair do banco. */
function store_copia_chave(bool $completo, string $modo, string $de, string $ate, int $idTrip, string $extra = ''): string
{
    return hash('sha256', store_base() . '|' . ($completo ? '1' : '0') . '|' . $modo . '|' . $de . '|' . $ate . '|' . $idTrip . '|' . $extra);
}

function store_copia_mem(): array
{
    if (!isset($GLOBALS['fln_copia']) || !is_array($GLOBALS['fln_copia'])) {
        $GLOBALS['fln_copia'] = [];
    }
    return $GLOBALS['fln_copia'];
}

function store_copia_ler(string $chave): ?array
{
    $mem = store_copia_mem();
    if (isset($mem[$chave]) && is_array($mem[$chave]['dados'] ?? null)) {
        return $mem[$chave];
    }
    $path = store_leitura_dir() . DIRECTORY_SEPARATOR . $chave . '.json';
    if (!is_file($path)) {
        return null;
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json) || !is_array($json['dados'] ?? null) || (int) ($json['versao'] ?? 0) <= 0) {
        return null;
    }
    if (count($GLOBALS['fln_copia']) > 8) {
        array_shift($GLOBALS['fln_copia']);
    }
    $GLOBALS['fln_copia'][$chave] = $json;
    return $json;
}

function store_copia_gravar(string $chave, int $versao, array $dados): void
{
    if ($versao <= 0) {
        return;
    }
    $pacote = ['versao' => $versao, 'dados' => $dados];
    if (count(store_copia_mem()) > 8) {
        array_shift($GLOBALS['fln_copia']);
    }
    $GLOBALS['fln_copia'][$chave] = $pacote;
    $json = json_encode($pacote, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        return;
    }
    $path = store_leitura_dir() . DIRECTORY_SEPARATOR . $chave . '.json';
    $tmp = $path . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
    }
}

function store_notas_alinhar(int $versao): void
{
    if ($versao <= 0) {
        return;
    }
    $dir = store_leitura_dir() . DIRECTORY_SEPARATOR . 'notas';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $marca = $dir . DIRECTORY_SEPARATOR . 'versao';
    $gravada = is_file($marca) ? (int) file_get_contents($marca) : 0;
    if ($gravada === $versao) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $arq) {
        if (is_string($arq)) {
            @unlink($arq);
        }
    }
    $GLOBALS['fln_notas'] = [];
    @file_put_contents($marca, (string) $versao, LOCK_EX);
}

function store_nota_ler(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    if (!isset($GLOBALS['fln_notas']) || !is_array($GLOBALS['fln_notas'])) {
        $GLOBALS['fln_notas'] = [];
    }
    if (isset($GLOBALS['fln_notas'][$id]) && is_array($GLOBALS['fln_notas'][$id])) {
        return $GLOBALS['fln_notas'][$id];
    }
    $path = store_leitura_dir() . DIRECTORY_SEPARATOR . 'notas' . DIRECTORY_SEPARATOR . $id . '.json';
    if (!is_file($path)) {
        return null;
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json) || !is_string($json['obs'] ?? null)) {
        return null;
    }
    if (!is_string($json['interna'] ?? null)) {
        $json['interna'] = '';
    }
    $GLOBALS['fln_notas'][$id] = $json;
    return $json;
}

function store_nota_gravar(int $id, ?string $obs, ?string $interna): void
{
    if ($id <= 0) {
        return;
    }
    if (!isset($GLOBALS['fln_notas']) || !is_array($GLOBALS['fln_notas'])) {
        $GLOBALS['fln_notas'] = [];
    }
    $atual = store_nota_ler($id) ?? ['obs' => '', 'interna' => ''];
    if ($obs !== null) {
        $atual['obs'] = $obs;
    }
    if ($interna !== null) {
        $atual['interna'] = $interna;
        $atual['completa'] = true;
    }
    $GLOBALS['fln_notas'][$id] = $atual;
    $dir = store_leitura_dir() . DIRECTORY_SEPARATOR . 'notas';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $json = json_encode($atual, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        return;
    }
    @file_put_contents($dir . DIRECTORY_SEPARATOR . $id . '.json', $json, LOCK_EX);
}

function store_ler(bool $completo = true, string $modo = '', string $de = '', string $ate = '', int $idTrip = 0, bool $usarVista = true, array $lista = []): ?array
{
    // A casca traz usuário, senha e 2FA. Uma cópia local da versão antiga pedia o código depois de desligar.
    if ($modo === 'casca') {
        $usarVista = false;
    }
    $extra = $lista === [] ? '' : hash('sha256', (string) json_encode($lista));
    $chave = store_copia_chave($completo, $modo, $de, $ate, $idTrip, $extra);
    $copia = $usarVista ? store_copia_ler($chave) : null;
    $vista = (int) ($copia['versao'] ?? 0);
    $pesada = $lista === [] && !$completo && in_array($modo, ['corridas', 'financeiro'], true);
    if ($pesada && $vista <= 0 && !store_cota_local('lista', 40)) {
        throw new BancoIndisponivel('Muitas leituras da lista nesta hora.');
    }
    $r = null;
    if ($lista !== []) {
        $argsLista = $lista;
        $argsLista['p_base'] = store_base();
        if ($vista > 0) {
            $argsLista['p_vista'] = $vista;
        }
        try {
            $r = store_rpc('fln_ler_lista', $argsLista, 12);
        } catch (BancoIndisponivel $e) {
            $msg = $e->getMessage();
            $ausente = str_contains($msg, 'fln_ler_lista') || str_contains($msg, 'PGRST202') || str_contains($msg, 'schema cache');
            if (!$ausente) {
                throw $e;
            }
        }
    }
    if ($r === null && ($completo || $modo === '')) {
        $r = store_rpc($completo ? 'fln_ler' : 'fln_ler_leve', ['p_base' => store_base()], 40);
    } elseif ($r === null) {
        $args = [
            'p_base' => store_base(),
            'p_modo' => $modo,
            'p_de' => $de,
            'p_ate' => $ate,
            'p_id' => $idTrip,
        ];
        if ($vista > 0) {
            $args['p_vista'] = $vista;
        }
        try {
            $r = store_rpc('fln_ler_menu', $args, 25);
        } catch (BancoIndisponivel $e) {
            $msg = $e->getMessage();
            $vistaRecusada = $vista > 0 && (str_contains($msg, 'p_vista') || str_contains($msg, 'PGRST202'));
            $ausente = str_contains($msg, 'fln_ler_menu') || str_contains($msg, 'PGRST202') || str_contains($msg, 'schema cache');
            if ($vistaRecusada) {
                unset($args['p_vista']);
                $r = store_rpc('fln_ler_menu', $args, 25);
            } elseif (!$ausente) {
                throw $e;
            } else {
                $r = store_rpc('fln_ler_leve', ['p_base' => store_base()], 40);
            }
        }
    }
    if (!is_array($r)) {
        throw new BancoIndisponivel('Resposta inválida do banco de dados.');
    }
    if (!empty($r['igual'])) {
        if (is_array($copia['dados'] ?? null)) {
            $versao = (int) ($r['versao'] ?? $vista);
            store_versao($versao);
            store_notas_alinhar($versao);
            return $copia['dados'];
        }
        if ($usarVista) {
            return store_ler($completo, $modo, $de, $ate, $idTrip, false, $lista);
        }
        throw new BancoIndisponivel('Muitas leituras da lista nesta hora.');
    }
    if (!empty($r['limite'])) {
        if (is_array($copia['dados'] ?? null)) {
            store_versao((int) $copia['versao']);
            return $copia['dados'];
        }
        throw new BancoIndisponivel('Muitas leituras da lista nesta hora.');
    }
    $versao = (int) ($r['versao'] ?? 0);
    store_versao($versao);
    store_notas_alinhar($versao);
    $dados = is_array($r['dados'] ?? null) ? $r['dados'] : null;
    if ($dados !== null && $versao > 0 && $modo !== '') {
        store_copia_gravar($chave, $versao, $dados);
    }
    return $dados;
}

/** O banco manda as reservas em colunas para não repetir o nome de cada campo. */
function transfers_expandir(array $pacote): array
{
    $cols = $pacote['c'] ?? [];
    $linhas = $pacote['r'] ?? [];
    if (!is_array($cols) || !is_array($linhas)) {
        return [];
    }
    $saida = [];
    foreach ($linhas as $linha) {
        if (!is_array($linha)) {
            continue;
        }
        $trip = blank_trip((int) ($linha[0] ?? 0));
        unset($trip['obs']);
        foreach ($cols as $i => $nome) {
            if (!is_string($nome) || !array_key_exists($i, $linha) || $linha[$i] === null) {
                continue;
            }
            $trip[$nome] = $linha[$i];
        }
        $trip['id'] = (int) ($trip['id'] ?? 0);
        $saida[] = $trip;
    }
    return $saida;
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

/** Observações ficam fora do documento grande. Uma chamada devolve as da página ou do mês. */
function store_anotacoes(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if ($ids === [] || !store_remoto()) {
        return [];
    }
    $saida = [];
    $faltam = [];
    foreach ($ids as $id) {
        $nota = store_nota_ler($id);
        if ($nota !== null) {
            if (trim($nota['obs']) !== '') {
                $saida[$id] = $nota['obs'];
            }
            continue;
        }
        $faltam[] = $id;
    }
    if ($faltam === []) {
        return $saida;
    }
    try {
        $r = store_rpc('fln_anotacoes', ['p_ids' => $faltam, 'p_base' => store_base()], 8);
    } catch (Throwable) {
        return $saida;
    }
    if (!is_array($r)) {
        return $saida;
    }
    $vieram = [];
    foreach ($r as $id => $texto) {
        if (!is_string($texto)) {
            continue;
        }
        $vieram[(int) $id] = true;
        store_nota_gravar((int) $id, $texto, null);
        if (trim($texto) !== '') {
            $saida[(int) $id] = $texto;
        }
    }
    foreach ($faltam as $id) {
        if (!isset($vieram[$id])) {
            store_nota_gravar($id, '', null);
        }
    }
    return $saida;
}

function trip_com_obs(array $trip): array
{
    $id = (int) ($trip['id'] ?? 0);
    $temObs = trim((string) ($trip['obs'] ?? '')) !== '';
    $temInterna = array_key_exists('obs_interna', $trip);
    if ($temObs && $temInterna) {
        return $trip;
    }
    if ($id <= 0 || !store_remoto()) {
        if (!$temInterna) {
            $trip['obs_interna'] = '';
        }
        return $trip;
    }
    $par = store_nota_ler($id);
    if ($par === null || empty($par['completa'])) {
        try {
            $par = store_rpc('fln_anotacao_par', ['p_id' => $id, 'p_base' => store_base()], 8);
        } catch (Throwable) {
            $par = null;
        }
        if (is_array($par) && empty($par['limite'])) {
            store_nota_gravar($id, (string) ($par['obs'] ?? ''), (string) ($par['interna'] ?? ''));
        }
    }
    if (!is_array($par) || !empty($par['limite'])) {
        if (!$temObs) {
            $carregouObs = false;
            try {
                $lida = store_rpc('fln_anotacao', ['p_id' => $id, 'p_base' => store_base()], 8);
                if (is_string($lida)) {
                    $trip['obs'] = $lida;
                    $carregouObs = true;
                }
            } catch (Throwable) {
                $carregouObs = false;
            }
            if (!$carregouObs) {
                $trip['obs_falhou'] = true;
                if (!array_key_exists('obs', $trip)) {
                    $trip['obs'] = '';
                }
            }
        }
        if (!$temInterna) {
            $trip['obs_interna'] = '';
        }
        $trip['obs_interna_falhou'] = true;
        return $trip;
    }
    if (!$temObs) {
        $trip['obs'] = (string) ($par['obs'] ?? '');
    }
    if (!$temInterna) {
        $trip['obs_interna'] = (string) ($par['interna'] ?? '');
    }
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
    ], 22);
    if ($nova < 0) {
        throw new BancoConflito('Outra pessoa salvou ao mesmo tempo.');
    }
    store_versao($nova);
}

/** Datas do formulário, com dois dias de folga para o conflito de motorista. */
function store_janela_gravacao(): array
{
    $datas = [];
    $andar = static function (mixed $valor) use (&$andar, &$datas): void {
        if (is_array($valor)) {
            foreach ($valor as $item) {
                $andar($item);
            }
            return;
        }
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            $datas[] = $valor;
        }
    };
    $andar($_POST);
    $tz = new DateTimeZone('America/Sao_Paulo');
    if ($datas === []) {
        $datas[] = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }
    sort($datas);
    $de = new DateTimeImmutable($datas[0], $tz);
    $ate = new DateTimeImmutable($datas[array_key_last($datas)], $tz);
    return [$de->modify('-2 days')->format('Y-m-d'), $ate->modify('+2 days')->format('Y-m-d')];
}

/**
 * Casca, a reserva inteira e só a agenda dos dias vizinhos.
 * A lista completa fica no banco.
 */
function store_db_para_gravar(int $idTrip): array
{
    [$de, $ate] = store_janela_gravacao();
    $r = store_rpc('fln_ler_gravacao', [
        'p_base' => store_base(),
        'p_id' => $idTrip,
        'p_de' => $de,
        'p_ate' => $ate,
    ], 20);
    if (!is_array($r) || !is_array($r['dados'] ?? null)) {
        throw new BancoIndisponivel('Leitura enxuta indisponível.');
    }
    store_versao((int) ($r['versao'] ?? 0));
    $dados = $r['dados'];
    if (!isset($dados['settings'], $dados['motoristas']) || !is_array($dados['settings']) || !is_array($dados['motoristas'])) {
        throw new BancoIndisponivel('Leitura enxuta incompleta.');
    }
    $dados['transfers'] = [];
    $dados['lancamentos'] = [];
    unset($dados['_menu']);
    $GLOBALS['fln_ids_gravacao'] = [];
    $db = persistir_acesso_motorista(normalize_db($dados));
    if (!isset($db['seq']) || !is_array($db['seq'])) {
        $db['seq'] = [];
    }
    $trips = [];
    foreach (is_array($r['agenda'] ?? null) ? $r['agenda'] : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row['_eco'] = 1;
        $trips[] = $row;
    }
    $full = is_array($r['trip'] ?? null) ? $r['trip'] : null;
    if ($full !== null) {
        unset($full['_eco']);
        $colocou = false;
        $idFull = (int) ($full['id'] ?? 0);
        foreach ($trips as $i => $row) {
            if ((int) ($row['id'] ?? 0) === $idFull) {
                $trips[$i] = $full;
                $colocou = true;
                break;
            }
        }
        if (!$colocou) {
            $trips[] = $full;
        }
        if ($idFull > 0) {
            $GLOBALS['fln_ids_gravacao'][] = $idFull;
        }
    }
    $db['transfers'] = $trips;
    $db['lancamentos'] = [];
    foreach (is_array($r['lancamentos'] ?? null) ? $r['lancamentos'] : [] as $row) {
        if (is_array($row)) {
            $db['lancamentos'][] = $row;
        }
    }
    $GLOBALS['fln_menu'] = true;
    $db['seq']['transfer'] = max((int) ($db['seq']['transfer'] ?? 1), (int) ($r['max_transfer'] ?? 0) + 1);
    $db['seq']['lancamento'] = max((int) ($db['seq']['lancamento'] ?? 1), (int) ($r['max_lancamento'] ?? 0) + 1);
    return $db;
}

/** Grava só as reservas alteradas e os lançamentos delas. O restante permanece. */
function store_gravar_reservas(array $db, string $acao): void
{
    $trips = [];
    $idsGravados = [];
    foreach ($db['transfers'] ?? [] as $trip) {
        if (!is_array($trip) || !empty($trip['_eco'])) {
            continue;
        }
        unset($trip['_eco']);
        $trips[] = $trip;
        $idTrip = (int) ($trip['id'] ?? 0);
        if ($idTrip > 0) {
            $idsGravados[] = $idTrip;
        }
    }
    $apagar = [];
    foreach (is_array($GLOBALS['fln_ids_gravacao'] ?? null) ? $GLOBALS['fln_ids_gravacao'] : [] as $idCarregado) {
        $idCarregado = (int) $idCarregado;
        if ($idCarregado > 0 && !in_array($idCarregado, $idsGravados, true)) {
            $apagar[] = $idCarregado;
        }
    }
    if (($trips === [] && $apagar === []) || count($trips) > 40 || count($apagar) > 3) {
        throw new BancoIndisponivel('A gravação da reserva não fechou. Tente de novo.');
    }
    $lancamentos = [];
    foreach ($db['lancamentos'] ?? [] as $row) {
        if (!is_array($row) || (string) ($row['papel'] ?? '') === '_teto') {
            continue;
        }
        $lancamentos[] = $row;
    }
    $dados = $db;
    unset($dados['transfers'], $dados['lancamentos'], $dados['_menu']);
    $nova = (int) store_rpc('fln_gravar_reservas', [
        'p_versao' => store_versao(),
        'p_dados' => $dados,
        'p_trips' => $trips,
        'p_lancamentos' => $lancamentos,
        'p_acao' => $acao,
        'p_usuario' => store_quem(),
        'p_base' => store_base(),
        'p_apagar' => $apagar,
    ], 22);
    if ($nova < 0) {
        throw new BancoConflito('Outra pessoa salvou ao mesmo tempo.');
    }
    store_versao($nova);
}

/**
 * Lê o estado atual, aplica $alterar e grava. Se outra gravação ocorrer no meio,
 * relê e aplica de novo; $alterar não pode ter efeito fora de $db.
 */
function com_db(string $acao, callable $alterar, bool $leve = false): mixed
{
    // Chat, push e acesso não podem reenviar as 3 mil reservas: isso estoura a cota.
    if (!$leve && preg_match('/^(chat_|push_|acesso_|campanha_|2fa_)/', $acao) === 1) {
        $leve = true;
    }
    $cadastro = [
        'save_contrato', 'save_conta', 'save_comprovante', 'save_assinatura', 'save_atendente',
        'save_pagamentos', 'save_operacao', 'save_notificacoes', 'remove_logo',
        'add_automacao', 'update_automacao', 'delete_automacao', 'save_comunicador_autos',
        'add_webhook_dominio', 'update_webhook_dominio', 'delete_webhook_dominio', 'delete_inbox',
        'conectar_whatsapp', 'excluir_instancia_whatsapp', 'status_whatsapp',
        'sheets_sync_now', 'drive_backup_now', 'drive_desconectar', 'drive_oauth_salvar', 'drive_cron',
        'add_cliente', 'update_cliente', 'add_veiculo', 'update_veiculo',
        'add_nota', 'delete_nota',
        'add_motorista', 'update_motorista', 'reset_senha_motorista', 'candidatura_motorista', 'marca_fln',
    ];
    if (!$leve && in_array($acao, $cadastro, true)) {
        $leve = true;
    }
    $reserva = in_array($acao, [
        'add_transfer', 'update_transfer', 'set_transfer_status', 'delete_transfer',
        'receber_transfer', 'faturar_transfer', 'comunicar_reserva',
    ], true);
    for ($tentativa = 0; $tentativa < 4; $tentativa++) {
        $enxuta = false;
        if ($reserva) {
            try {
                $db = store_db_para_gravar((int) post('id'));
                $enxuta = true;
            } catch (Throwable $e) {
                $msgLeitura = $e->getMessage();
                if (str_contains($msgLeitura, 'cota de leitura')) {
                    throw $e instanceof BancoIndisponivel ? $e : new BancoIndisponivel($msgLeitura);
                }
                if (str_contains($msgLeitura, 'statement timeout') || str_contains($msgLeitura, 'canceling statement')) {
                    usleep(200000 * ($tentativa + 1));
                    continue;
                }
                $db = load_db(true, true);
            }
        } else {
            $db = $leve ? load_db(true, false, 'casca') : load_db(true, true);
        }
        $resultado = $alterar($db);
        if ($resultado === false) {
            return false;
        }
        $gravar = $db;
        if ($leve) {
            unset($gravar['transfers'], $gravar['lancamentos'], $gravar['_menu']);
        }
        try {
            if ($enxuta) {
                store_gravar_reservas($gravar, $acao);
            } else {
                save_db($gravar, $acao);
            }
            return $resultado;
        } catch (BancoConflito) {
            db_request_cache(null, true);
            usleep(40000 * ($tentativa + 1));
        } catch (Throwable $e) {
            $msgGrava = $e->getMessage();
            if (str_contains($msgGrava, 'statement timeout') || str_contains($msgGrava, 'canceling statement')) {
                db_request_cache(null, true);
                usleep(200000 * ($tentativa + 1));
                continue;
            }
            throw $e;
        }
    }
    throw new BancoIndisponivel('O banco de dados está ocupado. Tente de novo.');
}

final class SessaoBanco implements SessionHandlerInterface
{
    private bool $leituraOk = false;
    private string $lido = '';

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
            $this->lido = $dados;
            return $dados;
        } catch (Throwable) {
            // Não devolve sessão vazia: o PHP gravaria isso e apagaria o login.
            $this->leituraOk = false;
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        if (!$this->leituraOk || $data === $this->lido) {
            return true;
        }
        try {
            store_rpc('fln_sessao_gravar', ['p_id' => $id, 'p_dados' => $data, 'p_segundos' => (int) ini_get('session.gc_maxlifetime')], 8);
            $this->lido = $data;
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
