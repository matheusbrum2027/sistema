<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Canal exclusivo do atendente (Matheus).
 *
 * Trava em 3 camadas:
 * 1) WhatsApp = telefone do atendente (aliases BR com/sem 9º dígito)
 * 2) Sessão aberta só após enviar ID exclusivo + token exclusivo juntos
 * 3) Ação precisa estar ligada nas permissões do admin
 *
 * Cliente/motorista nunca entram aqui. Não passa por executar_acao,
 * não mexe em senha, 2FA, instância WhatsApp ou SQL.
 */

function ia_operador_catalogo(): array
{
    return [
        'criar_reserva' => 'Criar reserva',
        'consultar_reserva' => 'Consultar uma reserva',
        'listar_agenda' => 'Listar a agenda de um dia',
        'mudar_status' => 'Mudar o status da reserva',
        'comunicar_reserva' => 'Comunicar a reserva (cliente e motorista atribuído)',
        'faturar_reserva' => 'Marcar reserva como faturada',
        'excluir_reserva' => 'Enviar reserva para o lixo',
    ];
}

function ia_operador_permissoes_padrao(): array
{
    return [
        'criar_reserva' => true,
        'consultar_reserva' => true,
        'listar_agenda' => true,
        'mudar_status' => true,
        'comunicar_reserva' => true,
        'faturar_reserva' => false,
        'excluir_reserva' => false,
    ];
}

