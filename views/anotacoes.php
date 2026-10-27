<?php
declare(strict_types=1);
defined('APP') || exit;
$notas = $db['notas'];
usort($notas, static fn(array $a, array $b): int => strcmp((string) $b['criado_em'], (string) $a['criado_em']));
view_head();
?>
<div class="grid-2">
  <section>
    <?php if ($notas === []): ?>
      <article class="card"><?= empty_box('note', 'Nenhuma anotação', 'Use o formulário ao lado para deixar um lembrete da equipe.') ?></article>
    <?php else: ?>
      <div class="notes">
        <?php foreach ($notas as $nota): ?>
          <article class="note">
            <header>
              <strong><?= e($nota['titulo']) ?></strong>
              <form method="post" action="index.php" data-confirm="Excluir a anotação <?= e($nota['titulo']) ?>? Esta ação não pode ser desfeita.">
                <?= csrf_fields('delete_nota') ?>
                <input type="hidden" name="id" value="<?= (int) $nota['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Remover</button>
              </form>
            </header>
            <time><?= e($nota['criado_em']) ?></time>
            <p><?= e($nota['texto']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <article class="card">
    <header class="card-h"><h2><?= icon('plus') ?> Nova anotação</h2></header>
    <form method="post" action="index.php" class="form-grid">
      <?= csrf_fields('add_nota') ?>
      <label class="field full">Título <input name="titulo" required maxlength="80" placeholder="Embarque no aeroporto"></label>
      <label class="field full">Texto <textarea name="texto" required maxlength="500" placeholder="Conferir o voo G3 1234 e aguardar o passageiro no desembarque de Florianópolis."></textarea></label>
      <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar</button></div>
    </form>
  </article>
</div>
