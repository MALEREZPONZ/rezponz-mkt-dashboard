<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="ai">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">AI Synlighed</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>AI-søgemaskine Synlighed</h1>
      <p class="page-sub">Google AI Overviews + manuel ChatGPT/Perplexity tracking</p>
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
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">AI Overview visninger</div><div class="rzpa-kpi-value" id="kpi_ai_ov">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Featured Snippets</div><div class="rzpa-kpi-value color-blue" id="kpi_snippets">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">People Also Ask</div><div class="rzpa-kpi-value color-purple" id="kpi_paa">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Nævnt i AI-svar</div><div class="rzpa-kpi-value color-orange" id="kpi_mentioned">–</div><div class="rzpa-kpi-sub" id="kpi_mentioned_sub"></div></div>
  </div>

  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">AI Overview detektioner over tid</div>
      <div style="height:200px"><canvas id="chart_ai_ov"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Om AI-søgemaskine tracking</div>
      <div class="rzpa-chart-sub" style="margin-top:8px;line-height:1.7;font-size:13px;color:#777">
        Data hentes dagligt via SerpAPI for Rezponz' vigtigste søgeord.<br><br>
        <strong style="color:#aaa">Platforme der monitoreres:</strong><br>
        Google AI Overviews, Featured Snippets, People Also Ask<br><br>
        <strong style="color:#aaa">Manuel tracking:</strong><br>
        Log hvornår Rezponz nævnes i ChatGPT, Perplexity, Gemini m.fl.
      </div>
    </div>
  </div>

  <!-- Manual log form -->
  <div class="rzpa-card">
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
