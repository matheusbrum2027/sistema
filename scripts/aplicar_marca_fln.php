<?php
declare(strict_types=1);

/**
 * Aplica dados oficiais da Fln Transfer + logo + assinatura nas settings.
 * Uso: php scripts/aplicar_marca_fln.php
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function marca_data_uri(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo não encontrado: ' . $path);
    }
    $bin = file_get_contents($path);
    if ($bin === false || $bin === '') {
        throw new RuntimeException('Não foi possível ler: ' . $path);
    }
    $mime = (string) (mime_content_type($path) ?: 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

$raiz = dirname(__DIR__);
$logoUri = marca_data_uri($raiz . '/assets/brand/logo-fln.jpg');
$assUri = marca_data_uri($raiz . '/assets/brand/assinatura-matheus-brum.jpg');

$patch = [
    'nome' => 'Transfer FLN',
    'razao' => 'Fln Transfer Executivo e Turismo',
    'cnpj' => '41.581.147/0001-08',
    'telefone' => '(48) 98822-2934',
    'whatsapp' => '(48) 98822-2934',
    'email' => 'flnexecutivoads@gmail.com',
    'cidade' => 'Florianópolis',
    'uf' => 'SC',
    'endereco' => 'Servidão Estação Lua Clara, Rio Vermelho, Florianópolis - SC, 88060',
    'rep_legal' => 'Matheus Brum',
    'pix' => 'flnexecutivoads@gmail.com',
    'pix_cidade' => 'Florianópolis',
    'assinatura_nome' => 'Matheus Brum',
    'atendente_nome' => 'Matheus Brum',
    'atendente_fone' => '(48) 99967-2557',
    'dono_nome' => 'Matheus Brum',
    'dono_email' => 'matheusbrumds@gmail.com',
    'dono_telefone' => '(48) 99967-2557',
    'comp_principal' => "Fln Transfer Executivo e Turismo\nCNPJ 41.581.147/0001-08\nServidão Estação Lua Clara, Rio Vermelho, Florianópolis - SC\nTel +55 48 98822-2934 · flnexecutivoads@gmail.com\nRep. legal: Matheus Brum",
    'comp_rodape' => 'Obrigado por viajar com a Fln Transfer Executivo e Turismo. Guarde este comprovante.',
    'contrato_pdf' => true,
    'logo' => $logoUri,
    'logo_lateral' => $logoUri,
    'assinatura' => $assUri,
];

$ok = com_db('marca_fln', static function (array &$db) use ($patch): bool {
    foreach ($patch as $chave => $valor) {
        $db['settings'][$chave] = $valor;
    }
    return true;
});

echo $ok ? "Marca FLN aplicada nas configurações.\n" : "Falha ao gravar.\n";
echo 'Logo bytes (b64): ' . strlen($logoUri) . "\n";
echo 'Assinatura bytes (b64): ' . strlen($assUri) . "\n";
