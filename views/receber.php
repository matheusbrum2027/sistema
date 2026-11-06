<?php
declare(strict_types=1);
defined('APP') || exit;

$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$mot = (int) ($_GET['mot'] ?? 0);
$fat = (string) ($_GET['fat'] ?? '');
$porId = [];
foreach ($db['transfers'] as $t) {
    $porId[(int) $t['id']] = $t;
}
$rows = array_values(array_filter($db['lancamentos'], static function (array $l) use ($de, $ate, $q, $mot, $fat, $porId): bool {
    if (($l['papel'] ?? '') !== 'receita' || ($l['status'] ?? '') !== 'pendente') {
        return false;
    }
    $data = (string) ($l['data'] ?? '');
    if ($de !== '' && $data < $de) {
        return false;
    }
    if ($ate !== '' && $data > $ate) {
        return false;
    }
    if ($q !== '' && !str_contains(mb_strtolower((string) ($l['descricao'] ?? '') . ' ' . (string) ($l['cliente'] ?? '')), mb_strtolower($q))) {
        return false;
    }
    $trip = $porId[(int) ($l['transfer_id'] ?? 0)] ?? null;
    $mid = (int) ($l['motorista_id'] ?? ($trip['motorista_id'] ?? 0));
    if ($mot > 0 && $mid !== $mot) {
        return false;
    }
    $faturado = !empty($l['faturado']) || !empty($trip['faturado']);
    if ($fat === 'sim' && !$faturado) {
        return false;
    }
    if ($fat === 'nao' && $faturado) {
        return false;
    }
    return true;
}));
usort($rows, static fn(array $a, array $b): int => [(string) ($a['vencimento'] ?? $a['data']), $a['id']] <=> [(string) ($b['vencimento'] ?? $b['data']), $b['id']]);
$total = (float) array_sum(array_column($rows, 'valor'));
$pg = slice_page($rows);
$keep = array_filter(['de' => $de, 'ate' => $ate, 'q' => $q, 'mot' => $mot ?: null, 'fat' => $fat]);
view_head('<a class="btn btn-sm" href="' . e(href('lancamentos')) . '">Todos os lançamentos</a>', 'O que o cliente ainda deve. Faturado vence no fim do mês, por boleto.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="receber">
  <label>De <input type="date" name="de" value="<?= e($de) ?>"></label>
  <label>Até <input type="date" name="ate" value="<?= e($ate) ?>"></label>
  <label>Cliente <input name="q" value="<?= e($q) ?>" placeholder="Nome ou reserva"></label>
  <label>Motorista
    <select name="mot">
      <option value="0">Todos</option>
      <?php foreach ($db['motoristas'] as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $mot === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Cobrança
    <select name="fat">
      <option value="">Todas</option>
      <option value="nao"<?= $fat === 'nao' ? ' selected' : '' ?>>Na hora</option>
      <option value="sim"<?= $fat === 'sim' ? ' selected' : '' ?>>Boleto do mês</option>
    </select>
  </label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<article class="card stat" style="margin-bottom:12px"><span>Em aberto neste filtro</span><strong><?= e(brl($total)) ?></strong></article>
<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Viagem</th><th>Vencimento</th><th>Cliente</th><th>Motorista</th><th>Cobrança</th><th>Valor</th><th>Baixa</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $row): ?>
          <?php $trip = $porId[(int) ($row['transfer_id'] ?? 0)] ?? null; $faturado = !empty($row['faturado']) || !empty($trip['faturado']); $mid = (int) ($row['motorista_id'] ?? ($trip['motorista_id'] ?? 0)); ?>
          <tr>
            <td><?= e(br_date((string) $row['data'])) ?><div class="hint"><?= e($row['descricao']) ?></div></td>
            <td><?= e(br_date((string) ($row['vencimento'] ?? $row['data']))) ?></td>
            <td><?= e((string) ($row['cliente'] ?? '')) ?></td>
            <td><?= e($mid > 0 ? nome_motorista($db, $mid) : '—') ?></td>
            <td><?= $faturado ? 'Boleto no fim do mês' : 'Na hora' ?></td>
            <td><?= e(brl((float) $row['valor'])) ?></td>
            <td>
              <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Dar baixa em <?= e($row['descricao']) ?>?" data-confirm-2="Confirme novamente: este recebimento ficará pago."><?= csrf_fields('baixar_lancamento') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm" type="submit">Baixar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('receber', $pg, $keep) ?>
</section>
