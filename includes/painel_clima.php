<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function painel_clima_sessao(): bool
{
    return !empty($_SESSION['auth'])
        && (($_SESSION['role'] ?? '') === 'motorista')
        && (int) ($_SESSION['motorista_id'] ?? 0) > 0;
}

function painel_clima_texto(int $codigo): array
{
    if ($codigo === 0) {
        return ['texto' => 'Céu limpo', 'icone' => 'sol'];
    }
    if ($codigo <= 2) {
        return ['texto' => 'Parcialmente nublado', 'icone' => 'sol-nuvem'];
    }
    if ($codigo === 3) {
        return ['texto' => 'Nublado', 'icone' => 'nuvem'];
    }
    if ($codigo === 45 || $codigo === 48) {
        return ['texto' => 'Neblina', 'icone' => 'neblina'];
    }
    if ($codigo >= 95) {
        return ['texto' => 'Tempestade', 'icone' => 'tempestade'];
    }
    if ($codigo >= 71 && $codigo <= 77) {
        return ['texto' => 'Frio intenso', 'icone' => 'frio'];
    }
    if ($codigo >= 51) {
        return ['texto' => 'Chuva', 'icone' => 'chuva'];
    }
    return ['texto' => 'Instável', 'icone' => 'nuvem'];
}

function painel_clima_http(string $url): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: FLN-Transfer-Painel'],
    ]);
    if (function_exists('curl_ca_nativo')) {
        curl_ca_nativo($ch);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !is_string($raw) || $raw === '') {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function painel_clima_numero(mixed $valor): ?float
{
    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }
    if (!is_string($valor) || !is_numeric(str_replace(',', '.', $valor))) {
        return null;
    }
    return (float) str_replace(',', '.', $valor);
}

function painel_clima_moeda(array $json, string $chave, string $nome, string $sigla): ?array
{
    $linha = $json[$chave] ?? null;
    if (!is_array($linha)) {
        return null;
    }
    $compra = painel_clima_numero($linha['bid'] ?? null);
    if ($compra === null || $compra <= 0 || $compra > 100) {
        return null;
    }
    $venda = painel_clima_numero($linha['ask'] ?? null);
    return [
        'moeda' => $nome,
        'sigla' => $sigla,
        'compra' => round($compra, 4),
        'venda' => $venda !== null ? round($venda, 4) : round($compra, 4),
        'variacao' => round((float) (painel_clima_numero($linha['pctChange'] ?? null) ?? 0), 2),
        'max' => round((float) (painel_clima_numero($linha['high'] ?? null) ?? $compra), 4),
        'min' => round((float) (painel_clima_numero($linha['low'] ?? null) ?? $compra), 4),
    ];
}

function painel_clima_montar(): ?array
{
    $tempo = painel_clima_http('https://api.open-meteo.com/v1/forecast?latitude=-27.5954&longitude=-48.548&current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=America%2FSao_Paulo&forecast_days=3');
    $cambio = painel_clima_http('https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL');
    if (!is_array($tempo) || !is_array($cambio)) {
        return null;
    }
    $atual = $tempo['current'] ?? null;
    $diario = $tempo['daily'] ?? null;
    if (!is_array($atual) || !is_array($diario) || !is_array($diario['time'] ?? null)) {
        return null;
    }
    $codigo = (int) ($atual['weather_code'] ?? -1);
    $rotulo = painel_clima_texto($codigo);
    $dias = [];
    $nomes = ['Hoje', 'Amanhã', 'Depois'];
    foreach ($diario['time'] as $i => $data) {
        if (!is_string($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            continue;
        }
        $diaCodigo = (int) ($diario['weather_code'][$i] ?? -1);
        $diaRotulo = painel_clima_texto($diaCodigo);
        $quando = DateTimeImmutable::createFromFormat('!Y-m-d', $data, new DateTimeZone('America/Sao_Paulo'));
        $dias[] = [
            'data' => $data,
            'rotulo' => $nomes[$i] ?? ($quando ? $quando->format('d/m') : $data),
            'min' => (int) round((float) ($diario['temperature_2m_min'][$i] ?? 0)),
            'max' => (int) round((float) ($diario['temperature_2m_max'][$i] ?? 0)),
            'chuva' => (int) round((float) ($diario['precipitation_probability_max'][$i] ?? 0)),
            'texto' => $diaRotulo['texto'],
            'icone' => $diaRotulo['icone'],
        ];
        if (count($dias) >= 3) {
            break;
        }
    }
    $moedas = array_values(array_filter([
        painel_clima_moeda($cambio, 'USDBRL', 'Dólar', 'USD'),
        painel_clima_moeda($cambio, 'EURBRL', 'Euro', 'EUR'),
    ]));
    if ($dias === [] || $moedas === []) {
        return null;
    }
    return [
        'ok' => true,
        'cidade' => 'Florianópolis',
        'agora' => [
            'temp' => (int) round((float) ($atual['temperature_2m'] ?? 0)),
            'sensacao' => (int) round((float) ($atual['apparent_temperature'] ?? 0)),
            'umidade' => (int) round((float) ($atual['relative_humidity_2m'] ?? 0)),
            'vento' => (int) round((float) ($atual['wind_speed_10m'] ?? 0)),
            'texto' => $rotulo['texto'],
            'icone' => $rotulo['icone'],
        ],
        'dias' => $dias,
        'cambio' => $moedas,
        'atualizado' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m H:i'),
    ];
}

function painel_clima_publico(): array
{
    $arquivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fln-clima-florianopolis.json';
    if (is_file($arquivo)) {
        $cru = json_decode((string) file_get_contents($arquivo), true);
        $idade = time() - (int) ($cru['em'] ?? 0);
        if (is_array($cru['dados'] ?? null) && $idade >= 0 && $idade < 900) {
            return $cru['dados'];
        }
    }
    $dados = painel_clima_montar();
    if ($dados === null) {
        return ['ok' => false];
    }
    $gravado = json_encode(['em' => time(), 'dados' => $dados], JSON_UNESCAPED_UNICODE);
    if (is_string($gravado)) {
        @file_put_contents($arquivo, $gravado, LOCK_EX);
    }
    return $dados;
}
