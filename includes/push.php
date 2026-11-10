<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function push_openssl_pronto(): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;
    $origem = __DIR__ . DIRECTORY_SEPARATOR . 'openssl.cnf';
    if (is_file($origem)) {
        putenv('OPENSSL_CONF=' . $origem);
    }
}

function push_b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function push_b64url_dec(string $texto): string
{
    $texto = strtr($texto, '-_', '+/');
    $falta = strlen($texto) % 4;
    if ($falta > 0) {
        $texto .= str_repeat('=', 4 - $falta);
    }
    $bin = base64_decode($texto, true);
    return $bin === false ? '' : $bin;
}

function push_der_raw(string $der): string
{
    $offset = 0;
    $ler = static function (string $der, int &$offset): int {
        $b = ord($der[$offset++]);
        if (($b & 0x80) === 0) {
            return $b;
        }
        $n = $b & 0x7f;
        $v = 0;
        for ($i = 0; $i < $n; $i++) {
            $v = ($v << 8) | ord($der[$offset++]);
        }
        return $v;
    };
    if (ord($der[$offset++]) !== 0x30) {
        throw new RuntimeException('Assinatura VAPID inválida.');
    }
    $ler($der, $offset);
    if (ord($der[$offset++]) !== 0x02) {
        throw new RuntimeException('Assinatura VAPID inválida.');
    }
    $rLen = $ler($der, $offset);
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;
    if (ord($der[$offset++]) !== 0x02) {
        throw new RuntimeException('Assinatura VAPID inválida.');
    }
    $sLen = $ler($der, $offset);
    $s = substr($der, $offset, $sLen);
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

function push_ec_nova()
{
    push_openssl_pronto();
    $chave = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'private_key_bits' => 2048,
    ]);
    return $chave === false ? null : $chave;
}

