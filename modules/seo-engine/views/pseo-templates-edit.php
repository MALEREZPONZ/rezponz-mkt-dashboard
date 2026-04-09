<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$id       = absint( $_GET['id'] ?? 0 );
$template = $id ? RZPA_SEO_DB::get_template( $id ) : null;
$is_edit  = null !== $template;

// Determine which page referred us (blog-templates or pseo-templates)
$from_page = sanitize_key( $_GET['page'] ?? 'rzpa-seo-templates' );
$back_url  = admin_url( 'admin.php?page=' . $from_page );

// Decode template_config
$cfg = [];
if ( $template && ! empty( $template['template_config'] ) ) {
    $cfg = json_decode( $template['template_config'], true ) ?: [];
}

$placeholders = RZPA_SEO_Template::PLACEHOLDERS;
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title"><?php echo $is_edit ? 'Rediger Template' : 'Nyt Template'; ?></h1>
      <p class="rzpa-page-sub">
        <a href="<?php echo esc_url( $back_url ); ?>" style="color:var(--neon);">← Tilbage til templates</a>
      </p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Template gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_save_template">
    <?php if ( $is_edit ) : ?>
      <input type="hidden" name="id" value="<?php echo absint( $template['id'] ); ?>">
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">

      <!-- Main Form -->
      <div>
        <div class="rzpa-card" style="margin-bottom:20px;">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Grundoplysninger</h3></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Navn <span style="color:var(--neon);">*</span></label>
              <input type="text" name="name" id="tpl-name" required
                     value="<?php echo esc_attr( $template['name'] ?? '' ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Slug</label>
              <input type="text" name="slug" id="tpl-slug"
                     value="<?php echo esc_attr( $template['slug'] ?? '' ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              <p style="font-size:11px;color:var(--text-muted);margin:4px 0 0;">Auto-genereres fra navn</p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Type</label>
                <select name="type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <option value="pseo"<?php selected( ( $template['type'] ?? 'pseo' ), 'pseo' ); ?>>pSEO</option>
                  <option value="blog"<?php selected( ( $template['type'] ?? '' ), 'blog' ); ?>>Blog</option>
                </select>
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Status</label>
                <select name="status" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <option value="active"<?php selected( ( $template['status'] ?? 'active' ), 'active' ); ?>>Aktiv</option>
                  <option value="inactive"<?php selected( ( $template['status'] ?? '' ), 'inactive' ); ?>>Inaktiv</option>
                  <option value="draft"<?php selected( ( $template['status'] ?? '' ), 'draft' ); ?>>Kladde</option>
                </select>
              </div>
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Beskrivelse</label>
              <textarea name="description" rows="3"
                        style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $template['description'] ?? '' ); ?></textarea>
            </div>

          </div>
        </div>

        <!-- Pattern Fields -->
        <div class="rzpa-card" style="margin-bottom:20px;">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Mønstre &amp; Skabeloner</h3></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

            <?php
            $pattern_fields = [
              'slug_pattern'         => [ 'Slug Mønster',           '{city}-{job_type}' ],
              'title_pattern'        => [ 'Titel Mønster',          '{job_type} i {city} | Rezponz' ],
              'h1_pattern'           => [ 'H1 Mønster',             '{job_type} i {city}' ],
              'meta_title_pattern'   => [ 'Meta Titel Mønster',     '{job_type} i {city} | Rezponz' ],
              'rewrite_base'         => [ 'Rewrite Base',           'job' ],
            ];
            foreach ( $pattern_fields as $key => [ $label, $placeholder ] ) :
              $max = in_array( $key, [ 'meta_title_pattern' ], true ) ? ' maxlength="70"' : '';
            ?>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);"><?php echo esc_html( $label ); ?></label>
                <input type="text" name="template_fields[<?php echo esc_attr( $key ); ?>]"
                       value="<?php echo esc_attr( $cfg[ $key ] ?? '' ); ?>"
                       placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $max; ?>
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                <?php if ( 'meta_title_pattern' === $key ) : ?>
                  <p style="font-size:11px;color:var(--text-muted);margin:4px 0 0;"><span id="meta-title-count">0</span>/70 tegn</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Meta Beskrivelse Mønster</label>
              <textarea name="template_fields[meta_description_pattern]" rows="3" maxlength="160"
                        style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"
                        ><?php echo esc_textarea( $cfg['meta_description_pattern'] ?? '' ); ?></textarea>
              <p style="font-size:11px;color:var(--text-muted);margin:4px 0 0;"><span id="meta-desc-count">0</span>/160 tegn</p>
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Intro Mønster</label>
              <textarea name="template_fields[intro_pattern]" rows="4"
                        style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $cfg['intro_pattern'] ?? '' ); ?></textarea>
            </div>

          </div>
        </div>

        <!-- Quality Rules -->
        <div class="rzpa-card" style="margin-bottom:20px;">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Kvalitetskrav</h3></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Min. antal ord</label>
                <input type="number" name="template_fields[quality_min_words]" min="50" max="10000"
                       value="<?php echo esc_attr( $cfg['quality_min_words'] ?? 300 ); ?>"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Min. antal H2</label>
                <input type="number" name="template_fields[quality_min_h2]" min="0" max="20"
                       value="<?php echo esc_attr( $cfg['quality_min_h2'] ?? 2 ); ?>"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              </div>
            </div>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
              <?php
              $quality_checks = [
                'quality_require_faq'   => 'Kræv FAQ',
                'quality_require_cta'   => 'Kræv CTA',
                'quality_require_local' => 'Kræv lokal dokumentation',
              ];
              foreach ( $quality_checks as $qkey => $qlabel ) : ?>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                  <input type="checkbox" name="template_fields[<?php echo esc_attr( $qkey ); ?>]" value="1"
                         <?php checked( ! empty( $cfg[ $qkey ] ) ); ?>>
                  <?php echo esc_html( $qlabel ); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Fallback Values -->
        <div class="rzpa-card" style="margin-bottom:20px;">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Fallback Værdier</h3></div>
          <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">By Fallback</label>
              <input type="text" name="template_fields[fallback_city]"
                     value="<?php echo esc_attr( $cfg['fallback_city'] ?? '' ); ?>"
                     placeholder="København"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>
            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Region Fallback</label>
              <input type="text" name="template_fields[fallback_region]"
                     value="<?php echo esc_attr( $cfg['fallback_region'] ?? '' ); ?>"
                     placeholder="Sjælland"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>
          </div>
        </div>

        <!-- Sections Config -->
        <div class="rzpa-card" style="margin-bottom:20px;">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Sektioner (JSON)</h3></div>
          <div style="padding:20px;">
            <textarea name="template_fields[sections]" rows="8"
                      style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);font-family:monospace;font-size:12px;resize:vertical;"
                      ><?php echo esc_textarea( isset( $cfg['sections'] ) ? ( is_array( $cfg['sections'] ) ? wp_json_encode( $cfg['sections'], JSON_PRETTY_PRINT ) : $cfg['sections'] ) : '' ); ?></textarea>
            <p style="font-size:11px;color:var(--text-muted);margin:6px 0 0;">
              Eksempel: <code>[{"type":"intro"},{"type":"faq"},{"type":"cta"}]</code>
            </p>
          </div>
        </div>

        <!-- Hidden JSON field built by JS -->
        <input type="hidden" name="template_config" id="template-config-json" value="<?php echo esc_attr( $template['template_config'] ?? '{}' ); ?>">

        <div style="margin-top:8px;">
          <button type="submit" class="rzpa-btn rzpa-btn-primary">
            <?php echo $is_edit ? 'Gem Ændringer' : 'Opret Template'; ?>
          </button>
          <a href="<?php echo esc_url( $back_url ); ?>" class="rzpa-btn" style="margin-left:8px;">Annuller</a>
        </div>
      </div>

      <!-- Sidebar -->
      <div>
        <div class="rzpa-card" style="margin-bottom:20px;">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Tilgængelige Pladsholdere</h3></div>
          <div style="padding:16px;">
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Brug <code>{navn}</code> i dine mønstre:</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach ( $placeholders as $ph ) : ?>
                <code style="background:var(--bg-300);border:1px solid var(--border);padding:2px 8px;border-radius:4px;font-size:11px;cursor:pointer;"
                      title="Klik for at kopiere">{<?php echo esc_html( $ph ); ?>}</code>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php if ( $is_edit ) : ?>
        <div class="rzpa-card">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Preview</h3></div>
          <div style="padding:16px;">
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Forhåndsvis template med eksempeldata.</p>
            <button type="button" id="preview-template-btn" class="rzpa-btn" style="width:100%;">Vis Preview</button>
            <div id="preview-result" style="margin-top:12px;display:none;background:var(--bg-300);border:1px solid var(--border);border-radius:var(--radius);padding:12px;font-size:12px;max-height:300px;overflow-y:auto;">
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </form>

