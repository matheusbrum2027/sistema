<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Atendimento WhatsApp pós-comunicação:
 * - cards com botões (cliente e motorista)
 * - IA restrita APENAS à reserva comunicada
 * - escalonamento para atendente humano (Matheus Brum)
 */

function atendimento_store_garantir(array &$db): void
{
    if (!isset($db['atendimento']) || !is_array($db['atendimento'])) {
        $db['atendimento'] = ['por_fone' => []];
    }
    if (!isset($db['atendimento']['por_fone']) || !is_array($db['atendimento']['por_fone'])) {
        $db['atendimento']['por_fone'] = [];
    }
}

function atendimento_fone_chave(string $numeroOuJid): string
{
    $dig = preg_replace('/\D+/', '', $numeroOuJid) ?? '';
    if (str_starts_with($dig, '55') && strlen($dig) > 11) {
        return $dig;
    }
    if ($dig !== '' && strlen($dig) <= 11) {
        return '55' . $dig;
    }
    return $dig;
}

function atendimento_vincular(array &$db, string $numero, int $reservaId, string $papel): void
{
    atendimento_store_garantir($db);
    $chave = atendimento_fone_chave($numero);
    if ($chave === '' || $reservaId <= 0) {
        return;
    }
    $papel = $papel === 'motorista' ? 'motorista' : 'cliente';
    $db['atendimento']['por_fone'][$chave] = [
        'reserva_id' => $reservaId,
        'papel' => $papel,
        'desde' => time(),
        'humano' => false,
        'confirmado' => false,
        'ultima_interacao' => time(),
    ];
}

function atendimento_contexto(array $db, string $numeroOuJid): ?array
{
    atendimento_store_garantir($db);
    $chave = atendimento_fone_chave($numeroOuJid);
    if ($chave === '') {
        return null;
    }
    $ctx = $db['atendimento']['por_fone'][$chave] ?? null;
    return is_array($ctx) ? $ctx : null;
}

function atendimento_atualizar(array &$db, string $numeroOuJid, array $patch): void
{
    atendimento_store_garantir($db);
    $chave = atendimento_fone_chave($numeroOuJid);
    if ($chave === '' || !isset($db['atendimento']['por_fone'][$chave])) {
        return;
    }
    $db['atendimento']['por_fone'][$chave] = array_merge($db['atendimento']['por_fone'][$chave], $patch, [
        'ultima_interacao' => time(),
    ]);
}

function atendimento_trip(array $db, int $id): ?array
{
    foreach ($db['transfers'] as $t) {
        if ((int) ($t['id'] ?? 0) === $id) {
            return $t;
        }
    }
    return null;
}

function atendimento_atendente(array $db): array
{
    $nome = trim((string) ($db['settings']['atendente_nome'] ?? ''));
    if ($nome === '') {
        $nome = trim((string) ($db['settings']['assinatura_nome'] ?? $db['settings']['rep_legal'] ?? 'Matheus Brum'));
    }
    if ($nome === '') {
        $nome = 'Matheus Brum';
    }
    $fone = wa_number((string) ($db['settings']['atendente_fone'] ?? ''));
    if (strlen($fone) < 12) {
        $fone = wa_number((string) ($db['settings']['telefone'] ?? ''));
    }
    return ['nome' => $nome, 'numero' => $fone];
}

function atendimento_card_texto_cliente(array $db, array $trip): string
{
    $marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
    $linhas = [
        '✨ *' . $marca . '*',
        'Olá, *' . (string) ($trip['cliente'] ?? '') . '*!',
        '',
        'Sua reserva *#' . (int) $trip['id'] . '* está confirmada no sistema.',
        '',
        '📅 *Quando:* ' . br_date((string) ($trip['data'] ?? '')) . ' · ' . (string) ($trip['hora'] ?? '--:--'),
        '📍 *Embarque:* ' . (string) ($trip['origem'] ?? ''),
        '🏁 *Destino:* ' . (string) ($trip['destino'] ?? ''),
        '👥 *Passageiros:* ' . (string) ($trip['pax'] ?? ''),
        '',
        'Toque em *Confirmar reserva* ou *Ver PDF* (sem dados financeiros).',
        'Depois disso, posso tirar dúvidas *somente* sobre esta reserva.',
    ];
    return implode("\n", $linhas);
}

