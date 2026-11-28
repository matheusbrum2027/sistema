<?php
declare(strict_types=1);
defined('APP') || exit;
/** @var array $db */
$marca = (string) ($db['settings']['nome'] ?? 'FLN Transfer');
$ok = isset($_GET['ok']);
$flashHtml = render_flash();
$ano = (int) date('Y');
$faixa = ['Aeroporto Hercílio Luz', 'Jurerê', 'Lagoa da Conceição', 'Campeche', 'Santo Antônio', 'Centro', 'Costão do Santinho', 'Praia Brava'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#090b10">
  <meta name="description" content="Página de quem já conhece a FLN Transfer. Motorista cadastrado baixa o aplicativo. Quem ainda vai entrar na frota faz a inscrição.">
  <title>Seja motorista · <?= e($marca) ?></title>
  <?php [$faviconHref, $faviconMime] = brand_favicon($db); ?>
  <link rel="icon" href="<?= e($faviconHref) ?>" type="<?= e($faviconMime) ?>">
  <link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/site-motorista.css?v=5">
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
      <a class="sm-logo" href="#inicio">
        <img src="assets/brand/logo-fln.jpg" width="46" height="46" alt="<?= e($marca) ?>">
        <span>FLN Transfer</span>
      </a>
      <nav class="sm-nav" aria-label="Principal">
        <a href="#inicio">Início</a>
        <a href="<?= e(href('aplicativo')) ?>">Já sou motorista</a>
        <a href="#no-app">No aplicativo</a>
        <a href="#inscricao">Inscrição</a>
      </nav>
      <a class="sm-btn sm-header-cta" href="<?= e(href('aplicativo')) ?>">Baixar o app</a>
      <button class="sm-menu-btn" type="button" id="sm-menu" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="sm-nav-mobile sm-wrap" id="sm-nav-mobile" hidden>
      <a href="#inicio">Início</a>
      <a href="<?= e(href('aplicativo')) ?>">Já sou motorista</a>
      <a href="#no-app">No aplicativo</a>
      <a href="#inscricao">Inscrição</a>
      <a class="sm-btn" href="<?= e(href('aplicativo')) ?>">Baixar o app</a>
    </nav>
  </header>

  <?php if ($flashHtml !== ''): ?>
    <?= str_replace(['flash flash-ok', 'flash flash-err'], ['sm-flash sm-flash-ok', 'sm-flash sm-flash-err'], $flashHtml) ?>
  <?php elseif ($ok): ?>
    <div class="sm-flash sm-flash-ok" role="status">Inscrição recebida. Em breve o Matheus entra em contato para marcar a reunião.</div>
  <?php endif; ?>

  <main>
    <section class="sm-hero" id="inicio">
      <div class="sm-aurora" aria-hidden="true"><span></span><span></span><span></span></div>
      <div class="sm-wrap sm-hero-grid">
        <div class="sm-hero-copy">
          <div class="sm-kicker sm-rise"><i></i> Para quem já conhece a operação</div>
          <h1 class="sm-rise sm-d1">O aplicativo da casa.<br><em>O acesso você já tem.</em></h1>
          <p class="sm-hero-lead sm-rise sm-d2">Esta página é o canal da FLN Transfer. Quem já está cadastrado baixa o aplicativo e entra com o mesmo usuário. Quem ainda vai entrar na frota deixa a inscrição no final da página.</p>
          <div class="sm-hero-actions sm-rise sm-d3">
            <a class="sm-btn" href="<?= e(href('aplicativo')) ?>">Já sou motorista</a>
            <a class="sm-btn sm-btn-ghost" href="#inscricao">Ainda vou me cadastrar</a>
          </div>
          <p class="sm-ja ja-instalado">Este celular já tem o FLN Transfer. Não adicione outro ícone.</p>
          <div class="sm-store-row sm-rise sm-d4 so-instalar">
            <a class="sm-store so-android" href="<?= e(href('aplicativo')) ?>">
              <b aria-hidden="true">▶</b>
              <span><small>Android · versão 1.4</small><strong>Página do aplicativo</strong></span>
            </a>
            <a class="sm-store so-ios" href="#app">
              <b aria-hidden="true"></b>
              <span><small>iPhone · um atalho</small><strong>Adicionar uma vez</strong></span>
            </a>
          </div>
          <p class="sm-hero-note">Um celular, um aplicativo. A instalação nova entra por cima da anterior e mantém o login. Se o Chrome oferecer “Instalar aplicativo”, cancele: isso cria um segundo ícone.</p>
        </div>
        <div class="sm-stage" aria-hidden="true">
          <div class="sm-pill sm-pill-1"><span class="sm-dot"></span><span>Agenda ao vivo<small>só as suas corridas</small></span></div>
          <div class="sm-pill sm-pill-2"><span class="sm-dot"></span><span>Repasse na reunião<small>embarque e parada extra</small></span></div>
          <article class="sm-device sm-device-b">
            <div class="sm-screen">
              <div class="sm-status"><span>Detalhe</span><span>FLN</span></div>
              <h3>Reserva</h3>
              <div class="sm-trip"><time>14:20</time><div><strong>Embarque</strong><span>Aeroporto Hercílio Luz</span></div></div>
              <div class="sm-trip"><time>15:05</time><div><strong>Desembarque</strong><span>Jurerê Internacional</span></div></div>
              <div class="sm-trip"><time>2</time><div><strong>Paradas</strong><span>repasse combinado</span></div></div>
            </div>
          </article>
          <article class="sm-device sm-device-a">
            <div class="sm-screen">
              <div class="sm-status"><span>09:41</span><span>FLN</span></div>
              <h3>Agenda</h3>
              <div class="sm-chip-row"><span class="sm-chip on">Hoje</span><span class="sm-chip">Amanhã</span><span class="sm-chip">Semana</span></div>
              <div class="sm-trip"><time>14:20</time><div><strong>Aeroporto → Jurerê</strong><span>Sedan · embarque</span></div><em>Ida</em></div>
              <div class="sm-trip"><time>16:00</time><div><strong>Centro → Costão</strong><span>Hotel pickup</span></div><em>Volta</em></div>
              <div class="sm-trip"><time>18:40</time><div><strong>Lagoa → Campeche</strong><span>Status no app</span></div><em>Ida</em></div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <div class="sm-marquee" aria-hidden="true">
      <div class="sm-marquee-track">
        <?php for ($volta = 0; $volta < 2; $volta++): ?>
          <?php foreach ($faixa as $lugar): ?><span><?= e($lugar) ?></span><?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>

    <section class="sm-section" id="no-app">
      <div class="sm-wrap">
        <div class="sm-head" data-reveal>
          <div class="sm-label">Dois caminhos</div>
          <h2>Quem já dirige e quem ainda vai entrar</h2>
          <p class="sm-section-lead">A maior parte de quem abre esta página já trabalha com a FLN. O download fica numa página só de motorista cadastrado. A inscrição continua aqui, no final.</p>
        </div>
        <div class="sm-paths">
          <article class="sm-path is-featured" data-reveal>
            <span>Já cadastrado</span>
            <h3>Baixar o aplicativo</h3>
            <p>Use o usuário que a operação já entregou. A versão 1.4 instala por cima da anterior, abre o sistema atual e mantém a sessão.</p>
            <a class="sm-btn" href="<?= e(href('aplicativo')) ?>">Ir para o download</a>
          </article>
          <article class="sm-path" data-reveal>
            <span>Ainda sem acesso</span>
            <h3>Deixar a inscrição</h3>
            <p>Nome, contato, veículo e o horário em que consegue conversar com o Matheus. A escala só começa depois da aprovação.</p>
            <a class="sm-btn sm-btn-ghost" href="#inscricao">Ir para a inscrição</a>
          </article>
        </div>
      </div>
    </section>

    <section class="sm-section">
      <div class="sm-wrap">
        <div class="sm-head" data-reveal>
          <div class="sm-label">No celular</div>
          <h2>O que o motorista encontra depois de entrar</h2>
        </div>
        <div class="sm-plans">
          <article class="sm-plan" data-reveal>
            <h3>Agenda só sua</h3>
            <div class="sm-plan-price">Corridas <small>atribuídas</small></div>
            <ul>
              <li>Horário, embarque e desembarque</li>
              <li>Ida, volta, trajetos e por hora</li>
              <li>Nada da agenda dos outros motoristas</li>
            </ul>
          </article>
          <article class="sm-plan is-featured" data-reveal>
            <h3>Aviso no aparelho</h3>
            <div class="sm-plan-price">Na hora <small>da escala</small></div>
            <ul>
              <li>Viagem atribuída</li>
              <li>Mudança de status</li>
              <li>Cancelamento</li>
            </ul>
          </article>
          <article class="sm-plan" data-reveal>
            <h3>Rota para o dia</h3>
            <div class="sm-plan-price">PDF <small>da viagem</small></div>
            <ul>
              <li>O arquivo vai para Downloads</li>
              <li>A observação visível ao motorista sai no PDF</li>
              <li>O login continua depois de sair da tela</li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <section class="sm-section" id="faq">
      <div class="sm-wrap sm-faq">
        <div data-reveal>
          <div class="sm-label">Uso</div>
          <h2>O que costuma travar na instalação</h2>
        </div>
        <div class="sm-acc" data-reveal>
          <details class="sm-acc-item" open>
            <summary>Já tenho o aplicativo. Preciso apagar?</summary>
            <p>Não. Baixe a versão 1.4 e confirme a atualização. O Android troca o arquivo e o usuário permanece.</p>
          </details>
          <details class="sm-acc-item">
            <summary>O Chrome ofereceu instalar o site. Aceito?</summary>
            <p>Não. Esse aviso cria um segundo ícone. No Android use só o APK. No iPhone, um único atalho pelo Safari.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Qual usuário eu uso?</summary>
            <p>O mesmo que a operação cadastrou. A página de download não cria senha e não substitui o acesso que você já tem.</p>
          </details>
          <details class="sm-acc-item">
            <summary>A inscrição desta página já me coloca na escala?</summary>
            <p>Não. Ela avisa a operação. O login e as corridas entram depois da aprovação.</p>
          </details>
        </div>
      </div>
    </section>

    <section class="sm-form-band" id="inscricao">
      <div class="sm-wrap">
        <div data-reveal>
          <div class="sm-label">Candidatura</div>
          <h2>Inscrição do motorista</h2>
          <p class="sm-section-lead">Seus dados, o veículo e a preferência de reunião. A equipe recebe a candidatura e o Matheus retorna para combinar o horário.</p>
        </div>
        <form class="sm-form" method="post" action="index.php?p=seja-motorista" data-reveal>
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
            <span class="sm-form-hint">A candidatura aparece para a operação em Solicitação de motoristas.</span>
          </div>
        </form>
      </div>
    </section>
  </main>

  <footer class="sm-footer">
    <div class="sm-wrap sm-footer-grid">
      <div class="sm-footer-brand">
        <a class="sm-logo" href="#inicio">
          <img src="assets/brand/logo-fln.jpg" width="46" height="46" alt="">
          <span>FLN Transfer</span>
        </a>
        <p>Canal de quem já conhece a operação. Motorista cadastrado baixa o aplicativo. Nova entrada deixa a inscrição nesta página.</p>
      </div>
      <div>
        <h4>Navegação</h4>
        <ul>
          <li><a href="#inicio">Início</a></li>
          <li><a href="<?= e(href('aplicativo')) ?>">Aplicativo do motorista</a></li>
          <li><a href="#inscricao">Inscrição</a></li>
        </ul>
      </div>
      <div>
        <h4>Operação</h4>
        <ul>
          <li><a href="<?= e(href('entrar')) ?>">Entrar no painel</a></li>
          <li><a href="<?= e(href('aplicativo')) ?>">Download do APK</a></li>
          <li><a href="#faq">Dúvidas de instalação</a></li>
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
      <span>Aplicativo 1.4 para quem já está na operação</span>
    </div>
  </footer>
  <script src="assets/js/site-motorista.js?v=2"></script>
</body>
</html>
