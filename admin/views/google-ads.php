<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$gads_opts       = get_option( 'rzpa_settings', [] );
$gads_configured = ! empty( $gads_opts['google_ads_refresh_token'] ) && ! empty( $gads_opts['google_ads_customer_id'] ) && ! empty( $gads_opts['google_ads_developer_token'] );
$has_openai      = ! empty( $gads_opts['openai_api_key'] );
?>
<div id="rzpa-app" data-rzpa-page="google-ads">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/Rezponz-logo.png' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge" style="background:rgba(66,133,244,.15);color:#4285F4">Google Ads</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Google Ads</h1>
      <p class="page-sub">Her ser du hvad dine Google-annoncer koster — og hvad du får ud af dem</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-sync-gads" class="btn-ghost">⟳ Hent data</button>
    </div>
  </div>

  <?php if ( ! $gads_configured ) : ?>
  <div style="background:rgba(66,133,244,.06);border:1px solid rgba(66,133,244,.25);border-radius:12px;padding:28px 32px;margin-bottom:24px">
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="font-size:32px;flex-shrink:0">🔌</div>
      <div>
        <h2 style="margin:0 0 8px;font-size:18px;color:#fff">Google Ads er ikke forbundet endnu</h2>
        <p style="font-size:13px;color:#888;margin:0 0 16px;line-height:1.7">
          For at forbinde Google Ads skal du have: <strong style="color:#ccc">Developer Token</strong> fra Google Ads API Center,
          <strong style="color:#ccc">Customer ID</strong> fra din Google Ads-konto, og autorisere via OAuth.
        </p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings#google-ads' ) ); ?>" class="btn-primary" style="text-decoration:none;display:inline-block">
          ⚙️ Opsæt Google Ads →
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Health bar -->
  <div id="gads-health-bar" style="display:none"></div>

  <!-- Diagnostik panel (vises kun ved fejl) -->
  <div id="gads-diag-panel" style="display:none;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:20px 24px;margin-bottom:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="font-size:14px;font-weight:600;color:#e5e5e5">🔍 Google Ads Diagnostik</div>
      <button id="gads-test-btn" class="btn-ghost" style="font-size:12px">🧪 Test forbindelse</button>
    </div>
    <div id="gads-diag-content" style="font-size:13px;color:#888">
      Klik "Test forbindelse" for at se hvad Google siger.
    </div>
  </div>

  <!-- Story -->
  <div id="gads-story" class="rzpa-story hidden"></div>

  <!-- Periode label -->
  <div id="gads-period-label" style="font-size:12px;color:#555;margin-bottom:8px;display:none">
    📊 Viser data for de seneste <strong id="gads-period-days">30</strong> dage
  </div>

  <!-- KPI kort -->
  <div class="rzpa-kpi-grid v2">
    <div class="rzpa-kpi-v2">
      <div class="k2-q">💰 Hvad kostede annoncerne?</div>
      <div class="k2-val" id="gads_kpi_spend">–</div>
      <div class="k2-ctx" id="gads_kpi_spend_sub">kr brugt på Google Ads</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">👁 Hvor mange <span data-tip="En visning tæller hver gang din annonce dukker op i Google-søgeresultater.">så annoncen?</span></div>
      <div class="k2-val" id="gads_kpi_impr">–</div>
      <div class="k2-ctx">gange dukkede annoncen op</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🖱 Hvem klikkede videre?</div>
      <div class="k2-val" id="gads_kpi_clicks">–</div>
      <div class="k2-ctx" id="gads_kpi_cpc_sub">personer klikkede videre</div>
    </div>
    <div class="rzpa-kpi-v2">
      <div class="k2-q">🎯 <span data-tip="Klikprocenten (CTR): ud af 100 der ser annoncen, hvor mange klikker? Over 2% er godt for søgeannoncer.">Klikprocent</span></div>
      <div class="k2-val" id="gads_kpi_ctr">–</div>
      <div class="k2-ctx">af de der ser annoncen, klikker</div>
      <div class="k2-status" id="gads_kpi_ctr_sub"></div>
    </div>
  </div>

  <!-- Performance oversigt -->
  <div class="rzpa-perf-summary" id="gads-perf-summary" style="display:none">
    <div class="perf-item"><span id="gads_perf_good">0</span> kampagner kører <strong>godt</strong> 🟢</div>
    <div class="perf-item"><span id="gads_perf_mid">0</span> kører <strong>middel</strong> 🟡</div>
    <div class="perf-item"><span id="gads_perf_bad">0</span> kører <strong>svagt</strong> 🔴</div>
  </div>

  <!-- ══ AI-SPECIALIST ══════════════════════════════════════════════════════ -->
  <?php if ( $has_openai ) : ?>
  <div class="rzpa-card" id="gads-ai-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🤖 Google Ads AI-specialist</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Din personlige Google Ads-rådgiver — analyserer kampagner og giver konkrete handlingsforslag</div>
      </div>
      <button id="gads-ai-refresh" class="btn-ghost" style="font-size:12px">✨ Analysér nu</button>
    </div>
    <div id="gads-ai-content" style="font-size:13px;color:#888;line-height:1.8">
      Klik <strong style="color:#ccc">"Analysér nu"</strong> for at få en AI-analyse af dine Google Ads med konkrete forbedringsforslag.
    </div>
  </div>
  <?php endif; ?>

  <!-- Performance over tid -->
  <div class="rzpa-card rzpa-monthly-card" id="gads-monthly-card" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
      <div>
        <div class="rzpa-chart-title" style="margin:0">📈 Performance over tid</div>
        <div class="rzpa-chart-sub" style="margin:4px 0 0">Forbrug (blå søjler) og antal klik (grøn linje) per måned</div>
      </div>
      <div id="gads-months-filter" class="rzpa-date-filter" style="font-size:12px">
        <button data-months="3">3 mdr.</button>
        <button data-months="6" class="active">6 mdr.</button>
        <button data-months="12">12 mdr.</button>
      </div>
    </div>
    <div style="height:220px;margin-top:12px"><canvas id="chart_gads_monthly"></canvas></div>
  </div>

  <!-- Kampagne-grafer -->
  <div class="rzpa-chart-grid">
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title">Forbrug per kampagne (kr)</div>
      <div class="rzpa-chart-sub">Hvilke kampagner koster mest?</div>
      <div style="height:200px"><canvas id="chart_gads_spend"></canvas></div>
    </div>
    <div class="rzpa-chart-wrap">
      <div class="rzpa-chart-title"><span data-tip="CTR: andelen der klikker. Over 2% er godt for søgeannoncer.">Klikprocent per kampagne</span></div>
      <div class="rzpa-chart-sub">🟢 Over 2% er godt &nbsp;·&nbsp; 🔴 Under 0,5% bør du handle</div>
      <div style="height:200px"><canvas id="chart_gads_ctr"></canvas></div>
    </div>
  </div>

  <!-- Alle kampagner -->
  <div class="rzpa-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2>Alle dine kampagner</h2>
        <div class="rzpa-card-sub">Klik på en kolonne-overskrift for at sortere</div>
      </div>
      <div class="rzpa-filter-bar" id="gads-filter-bar">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <button class="filter-btn" data-filter="ACTIVE">Aktive</button>
        <button class="filter-btn" data-filter="PAUSED">Pauserede</button>
        <button class="filter-btn" data-filter="good">🟢 Godt</button>
        <button class="filter-btn" data-filter="mid">🟡 Middel</button>
        <button class="filter-btn" data-filter="bad">🔴 Svagt</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table" id="gads-table">
        <thead><tr>
          <th>Kampagnenavn</th>
          <th>Status</th>
          <th data-sort="spend" style="cursor:pointer">Forbrug ↕</th>
          <th data-sort="impressions" style="cursor:pointer">Vist ↕</th>
          <th data-sort="clicks" style="cursor:pointer">Klik ↕</th>
          <th data-sort="ctr" style="cursor:pointer"><span data-tip="Klikprocent: ud af 100 der ser annoncen, hvor mange klikker?">CTR ↕</span></th>
          <th data-sort="cpc" style="cursor:pointer"><span data-tip="Pris per klik">CPC ↕</span></th>
          <th><span data-tip="Antal konverteringer (leads, køb osv.) — kræver konverteringssporing i Google Ads">Konv.</span></th>
          <th>Vurdering</th>
        </tr></thead>
        <tbody id="gads_tbody"><tr><td colspan="9" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ══ AKTIVE ANNONCER ═══════════════════════════════════════════════════ -->
  <div class="rzpa-card" id="gads-ads-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">📢 Aktive annoncer</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Dine aktive Google-annoncetekster med performance de seneste 30 dage</div>
      </div>
      <button id="gads-ads-load" class="btn-ghost" style="font-size:12px">📢 Hent annoncer</button>
    </div>
    <div id="gads-ads-content" style="color:#555;font-size:13px">
      Klik <strong style="color:#888">"Hent annoncer"</strong> for at se dine aktive Google Ads-annoncer.
    </div>
  </div>
