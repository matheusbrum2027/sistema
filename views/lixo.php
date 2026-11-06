<?php
declare(strict_types=1);
defined('APP') || exit;

lixo_reservas_garantir($db);
$itens = $db['lixo_reservas'];
usort($itens, static function (array $a, array $b): int {
    return strcmp((string) ($b['excluida_em'] ?? ''), (string) ($a['excluida_em'] ?? ''));
});
view_head('', 'Reservas excluídas ficam aqui por 30 dias. Depois o lixo esvazia sozinho.');
?>
<section class="card">
  <?php if ($itens === []): ?>
    <?= empty_box('trash', 'Lixo vazio', 'Quando você excluir uma reserva, ela aparece aqui para restaurar em até 30 dias.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table-lista">
        <thead>
          <tr>
            <th>Nº</th>
            <th>Cliente</th>
            <th>Trajeto</th>
            <th>Data</th>
            <th>Valor</th>
            <th>Excluída em</th>
            <th>Restam</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($itens as $item): ?>
            <?php
              $trip = is_array($item['transfer'] ?? null) ? $item['transfer'] : [];
              $id = (int) ($item['id'] ?? $trip['id'] ?? 0);
              $em = (string) ($item['excluida_em'] ?? '');
              $dias = lixo_dias_restantes($em);
            ?>
            <tr>
              <td data-label="Nº">#<?= $id ?></td>
              <td data-label="Cliente"><span class="clip" title="<?= e((string) ($trip['cliente'] ?? '')) ?>"><?= e((string) ($trip['cliente'] ?? '—')) ?></span></td>
              <td data-label="Trajeto"><span class="clip" title="<?= e(($trip['origem'] ?? '') . ' → ' . ($trip['destino'] ?? '')) ?>"><?= e(short_place((string) ($trip['origem'] ?? ''))) ?> → <?= e(short_place((string) ($trip['destino'] ?? ''))) ?></span></td>
              <td data-label="Data"><?= e(br_date((string) ($trip['data'] ?? ''))) ?> <span class="hint"><?= e((string) ($trip['hora'] ?? '')) ?></span></td>
              <td data-label="Valor"><?= e(brl((float) ($trip['valor'] ?? 0))) ?></td>
              <td data-label="Excluída em"><?php
                if ($em === '') {
                    echo '—';
                } else {
                    $dtEm = DateTimeImmutable::createFromFormat('Y-m-d H:i', $em)
                        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $em);
                    echo e($dtEm ? $dtEm->format('d/m/Y H:i') : $em);
                }
              ?></td>
              <td data-label="Restam"><?= $dias ?> dia<?= $dias === 1 ? '' : 's' ?></td>
              <td class="actions">
                <form method="post" action="index.php" data-confirm="Restaurar a reserva #<?= $id ?>? Ela volta para a agenda e o financeiro.">
                  <?= csrf_fields('restaurar_lixo') ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="btn btn-sm btn-primary" type="submit">Restaurar</button>
                </form>
                <form method="post" action="index.php" data-confirm="Apagar de vez a reserva #<?= $id ?>? Isso não tem volta.">
                  <?= csrf_fields('apagar_lixo') ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Apagar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
