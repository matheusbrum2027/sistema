<?php
declare(strict_types=1);
defined('APP') || exit;
$recuperar = isset($_GET['recuperar']);
$cancelar2fa = isset($_GET['cancelar']);
$etapa2fa = !$recuperar && (string) ($_GET['etapa'] ?? '') === '2fa';
if ($cancelar2fa || (!$etapa2fa && !$recuperar && isset($_GET['limpar_2fa']))) {
    unset($_SESSION['pending_2fa']);
}
if ($cancelar2fa) {
    redirect(href('entrar'));
}
$pendente2fa = $etapa2fa ? login_pendente_2fa() : null;
if ($etapa2fa && $pendente2fa === null) {
    flash('A verificação expirou. Entre de novo.', 'err');
    redirect(href('entrar'));
}
$exigeCodigo = !$etapa2fa;
if ($exigeCodigo) {
    captcha_garantir();
    $cap = $_SESSION['captcha'];
    $ver = (int) ($cap['ver'] ?? 1);
}
$acao = $recuperar ? 'recuperar' : ($etapa2fa ? 'login_2fa' : 'login');
$marca = (string) ($db['settings']['nome'] ?? 'Transfer FLN');
$acessoNome = '';
$acessoUsuario = '';
$acessoToken = strtolower((string) ($_GET['acesso'] ?? ''));
if (!$etapa2fa && preg_match('/^[a-f0-9]{32}$/', $acessoToken)) {
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
$titulo = $recuperar ? 'Recuperar acesso' : ($etapa2fa ? 'Código do autenticador' : 'Acesse sua operação');
$segRestantes = 30 - (time() % 30);
?>
<section class="auth-screen">
  <aside class="auth-hero">
    <img src="assets/brand/logo-fln.jpg" width="640" height="640" alt="<?= e($marca) ?> — Transfer Executivo e Turismo, Florianópolis">
    <p>Reservas, motoristas, veículos e financeiro no mesmo painel.</p>
  </aside>
  <div class="auth-card">
    <div class="auth-card-top">
      <div>
        <h1><?= e($titulo) ?></h1>
        <?php if (!$etapa2fa): ?>
        <p class="lead"><?= $recuperar ? 'Informe o e-mail da conta. A resposta não indica se o cadastro existe.' : e($leadLogin) ?></p>
        <?php endif; ?>
      </div>
      <button class="btn theme-btn" type="button" data-theme-toggle><span data-theme-label>Modo escuro</span></button>
    </div>
    <?= render_flash() ?>
    <form method="post" action="index.php" class="form-grid auth-form<?= $etapa2fa ? ' auth-form-2fa' : '' ?>" autocomplete="<?= $etapa2fa ? 'off' : 'on' ?>">
      <?= csrf_fields($acao) ?>
      <?php if ($etapa2fa): ?>
      <div class="auth-2fa full">
        <p class="auth-2fa-passo">Passo 2 de 2</p>
        <p class="auth-2fa-titulo">Abra o Google Authenticator (ou Authy) e digite o código de 6 dígitos</p>
        <label class="field auth-2fa-campo" for="codigo_2fa">
          <span class="auth-2fa-rotulo">Código</span>
          <input id="codigo_2fa" class="auth-2fa-input" name="codigo_2fa" required maxlength="6" minlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" autofocus>
        </label>
        <p class="auth-2fa-timer">O código muda a cada 30s · faltam ~<?= (int) $segRestantes ?>s neste ciclo</p>
      </div>
      <?php else: ?>
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
          <span id="captcha-rotulo">Código visual anti-bot</span>
          <a class="btn" href="index.php?captcha_novo=1<?= $recuperar ? '&amp;recuperar=1' : '' ?>">Atualizar código</a>
        </div>
        <img src="index.php?captcha=img&amp;v=<?= $ver ?>" width="228" height="76" alt="Imagem do código de verificação. Use o áudio se não conseguir ler.">
        <p class="captcha-ajuda">Cinco caracteres. Espaços são ignorados.</p>
        <a href="index.php?captcha=audio&amp;v=<?= $ver ?>">Ouvir alternativa em áudio</a>
        <label class="field" for="captcha">Digite o código visual
          <input id="captcha" name="captcha" required maxlength="8" autocomplete="off" spellcheck="false" aria-describedby="captcha-rotulo">
        </label>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <div class="form-actions full">
        <button class="btn btn-primary" type="submit"><?= $recuperar ? 'Enviar instruções' : ($etapa2fa ? 'Entrar' : 'Entrar') ?></button>
        <?php if ($recuperar): ?>
          <a class="btn" href="index.php?p=entrar">Voltar ao login</a>
        <?php elseif ($etapa2fa): ?>
          <a class="btn" href="index.php?p=entrar&amp;cancelar=1">Voltar</a>
        <?php else: ?>
          <a class="btn" href="index.php?p=entrar&amp;recuperar=1">Esqueci a senha</a>
        <?php endif; ?>
      </div>
    </form>
    <?php if (!$etapa2fa && !$recuperar): ?>
    <p class="auth-seguro">Login com usuário, senha e código visual. Se a 2FA estiver ativa, o autenticador pede o código depois.</p>
    <?php endif; ?>
  </div>
</section>
<?php if ($etapa2fa): ?>
<script>
(function () {
  var input = document.getElementById('codigo_2fa');
  if (!input) return;
  input.addEventListener('input', function () {
    input.value = input.value.replace(/\D+/g, '').slice(0, 6);
    if (input.value.length === 6 && input.form) {
      input.form.requestSubmit ? input.form.requestSubmit() : input.form.submit();
    }
  });
})();
</script>
<?php endif; ?>