function push_vapid_garantir(array &$db): void
{
    $publica = (string) ($db['settings']['vapid_public'] ?? '');
    $privada = (string) ($db['settings']['vapid_private'] ?? '');
    if ($publica !== '' && $privada !== '' && strlen(push_b64url_dec($publica)) === 65) {
        return;
    }
    $chave = push_ec_nova();
    if ($chave === null) {
        return;
    }
    $det = openssl_pkey_get_details($chave);
    if (!is_array($det) || empty($det['ec']['x']) || empty($det['ec']['y'])) {
        return;
    }
    $bruta = "\x04"
        . str_pad((string) $det['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad((string) $det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $pem = '';
    if (!openssl_pkey_export($chave, $pem) || $pem === '') {
        return;
    }
    $db['settings']['vapid_public'] = push_b64url($bruta);
    $db['settings']['vapid_private'] = base64_encode($pem);
    $GLOBALS['fln_vapid_novo'] = true;
}

function push_pem_publica(string $bruta65): string
{
    $cabeca = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $cabeca . $bruta65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function push_vapid_jwt(array $db, string $endpoint): ?array
{
    $publica = push_b64url_dec((string) ($db['settings']['vapid_public'] ?? ''));
    $pem = base64_decode((string) ($db['settings']['vapid_private'] ?? ''), true);
    if (strlen($publica) !== 65 || !is_string($pem) || $pem === '') {
        return null;
    }
    $partes = parse_url($endpoint);
    $origem = (string) ($partes['scheme'] ?? '') . '://' . (string) ($partes['host'] ?? '');
    if (($partes['scheme'] ?? '') !== 'https' || ($partes['host'] ?? '') === '') {
        return null;
    }
    $email = trim((string) ($db['settings']['email'] ?? ''));
    $sub = filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $email : 'mailto:admin@transferfln.app';
    $cabeca = push_b64url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $corpo = push_b64url((string) json_encode(['aud' => $origem, 'exp' => time() + 12 * 3600, 'sub' => $sub], JSON_UNESCAPED_SLASHES));
    $dados = $cabeca . '.' . $corpo;
    $assinatura = '';
    if (!openssl_sign($dados, $assinatura, $pem, OPENSSL_ALGO_SHA256)) {
        return null;
    }
    return ['t' => $dados . '.' . push_b64url(push_der_raw($assinatura)), 'k' => push_b64url($publica)];
}

function push_cifrar(string $texto, string $p256dh, string $auth): string
{
    $uaPublica = push_b64url_dec($p256dh);
    $segredo = push_b64url_dec($auth);
    if (strlen($uaPublica) !== 65 || strlen($segredo) < 16) {
        throw new RuntimeException('Inscrição de notificação inválida.');
    }
    $local = push_ec_nova();
    if ($local === null) {
        throw new RuntimeException('OpenSSL não gerou a chave do aviso.');
    }
    $det = openssl_pkey_get_details($local);
    $asPublica = "\x04"
        . str_pad((string) $det['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad((string) $det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $comum = openssl_pkey_derive(push_pem_publica($uaPublica), $local);
    if (!is_string($comum) || $comum === '') {
        throw new RuntimeException('Não foi possível preparar o aviso.');
    }
    $comum = str_pad(substr($comum, -32), 32, "\x00", STR_PAD_LEFT);
    $sal = random_bytes(16);
    $ikm = hash_hkdf('sha256', $comum, 32, "WebPush: info\x00" . $uaPublica . $asPublica, $segredo);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $sal);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $sal);
    $claro = $texto . "\x02";
    $tag = '';
    $cifrado = openssl_encrypt($claro, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if (!is_string($cifrado) || strlen($tag) !== 16) {
        throw new RuntimeException('Falha ao proteger o aviso.');
    }
    return $sal . pack('N', 4096) . chr(65) . $asPublica . $cifrado . $tag;
}

function push_nome_aparelho(string $ua): string
{
    $so = 'Computador';
    if (stripos($ua, 'Android') !== false) {
        $so = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $so = 'iPhone';
    } elseif (stripos($ua, 'Windows') !== false) {
        $so = 'Windows';
    } elseif (stripos($ua, 'Mac OS') !== false) {
        $so = 'Mac';
    }
    $nav = 'Navegador';
    if (stripos($ua, 'Edg/') !== false) {
        $nav = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS/') !== false) {
        $nav = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $nav = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $nav = 'Safari';
    }
    return $so . ' · ' . $nav;
}

function aviso_ligado(array $db, string $tipo): bool
{
    $chave = match ($tipo) {
        'reserva' => 'notif_reserva',
        'solicitacao' => 'notif_solicitacao',
        'cancelamento' => 'notif_cancelamento',
        'status' => 'notif_status',
        default => '',
    };
    return $chave !== '' && !empty($db['settings'][$chave]);
}

function aviso_resumo_reserva(array $trip): string
{
    $data = trim((string) ($trip['data'] ?? ''));
    $quando = ($data !== '' && function_exists('br_date') ? br_date($data) : $data);
    $hora = trim((string) ($trip['hora'] ?? ''));
    $trecho = trim((string) ($trip['origem'] ?? '') . ' → ' . (string) ($trip['destino'] ?? ''));
    return trim((string) ($trip['cliente'] ?? 'Cliente') . ' · ' . $trecho . ' · ' . trim($quando . ' ' . $hora), " ·");
}

function aviso_publicar(array &$db, string $tipo, string $titulo, string $corpo, string $url, string $audiencia = 'admin', array $motoristas = []): void
{
    $motoristas = array_values(array_unique(array_filter(array_map('intval', $motoristas), static fn(int $id): bool => $id > 0)));
    if ($audiencia === 'motorista') {
        if ($motoristas === []) {
            return;
        }
    } elseif (!aviso_ligado($db, $tipo)) {
        return;
    }
    if (!isset($db['seq']) || !is_array($db['seq'])) {
        $db['seq'] = [];
    }
    $id = (int) ($db['seq']['aviso'] ?? 1);
    $db['seq']['aviso'] = $id + 1;
    $item = [
        'id' => $id,
        'tipo' => $tipo,
        'titulo' => clip($titulo, 90),
        'corpo' => clip($corpo, 180),
        'url' => clip($url, 180),
        'som' => !array_key_exists('notif_som', $db['settings']) || !empty($db['settings']['notif_som']),
        'vibrar' => !array_key_exists('notif_vibrar', $db['settings']) || !empty($db['settings']['notif_vibrar']),
        'em' => date('Y-m-d H:i'),
    ];
    if ($audiencia === 'motorista') {
        $item['audiencia'] = 'motorista';
        $item['motoristas'] = $motoristas;
    }
    if (!isset($db['avisos']) || !is_array($db['avisos'])) {
        $db['avisos'] = [];
    }
    $db['avisos'][] = $item;
    if (count($db['avisos']) > 40) {
        $db['avisos'] = array_slice($db['avisos'], -40);
    }
    $GLOBALS['fln_avisos_enviar'][] = $item;
}

function ids_motoristas_viagem(array $trip): array
{
    $ids = [];
    foreach (['motorista_id', 'motorista_volta_id'] as $chave) {
        $id = (int) ($trip[$chave] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    foreach ($trip['extras'] ?? [] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $eu = (($_SESSION['role'] ?? '') === 'motorista') ? (int) ($_SESSION['motorista_id'] ?? 0) : 0;
    if ($eu > 0) {
        unset($ids[$eu]);
    }
    return array_values($ids);
}

function aviso_viagem_motoristas(array &$db, array $trip, string $titulo): void
{
    $ids = ids_motoristas_viagem($trip);
    if ($ids === []) {
        return;
    }
    aviso_publicar(
        $db,
        'reserva',
        $titulo,
        aviso_resumo_reserva($trip),
        'index.php?p=agenda&ver=' . (int) ($trip['id'] ?? 0),
        'motorista',
        $ids
    );
}

function aviso_motoristas_atribuidos(array &$db, array $antes, array $depois): void
{
    $tinha = [];
    foreach (['motorista_id', 'motorista_volta_id'] as $chave) {
        $id = (int) ($antes[$chave] ?? 0);
        if ($id > 0) {
            $tinha[$id] = true;
        }
    }
    foreach ($antes['extras'] ?? [] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $tinha[$id] = true;
        }
    }
    $novos = [];
    foreach (ids_motoristas_viagem($depois) as $id) {
        if (!isset($tinha[$id])) {
            $novos[] = $id;
        }
    }
    if ($novos === []) {
        return;
    }
    aviso_publicar(
        $db,
        'reserva',
        'Nova viagem #' . (int) ($depois['id'] ?? 0),
        aviso_resumo_reserva($depois),
        'index.php?p=agenda&ver=' . (int) ($depois['id'] ?? 0),
        'motorista',
        $novos
    );
}

function aviso_item_para(array $item, string $papel, int $motoristaId): bool
{
    $audiencia = (string) ($item['audiencia'] ?? 'admin');
    if ($papel === 'motorista') {
        $lista = array_map('intval', is_array($item['motoristas'] ?? null) ? $item['motoristas'] : []);
        return $audiencia === 'motorista' && $motoristaId > 0 && in_array($motoristaId, $lista, true);
    }
    return $audiencia !== 'motorista';
}

function push_aparelho_recebe(array $ap, array $item): bool
{
    $papel = (string) ($ap['papel'] ?? 'admin');
    $mid = (int) ($ap['motorista_id'] ?? 0);
    if ($papel === 'motorista' || $mid > 0) {
        return aviso_item_para($item, 'motorista', $mid);
    }
    return aviso_item_para($item, 'admin', 0);
}

function aviso_reservas_novas(array &$db, array $trips): void
{
    $trips = array_values(array_filter($trips, 'is_array'));
    if ($trips === []) {
        return;
    }
    if (count($trips) === 1) {
        $t = $trips[0];
        aviso_publicar($db, 'reserva', 'Nova reserva #' . (int) $t['id'], aviso_resumo_reserva($t), 'index.php?p=agenda&ver=' . (int) $t['id']);
        aviso_viagem_motoristas($db, $t, 'Nova viagem #' . (int) $t['id']);
        return;
    }
    $nums = array_map(static fn(array $t): string => '#' . (int) $t['id'], $trips);
    aviso_publicar($db, 'reserva', 'Novas reservas ' . implode(' e ', $nums), aviso_resumo_reserva($trips[0]), 'index.php?p=reservas');
    foreach ($trips as $t) {
        aviso_viagem_motoristas($db, $t, 'Nova viagem #' . (int) $t['id']);
    }
}

function aviso_solicitacao(array &$db, array $registro): void
{
    $corpo = trim((string) ($registro['cliente'] ?? 'Cliente') . ' · ' . (string) ($registro['origem'] ?? '') . ' → ' . (string) ($registro['destino'] ?? ''));
    aviso_publicar($db, 'solicitacao', 'Nova solicitação #' . (int) ($registro['id'] ?? 0), $corpo, 'index.php?p=solicitacoes');
}

function aviso_mudanca_status(array &$db, array $trip, string $antes, string $depois): void
{
    if ($antes === $depois) {
        return;
    }
    $url = 'index.php?p=agenda&ver=' . (int) ($trip['id'] ?? 0);
    if ($depois === 'cancelada') {
        aviso_publicar($db, 'cancelamento', 'Reserva #' . (int) $trip['id'] . ' cancelada', aviso_resumo_reserva($trip), $url);
        aviso_viagem_motoristas($db, $trip, 'Viagem #' . (int) $trip['id'] . ' cancelada');
        return;
    }
    aviso_publicar($db, 'status', 'Reserva #' . (int) $trip['id'] . ' · ' . status_label($depois), aviso_resumo_reserva($trip), $url);
    aviso_viagem_motoristas($db, $trip, 'Viagem #' . (int) $trip['id'] . ' · ' . status_label($depois));
}

function push_enviar_lista(array $db, array $itens): void
{
    if (!empty($GLOBALS['fln_push_dentro']) || $itens === [] || !function_exists('curl_multi_init')) {
        return;
    }
    $aparelhos = array_values(array_filter($db['push'] ?? [], 'is_array'));
    if ($aparelhos === []) {
        return;
    }
    $GLOBALS['fln_push_dentro'] = true;
    $mortos = [];
    try {
        $mh = curl_multi_init();
        if ($mh === false) {
            return;
        }
        $handles = [];
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            $payload = (string) json_encode([
                'id' => $item['id'] ?? 0,
                'titulo' => (string) ($item['titulo'] ?? 'Transfer FLN'),
                'corpo' => (string) ($item['corpo'] ?? ''),
                'url' => (string) ($item['url'] ?? 'index.php?p=agenda'),
                'som' => !empty($item['som']),
                'vibrar' => !empty($item['vibrar']),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($aparelhos as $ap) {
                if (!push_aparelho_recebe($ap, $item)) {
                    continue;
                }
                // APK usa worker nativo (p=aviso_apk); VAPID só para Chrome/PWA.
                if ((string) ($ap['kind'] ?? 'web') === 'apk') {
                    continue;
                }
                $endpoint = (string) ($ap['endpoint'] ?? '');
                if ($endpoint === '' || str_starts_with($endpoint, 'apk://')) {
                    continue;
                }
                $jwt = push_vapid_jwt($db, $endpoint);
                if ($jwt === null) {
                    continue;
                }
                try {
                    $corpo = push_cifrar($payload, (string) ($ap['p256dh'] ?? ''), (string) ($ap['auth'] ?? ''));
                } catch (Throwable) {
                    continue;
                }
                $ch = curl_init($endpoint);
                if ($ch === false) {
                    continue;
                }
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $corpo,
                    CURLOPT_HTTPHEADER => [
                        'TTL: 86400',
                        'Urgency: high',
                        'Content-Encoding: aes128gcm',
                        'Content-Type: application/octet-stream',
                        'Content-Length: ' . strlen($corpo),
                        'Authorization: vapid t=' . $jwt['t'] . ', k=' . $jwt['k'],
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = ['ch' => $ch, 'id' => (string) ($ap['id'] ?? '')];
            }
        }
        $rodando = null;
        do {
            $estado = curl_multi_exec($mh, $rodando);
            if ($rodando) {
                curl_multi_select($mh, 1.0);
            }
        } while ($rodando && $estado === CURLM_OK);
        foreach ($handles as $h) {
            $codigo = (int) curl_getinfo($h['ch'], CURLINFO_RESPONSE_CODE);
            if ($codigo === 404 || $codigo === 410) {
                $mortos[] = $h['id'];
            } elseif ($codigo !== 201 && $codigo !== 200) {
                error_log('fln push http ' . $codigo . ' aparelho ' . $h['id']);
            }
            curl_multi_remove_handle($mh, $h['ch']);
            curl_close($h['ch']);
        }
        curl_multi_close($mh);
        if ($mortos !== []) {
            $db['push'] = array_values(array_filter(
                $db['push'] ?? [],
                static fn(mixed $ap): bool => is_array($ap) && !in_array((string) ($ap['id'] ?? ''), $mortos, true)
            ));
            save_db($db, 'push_limpar');
        }
    } catch (Throwable $e) {
        error_log('fln push: ' . $e->getMessage());
    } finally {
        $GLOBALS['fln_push_dentro'] = false;
    }
}

function push_acao(array &$db, string $action): array
{
    if (!isset($db['push']) || !is_array($db['push'])) {
        $db['push'] = [];
    }
    if ($action === 'push_remover') {
        $id = preg_replace('/[^a-f0-9]/', '', strtolower(post('id'))) ?? '';
        $endpoint = trim(post('endpoint'));
        if ($id === '' && $endpoint === '') {
            return ['ok' => false, 'erro' => 'Aparelho não informado.'];
        }
        $db['push'] = array_values(array_filter(
            $db['push'],
            static function (mixed $ap) use ($id, $endpoint): bool {
                if (!is_array($ap)) {
                    return false;
                }
                if ($id !== '' && (string) ($ap['id'] ?? '') === $id) {
                    return false;
                }
                if ($endpoint !== '' && (string) ($ap['endpoint'] ?? '') === $endpoint) {
                    return false;
                }
                return true;
            }
        ));
        return ['ok' => true, 'mensagem' => 'Aparelho desligado.'];
    }
    if ($action === 'push_testar') {
        $GLOBALS['fln_avisos_enviar'][] = [
            'id' => 'teste-' . bin2hex(random_bytes(3)),
            'titulo' => 'Teste · Transfer FLN',
            'corpo' => 'Se este aviso apareceu, o celular está pronto para avisar quando cair uma reserva.',
            'url' => 'index.php?p=configuracoes',
            'som' => !array_key_exists('notif_som', $db['settings']) || !empty($db['settings']['notif_som']),
            'vibrar' => !array_key_exists('notif_vibrar', $db['settings']) || !empty($db['settings']['notif_vibrar']),
        ];
        $n = count($db['push']);
        return ['ok' => true, 'mensagem' => $n > 0
            ? 'Teste enviado para ' . $n . ' aparelho(s). O celular vibra mesmo com o painel fechado.'
            : 'Som tocado neste aparelho. Ative também o celular para receber com a tela bloqueada.'];
    }
    // APK nativo: token próprio (sem VAPID). O worker Android consulta p=aviso_apk.
    if (post('tipo') === 'apk') {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower(trim(post('token')))) ?? '';
        if (strlen($token) < 32) {
            return ['ok' => false, 'erro' => 'Token do APK inválido.'];
        }
        $id = substr(hash('sha256', 'apk:' . $token), 0, 12);
        $novo = [
            'id' => $id,
            'kind' => 'apk',
            'token' => $token,
            'endpoint' => 'apk://' . $token,
            'p256dh' => '',
            'auth' => '',
            'aparelho' => 'APK · ' . push_nome_aparelho((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Android')),
            'papel' => (($_SESSION['role'] ?? 'admin') === 'motorista') ? 'motorista' : 'admin',
            'motorista_id' => (($_SESSION['role'] ?? 'admin') === 'motorista') ? (int) ($_SESSION['motorista_id'] ?? 0) : 0,
            'criado_em' => date('Y-m-d H:i'),
            'desde' => (int) ($db['seq']['aviso'] ?? 1) - 1,
        ];
        $trocou = false;
        foreach ($db['push'] as $i => $ap) {
            if (is_array($ap) && ((string) ($ap['id'] ?? '') === $id || (string) ($ap['token'] ?? '') === $token)) {
                $novo['desde'] = (int) ($ap['desde'] ?? $novo['desde']);
                $db['push'][$i] = $novo;
                $trocou = true;
                break;
            }
        }
        if (!$trocou) {
            $db['push'][] = $novo;
        }
        if (count($db['push']) > 40) {
            $db['push'] = array_slice($db['push'], -40);
        }
        return ['ok' => true, 'id' => $id, 'mensagem' => 'APK ligado. Os avisos aparecem mesmo com o app em segundo plano.'];
    }

    $endpoint = trim(post('endpoint'));
    $p256dh = trim(post('p256dh'));
    $auth = trim(post('auth'));
    if (!preg_match('#^https://#i', $endpoint) || strlen($endpoint) > 500 || strlen(push_b64url_dec($p256dh)) !== 65 || strlen(push_b64url_dec($auth)) < 16) {
        return ['ok' => false, 'erro' => 'Não foi possível ligar este aparelho. Atualize a página e tente de novo.'];
    }
    $id = substr(hash('sha256', $endpoint), 0, 12);
    $novo = [
        'id' => $id,
        'kind' => 'web',
        'endpoint' => $endpoint,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'aparelho' => push_nome_aparelho((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'papel' => (($_SESSION['role'] ?? 'admin') === 'motorista') ? 'motorista' : 'admin',
        'motorista_id' => (($_SESSION['role'] ?? 'admin') === 'motorista') ? (int) ($_SESSION['motorista_id'] ?? 0) : 0,
        'criado_em' => date('Y-m-d H:i'),
    ];
    $trocou = false;
    foreach ($db['push'] as $i => $ap) {
        if (is_array($ap) && (string) ($ap['id'] ?? '') === $id) {
            $db['push'][$i] = $novo;
            $trocou = true;
            break;
        }
    }
    if (!$trocou) {
        $db['push'][] = $novo;
    }
        if (count($db['push']) > 40) {
            $db['push'] = array_slice($db['push'], -40);
        }
        return ['ok' => true, 'id' => $id, 'mensagem' => 'Este aparelho vai vibrar e tocar quando cair uma reserva.'];
}

/** Lista avisos novos para um token do APK (sem sessão). */
function push_avisos_apk(array &$db, string $token, int $desde): array
{
    $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
    if (strlen($token) < 32) {
        return ['ok' => false, 'erro' => 'token'];
    }
    $achou = false;
    foreach ($db['push'] as $i => $ap) {
        if (!is_array($ap) || (string) ($ap['kind'] ?? '') !== 'apk') {
            continue;
        }
        if ((string) ($ap['token'] ?? '') !== $token) {
            continue;
        }
        $achou = true;
        $itens = [];
        $ultimo = $desde;
        foreach ($db['avisos'] ?? [] as $aviso) {
            if (!is_array($aviso)) {
                continue;
            }
            $idAviso = (int) ($aviso['id'] ?? 0);
            if ($idAviso > $ultimo) {
                $ultimo = $idAviso;
            }
            if ($idAviso > $desde && aviso_item_para($aviso, (string) ($ap['papel'] ?? 'admin'), (int) ($ap['motorista_id'] ?? 0))) {
                $itens[] = $aviso;
            }
        }
        $db['push'][$i]['desde'] = $ultimo;
        $db['push'][$i]['visto_em'] = date('Y-m-d H:i');
        return ['ok' => true, 'itens' => $itens, 'ultimo' => $ultimo];
    }
    if (!$achou) {
        return ['ok' => false, 'erro' => 'aparelho'];
    }
    return ['ok' => true, 'itens' => [], 'ultimo' => $desde];
}

push_openssl_pronto();
