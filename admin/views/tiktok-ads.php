<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="tiktok">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">TikTok Ads</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>TikTok Ads</h1>
      <p class="page-sub">TikTok for Business API · Video Performance</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-tiktok" class="btn-ghost">Sync</button>
    </div>
  </div>

  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Samlet Spend</div><div class="rzpa-kpi-value" id="kpi_spend">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Video Views</div><div class="rzpa-kpi-value color-red" id="kpi_views">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Gns. ROAS</div><div class="rzpa-kpi-value color-blue" id="kpi_roas">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Klik</div><div class="rzpa-kpi-value color-purple" id="kpi_clicks">–</div></div>
  </div>

  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Video Views per kampagne</div>
      <div style="height:200px"><canvas id="chart_views"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">ROAS per kampagne</div>
      <div style="height:200px"><canvas id="chart_roas"></canvas></div>
    </div>
  </div>

  <div class="rzpa-card">
    <h2>Alle TikTok kampagner</h2>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Kampagne</th><th>Status</th><th>Spend</th><th>Video Views</th>
          <th>Klik</th><th>Konv.</th><th>ROAS</th><th>Cost/View</th>
        </tr></thead>
        <tbody id="tiktok_tbody"><tr><td colspan="8" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>
