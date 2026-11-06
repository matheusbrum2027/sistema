<?php
declare(strict_types=1);

function chat_admin(): bool
{
    return !empty($_SESSION['auth']) && (($_SESSION['role'] ?? '') === 'admin');
}

function chat_hora(int $ts): string
{
    if ($ts <= 0) {
        return '';
    }
    if ($ts > 20000000000) {
        $ts = (int) floor($ts / 1000);
    }
    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $hoje = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    if ($dt->format('Y-m-d') === $hoje) {
        return $dt->format('H:i');
    }
    return $dt->format('d/m H:i');
}

/** Normaliza timestamp para milissegundos. 0 = inválido (nunca inventa "agora"). */
function chat_ts(int $raw): int
{
    if ($raw <= 0) {
        return 0;
    }
    if ($raw < 100000000000) {
        return $raw * 1000;
    }
    return $raw;
}

function chat_ts_agora(): int
{
    return (int) round(microtime(true) * 1000);
}

function chat_desde_ms(array $db): int
{
    $desde = (int) ($db['chat']['desde'] ?? 0);
    if ($desde <= 0) {
        return 0;
    }
    return $desde < 100000000000 ? $desde * 1000 : $desde;
}

/** Remove mensagens/conversas anteriores ao corte da conexão. */
function chat_limpar_antes_do_corte(array &$db): bool
{
    chat_store_garantir($db);
    $desdeMs = chat_desde_ms($db);
    if ($desdeMs <= 0) {
        return false;
    }
    $mudou = false;
    foreach (array_keys($db['chat']['mensagens']) as $id) {
        if (!is_string($id) || !is_array($db['chat']['mensagens'][$id] ?? null)) {
            unset($db['chat']['mensagens'][$id]);
            unset($db['chat']['conversas'][$id]);
            $mudou = true;
            continue;
        }
        $antes = count($db['chat']['mensagens'][$id]);
        $db['chat']['mensagens'][$id] = array_values(array_filter(
            $db['chat']['mensagens'][$id],
            static function ($msg) use ($desdeMs): bool {
                if (!is_array($msg)) {
                    return false;
                }
                $ts = chat_ts((int) ($msg['ts'] ?? 0));
                return $ts > 0 && $ts + 2000 >= $desdeMs;
            }
        ));
        if (count($db['chat']['mensagens'][$id]) !== $antes) {
            $mudou = true;
        }
        if ($db['chat']['mensagens'][$id] === []) {
            unset($db['chat']['mensagens'][$id]);
            unset($db['chat']['conversas'][$id]);
            $mudou = true;
            continue;
        }
        chat_recalcular_conversa($db, $id);
    }
    foreach (array_keys($db['chat']['conversas']) as $id) {
        if (!isset($db['chat']['mensagens'][$id])) {
            unset($db['chat']['conversas'][$id]);
            $mudou = true;
        }
    }
    return $mudou;
}

function chat_ordenar_mensagens(array &$lista): void
{
    usort($lista, static function ($a, $b): int {
        if (!is_array($a) || !is_array($b)) {
            return 0;
        }
        $ta = chat_ts((int) ($a['ts'] ?? 0));
        $tb = chat_ts((int) ($b['ts'] ?? 0));
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });
    $lista = array_values($lista);
}

function chat_foto_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return '';
    }
    return clip($url, 500);
}

function chat_recalcular_conversa(array &$db, string $id): void
{
    if (!isset($db['chat']['mensagens'][$id]) || !is_array($db['chat']['mensagens'][$id])) {
        return;
    }
    chat_ordenar_mensagens($db['chat']['mensagens'][$id]);
    $msgs = $db['chat']['mensagens'][$id];
    if ($msgs === []) {
        return;
    }
    if (count($msgs) > 120) {
        $msgs = array_slice($msgs, -120);
        $db['chat']['mensagens'][$id] = $msgs;
    }
    $ultima = $msgs[count($msgs) - 1];
    $atual = is_array($db['chat']['conversas'][$id] ?? null) ? $db['chat']['conversas'][$id] : [];
    $nome = trim((string) ($atual['nome'] ?? ''));
    if ($nome === '') {
        $nome = 'Conversa';
    }
    $db['chat']['conversas'][$id] = [
        'id' => $id,
        'nome' => clip($nome, 80),
        'fone' => (string) ($atual['fone'] ?? ''),
        'preview' => clip((string) ($ultima['texto'] ?? ''), 90),
        'ts' => chat_ts((int) ($ultima['ts'] ?? 0)),
        'naoLidas' => max(0, (int) ($atual['naoLidas'] ?? 0)),
        'grupo' => !empty($atual['grupo']) || str_ends_with($id, '@g.us'),
        'letra' => mb_strtoupper(mb_substr($nome, 0, 1)),
        'foto' => chat_foto_url((string) ($atual['foto'] ?? '')),
    ];
}

function chat_id_ok(string $id): bool
{
    return (bool) preg_match('/^[0-9A-Za-z._-]{6,80}@(s\.whatsapp\.net|g\.us|lid)$/', $id);
}