<?php /* Inline patch – bypasses cached dashboard.js */ ?>
<script>
(function(){
  function initGadsAdsPatch(){
    var btn     = document.getElementById('gads-ads-load');
    var content = document.getElementById('gads-ads-content');
    if(!btn||!content) return;
    // Replace button to strip old cached event listeners
    var nb = btn.cloneNode(true);
    btn.parentNode.replaceChild(nb, btn);
    nb.addEventListener('click', function(){
      nb.disabled = true; nb.textContent = '⏳ Henter…';
      content.innerHTML = '<div style="color:#555;text-align:center;padding:20px">Henter aktive Google Ads-annoncer…</div>';
      fetch('<?php echo esc_js( rest_url( 'rzpa/v1/google-ads/ads' ) ); ?>?force=1', {
        headers: {'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'}
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        nb.disabled = false; nb.textContent = '📢 Hent annoncer';
        var d = res.data || [];
        if(d && d.error){ content.innerHTML='<span style="color:#ff6b6b">⚠️ '+d.error+'</span>'; return; }
        if(!Array.isArray(d)||!d.length){ content.innerHTML='<span style="color:#555">Ingen aktive RSA-annoncer fundet de seneste 30 dage.</span>'; return; }
        var fmtNum = function(n){ return Number(n||0).toLocaleString('da-DK'); };
        var fmtKr  = function(n){ return Number(n||0).toLocaleString('da-DK',{minimumFractionDigits:0,maximumFractionDigits:0})+' kr'; };
        content.innerHTML = '<div class="gads-ads-grid">'+d.map(function(ad){
          var hl=ad.headlines||[], ds=ad.descriptions||[], url=ad.final_url||'', host='';
          try{ host=new URL(url).hostname.replace('www.',''); }catch(e){}
          var ctrColor = (ad.ctr>=2)?'#4ade80':(ad.ctr>=1)?'#f59e0b':'#ef4444';
          return '<div class="gads-ad-card" style="display:flex;flex-direction:column;gap:0">'+
            // Badge + kampagne
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">'+
              '<span class="gads-ad-badge" style="background:rgba(66,133,244,.15);padding:2px 8px;border-radius:4px">RSA · Google Søgning</span>'+
              '<span style="font-size:10px;color:#555;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+ad.ad_group+'">'+(ad.ad_group||ad.campaign||'')+'</span>'+
            '</div>'+
            // Google ad preview
            '<div style="background:#0d0d0d;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px;margin-bottom:10px">'+
              '<div style="color:#4ade80;font-size:11px;margin-bottom:3px">📢 Annonce · '+host+'</div>'+
              '<div style="color:#4285F4;font-size:15px;font-weight:700;line-height:1.4;margin-bottom:6px">'+hl.slice(0,3).join(' | ')+'</div>'+
              '<div style="color:#aaa;font-size:12px;line-height:1.6">'+ds.slice(0,2).join(' · ')+'</div>'+
            '</div>'+
            // KPI-række
            '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:10px">'+
              '<div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">'+
                '<div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">Visninger</div>'+
                '<div style="font-size:13px;font-weight:700;color:#ccc">'+fmtNum(ad.impressions)+'</div>'+
              '</div>'+
              '<div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">'+
                '<div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">Klik</div>'+
                '<div style="font-size:13px;font-weight:700;color:#ccc">'+fmtNum(ad.clicks)+'</div>'+
              '</div>'+
              '<div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">'+
                '<div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">CTR</div>'+
                '<div style="font-size:13px;font-weight:700;color:'+ctrColor+'">'+Number(ad.ctr||0).toFixed(2)+'%</div>'+
              '</div>'+
              '<div style="background:#111;border-radius:6px;padding:6px 8px;text-align:center">'+
                '<div style="font-size:9px;color:#555;text-transform:uppercase;margin-bottom:2px">Forbrug</div>'+
                '<div style="font-size:13px;font-weight:700;color:#ccc">'+fmtKr(ad.spend)+'</div>'+
              '</div>'+
            '</div>'+
            // Links
            '<div style="display:flex;gap:8px;margin-top:auto">'+
              (ad.gads_url ? '<a href="'+ad.gads_url+'" target="_blank" rel="noopener" style="flex:1;text-align:center;background:rgba(66,133,244,.12);border:1px solid rgba(66,133,244,.3);border-radius:6px;padding:6px 10px;font-size:11px;color:#4285F4;text-decoration:none">🔗 Se i Google Ads</a>' : '')+
              (url ? '<a href="'+url+'" target="_blank" rel="noopener" style="flex:1;text-align:center;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);border-radius:6px;padding:6px 10px;font-size:11px;color:#4ade80;text-decoration:none">↗ Landingsside</a>' : '')+
            '</div>'+
          '</div>';
        }).join('')+'</div>';
      })
      .catch(function(e){
        nb.disabled=false; nb.textContent='📢 Hent annoncer';
        content.innerHTML='<span style="color:#ff6b6b">⚠️ Fejl: '+e.message+'</span>';
      });
    });
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded',initGadsAdsPatch); }
  else { initGadsAdsPatch(); }
})();
</script>

  <!-- Fakturaer -->
  <div class="rzpa-card" id="gads-invoices-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <h2 style="margin:0">🧾 Betalingshistorik</h2>
        <div class="rzpa-card-sub" style="margin:4px 0 0">Månedligt forbrug fra din Google Ads-konto</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <button id="gads-invoices-load" class="btn-ghost" style="font-size:12px">⬇ Hent betalinger</button>
        <button id="gads-invoices-csv" class="btn-ghost" style="font-size:12px;display:none">📥 Eksportér CSV</button>
        <button id="gads-invoices-pdf" class="btn-ghost" style="font-size:12px;display:none">🖨 Download PDF</button>
        <a href="https://ads.google.com/aw/billing/summary" target="_blank" class="btn-ghost" style="font-size:12px;text-decoration:none">🔗 Åbn i Google Ads</a>
      </div>
    </div>
    <div id="gads-invoices-content" style="color:#555;font-size:13px">
      Klik <strong style="color:#888">"Hent betalinger"</strong> for at indlæse din betalingshistorik.
    </div>
  </div>

</div>
