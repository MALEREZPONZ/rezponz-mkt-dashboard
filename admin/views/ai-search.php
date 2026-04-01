<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="ai">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">AI Synlighed</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>AI-søgemaskine Synlighed</h1>
      <p class="page-sub">Tracking af Google AI Overviews, Featured Snippets og ChatGPT/Perplexity nævnelser</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-ai-sync" class="btn-ghost">Sync SerpAPI</button>
    </div>
  </div>

  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">AI Overview synlighed</div>
      <div class="rzpa-kpi-value" id="kpi_ai_ov">–</div>
      <div class="rzpa-kpi-sub">søgeord med AI Overview</div>
    </div>
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">Featured Snippets</div>
      <div class="rzpa-kpi-value color-blue" id="kpi_snippets">–</div>
      <div class="rzpa-kpi-sub">søgeord med snippet</div>
    </div>
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">People Also Ask</div>
      <div class="rzpa-kpi-value color-purple" id="kpi_paa">–</div>
      <div class="rzpa-kpi-sub">søgeord med PAA</div>
    </div>
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">Nævnt i AI-svar</div>
      <div class="rzpa-kpi-value color-orange" id="kpi_mentioned">–</div>
      <div class="rzpa-kpi-sub" id="kpi_mentioned_sub">af loggede forespørgsler</div>
    </div>
  </div>

  <div class="ai-main-grid">

    <div class="rzpa-card" style="padding:0;overflow:hidden">
      <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div>
          <h2 style="margin:0;font-size:15px">Søgeord status</h2>
          <div class="rzpa-card-sub" style="margin-top:2px">Seneste check pr. søgeord</div>
        </div>
        <span id="ai-kw-count" style="font-size:12px;color:#555"></span>
      </div>
      <div style="overflow-x:auto">
        <table class="rzpa-table" style="margin:0">
          <thead><tr>
            <th style="padding-left:20px">Søgeord</th>
            <th style="text-align:center">AI Overview</th>
            <th style="text-align:center">Snippet</th>
            <th style="text-align:center">PAA</th>
            <th>Seneste tjek</th>
            <th>Kilde</th>
          </tr></thead>
          <tbody id="ai-kw-tbody">
            <tr><td colspan="6" class="rzpa-loading" style="padding:24px 20px">Indlæser søgeord…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">

      <div class="rzpa-card" style="text-align:center">
        <h2 style="margin:0 0 4px;font-size:13px;color:#666;font-weight:500;text-transform:uppercase;letter-spacing:.05em">AI-synlighedsscore</h2>
        <div id="ai-score-value" style="font-size:56px;font-weight:800;line-height:1;margin:10px 0 2px;color:var(--neon)">–</div>
        <div id="ai-score-label" style="font-size:12px;color:#555;margin-bottom:14px">af dine søgeord har AI-synlighed</div>
        <div style="height:8px;background:rgba(255,255,255,.07);border-radius:4px;overflow:hidden">
          <div id="ai-score-bar" style="height:100%;border-radius:4px;background:var(--neon);transition:width .6s;width:0%"></div>
        </div>
      </div>

      <div class="rzpa-card" style="flex:1">
        <h2 style="margin:0 0 14px;font-size:14px">💡 Optimeringsindsatser</h2>
        <div id="ai-tips" style="display:flex;flex-direction:column;gap:10px">
          <div class="rzpa-loading" style="font-size:13px">Beregner…</div>
        </div>
      </div>

    </div>
  </div>

  <div class="rzpa-card" style="margin-top:16px">
    <div class="rzpa-chart-title">AI Overview detektioner over tid</div>
    <div style="height:180px"><canvas id="chart_ai_ov"></canvas></div>
  </div>

  <div class="rzpa-card" id="ai-overview-texts" style="display:none;margin-top:16px">
    <h2 style="margin:0 0 14px;font-size:15px">📄 AI Overview tekst</h2>
    <div id="ai-overview-texts-inner" style="display:flex;flex-direction:column;gap:12px"></div>
  </div>

  <div class="rzpa-card" style="margin-top:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div>
        <h2 style="margin:0">Manuel AI-svar log</h2>
        <div class="rzpa-card-sub">Log hvornår Rezponz nævnes i ChatGPT, Perplexity osv.</div>
      </div>
      <button id="rzpa-log-toggle" class="btn-primary">+ Tilføj log</button>
    </div>
    <div id="rzpa-log-form" class="rzpa-form" style="display:none">
      <div class="rzpa-form-grid">
        <div class="rzpa-field">
          <label>Platform</label>
          <select id="log_platform">
            <option>ChatGPT</option><option>Perplexity</option><option>Gemini</option>
            <option>Claude</option><option>Copilot</option>
          </select>
        </div>
        <div class="rzpa-field">
          <label>Søgeforespørgsel *</label>
          <input type="text" id="log_query" placeholder="Hvad spurgte du om?" />
        </div>
      </div>
      <div class="rzpa-field" style="margin-bottom:12px">
        <label>AI-svar (uddrag)</label>
        <textarea id="log_response" placeholder="Indsæt relevant del af AI-svaret…"></textarea>
      </div>
      <div style="display:flex;gap:20px;align-items:center;margin-bottom:14px">
        <label class="rzpa-checkbox">
          <input type="checkbox" id="log_mentioned" />
          Rezponz nævnt i svaret
        </label>
        <div class="rzpa-field" style="flex:1;margin:0">
          <input type="text" id="log_notes" placeholder="Noter…" />
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button id="rzpa-log-submit" class="btn-primary">Gem log</button>
        <button onclick="document.getElementById('rzpa-log-form').style.display='none'" class="btn-ghost">Annuller</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Dato</th><th>Platform</th><th>Forespørgsel</th><th>Nævnt</th><th>Noter</th><th></th>
        </tr></thead>
        <tbody id="ai_log_tbody"><tr><td colspan="6" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>