</div>

<script>
(function($){
  // Auto-slug from name
  $('#tpl-name').on('input', function(){
    if ($('#tpl-slug').data('manual')) return;
    var slug = $(this).val().toLowerCase()
      .replace(/[æ]/g,'ae').replace(/[ø]/g,'oe').replace(/[å]/g,'aa')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    $('#tpl-slug').val(slug);
  });
  $('#tpl-slug').on('input', function(){
    $(this).data('manual', true);
  });

  // Char counters
  $('[name="template_fields[meta_title_pattern]"]').on('input', function(){
    $('#meta-title-count').text($(this).val().length);
  }).trigger('input');
  $('[name="template_fields[meta_description_pattern]"]').on('input', function(){
    $('#meta-desc-count').text($(this).val().length);
  }).trigger('input');

  // Build JSON from template fields before submit
  $('form').on('submit', function(){
    var cfg = {};
    $('[name^="template_fields["]').each(function(){
      var key = $(this).attr('name').replace('template_fields[','').replace(']','');
      if (this.type === 'checkbox') { cfg[key] = this.checked ? 1 : 0; }
      else { cfg[key] = $(this).val(); }
    });
    $('#template-config-json').val(JSON.stringify(cfg));
  });

  // Preview button
  $('#preview-template-btn').on('click', function(){
    var $btn = $(this);
    $btn.text('Henter...').prop('disabled', true);
    $.post(RZPA_SEO.ajaxUrl, {
      action: 'rzpa_seo_preview_template',
      nonce: RZPA_SEO.nonce,
      template_id: <?php echo absint( $id ); ?>
    }, function(res){
      $btn.text('Vis Preview').prop('disabled', false);
      if (res.success) {
        $('#preview-result').html(res.data).show();
      } else {
        $('#preview-result').html('<span style="color:#f66;">Fejl ved preview</span>').show();
      }
    });
  });
})(jQuery);
</script>
