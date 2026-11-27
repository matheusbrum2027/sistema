<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function eh_master(): bool
{
    return !empty($_SESSION['auth'])
        && (($_SESSION['role'] ?? '') === 'admin')
        && (int) ($_SESSION['funcionario_id'] ?? 0) <= 0;
}

function pode_ver_financeiro(): bool
{
    if (empty($_SESSION['auth']) || (($_SESSION['role'] ?? '') !== 'admin')) {
        return false;
    }
    if ((int) ($_SESSION['funcionario_id'] ?? 0) <= 0) {
        return true;
    }
    return funcionario_financeiro_atual();
}

/** O desmarque vale na hora, sem esperar o funcionário sair e entrar de novo. */
function funcionario_financeiro_atual(): bool
{
    static $lido = false;
    static $valor = false;
    if ($lido) {
        return $valor;
    }
    $lido = true;
    $id = (int) ($_SESSION['funcionario_id'] ?? 0);
    if ($id <= 0) {
        $valor = true;
        return true;
    }
    try {
        foreach (funcionarios_listar() as $func) {
            if ((int) ($func['id'] ?? 0) !== $id) {
                continue;
            }
            $flag = $func['ver_financeiro'] ?? false;
            $valor = $flag === true || $flag === 1 || $flag === '1' || $flag === 't' || $flag === 'true';
            $_SESSION['ver_financeiro'] = $valor;
            return $valor;
        }
    } catch (Throwable) {
        $valor = false;
        $_SESSION['ver_financeiro'] = false;
        return false;
    }
    $valor = false;
    $_SESSION['ver_financeiro'] = false;
    return false;
}

function funcionario_sem_financeiro(): bool
{
    return !empty($_SESSION['auth'])
        && (($_SESSION['role'] ?? '') === 'admin')
        && (int) ($_SESSION['funcionario_id'] ?? 0) > 0
        && !pode_ver_financeiro();
}

function paginas_financeiras(): array
{
    return ['metricas', 'fin-dashboard', 'lancamentos', 'receber', 'faturado', 'pagar', 'relatorios'];
}

function paginas_somente_master(): array
{
    return ['funcionarios', 'configuracoes', 'desenvolvedor'];
}

function acao_financeira(string $acao): bool
{
    return in_array($acao, [
        'faturar_transfer',
        'add_lancamento',
        'edit_lancamento',
        'baixar_lancamento',
        'delete_lancamento',
    ], true);
}

function acao_somente_master(string $acao): bool
{
    return in_array($acao, [
        'save_settings',
        'funcionario_salvar',
        'funcionario_apagar',
        'reset_demo',
        'remove_logo',
        'add_webhook_dominio',
        'update_webhook_dominio',
        'delete_webhook_dominio',
    ], true);
}

function funcionario_usuario_erro(string $usuario, string $master): string
{
    $usuario = mb_strtolower(trim($usuario));
    $master = mb_strtolower(trim($master));
    if (preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/', $usuario) !== 1) {
        return 'O usuário precisa ter de 3 a 40 letras ou números, sem espaço.';
    }
    if ($usuario === $master || $usuario === 'admin' || $usuario === 'motorista') {
        return 'Este usuário é reservado e não pode ser de um funcionário.';
    }
    return '';
}

/** @return array<string, mixed>|null */
function funcionario_buscar(string $usuario): ?array
{
    if (!function_exists('store_rpc') || !store_remoto()) {
        return null;
    }
    $r = store_rpc('fln_funcionario_buscar', ['p_usuario' => mb_strtolower(trim($usuario))], 8);
    return is_array($r) ? $r : null;
}

/** @return list<array<string, mixed>> */
function funcionarios_listar(): array
{
    if (!function_exists('store_rpc') || !store_remoto()) {
        return [];
    }
    $r = store_rpc('fln_funcionarios', [], 8);
    if (!is_array($r)) {
        return [];
    }
    $saida = [];
    foreach ($r as $linha) {
        if (!is_array($linha) || isset($linha['senha'])) {
            continue;
        }
        $saida[] = $linha;
    }
    return $saida;
}

function funcionario_salvar(int $id, string $usuario, string $nome, string $senhaHash, bool $ativo, bool $verFinanceiro): int
{
    $novo = store_rpc('fln_funcionario_salvar', [
        'p_id' => $id,
        'p_usuario' => $usuario,
        'p_nome' => $nome,
        'p_senha' => $senhaHash,
        'p_ativo' => $ativo,
        'p_ver_financeiro' => $verFinanceiro,
    ], 12);
    return (int) $novo;
}

function funcionario_apagar(int $id): void
{
    store_rpc('fln_funcionario_apagar', ['p_id' => $id], 8);
}

function funcionario_entrar(string $usuario, string $senha): ?array
{
    $achado = funcionario_buscar($usuario);
    $hash = is_array($achado) ? (string) ($achado['senha'] ?? '') : '';
    if ($hash === '' || !senha_e_hash($hash)) {
        password_verify($senha, '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ12');
        return null;
    }
    if (empty($achado['ativo']) || !senha_confere($hash, $senha)) {
        return null;
    }
    unset($achado['senha']);
    return $achado;
}
