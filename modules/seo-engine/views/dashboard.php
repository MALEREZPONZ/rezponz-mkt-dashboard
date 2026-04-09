<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

// ── Fetch data ────────────────────────────────────────────────────────────────
$status_counts  = RZPA_SEO_DB::count_by_status();
$brief_counts   = RZPA_SEO_DB::count_briefs_by_status();
$total_datasets = array_sum( $status_counts );
$total_briefs   = array_sum( $brief_counts );

$pseo_query  = new WP_Query( [ 'post_type' => 'rzpa_pseo', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] );
$total_pseo  = $pseo_query->found_posts;

$pseo_published = new WP_Query( [ 'post_type' => 'rzpa_pseo', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ] );
$pseo_draft     = new WP_Query( [ 'post_type' => 'rzpa_pseo', 'post_status' => 'draft',   'posts_per_page' => -1, 'fields' => 'ids' ] );

$logs_total = 0;
$recent_logs = RZPA_SEO_DB::get_logs( null, null, 10, 0, $logs_total );

$settings    = get_option( 'rzpa_seo_settings', [] );
$rewrite_base = $settings['rewrite_base'] ?? 'job';
$ai_ok       = RZPA_SEO_AI::is_configured();
$db_version  = get_option( RZPA_SEO_DB::DB_VERSION_KEY, '—' );
$cpt_ok      = post_type_exists( 'rzpa_pseo' );

