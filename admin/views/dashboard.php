<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="dashboard">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Marketing Overblik</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Dit Marketing Overblik</h1>
      <p class="page-sub">Her ser du alt på ét sted — hvad dine annoncer koster, og hvor mange der finder jer på Google</p>
      <div id="rzpa-sync-status" class="rzpa-sync-status"></div>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-btn" class="btn-ghost">⟳ Opdater</button>
    </div>
  </div>

  <!-- Fortæller-kort (udfyldes af JS) -->
  <div id="rzpa-dashboard-story" class="rzpa-story hidden"></div>

  <!-- 4 hoved-KPI -->
  <div class="rzpa-kpi-grid" style="margin-bottom:18px">
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">💸 Samlet annonceforbrug</div>
      <div class="rzpa-kpi-value" id="kpi_spend">–</div>
      <div class="rzpa-kpi-sub" id="kpi_spend_sub">kr brugt på alle platforme</div>
    </div>
    <div class="rzpa-kpi color-purple">
      <div class="rzpa-kpi-label">🔍 Besøg fra Google</div>
      <div class="rzpa-kpi-value" id="kpi_seo_clicks">–</div>
      <div class="rzpa-kpi-sub" id="kpi_seo_clicks_sub">Folk der finder jer gratis</div>
    </div>
    <div class="rzpa-kpi color-orange">
      <div class="rzpa-kpi-label">🤖 Google AI-omtaler</div>
      <div class="rzpa-kpi-value" id="kpi_ai">–</div>
      <div class="rzpa-kpi-sub" id="kpi_ai_sub">Gange Googles AI nævner Rezponz</div>
    </div>
    <div class="rzpa-kpi color-blue">
      <div class="rzpa-kpi-label">📣 Aktive kampagner</div>
      <div class="rzpa-kpi-value" id="kpi_campaigns">–</div>
      <div class="rzpa-kpi-sub">Annoncer der kører lige nu</div>
    </div>
  </div>

  <!-- Platform status-kort -->
  <div class="rzpa-plat-grid">
    <div class="rzpa-plat-card">
      <div class="pc-head">
        <div class="pc-title">📘 Facebook & Instagram</div>
        <span id="meta-plat-status" class="rzpa-pill neutral">Ikke synkroniseret</span>
      </div>
      <div class="pc-big" id="roi_meta_spend">–</div>
      <div class="pc-desc">brugt på Meta Ads i perioden</div>
      <div class="pc-row"><span>Klikprocent</span><strong id="roi_meta_roas">–</strong></div>
      <div class="pc-row"><span>Klik til sitet</span><strong id="meta-plat-clicks">–</strong></div>
    </div>
    <div class="rzpa-plat-card">
      <div class="pc-head">
        <div class="pc-title">🔍 Google (SEO)</div>
        <span id="seo-plat-status" class="rzpa-pill neutral">Organisk trafik</span>
      </div>
      <div class="pc-big" id="seo-plat-clicks">–</div>
      <div class="pc-desc">klik fra Google-søgninger</div>
      <div class="pc-row"><span>Søgeord på side 1</span><strong id="seo-plat-top10">–</strong></div>
      <div class="pc-row"><span>Søgeord i top 3</span><strong id="seo-plat-top3">–</strong></div>
    </div>
    <div class="rzpa-plat-card">
      <div class="pc-head">
        <div class="pc-title">🤖 Google AI</div>
        <span id="ai-plat-status" class="rzpa-pill neutral">AI-omtaler</span>
      </div>
      <div class="pc-big" id="ai-plat-count">–</div>
      <div class="pc-desc">gange vist i Googles AI-svar</div>
      <div class="pc-row"><span>Fremhævede svar</span><strong id="ai-plat-snippets">–</strong></div>
      <div class="pc-row"><span>Relaterede spørgsmål</span><strong id="ai-plat-paa">–</strong></div>
    </div>
  </div>

  <!-- Skjult ROI forklaringsboks (bruges stadig af JS) -->
  <div class="rzpa-explain-bar" id="rzpa-roas-explain" style="display:none">
    <span class="explain-icon">💡</span>
    <span id="rzpa-roas-explain-text"></span>
  </div>

  <!-- Dagligt forbrug -->
  <div class="rzpa-chart-wrap">
    <div class="rzpa-chart-title">📈 Dagligt annonceforbrug</div>
    <div class="rzpa-chart-sub">Blå = Facebook/Instagram · Gul = Snapchat · Rød = TikTok</div>
    <div style="height:220px;position:relative"><canvas id="chart_trends"></canvas></div>
  </div>

  <!-- SEO søgeord + Top kampagner -->
  <div class="rzpa-chart-grid-wide">
    <div class="rzpa-chart-wrap" style="margin-bottom:0">
      <div class="rzpa-chart-title">🔍 Top søgeord på Google</div>
      <div class="rzpa-chart-sub">De ord folk søger på og finder jer med</div>
      <div style="height:200px;position:relative"><canvas id="chart_seo"></canvas></div>
    </div>
    <div class="rzpa-card" style="margin-bottom:0">
      <h2>🏆 Dine bedste kampagner</h2>
      <div class="rzpa-card-sub">Kampagner med flest klik til jeres hjemmeside</div>
      <div class="rzpa-table-wrap">
        <table class="rzpa-table">
          <thead><tr>
            <th>Kampagne</th>
            <th>Platform</th>
            <th>Brugt</th>
            <th>Klikprocent</th>
          </tr></thead>
          <tbody id="top_campaigns_tbody">
            <tr><td colspan="4" class="rzpa-loading">Indlæser…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
