<?php
declare(strict_types=1);
defined('APP') || exit;
$token = strtolower(trim((string) ($_GET['t'] ?? '')));
$info = rastreio_publico($token);
$ok = !empty($info['ok']);
$empresa = trim((string) ($info['empresa'] ?? ''));
if ($empresa === '') {
    $empresa = 'Transfer FLN';
}
$status = (string) ($info['status'] ?? '');
$fechado = in_array($status, ['concluido', 'encerrado'], true);
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <title>Rastreio · <?= e($empresa) ?></title>
  <style>
    :root { color-scheme: dark; --orange: #f26b1a; --ink: #f2f4f8; --muted: #c5ceda; --card: #1e232c; --line: #3a4250; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100dvh; background: #12141a; color: var(--ink); font-family: "Segoe UI", sans-serif; }
    main { width: min(440px, calc(100% - 28px)); margin: 0 auto; padding: max(56px, calc(24px + env(safe-area-inset-top, 0px))) 0 40px; }
    .marca { color: var(--orange); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    h1 { margin: 8px 0 6px; font-size: 28px; line-height: 1.15; }
    p { margin: 0 0 12px; color: var(--muted); line-height: 1.45; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 16px; margin-top: 16px; }
    .rota { display: grid; gap: 10px; }
    .ponto { display: grid; gap: 2px; }
    .ponto span { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .ponto strong { font-size: 15px; line-height: 1.35; }
    .acoes { display: grid; gap: 10px; margin-top: 16px; }
    button { min-height: 52px; border: 0; border-radius: 12px; font-size: 16px; font-weight: 750; cursor: pointer; }
    .go { background: #157347; color: #fff; }
    .stop { background: #b42318; color: #fff; }
    button:disabled { opacity: .45; cursor: default; }
    .estado { min-height: 22px; font-weight: 650; color: var(--ink); }
    .vivo { color: #7ddea8; }
  </style>
</head>
<body>
<main>
  <p class="marca"><?= e($empresa) ?></p>
  <?php if (!$ok): ?>
    <h1>Link indisponível</h1>
    <p>Este endereço não corresponde a um rastreio aberto. Peça um novo link à central.</p>
  <?php else: ?>
    <h1><?= $fechado ? 'Viagem encerrada' : 'Sua viagem está pronta' ?></h1>
    <p><?= e((string) ($info['quando'] ?? '')) ?><?= $fechado ? ' · este link não recebe mais localização.' : ' · toque em iniciar e deixe a página aberta.' ?></p>
    <section class="card rota">
      <div class="ponto"><span>Embarque</span><strong><?= e((string) ($info['origem'] ?? '—')) ?></strong></div>
      <div class="ponto"><span>Desembarque</span><strong><?= e((string) ($info['destino'] ?? '—')) ?></strong></div>
    </section>
    <?php if (!$fechado): ?>
      <div class="acoes">
        <p class="estado" id="ras-estado"><?= $status === 'ao_vivo' ? 'Viagem em andamento.' : 'Aguardando você iniciar.' ?></p>
        <button class="go" id="ras-go" type="button">Iniciar viagem</button>
        <button class="stop" id="ras-stop" type="button"<?= $status === 'ao_vivo' ? '' : ' disabled' ?>>Encerrar viagem</button>
      </div>
      <script>
      (() => {
        const token = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const estado = document.getElementById('ras-estado');
        const go = document.getElementById('ras-go');
        const stop = document.getElementById('ras-stop');
        let watch = 0;
        let ultimo = 0;
        const enviar = async (acao, extra) => {
          const corpo = new FormData();
          corpo.set('acao', acao);
          corpo.set('token', token);
          Object.entries(extra || {}).forEach(([k, v]) => corpo.set(k, String(v)));
          const resposta = await fetch(location.pathname + location.search, { method: 'POST', body: corpo, credentials: 'same-origin' });
          return resposta.json();
        };
        const ponto = (pos) => {
          const agora = Date.now();
          if (agora - ultimo < 10000) return;
          ultimo = agora;
          enviar('ping', {
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            precisao: pos.coords.accuracy || 0
          }).then((dados) => {
            if (dados && dados.erro === 'encerrado') {
              estado.textContent = 'A central encerrou este link.';
              if (watch) navigator.geolocation.clearWatch(watch);
              go.disabled = true;
              stop.disabled = true;
            } else if (dados && dados.ok) {
              estado.textContent = 'Localização enviada.';
              estado.className = 'estado vivo';
            } else {
              estado.textContent = 'Ainda não enviou. A página tenta de novo.';
            }
          }).catch(() => { estado.textContent = 'Sem rede. Tentando de novo.'; });
        };
        const falha = () => { estado.textContent = 'Permita a localização do navegador para iniciar.'; };
        const ligar = () => {
          if (!navigator.geolocation) { falha(); return; }
          go.disabled = true;
          stop.disabled = false;
          estado.textContent = 'Buscando o GPS…';
          navigator.geolocation.getCurrentPosition(ponto, falha, { enableHighAccuracy: true, timeout: 12000 });
          watch = navigator.geolocation.watchPosition(ponto, falha, { enableHighAccuracy: true, maximumAge: 5000 });
        };
        go.addEventListener('click', ligar);
        stop.addEventListener('click', async () => {
          stop.disabled = true;
          if (watch) navigator.geolocation.clearWatch(watch);
          try { await enviar('fim'); } catch (err) {}
          estado.textContent = 'Viagem encerrada.';
          go.disabled = true;
        });
        <?php if ($status === 'ao_vivo'): ?>
        ligar();
        <?php endif; ?>
      })();
      </script>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
