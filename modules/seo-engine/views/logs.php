<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$per_page    = 50;
$paged       = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset      = ( $paged - 1 ) * $per_page;
$type_filter = sanitize_text_field( $_GET['content_type'] ?? '' );
$sev_filter  = sanitize_text_field( $_GET['severity'] ?? '' );
$date_from   = sanitize_text_field( $_GET['date_from'] ?? '' );
$date_to     = sanitize_text_field( $_GET['date_to'] ?? '' );

$total = 0;
$logs  = RZPA_SEO_DB::get_logs( $type_filter ?: null, $sev_filter ?: null, $per_page, $offset, $total );

// Stats (last 30 days)
$stats_total = 0;
$all_logs    = RZPA_SEO_DB::get_logs( null, null, 9999, 0, $stats_total );
$cutoff      = strtotime( '-30 days' );
$errors      = 0;
$warnings    = 0;
foreach ( $all_logs as $l ) {
    if ( strtotime( $l['created_at'] ) < $cutoff ) continue;
    if ( 'error'   === $l['severity'] ) $errors++;
    if ( 'warning' === $l['severity'] ) $warnings++;
}

$page_url = admin_url( 'admin.php?page=rzpa-seo-logs' );

$sev_row_styles = [
    'info'    => '',
    'success' => 'background:rgba(0,200,80,.04);',
    'warning' => 'background:rgba(255,160,0,.06);',
    'error'   => 'background:rgba(220,50,50,.08);',
];
$sev_badge_cls = [
    'info'    => '',
    'success' => 'badge-active',
    'warning' => 'badge-warning',
    'error'   => 'badge-error',
];
$type_badge_cls = [
    'pseo'    => 'badge-active',
    'blog'    => 'badge-blue',
    'linking' => '',
    'import'  => 'badge-paused',
];
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">📋 Logs &amp; Aktivitet</h1>
      <p class="rzpa-page-sub"><?php echo esc_html( number_format( $stats_total ) ); ?> logs i alt</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'rzpa_seo_export_logs_csv', '_wpnonce' => wp_create_nonce( RZPA_SEO_Admin::NONCE_ACTION ) ], admin_url( 'admin-post.php' ) ) ); ?>"
         class="rzpa-btn">📥 Eksportér CSV</a>
    </div>
  </div>

  <?php if ( isset( $_GET['cleared'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Gamle logs slettet.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <!-- Stats row -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Logs i alt (30 dage)</div>
      <div class="rzpa-kpi-value"><?php echo esc_html( number_format( $stats_total ) ); ?></div>
    </div>
    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Fejl (30 dage)</div>
      <div class="rzpa-kpi-value" style="color:<?php echo $errors > 0 ? '#f66' : 'inherit'; ?>"><?php echo esc_html( $errors ); ?></div>
    </div>
    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Advarsler (30 dage)</div>
      <div class="rzpa-kpi-value" style="color:<?php echo $warnings > 0 ? '#fa0' : 'inherit'; ?>"><?php echo esc_html( $warnings ); ?></div>
    </div>
  </div>

  <!-- Filters -->
  <div class="rzpa-card" style="margin-bottom:16px;">
    <div style="padding:12px 16px;">
      <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <input type="hidden" name="page" value="rzpa-seo-logs">

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Type</label>
          <select name="content_type" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="">Alle</option>
            <?php foreach ( [ 'pseo' => 'pSEO', 'blog' => 'Blog', 'linking' => 'Linking', 'import' => 'Import' ] as $tv => $tl ) : ?>
              <option value="<?php echo esc_attr( $tv ); ?>"<?php selected( $type_filter, $tv ); ?>><?php echo esc_html( $tl ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Sværhedsgrad</label>
          <select name="severity" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="">Alle</option>
            <?php foreach ( [ 'info' => 'Info', 'success' => 'Success', 'warning' => 'Advarsel', 'error' => 'Fejl' ] as $sv => $sl ) : ?>
              <option value="<?php echo esc_attr( $sv ); ?>"<?php selected( $sev_filter, $sv ); ?>><?php echo esc_html( $sl ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Fra dato</label>
          <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>"
                 style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Til dato</label>
          <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>"
                 style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
        </div>

        <button type="submit" class="rzpa-btn">Filtrer</button>
        <a href="<?php echo esc_url( $page_url ); ?>" class="rzpa-btn" style="color:var(--text-muted);">Nulstil</a>
      </form>
    </div>
  </div>

  <!-- Logs Table -->
  <div class="rzpa-card">
    <?php if ( empty( $logs ) ) : ?>
      <div class="rzpa-empty">
        <p>Ingen logs fundet for de valgte filtre.</p>
      </div>
    <?php else : ?>
      <table class="rzpa-table" style="font-size:12px;">
        <thead>
          <tr>
            <th style="white-space:nowrap;">Dato/Tid</th>
            <th>Type</th>
            <th>Objekt</th>
            <th>Handling</th>
            <th>Sværhedsgrad</th>
            <th>Besked</th>
            <th style="width:30px;"></th>
            <th>Bruger</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $logs as $i => $log ) :
            $row_style  = $sev_row_styles[ $log['severity'] ] ?? '';
            $badge_cls  = $sev_badge_cls[ $log['severity'] ] ?? '';
            $type_cls   = $type_badge_cls[ $log['content_type'] ?? '' ] ?? '';
            $context_id = 'ctx-' . $i;
            $has_ctx    = ! empty( $log['context'] );
            $msg_full   = $log['message'] ?? '';
            $msg_short  = mb_strlen( $msg_full ) > 100 ? mb_substr( $msg_full, 0, 100 ) . '…' : $msg_full;
          ?>
            <tr style="<?php echo esc_attr( $row_style ); ?>">
              <td style="white-space:nowrap;color:var(--text-muted);">
                <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log['created_at'] ) ) ); ?>
              </td>
              <td>
                <span class="badge <?php echo esc_attr( $type_cls ); ?>" style="font-size:11px;">
                  <?php echo esc_html( $log['content_type'] ?? '—' ); ?>
                </span>
              </td>
              <td>
                <?php if ( ! empty( $log['object_id'] ) ) :
                  $edit_link = get_edit_post_link( absint( $log['object_id'] ) );
                  if ( $edit_link ) : ?>
                    <a href="<?php echo esc_url( $edit_link ); ?>" target="_blank" style="color:var(--neon);">#<?php echo absint( $log['object_id'] ); ?> ↗</a>
                  <?php else : ?>
                    #<?php echo absint( $log['object_id'] ); ?>
                  <?php endif; ?>
                <?php else : ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td><?php echo esc_html( $log['action_type'] ?? '—' ); ?></td>
              <td>
                <span class="badge <?php echo esc_attr( $badge_cls ); ?>" style="font-size:11px;">
                  <?php echo esc_html( $log['severity'] ?? '—' ); ?>
                </span>
              </td>
              <td title="<?php echo esc_attr( $msg_full ); ?>"><?php echo esc_html( $msg_short ); ?></td>
              <td style="text-align:center;">
                <?php if ( $has_ctx ) : ?>
                  <button type="button" class="rzpa-btn ctx-toggle" data-target="<?php echo esc_attr( $context_id ); ?>"
                          style="font-size:11px;padding:2px 6px;">▶</button>
                <?php endif; ?>
              </td>
              <td style="color:var(--text-muted);">
                <?php
                $uid = absint( $log['user_id'] ?? 0 );
                echo $uid ? esc_html( get_userdata( $uid )->user_login ?? '#' . $uid ) : '—';
                ?>
              </td>
            </tr>
            <?php if ( $has_ctx ) : ?>
              <tr id="<?php echo esc_attr( $context_id ); ?>" style="display:none;">
                <td colspan="8" style="padding:0;">
                  <pre style="margin:0;padding:12px 16px;background:var(--bg-200);border-top:1px solid var(--border);font-size:11px;overflow-x:auto;color:var(--text-muted);"><?php echo esc_html( json_encode( json_decode( $log['context'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ( $total > $per_page ) :
        $pages = ceil( $total / $per_page );
      ?>
        <div style="padding:12px 16px;display:flex;gap:6px;border-top:1px solid var(--border);">
          <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $p, $page_url ) ); ?>"
               class="rzpa-btn<?php echo $p === $paged ? ' rzpa-btn-primary' : ''; ?>"
               style="font-size:12px;padding:4px 10px;"><?php echo $p; ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div style="margin-top:16px;display:flex;gap:12px;align-items:center;">
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
          onsubmit="return confirm('Slet alle logs ældre end 90 dage?');">
      <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
      <input type="hidden" name="action" value="rzpa_seo_clear_logs">
      <button type="submit" class="rzpa-btn" style="color:var(--text-muted);">🗑 Ryd logs &gt; 90 dage</button>
    </form>
    <span style="font-size:12px;color:var(--text-muted);">Rydder automatisk logs ældre end 90 dage.</span>
  </div>

</div>
<script>
(function($){
  $(document).on('click', '.ctx-toggle', function(){
    var $btn    = $(this);
    var $target = $('#' + $btn.data('target'));
    if ($target.is(':visible')) {
      $target.hide();
      $btn.text('▶');
    } else {
      $target.show();
      $btn.text('▼');
    }
  });
})(jQuery);
</script>
