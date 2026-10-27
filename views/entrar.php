<?php
declare(strict_types=1);
defined('APP') || exit;
captcha_garantir();
$cap = $_SESSION['captcha'];
$ver = (int) ($cap['ver'] ?? 1);
$recuperar = isset($_GET['recuperar']);
$acao = $recuperar ? 'recuperar' : 'login';
$marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
?>
<section class="auth-screen">
  <div class="auth-brand">
    <p class="auth-kicker">Operação de transfer</p>
    <strong><?= e($marca) ?></strong>
    <span>Gestão de Frota</span>
    <p>Reservas, motoristas, veículos e financeiro no mesmo painel.</p>
  </div>
  <div class="auth-card">
    <div class="auth-card-top">
      <div>
        <h1><?= $recuperar ? 'Recuperar acesso' : 'Acesse sua operação' ?></h1>
        <p class="lead"><?= $recuperar ? 'Informe o e-mail da conta. A resposta não indica se o cadastro existe.' : 'Entre com o usuário da operação ou com o e-mail cadastrado.' ?></p>
      </div>
      <button class="btn theme-btn" type="button" data-theme-toggle><span data-theme-label>Modo escuro</span></button>
    </div>
    <?= render_flash() ?>
    <form method="post" action="index.php" class="form-grid auth-form">
      <?= csrf_fields($acao) ?>
      <label class="field full" for="usuario"><?= $recuperar ? 'E-mail' : 'Usuário ou e-mail' ?>
        <input id="usuario" name="usuario" required maxlength="80" autocomplete="<?= $recuperar ? 'email' : 'username' ?>" inputmode="<?= $recuperar ? 'email' : 'text' ?>" placeholder="<?= $recuperar ? 'nome@empresa.com' : 'usuário ou e-mail' ?>">
      </label>
      <?php if (!$recuperar): ?>
      <label class="field full" for="senha">Senha
        <span class="senha-linha">
          <input id="senha" type="password" name="senha" required maxlength="80" autocomplete="current-password" placeholder="senha da operação">
          <button class="btn" type="button" data-toggle-senha aria-pressed="false" aria-controls="senha">Mostrar</button>
        </span>
      </label>
      <?php endif; ?>
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
