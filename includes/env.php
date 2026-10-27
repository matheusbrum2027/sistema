<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function carregar_env(string $arquivo): void
{
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
    if ($linhas === false) {
        return;
    }
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }
        [$nome, $valor] = explode('=', $linha, 2);
        $nome = trim($nome);
        $valor = trim($valor);
        if ($nome === '' || getenv($nome) !== false) {
            continue;
        }
        if ((str_starts_with($valor, '"') && str_ends_with($valor, '"')) || (str_starts_with($valor, "'") && str_ends_with($valor, "'"))) {
            $valor = substr($valor, 1, -1);
        }
        putenv($nome . '=' . $valor);
        $_ENV[$nome] = $valor;
    }
}

function env_valor(string $nome, string $padrao = ''): string
{
    $valor = getenv($nome);
    if ($valor === false || $valor === '') {
        $valor = (string) ($_ENV[$nome] ?? $padrao);
    }
    return $valor;
}
