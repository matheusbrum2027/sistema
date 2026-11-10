<?php
declare(strict_types=1);
defined('APP') || exit;

$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
$mot = (int) ($_GET['mot'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$rows = array_values(array_filter($db['lancamentos'], static function (array $l) use ($de, $ate, $mot, $q): bool {
    if (($l['tipo'] ?? '') !== 'saida' || ($l['status'] ?? '') !== 'pendente') {
        return false;
    }
    $data = (string) ($l['data'] ?? '');
    if ($de !== '' && $data < $de) {
        return false;
    }
    if ($ate !== '' && $data > $ate) {
        return false;
    }
    if ($mot > 0 && (int) ($l['motorista_id'] ?? 0) !== $mot && ($l['papel'] ?? '') === 'repasse') {
        return false;
    }
    if ($mot > 0 && ($l['papel'] ?? '') !== 'repasse') {
        return false;
    }
    if ($q !== '' && !str_contains(mb_strtolower((string) ($l['descricao'] ?? '') . ' ' . (string) ($l['cliente'] ?? '') . ' ' . (string) ($l['categoria'] ?? '')), mb_strtolower($q))) {
        return false;
    }
    return true;
}));
usort($rows, static fn(array $a, array $b): int => [$b['data'], $b['id']] <=> [$a['data'], $a['id']]);
$total = (float) array_sum(array_column($rows, 'valor'));
$pg = slice_page($rows);
$keep = array_filter(['de' => $de, 'ate' => $ate, 'mot' => $mot ?: null, 'q' => $q]);
view_head('<a class="btn btn-sm" href="' . e(href('lancamentos')) . '">Lançamentos</a>', 'Despesas e o pro-labore de cada motorista. O repasse nasce quando a viagem é concluída.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="pagar">
  <label>De <input type="date" name="de" value="<?= e($de) ?>"></label>
  <label>Até <input type="date" name="ate" value="<?= e($ate) ?>"></label>
  <label>Motorista
    <select name="mot">
      <option value="0">Todos, com despesas</option>
      <?php foreach ($db['motoristas'] as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $mot === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Busca <input name="q" value="<?= e($q) ?>" placeholder="Repasse ou combustível"></label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<article class="card stat" style="margin-bottom:12px"><span>Em aberto neste filtro</span><strong><?= e(brl($total)) ?></strong></article>
<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Motorista</th><th>Valor</th><th>Baixa</th></tr></thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
          <tr><td colspan="6">Nenhuma conta em aberto.</td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $row): ?>
          <?php $mid = (int) ($row['motorista_id'] ?? 0); ?>
          <tr>
            <td><?= e(br_date((string) $row['data'])) ?></td>
            <td><?= e((string) ($row['papel'] ?? '') === 'repasse' ? 'Pro-labore' : (string) $row['categoria']) ?></td>
            <td><?= e($row['descricao']) ?></td>
            <td><?= e($mid > 0 ? nome_motorista($db, $mid) : (string) ($row['cliente'] ?? '—')) ?></td>
            <td class="money-neg"><?= e(brl((float) $row['valor'])) ?></td>
            <td>
              <form method="post" action="index.php" data-confirm="Pagar <?= e($row['descricao']) ?>?"><?= csrf_fields('baixar_lancamento') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm" type="submit">Pagar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('pagar', $pg, $keep) ?>
</section>
