<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="meta">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Meta Annoncer</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Meta Annoncer</h1>
      <p class="page-sub">Facebook + Instagram · Hvad har du brugt, og hvor mange ser og klikker?</p>
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

  <!-- KPI kort -->
  <div class="rzpa-kpi-grid">
    <div class="rzpa-kpi">
      <div class="rzpa-kpi-label">Samlet forbrug</div>
      <div class="rzpa-kpi-value" id="kpi_spend">–</div>
      <div class="rzpa-kpi-sub" id="kpi_spend_sub">kr brugt på annoncer i perioden</div>
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
    <div class="rzpa-kpi color-blue">
      <div class="rzpa-kpi-label">Klikrate (CTR)</div>
      <div class="rzpa-kpi-value" id="kpi_ctr">–</div>
      <div class="rzpa-kpi-sub" id="kpi_ctr_sub">% af de der ser annoncen klikker</div>
    </div>
  </div>

  <!-- Forklaringsboks -->
  <div class="rzpa-explain-bar" id="meta-explain" style="display:none">
    <span class="explain-icon">💡</span>
    <span id="meta-explain-text"></span>
  </div>

  <!-- Performance oversigt -->
  <div class="rzpa-perf-summary" id="meta-perf-summary" style="display:none">
    <div class="perf-item perf-good"><span id="perf_good_count">0</span> kampagner kører <strong>godt</strong> 🟢</div>
    <div class="perf-item perf-mid"><span id="perf_mid_count">0</span> kører <strong>middel</strong> 🟡</div>
    <div class="perf-item perf-bad"><span id="perf_bad_count">0</span> kører <strong>svagt</strong> 🔴</div>
  </div>

  <!-- Månedlig oversigt -->
  <div class="rzpa-card rzpa-monthly-card" id="meta-monthly-card" style="display:none">
    <div class="rzpa-chart-title">Månedligt forbrug</div>
    <div class="rzpa-chart-sub">Total annonceforbrug per måned (DKK) · Seneste 6 måneder</div>
    <div style="height:180px;margin-top:12px"><canvas id="chart_monthly"></canvas></div>
  </div>

  <!-- Grafer -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Forbrug per kampagne</div>
      <div class="rzpa-chart-sub">Hvor mange kr er brugt (DKK)</div>
      <div style="height:200px"><canvas id="chart_spend"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Klikrate per kampagne (CTR %)</div>
      <div class="rzpa-chart-sub">Grøn ≥ 1,5% er godt · Under 0,5% er svagt</div>
      <div style="height:200px"><canvas id="chart_ctr"></canvas></div>
    </div>
  </div>

  <!-- Filter + tabel -->
  <div class="rzpa-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">Alle kampagner</h2>
        <div class="rzpa-card-sub">Sortér ved at klikke på en kolonneoverskrift</div>
      </div>
      <div class="rzpa-filter-bar" id="meta-filter-bar">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <button class="filter-btn" data-filter="ACTIVE">Aktive</button>
        <button class="filter-btn" data-filter="PAUSED">Pauseret</button>
        <button class="filter-btn perf-good-btn" data-filter="good">🟢 Godt</button>
        <button class="filter-btn perf-mid-btn" data-filter="mid">🟡 Middel</button>
        <button class="filter-btn perf-bad-btn" data-filter="bad">🔴 Svagt</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table" id="meta-table">
        <thead><tr>
          <th>Kampagnenavn</th>
          <th>Status</th>
          <th data-sort="spend" style="cursor:pointer">Forbrug ↕</th>
          <th data-sort="impressions" style="cursor:pointer">Visninger ↕</th>
          <th data-sort="reach" style="cursor:pointer">Rækkevidde ↕</th>
          <th data-sort="clicks" style="cursor:pointer">Klik ↕</th>
          <th>Pris/1000 vist</th>
          <th data-sort="cpc" style="cursor:pointer">Pris/klik ↕</th>
          <th data-sort="ctr" style="cursor:pointer">Klikrate ↕</th>
          <th>Performance</th>
          <th></th>
        </tr></thead>
        <tbody id="meta_tbody"><tr><td colspan="11" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
    <div id="meta-no-results" style="display:none;text-align:center;padding:24px;color:var(--text-muted)">
      Ingen kampagner matcher filteret.
    </div>
  </div>

</div>
