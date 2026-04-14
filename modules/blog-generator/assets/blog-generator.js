/* =========================================================
   Rezponz Blog Generator — blog-generator.js  v3.3.0
   ========================================================= */
(function () {
  'use strict';

  const BASE  = RZPA_BG.apiBase;
  const NONCE = RZPA_BG.nonce;

  // ── State ────────────────────────────────────────────────────────────────────
  let allTopics   = [];
  let mediaImages = [];
  let pickerCb    = null;   // callback(imageId) ved billede-valg
  let pickerSel   = null;   // aktuelt valgt image_id i picker
  let pollTimers  = {};     // topic_id → interval
  let calYear     = new Date().getFullYear();
  let calMonth    = new Date().getMonth();   // 0-indexed

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const el  = id  => document.getElementById(id);
  const qs  = (s, c) => (c || document).querySelector(s);
  const qsa = (s, c) => [...(c || document).querySelectorAll(s)];

  async function api(path, opts = {}) {
    const res  = await fetch(BASE + path, {
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

  function pillarLabel(k) { return RZPA_BG.pillars[k] || k; }
  function typeLabel(k)   { return RZPA_BG.types[k]   || k; }
  function targetLabel(k) { return RZPA_BG.targets[k] || k; }

  function fmtDate(iso) {
    if (!iso) return '–';
    const d = new Date(iso.replace(' ', 'T'));
    return isNaN(d) ? '–' : d.toLocaleDateString('da-DK', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
  }

  // ── Tabs ─────────────────────────────────────────────────────────────────────
  qsa('.bg-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      qsa('.bg-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const name = tab.dataset.tab;
      qsa('[id^="tab-"]').forEach(p => p.style.display = 'none');
      el('tab-' + name).style.display = 'block';
      if (name === 'settings')  loadSettings();
      if (name === 'calendar')  renderCalendar();
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
      if (!el('bg-topics-empty')) {
        el('bg-topics-wrap').insertAdjacentHTML('beforeend',
          '<div class="bg-empty" id="bg-topics-empty">Køen er tom — tilføj et emne eller tryk "Overrask mig" 🎲</div>'
        );
      }
      return;
    }

    const old = el('bg-topics-empty');
    if (old) old.remove();
    el('bg-topics-table').style.display = 'table';

    tbody.innerHTML = queued.map(t => {
      const imgThumb = t.image_url
        ? `<img src="${escAttr(t.image_url)}" style="width:36px;height:36px;object-fit:cover;border-radius:6px">`
        : `<span style="font-size:18px;opacity:.4">🖼</span>`;

      const actions = t.status === 'generating'
        ? `<span class="bg-spinner"></span>`
        : t.status === 'done'
          ? `<a href="${t.post_edit}" target="_blank" class="bg-btn bg-btn-ghost bg-btn-sm">Rediger</a>`
          : `<button class="bg-btn bg-btn-primary bg-btn-sm bg-gen-btn" data-id="${t.id}">▶ Generer</button>`;

      const errNote = t.status === 'failed' && t.error_msg
        ? `<div style="font-size:11px;color:#ff5555;margin-top:3px">${escHtml(t.error_msg.substring(0, 80))}</div>`
        : '';

      const scheduledLabel = t.scheduled_for
        ? `<span class="bg-sched-pill" data-id="${t.id}" title="Klik for at ændre">⏰ ${fmtDate(t.scheduled_for)}</span>`
        : `<span class="bg-sched-empty" data-id="${t.id}" title="Klik for at planlægge">+ Planlæg</span>`;

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
        <td>${scheduledLabel}</td>
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

    // Planlagt dato — klik for at sætte/ændre
    tbody.querySelectorAll('.bg-sched-pill, .bg-sched-empty').forEach(pill => {
      pill.addEventListener('click', () => openInlineDatePicker(pill, +pill.dataset.id));
    });

    // Billede-picker i tabel (PATCH topic — kun opdater image, ikke generer)
    tbody.querySelectorAll('.bg-image-pick-cell').forEach(cell => {
      cell.addEventListener('click', () => {
        openImagePicker(+cell.dataset.image || null, async (imgId) => {
          try {
            await api('topics/' + cell.dataset.id, {
              method: 'PATCH',
              body:   JSON.stringify({ image_id: imgId }),
            });
            const t = allTopics.find(x => x.id == cell.dataset.id);
            if (t) {
              t.image_id  = imgId;
              t.image_url = mediaImages.find(m => m.id === imgId)?.thumb || '';
            }
            renderTopics();
            toast('✓ Billede opdateret');
          } catch(e) {
            toast('Fejl: ' + e.message, 'err');
          }
        });
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
  async function generateTopic(id) {
    // Use loose equality (==) — REST API returns IDs as strings, btn.dataset as numbers
    const topic = allTopics.find(t => t.id == id);
    if (!topic) {
      toast('Fejl: Emne ikke fundet — prøv at genindlæse siden', 'err');
      return;
    }
    // Start generation immediately — image can be added afterwards
    await _doGenerate(id, topic.image_id || null);
  }

  async function _doGenerate(id, imageId) {
    try {
      await api('topics/' + id + '/generate', {
        method: 'POST',
        body:   JSON.stringify({ image_id: imageId }),
      });
      const t = allTopics.find(x => x.id == id);
      if (t) t.status = 'generating';
      renderTopics();
      toast('⏳ Generering startet — checker status…');
      startPolling(id);
    } catch(e) {
      // Surface clear error — common case: missing OpenAI key
      toast('❌ ' + (e.message || 'Ukendt fejl'), 'err');
    }
  }

  // ── Polling ───────────────────────────────────────────────────────────────────
  const POLL_INTERVAL_MS = 4000;
  const POLL_MAX         = 75;  // 75 × 4s = 5 min max
  const pollCounts       = {};

  function startPolling(id) {
    if (pollTimers[id]) return;
    pollCounts[id] = 0;

    pollTimers[id] = setInterval(async () => {
      pollCounts[id] = (pollCounts[id] || 0) + 1;

      // Max timeout: stop polling og vis venlig fejl
      if (pollCounts[id] > POLL_MAX) {
        clearInterval(pollTimers[id]);
        delete pollTimers[id];
        delete pollCounts[id];
        const t = allTopics.find(x => x.id === id);
        if (t && t.status === 'generating') {
          t.status    = 'failed';
          t.error_msg = 'Timeout: generering tog for lang tid. Tjek at WP Cron kører korrekt og prøv igen.';
          renderTopics();
          toast('⏱ Timeout efter 5 min — er WP Cron aktivt på serveren?', 'err');
        }
        return;
      }

      try {
        const data = await api('topics/' + id + '/status');
        const t    = allTopics.find(x => x.id == id);

        if (data.status !== 'generating') {
          clearInterval(pollTimers[id]);
          delete pollTimers[id];
          delete pollCounts[id];

          if (t) {
            t.status     = data.status;
            t.wp_post_id = data.wp_post_id;
            t.post_edit  = data.post_edit;
            t.post_url   = data.post_url;
            t.error_msg  = data.error_msg;
          }

          renderTopics();
          renderDone();

          if (data.status === 'done')
            toast('✅ Blogindlæg klar!', 'ok');
          else if (data.status === 'failed')
            toast('❌ Generering fejlede: ' + (data.error_msg || 'Ukendt fejl'), 'err');
        }
      } catch(e) { /* silence poll errors */ }
    }, POLL_INTERVAL_MS);
  }

  // ── Delete ────────────────────────────────────────────────────────────────────
  async function deleteTopic(id) {
    if (!confirm('Slet dette emne fra køen?')) return;
    try {
      await api('topics/' + id, { method: 'DELETE' });
      if (pollTimers[id]) { clearInterval(pollTimers[id]); delete pollTimers[id]; }
      allTopics = allTopics.filter(t => t.id != id);
      renderTopics();
      renderDone();
      toast('Emne slettet');
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  // ── Add form ──────────────────────────────────────────────────────────────────
  el('bg-add-btn').addEventListener('click', () => {
    const form = el('bg-add-form');
    const show = form.style.display === 'none' || form.style.display === '';
    form.style.display = show ? 'block' : 'none';
    if (show) el('bg-new-title').focus();
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

    const scheduledRaw = el('bg-new-scheduled').value;
    const scheduledFor = scheduledRaw ? new Date(scheduledRaw).toISOString() : null;

    try {
      const payload = {
        title,
        keywords:              el('bg-new-keywords').value.trim(),
        pillar:                el('bg-new-pillar').value,
        article_type:          el('bg-new-type').value,
        target:                el('bg-new-target').value,
        word_count:            +el('bg-new-words').value,
        include_faq:           el('bg-new-faq').checked ? 1 : 0,
        include_toc:           el('bg-new-toc').checked ? 1 : 0,
        include_tldr:          el('bg-new-tldr').checked ? 1 : 0,
        include_internal_links:el('bg-new-internal-links').checked ? 1 : 0,
        publish_immediately:   el('bg-new-publish-now').checked ? 1 : 0,
        scheduled_for:         scheduledFor,
      };

      const data = await api('topics', { method: 'POST', body: JSON.stringify(payload) });

      toast('✓ Emne tilføjet til køen' + (scheduledFor ? ' — planlagt til ' + fmtDate(scheduledFor) : ''));
      el('bg-new-title').value     = '';
      el('bg-new-keywords').value  = '';
      el('bg-new-scheduled').value = '';
      el('bg-add-form').style.display = 'none';

      allTopics.push({
        id:            data.id,
        title,
        status:        'queued',
        pillar:        el('bg-new-pillar').value,
        article_type:  el('bg-new-type').value,
        target:        el('bg-new-target').value,
        word_count:    +el('bg-new-words').value,
        scheduled_for: scheduledFor,
        image_id:      null,
        image_url:     null,
        error_msg:     null,
      });
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
    const queued = allTopics.filter(t => t.status === 'queued' && !t.scheduled_for);
    if (!queued.length) { toast('Ingen emner i kø — tilføj et emne først', 'err'); return; }
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
        body:   JSON.stringify({
          keyword: el('bg-suggest-kw').value.trim(),
          target:  el('bg-suggest-target').value,
          count:   6,
        }),
      });

      el('bg-suggest-results').innerHTML = suggestions.map(s =>
        `<span class="bg-suggest-chip"
          data-title="${escAttr(s.title)}"
          data-pillar="${s.pillar || 'custom'}"
          data-type="${s.article_type || 'explainer'}">
          ${escHtml(s.title)}
          ${s.difficulty   ? `<small style="opacity:.6"> · ⚡${s.difficulty}/10</small>` : ''}
        </span>`
      ).join('');

      el('bg-suggest-results').querySelectorAll('.bg-suggest-chip').forEach(chip => {
        chip.addEventListener('click', () => {
          el('bg-new-title').value  = chip.dataset.title;
          el('bg-new-pillar').value = chip.dataset.pillar;
          el('bg-new-type').value   = chip.dataset.type;
          el('bg-add-form').style.display = 'block';
          el('bg-new-title').focus();
        });
      });
    } catch(e) {
      el('bg-suggest-results').innerHTML = `<span style="color:#ff5555;font-size:12px">${escHtml(e.message)}</span>`;
    } finally {
      btn.disabled    = false;
      btn.textContent = '✨ Foreslå emner';
    }
  });

  // ── Image Picker ──────────────────────────────────────────────────────────────
  async function loadMedia() {
    if (mediaImages.length) return;
    try { mediaImages = await api('media'); }
    catch(e) { mediaImages = []; }
  }

  function renderPickerGrid(containerId, selectedId) {
    const grid = el(containerId);
    if (!mediaImages.length) {
      grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:24px;color:var(--muted);font-size:13px">
        <div style="font-size:32px;margin-bottom:8px">📂</div>
        <div>Ingen billeder i mediebiblioteket.</div>
        <a href="${typeof ajaxurl !== 'undefined' ? ajaxurl.replace('admin-ajax.php','upload.php') : '/wp-admin/upload.php'}"
           target="_blank" style="color:var(--neon);font-size:12px;margin-top:6px;display:inline-block">
          → Upload billeder i mediebiblioteket
        </a>
      </div>`;
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

  function openImagePicker(currentId, callback) {
    pickerCb  = callback;
    pickerSel = currentId || null;
    const wrap = el('bg-image-picker-wrap');
    wrap.style.display = 'flex';

    // Vis loading-spinner mens billeder hentes
    el('bg-picker-grid').innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px"><div class="bg-spinner" style="width:24px;height:24px;border-width:3px"></div></div>';

    loadMedia().then(() => renderPickerGrid('bg-picker-grid', currentId));
  }

  function closeImagePicker() {
    el('bg-image-picker-wrap').style.display = 'none';
    pickerCb  = null;
    pickerSel = null;
  }

  el('bg-image-picker-close').addEventListener('click', closeImagePicker);

  el('bg-image-picker-confirm').addEventListener('click', () => {
    const cb = pickerCb;
    closeImagePicker();
    if (cb) cb(pickerSel);
  });
  el('bg-image-picker-clear').addEventListener('click', () => {
    const cb = pickerCb;
    closeImagePicker();
    if (cb) cb(null);
  });

  // Luk ved klik på baggrunden (udenfor modal-boksen)
  el('bg-image-picker-wrap').addEventListener('click', e => {
    if (e.target === el('bg-image-picker-wrap')) closeImagePicker();
  });

  // ── Calendar ──────────────────────────────────────────────────────────────────
  const MONTH_NAMES_DA = ['Januar','Februar','Marts','April','Maj','Juni',
                          'Juli','August','September','Oktober','November','December'];

  async function renderCalendar() {
    const ym    = `${calYear}-${String(calMonth + 1).padStart(2, '0')}`;
    el('bg-cal-title').textContent = `${MONTH_NAMES_DA[calMonth]} ${calYear}`;

    let events = [];
    try {
      events = await api('calendar/' + ym);
    } catch(e) {
      events = [];
    }

    // Byg events-map: day → [topics]
    const dayMap = {};
    events.forEach(t => {
      if (!t.scheduled_for) return;
      const d = new Date(t.scheduled_for.replace(' ', 'T'));
      const day = d.getDate();
      if (!dayMap[day]) dayMap[day] = [];
      dayMap[day].push(t);
    });

    // Også topics fra allTopics (lokal state) for evt. nytilføjede
    // scheduled_for kan være ISO-string (fra toISOString()) eller MySQL datetime ("Y-m-d H:i:s")
    allTopics.forEach(t => {
      if (!t.scheduled_for) return;
      const sf = t.scheduled_for.includes('T') ? t.scheduled_for : t.scheduled_for.replace(' ', 'T');
      const d  = new Date(sf);
      if (d.getFullYear() === calYear && d.getMonth() === calMonth) {
        const day = d.getDate();
        if (!dayMap[day]) dayMap[day] = [];
        // undgå dubletter
        if (!dayMap[day].find(x => x.id === t.id)) dayMap[day].push(t);
      }
    });

    // Find 1. dag i måneden og antal dage
    const firstDay = new Date(calYear, calMonth, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
    // Konverter til mandag-baseret (0=Mon … 6=Sun)
    const startOffset = (firstDay + 6) % 7;

    const today = new Date();
    const isThisMonth = today.getFullYear() === calYear && today.getMonth() === calMonth;

    // Fjern alle eksisterende dage (behold day-name headers)
    const grid = el('bg-cal-grid');
    qsa('.bg-cal-cell', grid).forEach(c => c.remove());

    // Tomme celler for offset
    for (let i = 0; i < startOffset; i++) {
      grid.insertAdjacentHTML('beforeend',
        '<div class="bg-cal-cell other-month"><div class="bg-cal-num"></div></div>'
      );
    }

    // Dage i måneden
    for (let d = 1; d <= daysInMonth; d++) {
      const isToday = isThisMonth && d === today.getDate();
      const dateStr = `${calYear}-${String(calMonth + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const eventsHtml = (dayMap[d] || []).map(t => {
        const canRm = (t.status === 'queued' || t.status === 'failed');
        return `<div class="bg-cal-event ${t.status || ''}" data-id="${t.id}" title="${escAttr(t.title)}">
          <span class="bg-cal-event-title">${escHtml(t.title.substring(0, 28))}${t.title.length > 28 ? '…' : ''}</span>
          ${canRm ? `<span class="bg-cal-event-rm" data-rm="${t.id}" title="Fjern fra kalender">✕</span>` : ''}
        </div>`;
      }).join('');

      grid.insertAdjacentHTML('beforeend',
        `<div class="bg-cal-cell${isToday ? ' today' : ''}" data-date="${dateStr}">
          <div class="bg-cal-num"><span>${d}</span><span class="bg-cal-add-btn" title="Planlæg emne her">＋</span></div>
          ${eventsHtml}
        </div>`
      );
    }

    // Click på event-titel → skift til kø-tab og scroll
    qsa('.bg-cal-event', grid).forEach(ev => {
      ev.querySelector('.bg-cal-event-title')?.addEventListener('click', e => {
        e.stopPropagation();
        const id = +ev.dataset.id;
        const queueTab = qs('[data-tab="queue"]');
        if (queueTab) queueTab.click();
        setTimeout(() => {
          const row = qs(`tr[data-id="${id}"]`);
          if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
      });
    });

    // Click på ✕ → fjern planlagt dato (unschedule)
    qsa('.bg-cal-event-rm', grid).forEach(btn => {
      btn.addEventListener('click', async e => {
        e.stopPropagation();
        const id = +btn.dataset.rm;
        try {
          await api('topics/' + id, {
            method: 'PATCH',
            body: JSON.stringify({ scheduled_for: null }),
          });
          const t = allTopics.find(x => x.id == id);
          if (t) t.scheduled_for = null;
          toast('Emne fjernet fra kalender');
          renderCalendar();
        } catch(e) {
          toast('Fejl: ' + e.message, 'err');
        }
      });
    });

    // Click på dag-celle → åbn dropdown til at planlægge
    qsa('.bg-cal-cell', grid).forEach(cell => {
      cell.addEventListener('click', e => {
        if (e.target.closest('.bg-cal-event')) return; // ignorér event-klik
        openCalDropdown(cell, cell.dataset.date);
      });
    });
  }

  // ── Kalender-dropdown ──────────────────────────────────────────────────────
  let calDropdownDate = null;

  function openCalDropdown(cell, dateStr) {
    if (!dateStr) return;
    calDropdownDate = dateStr;

    // Markér åben celle
    qsa('.bg-cal-open').forEach(c => c.classList.remove('bg-cal-open'));
    cell.classList.add('bg-cal-open');

    // Formater dato til visning
    const [yr, mo, dy] = dateStr.split('-');
    const MONTH_DA = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
    el('bg-cal-dd-title').textContent = `Planlæg til ${parseInt(dy)}. ${MONTH_DA[parseInt(mo)-1]} ${yr}`;

    // Filtrer: kun køet emner UDEN dato
    const available = allTopics.filter(t =>
      (t.status === 'queued' || t.status === 'failed') && !t.scheduled_for
    );

    const list = el('bg-cal-dd-list');
    const empty = el('bg-cal-dd-empty');

    if (available.length === 0) {
      list.style.display = 'none';
      empty.style.display = '';
    } else {
      empty.style.display = 'none';
      list.style.display = '';
      list.innerHTML = available.map(t => `
        <div class="bg-cal-dd-item" data-schedule-id="${t.id}">
          <span class="bg-cal-dd-item-dot"></span>
          <span class="bg-cal-dd-item-title">${escHtml(t.title)}</span>
          <span class="bg-cal-dd-item-kw">${escHtml(t.keywords || '')}</span>
        </div>`).join('');

      list.querySelectorAll('.bg-cal-dd-item').forEach(row => {
        row.addEventListener('click', () => scheduleTopicToDate(+row.dataset.scheduleId, calDropdownDate));
      });
    }

    // Positionér dropdown ved cellen
    const rect = cell.getBoundingClientRect();
    const dd   = el('bg-cal-dropdown');
    dd.style.display = '';

    // Bestem side: højre hvis der er plads, ellers venstre
    const ddW  = 300;
    const winW = window.innerWidth;
    let left = rect.right + 6;
    if (left + ddW > winW - 12) left = rect.left - ddW - 6;
    if (left < 8) left = 8;

    // position:fixed → koordinater relativt til viewport (ingen scrollY)
    let top = rect.bottom + 4;
    const winH = window.innerHeight;
    if (top + 300 > winH) top = Math.max(4, rect.top - 300 - 4);

    dd.style.left = left + 'px';
    dd.style.top  = top  + 'px';
  }

  function closeCalDropdown() {
    el('bg-cal-dropdown').style.display = 'none';
    qsa('.bg-cal-open').forEach(c => c.classList.remove('bg-cal-open'));
    calDropdownDate = null;
  }

  async function scheduleTopicToDate(topicId, dateStr) {
    const scheduledFor = dateStr + ' 09:00:00'; // standard: 09:00
    try {
      await api('topics/' + topicId, {
        method: 'PATCH',
        body: JSON.stringify({ scheduled_for: scheduledFor }),
      });
      const t = allTopics.find(x => x.id == topicId);
      if (t) t.scheduled_for = scheduledFor;
      closeCalDropdown();
      toast('✓ Emne planlagt');
      renderCalendar();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  // Luk dropdown ved klik udenfor
  document.addEventListener('click', e => {
    const dd = el('bg-cal-dropdown');
    if (!dd || dd.style.display === 'none') return;
    if (!dd.contains(e.target) && !e.target.closest('.bg-cal-cell')) closeCalDropdown();
  });
  el('bg-cal-dd-close')?.addEventListener('click', closeCalDropdown);
  el('bg-cal-dd-goto-queue')?.addEventListener('click', () => {
    closeCalDropdown();
    qs('[data-tab="queue"]')?.click();
  });

  el('bg-cal-prev').addEventListener('click', () => {
    calMonth--;
    if (calMonth < 0) { calMonth = 11; calYear--; }
    renderCalendar();
  });
  el('bg-cal-next').addEventListener('click', () => {
    calMonth++;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    renderCalendar();
  });
  el('bg-cal-today').addEventListener('click', () => {
    calYear  = new Date().getFullYear();
    calMonth = new Date().getMonth();
    renderCalendar();
  });

  // ── Settings tab ──────────────────────────────────────────────────────────────
  async function loadSettings() {
    try {
      const cats  = await api('categories');
      const sel   = el('bg-default-cat');
      const saved = sel.dataset.saved || '';
      sel.innerHTML = '<option value="">— Ingen kategori —</option>' +
        cats.map(c => `<option value="${c.id}"${c.id == saved ? ' selected' : ''}>${escHtml(c.name)}</option>`).join('');
    } catch(e) {}

    const bv = el('bg-brand-voice');
    if (!bv.value) bv.value = localStorage.getItem('rzpa_brand_voice') || '';

    // Hent gemte værdier fra localized data (sættes ved siden af nonce i PHP)
    if (RZPA_BG.settings) {
      if (el('bg-default-type') && RZPA_BG.settings.blog_gen_default_type)
        el('bg-default-type').value = RZPA_BG.settings.blog_gen_default_type;
      if (el('bg-default-words') && RZPA_BG.settings.blog_gen_default_words)
        el('bg-default-words').value = RZPA_BG.settings.blog_gen_default_words;
      if (el('bg-elementor-template-id') && RZPA_BG.settings.blog_gen_elementor_template_id)
        el('bg-elementor-template-id').value = RZPA_BG.settings.blog_gen_elementor_template_id;
      if (el('bg-default-author-id') && RZPA_BG.settings.blog_gen_default_author)
        el('bg-default-author-id').value = RZPA_BG.settings.blog_gen_default_author;
    }

    await loadMedia();
    renderPickerGrid('bg-settings-media-grid', null);
  }

  el('bg-save-brand-voice').addEventListener('click', async () => {
    const voice = el('bg-brand-voice').value.trim();
    localStorage.setItem('rzpa_brand_voice', voice);
    try {
      const res = await fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action:    'rzpa_save_blog_gen_setting',
          key:       'blog_gen_brand_voice',
          value:     voice,
          _wpnonce:  NONCE,
        }),
      });
      const data = await res.json();
      toast(data.success ? '✓ Brand voice gemt' : '⚠ Gem fejlede', data.success ? 'ok' : 'err');
    } catch(e) {
      toast('✓ Gemt lokalt (serverfejl: ' + e.message + ')');
    }
  });

  el('bg-save-settings').addEventListener('click', async () => {
    const settings = [
      { key: 'blog_gen_category',              value: el('bg-default-cat').value },
      { key: 'blog_gen_default_type',          value: el('bg-default-type')?.value       || '' },
      { key: 'blog_gen_default_words',         value: el('bg-default-words')?.value      || '' },
      { key: 'blog_gen_elementor_template_id', value: el('bg-elementor-template-id')?.value || '' },
      { key: 'blog_gen_default_author',        value: el('bg-default-author-id')?.value  || '' },
    ];

    try {
      // Gem alle settings i parallelle AJAX-kald
      await Promise.all(settings.map(s =>
        fetch(ajaxurl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action:   'rzpa_save_blog_gen_setting',
            key:      s.key,
            value:    s.value,
            _wpnonce: NONCE,
          }),
        })
      ));
      toast('✓ Indstillinger gemt');
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  });

  // ── Inline date picker for queue PLANLAGT column ────────────────────────────

  function openInlineDatePicker(pill, topicId) {
    // Fjern evt. eksisterende picker
    const existing = document.getElementById('bg-inline-dp');
    if (existing) {
      existing.remove();
      document.removeEventListener('click', outsideDpClick);
      if (existing._topicId === topicId) return; // toggle lukker
    }

    const topic = allTopics.find(t => t.id == topicId);
    const rect  = pill.getBoundingClientRect();

    const dp = document.createElement('div');
    dp.id = 'bg-inline-dp';
    dp._topicId = topicId;
    dp.style.cssText = [
      'position:fixed', 'z-index:99999',
      'background:#1a1a1a', 'border:1px solid rgba(204,255,0,.3)',
      'border-radius:10px', 'padding:12px 14px',
      'box-shadow:0 8px 32px rgba(0,0,0,.6)', 'min-width:230px',
    ].join(';');

    let left = rect.left;
    if (left + 245 > window.innerWidth) left = window.innerWidth - 250;
    dp.style.top  = (rect.bottom + 6) + 'px';
    dp.style.left = Math.max(8, left) + 'px';

    let defaultVal = '';
    if (topic?.scheduled_for) {
      const d = new Date(topic.scheduled_for.replace(' ', 'T'));
      defaultVal = d.toISOString().slice(0, 16);
    }

    dp.innerHTML = `
      <div style="font-size:11px;color:#666;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em">Planlæg publicering</div>
      <input type="datetime-local" id="bg-dp-input" value="${defaultVal}"
        style="background:#111;border:1px solid rgba(255,255,255,.15);border-radius:8px;
               color:#e5e5e5;padding:7px 10px;font-size:13px;width:100%;box-sizing:border-box;
               outline:none;font-family:inherit;color-scheme:dark">
      <div style="display:flex;gap:8px;margin-top:10px">
        <button id="bg-dp-save" style="flex:1;background:#CCFF00;color:#111;border:none;border-radius:6px;
          padding:7px 0;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">✓ Gem dato</button>
        ${topic?.scheduled_for
          ? `<button id="bg-dp-clear" style="background:transparent;color:#ff5555;border:1px solid rgba(255,85,85,.3);
              border-radius:6px;padding:7px 10px;font-size:12px;cursor:pointer;font-family:inherit">✕</button>`
          : ''}
      </div>`;

    document.body.appendChild(dp);

    dp.querySelector('#bg-dp-save').addEventListener('click', async () => {
      const val = dp.querySelector('#bg-dp-input').value;
      if (!val) { toast('Vælg en dato', 'err'); return; }
      await applySchedule(topicId, val.replace('T', ' ') + ':00');
      dp.remove();
      document.removeEventListener('click', outsideDpClick);
    });
    dp.querySelector('#bg-dp-clear')?.addEventListener('click', async () => {
      await applySchedule(topicId, null);
      dp.remove();
      document.removeEventListener('click', outsideDpClick);
    });

    setTimeout(() => document.addEventListener('click', outsideDpClick), 60);
  }

  function outsideDpClick(e) {
    const dp = document.getElementById('bg-inline-dp');
    if (dp && !dp.contains(e.target) && !e.target.closest('.bg-sched-pill, .bg-sched-empty')) {
      dp.remove();
      document.removeEventListener('click', outsideDpClick);
    }
  }

  async function applySchedule(topicId, scheduledFor) {
    try {
      await api('topics/' + topicId, {
        method: 'PATCH',
        body: JSON.stringify({ scheduled_for: scheduledFor }),
      });
      const t = allTopics.find(x => x.id == topicId);
      if (t) t.scheduled_for = scheduledFor;
      toast(scheduledFor ? '✓ Dato planlagt' : 'Dato fjernet');
      renderTopics();
    } catch(e) {
      toast('Fejl: ' + e.message, 'err');
    }
  }

  // ── XSS helpers ───────────────────────────────────────────────────────────────
  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function escAttr(s) {
    return String(s || '').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── Init ──────────────────────────────────────────────────────────────────────
  loadTopics();

})();
