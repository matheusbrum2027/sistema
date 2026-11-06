<?php
declare(strict_types=1);
defined('APP') || exit;
$s = $db['settings'];
view_head('', 'Cada bloco salva sozinho. Toque em Editar para liberar os campos.');
?>
<div class="cfg-page">
<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Dados da empresa</h2>
      <p class="cfg-lead">Nome, contato e endereço usados no contrato, no comprovante e no Pix.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" enctype="multipart/form-data" class="form-grid">
    <?= csrf_fields('save_conta') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Nome fantasia <input name="nome" required maxlength="80" value="<?= e($s['nome']) ?>" placeholder="Transfer FLN"></label>
      <label class="field">Razão social <input name="razao" maxlength="120" value="<?= e($s['razao']) ?>" placeholder="Fln Transfer Executivo e Turismo"></label>
      <label class="field">CNPJ <input name="cnpj" maxlength="20" value="<?= e($s['cnpj']) ?>" placeholder="41.581.147/0001-08"></label>
      <label class="field">Representante legal <input name="rep_legal" maxlength="80" value="<?= e($s['rep_legal'] ?? '') ?>" placeholder="Matheus Brum"></label>
      <label class="field">Telefone <input name="telefone" maxlength="30" value="<?= e($s['telefone']) ?>" placeholder="(48) 98822-2934"></label>
      <label class="field">WhatsApp <input name="whatsapp" maxlength="30" value="<?= e($s['whatsapp'] ?? '') ?>" placeholder="(48) 98822-2934"></label>
      <label class="field">E-mail <input type="email" name="email" maxlength="80" value="<?= e($s['email']) ?>" placeholder="flnexecutivoads@gmail.com"></label>
      <label class="field">Cidade <input name="cidade" maxlength="60" value="<?= e($s['cidade']) ?>" placeholder="Florianópolis"></label>
      <label class="field">UF <input name="uf" maxlength="2" value="<?= e($s['uf']) ?>" placeholder="SC"></label>
      <label class="field full">Endereço <input name="endereco" maxlength="160" value="<?= e($s['endereco']) ?>" placeholder="Servidão Estação Lua Clara, Rio Vermelho, Florianópolis - SC, 88060"></label>
      <p class="cfg-split">Identidade visual</p>
      <label class="field">Logo <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
      <label class="field">Logo lateral <input type="file" name="logo_lateral" accept="image/png,image/jpeg,image/webp"></label>
      <label class="field">Favicon <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar dados</button></div>
    </fieldset>
  </form>
  <?php if (!empty($s['logo'])): ?><p class="hint">Logo atual carregada.</p><?php endif; ?>
</section>

<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Comprovante</h2>
      <p class="cfg-lead">Texto do cabeçalho e do rodapé impressos no comprovante da reserva.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_comprovante') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field full">Informação principal <textarea name="comp_principal" maxlength="2000" placeholder="Fln Transfer Executivo e Turismo&#10;CNPJ 41.581.147/0001-08"><?= e($s['comp_principal'] ?? '') ?></textarea></label>
      <label class="field full">Rodapé <textarea name="comp_rodape" maxlength="2000" placeholder="Obrigado por viajar com a gente."><?= e($s['comp_rodape'] ?? '') ?></textarea></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar comprovante</button></div>
    </fieldset>
  </form>
</section>

<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Assinatura eletrônica</h2>
      <p class="cfg-lead">Nome e imagem usados no contrato e no PDF da reserva.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" enctype="multipart/form-data" class="form-grid">
    <?= csrf_fields('save_assinatura') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Nome na assinatura <input name="assinatura_nome" maxlength="80" value="<?= e($s['assinatura_nome'] ?? '') ?>" placeholder="Matheus Brum"></label>
      <label class="field">Imagem da assinatura <input type="file" name="assinatura" accept="image/png,image/jpeg,image/webp"></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar assinatura</button></div>
    </fieldset>
  </form>
  <?php if (!empty($s['assinatura'])): ?><p class="hint">Assinatura ativa no contrato e no PDF da reserva.</p><?php endif; ?>
</section>

<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Atendente WhatsApp (humano)</h2>
      <p class="cfg-lead">Quando o cliente ou o motorista pedir atendente no card da reserva, o sistema avisa esta pessoa no WhatsApp.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_atendente') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Nome do atendente <input name="atendente_nome" maxlength="80" value="<?= e($s['atendente_nome'] ?? ($s['assinatura_nome'] ?? 'Matheus Brum')) ?>" placeholder="Matheus Brum"></label>
      <label class="field">WhatsApp do atendente <input name="atendente_fone" maxlength="30" value="<?= e($s['atendente_fone'] ?? '') ?>" placeholder="(48) 99967-2557"></label>
      <p class="hint full">Celular pessoal do Matheus Brum para receber avisos de “falar com atendente”. Sem este número, o pedido não dispara.</p>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar atendente</button></div>
    </fieldset>
  </form>
</section>

