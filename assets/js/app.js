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

  const steps = [80, 90, 100, 110, 125];
  let zi = steps.indexOf(Number(localStorage.getItem('et-zoom') || 100));
  if (zi < 0) zi = 2;
  const applyZoom = () => {
    if (coarse) {
      document.documentElement.style.zoom = '1';
      return;
    }
    document.documentElement.style.zoom = String(steps[zi] / 100);
    const lab = document.querySelector('[data-zoom-label]');
    if (lab) lab.textContent = steps[zi] + '%';
    localStorage.setItem('et-zoom', String(steps[zi]));
  };
  applyZoom();
  if (!coarse) {
    document.querySelectorAll('[data-zoom]').forEach((btn) => {
      btn.addEventListener('click', () => {
        zi = Math.min(steps.length - 1, Math.max(0, zi + Number(btn.getAttribute('data-zoom'))));
        applyZoom();
      });
    });
  }

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

  document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-search')) return;
    const q = input.value.trim().toLowerCase();
    document.querySelectorAll(input.getAttribute('data-search') + ' tbody tr').forEach((tr) => {
      tr.hidden = q !== '' && !tr.innerText.toLowerCase().includes(q);
    });
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
      const novo = document.createElement('script');
      novo.textContent = antigo.textContent;
      antigo.replaceWith(novo);
    });
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
  async function abrirSemRecarregar(url, gravar) {
    const seq = ++navSeq;
    let resposta;
    try {
      resposta = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
    } catch (err) {
      location.href = url;
      return;
    }
    if (seq !== navSeq) return;
    const tipo = resposta.headers.get('content-type') || '';
    if (!resposta.ok || !tipo.includes('text/html')) {
      location.href = url;
      return;
    }
    try {
      const doc = new DOMParser().parseFromString(await resposta.text(), 'text/html');
      const novo = doc.querySelector('.content');
      if (!novo || doc.querySelector('.auth-wrap')) {
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
    } catch (err) {
      location.href = url;
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
      navigator.clipboard.writeText(text).then(() => { copiar.textContent = 'Copiado'; }).catch(() => window.prompt('Copie o link:', text));
      return;
    }
    const abrir = event.target.closest('[data-open-dialog]');
    if (abrir) {
      const dlg = document.getElementById(abrir.getAttribute('data-open-dialog'));
      if (dlg) {
        dlg.setAttribute('closedby', 'none');
        dlg.showModal();
      }
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
  let aoConfirmar = null;
  let passoConfirmacao = 0;
  let textosConfirmacao = [];
  function textosDe(el) {
    const base = el.getAttribute('data-confirm') || '';
    const vezes = Number(el.getAttribute('data-confirm-vezes') || (base ? '1' : '0'));
    if (!base || vezes < 1) return [];
    const lista = [base];
    if (vezes >= 2) lista.push(el.getAttribute('data-confirm-2') || ('Confirme novamente: ' + base));
    return lista;
  }
  function textosStatus(form) {
    const status = form.querySelector('select[name="status"][data-status-inicial]');
    if (!status || status.value === status.dataset.statusInicial) return [];
    const nome = status.options[status.selectedIndex]?.textContent?.trim() || status.value;
    return [
      'Marcar como ' + nome + '?',
      'Confirme novamente: o status ficará como ' + nome + '.'
    ];
  }
  function mostrarConfirmacao() {
    const dlg = document.getElementById('dlg-confirma');
    const titulo = document.getElementById('confirma-titulo');
    const texto = document.getElementById('confirma-texto');
    if (!dlg) return;
    if (titulo) titulo.textContent = passoConfirmacao === 0 ? 'Confirmar' : 'Confirmar novamente';
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
    document.body.classList.add('printing');
    window.print();
    document.body.classList.remove('printing');
  }
  function aplicarTema(tema, gravar) {
    if (tema === 'dark') document.documentElement.dataset.theme = 'dark';
    else delete document.documentElement.dataset.theme;
    document.querySelectorAll('[data-theme-label]').forEach((el) => {
      el.textContent = tema === 'dark' ? 'Modo claro' : 'Modo escuro';
    });
    if (gravar !== false) localStorage.setItem('fln-tema', tema === 'dark' ? 'dark' : 'light');
  }
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === '1') return;
    const textos = textosDe(form);
    const status = textos.length ? [] : textosStatus(form);
    const fila = textos.length ? textos : status;
    if (fila.length === 0) return;
    event.preventDefault();
    pedirConfirmacao(fila, () => {
      let campo = form.querySelector('input[name="confirmar"]');
      if (!campo && form.hasAttribute('data-confirm')) {
        campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = 'confirmar';
        form.appendChild(campo);
      }
      if (campo) campo.value = 'sim';
      form.dataset.confirmed = '1';
      form.requestSubmit();
    });
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
  const tick = document.getElementById('tick');
  if (tick && document.body.dataset.autorefresh === '1') {
    let s = 60;
    window.setInterval(() => {
      s -= 1;
      if (s <= 0) location.reload();
      tick.textContent = String(s);
    }, 1000);
  }
})();