function chat_ignorar_grupos(array $settings): bool
{
    if (!array_key_exists('chat_ignorar_grupos', $settings)) {
        return true;
    }
    return !empty($settings['chat_ignorar_grupos']);
}

function chat_telefone_de(string $jid, string $phone = ''): string
{
    $raw = trim($phone);
    if ($raw === '') {
        $raw = (string) preg_replace('/@.+$/', '', $jid);
    }
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($d, '55') && strlen($d) >= 12) {
        $ddd = substr($d, 2, 2);
        $resto = substr($d, 4);
        if (strlen($resto) === 9) {
            return '+55 ' . $ddd . ' ' . substr($resto, 0, 5) . '-' . substr($resto, 5);
        }
        if (strlen($resto) === 8) {
            return '+55 ' . $ddd . ' ' . substr($resto, 0, 4) . '-' . substr($resto, 4);
        }
    }
    return $d !== '' ? '+' . $d : '';
}

function chat_openai_mascara(string $key): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }
    $len = strlen($key);
    if ($len <= 8) {
        return str_repeat('•', $len);
    }
    return substr($key, 0, 3) . str_repeat('•', max(6, $len - 7)) . substr($key, -4);
}

function chat_token_invalido(string $erro): bool
{
    $erro = strtolower($erro);
    return str_contains($erro, 'invalid token')
        || str_contains($erro, 'missing token')
        || str_contains($erro, 'token inválido')
        || str_contains($erro, 'token invalido');
}

function chat_erro_humano(string $erro): string
{
    if (chat_token_invalido($erro)) {
        chat_marcar_desconectado();
        return 'A sessão do WhatsApp no painel expirou. As conversas locais continuam visíveis. Reconecte no Comunicador para voltar a enviar e receber.';
    }
    if (stripos($erro, '429') !== false || stripos($erro, 'rate') !== false) {
        return 'A UAZAPI pediu para aguardar. Espere alguns segundos e tente de novo.';
    }
    return $erro;
}

function chat_marcar_desconectado(string $motivo = ''): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;
    try {
        com_db('chat_token', static function (array &$db) use ($motivo): ?bool {
            $status = (string) ($db['settings']['wa_status'] ?? '');
            if ($status === 'desconectado' && trim((string) ($db['settings']['wa_erro'] ?? '')) !== '') {
                return null;
            }
            $db['settings']['wa_status'] = 'desconectado';
            $db['settings']['wa_instance_status'] = 'disconnected';
            $db['settings']['wa_qr'] = '';
            $db['settings']['wa_erro'] = $motivo !== ''
                ? $motivo
                : 'Token da instância inválido. Gere um novo QR no Comunicador.';
            return true;
        });
    } catch (Throwable) {
    }
}

/** Garante o armazenamento local do chat. Nunca importa histórico do WhatsApp. */
function chat_store_garantir(array &$db): void
{
    $novo = false;
    if (!isset($db['chat']) || !is_array($db['chat'])) {
        $db['chat'] = [];
        $novo = true;
    }
    if (empty($db['chat']['secret']) || !is_string($db['chat']['secret']) || !preg_match('/^[a-f0-9]{32}$/', (string) $db['chat']['secret'])) {
        $db['chat']['secret'] = bin2hex(random_bytes(16));
        $novo = true;
    }
    if (!isset($db['chat']['desde']) || (int) $db['chat']['desde'] <= 0) {
        $db['chat']['desde'] = time();
        $novo = true;
    }
    if (!isset($db['chat']['conversas']) || !is_array($db['chat']['conversas'])) {
        $db['chat']['conversas'] = [];
        $novo = true;
    }
    if (!isset($db['chat']['mensagens']) || !is_array($db['chat']['mensagens'])) {
        $db['chat']['mensagens'] = [];
        $novo = true;
    }
    if ($novo) {
        $GLOBALS['fln_chat_novo'] = true;
    }
}

function chat_texto_msg(array $msg): string
{
    $btn = function_exists('atendimento_extrair_btn_id') ? atendimento_extrair_btn_id($msg) : '';
    if ($btn !== '') {
        return $btn;
    }
    $texto = trim((string) ($msg['text'] ?? ''));
    if ($texto !== '') {
        return $texto;
    }
    return match ((string) ($msg['messageType'] ?? '')) {
        'image', 'ImageMessage', 'Image' => 'Imagem',
        'video', 'VideoMessage', 'Video' => 'Vídeo',
        'audio', 'ptt', 'AudioMessage', 'Audio', 'Ptt' => 'Áudio',
        'document', 'DocumentMessage', 'Document' => 'Documento',
        'sticker', 'StickerMessage' => 'Figurinha',
        'location', 'LocationMessage' => 'Localização',
        'contact', 'vcard', 'ContactMessage' => 'Contato',
        'poll', 'PollCreationMessage' => 'Enquete',
        'buttonsResponseMessage', 'ButtonsResponseMessage', 'listResponseMessage', 'ListResponseMessage',
        'templateButtonReplyMessage', 'interactiveResponseMessage' => trim((string) ($msg['buttonOrListid'] ?? $msg['selectedId'] ?? 'Botão')),
        'Conversation', 'ExtendedTextMessage', 'conversation' => '',
        default => trim((string) ($msg['messageType'] ?? '')) !== '' ? (string) $msg['messageType'] : '',
    };
}

