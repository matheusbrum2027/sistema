(function () {
  var raiz = document.querySelector('[data-mot-painel]');
  if (!raiz) return;
  var grade = raiz.querySelector('[data-mot-grade]');
  var relogio = raiz.querySelector('[data-mot-relogio]');
  if (!grade) return;

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

  function textoClima(codigo) {
    if (codigo === 0) return { texto: 'Céu limpo', icone: 'sol' };
    if (codigo <= 2) return { texto: 'Parcialmente nublado', icone: 'sol-nuvem' };
    if (codigo === 3) return { texto: 'Nublado', icone: 'nuvem' };
    if (codigo === 45 || codigo === 48) return { texto: 'Neblina', icone: 'neblina' };
    if (codigo >= 95) return { texto: 'Tempestade', icone: 'tempestade' };
    if (codigo >= 71 && codigo <= 77) return { texto: 'Frio intenso', icone: 'frio' };
    if (codigo >= 51) return { texto: 'Chuva', icone: 'chuva' };
    return { texto: 'Instável', icone: 'nuvem' };
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

  function moedaDe(json, chave, nome, sigla) {
    var linha = json && json[chave];
    var compra = linha ? Number(String(linha.bid || '').replace(',', '.')) : NaN;
    if (!linha || !isFinite(compra) || compra <= 0) return null;
    var venda = Number(String(linha.ask || linha.bid).replace(',', '.'));
    var variacao = Number(String(linha.pctChange || '0').replace(',', '.'));
    var max = Number(String(linha.high || linha.bid).replace(',', '.'));
    var min = Number(String(linha.low || linha.bid).replace(',', '.'));
    return { moeda: nome, sigla: sigla, compra: compra, venda: venda, variacao: variacao, max: max, min: min };
  }

  function desenhar(clima, cambio) {
    var partes = [];
    if (clima) {
      var dias = (clima.dias || []).map(function (dia, i) {
        return '<button type="button" class="mot-dia' + (i === 0 ? ' is-on' : '') + '" data-mot-dia="' + i + '" data-mot-icone="' + dia.icone + '">' +
          '<b>' + dia.rotulo + '</b><span>' + dia.max + '° / ' + dia.min + '°</span></button>';
      }).join('');
      var infos = (clima.dias || []).map(function (dia, i) {
        return '<p class="mot-dia-info' + (i === 0 ? ' is-on' : '') + '" data-mot-info="' + i + '">' +
          dia.texto + ' · chuva ' + dia.chuva + '%</p>';
      }).join('');
      partes.push(
        '<article class="mot-card mot-card-clima is-aberto mot-ceu-' + clima.icone + '" data-mot-card="clima">' +
          '<span class="mot-ceu" aria-hidden="true">' +
            '<span class="mot-sol"></span><span class="mot-nuvem"></span>' +
            '<span class="mot-chuva"><i></i><i></i><i></i><i></i><i></i><i></i></span>' +
          '</span>' +
          '<span class="mot-card-k">Florianópolis</span>' +
          '<span class="mot-clima-linha"><span class="mot-ico">' + icone(clima.icone) + '</span><strong>' + clima.temp + '°</strong></span>' +
          '<small>' + clima.texto + '</small>' +
          '<span class="mot-dias">' + dias + '</span>' +
          '<span class="mot-detalhe">' + infos +
            '<em>Sensação ' + clima.sensacao + '° · umidade ' + clima.umidade + '% · vento ' + clima.vento + ' km/h</em>' +
          '</span></article>'
      );
    }
    (cambio || []).forEach(function (moeda) {
      var variacao = Number(moeda.variacao || 0);
      var sinal = variacao > 0 ? '+' : '';
      var tom = variacao > 0 ? 'sobe' : (variacao < 0 ? 'desce' : '');
      partes.push(
        '<article class="mot-card mot-card-moeda ' + tom + '" data-mot-card="' + moeda.sigla + '">' +
          '<span class="mot-card-k"><b class="mot-sigla">' + moeda.sigla + '</b> ' + moeda.moeda + '</span>' +
          '<strong>' + dinheiro(moeda.compra) + '</strong>' +
          '<small class="' + tom + '">' + sinal + variacao.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '% hoje</small>' +
          '<span class="mot-bolsa" aria-hidden="true"><svg viewBox="0 0 120 36" preserveAspectRatio="none"><path class="mot-traco" d="' + (variacao < 0 ? 'M0 8 C20 10 30 14 46 18 C62 24 78 22 96 28 L120 32' : 'M0 30 C18 26 34 22 50 16 C68 10 86 12 120 4') + '"/></svg></span>' +
          '<span class="mot-detalhe">Compra ' + dinheiro(moeda.compra) + '<br>Venda ' + dinheiro(moeda.venda) +
            '<br>Mínima ' + dinheiro(moeda.min) + ' · Máxima ' + dinheiro(moeda.max) + '</span></article>'
      );
    });
    if (!partes.length) {
      grade.innerHTML = '<p class="mot-painel-espera">Clima e câmbio indisponíveis agora. Sua agenda continua só com as suas viagens.</p>';
      return;
    }
    grade.innerHTML = partes.join('');
  }

  grade.addEventListener('click', function (ev) {
    var dia = ev.target.closest('[data-mot-dia]');
    if (dia) {
      ev.stopPropagation();
      var cardDia = dia.closest('[data-mot-card]');
      var indice = dia.getAttribute('data-mot-dia');
      cardDia.querySelectorAll('[data-mot-dia], [data-mot-info]').forEach(function (el) {
        var chave = el.getAttribute('data-mot-dia') || el.getAttribute('data-mot-info');
        el.classList.toggle('is-on', chave === indice);
      });
      var ceu = dia.getAttribute('data-mot-icone') || 'nuvem';
      cardDia.className = cardDia.className.replace(/mot-ceu-[\w-]+/, 'mot-ceu-' + ceu);
      return;
    }
    var card = ev.target.closest('[data-mot-card]');
    if (!card) return;
    var vaiAbrir = !card.classList.contains('is-aberto');
    grade.querySelectorAll('[data-mot-card]').forEach(function (outro) {
      var ligado = vaiAbrir && outro === card;
      outro.classList.toggle('is-aberto', ligado);
      outro.setAttribute('aria-expanded', ligado ? 'true' : 'false');
    });
  });

  var climaUrl = 'https://api.open-meteo.com/v1/forecast?latitude=-27.5954&longitude=-48.548&current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=America%2FSao_Paulo&forecast_days=3';
  var cambioUrl = 'https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL';

  Promise.all([
    fetch(climaUrl, { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; }),
    fetch(cambioUrl, { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; })
  ]).then(function (par) {
    var tempo = par[0];
    var fx = par[1];
    var clima = null;
    if (tempo && tempo.current && tempo.daily && Array.isArray(tempo.daily.time)) {
      var rotulo = textoClima(Number(tempo.current.weather_code || 0));
      var nomes = ['Hoje', 'Amanhã', 'Depois'];
      var dias = tempo.daily.time.slice(0, 3).map(function (data, i) {
        var dia = textoClima(Number((tempo.daily.weather_code || [])[i] || 0));
        return {
          rotulo: nomes[i] || data,
          min: Math.round(Number((tempo.daily.temperature_2m_min || [])[i] || 0)),
          max: Math.round(Number((tempo.daily.temperature_2m_max || [])[i] || 0)),
          chuva: Math.round(Number((tempo.daily.precipitation_probability_max || [])[i] || 0)),
          texto: dia.texto,
          icone: dia.icone
        };
      });
      clima = {
        temp: Math.round(Number(tempo.current.temperature_2m || 0)),
        sensacao: Math.round(Number(tempo.current.apparent_temperature || 0)),
        umidade: Math.round(Number(tempo.current.relative_humidity_2m || 0)),
        vento: Math.round(Number(tempo.current.wind_speed_10m || 0)),
        texto: rotulo.texto,
        icone: rotulo.icone,
        dias: dias
      };
    }
    var cambio = [moedaDe(fx, 'USDBRL', 'Dólar', 'USD'), moedaDe(fx, 'EURBRL', 'Euro', 'EUR')].filter(Boolean);
    desenhar(clima, cambio);
  });
})();