function ia_operador_garantir(array &$db): void
{
    $novo = false;
    $id = trim((string) ($db['settings']['ia_operador_id'] ?? ''));
    if (!preg_match('/^FLN[A-F0-9]{8}$/', $id)) {
        $db['settings']['ia_operador_id'] = 'FLN' . strtoupper(bin2hex(random_bytes(4)));
        $novo = true;
    }
    $token = trim((string) ($db['settings']['ia_operador_token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        $db['settings']['ia_operador_token'] = bin2hex(random_bytes(16));
        $novo = true;
    }
    $padrao = ia_operador_permissoes_padrao();
    $atual = is_array($db['settings']['ia_permissoes'] ?? null) ? $db['settings']['ia_permissoes'] : [];
    $limpo = [];
    foreach ($padrao as $chave => $valor) {
        if (!array_key_exists($chave, $atual)) {
            $limpo[$chave] = $valor;
            $novo = true;
            continue;
        }
        $limpo[$chave] = !empty($atual[$chave]);
    }
    $db['settings']['ia_permissoes'] = $limpo;
    if ($novo) {
        $GLOBALS['fln_ia_operador_novo'] = true;
    }
}

function ia_operador_permissoes(array $db): array
{
    $padrao = ia_operador_permissoes_padrao();
    $atual = is_array($db['settings']['ia_permissoes'] ?? null) ? $db['settings']['ia_permissoes'] : [];
    $out = [];
    foreach ($padrao as $chave => $valor) {
        $out[$chave] = array_key_exists($chave, $atual) ? !empty($atual[$chave]) : $valor;
    }
    return $out;
}

function ia_operador_aplicar_permissoes(array &$db, string $lista): void
{
    $padrao = ia_operador_permissoes_padrao();
    $ligados = array_values(array_filter(array_map('trim', explode(',', $lista)), static fn (string $v): bool => $v !== ''));
    $out = [];
    foreach ($padrao as $chave => $_valor) {
        $out[$chave] = in_array($chave, $ligados, true);
    }
    $db['settings']['ia_permissoes'] = $out;
}

function ia_operador_fones_iguais(string $a, string $b): bool
{
    if ($a === '' || $b === '' || !function_exists('atendimento_fone_chave')) {
        return false;
    }
    $a = atendimento_fone_chave($a);
    $b = atendimento_fone_chave($b);
    if ($a === '' || $b === '') {
        return false;
    }
    if (hash_equals($a, $b)) {
        return true;
    }
    if (!function_exists('atendimento_fone_aliases')) {
        return false;
    }
    foreach (atendimento_fone_aliases($a) as $alias) {
        if ($alias !== '' && strlen($alias) === strlen($b) && hash_equals($alias, $b)) {
            return true;
        }
    }
    return false;
}

function ia_operador_eh(array $db, string $numero): bool
{
    if ($numero === '' || !function_exists('atendimento_atendente')) {
        return false;
    }
    $at = atendimento_atendente($db);
    $esperado = atendimento_fone_chave((string) ($at['numero'] ?? ''));
    if ($esperado === '' || strlen($esperado) < 12) {
        return false;
    }
    return ia_operador_fones_iguais($esperado, $numero);
}

function ia_operador_sessao_ok(array $db, string $numero): bool
{
    $sessao = $db['atendimento']['operador']['sessao'] ?? null;
    if (!is_array($sessao)) {
        return false;
    }
    $id = (string) ($db['settings']['ia_operador_id'] ?? '');
    $token = (string) ($db['settings']['ia_operador_token'] ?? '');
    $fone = (string) ($sessao['fone'] ?? '');
    $idSalvo = (string) ($sessao['id'] ?? '');
    $hashSalvo = (string) ($sessao['token_hash'] ?? '');
    if ($id === '' || $token === '' || $fone === '' || $idSalvo === '' || $hashSalvo === '') {
        return false;
    }
    if (!ia_operador_fones_iguais($fone, $numero)) {
        return false;
    }
    if (strlen($idSalvo) !== strlen($id) || !hash_equals($idSalvo, $id)) {
        return false;
    }
    $hash = hash('sha256', $token);
    if (strlen($hashSalvo) !== strlen($hash) || !hash_equals($hashSalvo, $hash)) {
        return false;
    }
    // Sessão expira com ociosidade (12h) ou no máximo 7 dias.
    $desde = (int) ($sessao['desde'] ?? 0);
    $ultimo = (int) ($sessao['ultimo'] ?? $desde);
    $agora = time();
    if ($desde <= 0 || ($agora - $desde) > 604800 || ($agora - $ultimo) > 43200) {
        return false;
    }
    return true;
}

/** Guarda o último aviso ao Matheus para ele mandar a IA agir nessa reserva. */
function ia_operador_registrar_aviso(array &$db, array $trip, string $numeroCliente, string $papel, string $motivo): void
{
    atendimento_store_garantir($db);
    if (!isset($db['atendimento']['operador']) || !is_array($db['atendimento']['operador'])) {
        $db['atendimento']['operador'] = ['vistos' => [], 'sessao' => null, 'pedido_em' => 0, 'pendente' => null];
    }
    $db['atendimento']['operador']['pendente'] = [
        'reserva_id' => (int) ($trip['id'] ?? 0),
        'cliente' => (string) ($trip['cliente'] ?? ''),
        'de' => atendimento_fone_chave($numeroCliente),
        'papel' => $papel === 'motorista' ? 'motorista' : 'cliente',
        'motivo' => mb_substr(trim($motivo), 0, 220),
        'em' => time(),
    ];
}

function ia_operador_credenciais_na_mensagem(string $texto, string $id, string $token): bool
{
    if ($id === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        return false;
    }
    if (!str_contains(strtoupper($texto), $id)) {
        return false;
    }
    return (bool) preg_match('/(?<![a-f0-9])' . preg_quote($token, '/') . '(?![a-f0-9])/i', $texto);
}

function ia_operador_limpar_segredo(string $texto, string $id, string $token): string
{
    $limpo = preg_replace('/' . preg_quote($token, '/') . '/i', ' ', $texto) ?? $texto;
    $limpo = str_ireplace($id, ' ', $limpo);
    $limpo = trim(preg_replace('/\s+/', ' ', $limpo) ?? $limpo);
    return $limpo;
}

function ia_operador_so_cumprimento(string $texto): bool
{
    $t = mb_strtolower(trim($texto));
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $t) ?? $t;
    $t = trim($t);
    if ($t === '' || mb_strlen($t) < 8) {
        return true;
    }
    return in_array($t, [
        'ok', 'obrigado', 'obrigada', 'valeu', 'blz', 'beleza', 'entendi',
        'certo', 'sim', 'nao', 'não', 'show', 'perfeito', 'combinado', 'pode deixar',
    ], true);
}

function ia_operador_entrada(array &$db, string $numero, string $textoBruto, string $msgId): void
{
    atendimento_store_garantir($db);
    if (!isset($db['atendimento']['operador']) || !is_array($db['atendimento']['operador'])) {
        $db['atendimento']['operador'] = ['vistos' => [], 'sessao' => null, 'pedido_em' => 0, 'pendente' => null];
    }
    $vistos = is_array($db['atendimento']['operador']['vistos'] ?? null)
        ? $db['atendimento']['operador']['vistos']
        : [];
    if ($msgId !== '' && in_array($msgId, $vistos, true)) {
        return;
    }
    if ($msgId !== '') {
        $vistos[] = $msgId;
        if (count($vistos) > 40) {
            $vistos = array_slice($vistos, -40);
        }
        $db['atendimento']['operador']['vistos'] = array_values($vistos);
    }

    // Camada 1: só o WhatsApp do atendente chega aqui (ia_operador_eh).
    $texto = trim($textoBruto);
    $id = (string) ($db['settings']['ia_operador_id'] ?? '');
    $token = (string) ($db['settings']['ia_operador_token'] ?? '');
    $numero = atendimento_fone_chave($numero);
    $liberouAgora = false;
    if (ia_operador_credenciais_na_mensagem($texto, $id, $token)) {
        // Camada 2: abre sessão exclusiva só neste número, com hash do token.
        $db['atendimento']['operador']['sessao'] = [
            'fone' => $numero,
            'id' => $id,
            'token_hash' => hash('sha256', $token),
            'desde' => time(),
            'ultimo' => time(),
        ];
        $db['atendimento']['operador']['pedido_em'] = 0;
        $texto = ia_operador_limpar_segredo($texto, $id, $token);
        $liberouAgora = true;
    }

    if (!ia_operador_sessao_ok($db, $numero)) {
        if ($liberouAgora === false && ia_operador_credenciais_na_mensagem($textoBruto, $id, $token) === false) {
            // Credenciais erradas/parciais: nunca confirma o que faltou.
            if (preg_match('/FLN[A-F0-9]{8}/i', $textoBruto) || preg_match('/\b[a-f0-9]{32}\b/i', $textoBruto)) {
                atendimento_responder_texto(
                    $db,
                    $numero,
                    'ID ou token inválidos nesta conversa. Envie o *ID exclusivo* e o *token exclusivo* juntos, exatamente como estão no painel. Sem os dois corretos eu não executo nada.'
                );
                return;
            }
        }
        if (ia_operador_so_cumprimento($texto)) {
            return;
        }
        $pedido = (int) ($db['atendimento']['operador']['pedido_em'] ?? 0);
        if ($pedido > 0 && (time() - $pedido) < 900) {
            return;
        }
        $db['atendimento']['operador']['pedido_em'] = time();
        atendimento_responder_texto(
            $db,
            $numero,
            'Canal exclusivo bloqueado. Envie nesta conversa o *ID exclusivo* e o *token exclusivo* juntos (Configurações do chat no painel). Sem os dois eu não crio reserva, não mudo status e não comunico ninguém.'
        );
        return;
    }

    if (isset($db['atendimento']['operador']['sessao']) && is_array($db['atendimento']['operador']['sessao'])) {
        $db['atendimento']['operador']['sessao']['ultimo'] = time();
    }

    $permissoes = ia_operador_permissoes($db);
    $ativas = [];
    $rotulos = [];
    foreach ($permissoes as $chave => $ligada) {
        if ($ligada) {
            $ativas[] = $chave;
            $rotulos[] = ia_operador_catalogo()[$chave] ?? $chave;
        }
    }

    if (ia_operador_so_cumprimento($texto)) {
        if ($liberouAgora) {
            $lista = $rotulos !== [] ? implode('; ', $rotulos) : 'nenhuma (marque no painel)';
            atendimento_responder_texto(
                $db,
                $numero,
                'Acesso exclusivo liberado. Eu executo sempre o que você pedir, desde que esteja liberado nas permissões: ' . $lista . '. Pode mandar criar reserva, consultar, agenda, status, comunicar etc.'
            );
        }
        return;
    }

    // Camada 3: só ações marcadas pelo admin.
    if ($ativas === []) {
        atendimento_responder_texto($db, $numero, 'O admin não marcou nenhuma ação para a IA executar. Nada foi feito.');
        return;
    }
    if (atendimento_openai_key($db) === '') {
        atendimento_responder_texto($db, $numero, 'A chave OpenAI ainda não está no painel. Sem ela eu não interpreto o pedido e não executo nada.');
        return;
    }

    $pendente = is_array($db['atendimento']['operador']['pendente'] ?? null)
        ? $db['atendimento']['operador']['pendente']
        : null;
    [$ok, $decisao] = ia_operador_decidir($db, $texto, $ativas, $pendente);
    if (!$ok || !is_array($decisao)) {
        atendimento_responder_texto($db, $numero, 'Não consegui interpretar o pedido com segurança. Nada foi executado. Reformule em uma frase.');
        return;
    }
    $acao = (string) ($decisao['acao'] ?? 'nenhuma');
    $campos = is_array($decisao['campos'] ?? null) ? $decisao['campos'] : [];
    $fala = trim((string) ($decisao['resposta'] ?? ''));
    $catalogo = ia_operador_catalogo();
    if ($acao === '' || $acao === 'nenhuma' || !isset($catalogo[$acao])) {
        atendimento_responder_texto(
            $db,
            $numero,
            $fala !== '' ? mb_substr($fala, 0, 700) : 'Me diga a ação e os dados (ex.: criar reserva com cliente, telefone, origem, destino, data, hora e valor).'
        );
        return;
    }
    if (empty($permissoes[$acao])) {
        atendimento_responder_texto($db, $numero, 'A ação "' . $catalogo[$acao] . '" está bloqueada nas permissões do admin. Nada foi executado.');
        return;
    }

    // Com sessão + permissão: executa sempre (sem pedir confirmação extra).
    $resultado = ia_operador_executar($db, $acao, $campos);
    atendimento_responder_texto($db, $numero, $resultado);
}

function ia_operador_decidir(array $db, string $pedido, array $ativas, ?array $pendente = null): array
{
    $key = atendimento_openai_key($db);
    if ($key === '' || !function_exists('curl_init')) {
        return [false, null];
    }
    $linhas = [];
    foreach ($ativas as $chave) {
        $linhas[] = '- ' . $chave . ' (' . (ia_operador_catalogo()[$chave] ?? $chave) . ')';
    }
    $ctxPend = '';
    if (is_array($pendente) && (int) ($pendente['reserva_id'] ?? 0) > 0 && (time() - (int) ($pendente['em'] ?? 0)) < 86400) {
        $ctxPend = "Contexto do último aviso ao operador (use o id se ele disser \"essa\", \"desta\", \"a reserva\" sem número):\n"
            . 'reserva_id=' . (int) $pendente['reserva_id']
            . '; cliente=' . (string) ($pendente['cliente'] ?? '')
            . '; papel_quem_pediu=' . (string) ($pendente['papel'] ?? '')
            . '; motivo=' . (string) ($pendente['motivo'] ?? '') . "\n";
    }
    $sistema = "Você traduz pedidos do operador Matheus em UMA ação do sistema Transfer FLN.\n"
        . "Ele já autenticou com ID e token exclusivos. Se o pedido for claro e a ação estiver permitida, escolha a ação — NÃO peça confirmação.\n"
        . "Responda somente JSON com as chaves acao, campos e resposta.\n"
        . "acao deve ser exatamente uma destas: " . implode(', ', $ativas) . ", ou nenhuma.\n"
        . "Ações permitidas agora:\n" . implode("\n", $linhas) . "\n"
        . $ctxPend
        . "campos conforme a ação:\n"
        . "criar_reserva: cliente, telefone, origem, destino, data (YYYY-MM-DD), hora (HH:MM), pax (inteiro), valor (número), tipo (ida, volta ou ida_volta), obs, comunicar (true ou false se ele pediu para avisar também).\n"
        . "consultar_reserva: id (inteiro).\n"
        . "listar_agenda: data (YYYY-MM-DD; se não disser o dia, use hoje).\n"
        . "mudar_status: id, status (pendente, reserva, andamento, concluida ou cancelada).\n"
        . "comunicar_reserva: id.\n"
        . "faturar_reserva: id, desfazer (true só se ele pediu para tirar o faturamento).\n"
        . "excluir_reserva: id.\n"
        . "Se faltar um dado obrigatório, use acao nenhuma e peça só o que falta em resposta.\n"
        . "Não invente telefone, endereço, data, hora, valor ou id.\n"
        . "Não repita credenciais, tokens ou IDs de acesso. Não diga que já executou: o sistema executa depois.\n"
        . "Hoje é " . today()->format('Y-m-d') . " (America/Sao_Paulo).";

    $payload = [
        'model' => 'gpt-4o-mini',
        'temperature' => 0,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $sistema],
            ['role' => 'user', 'content' => mb_substr($pedido, 0, 1200)],
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
    if (function_exists('curl_ca_nativo')) {
        curl_ca_nativo($ch);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [false, null];
    }
    $json = json_decode((string) $raw, true);
    $txt = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    $txt = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $txt) ?? $txt;
    $decisao = json_decode($txt, true);
    if (!is_array($decisao)) {
        return [false, null];
    }
    return [true, $decisao];
}

