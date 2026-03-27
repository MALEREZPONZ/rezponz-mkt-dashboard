<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$meta_opts  = get_option( 'rzpa_settings', [] );
$has_openai = ! empty( $meta_opts['openai_api_key'] );
?>
<div id="rzpa-app" data-rzpa-page="meta">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Meta Annoncer</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Facebook &amp; Instagram Annoncer</h1>
      <p class="page-sub">Her ser du hvad dine annoncer koster — og hvad du får ud af dem</p>
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

  <!-- Health bar (udfyldes af JS) -->
  <div id="rzpa-health-bar" style="display:none"></div>

  <!-- Fortæller-kort (udfyldes af JS) -->
  <div id="meta-story" class="rzpa-story hidden"></div>

  <!-- KPI kort med simple spørgsmål -->
  <div id="meta-period-label" style="font-size:12px;color:#555;margin-bottom:8px;display:none">
    📊 Viser data for de seneste <strong id="meta-period-days">30</strong> dage
  </div>
  <div class="rzpa-kpi-grid v2">
    <div class="rzpa-kpi-v2">
      <div class="k2-q">💰 Hvad kostede annoncerne?</div>
      <div class="k2-val" id="kpi_spend">–</div>
      <div class="k2-ctx" id="kpi_spend_sub">kr brugt på Facebook og Instagram</div>
      <div class="k2-status" id="kpi_spend_pill"></div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">👁 Hvor mange <span data-tip="En 'visning' tæller hver gang din annonce dukker op i en persons feed — uanset om de klikker eller ej.">så annoncen?</span></div>
      <div class="k2-val" id="kpi_impr">–</div>
      <div class="k2-ctx">gange dukkede annoncen op</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🖱 Hvem klikkede videre til sitet?</div>
      <div class="k2-val" id="kpi_clicks">–</div>
      <div class="k2-ctx" id="kpi_cpc_sub">personer klikkede videre</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🎯 Er folk <span data-tip="Klikprocenten (CTR) viser: ud af 100 der ser annoncen, hvor mange klikker? Over 1,5% er godt for B2B markedsføring. Under 0,5% bør du ændre annoncens billede eller tekst.">interesserede?</span></div>
      <div class="k2-val" id="kpi_ctr">–</div>
      <div class="k2-ctx">af de der ser annoncen, klikker videre</div>
      <div class="k2-status" id="kpi_ctr_sub"></div>
    </div>
  </div>

  <!-- Performance oversigt -->
  <div class="rzpa-perf-summary" id="meta-perf-summary" style="display:none">
    <div class="perf-item"><span id="perf_good_count">0</span> kampagner kører <strong>godt</strong> 🟢</div>
    <div class="perf-item"><span id="perf_mid_count">0</span> kører <strong>middel</strong> 🟡</div>
    <div class="perf-item"><span id="perf_bad_count">0</span> kører <strong>svagt — overvej at pause dem</strong> 🔴</div>
  </div>

  <!-- ══ META AI-SPECIALIST ═══════════════════════════════════════════════ -->
  <?php if ( $has_openai ) : ?>
  <div class="rzpa-card" id="meta-ai-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🤖 Meta AI-specialist</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Din personlige Meta Ads-rådgiver — analyserer dine kampagner og giver konkrete handlingsforslag</div>
      </div>
      <button id="meta-ai-refresh" class="btn-ghost" style="font-size:12px">✨ Analysér nu</button>
    </div>
    <div id="meta-ai-content" style="font-size:13px;color:#888;line-height:1.8">
      Klik <strong style="color:#ccc">"Analysér nu"</strong> for at få en komplet AI-analyse af dine Meta-annoncer med prioriterede forbedringsforslag til kampagner, content og teknik.
    </div>
  </div>
  <?php else : ?>
  <div class="rzpa-card" style="border-color:rgba(24,119,242,.12)">
    <h2>🤖 Meta AI-specialist</h2>
    <div class="rzpa-card-sub">Tilføj en OpenAI API-nøgle i Indstillinger for at aktivere din personlige Meta Ads-rådgiver</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="display:inline-block;margin-top:12px;font-size:12px;color:#1877F2;text-decoration:none">⚙️ Tilføj OpenAI nøgle →</a>
  </div>
  <?php endif; ?>

  <!-- ══ TOP ANNONCER ═══════════════════════════════════════════════════ -->
  <div class="rzpa-card" id="meta-top-ads-card" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🏆 Top annoncer</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Dine bedst performende annoncer baseret på rækkevidde</div>
      </div>
      <button id="meta-top-ads-load" class="btn-ghost" style="font-size:12px">📊 Hent top annoncer</button>
    </div>
    <div id="meta-top-ads-content">
      <div id="meta-top-ads-podium"></div>
      <div id="meta-top-ads-list"></div>
    </div>
  </div>

  <!-- ══ LANDING PAGES ═══════════════════════════════════════════════════ -->
  <div class="rzpa-card" id="meta-landing-pages-card" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🔗 Landing pages</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Hvilke sider sender dine annoncer trafik til?</div>
      </div>
    </div>
    <div id="meta-landing-pages-content"></div>
  </div>

  <!-- Performance over tid -->
  <div class="rzpa-card rzpa-monthly-card" id="meta-monthly-card" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
      <div>
        <div class="rzpa-chart-title" style="margin:0">📈 Performance over tid</div>
        <div class="rzpa-chart-sub" style="margin:4px 0 0">Forbrug (blå søjler) og antal klik (grøn linje) per måned</div>
      </div>
      <div id="meta-months-filter" class="rzpa-date-filter" style="font-size:12px">
        <button data-months="3">3 mdr.</button>
        <button data-months="6" class="active">6 mdr.</button>
        <button data-months="12">12 mdr.</button>
      </div>
    </div>
    <div style="height:220px;margin-top:12px"><canvas id="chart_monthly"></canvas></div>
  </div>

  <!-- Grafer -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Forbrug per kampagne (kr)</div>
      <div class="rzpa-chart-sub">Hvilke kampagner koster mest?</div>
      <div style="height:200px"><canvas id="chart_spend"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title"><span data-tip="Klikprocenten viser, hvor mange af dem der ser annoncen der klikker. Grøn = over 1,5% (godt) · Gul = 0,5–1,5% (middel) · Rød = under 0,5% (svagt)">Interesse per kampagne</span></div>
      <div class="rzpa-chart-sub">🟢 Over 1,5% er godt &nbsp;·&nbsp; 🔴 Under 0,5% bør du handle</div>
      <div style="height:200px"><canvas id="chart_ctr"></canvas></div>
    </div>
  </div>

  <!-- Forklaringsboks (udfyldes af JS — skjult i standard) -->
  <div class="rzpa-explain-bar" id="meta-explain" style="display:none">
    <span class="explain-icon">💡</span>
    <span id="meta-explain-text"></span>
  </div>

  <!-- Filter + tabel -->
  <div class="rzpa-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2>Alle dine kampagner</h2>
        <div class="rzpa-card-sub">Klik på en kolonne-overskrift for at sortere &nbsp;·&nbsp; Brug filtrene til højre</div>
      </div>
      <div class="rzpa-filter-bar" id="meta-filter-bar">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <button class="filter-btn" data-filter="ACTIVE">Aktive</button>
        <button class="filter-btn" data-filter="PAUSED">Pauserede</button>
        <button class="filter-btn" data-filter="good">🟢 Godt</button>
        <button class="filter-btn" data-filter="mid">🟡 Middel</button>
        <button class="filter-btn" data-filter="bad">🔴 Svagt</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table" id="meta-table">
        <thead><tr>
          <th>Kampagnenavn</th>
          <th>Status</th>
          <th data-sort="spend" style="cursor:pointer" title="Klik for at sortere">Forbrug ↕</th>
          <th data-sort="impressions" style="cursor:pointer" title="Klik for at sortere"><span data-tip="Antal gange annoncen er dukket op i folks feed">Vist ↕</span></th>
          <th data-sort="reach" style="cursor:pointer" title="Klik for at sortere"><span data-tip="Antal unikke personer der har set annoncen">Unikke ↕</span></th>
          <th data-sort="clicks" style="cursor:pointer" title="Klik for at sortere">Klik ↕</th>
          <th><span data-tip="Hvad koster det at vise annoncen 1.000 gange? Lavere er bedre.">Pr. 1000 vist</span></th>
          <th data-sort="cpc" style="cursor:pointer" title="Klik for at sortere"><span data-tip="Pris per klik: Hvad koster det at få én person til at klikke videre?">Pris/klik ↕</span></th>
          <th data-sort="ctr" style="cursor:pointer" title="Klik for at sortere"><span data-tip="Klikprocent: Ud af 100 der ser annoncen, hvor mange klikker? Over 1,5% er godt.">Klikprocent ↕</span></th>
          <th>Vurdering</th>
          <th></th>
        </tr></thead>
        <tbody id="meta_tbody"><tr><td colspan="11" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
    <div id="meta-no-results" style="display:none;text-align:center;padding:24px;color:var(--text-muted)">
      Ingen kampagner matcher filteret.
    </div>
  </div>


  <!-- ══ FAKTURAER ═══════════════════════════════════════════════════════ -->
  <div class="rzpa-card" id="meta-invoices-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🧾 Betalingshistorik</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Dine transaktioner fra Meta — med faktura-ID og direkte download</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="date" id="meta-inv-since" class="rzpa-date-input"
               value="<?php echo esc_attr( gmdate('Y-m-d', strtotime('-90 days')) ); ?>"
               style="background:#1a1a1a;border:1px solid #333;color:#ccc;border-radius:6px;padding:5px 8px;font-size:12px">
        <span style="color:#555;font-size:12px">til</span>
        <input type="date" id="meta-inv-until" class="rzpa-date-input"
               value="<?php echo esc_attr( gmdate('Y-m-d') ); ?>"
               style="background:#1a1a1a;border:1px solid #333;color:#ccc;border-radius:6px;padding:5px 8px;font-size:12px">
        <button id="meta-invoices-load" class="btn-ghost" style="font-size:12px">⟳ Hent</button>
        <button id="meta-invoices-csv" class="btn-ghost" style="font-size:12px;display:none">📥 CSV</button>
        <button id="meta-invoices-pdf" class="btn-ghost" style="font-size:12px;display:none">🖨 PDF</button>
        <a href="https://business.facebook.com/billing_hub/payment_activity" target="_blank" class="btn-ghost" style="font-size:12px;text-decoration:none">🔗 Åbn i Meta</a>
      </div>
    </div>
    <div id="meta-invoices-content" style="color:#555;font-size:13px">
      <div style="text-align:center;padding:24px;color:#444">⏳ Henter transaktioner…</div>
    </div>
  </div>

</div>

<!-- ── Ad Creative Modal ─────────────────────────────────────────────── -->
<div id="rzpa-ad-modal" class="rzpa-modal-overlay" style="display:none" role="dialog" aria-modal="true">
  <div class="rzpa-modal">
    <div class="rzpa-modal-head">
      <div>
        <div class="rzpa-modal-eyebrow">Annoncer i kampagne</div>
        <h2 id="rzpa-modal-title" class="rzpa-modal-title">–</h2>
      </div>
      <button id="rzpa-modal-close" class="rzpa-modal-close" aria-label="Luk">✕</button>
    </div>
    <div class="rzpa-modal-body">
      <div id="rzpa-ad-cards" class="rzpa-ad-cards"></div>
    </div>
  </div>
</div>
