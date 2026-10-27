<?php
declare(strict_types=1);
defined('APP') || exit;
$q = trim((string) ($_GET['q'] ?? ''));
$rows = array_values(array_filter($db['mot_solic'], static fn(array $r): bool => ($r['status'] ?? '') === 'nova'));
if ($q !== '') {
    $rows = array_values(array_filter($rows, static fn(array $r): bool => str_contains(mb_strtolower($r['nome'] . ' ' . $r['telefone'] . ' ' . ($r['email'] ?? '')), mb_strtolower($q))));
}
view_head('', 'Candidatos enviados pelo webhook Motorista. Com a automação ativa, cada envio aparece aqui. Quem já está na frota fica em Cadastros.');
?>
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="p" value="mot-solicitacoes">
  <label>Busca <input name="q" value="<?= e($q) ?>" placeholder="Buscar por nome, e-mail ou telefone"></label>
  <button class="btn btn-sm" type="submit">Buscar</button>
</form>
<section class="card">
  <?php if ($rows === []): ?>
    <?= empty_box('file', 'Nenhuma solicitação em aberto', 'Ligue o formulário do site à URL do webhook com a automação ativa. Depois de aprovar, o motorista entra em Cadastros.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nome</th><th>Telefone</th><th>Cidade</th><th>CPF</th><th>Mensagem</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e($row['nome']) ?></td>
              <td><?= e($row['telefone']) ?></td>
              <td><?= e($row['cidade'] ?? '') ?></td>
              <td><?= e($row['cpf'] ?? '') ?></td>
              <td><?= e($row['mensagem'] ?? '') ?></td>
              <td class="actions">
                <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Aprovar a solicitação de <?= e($row['nome'] ?? 'motorista') ?>?" data-confirm-2="Confirme novamente: esta solicitação ficará aprovada."><?= csrf_fields('set_mot_solic') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="status" value="aprovada"><button class="btn btn-sm btn-primary" type="submit">Aprovar</button></form>
                <form method="post" action="index.php" data-confirm-vezes="2" data-confirm="Recusar a solicitação de <?= e($row['nome'] ?? 'motorista') ?>? O cadastro não entra na frota." data-confirm-2="Confirme novamente: esta solicitação ficará recusada."><?= csrf_fields('set_mot_solic') ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="status" value="recusada"><button class="btn btn-sm" type="submit">Recusar</button></form>
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
    <label class="field">Telefone <input name="telefone" required maxlength="30" placeholder="(47) 99100-2020"></label>
    <label class="field">Cidade <input name="cidade" maxlength="60" placeholder="Balneário Camboriú"></label>
    <label class="field">CNH <input name="cnh" maxlength="20" placeholder="01234567890"></label>
    <label class="field">Categoria <select name="categoria"><?php foreach (['B', 'C', 'D', 'E'] as $cat): ?><option><?= $cat ?></option><?php endforeach; ?></select></label>
    <label class="field full">Mensagem <textarea name="mensagem" maxlength="300" placeholder="Três anos com transfers executivos no litoral de Santa Catarina."></textarea></label>
    <div class="form-actions full"><button class="btn btn-primary" type="submit">Registrar</button></div>
  </form>
</section>
