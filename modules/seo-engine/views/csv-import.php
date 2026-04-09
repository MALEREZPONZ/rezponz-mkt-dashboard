<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$back_url       = admin_url( 'admin.php?page=rzpa-seo-datasets' );
$import_result  = sanitize_text_field( $_GET['import_result'] ?? '' );

// Parse result string: "imported:X,updated:Y,failed:Z"
$result_data = [];
if ( $import_result ) {
    parse_str( str_replace( ',', '&', $import_result ), $result_data );
}

// Supported columns (static list matching what RZPA_SEO_CSV expects)
$supported_columns = class_exists( 'RZPA_SEO_CSV' )
    ? RZPA_SEO_CSV::DATASET_COLUMNS
    : [ 'keyword', 'primary_keyword', 'city', 'region', 'area', 'template_id', 'template_name',
        'intent', 'tone_of_voice', 'article_type', 'target_length', 'faq_required',
        'cta_type', 'meta_title', 'meta_description', 'slug', 'status', 'cluster_reference',
        'secondary_keywords', 'audience' ];

$required_columns = [ 'keyword', 'primary_keyword' ];

// Determine current step
$current_step = $import_result ? 4 : 1;
$steps = [
    1 => 'Upload',
    2 => 'Mapping',
    3 => 'Preview',
    4 => 'Import',
];
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">📤 CSV Import – Datasæt</h1>
      <p class="rzpa-page-sub"><a href="<?php echo esc_url( $back_url ); ?>" style="color:var(--neon);">← Tilbage til datasæt</a></p>
    </div>
  </div>

  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible">
      <p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p>
    </div>
  <?php endif; ?>

  <!-- Import Results -->
  <?php if ( $import_result && ! empty( $result_data ) ) : ?>
    <div class="notice notice-success is-dismissible">
      <p>
        <strong>Import fuldført:</strong>
        <?php echo absint( $result_data['imported'] ?? 0 ); ?> importeret,
        <?php echo absint( $result_data['updated']  ?? 0 ); ?> opdateret,
        <?php echo absint( $result_data['failed']   ?? 0 ); ?> fejlet.
      </p>
    </div>
  <?php endif; ?>

  <!-- Step Indicator -->
  <div style="display:flex;align-items:center;gap:0;margin-bottom:32px;">
    <?php foreach ( $steps as $num => $label ) :
      $is_done   = $num < $current_step;
      $is_active = $num === $current_step;
      $step_color = $is_active ? 'var(--neon)' : ( $is_done ? 'var(--neon)' : 'var(--text-muted)' );
      $step_bg    = $is_active ? 'var(--neon)' : ( $is_done ? 'rgba(204,255,0,.2)' : 'var(--bg-200)' );
      $step_text  = $is_active ? 'var(--bg)' : ( $is_done ? 'var(--neon)' : 'var(--text-muted)' );
    ?>
      <div style="display:flex;align-items:center;">
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
          <div style="width:32px;height:32px;border-radius:50%;background:<?php echo esc_attr( $step_bg ); ?>;border:2px solid <?php echo esc_attr( $step_color ); ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:<?php echo esc_attr( $step_text ); ?>;">
            <?php echo $is_done ? '✓' : $num; ?>
          </div>
          <span style="font-size:11px;color:<?php echo esc_attr( $step_color ); ?>;white-space:nowrap;"><?php echo esc_html( $label ); ?></span>
        </div>
        <?php if ( $num < count( $steps ) ) : ?>
          <div style="width:60px;height:2px;background:<?php echo $is_done ? 'var(--neon)' : 'var(--border)'; ?>;margin:0 4px;margin-bottom:18px;"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- STEP 1: Upload Form -->
  <div class="rzpa-card" style="margin-bottom:24px;">
    <div class="rzpa-card-header">
      <h3 class="rzpa-card-title">Trin 1: Upload CSV-fil</h3>
    </div>
    <div style="padding:24px;">

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
            enctype="multipart/form-data" id="csv-upload-form">
        <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
        <input type="hidden" name="action" value="rzpa_seo_csv_import">

        <!-- Dropzone -->
        <div id="csv-dropzone"
             style="border:2px dashed var(--border);border-radius:var(--radius);padding:48px 24px;text-align:center;cursor:pointer;transition:border-color .2s;margin-bottom:20px;background:var(--bg-200);">
          <div style="font-size:40px;margin-bottom:12px;">📂</div>
          <p style="font-size:15px;margin:0 0 8px;color:var(--text);">Træk og slip din CSV-fil her</p>
          <p style="font-size:13px;margin:0 0 16px;color:var(--text-muted);">eller</p>
          <label class="rzpa-btn rzpa-btn-primary" style="cursor:pointer;display:inline-block;">
            Vælg fil
            <input type="file" name="csv_file" id="csv-file-input" accept=".csv" style="display:none;" required>
          </label>
          <p id="csv-filename" style="margin:12px 0 0;font-size:12px;color:var(--neon);display:none;"></p>
        </div>

        <!-- Duplicate handling -->
        <div style="margin-bottom:20px;">
          <p style="font-size:13px;margin:0 0 8px;color:var(--text-muted);">Ved dubletter:</p>
          <div style="display:flex;gap:20px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
              <input type="radio" name="on_duplicate" value="skip" checked>
              Spring over (bevar eksisterende)
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
              <input type="radio" name="on_duplicate" value="update">
              Opdatér eksisterende
            </label>
          </div>
        </div>

        <div style="display:flex;gap:12px;align-items:center;">
          <button type="submit" class="rzpa-btn rzpa-btn-primary" id="upload-btn">📤 Upload og importér</button>
          <span style="font-size:12px;color:var(--text-muted);">Filen analyseres og importeres på én gang.</span>
        </div>

      </form>

    </div>
  </div>

  <!-- Supported Columns -->
  <div style="display:grid;grid-template-columns:3fr 2fr;gap:24px;align-items:start;">

    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">Understøttede kolonner</h3></div>
      <div style="padding:16px 20px;">
        <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">
          Kolonnerne skal være i første række (headers). Rækkefølgen er ligegyldig.
          <span style="color:var(--neon);">*</span> = påkrævet.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          <?php foreach ( $supported_columns as $col ) :
            $is_required = in_array( $col, $required_columns, true );
          ?>
            <span style="background:var(--bg-200);border:1px solid <?php echo $is_required ? 'var(--neon)' : 'var(--border)'; ?>;border-radius:4px;padding:3px 8px;font-size:12px;font-family:monospace;color:<?php echo $is_required ? 'var(--neon)' : 'var(--text)'; ?>;">
              <?php echo esc_html( $col ); ?><?php echo $is_required ? '*' : ''; ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">Download skabelon</h3></div>
      <div style="padding:20px;">
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">
          Download en tom CSV med alle kolonneoverskrifter klar til udfyldning.
        </p>
        <a href="<?php echo esc_url( add_query_arg( [
            'action'   => 'rzpa_seo_download_csv_template',
            '_wpnonce' => wp_create_nonce( RZPA_SEO_Admin::NONCE_ACTION ),
        ], admin_url( 'admin-post.php' ) ) ); ?>"
           class="rzpa-btn rzpa-btn-primary">📥 Download CSV-skabelon</a>
        <p style="margin:10px 0 0;font-size:11px;color:var(--text-muted);">Indeholder headers for alle understøttede felter.</p>
      </div>
    </div>

  </div>

  <!-- Tips -->
  <div class="rzpa-card" style="margin-top:24px;">
    <div class="rzpa-card-header"><h3 class="rzpa-card-title">Tips</h3></div>
    <div style="padding:16px 20px;">
      <ul style="margin:0;padding:0 0 0 18px;display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--text-muted);">
        <li>Brug UTF-8 enkodning og komma (<code>,</code>) som separator.</li>
        <li>Datofelter accepteres i formatet <code>YYYY-MM-DD</code>.</li>
        <li>Booleske felter (fx <code>faq_required</code>): brug <code>1</code>/<code>0</code> eller <code>true</code>/<code>false</code>.</li>
        <li>Tomme kolonner ignoreres — eksisterende data bevares ved "spring over".</li>
        <li>Max. anbefalet filstørrelse: 5 MB / ~10.000 rækker pr. upload.</li>
      </ul>
    </div>
  </div>

</div>
<script>
(function($){
  // Dropzone hover styling
  var $dz = $('#csv-dropzone');
  $dz.on('dragover dragenter', function(e){
    e.preventDefault();
    $dz.css('border-color', 'var(--neon)');
  }).on('dragleave drop', function(e){
    e.preventDefault();
    $dz.css('border-color', 'var(--border)');
    if (e.type === 'drop') {
      var files = e.originalEvent.dataTransfer.files;
      if (files.length) {
        $('#csv-file-input')[0].files = files;
        showFilename(files[0].name);
      }
    }
  });

  $('#csv-file-input').on('change', function(){
    if (this.files.length) showFilename(this.files[0].name);
  });

  function showFilename(name) {
    $('#csv-filename').text('Valgt: ' + name).show();
    $dz.css('border-color', 'var(--neon)');
  }
})(jQuery);
</script>