function chat_webhook_url(array $db): string
{
    chat_store_garantir($db);
    return hook_url((string) $db['chat']['secret']);
}

function chat_configurar_webhook(array $settings, array $db): void
{
    if (!uazapi_settings_ready($settings)) {
        return;
    }
    $excluir = ['wasSentByApi'];
    if (chat_ignorar_grupos($settings)) {
        $excluir[] = 'isGroupYes';
    }
    uazapi_request('POST', '/webhook', $settings, [
        'enabled' => true,
        'url' => chat_webhook_url($db),
        'events' => ['messages', 'connection'],
        'excludeMessages' => $excluir,
        'addUrlEvents' => false,
        'addUrlTypesMessages' => false,
    ], 15);
}

/**
 * Chamado ao gerar QR / atualizar status conectado.
 * Abre o corte temporal (sem puxar histórico) e registra o webhook.
 * Conversas locais já salvas permanecem.
 */
function chat_ao_conectar(array &$db, bool $novoCorte = false): void
{
    chat_store_garantir($db);
    if ($novoCorte || (int) ($db['chat']['desde'] ?? 0) <= 0) {
        $db['chat']['desde'] = time();
    }
    if (uazapi_settings_ready($db['settings'])) {
        chat_configurar_webhook($db['settings'], $db);
    }
}

function chat_gravar_mensagem(array &$db, array $entrada): bool
{
    chat_store_garantir($db);
    $id = trim((string) ($entrada['chatid'] ?? ''));
    if (!chat_id_ok($id) || str_contains($id, 'status@')) {
        return false;
    }
    $grupo = !empty($entrada['grupo']) || str_ends_with($id, '@g.us');
    if ($grupo && chat_ignorar_grupos($db['settings'])) {
        return false;
    }
    $rawTs = (int) ($entrada['ts'] ?? 0);
    $ts = chat_ts($rawTs);
    if ($ts <= 0) {
        if (empty($entrada['forcar_agora'])) {
            return false;
        }
        $ts = chat_ts_agora();
    }
    $desdeMs = chat_desde_ms($db);
    if ($desdeMs > 0 && $ts + 2000 < $desdeMs) {
        return false;
    }
    $msgId = trim((string) ($entrada['id'] ?? ''));
    if ($msgId === '') {
        $msgId = 'local-' . $ts . '-' . bin2hex(random_bytes(4));
    }
    $texto = trim((string) ($entrada['texto'] ?? ''));
    if ($texto === '') {
        return false;
    }
    $minha = !empty($entrada['minha']);
    $nome = trim((string) ($entrada['nome'] ?? ''));
    if ($nome === '') {
        $nome = chat_telefone_de($id, (string) ($entrada['fone'] ?? ''));
    }
    if ($nome === '') {
        $nome = 'Conversa';
    }
    $fone = chat_telefone_de($id, (string) ($entrada['fone'] ?? ''));
    $autor = trim((string) ($entrada['autor'] ?? ''));
    $foto = chat_foto_url((string) ($entrada['foto'] ?? ''));

    if (!isset($db['chat']['mensagens'][$id]) || !is_array($db['chat']['mensagens'][$id])) {
        $db['chat']['mensagens'][$id] = [];
    }
    $jaExiste = false;
    foreach ($db['chat']['mensagens'][$id] as $idx => &$existente) {
        if (!is_array($existente) || (string) ($existente['id'] ?? '') !== $msgId) {
            continue;
        }
        $jaExiste = true;
        $tsAntigo = chat_ts((int) ($existente['ts'] ?? 0));
        if ($desdeMs > 0 && $ts + 2000 < $desdeMs) {
            unset($db['chat']['mensagens'][$id][$idx], $existente);
            $db['chat']['mensagens'][$id] = array_values($db['chat']['mensagens'][$id]);
            if ($db['chat']['mensagens'][$id] === []) {
                unset($db['chat']['mensagens'][$id], $db['chat']['conversas'][$id]);
            } else {
                chat_recalcular_conversa($db, $id);
            }
            return true;
        }
        if ($ts !== $tsAntigo || (string) ($existente['texto'] ?? '') !== $texto) {
            $existente['ts'] = $ts;
            $existente['texto'] = clip($texto, 4000);
            $existente['minha'] = $minha;
            $existente['autor'] = clip($autor, 80);
            unset($existente);
            chat_recalcular_conversa($db, $id);
            $atual = $db['chat']['conversas'][$id] ?? [];
            if ($foto !== '') {
                $atual['foto'] = $foto;
            }
            if ($nome !== '' && $nome !== 'Conversa') {
                $atual['nome'] = clip($nome, 80);
                $atual['letra'] = mb_strtoupper(mb_substr($nome, 0, 1));
            }
            if ($fone !== '') {
                $atual['fone'] = $fone;
            }
            $db['chat']['conversas'][$id] = $atual;
            uasort($db['chat']['conversas'], static function (array $a, array $b): int {
                return chat_ts((int) ($b['ts'] ?? 0)) <=> chat_ts((int) ($a['ts'] ?? 0));
            });
            return true;
        }
        unset($existente);
        break;
    }
    unset($existente);
    if ($jaExiste) {
        $atual = $db['chat']['conversas'][$id] ?? [];
        $mudouMeta = false;
        if ($foto !== '' && $foto !== (string) ($atual['foto'] ?? '')) {
            $atual['foto'] = $foto;
            $mudouMeta = true;
        }
        if ($mudouMeta) {
            $db['chat']['conversas'][$id] = $atual;
            return true;
        }
        return false;
    }
    $db['chat']['mensagens'][$id][] = [
        'id' => $msgId,
        'texto' => clip($texto, 4000),
        'minha' => $minha,
        'ts' => $ts,
        'autor' => clip($autor, 80),
    ];

    $atual = is_array($db['chat']['conversas'][$id] ?? null) ? $db['chat']['conversas'][$id] : [];
    $naoLidas = (int) ($atual['naoLidas'] ?? 0);
    if (!$minha) {
        $naoLidas++;
    }
    if ($foto === '') {
        $foto = chat_foto_url((string) ($atual['foto'] ?? ''));
    }
    $db['chat']['conversas'][$id] = [
        'id' => $id,
        'nome' => clip($nome, 80),
        'fone' => $fone !== '' ? $fone : (string) ($atual['fone'] ?? ''),
        'preview' => clip($texto, 90),
        'ts' => $ts,
        'naoLidas' => max(0, $naoLidas),
        'grupo' => $grupo,
        'letra' => mb_strtoupper(mb_substr($nome, 0, 1)),
        'foto' => $foto,
    ];
    chat_recalcular_conversa($db, $id);
    $db['chat']['conversas'][$id]['naoLidas'] = max(0, $naoLidas);
    if ($foto !== '') {
        $db['chat']['conversas'][$id]['foto'] = $foto;
    }

    uasort($db['chat']['conversas'], static function (array $a, array $b): int {
        return chat_ts((int) ($b['ts'] ?? 0)) <=> chat_ts((int) ($a['ts'] ?? 0));
    });
    if (count($db['chat']['conversas']) > 80) {
        $db['chat']['conversas'] = array_slice($db['chat']['conversas'], 0, 80, true);
    }
    return true;
}

