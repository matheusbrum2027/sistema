<?php
declare(strict_types=1);
defined('APP') || exit;

if (!eh_master()) {
    redirect(href('agenda'));
}

$master = mb_strtolower((string) ($db['settings']['usuario'] ?? 'admin'));
$lista = [];
$erroLista = '';
try {
    $lista = funcionarios_listar();
} catch (Throwable) {
    $erroLista = 'Não foi possível carregar os funcionários agora.';
}
?>
<section class="card">
  <h2>Administrador master</h2>
  <p>O usuário <strong><?= e($master) ?></strong> é fixo. Ele não entra nesta lista e não pode ser removido. Só ele cria funcionários e libera a parte financeira.</p>
</section>

<section class="card">
  <h2>Novo funcionário</h2>
  <p class="hint">Ele entra no painel administrativo com a operação liberada. Valores, faturamento e o menu Financeiro ficam ocultos até você marcar a permissão.</p>
  <?php if ($erroLista !== ''): ?><p class="flash flash-err"><?= e($erroLista) ?></p><?php endif; ?>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('funcionario_salvar') ?>
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="ativo" value="1">
    <label class="field">Nome
      <input name="nome" maxlength="80" required autocomplete="off">
    </label>
    <label class="field">Usuário
      <input name="usuario" maxlength="40" required autocomplete="off" autocapitalize="none" spellcheck="false">
    </label>
    <label class="field">Senha
      <input type="password" name="senha" maxlength="80" required autocomplete="new-password">
    </label>
    <label class="extra-row">
      <input type="checkbox" name="ver_financeiro" value="1">
      <span><strong>Ver parte financeira</strong><small>Desmarcado: não vê valores, faturamento nem o menu Financeiro.</small></span>
    </label>
    <button class="btn btn-primary" type="submit">Criar funcionário</button>
  </form>
</section>

<?php if ($lista !== []): ?>
<section class="card">
  <h2>Funcionários</h2>
  <div class="table-wrap">
    <table class="grid">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Usuário</th>
          <th>Acesso</th>
          <th>Financeiro</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lista as $func): ?>
          <?php $fid = (int) ($func['id'] ?? 0); ?>
          <tr>
            <td colspan="5">
              <form method="post" action="index.php" class="form-grid">
                <?= csrf_fields('funcionario_salvar') ?>
                <input type="hidden" name="id" value="<?= $fid ?>">
                <label class="field">Nome
                  <input name="nome" maxlength="80" required value="<?= e((string) ($func['nome'] ?? '')) ?>">
                </label>
                <label class="field">Usuário
                  <input name="usuario" maxlength="40" required autocapitalize="none" spellcheck="false" value="<?= e((string) ($func['usuario'] ?? '')) ?>">
                </label>
                <label class="field">Nova senha
                  <input type="password" name="senha" maxlength="80" autocomplete="new-password" placeholder="Em branco mantém a atual">
                </label>
                <label class="extra-row">
                  <input type="checkbox" name="ativo" value="1"<?= !empty($func['ativo']) ? ' checked' : '' ?>>
                  <span>Ativo</span>
                </label>
                <label class="extra-row">
                  <input type="checkbox" name="ver_financeiro" value="1"<?= !empty($func['ver_financeiro']) ? ' checked' : '' ?>>
                  <span>Pode ver valores e faturamento</span>
                </label>
                <button class="btn btn-primary" type="submit">Salvar</button>
              </form>
              <form method="post" action="index.php" data-confirm="Remover o funcionário <?= e((string) ($func['nome'] ?? '')) ?>? O administrador master não é afetado.">
                <?= csrf_fields('funcionario_apagar') ?>
                <input type="hidden" name="id" value="<?= $fid ?>">
                <button class="btn btn-danger" type="submit">Remover</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
