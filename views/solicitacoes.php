<?php
declare(strict_types=1);
defined('APP') || exit;
$rows = $db['inbox'];
usort($rows, static fn(array $a, array $b): int => ((int) $b['id']) <=> ((int) $a['id']));
$dominios = webhook_dominios($db);
view_head(
    '<a class="btn btn-sm" href="' . e(href('configuracoes')) . '#webhook-dominios">Domínios autorizados</a> <a class="btn btn-sm" href="' . e(href('solicitacoes', ['csv' => 1])) . '">Exportar CSV</a>',
    'Registros recebidos via webhook do site (' . count($rows) . ')'
);
?>
<?php if ($dominios === []): ?>
  <div class="flash flash-err" role="status">Nenhum domínio autorizado. O webhook recusa toda solicitação até você cadastrar a origem em Configurações.</div>
<?php endif; ?>
<section class="card">
  <?php if ($rows === []): ?>
    <?= empty_box('file', 'Nenhuma solicitação recebida', 'Quando um domínio autorizado enviar um pedido para o webhook ativo, ele aparece aqui.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table-lista">
        <thead><tr><th>Quando</th><th>Cliente</th><th>Trajeto</th><th>Data</th><th>Valor</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php $sid = (int) ($row['id'] ?? 0); ?>
            <tr>
              <td><?= e((string) ($row['criado_em'] ?? '')) ?></td>
              <td><?= e((string) ($row['cliente'] ?? '')) ?><div class="hint"><?= e((string) ($row['telefone'] ?? '')) ?> <?= e((string) ($row['email'] ?? '')) ?></div></td>
              <td><?= e((string) ($row['origem'] ?? '')) ?> → <?= e((string) ($row['destino'] ?? '')) ?></td>
              <td><?= e((string) ($row['data'] ?? '')) ?> <?= e((string) ($row['hora'] ?? '')) ?></td>
              <td><?= e(brl((float) ($row['valor'] ?? 0))) ?></td>
              <td class="actions">
                <button class="btn btn-sm" type="button" data-open-dialog="dlg-sol-<?= $sid ?>">Ver detalhes</button>
                <form method="post" action="index.php" data-confirm="Converter a solicitação #<?= $sid ?> de <?= e((string) ($row['cliente'] ?? '')) ?> em reserva?" data-confirm-2="Segunda confirmação: você realmente deseja converter esta solicitação? O cadastro da reserva será aberto com os dados recebidos para você conferir."><?= csrf_fields('inbox_abrir_conversao') ?><input type="hidden" name="id" value="<?= $sid ?>"><button class="btn btn-sm btn-primary" type="submit">Converter em Reserva</button></form>
                <form method="post" action="index.php" data-confirm="Excluir a solicitação de <?= e((string) ($row['cliente'] ?? '')) ?>? Esta ação não pode ser desfeita."><?= csrf_fields('delete_inbox') ?><input type="hidden" name="id" value="<?= $sid ?>"><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php foreach ($rows as $row): ?>
  <?php
    $sid = (int) ($row['id'] ?? 0);
    $dados = is_array($row['dados'] ?? null) ? $row['dados'] : [];
  ?>
  <dialog id="dlg-sol-<?= $sid ?>" class="dlg-cadastro">
    <header class="dialog-h">
      <div>
        <h2>Solicitação #<?= $sid ?></h2>
        <p>Dados recebidos pelo webhook. Nada foi convertido ainda.</p>
      </div>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </header>
    <div class="dialog-b">
      <h3 class="sec-title">Dados da solicitação</h3>
      <dl class="detalhe-lista">
        <?php foreach (solicitacao_linhas($row) as $linha): ?>
          <dt><?= e($linha[0]) ?></dt>
          <dd><span><?= e($linha[1]) ?></span></dd>
        <?php endforeach; ?>
      </dl>
      <h3 class="sec-title">Payload enviado</h3>
      <?php if ($dados === []): ?>
        <p class="hint">Esta solicitação foi recebida antes do registro completo. Os campos acima são os que o painel guardou.</p>
      <?php else: ?>
        <dl class="detalhe-lista">
          <?php foreach ($dados as $chave => $valor): ?>
            <dt><?= e((string) $chave) ?></dt>
            <dd><span><?= e(trim((string) $valor) !== '' ? (string) $valor : '—') ?></span></dd>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>
    </div>
    <footer class="dlg-foot">
      <button class="btn" type="button" data-close-dialog>Fechar</button>
    </footer>
  </dialog>
<?php endforeach; ?>