function chat_webhook_recebido(array $payload): void
{
    $evento = strtolower((string) ($payload['EventType'] ?? $payload['event'] ?? $payload['type'] ?? ''));
    if ($evento === 'history' || $evento === 'chats') {
        return;
    }
    if ($evento === 'connection') {
        $estado = strtolower((string) ($payload['instance']['status'] ?? $payload['status'] ?? $payload['state'] ?? ''));
        if ($estado !== '') {
            try {
                com_db('chat_conn', static function (array &$db) use ($estado): bool {
                    if (in_array($estado, ['connected', 'open', 'conectado'], true)) {
                        $db['settings']['wa_status'] = 'conectado';
                        $db['settings']['wa_instance_status'] = 'connected';
                        $db['settings']['wa_erro'] = '';
                        chat_ao_conectar($db, false);
                    } elseif (in_array($estado, ['disconnected', 'close', 'desconectado'], true)) {
                        $db['settings']['wa_status'] = 'desconectado';
                        $db['settings']['wa_instance_status'] = 'disconnected';
                    }
                    return true;
                });
            } catch (Throwable) {
            }
        }
        return;
    }
    if ($evento !== '' && $evento !== 'messages' && $evento !== 'message') {
        return;
    }
    $msg = $payload['message'] ?? null;
    if (!is_array($msg)) {
        return;
    }
    $chatSnap = is_array($payload['chat'] ?? null) ? $payload['chat'] : [];
    $chatid = trim((string) ($msg['chatid'] ?? $chatSnap['wa_chatid'] ?? ''));
    $texto = chat_texto_msg($msg);
    $nome = '';
    foreach (['wa_contactName', 'name', 'wa_name'] as $campo) {
        $nome = trim((string) ($chatSnap[$campo] ?? ''));
        if ($nome !== '') {
            break;
        }
    }
    if ($nome === '') {
        $nome = trim((string) ($msg['senderName'] ?? ''));
    }
    $btnId = function_exists('atendimento_extrair_btn_id') ? atendimento_extrair_btn_id($msg) : '';
    try {
        com_db('chat_msg', static function (array &$db) use ($msg, $chatid, $texto, $nome, $chatSnap, $btnId): bool {
            if (($db['settings']['wa_status'] ?? '') !== 'conectado') {
                return false;
            }
            $gravou = chat_gravar_mensagem($db, [
                'chatid' => $chatid,
                'id' => (string) ($msg['id'] ?? $msg['messageid'] ?? ''),
                'texto' => $texto !== '' ? $texto : ($btnId !== '' ? $btnId : ''),
                'minha' => !empty($msg['fromMe']),
                'ts' => (int) ($msg['messageTimestamp'] ?? 0),
                'autor' => (string) ($msg['senderName'] ?? ''),
                'nome' => $nome,
                'fone' => (string) ($chatSnap['phone'] ?? ''),
                'grupo' => !empty($msg['isGroup']) || !empty($chatSnap['wa_isGroup']),
                'foto' => (string) ($chatSnap['imagePreview'] ?? $chatSnap['image'] ?? ''),
            ]);
            // Atendimento IA / botões da reserva comunicada (só esta reserva).
            if (empty($msg['fromMe']) && empty($msg['isGroup']) && function_exists('atendimento_processar_entrada')) {
                try {
                    atendimento_processar_entrada($db, $msg, $chatid, $texto, $btnId);
                } catch (Throwable $e) {
                    error_log('fln atendimento: ' . $e->getMessage());
                }
            }
            return $gravou;
        });
    } catch (Throwable $e) {
        error_log('fln chat hook: ' . $e->getMessage());
    }
}

