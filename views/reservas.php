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
    if ($mot > 0 && !viagem_do_motorista($t, $mot)) {
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
        $edit = trip_com_obs($t);
    }
}
$solPedido = (int) ($_GET['solicitacao'] ?? 0);
$solLiberada = (int) ($_SESSION['solicitacao_liberada'] ?? 0);
$convertendo = null;
$solNegada = false;
if (!$edit && $solPedido > 0) {
    if ($solLiberada !== $solPedido) {
        $solNegada = true;
    } else {
        foreach ($db['inbox'] as $linhaSol) {
            if ((int) ($linhaSol['id'] ?? 0) === $solPedido) {
                $convertendo = $linhaSol;
                break;
            }
        }
        if ($convertendo === null) {
            $solNegada = true;
        }
    }
}
$pre = is_array($convertendo) ? solicitacao_como_reserva($convertendo) : [];
$campo = static function (string $chave) use ($edit, $pre): string {
    if (is_array($edit) && trim((string) ($edit[$chave] ?? '')) !== '') {
        return (string) $edit[$chave];
    }
    return (string) ($pre[$chave] ?? '');
};
$solForm = (int) ($convertendo['id'] ?? 0);
$pg = slice_page($rows);
$keep = array_filter(['de' => $de, 'ate' => $ate, 'estado' => $estado, 'motorista' => $mot ?: null, 'faturado' => $fat, 'q' => $q]);
view_head('<button class="btn btn-primary" type="button" data-open-dialog="dlg-reserva">+ Criar Reserva</button>', 'Transfer (' . count($db['transfers']) . ')');
?>
<?php if ($solNegada): ?>
  <div class="flash flash-err" role="alert">A conversão só abre depois das duas confirmações na lista de solicitações.</div>
<?php endif; ?>
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
      <thead><tr><th>Nº</th><th>Cliente</th><th>Contato</th><th>Tipo</th><th>Categoria</th><th>Trajeto</th><th>Data</th><th>Motorista</th><th>Valor</th><th class="col-pgto" title="Forma de pagamento">Forma pgto</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($pg['rows'] as $trip): ?>
          <tr>
            <td class="col-fixo" data-label="Nº"><a href="<?= e(href('agenda', ['ver' => $trip['id'], 'mes' => substr((string) $trip['data'], 0, 7)])) ?>">#<?= (int) $trip['id'] ?></a></td>
            <td class="col-cli" data-label="Cliente"><span class="clip" title="<?= e($trip['cliente']) ?>"><?= e($trip['cliente']) ?></span></td>
            <td class="col-contato" data-label="Contato"><span class="clip" title="<?= e(trim($trip['telefone'] . ' ' . $trip['email'])) ?>"><?= e($trip['telefone']) ?></span></td>
            <td class="col-fixo" data-label="Tipo"><?= tipo_badges($trip) ?></td>
            <td class="col-fixo" data-label="Categoria"><span class="tag"><?= e($trip['categoria'] ?? 'SED') ?></span></td>
            <td class="col-rota" data-label="Trajeto"><span class="clip" title="<?= e($trip['origem'] . ' → ' . $trip['destino']) ?>"><?= e($trip['origem']) ?> → <?= e($trip['destino']) ?></span></td>
            <td class="col-fixo" data-label="Data"><?= e(br_date((string) $trip['data'])) ?> <span class="hint"><?= e($trip['hora']) ?></span></td>
            <td class="col-mot" data-label="Motorista"><span class="clip" title="<?= e(nome_motorista($db, $trip['motorista_id'])) ?>"><?= e(nome_motorista($db, $trip['motorista_id'])) ?></span></td>
            <td class="col-fixo" data-label="Valor"><?= e(brl((float) $trip['valor'])) ?></td>
            <td class="col-fixo col-pgto" data-label="Pgto"><?php $pgto = rotulo_forma_pgto($trip); ?><?= $pgto !== '' ? e($pgto) : '—' ?></td>
            <td class="col-fixo" data-label="Status"><?= status_badge((string) $trip['status']) ?></td>
            <td class="actions">
              <a class="btn btn-sm" href="<?= e(href('reservas', ['edit' => $trip['id']] + $keep)) ?>">Editar</a>
              <button class="btn btn-sm" type="button" data-open-dialog="dlg-ver-<?= (int) $trip['id'] ?>" data-reserva-id="<?= (int) $trip['id'] ?>">Ver</button>
              <button class="btn btn-sm" type="button" data-open-dialog="dlg-pdf-<?= (int) $trip['id'] ?>" data-reserva-id="<?= (int) $trip['id'] ?>">PDF</button>
              <form method="post" action="index.php" data-confirm="Enviar a reserva #<?= (int) $trip['id'] ?> de <?= e($trip['cliente']) ?> para o lixo? Ela sai da agenda e do financeiro em aberto, mas pode ser restaurada em até 30 dias."><?= csrf_fields('delete_transfer') ?><input type="hidden" name="id" value="<?= (int) $trip['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager('reservas', $pg, $keep) ?>
