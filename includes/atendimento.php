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
 * - OpenAI (settings.chat_openai_key ou OPENAI_API_KEY)
 * - automação pausável a qualquer momento no chat (humano / palavra / botão)
 * - escalonamento para atendente humano (Matheus Brum)
 */

function atendimento_openai_key(array $db): string
{
    $key = trim((string) ($db['settings']['chat_openai_key'] ?? ''));
    if ($key !== '') {
        return $key;
    }
    return trim((string) env_valor('OPENAI_API_KEY', ''));
}

function atendimento_ia_global_ativa(array $db): bool
{
    return !empty($db['settings']['chat_ia_ativa']) && atendimento_openai_key($db) !== '';
}

function atendimento_pausado(array $ctx): bool
{
    if (!is_array($ctx)) {
        return false;
    }
    if (!empty($ctx['humano'])) {
        return true;
    }
    $ate = (int) ($ctx['pausado_ate'] ?? 0);
    return $ate > time();
}

function atendimento_palavra_pausa(array $db): string
{
    $p = mb_strtolower(trim((string) ($db['settings']['chat_ia_parar'] ?? 'parar')));
    return $p !== '' ? $p : 'parar';
}

function atendimento_store_garantir(array &$db): void
{
    if (!isset($db['atendimento']) || !is_array($db['atendimento'])) {
        $db['atendimento'] = ['por_fone' => []];
    }
    if (!isset($db['atendimento']['por_fone']) || !is_array($db['atendimento']['por_fone'])) {
        $db['atendimento']['por_fone'] = [];
    }
}

function atendimento_fone_legivel(string $numero): string
{
    $d = preg_replace('/\D+/', '', $numero) ?? '';
    if (str_starts_with($d, '55') && strlen($d) >= 12) {
        $ddd = substr($d, 2, 2);
        $resto = substr($d, 4);
        if (strlen($resto) === 9) {
            return '+55 (' . $ddd . ') ' . substr($resto, 0, 5) . '-' . substr($resto, 5);
        }
        if (strlen($resto) === 8) {
            return '+55 (' . $ddd . ') ' . substr($resto, 0, 4) . '-' . substr($resto, 4);
        }
    }
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return $d !== '' ? $d : $numero;
}

