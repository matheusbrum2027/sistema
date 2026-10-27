<?php
declare(strict_types=1);
defined('APP') || exit;
$rows = array_values(array_filter($db['lancamentos'], static fn(array $l): bool => ($l['papel'] ?? '') === 'receita' && ($l['status'] ?? '') === 'pendente'));
usort($rows, static fn(array $a, array $b): int => [$b['data'], $b['id']] <=> [$a['data'], $a['id']]);
$pg = slice_page($rows);
view_head('<a class="btn btn-sm" href="' . e(href('lancamentos')) . '">Todos os lançamentos</a>', 'Receitas geradas pelas reservas, incluindo viagens com data futura.');
?>
<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Data</th><th>Linha</th><th>Origem</th><th>Descrição</th><th>Valor</th><th>Estado</th><th>Meio</th><th>Baixa</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $row): ?>
          <tr>
            <td><?= e(br_date((string) $row['data'])) ?></td>
            <td><span class="tag"><?= e($row['linha'] ?? 'Principal') ?></span></td>
            <td><?= e($row['origem'] ?? 'transfer') ?></td>
            <td><?= e($row['descricao']) ?></td>
            <td><?= e(brl((float) $row['valor'])) ?></td>
            <td><?= status_badge('pendente') ?></td>
            <td><?= e($row['meio'] ?? '') ?: '—' ?></td>
            <td>
              <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Dar baixa no lançamento <?= e($row['descricao']) ?> e marcá-lo como pago?" data-confirm-2="Confirme novamente: este lançamento ficará pago."><?= csrf_fields('baixar_lancamento') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm" type="submit">Baixar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('receber', $pg) ?>
  <p class="help">Para marcar como pago, use a baixa da linha ou a página Lançamentos.</p>
</section>
