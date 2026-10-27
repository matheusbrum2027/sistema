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
$noMes = static function (string $data) use ($mes): bool {
    return str_starts_with($data, $mes);
};
$receitas = 0.0;
$recebido = 0.0;
$pendReceber = 0.0;
foreach ($db['transfers'] as $t) {
    if ($t['status'] === 'cancelada' || !$noMes((string) $t['data'])) {
        continue;
    }
    $receitas += (float) $t['valor'];
    if (!empty($t['pago'])) {
        $recebido += (float) $t['valor'];
    } else {
        $pendReceber += (float) $t['valor'];
    }
}
$despTotal = 0.0;
$despPaga = 0.0;
$despPend = 0.0;
foreach ($db['lancamentos'] as $l) {
    if (($l['tipo'] ?? '') !== 'saida' || ($l['status'] ?? '') === 'cancelado' || !$noMes((string) $l['data'])) {
        continue;
    }
    $despTotal += (float) $l['valor'];
    if (($l['status'] ?? '') === 'pago') {
        $despPaga += (float) $l['valor'];
    } else {
        $despPend += (float) $l['valor'];
    }
}
$lucroReal = $recebido - $despPaga;
$lucroProj = $receitas - $despTotal;
view_head();
?>
<div class="month-nav">
  <a class="btn btn-sm" href="<?= e(href('fin-dashboard', ['mes' => $cursor->modify('-1 month')->format('Y-m')])) ?>">‹</a>
  <strong><?= e(ucfirst(mes_longo($cursor))) ?> de <?= e($cursor->format('Y')) ?></strong>
  <a class="btn btn-sm" href="<?= e(href('fin-dashboard', ['mes' => $cursor->modify('+1 month')->format('Y-m')])) ?>">›</a>
</div>
<div class="stat-row five">
  <article class="card stat"><span>Faturado (receitas)</span><strong><?= e(brl($receitas)) ?></strong><small>Soma das receitas não canceladas no mês</small></article>
  <article class="card stat"><span>Recebido</span><strong class="ok"><?= e(brl($recebido)) ?></strong><small>Receitas marcadas como pagas</small></article>
  <article class="card stat"><span>Pendente (receber)</span><strong class="warn"><?= e(brl($pendReceber)) ?></strong><small>Ainda não marcado como pago</small></article>
  <article class="card stat"><span>Lucro real (caixa)</span><strong><?= e(brl($lucroReal)) ?></strong><small>Recebido − despesas pagas no mês</small></article>
  <article class="card stat"><span>Lucro projetado</span><strong><?= e(brl($lucroProj)) ?></strong><small>Receitas − despesas do mês, não canceladas</small></article>
</div>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Despesas no mês</h2></header>
  <div class="stat-row">
    <article><span>Total (não canceladas)</span><strong><?= e(brl($despTotal)) ?></strong></article>
    <article><span>Pagas</span><strong><?= e(brl($despPaga)) ?></strong></article>
    <article><span>Pendentes (a pagar)</span><strong class="warn"><?= e(brl($despPend)) ?></strong></article>
  </div>
</section>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Áreas</h2></header>
  <div class="area-links">
    <a href="<?= e(href('lancamentos')) ?>">Lançamentos</a>
    <a href="<?= e(href('receber')) ?>">Contas a receber</a>
    <a href="<?= e(href('faturado')) ?>">Faturado</a>
    <a href="<?= e(href('pagar')) ?>">Contas a pagar</a>
    <a href="<?= e(href('relatorios')) ?>">Relatórios</a>
  </div>
</section>
<p class="help" style="margin-top:12px">Reservas novas ou atualizadas geram ou ajustam lançamentos automaticamente (receita pendente em contas a receber). Marcar como pago é manual em Lançamentos. Ao concluir a viagem com repasse, cria-se a despesa de repasse ao motorista (contas a pagar). Cancelar a reserva cancela a receita em aberto.</p>
