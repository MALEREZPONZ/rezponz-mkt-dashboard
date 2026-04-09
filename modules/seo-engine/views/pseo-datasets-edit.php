<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$id      = absint( $_GET['id'] ?? 0 );
$ds      = $id ? RZPA_SEO_DB::get_dataset( $id ) : null;
$is_edit = null !== $ds;
$back    = admin_url( 'admin.php?page=rzpa-seo-datasets' );

$templates = RZPA_SEO_DB::get_templates( 'pseo', 'active' );
$active_tab = sanitize_key( $_GET['tab'] ?? 'basic' );

// Decode JSON fields
$faq_items   = json_decode( $ds['faq_items']           ?? '[]', true ) ?: [];
$uvp         = json_decode( $ds['unique_value_points']  ?? '[]', true ) ?: [];
$rel_links   = json_decode( $ds['related_links']        ?? '[]', true ) ?: [];
$override    = json_decode( $ds['manual_override_flags'] ?? '{}', true ) ?: [];
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title"><?php echo $is_edit ? 'Rediger Datasæt' : 'Nyt Datasæt'; ?></h1>
      <p class="rzpa-page-sub"><a href="<?php echo esc_url( $back ); ?>" style="color:var(--neon);">← Tilbage til datasæt</a></p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Datasæt gemt.</p></div>
  <?php endif; ?>

  <!-- Tabs -->
  <div style="display:flex;gap:2px;margin-bottom:20px;">
    <?php
    $tabs = [ 'basic' => 'Basisdata', 'content' => 'Indhold', 'seo' => 'SEO', 'status' => 'Status' ];
    foreach ( $tabs as $tab_key => $tab_label ) :
      $is_active = ( $active_tab === $tab_key );
    ?>
      <a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key ) ); ?>"
         class="rzpa-btn<?php echo $is_active ? ' rzpa-btn-primary' : ''; ?>"
         style="border-radius:<?php echo $tab_key === 'basic' ? '8px 0 0 8px' : ( $tab_key === 'status' ? '0 8px 8px 0' : '0' ); ?>;">
        <?php echo esc_html( $tab_label ); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_save_dataset">
    <?php if ( $is_edit ) : ?><input type="hidden" name="id" value="<?php echo absint( $ds['id'] ); ?>"><?php endif; ?>

    <?php /* ── TAB: BASISDATA ── */ if ( 'basic' === $active_tab ) : ?>
    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">Basisdata</h3></div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Dataset Gruppe</label>
            <input type="text" name="dataset_group" value="<?php echo esc_attr( $ds['dataset_group'] ?? '' ); ?>"
                   style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Template</label>
            <select name="template_id" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              <option value="">— Vælg template —</option>
              <?php foreach ( $templates as $tpl ) : ?>
                <option value="<?php echo absint( $tpl['id'] ); ?>"<?php selected( ( $ds['template_id'] ?? 0 ), $tpl['id'] ); ?>>
                  <?php echo esc_html( $tpl['name'] ); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Søgeord <span style="color:var(--neon);">*</span></label>
            <input type="text" name="keyword" required value="<?php echo esc_attr( $ds['keyword'] ?? '' ); ?>"
                   style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Primært Søgeord <span style="color:var(--neon);">*</span></label>
            <input type="text" name="primary_keyword" required value="<?php echo esc_attr( $ds['primary_keyword'] ?? '' ); ?>"
                   style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
          </div>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Sekundære Søgeord <span style="font-size:11px;">(kommasepareret)</span></label>
          <input type="text" name="secondary_keywords" value="<?php echo esc_attr( $ds['secondary_keywords'] ?? '' ); ?>"
                 style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
          <?php
          $geo_fields = [ 'city' => 'By', 'region' => 'Region', 'area' => 'Område', 'country' => 'Land' ];
          foreach ( $geo_fields as $gf => $gl ) : ?>
            <div>
              <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);"><?php echo esc_html( $gl ); ?></label>
              <input type="text" name="<?php echo esc_attr( $gf ); ?>"
                     value="<?php echo esc_attr( $ds[ $gf ] ?? ( 'country' === $gf ? 'dk' : '' ) ); ?>"
                     style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            </div>
          <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Jobtype</label>
            <input type="text" name="job_type" value="<?php echo esc_attr( $ds['job_type'] ?? '' ); ?>"
                   style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Kategori</label>
            <input type="text" name="category" value="<?php echo esc_attr( $ds['category'] ?? '' ); ?>"
                   style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Målgruppe</label>
            <input type="text" name="audience" value="<?php echo esc_attr( $ds['audience'] ?? '' ); ?>"
                   style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Ansættelsestype</label>
            <select name="employment_type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              <?php foreach ( [ '' => '—', 'Fuldtid' => 'Fuldtid', 'Deltid' => 'Deltid', 'Freelance' => 'Freelance', 'Praktik' => 'Praktik' ] as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $ds['employment_type'] ?? '', $v ); ?>><?php echo esc_html( $l ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Søgeintention</label>
            <select name="search_intent" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              <?php foreach ( [ 'informational' => 'Informational', 'navigational' => 'Navigational', 'transactional' => 'Transactional', 'commercial' => 'Commercial' ] as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $ds['search_intent'] ?? 'informational', $v ); ?>><?php echo esc_html( $l ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

      </div>
    </div>

    <?php elseif ( 'content' === $active_tab ) : ?>
    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">Indhold</h3></div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:20px;">

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Intro Tekst</label>
          <textarea name="intro_text" rows="5"
                    style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $ds['intro_text'] ?? '' ); ?></textarea>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">CTA Tekst</label>
          <textarea name="cta_text" rows="3"
                    style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $ds['cta_text'] ?? '' ); ?></textarea>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Lokal Dokumentation</label>
          <textarea name="local_proof" rows="4"
                    style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $ds['local_proof'] ?? '' ); ?></textarea>
        </div>

        <!-- Unique Value Points -->
        <div>
          <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--text-muted);">Unikke Værdipunkter</label>
          <div id="uvp-list" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ( $uvp as $i => $point ) : ?>
              <div class="uvp-row" style="display:flex;gap:8px;align-items:center;">
                <input type="text" name="unique_value_points[]" value="<?php echo esc_attr( $point ); ?>"
                       style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                <button type="button" class="rzpa-btn remove-row" style="font-size:12px;padding:6px 10px;">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="rzpa-btn" id="add-uvp" style="margin-top:8px;font-size:12px;">+ Tilføj værdipunkt</button>
        </div>

        <!-- FAQ Items -->
        <div>
          <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--text-muted);">FAQ Spørgsmål</label>
          <div id="faq-list" style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ( $faq_items as $i => $faq ) : ?>
              <div class="faq-row" style="background:var(--bg-300);border:1px solid var(--border);border-radius:var(--radius);padding:12px;position:relative;">
                <button type="button" class="rzpa-btn remove-row" style="position:absolute;top:8px;right:8px;font-size:11px;padding:4px 8px;">✕</button>
                <div style="margin-bottom:8px;">
                  <input type="text" name="faq_items[<?php echo $i; ?>][question]"
                         value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>"
                         placeholder="Spørgsmål"
                         style="width:100%;background:var(--bg-200);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                </div>
                <textarea name="faq_items[<?php echo $i; ?>][answer]" rows="3" placeholder="Svar"
                          style="width:100%;background:var(--bg-200);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;resize:vertical;"><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="rzpa-btn" id="add-faq" style="margin-top:8px;font-size:12px;">+ Tilføj FAQ</button>
        </div>

        <!-- Related Links -->
        <div>
          <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--text-muted);">Relaterede Links</label>
          <div id="links-list" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ( $rel_links as $i => $link ) : ?>
              <div class="link-row" style="display:flex;gap:8px;align-items:center;">
                <input type="url" name="related_links[<?php echo $i; ?>][url]" placeholder="https://..."
                       value="<?php echo esc_attr( $link['url'] ?? '' ); ?>"
                       style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                <input type="text" name="related_links[<?php echo $i; ?>][text]" placeholder="Linktekst"
                       value="<?php echo esc_attr( $link['text'] ?? '' ); ?>"
                       style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                <button type="button" class="rzpa-btn remove-row" style="font-size:12px;padding:6px 10px;">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="rzpa-btn" id="add-link" style="margin-top:8px;font-size:12px;">+ Tilføj link</button>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Custom Sektioner (JSON)</label>
          <textarea name="custom_sections" rows="4"
                    style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);font-family:monospace;font-size:12px;resize:vertical;"><?php echo esc_textarea( $ds['custom_sections'] ?? '[]' ); ?></textarea>
        </div>

      </div>
    </div>

    <?php elseif ( 'seo' === $active_tab ) : ?>
    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">SEO</h3></div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Slug</label>
          <div style="display:flex;gap:8px;">
            <input type="text" name="slug" id="ds-slug" value="<?php echo esc_attr( $ds['slug'] ?? '' ); ?>"
                   style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            <button type="button" id="auto-slug" class="rzpa-btn" style="font-size:12px;">Auto</button>
          </div>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Meta Title <span style="font-size:11px;color:var(--text-muted);" id="meta-title-count">(0/70)</span></label>
          <div style="display:flex;gap:8px;">
            <input type="text" name="meta_title" id="ds-meta-title" maxlength="70"
                   value="<?php echo esc_attr( $ds['meta_title'] ?? '' ); ?>"
                   style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
            <button type="button" class="rzpa-btn" style="font-size:12px;" id="auto-meta-title">Auto</button>
          </div>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Meta Description <span style="font-size:11px;color:var(--text-muted);" id="meta-desc-count">(0/160)</span></label>
          <div style="display:flex;gap:8px;align-items:flex-start;">
            <textarea name="meta_description" id="ds-meta-desc" rows="3" maxlength="160"
                      style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);resize:vertical;"><?php echo esc_textarea( $ds['meta_description'] ?? '' ); ?></textarea>
            <button type="button" class="rzpa-btn" style="font-size:12px;" id="auto-meta-desc">Auto</button>
          </div>
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Canonical URL</label>
          <input type="url" name="canonical_url" value="<?php echo esc_attr( $ds['canonical_url'] ?? '' ); ?>"
                 style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
        </div>

        <div>
          <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--text-muted);">Indeksering</label>
          <div style="display:flex;gap:20px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
              <input type="radio" name="indexation_status" value="1"<?php checked( intval( $ds['indexation_status'] ?? 1 ), 1 ); ?>> Ja
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
              <input type="radio" name="indexation_status" value="0"<?php checked( intval( $ds['indexation_status'] ?? 1 ), 0 ); ?>> Nej (noindex)
            </label>
          </div>
        </div>

      </div>
    </div>

    <?php elseif ( 'status' === $active_tab ) : ?>
    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">Status &amp; Kontrol</h3></div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:20px;">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Genereringsstatus</label>
            <select name="generation_status" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              <?php foreach ( [ 'pending' => 'Afventer', 'draft' => 'Kladde', 'review' => 'Review', 'approved' => 'Godkendt', 'published' => 'Publiceret', 'failed' => 'Fejlet' ] as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $ds['generation_status'] ?? 'pending', $v ); ?>><?php echo esc_html( $l ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Kvalitetsstatus</label>
            <select name="quality_status" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:var(--radius);">
              <?php foreach ( [ 'unchecked' => 'Utjekket', 'passed' => 'Bestået', 'needs_improvement' => 'Forbedres', 'failed' => 'Fejlet' ] as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $ds['quality_status'] ?? 'unchecked', $v ); ?>><?php echo esc_html( $l ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--text-muted);">Manuel Override (lås felter mod overskrivning ved regenerering)</label>
          <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <?php foreach ( [ 'title' => 'Titel', 'intro' => 'Intro', 'faq' => 'FAQ', 'cta' => 'CTA', 'meta_title' => 'Meta Titel', 'meta_desc' => 'Meta Beskrivelse' ] as $k => $l ) : ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                <input type="checkbox" name="override_<?php echo esc_attr( $k ); ?>" value="1"
                       <?php checked( ! empty( $override[ $k ] ) ); ?>>
                <?php echo esc_html( $l ); ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ( $is_edit && ! empty( $ds['linked_post_id'] ) ) : ?>
        <div>
          <label style="display:block;margin-bottom:6px;font-size:13px;color:var(--text-muted);">Tilknyttet WordPress Side</label>
          <a href="<?php echo esc_url( get_edit_post_link( $ds['linked_post_id'] ) ); ?>" target="_blank" class="rzpa-btn" style="font-size:12px;">
            Rediger side #<?php echo absint( $ds['linked_post_id'] ); ?> ↗
          </a>
          &nbsp;
          <a href="<?php echo esc_url( get_permalink( $ds['linked_post_id'] ) ); ?>" target="_blank" class="rzpa-btn" style="font-size:12px;">
            Se live ↗
          </a>
        </div>
        <?php endif; ?>

        <?php if ( $is_edit ) : ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:12px;color:var(--text-muted);">
          <div>Oprettet: <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $ds['created_at'] ) ) ); ?></div>
          <div>Opdateret: <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $ds['updated_at'] ) ) ); ?></div>
        </div>
        <?php endif; ?>

      </div>
    </div>
    <?php endif; ?>

    <div style="margin-top:20px;display:flex;gap:8px;">
      <button type="submit" class="rzpa-btn rzpa-btn-primary"><?php echo $is_edit ? 'Gem Ændringer' : 'Opret Datasæt'; ?></button>
      <a href="<?php echo esc_url( $back ); ?>" class="rzpa-btn">Annuller</a>
    </div>
  </form>

