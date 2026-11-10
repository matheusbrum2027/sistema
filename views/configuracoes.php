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
      <p class="hint full">O favicon do sistema usa exatamente a logo da empresa. Se enviar só o favicon, ele também vira a logo.</p>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar dados</button></div>
    </fieldset>
  </form>
  <?php if (!empty($s['logo']) || !empty($s['tem_logo'])): ?><p class="hint">Logo atual carregada (também usada no favicon).</p><?php endif; ?>
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
  <?php if (!empty($s['assinatura']) || !empty($s['tem_assinatura'])): ?><p class="hint">Assinatura ativa no contrato e no PDF da reserva.</p><?php endif; ?>
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
      <label class="field">Chave Pix <input name="pix" maxlength="80" value="<?= e($s['pix']) ?>" placeholder="chave Pix da empresa"></label>
      <label class="field">Cidade do recebedor <input name="pix_cidade" maxlength="60" value="<?= e($s['pix_cidade'] ?? '') ?>" placeholder="Florianópolis"></label>
      <?php $temPagTok = segredo_cadastrado((string) ($s['pagbank_token'] ?? '')); ?>
      <label class="field full">Token PagBank
        <input type="password" name="pagbank_token" maxlength="200" value="" placeholder="<?= $temPagTok ? '•••••••• (salvo — deixe em branco para manter)' : 'cole o token PagBank' ?>" autocomplete="new-password">
      </label>
      <?php if ($temPagTok): ?><p class="hint full">Token PagBank cadastrado e oculto. Preencha só para trocar.</p><?php endif; ?>
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
      <label class="field">Usuário do administrador <input name="usuario" required maxlength="80" value="<?= e($s['usuario'] ?? 'admin') ?>" placeholder="admin" autocomplete="username"></label>
      <?php $temSenhaAdmin = segredo_cadastrado((string) ($s['senha'] ?? '')); ?>
      <label class="field">Senha do administrador
        <input type="password" name="senha" maxlength="80" value="" placeholder="<?= $temSenhaAdmin ? '•••••••• (salva — deixe em branco para manter)' : 'defina uma senha forte' ?>" autocomplete="new-password">
      </label>
      <?php if ($temSenhaAdmin): ?><p class="hint full">Senha já cadastrada. O valor nunca é exibido. Preencha só para trocar.</p><?php endif; ?>
      <p class="cfg-split">Instância WhatsApp</p>
      <?php $temAdminTok = segredo_cadastrado((string) ($s['uazapi_admin_token'] ?? '')); ?>
      <label class="field full">Token permanente da API
        <input type="password" name="uazapi_admin_token" maxlength="512" value="" placeholder="<?= $temAdminTok ? '•••••••• (salvo — deixe em branco para manter)' : 'token permanente uazapiGO' ?>" autocomplete="new-password">
      </label>
      <?php if ($temAdminTok): ?><p class="hint full">Token permanente cadastrado e oculto. Preencha só para trocar.</p><?php endif; ?>
      <label class="field full">URL create-instance (opcional) <input name="uazapi_create_url" maxlength="220" value="<?= e($s['uazapi_create_url'] ?? '') ?>" placeholder="https://….supabase.co/functions/v1/create-instance-url" autocomplete="off"></label>
      <?php
        $temUrlInst = trim((string) ($s['uazapi_url'] ?? '')) !== '';
        $temTokInst = segredo_cadastrado((string) ($s['uazapi_token'] ?? ''));
      ?>
      <p class="hint full">URL da instância: <?= $temUrlInst ? 'conectada (gerenciada no Comunicador — valor oculto aqui)' : 'ainda não gerada — use o Comunicador' ?>.</p>
      <p class="hint full">Token da instância: <?= $temTokInst ? 'cadastrado e oculto' : 'ainda não gerado' ?>.</p>
      <label class="cfg-switch full"><input type="checkbox" name="whatsapp_ativo" value="1"<?= !empty($s['whatsapp_ativo']) ? ' checked' : '' ?>> <span>Avisar o motorista no WhatsApp<small>Envia 1 dia antes e no dia da viagem.</small></span></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar operação</button></div>
    </fieldset>
  </form>
</section>

