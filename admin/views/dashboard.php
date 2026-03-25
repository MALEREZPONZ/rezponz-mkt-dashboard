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
      <p class="page-sub">Ad spend effektivitet og ROI på tværs af alle platforme</p>
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

  <!-- KPI grid – 4 tal øverst -->
  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">Samlet Ad Spend</div>
      <div class="rzpa-kpi-value" id="kpi_spend">–</div>
      <div class="rzpa-kpi-sub" id="kpi_spend_sub"></div>
    </div>
    <div class="rzpa-kpi color-purple">
      <div class="rzpa-kpi-label">Organiske klik</div>
      <div class="rzpa-kpi-value" id="kpi_seo_clicks">–</div>
      <div class="rzpa-kpi-sub" id="kpi_seo_clicks_sub"></div>
    </div>
    <div class="rzpa-kpi color-orange">
      <div class="rzpa-kpi-label">AI Overviews</div>
      <div class="rzpa-kpi-value" id="kpi_ai">–</div>
      <div class="rzpa-kpi-sub" id="kpi_ai_sub"></div>
    </div>
    <div class="rzpa-kpi color-blue">
      <div class="rzpa-kpi-label">Aktive kampagner</div>
      <div class="rzpa-kpi-value" id="kpi_campaigns">–</div>
    </div>
  </div>

  <!-- ROI Platform cards – horisontale rækker -->
  <div class="rzpa-roi-bar">
    <div class="rzpa-roi-card">
      <div class="roi-icon">📘</div>
      <div class="roi-info">
        <div class="platform-name">Meta Ads</div>
        <div class="roas-value roas-high" id="roi_meta_roas">–</div>
        <div class="roas-label">ROAS</div>
        <div class="spend-row">
          <span>Spend:</span>
          <strong id="roi_meta_spend">–</strong>
        </div>
      </div>
    </div>
    <div class="rzpa-roi-card">
      <div class="roi-icon">👻</div>
      <div class="roi-info">
        <div class="platform-name">Snapchat Ads</div>
        <div class="roas-value roas-mid" id="roi_snap_engagement">–</div>
        <div class="roas-label">Engagement Rate</div>
        <div class="spend-row">
          <span>Spend:</span>
          <strong id="roi_snap_spend">–</strong>
        </div>
      </div>
    </div>
    <div class="rzpa-roi-card">
      <div class="roi-icon">🎵</div>
      <div class="roi-info">
        <div class="platform-name">TikTok Ads</div>
        <div class="roas-value roas-high" id="roi_tt_roas">–</div>
        <div class="roas-label">ROAS</div>
        <div class="spend-row">
          <span>Spend:</span>
          <strong id="roi_tt_spend">–</strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Spend & ROAS over tid -->
  <div class="rzpa-chart-wrap">
    <div class="rzpa-chart-title">Spend & ROAS over tid</div>
    <div class="rzpa-chart-sub">Dagligt ad spend per platform (stacked) + samlet ROAS-trend</div>
    <div style="height:260px;position:relative"><canvas id="chart_trends"></canvas></div>
  </div>

  <!-- Bottom grid: SEO søgeord + Top kampagner -->
  <div class="rzpa-chart-grid-wide">
    <div class="rzpa-chart-wrap" style="margin-bottom:0">
      <div class="rzpa-chart-title">Top SEO Søgeord</div>
      <div class="rzpa-chart-sub">Organiske klik i perioden</div>
      <div style="height:200px;position:relative"><canvas id="chart_seo"></canvas></div>
    </div>
    <div class="rzpa-card" style="margin-bottom:0">
      <h2>Bedste kampagner</h2>
      <div class="rzpa-card-sub">Alle platforme · sorteret efter ROAS</div>
      <div class="rzpa-table-wrap">
        <table class="rzpa-table">
          <thead><tr>
            <th>Kampagne</th>
            <th>Platform</th>
            <th>Spend</th>
            <th>ROAS</th>
          </tr></thead>
          <tbody id="top_campaigns_tbody">
            <tr><td colspan="4" class="rzpa-loading">Indlæser…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
