<?php
declare(strict_types=1);

/**
 * Testes locais de captcha + TOTP (2FA) sem HTTP.
 * Uso: php scripts/testar_login_seguranca.php
 */

$raiz = dirname(__DIR__);
require_once $raiz . '/includes/captcha.php';
require_once $raiz . '/includes/senha.php';
require_once $raiz . '/includes/totp.php';

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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo "== Captcha anti-bot ==\n";
captcha_novo();
$texto = (string) ($_SESSION['captcha']['audio'] ?? '');
ok('captcha gera 5 chars', strlen($texto) === 5, 'len=' . strlen($texto));
ok('captcha confere texto certo', captcha_confere($texto));
captcha_novo();
$texto2 = (string) ($_SESSION['captcha']['audio'] ?? '');
ok('captcha rejeita texto errado', !captcha_confere('XXXXX'));
captcha_novo();
$texto3 = (string) ($_SESSION['captcha']['audio'] ?? '');
ok('captcha ignora case/espaços', captcha_confere('  ' . strtolower($texto3) . '  '));
captcha_novo();
$texto4 = (string) ($_SESSION['captcha']['audio'] ?? '');
ok('captcha uso único', captcha_confere($texto4) && !captcha_confere($texto4));

echo "\n== TOTP / 2FA ==\n";
$secret = totp_secret_novo();
ok('secret base32', (bool) preg_match('/^[A-Z2-7]{32}$/', $secret), $secret);
$codigo = totp_codigo($secret);
ok('codigo 6 digitos', (bool) preg_match('/^\d{6}$/', $codigo), $codigo);
ok('totp confere atual', totp_confere($secret, $codigo));
$errado = $codigo === '999999' ? '888888' : '999999';
ok('totp rejeita codigo errado', !totp_confere($secret, $errado));
// código de janela passada (±30s) ainda aceito
$passado = totp_codigo($secret, time() - 30);
ok('totp janela -1', totp_confere($secret, $passado));
ok('totp rejeita codigo curto', !totp_confere($secret, '123'));
$uri = totp_uri($secret, 'admin', 'FLN Transfer');
ok('otpauth uri', str_starts_with($uri, 'otpauth://totp/'));
$qr = totp_qr_url($uri);
ok('qr local', str_starts_with($qr, 'data:image/svg+xml;base64,') && !str_contains($qr, 'qrserver'));
$svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')), true);
ok('qr desenha', is_string($svg) && str_contains($svg, '<svg') && str_contains($svg, '<rect'));

$dbOff = ['settings' => ['verificacao_2fa' => false, 'totp_secret' => '']];
$dbSetup = ['settings' => ['verificacao_2fa' => false, 'totp_secret' => $secret]];
$dbOn = ['settings' => ['verificacao_2fa' => true, 'totp_secret' => $secret]];
$dbSemSecret = ['settings' => ['verificacao_2fa' => true, 'totp_secret' => '']];
ok('2FA off', totp_estado($dbOff) === 'off' && !autenticacao_2fa_pronta($dbOff));
ok('2FA setup sem exigir login', totp_estado($dbSetup) === 'setup' && !autenticacao_2fa_pronta($dbSetup));
ok('2FA on com secret', totp_estado($dbOn) === 'on' && autenticacao_2fa_pronta($dbOn));
ok('2FA on sem secret', !autenticacao_2fa_pronta($dbSemSecret));

// Vetor conhecido RFC 6238 (secret "12345678901234567890" em base32 = GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ)
$rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
$rfcEm = totp_codigo($rfcSecret, 59);
ok('RFC 6238 t=59', $rfcEm === '287082', $rfcEm);
$rfcEm2 = totp_codigo($rfcSecret, 1111111109);
ok('RFC 6238 t=1111111109', $rfcEm2 === '081804', $rfcEm2);

echo "\n== Senhas ==\n";
$clara = 'frase-de-teste-12';
$hash = senha_proteger($clara);
ok('senha vira hash', senha_e_hash($hash) && $hash !== $clara);
ok('senha confere hash', senha_confere($hash, $clara));
ok('senha rejeita outra', !senha_confere($hash, 'outra-frase-12'));
ok('senha legada ainda entra', senha_confere('segredo-antigo', 'segredo-antigo'));
ok('senha legada rejeita', !senha_confere('segredo-antigo', 'outra'));
ok('senha vazia nao entra', !senha_confere('', 'x') && !senha_confere($hash, ''));
ok('senha curta recusada', senha_nova_erro('curta') !== '' && senha_nova_erro('') === '');
ok('senha curta do motorista', str_contains(senha_nova_erro('curta', 'motorista'), 'motorista'));
$doc = ['settings' => ['senha' => 'admin-painel-1'], 'motoristas' => [['senha' => 'TOKENMOTOR'], ['senha' => ''], ['senha' => $hash]]];
senhas_proteger_documento($doc);
ok('documento protege admin', senha_confere((string) $doc['settings']['senha'], 'admin-painel-1'));
ok('documento protege motorista', senha_confere((string) $doc['motoristas'][0]['senha'], 'TOKENMOTOR'));
ok('documento mantem vazio e hash', $doc['motoristas'][1]['senha'] === '' && $doc['motoristas'][2]['senha'] === $hash);

echo "\n== Fonte PNG captcha ==\n";
$fonte = captcha_fonte();
ok('fonte tem alfabeto', isset($fonte['A'], $fonte['9'], $fonte['@']));
captcha_novo();
$cap = $_SESSION['captcha'] ?? [];
ok('sessao captcha pronta', is_array($cap) && !empty($cap['hash']) && !empty($cap['audio']));

echo "\nResumo: " . ($falhas === 0 ? 'TODOS OS TESTES PASSARAM' : "$falhas falha(s)") . "\n";
exit($falhas === 0 ? 0 : 1);
