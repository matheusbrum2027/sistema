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
  const btnIa = document.getElementById('chat-pausar-ia');
  if (!borda || !painel || !abrir || !lista || !msgs || !form || !texto) return;
  let iaPausada = false;

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

  let pollTick = 0;
  function iniciarPoll() {
    pararPoll();
    pollTick = 0;
    // Lista local a cada 20s; UAZAPI só a cada ~60s.
    pollTimer = window.setInterval(() => {
      if (!aberta || document.hidden || authFail || painel.classList.contains('is-cfg')) return;
      pollTick += 1;
      puxarFundo(true, pollTick % 3 === 0);
    }, 20000);
  }

  function iniciarFundo() {
    if (fundoTimer) window.clearInterval(fundoTimer);
    // Fundo: só lista local (rápido). Nunca trava o menu com UAZAPI.
    fundoTimer = window.setInterval(() => {
      if (document.hidden || authFail || aberta) return;
      puxarFundo(false, false);
    }, 45000);
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
    document.dispatchEvent(new CustomEvent('fln:fechar-campanha'));
    document.dispatchEvent(new CustomEvent('fln:fechar-mapa'));
    document.dispatchEvent(new CustomEvent('fln:fechar-analise'));
    aberta = true;
    authFail = false;
    painel.hidden = false;
    borda.hidden = true;
    abrir.setAttribute('aria-expanded', 'true');
    setFull(false);
    mostrarCfg(false);
    travarZoomMobile();
    // Lista local imediata; UAZAPI só em segundo plano (não bloqueia a abertura).
    if (conversas.length) pintarLista();
    else lista.innerHTML = '<p class="chat-vazio">Carregando conversas…</p>';
    carregarLista(true);
    window.setTimeout(() => {
      if (aberta && !authFail) puxarFundo(true, true);
    }, 50);
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

  document.addEventListener('fln:fechar-chat', () => {
    if (aberta) fecharPainel();
  });

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
      + '<span class="chat-item-meio"><strong>' + esc(c.nome) + (c.grupo ? ' · grupo' : '') + '</strong><small class="' + (c.digitando ? 'is-digitando' : '') + '">' + esc(c.digitando || c.preview) + '</small></span>'
      + '<span class="chat-item-lado"><time>' + esc(c.hora) + '</time>'
      + (c.naoLidas ? '<b>' + esc(c.naoLidas > 99 ? '99+' : c.naoLidas) + '</b>' : '')
      + '</span></button>'
    )).join('');
    if (ativa) {
      const abertaAgora = conversas.find((c) => c.id === ativa);
      const fone = document.getElementById('chat-fone');
      if (abertaAgora && fone) fone.textContent = abertaAgora.digitando || abertaAgora.fone || '';
    }
  }

  busca?.addEventListener('input', pintarLista);

  lista.addEventListener('click', (event) => {
    const item = event.target.closest('.chat-item');
    if (!item) return;
    ativa = item.getAttribute('data-id') || '';
    const conv = conversas.find((c) => c.id === ativa);
    document.getElementById('chat-nome').textContent = conv ? conv.nome : 'Conversa';
    document.getElementById('chat-fone').textContent = conv ? (conv.digitando || conv.fone) : '';
    pintarAvatarCabecalho(conv?.foto || '', conv?.letra || '');
    painel.classList.add('is-fio');
    pintarLista();
    msgs.innerHTML = '<p class="chat-vazio">Carregando…</p>';
    carregarMsgs(ativa, false);
    atualizarBotaoIa(ativa);
    if (!isMobile()) texto.focus();
  });

  function pintarBotaoIa() {
    if (!btnIa) return;
    if (!ativa) {
      btnIa.hidden = true;
      return;
    }
    btnIa.hidden = false;
    btnIa.classList.toggle('is-pausada', iaPausada);
    btnIa.textContent = iaPausada ? 'Retomar IA' : 'Pausar IA';
    btnIa.title = iaPausada
      ? 'Retomar a automação nesta conversa'
      : 'Pausar a automação nesta conversa agora';
  }

  async function atualizarBotaoIa(id) {
    if (!btnIa || !id) {
      if (btnIa) btnIa.hidden = true;
      return;
    }
    try {
      const r = await fetch('index.php?p=chat&acao=estado_ia&id=' + encodeURIComponent(id), {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const j = await r.json();
      iaPausada = !!j.pausada;
      pintarBotaoIa();
    } catch (e) {
      pintarBotaoIa();
    }
  }

  btnIa?.addEventListener('click', async () => {
    if (!ativa || authFail) return;
    const acao = iaPausada ? 'retomar_ia' : 'pausar_ia';
    btnIa.disabled = true;
    try {
      const corpo = new URLSearchParams({ csrf, id: ativa });
      const r = await fetch('index.php?p=chat&acao=' + acao, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: corpo,
      });
      const j = await r.json();
      if (!j.ok) {
        window.alert(j.erro || 'Não foi possível alterar a IA.');
      } else {
        iaPausada = !!j.pausada;
        pintarBotaoIa();
      }
    } catch (e) {
      window.alert('Não foi possível alterar a IA.');
    }
    btnIa.disabled = false;
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
        iaPausada = true;
        pintarBotaoIa();
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
    if (ia) ia.checked = dados.ia_ativa !== false && dados.ia_ativa !== 0;
    const digitando = document.getElementById('cfg-mostrar-digitando');
    const online = document.getElementById('cfg-online-ativo');
    const inicio = document.getElementById('cfg-online-inicio');
    const fim = document.getElementById('cfg-online-fim');
    const avaliacao = document.getElementById('cfg-avaliacao-auto');
    if (digitando) digitando.checked = dados.mostrar_digitando !== false;
    if (online) online.checked = dados.online_ativo !== false;
    if (inicio) inicio.value = dados.online_inicio || '06:00';
    if (fim) fim.value = dados.online_fim || '22:00';
    if (avaliacao) avaliacao.checked = dados.avaliacao_auto !== false;
    const opId = document.getElementById('cfg-operador-id');
    const opToken = document.getElementById('cfg-operador-token');
    if (opId && dados.operador_id) opId.value = dados.operador_id;
    if (opToken && dados.operador_token) opToken.value = dados.operador_token;
    if (dados.permissoes && typeof dados.permissoes === 'object') {
      document.querySelectorAll('.cfg-ia-perm').forEach((caixa) => {
        if (Object.prototype.hasOwnProperty.call(dados.permissoes, caixa.value)) {
          caixa.checked = !!dados.permissoes[caixa.value];
        }
      });
    }
    if (parar) parar.value = dados.ia_parar || 'parar';
    if (pararMin) pararMin.value = String(dados.ia_parar_min ?? 30);
    if (pausar) pausar.value = String(dados.ia_pausar_manual ?? 5);
    if (openai) {
      openai.value = '';
      openai.placeholder = dados.openai_tem ? ('Salva: ' + (dados.openai_mascara || '••••')) : 'sk-…';
    }
    if (hint) {
      hint.textContent = dados.openai_tem
        ? 'Chave salva (painel ou OPENAI_API_KEY). A IA responde só sobre a reserva do Comunicar até o status Concluída; aí manda o card de avaliação se estiver ligado.'
        : 'Cole a chave OpenAI (sk-…) e salve. Sem ela a IA não conversa — só os botões do card. Depois do Comunicar a memória fica só daquela reserva.';
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
      mostrar_digitando: document.getElementById('cfg-mostrar-digitando')?.checked ? '1' : '0',
      online_ativo: document.getElementById('cfg-online-ativo')?.checked ? '1' : '0',
      online_inicio: document.getElementById('cfg-online-inicio')?.value || '06:00',
      online_fim: document.getElementById('cfg-online-fim')?.value || '22:00',
      avaliacao_auto: document.getElementById('cfg-avaliacao-auto')?.checked ? '1' : '0',
      openai_key: document.getElementById('cfg-openai')?.value || '',
    });
    const permMarcadas = [];
    document.querySelectorAll('.cfg-ia-perm').forEach((caixa) => {
      if (caixa.checked) permMarcadas.push(caixa.value);
    });
    if (document.querySelector('.cfg-ia-perm')) {
      corpo.set('ia_perm', permMarcadas.join(','));
    }
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