function ia_operador_executar(array &$db, string $acao, array $campos): string
{
    try {
        return match ($acao) {
            'criar_reserva' => ia_operador_criar($db, $campos),
            'consultar_reserva' => ia_operador_consultar($db, $campos),
            'listar_agenda' => ia_operador_listar($db, $campos),
            'mudar_status' => ia_operador_status($db, $campos),
            'comunicar_reserva' => ia_operador_comunicar($db, $campos),
            'faturar_reserva' => ia_operador_faturar($db, $campos),
            'excluir_reserva' => ia_operador_excluir($db, $campos),
            default => 'Essa ação não está no canal exclusivo. Nada foi executado.',
        };
    } catch (Throwable $e) {
        error_log('fln ia operador: ' . $e->getMessage());
        return 'A ação não foi concluída. Nada parcial deve ser considerado feito. Tente de novo com os dados completos.';
    }
}

function ia_operador_campo(array $campos, string $chave): string
{
    return trim((string) ($campos[$chave] ?? ''));
}

function ia_operador_data(string $bruto): string
{
    $bruto = trim($bruto);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $bruto, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $bruto : '';
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $bruto, $m)) {
        if (!checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return '';
        }
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
}

function ia_operador_hora(string $bruto): string
{
    $bruto = trim($bruto);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $bruto, $m)) {
        return '';
    }
    $h = (int) $m[1];
    $min = (int) $m[2];
    if ($h > 23 || $min > 59) {
        return '';
    }
    return sprintf('%02d:%02d', $h, $min);
}