<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Pagamentos</h2>
      <p class="cfg-lead">Chave Pix e, se for usar, a cobrança pelo PagBank.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_pagamentos') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Chave Pix <input name="pix" maxlength="80" value="<?= e($s['pix']) ?>" placeholder="flnexecutivoads@gmail.com"></label>
      <label class="field">Cidade do recebedor <input name="pix_cidade" maxlength="60" value="<?= e($s['pix_cidade'] ?? '') ?>" placeholder="Florianópolis"></label>
      <label class="field full">Token PagBank <input name="pagbank_token" maxlength="200" value="<?= e($s['pagbank_token'] ?? '') ?>" placeholder="cole o token PagBank" autocomplete="off"></label>
      <label class="cfg-switch full"><input type="checkbox" name="pagbank_ativo" value="1"<?= !empty($s['pagbank_ativo']) ? ' checked' : '' ?>> <span>PagBank ativo<small>Liga a cobrança com o token acima.</small></span></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar pagamentos</button></div>
    </fieldset>
  </form>
</section>

<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Operação e WhatsApp</h2>
      <p class="cfg-lead">Regras da agenda, acesso do administrador e a instância que envia mensagem ao motorista.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_operacao') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <label class="field">Antecedência mínima (horas) <input type="number" name="antecedencia" min="0" max="72" value="<?= e($s['antecedencia']) ?>" placeholder="2"></label>
      <label class="field">Intervalo entre viagens (minutos) <input type="number" name="intervalo_motorista" min="15" max="720" step="5" required value="<?= e($s['intervalo_motorista'] ?? '120') ?>" placeholder="120"></label>
      <label class="cfg-switch"><input type="checkbox" name="modo_simples" value="1"<?= !empty($s['modo_simples']) ? ' checked' : '' ?>> <span>Modo simples<small>Enxuga a operação do dia a dia.</small></span></label>
      <label class="cfg-switch"><input type="checkbox" name="verificacao_2fa" value="1"<?= !empty($s['verificacao_2fa']) ? ' checked' : '' ?>> <span>Código no login<small>Desligado, entra só com usuário e senha.</small></span></label>
      <label class="field">Usuário do administrador <input name="usuario" required maxlength="40" value="<?= e($s['usuario'] ?? 'admin') ?>" placeholder="admin" autocomplete="off"></label>
      <label class="field">Senha do administrador <input name="senha" maxlength="40" value="<?= e($s['senha'] ?? '') ?>" placeholder="admin123" autocomplete="off"></label>
      <p class="cfg-split">Instância WhatsApp</p>
      <label class="field full">Token permanente da API <input name="uazapi_admin_token" maxlength="512" value="<?= e($s['uazapi_admin_token'] ?? '') ?>" placeholder="token permanente uazapiGO" autocomplete="off"></label>
      <label class="field full">URL create-instance (opcional) <input name="uazapi_create_url" maxlength="220" value="<?= e($s['uazapi_create_url'] ?? '') ?>" placeholder="https://….supabase.co/functions/v1/create-instance-url"></label>
      <label class="field">URL da instância <input name="uazapi_url" maxlength="160" value="<?= e($s['uazapi_url'] ?? '') ?>" placeholder="preenchido ao gerar QR no Comunicador" readonly></label>
      <label class="field">Token da instância <input name="uazapi_token" maxlength="4096" value="<?= e($s['uazapi_token'] ?? '') ?>" placeholder="gerado automaticamente" autocomplete="off" readonly></label>
      <label class="cfg-switch full"><input type="checkbox" name="whatsapp_ativo" value="1"<?= !empty($s['whatsapp_ativo']) ? ' checked' : '' ?>> <span>Avisar o motorista no WhatsApp<small>Envia 1 dia antes e no dia da viagem.</small></span></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar operação</button></div>
    </fieldset>
  </form>
</section>

