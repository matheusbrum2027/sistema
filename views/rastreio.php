<?php
declare(strict_types=1);
defined('APP') || exit;
$empresa = trim((string) ($db['settings']['nome'] ?? ''));
if ($empresa === '') {
    $empresa = 'Transfer FLN';
}
$painel = rastreio_painel();
$catalogo = $painel['ok'] ? rastreio_catalogo($db, $painel['futuras']) : [];
$ativos = $painel['ativos'];
$historico = $painel['historico'];
$itens = [];
foreach ($catalogo as $item) {
    $prox = rastreio_proxima($item);
    $quando = rastreio_quando_legivel($prox['quando']);
    $cliente = trim((string) $item['cliente']);
    $origem = clip((string) $item['origem'], 42);
    $destino = clip((string) $item['destino'], 42);
    $nomeOpcao = $cliente !== '' ? $cliente : 'Reserva';
    $rotulo = '#' . (int) $item['id'];
    if ($quando !== '') {
        $rotulo .= ' · ' . $quando;
    }
    $rotulo .= ' · ' . $nomeOpcao;
    if ($prox['perna'] === 'volta') {
        $rotulo .= ' · volta';
    }
    if ($origem !== '' || $destino !== '') {
        $rotulo .= ' · ' . $origem . ' → ' . $destino;
    }
    $itens[] = [
        'id' => (int) $item['id'],
        'rotulo' => $rotulo,
        'cliente' => $cliente,
        'telefone' => (string) $item['telefone'],
        'mot_nome' => (string) $item['mot_nome'],
        'mot_tel' => (string) $item['mot_tel'],
        'mot_volta_nome' => (string) $item['mot_volta_nome'],
        'mot_volta_tel' => (string) $item['mot_volta_tel'],
        'perna' => $prox['perna'],
    ];
}
$fichas = [];
$montarFicha = static function (array $row, bool $comUrl) use ($empresa): array {
    $id = (int) ($row['id'] ?? 0);
    $token = (string) ($row['token'] ?? '');
    return [
        'id' => $id,
        'reserva' => (int) ($row['reserva_id'] ?? 0),
        'papel' => (string) ($row['papel'] ?? ''),
        'nome' => (string) ($row['nome'] ?? ''),
        'telefone' => (string) ($row['telefone'] ?? ''),
        'obs' => (string) ($row['obs'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'frase' => rastreio_frase($row),
        'origem' => (string) ($row['origem'] ?? ''),
        'destino' => (string) ($row['destino'] ?? ''),
        'quando' => (string) ($row['quando'] ?? ''),
        'km' => rastreio_km_txt($row['km'] ?? 0),
        'minutos' => rastreio_minutos(
            isset($row['iniciado_em']) ? (string) $row['iniciado_em'] : null,
            isset($row['encerrado_em']) ? (string) $row['encerrado_em'] : null
        ),
        'criado' => rastreio_quando_curto(isset($row['criado_em']) ? (string) $row['criado_em'] : null),
        'inicio' => rastreio_quando_curto(isset($row['iniciado_em']) ? (string) $row['iniciado_em'] : null),
        'fim' => rastreio_quando_curto(isset($row['encerrado_em']) ? (string) $row['encerrado_em'] : null),
        'url' => $comUrl && $token !== '' ? rastreio_url($token) : '',
        'empresa' => $empresa,
    ];
};
foreach ($ativos as $row) {
    if (is_array($row)) {
        $fichas[(string) (int) ($row['id'] ?? 0)] = $montarFicha($row, true);
    }
}
foreach ($historico as $row) {
    if (is_array($row)) {
        $fichas[(string) (int) ($row['id'] ?? 0)] = $montarFicha($row, false);
    }
}
$nAtivos = count($ativos);
?>
<section class="ras-hero" aria-label="Gerador de link de rastreamento">
  <img src="assets/brand/rastreio-topo.jpg?v=1" alt="Gerador de link de rastreamento. Gere o link, envie e acompanhe a viagem ao vivo.">
</section>

<div class="ras-topo">
  <div>
    <h2>Geolocalização de clientes</h2>
    <p>Crie o link em uma reserva que ainda vai acontecer, envie pelo WhatsApp e acompanhe o trajeto nesta tela.</p>
  </div>
  <div class="ras-topo-acoes">
    <a class="btn btn-sm" href="<?= e(href('rastreio')) ?>"><?= icon('refresh') ?> Atualizar</a>
    <button class="btn btn-primary btn-sm" type="button" data-open-dialog="dlg-ras-novo"><?= icon('plus') ?> Novo link</button>
  </div>
</div>

<?php if (!$painel['ok']): ?>
  <?= empty_box('pin', 'Rastreio indisponível', 'Não foi possível ler os links agora. Atualize a página em instantes.') ?>
<?php endif; ?>

<section class="card ras-bloco">
  <header class="ras-bloco-h">
    <h3>Links ativos</h3>
    <span><?= $nAtivos === 1 ? '1 rastreio' : e((string) $nAtivos) . ' rastreios' ?></span>
  </header>
  <?php if ($ativos === []): ?>
    <p class="ras-vazio">Nenhum link ativo. Clique em Novo link e escolha a reserva.</p>
  <?php else: ?>
    <div class="ras-ativos">
      <?php foreach ($ativos as $row): ?>
        <?php
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        $ficha = $fichas[(string) $id] ?? [];
        $nome = trim((string) ($ficha['nome'] ?? ''));
        if ($nome === '') {
            $nome = 'Reserva #' . (int) ($row['reserva_id'] ?? 0);
        }
        $status = (string) ($row['status'] ?? '');
        $papel = (string) ($row['papel'] ?? '');
        ?>
        <article class="ras-card">
          <div class="ras-card-id">
            <strong><?= e($nome) ?></strong>
            <span class="badge <?= $status === 'ao_vivo' ? 'badge-andamento' : 'badge-pendente' ?>"><?= $status === 'ao_vivo' ? 'Ao vivo' : 'Aguardando' ?></span>
            <span class="badge badge-reserva"><?= $papel === 'motorista' ? 'Motorista' : 'Cliente' ?></span>
            <p><?= e((string) ($ficha['frase'] ?? '')) ?> · <?= e((string) ($ficha['km'] ?? '0,00')) ?> km · <?= (int) ($ficha['minutos'] ?? 0) ?> min</p>
            <?php if (($ficha['url'] ?? '') !== ''): ?>
              <code><?= e((string) $ficha['url']) ?></code>
            <?php endif; ?>
            <?php if (trim((string) ($ficha['telefone'] ?? '')) !== ''): ?>
              <small>Tel. <?= e((string) $ficha['telefone']) ?></small>
            <?php endif; ?>
          </div>
          <div class="ras-card-acoes">
            <button class="btn btn-sm" type="button" data-ras-copiar="<?= $id ?>">Copiar</button>
            <?php if (($ficha['url'] ?? '') !== ''): ?>
              <a class="btn btn-sm" href="<?= e((string) $ficha['url']) ?>" target="_blank" rel="noopener">Abrir</a>
            <?php endif; ?>
            <form method="post" action="index.php">
              <?= csrf_fields('comunicar_rastreio') ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <button class="btn btn-primary btn-sm" type="submit">Comunicar</button>
            </form>
            <button class="btn btn-sm" type="button" data-ras-mapa="<?= $id ?>">Acompanhar</button>
            <button class="btn btn-sm" type="button" data-ras-detalhe="<?= $id ?>">Detalhes</button>
            <form method="post" action="index.php" data-confirm="Encerrar o link de <?= e($nome) ?>? O celular para de enviar a localização.">
              <?= csrf_fields('encerrar_rastreio') ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <button class="btn btn-danger btn-sm" type="submit">Encerrar</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="card ras-bloco">
  <header class="ras-bloco-h">
    <h3>Histórico</h3>
    <span><?= count($historico) === 1 ? '1 registro' : e((string) count($historico)) . ' registros' ?></span>
  </header>
  <?php if ($historico === []): ?>
    <p class="ras-vazio">Os links encerrados aparecem aqui, com o tempo e a distância.</p>
  <?php else: ?>
    <div class="ras-hist">
      <?php foreach ($historico as $row): ?>
        <?php
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        $ficha = $fichas[(string) $id] ?? [];
        $nome = trim((string) ($ficha['nome'] ?? ''));
        if ($nome === '') {
            $nome = 'Reserva #' . (int) ($row['reserva_id'] ?? 0);
        }
        $status = (string) ($row['status'] ?? '');
        $quando = (string) ($ficha['fim'] ?? '');
        if ($quando === '') {
            $quando = (string) ($ficha['criado'] ?? '');
        }
        ?>
        <article class="ras-hist-item">
          <div>
            <strong><?= e($nome) ?></strong>
            <p><?= e((string) ($ficha['km'] ?? '0,00')) ?> km · <?= (int) ($ficha['minutos'] ?? 0) ?> min</p>
            <small><?= e($quando) ?></small>
          </div>
          <div class="ras-hist-acoes">
            <button class="btn btn-sm" type="button" data-ras-detalhe="<?= $id ?>">Detalhes</button>
            <span class="badge <?= $status === 'concluido' ? 'badge-concluida' : 'badge-cancelada' ?>"><?= $status === 'concluido' ? 'Concluído' : 'Encerrado' ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<dialog id="dlg-ras-novo" class="dlg-ras">
  <header class="dialog-h">
    <h2>Criar link de rastreamento</h2>
    <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
  </header>
  <form method="post" action="index.php" id="ras-form">
    <?= csrf_fields('criar_rastreio') ?>
    <div class="dialog-b">
      <label class="field">
        Reserva
        <input type="search" id="ras-busca" placeholder="Buscar por nome, número ou local" autocomplete="off"<?= $itens === [] ? ' disabled' : '' ?>>
        <select name="reserva" id="ras-reserva" required>
          <option value="">Selecione uma reserva</option>
          <?php foreach ($itens as $item): ?>
            <option value="<?= (int) $item['id'] ?>"><?= e((string) $item['rotulo']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="ras-dica">A reserva é obrigatória. A lista traz só viagens com data e hora ainda por ocorrer.</p>
      <?php if ($itens === []): ?>
        <p class="ras-dica is-alerta">Não há reserva futura no painel. O link não pode ser criado.</p>
      <?php endif; ?>
      <fieldset class="ras-papeis">
        <legend>Quem é rastreado</legend>
        <div class="ras-papel-grade">
          <button class="ras-papel is-on" type="button" data-papel="cliente">
            <strong>Cliente</strong>
            <span>Celular do passageiro</span>
          </button>
          <button class="ras-papel" type="button" data-papel="motorista">
            <strong>Motorista</strong>
            <span>Celular do condutor</span>
          </button>
        </div>
        <input type="hidden" name="papel" id="ras-papel" value="cliente">
        <p class="ras-dica" id="ras-papel-dica">O passageiro abre o link e toca em iniciar viagem.</p>
      </fieldset>
      <label class="field" id="ras-nome-campo">
        <span id="ras-nome-rotulo">Nome do cliente (opcional)</span>
        <input name="nome" id="ras-nome" maxlength="80" autocomplete="off" placeholder="Preenchido pela reserva">
      </label>
      <label class="field" id="ras-tel-campo">
        <span id="ras-tel-rotulo">Telefone do cliente (opcional)</span>
        <input name="telefone" id="ras-tel" maxlength="30" inputmode="tel" autocomplete="off" placeholder="Usado no Comunicar">
      </label>
      <label class="field">
        Observações (opcional)
        <textarea name="obs" maxlength="400" placeholder="Notas internas para a central ou o motorista"></textarea>
      </label>
    </div>
    <footer class="dlg-foot">
      <button class="btn btn-primary" type="submit" id="ras-criar"<?= $itens === [] ? ' disabled' : '' ?>>Criar link</button>
    </footer>
  </form>
</dialog>

<dialog id="dlg-ras-mapa" class="dlg-ras dlg-ras-mapa">
  <header class="dialog-h">
    <div>
      <h2 id="ras-mapa-titulo">Acompanhar</h2>
      <p class="ras-mapa-meta" id="ras-mapa-meta"></p>
    </div>
    <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
  </header>
  <div class="dialog-b ras-mapa-corpo">
    <div id="ras-mapa"></div>
    <p class="ras-mapa-nota" id="ras-mapa-nota">Aguardando o primeiro ponto.</p>
  </div>
</dialog>

<dialog id="dlg-ras-detalhe" class="dlg-ras">
  <header class="dialog-h">
    <h2 id="ras-det-titulo">Detalhes</h2>
    <button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>
  </header>
  <div class="dialog-b">
    <dl class="ras-detalhe" id="ras-detalhe"></dl>
  </div>
  <footer class="dlg-foot">
    <button class="btn" type="button" data-close-dialog>Fechar</button>
  </footer>
</dialog>

<script type="application/json" id="ras-dados"><?= json_encode(['itens' => $itens, 'fichas' => $fichas], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(() => {
  const bruto = document.getElementById('ras-dados');
  const dados = bruto ? JSON.parse(bruto.textContent || '{}') : {};
  const itens = Array.isArray(dados.itens) ? dados.itens : [];
  const fichas = dados.fichas && typeof dados.fichas === 'object' ? dados.fichas : {};
  const form = document.getElementById('ras-form');
  const reserva = document.getElementById('ras-reserva');
  const busca = document.getElementById('ras-busca');
  const papel = document.getElementById('ras-papel');
  const nome = document.getElementById('ras-nome');
  const tel = document.getElementById('ras-tel');
  const criar = document.getElementById('ras-criar');
  const porId = new Map(itens.map((item) => [String(item.id), item]));

  const aplicarPapel = () => {
    const cliente = (papel.value || 'cliente') === 'cliente';
    document.querySelectorAll('.ras-papel').forEach((botao) => {
      botao.classList.toggle('is-on', botao.getAttribute('data-papel') === (cliente ? 'cliente' : 'motorista'));
    });
    const rotuloNome = document.getElementById('ras-nome-rotulo');
    const rotuloTel = document.getElementById('ras-tel-rotulo');
    const dica = document.getElementById('ras-papel-dica');
    if (rotuloNome) rotuloNome.textContent = cliente ? 'Nome do cliente (opcional)' : 'Nome do motorista (opcional)';
    if (rotuloTel) rotuloTel.textContent = cliente ? 'Telefone do cliente (opcional)' : 'Telefone do motorista (opcional)';
    if (dica) dica.textContent = cliente
      ? 'O passageiro abre o link e toca em iniciar viagem.'
      : 'O motorista envia a localização enquanto a viagem está aberta.';
  };
  const preencher = () => {
    const item = porId.get(reserva.value);
    if (!item) {
      nome.value = '';
      tel.value = '';
      if (criar) criar.disabled = true;
      return;
    }
    const cliente = papel.value !== 'motorista';
    if (cliente) {
      nome.value = item.cliente || '';
      tel.value = item.telefone || '';
    } else if (item.perna === 'volta' && (item.mot_volta_nome || item.mot_volta_tel)) {
      nome.value = item.mot_volta_nome || '';
      tel.value = item.mot_volta_tel || '';
    } else {
      nome.value = item.mot_nome || '';
      tel.value = item.mot_tel || '';
    }
    if (criar) criar.disabled = false;
  };
  document.querySelectorAll('.ras-papel').forEach((botao) => {
    botao.addEventListener('click', () => {
      papel.value = botao.getAttribute('data-papel') === 'motorista' ? 'motorista' : 'cliente';
      aplicarPapel();
      if (reserva.value) preencher();
    });
  });
  reserva.addEventListener('change', preencher);
  if (busca) {
    busca.addEventListener('input', () => {
      const q = busca.value.trim().toLocaleLowerCase('pt-BR');
      [...reserva.options].forEach((opcao, indice) => {
        if (indice === 0) return;
        const cabe = q === '' || opcao.textContent.toLocaleLowerCase('pt-BR').includes(q);
        opcao.hidden = !cabe;
      });
    });
  }
  if (form) {
    form.addEventListener('submit', (evento) => {
      if (!reserva.value) {
        evento.preventDefault();
        reserva.focus();
      }
    });
  }
  aplicarPapel();
  if (criar) criar.disabled = true;

  const copiar = async (url) => {
    try {
      await navigator.clipboard.writeText(url);
      return true;
    } catch (err) {
      const area = document.createElement('textarea');
      area.value = url;
      document.body.appendChild(area);
      area.select();
      const ok = document.execCommand('copy');
      area.remove();
      return ok;
    }
  };
  document.querySelectorAll('[data-ras-copiar]').forEach((botao) => {
    botao.addEventListener('click', async () => {
      const ficha = fichas[botao.getAttribute('data-ras-copiar')] || {};
      if (!ficha.url) return;
      const ok = await copiar(ficha.url);
      const original = botao.textContent;
      botao.textContent = ok ? 'Copiado' : 'Falhou';
      setTimeout(() => { botao.textContent = original; }, 1400);
    });
  });

  const detalhe = document.getElementById('dlg-ras-detalhe');
  const listaDetalhe = document.getElementById('ras-detalhe');
  const abrirDetalhe = (id) => {
    const ficha = fichas[id] || {};
    const linhas = [
      ['Reserva', ficha.reserva ? '#' + ficha.reserva : '—'],
      ['Quem', ficha.papel === 'motorista' ? 'Motorista' : 'Cliente'],
      ['Nome', ficha.nome || '—'],
      ['Telefone', ficha.telefone || '—'],
      ['Quando', ficha.quando || '—'],
      ['Embarque', ficha.origem || '—'],
      ['Desembarque', ficha.destino || '—'],
      ['Situação', ficha.frase || '—'],
      ['Distância', (ficha.km || '0,00') + ' km · ' + (ficha.minutos || 0) + ' min'],
      ['Criado', ficha.criado || '—'],
      ['Início', ficha.inicio || '—'],
      ['Fim', ficha.fim || '—'],
      ['Observações', ficha.obs || '—']
    ];
    listaDetalhe.replaceChildren();
    linhas.forEach(([rotulo, valor]) => {
      const bloco = document.createElement('div');
      const dt = document.createElement('dt');
      const dd = document.createElement('dd');
      dt.textContent = rotulo;
      dd.textContent = valor;
      bloco.append(dt, dd);
      listaDetalhe.appendChild(bloco);
    });
    document.getElementById('ras-det-titulo').textContent = ficha.nome || 'Detalhes';
    if (!detalhe.open) detalhe.showModal();
  };
  document.querySelectorAll('[data-ras-detalhe]').forEach((botao) => {
    botao.addEventListener('click', () => abrirDetalhe(botao.getAttribute('data-ras-detalhe')));
  });

  const dlgMapa = document.getElementById('dlg-ras-mapa');
  const nota = document.getElementById('ras-mapa-nota');
  const meta = document.getElementById('ras-mapa-meta');
  let mapa = null;
  let trilha = null;
  let marca = null;
  let timer = 0;
  let mapaId = 0;
  if (window.__rasTimer) clearInterval(window.__rasTimer);
  const parar = () => {
    if (timer) clearInterval(timer);
    timer = 0;
    window.__rasTimer = 0;
    mapaId = 0;
  };
  const desenhar = (pontos) => {
    if (!window.L) {
      nota.textContent = 'O mapa não carregou. Atualize a página.';
      return;
    }
    const el = document.getElementById('ras-mapa');
    if (!mapa) {
      mapa = L.map(el, { zoomControl: true });
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        referrerPolicy: 'strict-origin-when-cross-origin'
      }).addTo(mapa);
    }
    if (trilha) { mapa.removeLayer(trilha); trilha = null; }
    if (marca) { mapa.removeLayer(marca); marca = null; }
    if (!pontos.length) {
      mapa.setView([-27.595, -48.548], 11);
      nota.textContent = 'Ainda sem pontos. O trajeto entra quando a pessoa toca em iniciar viagem.';
      setTimeout(() => mapa.invalidateSize(), 60);
      return;
    }
    const coords = pontos.map((p) => [Number(p[0]), Number(p[1])]);
    trilha = L.polyline(coords, { color: '#f26b1a', weight: 4 }).addTo(mapa);
    marca = L.circleMarker(coords[coords.length - 1], {
      radius: 7, color: '#fff', weight: 2, fillColor: '#f26b1a', fillOpacity: 1
    }).addTo(mapa);
    mapa.fitBounds(trilha.getBounds().pad(0.25));
    nota.textContent = coords.length === 1 ? '1 ponto recebido.' : coords.length + ' pontos no trajeto.';
    setTimeout(() => mapa.invalidateSize(), 60);
  };
  const puxar = async () => {
    if (!mapaId) return;
    try {
      const resposta = await fetch('index.php?p=rastreio&acao=pontos&id=' + encodeURIComponent(mapaId), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const corpo = await resposta.json();
      if (!corpo || !corpo.ok) {
        nota.textContent = 'Não foi possível ler o trajeto.';
        return;
      }
      desenhar(Array.isArray(corpo.pontos) ? corpo.pontos : []);
      meta.textContent = (corpo.km != null ? Number(corpo.km).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' km · ' : '') + (corpo.status === 'ao_vivo' ? 'ao vivo' : (corpo.status || ''));
    } catch (err) {
      nota.textContent = 'Sem rede para atualizar o mapa.';
    }
  };
  document.querySelectorAll('[data-ras-mapa]').forEach((botao) => {
    botao.addEventListener('click', () => {
      const id = botao.getAttribute('data-ras-mapa');
      const ficha = fichas[id] || {};
      parar();
      mapaId = Number(id) || 0;
      document.getElementById('ras-mapa-titulo').textContent = ficha.nome || 'Acompanhar';
      meta.textContent = ficha.quando || '';
      if (!dlgMapa.open) dlgMapa.showModal();
      puxar();
      if (ficha.status === 'aguardando' || ficha.status === 'ao_vivo') {
        timer = setInterval(puxar, 8000);
        window.__rasTimer = timer;
      }
    });
  });
  dlgMapa.addEventListener('close', parar);
})();
</script>
