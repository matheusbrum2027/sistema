<?php
declare(strict_types=1);
defined('APP') || exit;
$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$rows = array_values(array_filter($db['lancamentos'], static function (array $l) use ($de, $ate, $q): bool {
    if (($l['status'] ?? '') === 'cancelado') {
        return false;
    }
    if ($de !== '' && $l['data'] < $de) {
        return false;
    }
    if ($ate !== '' && $l['data'] > $ate) {
        return false;
    }
    if ($q !== '' && !str_contains(mb_strtolower($l['descricao'] . ' ' . ($l['cliente'] ?? '') . ' ' . $l['categoria']), mb_strtolower($q))) {
        return false;
    }
    return true;
}));
usort($rows, static fn(array $a, array $b): int => [$b['data'], $b['id']] <=> [$a['data'], $a['id']]);
foreach ($db['lancamentos'] as $l) {
    if ((int) $l['id'] === $editId) {
        $edit = $l;
    }
}
$pg = slice_page($rows);
$keep = array_filter(['de' => $de, 'ate' => $ate, 'q' => $q]);
view_head('<a class="btn btn-sm" href="' . e(href('fin-dashboard')) . '">Dashboard</a> <button class="btn btn-primary" type="button" data-open-dialog="dlg-lanc">+ Novo manual</button>', 'Receitas e despesas. Reservas entram automaticamente.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="lancamentos">
  <label>Data (de) <input type="date" name="de" value="<?= e($de) ?>" placeholder="01/10/2026"></label>
  <label>Data (até) <input type="date" name="ate" value="<?= e($ate) ?>" placeholder="31/10/2026"></label>
  <label>Cliente <input name="q" value="<?= e($q) ?>" placeholder="MZ ou Reserva transfer"></label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Data</th><th>Linha</th><th>Tipo</th><th>Origem</th><th>Categoria</th><th>Valor</th><th>Estado</th><th>Meio</th><th>Baixa</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $row): ?>
          <tr>
            <td><?= e(br_date((string) $row['data'])) ?></td>
            <td><span class="tag"><?= e($row['linha'] ?? 'Principal') ?></span></td>
            <td><span class="tag <?= $row['tipo'] === 'entrada' ? 'tag-rec' : 'tag-pag' ?>"><?= $row['tipo'] === 'entrada' ? 'Receita' : 'Despesa' ?></span></td>
            <td><?= e($row['origem'] ?? 'manual') ?></td>
            <td><?= e($row['categoria']) ?></td>
            <td class="<?= $row['tipo'] === 'saida' ? 'money-neg' : '' ?>"><?= e(brl((float) $row['valor'])) ?></td>
            <td><?= status_badge((string) ($row['status'] === 'pago' ? 'pago' : 'pendente')) ?></td>
            <td><?= e($row['meio'] ?? '') ?: '—' ?></td>
            <td><?= e(($row['baixa'] ?? '') !== '' ? br_date((string) $row['baixa']) : '—') ?></td>
            <td class="actions">
              <?php if (($row['status'] ?? '') !== 'pago'): ?>
                <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Marcar o lançamento <?= e($row['descricao']) ?> como pago?" data-confirm-2="Confirme novamente: este lançamento ficará pago."><?= csrf_fields('baixar_lancamento') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-primary" type="submit">Pago</button></form>
              <?php endif; ?>
              <a class="btn btn-sm" href="<?= e(href('lancamentos', ['edit' => $row['id']] + $keep)) ?>">Editar</a>
              <form method="post" action="index.php" data-confirm="Excluir o lançamento <?= e($row['descricao']) ?>? Esta ação não pode ser desfeita."><?= csrf_fields('delete_lancamento') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('lancamentos', $pg, $keep) ?>
</section>
<dialog id="dlg-lanc" <?= $edit ? 'open' : '' ?>>
  <form method="post" action="index.php">
    <header class="dialog-h"><h2><?= $edit ? 'Editar lançamento' : 'Novo lançamento manual' ?></h2><button class="btn btn-sm" type="button" data-close-dialog>Fechar</button></header>
    <div class="dialog-b form-grid">
      <?= csrf_fields($edit ? 'edit_lancamento' : 'add_lancamento') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label class="field">Tipo
        <select name="tipo">
          <option value="entrada"<?= ($edit['tipo'] ?? '') === 'entrada' ? ' selected' : '' ?>>Receita</option>
          <option value="saida"<?= ($edit['tipo'] ?? '') === 'saida' ? ' selected' : '' ?>>Despesa</option>
        </select>
      </label>
      <label class="field">Estado
        <select name="status" data-status-inicial="<?= e((string) (($edit['status'] ?? '') === 'pago' ? 'pago' : 'pendente')) ?>">
          <option value="pendente"<?= ($edit['status'] ?? '') !== 'pago' ? ' selected' : '' ?>>Pendente</option>
          <option value="pago"<?= ($edit['status'] ?? '') === 'pago' ? ' selected' : '' ?>>Pago</option>
        </select>
      </label>
      <label class="field full">Descrição <input name="descricao" required maxlength="120" value="<?= e($edit['descricao'] ?? '') ?>" placeholder="Abastecimento posto Shell, Florianópolis"></label>
      <label class="field">Categoria <input name="categoria" maxlength="40" value="<?= e($edit['categoria'] ?? '') ?>" placeholder="Combustível"></label>
      <label class="field">Cliente <input name="cliente" maxlength="80" value="<?= e($edit['cliente'] ?? '') ?>" placeholder="MZ Turismo"></label>
      <label class="field">Valor <input name="valor" required inputmode="decimal" value="<?= e(isset($edit['valor']) ? number_format((float) $edit['valor'], 2, ',', '') : '') ?>" placeholder="180,00"></label>
      <label class="field">Data <input type="date" name="data" required value="<?= e($edit['data'] ?? today()->format('Y-m-d')) ?>" placeholder="27/10/2026"></label>
      <label class="field">Vencimento <input type="date" name="vencimento" value="<?= e($edit['vencimento'] ?? today()->format('Y-m-d')) ?>" placeholder="30/10/2026"></label>
      <label class="field">Meio <input name="meio" maxlength="30" value="<?= e($edit['meio'] ?? '') ?>" placeholder="Pix"></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar</button></div>
    </div>
  </form>
</dialog>
