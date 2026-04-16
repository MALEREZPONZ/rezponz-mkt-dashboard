/* =========================================================
   RezCRM — rezcrm.js  v3.3.0
   ========================================================= */
(function () {
  'use strict';

  const BASE  = RZPZ_CRM.apiBase;
  const NONCE = RZPZ_CRM.nonce;

  // ── State ───────────────────────────────────────────────────────────────────
  let allApplications = [];
  let allPositions    = [];
  let allTemplates    = [];
  let activeApp       = null;  // application currently open in detail modal
  let editingPosId    = null;
  let editingTplId    = null;
  // Drag state (mouse-based — bypasses WordPress admin HTML5 DnD conflicts)
  let dragId          = null;
  let dragActive      = false;
  let dragCloneEl     = null;
  let dragSourceEl    = null;
  let dragHoverStage  = null;
  let activePositionId   = null;   // position ID open in detail view
  let posDetailStage     = '';     // stage filter in position detail
  let posDetailFolder    = null;   // active folder ID filter in position detail
  let positiveFolders    = [];     // folders for current position
  let posTabStatusFilter = '';     // status filter in positions tab list
  let posTabTypeFilter   = '';     // job_type filter in positions tab list

  // ── Helpers ─────────────────────────────────────────────────────────────────
  const el  = id => document.getElementById(id);
  const qs  = (s, c) => (c || document).querySelector(s);
  const qsa = (s, c) => [...(c || document).querySelectorAll(s)];

  async function api(path, opts = {}) {
    const res  = await fetch(BASE + path, {
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      ...opts,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'API fejl ' + res.status);
    return data;
  }

  function toast(msg, type = 'ok') {
    const t = el('crm-toast');
    t.textContent = msg;
    t.className   = 'crm-toast show ' + type;
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.className = 'crm-toast'; }, 3500);
  }

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtDate(d) {
    if (!d) return '–';
    const dt = new Date(d.includes('T') ? d : d.replace(' ','T'));
    return dt.toLocaleDateString('da-DK', { day:'numeric', month:'short', year:'numeric' });
  }

  function fmtDateTime(d) {
    if (!d) return '';
    const dt = new Date(d.includes('T') ? d : d.replace(' ','T'));
    return dt.toLocaleDateString('da-DK', { day: 'numeric', month: 'short', year: 'numeric' })
      + ' kl. ' + dt.toLocaleTimeString('da-DK', { hour: '2-digit', minute: '2-digit' });
  }

  function stagePill(stage) {
    const label = (RZPZ_CRM.stages[stage] || stage);
    return `<span class="crm-stage-pill crm-stage-${escHtml(stage)}">${escHtml(label)}</span>`;
  }

  function starsHtml(rating) {
    let s = '';
    for (let i = 1; i <= 5; i++) {
      s += `<span class="crm-star${i <= (rating || 0) ? ' filled' : ''}" data-r="${i}">★</span>`;
    }
    return s;
  }

  function sourceLabel(src) {
    return RZPZ_CRM.sources[src] || src || '–';
  }

  function fmtDateShort(d) {
    if (!d || d === '0000-00-00') return '–';
    const dt = new Date(d.includes('T') ? d : d + 'T00:00:00');
    return dt.toLocaleDateString('da-DK', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function parseAonScores(jsonStr) {
    if (!jsonStr) return {};
    try {
      const d = JSON.parse(jsonStr);
      const scores = {};
      // Flat keys
      const flat = d.scores || d.results || d;
      if (flat && typeof flat === 'object') {
        Object.entries(flat).forEach(([k, v]) => {
          const kl = k.toLowerCase();
          if (kl.includes('kunde') || kl === 'cs') scores.kundeservice = v;
          if (kl.includes('outbound') || kl === 'ob') scores.outbound = v;
          if (kl.includes('logisk') || kl.includes('logical') || kl.includes('logic')) scores.logisk = v;
        });
      }
      // Array structure
      if (Array.isArray(d.assessments || d.sections)) {
        (d.assessments || d.sections).forEach(a => {
          const n = (a.name || a.title || a.type || '').toLowerCase();
          const val = a.score ?? a.result ?? a.value ?? a.scaled_score ?? null;
          if (n.includes('kunde') || n.includes('customer')) scores.kundeservice = val;
          if (n.includes('outbound')) scores.outbound = val;
          if (n.includes('logisk') || n.includes('logical') || n.includes('logic')) scores.logisk = val;
        });
      }
      return scores;
    } catch(e) { return {}; }
  }

  // ── Calendar tab ─────────────────────────────────────────────────────────
  let calYear, calMonth;

  function initCalendar() {
      const now = new Date();
      calYear  = now.getFullYear();
      calMonth = now.getMonth();
      el('crm-cal-prev').addEventListener('click', () => { calMonth--; if(calMonth<0){calMonth=11;calYear--;} renderCalendar(); });
      el('crm-cal-next').addEventListener('click', () => { calMonth++; if(calMonth>11){calMonth=0;calYear++;} renderCalendar(); });
      renderCalendar();
  }

  function renderCalendar() {
      const label = new Date(calYear, calMonth, 1).toLocaleDateString('da-DK', { month: 'long', year: 'numeric' });
      el('crm-cal-month-label').textContent = label.charAt(0).toUpperCase() + label.slice(1);
      el('crm-cal-detail').style.display = 'none';

      const grid = el('crm-cal-grid');
      grid.innerHTML = '';

      // Day headers
      ['Man','Tir','Ons','Tor','Fre','Lør','Søn'].forEach(d => {
          const h = document.createElement('div');
          h.className = 'crm-cal-dayname';
          h.textContent = d;
          grid.appendChild(h);
      });

      const firstDay = new Date(calYear, calMonth, 1);
      const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
      const startOffset = (firstDay.getDay() + 6) % 7;

      for (let i = 0; i < startOffset; i++) {
          const blank = document.createElement('div');
          blank.className = 'crm-cal-day crm-cal-day-empty';
          grid.appendChild(blank);
      }

      const today = new Date();

      for (let d = 1; d <= daysInMonth; d++) {
          const dateStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
          const dayApps = allApplications.filter(a => a.created_at && a.created_at.startsWith(dateStr));

          const cell = document.createElement('div');
          cell.className = 'crm-cal-day' +
              (d === today.getDate() && calMonth === today.getMonth() && calYear === today.getFullYear() ? ' crm-cal-today' : '') +
              (dayApps.length > 0 ? ' crm-cal-has-apps' : '');
          cell.innerHTML = `<span class="crm-cal-daynum">${d}</span>` +
              (dayApps.length > 0 ? `<span class="crm-cal-dot">${dayApps.length}</span>` : '');

          if (dayApps.length > 0) {
              cell.addEventListener('click', () => showCalDay(dateStr, dayApps));
          }
          grid.appendChild(cell);
      }
  }

  function showCalDay(dateStr, apps) {
      const [y,m,d] = dateStr.split('-');
      const label = new Date(y,m-1,d).toLocaleDateString('da-DK',{weekday:'long',day:'numeric',month:'long'});
      el('crm-cal-detail-title').textContent = label.charAt(0).toUpperCase() + label.slice(1);
      el('crm-cal-detail-list').innerHTML = apps.map(a =>
          `<div class="crm-cal-app-item" style="cursor:pointer" data-id="${a.id}">
              <span>${escHtml(a.first_name)} ${escHtml(a.last_name)}</span>
              <span style="color:var(--crm-muted);font-size:12px">${escHtml(a.position_title || 'Generel ansøgning')}</span>
              ${stagePill(a.stage)}
           </div>`
      ).join('');
      el('crm-cal-detail-list').querySelectorAll('[data-id]').forEach(item => {
          item.addEventListener('click', () => openAppDetail(+item.dataset.id));
      });
      el('crm-cal-detail').style.display = '';
  }

  // ── Boot ────────────────────────────────────────────────────────────────────
  async function boot() {
    await Promise.all([loadPositions(), loadApplications(), loadTemplates()]);
    renderKanban();
    renderListView();
    renderPositionsTab();    // NEW
    loadStats();
    initFilters();
    initTabs();
    initCalendar();
    initModals();
    initPositionModal();
    initTemplatesModal();
    initPositionsTabHandlers();  // NEW
  }

  // ── Data loaders ─────────────────────────────────────────────────────────────
  async function loadPositions() {
    allPositions = await api('positions');
    // Populate position filter + app-modal select
    populatePositionSelects();
  }

  async function loadApplications() {
    const stage  = el('list-stage-filter')?.value || '';
    const search = el('crm-search')?.value || '';
    const posId  = el('crm-position-filter')?.value || '';
    const params = new URLSearchParams();
    if (stage)  params.set('stage', stage);
    if (search) params.set('search', search);
    if (posId)  params.set('position_id', posId);
    const queryStr = params.toString();
    allApplications = await api('applications' + (queryStr ? '?' + queryStr : ''));
  }

  async function loadTemplates() {
    allTemplates = await api('templates');
  }

  async function loadStats() {
    try {
      const s = await api('stats');
      el('kpi-total').textContent     = Object.values(s.pipeline || {}).reduce((a,b) => a + (+b), 0);
      el('kpi-samtale').textContent   = s.pipeline?.samtale || 0;
      el('kpi-ansat').textContent     = s.pipeline?.ansat   || 0;
      el('kpi-conversion').textContent = (s.conversion?.conversion_rate || 0) + '%';
      renderSources(s.sources || []);
    } catch(e) {}
  }

  function populatePositionSelects() {
    const filter  = el('crm-position-filter');
    const appSel  = el('app-position');

    // Vis alle stillinger (ikke kun 'open') — 'closed'/'draft' stadig valgbare ved manuel oprettelse
    const opts = allPositions.filter(p => p.status !== 'draft').map(p =>
      `<option value="${p.id}">${escHtml(p.title)}${p.status !== 'open' ? ' (' + escHtml(p.status) + ')' : ''}</option>`
    ).join('');

    if (filter) {
      const was = filter.value;
      filter.innerHTML = '<option value="">Alle stillinger</option>' + opts;
      filter.value = was;
    }
    if (appSel) {
      appSel.innerHTML = '<option value="">— Ingen specifik stilling —</option>' + opts;
    }
  }

  // ── Kanban ──────────────────────────────────────────────────────────────────
  function renderKanban() {
    const kanban = el('crm-kanban');
    kanban.innerHTML = '';

    Object.entries(RZPZ_CRM.stages).forEach(([stage, label]) => {
      const apps = allApplications.filter(a => a.stage === stage);

      const col = document.createElement('div');
      col.className     = 'crm-col';
      col.dataset.stage = stage;

      col.innerHTML = `
        <div class="crm-col-header">
          <span class="crm-col-title">${escHtml(label)}</span>
          <span class="crm-col-count">${apps.length}</span>
        </div>
        <div class="crm-cards" data-stage="${escHtml(stage)}">
          ${apps.map(cardHtml).join('')}
        </div>`;

      kanban.appendChild(col);
    });

    // ── Mouse-based drag & drop (avoids WordPress admin HTML5 DnD conflicts) ──
    qsa('.crm-card').forEach(card => {
      let _didDrag = false;

      card.addEventListener('mousedown', e => {
        if (e.button !== 0 || e.target.closest('a,button,select,textarea,input')) return;

        const startX = e.clientX;
        const startY = e.clientY;
        _didDrag = false;

        const onMove = moveE => {
          const dx = moveE.clientX - startX;
          const dy = moveE.clientY - startY;

          // Only start drag after moving 6px (avoids accidental drags on clicks)
          if (!dragActive && Math.hypot(dx, dy) < 6) return;

          if (!dragActive) {
            dragActive    = true;
            dragId        = +card.dataset.id;
            dragSourceEl  = card;
            _didDrag      = true;

            // Create floating ghost clone
            const rect = card.getBoundingClientRect();
            dragCloneEl = card.cloneNode(true);
            Object.assign(dragCloneEl.style, {
              position:  'fixed',
              zIndex:    '99999',
              width:     rect.width + 'px',
              pointerEvents: 'none',
              opacity:   '0.88',
              boxShadow: '0 16px 48px rgba(0,0,0,.55)',
              transform: 'rotate(2deg) scale(1.03)',
              transition: 'none',
              left:      rect.left + 'px',
              top:       rect.top  + 'px',
              cursor:    'grabbing',
            });
            document.body.appendChild(dragCloneEl);
            card.style.opacity = '0.2';
            document.body.style.userSelect = 'none';
          }

          // Follow cursor
          dragCloneEl.style.left = (moveE.clientX - dragCloneEl.offsetWidth / 2) + 'px';
          dragCloneEl.style.top  = (moveE.clientY - 24) + 'px';

          // Highlight column under cursor
          qsa('.crm-col').forEach(c => c.classList.remove('drag-over'));
          dragHoverStage = null;
          dragCloneEl.style.display = 'none';
          const under = document.elementFromPoint(moveE.clientX, moveE.clientY);
          dragCloneEl.style.display = '';
          const hoverCol = under?.closest('.crm-col');
          if (hoverCol) {
            hoverCol.classList.add('drag-over');
            dragHoverStage = hoverCol.dataset.stage;
          }
        };

        const onUp = async () => {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup',   onUp);
          document.body.style.userSelect = '';

          if (dragActive) {
            dragCloneEl?.remove();
            dragCloneEl = null;
            if (dragSourceEl) dragSourceEl.style.opacity = '';
            qsa('.crm-col').forEach(c => c.classList.remove('drag-over'));

            if (dragHoverStage) await handleDrop(dragHoverStage);

            dragActive = false; dragId = null; dragSourceEl = null; dragHoverStage = null;
          }
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup',   onUp);
      });

      card.addEventListener('click', () => {
        if (_didDrag) { _didDrag = false; return; }
        openAppDetail(+card.dataset.id);
      });
    });
  }

  function cardHtml(a) {
    const rejBadge = a.rejection_scheduled_at && !a.rejection_sent_at
      ? `<div class="crm-card-rejection">⏳ Afslag-email ${fmtDate(a.rejection_scheduled_at)}</div>`
      : '';
    return `<div class="crm-card" data-id="${a.id}">
      <div class="crm-card-header">
        ${a.photo_url
          ? `<img src="${escHtml(a.photo_url)}" class="crm-card-photo" alt="">`
          : `<div class="crm-card-initials">${escHtml(((a.first_name||'')[0]||'').toUpperCase() + ((a.last_name||'')[0]||'').toUpperCase())}</div>`}
        <div>
          <div class="crm-card-name">${escHtml(a.first_name)} ${escHtml(a.last_name)}</div>
          <div class="crm-card-pos">${escHtml(a.position_title || '')}</div>
        </div>
      </div>
      <div class="crm-card-meta">
        <span class="crm-source-badge">${escHtml(sourceLabel(a.source))}</span>
        <span class="crm-rating-stars">${'★'.repeat(a.rating || 0)}</span>
      </div>
      ${rejBadge}
    </div>`;
  }

  async function handleDrop(newStage) {
    if (!dragId) return;
    const app = allApplications.find(a => a.id === dragId);
    if (!app || app.stage === newStage) return;
    try {
      await api(`applications/${dragId}/stage`, {
        method: 'PATCH',
        body:   JSON.stringify({ stage: newStage }),
      });
      toast(`Flyttet til ${RZPZ_CRM.stages[newStage] || newStage}`);
      await loadApplications();
      renderKanban();
      renderListView();
      loadStats();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
    dragId = null;
  }

  // ── List view ───────────────────────────────────────────────────────────────
  function renderListView() {
    const tbody = el('crm-list-tbody');
    if (!allApplications.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--crm-muted)">Ingen ansøgninger endnu</td></tr>';
      return;
    }
    tbody.innerHTML = allApplications.map(a => `
      <tr data-id="${a.id}">
        <td>${escHtml(a.first_name)} ${escHtml(a.last_name)}</td>
        <td>${escHtml(a.email)}</td>
        <td>${escHtml(a.position_title || '–')}</td>
        <td>${stagePill(a.stage)}</td>
        <td>${escHtml(sourceLabel(a.source))}</td>
        <td>${fmtDate(a.created_at)}</td>
        <td>${'★'.repeat(a.rating || 0)}</td>
        <td><button class="crm-btn crm-btn-ghost" style="padding:4px 10px;font-size:11px" data-open="${a.id}">Åbn</button></td>
      </tr>`).join('');

    qsa('#crm-list-tbody tr').forEach(row => {
      row.addEventListener('click', e => {
        const btn = e.target.closest('[data-open]');
        openAppDetail(+(btn ? btn.dataset.open : row.dataset.id));
      });
    });
  }

  // ── Sources ─────────────────────────────────────────────────────────────────
  function renderSources(sources) {
    const grid = el('crm-sources-grid');
    if (!sources.length) { grid.innerHTML = '<p style="color:var(--crm-muted)">Ingen data endnu</p>'; return; }
    grid.innerHTML = sources.map(s => `
      <div class="crm-source-card">
        <div class="crm-kpi-val">${s.cnt}</div>
        <div class="crm-kpi-label">${escHtml(sourceLabel(s.source))}</div>
      </div>`).join('');
  }

  // ── Filters & tabs ──────────────────────────────────────────────────────────
  function initFilters() {
    let searchTimer;
    el('crm-search').addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(refreshAll, 350);
    });
    el('crm-position-filter').addEventListener('change', refreshAll);
    el('list-stage-filter').addEventListener('change', refreshAll);
  }

  async function refreshAll() {
    await loadApplications();
    renderKanban();
    renderListView();
  }

  function initTabs() {
    qsa('.crm-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        qsa('.crm-tab').forEach(t => t.classList.remove('crm-tab-active'));
        qsa('.crm-tab-panel').forEach(p => { p.style.display = 'none'; });
        tab.classList.add('crm-tab-active');
        const panel = el('tab-' + tab.dataset.tab);
        if (panel) panel.style.display = '';
      });
    });
  }

  // ── Modals ──────────────────────────────────────────────────────────────────
  function initModals() {
    // Close buttons
    qsa('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });
    el('crm-backdrop').addEventListener('click', closeAllModals);

    // Source options
    const srcSel = el('app-source');
    if (srcSel) {
      srcSel.innerHTML = Object.entries(RZPZ_CRM.sources)
        .map(([k,v]) => `<option value="${k}">${escHtml(v)}</option>`).join('');
    }

    // New application button
    el('crm-new-app-btn').addEventListener('click', openCreateForm);

    // Save new application
    el('crm-app-save-btn').addEventListener('click', saveNewApplication);

    // ── Detail tabs — bind ONCE here, never in renderAppDetail ──────────────
    // Using activeApp.id at click-time prevents stale closures and duplicate
    // listeners that caused the activity feed to load inconsistently.
    qsa('[data-dtab]').forEach(btn => {
      btn.addEventListener('click', () => {
        qsa('[data-dtab]').forEach(b => b.classList.remove('active'));
        qsa('.crm-detail-tabpanel').forEach(p => p.style.display = 'none');
        btn.classList.add('active');
        const panel = el('crm-dtab-' + btn.dataset.dtab);
        if (panel) panel.style.display = '';

        if (!activeApp) return;
        const appId = activeApp.id;

        if (btn.dataset.dtab === 'aktivitet') {
          loadActivityFeed(appId);
        }
        if (btn.dataset.dtab === 'noter') {
          loadNotesFeed(appId);
        }
        if (btn.dataset.dtab === 'kommunikation') {
          const tplSel = el('crm-template-select');
          if (tplSel) {
            tplSel.innerHTML = '<option value="">— Vælg skabelon eller skriv manuelt —</option>' +
              allTemplates.map(t => `<option value="${t.id}">${escHtml(t.name)} (${t.type})</option>`).join('');
            tplSel.onchange = () => {
              const tpl = allTemplates.find(t => t.id == tplSel.value);
              if (tpl) {
                el('crm-comm-subject').value = tpl.subject || '';
                el('crm-comm-body').value    = tpl.body    || '';
              }
            };
          }
          el('crm-send-btn').onclick = () => sendComm(appId);
        }
      });
    });
  }

  function openModal(id) {
    el(id).style.display = 'flex';
    el('crm-backdrop').style.display = 'block';
  }
  function closeModal(id) {
    el(id).style.display = 'none';
    // Only hide backdrop if no other modals open
    if (!qsa('.crm-modal').some(m => m.style.display !== 'none')) {
      el('crm-backdrop').style.display = 'none';
    }
  }
  function closeAllModals() {
    qsa('.crm-modal').forEach(m => m.style.display = 'none');
    el('crm-backdrop').style.display = 'none';
  }

  // ── Create application form ─────────────────────────────────────────────────
  function openCreateForm() {
    el('crm-app-modal-title').textContent = 'Ny ansøgning';
    el('crm-app-create-form').style.display = '';
    el('crm-app-detail').style.display       = 'none';
    // Reset fields
    ['app-first-name','app-last-name','app-email','app-phone','app-cover'].forEach(id => { el(id).value = ''; });
    el('app-gdpr').checked = false;
    populatePositionSelects();
    openModal('crm-app-modal');
  }

  async function saveNewApplication() {
    const payload = {
      first_name:   el('app-first-name').value.trim(),
      last_name:    el('app-last-name').value.trim(),
      email:        el('app-email').value.trim(),
      phone:        el('app-phone').value.trim(),
      cover_letter: el('app-cover').value.trim(),
      gdpr_consent: el('app-gdpr').checked ? 1 : 0,
      position_id:  +el('app-position').value,
      source:       el('app-source').value,
    };
    if (!payload.first_name || !payload.email) {
      toast('Udfyld fornavn og email', 'err'); return;
    }
    try {
      el('crm-app-save-btn').textContent = '⏳ Gemmer…';
      const res = await api('applications', { method: 'POST', body: JSON.stringify(payload) });
      toast('Ansøgning oprettet ✓');
      closeModal('crm-app-modal');
      await loadApplications();
      renderKanban();
      renderListView();
      loadStats();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      el('crm-app-save-btn').textContent = 'Gem ansøgning';
    }
  }

  // ── Application detail ──────────────────────────────────────────────────────
  async function openAppDetail(id) {
    el('crm-app-modal-title').textContent = 'Ansøgning';
    el('crm-app-create-form').style.display = 'none';
    el('crm-app-detail').style.display       = '';
    openModal('crm-app-modal');

    try {
      activeApp = await api('applications/' + id);
      renderAppDetail(activeApp);

      // Nulstil til overblik-tab ved ny åbning
      qsa('.crm-detail-tabpanel').forEach(p => p.style.display = 'none');
      const overblikPanel = el('crm-dtab-overblik');
      if (overblikPanel) overblikPanel.style.display = '';
      qsa('[data-dtab]').forEach(b => b.classList.remove('active'));
      document.querySelector('[data-dtab="overblik"]')?.classList.add('active');

      // Load history + comms
      const [hist, comms] = await Promise.all([
        api('applications/' + id + '/history'),
        api('applications/' + id + '/comms'),
      ]);
      renderHistory(hist);
      renderComms(comms);
    } catch(e) {
      toast('Fejl ved hentning: ' + e.message, 'err');
    }
  }

  function renderAppDetail(a) {
    // ── Initialer / avatar farve ─────────────────────────────────────────────
    const initials = ((a.first_name||'')[0]||'') + ((a.last_name||'')[0]||'');
    const avatarColors = ['#5d8089','#8b6fbd','#e05c8a','#4a9e7f','#c97b3a'];
    const avatarBg = avatarColors[(a.id || 0) % avatarColors.length];

    // ── Parse form-noter til struktureret liste ──────────────────────────────
    function parseNotes(raw) {
      if (!raw) return [];
      return raw.trim().split(/\r?\n/)
        .map(line => {
          line = line.trim();
          if (!line) return null;
          const idx = line.lastIndexOf(':');   // brug SIDSTE kolon — spørgsmål kan indeholde kolon
          if (idx < 1) return null;
          const label = line.slice(0, idx).trim();
          const value = line.slice(idx + 1).trim();
          return (label && value) ? { label, value } : null;
        })
        .filter(Boolean);
    }
    const formRows = parseNotes(a.notes);

    // ── Kontakt-rækker ───────────────────────────────────────────────────────
    const infoRows = [
      a.email    && { icon: '✉', label: 'Email',    val: `<a href="mailto:${escHtml(a.email)}" class="crm-link">${escHtml(a.email)}</a>` },
      a.phone    && { icon: '📞', label: 'Telefon',  val: escHtml(a.phone) },
      a.city     && { icon: '📍', label: 'By',       val: escHtml(a.city) },
      { icon: '💼', label: 'Stilling',  val: escHtml(a.position_title || 'Generel ansøgning') },
      { icon: '📣', label: 'Kilde',     val: escHtml(sourceLabel(a.source)) },
      { icon: '🗓', label: 'Modtaget',  val: fmtDate(a.created_at) },
    ].filter(Boolean);

    // ── Filer-kort ───────────────────────────────────────────────────────────
    const filerHtml = `
      <div class="crm-info-card crm-files-card">
        <div class="crm-card-heading">Filer &amp; vedhæftninger</div>
        ${a.cv_url
          ? `<a href="${escHtml(a.cv_url)}" target="_blank" class="crm-file-link">
               <span class="crm-file-icon">📄</span>
               <span class="crm-file-name">CV / ansøgningsfil</span>
               <span class="crm-file-action">Åbn ↗</span>
             </a>`
          : `<p class="crm-files-empty">Ingen CV eller filer tilknyttet</p>`}
        ${a.photo_url
          ? `<a href="${escHtml(a.photo_url)}" target="_blank" class="crm-file-link">
               <span class="crm-file-icon">🖼</span>
               <span class="crm-file-name">Profilfoto</span>
               <span class="crm-file-action">Åbn ↗</span>
             </a>`
          : ''}
        <div class="crm-attach-edit" id="crm-attach-edit" style="display:none">
          <label class="crm-attach-label">
            <span>📷 Profilfoto</span>
            <input type="file" id="crm-photo-file" accept="image/*" class="crm-attach-file-input">
            <span class="crm-attach-filename" id="crm-photo-filename">${a.photo_url ? '✓ Foto uploadet' : 'Vælg billede…'}</span>
          </label>
          <label class="crm-attach-label">
            <span>📄 CV / fil</span>
            <input type="file" id="crm-cv-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="crm-attach-file-input">
            <span class="crm-attach-filename" id="crm-cv-filename">${a.cv_url ? '✓ Fil uploadet' : 'Vælg fil…'}</span>
          </label>
          <button class="crm-btn crm-btn-primary crm-btn-sm" id="crm-attach-save-btn">Upload &amp; gem</button>
          <span class="crm-attach-spinner" id="crm-attach-spinner" style="display:none;font-size:12px;color:var(--crm-muted)">⏳ Uploader…</span>
        </div>
        <button class="crm-btn crm-btn-ghost crm-btn-sm" id="crm-attach-toggle-btn" style="margin-top:8px">
          ✎ ${a.photo_url || a.cv_url ? 'Skift foto/fil' : 'Tilføj foto/fil'}
        </button>
      </div>`;

    // ── AON status ───────────────────────────────────────────────────────────
    const aonHtml = a.aon_invitation_url ? `
      <div class="crm-info-card crm-aon-card">
        <div class="crm-aon-header">
          <span class="crm-aon-label">AON Talent Assessment</span>
          <span class="crm-badge ${a.aon_status === 'completed' ? 'crm-badge-green' : 'crm-badge-amber'}">
            ${a.aon_status === 'completed' ? '✓ Gennemført' : '⏳ Afventer'}
          </span>
        </div>
        <a href="${escHtml(a.aon_invitation_url)}" target="_blank" class="crm-link" style="font-size:12px">Åbn testlink ↗</a>
        ${a.aon_result_json && a.aon_status === 'completed'
          ? `<button id="crm-aon-result-btn" class="crm-btn crm-btn-sm" style="margin-top:8px">Se testresultat</button>` : ''}
      </div>` : '';

    el('crm-detail-meta').innerHTML = `
      <!-- Ansøger-header -->
      <div class="crm-applicant-header">
        ${a.photo_url
          ? `<img src="${escHtml(a.photo_url)}" class="crm-avatar crm-avatar-lg crm-avatar-photo" alt="Profil">`
          : `<div class="crm-avatar crm-avatar-lg" style="background:${avatarBg}">${escHtml(initials.toUpperCase())}</div>`}
        <div class="crm-applicant-name">
          <h3>${escHtml(a.first_name)} ${escHtml(a.last_name)}</h3>
          <span class="crm-applicant-pos">${escHtml(a.position_title || 'Generel ansøgning')}</span>
        </div>
        ${stagePill(a.stage)}
      </div>

      <!-- Kontakt-info -->
      <div class="crm-info-card">
        ${infoRows.map(r => `
          <div class="crm-info-row">
            <span class="crm-info-icon">${r.icon}</span>
            <span class="crm-info-label">${r.label}</span>
            <span class="crm-info-val">${r.val}</span>
          </div>`).join('')}
      </div>

      <!-- Filer -->
      ${filerHtml}

      ${aonHtml}

      <!-- Ansøgningstekst -->
      ${a.cover_letter ? `
      <div class="crm-info-card">
        <div class="crm-card-heading">Ansøgningstekst</div>
        <div class="crm-cover-text">${escHtml(a.cover_letter)}</div>
      </div>` : ''}

      <!-- Svar fra formular -->
      ${formRows.length ? `
      <div class="crm-info-card">
        <div class="crm-card-heading">Svar fra formular</div>
        <table class="crm-form-answers">
          ${formRows.map(r => `
            <tr>
              <td class="crm-fa-label">${escHtml(r.label)}</td>
              <td class="crm-fa-val">${escHtml(r.value)}</td>
            </tr>`).join('')}
        </table>
      </div>` : ''}
    `;

    // Filer: toggle + gem
    el('crm-attach-toggle-btn')?.addEventListener('click', () => {
      const d = el('crm-attach-edit');
      if (d) d.style.display = d.style.display === 'none' ? '' : 'none';
    });
    el('crm-photo-file')?.addEventListener('change', () => {
      const f = el('crm-photo-file').files[0];
      if (f) el('crm-photo-filename').textContent = f.name;
    });
    el('crm-cv-file')?.addEventListener('change', () => {
      const f = el('crm-cv-file').files[0];
      if (f) el('crm-cv-filename').textContent = f.name;
    });

    el('crm-attach-save-btn')?.addEventListener('click', async () => {
      const spinner  = el('crm-attach-spinner');
      const saveBtn  = el('crm-attach-save-btn');
      const photoFile = el('crm-photo-file')?.files[0];
      const cvFile    = el('crm-cv-file')?.files[0];

      if (!photoFile && !cvFile) { toast('Vælg mindst én fil', 'err'); return; }

      spinner.style.display = '';
      saveBtn.disabled = true;

      try {
        // Upload helper — bruger samme endpoint som frontend-formularen
        async function uploadFile(file) {
          const fd = new FormData();
          fd.append('file', file);
          const res = await fetch(RZPZ_CRM.apiBase.replace('/crm/', '/crm/') + '../crm/upload-file', {
            method: 'POST',
            headers: { 'X-WP-Nonce': RZPZ_CRM.nonce },
            body: fd,
          });
          if (!res.ok) throw new Error('Upload fejlede (' + res.status + ')');
          const d = await res.json();
          return d.url || '';
        }

        let photo_url = a.photo_url || '';
        let cv_url    = a.cv_url    || '';

        if (photoFile) photo_url = await uploadFile(photoFile);
        if (cvFile)    cv_url    = await uploadFile(cvFile);

        await api(`applications/${a.id}/attachments`, {
          method: 'PATCH',
          body: JSON.stringify({ photo_url, cv_url }),
        });
        toast('Filer gemt ✓');
        activeApp = await api('applications/' + a.id);
        renderAppDetail(activeApp);
      } catch(e) {
        toast('Fejl: ' + e.message, 'err');
      } finally {
        spinner.style.display = 'none';
        saveBtn.disabled = false;
      }
    });

    // AON resultat
    el('crm-aon-result-btn')?.addEventListener('click', () => {
      try {
        const data = JSON.parse(a.aon_result_json || '{}');
        alert(JSON.stringify(data, null, 2));
      } catch(e) {}
    });

    // Stage buttons
    const stageContainer = el('crm-stage-btns');
    stageContainer.innerHTML = Object.entries(RZPZ_CRM.stages).map(([s, l]) =>
      `<button class="crm-stage-btn ${a.stage === s ? 'active' : ''}" data-stage="${s}">${escHtml(l)}</button>`
    ).join('');
    stageContainer.querySelectorAll('.crm-stage-btn').forEach(btn => {
      btn.addEventListener('click', () => moveStage(a.id, btn.dataset.stage));
    });

    // Rating stars
    const starsEl = el('crm-stars');
    starsEl.innerHTML = starsHtml(a.rating);
    starsEl.querySelectorAll('.crm-star').forEach(star => {
      star.addEventListener('click', () => setRating(a.id, +star.dataset.r));
    });

    // Job påbegyndt — Rubix-overførsel
    const rubixSec = el('crm-rubix-section');
    if (rubixSec) {
      if (a.stage === 'job_pabegyndt') {
        rubixSec.style.display = '';
        const rubixBtn = el('crm-rubix-btn');
        if (rubixBtn) {
          if (a.rubix_synced) {
            rubixBtn.textContent = '✓ Overført til Rubix';
            rubixBtn.disabled = true;
            rubixBtn.style.opacity = '0.6';
          } else {
            rubixBtn.textContent = '🔄 Overfør data til Rubix';
            rubixBtn.disabled = false;
            rubixBtn.style.opacity = '1';
          }
          rubixBtn.onclick = () => transferToRubix(a.id);
        }
      } else {
        rubixSec.style.display = 'none';
      }
    }

    // Rejection info
    const rejSec = el('crm-rejection-section');
    if (a.rejection_scheduled_at && !a.rejection_sent_at) {
      rejSec.style.display = '';
      el('crm-rejection-text').textContent = `Afslag-email sendes ${fmtDate(a.rejection_scheduled_at)} (3-5 dages forsinkelse)`;
      el('crm-cancel-rejection-btn').onclick = () => cancelRejection(a.id);
    } else {
      rejSec.style.display = 'none';
    }

  }

  async function moveStage(appId, newStage) {
    const note = el('crm-stage-note')?.value || '';
    try {
      const res = await api(`applications/${appId}/stage`, {
        method: 'PATCH',
        body:   JSON.stringify({ stage: newStage, note }),
      });
      // Vis faktisk stage (ansat → auto-avancerer til job_pabegyndt)
      const actualStage = res.stage || newStage;
      toast(`Flyttet til ${RZPZ_CRM.stages[actualStage] || actualStage} ✓`);
      activeApp = await api('applications/' + appId);
      renderAppDetail(activeApp);
      const [hist] = await Promise.all([api('applications/' + appId + '/history')]);
      renderHistory(hist);
      await loadApplications();
      renderKanban();
      renderListView();
      loadStats();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  async function transferToRubix(appId) {
    const btn = el('crm-rubix-btn');
    if (btn) { btn.textContent = '⏳ Overfører…'; btn.disabled = true; }
    try {
      const res = await api(`applications/${appId}/rubix-transfer`, { method: 'POST' });
      toast(res.message || 'Overført til Rubix ✓');
      if (btn) { btn.textContent = '✓ Overført til Rubix'; }
      // Genindlæs for at opdatere rubix_synced flag
      activeApp = await api('applications/' + appId);
      renderAppDetail(activeApp);
    } catch(e) {
      toast('Rubix fejl: ' + e.message, 'err');
      if (btn) { btn.textContent = '🔄 Overfør data til Rubix'; btn.disabled = false; }
    }
  }

  async function setRating(appId, rating) {
    try {
      await api(`applications/${appId}`, {
        method: 'PATCH',
        body:   JSON.stringify({ rating }),
      });
      const starsEl = el('crm-stars');
      starsEl.innerHTML = starsHtml(rating);
      starsEl.querySelectorAll('.crm-star').forEach(star => {
        star.addEventListener('click', () => setRating(appId, +star.dataset.r));
      });
    } catch(e) {}
  }

  async function cancelRejection(appId) {
    try {
      await api(`applications/${appId}/rejection`, { method: 'DELETE' });
      toast('Afslag-email annulleret');
      el('crm-rejection-section').style.display = 'none';
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  async function sendComm(appId) {
    const type    = el('crm-comm-type').value;
    const tplId   = +el('crm-template-select').value || 0;
    const subject = el('crm-comm-subject').value.trim();
    const body    = el('crm-comm-body').value.trim();
    if (!body) { toast('Skriv en besked', 'err'); return; }

    const btn = el('crm-send-btn');
    btn.textContent = '⏳ Sender…'; btn.disabled = true;
    try {
      await api(`applications/${appId}/send`, {
        method: 'POST',
        body:   JSON.stringify({ type, template_id: tplId, subject, body }),
      });
      toast('Besked sendt ✓');
      el('crm-comm-body').value    = '';
      el('crm-comm-subject').value = '';
      const comms = await api('applications/' + appId + '/comms');
      renderComms(comms);
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      btn.textContent = 'Send'; btn.disabled = false;
    }
  }

  function renderHistory(hist) {
    const stages = RZPZ_CRM.stages;
    el('crm-history-list').innerHTML = hist.length
      ? hist.map(h => `
          <div class="crm-history-item">
            <div>${escHtml(stages[h.from_stage] || '–')} → <strong>${escHtml(stages[h.to_stage] || h.to_stage)}</strong>
              ${h.display_name ? `<span style="color:var(--crm-muted)"> af ${escHtml(h.display_name)}</span>` : ''}
            </div>
            ${h.note ? `<div style="color:var(--crm-muted);font-size:11px;margin-top:3px">${escHtml(h.note)}</div>` : ''}
            <div class="crm-history-time">${fmtDate(h.created_at)}</div>
          </div>`).join('')
      : '<p style="color:var(--crm-muted);font-size:12px">Ingen historik</p>';
  }

  function renderComms(comms) {
    el('crm-comms-list').innerHTML = comms.length
      ? comms.map(c => `
          <div class="crm-comm-item">
            <div>${c.type === 'sms' ? '💬' : '📧'} <strong>${escHtml(c.subject || 'SMS')}</strong>
              <span style="font-size:10px;color:${c.status==='sent'?'var(--crm-success)':'var(--crm-danger)'}">${c.status}</span>
            </div>
            <div class="crm-comm-time">${fmtDate(c.sent_at)}</div>
          </div>`).join('')
      : '<p style="color:var(--crm-muted);font-size:12px">Ingen beskeder sendt</p>';
  }

  // ── Activity feed ───────────────────────────────────────────────────────────

  async function loadActivityFeed(appId) {
    const feed = el('crm-activity-feed');
    if (!feed) return;
    feed.innerHTML = '<div class="crm-activity-loading">⏳ Henter…</div>';
    try {
      const items = await api('applications/' + appId + '/activity');
      if (!items.length) {
        feed.innerHTML = '<div class="crm-activity-empty">Ingen aktivitet endnu</div>';
        return;
      }

      const stageNames = RZPZ_CRM.stages || {};

      feed.innerHTML = items.map(item => {
        let icon, title, sub = '';

        if (item.type === 'stage') {
          const from = stageNames[item.from_stage] || item.from_stage || 'Start';
          const to   = stageNames[item.to_stage]   || item.to_stage;
          icon  = '🔄';
          title = `Fase ændret: <strong>${escHtml(from)} → ${escHtml(to)}</strong>`;
          if (item.note) sub = `<div class="crm-act-note">${escHtml(item.note)}</div>`;
        } else if (item.type === 'note') {
          icon  = '📝';
          title = 'Note tilføjet';
          sub   = `<div class="crm-act-note">${escHtml(item.note)}</div>`;
        } else if (item.type === 'comm') {
          const typeLabel = item.comm_type === 'sms' ? 'SMS sendt' : 'Email sendt';
          icon  = item.comm_type === 'sms' ? '💬' : '✉';
          title = escHtml(typeLabel) + (item.subject ? `: <em>${escHtml(item.subject)}</em>` : '');
          if (item.status === 'error') title += ' <span class="crm-act-err">⚠ Fejl</span>';
        }

        const timeStr = item.at ? fmtDateTime(item.at) : '';
        const byStr   = item.by && item.by !== 'System' ? item.by : '';

        return `<div class="crm-act-item crm-act-${escHtml(item.type)}">
          <div class="crm-act-icon">${icon}</div>
          <div class="crm-act-body">
            <div class="crm-act-title">${title}</div>
            ${sub}
            <div class="crm-act-meta">
              ${byStr ? `<span class="crm-act-by">${escHtml(byStr)}</span>` : ''}
              ${timeStr ? `<span class="crm-act-time">${escHtml(timeStr)}</span>` : ''}
            </div>
          </div>
        </div>`;
      }).join('');
    } catch(e) {
      feed.innerHTML = '<div class="crm-activity-empty">Kunne ikke hente aktivitet</div>';
    }
  }

  // ── Notes feed ──────────────────────────────────────────────────────────────

  async function loadNotesFeed(appId) {
    const list = el('crm-notes-list');
    if (!list) return;
    list.innerHTML = '<div class="crm-activity-loading">⏳ Henter noter…</div>';
    try {
      const items = await api('applications/' + appId + '/activity');
      const notes = items.filter(i => i.type === 'note');
      if (!notes.length) {
        list.innerHTML = '<div class="crm-activity-empty">Ingen noter endnu</div>';
      } else {
        list.innerHTML = notes.map(n => `
          <div class="crm-note-item">
            <div class="crm-note-text">${escHtml(n.note)}</div>
            <div class="crm-note-meta">
              ${n.by && n.by !== 'System' ? escHtml(n.by) + ' · ' : ''}${fmtDateTime(n.at)}
            </div>
          </div>`).join('');
      }
    } catch(e) {
      list.innerHTML = '<div class="crm-activity-empty">Kunne ikke hente noter</div>';
    }

    // Gem note — fjern evt. tidligere listener ved at erstatte knappen
    const saveBtn = el('crm-note-save-btn');
    if (saveBtn) {
      const newBtn = saveBtn.cloneNode(true);
      saveBtn.parentNode.replaceChild(newBtn, saveBtn);
      newBtn.addEventListener('click', async () => {
        const input = el('crm-note-input');
        const text  = input?.value.trim();
        if (!text) { toast('Skriv en note', 'err'); return; }
        try {
          await api(`applications/${appId}/note`, {
            method: 'POST',
            body: JSON.stringify({ note: text }),
          });
          input.value = '';
          toast('Note gemt ✓');
          loadNotesFeed(appId);
        } catch(e) { toast('Fejl: ' + e.message, 'err'); }
      });
    }
  }

  // ── Stillinger Tab ─────────────────────────────────────────────────────────

  function renderPositionsTab() {
    const grid = el('crm-pos-tab-grid');
    if (!grid) return;

    let filtered = posTabStatusFilter
        ? allPositions.filter(p => p.status === posTabStatusFilter)
        : allPositions;
    if (posTabTypeFilter) {
        filtered = filtered.filter(p => p.job_type === posTabTypeFilter);
    }

    if (!filtered.length) {
        grid.innerHTML = `<div class="crm-pos-empty">
            <span style="font-size:40px">💼</span>
            <p>Ingen stillinger fundet. Opret din første stilling.</p>
        </div>`;
        return;
    }

    grid.innerHTML = filtered.map(p => {
        const total = p.total_applications || 0;
        const ny    = p.count_ny || 0;

        const stagesBar = [
            { key: 'ny',            label: 'Ny',          count: p.count_ny            || 0, color: 'var(--crm-blue)' },
            { key: 'screening',     label: 'Screening',   count: p.count_screening     || 0, color: 'var(--crm-orange)' },
            { key: 'samtale',       label: 'Samtale',     count: p.count_samtale       || 0, color: '#a78bfa' },
            { key: 'tilbud',        label: 'Tilbud',      count: p.count_tilbud        || 0, color: 'var(--crm-neon)' },
            { key: 'ansat',         label: 'Ansat',       count: p.count_ansat         || 0, color: 'var(--crm-success)' },
            { key: 'job_pabegyndt', label: 'Påbegyndt',   count: p.count_job_pabegyndt || 0, color: '#34d399' },
            { key: 'afslag',        label: 'Afslag',      count: p.count_afslag        || 0, color: 'var(--crm-danger)' },
        ].filter(s => s.count > 0);

        const stagePills = stagesBar.map(s =>
            `<span class="crm-pos-stage-pill" style="background:${s.color}18;color:${s.color};border:1px solid ${s.color}40">${s.label} ${s.count}</span>`
        ).join('');

        const statusColor = { open: 'var(--crm-success)', draft: 'var(--crm-orange)', closed: 'var(--crm-muted)' }[p.status] || 'var(--crm-muted)';
        const statusLabel = { open: '● Aktiv', draft: '◐ Kladde', closed: '○ Lukket' }[p.status] || p.status;
        const jobTypes    = RZPZ_CRM.jobTypes || {};
        const typeLabel   = p.job_type ? (jobTypes[p.job_type] || p.job_type) : null;

        return `<div class="crm-pos-card" data-pos-id="${p.id}" data-job-type="${escHtml(p.job_type||'')}">
            <div class="crm-pos-card-header">
                <div class="crm-pos-card-info">
                    ${typeLabel ? `<div class="crm-pos-type-badge">${escHtml(typeLabel)}</div>` : ''}
                    <div class="crm-pos-card-title">${escHtml(p.title)}</div>
                    <div class="crm-pos-card-meta">
                        ${p.department ? escHtml(p.department) : ''}
                        ${p.location ? '<span class="crm-pos-sep">·</span>' + escHtml(p.location) : ''}
                    </div>
                </div>
                <div class="crm-pos-card-status" style="color:${statusColor}">${statusLabel}</div>
            </div>
            <div class="crm-pos-card-stats">
                <div class="crm-pos-stat">
                    <span class="crm-pos-stat-val">${total}</span>
                    <span class="crm-pos-stat-label">Ansøgninger</span>
                </div>
                <div class="crm-pos-stat">
                    <span class="crm-pos-stat-val" style="color:var(--crm-blue)">${ny}</span>
                    <span class="crm-pos-stat-label">Nye</span>
                </div>
                <div class="crm-pos-stat crm-pos-stat-pipeline">
                    ${stagePills || '<span style="color:var(--crm-muted);font-size:11px">Ingen ansøgninger endnu</span>'}
                </div>
            </div>
            <div class="crm-pos-card-footer">
                <span class="crm-pos-card-date">Oprettet ${fmtDate(p.created_at)}</span>
                <div class="crm-pos-card-btns">
                    ${p.source_url ? `<a href="${escHtml(p.source_url)}" target="_blank" class="crm-btn crm-btn-ghost crm-btn-xs">🔗 Opslag</a>` : ''}
                    <button class="crm-btn crm-btn-ghost crm-btn-xs" data-pos-edit="${p.id}">✏ Rediger</button>
                    <button class="crm-btn crm-btn-primary crm-btn-xs" data-pos-open="${p.id}">Se kandidater →</button>
                </div>
            </div>
        </div>`;
    }).join('');

    // Bind click handlers
    qsa('[data-pos-open]', grid).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            openPositionDetail(+btn.dataset.posOpen);
        });
    });
    qsa('[data-pos-edit]', grid).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            editPosition(+btn.dataset.posEdit);
            openModal('crm-positions-modal');
        });
    });
    // Click card itself → open detail
    qsa('.crm-pos-card', grid).forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('[data-pos-edit]') || e.target.closest('[data-pos-open]') || e.target.closest('a')) return;
            openPositionDetail(+card.dataset.posId);
        });
    });
  }

  function initPositionsTabHandlers() {
    // New position button in tab
    el('crm-pos-tab-new-btn')?.addEventListener('click', () => {
        editingPosId = null;
        el('crm-pos-form-title').textContent = 'Ny stilling';
        ['pos-title','pos-dept','pos-location','pos-url'].forEach(id => { el(id).value = ''; });
        el('pos-desc').value   = '';
        el('pos-status').value = 'open';
        if (el('pos-job-type')) el('pos-job-type').value = '';
        el('crm-position-form').style.display = '';
        openModal('crm-positions-modal');
    });

    // Back button
    el('crm-pos-back-btn')?.addEventListener('click', backToPositionsList);

    // Status filter buttons
    qsa('[data-pos-status]').forEach(btn => {
        btn.addEventListener('click', () => {
            posTabStatusFilter = btn.dataset.posStatus;
            qsa('[data-pos-status]').forEach(b => b.classList.toggle('crm-pos-filter-active', b === btn));
            renderPositionsTab();
        });
    });

    // Job type filter buttons
    qsa('[data-pos-type]').forEach(btn => {
        btn.addEventListener('click', () => {
            posTabTypeFilter = btn.dataset.posType;
            qsa('[data-pos-type]').forEach(b => b.classList.toggle('crm-pos-type-active', b === btn));
            renderPositionsTab();
        });
    });
  }

  async function openPositionDetail(posId) {
    activePositionId = posId;
    posDetailStage   = '';
    posDetailFolder  = null;
    positiveFolders  = [];
    el('crm-pos-tab-list').style.display   = 'none';
    el('crm-pos-tab-detail').style.display = '';

    // Indlæs ALLE ansøgninger for denne stilling (ignorer evt. aktive filtre)
    try {
      const apps = await api('applications?position_id=' + posId + '&_limit=500');
      // Merge ind i allApplications uden at miste andre
      const otherApps = allApplications.filter(a => +a.position_id !== posId);
      allApplications = [...otherApps, ...apps];
    } catch(e) { /* brug eksisterende allApplications som fallback */ }

    try {
      positiveFolders = await api('positions/' + posId + '/folders');
    } catch(e) { positiveFolders = []; }

    renderPositionDetailView();
  }

  function backToPositionsList() {
    activePositionId = null;
    el('crm-pos-tab-list').style.display   = '';
    el('crm-pos-tab-detail').style.display = 'none';
  }

  function renderPositionDetailView() {
    const pos = allPositions.find(p => +p.id === activePositionId);
    if (!pos) return;

    // Header
    const statusColor = { open: 'var(--crm-success)', draft: 'var(--crm-orange)', closed: 'var(--crm-muted)' }[pos.status] || 'var(--crm-muted)';
    el('crm-pos-detail-title-wrap').innerHTML = `
        <div class="crm-pos-detail-name">${escHtml(pos.title)}</div>
        <div class="crm-pos-detail-sub">
            ${pos.department ? escHtml(pos.department) : ''}
            ${pos.location ? ' · ' + escHtml(pos.location) : ''}
            <span style="margin-left:8px;color:${statusColor}">${pos.status === 'open' ? '● Aktiv' : pos.status === 'draft' ? '◐ Kladde' : '○ Lukket'}</span>
        </div>`;

    el('crm-pos-detail-actions').innerHTML = `
        ${pos.source_url ? `<a href="${escHtml(pos.source_url)}" target="_blank" class="crm-btn crm-btn-ghost crm-btn-sm">🔗 Se jobopslag</a>` : ''}
        <button class="crm-btn crm-btn-ghost crm-btn-sm" id="crm-pos-edit-from-detail">✏ Rediger stilling</button>`;

    el('crm-pos-edit-from-detail')?.addEventListener('click', () => {
        editPosition(activePositionId);
        openModal('crm-positions-modal');
    });

    // Get apps for this position
    const posApps = allApplications.filter(a => +a.position_id === activePositionId);

    // Stage counts for sidebar
    const stages = [
        { key: '',              label: 'Alle',        count: posApps.length },
        { key: 'ny',            label: 'Nye',         count: posApps.filter(a => a.stage === 'ny').length },
        { key: 'screening',     label: 'Screening',   count: posApps.filter(a => a.stage === 'screening').length },
        { key: 'samtale',       label: 'Samtale',     count: posApps.filter(a => a.stage === 'samtale').length },
        { key: 'tilbud',        label: 'Tilbud',      count: posApps.filter(a => a.stage === 'tilbud').length },
        { key: 'ansat',         label: 'Ansat',       count: posApps.filter(a => a.stage === 'ansat').length },
        { key: 'job_pabegyndt', label: 'Job påbegyndt', count: posApps.filter(a => a.stage === 'job_pabegyndt').length },
        { key: 'afslag',        label: 'Afslag',      count: posApps.filter(a => a.stage === 'afslag').length },
    ];

    // Sidebar — stages section
    const foldersSection = `
<div class="crm-pos-sidebar-section" style="margin-top:12px">
    <div class="crm-pos-sidebar-heading" style="display:flex;align-items:center;justify-content:space-between">
        <span>Mapper</span>
        <button class="crm-pos-folder-add-btn" id="crm-pos-folder-add-btn" title="Opret ny mappe">+</button>
    </div>
    <div id="crm-pos-folder-new" style="display:none;padding:6px 0">
        <input type="text" id="crm-pos-folder-name-input" class="crm-input crm-input-sm" placeholder="Mappenavn…" style="width:100%;margin-bottom:6px">
        <div style="display:flex;gap:6px">
            <button class="crm-btn crm-btn-primary crm-btn-xs" id="crm-pos-folder-save-btn">Gem</button>
            <button class="crm-btn crm-btn-ghost crm-btn-xs" id="crm-pos-folder-cancel-btn">Annuller</button>
        </div>
    </div>
    ${positiveFolders.length ? positiveFolders.map(f => `
        <button class="crm-pos-sidebar-btn crm-pos-folder-btn${posDetailFolder === f.id ? ' active' : ''}" data-folder-id="${f.id}">
            <span>📁 ${escHtml(f.name)}</span>
            <div style="display:flex;align-items:center;gap:4px">
                <span class="crm-pos-sidebar-count">${f.app_count}</span>
                <button class="crm-pos-folder-del-btn" data-del-folder="${f.id}" title="Slet mappe" style="background:none;border:none;color:var(--crm-muted);cursor:pointer;padding:0 2px;font-size:12px;line-height:1">×</button>
            </div>
        </button>`).join('') : '<div style="color:var(--crm-muted);font-size:12px;padding:6px 10px">Ingen mapper endnu</div>'}
</div>`;

    el('crm-pos-sidebar').innerHTML = `
        <div class="crm-pos-sidebar-section">
            <div class="crm-pos-sidebar-heading">Kandidater</div>
            ${stages.map(s => `
                <button class="crm-pos-sidebar-btn${posDetailStage === s.key && !posDetailFolder ? ' active' : ''}" data-detail-stage="${s.key}">
                    <span>${escHtml(s.label)}</span>
                    <span class="crm-pos-sidebar-count">${s.count}</span>
                </button>`).join('')}
        </div>` + foldersSection;

    qsa('[data-detail-stage]', el('crm-pos-sidebar')).forEach(btn => {
        btn.addEventListener('click', () => {
            posDetailStage  = btn.dataset.detailStage;
            posDetailFolder = null;
            renderPositionDetailView();
        });
    });

    // Folder filter buttons
    qsa('[data-folder-id]', el('crm-pos-sidebar')).forEach(btn => {
        btn.addEventListener('click', e => {
            if (e.target.closest('[data-del-folder]')) return;
            posDetailFolder = posDetailFolder === +btn.dataset.folderId ? null : +btn.dataset.folderId;
            posDetailStage  = '';  // clear stage filter when folder selected
            renderPositionDetailView();
        });
    });
    // Delete folder
    qsa('[data-del-folder]', el('crm-pos-sidebar')).forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            if (!confirm('Slet mappe? Ansøgere forbliver i stillingen.')) return;
            await api('folders/' + btn.dataset.delFolder, {method:'DELETE'});
            positiveFolders = await api('positions/' + activePositionId + '/folders');
            if (posDetailFolder === +btn.dataset.delFolder) posDetailFolder = null;
            renderPositionDetailView();
        });
    });
    // New folder toggle
    el('crm-pos-folder-add-btn')?.addEventListener('click', () => {
        const d = el('crm-pos-folder-new');
        if (d) { d.style.display = d.style.display === 'none' ? '' : 'none'; }
        el('crm-pos-folder-name-input')?.focus();
    });
    el('crm-pos-folder-cancel-btn')?.addEventListener('click', () => {
        if (el('crm-pos-folder-new')) el('crm-pos-folder-new').style.display = 'none';
    });
    el('crm-pos-folder-save-btn')?.addEventListener('click', async () => {
        const input = el('crm-pos-folder-name-input');
        const name  = input?.value.trim();
        if (!name) { toast('Skriv et mappenavn', 'err'); return; }
        try {
            await api('positions/' + activePositionId + '/folders', {method:'POST', body: JSON.stringify({name})});
            positiveFolders = await api('positions/' + activePositionId + '/folders');
            if (input) input.value = '';
            if (el('crm-pos-folder-new')) el('crm-pos-folder-new').style.display = 'none';
            toast('Mappe oprettet ✓');
            renderPositionDetailView();
        } catch(e) { toast('Fejl: '+e.message, 'err'); }
    });

    // Filter apps
    let visibleApps = posApps;
    if (posDetailFolder) {
        visibleApps = posApps.filter(a => +a.folder_id === posDetailFolder);
    } else if (posDetailStage) {
        visibleApps = posApps.filter(a => a.stage === posDetailStage);
    }

    // Candidates list
    if (!visibleApps.length) {
        el('crm-pos-candidates').innerHTML = `<div class="crm-pos-empty crm-pos-cand-empty">
            <span style="font-size:32px">🔍</span>
            <p>${posDetailStage ? 'Ingen kandidater i denne fase' : 'Ingen ansøgninger til denne stilling endnu'}</p>
        </div>`;
        return;
    }

    el('crm-pos-candidates').innerHTML = `
        <div class="crm-pos-cand-toolbar">
            <span class="crm-pos-cand-count">${visibleApps.length} kandidat${visibleApps.length !== 1 ? 'er' : ''}</span>
        </div>
        <div class="crm-cand-table-wrap">
        <table class="crm-cand-table">
          <thead>
            <tr>
              <th class="crm-cth-avatar"></th>
              <th class="crm-cth-name">Navn</th>
              <th class="crm-cth-phone">Telefon</th>
              <th class="crm-cth-birth">Fødselsdag</th>
              <th class="crm-cth-start">Start dato</th>
              <th class="crm-cth-score">Kundeservice</th>
              <th class="crm-cth-score">Outbound</th>
              <th class="crm-cth-score">Logisk test</th>
              <th class="crm-cth-video">Video</th>
              <th class="crm-cth-stage">Status</th>
              <th class="crm-cth-rating">★</th>
              <th class="crm-cth-actions"></th>
            </tr>
          </thead>
          <tbody>
            ${visibleApps.map(a => {
                const initials = ((a.first_name || '?')[0] + (a.last_name || '?')[0]).toUpperCase();
                const avatarBg = ['#5b8dee','#ff9800','#a78bfa','#34d399','#f472b6','#CCFF00'][a.id % 6];
                const avatarHtml = a.photo_url
                    ? `<img src="${escHtml(a.photo_url)}" class="crm-cand-avatar crm-cand-avatar-img" alt="">`
                    : `<div class="crm-cand-avatar" style="background:${avatarBg}">${escHtml(initials)}</div>`;
                const aon = parseAonScores(a.aon_result_json);
                const scoreCell = v => v != null ? `<span class="crm-score-badge">${escHtml(String(v))}</span>` : '<span class="crm-score-dash">—</span>';
                return `<tr class="crm-cand-row" data-app-id="${a.id}">
                    <td class="crm-ctd-avatar">${avatarHtml}</td>
                    <td class="crm-ctd-name">
                        <div class="crm-cand-name">${escHtml(a.first_name)} ${escHtml(a.last_name)}</div>
                        <div class="crm-cand-email">${escHtml(a.email || '')}</div>
                    </td>
                    <td class="crm-ctd-phone">${a.phone ? `<a href="tel:${escHtml(a.phone)}" class="crm-link">${escHtml(a.phone)}</a>` : '—'}</td>
                    <td class="crm-ctd-birth">${fmtDateShort(a.birthdate)}</td>
                    <td class="crm-ctd-start">${fmtDateShort(a.availability)}</td>
                    <td class="crm-ctd-score">${scoreCell(aon.kundeservice)}</td>
                    <td class="crm-ctd-score">${scoreCell(aon.outbound)}</td>
                    <td class="crm-ctd-score">${scoreCell(aon.logisk)}</td>
                    <td class="crm-ctd-video">${a.video_url ? `<a href="${escHtml(a.video_url)}" target="_blank" class="crm-video-icon" title="Se video">🎥</a>` : '<span class="crm-score-dash">—</span>'}</td>
                    <td class="crm-ctd-stage">${stagePill(a.stage)}</td>
                    <td class="crm-ctd-rating"><div class="crm-cand-rating">${starsHtml(a.rating)}</div></td>
                    <td class="crm-ctd-actions">
                        <div class="crm-cand-actions-wrap">
                        ${positiveFolders.length ? `
                        <select class="crm-cand-folder-sel crm-select-xs" data-app-folder-id="${a.id}" title="Flyt til mappe">
                            <option value="">📁 Mappe…</option>
                            ${positiveFolders.map(f => `<option value="${f.id}"${+a.folder_id===f.id?' selected':''}>${escHtml(f.name)}</option>`).join('')}
                            ${a.folder_id ? `<option value="__remove">↩ Fjern fra mappe</option>` : ''}
                        </select>` : ''}
                        <button class="crm-btn crm-btn-ghost crm-btn-xs crm-cand-open-btn" data-app-id="${a.id}">Åbn →</button>
                        </div>
                    </td>
                </tr>`;
            }).join('')}
          </tbody>
        </table>
        </div>`;

    // Bind open buttons — ignore clicks originating from interactive elements
    qsa('[data-app-id]', el('crm-pos-candidates')).forEach(el2 => {
        el2.addEventListener('click', (e) => {
            if (e.target.closest('select, input, a, label')) return;
            openAppDetail(+el2.dataset.appId);
        });
    });

    // Bind folder selects
    qsa('[data-app-folder-id]', el('crm-pos-candidates')).forEach(sel => {
        sel.addEventListener('change', async () => {
            const appId    = +sel.dataset.appFolderId;
            const folderId = sel.value === '__remove' ? null : (+sel.value || null);
            try {
                await api('applications/' + appId + '/folder', {method:'PATCH', body: JSON.stringify({folder_id: folderId})});
                // Update local state
                const app = allApplications.find(a => a.id === appId);
                if (app) app.folder_id = folderId;
                positiveFolders = await api('positions/' + activePositionId + '/folders');
                toast(folderId ? 'Tilføjet til mappe ✓' : 'Fjernet fra mappe');
                renderPositionDetailView();
            } catch(e) { toast('Fejl: '+e.message,'err'); }
        });
    });
  }

  // ── Positions modal ─────────────────────────────────────────────────────────
  function initPositionModal() {
    el('crm-positions-btn').addEventListener('click', () => {
      renderPositionsList();
      el('crm-position-form').style.display = 'none';
      openModal('crm-positions-modal');
    });

    el('crm-new-position-btn').addEventListener('click', () => {
      editingPosId = null;
      el('crm-pos-form-title').textContent = 'Ny stilling';
      ['pos-title','pos-dept','pos-location','pos-url'].forEach(id => { el(id).value = ''; });
      el('pos-desc').value   = '';
      el('pos-status').value = 'open';
      el('crm-position-form').style.display = '';
    });

    el('crm-pos-cancel-btn').addEventListener('click', () => {
      el('crm-position-form').style.display = 'none';
    });

    el('crm-pos-save-btn').addEventListener('click', savePosition);
  }

  function renderPositionsList() {
    el('crm-positions-list').innerHTML = allPositions.length
      ? allPositions.map(p => `
          <div class="crm-pos-item">
            <div>
              <div class="crm-pos-title">${escHtml(p.title)}</div>
              <div class="crm-pos-meta">${escHtml(p.department || '')} ${p.location ? '· ' + escHtml(p.location) : ''}</div>
            </div>
            <div class="crm-pos-actions">
              <span class="crm-pos-status-${p.status}">${p.status}</span>
              <button class="crm-btn crm-btn-ghost" style="padding:4px 10px;font-size:11px" data-edit-pos="${p.id}">Rediger</button>
            </div>
          </div>`).join('')
      : '<p style="color:var(--crm-muted)">Ingen stillinger oprettet endnu</p>';

    qsa('[data-edit-pos]').forEach(btn => {
      btn.addEventListener('click', () => editPosition(+btn.dataset.editPos));
    });
  }

  function editPosition(id) {
    const pos = allPositions.find(p => p.id === id);
    if (!pos) return;
    editingPosId = id;
    el('crm-pos-form-title').textContent = 'Rediger stilling';
    if (el('pos-job-type')) el('pos-job-type').value = pos.job_type || '';
    el('pos-title').value    = pos.title || '';
    el('pos-dept').value     = pos.department || '';
    el('pos-location').value = pos.location || '';
    el('pos-url').value      = pos.source_url || '';
    el('pos-desc').value     = pos.description || '';
    el('pos-status').value   = pos.status || 'open';
    el('crm-position-form').style.display = '';
  }

  async function savePosition() {
    const payload = {
      id:          editingPosId || undefined,
      job_type:    el('pos-job-type')?.value || '',
      title:       el('pos-title').value.trim(),
      department:  el('pos-dept').value.trim(),
      location:    el('pos-location').value.trim(),
      source_url:  el('pos-url').value.trim(),
      description: el('pos-desc').value.trim(),
      status:      el('pos-status').value,
    };
    if (!payload.title) { toast('Udfyld jobtitel', 'err'); return; }

    const btn = el('crm-pos-save-btn');
    btn.textContent = '⏳ Gemmer…';
    try {
      if (editingPosId) {
        await api('positions/' + editingPosId, { method: 'PUT', body: JSON.stringify(payload) });
      } else {
        await api('positions', { method: 'POST', body: JSON.stringify(payload) });
      }
      toast('Stilling gemt ✓');
      el('crm-position-form').style.display = 'none';
      await loadPositions();
      renderPositionsList();
      renderPositionsTab();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      btn.textContent = 'Gem stilling';
    }
  }

  // ── Templates modal ─────────────────────────────────────────────────────────
  function initTemplatesModal() {
    el('crm-templates-btn').addEventListener('click', () => {
      renderTemplatesList();
      openModal('crm-templates-modal');
    });

    el('crm-tpl-new-btn').addEventListener('click', () => {
      editingTplId = null;
      el('crm-tpl-form-title').textContent = 'Ny skabelon';
      el('tpl-name').value    = '';
      el('tpl-type').value    = 'email';
      el('tpl-trigger').value = 'manual';
      el('tpl-subject').value = '';
      el('tpl-body').value    = '';
    });

    el('tpl-type').addEventListener('change', () => {
      el('tpl-subject-field').style.display = el('tpl-type').value === 'email' ? '' : 'none';
    });

    el('crm-tpl-save-btn').addEventListener('click', saveTemplate);
  }

  function renderTemplatesList() {
    const list = el('crm-templates-list');
    list.innerHTML = allTemplates.length
      ? allTemplates.map(t => `
          <div class="crm-tpl-item" data-tpl-id="${t.id}">
            <span class="crm-tpl-item-name">${escHtml(t.name)}</span>
            <span class="crm-tpl-badge">${t.type}</span>
            <button class="crm-tpl-del" data-del-tpl="${t.id}" title="Slet">✕</button>
          </div>`).join('')
      : '<p style="color:var(--crm-muted);font-size:13px">Ingen skabeloner</p>';

    qsa('[data-tpl-id]').forEach(item => {
      item.addEventListener('click', e => {
        if (e.target.closest('[data-del-tpl]')) return;
        loadTemplateIntoEditor(+item.dataset.tplId);
      });
    });
    qsa('[data-del-tpl]').forEach(btn => {
      btn.addEventListener('click', () => deleteTemplate(+btn.dataset.delTpl));
    });
  }

  function loadTemplateIntoEditor(id) {
    const tpl = allTemplates.find(t => t.id == id); // loose == : DB returns string IDs
    if (!tpl) return;
    editingTplId = id;
    el('crm-tpl-form-title').textContent = '✏️ ' + (tpl.name || 'Rediger skabelon');
    el('tpl-name').value    = tpl.name    || '';
    el('tpl-type').value    = tpl.type    || 'email';
    el('tpl-trigger').value = tpl.trigger || 'manual';
    el('tpl-subject').value = tpl.subject || '';
    el('tpl-body').value    = tpl.body    || '';
    el('tpl-subject-field').style.display = tpl.type === 'email' ? '' : 'none';
    qsa('.crm-tpl-item').forEach(i => i.classList.toggle('active', +i.dataset.tplId === id));
  }

  async function saveTemplate() {
    const payload = {
      id:      editingTplId || undefined,
      name:    el('tpl-name').value.trim(),
      type:    el('tpl-type').value,
      trigger: el('tpl-trigger').value,
      subject: el('tpl-subject').value.trim(),
      body:    el('tpl-body').value.trim(),
    };
    if (!payload.name || !payload.body) { toast('Udfyld navn og indhold', 'err'); return; }

    const btn = el('crm-tpl-save-btn');
    btn.textContent = '⏳ Gemmer…';
    try {
      const method = editingTplId ? 'PUT' : 'POST';
      const endpoint = editingTplId ? 'templates/' + editingTplId : 'templates';
      const res = await api(endpoint, { method, body: JSON.stringify(payload) });
      const savedId = res?.id || editingTplId;
      toast('Skabelon gemt ✓');
      await loadTemplates();
      renderTemplatesList();
      if (savedId) loadTemplateIntoEditor(savedId); // keep selection after save
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      btn.textContent = 'Gem skabelon';
    }
  }

  async function deleteTemplate(id) {
    if (!confirm('Slet skabelon?')) return;
    try {
      await api('templates/' + id, { method: 'DELETE' });
      toast('Slettet');
      await loadTemplates();
      renderTemplatesList();
    } catch(e) {
      toast('Fejl', 'err');
    }
  }

  // ── Start ────────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', boot);

})();
