/* =============================================================================
   Rezponz Live Quiz — Player Screen
   ============================================================================= */
(function () {
  'use strict';

  const COLORS  = ['#e85a10', '#3b82f6', '#eab308', '#22c55e'];
  const SHAPES  = ['▲', '◆', '●', '■'];

  let root, cfg;
  let token = '', hash = '', phase = 'join';
  let poll_iv = null, timer_iv = null;
  let state = null;

  // ── Boot ───────────────────────────────────────────────────────────────────

  function boot() {
    root = document.getElementById('rzlq-player-root');
    if (!root) return;

    cfg = {
      api:   root.dataset.api,
      nonce: root.dataset.nonce,
    };

    // Restore session from localStorage
    const saved = localStorage.getItem('rzlq_token');
    if (saved) {
      token = saved;
      start_polling();
    } else {
      render_join();
    }
  }

  // ── Polling ────────────────────────────────────────────────────────────────

  function start_polling() {
    if (poll_iv) clearInterval(poll_iv);
    poll();
    poll_iv = setInterval(poll, 1500);
  }

  function stop_polling() {
    clearInterval(poll_iv);
    poll_iv = null;
  }

  async function poll() {
    if (!token) return;
    try {
      const res  = await fetch(`${cfg.api}/state?token=${encodeURIComponent(token)}&hash=${hash}`, {
        headers: { 'X-WP-Nonce': cfg.nonce, 'Cache-Control': 'no-cache' }
      });
      const data = await res.json();

      if (!data.success) {
        // Token invalid — back to join
        localStorage.removeItem('rzlq_token');
        token = '';
        stop_polling();
        render_join('Spillet blev ikke fundet. Prøv igen.');
        return;
      }

      if (data.changed === false) return;

      hash  = data.hash;
      state = data;
      dispatch_state();
    } catch (_) {}
  }

  // ── State dispatch ─────────────────────────────────────────────────────────

  function dispatch_state() {
    if (!state) return;
    clearInterval(timer_iv);

    switch (state.status) {
      case 'waiting':          render_waiting();    break;
      case 'question_active':  render_question();   break;
      case 'question_results': render_result();     break;
      case 'leaderboard':      render_leaderboard(false); break;
      case 'podium':           render_leaderboard(true);  break;
      case 'finished':         render_finished();   break;
    }
  }

  // ── JOIN ───────────────────────────────────────────────────────────────────

  function render_join(err_msg) {
    set_phase(`
      <div class="rp-logo">Rezponz</div>
      <div class="rp-card">
        <div class="rp-card-title">Deltag i quiz</div>
        <div class="rp-card-sub">Indtast PIN-koden fra skærmen</div>
        <div class="rp-input-group">
          <label class="rp-label">PIN-kode</label>
          <input id="rp-pin" class="rp-input" type="tel" inputmode="numeric"
                 maxlength="6" placeholder="000000" autocomplete="off">
        </div>
        <button class="rp-btn" id="rp-join-btn" onclick="rzlqJoin()">Næste</button>
        <div class="rp-error" id="rp-err">${esc(err_msg || '')}</div>
      </div>`);

    if (err_msg) show_err('rp-err', err_msg);

    const pin = document.getElementById('rp-pin');
    if (pin) {
      pin.focus();
      pin.addEventListener('input', () => {
        if (pin.value.length === 6) rzlqJoin();
      });
    }
  }

  window.rzlqJoin = async function () {
    const pin = (document.getElementById('rp-pin')?.value || '').trim();
    if (pin.length < 4) return;

    const btn = document.getElementById('rp-join-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Tjekker…'; }

    try {
      const res  = await fetch(`${cfg.api}/join`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
        body: JSON.stringify({ pin }),
      });
      const data = await res.json();

      if (!data.success) {
        if (btn) { btn.disabled = false; btn.textContent = 'Næste'; }
        show_err('rp-err', data.message || 'Ugyldig PIN-kode');
        return;
      }

      // PIN valid — show nickname screen with game_id
      render_nickname(data.game_id);
    } catch (_) {
      if (btn) { btn.disabled = false; btn.textContent = 'Næste'; }
      show_err('rp-err', 'Netværksfejl – prøv igen');
    }
  };

  // ── NICKNAME ───────────────────────────────────────────────────────────────

  let _game_id = null;

  function render_nickname(game_id) {
    _game_id = game_id;
    set_phase(`
      <div class="rp-logo">Rezponz</div>
      <div class="rp-card">
        <div class="rp-card-title">Vælg dit navn</div>
        <div class="rp-card-sub">Sådan ser de andre dig på skærmen</div>
        <div class="rp-input-group">
          <label class="rp-label">Kaldenavn</label>
          <input id="rp-nick" class="rp-input nickname" type="text"
                 maxlength="20" placeholder="Dit navn…" autocomplete="off">
        </div>
        <button class="rp-btn" id="rp-nick-btn" onclick="rzlqSetNick()">Deltag</button>
        <div class="rp-error" id="rp-err"></div>
      </div>`);

    const nick = document.getElementById('rp-nick');
    if (nick) {
      nick.focus();
      nick.addEventListener('keydown', e => { if (e.key === 'Enter') rzlqSetNick(); });
    }
  }

  window.rzlqSetNick = async function () {
    const nick = (document.getElementById('rp-nick')?.value || '').trim();
    if (!nick) { show_err('rp-err', 'Skriv et kaldenavn'); return; }

    const btn = document.getElementById('rp-nick-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Tilmelder…'; }

    try {
      const res  = await fetch(`${cfg.api}/join`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
        body: JSON.stringify({ game_id: _game_id, nickname: nick }),
      });
      const data = await res.json();

      if (!data.success) {
        if (btn) { btn.disabled = false; btn.textContent = 'Deltag'; }
        show_err('rp-err', data.message || 'Kaldenavn optaget');
        return;
      }

      token = data.token;
      localStorage.setItem('rzlq_token', token);
      start_polling();
    } catch (_) {
      if (btn) { btn.disabled = false; btn.textContent = 'Deltag'; }
      show_err('rp-err', 'Netværksfejl – prøv igen');
    }
  };

  // ── WAITING ROOM ───────────────────────────────────────────────────────────

  function render_waiting() {
    const nickname = state.nickname || '';
    set_phase(`
      <div class="rp-waiting">
        <div class="rp-nickname-badge">${esc(nickname)}</div>
        <div class="rp-waiting-title">Du er tilmeldt! 🎉</div>
        <div class="rp-waiting-sub">Vent på at værten starter quizzen</div>
        <div class="rp-dots">
          <span></span><span></span><span></span>
        </div>
        <div class="rp-waiting-sub" style="font-size:13px;opacity:.6">
          ${state.player_count || 1} deltager${(state.player_count || 1) !== 1 ? 'e' : ''} tilmeldt
        </div>
      </div>`);
  }

  // ── QUESTION ───────────────────────────────────────────────────────────────

  let _answered = false;

  function render_question() {
    const q         = state.question || {};
    const idx       = state.question_index ?? 0;
    const total     = state.question_total ?? 1;
    const type      = q.type || 'multiple_choice';
    const progress  = ((idx + 1) / total) * 100;

    _answered = state.has_answered || false;

    const imgHtml = q.image
      ? `<img class="rp-q-image" src="${esc(q.image)}" alt="">`
      : '';

    let answersHtml = '';
    if (_answered) {
      answersHtml = `
        <div class="rp-answered-state">
          <div class="rp-answered-icon">✅</div>
          <div class="rp-answered-text">Svar registreret – vent på resultater</div>
        </div>`;
    } else if (type === 'slider') {
      const min = q.min ?? 1;
      const max = q.max ?? 10;
      const mid = Math.round((min + max) / 2);
      answersHtml = `
        <div class="rp-slider-wrap">
          <div class="rp-slider-val" id="rp-slval">${mid}</div>
          <input class="rp-slider" type="range" id="rp-slider"
                 min="${min}" max="${max}" value="${mid}"
                 oninput="document.getElementById('rp-slval').textContent=this.value">
          <div class="rp-slider-labels">
            <span>${min}</span><span>${max}</span>
          </div>
        </div>
        <button class="rp-btn" onclick="rzlqAnswer('slider', document.getElementById('rp-slider').value)">
          Bekræft svar
        </button>`;
    } else {
      const opts = q.options || [];
      answersHtml = `<div class="rp-answers">
        ${opts.map((o, i) => `
          <button class="rp-answer" style="background:${COLORS[i] || '#555'}"
                  onclick="rzlqAnswer('choice', ${i})">
            <span class="rp-answer-shape">${SHAPES[i] || ''}</span>
            <span class="rp-answer-text">${esc(o.text)}</span>
          </button>`).join('')}
      </div>`;
    }

    set_phase(`
      <div class="rp-q-wrap">
        <div class="rp-progress">
          <div class="rp-progress-fill" style="width:${progress}%"></div>
        </div>
        <div class="rp-timer">
          <div class="rp-timer-circle">
            <svg class="rp-timer-svg" width="56" height="56" viewBox="0 0 56 56">
              <circle class="rp-timer-track" cx="28" cy="28" r="24"/>
              <circle class="rp-timer-arc" id="rp-arc" cx="28" cy="28" r="24"
                      stroke-dasharray="150.8" stroke-dashoffset="0"/>
            </svg>
            <div class="rp-timer-text" id="rp-tnum">—</div>
          </div>
        </div>
        <div class="rp-q-text-area">
          <div class="rp-q-counter">Spørgsmål ${idx + 1} / ${total}</div>
          ${imgHtml}
          <div class="rp-q-text">${esc(q.text || '')}</div>
        </div>
        ${answersHtml}
      </div>`);

    if (!_answered) {
      start_timer(state.time_remaining_ms, state.time_limit_ms);
    }
  }

  function start_timer(remaining_ms, limit_ms) {
    const numEl = document.getElementById('rp-tnum');
    const arc   = document.getElementById('rp-arc');
    const circ  = 150.8; // 2π × 24
    let t = remaining_ms;

    function tick() {
      if (!numEl) return;
      const secs = Math.max(0, Math.ceil(t / 1000));
      numEl.textContent = secs;
      numEl.style.color = secs <= 5 ? '#ef4444' : secs <= 10 ? '#f97316' : '#fff';
      if (arc) {
        const pct = Math.max(0, t / limit_ms);
        arc.style.strokeDashoffset = circ * (1 - pct);
        arc.style.stroke = secs <= 5 ? '#ef4444' : secs <= 10 ? '#f97316' : '#738991';
      }
      t -= 100;
      if (t <= 0) clearInterval(timer_iv);
    }
    tick();
    timer_iv = setInterval(tick, 100);
  }

  window.rzlqAnswer = async function (type, value) {
    if (_answered) return;
    _answered = true;

    // Disable all answer buttons immediately
    document.querySelectorAll('.rp-answer').forEach(b => b.disabled = true);
    document.querySelectorAll('.rp-btn').forEach(b => b.disabled = true);

    try {
      const body = type === 'slider'
        ? { token, slider_value: Number(value) }
        : { token, option_index: Number(value) };

      const res  = await fetch(`${cfg.api}/answer`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
        body: JSON.stringify(body),
      });
      const data = await res.json();

      if (data.success) {
        // Show answered state immediately, don't wait for poll
        const wrap = document.querySelector('.rp-answers, .rp-slider-wrap');
        if (wrap) {
          wrap.innerHTML = '';
        }
        const area = document.querySelector('.rp-q-text-area');
        if (area) {
          const ack = document.createElement('div');
          ack.className = 'rp-answered-state';
          ack.innerHTML = '<div class="rp-answered-icon">✅</div><div class="rp-answered-text">Svar registreret – vent på resultater</div>';
          area.after(ack);
        }
      }
    } catch (_) {
      _answered = false;
    }
  };

  // ── RESULT (after each question) ──────────────────────────────────────────

  function render_result() {
    const correct      = state.player_result?.correct ?? false;
    const points       = state.player_result?.points_gained ?? 0;
    const total_score  = state.player_result?.total_score ?? 0;
    const streak       = state.player_result?.streak ?? 0;
    const icon         = correct ? '🎯' : '😬';
    const label        = correct ? 'Korrekt!' : 'Forkert';

    set_phase(`
      <div class="rp-result-wrap">
        <div class="rp-result-icon">${icon}</div>
        <div class="rp-result-label">${label}</div>
        ${points > 0 ? `<div class="rp-points-gained">+${points} point</div>` : ''}
        <div class="rp-total-score">Total: ${total_score} point</div>
        ${streak >= 2 ? `<div class="rp-streak">🔥 ${streak} rigtige i træk!</div>` : ''}
        <div class="rp-waiting-for-host">Venter på næste spørgsmål…</div>
      </div>`);
  }

  // ── LEADERBOARD ────────────────────────────────────────────────────────────

  function render_leaderboard(is_podium) {
    const lb      = state.leaderboard || [];
    const my_rank = state.my_rank ?? null;
    const medals  = ['🥇', '🥈', '🥉'];

    const rows = lb.map((p, i) => `
      <div class="rp-lb-row${p.is_me ? ' is-me' : ''}">
        <div class="rp-lb-rank">${medals[i] || (i + 1)}</div>
        <div class="rp-lb-name">${esc(p.nickname)}${p.is_me ? ' 👈' : ''}</div>
        <div class="rp-lb-score">${Number(p.score).toLocaleString('da')}</div>
      </div>`).join('');

    const myRankHtml = my_rank && my_rank > lb.length
      ? `<div class="rp-waiting-for-host" style="margin-top:12px">Din placering: #${my_rank}</div>`
      : '';

    if (is_podium) {
      // Clear session on podium
      localStorage.removeItem('rzlq_token');
      token = '';
      stop_polling();
    }

    set_phase(`
      <div class="rp-lb-wrap">
        <div class="rp-lb-title">${is_podium ? '🏆 Endelig stilling' : '📋 Topplaceringer'}</div>
        ${rows || '<div class="rp-waiting-for-host">Ingen data</div>'}
        ${myRankHtml}
      </div>`);
  }

  // ── FINISHED ───────────────────────────────────────────────────────────────

  function render_finished() {
    stop_polling();
    localStorage.removeItem('rzlq_token');
    token = '';

    set_phase(`
      <div class="rp-waiting" style="text-align:center">
        <div style="font-size:72px">🎉</div>
        <div class="rp-waiting-title">Spillet er slut!</div>
        <div class="rp-waiting-sub">Tak for at spille med</div>
        <button class="rp-btn" style="max-width:280px;margin-top:8px"
                onclick="location.reload()">Spil igen</button>
      </div>`);
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  function set_phase(html) {
    root.innerHTML = `<div class="rp-phase">${html}</div>`;
  }

  function show_err(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.style.display = msg ? 'block' : 'none';
  }

  function esc(s) {
    return String(s || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Init ──────────────────────────────────────────────────────────────────

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

})();
