(() => {
  const abrir = document.getElementById('mapa-abrir');
  const painel = document.getElementById('mapa-painel');
  const stage = document.getElementById('mapa-stage');
  const listaEl = document.getElementById('mapa-lista');
  if (!abrir || !painel || !stage || !listaEl) return;

  const btnFull = document.getElementById('mapa-full');
  const icoExpand = btnFull?.innerHTML || '';
  const icoCompress = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h5V4M20 9h-5V4M4 15h5v5M20 15h-5v5"/></svg>';

  let aberta = false;
  let filtro = 'proximas';
  let status = 'todas';
  let dados = null;
  let ativa = 0;
  let poll = 0;
  let map = null;
  let layerPins = null;
  let layerRotas = null;
  let layerHot = null;
  const marcadores = new Map();

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function corStatus(st, tipo) {
    if (st === 'andamento') return '#22c55e';
    if (st === 'pendente') return '#eab308';
    if (st === 'cancelada') return '#94a3b8';
    return tipo === 'destino' ? '#38bdf8' : '#f97316';
  }

  function iconePin(tipo, st) {
    const cor = corStatus(st, tipo);
    return L.divIcon({
      className: 'mapa-leaf-pin',
      html: '<span style="--pin:' + cor + '"></span>',
      iconSize: [22, 28],
      iconAnchor: [11, 28],
      popupAnchor: [0, -24],
    });
  }

  function ajustarMapa() {
    if (!map) return;
    map.invalidateSize({ animate: false });
  }

  function garantirMapa() {
    if (typeof L === 'undefined') return null;
    if (map) {
      ajustarMapa();
      return map;
    }
    L.Icon.Default.mergeOptions({
      iconUrl: 'assets/vendor/leaflet/images/marker-icon.png',
      iconRetinaUrl: 'assets/vendor/leaflet/images/marker-icon-2x.png',
      shadowUrl: 'assets/vendor/leaflet/images/marker-shadow.png',
    });
    map = L.map(stage, {
      zoomControl: true,
      attributionControl: true,
    }).setView([-27.5954, -48.5480], 11);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    layerRotas = L.layerGroup().addTo(map);
    layerPins = L.layerGroup().addTo(map);
    layerHot = L.layerGroup().addTo(map);
    requestAnimationFrame(() => requestAnimationFrame(ajustarMapa));
    setTimeout(ajustarMapa, 180);
    return map;
  }

  function setFull(on) {
    painel.classList.toggle('is-full', on);
    document.body.classList.toggle('mapa-full-on', on);
    if (btnFull) {
      btnFull.setAttribute('aria-pressed', on ? 'true' : 'false');
      btnFull.innerHTML = on ? icoCompress : icoExpand;
    }
    setTimeout(ajustarMapa, 80);
  }

  function badge(n) {
    const b = document.getElementById('mapa-aba-badge');
    if (!b) return;
    if (n <= 0) {
      b.hidden = true;
      b.textContent = '';
      return;
    }
    b.hidden = false;
    b.textContent = n > 99 ? '99+' : String(n);
  }

  function popupHtml(p) {
    return (
      '<div class="mapa-popup">' +
        '<strong>#' + esc(p.id) + ' · ' + esc(p.status) + '</strong>' +
        '<div>' + esc(p.cliente || 'Cliente') + '</div>' +
        '<small>' + esc(p.texto) + (p.par ? ' → ' + esc(p.par) : '') + '</small>' +
        '<small>' + esc(p.data) + ' ' + esc(p.hora || '') + ' · ' + esc(p.tipo) + '</small>' +
      '</div>'
    );
  }

  function pintar() {
    if (!dados || !map) return;
    const r = dados.resumo || {};
    document.getElementById('mapa-kpi-total').textContent = String(r.total || 0);
    document.getElementById('mapa-kpi-pins').textContent = String(r.pins || 0);
    document.getElementById('mapa-kpi-rotas').textContent = String(r.rotas || 0);
    badge(Number(r.total) || 0);
    const linha = document.getElementById('mapa-status-linha');
    if (linha) linha.textContent = (r.total || 0) + ' viagens · Leaflet / OpenStreetMap';

    layerRotas.clearLayers();
    layerPins.clearLayers();
    layerHot.clearLayers();
    marcadores.clear();

    (dados.rotas || []).forEach((rt) => {
      L.polyline(
        [[rt.lat1, rt.lng1], [rt.lat2, rt.lng2]],
        { color: '#f97316', weight: 3, opacity: 0.75, dashArray: '6 8' }
      ).addTo(layerRotas);
    });

    const bounds = [];
    (dados.pins || []).forEach((p) => {
      const m = L.marker([p.lat, p.lng], { icon: iconePin(p.tipo, p.status) })
        .bindPopup(popupHtml(p))
        .addTo(layerPins);
      m.on('click', () => { ativa = Number(p.id); pintarLista(); });
      if (!marcadores.has(Number(p.id))) marcadores.set(Number(p.id), []);
      marcadores.get(Number(p.id)).push(m);
      bounds.push([p.lat, p.lng]);
    });

    (dados.hotspots || []).slice(0, 8).forEach((h) => {
      const circ = L.circleMarker([h.lat, h.lng], {
        radius: Math.min(22, 10 + h.qtd * 2),
        color: '#2563eb',
        weight: 2,
        fillColor: '#3b82f6',
        fillOpacity: 0.28,
      }).bindTooltip(h.nome + ': ' + h.qtd + ' ponto(s)', { permanent: false })
        .addTo(layerHot);
      circ.on('click', () => {
        const pin = (dados.pins || []).find((p) => p.ponto === h.nome);
        if (pin) focar(pin.id);
      });
    });

    if (bounds.length) {
      try {
        map.fitBounds(bounds, { padding: [36, 36], maxZoom: 13 });
      } catch (e) { /* ignore */ }
    } else {
      const c = dados.centro || { lat: -27.5954, lng: -48.5480, zoom: 11 };
      map.setView([c.lat, c.lng], c.zoom || 11);
    }
    pintarLista();
  }

  function pintarLista() {
    const cards = [];
    const visto = {};
    (dados?.pins || []).forEach((p) => {
      if (visto[p.id]) return;
      visto[p.id] = true;
      cards.push(p);
    });
    if (!cards.length) {
      listaEl.innerHTML = '<p class="mapa-vazio">Nenhuma viagem com ponto conhecido neste filtro.</p>';
      return;
    }
    listaEl.innerHTML = cards.slice(0, 40).map((p) => (
      '<button type="button" class="mapa-card' + (Number(p.id) === ativa ? ' is-on' : '') + '" data-id="' + esc(p.id) + '">' +
        '<strong>#' + esc(p.id) + ' · ' + esc(p.hora || '--:--') + '</strong>' +
        '<small>' + esc(p.cliente || 'Cliente') + '</small>' +
        '<small>' + esc(p.texto) + (p.par ? ' → ' + esc(p.par) : '') + '</small>' +
      '</button>'
    )).join('');
  }

  function focar(id) {
    ativa = Number(id) || 0;
    pintarLista();
    const grupo = marcadores.get(ativa) || [];
    if (!grupo.length) return;
    const m = grupo[0];
    map.setView(m.getLatLng(), Math.max(map.getZoom(), 13), { animate: true });
    m.openPopup();
  }

  async function carregar() {
    if (!garantirMapa()) {
      listaEl.innerHTML = '<p class="mapa-vazio">A biblioteca do mapa não carregou. Atualize a página.</p>';
      return;
    }
    try {
      const r = await fetch('index.php?p=mapa&filtro=' + encodeURIComponent(filtro) + '&status=' + encodeURIComponent(status), {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const j = await r.json();
      if (!j.ok) throw new Error(j.erro || 'Falha');
      dados = j;
      pintar();
      ajustarMapa();
      if (ativa) focar(ativa);
    } catch (e) {
      listaEl.innerHTML = '<p class="mapa-vazio">' + esc(e.message || 'Não foi possível carregar o mapa.') + '</p>';
    }
  }

  function abrirPainel() {
    document.dispatchEvent(new CustomEvent('fln:fechar-chat'));
    document.dispatchEvent(new CustomEvent('fln:fechar-campanha'));
    document.dispatchEvent(new CustomEvent('fln:fechar-analise'));
    aberta = true;
    painel.hidden = false;
    const borda = document.getElementById('mapa-borda');
    if (borda) borda.hidden = true;
    abrir.setAttribute('aria-expanded', 'true');
    garantirMapa();
    carregar();
    clearInterval(poll);
    poll = window.setInterval(() => { if (aberta) carregar(); }, 45000);
  }

  function fecharPainel() {
    aberta = false;
    painel.hidden = true;
    const borda = document.getElementById('mapa-borda');
    if (borda) borda.hidden = false;
    abrir.setAttribute('aria-expanded', 'false');
    setFull(false);
    clearInterval(poll);
  }

  abrir.addEventListener('click', () => {
    if (aberta) fecharPainel();
    else abrirPainel();
  });
  painel.querySelectorAll('[data-mapa-fechar]').forEach((b) => b.addEventListener('click', fecharPainel));
  btnFull?.addEventListener('click', () => setFull(!painel.classList.contains('is-full')));
  document.addEventListener('fln:fechar-mapa', () => { if (aberta) fecharPainel(); });
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && aberta) fecharPainel(); });

  document.getElementById('mapa-filtro-periodo')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-filtro]');
    if (!b) return;
    filtro = b.getAttribute('data-filtro') || 'proximas';
    [...b.parentElement.querySelectorAll('button')].forEach((x) => x.classList.toggle('is-on', x === b));
    carregar();
  });
  document.getElementById('mapa-filtro-status')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-status]');
    if (!b) return;
    status = b.getAttribute('data-status') || 'todas';
    [...b.parentElement.querySelectorAll('button')].forEach((x) => x.classList.toggle('is-on', x === b));
    carregar();
  });

  window.addEventListener('resize', ajustarMapa);
  listaEl.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-id]');
    if (!b) return;
    focar(b.getAttribute('data-id'));
  });
})();
