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
$weekStart = (int) $cursor->format('w');
$gridStart = $cursor->modify('-' . $weekStart . ' days');
$gridIni = $gridStart->format('Y-m-d');
$gridFim = $gridStart->modify('+41 days')->format('Y-m-d');
$hojeIso = today()->format('Y-m-d');
$limiteIso = today()->modify('+30 days')->format('Y-m-d');
$janelaIni = $gridIni < $hojeIso ? $gridIni : $hojeIso;
$janelaFim = $gridFim > $limiteIso ? $gridFim : $limiteIso;
$byDay = [];
$proximas = [];
$meuId = (int) ($_SESSION['motorista_id'] ?? 0);
$veiculosPorId = [];
foreach ($db['veiculos'] ?? [] as $veiculo) {
    $veiculosPorId[(int) ($veiculo['id'] ?? 0)] = strtoupper(trim((string) ($veiculo['tipo'] ?? '')));
}
$frotaTom = static function (string $tipo): string {
    $t = strtoupper(trim($tipo));
    if ($t === '') {
        return '';
    }
    if (str_contains($t, 'MINI')) {
        return 'mini';
    }
    if (str_contains($t, '07') || str_contains($t, '7LUG')) {
        return 'lugares';
    }
    if (str_contains($t, 'VAN') || str_contains($t, 'ONIBUS') || str_contains($t, 'MIC')) {
        return 'van';
    }
    if (str_contains($t, 'SED') || str_contains($t, 'HAT')) {
        return 'carro';
    }
    return 'carro';
};
$frotaRotulo = static function (string $tipo, string $categoria): string {
    $t = strtoupper(trim($tipo));
    if ($t !== '') {
        if (str_contains($t, 'MINI')) {
            return 'MINIVAN';
        }
        if (str_contains($t, '07') || str_contains($t, '7LUG')) {
            return '07LUG.';
        }
        if (str_contains($t, 'MIC')) {
            return 'MIC.VAN';
        }
        if (str_contains($t, 'ONIBUS')) {
            return 'ONIBUS';
        }
        if (str_contains($t, 'VAN')) {
            return 'VAN';
        }
        if (str_contains($t, 'SED')) {
            return 'SED';
        }
        if (str_contains($t, 'HAT')) {
            return 'HAT';
        }
        return mb_substr($t, 0, 8);
    }
    $c = strtoupper(trim($categoria));
    return $c !== '' ? mb_substr($c, 0, 8) : '';
};
foreach ($todas as $trip) {
    $statusTrip = (string) ($trip['status'] ?? '');
    if ($statusTrip === 'cancelada') {
        continue;
    }
    $d1 = (string) ($trip['data'] ?? '');
    $d2 = (string) ($trip['data_volta'] ?? '');
    if (($d1 === '' || $d1 < $janelaIni || $d1 > $janelaFim)
        && ($d2 === '' || $d2 < $janelaIni || $d2 > $janelaFim)) {
        continue;
    }
    foreach (agenda_legs($trip) as $leg) {
        if (!$admin && !perna_do_motorista($trip, (string) $leg['kind'], $meuId)) {
            continue;
        }
        $dataLeg = (string) ($leg['data'] ?? '');
        if ($dataLeg >= $gridIni && $dataLeg <= $gridFim) {
            $frota = $veiculosPorId[(int) ($trip['veiculo_id'] ?? 0)] ?? '';
            $cat = (string) ($trip['categoria'] ?? $trip['tipo_veiculo'] ?? '');
            $rotulo = $frotaRotulo($frota, $cat);
            $byDay[$dataLeg][] = $leg + [
                'cliente' => $trip['cliente'],
                'status' => $statusTrip,
                'veiculo' => $frota,
                'frota' => $frotaTom($frota !== '' ? $frota : $cat),
                'frota_label' => $rotulo,
            ];
        }
        if ($statusTrip !== 'concluida' && $dataLeg >= $hojeIso && $dataLeg <= $limiteIso) {
            $inverte = $leg['kind'] === 'volta' && (string) ($trip['tipo'] ?? '') === 'ida_volta';
            $proximas[] = $leg + [
                'de' => (string) ($inverte ? $trip['destino'] : $trip['origem']),
                'para' => (string) ($inverte ? $trip['origem'] : $trip['destino']),
                'cliente' => (string) $trip['cliente'],
                'status' => $statusTrip,
            ];
        }
    }
}
foreach ($byDay as &$legs) {
    usort($legs, static fn(array $a, array $b): int => $a['hora'] <=> $b['hora']);
}
unset($legs);
usort($proximas, static fn(array $a, array $b): int => [$a['data'], $a['hora']] <=> [$b['data'], $b['hora']]);
if (!$admin) {
    view_head('', 'Suas viagens. O acesso mostra somente o que foi atribuído a você.');
    echo '<p id="push-estado" class="help">Preparando os avisos deste celular…</p>';
} else {
    view_head();
}
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
        $qtd = count($legs);
    ?>
      <div class="cal-day<?= $day->format('Y-m') !== $mes ? ' out' : '' ?><?= $key === today()->format('Y-m-d') ? ' today' : '' ?>">
        <div class="cal-day-top">
          <b><?= e($day->format('j')) ?></b>
          <span class="cal-dow-nome"><?= e($nomesDia[(int) $day->format('w')]) ?></span>
          <?php if ($qtd > 0): ?>
            <span class="cal-qtd" title="<?= $qtd === 1 ? '1 corrida neste dia' : e((string) $qtd) . ' corridas neste dia' ?>"><?= $qtd ?></span>
          <?php endif; ?>
        </div>
        <div class="cal-itens">
        <?php foreach ($legs as $leg): ?>
          <?php
            $horaLeg = $leg['hora'] !== '' ? substr((string) $leg['hora'], 0, 5) : '--:--';
            $idCls = !empty($leg['passou']) ? 'chip-late' : 'chip-ok';
            if (($leg['status'] ?? '') === 'andamento') {
                $idCls = 'chip-run';
            } elseif (($leg['status'] ?? '') === 'concluida') {
                $idCls = 'chip-done';
            } elseif (!empty($leg['passou'])) {
                $idCls = 'chip-late';
            }
            $frotaLbl = (string) ($leg['frota_label'] ?? '');
          ?>
          <button type="button" class="chip chip-<?= e($leg['kind']) ?><?= !empty($leg['passou']) ? ' chip-passou' : '' ?>" data-open-dialog="dlg-ver-<?= (int) $leg['id'] ?>" data-reserva-id="<?= (int) $leg['id'] ?>" aria-label="Reserva #<?= (int) $leg['id'] ?>, <?= e($leg['label']) ?><?= $frotaLbl !== '' ? ' ' . e($frotaLbl) : '' ?> <?= e($horaLeg) ?>, <?= e($leg['place']) ?>">
            <span class="chip-id <?= $idCls ?>">#<?= (int) $leg['id'] ?></span>
            <?php if ($frotaLbl !== ''): ?><span class="chip-frota frota-<?= e(($leg['frota'] ?? '') !== '' ? $leg['frota'] : 'carro') ?>"><?= e($frotaLbl) ?></span><?php endif; ?>
            <span class="chip-tipo"><?= e($leg['label']) ?></span>
            <span class="chip-hora"><?= e($horaLeg) ?></span>
            <span class="chip-place"><?= e($leg['place']) ?></span>
          </button>
        <?php endforeach; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
  <p class="cal-note">Cada faixa: <strong>#código</strong> · frota · ida/volta · horário · local. O selo laranja no dia é a quantidade de corridas. Ida em vermelho, volta em verde. Toque para abrir.</p>
</div>
<?php if ($ver > 0): ?>
  <span hidden data-reserva-deep-link="<?= $ver ?>"></span>
<?php endif; ?>
