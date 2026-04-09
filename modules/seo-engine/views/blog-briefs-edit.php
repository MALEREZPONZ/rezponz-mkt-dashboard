<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$id      = absint( $_GET['id'] ?? 0 );
$brief   = $id ? RZPA_SEO_DB::get_brief( $id ) : null;
$is_edit = null !== $brief;
$back    = admin_url( 'admin.php?page=rzpa-seo-briefs' );

$blog_templates = RZPA_SEO_DB::get_templates( 'blog', 'active' );
$link_targets   = json_decode( $brief['internal_link_targets'] ?? '[]', true ) ?: [];
$ai_configured  = RZPA_SEO_AI::is_configured();

// WP Pages for pillar reference
$wp_pages = get_posts( [ 'post_type' => 'page', 'posts_per_page' => 50, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ] );
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title"><?php echo $is_edit ? 'Rediger Brief' : 'Nyt Blog Brief'; ?></h1>
      <p class="rzpa-page-sub"><a href="<?php echo esc_url( $back ); ?>" style="color:var(--neon);">← Tilbage til briefs</a></p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Brief gemt.</p></div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_save_brief">
    <?php if ( $is_edit ) : ?><input type="hidden" name="id" value="<?php echo absint( $brief['id'] ); ?>"><?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">

      <!-- Main Form -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <div class="rzpa-card">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Brief Grundoplysninger</h3></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Template</label>
              <select name="template_id" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                <option value="">— Ingen template —</option>
                <?php foreach ( $blog_templates as $tpl ) : ?>
                  <option value="<?php echo absint( $tpl['id'] ); ?>"<?php selected( $brief['template_id'] ?? 0, $tpl['id'] ); ?>>
                    <?php echo esc_html( $tpl['name'] ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Primært Søgeord <span style="color:var(--neon);">*</span></label>
              <input type="text" name="primary_keyword" required value="<?php echo esc_attr( $brief['primary_keyword'] ?? '' ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Sekundære Søgeord <span style="font-size:11px;">(kommasepareret)</span></label>
              <input type="text" name="secondary_keywords" value="<?php echo esc_attr( $brief['secondary_keywords'] ?? '' ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Søgeintention</label>
                <select name="intent" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <?php foreach ( [ 'informational' => 'Informational', 'navigational' => 'Navigational', 'transactional' => 'Transactional', 'commercial' => 'Commercial' ] as $v => $l ) : ?>
                    <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $brief['intent'] ?? 'informational', $v ); ?>><?php echo esc_html( $l ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Målgruppe</label>
                <input type="text" name="audience" value="<?php echo esc_attr( $brief['audience'] ?? '' ); ?>"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Tone of Voice</label>
                <select name="tone_of_voice" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <?php foreach ( [ 'professional' => 'Professionel', 'conversational' => 'Samtale', 'authoritative' => 'Autoritativ', 'friendly' => 'Venlig', 'technical' => 'Teknisk' ] as $v => $l ) : ?>
                    <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $brief['tone_of_voice'] ?? 'professional', $v ); ?>><?php echo esc_html( $l ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Artikel Type</label>
                <select name="article_type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <?php foreach ( [ 'how-to' => 'How-to guide', 'listicle' => 'Listicle', 'guide' => 'Guide', 'comparison' => 'Sammenligning', 'news' => 'Nyhed', 'review' => 'Anmeldelse', 'opinion' => 'Holdning' ] as $v => $l ) : ?>
                    <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $brief['article_type'] ?? 'how-to', $v ); ?>><?php echo esc_html( $l ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Ønsket Længde (ord)</label>
                <input type="number" name="target_length" min="300" max="10000"
                       value="<?php echo esc_attr( $brief['target_length'] ?? 1500 ); ?>"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Overskriftsdybde</label>
                <select name="heading_depth" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <option value="2"<?php selected( $brief['heading_depth'] ?? 3, 2 ); ?>>H2 (2 niveauer)</option>
                  <option value="3"<?php selected( $brief['heading_depth'] ?? 3, 3 ); ?>>H3 (3 niveauer)</option>
                  <option value="4"<?php selected( $brief['heading_depth'] ?? 3, 4 ); ?>>H4 (4 niveauer)</option>
                </select>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                  <input type="checkbox" name="faq_required" value="1"<?php checked( ! empty( $brief['faq_required'] ) ); ?>>
                  Kræv FAQ sektion
                </label>
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">CTA Type</label>
                <select name="cta_type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <option value=""<?php selected( $brief['cta_type'] ?? '', '' ); ?>>Ingen CTA</option>
                  <option value="contact"<?php selected( $brief['cta_type'] ?? '', 'contact' ); ?>>Kontakt</option>
                  <option value="demo"<?php selected( $brief['cta_type'] ?? '', 'demo' ); ?>>Demo</option>
                  <option value="apply"<?php selected( $brief['cta_type'] ?? '', 'apply' ); ?>>Ansøg</option>
                </select>
              </div>
            </div>

          </div>
        </div>

        <!-- SEO Fields -->
        <div class="rzpa-card">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">SEO</h3></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Slug</label>
              <input type="text" name="slug" value="<?php echo esc_attr( $brief['slug'] ?? '' ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Meta Title <span id="brief-title-count" style="font-size:11px;">(0/70)</span></label>
              <input type="text" name="meta_title" id="brief-meta-title" maxlength="70"
                     value="<?php echo esc_attr( $brief['meta_title'] ?? '' ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Meta Description <span id="brief-desc-count" style="font-size:11px;">(0/160)</span></label>
              <textarea name="meta_description" id="brief-meta-desc" rows="3" maxlength="160"
                        style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $brief['meta_description'] ?? '' ); ?></textarea>
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Uddrag / Excerpt</label>
              <textarea name="excerpt" rows="3"
                        style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $brief['excerpt'] ?? '' ); ?></textarea>
            </div>

          </div>
        </div>

        <!-- Content Structure -->
        <div class="rzpa-card">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Indholds Struktur</h3></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Pillar Side Reference</label>
                <select name="pillar_reference" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                  <option value="">— Ingen —</option>
                  <?php foreach ( $wp_pages as $wp_page ) : ?>
                    <option value="<?php echo absint( $wp_page->ID ); ?>"<?php selected( $brief['pillar_reference'] ?? 0, $wp_page->ID ); ?>>
                      <?php echo esc_html( $wp_page->post_title ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Cluster Reference</label>
                <input type="text" name="cluster_reference" value="<?php echo esc_attr( $brief['cluster_reference'] ?? '' ); ?>"
                       placeholder="f.eks. jobs-kobenhavn"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              </div>
            </div>

            <!-- Internal Link Targets -->
            <div>
              <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--text-muted);">Interne Linkmål</label>
              <div id="link-targets-list" style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ( $link_targets as $i => $lt ) : ?>
                  <div class="lt-row" style="display:flex;gap:8px;align-items:center;">
                    <input type="text" name="internal_link_targets[]" value="<?php echo esc_attr( is_array( $lt ) ? ( $lt['url'] ?? $lt ) : $lt ); ?>"
                           placeholder="URL eller søgeterm"
                           style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                    <button type="button" class="rzpa-btn remove-lt" style="font-size:12px;padding:6px 10px;">✕</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" id="add-link-target" class="rzpa-btn" style="margin-top:8px;font-size:12px;">+ Tilføj linkmål</button>
            </div>

            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Status</label>
              <select name="status" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
                <?php foreach ( [ 'draft' => 'Kladde', 'review' => 'Review', 'approved' => 'Godkendt', 'generated' => 'Genereret', 'published' => 'Publiceret' ] as $v => $l ) : ?>
                  <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $brief['status'] ?? 'draft', $v ); ?>><?php echo esc_html( $l ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>
        </div>

        <div>
          <button type="submit" class="rzpa-btn rzpa-btn-primary"><?php echo $is_edit ? 'Gem Ændringer' : 'Opret Brief'; ?></button>
          <a href="<?php echo esc_url( $back ); ?>" class="rzpa-btn" style="margin-left:8px;">Annuller</a>
        </div>
      </div>

      <!-- Sidebar -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- AI Support -->
        <div class="rzpa-card">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">AI Support</h3></div>
          <div style="padding:16px;">
            <?php if ( $ai_configured ) : ?>
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                <span class="badge badge-active">AI aktiv – <?php echo esc_html( RZPA_SEO_AI::get_provider() ); ?></span>
              </div>
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                Genereringen vil anvende AI til at producere indholdet ud fra dette brief.
              </p>
              <?php if ( $is_edit ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                  <input type="hidden" name="action" value="rzpa_seo_generate_blog">
                  <input type="hidden" name="brief_id" value="<?php echo absint( $id ); ?>">
                  <input type="hidden" name="use_ai" value="1">
                  <button type="submit" class="rzpa-btn rzpa-btn-primary" style="width:100%;">🤖 Generér med AI</button>
                </form>
              <?php endif; ?>
            <?php else : ?>
              <p style="font-size:12px;color:var(--text-muted);">AI er ikke konfigureret. Opsæt API-nøgle i <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-seo-settings' ) ); ?>" style="color:var(--neon);">SEO Indstillinger</a>.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Quality Requirements -->
        <div class="rzpa-card">
          <div class="rzpa-card-header"><h3 class="rzpa-card-title">Indholdskrav</h3></div>
          <div style="padding:16px;">
            <?php
            $settings = get_option( 'rzpa_seo_settings', [] );
            $items = [
              'Min. ' . ( $settings['quality_min_words'] ?? 300 ) . ' ord',
              'Min. ' . ( $settings['quality_min_h2'] ?? 2 ) . ' H2-overskrifter',
            ];
            if ( ! empty( $brief['faq_required'] ) ) $items[] = 'FAQ sektion påkrævet';
            if ( ! empty( $brief['cta_type'] ) && 'none' !== $brief['cta_type'] ) $items[] = 'CTA: ' . esc_html( $brief['cta_type'] );
            ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;">
              <?php foreach ( $items as $item ) : ?>
                <li style="display:flex;align-items:center;gap:6px;font-size:12px;">
                  <span style="color:var(--neon);">✓</span> <?php echo esc_html( $item ); ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

      </div>

    </div>
  </form>

</div>
<script>
(function($){
  $('#brief-meta-title').on('input', function(){ $('#brief-title-count').text('('+$(this).val().length+'/70)'); }).trigger('input');
  $('#brief-meta-desc').on('input',  function(){ $('#brief-desc-count').text('('+$(this).val().length+'/160)'); }).trigger('input');

  $('#add-link-target').on('click', function(){
    var row = $('<div class="lt-row" style="display:flex;gap:8px;align-items:center;"></div>')
      .append($('<input type="text" placeholder="URL eller søgeterm" style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">').attr('name','internal_link_targets[]'))
      .append('<button type="button" class="rzpa-btn remove-lt" style="font-size:12px;padding:6px 10px;">✕</button>');
    $('#link-targets-list').append(row);
  });
  $(document).on('click', '.remove-lt', function(){ $(this).closest('.lt-row').remove(); });
})(jQuery);
</script>
