(() => {
  const abrir = document.getElementById('camp-abrir');
  const painel = document.getElementById('camp-painel');
  const listaEl = document.getElementById('camp-lista');
  const form = document.getElementById('camp-form');
  const busca = document.getElementById('camp-busca');
  if (!abrir || !painel || !listaEl || !form) return;

  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const btnFull = document.getElementById('camp-full');
  const btnFull2 = document.getElementById('camp-full-2');
  const icoExpand = btnFull?.innerHTML || '';
  const icoCompress = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h5V4M20 9h-5V4M4 15h5v5M20 15h-5v5"/></svg>';
  const isMobile = () => window.matchMedia('(max-width: 800px), (pointer: coarse)').matches;

  let itens = [];
  let ativa = 0;
  let aberta = false;
  let busy = false;
  let publicoOpcoes = {};

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function aviso(texto, erro = false) {
    const el = document.getElementById('camp-aviso');
    if (!el) return;
    if (!texto) {
      el.hidden = true;
      el.textContent = '';
      el.classList.remove('is-err');
      return;
    }
    el.hidden = false;
    el.textContent = texto;
    el.classList.toggle('is-err', !!erro);
  }

  function setFull(on) {
    painel.classList.toggle('is-full', on);
    document.body.classList.toggle('camp-full-on', on);
    [btnFull, btnFull2].forEach((b) => {
      if (!b) return;
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.innerHTML = on ? icoCompress : icoExpand;
    });
  }

  function mobileMode(detalhe) {
    painel.classList.toggle('is-lista', !detalhe);
    painel.classList.toggle('is-detalhe', !!detalhe);
  }

  function badgeAtivas(n) {
    const badge = document.getElementById('camp-aba-badge');
    if (!badge) return;
    if (n <= 0) {
      badge.hidden = true;
      badge.textContent = '';
      return;
    }
    badge.hidden = false;
    badge.textContent = n > 99 ? '99+' : String(n);
  }

  function pintarKpis(resumo) {
    const r = resumo || {};
    const set = (id, v) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(v ?? 0);
    };
    set('camp-kpi-total', r.total || 0);
    set('camp-kpi-ativas', r.ativas || 0);
    set('camp-kpi-envios', r.enviados || 0);
    badgeAtivas(Number(r.ativas) || 0);
    const linha = document.getElementById('camp-status-linha');
    if (linha) {
      linha.textContent = r.whatsapp_ok ? 'WhatsApp pronto para disparo' : 'Conecte o WhatsApp no Comunicador';
    }
    publicoOpcoes = r.publico_opcoes || publicoOpcoes;
  }

  function filtrados() {
    const q = (busca?.value || '').trim().toLowerCase();
    if (!q) return itens;
    return itens.filter((c) => String(c.nome || '').toLowerCase().includes(q) || String(c.status || '').includes(q));
  }

  function pintarLista() {
    const rows = filtrados();
    if (!rows.length) {
      listaEl.innerHTML = '<p class="camp-vazio">Nenhuma campanha ainda.<br>Toque em + para criar a primeira.</p>';
      return;
    }
    listaEl.innerHTML = rows.map((c) => {
      const m = c.metricas || {};
      return (
        '<button type="button" class="camp-item' + (Number(c.id) === ativa ? ' is-on' : '') + '" data-camp-id="' + esc(c.id) + '">' +
          '<strong>' + esc(c.nome) + '</strong>' +
          '<small>' + esc(c.publico_label || c.publico) + ' · ' + esc(m.enviados || 0) + '/' + esc(m.alcance || 0) + '</small>' +
          '<span class="camp-tag ' + esc(c.status) + '">' + esc(c.status) + '</span>' +
        '</button>'
      );
    }).join('');
  }

  function limparForm() {
    ativa = 0;
    form.reset();
    document.getElementById('camp-id').value = '0';
    document.getElementById('camp-nome').textContent = 'Nova campanha';
    document.getElementById('camp-sub').textContent = 'Crie, acompanhe e dispare';
    document.getElementById('camp-progresso').hidden = true;
    document.getElementById('camp-disparar').hidden = true;
    document.getElementById('camp-encerrar').hidden = true;
    document.getElementById('camp-apagar').hidden = true;
    document.getElementById('camp-wrap-manual').hidden = true;
    aviso('');
    pintarLista();
  }

  function mostrarItem(c) {
    if (!c) {
      limparForm();
      return;
    }
    ativa = Number(c.id) || 0;
    document.getElementById('camp-id').value = String(ativa);
    document.getElementById('camp-campo-nome').value = c.nome || '';
    document.getElementById('camp-campo-publico').value = c.publico || 'clientes';
    document.getElementById('camp-campo-manual').value = c.lista_manual || '';
    document.getElementById('camp-campo-msg').value = c.mensagem || '';
    document.getElementById('camp-nome').textContent = c.nome || 'Campanha';
    document.getElementById('camp-sub').textContent = (c.publico_label || c.publico || '') + ' · ' + (c.status || 'rascunho');
    document.getElementById('camp-wrap-manual').hidden = (c.publico || '') !== 'manual';
    const m = c.metricas || {};
    const prog = document.getElementById('camp-progresso');
    prog.hidden = false;
    document.getElementById('camp-progresso-pct').textContent = (m.progresso || 0) + '%';
    document.getElementById('camp-progresso-txt').textContent =
      c.status === 'encerrada' ? 'Campanha encerrada' : ((m.pendentes || 0) > 0 ? 'Disparo em andamento' : 'Pronta para disparar');
    document.getElementById('camp-barra-fill').style.width = Math.max(0, Math.min(100, Number(m.progresso) || 0)) + '%';
    document.getElementById('camp-m-alcance').textContent = String(m.alcance || 0);
    document.getElementById('camp-m-ok').textContent = String(m.enviados || 0);
    document.getElementById('camp-m-falha').textContent = String(m.falhas || 0);
    document.getElementById('camp-m-pend').textContent = String(m.pendentes || 0);
    document.getElementById('camp-disparar').hidden = c.status === 'encerrada';
    document.getElementById('camp-encerrar').hidden = c.status === 'encerrada';
    document.getElementById('camp-apagar').hidden = false;
    aviso('');
    pintarLista();
  }

  async function carregar() {
    try {
      const r = await fetch('index.php?p=campanha&acao=lista', { credentials: 'same-origin', cache: 'no-store' });
      const j = await r.json();
      if (!j.ok) throw new Error(j.erro || 'Falha');
      itens = Array.isArray(j.itens) ? j.itens : [];
      pintarKpis(j.resumo || {});
      pintarLista();
      if (ativa) {
        const atual = itens.find((c) => Number(c.id) === ativa);
        if (atual) mostrarItem(atual);
      }
    } catch (e) {
      aviso(e.message || 'Não foi possível carregar as campanhas.', true);
    }
  }

  async function post(acao, data) {
    const body = new URLSearchParams(data || {});
    body.set('csrf', csrf);
    const r = await fetch('index.php?p=campanha&acao=' + encodeURIComponent(acao), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString(),
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.erro || 'Falha');
    return j;
  }

  function abrirPainel() {
    document.dispatchEvent(new CustomEvent('fln:fechar-chat'));
    document.dispatchEvent(new CustomEvent('fln:fechar-mapa'));
    document.dispatchEvent(new CustomEvent('fln:fechar-analise'));
    aberta = true;
    painel.hidden = false;
    document.getElementById('camp-borda') && (document.getElementById('camp-borda').hidden = true);
    abrir.setAttribute('aria-expanded', 'true');
    mobileMode(false);
    carregar();
  }

  function fecharPainel() {
    aberta = false;
    painel.hidden = true;
    const borda = document.getElementById('camp-borda');
    if (borda) borda.hidden = false;
    abrir.setAttribute('aria-expanded', 'false');
    setFull(false);
  }

  document.addEventListener('fln:fechar-campanha', () => {
    if (aberta) fecharPainel();
  });

  abrir.addEventListener('click', () => {
    if (aberta) fecharPainel();
    else abrirPainel();
  });

  painel.querySelectorAll('[data-camp-fechar]').forEach((b) => b.addEventListener('click', fecharPainel));
  document.getElementById('camp-nova')?.addEventListener('click', () => {
    limparForm();
    if (isMobile()) mobileMode(true);
  });
  document.getElementById('camp-voltar')?.addEventListener('click', () => mobileMode(false));
  [btnFull, btnFull2].forEach((b) => b?.addEventListener('click', () => setFull(!painel.classList.contains('is-full'))));

  listaEl.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-camp-id]');
    if (!btn) return;
    const id = Number(btn.getAttribute('data-camp-id') || 0);
    const item = itens.find((c) => Number(c.id) === id);
    if (!item) return;
    mostrarItem(item);
    if (isMobile()) mobileMode(true);
  });

  busca?.addEventListener('input', pintarLista);

  document.getElementById('camp-campo-publico')?.addEventListener('change', (ev) => {
    document.getElementById('camp-wrap-manual').hidden = ev.target.value !== 'manual';
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (busy) return;
    busy = true;
    const btn = document.getElementById('camp-salvar');
    if (btn) btn.disabled = true;
    try {
      const j = await post('salvar', {
        id: document.getElementById('camp-id').value || '0',
        nome: document.getElementById('camp-campo-nome').value,
        publico: document.getElementById('camp-campo-publico').value,
        lista_manual: document.getElementById('camp-campo-manual').value,
        mensagem: document.getElementById('camp-campo-msg').value,
      });
      aviso(j.msg || 'Salvo.');
      await carregar();
      if (j.item) mostrarItem(j.item);
    } catch (e) {
      aviso(e.message || 'Erro ao salvar.', true);
    } finally {
      busy = false;
      if (btn) btn.disabled = false;
    }
  });

  document.getElementById('camp-disparar')?.addEventListener('click', async () => {
    if (!ativa || busy) return;
    if (!window.confirm('Disparar um lote desta campanha no WhatsApp agora?')) return;
    busy = true;
    const btn = document.getElementById('camp-disparar');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Enviando…';
    }
    try {
      const j = await post('disparar', { id: String(ativa), lote: '15' });
      aviso(j.msg || 'Lote enviado.');
      await carregar();
      if (j.item) mostrarItem(j.item);
      if ((j.lote?.pendentes || 0) > 0 && window.confirm('Ainda há pendentes. Disparar o próximo lote?')) {
        busy = false;
        if (btn) btn.disabled = false;
        btn?.click();
        return;
      }
    } catch (e) {
      aviso(e.message || 'Falha no disparo.', true);
    } finally {
      busy = false;
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Disparar lote';
      }
    }
  });

  document.getElementById('camp-encerrar')?.addEventListener('click', async () => {
    if (!ativa || busy) return;
    busy = true;
    try {
      const j = await post('status', { id: String(ativa), status: 'encerrada' });
      aviso(j.msg || 'Encerrada.');
      await carregar();
      if (j.item) mostrarItem(j.item);
    } catch (e) {
      aviso(e.message || 'Falha.', true);
    } finally {
      busy = false;
    }
  });

  document.getElementById('camp-apagar')?.addEventListener('click', async () => {
    if (!ativa || busy) return;
    if (!window.confirm('Apagar esta campanha?')) return;
    busy = true;
    try {
      await post('apagar', { id: String(ativa) });
      limparForm();
      await carregar();
      aviso('Campanha removida.');
    } catch (e) {
      aviso(e.message || 'Falha ao apagar.', true);
    } finally {
      busy = false;
    }
  });

  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && aberta) fecharPainel();
  });
})();
