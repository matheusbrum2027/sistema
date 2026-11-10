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

function mapa_geocode_google(string $texto): ?array
{
    if (!function_exists('google_api_key') || google_api_key() === '') {
        return null;
    }
    $endereco = trim($texto);
    if ($endereco === '') {
        return null;
    }
    if (!str_contains(mb_strtolower($endereco), 'florian') && !str_contains(mb_strtolower($endereco), 'sc')) {
        $endereco .= ', Grande Florianópolis, SC, Brasil';
    }
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address' => $endereco,
        'region' => 'br',
        'language' => 'pt-BR',
        'key' => google_api_key(),
    ], '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    if (function_exists('curl_ca_nativo')) {
        curl_ca_nativo($ch);
    }
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $raw, true);
    if (!is_array($json) || (string) ($json['status'] ?? '') !== 'OK') {
        return null;
    }
    $res = $json['results'][0] ?? null;
    if (!is_array($res)) {
        return null;
    }
    $loc = $res['geometry']['location'] ?? null;
    if (!is_array($loc) || !isset($loc['lat'], $loc['lng'])) {
        return null;
    }
    $nome = trim((string) ($res['formatted_address'] ?? $texto));
    if (mb_strlen($nome) > 48) {
        $nome = mb_substr($nome, 0, 45) . '…';
    }
    return [
        'id' => 'geo_' . substr(sha1(mb_strtolower($texto)), 0, 10),
        'nome' => $nome !== '' ? $nome : 'Ponto',
        'lat' => (float) $loc['lat'],
        'lng' => (float) $loc['lng'],
    ];
}

function mapa_resolver_ponto(string $texto): ?array
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
    return mapa_geocode_google($texto);
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
    ];
}
