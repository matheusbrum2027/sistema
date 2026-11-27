<?php
declare(strict_types=1);

if (!function_exists('clip')) {
    function clip(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }
}

require dirname(__DIR__) . '/includes/ops.php';
require dirname(__DIR__) . '/includes/funcionarios.php';

$falhas = 0;
$depois = [];

function conferir(bool $condicao, string $nome): void
{
    global $falhas;
    global $depois;
    $depois[] = ($condicao ? 'ok  ' : 'FALHOU  ') . $nome;
    if (!$condicao) {
        global $falhas;
        $falhas++;
    }
}

function base_dominios(array $hosts): array
{
    $lista = [];
    $n = 1;
    foreach ($hosts as $host) {
        $lista[] = ['id' => $n, 'host' => $host, 'criado_em' => '2026-11-27 20:00'];
        $n++;
    }
    return ['settings' => ['webhook_dominios' => $lista]];
}

function definir_origem(?string $origin, ?string $referer = null): void
{
    unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    if ($origin !== null) {
        $_SERVER['HTTP_ORIGIN'] = $origin;
    }
    if ($referer !== null) {
        $_SERVER['HTTP_REFERER'] = $referer;
    }
}

$aceitos = [
    'www.cliente.com.br' => 'www.cliente.com.br',
    'HTTPS://WWW.Cliente.com.br/pedido' => 'www.cliente.com.br',
    'http://www.cliente.com.br' => 'www.cliente.com.br',
    'https://www.cliente.com.br:443' => 'www.cliente.com.br',
    'cliente.com.br' => 'cliente.com.br',
    'https://reservas.cliente.com.br.' => 'reservas.cliente.com.br',
];
foreach ($aceitos as $bruto => $esperado) {
    conferir(webhook_dominio_normalizar($bruto) === $esperado, "guarda $bruto");
}

$recusados = [
    '',
    'localhost',
    'http://localhost',
    'https://127.0.0.1',
    'https://192.168.0.8',
    'https://10.1.1.1',
    'https://[::1]',
    '*.cliente.com.br',
    'https://user@www.cliente.com.br',
    'https://user:senha@www.cliente.com.br',
    'https://www.cliente.com.br:8443',
    'https://cliente',
    'javascript:alert(1)',
    'https://painel.local',
    'https://painel.localhost',
    'https://painel.internal',
    "www.cliente.com.br\r\nX-Evil: 1",
    'https://2130706433',
    'https://0.0.0.0',
    'not a host',
    'https://www.cliente.com.br%2e.evil.com',
];
foreach ($recusados as $bruto) {
    conferir(webhook_dominio_normalizar($bruto) === '', "recusa [$bruto]");
}

$db = base_dominios(['www.cliente.com.br']);
$vazio = base_dominios([]);

definir_origem('https://www.cliente.com.br');
conferir(webhook_origem_autorizada($db), 'origin exato entra');
conferir(!webhook_origem_autorizada($vazio), 'lista vazia recusa');

definir_origem('https://WWW.CLIENTE.COM.BR/qualquer');
conferir(webhook_origem_autorizada($db), 'origin maiusculo entra');

definir_origem('http://www.cliente.com.br');
conferir(!webhook_origem_autorizada($db), 'http e recusado');

definir_origem('https://www.cliente.com.br:8443');
conferir(!webhook_origem_autorizada($db), 'porta estranha e recusada');

definir_origem('https://cliente.com.br');
conferir(!webhook_origem_autorizada($db), 'apex nao herda o www');

definir_origem('https://evil.www.cliente.com.br');
conferir(!webhook_origem_autorizada($db), 'subdominio nao herda');

definir_origem('https://www.cliente.com.br.evil.com');
conferir(!webhook_origem_autorizada($db), 'sufixo falso nao entra');

definir_origem('https://evil.com', 'https://www.cliente.com.br/pagina');
conferir(!webhook_origem_autorizada($db), 'origin ruim nao cai no referer');

definir_origem('null', 'https://www.cliente.com.br/pagina');
conferir(!webhook_origem_autorizada($db), 'origin null nao cai no referer');

definir_origem(null, 'https://www.cliente.com.br/checkout');
conferir(webhook_origem_autorizada($db), 'referer https do host entra');

definir_origem(null, 'http://www.cliente.com.br/checkout');
conferir(!webhook_origem_autorizada($db), 'referer http e recusado');

definir_origem(null, null);
conferir(!webhook_origem_autorizada($db), 'post direto sem origem e recusado');

definir_origem('https://127.0.0.1');
conferir(!webhook_origem_autorizada(base_dominios(['127.0.0.1'])), 'ip guardado nao libera');

definir_origem('https://www.cliente.com.br');
conferir(webhook_cors_valor($db) === 'https://www.cliente.com.br', 'cors devolve so o https do host');
definir_origem('http://www.cliente.com.br');
conferir(webhook_cors_valor($db) === '', 'cors nao reflete http');
definir_origem('https://evil.com');
conferir(webhook_cors_valor($db) === '', 'cors nao reflete outro site');
definir_origem("https://www.cliente.com.br\r\nX-Evil: 1");
conferir(webhook_cors_valor($db) === '', 'cors nao aceita quebra de cabecalho');

conferir(acao_somente_master('add_webhook_dominio'), 'so o master autoriza');
conferir(acao_somente_master('update_webhook_dominio'), 'so o master edita');
conferir(acao_somente_master('delete_webhook_dominio'), 'so o master remove');
conferir(!acao_somente_master('add_transfer'), 'reserva comum nao virou acao de master');

foreach ($depois as $linha) {
    echo $linha . "\n";
}
if ($falhas > 0) {
    fwrite(STDERR, $falhas . " teste(s) falharam\n");
    exit(1);
}
echo "todos os testes de dominio passaram\n";
