<?php
declare(strict_types=1);
defined('APP') || exit;
$s = $db['settings'];
view_head('', 'Os campos ficam bloqueados. Clique em Editar no cartão para liberar a alteração.');
?>
<section class="card cfg-card">
  <header class="card-h"><h2>Logo da conta</h2><button class="btn btn-sm" type="button" data-cfg-edit>Editar</button></header>
  <form method="post" action="index.php" enctype="multipart/form-data" class="form-grid">
    <?= csrf_fields('save_conta') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Nome fantasia <input name="nome" required maxlength="80" value="<?= e($s['nome']) ?>" placeholder="Transfer FLN"></label>
      <label class="field">Razão social <input name="razao" maxlength="120" value="<?= e($s['razao']) ?>" placeholder="Fln Transfer Executivo e Turismo"></label>
      <label class="field">CNPJ <input name="cnpj" maxlength="20" value="<?= e($s['cnpj']) ?>" placeholder="45.827.819/0001-08"></label>
      <label class="field">Representante legal <input name="rep_legal" maxlength="80" value="<?= e($s['rep_legal'] ?? '') ?>" placeholder="Matheus Blum"></label>
      <label class="field">Telefone <input name="telefone" maxlength="30" value="<?= e($s['telefone']) ?>" placeholder="(48) 98822-2534"></label>
      <label class="field">WhatsApp <input name="whatsapp" maxlength="30" value="<?= e($s['whatsapp'] ?? '') ?>" placeholder="(48) 98822-2534"></label>
      <label class="field">E-mail <input type="email" name="email" maxlength="80" value="<?= e($s['email']) ?>" placeholder="financeiroflnsc@gmail.com"></label>
      <label class="field">Cidade <input name="cidade" maxlength="60" value="<?= e($s['cidade']) ?>" placeholder="Florianópolis"></label>
      <label class="field">UF <input name="uf" maxlength="2" value="<?= e($s['uf']) ?>" placeholder="SC"></label>
      <label class="field full">Endereço <input name="endereco" maxlength="160" value="<?= e($s['endereco']) ?>" placeholder="Servidão Estação Luiz Cline, Florianópolis - SC"></label>
      <label class="field">Logo <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
      <label class="field">Logo lateral <input type="file" name="logo_lateral" accept="image/png,image/jpeg,image/webp"></label>
      <label class="field">Favicon <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar dados</button></div>
    </fieldset>
  </form>
  <?php if (!empty($s['logo'])): ?><p class="hint">Logo atual carregada.</p><?php endif; ?>
</section>
<section class="card cfg-card" style="margin-top:12px">
  <header class="card-h"><h2>Comprovante</h2><button class="btn btn-sm" type="button" data-cfg-edit>Editar</button></header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_comprovante') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field full">Informação principal <textarea name="comp_principal" maxlength="2000" placeholder="Fln Transfer Executivo e Turismo&#10;CNPJ 45.827.819/0001-08"><?= e($s['comp_principal'] ?? '') ?></textarea></label>
      <label class="field full">Rodapé <textarea name="comp_rodape" maxlength="2000" placeholder="Obrigado por viajar com a gente."><?= e($s['comp_rodape'] ?? '') ?></textarea></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar comprovante</button></div>
    </fieldset>
  </form>
</section>
<section class="card cfg-card" style="margin-top:12px">
  <header class="card-h"><h2>Assinatura eletrônica</h2><button class="btn btn-sm" type="button" data-cfg-edit>Editar</button></header>
  <form method="post" action="index.php" enctype="multipart/form-data" class="form-grid">
    <?= csrf_fields('save_assinatura') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Nome na assinatura <input name="assinatura_nome" maxlength="80" value="<?= e($s['assinatura_nome'] ?? '') ?>" placeholder="Matheus Blum"></label>
      <label class="field">Imagem da assinatura <input type="file" name="assinatura" accept="image/png,image/jpeg,image/webp"></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar assinatura</button></div>
    </fieldset>
  </form>
  <?php if (!empty($s['assinatura'])): ?><p class="hint">Assinatura ativa no contrato e no PDF da reserva.</p><?php endif; ?>
