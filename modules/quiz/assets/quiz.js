/* =============================================================================
   Rezponz Profil-Quiz  —  Frontend Logic  v3
   ============================================================================= */
(function () {
  'use strict';

  const LS_KEY = 'rzq_state_v1';

  /* ── State ────────────────────────────────────────────────────────────────── */
  const S = {
    phase:        'intro',   // intro | quiz | analyzing | lead | result
    qIndex:       0,
    answers:      [],
    showFeedback: false,
    submitting:   false,
    result:       null,
    autoTimer:    null,
    direction:    'forward',
    exitBound:    false,
  };

  let profiles  = [];
  let questions = [];
  let root      = null;

  /* ── Bootstrap ────────────────────────────────────────────────────────────── */
  function boot() {
    root = document.getElementById('rzq-root');
    if (!root) return;

    let data;
    try { data = JSON.parse(root.dataset.quiz || '{}'); } catch (e) { data = {}; }

    profiles  = data.profiles  || [];
    questions = data.questions || [];

    if (!profiles.length || !questions.length) {
      root.innerHTML = '<div style="padding:40px;color:#888;text-align:center">Quiz er ikke konfigureret endnu.</div>';
      return;
    }

    const saved = loadProgress();
    if (saved && saved.phase === 'quiz' && saved.qIndex > 0) {
      S.phase   = saved.phase;
      S.qIndex  = saved.qIndex;
      S.answers = saved.answers || [];
    }

    render();
  }

  /* ── Scoring ──────────────────────────────────────────────────────────────── */
  function calcScores() {
    const sc = {};
    profiles.forEach(p => sc[p.slug] = 0);
    S.answers.forEach(a => {
      if (a.weights) {
        Object.entries(a.weights).forEach(([slug, w]) => {
          if (slug in sc) sc[slug] += Number(w);
        });
      }
    });
    return sc;
  }

  function rankProfiles(scores) {
    return [...profiles]
      .map(p => ({ ...p, score: scores[p.slug] || 0 }))
      .sort((a, b) => b.score - a.score);
  }

  /* ── Render dispatcher ────────────────────────────────────────────────────── */
  function render() {
    if (S.autoTimer) { clearTimeout(S.autoTimer); S.autoTimer = null; }
    switch (S.phase) {
      case 'intro':     renderIntro();     break;
      case 'quiz':      renderQuiz();      break;
      case 'analyzing': renderAnalyzing(); break;
      case 'lead':      renderLead();      break;
      case 'result':    renderResult();    break;
    }
    if (S.phase !== 'intro') scrollToQuiz();
  }

  /* ── localStorage ─────────────────────────────────────────────────────────── */
  function saveProgress() {
    try {
      localStorage.setItem(LS_KEY, JSON.stringify({
        phase: S.phase, qIndex: S.qIndex, answers: S.answers, ts: Date.now(),
      }));
    } catch (e) {}
  }

  function loadProgress() {
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return null;
      const saved = JSON.parse(raw);
      if (Date.now() - (saved.ts || 0) > 2 * 60 * 60 * 1000) {
        localStorage.removeItem(LS_KEY); return null;
      }
      return saved;
    } catch (e) { return null; }
  }

  function clearProgress() {
    try { localStorage.removeItem(LS_KEY); } catch (e) {}
  }

  /* ── Scroll + exit guard ──────────────────────────────────────────────────── */
  function scrollToQuiz() {
    if (!root) return;
    const rect = root.getBoundingClientRect();
    if (rect.top < -10 || rect.top > window.innerHeight * 0.3) {
      window.scrollTo({ top: Math.max(0, window.pageYOffset + rect.top - 20), behavior: 'smooth' });
    }
  }

  function setupExitGuard() {
    if (S.exitBound) return;
    S.exitBound = true;
    window.addEventListener('beforeunload', onBeforeUnload);
  }
  function removeExitGuard() {
    if (!S.exitBound) return;
    S.exitBound = false;
    window.removeEventListener('beforeunload', onBeforeUnload);
  }
  function onBeforeUnload(e) {
    if (S.phase === 'quiz' || S.phase === 'lead') { e.preventDefault(); e.returnValue = ''; }
  }

  /* ══════════════════════════════════════════════════════════════════════════
     INTRO  — kort, direkte, Gen Z
  ══════════════════════════════════════════════════════════════════════════ */
  function renderIntro() {
    removeExitGuard();

    const saved      = loadProgress();
    const showResume = saved && saved.phase === 'quiz' && (saved.qIndex || 0) > 0;

    const resumeBanner = showResume ? `
      <div class="rzq-resume-banner" id="rzq-resume">
        <div class="rzq-resume-text">
          ↩ <strong>Fortsæt din quiz</strong> — du var ved spørgsmål ${(saved.qIndex || 0) + 1} af ${questions.length}
        </div>
        <span class="rzq-resume-dismiss" id="rzq-resume-dismiss">✕</span>
      </div>` : '';

    const profileCards = profiles.map(p => `
      <div class="rzq-pcard" style="--pc-color:${p.color}">
        <div class="rzq-pcard-icon" style="background:${p.color}20;border:1.5px solid ${p.color}30">${p.icon_emoji}</div>
        <div class="rzq-pcard-name">${esc(p.title)}</div>
      </div>`).join('');

    root.innerHTML = `<div class="rzq-phase"><div class="rzq-intro-splash">

      <div class="rzq-intro-hero">
        <div class="rzq-blob rzq-blob-1"></div>
        <div class="rzq-blob rzq-blob-2"></div>
        <div class="rzq-blob rzq-blob-3"></div>
        <div class="rzq-hero-icon-wrap">
          <div class="rzq-hero-glow"></div>
          <span class="rzq-hero-emoji">🧠</span>
        </div>
      </div>

      <div class="rzq-intro-body">
        <div class="rzq-intro-badge">${questions.length} spørgsmål · ~3 min</div>
        <h1 class="rzq-intro-title">
          Hvad er du<br>
          <span class="rzq-grad">egentlig for én?</span>
        </h1>
        <p class="rzq-intro-sub">
          Tag quizzen — find din type og se hvad der matcher dig inden for kundeservice.
        </p>
      </div>

      <div class="rzq-intro-profiles-label">De 4 typer</div>
      <div class="rzq-profile-grid">${profileCards}</div>

      ${resumeBanner}

      <div class="rzq-intro-cta">
        <button class="rzq-btn-primary" id="rzq-start-btn">
          ${showResume ? 'Start forfra' : 'Find min type'}
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>

      <div class="rzq-trust-bar" style="margin-top:16px">
        <div class="rzq-trust-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          GDPR-sikret
        </div>
        <div class="rzq-trust-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          100% gratis
        </div>
        <div class="rzq-trust-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          Ingen spam
        </div>
      </div>

      <div class="rzq-social-proof">
        <span class="sp-dot"></span>
        Over 2.800 har allerede taget quizzen
      </div>

    </div></div>`;

    document.getElementById('rzq-start-btn').addEventListener('click', () => {
      clearProgress();
      S.phase = 'quiz'; S.qIndex = 0; S.answers = []; S.direction = 'forward';
      render();
    });

    if (showResume) {
      document.getElementById('rzq-resume').addEventListener('click', e => {
        if (e.target.id === 'rzq-resume-dismiss') { clearProgress(); e.currentTarget.remove(); return; }
        S.phase = saved.phase; S.qIndex = saved.qIndex; S.answers = saved.answers; S.direction = 'forward';
        render();
      });
    }
  }

  /* ══════════════════════════════════════════════════════════════════════════
     QUIZ
  ══════════════════════════════════════════════════════════════════════════ */
  function renderQuiz() {
    setupExitGuard();
    saveProgress();

    const q         = questions[S.qIndex];
    const total     = questions.length;
    const num       = S.qIndex + 1;
    const pct       = Math.round((num / total) * 100);
    const selected  = S.answers[S.qIndex];
    const letters   = ['A', 'B', 'C', 'D', 'E', 'F'];
    const animClass = S.direction === 'back' ? 'rzq-anim-back' : 'rzq-anim-fwd';

    // Live profile hint after 2+ answered
    let profileHintHtml = '';
    const answeredCount = S.answers.filter(Boolean).length;
    if (answeredCount >= 2) {
      const ranked = rankProfiles(calcScores());
      const leader = ranked[0];
      if (leader && leader.score > 0) {
        profileHintHtml = `
          <div class="rzq-profile-hint">
            <span class="rzq-profile-hint-icon">${leader.icon_emoji}</span>
            <span>Peger mod: <strong>${esc(leader.title)}</strong></span>
          </div>`;
      }
    }

    const answersHtml = q.answers.map((a, i) => {
      const isSel = selected && selected.answerId == a.id;
      return `
        <button class="rzq-answer-btn${isSel ? ' selected' : ''}"
                data-aid="${a.id}" data-qid="${q.id}"
                data-fb="${esc(a.feedback_text || '')}"
                data-tag="${esc(a.tagline || '')}"
                data-weights="${esc(JSON.stringify(a.weights || {}))}">
          <span class="rzq-answer-letter">${letters[i] || (i+1)}</span>
          <span class="rzq-answer-text">${esc(a.answer_text)}</span>
        </button>`;
    }).join('');

    const feedbackHtml = (S.showFeedback && selected) ? `
      <div class="rzq-feedback">
        <div class="rzq-feedback-tagline">${esc(selected.tagline)}</div>
        <div class="rzq-feedback-text">${esc(selected.feedbackText)}</div>
      </div>` : '';

    const helperHtml = q.helper_text ? `<div class="rzq-question-helper">${esc(q.helper_text)}</div>` : '';

    root.innerHTML = `<div class="rzq-phase"><div class="rzq-quiz-wrap ${animClass}">

      ${profileHintHtml}

      <div class="rzq-quiz-header">
        <button class="rzq-quiz-back" id="rzq-back" aria-label="Tilbage">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <span class="rzq-quiz-counter">${num} / ${total}</span>
        <span class="rzq-quiz-pct">${pct}%</span>
      </div>

      <div class="rzq-progress-wrap">
        <div class="rzq-progress-bar">
          <div class="rzq-progress-fill" id="rzq-pfill" style="width:0%"></div>
        </div>
      </div>

      <div class="rzq-question-body">
        ${helperHtml}
        <h2 class="rzq-question-text">${esc(q.question_text)}</h2>
      </div>

      <div class="rzq-answers" id="rzq-answers">${answersHtml}</div>

      ${feedbackHtml}

      <div class="rzq-nav">
        <button class="rzq-btn-back" id="rzq-back-text">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Tilbage
        </button>
        <button class="rzq-btn-next" id="rzq-next" ${selected ? '' : 'disabled'}>
          ${S.qIndex < total - 1 ? 'Næste' : 'Se min type'}
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>

    </div></div>`;

    requestAnimationFrame(() => {
      const fill = document.getElementById('rzq-pfill');
      if (fill) setTimeout(() => { fill.style.width = pct + '%'; }, 30);
    });

    document.getElementById('rzq-answers').addEventListener('click', e => {
      const btn = e.target.closest('.rzq-answer-btn');
      if (!btn) return;
      S.answers[S.qIndex] = {
        questionId: btn.dataset.qid, answerId: btn.dataset.aid,
        weights: JSON.parse(btn.dataset.weights || '{}'),
        feedbackText: btn.dataset.fb, tagline: btn.dataset.tag,
      };
      S.showFeedback = true;
      S.direction    = 'forward';
      renderQuiz();
    });

    document.getElementById('rzq-next').addEventListener('click', () => {
      if (!S.answers[S.qIndex]) return;
      S.showFeedback = false;
      S.direction    = 'forward';
      if (S.qIndex < questions.length - 1) {
        S.qIndex++;
        render();
      } else {
        S.phase = 'analyzing';
        render();
      }
    });

    document.getElementById('rzq-back').addEventListener('click', goBack);
    document.getElementById('rzq-back-text').addEventListener('click', goBack);

    function goBack() {
      S.showFeedback = false; S.direction = 'back';
      if (S.qIndex > 0) { S.qIndex--; render(); }
      else { S.phase = 'intro'; render(); }
    }
  }

  /* ══════════════════════════════════════════════════════════════════════════
     ANALYZING  — "wow moment" overgang. Bygger forventning, føles som en app
  ══════════════════════════════════════════════════════════════════════════ */
  function renderAnalyzing() {
    const ranked = rankProfiles(calcScores());
    const leader = ranked[0];

    const steps = [
      { pct: 20, txt: 'Analyserer dine svar…' },
      { pct: 45, txt: 'Matcher din personlighed…' },
      { pct: 70, txt: 'Finder din type…' },
      { pct: 90, txt: `${leader.icon_emoji} Det begynder at ligne noget…` },
      { pct: 100, txt: 'Din profil er klar! 🎉' },
    ];

    root.innerHTML = `<div class="rzq-phase rzq-anim-fwd">
      <div class="rzq-analyzing-wrap">
        <div class="rzq-analyzing-ring">
          <div class="rzq-analyzing-emoji" id="rzq-ana-emoji">🔍</div>
        </div>
        <div class="rzq-analyzing-label" id="rzq-ana-label">Analyserer dine svar…</div>
        <div class="rzq-analyzing-bar-bg">
          <div class="rzq-analyzing-bar-fill" id="rzq-ana-fill"></div>
        </div>
        <div class="rzq-analyzing-pct" id="rzq-ana-pct">0%</div>
      </div>
    </div>`;

    let i = 0;
    function runStep() {
      if (i >= steps.length) {
        setTimeout(() => { S.phase = 'lead'; render(); }, 400);
        return;
      }
      const step = steps[i++];
      const fill  = document.getElementById('rzq-ana-fill');
      const label = document.getElementById('rzq-ana-label');
      const pctEl = document.getElementById('rzq-ana-pct');
      const emoji = document.getElementById('rzq-ana-emoji');
      if (!fill) return;

      fill.style.width  = step.pct + '%';
      if (label) label.textContent = step.txt;
      if (pctEl) pctEl.textContent = step.pct + '%';
      if (emoji && step.pct >= 90) emoji.textContent = leader.icon_emoji;

      setTimeout(runStep, i === steps.length ? 600 : 420);
    }

    setTimeout(runStep, 200);
  }

  /* ══════════════════════════════════════════════════════════════════════════
     LEAD FORM  — Gen Z-venlig: email primær, tlf. valgfri, ingen salgssprog
  ══════════════════════════════════════════════════════════════════════════ */
  function renderLead() {
    setupExitGuard();
    saveProgress();

    const ranked = rankProfiles(calcScores());
    const peek   = ranked[0];

    root.innerHTML = `<div class="rzq-phase rzq-anim-fwd">

      <div class="rzq-profile-peek">
        <div class="rzq-peek-label">✦ Din type er fundet</div>
        <div class="rzq-peek-lock" style="filter:drop-shadow(0 0 20px ${esc(peek.color)}66)">${esc(peek.icon_emoji)}</div>
        <div class="rzq-peek-title" style="color:${esc(peek.color)}">${esc(peek.title)}</div>
        <p class="rzq-peek-sub">Skriv din email for at se din fulde profil — helt gratis</p>
      </div>

      <div class="rzq-form">

        <div class="rzq-value-list">
          <div class="rzq-value-item">
            <div class="rzq-value-icon vi-1">🎯</div>
            <span>Din fulde profil med styrker og hvad du er god til</span>
          </div>
          <div class="rzq-value-item">
            <div class="rzq-value-icon vi-2">💼</div>
            <span>Jobs der faktisk matcher din type</span>
          </div>
          <div class="rzq-value-item">
            <div class="rzq-value-icon vi-3">✉️</div>
            <span>Vi sender din profil til din email — ingen spam</span>
          </div>
        </div>

        <div class="rzq-form-group">
          <label class="rzq-form-label" for="rzq-name">Dit navn</label>
          <input class="rzq-form-input" type="text" id="rzq-name"
                 placeholder="Hvad hedder du?" autocomplete="name">
          <div class="rzq-field-msg" id="rzq-name-msg"></div>
        </div>

        <div class="rzq-form-group">
          <label class="rzq-form-label" for="rzq-email">Din email</label>
          <input class="rzq-form-input" type="email" id="rzq-email"
                 placeholder="din@email.dk" autocomplete="email">
          <div class="rzq-field-msg" id="rzq-email-msg"></div>
        </div>

        <div class="rzq-form-group">
          <label class="rzq-form-label" for="rzq-phone">Telefonnummer <span class="rzq-required-star">*</span></label>
          <input class="rzq-form-input" type="tel" id="rzq-phone"
                 placeholder="Dit nummer" autocomplete="tel">
          <div class="rzq-field-msg" id="rzq-phone-msg"></div>
        </div>

        <div class="rzq-honeypot">
          <input type="text" name="website" id="rzq-hp-web" tabindex="-1" autocomplete="off">
          <input type="text" name="company" id="rzq-hp-co"  tabindex="-1" autocomplete="off">
        </div>

        <label class="rzq-consent-wrap">
          <input type="checkbox" id="rzq-consent">
          <span class="rzq-consent-label">
            Jeg accepterer Rezponz's behandling af mine personoplysninger i overensstemmelse med GDPR og privatlivspolitikken. <span class="rzq-required-star">*</span>
          </span>
        </label>

        <label class="rzq-consent-wrap" style="margin-top:10px">
          <input type="checkbox" id="rzq-contact-consent">
          <span class="rzq-consent-label">
            Jeg giver Rezponz tilladelse til at kontakte mig om relevante jobmuligheder og karrieretilbud. <span class="rzq-required-star">*</span>
          </span>
        </label>

        <div class="rzq-form-error" id="rzq-form-err"></div>

        <button class="rzq-btn-primary" id="rzq-submit-btn">
          <span id="rzq-submit-label">Vis mig min profil →</span>
        </button>

        <div style="text-align:center;margin-top:10px;font-size:11px;color:rgba(240,244,255,.35)">
          🔒 Ingen spam. Ingen salgskald. Kun din profil.
        </div>
      </div>
    </div>`;

    setupLiveValidation();
    document.getElementById('rzq-submit-btn').addEventListener('click', handleSubmit);
  }

  function setupLiveValidation() {
    const rules = [
      {
        id: 'rzq-name', msgId: 'rzq-name-msg',
        validate: v => v.trim().length >= 2,
        errMsg: 'Skriv dit navn', okMsg: '✓',
      },
      {
        id: 'rzq-email', msgId: 'rzq-email-msg',
        validate: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()),
        errMsg: 'Tjek din email', okMsg: '✓',
      },
      {
        id: 'rzq-phone', msgId: 'rzq-phone-msg',
        validate: v => /^[\d\s\+\-\(\)]{7,}$/.test(v.trim()),
        errMsg: 'Skriv dit telefonnummer', okMsg: '✓',
      },
    ];
    rules.forEach(rule => {
      const input = document.getElementById(rule.id);
      const msg   = document.getElementById(rule.msgId);
      if (!input || !msg) return;
      input.addEventListener('blur', () => {
        if (!input.value.trim()) return;
        const ok = rule.validate(input.value);
        input.classList.toggle('input-ok',    ok);
        input.classList.toggle('input-error', !ok);
        msg.className   = `rzq-field-msg ${ok ? 'ok' : 'err'}`;
        msg.textContent = ok ? rule.okMsg : rule.errMsg;
      });
      input.addEventListener('focus', () => input.classList.remove('input-error'));
    });
  }

  async function handleSubmit() {
    const name           = document.getElementById('rzq-name').value.trim();
    const email          = document.getElementById('rzq-email').value.trim();
    const phone          = document.getElementById('rzq-phone').value.trim();
    const consent        = document.getElementById('rzq-consent').checked;
    const contactConsent = document.getElementById('rzq-contact-consent').checked;
    const errEl          = document.getElementById('rzq-form-err');
    const btn            = document.getElementById('rzq-submit-btn');
    const label          = document.getElementById('rzq-submit-label');

    errEl.style.display = 'none';
    if (!name)                                                  return showErr(errEl, 'Skriv dit navn.');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))   return showErr(errEl, 'Skriv en gyldig email.');
    if (!phone || !/^[\d\s\+\-\(\)]{7,}$/.test(phone))         return showErr(errEl, 'Skriv dit telefonnummer.');
    if (!consent)                                               return showErr(errEl, 'Du skal acceptere GDPR-vilkårene.');
    if (!contactConsent)                                        return showErr(errEl, 'Du skal give tilladelse til at vi må kontakte dig.');

    if (S.submitting) return;
    S.submitting = true;
    btn.disabled = true;
    label.textContent = 'Henter din profil…';

    try {
      const res  = await fetch(root.dataset.submitUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': root.dataset.nonce || '' },
        body: JSON.stringify({
          name, email, phone, consent, contact_consent: contactConsent,
          website: document.getElementById('rzq-hp-web').value,
          company: document.getElementById('rzq-hp-co').value,
          answers: S.answers.map(a => ({ questionId: a.questionId, answerId: a.answerId })),
        }),
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Ukendt fejl');
      S.result = json;
      S.phase  = 'result';
      clearProgress();
      removeExitGuard();
      render();
      launchConfetti();
    } catch (err) {
      showErr(errEl, err.message || 'Noget gik galt. Prøv igen.');
      btn.disabled = false;
      label.textContent = 'Vis mig min profil →';
    } finally {
      S.submitting = false;
    }
  }

  /* ══════════════════════════════════════════════════════════════════════════
     RESULT
  ══════════════════════════════════════════════════════════════════════════ */
  function renderResult() {
    removeExitGuard();
    clearProgress();

    const { winningProfile: w, secondaryProfile: sec, allProfiles } = S.result;
    const scores   = allProfiles.map(p => p.score);
    const maxScore = Math.max(...scores, 1);

    const scoreBars = allProfiles.map(p => {
      const pct      = Math.round((p.score / maxScore) * 100);
      const isWinner = p.slug === w.slug;
      return `
        <div class="rzq-score-row${isWinner ? ' rzq-score-winner' : ''}">
          <div class="rzq-score-label">
            <span class="rzq-score-emoji">${p.icon_emoji}</span>
            <span>${esc(p.title)}</span>
          </div>
          <div class="rzq-score-bar-bg">
            <div class="rzq-score-bar-fill" data-pct="${pct}" style="background:${esc(p.color)}"></div>
          </div>
          <div class="rzq-score-pct${isWinner ? ' rzq-score-pct-hi' : ''}">${pct}%</div>
        </div>`;
    }).join('');

    const traits = [
      { icon: '💪', label: 'Dine styrker – og hvorfor de passer til Rezponz', key: 'strengths',     accentColor: w.color },
      { icon: '🚀', label: 'Du trives med – det har vi hos Rezponz',          key: 'thrives_with',  accentColor: '#6366f1' },
      { icon: '📈', label: 'Det kan udfordre dig – men det hjælper vi dig med', key: 'develop_areas', accentColor: '#10b981' },
    ].map(tc => {
      const items = (w[tc.key] || []).map(item =>
        `<li class="rzq-trait-item">
           <span class="rzq-trait-check" style="color:${esc(tc.accentColor)}">✓</span>
           ${esc(item)}
         </li>`
      ).join('');
      return `
        <div class="rzq-trait-card" style="--tc-accent:${esc(tc.accentColor)}">
          <div class="rzq-trait-header">
            <span class="rzq-trait-icon">${tc.icon}</span>
            <span>${tc.label}</span>
          </div>
          <ul class="rzq-trait-list">${items}</ul>
        </div>`;
    }).join('');

    root.innerHTML = `<div class="rzq-phase rzq-anim-fwd"><div class="rzq-result-wrap">

      <div class="rzq-result-hero" style="--pc:${esc(w.color)}">
        <div class="rzq-result-badge">✦ Din type</div>
        <div class="rzq-result-icon-wrap">
          <div class="rzq-result-ring"></div>
          <span class="rzq-result-icon">${esc(w.icon_emoji)}</span>
        </div>
        <h2 class="rzq-result-title" style="color:${esc(w.color)}">${esc(w.title)}</h2>
        <p class="rzq-result-desc">${esc(w.description)}</p>
      </div>

      <div class="rzq-scroll-hint" id="rzq-scroll-hint">↓ Scroll og se din fulde profil</div>

      <div class="rzq-result-content">

        <div class="rzq-score-section">
          <div class="rzq-section-label">Din fordeling</div>
          <div class="rzq-score-rows">${scoreBars}</div>
        </div>

        <div class="rzq-traits">${traits}</div>

        <div class="rzq-secondary">
          <div class="rzq-secondary-icon">${esc(sec.icon_emoji)}</div>
          <div class="rzq-secondary-body">
            <div class="rzq-secondary-label">Du har også træk fra</div>
            <div class="rzq-secondary-name">${esc(sec.title)}</div>
            <div class="rzq-secondary-desc">${esc(sec.description.slice(0, 88))}…</div>
          </div>
        </div>

        <div style="text-align:center;padding:4px 0 2px">
          <button class="rzq-share-btn" id="rzq-share-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Del din type
          </button>
        </div>

        <div class="rzq-result-cta" style="--pc:${esc(w.color)}" id="rzq-main-cta">
          <div class="rzq-cta-emoji">${esc(w.icon_emoji)}</div>
          <div class="rzq-cta-eyebrow">Næste skridt</div>
          <h3 class="rzq-cta-title">
            Din profil passer perfekt<br>
            <em class="rzq-cta-profile">til et job hos Rezponz</em>
          </h3>
          <p class="rzq-cta-sub">
            Se vores åbne stillinger og søg det job der matcher din ${esc(w.title)}-type
          </p>
          <div class="rzq-result-urgency">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Vi ser frem til at høre fra dig
          </div>
          <a href="https://rezponz.dk/karriere-stillinger/" class="rzq-cta-btn" id="rzq-cta-anchor">
            Søg dit nye job hos Rezponz her
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
          <div class="rzq-cta-note">Gratis · Uforpligtende · Find dit match</div>
        </div>

      </div>
      <div class="rzq-result-pad"></div>

    </div></div>

    <div class="rzq-sticky-cta" id="rzq-sticky-cta">
      <a href="https://rezponz.dk/karriere-stillinger/" class="rzq-sticky-cta-inner">
        ${esc(w.icon_emoji)} Søg dit nye job hos Rezponz her
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>`;

    requestAnimationFrame(() => {
      setTimeout(() => {
        document.querySelectorAll('.rzq-score-bar-fill').forEach(el => {
          el.style.width = el.dataset.pct + '%';
        });
      }, 120);
    });

    const scrollHint = document.getElementById('rzq-scroll-hint');
    if (scrollHint) {
      window.addEventListener('scroll', function h() {
        scrollHint.style.opacity = '0';
        setTimeout(() => scrollHint.remove(), 400);
        window.removeEventListener('scroll', h);
      }, { passive: true });
    }

    const stickyCta = document.getElementById('rzq-sticky-cta');
    const mainCta   = document.getElementById('rzq-cta-anchor');
    if (stickyCta && mainCta && window.IntersectionObserver) {
      new IntersectionObserver(([e]) => {
        stickyCta.classList.toggle('visible', !e.isIntersecting);
      }, { threshold: 0.1 }).observe(mainCta);
    }

    const shareBtn = document.getElementById('rzq-share-btn');
    if (shareBtn) shareBtn.addEventListener('click', () => shareResult(w.title, w.icon_emoji));
  }

  /* ── Share ────────────────────────────────────────────────────────────────── */
  async function shareResult(title, emoji) {
    const text = `Jeg er en ${emoji} ${title}! Find din type på Rezponz.dk 👇`;
    const url  = window.location.href;
    const btn  = document.getElementById('rzq-share-btn');
    try {
      if (navigator.share) {
        await navigator.share({ title: 'Min Rezponz-type', text, url });
      } else {
        await navigator.clipboard.writeText(`${text}\n${url}`);
        if (btn) {
          btn.classList.add('rzq-share-copied');
          btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Kopieret!`;
          setTimeout(() => {
            btn.classList.remove('rzq-share-copied');
            btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg> Del din type`;
          }, 2500);
        }
      }
    } catch (e) {}
  }

  /* ── Confetti ─────────────────────────────────────────────────────────────── */
  function launchConfetti() {
    const colors = ['#ff6b35','#d63384','#ffda29','#5ee7d0','#a78bfa','#34d399'];
    const wrap   = document.createElement('div');
    wrap.className = 'rzq-confetti';
    document.body.appendChild(wrap);
    for (let i = 0; i < 80; i++) {
      const p = document.createElement('div');
      p.className = 'rzq-confetti-piece';
      p.style.cssText = `left:${Math.random()*100}%;background:${colors[Math.floor(Math.random()*colors.length)]};--dur:${1.8+Math.random()*1.5}s;--delay:${Math.random()*.8}s;width:${5+Math.random()*7}px;height:${5+Math.random()*7}px;border-radius:${Math.random()>.5?'50%':'2px'};`;
      wrap.appendChild(p);
    }
    setTimeout(() => wrap.remove(), 4000);
  }

  /* ── Helpers ──────────────────────────────────────────────────────────────── */
  function esc(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function showErr(el, msg) { el.textContent = msg; el.style.display = 'block'; }

  /* ── Init ─────────────────────────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

})();
