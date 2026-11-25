<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function mapa_admin(): bool
{
    return !empty($_SESSION['auth']) && (($_SESSION['role'] ?? '') === 'admin');
}

/** Pontos conhecidos da Grande Florianópolis (lat/lng para Leaflet). */
function mapa_pontos(): array
{
    return [
        'aeroporto' => ['nome' => 'Aeroporto', 'lat' => -27.6703, 'lng' => -48.5525, 'aliases' => ['aeroporto', 'hercilio', 'hercílio', 'fln', 'sfn']],
        'centro' => ['nome' => 'Centro', 'lat' => -27.5954, 'lng' => -48.5480, 'aliases' => ['centro', 'mercado publico', 'mercado público', 'beira-mar', 'beiramar']],
        'canasvieiras' => ['nome' => 'Canasvieiras', 'lat' => -27.4290, 'lng' => -48.4570, 'aliases' => ['canasvieiras', 'canas']],
        'ingleses' => ['nome' => 'Ingleses', 'lat' => -27.4370, 'lng' => -48.3930, 'aliases' => ['ingleses']],
        'jurere' => ['nome' => 'Jurerê', 'lat' => -27.4375, 'lng' => -48.4980, 'aliases' => ['jurere', 'jurerê']],
        'lagoa' => ['nome' => 'Lagoa', 'lat' => -27.6025, 'lng' => -48.4670, 'aliases' => ['lagoa', 'conceicao', 'conceição']],
        'campeche' => ['nome' => 'Campeche', 'lat' => -27.6840, 'lng' => -48.4840, 'aliases' => ['campeche']],
        'rio_vermelho' => ['nome' => 'Rio Vermelho', 'lat' => -27.4840, 'lng' => -48.4050, 'aliases' => ['rio vermelho']],
        'sao_jose' => ['nome' => 'São José', 'lat' => -27.5969, 'lng' => -48.6180, 'aliases' => ['sao jose', 'são josé', 'kobrasol']],
        'palhoca' => ['nome' => 'Palhoça', 'lat' => -27.6455, 'lng' => -48.6679, 'aliases' => ['palhoca', 'palhoça', 'pedra branca']],
        'biguacu' => ['nome' => 'Biguaçu', 'lat' => -27.4942, 'lng' => -48.6556, 'aliases' => ['biguacu', 'biguaçu']],
        'bc' => ['nome' => 'Balneário', 'lat' => -26.9926, 'lng' => -48.6352, 'aliases' => ['balneario', 'balneário', 'camboriu', 'camboriú', 'bc']],
        'itajai' => ['nome' => 'Itajaí', 'lat' => -26.9078, 'lng' => -48.6619, 'aliases' => ['itajai', 'itajaí']],
    ];
}

function mapa_geocode_arquivo(): string
{
    return rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'fln-geocode.json';
}

function &mapa_geocode_mem(): array
{
    static $cache = null;
    if ($cache === null) {
        $arquivo = mapa_geocode_arquivo();
        $lido = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : [];
        $cache = is_array($lido) ? $lido : [];
    }
    return $cache;
}

function mapa_geocode_ler(): array
{
    return mapa_geocode_mem();
}

function mapa_geocode_gravar(array $cache): void
{
    $mem = &mapa_geocode_mem();
    $mem = $cache;
    @file_put_contents(mapa_geocode_arquivo(), json_encode($cache, JSON_UNESCAPED_UNICODE));
}

function mapa_geocode_chave(string $texto): string
{
    $t = mb_strtolower(trim($texto));
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    return sha1($t);
}

function mapa_ponto_na_regiao(float $lat, float $lng): bool
{
    return $lat >= -29.5 && $lat <= -25.7 && $lng >= -53.9 && $lng <= -48.2;
}

function mapa_ponto_cache(string $texto): ?array
{
    $chave = mapa_geocode_chave($texto);
    $cache = mapa_geocode_ler();
    $hit = $cache[$chave] ?? null;
    return is_array($hit) && isset($hit['lat'], $hit['lng']) ? $hit : null;
}

