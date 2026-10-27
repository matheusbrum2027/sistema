<?php
declare(strict_types=1);
defined('APP') || exit;
$s = $db['settings'];
view_head('<button class="btn btn-primary" type="submit" form="form-contrato">Salvar contrato</button>', 'O contrato abaixo entra no PDF de confirmação da reserva.');
?>
<form id="form-contrato" method="post" action="index.php">
  <?= csrf_fields('save_contrato') ?>
  <section class="card">
    <header class="card-h">
      <h2>Contrato no PDF de confirmação da reserva</h2>
      <label class="checkline"><input type="checkbox" name="contrato_pdf" value="1"<?= !empty($s['contrato_pdf']) ? ' checked' : '' ?>> Incluir</label>
    </header>
  </section>
  <section class="card sheet" style="margin-top:12px">
    <header class="sheet-top">
      <div>
        <?php if (!empty($s['logo'])): ?><img src="<?= e($s['logo']) ?>" alt="" class="sheet-logo"><?php endif; ?>
        <strong><?= e($s['razao']) ?></strong>
        <p>CNPJ <?= e($s['cnpj']) ?><br><?= e($s['endereco']) ?><br>Rep. legal: <?= e($s['rep_legal'] ?? '') ?><br><?= e($s['email']) ?> · <?= e($s['telefone']) ?></p>
      </div>
      <div>
        <strong>Contrato de Prestação de Serviço</strong>
        <p>Gerado em <?= e(today()->format('d/m/Y')) ?></p>
      </div>
    </header>
    <h3>Contrato</h3>
    <pre><?= e($s['contrato_modelo'] ?? '') ?></pre>
    <pre><?= e($s['contrato_cancelamento'] ?? '') ?></pre>
    <pre><?= e($s['contrato_clausulas'] ?? '') ?></pre>
    <div class="sign">
      <div><span></span>Contratante</div>
      <div><span></span>Contratada<?= !empty($s['assinatura']) ? '<img src="' . e($s['assinatura']) . '" alt="" class="sign-img">' : '' ?></div>
    </div>
  </section>
  <section class="card" style="margin-top:12px">
    <header class="card-h"><h2>Editar textos do contrato</h2></header>
    <label class="field">Modelo de contrato <textarea name="contrato_modelo" maxlength="4000" placeholder="1. Das partes..."><?= e($s['contrato_modelo'] ?? '') ?></textarea></label>
    <label class="field" style="margin-top:10px">Política de cancelamento <textarea name="contrato_cancelamento" maxlength="4000" placeholder="Cancelamento com mais de 72 horas: reembolso integral."><?= e($s['contrato_cancelamento'] ?? '') ?></textarea></label>
    <label class="field" style="margin-top:10px">Cláusulas adicionais <textarea name="contrato_clausulas" maxlength="4000" placeholder="Foro da comarca de Florianópolis."><?= e($s['contrato_clausulas'] ?? '') ?></textarea></label>
  </section>
</form>
