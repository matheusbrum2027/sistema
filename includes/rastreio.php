<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function rastreio_url(string $token): string
{
    return rtrim(base_url(), '/') . '/' . href('rastrear', ['t' => $token]);
}

function rastreio_erro(string $codigo): string
{
    return match ($codigo) {
        'reserva_obrigatoria' => 'Selecione uma reserva. O link não abre sem ela.',
        'reserva_ausente' => 'Essa reserva não está no painel.',
        'reserva_passada' => 'Só entra reserva com data e hora ainda por ocorrer.',
        'link_aberto' => 'Essa reserva já tem um link aberto para essa pessoa. Encerre o atual antes de criar outro.',
        'link_fechado' => 'Esse link já foi encerrado.',
        'papel_invalido' => 'Escolha cliente ou motorista.',
        default => 'Não foi possível concluir o rastreio.',
    };
}

function rastreio_codigo(Throwable $e): string
{
    $msg = $e->getMessage();
    foreach (['reserva_obrigatoria', 'reserva_ausente', 'reserva_passada', 'link_aberto', 'link_fechado', 'papel_invalido'] as $codigo) {
        if (str_contains($msg, $codigo)) {
            return $codigo;
        }
    }
    return '';
}

function rastreio_admin(): bool
{
    return !empty($_SESSION['auth']) && (($_SESSION['role'] ?? '') === 'admin');
}

function rastreio_painel(): array
{
    $vazio = ['ok' => false, 'futuras' => [], 'ativos' => [], 'historico' => []];
    try {
        $resp = store_rpc('fln_rastreio_painel', ['p_base' => store_base()], 18);
    } catch (Throwable) {
        return $vazio;
    }
    if (!is_array($resp)) {
        return $vazio;
    }
    foreach (['futuras', 'ativos', 'historico'] as $chave) {
        if (!is_array($resp[$chave] ?? null)) {
            $resp[$chave] = [];
        }
    }
    $resp['ok'] = true;
    return $resp;
}

function rastreio_minutos(?string $inicio, ?string $fim): int
{
    if ($inicio === null || trim($inicio) === '') {
        return 0;
    }
    try {
        $a = new DateTimeImmutable($inicio);
        $b = ($fim !== null && trim($fim) !== '') ? new DateTimeImmutable($fim) : new DateTimeImmutable('now');
    } catch (Throwable) {
        return 0;
    }
    $seg = $b->getTimestamp() - $a->getTimestamp();
    return $seg > 0 ? (int) floor($seg / 60) : 0;
}

function rastreio_km_txt(mixed $km): string
{
    return number_format((float) $km, 2, ',', '.');
}

function rastreio_quando_curto(?string $iso): string
{
    if ($iso === null || trim($iso) === '') {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso);
        $d = $d->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    } catch (Throwable) {
        return '';
    }
    return $d->format('d/m/Y, H:i');
}

function rastreio_quando_legivel(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}:\d{2}))?$/', trim($iso), $m)) {
        return '';
    }
    return $m[3] . '/' . $m[2] . '/' . $m[1] . (isset($m[4]) ? ' ' . $m[4] : '');
}

function rastreio_proxima(array $item): array
{
    $agora = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i');
    $ida = trim((string) ($item['ida'] ?? ''));
    $volta = trim((string) ($item['volta'] ?? ''));
    $idaOk = $ida !== '' && $ida > $agora;
    $voltaOk = $volta !== '' && $volta > $agora;
    if ($idaOk && $voltaOk) {
        return $ida <= $volta
            ? ['quando' => $ida, 'perna' => 'ida']
            : ['quando' => $volta, 'perna' => 'volta'];
    }
    if ($voltaOk) {
        return ['quando' => $volta, 'perna' => 'volta'];
    }
    return ['quando' => $ida, 'perna' => 'ida'];
}

