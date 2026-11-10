<?php
declare(strict_types=1);
defined('APP') || exit;
$notas = $db['notas'];
usort($notas, static fn(array $a, array $b): int => strcmp((string) $b['criado_em'], (string) $a['criado_em']));
$total = count($notas);
$fmtNota = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '—';
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $raw)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d', substr($raw, 0, 10));
    return $d ? $d->format('d/m/Y H:i') : $raw;
};
view_head(
    '<button class="btn btn-primary" type="button" data-open-dialog="dlg-nota">+ Nova anotação</button>',
    $total === 0
        ? 'Lembretes internos da equipe.'
        : plural($total, 'anotação salva', 'anotações salvas') . '.'
);
?>
<section class="notas-page">
  <article class="card notas-composer">
    <header class="card-h">
      <h2><?= icon('plus') ?> Rascunho rápido</h2>
    </header>
    <form method="post" action="index.php" class="notas-form">
      <?= csrf_fields('add_nota') ?>
      <label class="field">Título
        <input name="titulo" required maxlength="80" placeholder="Ex.: Embarque no aeroporto" autocomplete="off">
      </label>
      <label class="field">Texto
        <textarea name="texto" required maxlength="500" rows="4" placeholder="Detalhe o lembrete para a equipe…"></textarea>
      </label>
      <div class="notas-form-acoes">
        <small>Máx. 500 caracteres. Visível só no painel.</small>
        <button class="btn btn-primary" type="submit">Salvar anotação</button>
      </div>
    </form>
  </article>

  <?php if ($notas === []): ?>
    <article class="card notas-vazio">
      <strong>Nenhuma anotação ainda</strong>
      <p>Use o rascunho acima ou o botão <em>Nova anotação</em> para deixar um lembrete da operação.</p>
    </article>
  <?php else: ?>
    <div class="notas-lista" role="list">
      <?php foreach ($notas as $nota): ?>
        <article class="nota-card" role="listitem">
          <header class="nota-card-h">
            <div class="nota-card-tit">
              <strong><?= e($nota['titulo']) ?></strong>
              <time datetime="<?= e((string) $nota['criado_em']) ?>"><?= e($fmtNota((string) $nota['criado_em'])) ?></time>
            </div>
            <form method="post" action="index.php" data-confirm="Excluir a anotação <?= e($nota['titulo']) ?>? Esta ação não pode ser desfeita.">
              <?= csrf_fields('delete_nota') ?>
              <input type="hidden" name="id" value="<?= (int) $nota['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit" title="Remover">Remover</button>
            </form>
          </header>
          <p class="nota-card-txt"><?= e($nota['texto']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<dialog id="dlg-nota" class="dlg-cadastro">
  <form method="post" action="index.php">
    <header class="dialog-h dlg-top">
      <div>
        <h2>Nova anotação</h2>
        <p>Lembrete interno para a equipe. Não é enviado ao cliente.</p>
      </div>
      <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
    </header>
    <div class="dialog-b">
      <?= csrf_fields('add_nota') ?>
      <div class="form-grid">
        <label class="field full">Título <input name="titulo" required maxlength="80" placeholder="Embarque no aeroporto"></label>
        <label class="field full">Texto <textarea name="texto" required maxlength="500" rows="5" placeholder="Conferir o voo e aguardar o passageiro no desembarque."></textarea></label>
      </div>
    </div>
    <footer class="dlg-foot">
      <button class="btn" type="button" data-close-dialog>Fechar</button>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </footer>
  </form>
</dialog>
