/* ═══════════════════════════════════════════════════
   Rezponz Analytics – Dashboard JavaScript
   ═══════════════════════════════════════════════════ */

const RZPA_App = (() => {

  // ── Helpers ───────────────────────────────────────

  const API = RZPA.apiBase;
  const HDR = { 'Content-Type': 'application/json', 'X-WP-Nonce': RZPA.nonce };
  const CACHE_TTL = 5 * 60 * 1000; // 5 min

  // Server-preload: PHP-data indlejret direkte i HTML — nul HTTP-kald
  const _preload = RZPA.preload || {};

  // Matcher en API-sti til indlejret PHP-data (ignorerer ?days=X på første load)
  function _matchPreload(path) {
    if (path.includes('/dashboard/overview'))        return _preload.dashboard_overview;
    if (path.includes('/meta/summary'))              return _preload.meta_summary;
    if (path.includes('/meta/campaigns'))            return _preload.meta_campaigns;
    if (path.includes('/meta/has-data'))             return { has_data: !!_preload.meta_has_data, days: 30 };
    if (path.includes('/seo/summary'))               return _preload.seo_summary;
    if (path.includes('/seo/keywords'))              return _preload.seo_keywords;
    if (path.includes('/seo/pages'))                 return _preload.seo_pages;
    return undefined;
  }

  // Bruges-én-gang flag pr. nøgle
  const _preloadUsed = {};

  async function api(path, opts = {}) {
    const isGet = !opts.method || opts.method === 'GET';

    // ① PHP server-preload – ingen netværksanmodning overhovedet
    if (isGet && !_preloadUsed[path]) {
      const hit = _matchPreload(path);
      if (hit !== undefined) {
        _preloadUsed[path] = true;
        return { success: true, data: hit };
      }
    }

    // ② Browser sessionStorage – data genbruges i 5 min ved sidesnak
    const cacheKey = 'rzpa||' + path;
    if (isGet) {
      try {
        const raw = sessionStorage.getItem(cacheKey);
        if (raw) {
          const { data, ts } = JSON.parse(raw);
          if (Date.now() - ts < CACHE_TTL) return data;
        }
      } catch(e) {}
    }

    // ③ Rigtig REST-kald (kun når data mangler eller er forældet)
    const { headers: extraHdr, ...restOpts } = opts;
    const res  = await fetch(API + path, { headers: { ...HDR, ...(extraHdr||{}) }, ...restOpts });
    const data = await res.json();

    if (isGet) {
      try { sessionStorage.setItem(cacheKey, JSON.stringify({ data, ts: Date.now() })); }
      catch(e) {}
    }
    return data;
  }

  // Ryd browser-cache for et bestemt path-prefix (bruges efter sync)
  function clearCache(prefix) {
    try {
      Object.keys(sessionStorage)
        .filter(k => k.startsWith('rzpa||' + prefix))
        .forEach(k => sessionStorage.removeItem(k));
    } catch(e) {}
  }

  function fmt(n, d = 0) {
    if (n == null || n === '') return '–';
    return Number(n).toLocaleString('da-DK', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function el(id) { return document.getElementById(id); }

  function setText(id, value) {
    const e = el(id); if (e) e.textContent = value;
  }

  function renderKPI(id, value, extra = '') {
    const e = el(id);
    if (e) { e.innerHTML = value; if (extra && el(id + '_sub')) el(id + '_sub').textContent = extra; }
  }

  function roasClass(v) {
    return v >= 2.5 ? 'roas-high' : v >= 1.5 ? 'roas-mid' : 'roas-low';
  }

  function badgeHtml(status) {
    const cls = ['ACTIVE','ENABLE'].includes(status?.toUpperCase()) ? 'active' : 'paused';
    const label = status === 'ENABLE' ? 'ACTIVE' : (status || '–');
    return `<span class="badge badge-${cls}">${label}</span>`;
  }

  function sortTable(arr, key, dir) {
    return [...arr].sort((a, b) => {
      const av = isNaN(a[key]) ? String(a[key]) : Number(a[key]);
      const bv = isNaN(b[key]) ? String(b[key]) : Number(b[key]);
      return dir === 'asc' ? (av > bv ? 1 : -1) : (av < bv ? 1 : -1);
    });
  }

  function makeSortable(tbodyId, data, renderFn) {
    let sortKey = null, sortDir = 'desc';
    document.querySelectorAll(`#${tbodyId}`).forEach(() => {});
    document.querySelectorAll(`[data-sort]`).forEach(th => {
      th.addEventListener('click', () => {
        const k = th.dataset.sort;
        if (sortKey === k) sortDir = sortDir === 'desc' ? 'asc' : 'desc';
        else { sortKey = k; sortDir = 'desc'; }
        document.querySelectorAll('[data-sort]').forEach(h => h.textContent = h.textContent.replace(/ [↑↓]$/, ''));
        th.textContent += sortDir === 'asc' ? ' ↑' : ' ↓';
        const sorted = sortTable(data, sortKey, sortDir);
        const tbody = el(tbodyId);
        if (tbody) tbody.innerHTML = sorted.map(renderFn).join('');
      });
    });
  }

  // ── Chart helpers ──────────────────────────────────

  const CHART_DEFAULTS = {
    color: '#CCFF00',
    grid:  'rgba(255,255,255,0.06)',
    text:  '#666',
  };

  function barChart(canvasId, labels, datasets, opts = {}) {
    const canvas = el(canvasId);
    if (!canvas) return;
    // destroy existing
    if (canvas._chart) canvas._chart.destroy();
    canvas._chart = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: datasets.length > 1, labels: { color: '#888', font: { size: 11 } } } },
        scales: {
          x: { grid: { color: CHART_DEFAULTS.grid, drawBorder: false }, ticks: { color: CHART_DEFAULTS.text, font: { size: 10 } } },
          y: { grid: { color: CHART_DEFAULTS.grid, drawBorder: false }, ticks: { color: CHART_DEFAULTS.text, font: { size: 10 } }, ...( opts.yTick ? { ticks: { ...{color: CHART_DEFAULTS.text, font:{size:10}}, callback: opts.yTick } } : {} ) },
          ...(opts.y2 ? { y2: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: CHART_DEFAULTS.text, font: { size: 10 } } } } : {}),
        },
        ...opts.extra,
      },
    });
  }

  function lineChart(canvasId, labels, datasets) {
    const canvas = el(canvasId);
    if (!canvas) return;
    if (canvas._chart) canvas._chart.destroy();
    canvas._chart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: CHART_DEFAULTS.grid }, ticks: { color: CHART_DEFAULTS.text, font: { size: 10 } } },
          y: { grid: { color: CHART_DEFAULTS.grid }, ticks: { color: CHART_DEFAULTS.text, font: { size: 10 } } },
        },
      },
    });
  }

  function comboChart(canvasId, labels, datasets, opts = {}) {
    const canvas = el(canvasId);
    if (!canvas) return;
    if (canvas._chart) canvas._chart.destroy();
    canvas._chart = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            labels: { color: '#888', font: { size: 11 }, boxWidth: 12, padding: 16 }
          },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                if (ctx.dataset.yAxisID === 'y2') return ' ROAS: ' + v.toFixed(2) + 'x';
                return ' ' + ctx.dataset.label + ': ' + v.toLocaleString('da-DK') + ' kr';
              }
            }
          }
        },
        scales: {
          x: {
            stacked: true,
            grid: { color: CHART_DEFAULTS.grid },
            ticks: { color: CHART_DEFAULTS.text, font: { size: 10 }, maxTicksLimit: 12 }
          },
          y: {
            stacked: true,
            grid: { color: CHART_DEFAULTS.grid },
            ticks: { color: CHART_DEFAULTS.text, font: { size: 10 }, callback: v => (v>=1000?(v/1000).toFixed(0)+'k':v) }
          },
          ...(opts.y2 ? {
            y2: {
              position: 'right',
              grid: { drawOnChartArea: false },
              ticks: { color: '#CCFF00', font: { size: 10 }, callback: v => v.toFixed(1) + 'x' },
              min: 0, suggestedMax: 5,
            }
          } : {}),
        },
      },
    });
  }

  function hBarChart(canvasId, labels, data) {
    const canvas = el(canvasId);
    if (!canvas) return;
    if (canvas._chart) canvas._chart.destroy();
    canvas._chart = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets: [{ data, backgroundColor: '#CCFF00', borderRadius: 4 }] },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: CHART_DEFAULTS.grid }, ticks: { color: CHART_DEFAULTS.text, font: { size: 10 } } },
          y: { grid: { display: false }, ticks: { color: '#aaa', font: { size: 10 } } },
        },
      },
    });
  }

  // ── Sync status ────────────────────────────────────

  async function loadSyncStatus(containerId) {
    const container = el(containerId);
    if (!container) return;
    try {
      const r = await api('/status');
      const syncs = r.data?.syncs || [];
      container.innerHTML = syncs.map(s => {
        const ago = timeAgo(s.synced_at);
        return `<div class="rzpa-sync-item">
          <div class="rzpa-sync-dot ${s.status !== 'success' ? 'error' : ''}"></div>
          <span>${s.source.replace(/_/g, ' ')}</span>
          <span style="color:#444">·</span>
          <span>${ago}</span>
        </div>`;
      }).join('');
    } catch(e) {}
  }

  function timeAgo(dt) {
    if (!dt) return '–';
    const diff = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (diff < 60) return diff + 's siden';
    if (diff < 3600) return Math.floor(diff/60) + 'm siden';
    if (diff < 86400) return Math.floor(diff/3600) + 't siden';
    return Math.floor(diff/86400) + 'd siden';
  }

  // ── Sync all button ────────────────────────────────

  function initSyncBtn() {
    const btn = el('rzpa-sync-btn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      btn.textContent = 'Synkroniserer…';
      try {
        await api('/sync', { method: 'POST' });
        clearCache('/'); // Ryd hele browser-cachen
        window.location.reload();
      } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Sync';
        alert('Sync fejlede');
      }
    });
  }

  // ── Date filter ────────────────────────────────────

  function initDateFilter(containerId, onChange) {
    const container = el(containerId);
    if (!container) return;
    container.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => {
        container.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        onChange(parseInt(btn.dataset.days));
      });
    });
  }

  // ════════════════════════════════════════════════════
  // PAGE: DASHBOARD
  // ════════════════════════════════════════════════════

  async function initDashboard() {
    let days = 30;
    loadSyncStatus('rzpa-sync-status');
    initSyncBtn();
    initDateFilter('rzpa-date-filter', d => { days = d; loadDashboard(d); });
    loadDashboard(days);
  }

  async function loadDashboard(days) {
    // Ét kombineret kald i stedet for 10 – meget hurtigere
    let overview;
    try { overview = await api(`/dashboard/overview?days=${days}`); }
    catch(e) { overview = {}; }
    const d = overview.data || {};

    const seoD  = d.seo            || {};
    const metaD = d.meta           || {};
    const snapD = d.snap           || {};
    const ttD   = d.tiktok         || {};
    const aiD   = d.ai             || {};
    const kwD   = d.keywords       || [];
    const mcD   = d.meta_campaigns || [];
    const scD   = d.snap_campaigns || [];
    const tcD   = d.tt_campaigns   || [];
    const trD   = d.trends         || [];

    // Alias så resten af koden stadig virker
    const s = seoD, m = metaD, sn = snapD, t = ttD, a = aiD;
    const kw = { data: kwD }, mc = { data: mcD }, sc = { data: scD },
          tc = { data: tcD }, trends = { data: trD };
    const seo = {data:s}, meta = {data:m}, snap = {data:sn}, tt = {data:t}, ai = {data:a};

    const metaOk = m.configured !== false;
    const snapOk = sn.configured !== false;
    const ttOk   = t.configured  !== false;

    // ── Fortæller-kort på dashboard ──────────────────
    const storyEl = el('rzpa-dashboard-story');
    if (storyEl) {
      const metaSpend  = metaOk ? (parseFloat(m.total_spend)||0) : 0;
      const seoClicks  = parseInt(s.total_clicks)||0;
      const metaCtr    = metaOk ? (parseFloat(m.avg_ctr)||0) : 0;
      const top10      = parseInt(s.keywords_top10)||0;
      let story = '';
      if (metaSpend > 0 || seoClicks > 0) {
        story += metaSpend > 0
          ? `Du brugte <strong>${fmt(metaSpend,0)} kr</strong> på Meta-annoncer i perioden. `
          : '';
        story += seoClicks > 0
          ? `<strong>${fmt(seoClicks)}</strong> fandt jer via Google — gratis besøg. `
          : '';
        story += top10 > 0
          ? `I er på Googles 1. side for <strong>${top10} søgeord</strong>. `
          : '';
        story += metaCtr > 0
          ? `Jeres annonce-klikprocent er <strong>${fmt(metaCtr,2)}%</strong> ${metaCtr>=1.5?'— det er godt! 🚀':metaCtr>=0.5?'— der er plads til forbedring 💡':'— overvej at opdatere annoncerne ⚠️'}.`
          : '';
        storyEl.innerHTML = story;
        storyEl.classList.remove('hidden');
      }
    }

    // ── Platform-status-kort på dashboard ────────────
    // Meta spend og clicks
    if (metaOk && parseFloat(m.total_spend) > 0) {
      const ctr = parseFloat(m.avg_ctr)||0;
      const pill = el('meta-plat-status');
      if (pill) {
        pill.textContent = ctr>=1.5?'✓ Kører godt':ctr>=0.5?'⚠ Middel':'✗ Lav klikprocent';
        pill.className = 'rzpa-pill ' + (ctr>=1.5?'good':ctr>=0.5?'warn':'bad');
      }
      setText('meta-plat-clicks', fmt(m.total_clicks));
      // roi_meta_roas already set elsewhere – override with CTR
      const roasEl = el('roi_meta_roas');
      if (roasEl) { roasEl.textContent = fmt(m.avg_ctr,2)+'%'; roasEl.className=''; }
    }
    // SEO
    const seoStatus = el('seo-plat-status');
    if (seoStatus) {
      const top10n = parseInt(s.keywords_top10)||0;
      seoStatus.textContent = top10n>10?'✓ God synlighed':top10n>0?'⚠ Kan forbedres':'Ingen data';
      seoStatus.className = 'rzpa-pill '+(top10n>10?'good':top10n>0?'warn':'neutral');
    }
    setText('seo-plat-clicks', fmt(s.total_clicks)||'–');
    setText('seo-plat-top10', fmt(s.keywords_top10)||'–');
    setText('seo-plat-top3', fmt(s.keywords_top3)||'–');
    // AI
    const aiStatus = el('ai-plat-status');
    const aiCount  = parseInt(a.ai_overview_count)||0;
    if (aiStatus) {
      aiStatus.textContent = aiCount>5?'✓ God AI-synlighed':aiCount>0?'⚠ Delvis':'Ikke målt';
      aiStatus.className = 'rzpa-pill '+(aiCount>5?'good':aiCount>0?'warn':'neutral');
    }
    setText('ai-plat-count',    fmt(a.ai_overview_count)||'–');
    setText('ai-plat-snippets', fmt(a.featured_snippet_count)||'–');
    setText('ai-plat-paa',      fmt(a.paa_count)||'–');

    const totalSpend = (metaOk ? parseFloat(m.total_spend)||0 : 0)
                     + (snapOk ? parseFloat(sn.total_spend)||0 : 0)
                     + (ttOk   ? parseFloat(t.total_spend)||0  : 0);
    const metaRoas = metaOk ? (parseFloat(m.avg_roas) || 0) : 0;
    const snapEng  = snapOk ? (parseFloat(sn.avg_engagement_rate) || 0) : 0;
    const ttRoas   = ttOk   ? (parseFloat(t.avg_roas) || 0)  : 0;

    // ROI Spotlight – vis "Ikke opsat" for ukonfigurerede platforme
    const setRoas = (id, val, clsName) => {
      const e = el(id);
      if (e) { e.textContent = val; e.className = 'roas-value ' + clsName; }
    };
    if (metaOk) {
      setRoas('roi_meta_roas', fmt(metaRoas,2)+'x', roasClass(metaRoas));
      setText('roi_meta_spend', fmt(m.total_spend,0)+' kr');
    } else {
      setRoas('roi_meta_roas', 'Ikke opsat', 'roas-low');
      setText('roi_meta_spend', '–');
    }
    if (snapOk) {
      setRoas('roi_snap_engagement', fmt(snapEng,2)+'%', snapEng>=3?'roas-high':snapEng>=1.5?'roas-mid':'roas-low');
      setText('roi_snap_spend', fmt(sn.total_spend,0)+' kr');
    } else {
      setRoas('roi_snap_engagement', 'Ikke opsat', 'roas-low');
      setText('roi_snap_spend', '–');
    }
    if (ttOk) {
      setRoas('roi_tt_roas', fmt(ttRoas,2)+'x', roasClass(ttRoas));
      setText('roi_tt_spend', fmt(t.total_spend,0)+' kr');
    } else {
      setRoas('roi_tt_roas', 'Ikke opsat', 'roas-low');
      setText('roi_tt_spend', '–');
    }

    // Dynamisk afkast-forklaring under kortene (kun Meta hvis konfigureret)
    const explainBar  = el('rzpa-roas-explain');
    const explainText = el('rzpa-roas-explain-text');
    if (explainBar && explainText && metaOk && metaRoas > 0) {
      const earned = (parseFloat(m.total_spend)||0) * metaRoas;
      explainText.textContent = `💰 Meta: Du brugte ${fmt(m.total_spend,0)} kr og fik ca. ${fmt(earned,0)} kr i omsætning tilbage (ROAS ${fmt(metaRoas,2)}x). Under 1x = taber penge · 2,5x+ = rigtig god.`;
      explainBar.style.display = 'flex';
    } else if (explainBar) {
      explainBar.style.display = 'none';
    }

    // KPIs – kun konfigurerede platforme tælles med
    const configuredCount = (metaOk?1:0)+(snapOk?1:0)+(ttOk?1:0);
    const avgRoas = totalSpend > 0 && (metaOk||ttOk)
      ? ((metaOk?(m.total_spend||0)*metaRoas:0) + (ttOk?(t.total_spend||0)*ttRoas:0))
        / ((metaOk?(m.total_spend||0):0) + (ttOk?(t.total_spend||0):0) || 1)
      : 0;
    const perDay = days > 0 ? Math.round(totalSpend / days) : 0;
    renderKPI('kpi_spend',
      totalSpend > 0 ? fmt(totalSpend,0)+' kr' : '–',
      perDay > 0 ? '≈ '+fmt(perDay,0)+' kr/dag · Afkast: '+avgRoas.toFixed(2)+'x'
                 : configuredCount === 0 ? 'Ingen platforme sat op endnu' : 'Meta + Snapchat + TikTok');
    renderKPI('kpi_seo_clicks', fmt(s.total_clicks)||'–',
      s.keywords_top10 > 0 ? (s.keywords_top10)+' søgeord på Googles 1. side' : 'Gratis besøg fra Google');
    renderKPI('kpi_ai', fmt(a.ai_overview_count)||'–',
      (a.featured_snippet_count||0)+' gange vist som fremhævet svar');
    renderKPI('kpi_campaigns',
      ((metaOk?(m.campaign_count||0):0)+(snapOk?(sn.campaign_count||0):0)+(ttOk?(t.campaign_count||0):0)) || '–');

    // Time-series combo chart – always render, fallback til mock hvis ingen data
    let td = trends.data || [];
    if (!td.length) {
      // Generer simpel mock til visning (14 dage)
      const now = Date.now();
      td = Array.from({ length: 14 }, (_, i) => {
        const dt = new Date(now - (13 - i) * 86400000);
        const base = 800 + Math.random() * 400;
        return {
          date: dt.toISOString().slice(0, 10),
          meta_spend:   Math.round(base * (0.5 + Math.random() * 0.3)),
          snap_spend:   Math.round(base * (0.15 + Math.random() * 0.1)),
          tiktok_spend: Math.round(base * (0.2 + Math.random() * 0.15)),
          avg_roas:     (1.8 + Math.random() * 1.4).toFixed(2),
        };
      });
    }
    const labels = td.map(d => d.date.slice(5));
    comboChart('chart_trends', labels, [
      { type: 'bar',  label: 'Meta',     data: td.map(d => d.meta_spend),
        backgroundColor: 'rgba(24,119,242,0.75)', stack: 'spend', borderRadius: 2 },
      { type: 'bar',  label: 'Snapchat', data: td.map(d => d.snap_spend),
        backgroundColor: 'rgba(255,200,0,0.8)', stack: 'spend', borderRadius: 2 },
      { type: 'bar',  label: 'TikTok',  data: td.map(d => d.tiktok_spend),
        backgroundColor: 'rgba(255,45,85,0.75)', stack: 'spend', borderRadius: 2 },
      { type: 'line', label: 'ROAS',    data: td.map(d => parseFloat(d.avg_roas||0)),
        borderColor: '#CCFF00', backgroundColor: 'rgba(204,255,0,0.06)',
        fill: true,
        yAxisID: 'y2', tension: 0.4, pointRadius: 2, borderWidth: 2, pointHoverRadius: 5 },
    ], { y2: true });

    // SEO horizontal bar
    const kwData = (kw.data||[]).slice(0,8);
    hBarChart('chart_seo', kwData.map(k=>k.keyword), kwData.map(k=>k.total_clicks));

    // Top campaigns across all platforms sorted by ROAS
    const allCamps = [
      ...(mc.data||[]).map(c => ({...c, platform: 'Meta'})),
      ...(sc.data||[]).map(c => ({...c, roas: 0, platform: 'Snap'})),
      ...(tc.data||[]).map(c => ({...c, platform: 'TikTok'})),
    ].sort((a, b) => (parseFloat(b.roas)||0) - (parseFloat(a.roas)||0));

    const tbody = el('top_campaigns_tbody');
    if (tbody) {
      const platClass = p => p==='Meta'?'plat-meta':p==='Snap'?'plat-snap':'plat-tiktok';
      // Sort by clicks for Meta (more meaningful than ROAS for service company)
      const sortedCamps = [...allCamps].sort((a,b)=>(parseInt(b.clicks)||0)-(parseInt(a.clicks)||0));
      tbody.innerHTML = sortedCamps.slice(0,8).map(c => {
        const ctr = parseFloat(c.ctr)||0;
        const perfHtml = ctr>0
          ? `<span class="rzpa-pill ${ctr>=1.5?'good':ctr>=0.5?'warn':'bad'}">${fmt(ctr,2)}%</span>`
          : (c.roas>0?`<span style="color:var(--neon);font-weight:700">${fmt(c.roas,2)}x</span>`:'–');
        return `<tr>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500">${c.campaign_name.replace(/Rezponz\s*[–-]\s*/i,'')}</td>
          <td><span class="${platClass(c.platform)}" style="font-size:11px;font-weight:700;text-transform:uppercase">${c.platform}</span></td>
          <td style="color:var(--text-muted)">${fmt(c.spend,0)} kr</td>
          <td>${perfHtml}</td>
        </tr>`;
      }).join('') || '<tr><td colspan="4" class="rzpa-empty">Ingen kampagnedata endnu</td></tr>';
    }
  }

  // ════════════════════════════════════════════════════
  // PAGE: SEO
  // ════════════════════════════════════════════════════

  async function initSEO() {
    let days = 30, allKw = [], allPages = [], contentMap = {};

    // Hent content map (side/blog typer) én gang
    api('/seo/content-map').then(r => { contentMap = r.data || {}; });

    initDateFilter('rzpa-date-filter', d => { days = d; loadSEO(d); });
    loadSEO(days);
    loadSEOMonthly();

    el('rzpa-seo-sync')?.addEventListener('click', async () => {
      const btn = el('rzpa-seo-sync');
      if (btn) { btn.disabled = true; btn.textContent = 'Henter…'; }
      const res = await api('/seo/sync', { method: 'POST' });
      if (btn) { btn.disabled = false; btn.textContent = '⟳ Hent data'; }
      if (res && res.success && res.data && res.data.success === false && res.data.error) {
        const story = el('seo-story');
        if (story) story.innerHTML =
          `<p style="color:#ff6b6b;font-weight:600">❌ Google API fejl</p>` +
          `<p style="color:#ccc;margin:6px 0">${res.data.error}</p>` +
          `<p style="font-size:12px;color:#888;margin-top:8px">` +
          `Tjek at <strong>Site URL</strong> i Indstillinger matcher præcis din Google Search Console.<br>` +
          `Prøv: <code>https://www.rezponz.dk</code>, <code>https://rezponz.dk</code> eller <code>sc-domain:rezponz.dk</code></p>`;
        return;
      }
      clearCache('/seo/');
      clearCache('/dashboard/overview');
      loadSEO(days);
      loadSEOMonthly();
    });

    // AI-analyse knap
    el('seo-ai-refresh')?.addEventListener('click', async () => {
      const btn = el('seo-ai-refresh');
      const content = el('seo-ai-content');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Analyserer…'; }
      if (content) content.innerHTML = '<span style="color:#666">Sender data til AI — tager 10-20 sekunder…</span>';
      const res = await api('/seo/ai-analysis', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ keywords: allKw.slice(0,20), pages: allPages.slice(0,15) }),
      });
      if (btn) { btn.disabled = false; btn.textContent = '✨ Analysér nu'; }
      if (!content) return;
      const d = res.data || {};
      if (d.error) {
        content.innerHTML = `<span style="color:#ff6b6b">⚠️ ${d.error}</span>`;
      } else if (d.analysis) {
        // Formatér nummererede punkter
        const lines = d.analysis.split('\n').filter(l => l.trim());
        content.innerHTML = lines.map(l =>
          l.match(/^\d+\./) ?
            `<div style="display:flex;gap:10px;margin-bottom:12px;padding:12px;background:var(--bg-200);border-radius:8px;border-left:3px solid var(--neon)">
              <div style="color:var(--neon);font-weight:700;flex-shrink:0">${l.match(/^\d+/)[0]}.</div>
              <div style="color:#ccc">${l.replace(/^\d+\.\s*/, '')}</div>
            </div>` : `<p style="color:#888;margin:4px 0">${l}</p>`
        ).join('');
      }
    });

    async function loadSEO(d) {
      const [sum, kw, pages, cmp] = await Promise.all([
        api(`/seo/summary?days=${d}`),
        api(`/seo/keywords?days=${d}&limit=50`),
        api(`/seo/pages?days=${d}&limit=30`),
        api(`/seo/comparison?days=${d}`),
      ]);
      const s   = sum.data || {};
      allKw     = kw.data  || [];
      allPages  = pages.data || [];
      const cur  = cmp.data?.current  || {};
      const prev = cmp.data?.previous || {};

      // KPI med trend-pile
      const trendArrow = (cur, prev) => {
        if (!prev || prev == 0) return '';
        const pct = Math.round(((cur - prev) / prev) * 100);
        if (pct > 0)  return ` <span style="color:#4ade80;font-size:12px">↑ ${pct}%</span>`;
        if (pct < 0)  return ` <span style="color:#ff6b6b;font-size:12px">↓ ${Math.abs(pct)}%</span>`;
        return '';
      };

      renderKPI('kpi_clicks', fmt(s.total_clicks) + trendArrow(s.total_clicks, prev.clicks));
      renderKPI('kpi_impr',   fmt(s.total_impressions) + trendArrow(s.total_impressions, prev.impressions));
      renderKPI('kpi_ctr',    fmt(s.avg_ctr, 2) + '%');
      renderKPI('kpi_top10',  fmt(s.keywords_top10));
      setText('kpi_top10_sub', (s.keywords_top3||0) + ' søgeord i top 3 · ' + fmt(s.keywords_top10||0) + ' på side 1');

      // Story
      const seoStory = el('seo-story');
      if (seoStory && parseInt(s.total_clicks) > 0) {
        const clicks = parseInt(s.total_clicks), top10n = parseInt(s.keywords_top10)||0, top3n = parseInt(s.keywords_top3)||0;
        const trendNote = prev.clicks > 0 ? (clicks > prev.clicks ?
          ` Det er <strong style="color:#4ade80">${Math.round(((clicks-prev.clicks)/prev.clicks)*100)}% mere</strong> end forrige periode. 📈` :
          ` Det er <strong style="color:#ff6b6b">${Math.round(((prev.clicks-clicks)/prev.clicks)*100)}% færre</strong> end forrige periode.`) : '';
        seoStory.innerHTML = `<strong>${fmt(clicks)}</strong> personer fandt jer på Google i perioden — helt gratis!${trendNote} `
          + `I er på Googles 1. side for <strong>${top10n} søgeord</strong>, og <strong>${top3n} af dem er i top 3</strong>. `
          + (top10n >= 10 ? '✅ God organisk synlighed — fortsæt med at opdatere indholdet.' : '💡 Se Action Center nedenfor for konkrete tips til at få flere søgeord på side 1.');
        seoStory.classList.remove('hidden');
      }

      // Health bar
      const seoHealth = el('rzpa-seo-health');
      if (seoHealth) {
        const top10n = parseInt(s.keywords_top10)||0, clicks = parseInt(s.total_clicks)||0;
        if (!clicks) {
          seoHealth.className = 'rzpa-health health-empty';
          seoHealth.innerHTML = '<span class="h-icon">ℹ️</span><div class="h-text">Ingen SEO-data endnu — klik "Hent data"</div>';
        } else if (top10n >= 15) {
          seoHealth.className = 'rzpa-health health-good';
          seoHealth.innerHTML = `<span class="h-icon">🟢</span><div class="h-text"><strong>Stærk SEO-synlighed!</strong> ${top10n} søgeord på side 1.<div class="h-sub">Fokusér på at fastholde top 3-placeringer og optimér CTR på sider med mange visninger.</div></div>`;
        } else if (top10n >= 5) {
          seoHealth.className = 'rzpa-health health-good';
          seoHealth.innerHTML = `<span class="h-icon">🟢</span><div class="h-text"><strong>God SEO-synlighed!</strong> ${top10n} søgeord på Googles 1. side.<div class="h-sub">Se Action Center for hurtige gevinster.</div></div>`;
        } else if (top10n > 0) {
          seoHealth.className = 'rzpa-health health-warn';
          seoHealth.innerHTML = `<span class="h-icon">🟡</span><div class="h-text"><strong>Begynder at få synlighed.</strong> ${top10n} søgeord på side 1.<div class="h-sub">Se "Muligheder" nedenfor — der er søgeord tæt på side 1.</div></div>`;
        } else {
          seoHealth.className = 'rzpa-health health-bad';
          seoHealth.innerHTML = `<span class="h-icon">🔴</span><div class="h-text"><strong>Lav SEO-synlighed.</strong> Ingen søgeord på side 1.<div class="h-sub">Opret SEO-optimerede undersider og blogindlæg om jeres kerneydelser.</div></div>`;
        }
        seoHealth.style.display = 'flex';
      }

      hBarChart('chart_kw_clicks', allKw.slice(0,8).map(k=>k.keyword), allKw.slice(0,8).map(k=>k.total_clicks));
      renderSeoTable(allKw, d);
      renderPagesTable(allPages);
      renderOpportunities(allKw);
      renderCTRTable(allPages);
      renderActionCenter(allKw, allPages);
    }

    async function loadSEOMonthly() {
      const r = await api('/seo/monthly');
      const months = r.data || [];
      if (!months.length) return;
      const canvas = el('chart_seo_monthly');
      if (!canvas) return;
      if (canvas._chart) { canvas._chart.destroy(); delete canvas._chart; }
      const labels = months.map(m => {
        const [y, mo] = m.month.split('-');
        return ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'][parseInt(mo)-1] + ' ' + y.slice(2);
      });
      canvas._chart = new Chart(canvas, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { label: 'Klik', data: months.map(m => m.total_clicks), backgroundColor: 'rgba(204,255,0,0.7)', borderRadius: 6, order: 1 },
            { label: 'Visninger', data: months.map(m => m.total_impressions), type: 'line', borderColor: '#888', backgroundColor: 'transparent', pointRadius: 3, tension: 0.3, yAxisID: 'y2', order: 0 },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { labels: { color: '#888', font: { size: 11 } } } },
          scales: {
            x: { ticks: { color: '#666' }, grid: { color: 'rgba(255,255,255,.04)' } },
            y: { ticks: { color: '#666' }, grid: { color: 'rgba(255,255,255,.06)' } },
            y2: { position: 'right', ticks: { color: '#666' }, grid: { display: false } },
          },
        },
      });
    }

    function contentTypeBadge(url) {
      const path = url.replace(/^https?:\/\/[^/]+/, '') || '/';
      const normalized = path.endsWith('/') ? path : path + '/';
      const type = contentMap[normalized] || contentMap[path] || null;
      if (type === 'post') return '<span class="rzpa-type-badge type-blog">✍️ Blog</span>';
      if (type === 'page') return '<span class="rzpa-type-badge type-page">📄 Side</span>';
      return '<span class="rzpa-type-badge type-other">🔗 Andet</span>';
    }

    function posBadge(p) {
      const n = parseFloat(p)||0;
      if (!n) return '<span class="pos-badge pos-out">–</span>';
      const cls = n<=3?'pos-top3':n<=10?'pos-top10':n<=20?'pos-top20':'pos-out';
      const lbl = n<=3?'Top 3 🏆':n<=10?'Side 1':n<=20?'Side 2':'Side '+Math.ceil(n/10);
      return `<span class="pos-badge ${cls}">#${Math.round(n)} ${lbl}</span>`;
    }

    function renderSeoTable(data, d) {
      const tbody = el('seo_tbody');
      if (!tbody) return;
      tbody.innerHTML = data.map(k => `
        <tr style="cursor:pointer" onclick="RZPA_App.loadKwTrend('${encodeURIComponent(k.keyword)}',${d})">
          <td style="color:#ddd;font-weight:500">${k.keyword}</td>
          <td>${posBadge(k.avg_position)}</td>
          <td>${fmt(k.total_clicks)}</td>
          <td>${fmt(k.total_impressions)}</td>
          <td>${fmt(k.avg_ctr,2)}%</td>
        </tr>`).join('') || '<tr><td colspan="5" class="rzpa-empty">Ingen data endnu – synkronisér via knappen</td></tr>';
    }

    function renderPagesTable(data) {
      const tbody = el('seo_pages_tbody');
      if (!tbody) return;
      if (!data.length) { tbody.innerHTML = '<tr><td colspan="6" class="rzpa-empty">Ingen sidedata – synkronisér SEO data</td></tr>'; return; }
      tbody.innerHTML = data.slice(0,20).map(p => {
        const url = p.page_url.replace(/^https?:\/\/[^/]+/, '') || '/';
        return `<tr>
          <td style="color:#ddd;font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis" title="${p.page_url}">${url}</td>
          <td>${contentTypeBadge(p.page_url)}</td>
          <td>${posBadge(p.avg_position)}</td>
          <td>${fmt(p.total_clicks)}</td>
          <td>${fmt(p.total_impressions)}</td>
          <td>${fmt(p.avg_ctr,2)}%</td>
        </tr>`;
      }).join('');
    }

    function renderCTRTable(data) {
      const tbody = el('seo_ctr_tbody');
      if (!tbody) return;
      // Sider med >50 visninger men CTR under 5%
      const low = data.filter(p => p.total_impressions > 50 && p.avg_ctr < 5 && p.avg_position <= 20)
                      .sort((a,b) => b.total_impressions - a.total_impressions);
      if (!low.length) { tbody.innerHTML = '<tr><td colspan="6" class="rzpa-empty">Ingen sider med lav CTR — godt klaret! 🎉</td></tr>'; return; }
      tbody.innerHTML = low.slice(0,8).map(p => {
        const url = p.page_url.replace(/^https?:\/\/[^/]+/, '') || '/';
        const tip = p.avg_ctr < 2
          ? 'Titlen er sandsynligvis for generisk — prøv en mere fængende overskrift'
          : 'Meta-beskrivelsen mangler måske et call-to-action';
        return `<tr>
          <td style="color:#ddd;font-weight:500;max-width:180px;overflow:hidden;text-overflow:ellipsis" title="${p.page_url}">${url}</td>
          <td>${contentTypeBadge(p.page_url)}</td>
          <td>${posBadge(p.avg_position)}</td>
          <td>${fmt(p.total_impressions)}</td>
          <td style="color:#f59e0b;font-weight:600">${fmt(p.avg_ctr,2)}%</td>
          <td style="font-size:11px;color:#888">${tip}</td>
        </tr>`;
      }).join('');
    }

    function renderOpportunities(data) {
      const tbody = el('seo_opportunities_tbody');
      if (!tbody) return;
      const opps = data.filter(k => k.avg_position > 10 && k.avg_position <= 20)
                       .sort((a,b) => a.avg_position - b.avg_position);
      if (!opps.length) { tbody.innerHTML = '<tr><td colspan="5" class="rzpa-empty">Ingen søgeord i position 11–20</td></tr>'; return; }
      tbody.innerHTML = opps.slice(0,10).map(k => {
        const tip = k.total_impressions > 100
          ? 'Skriv et dedikeret blogindlæg eller side om dette emne'
          : 'Tilføj søgeordet naturligt på eksisterende sider';
        return `<tr>
          <td style="color:#ddd;font-weight:500">${k.keyword}</td>
          <td>${posBadge(k.avg_position)}</td>
          <td>${fmt(k.total_impressions)}</td>
          <td>${fmt(k.avg_ctr,2)}%</td>
          <td style="font-size:11px;color:#888">${tip}</td>
        </tr>`;
      }).join('');
    }

    function renderActionCenter(kws, pages) {
      const card = el('seo-action-center');
      const list = el('seo-action-list');
      if (!card || !list) return;

      const actions = [];

      // 1. Quick wins: søgeord position 4-10
      const quickWins = kws.filter(k => k.avg_position > 3 && k.avg_position <= 10)
                           .sort((a,b) => b.total_clicks - a.total_clicks).slice(0,3);
      quickWins.forEach(k => {
        const pos = Math.round(k.avg_position);
        const extraClicks = Math.round(k.total_clicks * (pos > 5 ? 1.5 : 0.8));
        const googleUrl = `https://www.google.dk/search?q=${encodeURIComponent(k.keyword)}`;
        actions.push({
          prio: 'high',
          icon: '🚀',
          title: `Ryk "${k.keyword}" fra #${pos} til top 3`,
          desc: `Dette søgeord er tæt på top 3. Med lidt forbedring af siden kan I potentielt få ~${fmt(extraClicks)} flere klik om måneden.`,
          action: `Opdatér siden med søgeordet i overskriften (H1), meta-titlen og de første 100 ord. Tilføj interne links til siden fra andre sider på rezponz.dk.`,
          links: [{ label: '🔍 Tjek din placering på Google', url: googleUrl }],
        });
      });

      // 2. CTR-problemer: mange visninger, få klik
      const ctrIssues = pages.filter(p => p.total_impressions > 100 && p.avg_ctr < 3 && p.avg_position <= 10)
                             .sort((a,b) => b.total_impressions - a.total_impressions).slice(0,2);
      ctrIssues.forEach(p => {
        const url = p.page_url.replace(/^https?:\/\/[^/]+/, '') || '/';
        const slug = url.replace(/^\/|\/$/g,'').replace(/-/g,' ') || 'forsiden';
        actions.push({
          prio: 'medium',
          icon: '✏️',
          title: `Forbedre sidetitlen på ${url}`,
          desc: `Siden vises ${fmt(p.total_impressions)} gange på Google men kun ${fmt(p.avg_ctr,1)}% klikker ind. En bedre titel kan fordoble trafikken uden at ændre indholdet.`,
          action: `Gå til WordPress → Rediger siden → Skift SEO-titlen (Yoast/RankMath) til noget mere fængende med et klart benefit. F.eks. "${slug}" → "Hvad er [emne]? Alt du skal vide [${new Date().getFullYear()}]"`,
          links: [
            { label: '🔗 Åbn siden', url: p.page_url },
            { label: '✏️ Rediger i WordPress', url: (RZPA.admin_url || '/wp-admin/') + 'post.php?action=edit&rzpa_find_url=' + encodeURIComponent(p.page_url) },
          ],
        });
      });

      // 3. Side 1-kandidater
      const candidates = kws.filter(k => k.avg_position > 10 && k.avg_position <= 15 && k.total_impressions > 50)
                            .sort((a,b) => b.total_impressions - a.total_impressions).slice(0,2);
      candidates.forEach(k => {
        const googleUrl = `https://www.google.dk/search?q=${encodeURIComponent(k.keyword)}`;
        actions.push({
          prio: 'medium',
          icon: '💡',
          title: `Skub "${k.keyword}" op på side 1`,
          desc: `Position #${Math.round(k.avg_position)} — kun ét step fra side 1. Søgeordet vises allerede ${fmt(k.total_impressions)} gange.`,
          action: `Skriv et nyt blogindlæg der fokuserer specifikt på "${k.keyword}". Brug søgeordet i URL'en, overskriften og de første afsnit. Tilføj FAQ-sektion med relaterede spørgsmål.`,
          links: [{ label: '🔍 Se placeringen på Google', url: googleUrl }],
        });
      });

      if (!actions.length) {
        card.style.display = 'none';
        return;
      }

      list.innerHTML = actions.map((a, i) => `
        <div class="rzpa-action-item rzpa-action-${a.prio}">
          <div class="action-num">${i+1}</div>
          <div class="action-body">
            <div class="action-title">${a.icon} ${a.title}</div>
            <div class="action-desc">${a.desc}</div>
            <div class="action-how"><strong>Sådan gør du:</strong> ${a.action}</div>
            ${(a.links||[]).length ? `<div class="action-links">${a.links.map(l=>`<a href="${l.url}" target="_blank" class="action-link">${l.label}</a>`).join('')}</div>` : ''}
          </div>
          <div class="action-prio action-prio-${a.prio}">${a.prio === 'high' ? '🔥 Høj' : '⚡ Middel'}</div>
        </div>
      `).join('');

      card.style.display = 'block';
    }
  }

  async function loadKwTrend(kwEncoded, days) {
    const kw = decodeURIComponent(kwEncoded);
    const r = await api(`/seo/keyword-trend?keyword=${kwEncoded}&days=${days}`);
    const data = r.data || [];
    const titleEl = el('trend_title');
    if (titleEl) titleEl.textContent = 'Position: ' + kw;
    lineChart('chart_kw_trend',
      data.map(d => d.date.slice(5)),
      [{ data: data.map(d => parseFloat(d.position).toFixed(1)), borderColor: '#CCFF00',
         backgroundColor: 'rgba(204,255,0,0.08)', fill: true, tension: 0.3, pointRadius: 2 }]
    );
    const chart = el('chart_kw_trend');
    if (chart?._chart) {
      chart._chart.options.scales.y.reverse = true;
      chart._chart.update();
    }
  }

  // ════════════════════════════════════════════════════
  // PAGE: AI SEARCH
  // ════════════════════════════════════════════════════

  async function initAI() {
    let days = 30;
    initDateFilter('rzpa-date-filter', d => { days = d; loadAI(d); });
    loadAI(days);

    el('rzpa-ai-sync')?.addEventListener('click', async () => {
      await api('/ai/sync', { method: 'POST' });
      loadAI(days);
    });

    el('rzpa-log-toggle')?.addEventListener('click', () => {
      const form = el('rzpa-log-form');
      if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    el('rzpa-log-submit')?.addEventListener('click', async () => {
      const data = {
        platform:          el('log_platform')?.value,
        query:             el('log_query')?.value,
        response_text:     el('log_response')?.value,
        rezponz_mentioned: el('log_mentioned')?.checked ? 1 : 0,
        notes:             el('log_notes')?.value,
      };
      if (!data.query) { alert('Forespørgsel er påkrævet'); return; }
      await api('/ai/manual-logs', { method: 'POST', body: JSON.stringify(data) });
      el('rzpa-log-form').style.display = 'none';
      loadAI(days);
    });
  }

  async function loadAI(days) {
    const [sum, logs, ov] = await Promise.all([
      api(`/ai/summary?days=${days}`),
      api(`/ai/manual-logs?days=${days}`),
      api(`/ai/overview?days=${days}`),
    ]);
    const s = sum.data || {};
    const logData = logs.data || [];

    renderKPI('kpi_ai_ov',      fmt(s.ai_overview_count));
    renderKPI('kpi_snippets',   fmt(s.featured_snippet_count));
    renderKPI('kpi_paa',        fmt(s.paa_count));
    renderKPI('kpi_mentioned',  logData.filter(l => l.rezponz_mentioned == 1).length,
              `af ${logData.length} loggede forespørgsler`);

    // AI overview bar chart by date
    const byDate = {};
    (ov.data||[]).forEach(row => {
      if (!byDate[row.date]) byDate[row.date] = { ai: 0, snippet: 0 };
      if (row.has_ai_overview) byDate[row.date].ai++;
      if (row.has_featured_snippet) byDate[row.date].snippet++;
    });
    const dates = Object.keys(byDate).slice(-14);
    barChart('chart_ai_ov', dates.map(d=>d.slice(5)), [
      { label: 'AI Overview', data: dates.map(d=>byDate[d].ai), backgroundColor: '#CCFF00', borderRadius: 4 },
      { label: 'Featured Snippet', data: dates.map(d=>byDate[d].snippet), backgroundColor: '#4488ff', borderRadius: 4 },
    ]);

    // Manual log table
    const tbody = el('ai_log_tbody');
    if (tbody) {
      if (!logData.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="rzpa-empty">Ingen logs endnu. Tilføj din første.</td></tr>';
      } else {
        tbody.innerHTML = logData.map(log => `
          <tr>
            <td>${log.date}</td>
            <td><span class="badge badge-paused">${log.platform}</span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis">${log.query}</td>
            <td>${log.rezponz_mentioned == 1 ? '<span class="badge badge-active">Ja</span>' : '<span class="badge badge-paused">Nej</span>'}</td>
            <td style="color:#555;max-width:150px;overflow:hidden;text-overflow:ellipsis">${log.notes||'–'}</td>
            <td><button class="btn-danger" onclick="RZPA_App.deleteLog(${log.id})">Slet</button></td>
          </tr>`).join('');
      }
    }
  }

  async function deleteLog(id) {
    if (!confirm('Slet dette log?')) return;
    await api(`/ai/manual-logs/${id}`, { method: 'DELETE' });
    const days = parseInt(document.querySelector('[data-days].active')?.dataset.days || 30);
    loadAI(days);
  }

  // ════════════════════════════════════════════════════
  // PAGE: META ADS
  // ════════════════════════════════════════════════════

  // ── Meta helpers ──────────────────────────────────
  function perfClass(ctr) {
    return ctr >= 1.5 ? 'perf-good' : ctr >= 0.5 ? 'perf-mid' : 'perf-bad';
  }
  function perfLabel(ctr) {
    return ctr >= 1.5 ? '🟢 Godt' : ctr >= 0.5 ? '🟡 Middel' : '🔴 Svagt';
  }

  let metaAllData = [], metaSortKey = 'spend', metaSortDir = 'desc', metaFilter = 'all';

  // ── Ad Creative Modal ──────────────────────────────────────────────────────

  const _ctaLabels = {
    LEARN_MORE:'Læs mere', SHOP_NOW:'Køb nu', SIGN_UP:'Tilmeld dig',
    GET_QUOTE:'Få et tilbud', CONTACT_US:'Kontakt os', DOWNLOAD:'Download',
    WATCH_MORE:'Se mere', BOOK_TRAVEL:'Book rejse', APPLY_NOW:'Ansøg nu',
    GET_OFFER:'Få tilbud',
  };
  function fmtCTA(v) { return _ctaLabels[v] || v || ''; }

  function openAdModal(campaignId, campaignName) {
    const modal = el('rzpa-ad-modal');
    if (!modal) return;
    const titleEl = el('rzpa-modal-title');
    const cards   = el('rzpa-ad-cards');
    if (titleEl) titleEl.textContent = campaignName;
    if (cards)   cards.innerHTML = '<div class="rzpa-loading-modal">⏳ Henter annoncer fra Meta…</div>';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    api(`/meta/campaign-ads?campaign_id=${encodeURIComponent(campaignId)}`).then(r => {
      const ads = r.data || [];
      if (!cards) return;
      if (!ads.length) {
        cards.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:48px 24px">Ingen annoncer fundet for denne kampagne.<br><small>Det kan skyldes at kampagnen er ny, eller at din token mangler ads-tilladelse.</small></p>';
        return;
      }
      cards.innerHTML = ads.map(ad => {
        const thumb = ad.thumbnail_url || ad.image_url;
        const mediaHtml = thumb
          ? `<img src="${thumb}" class="rzpa-ad-thumb" alt="Annonce preview">`
          : `<div class="rzpa-ad-no-thumb">📷 Ingen preview tilgængeligt</div>`;
        const playBtn = ad.has_video
          ? `<button class="rzpa-play-btn" data-adid="${ad.ad_id}" title="Afspil annonce">▶</button>`
          : '';
        const cta = fmtCTA(ad.cta);
        return `<div class="rzpa-ad-card">
          <div class="rzpa-ad-preview-wrap" id="adprev-${ad.ad_id}">
            ${mediaHtml}${playBtn}
          </div>
          <div class="rzpa-ad-info">
            <div class="rzpa-ad-name">${ad.ad_name||'Unavngivet'}</div>
            ${ad.title ? `<div class="rzpa-ad-title">${ad.title}</div>` : ''}
            ${ad.body  ? `<div class="rzpa-ad-body">${ad.body.substring(0,120)}${ad.body.length>120?'…':''}</div>` : ''}
            ${cta      ? `<div class="rzpa-ad-cta">${cta}</div>` : ''}
          </div>
        </div>`;
      }).join('');

      // Play-knapper: hent og vis Meta iframe-preview
      cards.querySelectorAll('.rzpa-play-btn').forEach(btn => {
        btn.addEventListener('click', () => loadAdPreview(btn.dataset.adid, document.getElementById('adprev-' + btn.dataset.adid)));
      });
    }).catch(() => {
      if (cards) cards.innerHTML = '<p style="color:#ff6633;padding:40px;text-align:center">Fejl: Kunne ikke hente annoncer. Tjek din Meta Access Token i Indstillinger.</p>';
    });
  }

  function closeAdModal() {
    const modal = el('rzpa-ad-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  function loadAdPreview(adId, container) {
    if (!container) return;
    container.innerHTML = '<div class="rzpa-loading-modal" style="min-height:80px">Indlæser annonce…</div>';
    api(`/meta/ad-preview?ad_id=${encodeURIComponent(adId)}`).then(r => {
      const html = r.data?.iframe_html;
      if (!html) {
        container.innerHTML = '<p style="color:var(--text-dim);text-align:center;padding:20px;font-size:12px">Preview ikke tilgængeligt for denne annonce.</p>';
        return;
      }
      container.innerHTML = `<div class="rzpa-ad-iframe-wrap">${html}</div>`;
    });
  }

  // Modal luk-handlers (oprettes én gang ved modul-init)
  document.addEventListener('click', e => {
    if (e.target.id === 'rzpa-modal-close' || e.target.id === 'rzpa-ad-modal') closeAdModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAdModal();
  });

  // ─────────────────────────────────────────────────────────────────────────

  function renderMetaTable(data) {
    const tbody = el('meta_tbody');
    const noRes = el('meta-no-results');
    if (!tbody) return;
    let filtered = data.filter(c => {
      if (metaFilter === 'all') return true;
      if (metaFilter === 'ACTIVE') return c.status === 'ACTIVE';
      if (metaFilter === 'PAUSED') return c.status === 'PAUSED';
      const ctr = parseFloat(c.ctr) || 0;
      if (metaFilter === 'good') return ctr >= 1.5;
      if (metaFilter === 'mid')  return ctr >= 0.5 && ctr < 1.5;
      if (metaFilter === 'bad')  return ctr < 0.5;
      return true;
    });
    filtered = sortTable(filtered, metaSortKey, metaSortDir);
    if (!filtered.length) {
      tbody.innerHTML = '';
      if (noRes) noRes.style.display = 'block';
      return;
    }
    if (noRes) noRes.style.display = 'none';
    tbody.innerHTML = filtered.map(c => {
      const ctr  = parseFloat(c.ctr) || 0;
      const name = c.campaign_name.replace(/Rezponz\s*[–-]\s*/i,'');
      const mgr  = c.campaign_id
        ? `<button class="rzpa-see-ads-btn" data-cid="${c.campaign_id}" data-cname="${name}">🎨 Se annoncer</button>`
        : '';
      return `<tr>
        <td style="color:#ddd;font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis" title="${c.campaign_name}">${name}</td>
        <td>${badgeHtml(c.status)}</td>
        <td>${fmt(c.spend,0)} kr</td>
        <td>${fmt(c.impressions)}</td>
        <td>${fmt(c.reach)}</td>
        <td>${fmt(c.clicks)}</td>
        <td>${fmt(c.cpm,2)} kr</td>
        <td>${fmt(c.cpc,2)} kr</td>
        <td style="font-weight:600">${fmt(ctr,2)}%</td>
        <td><span class="perf-badge ${perfClass(ctr)}">${perfLabel(ctr)}</span></td>
        <td>${mgr}</td>
      </tr>`;
    }).join('');
  }

  async function syncMeta(days) {
    const btn = el('rzpa-sync-meta');
    if (btn) { btn.disabled = true; btn.textContent = 'Henter…'; }
    try {
      const r = await api('/meta/sync', { method: 'POST', body: JSON.stringify({days}) });
      // Ryd browser-cache så næste load henter friske data
      clearCache('/meta/');
      clearCache('/dashboard/overview');
      if (btn) {
        btn.textContent = `✓ ${r.data?.count||0} kampagner hentet`;
        setTimeout(() => { btn.disabled = false; btn.textContent = '⟳ Hent data'; }, 3000);
      }
      return true;
    } catch(e) {
      if (btn) { btn.disabled = false; btn.textContent = '⟳ Hent data'; }
      alert('Fejl: Tjek at Meta Access Token er gyldigt i Indstillinger.');
      return false;
    }
  }

  async function loadMonthlyChart() {
    try {
      const r = await api('/meta/monthly?months=6');
      const data = r.data || [];
      if (!data.length) return;
      const card = el('meta-monthly-card');
      if (card) card.style.display = 'block';
      const labels = data.map(d => {
        const [y, m] = d.month.split('-');
        const names = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
        return names[parseInt(m,10)-1] + ' ' + y.slice(2);
      });
      barChart('chart_monthly', labels,
        [{ data: data.map(d => Math.round(d.spend||0)),
           backgroundColor: 'rgba(24,119,242,0.8)',
           borderRadius: 6 }],
        { yTick: v => (v>=1000?(v/1000).toFixed(0)+'k':v)+' kr' }
      );
    } catch(e) {}
  }

  async function initMeta() {
    let days = 30;

    // Smart auto-sync: hent data hvis perioden ikke har data endnu
    async function maybeSync(d) {
      const check = await api(`/meta/has-data?days=${d}`);
      if (!check.data?.has_data) {
        return await syncMeta(d);
      }
      return true;
    }

    // Date filter: load first, sync only if needed
    initDateFilter('rzpa-date-filter', async d => {
      days = d;
      const tbody = el('meta_tbody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="rzpa-loading">Henter data…</td></tr>';
      await maybeSync(d);
      loadMeta(d);
    });

    // Initial load
    await maybeSync(days);
    loadMeta(days);
    loadMonthlyChart();

    el('rzpa-sync-meta')?.addEventListener('click', async () => {
      if (await syncMeta(days)) {
        loadMeta(days);
        loadMonthlyChart();
      }
    });

    // Filter-knapper
    el('meta-filter-bar')?.addEventListener('click', e => {
      const btn = e.target.closest('[data-filter]');
      if (!btn) return;
      metaFilter = btn.dataset.filter;
      el('meta-filter-bar').querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderMetaTable(metaAllData);
    });

    // Sortér kolonner
    el('meta-table')?.querySelector('thead')?.addEventListener('click', e => {
      const th = e.target.closest('[data-sort]');
      if (!th) return;
      const key = th.dataset.sort;
      metaSortDir = (metaSortKey === key && metaSortDir === 'desc') ? 'asc' : 'desc';
      metaSortKey = key;
      renderMetaTable(metaAllData);
    });

    // Se annoncer-knapper (event delegation på tbody)
    el('meta_tbody')?.addEventListener('click', e => {
      const btn = e.target.closest('.rzpa-see-ads-btn');
      if (!btn) return;
      openAdModal(btn.dataset.cid, btn.dataset.cname);
    });
  }

  async function loadMeta(days) {
    const [sum, camps] = await Promise.all([
      api(`/meta/summary?days=${days}`),
      api(`/meta/campaigns?days=${days}`),
    ]);
    const s = sum.data || {};
    metaAllData = camps.data || [];

    if (s.configured === false) {
      const app = el('rzpa-app');
      if (app) app.innerHTML = `<div class="rzpa-not-configured">
        <h2>⚙️ Meta Ads er ikke opsat</h2>
        <p>Gå til <a href="?page=rzpa-settings">Indstillinger</a> og tilføj Access Token og Ad Account ID.</p>
      </div>`;
      return;
    }

    const spend  = parseFloat(s.total_spend) || 0;
    const clicks = parseInt(s.total_clicks) || 0;
    const impr   = parseInt(s.total_impressions) || 0;
    const ctr    = parseFloat(s.avg_ctr) || (impr > 0 ? Math.round(clicks/impr*10000)/100 : 0);
    const cpc    = parseFloat(s.avg_cpc) || (clicks > 0 ? Math.round(spend/clicks*100)/100 : 0);
    const perDay = days > 0 ? Math.round(spend / days) : 0;

    renderKPI('kpi_spend',  spend > 0 ? fmt(spend,0) + ' kr' : '–',
      perDay > 0 ? '≈ ' + fmt(perDay,0) + ' kr/dag' : 'Klik "Hent data" for at hente kampagner');
    renderKPI('kpi_impr',   fmt(impr));
    renderKPI('kpi_clicks', fmt(clicks), cpc > 0 ? fmt(cpc,2) + ' kr per klik' : '');
    renderKPI('kpi_ctr',    ctr > 0 ? fmt(ctr,2) + '%' : '–',
      ctr >= 1.5 ? '✅ Over gennemsnit' : ctr >= 0.5 ? '⚠️ Under målet (mål: 1,5%+)' : ctr > 0 ? '❌ Lav – overvej nyt kreativt indhold' : '');

    // ── Fortæller-kort ──────────────────────────────
    const storyEl = el('meta-story');
    if (storyEl && spend > 0) {
      const ctrLabel = ctr>=1.5?'rigtig godt — annoncerne fanger folks opmærksomhed 🚀'
                      :ctr>=0.5?'okay, men der er plads til forbedring 💡'
                      :ctr>0?'lav — prøv nyt billede eller tekst på annoncerne ⚠️':'–';
      storyEl.innerHTML = `I perioden brugte I <strong>${fmt(spend,0)} kr</strong> på Facebook og Instagram. `
        + `Annoncerne dukkede op <strong>${fmt(impr)} gange</strong>, og <strong>${fmt(clicks)} personer</strong> klikkede videre til jeres hjemmeside. `
        + (cpc>0?`Det svarer til <strong>${fmt(cpc,2)} kr per besøg</strong>. `:'')
        + (ctr>0?`Jeres klikprocent er <strong>${fmt(ctr,2)}%</strong> — det er <strong>${ctrLabel}</strong>.`:'');
      storyEl.classList.remove('hidden');
    } else if (storyEl) {
      storyEl.innerHTML = 'Klik på <strong>Hent data</strong> for at hente dine kampagner fra Meta.';
      storyEl.classList.remove('hidden');
    }

    // ── Health bar ──────────────────────────────────
    const hBar = el('rzpa-health-bar');
    if (hBar) {
      if (!spend) {
        hBar.className = 'rzpa-health health-empty';
        hBar.innerHTML = '<span class="h-icon">ℹ️</span><div class="h-text">Klik på "Hent data" for at hente dine kampagner</div>';
      } else if (ctr>=1.5) {
        hBar.className = 'rzpa-health health-good';
        hBar.innerHTML = `<span class="h-icon">🟢</span><div class="h-text"><strong>Alt ser godt ud!</strong> Klikprocenten er ${fmt(ctr,2)}% — over målet på 1,5%.<div class="h-sub">Fortsæt som nu — annoncerne virker.</div></div>`;
      } else if (ctr>=0.5) {
        hBar.className = 'rzpa-health health-warn';
        hBar.innerHTML = `<span class="h-icon">🟡</span><div class="h-text"><strong>Annoncerne virker, men kan forbedres.</strong> Klikprocenten er ${fmt(ctr,2)}% (mål: 1,5%+).<div class="h-sub">Test nyt billede eller overskrift for at hæve klikprocenten.</div></div>`;
      } else if (ctr>0) {
        hBar.className = 'rzpa-health health-bad';
        hBar.innerHTML = `<span class="h-icon">🔴</span><div class="h-text"><strong>Lav klikprocent — annoncerne bør opdateres.</strong> ${fmt(ctr,2)}% klikker videre (mål: over 1,5%).<div class="h-sub">Prøv nyt billede, ny overskrift eller ny målgruppe.</div></div>`;
      }
      hBar.style.display = 'flex';
    }

    // ── Spend pill ──────────────────────────────────
    const spPill = el('kpi_spend_pill');
    if (spPill && perDay > 0) {
      spPill.innerHTML = `<span class="rzpa-pill neutral">≈ ${fmt(perDay,0)} kr/dag</span>`;
    }
    // CTR sub text
    const ctrSub = el('kpi_ctr_sub');
    if (ctrSub) {
      if (ctr >= 1.5) ctrSub.innerHTML = '<span class="rzpa-pill good">✓ Over gennemsnit</span>';
      else if (ctr >= 0.5) ctrSub.innerHTML = '<span class="rzpa-pill warn">⚠ Kan forbedres</span>';
      else if (ctr > 0) ctrSub.innerHTML = '<span class="rzpa-pill bad">✗ For lavt — handl nu</span>';
    }

    const expEl = el('meta-explain'), expTxt = el('meta-explain-text');
    if (expEl && expTxt && spend > 0) {
      expEl.style.display = 'flex';
      expTxt.textContent = `Du brugte ${fmt(spend,0)} kr og nåede ${fmt(impr)} folk – ${fmt(clicks)} klikkede videre. `
        + `Det svarer til ${fmt(cpc,2)} kr per klik og ${fmt(perDay,0)} kr per dag. `
        + (ctr >= 1.5 ? 'Annoncerne fanger godt opmærksomhed! 🚀'
          : ctr >= 0.5 ? 'Klikraten er okay – test nyt indhold for at forbedre den.'
          : ctr > 0 ? 'Klikraten er lav – prøv at ændre billede, overskrift eller målgruppe.'
          : '');
    } else if (expEl) expEl.style.display = 'none';

    // Performance oversigt
    const perfSum = el('meta-perf-summary');
    if (perfSum && metaAllData.length) {
      const good = metaAllData.filter(c=>(parseFloat(c.ctr)||0)>=1.5).length;
      const mid  = metaAllData.filter(c=>{const v=parseFloat(c.ctr)||0;return v>=0.5&&v<1.5;}).length;
      const bad  = metaAllData.filter(c=>(parseFloat(c.ctr)||0)<0.5).length;
      setText('perf_good_count',good); setText('perf_mid_count',mid); setText('perf_bad_count',bad);
      perfSum.style.display = 'flex';
    }

    const top6   = [...metaAllData].sort((a,b)=>b.spend-a.spend).slice(0,6);
    const labels = top6.map(c => c.campaign_name.replace(/Rezponz\s*[–-]\s*/i,'').slice(0,20));
    barChart('chart_spend', labels,
      [{data:top6.map(c=>Math.round(c.spend||0)),backgroundColor:'#1877F2',borderRadius:5}],
      {yTick: v => Math.round(v/1000)+'k kr'}
    );
    barChart('chart_ctr', labels,
      [{data:top6.map(c=>parseFloat(c.ctr||0)),
        backgroundColor:top6.map(c=>(parseFloat(c.ctr)||0)>=1.5?'#CCFF00':(parseFloat(c.ctr)||0)>=0.5?'#f5a623':'#cc4400'),
        borderRadius:5}],
      {yTick: v => v+'%'}
    );
    renderMetaTable(metaAllData);
  }

  // ════════════════════════════════════════════════════
  // PAGE: SNAPCHAT
  // ════════════════════════════════════════════════════

  async function initSnap() {
    let days = 30;
    initDateFilter('rzpa-date-filter', d => { days = d; loadSnap(d); });
    loadSnap(days);
    el('rzpa-sync-snap')?.addEventListener('click', async () => {
      await api('/snap/sync', { method: 'POST', body: JSON.stringify({days}) });
      loadSnap(days);
    });
  }

  async function loadSnap(days) {
    const [sum, camps] = await Promise.all([
      api(`/snap/summary?days=${days}`),
      api(`/snap/campaigns?days=${days}`),
    ]);
    const s = sum.data || {}, data = camps.data || [];

    if (s.configured === false) {
      const app = el('rzpa-app');
      if (app) app.innerHTML = `<div class="rzpa-not-configured">
        <h2>⚙️ Snapchat Ads er ikke opsat</h2>
        <p>Gå til <a href="?page=rzpa-settings">Indstillinger</a> og tilføj din Snapchat access token og ad account ID.</p>
      </div>`;
      return;
    }

    renderKPI('kpi_spend',      fmt(s.total_spend,0) + ' kr');
    renderKPI('kpi_swipes',     fmt(s.total_swipe_ups));
    renderKPI('kpi_impr',       fmt(s.total_impressions));
    renderKPI('kpi_engagement', fmt(s.avg_engagement_rate,2) + '%');

    const labels = data.slice(0,6).map(c => c.campaign_name.replace('Rezponz – ','').slice(0,20));
    barChart('chart_spend', labels,
      [{ data: data.slice(0,6).map(c=>Math.round(c.spend)), backgroundColor: '#FFFC00', borderRadius: 5 }]
    );
    barChart('chart_engagement', labels,
      [{ data: data.slice(0,6).map(c=>c.engagement_rate), backgroundColor: '#CCFF00', borderRadius: 5 }]
    );

    const tbody = el('snap_tbody');
    if (tbody) tbody.innerHTML = data.map(c => `
      <tr>
        <td style="color:#ddd;font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis">${c.campaign_name}</td>
        <td>${badgeHtml(c.status)}</td>
        <td>${fmt(c.spend,0)} kr</td>
        <td>${fmt(c.impressions)}</td>
        <td style="color:var(--neon);font-weight:700">${fmt(c.swipe_ups)}</td>
        <td>${fmt(c.conversions)}</td>
        <td>${fmt(c.cpm,2)} kr</td>
        <td>${fmt(c.engagement_rate,2)}%</td>
      </tr>`).join('');
  }

  // ════════════════════════════════════════════════════
  // PAGE: TIKTOK
  // ════════════════════════════════════════════════════

  async function initTikTok() {
    let days = 30;
    initDateFilter('rzpa-date-filter', d => { days = d; loadTikTok(d); });
    loadTikTok(days);
    el('rzpa-sync-tiktok')?.addEventListener('click', async () => {
      await api('/tiktok/sync', { method: 'POST', body: JSON.stringify({days}) });
      loadTikTok(days);
    });
  }

  async function loadTikTok(days) {
    const [sum, camps] = await Promise.all([
      api(`/tiktok/summary?days=${days}`),
      api(`/tiktok/campaigns?days=${days}`),
    ]);
    const s = sum.data || {}, data = camps.data || [];

    if (s.configured === false) {
      const app = el('rzpa-app');
      if (app) app.innerHTML = `<div class="rzpa-not-configured">
        <h2>⚙️ TikTok Ads er ikke opsat</h2>
        <p>Gå til <a href="?page=rzpa-settings">Indstillinger</a> og tilføj din TikTok access token og advertiser ID.</p>
      </div>`;
      return;
    }

    renderKPI('kpi_spend',  fmt(s.total_spend,0) + ' kr');
    renderKPI('kpi_views',  fmt(s.total_video_views));
    renderKPI('kpi_roas',   fmt(s.avg_roas,2) + 'x');
    renderKPI('kpi_clicks', fmt(s.total_clicks));

    const labels = data.slice(0,6).map(c => c.campaign_name.replace('Rezponz – ','').slice(0,22));
    barChart('chart_views', labels,
      [{ data: data.slice(0,6).map(c=>c.video_views), backgroundColor: '#ff0050', borderRadius: 5 }],
      { yTick: v => (v/1000).toFixed(0)+'k' }
    );
    barChart('chart_roas', labels,
      [{ data: data.slice(0,6).map(c=>c.roas),
         backgroundColor: data.slice(0,6).map(c => c.roas>=2.5?'#CCFF00':c.roas>=1.5?'#88cc00':'#cc4400'),
         borderRadius: 5 }]
    );

    const tbody = el('tiktok_tbody');
    if (tbody) tbody.innerHTML = data.map(c => `
      <tr>
        <td style="color:#ddd;font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis">${c.campaign_name}</td>
        <td>${badgeHtml(c.status)}</td>
        <td>${fmt(c.spend,0)} kr</td>
        <td>${fmt(c.video_views)}</td>
        <td>${fmt(c.clicks)}</td>
        <td>${fmt(c.conversions)}</td>
        <td class="${roasClass(c.roas)}">${fmt(c.roas,2)}x</td>
        <td>${fmt(c.cost_per_view,4)} kr</td>
      </tr>`).join('');
  }

  // ════════════════════════════════════════════════════
  // PAGE: RAPPORT
  // ════════════════════════════════════════════════════

  async function initRapport() {
    el('rzpa-gen-rapport')?.addEventListener('click', async () => {
      const btn    = el('rzpa-gen-rapport');
      const notice = el('rzpa-rapport-notice');
      const days   = parseInt(document.querySelector('[data-days].active')?.dataset.days || 30);

      btn.disabled = true;
      btn.textContent = 'Genererer…';

      try {
        const r = await api('/pdf/generate', {
          method: 'POST',
          body: JSON.stringify({ days }),
        });

        if (r.success && r.html) {
          // Brug Blob URL for at undgå popup-blocker
          const blob = new Blob([r.html], { type: 'text/html;charset=utf-8' });
          const url  = URL.createObjectURL(blob);
          const win  = window.open(url, '_blank');
          if (win) {
            win.addEventListener('load', () => {
              setTimeout(() => { win.print(); URL.revokeObjectURL(url); }, 800);
            });
            notice.className = 'rzpa-notice success';
            notice.textContent = 'Rapport åbnet i nyt vindue – brug Ctrl+P / Cmd+P til at gemme som PDF.';
          } else {
            // Popup blev blokeret – tilbyd download i stedet
            const a = document.createElement('a');
            a.href = url; a.download = 'rezponz-rapport.html'; a.click();
            notice.className = 'rzpa-notice success';
            notice.textContent = 'Rapport downloadet som HTML – åbn filen og tryk Ctrl+P for at gemme som PDF.';
          }
          notice.style.display = 'block';
        }
      } catch(e) {
        notice.className = 'rzpa-notice error';
        notice.textContent = 'Fejl: ' + e.message;
        notice.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Generer & Åbn Rapport';
      }
    });

    initDateFilter('rzpa-date-filter', () => {});
  }

  // ── Section init map (IIFE-scoped so PJAX can reach it) ─────────────────

  const initMap = {
    dashboard: initDashboard,
    seo:       initSEO,
    ai:        initAI,
    meta:      initMeta,
    snap:      initSnap,
    tiktok:    initTikTok,
    rapport:   initRapport,
    // settings: pure PHP form — ingen JS init nødvendig
  };

  // ── PJAX: instant navigation – ingen full page reload ───────────────────
  //
  // Når brugeren klikker et RZPA-menupunkt:
  //   1. Fetch siden som tekst  (WP genrenderer kun <body>-indhold)
  //   2. Udskift #rzpa-app med det nye indhold
  //   3. pushState → URL opdateres uden reload
  //   4. Kør init-funktionen for den nye sektion
  //
  // Fallback: ved netværksfejl falder vi tilbage til normal navigation.

  let _pjaxBusy = false;

  async function pjaxGo(targetUrl) {
    if (_pjaxBusy) return;
    _pjaxBusy = true;

    const app = document.getElementById('rzpa-app');
    if (!app) { window.location.href = targetUrl; return; }

    // Optimistisk UI: dim nuværende indhold
    app.classList.add('rzpa-pjax-loading');

    try {
      const res  = await fetch(targetUrl, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const html = await res.text();

      const doc    = new DOMParser().parseFromString(html, 'text/html');
      const newApp = doc.getElementById('rzpa-app');
      if (!newApp) { window.location.href = targetUrl; return; }

      // Opfrisk PHP preload-data fra den nye side (sendes med i <script>)
      doc.querySelectorAll('script').forEach(s => {
        const m = s.textContent.match(/"preload"\s*:\s*(\{[\s\S]*?\})\s*(?:[,}])/);
        if (m) {
          try {
            const fresh = JSON.parse(m[1]);
            Object.keys(fresh).forEach(k => {
              _preload[k] = fresh[k];
              // Nulstil "brugt" flag så den friske data rent faktisk bruges
              Object.keys(_preloadUsed).forEach(u => {
                if (_matchPreload(u) !== undefined) delete _preloadUsed[u];
              });
            });
          } catch(e) {}
        }
      });

      // Erstat app-indhold
      const newPage  = newApp.dataset.rzpaPage || '';
      app.dataset.rzpaPage = newPage;
      app.innerHTML  = newApp.innerHTML;

      // Modaler lever udenfor #rzpa-app — synkroniser dem
      ['rzpa-ad-modal'].forEach(id => {
        const oldEl = document.getElementById(id);
        const newEl = doc.getElementById(id);
        if (newEl && oldEl) oldEl.replaceWith(newEl.cloneNode(true));
        else if (newEl)     document.body.appendChild(newEl.cloneNode(true));
        else if (oldEl)     oldEl.remove();
      });

      // Opdater URL-linje
      history.pushState({ rzpaPjax: newPage }, '', targetUrl);

      // Opdater WP sidebars markering af aktivt menupunkt
      pjaxUpdateMenu(targetUrl);

      // Start sektionens JS
      if (initMap[newPage]) initMap[newPage]();

    } catch(e) {
      window.location.href = targetUrl; // graceful fallback
    } finally {
      document.getElementById('rzpa-app')?.classList.remove('rzpa-pjax-loading');
      _pjaxBusy = false;
    }
  }

  function pjaxUpdateMenu(url) {
    const m    = url.match(/[?&]page=(rzpa-[^&]*)/);
    const slug = m?.[1] ?? '';
    if (!slug) return;

    document.querySelectorAll('#adminmenu a').forEach(a => {
      const aHref = a.getAttribute('href') || '';
      if (!aHref.includes('page=rzpa')) return;
      const li = a.closest('li');
      if (!li) return;

      const isTarget = aHref.includes(slug);
      li.classList.toggle('current', isTarget);
      a.classList.toggle('current', isTarget);

      // Toplevel menu-item (Rezponz Analytics)
      if (aHref.includes('rzpa-dashboard')) {
        const topLi = li.closest('.wp-has-submenu') ?? li.parentElement?.closest('li');
        if (topLi) topLi.classList.add('wp-has-current-submenu', 'wp-menu-open');
      }
    });
  }

  // ── Auto-init ──────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('rzpa-app')?.dataset?.rzpaPage;
    if (initMap[page]) initMap[page]();

    // Sæt PJAX-navigation op på RZPA-menupunkter
    document.querySelectorAll('#adminmenu a').forEach(a => {
      if (!(a.getAttribute('href') || '').includes('page=rzpa')) return;
      a.addEventListener('click', e => {
        e.preventDefault();
        pjaxGo(a.href); // .href er altid absolut URL
      });
    });

    // Browser tilbage/frem-knapper
    window.addEventListener('popstate', e => {
      if (e.state?.rzpaPjax !== undefined) pjaxGo(location.href);
    });
  });

  // Public API (used inline)
  return { loadKwTrend, deleteLog };

})();
