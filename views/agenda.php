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
$meuId = (int) ($_SESSION['motorista_id'] ?? 0);
foreach ($todas as $trip) {
    if ($trip['status'] === 'cancelada') {
        continue;
    }
    foreach (agenda_legs($trip) as $leg) {
        if (!$admin && !perna_do_motorista($trip, (string) $leg['kind'], $meuId)) {
            continue;
        }
        $byDay[$leg['data']][] = $leg + ['cliente' => $trip['cliente']];
    }
}
foreach ($byDay as &$legs) {
    usort($legs, static fn(array $a, array $b): int => $a['hora'] <=> $b['hora']);
}
unset($legs);
$weekStart = (int) $cursor->format('w');
$gridStart = $cursor->modify('-' . $weekStart . ' days');
if (!$admin) {
    view_head('', 'Suas viagens. O acesso mostra somente o que foi atribuído a você.');
} else {
    view_head();
}
$hojeIso = today()->format('Y-m-d');
$limiteIso = today()->modify('+30 days')->format('Y-m-d');
$proximas = [];
foreach ($todas as $trip) {
    if (in_array((string) $trip['status'], ['cancelada', 'concluida'], true)) {
        continue;
    }
    foreach (agenda_legs($trip) as $leg) {
        if (!$admin && !perna_do_motorista($trip, (string) $leg['kind'], $meuId)) {
            continue;
        }
        if ($leg['data'] < $hojeIso || $leg['data'] > $limiteIso) {
            continue;
        }
        $inverte = $leg['kind'] === 'volta' && (string) ($trip['tipo'] ?? '') === 'ida_volta';
        $proximas[] = $leg + [
            'de' => (string) ($inverte ? $trip['destino'] : $trip['origem']),
            'para' => (string) ($inverte ? $trip['origem'] : $trip['destino']),
            'cliente' => (string) $trip['cliente'],
            'status' => (string) $trip['status'],
        ];
    }
}
usort($proximas, static fn(array $a, array $b): int => [$a['data'], $a['hora']] <=> [$b['data'], $b['hora']]);
?>
<section class="agenda-lista<?= $admin ? ' so-celular' : '' ?>" aria-label="Próximas viagens">
  <h2 class="sec-title">Próximas viagens</h2>
  <?php if ($proximas === []): ?>
    <p class="empty-box"><strong>Nenhuma viagem nos próximos 30 dias.</strong> <?= $admin ? 'As reservas aparecem aqui assim que forem criadas.' : 'Quando o administrador atribuir uma reserva a você, ela aparece aqui.' ?></p>
  <?php else: ?>
    <?php foreach ($proximas as $p): ?>
      <?php $d = DateTimeImmutable::createFromFormat('!Y-m-d', $p['data']); ?>
      <button type="button" class="viagem-card" data-open-dialog="dlg-ver-<?= (int) $p['id'] ?>" data-reserva-id="<?= (int) $p['id'] ?>">
        <span class="viagem-quando">
          <strong><?= $p['data'] === $hojeIso ? 'Hoje' : e($d ? $d->format('d/m') : $p['data']) ?></strong>
          <span><?= e($p['hora'] !== '' ? $p['hora'] : '--:--') ?></span>
        </span>
        <span class="viagem-info">
          <span class="viagem-topo"><span class="chip-meta"><span class="<?= $p['passou'] ? 'chip-late' : 'chip-ok' ?>">#<?= (int) $p['id'] ?></span> <?= e($p['label']) ?></span> <?= status_badge($p['status']) ?></span>
          <span class="viagem-rota"><?= e($p['de']) ?> → <?= e($p['para']) ?></span>
          <span class="viagem-cli"><?= e($p['cliente']) ?></span>
        </span>
      </button>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<div class="cal-wrap">
  <div class="cal-head">
    <a class="cal-nav" href="<?= e(href('agenda', ['mes' => $cursor->modify('-1 month')->format('Y-m')])) ?>" aria-label="Mês anterior">‹</a>
    <strong><?= e(ucfirst(mes_longo($cursor))) ?> <?= e($cursor->format('Y')) ?></strong>
    <a class="cal-nav" href="<?= e(href('agenda', ['mes' => $cursor->modify('+1 month')->format('Y-m')])) ?>" aria-label="Próximo mês">›</a>
    <a class="btn btn-sm cal-hoje" href="<?= e(href('agenda', ['mes' => today()->format('Y-m')])) ?>">Hoje</a>
  </div>
  <div class="cal-grid">
    <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $dow): ?>
      <div class="cal-dow"><?= $dow ?></div>
    <?php endforeach; ?>
    <?php
      $nomesDia = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
      for ($i = 0; $i < 42; $i++):
        $day = $gridStart->modify('+' . $i . ' days');
        $key = $day->format('Y-m-d');
        $legs = $byDay[$key] ?? [];
    ?>
      <div class="cal-day<?= $day->format('Y-m') !== $mes ? ' out' : '' ?><?= $key === today()->format('Y-m-d') ? ' today' : '' ?>">
        <b><?= e($day->format('j')) ?></b>
        <span class="cal-dow-nome"><?= e($nomesDia[(int) $day->format('w')]) ?></span>
        <div class="cal-itens">
        <?php foreach ($legs as $leg): ?>
          <?php $horaLeg = $leg['hora'] !== '' ? substr((string) $leg['hora'], 0, 5) : '--:--'; ?>
          <button type="button" class="chip chip-<?= e($leg['kind']) ?>" data-open-dialog="dlg-ver-<?= (int) $leg['id'] ?>" data-reserva-id="<?= (int) $leg['id'] ?>" aria-label="Reserva #<?= (int) $leg['id'] ?>, <?= e($leg['label']) ?> <?= e($horaLeg) ?>, <?= e($leg['place']) ?>">
            <span class="<?= $leg['passou'] ? 'chip-late' : 'chip-ok' ?>">#<?= (int) $leg['id'] ?></span>
            <span class="chip-tipo"><?= e($leg['label']) ?></span>
            <span class="chip-hora"><?= e($horaLeg) ?></span>
            <span class="chip-place"><?= e($leg['place']) ?></span>
          </button>
        <?php endforeach; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
  <p class="cal-note">Reservas com data e hora preenchidas aparecem no dia correspondente. Ida e volta geram entradas separadas (com horários distintos quando informados). Toque numa reserva para ver os detalhes no centro da tela. O código fica <span class="chip-ok">verde</span> se o horário do dia ainda não passou e <span class="chip-late">vermelho</span> se passou.</p>
</div>
<?php if ($ver > 0): ?>
  <span hidden data-reserva-deep-link="<?= $ver ?>"></span>
<?php endif; ?>
