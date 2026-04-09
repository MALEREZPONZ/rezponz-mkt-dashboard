<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$tt_opts    = get_option( 'rzpa_settings', [] );
$has_tt     = ! empty( $tt_opts['tiktok_access_token'] );
$has_openai = ! empty( $tt_opts['openai_api_key'] );
?>
<div id="rzpa-app" data-rzpa-page="tiktok">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/Rezponz-logo.png' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge" style="background:rgba(255,0,80,.12);color:#ff0050">TikTok Ads</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>TikTok Annoncer</h1>
      <p class="page-sub">Her ser du hvad dine TikTok-annoncer koster — og hvad du får ud af dem</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-tiktok" class="btn-ghost">⟳ Hent data</button>
    </div>
  </div>

  <!-- Health / story bar -->
  <div id="tiktok-health-bar" style="display:none"></div>
  <div id="tiktok-story" class="rzpa-story hidden"></div>

  <!-- Period label -->
  <div id="tiktok-period-label" style="font-size:12px;color:#555;margin-bottom:8px;display:none">
    📊 Viser data for de seneste <strong id="tiktok-period-days">30</strong> dage
  </div>

  <!-- KPI v2 -->
  <div class="rzpa-kpi-grid v2">
    <div class="rzpa-kpi-v2">
      <div class="k2-q">💰 Hvad kostede annoncerne?</div>
      <div class="k2-val" id="kpi_spend">–</div>
      <div class="k2-ctx" id="kpi_spend_sub">kr brugt på TikTok</div>
      <div class="k2-status" id="kpi_spend_pill"></div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">▶ Hvor mange <span data-tip="Video views tæller gange nogen har set din annonce i mere end 2 sekunder på TikTok.">så videoen?</span></div>
      <div class="k2-val" id="kpi_views">–</div>
      <div class="k2-ctx" id="kpi_views_sub">video views i alt</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">📈 <span data-tip="ROAS = Return On Ad Spend. Hvis du bruger 1 kr og får 3 kr i salg, er ROAS 3x. Over 2x er godt.">Hvad tjener du per krone?</span></div>
      <div class="k2-val" id="kpi_roas">–</div>
      <div class="k2-ctx" id="kpi_roas_sub">gns. ROAS (return on ad spend)</div>
      <div class="k2-status" id="kpi_roas_pill"></div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🎯 <span data-tip="Hook rate: andelen der ser mere end 3 sekunder. Over 25% er godt for TikTok video-annoncer.">Holder annoncen folks opmærksomhed?</span></div>
      <div class="k2-val" id="kpi_hook">–</div>
      <div class="k2-ctx">hook rate (3s view rate)</div>
      <div class="k2-status" id="kpi_hook_sub"></div>
    </div>
  </div>

  <!-- Performance oversigt -->
  <div class="rzpa-perf-summary" id="tiktok-perf-summary" style="display:none">
    <div class="perf-item"><span id="tiktok_perf_good">0</span> kampagner kører <strong>godt</strong> 🟢</div>
    <div class="perf-item"><span id="tiktok_perf_mid">0</span> kører <strong>middel</strong> 🟡</div>
    <div class="perf-item"><span id="tiktok_perf_bad">0</span> kører <strong>svagt</strong> 🔴</div>
  </div>

  <!-- ══ AI CREATIVE SPECIALIST ══════════════════════════════════════════════ -->
  <?php if ( $has_openai ) : ?>
  <div class="rzpa-card" id="tiktok-ai-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🤖 TikTok AI-specialist</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Din personlige TikTok Ads-rådgiver — analyserer kampagner, video-performance og giver konkrete handlingsforslag</div>
      </div>
      <button id="tiktok-ai-refresh" class="btn-ghost" style="font-size:12px">✨ Analysér nu</button>
    </div>
    <div id="tiktok-ai-content" style="font-size:13px;color:#888;line-height:1.8">
      Klik <strong style="color:#ccc">"Analysér nu"</strong> for at få en komplet AI-analyse af dine TikTok-annoncer med prioriterede forbedringsforslag.
    </div>
  </div>
  <?php else : ?>
  <div class="rzpa-card" style="border-color:rgba(255,0,80,.08)">
    <h2>🤖 TikTok AI-specialist</h2>
    <div class="rzpa-card-sub">Tilføj en OpenAI API-nøgle i Indstillinger for at aktivere din personlige TikTok Ads-rådgiver</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="display:inline-block;margin-top:12px;font-size:12px;color:#ff0050;text-decoration:none;opacity:.8">⚙️ Tilføj OpenAI nøgle →</a>
  </div>
  <?php endif; ?>

  <!-- ══ CREATIVE INTELLIGENCE ══════════════════════════════════════════════ -->
  <div class="rzpa-card" id="tiktok-creatives-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🏆 Creative Intelligence</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Dine aktive TikTok-annoncer med video-scores — hook rate, hold rate og overall performance</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <div id="tiktok-creative-sort" class="rzpa-date-filter" style="font-size:12px">
          <button data-csort="score" class="active">🏅 Score</button>
          <button data-csort="roas">📈 ROAS</button>
          <button data-csort="views">▶ Views</button>
        </div>
        <button id="tiktok-load-creatives" class="btn-ghost" style="font-size:12px">📊 Hent creatives</button>
      </div>
    </div>
    <div id="tiktok-creatives-content" style="color:#555;font-size:13px;padding:32px;text-align:center">
      Klik <strong style="color:#888">"Hent creatives"</strong> for at analysere dine TikTok-videoer.
    </div>
  </div>

  <!-- Performance over tid -->
  <div class="rzpa-card rzpa-monthly-card" id="tiktok-monthly-card" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
      <div>
        <div class="rzpa-chart-title" style="margin:0">📈 Performance over tid</div>
        <div class="rzpa-chart-sub" style="margin:4px 0 0">Forbrug (røde søjler) og video views (grøn linje) per måned</div>
      </div>
    </div>
    <div style="height:220px;margin-top:12px"><canvas id="chart_tiktok_monthly"></canvas></div>
  </div>

  <!-- Grafer -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Video Views per kampagne</div>
      <div class="rzpa-chart-sub">Hvilke kampagner får flest visninger?</div>
      <div style="height:200px"><canvas id="chart_views"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title"><span data-tip="ROAS: Return On Ad Spend. Over 2,5x er stærkt.">ROAS per kampagne</span></div>
      <div class="rzpa-chart-sub">🟢 Over 2,5x er stærkt &nbsp;·&nbsp; 🔴 Under 1x er tab</div>
      <div style="height:200px"><canvas id="chart_roas"></canvas></div>
    </div>
  </div>

  <!-- ══ Alle kampagner ═══════════════════════════════════════════════════ -->
  <div class="rzpa-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2>Alle dine kampagner</h2>
        <div class="rzpa-card-sub">Klik på en kolonne-overskrift for at sortere</div>
      </div>
      <div class="rzpa-filter-bar" id="tiktok-filter-bar">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <button class="filter-btn" data-filter="ACTIVE">Aktive</button>
        <button class="filter-btn" data-filter="PAUSED">Pauserede</button>
        <button class="filter-btn" data-filter="good">🟢 Godt</button>
        <button class="filter-btn" data-filter="mid">🟡 Middel</button>
        <button class="filter-btn" data-filter="bad">🔴 Svagt</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table" id="tiktok-table">
        <thead><tr>
          <th>Kampagnenavn</th>
          <th>Status</th>
          <th data-sort="spend" style="cursor:pointer">Forbrug ↕</th>
          <th data-sort="video_views" style="cursor:pointer">Video Views ↕</th>
          <th data-sort="clicks" style="cursor:pointer">Klik ↕</th>
          <th data-sort="conversions" style="cursor:pointer">Konv. ↕</th>
          <th data-sort="roas" style="cursor:pointer"><span data-tip="Return On Ad Spend — over 2,5x er stærkt">ROAS ↕</span></th>
          <th><span data-tip="Pris per video view">Cost/View</span></th>
          <th>Vurdering</th>
        </tr></thead>
        <tbody id="tiktok_tbody"><tr><td colspan="9" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
    <div id="tiktok-no-results" style="display:none;text-align:center;padding:24px;color:var(--text-muted)">
      Ingen kampagner matcher filteret.
    </div>
  </div>

</div>
