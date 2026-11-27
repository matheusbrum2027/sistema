<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function ficha_texto(array $row, string $chave, string $vazio = '—'): string
{
    $valor = trim((string) ($row[$chave] ?? ''));
    return $valor !== '' ? $valor : $vazio;
}

function ficha_motorista_por_id(array $db, int $id): ?array
{
    foreach ($db['motoristas'] ?? [] as $mot) {
        if ((int) ($mot['id'] ?? 0) === $id) {
            return $mot;
        }
    }
    return null;
}

function ficha_cliente_por_id(array $db, int $id): ?array
{
    foreach ($db['clientes'] ?? [] as $cliente) {
        if ((int) ($cliente['id'] ?? 0) === $id) {
            return $cliente;
        }
    }
    return null;
}

function dossie_id(array $mot): string
{
    $ano = substr((string) ($mot['criado_em'] ?? ''), 0, 4);
    if (!preg_match('/^\d{4}$/', $ano)) {
        $ano = date('Y');
    }
    $selo = strtoupper(substr(hash('sha256', 'fln-dossie-' . (int) ($mot['id'] ?? 0)), 0, 6));
    return '#MTR-' . $ano . '-' . $selo;
}

function dossie_token(array $mot): string
{
    $segredo = env_valor('FLN_SEGREDO', 'fln');
    return substr(hash_hmac('sha256', 'motorista-' . (int) ($mot['id'] ?? 0), $segredo), 0, 20);
}

function dossie_url_publica(array $mot): string
{
    return rtrim(base_url(), '/') . '/index.php?p=verificar&c=' . rawurlencode(dossie_token($mot));
}

function ficha_motorista_do_token(array $db, string $token): ?array
{
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{20}$/', $token)) {
        return null;
    }
    foreach ($db['motoristas'] ?? [] as $mot) {
        if (hash_equals(dossie_token($mot), $token)) {
            return $mot;
        }
    }
    return null;
}

function ficha_endereco(array $row): string
{
    $rua = trim((string) ($row['logradouro'] ?? '') . ' ' . (string) ($row['numero'] ?? ''));
    $partes = array_filter([
        $rua,
        trim((string) ($row['complemento'] ?? '')),
        trim((string) ($row['bairro'] ?? '')),
    ], static fn(string $s): bool => $s !== '');
    $linha = implode(', ', $partes);
    if ($linha === '') {
        $linha = trim((string) ($row['endereco'] ?? ''));
    }
    return $linha;
}

function ficha_cidade(array $row): string
{
    $cidade = trim((string) ($row['cidade'] ?? ''));
    $uf = strtoupper(trim((string) ($row['uf'] ?? '')));
    if ($cidade === '' && $uf === '') {
        return '';
    }
    return $uf !== '' && $cidade !== '' ? $cidade . ' / ' . $uf : ($cidade !== '' ? $cidade : $uf);
}

function ficha_pagamento(array $mot): string
{
    if (trim((string) ($mot['pix'] ?? '')) !== '') {
        return 'PIX';
    }
    if (trim((string) ($mot['banco'] ?? '') . (string) ($mot['conta'] ?? '')) !== '') {
        return 'TRANSFERÊNCIA';
    }
    return '';
}

function ficha_status_frota(array $mot): string
{
    return (string) ($mot['status'] ?? 'ativo') === 'ativo' ? 'Ativo na frota' : 'Inativo';
}

