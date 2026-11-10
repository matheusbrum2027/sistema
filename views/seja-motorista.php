<?php
declare(strict_types=1);
defined('APP') || exit;
/** @var array $db */
$marca = (string) ($db['settings']['nome'] ?? 'FLN Transfer');
$ok = isset($_GET['ok']);
$flashHtml = render_flash();
$ano = (int) date('Y');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#e10600">
  <meta name="description" content="Cadastre-se como motorista da FLN Transfer Executivo em Florianópolis. Baixe o app, registre seus dados e marque a reunião de integração.">
  <title>Seja motorista · <?= e($marca) ?></title>
  <?php [$faviconHref, $faviconMime] = brand_favicon($db); ?>
  <link rel="icon" href="<?= e($faviconHref) ?>" type="<?= e($faviconMime) ?>">
  <link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/site-motorista.css?v=1">
</head>
<body class="sm-body">
  <header class="sm-header" id="topo">
    <div class="sm-wrap sm-header-inner">
      <a class="sm-logo" href="#topo">
        <img src="assets/brand/logo-fln.jpg" width="46" height="46" alt="<?= e($marca) ?>">
        <span>FLN Transfer</span>
      </a>
      <nav class="sm-nav" aria-label="Principal">
        <a href="#inicio">Início</a>
        <a href="#como-funciona">Como funciona</a>
        <a href="#processo">Processo</a>
        <a href="#parcerias">Parcerias</a>
        <a href="#faq">FAQ</a>
      </nav>
      <a class="sm-btn sm-header-cta" href="#inscricao">Quero me candidatar</a>
      <button class="sm-menu-btn" type="button" id="sm-menu" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="sm-nav-mobile sm-wrap" id="sm-nav-mobile" hidden>
      <a href="#inicio">Início</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="#processo">Processo</a>
      <a href="#parcerias">Parcerias</a>
      <a href="#faq">FAQ</a>
      <a class="sm-btn" href="#inscricao">Quero me candidatar</a>
    </nav>
  </header>

  <?php if ($flashHtml !== ''): ?>
    <?= str_replace(['flash flash-ok', 'flash flash-err'], ['sm-flash sm-flash-ok', 'sm-flash sm-flash-err'], $flashHtml) ?>
  <?php elseif ($ok): ?>
    <div class="sm-flash sm-flash-ok" role="status">Inscrição recebida. Em breve o Matheus entra em contato para marcar a reunião.</div>
  <?php endif; ?>

  <main>
    <section class="sm-hero" id="inicio">
      <div class="sm-wrap sm-hero-grid">
        <div>
          <div class="sm-kicker">Transporte executivo · Florianópolis</div>
          <h1>Dirija com a FLN. Atenda transfers com rotina clara.</h1>
          <p class="sm-hero-lead">
            A operação cresceu. Agora o motorista se candidata por aqui: baixa o app, cadastra os próprios dados e do veículo,
            e marca a reunião com o Matheus para entender atendimento, terceirização e repasse em embarques e desembarques.
          </p>
          <div class="sm-store-row">
            <a class="sm-store" href="#app-iphone">
              <span aria-hidden="true"></span>
              <span><small>Instalar no</small><strong>iPhone</strong></span>
            </a>
            <a class="sm-store" href="assets/app/fln-transfer.apk" download="fln-transfer.apk">
              <span aria-hidden="true">▶</span>
              <span><small>Baixar</small><strong>APK Android</strong></span>
            </a>
          </div>
          <p class="sm-hero-note">Android: instale o APK (fora da Play Store). iPhone: adicione o painel à Tela de Início pelo Safari. Depois da aprovação, use login e senha-token.</p>
          <div style="margin-top:1.4rem;display:flex;flex-wrap:wrap;gap:.75rem">
            <a class="sm-btn" href="#inscricao">Fazer inscrição</a>
            <a class="sm-btn sm-btn-ghost" href="#como-funciona">Ver como funciona</a>
          </div>
        </div>
        <div class="sm-phones" aria-hidden="true">
          <div class="sm-bubble sm-bubble-1">✈</div>
          <div class="sm-bubble sm-bubble-2">🚘</div>
          <div class="sm-bubble sm-bubble-3">📍</div>
          <div class="sm-phone sm-phone-a">
            <div class="sm-phone-screen">
              <div class="sm-phone-top"><span>09:41</span><span>FLN</span></div>
              <div class="sm-chip-row"><span class="sm-chip on">Hoje</span><span class="sm-chip">Amanhã</span><span class="sm-chip">Semana</span></div>
              <div class="sm-card-mini"><strong>Aeroporto → Jurerê</strong><span>14:20 · Cliente VIP · Sedan</span></div>
              <div class="sm-card-mini"><strong>Centro → Costão</strong><span>16:00 · Hotel pickup</span></div>
              <div class="sm-card-mini"><strong>Soft landing</strong><span>Status e endereço no app</span></div>
            </div>
          </div>
          <div class="sm-phone sm-phone-b">
            <div class="sm-phone-screen">
              <div class="sm-phone-top"><span>Detalhe</span><span>●</span></div>
              <div class="sm-card-mini"><strong>Reserva #4821</strong><span>Embarque + desembarque</span></div>
              <div class="sm-card-mini"><strong>Roteiro</strong><span>2 paradas · repasse combinado</span></div>
              <div class="sm-card-mini"><strong>Contato</strong><span>Cliente e operação no chat</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="sm-section" id="como-funciona">
      <div class="sm-wrap sm-about-grid">
        <div class="sm-photo">
          <div class="sm-photo-inner">
            <div class="sm-photo-badge">
              <strong>FLN</strong>
              Transfer executivo e turismo em Florianópolis e região.
            </div>
          </div>
        </div>
        <div>
          <div class="sm-label">O projeto</div>
          <h2>Reservas centralizadas. Motoristas na ponta.</h2>
          <p class="sm-section-lead">
            O administrador cria e atribui as reservas. Você recebe no app o que é da sua agenda.
            Esta página existe para acelerar a entrada de novos motoristas na empresa quando o volume sobe —
            sem depender só de cadastro manual.
          </p>
          <a class="sm-btn" href="#inscricao">Começar cadastro</a>
          <div class="sm-stats">
            <div class="sm-stat"><strong>Agenda</strong><span>no celular</span></div>
            <div class="sm-stat"><strong>Frota</strong><span>própria ou parceira</span></div>
            <div class="sm-stat"><strong>Reunião</strong><span>com o Matheus</span></div>
          </div>
        </div>
      </div>
    </section>

    <section class="sm-logos">
      <div class="sm-wrap sm-logos-row">
        <div class="sm-logo-item">AEROPORTO HERCÍLIO LUZ</div>
        <div class="sm-logo-item">HOTÉIS &amp; RESORTS</div>
        <div class="sm-logo-item">EXECUTIVO FLN</div>
        <div class="sm-logo-item">TURISMO SC</div>
      </div>
    </section>

    <section class="sm-section sm-exp" id="experiencia">
      <div class="sm-wrap sm-exp-grid">
        <div class="sm-exp-visual">
          <div class="sm-blob" aria-hidden="true"></div>
          <div class="sm-phone">
            <div class="sm-phone-screen">
              <div class="sm-phone-top"><span>App motorista</span><span>FLN</span></div>
              <div class="sm-card-mini"><strong>Minhas reservas</strong><span>Só o que foi atribuído a você</span></div>
              <div class="sm-card-mini"><strong>Status</strong><span>Reserva · a caminho · concluída</span></div>
              <div class="sm-card-mini"><strong>Suporte</strong><span>Operação acompanha em tempo real</span></div>
            </div>
          </div>
        </div>
        <div>
          <div class="sm-label">Experiência</div>
          <h2>O que você encontra no dia a dia</h2>
          <p class="sm-section-lead">
            Clareza de rota, horário e regras de atendimento. A reunião de integração cobre o restante —
            inclusive quando há mais de um embarque ou desembarque e como o valor é repassado.
          </p>
          <div class="sm-bars" data-bars>
            <div>
              <div class="sm-bar-label"><span>Agenda organizada</span><span>92%</span></div>
              <div class="sm-bar-track"><div class="sm-bar-fill" data-w="92"></div></div>
            </div>
            <div>
              <div class="sm-bar-label"><span>Comunicação com a operação</span><span>88%</span></div>
              <div class="sm-bar-track"><div class="sm-bar-fill" data-w="88"></div></div>
            </div>
            <div>
              <div class="sm-bar-label"><span>Repasse transparente</span><span>95%</span></div>
              <div class="sm-bar-track"><div class="sm-bar-fill" data-w="95"></div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="sm-section" id="processo">
      <div class="sm-wrap">
        <div class="sm-features-head">
          <div class="sm-label">Inscrição</div>
          <h2>Do formulário à primeira corrida</h2>
          <p class="sm-section-lead">Tudo começa nesta página. Depois da reunião e da aprovação, o acesso entra no sistema da empresa.</p>
        </div>
        <div class="sm-features-grid">
          <article class="sm-feature">
            <div class="sm-feature-icon">1</div>
            <h3>Baixe / abra o app</h3>
            <p>Use o painel web da FLN no celular. Depois da aprovação você entra com e-mail e senha-token.</p>
          </article>
          <article class="sm-feature is-hot">
            <div class="sm-feature-icon">2</div>
            <h3>Cadastre seus dados</h3>
            <p>Nome, telefone, CPF, CNH e cidade. É o que a operação usa para montar sua ficha na frota.</p>
          </article>
          <article class="sm-feature">
            <div class="sm-feature-icon">3</div>
            <h3>Dados do veículo</h3>
            <p>Modelo, placa, ano, cor e lugares. Se aprovado, o veículo pode ir direto para o cadastro da frota.</p>
          </article>
          <article class="sm-feature">
            <div class="sm-feature-icon">4</div>
            <h3>Tipo de parceria</h3>
            <p>Informe se atua com veículo próprio, terceirizado ou ambos — o Matheus explica as regras na reunião.</p>
          </article>
          <article class="sm-feature">
            <div class="sm-feature-icon">5</div>
            <h3>Marque a reunião</h3>
            <p>Escolha a preferência de dia e horário. A conversa cobre atendimento, terceirização e repasses com paradas extras.</p>
          </article>
          <article class="sm-feature">
            <div class="sm-feature-icon">6</div>
            <h3>Aprovação na frota</h3>
            <p>Quando a operação aprova, você recebe login e passa a ver só as reservas atribuídas a você.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="sm-section" id="faq">
      <div class="sm-wrap sm-faq-grid">
        <div>
          <div class="sm-label">Dúvidas</div>
          <h2>Perguntas frequentes</h2>
          <p class="sm-section-lead">Antes da reunião, o essencial sobre o modelo da FLN Transfer.</p>
        </div>
        <div class="sm-acc">
          <details class="sm-acc-item" open>
            <summary>Quem cria as reservas?</summary>
            <p>O administrador. O motorista não inventa demanda: recebe no app o que foi atribuído pela operação.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Preciso ter carro próprio?</summary>
            <p>Não necessariamente. Dá para atuar com veículo próprio, em regime terceirizado ou nos dois formatos. Isso fica alinhado na reunião.</p>
          </details>
          <details class="sm-acc-item">
            <summary>O que acontece se houver mais de um embarque ou desembarque?</summary>
            <p>Há regra de repasse para paradas extras. O Matheus explica o critério na conversa de integração, com exemplos do dia a dia.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Quando recebo o login?</summary>
            <p>Depois da reunião e da aprovação no painel. Aí o cadastro entra em Motoristas e, se houver placa válida, o veículo pode ir para a frota.</p>
          </details>
          <details class="sm-acc-item">
            <summary>A inscrição nesta página já me coloca na escala?</summary>
            <p>Não. Ela abre a candidatura. A escala só começa após aprovação e atribuição de reservas pela operação.</p>
          </details>
        </div>
      </div>
    </section>

    <section class="sm-section sm-plans" id="parcerias">
      <div class="sm-wrap">
        <div class="sm-plans-head">
          <div class="sm-label">Parcerias</div>
          <h2>Como você pode entrar</h2>
          <p class="sm-section-lead">Três caminhos comuns. O detalhe comercial e operacional fica na reunião com o Matheus.</p>
        </div>
        <div class="sm-plans-grid">
          <article class="sm-plan">
            <h3>Veículo próprio</h3>
            <div class="sm-plan-price">Frota <small>sua</small></div>
            <ul>
              <li>Você cadastra o carro nesta página</li>
              <li>Atende transfers atribuídos no app</li>
              <li>Padrão executivo alinhado com a marca</li>
            </ul>
            <a class="sm-btn sm-btn-ghost" href="#inscricao">Candidatar</a>
          </article>
          <article class="sm-plan is-featured">
            <h3>Terceirizado</h3>
            <div class="sm-plan-price">Parceiro <small>FLN</small></div>
            <ul>
              <li>Modelo de terceirização explicado na reunião</li>
              <li>Regras de atendimento e apresentação</li>
              <li>Repasse claro em rotas e paradas extras</li>
            </ul>
            <a class="sm-btn" href="#inscricao">Candidatar</a>
          </article>
          <article class="sm-plan">
            <h3>Misto</h3>
            <div class="sm-plan-price">Flex <small>dois</small></div>
            <ul>
              <li>Dias com carro próprio e dias parceiros</li>
              <li>Mesmo app e mesma operação</li>
              <li>Combinado caso a caso com o Matheus</li>
            </ul>
            <a class="sm-btn sm-btn-ghost" href="#inscricao">Candidatar</a>
          </article>
        </div>
      </div>
    </section>

    <section class="sm-section" id="passos">
      <div class="sm-wrap">
        <div class="sm-features-head">
          <div class="sm-label">Na prática</div>
          <h2>Dois passos depois do formulário</h2>
        </div>
        <div class="sm-steps-grid">
          <article class="sm-step-card">
            <div class="sm-step-media">
              <span>01 · Integração</span>
              <strong style="font-family:var(--font-display);font-size:1.4rem">Reunião com o Matheus</strong>
            </div>
            <div class="sm-step-body">
              <h3>Entenda o atendimento de ponta a ponta</h3>
              <p>Como falar com o passageiro, o que fazer em atraso de voo, terceirização e como calcular repasse com mais de um embarque ou desembarque.</p>
              <a class="sm-btn sm-btn-ghost" href="#inscricao">Agendar preferência</a>
            </div>
          </article>
          <article class="sm-step-card">
            <div class="sm-step-media" style="background:linear-gradient(135deg,#111,#e10600)">
              <span>02 · Operação</span>
              <strong style="font-family:var(--font-display);font-size:1.4rem">Acesso ao sistema</strong>
            </div>
            <div class="sm-step-body">
              <h3>Login, veículo e primeiras reservas</h3>
              <p>Com a aprovação, a operação libera o usuário. Você abre o app, confere a agenda e começa a rodar os transfers atribuídos.</p>
              <a class="sm-btn sm-btn-ghost" href="<?= e(href('entrar')) ?>">Abrir o app</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="sm-section" id="app-iphone">
      <div class="sm-wrap sm-faq-grid">
        <div>
          <div class="sm-label">Aplicativo</div>
          <h2>Instale no celular</h2>
          <p class="sm-section-lead">Sem Play Store e sem App Store. Android recebe o APK; iPhone usa o atalho nativo do Safari.</p>
        </div>
        <div class="sm-acc">
          <details class="sm-acc-item" open>
            <summary>Android — baixar o APK</summary>
            <p>1) Toque em <strong>Baixar APK Android</strong>. 2) Abra o arquivo baixado. 3) Se o celular pedir, permita instalar apps deste navegador. 4) Abra o FLN Transfer, entre com seu login e em Configurações toque em <strong>Ativar</strong> para avisos.</p>
            <p style="margin-top:.8rem"><a class="sm-btn" href="assets/app/fln-transfer.apk" download="fln-transfer.apk">Baixar APK</a></p>
          </details>
          <details class="sm-acc-item">
            <summary>iPhone — adicionar à Tela de Início</summary>
            <p>A Apple não permite instalar APK. No iPhone: 1) Abra este site no <strong>Safari</strong>. 2) Toque em Compartilhar. 3) Escolha <strong>Adicionar à Tela de Início</strong>. 4) Abra o ícone FLN Transfer e entre com login. Os avisos funcionam melhor com o atalho aberto; para push completo no iOS use o Safari recente com permissão de notificação.</p>
            <p style="margin-top:.8rem"><a class="sm-btn sm-btn-ghost" href="<?= e(href('entrar')) ?>">Abrir painel no Safari</a></p>
          </details>
        </div>
      </div>
    </section>

    <section class="sm-form-band" id="inscricao">
      <div class="sm-wrap">
        <h2>Inscrição do motorista</h2>
        <p class="sm-section-lead">
          Preencha seus dados, do veículo e a preferência de reunião. A equipe recebe a candidatura e o Matheus retorna para combinar o horário.
        </p>
        <form class="sm-form" method="post" action="index.php?p=seja-motorista">
          <?= csrf_fields('candidatura_motorista') ?>
          <div class="sm-form-grid">
            <div class="sm-form-group-title">Seus dados</div>
            <div class="sm-field"><label for="nome">Nome completo</label><input id="nome" name="nome" required maxlength="80" placeholder="Seu nome" autocomplete="name"></div>
            <div class="sm-field"><label for="telefone">WhatsApp</label><input id="telefone" name="telefone" required maxlength="30" placeholder="(48) 99999-0000" autocomplete="tel"></div>
            <div class="sm-field"><label for="email">E-mail</label><input id="email" name="email" type="email" maxlength="80" placeholder="voce@email.com" autocomplete="email"></div>
            <div class="sm-field"><label for="cidade">Cidade</label><input id="cidade" name="cidade" maxlength="60" placeholder="Florianópolis"></div>
            <div class="sm-field"><label for="cpf">CPF</label><input id="cpf" name="cpf" maxlength="20" placeholder="000.000.000-00"></div>
            <div class="sm-field"><label for="cnh">CNH</label><input id="cnh" name="cnh" maxlength="20" placeholder="Número da CNH"></div>
            <div class="sm-field">
              <label for="categoria">Categoria CNH</label>
              <select id="categoria" name="categoria">
                <?php foreach (['B', 'C', 'D', 'E'] as $cat): ?>
                  <option value="<?= $cat ?>"<?= $cat === 'B' ? ' selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sm-field">
              <label for="parceria">Tipo de parceria</label>
              <select id="parceria" name="parceria">
                <option value="proprio">Veículo próprio</option>
                <option value="terceirizado">Terceirizado</option>
                <option value="ambos">Próprio e terceirizado</option>
              </select>
            </div>

            <div class="sm-form-group-title">Dados do veículo</div>
            <div class="sm-field"><label for="veiculo_modelo">Modelo</label><input id="veiculo_modelo" name="veiculo_modelo" maxlength="60" placeholder="Toyota Corolla"></div>
            <div class="sm-field"><label for="veiculo_placa">Placa</label><input id="veiculo_placa" name="veiculo_placa" maxlength="12" placeholder="ABC1D23" style="text-transform:uppercase"></div>
            <div class="sm-field"><label for="veiculo_ano">Ano</label><input id="veiculo_ano" name="veiculo_ano" maxlength="4" placeholder="2022" inputmode="numeric"></div>
            <div class="sm-field"><label for="veiculo_cor">Cor</label><input id="veiculo_cor" name="veiculo_cor" maxlength="30" placeholder="Preto"></div>
            <div class="sm-field"><label for="veiculo_lugares">Lugares</label><input id="veiculo_lugares" name="veiculo_lugares" type="number" min="1" max="20" placeholder="4"></div>
            <div class="sm-field"><label for="reuniao_pref">Preferência de reunião</label><input id="reuniao_pref" name="reuniao_pref" maxlength="120" placeholder="Ex.: terça à tarde ou quinta de manhã"></div>

            <div class="sm-field full">
              <label for="mensagem">Mensagem (opcional)</label>
              <textarea id="mensagem" name="mensagem" maxlength="500" placeholder="Experiência com transfer, disponibilidade semanal, dúvidas sobre terceirização…"></textarea>
            </div>
          </div>
          <div class="sm-form-actions">
            <button class="sm-btn" type="submit">Enviar inscrição</button>
            <span class="sm-form-hint">Ao enviar, a candidatura aparece para a operação em Solicitação de motoristas.</span>
          </div>
        </form>
      </div>
    </section>
  </main>

  <footer class="sm-footer">
    <div class="sm-wrap sm-footer-grid">
      <div class="sm-footer-brand">
        <a class="sm-logo" href="#topo" style="color:#fff">
          <img src="assets/brand/logo-fln.jpg" width="46" height="46" alt="">
          <span>FLN Transfer</span>
        </a>
        <p>Transporte executivo e turismo em Florianópolis. Esta página é o canal oficial de inscrição de motoristas na operação.</p>
        <div class="sm-store-row">
          <a class="sm-store" href="assets/app/fln-transfer.apk" download="fln-transfer.apk"><span><small>Android</small><strong>Baixar APK</strong></span></a>
          <a class="sm-store" href="#app-iphone"><span><small>iPhone</small><strong>Instalar atalho</strong></span></a>
        </div>
      </div>
      <div>
        <h4>Navegação</h4>
        <ul>
          <li><a href="#inicio">Início</a></li>
          <li><a href="#como-funciona">Como funciona</a></li>
          <li><a href="#processo">Processo</a></li>
          <li><a href="#inscricao">Inscrição</a></li>
        </ul>
      </div>
      <div>
        <h4>Operação</h4>
        <ul>
          <li><a href="<?= e(href('entrar')) ?>">Entrar no sistema</a></li>
          <li><a href="#parcerias">Parcerias</a></li>
          <li><a href="#faq">FAQ</a></li>
        </ul>
      </div>
      <div>
        <h4>Contato</h4>
        <ul>
          <li>Florianópolis — SC</li>
          <li>Reunião: Matheus</li>
          <li>Candidatura pelo formulário desta página</li>
        </ul>
      </div>
    </div>
    <div class="sm-wrap sm-footer-bottom">
      <span>© <?= $ano ?> <?= e($marca) ?></span>
      <span>Transporte executivo · inscrição de motoristas</span>
    </div>
  </footer>

  <script>
  (function () {
    var header = document.querySelector('.sm-header');
    var menuBtn = document.getElementById('sm-menu');
    var mobile = document.getElementById('sm-nav-mobile');
    function onScroll() {
      if (!header) return;
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    if (menuBtn && mobile) {
      menuBtn.addEventListener('click', function () {
        var open = mobile.classList.toggle('open');
        mobile.hidden = !open;
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      mobile.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          mobile.classList.remove('open');
          mobile.hidden = true;
          menuBtn.setAttribute('aria-expanded', 'false');
        });
      });
    }
    var bars = document.querySelector('[data-bars]');
    if (bars && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.querySelectorAll('.sm-bar-fill').forEach(function (el) {
            el.style.width = (el.getAttribute('data-w') || '0') + '%';
          });
          io.disconnect();
        });
      }, { threshold: 0.35 });
      io.observe(bars);
    }
  })();
  </script>
</body>
</html>
