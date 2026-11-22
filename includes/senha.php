<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function senha_e_hash(string $valor): bool
{
    return preg_match('/^\$2[aby]\$\d{2}\$/', $valor) === 1;
}

function senha_confere(string $gravada, string $digitada): bool
{
    if ($gravada === '' || $digitada === '') {
        return false;
    }
    if (senha_e_hash($gravada)) {
        return password_verify($digitada, $gravada);
    }
    return hash_equals($gravada, $digitada);
}

function senha_proteger(string $valor): string
{
    if ($valor === '' || senha_e_hash($valor)) {
        return $valor;
    }
    return password_hash($valor, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** Mensagem de erro, ou vazio quando a senha nova pode ser gravada. Vazio digitado significa manter a atual. */
function senha_nova_erro(string $senha, string $deQuem = 'administrador'): string
{
    if ($senha === '') {
        return '';
    }
    if (strlen($senha) < 10) {
        $quem = $deQuem === 'motorista' ? 'do motorista' : 'do administrador';
        return 'A senha ' . $quem . ' precisa ter pelo menos 10 caracteres.';
    }
    return '';
}

function senhas_proteger_documento(array &$db): void
{
    if (isset($db['settings']) && is_array($db['settings']) && array_key_exists('senha', $db['settings'])) {
        $db['settings']['senha'] = senha_proteger((string) $db['settings']['senha']);
    }
    if (!isset($db['motoristas']) || !is_array($db['motoristas'])) {
        return;
    }
    foreach ($db['motoristas'] as $i => $mot) {
        if (!is_array($mot)) {
            continue;
        }
        $db['motoristas'][$i]['senha'] = senha_proteger((string) ($mot['senha'] ?? ''));
    }
}
