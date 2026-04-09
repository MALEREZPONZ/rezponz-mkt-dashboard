/* =============================================================================
   Rezponz Live Quiz — Host Screen
   ============================================================================= */
(function () {
  'use strict';

  const SHAPES = ['▲', '◆', '●', '■'];
  const COLORS  = ['#e85a10', '#3b82f6', '#eab308', '#22c55e'];

  let root, cfg, state = null, hash = '', timer_iv = null, poll_iv = null;

  // ── Boot ───────────────────────────────────────────────────────────────────

  function boot() {
    root = document.getElementById('rzlq-host-root');
    if (!root) return;

    cfg = {
      gameId:     root.dataset.gameId,
      api:        root.dataset.api,
      nonce:      root.dataset.nonce,
      quizTitle:  root.dataset.quizTitle || 'Quiz',
      playerUrl:  root.dataset.playerUrl || '',
    };

    render_shell();
    poll();
    poll_iv = setInterval(poll, 1500);
  }

  // ── Polling ────────────────────────────────────────────────────────────────

  async function poll() {
    try {
      const url = `${cfg.api}/host/state?game_id=${cfg.gameId}&hash=${hash}`;
      const res  = await fetch(url, {
        headers: { 'X-WP-Nonce': cfg.nonce, 'Cache-Control': 'no-cache' }
      });
      const data = await res.json();

      if (!data.success) return;
      if (data.changed === false) return; // unchanged — skip re-render

      hash  = data.hash;
      state = data;
      render_state();
    } catch (_) {}
  }

  // ── REST helpers ──────────────────────────────────────────────────────────

  async function advance() {
    const btn = document.getElementById('rh-adv-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Vent…'; }

    try {
      const res  = await fetch(`${cfg.api}/host/advance`, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce':   cfg.nonce,
        },
        body: JSON.stringify({ game_id: cfg.gameId }),
      });
      const data = await res.json();
      if (data.success) {
        hash = ''; // force full refresh
        await poll();
      }
    } catch (_) {
      if (btn) { btn.disabled = false; }
    }
  }

  async function kickPlayer(playerId) {
    await fetch(`${cfg.api}/host/kick`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify({ game_id: cfg.gameId, player_id: playerId }),
    });
    hash = '';
    await poll();
  }

  // ── Shell ──────────────────────────────────────────────────────────────────

  function render_shell() {
    root.innerHTML = `
      <div class="rh">
        <div class="rh-topbar">
          <div class="rh-logo">Rezponz</div>
          <div class="rh-quiz-title">${esc(cfg.quizTitle)}</div>
          <div class="rh-topbar-actions">
            <button class="rh-fs-btn" onclick="rzlqToggleFS()">⛶ Fuldskærm</button>
          </div>
        </div>
        <div id="rh-main"></div>
        <div id="rh-advance-bar" class="rh-advance-bar" style="display:none">
          <button id="rh-adv-btn" class="rh-advance-btn" onclick="rzlqAdvance()">Start</button>
        </div>
      </div>`;
  }

  // ── Render dispatcher ─────────────────────────────────────────────────────

  function render_state() {
    if (!state) return;
    clearInterval(timer_iv);

    switch (state.status) {
      case 'waiting':          render_lobby();    break;
      case 'question_active':  render_question(); break;
      case 'question_results': render_results();  break;
      case 'leaderboard':      render_leaderboard(false); break;
      case 'podium':           render_leaderboard(true);  break;
      case 'finished':         render_finished(); break;
    }
  }

  // ── LOBBY ─────────────────────────────────────────────────────────────────

  function render_lobby() {
    const players   = state.players || [];
    const playerUrl = cfg.playerUrl || window.location.origin + '/spil';

    const chips = players.map(p =>
      `<div class="rh-player-chip" ondblclick="rzlqKick(${p.id})" title="Dobbeltklik for at fjerne">${esc(p.nickname)}</div>`
    ).join('');

    main(`
      <div class="rh-lobby">
        <div class="rh-pin-box">
          <div class="rh-pin-eyebrow">Gå til · ${esc(playerUrl)}</div>
          <div class="rh-pin">${esc(state.pin)}</div>
          <div class="rh-join-url">Indtast PIN-koden på spillersiden</div>
        </div>
        <div>
          <div class="rh-player-count"><strong>${players.length}</strong> spillere tilmeldt</div>
        </div>
        ${chips ? `<div class="rh-player-grid">${chips}</div>` : ''}
      </div>`);

    adv_btn(players.length > 0 ? '▶ Start første spørgsmål' : 'Afventer spillere…', players.length === 0);
  }

  // ── QUESTION ACTIVE ───────────────────────────────────────────────────────

  function render_question() {
    const q         = state.question || {};
    const idx       = state.question_index;
    const total     = state.question_total;
    const type      = q.type || 'multiple_choice';

    // Answers grid
    let answersHtml = '';
    if (type === 'slider') {
      answersHtml = `<div style="padding:16px 28px;font-size:17px;color:rgba(255,255,255,.5);text-align:center">
        Skala: ${q.min ?? 1} → ${q.max ?? 10} &nbsp;·&nbsp; Korrekt: ${q.correct ?? '?'}
      </div>`;
    } else {
      const opts = q.options || [];
      answersHtml = `<div class="rh-answers-grid" style="grid-template-columns:${opts.length===2?'1fr 1fr':'1fr 1fr'}">
        ${opts.map((o, i) => `
          <div class="rh-ans-btn" style="background:${COLORS[i]||'#555'}">
            <span class="rh-ans-shape">${SHAPES[i]||''}</span>
            ${esc(o.text)}
            ${o.correct ? ' <span style="margin-left:auto;opacity:.8">✓</span>' : ''}
          </div>`).join('')}
      </div>`;
    }

    const imgHtml = q.image_url
      ? `<img class="rh-q-image" src="${esc(q.image_url)}" alt="">`
      : '';

    main(`
      <div class="rh-question-wrap">
        <div class="rh-q-header">
          <span class="rh-q-counter">Spørgsmål ${idx+1} / ${total}</span>
          <div class="rh-timer-wrap">
            <div id="rh-timer-num" class="rh-timer-number">—</div>
            <div class="rh-timer-bar-bg">
              <div id="rh-timer-bar" class="rh-timer-bar-fill" style="width:100%"></div>
            </div>
          </div>
          <span class="rh-q-answered">Besvaret: <strong id="rh-ans-cnt">${state.answer_count||0}</strong> / ${state.player_count||0}</span>
        </div>
        <div class="rh-q-text-wrap">
          ${imgHtml}
          <div class="rh-q-text">${esc(q.text || '')}</div>
        </div>
        ${answersHtml}
      </div>`);

    adv_btn('⏩ Afslut spørgsmål nu');
    start_timer(state.time_remaining_ms, state.time_limit_ms);
  }

  function start_timer(remaining_ms, limit_ms) {
    const el  = document.getElementById('rh-timer-num');
    const bar = document.getElementById('rh-timer-bar');
    let t     = remaining_ms;

    function tick() {
      if (!el) return;
      const secs = Math.ceil(t / 1000);
      el.textContent   = secs;
      el.className     = 'rh-timer-number' + (secs <= 5 ? ' urgent' : secs <= 10 ? ' warn' : '');
      const pct        = Math.max(0, (t / limit_ms) * 100);
      bar.style.width  = pct + '%';
      bar.style.background = secs <= 5 ? '#ef4444' : secs <= 10 ? '#f97316' : '#738991';
      t -= 100;
      if (t <= 0) {
        clearInterval(timer_iv);
        el.textContent = '0';
      }
    }
    tick();
    timer_iv = setInterval(tick, 100);
  }

  // ── RESULTS ────────────────────────────────────────────────────────────────

  function render_results() {
    const q       = state.question || {};
    const dist    = state.distribution || {};
    const opts    = q.options || [];
    const tot     = state.answer_count || 0;
    const type    = q.type || 'multiple_choice';
    const hasNext = state.has_next;

    let rows = '';

    if ( type === 'slider' ) {
      // Show slider value distribution as a simple list
      const entries = Object.entries(dist).sort((a,b) => Number(a[0])-Number(b[0]));
      const maxCnt  = Math.max(1, ...entries.map(e => e[1]));
      rows = entries.map(([val, cnt]) => {
        const pct = Math.round(cnt / maxCnt * 100);
        return `
          <div class="rh-bar-row">
            <div class="rh-bar-label">
              <span style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:900;color:#738991;width:50px;text-align:right">${val}</span>
            </div>
            <div class="rh-bar-bg">
              <div class="rh-bar-fill" data-pct="${pct}" style="background:#738991">${cnt} svar</div>
            </div>
            <div class="rh-bar-count">${cnt}</div>
          </div>`;
      }).join('');
      if ( !rows ) rows = `<p style="color:rgba(255,255,255,.4);text-align:center">Ingen besvarede</p>`;
    } else {
      rows = opts.map((o, i) => {
        const count = dist[String(i)] || 0;
        const pct   = tot > 0 ? Math.round(count / tot * 100) : 0;
        return `
          <div class="rh-bar-row">
            <div class="rh-bar-label">
              <div style="width:28px;height:28px;border-radius:8px;background:${COLORS[i]};display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff">${SHAPES[i]}</div>
              <span style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px">${esc(o.text)}</span>
              ${o.correct ? '<span class="rh-correct-mark">✅</span>' : ''}
            </div>
            <div class="rh-bar-bg">
              <div class="rh-bar-fill" data-pct="${pct}" style="background:${COLORS[i]}">${pct}%</div>
            </div>
            <div class="rh-bar-count">${count}</div>
          </div>`;
      }).join('') || `<p style="color:rgba(255,255,255,.4);text-align:center">Ingen besvarede</p>`;
    }

    main(`
      <div class="rh-results-wrap">
        <div class="rh-results-title">📊 Resultater — ${tot} svar</div>
        ${rows}
      </div>`);

    adv_btn(hasNext ? '📋 Vis topplaceringer' : '🏆 Vis podium');

    // Animate bars in after paint
    requestAnimationFrame(() => setTimeout(() => {
      document.querySelectorAll('.rh-bar-fill').forEach(el => {
        el.style.width = el.dataset.pct + '%';
      });
    }, 80));
  }

  // ── LEADERBOARD / PODIUM ──────────────────────────────────────────────────

  function render_leaderboard(is_podium) {
    const lb     = state.leaderboard || [];
    const medals = ['🥇', '🥈', '🥉'];

    const rows = lb.map((p, i) => `
      <div class="rh-lb-row" style="animation-delay:${i*0.07}s">
        <div class="rh-lb-rank">${medals[i] || (i+1)}</div>
        <div class="rh-lb-name">${esc(p.nickname)}</div>
        <div class="rh-lb-score">${Number(p.score).toLocaleString('da')}</div>
      </div>`).join('');

    main(`
      <div class="rh-lb-wrap">
        <div class="rh-lb-title">${is_podium ? '🏆 Endelig stilling' : '📋 Topplaceringer'}</div>
        ${rows || `<p style="color:rgba(255,255,255,.4);text-align:center">Ingen data</p>`}
      </div>`);

    if (is_podium) {
      adv_btn('✓ Afslut spil', false, 'danger');
    } else if (state.has_next) {
      adv_btn('▶ Næste spørgsmål');
    } else {
      adv_btn('🏆 Vis podium');
    }
  }

  // ── FINISHED ──────────────────────────────────────────────────────────────

  function render_finished() {
    clearInterval(poll_iv);
    main(`
      <div class="rh-lobby" style="text-align:center">
        <div style="font-size:80px">🎉</div>
        <h2 style="font-family:'Outfit',sans-serif;font-size:32px;font-weight:900">Spillet er slut!</h2>
        <p style="color:rgba(255,255,255,.5);font-size:16px">Tak for at spille med</p>
        <a href="${esc(document.referrer || '/wp-admin/admin.php?page=rzlq-quizzes')}"
           style="display:inline-block;margin-top:20px;background:#738991;color:#fff;padding:14px 32px;border-radius:12px;text-decoration:none;font-size:16px;font-weight:700">
          ← Tilbage til quizzer
        </a>
      </div>`);

    document.getElementById('rh-advance-bar').style.display = 'none';
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  function main(html) {
    document.getElementById('rh-main').innerHTML = html;
  }

  function adv_btn(label, disabled = false, extra_class = '') {
    const bar = document.getElementById('rh-advance-bar');
    const btn = document.getElementById('rh-adv-btn');
    if (!bar || !btn) return;
    bar.style.display = 'flex';
    btn.textContent = label;
    btn.disabled    = disabled;
    btn.className   = 'rh-advance-btn' + (extra_class ? ' ' + extra_class : '');
  }

  function esc(s) {
    return String(s || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Global event handlers ─────────────────────────────────────────────────

  window.rzlqAdvance   = advance;
  window.rzlqKick      = kickPlayer;
  window.rzlqToggleFS  = () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
    else document.exitFullscreen?.();
  };

  // ── Init ──────────────────────────────────────────────────────────────────

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

})();
