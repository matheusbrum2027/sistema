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
$deMes = $cursor->format('Y-m-d');
$ateMes = fim_do_mes($deMes);
$motFiltro = (int) ($_GET['mot'] ?? 0);
$prolabore = resumo_prolabore($db, $deMes, $ateMes);
$receitas = 0.0;
$recebido = 0.0;
$aVista = 0.0;
$boleto = 0.0;
$proAberto = 0.0;
$proPago = 0.0;
foreach ($prolabore as $linha) {
    if ($motFiltro > 0 && (int) $linha['id'] !== $motFiltro) {
        continue;
    }
    $receitas += $linha['valor'];
    $recebido += $linha['recebido'];
    $aVista += $linha['vista'];
    $boleto += $linha['boleto'];
    $proAberto += $linha['prolabore_aberto'];
    $proPago += $linha['prolabore_pago'];
}
$despTotal = 0.0;
$despPaga = 0.0;
$despPend = 0.0;
foreach ($db['lancamentos'] as $l) {
    if (($l['tipo'] ?? '') !== 'saida' || ($l['status'] ?? '') === 'cancelado' || !$noMes((string) $l['data'])) {
        continue;
    }
    if ($motFiltro > 0 && (int) ($l['motorista_id'] ?? 0) !== $motFiltro) {
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
view_head();
?>
<div class="month-nav">
  <a class="btn btn-sm" href="<?= e(href('fin-dashboard', ['mes' => $cursor->modify('-1 month')->format('Y-m')])) ?>">‹</a>
  <strong><?= e(ucfirst(mes_longo($cursor))) ?> de <?= e($cursor->format('Y')) ?></strong>
  <a class="btn btn-sm" href="<?= e(href('fin-dashboard', ['mes' => $cursor->modify('+1 month')->format('Y-m')])) ?>">›</a>
</div>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="fin-dashboard">
  <input type="hidden" name="mes" value="<?= e($mes) ?>">
  <label>Motorista
    <select name="mot">
      <option value="0">Todos</option>
      <?php foreach ($db['motoristas'] as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $motFiltro === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<div class="stat-row five">
  <article class="card stat"><span>Receita do mês</span><strong><?= e(brl($receitas)) ?></strong><small>Reservas não canceladas</small></article>
  <article class="card stat"><span>Já recebido</span><strong class="ok"><?= e(brl($recebido)) ?></strong><small>Cliente já pagou</small></article>
  <article class="card stat"><span>A receber na hora</span><strong class="warn"><?= e(brl($aVista)) ?></strong><small>Sem a marca de faturado</small></article>
  <article class="card stat"><span>Boleto no fim do mês</span><strong class="warn"><?= e(brl($boleto)) ?></strong><small>Faturado e ainda em aberto</small></article>
  <article class="card stat"><span>Pro-labore em aberto</span><strong><?= e(brl($proAberto)) ?></strong><small>Repasse de viagens concluídas</small></article>
</div>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Despesas no mês</h2></header>
  <div class="stat-row">
    <article><span>Total (não canceladas)</span><strong><?= e(brl($despTotal)) ?></strong></article>
    <article><span>Pagas</span><strong><?= e(brl($despPaga)) ?></strong></article>
    <article><span>Pendentes (a pagar)</span><strong class="warn"><?= e(brl($despPend)) ?></strong></article>
    <article><span>Resultado do caixa</span><strong><?= e(brl($lucroReal)) ?></strong></article>
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
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Pro-labore por motorista</h2></header>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Motorista</th><th>Viagens</th><th>Concluídas</th><th>Receita</th><th>Na hora</th><th>Boleto</th><th>Pro-labore</th><th>Pago</th><th>Em aberto</th></tr></thead>
      <tbody>
        <?php foreach ($prolabore as $linha): ?>
          <?php if ($motFiltro > 0 && (int) $linha['id'] !== $motFiltro) continue; ?>
          <tr>
            <td><?= e($linha['nome']) ?></td>
            <td><?= (int) $linha['viagens'] ?></td>
            <td><?= (int) $linha['concluidas'] ?></td>
            <td><?= e(brl($linha['valor'])) ?></td>
            <td><?= e(brl($linha['vista'])) ?></td>
            <td><?= e(brl($linha['boleto'])) ?></td>
            <td><?= e(brl($linha['prolabore'])) ?></td>
            <td><?= e(brl($linha['prolabore_pago'])) ?></td>
            <td><?= e(brl($linha['prolabore_aberto'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<p class="help" style="margin-top:12px">Cada reserva entra em Contas a receber. Se estiver marcada como faturado, o vencimento vai para o último dia do mês: a empresa paga o pacote por boleto. Concluir a viagem, com repasse maior que zero, gera o pro-labore daquele motorista em Contas a pagar. Juros, mora e sangria não são calculados sozinhos; uma taxa entra como lançamento manual.</p>
