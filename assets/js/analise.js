(() => {
  const abrir = document.getElementById('analise-abrir');
  const painel = document.getElementById('analise-painel');
  const corpo = document.getElementById('analise-resultado');
  const vazio = document.getElementById('analise-vazio');
  const btnGerar = document.getElementById('analise-gerar');
  if (!abrir || !painel || !corpo || !btnGerar) return;

  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const btnFull = document.getElementById('analise-full');
  const icoExpand = btnFull?.innerHTML || '';
  const icoCompress = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h5V4M20 9h-5V4M4 15h5v5M20 15h-5v5"/></svg>';

  let aberta = false;
  let periodo = '90d';
  let busy = false;

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const brl = (n) => Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  function setFull(on) {
    painel.classList.toggle('is-full', on);
    document.body.classList.toggle('analise-full-on', on);
    if (btnFull) {
      btnFull.setAttribute('aria-pressed', on ? 'true' : 'false');
      btnFull.innerHTML = on ? icoCompress : icoExpand;
    }
  }

  function pintarKpis(dados) {
    const r = dados?.resumo || {};
    document.getElementById('an-kpi-viagens').textContent = String(r.viagens ?? '—');
    document.getElementById('an-kpi-receita').textContent = r.receita != null ? brl(r.receita) : '—';
    document.getElementById('an-kpi-ticket').textContent = r.ticket != null ? brl(r.ticket) : '—';
    document.getElementById('an-kpi-frota').textContent = String(r.veiculos_ativos ?? '—');
    const linha = document.getElementById('analise-status-linha');
    if (linha && dados) {
      linha.textContent = (dados.periodo_label || '') + ' · ' + (dados.de || '') + ' — ' + (dados.ate || '');
    }
  }

  function rankHtml(titulo, rows, maxViagens) {
    if (!rows?.length) {
      return '<section class="analise-card"><h3>' + esc(titulo) + '</h3><p>Sem dados suficientes no período.</p></section>';
    }
    return (
      '<section class="analise-card"><h3>' + esc(titulo) + '</h3><div class="analise-rank">' +
      rows.map((row, i) => {
        const pct = maxViagens > 0 ? Math.round((row.viagens / maxViagens) * 100) : 0;
        return (
          '<div class="analise-rank-row">' +
            '<b>' + (i + 1) + '</b>' +
            '<div><strong>' + esc(row.local) + '</strong>' +
              '<div class="analise-bar"><i style="width:' + pct + '%"></i></div>' +
            '</div>' +
            '<small>' + esc(row.viagens) + ' · ' + esc(brl(row.receita)) + '</small>' +
          '</div>'
        );
      }).join('') +
      '</div></section>'
    );
  }

  function pintarRelatorio(out) {
    const dados = out.dados || {};
    const rel = out.relatorio || {};
    pintarKpis(dados);
    const maxD = Math.max(1, ...(dados.top_destinos || []).map((x) => Number(x.viagens) || 0));
    const maxO = Math.max(1, ...(dados.top_origens || []).map((x) => Number(x.viagens) || 0));

    const locais = Array.isArray(rel.locais_destaque) ? rel.locais_destaque : [];
    const expansao = Array.isArray(rel.expansao) ? rel.expansao : [];
    const riscos = Array.isArray(rel.riscos) ? rel.riscos : [];
    const passos = Array.isArray(rel.proximos_passos) ? rel.proximos_passos : [];

    corpo.innerHTML =
      '<p class="analise-meta">Gerado em ' + esc(out.gerado_em || '') + '</p>' +
      '<section class="analise-card">' +
        '<h3>' + esc(rel.titulo || 'Relatório estratégico') + '</h3>' +
        '<p>' + esc(rel.resumo_executivo || '') + '</p>' +
      '</section>' +
      '<div class="analise-grid-2">' +
        rankHtml('Locais que mais venderam (destino)', dados.top_destinos || [], maxD) +
        rankHtml('Embarques com maior volume', dados.top_origens || [], maxO) +
      '</div>' +
      '<section class="analise-card"><h3>Leitura da IA sobre os locais</h3><ul class="analise-lista">' +
        (locais.length
          ? locais.map((l) => (
              '<li><span class="analise-pill ' + esc(l.prioridade || 'media') + '">' + esc(l.prioridade || 'media') + '</span>' +
              '<strong>' + esc(l.local || '') + '</strong><small>' + esc(l.motivo || '') + '</small></li>'
            )).join('')
          : '<li><small>Sem destaques adicionais.</small></li>') +
      '</ul></section>' +
      '<section class="analise-card"><h3>Formas corretas de expandir</h3><ul class="analise-lista">' +
        (expansao.length
          ? expansao.map((e) => (
              '<li><strong>' + esc(e.titulo || '') + '</strong>' +
              '<small>' + esc(e.acao || '') + '</small>' +
              '<small>Impacto: ' + esc(e.impacto || '') + '</small></li>'
            )).join('')
          : '<li><small>Sem recomendações neste ciclo.</small></li>') +
      '</ul></section>' +
      '<div class="analise-grid-2">' +
        '<section class="analise-card"><h3>Riscos</h3><ul class="analise-lista">' +
          (riscos.length ? riscos.map((r) => '<li><small>' + esc(r) + '</small></li>').join('') : '<li><small>—</small></li>') +
        '</ul></section>' +
        '<section class="analise-card"><h3>Próximos passos</h3><ul class="analise-lista">' +
          (passos.length ? passos.map((p) => '<li><small>' + esc(p) + '</small></li>').join('') : '<li><small>—</small></li>') +
        '</ul></section>' +
      '</div>';

    if (vazio) vazio.hidden = true;
    corpo.hidden = false;
  }

  async function carregarResumo() {
    try {
      const r = await fetch('index.php?p=analise&acao=resumo&periodo=' + encodeURIComponent(periodo), {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const j = await r.json();
      if (j.ok) pintarKpis(j.dados || {});
    } catch (e) { /* ignore */ }
  }

  async function gerar() {
    if (busy) return;
    busy = true;
    btnGerar.disabled = true;
    btnGerar.textContent = 'Analisando…';
    if (vazio) {
      vazio.hidden = false;
      vazio.innerHTML = '<strong>Gerando relatório</strong><p>Cruzando vendas, locais e capacidade da frota com a OpenAI…</p>';
    }
    corpo.hidden = true;
    try {
      const body = new URLSearchParams({ csrf, periodo });
      const r = await fetch('index.php?p=analise&acao=gerar&periodo=' + encodeURIComponent(periodo), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const j = await r.json();
      if (!j.ok) throw new Error(j.erro || 'Falha ao gerar');
      pintarRelatorio(j);
    } catch (e) {
      if (vazio) {
        vazio.hidden = false;
        vazio.innerHTML = '<strong>Não foi possível gerar</strong><p>' + esc(e.message || 'Erro') + '</p>';
      }
      corpo.hidden = true;
    } finally {
      busy = false;
      btnGerar.disabled = false;
      btnGerar.textContent = 'Gerar relatório';
    }
  }

  function abrirPainel() {
    document.dispatchEvent(new CustomEvent('fln:fechar-chat'));
    document.dispatchEvent(new CustomEvent('fln:fechar-campanha'));
    document.dispatchEvent(new CustomEvent('fln:fechar-mapa'));
    aberta = true;
    painel.hidden = false;
    const borda = document.getElementById('analise-borda');
    if (borda) borda.hidden = true;
    abrir.setAttribute('aria-expanded', 'true');
    carregarResumo();
  }

  function fecharPainel() {
    aberta = false;
    painel.hidden = true;
    const borda = document.getElementById('analise-borda');
    if (borda) borda.hidden = false;
    abrir.setAttribute('aria-expanded', 'false');
    setFull(false);
  }

  abrir.addEventListener('click', () => {
    if (aberta) fecharPainel();
    else abrirPainel();
  });
  painel.querySelectorAll('[data-analise-fechar]').forEach((b) => b.addEventListener('click', fecharPainel));
  btnFull?.addEventListener('click', () => setFull(!painel.classList.contains('is-full')));
  btnGerar.addEventListener('click', gerar);
  document.addEventListener('fln:fechar-analise', () => { if (aberta) fecharPainel(); });
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && aberta) fecharPainel(); });

  document.getElementById('analise-periodo')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-periodo]');
    if (!b) return;
    periodo = b.getAttribute('data-periodo') || '90d';
    [...b.parentElement.querySelectorAll('button')].forEach((x) => x.classList.toggle('is-on', x === b));
    carregarResumo();
  });
})();
