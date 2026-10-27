<?php
declare(strict_types=1);
defined('APP') || exit;
$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$rows = array_values(array_filter($db['transfers'], static function (array $t) use ($de, $ate, $q): bool {
    if (empty($t['faturado']) || $t['status'] === 'cancelada') {
        return false;
    }
    if ($de !== '' && $t['data'] < $de) {
        return false;
    }
    if ($ate !== '' && $t['data'] > $ate) {
        return false;
    }
    if ($q !== '' && !str_contains(mb_strtolower((string) $t['cliente']), mb_strtolower($q))) {
        return false;
    }
    return true;
}));
usort($rows, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
$total = (float) array_sum(array_column($rows, 'valor'));
$pg = slice_page($rows);
view_head('<a class="btn btn-sm" href="' . e(href('receber')) . '">Contas a receber</a>', 'Reservas de transfer marcadas como faturadas.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="faturado">
  <label>Data da viagem (de) <input type="date" name="de" value="<?= e($de) ?>" placeholder="dd/mm/aaaa"></label>
  <label>Data da viagem (até) <input type="date" name="ate" value="<?= e($ate) ?>" placeholder="dd/mm/aaaa"></label>
  <label>Cliente <input name="q" value="<?= e($q) ?>" placeholder="Todos os clientes"></label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<div class="stat-row">
  <article class="card stat"><span>Reservas faturadas</span><strong><?= count($rows) ?></strong></article>
  <article class="card stat accent"><span>Total a receber (faturado)</span><strong><?= e(brl($total)) ?></strong></article>
</div>
<section class="card" style="margin-top:12px">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nº</th><th>Cliente</th><th>Data viagem</th><th>Valor</th><th>Pagamento</th><th>Estado</th><th>Criada em</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $trip): ?>
          <tr>
            <td>#<?= (int) $trip['id'] ?></td>
            <td><?= e($trip['cliente']) ?></td>
            <td><?= e(br_date((string) $trip['data'])) ?></td>
            <td><?= e(brl((float) $trip['valor'])) ?></td>
            <td><?= !empty($trip['pago']) ? e((string) ($trip['meio'] ?: 'pago')) : '—' ?></td>
            <td><?= status_badge(!empty($trip['pago']) ? 'pago' : 'pendente') ?></td>
            <td><?= e(br_date((string) ($trip['criado_em'] ?? $trip['data']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('faturado', $pg, array_filter(['de' => $de, 'ate' => $ate, 'q' => $q])) ?>
  <p class="help">As receitas continuam em Contas a receber até a baixa. Aqui ficam apenas as reservas com a marca de faturado.</p>
</section>
