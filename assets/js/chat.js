(() => {
  const borda = document.getElementById('chat-borda');
  const painel = document.getElementById('chat-painel');
  const abrir = document.getElementById('chat-abrir');
  const lista = document.getElementById('chat-conversas');
  const msgs = document.getElementById('chat-msgs');
  const busca = document.getElementById('chat-busca');
  const form = document.getElementById('chat-form');
  const texto = document.getElementById('chat-texto');
  const statusLinha = document.getElementById('chat-status-linha');
  const btnSync = document.getElementById('chat-sync');
  const btnFull = document.getElementById('chat-full');
  const btnFull2 = document.getElementById('chat-full-2');
  const btnCfg = document.getElementById('chat-cfg');
  const cfgPainel = document.getElementById('chat-cfg-painel');
  const cfgForm = document.getElementById('chat-cfg-form');
  const cfgAviso = document.getElementById('chat-cfg-aviso');
  const badge = document.getElementById('chat-aba-badge');
  if (!borda || !painel || !abrir || !lista || !msgs || !form || !texto) return;

  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const icoExpand = btnFull?.innerHTML || '';
  const icoCompress = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h5V4M20 9h-5V4M4 15h5v5M20 15h-5v5"/></svg>';
  const isMobile = () => window.matchMedia('(max-width: 800px), (pointer: coarse)').matches;

  let conversas = [];
  let ativa = '';
  let aberta = false;
  let authFail = false;
  let conectado = false;
  let listaBusy = false;
  let msgsBusy = false;
  let syncBusy = false;
  let pollBusy = false;
  let cfgBusy = false;
  let listaCtrl = null;
  let msgsCtrl = null;
  let pollTimer = 0;
  let fundoTimer = 0;
  let comunicador = 'index.php?p=comunicador';
  let ignorarGrupos = true;

  const esc = (valor) => String(valor ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function avatarHtml(foto, letra) {
    const L = esc((letra || '?').toString().slice(0, 1).toUpperCase() || '?');
    if (foto) {
      return '<img src="' + esc(foto) + '" alt="" referrerpolicy="no-referrer" loading="lazy" onerror="this.remove()">';
    }
    return L;
  }

  function pintarAvatarCabecalho(foto, letra) {
    const box = document.getElementById('chat-avatar');
    if (!box) return;
    const L = ((letra || '?').toString().slice(0, 1).toUpperCase() || '?');
    box.setAttribute('data-letra', L);
    if (foto) {
      box.innerHTML = '<img src="' + esc(foto) + '" alt="" referrerpolicy="no-referrer" loading="lazy">';
      const img = box.querySelector('img');
      img?.addEventListener('error', () => {
        box.innerHTML = '<span id="chat-letra">' + esc(L) + '</span>';
      }, { once: true });
      return;
    }
    box.innerHTML = '<span id="chat-letra">' + esc(L) + '</span>';
  }

  function atualizarBadge(total) {
    if (!badge) return;
    const n = Math.max(0, Number(total) || 0);
    if (n <= 0) {
      badge.hidden = true;
      badge.textContent = '';
      badge.setAttribute('aria-hidden', 'true');
      return;
    }
    badge.hidden = false;
    badge.removeAttribute('aria-hidden');
    badge.textContent = n > 99 ? '99+' : String(n);
  }

  // Chat WhatsApp: só o número no ícone. Sem som, vibração, faixa ou push.

  function atualizarStatus(dados) {
    if (!statusLinha) return;
    if (dados?.comunicador) comunicador = dados.comunicador;
    if (typeof dados?.ignorar_grupos === 'boolean') ignorarGrupos = dados.ignorar_grupos;
    if (typeof dados?.naoLidas === 'number') atualizarBadge(dados.naoLidas);
    if (typeof dados?.conectado !== 'boolean') return;
    conectado = !!dados.conectado;
    if (dados.conectado) {
      statusLinha.textContent = dados.perfil ? ('Conectado · ' + dados.perfil) : 'Conectado';
      return;
    }
    if (dados?.pronto) {
      statusLinha.textContent = 'Instância salva · reconecte no Comunicador';
      return;
    }
    statusLinha.textContent = 'WhatsApp desconectado';
  }

  function erroHtml(msg) {
    return '<div class="chat-vazio">'
      + '<p>' + esc(msg || 'Não foi possível carregar.') + '</p>'
      + '<p><a class="chat-link" href="' + esc(comunicador) + '">Abrir Comunicador</a></p>'
      + '</div>';
  }

  function setFull(on) {
    if (isMobile()) on = false;
    painel.classList.toggle('is-full', on);
    document.body.classList.toggle('chat-full-on', on);
    const pressed = on ? 'true' : 'false';
    const html = on ? icoCompress : icoExpand;
    [btnFull, btnFull2].forEach((btn) => {
      if (!btn) return;
      btn.setAttribute('aria-pressed', pressed);
      btn.setAttribute('title', on ? 'Sair da tela cheia' : 'Tela cheia');
      btn.setAttribute('aria-label', on ? 'Sair da tela cheia' : 'Tela cheia');
      btn.innerHTML = html;
    });
  }

  function mostrarCfg(on) {
    if (!cfgPainel) return;
    painel.classList.toggle('is-cfg', on);
    cfgPainel.hidden = !on;
    if (on) carregarConfig();
  }

  function pararPoll() {
    if (pollTimer) window.clearInterval(pollTimer);
    pollTimer = 0;
  }

  function iniciarPoll() {
    pararPoll();
    // Com o painel aberto: puxa UAZAPI com calma (não a cada poucos segundos).
    pollTimer = window.setInterval(() => {
      if (!aberta || document.hidden || authFail || painel.classList.contains('is-cfg')) return;
      puxarFundo(true, true);
    }, 25000);
  }

  function iniciarFundo() {
    if (fundoTimer) window.clearInterval(fundoTimer);
    // Fundo: só lista local (rápido). Nunca trava o menu com UAZAPI.
    fundoTimer = window.setInterval(() => {
      if (document.hidden || authFail || aberta) return;
      puxarFundo(false, false);
    }, 20000);
    puxarFundo(false, false);
  }

  function travarZoomMobile() {
    if (!isMobile()) return;
    try {
      document.documentElement.style.zoom = '1';
      if (window.visualViewport) {
        const vv = window.visualViewport;
        if (Math.abs(vv.scale - 1) > 0.01) {
          window.scrollTo(0, 0);
        }
      }
    } catch (err) { /* ignora */ }
  }

  function abrirPainel() {
    aberta = true;
    authFail = false;
    painel.hidden = false;
    borda.hidden = true;
    abrir.setAttribute('aria-expanded', 'true');
    setFull(false);
    mostrarCfg(false);
    travarZoomMobile();
    sincronizar(false);
    iniciarPoll();
  }

  function fecharPainel() {
    aberta = false;
    setFull(false);
    mostrarCfg(false);
    painel.hidden = true;
    borda.hidden = false;
    abrir.setAttribute('aria-expanded', 'false');
    painel.classList.remove('is-fio');
    pararPoll();
    if (listaCtrl) listaCtrl.abort();
    if (msgsCtrl) msgsCtrl.abort();
    travarZoomMobile();
  }

  let toqueAba = false;
  abrir.addEventListener('touchend', (event) => {
    if (!isMobile()) return;
    event.preventDefault();
    toqueAba = true;
    window.setTimeout(() => { toqueAba = false; }, 450);
    travarZoomMobile();
    if (!aberta) abrirPainel();
  }, { passive: false });
  abrir.addEventListener('click', (event) => {
    event.preventDefault();
    if (toqueAba) return;
    travarZoomMobile();
    abrirPainel();
  });

  painel.querySelectorAll('[data-chat-fechar]').forEach((btn) => btn.addEventListener('click', fecharPainel));
  document.getElementById('chat-voltar')?.addEventListener('click', () => painel.classList.remove('is-fio'));
  document.getElementById('chat-cfg-voltar')?.addEventListener('click', () => mostrarCfg(false));
  btnFull?.addEventListener('click', () => {
    if (isMobile()) return;
    setFull(!painel.classList.contains('is-full'));
  });
  btnFull2?.addEventListener('click', () => {
    if (isMobile()) return;
    setFull(!painel.classList.contains('is-full'));
  });
  btnSync?.addEventListener('click', () => sincronizar(false));
  btnCfg?.addEventListener('click', () => mostrarCfg(true));

  document.addEventListener('keydown', (event) => {
    if (!aberta || event.key !== 'Escape') return;
    if (painel.classList.contains('is-cfg')) {
      mostrarCfg(false);
      return;
    }
    if (painel.classList.contains('is-full')) {
      setFull(false);
      return;
    }
    fecharPainel();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden || authFail) return;
    puxarFundo(aberta, aberta);
  });

  async function puxarFundo(atualizarUi, puxarApi) {
    if (pollBusy || authFail) return;
    pollBusy = true;
    try {
      const url = puxarApi
        ? 'index.php?p=chat&acao=poll&puxar=1'
        : 'index.php?p=chat&acao=lista';
      const resposta = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const dados = await resposta.json();
      atualizarStatus(dados);
      if (!dados.ok) {
        if (/Comunicador|expirou|token|desconect/i.test(String(dados.erro || ''))) authFail = true;
        return;
      }
      authFail = false;
      conversas = dados.conversas || [];
      if (atualizarUi && aberta && !painel.classList.contains('is-cfg')) {
        pintarLista();
        if (ativa) carregarMsgs(ativa, true);
      } else if (!aberta) {
        atualizarBadge(dados.naoLidas || 0);
      }
    } catch (err) {
      /* silencioso no fundo */
    } finally {
      pollBusy = false;
    }
  }

  async function sincronizar(silencioso) {
    if (syncBusy) return;
    syncBusy = true;
    btnSync?.classList.add('is-spin');
    if (!silencioso) lista.innerHTML = '<p class="chat-vazio">Sincronizando com a UAZAPI…</p>';
    try {
      const resposta = await fetch('index.php?p=chat&acao=sync', { credentials: 'same-origin', cache: 'no-store' });
      const dados = await resposta.json();
      atualizarStatus(dados);
      if (!dados.ok) {
        authFail = /Comunicador|expirou|token|desconect/i.test(String(dados.erro || ''));
        lista.innerHTML = erroHtml(dados.erro);
        return;
      }
      authFail = false;
      conversas = dados.conversas || [];
      pintarLista();
      if (ativa) carregarMsgs(ativa, true);
    } catch (err) {
      if (!silencioso) lista.innerHTML = erroHtml('Não foi possível sincronizar agora.');
    } finally {
      syncBusy = false;
      btnSync?.classList.remove('is-spin');
    }
  }

  async function carregarLista(silencioso) {
    if (listaBusy || authFail || !aberta) return;
    listaBusy = true;
    if (listaCtrl) listaCtrl.abort();
    listaCtrl = new AbortController();
    try {
      const resposta = await fetch('index.php?p=chat&acao=lista', {
        credentials: 'same-origin',
        signal: listaCtrl.signal,
      });
      const dados = await resposta.json();
      if (!dados.ok) {
        if (/Comunicador|expirou|token/i.test(String(dados.erro || ''))) authFail = true;
        if (!silencioso) lista.innerHTML = erroHtml(dados.erro);
        return;
      }
      if (typeof dados.ignorar_grupos === 'boolean') ignorarGrupos = dados.ignorar_grupos;
      conversas = dados.conversas || [];
      atualizarBadge(dados.naoLidas || 0);
      pintarLista();
    } catch (err) {
      if (err?.name === 'AbortError') return;
      if (!silencioso) lista.innerHTML = erroHtml('Não foi possível carregar as conversas.');
    } finally {
      listaBusy = false;
    }
  }

  function pintarLista() {
    const q = (busca?.value || '').trim().toLowerCase();
    const itens = conversas.filter((c) => !q || (c.nome + ' ' + c.fone + ' ' + c.preview).toLowerCase().includes(q));
    if (!itens.length) {
      const vazio = conversas.length
        ? 'Nenhuma conversa com esse nome.'
        : (ignorarGrupos
          ? 'Tudo zerado por enquanto. Só entram conversas individuais novas depois da conexão — grupos ficam de fora.'
          : 'Tudo zerado por enquanto. O chat não puxa histórico antigo: só mensagens novas após conectar o QR.');
      lista.innerHTML = '<p class="chat-vazio">' + vazio + '</p>';
      return;
    }
    lista.innerHTML = itens.map((c) => (
      '<button type="button" class="chat-item' + (c.id === ativa ? ' is-on' : '') + '" data-id="' + esc(c.id) + '">'
      + '<span class="chat-avatar">' + avatarHtml(c.foto, c.letra) + '</span>'
      + '<span class="chat-item-meio"><strong>' + esc(c.nome) + (c.grupo ? ' · grupo' : '') + '</strong><small>' + esc(c.preview) + '</small></span>'
      + '<span class="chat-item-lado"><time>' + esc(c.hora) + '</time>'
      + (c.naoLidas ? '<b>' + esc(c.naoLidas > 99 ? '99+' : c.naoLidas) + '</b>' : '')
      + '</span></button>'
    )).join('');
  }

  busca?.addEventListener('input', pintarLista);

  lista.addEventListener('click', (event) => {
    const item = event.target.closest('.chat-item');
    if (!item) return;
    ativa = item.getAttribute('data-id') || '';
    const conv = conversas.find((c) => c.id === ativa);
    document.getElementById('chat-nome').textContent = conv ? conv.nome : 'Conversa';
    document.getElementById('chat-fone').textContent = conv ? conv.fone : '';
    pintarAvatarCabecalho(conv?.foto || '', conv?.letra || '');
    painel.classList.add('is-fio');
    pintarLista();
    msgs.innerHTML = '<p class="chat-vazio">Carregando…</p>';
    carregarMsgs(ativa, false);
    if (!isMobile()) texto.focus();
  });

  async function carregarMsgs(id, silencioso) {
    if (!id || msgsBusy || authFail || !aberta) return;
    msgsBusy = true;
    if (msgsCtrl) msgsCtrl.abort();
    msgsCtrl = new AbortController();
    try {
      const resposta = await fetch('index.php?p=chat&acao=msgs&id=' + encodeURIComponent(id), {
        credentials: 'same-origin',
        signal: msgsCtrl.signal,
      });
      const dados = await resposta.json();
      if (id !== ativa) return;
      if (!dados.ok) {
        if (/Comunicador|expirou|token/i.test(String(dados.erro || ''))) authFail = true;
        if (!silencioso) msgs.innerHTML = erroHtml(dados.erro);
        return;
      }
      const conv = conversas.find((c) => c.id === id);
      if (conv) {
        conv.naoLidas = 0;
        if (dados.foto) conv.foto = dados.foto;
        if (dados.nome) conv.nome = dados.nome;
        if (dados.fone) conv.fone = dados.fone;
      }
      if (typeof dados.naoLidas === 'number') {
        atualizarBadge(dados.naoLidas);
      } else {
        atualizarBadge(conversas.reduce((acc, c) => acc + (Number(c.naoLidas) || 0), 0));
      }
      if (dados.foto || dados.nome) {
        document.getElementById('chat-nome').textContent = dados.nome || conv?.nome || 'Conversa';
        document.getElementById('chat-fone').textContent = dados.fone || conv?.fone || '';
        pintarAvatarCabecalho(dados.foto || conv?.foto || '', (dados.nome || conv?.nome || '?').slice(0, 1));
      }
      pintarLista();
      let linhas = dados.mensagens || [];
      linhas = [...linhas].sort((a, b) => {
        const ta = Number(a.ts) || 0;
        const tb = Number(b.ts) || 0;
        if (ta !== tb) return ta - tb;
        return String(a.id || '').localeCompare(String(b.id || ''));
      });
      if (!linhas.length) {
        msgs.innerHTML = '<p class="chat-vazio">Nenhuma mensagem nesta conversa.</p>';
        return;
      }
      const noFim = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 90;
      msgs.innerHTML = linhas.map((m) => (
        '<div class="chat-bolha' + (m.minha ? ' minha' : '') + '">'
        + (!m.minha && m.autor ? '<b>' + esc(m.autor) + '</b>' : '')
        + '<p>' + esc(m.texto) + '</p><time>' + esc(m.hora) + '</time></div>'
      )).join('');
      if (!silencioso || noFim) msgs.scrollTop = msgs.scrollHeight;
    } catch (err) {
      if (err?.name === 'AbortError') return;
      if (!silencioso && id === ativa) msgs.innerHTML = erroHtml('Não foi possível abrir a conversa.');
    } finally {
      msgsBusy = false;
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const valor = texto.value.trim();
    if (!ativa || valor === '' || authFail) return;
    texto.disabled = true;
    const corpo = new URLSearchParams({ csrf, id: ativa, texto: valor });
    try {
      const resposta = await fetch('index.php?p=chat&acao=enviar', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: corpo,
      });
      const dados = await resposta.json();
      if (!dados.ok) {
        window.alert(dados.erro || 'Não foi possível enviar.');
        if (/Comunicador|expirou|token/i.test(String(dados.erro || ''))) authFail = true;
      } else {
        texto.value = '';
        await carregarMsgs(ativa, false);
        carregarLista(true);
      }
    } catch (err) {
      window.alert('Não foi possível enviar.');
    }
    texto.disabled = false;
    if (!isMobile()) texto.focus();
  });

  function preencherConfig(dados) {
    const ignorar = document.getElementById('cfg-ignorar-grupos');
    const ia = document.getElementById('cfg-ia-ativa');
    const parar = document.getElementById('cfg-ia-parar');
    const pararMin = document.getElementById('cfg-ia-parar-min');
    const pausar = document.getElementById('cfg-ia-pausar-manual');
    const openai = document.getElementById('cfg-openai');
    const hint = document.getElementById('cfg-openai-hint');
    if (ignorar) ignorar.checked = !!dados.ignorar_grupos;
    if (ia) ia.checked = !!dados.ia_ativa;
    if (parar) parar.value = dados.ia_parar || 'parar';
    if (pararMin) pararMin.value = String(dados.ia_parar_min ?? 30);
    if (pausar) pausar.value = String(dados.ia_pausar_manual ?? 5);
    if (openai) {
      openai.value = '';
      openai.placeholder = dados.openai_tem ? ('Salva: ' + (dados.openai_mascara || '••••')) : 'sk-…';
    }
    if (hint) {
      hint.textContent = dados.openai_tem
        ? 'Já existe uma chave salva. Preencha só se quiser trocar.'
        : 'Usada pela UAZAPI para áudio, embeddings e agentes.';
    }
    if (dados.comunicador) comunicador = dados.comunicador;
    if (typeof dados.ignorar_grupos === 'boolean') ignorarGrupos = dados.ignorar_grupos;
  }

  async function carregarConfig() {
    if (!cfgForm) return;
    if (cfgAviso) {
      cfgAviso.hidden = true;
      cfgAviso.textContent = '';
    }
    try {
      const resposta = await fetch('index.php?p=chat&acao=config', { credentials: 'same-origin' });
      const dados = await resposta.json();
      if (!dados.ok) {
        if (cfgAviso) {
          cfgAviso.hidden = false;
          cfgAviso.textContent = dados.erro || 'Não foi possível abrir as configurações.';
        }
        return;
      }
      preencherConfig(dados);
    } catch (err) {
      if (cfgAviso) {
        cfgAviso.hidden = false;
        cfgAviso.textContent = 'Não foi possível abrir as configurações.';
      }
    }
  }

  cfgForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (cfgBusy) return;
    cfgBusy = true;
    const corpo = new URLSearchParams({
      csrf,
      ignorar_grupos: document.getElementById('cfg-ignorar-grupos')?.checked ? '1' : '0',
      ia_ativa: document.getElementById('cfg-ia-ativa')?.checked ? '1' : '0',
      ia_parar: document.getElementById('cfg-ia-parar')?.value || 'parar',
      ia_parar_min: document.getElementById('cfg-ia-parar-min')?.value || '30',
      ia_pausar_manual: document.getElementById('cfg-ia-pausar-manual')?.value || '5',
      openai_key: document.getElementById('cfg-openai')?.value || '',
    });
    if (cfgAviso) {
      cfgAviso.hidden = false;
      cfgAviso.textContent = 'Salvando…';
    }
    try {
      const resposta = await fetch('index.php?p=chat&acao=salvar_config', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: corpo,
      });
      const dados = await resposta.json();
      if (!dados.ok) {
        if (cfgAviso) cfgAviso.textContent = dados.erro || 'Não foi possível salvar.';
        return;
      }
      preencherConfig(dados);
      if (cfgAviso) cfgAviso.textContent = dados.aviso || 'Configurações salvas.';
      carregarLista(true);
    } catch (err) {
      if (cfgAviso) cfgAviso.textContent = 'Não foi possível salvar.';
    } finally {
      cfgBusy = false;
    }
  });

  iniciarFundo();
})();
