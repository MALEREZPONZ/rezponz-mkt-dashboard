<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="seo">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">SEO Performance</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>SEO Performance</h1>
      <p class="page-sub">Google Search Console · Søgeord, sider og positionsudvikling</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button onclick="fetch(RZPA.apiBase+'/seo/sync',{method:'POST',headers:{'X-WP-Nonce':RZPA.nonce}}).then(()=>location.reload())" class="btn-ghost">⟳ Sync nu</button>
    </div>
  </div>

  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi"><div class="rzpa-kpi-label">Organiske klik</div><div class="rzpa-kpi-value" id="kpi_clicks">–</div><div class="rzpa-kpi-sub" id="kpi_clicks_sub"></div></div>
    <div class="rzpa-kpi color-blue"><div class="rzpa-kpi-label">Impressions</div><div class="rzpa-kpi-value" id="kpi_impr">–</div></div>
    <div class="rzpa-kpi color-purple"><div class="rzpa-kpi-label">Gns. CTR</div><div class="rzpa-kpi-value" id="kpi_ctr">–</div></div>
    <div class="rzpa-kpi color-orange"><div class="rzpa-kpi-label">Søgeord i top 10</div><div class="rzpa-kpi-value" id="kpi_top10">–</div><div class="rzpa-kpi-sub" id="kpi_top3_sub"></div></div>
  </div>

  <!-- Charts: top keywords + position trend -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Top søgeord – organiske klik</div>
      <div class="rzpa-chart-sub">De 8 søgeord med flest klik</div>
      <div style="height:220px"><canvas id="chart_kw_clicks"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title" id="trend_title">Positionsudvikling</div>
      <div class="rzpa-chart-sub">Klik på et søgeord i tabellen for at se trend</div>
      <div style="height:220px"><canvas id="chart_kw_trend"></canvas></div>
    </div>
  </div>

  <!-- Top pages section -->
  <div class="rzpa-card" style="margin-bottom:20px">
    <h2>Top sider – organisk trafik</h2>
    <div class="rzpa-card-sub">Hvilke URL'er modtager mest organisk trafik fra Google</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>URL</th>
          <th>Gns. Position</th>
          <th>Klik</th>
          <th>Impressions</th>
          <th>CTR</th>
        </tr></thead>
        <tbody id="seo_pages_tbody"><tr><td colspan="5" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- Opportunities: position 11-20 -->
  <div class="rzpa-card" style="margin-bottom:20px">
    <h2>Muligheder – tæt på side 1</h2>
    <div class="rzpa-card-sub">Søgeord der rangerer #11–20 og kan løftes til top 10 med målrettet indsats</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Søgeord</th>
          <th>Position</th>
          <th>Impressions</th>
          <th>CTR</th>
        </tr></thead>
        <tbody id="seo_opportunities_tbody"><tr><td colspan="4" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- All keywords -->
  <div class="rzpa-card">
    <h2>Alle søgeord</h2>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th data-sort="keyword">Søgeord</th>
          <th data-sort="avg_position">Position ↕</th>
          <th data-sort="total_clicks">Klik ↕</th>
          <th data-sort="total_impressions">Impressions ↕</th>
          <th data-sort="avg_ctr">CTR ↕</th>
        </tr></thead>
        <tbody id="seo_tbody"><tr><td colspan="5" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>
