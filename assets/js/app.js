(() => {
  const coarse = window.matchMedia('(pointer: coarse)').matches;
  ['gesturestart', 'gesturechange', 'gestureend'].forEach((name) => {
    document.addEventListener(name, (event) => event.preventDefault());
  });
  if (coarse) {
    document.addEventListener('wheel', (event) => {
      if (event.ctrlKey) event.preventDefault();
    }, { passive: false });
  }

  document.documentElement.style.zoom = '1';
  try { localStorage.removeItem('et-zoom'); } catch (err) { /* o painel fica em 100% */ }

  document.querySelectorAll('details.nav-group').forEach((d) => {
    const key = 'nav-' + d.dataset.group;
    const saved = localStorage.getItem(key);
    if (saved === '0') d.open = false;
    if (saved === '1') d.open = true;
    d.addEventListener('toggle', () => localStorage.setItem(key, d.open ? '1' : '0'));
  });
  document.querySelectorAll('details.nav-group').forEach((d) => {
    if (d.querySelector('.nav-link.active')) d.open = true;
  });

  const side = document.querySelector('.sidebar');
  const scrim = document.querySelector('.scrim');
  function fecharMenu() {
    side?.classList.remove('is-open');
    scrim?.classList.remove('show');
  }
  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    side?.classList.toggle('is-open');
    scrim?.classList.toggle('show');
  });
  scrim?.addEventListener('click', fecharMenu);
  function avisarFlash(root) {
    (root || document).querySelectorAll('.flash').forEach((el) => {
      if (el.dataset.armed === '1') return;
      el.dataset.armed = '1';
      const erro = el.classList.contains('flash-err');
      const fechar = () => {
        el.classList.add('is-saindo');
        window.setTimeout(() => el.remove(), 280);
      };
      el.addEventListener('click', fechar);
      window.setTimeout(fechar, erro ? 8000 : 4500);
    });
  }

  let searchTimer = 0;
  document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-search')) return;
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
      const normalizar = (s) => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
      const q = normalizar(input.value.trim());
      document.querySelectorAll(input.getAttribute('data-search') + ' tbody tr').forEach((tr) => {
        if (!tr.dataset.searchText) tr.dataset.searchText = normalizar(tr.textContent || '');
        tr.hidden = q !== '' && !tr.dataset.searchText.includes(q);
      });
    }, 140);
  });

  document.addEventListener('cancel', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.id === 'dlg-confirma') event.preventDefault();
  }, true);

  function travarCliqueFora(dlg) {
    if (!(dlg instanceof HTMLDialogElement)) return;
    dlg.setAttribute('closedby', dlg.id === 'dlg-confirma' ? 'none' : 'closerequest');
  }

  function abrirDialogos(root) {
    root.querySelectorAll('dialog[open]').forEach((dlg) => {
      dlg.removeAttribute('open');
      travarCliqueFora(dlg);
      dlg.showModal();
    });
  }
  function ativarScripts(root) {
    root.querySelectorAll('script').forEach((antigo) => {
      if ((antigo.getAttribute('type') || '').toLowerCase() === 'application/json') return;
      const novo = document.createElement('script');
      [...antigo.attributes].forEach((attr) => novo.setAttribute(attr.name, attr.value));
      novo.textContent = antigo.textContent;
      antigo.replaceWith(novo);
    });
  }
  const reservasCarregando = new Map();
  const fichasCarregando = new Map();
  async function carregarFicha(tipo, id, alvo) {
    const chave = tipo + ':' + id;
    const existente = document.getElementById(alvo);
    if (existente) return existente;
    if (!fichasCarregando.has(chave)) {
      const pagina = tipo === 'cliente' ? 'clientes' : 'mot-cadastros';
      const fragmento = tipo === 'cliente' ? 'cliente' : 'dossie';
      fichasCarregando.set(chave, fetch('index.php?p=' + pagina + '&fragment=' + fragmento + '&id=' + encodeURIComponent(id), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      }).then(async (resposta) => {
        if (!resposta.ok) throw new Error('Ficha indisponível');
        const tpl = document.createElement('template');
        tpl.innerHTML = await resposta.text();
        document.body.appendChild(tpl.content);
        return document.getElementById(alvo);
      }).finally(() => fichasCarregando.delete(chave)));
    }
    return fichasCarregando.get(chave);
  }
  const cacheReservas = new Map();
  let reservaPrefetchCtrl = null;
  function inserirReserva(html, alvo, id) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html;
    document.body.appendChild(tpl.content);
    return document.getElementById(alvo || ('dlg-ver-' + id));
  }
  function preaquecerReserva(id) {
    const chave = String(id || '');
    if (!chave || cacheReservas.has(chave) || reservasCarregando.has(chave) || navOcupado) return;
    if (reservaPrefetchCtrl) reservaPrefetchCtrl.abort();
    reservaPrefetchCtrl = new AbortController();
    const contexto = document.body.dataset.page === 'reservas' ? '&contexto=reservas' : '';
    fetch('index.php?p=agenda&fragment=reserva&id=' + encodeURIComponent(chave) + contexto, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
      cache: 'no-store',
      signal: reservaPrefetchCtrl.signal,
    }).then(async (resposta) => {
      if (!resposta.ok) return;
      const html = await resposta.text();
      if (html.includes('dlg-ver-')) cacheReservas.set(chave, html);
    }).catch(() => {});
  }
  async function carregarReserva(id, alvo) {
    if (!id) return null;
    const chave = String(id);
    const existente = document.getElementById(alvo || ('dlg-ver-' + chave));
    if (existente) return existente;
    const guardado = cacheReservas.get(chave);
    if (guardado) return inserirReserva(guardado, alvo, chave);
    if (!reservasCarregando.has(chave)) {
      const contexto = document.body.dataset.page === 'reservas' ? '&contexto=reservas' : '';
      reservasCarregando.set(chave, fetch('index.php?p=agenda&fragment=reserva&id=' + encodeURIComponent(chave) + contexto, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      }).then(async (resposta) => {
        if (!resposta.ok) throw new Error('Reserva indisponível');
        const html = await resposta.text();
        cacheReservas.set(chave, html);
        return inserirReserva(html, alvo, chave);
      }).finally(() => reservasCarregando.delete(chave)));
    }
    return reservasCarregando.get(chave);
  }
  let pageTimer = 0;
  function iniciarPagina() {
    avisarFlash(document);
    window.clearInterval(pageTimer);
    pageTimer = 0;
    const tick = document.getElementById('tick');
    if (tick && document.body.dataset.autorefresh === '1') {
      let s = 600;
      pageTimer = window.setInterval(() => {
        s -= 1;
        tick.textContent = String(Math.max(0, s));
        if (s <= 0) {
          window.clearInterval(pageTimer);
          abrirSemRecarregar(location.href, false);
        }
      }, 1000);
      return;
    }
    const waPoll = document.querySelector('[data-wa-poll="1"]');
    const form = document.getElementById('wa-status-form');
    if (waPoll && form) {
      pageTimer = window.setInterval(() => form.requestSubmit(), 12000);
    }
    const deep = document.querySelector('[data-reserva-deep-link]');
    if (deep) {
      const id = deep.getAttribute('data-reserva-deep-link');
      carregarReserva(id, 'dlg-ver-' + id).then((dlg) => {
        if (!dlg || dlg.open) return;
        travarCliqueFora(dlg);
        dlg.showModal();
      }).catch(() => {});
    }
    calIniciar();
  }
  let calTipo = 'todas';
  let calDia = '';
  const calTela = window.matchMedia('(max-width: 900px)');
  function calDiaValido(valor) {
    return /^\d{4}-\d{2}-\d{2}$/.test(valor || '');
  }
  function calDevolver() {
    document.querySelectorAll('#cal-foco-lista .chip').forEach((chip) => {
      const dia = chip.getAttribute('data-dia') || '';
      const dono = calDiaValido(dia) ? document.querySelector('.cal-day[data-dia="' + dia + '"] .cal-itens') : null;
      chip.hidden = false;
      if (dono) dono.appendChild(chip);
    });
  }
  function calMostrar() {
    const lista = document.getElementById('cal-foco-lista');
    const titulo = document.getElementById('cal-foco-titulo');
    const vazio = document.getElementById('cal-foco-vazio');
    const qtd = document.getElementById('cal-foco-qtd');
    if (!lista) return;
    if (!calTela.matches) {
      calDevolver();
      document.querySelectorAll('.cal-day.is-escolhido').forEach((el) => el.classList.remove('is-escolhido'));
      return;
    }
    calDevolver();
    const busca = (document.getElementById('cal-busca')?.value || '').trim().toLowerCase();
    const escolhido = calDiaValido(calDia) ? document.querySelector('.cal-day[data-dia="' + calDia + '"]') : null;
    document.querySelectorAll('.cal-day.is-escolhido').forEach((el) => el.classList.remove('is-escolhido'));
    if (escolhido) escolhido.classList.add('is-escolhido');
    if (titulo) titulo.textContent = busca !== '' ? 'Busca no mês' : (escolhido?.getAttribute('data-rotulo') || 'Dia');
    const origem = busca !== '' ? document.querySelectorAll('.cal-day .chip') : (escolhido ? escolhido.querySelectorAll('.chip') : []);
    let visiveis = 0;
    origem.forEach((chip) => {
      const okTipo = calTipo === 'todas' || chip.classList.contains('chip-' + calTipo);
      const okBusca = busca === '' || (chip.textContent || '').toLowerCase().includes(busca);
      chip.hidden = !(okTipo && okBusca);
      if (!chip.hidden) visiveis += 1;
      lista.appendChild(chip);
    });
    if (qtd) qtd.textContent = visiveis === 1 ? '1 reserva' : visiveis + ' reservas';
    if (vazio) {
      vazio.hidden = visiveis !== 0;
      vazio.textContent = busca !== '' || calTipo !== 'todas' ? 'Nenhuma reserva neste filtro.' : 'Nenhuma reserva neste dia.';
    }
  }
  function calIniciar() {
    if (!document.getElementById('cal-foco')) {
      calDevolver();
      return;
    }
    if (!calTela.matches) {
      calMostrar();
      return;
    }
    if (!calDiaValido(calDia) || !document.querySelector('.cal-day[data-dia="' + calDia + '"]')) {
      const hoje = document.querySelector('.cal-day.today') || document.querySelector('.cal-day.tem:not(.out)') || document.querySelector('.cal-day:not(.out)');
      calDia = hoje?.getAttribute('data-dia') || '';
    }
    document.querySelectorAll('[data-cal-tipo]').forEach((btn) => btn.classList.toggle('is-on', btn.getAttribute('data-cal-tipo') === calTipo));
    calMostrar();
  }
  function marcarMenu(url) {
    const pagina = new URL(url, location.href).searchParams.get('p') || 'agenda';
    document.querySelectorAll('.nav-link').forEach((link) => {
      let alvo = 'agenda';
      try { alvo = new URL(link.href, location.href).searchParams.get('p') || 'agenda'; } catch (err) { alvo = 'agenda'; }
      const ativo = alvo === pagina;
      link.classList.toggle('active', ativo);
      if (ativo) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    document.querySelector('.nav-link.active')?.closest('details')?.setAttribute('open', '');
  }
  const cachePaginas = new Map();
  let filaPrefetch = [];
  let prefetchAtivo = false;
  function chavePagina(url) {
    try {
      const alvo = new URL(url, location.href);
      alvo.hash = '';
      return alvo.pathname + alvo.search;
    } catch (err) {
      return '';
    }
  }
  function podeCachear(url) {
    let alvo;
    try { alvo = new URL(url, location.href); } catch (err) { return false; }
    if (alvo.origin !== location.origin) return false;
    if (!alvo.pathname.endsWith('index.php') && alvo.pathname !== location.pathname) return false;
    if (alvo.searchParams.has('fragment') || alvo.searchParams.has('baixar') || alvo.searchParams.has('hook') || alvo.searchParams.has('captcha')) return false;
    const pagina = alvo.searchParams.get('p') || 'agenda';
    return pagina !== 'entrar';
  }
  function guardarPagina(url, html) {
    const chave = chavePagina(url);
    if (!chave || !html || html.includes('auth-wrap') || html.includes('class="flash') || !html.includes('class="content"')) return;
    cachePaginas.set(chave, html);
    if (cachePaginas.size > 24) cachePaginas.delete(cachePaginas.keys().next().value);
  }
  function pintarPagina(html, url, gravar) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const novo = doc.querySelector('.content');
    if (!novo || doc.querySelector('.auth-wrap')) return false;
    document.querySelectorAll('dialog[open]').forEach((dlg) => {
      if (dlg.id !== 'dlg-confirma') dlg.close();
    });
    const conteudo = document.importNode(novo, true);
    const atual = document.querySelector('.content');
    if (!atual) return false;
    atual.replaceWith(conteudo);
    ativarScripts(conteudo);
    abrirDialogos(conteudo);
    const ferramentas = doc.querySelector('.topbar');
    const atualBarra = document.querySelector('.topbar');
    if (ferramentas && atualBarra) {
      const marca = atualBarra.querySelector('.topbar-brand');
      atualBarra.replaceChildren(marca, ...[...ferramentas.children].filter((el) => !el.classList.contains('topbar-brand')).map((el) => document.importNode(el, true)));
    }
    document.title = doc.title;
    document.body.dataset.page = doc.body.dataset.page || '';
    document.body.dataset.autorefresh = doc.body.dataset.autorefresh || '0';
    if (gravar) history.pushState({ url }, '', url);
    marcarMenu(url);
    fecharMenu();
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    iniciarPagina();
    return true;
  }
  let navOcupado = false;
  let prefetchCtrl = null;
  function bombearPrefetch() {
    if (prefetchAtivo || navOcupado || filaPrefetch.length === 0) return;
    prefetchAtivo = true;
    const passo = () => {
      if (navOcupado) {
        prefetchAtivo = false;
        return;
      }
      const proxima = filaPrefetch.shift();
      if (!proxima) {
        prefetchAtivo = false;
        return;
      }
      prefetchCtrl = new AbortController();
      fetch(proxima, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store', signal: prefetchCtrl.signal })
        .then((resposta) => resposta.ok && (resposta.headers.get('content-type') || '').includes('text/html') ? resposta.text() : '')
        .then((html) => guardarPagina(proxima, html))
        .catch(() => {})
        .finally(() => window.setTimeout(passo, 80));
    };
    passo();
  }
  function pedirPagina(url) {
    if (!podeCachear(url) || cachePaginas.has(chavePagina(url)) || filaPrefetch.includes(url)) return;
    filaPrefetch.push(url);
    bombearPrefetch();
  }
  let navSeq = 0;
  let navCtrl = null;
  function irParaPagina(url) {
    location.assign(url);
  }
  async function abrirSemRecarregar(url, gravar) {
    const seq = ++navSeq;
    navOcupado = true;
    if (navCtrl) navCtrl.abort();
    navCtrl = new AbortController();
    const prazoNav = window.setTimeout(() => {
      if (seq === navSeq) navCtrl.abort();
    }, 12000);
    const guardado = podeCachear(url) ? cachePaginas.get(chavePagina(url)) : '';
    let mostrou = false;
    if (guardado) {
      try { mostrou = pintarPagina(guardado, url, gravar); } catch (err) { mostrou = false; }
    }
    try {
      const resposta = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
        signal: navCtrl.signal,
        cache: 'no-store',
      });
      if (seq !== navSeq) return;
      const tipo = resposta.headers.get('content-type') || '';
      if (!resposta.ok || !tipo.includes('text/html')) {
        if (!mostrou) irParaPagina(url);
        return;
      }
      const html = await resposta.text();
      if (seq !== navSeq) return;
      guardarPagina(resposta.url || url, html);
      if (mostrou && html === guardado) return;
      if (!pintarPagina(html, resposta.url || url, gravar && !mostrou) && !mostrou) {
        irParaPagina(resposta.url || url);
      }
    } catch (err) {
      if (seq !== navSeq) return;
      if (!mostrou) irParaPagina(url);
    } finally {
      window.clearTimeout(prazoNav);
      soltarNav(seq);
    }
  }
  function soltarNav(seq) {
    if (seq !== navSeq) return;
    navOcupado = false;
    bombearPrefetch();
  }
  const pdfBlobs = new Map();
  function visorPdf() {
    let dlg = document.getElementById('dlg-pdf-visor');
    if (dlg) return dlg;
    dlg = document.createElement('dialog');
    dlg.id = 'dlg-pdf-visor';
    dlg.className = 'dlg-pdf dlg-pdf-visor';
    dlg.innerHTML = ''
      + '<header class="dialog-h">'
      + '<div><h2>Pré-visualização do PDF</h2><p class="pdf-visor-nome"></p></div>'
      + '<div class="dialog-tools">'
      + '<button class="btn btn-sm btn-primary" type="button" data-pdf-salvar>Baixar PDF</button>'
      + '<button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button>'
      + '</div></header>'
      + '<div class="dialog-b pdf-visor-corpo">'
      + '<p class="pdf-visor-estado">Carregando a pré-visualização…</p>'
      + '<iframe class="pdf-visor-frame" title="Pré-visualização do PDF" hidden></iframe>'
      + '</div>'
      + '<footer class="dlg-foot">'
      + '<button class="btn btn-primary" type="button" data-pdf-salvar>Baixar PDF</button>'
      + '<button class="btn" type="button" data-close-dialog>Fechar</button>'
      + '</footer>';
    document.body.appendChild(dlg);
    dlg.addEventListener('close', () => {
      const frame = dlg.querySelector('iframe');
      if (frame) {
        frame.hidden = true;
        frame.removeAttribute('src');
      }
    });
    return dlg;
  }
  function salvarArquivo(href, nome) {
    const a = document.createElement('a');
    a.href = href;
    a.download = nome;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }
  async function pdfPronto(url) {
    if (pdfBlobs.has(url)) return pdfBlobs.get(url);
    const pedido = fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(async (resposta) => {
      const bruto = await resposta.arrayBuffer();
      const bytes = new Uint8Array(bruto);
      const ehPdf = resposta.ok && bytes.length > 5 && bytes[0] === 0x25 && bytes[1] === 0x50 && bytes[2] === 0x44 && bytes[3] === 0x46;
      if (!ehPdf) throw new Error('pdf');
      return URL.createObjectURL(new Blob([bruto], { type: 'application/pdf' }));
    });
    pdfBlobs.set(url, pedido);
    try {
      return await pedido;
    } catch (err) {
      pdfBlobs.delete(url);
      throw err;
    }
  }
  async function abrirPreviaPdf(url, nome) {
    const dlg = visorPdf();
    const estado = dlg.querySelector('.pdf-visor-estado');
    const frame = dlg.querySelector('iframe');
    const rotulo = dlg.querySelector('.pdf-visor-nome');
    if (rotulo) rotulo.textContent = nome;
    if (estado) {
      estado.hidden = false;
      estado.textContent = 'Carregando a pré-visualização…';
    }
    if (frame) {
      frame.hidden = true;
      frame.removeAttribute('src');
    }
    dlg.dataset.pdfUrl = url;
    dlg.dataset.pdfNome = nome;
    travarCliqueFora(dlg);
    if (!dlg.open) dlg.showModal();
    try {
      const href = await pdfPronto(url);
      if (dlg.dataset.pdfUrl !== url || !dlg.open) return;
      if (frame) {
        frame.src = href;
        frame.hidden = false;
      }
      if (estado) estado.hidden = true;
    } catch (err) {
      if (estado) {
        estado.hidden = false;
        estado.textContent = 'A pré-visualização não carregou. Toque em Baixar PDF para salvar o arquivo.';
      }
    }
  }
  async function salvarPdfDireto(url, nome, botao) {
    const rotulo = botao ? botao.textContent : '';
    if (botao) {
      botao.disabled = true;
      botao.textContent = 'Baixando…';
    }
    try {
      if (window.FlnApp && typeof window.FlnApp.baixar === 'function') {
        window.FlnApp.baixar(url, nome);
        return;
      }
      const href = await pdfPronto(url);
      salvarArquivo(href, nome);
    } catch (err) {
      salvarArquivo(url, nome);
    } finally {
      if (botao) {
        botao.disabled = false;
        botao.textContent = rotulo;
      }
    }
  }
  function baixarPdfDoLink(link) {
    const nome = (link.getAttribute('data-baixar-pdf') || link.getAttribute('download') || 'reserva.pdf').trim();
    const url = new URL(link.href, location.href);
    url.searchParams.delete('ver');
    abrirPreviaPdf(url.href, nome);
  }
  document.addEventListener('click', (event) => {
    const alvo = event.target instanceof Element ? event.target : event.target.parentElement;
    if (!alvo) return;
    if (alvo.closest('#flutuantes-direita, #mapa-painel, #chat-painel, .leaflet-container')) return;
    const salvarPdf = alvo.closest('[data-pdf-salvar]');
    if (salvarPdf) {
      event.preventDefault();
      const dlg = salvarPdf.closest('#dlg-pdf-visor');
      const url = dlg?.dataset.pdfUrl || '';
      const nome = dlg?.dataset.pdfNome || 'reserva.pdf';
      if (url) salvarPdfDireto(url, nome, salvarPdf);
      return;
    }
    const editarReserva = alvo.closest('[data-editar-reserva]');
    if (editarReserva) {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
      event.preventDefault();
      const destino = new URL(editarReserva.href, location.href);
      if (document.body.dataset.page === 'reservas') {
        const atual = new URL(location.href);
        ['de', 'ate', 'estado', 'motorista', 'faturado', 'q', 'ord', 'dir', 'limpar'].forEach((chave) => {
          if (atual.searchParams.has(chave) && !destino.searchParams.has(chave)) {
            destino.searchParams.set(chave, atual.searchParams.get(chave) || '');
          }
        });
      }
      location.assign(destino.href);
      return;
    }
    const pdfLink = alvo.closest('[data-baixar-pdf]');
    if (pdfLink) {
      event.preventDefault();
      baixarPdfDoLink(pdfLink);
      return;
    }
    const atualizarCorridas = alvo.closest('[data-atualizar-corridas]');
    if (atualizarCorridas) {
      event.preventDefault();
      if (!navOcupado) abrirSemRecarregar(location.href, false);
      return;
    }
    const abrirDia = alvo.closest('.cal-abrir');
    if (abrirDia && calTela.matches) {
      const dia = abrirDia.getAttribute('data-cal-dia') || '';
      if (calDiaValido(dia)) {
        calDia = dia;
        const busca = document.getElementById('cal-busca');
        if (busca) busca.value = '';
        calMostrar();
        document.getElementById('cal-foco')?.scrollIntoView({ block: 'nearest' });
      }
      return;
    }
    const tipoBtn = alvo.closest('[data-cal-tipo]');
    if (tipoBtn && document.getElementById('cal-foco')) {
      calTipo = tipoBtn.getAttribute('data-cal-tipo') || 'todas';
      document.querySelectorAll('[data-cal-tipo]').forEach((btn) => btn.classList.toggle('is-on', btn === tipoBtn));
      calMostrar();
      return;
    }
    const olho = alvo.closest('[data-toggle-senha]');
    if (olho) {
      const campo = document.getElementById('senha');
      if (campo) {
        const visivel = campo.type === 'text';
        campo.type = visivel ? 'password' : 'text';
        olho.textContent = visivel ? 'Mostrar' : 'Ocultar';
        olho.setAttribute('aria-pressed', visivel ? 'false' : 'true');
      }
      return;
    }
    const copiar = alvo.closest('[data-copy]');
    if (copiar) {
      const text = copiar.getAttribute('data-copy') || '';
      const avisar = () => {
        if (copiar.dataset.copyKeep === '1') {
          copiar.classList.add('is-copied');
          window.setTimeout(() => copiar.classList.remove('is-copied'), 1200);
          return;
        }
        copiar.textContent = 'Copiado';
      };
      navigator.clipboard.writeText(text).then(avisar).catch(() => window.prompt('Copie:', text));
      return;
    }
      const abrir = alvo.closest('[data-open-dialog]');
      if (abrir) {
        event.preventDefault();
        const alvo = abrir.getAttribute('data-open-dialog');
        const id = abrir.getAttribute('data-reserva-id');
        const ficha = abrir.getAttribute('data-ficha');
        const fichaId = abrir.getAttribute('data-ficha-id');
        const mostrar = (dlg) => {
          if (!dlg) return;
          travarCliqueFora(dlg);
          dlg.showModal();
        };
        const dlg = document.getElementById(alvo);
        if (dlg) mostrar(dlg);
        else if (ficha && fichaId) carregarFicha(ficha, fichaId, alvo).then(mostrar).catch(() => { location.href = 'index.php?p=clientes'; });
        else if (id) carregarReserva(id, alvo).then(mostrar).catch(() => {
          if (document.body.dataset.page !== 'reservas') location.href = 'index.php?p=agenda&ver=' + encodeURIComponent(id);
        });
        return;
      }
    const fechar = alvo.closest('[data-close-dialog]');
    if (fechar) {
      const dlg = fechar.closest('dialog');
      const chave = dlg?.dataset.clearQuery || (dlg?.id === 'dlg-reserva' ? 'edit' : '');
      if (chave) {
        try {
          const u = new URL(location.href);
          if (u.searchParams.has(chave)) {
            u.searchParams.delete(chave);
            history.replaceState({}, '', u);
          }
        } catch (err) { /* ignore */ }
      }
      dlg?.close();
      return;
    }
    const aba = alvo.closest('[data-form-tab]');
    if (aba) {
      const form = aba.closest('form');
      const nome = aba.getAttribute('data-form-tab');
      form?.querySelectorAll('[data-form-tab]').forEach((btn) => btn.classList.toggle('is-on', btn === aba));
      form?.querySelectorAll('[data-form-pane]').forEach((pane) => { pane.hidden = pane.getAttribute('data-form-pane') !== nome; });
      return;
    }
    const proximo = alvo.closest('[data-form-next]');
    if (proximo) {
      const form = proximo.closest('form');
      const abas = [...(form?.querySelectorAll('[data-form-tab]') || [])];
      const atual = abas.findIndex((btn) => btn.classList.contains('is-on'));
      abas[Math.min(abas.length - 1, atual + 1)]?.click();
      return;
    }
    const proAbre = alvo.closest('[data-pro-abre]');
    if (proAbre) {
      const painel = document.getElementById(proAbre.getAttribute('aria-controls') || '');
      const aberto = proAbre.getAttribute('aria-expanded') === 'true';
      proAbre.setAttribute('aria-expanded', aberto ? 'false' : 'true');
      if (painel) painel.hidden = aberto;
      proAbre.querySelector('.pro-seta')?.classList.toggle('is-baixo', aberto);
      return;
    }
    const editarCfg = alvo.closest('[data-cfg-edit]');
    if (editarCfg) {
      const card = editarCfg.closest('.cfg-card');
      const fieldset = card?.querySelector('fieldset');
      const flag = card?.querySelector('input[name="editando"]');
      if (fieldset) {
        fieldset.disabled = false;
        fieldset.classList.remove('cfg-lock');
      }
      if (flag) flag.value = '1';
      card?.classList.add('is-editando');
      card?.querySelectorAll('[data-cfg-edit]').forEach((btn) => { btn.hidden = true; });
      card?.querySelectorAll('[data-cfg-save]').forEach((btn) => { btn.disabled = false; });
      const estado = card?.querySelector('[data-contrato-estado]');
      if (estado) estado.textContent = 'Editando. Altere os textos e toque em Salvar contrato.';
      const nome = editarCfg.getAttribute('data-cfg-focus') || '';
      const alvo = nome ? card?.querySelector('[name="' + nome + '"]') : null;
      const bloco = alvo?.closest('.contrato-campo');
      if (bloco) bloco.scrollIntoView({ block: 'center', behavior: 'smooth' });
      if (alvo) window.setTimeout(() => alvo.focus(), 280);
      return;
    }
    const tema = alvo.closest('[data-theme-toggle]');
    if (tema) {
      const escuro = document.documentElement.dataset.theme === 'dark';
      pedirConfirmacao(
        [escuro ? 'Deseja realmente voltar para o modo claro?' : 'Deseja realmente ativar o modo escuro?'],
        () => aplicarTema(escuro ? 'light' : 'dark')
      );
      return;
    }
    const imprimir = alvo.closest('[data-print]');
    if (imprimir) {
      const textos = textosDe(imprimir);
      const gerar = () => gerarPdf(imprimir);
      if (textos.length === 0) {
        gerar();
        return;
      }
      pedirConfirmacao(textos, gerar);
      return;
    }
    const link = alvo.closest('a[href]');
    if (!link || !link.closest('[data-shell-nav]')) return;
    if (link.closest('.sidebar')) fecharMenu();
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    let destino;
    try { destino = new URL(link.href, location.href); } catch (err) { return; }
    if (destino.origin !== location.origin) return;
    if (!destino.pathname.endsWith('index.php') && destino.pathname !== location.pathname) return;
    event.preventDefault();
    abrirSemRecarregar(destino.href, true);
  });
  window.addEventListener('popstate', () => {
    abrirSemRecarregar(location.href, false);
  });
  abrirDialogos(document);
  aplicarTema(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light', false);
  iniciarPagina();
  let aoConfirmar = null;
  let passoConfirmacao = 0;
  let textosConfirmacao = [];
  function textosDe(el) {
    const passos = [];
    const base = el.getAttribute('data-confirm') || '';
    if (base) passos.push(base);
    const extra = el.getAttribute('data-confirm-2') || '';
    if (extra) passos.push(extra);
    return passos;
  }
  function textosStatus(form) {
    const status = form.querySelector('select[name="status"][data-status-inicial]');
    if (!status || status.value === status.dataset.statusInicial) return [];
    const nome = status.options[status.selectedIndex]?.textContent?.trim() || status.value;
    return ['Marcar como ' + nome + '?'];
  }
  function mostrarConfirmacao() {
    const dlg = document.getElementById('dlg-confirma');
    const titulo = document.getElementById('confirma-titulo');
    const texto = document.getElementById('confirma-texto');
    if (!dlg) return;
    if (titulo) titulo.textContent = passoConfirmacao > 0 ? 'Segunda confirmação' : 'Confirmar';
    if (texto) texto.textContent = textosConfirmacao[passoConfirmacao] || 'Confirma esta ação?';
    dlg.setAttribute('closedby', 'none');
    if (!dlg.open) dlg.showModal();
  }
  function pedirConfirmacao(textos, callback) {
    textosConfirmacao = textos;
    passoConfirmacao = 0;
    aoConfirmar = callback;
    mostrarConfirmacao();
  }
  function gerarPdf(botao) {
    const dlg = botao.closest('dialog');
    const folha = dlg?.querySelector('.reserva-folha');
    const nome = (botao.getAttribute('data-pdf-arquivo') || '').trim()
      || ((folha?.getAttribute('data-pdf-nome') || 'Reserva').trim() + '.pdf');
    baixarPdfArquivo(dlg, nome);
  }
  function baixarPdfArquivo(dlg, nome) {
    const id = (dlg?.id || '').replace(/^dlg-pdf-/, '');
    if (!/^\d+$/.test(id)) return;
    const arquivo = nome.replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim();
    const url = 'index.php?p=agenda&baixar=pdf&id=' + encodeURIComponent(id);
    abrirPreviaPdf(new URL(url, location.href).href, arquivo || 'reserva.pdf');
  }
  function aplicarTema(tema, gravar) {
    if (tema === 'dark') document.documentElement.dataset.theme = 'dark';
    else delete document.documentElement.dataset.theme;
    document.querySelectorAll('[data-theme-label]').forEach((el) => {
      el.textContent = tema === 'dark' ? 'Modo claro' : 'Modo escuro';
    });
    const barra = document.querySelector('meta[name="theme-color"]');
    if (barra) barra.setAttribute('content', tema === 'dark' ? '#12141a' : '#eef0f3');
    if (gravar !== false) localStorage.setItem('fln-tema', tema === 'dark' ? 'dark' : 'light');
  }
  function marcarConfirmado(form) {
    let campo = form.querySelector('input[name="confirmar"]');
    if (!campo) {
      campo = document.createElement('input');
      campo.type = 'hidden';
      campo.name = 'confirmar';
      form.appendChild(campo);
    }
    campo.value = 'sim';
    form.dataset.confirmed = '1';
  }
  const syncComCard = (card) => {
    if (!(card instanceof HTMLElement) || !card.classList.contains('com-card')) return;
    const box = card.querySelector('input[type="checkbox"]');
    if (!(box instanceof HTMLInputElement) || box.disabled) {
      card.classList.add('is-off');
      card.classList.remove('is-on');
      return;
    }
    card.classList.toggle('is-on', box.checked);
    card.classList.toggle('is-off', false);
  };
  document.addEventListener('change', (event) => {
    const box = event.target;
    if (!(box instanceof HTMLInputElement) || box.type !== 'checkbox' || box.name !== 'destino[]') return;
    syncComCard(box.closest('.com-card'));
    const form = box.closest('.com-form');
    if (form && form.querySelectorAll('input[name="destino[]"]:checked:not(:disabled)').length > 0) {
      form.querySelector('.com-aviso')?.remove();
    }
  });

  function marcarFormBusy(form) {
    if (form.dataset.busy === '1') return false;
    form.dataset.busy = '1';
    form.querySelectorAll('button[type="submit"]').forEach((btn) => {
      btn.disabled = true;
      if (!btn.dataset.labelOriginal) btn.dataset.labelOriginal = btn.textContent || '';
      btn.textContent = 'Salvando…';
    });
    return true;
  }
  document.addEventListener('invalid', (event) => {
    const campo = event.target;
    if (!(campo instanceof HTMLInputElement) && !(campo instanceof HTMLSelectElement) && !(campo instanceof HTMLTextAreaElement)) return;
    const form = campo.form;
    if (!(form instanceof HTMLFormElement) || !form.closest('dialog')) return;
    event.preventDefault();
    if (form.dataset.avisoInvalido === '1') return;
    form.dataset.avisoInvalido = '1';
    window.setTimeout(() => { delete form.dataset.avisoInvalido; }, 0);
    const rotulo = (campo.closest('label')?.innerText || 'Campo obrigatório').split('\n')[0].replace(/\s+/g, ' ').trim();
    const aviso = document.createElement('div');
    aviso.className = 'flash flash-err';
    aviso.setAttribute('role', 'alert');
    aviso.textContent = rotulo + ': ' + (campo.validationMessage || 'preencha este campo.');
    const painel = form.querySelector('.dialog-b, .reserva-scroll') || form;
    painel.querySelector(':scope > .flash-err')?.remove();
    painel.prepend(aviso);
    window.setTimeout(() => aviso.remove(), 7000);
    const rolagem = campo.closest('.dialog-b, .reserva-scroll');
    if (rolagem) campo.scrollIntoView({ block: 'center', inline: 'nearest' });
    campo.focus({ preventScroll: true });
  }, true);
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.busy === '1') {
      event.preventDefault();
      return;
    }
    if (form.classList.contains('com-form') && form.dataset.confirmed !== '1') {
      const marcados = form.querySelectorAll('input[name="destino[]"]:checked:not(:disabled)');
      if (marcados.length === 0) {
        event.preventDefault();
        let aviso = form.querySelector('.com-aviso');
        if (!aviso) {
          aviso = document.createElement('p');
          aviso.className = 'help com-aviso';
          form.querySelector('.com-cards')?.after(aviso);
        }
        aviso.textContent = 'Marque ao menos um card (motorista ou cliente).';
        form.querySelector('.com-card:not(.is-off)')?.focus?.();
        return;
      }
      form.querySelector('.com-aviso')?.remove();
    }
    if (form.dataset.confirmed !== '1') {
      const textos = textosDe(form);
      const status = textos.length > 0 ? [] : textosStatus(form);
      const lista = textos.length > 0 ? textos : status;
      if (lista.length > 0) {
        event.preventDefault();
        pedirConfirmacao(lista, () => {
          marcarConfirmado(form);
          form.requestSubmit();
        });
        return;
      }
    }
    cachePaginas.clear();
    cacheReservas.clear();
    marcarFormBusy(form);
  });
  document.getElementById('confirma-sim')?.addEventListener('click', () => {
    if (passoConfirmacao < textosConfirmacao.length - 1) {
      passoConfirmacao += 1;
      mostrarConfirmacao();
      return;
    }
    const seguir = aoConfirmar;
    aoConfirmar = null;
    document.getElementById('dlg-confirma')?.close();
    if (seguir) seguir();
  });
  document.addEventListener('input', (event) => {
    if (event.target?.id === 'cal-busca') calMostrar();
  });
  calTela.addEventListener('change', () => calIniciar());
  document.getElementById('dlg-confirma')?.addEventListener('close', () => {
    aoConfirmar = null;
    passoConfirmacao = 0;
  });
})();
