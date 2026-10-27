<?php
declare(strict_types=1);
/** @var array $route */
/** @var array $db */
/** @var string $current */
/** @var string $range */
/** @var bool $bare */
$brand = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
$ranges = ['hoje' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', '12m' => '12 meses'];
$auto = $current === 'metricas';
foreach ($db['automacoes'] as $autoRow) {
    if ($autoRow['id'] === 'resumo' && empty($autoRow['ativo'])) {
        $auto = false;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <script>try{if(localStorage.getItem('fln-tema')==='dark')document.documentElement.dataset.theme='dark';}catch(e){}</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="color-scheme" content="light dark">
  <meta name="robots" content="noindex">
  <title><?= e($route['title']) ?> · <?= e($brand) ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%23f26b1a'/%3E%3Ctext x='16' y='22' text-anchor='middle' font-family='Segoe UI,sans-serif' font-size='16' font-weight='700' fill='white'%3EET%3C/text%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="assets/css/app.css?v=25">
</head>
<body data-page="<?= e($current) ?>" data-autorefresh="<?= $auto ? '1' : '0' ?>">
<?php if ($bare): ?>
  <div class="auth-wrap">
<?php else: ?>
  <div class="scrim"></div>
  <div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main">
      <header class="topbar" data-shell-nav>
        <div class="topbar-brand">
          <button class="nav-toggle" type="button" data-nav-toggle aria-label="Abrir menu"><?= icon('menu') ?></button>
          <span><?= e($brand) ?> — Gestão de Frota</span>
        </div>
        <?php if (!empty($route['filters'])): ?>
          <div class="range" role="tablist" aria-label="Período">
            <?php foreach ($ranges as $key => $label): ?>
              <a class="<?= $range === $key ? 'active' : '' ?>" href="<?= e(href('metricas', ['range' => $key])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="range-refresh" href="<?= e(current_url()) ?>" title="Atualizar" aria-label="Atualizar"><?= icon('refresh') ?></a>
          </div>
        <?php else: ?>
          <div class="crumb"><?= e($route['group']) ?> / <?= e($route['title']) ?></div>
        <?php endif; ?>
      </header>
      <div class="content">
        <?= render_flash() ?>
<?php endif; ?>