function atendimento_fone_wa_link(string $numero): string
{
    $d = preg_replace('/\D+/', '', $numero) ?? '';
    if ($d === '') {
        return '';
    }
    if (!str_starts_with($d, '55') && strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'https://wa.me/' . $d;
}

function atendimento_nome_legivel(string $nome): string
{
    $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? '');
    if ($nome === '') {
        return '';
    }
    $nome = mb_convert_case(mb_strtolower($nome, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    return $nome;
}

function atendimento_fone_chave(string $numeroOuJid): string
{
    $dig = preg_replace('/\D+/', '', $numeroOuJid) ?? '';
    if ($dig === '') {
        return '';
    }
    if (!str_starts_with($dig, '55') && strlen($dig) <= 11) {
        $dig = '55' . $dig;
    }
    // Celular BR antigo sem o 9º dígito (55 + DDD + 8) → normaliza para 13 dígitos.
    if (strlen($dig) === 12 && str_starts_with($dig, '55')) {
        $resto = substr($dig, 4);
        if (strlen($resto) === 8 && $resto !== '' && $resto[0] >= '6') {
            $dig = substr($dig, 0, 4) . '9' . $resto;
        }
    }
    return $dig;
}

/** Alias possíveis do mesmo WhatsApp (com/sem 9º dígito). */
function atendimento_fone_aliases(string $chave): array
{
    $chave = atendimento_fone_chave($chave);
    if ($chave === '') {
        return [];
    }
    $out = [$chave];
    if (strlen($chave) === 13 && str_starts_with($chave, '55') && $chave[4] === '9') {
        $out[] = substr($chave, 0, 4) . substr($chave, 5);
    } elseif (strlen($chave) === 12 && str_starts_with($chave, '55')) {
        $out[] = substr($chave, 0, 4) . '9' . substr($chave, 4);
    }
    return array_values(array_unique($out));
}

/**
 * Grava/atualiza a memória da IA para o WhatsApp.
 * $reiniciar=true no Comunicar: ficha nova + IA religada.
 * Nas mensagens seguintes: só garante vínculo/ficha, preserva pausa/humano.
 */
function atendimento_vincular(array &$db, string $numero, int $reservaId, string $papel, bool $reiniciar = false): void
{
    atendimento_store_garantir($db);
    $chave = atendimento_fone_chave($numero);
    if ($chave === '' || $reservaId <= 0) {
        return;
    }
    $papel = $papel === 'motorista' ? 'motorista' : 'cliente';
    // Une memória espalhada em aliases (ex.: número com e sem o 9).
    $anterior = [];
    foreach (atendimento_fone_aliases($chave) as $alias) {
        $row = $db['atendimento']['por_fone'][$alias] ?? null;
        if (!is_array($row)) {
            continue;
        }
        $anterior = array_merge($anterior, $row);
        if ($alias !== $chave) {
            unset($db['atendimento']['por_fone'][$alias]);
        }
    }
    $trip = atendimento_trip($db, $reservaId);
    $concluida = is_array($trip) && (string) ($trip['status'] ?? '') === 'concluida';
    $mesma = (int) ($anterior['reserva_id'] ?? 0) === $reservaId;
    $ficha = is_array($trip)
        ? atendimento_montar_ficha($db, $trip, $papel, $concluida)
        : (string) ($anterior['ficha'] ?? '');
    if ($reiniciar || !$mesma || $ficha === '' || $anterior === []) {
        // Comunicar ou troca de reserva: memória limpa desta ficha + IA ativa.
        $db['atendimento']['por_fone'][$chave] = [
            'desde' => time(),
            'reserva_id' => $reservaId,
            'papel' => $papel,
            'ultima_interacao' => time(),
            'concluida' => $concluida,
            'ficha' => $ficha,
            'humano' => false,
            'confirmado' => false,
            'avisou_humano' => false,
            'flood' => false,
            'burst' => [],
            'pausado_ate' => 0,
            'vistos' => [],
            'ultimo_texto_enviado' => '',
            'ultimo_envio_em' => 0,
            'avaliacao_enviada' => false,
            'avaliacao_nota' => 0,
        ];
        return;
    }
    $db['atendimento']['por_fone'][$chave] = array_merge($anterior, [
        'reserva_id' => $reservaId,
        'papel' => $papel,
        'ultima_interacao' => time(),
        'concluida' => $concluida || !empty($anterior['concluida']),
        'ficha' => $ficha !== '' ? $ficha : (string) ($anterior['ficha'] ?? ''),
    ]);
}

function atendimento_montar_ficha(array $db, array $trip, string $papel, bool $concluida): string
{
    $linhas = [
        'Reserva' => '#' . (int) ($trip['id'] ?? 0),
        'Situação' => $concluida ? 'corrida já concluída' : status_label((string) ($trip['status'] ?? '')),
        'Quando' => trim(br_date((string) ($trip['data'] ?? '')) . ' ' . (string) ($trip['hora'] ?? '')),
        'Embarque' => (string) ($trip['origem'] ?? ''),
        'Destino' => (string) ($trip['destino'] ?? ''),
        'Passageiros' => (string) ($trip['pax'] ?? ''),
        'Tipo' => (string) ($trip['tipo'] ?? ''),
    ];
    if ($papel === 'motorista') {
        $linhas['Passageiro'] = (string) ($trip['cliente'] ?? '');
        $linhas['Contato do passageiro'] = (string) ($trip['telefone'] ?? '');
    } else {
        $linhas['Cliente'] = (string) ($trip['cliente'] ?? '');
        if ((string) ($trip['quem'] ?? '') !== 'parceiro') {
            $linhas['Motorista'] = nome_motorista($db, $trip['motorista_id'] ?? 0);
        }
    }
    $obs = trim((string) ($trip['obs'] ?? ''));
    if ($obs !== '') {
        $linhas['Observações'] = mb_substr($obs, 0, 400);
    }
    $txt = [];
    foreach ($linhas as $k => $v) {
        $v = trim((string) $v);
        if ($v !== '' && $v !== '—') {
            $txt[] = $k . ': ' . $v;
        }
    }
    $txt[] = 'Fonte única: estes dados foram gravados no momento da comunicação desta reserva.';
    $txt[] = 'Proibido: valores, preços, repasses, outras reservas e qualquer assunto fora desta ficha.';
    return implode("\n", $txt);
}

function atendimento_avaliacao_ligada(array $db): bool
{
    return !array_key_exists('wa_avaliacao_auto', $db['settings']) || !empty($db['settings']['wa_avaliacao_auto']);
}

function atendimento_enviar_avaliacao(array &$db, string $numero, array $trip): array
{
    if (!whatsapp_pronto($db)) {
        return [false, 'WhatsApp indisponível.'];
    }
    $numero = atendimento_fone_chave($numero);
    if (strlen($numero) < 12) {
        return [false, 'Telefone inválido para avaliação.'];
    }
    $id = (int) ($trip['id'] ?? 0);
    $papel = 'cliente';
    $ctx = atendimento_contexto($db, $numero);
    if (is_array($ctx) && ($ctx['papel'] ?? '') === 'motorista') {
        $papel = 'motorista';
    }
    $marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
    $quem = $papel === 'motorista' ? 'a corrida' : 'a sua viagem';
    $texto = implode("\n", [
        '*' . $marca . '*',
        'A reserva *#' . $id . '* foi concluída.',
        '',
        'Como foi ' . $quem . '?',
        'Toque em uma nota. Leva poucos segundos.',
    ]);
    $sigla = $papel === 'motorista' ? 'M' : 'C';
    $choices = [
        'Excelente|av5' . $sigla . $id,
        'Boa|av4' . $sigla . $id,
        'Regular|av3' . $sigla . $id,
        'Ruim|av1' . $sigla . $id,
    ];
    if (function_exists('uazapi_digitando')) {
        uazapi_digitando($db['settings'], $numero);
    }
    return uazapi_send_menu(
        (string) $db['settings']['uazapi_url'],
        (string) $db['settings']['uazapi_token'],
        $numero,
        $texto,
        $choices,
        'Avaliação da reserva #' . $id
    );
}

function atendimento_marcar_concluida(array &$db, array $trip): void
{
    atendimento_store_garantir($db);
    $id = (int) ($trip['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $pedirNota = atendimento_avaliacao_ligada($db);
    $vistos = [];
    foreach ($db['atendimento']['por_fone'] as $chaveBruta => $ctx) {
        if (!is_array($ctx) || (int) ($ctx['reserva_id'] ?? 0) !== $id) {
            continue;
        }
        $chave = atendimento_fone_chave((string) $chaveBruta);
        if ($chave === '' || isset($vistos[$chave])) {
            if ($chave !== '' && (string) $chaveBruta !== $chave) {
                unset($db['atendimento']['por_fone'][$chaveBruta]);
            }
            continue;
        }
        $vistos[$chave] = true;
        $papel = (string) ($ctx['papel'] ?? 'cliente');
        $ctx['concluida'] = true;
        $ctx['ficha'] = atendimento_montar_ficha($db, $trip, $papel, true);
        // Memória permanece (corrida já concluída). Avaliação automática se ligada.
        if ($pedirNota && empty($ctx['avaliacao_enviada'])) {
            [$ok] = atendimento_enviar_avaliacao($db, $chave, $trip);
            if ($ok) {
                $ctx['avaliacao_enviada'] = true;
            }
        }
        if ((string) $chaveBruta !== $chave) {
            unset($db['atendimento']['por_fone'][$chaveBruta]);
        }
        $db['atendimento']['por_fone'][$chave] = $ctx;
    }
}

function atendimento_contexto(array $db, string $numeroOuJid): ?array
{
    atendimento_store_garantir($db);
    foreach (atendimento_fone_aliases($numeroOuJid) as $alias) {
        $ctx = $db['atendimento']['por_fone'][$alias] ?? null;
        if (is_array($ctx)) {
            return $ctx;
        }
    }
    return null;
}

function atendimento_atualizar(array &$db, string $numeroOuJid, array $patch): void
{
    atendimento_store_garantir($db);
    $chave = atendimento_fone_chave($numeroOuJid);
    if ($chave === '') {
        return;
    }
    $base = null;
    $achou = '';
    foreach (atendimento_fone_aliases($chave) as $alias) {
        if (isset($db['atendimento']['por_fone'][$alias]) && is_array($db['atendimento']['por_fone'][$alias])) {
            $base = $db['atendimento']['por_fone'][$alias];
            $achou = $alias;
            break;
        }
    }
    if ($base === null) {
        return;
    }
    if ($achou !== '' && $achou !== $chave) {
        unset($db['atendimento']['por_fone'][$achou]);
    }
    $db['atendimento']['por_fone'][$chave] = array_merge($base, $patch, [
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
    if (!preg_match('/^(ok|pdf|at|av[1-5])([CM])(\d+)$/', $id, $m)) {
        return null;
    }
    $nota = 0;
    $acao = $m[1];
    if (str_starts_with($acao, 'av')) {
        $nota = (int) substr($acao, 2);
        $acao = 'av';
    }
    return [
        'acao' => $acao,
        'papel' => $m[2] === 'M' ? 'motorista' : 'cliente',
        'reserva_id' => (int) $m[3],
        'nota' => $nota,
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
        'Solicitar atendimento|' . atendimento_btn_id('at', 'cliente', $id),
    ];
    $url = (string) $db['settings']['uazapi_url'];
    $token = (string) $db['settings']['uazapi_token'];
    [$ok, $det] = uazapi_send_menu($url, $token, $numero, $texto, $choices, 'Transfer FLN · reserva #' . $id);
    if ($ok) {
        atendimento_vincular($db, $numero, $id, 'cliente', true);
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
        'Solicitar atendimento|' . atendimento_btn_id('at', 'motorista', $id),
    ];
    $url = (string) $db['settings']['uazapi_url'];
    $token = (string) $db['settings']['uazapi_token'];
    [$ok, $det] = uazapi_send_menu($url, $token, $numero, $texto, $choices, 'Roteiro sem dados financeiros · #' . $id);
    if ($ok) {
        atendimento_vincular($db, $numero, $id, 'motorista', true);
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

function atendimento_base_reserva(array $db, array $trip, string $papel = 'cliente', bool $concluida = false, string $ficha = ''): string
{
    if (trim($ficha) === '') {
        $ficha = atendimento_montar_ficha($db, $trip, $papel, $concluida);
    }
    return "FICHA GRAVADA NA COMUNICAÇÃO (única fonte permitida):\n" . $ficha;
}

function atendimento_openai(array $db, string $pergunta, array $trip, string $papel, bool $concluida = false, string $ficha = ''): array
{
    $key = atendimento_openai_key($db);
    if ($key === '') {
        return [false, 'Ainda não tenho a chave OpenAI configurada no painel. Peça um atendente humano se precisar.'];
    }
    if (!function_exists('curl_init')) {
        return [false, 'Sem cURL no servidor.'];
    }
    $marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
    $palavra = atendimento_palavra_pausa($db);
    $sistema = "Você é o assistente de WhatsApp da {$marca}.\n"
        . "REGRAS OBRIGATÓRIAS:\n"
        . "1) Responda SOMENTE com base na reserva comunicada abaixo.\n"
        . "2) Se a pergunta for fora desta reserva (clima, outras viagens, preço, política geral, curiosidades), diga que só pode ajudar com esta reserva e sugira falar com atendente.\n"
        . "3) Nunca invente horários, endereços, motoristas ou status.\n"
        . "4) Nunca cite valores, preços, repasses ou dados financeiros.\n"
        . "5) Seja breve, educado e em português do Brasil.\n"
        . "6) Se pedirem humano/atendente, oriente a usar o botão ou digitar a palavra de pausa \"{$palavra}\".\n"
        . ($concluida
            ? "7) A corrida JÁ FOI CONCLUÍDA. Diga isso com clareza. Não trate como viagem futura e não invente um novo agendamento.\n"
            : "7) A corrida ainda não foi concluída. Use só a ficha abaixo.\n")
        . "Papel do interlocutor: {$papel}.\n\n"
        . atendimento_base_reserva($db, $trip, $papel, $concluida, $ficha);

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
    // Frases explícitas — NÃO usar palavras soltas como "pessoa" (gera flood falso).
    $pistas = [
        'falar com atendente',
        'falar com o atendente',
        'falar com uma atendente',
        'quero atendente',
        'chama o atendente',
        'chamar atendente',
        'atendimento humano',
        'falar com humano',
        'falar com alguém',
        'falar com alguem',
        'quero atendimento',
        'falar com o matheus',
        'falar com matheus',
        'chama o matheus',
    ];
    foreach ($pistas as $p) {
        if (str_contains($t, $p)) {
            return true;
        }
    }
    if (preg_match('/\b(atendente|operador)\b/u', $t)) {
        return true;
    }
    return false;
}

function atendimento_pediu_pausa(string $texto, string $palavra): bool
{
    $palavra = mb_strtolower(trim($palavra));
    $texto = mb_strtolower(trim($texto));
    if ($palavra === '' || $texto === '') {
        return false;
    }
    if ($texto === $palavra) {
        return true;
    }
    $quoted = preg_quote($palavra, '/');
    return (bool) preg_match('/(?:^|[^\p{L}\p{N}])' . $quoted . '(?:$|[^\p{L}\p{N}])/u', $texto);
}

function atendimento_msg_id(array $msg, string $chatid, string $texto): string
{
    $id = trim((string) ($msg['id'] ?? $msg['messageid'] ?? $msg['messageId'] ?? ''));
    if ($id !== '') {
        return $id;
    }
    $ts = (string) ($msg['messageTimestamp'] ?? '');
    return sha1($chatid . '|' . $ts . '|' . $texto);
}

function atendimento_ja_visto(array $ctx, string $id): bool
{
    $vistos = $ctx['vistos'] ?? [];
    return $id !== '' && is_array($vistos) && in_array($id, $vistos, true);
}

function atendimento_marcar_visto(array &$db, string $numero, string $id): void
{
    if ($id === '') {
        return;
    }
    $ctx = atendimento_contexto($db, $numero) ?? [];
    $vistos = is_array($ctx['vistos'] ?? null) ? $ctx['vistos'] : [];
    $vistos[] = $id;
    if (count($vistos) > 40) {
        $vistos = array_slice($vistos, -40);
    }
    atendimento_atualizar($db, $numero, ['vistos' => $vistos]);
}

/**
 * Várias mensagens seguidas do contato:
 * só pausa a automação — sem responder e sem avisar o atendente.
 */
function atendimento_registrar_flood(array &$db, string $numero): bool
{
    $ctx = atendimento_contexto($db, $numero) ?? [];
    if (!empty($ctx['flood'])) {
        atendimento_atualizar($db, $numero, ['humano' => true, 'flood' => true]);
        return true;
    }
    $agora = time();
    $burst = [];
    foreach (is_array($ctx['burst'] ?? null) ? $ctx['burst'] : [] as $ts) {
        $ts = (int) $ts;
        if ($ts >= $agora - 45) {
            $burst[] = $ts;
        }
    }
    $burst[] = $agora;
    $em12 = 0;
    $em30 = 0;
    foreach ($burst as $ts) {
        if ($ts >= $agora - 12) {
            $em12++;
        }
        if ($ts >= $agora - 30) {
            $em30++;
        }
    }
    // 3 msgs em 12s ou 5 em 30s = flood (corta cedo para não gastar API).
    $flood = $em12 >= 3 || $em30 >= 5 || count($burst) >= 7;
    atendimento_atualizar($db, $numero, [
        'burst' => array_slice($burst, -14),
        'flood' => $flood,
        'humano' => $flood ? true : !empty($ctx['humano']),
        'flood_em' => $flood ? $agora : (int) ($ctx['flood_em'] ?? 0),
    ]);
    return $flood;
}

function atendimento_texto_eco(array $ctx, string $texto): bool
{
    $ultimo = trim((string) ($ctx['ultimo_texto_enviado'] ?? ''));
    $texto = trim($texto);
    if ($ultimo === '' || $texto === '') {
        return false;
    }
    return $texto === $ultimo || str_contains($ultimo, $texto);
}

/**
 * Avisa o atendente no máximo UMA vez por conversa/telefone.
 * Em flood: nunca avisa — só garante pausa.
 */
function atendimento_avisar_humano_uma_vez(array &$db, string $numeroCliente, array $trip, string $papel, string $trechoMsg): void
{
    $ctx = atendimento_contexto($db, $numeroCliente) ?? [];
    if (!empty($ctx['flood'])) {
        atendimento_atualizar($db, $numeroCliente, ['humano' => true, 'flood' => true]);
        return;
    }
    if (!empty($ctx['avisou_humano'])) {
        atendimento_atualizar($db, $numeroCliente, ['humano' => true]);
        return;
    }
    // Trava o aviso ANTES do envio (evita race de webhooks duplicados).
    atendimento_atualizar($db, $numeroCliente, [
        'humano' => true,
        'avisou_humano' => true,
        'avisou_em' => time(),
    ]);
    atendimento_avisar_humano($db, $numeroCliente, $trip, $papel, $trechoMsg);
}

/**
 * Escala para humano: 1 aviso ao atendente + 1 confirmação ao contato.
 * Se flood ou já avisou: só pausa, sem novas mensagens.
 */
function atendimento_escalar_humano(
    array &$db,
    string $numero,
    array $trip,
    string $papel,
    string $trechoMsg,
    string $confirmacaoCliente
): void {
    $ctx = atendimento_contexto($db, $numero) ?? [];
    if (!empty($ctx['flood'])) {
        atendimento_atualizar($db, $numero, ['humano' => true, 'flood' => true]);
        return;
    }
    $jaAvisou = !empty($ctx['avisou_humano']);
    if (!$jaAvisou && $confirmacaoCliente !== '') {
        // Confirma ao cliente ANTES de marcar pausa (senão a resposta seria bloqueada).
        atendimento_responder_texto($db, $numero, $confirmacaoCliente);
        atendimento_atualizar($db, $numero, [
            'ultimo_envio_em' => time(),
            'ultimo_texto_enviado' => $confirmacaoCliente,
        ]);
    }
    atendimento_avisar_humano_uma_vez($db, $numero, $trip, $papel, $trechoMsg);
}

function atendimento_responder_uma_vez(array &$db, string $numero, string $texto): void
{
    $ctx = atendimento_contexto($db, $numero) ?? [];
    if (!empty($ctx['flood']) || atendimento_pausado($ctx)) {
        return;
    }
    $igual = (string) ($ctx['ultimo_texto_enviado'] ?? '') === $texto
        && (time() - (int) ($ctx['ultimo_envio_em'] ?? 0)) < 180;
    if ($igual) {
        return;
    }
    // Evita rajada de respostas da IA no mesmo segundo.
    if ((time() - (int) ($ctx['ultimo_envio_em'] ?? 0)) < 4) {
        return;
    }
    atendimento_responder_texto($db, $numero, $texto);
    atendimento_atualizar($db, $numero, [
        'ultimo_envio_em' => time(),
        'ultimo_texto_enviado' => $texto,
    ]);
}

function atendimento_avisar_humano(array &$db, string $numeroCliente, array $trip, string $papel, string $trechoMsg): array
{
    $at = atendimento_atendente($db);
    if (strlen($at['numero']) < 12) {
        return [false, 'Telefone do atendente não configurado (Configurações → atendente).'];
    }
    $marca = trim((string) ($db['settings']['nome'] ?? 'Transfer FLN'));
    if ($marca === '') {
        $marca = 'Transfer FLN';
    }
    $ehMot = $papel === 'motorista';
    $quem = $ehMot ? 'motorista' : 'cliente';
    $nomeCliente = atendimento_nome_legivel((string) ($trip['cliente'] ?? ''));
    $foneLegivel = atendimento_fone_legivel($numeroCliente);
    $waLink = atendimento_fone_wa_link($numeroCliente);
    $quando = trim(br_date((string) ($trip['data'] ?? '')) . ' ' . substr((string) ($trip['hora'] ?? ''), 0, 5));
    $origem = trim((string) ($trip['origem'] ?? ''));
    $destino = trim((string) ($trip['destino'] ?? ''));
    $rota = ($origem !== '' && $destino !== '')
        ? ($origem . ' → ' . $destino)
        : ($origem !== '' ? $origem : $destino);
    $status = status_label((string) ($trip['status'] ?? 'reserva'));
    $motivo = trim($trechoMsg);
    if ($motivo === '' || $motivo === 'Pediu atendente pelo botão') {
        $motivo = 'Tocou em *Solicitar atendimento* no WhatsApp';
    }
    $primeiroNome = trim((string) preg_replace('/\s+.*/', '', $at['nome']));
    if ($primeiroNome === '') {
        $primeiroNome = $at['nome'];
    }

    $linhas = [
        '*' . $marca . ' · Pedido de atendimento*',
        '',
        'Olá, ' . $primeiroNome . '!',
        'O *' . $quem . '* pediu atendimento humano no WhatsApp.',
        '',
        '*Reserva #' . (int) ($trip['id'] ?? 0) . '*',
        'Cliente: ' . ($nomeCliente !== '' ? $nomeCliente : '—'),
        'Quem escreveu: ' . ($ehMot ? 'Motorista' : 'Cliente'),
        'Contato: ' . ($foneLegivel !== '' ? $foneLegivel : '—'),
    ];
    if ($waLink !== '') {
        $linhas[] = 'Abrir WhatsApp: ' . $waLink;
    }
    if ($quando !== '' && $quando !== '—') {
        $linhas[] = 'Quando: ' . $quando;
    }
    if ($rota !== '') {
        $linhas[] = 'Rota: ' . $rota;
    }
    if ($status !== '') {
        $linhas[] = 'Situação: ' . $status;
    }
    $linhas[] = 'Motivo: ' . mb_substr($motivo, 0, 220);
    $linhas[] = '';
    $linhas[] = 'A automação desta conversa já foi *pausada*.';
    $linhas[] = 'Pode me responder aqui e pedir o que precisa (ex.: comunicar #' . (int) ($trip['id'] ?? 0) . ', mudar status, criar reserva).';
    $linhas[] = 'Se o canal exclusivo ainda não estiver liberado, envie o *ID* e o *token* do painel (Chat → Configurações) nesta conversa — eu só executo com os dois.';

    $texto = implode("\n", $linhas);
    // Garante quebras reais (evita literal \n se vier de origem escapada).
    $texto = str_replace(["\r\n", "\r", '\\n'], ["\n", "\n", "\n"], $texto);

    if (function_exists('ia_operador_registrar_aviso')) {
        ia_operador_registrar_aviso($db, $trip, $numeroCliente, $papel, $motivo);
    }

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
    if (function_exists('uazapi_digitando')) {
        uazapi_digitando($db['settings'], $numero);
    }
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
    $msgId = atendimento_msg_id($msg, $chatid, $textoBruto);
    if (ia_operador_eh($db, $numero)) {
        ia_operador_entrada($db, $numero, $textoBruto, $msgId);
        return true;
    }
    $ctxPre = atendimento_contexto($db, $numero) ?? [];
    if (atendimento_ja_visto($ctxPre, $msgId)) {
        return true;
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
    atendimento_marcar_visto($db, $numero, $msgId);

    $ctxAtual = atendimento_contexto($db, $numero) ?? [];
    if (atendimento_texto_eco($ctxAtual, $textoBruto)) {
        return true;
    }

    // Já em flood ou pausado: silêncio total (não responde, não avisa de novo).
    if (!empty($ctxAtual['flood']) || atendimento_pausado($ctxAtual)) {
        atendimento_registrar_flood($db, $numero);
        return true;
    }
    // Detecta flood cedo — só pausa, sem resposta e sem avisar o humano.
    if (atendimento_registrar_flood($db, $numero)) {
        return true;
    }

    if ($parsed !== null) {
        $acao = $parsed['acao'];
        if ($acao === 'pdf') {
            [$ok, $det] = atendimento_enviar_pdf($db, $numero, $trip, $papel);
            if (!$ok) {
                atendimento_responder_uma_vez($db, $numero, 'Não consegui enviar o PDF agora. Tente de novo ou fale com um atendente. (' . $det . ')');
            }
            return true;
        }
        if ($acao === 'ok') {
            atendimento_atualizar($db, $numero, ['confirmado' => true]);
            if ($papel === 'motorista') {
                atendimento_responder_uma_vez(
                    $db,
                    $numero,
                    'Ciência confirmada na reserva #' . $reservaId . '. Posso esclarecer detalhes *somente* desta corrida. Se precisar de uma pessoa, toque em *Solicitar atendimento*.'
                );
            } else {
                atendimento_responder_uma_vez(
                    $db,
                    $numero,
                    'Reserva #' . $reservaId . ' confirmada! Posso ajudar com dúvidas *somente* sobre esta viagem. Para o PDF, use *Ver PDF*. Se quiser uma pessoa, toque em *Solicitar atendimento*.'
                );
            }
            return true;
        }
        if ($acao === 'av') {
            $nota = (int) ($parsed['nota'] ?? 0);
            atendimento_atualizar($db, $numero, ['avaliacao_nota' => $nota, 'avaliacao_enviada' => true, 'concluida' => true]);
            atendimento_responder_uma_vez(
                $db,
                $numero,
                'Obrigado pela avaliação da reserva #' . $reservaId . '. A corrida já está concluída.'
            );
            return true;
        }
        if ($acao === 'at') {
            atendimento_escalar_humano($db, $numero, $trip, $papel, 'Pediu atendente pelo botão',
                'Certo! Avisei o atendente (' . atendimento_atendente($db)['nome'] . '). Em breve alguém assume esta conversa.');
            return true;
        }
    }

    $texto = trim($textoBruto);
    if ($texto === '' || in_array(mb_strtolower($texto), ['imagem', 'áudio', 'audio', 'vídeo', 'video', 'documento', 'figurinha', 'localização', 'localizacao', 'contato', 'enquete'], true)) {
        return true;
    }

    $palavra = atendimento_palavra_pausa($db);
    if (atendimento_pediu_pausa($texto, $palavra)) {
        $min = max(0, (int) ($db['settings']['chat_ia_parar_min'] ?? 30));
        if ($min > 0) {
            atendimento_atualizar($db, $numero, ['pausado_ate' => time() + ($min * 60)]);
        }
        $msgCli = 'Automação pausada. ' . atendimento_atendente($db)['nome'] . ' foi avisado e assume esta conversa.'
            . ($min > 0 ? ' (pausa por cerca de ' . $min . ' min)' : '');
        atendimento_escalar_humano($db, $numero, $trip, $papel, 'Pausa da IA: ' . $texto, $msgCli);
        return true;
    }

    if (atendimento_parece_atendente($texto)) {
        atendimento_escalar_humano(
            $db,
            $numero,
            $trip,
            $papel,
            $texto,
            'Entendi! Já avisei ' . atendimento_atendente($db)['nome'] . ' para entrar nesta conversa.'
        );
        return true;
    }

    // Chatbot de texto só com OpenAI + automação ligada (botões PDF/OK/atendente seguem).
    if (!atendimento_ia_global_ativa($db)) {
        $semChave = atendimento_openai_key($db) === '';
        atendimento_responder_uma_vez(
            $db,
            $numero,
            $semChave
                ? 'Ainda não consigo responder por texto: falta a chave OpenAI no painel (Chat → Configurações). Use os botões da reserva ou *Solicitar atendimento*.'
                : 'O atendimento automático por texto está pausado no momento. Use os botões da reserva ou toque em *Solicitar atendimento*.'
        );
        return true;
    }

    $ctxIa = atendimento_contexto($db, $numero) ?? [];
    if (!empty($ctxIa['flood']) || atendimento_pausado($ctxIa)) {
        return true;
    }
    $concluida = !empty($ctxIa['concluida']) || (string) ($trip['status'] ?? '') === 'concluida';
    $ficha = trim((string) ($ctxIa['ficha'] ?? ''));
    if ($ficha === '') {
        $ficha = atendimento_montar_ficha($db, $trip, $papel, $concluida);
        atendimento_atualizar($db, $numero, ['ficha' => $ficha, 'concluida' => $concluida]);
    }
    [$okIa, $resposta] = atendimento_openai($db, $texto, $trip, $papel, $concluida, $ficha);
    if (!$okIa) {
        $resposta = 'No momento não consegui consultar a IA. '
            . 'Posso só falar da reserva #' . $reservaId . '. Se preferir, toque em *Solicitar atendimento*.';
    }
    // Revalida flood/pausa depois da chamada (mensagem pode ter chegado em rajada).
    $ctxPos = atendimento_contexto($db, $numero) ?? [];
    if (!empty($ctxPos['flood']) || atendimento_pausado($ctxPos)) {
        return true;
    }
    atendimento_responder_uma_vez($db, $numero, $resposta);
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

require_once __DIR__ . '/ia_operador.php';