</section>
<?php
$draft = null;
$draftErro = '';
if ((string) ($_GET['rascunho'] ?? '') === '1' && !empty($_SESSION['reserva_draft']) && is_array($_SESSION['reserva_draft'])) {
    $draft = $_SESSION['reserva_draft'];
    $draftErro = (string) ($_SESSION['reserva_draft_erro'] ?? '');
    unset($_SESSION['reserva_draft'], $_SESSION['reserva_draft_erro']);
    if ($solForm === 0) {
        $solForm = (int) ($draft['solicitacao_id'] ?? 0);
    }
}
$tipoForm = (string) ($pre['tipo'] ?? 'ida');
if (!in_array($tipoForm, ['ida', 'ida_volta', 'trajetos', 'hora'], true)) {
    $tipoForm = 'ida';
}
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
$docEdit = is_array($edit) ? (string) ($edit['documento'] ?? '') : (string) ($pre['documento'] ?? '');
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
} elseif (!$edit && (float) ($pre['valor'] ?? 0) > 0) {
    $manualEdit = number_format((float) $pre['valor'], 2, ',', '');
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
$extrasEdit = array_map('intval', is_array($edit) ? ($edit['extras'] ?? []) : []);
$pernaForm = 'ida';
$ordemForm = 1;
if (is_array($edit)) {
    $ordemForm = max(1, (int) ($edit['ordem'] ?? 1));
    if ((string) ($edit['tipo'] ?? '') === 'volta') {
        $pernaForm = 'volta';
    } elseif ((string) ($edit['tipo'] ?? '') === 'hora') {
        $pernaForm = 'hora';
    } elseif ((string) ($edit['conjunto'] ?? '') === 'trajetos' || (string) ($edit['tipo'] ?? '') === 'trajetos') {
        $pernaForm = 't0';
    }
}
$timeEdit = array_values(array_unique(array_filter(array_merge([(int) (is_array($edit) ? ($edit['motorista_id'] ?? 0) : 0)], $extrasEdit))));
$frotaEngate = [];
foreach ($db['motoristas'] as $m) {
    $mid = (int) $m['id'];
    if (($m['status'] ?? 'ativo') === 'ativo' || in_array($mid, $timeEdit, true)) {
        $frotaEngate[] = ['id' => $mid, 'nome' => (string) $m['nome']];
    }
}
$catsOk = array_keys(categorias_veiculo());
$catAtual = is_array($edit) ? (string) ($edit['categoria'] ?? 'SED') : (string) ($pre['categoria'] ?? 'SED');
$catEdit = in_array($catAtual, $catsOk, true) ? $catAtual : 'SED';
?>
<dialog id="dlg-reserva" class="dlg-reserva" <?= ($edit || $draft || $convertendo) ? 'open' : '' ?>>
  <form method="post" action="index.php" id="form-reserva" data-mode="<?= $edit ? 'edit' : 'create' ?>" data-perna="<?= e($pernaForm) ?>" data-ordem="<?= (int) $ordemForm ?>">
    <header class="dialog-h dlg-top">
      <div>
        <h2><?= $edit ? 'Editar reserva #' . (int) $edit['id'] : ($convertendo ? 'Converter solicitação #' . (int) $convertendo['id'] : 'Criar Nova Reserva') ?></h2>
        <p><?= $edit ? 'Altere os campos necessários e salve. Use Fechar para voltar à lista sem salvar.' : ($convertendo ? 'Os dados do webhook já estão nos campos. Confira, atribua um motorista e só então conclua.' : 'Preencha os dados para criar uma nova reserva manual.') ?></p>
      </div>
      <div class="dialog-tools">
        <button class="btn btn-sm" type="button" data-close-dialog>Fechar</button>
        <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
      </div>
    </header>
    <div class="dialog-b reserva-scroll">
      <?php if ($draftErro !== ''): ?>
        <div class="flash flash-err" role="alert"><?= e($draftErro) ?> Os dados preenchidos foram mantidos.</div>
      <?php endif; ?>
      <?php if ($convertendo): ?>
        <div class="box box-warn">Conversão da solicitação #<?= (int) $convertendo['id'] ?>. É obrigatório atribuir um motorista da frota. Sem motorista o cadastro não é concluído.</div>
      <?php endif; ?>
      <?= csrf_fields($edit ? 'update_transfer' : 'add_transfer') ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <?php if ($solForm > 0): ?><input type="hidden" name="solicitacao_id" value="<?= (int) $solForm ?>"><?php endif; ?>
      <input type="hidden" name="cliente_id" id="cliente-id" value="<?= (int) ($edit['cliente_id'] ?? 0) ?>">

      <h3 class="sec-title">Informações do Cliente</h3>
      <div class="box">
        <span class="lbl">Origem do cliente</span>
        <div class="origin-row">
          <label><input type="radio" name="origem_cliente" value="cadastrado"<?= $pre === [] ? ' checked' : '' ?>> Cliente cadastrado</label>
          <label><input type="radio" name="origem_cliente" value="novo"<?= $pre !== [] ? ' checked' : '' ?>> NOVO CLIENTE</label>
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
            <input name="cliente" required maxlength="80" value="<?= e($campo('cliente')) ?>" placeholder="Paulo Henrique Correa de Macedo">
          </label>
          <label class="field">CPF/CNPJ *
            <input name="documento" required maxlength="20" value="<?= e($docEdit) ?>" placeholder="000.000.000-00">
          </label>
          <label class="field">Email *
            <input type="email" name="email" required maxlength="80" value="<?= e($campo('email')) ?>" placeholder="financeiroflnsc@gmail.com">
          </label>
          <label class="field">Telefone *
            <input name="telefone" required maxlength="30" value="<?= e($campo('telefone')) ?>" placeholder="(48) 98822-2534">
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
            <input name="embarque" maxlength="180" value="<?= e($campo('origem')) ?>" placeholder="Endereço completo de embarque">
          </label>
          <label class="field">Local de Desembarque (IDA) *
            <input name="desembarque" maxlength="180" value="<?= e($campo('destino')) ?>" placeholder="Endereço completo de destino">
          </label>
          <label class="field">Data do Embarque (IDA) *
            <input type="date" name="data" value="<?= e($campo('data')) ?>" placeholder="dd/mm/aaaa">
          </label>
          <label class="field">Hora
            <input type="time" name="hora" value="<?= e($campo('hora')) ?>" placeholder="--:--">
          </label>
          <label class="field">Número de Passageiros *
            <input type="number" name="pax" min="1" max="40" value="<?= (int) ($campo('pax') !== '' ? $campo('pax') : 1) ?>" placeholder="2">
          </label>
          <label class="field">Cupom
            <input name="cupom" maxlength="40" value="<?= e($campo('cupom')) ?>" placeholder="FLN10">
          </label>
          <label class="field full">Mensagem / Observações
            <textarea name="obs_ida" maxlength="800" placeholder="Duas malas grandes, voo G3 1234"><?= e(($tipoForm === 'ida' || $tipoForm === 'ida_volta') ? $campo('obs') : '') ?></textarea>
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
            <input name="inicio" maxlength="180" value="<?= e($tipoForm === 'hora' ? $campo('origem') : '') ?>" placeholder="Endereço completo">
          </label>
          <label class="field">Ponto de Encerramento
            <input name="encerramento" maxlength="180" value="<?= e($tipoForm === 'hora' ? $campo('destino') : '') ?>" placeholder="Endereço completo">
          </label>
          <label class="field">Data
            <input type="date" name="data_hora" value="<?= e($tipoForm === 'hora' ? $campo('data') : '') ?>" placeholder="dd/mm/aaaa">
          </label>
          <label class="field">Hora
            <input type="time" name="hora_servico" value="<?= e($tipoForm === 'hora' ? $campo('hora') : '') ?>" placeholder="--:--">
          </label>
          <label class="field">Passageiros
            <input type="number" name="pax_hora" min="1" max="40" value="<?= (int) ($tipoForm === 'hora' ? ($campo('pax') !== '' ? $campo('pax') : 1) : 1) ?>" placeholder="2">
          </label>
          <label class="field">Qtd. Horas
            <input name="qtd_horas" maxlength="10" value="<?= e((string) ($edit['qtd_horas'] ?? '')) ?>" placeholder="4">
          </label>
          <label class="field full">Cupom
            <input name="cupom_hora" maxlength="40" value="<?= e($tipoForm === 'hora' ? $campo('cupom') : '') ?>" placeholder="FLN10">
          </label>
          <label class="field full">Itinerário / Observações
            <textarea name="itinerario" maxlength="800" placeholder="Roteiro do dia, paradas e horários"><?= e($tipoForm === 'hora' ? $campo('obs') : '') ?></textarea>
          </label>
        </div>
      </div>

      <h3 class="sec-title">Veículo e Motorista</h3>
      <?php if ($solForm > 0): ?><p class="hint">Obrigatório: arraste um motorista da frota e solte no trecho. A conversão não conclui sem motorista.</p><?php endif; ?>
      <label class="field">Quem fará a viagem? *
        <select name="quem" id="quem-viagem">
          <option value="motorista"<?= ($edit['quem'] ?? 'motorista') !== 'parceiro' ? ' selected' : '' ?>>Motorista</option>
          <option value="parceiro"<?= ($edit['quem'] ?? '') === 'parceiro' ? ' selected' : '' ?>>Parceiro</option>
        </select>
      </label>
      <p class="hint" id="hint-parceiro" hidden>Parceiro não tem vínculo com motorista da plataforma.</p>
      <div id="bloco-motorista">
        <input type="hidden" name="equipe_modo" value="1">
        <input type="hidden" name="repasse" id="repasse-hidden" value="<?= e($edit ? number_format((float) ($edit['repasse'] ?? 0), 2, ',', '') : '0,00') ?>">
        <p class="hint">À esquerda, arraste o motorista e solte no trecho da direita. Só funciona arrastando. Em cada nome, informe o repasse daquele motorista.</p>
        <div class="engate">
          <div class="engate-pool" id="engate-pool">
            <strong>Motoristas</strong>
            <?php foreach ($frotaEngate as $m): ?>
              <button type="button" class="engate-chip" draggable="true" data-id="<?= (int) $m['id'] ?>" data-nome="<?= e($m['nome']) ?>"><?= e($m['nome']) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="engate-alvos" id="engate-alvos"></div>
        </div>
        <div id="engate-hidden"></div>
        <div class="box box-warn" id="aviso-conflito" role="alert" hidden></div>
      </div>

      <h3 class="sec-title">Valor da reserva</h3>
      <label class="field">Definir valor total manualmente
        <input name="valor_manual" id="valor-manual" inputmode="decimal" placeholder="0,00" value="<?= e($manualEdit) ?>">
      </label>
      <div class="total-line">
        <span>Valor Total (a receber do cliente)</span>
        <strong id="total-reserva">R$ 0,00</strong>
      </div>

      <h3 class="sec-title">Formas de pagamento</h3>
      <div class="pay-list">
        <?php foreach (meios_pagamento() as $chave => $rotulo): ?>
          <label class="pay-line">
            <input type="checkbox" name="pag_<?= e($chave) ?>" value="1" data-pag <?= isset($pagEdit[$chave]) ? 'checked' : '' ?>>
            <span><?= e($rotulo) ?></span>
            <input name="pag_valor_<?= e($chave) ?>" inputmode="decimal" placeholder="0,00" value="<?= isset($pagEdit[$chave]) ? e(number_format((float) $pagEdit[$chave], 2, ',', '')) : '' ?>">
          </label>
        <?php endforeach; ?>
      </div>

      <div class="box box-warn">
        <span class="lbl">Faturado *</span>
        <p class="hint">Faturado significa que a empresa paga estas corridas juntas, no fim do mês, por boleto. O valor continua em Contas a receber até a baixa. Corrida paga na hora fica sem esta marca.</p>
        <div class="origin-row">
          <label><input type="radio" name="faturado" value="sim"<?= !empty($edit['faturado']) ? ' checked' : '' ?>> Sim — faturado</label>
          <label><input type="radio" name="faturado" value="nao"<?= empty($edit['faturado']) ? ' checked' : '' ?>> Não — não faturado</label>
        </div>
      </div>
      <label class="extra-row">
        <input type="checkbox" name="esconder" value="1"<?= !empty($edit['esconder_valores']) ? ' checked' : '' ?>>
        <span><strong>Esconder valores</strong><small>O PDF de confirmação nunca leva o repasse do motorista. Com esta opção, o valor cobrado do cliente também sai do PDF e do painel do motorista.</small></span>
      </label>
      <label class="field">Status da reserva
        <select name="status" data-status-inicial="<?= e((string) ($edit['status'] ?? 'pendente')) ?>">
          <?php foreach (['pendente' => 'Pendente', 'reserva' => 'Reserva', 'andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'] as $k => $lab): ?>
            <option value="<?= e($k) ?>"<?= ($edit['status'] ?? 'pendente') === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Em Concluída, com repasse &gt; 0, gera automaticamente a despesa do motorista no Financeiro.</span>
      </label>
      <label class="field">Observações
        <textarea name="obs" maxlength="800" placeholder="Uma informação por linha. Enter quebra a linha.&#10;Ex.: Nome do passageiro&#10;Voo e horário"><?= e($campo('obs')) ?></textarea>
      </label>
    </div>
    <footer class="dlg-foot">
      <button class="btn" type="button" data-close-dialog>Fechar</button>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Salvar reserva' : ($solForm > 0 ? 'Concluir conversão' : 'Criar Reserva') ?></button>
    </footer>
  </form>
</dialog>
<?php
$ocupacao = [];
foreach (ocupacoes_motoristas($db) as $mid => $lista) {
    foreach ($lista as $o) {
        $ocupacao[$mid][] = [$o['trip'], $o['inicio'], $o['fim']];
    }
}
$nomesMot = [];
foreach ($db['motoristas'] as $m) {
    $nomesMot[(int) $m['id']] = (string) $m['nome'];
}
?>
<script type="application/json" id="ocupacao-motoristas"><?= json_encode(['intervalo' => intervalo_motorista($db), 'editando' => (int) ($edit['id'] ?? 0), 'nomes' => $nomesMot, 'ocupacao' => $ocupacao, 'perna' => $pernaForm, 'ordem' => $ordemForm, 'time' => $timeEdit, 'repasse' => $edit ? number_format((float) ($edit['repasse'] ?? 0), 2, ',', '') : '0,00'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php if (is_array($draft)): ?>
<script type="application/json" id="reserva-rascunho"><?= json_encode($draft, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<script>
(function () {
  const form = document.getElementById('form-reserva');
  if (!form) return;
  const dadosOcup = JSON.parse(document.getElementById('ocupacao-motoristas').textContent || '{}');
  const aviso = document.getElementById('aviso-conflito');
  function instante(data, hora) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(data || '')) return null;
    const [a, m, d] = data.split('-').map(Number);
    const [h, mi] = /^\d{2}:\d{2}$/.test(hora || '') ? hora.split(':').map(Number) : [0, 0];
    return Date.UTC(a, m - 1, d, h + 3, mi) / 1000;
  }
  function formatar(seg) {
    const dt = new Date((seg - 3 * 3600) * 1000);
    const p = (n) => String(n).padStart(2, '0');
    return p(dt.getUTCDate()) + '/' + p(dt.getUTCMonth() + 1) + ' às ' + p(dt.getUTCHours()) + ':' + p(dt.getUTCMinutes());
  }
  function motoresDaChave(chave) {
    return Array.from(form.querySelectorAll('input[name="equipe[' + chave + '][]"]')).map((c) => Number(c.value)).filter((n) => n > 0);
  }
  function conflitosAtuais() {
    const el = form.elements;
    const tipoAtual = el.tipo_viagem ? el.tipo_viagem.value : 'ida';
    if (el.quem && el.quem.value === 'parceiro') return [];
    const trechos = [];
    if (tipoAtual === 'hora') {
      const quando = instante(el.data_hora.value, el.hora_servico.value);
      if (quando !== null) trechos.push([quando, motoresDaChave('hora')]);
    } else if (tipoAtual === 'trajetos') {
      form.querySelectorAll('#trajeto-lista .trajeto').forEach((box, i) => {
        const quando = instante(box.querySelector('[name$="[data]"]')?.value, box.querySelector('[name$="[hora]"]')?.value);
        if (quando !== null) trechos.push([quando, motoresDaChave('t' + i)]);
      });
    } else {
      const ida = instante(el.data.value, el.hora.value);
      if (ida !== null && motoresDaChave('ida').length) trechos.push([ida, motoresDaChave('ida')]);
      if (tipoAtual === 'ida_volta' && !(el.data_volta && el.data_volta.closest('[hidden]'))) {
        const volta = instante(el.data_volta.value || el.data.value, el.hora_volta.value || el.hora.value);
        if (volta !== null) trechos.push([volta, motoresDaChave('volta')]);
      } else if (motoresDaChave('volta').length && ida !== null && !motoresDaChave('ida').length) {
        trechos.push([ida, motoresDaChave('volta')]);
      }
    }
    const dur = (dadosOcup.intervalo || 120) * 60;
    const achados = [];
    trechos.forEach(([inicio, mids]) => {
      mids.filter((m) => m > 0).forEach((mid) => {
        (dadosOcup.ocupacao[mid] || []).forEach(([trip, ini, fim]) => {
          if (trip === dadosOcup.editando) return;
          if (inicio < fim && ini < inicio + dur) {
            achados.push((dadosOcup.nomes[mid] || 'Motorista') + ' já tem a reserva #' + trip + ' em ' + formatar(ini) + '.');
          }
        });
      });
    });
    return Array.from(new Set(achados));
  }
  function syncConflito() {
    const lista = conflitosAtuais();
    aviso.hidden = lista.length === 0;
    aviso.textContent = lista.length ? 'Conflito de agenda: ' + lista.join(' ') + ' O intervalo mínimo entre viagens do mesmo motorista é de ' + dadosOcup.intervalo + ' minutos. Escolha outro motorista ou outro horário.' : '';
    return lista.length === 0;
  }
  form.addEventListener('change', syncConflito);
  form.addEventListener('input', (ev) => { if (ev.target.type === 'date' || ev.target.type === 'time') syncConflito(); });
  form.addEventListener('submit', (ev) => {
    if (typeof syncRepasseHidden === 'function') syncRepasseHidden();
    if (!syncConflito()) {
      ev.preventDefault();
      ev.stopImmediatePropagation();
      aviso.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  }, true);
  syncConflito();
  const tipo = document.getElementById('tipo-viagem');
  const quem = document.getElementById('quem-viagem');
  const busca = document.getElementById('busca-cliente');
  const lista = document.getElementById('client-list');
  const blocoBusca = document.getElementById('bloco-busca');
  const totalEl = document.getElementById('total-reserva');
  const editing = form.dataset.mode === 'edit';
  const alvos = document.getElementById('engate-alvos');
  const oculto = document.getElementById('engate-hidden');
  const repasseHidden = document.getElementById('repasse-hidden');
  const times = {};
  const repasses = {};
  if (dadosOcup.time && dadosOcup.time.length && dadosOcup.perna) {
    times[dadosOcup.perna] = dadosOcup.time.slice();
    if (!repasses[dadosOcup.perna]) repasses[dadosOcup.perna] = {};
    if (dadosOcup.time[0]) repasses[dadosOcup.perna][dadosOcup.time[0]] = dadosOcup.repasse || '0,00';
  }

  function tituloSlot(chave, i) {
    if (chave === 'volta') return 'Volta';
    if (chave === 'hora') return 'Por hora';
    if (chave === 'ida') return editing && form.dataset.perna === 'ida' && tipo.value === 'ida_volta' ? 'Ida' : (tipo.value === 'ida' ? 'Somente ida' : 'Ida');
    const ordem = editing ? Number(form.dataset.ordem || 1) : i + 1;
    return 'Trajeto ' + ordem;
  }
  function chavesAtuais() {
    if (editing) return [form.dataset.perna || 'ida'];
    const atual = tipo.value;
    if (atual === 'hora') return ['hora'];
    if (atual === 'ida_volta') return ['ida', 'volta'];
    if (atual === 'trajetos') {
      return Array.from(form.querySelectorAll('#trajeto-lista .trajeto')).map((_, i) => 't' + i);
    }
    return ['ida'];
  }
  function lerDinheiroRepasse(v) {
    let s = String(v || '').replace(/R\$|\s/g, '');
    if (s.includes(',') && s.includes('.')) s = s.replace(/\./g, '').replace(',', '.');
    else s = s.replace(',', '.');
    const n = parseFloat(s);
    return Number.isFinite(n) ? n : 0;
  }
  function syncRepasseHidden() {
    if (!repasseHidden) return;
    let soma = 0;
    form.querySelectorAll('.engate-repasse-input').forEach((campo) => {
      soma += lerDinheiroRepasse(campo.value);
    });
    repasseHidden.value = soma.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function guardarRepassesVisuais() {
    form.querySelectorAll('.engate-posto').forEach((posto) => {
      const chave = posto.closest('.engate-slot')?.dataset.chave;
      const id = Number(posto.dataset.id || 0);
      const campo = posto.querySelector('.engate-repasse-input');
      if (!chave || !id || !campo) return;
      if (!repasses[chave]) repasses[chave] = {};
      repasses[chave][id] = campo.value || '0,00';
    });
  }
  function sincronizarEquipe() {
    oculto.replaceChildren();
    chavesAtuais().forEach((chave) => {
      (times[chave] || []).forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'equipe[' + chave + '][]';
        input.value = String(id);
        oculto.appendChild(input);
      });
    });
    syncRepasseHidden();
  }
  function desenharAlvos() {
    if (!alvos) return;
    guardarRepassesVisuais();
    alvos.replaceChildren();
    chavesAtuais().forEach((chave, i) => {
      const zona = document.createElement('div');
      zona.className = 'engate-slot';
      zona.dataset.chave = chave;
      const titulo = document.createElement('strong');
      titulo.textContent = tituloSlot(chave, i);
      zona.appendChild(titulo);
      const lista = document.createElement('div');
      lista.className = 'engate-postos';
      (times[chave] || []).forEach((id, ordem) => {
        const nome = (dadosOcup.nomes && dadosOcup.nomes[id]) || 'Motorista';
        const posto = document.createElement('div');
        posto.className = 'engate-posto';
        posto.dataset.id = String(id);
        const topo = document.createElement('div');
        topo.className = 'engate-posto-topo';
        const nomeEl = document.createElement('span');
        nomeEl.className = 'engate-posto-nome';
        nomeEl.textContent = nome + (ordem === 0 ? ' · principal' : '');
        const tirar = document.createElement('button');
        tirar.type = 'button';
        tirar.className = 'engate-tirar';
        tirar.dataset.id = String(id);
        tirar.setAttribute('aria-label', 'Tirar ' + nome);
        tirar.textContent = '×';
        topo.appendChild(nomeEl);
        topo.appendChild(tirar);
        const caixa = document.createElement('label');
        caixa.className = 'engate-repasse';
        caixa.textContent = 'Repasse (R$)';
        const campo = document.createElement('input');
        campo.type = 'text';
        campo.inputMode = 'decimal';
        campo.className = 'engate-repasse-input';
        campo.placeholder = '0,00';
        campo.value = (repasses[chave] && repasses[chave][id]) ? repasses[chave][id] : (ordem === 0 && editing ? (dadosOcup.repasse || '0,00') : '0,00');
        campo.addEventListener('input', syncRepasseHidden);
        caixa.appendChild(campo);
        posto.appendChild(topo);
        posto.appendChild(caixa);
        lista.appendChild(posto);
      });
      if (!(times[chave] || []).length) {
        const vazio = document.createElement('span');
        vazio.className = 'engate-vazio';
        vazio.textContent = 'Arraste e solte o motorista aqui';
        lista.appendChild(vazio);
      }
      zona.appendChild(lista);
      alvos.appendChild(zona);
    });
    sincronizarEquipe();
    syncConflito();
  }
  function porNoTrecho(chave, id) {
    if (!chave || !id) return;
    const lista = times[chave] || [];
    if (!lista.includes(id)) lista.push(id);
    times[chave] = lista;
    if (!repasses[chave]) repasses[chave] = {};
    if (repasses[chave][id] === undefined) repasses[chave][id] = '0,00';
    desenharAlvos();
  }
  function soltarEm(event, id) {
    const zona = document.elementFromPoint(event.clientX, event.clientY)?.closest('.engate-slot');
    if (zona) porNoTrecho(zona.dataset.chave, id);
  }
  if (alvos) {
    document.getElementById('engate-pool')?.addEventListener('dragstart', (event) => {
      const chip = event.target.closest('.engate-chip');
      if (!chip || !event.dataTransfer) return;
      event.dataTransfer.setData('text/plain', chip.dataset.id || '');
      event.dataTransfer.effectAllowed = 'copy';
    });
    alvos.addEventListener('dragover', (event) => {
      if (event.target.closest('.engate-slot')) {
        event.preventDefault();
        event.target.closest('.engate-slot').classList.add('is-over');
      }
    });
    alvos.addEventListener('dragleave', (event) => {
      event.target.closest('.engate-slot')?.classList.remove('is-over');
    });
    alvos.addEventListener('drop', (event) => {
      const zona = event.target.closest('.engate-slot');
      if (!zona) return;
      event.preventDefault();
      zona.classList.remove('is-over');
      porNoTrecho(zona.dataset.chave, Number(event.dataTransfer?.getData('text/plain') || 0));
    });
    alvos.addEventListener('click', (event) => {
      const tirar = event.target.closest('.engate-tirar');
      if (!tirar) return;
      const zona = tirar.closest('.engate-slot');
      const id = Number(tirar.dataset.id || 0);
      const chave = zona?.dataset.chave;
      times[chave] = (times[chave] || []).filter((n) => n !== id);
      if (repasses[chave]) delete repasses[chave][id];
      desenharAlvos();
    });
    let arraste = null;
    document.getElementById('engate-pool')?.addEventListener('pointerdown', (event) => {
      const chip = event.target.closest('.engate-chip');
      if (!chip || (event.pointerType === 'mouse' && event.button !== 0)) return;
      arraste = { id: Number(chip.dataset.id || 0), x: event.clientX, y: event.clientY, foi: false };
    });
    window.addEventListener('pointermove', (event) => {
      if (!arraste) return;
      if (Math.abs(event.clientX - arraste.x) + Math.abs(event.clientY - arraste.y) > 8) arraste.foi = true;
      document.querySelectorAll('.engate-slot').forEach((zona) => zona.classList.remove('is-over'));
      if (arraste.foi) document.elementFromPoint(event.clientX, event.clientY)?.closest('.engate-slot')?.classList.add('is-over');
    });
    window.addEventListener('pointerup', (event) => {
      document.querySelectorAll('.engate-slot').forEach((zona) => zona.classList.remove('is-over'));
      if (!arraste) return;
      const id = arraste.id;
      const foi = arraste.foi;
      arraste = null;
      if (foi) soltarEm(event, id);
    });
  }

  function syncTipo() {
    const atual = tipo.value;
    form.querySelectorAll('[data-show]').forEach((el) => {
      const ok = el.dataset.show.split(' ').includes(atual);
      const soCriar = el.hasAttribute('data-create-only') && editing;
      el.hidden = !ok || soCriar;
    });
    desenharAlvos();
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
  function semAcento(s) {
    return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }
  function filtrarClientes() {
    const q = semAcento(busca.value.trim());
    const qDig = q.replace(/\D/g, '');
    lista.querySelectorAll('.client-pick').forEach((btn) => {
      const nome = semAcento(btn.dataset.nome);
      const email = semAcento(btn.dataset.email);
      const tel = String(btn.dataset.tel || '').replace(/\D/g, '');
      const doc = String(btn.dataset.doc || '').replace(/\D/g, '');
      const casa = q === ''
        || (nome + ' ' + email).includes(q)
        || (qDig.length >= 2 && (tel.includes(qDig) || doc.includes(qDig)));
      btn.hidden = !casa;
      btn.classList.toggle('is-off', !casa);
    });
    lista.scrollTop = 0;
  }
  busca.addEventListener('input', filtrarClientes);
  busca.addEventListener('search', filtrarClientes);
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
    desenharAlvos();
  });
  function campo(rotulo, nome, tipo, ph) {
    return '<label class="field">' + rotulo + '<input name="' + nome + '" type="' + tipo + '" placeholder="' + ph + '"' + (tipo === 'number' ? ' min="1" max="40" value="1"' : '') + '></label>';
  }
  const obsIda = form.elements.obs_ida;
  if (obsIda && form.elements.obs.value === '' && obsIda.value !== '') form.elements.obs.value = obsIda.value;
  form.addEventListener('submit', (event) => {
    const sol = form.elements.solicitacao_id;
    if (sol && String(sol.value || '') !== '' && String(sol.value) !== '0') {
      const tipoAtual = form.elements.tipo_viagem ? form.elements.tipo_viagem.value : 'ida';
      const temNa = (chave) => Array.isArray(times[chave]) && times[chave].length > 0;
      let temMotorista = form.elements.quem && form.elements.quem.value !== 'parceiro';
      if (!temMotorista) {
        temMotorista = false;
      } else if (tipoAtual === 'hora') {
        temMotorista = temNa('hora');
      } else if (tipoAtual === 'trajetos') {
        const caixas = form.querySelectorAll('#trajeto-lista .trajeto');
        temMotorista = caixas.length > 0;
        caixas.forEach((box, i) => {
          if (!temNa('t' + i)) temMotorista = false;
        });
      } else {
        temMotorista = temNa('ida');
        if (tipoAtual === 'ida_volta' && !temNa('volta')) temMotorista = false;
      }
      if (!temMotorista) {
        event.preventDefault();
        event.stopPropagation();
        aviso.hidden = false;
        aviso.textContent = 'Atribua um motorista da frota em cada trecho para concluir a conversão.';
        return;
      }
    }
    sincronizarEquipe();
    const btnSalvar = form.querySelector('footer [type="submit"]');
    if (btnSalvar instanceof HTMLButtonElement) btnSalvar.disabled = true;
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
  function aplicarRascunho(draft) {
    if (!draft || typeof draft !== 'object') return;
    const ignorar = { csrf: 1, action: 1, back: 1, confirmar: 1 };
    const trechos = Array.isArray(draft.trajetos) ? draft.trajetos.length : 0;
    const listaT = document.getElementById('trajeto-lista');
    const addBtn = document.getElementById('add-trajeto');
    while (listaT && addBtn && listaT.querySelectorAll('.trajeto').length < trechos) {
      addBtn.click();
    }
    function setNome(nome, valor) {
      const els = form.querySelectorAll('[name="' + nome.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
      if (!els.length) return;
      const primeiro = els[0];
      if (primeiro.type === 'checkbox' || primeiro.type === 'radio') {
        const valores = Array.isArray(valor) ? valor.map(String) : [String(valor)];
        els.forEach((el) => { el.checked = valores.includes(String(el.value)); });
        return;
      }
      primeiro.value = valor == null ? '' : String(valor);
      primeiro.dispatchEvent(new Event('input', { bubbles: true }));
      primeiro.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function walk(prefix, valor) {
      if (prefix !== '' && ignorar[prefix.split(/[\[\]]/)[0]]) return;
      if (valor == null) return;
      if (Array.isArray(valor)) {
        if (valor.every((v) => typeof v !== 'object' || v === null)) {
          const nome = prefix + '[]';
          form.querySelectorAll('[name="' + nome.replace(/"/g, '\\"') + '"]').forEach((el) => {
            if (el.type === 'hidden') el.remove();
          });
          const checks = form.querySelectorAll('[name="' + nome.replace(/"/g, '\\"') + '"]');
          if (checks.length) {
            const vals = valor.map(String);
            checks.forEach((el) => { el.checked = vals.includes(String(el.value)); });
            return;
          }
          const alvo = form.querySelector('#engate-hidden') || form;
          valor.forEach((v) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = nome;
            input.value = String(v);
            alvo.appendChild(input);
          });
          return;
        }
        valor.forEach((item, i) => walk(prefix + '[' + i + ']', item));
        return;
      }
      if (typeof valor === 'object') {
        Object.keys(valor).forEach((k) => walk(prefix === '' ? k : prefix + '[' + k + ']', valor[k]));
        return;
      }
      setNome(prefix, valor);
    }
    walk('', draft);
  }
  const noRascunho = document.getElementById('reserva-rascunho');
  if (noRascunho) {
    try { aplicarRascunho(JSON.parse(noRascunho.textContent || '{}')); } catch (err) { /* mantém o formulário */ }
  }
  syncTipo();
  syncQuem();
  syncOrigem();
  syncTotal();
  if (typeof desenharAlvos === 'function') desenharAlvos();
  if (typeof filtrarClientes === 'function') filtrarClientes();
})();
</script>
