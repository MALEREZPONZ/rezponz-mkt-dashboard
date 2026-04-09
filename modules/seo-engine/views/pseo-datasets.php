<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$page_url = admin_url( 'admin.php?page=rzpa-seo-datasets' );

// Filters from GET
$filter_template = absint( $_GET['template_id'] ?? 0 );
$filter_status   = sanitize_key( $_GET['generation_status'] ?? '' );
$filter_group    = sanitize_text_field( $_GET['dataset_group'] ?? '' );
$filter_quality  = sanitize_key( $_GET['quality_status'] ?? '' );
$filter_search   = sanitize_text_field( $_GET['s'] ?? '' );
$paged           = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page        = 25;
$offset          = ( $paged - 1 ) * $per_page;

// Fetch
$total    = 0;
$datasets = RZPA_SEO_DB::get_datasets(
    $filter_template ?: null,
    $filter_status   ?: null,
    $filter_group    ?: null,
    $per_page,
    $offset,
    $total
);

$all_templates = RZPA_SEO_DB::get_templates();

// Generation status config
$gen_status_labels = [
    'pending'   => [ 'Afventer',    'badge-gray' ],
    'draft'     => [ 'Kladde',      'badge-yellow' ],
    'review'    => [ 'Review',      'badge-blue' ],
    'approved'  => [ 'Godkendt',    'badge-purple' ],
    'published' => [ 'Publiceret',  'badge-active' ],
    'failed'    => [ 'Fejlet',      'badge-error' ],
];
$qual_status_labels = [
    'unchecked'         => [ 'Utjekket',     'badge-gray' ],
    'passed'            => [ 'Bestået',       'badge-active' ],
    'needs_improvement' => [ 'Forbedres',     'badge-yellow' ],
    'failed'            => [ 'Fejlet',        'badge-error' ],
];

