<?php
declare(strict_types=1);
defined('APP') || exit;

$isReserva = $current === 'reservas';
$st = (string) ($_GET['st'] ?? ($isReserva ? 'reserva' : 'fila'));
$allowed = $isReserva ? ['reserva'] : ['fila', 'pendente', 'andamento', 'concluida', 'cancelada', 'todas'];
if (!in_array($st, $allowed, true)) {
    $st = $isReserva ? 'reserva' : 'fila';
}

$rows = array_values(array_filter($db['transfers'], static function (array $t) use ($st, $isReserva): bool {
    if ($isReserva) {
        return $t['status'] === 'reserva';
    }
    return match ($st) {
        'pendente' => $t['status'] === 'pendente',
        'andamento' => $t['status'] === 'andamento',
        'concluida' => $t['status'] === 'concluida',
        'cancelada' => $t['status'] === 'cancelada',
        'todas' => true,
        default => in_array($t['status'], ['pendente', 'andamento'], true),
    };
}));
usort($rows, static fn(array $a, array $b): int => [$a['data'], $a['hora']] <=> [$b['data'], $b['hora']]);

$nomeMot = static function (array $db, $id): string {
    if (!$id) {
        return 'A definir';
    }
    foreach ($db['motoristas'] as $m) {
        if ((int) $m['id'] === (int) $id) {
            return $m['nome'];
        }
    }
    return 'A definir';
};
$nomeVei = static function (array $db, $id): string {
    if (!$id) {
        return 'Sem veículo';
    }
    foreach ($db['veiculos'] as $v) {
        if ((int) $v['id'] === (int) $id) {
            return $v['apelido'] . ' · ' . $v['placa'];
        }
    }
    return 'Sem veículo';
};

$actionBtn = static function (int $id, string $status, string $label, string $class = '') {
    $nome = status_label($status);
    echo '<form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Marcar a reserva #' . $id . ' como ' . e($nome) . '?" data-confirm-2="Confirme novamente: a reserva #' . $id . ' ficará como ' . e($nome) . '.">';
    echo csrf_fields('set_transfer_status');
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<input type="hidden" name="status" value="' . e($status) . '">';
    echo '<button class="btn btn-sm ' . e($class) . '" type="submit">' . e($label) . '</button>';
    echo '</form>';
};

view_head($isReserva ? '' : '<a class="btn btn-primary" href="#nova">Nova solicitação</a>');
?>
<?php if (!$isReserva): ?>
  <div class="toolbar">
    <div class="chips">
      <?php foreach (['fila' => 'Na fila', 'pendente' => 'Pendentes', 'andamento' => 'Andamento', 'concluida' => 'Concluídas', 'cancelada' => 'Canceladas', 'todas' => 'Todas'] as $key => $label): ?>
        <a class="chip<?= $st === $key ? ' active' : '' ?>" href="<?= e(href('solicitacoes', ['st' => $key])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <input class="search" type="search" placeholder="Buscar por cliente, origem ou destino" data-search="#tbl-viagens">
  </div>
<?php endif; ?>

