<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

// Enqueue form builder JS/CSS
wp_enqueue_style( 'rzpz-crm-fb', RZPA_URL . 'modules/rezcrm/assets/rezcrm-form-builder.css', [], RZPA_VERSION );
wp_enqueue_script( 'rzpz-crm-fb', RZPA_URL . 'modules/rezcrm/assets/rezcrm-form-builder.js', [ 'jquery', 'jquery-ui-sortable' ], RZPA_VERSION, true );

$forms    = RZPZ_CRM_Forms_DB::get_forms();
$positions = RZPZ_CRM_DB::get_positions( 'open' );

wp_localize_script( 'rzpz-crm-fb', 'RZPZ_FB', [
    'apiBase'    => rest_url( 'rzpa/v1/' ),
    'nonce'      => wp_create_nonce( 'wp_rest' ),
    'fieldTypes' => RZPZ_CRM_Forms_DB::FIELD_TYPES,
    'coreMap'    => RZPZ_CRM_Forms_DB::CORE_FIELD_MAP,
    'siteUrl'    => site_url(),
] );
?>
<div class="wrap rzpa-wrap rzpz-fb-wrap" id="rzpz-fb-app">
  <div id="fb-toast" class="crm-toast"></div>

  <!-- ── Header ────────────────────────────────────────────────────────────── -->
  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">Ansøgningsformularer</h1>
      <p class="rzpa-page-sub">Opret og administrér ansøgningsformularer til dine stillinger</p>
    </div>
    <div class="rzpa-header-actions">
      <button class="rzpa-btn rzpa-btn-ghost" id="fb-provision-btn" title="Opretter automatisk en ansøgningsformular for alle stillinger der endnu ikke har en">
        ⚡ Opret formularer til alle stillinger
      </button>
      <button class="rzpa-btn rzpa-btn-primary" id="fb-new-form-btn">+ Ny formular</button>
    </div>
  </div>

  <!-- ── Forms list ─────────────────────────────────────────────────────────── -->
  <div id="fb-forms-view">
    <?php if ( empty( $forms ) ) : ?>
      <div style="text-align:center;padding:60px;color:#888">
        <p style="font-size:18px">Ingen formularer endnu</p>
        <p>Klik "+ Ny formular" for at oprette din første ansøgningsformular</p>
      </div>
    <?php else : ?>
      <div class="fb-forms-grid" id="fb-forms-grid">
        <?php foreach ( $forms as $form ) :
          $rate = $form->total_sessions > 0
            ? round( $form->total_completed / $form->total_sessions * 100, 1 ) : 0;
        ?>
          <div class="fb-form-card" data-form-id="<?php echo (int) $form->id; ?>">
            <div class="fb-form-card-header">
              <h3><?php echo esc_html( $form->title ); ?></h3>
              <span class="fb-form-status <?php echo $form->is_active ? 'fb-status-active' : 'fb-status-draft'; ?>">
                <?php echo $form->is_active ? 'Aktiv' : 'Inaktiv'; ?>
              </span>
            </div>

            <div class="fb-form-stats">
              <div class="fb-stat">
                <span class="fb-stat-val"><?php echo (int) $form->total_sessions; ?></span>
                <span class="fb-stat-label">Påbegyndt</span>
              </div>
              <div class="fb-stat">
                <span class="fb-stat-val"><?php echo (int) $form->total_completed; ?></span>
                <span class="fb-stat-label">Gennemført</span>
              </div>
              <div class="fb-stat fb-stat-neon">
                <span class="fb-stat-val"><?php echo $rate; ?>%</span>
                <span class="fb-stat-label">Konvertering</span>
              </div>
            </div>

            <div class="fb-form-meta">
              <code style="font-size:12px;background:rgba(255,255,255,.05);padding:4px 8px;border-radius:4px">
                [rezcrm_form slug="<?php echo esc_attr( $form->slug ); ?>"]
              </code>
            </div>

            <div class="fb-form-actions">
              <button class="rzpa-btn rzpa-btn-sm fb-edit-btn" data-id="<?php echo (int) $form->id; ?>">✏️ Rediger</button>
              <button class="rzpa-btn rzpa-btn-sm fb-settings-btn" data-id="<?php echo (int) $form->id; ?>">⚙️ Indstillinger</button>
              <button class="rzpa-btn rzpa-btn-sm fb-stats-btn" data-id="<?php echo (int) $form->id; ?>">📊 Stats</button>
            </div>
            <div class="fb-form-actions fb-form-actions-danger">
              <button class="rzpa-btn rzpa-btn-sm fb-duplicate-btn" data-id="<?php echo (int) $form->id; ?>" data-title="<?php echo esc_attr( $form->title ); ?>">⧉ Dupliker</button>
              <button class="rzpa-btn rzpa-btn-sm fb-delete-btn" data-id="<?php echo (int) $form->id; ?>" data-title="<?php echo esc_attr( $form->title ); ?>">🗑 Slet</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Form Builder (field editor) ──────────────────────────────────────── -->
  <div id="fb-builder-view" style="display:none">
    <div class="fb-builder-header">
      <button class="rzpa-btn" id="fb-back-btn">← Tilbage</button>
      <h2 id="fb-builder-title">Rediger formular</h2>
      <button class="rzpa-btn rzpa-btn-primary" id="fb-save-fields-btn">💾 Gem felter</button>
    </div>

    <div class="fb-builder-layout">

      <!-- Left: field palette ─────────────────────────────── -->
      <div class="fb-palette">
        <h3>Felttyper</h3>
        <p class="fb-palette-hint">Klik for at tilføje</p>
        <?php foreach ( RZPZ_CRM_Forms_DB::FIELD_TYPES as $type => $label ) : ?>
          <button class="fb-palette-btn" data-type="<?php echo esc_attr($type); ?>">
            <?php
            $icons = [
              'section'=>'━', 'text'=>'T', 'textarea'=>'¶', 'email'=>'@', 'phone'=>'📞',
              'date'=>'📅', 'birthdate'=>'🎂', 'radio'=>'◉', 'checkbox'=>'☑', 'select'=>'▼',
              'yes_no'=>'Y/N', 'file'=>'📎', 'profile_photo'=>'🤳', 'hidden'=>'👁',
            ];
            echo $icons[$type] ?? '?';
            ?> <?php echo esc_html($label); ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Center: drag-drop canvas ────────────────────────── -->
      <div class="fb-canvas-wrap">
        <h3>Formularlayout <span class="fb-field-count" id="fb-field-count">0 felter</span></h3>
        <div class="fb-canvas" id="fb-canvas">
          <div class="fb-canvas-empty" id="fb-canvas-empty">
            <p>Klik på felttyper til venstre for at tilføje felter</p>
          </div>
        </div>
      </div>

      <!-- Right: field configurator ───────────────────────── -->
      <div class="fb-config" id="fb-config">
        <h3>Felt-indstillinger</h3>
        <div id="fb-config-inner">
          <p style="color:var(--crm-muted);font-size:13px">Klik på et felt for at redigere det</p>
        </div>
      </div>

    </div><!-- /.fb-builder-layout -->
  </div><!-- /#fb-builder-view -->

  <!-- ── Form settings modal ──────────────────────────────────────────────── -->
  <div id="fb-settings-modal" class="crm-modal" style="display:none">
    <div class="crm-modal-inner">
      <div class="crm-modal-header">
        <h2>Formular-indstillinger</h2>
        <button class="crm-modal-close" data-close="fb-settings-modal">✕</button>
      </div>
      <div class="crm-modal-body">
        <input type="hidden" id="fb-settings-form-id">
        <div class="crm-field">
          <label>Titel</label>
          <input type="text" id="fb-form-title" class="crm-input">
        </div>
        <div class="crm-field">
          <label>Slug (bruges i shortcode)</label>
          <input type="text" id="fb-form-slug" class="crm-input" placeholder="fx ansoegning-kundeservice">
        </div>
        <div class="crm-field">
          <label>Tilknyttet stilling</label>
          <select id="fb-form-position" class="crm-select" style="width:100%">
            <option value="">Ingen (universal)</option>
            <?php foreach ( $positions as $pos ) : ?>
              <option value="<?php echo (int) $pos->id; ?>"><?php echo esc_html( $pos->title ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="crm-field">
          <label>Intro-tekst (vises over formularen)</label>
          <textarea id="fb-form-intro" class="crm-textarea" rows="3"></textarea>
        </div>
        <div class="crm-field">
          <label>Succes-besked (efter indsendelse)</label>
          <textarea id="fb-form-success" class="crm-textarea" rows="3"></textarea>
        </div>
        <div class="crm-field">
          <label>Viderestil til URL efter indsendelse</label>
          <input type="url" id="fb-form-redirect" class="crm-input" placeholder="https://...">
        </div>
        <div class="crm-field">
          <label>Notifikation til email (admin)</label>
          <input type="email" id="fb-form-notify" class="crm-input" placeholder="hr@rezponz.dk">
        </div>
        <div class="crm-field">
          <label class="crm-checkbox-label">
            <input type="checkbox" id="fb-form-active"> Formular er aktiv
          </label>
        </div>
        <div class="crm-field">
          <label class="crm-checkbox-label">
            <input type="checkbox" id="fb-form-progress"> Vis fremskridtsbar
          </label>
        </div>
        <div class="crm-field">
          <label class="crm-checkbox-label">
            <input type="checkbox" id="fb-form-multistep"> Multi-trin (opdelt i sektioner)
          </label>
        </div>
      </div>
      <div class="crm-modal-footer">
        <button class="rzpa-btn" data-close="fb-settings-modal">Annuller</button>
        <button class="rzpa-btn rzpa-btn-primary" id="fb-save-settings-btn">Gem indstillinger</button>
      </div>
    </div>
  </div>

  <!-- ── Stats modal ──────────────────────────────────────────────────────── -->
  <div id="fb-stats-modal" class="crm-modal" style="display:none">
    <div class="crm-modal-inner crm-modal-wide">
      <div class="crm-modal-header">
        <h2>Konverteringsstatistik</h2>
        <button class="crm-modal-close" data-close="fb-stats-modal">✕</button>
      </div>
      <div class="crm-modal-body" id="fb-stats-body">
        <p style="color:var(--crm-muted)">Indlæser…</p>
      </div>
    </div>
  </div>

  <div id="crm-backdrop" class="crm-backdrop" style="display:none"></div>
</div>
