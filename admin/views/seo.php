<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$seo_opts       = get_option( 'rzpa_settings', [] );
$seo_configured = ! empty( $seo_opts['google_client_id'] ) && ! empty( $seo_opts['google_refresh_token'] );
$has_openai     = ! empty( $seo_opts['openai_api_key'] );
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
  <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.25);border-radius:12px;padding:28px 32px;margin-bottom:24px">
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="font-size:32px;flex-shrink:0">🔌</div>
      <div>
        <h2 style="margin:0 0 8px;font-size:18px;color:#fff">Google Search Console er ikke forbundet endnu</h2>
        <p style="font-size:13px;color:#888;margin:0 0 16px;line-height:1.7">
          Denne side viser data fra Google om, hvilke søgeord der bringer folk til <strong style="color:#ccc">rezponz.dk</strong>,
          og hvordan jeres sider rangerer på Google.
        </p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" class="btn-primary" style="text-decoration:none;display:inline-block">
          ⚙️ Forbind Google Search Console →
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Health bar -->
  <div id="rzpa-seo-health" style="display:none"></div>

  <!-- Story -->
  <div id="seo-story" class="rzpa-story hidden"></div>

  <!-- KPI kort -->
  <div class="rzpa-kpi-grid v2">
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🖱 Hvor mange klikkede ind?</div>
      <div class="k2-val" id="kpi_clicks">–</div>
      <div class="k2-ctx" id="kpi_clicks_trend">klik fra Google-søgninger</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">👁 <span data-tip="Visninger = antal gange rezponz.dk dukkede op i Google, selvom folk ikke klikkede.">Visninger på Google</span></div>
      <div class="k2-val" id="kpi_impr">–</div>
      <div class="k2-ctx" id="kpi_impr_trend">visninger i søgeresultaterne</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🎯 <span data-tip="Klikprocent: ud af dem der ser jer, hvor mange klikker ind? Over 3% er godt.">Klikprocent</span></div>
      <div class="k2-val" id="kpi_ctr">–</div>
      <div class="k2-ctx">af dem der ser jer, klikker ind</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🏆 <span data-tip="Søgeord på Googles 1. side (top 10). Top 3 er bedst — størstedelen af klikene går til de 3 første.">Søgeord på 1. side</span></div>
      <div class="k2-val" id="kpi_top10">–</div>
      <div class="k2-ctx" id="kpi_top10_sub">søgeord vises på side 1</div>
    </div>
  </div>

  <!-- ══ ACTION CENTER ════════════════════════════════════════════════════ -->
  <div class="rzpa-card rzpa-action-center" id="seo-action-center" style="display:none">
    <h2>⚡ Hvad skal du gøre nu?</h2>
    <div class="rzpa-card-sub">Konkrete opgaver baseret på dine egne data — sorteret efter hvad der giver mest</div>
    <div id="seo-action-list" class="rzpa-action-list"></div>
  </div>

  <!-- ══ AI ANBEFALINGER ══════════════════════════════════════════════════ -->
  <?php if ( $has_openai ) : ?>
  <div class="rzpa-card" id="seo-ai-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🤖 AI-anbefalinger</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Konkrete råd til at forbedre jeres Google-ranking</div>
      </div>
      <button id="seo-ai-refresh" class="btn-ghost" style="font-size:12px">✨ Analysér nu</button>
    </div>
    <div id="seo-ai-content" style="font-size:13px;color:#888;line-height:1.8">
      Klik "Analysér nu" for at få AI-drevne anbefalinger baseret på dine søgeord og sider.
    </div>
  </div>
  <?php else : ?>
  <div class="rzpa-card" style="border-color:rgba(204,255,0,.1)">
    <h2>🤖 AI-anbefalinger</h2>
    <div class="rzpa-card-sub">Tilføj en OpenAI API-nøgle i Indstillinger for at få konkrete SEO-anbefalinger baseret på dine data</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="display:inline-block;margin-top:12px;font-size:12px;color:var(--neon);text-decoration:none">⚙️ Tilføj OpenAI nøgle →</a>
  </div>
  <?php endif; ?>

  <!-- Top søgeord graf -->
  <div class="rzpa-chart-wrap">
    <div class="rzpa-chart-title">Top søgeord med flest klik</div>
    <div class="rzpa-chart-sub">De ord folk søger på og trykker ind på jer med</div>
    <div style="height:200px;position:relative"><canvas id="chart_kw_clicks"></canvas></div>
  </div>

  <!-- ══ MÅNEDLIG TRAFIK ══════════════════════════════════════════════════ -->
  <div class="rzpa-card rzpa-monthly-card">
    <h2>📅 Månedlig trafik</h2>
    <div class="rzpa-card-sub">Klik og visninger måned for måned — ser I vækst?</div>
    <div style="height:180px;position:relative"><canvas id="chart_seo_monthly"></canvas></div>
  </div>

  <!-- Søgeordstabel + trend-graf -->
  <div class="rzpa-chart-grid-wide">

    <div class="rzpa-card" style="margin-bottom:0">
      <h2>📋 Alle søgeord</h2>
      <div class="rzpa-card-sub">Klik på et søgeord for at se udviklingen over tid</div>
      <div class="rzpa-table-wrap">
        <table class="rzpa-table">
          <thead><tr>
            <th>Søgeord</th>
            <th><span data-tip="Din placering på Google. #1 er bedst. Top 3 er guld.">Placering</span></th>
            <th>Klik</th>
            <th>Vist</th>
            <th>CTR</th>
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
          <th>Type</th>
          <th><span data-tip="Gennemsnitlig placering for denne side">Placering</span></th>
          <th>Klik</th>
          <th>Vist</th>
          <th><span data-tip="Lav CTR = mange ser siden men klikker ikke. Prøv en bedre sidetitel.">CTR</span></th>
        </tr></thead>
        <tbody id="seo_pages_tbody">
          <tr><td colspan="6" class="rzpa-loading">Indlæser…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Muligheder: næsten side 1 -->
  <div class="rzpa-card">
    <h2>💡 Muligheder — søgeord tæt på side 1</h2>
    <div class="rzpa-card-sub">Søgeord I er placeret i position 11–20 (side 2). Lidt mere arbejde og de kan ryge op på side 1!</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Søgeord</th>
          <th>Placering</th>
          <th>Vist</th>
          <th>CTR</th>
          <th>Hvad du kan gøre</th>
        </tr></thead>
        <tbody id="seo_opportunities_tbody">
          <tr><td colspan="5" class="rzpa-loading">Indlæser…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══ SØGEORDS-ANBEFALINGER ════════════════════════════════════════════ -->
  <?php if ( $has_openai ) : ?>
  <div class="rzpa-card" id="seo-kw-suggestions-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🔍 Søgeordsanbefalinger</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">30 vigtige søgeord I bør ranke på — baseret på AI-analyse af jeres branche og konkurrenter</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <div style="font-size:11px;color:#444;font-style:italic" id="seo-kw-cache-note"></div>
        <button id="seo-kw-suggestions-btn" class="btn-ghost" style="font-size:12px">🔍 Hent søgeordsforslag</button>
      </div>
    </div>
    <div id="seo-kw-suggestions-content" style="font-size:13px;color:#666;line-height:1.8">
      Klik <strong style="color:#888">"Hent søgeordsforslag"</strong> for at få en AI-genereret liste over søgeord med god trafik som I bør fokusere på.
    </div>
  </div>
  <?php else : ?>
  <div class="rzpa-card" style="border-color:rgba(204,255,0,.08)">
    <h2>🔍 Søgeordsanbefalinger</h2>
    <div class="rzpa-card-sub">Tilføj en OpenAI API-nøgle i Indstillinger for at få AI-genererede søgeordsforslag</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="display:inline-block;margin-top:12px;font-size:12px;color:var(--neon);text-decoration:none">⚙️ Tilføj OpenAI nøgle →</a>
  </div>
  <?php endif; ?>

  <!-- ══ FORKLARING PÅ PLACERINGER ════════════════════════════════════════ -->
  <div style="background:rgba(245,166,35,.05);border:1px solid rgba(245,166,35,.15);border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:12px;color:#888;line-height:1.7">
    <strong style="color:#f5a623">ℹ️ Om placeringer i tabellen:</strong> Tallene kommer fra Google Search Console og viser den <em>gennemsnitlige</em> placering over den valgte periode — ikke din placering i realtid. Din placering på Google varierer efter søgerens placering, browserhistorik og tidspunkt. Søg inkognito fra Aalborg for det mest præcise billede.
  </div>

  <!-- CTR Optimizer: mange visninger, få klik -->
  <div class="rzpa-card">
    <h2>🎯 CTR-optimering — sider med mange visninger men få klik</h2>
    <div class="rzpa-card-sub">Disse sider dukker op på Google, men folk klikker ikke — en bedre sidetitel eller beskrivelse kan fordoble trafikken</div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Side</th>
          <th>Type</th>
          <th>Placering</th>
          <th>Vist</th>
          <th>CTR</th>
          <th>Tip</th>
        </tr></thead>
        <tbody id="seo_ctr_tbody">
          <tr><td colspan="6" class="rzpa-loading">Indlæser…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
