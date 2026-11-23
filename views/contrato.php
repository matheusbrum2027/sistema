<?php
declare(strict_types=1);
defined('APP') || exit;
$s = $db['settings'];
$assNome = trim((string) ($s['assinatura_nome'] ?? ''));
if ($assNome === '') {
    $assNome = trim((string) ($s['rep_legal'] ?? 'Matheus Brum'));
}
view_head('', 'Os textos abaixo entram no PDF da reserva. Toque em Editar, altere o bloco e salve.');
?>
<form id="form-contrato" method="post" action="index.php" class="cfg-card">
  <?= csrf_fields('save_contrato') ?>
  <input type="hidden" name="editando" value="0">
  <section class="card contrato-topo">
    <header class="card-h">
      <div>
        <h2>Contrato no PDF da reserva</h2>
        <p class="cfg-lead">Logo, CNPJ, endereço e assinatura vêm das Configurações. Aqui você altera só os textos. A prévia de baixo mostra o resultado, ela não é o campo de edição.</p>
      </div>
      <button class="btn btn-primary btn-sm" type="button" data-cfg-edit data-cfg-focus="contrato_modelo">Editar textos</button>
    </header>
    <p class="contrato-estado" data-contrato-estado>Bloqueado. Toque em Editar textos para alterar.</p>
  </section>

  <fieldset class="contrato-editor" disabled>
    <section class="card contrato-campo">
      <header class="contrato-campo-h">
        <h3>Incluir no PDF</h3>
        <p>Desmarcado, a reserva sai sem estas páginas. O valor da corrida continua nas regras da reserva.</p>
      </header>
      <label class="checkline"><input type="checkbox" name="contrato_pdf" value="1"<?= !empty($s['contrato_pdf']) ? ' checked' : '' ?>> Incluir contrato, cancelamento e cláusulas no PDF da reserva</label>
    </section>

    <section class="card contrato-campo" id="campo-contrato-modelo">
      <header class="contrato-campo-h">
        <h3>1. Modelo do contrato</h3>
        <p>Uma seção por bloco. A primeira linha é o título (<code>1. DAS PARTES</code>). As seguintes são os itens (<code>1.1. texto</code>).</p>
      </header>
      <label class="field">Texto que vira as seções numeradas
        <textarea name="contrato_modelo" maxlength="4000" rows="12"><?= e((string) ($s['contrato_modelo'] ?? '')) ?></textarea>
      </label>
    </section>

    <section class="card contrato-campo" id="campo-contrato-cancelamento">
      <header class="contrato-campo-h">
        <h3>2. Política de cancelamento</h3>
        <p>Uma regra por linha. O selo colorido sai sozinho quando a linha fala em 72 horas, 48 horas, no-show ou força maior.</p>
      </header>
      <label class="field">Regras de cancelamento
        <textarea name="contrato_cancelamento" maxlength="4000" rows="8"><?= e((string) ($s['contrato_cancelamento'] ?? '')) ?></textarea>
      </label>
    </section>

    <section class="card contrato-campo" id="campo-contrato-clausulas">
      <header class="contrato-campo-h">
        <h3>3. Cláusulas adicionais</h3>
        <p>Numere como <code>8.1.</code>, <code>8.2.</code> e assim por diante. Cada número vira um cartão na prévia e no PDF.</p>
      </header>
      <label class="field">Cláusulas
        <textarea name="contrato_clausulas" maxlength="4000" rows="8"><?= e((string) ($s['contrato_clausulas'] ?? '')) ?></textarea>
      </label>
    </section>
  </fieldset>

  <div class="contrato-salvar">
    <p>Salvar grava os três textos e a opção de incluir no PDF.</p>
    <button class="btn btn-primary" type="submit" disabled data-cfg-save>Salvar contrato</button>
  </div>

  <section class="card sheet contrato-preview reserva-folha">
    <header class="contrato-previa-h">
      <div>
        <h2>Prévia do PDF</h2>
        <p>Isto é só a leitura. Para mudar um trecho, use Editar neste bloco.</p>
      </div>
    </header>
    <?php reserva_cabecalho_empresa($s, 'Prévia do contrato', 'Transfer › Contrato'); ?>
    <div class="contrato-previa-corpo">
      <div class="contrato-previa-sec">
        <button class="btn btn-sm" type="button" data-cfg-edit data-cfg-focus="contrato_modelo">Editar modelo</button>
        <?php reserva_bloco_contrato('Contrato', (string) ($s['contrato_modelo'] ?? '')); ?>
      </div>
      <?php if (trim((string) ($s['contrato_cancelamento'] ?? '')) !== ''): ?>
      <div class="contrato-previa-sec">
        <button class="btn btn-sm" type="button" data-cfg-edit data-cfg-focus="contrato_cancelamento">Editar cancelamento</button>
        <?php reserva_bloco_contrato('Cancelamento', (string) $s['contrato_cancelamento']); ?>
      </div>
      <?php endif; ?>
      <?php if (trim((string) ($s['contrato_clausulas'] ?? '')) !== ''): ?>
      <div class="contrato-previa-sec">
        <button class="btn btn-sm" type="button" data-cfg-edit data-cfg-focus="contrato_clausulas">Editar cláusulas</button>
        <?php reserva_bloco_contrato('Cláusulas', (string) $s['contrato_clausulas']); ?>
      </div>
      <?php endif; ?>
      <footer class="doc-sign">
        <p>Contratante</p>
        <p>Contratada</p>
        <div class="doc-sign-line"></div>
        <div class="doc-sign-line">
          <?php if (!empty($s['assinatura']) || !empty($s['tem_assinatura'])): ?>
            <img src="<?= !empty($s['assinatura']) ? e(assinatura_src_aparada((string) $s['assinatura'])) : e('index.php?p=marca&k=assinatura&v=' . (int) ($s['marca_v'] ?? 1)) ?>" alt="Assinatura digital" class="doc-sign-img">
          <?php endif; ?>
        </div>
        <strong></strong>
        <strong><?= e($assNome) ?></strong>
        <span></span>
        <span><?= e((string) ($s['razao'] ?? '')) ?></span>
      </footer>
    </div>
  </section>
</form>
