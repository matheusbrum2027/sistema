<?php
declare(strict_types=1);
defined('APP') || exit;

$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
$estado = (string) ($_GET['estado'] ?? '');
$mot = (int) ($_GET['motorista'] ?? 0);
$fat = (string) ($_GET['faturado'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$rows = $db['transfers'];
usort($rows, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
$rows = array_values(array_filter($rows, static function (array $t) use ($de, $ate, $estado, $mot, $fat, $q): bool {
    if ($de !== '' && $t['data'] < $de) {
        return false;
    }
    if ($ate !== '' && $t['data'] > $ate) {
        return false;
    }
    if ($estado !== '' && $t['status'] !== $estado) {
        return false;
    }
    if ($mot > 0 && (int) ($t['motorista_id'] ?? 0) !== $mot) {
        return false;
    }
    if ($fat === 'sim' && empty($t['faturado'])) {
        return false;
    }
    if ($fat === 'nao' && !empty($t['faturado'])) {
        return false;
    }
    if ($q !== '') {
        $blob = mb_strtolower($t['cliente'] . ' ' . $t['telefone'] . ' ' . $t['email'] . ' ' . $t['origem'] . ' ' . $t['destino'] . ' ' . $t['id']);
        if (!str_contains($blob, mb_strtolower($q))) {
            return false;
        }
    }
    return true;
}));
foreach ($db['transfers'] as $t) {
    if ((int) $t['id'] === $editId) {
        $edit = $t;
    }
}
$pg = slice_page($rows);
$keep = array_filter(['de' => $de, 'ate' => $ate, 'estado' => $estado, 'motorista' => $mot ?: null, 'faturado' => $fat, 'q' => $q]);
view_head('<button class="btn btn-primary" type="button" data-open-dialog="dlg-reserva">+ Criar Reserva</button>', 'Transfer (' . count($db['transfers']) . ')');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="reservas">
  <label>Data (de) <input type="date" name="de" value="<?= e($de) ?>" placeholder="dd/mm/aaaa"></label>
  <label>Data (até) <input type="date" name="ate" value="<?= e($ate) ?>" placeholder="dd/mm/aaaa"></label>
  <label>Estado
    <select name="estado">
      <option value="">Todos os estados</option>
      <?php foreach (['pendente' => 'Pendente', 'reserva' => 'Reserva', 'andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'] as $k => $lab): ?>
        <option value="<?= e($k) ?>"<?= $estado === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Motorista
    <select name="motorista">
      <option value="0">Todos</option>
      <?php foreach ($db['motoristas'] as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $mot === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Faturado
    <select name="faturado">
      <option value="">Todos</option>
      <option value="sim"<?= $fat === 'sim' ? ' selected' : '' ?>>Faturado</option>
      <option value="nao"<?= $fat === 'nao' ? ' selected' : '' ?>>Não faturado</option>
    </select>
  </label>
  <label>Cliente / contato / nº <input name="q" value="<?= e($q) ?>" placeholder="Paulo Henrique ou 3778"></label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
</form>
<section class="card">
  <div class="table-wrap">
    <table class="table-reservas">
      <thead><tr><th>Nº</th><th>Cliente</th><th>Contato</th><th>Tipo</th><th>Categoria</th><th>Trajeto</th><th>Data</th><th>Motorista</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $trip): ?>
          <tr>
            <td><a href="<?= e(href('agenda', ['ver' => $trip['id'], 'mes' => substr((string) $trip['data'], 0, 7)])) ?>">#<?= (int) $trip['id'] ?></a></td>
            <td><?= e($trip['cliente']) ?></td>
            <td><?= e($trip['telefone']) ?><div class="hint"><?= e($trip['email']) ?></div></td>
            <td><?= tipo_badges($trip) ?></td>
            <td><span class="tag"><?= e($trip['categoria'] ?? 'SED') ?></span></td>
            <td><?= e(short_place((string) $trip['origem'])) ?> → <?= e(short_place((string) $trip['destino'])) ?></td>
            <td><?= e(br_date((string) $trip['data'])) ?></td>
            <td><?= e(nome_motorista($db, $trip['motorista_id'])) ?></td>
            <td><?= e(brl((float) $trip['valor'])) ?></td>
            <td><?= status_badge((string) $trip['status']) ?></td>
            <td class="actions">
              <a class="btn btn-sm" href="<?= e(href('reservas', ['edit' => $trip['id']] + $keep)) ?>">Editar</a>
              <button class="btn btn-sm" type="button" data-open-dialog="dlg-ver-<?= (int) $trip['id'] ?>">Ver detalhes</button>
              <button class="btn btn-sm" type="button" data-open-dialog="dlg-pdf-<?= (int) $trip['id'] ?>">Baixar</button>
              <form method="post" action="index.php" data-confirm="Excluir a reserva #<?= (int) $trip['id'] ?> de <?= e($trip['cliente']) ?>? Esta exclusão tira a viagem da agenda e do financeiro em aberto."><?= csrf_fields('delete_transfer') ?><input type="hidden" name="id" value="<?= (int) $trip['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('reservas', $pg, $keep) ?>
</section>
<?php foreach ($pg['rows'] as $trip): ?>
  <dialog id="dlg-ver-<?= (int) $trip['id'] ?>">
    <header class="dialog-h">
      <h2>Detalhes da reserva #<?= (int) $trip['id'] ?></h2>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </header>
    <div class="dialog-b"><?php reserva_detalhes($db, $trip); ?></div>
    <footer class="dlg-foot">
      <?php if (($_SESSION['role'] ?? 'admin') !== 'motorista'): ?>
        <a class="btn btn-sm" href="<?= e(href('reservas', ['edit' => $trip['id']] + $keep)) ?>">Editar</a>
      <?php endif; ?>
      <?php if (reserva_pode_pdf($trip)): ?>
        <button class="btn btn-sm btn-primary" type="button" data-open-dialog="dlg-pdf-<?= (int) $trip['id'] ?>">Baixar PDF</button>
      <?php endif; ?>
      <?php if (($_SESSION['role'] ?? 'admin') !== 'motorista'): ?>
        <form method="post" action="index.php" data-confirm="Excluir a reserva #<?= (int) $trip['id'] ?> de <?= e($trip['cliente']) ?>? Esta exclusão tira a viagem da agenda e do financeiro em aberto."><?= csrf_fields('delete_transfer') ?><input type="hidden" name="id" value="<?= (int) $trip['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
      <?php endif; ?>
    </footer>
  </dialog>
  <dialog id="dlg-pdf-<?= (int) $trip['id'] ?>" class="dlg-pdf">
    <header class="dialog-h no-print">
      <h2>Pré-visualização da reserva #<?= (int) $trip['id'] ?></h2>
      <div class="dialog-tools">
        <button class="btn btn-sm btn-primary" type="button" data-print data-confirm-vezes="2" data-confirm="Baixar o PDF da reserva #<?= (int) $trip['id'] ?>?" data-confirm-2="Confirme novamente para gerar o PDF da reserva #<?= (int) $trip['id'] ?>.">Baixar PDF</button>
        <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
      </div>
    </header>
    <div class="dialog-b"><?php reserva_folha($db, $trip); ?></div>
    <footer class="dlg-foot no-print">
      <button class="btn btn-primary" type="button" data-print data-confirm-vezes="2" data-confirm="Baixar o PDF da reserva #<?= (int) $trip['id'] ?>?" data-confirm-2="Confirme novamente para gerar o PDF da reserva #<?= (int) $trip['id'] ?>.">Baixar PDF</button>
      <button class="btn" type="button" data-close-dialog>Fechar</button>
    </footer>
  </dialog>
<?php endforeach; ?>
<?php
$tipoForm = 'ida';
if ($edit) {
    $tipoForm = match ((string) ($edit['tipo'] ?? 'ida')) {
        'hora' => 'hora',
        'trajetos' => 'trajetos',
        'volta', 'ida_volta' => 'ida_volta',
        default => match ((string) ($edit['conjunto'] ?? '')) {
            'ida_volta' => 'ida_volta',
            'trajetos' => 'trajetos',
            default => 'ida',
        },
    };
}
$docEdit = (string) ($edit['documento'] ?? '');
if ($edit && $docEdit === '' && !empty($edit['cliente_id'])) {
    foreach ($db['clientes'] as $c) {
        if ((int) $c['id'] === (int) $edit['cliente_id']) {
            $docEdit = (string) ($c['documento'] ?? '');
            break;
        }
    }
}
$pagEdit = is_array($edit['pagamentos'] ?? null) ? $edit['pagamentos'] : [];
$somaPag = 0.0;
foreach ($pagEdit as $parte) {
    $somaPag += (float) $parte;
}
$manualEdit = '';
if ($edit && (float) $edit['valor'] > 0 && abs($somaPag - (float) $edit['valor']) > 0.009) {
    $manualEdit = number_format((float) $edit['valor'], 2, ',', '');
    $pagEdit = [];
}
$trechosForm = [
    ['embarque' => '', 'desembarque' => '', 'data' => '', 'hora' => '', 'pax' => 1],
    ['embarque' => '', 'desembarque' => '', 'data' => '', 'hora' => '', 'pax' => 1],
];
if ($edit && ($edit['conjunto'] ?? '') === 'trajetos') {
    $trechosForm = [[
        'embarque' => (string) ($edit['origem'] ?? ''),
        'desembarque' => (string) ($edit['destino'] ?? ''),
        'data' => (string) ($edit['data'] ?? ''),
        'hora' => (string) ($edit['hora'] ?? ''),
        'pax' => (int) ($edit['pax'] ?? 1),
    ]];
} elseif ($edit && ($edit['tipo'] ?? '') === 'trajetos' && is_array($edit['trajetos'] ?? null) && $edit['trajetos'] !== []) {
    $trechosForm = array_values($edit['trajetos']);
}
$extrasEdit = array_map('intval', $edit['extras'] ?? []);
$catsOk = array_keys(categorias_veiculo());
$catEdit = in_array((string) ($edit['categoria'] ?? 'SED'), $catsOk, true) ? (string) $edit['categoria'] : 'SED';
?>
<dialog id="dlg-reserva" class="dlg-reserva" <?= $edit ? 'open' : '' ?>>
  <form method="post" action="index.php" id="form-reserva" data-mode="<?= $edit ? 'edit' : 'create' ?>">
    <header class="dialog-h dlg-top">
      <div>
        <h2><?= $edit ? 'Editar reserva #' . (int) $edit['id'] : 'Criar Nova Reserva' ?></h2>
        <p><?= $edit ? 'Altere os campos necessários e salve. Use Fechar para voltar à lista sem salvar.' : 'Preencha os dados para criar uma nova reserva manual.' ?></p>
      </div>
      <div class="dialog-tools">
        <button class="btn btn-sm" type="button" data-close-dialog>Fechar</button>
        <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
      </div>
    </header>
    <div class="dialog-b reserva-scroll">
      <?= csrf_fields($edit ? 'update_transfer' : 'add_transfer') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <input type="hidden" name="cliente_id" id="cliente-id" value="<?= (int) ($edit['cliente_id'] ?? 0) ?>">

      <h3 class="sec-title">Informações do Cliente</h3>
      <div class="box">
        <span class="lbl">Origem do cliente</span>
        <div class="origin-row">
          <label><input type="radio" name="origem_cliente" value="cadastrado" checked> Cliente cadastrado</label>
          <label><input type="radio" name="origem_cliente" value="novo"> NOVO CLIENTE</label>
        </div>
        <div id="bloco-busca">
          <label class="field">Buscar e selecionar
            <input id="busca-cliente" placeholder="Nome, e-mail, telefone ou CPF..." autocomplete="off">
          </label>
          <p class="hint">Lista com rolagem (~10 visíveis). Digite para filtrar entre todos os clientes. (<?= count($db['clientes']) ?>)</p>
          <div class="client-list" id="client-list">
            <?php foreach ($db['clientes'] as $c): ?>
              <button type="button" class="client-pick" data-id="<?= (int) $c['id'] ?>" data-nome="<?= e($c['nome']) ?>" data-email="<?= e($c['email'] ?? '') ?>" data-tel="<?= e($c['telefone'] ?? '') ?>" data-doc="<?= e($c['documento'] ?? '') ?>"><?= e($c['nome']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-grid" style="margin-top:10px">
          <label class="field">Nome Completo *
            <input name="cliente" required maxlength="80" value="<?= e($edit['cliente'] ?? '') ?>" placeholder="Paulo Henrique Correa de Macedo">
          </label>
          <label class="field">CPF/CNPJ *
            <input name="documento" required maxlength="20" value="<?= e($docEdit) ?>" placeholder="000.000.000-00">
          </label>
          <label class="field">Email *
            <input type="email" name="email" required maxlength="80" value="<?= e($edit['email'] ?? '') ?>" placeholder="financeiroflnsc@gmail.com">
          </label>
          <label class="field">Telefone *
            <input name="telefone" required maxlength="30" value="<?= e($edit['telefone'] ?? '') ?>" placeholder="(48) 98822-2534">
          </label>
        </div>
      </div>

      <h3 class="sec-title">Detalhes da Viagem</h3>
      <p class="hint">Indique endereços completos (rua, número, bairro e cidade) para facilitar o atendimento.</p>
      <div class="form-grid">
        <label class="field">Tipo de Viagem *
          <select name="tipo_viagem" id="tipo-viagem">
            <?php foreach (['ida' => 'Somente Ida', 'ida_volta' => 'Ida e Volta', 'trajetos' => '2 ou mais trajetos', 'hora' => 'Por Hora'] as $k => $lab): ?>
              <option value="<?= e($k) ?>"<?= $tipoForm === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Categoria do veículo *
          <select name="categoria">
            <?php foreach (categorias_veiculo() as $k => $lab): ?>
              <option value="<?= e($k) ?>"<?= $catEdit === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="box bloco" data-show="ida ida_volta">
        <strong class="bloco-h">→ Ida</strong>
        <div class="form-grid">
          <label class="field">Local de Embarque (IDA) *
            <input name="embarque" maxlength="180" value="<?= e($edit['origem'] ?? '') ?>" placeholder="Endereço completo de embarque">
          </label>
          <label class="field">Local de Desembarque (IDA) *
            <input name="desembarque" maxlength="180" value="<?= e($edit['destino'] ?? '') ?>" placeholder="Endereço completo de destino">
          </label>
          <label class="field">Data do Embarque (IDA) *
            <input type="date" name="data" value="<?= e($edit['data'] ?? '') ?>" placeholder="dd/mm/aaaa">
          </label>
          <label class="field">Hora
            <input type="time" name="hora" value="<?= e($edit['hora'] ?? '') ?>" placeholder="--:--">
          </label>
          <label class="field">Número de Passageiros *
            <input type="number" name="pax" min="1" max="40" value="<?= (int) ($edit['pax'] ?? 1) ?>" placeholder="2">
          </label>
          <label class="field">Cupom
            <input name="cupom" maxlength="40" value="<?= e($edit['cupom'] ?? '') ?>" placeholder="FLN10">
          </label>
          <label class="field full">Mensagem / Observações
            <textarea name="obs_ida" maxlength="800" placeholder="Duas malas grandes, voo G3 1234"><?= e(($tipoForm === 'ida' || $tipoForm === 'ida_volta') ? (string) ($edit['obs'] ?? '') : '') ?></textarea>
          </label>
        </div>
      </div>

      <div class="box bloco" data-show="ida_volta" data-create-only>
        <strong class="bloco-h">↺ Volta</strong>
        <div class="form-grid">
          <label class="field">Local de Embarque (Volta)
            <input name="volta_embarque" maxlength="180" placeholder="Endereço completo">
          </label>
          <label class="field">Local de Desembarque (Volta)
            <input name="volta_desembarque" maxlength="180" placeholder="Endereço completo">
          </label>
          <label class="field">Data
            <input type="date" name="data_volta" placeholder="dd/mm/aaaa">
          </label>
          <label class="field">Hora
            <input type="time" name="hora_volta" placeholder="--:--">
          </label>
          <label class="field">Passageiros
            <input type="number" name="pax_volta" min="1" max="40" placeholder="2">
          </label>
          <label class="field">Cupom
            <input name="cupom_volta" maxlength="40" placeholder="FLN10">
          </label>
          <label class="field full">Mensagem / Observações
            <textarea name="obs_volta" maxlength="800" placeholder="Voo de retorno e horário"></textarea>
          </label>
        </div>
      </div>

      <div class="bloco" data-show="trajetos">
        <p class="hint">Cada destino vira uma reserva com número próprio na agenda e na tabela. O valor e o repasse são divididos entre elas. Mínimo 2 destinos.</p>
        <div id="trajeto-lista">
          <?php foreach ($trechosForm as $i => $tr): ?>
            <div class="box trajeto">
              <strong class="bloco-h">Trajeto <?= (int) $i + 1 ?></strong>
              <div class="form-grid">
                <label class="field">Embarque *
                  <input name="trajetos[<?= (int) $i ?>][embarque]" maxlength="180" value="<?= e((string) ($tr['embarque'] ?? '')) ?>" placeholder="Endereço completo de embarque">
                </label>
                <label class="field">Desembarque *
                  <input name="trajetos[<?= (int) $i ?>][desembarque]" maxlength="180" value="<?= e((string) ($tr['desembarque'] ?? '')) ?>" placeholder="Endereço completo de destino">
                </label>
                <label class="field">Data *
                  <input type="date" name="trajetos[<?= (int) $i ?>][data]" value="<?= e((string) ($tr['data'] ?? '')) ?>" placeholder="dd/mm/aaaa">
                </label>
                <label class="field">Hora
                  <input type="time" name="trajetos[<?= (int) $i ?>][hora]" value="<?= e((string) ($tr['hora'] ?? '')) ?>" placeholder="--:--">
                </label>
                <label class="field">Passageiros *
                  <input type="number" name="trajetos[<?= (int) $i ?>][pax]" min="1" max="40" value="<?= (int) ($tr['pax'] ?? 1) ?>" placeholder="2">
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-sm" type="button" id="add-trajeto">+ Adicionar trajeto</button>
      </div>

      <div class="box bloco" data-show="hora">
        <strong class="bloco-h">Por Hora</strong>
        <div class="form-grid">
          <label class="field">Endereço de Início
            <input name="inicio" maxlength="180" value="<?= e(($edit['tipo'] ?? '') === 'hora' ? (string) $edit['origem'] : '') ?>" placeholder="Endereço completo">
          </label>
          <label class="field">Ponto de Encerramento
            <input name="encerramento" maxlength="180" value="<?= e(($edit['tipo'] ?? '') === 'hora' ? (string) $edit['destino'] : '') ?>" placeholder="Endereço completo">
          </label>
          <label class="field">Data
            <input type="date" name="data_hora" value="<?= e(($edit['tipo'] ?? '') === 'hora' ? (string) $edit['data'] : '') ?>" placeholder="dd/mm/aaaa">
          </label>
          <label class="field">Hora
            <input type="time" name="hora_servico" value="<?= e(($edit['tipo'] ?? '') === 'hora' ? (string) $edit['hora'] : '') ?>" placeholder="--:--">
          </label>
          <label class="field">Passageiros
            <input type="number" name="pax_hora" min="1" max="40" value="<?= (int) (($edit['tipo'] ?? '') === 'hora' ? ($edit['pax'] ?? 1) : 1) ?>" placeholder="2">
          </label>
          <label class="field">Qtd. Horas
            <input name="qtd_horas" maxlength="10" value="<?= e((string) ($edit['qtd_horas'] ?? '')) ?>" placeholder="4">
          </label>
          <label class="field full">Cupom
            <input name="cupom_hora" maxlength="40" value="<?= e(($edit['tipo'] ?? '') === 'hora' ? (string) ($edit['cupom'] ?? '') : '') ?>" placeholder="FLN10">
          </label>
          <label class="field full">Itinerário / Observações
            <textarea name="itinerario" maxlength="800" placeholder="Roteiro do dia, paradas e horários"><?= e(($edit['tipo'] ?? '') === 'hora' ? (string) ($edit['obs'] ?? '') : '') ?></textarea>
          </label>
        </div>
      </div>

      <h3 class="sec-title">Veículo e Motorista</h3>
      <label class="field">Quem fará a viagem? *
        <select name="quem" id="quem-viagem">
          <option value="motorista"<?= ($edit['quem'] ?? 'motorista') !== 'parceiro' ? ' selected' : '' ?>>Motorista</option>
          <option value="parceiro"<?= ($edit['quem'] ?? '') === 'parceiro' ? ' selected' : '' ?>>Parceiro</option>
        </select>
      </label>
      <p class="hint" id="hint-parceiro" hidden>Parceiro não tem vínculo com motorista da plataforma.</p>
      <div id="bloco-motorista">
        <label class="field">Motorista da frota *
          <select name="motorista_id" id="motorista-principal">
            <option value="0">— Não atribuir ainda —</option>
            <?php foreach ($db['motoristas'] as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= (int) ($edit['motorista_id'] ?? 0) === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="bloco" data-show="ida_volta" data-create-only>
          <label class="field">Motorista da volta
            <select name="motorista_volta_id">
              <option value="0">— O mesmo da ida —</option>
              <?php foreach ($db['motoristas'] as $m): ?>
                <option value="<?= (int) $m['id'] ?>"><?= e($m['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <p class="hint">A atribuição fica gravada só nesta reserva. Ida e volta são linhas separadas — mudar o motorista da VOLTA não altera a IDA.</p>
      </div>
      <h4 class="sec-sub">Outros motoristas desta reserva</h4>
      <p class="hint">Marque só quem também executa esta viagem. Ao marcar, a reserva passa a aparecer no mini painel daquele motorista e em mais nenhum outro. O repasse de cada um é separado do motorista principal.</p>
      <div class="extra-list">
        <?php foreach ($db['motoristas'] as $m): ?>
          <label class="extra-row">
            <input type="checkbox" name="extras[]" value="<?= (int) $m['id'] ?>"<?= in_array((int) $m['id'], $extrasEdit, true) ? ' checked' : '' ?>>
            <span><strong><?= e($m['nome']) ?></strong><small>Mini painel próprio. O vínculo não sai desta conta.</small></span>
          </label>
        <?php endforeach; ?>
      </div>

      <h3 class="sec-title">Valores e Pagamento</h3>
      <div class="pay-list">
        <?php foreach (meios_pagamento() as $chave => $rotulo): ?>
          <label class="pay-line">
            <input type="checkbox" name="pag_<?= e($chave) ?>" value="1" data-pag <?= isset($pagEdit[$chave]) ? 'checked' : '' ?>>
            <span><?= e($rotulo) ?></span>
            <input name="pag_valor_<?= e($chave) ?>" inputmode="decimal" placeholder="0,00" value="<?= isset($pagEdit[$chave]) ? e(number_format((float) $pagEdit[$chave], 2, ',', '')) : '' ?>">
          </label>
        <?php endforeach; ?>
      </div>
      <label class="field">Definir valor total manualmente
        <input name="valor_manual" id="valor-manual" inputmode="decimal" placeholder="0,00" value="<?= e($manualEdit) ?>">
      </label>
      <div class="total-line">
        <span>Valor Total (a receber do cliente)</span>
        <strong id="total-reserva">R$ 0,00</strong>
      </div>

      <div class="box box-warn">
        <span class="lbl">Faturado *</span>
        <p class="hint">Indica se o serviço será cobrado em data posterior (contabiliza em Financeiro → Faturado). Independente da opção «Esconder valores».</p>
        <div class="origin-row">
          <label><input type="radio" name="faturado" value="sim"<?= !empty($edit['faturado']) ? ' checked' : '' ?>> Sim — faturado</label>
          <label><input type="radio" name="faturado" value="nao"<?= empty($edit['faturado']) ? ' checked' : '' ?>> Não — não faturado</label>
        </div>
      </div>
      <label class="extra-row">
        <input type="checkbox" name="esconder" value="1"<?= !empty($edit['esconder_valores']) ? ' checked' : '' ?>>
        <span><strong>Esconder valores</strong><small>Opcional e separado de «Faturado». Quando ativo, oculta os valores cobrados ao cliente no mini portal do motorista e no PDF de confirmação da reserva.</small></span>
      </label>
      <div class="form-grid">
        <label class="field">Status da reserva
          <select name="status" data-status-inicial="<?= e((string) ($edit['status'] ?? 'pendente')) ?>">
            <?php foreach (['pendente' => 'Pendente', 'reserva' => 'Reserva', 'andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'] as $k => $lab): ?>
              <option value="<?= e($k) ?>"<?= ($edit['status'] ?? 'pendente') === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Em Concluída, com repasse &gt; 0, gera automaticamente uma despesa no Financeiro (contas a pagar ao motorista).</span>
        </label>
        <label class="field">Repasse do motorista principal (R$)
          <input name="repasse" inputmode="decimal" placeholder="0,00" value="<?= e($edit ? number_format((float) ($edit['repasse'] ?? 0), 2, ',', '') : '0,00') ?>">
          <span class="hint">Valor só do motorista principal. Cada motorista extra tem o próprio repasse no cartão acima. Ida e volta criadas juntas dividem estes valores entre as duas reservas.</span>
        </label>
      </div>
      <label class="field">Observações
        <textarea name="obs" maxlength="800" placeholder="Uma informação por linha. Enter quebra a linha.&#10;Ex.: Nome do passageiro&#10;Voo e horário"><?= e((string) ($edit['obs'] ?? '')) ?></textarea>
      </label>
    </div>
    <footer class="dlg-foot">
      <button class="btn" type="button" data-close-dialog>Fechar</button>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Salvar reserva' : 'Criar Reserva' ?></button>
    </footer>
  </form>
</dialog>
<script>
(function () {
  const form = document.getElementById('form-reserva');
  if (!form) return;
  const tipo = document.getElementById('tipo-viagem');
  const quem = document.getElementById('quem-viagem');
  const busca = document.getElementById('busca-cliente');
  const lista = document.getElementById('client-list');
  const blocoBusca = document.getElementById('bloco-busca');
  const totalEl = document.getElementById('total-reserva');
  const editing = form.dataset.mode === 'edit';

  function syncTipo() {
    const atual = tipo.value;
    form.querySelectorAll('[data-show]').forEach((el) => {
      const ok = el.dataset.show.split(' ').includes(atual);
      const soCriar = el.hasAttribute('data-create-only') && editing;
      el.hidden = !ok || soCriar;
    });
  }
  function syncQuem() {
    const parceiro = quem.value === 'parceiro';
    document.getElementById('bloco-motorista').hidden = parceiro;
    document.getElementById('hint-parceiro').hidden = !parceiro;
  }
  function syncOrigem() {
    const novo = form.querySelector('[name=origem_cliente]:checked')?.value === 'novo';
    blocoBusca.hidden = novo;
    if (novo) document.getElementById('cliente-id').value = '0';
  }
  function lerDinheiro(v) {
    let s = String(v || '').replace(/R\$|\s/g, '');
    if (s.includes(',') && s.includes('.')) s = s.replace(/\./g, '').replace(',', '.');
    else s = s.replace(',', '.');
    const n = parseFloat(s);
    return Number.isFinite(n) ? n : 0;
  }
  function syncTotal() {
    let soma = 0;
    form.querySelectorAll('[data-pag]').forEach((box) => {
      if (!box.checked) return;
      const campo = box.closest('.pay-line')?.querySelector('input[name^="pag_valor_"]');
      soma += lerDinheiro(campo?.value);
    });
    const manual = lerDinheiro(document.getElementById('valor-manual').value);
    const n = manual > 0 ? manual : soma;
    totalEl.textContent = n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }
  tipo.addEventListener('change', syncTipo);
  quem.addEventListener('change', syncQuem);
  form.querySelectorAll('[name=origem_cliente]').forEach((r) => r.addEventListener('change', syncOrigem));
  form.addEventListener('input', (ev) => {
    if (ev.target.matches('[data-pag], [name^="pag_valor_"], #valor-manual')) syncTotal();
  });
  form.addEventListener('change', (ev) => {
    if (ev.target.matches('[data-pag]')) syncTotal();
  });
  busca.addEventListener('input', () => {
    const q = busca.value.trim().toLowerCase();
    lista.querySelectorAll('.client-pick').forEach((btn) => {
      const blob = (btn.dataset.nome + ' ' + btn.dataset.email + ' ' + btn.dataset.tel + ' ' + btn.dataset.doc).toLowerCase();
      btn.hidden = q !== '' && !blob.includes(q);
    });
  });
  lista.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.client-pick');
    if (!btn) return;
    document.getElementById('cliente-id').value = btn.dataset.id || '0';
    form.elements.cliente.value = btn.dataset.nome || '';
    form.elements.email.value = btn.dataset.email || '';
    form.elements.telefone.value = btn.dataset.tel || '';
    form.elements.documento.value = btn.dataset.doc || '';
    lista.querySelectorAll('.client-pick').forEach((el) => el.classList.toggle('on', el === btn));
  });
  document.getElementById('add-trajeto').addEventListener('click', () => {
    const listaT = document.getElementById('trajeto-lista');
    const i = listaT.querySelectorAll('.trajeto').length;
    const box = document.createElement('div');
    box.className = 'box trajeto';
    box.innerHTML = '<strong class="bloco-h">Trajeto ' + (i + 1) + '</strong><div class="form-grid">'
      + campo('Embarque *', 'trajetos[' + i + '][embarque]', 'text', 'Endereço completo de embarque')
      + campo('Desembarque *', 'trajetos[' + i + '][desembarque]', 'text', 'Endereço completo de destino')
      + campo('Data *', 'trajetos[' + i + '][data]', 'date', 'dd/mm/aaaa')
      + campo('Hora', 'trajetos[' + i + '][hora]', 'time', '--:--')
      + campo('Passageiros *', 'trajetos[' + i + '][pax]', 'number', '2')
      + '</div>';
    listaT.appendChild(box);
  });
  function campo(rotulo, nome, tipo, ph) {
    return '<label class="field">' + rotulo + '<input name="' + nome + '" type="' + tipo + '" placeholder="' + ph + '"' + (tipo === 'number' ? ' min="1" max="40" value="1"' : '') + '></label>';
  }
  const obsIda = form.elements.obs_ida;
  if (obsIda && form.elements.obs.value === '' && obsIda.value !== '') form.elements.obs.value = obsIda.value;
  form.addEventListener('submit', () => {
    if (form.elements.obs.value.trim() === '' && obsIda && !obsIda.closest('[hidden]') && obsIda.value.trim() !== '') {
      form.elements.obs.value = obsIda.value;
    }
    const atual = tipo.value;
    if (atual === 'hora') {
      form.elements.data.value = form.elements.data_hora.value;
      form.elements.hora.value = form.elements.hora_servico.value;
      form.elements.pax.value = form.elements.pax_hora.value || '1';
      if (form.elements.cupom_hora.value) form.elements.cupom.value = form.elements.cupom_hora.value;
    }
  });
  syncTipo();
  syncQuem();
  syncOrigem();
  syncTotal();
})();
</script>
