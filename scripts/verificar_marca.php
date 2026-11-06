<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$s = load_db()['settings'];
foreach ([
    'nome', 'razao', 'cnpj', 'telefone', 'whatsapp', 'email', 'endereco',
    'rep_legal', 'assinatura_nome', 'atendente_nome', 'atendente_fone', 'pix',
] as $k) {
    echo $k . ': ' . (string) ($s[$k] ?? '') . PHP_EOL;
}
echo 'logo: ' . (str_starts_with((string) ($s['logo'] ?? ''), 'data:image') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'assinatura: ' . (str_starts_with((string) ($s['assinatura'] ?? ''), 'data:image') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'contrato_pdf: ' . (!empty($s['contrato_pdf']) ? 'sim' : 'nao') . PHP_EOL;