function rastreio_frase(array $row): string
{
    $status = (string) ($row['status'] ?? '');
    if ($status === 'ao_vivo') {
        return 'Em deslocamento';
    }
    if ($status === 'aguardando') {
        return trim((string) ($row['comunicado_em'] ?? '')) !== ''
            ? 'Link enviado · aguardando iniciar'
            : 'Ainda não comunicado · aguardando iniciar';
    }
    if ($status === 'concluido') {
        return 'Concluído';
    }
    return 'Encerrado';
}

function rastreio_catalogo(array $db, array $futuras): array
{
    $mots = [];
    foreach ($db['motoristas'] ?? [] as $mot) {
        if (!is_array($mot)) {
            continue;
        }
        $mots[(int) ($mot['id'] ?? 0)] = $mot;
    }
    $saida = [];
    foreach ($futuras as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $idaId = (int) ($row['motorista_id'] ?? 0);
        $voltaId = (int) ($row['motorista_volta_id'] ?? 0);
        $ida = $mots[$idaId] ?? null;
        $volta = $mots[$voltaId] ?? null;
        $saida[] = [
            'id' => $id,
            'cliente' => (string) ($row['cliente'] ?? ''),
            'telefone' => (string) ($row['telefone'] ?? ''),
            'origem' => (string) ($row['origem'] ?? ''),
            'destino' => (string) ($row['destino'] ?? ''),
            'tipo' => (string) ($row['tipo'] ?? ''),
            'ida' => (string) ($row['ida'] ?? ''),
            'volta' => (string) ($row['volta'] ?? ''),
            'mot_nome' => is_array($ida) ? (string) ($ida['nome'] ?? '') : '',
            'mot_tel' => is_array($ida) ? (string) ($ida['telefone'] ?? '') : '',
            'mot_volta_nome' => is_array($volta) ? (string) ($volta['nome'] ?? '') : '',
            'mot_volta_tel' => is_array($volta) ? (string) ($volta['telefone'] ?? '') : '',
        ];
    }
    return $saida;
}