function atendimento_card_texto_motorista(array $db, array $trip, string $nomeMot, string $trecho): string
{
    $marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
    $cat = (string) ((categorias_veiculo()[(string) ($trip['categoria'] ?? '')] ?? ($trip['categoria'] ?? '')));
    $linhas = [
        '🚗 *' . $marca . ' · Roteiro*',
        'Olá, *' . $nomeMot . '*!',
        '',
        'Você recebeu a corrida *#' . (int) $trip['id'] . '*'
            . ($trecho !== '' ? ' (' . $trecho . ')' : '') . '.',
        '',
        '📅 *Quando:* ' . br_date((string) ($trip['data'] ?? '')) . ' · ' . (string) ($trip['hora'] ?? '--:--'),
        '👤 *Passageiro:* ' . (string) ($trip['cliente'] ?? ''),
        '📞 *Contato:* ' . (string) ($trip['telefone'] ?? ''),
        '📍 *Embarque:* ' . (string) ($trip['origem'] ?? ''),
        '🏁 *Desembarque:* ' . (string) ($trip['destino'] ?? ''),
        '👥 *Pax:* ' . (string) ($trip['pax'] ?? ''),
    ];
    if ($cat !== '') {
        $linhas[] = '🚐 *Categoria:* ' . $cat;
    }
    $obs = trim((string) ($trip['obs'] ?? ''));
    if ($obs !== '') {
        $linhas[] = '📝 *Obs.:* ' . mb_substr($obs, 0, 180);
    }
    $linhas[] = '';
    $linhas[] = 'Toque em *Confirmar ciência* ou *Ver roteiro PDF* (sem valores).';
    $linhas[] = 'Dúvidas? Respondo *somente* sobre esta corrida.';
    return implode("\n", $linhas);
}

function atendimento_btn_id(string $acao, string $papel, int $reservaId): string
{
    $p = $papel === 'motorista' ? 'M' : 'C';
    $a = match ($acao) {
        'ok' => 'ok',
        'pdf' => 'pdf',
        'at' => 'at',
        default => 'x',
    };
    return $a . $p . $reservaId;
}

function atendimento_parse_btn(string $id): ?array
{
    $id = trim($id);
    if (!preg_match('/^(ok|pdf|at)([CM])(\d+)$/', $id, $m)) {
        return null;
    }
    return [
        'acao' => $m[1],
        'papel' => $m[2] === 'M' ? 'motorista' : 'cliente',
        'reserva_id' => (int) $m[3],
    ];
}

function uazapi_send_menu(string $base, string $token, string $number, string $text, array $choices, string $footer = '', int $timeout = 20): array
{
    $payload = [
        'number' => $number,
        'type' => 'button',
        'text' => $text,
        'choices' => array_values($choices),
    ];
    if ($footer !== '') {
        $payload['footerText'] = $footer;
    }
    return uazapi_send_json($base, $token, '/send/menu', $payload, $timeout, true);
}

function atendimento_enviar_card_cliente(array &$db, array $trip, string $numero): array
{
    if (!whatsapp_pronto($db) || strlen($numero) < 12) {
        return [false, 'WhatsApp ou telefone inválido.'];
    }
    $id = (int) $trip['id'];
    $texto = atendimento_card_texto_cliente($db, $trip);
    $choices = [
        'Confirmar reserva|' . atendimento_btn_id('ok', 'cliente', $id),
        'Ver PDF|' . atendimento_btn_id('pdf', 'cliente', $id),
        'Falar com atendente|' . atendimento_btn_id('at', 'cliente', $id),
    ];
    $url = (string) $db['settings']['uazapi_url'];
    $token = (string) $db['settings']['uazapi_token'];
    [$ok, $det] = uazapi_send_menu($url, $token, $numero, $texto, $choices, 'Transfer FLN · reserva #' . $id);
    if ($ok) {
        atendimento_vincular($db, $numero, $id, 'cliente');
    }
    return [$ok, $det];
}

