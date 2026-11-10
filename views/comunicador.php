<?php
declare(strict_types=1);
defined('APP') || exit;
$s = $db['settings'];
$status = (string) ($s['wa_status'] ?? 'desconectado');
$inst = (string) ($s['wa_instance_status'] ?? '');
$adminOk = uazapi_admin_ready($s);
$instOk = uazapi_settings_ready($s);
$statusLabel = uazapi_status_label($status, $inst);
$profile = trim((string) ($s['wa_profile_name'] ?? ''));
$instName = trim((string) ($s['uazapi_instance_name'] ?? ''));
$auto = static function (string $id) use ($db): bool {
    return auto_on($db, $id);
};
$openaiOk = trim((string) ($s['chat_openai_key'] ?? '')) !== '';
$pushN = count(array_filter($db['push'] ?? [], 'is_array'));
view_head();
?>
<section class="card connect-card" data-wa-poll="<?= $status === 'aguardando' && $instOk ? '1' : '0' ?>">
  <h2>Comunicador WhatsApp</h2>
  <p class="wa-status-line">
    <span class="wa-badge wa-<?= e($status) ?>"><?= e($statusLabel) ?></span>
    <?php if ($instName !== ''): ?><span class="wa-profile">· <?= e($instName) ?></span><?php endif; ?>
    <?php if ($profile !== '' && $status === 'conectado'): ?>
      <span class="wa-profile">· <?= e($profile) ?></span>
    <?php endif; ?>
  </p>
  <?php if (!$adminOk): ?>
    <p class="help">Cole o <strong>token permanente</strong> (Seus Tokens) em <a href="<?= e(href('configuracoes')) ?>">Configurações → Operação e WhatsApp</a>. Ao gerar o QR Code, o sistema cria a instância via <code>create-instance-url</code> e conecta com <code>POST /instance/connect</code>.</p>
  <?php elseif ($status === 'conectado'): ?>
    <p>WhatsApp conectado. O chat na borda direita é para acompanhar. A IA por texto só responde depois de configurar a chave OpenAI no chat.</p>
    <?php if ($instOk): ?><p class="hint">Servidor: <?= e((string) ($s['uazapi_url'] ?? '')) ?></p><?php endif; ?>
  <?php else: ?>
    <p>Toque em <strong>Gerar QR Code</strong>: o painel cria uma instância nova na API, abre o pareamento e mostra o QR. Escaneie em <strong>WhatsApp → Aparelhos conectados → Conectar aparelho</strong>.</p>
  <?php endif; ?>
  <?php if (!empty($s['wa_qr']) && $status !== 'conectado'): ?>
    <img class="qr" width="240" height="240" alt="QR Code para conectar o WhatsApp" src="<?= e($s['wa_qr']) ?>">
    <p class="hint">O código expira em alguns minutos. Use Atualizar status ou gere outro QR se necessário.</p>
  <?php endif; ?>
  <?php if (!empty($s['wa_paircode']) && $status !== 'conectado'): ?>
    <p class="wa-pair">Código de pareamento: <strong><?= e($s['wa_paircode']) ?></strong></p>
  <?php endif; ?>
  <?php if (!empty($s['wa_erro'])): ?><p class="flash err" role="alert"><?= e($s['wa_erro']) ?></p><?php endif; ?>
  <div class="form-actions wa-actions">
    <form method="post" action="index.php" id="wa-connect-form">
      <?= csrf_fields('conectar_whatsapp') ?>
      <button class="btn btn-primary" type="submit"<?= $adminOk ? '' : ' disabled' ?>>Gerar QR Code</button>
    </form>
    <form method="post" action="index.php" id="wa-status-form">
      <?= csrf_fields('status_whatsapp') ?>
      <button class="btn" type="submit"<?= $instOk ? '' : ' disabled' ?>>Atualizar status</button>
    </form>
    <form method="post" action="index.php" data-confirm="Excluir a instância e apagar as conversas deste comunicador?">
      <?= csrf_fields('excluir_instancia_whatsapp') ?>
      <button class="btn btn-danger" type="submit"<?= ($adminOk || $instOk) ? '' : ' disabled' ?>>Excluir instância</button>
    </form>
  </div>
</section>

<section class="card cfg-card" style="margin-top:14px">
  <header class="card-h">
    <div>
      <h2>Automações de aviso (WhatsApp)</h2>
      <p class="cfg-lead">Controla o que sai sozinho. Cliente só recebe quando você comunica manualmente na reserva.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_comunicador_autos') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="cfg-switch full">
        <input type="checkbox" name="whatsapp_criacao" value="1"<?= $auto('whatsapp_criacao') ? ' checked' : '' ?>>
        <span>Ao criar a reserva → motoristas atribuídos<small>Envia o card (ciência / PDF / atendente) só para os motoristas daquela reserva. Não envia ao cliente. Não duplica se já foi enviado com sucesso.</small></span>
      </label>
      <label class="cfg-switch full">
        <input type="checkbox" name="whatsapp_d1" value="1"<?= $auto('whatsapp_d1') ? ' checked' : '' ?>>
        <span>1 dia antes → motoristas<small>Lembrete automático na véspera, apenas aos motoristas do trecho.</small></span>
      </label>
      <label class="cfg-switch full">
        <input type="checkbox" name="whatsapp_dia" value="1"<?= $auto('whatsapp_dia') ? ' checked' : '' ?>>
        <span>No dia → motoristas<small>Lembrete no dia da viagem (pula se o horário já passou).</small></span>
      </label>
      <p class="hint full">Cliente: somente envio manual no botão Comunicar da reserva. Chatbot de texto: <?= $openaiOk ? 'chave OpenAI ok' : 'desligado até cadastrar a chave OpenAI no chat' ?>.</p>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar automações</button></div>
    </fieldset>
  </form>
</section>

<section class="card" style="margin-top:14px" id="avisos-painel">
  <header class="card-h">
    <div>
      <h2>Notificações do painel (celular / APK / Chrome)</h2>
      <p class="cfg-lead">Aviso de nova reserva, solicitação e cancelamento no aparelho do administrador — mesmo com o app fechado, se este aparelho estiver ativado.</p>
    </div>
  </header>
  <p class="hint"><?= (int) $pushN ?> aparelho(s) inscrito(s). Ative em <a href="<?= e(href('configuracoes')) ?>#notificacoes">Configurações → Notificações</a>.</p>
  <ol class="cfg-passos">
    <li><strong>Chrome no celular.</strong> Abra o painel em https, ative as notificações e permita o aviso. Funciona com a tela bloqueada.</li>
    <li><strong>APK Transfer FLN.</strong> Abra o APK, entre como admin e ative as notificações. O app nativo mostra o aviso mesmo em segundo plano; com o processo morto o worker agenda a checagem periódica.</li>
    <li><strong>Não multiplica motorista.</strong> Push é só para aparelhos do administrador. WhatsApp automático é só para motoristas atribuídos.</li>
  </ol>
</section>
