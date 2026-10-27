<?php
declare(strict_types=1);
defined('APP') || exit;
$vista = (string) ($_GET['vista'] ?? 'tabela');
if (!in_array($vista, ['tabela', 'cards'], true)) {
    $vista = 'tabela';
}
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$rows = $db['veiculos'];
if ($q !== '') {
    $rows = array_values(array_filter($rows, static fn(array $v): bool => str_contains(mb_strtolower($v['placa'] . ' ' . ($v['marca'] ?? '') . ' ' . $v['modelo'] . ' ' . ($v['tipo'] ?? '') . ' ' . $v['apelido']), mb_strtolower($q))));
}
foreach ($db['veiculos'] as $v) {
    if ((int) $v['id'] === $editId) {
        $edit = $v;
    }
}
$ativos = count(array_filter($db['veiculos'], static fn(array $v): bool => ($v['status'] ?? '') === 'ativo'));
$preco = count(array_filter($db['veiculos'], static fn(array $v): bool => !empty($v['precificacao'])));
view_head('<button class="btn btn-primary" type="button" data-open-dialog="dlg-vei">+ Novo veículo</button>', 'Cadastro da frota para o cálculo das corridas.');
?>
<div class="stat-row">
  <article class="card stat"><span>Total de veículos</span><strong><?= count($db['veiculos']) ?></strong></article>
  <article class="card stat"><span>Ativos</span><strong class="ok"><?= $ativos ?></strong></article>
  <article class="card stat"><span>Com precificação</span><strong class="warn"><?= $preco ?></strong></article>
  <article class="card stat"><span>Vista</span><strong><a href="<?= e(href('veiculos', ['vista' => 'cards', 'q' => $q])) ?>">Cards</a> <a href="<?= e(href('veiculos', ['vista' => 'tabela', 'q' => $q])) ?>">Tabela</a></strong></article>
</div>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="veiculos">
  <input type="hidden" name="vista" value="<?= e($vista) ?>">
  <label>Busca <input name="q" value="<?= e($q) ?>" placeholder="Buscar por placa, marca, modelo ou tipo"></label>
  <button class="btn btn-sm" type="submit">Buscar</button>
</form>
<section class="card">
  <?php if ($rows === []): ?>
    <?= empty_box('car', 'Nenhum veículo cadastrado', 'Use o botão Novo veículo.') ?>
  <?php elseif ($vista === 'cards'): ?>
    <div class="people-grid">
      <?php foreach ($rows as $v): ?>
        <article class="card person">
          <strong><?= e($v['apelido']) ?></strong>
          <span><?= e($v['placa']) ?> · <?= e(($v['marca'] ?? '') . ' ' . $v['modelo']) ?></span>
          <span class="hint"><?= e($v['tipo'] ?? 'SED') ?> · <?= (int) $v['lugares'] ?> lugares<?= !empty($v['precificacao']) ? ' · base ' . e(brl((float) ($v['valor_base'] ?? 0))) : '' ?></span>
          <?= status_badge((string) $v['status']) ?>
          <a class="btn btn-sm" href="<?= e(href('veiculos', ['edit' => $v['id'], 'vista' => 'cards'])) ?>">Editar</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Apelido</th><th>Placa</th><th>Marca / modelo</th><th>Tipo</th><th>Lugares</th><th>Precificação</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $v): ?>
            <tr>
              <td><?= e($v['apelido']) ?></td>
              <td><?= e($v['placa']) ?></td>
              <td><?= e(trim(($v['marca'] ?? '') . ' ' . $v['modelo'])) ?></td>
              <td><?= e($v['tipo'] ?? 'SED') ?></td>
              <td><?= (int) $v['lugares'] ?></td>
              <td><?= !empty($v['precificacao']) ? e(brl((float) ($v['valor_base'] ?? 0))) : '—' ?></td>
              <td><?= status_badge((string) $v['status']) ?></td>
              <td class="actions">
                <a class="btn btn-sm" href="<?= e(href('veiculos', ['edit' => $v['id']])) ?>">Editar</a>
                <form method="post" action="index.php" data-confirm="Excluir o veículo <?= e($v['nome'] ?? $v['placa'] ?? '') ?>? Esta ação não pode ser desfeita."><?= csrf_fields('delete_veiculo') ?><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<dialog id="dlg-vei" <?= $edit ? 'open' : '' ?>>
  <form method="post" action="index.php">
    <header class="dialog-h"><h2><?= $edit ? 'Editar veículo' : 'Novo veículo' ?></h2><button class="btn btn-sm" type="button" data-close-dialog>Fechar</button></header>
    <div class="dialog-b form-grid">
      <?= csrf_fields($edit ? 'update_veiculo' : 'add_veiculo') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label class="field">Apelido <input name="apelido" required maxlength="40" value="<?= e($edit['apelido'] ?? '') ?>" placeholder="Spin 01"></label>
      <label class="field">Placa <input name="placa" required minlength="7" maxlength="8" value="<?= e($edit['placa'] ?? '') ?>" placeholder="ABC1D23"></label>
      <label class="field">Marca <input name="marca" maxlength="40" value="<?= e($edit['marca'] ?? '') ?>" placeholder="Chevrolet"></label>
      <label class="field">Modelo <input name="modelo" required maxlength="40" value="<?= e($edit['modelo'] ?? '') ?>" placeholder="Spin LT"></label>
      <label class="field">Tipo
        <select name="tipo"><?php foreach (['SED', 'VAN', 'EXEC', 'BUS'] as $cat): ?><option<?= ($edit['tipo'] ?? 'SED') === $cat ? ' selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select>
      </label>
      <label class="field">Ano <input type="number" name="ano" required min="1990" max="<?= (int) today()->format('Y') + 1 ?>" value="<?= e((string) ($edit['ano'] ?? 2022)) ?>" placeholder="2022"></label>
      <label class="field">Cor <input name="cor" maxlength="20" value="<?= e($edit['cor'] ?? '') ?>" placeholder="Branco"></label>
      <label class="field">Lugares <input type="number" name="lugares" required min="1" max="60" value="<?= (int) ($edit['lugares'] ?? 7) ?>" placeholder="7"></label>
      <label class="field">Status
        <select name="status">
          <?php foreach (['ativo' => 'Ativo', 'manutencao' => 'Manutenção', 'inativo' => 'Inativo'] as $k => $lab): ?>
            <option value="<?= e($k) ?>"<?= ($edit['status'] ?? 'ativo') === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Valor base da corrida <input name="valor_base" inputmode="decimal" value="<?= e(isset($edit['valor_base']) ? number_format((float) $edit['valor_base'], 2, ',', '') : '') ?>" placeholder="80,00"></label>
      <label class="field full checkline"><input type="checkbox" name="precificacao" value="1"<?= !empty($edit['precificacao']) ? ' checked' : '' ?>> Usar precificação neste veículo</label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar veículo</button></div>
    </div>
  </form>
</dialog>
