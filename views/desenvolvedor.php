<?php
declare(strict_types=1);
defined('APP') || exit;
$contrato = webhook_contrato();
$url = '';
foreach ($db['automacoes'] as $hook) {
    if ((string) ($hook['tipo_hook'] ?? '') === 'Transfer Executivo' && trim((string) ($hook['secret'] ?? '')) !== '') {
        $url = hook_url((string) $hook['secret']);
        break;
    }
}
view_head(
    $url !== '' ? '<button class="btn btn-sm" type="button" data-copy="' . e($url) . '">Copiar URL</button>' : '',
    'Tudo o que o webhook aceita, em todas as categorias de viagem. Nada aqui fica bloqueado.'
);
?>
<section class="card">
  <header class="card-h"><h2>Como enviar</h2></header>
  <p><?= e((string) $contrato['envio']) ?></p>
  <?php if ($url === ''): ?>
    <p class="hint">Ainda não há automação Transfer Executivo. Crie uma em Automações para obter a URL.</p>
  <?php else: ?>
    <p><code class="dev-url"><?= e($url) ?></code></p>
  <?php endif; ?>
  <p class="hint">Abrir essa URL no navegador devolve o mesmo contrato em JSON. Para gravar uma solicitação, envie POST. O domínio do site precisa estar em Configurações → Domínios do webhook.</p>
</section>

<section class="card" style="margin-top:12px">
  <header class="card-h"><h2>Campos de todas as categorias</h2></header>
  <div class="table-wrap">
    <table class="table-lista">
      <thead><tr><th>Campo</th><th>Obrigatório</th><th>O que enviar</th></tr></thead>
      <tbody>
        <?php foreach ($contrato['comum'] as [$campo, $obrigatorio, $texto]): ?>
          <tr>
            <td><code><?= e($campo) ?></code></td>
            <td><?= $obrigatorio === 'Sim' ? '<span class="tag tag-pag">Sim</span>' : '<span class="tag">Não</span>' ?></td>
            <td><?= e($texto) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($contrato['categorias'] as $cat): ?>
  <?php $json = json_encode($cat['exemplo'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: ''; ?>
  <section class="card" style="margin-top:12px" id="dev-<?= e((string) $cat['id']) ?>">
    <header class="card-h">
      <div>
        <h2><?= e((string) $cat['titulo']) ?></h2>
        <p class="cfg-lead"><?= e((string) $cat['resumo']) ?></p>
      </div>
      <button class="btn btn-sm" type="button" data-copy="<?= e($json) ?>">Copiar exemplo</button>
    </header>
    <div class="table-wrap">
      <table class="table-lista">
        <thead><tr><th>Campo</th><th>Obrigatório</th><th>O que enviar</th></tr></thead>
        <tbody>
          <?php foreach ($cat['campos'] as [$campo, $obrigatorio, $texto]): ?>
            <tr>
              <td><code><?= e($campo) ?></code></td>
              <td><?= $obrigatorio === 'Sim' ? '<span class="tag tag-pag">Sim</span>' : '<span class="tag">Não</span>' ?></td>
              <td><?= e($texto) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <pre class="dev-json"><?= e($json) ?></pre>
  </section>
<?php endforeach; ?>
<p class="help" style="margin-top:12px">A solicitação chega em Transfer → Solicitações. Ao converter, ida, volta, trajetos e por hora abrem preenchidos. Dá para alterar qualquer campo antes de salvar a reserva.</p>