function ia_operador_valor(mixed $bruto): ?float
{
    if (is_int($bruto) || is_float($bruto)) {
        return round((float) $bruto, 2);
    }
    $txt = trim((string) $bruto);
    if ($txt === '' || !preg_match('/^\d{1,7}([.,]\d{1,2})?$/', $txt)) {
        return null;
    }
    return round((float) str_replace(',', '.', $txt), 2);
}

function ia_operador_status_normal(string $bruto): string
{
    $t = mb_strtolower(trim($bruto));
    $t = str_replace(['í', 'á', 'ã'], ['i', 'a', 'a'], $t);
    return match ($t) {
        'pendente' => 'pendente',
        'reserva', 'reservada', 'reservado' => 'reserva',
        'andamento', 'em andamento' => 'andamento',
        'concluida', 'concluido', 'concluída' => 'concluida',
        'cancelada', 'cancelado' => 'cancelada',
        default => '',
    };
}

function ia_operador_criar(array &$db, array $campos): string
{
    $cliente = clip(ia_operador_campo($campos, 'cliente'), 80);
    $telefone = wa_number(ia_operador_campo($campos, 'telefone'));
    $origem = clip(ia_operador_campo($campos, 'origem'), 120);
    $destino = clip(ia_operador_campo($campos, 'destino'), 120);
    $data = ia_operador_data(ia_operador_campo($campos, 'data'));
    $hora = ia_operador_hora(ia_operador_campo($campos, 'hora'));
    $valor = ia_operador_valor($campos['valor'] ?? '');
    $faltando = [];
    if ($cliente === '') {
        $faltando[] = 'cliente';
    }
    if (strlen($telefone) < 12) {
        $faltando[] = 'telefone';
    }
    if ($origem === '') {
        $faltando[] = 'origem';
    }
    if ($destino === '') {
        $faltando[] = 'destino';
    }
    if ($data === '') {
        $faltando[] = 'data';
    }
    if ($hora === '') {
        $faltando[] = 'hora';
    }
    if ($valor === null || $valor <= 0) {
        $faltando[] = 'valor';
    }
    if ($faltando !== []) {
        return 'A reserva não foi criada. Falta: ' . implode(', ', $faltando) . '.';
    }
    $tipo = ia_operador_campo($campos, 'tipo');
    if (!in_array($tipo, ['ida', 'volta', 'ida_volta'], true)) {
        $tipo = 'ida';
    }
    $pax = (int) ($campos['pax'] ?? 1);
    if ($pax < 1 || $pax > 99) {
        $pax = 1;
    }
    $trip = blank_trip(0);
    $trip['id'] = proximo_id_transfer($db);
    $trip['cliente'] = $cliente;
    $trip['telefone'] = $telefone;
    $trip['origem'] = $origem;
    $trip['destino'] = $destino;
    $trip['data'] = $data;
    $trip['hora'] = $hora;
    $trip['valor'] = $valor;
    $trip['tipo'] = $tipo;
    $trip['pax'] = $pax;
    $trip['status'] = 'pendente';
    $trip['canal'] = 'whatsapp';
    $obs = clip(ia_operador_campo($campos, 'obs'), 400);
    $trip['obs'] = $obs !== '' ? $obs : 'Criada pelo canal exclusivo do atendente.';
    $db['transfers'][] = $trip;
    sync_trip_finance($db, (int) $trip['id']);
    if (function_exists('aviso_reservas_novas')) {
        aviso_reservas_novas($db, [$trip]);
    }
    $msg = 'Reserva #' . (int) $trip['id'] . ' criada. ' . $cliente . ', ' . br_date($data) . ' ' . $hora . ', ' . $origem . ' → ' . $destino . '.';
    $querComunicar = !empty($campos['comunicar']) && $campos['comunicar'] !== 'false' && $campos['comunicar'] !== '0';
    if ($querComunicar) {
        if (empty(ia_operador_permissoes($db)['comunicar_reserva'])) {
            $msg .= ' A comunicação está bloqueada nas permissões, então o card não foi enviado.';
        } else {
            $msg .= ' ' . ia_operador_comunicar($db, ['id' => (int) $trip['id']]);
        }
    }
    return $msg;
}

