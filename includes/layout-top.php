<?php
declare(strict_types=1);
/** @var array $route */
/** @var array $db */
/** @var string $current */
/** @var string $range */
/** @var bool $bare */
$partial = !$bare && strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
$brand = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
$ranges = ['hoje' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', '12m' => '12 meses'];
$auto = $current === 'metricas';
$btnCorridas = in_array($current, ['agenda', 'reservas'], true)
    ? '<button class="btn topbar-atualizar" type="button" data-atualizar-corridas>' . icon('refresh') . ' Atualizar corridas</button>'
    : '';
foreach ($db['automacoes'] as $autoRow) {
    if ($autoRow['id'] === 'resumo' && empty($autoRow['ativo'])) {
        $auto = false;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <script>try{var escuro=localStorage.getItem('fln-tema')==='dark';if(escuro)document.documentElement.dataset.theme='dark';var barra=document.querySelector('meta[name="theme-color"]');if(barra)barra.setAttribute('content',escuro?'#12141a':'#eef0f3');}catch(e){}</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#f26b1a">
  <?php if (!preg_match('/Android/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))): ?>
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="FLN Transfer">
  <?php endif; ?>
  <meta name="robots" content="noindex">
  <title><?= e($route['title']) ?> · <?= e($brand) ?></title>
  <?php if (!$partial): ?>
  <script>
  (function () {
    var ua = navigator.userAgent || '';
    var ipad = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    if (/Android|iPhone|iPad|iPod/i.test(ua) || ipad) return;
    var link = document.createElement('link');
    link.rel = 'manifest';
    link.href = 'manifest.json';
    document.head.appendChild(link);
  })();
  </script>
  <?php
    [$faviconHref, $faviconMime] = brand_favicon($db);
  ?>
  <link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
  <?php if (!empty($_SESSION['auth'])): ?>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <?php endif; ?>
  <link rel="icon" href="<?= e($faviconHref) ?>" type="<?= e($faviconMime) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="assets/css/app.css?v=101">
  <?php endif; ?>
</head>
<?php
$notifLigado = !empty($_SESSION['auth']) && in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'motorista'], true);
$avisoUltimo = 0;
if ($notifLigado) {
    $papelAviso = (string) ($_SESSION['role'] ?? 'admin');
    $midAviso = (int) ($_SESSION['motorista_id'] ?? 0);
    foreach ($db['avisos'] ?? [] as $avisoLinha) {
        if (is_array($avisoLinha) && aviso_item_para($avisoLinha, $papelAviso, $midAviso)) {
            $avisoUltimo = max($avisoUltimo, (int) ($avisoLinha['id'] ?? 0));
        }
    }
}
?>
<body data-page="<?= e($current) ?>" data-papel="<?= e((string) ($_SESSION['role'] ?? '')) ?>" data-mid="<?= (int) ($_SESSION['motorista_id'] ?? 0) ?>" data-autorefresh="<?= $auto ? '1' : '0' ?>"<?php if ($notifLigado): ?> data-notif="1" data-vapid="<?= e((string) ($db['settings']['vapid_public'] ?? '')) ?>" data-aviso="<?= $avisoUltimo ?>" data-som="<?= empty($db['settings']['notif_som']) && array_key_exists('notif_som', $db['settings']) ? '0' : '1' ?>" data-vibrar="<?= empty($db['settings']['notif_vibrar']) && array_key_exists('notif_vibrar', $db['settings']) ? '0' : '1' ?>"<?php endif; ?>>
<?php if ($partial): ?>
  <header class="topbar" data-shell-nav>
    <div class="topbar-brand"></div>
    <?php if (!empty($route['filters'])): ?>
      <div class="range" role="tablist" aria-label="Período">
        <?php foreach ($ranges as $key => $label): ?>
          <a class="<?= $range === $key ? 'active' : '' ?>" href="<?= e(href('metricas', ['range' => $key])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="range-refresh" href="<?= e(current_url()) ?>" title="Atualizar" aria-label="Atualizar"><?= icon('refresh') ?></a>
      </div>
    <?php else: ?>
      <div class="crumb"><?= e($route['group']) ?> / <?= e($route['title']) ?></div>
      <?= $btnCorridas ?>
    <?php endif; ?>
  </header>
  <div class="content">
    <?= render_flash() ?>
<?php elseif ($bare): ?>
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
          <?= $btnCorridas ?>
        <?php endif; ?>
      </header>
      <div class="content">
        <?= render_flash() ?>
<?php endif; ?>
