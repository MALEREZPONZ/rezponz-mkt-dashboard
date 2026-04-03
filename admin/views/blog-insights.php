<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$opts       = get_option( 'rzpa_settings', [] );
$seo_ok     = ! empty( $opts['google_client_id'] ) && ! empty( $opts['google_refresh_token'] );
$has_openai = ! empty( $opts['openai_api_key'] );
?>
<div id="rzpa-app" data-rzpa-page="blog">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
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

  <!-- Blog tabel -->
  <div id="rzpa-blog-content">
    <div class="rzpa-loading">Henter blogdata…</div>
  </div>

</div>
