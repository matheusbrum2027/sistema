(function () {
  var raiz = document.querySelector('[data-mot-painel]');
  if (!raiz) return;
  var grade = raiz.querySelector('[data-mot-grade]');
  var relogio = raiz.querySelector('[data-mot-relogio]');
  var aberto = 'clima';

  function hora() {
    if (!relogio) return;
    try {
      relogio.textContent = new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        hour: '2-digit',
        minute: '2-digit'
      }).format(new Date());
    } catch (e) {}
  }
  hora();
  setInterval(hora, 30000);

  function dinheiro(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function icone(nome) {
    var comum = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    if (nome === 'sol') return '<svg ' + comum + '><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
    if (nome === 'chuva') return '<svg ' + comum + '><path d="M7 16a4 4 0 1 1 .6-8 5 5 0 0 1 9.6 1.5A3.5 3.5 0 1 1 17 16"/><path d="M8 19l-1 2M12 19l-1 2M16 19l-1 2"/></svg>';
    if (nome === 'tempestade') return '<svg ' + comum + '><path d="M7 15a4 4 0 1 1 .6-8 5 5 0 0 1 9.6 1.5A3.5 3.5 0 1 1 17 15"/><path d="M11 16l-2 4h3l-2 4"/></svg>';
    if (nome === 'neblina' || nome === 'frio') return '<svg ' + comum + '><path d="M4 9h16M4 13h16M4 17h10"/></svg>';
    if (nome === 'sol-nuvem') return '<svg ' + comum + '><circle cx="8" cy="9" r="3"/><path d="M8 4v1.5M4.5 6.2l1 1M3 9h1.5"/><path d="M10 16a3.5 3.5 0 1 1 .5-7 4.2 4.2 0 0 1 8 1.4A3 3 0 1 1 18 16"/></svg>';
    return '<svg ' + comum + '><path d="M7 16a4 4 0 1 1 .6-8 5 5 0 0 1 9.6 1.5A3.5 3.5 0 1 1 17 16H7z"/></svg>';
  }

  function desenhar(dados) {
    var agora = dados.agora || {};
    var dias = Array.isArray(dados.dias) ? dados.dias : [];
    var cambio = Array.isArray(dados.cambio) ? dados.cambio : [];
    var diasHtml = dias.map(function (dia, i) {
      return '<button type="button" class="mot-dia' + (i === 0 ? ' is-on' : '') + '" data-mot-dia="' + i + '">' +
        '<b>' + dia.rotulo + '</b><span>' + dia.max + '° / ' + dia.min + '°</span></button>';
    }).join('');
    var detalheDias = dias.map(function (dia, i) {
      return '<p class="mot-dia-info' + (i === 0 ? ' is-on' : '') + '" data-mot-info="' + i + '">' +
        dia.texto + ' · chuva ' + dia.chuva + '%</p>';
    }).join('');
    var moedas = cambio.map(function (moeda) {
      var variacao = Number(moeda.variacao || 0);
      var sinal = variacao > 0 ? '+' : '';
      var tom = variacao > 0 ? 'sobe' : (variacao < 0 ? 'desce' : '');
      return '<button type="button" class="mot-card" data-mot-card="' + moeda.sigla + '" aria-expanded="false">' +
        '<span class="mot-card-k">' + moeda.moeda + '</span>' +
        '<strong>' + dinheiro(moeda.compra) + '</strong>' +
        '<small class="' + tom + '">' + sinal + variacao.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%</small>' +
        '<span class="mot-detalhe">Compra ' + dinheiro(moeda.compra) + ' · Venda ' + dinheiro(moeda.venda) +
        '<br>Mín. ' + dinheiro(moeda.min) + ' · Máx. ' + dinheiro(moeda.max) + '</span></button>';
    }).join('');
    grade.innerHTML =
      '<button type="button" class="mot-card mot-card-clima is-aberto" data-mot-card="clima" aria-expanded="true">' +
        '<span class="mot-card-k">' + (dados.cidade || 'Florianópolis') + '</span>' +
        '<span class="mot-clima-linha"><span class="mot-ico">' + icone(agora.icone) + '</span><strong>' + agora.temp + '°</strong></span>' +
        '<small>' + (agora.texto || 'Clima') + '</small>' +
        '<span class="mot-detalhe">' +
          '<span class="mot-dias">' + diasHtml + '</span>' + detalheDias +
          '<em>Sensação ' + agora.sensacao + '° · umidade ' + agora.umidade + '% · vento ' + agora.vento + ' km/h</em>' +
        '</span></button>' + moedas;
    var sel = grade.querySelector('[data-mot-card="' + aberto + '"]');
    grade.querySelectorAll('[data-mot-card]').forEach(function (card) {
      var ligado = card === sel;
      card.classList.toggle('is-aberto', ligado);
      card.setAttribute('aria-expanded', ligado ? 'true' : 'false');
    });
  }

  grade.addEventListener('click', function (ev) {
    var dia = ev.target.closest('[data-mot-dia]');
    if (dia) {
      var card = dia.closest('[data-mot-card]');
      var indice = dia.getAttribute('data-mot-dia');
      card.querySelectorAll('[data-mot-dia], [data-mot-info]').forEach(function (el) {
        var chave = el.getAttribute('data-mot-dia') || el.getAttribute('data-mot-info');
        el.classList.toggle('is-on', chave === indice);
      });
      return;
    }
    var card = ev.target.closest('[data-mot-card]');
    if (!card) return;
    aberto = card.getAttribute('data-mot-card') || 'clima';
    var vaiAbrir = !card.classList.contains('is-aberto');
    grade.querySelectorAll('[data-mot-card]').forEach(function (outro) {
      var ligado = vaiAbrir && outro === card;
      outro.classList.toggle('is-aberto', ligado);
      outro.setAttribute('aria-expanded', ligado ? 'true' : 'false');
    });
  });

  fetch('index.php?p=clima', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then(function (res) { return res.ok ? res.json() : null; })
    .then(function (dados) {
      if (!dados || !dados.ok) {
        grade.innerHTML = '<p class="mot-painel-espera">Clima e câmbio indisponíveis agora. Sua agenda continua só com as suas viagens.</p>';
        return;
      }
      desenhar(dados);
    })
    .catch(function () {
      grade.innerHTML = '<p class="mot-painel-espera">Clima e câmbio indisponíveis agora. Sua agenda continua só com as suas viagens.</p>';
    });
})();
