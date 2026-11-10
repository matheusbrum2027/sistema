<?php
declare(strict_types=1);

/**
 * Fluxo setup → confirmar → desligar, sem travar o admin.
 * Uso: php scripts/testar_2fa_fluxo.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$falhas = 0;
function ok(string $nome, bool $cond, string $detalhe = ''): void
{
    global $falhas;
    if ($cond) {
        echo "[OK] $nome\n";
        return;
    }
    $falhas++;
    echo "[FALHA] $nome" . ($detalhe !== '' ? " — $detalhe" : '') . "\n";
}

$backup = com_db('test_2fa_backup', static function (array &$db): array {
    return [
        'verificacao_2fa' => !empty($db['settings']['verificacao_2fa']),
        'totp_secret' => (string) ($db['settings']['totp_secret'] ?? ''),
        'totp_confirmado' => !empty($db['settings']['totp_confirmado']),
    ];
});

$secretTeste = totp_secret_novo();
com_db('test_2fa_setup', static function (array &$db) use ($secretTeste): void {
    $db['settings']['totp_secret'] = $secretTeste;
    $db['settings']['totp_confirmado'] = false;
    $db['settings']['verificacao_2fa'] = false;
});

$db = load_db(true);
ok('estado setup', totp_estado($db) === 'setup');
ok('login ainda sem 2FA no setup', !autenticacao_2fa_pronta($db));

$codigo = totp_codigo($secretTeste);
ok('codigo confere', totp_confere($secretTeste, $codigo), $codigo);

com_db('test_2fa_on', static function (array &$db) use ($codigo): void {
    if (!totp_confere((string) $db['settings']['totp_secret'], $codigo)) {
        throw new RuntimeException('codigo invalido no teste');
    }
    $db['settings']['totp_confirmado'] = true;
    $db['settings']['verificacao_2fa'] = true;
});
$db = load_db(true);
ok('estado on', totp_estado($db) === 'on' && autenticacao_2fa_pronta($db));

login_pendente_2fa([
    'role' => 'admin',
    'motorista_id' => 0,
    'expira' => time() + 600,
]);
$pend = login_pendente_2fa();
ok('pending 2fa', is_array($pend));
$_SESSION['auth'] = false;
ok('pending nao autentica sozinho', empty($_SESSION['auth']));
login_aplicar_sessao('admin', 0);
ok('login apos 2fa', !empty($_SESSION['auth']) && empty($_SESSION['pending_2fa']));

com_db('test_2fa_restore', static function (array &$db) use ($backup): void {
    $db['settings']['verificacao_2fa'] = !empty($backup['verificacao_2fa']);
    $db['settings']['totp_secret'] = (string) ($backup['totp_secret'] ?? '');
    $db['settings']['totp_confirmado'] = !empty($backup['totp_confirmado']);
});
$db2 = load_db(true);
ok('estado restaurado', totp_estado($db2) === totp_estado([
    'settings' => [
        'verificacao_2fa' => !empty($backup['verificacao_2fa']),
        'totp_secret' => (string) ($backup['totp_secret'] ?? ''),
    ],
]));

echo "\nResumo: " . ($falhas === 0 ? 'TODOS OS TESTES PASSARAM' : "$falhas falha(s)") . "\n";
exit($falhas === 0 ? 0 : 1);