/**
 * Puxa só mensagens novas (após o corte) enquanto estiver conectado.
 * Não importa histórico antigo. Se desconectar, não busca nada.
 */
function chat_puxar_novas(array $db): int
{
    $settings = $db['settings'] ?? [];
    if (!uazapi_settings_ready($settings) || (($settings['wa_status'] ?? '') !== 'conectado')) {
        return 0;
    }
    chat_store_garantir($db);
    $desde = chat_desde_ms($db);
    if ($desde <= 0) {
        return 0;
    }
    $ignorarGrupos = chat_ignorar_grupos($settings);
    $body = [
        'operator' => 'AND',
        'sort' => '-wa_lastMsgTimestamp',
        'limit' => 20,
        'offset' => 0,
        'compact' => true,
        // Só conversas com atividade após o corte da conexão.
        'wa_lastMsgTimestamp' => '>=' . $desde,
    ];
    if ($ignorarGrupos) {
        $body['wa_isGroup'] = false;
    }
    [$ok, , $erro, $json] = uazapi_request('POST', '/chat/find', $settings, $body, 18);
    if (!$ok || !is_array($json)) {
        if (chat_token_invalido((string) $erro)) {
            chat_marcar_desconectado();
        }
        // Fallback sem filtro do provedor, ainda cortando localmente.
        unset($body['wa_lastMsgTimestamp']);
        [$ok, , $erro, $json] = uazapi_request('POST', '/chat/find', $settings, $body, 18);
        if (!$ok || !is_array($json)) {
            if (chat_token_invalido((string) $erro)) {
                chat_marcar_desconectado();
            }
            return 0;
        }
    }
    $chats = $json['chats'] ?? [];
    if (!is_array($chats)) {
        $chats = [];
    }
    $alvos = [];
    foreach ($chats as $chat) {
        if (!is_array($chat)) {
            continue;
        }
        $id = trim((string) ($chat['wa_chatid'] ?? ''));
        if (!chat_id_ok($id) || str_contains($id, 'status@')) {
            continue;
        }
        $grupo = !empty($chat['wa_isGroup']) || str_ends_with($id, '@g.us');
        if ($grupo && $ignorarGrupos) {
            continue;
        }
        $last = chat_ts((int) ($chat['wa_lastMsgTimestamp'] ?? 0));
        // Sem horário válido ou anterior ao corte: ignora (não importa contato antigo).
        if ($last <= 0 || $last + 2000 < $desde) {
            continue;
        }
        $alvos[$id] = $chat;
    }
    // Só atualiza conversas que já existem localmente (não cria contato só por estar na agenda).
    foreach ($db['chat']['conversas'] as $id => $local) {
        if (!is_string($id) || isset($alvos[$id]) || !chat_id_ok($id)) {
            continue;
        }
        $grupo = (!is_array($local) ? false : !empty($local['grupo'])) || str_ends_with($id, '@g.us');
        if ($grupo && $ignorarGrupos) {
            continue;
        }
        $alvos[$id] = is_array($local) ? $local : ['wa_chatid' => $id];
    }
    if ($alvos === []) {
        return 0;
    }
    $alvos = array_slice($alvos, 0, 5, true);
    $entradas = [];
    foreach ($alvos as $id => $chatSnap) {
        if (!is_array($chatSnap)) {
            $chatSnap = [];
        }
        $foto = chat_foto_url((string) ($chatSnap['imagePreview'] ?? $chatSnap['image'] ?? $chatSnap['foto'] ?? ''));
        if ($foto === '' && is_array($db['chat']['conversas'][$id] ?? null)) {
            $foto = chat_foto_url((string) ($db['chat']['conversas'][$id]['foto'] ?? ''));
        }
        [$okM, , $erroM, $jsonM] = uazapi_request('POST', '/message/find', $settings, [
            'chatid' => $id,
            'limit' => 25,
            'offset' => 0,
        ], 10);
        if (!$okM || !is_array($jsonM)) {
            if (chat_token_invalido((string) $erroM)) {
                chat_marcar_desconectado();
                break;
            }
            continue;
        }
        $brutas = $jsonM['messages'] ?? [];
        if (!is_array($brutas)) {
            continue;
        }
        $nome = '';
        foreach (['wa_contactName', 'name', 'wa_name', 'nome'] as $campo) {
            $nome = trim((string) ($chatSnap[$campo] ?? ''));
            if ($nome !== '') {
                break;
            }
        }
        if ($nome === '' && is_array($db['chat']['conversas'][$id] ?? null)) {
            $nome = trim((string) ($db['chat']['conversas'][$id]['nome'] ?? ''));
        }
        foreach ($brutas as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $ts = chat_ts((int) ($msg['messageTimestamp'] ?? 0));
            if ($ts <= 0 || $ts + 2000 < $desde) {
                continue;
            }
            $texto = chat_texto_msg($msg);
            if ($texto === '') {
                continue;
            }
            $entradas[] = [
                'chatid' => $id,
                'id' => (string) ($msg['id'] ?? $msg['messageid'] ?? ''),
                'texto' => $texto,
                'minha' => !empty($msg['fromMe']),
                'ts' => $ts,
                'autor' => (string) ($msg['senderName'] ?? ''),
                'nome' => $nome !== '' ? $nome : (string) ($msg['senderName'] ?? ''),
                'fone' => (string) ($chatSnap['phone'] ?? ($chatSnap['fone'] ?? '')),
                'grupo' => !empty($msg['isGroup']) || !empty($chatSnap['wa_isGroup']) || !empty($chatSnap['grupo']) || str_ends_with($id, '@g.us'),
                'foto' => $foto,
            ];
        }
    }
    if ($entradas === []) {
        return 0;
    }
    try {
        $resultado = com_db('chat_puxar', static function (array &$store) use ($entradas): int|false {
            if (($store['settings']['wa_status'] ?? '') !== 'conectado') {
                return false;
            }
            $qtd = 0;
            foreach ($entradas as $entrada) {
                if (chat_gravar_mensagem($store, $entrada)) {
                    $qtd++;
                }
            }
            return $qtd > 0 ? $qtd : false;
        });
        return is_int($resultado) ? $resultado : 0;
    } catch (Throwable) {
        return 0;
    }
}

