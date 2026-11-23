<?php
declare(strict_types=1);
defined('APP') || exit;
$rel = relatorio_consulta($db);
$tot = $rel['totais'];
$keep = array_filter([
    'de' => $rel['de'],
    'ate' => $rel['ate'],
    'mot' => $rel['mot'] ?: null,
    'estado' => $rel['estado'],
    'q' => $rel['q'],
]);
$pg = slice_page($rel['viagens'], 20);
$arquivo = relatorio_arquivo_pdf($rel);
$pdfHref = href('relatorios', ['baixar' => 'pdf'] + $keep);
$motoristas = $db['motoristas'];
usort($motoristas, static fn(array $a, array $b): int => strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? '')));
view_head('<a class="btn btn-primary" href="' . e($pdfHref) . '" download="' . e($arquivo) . '" data-baixar-pdf="' . e($arquivo) . '">Baixar PDF</a>', 'Filtre o período e baixe o relatório em paisagem.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="relatorios">
  <label>Data (de) <input type="date" name="de" value="<?= e($rel['de']) ?>"></label>
  <label>Data (até) <input type="date" name="ate" value="<?= e($rel['ate']) ?>"></label>
  <label>Motorista
    <select name="mot">
      <option value="0">Todos</option>
      <?php foreach ($motoristas as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $rel['mot'] === (int) $m['id'] ? ' selected' : '' ?>><?= e((string) $m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Situação
    <select name="estado">
      <?php foreach ([
          '' => 'Ativas',
          'todas' => 'Todas',
          'pendente' => 'Pendente',
          'reserva' => 'Reserva',
          'andamento' => 'Em andamento',
          'concluida' => 'Concluída',
          'cancelada' => 'Cancelada',
      ] as $k => $lab): ?>
        <option value="<?= e($k) ?>"<?= $rel['estado'] === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Cliente / nº <input name="q" value="<?= e($rel['q']) ?>" placeholder="MZ ou 2390"></label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<div class="stat-row">
  <article class="card stat"><span>Reservas</span><strong><?= (int) $tot['viagens'] ?></strong></article>
  <article class="card stat"><span>Receita</span><strong><?= e(brl((float) $tot['receita'])) ?></strong></article>
  <article class="card stat"><span>Recebido</span><strong><?= e(brl((float) $tot['recebido'])) ?></strong></article>
  <article class="card stat"><span>Na hora</span><strong><?= e(brl((float) $tot['vista'])) ?></strong></article>
  <article class="card stat"><span>Boleto</span><strong><?= e(brl((float) $tot['boleto'])) ?></strong></article>
  <article class="card stat"><span>Pro-labore</span><strong><?= e(brl((float) $tot['repasse'])) ?></strong></article>
  <article class="card stat"><span>Despesas</span><strong><?= e(brl((float) $tot['despesa'])) ?></strong></article>
</div>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Reservas de <?= e($rel['rotulo']) ?></h2><span class="hint"><?= e($rel['motorista']) ?> · <?= e($rel['situacao']) ?></span></header>
  <?php if ($rel['viagens'] === []): ?>
    <?= empty_box('bars', 'Nenhuma reserva neste filtro', 'Alargue as datas ou mude a situação.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table-fixa">
        <thead><tr><th>Nº</th><th>Data</th><th>Cliente</th><th>Trajeto</th><th>Motorista</th><th>Valor</th><th>Conta</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($pg['rows'] as $t): ?>
            <tr>
              <td>#<?= (int) $t['id'] ?></td>
              <td><?= e(br_date((string) $t['data'])) ?> <span class="hint"><?= e(substr((string) ($t['hora'] ?? ''), 0, 5)) ?></span></td>
              <td><?= e((string) $t['cliente']) ?></td>
              <td><?= e(short_place((string) $t['origem'])) ?> → <?= e(short_place((string) $t['destino'])) ?></td>
              <td><?= e(nome_motorista($db, $t['motorista_id'] ?? 0)) ?></td>
              <td><?= e(brl((float) $t['valor'])) ?></td>
              <td><?= !empty($t['pago']) ? 'Recebido' : (!empty($t['faturado']) ? 'Boleto' : 'Na hora') ?></td>
              <td><?= status_badge((string) $t['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager('relatorios', $pg, $keep) ?>
  <?php endif; ?>
</section>
<p class="help" style="margin-top:12px"><?= (int) $tot['lancamentos'] ?> lançamentos neste filtro entram no PDF, junto com o critério da consulta, a soma das reservas e o pro-labore. O arquivo sai sempre em paisagem.</p>
