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
  let dragId          = null;

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

  // ── Boot ────────────────────────────────────────────────────────────────────
  async function boot() {
    await Promise.all([loadPositions(), loadApplications(), loadTemplates()]);
    renderKanban();
    renderListView();
    loadStats();
    initFilters();
    initTabs();
    initModals();
    initPositionModal();
    initTemplatesModal();
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

    const opts = allPositions.filter(p => p.status === 'open').map(p =>
      `<option value="${p.id}">${escHtml(p.title)}</option>`
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

      // Drag-over events
      const cardsDiv = col.querySelector('.crm-cards');
      cardsDiv.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
      cardsDiv.addEventListener('dragleave', () => col.classList.remove('drag-over'));
      cardsDiv.addEventListener('drop', e => { e.preventDefault(); col.classList.remove('drag-over'); handleDrop(stage); });
    });

    // Card click + drag events
    qsa('.crm-card').forEach(card => {
      card.addEventListener('click', () => openAppDetail(+card.dataset.id));
      card.addEventListener('dragstart', () => { dragId = +card.dataset.id; card.classList.add('dragging'); });
      card.addEventListener('dragend',   () => card.classList.remove('dragging'));
    });
  }

  function cardHtml(a) {
    const rejBadge = a.rejection_scheduled_at && !a.rejection_sent_at
      ? `<div class="crm-card-rejection">⏳ Afslag-email ${fmtDate(a.rejection_scheduled_at)}</div>`
      : '';
    return `<div class="crm-card" data-id="${a.id}" draggable="true">
      <div class="crm-card-name">${escHtml(a.first_name)} ${escHtml(a.last_name)}</div>
      <div class="crm-card-pos">${escHtml(a.position_title || '')}</div>
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
    // Meta info
    el('crm-detail-meta').innerHTML = `
      <div><strong>Navn:</strong> ${escHtml(a.first_name)} ${escHtml(a.last_name)}</div>
      <div><strong>Email:</strong> <a href="mailto:${escHtml(a.email)}" style="color:var(--crm-neon)">${escHtml(a.email)}</a></div>
      ${a.phone ? `<div><strong>Tlf:</strong> ${escHtml(a.phone)}</div>` : ''}
      ${a.city  ? `<div><strong>By:</strong> ${escHtml(a.city)}</div>` : ''}
      <div><strong>Stilling:</strong> ${escHtml(a.position_title || '–')}</div>
      <div><strong>Kilde:</strong> ${escHtml(sourceLabel(a.source))}</div>
      <div><strong>Oprettet:</strong> ${fmtDate(a.created_at)}</div>
      ${a.cv_url ? `<div><strong>CV:</strong> <a href="${escHtml(a.cv_url)}" target="_blank" style="color:var(--crm-neon)">Åbn CV</a></div>` : ''}
    `;

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

    // Rejection info
    const rejSec = el('crm-rejection-section');
    if (a.rejection_scheduled_at && !a.rejection_sent_at) {
      rejSec.style.display = '';
      el('crm-rejection-text').textContent = `Afslag-email sendes ${fmtDate(a.rejection_scheduled_at)} (3-5 dages forsinkelse)`;
      el('crm-cancel-rejection-btn').onclick = () => cancelRejection(a.id);
    } else {
      rejSec.style.display = 'none';
    }

    // Populate template select
    const tplSel = el('crm-template-select');
    tplSel.innerHTML = '<option value="">— Vælg skabelon eller skriv manuelt —</option>' +
      allTemplates.map(t => `<option value="${t.id}">${escHtml(t.name)} (${t.type})</option>`).join('');
    tplSel.onchange = () => {
      const tpl = allTemplates.find(t => t.id == tplSel.value);
      if (tpl) {
        el('crm-comm-subject').value = tpl.subject || '';
        el('crm-comm-body').value    = tpl.body    || '';
      }
    };

    // Send button
    el('crm-send-btn').onclick = () => sendComm(a.id);
  }

  async function moveStage(appId, newStage) {
    const note = el('crm-stage-note')?.value || '';
    try {
      await api(`applications/${appId}/stage`, {
        method: 'PATCH',
        body:   JSON.stringify({ stage: newStage, note }),
      });
      toast(`Flyttet til ${RZPZ_CRM.stages[newStage] || newStage} ✓`);
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
