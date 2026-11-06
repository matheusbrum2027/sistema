<?php
declare(strict_types=1);
defined('APP') || exit;
$s = $db['settings'];
$assNome = trim((string) ($s['assinatura_nome'] ?? ''));
if ($assNome === '') {
    $assNome = trim((string) ($s['rep_legal'] ?? 'Matheus Brum'));
}
view_head('', 'O contrato abaixo entra no PDF de confirmação da reserva. Toque em Editar para alterar os textos.');
?>
<form id="form-contrato" method="post" action="index.php" class="cfg-card">
  <?= csrf_fields('save_contrato') ?>
  <input type="hidden" name="editando" value="0">
  <section class="card">
    <header class="card-h">
      <h2>Contrato no PDF de confirmação da reserva</h2>
      <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
    </header>
    <p class="cfg-lead">Cabeçalho, logo e assinatura vêm automaticamente das Configurações. Aqui você só edita os textos jurídicos.</p>
  </section>
  <section class="card sheet contrato-preview reserva-folha" style="margin-top:12px;padding:0;overflow:hidden">
    <?php reserva_cabecalho_empresa($s, 'Prévia do contrato', 'Transfer › Contrato'); ?>
    <div style="padding:0 0 16px">
      <?php reserva_bloco_contrato('Contrato', (string) ($s['contrato_modelo'] ?? '')); ?>
      <?php if (trim((string) ($s['contrato_cancelamento'] ?? '')) !== ''): ?>
        <?php reserva_bloco_contrato('Cancelamento', (string) $s['contrato_cancelamento']); ?>
      <?php endif; ?>
      <?php if (trim((string) ($s['contrato_clausulas'] ?? '')) !== ''): ?>
        <?php reserva_bloco_contrato('Cláusulas', (string) $s['contrato_clausulas']); ?>
      <?php endif; ?>
      <footer class="doc-sign">
        <div class="doc-sign-box"><p>Contratante</p><div class="doc-sign-space"></div></div>
        <div class="doc-sign-box">
          <p>Contratada</p>
          <?php if (!empty($s['assinatura'])): ?>
            <img src="<?= e((string) $s['assinatura']) ?>" alt="Assinatura digital" class="doc-sign-img">
          <?php else: ?>
            <div class="doc-sign-space"></div>
          <?php endif; ?>
          <strong><?= e($assNome) ?></strong>
          <span><?= e((string) ($s['razao'] ?? '')) ?></span>
        </div>
      </footer>
    </div>
  </section>
  <section class="card" style="margin-top:12px">
    <header class="card-h"><h2>Textos do contrato</h2></header>
    <fieldset disabled>
      <label class="checkline"><input type="checkbox" name="contrato_pdf" value="1"<?= !empty($s['contrato_pdf']) ? ' checked' : '' ?>> Incluir no PDF da reserva (com logo e assinatura automáticas)</label>
      <label class="field" style="margin-top:10px">Modelo de contrato <textarea name="contrato_modelo" maxlength="4000" placeholder="1. Das partes..."><?= e($s['contrato_modelo'] ?? '') ?></textarea></label>
      <label class="field" style="margin-top:10px">Política de cancelamento <textarea name="contrato_cancelamento" maxlength="4000" placeholder="Cancelamento com mais de 72 horas: reembolso integral."><?= e($s['contrato_cancelamento'] ?? '') ?></textarea></label>
      <label class="field" style="margin-top:10px">Cláusulas adicionais <textarea name="contrato_clausulas" maxlength="4000" placeholder="Foro da comarca de Florianópolis."><?= e($s['contrato_clausulas'] ?? '') ?></textarea></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Salvar contrato</button></div>
    </fieldset>
  </section>
</form>