// Parse result notices
$import_result = sanitize_text_field( $_GET['import_result'] ?? '' );
$bulk_result   = sanitize_text_field( $_GET['bulk_result']   ?? '' );
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">pSEO Datasæt</h1>
      <p class="rzpa-page-sub"><?php echo esc_html( $total ); ?> datasæt i alt</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="<?php echo esc_url( $page_url . '&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Nyt Dataset</a>
      <button type="button" class="rzpa-btn" id="show-import-modal">📤 CSV Import</button>
      <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=rzpa_seo_csv_export&_wpnonce=' . wp_create_nonce( RZPA_SEO_Admin::NONCE_ACTION ) ) ); ?>"
         class="rzpa-btn">📥 Eksportér CSV</a>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Ændring gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <?php if ( $import_result ) :
    parse_str( str_replace( ',', '&', $import_result ), $ir );
  ?>
    <div class="notice notice-success is-dismissible">
      <p>Import fuldført: <?php echo absint( $ir['imported'] ?? 0 ); ?> importeret,
         <?php echo absint( $ir['updated'] ?? 0 ); ?> opdateret,
         <?php echo absint( $ir['failed'] ?? 0 ); ?> fejlet.</p>
    </div>
  <?php endif; ?>

  <?php if ( $bulk_result ) :
    parse_str( str_replace( ',', '&', $bulk_result ), $br );
  ?>
    <div class="notice notice-success is-dismissible">
      <p>Bulk generering fuldført: <?php echo absint( $br['generated'] ?? 0 ); ?> genereret,
         <?php echo absint( $br['updated'] ?? 0 ); ?> opdateret,
         <?php echo absint( $br['failed'] ?? 0 ); ?> fejlet.</p>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="rzpa-card" style="margin-bottom:16px;">
    <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
      <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;width:100%;">
        <input type="hidden" name="page" value="rzpa-seo-datasets">

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Søg</label>
          <input type="text" name="s" value="<?php echo esc_attr( $filter_search ); ?>"
                 placeholder="Søgeord, by..."
                 style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Template</label>
          <select name="template_id" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="">Alle templates</option>
            <?php foreach ( $all_templates as $tpl ) : ?>
              <option value="<?php echo absint( $tpl['id'] ); ?>"<?php selected( $filter_template, $tpl['id'] ); ?>>
                <?php echo esc_html( $tpl['name'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Genereringsstatus</label>
          <select name="generation_status" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="">Alle</option>
            <?php foreach ( $gen_status_labels as $val => [ $lbl, ] ) : ?>
              <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $filter_status, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:11px;color:var(--text-muted);margin-bottom:4px;">Kvalitet</label>
          <select name="quality_status" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:var(--radius);font-size:13px;">
            <option value="">Alle</option>
            <?php foreach ( $qual_status_labels as $val => [ $lbl, ] ) : ?>
              <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $filter_quality, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="rzpa-btn">Filtrer</button>
        <a href="<?php echo esc_url( $page_url ); ?>" class="rzpa-btn" style="color:var(--text-muted);">Nulstil</a>
      </form>
    </div>
  </div>

  <!-- Bulk Actions + Table -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bulk-form">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_bulk_generate">

    <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
      <label style="font-size:13px;color:var(--text-muted);">
        <input type="checkbox" id="select-all-datasets"> Vælg alle
      </label>
      <select name="publish_status" style="background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:5px 10px;border-radius:var(--radius);font-size:13px;">
        <option value="draft">Kladde</option>
        <option value="pending">Afventer gennemgang</option>
        <option value="publish">Publicér</option>
      </select>
      <button type="submit" class="rzpa-btn rzpa-btn-primary">⚡ Generér valgte</button>
    </div>

    <div class="rzpa-card">
      <?php if ( empty( $datasets ) ) : ?>
        <div class="rzpa-empty">
          <p>Ingen datasæt fundet.</p>
          <a href="<?php echo esc_url( $page_url . '&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Opret nyt datasæt</a>
        </div>
      <?php else : ?>
        <table class="rzpa-table">
          <thead>
            <tr>
              <th style="width:32px;"><input type="checkbox" id="select-all-header"></th>
              <th>ID</th>
              <th>Søgeord</th>
              <th>By</th>
              <th>Template</th>
              <th>Generering</th>
              <th>Kvalitet</th>
              <th>Indeksering</th>
              <th>Side</th>
              <th>Opdateret</th>
              <th>Handlinger</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $tpl_map = [];
            foreach ( $all_templates as $t ) { $tpl_map[ $t['id'] ] = $t['name']; }

            foreach ( $datasets as $ds ) :
              [ $gen_lbl, $gen_cls ] = $gen_status_labels[ $ds['generation_status'] ] ?? [ $ds['generation_status'], '' ];
              [ $q_lbl, $q_cls ]     = $qual_status_labels[ $ds['quality_status'] ]    ?? [ $ds['quality_status'], '' ];
              $post_link = '';
              if ( ! empty( $ds['linked_post_id'] ) ) {
                  $post_link = '<a href="' . esc_url( get_edit_post_link( $ds['linked_post_id'] ) ) . '" target="_blank" style="color:var(--neon);" title="Rediger side">↗</a>';
              }
            ?>
              <tr>
                <td><input type="checkbox" name="dataset_ids[]" value="<?php echo absint( $ds['id'] ); ?>" class="dataset-cb"></td>
                <td style="color:var(--text-muted);font-size:12px;"><?php echo absint( $ds['id'] ); ?></td>
                <td><strong><?php echo esc_html( $ds['keyword'] ); ?></strong></td>
                <td><?php echo esc_html( $ds['city'] ); ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?php echo esc_html( $tpl_map[ $ds['template_id'] ] ?? '—' ); ?></td>
                <td><span class="badge <?php echo esc_attr( $gen_cls ); ?>"><?php echo esc_html( $gen_lbl ); ?></span></td>
                <td><span class="badge <?php echo esc_attr( $q_cls ); ?>"><?php echo esc_html( $q_lbl ); ?></span></td>
                <td style="font-size:12px;"><?php echo $ds['indexation_status'] ? '✓' : '✗'; ?></td>
                <td><?php echo wp_kses_post( $post_link ); ?></td>
                <td style="color:var(--text-muted);font-size:11px;"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $ds['updated_at'] ) ) ); ?></td>
                <td>
                  <a href="<?php echo esc_url( $page_url . '&action=edit&id=' . absint( $ds['id'] ) ); ?>"
                     class="rzpa-btn" style="font-size:11px;">Rediger</a>

                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                    <input type="hidden" name="action" value="rzpa_seo_generate_page">
                    <input type="hidden" name="dataset_id" value="<?php echo absint( $ds['id'] ); ?>">
                    <button type="submit" class="rzpa-btn" style="font-size:11px;">⚡ Generér</button>
                  </form>

                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        style="display:inline;" onsubmit="return confirm('Slet dette datasæt?');">
                    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                    <input type="hidden" name="action" value="rzpa_seo_delete_dataset">
                    <input type="hidden" name="id" value="<?php echo absint( $ds['id'] ); ?>">
                    <button type="submit" class="rzpa-btn" style="font-size:11px;color:var(--text-muted);">Slet</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total > $per_page ) :
          $pages = ceil( $total / $per_page );
        ?>
          <div style="padding:12px 16px;display:flex;gap:6px;align-items:center;border-top:1px solid var(--border);">
            <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
              <a href="<?php echo esc_url( add_query_arg( 'paged', $p, $page_url ) ); ?>"
                 class="rzpa-btn<?php echo ( $p === $paged ) ? ' rzpa-btn-primary' : ''; ?>"
                 style="font-size:12px;padding:4px 10px;"><?php echo esc_html( $p ); ?></a>
            <?php endfor; ?>
            <span style="font-size:12px;color:var(--text-muted);"><?php echo esc_html( $total ); ?> total</span>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </form>