<section class="card cfg-card" id="notificacoes">
  <header class="card-h">
    <div>
      <h2>Notificações do aplicativo</h2>
      <p class="cfg-lead">O celular e o computador avisam na hora, com som e vibração, mesmo com o painel fechado.</p>
    </div>
    <button class="btn btn-sm" type="button" data-cfg-edit>Editar</button>
  </header>
  <div class="push-painel">
    <p id="push-estado" class="push-estado">Verificando este aparelho…</p>
    <div class="push-acoes">
      <button class="btn btn-primary" type="button" id="push-ativar">Ativar neste aparelho</button>
      <button class="btn" type="button" id="push-testar">Testar som e vibração</button>
      <button class="btn" type="button" id="push-instalar" hidden>Instalar aplicativo</button>
      <button class="btn" type="button" id="push-desligar">Desligar neste aparelho</button>
    </div>
  </div>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('save_notificacoes') ?>
    <input type="hidden" name="editando" value="0">
    <fieldset disabled>
      <div class="cfg-switches full">
        <label class="cfg-switch"><input type="checkbox" name="notif_som" value="1"<?= !isset($s['notif_som']) || !empty($s['notif_som']) ? ' checked' : '' ?>> <span>Som<small>Toque quando o aviso chegar.</small></span></label>
        <label class="cfg-switch"><input type="checkbox" name="notif_vibrar" value="1"<?= !isset($s['notif_vibrar']) || !empty($s['notif_vibrar']) ? ' checked' : '' ?>> <span>Vibração<small>O aparelho vibra junto com o som.</small></span></label>
        <label class="cfg-switch"><input type="checkbox" name="notif_reserva" value="1"<?= !isset($s['notif_reserva']) || !empty($s['notif_reserva']) ? ' checked' : '' ?>> <span>Nova reserva<small>Avisa assim que a reserva entra.</small></span></label>
        <label class="cfg-switch"><input type="checkbox" name="notif_solicitacao" value="1"<?= !isset($s['notif_solicitacao']) || !empty($s['notif_solicitacao']) ? ' checked' : '' ?>> <span>Solicitação do site<small>Pedido que ainda não virou reserva.</small></span></label>
        <label class="cfg-switch"><input type="checkbox" name="notif_cancelamento" value="1"<?= !isset($s['notif_cancelamento']) || !empty($s['notif_cancelamento']) ? ' checked' : '' ?>> <span>Cancelamento<small>Avisa quando uma reserva é cancelada.</small></span></label>
        <label class="cfg-switch"><input type="checkbox" name="notif_status" value="1"<?= !empty($s['notif_status']) ? ' checked' : '' ?>> <span>Mudança de status<small>Cada troca de situação da viagem.</small></span></label>
      </div>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar notificações</button></div>
    </fieldset>
  </form>
  <h3 class="push-sub">Aparelhos ligados</h3>
  <?php $aparelhos = array_values(array_filter($db['push'] ?? [], 'is_array')); ?>
  <?php if ($aparelhos === []): ?>
    <p class="help">Nenhum aparelho ativado. Faça isso neste computador e também no celular do cliente.</p>
  <?php else: ?>
    <ul class="push-aparelhos">
      <?php foreach ($aparelhos as $ap): ?>
        <li>
          <span><strong><?= e((string) ($ap['aparelho'] ?? 'Aparelho')) ?></strong><small><?= e((string) ($ap['criado_em'] ?? '')) ?></small></span>
          <button class="btn btn-sm" type="button" data-push-remover="<?= e((string) ($ap['id'] ?? '')) ?>">Remover</button>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <ol class="cfg-passos">
    <li><strong>Computador.</strong> Toque em Ativar neste aparelho e permita as notificações.</li>
    <li><strong>Celular.</strong> Abra o endereço https do painel no Chrome. O endereço desta máquina não acorda o telefone.</li>
    <li><strong>Instalar.</strong> No Chrome, use Instalar aplicativo ou o menu Adicionar à tela inicial. No iPhone, abra pelo ícone da Tela de Início.</li>
    <li><strong>Teste.</strong> Feche o painel, bloqueie a tela e toque em Testar som e vibração.</li>
  </ol>
  <?php if (trim((string) ($s['vapid_public'] ?? '')) === ''): ?>
    <p class="help">Este servidor ainda não preparou a chave das notificações. Atualize a página.</p>
  <?php endif; ?>
</section>

<section class="card cfg-card">
  <header class="card-h">
    <div>
      <h2>Google Sheets</h2>
      <p class="cfg-lead"><?= e(sheets_status_texto($db)) ?></p>
    </div>
  </header>
  <p class="help">Cada menu vira um arquivo no Drive e cada submenu uma aba. Criar, editar ou excluir no painel atualiza a planilha na mesma gravação.</p>
  <ol class="cfg-passos">
    <li>No Google Cloud, ative a API do Sheets e a API do Drive.</li>
    <li>Crie uma conta de serviço e baixe o JSON.</li>
    <li>Coloque o JSON em base64 na variável <code>GOOGLE_SERVICE_ACCOUNT_B64</code>.</li>
    <li>Defina <code>GOOGLE_SHEETS_ENABLED=1</code> no <code>.env</code> e no Vercel.</li>
  </ol>
  <?php if (sheets_ativo()): ?>
    <form method="post" action="index.php" style="margin-top:12px">
      <?= csrf_fields('sheets_sync_now') ?>
      <button class="btn btn-sm" type="submit">Atualizar planilhas agora</button>
    </form>
    <?php $links = sheets_links_admin($db); if ($links !== []): ?>
      <ul class="help" style="margin:8px 0 0;padding-left:18px">
        <?php foreach ($links as $menuId => $url): ?>
          <li><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($menuId) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card cfg-card cfg-danger">
  <header class="card-h">
    <div>
      <h2>Zona de perigo</h2>
      <p class="cfg-lead">Troca os dados desta sessão pela demonstração. No banco real, esta ação fica bloqueada.</p>
    </div>
  </header>
  <form method="post" action="index.php" data-confirm="Restaurar os dados de demonstração? As reservas e ajustes desta sessão serão substituídos.">
    <?= csrf_fields('reset_demo') ?>
    <button class="btn btn-danger" type="submit">Restaurar demonstração</button>
  </form>
</section>
</div>
