<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function analise_admin(): bool
{
    return !empty($_SESSION['auth']) && (($_SESSION['role'] ?? '') === 'admin');
}

function analise_normalizar_lugar(string $texto): string
{
    $t = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    if ($t === '') {
        return 'Não informado';
    }
    $low = mb_strtolower($t);
    $low = strtr($low, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    $mapa = [
        'aeroporto' => 'Aeroporto Hercílio Luz',
        'hercilio' => 'Aeroporto Hercílio Luz',
        'fln' => 'Aeroporto Hercílio Luz',
        'canasvieiras' => 'Canasvieiras',
        'ingleses' => 'Ingleses',
        'jurere' => 'Jurerê',
        'lagoa' => 'Lagoa da Conceição',
        'campeche' => 'Campeche',
        'centro' => 'Centro de Florianópolis',
        'beira-mar' => 'Centro de Florianópolis',
        'beiramar' => 'Centro de Florianópolis',
        'sao jose' => 'São José',
        'kobrasol' => 'São José',
        'palhoca' => 'Palhoça',
        'biguacu' => 'Biguaçu',
        'balneario' => 'Balneário Camboriú',
        'camboriu' => 'Balneário Camboriú',
        'itajai' => 'Itajaí',
        'rio vermelho' => 'Rio Vermelho',
    ];
    foreach ($mapa as $alias => $nome) {
        if (str_contains($low, $alias)) {
            return $nome;
        }
    }
    if (mb_strlen($t) > 42) {
        return mb_substr($t, 0, 40) . '…';
    }
    return $t;
}

function analise_coleta(array $db, string $periodo = '90d'): array
{
    $hoje = today();
    [$start, $end] = match ($periodo) {
        '30d' => [$hoje->modify('-29 days'), $hoje],
        '12m' => [$hoje->modify('first day of this month')->modify('-11 months'), $hoje],
        default => [$hoje->modify('-89 days'), $hoje],
    };

    $trips = array_values(array_filter(
        $db['transfers'] ?? [],
        static function (array $t) use ($start, $end): bool {
            if (($t['status'] ?? '') === 'cancelada') {
                return false;
            }
            return date_in((string) ($t['data'] ?? ''), $start, $end);
        }
    ));

    $porDest = [];
    $porOrig = [];
    $porStatus = [];
    $receita = 0.0;
    foreach ($trips as $t) {
        $dest = analise_normalizar_lugar((string) ($t['destino'] ?? ''));
        $orig = analise_normalizar_lugar((string) ($t['origem'] ?? ''));
        $st = (string) ($t['status'] ?? 'reserva');
        $val = (float) ($t['valor'] ?? 0);
        $receita += $val;
        if (!isset($porDest[$dest])) {
            $porDest[$dest] = ['local' => $dest, 'viagens' => 0, 'receita' => 0.0];
        }
        if (!isset($porOrig[$orig])) {
            $porOrig[$orig] = ['local' => $orig, 'viagens' => 0, 'receita' => 0.0];
        }
        $porDest[$dest]['viagens']++;
        $porDest[$dest]['receita'] += $val;
        $porOrig[$orig]['viagens']++;
        $porOrig[$orig]['receita'] += $val;
        $porStatus[$st] = ($porStatus[$st] ?? 0) + 1;
    }

    $rank = static function (array $rows): array {
        $rows = array_values($rows);
        usort($rows, static fn(array $a, array $b): int => $b['viagens'] <=> $a['viagens'] ?: $b['receita'] <=> $a['receita']);
        return array_slice($rows, 0, 8);
    };

    $motAtivos = count(array_filter(
        $db['motoristas'] ?? [],
        static fn($m): bool => is_array($m) && ($m['status'] ?? '') === 'ativo'
    ));
    $veiculos = count(array_filter(
        $db['veiculos'] ?? [],
        static fn($v): bool => is_array($v) && ($v['status'] ?? '') === 'ativo'
    ));

    return [
        'periodo' => $periodo,
        'periodo_label' => match ($periodo) {
            '30d' => 'Últimos 30 dias',
            '12m' => 'Últimos 12 meses',
            default => 'Últimos 90 dias',
        },
        'de' => $start->format('d/m/Y'),
        'ate' => $end->format('d/m/Y'),
        'resumo' => [
            'viagens' => count($trips),
            'receita' => round($receita, 2),
            'ticket' => count($trips) > 0 ? round($receita / count($trips), 2) : 0.0,
            'clientes' => count($db['clientes'] ?? []),
            'motoristas_ativos' => $motAtivos,
            'veiculos_ativos' => $veiculos,
            'solicitacoes' => count($db['inbox'] ?? []),
            'por_status' => $porStatus,
        ],
        'top_destinos' => $rank($porDest),
        'top_origens' => $rank($porOrig),
        'empresa' => (string) ($db['settings']['nome'] ?? 'Transfer FLN'),
        'cidade_base' => trim((string) ($db['settings']['cidade'] ?? 'Florianópolis') . ' / ' . (string) ($db['settings']['uf'] ?? 'SC')),
    ];
}

function analise_openai_relatorio(array $db, array $dados): array
{
    $key = function_exists('atendimento_openai_key') ? atendimento_openai_key($db) : trim((string) ($db['settings']['chat_openai_key'] ?? ''));
    if ($key === '') {
        return [false, null, 'Configure a chave OpenAI no chat (Configurações) ou em OPENAI_API_KEY.'];
    }
    if (!function_exists('curl_init')) {
        return [false, null, 'cURL indisponível no servidor.'];
    }

    $payloadDados = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sistema = "Você é o analista estratégico da operação de transfer executivo {$dados['empresa']} em {$dados['cidade_base']}.\n"
        . "Use APENAS os dados JSON fornecidos. Não invente números.\n"
        . "Responda SOMENTE um JSON válido (sem markdown) com este formato:\n"
        . "{\n"
        . "  \"titulo\": string,\n"
        . "  \"resumo_executivo\": string (2-4 frases),\n"
        . "  \"locais_destaque\": [{\"local\": string, \"motivo\": string, \"prioridade\": \"alta|media|baixa\"}],\n"
        . "  \"expansao\": [{\"titulo\": string, \"acao\": string, \"impacto\": string}],\n"
        . "  \"riscos\": [string],\n"
        . "  \"proximos_passos\": [string]\n"
        . "}\n"
        . "Foque em: locais que mais venderam, oportunidades reais de expansão na Grande Florianópolis/SC, capacidade de frota/motoristas e qualidade da operação.";

    $body = [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.35,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $sistema],
            ['role' => 'user', 'content' => "Analise estes dados e monte o relatório:\n" . $payloadDados],
        ],
        'max_tokens' => 1200,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if (function_exists('curl_ca_nativo')) {
        curl_ca_nativo($ch);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return [false, null, 'Falha de rede na OpenAI' . ($err !== '' ? ': ' . $err : '.')];
    }
    $json = json_decode((string) $raw, true);
    $txt = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    if ($code < 200 || $code >= 300 || $txt === '') {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
        return [false, null, $msg !== '' ? $msg : 'A IA não retornou o relatório.'];
    }
    $rel = json_decode($txt, true);
    if (!is_array($rel)) {
        return [false, null, 'Relatório em formato inválido.'];
    }
    return [true, $rel, ''];
}

