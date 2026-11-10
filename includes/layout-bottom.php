<?php
declare(strict_types=1);
/** @var bool $bare */
/** @var bool $partial */
?>
<?php if ($partial): ?>
  </div>
<?php elseif ($bare): ?>
  </div>
<?php else: ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php if (!$partial): ?>
<dialog id="dlg-confirma">
  <header class="dialog-h"><h2 id="confirma-titulo">Confirmar</h2><button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button></header>
  <div class="dialog-b"><p id="confirma-texto">Confirma esta ação?</p></div>
  <footer class="dlg-foot">
    <button class="btn" type="button" data-close-dialog>Não</button>
    <button class="btn btn-danger" type="button" id="confirma-sim">Sim</button>
  </footer>
</dialog>
<script src="assets/js/app.js?v=23"></script>
<?php if (($_SESSION['role'] ?? '') === 'admin' && !empty($_SESSION['auth']) && empty($bare)): ?>
<div class="flutuantes-direita" id="flutuantes-direita">
  <div class="chat-borda" id="chat-borda">
    <button class="dock-aba dock-chat" type="button" id="chat-abrir" aria-controls="chat-painel" aria-expanded="false" title="WhatsApp">
      <span class="dock-ico" aria-hidden="true"><?= icon('chat') ?></span>
      <span class="dock-aba-txt">WhatsApp</span>
      <span class="dock-aba-badge" id="chat-aba-badge" hidden></span>
    </button>
  </div>
  <div class="dock-borda" id="mapa-borda">
    <button class="dock-aba dock-mapa" type="button" id="mapa-abrir" aria-controls="mapa-painel" aria-expanded="false" title="Mapa operacional">
      <span class="dock-ico" aria-hidden="true"><?= icon('pin') ?></span>
      <span class="dock-aba-txt">Mapa</span>
      <span class="dock-aba-badge" id="mapa-aba-badge" hidden></span>
    </button>
  </div>
</div>
<aside class="mapa-painel" id="mapa-painel" hidden>
  <header class="mapa-h">
    <div class="mapa-h-titulo">
      <strong>Mapa ao vivo</strong>
      <small id="mapa-status-linha">Viagens na Grande Florianópolis</small>
    </div>
    <button class="mapa-ico-btn" type="button" id="mapa-full" title="Tela cheia" aria-label="Tela cheia" aria-pressed="false"><?= icon('expand') ?></button>
    <button class="mapa-x" type="button" data-mapa-fechar aria-label="Fechar">×</button>
  </header>
  <div class="mapa-filtros">
    <div class="mapa-seg" id="mapa-filtro-periodo" role="tablist">
      <button type="button" class="is-on" data-filtro="proximas">Próximas</button>
      <button type="button" data-filtro="hoje">Hoje</button>
      <button type="button" data-filtro="7d">7 dias</button>
      <button type="button" data-filtro="30d">30 dias</button>
    </div>
    <div class="mapa-seg" id="mapa-filtro-status" role="tablist">
      <button type="button" class="is-on" data-status="todas">Todas</button>
      <button type="button" data-status="reserva">Reserva</button>
      <button type="button" data-status="andamento">Andamento</button>
      <button type="button" data-status="pendente">Pendente</button>
    </div>
  </div>
  <div class="mapa-kpis">
    <div class="mapa-kpi"><span id="mapa-kpi-total">0</span><small>Viagens</small></div>
    <div class="mapa-kpi"><span id="mapa-kpi-pins">0</span><small>Pontos</small></div>
    <div class="mapa-kpi"><span id="mapa-kpi-rotas">0</span><small>Rotas</small></div>
  </div>
  <div class="mapa-stage-wrap">
    <div class="mapa-stage" id="mapa-stage" aria-label="Mapa Leaflet da operação"></div>
  </div>
  <div class="mapa-rodape">
    <div class="mapa-legenda">
      <span><i class="lg-origem"></i> Embarque</span>
      <span><i class="lg-destino"></i> Destino</span>
      <span><i class="lg-rota"></i> Rota</span>
    </div>
    <div class="mapa-lista" id="mapa-lista"></div>
  </div>