function ia_operador_consultar(array $db, array $campos): string
{
    $id = (int) ($campos['id'] ?? 0);
    $trip = $id > 0 ? atendimento_trip($db, $id) : null;
    if ($trip === null) {
        return 'Reserva #' . $id . ' não encontrada.';
    }
    return 'Reserva #' . $id
        . ' · ' . status_label((string) ($trip['status'] ?? ''))
        . "\n" . (string) ($trip['cliente'] ?? '')
        . ' · ' . atendimento_fone_legivel((string) ($trip['telefone'] ?? ''))
        . "\n" . br_date((string) ($trip['data'] ?? '')) . ' ' . (string) ($trip['hora'] ?? '')
        . "\n" . (string) ($trip['origem'] ?? '') . ' → ' . (string) ($trip['destino'] ?? '')
        . "\nValor: R$ " . number_format((float) ($trip['valor'] ?? 0), 2, ',', '.');
}

function ia_operador_listar(array $db, array $campos): string
{
    $data = ia_operador_data(ia_operador_campo($campos, 'data'));
    if ($data === '') {
        $data = today()->format('Y-m-d');
    }
    $linhas = [];
    foreach ($db['transfers'] as $trip) {
        if (!is_array($trip) || (string) ($trip['data'] ?? '') !== $data) {
            continue;
        }
        $linhas[] = '#' . (int) ($trip['id'] ?? 0)
            . ' ' . (string) ($trip['hora'] ?? '')
            . ' ' . (string) ($trip['cliente'] ?? '')
            . ' · ' . (string) ($trip['origem'] ?? '') . ' → ' . (string) ($trip['destino'] ?? '')
            . ' (' . status_label((string) ($trip['status'] ?? '')) . ')';
        if (count($linhas) >= 20) {
            break;
        }
    }
    if ($linhas === []) {
        return 'Nenhuma reserva em ' . br_date($data) . '.';
    }
    return 'Agenda de ' . br_date($data) . ":\n" . implode("\n", $linhas);
}

