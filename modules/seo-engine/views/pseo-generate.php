<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$page_url = admin_url( 'admin.php?page=rzpa-seo-generate' );

// Filters
$filter_template = absint( $_GET['template_id'] ?? 0 );
$filter_group    = sanitize_text_field( $_GET['dataset_group'] ?? '' );
$filter_statuses = array_map( 'sanitize_key', (array) ( $_GET['gen_statuses'] ?? [ 'pending', 'failed' ] ) );
$paged           = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page        = 50;
$offset          = ( $paged - 1 ) * $per_page;

// Stats
$all_status_counts = RZPA_SEO_DB::count_by_status();
$ready     = ( $all_status_counts['pending'] ?? 0 ) + ( $all_status_counts['draft'] ?? 0 ) + ( $all_status_counts['approved'] ?? 0 );
$generated = ( $all_status_counts['published'] ?? 0 ) + ( $all_status_counts['review'] ?? 0 );
$failed    = $all_status_counts['failed'] ?? 0;

// Fetch datasets
$total    = 0;
$datasets = RZPA_SEO_DB::get_datasets(
    $filter_template ?: null,
    count( $filter_statuses ) === 1 ? $filter_statuses[0] : null,
    $filter_group ?: null,
    $per_page,
    $offset,
    $total
);

$all_templates = RZPA_SEO_DB::get_templates( 'pseo', 'active' );
$tpl_map = [];
foreach ( $all_templates as $t ) { $tpl_map[ $t['id'] ] = $t['name']; }