function ficha_doc_anexado(array $mot, array $chaves): bool
{
    foreach ($chaves as $chave) {
        if (trim((string) ($mot[$chave] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function ficha_iniciais(string $nome): string
{
    $pedacos = preg_split('/\s+/u', trim($nome)) ?: [];
    $letras = '';
    foreach ($pedacos as $pedaco) {
        if ($pedaco === '') {
            continue;
        }
        $letras .= mb_strtoupper(mb_substr($pedaco, 0, 1));
        if (mb_strlen($letras) >= 2) {
            break;
        }
    }
    return $letras !== '' ? $letras : '?';
}

/** @return array{0:int,1:float} */
function ficha_viagens_cliente(array $db, array $cliente): array
{
    $id = (int) ($cliente['id'] ?? 0);
    $nome = mb_strtolower(trim((string) ($cliente['nome'] ?? '')));
    $fone = preg_replace('/\D+/', '', (string) ($cliente['telefone'] ?? '')) ?? '';
    $n = 0;
    $valor = 0.0;
    foreach ($db['transfers'] ?? [] as $trip) {
        if (!is_array($trip) || (string) ($trip['status'] ?? '') === 'cancelada') {
            continue;
        }
        $mesmo = $id > 0 && (int) ($trip['cliente_id'] ?? 0) === $id;
        if (!$mesmo && $nome !== '') {
            $mesmo = mb_strtolower(trim((string) ($trip['cliente'] ?? ''))) === $nome;
            $foneTrip = preg_replace('/\D+/', '', (string) ($trip['telefone'] ?? '')) ?? '';
            if ($mesmo && $fone !== '' && $foneTrip !== '' && $foneTrip !== $fone) {
                $mesmo = false;
            }
        }
        if (!$mesmo) {
            continue;
        }
        $n++;
        $valor += (float) ($trip['valor'] ?? 0);
    }
    return [$n, round($valor, 2)];
}

function ficha_cliente_dialog(array $db, array $cliente): string
{
    $id = (int) ($cliente['id'] ?? 0);
    $pct = perfil_cliente($cliente);
    $tipo = (string) ($cliente['tipo'] ?? 'PF') === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física';
    [$viagens, $valor] = ficha_viagens_cliente($db, $cliente);
    $endereco = ficha_endereco($cliente);
    $cidade = ficha_cidade($cliente);
    $nome = (string) ($cliente['nome'] ?? '');
    $doc = ficha_texto($cliente, 'documento', 'Não informado');
    $email = ficha_texto($cliente, 'email', 'Não informado');
    $tel = ficha_texto($cliente, 'telefone', 'Não informado');
    $obs = trim((string) ($cliente['obs'] ?? ''));
    $financeiro = href('receber', ['q' => $nome]);
    $editar = href('clientes', ['edit' => $id]);

    ob_start();
    ?>
<dialog id="dlg-cli-<?= $id ?>" class="dlg-ficha">
  <header class="dialog-h">
    <div>
      <h2>Detalhes do cliente</h2>
      <p class="hint">Cadastro usado nas reservas. Os dados do cliente ficam só com o administrador.</p>
    </div>
    <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
  </header>
  <div class="dialog-b ficha">
    <div class="ficha-perfil">
      <div class="ficha-perfil-top"><span>Perfil no cadastro</span><strong><?= $pct ?>%</strong></div>
      <span class="perfil perfil-lg" title="<?= $pct ?>%"><i style="width:<?= $pct ?>%"></i></span>
      <p class="hint">Complete endereço e documento para subir a percentagem.</p>
    </div>
    <h3 class="ficha-nome"><?= e($nome) ?></h3>
    <p class="ficha-tipo"><?= e($tipo) ?></p>
    <ul class="ficha-lista">
      <li><span>E-mail</span><b><?= e($email) ?></b></li>
      <li><span>Telefone</span><b><?= e($tel) ?></b></li>
      <li><span>CPF / CNPJ</span><b><?= e($doc) ?></b></li>
      <?php if (trim((string) ($cliente['rg'] ?? '')) !== ''): ?><li><span>RG</span><b><?= e((string) $cliente['rg']) ?></b></li><?php endif; ?>
      <?php if (trim((string) ($cliente['empresa'] ?? '')) !== ''): ?><li><span>Empresa</span><b><?= e((string) $cliente['empresa']) ?></b></li><?php endif; ?>
      <?php if ($endereco !== ''): ?><li><span>Endereço</span><b><?= e($endereco) ?></b></li><?php endif; ?>
      <?php if ($cidade !== ''): ?><li><span>Cidade</span><b><?= e($cidade) ?></b></li><?php endif; ?>
      <?php if (trim((string) ($cliente['cep'] ?? '')) !== ''): ?><li><span>CEP</span><b><?= e((string) $cliente['cep']) ?></b></li><?php endif; ?>
    </ul>
    <?php if ($obs !== ''): ?>
      <div class="ficha-box">
        <span>Observações</span>
        <p><?= e($obs) ?></p>
      </div>
    <?php endif; ?>
    <div class="ficha-box">
      <span>Resumo das reservas</span>
      <p><b><?= (int) $viagens ?></b> <?= $viagens === 1 ? 'viagem registada' : 'viagens registadas' ?></p>
      <p>Valor total em reservas (referência): <b><?= e(brl($valor)) ?></b></p>
      <p class="hint">A soma usa as reservas ligadas a este cliente, sem as canceladas.</p>
    </div>
    <div class="ficha-acoes">
      <a class="btn" href="<?= e($editar) ?>">Editar</a>
      <a class="btn btn-primary" href="<?= e($financeiro) ?>">Abrir financeiro</a>
    </div>
  </div>
</dialog>
    <?php
    return (string) ob_get_clean();
}

function ficha_data_txt(string $iso): string
{
    $iso = trim($iso);
    if ($iso === '') {
        return 'Não informado';
    }
    return function_exists('br_date') ? br_date($iso) : $iso;
}

function ficha_cnh_txt(string $iso): string
{
    $iso = trim($iso);
    if ($iso === '') {
        return 'Não informada';
    }
    $txt = ficha_data_txt($iso);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) === 1 && $iso < date('Y-m-d')) {
        return $txt . ' · vencida';
    }
    return $txt;
}

function ficha_desde_txt(array $mot): string
{
    $iso = trim((string) ($mot['criado_em'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $iso) !== 1) {
        return 'Não informado';
    }
    return ficha_data_txt(substr($iso, 0, 10));
}

function ficha_motorista_dialog(array $db, array $mot, bool $aberto = false): string
{
    $id = (int) ($mot['id'] ?? 0);
    $nome = (string) ($mot['nome'] ?? '');
    $email = mb_strtolower(trim((string) ($mot['email'] ?? $mot['usuario'] ?? '')));
    $login = mb_strtolower(trim((string) ($mot['usuario'] ?? $mot['email'] ?? '')));
    $cidade = ficha_cidade($mot);
    $endereco = ficha_endereco($mot);
    $cep = trim((string) ($mot['cep'] ?? ''));
    $pagamento = ficha_pagamento($mot);
    $status = ficha_status_frota($mot);
    $ativo = (string) ($mot['status'] ?? 'ativo') === 'ativo';
    $link = link_motorista($mot);
    $empresa = trim((string) ($db['settings']['nome'] ?? 'Transfer FLN'));
    if ($empresa === '') {
        $empresa = 'Transfer FLN';
    }
    $obs = trim((string) ($mot['obs'] ?? ''));
    $banco = trim((string) ($mot['banco'] ?? ''));
    $agencia = trim((string) ($mot['agencia'] ?? ''));
    $conta = trim((string) ($mot['conta'] ?? ''));

    ob_start();
    ?>
<dialog id="dlg-dossie-<?= $id ?>" class="dlg-ficha dlg-dossie" <?= $aberto ? 'open' : '' ?>>
  <header class="dialog-h dossie-h">
    <div class="dossie-marca">
      <img src="index.php?p=marca&amp;k=logo" alt="" width="44" height="44" onerror="this.remove()">
      <div>
        <h2>Dossiê do motorista</h2>
        <p class="hint"><?= e($empresa) ?> · <?= e(dossie_id($mot)) ?> · <?= e(date('d/m/Y')) ?></p>
      </div>
    </div>
    <div class="dialog-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(href('mot-cadastros', ['baixar' => 'dossie', 'id' => $id])) ?>">Exportar PDF</a>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </div>
  </header>
  <div class="dialog-b ficha dossie">
    <section class="dossie-hero">
      <span class="ficha-avatar" aria-hidden="true"><?= e(ficha_iniciais($nome)) ?></span>
      <div class="dossie-ident">
        <h3 class="ficha-nome"><?= e($nome) ?></h3>
        <p class="ficha-tags">
          <span class="tag <?= $ativo ? 'tag-ok' : 'tag-wait' ?>"><?= e($status) ?></span>
          <span class="tag <?= portal_label($mot) === 'Pendente' ? 'tag-wait' : 'tag-ok' ?>"><?= e(portal_label($mot)) ?></span>
          <span class="tag">CNH <?= e(ficha_texto($mot, 'categoria', 'sem categoria')) ?></span>
        </p>
        <p class="dossie-linha"><?= e(ficha_texto($mot, 'telefone', 'Telefone não informado')) ?> · <?= e($email !== '' ? $email : 'E-mail não informado') ?></p>
      </div>
    </section>

    <div class="dossie-cols">
      <section class="dossie-bloco">
        <h3>Identificação</h3>
        <dl>
          <div><dt>CPF</dt><dd><?= e(ficha_texto($mot, 'cpf', 'Não informado')) ?></dd></div>
          <div><dt>RG</dt><dd><?= e(ficha_texto($mot, 'rg', 'Não informado')) ?></dd></div>
          <div><dt>Nascimento</dt><dd><?= e(ficha_data_txt((string) ($mot['nascimento'] ?? ''))) ?></dd></div>
          <div><dt>Login do portal</dt><dd><?= e($login !== '' ? $login : 'Não informado') ?></dd></div>
        </dl>
      </section>
      <section class="dossie-bloco">
        <h3>Endereço</h3>
        <dl>
          <div class="dossie-largo"><dt>Logradouro</dt><dd><?= e($endereco !== '' ? $endereco : 'Não informado') ?></dd></div>
          <div><dt>Cidade / UF</dt><dd><?= e($cidade !== '' ? $cidade : 'Não informada') ?></dd></div>
          <div><dt>CEP</dt><dd><?= e($cep !== '' ? $cep : 'Não informado') ?></dd></div>
        </dl>
      </section>
      <section class="dossie-bloco">
        <h3>CNH e frota</h3>
        <dl>
          <div><dt>Número</dt><dd><?= e(ficha_texto($mot, 'cnh', 'Não informado')) ?></dd></div>
          <div><dt>Categoria</dt><dd><?= e(ficha_texto($mot, 'categoria', 'Não informada')) ?></dd></div>
          <div><dt>Validade</dt><dd><?= e(ficha_cnh_txt((string) ($mot['cnh_validade'] ?? ''))) ?></dd></div>
          <div><dt>Na frota desde</dt><dd><?= e(ficha_desde_txt($mot)) ?></dd></div>
        </dl>
      </section>
      <section class="dossie-bloco">
        <h3>Repasse</h3>
        <dl>
          <div><dt>Forma</dt><dd><?= e($pagamento !== '' ? $pagamento : 'Não informada') ?></dd></div>
          <div><dt>Chave PIX</dt><dd><?= e(ficha_texto($mot, 'pix', 'Não informada')) ?></dd></div>
          <div><dt>Banco</dt><dd><?= e($banco !== '' ? $banco : 'Não informado') ?></dd></div>
          <div><dt>Agência / conta</dt><dd><?= e(trim($agencia . ($agencia !== '' && $conta !== '' ? ' · ' : '') . $conta) ?: 'Não informada') ?></dd></div>
        </dl>
      </section>
    </div>

    <?php if ($obs !== ''): ?>
      <section class="dossie-bloco dossie-obs"><h3>Observações</h3><p><?= e($obs) ?></p></section>
    <?php endif; ?>

    <section class="dossie-bloco dossie-portal">
      <h3>Acesso ao painel</h3>
      <p class="hint">A senha gravada não aparece aqui. Gerar outra invalida a atual e mostra a nova na faixa do topo por 1 minuto. O PDF leva a logo, o cadastro e o QR de confirmação da frota.</p>
      <div class="ficha-acoes">
        <a class="btn btn-sm" href="<?= e($link) ?>" target="_blank" rel="noopener">Abrir portal</a>
        <button class="btn btn-sm" type="button" data-copy="<?= e($link) ?>">Copiar link</button>
        <a class="btn btn-sm" href="<?= e(href('mot-cadastros', ['edit' => $id])) ?>">Editar cadastro</a>
        <form method="post" action="index.php" data-confirm="Gerar nova senha temporária para <?= e($nome) ?>? A atual deixa de valer e a nova fica na faixa do topo por 1 minuto."><?= csrf_fields('reset_senha_motorista') ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm" type="submit">Redefinir senha</button></form>
      </div>
    </section>
  </div>
</dialog>
    <?php
    return (string) ob_get_clean();
}
