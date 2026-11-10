<?php
declare(strict_types=1);
defined('APP') || exit;
$q = trim((string) ($_GET['q'] ?? ''));
$rows = array_values(array_filter($db['mot_solic'], static fn(array $r): bool => ($r['status'] ?? '') === 'nova'));
if ($q !== '') {
    $rows = array_values(array_filter($rows, static fn(array $r): bool => str_contains(mb_strtolower($r['nome'] . ' ' . $r['telefone'] . ' ' . ($r['email'] ?? '') . ' ' . ($r['veiculo_placa'] ?? '')), mb_strtolower($q))));
}
$parceriaLabel = static fn(string $p): string => match ($p) {
    'terceirizado' => 'Terceirizado',
    'ambos' => 'Próprio ou terceirizado',
    default => 'Veículo próprio',
};
view_head('', 'Candidatos do site e do webhook Motorista. Após a reunião com o Matheus, aprove para liberar login e veículo na frota.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="mot-solicitacoes">
  <label>Busca <input name="q" value="<?= e($q) ?>" placeholder="Nome, e-mail, telefone ou placa"></label>
  <button class="btn btn-sm" type="submit">Buscar</button>
  <a class="btn btn-sm" href="<?= e(href('seja-motorista')) ?>" target="_blank" rel="noopener">Abrir página pública</a>
</form>
<section class="card">
  <?php if ($rows === []): ?>
    <?= empty_box('file', 'Nenhuma solicitação em aberto', 'Use o formulário do site (Seja motorista) ou a URL do webhook com a automação ativa. Depois de aprovar, o motorista entra em Cadastros.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table-lista">
        <thead><tr><th>Candidato</th><th>Contato</th><th>Veículo</th><th>Parceria / reunião</th><th>Mensagem</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
              $veiculo = trim(implode(' · ', array_filter([
                  (string) ($row['veiculo_modelo'] ?? ''),
                  (string) ($row['veiculo_placa'] ?? ''),
                  (($row['veiculo_ano'] ?? '') !== '' ? (string) $row['veiculo_ano'] : ''),
                  (($row['veiculo_cor'] ?? '') !== '' ? (string) $row['veiculo_cor'] : ''),
                  ((int) ($row['veiculo_lugares'] ?? 0) > 0 ? ((int) $row['veiculo_lugares'] . ' lugares') : ''),
              ])));
            ?>
            <tr>
              <td>
                <strong><?= e($row['nome']) ?></strong>
                <div class="muted"><?= e(($row['cidade'] ?? '') !== '' ? (string) $row['cidade'] : '—') ?> · CPF <?= e(($row['cpf'] ?? '') !== '' ? (string) $row['cpf'] : '—') ?></div>
                <div class="muted">CNH <?= e(($row['cnh'] ?? '') !== '' ? (string) $row['cnh'] : '—') ?> · cat. <?= e((string) ($row['categoria'] ?? 'B')) ?> · <?= e((string) ($row['origem'] ?? 'manual')) ?></div>
              </td>
              <td>
                <?= e($row['telefone']) ?>
                <?php if (($row['email'] ?? '') !== ''): ?><div class="muted"><?= e((string) $row['email']) ?></div><?php endif; ?>
              </td>
              <td><?= $veiculo !== '' ? e($veiculo) : '—' ?></td>
              <td>
                <?= e($parceriaLabel((string) ($row['parceria'] ?? 'proprio'))) ?>
                <?php if (($row['reuniao_pref'] ?? '') !== ''): ?><div class="muted">Reunião: <?= e((string) $row['reuniao_pref']) ?></div><?php endif; ?>
              </td>
              <td><?= e($row['mensagem'] ?? '') ?></td>
              <td class="actions">
                <form method="post" action="index.php" data-confirm="Aprovar a solicitação de <?= e($row['nome'] ?? 'motorista') ?>?"><?= csrf_fields('set_mot_solic') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="status" value="aprovada"><button class="btn btn-sm btn-primary" type="submit">Aprovar</button></form>
                <form method="post" action="index.php" data-confirm="Recusar a solicitação de <?= e($row['nome'] ?? 'motorista') ?>? O cadastro não entra na frota."><?= csrf_fields('set_mot_solic') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="status" value="recusada"><button class="btn btn-sm" type="submit">Recusar</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Registrar candidato manualmente</h2></header>
  <form method="post" action="index.php" class="form-grid">
    <?= csrf_fields('add_mot_solic') ?>
    <label class="field">Nome <input name="nome" required maxlength="80" placeholder="Ana Souza"></label>
    <label class="field">Telefone <input name="telefone" required maxlength="30" placeholder="(48) 99100-2020"></label>
    <label class="field">E-mail <input name="email" type="email" maxlength="80" placeholder="ana@email.com"></label>
    <label class="field">Cidade <input name="cidade" maxlength="60" placeholder="Florianópolis"></label>
    <label class="field">CPF <input name="cpf" maxlength="20" placeholder="000.000.000-00"></label>
    <label class="field">CNH <input name="cnh" maxlength="20" placeholder="01234567890"></label>
    <label class="field">Categoria <select name="categoria"><?php foreach (['B', 'C', 'D', 'E'] as $cat): ?><option><?= $cat ?></option><?php endforeach; ?></select></label>
    <label class="field">Parceria <select name="parceria"><option value="proprio">Veículo próprio</option><option value="terceirizado">Terceirizado</option><option value="ambos">Ambos</option></select></label>
    <label class="field">Modelo do veículo <input name="veiculo_modelo" maxlength="60" placeholder="Toyota Corolla"></label>
    <label class="field">Placa <input name="veiculo_placa" maxlength="12" placeholder="ABC1D23"></label>
    <label class="field">Ano <input name="veiculo_ano" maxlength="4" placeholder="2022"></label>
    <label class="field">Cor <input name="veiculo_cor" maxlength="30" placeholder="Preto"></label>
    <label class="field">Lugares <input name="veiculo_lugares" type="number" min="1" max="20" placeholder="4"></label>
    <label class="field">Preferência de reunião <input name="reuniao_pref" maxlength="120" placeholder="Terça à tarde"></label>
    <label class="field full">Mensagem <textarea name="mensagem" maxlength="500" placeholder="Experiência com transfer aeroporto e hotel."></textarea></label>
    <div class="form-actions full"><button class="btn btn-primary" type="submit">Registrar</button></div>
  </form>
</section>
