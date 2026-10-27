<?php
declare(strict_types=1);
/** @var string $current */
/** @var array $db */
?>
<aside class="sidebar" data-shell-nav>
  <a class="logo" href="<?= e(href('agenda')) ?>">
    <span>
      <strong><?= e($db['settings']['nome']) ?></strong>
      <small>Gestão de Frota</small>
    </span>
  </a>
  <nav class="nav-scroll" data-shell-nav aria-label="Menu principal">
    <?php if (($_SESSION['role'] ?? 'admin') === 'motorista'): ?>
      <div class="nav-section">Motorista</div>
      <div class="nav-sub" style="padding-left:10px">
        <a class="nav-link<?= $current === 'agenda' ? ' active' : '' ?>" href="<?= e(href('agenda')) ?>"<?= $current === 'agenda' ? ' aria-current="page"' : '' ?>>
          <?= icon('calendar') ?> Minhas viagens
        </a>
      </div>
    <?php else: ?>
    <?php foreach (navigation() as $section): ?>
      <?php if (!empty($section['label'])): ?>
        <div class="nav-section"><?= e($section['label']) ?></div>
      <?php endif; ?>
      <?php foreach ($section['groups'] as $group): ?>
        <?php if (!empty($group['direct'])): ?>
          <?php $item = $group['items'][0]; ?>
          <a class="nav-link direct<?= $current === $item['id'] ? ' active' : '' ?>" href="<?= e(href($item['id'])) ?>"<?= $current === $item['id'] ? ' aria-current="page"' : '' ?>>
            <span class="nav-ico"><?= icon($group['icon']) ?></span>
            <?= e($group['label']) ?>
          </a>
          <?php continue; ?>
        <?php endif; ?>
        <details class="nav-group" data-group="<?= e($group['id']) ?>" open>
          <summary>
            <span class="nav-ico"><?= icon($group['icon']) ?></span>
            <span><?= e($group['label']) ?></span>
            <span class="chev"><?= icon('chevron') ?></span>
          </summary>
          <div class="nav-sub">
            <?php foreach ($group['items'] as $item): ?>
              <a class="nav-link<?= $current === $item['id'] ? ' active' : '' ?>" href="<?= e(href($item['id'])) ?>"<?= $current === $item['id'] ? ' aria-current="page"' : '' ?>>
                <?= icon($item['icon']) ?>
                <?= e($item['title']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>
  <div class="sidebar-foot">
    <div class="zoom-row">
      <?= icon('zoom') ?>
      <span>Zoom</span>
      <span class="zoom-controls">
        <button type="button" data-zoom="-1" aria-label="Diminuir zoom">−</button>
        <strong data-zoom-label>100%</strong>
        <button type="button" data-zoom="1" aria-label="Aumentar zoom">+</button>
      </span>
    </div>
    <button class="logout-btn theme-btn" type="button" data-theme-toggle>
      <?= icon('moon') ?> <span data-theme-label>Modo escuro</span>
    </button>
    <form method="post" action="index.php" onsubmit="return confirm('Sair do painel?');">
      <?= csrf_fields('logout') ?>
      <button class="logout-btn" type="submit"><?= icon('logout') ?> Sair</button>
    </form>
  </div>
</aside>