</div>

<script>
(function($){
  // Dynamic list helpers
  function makeInputRow(name, placeholder){
    return $('<div class="uvp-row" style="display:flex;gap:8px;align-items:center;"></div>')
      .append($('<input type="text" style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">').attr({name:name, placeholder:placeholder}))
      .append('<button type="button" class="rzpa-btn remove-row" style="font-size:12px;padding:6px 10px;">✕</button>');
  }

  $('#add-uvp').on('click', function(){
    $('#uvp-list').append(makeInputRow('unique_value_points[]','Værdipunkt'));
  });

  $('#add-faq').on('click', function(){
    var idx = $('#faq-list .faq-row').length;
    var row = $('<div class="faq-row" style="background:var(--bg-300);border:1px solid var(--border);border-radius:var(--radius);padding:12px;position:relative;"></div>');
    row.append('<button type="button" class="rzpa-btn remove-row" style="position:absolute;top:8px;right:8px;font-size:11px;padding:4px 8px;">✕</button>');
    row.append($('<div style="margin-bottom:8px;"></div>').append($('<input type="text" placeholder="Spørgsmål" style="width:100%;background:var(--bg-200);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">').attr('name','faq_items['+idx+'][question]')));
    row.append($('<textarea rows="3" placeholder="Svar" style="width:100%;background:var(--bg-200);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;resize:vertical;"></textarea>').attr('name','faq_items['+idx+'][answer]'));
    $('#faq-list').append(row);
  });

  $('#add-link').on('click', function(){
    var idx = $('#links-list .link-row').length;
    var row = $('<div class="link-row" style="display:flex;gap:8px;align-items:center;"></div>');
    row.append($('<input type="url" placeholder="https://..." style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">').attr('name','related_links['+idx+'][url]'));
    row.append($('<input type="text" placeholder="Linktekst" style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">').attr('name','related_links['+idx+'][text]'));
    row.append('<button type="button" class="rzpa-btn remove-row" style="font-size:12px;padding:6px 10px;">✕</button>');
    $('#links-list').append(row);
  });

  $(document).on('click', '.remove-row', function(){ $(this).closest('[class$="-row"]').remove(); });

  // Char counters
  $('#ds-meta-title').on('input', function(){ $('#meta-title-count').text('('+$(this).val().length+'/70)'); }).trigger('input');
  $('#ds-meta-desc').on('input',  function(){ $('#meta-desc-count').text('('+$(this).val().length+'/160)'); }).trigger('input');
})(jQuery);
</script>
