<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$snap_opts  = get_option( 'rzpa_settings', [] );
$has_snap   = ! empty( $snap_opts['snap_access_token'] );
$has_openai = ! empty( $snap_opts['openai_api_key'] );
?>
<div id="rzpa-app" data-rzpa-page="snap">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/Rezponz-logo.png' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge" style="background:rgba(255,252,0,.12);color:#FFFC00">Snapchat Ads</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Snapchat Annoncer</h1>
      <p class="page-sub">Her ser du hvad dine Snapchat-annoncer koster — og hvad du får ud af dem</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-snap" class="btn-ghost">⟳ Hent data</button>
    </div>
  </div>

  <!-- Health / story bar -->
  <div id="snap-health-bar" style="display:none"></div>
  <div id="snap-story" class="rzpa-story hidden"></div>

  <!-- Period label -->
  <div id="snap-period-label" style="font-size:12px;color:#555;margin-bottom:8px;display:none">
    📊 Viser data for de seneste <strong id="snap-period-days">30</strong> dage
  </div>

  <!-- KPI v2 -->
  <div class="rzpa-kpi-grid v2">
    <div class="rzpa-kpi-v2">
      <div class="k2-q">💰 Hvad kostede annoncerne?</div>
      <div class="k2-val" id="kpi_spend">–</div>
      <div class="k2-ctx" id="kpi_spend_sub">kr brugt på Snapchat</div>
      <div class="k2-status" id="kpi_spend_pill"></div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">👆 Hvem swipede op?</div>
      <div class="k2-val" id="kpi_swipes">–</div>
      <div class="k2-ctx" id="kpi_swipes_sub">swipe-ups til din hjemmeside</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">👁 Hvor mange <span data-tip="En visning tæller hver gang din annonce dukker op i en brugers feed på Snapchat.">så annoncen?</span></div>
      <div class="k2-val" id="kpi_impr">–</div>
      <div class="k2-ctx">gange dukkede annoncen op</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🎯 <span data-tip="Swipe-up rate: Ud af 100 der ser annoncen, hvor mange swiper op? Over 1% er godt for Snapchat.">Engagement rate</span></div>
      <div class="k2-val" id="kpi_engagement">–</div>
      <div class="k2-ctx">engagement rate</div>
      <div class="k2-status" id="kpi_engagement_sub"></div>
    </div>
  </div>

  <!-- Performance oversigt -->
  <div class="rzpa-perf-summary" id="snap-perf-summary" style="display:none">
    <div class="perf-item"><span id="snap_perf_good">0</span> kampagner kører <strong>godt</strong> 🟢</div>
    <div class="perf-item"><span id="snap_perf_mid">0</span> kører <strong>middel</strong> 🟡</div>
    <div class="perf-item"><span id="snap_perf_bad">0</span> kører <strong>svagt</strong> 🔴</div>
  </div>

  <!-- ══ AI CREATIVE SPECIALIST ══════════════════════════════════════════════ -->
  <?php if ( $has_openai ) : ?>
  <div class="rzpa-card" id="snap-ai-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🤖 Snapchat AI-specialist</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Din personlige Snapchat-rådgiver — analyserer dine kampagner og giver konkrete handlingsforslag</div>
      </div>
      <button id="snap-ai-refresh" class="btn-ghost" style="font-size:12px">✨ Analysér nu</button>
    </div>
    <div id="snap-ai-content" style="font-size:13px;color:#888;line-height:1.8">
      Klik <strong style="color:#ccc">"Analysér nu"</strong> for at få en komplet AI-analyse af dine Snapchat-annoncer med prioriterede forbedringsforslag.
    </div>
  </div>
  <?php else : ?>
  <div class="rzpa-card" style="border-color:rgba(255,252,0,.08)">
    <h2>🤖 Snapchat AI-specialist</h2>
    <div class="rzpa-card-sub">Tilføj en OpenAI API-nøgle i Indstillinger for at aktivere din personlige Snapchat Ads-rådgiver</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="display:inline-block;margin-top:12px;font-size:12px;color:#FFFC00;text-decoration:none;opacity:.8">⚙️ Tilføj OpenAI nøgle →</a>
  </div>
  <?php endif; ?>

  <!-- ══ CREATIVE INTELLIGENCE / TOP ANNONCER ═══════════════════════════════ -->
  <div class="rzpa-card" id="snap-creatives-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🏆 Creative Intelligence</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Dine aktive Snapchat-annoncer med performance-scores — inspiration fra superads.ai</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <div id="snap-creative-sort" class="rzpa-date-filter" style="font-size:12px">
          <button data-csort="score" class="active">🏅 Score</button>
          <button data-csort="swipes">👆 Swipes</button>
          <button data-csort="spend">💰 Spend</button>
        </div>
        <button id="snap-load-creatives" class="btn-ghost" style="font-size:12px">📊 Hent creatives</button>
      </div>
    </div>
    <div id="snap-creatives-content" style="color:#555;font-size:13px;padding:32px;text-align:center">
      Klik <strong style="color:#888">"Hent creatives"</strong> for at analysere dine Snapchat-annoncer.
    </div>
  </div>

  <!-- Performance over tid -->
  <div class="rzpa-card rzpa-monthly-card" id="snap-monthly-card" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
      <div>
        <div class="rzpa-chart-title" style="margin:0">📈 Performance over tid</div>
        <div class="rzpa-chart-sub" style="margin:4px 0 0">Forbrug (gule søjler) og swipe-ups (grøn linje) per måned</div>
      </div>
    </div>
    <div style="height:220px;margin-top:12px"><canvas id="chart_snap_monthly"></canvas></div>
  </div>

  <!-- Grafer -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Forbrug per kampagne (kr)</div>
      <div class="rzpa-chart-sub">Hvilke kampagner koster mest?</div>
      <div style="height:200px"><canvas id="chart_spend"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title"><span data-tip="Swipe-up rate: andelen der swiper op. Over 1% er godt for Snapchat.">Engagement per kampagne</span></div>
      <div class="rzpa-chart-sub">🟢 Over 1% er godt &nbsp;·&nbsp; 🔴 Under 0,3% bør du handle</div>
      <div style="height:200px"><canvas id="chart_engagement"></canvas></div>
    </div>
  </div>

  <!-- ══ Alle kampagner ═══════════════════════════════════════════════════ -->
  <div class="rzpa-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2>Alle dine kampagner</h2>
        <div class="rzpa-card-sub">Klik på en kolonne-overskrift for at sortere &nbsp;·&nbsp; Brug filtrene til højre</div>
      </div>
      <div class="rzpa-filter-bar" id="snap-filter-bar">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <button class="filter-btn" data-filter="ACTIVE">Aktive</button>
        <button class="filter-btn" data-filter="PAUSED">Pauserede</button>
        <button class="filter-btn" data-filter="good">🟢 Godt</button>
        <button class="filter-btn" data-filter="mid">🟡 Middel</button>
        <button class="filter-btn" data-filter="bad">🔴 Svagt</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table" id="snap-table">
        <thead><tr>
          <th>Kampagnenavn</th>
          <th>Status</th>
          <th data-sort="spend" style="cursor:pointer">Forbrug ↕</th>
          <th data-sort="impressions" style="cursor:pointer"><span data-tip="Antal gange annoncen er vist i Snapchat">Vist ↕</span></th>
          <th data-sort="swipe_ups" style="cursor:pointer"><span data-tip="Antal gange folk swipede op — svarende til et klik">Swipe-ups ↕</span></th>
          <th data-sort="conversions" style="cursor:pointer">Konv. ↕</th>
          <th><span data-tip="Pris per 1.000 visninger">CPM</span></th>
          <th data-sort="engagement_rate" style="cursor:pointer"><span data-tip="Engagement rate: andel der interagerer med annoncen">Engagement ↕</span></th>
          <th>Vurdering</th>
        </tr></thead>
        <tbody id="snap_tbody"><tr><td colspan="9" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
    <div id="snap-no-results" style="display:none;text-align:center;padding:24px;color:var(--text-muted)">
      Ingen kampagner matcher filteret.
    </div>
  </div>

</div>