function mapa_geocode_osm(string $texto): ?array
{
    $endereco = trim($texto);
    if ($endereco === '' || mb_strlen($endereco) > 180 || !function_exists('curl_init')) {
        return null;
    }
    $salvo = mapa_ponto_cache($endereco);
    if ($salvo) {
        return $salvo;
    }
    static $ultimo = 0.0;
    $agora = microtime(true);
    if ($ultimo > 0 && ($agora - $ultimo) < 1.1) {
        usleep((int) ((1.1 - ($agora - $ultimo)) * 1000000));
    }
    $ultimo = microtime(true);
    $consulta = $endereco;
    $baixo = mb_strtolower($consulta);
    if (!str_contains($baixo, 'santa catarina') && !str_contains($baixo, 'brasil') && !preg_match('/\bsc\b/u', $baixo)) {
        $consulta .= ', Santa Catarina, Brasil';
    }
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $consulta,
        'format' => 'jsonv2',
        'limit' => 1,
        'countrycodes' => 'br',
        'viewbox' => '-53.9,-25.7,-48.2,-29.5',
        'bounded' => 0,
    ], '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: FLNTransferPainel/1.0 (mapa operacional)',
        ],
    ]);
    if (function_exists('curl_ca_nativo')) {
        curl_ca_nativo($ch);
    }
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $raw, true);
    $linha = is_array($json) ? ($json[0] ?? null) : null;
    if (!is_array($linha) || !isset($linha['lat'], $linha['lon'])) {
        return null;
    }
    $lat = (float) $linha['lat'];
    $lng = (float) $linha['lon'];
    if (!mapa_ponto_na_regiao($lat, $lng)) {
        return null;
    }
    $nome = trim((string) ($linha['display_name'] ?? $endereco));
    $nome = preg_replace('/,\s*Brasil$/u', '', $nome) ?? $nome;
    if (mb_strlen($nome) > 48) {
        $nome = mb_substr($nome, 0, 45) . '…';
    }
    $ponto = [
        'id' => 'geo_' . substr(mapa_geocode_chave($endereco), 0, 10),
        'nome' => $nome !== '' ? $nome : 'Ponto',
        'lat' => $lat,
        'lng' => $lng,
    ];
    $cache = mapa_geocode_ler();
    $cache[mapa_geocode_chave($endereco)] = $ponto;
    if (count($cache) > 400) {
        $cache = array_slice($cache, -400, null, true);
    }
    mapa_geocode_gravar($cache);
    return $ponto;
}

function mapa_resolver_ponto(string $texto, bool $remoto = false): ?array
{
    $t = mb_strtolower(trim($texto));
    if ($t === '') {
        return null;
    }
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    foreach (mapa_pontos() as $id => $p) {
        foreach ($p['aliases'] as $alias) {
            if ($alias !== '' && str_contains($t, $alias)) {
                return [
                    'id' => $id,
                    'nome' => $p['nome'],
                    'lat' => (float) $p['lat'],
                    'lng' => (float) $p['lng'],
                ];
            }
        }
    }
    if (!$remoto) {
        return mapa_ponto_cache($texto);
    }
    return mapa_geocode_osm($texto);
}

function mapa_jitter_geo(int $id, float $lat, float $lng): array
{
    $dLat = ((($id * 37) % 11) - 5) * 0.0011;
    $dLng = ((($id * 53) % 11) - 5) * 0.0011;
    return [$lat + $dLat, $lng + $dLng];
}

