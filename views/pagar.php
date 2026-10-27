<?php
declare(strict_types=1);
defined('APP') || exit;
$rows = array_values(array_filter($db['lancamentos'], static fn(array $l): bool => ($l['tipo'] ?? '') === 'saida' && ($l['status'] ?? '') === 'pendente'));
usort($rows, static fn(array $a, array $b): int => [$b['data'], $b['id']] <=> [$a['data'], $a['id']]);
$pg = slice_page($rows);
view_head('<a class="btn btn-sm" href="' . e(href('lancamentos')) . '">Lançamentos</a>', 'Combustível, manutenção, taxas e repasses ao motorista.');
?>
<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Estado</th></tr></thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
          <tr><td colspan="5">Nenhuma conta em aberto.</td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $row): ?>
          <tr>
            <td><?= e(br_date((string) $row['data'])) ?></td>
            <td><?= e($row['categoria']) ?></td>
            <td><?= e($row['descricao']) ?></td>
            <td class="money-neg"><?= e(brl((float) $row['valor'])) ?></td>
            <td><?= status_badge('pendente') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('pagar', $pg) ?>
</section>
