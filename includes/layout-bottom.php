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
<script src="assets/js/app.js?v=18"></script>
<?php if (($_SESSION['role'] ?? '') === 'admin' && !empty($_SESSION['auth']) && empty($bare)): ?>
<div class="chat-borda" id="chat-borda">
  <button class="chat-aba" type="button" id="chat-abrir" aria-controls="chat-painel" aria-expanded="false" title="WhatsApp">
    <img class="chat-aba-img" src="assets/whatsapp-aba.png" width="44" height="44" alt="" decoding="async">
    <span class="chat-aba-txt">Ver o chat</span>
    <span class="chat-aba-badge" id="chat-aba-badge" hidden></span>
  </button>
</div>
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
        <legend>Inteligência artificial</legend>
        <label class="chat-switch">
          <input type="checkbox" name="ia_ativa" id="cfg-ia-ativa" value="1">
          <span>
            <strong>Chatbot genérico da UAZAPI</strong>
            <small>Deixe desligado. O atendimento pós-comunicação usa a IA do painel (só a reserva comunicada).</small>
          </span>
        </label>
        <label class="chat-field">
          Chave OpenAI
          <input type="password" name="openai_key" id="cfg-openai" maxlength="200" autocomplete="off" placeholder="sk-… (deixe em branco para manter)">
          <small id="cfg-openai-hint">Usada pela IA do FLN nas respostas pós-card (somente dados da reserva comunicada).</small>
        </label>
        <label class="chat-field">
          Palavra para pausar a IA
          <input type="text" name="ia_parar" id="cfg-ia-parar" maxlength="40" value="parar">
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
      </fieldset>

      <p class="chat-cfg-aviso" id="chat-cfg-aviso" hidden></p>
      <div class="chat-cfg-acoes">
        <a class="chat-link" href="<?= e(href('comunicador')) ?>">Abrir Comunicador</a>
        <button class="chat-cfg-salvar" type="submit">Salvar</button>
      </div>
    </form>
  </section>
</aside>
<script src="assets/js/chat.js?v=8"></script>
<script src="assets/js/push.js?v=4"></script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
