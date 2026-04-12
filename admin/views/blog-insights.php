<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$opts       = get_option( 'rzpa_settings', [] );
$seo_ok     = ! empty( $opts['google_client_id'] ) && ! empty( $opts['google_refresh_token'] );
$has_openai = ! empty( $opts['openai_api_key'] );
?>
<div id="rzpa-app" data-rzpa-page="blog">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/Rezponz-logo.png' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Blog Indsigt</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Blog Indsigt</h1>
      <p class="page-sub">Alle blogindlæg fra rezponz.dk — Google-placering, AI-synlighed og anbefalinger</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-blog-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
    </div>
  </div>

  <?php if ( ! $seo_ok ) : ?>
  <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.25);border-radius:12px;padding:28px 32px;margin-bottom:24px">
    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="font-size:32px;flex-shrink:0">🔌</div>
      <div>
        <h2 style="margin:0 0 8px;font-size:18px;color:#fff">Google Search Console er ikke forbundet</h2>
        <p style="font-size:13px;color:#888;margin:0 0 16px;line-height:1.7">
          Forbind Google Search Console for at se præcise Google-placeringer for hvert blogindlæg.
          Blogindlæggene vises stadig, men uden rankingdata.
        </p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" class="btn-primary" style="text-decoration:none;display:inline-block">
          ⚙️ Forbind Google Search Console →
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- KPI-bar -->
  <div id="rzpa-blog-kpis" class="rzpa-kpi-grid" style="display:none"></div>

  <!-- Filter + søgning -->
  <div id="rzpa-blog-toolbar" class="rzpa-blog-toolbar" style="display:none">
    <div class="rzpa-blog-search-wrap">
      <input type="text" id="rzpa-blog-search" placeholder="🔍 Søg i blogindlæg…" class="rzpa-blog-search">
    </div>
    <div class="rzpa-blog-filters">
      <button class="rzpa-blog-filter active" data-filter="all">Alle</button>
      <button class="rzpa-blog-filter" data-filter="high">🔴 Høj prioritet</button>
      <button class="rzpa-blog-filter" data-filter="top1-3">🏆 Top 1-3</button>
      <button class="rzpa-blog-filter" data-filter="page1">✅ Side 1</button>
      <button class="rzpa-blog-filter" data-filter="no-gsc">⚠️ Ingen data</button>
      <button class="rzpa-blog-filter" data-filter="ai">🤖 AI-synlig</button>
    </div>
  </div>

  <!-- Inline blogdata (30 dage) – tilgængeligt synkront for JS, undgår API-kald ved pageload -->
  <div id="rzpa-blog-inline" data-days="30" data-posts="<?php echo esc_attr( wp_json_encode( RZPA_Database::get_blog_insights( 30 ) ) ); ?>" style="display:none"></div>

  <!-- Blog tabel -->
  <div id="rzpa-blog-content">
    <div class="rzpa-loading">Henter blogdata…</div>
  </div>

  <!-- ══ AI BLOG STRATEGI ══════════════════════════════════════════════════ -->
  <div class="rzpa-ai-strat-card">
    <div class="rzpa-ai-strat-header">
      <div class="rzpa-ai-strat-icon">🤖</div>
      <div>
        <div class="rzpa-ai-strat-title">AI Blog Strategi</div>
        <div class="rzpa-ai-strat-sub">
          Analyser hvilke blogindlæg Rezponz bør skrive for at ranke på jobrelevante søgeord —
          prioriteret efter søgevolumen, konkurrence og kommerciel værdi.
        </div>
      </div>
      <?php if ( $has_openai ) : ?>
      <button id="rzpa-blog-ai-btn" class="rzpa-ai-strat-btn">
        ✨ Generér AI Strategi
      </button>
      <?php else : ?>
      <a href="<?php echo esc_url( admin_url('admin.php?page=rzpa-settings') ); ?>"
         class="rzpa-ai-strat-btn rzpa-ai-strat-btn--warn">
        ⚙️ Tilføj OpenAI nøgle
      </a>
      <?php endif; ?>
    </div>

    <div id="rzpa-blog-ai-result" style="display:none"></div>
  </div>

  <style>
  /* ── AI Blog Strategi card ─────────────────────────────────── */
  .rzpa-ai-strat-card {
    background:#111;
    border:1px solid #CCFF0030;
    border-radius:14px;
    padding:28px 32px;
    margin-top:32px;
  }
  .rzpa-ai-strat-header {
    display:flex;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
  }
  .rzpa-ai-strat-icon { font-size:32px; flex-shrink:0; margin-top:2px; }
  .rzpa-ai-strat-title {
    font-size:18px;
    font-weight:700;
    color:#fff;
    margin-bottom:4px;
  }
  .rzpa-ai-strat-sub {
    font-size:13px;
    color:#888;
    line-height:1.6;
    max-width:560px;
  }
  .rzpa-ai-strat-btn {
    margin-left:auto;
    flex-shrink:0;
    background:#CCFF00;
    color:#0d0d0d;
    border:none;
    border-radius:8px;
    padding:10px 22px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
    transition:opacity .15s;
    white-space:nowrap;
    align-self:flex-start;
  }
  .rzpa-ai-strat-btn:hover { opacity:.85; }
  .rzpa-ai-strat-btn:disabled { opacity:.4; cursor:not-allowed; }
  .rzpa-ai-strat-btn--warn {
    background:#1e1e1e;
    color:#f59e0b;
    border:1px solid #f59e0b40;
  }

  /* ── Suggestions list ──────────────────────────────────────── */
  .rzpa-ai-suggestions {
    margin-top:28px;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  .rzpa-ai-sug-item {
    background:#1a1a1a;
    border:1px solid #2a2a2a;
    border-radius:10px;
    padding:18px 22px;
    display:grid;
    grid-template-columns:44px 1fr auto;
    gap:0 16px;
    align-items:start;
    transition:border-color .15s;
  }
  .rzpa-ai-sug-item:hover { border-color:#CCFF0040; }
  .rzpa-ai-sug-rank {
    width:44px;
    height:44px;
    border-radius:50%;
    background:#CCFF00;
    color:#0d0d0d;
    font-size:18px;
    font-weight:900;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    margin-top:2px;
  }
  .rzpa-ai-sug-rank.rank-1 { background:#CCFF00; }
  .rzpa-ai-sug-rank.rank-2 { background:#a8d800; }
  .rzpa-ai-sug-rank.rank-3 { background:#8bc400; }
  .rzpa-ai-sug-rank.rank-other { background:#1e1e1e; color:#CCFF00; border:2px solid #CCFF0040; }
  .rzpa-ai-sug-body { min-width:0; }
  .rzpa-ai-sug-title {
    font-size:15px;
    font-weight:700;
    color:#fff;
    margin-bottom:5px;
    line-height:1.35;
  }
  .rzpa-ai-sug-keyword {
    display:inline-block;
    background:#CCFF0015;
    color:#CCFF00;
    border:1px solid #CCFF0030;
    border-radius:5px;
    padding:2px 9px;
    font-size:11.5px;
    font-weight:700;
    letter-spacing:.3px;
    margin-bottom:10px;
  }
  .rzpa-ai-sug-desc {
    font-size:12.5px;
    color:#888;
    line-height:1.6;
    margin-bottom:8px;
  }
  .rzpa-ai-sug-angle {
    font-size:12px;
    color:#555;
    font-style:italic;
    border-left:2px solid #CCFF0040;
    padding-left:10px;
    line-height:1.5;
  }
  .rzpa-ai-sug-meta {
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    gap:6px;
    flex-shrink:0;
    min-width:110px;
  }
  .rzpa-ai-badge {
    padding:3px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
    white-space:nowrap;
  }
  .rzpa-ai-vol-high  { background:#CCFF0020; color:#CCFF00; border:1px solid #CCFF0040; }
  .rzpa-ai-vol-medium{ background:#f59e0b18; color:#f59e0b; border:1px solid #f59e0b40; }
  .rzpa-ai-vol-low   { background:#6b728018; color:#9ca3af; border:1px solid #6b728040; }
  .rzpa-ai-comp-low  { background:#4ade8018; color:#4ade80; border:1px solid #4ade8040; }
  .rzpa-ai-comp-medium{ background:#f59e0b18; color:#f59e0b; border:1px solid #f59e0b40; }
  .rzpa-ai-comp-high { background:#ef444418; color:#ef4444; border:1px solid #ef444440; }
  .rzpa-ai-score {
    font-size:22px;
    font-weight:900;
    color:#CCFF00;
    line-height:1;
  }
  .rzpa-ai-score-lbl {
    font-size:10px;
    color:#555;
    text-transform:uppercase;
    letter-spacing:.5px;
  }
  .rzpa-ai-strat-info {
    margin-top:16px;
    padding:12px 16px;
    background:#0d0d0d;
    border-radius:8px;
    font-size:12px;
    color:#555;
    display:flex;
    align-items:center;
    gap:8px;
  }
  .rzpa-index-btn {
    display:inline-block;
    margin-top:5px;
    font-size:11px;
    font-weight:600;
    padding:3px 10px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.15);
    background:rgba(255,255,255,.05);
    color:#aaa;
    cursor:pointer;
    transition:all .15s;
    font-family:inherit;
  }
  .rzpa-index-btn:hover:not(:disabled) {
    border-color:rgba(204,255,0,.4);
    color:var(--neon);
    background:rgba(204,255,0,.06);
  }
  .rzpa-index-btn:disabled { cursor:default; opacity:.7; }
  </style>

</div>

<?php if ( $has_openai ) : ?>
<script>
(function() {
  /* Inline handler — bypasser WP Fastest Cache (cached JS ignorerer ?ver=) */
  var API_URL  = <?php echo wp_json_encode( rest_url('rzpa/v1/blog/ai-suggestions') ); ?>;
  var NONCE    = <?php echo wp_json_encode( wp_create_nonce('wp_rest') ); ?>;

  function volClass(v)  { return v==='høj'?'rzpa-ai-vol-high':v==='medium'?'rzpa-ai-vol-medium':'rzpa-ai-vol-low'; }
  function compClass(c) { return c==='lav'?'rzpa-ai-comp-low':c==='medium'?'rzpa-ai-comp-medium':'rzpa-ai-comp-high'; }
  function volLabel(v)  { return {høj:'📈 Høj søgevolumen',medium:'📊 Medium søgevolumen',lav:'📉 Lav søgevolumen'}[v]||v; }
  function compLabel(c) { return {lav:'🟢 Lav konkurrence',medium:'🟡 Medium konkurrence',høj:'🔴 Høj konkurrence'}[c]||c; }
  function rankCls(n)   { return n===1?'rank-1':n===2?'rank-2':n===3?'rank-3':'rank-other'; }

  function renderSuggestions(suggestions) {
    var items = suggestions.map(function(s) {
      var n     = parseInt(s.priority)||0;
      var vol   = (s.search_volume||'').toLowerCase();
      var comp  = (s.competition  ||'').toLowerCase();
      var score = parseInt(s.value_score)||0;
      var searches = s.estimated_monthly_searches||'';
      return '<div class="rzpa-ai-sug-item">'
        + '<div class="rzpa-ai-sug-rank '+rankCls(n)+'">'+n+'</div>'
        + '<div class="rzpa-ai-sug-body">'
        +   '<div class="rzpa-ai-sug-title">'+(s.title||'–')+'</div>'
        +   '<div class="rzpa-ai-sug-keyword">🔑 '+(s.keyword||'–')+'</div>'
        +   '<div class="rzpa-ai-sug-desc">'
        +     '<strong style="color:#ccc">Søgeintention:</strong> '+(s.search_intent||'–')
        +     '<br><br><strong style="color:#ccc">Rezponz-værdi:</strong> '+(s.rezponz_value||'–')
        +   '</div>'
        +   (s.content_angle?'<div class="rzpa-ai-sug-angle">💡 Vinkel: '+s.content_angle+'</div>':'')
        + '</div>'
        + '<div class="rzpa-ai-sug-meta">'
        +   '<div><div class="rzpa-ai-score">'+score+'<span style="font-size:14px;color:#888">/10</span></div>'
        +     '<div class="rzpa-ai-score-lbl">Værdi</div></div>'
        +   (vol  ?'<span class="rzpa-ai-badge '+volClass(vol)+'">'+volLabel(vol)+'</span>':'')
        +   (comp ?'<span class="rzpa-ai-badge '+compClass(comp)+'">'+compLabel(comp)+'</span>':'')
        +   (searches?'<span style="font-size:11px;color:#555">~'+searches+'/md</span>':'')
        + '</div>'
        + '</div>';
    }).join('');

    var ts = new Date().toLocaleString('da-DK',{day:'numeric',month:'long',hour:'2-digit',minute:'2-digit'});
    return '<div class="rzpa-ai-suggestions">'+items+'</div>'
      + '<div class="rzpa-ai-strat-info">🤖 Genereret af GPT-4.1 mini · '+ts+' · Baseret på dine eksisterende blogindlæg og Rezponz\' rekrutteringsfokus</div>';
  }

  function getDays() {
    var active = document.querySelector('#rzpa-blog-date-filter [data-days].active');
    return active ? parseInt(active.dataset.days)||30 : 30;
  }

  function attachBtn() {
    var btn    = document.getElementById('rzpa-blog-ai-btn');
    var result = document.getElementById('rzpa-blog-ai-result');
    if (!btn || btn._rzpzAiBound) return;
    btn._rzpzAiBound = true;

    btn.addEventListener('click', function() {
      btn.disabled    = true;
      btn.textContent = '⏳ Analyserer…';
      result.style.display = 'none';

      fetch(API_URL + '?days=' + getDays(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce':   NONCE,
        },
        body: JSON.stringify({ days: getDays() }),
      })
      .then(function(res) {
        if (!res.ok) return res.json().then(function(e){ throw new Error(e.message||'HTTP '+res.status); });
        return res.json();
      })
      .then(function(r) {
        var sugs = r.data||r;
        if (!Array.isArray(sugs)||!sugs.length) throw new Error('Ingen forslag modtaget fra AI.');
        result.innerHTML    = renderSuggestions(sugs);
        result.style.display = 'block';
        btn.textContent = '✨ Generér igen';
      })
      .catch(function(err) {
        result.innerHTML    = '<div style="color:#ef4444;padding:20px 0;font-size:13px">❌ '+err.message+'</div>';
        result.style.display = 'block';
        btn.textContent = '✨ Generér AI Strategi';
      })
      .finally(function() {
        btn.disabled = false;
      });
    });
  }

  /* Kør straks + vent på DOMContentLoaded som fallback */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachBtn);
  } else {
    attachBtn();
  }
})();
</script>
<?php endif; ?>