<?php
$estado2fa = totp_estado($db);
$secret2fa = totp_secret_limpo((string) ($s['totp_secret'] ?? ''));
$conta2fa = (string) ($s['usuario'] ?? 'admin');
$uri2fa = $secret2fa !== '' ? totp_uri($secret2fa, $conta2fa, (string) ($s['nome'] ?? 'FLN Transfer')) : '';
$qr2fa = $uri2fa !== '' ? totp_qr_url($uri2fa, 240) : '';
$rotulo2fa = $estado2fa === 'on' ? 'Ativa' : ($estado2fa === 'setup' ? 'Aguardando confirmação' : 'Desligada');
$classe2fa = $estado2fa === 'on' ? 'ok' : ($estado2fa === 'setup' ? 'warn' : 'off');
?>
<section class="card cfg-card" id="seguranca-2fa">
  <header class="card-h">
    <div>
      <h2>Verificação em 2 passos (2FA)</h2>
      <p class="cfg-lead">Só para o administrador. O código visual anti-bot continua obrigatório para todos.</p>
    </div>
    <span class="fa2-badge fa2-badge-<?= e($classe2fa) ?>"><?= e($rotulo2fa) ?></span>
  </header>

  <div class="fa2-painel">
    <?php if ($estado2fa === 'off'): ?>
    <ol class="fa2-passos">
      <li>Instale o Google Authenticator ou Authy no celular</li>
      <li>Toque em <strong>Ativar 2FA</strong> e escaneie o QR</li>
      <li>Digite o código de 6 dígitos para confirmar</li>
    </ol>
    <form method="post" action="index.php" class="fa2-acoes">
      <?= csrf_fields('2fa_iniciar') ?>
      <button class="btn btn-primary" type="submit">Ativar 2FA</button>
    </form>

    <?php elseif ($estado2fa === 'setup'): ?>
    <div class="fa2-setup">
      <div class="fa2-qr-wrap">
        <img class="fa2-qr" src="<?= e($qr2fa) ?>" width="240" height="240" alt="QR Code 2FA">
        <p class="fa2-dica">Escaneie com o autenticador</p>
      </div>
      <div class="fa2-chave-wrap">
        <p class="fa2-dica">Ou digite esta chave no app</p>
        <code class="fa2-chave" id="fa2-chave"><?= e(totp_secret_exibir($secret2fa)) ?></code>
        <button class="btn" type="button" data-copy="<?= e($secret2fa) ?>" data-copy-keep="1">Copiar chave</button>
        <a class="btn" href="<?= e($uri2fa) ?>">Abrir no celular</a>
      </div>
    </div>
    <form method="post" action="index.php" class="fa2-confirmar">
      <?= csrf_fields('2fa_confirmar') ?>
      <label class="field" for="cfg_codigo_2fa">Código de 6 dígitos do app
        <input id="cfg_codigo_2fa" class="auth-2fa-input" name="codigo_2fa" required maxlength="6" minlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000">
      </label>
      <div class="fa2-acoes">
        <button class="btn btn-primary" type="submit">Confirmar e ligar</button>
      </div>
    </form>
    <form method="post" action="index.php" class="fa2-acoes" onsubmit="return confirm('Cancelar a configuração da 2FA?');">
      <?= csrf_fields('2fa_desligar') ?>
      <button class="btn" type="submit">Cancelar</button>
    </form>

    <?php else: ?>
    <p class="fa2-ok-txt">No login do admin, depois da senha e do código visual, o sistema pede o código do autenticador.</p>
    <div class="fa2-acoes">
      <form method="post" action="index.php" onsubmit="return confirm('Gerar nova chave? O app antigo deixa de funcionar.');">
        <?= csrf_fields('2fa_iniciar') ?>
        <button class="btn" type="submit">Trocar chave</button>
      </form>
      <form method="post" action="index.php" onsubmit="return confirm('Desligar a 2FA?');">
        <?= csrf_fields('2fa_desligar') ?>
        <button class="btn" type="submit">Desligar 2FA</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
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
    <li><strong>Android.</strong> Baixe o <a href="assets/app/fln-transfer.apk" download="fln-transfer.apk">APK FLN Transfer</a>, instale, entre no app e toque em Ativar neste aparelho.</li>
    <li><strong>iPhone.</strong> No Safari, use Compartilhar → Adicionar à Tela de Início. Abra o ícone e ative as notificações.</li>
    <li><strong>Computador.</strong> No Chrome, toque em Ativar neste aparelho (e Instalar aplicativo, se aparecer).</li>
    <li><strong>Teste.</strong> Feche o painel, bloqueie a tela e toque em Testar som e vibração.</li>
  </ol>
  <?php if (trim((string) ($s['vapid_public'] ?? '')) === ''): ?>
    <p class="help">Este servidor ainda não preparou a chave das notificações. Atualize a página.</p>
  <?php endif; ?>
