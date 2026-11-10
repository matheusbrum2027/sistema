<?php
declare(strict_types=1);
defined('APP') || exit;
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$rows = $db['clientes'];
if ($q !== '') {
    $rows = array_values(array_filter($rows, static fn(array $c): bool => str_contains(mb_strtolower($c['nome'] . ' ' . ($c['documento'] ?? '') . ' ' . ($c['cidade'] ?? '') . ' ' . $c['email']), mb_strtolower($q))));
}
foreach ($db['clientes'] as $c) {
    if ((int) $c['id'] === $editId) {
        $edit = $c;
    }
}
$pg = slice_page($rows);
view_head('<button class="btn btn-primary" type="button" data-open-dialog="dlg-cli">+ Cadastrar cliente</button>', 'A lista abre em tabela para caber muitos cadastros.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="clientes">
  <label>Busca <input name="q" value="<?= e($q) ?>" placeholder="Buscar por nome, CPF ou cidade"></label>
  <button class="btn btn-sm" type="submit">Buscar</button>
</form>
  <section class="card">
  <div class="table-wrap">
    <table class="table-lista">
        <thead><tr><th></th><th>Nome</th><th>Tipo</th><th>E-mail</th><th>Telefone</th><th>Perfil</th><th class="col-acoes">Ações</th></tr></thead>
        <tbody>
          <?php foreach ($pg['rows'] as $c): $pct = perfil_cliente($c); $letra = mb_strtoupper(mb_substr(trim((string) ($c['nome'] ?? '')), 0, 1)); if ($letra === '') { $letra = '?'; } ?>
            <tr>
              <td><span class="avatar" aria-hidden="true"><?= e($letra) ?></span></td>
              <td><?= e($c['nome']) ?></td>
              <td><?= e($c['tipo'] ?? 'PF') ?></td>
              <td><?= e($c['email'] ?: 'Não informado') ?></td>
              <td><?= e($c['telefone'] ?: 'Não informado') ?></td>
              <td class="col-perfil"><span class="perfil" title="<?= $pct ?>%"><i style="width:<?= $pct ?>%"></i></span> <?= $pct ?>%</td>
              <td class="actions">
                <a class="btn btn-sm" href="<?= e(href('clientes', ['edit' => $c['id']])) ?>">Editar</a>
                <form method="post" action="index.php" data-confirm="Excluir o cliente <?= e($c['nome']) ?>? Esta ação não pode ser desfeita."><?= csrf_fields('delete_cliente') ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager('clientes', $pg, array_filter(['q' => $q])) ?>
  </section>
<dialog id="dlg-cli" class="dlg-cadastro" data-clear-query="edit" <?= $edit ? 'open' : '' ?>>
  <form method="post" action="index.php">
    <header class="dialog-h dlg-top">
      <div>
        <h2><?= $edit ? 'Editar cliente' : 'Cadastrar cliente' ?></h2>
        <p>Nome é obrigatório. Os demais dados completam o cadastro usado nas reservas.</p>
      </div>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </header>
    <div class="dialog-b">
      <?= csrf_fields($edit ? 'update_cliente' : 'add_cliente') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <div class="form-tabs" role="tablist">
        <button class="btn btn-sm is-on" type="button" data-form-tab="pessoal">Pessoal</button>
        <button class="btn btn-sm" type="button" data-form-tab="endereco">Endereço</button>
      </div>
      <div data-form-pane="pessoal">
        <h3 class="sec-title">Dados básicos</h3>
        <div class="form-grid">
          <label class="field full">Nome completo * <input name="nome" required maxlength="80" value="<?= e($edit['nome'] ?? '') ?>" placeholder="Paulo Henrique Correa de Macedo"></label>
          <label class="field">Tipo
            <select name="tipo">
              <option value="PF"<?= ($edit['tipo'] ?? 'PF') === 'PF' ? ' selected' : '' ?>>Pessoa física</option>
              <option value="PJ"<?= ($edit['tipo'] ?? '') === 'PJ' ? ' selected' : '' ?>>Pessoa jurídica</option>
            </select>
          </label>
          <label class="field">CPF ou CNPJ <input name="documento" maxlength="20" value="<?= e($edit['documento'] ?? '') ?>" placeholder="000.000.000-00"></label>
          <label class="field">RG <input name="rg" maxlength="20" value="<?= e($edit['rg'] ?? '') ?>" placeholder="0.000.000"></label>
          <label class="field">Telefone <input name="telefone" maxlength="30" value="<?= e($edit['telefone'] ?? '') ?>" placeholder="(48) 98822-2534"></label>
          <label class="field">E-mail <input type="email" name="email" maxlength="80" value="<?= e($edit['email'] ?? '') ?>" placeholder="cliente@email.com"></label>
          <label class="field">Nascimento <input type="date" name="nascimento" value="<?= e($edit['nascimento'] ?? '') ?>"></label>
          <label class="field full">Empresa <input name="empresa" maxlength="80" value="<?= e($edit['empresa'] ?? '') ?>" placeholder="Razão social, se houver"></label>
          <label class="field full">Observações <textarea name="obs" maxlength="800" placeholder="Anotações do atendimento"><?= e($edit['obs'] ?? '') ?></textarea></label>
        </div>
      </div>
      <div data-form-pane="endereco" hidden>
        <h3 class="sec-title">Endereço</h3>
        <div class="form-grid">
          <label class="field">Estado (UF)
            <select name="uf">
              <option value="">Selecione o estado</option>
              <?php foreach (['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'] as $uf): ?>
                <option value="<?= $uf ?>"<?= strtoupper((string) ($edit['uf'] ?? '')) === $uf ? ' selected' : '' ?>><?= $uf ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">Cidade <input name="cidade" maxlength="60" value="<?= e($edit['cidade'] ?? '') ?>" placeholder="Florianópolis"></label>
          <label class="field">CEP <input name="cep" maxlength="12" value="<?= e($edit['cep'] ?? '') ?>" placeholder="00000-000"></label>
          <label class="field">Logradouro <input name="logradouro" maxlength="120" value="<?= e($edit['logradouro'] ?? '') ?>" placeholder="Rua, avenida"></label>
          <label class="field">Número <input name="numero" maxlength="12" value="<?= e($edit['numero'] ?? '') ?>" placeholder="100"></label>
          <label class="field">Complemento <input name="complemento" maxlength="40" value="<?= e($edit['complemento'] ?? '') ?>" placeholder="Apto, sala"></label>
          <label class="field full">Bairro <input name="bairro" maxlength="60" value="<?= e($edit['bairro'] ?? '') ?>" placeholder="Centro"></label>
          <label class="field full">Endereço completo <input name="endereco" maxlength="160" value="<?= e($edit['endereco'] ?? '') ?>" placeholder="Usado na reserva quando o logradouro não for preenchido"></label>
        </div>
      </div>
    </div>
    <footer class="dlg-foot">
      <button class="btn" type="button" data-close-dialog>Fechar</button>
      <button class="btn" type="button" data-form-next>Próximo</button>
      <button class="btn btn-primary" type="submit">Salvar cliente</button>
    </footer>
  </form>
</dialog>
