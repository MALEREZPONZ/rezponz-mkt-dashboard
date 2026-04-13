<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rzpa-wrap" id="page-sitemaps">
  <div class="rzpa-top-bar">
    <h1 class="rzpa-page-title">🗺️ Sitemaps</h1>
    <button id="rzpa-sm-new-btn" class="btn-primary">+ Nyt sitemap</button>
  </div>

  <!-- Opret / rediger sitemap (skjult som standard) -->
  <div id="rzpa-sm-form-card" class="rzpa-card" style="display:none">
    <h2 style="margin:0 0 14px;font-size:14px;font-weight:700" id="rzpa-sm-form-title">Nyt sitemap</h2>
    <div class="rzpa-form-grid" style="grid-template-columns:1fr 1fr 2fr;gap:12px;margin-bottom:12px">
      <div class="rzpa-field">
        <label>Navn *</label>
        <input type="text" id="sm-name" placeholder="fx Blog-sider" />
      </div>
      <div class="rzpa-field">
        <label>Slug (URL-del) *</label>
        <input type="text" id="sm-slug" placeholder="fx blog-sider" />
        <small style="color:#555;font-size:10px">/sitemap-<span id="sm-slug-preview">blog-sider</span>.xml</small>
      </div>
      <div class="rzpa-field">
        <label>Beskrivelse</label>
        <input type="text" id="sm-desc" placeholder="Valgfri intern note" />
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <button id="rzpa-sm-save-btn" class="btn-primary">Gem sitemap</button>
      <button id="rzpa-sm-cancel-btn" class="btn-ghost">Annuller</button>
    </div>
    <input type="hidden" id="sm-edit-id" value="" />
  </div>

  <!-- Liste over sitemaps -->
  <div class="rzpa-card" id="rzpa-sm-list-card">
    <div id="rzpa-sm-empty" style="display:none;padding:32px 0;text-align:center;color:#555">
      Ingen sitemaps endnu — klik "Nyt sitemap" for at komme i gang.
    </div>
    <div class="rzpa-table-wrap" id="rzpa-sm-table-wrap">
      <table class="rzpa-table">
        <thead>
          <tr>
            <th>Navn</th>
            <th>URL</th>
            <th style="text-align:center">URLs</th>
            <th>Oprettet</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="rzpa-sm-tbody">
          <tr><td colspan="5" class="rzpa-loading">Indlæser…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- URL-manager (vises når man vælger et sitemap) -->
  <div id="rzpa-sm-url-card" class="rzpa-card" style="display:none">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div>
        <h2 style="margin:0;font-size:14px;font-weight:700">
          🔗 URLs i: <span id="rzpa-sm-url-title">–</span>
        </h2>
        <div style="font-size:11px;color:#555;margin-top:3px">
          XML: <a id="rzpa-sm-xml-link" href="#" target="_blank" style="color:var(--neon)">–</a>
        </div>
      </div>
      <button id="rzpa-sm-close-urls" class="btn-ghost" style="font-size:11px">✕ Luk</button>
    </div>

    <!-- Tilføj én URL -->
    <details style="margin-bottom:12px">
      <summary style="cursor:pointer;font-size:12px;color:#999;padding:6px 0">+ Tilføj én URL</summary>
      <div style="padding:12px 0 4px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="rzpa-field" style="flex:2;min-width:240px;margin:0">
          <label style="font-size:11px">URL *</label>
          <input type="url" id="sm-url-single" placeholder="https://rezponz.dk/eksempel/" />
        </div>
        <div class="rzpa-field" style="width:80px;margin:0">
          <label style="font-size:11px">Prioritet</label>
          <select id="sm-url-priority">
            <option value="1.0">1.0</option>
            <option value="0.9">0.9</option>
            <option value="0.8">0.8</option>
            <option value="0.7">0.7</option>
            <option value="0.6">0.6</option>
            <option value="0.5" selected>0.5</option>
            <option value="0.4">0.4</option>
            <option value="0.3">0.3</option>
            <option value="0.2">0.2</option>
            <option value="0.1">0.1</option>
          </select>
        </div>
        <div class="rzpa-field" style="width:110px;margin:0">
          <label style="font-size:11px">Opdateringsfrekvens</label>
          <select id="sm-url-changefreq">
            <option value="always">always</option>
            <option value="hourly">hourly</option>
            <option value="daily">daily</option>
            <option value="weekly" selected>weekly</option>
            <option value="monthly">monthly</option>
            <option value="yearly">yearly</option>
            <option value="never">never</option>
          </select>
        </div>
        <div class="rzpa-field" style="width:140px;margin:0">
          <label style="font-size:11px">Sidst ændret</label>
          <input type="date" id="sm-url-lastmod" />
        </div>
        <button id="sm-url-add-btn" class="btn-primary" style="white-space:nowrap;height:34px">Tilføj URL</button>
      </div>
    </details>

    <!-- Bulk tilføj URLs -->
    <details style="margin-bottom:16px">
      <summary style="cursor:pointer;font-size:12px;color:#999;padding:6px 0">+ Tilføj flere URLs på én gang</summary>
      <div style="padding:12px 0 4px">
        <label style="font-size:11px;color:#999">Én URL pr. linje:</label>
        <textarea id="sm-url-bulk" rows="5" placeholder="https://rezponz.dk/side-1/&#10;https://rezponz.dk/side-2/&#10;https://rezponz.dk/side-3/" style="width:100%;margin:6px 0 8px;font-size:12px;font-family:monospace;background:#111;color:#e5e5e5;border:1px solid var(--border);border-radius:6px;padding:8px;box-sizing:border-box;resize:vertical"></textarea>
        <div style="display:flex;gap:10px;align-items:center">
          <div class="rzpa-field" style="width:80px;margin:0">
            <label style="font-size:11px">Prioritet</label>
            <select id="sm-bulk-priority">
              <option value="1.0">1.0</option><option value="0.9">0.9</option>
              <option value="0.8">0.8</option><option value="0.7">0.7</option>
              <option value="0.6">0.6</option><option value="0.5" selected>0.5</option>
              <option value="0.4">0.4</option><option value="0.3">0.3</option>
            </select>
          </div>
          <div class="rzpa-field" style="width:110px;margin:0">
            <label style="font-size:11px">Frekvens</label>
            <select id="sm-bulk-changefreq">
              <option value="always">always</option><option value="hourly">hourly</option>
              <option value="daily">daily</option><option value="weekly" selected>weekly</option>
              <option value="monthly">monthly</option><option value="yearly">yearly</option>
            </select>
          </div>
          <button id="sm-bulk-add-btn" class="btn-primary" style="height:34px">Tilføj alle</button>
          <span id="sm-bulk-result" style="font-size:11px;color:#4ade80"></span>
        </div>
      </div>
    </details>

    <!-- URL-tabel -->
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead>
          <tr>
            <th>URL</th>
            <th style="width:70px;text-align:center">Prioritet</th>
            <th style="width:100px">Frekvens</th>
            <th style="width:110px">Sidst ændret</th>
            <th style="width:40px"></th>
          </tr>
        </thead>
        <tbody id="rzpa-sm-url-tbody">
          <tr><td colspan="5" style="padding:20px;color:#555;text-align:center">Ingen URLs endnu.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  const api   = (path, opts = {}) => fetch(`<?php echo esc_js( rest_url( 'rzpa/v1' ) ); ?>${path}`, {
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' },
    ...opts,
  }).then(r => r.json());

  const el    = id => document.getElementById(id);
  let sitemaps = [];
  let currentSitemapId = null;

  // ── Load sitemaps ──────────────────────────────────────────────────────────
  async function loadSitemaps() {
    const data = await api('/sitemaps');
    sitemaps = Array.isArray(data) ? data : (data.data || []);
    renderSitemapTable();
  }

  function renderSitemapTable() {
    const tbody = el('rzpa-sm-tbody');
    const empty = el('rzpa-sm-empty');

    if (!sitemaps.length) {
      tbody.innerHTML = '';
      el('rzpa-sm-table-wrap').style.display = 'none';
      empty.style.display = 'block';
      return;
    }

    el('rzpa-sm-table-wrap').style.display = 'block';
    empty.style.display = 'none';

    tbody.innerHTML = sitemaps.map(s => `
      <tr>
        <td style="font-weight:600;color:#e5e5e5">${esc(s.name)}</td>
        <td>
          <a href="${esc(s.xml_url)}" target="_blank" style="font-size:11px;color:var(--neon);text-decoration:none;word-break:break-all">${esc(s.xml_url)}</a>
        </td>
        <td style="text-align:center">
          <span style="font-size:12px;font-weight:600;color:#4ade80">${parseInt(s.url_count) || 0}</span>
        </td>
        <td style="font-size:11px;color:#555">${s.created_at ? s.created_at.substring(0,10) : '–'}</td>
        <td style="text-align:right;white-space:nowrap">
          <button class="rzpa-sm-manage-btn" data-id="${s.id}" style="font-size:10px;padding:2px 8px;border-radius:6px;border:1px solid rgba(204,255,0,.25);background:rgba(204,255,0,.06);color:var(--neon);cursor:pointer;margin-right:4px">🔗 Håndter URLs</button>
          <button class="rzpa-sm-edit-btn" data-id="${s.id}" style="font-size:10px;padding:2px 8px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:#aaa;cursor:pointer;margin-right:4px">✏️</button>
          <button class="rzpa-sm-del-btn" data-id="${s.id}" data-name="${esc(s.name)}" style="font-size:10px;padding:2px 8px;border-radius:6px;border:1px solid rgba(239,68,68,.2);background:rgba(239,68,68,.05);color:#ef4444;cursor:pointer">🗑</button>
        </td>
      </tr>`).join('');

    // Event listeners
    tbody.querySelectorAll('.rzpa-sm-manage-btn').forEach(btn => {
      btn.addEventListener('click', () => openUrlManager(parseInt(btn.dataset.id)));
    });
    tbody.querySelectorAll('.rzpa-sm-edit-btn').forEach(btn => {
      btn.addEventListener('click', () => openEditForm(parseInt(btn.dataset.id)));
    });
    tbody.querySelectorAll('.rzpa-sm-del-btn').forEach(btn => {
      btn.addEventListener('click', () => deleteSitemap(parseInt(btn.dataset.id), btn.dataset.name));
    });
  }

  // ── Opret / rediger sitemap ──────────────────────────────────────────────
  el('rzpa-sm-new-btn').addEventListener('click', () => {
    el('sm-edit-id').value = '';
    el('sm-name').value    = '';
    el('sm-slug').value    = '';
    el('sm-desc').value    = '';
    el('sm-slug-preview').textContent = 'dit-slug';
    el('rzpa-sm-form-title').textContent = 'Nyt sitemap';
    el('rzpa-sm-form-card').style.display = 'block';
    el('sm-name').focus();
  });

  el('rzpa-sm-cancel-btn').addEventListener('click', () => {
    el('rzpa-sm-form-card').style.display = 'none';
  });

  // Auto-generér slug fra navn
  el('sm-name').addEventListener('input', () => {
    if ( el('sm-edit-id').value ) return; // Redigering: rør ikke slug
    const slug = el('sm-name').value.toLowerCase()
      .replace(/[æ]/g,'ae').replace(/[ø]/g,'oe').replace(/[å]/g,'aa')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    el('sm-slug').value = slug;
    el('sm-slug-preview').textContent = slug || 'dit-slug';
  });
  el('sm-slug').addEventListener('input', () => {
    el('sm-slug-preview').textContent = el('sm-slug').value || 'dit-slug';
  });

  el('rzpa-sm-save-btn').addEventListener('click', async () => {
    const btn  = el('rzpa-sm-save-btn');
    const name = el('sm-name').value.trim();
    const slug = el('sm-slug').value.trim();
    const desc = el('sm-desc').value.trim();
    const editId = el('sm-edit-id').value;

    if (!name || !slug) { alert('Navn og slug er påkrævet.'); return; }

    btn.disabled = true; btn.textContent = '⏳ Gemmer…';
    try {
      const body = JSON.stringify({ name, slug, description: desc });
      let res;
      if (editId) {
        res = await api(`/sitemaps/${editId}`, { method: 'PUT', body });
      } else {
        res = await api('/sitemaps', { method: 'POST', body });
      }

      if (res.ok || res.sitemap) {
        btn.textContent = '✓ Gemt';
        setTimeout(() => { el('rzpa-sm-form-card').style.display = 'none'; loadSitemaps(); btn.disabled=false; btn.textContent='Gem sitemap'; }, 700);
      } else {
        alert(res.message || 'Fejl ved gem');
        btn.disabled = false; btn.textContent = 'Gem sitemap';
      }
    } catch(e) {
      alert('Fejl: ' + e.message);
      btn.disabled = false; btn.textContent = 'Gem sitemap';
    }
  });

  function openEditForm(id) {
    const s = sitemaps.find(x => x.id == id);
    if (!s) return;
    el('sm-edit-id').value = s.id;
    el('sm-name').value    = s.name;
    el('sm-slug').value    = s.slug;
    el('sm-desc').value    = s.description || '';
    el('sm-slug-preview').textContent = s.slug;
    el('rzpa-sm-form-title').textContent = 'Rediger sitemap';
    el('rzpa-sm-form-card').style.display = 'block';
    el('sm-name').focus();
  }

  async function deleteSitemap(id, name) {
    if (!confirm(`Slet sitemap "${name}" og alle dets URLs? Dette kan ikke fortrydes.`)) return;
    await api(`/sitemaps/${id}`, { method: 'DELETE' });
    if (currentSitemapId === id) {
      el('rzpa-sm-url-card').style.display = 'none';
      currentSitemapId = null;
    }
    loadSitemaps();
  }

  // ── URL-manager ──────────────────────────────────────────────────────────
  async function openUrlManager(id) {
    currentSitemapId = id;
    const res = await api(`/sitemaps/${id}`);
    const s = res.data || res;
    el('rzpa-sm-url-title').textContent = s.name;
    el('rzpa-sm-xml-link').href = s.xml_url;
    el('rzpa-sm-xml-link').textContent = s.xml_url;
    renderUrlTable(s.urls || []);
    el('rzpa-sm-url-card').style.display = 'block';
    el('rzpa-sm-url-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  el('rzpa-sm-close-urls').addEventListener('click', () => {
    el('rzpa-sm-url-card').style.display = 'none';
    currentSitemapId = null;
  });

  function renderUrlTable(urls) {
    const tbody = el('rzpa-sm-url-tbody');
    if (!urls.length) {
      tbody.innerHTML = `<tr><td colspan="5" style="padding:20px;color:#555;text-align:center">Ingen URLs endnu – tilføj nedenfor.</td></tr>`;
      return;
    }
    tbody.innerHTML = urls.map(u => `
      <tr>
        <td style="font-size:12px;word-break:break-all"><a href="${esc(u.url)}" target="_blank" style="color:#e5e5e5;text-decoration:none">${esc(u.url)}</a></td>
        <td style="text-align:center;font-size:12px;color:#999">${u.priority}</td>
        <td style="font-size:12px;color:#999">${u.changefreq}</td>
        <td style="font-size:11px;color:#555">${u.lastmod || '–'}</td>
        <td>
          <button class="rzpa-sm-del-url-btn" data-id="${u.id}" style="font-size:10px;padding:1px 7px;border-radius:6px;border:1px solid rgba(239,68,68,.2);background:rgba(239,68,68,.05);color:#ef4444;cursor:pointer">✕</button>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('.rzpa-sm-del-url-btn').forEach(btn => {
      btn.addEventListener('click', () => deleteUrl(parseInt(btn.dataset.id)));
    });
  }

  // Tilføj én URL
  el('sm-url-add-btn').addEventListener('click', async () => {
    if (!currentSitemapId) return;
    const btn       = el('sm-url-add-btn');
    const url       = el('sm-url-single').value.trim();
    const priority  = parseFloat(el('sm-url-priority').value);
    const changefreq= el('sm-url-changefreq').value;
    const lastmod   = el('sm-url-lastmod').value || null;

    if (!url) { alert('URL er påkrævet.'); return; }

    btn.disabled = true; btn.textContent = '⏳';
    try {
      const res = await api(`/sitemaps/${currentSitemapId}/urls`, {
        method: 'POST',
        body: JSON.stringify({ url, priority, changefreq, lastmod }),
      });
      if (res.ok || res.urls) {
        renderUrlTable(res.urls || []);
        el('sm-url-single').value = '';
        loadSitemaps(); // Opdater tæller
      } else {
        alert(res.message || 'Fejl ved tilføjelse');
      }
    } catch(e) { alert('Fejl: ' + e.message); }
    btn.disabled = false; btn.textContent = 'Tilføj URL';
  });

  // Bulk tilføj
  el('sm-bulk-add-btn').addEventListener('click', async () => {
    if (!currentSitemapId) return;
    const btn       = el('sm-bulk-add-btn');
    const raw       = el('sm-url-bulk').value.trim();
    const priority  = parseFloat(el('sm-bulk-priority').value);
    const changefreq= el('sm-bulk-changefreq').value;

    if (!raw) { alert('Indsæt mindst én URL.'); return; }

    btn.disabled = true; btn.textContent = '⏳';
    try {
      const res = await api(`/sitemaps/${currentSitemapId}/urls/bulk`, {
        method: 'POST',
        body: JSON.stringify({ urls: raw, priority, changefreq }),
      });
      if (res.ok || res.urls) {
        el('sm-bulk-result').textContent = `✓ ${res.added || 0} URLs tilføjet`;
        renderUrlTable(res.urls || []);
        el('sm-url-bulk').value = '';
        loadSitemaps();
        setTimeout(() => { el('sm-bulk-result').textContent = ''; }, 3000);
      } else {
        alert(res.message || 'Fejl');
      }
    } catch(e) { alert('Fejl: ' + e.message); }
    btn.disabled = false; btn.textContent = 'Tilføj alle';
  });

  async function deleteUrl(urlId) {
    await api(`/sitemaps/urls/${urlId}`, { method: 'DELETE' });
    // Genindlæs URL-liste
    const res = await api(`/sitemaps/${currentSitemapId}`);
    renderUrlTable((res.data || res).urls || []);
    loadSitemaps();
  }

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // Start
  loadSitemaps();
})();
</script>