function atendimento_enviar_card_motorista(array &$db, array $trip, string $numero, string $nomeMot, string $trecho): array
{
    if (!whatsapp_pronto($db) || strlen($numero) < 12) {
        return [false, 'WhatsApp ou telefone inválido.'];
    }
    $id = (int) $trip['id'];
    $texto = atendimento_card_texto_motorista($db, $trip, $nomeMot, $trecho);
    $choices = [
        'Confirmar ciência|' . atendimento_btn_id('ok', 'motorista', $id),
        'Ver roteiro PDF|' . atendimento_btn_id('pdf', 'motorista', $id),
        'Falar com atendente|' . atendimento_btn_id('at', 'motorista', $id),
    ];
    $url = (string) $db['settings']['uazapi_url'];
    $token = (string) $db['settings']['uazapi_token'];
    [$ok, $det] = uazapi_send_menu($url, $token, $numero, $texto, $choices, 'Roteiro sem dados financeiros · #' . $id);
    if ($ok) {
        atendimento_vincular($db, $numero, $id, 'motorista');
    }
    return [$ok, $det];
}

function atendimento_pdf_bytes(array $db, array $trip, string $papel): string
{
    $trip['esconder_valores'] = true;
    if ($papel === 'cliente') {
        $linhas = [
            'Reserva' => '#' . (int) ($trip['id'] ?? 0),
            'Cliente' => (string) ($trip['cliente'] ?? ''),
            'Data' => br_date((string) ($trip['data'] ?? '')),
            'Hora' => (string) ($trip['hora'] ?? ''),
            'Embarque' => (string) ($trip['origem'] ?? ''),
            'Destino' => (string) ($trip['destino'] ?? ''),
            'Passageiros' => (string) ($trip['pax'] ?? ''),
            'Status' => status_label((string) ($trip['status'] ?? '')),
        ];
        $obs = trim((string) ($trip['obs'] ?? ''));
        if ($obs !== '') {
            $linhas['Observações'] = $obs;
        }
        $linhas = array_filter($linhas, static fn (string $v): bool => trim($v) !== '');
        return pdf_simples_bytes('Reserva #' . (int) ($trip['id'] ?? 0), $linhas);
    }
    return reserva_pdf_motorista($db, $trip, '');
}

function atendimento_enviar_pdf(array $db, string $numero, array $trip, string $papel): array
{
    $pdf = atendimento_pdf_bytes($db, $trip, $papel);
    if ($pdf === '' || !str_starts_with($pdf, '%PDF')) {
        return [false, 'Falha ao gerar PDF.'];
    }
    $nome = ($papel === 'motorista' ? 'roteiro' : 'reserva') . '-' . (int) $trip['id'] . '.pdf';
    $legenda = $papel === 'motorista'
        ? 'Roteiro da reserva #' . (int) $trip['id'] . ' (apenas rotas, sem dados financeiros).'
        : 'PDF da sua reserva #' . (int) $trip['id'] . ' (sem dados financeiros).';
    return uazapi_send_document(
        (string) $db['settings']['uazapi_url'],
        (string) $db['settings']['uazapi_token'],
        $numero,
        $pdf,
        $nome,
        $legenda,
        25
    );
}

function atendimento_base_reserva(array $db, array $trip): string
{
    $linhas = reserva_rota_motorista($db, $trip, '');
    $blocos = ['RESERVA COMUNICADA (única fonte permitida):'];
    foreach ($linhas as $k => $v) {
        $blocos[] = $k . ': ' . $v;
    }
    $blocos[] = 'PROIBIDO: falar de valores, preços, repasses, outras reservas, ou assuntos fora desta reserva.';
    return implode("\n", $blocos);
}

function atendimento_openai(array $db, string $pergunta, array $trip, string $papel): array
{
    $key = trim((string) ($db['settings']['chat_openai_key'] ?? ''));
    if ($key === '') {
        return [false, 'Ainda não tenho a chave OpenAI configurada no painel. Peça um atendente humano se precisar.'];
    }
    if (!function_exists('curl_init')) {
        return [false, 'Sem cURL no servidor.'];
    }
    $marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
    $sistema = "Você é o assistente de WhatsApp da {$marca}.\n"
        . "REGRA ABSOLUTA: você só pode responder com base na reserva comunicada abaixo. "
        . "Se a pessoa perguntar qualquer coisa fora dessa reserva (clima, outras viagens, preço, política geral, curiosidades), "
        . "responda educadamente que só pode ajudar com os dados desta reserva e sugira o botão/opção de falar com atendente.\n"
        . "Nunca invente horários, endereços ou motoristas. Nunca cite valores financeiros.\n"
        . "Papel do interlocutor: {$papel}.\n\n"
        . atendimento_base_reserva($db, $trip);

    $payload = [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.2,
        'messages' => [
            ['role' => 'system', 'content' => $sistema],
            ['role' => 'user', 'content' => $pergunta],
        ],
        'max_tokens' => 400,
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    curl_ca_nativo($ch);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return [false, 'Não consegui consultar a IA agora (' . ($err !== '' ? $err : 'sem rede') . ').'];
    }
    $json = json_decode((string) $raw, true);
    $txt = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    if ($code < 200 || $code >= 300 || $txt === '') {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
        return [false, $msg !== '' ? $msg : 'A IA não retornou resposta.'];
    }
    return [true, $txt];
}