function chat_config(array $settings): array
{
    $key = trim((string) ($settings['chat_openai_key'] ?? ''));
    return [
        'ok' => true,
        'ignorar_grupos' => chat_ignorar_grupos($settings),
        'ia_ativa' => !empty($settings['chat_ia_ativa']),
        'ia_parar' => (string) ($settings['chat_ia_parar'] ?? 'parar'),
        'ia_parar_min' => max(0, (int) ($settings['chat_ia_parar_min'] ?? 30)),
        'ia_pausar_manual' => max(0, (int) ($settings['chat_ia_pausar_manual'] ?? 5)),
        'openai_mascara' => chat_openai_mascara($key),
        'openai_tem' => $key !== '',
        'pronto' => uazapi_settings_ready($settings),
        'comunicador' => href('comunicador'),
    ];
}

function chat_salvar_config(array $entrada): array
{
    $ignorar = !empty($entrada['ignorar_grupos']);
    $iaAtiva = !empty($entrada['ia_ativa']);
    $parar = clip((string) ($entrada['ia_parar'] ?? 'parar'), 40);
    $pararMin = max(0, min(1440, (int) ($entrada['ia_parar_min'] ?? 30)));
    $pausarManual = max(0, min(1440, (int) ($entrada['ia_pausar_manual'] ?? 5)));
    $openaiNova = trim((string) ($entrada['openai_key'] ?? ''));
    if ($openaiNova !== '' && !preg_match('/^sk-[A-Za-z0-9_\-]{10,}$/', $openaiNova)) {
        return ['ok' => false, 'erro' => 'A chave OpenAI parece inválida. Ela costuma começar com sk-.'];
    }

    try {
        $salvo = com_db('chat_cfg', static function (array &$db) use ($ignorar, $iaAtiva, $parar, $pararMin, $pausarManual, $openaiNova): array {
            chat_store_garantir($db);
            $db['settings']['chat_ignorar_grupos'] = $ignorar;
            $db['settings']['chat_ia_ativa'] = $iaAtiva;
            $db['settings']['chat_ia_parar'] = $parar;
            $db['settings']['chat_ia_parar_min'] = $pararMin;
            $db['settings']['chat_ia_pausar_manual'] = $pausarManual;
            if ($openaiNova !== '') {
                $db['settings']['chat_openai_key'] = clip($openaiNova, 200);
            }
            if (uazapi_settings_ready($db['settings']) && (($db['settings']['wa_status'] ?? '') === 'conectado')) {
                chat_configurar_webhook($db['settings'], $db);
            }
            return $db['settings'];
        });
    } catch (Throwable) {
        return ['ok' => false, 'erro' => 'Não foi possível gravar as configurações.'];
    }
    if (!is_array($salvo)) {
        return ['ok' => false, 'erro' => 'Não foi possível gravar as configurações.'];
    }

    $apiMsg = 'Preferências salvas no painel.';
    if (uazapi_settings_ready($salvo)) {
        $body = [
            'chatbot_enabled' => $iaAtiva,
            'chatbot_ignoreGroups' => $ignorar,
            'chatbot_stopConversation' => $parar,
            'chatbot_stopMinutes' => $pararMin,
            'chatbot_stopWhenYouSendMsg' => $pausarManual,
        ];
        $key = trim((string) ($salvo['chat_openai_key'] ?? ''));
        if ($key !== '') {
            $body['openai_apikey'] = $key;
        }
        [$okApi, , $erroApi] = uazapi_request('POST', '/instance/updatechatbotsettings', $salvo, $body, 20);
        if (!$okApi) {
            return array_merge(chat_config($salvo), [
                'ok' => true,
                'parcial' => true,
                'aviso' => 'Preferências salvas no painel. Na UAZAPI: ' . chat_erro_humano($erroApi !== '' ? $erroApi : 'não aceitou as opções do chatbot.'),
            ]);
        }
        $apiMsg = 'Preferências salvas e aplicadas na UAZAPI.';
    }

    return array_merge(chat_config($salvo), ['ok' => true, 'parcial' => false, 'aviso' => $apiMsg]);
}

