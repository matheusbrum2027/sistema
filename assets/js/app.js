(() => {
  const coarse = window.matchMedia('(pointer: coarse)').matches;
  document.addEventListener('touchmove', (event) => {
    if (event.touches && event.touches.length > 1) event.preventDefault();
  }, { passive: false });
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
  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    side?.classList.toggle('is-open');
    scrim?.classList.toggle('show');
  });
  scrim?.addEventListener('click', () => {
    side?.classList.remove('is-open');
    scrim.classList.remove('show');
  });

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
    if (event.target instanceof HTMLDialogElement) event.preventDefault();
  }, true);

  function abrirDialogos(root) {
    root.querySelectorAll('dialog[open]').forEach((dlg) => {
      dlg.removeAttribute('open');
      dlg.setAttribute('closedby', 'none');
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
  async function carregarReserva(id, alvo) {
    if (!id) return null;
    const existente = document.getElementById(alvo || ('dlg-ver-' + id));
    if (existente) return existente;
    if (!reservasCarregando.has(id)) {
      const contexto = document.body.dataset.page === 'reservas' ? '&contexto=reservas' : '';
      reservasCarregando.set(id, fetch('index.php?p=agenda&fragment=reserva&id=' + encodeURIComponent(id) + contexto, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      }).then(async (resposta) => {
        if (!resposta.ok) throw new Error('Reserva indisponível');
        const tpl = document.createElement('template');
        tpl.innerHTML = await resposta.text();
        document.body.appendChild(tpl.content);
        return document.getElementById(alvo || ('dlg-ver-' + id));
      }).finally(() => reservasCarregando.delete(id)));
    }
    return reservasCarregando.get(id);
  }
  let pageTimer = 0;
  function iniciarPagina() {
    window.clearInterval(pageTimer);
    pageTimer = 0;
    const tick = document.getElementById('tick');
    if (tick && document.body.dataset.autorefresh === '1') {
      let s = 60;
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
        dlg.setAttribute('closedby', 'none');
        dlg.showModal();
      }).catch(() => {});
    }
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
  let navSeq = 0;
  let navCtrl = null;
  async function abrirSemRecarregar(url, gravar) {
    const seq = ++navSeq;
    if (navCtrl) navCtrl.abort();
    navCtrl = new AbortController();
    document.body.classList.add('is-nav');
    let resposta;
    try {
      resposta = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
        signal: navCtrl.signal,
        cache: 'no-store',
      });
    } catch (err) {
      if (err?.name === 'AbortError') return;
      document.body.classList.remove('is-nav');
      location.href = url;
      return;
    }
    if (seq !== navSeq) return;
    const tipo = resposta.headers.get('content-type') || '';
    if (!resposta.ok || !tipo.includes('text/html')) {
      document.body.classList.remove('is-nav');
      location.href = url;
      return;
    }
    try {
      const doc = new DOMParser().parseFromString(await resposta.text(), 'text/html');
      const novo = doc.querySelector('.content');
      if (!novo || doc.querySelector('.auth-wrap')) {
        document.body.classList.remove('is-nav');
        location.href = resposta.url || url;
        return;
      }
      document.querySelectorAll('dialog[open]').forEach((dlg) => {
        if (dlg.id !== 'dlg-confirma') dlg.close();
      });
      const conteudo = document.importNode(novo, true);
      document.querySelector('.content').replaceWith(conteudo);
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
      const destino = resposta.url || url;
      if (gravar) history.pushState({ url: destino }, '', destino);
      marcarMenu(destino);
      document.querySelector('.sidebar')?.classList.remove('is-open');
      document.querySelector('.scrim')?.classList.remove('show');
      window.scrollTo(0, 0);
      document.documentElement.scrollTop = 0;
      document.body.scrollTop = 0;
      iniciarPagina();
    } catch (err) {
      if (err?.name === 'AbortError') return;
      location.href = url;
    } finally {
      if (seq === navSeq) document.body.classList.remove('is-nav');
    }
  }
  document.addEventListener('click', (event) => {
    const olho = event.target.closest('[data-toggle-senha]');
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
    const copiar = event.target.closest('[data-copy]');
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
    const abrir = event.target.closest('[data-open-dialog]');
    if (abrir) {
      const alvo = abrir.getAttribute('data-open-dialog');
      const id = abrir.getAttribute('data-reserva-id');
      const mostrar = (dlg) => {
        if (!dlg) return;
        dlg.setAttribute('closedby', 'none');
        dlg.showModal();
      };
      const dlg = document.getElementById(alvo);
      if (dlg) mostrar(dlg);
      else if (id) carregarReserva(id, alvo).then(mostrar).catch(() => { location.href = 'index.php?p=agenda&ver=' + encodeURIComponent(id); });
      return;
    }
    const fechar = event.target.closest('[data-close-dialog]');
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
    const aba = event.target.closest('[data-form-tab]');
    if (aba) {
      const form = aba.closest('form');
      const nome = aba.getAttribute('data-form-tab');
      form?.querySelectorAll('[data-form-tab]').forEach((btn) => btn.classList.toggle('is-on', btn === aba));
      form?.querySelectorAll('[data-form-pane]').forEach((pane) => { pane.hidden = pane.getAttribute('data-form-pane') !== nome; });
      return;
    }
    const proximo = event.target.closest('[data-form-next]');
    if (proximo) {
      const form = proximo.closest('form');
      const abas = [...(form?.querySelectorAll('[data-form-tab]') || [])];
      const atual = abas.findIndex((btn) => btn.classList.contains('is-on'));
      abas[Math.min(abas.length - 1, atual + 1)]?.click();
      return;
    }
    const editarCfg = event.target.closest('[data-cfg-edit]');
    if (editarCfg) {
      const card = editarCfg.closest('.cfg-card');
      const fieldset = card?.querySelector('fieldset');
      const flag = card?.querySelector('input[name="editando"]');
      if (fieldset) fieldset.disabled = false;
      if (flag) flag.value = '1';
      editarCfg.hidden = true;
      return;
    }
    const tema = event.target.closest('[data-theme-toggle]');
    if (tema) {
      const escuro = document.documentElement.dataset.theme === 'dark';
      pedirConfirmacao(
        [escuro ? 'Deseja realmente voltar para o modo claro?' : 'Deseja realmente ativar o modo escuro?'],
        () => aplicarTema(escuro ? 'light' : 'dark')
      );
      return;
    }
    const imprimir = event.target.closest('[data-print]');
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
    const link = event.target.closest('a[href]');
    if (!link || !link.closest('[data-shell-nav]')) return;
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
    const folha = botao.closest('dialog')?.querySelector('.reserva-folha');
    if (!folha) return;
    let root = document.getElementById('print-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'print-root';
      document.body.appendChild(root);
    }
    root.innerHTML = folha.outerHTML;
    let limparTimer = 0;
    const mql = window.matchMedia ? window.matchMedia('print') : null;
    const noPrintChange = (ev) => {
      if (!ev.matches) limpar();
    };
    const limpar = () => {
      if (!document.body.classList.contains('printing')) return;
      if (limparTimer) clearTimeout(limparTimer);
      limparTimer = 0;
      document.body.classList.remove('printing');
      window.removeEventListener('afterprint', limpar);
      if (mql) {
        if (mql.removeEventListener) mql.removeEventListener('change', noPrintChange);
        else if (mql.removeListener) mql.removeListener(noPrintChange);
      }
    };
    document.body.classList.add('printing');
    window.addEventListener('afterprint', limpar);
    if (mql) {
      if (mql.addEventListener) mql.addEventListener('change', noPrintChange);
      else if (mql.addListener) mql.addListener(noPrintChange);
    }
    // Rede de segurança se afterprint não disparar.
    limparTimer = setTimeout(limpar, 120000);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { window.print(); });
    });
  }
  function aplicarTema(tema, gravar) {
    if (tema === 'dark') document.documentElement.dataset.theme = 'dark';
    else delete document.documentElement.dataset.theme;
    document.querySelectorAll('[data-theme-label]').forEach((el) => {
      el.textContent = tema === 'dark' ? 'Modo claro' : 'Modo escuro';
    });
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
  document.getElementById('dlg-confirma')?.addEventListener('close', () => {
    aoConfirmar = null;
    passoConfirmacao = 0;
  });
})();
