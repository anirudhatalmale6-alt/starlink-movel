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
      q: 'Onde você está?', label: 'Cidade e Estado', placeholder: 'Ex: Brasília DF' },
    { type: 'options', key: 'motivo_uso', title: 'Cenário de uso', desc: 'Isso nos ajuda a entender como a Starlink Móvel vai te atender.',
      q: 'Em qual cenário você pretende usar a Starlink Móvel?',
      options: [
        { v: 'Área rural', ic: 'sprout' },
        { v: 'Deslocamento / estrada', ic: 'route' },
        { v: 'Atividade profissional externa', ic: 'briefcase' },
        { v: 'Uso pessoal', ic: 'user' },
        { v: 'Outro contexto', ic: 'chat' }
      ] },
    { type: 'options', key: 'dispositivo', title: 'Dispositivo', desc: 'A Starlink Móvel é configurada diretamente no seu celular.',
      q: 'Qual é o sistema do seu dispositivo?',
      options: [ { v: 'Android', ic: 'phone' }, { v: 'iPhone (iOS)', ic: 'apple' } ] },
    { type: 'options', key: 'internet_atual', title: 'Situação atual', desc: 'Entender sua conexão atual nos ajuda a dimensionar a solução.',
      q: 'Qual é a sua situação atual de internet?',
      options: [
        { v: 'Sem internet ou sinal péssimo', ic: 'wifioff' },
        { v: 'Internet lenta e instável', ic: 'activity' },
        { v: 'Pago caro por um serviço ruim', ic: 'dollar' },
        { v: 'Tenho internet ok, quero upgrade', ic: 'trendup' }
      ] },
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
      if (s.label) {
        html += '<div class="qfield"><span class="qfield__lbl">' + s.label + '</span>' +
                '<input class="qinput" id="qf" type="text" placeholder="' + s.placeholder + '" value="' + (answers[s.key] || '') + '" autocomplete="off" /></div>';
      } else {
        html += '<input class="qinput" id="qf" type="text" placeholder="' + s.placeholder + '" value="' + (answers[s.key] || '') + '" autocomplete="off" />';
      }
      html += foot('Continuar');
    } else if (s.type === 'phone') {
      html += '<div class="qstep__q">' + s.q + '</div>';
      html += '<div class="qphone"><span class="qphone__cc">BR +55</span><input class="qinput" id="qf" type="tel" inputmode="numeric" placeholder="' + s.placeholder + '" value="' + (answers[s.key] || '') + '" autocomplete="off" /></div>';
      html += foot('Continuar');
    } else if (s.type === 'options') {
      html += '<div class="qstep__q">' + s.q + '</div><div class="qoptions">';
      s.options.forEach(function (o) {
        var val = o.v, ic = ICONS[o.ic] || '';
        var sel = answers[s.key] === val ? ' sel' : '';
        html += '<button class="qopt' + sel + '" data-val="' + val + '"><span class="qopt__ic">' + ic + '</span>' +
                '<span class="qopt__lbl">' + val + '</span>' + CHEVSM + '</button>';
      });
      html += '</div>';
    } else if (s.type === 'cta') {
      html += '<div class="qstep__q">' + s.q + '</div>';
      html += '<div class="qoptions"><button class="qopt qopt--cta" data-val="Imediata"><span class="qopt__ic qopt__ic--zap">' + ICONS.zap + '</span>' +
              '<span class="qopt__lbl"><b>' + s.cta + '</b><em>' + s.ctaNote + '</em></span>' + CHEVSM + '</button></div>';
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
      html += '<div class="qfoot"><button class="qbtn" id="qsend">Confirmar e Enviar' + CHEV + '</button></div>';
    }
    html += '</div>';
    body.innerHTML = html;
    wire();
  }

  function row(k, v) {
    return '<div class="qreview__row"><span class="qreview__k">' + k + '</span><span class="qreview__v">' + (v || '—') + '</span></div>';
  }
  var CHEV = '<svg class="qbtn__chev" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>';
  var CHEVSM = '<svg class="qopt__arw" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>';

  // ícones (traço, estilo lucide) usados nas opções do quiz
  function svg(paths) { return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>'; }
  var ICONS = {
    sprout:   svg('<path d="M7 20h10"/><path d="M12 20c0-6 0-8 0-10"/><path d="M12 10C12 6 9 4 5 4c0 4 3 6 7 6z"/><path d="M12 12c0-3 2-5 6-5 0 3-2 5-6 5z"/>'),
    route:    svg('<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h6a4 4 0 0 0 0-8H10a4 4 0 0 1 0-8h6"/>'),
    briefcase:svg('<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>'),
    user:     svg('<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'),
    chat:     svg('<path d="M21 12a8 8 0 0 1-11.5 7.2L4 21l1.8-5.5A8 8 0 1 1 21 12z"/>'),
    phone:    svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>'),
    apple:    svg('<path d="M16 4c-1 .3-2 1-2.5 2"/><path d="M12 8c-1.5 0-3-1-4.5-.5C5 8 4 10.5 4 13c0 3.5 2 8 4.5 8 1 0 1.5-.6 2.5-.6s1.5.6 2.5.6C18 21 20 16 20 13c0-2.5-1-4.5-3-5.5C15.5 7 13.5 8 12 8z"/>'),
    wifioff:  svg('<path d="M2 8.8a16 16 0 0 1 6-3.4"/><path d="M22 8.8a16 16 0 0 0-6.5-3.5"/><path d="M8.5 12.5a9 9 0 0 1 3-1.4"/><path d="M15.5 12.5a9 9 0 0 0-1.5-1"/><path d="M12 20h.01"/><path d="M2 2l20 20"/>'),
    activity: svg('<path d="M3 12h4l3 8 4-16 3 8h4"/>'),
    dollar:   svg('<path d="M12 2v20"/><path d="M17 6.5C17 4.5 14.8 3.5 12 3.5S7 4.7 7 7s2.2 3 5 3.5 5 1.3 5 3.5-2.2 3.5-5 3.5-5-1-5-3"/>'),
    trendup:  svg('<path d="M3 17l6-6 4 4 8-8"/><path d="M17 7h4v4"/>'),
    zap:      svg('<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>')
  };
  function foot(label) {
    return '<div class="qfoot"><button class="qbtn" id="qnext">' + label + CHEV + '</button></div>';
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

  // atendente de demonstração (usado quando o backend não está disponível, ex.: GitHub Pages)
  function demoAttendant() {
    var ini = (answers.nome || '').trim().charAt(0).toUpperCase() || 'S';
    var av = 'data:image/svg+xml;base64,' + btoa(
      '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="#1c1c1f"/>' +
      '<text x="80" y="80" dy="0.35em" text-anchor="middle" font-family="Inter,Arial" font-size="60" font-weight="600" fill="#e8e8ec">F</text></svg>');
    return { attendant: 'Felipe', role: 'Especialista em Ativação', photo: av, whatsapp_url: demoWhatsappUrl() };
  }

  function submitLead() {
    // tela "conectando" com animação de radar (pulsos concêntricos)
    body.innerHTML =
      '<div class="qconnect">' +
      '<div class="qradar"><span></span><span></span><span></span><i></i></div>' +
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

    // metadados do dispositivo/conexão (melhor esforço — depende do navegador)
    var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    var payload = {};
    for (var k in answers) if (answers.hasOwnProperty(k)) payload[k] = answers[k];
    payload._meta = {
      conn_effective: conn && conn.effectiveType ? conn.effectiveType : '',
      conn_type: conn && conn.type ? conn.type : '',
      screen: (window.screen ? window.screen.width + 'x' + window.screen.height : ''),
      lang: navigator.language || '',
      platform: navigator.platform || ''
    };

    function finish(data) {
      setTimeout(function () {
        clearInterval(timer);
        if (data && data.ok && data.whatsapp_url) showAttendant(data);
        else showAttendant(demoAttendant()); // sem atendente cadastrado → demo
      }, 4400);
    }

    fetch('api/lead.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json(); })
      .then(finish)
      .catch(function () { finish(null); }); // backend indisponível (demo) → atendente de demonstração
  }

  // card do atendente designado (aparece depois do radar, antes de ir pro WhatsApp)
  function showAttendant(data) {
    var name = data.attendant || 'Atendente';
    var role = data.role || 'Especialista em Ativação';
    var photo = data.photo || '';
    var url = data.whatsapp_url || demoWhatsappUrl();
    body.innerHTML =
      '<div class="qattend">' +
      '<div class="qattend__label">Atendente Designado</div>' +
      '<div class="qattend__avatar"><img src="' + photo + '" alt="' + name + '" /><span class="qattend__badge"></span></div>' +
      '<div class="qattend__name">' + name + '</div>' +
      '<div class="qattend__role">' + role + '</div>' +
      '<div class="qattend__status"><span class="qattend__dot"></span> Disponível para continuar seu atendimento</div>' +
      '<a class="qbtn qattend__btn" href="' + url + '">' +
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.2-1.2l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20zm4.5-5.9c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.5-.6.2-.4v-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3c-.3.3-.9.9-.9 2.1s.9 2.5 1.1 2.6c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.2.7 3 .6.5 0 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1z"/></svg>' +
        ' Continuar no WhatsApp</a>' +
      '</div>';
  }

  // Esc closes
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && quiz.classList.contains('open')) closeQuiz();
  });
})();