function chat_estado(array $db): array
{
    $settings = $db['settings'] ?? [];
    $status = (string) ($settings['wa_status'] ?? 'desconectado');
    $pronto = uazapi_settings_ready($settings);
    chat_store_garantir($db);
    return [
        'ok' => true,
        'pronto' => $pronto,
        'status' => $status,
        'conectado' => $pronto && $status === 'conectado',
        'perfil' => trim((string) ($settings['wa_profile_name'] ?? '')),
        'instancia' => trim((string) ($settings['uazapi_instance_name'] ?? '')),
        'comunicador' => href('comunicador'),
        'ignorar_grupos' => chat_ignorar_grupos($settings),
        'desde' => (int) ($db['chat']['desde'] ?? 0),
        'local' => true,
    ];
}

function chat_lista(array $db): array
{
    chat_store_garantir($db);
    $ignorarGrupos = chat_ignorar_grupos($db['settings'] ?? []);
    $conversas = [];
    foreach ($db['chat']['conversas'] as $chat) {
        if (!is_array($chat)) {
            continue;
        }
        $id = trim((string) ($chat['id'] ?? ''));
        if (!chat_id_ok($id)) {
            continue;
        }
        $grupo = !empty($chat['grupo']) || str_ends_with($id, '@g.us');
        if ($ignorarGrupos && $grupo) {
            continue;
        }
        $nome = trim((string) ($chat['nome'] ?? 'Conversa'));
        $conversas[] = [
            'id' => $id,
            'nome' => $nome,
            'fone' => (string) ($chat['fone'] ?? ''),
            'preview' => (string) ($chat['preview'] ?? ''),
            'hora' => chat_hora((int) ($chat['ts'] ?? 0)),
            'naoLidas' => max(0, (int) ($chat['naoLidas'] ?? 0)),
            'letra' => mb_strtoupper(mb_substr($nome !== '' ? $nome : '?', 0, 1)),
            'grupo' => $grupo,
            'foto' => chat_foto_url((string) ($chat['foto'] ?? '')),
            'ts' => chat_ts((int) ($chat['ts'] ?? 0)),
        ];
    }
    usort($conversas, static function (array $a, array $b): int {
        return ((int) ($b['ts'] ?? 0)) <=> ((int) ($a['ts'] ?? 0));
    });
    $naoLidas = 0;
    foreach ($conversas as $c) {
        $naoLidas += (int) ($c['naoLidas'] ?? 0);
    }
    return array_merge(chat_estado($db), [
        'ok' => true,
        'erro' => '',
        'conversas' => $conversas,
        'naoLidas' => $naoLidas,
        'ignorar_grupos' => $ignorarGrupos,
        'local' => true,
    ]);
}

function chat_mensagens(array $db, string $id): array
{
    if (!chat_id_ok($id)) {
        return ['ok' => false, 'erro' => 'Conversa inválida.', 'mensagens' => []];
    }
    chat_store_garantir($db);
    try {
        com_db('chat_lida', static function (array &$store) use ($id): bool {
            chat_store_garantir($store);
            if (isset($store['chat']['conversas'][$id]) && is_array($store['chat']['conversas'][$id])) {
                $store['chat']['conversas'][$id]['naoLidas'] = 0;
            }
            return true;
        });
    } catch (Throwable) {
    }
    $db = load_db();
    chat_store_garantir($db);
    $linhas = $db['chat']['mensagens'][$id] ?? [];
    if (!is_array($linhas)) {
        $linhas = [];
    }
    chat_ordenar_mensagens($linhas);
    $mensagens = [];
    foreach ($linhas as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $mensagens[] = [
            'id' => (string) ($msg['id'] ?? ''),
            'texto' => (string) ($msg['texto'] ?? ''),
            'minha' => !empty($msg['minha']),
            'hora' => chat_hora((int) ($msg['ts'] ?? 0)),
            'autor' => (string) ($msg['autor'] ?? ''),
            'ts' => chat_ts((int) ($msg['ts'] ?? 0)),
        ];
    }
    $conv = is_array($db['chat']['conversas'][$id] ?? null) ? $db['chat']['conversas'][$id] : [];
    $lista = chat_lista($db);
    return [
        'ok' => true,
        'erro' => '',
        'mensagens' => $mensagens,
        'foto' => chat_foto_url((string) ($conv['foto'] ?? '')),
        'nome' => (string) ($conv['nome'] ?? ''),
        'fone' => (string) ($conv['fone'] ?? ''),
        'naoLidas' => (int) ($lista['naoLidas'] ?? 0),
        'local' => true,
    ];
}