$base_url = admin_url( 'admin.php' );
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">🔍 SEO Engine Dashboard</h1>
      <p class="rzpa-page-sub">Overblik over programmatisk SEO, blog briefs og generering</p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Ændringer gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">

    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">pSEO Sider</div>
      <div class="rzpa-kpi-value"><?php echo esc_html( $total_pseo ); ?></div>
      <div class="rzpa-kpi-meta">
        <span><?php echo esc_html( $pseo_published->found_posts ); ?> publiceret</span>
        &nbsp;·&nbsp;
        <span><?php echo esc_html( $pseo_draft->found_posts ); ?> kladder</span>
      </div>
    </div>

    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Datasæt</div>
      <div class="rzpa-kpi-value"><?php echo esc_html( $total_datasets ); ?></div>
      <div class="rzpa-kpi-meta">
        <span><?php echo esc_html( $status_counts['published'] ?? 0 ); ?> pub.</span>
        &nbsp;·&nbsp;
        <span><?php echo esc_html( $status_counts['pending'] ?? 0 ); ?> afventer</span>
        &nbsp;·&nbsp;
        <span><?php echo esc_html( $status_counts['failed'] ?? 0 ); ?> fejlede</span>
      </div>
    </div>

    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Blog Briefs</div>
      <div class="rzpa-kpi-value"><?php echo esc_html( $total_briefs ); ?></div>
      <div class="rzpa-kpi-meta">
        <span><?php echo esc_html( $brief_counts['approved'] ?? 0 ); ?> godkendt</span>
        &nbsp;·&nbsp;
        <span><?php echo esc_html( $brief_counts['generated'] ?? 0 ); ?> genereret</span>
      </div>
    </div>

    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Kvalitetsscore</div>
      <?php
      $total_checked = ( $status_counts['published'] ?? 0 ) + ( $status_counts['review'] ?? 0 ) + ( $status_counts['approved'] ?? 0 );
      $total_all     = $total_datasets ?: 1;
      $pct           = $total_all > 0 ? round( $total_checked / $total_all * 100 ) : 0;
      ?>
      <div class="rzpa-kpi-value"><?php echo esc_html( $pct ); ?>%</div>
      <div class="rzpa-kpi-meta">Sider der har bestået kvalitetstjek</div>
    </div>

  </div>

  <!-- Quick Actions -->
  <div class="rzpa-card" style="margin-bottom:24px;">
    <div class="rzpa-card-header">
      <h3 class="rzpa-card-title">Hurtige Handlinger</h3>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:16px;">
      <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-datasets&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Nyt Dataset</a>
      <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-generate' ); ?>" class="rzpa-btn rzpa-btn-primary">⚡ Generér Sider</a>
      <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-briefs&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">📝 Nyt Brief</a>
      <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-datasets&tab=import' ); ?>" class="rzpa-btn">📤 CSV Import</a>
      <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-links' ); ?>" class="rzpa-btn">🔗 Link Regler</a>
      <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-logs' ); ?>" class="rzpa-btn">📊 Logs</a>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

    <!-- Recent Activity -->
    <div class="rzpa-card">
      <div class="rzpa-card-header">
        <h3 class="rzpa-card-title">Seneste Aktivitet</h3>
        <a href="<?php echo esc_url( $base_url . '?page=rzpa-seo-logs' ); ?>" class="rzpa-btn" style="font-size:12px;">Se alle</a>
      </div>
      <?php if ( empty( $recent_logs ) ) : ?>
        <div class="rzpa-empty" style="padding:20px;">Ingen logposter endnu.</div>
      <?php else : ?>
        <table class="rzpa-table" style="font-size:13px;">
          <thead><tr><th>Tid</th><th>Type</th><th>Besked</th></tr></thead>
          <tbody>
          <?php foreach ( $recent_logs as $log ) :
            $sev_class = match( $log['severity'] ) {
              'success' => 'badge-active',
              'warning' => 'badge-paused',
              'error'   => 'badge-error',
              default   => '',
            };
          ?>
            <tr>
              <td style="white-space:nowrap;color:var(--text-muted);font-size:11px;">
                <?php echo esc_html( wp_date( 'd/m H:i', strtotime( $log['created_at'] ) ) ); ?>
              </td>
              <td><span class="badge <?php echo esc_attr( $sev_class ); ?>"><?php echo esc_html( $log['content_type'] ); ?></span></td>
              <td><?php echo esc_html( wp_trim_words( $log['message'], 10 ) ); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Status Overview -->
    <div class="rzpa-card">
      <div class="rzpa-card-header">
        <h3 class="rzpa-card-title">Datasæt Status</h3>
      </div>
      <table class="rzpa-table" style="font-size:13px;">
        <thead><tr><th>Status</th><th>Antal</th></tr></thead>
        <tbody>
        <?php
        $status_labels = [
          'pending'    => 'Afventer',
          'draft'      => 'Kladde',
          'review'     => 'Til review',
          'approved'   => 'Godkendt',
          'published'  => 'Publiceret',
          'failed'     => 'Fejlet',
        ];
        foreach ( $status_labels as $key => $label ) :
          $count = $status_counts[ $key ] ?? 0;
        ?>
          <tr>
            <td><?php echo esc_html( $label ); ?></td>
            <td><strong><?php echo esc_html( $count ); ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- System Status -->
  <div class="rzpa-card">
    <div class="rzpa-card-header">
      <h3 class="rzpa-card-title">System Status</h3>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;padding:16px;">
      <div>
        <div style="color:var(--text-muted);font-size:12px;margin-bottom:4px;">CPT registreret</div>
        <span class="badge <?php echo $cpt_ok ? 'badge-active' : 'badge-paused'; ?>">
          <?php echo $cpt_ok ? 'Ja' : 'Nej'; ?>
        </span>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:12px;margin-bottom:4px;">Rewrite Base</div>
        <code style="background:var(--bg-300);padding:2px 6px;border-radius:4px;font-size:12px;">
          <?php echo esc_html( $rewrite_base ?: 'job' ); ?>
        </code>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:12px;margin-bottom:4px;">AI Konfigureret</div>
        <span class="badge <?php echo $ai_ok ? 'badge-active' : ''; ?>">
          <?php echo $ai_ok ? 'Ja – ' . esc_html( RZPA_SEO_AI::get_provider() ) : 'Nej'; ?>
        </span>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:12px;margin-bottom:4px;">DB Version</div>
        <code style="background:var(--bg-300);padding:2px 6px;border-radius:4px;font-size:12px;">
          v<?php echo esc_html( $db_version ); ?>
        </code>
      </div>
    </div>
  </div>

</div>
