<?php
declare(strict_types=1);
defined('APP') || exit;
$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$mot = (int) ($_GET['mot'] ?? 0);
$pago = (string) ($_GET['pago'] ?? '');
$rows = array_values(array_filter($db['transfers'], static function (array $t) use ($de, $ate, $q, $mot, $pago): bool {
    if (empty($t['faturado']) || $t['status'] === 'cancelada') {
        return false;
    }
    if ($de !== '' && $t['data'] < $de) {
        return false;
    }
    if ($ate !== '' && $t['data'] > $ate) {
        return false;
    }
    if ($mot > 0 && !viagem_do_motorista($t, $mot)) {
        return false;
    }
    if ($pago === 'sim' && empty($t['pago'])) {
        return false;
    }
    if ($pago === 'nao' && !empty($t['pago'])) {
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
view_head('<a class="btn btn-sm" href="' . e(href('receber')) . '">Contas a receber</a>', 'Corridas que a empresa paga juntas no fim do mês, por boleto.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="faturado">
  <label>Data da viagem (de) <input type="date" name="de" value="<?= e($de) ?>" placeholder="dd/mm/aaaa"></label>
  <label>Data da viagem (até) <input type="date" name="ate" value="<?= e($ate) ?>" placeholder="dd/mm/aaaa"></label>
  <label>Cliente <input name="q" value="<?= e($q) ?>" placeholder="Todos os clientes"></label>
  <label>Motorista
    <select name="mot">
      <option value="0">Todos</option>
      <?php foreach ($db['motoristas'] as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $mot === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Boleto
    <select name="pago">
      <option value="">Todos</option>
      <option value="nao"<?= $pago === 'nao' ? ' selected' : '' ?>>Em aberto</option>
      <option value="sim"<?= $pago === 'sim' ? ' selected' : '' ?>>Pago</option>
    </select>
  </label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<div class="stat-row">
  <article class="card stat"><span>Reservas faturadas</span><strong><?= count($rows) ?></strong></article>
  <article class="card stat accent"><span>Total a receber (faturado)</span><strong><?= e(brl($total)) ?></strong></article>
</div>
<section class="card" style="margin-top:12px">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nº</th><th>Cliente</th><th>Motorista</th><th>Data viagem</th><th>Vencimento</th><th>Valor</th><th>Estado</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $trip): ?>
          <tr>
            <td>#<?= (int) $trip['id'] ?></td>
            <td><?= e($trip['cliente']) ?></td>
            <td><?= e(nome_motorista($db, $trip['motorista_id'] ?? 0)) ?></td>
            <td><?= e(br_date((string) $trip['data'])) ?></td>
            <td><?= e(br_date(fim_do_mes((string) $trip['data']))) ?></td>
            <td><?= e(brl((float) $trip['valor'])) ?></td>
            <td><?= status_badge(!empty($trip['pago']) ? 'pago' : 'pendente') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('faturado', $pg, array_filter(['de' => $de, 'ate' => $ate, 'q' => $q, 'mot' => $mot ?: null, 'pago' => $pago])) ?>
  <p class="help">Dez corridas faturadas no mês formam um boleto com vencimento no último dia desse mês. A baixa, quando a empresa pagar, é feita em Contas a receber.</p>
</section>