function ia_operador_status(array &$db, array $campos): string
{
    $id = (int) ($campos['id'] ?? 0);
    $novo = ia_operador_status_normal(ia_operador_campo($campos, 'status'));
    $i = $id > 0 ? index_of($db['transfers'], $id) : null;
    if ($i === null) {
        return 'Reserva #' . $id . ' não encontrada. Status não alterado.';
    }
    if ($novo === '') {
        return 'Status não alterado. Use pendente, reserva, andamento, concluída ou cancelada.';
    }
    $antigo = (string) ($db['transfers'][$i]['status'] ?? '');
    if ($antigo === $novo) {
        return 'A reserva #' . $id . ' já está ' . status_label($novo) . '.';
    }
    $db['transfers'][$i]['status'] = $novo;
    apply_status_side_effects($db, $db['transfers'][$i], $antigo, $novo);
    if (function_exists('aviso_mudanca_status')) {
        aviso_mudanca_status($db, $db['transfers'][$i], $antigo, $novo);
    }
    sync_trip_finance($db, $id);
    return 'Reserva #' . $id . ' agora está ' . status_label($novo) . '.';
}

function ia_operador_comunicar(array &$db, array $campos): string
{
    $id = (int) ($campos['id'] ?? 0);
    $trip = $id > 0 ? atendimento_trip($db, $id) : null;
    if ($trip === null) {
        return 'Reserva #' . $id . ' não encontrada. Nada foi comunicado.';
    }
    $operador = atendimento_fone_chave((string) (atendimento_atendente($db)['numero'] ?? ''));
    $enviados = 0;
    $falhas = 0;
    $pulouOpcional = 0;
    foreach (destinatarios_comunicar($db, $trip, '') as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item['opcional'])) {
            $pulouOpcional++;
            continue;
        }
        $numero = atendimento_fone_chave((string) ($item['numero'] ?? ''));
        if ($numero === '' || strlen($numero) < 12) {
            $falhas++;
            continue;
        }
        if ($operador !== '' && hash_equals($operador, $numero)) {
            continue;
        }
        $item['numero'] = $numero;
        $item['viagem'] = $id;
        [$ok] = whatsapp_enviar_manual($db, $item, 20);
        if ($ok) {
            $enviados++;
        } else {
            $falhas++;
        }
    }
    if ($enviados === 0) {
        $extra = $pulouOpcional > 0 ? ' Nenhum motorista está atribuído a esta reserva, então a frota inteira não foi avisada.' : '';
        return 'Reserva #' . $id . ' não teve card enviado.' . $extra;
    }
    $msg = 'Reserva #' . $id . ' comunicada para ' . $enviados . ' contato' . ($enviados === 1 ? '' : 's') . '.';
    if ($falhas > 0) {
        $msg .= ' ' . $falhas . ' ficaram sem telefone válido.';
    }
    return $msg;
}

