(function () {
  const corpo = document.body;
  if (!corpo || corpo.dataset.notif !== '1') return;

  const estado = document.getElementById('push-estado');
  const ultimoInicial = Number(corpo.dataset.aviso || 0);
  if (!localStorage.getItem('fln-aviso-ultimo')) {
    localStorage.setItem('fln-aviso-ultimo', String(ultimoInicial));
  }
  const tocados = new Set();
  let audio = null;
  let testeLocal = 0;
  let instalarEvento = null;

  function b64ParaBytes(texto) {
    const pad = '='.repeat((4 - (texto.length % 4)) % 4);
    const bin = atob(String(texto).replace(/-/g, '+').replace(/_/g, '/'));
    const saida = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i += 1) saida[i] = bin.charCodeAt(i);
    return saida;
  }

  function textoFalha(err) {
    const texto = String((err && err.message) || err || '');
    if (/push service not available|registration failed/i.test(texto)) {
      return 'Este navegador não entrega avisos com a tela fechada. Abra o painel no Chrome do computador ou do celular e toque em Ativar.';
    }
    return texto || 'Não foi possível ativar.';
  }

  function dizer(texto) {
    if (estado) estado.textContent = texto;
  }

  function garantirAudio() {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!audio) audio = new Ctx();
    if (audio.state === 'suspended') audio.resume();
    return audio;
  }

  function tocar() {
    if (corpo.dataset.som === '0') return;
    const ctx = garantirAudio();
    if (!ctx) return;
    const agora = ctx.currentTime;
    [880, 1175].forEach((freq, indice) => {
      const osc = ctx.createOscillator();
      const ganho = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      const ini = agora + indice * 0.16;
      ganho.gain.setValueAtTime(0.0001, ini);
      ganho.gain.exponentialRampToValueAtTime(0.22, ini + 0.02);
      ganho.gain.exponentialRampToValueAtTime(0.0001, ini + 0.14);
      osc.connect(ganho);
      ganho.connect(ctx.destination);
      osc.start(ini);
      osc.stop(ini + 0.15);
    });
  }

  function vibrar() {
    if (corpo.dataset.vibrar === '0' || !navigator.vibrate) return;
    navigator.vibrate([180, 90, 180, 90, 280]);
  }

  function mostrarFaixa(aviso) {
    let faixa = document.getElementById('aviso-ao-vivo');
    if (!faixa) {
      faixa = document.createElement('button');
      faixa.id = 'aviso-ao-vivo';
      faixa.type = 'button';
      faixa.className = 'aviso-ao-vivo no-print';
      document.body.appendChild(faixa);
      faixa.addEventListener('click', () => {
        const url = faixa.dataset.url || 'index.php?p=agenda';
        faixa.hidden = true;
        if (window.abrirSemRecarregar) window.abrirSemRecarregar(url, true);
        else location.href = url;
      });
    }
    faixa.dataset.url = aviso.url || 'index.php?p=agenda';
    faixa.innerHTML = '<strong></strong><span></span>';
    faixa.querySelector('strong').textContent = aviso.titulo || 'Transfer FLN';
    faixa.querySelector('span').textContent = aviso.corpo || '';
    faixa.hidden = false;
    window.clearTimeout(mostrarFaixa._t);
    mostrarFaixa._t = window.setTimeout(() => { faixa.hidden = true; }, 8000);
  }

  function ehApk() {
    try {
      return !!(window.FlnApp && typeof window.FlnApp.isNative === 'function' && window.FlnApp.isNative());
    } catch (err) {
      return false;
    }
  }

  function receber(aviso, local) {
    if (!aviso) return;
    const id = String(aviso.id || '');
    if (id !== '' && tocados.has(id)) return;
    if (id.startsWith('teste') && Date.now() - testeLocal < 4000) return;
    if (id !== '') tocados.add(id);
    if (aviso.som !== false) tocar();
    if (aviso.vibrar !== false) vibrar();
    if (!local) mostrarFaixa(aviso);
    if (ehApk() && window.FlnApp && typeof window.FlnApp.notify === 'function') {
      try {
        window.FlnApp.notify(
          String(aviso.titulo || 'Transfer FLN'),
          String(aviso.corpo || ''),
          String(aviso.url || 'index.php?p=agenda'),
          Number(aviso.id) || Date.now() % 100000
        );
      } catch (err) { /* native opcional */ }
    }
  }

  function csrf() {
    const meta = document.querySelector('meta[name="csrf"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  async function enviar(acao, campos) {
    const dados = new FormData();
    dados.set('csrf', csrf());
    dados.set('action', acao);
    Object.keys(campos || {}).forEach((chave) => dados.set(chave, campos[chave]));
    const resposta = await fetch('index.php?p=configuracoes', { method: 'POST', body: dados, credentials: 'same-origin' });
    const json = await resposta.json().catch(() => null);
    if (!resposta.ok || !json || !json.ok) {
      throw new Error((json && json.erro) || 'O servidor demorou para concluir. Espere um instante e tente de novo.');
    }
    return json;
  }

  async function inscricaoAtual() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
    const reg = await navigator.serviceWorker.register('sw.js', { scope: './', updateViaCache: 'none' });
    await navigator.serviceWorker.ready;
    return reg.pushManager.getSubscription();
  }

  async function atualizarEstado() {
    if (ehApk()) {
      dizer('APK instalado. Toque em Ativar para receber avisos com o app em segundo plano.');
      return;
    }
    if (!window.isSecureContext) {
      dizer('Este endereço não é seguro. No celular, abra o painel publicado em https e toque em Ativar.');
      return;
    }
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
      dizer('Este navegador não mostra notificação de aplicativo. Use o APK no Android ou o Chrome.');
      return;
    }
    if (Notification.permission === 'denied') {
      dizer('O navegador bloqueou os avisos. Nas configurações do site, permita notificações e volte aqui.');
      return;
    }
    try {
      const atual = await inscricaoAtual();
      dizer(atual
        ? 'Este aparelho está ligado. Uma reserva nova vibra e toca mesmo com o painel fechado.'
        : 'Este aparelho ainda não recebe avisos. Toque em Ativar.');
    } catch (err) {
      dizer('Não foi possível verificar este aparelho. Atualize a página.');
    }
  }

  async function ativar() {
    garantirAudio();
    if (ehApk()) {
      try {
        if (typeof window.FlnApp.requestNotifications === 'function') {
          window.FlnApp.requestNotifications();
        }
        const token = typeof window.FlnApp.deviceToken === 'function' ? String(window.FlnApp.deviceToken() || '') : '';
        if (token.length < 32) {
          dizer('Não foi possível gerar o token deste APK.');
          return;
        }
        const salvo = await enviar('push_inscrever', { tipo: 'apk', token: token });
        dizer(salvo.mensagem || 'APK ligado para avisos.');
        tocar();
        vibrar();
        return;
      } catch (err) {
        dizer(textoFalha(err));
        return;
      }
    }
    if (!window.isSecureContext) {
      dizer('No celular, abra o endereço https do painel. O endereço local não consegue acordar o telefone.');
      return;
    }
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
      dizer('Use o Chrome para instalar e receber os avisos, ou o APK Transfer FLN.');
      return;
    }
    const permissao = await Notification.requestPermission();
    if (permissao !== 'granted') {
      dizer('Permissão negada. Sem ela o celular não vibra com o painel fechado.');
      return;
    }
    const reg = await navigator.serviceWorker.register('sw.js', { scope: './', updateViaCache: 'none' });
    await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
      sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: b64ParaBytes(corpo.dataset.vapid || '')
      });
    }
    const json = sub.toJSON();
    const salvo = await enviar('push_inscrever', {
      endpoint: json.endpoint || '',
      p256dh: (json.keys && json.keys.p256dh) || '',
      auth: (json.keys && json.keys.auth) || ''
    });
    dizer(salvo.mensagem);
    tocar();
    vibrar();
  }

  async function desligar() {
    const atual = await inscricaoAtual();
    const endpoint = atual ? atual.endpoint : '';
    if (atual) await atual.unsubscribe();
    if (endpoint) await enviar('push_remover', { endpoint: endpoint });
    dizer('Este aparelho não vai mais receber avisos.');
  }

  async function testar() {
    garantirAudio();
    testeLocal = Date.now();
    tocar();
    vibrar();
    try {
      const json = await enviar('push_testar', {});
      dizer(json.mensagem);
    } catch (err) {
      dizer(err.message || 'O teste local tocou, mas o envio aos outros aparelhos falhou.');
    }
  }

  document.addEventListener('click', (event) => {
    const alvo = event.target.closest('#push-ativar, #push-testar, #push-desligar, #push-instalar, [data-push-remover]');
    if (!alvo) return;
    event.preventDefault();
    if (alvo.id === 'push-ativar') ativar().catch((err) => dizer(textoFalha(err)));
    else if (alvo.id === 'push-testar') testar();
    else if (alvo.id === 'push-desligar') desligar().catch((err) => dizer(err.message || 'Não foi possível desligar.'));
    else if (alvo.id === 'push-instalar') {
      if (!instalarEvento) {
        dizer('No Android, use o APK FLN Transfer nesta mesma tela. Neste computador, o Chrome oferece a instalação.');
        return;
      }
      instalarEvento.prompt().then(() => {
        instalarEvento = null;
        alvo.hidden = true;
      }).catch(() => {
        dizer('A instalação não foi concluída. No Android, baixe o APK FLN Transfer nesta mesma tela.');
      });
    } else if (alvo.hasAttribute('data-push-remover')) {
      enviar('push_remover', { id: alvo.getAttribute('data-push-remover') || '' })
        .then(() => alvo.closest('li')?.remove())
        .catch((err) => dizer(err.message || 'Não foi possível remover.'));
    }
  });

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    instalarEvento = event;
    const botao = document.getElementById('push-instalar');
    if (botao) botao.hidden = false;
  });

  if (navigator.serviceWorker) {
    navigator.serviceWorker.addEventListener('message', (event) => {
      const dados = event.data || {};
      if (dados.tipo === 'aviso') receber(dados.aviso, false);
    });
  }

  async function buscarNovos() {
    // No APK também busca em background enquanto o WebView estiver vivo.
    if (!ehApk() && document.visibilityState !== 'visible') return;
    const desde = Number(localStorage.getItem('fln-aviso-ultimo') || 0);
    try {
      const resposta = await fetch('index.php?p=aviso&desde=' + encodeURIComponent(String(desde)), { credentials: 'same-origin', cache: 'no-store' });
      if (!resposta.ok) return;
      const json = await resposta.json();
      const itens = Array.isArray(json.itens) ? json.itens : [];
      itens.forEach((item) => receber(item, false));
      if (json.ultimo) localStorage.setItem('fln-aviso-ultimo', String(json.ultimo));
    } catch (err) { /* próxima tentativa */ }
  }

  window.setInterval(buscarNovos, ehApk() ? 25000 : 20000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') buscarNovos();
  });
  if (document.getElementById('push-estado')) atualizarEstado();
  else if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js', { scope: './', updateViaCache: 'none' }).catch(() => {});
  }
})();
