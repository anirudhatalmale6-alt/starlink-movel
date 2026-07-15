/* ============================================================
   Starlink Móvel — landing interactions + qualification quiz
   ============================================================ */
(function () {
  'use strict';

  /* ---------- Reveal on scroll ---------- */
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });

  /* ---------- Animated counters ---------- */
  function animateCount(el) {
    var to = parseFloat(el.getAttribute('data-to'));
    var sep = el.getAttribute('data-sep') === '1';
    var dur = 1600, start = null;
    function fmt(n) {
      n = Math.floor(n);
      return sep ? n.toLocaleString('pt-BR') : String(n);
    }
    function tick(t) {
      if (!start) start = t;
      var p = Math.min((t - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(to * eased);
      if (p < 1) requestAnimationFrame(tick);
      else el.textContent = fmt(to);
    }
    requestAnimationFrame(tick);
  }
  var cio = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { animateCount(e.target); cio.unobserve(e.target); }
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('.count').forEach(function (el) { cio.observe(el); });

  /* ============================================================
     QUIZ
     ============================================================ */
  var STEPS = [
    { type: 'text', key: 'nome', title: 'Olá.', desc: 'Vou te ajudar a verificar se a Starlink Móvel é a solução ideal para você.',
      q: 'Qual é o seu nome?', placeholder: 'Digite seu nome' },
    { type: 'phone', key: 'whatsapp', title: 'Prazer, {nome}.', desc: 'Precisamos do seu WhatsApp para o suporte técnico.',
      q: 'Seu número de WhatsApp?', placeholder: '(00) 00000-0000' },
    { type: 'text', key: 'localizacao', title: 'Localização', desc: 'Informe sua cidade e estado para verificarmos a cobertura na sua região.',
      q: 'Onde você está?', placeholder: 'Cidade e Estado' },
    { type: 'options', key: 'motivo_uso', title: 'Cenário de uso', desc: 'Isso nos ajuda a entender como a Starlink Móvel vai te atender.',
      q: 'Em qual cenário você pretende usar a Starlink Móvel?',
      options: ['Área rural', 'Deslocamento / estrada', 'Atividade profissional externa', 'Uso pessoal', 'Outro contexto'] },
    { type: 'options', key: 'dispositivo', title: 'Dispositivo', desc: 'A Starlink Móvel é configurada diretamente no seu celular.',
      q: 'Qual é o sistema do seu dispositivo?',
      options: ['Android', 'iPhone (iOS)'] },
    { type: 'options', key: 'internet_atual', title: 'Situação atual', desc: 'Entender sua conexão atual nos ajuda a dimensionar a solução.',
      q: 'Qual é a sua situação atual de internet?',
      options: ['Sem internet ou sinal péssimo', 'Internet lenta e instável', 'Pago caro por um serviço ruim', 'Tenho internet ok, quero upgrade'] },
    { type: 'cta', key: 'ativacao', title: 'Ativação', desc: 'Sua qualificação foi concluída com sucesso. Falta apenas um passo.',
      q: 'Pronto para ativar sua conexão?', cta: 'Ativar agora', ctaNote: 'Iniciar minha ativação imediatamente' },
    { type: 'review', title: 'Confirme seus dados', desc: 'Verifique se está tudo certo antes de enviar.' }
  ];

  var answers = {};
  var idx = 0;

  var quiz = document.getElementById('quiz');
  var body = document.getElementById('quizBody');
  var stepNum = document.getElementById('quizStepNum');
  var barFill = document.getElementById('quizBarFill');
  var backBtn = document.getElementById('quizBack');
  var closeBtn = document.getElementById('quizClose');

  document.querySelectorAll('.js-open-quiz').forEach(function (b) {
    b.addEventListener('click', openQuiz);
  });
  closeBtn.addEventListener('click', closeQuiz);
  backBtn.addEventListener('click', function () { if (idx > 0) { idx--; render(); } });

  function openQuiz() {
    answers = {}; idx = 0;
    quiz.classList.add('open'); quiz.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    render();
  }
  function closeQuiz() {
    quiz.classList.remove('open'); quiz.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function tpl(str) { return str.replace(/\{(\w+)\}/g, function (_, k) { return answers[k] || ''; }); }

  function render() {
    var s = STEPS[idx];
    stepNum.textContent = idx + 1;
    barFill.style.width = ((idx + 1) / STEPS.length * 100) + '%';
    backBtn.classList.toggle('show', idx > 0);

    var html = '<div class="qstep">';
    html += '<h2 class="qstep__title">' + tpl(s.title) + '</h2>';
    if (s.desc) html += '<p class="qstep__desc">' + tpl(s.desc) + '</p>';

    if (s.type === 'text') {
      html += '<div class="qstep__q">' + s.q + '</div>';
      html += '<input class="qinput" id="qf" type="text" placeholder="' + s.placeholder + '" value="' + (answers[s.key] || '') + '" autocomplete="off" />';
      html += foot('Continuar');
    } else if (s.type === 'phone') {
      html += '<div class="qstep__q">' + s.q + '</div>';
      html += '<div class="qphone"><span class="qphone__cc">BR +55</span><input class="qinput" id="qf" type="tel" inputmode="numeric" placeholder="' + s.placeholder + '" value="' + (answers[s.key] || '') + '" autocomplete="off" /></div>';
      html += foot('Continuar');
    } else if (s.type === 'options') {
      html += '<div class="qstep__q">' + s.q + '</div><div class="qoptions">';
      s.options.forEach(function (o) {
        var sel = answers[s.key] === o ? ' sel' : '';
        html += '<button class="qopt' + sel + '" data-val="' + o + '">' + o + '<span class="qopt__arw">&rarr;</span></button>';
      });
      html += '</div>';
    } else if (s.type === 'cta') {
      html += '<div class="qstep__q">' + s.q + '</div>';
      html += '<div class="qoptions"><button class="qopt" data-val="Imediata"><span>' + s.cta + '</span><span class="qopt__arw">&rarr;</span></button></div>';
      html += '<p class="qstep__desc" style="margin-top:18px;margin-bottom:0;font-size:14px">' + s.ctaNote + '</p>';
    } else if (s.type === 'review') {
      html += '<div class="qreview">';
      html += row('Nome', answers.nome);
      html += row('WhatsApp', '+55 ' + (answers.whatsapp || ''));
      html += row('Localização', answers.localizacao);
      html += row('Cenário de uso', answers.motivo_uso);
      html += row('Dispositivo', answers.dispositivo);
      html += row('Internet atual', answers.internet_atual);
      html += row('Ativação', answers.ativacao || 'Imediata');
      html += '</div>';
      html += '<div class="qfoot"><button class="qbtn" id="qsend">Confirmar e Enviar &rarr;</button></div>';
    }
    html += '</div>';
    body.innerHTML = html;
    wire();
  }

  function row(k, v) {
    return '<div class="qreview__row"><span class="qreview__k">' + k + '</span><span class="qreview__v">' + (v || '—') + '</span></div>';
  }
  function foot(label) {
    return '<div class="qfoot"><button class="qbtn" id="qnext">' + label + ' &rarr;</button></div>';
  }

  function wire() {
    var s = STEPS[idx];
    var input = document.getElementById('qf');
    var nextBtn = document.getElementById('qnext');
    var sendBtn = document.getElementById('qsend');

    if (input) {
      input.focus();
      function validate() {
        var v = input.value.trim();
        var ok = s.type === 'phone' ? v.replace(/\D/g, '').length >= 10 : v.length >= 2;
        if (nextBtn) nextBtn.disabled = !ok;
      }
      validate();
      input.addEventListener('input', validate);
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && nextBtn && !nextBtn.disabled) commitAndNext(s, input.value.trim());
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        if (input) commitAndNext(s, input.value.trim());
      });
    }
    document.querySelectorAll('.qopt').forEach(function (btn) {
      btn.addEventListener('click', function () {
        answers[s.key] = btn.getAttribute('data-val');
        setTimeout(function () { idx++; render(); }, 160);
      });
    });
    if (sendBtn) sendBtn.addEventListener('click', submitLead);
  }

  function commitAndNext(s, val) {
    answers[s.key] = val;
    idx++;
    render();
  }

  /* ---------- Submit → save lead + round-robin attendant ---------- */
  var CONNECT_MSGS = ['Analisando suas informações...', 'Verificando disponibilidade...', 'Localizando um especialista...', 'Atendente encontrado.'];

  // Modo demonstração: quando o site roda sem o backend PHP (ex.: GitHub Pages),
  // a mensagem de WhatsApp é montada no próprio navegador com um número de exemplo,
  // para que o fluxo completo possa ser demonstrado. Na hospedagem real (com o
  // backend), o número vem do painel admin em fila (round-robin).
  var DEMO_NUMBER = '5511999999999';
  var DEMO_TEMPLATE =
    'Olá, meu nome é {nome}. Finalizei meu processo de qualificação na Starlink Móvel.\n\n' +
    '📍 Localização: {localizacao}\n' +
    '🎯 Motivo de uso: {motivo_uso}\n' +
    '📡 Internet atual: {internet_atual}\n' +
    '📱 Dispositivo: {dispositivo}\n' +
    '⚡ Ativação: Imediata\n\n' +
    'Gostaria de continuar meu atendimento para realizar minha instalação.';

  function demoWhatsappUrl() {
    var msg = DEMO_TEMPLATE.replace(/\{(\w+)\}/g, function (_, k) { return answers[k] || ''; });
    return 'https://wa.me/' + DEMO_NUMBER + '?text=' + encodeURIComponent(msg);
  }

  function submitLead() {
    // connecting screen
    body.innerHTML =
      '<div class="qconnect">' +
      '<div class="qspinner"></div>' +
      '<div class="qconnect__msg" id="qcMsg">' + CONNECT_MSGS[0] + '</div>' +
      '<div class="qconnect__brand">Starlink · Conectando você a um especialista</div>' +
      '</div>';
    backBtn.classList.remove('show');
    var msgEl = document.getElementById('qcMsg');
    var i = 0;
    var timer = setInterval(function () {
      i++;
      if (i < CONNECT_MSGS.length && msgEl) msgEl.textContent = CONNECT_MSGS[i];
    }, 1100);

    fetch('api/lead.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(answers)
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        setTimeout(function () {
          clearInterval(timer);
          if (data && data.ok && data.whatsapp_url) {
            window.location.href = data.whatsapp_url;
          } else {
            // sem atendente cadastrado ainda → usa o modo demonstração
            window.location.href = demoWhatsappUrl();
          }
        }, 4400);
      })
      .catch(function () {
        // backend indisponível (ex.: demo no GitHub Pages) → modo demonstração
        setTimeout(function () { clearInterval(timer); window.location.href = demoWhatsappUrl(); }, 4400);
      });
  }

  function showError() {
    body.innerHTML =
      '<div class="qconnect">' +
      '<div class="qconnect__msg">Nenhum atendente disponível no momento.</div>' +
      '<div class="qconnect__brand">Tente novamente em instantes</div>' +
      '<div class="qfoot" style="width:100%;max-width:320px"><button class="qbtn" onclick="location.reload()">Tentar novamente</button></div>' +
      '</div>';
  }

  // Esc closes
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && quiz.classList.contains('open')) closeQuiz();
  });
})();
