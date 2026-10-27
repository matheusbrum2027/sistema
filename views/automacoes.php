<?php
declare(strict_types=1);
defined('APP') || exit;
$hooks = array_values(array_filter($db['automacoes'], static fn(array $a): bool => !empty($a['secret'])));
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($hooks as $h) {
    if ((int) $h['id'] === $editId) {
        $edit = $h;
    }
}
view_head('<button class="btn btn-primary" type="button" data-open-dialog="dlg-auto">+ Nova automação</button>', 'Webhooks que criam reservas e cadastros de motorista.');
?>
<section class="card">
  <header class="card-h"><h2>Automações da plataforma</h2></header>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nome</th><th>Tipo</th><th>Webhook URL</th><th>Status</th><th>Ativar</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($hooks as $hook): ?>
          <tr>
            <td><?= e($hook['nome']) ?></td>
            <td><span class="tag"><?= e($hook['tipo_hook']) ?></span></td>
            <td><code><?= e(hook_url((string) $hook['secret'])) ?></code> <button class="btn btn-sm" type="button" data-copy="<?= e(hook_url((string) $hook['secret'])) ?>">Copiar</button></td>
            <td><?= !empty($hook['ativo']) ? '<span class="tag tag-ok">Ativo</span>' : '<span class="tag">Inativo</span>' ?></td>
            <td>
              <form method="post" action="index.php"><?= csrf_fields('toggle_automacao') ?><input type="hidden" name="id" value="<?= e((string) $hook['id']) ?>"><button class="switch<?= !empty($hook['ativo']) ? ' on' : '' ?>" type="submit" aria-label="Ativar"><i></i></button></form>
            </td>
            <td class="actions">
              <a class="btn btn-sm" href="<?= e(href('automacoes', ['edit' => $hook['id']])) ?>">Configurar</a>
              <form method="post" action="index.php" data-confirm="Excluir a automação <?= e($hook['nome']) ?>? O webhook deixa de receber dados."><?= csrf_fields('delete_automacao') ?><input type="hidden" name="id" value="<?= (int) $hook['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Webhooks de campanha</h2></header>
  <?= empty_box('zap', 'Nenhum webhook de campanha ativo', 'Campanhas criam o próprio webhook e ele sai da lista quando a campanha encerra.') ?>
</section>
<dialog id="dlg-auto" <?= $edit ? 'open' : '' ?>>
  <form method="post" action="index.php">
    <header class="dialog-h"><h2><?= $edit ? 'Configurar automação' : 'Nova automação' ?></h2><button class="btn btn-sm" type="button" data-close-dialog>Fechar</button></header>
    <div class="dialog-b form-grid">
      <?= csrf_fields($edit ? 'update_automacao' : 'add_automacao') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label class="field">Nome <input name="nome" required maxlength="60" value="<?= e($edit['nome'] ?? '') ?>" placeholder="Site da frota"></label>
      <label class="field">Tipo
        <select name="tipo_hook">
          <?php foreach (['Transfer Executivo', 'Motorista'] as $tipo): ?>
            <option<?= ($edit['tipo_hook'] ?? '') === $tipo ? ' selected' : '' ?>><?= e($tipo) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar</button></div>
    </div>
  </form>
</dialog>
