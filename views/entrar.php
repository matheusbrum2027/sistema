<?php
declare(strict_types=1);
defined('APP') || exit;
$recuperar = isset($_GET['recuperar']);
$exigeCodigo = verificacao_2fa_ativa($db);
if ($exigeCodigo) {
    captcha_garantir();
    $cap = $_SESSION['captcha'];
    $ver = (int) ($cap['ver'] ?? 1);
}
$acao = $recuperar ? 'recuperar' : 'login';
$marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
$acessoNome = '';
$acessoUsuario = '';
$acessoToken = strtolower((string) ($_GET['acesso'] ?? ''));
if (preg_match('/^[a-f0-9]{32}$/', $acessoToken)) {
    foreach ($db['motoristas'] as $mot) {
        $gravado = strtolower((string) ($mot['acesso'] ?? ''));
        if ($gravado !== '' && hash_equals($gravado, $acessoToken)) {
            $acessoNome = (string) ($mot['nome'] ?? '');
            $emailMot = mb_strtolower(trim((string) ($mot['email'] ?? '')));
            $acessoUsuario = $emailMot !== '' ? $emailMot : (string) ($mot['usuario'] ?? '');
            break;
        }
    }
}
$leadLogin = 'Admin: usuário e senha da operação. Motorista: e-mail cadastrado e a senha-token recebida do administrador.';
if ($acessoNome !== '') {
    $leadLogin = 'Portal de ' . $acessoNome . '. Entre com o e-mail cadastrado e a senha-token. Depois do login, aparecem só as reservas atribuídas a você.';
} elseif ($acessoToken !== '') {
    $leadLogin = 'Este link de motorista não foi encontrado. Peça um novo link ao administrador.';
}
?>
<section class="auth-screen">
  <aside class="auth-hero">
    <img src="assets/brand/logo-fln.jpg" width="640" height="640" alt="<?= e($marca) ?> — Transfer Executivo e Turismo, Florianópolis">
    <p>Reservas, motoristas, veículos e financeiro no mesmo painel.</p>
  </aside>
  <div class="auth-card">
    <div class="auth-card-top">
      <div>
        <h1><?= $recuperar ? 'Recuperar acesso' : 'Acesse sua operação' ?></h1>
        <p class="lead"><?= $recuperar ? 'Informe o e-mail da conta. A resposta não indica se o cadastro existe.' : e($leadLogin) ?></p>
      </div>
      <button class="btn theme-btn" type="button" data-theme-toggle><span data-theme-label>Modo escuro</span></button>
    </div>
    <?= render_flash() ?>
    <form method="post" action="index.php" class="form-grid auth-form">
      <?= csrf_fields($acao) ?>
      <label class="field full" for="usuario"><?= $recuperar ? 'E-mail' : ($acessoNome !== '' ? 'E-mail do motorista' : 'Usuário ou e-mail') ?>
        <input id="usuario" name="usuario" required maxlength="80" autocomplete="<?= $recuperar || $acessoNome !== '' ? 'email' : 'username' ?>" inputmode="<?= $recuperar || $acessoNome !== '' ? 'email' : 'text' ?>" placeholder="<?= $recuperar || $acessoNome !== '' ? 'nome@email.com' : 'admin ou e-mail do motorista' ?>" value="<?= e($recuperar ? '' : $acessoUsuario) ?>">
      </label>
      <?php if (!$recuperar): ?>
      <label class="field full" for="senha"><?= $acessoNome !== '' ? 'Senha-token' : 'Senha' ?>
        <span class="senha-linha">
          <input id="senha" type="password" name="senha" required maxlength="80" autocomplete="current-password" placeholder="<?= $acessoNome !== '' ? 'token recebido do administrador' : 'senha da operação' ?>">
          <button class="btn" type="button" data-toggle-senha aria-pressed="false" aria-controls="senha">Mostrar</button>
        </span>
      </label>
      <?php endif; ?>
      <?php if ($exigeCodigo): ?>
      <div class="captcha-box full">
        <div class="captcha-head">
          <span id="captcha-rotulo">Código de verificação</span>
          <a class="btn" href="index.php?captcha_novo=1<?= $recuperar ? '&amp;recuperar=1' : '' ?>">Atualizar código</a>
        </div>
        <img src="index.php?captcha=img&amp;v=<?= $ver ?>" width="228" height="76" alt="Imagem do código de verificação. Use o áudio se não conseguir ler.">
        <p class="captcha-ajuda">Cinco caracteres, com letras, números e sinais @ # % &amp;. Espaços são ignorados e maiúsculas não importam.</p>
        <a href="index.php?captcha=audio&amp;v=<?= $ver ?>">Ouvir alternativa em áudio</a>
        <label class="field" for="captcha">Digite o código
          <input id="captcha" name="captcha" required maxlength="8" autocomplete="off" spellcheck="false" aria-describedby="captcha-rotulo">
        </label>
      </div>
      <?php endif; ?>
      <div class="form-actions full">
        <button class="btn btn-primary" type="submit"><?= $recuperar ? 'Enviar instruções' : 'Entrar' ?></button>
        <?php if ($recuperar): ?>
          <a class="btn" href="index.php?p=entrar">Voltar ao login</a>
        <?php else: ?>
          <a class="btn" href="index.php?p=entrar&amp;recuperar=1">Esqueci a senha</a>
        <?php endif; ?>
      </div>
    </form>
    <p class="auth-seguro">A sessão fica no servidor. A chave privilegiada do banco não é enviada ao navegador. Depois de várias tentativas, o acesso fica pausado por alguns minutos.</p>
  </div>
</section>
