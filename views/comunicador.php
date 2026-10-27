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
$msgs = $db['mensagens'];
usort($msgs, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
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
    <p>WhatsApp conectado. Os agendamentos podem ser enviados ao motorista 1 dia antes e no dia, se a opção estiver ativa em Configurações.</p>
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
    <form method="post" action="index.php" data-confirm="Excluir a instância WhatsApp deste painel e na UAZAPI? O número será desconectado.">
      <?= csrf_fields('excluir_instancia_whatsapp') ?>
      <button class="btn btn-danger" type="submit"<?= ($adminOk || $instOk) ? '' : ' disabled' ?>>Excluir instância</button>
    </form>
  </div>
</section>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Disparos da agenda</h2></header>
  <p class="help">Com a instância conectada, o sistema envia o texto da viagem ao telefone do motorista (<code>POST /send/text</code>).</p>
  <form method="post" action="index.php" style="margin:12px 0">
    <?= csrf_fields('disparar_whatsapp') ?>
    <button class="btn" type="submit"<?= $instOk && $status === 'conectado' ? '' : ' disabled' ?>>Disparar WhatsApp de hoje e de amanhã</button>
  </form>
  <?php if ($msgs === []): ?>
    <?= empty_box('monitor', 'Nenhum disparo registrado', 'Atribua um motorista a uma viagem de hoje ou de amanhã e dispare de novo.') ?>
  <?php else: ?>
    <?php foreach (array_slice($msgs, 0, 12) as $msg): ?>
      <article class="msg">
        <strong><?= e($msg['titulo']) ?></strong>
        <time><?= e($msg['quando']) ?> · <?= e($msg['para']) ?> · viagem #<?= (int) $msg['viagem'] ?></time>
        <p style="margin-top:8px"><?= e($msg['texto']) ?></p>
        <?php if (!empty($msg['status'])): ?><p class="hint"><?= e($msg['status']) ?><?= !empty($msg['detalhe']) ? ' · ' . e($msg['detalhe']) : '' ?></p><?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php if ($status === 'aguardando' && $instOk): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('wa-status-form');
  if (!form) return;
  window.setInterval(() => form.requestSubmit(), 12000);
});
</script>
<?php endif; ?>