</section>
<section class="card cfg-card" style="margin-top:12px">
  <header class="card-h"><h2>Pagamentos</h2><button class="btn btn-sm" type="button" data-cfg-edit>Editar</button></header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_pagamentos') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Chave Pix <input name="pix" maxlength="80" value="<?= e($s['pix']) ?>" placeholder="financeiroflnsc@gmail.com"></label>
      <label class="field">Cidade do recebedor <input name="pix_cidade" maxlength="60" value="<?= e($s['pix_cidade'] ?? '') ?>" placeholder="Florianópolis"></label>
      <label class="field full">Token PagBank <input name="pagbank_token" maxlength="200" value="<?= e($s['pagbank_token'] ?? '') ?>" placeholder="cole o token PagBank" autocomplete="off"></label>
      <label class="field full checkline"><input type="checkbox" name="pagbank_ativo" value="1"<?= !empty($s['pagbank_ativo']) ? ' checked' : '' ?>> PagBank ativo</label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar pagamentos</button></div>
    </fieldset>
  </form>
</section>
<section class="card cfg-card" style="margin-top:12px">
  <header class="card-h"><h2>Operação e WhatsApp</h2><button class="btn btn-sm" type="button" data-cfg-edit>Editar</button></header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_operacao') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Antecedência mínima (horas) <input type="number" name="antecedencia" min="0" max="72" value="<?= e($s['antecedencia']) ?>" placeholder="2"></label>
      <label class="field full checkline"><input type="checkbox" name="modo_simples" value="1"<?= !empty($s['modo_simples']) ? ' checked' : '' ?>> Modo simples</label>
      <label class="field">Usuário do administrador <input name="usuario" required maxlength="40" value="<?= e($s['usuario'] ?? 'admin') ?>" placeholder="admin" autocomplete="off"></label>
      <label class="field">Senha do administrador <input name="senha" maxlength="40" value="<?= e($s['senha'] ?? '') ?>" placeholder="admin123" autocomplete="off"></label>
      <label class="field full">Token permanente da API (Seus Tokens) <input name="uazapi_admin_token" maxlength="512" value="<?= e($s['uazapi_admin_token'] ?? '') ?>" placeholder="token permanente uazapiGO" autocomplete="off"></label>
      <label class="field full">URL create-instance (opcional) <input name="uazapi_create_url" maxlength="220" value="<?= e($s['uazapi_create_url'] ?? '') ?>" placeholder="https://….supabase.co/functions/v1/create-instance-url"></label>
      <label class="field full">URL da instância <input name="uazapi_url" maxlength="160" value="<?= e($s['uazapi_url'] ?? '') ?>" placeholder="preenchido ao gerar QR no Comunicador" readonly></label>
      <label class="field full">Token da instância <input name="uazapi_token" maxlength="4096" value="<?= e($s['uazapi_token'] ?? '') ?>" placeholder="JWT gerado automaticamente" autocomplete="off" readonly></label>
      <label class="field full checkline"><input type="checkbox" name="whatsapp_ativo" value="1"<?= !empty($s['whatsapp_ativo']) ? ' checked' : '' ?>> Enviar agendamento no WhatsApp do motorista, 1 dia antes e no dia</label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar operação</button></div>
    </fieldset>
  </form>
</section>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Zona de perigo</h2></header>
  <p class="help">Restaura a operação de demonstração desta sessão. Ajustes feitos aqui são substituídos.</p>
  <form method="post" action="index.php" style="margin-top:12px" data-confirm="Restaurar os dados de demonstração? As reservas e ajustes desta sessão serão substituídos.">
    <?= csrf_fields('reset_demo') ?>
    <button class="btn btn-danger" type="submit">Restaurar demonstração</button>
  </form>
</section>