function atendimento_parece_atendente(string $texto): bool
{
    $t = mb_strtolower(trim($texto));
    if ($t === '') {
        return false;
    }
    $pistas = [
        'atendente', 'humano', 'pessoa', 'operador', 'suporte',
        'falar com alguém', 'falar com alguem', 'quero atendimento',
        'matheus', 'blum', 'brum',
    ];
    foreach ($pistas as $p) {
        if (str_contains($t, $p)) {
            return true;
        }
    }
    return false;
}

function atendimento_avisar_humano(array &$db, string $numeroCliente, array $trip, string $papel, string $trechoMsg): array
{
    $at = atendimento_atendente($db);
    if (strlen($at['numero']) < 12) {
        return [false, 'Telefone do atendente não configurado (Configurações → atendente).'];
    }
    $marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
    $texto = '*' . $marca . ' · Atendimento*\n'
        . 'Olá ' . $at['nome'] . '!\n'
        . 'Alguém pediu para falar com um atendente no WhatsApp.\n\n'
        . 'Reserva: *#' . (int) $trip['id'] . '*\n'
        . 'Papel: ' . $papel . "\n"
        . 'Contato: ' . $numeroCliente . "\n"
        . 'Cliente da reserva: ' . (string) ($trip['cliente'] ?? '') . "\n"
        . 'Trecho da conversa: ' . mb_substr($trechoMsg, 0, 240) . "\n\n"
        . 'Abra o chat no painel para assumir.';
    return uazapi_send_text(
        (string) $db['settings']['uazapi_url'],
        (string) $db['settings']['uazapi_token'],
        $at['numero'],
        $texto,
        15
    );
}

function atendimento_responder_texto(array $db, string $numero, string $texto): array
{
    return uazapi_send_text(
        (string) $db['settings']['uazapi_url'],
        (string) $db['settings']['uazapi_token'],
        $numero,
        $texto,
        15
    );
}

/**
 * Processa mensagem/botão entrante. Retorna true se tratou (evita IA genérica UAZAPI).
 */