function chat_sync(array $db): array
{
    $settings = $db['settings'] ?? [];
    $base = chat_estado($db);
    if (uazapi_settings_ready($settings)) {
        [$okStatus, $msgStatus, $state] = uazapi_refresh_status($settings);
        if ($okStatus && is_array($state)) {
            try {
                com_db('chat_sync', static function (array &$store) use ($state): bool {
                    uazapi_apply_whatsapp_state($store['settings'], $state);
                    if (($store['settings']['wa_status'] ?? '') === 'conectado') {
                        chat_ao_conectar($store, false);
                    }
                    return true;
                });
            } catch (Throwable) {
            }
            $db = load_db();
            $base = chat_estado($db);
        } elseif (chat_token_invalido((string) $msgStatus)) {
            chat_marcar_desconectado();
            $db = load_db();
            $base = chat_estado($db);
            $lista = chat_lista($db);
            return array_merge($base, $lista, [
                'ok' => true,
                'aviso' => chat_erro_humano((string) $msgStatus),
            ]);
        }
    }
    try {
        com_db('chat_limpa', static function (array &$store): bool {
            return chat_limpar_antes_do_corte($store);
        });
        $db = load_db();
        $base = chat_estado($db);
    } catch (Throwable) {
    }
    $novos = 0;
    if (!empty($base['conectado'])) {
        $novos = chat_puxar_novas($db);
        if ($novos > 0) {
            $db = load_db();
            $base = chat_estado($db);
        }
    }
    $lista = chat_lista($db);
    return array_merge($base, $lista, ['novos' => $novos]);
}

/**
 * Poll do chat. Por padrão só lê o banco local (rápido).
 * Com $puxar=true (painel aberto / sync) busca na UAZAPI — nunca gera notificação.
 */
function chat_poll(array $db, bool $puxar = false): array
{
    $base = chat_estado($db);
    $novos = 0;
    if ($puxar && !empty($base['conectado'])) {
        try {
            com_db('chat_limpa', static function (array &$store): bool {
                return chat_limpar_antes_do_corte($store);
            });
            $db = load_db();
        } catch (Throwable) {
        }
        $novos = chat_puxar_novas($db);
        $db = load_db();
        $base = chat_estado($db);
    }
    $lista = chat_lista($db);
    return array_merge($base, $lista, ['novos' => $novos]);
}

function chat_enviar(array $db, string $id, string $texto): array
{
    if (!chat_id_ok($id)) {
        return ['ok' => false, 'erro' => 'Conversa inválida.'];
    }
    $texto = trim($texto);
    if ($texto === '') {
        return ['ok' => false, 'erro' => 'Escreva a mensagem.'];
    }
    $settings = $db['settings'] ?? [];
    if (!uazapi_settings_ready($settings) || (($settings['wa_status'] ?? '') !== 'conectado')) {
        return ['ok' => false, 'erro' => 'WhatsApp desconectado. Reconecte no Comunicador para enviar. A conversa continua salva aqui.'];
    }
    [$ok, $detalhe] = uazapi_send_text(
        (string) ($settings['uazapi_url'] ?? ''),
        (string) ($settings['uazapi_token'] ?? ''),
        $id,
        clip($texto, 4000),
        20
    );
    if (!$ok) {
        return ['ok' => false, 'erro' => chat_erro_humano($detalhe)];
    }
    try {
        com_db('chat_out', static function (array &$store) use ($id, $texto, $db): bool {
            $nome = (string) (($db['chat']['conversas'][$id]['nome'] ?? '') ?: 'Conversa');
            $fone = (string) ($db['chat']['conversas'][$id]['fone'] ?? '');
            chat_gravar_mensagem($store, [
                'chatid' => $id,
                'id' => 'out-' . time() . '-' . bin2hex(random_bytes(3)),
                'texto' => $texto,
                'minha' => true,
                'ts' => chat_ts_agora(),
                'forcar_agora' => true,
                'nome' => $nome,
                'fone' => $fone,
                'grupo' => str_ends_with($id, '@g.us'),
            ]);
            if (isset($store['chat']['conversas'][$id])) {
                $store['chat']['conversas'][$id]['naoLidas'] = 0;
            }
            // Mensagem humana do painel: pausa a IA desta conversa de reserva.
            if (function_exists('atendimento_contexto') && function_exists('atendimento_atualizar')) {
                $ctx = atendimento_contexto($store, $id);
                if (is_array($ctx) && empty($ctx['humano'])) {
                    atendimento_atualizar($store, $id, ['humano' => true]);
                }
            }
            return true;
        });
    } catch (Throwable) {
    }
    return ['ok' => true, 'erro' => ''];
}
