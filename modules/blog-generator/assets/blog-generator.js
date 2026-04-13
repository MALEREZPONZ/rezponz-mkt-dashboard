/* =========================================================
   Rezponz Blog Generator — blog-generator.js
   ========================================================= */
(function () {
  'use strict';

  const BASE   = RZPA_BG.apiBase;
  const NONCE  = RZPA_BG.nonce;

  // ── State ───────────────────────────────────────────────────────────────────
  let allTopics    = [];
  let mediaImages  = [];
  let pickerCb     = null;   // callback(imageId) ved billede-valg
  let pickerSel    = null;   // aktuelt valgt image_id i picker
  let pollTimers   = {};     // topic_id → interval

  // ── Helpers ─────────────────────────────────────────────────────────────────
  const el   = id => document.getElementById(id);
  const qs   = (sel, ctx) => (ctx || document).querySelector(sel);
  const qsa  = (sel, ctx) => [...(ctx || document).querySelectorAll(sel)];

  async function api(path, opts = {}) {
    const res = await fetch(BASE + path, {
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      ...opts,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || data.code || 'API fejl ' + res.status);
    return data;
  }

  function toast(msg, type = 'ok') {
    const t = el('bg-toast');
    t.textContent = msg;
    t.className   = 'bg-toast show ' + type;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = 'bg-toast'; }, 3500);
  }

  function statusBadge(status) {
    const labels = { queued:'Kø', generating:'Genererer…', done:'Klar', failed:'Fejl' };
    return `<span class="bg-badge bg-badge-${status}">${labels[status] || status}</span>`;
  }

  function pillarLabel(k) { return (RZPA_BG.pillars[k] || k); }
  function typeLabel(k)   { return (RZPA_BG.types[k]   || k); }
  function targetLabel(k) { return (RZPA_BG.targets[k] || k); }

  // ── Tabs ─────────────────────────────────────────────────────────────────────
  qsa('.bg-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      qsa('.bg-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const name = tab.dataset.tab;
      qsa('[id^="tab-"]').forEach(p => p.style.display = 'none');
      el('tab-' + name).style.display = 'block';
      if (name === 'settings') loadSettings();
    });
  });

  // ── Load topics ───────────────────────────────────────────────────────────────
  async function loadTopics() {
    try {
      allTopics = await api('topics');
    } catch(e) {
      toast('Kunne ikke hente emner: ' + e.message, 'err');
      allTopics = [];
    }
    renderTopics();
    renderDone();
  }

  function renderTopics() {
    const queued = allTopics.filter(t => t.status !== 'done');
    el('bg-queue-count').textContent = queued.length ? `(${queued.length})` : '';

    const tbody = el('bg-topics-body');
    el('bg-topics-loading').style.display = 'none';

    if (!queued.length) {
      el('bg-topics-table').style.display = 'none';
      tbody.innerHTML = '';
      el('bg-topics-wrap').insertAdjacentHTML('beforeend',
        '<div class="bg-empty" id="bg-topics-empty">Køen er tom — tilføj et emne eller tryk "Overrask mig" 🎲</div>'
      );
      return;
    }

    const old = el('bg-topics-empty');
    if (old) old.remove();
    el('bg-topics-table').style.display = 'table';

    tbody.innerHTML = queued.map(t => {
      const imgThumb = t.image_url
        ? `<img src="${t.image_url}" style="width:36px;height:36px;object-fit:cover;border-radius:6px">`
        : `<span style="font-size:18px;opacity:.4">🖼</span>`;

      const actions = t.status === 'generating'
        ? `<span class="bg-spinner"></span>`
        : t.status === 'done'
          ? `<a href="${t.post_edit}" target="_blank" class="bg-btn bg-btn-ghost bg-btn-sm">Rediger</a>`
          : `<button class="bg-btn bg-btn-primary bg-btn-sm bg-gen-btn" data-id="${t.id}">▶ Generer</button>`;

      const errNote = t.status === 'failed' && t.error_msg
        ? `<div style="font-size:11px;color:#ff5555;margin-top:3px">${t.error_msg.substring(0,80)}</div>`
        : '';

      return `<tr data-id="${t.id}">
        <td><button class="bg-btn bg-btn-danger bg-btn-sm bg-del-btn" data-id="${t.id}" title="Slet">✕</button></td>
        <td>
          <div style="font-weight:600;color:#fff">${escHtml(t.title)}</div>
          ${t.keywords ? `<div style="font-size:11px;color:var(--muted)">${escHtml(t.keywords)}</div>` : ''}
          ${errNote}
        </td>
        <td><span class="bg-pillar">${pillarLabel(t.pillar)}</span></td>
        <td style="color:var(--muted);font-size:12px">${typeLabel(t.article_type)}</td>
        <td style="color:var(--muted);font-size:12px">${targetLabel(t.target)}</td>
        <td>
          <div class="bg-image-pick-cell" data-id="${t.id}" data-image="${t.image_id || ''}" style="cursor:pointer" title="Vælg billede">
            ${imgThumb}
          </div>
        </td>
        <td>${statusBadge(t.status)}</td>
        <td>${actions}</td>
      </tr>`;
    }).join('');

    // Generer-knapper
    tbody.querySelectorAll('.bg-gen-btn').forEach(btn => {
      btn.addEventListener('click', () => generateTopic(+btn.dataset.id));
    });

    // Slet-knapper
    tbody.querySelectorAll('.bg-del-btn').forEach(btn => {
      btn.addEventListener('click', () => deleteTopic(+btn.dataset.id));
    });

    // Billede-picker i tabel
    tbody.querySelectorAll('.bg-image-pick-cell').forEach(cell => {
      cell.addEventListener('click', () => {
        openImagePicker(+cell.dataset.image || null, async (imgId) => {
          try {
            await api('topics/' + cell.dataset.id + '/generate', {
              method: 'POST',
              body: JSON.stringify({ image_id: imgId }),
            });
          } catch(e) { /* handled by generateTopic */ }
          // Opdater blot image i state og re-render
          const t = allTopics.find(x => x.id == cell.dataset.id);
          if (t) {
            t.image_id  = imgId;
            t.image_url = mediaImages.find(m => m.id === imgId)?.thumb || '';
          }
          renderTopics();
        }, true /* pickerOnly — don't auto-generate */);
      });
    });

    // Start polling for 'generating' emner
    queued.filter(t => t.status === 'generating').forEach(t => startPolling(t.id));
  }

  function renderDone() {
    const done = allTopics.filter(t => t.status === 'done');
    el('bg-done-count').textContent = done.length ? `(${done.length})` : '';

    const tbody = el('bg-done-body');
    if (!done.length) {
      tbody.innerHTML = '<tr><td colspan="4"><div class="bg-empty">Ingen artikler genereret endnu</div></td></tr>';
      return;
    }

    tbody.innerHTML = done.map(t => `<tr>
      <td style="font-weight:600;color:#fff">${escHtml(t.title)}</td>
      <td><span class="bg-pillar">${pillarLabel(t.pillar)}</span></td>
      <td>
        ${t.post_edit ? `<a href="${t.post_edit}" target="_blank" class="bg-btn bg-btn-ghost bg-btn-sm">Rediger udkast</a>` : '–'}
        ${t.post_url  ? `<a href="${t.post_url}"  target="_blank" class="bg-btn bg-btn-ghost bg-btn-sm" style="margin-left:4px">Se side →</a>` : ''}
      </td>
      <td>
        <button class="bg-btn bg-btn-danger bg-btn-sm bg-del-btn" data-id="${t.id}">Slet</button>
      </td>
    </tr>`).join('');

    tbody.querySelectorAll('.bg-del-btn').forEach(btn => {
      btn.addEventListener('click', () => deleteTopic(+btn.dataset.id));
    });
  }

  // ── Generate ──────────────────────────────────────────────────────────────────
  async function generateTopic(id, imageId) {
    const topic = allTopics.find(t => t.id === id);
    if (!topic) return;

    if (!imageId && !topic.image_id) {
      // Ingen billede valgt — åbn picker
      openImagePicker(null, async (imgId) => {
        topic.image_id  = imgId;
        topic.image_url = imgId ? (mediaImages.find(m => m.id === imgId)?.thumb || '') : '';
        await _doGenerate(id, imgId);
      });
      return;
    }
    await _doGenerate(id, imageId || topic.image_id);
  }

  async function _doGenerate(id, imageId) {
    try {
      await api('topics/' + id + '/generate', {
        method: 'POST',
        body: JSON.stringify({ image_id: imageId }),
      });
      const t = allTopics.find(x => x.id === id);
      if (t) t.status = 'generating';
      renderTopics();
      toast('⏳ Generering startet — checker status…');
      startPolling(id);
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  // ── Polling ───────────────────────────────────────────────────────────────────
  function startPolling(id) {
    if (pollTimers[id]) return;
    pollTimers[id] = setInterval(async () => {
      try {
        const data = await api('topics/' + id + '/status');
        const t    = allTopics.find(x => x.id === id);

        if (data.status !== 'generating') {
          clearInterval(pollTimers[id]);
          delete pollTimers[id];

          if (t) {
            t.status     = data.status;
            t.wp_post_id = data.wp_post_id;
            t.post_edit  = data.post_edit;
            t.post_url   = data.post_url;
            t.error_msg  = data.error_msg;
          }

          renderTopics();
          renderDone();

          if (data.status === 'done') {
            toast('✅ Blogindlæg klar! ' + (data.post_edit ? '' : ''), 'ok');
          } else if (data.status === 'failed') {
            toast('❌ Generering fejlede: ' + (data.error_msg || 'Ukendt fejl'), 'err');
          }
        }
      } catch(e) { /* silence poll errors */ }
    }, 4000);
  }

  // ── Delete ────────────────────────────────────────────────────────────────────
  async function deleteTopic(id) {
    if (!confirm('Slet dette emne fra køen?')) return;
    try {
      await api('topics/' + id, { method: 'DELETE' });
      allTopics = allTopics.filter(t => t.id !== id);
      renderTopics();
      renderDone();
      toast('Emne slettet');
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  // ── Add form ──────────────────────────────────────────────────────────────────
  el('bg-add-btn').addEventListener('click', () => {
    el('bg-add-form').style.display = el('bg-add-form').style.display === 'none' ? 'block' : 'none';
    if (el('bg-add-form').style.display !== 'none') el('bg-new-title').focus();
  });

  el('bg-cancel-add').addEventListener('click', () => {
    el('bg-add-form').style.display = 'none';
  });

  el('bg-save-new').addEventListener('click', async () => {
    const title = el('bg-new-title').value.trim();
    if (!title) { el('bg-new-title').focus(); return; }

    const btn = el('bg-save-new');
    btn.disabled    = true;
    btn.textContent = '⏳ Tilføjer…';

    try {
      const data = await api('topics', {
        method: 'POST',
        body: JSON.stringify({
          title,
          keywords:     el('bg-new-keywords').value.trim(),
          pillar:       el('bg-new-pillar').value,
          article_type: el('bg-new-type').value,
          target:       el('bg-new-target').value,
          word_count:   +el('bg-new-words').value,
          include_faq:  el('bg-new-faq').checked ? 1 : 0,
        }),
      });

      toast('✓ Emne tilføjet til køen');
      el('bg-new-title').value    = '';
      el('bg-new-keywords').value = '';
      el('bg-add-form').style.display = 'none';

      // Tilføj til lokal state og re-render
      allTopics.push({ id: data.id, title, status: 'queued',
        pillar: el('bg-new-pillar').value,
        article_type: el('bg-new-type').value,
        target: el('bg-new-target').value,
        word_count: +el('bg-new-words').value,
        image_id: null, image_url: null, error_msg: null });
      renderTopics();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    } finally {
      btn.disabled    = false;
      btn.textContent = 'Tilføj til kø';
    }
  });

  // ── Overrask mig ──────────────────────────────────────────────────────────────
  el('bg-surprise-btn').addEventListener('click', async () => {
    const queued = allTopics.filter(t => t.status === 'queued');
    if (!queued.length) {
      toast('Ingen emner i kø — tilføj et emne først', 'err');
      return;
    }
    const pick = queued[Math.floor(Math.random() * queued.length)];
    const btn  = el('bg-surprise-btn');
    btn.disabled    = true;
    btn.textContent = '⏳ Starter…';

    await generateTopic(pick.id);

    btn.disabled    = false;
    btn.textContent = '🎲 Overrask mig';
  });

  // ── AI Forslagsmotor ──────────────────────────────────────────────────────────
  el('bg-suggest-btn').addEventListener('click', async () => {
    const btn = el('bg-suggest-btn');
    btn.disabled    = true;
    btn.textContent = '⏳ Henter…';
    el('bg-suggest-results').innerHTML = '<div class="bg-spinner"></div>';

    try {
      const suggestions = await api('suggest', {
        method: 'POST',
        body: JSON.stringify({
          keyword: el('bg-suggest-kw').value.trim(),
          target:  el('bg-suggest-target').value,
          count:   6,
        }),
      });

      el('bg-suggest-results').innerHTML = suggestions.map(s =>
        `<span class="bg-suggest-chip" data-title="${escAttr(s.title)}" data-pillar="${s.pillar || 'custom'}" data-type="${s.article_type || 'explainer'}">${escHtml(s.title)}</span>`
      ).join('');

      el('bg-suggest-results').querySelectorAll('.bg-suggest-chip').forEach(chip => {
        chip.addEventListener('click', () => {
          el('bg-new-title').value = chip.dataset.title;
          el('bg-new-pillar').value = chip.dataset.pillar;
          el('bg-new-type').value  = chip.dataset.type;
          el('bg-add-form').style.display = 'block';
          el('bg-new-title').focus();
        });
      });
    } catch(e) {
      el('bg-suggest-results').innerHTML = `<span style="color:#ff5555;font-size:12px">${e.message}</span>`;
    } finally {
      btn.disabled    = false;
      btn.textContent = '✨ Foreslå emner';
    }
  });

  // ── Image Picker ──────────────────────────────────────────────────────────────
  async function loadMedia() {
    if (mediaImages.length) return;
    try {
      mediaImages = await api('media');
    } catch(e) {
      mediaImages = [];
    }
  }

  function renderPickerGrid(containerId, selectedId) {
    const grid = el(containerId);
    if (!mediaImages.length) {
      grid.innerHTML = '<p style="color:var(--muted);font-size:13px">Ingen billeder i mediebiblioteket.</p>';
      return;
    }
    grid.innerHTML = mediaImages.map(m =>
      `<div class="bg-image-item${selectedId && m.id == selectedId ? ' selected' : ''}" data-id="${m.id}" title="${escAttr(m.title)}">
        <img src="${m.thumb}" alt="${escAttr(m.alt || m.title)}" loading="lazy">
      </div>`
    ).join('');

    grid.querySelectorAll('.bg-image-item').forEach(item => {
      item.addEventListener('click', () => {
        grid.querySelectorAll('.bg-image-item').forEach(i => i.classList.remove('selected'));
        item.classList.toggle('selected');
        pickerSel = item.classList.contains('selected') ? +item.dataset.id : null;
      });
    });
  }

  function openImagePicker(currentId, callback, pickerOnly = false) {
    pickerCb  = callback;
    pickerSel = currentId || null;

    const wrap = el('bg-image-picker-wrap');
    wrap.style.display = 'flex';

    loadMedia().then(() => renderPickerGrid('bg-picker-grid', currentId));
  }

  el('bg-image-picker-close').addEventListener('click', () => {
    el('bg-image-picker-wrap').style.display = 'none';
    pickerCb = null;
  });

  el('bg-image-picker-confirm').addEventListener('click', () => {
    el('bg-image-picker-wrap').style.display = 'none';
    if (pickerCb) pickerCb(pickerSel);
    pickerCb = null;
  });

  el('bg-image-picker-clear').addEventListener('click', () => {
    el('bg-image-picker-wrap').style.display = 'none';
    if (pickerCb) pickerCb(null);
    pickerCb = null;
  });

  // ── Settings tab ──────────────────────────────────────────────────────────────
  async function loadSettings() {
    // Kategori-liste
    try {
      const cats   = await api('categories');
      const sel    = el('bg-default-cat');
      const saved  = el('bg-default-cat').dataset.saved || '';
      sel.innerHTML = '<option value="">— Ingen kategori —</option>' +
        cats.map(c => `<option value="${c.id}"${c.id == saved ? ' selected' : ''}>${escHtml(c.name)}</option>`).join('');
    } catch(e) {}

    // Brand voice
    const bv = el('bg-brand-voice');
    if (!bv.value) {
      // Hent fra lokalt storage hvis gemt
      bv.value = localStorage.getItem('rzpa_brand_voice') || '';
    }

    // Media i settings
    await loadMedia();
    renderPickerGrid('bg-settings-media-grid', null);
  }

  el('bg-save-brand-voice').addEventListener('click', async () => {
    const voice = el('bg-brand-voice').value.trim();
    localStorage.setItem('rzpa_brand_voice', voice);

    // Gem via WP options REST (kræver en lille PHP-endpoint — sender til rzpa_settings)
    try {
      await fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'rzpa_save_blog_gen_setting',
          key:    'blog_gen_brand_voice',
          value:  voice,
          nonce:  NONCE,
        }),
      });
      toast('✓ Brand voice gemt');
    } catch(e) {
      toast('✓ Gemt lokalt (serverfejl: ' + e.message + ')');
    }
  });

  el('bg-save-settings').addEventListener('click', async () => {
    try {
      await fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'rzpa_save_blog_gen_setting',
          key:    'blog_gen_category',
          value:  el('bg-default-cat').value,
          nonce:  NONCE,
        }),
      });
      toast('✓ Indstillinger gemt');
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  });

  // ── XSS helpers ───────────────────────────────────────────────────────────────
  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function escAttr(s) {
    return String(s || '').replace(/"/g,'&quot;');
  }

  // ── Init ──────────────────────────────────────────────────────────────────────
  loadTopics();

})();
