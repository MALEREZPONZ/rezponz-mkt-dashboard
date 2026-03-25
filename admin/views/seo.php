<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$seo_opts      = get_option( 'rzpa_settings', [] );
$seo_configured = ! empty( $seo_opts['google_client_id'] ) && ! empty( $seo_opts['google_refresh_token'] );
?>
<div id="rzpa-app" data-rzpa-page="seo">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Google SEO</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Organisk søgetrafik fra Google</h1>
      <p class="page-sub">Her ser du hvor mange der finder jer på Google — uden at I betaler for det</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-seo-sync" class="btn-ghost">⟳ Hent data</button>
    </div>
  </div>

  <?php if ( ! $seo_configured ) : ?>
  <!-- ── Google ikke forbundet ─────────────────────── -->
  <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.25);border-radius:12px;padding:28px 32px;margin-bottom:24px">
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="font-size:32px;flex-shrink:0">🔌</div>
      <div>
        <h2 style="margin:0 0 8px;font-size:18px;color:#fff">Google Search Console er ikke forbundet endnu</h2>
        <p style="font-size:13px;color:#888;margin:0 0 16px;line-height:1.7">
          Denne side viser data fra Google om, hvilke søgeord der bringer folk til <strong style="color:#ccc">rezponz.dk</strong>,
          og hvordan jeres sider rangerer på Google. For at se <em>rigtige</em> data skal Google Search Console forbindes.
        </p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>#google"
           class="btn-primary" style="text-decoration:none;display:inline-block">
          ⚙️ Forbind Google Search Console →
        </a>
        <span style="font-size:12px;color:#555;margin-left:12px">Det tager ca. 3 minutter</span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Health bar (udfyldes af JS) -->
  <div id="rzpa-seo-health" style="display:none"></div>

  <!-- Fortæller-kort (udfyldes af JS) -->
  <div id="seo-story" class="rzpa-story hidden"></div>

  <!-- KPI kort -->
  <div class="rzpa-kpi-grid v2">
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🖱 Hvor mange klikkede ind?</div>
      <div class="k2-val" id="kpi_clicks">–</div>
      <div class="k2-ctx">klik fra Google-søgninger</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">👁 <span data-tip="Visninger = antal gange rezponz.dk er dukket op i Googles søgeresultater, selvom folk ikke klikkede.">Hvor mange så jer på Google?</span></div>
      <div class="k2-val" id="kpi_impr">–</div>
      <div class="k2-ctx">visninger i søgeresultaterne</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🎯 <span data-tip="Klikprocenten viser: ud af dem der ser jer på Google, hvor mange klikker ind? Over 3% er godt for SEO.">Klikprocent</span></div>
      <div class="k2-val" id="kpi_ctr">–</div>
      <div class="k2-ctx">af dem der ser jer, klikker ind</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🏆 <span data-tip="Søgeord i top 10 = I dukker op på Googles 1. side. Det er der folk kigger. Top 3 er endnu bedre — størstedelen af klikene går til de 3 første resultater.">Søgeord på 1. side</span></div>
      <div class="k2-val" id="kpi_top10">–</div>
      <div class="k2-ctx" id="kpi_top10_sub">søgeord vises på side 1</div>
    </div>
  </div>

  <!-- Top søgeord graf -->
  <div class="rzpa-chart-wrap">
    <div class="rzpa-chart-title">Top søgeord med flest klik</div>
    <div class="rzpa-chart-sub">De ord folk søger på og trykker ind på jer med</div>
    <div style="height:200px;position:relative"><canvas id="chart_kw_clicks"></canvas></div>
  </div>

  <!-- Søgeordstabel + trend-graf side om side -->
  <div class="rzpa-chart-grid-wide">

    <div class="rzpa-card" style="margin-bottom:0">
      <h2>📋 Alle søgeord</h2>
      <div class="rzpa-card-sub">Klik på et søgeord for at se udviklingen over tid</div>
      <div class="rzpa-table-wrap">
        <table class="rzpa-table">
          <thead><tr>
            <th>Søgeord</th>
            <th><span data-tip="Din placering på Google. #1 er bedst. Top 10 = 1. side. Top 3 er der flest klikker.">Placering</span></th>
            <th>Klik</th>
            <th>Vist</th>
            <th>Klikprocent</th>
          </tr></thead>
          <tbody id="seo_tbody">
            <tr><td colspan="5" class="rzpa-loading">Indlæser…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rzpa-chart-wrap" style="margin-bottom:0">
      <div class="rzpa-chart-title" id="trend_title">Placering over tid</div>
      <div class="rzpa-chart-sub">Klik på et søgeord i tabellen for at se grafen</div>
      <div style="height:220px;position:relative"><canvas id="chart_kw_trend"></canvas></div>
    </div>

  </div>

  <!-- Top sider -->
  <div class="rzpa-card">
    <h2>📄 Hvilke sider finder folk?</h2>
    <div class="rzpa-card-sub">De undersider på rezponz.dk som Google sender flest besøgende til</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Side (URL)</th>
          <th><span data-tip="Gennemsnitlig placering for denne side på Google">Placering</span></th>
          <th>Klik</th>
          <th>Vist</th>
          <th>Klikprocent</th>
        </tr></thead>
        <tbody id="seo_pages_tbody">
          <tr><td colspan="5" class="rzpa-loading">Indlæser…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Muligheder -->
  <div class="rzpa-card">
    <h2>💡 Muligheder — søgeord tæt på side 1</h2>
    <div class="rzpa-card-sub">Søgeord I er placeret i position 11–20 (side 2 på Google). Lidt mere arbejde og de kan ryge op på side 1!</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Søgeord</th>
          <th>Nuværende placering</th>
          <th>Vist (visninger)</th>
          <th>Klikprocent</th>
        </tr></thead>
        <tbody id="seo_opportunities_tbody">
          <tr><td colspan="4" class="rzpa-loading">Indlæser…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