function ia_operador_faturar(array &$db, array $campos): string
{
    $id = (int) ($campos['id'] ?? 0);
    $i = $id > 0 ? index_of($db['transfers'], $id) : null;
    if ($i === null) {
        return 'Reserva #' . $id . ' não encontrada. Faturamento não alterado.';
    }
    $desfazer = !empty($campos['desfazer']) && $campos['desfazer'] !== 'false' && $campos['desfazer'] !== '0';
    if ($desfazer) {
        $db['transfers'][$i]['faturado'] = false;
        $db['transfers'][$i]['faturado_em'] = null;
        return 'Faturamento removido da reserva #' . $id . '.';
    }
    if (!empty($db['transfers'][$i]['faturado'])) {
        $fatura = (string) ($db['transfers'][$i]['fatura'] ?? '');
        return 'A reserva #' . $id . ' já está faturada' . ($fatura !== '' ? ' (' . $fatura . ')' : '') . '.';
    }
    $db['transfers'][$i]['faturado'] = true;
    $db['transfers'][$i]['faturado_em'] = today()->format('Y-m-d');
    if (empty($db['transfers'][$i]['fatura'])) {
        if (!isset($db['seq']['fatura'])) {
            $db['seq']['fatura'] = 1;
        }
        $num = (int) $db['seq']['fatura'];
        $db['seq']['fatura'] = $num + 1;
        $db['transfers'][$i]['fatura'] = 'FAT-' . today()->format('Y') . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }
    return 'Reserva #' . $id . ' faturada (' . (string) $db['transfers'][$i]['fatura'] . ').';
}