function mapa_viagens(array $db, string $filtro = 'hoje', string $status = 'todas'): array
{
    $hoje = date('Y-m-d');
    $limite = $hoje;
    $ate = $hoje;
    if ($filtro === '7d') {
        $limite = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
        $ate = '2999-12-31';
    } elseif ($filtro === '30d') {
        $limite = (new DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');
        $ate = '2999-12-31';
    } elseif ($filtro === 'todas') {
        $limite = '1970-01-01';
        $ate = '2999-12-31';
    } elseif ($filtro === 'proximas') {
        $limite = $hoje;
        $ate = (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d');
    }

    $pins = [];
    $rotas = [];
    $faltam = [];
    $porStatus = ['pendente' => 0, 'reserva' => 0, 'andamento' => 0, 'concluida' => 0, 'cancelada' => 0];
    $porPonto = [];

    foreach ($db['transfers'] ?? [] as $t) {
        if (!is_array($t)) {
            continue;
        }
        $st = (string) ($t['status'] ?? '');
        if ($status !== 'todas' && $st !== $status) {
            continue;
        }
        $data = (string) ($t['data'] ?? '');
        if ($data < $limite || $data > $ate) {
            continue;
        }
        if (isset($porStatus[$st])) {
            $porStatus[$st]++;
        }
        $origemTxt = (string) ($t['origem'] ?? '');
        $destinoTxt = (string) ($t['destino'] ?? '');
        $o = mapa_resolver_ponto($origemTxt);
        $d = mapa_resolver_ponto($destinoTxt);
        if ($o === null && trim($origemTxt) !== '' && count($faltam) < 24) {
            $faltam[] = [
                'id' => (int) ($t['id'] ?? 0),
                'tipo' => 'origem',
                'texto' => $origemTxt,
                'status' => $st,
                'hora' => (string) ($t['hora'] ?? ''),
                'data' => $data,
                'cliente' => (string) ($t['cliente'] ?? ''),
                'par' => $destinoTxt,
            ];
        }
        if ($d === null && trim($destinoTxt) !== '' && count($faltam) < 24) {
            $faltam[] = [
                'id' => (int) ($t['id'] ?? 0),
                'tipo' => 'destino',
                'texto' => $destinoTxt,
                'status' => $st,
                'hora' => (string) ($t['hora'] ?? ''),
                'data' => $data,
                'cliente' => (string) ($t['cliente'] ?? ''),
                'par' => $origemTxt,
            ];
        }
        if ($o === null && $d === null) {
            continue;
        }
        $id = (int) ($t['id'] ?? 0);
        $oLat = null;
        $oLng = null;
        $dLat = null;
        $dLng = null;
        if ($o !== null) {
            [$oLat, $oLng] = mapa_jitter_geo($id, $o['lat'], $o['lng']);
            $pins[] = [
                'id' => $id,
                'tipo' => 'origem',
                'lat' => round($oLat, 6),
                'lng' => round($oLng, 6),
                'ponto' => $o['nome'],
                'status' => $st,
                'hora' => (string) ($t['hora'] ?? ''),
                'data' => $data,
                'cliente' => (string) ($t['cliente'] ?? ''),
                'texto' => $origemTxt,
                'par' => $destinoTxt,
            ];
            $porPonto[$o['id']] = ($porPonto[$o['id']] ?? 0) + 1;
        }
        if ($d !== null) {
            [$dLat, $dLng] = mapa_jitter_geo($id + 17, $d['lat'], $d['lng']);
            $pins[] = [
                'id' => $id,
                'tipo' => 'destino',
                'lat' => round($dLat, 6),
                'lng' => round($dLng, 6),
                'ponto' => $d['nome'],
                'status' => $st,
                'hora' => (string) ($t['hora'] ?? ''),
                'data' => $data,
                'cliente' => (string) ($t['cliente'] ?? ''),
                'texto' => $destinoTxt,
                'par' => $origemTxt,
            ];
            $porPonto[$d['id']] = ($porPonto[$d['id']] ?? 0) + 1;
        }
        if ($o !== null && $d !== null && $oLat !== null && $dLat !== null) {
            $rotas[] = [
                'id' => $id,
                'lat1' => round($oLat, 6),
                'lng1' => round($oLng, 6),
                'lat2' => round($dLat, 6),
                'lng2' => round($dLng, 6),
                'status' => $st,
            ];
        }
    }

    $hotspots = [];
    foreach (mapa_pontos() as $id => $p) {
        $n = (int) ($porPonto[$id] ?? 0);
        if ($n <= 0) {
            continue;
        }
        $hotspots[] = [
            'id' => $id,
            'nome' => $p['nome'],
            'lat' => $p['lat'],
            'lng' => $p['lng'],
            'qtd' => $n,
        ];
    }
    usort($hotspots, static fn(array $a, array $b): int => $b['qtd'] <=> $a['qtd']);

    return [
        'ok' => true,
        'filtro' => $filtro,
        'status' => $status,
        'centro' => ['lat' => -27.5954, 'lng' => -48.5480, 'zoom' => 11],
        'resumo' => [
            'pins' => count($pins),
            'rotas' => count($rotas),
            'por_status' => $porStatus,
            'total' => array_sum($porStatus),
        ],
        'hotspots' => $hotspots,
        'pins' => $pins,
        'rotas' => $rotas,
        'faltam' => $faltam,
    ];
}
