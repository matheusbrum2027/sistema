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

  function curva(pontos, indice) {
    var temps = pontos.map(function (p) { return p.temp; });
    var menor = Math.min.apply(null, temps);
    var maior = Math.max.apply(null, temps);
    var w = 320;
    var h = 70;
    var coords = pontos.map(function (p, i) {
      var x = pontos.length === 1 ? 0 : (i / (pontos.length - 1)) * w;
      var y = maior === menor ? h / 2 : h - 8 - ((p.temp - menor) / (maior - menor)) * (h - 16);
      return x.toFixed(1) + ',' + y.toFixed(1);
    });
    var linha = coords.map(function (c, i) { return (i ? 'L' : 'M') + c; }).join(' ');
    var area = linha + ' L' + w + ' ' + h + ' L0 ' + h + ' Z';
    var horas = pontos.map(function (p) { return '<span>' + p.hora + '</span>'; }).join('');
    var chuvas = pontos.map(function (p) { return '<span>' + p.chuva + '%</span>'; }).join('');
    return '<div class="mot-curva' + (indice === 0 ? ' is-on' : '') + '" data-mot-curva="' + indice + '">' +
      '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">' +
      '<defs><linearGradient id="mot-grad-' + indice + '" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0" stop-color="#f26b1a" stop-opacity=".45"/>' +
      '<stop offset="1" stop-color="#5b9dff" stop-opacity=".05"/></linearGradient></defs>' +
      '<path d="' + area + '" fill="url(#mot-grad-' + indice + ')"/>' +
      '<path d="' + linha + '" fill="none" stroke="#f26b1a" stroke-width="2.5" stroke-linejoin="round"/>' +
      '</svg><div class="mot-horas">' + horas + '</div><div class="mot-chuvas">' + chuvas + '</div></div>';
  }

  function desenhar(clima, cambio) {
    var partes = [];
    if (clima) {
      var dias = (clima.dias || []).map(function (dia, i) {
        return '<button type="button" class="mot-dia' + (i === 0 ? ' is-on' : '') + '" data-mot-dia="' + i + '" data-mot-icone="' + dia.icone + '">' +
          '<b>' + dia.rotulo + '</b><span class="mot-ico">' + icone(dia.icone) + '</span><span>' + dia.max + '° ' + dia.min + '°</span></button>';
      }).join('');
      var agora = (clima.dias || []).map(function (dia, i) {
        var grau = i === 0 ? clima.temp : dia.max;
        var extra = i === 0 ? clima.texto : dia.texto;
        return '<div class="mot-agora' + (i === 0 ? ' is-on' : '') + '" data-mot-agora="' + i + '">' +
          '<span class="mot-ico">' + icone(i === 0 ? clima.icone : dia.icone) + '</span>' +
          '<strong>' + grau + '°</strong>' +
          '<span class="mot-agora-txt"><b>' + extra + '</b><small>A ' + dia.max + '° · M ' + dia.min + '°</small></span></div>';
      }).join('');
      var curvas = (clima.dias || []).map(function (dia, i) { return curva(dia.horas, i); }).join('');
      var lado = '';
      if (clima.chuvaAviso) lado += '<div class="mot-alerta"><strong>' + clima.chuvaAviso + '</strong><small>' + clima.chuvaDetalhe + '</small></div>';
      if (clima.tempestade) lado += '<div class="mot-alerta mot-alerta-tempestade"><strong>' + clima.tempestade + '</strong><small>' + clima.tempestadeDetalhe + '</small></div>';
      lado += '<div class="mot-medidas">' +
        '<div class="mot-medida"><b>Visibilidade</b><strong>' + clima.visibilidade + '</strong><small>' + clima.visibilidadeTexto + '</small></div>' +
        '<div class="mot-medida"><b>Pressão</b><strong>' + clima.pressao + '</strong><small>' + clima.pressaoTexto + '</small></div>' +
        (clima.ar === null ? '' : '<div class="mot-medida"><b>Qualidade do ar</b><strong>' + clima.ar + '</strong><small>' + clima.arTexto + '</small></div>') +
        '<div class="mot-medida"><b>UV</b><strong>' + clima.uv + '</strong><small>' + clima.uvTexto + '</small></div>' +
        '</div>';
      partes.push(
        '<article class="mot-card mot-card-clima mot-ceu-' + clima.icone + '" data-mot-card="clima">' +
          '<span class="mot-ceu" aria-hidden="true">' +
            '<span class="mot-sol"></span><span class="mot-nuvem"></span>' +
            '<span class="mot-chuva"><i></i><i></i><i></i><i></i><i></i><i></i></span>' +
          '</span>' +
          '<div class="mot-clima">' +
            '<div><div class="mot-clima-topo"><div><b>Florianópolis, Santa Catarina</b><small>Atualizado agora · ' + (relogio ? relogio.textContent : '') + '</small></div></div>' +
            agora + '<div class="mot-dias">' + dias + '</div>' + curvas +
            '<small class="mot-extra">Sensação ' + clima.sensacao + '° · umidade ' + clima.umidade + '% · vento ' + clima.vento + ' km/h</small></div>' +
            '<aside class="mot-clima-lado">' + lado + '</aside></div></article>'
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
      cardDia.querySelectorAll('[data-mot-dia], [data-mot-agora], [data-mot-curva]').forEach(function (el) {
        var chave = el.getAttribute('data-mot-dia') || el.getAttribute('data-mot-agora') || el.getAttribute('data-mot-curva');
        el.classList.toggle('is-on', chave === indice);
      });
      var ceu = dia.getAttribute('data-mot-icone') || 'nuvem';
      cardDia.className = cardDia.className.replace(/mot-ceu-[\w-]+/, 'mot-ceu-' + ceu);
      return;
    }
    var card = ev.target.closest('[data-mot-card]');
    if (!card || card.getAttribute('data-mot-card') === 'clima') return;
    var vaiAbrir = !card.classList.contains('is-aberto');
    grade.querySelectorAll('[data-mot-card]').forEach(function (outro) {
      var ligado = vaiAbrir && outro === card;
      outro.classList.toggle('is-aberto', ligado);
      outro.setAttribute('aria-expanded', ligado ? 'true' : 'false');
    });
  });

  function horaDe(iso) {
    return String(iso || '').slice(11, 16);
  }
  function indiceAgora(horas) {
    var alvo = '';
    try {
      alvo = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'America/Sao_Paulo',
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
      }).format(new Date()).replace(' ', 'T').slice(0, 13);
    } catch (e) { alvo = ''; }
    for (var i = 0; i < horas.length; i++) {
      if (String(horas[i]).slice(0, 13) === alvo) return i;
    }
    return 0;
  }
  function amostras(tempo, data, inicio) {
    var horas = tempo.hourly && tempo.hourly.time ? tempo.hourly.time : [];
    var lista = [];
    horas.forEach(function (iso, i) {
      if (String(iso).slice(0, 10) !== data) return;
      if (inicio && i < inicio) return;
      lista.push(i);
    });
    if (lista.length > 8) {
      var passo = Math.ceil(lista.length / 8);
      lista = lista.filter(function (_, n) { return n % passo === 0; }).slice(0, 8);
    }
    return lista.map(function (i) {
      return {
        hora: horaDe(horas[i]),
        temp: Math.round(Number(tempo.hourly.temperature_2m[i] || 0)),
        chuva: Math.round(Number(tempo.hourly.precipitation_probability[i] || 0))
      };
    });
  }
  function textoUv(valor) {
    if (valor <= 2) return 'Baixo';
    if (valor <= 5) return 'Moderado';
    if (valor <= 7) return 'Alto';
    if (valor <= 10) return 'Muito alto';
    return 'Extremo';
  }
  function textoAr(valor) {
    if (valor <= 50) return 'Bom';
    if (valor <= 100) return 'Moderado';
    if (valor <= 150) return 'Ruim';
    return 'Muito ruim';
  }

  var climaUrl = 'https://api.open-meteo.com/v1/forecast?latitude=-27.5954&longitude=-48.548&current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,pressure_msl,visibility&hourly=temperature_2m,precipitation_probability,precipitation,weather_code,pressure_msl,uv_index&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,uv_index_max&timezone=America%2FSao_Paulo&forecast_days=6';
  var arUrl = 'https://air-quality-api.open-meteo.com/v1/air-quality?latitude=-27.5954&longitude=-48.548&current=us_aqi&timezone=America%2FSao_Paulo';
  var cambioUrl = 'https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL';

  Promise.all([
    fetch(climaUrl, { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; }),
    fetch(cambioUrl, { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; }),
    fetch(arUrl, { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; })
  ]).then(function (par) {
    var tempo = par[0];
    var fx = par[1];
    var arJson = par[2];
    var clima = null;
    if (tempo && tempo.current && tempo.daily && Array.isArray(tempo.daily.time) && tempo.hourly) {
      var rotulo = textoClima(Number(tempo.current.weather_code || 0));
      var agora = indiceAgora(tempo.hourly.time || []);
      var dias = tempo.daily.time.slice(0, 6).map(function (data, i) {
        var dia = textoClima(Number((tempo.daily.weather_code || [])[i] || 0));
        var nome = 'Hoje';
        if (i > 0) {
          try {
            nome = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: 'numeric', timeZone: 'America/Sao_Paulo' }).format(new Date(data + 'T12:00:00-03:00'));
          } catch (e) { nome = data.slice(8); }
        }
        var horas = amostras(tempo, data, i === 0 ? agora : 0);
        if (!horas.length) horas = [{ hora: '00:00', temp: Math.round(Number((tempo.daily.temperature_2m_max || [])[i] || 0)), chuva: 0 }];
        return {
          rotulo: nome,
          min: Math.round(Number((tempo.daily.temperature_2m_min || [])[i] || 0)),
          max: Math.round(Number((tempo.daily.temperature_2m_max || [])[i] || 0)),
          texto: dia.texto,
          icone: dia.icone,
          horas: horas
        };
      });
      var chuvaAviso = '';
      var chuvaDetalhe = '';
      var tempestade = '';
      var tempestadeDetalhe = '';
      var codigos = tempo.hourly.weather_code || [];
      var probs = tempo.hourly.precipitation_probability || [];
      var mm = tempo.hourly.precipitation || [];
      var relogios = tempo.hourly.time || [];
      for (var n = agora; n < Math.min(relogios.length, agora + 12); n++) {
        var codigo = Number(codigos[n] || 0);
        var prob = Number(probs[n] || 0);
        var agua = Number(mm[n] || 0);
        if (!tempestade && codigo >= 95) {
          tempestade = n === agora ? 'Tempestade agora' : 'Tempestade por volta das ' + horaDe(relogios[n]);
          tempestadeDetalhe = 'O céu indica trovoada neste período. Confira a rota antes de sair.';
        }
        if (!chuvaAviso && (prob >= 45 || agua >= 0.2)) {
          chuvaAviso = n === agora ? 'Chuva neste momento' : 'Chuva prevista por volta das ' + horaDe(relogios[n]);
          chuvaDetalhe = 'Chance de ' + Math.round(prob) + '% nessa hora.';
        }
      }
      var visMetros = Number(tempo.current.visibility || 0);
      var visKm = visMetros >= 1000 ? Math.round(visMetros / 1000) : Math.round(visMetros / 100) / 10;
      var pressao = Math.round(Number(tempo.current.pressure_msl || 0));
      var pressaoAntes = Number((tempo.hourly.pressure_msl || [])[Math.max(0, agora - 3)] || pressao);
      var uv = Math.round(Number((tempo.daily.uv_index_max || [])[0] || (tempo.hourly.uv_index || [])[agora] || 0));
      var ar = arJson && arJson.current ? Math.round(Number(arJson.current.us_aqi || 0)) : null;
      clima = {
        temp: Math.round(Number(tempo.current.temperature_2m || 0)),
        sensacao: Math.round(Number(tempo.current.apparent_temperature || 0)),
        umidade: Math.round(Number(tempo.current.relative_humidity_2m || 0)),
        vento: Math.round(Number(tempo.current.wind_speed_10m || 0)),
        texto: rotulo.texto,
        icone: rotulo.icone,
        dias: dias,
        chuvaAviso: chuvaAviso,
        chuvaDetalhe: chuvaDetalhe,
        tempestade: tempestade,
        tempestadeDetalhe: tempestadeDetalhe,
        visibilidade: (visKm || 0) + ' km',
        visibilidadeTexto: visKm >= 10 ? 'Excelente' : (visKm >= 4 ? 'Boa' : 'Reduzida'),
        pressao: String(pressao),
        pressaoTexto: pressao > pressaoAntes + 0.4 ? 'Aumento' : (pressao < pressaoAntes - 0.4 ? 'Queda' : 'Estável'),
        uv: String(uv),
        uvTexto: textoUv(uv),
        ar: ar,
        arTexto: ar === null ? '' : textoAr(ar)
      };
    }
    var cambio = [moedaDe(fx, 'USDBRL', 'Dólar', 'USD'), moedaDe(fx, 'EURBRL', 'Euro', 'EUR')].filter(Boolean);
    desenhar(clima, cambio);
  });
})();
