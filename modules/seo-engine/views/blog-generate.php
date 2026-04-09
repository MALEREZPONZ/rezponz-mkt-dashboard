<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$page_url      = admin_url( 'admin.php?page=rzpa-seo-blog-generate' );
$filter_status = sanitize_key( $_GET['status'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 25;
$offset        = ( $paged - 1 ) * $per_page;

$total  = 0;
$briefs = RZPA_SEO_DB::get_briefs( $filter_status ?: null, $per_page, $offset, $total );
$ai_ok  = RZPA_SEO_AI::is_configured();

$status_labels = [
    'draft'     => 'Kladde',
    'review'    => 'Review',
    'approved'  => 'Godkendt',
    'generated' => 'Genereret',
    'published' => 'Publiceret',
];

$log_total = 0;
$blog_logs = RZPA_SEO_DB::get_logs( 'blog', null, 20, 0, $log_total );
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">📝 Generér Blogindlæg</h1>
      <p class="rzpa-page-sub">Generer blogindlæg fra godkendte briefs</p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Blog genereret.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <?php if ( ! $ai_ok ) : ?>
    <div class="notice notice-warning">
      <p>AI er ikke konfigureret. Generering sker uden AI. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-seo-settings' ) ); ?>">Opsæt AI</a></p>
    </div>
  <?php endif; ?>

  <!-- Brief Selection -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="blog-gen-form">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_generate_blog">

    <!-- Filter + Options -->
    <div class="rzpa-card" style="margin-bottom:16px;">
      <div style="padding:16px;display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Filtrer status</label>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php foreach ( $status_labels as $sv => $sl ) :
              $active = ( $filter_status === $sv ) ? 'rzpa-btn-primary' : '';
            ?>
              <a href="<?php echo esc_url( add_query_arg( 'status', $sv, $page_url ) ); ?>"
                 class="rzpa-btn <?php echo esc_attr( $active ); ?>" style="font-size:12px;"><?php echo esc_html( $sl ); ?></a>
            <?php endforeach; ?>
            <a href="<?php echo esc_url( $page_url ); ?>" class="rzpa-btn<?php echo ! $filter_status ? ' rzpa-btn-primary' : ''; ?>" style="font-size:12px;">Alle</a>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <div>
            <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Publiceringstatus</label>
            <select name="publish_status" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
              <option value="draft">Kladde</option>
              <option value="pending">Afventer gennemgang</option>
            </select>
          </div>
          <?php if ( $ai_ok ) : ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
              <input type="checkbox" name="use_ai" value="1" checked> Brug AI til generering
            </label>
          <?php else : ?>
            <input type="hidden" name="use_ai" value="0">
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="rzpa-card" style="margin-bottom:24px;">
      <?php if ( empty( $briefs ) ) : ?>
        <div class="rzpa-empty">
          <p>Ingen briefs fundet.</p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-seo-briefs&action=new' ) ); ?>" class="rzpa-btn rzpa-btn-primary">Opret brief</a>
        </div>
      <?php else : ?>
        <div style="padding:12px 16px;display:flex;gap:8px;align-items:center;border-bottom:1px solid var(--border);">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <input type="checkbox" id="select-all-briefs"> Vælg alle
          </label>
          <button type="submit" class="rzpa-btn rzpa-btn-primary">⚡ Generér valgte</button>
        </div>
        <table class="rzpa-table">
          <thead>
            <tr>
              <th style="width:32px;"></th>
              <th>Søgeord</th>
              <th>Intent</th>
              <th>Type</th>
              <th>Status</th>
              <th>Indlæg</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $briefs as $brief ) :
              $post_link = '';
              if ( ! empty( $brief['linked_post_id'] ) ) {
                  $post_link = '<a href="' . esc_url( get_edit_post_link( $brief['linked_post_id'] ) ) . '" target="_blank" style="color:var(--neon);">↗</a>';
              }
            ?>
              <tr>
                <td><input type="checkbox" name="brief_ids[]" value="<?php echo absint( $brief['id'] ); ?>" class="brief-cb"></td>
                <td>
                  <strong><?php echo esc_html( $brief['primary_keyword'] ); ?></strong>
                  <?php if ( $brief['audience'] ) : ?>
                    <br><small style="color:var(--text-muted);"><?php echo esc_html( $brief['audience'] ); ?></small>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;"><?php echo esc_html( $brief['intent'] ); ?></td>
                <td style="font-size:12px;"><?php echo esc_html( $brief['article_type'] ); ?></td>
                <td><span class="badge"><?php echo esc_html( $status_labels[ $brief['status'] ] ?? $brief['status'] ); ?></span></td>
                <td><?php echo wp_kses_post( $post_link ); ?></td>
              </tr>
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

  </form>

  <!-- Recent Blog Logs -->
  <div class="rzpa-card">
    <div class="rzpa-card-header">
      <h3 class="rzpa-card-title">Seneste Blog Logs</h3>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-seo-logs&content_type=blog' ) ); ?>" class="rzpa-btn" style="font-size:12px;">Se alle</a>
    </div>
    <?php if ( empty( $blog_logs ) ) : ?>
      <div class="rzpa-empty" style="padding:20px;">Ingen blog-logs endnu.</div>
    <?php else : ?>
      <table class="rzpa-table" style="font-size:12px;">
        <thead><tr><th>Tid</th><th>Objekt</th><th>Handling</th><th>Besked</th><th>Niveau</th></tr></thead>
        <tbody>
        <?php foreach ( $blog_logs as $log ) : ?>
          <tr>
            <td style="white-space:nowrap;color:var(--text-muted);"><?php echo esc_html( wp_date( 'd/m H:i', strtotime( $log['created_at'] ) ) ); ?></td>
            <td><?php echo $log['object_id'] ? absint( $log['object_id'] ) : '—'; ?></td>
            <td><?php echo esc_html( $log['action_type'] ); ?></td>
            <td><?php echo esc_html( wp_trim_words( $log['message'], 12 ) ); ?></td>
            <td><span class="badge <?php echo esc_attr( 'error' === $log['severity'] ? 'badge-error' : ( 'success' === $log['severity'] ? 'badge-active' : '' ) ); ?>"><?php echo esc_html( $log['severity'] ); ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
<script>
(function($){
  $('#select-all-briefs').on('change', function(){
    $('.brief-cb').prop('checked', $(this).prop('checked'));
  });
  // Single-brief form: override hidden brief_id with first checked
  $('#blog-gen-form').on('submit', function(e){
    var checked = $('.brief-cb:checked');
    if (checked.length === 1) {
      $(this).append($('<input type="hidden" name="brief_id">').val(checked.first().val()));
    }
  });
})(jQuery);
</script>