function analise_relatorio_local(array $dados): array
{
    $top = $dados['top_destinos'][0] ?? null;
    $top2 = $dados['top_destinos'][1] ?? null;
    $resumo = $dados['resumo'];
    $viagens = (int) ($resumo['viagens'] ?? 0);
    $frota = (int) ($resumo['veiculos_ativos'] ?? 0);
    $mots = (int) ($resumo['motoristas_ativos'] ?? 0);
    $ticket = (float) ($resumo['ticket'] ?? 0);
    $empresa = (string) ($dados['empresa'] ?? 'Transfer FLN');
    $base = (string) ($dados['cidade_base'] ?? 'Florianópolis / SC');

    $locais = [];
    foreach (array_slice($dados['top_destinos'] ?? [], 0, 5) as $i => $row) {
        $locais[] = [
            'local' => (string) $row['local'],
            'motivo' => $i === 0
                ? 'Maior volume do período: ' . (int) $row['viagens'] . ' viagens e R$ ' . number_format((float) $row['receita'], 2, ',', '.') . '.'
                : 'Concentração relevante de demanda (' . (int) $row['viagens'] . ' viagens).',
            'prioridade' => $i === 0 ? 'alta' : ($i < 3 ? 'media' : 'baixa'),
        ];
    }

    $expansao = [];
    if ($top) {
        $expansao[] = [
            'titulo' => 'Blindar o hub ' . $top['local'],
            'acao' => 'Priorize cobertura e tempo de resposta neste destino antes de abrir frentes novas. Monte escala fixa nos horários de pico e campanhas só para quem já pediu transfer nesta região.',
            'impacto' => 'Protege a receita principal e reduz no-show/atraso onde mais vende.',
        ];
    }
    if ($top2) {
        $expansao[] = [
            'titulo' => 'Expandir com disciplina para ' . $top2['local'],
            'acao' => 'Trate como segunda praça: preços claros, motoristas com rota conhecida e oferta empacotada (aeroporto ↔ destino). Só aumente frota depois de 2–3 semanas estáveis.',
            'impacto' => 'Crescimento com menor risco operacional.',
        ];
    }
    $expansao[] = [
        'titulo' => 'Capacidade antes de marketing',
        'acao' => $frota > 0 && $mots > 0
            ? "Com {$mots} motorista(s) e {$frota} veículo(s) ativos, limite campanhas ao que a frota consegue cumprir no mesmo dia."
            : 'Cadastre motoristas/veículos ativos antes de disparar campanhas de aquisição.',
        'impacto' => 'Evita vender demanda que a operação não entrega.',
    ];
    if ($ticket > 0) {
        $expansao[] = [
            'titulo' => 'Subir ticket médio com pacotes',
            'acao' => 'Ofereça round-trip e espera no aeroporto nos destinos do ranking, sem diluir preço do trecho simples.',
            'impacto' => 'Melhora margem sem depender só de mais viagens.',
        ];
    }

    $riscos = [];
    if ($viagens === 0) {
        $riscos[] = 'Período sem viagens válidas — o relatório fica limitado até haver histórico.';
    }
    if ($frota > 0 && $mots > 0 && $frota > $mots * 2) {
        $riscos[] = 'Frota maior que a equipe disponível — risco de ociosidade ou cobertura irregular.';
    }
    if (count($dados['top_destinos'] ?? []) <= 1 && $viagens > 5) {
        $riscos[] = 'Demanda muito concentrada em um único destino — choque de demanda ou concorrência local afeta o caixa rápido.';
    }
    if ($riscos === []) {
        $riscos[] = 'Monitorar cancelamentos e tempo de aceite nos hubs do ranking.';
    }

    $passos = [
        'Revisar escala dos 2 destinos com mais vendas na próxima semana.',
        'Congelar expansão geográfica até a taxa de cumprimento das reservas atuais estar estável.',
        'Usar Campanhas só no público que já pediu transfer nos hubs líderes.',
    ];

    $resumoTxt = $viagens > 0 && $top
        ? "{$empresa} em {$base} registrou {$viagens} viagens no período, com ticket médio de R$ " . number_format($ticket, 2, ',', '.') . ". O destino que mais vendeu foi {$top['local']}" . ($top2 ? ", seguido de {$top2['local']}" : '') . '. A expansão correta é reforçar esses hubs e só então abrir praças adjacentes com frota sobrando.'
        : "{$empresa} em {$base}: ainda há poucos dados no período. Foque em registrar origem/destino corretamente e só depois planejar expansão.";

    return [
        'titulo' => 'Relatório operacional · ' . ($dados['periodo_label'] ?? 'Período'),
        'resumo_executivo' => $resumoTxt,
        'locais_destaque' => $locais,
        'expansao' => $expansao,
        'riscos' => $riscos,
        'proximos_passos' => $passos,
        'fonte' => 'local',
    ];
}

function analise_gerar(array $db, string $periodo = '90d'): array
{
    $dados = analise_coleta($db, $periodo);
    [$ok, $rel, $erro] = analise_openai_relatorio($db, $dados);
    if (!$ok) {
        $rel = analise_relatorio_local($dados);
        $rel['resumo_executivo'] = ($rel['resumo_executivo'] ?? '')
            . ($erro !== '' ? ' (Modo local: ' . $erro . ')' : ' (Modo local sem OpenAI.)');
        $ok = true;
        $erro = '';
    } else {
        $rel['fonte'] = 'openai';
    }
    return [
        'ok' => $ok,
        'erro' => $erro,
        'gerado_em' => date('d/m/Y H:i'),
        'dados' => $dados,
        'relatorio' => $rel,
    ];
}
