<?php
declare(strict_types=1);
defined('APP') || exit;
$rows = $db['inbox'];
usort($rows, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
view_head('<a class="btn btn-sm" href="' . e(href('solicitacoes', ['csv' => 1])) . '">Exportar CSV</a>', 'Registros recebidos via webhook do site (' . count($rows) . ')');
?>
<section class="card">
  <?php if ($rows === []): ?>
    <?= empty_box('file', 'Nenhuma solicitação recebida', 'Quando o site enviar um pedido para o webhook ativo, ele aparece aqui.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Quando</th><th>Cliente</th><th>Trajeto</th><th>Data</th><th>Valor</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e($row['criado_em']) ?></td>
              <td><?= e($row['cliente']) ?><div class="hint"><?= e($row['telefone']) ?> <?= e($row['email']) ?></div></td>
              <td><?= e($row['origem']) ?> → <?= e($row['destino']) ?></td>
              <td><?= e($row['data']) ?> <?= e($row['hora']) ?></td>
              <td><?= e(brl((float) $row['valor'])) ?></td>
              <td class="actions">
                <form method="post" action="index.php"><?= csrf_fields('inbox_to_reserva') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-primary" type="submit">Virar reserva</button></form>
                <form method="post" action="index.php" data-confirm="Excluir a solicitação de <?= e($row['cliente']) ?>? Esta ação não pode ser desfeita."><?= csrf_fields('delete_inbox') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
