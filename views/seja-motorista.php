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
  <meta name="description" content="Cadastre-se como motorista da FLN Transfer Executivo em Florianópolis. Baixe o app, registre seus dados e marque a reunião de integração.">
  <title>Seja motorista · <?= e($marca) ?></title>
  <?php [$faviconHref, $faviconMime] = brand_favicon($db); ?>
  <link rel="icon" href="<?= e($faviconHref) ?>" type="<?= e($faviconMime) ?>">
  <link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/site-motorista.css?v=4">
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
      <div class="sm-aurora" aria-hidden="true"><span></span><span></span><span></span></div>
      <div class="sm-wrap sm-hero-grid">
        <div class="sm-hero-copy">
          <div class="sm-kicker sm-rise"><i></i> Florianópolis · transfer executivo</div>
          <h1 class="sm-rise sm-d1">Dirija a ilha.<br><em>A rota chega no app.</em></h1>
          <p class="sm-hero-lead sm-rise sm-d2">A operação cresceu e a entrada de motoristas passou para esta página. Você baixa o aplicativo, envia seus dados e do veículo, e marca a reunião com o Matheus para atendimento, terceirização e repasse.</p>
          <div class="sm-hero-actions sm-rise sm-d3">
            <a class="sm-btn" href="#inscricao">Fazer inscrição</a>
            <a class="sm-btn sm-btn-ghost" href="#como-funciona">Ver como funciona</a>
          </div>
          <p class="sm-ja ja-instalado">Este celular já tem o FLN Transfer. Não adicione outro ícone.</p>
          <div class="sm-store-row sm-rise sm-d4 so-instalar">
            <a class="sm-store so-android" href="assets/app/fln-transfer.apk" data-apk>
              <b aria-hidden="true">▶</b>
              <span><small>Android · um arquivo</small><strong>Baixar APK</strong></span>
            </a>
            <a class="sm-store so-ios" href="#app">
              <b aria-hidden="true"></b>
              <span><small>iPhone · um atalho</small><strong>Adicionar uma vez</strong></span>
            </a>
          </div>
          <p class="sm-hero-note">Um celular, um aplicativo. Android instala só o APK. iPhone adiciona o painel uma vez no Safari. Se o Chrome oferecer “Instalar aplicativo”, cancele.</p>
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

    <section class="sm-section" id="como-funciona">
      <div class="sm-wrap sm-split">
        <div class="sm-panel" data-reveal>
          <strong>FLN</strong>
          <p>Transfer executivo e turismo em Florianópolis e região. A reserva nasce na operação e chega no celular de quem vai fazer a corrida.</p>
        </div>
        <div data-reveal>
          <div class="sm-label">O projeto</div>
          <h2>Reservas na mesa. Motorista na ponta.</h2>
          <p class="sm-section-lead">O administrador cria e atribui. Você vê no aplicativo só o que é da sua agenda. Esta página acelera a entrada quando o volume sobe, sem depender só de cadastro manual.</p>
          <a class="sm-btn" href="#inscricao">Começar cadastro</a>
          <div class="sm-stats">
            <div class="sm-stat"><strong>Agenda</strong><span>no celular</span></div>
            <div class="sm-stat"><strong>Frota</strong><span>própria ou parceira</span></div>
            <div class="sm-stat"><strong>Reunião</strong><span>com o Matheus</span></div>
          </div>
        </div>
      </div>
    </section>

    <section class="sm-section" id="processo">
      <div class="sm-wrap">
        <div class="sm-head" data-reveal>
          <div class="sm-label">Inscrição</div>
          <h2>Do formulário à primeira corrida</h2>
          <p class="sm-section-lead">Tudo começa aqui. Depois da reunião e da aprovação, o acesso entra no sistema da empresa.</p>
        </div>
        <div class="sm-steps">
          <article class="sm-step" data-reveal><b>01</b><div><h3>Abra o aplicativo</h3><p>Android recebe um único APK. iPhone ganha um único atalho no Safari. O login chega depois da aprovação.</p></div></article>
          <article class="sm-step is-hot" data-reveal><b>02</b><div><h3>Cadastre seus dados</h3><p>Nome, WhatsApp, CPF, CNH e cidade. É a ficha que a operação usa na frota.</p></div></article>
          <article class="sm-step" data-reveal><b>03</b><div><h3>Informe o veículo</h3><p>Modelo, placa, ano, cor e lugares. Com a aprovação, o carro pode entrar na frota.</p></div></article>
          <article class="sm-step" data-reveal><b>04</b><div><h3>Escolha a parceria</h3><p>Veículo próprio, terceirizado ou os dois. As regras ficam claras na reunião.</p></div></article>
          <article class="sm-step" data-reveal><b>05</b><div><h3>Marque a conversa</h3><p>Dia e horário de preferência. Atendimento, terceirização e repasse de parada extra.</p></div></article>
          <article class="sm-step" data-reveal><b>06</b><div><h3>Entre na escala</h3><p>Aprovado, você recebe o login e passa a ver somente as reservas atribuídas a você.</p></div></article>
        </div>
      </div>
    </section>

    <section class="sm-section" id="experiencia">
      <div class="sm-wrap sm-exp">
        <div data-reveal>
          <div class="sm-label">No aplicativo</div>
          <h2>O dia a dia cabe numa tela</h2>
          <p class="sm-section-lead">Horário, rota e status. A reunião cobre o restante, inclusive quando há mais de um embarque ou desembarque.</p>
        </div>
        <div class="sm-meter" data-reveal>
          <div><span>Agenda só das suas corridas <small>100%</small></span><div class="sm-track"><i class="sm-fill" style="--w:100%"></i></div></div>
          <div><span>Status em um toque <small>no app</small></span><div class="sm-track"><i class="sm-fill" style="--w:86%"></i></div></div>
          <div><span>Repasse explicado na reunião <small>com exemplos</small></span><div class="sm-track"><i class="sm-fill" style="--w:94%"></i></div></div>
        </div>
      </div>
    </section>

    <section class="sm-section" id="parcerias">
      <div class="sm-wrap">
        <div class="sm-head" data-reveal>
          <div class="sm-label">Parcerias</div>
          <h2>Três jeitos de entrar</h2>
          <p class="sm-section-lead">O detalhe comercial fica na reunião com o Matheus. O formulário já registra o caminho que você prefere.</p>
        </div>
        <div class="sm-plans">
          <article class="sm-plan" data-reveal>
            <h3>Veículo próprio</h3>
            <div class="sm-plan-price">Frota <small>sua</small></div>
            <ul>
              <li>O carro é cadastrado nesta página</li>
              <li>As corridas chegam atribuídas no app</li>
              <li>Padrão executivo alinhado com a marca</li>
            </ul>
            <a class="sm-btn sm-btn-ghost" href="#inscricao">Candidatar</a>
          </article>
          <article class="sm-plan is-featured" data-reveal>
            <h3>Terceirizado</h3>
            <div class="sm-plan-price">Parceiro <small>FLN</small></div>
            <ul>
              <li>O modelo é explicado na reunião</li>
              <li>Atendimento e apresentação combinados</li>
              <li>Repasse claro em rota e parada extra</li>
            </ul>
            <a class="sm-btn sm-btn-light" href="#inscricao">Candidatar</a>
          </article>
          <article class="sm-plan" data-reveal>
            <h3>Misto</h3>
            <div class="sm-plan-price">Flex <small>dois</small></div>
            <ul>
              <li>Dias com carro próprio e dias parceiros</li>
              <li>O mesmo aplicativo e a mesma operação</li>
              <li>Combinado caso a caso com o Matheus</li>
            </ul>
            <a class="sm-btn sm-btn-ghost" href="#inscricao">Candidatar</a>
          </article>
        </div>
      </div>
    </section>

    <section class="sm-section" id="faq">
      <div class="sm-wrap sm-faq">
        <div data-reveal>
          <div class="sm-label">Dúvidas</div>
          <h2>Antes da reunião</h2>
          <p class="sm-section-lead">O essencial sobre o modelo da FLN Transfer. O restante a gente alinha pessoalmente.</p>
        </div>
        <div class="sm-acc" data-reveal>
          <details class="sm-acc-item" open>
            <summary>Quem cria as reservas?</summary>
            <p>O administrador. O motorista não inventa demanda: recebe no app o que foi atribuído pela operação.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Preciso ter carro próprio?</summary>
            <p>Não necessariamente. Dá para atuar com veículo próprio, em regime terceirizado ou nos dois formatos. Isso fica alinhado na reunião.</p>
          </details>
          <details class="sm-acc-item">
            <summary>E se houver mais de um embarque ou desembarque?</summary>
            <p>Há regra de repasse para paradas extras. O Matheus explica o critério na conversa de integração, com exemplos do dia a dia.</p>
          </details>
          <details class="sm-acc-item">
            <summary>Quando recebo o login?</summary>
            <p>Depois da reunião e da aprovação no painel. Aí o cadastro entra em Motoristas e, se houver placa válida, o veículo pode ir para a frota.</p>
          </details>
          <details class="sm-acc-item">
            <summary>A inscrição já me coloca na escala?</summary>
            <p>Não. Ela abre a candidatura. A escala só começa após aprovação e atribuição de reservas pela operação.</p>
          </details>
        </div>
      </div>
    </section>

    <section class="sm-section" id="app">
      <div class="sm-wrap">
        <p class="sm-ja ja-instalado" data-reveal>O FLN Transfer já está neste celular. Abra o ícone que já existe e apague o outro, se tiver sobrado dois.</p>
        <div class="sm-duo so-instalar">
          <article class="so-android" data-reveal>
            <span>Android</span>
            <h3>Só o APK</h3>
            <p>Toque uma vez em baixar, abra o arquivo e permita a instalação. Não use o aviso do Chrome que oferece instalar o site: esse segundo ícone é o aplicativo duplicado. Depois entre e ative os avisos.</p>
            <a class="sm-btn" href="assets/app/fln-transfer.apk" data-apk>Baixar o aplicativo</a>
          </article>
          <article class="so-ios" data-reveal>
            <span>iPhone</span>
            <h3>Um atalho, no Safari</h3>
            <p>Abra o painel no Safari, toque em Compartilhar e escolha Adicionar à Tela de Início uma única vez. Não repita o passo. Se ficaram dois ícones, apague um e fique com o FLN Transfer.</p>
            <a class="sm-btn sm-btn-ghost" href="<?= e(href('entrar')) ?>">Abrir o painel no Safari</a>
          </article>
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
        <p>Transporte executivo e turismo em Florianópolis. Canal oficial de inscrição de motoristas na operação.</p>
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
          <li><a href="<?= e(href('entrar')) ?>">Entrar no app</a></li>
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
      <span>Transfer executivo · inscrição de motoristas</span>
    </div>
  </footer>
  <script src="assets/js/site-motorista.js?v=2"></script>
</body>
</html>