</div>

<!-- CSV Import Modal -->
<div id="import-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:99999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-100);border:1px solid var(--border);border-radius:var(--radius);padding:32px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto;">
    <h2 style="margin:0 0 20px;font-size:18px;">📤 CSV Import</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
      <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
      <input type="hidden" name="action" value="rzpa_seo_csv_import">

      <div style="border:2px dashed var(--border);border-radius:var(--radius);padding:32px;text-align:center;margin-bottom:20px;">
        <p style="color:var(--text-muted);margin-bottom:12px;">Træk CSV fil hertil eller klik for at vælge</p>
        <input type="file" name="csv_file" accept=".csv" required style="margin:auto;">
      </div>

      <div style="margin-bottom:16px;">
        <h4 style="font-size:13px;margin-bottom:8px;">Understøttede kolonner:</h4>
        <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:11px;">
          <?php
          $csv_cols = [ 'keyword', 'primary_keyword', 'city', 'region', 'area', 'job_type', 'category', 'meta_title', 'meta_description', 'template_slug', 'dataset_group' ];
          foreach ( $csv_cols as $col ) :
          ?>
            <code style="background:var(--bg-300);padding:2px 6px;border-radius:4px;"><?php echo esc_html( $col ); ?></code>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-size:13px;color:var(--text-muted);margin-bottom:8px;display:block;">Ved dubletter:</label>
        <label style="display:flex;align-items:center;gap:6px;margin-bottom:6px;font-size:13px;">
          <input type="radio" name="on_duplicate" value="skip" checked> Spring over
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
          <input type="radio" name="on_duplicate" value="update"> Opdatér eksisterende
        </label>
      </div>

      <div style="display:flex;gap:8px;">
        <button type="submit" class="rzpa-btn rzpa-btn-primary">📤 Importér</button>
        <button type="button" class="rzpa-btn" id="close-import-modal">Annuller</button>
      </div>
    </form>
  </div>
</div>

<script>
(function($){
  $('#select-all-datasets, #select-all-header').on('change', function(){
    $('.dataset-cb').prop('checked', $(this).prop('checked'));
  });
  $('#show-import-modal').on('click', function(){
    $('#import-modal').css('display','flex');
  });
  $('#close-import-modal').on('click', function(){
    $('#import-modal').hide();
  });
})(jQuery);
</script>