</aside>
<aside class="chat-painel" id="chat-painel" hidden>
  <section class="chat-lista" aria-label="Conversas">
    <header class="chat-lista-h">
      <div class="chat-lista-titulo">
        <strong>Conversas</strong>
        <small id="chat-status-linha">WhatsApp</small>
      </div>
      <button class="chat-ico-btn" type="button" id="chat-cfg" title="Configurações" aria-label="Configurações"><?= icon('gear') ?></button>
      <button class="chat-ico-btn" type="button" id="chat-sync" title="Sincronizar" aria-label="Sincronizar"><?= icon('refresh') ?></button>
      <button class="chat-ico-btn" type="button" id="chat-full" title="Tela cheia" aria-label="Tela cheia" aria-pressed="false"><?= icon('expand') ?></button>
      <button class="chat-x" type="button" data-chat-fechar aria-label="Fechar chat">×</button>
    </header>
    <div class="chat-busca"><input id="chat-busca" type="search" placeholder="Buscar conversa" autocomplete="off"></div>
    <div class="chat-rolagem" id="chat-conversas"></div>
  </section>
  <section class="chat-fio" aria-label="Mensagens">
    <header class="chat-fio-h">
      <button class="chat-voltar" type="button" id="chat-voltar" aria-label="Voltar às conversas">‹</button>
      <span class="chat-avatar" id="chat-avatar" data-letra=""><span id="chat-letra"> </span></span>
      <div class="chat-quem">
        <strong id="chat-nome">Selecione uma conversa</strong>
        <small id="chat-fone"></small>
      </div>
      <button class="chat-btn-ia" type="button" id="chat-pausar-ia" hidden title="Pausar ou retomar a automação desta conversa">Pausar IA</button>
      <button class="chat-ico-btn chat-full-fio" type="button" id="chat-full-2" title="Tela cheia" aria-label="Tela cheia"><?= icon('expand') ?></button>
      <button class="chat-x" type="button" data-chat-fechar aria-label="Fechar chat">×</button>
    </header>
    <div class="chat-msgs" id="chat-msgs">
      <p class="chat-vazio">Escolha uma conversa à esquerda.</p>
    </div>
    <form class="chat-form" id="chat-form">
      <input id="chat-texto" type="text" maxlength="4000" placeholder="Mensagem" autocomplete="off">
      <button class="chat-enviar" type="submit" aria-label="Enviar"><?= icon('send') ?></button>
    </form>
  </section>
  <section class="chat-cfg" id="chat-cfg-painel" hidden aria-label="Configurações do WhatsApp">
    <header class="chat-cfg-h">
      <button class="chat-voltar" type="button" id="chat-cfg-voltar" aria-label="Voltar">‹</button>
      <strong>Configurações</strong>
      <button class="chat-x" type="button" data-chat-fechar aria-label="Fechar chat">×</button>
    </header>
    <form class="chat-cfg-form" id="chat-cfg-form">
      <p class="chat-cfg-lead">O chat do painel é para acompanhar. Depois de comunicar uma reserva, a IA do FLN responde só sobre aquela reserva.</p>

      <fieldset class="chat-cfg-bloco">
        <legend>Lista e atendimento</legend>
        <label class="chat-switch">
          <input type="checkbox" name="ignorar_grupos" id="cfg-ignorar-grupos" value="1" checked>
          <span>
            <strong>Ignorar grupos</strong>
            <small>Esconde grupos na lista e manda a API/IA ignorar mensagens de grupo.</small>
          </span>
        </label>
      </fieldset>

      <fieldset class="chat-cfg-bloco">
        <legend>Presença no WhatsApp</legend>
        <label class="chat-switch">
          <input type="checkbox" name="mostrar_digitando" id="cfg-mostrar-digitando" value="1" checked>
          <span>
            <strong>Mostrar quando estiver digitando</strong>
            <small>O painel avisa quando o WhatsApp conectado está digitando, e a resposta automática também aparece como digitando.</small>
          </span>
        </label>
        <label class="chat-switch">
          <input type="checkbox" name="online_ativo" id="cfg-online-ativo" value="1" checked>
          <span>
            <strong>Ficar online no horário do painel</strong>
            <small>A conta conectada fica online só dentro deste intervalo. Fora dele, fica indisponível.</small>
          </span>
        </label>
        <div class="chat-cfg-grid">
          <label class="chat-field">
            Online das
            <input type="time" name="online_inicio" id="cfg-online-inicio" value="06:00">
          </label>
          <label class="chat-field">
            até
            <input type="time" name="online_fim" id="cfg-online-fim" value="22:00">
          </label>
        </div>
      </fieldset>

      <fieldset class="chat-cfg-bloco">
        <legend>Inteligência artificial</legend>
        <label class="chat-switch">
          <input type="checkbox" name="ia_ativa" id="cfg-ia-ativa" value="1" checked>
          <span>
            <strong>IA de atendimento (OpenAI)</strong>
            <small>Com a chave OpenAI, responde só com a ficha da reserva que o Comunicar enviou. A ficha vira “corrida já concluída” quando o status muda para concluída.</small>
          </span>
        </label>
        <label class="chat-field">
          Chave OpenAI
          <input type="password" name="openai_key" id="cfg-openai" maxlength="512" autocomplete="off" placeholder="sk-… (deixe em branco para manter)">
          <small id="cfg-openai-hint">Obrigatória para a IA responder após o Comunicar. Também aceita OPENAI_API_KEY no servidor. Sem chave, só os botões do card funcionam.</small>
        </label>
        <label class="chat-field">
          Palavra para pausar a IA
          <input type="text" name="ia_parar" id="cfg-ia-parar" maxlength="40" value="parar">
          <small>Cliente ou equipe digita no WhatsApp para pausar a automação na hora.</small>
        </label>
        <div class="chat-cfg-grid">
          <label class="chat-field">
            Pausar por (min)
            <input type="number" name="ia_parar_min" id="cfg-ia-parar-min" min="0" max="1440" value="30">
          </label>
          <label class="chat-field">
            Pausar se você responder (min)
            <input type="number" name="ia_pausar_manual" id="cfg-ia-pausar-manual" min="0" max="1440" value="5">
          </label>
        </div>
        <label class="chat-switch">
          <input type="checkbox" name="avaliacao_auto" id="cfg-avaliacao-auto" value="1" checked>
          <span>
            <strong>Pedir avaliação ao concluir</strong>
            <small>Quando a reserva vai para concluída, manda um card de nota no WhatsApp de quem recebeu o Comunicar. Desmarque para não enviar.</small>
          </span>
        </label>
      </fieldset>

      <?php if (($role ?? '') === 'admin' && function_exists('ia_operador_catalogo')): ?>
      <fieldset class="chat-cfg-bloco">
        <legend>Canal exclusivo do atendente</legend>
        <p class="chat-cfg-aviso" style="position:static">Trava em 3 camadas: (1) só o WhatsApp do atendente; (2) ID + token exclusivos enviados juntos nesta conversa; (3) só as ações marcadas abaixo. Com a sessão liberada, a IA executa sempre o que ele pedir (inclusive criar reserva). Cliente e motorista nunca entram neste canal. A resposta da IA nunca devolve o token.</p>
        <label class="chat-field">
          ID exclusivo
          <input type="text" id="cfg-operador-id" readonly autocomplete="off" value="">
        </label>
        <label class="chat-field">
          Token exclusivo
          <input type="text" id="cfg-operador-token" readonly autocomplete="off" value="">
          <small>Copie os dois e envie uma vez no WhatsApp do atendente. A resposta da IA não devolve o token.</small>
        </label>
        <?php foreach (ia_operador_catalogo() as $chavePerm => $rotuloPerm): ?>
        <label class="chat-switch">
          <input type="checkbox" class="cfg-ia-perm" value="<?= e($chavePerm) ?>" <?= !empty(ia_operador_permissoes($db)[$chavePerm]) ? 'checked' : '' ?>>
          <span><strong><?= e($rotuloPerm) ?></strong></span>
        </label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>

      <p class="chat-cfg-aviso" id="chat-cfg-aviso" hidden></p>
      <div class="chat-cfg-acoes">
        <a class="chat-link" href="<?= e(href('comunicador')) ?>">Abrir Comunicador</a>
        <button class="chat-cfg-salvar" type="submit">Salvar</button>
      </div>
    </form>
  </section>
</aside>
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css?v=1">
<script src="assets/vendor/leaflet/leaflet.js?v=1"></script>
<script src="assets/js/chat.js?v=17"></script>
<script src="assets/js/mapa.js?v=4"></script>
<script src="assets/js/push.js?v=5"></script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
