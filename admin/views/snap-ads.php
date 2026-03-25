<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="snap">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Snapchat Ads</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Snapchat Ads</h1>
      <p class="page-sub">Snapchat Marketing API · Swipe-ups & Konverteringer</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-snap" class="btn-ghost">Sync</button>
    </div>
  </div>

  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Samlet Spend</div><div class="rzpa-kpi-value" id="kpi_spend">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Swipe-ups</div><div class="rzpa-kpi-value color-blue" id="kpi_swipes">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Impressions</div><div class="rzpa-kpi-value color-purple" id="kpi_impr">–</div></div>
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Gns. Engagement</div><div class="rzpa-kpi-value color-orange" id="kpi_engagement">–</div></div>
  </div>

  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Spend per kampagne</div>
      <div style="height:200px"><canvas id="chart_spend"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Engagement Rate %</div>
      <div style="height:200px"><canvas id="chart_engagement"></canvas></div>
    </div>
  </div>

  <div class="rzpa-card">
    <h2>Alle kampagner</h2>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Kampagne</th><th>Status</th><th>Spend</th><th>Impressions</th>
          <th>Swipe-ups</th><th>Konverteringer</th><th>CPM</th><th>Engagement</th>
        </tr></thead>
        <tbody id="snap_tbody"><tr><td colspan="8" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>
