<?php
declare(strict_types=1);
defined('APP') || exit;

$mes = (string) ($_GET['mes'] ?? today()->format('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = today()->format('Y-m');
}
$cursor = DateTimeImmutable::createFromFormat('!Y-m-d', $mes . '-01', new DateTimeZone('America/Sao_Paulo'));
if (!$cursor) {
    $cursor = today()->modify('first day of this month');
    $mes = $cursor->format('Y-m');
}
$ver = (int) ($_GET['ver'] ?? 0);
$role = (string) ($_SESSION['role'] ?? 'admin');
$admin = $role !== 'motorista';
$todas = $db['transfers'];
if (!$admin) {
    $meu = (int) ($_SESSION['motorista_id'] ?? 0);
    $todas = array_values(array_filter($todas, static fn(array $t): bool => viagem_do_motorista($t, $meu)));
}
$byDay = [];
foreach ($todas as $trip) {
    if ($trip['status'] === 'cancelada') {
        continue;
    }
    foreach (agenda_legs($trip) as $leg) {
        $byDay[$leg['data']][] = $leg + ['cliente' => $trip['cliente']];
    }
}
foreach ($byDay as &$legs) {
    usort($legs, static fn(array $a, array $b): int => $a['hora'] <=> $b['hora']);
}
unset($legs);
$weekStart = (int) $cursor->format('N');
$gridStart = $cursor->modify('-' . ($weekStart - 1) . ' days');
if (!$admin) {
    view_head('', 'Suas viagens. O acesso mostra somente o que foi atribuído a você.');
} else {
    view_head();
}
?>
<div class="cal-wrap">
  <div class="cal-head">
    <a class="btn btn-sm" href="<?= e(href('agenda', ['mes' => $cursor->modify('-1 month')->format('Y-m')])) ?>">Mês anterior</a>
    <strong><?= e(ucfirst(mes_longo($cursor))) ?> <?= e($cursor->format('Y')) ?></strong>
    <a class="btn btn-sm" href="<?= e(href('agenda', ['mes' => $cursor->modify('+1 month')->format('Y-m')])) ?>">Próximo mês</a>
  </div>
  <div class="cal-grid">
    <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $dow): ?>
      <div class="cal-dow"><?= $dow ?></div>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < 42; $i++): ?>
      <?php
        $day = $gridStart->modify('+' . $i . ' days');
        $key = $day->format('Y-m-d');
        $legs = $byDay[$key] ?? [];
      ?>
      <div class="cal-day<?= $day->format('Y-m') !== $mes ? ' out' : '' ?><?= $key === today()->format('Y-m-d') ? ' today' : '' ?>">
        <b><?= e($day->format('j')) ?></b>
        <?php foreach ($legs as $leg): ?>
          <button type="button" class="chip chip-<?= e($leg['kind']) ?>" data-open-dialog="dlg-ver-<?= (int) $leg['id'] ?>" aria-label="Reserva #<?= (int) $leg['id'] ?>, <?= e($leg['label']) ?> <?= e($leg['hora']) ?>, <?= e($leg['place']) ?>">
            <span class="chip-meta"><span class="<?= $leg['passou'] ? 'chip-late' : 'chip-ok' ?>">#<?= (int) $leg['id'] ?></span> <?= e($leg['label']) ?> <?= e($leg['hora']) ?></span>
            <span class="chip-place"><?= e($leg['place']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
  <p class="cal-note">Reservas com data e hora preenchidas aparecem no dia correspondente. Ida e volta geram entradas separadas (com horários distintos quando informados). Toque numa reserva para ver os detalhes no centro da tela. O código fica <span class="chip-ok">verde</span> se o horário do dia ainda não passou e <span class="chip-late">vermelho</span> se passou.</p>
</div>
<?php foreach ($todas as $trip): ?>
  <dialog id="dlg-ver-<?= (int) $trip['id'] ?>">
    <header class="dialog-h">
      <h2>Detalhes da reserva #<?= (int) $trip['id'] ?></h2>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </header>
    <div class="dialog-b"><?php reserva_detalhes($db, $trip); ?></div>
    <footer class="dlg-foot">
      <?php if ($admin): ?>
        <a class="btn btn-sm" href="<?= e(href('reservas', ['edit' => $trip['id']])) ?>">Editar</a>
        <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="<?= !empty($trip['faturado']) ? 'Tirar o faturamento da reserva #' . (int) $trip['id'] . '?' : 'Marcar a reserva #' . (int) $trip['id'] . ' como faturada?' ?>" data-confirm-2="<?= !empty($trip['faturado']) ? 'Confirme novamente para tirar o faturamento da reserva #' . (int) $trip['id'] . '.' : 'Confirme novamente: a reserva #' . (int) $trip['id'] . ' ficará faturada.' ?>">
          <?= csrf_fields('faturar_transfer') ?>
          <input type="hidden" name="id" value="<?= (int) $trip['id'] ?>">
          <button class="btn btn-sm" type="submit"><?= !empty($trip['faturado']) ? 'Tirar faturamento' : 'Faturar' ?></button>
        </form>
        <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Marcar a reserva #<?= (int) $trip['id'] ?> como concluída?" data-confirm-2="Confirme novamente: a reserva #<?= (int) $trip['id'] ?> ficará concluída.">
          <?= csrf_fields('set_transfer_status') ?>
          <input type="hidden" name="id" value="<?= (int) $trip['id'] ?>">
          <input type="hidden" name="status" value="concluida">
          <button class="btn btn-sm btn-primary" type="submit">Concluir</button>
        </form>
      <?php endif; ?>
      <?php if (!$admin): ?>
        <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Alterar o status da reserva #<?= (int) $trip['id'] ?>?" data-confirm-2="Confirme novamente a mudança de status da reserva #<?= (int) $trip['id'] ?>.">
          <?= csrf_fields('set_transfer_status') ?>
          <input type="hidden" name="id" value="<?= (int) $trip['id'] ?>">
          <label class="field">Status
            <select name="status">
              <?php foreach (['pendente' => 'Pendente', 'reserva' => 'Reservada', 'andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'] as $k => $lab): ?>
                <option value="<?= e($k) ?>"<?= ($trip['status'] ?? '') === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="btn btn-sm btn-primary" type="submit">Atualizar status</button>
        </form>
      <?php endif; ?>
      <?php if (reserva_pode_pdf($trip)): ?>
        <button class="btn btn-sm btn-primary" type="button" data-open-dialog="dlg-pdf-<?= (int) $trip['id'] ?>">Baixar PDF</button>
      <?php endif; ?>
    </footer>
  </dialog>
  <?php if (reserva_pode_pdf($trip)): ?>
    <dialog id="dlg-pdf-<?= (int) $trip['id'] ?>" class="dlg-pdf">
      <header class="dialog-h no-print">
        <h2>Pré-visualização da reserva #<?= (int) $trip['id'] ?></h2>
        <div class="dialog-tools">
          <button class="btn btn-sm btn-primary" type="button" data-print data-confirm-vezes="2" data-confirm="Baixar o PDF da reserva #<?= (int) $trip['id'] ?>?" data-confirm-2="Confirme novamente para gerar o PDF da reserva #<?= (int) $trip['id'] ?>.">Baixar PDF</button>
          <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
        </div>
      </header>
      <div class="dialog-b"><?php reserva_folha($db, $trip); ?></div>
      <footer class="dlg-foot no-print">
        <button class="btn btn-primary" type="button" data-print data-confirm-vezes="2" data-confirm="Baixar o PDF da reserva #<?= (int) $trip['id'] ?>?" data-confirm-2="Confirme novamente para gerar o PDF da reserva #<?= (int) $trip['id'] ?>.">Baixar PDF</button>
        <button class="btn" type="button" data-close-dialog>Fechar</button>
      </footer>
    </dialog>
  <?php endif; ?>
<?php endforeach; ?>
<?php if ($ver > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const dlg = document.getElementById('dlg-ver-<?= $ver ?>');
  if (!dlg) return;
  dlg.setAttribute('closedby', 'none');
  dlg.showModal();
  dlg.addEventListener('close', () => {
    const u = new URL(window.location.href);
    u.searchParams.delete('ver');
    history.replaceState({}, '', u);
  }, { once: true });
});
</script>
<?php endif; ?>
