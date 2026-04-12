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
    if (path.includes('/meta/summary') && !path.includes('days=7') && !path.includes('days=90'))   return _preload.meta_summary;
    if (path.includes('/meta/campaigns') && !path.includes('days=7') && !path.includes('days=90')) return _preload.meta_campaigns;
    if (path.includes('/meta/has-data'))             return { has_data: !!_preload.meta_has_data, days: 30 };
    if (path.includes('/seo/summary'))               return _preload.seo_summary;
    if (path.includes('/seo/keywords'))              return _preload.seo_keywords;
    if (path.includes('/seo/pages'))                 return _preload.seo_pages;
    if (path.includes('/blog/insights') && !path.includes('days=7') && !path.includes('days=90')) return _preload.blog_insights;
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
    const { headers: extraHdr, timeout: reqTimeout, ...restOpts } = opts;
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), (reqTimeout || 45) * 1000);
    let res, data;
    try {
      res  = await fetch(API + path, { headers: { ...HDR, ...(extraHdr||{}) }, signal: ctrl.signal, ...restOpts });
      data = await res.json();
    } finally {
      clearTimeout(timer);
    }

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
    initCrossChannelAds();
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
    initKeywordSuggestions();

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
      const btn = el('rzpa-ai-sync');
      if (btn) { btn.disabled = true; btn.textContent = 'Synkroniserer…'; }
      try {
        const r = await api('/ai/sync', { method: 'POST', timeout: 120 });
        const d = r?.data ?? r;
        if (d?.errors?.length) {
          // Filtrer "no results" fejl fra — dem håndteres stille i PHP
          const realErrors = d.errors.filter(e => !/no results|hasn't returned/i.test(e));
          if (realErrors.length) {
            alert('SerpAPI API-fejl: ' + realErrors[0] + '\n\nTjek at din API-nøgle er korrekt under Indstillinger → SerpAPI.');
          } else if (btn) {
            btn.textContent = `✓ ${d?.count ?? 0} søgeord synkroniseret`;
            setTimeout(() => { btn.disabled = false; btn.textContent = 'Sync SerpAPI'; }, 3000);
          }
        } else if (btn) {
          btn.textContent = `✓ ${d?.count ?? 0} søgeord synkroniseret`;
          setTimeout(() => { btn.disabled = false; btn.textContent = 'Sync SerpAPI'; }, 3000);
        }
      } catch(e) {
        alert('Fejl ved sync: ' + e.message);
      } finally {
        if (btn) { btn.disabled = false; }
      }
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

  // ── AI helpers ────────────────────────────────────────────────────────────

  function generateActionPlan(keywords, logData, hasKey, tracked) {
    const actions  = [];
    const total    = keywords.length;
    const noAI     = keywords.filter(k => k.has_ai_overview != 1);
    const noSnip   = keywords.filter(k => k.has_featured_snippet != 1);
    const noPAA    = keywords.filter(k => k.has_paa != 1);
    const mentioned= logData.filter(l => l.rezponz_mentioned == 1).length;

    if (!hasKey) {
      actions.push({
        color: '#f59e0b',
        title: 'Tilføj SerpAPI-nøgle for at aktivere AI-tracking',
        desc:  'Uden SerpAPI-nøgle tracker vi ikke din faktiske synlighed. Gå til Indstillinger → SerpAPI og indsæt din nøgle.',
        impact: 'Kritisk',
        type: 'settings_link',
        fixLabel: '⚙️ Indstillinger',
      });
    }
    if (hasKey && !total && tracked.length) {
      actions.push({
        color: '#f59e0b',
        title: 'Klik "Sync SerpAPI" for at hente din synlighed',
        desc:  `Du har ${tracked.length} søgeord konfigureret men ingen synlighedsdata endnu. Tryk knappen øverst til højre.`,
        impact: 'Kritisk',
        type: 'sync_btn',
        fixLabel: '⟳ Sync nu',
      });
    }
    if (noAI.length > 0) {
      const kws = noAI.slice(0, 3).map(k => `"${k.keyword}"`).join(', ');
      actions.push({
        color: '#ef4444',
        title: `Skriv FAQ-sider til ${noAI.length} søgeord der mangler AI Overview`,
        desc:  `${kws}${noAI.length > 3 ? ` og ${noAI.length - 3} andre` : ''} udløser ikke AI Overview. Lav en dedikeret FAQ-side pr. søgeord med direkte, autoritative svar på 300–500 ord. Google AI henter fra præcise, strukturerede svar.`,
        impact: 'Høj effekt',
        type: 'faq_pages',
        fixLabel: 'Opret FAQ-udkast',
        fixKw: noAI.map(k => k.keyword),
      });
    }
    if (noSnip.length > 0 && total > 0) {
      actions.push({
        color: '#f97316',
        title: `Strukturér ${noSnip.length} sider til Featured Snippet`,
        desc:  'Google foretrækker svar i korte afsnit på 40–60 ord eller som lister/tabeller. Brug H2-overskrifter med spørgsmålsformat ("Hvad er…?") og svar direkte nedenunder uden indledning.',
        impact: 'Høj effekt',
        type: 'featured_snippet',
        fixLabel: 'Opret optimeringsudkast',
        fixKw: noSnip.map(k => k.keyword),
      });
    }
    if (noPAA.length > 0 && total > 0) {
      actions.push({
        color: '#a78bfa',
        title: `Tilføj Q&A-sektion på ${noPAA.length} sider for "Folk spørger også"`,
        desc:  '"Folk spørger også"-boksen drives af relaterede spørgsmål. Tilføj 5–8 Q&A-par på dine vigtigste sider og implementér FAQPage Schema Markup (JSON-LD) for at øge sandsynligheden markant.',
        impact: 'Middel effekt',
        type: 'paa_sections',
        fixLabel: 'Opret Q&A-udkast',
        fixKw: noPAA.map(k => k.keyword),
      });
    }
    if (logData.length === 0) {
      actions.push({
        color: '#60a5fa',
        title: 'Log dine første ChatGPT/Perplexity-testsvar',
        desc:  'Test manuelt: spørg ChatGPT "Hvem tilbyder kundeservice outsourcing i Danmark?" og log svaret nedenfor. Det giver dig konkret indsigt i om AI-assistenter kender Rezponz.',
        impact: 'Vigtig data',
        type: 'scroll_logs',
        fixLabel: '📋 Åbn logform',
      });
    }
    if (logData.length > 0 && mentioned === 0) {
      actions.push({
        color: '#fb923c',
        title: 'Rezponz nævnes ikke i AI-svar – byg digital autoritet',
        desc:  'Få Rezponz omtalt på autoritetssider: brancheforeninger, sammenligningstjenester (fx Trustpilot, Capterra), nyhedsmedier og gæsteblogindlæg. Jo flere externe referencer, jo større sandsynlighed for AI-nævnelse.',
        impact: 'Strategisk',
      });
    }
    if (total > 0 && noAI.length === 0 && actions.length === 0) {
      actions.push({
        color: '#4ade80',
        title: 'Alle søgeord har AI-synlighed – udvid med nye søgeord',
        desc:  'God synlighed på nuværende søgeord. Overvej at tilføje long-tail varianter og spørgsmålsbaserede søgeord (fx "hvad koster kundeservice outsourcing?") for at fange endnu mere AI-trafik.',
        impact: 'Vækst',
      });
    }
    return actions;
  }

  function kwNextStep(k) {
    if (k.has_ai_overview == 1 && k.has_featured_snippet == 1 && k.has_paa == 1) {
      return '<span style="color:#4ade80">✓ Fuld synlighed – hold indhold opdateret</span>';
    }
    if (k.has_ai_overview != 1) return 'Skriv 300+ ord FAQ-svar på søgeordet';
    if (k.has_featured_snippet != 1) return 'Strukturér svar: H2 + 50-ords afsnit';
    if (k.has_paa != 1) return 'Tilføj 5 Q&A med FAQPage schema';
    return '–';
  }

  function kwPriority(k) {
    if (k.has_ai_overview != 1)
      return '<span style="font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(239,68,68,.15);color:#ef4444">🔴 Høj</span>';
    if (k.has_featured_snippet != 1 || k.has_paa != 1)
      return '<span style="font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(249,115,22,.15);color:#f97316">🟡 Middel</span>';
    return '<span style="font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(74,222,128,.15);color:#4ade80">🟢 OK</span>';
  }

  // ── loadAI ────────────────────────────────────────────────────────────────

  async function loadAI(days) {
    const [sum, logs, ov, kwStatus] = await Promise.all([
      api(`/ai/summary?days=${days}`),
      api(`/ai/manual-logs?days=${days}`),
      api(`/ai/overview?days=${days}`),
      api('/ai/keyword-status'),
    ]);
    const s        = sum.data      || {};
    const logData  = logs.data     || [];
    const ovData   = ov.data       || [];
    const kwData   = kwStatus.data || {};
    const keywords = kwData.keywords || [];
    const tracked  = kwData.tracked  || [];
    const hasKey   = kwData.has_api_key;

    const total    = keywords.length;
    const withAI   = keywords.filter(k => k.has_ai_overview == 1).length;
    const withSnip = keywords.filter(k => k.has_featured_snippet == 1).length;
    const withPAA  = keywords.filter(k => k.has_paa == 1).length;
    const mentioned= logData.filter(l => l.rezponz_mentioned == 1).length;

    // Score: AI Overview 50 %, Snippet 30 %, PAA 20 %
    const score = total > 0
      ? Math.round((withAI / total * 50) + (withSnip / total * 30) + (withPAA / total * 20))
      : 0;

    // ── SVG score-ring ────────────────────────────────────
    const arc       = el('ai-score-arc');
    const pctEl     = el('ai-score-pct');
    const verdictEl = el('ai-score-verdict');

    if (arc) {
      const circumference = 239; // 2π × 38
      const offset = circumference - (score / 100 * circumference);
      let ringColor = '#ef4444';
      if (score >= 70) ringColor = '#4ade80';
      else if (score >= 40) ringColor = '#CCFF00';
      else if (score >= 15) ringColor = '#f59e0b';
      arc.style.stroke = ringColor;
      setTimeout(() => { arc.style.strokeDashoffset = offset; }, 120);
    }
    if (pctEl) pctEl.textContent = total > 0 ? score + '%' : '–';

    if (verdictEl) {
      let vText, vBg, vColor;
      if (!total)        { vText = 'Ingen data';  vBg = 'rgba(255,255,255,.06)'; vColor = '#555'; }
      else if (score>=70){ vText = '🔥 Stærk';    vBg = 'rgba(74,222,128,.12)'; vColor = '#4ade80'; }
      else if (score>=40){ vText = '✅ OK';        vBg = 'rgba(204,255,0,.10)';  vColor = '#CCFF00'; }
      else if (score>=15){ vText = '⚠️ Svag';     vBg = 'rgba(245,158,11,.12)'; vColor = '#f59e0b'; }
      else               { vText = '🚨 Kritisk';  vBg = 'rgba(239,68,68,.12)';  vColor = '#ef4444'; }
      verdictEl.textContent      = vText;
      verdictEl.style.background = vBg;
      verdictEl.style.color      = vColor;
    }

    // ── Status banner ─────────────────────────────────────
    const headlineEl = el('ai-status-headline');
    const subEl      = el('ai-status-sub');
    const pillsEl    = el('ai-status-pills');
    const noAI       = keywords.filter(k => k.has_ai_overview != 1);

    if (!total && !hasKey) {
      if (headlineEl) headlineEl.textContent = 'Kom i gang med AI-synlighedssporing';
      if (subEl)      subEl.innerHTML = 'Tilføj din SerpAPI-nøgle og søgeord under <a href="' + (RZPA?.adminUrl||'#') + '?page=rzpa-settings" style="color:var(--neon)">Indstillinger</a> for at begynde at tracke din placering i Google AI Overviews.';
    } else if (!total) {
      if (headlineEl) headlineEl.textContent = 'Klar til første sync';
      if (subEl)      subEl.innerHTML = `${tracked.length} søgeord konfigureret. Klik <strong style="color:#fff">"⟳ Sync SerpAPI"</strong> øverst til højre for at hente din nuværende synlighed.`;
    } else if (score >= 70) {
      if (headlineEl) headlineEl.textContent = `Fremragende AI-synlighed – ${score}% score`;
      if (subEl)      subEl.innerHTML = `${withAI} af ${total} søgeord har AI Overview. Du er godt positioneret. Fokus nu: hold indhold frisk og udvid med nye søgeord.`;
    } else if (score >= 40) {
      if (headlineEl) headlineEl.textContent = `God start – ${score}% score, men der er plads til forbedring`;
      if (subEl)      subEl.innerHTML = `${noAI.length} søgeord mangler AI Overview. Struktureret FAQ-indhold er din hurtigste vej til bedre synlighed – se handlingsplanen nedenfor.`;
    } else {
      if (headlineEl) headlineEl.textContent = `${score}% score – Stort optimeringspotentiale`;
      if (subEl)      subEl.innerHTML = `Kun ${withAI} af ${total} søgeord har AI Overview. Følg handlingsplanen nedenfor trin for trin for at forbedre din synlighed hurtigt.`;
    }

    if (pillsEl) {
      const pills = [];
      if (!hasKey) pills.push(['🔑 Mangler SerpAPI-nøgle', '#f59e0b']);
      else         pills.push(['✓ SerpAPI aktiv', '#4ade80']);
      if (total)          pills.push([`${total} søgeord tracket`, '#60a5fa']);
      if (logData.length) pills.push([`${logData.length} AI-logs`, '#a78bfa']);
      if (mentioned > 0)  pills.push([`Rezponz nævnt ${mentioned}×`, '#CCFF00']);
      pillsEl.innerHTML = pills.map(([txt, c]) =>
        `<span style="font-size:11px;padding:3px 10px;border-radius:12px;background:${c}22;color:${c};border:1px solid ${c}44">${txt}</span>`
      ).join('');
    }

    // ── KPIer ─────────────────────────────────────────────
    renderKPI('kpi_ai_ov',    total > 0 ? withAI   : '–');
    renderKPI('kpi_snippets', total > 0 ? withSnip  : '–');
    renderKPI('kpi_paa',      total > 0 ? withPAA   : '–');
    renderKPI('kpi_mentioned',logData.length > 0 ? mentioned : '–');

    // ── Handlingsplan ─────────────────────────────────────
    const planEl    = el('ai-action-plan');
    const planCount = el('ai-plan-count');
    if (planEl) {
      const fixableTypes   = ['faq_pages', 'featured_snippet', 'paa_sections'];
      const getStored      = t => { try { return JSON.parse(localStorage.getItem(`rzpa_fiks_${t}`) || 'null'); } catch(e) { return null; } };
      const allActions     = generateActionPlan(keywords, logData, hasKey, tracked);
      // Fjern handlinger der allerede er udført (gemt i localStorage)
      const actions        = allActions.filter(a => !fixableTypes.includes(a.type) || !getStored(a.type));
      const doneCount      = allActions.length - actions.length;
      if (planCount) planCount.textContent = actions.length ? `${actions.length} handlinger${doneCount ? ` · ${doneCount} afsluttet` : ''}` : '';
      if (!actions.length) {
        planEl.innerHTML = '<div style="padding:16px 0;color:#4ade80;font-size:13px">✅ Ingen aktive handlinger – godt arbejde! Din AI-synlighed forbedres løbende.</div>';
      } else {
        planEl.innerHTML = actions.map((a, i) => {
          const storeKey = a.type ? `rzpa_fiks_${a.type}` : null;
          const stored   = storeKey ? getStored(a.type) : null;
          let fixBtn = '';
          if (a.type === 'settings_link') {
            fixBtn = `<a href="${RZPA?.settingsUrl||'#'}" class="rzpa-fiks-btn">${a.fixLabel}</a>`;
          } else if (a.type === 'sync_btn') {
            fixBtn = `<button class="rzpa-fiks-btn" onclick="document.getElementById('rzpa-ai-sync')?.click()">${a.fixLabel}</button>`;
          } else if (a.type === 'scroll_logs') {
            fixBtn = `<button class="rzpa-fiks-btn rzpa-fiks-scroll" data-target="rzpa-log-form">${a.fixLabel}</button>`;
          } else if (a.type && stored) {
            if (stored.created) {
              fixBtn = `<span style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
                <a href="${stored.editUrl}" target="_blank" class="rzpa-fiks-btn rzpa-fiks-done" style="background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#f59e0b">📝 Publicer kladde →</a>
                <span style="font-size:10px;color:#555">"${(stored.title||'').substring(0,35)}${(stored.title||'').length>35?'…':''}"</span>
              </span>`;
            } else {
              fixBtn = `<span style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
                <a href="${stored.viewUrl}" target="_blank" class="rzpa-fiks-btn rzpa-fiks-done">✅ Live – se side →</a>
                <a href="${stored.editUrl}" target="_blank" style="font-size:10px;color:#555;text-decoration:none">Rediger i WordPress →</a>
              </span>`;
            }
          } else if (a.type) {
            fixBtn = `<button class="rzpa-fiks-btn" data-fix-type="${a.type}" data-fix-kw="${encodeURIComponent(JSON.stringify(a.fixKw||[]))}">${a.fixLabel}</button>`;
          }
          return `
          <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:rgba(255,255,255,.03);border-radius:10px;border-left:3px solid ${a.color}">
            <div style="width:22px;height:22px;border-radius:50%;background:${a.color}22;border:1px solid ${a.color}55;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:800;color:${a.color}">${i+1}</div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:#e5e5e5;margin-bottom:4px">${a.title}</div>
              <div style="font-size:12px;color:#777;line-height:1.55">${a.desc}</div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">
              <span style="font-size:10px;padding:3px 9px;border-radius:10px;background:${a.color}18;color:${a.color};white-space:nowrap">${a.impact}</span>
              ${fixBtn}
            </div>
          </div>`;
        }).join('');

        // Scroll-knapper (åbn evt. skjult formular + scroll til den)
        planEl.querySelectorAll('.rzpa-fiks-scroll').forEach(btn => {
          btn.addEventListener('click', () => {
            const t = document.getElementById(btn.dataset.target);
            if (!t) return;
            // Vis formularen hvis den er skjult (toggle-baseret)
            if (t.style.display === 'none' || t.style.display === '') t.style.display = 'block';
            setTimeout(() => t.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
          });
        });

        // API fix-knapper
        planEl.querySelectorAll('[data-fix-type]').forEach(btn => {
          btn.addEventListener('click', async () => {
            const type = btn.dataset.fixType;
            const keywords = (() => { try { return JSON.parse(decodeURIComponent(btn.dataset.fixKw || '%5B%5D')); } catch(e) { return []; } })();
            btn.disabled = true;
            btn.textContent = '⏳ Fikser…';
            try {
              const res = await api('/ai/fix-action', { method: 'POST', body: JSON.stringify({ type, keywords }) });
              if (res.ok && res.edit_url) {
                try {
                  localStorage.setItem(`rzpa_fiks_${type}`, JSON.stringify({
                    editUrl: res.edit_url, viewUrl: res.view_url || '',
                    title: res.title || res.label || '', created: !!res.created,
                  }));
                } catch(e) {}
                if (res.created) {
                  btn.outerHTML = `<span style="display:flex;flex-direction:column;gap:5px;align-items:flex-end">
                    <a href="${res.edit_url}" target="_blank" class="rzpa-fiks-btn rzpa-fiks-done" style="background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#f59e0b">📝 Kladde klar – publicer nu →</a>
                    <span style="font-size:10px;color:#666">"${(res.title||'').substring(0,40)}${(res.title||'').length>40?'…':''}"</span>
                  </span>`;
                } else {
                  btn.outerHTML = `<span style="display:flex;flex-direction:column;gap:5px;align-items:flex-end">
                    <a href="${res.view_url||res.edit_url}" target="_blank" class="rzpa-fiks-btn rzpa-fiks-done">✅ Opdateret – se live →</a>
                    <a href="${res.edit_url}" target="_blank" style="font-size:10px;color:#555;text-decoration:none">Rediger i WordPress →</a>
                  </span>`;
                }
              } else {
                const msg = res.message || res.data?.message || '✗ Fejl';
                btn.textContent = msg.length > 30 ? '✗ Fejl' : msg;
                btn.title = msg;
                btn.style.color = '#ff5555';
                btn.disabled = false;
              }
            } catch(err) {
              btn.textContent = '✗ Fejl';
              btn.style.color = '#ff5555';
              btn.disabled = false;
            }
          });
        });
      }
    }

    // ── Søgeord-tabel ─────────────────────────────────────
    const kwTbody = el('ai-kw-tbody');
    const kwCount = el('ai-kw-count');
    if (kwTbody) {
      if (!hasKey && !total) {
        kwTbody.innerHTML = `<tr><td colspan="6" style="padding:28px 20px;color:#555;text-align:center">
          Tilføj SerpAPI-nøgle og søgeord under
          <a href="${RZPA?.adminUrl||'#'}?page=rzpa-settings" style="color:var(--neon)">Indstillinger</a>
          for at begynde at tracke.
        </td></tr>`;
      } else if (!total) {
        kwTbody.innerHTML = (tracked.length ? tracked : ['–']).map(kw => `
          <tr data-filter="missing">
            <td style="padding-left:20px;font-weight:500;color:#aaa">${kw}</td>
            <td style="text-align:center"><span style="color:#333">–</span></td>
            <td style="text-align:center"><span style="color:#333">–</span></td>
            <td style="text-align:center"><span style="color:#333">–</span></td>
            <td style="color:#555;font-size:12px">Klik Sync SerpAPI</td>
            <td style="text-align:center"><span style="font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(245,158,11,.15);color:#f59e0b">Venter</span></td>
          </tr>`).join('');
      } else {
        const chk = v => v == 1
          ? '<span style="color:#4ade80;font-size:15px">✓</span>'
          : '<span style="color:#2d2d2d;font-size:13px">✗</span>';
        // Søgeord der allerede har fået oprettet FAQ/udkast (gemt i localStorage)
        const kwDone = (() => { try { return JSON.parse(localStorage.getItem('rzpa_kw_done') || '[]'); } catch(e) { return []; } })();
        kwTbody.innerHTML = keywords.map(k => {
          const filter  = k.has_ai_overview == 1 ? 'has_ai' : 'missing';
          const kwEnc   = encodeURIComponent(JSON.stringify([k.keyword]));
          const fixType = k.has_ai_overview != 1 ? 'faq_pages' : k.has_featured_snippet != 1 ? 'featured_snippet' : k.has_paa != 1 ? 'paa_sections' : null;
          const fixLbl  = k.has_ai_overview != 1 ? 'Opret FAQ' : k.has_featured_snippet != 1 ? 'Opret udkast' : k.has_paa != 1 ? 'Opret Q&A' : '';
          // Vis "Kladde oprettet" badge hvis allerede behandlet denne session/reload
          const isDone  = kwDone.includes(k.keyword);
          const doneHtml = isDone ? `<span style="margin-left:6px;font-size:10px;color:#4ade80;white-space:nowrap">✓ Kladde oprettet</span>` : '';
          const fixBtn  = (!isDone && fixType) ? `<button class="rzpa-kw-fix-btn" data-fix-type="${fixType}" data-fix-kw="${kwEnc}" style="margin-left:6px;font-size:10px;padding:2px 7px;border-radius:6px;border:1px solid rgba(204,255,0,.25);background:rgba(204,255,0,.06);color:var(--neon);cursor:pointer;white-space:nowrap">${fixLbl}</button>` : '';
          return `<tr data-filter="${filter}">
            <td style="padding-left:20px;font-weight:500;color:#e5e5e5">${k.keyword}</td>
            <td style="text-align:center">${chk(k.has_ai_overview)}</td>
            <td style="text-align:center">${chk(k.has_featured_snippet)}</td>
            <td style="text-align:center">${chk(k.has_paa)}</td>
            <td style="font-size:12px;color:#999">${kwNextStep(k)}${fixBtn}${doneHtml}</td>
            <td style="text-align:center">${kwPriority(k)}</td>
          </tr>`;
        }).join('');
        if (kwCount) kwCount.textContent = `${total} søgeord`;

        // Klik-handler for per-søgeord Fiks-knapper
        kwTbody.querySelectorAll('.rzpa-kw-fix-btn').forEach(btn => {
          btn.addEventListener('click', async e => {
            e.stopPropagation();
            const type = btn.dataset.fixType;
            const kws  = (() => { try { return JSON.parse(decodeURIComponent(btn.dataset.fixKw || '%5B%5D')); } catch(err) { return []; } })();
            btn.disabled = true;
            btn.textContent = '⏳';
            try {
              const res = await api('/ai/fix-action', { method: 'POST', body: JSON.stringify({ type, keywords: kws }) });
              if (res.ok && res.edit_url) {
                // Gem i localStorage så "Kladde oprettet" vises ved næste reload
                try {
                  const done = JSON.parse(localStorage.getItem('rzpa_kw_done') || '[]');
                  if (!done.includes(kws[0])) { done.push(kws[0]); localStorage.setItem('rzpa_kw_done', JSON.stringify(done)); }
                } catch(e) {}
                const tr = btn.closest('tr');
                // Vis link + fade rækken ned
                btn.outerHTML = `<a href="${res.edit_url}" target="_blank" style="margin-left:6px;font-size:10px;color:#4ade80;text-decoration:none;white-space:nowrap">✓ Kladde klar →</a>`;
                if (tr) {
                  tr.style.transition = 'opacity .6s, background .6s';
                  tr.style.opacity = '0.35';
                  tr.style.background = 'rgba(74,222,128,.03)';
                  setTimeout(() => { if (tr.parentNode) tr.remove(); }, 2500);
                }
              } else {
                btn.textContent = '✗';
                btn.title = res.message || '';
                btn.style.color = '#ff5555';
                btn.disabled = false;
              }
            } catch(err) {
              btn.textContent = '✗';
              btn.style.color = '#ff5555';
              btn.disabled = false;
            }
          });
        });
      }
    }

    // ── AI Overview tekster ────────────────────────────────
    const textsCard  = el('ai-overview-texts');
    const textsInner = el('ai-overview-texts-inner');
    const withText   = keywords.filter(k => k.ai_overview_text);
    if (textsCard && textsInner) {
      if (withText.length) {
        textsCard.style.display = 'block';
        textsInner.innerHTML = withText.map(k => {
          const isMentioned = k.ai_overview_text && k.ai_overview_text.toLowerCase().includes('rezponz');
          return `<div style="padding:12px 14px;background:rgba(255,255,255,.03);border-radius:8px;border:1px solid ${isMentioned ? '#CCFF0033' : 'var(--border)'}">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.05em">${k.keyword}</span>
              ${isMentioned
                ? '<span style="font-size:10px;padding:1px 7px;border-radius:10px;background:rgba(204,255,0,.12);color:#CCFF00">Rezponz nævnt ✓</span>'
                : '<span style="font-size:10px;padding:1px 7px;border-radius:10px;background:rgba(239,68,68,.12);color:#ef4444">Ikke nævnt</span>'}
            </div>
            <p style="margin:0;font-size:12px;color:#999;line-height:1.6">${k.ai_overview_text}</p>
          </div>`;
        }).join('');
      } else {
        textsCard.style.display = 'none';
      }
    }

    // ── Trend-chart ────────────────────────────────────────
    const byDate = {};
    ovData.forEach(row => {
      if (!byDate[row.date]) byDate[row.date] = { ai: 0, snippet: 0 };
      if (row.has_ai_overview)      byDate[row.date].ai++;
      if (row.has_featured_snippet) byDate[row.date].snippet++;
    });
    const dates = Object.keys(byDate).sort().slice(-14);
    if (dates.length) {
      barChart('chart_ai_ov', dates.map(d => d.slice(5)), [
        { label: 'AI Overview',      data: dates.map(d => byDate[d].ai),      backgroundColor: '#CCFF00', borderRadius: 4 },
        { label: 'Featured Snippet', data: dates.map(d => byDate[d].snippet), backgroundColor: '#4488ff', borderRadius: 4 },
      ]);
    }

    // ── Manuel log-tabel ──────────────────────────────────
    const tbody = el('ai_log_tbody');
    if (tbody) {
      if (!logData.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="rzpa-empty">Ingen logs endnu – tilføj din første med "+ Tilføj log".</td></tr>';
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
  // PAGE: REKRUTTERING
  // ════════════════════════════════════════════════════

  if (el('page-rekruttering')) {
    let rekrutDays = 30;
    const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    const rekrutDaysSel = el('rekrut-days');
    const rekrutRefresh = el('rekrut-refresh');
    if (rekrutDaysSel) rekrutDaysSel.addEventListener('change', () => { rekrutDays = parseInt(rekrutDaysSel.value); loadRekruttering(rekrutDays, true); });
    if (rekrutRefresh) rekrutRefresh.addEventListener('click', async () => {
      const orig = rekrutRefresh.textContent;
      rekrutRefresh.textContent = '⏳ Henter…';
      rekrutRefresh.disabled = true;
      await loadRekruttering(rekrutDays, true);
      rekrutRefresh.textContent = '✓ Opdateret';
      setTimeout(() => { rekrutRefresh.textContent = orig; rekrutRefresh.disabled = false; }, 2000);
    });

    // Filter-knapper
    document.querySelectorAll('[data-rekrut-filter]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-rekrut-filter]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const f = btn.dataset.rekrutFilter;
        document.querySelectorAll('#rekrut-campaigns-tbody tr[data-channel]').forEach(row => {
          row.style.display = (f === 'all' || row.dataset.channel === f) ? '' : 'none';
        });
      });
    });

    // Pipeline: vis Gem-knap ved ændring
    document.addEventListener('input', e => {
      if (e.target.classList.contains('pipeline-input')) {
        const saveBtn = el('rekrut-pipeline-save');
        if (saveBtn) saveBtn.style.display = '';
      }
    });

    // Gem pipeline
    el('rekrut-pipeline-save')?.addEventListener('click', async () => {
      const inputs = document.querySelectorAll('.pipeline-input');
      const data = {};
      inputs.forEach(inp => {
        const stage = inp.dataset.stage;
        const col   = inp.dataset.col;
        if (!data[stage]) data[stage] = {};
        data[stage][col] = parseInt(inp.value) || 0;
      });
      await api('/rekruttering/pipeline', { method: 'POST', body: JSON.stringify(data) });
      const saveBtn = el('rekrut-pipeline-save');
      if (saveBtn) { saveBtn.textContent = '✓ Gemt'; setTimeout(() => { saveBtn.textContent = '💾 Gem'; saveBtn.style.display = 'none'; }, 2000); }
    });

    loadRekruttering(rekrutDays);

    // ── AI Rekrutteringsrapport ────────────────────────────────────
    const aiReportBtn = el('rekrut-ai-report-btn');
    if (aiReportBtn) {
      aiReportBtn.addEventListener('click', async () => {
        const force = aiReportBtn.dataset.generated === '1';
        aiReportBtn.textContent = '⏳ Analyserer…';
        aiReportBtn.disabled = true;
        const reportEl = el('rekrut-ai-report');
        if (reportEl) reportEl.innerHTML = '<div style="color:#555;font-size:13px;padding:8px 0">🤖 GPT-4.1 mini analyserer dine rekrutteringstal…</div>';
        try {
          const params = new URLSearchParams({ days: rekrutDays });
          if (force) params.set('force', '1');
          const res = await api('/rekruttering/ai-report?' + params.toString());
          const d = res.data || res;
          if (d.ok === false) throw new Error(d.error || 'Fejl');
          renderAIReport(d);
          aiReportBtn.textContent = '🔄 Opdater rapport';
          aiReportBtn.dataset.generated = '1';
        } catch(err) {
          if (reportEl) reportEl.innerHTML = `<div style="color:#ff5555;font-size:13px">⚠ ${err.message}</div>`;
          aiReportBtn.textContent = '✨ Prøv igen';
        }
        aiReportBtn.disabled = false;
      });
    }

    function renderAIReport(d) {
      const reportEl = el('rekrut-ai-report');
      if (!reportEl) return;
      const recs = (d.recommendations || []).map(r => {
        const borderClass = r.priority === 'høj' ? 'high' : r.priority === 'middel' ? 'middel' : 'lav';
        return `<div class="ai-report-card ${borderClass}">
          <div style="font-size:22px;flex-shrink:0">${r.icon || '💡'}</div>
          <div>
            <div style="font-weight:600;font-size:13px;color:#e5e5e5;margin-bottom:4px">${esc(r.title)}</div>
            <div style="font-size:12px;color:#aaa;line-height:1.5;margin-bottom:6px">${esc(r.text)}</div>
            <div style="font-size:11px;color:var(--neon);font-weight:600">→ ${esc(r.action)}</div>
          </div>
        </div>`;
      }).join('');
      const ts = d.generated_at ? `<div style="font-size:10px;color:#444;margin-top:14px;text-align:right">🤖 GPT-4.1 mini · ${d.generated_at}</div>` : '';
      reportEl.innerHTML = `
        <div style="margin-bottom:12px">
          <div style="font-size:15px;font-weight:600;color:#e5e5e5;margin-bottom:6px">${esc(d.headline || '')}</div>
          <div style="font-size:13px;color:#888;line-height:1.5">${esc(d.summary || '')}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">${recs}</div>
        ${ts}`;
    }

    // ── Jobopslag Generator ────────────────────────────────────────
    const jobadBtn = el('jobad-generate-btn');
    if (jobadBtn) {
      jobadBtn.addEventListener('click', async () => {
        const role     = (el('jobad-role')?.value || '').trim();
        const location = el('jobad-location')?.value || 'Aalborg';
        const tone     = el('jobad-tone')?.value || 'professionel og imødekommende';
        const points   = (el('jobad-points')?.value || '').split('\n').map(s => s.trim()).filter(Boolean);

        if (!role) { el('jobad-role').focus(); return; }

        jobadBtn.textContent = '⏳ Genererer…';
        jobadBtn.disabled = true;
        const resultEl = el('jobad-result');
        if (resultEl) { resultEl.style.display = 'none'; resultEl.innerHTML = ''; }

        try {
          const res = await api('/rekruttering/generate-job-ad', {
            method: 'POST',
            body: JSON.stringify({ role, location, tone, points })
          });
          const d = res.data || res;
          if (d.ok === false) throw new Error(d.error || 'Fejl');
          renderJobAd(d.ad, role, location, resultEl);
          jobadBtn.textContent = '✨ Generer igen';
        } catch(err) {
          if (resultEl) { resultEl.innerHTML = `<div style="color:#ff5555">⚠ ${esc(err.message)}</div>`; resultEl.style.display = ''; }
          jobadBtn.textContent = '✨ Generer jobopslag';
        }
        jobadBtn.disabled = false;
      });
    }

    function renderJobAd(ad, role, location, container) {
      if (!ad || !container) return;
      const m = ad.meta || {};
      const g = ad.google || {};
      const tips = (ad.tips || []).map(t => `<li style="font-size:12px;color:#aaa;padding:2px 0">💡 ${esc(t)}</li>`).join('');

      const field = (label, value, charLimit) => {
        const len = (value || '').length;
        const over = charLimit && len > charLimit;
        const counter = charLimit ? `<span style="color:${over ? '#ef4444' : '#555'}">${len}/${charLimit}</span>` : '';
        return `<div class="jobad-field">
          <div class="jobad-field-label">${label} ${counter} <button class="jobad-copy-btn" data-copy="${esc(value || '')}">Kopiér</button></div>
          <div class="jobad-field-value">${esc(value || '')}</div>
        </div>`;
      };

      container.innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div>
            <div style="font-size:12px;font-weight:600;color:#60a5fa;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px">📘 Meta Ads</div>
            ${field('Primær tekst (125 tegn)', m.primary_text, 125)}
            ${field('Overskrift (40 tegn)', m.headline, 40)}
            ${field('Beskrivelse', m.description)}
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;color:#34a853;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px">🔍 Google Search (RSA)</div>
            ${field('Headline 1 (30 tegn)', g.headline1, 30)}
            ${field('Headline 2 (30 tegn)', g.headline2, 30)}
            ${field('Headline 3 (30 tegn)', g.headline3, 30)}
            ${field('Beskrivelse 1 (90 tegn)', g.desc1, 90)}
            ${field('Beskrivelse 2 (90 tegn)', g.desc2, 90)}
          </div>
        </div>
        ${tips ? `<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)"><div style="font-size:11px;color:#555;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Targeting-tips</div><ul style="margin:0;padding:0 0 0 14px">${tips}</ul></div>` : ''}
        <div style="margin-top:14px;font-size:10px;color:#444;text-align:right">🤖 GPT-4.1 mini · ${role} · ${location}</div>`;
      container.style.display = '';

      // Kopiér-knapper
      container.querySelectorAll('.jobad-copy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          navigator.clipboard?.writeText(btn.dataset.copy || '').then(() => {
            const orig = btn.textContent;
            btn.textContent = '✓ Kopieret';
            setTimeout(() => btn.textContent = orig, 1500);
          });
        });
      });
    }
  }

  async function loadRekruttering(days, force = false) {
    const params = new URLSearchParams({ days });
    if (force) params.set('force', '1');
    const r = await api('/rekruttering/stats?' + params.toString());
    const d = r.data || r;
    if (!d || d.error) return;

    const t = d.totals || {};
    const pipeline = d.pipeline || {};
    const meta     = d.meta_campaigns   || [];
    const google   = d.google_campaigns || [];
    const all      = [...meta, ...google];

    // ── KPIs ──────────────────────────────────────────────
    const hired = (pipeline.ansat?.aalborg||0) + (pipeline.ansat?.remote||0) + (pipeline.ansat?.uopfordret||0);
    renderKPI('rekrut-kpi-leads',  t.leads  || 0);
    renderKPI('rekrut-kpi-cpl',    t.leads  > 0 ? (t.cpl + ' kr.') : '–');
    renderKPI('rekrut-kpi-spend',  t.spend  > 0 ? fmt(t.spend, 0) + ' kr.' : '–');
    renderKPI('rekrut-kpi-hired',  hired || '–');
    const leadsSubEl = el('rekrut-kpi-leads-sub');
    if (leadsSubEl) leadsSubEl.textContent = `Meta: ${t.meta_leads||0} · Google: ${t.google_leads||0}`;
    const spendSubEl = el('rekrut-kpi-spend-sub');
    if (spendSubEl) spendSubEl.textContent = `Meta: ${fmt(t.meta_spend||0,0)} kr. · Google: ${fmt(t.google_spend||0,0)} kr.`;

    // ── Kanalfordeling ────────────────────────────────────
    const chanEl = el('rekrut-channels');
    if (chanEl) {
      const totalLeads = (t.meta_leads||0) + (t.google_leads||0);
      const channels = [
        { label: 'Meta Ads',    leads: t.meta_leads||0,   spend: t.meta_spend||0,   color: '#4488ff' },
        { label: 'Google Ads',  leads: t.google_leads||0, spend: t.google_spend||0, color: '#CCFF00' },
      ];
      chanEl.innerHTML = channels.map(c => {
        const pct = totalLeads > 0 ? Math.round(c.leads / totalLeads * 100) : 0;
        const cpl = c.leads > 0 ? Math.round(c.spend / c.leads) : 0;
        return `
          <div class="channel-bar-wrap">
            <div style="width:90px;font-size:12px;color:#aaa;flex-shrink:0">${c.label}</div>
            <div class="channel-bar-bg">
              <div class="channel-bar-fill" style="width:${pct}%;background:${c.color}"></div>
            </div>
            <div style="width:40px;text-align:right;font-size:12px;font-weight:700;color:#e5e5e5">${c.leads}</div>
            <div style="width:70px;text-align:right;font-size:11px;color:#666">${cpl ? cpl + ' kr/ans.' : '–'}</div>
          </div>`;
      }).join('') + (totalLeads === 0 ? '<div style="color:#555;font-size:13px;padding:8px 0">Ingen ansøgningsdata for perioden.<br><small style="color:#444">Meta Pixel tracker Lead-events — sikr at eventet er aktivt.</small></div>' : '');
    }

    // ── Pipeline ──────────────────────────────────────────
    const pipeEl = el('rekrut-pipeline-rows');
    if (pipeEl) {
      const stageColors = { ansoegt:'#60a5fa', screenet:'#a78bfa', samtale:'#CCFF00', tilbudt:'#f59e0b', ansat:'#4ade80' };
      pipeEl.innerHTML = Object.entries(pipeline).map(([stage, vals]) => {
        const col = stageColors[stage] || '#666';
        const total = (vals.aalborg||0) + (vals.remote||0) + (vals.uopfordret||0);
        return `
          <div style="display:grid;grid-template-columns:1fr auto auto auto;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.05)">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="width:8px;height:8px;border-radius:50%;background:${col};flex-shrink:0;display:inline-block"></span>
              <span style="font-size:13px;color:#ccc">${vals.label}</span>
              <span style="font-size:11px;color:#555">(${total})</span>
            </div>
            ${['aalborg','remote','uopfordret'].map(col =>
              `<input type="number" min="0" class="pipeline-input" data-stage="${stage}" data-col="${col}" value="${vals[col]||0}" title="${col}">`
            ).join('')}
          </div>`;
      }).join('');
    }

    // ── Kampagnetabel ─────────────────────────────────────
    const tbody = el('rekrut-campaigns-tbody');
    if (tbody) {
      if (!all.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="rzpa-empty">Ingen kampagnedata for perioden</td></tr>';
      } else {
        tbody.innerHTML = all.map(c => {
          const cplBadge = c.cpl <= 0 ? '<span style="color:#555">–</span>'
            : c.cpl <= 100 ? `<span style="color:#4ade80;font-weight:600">${fmt(c.cpl,0)} kr.</span>`
            : c.cpl <= 300 ? `<span style="color:#f59e0b;font-weight:600">${fmt(c.cpl,0)} kr.</span>`
            : `<span style="color:#ef4444;font-weight:600">${fmt(c.cpl,0)} kr.</span>`;

          const rating = c.leads >= 10 ? '<span class="badge badge-active">🟢 Godt</span>'
            : c.leads >= 3 ? '<span class="badge badge-paused">🟡 Middel</span>'
            : c.leads > 0  ? '<span class="badge" style="background:#ef444420;color:#ef4444">🔴 Lavt</span>'
            : '<span style="color:#444;font-size:11px">Ingen leads</span>';

          const chanBadge = c.channel === 'meta'
            ? '<span style="font-size:10px;padding:2px 7px;border-radius:10px;background:#4488ff20;color:#4488ff">Meta</span>'
            : '<span style="font-size:10px;padding:2px 7px;border-radius:10px;background:#CCFF0020;color:#CCFF00">Google</span>';

          return `<tr data-channel="${c.channel}">
            <td style="font-weight:500">${c.campaign_name}</td>
            <td>${chanBadge}</td>
            <td style="text-align:right">${fmt(c.spend,0)} kr.</td>
            <td style="text-align:right;font-weight:700">${c.leads}</td>
            <td style="text-align:right">${cplBadge}</td>
            <td style="text-align:right">${fmt(c.clicks||0)}</td>
            <td style="text-align:right">${c.ctr||0}%</td>
            <td>${rating}</td>
          </tr>`;
        }).join('');
      }
    }

    // ── Anbefalinger ──────────────────────────────────────
    const tipsEl = el('rekrut-tips');
    if (tipsEl) {
      const tips = [];
      const totalLeads = t.leads || 0;
      const cpl = t.cpl || 0;

      if (totalLeads === 0) {
        tips.push({ color:'#ef4444', icon:'🚨', text:'<strong>Ingen ansøgninger sporet.</strong> Meta Pixel-eventet "Lead" skal aktiveres på <code>/tak-for-din-ansoegning/</code>. Kontakt din webmaster for at sætte det op.' });
      } else if (cpl > 500) {
        tips.push({ color:'#f59e0b', icon:'⚠️', text:`<strong>Høj CPL på ${fmt(cpl,0)} kr.</strong> Overvej at teste nye annonce-kreativerne eller justere målgruppen for at sænke kostprisen per ansøger.` });
      } else if (cpl > 0 && cpl <= 100) {
        tips.push({ color:'#4ade80', icon:'✅', text:`<strong>Fremragende CPL på ${fmt(cpl,0)} kr.!</strong> Jeres rekrutteringsannoncer performer rigtig godt. Overvej at øge budgettet for at skalere ansøgningsvolumen.` });
      }

      const noleadsOnMeta = meta.length > 0 && t.meta_leads === 0;
      if (noleadsOnMeta) {
        tips.push({ color:'#60a5fa', icon:'📡', text:'<strong>Meta Ads tracker ingen ansøgninger.</strong> Pixel-eventet "Lead" er ikke koblet til jeres Meta-kampagner. Gå til Events Manager og aktiver eventet på tak-siden.' });
      }

      const topGoogle = google.sort((a,b)=>b.leads-a.leads)[0];
      if (topGoogle && topGoogle.leads > 0) {
        tips.push({ color:'#CCFF00', icon:'🏆', text:`<strong>"${topGoogle.campaign_name}"</strong> er jeres bedste Google-kampagne med ${topGoogle.leads} ansøgninger til ${fmt(topGoogle.cpl||0,0)} kr./ans.` });
      }

      const pipelineAnsoegt = (pipeline.ansoegt?.aalborg||0) + (pipeline.ansoegt?.remote||0) + (pipeline.ansoegt?.uopfordret||0);
      const pipelineAnsat   = (pipeline.ansat?.aalborg||0)   + (pipeline.ansat?.remote||0)   + (pipeline.ansat?.uopfordret||0);
      if (pipelineAnsoegt > 0 && pipelineAnsat > 0) {
        const convRate = Math.round(pipelineAnsat / pipelineAnsoegt * 100);
        tips.push({ color:'#a78bfa', icon:'📊', text:`<strong>Pipeline-konverteringsrate: ${convRate}%</strong> — ${pipelineAnsoegt} ansøgt → ${pipelineAnsat} ansat. ${convRate < 10 ? 'Overvej at optimere screenings-processen.' : 'Flot konverteringsrate!'}` });
      }

      if (!tips.length) {
        tips.push({ color:'#4ade80', icon:'✅', text:'<strong>Alt ser godt ud!</strong> Jeres rekruttering kører. Husk at opdatere pipeline-tallene regelmæssigt.' });
      }

      tipsEl.innerHTML = tips.map(t => `
        <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 12px;background:rgba(255,255,255,.03);border-radius:8px;border-left:3px solid ${t.color}">
          <span style="font-size:15px;flex-shrink:0">${t.icon}</span>
          <span style="font-size:13px;color:#bbb;line-height:1.5">${t.text}</span>
        </div>`).join('');
    }
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

    api(`/meta/campaign-ads?campaign_id=${encodeURIComponent(campaignId)}`, { timeout: 40 }).then(r => {
      const raw = r?.data ?? r;
      const ads = Array.isArray(raw) ? raw : [];
      if (!cards) return;
      // Vis API-fejl hvis returneret
      if (raw && raw.__error) {
        cards.innerHTML = `<p style="color:#ef4444;text-align:center;padding:32px 24px">⚠️ Meta API fejl:<br><small>${raw.__error}</small></p>`;
        return;
      }
      if (!ads.length) {
        cards.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:48px 24px">Ingen annoncer fundet for denne kampagne.<br><small>Kampagnen kan have annoncer med paused/draft status — prøv at tjekke direkte i Meta Ads Manager.</small></p>';
        return;
      }
      const acctId = (RZPA.meta_account_id || '').replace(/^act_/, '');

      // Sorter: aktive først, derefter efter reach
      const sorted = [...ads].sort((a, b) => {
        const aActive = a.status === 'ACTIVE' ? 1 : 0;
        const bActive = b.status === 'ACTIVE' ? 1 : 0;
        if (aActive !== bActive) return bActive - aActive;
        return (b.reach || 0) - (a.reach || 0);
      });

      cards.innerHTML = sorted.map((ad, i) => {
        const thumb = ad.thumbnail_url || ad.image_url;
        const isActive = ad.status === 'ACTIVE';
        const metaUrl = acctId
          ? `https://adsmanager.facebook.com/adsmanager/manage/ads?act=${acctId}`
          : '';

        // Format badge
        const fmtLabel = ad.format === 'video' ? '▶ Video'
                       : ad.format === 'carousel' ? '⊞ Carousel'
                       : '🖼 Billede';

        // Tier
        const r = ad.reach || 0;
        const tier = r >= 5000 ? { label: '🏆 Winner', cls: 'cam-tier-winner' }
                   : r >= 1000 ? { label: '✅ Solid',  cls: 'cam-tier-solid' }
                   : r > 0     ? { label: '🧪 Testing', cls: 'cam-tier-testing' }
                   : null;

        // Status
        const statusMap = {
          ACTIVE:   { label: 'Aktiv',     cls: 'cam-status-active' },
          PAUSED:   { label: 'Paused',    cls: 'cam-status-paused' },
          ARCHIVED: { label: 'Arkiveret', cls: 'cam-status-archived' },
          DELETED:  { label: 'Slettet',   cls: 'cam-status-archived' },
        };
        const statusInfo = statusMap[ad.status] || { label: ad.status || '?', cls: 'cam-status-archived' };

        // Metrics
        const cta = fmtCTA(ad.cta);
        const hasText = ad.title || ad.body;
        const aiBtn = hasText
          ? `<button class="rzpa-ai-copy-btn btn-ghost cam-ai-btn" data-adid="${ad.ad_id}" data-title="${encodeURIComponent(ad.title||'')}" data-body="${encodeURIComponent(ad.body||'')}" data-cta="${encodeURIComponent(cta||'')}">✨ Forbedre tekst</button>`
          : '';

        return `<div class="cam-card ${isActive ? 'cam-card--active' : 'cam-card--paused'}">

          <!-- Thumbnail area -->
          <div class="cam-thumb" id="adprev-${ad.ad_id}">
            ${thumb
              ? `<img src="${thumb}" alt="" loading="lazy" onerror="this.style.display='none';this.parentNode.classList.add('cam-thumb--empty')">`
              : '<div class="cam-thumb-empty">📷</div>'}
            ${ad.has_video ? `<button class="rzpa-play-btn cam-play" data-adid="${ad.ad_id}">▶</button>` : ''}
            <span class="cam-rank">#${i+1}</span>
            <span class="cam-fmt-badge">${fmtLabel}</span>
            <span class="cam-status-badge ${statusInfo.cls}">${statusInfo.label}</span>
          </div>

          <!-- Body -->
          <div class="cam-body">
            ${tier ? `<span class="cam-tier ${tier.cls}">${tier.label}</span>` : ''}
            ${ad.title ? `<div class="cam-title">${ad.title}</div>` : `<div class="cam-title cam-title--name">${ad.ad_name || 'Unavngivet'}</div>`}
            ${ad.body  ? `<div class="cam-copy">${ad.body.substring(0,120)}${ad.body.length>120?'…':''}</div>` : ''}
            ${cta      ? `<span class="cam-cta-pill">${cta}</span>` : ''}
          </div>

          <!-- Metrics -->
          ${r > 0 || ad.spend > 0 ? `
          <div class="cam-metrics">
            <div class="cam-metric">
              <span class="cam-metric-label">👁 Reach</span>
              <strong>${r.toLocaleString('da-DK')}</strong>
            </div>
            ${ad.spend > 0 ? `<div class="cam-metric">
              <span class="cam-metric-label">💰 Forbrug</span>
              <strong class="cam-metric-spend">${parseFloat(ad.spend).toLocaleString('da-DK',{maximumFractionDigits:0})} kr.</strong>
            </div>` : ''}
            ${ad.clicks > 0 ? `<div class="cam-metric">
              <span class="cam-metric-label">🖱 Klik</span>
              <strong>${ad.clicks.toLocaleString('da-DK')}</strong>
            </div>` : ''}
          </div>` : ''}

          <!-- Footer -->
          <div class="cam-footer">
            ${ad.days_active > 0 ? `<span class="cam-days">📅 Aktiv i ${ad.days_active} dage</span>` : ''}
            <div class="cam-footer-actions">
              ${metaUrl ? `<a href="${metaUrl}" target="_blank" class="cam-meta-link">Se i Meta →</a>` : ''}
              ${aiBtn}
            </div>
          </div>
          <div class="rzpa-ai-copy-result" id="aicopy-${ad.ad_id}" style="display:none"></div>
        </div>`;
      }).join('');

      // Play-knapper: hent og vis Meta iframe-preview
      cards.querySelectorAll('.rzpa-play-btn').forEach(btn => {
        btn.addEventListener('click', () => loadAdPreview(btn.dataset.adid, document.getElementById('adprev-' + btn.dataset.adid)));
      });

      // AI copy-knapper
      cards.querySelectorAll('.rzpa-ai-copy-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const result = document.getElementById('aicopy-' + btn.dataset.adid);
          if (!result) return;
          btn.disabled = true; btn.textContent = '⏳ Genererer…';
          result.style.display = 'block';
          result.innerHTML = '<div style="color:#555;font-size:12px;padding:8px 0">✨ AI analyserer teksten og laver 3 forbedringer…</div>';
          const res = await api('/meta/ai-copy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              title: decodeURIComponent(btn.dataset.title || ''),
              body:  decodeURIComponent(btn.dataset.body  || ''),
              cta:   decodeURIComponent(btn.dataset.cta   || ''),
            }),
          });
          btn.disabled = false; btn.textContent = '✨ Forbedre tekst';
          const d = res?.data || {};
          if (d.error) {
            result.innerHTML = `<span style="color:#ff6b6b;font-size:12px">⚠️ ${d.error}</span>`;
            return;
          }
          if (d.suggestions) {
            const vColors = ['#60a5fa','#CCFF00','#f59e0b'];
            const versions = d.suggestions.split(/\n(?=VERSION \d+:)/);
            result.innerHTML = '<div style="margin-top:10px;border-top:1px solid rgba(255,255,255,0.06);padding-top:10px">'
              + versions.map((v, i) => {
                  const lines = v.replace(/^VERSION \d+:\n?/,'').split('\n').filter(l => l.trim());
                  const headline = lines.find(l => l.startsWith('Overskrift:'))?.replace('Overskrift:','').trim() || '';
                  const bodyTxt  = lines.find(l => l.startsWith('Brødtekst:'))?.replace('Brødtekst:','').trim() || '';
                  const why      = lines.find(l => l.startsWith('Hvorfor:'))?.replace('Hvorfor:','').trim() || '';
                  const color    = vColors[i] || '#888';
                  return `<div style="margin-bottom:10px;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:8px;border-left:2px solid ${color}">
                    <div style="font-size:10px;font-weight:700;color:${color};letter-spacing:.8px;margin-bottom:6px">VERSION ${i+1}</div>
                    ${headline ? `<div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:4px">${headline}</div>` : ''}
                    ${bodyTxt  ? `<div style="font-size:11px;color:#aaa;line-height:1.7;margin-bottom:4px">${bodyTxt}</div>` : ''}
                    ${why      ? `<div style="font-size:10px;color:#555;font-style:italic">${why}</div>` : ''}
                    <button class="rzpa-copy-text-btn" data-text="${encodeURIComponent((headline?headline+'\n\n':'')+bodyTxt)}" style="font-size:10px;color:var(--neon);background:none;border:none;cursor:pointer;padding:4px 0;margin-top:4px">📋 Kopiér tekst</button>
                  </div>`;
                }).join('')
              + '</div>';
            // Kopiér-knapper
            result.querySelectorAll('.rzpa-copy-text-btn').forEach(cb => {
              cb.addEventListener('click', () => {
                navigator.clipboard?.writeText(decodeURIComponent(cb.dataset.text)||'').then(() => {
                  cb.textContent = '✓ Kopieret!';
                  setTimeout(() => { cb.textContent = '📋 Kopiér tekst'; }, 2000);
                });
              });
            });
          }
        });
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

  function closeTapDetailModal() {
    const modal = el('rzpa-top-ad-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  function openTopAdDetailModal(ad) {
    const modal = el('rzpa-top-ad-modal');
    if (!modal) return;

    const proxyBase = (RZPA?.restBase || '/wp-json/rzpa/v1/') + 'meta/image-proxy?url=';
    const rawImg = ad.thumbnail_url || ad.image_url;
    const imgSrc = rawImg
      ? proxyBase + encodeURIComponent(rawImg) + (RZPA?.nonce ? '&_wpnonce=' + RZPA.nonce : '')
      : '';

    const fmtMap = { video: '▶ Video', carousel: '⊞ Carousel', image: '🖼 Billede' };
    const fmtLabel = fmtMap[ad.format] || '🖼 Billede';
    const fmtText  = { video: 'Video', carousel: 'Carousel', image: 'Billede' }[ad.format] || 'Billede';

    const reach   = parseInt(ad.reach) || 0;
    const spend   = parseFloat(ad.spend) || 0;
    const days    = ad.days_active || 0;
    const adLink  = `https://www.facebook.com/adsmanager/manage/ads?act=${ad.account_id || ''}&selected_ad_ids=${ad.ad_id || ''}`;

    // Tier badge (shown as status on image)
    const tier = reach >= 5000 ? { label: '🏆 Winner', cls: 'cam-status-active' }
               : reach >= 1000 ? { label: '✅ Solid',  cls: 'cam-status-active' }
               : reach > 0     ? { label: '🧪 Testing', cls: 'cam-status-paused' }
               : null;

    const titleEl = el('rzpa-tap-modal-title');
    const bodyEl  = el('rzpa-tap-modal-body');
    if (titleEl) titleEl.textContent = ad.ad_name || '–';
    if (bodyEl) bodyEl.innerHTML = `
      <div class="rzpa-tap-detail-thumb">
        ${imgSrc
          ? `<img src="${imgSrc}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
          : ''}
        <div class="rzpa-tap-detail-nothumb"${imgSrc ? ' style="display:none"' : ''}>📷</div>
        <span class="cam-fmt-badge">${fmtLabel}</span>
        ${tier ? `<span class="cam-status-badge ${tier.cls}">${tier.label}</span>` : ''}
      </div>

      <div class="rzpa-tap-detail-stats">
        <div class="rzpa-tap-stat">
          <span class="rzpa-tap-stat-icon">👁</span>
          <span class="rzpa-tap-stat-label">Reach</span>
          <strong>${reach.toLocaleString('da-DK')}</strong>
        </div>
        <div class="rzpa-tap-stat">
          <span class="rzpa-tap-stat-icon">💰</span>
          <span class="rzpa-tap-stat-label">Est. Månedligt Spend</span>
          <strong class="rzpa-tap-stat--green">${spend.toLocaleString('da-DK', {maximumFractionDigits:0})} kr.</strong>
        </div>
        <div class="rzpa-tap-stat">
          <span class="rzpa-tap-stat-icon">📅</span>
          <span class="rzpa-tap-stat-label">Aktiv i</span>
          <strong>${days} dage</strong>
        </div>
        <div class="rzpa-tap-stat">
          <span class="rzpa-tap-stat-icon">⊞</span>
          <span class="rzpa-tap-stat-label">Format</span>
          <strong>${fmtText}</strong>
        </div>
      </div>

      ${(ad.body_copy || ad.title) ? `
      <div class="rzpa-tap-detail-copy">
        <div class="rzpa-tap-section-label">Annoncetekst</div>
        ${ad.title ? `<div style="font-size:14px;font-weight:700;color:#1a1a1a;margin-bottom:6px">${ad.title}</div>` : ''}
        ${ad.body_copy ? `<div class="rzpa-tap-detail-body-text">${ad.body_copy}</div>` : ''}
      </div>` : ''}

      <div class="rzpa-tap-detail-footer">
        <a href="${adLink}" target="_blank" rel="noopener" class="cam-meta-link">Se i Meta Ads Manager →</a>
      </div>`;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
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
    if (e.target.id === 'rzpa-tap-modal-close' || e.target.id === 'rzpa-top-ad-modal') closeTapDetailModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAdModal(); closeTapDetailModal(); }
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

  async function loadMonthlyChart(months) {
    months = months || 6;
    try {
      // Clear session cache so new months value always fetches fresh
      try { sessionStorage.removeItem('rzpa||/meta/monthly?months=' + months); } catch(e) {}
      const r = await api('/meta/monthly?months=' + months);
      const data = r.data || [];
      const card = el('meta-monthly-card');
      if (!data.length) { if (card) card.style.display = 'none'; return; }
      if (card) card.style.display = 'block';
      const mNames = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
      const labels = data.map(d => {
        const [y, m] = d.month.split('-');
        return mNames[parseInt(m,10)-1] + ' ' + y.slice(2);
      });
      const ctrs = data.map(d => d.impressions > 0 ? Math.round(d.clicks/d.impressions*10000)/100 : 0);
      const canvas = el('chart_monthly');
      if (!canvas) return;
      if (canvas._chart) canvas._chart.destroy();
      canvas._chart = new Chart(canvas, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            {
              label: 'Forbrug (kr)',
              data: data.map(d => Math.round(d.spend||0)),
              backgroundColor: 'rgba(24,119,242,0.75)',
              borderRadius: 6,
              yAxisID: 'y',
              order: 2,
            },
            {
              label: 'Klik',
              data: data.map(d => d.clicks||0),
              type: 'line',
              borderColor: '#CCFF00',
              backgroundColor: 'rgba(204,255,0,0.08)',
              tension: 0.3,
              pointRadius: 4,
              pointBackgroundColor: '#CCFF00',
              fill: true,
              yAxisID: 'y2',
              order: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: true, labels: { color: '#888', font: { size: 11 }, boxWidth: 12, padding: 16 } },
            tooltip: {
              callbacks: {
                label: ctx => {
                  if (ctx.dataset.yAxisID === 'y')  return ' Forbrug: ' + ctx.parsed.y.toLocaleString('da-DK') + ' kr';
                  if (ctx.dataset.yAxisID === 'y2') return ' Klik: ' + ctx.parsed.y.toLocaleString('da-DK');
                  return ctx.dataset.label + ': ' + ctx.parsed.y;
                }
              }
            }
          },
          scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#666', font: { size: 10 } } },
            y: {
              position: 'left',
              grid: { color: 'rgba(255,255,255,0.05)' },
              ticks: { color: '#666', font: { size: 10 }, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k kr' : v+' kr' }
            },
            y2: {
              position: 'right',
              grid: { drawOnChartArea: false },
              ticks: { color: '#CCFF00', font: { size: 10 }, callback: v => v.toLocaleString('da-DK') },
              min: 0,
            },
          },
        },
      });
    } catch(e) {}
  }

  // ════════════════════════════════════════════════════
  // PAGE: GOOGLE ADS
  // ════════════════════════════════════════════════════

  async function initGoogleAds() {
    let days = 30, gadsAllData = [], gadsSortKey = 'spend', gadsSortDir = 'desc', gadsFilter = 'all';
    initGadsAds();

    function perfClassGads(ctr) { return ctr >= 2 ? 'perf-good' : ctr >= 0.5 ? 'perf-mid' : 'perf-bad'; }
    function perfLabelGads(ctr) { return ctr >= 2 ? 'Godt' : ctr >= 0.5 ? 'Middel' : 'Svagt'; }

    async function syncGads(d) {
      const btn = el('rzpa-sync-gads');
      if (btn) { btn.disabled = true; btn.textContent = 'Henter…'; }
      try {
        const r = await api('/google-ads/sync', { method: 'POST', body: JSON.stringify({days: d}) });
        clearCache('/google-ads/');
        if (btn) {
          const ok = r.data?.success !== false;
          if (!ok && r.data?.error) {
            btn.textContent = '⚠️ Fejl';
            const hBar = el('gads-health-bar');
            if (hBar) {
              let errMsg = r.data.error;
              const settingsLink = `<a href="${(RZPA?.settingsUrl||'admin.php?page=rzpa-settings')+'#google-ads'}" style="color:#CCFF00;text-decoration:underline">⚙️ Indstillinger → Google Ads</a>`;
              if (errMsg.includes('MCC:mangler')) {
                errMsg += ` — Manager Account ID mangler. ${settingsLink} og udfyld feltet.`;
              } else if (errMsg.includes('HTTP:404')) {
                // Try to get a more specific error via test endpoint
                try {
                  const testR = await api('/google-ads/test');
                  const td = testR.data || {};
                  if (td.raw_snippet) {
                    let hint = '';
                    const raw = td.raw_snippet.toLowerCase();
                    if (raw.includes('customer not found') || raw.includes('not found')) hint = 'Customer ID er ikke tilgængeligt via dette Manager Account.';
                    else if (raw.includes('permission') || raw.includes('denied')) hint = 'Adgang nægtet — tjek Developer Token og tilladelser.';
                    else if (raw.includes('invalid customer')) hint = 'Ugyldigt Customer ID — tjek at nummeret er korrekt.';
                    else hint = `Google svarede: <code style="font-size:11px;color:#aaa">${td.raw_snippet.substring(0,200)}</code>`;
                    errMsg = `HTTP 404 — ${hint} ${settingsLink}`;
                  } else {
                    errMsg += ` — ${settingsLink} og tjek Customer ID og Manager Account.`;
                  }
                } catch(te) {
                  errMsg += ` — ${settingsLink} og tjek at Customer ID og Manager Account er korrekte.`;
                }
              }
              hBar.style.display='flex';
              hBar.className='rzpa-health health-bad';
              hBar.innerHTML=`<span class="h-icon">🔴</span><div class="h-text">${errMsg}</div>`;
              showGadsDiagPanel();
            }
          } else {
            btn.textContent = `✓ ${r.data?.count||0} kampagner hentet`;
          }
          setTimeout(() => { btn.disabled = false; btn.textContent = '⟳ Hent data'; }, 3000);
        }
        return true;
      } catch(e) {
        if (btn) { btn.disabled = false; btn.textContent = '⟳ Hent data'; }
        return false;
      }
    }

    async function maybeSync(d) {
      const check = await api(`/google-ads/has-data?days=${d}`);
      if (!check.data?.has_data) return await syncGads(d);
      return true;
    }

    async function loadGads(d) {
      const [sum, camps] = await Promise.all([
        api(`/google-ads/summary?days=${d}`),
        api(`/google-ads/campaigns?days=${d}`),
      ]);
      const s = sum.data || {};
      gadsAllData = camps.data || [];

      const spend  = parseFloat(s.total_spend) || 0;
      const clicks = parseInt(s.total_clicks) || 0;
      const impr   = parseInt(s.total_impressions) || 0;
      const ctr    = parseFloat(s.avg_ctr) || (impr > 0 ? Math.round(clicks/impr*10000)/100 : 0);
      const cpc    = parseFloat(s.avg_cpc) || (clicks > 0 ? Math.round(spend/clicks*100)/100 : 0);
      const perDay = d > 0 ? Math.round(spend / d) : 0;

      renderKPI('gads_kpi_spend',  spend  > 0 ? fmt(spend,0)  + ' kr' : '–', perDay > 0 ? '≈ ' + fmt(perDay,0) + ' kr/dag' : '');
      renderKPI('gads_kpi_impr',   fmt(impr));
      renderKPI('gads_kpi_clicks', fmt(clicks), cpc > 0 ? fmt(cpc,2) + ' kr per klik' : '');
      renderKPI('gads_kpi_ctr',    ctr > 0 ? fmt(ctr,2) + '%' : '–');

      const periodLabel = el('gads-period-label');
      const periodDays  = el('gads-period-days');
      if (periodLabel && spend > 0) { periodLabel.style.display='block'; if(periodDays) periodDays.textContent=d; }

      // Health bar
      const hBar = el('gads-health-bar');
      if (hBar && spend > 0) {
        if (ctr >= 2) {
          hBar.className='rzpa-health health-good';
          hBar.innerHTML=`<span class="h-icon">🟢</span><div class="h-text"><strong>Godt klaret!</strong> CTR på ${fmt(ctr,2)}% er over benchmark på 2%.<div class="h-sub">Søgeannoncerne fanger folks interesse.</div></div>`;
        } else if (ctr >= 0.5) {
          hBar.className='rzpa-health health-warn';
          hBar.innerHTML=`<span class="h-icon">🟡</span><div class="h-text"><strong>Annoncerne virker, men kan forbedres.</strong> CTR på ${fmt(ctr,2)}% (mål: 2%+).<div class="h-sub">Test nye annoncetekster og søgeord.</div></div>`;
        } else if (ctr > 0) {
          hBar.className='rzpa-health health-bad';
          hBar.innerHTML=`<span class="h-icon">🔴</span><div class="h-text"><strong>Lav CTR — annoncerne bør optimeres.</strong> ${fmt(ctr,2)}% klikker videre (mål: 2%+).<div class="h-sub">Gennemgå søgeord, annoncer og negative søgeord.</div></div>`;
        }
        hBar.style.display='flex';
      }

      // CTR badge
      const ctrSub = el('gads_kpi_ctr_sub');
      if (ctrSub) {
        if (ctr >= 2)   ctrSub.innerHTML='<span class="rzpa-pill good">✓ Over benchmark</span>';
        else if (ctr >= 0.5) ctrSub.innerHTML='<span class="rzpa-pill warn">⚠ Under mål (2%)</span>';
        else if (ctr > 0)    ctrSub.innerHTML='<span class="rzpa-pill bad">✗ For lavt</span>';
      }

      // Story
      const storyEl = el('gads-story');
      if (storyEl && spend > 0) {
        const ctrLabel = ctr>=2 ? 'rigtig godt — annoncerne rammer de rigtige søgere 🚀'
                       : ctr>=0.5 ? 'okay, men der er plads til forbedring 💡'
                       : ctr>0 ? 'lav — prøv nye annoncetekster og søgeord ⚠️' : '–';
        storyEl.innerHTML = `I perioden brugte I <strong>${fmt(spend,0)} kr</strong> på Google Ads. `
          + `Annoncerne dukkede op <strong>${fmt(impr)} gange</strong>, og <strong>${fmt(clicks)} personer</strong> klikkede videre. `
          + (cpc>0 ? `Det svarer til <strong>${fmt(cpc,2)} kr per klik</strong>. ` : '')
          + (ctr>0 ? `Klikprocenten er <strong>${fmt(ctr,2)}%</strong> — det er <strong>${ctrLabel}</strong>.` : '');
        storyEl.classList.remove('hidden');
      }

      // Performance summary
      const perfSum = el('gads-perf-summary');
      if (perfSum && gadsAllData.length) {
        const good = gadsAllData.filter(c => (parseFloat(c.ctr)||0) >= 2).length;
        const mid  = gadsAllData.filter(c => { const v=parseFloat(c.ctr)||0; return v>=0.5&&v<2; }).length;
        const bad  = gadsAllData.filter(c => (parseFloat(c.ctr)||0) < 0.5).length;
        setText('gads_perf_good', good); setText('gads_perf_mid', mid); setText('gads_perf_bad', bad);
        perfSum.style.display = 'flex';
      }

      // Grafer
      const top6   = [...gadsAllData].sort((a,b)=>b.spend-a.spend).slice(0,6);
      const labels = top6.map(c => c.campaign_name.slice(0,22));
      barChart('chart_gads_spend', labels,
        [{data:top6.map(c=>Math.round(c.spend||0)),backgroundColor:'#4285F4',borderRadius:5}],
        {yTick: v => Math.round(v/1000)+'k kr'});
      barChart('chart_gads_ctr', labels,
        [{data:top6.map(c=>parseFloat(c.ctr||0)),
          backgroundColor:top6.map(c=>(parseFloat(c.ctr)||0)>=2?'#CCFF00':(parseFloat(c.ctr)||0)>=0.5?'#f5a623':'#cc4400'),
          borderRadius:5}],
        {yTick: v => v.toFixed(1)+'%'});

      renderGadsTable(gadsAllData);
    }

    function renderGadsTable(data) {
      const tbody = el('gads_tbody');
      if (!tbody) return;
      let filtered = data;
      if (gadsFilter === 'ACTIVE')  filtered = data.filter(c => c.status === 'ACTIVE');
      else if (gadsFilter === 'PAUSED') filtered = data.filter(c => c.status === 'PAUSED');
      else if (gadsFilter === 'good') filtered = data.filter(c => (parseFloat(c.ctr)||0) >= 2);
      else if (gadsFilter === 'mid')  filtered = data.filter(c => { const v=parseFloat(c.ctr)||0; return v>=0.5&&v<2; });
      else if (gadsFilter === 'bad')  filtered = data.filter(c => (parseFloat(c.ctr)||0) < 0.5);
      filtered = [...filtered].sort((a,b) => {
        const av = parseFloat(a[gadsSortKey])||0, bv = parseFloat(b[gadsSortKey])||0;
        return gadsSortDir === 'desc' ? bv-av : av-bv;
      });
      if (!filtered.length) { tbody.innerHTML='<tr><td colspan="9" class="rzpa-empty">Ingen kampagner matcher filteret.</td></tr>'; return; }
      tbody.innerHTML = filtered.map(c => {
        const ctr = parseFloat(c.ctr)||0;
        return `<tr>
          <td style="color:#ddd;font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis" title="${c.campaign_name}">${c.campaign_name}</td>
          <td>${badgeHtml(c.status)}</td>
          <td>${fmt(c.spend,0)} kr</td>
          <td>${fmt(c.impressions)}</td>
          <td>${fmt(c.clicks)}</td>
          <td style="font-weight:600">${fmt(ctr,2)}%</td>
          <td>${fmt(c.cpc,2)} kr</td>
          <td>${fmt(c.conversions||0,1)}</td>
          <td><span class="perf-badge ${perfClassGads(ctr)}">${perfLabelGads(ctr)}</span></td>
        </tr>`;
      }).join('');
    }

    async function loadGadsMonthly(months) {
      months = months || 6;
      try {
        sessionStorage.removeItem('rzpa||/google-ads/monthly?months=' + months);
        const r = await api('/google-ads/monthly?months=' + months);
        const data = r.data || [];
        const card = el('gads-monthly-card');
        if (!data.length) { if(card) card.style.display='none'; return; }
        if (card) card.style.display='block';
        const mNames = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
        const labels = data.map(d => { const [y,m]=d.month.split('-'); return mNames[parseInt(m,10)-1]+' '+y.slice(2); });
        const canvas = el('chart_gads_monthly');
        if (!canvas) return;
        if (canvas._chart) canvas._chart.destroy();
        canvas._chart = new Chart(canvas, {
          type: 'bar',
          data: { labels, datasets: [
            { label:'Forbrug (kr)', data:data.map(d=>Math.round(d.spend||0)), backgroundColor:'rgba(66,133,244,0.75)', borderRadius:6, yAxisID:'y', order:2 },
            { label:'Klik', data:data.map(d=>d.clicks||0), type:'line', borderColor:'#CCFF00', backgroundColor:'rgba(204,255,0,0.08)', tension:0.3, pointRadius:4, pointBackgroundColor:'#CCFF00', fill:true, yAxisID:'y2', order:1 },
          ]},
          options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
            plugins: { legend:{display:true,labels:{color:'#888',font:{size:11},boxWidth:12,padding:16}},
              tooltip:{callbacks:{label:ctx=>ctx.dataset.yAxisID==='y'?' Forbrug: '+ctx.parsed.y.toLocaleString('da-DK')+' kr':' Klik: '+ctx.parsed.y.toLocaleString('da-DK')}}},
            scales: {
              x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#666',font:{size:10}}},
              y:{position:'left',grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#666',font:{size:10},callback:v=>v>=1000?(v/1000).toFixed(0)+'k kr':v+' kr'}},
              y2:{position:'right',grid:{drawOnChartArea:false},ticks:{color:'#CCFF00',font:{size:10},callback:v=>v.toLocaleString('da-DK')},min:0},
            }
          }
        });
      } catch(e) {}
    }

    // Init
    initDateFilter('rzpa-date-filter', async d => {
      days = d;
      const tbody = el('gads_tbody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="rzpa-loading">Henter data for ' + d + ' dage fra Google Ads…</td></tr>';
      await syncGads(d);
      loadGads(d);
    });
    await maybeSync(days);
    loadGads(days);
    loadGadsMonthly(6);

    el('gads-months-filter')?.addEventListener('click', e => {
      const btn = e.target.closest('[data-months]');
      if (!btn) return;
      el('gads-months-filter').querySelectorAll('[data-months]').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      loadGadsMonthly(parseInt(btn.dataset.months,10));
    });

    el('rzpa-sync-gads')?.addEventListener('click', async () => {
      await syncGads(days);
      loadGads(days);
      loadGadsMonthly(6);
    });

    // ── Google Ads diagnostik ────────────────────────────────────────────
    el('gads-test-btn')?.addEventListener('click', async () => {
      const btn     = el('gads-test-btn');
      const content = el('gads-diag-content');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Tester…'; }

      let td = {};
      try {
        const r = await api('/google-ads/test');
        td = r?.data || {};
      } catch(e) {
        if (content) content.innerHTML = `<span style="color:#ef4444">Forbindelsesfejl: ${e.message}</span>`;
        if (btn) { btn.disabled = false; btn.textContent = '🧪 Test forbindelse'; }
        return;
      }
      if (btn) { btn.disabled = false; btn.textContent = '🔄 Test igen'; }

      const ok  = td.http_code === 200;
      const col = ok ? '#4ade80' : '#ef4444';
      const settingsUrl = (RZPA?.settingsUrl || 'admin.php?page=rzpa-settings') + '#google-ads';

      // ── Network + token check ─────────────────────────────────────────────
      const pingOk    = td.ping_ok === true;
      const ping2Ok   = td.ping2_ok === true;
      const tokenOk   = td.token_http === 200;
      const scopeOk   = td.token_scope ? td.token_scope.includes('adwords') : null;

      let infraHtml = `
        <div style="display:grid;grid-template-columns:repeat(2,1fr) repeat(2,1fr);gap:8px;margin-bottom:12px">
          <div style="background:#111;border:1px solid ${pingOk?'#2a4a2a':'#4a2a2a'};border-radius:8px;padding:8px 12px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:2px">Netværk → googleapis.com</div>
            <div style="font-size:12px;color:${pingOk?'#4ade80':'#ef4444'}">${pingOk ? '✅ OK' : '❌ Blokeret' + (td.ping_err ? ': ' + td.ping_err : '')}</div>
          </div>
          <div style="background:#111;border:1px solid ${ping2Ok?'#2a4a2a':'#4a2a2a'};border-radius:8px;padding:8px 12px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:2px">Netværk → googleads API ${td.ping2_http ? '(HTTP '+td.ping2_http+')' : ''}</div>
            <div style="font-size:12px;color:${ping2Ok?'#4ade80':'#ef4444'}">${ping2Ok ? '✅ OK' : '❌ BLOKERET' + (td.ping2_err ? ': ' + td.ping2_err : '')}</div>
          </div>
          <div style="background:#111;border:1px solid ${tokenOk?'#2a4a2a':'#4a2a2a'};border-radius:8px;padding:8px 12px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:2px">OAuth Token (HTTP ${td.token_http ?? '–'})</div>
            <div style="font-size:12px;color:${tokenOk?'#4ade80':'#ef4444'}">${tokenOk ? '✅ Token OK' : '❌ Token fejl'}</div>
          </div>
          <div style="background:#111;border:1px solid ${scopeOk===true?'#2a4a2a':scopeOk===false?'#4a2a2a':'#2a2a2a'};border-radius:8px;padding:8px 12px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:2px">Scope: adwords</div>
            <div style="font-size:12px;color:${scopeOk===true?'#4ade80':scopeOk===false?'#ef4444':'#888'}">${scopeOk===true ? '✅ Inkluderet' : scopeOk===false ? '❌ MANGLER' : '– ukendt'}</div>
          </div>
        </div>
        ${!ping2Ok ? `<div style="background:#2d0a0a;border:1px solid #f8717140;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:#f87171">🚨 <strong>Din server kan IKKE nå googleads.googleapis.com</strong> — det er sandsynligvis årsagen til alle 404-fejlene. Kontakt din hostingudbyder og bed dem whiteliste udgående forbindelser til <code>googleads.googleapis.com</code>.</div>` : ''}
        ${td.client_id_hint ? `<div style="font-size:11px;color:#555;margin-bottom:10px">OAuth Client ID: <code style="color:#777">${td.client_id_hint}</code></div>` : ''}`;

      const directAccounts = td.accessible_accounts || [];
      const mccAccounts    = td.accessible_via_mcc  || [];
      const allAccounts    = [...new Set([...directAccounts, ...mccAccounts])];

      let accessHtml = '';
      if (allAccounts.length) {
        accessHtml = `
          <div style="margin-top:12px">
            <div style="font-size:11px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Tilgængelige konti</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">
              ${allAccounts.map(a => {
                const isTarget = a === td.customer_id;
                const tag = mccAccounts.includes(a) ? ' (MCC)' : ' (direkte)';
                return `<span style="background:${isTarget?'#4ade8020':'#1a1a1a'};border:1px solid ${isTarget?'#4ade80':'#333'};border-radius:6px;padding:3px 10px;font-family:monospace;font-size:12px;color:${isTarget?'#4ade80':'#888'}">${a}${isTarget?' ✓':tag}</span>`;
              }).join('')}
            </div>
            ${!td.cid_in_direct && !td.cid_in_mcc ? `<div style="color:#ef4444;font-size:12px">🚨 <strong>${td.customer_id}</strong> er IKKE i nogen af listerne — Customer ID er muligvis forkert.</div>` : ''}
            ${td.cid_in_mcc ? `<div style="color:#4ade80;font-size:12px">✅ ${td.customer_id} er tilgængeligt via MCC (${td.manager_id}).</div>` : ''}
          </div>`;
      } else if (tokenOk) {
        const listErrMsg = td.list_raw_snippet ? ` — Google svarede: <code style="font-size:10px;color:#aaa">${td.list_raw_snippet.substring(0,200)}</code>` : '';
        accessHtml = `<div style="margin-top:10px;color:#f59e0b;font-size:12px">⚠️ Ingen konti returneret fra Google (listAccessibleCustomers HTTP ${td.list_http ?? '–'})${listErrMsg}</div>
          <div style="margin-top:6px;font-size:12px;color:#888">Mulig årsag: Developer Token er ikke godkendt, eller OAuth-client tilhører et andet Google Cloud-projekt end der hvor Google Ads API er aktiveret.</div>`;
      }

      let statusHtml = ok
        ? `<span style="color:#4ade80;font-weight:600">✅ Forbindelsen virker! ${td.campaigns_found} kampagner fundet${!td.used_mcc ? ' (direkte adgang uden MCC)' : ''}.</span>`
        : `<span style="color:#ef4444;font-weight:600">❌ Fejl: ${td.error || 'Ukendt fejl'}</span>
           <div style="margin-top:6px;font-size:12px;color:#888">Med MCC: HTTP ${td.http_code} · Uden MCC: HTTP ${td.http_no_mcc ?? '–'} · Stream: HTTP ${td.http_stream ?? '–'} · ListCustomers: HTTP ${td.list_http ?? '–'}</div>
           <div style="margin-top:10px"><a href="${settingsUrl}" style="color:#CCFF00;font-size:12px">⚙️ Gå til Google Ads Indstillinger →</a></div>`;

      if (content) content.innerHTML = `
        ${infraHtml}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px">
          <div style="background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 14px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:3px">Customer ID</div>
            <div style="font-family:monospace;color:#ccc">${td.customer_id || '–'}</div>
          </div>
          <div style="background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 14px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:3px">Manager (MCC)</div>
            <div style="font-family:monospace;color:#ccc">${td.manager_id || 'Ikke sat'}</div>
          </div>
          <div style="background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 14px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;margin-bottom:3px">API Version</div>
            <div style="font-family:monospace;color:#ccc">${td.api_version || '–'}</div>
          </div>
        </div>
        <div>${statusHtml}</div>
        ${accessHtml}
        ${td.raw_snippet && !ok ? `<details style="margin-top:10px"><summary style="color:#555;font-size:11px;cursor:pointer">Vis råt Google svar</summary><pre style="background:#0a0a0a;border:1px solid #222;border-radius:6px;padding:10px;font-size:10px;color:#666;overflow:auto;margin-top:6px;white-space:pre-wrap">${td.raw_snippet}</pre></details>` : ''}`;
    });

    // Vis diagnostik-panelet automatisk ved fejl
    function showGadsDiagPanel() {
      const panel = el('gads-diag-panel');
      if (panel) panel.style.display = 'block';
    }

    el('gads-filter-bar')?.addEventListener('click', e => {
      const btn = e.target.closest('[data-filter]');
      if (!btn) return;
      gadsFilter = btn.dataset.filter;
      el('gads-filter-bar').querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      renderGadsTable(gadsAllData);
    });

    el('gads-table')?.querySelector('thead')?.addEventListener('click', e => {
      const th = e.target.closest('[data-sort]');
      if (!th) return;
      const key = th.dataset.sort;
      gadsSortDir = (gadsSortKey===key && gadsSortDir==='desc') ? 'asc' : 'desc';
      gadsSortKey = key;
      renderGadsTable(gadsAllData);
    });

    // AI analyse
    el('gads-ai-refresh')?.addEventListener('click', async () => {
      const btn = el('gads-ai-refresh'), content = el('gads-ai-content');
      if (btn) { btn.disabled=true; btn.textContent='⏳ Analyserer…'; }
      if (content) content.innerHTML='<div style="color:#555;padding:16px 0">🤖 Sender kampagnedata til AI — tager 15-30 sekunder…</div>';
      const res = await api('/google-ads/ai-analysis?days='+days, {method:'POST'});
      if (btn) { btn.disabled=false; btn.textContent='✨ Analysér nu'; }
      if (!content) return;
      const d = res?.data || {};
      if (d.error) { const e=typeof d.error==='string'?d.error:JSON.stringify(d.error); content.innerHTML=`<span style="color:#ff6b6b">⚠️ ${e}</span>`; return; }
      if (d.analysis) {
        const sectionColors={'1':'#60a5fa','2':'#CCFF00','3':'#4285F4','4':'#f59e0b','5':'#a78bfa'};
        const sectionIcons ={'1':'📊','2':'🎯','3':'📋','4':'🔍','5':'⚙️'};
        function mdToHtmlG(txt) {
          if(!txt) return '';
          return txt.replace(/\*\*([^*]+)\*\*/g,'<strong style="color:#ddd">$1</strong>').replace(/\*([^*]+)\*/g,'<em style="color:#bbb">$1</em>').replace(/^[-–]\s+(.+)$/gm,'<li>$1</li>').replace(/(<li>[\s\S]*?<\/li>)/g,'<ul style="margin:8px 0 8px 16px;padding:0;list-style:none">$1</ul>').replace(/<\/ul>\s*<ul[^>]*>/g,'').replace(/\n{2,}/g,'</p><p style="margin:8px 0">').replace(/\n/g,' ');
        }
        const raw = d.analysis.replace(/^#+\s*/gm,'');
        const parts = raw.split(/(?=\b[1-5]\.\s+[A-ZÆØÅ]{3,})/);
        content.innerHTML = parts.map(section => {
          section = section.trim(); if(!section) return '';
          const m = section.match(/^([1-5])\.\s+([^\n.]+)[.\n]?([\s\S]*)/);
          if(!m) return `<p style="color:#888;font-size:12px;margin:8px 0">${mdToHtmlG(section)}</p>`;
          const [,num,titleRaw,bodyRaw]=m;
          const color=sectionColors[num]||'#888', icon=sectionIcons[num]||'•';
          const title=titleRaw.replace(/\*\*/g,'').trim(), body=mdToHtmlG(bodyRaw.trim());
          return `<div style="margin-bottom:14px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,0.06)">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:rgba(255,255,255,0.04);border-bottom:1px solid rgba(255,255,255,0.05)">
              <span style="font-size:18px">${icon}</span>
              <div><span style="font-size:10px;font-weight:700;letter-spacing:1px;color:${color};text-transform:uppercase">${num} / 5</span>
              <div style="font-size:13px;font-weight:700;color:#fff;margin-top:1px">${title}</div></div>
            </div>
            <div style="padding:14px 16px;font-size:12px;color:#aaa;line-height:1.85"><p style="margin:0">${body}</p></div>
          </div>`;
        }).filter(Boolean).join('');
        if (d.cached) content.insertAdjacentHTML('beforeend','<p style="font-size:11px;color:#444;margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.05)">📋 Cachet analyse — klik igen for frisk analyse</p>');
      }
    });

    // Fakturaer
    el('gads-invoices-load')?.addEventListener('click', async () => {
      const btn=el('gads-invoices-load'), content=el('gads-invoices-content'), csvBtn=el('gads-invoices-csv');
      if(btn){btn.disabled=true;btn.textContent='⏳ Henter…';}
      const res = await api('/google-ads/invoices');
      if(btn){btn.disabled=false;btn.textContent='⬇ Hent betalinger';}
      if(!content) return;
      const data = res?.data||[];
      if(data.error){content.innerHTML=`<span style="color:#ff6b6b">⚠️ ${typeof data.error==='string'?data.error:JSON.stringify(data.error)}</span>`;return;}
      if(!data.length){content.innerHTML='<span style="color:#555">Ingen betalingsdata fundet.</span>';return;}
      window._gadsInvoiceData = data;
      if(csvBtn) csvBtn.style.display='inline-flex';
      const pdfBtnGads = el('gads-invoices-pdf');
      if(pdfBtnGads) pdfBtnGads.style.display='inline-flex';
      renderInvoiceTable(content, data, 'Google Ads', '#4285F4');
    });

    el('gads-invoices-csv')?.addEventListener('click', () => {
      const data = window._gadsInvoiceData||[];
      if(!data.length) return;
      const csv = ['Måned,Forbrug,Valuta',...data.map(r=>`${r.month},${r.amount},${r.currency}`)].join('\n');
      const a=document.createElement('a');
      a.href=URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'}));
      a.download='google-ads-betalinger-'+new Date().toISOString().slice(0,10)+'.csv';
      a.click();
    });

    initGadsInvoicePDF();
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
      if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="rzpa-loading">Henter data for ' + d + ' dage fra Meta…</td></tr>';
      await syncMeta(d);
      loadMeta(d);
      loadTopAds(d);
      loadLandingPages(d);
    });

    // Initial load
    await maybeSync(days);
    loadMeta(days);
    loadTopAds(days);
    loadLandingPages(days);
    loadMonthlyChart(6);

    // Måneder-filter til performance-over-tid grafen
    el('meta-months-filter')?.addEventListener('click', e => {
      const btn = e.target.closest('[data-months]');
      if (!btn) return;
      el('meta-months-filter').querySelectorAll('[data-months]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      loadMonthlyChart(parseInt(btn.dataset.months, 10));
    });

    // Meta AI-specialist knap
    el('meta-ai-refresh')?.addEventListener('click', async () => {
      const btn     = el('meta-ai-refresh');
      const content = el('meta-ai-content');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Analyserer…'; }
      if (content) content.innerHTML = '<div style="color:#555;padding:16px 0">🤖 Sender kampagnedata til AI — tager 15-30 sekunder…</div>';
      const res = await api('/meta/ai-analysis?days=' + days, { method: 'POST' });
      if (btn) { btn.disabled = false; btn.textContent = '✨ Analysér nu'; }
      if (!content) return;
      const d = res?.data || {};
      if (d.error) {
        const errTxt = typeof d.error === 'string' ? d.error : JSON.stringify(d.error);
        content.innerHTML = `<span style="color:#ff6b6b">⚠️ ${errTxt}</span>`;
        return;
      }
      if (d.analysis) {
        const sectionMeta = {
          '1': { color: '#60a5fa', icon: '📊', label: 'OVERORDNET VURDERING' },
          '2': { color: '#CCFF00', icon: '🎯', label: 'TOP PRIORITET NU' },
          '3': { color: '#1877F2', icon: '📋', label: 'KAMPAGNE-ANBEFALINGER' },
          '4': { color: '#f59e0b', icon: '🎨', label: 'CONTENT & KREATIVT' },
          '5': { color: '#a78bfa', icon: '⚙️', label: 'TEKNISK OPTIMERING' },
        };

        // Konvertér markdown til HTML
        function mdToHtml(txt) {
          if (!txt) return '';
          return txt
            .replace(/\*\*([^*]+)\*\*/g, '<strong style="color:#ddd">$1</strong>')
            .replace(/\*([^*]+)\*/g,     '<em style="color:#bbb">$1</em>')
            .replace(/^[-–]\s+(.+)$/gm,  '<li>$1</li>')
            .replace(/(<li>[\s\S]*?<\/li>)/g, '<ul style="margin:8px 0 8px 16px;padding:0;list-style:none">$1</ul>')
            .replace(/<\/ul>\s*<ul[^>]*>/g, '')
            .replace(/\n{2,}/g, '</p><p style="margin:8px 0">')
            .replace(/\n/g, ' ');
        }

        // Split på sektioner: "# 1." eller "1." eller "**1." i starten af linje
        const raw = d.analysis.replace(/^#+\s*/gm, ''); // fjern # præfikser
        const parts = raw.split(/(?=\b[1-5]\.\s+[A-ZÆØÅ]{3,})/);

        const cards = parts.map(section => {
          section = section.trim();
          if (!section) return '';
          const m = section.match(/^([1-5])\.\s+([^\n.]+)[.\n]?([\s\S]*)/);
          if (!m) return `<p style="color:#888;font-size:12px;margin:8px 0">${mdToHtml(section)}</p>`;
          const [, num, titleRaw, bodyRaw] = m;
          const meta  = sectionMeta[num] || { color: '#888', icon: '•', label: '' };
          const title = titleRaw.replace(/\*\*/g,'').trim();
          const body  = mdToHtml(bodyRaw.trim());
          return `
            <div style="margin-bottom:14px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,0.06)">
              <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:rgba(255,255,255,0.04);border-bottom:1px solid rgba(255,255,255,0.05)">
                <span style="font-size:18px">${meta.icon}</span>
                <div>
                  <span style="font-size:10px;font-weight:700;letter-spacing:1px;color:${meta.color};text-transform:uppercase">${num} / 5</span>
                  <div style="font-size:13px;font-weight:700;color:#fff;margin-top:1px">${title}</div>
                </div>
              </div>
              <div style="padding:14px 16px;font-size:12px;color:#aaa;line-height:1.85">
                <p style="margin:0">${body}</p>
              </div>
            </div>`;
        }).filter(Boolean).join('');

        content.innerHTML = cards || `<p style="color:#888">Ingen analyse tilgængelig — prøv igen.</p>`;
        if (d.cached) {
          content.insertAdjacentHTML('beforeend', '<p style="font-size:11px;color:#444;margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.05)">📋 Cachet analyse — klik igen for frisk analyse</p>');
        }
      }
    });

    // ── Top Annoncer ──────────────────────────────────
    // Eksponér loadTopAds globalt så inline onclick="rzpaLoadTopAds(...)" virker
    async function loadTopAds(d, forceLoad = false) {
      const card = el('meta-top-ads-card');
      const content = el('meta-top-ads-content');
      if (!card || !content) return;

      card.style.display = 'block';

      // Ryd sessionStorage så vi ikke serverer stale data
      try {
        sessionStorage.removeItem('rzpa||/meta/top-ads?days=' + d);
        sessionStorage.removeItem('rzpa||/meta/top-ads?days=' + d + '&check=1');
        sessionStorage.removeItem('rzpa||/meta/top-ads?days=' + d + '&force=1');
      } catch(e) {}

      // Uden forceLoad: tjek cache hurtigt – undgå langsom Meta API-request ved pageload
      if (!forceLoad) {
        content.innerHTML = '<div class="rzpa-loading">Tjekker annoncer…</div>';
        let checkRaw;
        try {
          const cr = await api(`/meta/top-ads?days=${d}&check=1`, { timeout: 8 });
          checkRaw = cr?.data ?? cr;
        } catch(e) {
          checkRaw = { __no_cache: true };
        }
        // Ingen cache – vis prompt i stedet for at hænge i baggrunden
        if (checkRaw?.__no_cache) {
          content.innerHTML = `<div style="text-align:center;padding:20px 0">
            <p style="color:#888;margin:0 0 10px;font-size:13px">Annoncer ikke hentet endnu — klik for at hente fra Meta.</p>
            <button onclick="rzpaLoadTopAds(${d},true)" class="btn-ghost" style="font-size:13px">📊 Hent top annoncer</button>
          </div>`;
          return;
        }
        if (checkRaw?.__error) {
          content.innerHTML = `<p style="color:#ef4444;margin:0 0 8px">⚠️ Meta API fejl: ${checkRaw.__error}</p>
            <button onclick="rzpaLoadTopAds(${d},true)" class="btn-ghost" style="font-size:12px">↻ Prøv igen</button>`;
          return;
        }
        // Cache fundet → render direkte
        try { renderTopAds(content, checkRaw, d); } catch(re) {
          content.innerHTML = `<p style="color:#ef4444;margin:0">⚠️ Render fejl: ${re.message}</p>`;
        }
        return;
      }

      // forceLoad=true – fuldt Meta API-kald
      content.innerHTML = '<div class="rzpa-loading">Henter annoncer fra Meta… (kan tage op til 20 sek.)</div>';
      let raw;
      try {
        const r = await api(`/meta/top-ads?days=${d}&force=1`, { timeout: 25 });
        raw = r?.data ?? r;
      } catch(e) {
        content.innerHTML = `<p style="color:#ef4444;margin:0 0 8px">⚠️ Timeout – Meta API svarer langsomt (${e.message}).</p>
          <button onclick="rzpaLoadTopAds(${d},true)" class="btn-ghost" style="font-size:12px">↻ Prøv igen</button>`;
        return;
      }

      // API-fejl
      if (raw && raw.__error) {
        content.innerHTML = `<p style="color:#ef4444;margin:0 0 8px">⚠️ Meta API fejl: ${raw.__error}</p>
          <button onclick="rzpaLoadTopAds(${d},true)" class="btn-ghost" style="font-size:12px">↻ Prøv igen</button>`;
        return;
      }

      const ads = Array.isArray(raw) ? raw : [];

      if (!ads.length) {
        content.innerHTML = `<div style="text-align:center;padding:24px">
          <p style="color:#888;margin:0 0 8px">Ingen annoncer fundet i de seneste ${d} dage.</p>
          <p style="color:#555;font-size:12px;margin:0 0 12px">Prøv en længere periode eller tjek at dit Meta access token er gyldigt.</p>
          <button onclick="rzpaLoadTopAds(90,true)" class="btn-ghost" style="font-size:12px">Vis 90 dages periode</button>
        </div>`;
        return;
      }

      try { renderTopAds(content, ads, d); } catch(re) {
        content.innerHTML = `<p style="color:#ef4444;margin:0">⚠️ Render fejl: ${re.message}</p>`;
      }
    }

    function renderTopAds(content, ads, d) {
      if (!Array.isArray(ads) || !ads.length) return;

      const proxyBase = (RZPA?.restBase || '/wp-json/rzpa/v1/') + 'meta/image-proxy?url=';
      const thumbSrc = ad => {
        const raw = ad.thumbnail_url || ad.image_url;
        if (!raw) return '';
        return proxyBase + encodeURIComponent(raw) + (RZPA?.nonce ? '&_wpnonce=' + RZPA.nonce : '');
      };

      // Link direkte til annoncen i Meta Ads Manager
      const adLink = ad => {
        const actId = ad.account_id || '';
        const adId  = ad.ad_id || '';
        if (!adId) return 'https://www.facebook.com/adsmanager/';
        return `https://www.facebook.com/adsmanager/manage/ads?act=${actId}&selected_ad_ids=${adId}`;
      };

      // Performance-vurdering baseret på CTR og CPM
      const perfBadge = ad => {
        const ctr = parseFloat(ad.ctr) || 0;
        const cpm = parseFloat(ad.cpm) || 0;
        const reach = parseInt(ad.reach) || 0;
        if (!ad.impressions || ad.impressions === 0) return { label: 'Ingen data', color: '#666', bg: '#66666618' };
        // CTR benchmark DK: stærk >1.5%, ok 0.5-1.5%, svag <0.5%
        if (ctr >= 1.5) return { label: '🟢 Stærk', color: '#4ade80', bg: '#4ade8018' };
        if (ctr >= 0.5) return { label: '🟡 Okay',  color: '#f59e0b', bg: '#f59e0b18' };
        return                { label: '🔴 Svag',   color: '#ef4444', bg: '#ef444418' };
      };

      const byDays  = [...ads].sort((a, b) => (b.days_active || 0) - (a.days_active || 0));
      const bySpend = [...ads].sort((a, b) => b.spend - a.spend);

      const spotlight = [
        { ad: ads[0],     icon: '👑', label: 'Højeste Reach',         metric_label: 'Reach',                metric_value: fmt(ads[0]?.reach) },
        { ad: byDays[0],  icon: '⏱', label: 'Længste Løbetid',        metric_label: 'Aktiv i',              metric_value: `${byDays[0]?.days_active ?? '–'} dage` },
        { ad: bySpend[0], icon: '📈', label: 'Højeste Annonceforbrug', metric_label: 'Est. Månedligt Spend', metric_value: `${fmt(bySpend[0]?.spend, 0)} kr.` },
      ];

      // Store ad data keyed by ad_id for modal access
      window._rzpaTopAdsMap = window._rzpaTopAdsMap || {};
      spotlight.forEach(({ ad }) => { if (ad) window._rzpaTopAdsMap[ad.ad_id] = ad; });

      let html = '<div class="tap-grid">';
      spotlight.forEach(({ ad, icon, label, metric_label, metric_value }) => {
        if (!ad) return;
        const src  = thumbSrc(ad);
        const copy = (ad.body_copy || ad.ad_name || '').substring(0, 120);
        const perf = perfBadge(ad);
        const link = adLink(ad);
        const ctrTxt = ad.impressions > 0 ? `CTR ${ad.ctr}% · CPM ${fmt(ad.cpm,0)} kr.` : '';

        const isVideo = ad.has_video || ad.format === 'video';
        const fallbackIcon = isVideo ? '🎬' : '🖼️';
        const imgHtml = src
          ? `<img src="${src}" class="tap-img" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
          : '';
        const fallbackDiv = `<div class="tap-no-thumb" style="${src ? 'display:none' : ''}">${fallbackIcon}</div>`;

        html += `
          <div class="tap-card" data-adid="${ad.ad_id}">
            <div class="tap-thumb">
              ${imgHtml}${fallbackDiv}
              <span class="tap-badge">${icon} ${label}</span>
              <a href="${link}" target="_blank" rel="noopener"
                 style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;padding:3px 7px;border-radius:4px;text-decoration:none;font-weight:500;backdrop-filter:blur(4px)"
                 title="Åbn i Meta Ads Manager" onclick="event.stopPropagation()">↗ Meta</a>
            </div>
            <div class="tap-body">
              <p class="tap-copy">${copy || ad.ad_name || '–'}</p>
              <div style="display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap">
                <span style="display:inline-flex;align-items:center;background:${perf.bg};color:${perf.color};border-radius:20px;padding:2px 8px;font-size:11px;font-weight:600">${perf.label}</span>
                ${ctrTxt ? `<span style="font-size:11px;color:#666">${ctrTxt}</span>` : ''}
              </div>
            </div>
            <div class="tap-foot">
              <span class="tap-foot-label">${metric_label}</span>
              <strong class="tap-foot-val">${metric_value}</strong>
            </div>
          </div>`;
      });
      html += '</div>';
      content.innerHTML = html;

      // Klik på tap-card åbner detail modal
      content.querySelectorAll('.tap-card[data-adid]').forEach(card => {
        card.addEventListener('click', () => {
          const ad = window._rzpaTopAdsMap?.[card.dataset.adid];
          if (ad) openTopAdDetailModal(ad);
        });
      });
    }

    // Eksponér globalt så inline onclick-knapper virker fra genereret HTML
    window.rzpaLoadTopAds = (d, force) => loadTopAds(d, !!force);
    el('meta-top-ads-load')?.addEventListener('click', () => loadTopAds(days, true));

    // ── Landing Pages ──────────────────────────────────
    async function loadLandingPages(d) {
      const card = el('meta-landing-pages-card');
      const content = el('meta-landing-pages-content');
      if (!card || !content) return;

      const r = await api(`/meta/landing-pages?days=${d}`);
      const pages = r.data || [];
      if (!pages.length) return;

      card.style.display = '';
      const activeCount = pages.filter(p => p.active_ads > 0).length;
      content.innerHTML = `<div style="font-size:12px;color:#666;margin-bottom:12px">${pages.length} unikke landingssider · ${activeCount} med aktive annoncer</div>
        <div class="rzpa-landing-pages">
          ${pages.map(p => `<div class="rzpa-landing-page-row">
            <div class="lp-info">
              <div class="lp-domain">${p.domain}</div>
              <div class="lp-url">${p.url}</div>
            </div>
            <div class="lp-count">${p.active_ads > 0 ? `<span style="color:#4ade80">● Aktiv</span> · ` : ''}${p.ad_count} ann.</div>
            <a href="${p.url}" target="_blank" class="lp-link">Åbn →</a>
          </div>`).join('')}
        </div>`;
    }

    // ── Fakturaer / Transaktioner ─────────────────────────────────────────
    async function loadMetaInvoices(force = false) {
      const btn     = el('meta-invoices-load');
      const content = el('meta-invoices-content');
      const since   = el('meta-inv-since')?.value || '';
      const until   = el('meta-inv-until')?.value || '';
      if (btn) { btn.disabled = true; btn.textContent = '⏳'; }
      const params = new URLSearchParams({ since, until });
      if (force) params.set('force', '1');
      const res = await api('/meta/invoices?' + params.toString());
      if (btn) { btn.disabled = false; btn.textContent = '⟳ Hent'; }
      if (!content) return;
      const data = res?.data || [];
      if (data.error) {
        content.innerHTML = `<div style="color:#ef4444;padding:16px">⚠️ ${data.error}</div>`;
        return;
      }
      if (!Array.isArray(data) || !data.length) {
        content.innerHTML = '<div style="color:#555;padding:16px;text-align:center">Ingen transaktioner fundet i den valgte periode.</div>';
        return;
      }
      window._metaInvoiceData = data;
      const csvBtn = el('meta-invoices-csv');
      const pdfBtn = el('meta-invoices-pdf');
      if (csvBtn) csvBtn.style.display = 'inline-flex';
      if (pdfBtn) pdfBtn.style.display = 'inline-flex';
      const isFallback = data[0]?._source === 'spend_fallback';
      renderMetaTransactions(content, data, isFallback);
    }

    function renderMetaTransactions(container, data, isFallback = false) {
      const total = data.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0);
      const currency = data[0]?.currency || 'DKK';
      const hasIds   = data.some(r => r.transaction_id);

      const fallbackBanner = isFallback ? `
        <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#f59e0b;display:flex;gap:8px;align-items:flex-start">
          <span style="flex-shrink:0">⚠️</span>
          <div>
            <strong>Viser månedligt annonceforbrug — ikke faktiske betalinger</strong><br>
            <span style="color:#aaa">Meta's fakturerings-API returnerede ingen data. Beløbene nedenfor er hvad du brugte på annoncer pr. måned (fra Meta Insights), <em>ikke</em> hvad der faktisk blev trukket på dit betalingskort. Faktiske betalinger sker ved faktureringstærskler og kan afvige. Se præcise opkrævninger direkte på <a href="https://business.facebook.com/billing_hub/payment_activity" target="_blank" style="color:#1877F2">Meta Betalingsaktivitet</a>.</span>
          </div>
        </div>` : '';

      const statusBadge = s => {
        const map = { SETTLED:'#4ade80', PAID:'#4ade80', COMPLETED:'#4ade80', FAILED:'#ef4444', PENDING:'#f59e0b', VOID:'#888' };
        const col = map[s?.toUpperCase()] || '#888';
        const label = s === 'SETTLED' || s === 'PAID' ? 'Betalt' : s === 'FAILED' ? 'Fejlet' : s === 'PENDING' ? 'Afventer' : s || 'Betalt';
        return `<span style="display:inline-flex;align-items:center;gap:4px;background:${col}18;color:${col};border:1px solid ${col}40;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600">${label}</span>`;
      };

      const fmtDate = d => {
        if (!d) return '–';
        const [y,m,day] = d.split('-');
        const months = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
        return `${parseInt(day,10)}. ${months[parseInt(m,10)-1]} ${y}`;
      };

      const shortTxId = id => {
        if (!id) return '–';
        const parts = id.split('-');
        if (parts.length >= 2) return parts[0].slice(-8) + '-' + parts[1].slice(-8);
        return id.slice(-16);
      };

      container.innerHTML = fallbackBanner + `
        <div style="display:flex;gap:24px;flex-wrap:wrap;padding:14px 18px;background:rgba(24,119,242,.06);border-radius:10px;border:1px solid rgba(24,119,242,.15);margin-bottom:16px">
          <div><div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px">${isFallback ? 'Månedligt forbrug' : 'Samlet forbrug'}</div>
               <div style="font-size:22px;font-weight:700;color:#fff">${fmt(total,2)} ${currency}</div></div>
          <div><div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px">${isFallback ? 'Måneder' : 'Transaktioner'}</div>
               <div style="font-size:22px;font-weight:700;color:#fff">${data.length}</div></div>
        </div>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="background:#1a1a1a">
              ${hasIds ? '<th style="padding:10px 12px;text-align:left;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #2a2a2a">Transaktions-ID</th>' : ''}
              <th style="padding:10px 12px;text-align:left;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #2a2a2a">Dato</th>
              <th style="padding:10px 12px;text-align:right;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #2a2a2a">Beløb</th>
              <th style="padding:10px 12px;text-align:left;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #2a2a2a">Status</th>
              ${hasIds ? '<th style="padding:10px 12px;text-align:left;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #2a2a2a">Momsfakturerings-ID</th>' : ''}
              <th style="padding:10px 12px;text-align:center;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #2a2a2a">Handling</th>
            </tr>
          </thead>
          <tbody>
            ${data.map(r => `
              <tr style="border-bottom:1px solid #1e1e1e" onmouseenter="this.style.background='rgba(255,255,255,.025)'" onmouseleave="this.style.background=''">
                ${hasIds ? `<td style="padding:12px;color:#aaa;font-family:monospace;font-size:11px">${shortTxId(r.transaction_id)}</td>` : ''}
                <td style="padding:12px;color:#ccc">${fmtDate(r.date)}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:#fff">${fmt(parseFloat(r.amount||0),2)} ${r.currency||currency}</td>
                <td style="padding:12px">${statusBadge(r.status)}</td>
                ${hasIds ? `<td style="padding:12px;color:#aaa;font-size:12px;font-family:monospace">${r.invoice_id || '–'}</td>` : ''}
                <td style="padding:12px;text-align:center">
                  ${r.invoice_id
                    ? `<a href="${(RZPA?.restBase||'/wp-json/rzpa/v1/')}meta/invoice-pdf?invoice_id=${encodeURIComponent(r.invoice_id)}&_wpnonce=${RZPA?.nonce||''}"
                          download="Meta-Faktura-${r.invoice_id}.pdf"
                          style="display:inline-flex;align-items:center;gap:4px;background:#1877F218;border:1px solid #1877F240;border-radius:6px;color:#1877F2;font-size:11px;font-weight:600;padding:5px 10px;text-decoration:none"
                          title="Download PDF direkte">
                         ⬇ PDF
                       </a>`
                    : `<a href="${r.download_url || '#'}" target="_blank" rel="noopener"
                          style="display:inline-flex;align-items:center;gap:4px;background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#888;font-size:11px;font-weight:500;padding:5px 10px;text-decoration:none"
                          title="Åbn i Meta">
                         ↗ Meta
                       </a>`
                  }
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
        </div>`;
    }

    el('meta-invoices-load')?.addEventListener('click', () => loadMetaInvoices(true));
    el('meta-inv-since')?.addEventListener('change', () => loadMetaInvoices());
    el('meta-inv-until')?.addEventListener('change', () => loadMetaInvoices());

    // Auto-load ved sideindlæsning
    if (el('meta-invoices-card')) loadMetaInvoices();

    el('meta-invoices-csv')?.addEventListener('click', () => {
      const data = window._metaInvoiceData || [];
      if (!data.length) return;
      const header = 'Dato,Beløb,Valuta,Status,Faktura-ID,Transaktions-ID';
      const rows = data.map(r => `"${r.date}","${r.amount}","${r.currency}","${r.status}","${r.invoice_id||''}","${r.transaction_id||''}"`);
      const csv = [header, ...rows].join('\n');
      const blob = new Blob(['\uFEFF'+csv], { type: 'text/csv;charset=utf-8;' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'meta-transaktioner-' + new Date().toISOString().slice(0,10) + '.csv';
      a.click();
    });

    initMetaInvoicePDF();

    el('rzpa-sync-meta')?.addEventListener('click', async () => {
      if (await syncMeta(days)) {
        loadMeta(days);
        loadMonthlyChart(6);
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

    // Vis periode-label
    const periodLabel = el('meta-period-label');
    const periodDays = el('meta-period-days');
    if (periodLabel && spend > 0) { periodLabel.style.display = 'block'; if (periodDays) periodDays.textContent = days; }

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
  // CREATIVE INTELLIGENCE — Shared scoring utilities
  // ════════════════════════════════════════════════════

  /**
   * Compute superads.ai-inspired performance scores (0–100) from available metrics.
   * hookScore   = how well the ad captures attention (CTR-based)
   * holdScore   = how long people engage (video_views / impressions for video)
   * clickScore  = click efficiency (CTR + CPC inverse)
   * engScore    = social engagement depth
   * convScore   = conversion downstream performance
   * overall     = weighted composite
   */
  function computeCreativeScores(ad) {
    const ctr   = parseFloat(ad.ctr)             || 0;  // %
    const cpc   = parseFloat(ad.cpc || ad.cost_per_view) || 99;
    const eng   = parseFloat(ad.engagement_rate) || 0;  // %
    const roas  = parseFloat(ad.roas)            || 0;
    const impr  = parseInt(ad.impressions)       || 1;
    const views = parseInt(ad.video_views || ad.swipe_ups || 0);
    const conv  = parseInt(ad.conversions)       || 0;
    const clicks= parseInt(ad.clicks)            || 1;

    // Hook Score (0-100) — CTR benchmarks: 0.5%=20, 1.5%=60, 3%=100
    const hookScore = Math.min(100, Math.round(Math.sqrt(Math.min(ctr / 3, 1)) * 100));

    // Hold Score (0-100) — view rate (video views / impressions)
    const viewRate  = Math.min(1, views / impr);
    const holdScore = Math.min(100, Math.round(Math.sqrt(viewRate / 0.6) * 100));

    // Click Score (0-100) — blend of CTR and CPC efficiency
    const cpcScore  = Math.min(100, Math.round(100 * Math.exp(-cpc / 15)));
    const clickScore= Math.min(100, Math.round(hookScore * 0.55 + cpcScore * 0.45));

    // Engagement Score (0-100) — engagement rate (snap/meta) or ROAS proxy
    const engScore  = roas > 0
      ? Math.min(100, Math.round(Math.sqrt(Math.min(roas / 4, 1)) * 100))
      : Math.min(100, Math.round(Math.sqrt(Math.min(eng / 5, 1)) * 100));

    // Conversion Score (0-100) — conv rate
    const convRate  = conv > 0 ? (conv / clicks * 100) : 0;
    const convScore = Math.min(100, Math.round(Math.sqrt(Math.min(convRate / 5, 1)) * 100));

    // Overall — weighted
    const overall = Math.round(
      hookScore  * 0.28 +
      holdScore  * 0.18 +
      clickScore * 0.24 +
      engScore   * 0.18 +
      convScore  * 0.12
    );

    return { hookScore, holdScore, clickScore, engScore, convScore, overall };
  }

  function scoreColor(s) {
    return s >= 70 ? '#4ade80' : s >= 40 ? '#f59e0b' : '#ef4444';
  }

  function scoreLabel(s) {
    return s >= 70 ? 'Stærk' : s >= 40 ? 'Ok' : 'Svag';
  }

  /** Small circular SVG score ring + number */
  function scoreRing(score, label, sz = 52) {
    const c    = scoreColor(score);
    const r    = sz / 2 - 4;
    const circ = 2 * Math.PI * r;
    const dash = Math.round((score / 100) * circ * 10) / 10;
    return `<div style="text-align:center;flex-shrink:0">
      <div style="position:relative;width:${sz}px;height:${sz}px">
        <svg width="${sz}" height="${sz}" viewBox="0 0 ${sz} ${sz}" style="transform:rotate(-90deg)">
          <circle cx="${sz/2}" cy="${sz/2}" r="${r}" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="3.5"/>
          <circle cx="${sz/2}" cy="${sz/2}" r="${r}" fill="none" stroke="${c}" stroke-width="3.5"
                  stroke-dasharray="${dash} ${circ}" stroke-linecap="round"/>
        </svg>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:900;color:${c}">${score}</div>
      </div>
      <div style="font-size:9px;color:#666;margin-top:3px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">${label}</div>
    </div>`;
  }

  /** Full 5-score row for a creative card */
  function scoreRow(sc) {
    return `<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;padding:14px 0 10px;border-top:1px solid #1e1e1e">
      ${scoreRing(sc.hookScore,  'Hook',  44)}
      ${scoreRing(sc.holdScore,  'Hold',  44)}
      ${scoreRing(sc.clickScore, 'Click', 44)}
      ${scoreRing(sc.engScore,   'Eng.',  44)}
      ${scoreRing(sc.convScore,  'Conv.', 44)}
    </div>`;
  }

  /** Overall score badge pill */
  function overallBadge(overall) {
    const c = scoreColor(overall);
    return `<span style="display:inline-flex;align-items:center;gap:4px;background:${c}18;color:${c};border:1px solid ${c}30;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800">
      ${overall} · ${scoreLabel(overall)}
    </span>`;
  }

  /** Fatigue warning badge — shown when ad has run 30+ days and score is weak */
  function fatigueBadge(ad, scores) {
    const days = parseInt(ad.days_active || ad.age_days || 0);
    if (days >= 30 && scores.overall < 45) {
      return `<span style="background:#ef444415;color:#ef4444;border:1px solid #ef444430;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:3px">⚠️ Creative fatigue</span>`;
    }
    if (days >= 60) {
      return `<span style="background:#f59e0b15;color:#f59e0b;border:1px solid #f59e0b30;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:3px">⏱ ${days} dage aktiv</span>`;
    }
    return '';
  }

  /** Render a sorted creative gallery grid for any platform */
  function renderCreativeGallery(containerEl, ads, opts = {}) {
    if (!ads.length) {
      containerEl.innerHTML = '<div style="text-align:center;padding:40px;color:#555">Ingen aktive annoncer fundet.</div>';
      return;
    }

    const platform  = opts.platform || 'generic';
    const accentClr = opts.accent   || '#CCFF00';
    const noThumb   = opts.noThumb  || '📱';

    // Compute scores and sort
    const scored = ads.map(ad => ({ ...ad, _sc: computeCreativeScores(ad) }));
    const sortKey = opts.sortKey || 'score';
    scored.sort((a, b) => {
      if (sortKey === 'score')  return b._sc.overall - a._sc.overall;
      if (sortKey === 'roas')   return (parseFloat(b.roas) || 0) - (parseFloat(a.roas) || 0);
      if (sortKey === 'views')  return (parseInt(b.video_views) || 0) - (parseInt(a.video_views) || 0);
      if (sortKey === 'swipes') return (parseInt(b.swipe_ups) || 0) - (parseInt(a.swipe_ups) || 0);
      if (sortKey === 'spend')  return (parseFloat(b.spend) || 0) - (parseFloat(a.spend) || 0);
      return 0;
    });

    const topScore = scored[0]?._sc.overall || 0;

    const cards = scored.map((ad, idx) => {
      const sc  = ad._sc;
      const isTop = idx === 0;
      const thumb = ad.thumbnail_url || ad.image_url || '';
      const name  = (ad.ad_name || ad.name || 'Unavngivet annonce').substring(0, 60);

      // Primary metric display based on platform
      let metric1Label, metric1Val, metric2Label, metric2Val;
      if (platform === 'tiktok') {
        metric1Label = 'Video Views';  metric1Val = fmt(ad.video_views);
        metric2Label = 'ROAS';         metric2Val = fmt(ad.roas, 2) + 'x';
      } else if (platform === 'snap') {
        metric1Label = 'Swipe-ups';    metric1Val = fmt(ad.swipe_ups);
        metric2Label = 'CPM';          metric2Val = fmt(ad.cpm, 0) + ' kr';
      } else {
        metric1Label = 'Reach';        metric1Val = fmt(ad.reach);
        metric2Label = 'CTR';          metric2Val = (parseFloat(ad.ctr)||0).toFixed(2) + '%';
      }

      return `
        <div class="rzpa-ci-card${isTop ? ' rzpa-ci-card--top' : ''}" style="${isTop ? `border-color:${accentClr}30` : ''}">
          ${isTop ? `<div class="rzpa-ci-crown" style="background:${accentClr}">👑 #1 Best performer</div>` : ''}

          <!-- Thumbnail -->
          <div class="rzpa-ci-thumb">
            ${thumb
              ? `<img src="${thumb}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
              : ''}
            <div class="rzpa-ci-no-thumb" style="${thumb ? 'display:none' : ''}">${noThumb}</div>
            <div class="rzpa-ci-overall" style="background:${scoreColor(sc.overall)}18;color:${scoreColor(sc.overall)};border:1px solid ${scoreColor(sc.overall)}40">
              ${sc.overall}
            </div>
            ${fatigueBadge(ad, sc) ? `<div style="position:absolute;bottom:8px;left:8px">${fatigueBadge(ad, sc)}</div>` : ''}
          </div>

          <!-- Body -->
          <div class="rzpa-ci-body">
            <div class="rzpa-ci-rank" style="color:${accentClr}">#${idx + 1}</div>
            <div class="rzpa-ci-name" title="${name}">${name}</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 0">
              ${overallBadge(sc.overall)}
              ${ad.format ? `<span style="background:rgba(255,255,255,.06);color:#888;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:600">${ad.format === 'video' ? '▶ Video' : '🖼 Billede'}</span>` : ''}
            </div>

            <!-- Metrics -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px">
              <div style="background:#0f0f0f;border-radius:8px;padding:8px 10px">
                <div style="font-size:10px;color:#555;margin-bottom:2px;text-transform:uppercase;letter-spacing:.4px">${metric1Label}</div>
                <div style="font-size:16px;font-weight:800;color:${accentClr}">${metric1Val}</div>
              </div>
              <div style="background:#0f0f0f;border-radius:8px;padding:8px 10px">
                <div style="font-size:10px;color:#555;margin-bottom:2px;text-transform:uppercase;letter-spacing:.4px">${metric2Label}</div>
                <div style="font-size:16px;font-weight:800;color:#ddd">${metric2Val}</div>
              </div>
            </div>

            <!-- 5 Score rings -->
            ${scoreRow(sc)}
          </div>
        </div>`;
    });

    containerEl.innerHTML = `
      <div style="margin-bottom:14px;font-size:12px;color:#555">
        ${scored.length} annoncer analyseret &nbsp;·&nbsp;
        Gennemsnitlig overall score: <strong style="color:#ddd">${Math.round(scored.reduce((s,a)=>s+a._sc.overall,0)/scored.length)}</strong> &nbsp;·&nbsp;
        Bedste: <strong style="color:${scoreColor(topScore)}">${topScore}</strong>
      </div>
      <div class="rzpa-ci-grid">${cards.join('')}</div>`;
  }

  // ════════════════════════════════════════════════════
  // PAGE: SNAPCHAT
  // ════════════════════════════════════════════════════

  async function initSnap() {
    let days    = 30;
    let snapData = [];
    let creativeSortKey = 'score';

    initDateFilter('rzpa-date-filter', d => { days = d; loadSnap(d); });
    loadSnap(days);

    el('rzpa-sync-snap')?.addEventListener('click', async () => {
      const btn = el('rzpa-sync-snap');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Synkroniserer…'; }
      clearCache('/snap/');
      await api('/snap/sync', { method: 'POST', body: JSON.stringify({days}) });
      await loadSnap(days);
      if (btn) { btn.disabled = false; btn.textContent = '⟳ Hent data'; }
    });

    // Creative sort buttons
    document.querySelectorAll('#snap-creative-sort [data-csort]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#snap-creative-sort [data-csort]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        creativeSortKey = btn.dataset.csort;
        const content = el('snap-creatives-content');
        if (content && snapData.length) {
          renderCreativeGallery(content, snapData, { platform:'snap', accent:'#FFFC00', noThumb:'👻', sortKey: creativeSortKey });
        }
      });
    });

    // Load Creatives button
    el('snap-load-creatives')?.addEventListener('click', async () => {
      const btn     = el('snap-load-creatives');
      const content = el('snap-creatives-content');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Henter…'; }
      if (content) content.innerHTML = '<div class="rzpa-loading">Analyserer Snapchat-annoncer…</div>';
      try {
        const r = await api(`/snap/ads?days=${days}`, { timeout: 20 });
        snapData = r.data || [];
        renderCreativeGallery(content, snapData, { platform:'snap', accent:'#FFFC00', noThumb:'👻', sortKey: creativeSortKey });
      } catch(e) {
        if (content) content.innerHTML = `<div style="color:#ef4444;padding:24px">⚠️ ${e.message}</div>`;
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = '📊 Hent creatives'; }
      }
    });

    // AI Analyse
    el('snap-ai-refresh')?.addEventListener('click', async () => {
      const btn     = el('snap-ai-refresh');
      const content = el('snap-ai-content');
      if (!btn || !content) return;
      btn.disabled = true; btn.textContent = '⏳ Analyserer…';
      content.innerHTML = '<div class="rzpa-loading">AI-specialist analyserer dine Snapchat-kampagner…</div>';
      try {
        const r = await api(`/snap/ai-analysis?days=${days}`, { timeout: 45 });
        const txt = r.data?.analysis || r.analysis || 'Ingen analyse tilgængelig.';
        content.innerHTML = `<div class="rzpa-ai-response">${txt.replace(/\n/g,'<br>')}</div>`;
      } catch(e) {
        content.innerHTML = `<div style="color:#ef4444">⚠️ AI-analyse fejlede: ${e.message}</div>`;
      } finally {
        btn.disabled = false; btn.textContent = '✨ Analysér nu';
      }
    });
  }

  async function loadSnap(days) {
    const [sum, camps] = await Promise.all([
      api(`/snap/summary?days=${days}`),
      api(`/snap/campaigns?days=${days}`),
    ]);
    const s    = sum.data   || {};
    const data = camps.data || [];

    if (s.configured === false) {
      const app = el('rzpa-app');
      if (app) app.innerHTML = `<div class="rzpa-not-configured">
        <h2>⚙️ Snapchat Ads er ikke opsat</h2>
        <p>Gå til <a href="?page=rzpa-settings">Indstillinger</a> og tilføj din Snapchat access token og ad account ID.</p>
      </div>`;
      return;
    }

    // Period label
    const pl = el('snap-period-label');
    if (pl) { el('snap-period-days').textContent = days; pl.style.display = 'block'; }

    // KPI v2
    renderKPI('kpi_spend',      fmt(s.total_spend, 0) + ' kr');
    renderKPI('kpi_swipes',     fmt(s.total_swipe_ups));
    renderKPI('kpi_impr',       fmt(s.total_impressions));
    const eng = parseFloat(s.avg_engagement_rate) || 0;
    renderKPI('kpi_engagement', fmt(eng, 2) + '%');
    const engPill = el('kpi_engagement_sub');
    if (engPill) {
      if (eng >= 1)   { engPill.className = 'k2-status pill-good';  engPill.textContent = '✓ Over 1% — godt'; }
      else if (eng >= 0.3) { engPill.className = 'k2-status pill-mid'; engPill.textContent = '~ Middel'; }
      else if (eng > 0)    { engPill.className = 'k2-status pill-bad'; engPill.textContent = '↓ Under 0,3%'; }
    }

    // Performance summary
    const good = data.filter(c => (parseFloat(c.engagement_rate)||0) >= 1).length;
    const mid  = data.filter(c => { const v = parseFloat(c.engagement_rate)||0; return v >= 0.3 && v < 1; }).length;
    const bad  = data.filter(c => (parseFloat(c.engagement_rate)||0) < 0.3 && parseFloat(c.spend||0) > 0).length;
    const ps   = el('snap-perf-summary');
    if (ps && data.length) {
      setText('snap_perf_good', good); setText('snap_perf_mid', mid); setText('snap_perf_bad', bad);
      ps.style.display = 'flex';
    }

    // Charts
    const top6   = data.slice(0, 6);
    const labels = top6.map(c => c.campaign_name.replace(/Rezponz\s*[–-]\s*/i,'').slice(0, 20));
    barChart('chart_spend', labels,
      [{ data: top6.map(c => Math.round(c.spend || 0)), backgroundColor: '#FFFC00', borderRadius: 5 }],
      { yTick: v => Math.round(v/1000)+'k kr' }
    );
    barChart('chart_engagement', labels,
      [{ data: top6.map(c => parseFloat(c.engagement_rate)||0),
         backgroundColor: top6.map(c => (parseFloat(c.engagement_rate)||0) >= 1 ? '#CCFF00' : (parseFloat(c.engagement_rate)||0) >= 0.3 ? '#f5a623' : '#cc4400'),
         borderRadius: 5 }],
      { yTick: v => v + '%' }
    );

    // Campaign table with filter
    let snapAllData = data;
    const renderSnapTable = (rows) => {
      const tbody = el('snap_tbody');
      const noRes = el('snap-no-results');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '';
        if (noRes) noRes.style.display = 'block';
        return;
      }
      if (noRes) noRes.style.display = 'none';
      tbody.innerHTML = rows.map(c => {
        const eng = parseFloat(c.engagement_rate) || 0;
        const rating = eng >= 1 ? '<span class="perf-pill good">🟢 Godt</span>' : eng >= 0.3 ? '<span class="perf-pill mid">🟡 Middel</span>' : '<span class="perf-pill bad">🔴 Svagt</span>';
        return `<tr>
          <td style="color:#ddd;font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis" title="${c.campaign_name}">${c.campaign_name}</td>
          <td>${badgeHtml(c.status)}</td>
          <td>${fmt(c.spend,0)} kr</td>
          <td>${fmt(c.impressions)}</td>
          <td style="color:#FFFC00;font-weight:700">${fmt(c.swipe_ups)}</td>
          <td>${fmt(c.conversions)}</td>
          <td>${fmt(c.cpm,0)} kr</td>
          <td>${fmt(eng,2)}%</td>
          <td>${rating}</td>
        </tr>`;
      }).join('');
    };

    renderSnapTable(snapAllData);

    // Filter bar
    document.querySelectorAll('#snap-filter-bar .filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#snap-filter-bar .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const f = btn.dataset.filter;
        if (f === 'all')    renderSnapTable(snapAllData);
        else if (f === 'good') renderSnapTable(snapAllData.filter(c=>(parseFloat(c.engagement_rate)||0)>=1));
        else if (f === 'mid')  renderSnapTable(snapAllData.filter(c=>{const v=parseFloat(c.engagement_rate)||0;return v>=0.3&&v<1;}));
        else if (f === 'bad')  renderSnapTable(snapAllData.filter(c=>(parseFloat(c.engagement_rate)||0)<0.3&&parseFloat(c.spend||0)>0));
        else renderSnapTable(snapAllData.filter(c => c.status?.toUpperCase() === f));
      });
    });
  }

  // ════════════════════════════════════════════════════
  // PAGE: TIKTOK
  // ════════════════════════════════════════════════════

  async function initTikTok() {
    let days   = 30;
    let ttData = [];
    let creativeSortKey = 'score';

    initDateFilter('rzpa-date-filter', d => { days = d; loadTikTok(d); });
    loadTikTok(days);

    el('rzpa-sync-tiktok')?.addEventListener('click', async () => {
      const btn = el('rzpa-sync-tiktok');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Synkroniserer…'; }
      clearCache('/tiktok/');
      await api('/tiktok/sync', { method: 'POST', body: JSON.stringify({days}) });
      await loadTikTok(days);
      if (btn) { btn.disabled = false; btn.textContent = '⟳ Hent data'; }
    });

    // Creative sort buttons
    document.querySelectorAll('#tiktok-creative-sort [data-csort]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#tiktok-creative-sort [data-csort]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        creativeSortKey = btn.dataset.csort;
        const content = el('tiktok-creatives-content');
        if (content && ttData.length) {
          renderCreativeGallery(content, ttData, { platform:'tiktok', accent:'#ff0050', noThumb:'🎵', sortKey: creativeSortKey });
        }
      });
    });

    // Load Creatives button
    el('tiktok-load-creatives')?.addEventListener('click', async () => {
      const btn     = el('tiktok-load-creatives');
      const content = el('tiktok-creatives-content');
      if (btn) { btn.disabled = true; btn.textContent = '⏳ Henter…'; }
      if (content) content.innerHTML = '<div class="rzpa-loading">Analyserer TikTok-videoer…</div>';
      try {
        const r = await api(`/tiktok/ads?days=${days}`, { timeout: 20 });
        ttData = r.data || [];
        renderCreativeGallery(content, ttData, { platform:'tiktok', accent:'#ff0050', noThumb:'🎵', sortKey: creativeSortKey });
      } catch(e) {
        if (content) content.innerHTML = `<div style="color:#ef4444;padding:24px">⚠️ ${e.message}</div>`;
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = '📊 Hent creatives'; }
      }
    });

    // AI Analyse
    el('tiktok-ai-refresh')?.addEventListener('click', async () => {
      const btn     = el('tiktok-ai-refresh');
      const content = el('tiktok-ai-content');
      if (!btn || !content) return;
      btn.disabled = true; btn.textContent = '⏳ Analyserer…';
      content.innerHTML = '<div class="rzpa-loading">AI-specialist analyserer dine TikTok-kampagner…</div>';
      try {
        const r = await api(`/tiktok/ai-analysis?days=${days}`, { timeout: 45 });
        const txt = r.data?.analysis || r.analysis || 'Ingen analyse tilgængelig.';
        content.innerHTML = `<div class="rzpa-ai-response">${txt.replace(/\n/g,'<br>')}</div>`;
      } catch(e) {
        content.innerHTML = `<div style="color:#ef4444">⚠️ AI-analyse fejlede: ${e.message}</div>`;
      } finally {
        btn.disabled = false; btn.textContent = '✨ Analysér nu';
      }
    });
  }

  async function loadTikTok(days) {
    const [sum, camps] = await Promise.all([
      api(`/tiktok/summary?days=${days}`),
      api(`/tiktok/campaigns?days=${days}`),
    ]);
    const s    = sum.data   || {};
    const data = camps.data || [];

    if (s.configured === false) {
      const app = el('rzpa-app');
      if (app) app.innerHTML = `<div class="rzpa-not-configured">
        <h2>⚙️ TikTok Ads er ikke opsat</h2>
        <p>Gå til <a href="?page=rzpa-settings">Indstillinger</a> og tilføj din TikTok access token og advertiser ID.</p>
      </div>`;
      return;
    }

    // Period label
    const pl = el('tiktok-period-label');
    if (pl) { el('tiktok-period-days').textContent = days; pl.style.display = 'block'; }

    // KPI v2
    renderKPI('kpi_spend', fmt(s.total_spend, 0) + ' kr');
    renderKPI('kpi_views', fmt(s.total_video_views));
    const roas = parseFloat(s.avg_roas) || 0;
    renderKPI('kpi_roas',  fmt(roas, 2) + 'x');
    const roasPill = el('kpi_roas_pill');
    if (roasPill) {
      if (roas >= 2.5)  { roasPill.className = 'k2-status pill-good';  roasPill.textContent = '✓ Over 2,5x — stærkt'; }
      else if (roas >= 1.5) { roasPill.className = 'k2-status pill-mid'; roasPill.textContent = '~ Ok'; }
      else if (roas > 0)    { roasPill.className = 'k2-status pill-bad'; roasPill.textContent = '↓ Under 1,5x'; }
    }
    // Hook rate = total video views / total impressions (approx)
    const impr     = parseInt(s.total_impressions) || 1;
    const vviews   = parseInt(s.total_video_views)  || 0;
    const hookRate = Math.min(100, Math.round(vviews / impr * 100));
    renderKPI('kpi_hook', hookRate + '%');
    const hookPill = el('kpi_hook_sub');
    if (hookPill) {
      if (hookRate >= 25)  { hookPill.className = 'k2-status pill-good';  hookPill.textContent = '✓ Over 25% — godt'; }
      else if (hookRate >= 10) { hookPill.className = 'k2-status pill-mid'; hookPill.textContent = '~ Middel'; }
      else if (hookRate > 0)   { hookPill.className = 'k2-status pill-bad'; hookPill.textContent = '↓ Under 10%'; }
    }

    // Perf summary (ROAS-based for TikTok)
    const good = data.filter(c => (parseFloat(c.roas)||0) >= 2.5).length;
    const mid  = data.filter(c => { const v=parseFloat(c.roas)||0; return v>=1.5&&v<2.5; }).length;
    const bad  = data.filter(c => (parseFloat(c.roas)||0) < 1.5 && parseFloat(c.spend||0) > 0).length;
    const ps   = el('tiktok-perf-summary');
    if (ps && data.length) {
      setText('tiktok_perf_good', good); setText('tiktok_perf_mid', mid); setText('tiktok_perf_bad', bad);
      ps.style.display = 'flex';
    }

    // Charts
    const top6   = data.slice(0, 6);
    const labels = top6.map(c => c.campaign_name.replace(/Rezponz\s*[–-]\s*/i,'').slice(0, 22));
    barChart('chart_views', labels,
      [{ data: top6.map(c => parseInt(c.video_views)||0), backgroundColor: '#ff0050', borderRadius: 5 }],
      { yTick: v => (v/1000).toFixed(0)+'k' }
    );
    barChart('chart_roas', labels,
      [{ data: top6.map(c => parseFloat(c.roas)||0),
         backgroundColor: top6.map(c => (parseFloat(c.roas)||0)>=2.5?'#CCFF00':(parseFloat(c.roas)||0)>=1.5?'#88cc00':'#cc4400'),
         borderRadius: 5 }]
    );

    // Campaign table with filter
    let ttAllData = data;
    const renderTTTable = (rows) => {
      const tbody = el('tiktok_tbody');
      const noRes = el('tiktok-no-results');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '';
        if (noRes) noRes.style.display = 'block';
        return;
      }
      if (noRes) noRes.style.display = 'none';
      tbody.innerHTML = rows.map(c => {
        const r = parseFloat(c.roas) || 0;
        const rating = r >= 2.5 ? '<span class="perf-pill good">🟢 Stærkt</span>' : r >= 1.5 ? '<span class="perf-pill mid">🟡 Middel</span>' : '<span class="perf-pill bad">🔴 Svagt</span>';
        return `<tr>
          <td style="color:#ddd;font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis" title="${c.campaign_name}">${c.campaign_name}</td>
          <td>${badgeHtml(c.status)}</td>
          <td>${fmt(c.spend,0)} kr</td>
          <td>${fmt(c.video_views)}</td>
          <td>${fmt(c.clicks)}</td>
          <td>${fmt(c.conversions)}</td>
          <td class="${roasClass(r)}">${fmt(r,2)}x</td>
          <td>${fmt(c.cost_per_view,4)} kr</td>
          <td>${rating}</td>
        </tr>`;
      }).join('');
    };

    renderTTTable(ttAllData);

    document.querySelectorAll('#tiktok-filter-bar .filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#tiktok-filter-bar .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const f = btn.dataset.filter;
        if (f === 'all')    renderTTTable(ttAllData);
        else if (f === 'good') renderTTTable(ttAllData.filter(c=>(parseFloat(c.roas)||0)>=2.5));
        else if (f === 'mid')  renderTTTable(ttAllData.filter(c=>{const v=parseFloat(c.roas)||0;return v>=1.5&&v<2.5;}));
        else if (f === 'bad')  renderTTTable(ttAllData.filter(c=>(parseFloat(c.roas)||0)<1.5&&parseFloat(c.spend||0)>0));
        else renderTTTable(ttAllData.filter(c => c.status?.toUpperCase() === f));
      });
    });
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
      btn.textContent = '⏳ Genererer PDF…';
      notice.style.display = 'none';

      try {
        // Hent PDF direkte som binær blob fra server-side DomPDF endpoint
        const url = RZPA.adminPostUrl
          + '?action=rzpa_pdf_download'
          + '&days=' + days
          + '&_wpnonce=' + RZPA.pdfNonce;

        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) {
          let errMsg = 'PDF-generering fejlede (HTTP ' + res.status + ')';
          try {
            const errData = await res.clone().json();
            if (errData.error) errMsg = errData.error + (errData.in ? ' [' + errData.in + ']' : '');
          } catch(_) {
            const txt = await res.text().catch(() => '');
            if (txt && !txt.includes('<!DOCTYPE')) errMsg += ': ' + txt.slice(0, 300);
          }
          throw new Error(errMsg);
        }

        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/pdf')) {
          let errMsg = 'Uventet svartype: ' + contentType;
          try { const j = await res.clone().json(); if (j.error) errMsg = j.error; } catch(_) {}
          throw new Error(errMsg);
        }

        const blob   = await res.blob();
        const objUrl = URL.createObjectURL(blob);
        const a      = document.createElement('a');
        const today  = new Date().toISOString().slice(0, 10);
        a.href     = objUrl;
        a.download = 'rezponz-rapport-' + today + '.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(objUrl), 10000);

        notice.className   = 'rzpa-notice success';
        notice.textContent = '✅ PDF downloadet – tjek din downloads-mappe.';
        notice.style.display = 'block';

      } catch(e) {
        notice.className   = 'rzpa-notice error';
        notice.textContent = '❌ Fejl: ' + e.message;
        notice.style.display = 'block';
      } finally {
        btn.disabled    = false;
        btn.textContent = 'Generer & Åbn Rapport';
      }
    });

    initDateFilter('rzpa-date-filter', () => {});
  }

  // ════════════════════════════════════════════════════
  // PAGE: BLOG INDSIGT
  // ════════════════════════════════════════════════════

  async function initBlogInsights() {
    let days = 30;
    let allPosts = [];

    initDateFilter('rzpa-blog-date-filter', d => { days = d; loadBlogInsights(d); });

    async function loadBlogInsights(d) {
      const content  = el('rzpa-blog-content');
      const kpiBar   = el('rzpa-blog-kpis');
      const toolbar  = el('rzpa-blog-toolbar');
      if (!content) return;

      content.innerHTML = '<div class="rzpa-loading">Henter blogdata…</div>';
      if (kpiBar)  kpiBar.style.display = 'none';
      if (toolbar) toolbar.style.display = 'none';

      let posts;

      // Prøv inline PHP-data først (synkront, ingen API-kald nødvendigt)
      const inlineEl = el('rzpa-blog-inline');
      if (inlineEl && inlineEl.dataset.days === String(d)) {
        try { posts = JSON.parse(inlineEl.dataset.posts); } catch(e) {}
      }

      // Fallback: hent via REST API (ved datofilter-skift eller manglende inline-data)
      if (!Array.isArray(posts)) {
        try {
          const r = await api(`/blog/insights?days=${d}`, { timeout: 30 });
          posts = Array.isArray(r) ? r : (r?.data ?? r);
        } catch(e) {
          content.innerHTML = `<div style="color:#ef4444;padding:32px;text-align:center">⚠️ Kunne ikke hente blogdata: ${e.message}</div>`;
          return;
        }
      }

      if (!Array.isArray(posts) || !posts.length) {
        content.innerHTML = '<div class="rzpa-empty-state">Ingen blogindlæg fundet. Sørg for at der er publicerede indlæg på sitet.</div>';
        return;
      }

      allPosts = posts;
      try {
        renderBlogKPIs(posts, kpiBar);
        renderBlogTable(posts, content);
      } catch(e) {
        content.innerHTML = `<div style="color:#ef4444;padding:32px;text-align:center">⚠️ Render fejl: ${e.message}</div>`;
        return;
      }
      if (toolbar) toolbar.style.display = 'flex';

      // Søgning
      el('rzpa-blog-search')?.addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        const filtered = q ? allPosts.filter(p => p.title.toLowerCase().includes(q) || p.slug.includes(q)) : allPosts;
        const activeFilter = document.querySelector('.rzpa-blog-filter.active')?.dataset.filter || 'all';
        renderBlogTable(applyBlogFilter(filtered, activeFilter), content);
      });

      // Filtre
      document.querySelectorAll('.rzpa-blog-filter').forEach(btn => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('.rzpa-blog-filter').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          const q = el('rzpa-blog-search')?.value.toLowerCase() || '';
          let base = q ? allPosts.filter(p => p.title.toLowerCase().includes(q)) : allPosts;
          renderBlogTable(applyBlogFilter(base, btn.dataset.filter), content);
        });
      });
    }

    function applyBlogFilter(posts, filter) {
      switch (filter) {
        case 'high':   return posts.filter(p => p.priority === 'high');
        case 'top1-3': return posts.filter(p => p.position !== null && p.position <= 3);
        case 'page1':  return posts.filter(p => p.position !== null && p.position <= 10);
        case 'no-gsc': return posts.filter(p => !p.has_gsc);
        case 'ai':     return posts.filter(p => p.ai_visible);
        default:       return posts;
      }
    }

    function renderBlogKPIs(posts, container) {
      if (!container) return;
      const withGsc  = posts.filter(p => p.has_gsc);
      const page1    = posts.filter(p => p.position !== null && p.position <= 10).length;
      const aiVis    = posts.filter(p => p.ai_visible).length;
      const avgPos   = withGsc.length
        ? (withGsc.reduce((s, p) => s + (p.position || 0), 0) / withGsc.length).toFixed(1)
        : '–';
      const highPri  = posts.filter(p => p.priority === 'high').length;

      container.innerHTML = `
        <div class="rzpa-kpi-card">
          <div class="rzpa-kpi-icon">📝</div>
          <div class="rzpa-kpi-label">Blogindlæg i alt</div>
          <div class="rzpa-kpi-value">${posts.length}</div>
        </div>
        <div class="rzpa-kpi-card">
          <div class="rzpa-kpi-icon">🏆</div>
          <div class="rzpa-kpi-label">Side 1 (top 10)</div>
          <div class="rzpa-kpi-value">${page1}</div>
        </div>
        <div class="rzpa-kpi-card">
          <div class="rzpa-kpi-icon">📊</div>
          <div class="rzpa-kpi-label">Gns. placering</div>
          <div class="rzpa-kpi-value">${avgPos}</div>
        </div>
        <div class="rzpa-kpi-card">
          <div class="rzpa-kpi-icon">🤖</div>
          <div class="rzpa-kpi-label">AI-synlige</div>
          <div class="rzpa-kpi-value" style="color:var(--neon)">${aiVis}</div>
        </div>
        <div class="rzpa-kpi-card">
          <div class="rzpa-kpi-icon">🔴</div>
          <div class="rzpa-kpi-label">Kræver handling</div>
          <div class="rzpa-kpi-value" style="color:#ef4444">${highPri}</div>
        </div>`;
      container.style.display = 'grid';
      container.style.gridTemplateColumns = 'repeat(5, 1fr)';
    }

    // Mapper rec_label → fix_type der matches med PHP's apply_ai_fix_to_post()
    const BLOG_FIX_MAP = {
      'Ikke i GSC':           'fix_content',
      'Optimer title & CTR':  'fix_ctr',
      'Øg AI-synlighed':      'fix_ai_vis',
      'Mangler AI-synlighed': 'fix_ai_vis',
      'Tæt på side 1':        'fix_content',
      'Svag placering':       'fix_content',
      'Meget lav synlighed':  'fix_rewrite',
    };

    function renderBlogTable(posts, container) {
      if (!posts.length) {
        container.innerHTML = '<div class="rzpa-empty-state">Ingen indlæg matcher det valgte filter.</div>';
        return;
      }

      const posColor = p => {
        if (p === null) return '#666';
        if (p <= 3)  return '#4ade80';
        if (p <= 10) return '#CCFF00';
        if (p <= 20) return '#f59e0b';
        return '#ef4444';
      };
      const posBg = p => {
        if (p === null) return '#66666618';
        if (p <= 3)  return '#4ade8018';
        if (p <= 10) return '#CCFF0018';
        if (p <= 20) return '#f59e0b18';
        return '#ef444418';
      };
      const priDot = pri => {
        if (pri === 'resolved') return '✅';
        if (pri === 'pending')  return '⏳';
        if (pri === 'high')     return '🔴';
        if (pri === 'medium')   return '🟡';
        return '🟢';
      };

      let html = `
        <div class="rzpa-blog-table-wrap">
          <table class="rzpa-blog-table">
            <thead>
              <tr>
                <th>Blogindlæg</th>
                <th class="num" title="Gennemsnitlig placering på Google (lavere = bedre). #1 = øverst på Google.">Google placering ↕</th>
                <th class="num">Klik</th>
                <th class="num">Visninger</th>
                <th class="num">CTR</th>
                <th>AI</th>
                <th>Anbefaling</th>
              </tr>
            </thead>
            <tbody>`;

      posts.forEach(post => {
        const pos     = post.position;
        const posLabel = pos !== null ? 'Side ' + Math.ceil(pos / 10) : '–';
        const date    = post.date ? post.date.substring(0, 10) : '';
        const thumb   = post.thumbnail
          ? `<img src="${post.thumbnail}" alt="" class="rzpa-blog-thumb" loading="lazy">`
          : `<div class="rzpa-blog-thumb-empty">📝</div>`;
        const aiLabel = post.ai_visible
          ? `<span class="rzpa-blog-ai-badge rzpa-blog-ai-yes">🤖 Synlig</span>`
          : `<span class="rzpa-blog-ai-badge rzpa-blog-ai-no">–</span>`;

        html += `
              <tr class="rzpa-blog-row" data-postid="${post.post_id}">
                <td class="rzpa-blog-title-cell">
                  ${thumb}
                  <div class="rzpa-blog-title-wrap">
                    <div class="rzpa-blog-title">${post.title}</div>
                    <div class="rzpa-blog-meta">
                      <span>${date}</span>
                      <a href="${post.url}" target="_blank" rel="noopener" class="rzpa-blog-url-link" onclick="event.stopPropagation()">↗ Se side</a>
                    </div>
                  </div>
                </td>
                <td class="num">
                  <span style="background:${posBg(pos)};color:${posColor(pos)};padding:3px 9px;border-radius:20px;font-size:12px;font-weight:700">${posLabel}</span>
                </td>
                <td class="num">${post.clicks > 0 ? post.clicks.toLocaleString('da-DK') : '–'}</td>
                <td class="num">${post.impressions > 0 ? post.impressions.toLocaleString('da-DK') : '–'}</td>
                <td class="num">${post.ctr > 0 ? post.ctr.toFixed(1) + '%' : '–'}</td>
                <td>${aiLabel}</td>
                <td class="rzpa-blog-rec-cell">
                  <span class="rzpa-blog-rec-dot">${priDot(post.priority)}</span>
                  <span class="rzpa-blog-rec-label">${post.rec_label}</span>
                  ${post.has_gsc
                    ? ''
                    : post.indexing_requested
                      ? `<span class="rzpa-index-btn" style="cursor:default;opacity:.7;color:var(--neon);border-color:rgba(204,255,0,.2)" title="Indekseringsanmodning sendt ${new Date(post.indexing_requested).toLocaleDateString('da-DK',{day:'2-digit',month:'2-digit'})} – afventer Google">⏳ Afventer Google</span>`
                      : `<button class="rzpa-index-btn" data-url="${post.url}" title="Bed Google om at indeksere denne side">↗ Indekser</button>`}
                  ${post.fixed_at ? (() => { const d = new Date(post.fixed_at); const lbl = d.toLocaleDateString('da-DK',{day:'2-digit',month:'2-digit'}); return `<span class="rzpa-fixed-badge" title="AI fikset ${post.fixed_at}">✓ ${lbl}</span>`; })() : ''}
                  ${BLOG_FIX_MAP[post.rec_label] && post.post_id && post.priority !== 'resolved' && post.priority !== 'pending' ? `<button class="rzpa-blog-fix-btn rzpa-index-btn" data-post-id="${post.post_id}" data-fix-type="${BLOG_FIX_MAP[post.rec_label]}" data-keyword="${encodeURIComponent(post.title)}" style="${post.fixed_at ? 'border-color:rgba(74,222,128,.25);color:#4ade80;opacity:.75' : 'border-color:rgba(204,255,0,.3);color:var(--neon)'}">${post.fixed_at ? '🔄 Fiks igen' : '⚡ Fiks'}</button>` : ''}
                </td>
              </tr>
              <tr class="rzpa-blog-expand-row" id="expand-${post.post_id}" style="display:none">
                <td colspan="7">
                  <div class="rzpa-blog-expand">
                    <div class="rzpa-blog-expand-icon">💡</div>
                    <div>
                      <div class="rzpa-blog-expand-title">Anbefaling: ${post.rec_label}</div>
                      <div class="rzpa-blog-expand-text">${post.rec_detail}</div>
                      ${post.ai_keyword ? `<div style="margin-top:8px;font-size:12px;color:#888">🤖 Matchet AI-søgeord: <strong style="color:var(--neon)">${post.ai_keyword}</strong></div>` : ''}
                    </div>
                  </div>
                </td>
              </tr>`;
      });

      html += `</tbody></table></div>`;
      container.innerHTML = html;

      // Fix 1: HTML-escape helper – forhindrer XSS når AI-strenge injiceres i innerHTML
      const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

      // Indekser-knapper (ekskl. Fiks-knapper der deler rzpa-index-btn styling)
      container.querySelectorAll('.rzpa-index-btn:not(.rzpa-blog-fix-btn)').forEach(btn => {
        btn.addEventListener('click', async e => {
          e.stopPropagation();
          const url = btn.dataset.url;
          btn.textContent = '⏳';
          btn.disabled = true;
          try {
            const res = await api('/blog/request-indexing', { method: 'POST', body: JSON.stringify({ url }) });
            if (res.queued || res.data?.queued) {
              // Erstat knap med "⏳ Afventer Google" span
              const now = new Date().toLocaleDateString('da-DK',{day:'2-digit',month:'2-digit'});
              btn.outerHTML = `<span class="rzpa-index-btn" style="cursor:default;opacity:.7;color:var(--neon);border-color:rgba(204,255,0,.2)" title="Indekseringsanmodning sendt – afventer Google crawling (24-48 timer)">⏳ Afventer Google</span>`;
              // Opdater rec_label i samme række til "⏳ Indeksering afventer"
              const recCell = btn.closest?.('td') || document.querySelector(`[data-url="${url}"]`)?.closest('tr')?.querySelector('.rzpa-blog-rec-cell');
              const labelEl = btn.closest('tr')?.querySelector('.rzpa-blog-rec-label');
              const dotEl   = btn.closest('tr')?.querySelector('.rzpa-blog-rec-dot');
              if (labelEl) labelEl.textContent = '⏳ Indeksering afventer';
              if (dotEl)   dotEl.textContent = '⏳';
              // Gem i allPosts så filter/re-render bevarer tilstand
              const entry = allPosts?.find(p => p.url === url);
              if (entry) { entry.indexing_requested = new Date().toISOString(); entry.rec_label = '⏳ Indeksering afventer'; entry.priority = 'pending'; }
            } else {
              // Fix: tjek både res.code og res.data?.code (WP REST wrapper)
              const errCode = res.code || res.data?.code || '';
              const errMsg  = res.message || res.data?.message || 'Ukendt fejl';
              const settingsUrl = (typeof RZPA !== 'undefined' && RZPA.adminUrl)
                ? RZPA.adminUrl + 'admin.php?page=rzpa-settings'
                : '#';
              if (errCode === 'scope_missing' || errMsg.includes('scope')) {
                btn.outerHTML = `<a href="${settingsUrl}" class="rzpa-index-btn" style="text-decoration:none;color:#f59e0b;border-color:rgba(245,158,11,.3);white-space:nowrap" title="Genopret Google-forbindelsen – Indexing API kræver ny tilladelse">⚠ Genopret Google →</a>`;
              } else if (errCode === 'no_gsc') {
                btn.outerHTML = `<a href="${settingsUrl}" class="rzpa-index-btn" style="text-decoration:none;color:#f59e0b;border-color:rgba(245,158,11,.3);white-space:nowrap">⚠ Tilslut Google →</a>`;
              } else {
                btn.textContent = '✗ ' + (errMsg.length > 35 ? errMsg.substring(0,35)+'…' : errMsg);
                btn.style.color = '#ff5555';
                btn.style.borderColor = 'rgba(255,85,85,.3)';
                btn.title = errMsg;
                btn.disabled = false;
              }
            }
          } catch(err) {
            btn.textContent = '✗ Netværksfejl';
            btn.style.color = '#ff5555';
            btn.title = err.message || 'Tjek konsollen for detaljer';
            btn.disabled = false;
          }
        });
      });

      // ⚡ Fiks-knapper på blogindlæg
      container.querySelectorAll('.rzpa-blog-fix-btn').forEach(btn => {
        btn.addEventListener('click', async e => {
          e.stopPropagation();
          const postId   = btn.dataset.postId;
          const fixType  = btn.dataset.fixType;
          const keyword  = decodeURIComponent(btn.dataset.keyword || '');
          // Fix 5: Inkluder Elementor-advarsel i confirm-dialog
          const shortKw = keyword.length > 50 ? keyword.substring(0, 50) + '…' : keyword;
          if (!confirm(`AI vil forbedre "${shortKw || 'dette indlæg'}".\n\nÆndringerne publiceres direkte – du kan fortryde via Rediger → Revisioner.\n\nFortsæt?`)) return;

          if (!postId || isNaN(parseInt(postId))) return;
          btn.disabled = true;

          // Fix 8: Løbende progress-timer så brugeren kan se der sker noget
          let elapsed = 0;
          const msgs = ['Analyserer indhold…', 'Skriver med GPT-4.1 mini…', 'Optimerer til SEO…', 'Gemmer ændringer…'];
          btn.textContent = '⏳ ' + msgs[0];
          const timer = setInterval(() => {
            elapsed++;
            const msg = msgs[Math.min(Math.floor(elapsed / 8), msgs.length - 1)];
            btn.textContent = `⏳ ${msg} (${elapsed}s)`;
          }, 1000);

          try {
            const res = await api('/ai/fix-post', { method: 'POST', body: JSON.stringify({ post_id: parseInt(postId), fix_type: fixType, keyword }) });
            clearInterval(timer);
            if (res.ok) {
              // Opdater rec_label + dot til "✅ Fikset" i samme række
              const row     = btn.closest('tr');
              const labelEl = row?.querySelector('.rzpa-blog-rec-label');
              const dotEl   = row?.querySelector('.rzpa-blog-rec-dot');
              if (labelEl) { labelEl.textContent = '✅ Fikset'; labelEl.style.color = '#4ade80'; }
              if (dotEl)   dotEl.textContent = '✅';
              // Opdater allPosts in-memory
              const postId = parseInt(btn.dataset.postId);
              const entry  = allPosts?.find(p => p.post_id === postId);
              if (entry) { entry.priority = 'resolved'; entry.rec_label = '✅ Fikset'; entry.fixed_at = res.fixed_at; if (res.fixed_types) entry.fixed_types = res.fixed_types; }

              // Erstat knap med grønt success-link + opdater fixed-badge
              const today   = new Date().toLocaleDateString('da-DK',{day:'2-digit',month:'2-digit'});
              const recCell = btn.closest('td');
              recCell?.querySelectorAll('.rzpa-fixed-badge').forEach(b => b.remove());
              const badge = document.createElement('span');
              badge.className = 'rzpa-fixed-badge';
              badge.title = `AI fikset ${res.fixed_at || new Date().toISOString()}`;
              badge.textContent = `✓ ${today}`;
              btn.replaceWith(badge);
              const link = document.createElement('a');
              link.href = res.edit_url; link.target = '_blank';
              link.className = 'rzpa-index-btn';
              link.style.cssText = 'text-decoration:none;color:#4ade80;border-color:rgba(74,222,128,.3)';
              link.textContent = `✓ ${esc(res.label) || 'Fikset'} →`;
              badge.after(link);

              // Vis fix-summary i expand-rækken
              const expandRow = document.getElementById(`expand-${postId}`);
              if (expandRow) {
                const ch = Array.isArray(res.changes) ? res.changes : [];
                // Fix 1: esc() på alle AI-genererede strenge inden innerHTML
                const changesHtml = ch.length
                  ? `<ul style="margin:8px 0 0 0;padding:0;list-style:none">${ch.map(c => `<li style="font-size:12px;color:#aaa;padding:2px 0;display:flex;gap:6px"><span style="color:#4ade80;flex-shrink:0">✓</span>${esc(c)}</li>`).join('')}</ul>`
                  : '';
                const titleHtml = res.new_title
                  ? `<div style="margin-top:10px;font-size:12px;padding:8px 10px;background:rgba(74,222,128,.06);border-radius:6px;border-left:2px solid #4ade80"><span style="color:#888">Ny titel: </span><strong style="color:#e5e5e5">${esc(res.new_title)}</strong></div>`
                  : '';
                const metaHtml = res.new_meta
                  ? `<div style="margin-top:6px;font-size:12px;padding:8px 10px;background:rgba(96,165,250,.06);border-radius:6px;border-left:2px solid #60a5fa"><span style="color:#888">Meta: </span><em style="color:#ccc">${esc(res.new_meta)}</em></div>`
                  : '';
                const expandDiv = expandRow.querySelector('.rzpa-blog-expand');
                if (expandDiv) {
                  expandDiv.innerHTML = `
                    <div class="rzpa-blog-expand-icon">✅</div>
                    <div style="flex:1">
                      <div class="rzpa-blog-expand-title" style="color:#4ade80">AI Fix gennemført · GPT-4.1 mini</div>
                      <div class="rzpa-blog-expand-text">${esc(res.label) || 'Indhold opdateret'}</div>
                      ${changesHtml}${titleHtml}${metaHtml}
                    </div>`;
                }
                expandRow.style.display = '';
              }
            } else {
              clearInterval(timer);
              const msg = res.message || res.error || '✗ Fejl';
              // Kun vis "Tilføj nøgle"-link når nøglen specifikt mangler
              if (res.code === 'no_openai') {
                const settingsUrl = (typeof RZPA !== 'undefined' && RZPA.adminUrl)
                  ? RZPA.adminUrl + 'admin.php?page=rzpa-settings#openai'
                  : '#';
                btn.outerHTML = `<a href="${settingsUrl}" class="rzpa-index-btn" style="text-decoration:none;color:#f59e0b;border-color:rgba(245,158,11,.3);white-space:nowrap">⚠ Tilføj OpenAI-nøgle →</a>`;
              } else {
                // Vis den faktiske fejlbesked (fx quota, rate limit, netværk)
                const shortMsg = msg.length > 40 ? msg.substring(0, 40) + '…' : msg;
                btn.textContent = '✗ ' + shortMsg;
                btn.title = msg;
                btn.style.color = '#ff5555';
                btn.style.fontSize = '11px';
                btn.disabled = false;
              }
            }
          } catch(err) {
            clearInterval(timer);
            btn.textContent = '✗ Netværksfejl';
            btn.title = err.message || 'Tjek konsollen for detaljer';
            btn.style.color = '#ff5555';
            btn.disabled = false;
          }
        });
      });

      // Klik på række → toggle udvidet anbefaling
      container.querySelectorAll('.rzpa-blog-row').forEach(row => {
        row.addEventListener('click', () => {
          const expandRow = document.getElementById('expand-' + row.dataset.postid);
          if (!expandRow) return;
          const isOpen = expandRow.style.display !== 'none';
          // Luk alle andre
          container.querySelectorAll('.rzpa-blog-expand-row').forEach(r => r.style.display = 'none');
          container.querySelectorAll('.rzpa-blog-row.active').forEach(r => r.classList.remove('active'));
          if (!isOpen) {
            expandRow.style.display = '';
            row.classList.add('active');
          }
        });
      });
    }

    loadBlogInsights(days);

    // ── AI Blog Strategi ──────────────────────────────────────────────────
    const aiBtn    = el('rzpa-blog-ai-btn');
    const aiResult = el('rzpa-blog-ai-result');

    aiBtn?.addEventListener('click', async () => {
      if (!aiBtn || !aiResult) return;

      aiBtn.disabled    = true;
      aiBtn.textContent = '⏳ Analyserer…';
      aiResult.style.display = 'none';

      try {
        const r = await api('/blog/ai-suggestions', {
          method: 'POST',
          body: JSON.stringify({ days }),
        });

        // WP_Error format: { code, message, data: { status } }
        if (r.code && r.message) throw new Error(r.message);
        if (r.success === false && r.error) throw new Error(r.error);

        const suggestions = r.data ?? r;
        if (!Array.isArray(suggestions) || !suggestions.length) {
          throw new Error('Ingen forslag modtaget fra AI. Tjek at OpenAI API-nøgle er sat under Indstillinger.');
        }

        aiResult.innerHTML = renderAISuggestions(suggestions);
        aiResult.style.display = 'block';

      } catch(e) {
        aiResult.innerHTML = `<div style="color:#ef4444;padding:20px 0;font-size:13px">❌ ${e.message}</div>`;
        aiResult.style.display = 'block';
      } finally {
        aiBtn.disabled    = false;
        aiBtn.textContent = '✨ Generér igen';
      }
    });

    function renderAISuggestions(suggestions) {
      const volClass  = v => v === 'høj' ? 'rzpa-ai-vol-high'   : v === 'medium' ? 'rzpa-ai-vol-medium'  : 'rzpa-ai-vol-low';
      const compClass = c => c === 'lav' ? 'rzpa-ai-comp-low'   : c === 'medium' ? 'rzpa-ai-comp-medium' : 'rzpa-ai-comp-high';
      const volLabel  = v => ({ 'høj': '📈 Høj søgevolumen', 'medium': '📊 Medium søgevolumen', 'lav': '📉 Lav søgevolumen' })[v] || v;
      const compLabel = c => ({ 'lav': '🟢 Lav konkurrence', 'medium': '🟡 Medium konkurrence', 'høj': '🔴 Høj konkurrence' })[c] || c;
      const rankClass = n => n === 1 ? 'rank-1' : n === 2 ? 'rank-2' : n === 3 ? 'rank-3' : 'rank-other';

      const items = suggestions.map(s => {
        const n    = parseInt(s.priority) || 0;
        const vol  = (s.search_volume || '').toLowerCase();
        const comp = (s.competition   || '').toLowerCase();
        const score = parseInt(s.value_score) || 0;
        const searches = s.estimated_monthly_searches || '';

        return `
        <div class="rzpa-ai-sug-item">
          <div class="rzpa-ai-sug-rank ${rankClass(n)}">${n}</div>

          <div class="rzpa-ai-sug-body">
            <div class="rzpa-ai-sug-title">${s.title || '–'}</div>
            <div class="rzpa-ai-sug-keyword">🔑 ${s.keyword || '–'}</div>

            <div class="rzpa-ai-sug-desc">
              <strong style="color:#ccc">Søgeintention:</strong> ${s.search_intent || '–'}
              <br><br>
              <strong style="color:#ccc">Rezponz-værdi:</strong> ${s.rezponz_value || '–'}
            </div>

            ${s.content_angle ? `<div class="rzpa-ai-sug-angle">💡 Vinkel: ${s.content_angle}</div>` : ''}
          </div>

          <div class="rzpa-ai-sug-meta">
            <div>
              <div class="rzpa-ai-score">${score}<span style="font-size:14px;color:#888">/10</span></div>
              <div class="rzpa-ai-score-lbl">Værdi</div>
            </div>
            ${vol  ? `<span class="rzpa-ai-badge ${volClass(vol)}">${volLabel(vol)}</span>`   : ''}
            ${comp ? `<span class="rzpa-ai-badge ${compClass(comp)}">${compLabel(comp)}</span>` : ''}
            ${searches ? `<span style="font-size:11px;color:#555">~${searches}/md</span>` : ''}
          </div>
        </div>`;
      }).join('');

      const generatedAt = new Date().toLocaleString('da-DK', { day:'numeric', month:'long', hour:'2-digit', minute:'2-digit' });
      return `
        <div class="rzpa-ai-suggestions">${items}</div>
        <div class="rzpa-ai-strat-info">
          🤖 Genereret af GPT-4o mini · ${generatedAt} · Baseret på dine eksisterende blogindlæg og Rezponz' rekrutteringsfokus
        </div>`;
    }
  }

  // ── Section init map (IIFE-scoped so PJAX can reach it) ─────────────────

  const initMap = {
    dashboard:    initDashboard,
    seo:          initSEO,
    ai:           initAI,
    meta:         initMeta,
    'google-ads': initGoogleAds,
    snap:         initSnap,
    tiktok:       initTikTok,
    rapport:      initRapport,
    blog:         initBlogInsights,
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
      ['rzpa-ad-modal', 'rzpa-top-ad-modal'].forEach(id => {
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

  // ══ FÆLLES FAKTURA-RENDERER MED ÅRS-FILTER + BILLING-LINKS ════════════════
  // Bruges af Meta, Google Ads (og fremtidigt Snap/TikTok).
  function renderInvoiceTable(container, data, platform, color) {
    const monthName = m => {
      if (!m) return '';
      const [y, mo] = m.split('-');
      const names = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
      return (names[parseInt(mo, 10) - 1] || mo) + ' ' + y;
    };

    // Byg årsfilter fra data
    const years = [...new Set(data.map(r => (r.month || '').split('-')[0]).filter(Boolean))].sort().reverse();
    const currency = data[0]?.currency || 'DKK';

    function renderRows(filterYear) {
      const rows = filterYear ? data.filter(r => r.month?.startsWith(filterYear)) : data;
      const total = rows.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0);
      const totalImpr = rows.reduce((s, r) => s + (r.impressions || 0), 0);
      const totalClicks = rows.reduce((s, r) => s + (r.clicks || 0), 0);

      container.querySelector('.inv-summary-total').textContent = fmt(total, 2) + ' ' + currency;
      container.querySelector('.inv-summary-months').textContent = rows.length;
      if (container.querySelector('.inv-summary-impr')) {
        container.querySelector('.inv-summary-impr').textContent = num(totalImpr);
        container.querySelector('.inv-summary-clicks').textContent = num(totalClicks);
      }

      container.querySelector('tbody').innerHTML = rows.map(r => {
        const billingHref = r.billing_url || '#';
        return `<tr>
          <td style="color:#ccc;font-weight:600">${monthName(r.month)}</td>
          <td style="font-weight:700;color:#fff">${fmt(parseFloat(r.amount||0), 2)} ${r.currency||currency}</td>
          ${r.impressions !== undefined ? `<td style="color:#aaa">${num(r.impressions)}</td><td style="color:#aaa">${num(r.clicks)}</td>` : ''}
          <td><span style="color:#4ade80;font-size:11px;font-weight:600">✓ Betalt</span></td>
          <td>
            <a href="${billingHref}" target="_blank" rel="noopener"
               style="font-size:11px;color:${color};text-decoration:none;padding:3px 8px;border:1px solid ${color}40;border-radius:5px;white-space:nowrap">
              Se i ${platform} →
            </a>
          </td>
        </tr>`;
      }).join('');
    }

    const hasImpr = data.some(r => r.impressions !== undefined && r.impressions > 0);
    const extraCols = hasImpr ? '<th>Visninger</th><th>Klik</th>' : '';

    container.innerHTML = `
      <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;gap:8px;align-items:center">
          <span style="font-size:12px;color:#555">Filtrer år:</span>
          <select id="inv-year-filter" style="background:#111;color:#ccc;border:1px solid #333;border-radius:6px;padding:4px 10px;font-size:12px">
            <option value="">Alle år</option>
            ${years.map(y => `<option value="${y}">${y}</option>`).join('')}
          </select>
        </div>
      </div>
      <div style="display:flex;gap:28px;flex-wrap:wrap;padding:12px 16px;background:rgba(${color === '#1877F2' ? '24,119,242' : '66,133,244'},.05);border-radius:8px;border:1px solid rgba(${color === '#1877F2' ? '24,119,242' : '66,133,244'},.12);margin-bottom:14px">
        <div><div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px">Samlet forbrug</div><div class="inv-summary-total" style="font-size:20px;font-weight:700;color:#fff;margin-top:2px">–</div></div>
        <div><div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px">Måneder</div><div class="inv-summary-months" style="font-size:20px;font-weight:700;color:#fff;margin-top:2px">–</div></div>
        ${hasImpr ? `<div><div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px">Visninger</div><div class="inv-summary-impr" style="font-size:20px;font-weight:700;color:#fff;margin-top:2px">–</div></div>
        <div><div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px">Klik</div><div class="inv-summary-clicks" style="font-size:20px;font-weight:700;color:#fff;margin-top:2px">–</div></div>` : ''}
      </div>
      <div class="rzpa-table-wrap">
        <table class="rzpa-table">
          <thead><tr><th>Måned</th><th>Forbrug</th>${extraCols}<th>Status</th><th>Faktura</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>`;

    // Initial render
    renderRows('');

    // Årsfilter
    container.querySelector('#inv-year-filter')?.addEventListener('change', e => {
      renderRows(e.target.value);
    });

    // Sæt standard til indeværende år
    const curYear = new Date().getFullYear().toString();
    if (years.includes(curYear)) {
      container.querySelector('#inv-year-filter').value = curYear;
      renderRows(curYear);
    }
  }

  // ══ GOOGLE ADS AKTIVE ANNONCER ═════════════════════════════════════════════

  function initGadsAds() {
    el('gads-ads-load')?.addEventListener('click', loadGadsAds);
  }

  async function loadGadsAds() {
    const btn     = el('gads-ads-load');
    const content = el('gads-ads-content');
    if (!content) return;
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Henter…'; }
    content.innerHTML = '<div class="rzpa-loading">Henter aktive Google Ads-annoncer…</div>';
    console.log('[RZPA v2.0.8] loadGadsAds: starting API call...');

    let res;
    try {
      res = await api('/google-ads/ads');
      console.log('[RZPA v2.0.8] loadGadsAds: API response:', res);
    } catch(e) {
      console.error('[RZPA v2.0.8] loadGadsAds: API error:', e);
      if (btn) { btn.disabled = false; btn.textContent = '📢 Hent annoncer'; }
      content.innerHTML = `<span style="color:#ff6b6b">⚠️ Netværksfejl: ${e.message}</span>`;
      return;
    }
    if (btn) { btn.disabled = false; btn.textContent = '📢 Hent annoncer'; }
    const d = res?.data ?? [];
    console.log('[RZPA v2.0.8] loadGadsAds: data:', d);

    if (d && d.error) {
      content.innerHTML = `<span style="color:#ff6b6b">⚠️ ${d.error}</span>`;
      return;
    }
    if (!Array.isArray(d) || !d.length) {
      content.innerHTML = '<span style="color:#555">Ingen aktive annoncer fundet. Kontrollér at Google Ads er forbundet og har aktive kampagner med tekst-annoncer (RSA).</span>';
      return;
    }

    content.innerHTML = `<div class="gads-ads-grid">${d.map(ad => {
      const hl  = ad.headlines || [];
      const ds  = ad.descriptions || [];
      const host = ad.final_url ? (new URL(ad.final_url).hostname).replace('www.', '') : '';
      const ctrColor = ad.ctr >= 2 ? '#4ade80' : ad.ctr >= 1 ? '#f59e0b' : '#ef4444';
      return `<div class="gads-ad-card" style="display:flex;flex-direction:column;gap:0">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <span class="gads-ad-badge" style="background:rgba(66,133,244,.15);padding:2px 8px;border-radius:4px">RSA · Google Søgning</span>
          <span style="font-size:10px;color:#555;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${ad.ad_group}">${ad.ad_group || ad.campaign || ''}</span>
        </div>
        <div style="background:#0d0d0d;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px;margin-bottom:10px">
          <div style="color:#4ade80;font-size:11px;margin-bottom:3px">📢 Annonce · ${host}</div>
          <div style="color:#4285F4;font-size:15px;font-weight:700;line-height:1.4;margin-bottom:6px">${hl.slice(0,3).join(' | ')}</div>
          <div style="color:#aaa;font-size:12px;line-height:1.6">${ds.slice(0,2).join(' · ')}</div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:10px">
          <div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">
            <div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">Visninger</div>
            <div style="font-size:13px;font-weight:700;color:#ccc">${fmt(ad.impressions,0)}</div>
          </div>
          <div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">
            <div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">Klik</div>
            <div style="font-size:13px;font-weight:700;color:#ccc">${fmt(ad.clicks,0)}</div>
          </div>
          <div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">
            <div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">CTR</div>
            <div style="font-size:13px;font-weight:700;color:${ctrColor}">${fmt(ad.ctr,2)}%</div>
          </div>
          <div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">
            <div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">Forbrug</div>
            <div style="font-size:13px;font-weight:700;color:#ccc">${fmt(ad.spend,0)} kr</div>
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:auto">
          ${ad.gads_url ? `<a href="${ad.gads_url}" target="_blank" rel="noopener" style="flex:1;text-align:center;background:rgba(66,133,244,.12);border:1px solid rgba(66,133,244,.3);border-radius:6px;padding:6px 10px;font-size:11px;color:#4285F4;text-decoration:none">🔗 Se i Google Ads</a>` : ''}
          ${ad.final_url ? `<a href="${ad.final_url}" target="_blank" rel="noopener" style="flex:1;text-align:center;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);border-radius:6px;padding:6px 10px;font-size:11px;color:#4ade80;text-decoration:none">↗ Landingsside</a>` : ''}
        </div>
      </div>`;
    }).join('')}</div>`;
  }

  // ══ PDF DOWNLOAD HJÆLPER ══════════════════════════════════════════════════
  // Åbner et nyt vindue med formateret faktura-HTML og trigger print → PDF.
  function downloadInvoicePDF(data, platform, currency) {
    if (!data || !data.length) return;
    const total = data.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0);
    const mNames = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    const monthName = m => { if (!m) return ''; const [y,mo] = m.split('-'); return (mNames[parseInt(mo,10)-1]||mo)+' '+y; };

    const rows = data.map(r => `
      <tr>
        <td>${monthName(r.month)}</td>
        <td style="text-align:right"><strong>${parseFloat(r.amount||0).toFixed(2)} ${r.currency||currency||'DKK'}</strong></td>
        <td style="text-align:right">${(r.impressions||0).toLocaleString('da-DK')}</td>
        <td style="text-align:right">${(r.clicks||0).toLocaleString('da-DK')}</td>
        <td style="text-align:center;color:#2d8a4e">✓ Betalt</td>
      </tr>`).join('');

    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html>
<html lang="da"><head><meta charset="UTF-8">
<title>${platform} Betalingshistorik – Rezponz A/S</title>
<style>
  body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 40px; font-size: 14px; }
  h1 { font-size: 22px; margin-bottom: 4px; }
  .subtitle { color: #666; font-size: 13px; margin-bottom: 24px; }
  .summary { display: flex; gap: 32px; background: #f7f7f7; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; }
  .sum-item label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: .5px; display: block; }
  .sum-item strong { font-size: 20px; color: #111; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; padding: 10px 12px; background: #f0f0f0; border-bottom: 2px solid #ddd; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
  td { padding: 9px 12px; border-bottom: 1px solid #eee; }
  tr:last-child td { border-bottom: none; }
  .footer { margin-top: 28px; font-size: 11px; color: #aaa; border-top: 1px solid #eee; padding-top: 12px; }
  .print-btn { background: #111; color: #fff; border: none; padding: 10px 22px; border-radius: 6px; cursor: pointer; font-size: 14px; margin-bottom: 24px; }
  @media print { .print-btn { display: none; } body { margin: 20px; } }
</style></head><body>
<button class="print-btn" onclick="window.print()">🖨 Gem som PDF</button>
<h1>${platform} Betalingshistorik</h1>
<div class="subtitle">Rezponz A/S · Genereret ${new Date().toLocaleDateString('da-DK', {year:'numeric',month:'long',day:'numeric'})}</div>
<div class="summary">
  <div class="sum-item"><label>Samlet forbrug</label><strong>${total.toFixed(2)} ${currency||'DKK'}</strong></div>
  <div class="sum-item"><label>Antal måneder</label><strong>${data.length}</strong></div>
  <div class="sum-item"><label>Periode</label><strong>${monthName(data[data.length-1]?.month)} – ${monthName(data[0]?.month)}</strong></div>
</div>
<table>
  <thead><tr><th>Måned</th><th style="text-align:right">Forbrug</th><th style="text-align:right">Visninger</th><th style="text-align:right">Klik</th><th style="text-align:center">Status</th></tr></thead>
  <tbody>${rows}</tbody>
</table>
<div class="footer">Eksporteret fra Rezponz Marketing Dashboard · Data fra ${platform} Ads API</div>
</body></html>`);
    win.document.close();
    setTimeout(() => win.print(), 600);
  }

  // ══ CROSS-CHANNEL TOP ADS + BUDGET ANBEFALINGER (DASHBOARD) ═══════════════

  function initCrossChannelAds() {
    el('cross-channel-load-btn')?.addEventListener('click', loadCrossChannelAds);
    el('budget-recs-btn')?.addEventListener('click', loadBudgetRecommendations);
  }

  async function loadCrossChannelAds() {
    const btn = el('cross-channel-load-btn');
    const content = el('cross-channel-ads-content');
    if (!content) return;
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Henter…'; }
    content.innerHTML = '<div class="rzpa-loading">Henter annoncer fra alle kanaler…</div>';

    // Hent Meta top ads + Snap/TikTok ads parallelt
    const [metaR, snapR, ttR] = await Promise.all([
      api('/meta/top-ads?days=30').catch(() => ({})),
      api('/snap/ads?days=30').catch(() => ({})),
      api('/tiktok/ads?days=30').catch(() => ({})),
    ]);

    if (btn) { btn.disabled = false; btn.textContent = '📊 Hent annoncer'; }

    const metaAds = (metaR.data || []).slice(0, 3);
    const snapAds = (snapR.data || []).slice(0, 3);
    const ttAds   = (ttR.data   || []).slice(0, 3);

    const allEmpty = !metaAds.length && !snapAds.length && !ttAds.length;
    if (allEmpty) {
      content.innerHTML = '<p style="color:#555">Ingen aktive annoncer fundet. Synkronisér dine platforme og prøv igen.</p>';
      return;
    }

    const fmtBadge = f => f === 'video' ? '<span class="fmt-badge fmt-video">▶ Video</span>'
                        : f === 'carousel' ? '<span class="fmt-badge fmt-carousel">◫ Carousel</span>'
                        : '<span class="fmt-badge fmt-image">🖼 Billede</span>';

    const renderGroup = (ads, platformName, color) => {
      if (!ads.length) return '';
      return `<div class="cc-platform-group">
        <div class="cc-platform-label" style="color:${color}">${platformName}</div>
        <div class="cc-ads-row">
          ${ads.map(ad => {
            const img = ad.thumbnail_url || ad.image_url || ad.thumb_url || '';
            const name = ad.ad_name || ad.name || '';
            const reach = ad.reach || ad.impressions || 0;
            const spend = parseFloat(ad.spend||ad.cost||0);
            return `<div class="cc-ad-card">
              <div class="cc-ad-thumb">
                ${img ? `<img src="${img}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<div class="rzpa-top-ad-no-thumb">📷</div>'}
              </div>
              <div class="cc-ad-name" title="${name}">${name}</div>
              <div class="cc-ad-metrics">
                <span>👁 ${num(reach)}</span>
                ${spend > 0 ? `<span>💰 ${fmt(spend,0)} kr</span>` : ''}
              </div>
              ${fmtBadge(ad.format || 'image')}
            </div>`;
          }).join('')}
        </div>
      </div>`;
    };

    content.innerHTML = [
      renderGroup(metaAds, '📘 Meta (Facebook & Instagram)', '#1877F2'),
      renderGroup(snapAds, '👻 Snapchat', '#FFFC00'),
      renderGroup(ttAds,   '🎵 TikTok', '#ff2d55'),
    ].filter(Boolean).join('');
  }

  async function loadBudgetRecommendations() {
    const btn     = el('budget-recs-btn');
    const content = el('budget-recs-content');
    const note    = el('budget-recs-cache-note');
    if (!content) return;
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Analyserer…'; }
    content.innerHTML = '<span style="color:#666">Sender data til AI — 10-20 sekunder…</span>';

    const res = await api('/ads/budget-recommendations', { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' });
    if (btn) { btn.disabled = false; btn.textContent = '💡 Analysér budget'; }

    const d = res.data || {};
    if (d.error) {
      content.innerHTML = `<span style="color:#ff6b6b">⚠️ ${d.error}</span>`;
      return;
    }
    if (note && d.cached) note.textContent = '📋 Cachet analyse';

    if (d.analysis) {
      const lines = d.analysis.split('\n').filter(l => l.trim());
      content.innerHTML = lines.map(l =>
        l.match(/^\d+\./) ?
          `<div style="display:flex;gap:10px;margin-bottom:12px;padding:12px 14px;background:rgba(204,255,0,0.04);border-radius:8px;border-left:3px solid var(--neon)">
            <div style="color:var(--neon);font-weight:700;flex-shrink:0;font-size:15px">${l.match(/^\d+/)[0]}.</div>
            <div style="color:#ccc;font-size:13px;line-height:1.7">${l.replace(/^\d+\.\s*/, '')}</div>
          </div>` : `<p style="color:#888;font-size:12px;margin:4px 0">${l}</p>`
      ).join('');
    }
  }

  // ══ SEO SØGEORDSANBEFALINGER ══════════════════════════════════════════════

  function initKeywordSuggestions() {
    el('seo-kw-suggestions-btn')?.addEventListener('click', loadKeywordSuggestions);
  }

  async function loadKeywordSuggestions(force = false) {
    const btn     = el('seo-kw-suggestions-btn');
    const content = el('seo-kw-suggestions-content');
    const note    = el('seo-kw-cache-note');
    if (!content) return;
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Analyserer…'; }
    content.innerHTML = '<div class="rzpa-loading">AI analyserer søgeordslandskab for Rezponz — 15-30 sekunder…</div>';

    const res = await api('/seo/keyword-suggestions', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({force}) });
    if (btn) { btn.disabled = false; btn.textContent = '🔍 Hent søgeordsforslag'; }
    const d = res.data || {};

    if (d.error) {
      content.innerHTML = `<span style="color:#ff6b6b">⚠️ ${d.error}</span>`;
      return;
    }
    if (note && d.cached) note.textContent = '📋 Cachet — klik igen for ny analyse';

    const kws = d.keywords || [];
    if (!kws.length) { content.innerHTML = '<span style="color:#555">Ingen søgeordsforslag fundet.</span>'; return; }

    const diffColor = diff => diff === 'Lav' ? '#4ade80' : diff === 'Medium' ? '#f59e0b' : '#ef4444';
    const volColor  = vol  => vol  === 'Høj' ? '#4ade80' : vol  === 'Medium' ? '#f59e0b' : '#888';
    const intentMap = { kommerciel: '💼', informativ: '📖', lokal: '📍', rekruttering: '👥' };
    const posColor  = pos => pos <= 3 ? '#4ade80' : pos <= 10 ? '#f59e0b' : pos <= 30 ? '#f97316' : '#888';

    const ranked = kws.filter(k => k.current_position).length;
    content.innerHTML = `
      <div style="margin-bottom:14px;font-size:12px;color:#666;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <span>${kws.length} søgeordsforslag — sorteret efter prioritet · <strong style="color:${ranked>0?'#4ade80':'#888'}">${ranked} ranker du allerede på Google</strong>.</span>
        <button id="rzpa-kw-force-refresh" style="background:#1e1e1e;border:1px solid #333;color:#aaa;padding:3px 10px;border-radius:6px;cursor:pointer;font-size:11px;">🔄 Opdater med ny analyse</button>
      </div>
      <div class="rzpa-kw-suggestions-grid">
        ${kws.map((kw, i) => {
          const pos = kw.current_position;
          const posBadge = pos
            ? `<span class="rzpa-kw-badge" style="background:${posColor(pos)}20;color:${posColor(pos)};border-color:${posColor(pos)}40">📍 Google pos. ${Math.round(pos)}</span>`
            : '';
          return `
          <div class="rzpa-kw-card">
            <div class="rzpa-kw-card-header">
              <div class="rzpa-kw-rank">#${i+1}</div>
              <div class="rzpa-kw-intent" title="${kw.intent||''}">${intentMap[kw.intent]||'🔍'}</div>
            </div>
            <div class="rzpa-kw-phrase">${kw.keyword||''}</div>
            <div class="rzpa-kw-badges">
              ${posBadge}
              <span class="rzpa-kw-badge" style="background:${volColor(kw.monthly_searches)}20;color:${volColor(kw.monthly_searches)};border-color:${volColor(kw.monthly_searches)}40">
                📊 ${kw.monthly_searches||'?'} søgninger
              </span>
              <span class="rzpa-kw-badge" style="background:${diffColor(kw.difficulty)}20;color:${diffColor(kw.difficulty)};border-color:${diffColor(kw.difficulty)}40">
                ${kw.difficulty === 'Lav' ? '✅' : kw.difficulty === 'Medium' ? '⚡' : '🔥'} ${kw.difficulty||'?'} konkurrence
              </span>
            </div>
            <div class="rzpa-kw-action">${kw.action||''}</div>
          </div>`;
        }).join('')}
      </div>`;

    // Force-refresh knap
    document.getElementById('rzpa-kw-force-refresh')?.addEventListener('click', () => loadKeywordSuggestions(true));
  }

  // ══ PDF KNAPPER – BETALINGSHISTORIK ════════════════════════════════════════

  // Meta invoice PDF
  function initMetaInvoicePDF() {
    el('meta-invoices-pdf')?.addEventListener('click', () => {
      downloadInvoicePDF(window._metaInvoiceData||[], 'Meta Ads', window._metaInvoiceData?.[0]?.currency||'DKK');
    });
  }

  // Google Ads invoice PDF
  function initGadsInvoicePDF() {
    el('gads-invoices-pdf')?.addEventListener('click', () => {
      downloadInvoicePDF(window._gadsInvoiceData||[], 'Google Ads', 'DKK');
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