<section class="card">
  <?php if ($rows === []): ?>
    <?= empty_box($isReserva ? 'book' : 'file', $isReserva ? 'Nenhuma reserva' : 'Nenhuma solicitação neste filtro', $isReserva ? 'Reserve uma solicitação pendente para ela aparecer aqui.' : 'As viagens pendentes e em andamento aparecem na fila.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table id="tbl-viagens">
        <thead>
          <tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>Trajeto</th>
            <th>Quando</th>
            <th>Valor</th>
            <th>Equipe</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $trip): ?>
            <tr>
              <td>#<?= (int) $trip['id'] ?></td>
              <td>
                <strong><?= e($trip['cliente']) ?></strong>
                <div class="hint"><?= e($trip['telefone'] ?: plural((int) $trip['pax'], 'passageiro', 'passageiros')) ?></div>
              </td>
              <td>
                <div><?= e($trip['origem']) ?></div>
                <div class="hint">→ <?= e($trip['destino']) ?></div>
              </td>
              <td><?= e(br_date($trip['data'])) ?><div class="hint"><?= e($trip['hora']) ?></div></td>
              <td class="num"><?= e(brl((float) $trip['valor'])) ?></td>
              <td>
                <form method="post" action="index.php">
                  <?= csrf_fields('assign_transfer') ?>
                  <input type="hidden" name="id" value="<?= (int) $trip['id'] ?>">
                  <select class="inline-select" name="motorista_id" aria-label="Motorista">
                    <option value="0">Sem motorista</option>
                    <?php foreach ($db['motoristas'] as $mot): ?>
                      <option value="<?= (int) $mot['id'] ?>"<?= sel($trip['motorista_id'], $mot['id']) ?>><?= e($mot['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select class="inline-select" name="veiculo_id" aria-label="Veículo">
                    <option value="0">Sem veículo</option>
                    <?php foreach ($db['veiculos'] as $vei): ?>
                      <option value="<?= (int) $vei['id'] ?>"<?= sel($trip['veiculo_id'], $vei['id']) ?>><?= e($vei['placa']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm" type="submit">Salvar</button>
                </form>
                <div class="hint"><?= e($nomeMot($db, $trip['motorista_id'])) ?> · <?= e($nomeVei($db, $trip['veiculo_id'])) ?></div>
              </td>
              <td><?= status_badge($trip['status']) ?></td>
              <td>
                <div class="actions">
                  <?php if ($trip['status'] === 'pendente'): ?>
                    <?php $actionBtn((int) $trip['id'], 'reserva', 'Reservar', 'btn-primary'); ?>
                    <?php $actionBtn((int) $trip['id'], 'andamento', 'Iniciar'); ?>
                  <?php elseif ($trip['status'] === 'reserva'): ?>
                    <?php $actionBtn((int) $trip['id'], 'andamento', 'Iniciar', 'btn-primary'); ?>
                    <?php $actionBtn((int) $trip['id'], 'pendente', 'Voltar'); ?>
                  <?php endif; ?>
                  <?php if (in_array($trip['status'], ['pendente', 'reserva', 'andamento'], true)): ?>
                    <?php $actionBtn((int) $trip['id'], 'concluida', 'Concluir'); ?>
                    <?php $actionBtn((int) $trip['id'], 'cancelada', 'Cancelar', 'btn-danger'); ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if (!$isReserva): ?>
  <section class="card" id="nova" style="margin-top:14px">
    <header class="card-h"><h2><?= icon('plus') ?> Nova solicitação</h2></header>
    <form method="post" action="index.php" class="form-grid">
      <?= csrf_fields('add_transfer') ?>
      <label class="field">Cliente
        <input name="cliente" list="lista-clientes" required maxlength="80" placeholder="Felipe Goulart">
      </label>
      <datalist id="lista-clientes">
        <?php foreach ($db['clientes'] as $cliente): ?>
          <option value="<?= e($cliente['nome']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <label class="field">Telefone <input name="telefone" maxlength="30" inputmode="tel" placeholder="(47) 99900-1041"></label>
      <label class="field">Origem <input name="origem" required maxlength="180" placeholder="Aeroporto Hercílio Luz, Florianópolis"></label>
      <label class="field">Destino <input name="destino" required maxlength="180" placeholder="Rua Felipe Schmidt, 100, Centro, Florianópolis"></label>
      <label class="field">Data <input type="date" name="data" required value="<?= e(today()->modify('+1 day')->format('Y-m-d')) ?>" placeholder="28/10/2026"></label>
      <label class="field">Hora <input type="time" name="hora" required value="08:00" placeholder="08:00"></label>
      <label class="field">Passageiros <input type="number" name="pax" min="1" max="40" value="2" required placeholder="2"></label>
      <label class="field">Valor <input name="valor" required inputmode="decimal" placeholder="180,00"></label>
      <label class="field">Canal
        <select name="canal">
          <option value="transfer">Transfer</option>
          <option value="grupos">Grupos</option>
          <option value="motoristas">Motoristas</option>
        </select>
      </label>
      <label class="field full">Observação <input name="obs" maxlength="300" placeholder="Voo G3 1234, duas malas grandes"></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Registrar solicitação</button></div>
    </form>
  </section>
<?php endif; ?>