function atendimento_processar_entrada(array &$db, array $msg, string $chatid, string $textoBruto, string $btnId = ''): bool
{
    if (!empty($msg['fromMe']) || !empty($msg['isGroup'])) {
        return false;
    }
    if (!whatsapp_pronto($db)) {
        return false;
    }
    $numero = atendimento_fone_chave($chatid !== '' ? $chatid : (string) ($msg['sender'] ?? ''));
    if ($numero === '') {
        return false;
    }

    $parsed = $btnId !== '' ? atendimento_parse_btn($btnId) : null;
    if ($parsed === null && $textoBruto !== '') {
        $parsed = atendimento_parse_btn(trim($textoBruto));
    }

    $ctx = atendimento_contexto($db, $numero);
    $reservaId = (int) ($parsed['reserva_id'] ?? ($ctx['reserva_id'] ?? 0));
    if ($reservaId <= 0) {
        return false; // fora do fluxo de comunicação de reserva
    }
    $trip = atendimento_trip($db, $reservaId);
    if ($trip === null) {
        atendimento_responder_texto($db, $numero, 'Não encontrei a reserva #' . $reservaId . ' neste momento. Peça para falar com um atendente.');
        return true;
    }
    $papel = (string) ($parsed['papel'] ?? ($ctx['papel'] ?? 'cliente'));
    atendimento_vincular($db, $numero, $reservaId, $papel);

    // Contexto já em modo humano: não responde com IA.
    $ctxAtual = atendimento_contexto($db, $numero);
    if (!empty($ctxAtual['humano'])) {
        return true;
    }

    if ($parsed !== null) {
        $acao = $parsed['acao'];
        if ($acao === 'pdf') {
            [$ok, $det] = atendimento_enviar_pdf($db, $numero, $trip, $papel);
            if (!$ok) {
                atendimento_responder_texto($db, $numero, 'Não consegui enviar o PDF agora. Tente de novo ou fale com um atendente. (' . $det . ')');
            }
            return true;
        }
        if ($acao === 'ok') {
            atendimento_atualizar($db, $numero, ['confirmado' => true]);
            if ($papel === 'motorista') {
                atendimento_responder_texto(
                    $db,
                    $numero,
                    'Ciência confirmada na reserva #' . $reservaId . '. Posso esclarecer detalhes *somente* desta corrida. Se precisar de uma pessoa, toque em *Falar com atendente*.'
                );
            } else {
                atendimento_responder_texto(
                    $db,
                    $numero,
                    'Reserva #' . $reservaId . ' confirmada! Posso ajudar com dúvidas *somente* sobre esta viagem. Para o PDF, use *Ver PDF*. Se quiser uma pessoa, toque em *Falar com atendente*.'
                );
            }
            return true;
        }
        if ($acao === 'at') {
            atendimento_atualizar($db, $numero, ['humano' => true]);
            atendimento_avisar_humano($db, $numero, $trip, $papel, 'Pediu atendente pelo botão');
            atendimento_responder_texto(
                $db,
                $numero,
                'Certo! Avisei o atendente (' . atendimento_atendente($db)['nome'] . '). Em breve alguém assume esta conversa.'
            );
            return true;
        }
    }

    $texto = trim($textoBruto);
    if ($texto === '' || in_array(mb_strtolower($texto), ['imagem', 'áudio', 'audio', 'vídeo', 'video', 'documento', 'figurinha', 'localização', 'localizacao', 'contato', 'enquete'], true)) {
        return true;
    }

    if (atendimento_parece_atendente($texto)) {
        atendimento_atualizar($db, $numero, ['humano' => true]);
        atendimento_avisar_humano($db, $numero, $trip, $papel, $texto);
        atendimento_responder_texto(
            $db,
            $numero,
            'Entendi! Já avisei ' . atendimento_atendente($db)['nome'] . ' para entrar nesta conversa.'
        );
        return true;
    }

    // Chatbot de texto só com OpenAI configurada (botões PDF/OK/atendente seguem funcionando).
    $key = trim((string) ($db['settings']['chat_openai_key'] ?? ''));
    if ($key === '') {
        atendimento_responder_texto(
            $db,
            $numero,
            'O atendimento automático por texto ainda não está ativo. Use os botões da reserva ou toque em *Falar com atendente*.'
        );
        atendimento_atualizar($db, $numero, []);
        return true;
    }

    // IA restrita à reserva comunicada.
    [$okIa, $resposta] = atendimento_openai($db, $texto, $trip, $papel);
    if (!$okIa) {
        $resposta = 'No momento não consegui consultar a IA. '
            . 'Posso só falar da reserva #' . $reservaId . '. Se preferir, peça *Falar com atendente*.';
    }
    atendimento_responder_texto($db, $numero, $resposta);
    atendimento_atualizar($db, $numero, []);
    return true;
}

function atendimento_extrair_btn_id(array $msg): string
{
    foreach ([
        'buttonOrListid', 'buttonOrListId', 'selectedId', 'selectedButtonId',
        'listResponseId', 'rowId', 'id',
    ] as $campo) {
        $v = trim((string) ($msg[$campo] ?? ''));
        if ($v !== '' && atendimento_parse_btn($v) !== null) {
            return $v;
        }
    }
    $content = $msg['content'] ?? null;
    if (is_array($content)) {
        foreach (['selectedId', 'selectedButtonId', 'buttonOrListid', 'id', 'rowId'] as $campo) {
            $v = trim((string) ($content[$campo] ?? ''));
            if ($v !== '' && atendimento_parse_btn($v) !== null) {
                return $v;
            }
        }
    }
    $texto = trim((string) ($msg['text'] ?? ''));
    if (atendimento_parse_btn($texto) !== null) {
        return $texto;
    }
    return '';
}