// Result notices
$bulk_result = sanitize_text_field( $_GET['bulk_result'] ?? '' );
$gen_status_labels = [
    'pending'   => 'Afventer',
    'draft'     => 'Kladde',
    'review'    => 'Review',
    'approved'  => 'Godkendt',
    'published' => 'Publiceret',
    'failed'    => 'Fejlet',
];
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">⚡ Generér pSEO Sider</h1>
      <p class="rzpa-page-sub">Generer WordPress sider ud fra godkendte datasæt</p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Side genereret.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <?php if ( $bulk_result ) :
    parse_str( str_replace( ',', '&', $bulk_result ), $br );
  ?>
    <div class="notice notice-success is-dismissible">
      <p><strong>Bulk generering fuldført:</strong>
        <?php echo absint( $br['generated'] ?? 0 ); ?> genereret,
        <?php echo absint( $br['updated']   ?? 0 ); ?> opdateret,
        <?php echo absint( $br['failed']    ?? 0 ); ?> fejlet.
      </p>
    </div>
  <?php endif; ?>

  <!-- Stats bar -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Klar til generering</div>
      <div class="rzpa-kpi-value"><?php echo esc_html( $ready ); ?></div>
    </div>
    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Allerede genereret</div>
      <div class="rzpa-kpi-value"><?php echo esc_html( $generated ); ?></div>
    </div>
    <div class="rzpa-kpi-card">
      <div class="rzpa-kpi-label">Fejlede</div>
      <div class="rzpa-kpi-value" style="color:<?php echo $failed > 0 ? '#f66' : 'inherit'; ?>"><?php echo esc_html( $failed ); ?></div>
    </div>
  </div>

  <!-- Filters -->
  <div class="rzpa-card" style="margin-bottom:16px;">
    <div style="padding:12px 16px;">
      <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <input type="hidden" name="page" value="rzpa-seo-generate">

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Template</label>
          <select name="template_id" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="">Alle</option>
            <?php foreach ( $all_templates as $tpl ) : ?>
              <option value="<?php echo absint( $tpl['id'] ); ?>"<?php selected( $filter_template, $tpl['id'] ); ?>>
                <?php echo esc_html( $tpl['name'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Dataset Gruppe</label>
          <input type="text" name="dataset_group" value="<?php echo esc_attr( $filter_group ); ?>"
                 placeholder="Alle grupper"
                 style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Status (vis)</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ( $gen_status_labels as $sv => $sl ) : ?>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;">
                <input type="checkbox" name="gen_statuses[]" value="<?php echo esc_attr( $sv ); ?>"
                       <?php checked( in_array( $sv, $filter_statuses, true ) ); ?>>
                <?php echo esc_html( $sl ); ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="rzpa-btn">Filtrer</button>
      </form>
    </div>
  </div>

  <!-- Dataset Table + Bulk Generate -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="generate-form">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_bulk_generate">

    <!-- Generation Options -->
    <div class="rzpa-card" style="margin-bottom:16px;">
      <div style="padding:16px;display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end;">
        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Publiceringstatus</label>
          <select name="publish_status" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="draft">Kladde</option>
            <option value="pending">Afventer gennemgang</option>
            <option value="publish">Publicér direkte</option>
          </select>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
          <input type="checkbox" name="regenerate_existing" value="1">
          Regenerér eksisterende sider
        </label>
        <div style="display:flex;gap:8px;margin-top:4px;">
          <button type="submit" class="rzpa-btn rzpa-btn-primary">⚡ Generér valgte</button>
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);">
            <input type="checkbox" id="select-all-gen"> Vælg alle
          </label>
        </div>
      </div>
    </div>

    <div class="rzpa-card">
      <?php if ( empty( $datasets ) ) : ?>
        <div class="rzpa-empty">
          <p>Ingen datasæt matcher filtret.</p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-seo-datasets&action=new' ) ); ?>" class="rzpa-btn rzpa-btn-primary">Opret datasæt</a>
        </div>
      <?php else : ?>
        <table class="rzpa-table">
          <thead>
            <tr>
              <th style="width:32px;"><input type="checkbox" id="select-all-gen-header"></th>
              <th>Søgeord</th>
              <th>By</th>
              <th>Template</th>
              <th>Status</th>
              <th>Kvalitet</th>
              <th>Side</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $datasets as $ds ) :
              $post_link = '';
              if ( ! empty( $ds['linked_post_id'] ) ) {
                  $post_link = '<a href="' . esc_url( get_edit_post_link( $ds['linked_post_id'] ) ) . '" target="_blank" style="color:var(--neon);">↗ Side</a>';
              }
            ?>
              <tr>
                <td><input type="checkbox" name="dataset_ids[]" value="<?php echo absint( $ds['id'] ); ?>" class="gen-cb"></td>
                <td><strong><?php echo esc_html( $ds['keyword'] ); ?></strong></td>
                <td><?php echo esc_html( $ds['city'] ); ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?php echo esc_html( $tpl_map[ $ds['template_id'] ] ?? '—' ); ?></td>
                <td><span class="badge"><?php echo esc_html( $gen_status_labels[ $ds['generation_status'] ] ?? $ds['generation_status'] ); ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);"><?php echo esc_html( $ds['quality_status'] ); ?></td>
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

  <!-- Recent Logs -->
  <div class="rzpa-card" style="margin-top:24px;">
    <div class="rzpa-card-header">
      <h3 class="rzpa-card-title">Seneste Genereringslogs</h3>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-seo-logs&content_type=pseo' ) ); ?>" class="rzpa-btn" style="font-size:12px;">Se alle</a>
    </div>
    <?php
    $log_total = 0;
    $gen_logs  = RZPA_SEO_DB::get_logs( 'pseo', null, 20, 0, $log_total );
    ?>
    <?php if ( empty( $gen_logs ) ) : ?>
      <div class="rzpa-empty" style="padding:20px;">Ingen logs endnu.</div>
    <?php else : ?>
      <table class="rzpa-table" style="font-size:12px;">
        <thead><tr><th>Tid</th><th>Objekt</th><th>Handling</th><th>Besked</th><th>Niveau</th></tr></thead>
        <tbody>
        <?php foreach ( $gen_logs as $log ) : ?>
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
  $('#select-all-gen, #select-all-gen-header').on('change', function(){
    $('.gen-cb').prop('checked', $(this).prop('checked'));
  });
})(jQuery);
</script>
