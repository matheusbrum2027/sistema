<?php
declare(strict_types=1);

define('APP', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Ajuste aqui antes de rodar; não commite senhas reais.
$adminUsuario = getenv('FLN_ADMIN_USUARIO') ?: 'matheusbrumds@gmail.com';
$adminSenha = getenv('FLN_ADMIN_SENHA') ?: '';
$motId = (int) (getenv('FLN_MOT_ID') ?: '12');
$motSenha = getenv('FLN_MOT_SENHA') ?: '';
if ($adminSenha === '' || $motSenha === '') {
    fwrite(STDERR, "Defina FLN_ADMIN_SENHA e FLN_MOT_SENHA no ambiente.\n");
    exit(1);
}

for ($t = 0; $t < 6; $t++) {
    $db = load_db(true);
    $db['settings']['usuario'] = $adminUsuario;
    $db['settings']['senha'] = $adminSenha;

    foreach ($db['motoristas'] as &$mot) {
        $id = (int) ($mot['id'] ?? 0);
        if ($id === 3 && mb_strtolower(trim((string) ($mot['email'] ?? ''))) === mb_strtolower($adminUsuario)) {
            $mot['email'] = 'mot3.matheusbrumds@gmail.com';
            $mot['usuario'] = 'mot3.matheusbrumds@gmail.com';
        }
        if ($id === $motId) {
            $mot = credenciais_motorista($db, $mot, (string) ($mot['usuario'] ?? ''), $motSenha);
        }
    }
    unset($mot);

    try {
        save_db($db, 'criar_usuarios_acesso');
        echo "ok versao=" . store_versao() . "\n";
        echo "admin_usuario={$adminUsuario}\n";
        echo "admin_senha={$adminSenha}\n";
        foreach ($db['motoristas'] as $m) {
            if ((int) ($m['id'] ?? 0) === $motId) {
                echo "motorista_id={$motId}\n";
                echo 'motorista_nome=' . ($m['nome'] ?? '') . "\n";
                echo 'motorista_usuario=' . ($m['usuario'] ?? '') . "\n";
                echo "motorista_senha={$motSenha}\n";
                break;
            }
        }
        exit(0);
    } catch (BancoConflito) {
        usleep(200000 * ($t + 1));
    }
}
fwrite(STDERR, "conflito após tentativas\n");
exit(1);
