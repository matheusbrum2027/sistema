<?php
declare(strict_types=1);
defined('APP') || exit;
$mot = ficha_motorista_do_token($db, (string) ($_GET['c'] ?? ''));
$empresa = trim((string) ($db['settings']['nome'] ?? 'Transfer FLN'));
if ($empresa === '') {
    $empresa = 'Transfer FLN';
}
$cidade = is_array($mot) ? ficha_cidade($mot) : '';
$ativo = is_array($mot) && (string) ($mot['status'] ?? '') === 'ativo';
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificar motorista · <?= e($empresa) ?></title>
  <style>
    body { margin: 0; min-height: 100dvh; display: grid; place-items: center; background: #f4f6f8; color: #1b2433; font-family: "Segoe UI", sans-serif; }
    main { width: min(440px, calc(100% - 32px)); background: #fff; border: 1px solid #e6e8ee; border-radius: 12px; padding: 28px 24px; box-shadow: 0 12px 32px rgba(16, 24, 40, .08); }
    .barra { width: 42px; height: 4px; background: #c6a052; border-radius: 4px; margin-bottom: 14px; }
    h1 { margin: 0 0 6px; font-size: 22px; color: #1b284a; }
    p { margin: 0 0 10px; line-height: 1.45; color: #5c6778; }
    .ok { display: inline-block; margin: 8px 0 14px; padding: 4px 10px; border-radius: 99px; background: #e3f6e9; color: #1c844f; font-weight: 700; font-size: 13px; }
    .no { display: inline-block; margin: 8px 0 14px; padding: 4px 10px; border-radius: 99px; background: #f8e8e6; color: #9b2c2c; font-weight: 700; font-size: 13px; }
    strong { color: #1b284a; }
  </style>
</head>
<body>
<main>
  <div class="barra"></div>
  <h1>Verificação de autenticidade</h1>
  <?php if (!is_array($mot)): ?>
    <span class="no">Não encontrado</span>
    <p>Este QR não corresponde a um motorista da frota <?= e($empresa) ?>.</p>
  <?php else: ?>
    <span class="<?= $ativo ? 'ok' : 'no' ?>"><?= $ativo ? 'Motorista oficial' : 'Cadastro inativo' ?></span>
    <p><strong><?= e((string) $mot['nome']) ?></strong></p>
    <p><?= e($cidade !== '' ? $cidade : 'Cidade não informada') ?></p>
    <p><?= $ativo ? 'Este cadastro está ativo na frota. Endereço, documentos e contato não aparecem nesta página.' : 'O cadastro existe, mas não está ativo na frota. Nenhum documento é exibido aqui.' ?></p>
  <?php endif; ?>
  <p><?= e($empresa) ?></p>
</main>
</body>
</html>
