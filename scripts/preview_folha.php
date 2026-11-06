<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$db = load_db();
$trip = null;
foreach ($db['transfers'] as $t) {
    if ((int) ($t['id'] ?? 0) === 3787) {
        $trip = $t;
        break;
    }
}
if ($trip === null) {
    echo "Reserva 3787 não encontrada.\n";
    exit(1);
}
ob_start();
reserva_folha($db, $trip);
$html = (string) ob_get_clean();
$out = dirname(__DIR__) . '/scripts/pdf_preview/folha-3787.html';
file_put_contents($out, '<!DOCTYPE html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../../assets/css/app.css"><style>body{margin:0;background:#e8ebf0}.reserva-folha{max-width:820px;margin:20px auto}</style></head><body>' . $html . '</body></html>');
echo "OK " . strlen($html) . " bytes -> $out\n";
echo (str_contains($html, 'doc-banner') ? 'banner OK' : 'banner MISSING') . "\n";
echo (str_contains($html, 'doc-fact-route') ? 'route OK' : 'route MISSING') . "\n";
echo (str_contains($html, 'doc-cancel') || str_contains($html, 'Cancelamento') ? 'cancel OK' : 'cancel MISSING') . "\n";
echo (str_contains($html, 'Página') ? 'pages OK' : 'pages MISSING') . "\n";
