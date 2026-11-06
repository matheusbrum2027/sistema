/* Transfer FLN — avisos do aplicativo. v1 */
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

function destinoDoAviso(url) {
  try {
    return new URL(url || 'index.php?p=agenda', self.registration.scope).href;
  } catch (err) {
    return new URL('index.php?p=agenda', self.registration.scope).href;
  }
}

self.addEventListener('push', (event) => {
  let dados = {
    id: 'aviso',
    titulo: 'Transfer FLN',
    corpo: 'Nova reserva',
    url: 'index.php?p=agenda',
    som: true,
    vibrar: true
  };
  try {
    dados = Object.assign(dados, event.data ? event.data.json() : {});
  } catch (err) { /* payload vazio */ }

  event.waitUntil((async () => {
    const janelas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const aberta = janelas.some((janela) => janela.visibilityState === 'visible');
    if (aberta) {
      janelas.forEach((janela) => janela.postMessage({ tipo: 'aviso', aviso: dados }));
      return;
    }
    const icone = new URL('assets/icon-192.png', self.registration.scope).href;
    await self.registration.showNotification(dados.titulo || 'Transfer FLN', {
      body: dados.corpo || '',
      icon: icone,
      badge: icone,
      tag: 'fln-' + String(dados.id || Date.now()),
      renotify: true,
      silent: dados.som === false,
      vibrate: dados.vibrar === false ? [] : [180, 90, 180, 90, 280],
      requireInteraction: true,
      data: { url: dados.url || 'index.php?p=agenda' }
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const destino = destinoDoAviso(event.notification.data && event.notification.data.url);
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((janelas) => {
    for (const janela of janelas) {
      if ('focus' in janela) {
        if ('navigate' in janela) {
          return janela.navigate(destino).then((aberta) => (aberta || janela).focus());
        }
        return janela.focus();
      }
    }
    return self.clients.openWindow(destino);
  }));
});
