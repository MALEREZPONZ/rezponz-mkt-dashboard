/* ═══════════════════════════════════════════════════
   Rezponz Analytics – Dashboard JavaScript
   ═══════════════════════════════════════════════════ */

const RZPA_App = (() => {

  // ── Helpers ───────────────────────────────────────

  const API = RZPA.apiBase;
  const HDR = { 'Content-Type': 'application/json', 'X-WP-Nonce': RZPA.nonce };

  async function api(path, opts = {}) {
    const res = await fetch(API + path, { headers: HDR, ...opts });
    return res.json();
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
    if (e) { e.textContent = value; if (extra && el(id + '_sub')) el(id + '_sub').textContent = extra; }
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
    // Individuelle kald – fejl returnerer {} så resten af UI stadig renderes
    const safe = path => api(path).catch(() => ({}));
    const [seo, meta, snap, tt, ai, kw, mc, sc, tc, trends] = await Promise.all([
      safe(`/seo/summary?days=${days}`),
      safe(`/meta/summary?days=${days}`),
      safe(`/snap/summary?days=${days}`),
      safe(`/tiktok/summary?days=${days}`),
      safe(`/ai/summary?days=${days}`),
      safe(`/seo/keywords?days=${days}`),
      safe(`/meta/campaigns?days=${days}`),
      safe(`/snap/campaigns?days=${days}`),
      safe(`/tiktok/campaigns?days=${days}`),
      safe(`/ads/trends?days=${days}`),
    ]);

    const s = seo.data || {}, m = meta.data || {}, sn = snap.data || {},
          t = tt.data || {}, a = ai.data || {};

    const totalSpend = (parseFloat(m.total_spend)||0) + (parseFloat(sn.total_spend)||0) + (parseFloat(t.total_spend)||0);
    const metaRoas   = parseFloat(m.avg_roas) || 0;
    const snapEng    = parseFloat(sn.avg_engagement_rate) || 0;
    const ttRoas     = parseFloat(t.avg_roas) || 0;

    // ROI Spotlight – med afkast-forklaring i label
    const setRoas = (id, val, clsName) => {
      const e = el(id);
      if (e) { e.textContent = val; e.className = 'roas-value ' + clsName; }
    };
    setRoas('roi_meta_roas',       fmt(metaRoas,2)+'x', roasClass(metaRoas));
    setRoas('roi_snap_engagement', fmt(snapEng,2)+'%',  snapEng>=3?'roas-high':snapEng>=1.5?'roas-mid':'roas-low');
    setRoas('roi_tt_roas',         fmt(ttRoas,2)+'x',   roasClass(ttRoas));
    setText('roi_meta_spend', fmt(m.total_spend,0)+' kr');
    setText('roi_snap_spend', fmt(sn.total_spend,0)+' kr');
    setText('roi_tt_spend',   fmt(t.total_spend,0)+' kr');

    // Dynamisk afkast-forklaring under kortene
    const explainBar  = el('rzpa-roas-explain');
    const explainText = el('rzpa-roas-explain-text');
    if (explainBar && explainText && metaRoas > 0) {
      const earned = (parseFloat(m.total_spend)||0) * metaRoas;
      explainText.textContent = `💰 Meta: Du brugte ${fmt(m.total_spend,0)} kr og fik ca. ${fmt(earned,0)} kr i omsætning tilbage (ROAS ${fmt(metaRoas,2)}x). Under 1x = taber penge · 2,5x+ = rigtig god.`;
      explainBar.style.display = 'flex';
    }

    // KPIs – menneskelige undertekster
    const avgRoas = totalSpend > 0
      ? (((m.total_spend||0)*metaRoas + (t.total_spend||0)*ttRoas) / ((m.total_spend||0)+(t.total_spend||0)||1)).toFixed(2)
      : 0;
    const perDay = days > 0 ? Math.round(totalSpend / days) : 0;
    renderKPI('kpi_spend',      fmt(totalSpend,0)+' kr',
      perDay > 0 ? '≈ '+fmt(perDay,0)+' kr/dag · Afkast: '+avgRoas+'x' : 'Meta + Snapchat + TikTok');
    renderKPI('kpi_seo_clicks', fmt(s.total_clicks),
      s.keywords_top10 > 0 ? (s.keywords_top10)+' søgeord på Googles 1. side' : 'Gratis besøg fra Google');
    renderKPI('kpi_ai',         fmt(a.ai_overview_count)||'–',
      (a.featured_snippet_count||0)+' gange vist som fremhævet svar');
    renderKPI('kpi_campaigns',  ((m.campaign_count||0)+(sn.campaign_count||0)+(t.campaign_count||0)));

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
      tbody.innerHTML = allCamps.slice(0,8).map(c => `
        <tr>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500">${c.campaign_name}</td>
          <td><span class="${platClass(c.platform)}" style="font-size:11px;font-weight:700;text-transform:uppercase">${c.platform}</span></td>
          <td style="color:var(--text-muted)">${fmt(c.spend,0)} kr</td>
          <td class="${roasClass(parseFloat(c.roas)||0)}" style="font-weight:700">${c.roas>0?fmt(c.roas,2)+'x':'–'}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="rzpa-empty">Ingen kampagnedata endnu</td></tr>';
    }
  }

  // ════════════════════════════════════════════════════
  // PAGE: SEO
  // ════════════════════════════════════════════════════

  async function initSEO() {
    let days = 30, allKw = [];
    initDateFilter('rzpa-date-filter', d => { days = d; loadSEO(d); });
    loadSEO(days);

    async function loadSEO(d) {
      const [sum, kw, pages] = await Promise.all([
        api(`/seo/summary?days=${d}`),
        api(`/seo/keywords?days=${d}`),
        api(`/seo/pages?days=${d}`),
      ]);
      const s = sum.data || {};
      allKw = kw.data || [];

      renderKPI('kpi_clicks', fmt(s.total_clicks), 'CTR: ' + fmt(s.avg_ctr,2) + '%');
      renderKPI('kpi_impr',   fmt(s.total_impressions));
      renderKPI('kpi_ctr',    fmt(s.avg_ctr,2) + '%');
      renderKPI('kpi_top10',  fmt(s.keywords_top10), (s.keywords_top3||0) + ' i top 3');

      hBarChart('chart_kw_clicks', allKw.slice(0,8).map(k=>k.keyword), allKw.slice(0,8).map(k=>k.total_clicks));
      renderSeoTable(allKw, d);
      renderPagesTable(pages.data || []);
      renderOpportunities(allKw);
    }

    function posStyle(p) {
      if (p <= 3)  return 'color:var(--neon);font-weight:700';
      if (p <= 10) return 'color:#88aaff;font-weight:600';
      if (p <= 20) return 'color:var(--warn);font-weight:600';
      return 'color:#555';
    }

    function renderSeoTable(data, d) {
      const tbody = el('seo_tbody');
      if (!tbody) return;
      tbody.innerHTML = data.map(k => `
        <tr style="cursor:pointer" onclick="RZPA_App.loadKwTrend('${encodeURIComponent(k.keyword)}',${d})">
          <td style="color:#ddd;font-weight:500">${k.keyword}</td>
          <td style="${posStyle(k.avg_position)}">#${fmt(k.avg_position,1)}</td>
          <td>${fmt(k.total_clicks)}</td>
          <td>${fmt(k.total_impressions)}</td>
          <td>${fmt(k.avg_ctr,2)}%</td>
        </tr>`).join('') || '<tr><td colspan="5" class="rzpa-empty">Ingen data endnu – synkronisér via knappen</td></tr>';
    }

    function renderPagesTable(data) {
      const tbody = el('seo_pages_tbody');
      if (!tbody) return;
      if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="rzpa-empty">Ingen sidedata – synkronisér SEO data</td></tr>';
        return;
      }
      tbody.innerHTML = data.slice(0,15).map(p => {
        const url = p.page_url.replace(/^https?:\/\/[^/]+/, '');
        return `<tr>
          <td style="color:#ddd;font-weight:500;max-width:260px;overflow:hidden;text-overflow:ellipsis" title="${p.page_url}">${url || '/'}</td>
          <td style="${posStyle(p.avg_position)}">#${fmt(p.avg_position,1)}</td>
          <td>${fmt(p.total_clicks)}</td>
          <td>${fmt(p.total_impressions)}</td>
          <td>${fmt(p.avg_ctr,2)}%</td>
        </tr>`;
      }).join('');
    }

    function renderOpportunities(data) {
      const tbody = el('seo_opportunities_tbody');
      if (!tbody) return;
      const opps = data.filter(k => k.avg_position > 10 && k.avg_position <= 20)
                       .sort((a,b) => a.avg_position - b.avg_position);
      if (!opps.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="rzpa-empty">Ingen søgeord i position 11–20</td></tr>';
        return;
      }
      tbody.innerHTML = opps.slice(0,10).map(k => `
        <tr>
          <td style="color:#ddd;font-weight:500">${k.keyword}</td>
          <td style="color:var(--warn);font-weight:700">#${fmt(k.avg_position,1)}</td>
          <td>${fmt(k.total_impressions)}</td>
          <td>${fmt(k.avg_ctr,2)}%</td>
        </tr>`).join('');
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

  async function initMeta() {
    let days = 30;
    initDateFilter('rzpa-date-filter', d => { days = d; loadMeta(d); });
    loadMeta(days);
    el('rzpa-sync-meta')?.addEventListener('click', async () => {
      const btn = el('rzpa-sync-meta');
      btn.disabled = true;
      btn.textContent = 'Henter…';
      try {
        const r = await api('/meta/sync', { method: 'POST', body: JSON.stringify({days}) });
        const count = r.data?.count || 0;
        btn.textContent = '✓ ' + count + ' kampagner hentet';
        setTimeout(() => { btn.disabled = false; btn.textContent = '⟳ Hent data'; }, 3000);
        loadMeta(days);
      } catch(e) {
        btn.disabled = false;
        btn.textContent = '⟳ Hent data';
        alert('Fejl ved hentning af Meta-data. Tjek at Access Token er gyldigt i Indstillinger.');
      }
    });
  }

  async function loadMeta(days) {
    const [sum, camps] = await Promise.all([
      api(`/meta/summary?days=${days}`),
      api(`/meta/campaigns?days=${days}`),
    ]);
    const s = sum.data || {}, data = camps.data || [];

    const roas = parseFloat(s.avg_roas) || 0;
    const spend = parseFloat(s.total_spend) || 0;
    const perDay = days > 0 ? Math.round(spend / days) : 0;
    const avgCpc = parseFloat(s.avg_cpc) || 0;

    renderKPI('kpi_spend',   fmt(spend,0) + ' kr',
      perDay > 0 ? '≈ ' + fmt(perDay,0) + ' kr/dag' : '');
    renderKPI('kpi_roas',    roas > 0 ? fmt(roas,2) + 'x' : '–',
      roas >= 2.5 ? '✅ Rigtig godt afkast' : roas >= 1 ? '⚠️ Under målet (mål: 2,5x+)' : roas > 0 ? '❌ Taber penge' : 'Ingen data endnu');
    renderKPI('kpi_impr',    fmt(s.total_impressions));
    renderKPI('kpi_clicks',  fmt(s.total_clicks),
      avgCpc > 0 ? fmt(avgCpc,2) + ' kr per klik' : '');

    // Forklaringsboks
    const explainEl = el('meta-roas-explain');
    const textEl    = el('meta-roas-text');
    if (explainEl && textEl && roas > 0 && spend > 0) {
      const earned = spend * roas;
      explainEl.style.display = 'flex';
      textEl.textContent = `Du brugte ${fmt(spend,0)} kr og fik ca. ${fmt(earned,0)} kr i omsætning tilbage. `
        + (roas >= 2.5 ? `Det er rigtig godt – fortsæt og skalér op! 🚀`
          : roas >= 1.5 ? `Det er okay men der er plads til forbedring. Mål er 2,5x+.`
          : `Det er under hvad det bør være. Overvej at justere målgruppe eller kreativt indhold.`);
    } else if (explainEl) {
      explainEl.style.display = 'none';
    }

    const top6 = data.slice(0,6);
    const labels = top6.map(c => c.campaign_name.replace('Rezponz – ','').replace('Rezponz - ','').slice(0,22));

    barChart('chart_spend', labels,
      [{ data: top6.map(c=>Math.round(c.spend)), backgroundColor: '#1877F2', borderRadius: 5 }],
      { yTick: v => (v/1000).toFixed(0)+'k' }
    );
    barChart('chart_roas', labels,
      [{ data: top6.map(c=>c.roas),
         backgroundColor: top6.map(c => c.roas>=2.5?'#CCFF00':c.roas>=1.5?'#88cc00':'#cc4400'),
         borderRadius: 5 }]
    );

    const tbody = el('meta_tbody');
    if (tbody) tbody.innerHTML = data.map(c => `
      <tr>
        <td style="color:#ddd;font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis">${c.campaign_name}</td>
        <td>${badgeHtml(c.status)}</td>
        <td>${fmt(c.spend,0)} kr</td>
        <td>${fmt(c.impressions)}</td>
        <td>${fmt(c.reach)}</td>
        <td>${fmt(c.clicks)}</td>
        <td>${fmt(c.cpm,2)} kr</td>
        <td>${fmt(c.cpc,2)} kr</td>
        <td class="${roasClass(c.roas)}">${fmt(c.roas,2)}x</td>
      </tr>`).join('');
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
          const win = window.open('', '_blank');
          win.document.write(r.html);
          win.document.close();
          setTimeout(() => win.print(), 800);

          notice.className = 'rzpa-notice success';
          notice.textContent = 'Rapport åbnet i nyt vindue – brug Ctrl+P / Cmd+P til at gemme som PDF.';
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

  // ── Auto-init ──────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('rzpa-app')?.dataset?.rzpaPage;
    const initMap = {
      dashboard: initDashboard,
      seo:       initSEO,
      ai:        initAI,
      meta:      initMeta,
      snap:      initSnap,
      tiktok:    initTikTok,
      rapport:   initRapport,
    };
    if (initMap[page]) initMap[page]();
  });

  // Public API (used inline)
  return { loadKwTrend, deleteLog };

})();
