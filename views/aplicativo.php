<?php
declare(strict_types=1);
defined('APP') || exit;
/** @var array $db */
$marca = (string) ($db['settings']['nome'] ?? 'FLN Transfer');
$ano = (int) date('Y');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#090b10">
  <meta name="description" content="Download do aplicativo FLN Transfer para motoristas que já estão cadastrados na operação.">
  <title>Aplicativo do motorista · <?= e($marca) ?></title>
  <?php [$faviconHref, $faviconMime] = brand_favicon($db); ?>
  <link rel="icon" href="<?= e($faviconHref) ?>" type="<?= e($faviconMime) ?>">
  <link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/site-motorista.css?v=6">
  <script>
  (function () {
    var ua = navigator.userAgent || '';
    var ipad = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    document.documentElement.dataset.so = /Android/i.test(ua) ? 'android' : ((/iPhone|iPad|iPod/i.test(ua) || ipad) ? 'ios' : 'outro');
    if (navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches) {
      document.documentElement.dataset.jaApp = '1';
    }
  })();
  </script>
</head>
<body class="sm-body">
  <header class="sm-header" id="topo">
    <div class="sm-wrap sm-header-inner">
      <a class="sm-logo" href="<?= e(href('seja-motorista')) ?>">
        <img src="assets/brand/logo-fln.jpg" width="46" height="46" alt="<?= e($marca) ?>">
        <span>FLN Transfer</span>
      </a>
      <nav class="sm-nav" aria-label="Principal">
        <a href="#baixar">Download</a>
        <a href="#instalar">Instalar</a>
        <a href="<?= e(href('entrar')) ?>">Entrar</a>
      </nav>
      <a class="sm-btn sm-header-cta so-android" href="assets/app/fln-transfer.apk?v=5" data-apk>Baixar APK 1.4</a>
      <button class="sm-menu-btn" type="button" id="sm-menu" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="sm-nav-mobile sm-wrap" id="sm-nav-mobile" hidden>
      <a href="#baixar">Download</a>
      <a href="#instalar">Instalar</a>
      <a href="<?= e(href('entrar')) ?>">Entrar</a>
      <a class="sm-btn so-android" href="assets/app/fln-transfer.apk?v=5" data-apk>Baixar APK 1.4</a>
    </nav>
  </header>

  <main>
    <section class="sm-hero" id="baixar">
      <div class="sm-aurora" aria-hidden="true"><span></span><span></span><span></span></div>
      <div class="sm-wrap">
        <div class="sm-download sm-rise">
          <span class="sm-version">Versão 1.4 · motorista já cadastrado</span>
          <h1>Baixe o aplicativo e entre com o acesso que você já tem.</h1>
          <p>Esta página é só para quem já faz parte da operação. O arquivo é o FLN Transfer 1.4: abre o sistema atual, mostra só as viagens atribuídas a você e mantém o login. Se a versão anterior estiver instalada, confirme a atualização. Não apague o aplicativo antes.</p>
          <div class="sm-hero-actions">
            <a class="sm-btn so-android" href="assets/app/fln-transfer.apk?v=5" data-apk>Baixar o APK</a>
            <a class="sm-btn sm-btn-ghost" href="<?= e(href('entrar')) ?>">Já instalei, quero entrar</a>
          </div>
          <p class="sm-ja ja-instalado">Este celular já tem o FLN Transfer. Abra o ícone existente. Se aparecerem dois, apague o que foi criado pelo Chrome.</p>
          <p class="sm-hero-note">Android: um toque em baixar, abrir o arquivo e permitir a instalação. iPhone: não existe APK. Use o Safari, Compartilhar e Adicionar à Tela de Início uma única vez.</p>
        </div>
      </div>
    </section>

    <section class="sm-section" id="instalar">
      <div class="sm-wrap">
        <div class="sm-head" data-reveal>
          <div class="sm-label">Instalação</div>
          <h2>Quatro passos, com o usuário de sempre</h2>
        </div>
        <div class="sm-steps">
          <article class="sm-step is-hot" data-reveal><b>01</b><div><h3>Baixe a versão 1.4</h3><p>O arquivo se chama fln-transfer.apk. Ele substitui a 1.3 no mesmo ícone.</p></div></article>
          <article class="sm-step" data-reveal><b>02</b><div><h3>Permita esta fonte</h3><p>Se o Android perguntar, autorize a instalação a partir do navegador. Não aceite o atalho “Instalar aplicativo” do Chrome.</p></div></article>
          <article class="sm-step" data-reveal><b>03</b><div><h3>Entre com o seu acesso</h3><p>Usuário e senha que a operação já cadastrou. Esta página não cria conta e não troca a senha.</p></div></article>
          <article class="sm-step" data-reveal><b>04</b><div><h3>Ative os avisos</h3><p>No primeiro acesso, permita as notificações. A viagem atribuída, a mudança de status e o cancelamento avisam no celular.</p></div></article>
        </div>
      </div>
    </section>

    <section class="sm-section">
      <div class="sm-wrap sm-faq">
        <div data-reveal>
          <div class="sm-label">Depois do login</div>
          <h2>A tela é a sua agenda</h2>
          <p class="sm-section-lead">O aplicativo mostra as corridas atribuídas a você, o clima e o câmbio no topo. A observação interna da operação não aparece.</p>
          <p class="sm-section-lead" style="margin-top:1rem">Ainda não tem usuário? A inscrição continua na <a href="<?= e(href('seja-motorista')) ?>">página da operação</a>.</p>
        </div>
        <div class="sm-acc" data-reveal>
          <details class="sm-acc-item" open>
            <summary>A atualização apaga meu login?</summary>
            <p>Não. A versão 1.4 entra por cima e conserva a sessão. Na primeira abertura ela só descarta a tela antiga guardada no celular, para mostrar o sistema atual.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Vejo a viagem de outro motorista?</summary>
            <p>Não. A agenda e os avisos mostram somente o que está atribuído ao seu usuário.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Uso iPhone.</summary>
            <p>Abra o painel no Safari e adicione à Tela de Início uma vez. O APK é exclusivo do Android.</p>
          </details>
        </div>
      </div>
    </section>
  </main>

  <footer class="sm-footer">
    <div class="sm-wrap sm-footer-bottom">
      <span>© <?= $ano ?> <?= e($marca) ?></span>
      <span>FLN Transfer 1.4 · motoristas cadastrados</span>
    </div>
  </footer>
  <script src="assets/js/site-motorista.js?v=2"></script>
</body>
</html>
