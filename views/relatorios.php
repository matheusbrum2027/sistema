<?php
declare(strict_types=1);
defined('APP') || exit;
$mes = (string) ($_GET['mes'] ?? today()->format('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = today()->format('Y-m');
}
$cursor = DateTimeImmutable::createFromFormat('!Y-m-d', $mes . '-01') ?: today();
$viagens = array_values(array_filter($db['transfers'], static fn(array $t): bool => $t['status'] !== 'cancelada' && str_starts_with((string) $t['data'], $mes)));
$receita = (float) array_sum(array_column($viagens, 'valor'));
$pago = 0.0;
foreach ($viagens as $t) {
    if (!empty($t['pago'])) {
        $pago += (float) $t['valor'];
    }
}
$despesa = 0.0;
foreach ($db['lancamentos'] as $l) {
    if (($l['tipo'] ?? '') === 'saida' && ($l['status'] ?? '') !== 'cancelado' && str_starts_with((string) $l['data'], $mes)) {
        $despesa += (float) $l['valor'];
    }
}
view_head();
?>
<div class="month-nav">
  <a class="btn btn-sm" href="<?= e(href('relatorios', ['mes' => $cursor->modify('-1 month')->format('Y-m')])) ?>">Mês anterior</a>
  <strong><?= e(ucfirst(mes_longo($cursor))) ?> de <?= e($cursor->format('Y')) ?></strong>
  <a class="btn btn-sm" href="<?= e(href('relatorios', ['mes' => $cursor->modify('+1 month')->format('Y-m')])) ?>">Próximo mês</a>
</div>
<div class="stat-row">
  <article class="card stat"><span>Viagens</span><strong><?= count($viagens) ?></strong></article>
  <article class="card stat"><span>Receita</span><strong><?= e(brl($receita)) ?></strong></article>
  <article class="card stat"><span>Recebido</span><strong><?= e(brl($pago)) ?></strong></article>
  <article class="card stat"><span>Despesas</span><strong><?= e(brl($despesa)) ?></strong></article>
</div>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Viagens do mês</h2><button class="btn btn-sm" type="button" onclick="window.print()">Imprimir</button></header>
  <?php if ($viagens === []): ?>
    <?= empty_box('bars', 'Sem viagens no período', 'Alargue o mês para ver o histórico.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nº</th><th>Data</th><th>Cliente</th><th>Trajeto</th><th>Valor</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($viagens as $t): ?>
            <tr>
              <td>#<?= (int) $t['id'] ?></td>
              <td><?= e(br_date((string) $t['data'])) ?></td>
              <td><?= e($t['cliente']) ?></td>
              <td><?= e(short_place((string) $t['origem'])) ?> → <?= e(short_place((string) $t['destino'])) ?></td>
              <td><?= e(brl((float) $t['valor'])) ?></td>
              <td><?= status_badge((string) $t['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
