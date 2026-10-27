<?php
declare(strict_types=1);
defined('APP') || exit;
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$verId = (int) ($_GET['id'] ?? 0);
$edit = null;
$ver = null;
$rows = $db['motoristas'];
if ($q !== '') {
    $rows = array_values(array_filter($rows, static fn(array $m): bool => str_contains(mb_strtolower($m['nome'] . ' ' . ($m['cpf'] ?? '') . ' ' . $m['telefone']), mb_strtolower($q))));
}
usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['nome'], (string) $b['nome']));
foreach ($db['motoristas'] as $m) {
    if ((int) $m['id'] === $editId) {
        $edit = $m;
    }
    if ((int) $m['id'] === $verId) {
        $ver = $m;
    }
}
$pg = slice_page($rows);
view_head('<button class="btn btn-primary" type="button" data-open-dialog="dlg-mot">+ Novo motorista</button>', 'Cada motorista recebe usuário e senha para ver somente as próprias viagens.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="mot-cadastros">
  <label>Busca <input name="q" value="<?= e($q) ?>" placeholder="Buscar por nome ou CPF"></label>
  <button class="btn btn-sm" type="submit">Buscar</button>
</form>
<?php if ($ver): ?>
  <section class="card" style="margin-bottom:12px">
    <header class="card-h"><h2><?= e($ver['nome']) ?></h2><a href="<?= e(href('mot-cadastros')) ?>">Fechar</a></header>
    <p>Acesso: <strong><?= e($ver['usuario'] ?? '') ?></strong> · <?= e(portal_label($ver)) ?><?php if (($ver['senha'] ?? '') !== ''): ?> · senha <?= e($ver['senha']) ?><?php endif; ?></p>
    <p class="hint"><?= e($ver['telefone']) ?> · <?= e($ver['cidade'] ?? '') ?> <?= e($ver['uf'] ?? '') ?> · CPF <?= e($ver['cpf'] ?? '—') ?></p>
    <?php
      $suas = array_values(array_filter($db['transfers'], static fn(array $t): bool => viagem_do_motorista($t, (int) $ver['id']) && $t['status'] !== 'cancelada'));
    ?>
    <?php if ($suas === []): ?>
      <p class="hint">Nenhuma viagem atribuída.</p>
    <?php else: ?>
      <?php foreach ($suas as $t): ?>
        <p>#<?= (int) $t['id'] ?> · <?= e(br_date((string) $t['data'])) ?> <?= e($t['hora']) ?> · <?= e($t['origem']) ?> → <?= e($t['destino']) ?></p>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nome</th><th>CPF</th><th>Telefone</th><th>Cidade/UF</th><th>Frota</th><th>Portal</th><th>Data</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $mot): ?>
          <tr>
            <td><strong><?= e($mot['nome']) ?></strong></td>
            <td><?= e($mot['cpf'] ?? '—') ?></td>
            <td><?= e($mot['telefone']) ?></td>
            <td><?= e(trim(($mot['cidade'] ?? '') . (($mot['uf'] ?? '') !== '' ? ' - ' . $mot['uf'] : '')) ?: '—') ?></td>
            <td><?= status_badge((string) ($mot['status'] ?? 'ativo')) ?></td>
            <td><span class="tag <?= portal_label($mot) === 'Senha OK' ? 'tag-ok' : 'tag-wait' ?>"><?= e(portal_label($mot)) ?></span></td>
            <td><?= e(isset($mot['criado_em']) ? br_date((string) $mot['criado_em']) : '—') ?></td>
            <td class="actions">
              <a class="btn btn-sm" href="<?= e(href('mot-cadastros', ['id' => $mot['id']])) ?>">Abrir</a>
              <button class="btn btn-sm" type="button" data-copy="<?= e(base_url() . '/index.php?p=entrar') ?>">Copiar link</button>
              <form method="post" action="index.php"><?= csrf_fields('reset_senha_motorista') ?><input type="hidden" name="id" value="<?= (int) $mot['id'] ?>"><button class="btn btn-sm" type="submit">Senha</button></form>
              <a class="btn btn-sm" href="<?= e(href('mot-cadastros', ['edit' => $mot['id']])) ?>">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('mot-cadastros', $pg, array_filter(['q' => $q])) ?>
</section>
<dialog id="dlg-mot" class="dlg-cadastro" data-clear-query="edit" <?= $edit ? 'open' : '' ?>>
  <form method="post" action="index.php" id="form-mot">
    <header class="dialog-h dlg-top">
      <div>
        <h2><?= $edit ? 'Editar motorista' : 'Cadastrar motorista' ?></h2>
        <p>Obrigatório: nome completo, CPF e telefone. O login da agenda é gerado automaticamente.</p>
      </div>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </header>
    <div class="dialog-b">
      <?= csrf_fields($edit ? 'update_motorista' : 'add_motorista') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <div class="form-tabs" role="tablist">
        <button class="btn btn-sm is-on" type="button" data-form-tab="pessoal">Pessoal</button>
        <button class="btn btn-sm" type="button" data-form-tab="documentos">Documentos</button>
        <button class="btn btn-sm" type="button" data-form-tab="pagamento">Pagamento</button>
      </div>
      <div data-form-pane="pessoal">
        <h3 class="sec-title">Dados básicos</h3>
        <div class="form-grid">
          <label class="field full">Nome completo * <input name="nome" required maxlength="80" value="<?= e($edit['nome'] ?? '') ?>" placeholder="Fábio Luiz de Oliveira"></label>
          <label class="field">CPF * <input name="cpf" required maxlength="20" value="<?= e($edit['cpf'] ?? '') ?>" placeholder="000.000.000-00"></label>
          <label class="field">RG <input name="rg" maxlength="20" value="<?= e($edit['rg'] ?? '') ?>" placeholder="0.000.000"></label>
          <label class="field">Data de nascimento <input type="date" name="nascimento" value="<?= e($edit['nascimento'] ?? '') ?>"></label>
          <label class="field">Telefone * <input name="telefone" required maxlength="30" value="<?= e($edit['telefone'] ?? '') ?>" placeholder="(11) 99999-0000"></label>
          <label class="field full">E-mail <input type="email" name="email" maxlength="80" value="<?= e($edit['email'] ?? '') ?>" placeholder="motorista@email.com"></label>
        </div>
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
        </div>
      </div>
      <div data-form-pane="documentos" hidden>
        <h3 class="sec-title">CNH</h3>
        <div class="form-grid">
          <label class="field">Número da CNH <input name="cnh" maxlength="20" value="<?= e($edit['cnh'] ?? '') ?>" placeholder="00000000000"></label>
          <label class="field">Categoria
            <select name="categoria">
              <?php foreach (['A','B','C','D','E','AB','AC','AD','AE'] as $cat): ?>
                <option value="<?= $cat ?>"<?= ($edit['categoria'] ?? 'B') === $cat ? ' selected' : '' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">Validade <input type="date" name="cnh_validade" value="<?= e($edit['cnh_validade'] ?? '') ?>"></label>
          <label class="field">Situação na frota
            <select name="status">
              <option value="ativo"<?= ($edit['status'] ?? 'ativo') === 'ativo' ? ' selected' : '' ?>>Ativo</option>
              <option value="inativo"<?= ($edit['status'] ?? '') === 'inativo' ? ' selected' : '' ?>>Inativo</option>
            </select>
          </label>
          <label class="field full">Observações <textarea name="obs" maxlength="800" placeholder="Anotações internas"><?= e($edit['obs'] ?? '') ?></textarea></label>
        </div>
        <?php if ($edit && ($edit['usuario'] ?? '') !== ''): ?>
          <p class="hint">Login já gerado: <strong><?= e($edit['usuario']) ?></strong>. A senha só aparece de novo ao usar o botão Senha na lista.</p>
        <?php else: ?>
          <p class="hint">Ao salvar, o sistema cria usuário e senha. O motorista entra apenas na Agenda e só muda o status das reservas dele.</p>
        <?php endif; ?>
      </div>
      <div data-form-pane="pagamento" hidden>
        <h3 class="sec-title">Repasse</h3>
        <div class="form-grid">
          <label class="field full">Chave PIX <input name="pix" maxlength="80" value="<?= e($edit['pix'] ?? '') ?>" placeholder="CPF, e-mail ou telefone"></label>
          <label class="field">Banco <input name="banco" maxlength="40" value="<?= e($edit['banco'] ?? '') ?>" placeholder="Banco do Brasil"></label>
          <label class="field">Agência <input name="agencia" maxlength="12" value="<?= e($edit['agencia'] ?? '') ?>" placeholder="0001"></label>
          <label class="field">Conta <input name="conta" maxlength="20" value="<?= e($edit['conta'] ?? '') ?>" placeholder="12345-6"></label>
        </div>
      </div>
    </div>
    <footer class="dlg-foot">
      <button class="btn" type="button" data-close-dialog>Fechar</button>
      <button class="btn" type="button" data-form-next>Próximo</button>
      <button class="btn btn-primary" type="submit">Salvar motorista</button>
    </footer>
  </form>
</dialog>
