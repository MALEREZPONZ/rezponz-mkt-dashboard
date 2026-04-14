/* =========================================================
   RezCRM Form Builder — rezcrm-form-builder.js  v3.3.0
   ========================================================= */
(function ($) {
  'use strict';

  const API   = RZPZ_FB.apiBase;
  const NONCE = RZPZ_FB.nonce;

  // ── State ───────────────────────────────────────────────────────────────────
  let currentFormId  = null;
  let fields         = [];      // current form's fields (array of objects)
  let selectedIdx    = null;    // index of field being configured

  // ── Helpers ─────────────────────────────────────────────────────────────────
  const el  = id => document.getElementById(id);
  const qs  = (s, c) => (c || document).querySelector(s);
  const qsa = (s, c) => [...(c || document).querySelectorAll(s)];

  async function api(path, opts = {}) {
    const res  = await fetch(API + path, {
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      ...opts,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'API fejl ' + res.status);
    return data;
  }

  function toast(msg, type = 'ok') {
    const t = el('fb-toast');
    t.textContent = msg;
    t.className   = 'crm-toast show ' + type;
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.className = 'crm-toast'; }, 3500);
  }

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Boot ────────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    // New form
    el('fb-new-form-btn')?.addEventListener('click', createNewForm);

    // Edit fields
    document.addEventListener('click', function(e) {
      const editBtn = e.target.closest('.fb-edit-btn');
      if (editBtn) openBuilder(+editBtn.dataset.id);

      const settingsBtn = e.target.closest('.fb-settings-btn');
      if (settingsBtn) openSettings(+settingsBtn.dataset.id);

      const statsBtn = e.target.closest('.fb-stats-btn');
      if (statsBtn) openStats(+statsBtn.dataset.id);
    });

    el('fb-back-btn')?.addEventListener('click', () => {
      el('fb-builder-view').style.display = 'none';
      el('fb-forms-view').style.display   = '';
    });

    el('fb-save-fields-btn')?.addEventListener('click', saveFields);
    el('fb-save-settings-btn')?.addEventListener('click', saveSettings);

    // Close modals
    qsa('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        el(btn.dataset.close).style.display = 'none';
        el('crm-backdrop').style.display    = 'none';
      });
    });
    el('crm-backdrop')?.addEventListener('click', () => {
      qsa('.crm-modal').forEach(m => m.style.display = 'none');
      el('crm-backdrop').style.display = 'none';
    });

    // Palette: add field on click
    qsa('.fb-palette-btn').forEach(btn => {
      btn.addEventListener('click', () => addField(btn.dataset.type));
    });
  });

  // ── Create new form ──────────────────────────────────────────────────────────
  async function createNewForm() {
    const title = prompt('Formular-titel (fx "Ansøgning – kundeservice"):');
    if (!title) return;
    try {
      const res = await api('crm/forms', {
        method: 'POST',
        body:   JSON.stringify({ title, slug: title.toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9-]/g,''), is_active: 1, show_progress: 1, multi_step: 1 }),
      });
      toast('Formular oprettet ✓');
      await openBuilder(res.id);
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  // ── Open builder ─────────────────────────────────────────────────────────────
  async function openBuilder(formId) {
    currentFormId = formId;
    const form    = await api('crm/forms/' + formId);
    fields        = await api('crm/forms/' + formId + '/fields');

    // Normalise options (stored as JSON string → array)
    fields = fields.map(f => ({
      ...f,
      options: f.options ? (typeof f.options === 'string' ? JSON.parse(f.options) : f.options) : [],
    }));

    el('fb-builder-title').textContent = 'Rediger: ' + form.title;
    el('fb-forms-view').style.display  = 'none';
    el('fb-builder-view').style.display = '';

    renderCanvas();
    setupSortable();
    renderConfig(null); // clear config
  }

  // ── Canvas rendering ─────────────────────────────────────────────────────────
  function renderCanvas() {
    const canvas = el('fb-canvas');
    const empty  = el('fb-canvas-empty');

    canvas.innerHTML = '';
    canvas.appendChild(empty);
    empty.style.display = fields.length ? 'none' : '';

    fields.forEach((f, idx) => {
      const item = document.createElement('div');
      item.className   = 'fb-field-item' + (f.field_type === 'section' ? ' fb-section-item' : '') + (idx === selectedIdx ? ' active' : '');
      item.dataset.idx = idx;
      item.innerHTML   = `
        <span class="fb-field-drag" title="Træk for at flytte">⠿</span>
        <span class="fb-field-type-badge">${escHtml(RZPZ_FB.fieldTypes[f.field_type] || f.field_type)}</span>
        <span class="fb-field-label">${escHtml(f.label || '(ingen label)')}</span>
        ${f.required ? '<span class="fb-field-required">*</span>' : ''}
        <button class="fb-field-del" data-idx="${idx}" title="Slet felt">✕</button>`;

      item.addEventListener('click', e => {
        if (e.target.closest('.fb-field-del')) return;
        selectField(idx);
      });

      item.querySelector('.fb-field-del').addEventListener('click', e => {
        e.stopPropagation();
        fields.splice(idx, 1);
        if (selectedIdx === idx) { selectedIdx = null; renderConfig(null); }
        else if (selectedIdx > idx) selectedIdx--;
        renderCanvas();
        setupSortable();
        updateFieldCount();
      });

      canvas.appendChild(item);
    });

    updateFieldCount();
  }

  function updateFieldCount() {
    el('fb-field-count').textContent = fields.length + ' felt' + (fields.length !== 1 ? 'er' : '');
  }

  function setupSortable() {
    $('#fb-canvas').sortable({
      handle: '.fb-field-drag',
      placeholder: 'fb-field-item ui-sortable-placeholder',
      update: function(event, ui) {
        const newOrder = [];
        qsa('.fb-field-item[data-idx]').forEach(item => {
          newOrder.push(fields[+item.dataset.idx]);
        });
        fields = newOrder;
        renderCanvas();
        setupSortable();
      },
    });
  }

  // ── Add field ────────────────────────────────────────────────────────────────
  function addField(type) {
    const defaults = {
      field_type: type,
      label:      RZPZ_FB.fieldTypes[type] || type,
      field_key:  type + '_' + Date.now(),
      required:   type === 'email' || type === 'first_name' ? 1 : 0,
      options:    ['radio','checkbox','select'].includes(type) ? ['Mulighed 1','Mulighed 2'] : [],
      core_map:   '',
      placeholder:'',
      help_text:  '',
      section_name: type === 'section' ? 'Ny sektion' : '',
    };
    fields.push(defaults);
    renderCanvas();
    setupSortable();
    selectField(fields.length - 1);
    // Scroll to bottom
    el('fb-canvas').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // ── Select + configure field ─────────────────────────────────────────────────
  function selectField(idx) {
    selectedIdx = idx;
    renderCanvas();
    renderConfig(fields[idx]);
  }

  function renderConfig(field) {
    const panel = el('fb-config-inner');
    if (!field) {
      panel.innerHTML = '<p style="color:var(--crm-muted);font-size:13px">Klik på et felt for at redigere det</p>';
      return;
    }

    const isSection    = field.field_type === 'section';
    const hasOptions   = ['radio','checkbox','select','yes_no'].includes(field.field_type);
    const hasPlaceholder = ['text','textarea','email','phone'].includes(field.field_type);
    const coreMapOpts  = Object.entries(RZPZ_FB.coreMap).map(([k,v]) =>
      `<option value="${k}" ${field.core_map === k ? 'selected' : ''}>${escHtml(v)}</option>`
    ).join('');

    panel.innerHTML = `
      ${isSection ? `
        <div class="fb-config-field">
          <label>Sektion-titel</label>
          <input class="fb-config-input" id="cfg-section-name" value="${escHtml(field.section_name || field.label || '')}">
        </div>` : `
        <div class="fb-config-field">
          <label>Label (synlig for ansøger)</label>
          <input class="fb-config-input" id="cfg-label" value="${escHtml(field.label || '')}">
        </div>
        <div class="fb-config-field">
          <label>Felt-nøgle (unik ID)</label>
          <input class="fb-config-input" id="cfg-key" value="${escHtml(field.field_key || '')}">
        </div>
        ${hasPlaceholder ? `
          <div class="fb-config-field">
            <label>Placeholder</label>
            <input class="fb-config-input" id="cfg-placeholder" value="${escHtml(field.placeholder || '')}">
          </div>` : ''}
        <div class="fb-config-field">
          <label>Hjælpe-tekst</label>
          <textarea class="fb-config-textarea" id="cfg-help">${escHtml(field.help_text || '')}</textarea>
        </div>
        <div class="fb-config-field">
          <label class="fb-config-checkbox">
            <input type="checkbox" id="cfg-required" ${field.required ? 'checked' : ''}> Påkrævet
          </label>
        </div>
        <div class="fb-config-field">
          <label>Gem som (kerne-felt)</label>
          <select class="fb-config-select" id="cfg-core-map">
            <option value="">– Ekstra datapunkt –</option>
            ${coreMapOpts}
          </select>
        </div>
        ${hasOptions ? `
          <div class="fb-config-field">
            <label>Svarmuligheder</label>
            <div class="fb-options-list" id="cfg-options-list">
              ${(field.options || []).map((o, oi) => `
                <div class="fb-option-row">
                  <input class="fb-config-input" value="${escHtml(o)}" data-oi="${oi}">
                  <button class="fb-option-del" data-oi="${oi}">✕</button>
                </div>`).join('')}
            </div>
            <button class="crm-btn crm-btn-ghost fb-add-option">+ Tilføj mulighed</button>
          </div>` : ''}
      `}
    `;

    // Live-update handlers
    const bind = (id, key, cb) => {
      const inp = el(id);
      if (inp) inp.addEventListener('input', () => { fields[selectedIdx][key] = cb ? cb(inp) : inp.value; renderCanvas(); });
    };

    if (isSection) {
      bind('cfg-section-name', 'label');
      if (el('cfg-section-name')) el('cfg-section-name').addEventListener('input', () => {
        fields[selectedIdx]['section_name'] = el('cfg-section-name').value;
      });
    } else {
      bind('cfg-label', 'label');
      bind('cfg-key',   'field_key', inp => inp.value.replace(/[^a-z0-9_]/gi,'_').toLowerCase());
      bind('cfg-placeholder', 'placeholder');
      bind('cfg-help', 'help_text');
      el('cfg-required')?.addEventListener('change', () => {
        fields[selectedIdx]['required'] = el('cfg-required').checked ? 1 : 0;
        renderCanvas();
      });
      el('cfg-core-map')?.addEventListener('change', () => {
        fields[selectedIdx]['core_map'] = el('cfg-core-map').value;
      });
    }

    // Options: edit
    el('cfg-options-list')?.addEventListener('input', e => {
      if (e.target.dataset.oi !== undefined) {
        fields[selectedIdx].options[+e.target.dataset.oi] = e.target.value;
      }
    });
    // Options: delete
    el('cfg-options-list')?.addEventListener('click', e => {
      if (e.target.classList.contains('fb-option-del')) {
        fields[selectedIdx].options.splice(+e.target.dataset.oi, 1);
        renderConfig(fields[selectedIdx]);
      }
    });
    // Options: add
    qs('.fb-add-option')?.addEventListener('click', () => {
      if (!fields[selectedIdx].options) fields[selectedIdx].options = [];
      fields[selectedIdx].options.push('Ny mulighed');
      renderConfig(fields[selectedIdx]);
    });
  }

  // ── Save fields ──────────────────────────────────────────────────────────────
  async function saveFields() {
    const btn = el('fb-save-fields-btn');
    btn.textContent = '⏳ Gemmer…';
    try {
      // Re-assign sort_order from canvas order
      const orderedFields = [];
      qsa('.fb-field-item[data-idx]').forEach(item => {
        orderedFields.push(fields[+item.dataset.idx]);
      });
      fields = orderedFields;

      await api('crm/forms/' + currentFormId + '/fields', {
        method: 'POST',
        body:   JSON.stringify({ fields }),
      });
      toast('Felter gemt ✓');
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      btn.textContent = '💾 Gem felter';
    }
  }

  // ── Settings modal ───────────────────────────────────────────────────────────
  async function openSettings(formId) {
    const form = await api('crm/forms/' + formId);
    el('fb-settings-form-id').value   = formId;
    el('fb-form-title').value         = form.title || '';
    el('fb-form-slug').value          = form.slug || '';
    el('fb-form-intro').value         = form.intro_text || '';
    el('fb-form-success').value       = form.success_message || '';
    el('fb-form-redirect').value      = form.redirect_url || '';
    el('fb-form-notify').value        = form.notify_email || '';
    el('fb-form-active').checked      = !!form.is_active;
    el('fb-form-progress').checked    = !!form.show_progress;
    el('fb-form-multistep').checked   = !!form.multi_step;
    if (el('fb-form-position')) el('fb-form-position').value = form.position_id || '';

    el('fb-settings-modal').style.display = 'flex';
    el('crm-backdrop').style.display      = '';
  }

  async function saveSettings() {
    const id = +el('fb-settings-form-id').value;
    const btn = el('fb-save-settings-btn');
    btn.textContent = '⏳ Gemmer…';
    try {
      await api('crm/forms/' + id, {
        method: 'PUT',
        body:   JSON.stringify({
          title:           el('fb-form-title').value,
          slug:            el('fb-form-slug').value,
          intro_text:      el('fb-form-intro').value,
          success_message: el('fb-form-success').value,
          redirect_url:    el('fb-form-redirect').value,
          notify_email:    el('fb-form-notify').value,
          is_active:       el('fb-form-active').checked ? 1 : 0,
          show_progress:   el('fb-form-progress').checked ? 1 : 0,
          multi_step:      el('fb-form-multistep').checked ? 1 : 0,
          position_id:     el('fb-form-position')?.value || '',
        }),
      });
      toast('Indstillinger gemt ✓');
      el('fb-settings-modal').style.display = 'none';
      el('crm-backdrop').style.display      = 'none';
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      btn.textContent = 'Gem indstillinger';
    }
  }

  // ── Stats modal ──────────────────────────────────────────────────────────────
  async function openStats(formId) {
    el('fb-stats-modal').style.display = 'flex';
    el('crm-backdrop').style.display   = '';
    el('fb-stats-body').innerHTML      = '<p style="color:var(--crm-muted)">Indlæser…</p>';

    try {
      const s = await api('crm/forms/' + formId + '/stats?days=30');

      const dropoffRows = (s.step_dropoff || []).map(d =>
        `<tr><td>Trin ${escHtml(d.current_step)}</td><td>${escHtml(d.cnt)}</td><td style="color:#ff9800">Frafald her</td></tr>`
      ).join('') || '<tr><td colspan="3" style="color:#888">Ingen frafald-data endnu</td></tr>';

      const sourceRows = (s.utm_sources || []).map(d =>
        `<tr><td>${escHtml(d.source)}</td><td>${escHtml(d.cnt)}</td></tr>`
      ).join('') || '<tr><td colspan="2" style="color:#888">Ingen kilde-data endnu</td></tr>';

      el('fb-stats-body').innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px">
          <div class="fb-stat"><span class="fb-stat-val">${s.total}</span><span class="fb-stat-label">Påbegyndt</span></div>
          <div class="fb-stat"><span class="fb-stat-val">${s.completed}</span><span class="fb-stat-label">Gennemført</span></div>
          <div class="fb-stat fb-stat-neon"><span class="fb-stat-val">${s.conversion_rate}%</span><span class="fb-stat-label">Konverteringsrate</span></div>
        </div>
        <h3 style="font-size:13px;color:#888;margin:0 0 10px">Drop-off per trin (seneste 30 dage)</h3>
        <table class="crm-table" style="margin-bottom:20px">
          <thead><tr><th>Trin</th><th>Antal stoppet her</th><th></th></tr></thead>
          <tbody>${dropoffRows}</tbody>
        </table>
        <h3 style="font-size:13px;color:#888;margin:0 0 10px">Trafik-kilder</h3>
        <table class="crm-table">
          <thead><tr><th>Kilde</th><th>Antal</th></tr></thead>
          <tbody>${sourceRows}</tbody>
        </table>`;
    } catch(e) {
      el('fb-stats-body').innerHTML = '<p style="color:#ff5555">Fejl: ' + e.message + '</p>';
    }
  }

})(jQuery);