function ia_operador_excluir(array &$db, array $campos): string
{
    $id = (int) ($campos['id'] ?? 0);
    $i = $id > 0 ? index_of($db['transfers'], $id) : null;
    if ($i === null) {
        return 'Reserva #' . $id . ' não encontrada. Nada foi excluído.';
    }
    if (!empty($db['transfers'][$i]['pago'])) {
        return 'A reserva #' . $id . ' já foi paga e não foi excluída. Use cancelar.';
    }
    foreach ($db['lancamentos'] as $lancamento) {
        if ((int) ($lancamento['transfer_id'] ?? 0) === $id && ($lancamento['status'] ?? '') === 'pago') {
            return 'A reserva #' . $id . ' tem lançamento pago e não foi excluída. Use cancelar.';
        }
    }
    lixo_reservas_garantir($db);
    $trip = $db['transfers'][$i];
    $lancs = [];
    $db['lancamentos'] = array_values(array_filter(
        $db['lancamentos'],
        static function (array $row) use ($id, &$lancs): bool {
            if ((int) ($row['transfer_id'] ?? 0) !== $id) {
                return true;
            }
            if (($row['status'] ?? '') === 'pago') {
                return true;
            }
            $lancs[] = $row;
            return false;
        }
    ));
    array_splice($db['transfers'], $i, 1);
    $db['lixo_reservas'][] = [
        'id' => $id,
        'excluida_em' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i'),
        'transfer' => $trip,
        'lancamentos' => $lancs,
    ];
    return 'Reserva #' . $id . ' foi para o lixo. Dá para restaurar no painel em até 30 dias.';
}
