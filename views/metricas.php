<?php
declare(strict_types=1);
defined('APP') || exit;

$m = build_metrics($db, $range);
$live = true;
foreach ($db['automacoes'] as $autoRow) {
    if ($autoRow['id'] === 'resumo' && empty($autoRow['ativo'])) {
        $live = false;
    }
}
$subtitle = 'Indicadores de performance e KPIs do seu negócio.';
if ($live) {
    $subtitle .= ' Atualização automática em <strong id="tick">60</strong> s.';
}
view_head('', $subtitle);

$utilTxt = number_format($m['utilizacao'], 1, '.', '') . '%';
$viaDelta = number_format($m['viagens_delta'], 1, '.', '') . '%';
$maxDest = 0.0;
foreach ($m['destinos'] as $dest) {
    $maxDest = max($maxDest, (float) $dest['valor']);
}
$maxDest = max(1, $maxDest);
$funilBase = (int) $m['funil_sol'];
$wSol = $funilBase > 0 ? 100 : 0;
$wRes = $funilBase > 0 ? ($m['funil_res'] / $funilBase) * 100 : 0;
$wCon = $funilBase > 0 ? ($m['funil_con'] / $funilBase) * 100 : 0;
$channelMax = max(6, max($m['canal_transfer'] ?: [0]), max($m['canal_grupos'] ?: [0]), max($m['canal_motoristas'] ?: [0]));
?>
<div class="stack">
  <section class="kpi-grid">
    <article class="card">
      <div class="card-top">
        <span class="kicker">Frota ativa</span>
        <span class="card-tools"><?= icon('car') ?></span>
      </div>
      <div class="metric"><?= (int) $m['frota_ativos'] ?>/<?= (int) $m['frota_total'] ?></div>
      <div class="metric-sub"><a href="<?= e(href('veiculos')) ?>"><?= (int) $m['frota_ativos'] ?> de <?= (int) $m['frota_total'] ?> veículos</a></div>
    </article>
    <article class="card">
      <div class="card-top">
        <span class="kicker">Viagens hoje</span>
        <span class="card-tools"><?= icon('route') ?></span>
      </div>
      <div class="metric"><?= (int) $m['hoje_total'] ?></div>
      <div class="mini-stats">
        <span><i class="dot dot-green"></i> Concluí <b><?= (int) $m['hoje_conc'] ?></b></span>
        <span><i class="dot dot-orange"></i> Andam <b><?= (int) $m['hoje_and'] ?></b></span>
        <span><i class="dot dot-gray"></i> Pend. <b><?= (int) $m['hoje_pend'] ?></b></span>
      </div>
    </article>
    <article class="card">
      <div class="card-top">
        <span class="kicker">Utilização da frota</span>
        <span class="card-tools"><?= icon('activity') ?></span>
      </div>
      <div class="metric"><?= e($utilTxt) ?></div>
      <?= sparkline($m['chart_receita']) ?>
      <div class="kpi-foot">
        <span><?= (int) $m['viagens'] ?> viagens / <?= (int) $m['frota_total'] ?> veículos</span>
        <span><?= e($m['phrase']) ?></span>
      </div>
    </article>
    <article class="card">
      <div class="card-top">
        <span class="kicker">Receita (<?= e($m['short']) ?>)</span>
        <span class="card-tools"><?= delta_pill((float) $m['receita_delta']) ?> <?= icon('receipt') ?></span>
      </div>
      <div class="metric"><?= e(brl_compact((float) $m['receita'])) ?></div>
      <?= sparkline($m['chart_receita']) ?>
      <div class="kpi-foot">
        <span>Mês: <?= e(brl_compact((float) $m['receita_mes'])) ?></span>
        <span>Hoje: <?= e(brl_compact((float) $m['receita_hoje'])) ?></span>
      </div>
    </article>
  </section>

  <section class="kpi-grid row2">
    <article class="card">
      <div class="card-top">
        <span class="kicker">Ticket médio (período)</span>
        <span class="card-tools"><?= icon('receipt') ?></span>
      </div>
      <div class="metric"><?= e(brl((float) $m['ticket'])) ?></div>
      <?= sparkline($m['chart_receita']) ?>
    </article>
    <article class="card">
      <div class="card-top">
        <span class="kicker">Viagens (período)</span>
        <span class="card-tools"><?= icon('route') ?></span>
      </div>
      <div class="metric"><?= (int) $m['viagens'] ?></div>
      <div class="metric-sub"><?= e($viaDelta) ?> · <?= e($m['phrase']) ?></div>
    </article>
    <article class="card">
      <div class="card-top">
        <span class="kicker">Status da frota</span>
        <span class="card-tools"><?= icon('car') ?></span>
      </div>
      <div class="fleet-status">
        <div><span><i class="dot dot-green"></i> Ativos</span><strong><?= (int) $m['frota_ativos'] ?></strong></div>
        <div><span><i class="dot dot-orange"></i> Manutenção</span><strong><?= (int) $m['frota_manut'] ?></strong></div>
        <div><span><i class="dot dot-red"></i> Inativos</span><strong><?= (int) $m['frota_inativos'] ?></strong></div>
      </div>
    </article>
  </section>

  <section class="grid-2">
    <article class="card">
      <header class="card-h">
        <h2><?= icon('chart') ?> Evolução mensal</h2>
        <span class="hint">Últimos 12 meses</span>
      </header>
      <?= svg_line_chart($m['chart_labels'], [
          ['name' => 'Receita', 'color' => '#f97316', 'data' => $m['chart_receita'], 'axis' => 'left', 'fill' => true],
          ['name' => 'Viagens', 'color' => '#3b82f6', 'data' => $m['chart_viagens'], 'axis' => 'right', 'dash' => '6 4'],
          ['name' => 'Tendência', 'color' => '#94a3b8', 'data' => $m['chart_trend'], 'axis' => 'left', 'dash' => '3 4', 'width' => 1.6, 'hideDots' => true],
      ], [
          'label' => 'Evolução mensal de receita e viagens',
          'showRight' => true,
          'formatLeft' => 'money',
          'formatRight' => 'num',
          'maxRight' => max(1, max($m['chart_viagens'] ?: [0])),
      ]) ?>
    </article>
    <article class="card">
      <header class="card-h">
        <h2><?= icon('activity') ?> Solicitações por canal</h2>
        <span class="hint">Últimos 6 meses</span>
      </header>
      <?= svg_line_chart($m['canal_labels'], [
          ['name' => 'Transfer', 'color' => '#f97316', 'data' => $m['canal_transfer']],
          ['name' => 'Grupos', 'color' => '#3b82f6', 'data' => $m['canal_grupos']],
          ['name' => 'Motoristas', 'color' => '#22c55e', 'data' => $m['canal_motoristas']],
      ], [
          'label' => 'Solicitações por canal',
          'showRight' => false,
          'formatLeft' => 'int',
          'maxLeft' => $channelMax,
          'tickCount' => 3,
      ]) ?>
    </article>
  </section>

  <section class="grid-2 even">
    <article class="card">
      <header class="card-h">
        <h2><?= icon('list') ?> Últimos transfers</h2>
        <a class="count-badge" href="<?= e(href('solicitacoes')) ?>"><?= (int) $m['total_transfers'] ?></a>
      </header>
      <?php if ($m['ultimos'] === []): ?>
        <p class="quiet">Nenhum transfer cadastrado ainda.</p>
      <?php else: ?>
        <?php foreach ($m['ultimos'] as $trip): ?>
          <article class="trip">
            <div>
              <div class="trip-id">#<?= (int) $trip['id'] ?> · <?= e($trip['cliente']) ?></div>
              <div class="route-line"><?= e($trip['origem']) ?> → <?= e($trip['destino']) ?> · <?= e(br_date($trip['data'])) ?></div>
            </div>
            <div class="trip-side">
              <div class="price"><?= e(brl((float) $trip['valor'])) ?></div>
              <?= status_badge($trip['status']) ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </article>
    <article class="card">
      <header class="card-h">
        <h2><?= icon('users') ?> Últimos grupos</h2>
        <button class="icon-btn" type="button" data-open-dialog="dlg-grupo" aria-label="Novo grupo"><?= icon('plus') ?></button>
      </header>
      <?php if ($m['grupos'] === []): ?>
        <p class="quiet">Sem grupos cadastrados ainda.</p>
      <?php else: ?>
        <?php foreach ($m['grupos'] as $grupo): ?>
          <article class="trip">
            <div>
              <div class="trip-id"><?= e($grupo['nome']) ?></div>
              <div class="route-line"><?= e($grupo['destino']) ?> · <?= e(br_date($grupo['data'])) ?> · <?= (int) $grupo['pax'] ?> pax</div>
            </div>
            <div class="trip-side">
              <div class="price"><?= e(brl((float) $grupo['valor'])) ?></div>
              <form method="post" action="index.php" data-confirm="Excluir o grupo <?= e($grupo['nome']) ?>? Esta ação não pode ser desfeita.">
                <?= csrf_fields('delete_grupo') ?>
                <input type="hidden" name="id" value="<?= (int) $grupo['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Remover</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </article>
  </section>

  <section class="grid-2 even">
    <article class="card">
      <header class="card-h">
        <h2><?= icon('pin') ?> Top 5 destinos</h2>
        <span class="hint">Últimos 90d</span>
      </header>
      <?php if ($m['destinos'] === []): ?>
        <p class="quiet">Nenhum destino no período.</p>
      <?php else: ?>
        <ol class="destinos">
          <?php foreach ($m['destinos'] as $i => $dest): ?>
            <li>
              <div class="dest-top">
                <b><?= $i + 1 ?>. <?= e($dest['destino']) ?></b>
                <span><?= e(plural((int) $dest['viagens'], 'viagem', 'viagens')) ?> · <?= e(brl_compact((float) $dest['valor'])) ?></span>
              </div>
              <div class="bar"><span style="width: <?= e((string) round(((float) $dest['valor'] / $maxDest) * 100, 1)) ?>%"></span></div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </article>
    <article class="card">
      <header class="card-h">
        <h2><?= icon('funnel') ?> Funil de conversão</h2>
        <span class="hint">Últimos 90d</span>
      </header>
      <div class="funnel">
        <div class="funnel-row">
          <span class="f-ico orange"><?= icon('file') ?></span>
          <div>
            <div class="f-label"><span>Solicitações</span><span><?= (int) $m['funil_sol'] ?></span></div>
            <div class="f-track"><span class="c-orange" style="width: <?= e((string) $wSol) ?>%"></span></div>
          </div>
          <span class="f-meta">100% do funil</span>
        </div>
        <div class="funnel-row">
          <span class="f-ico blue"><?= icon('book') ?></span>
          <div>
            <div class="f-label"><span>Reservas</span><span><?= (int) $m['funil_res'] ?></span></div>
            <div class="f-track"><span class="c-blue" style="width: <?= e((string) round($wRes, 1)) ?>%"></span></div>
          </div>
          <span class="f-meta"><?= $funilBase > 0 ? e((string) round($wRes)) . '%' : '—' ?></span>
        </div>
        <div class="funnel-row">
          <span class="f-ico green"><?= icon('activity') ?></span>
          <div>
            <div class="f-label"><span>Concluídas</span><span><?= (int) $m['funil_con'] ?></span></div>
            <div class="f-track"><span class="c-green" style="width: <?= e((string) round($wCon, 1)) ?>%"></span></div>
          </div>
          <span class="f-meta"><?= $funilBase > 0 ? e((string) round($wCon)) . '%' : '—' ?></span>
        </div>
      </div>
    </article>
  </section>
</div>

<dialog id="dlg-grupo">
  <form method="post" action="index.php">
    <div class="dialog-h">
      <h2>Novo grupo</h2>
      <button class="icon-btn" type="button" data-close-dialog aria-label="Fechar">×</button>
    </div>
    <div class="dialog-b form-grid">
      <?= csrf_fields('add_grupo') ?>
      <label class="field full">Nome do grupo <input name="nome" required maxlength="80" placeholder="Excursão Serra Catarinense"></label>
      <label class="field">Data <input type="date" name="data" required value="<?= e(today()->format('Y-m-d')) ?>" placeholder="27/10/2026"></label>
      <label class="field">Passageiros <input type="number" name="pax" min="1" max="60" value="8" required placeholder="8"></label>
      <label class="field full">Destino <input name="destino" required maxlength="180" placeholder="Rua XV de Novembro, 200, Centro, Blumenau - SC"></label>
      <label class="field">Valor <input name="valor" inputmode="decimal" placeholder="1.250,00" value="0"></label>
      <div class="form-actions full">
        <button class="btn" type="button" data-close-dialog>Cancelar</button>
        <button class="btn btn-primary" type="submit">Salvar grupo</button>
      </div>
    </div>
  </form>
</dialog>