function rastreio_post_admin(): void
{
    $acao = (string) post('action');
    if (!in_array($acao, ['criar_rastreio', 'encerrar_rastreio', 'comunicar_rastreio'], true)) {
        return;
    }
    $volta = href('rastreio');
    if (!rastreio_admin()) {
        redirect(href('entrar'));
    }
    if (!csrf_ok()) {
        flash('Atualize a página e tente de novo.', 'err');
        redirect($volta);
    }
    try {
        if ($acao === 'criar_rastreio') {
            if (!fln_ritmo('rastreio_criar', 30, 3600)) {
                flash('Muitos links agora. Espere um pouco e crie de novo.', 'err');
                redirect($volta);
            }
            $empresa = 'Transfer FLN';
            try {
                $casca = load_db(true, false, 'casca');
                $nome = trim((string) ($casca['settings']['nome'] ?? ''));
                if ($nome !== '') {
                    $empresa = $nome;
                }
            } catch (Throwable) {
                $empresa = 'Transfer FLN';
            }
            store_rpc('fln_rastreio_criar', [
                'p_base' => store_base(),
                'p_reserva' => (int) post('reserva'),
                'p_papel' => (string) post('papel'),
                'p_nome' => (string) post('nome'),
                'p_telefone' => (string) post('telefone'),
                'p_obs' => (string) post('obs'),
                'p_empresa' => $empresa,
            ], 18);
            flash('Link criado. Ele está em Links ativos e já pode ser copiado.');
            redirect(href('rastreio', ['criado' => '1']));
        }
        if ($acao === 'encerrar_rastreio') {
            store_rpc('fln_rastreio_encerrar', ['p_id' => (int) post('id')], 12);
            flash('Link encerrado. O histórico guarda o trajeto.');
            redirect($volta);
        }
        $id = (int) post('id');
        $info = store_rpc('fln_rastreio_comunicar', ['p_id' => $id], 12);
        if (!is_array($info)) {
            flash('Não foi possível ler o link.', 'err');
            redirect($volta);
        }
        $numero = wa_number((string) ($info['telefone'] ?? ''));
        if ($numero === '') {
            flash('Informe o telefone no link antes de comunicar.', 'err');
            redirect($volta);
        }
        $casca = load_db(true, false, 'casca');
        $ajustes = is_array($casca['settings'] ?? null) ? $casca['settings'] : [];
        if (!uazapi_settings_ready($ajustes)) {
            flash('O WhatsApp da operação não está conectado. Use Copiar e envie o link manualmente.', 'err');
            redirect($volta);
        }
        $url = rastreio_url((string) ($info['token'] ?? ''));
        $quem = (string) ($info['nome'] ?? '');
        $texto = "FLN Transfer\n";
        if ($quem !== '') {
            $texto .= $quem . "\n";
        }
        $texto .= 'Reserva #' . (int) ($info['reserva_id'] ?? 0);
        $quando = trim((string) ($info['quando'] ?? ''));
        if ($quando !== '') {
            $texto .= ' · ' . $quando;
        }
        $texto .= "\nAbra o link e toque em Iniciar viagem para compartilhar a localização:\n" . $url;
        [$ok, $detalhe] = uazapi_send_text(
            (string) ($ajustes['uazapi_url'] ?? ''),
            (string) ($ajustes['uazapi_token'] ?? ''),
            $numero,
            $texto,
            18
        );
        if (!$ok) {
            flash('O WhatsApp não enviou. ' . (is_string($detalhe) ? $detalhe : 'Tente de novo.'), 'err');
            redirect($volta);
        }
        store_rpc('fln_rastreio_marcar', ['p_id' => $id], 8);
        flash('Link enviado no WhatsApp.');
        redirect($volta);
    } catch (Throwable $e) {
        $codigo = rastreio_codigo($e);
        flash($codigo !== '' ? rastreio_erro($codigo) : 'Não foi possível concluir o rastreio.', 'err');
        redirect($volta);
    }
}

function rastreio_publico_responder(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    $token = strtolower(trim((string) ($_POST['token'] ?? '')));
    $acao = (string) ($_POST['acao'] ?? '');
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        http_response_code(404);
        echo '{"ok":false}';
        return;
    }
    try {
        if ($acao === 'ping') {
            $resp = store_rpc('fln_rastreio_ping', [
                'p_token' => $token,
                'p_lat' => (float) ($_POST['lat'] ?? 0),
                'p_lng' => (float) ($_POST['lng'] ?? 0),
                'p_precisao' => (float) ($_POST['precisao'] ?? 0),
            ], 8);
        } elseif ($acao === 'fim') {
            $resp = store_rpc('fln_rastreio_fim', ['p_token' => $token], 8);
        } else {
            http_response_code(400);
            echo '{"ok":false}';
            return;
        }
        echo json_encode(is_array($resp) ? $resp : ['ok' => false], JSON_UNESCAPED_UNICODE);
    } catch (Throwable) {
        http_response_code(503);
        echo '{"ok":false}';
    }
}

function rastreio_json_pontos(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!rastreio_admin()) {
        http_response_code(401);
        echo '{"ok":false}';
        return;
    }
    try {
        $resp = store_rpc('fln_rastreio_pontos', ['p_id' => (int) ($_GET['id'] ?? 0)], 10);
        echo json_encode(is_array($resp) ? $resp : ['ok' => false], JSON_UNESCAPED_UNICODE);
    } catch (Throwable) {
        http_response_code(404);
        echo '{"ok":false}';
    }
}

function rastreio_publico(string $token): array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return ['ok' => false];
    }
    try {
        $resp = store_rpc('fln_rastreio_publico', ['p_token' => $token], 8);
    } catch (Throwable) {
        return ['ok' => false, 'erro' => true];
    }
    return is_array($resp) ? $resp : ['ok' => false];
}
