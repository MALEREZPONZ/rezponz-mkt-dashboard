<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="meta">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Meta Ads</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Meta Annoncer</h1>
      <p class="page-sub">Facebook + Instagram · Hvad har du brugt og hvad har du fået retur?</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-meta" class="btn-ghost">⟳ Hent data</button>
    </div>
  </div>

  <!-- KPI-tal øverst -->
  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">Samlet forbrug</div>
      <div class="rzpa-kpi-value" id="kpi_spend">–</div>
      <div class="rzpa-kpi-sub" id="kpi_spend_sub">Hvad du har brugt på annoncer</div>
    </div>
    <div class="rzpa-kpi color-blue">
      <div class="rzpa-kpi-label">Afkast (ROAS)</div>
      <div class="rzpa-kpi-value" id="kpi_roas">–</div>
      <div class="rzpa-kpi-sub" id="kpi_roas_sub">Omsætning per krone brugt</div>
    </div>
    <div class="rzpa-kpi color-purple">
      <div class="rzpa-kpi-label">Visninger</div>
      <div class="rzpa-kpi-value" id="kpi_impr">–</div>
      <div class="rzpa-kpi-sub">Gange dine annoncer er blevet vist</div>
    </div>
    <div class="rzpa-kpi color-orange">
      <div class="rzpa-kpi-label">Klik</div>
      <div class="rzpa-kpi-value" id="kpi_clicks">–</div>
      <div class="rzpa-kpi-sub" id="kpi_cpc_sub">Folk der klikkede på din annonce</div>
    </div>
  </div>

  <!-- Forklaringsboks ROAS -->
  <div class="rzpa-explain-bar" id="meta-roas-explain" style="display:none">
    <span class="explain-icon">💡</span>
    <span id="meta-roas-text"></span>
  </div>

  <!-- Grafer -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Forbrug per kampagne</div>
      <div class="rzpa-chart-sub">Hvor mange kr er brugt per kampagne (DKK)</div>
      <div style="height:200px"><canvas id="chart_spend"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Afkast per kampagne (ROAS)</div>
      <div class="rzpa-chart-sub">Grøn ≥ 2,5x er godt · Rød under 1x taber penge</div>
      <div style="height:200px"><canvas id="chart_roas"></canvas></div>
    </div>
  </div>

  <!-- Alle kampagner -->
  <div class="rzpa-card">
    <h2>Alle kampagner</h2>
    <div class="rzpa-card-sub">Klik på en kampagne for at se detaljer · Grønt afkast = pengene tjener sig hjem</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Kampagnenavn</th>
          <th>Status</th>
          <th>Forbrug (kr)</th>
          <th>Visninger</th>
          <th>Rækkevidde</th>
          <th>Klik</th>
          <th>Pris per 1000 vist</th>
          <th>Pris per klik</th>
          <th>Afkast (ROAS)</th>
        </tr></thead>
        <tbody id="meta_tbody"><tr><td colspan="9" class="rzpa-loading">Henter annonce-data…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>