</section>

<section class="card cfg-card" id="google-drive">
  <header class="card-h">
    <div>
      <h2>Google Drive · backup diário</h2>
      <p class="cfg-lead"><?= e(drive_status_texto($db)) ?></p>
    </div>
  </header>
  <?php if (!drive_conectado($db)): ?>
    <p class="help">Com um clique o sistema cria a pasta <strong>FLN Transfer Backups</strong> e, todo dia, uma subpasta <strong>backup DD/MM/AAAA</strong> com o JSON do estado.</p>
    <?php if (drive_oauth_configurado()): ?>
      <form method="post" action="index.php" style="margin-top:12px">
        <?= csrf_fields('drive_oauth_start') ?>
        <button class="btn btn-primary" type="submit">Conectar Google Drive</button>
      </form>
    <?php else: ?>
      <p class="help" style="margin-top:10px">No Google Cloud: crie um OAuth Client (tipo Web), URI de redirecionamento <code><?= e(drive_redirect_uri()) ?></code>, e cadastre <code>GOOGLE_OAUTH_CLIENT_ID</code> + <code>GOOGLE_OAUTH_CLIENT_SECRET</code> no Vercel.</p>
    <?php endif; ?>
    <p class="help" style="margin-top:10px">Chave de API (<code>GOOGLE_API_KEY</code>): <?= google_api_configurada() ? 'configurada no servidor (usada com key= nas APIs Google; não vai para o browser).' : 'ausente — cadastre no .env / Vercel.' ?></p>
  <?php else: ?>
    <ul class="help" style="margin:0;padding-left:18px;line-height:1.7">
      <li>Conta: <?= e(drive_email_mascarado($db)) ?></li>
      <li>Pasta do dia: <code><?= e(drive_nome_pasta_dia()) ?></code></li>
      <?php if (trim((string) ($s['drive_ultimo_backup'] ?? '')) !== ''): ?>
        <li>Último envio: <?= e((string) $s['drive_ultimo_backup']) ?><?php if (trim((string) ($s['drive_ultimo_pasta'] ?? '')) !== ''): ?> · <?= e((string) $s['drive_ultimo_pasta']) ?><?php endif; ?></li>
      <?php endif; ?>
      <?php if (trim((string) ($s['drive_ultimo_erro'] ?? '')) !== ''): ?>
        <li style="color:#b42318">Último erro: <?= e((string) $s['drive_ultimo_erro']) ?></li>
      <?php endif; ?>
    </ul>
    <form method="post" action="index.php" style="margin-top:12px;display:inline-block">
      <?= csrf_fields('drive_backup_now') ?>
      <button class="btn btn-sm btn-primary" type="submit">Enviar backup agora</button>
    </form>
    <?php
      $pastaId = trim((string) ($s['drive_root_folder_id'] ?? ''));
      if ($pastaId !== ''):
    ?>
      <a class="btn btn-sm" style="margin-left:6px" href="https://drive.google.com/drive/folders/<?= e($pastaId) ?>" target="_blank" rel="noopener">Abrir pasta no Drive</a>
    <?php endif; ?>
    <details class="cfg-drive-risco" style="margin-top:18px;border-top:1px solid var(--line,#e5e7eb);padding-top:12px">
      <summary style="cursor:pointer;color:#6b7280;font-size:.9rem">Opções avançadas (evitar)</summary>
      <p class="help" style="margin-top:10px">Desconectar remove o acesso e <strong>para</strong> os backups automáticos. Só use se for trocar de conta.</p>
      <form method="post" action="index.php" class="form-grid" style="margin-top:8px" data-confirm="Desconectar o Google Drive? Os backups diários vão parar.">
        <?= csrf_fields('drive_desconectar') ?>
        <label class="field">Digite DESCONECTAR <input name="confirmar_texto" required maxlength="20" autocomplete="off" placeholder="DESCONECTAR"></label>
        <div class="form-actions"><button class="btn btn-sm btn-danger" type="submit">Remover integração</button></div>
      </form>
    </details>
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
