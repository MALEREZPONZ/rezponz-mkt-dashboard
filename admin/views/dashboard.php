<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="dashboard">

  <!-- Logo bar -->
  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Marketing Intelligence</span>
  </div>

  <!-- Page header -->
  <div class="rzpa-header">
    <div>
      <h1>Marketing Overblik</h1>
      <p class="page-sub">Her ser du samlet status på alle dine annoncer og organisk søgetrafik</p>
      <div id="rzpa-sync-status" class="rzpa-sync-status"></div>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-btn" class="btn-ghost">⟳ Sync nu</button>
    </div>
  </div>

  <!-- KPI grid – 4 nøgletal øverst -->
  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">Samlet annonceforbrug</div>
      <div class="rzpa-kpi-value" id="kpi_spend">–</div>
      <div class="rzpa-kpi-sub" id="kpi_spend_sub">Meta + Snapchat + TikTok</div>
    </div>
    <div class="rzpa-kpi color-purple">
      <div class="rzpa-kpi-label">Besøg fra Google</div>
      <div class="rzpa-kpi-value" id="kpi_seo_clicks">–</div>
      <div class="rzpa-kpi-sub" id="kpi_seo_clicks_sub">Folk der klikker organisk</div>
    </div>
    <div class="rzpa-kpi color-orange">
      <div class="rzpa-kpi-label">Google AI-omtaler</div>
      <div class="rzpa-kpi-value" id="kpi_ai">–</div>
      <div class="rzpa-kpi-sub" id="kpi_ai_sub">Gange Rezponz nævnes af Googles AI</div>
    </div>
    <div class="rzpa-kpi color-blue">
      <div class="rzpa-kpi-label">Aktive annoncer</div>
      <div class="rzpa-kpi-value" id="kpi_campaigns">–</div>
      <div class="rzpa-kpi-sub">Kørende kampagner lige nu</div>
    </div>
  </div>

  <!-- ROI Platform cards -->
  <div class="rzpa-roi-bar">
    <div class="rzpa-roi-card">
      <div class="roi-icon">📘</div>
      <div class="roi-info">
        <div class="platform-name">Meta Ads <span style="font-size:10px;color:#555;font-weight:400">(Facebook + Instagram)</span></div>
        <div class="roas-value roas-high" id="roi_meta_roas">–</div>
        <div class="roas-label" id="roi_meta_label">Afkast per krone brugt (ROAS)</div>
        <div class="spend-row">
          <span>Brugt:</span>
          <strong id="roi_meta_spend">–</strong>
        </div>
      </div>
    </div>
    <div class="rzpa-roi-card">
      <div class="roi-icon">👻</div>
      <div class="roi-info">
        <div class="platform-name">Snapchat Ads</div>
        <div class="roas-value roas-mid" id="roi_snap_engagement">–</div>
        <div class="roas-label">Engagement – folk der interagerer</div>
        <div class="spend-row">
          <span>Brugt:</span>
          <strong id="roi_snap_spend">–</strong>
        </div>
      </div>
    </div>
    <div class="rzpa-roi-card">
      <div class="roi-icon">🎵</div>
      <div class="roi-info">
        <div class="platform-name">TikTok Ads</div>
        <div class="roas-value roas-high" id="roi_tt_roas">–</div>
        <div class="roas-label">Afkast per krone brugt (ROAS)</div>
        <div class="spend-row">
          <span>Brugt:</span>
          <strong id="roi_tt_spend">–</strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Forklaringsboks -->
  <div class="rzpa-explain-bar" id="rzpa-roas-explain" style="display:none">
    <span class="explain-icon">💡</span>
    <span id="rzpa-roas-explain-text"></span>
  </div>

  <!-- Spend & ROAS over tid -->
  <div class="rzpa-chart-wrap">
    <div class="rzpa-chart-title">Dagligt annonceforbrug & afkast</div>
    <div class="rzpa-chart-sub">Blå = Meta · Gul = Snapchat · Rød = TikTok · Grøn linje = samlet afkast (ROAS)</div>
    <div style="height:260px;position:relative"><canvas id="chart_trends"></canvas></div>
  </div>

  <!-- Bottom grid: SEO søgeord + Top kampagner -->
  <div class="rzpa-chart-grid-wide">
    <div class="rzpa-chart-wrap" style="margin-bottom:0">
      <div class="rzpa-chart-title">Top søgeord på Google</div>
      <div class="rzpa-chart-sub">De søgeord hvor flest klikker ind på rezponz.dk</div>
      <div style="height:200px;position:relative"><canvas id="chart_seo"></canvas></div>
    </div>
    <div class="rzpa-card" style="margin-bottom:0">
      <h2>Bedste annoncer lige nu</h2>
      <div class="rzpa-card-sub">Sorteret efter afkast – grøn = godt, rød = taber penge</div>
      <div class="rzpa-table-wrap">
        <table class="rzpa-table">
          <thead><tr>
            <th>Kampagne</th>
            <th>Platform</th>
            <th>Brugt</th>
            <th>Afkast</th>
          </tr></thead>
          <tbody id="top_campaigns_tbody">
            <tr><td colspan="4" class="rzpa-loading">Indlæser…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
