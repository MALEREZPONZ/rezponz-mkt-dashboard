<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$seo_opts   = get_option( 'rzpa_seo_settings', [] );
$ai_ok      = RZPA_SEO_AI::is_configured();
$cpt_ok     = post_type_exists( 'rzpa_pseo' );
$db_version = get_option( RZPA_SEO_DB::DB_VERSION_KEY, '—' );
$api_key_saved = ! empty( $seo_opts['rzpa_ai_api_key'] );
?>
<div class="rzpa-wrap" id="page-seo-settings">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">⚙️ SEO Engine Indstillinger</h1>
      <p class="rzpa-page-sub">Konfigurer AI, URL-struktur og generering</p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Indstillinger gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <!-- ══ HOVED-FORM (gem indstillinger) ══════════════════════════════════ -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
    <input type="hidden" name="action" value="rzpa_seo_save_settings">

    <div style="display:flex;flex-direction:column;gap:24px;">

      <!-- AI -->
      <div class="rzpa-card">
        <div class="rzpa-card-header"><span class="rzpa-card-title">🤖 AI-konfiguration</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:18px;">

          <div>
            <label class="rzpa-label">AI Udbyder</label>
            <select name="seo_settings[ai_provider]" class="rzpa-select" style="max-width:320px;">
              <option value=""       <?php selected( $seo_opts['rzpa_ai_provider'] ?? '', '' ); ?>>Ingen AI</option>
              <option value="openai" <?php selected( $seo_opts['rzpa_ai_provider'] ?? '', 'openai' ); ?>>OpenAI (GPT-4o)</option>
              <option value="claude" <?php selected( $seo_opts['rzpa_ai_provider'] ?? '', 'claude' ); ?>>Claude (Anthropic)</option>
            </select>
          </div>

          <div>
            <label class="rzpa-label">API Nøgle</label>
            <input type="password" name="seo_settings[ai_api_key]"
                   placeholder="<?php echo $api_key_saved ? '••••••••  (gemt — lad tom for at beholde)' : 'Indsæt API nøgle'; ?>"
                   class="rzpa-input" style="max-width:480px;">
            <p class="rzpa-hint">Nøglen gemmes krypteret og bruges til generering af sider, blogs og metadata.</p>
          </div>

          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:13px;color:var(--text-muted);">Status:</span>
            <?php if ( $ai_ok ) : ?>
              <span class="badge badge-active">✓ Forbundet via <?php echo esc_html( RZPA_SEO_AI::get_provider() ); ?></span>
            <?php else : ?>
              <span class="badge badge-paused">Ikke konfigureret</span>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- URL & CPT -->
      <div class="rzpa-card">
        <div class="rzpa-card-header"><span class="rzpa-card-title">🔗 URL & Sidestruktur</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

          <div>
            <label class="rzpa-label">Rewrite Base</label>
            <input type="text" name="seo_settings[rewrite_base]"
                   value="<?php echo esc_attr( $seo_opts['rewrite_base'] ?? 'job' ); ?>"
                   class="rzpa-input" style="max-width:320px;">
            <p class="rzpa-hint">
              Basis-URL for pSEO sider. <code>job</code> giver <code>/job/[slug]</code>.
              <strong style="color:#fa0;">OBS:</strong> Ændring kræver Flush Permalinks!
            </p>
          </div>

          <div>
            <label class="rzpa-label">Aktuel URL-struktur</label>
            <code style="background:var(--bg-200);border:1px solid var(--border);padding:6px 12px;border-radius:var(--radius);font-size:13px;display:inline-block;color:var(--neon);">
              <?php echo esc_html( trailingslashit( home_url() ) . ( $seo_opts['rewrite_base'] ?? 'job' ) . '/[slug]' ); ?>
            </code>
          </div>

          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:13px;color:var(--text-muted);">CPT Status:</span>
            <?php if ( $cpt_ok ) : ?>
              <span class="badge badge-active">✓ rzpa_pseo registreret</span>
            <?php else : ?>
              <span class="badge badge-error">✗ CPT ikke fundet</span>
            <?php endif; ?>
          </div>

          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[show_in_search]" value="1"
                   <?php checked( empty( $seo_opts['exclude_from_search'] ) ); ?>>
            Vis pSEO sider i WordPress-søgning
          </label>

        </div>
      </div>

      <!-- Generering -->
      <div class="rzpa-card">
        <div class="rzpa-card-header"><span class="rzpa-card-title">⚡ Generering</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

          <div>
            <label class="rzpa-label">Standard publiceringstatus</label>
            <select name="seo_settings[default_publish_status]" class="rzpa-select" style="max-width:320px;">
              <option value="draft"   <?php selected( $seo_opts['default_publish_status'] ?? 'draft', 'draft' ); ?>>Kladde (anbefalet)</option>
              <option value="pending" <?php selected( $seo_opts['default_publish_status'] ?? 'draft', 'pending' ); ?>>Afventer gennemgang</option>
              <option value="publish" <?php selected( $seo_opts['default_publish_status'] ?? 'draft', 'publish' ); ?>>Publicér med det samme</option>
            </select>
          </div>

          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[auto_link_on_generate]" value="1"
                   <?php checked( ! empty( $seo_opts['auto_link_on_generate'] ) ); ?>>
            Auto-foreslå interne links efter generering
          </label>

          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[respect_manual_overrides]" value="1"
                   <?php checked( $seo_opts['respect_manual_overrides'] ?? true ); ?>>
            Respektér manuelle overrides ved regenerering
          </label>

        </div>
      </div>

      <!-- Kvalitetskrav -->
      <div class="rzpa-card">
        <div class="rzpa-card-header"><span class="rzpa-card-title">✅ Standardkvalitetskrav</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

          <p class="rzpa-hint" style="margin:0;">Bruges som default for nye templates.</p>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:480px;">
            <div>
              <label class="rzpa-label">Min. antal ord</label>
              <input type="number" name="seo_settings[quality_min_words]" min="0"
                     value="<?php echo absint( $seo_opts['quality_min_words'] ?? 300 ); ?>"
                     class="rzpa-input">
            </div>
            <div>
              <label class="rzpa-label">Min. antal H2</label>
              <input type="number" name="seo_settings[quality_min_h2]" min="0"
                     value="<?php echo absint( $seo_opts['quality_min_h2'] ?? 2 ); ?>"
                     class="rzpa-input">
            </div>
          </div>

          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[quality_require_faq]" value="1"
                   <?php checked( ! empty( $seo_opts['quality_require_faq'] ) ); ?>>
            Kræv FAQ som standard
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[quality_require_cta]" value="1"
                   <?php checked( ! empty( $seo_opts['quality_require_cta'] ) ); ?>>
            Kræv CTA som standard
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[quality_require_local]" value="1"
                   <?php checked( ! empty( $seo_opts['quality_require_local'] ) ); ?>>
            Kræv lokal omtale
          </label>

        </div>
      </div>

      <!-- Sitemap -->
      <div class="rzpa-card">
        <div class="rzpa-card-header"><span class="rzpa-card-title">🗺️ Sitemap</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[sitemap_include_pseo]" value="1"
                   <?php checked( ! empty( $seo_opts['sitemap_include_pseo'] ) ); ?>>
            Inkludér pSEO sider i sitemap
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="seo_settings[sitemap_exclude_noindex]" value="1"
                   <?php checked( ! empty( $seo_opts['sitemap_exclude_noindex'] ) ); ?>>
            Ekskludér noindex sider fra sitemap
          </label>
        </div>
      </div>

    </div><!-- end cards -->

    <div style="margin-top:24px;padding-bottom:8px;">
      <button type="submit" class="rzpa-btn rzpa-btn-primary" style="font-size:14px;padding:10px 28px;">💾 Gem indstillinger</button>
    </div>

  </form>
  <!-- ══ SLUT PÅ HOVED-FORM ══════════════════════════════════════════════ -->

  <!-- Avanceret (separate forms — IKKE nested) -->
  <div class="rzpa-card" style="margin-top:24px;">
    <div class="rzpa-card-header"><span class="rzpa-card-title">🔧 Avanceret</span></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:20px;">

      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:13px;color:var(--text-muted);">DB Version:</span>
        <code style="background:var(--bg-200);border:1px solid var(--border);padding:3px 8px;border-radius:var(--radius);font-size:12px;">
          <?php echo esc_html( $db_version ); ?>
        </code>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
          <input type="hidden" name="action" value="rzpa_seo_flush_permalinks">
          <button type="submit" class="rzpa-btn">🔄 Flush Permalinks</button>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
          <input type="hidden" name="action" value="rzpa_seo_clear_cache">
          <button type="submit" class="rzpa-btn">🗑 Ryd Cache</button>
        </form>
      </div>

      <!-- Danger Zone -->
      <div style="border:1px solid rgba(220,50,50,.4);border-radius:var(--radius);padding:16px;background:rgba(220,50,50,.04);">
        <h4 style="margin:0 0 8px;font-size:13px;color:#f66;">⚠️ Danger Zone</h4>
        <p style="margin:0 0 12px;font-size:12px;color:var(--text-muted);">
          Sletter permanent alle genererede pSEO WordPress-sider. Kan ikke fortrydes.
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
              style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
          <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
          <input type="hidden" name="action" value="rzpa_seo_delete_all_pseo">
          <input type="text" name="confirm_delete" placeholder='Skriv "SLET" for at bekræfte'
                 class="rzpa-input" style="max-width:260px;border-color:rgba(220,50,50,.5);">
          <button type="submit" class="rzpa-btn" style="background:rgba(220,50,50,.15);border-color:#f66;color:#f66;"
                  onclick="var v=this.form.querySelector('[name=confirm_delete]').value;if(v!=='SLET'){alert('Skriv SLET for at bekræfte.');return false;}return confirm('Er du helt sikker? Dette kan ikke fortrydes.');">
            🗑 Slet alle pSEO sider
          </button>
        </form>
      </div>

    </div>
  </div>

</div>

<style>
#page-seo-settings .rzpa-label {
  display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text);
}
#page-seo-settings .rzpa-hint {
  margin:4px 0 0;font-size:12px;color:var(--text-muted);
}
#page-seo-settings .rzpa-input {
  width:100%;background:var(--bg-300);border:1px solid var(--border);
  color:var(--text);padding:8px 12px;border-radius:var(--radius);font-size:13px;
}
#page-seo-settings .rzpa-select {
  width:100%;background:var(--bg-300);border:1px solid var(--border);
  color:var(--text);padding:8px 12px;border-radius:var(--radius);font-size:13px;
}
#page-seo-settings .rzpa-input:focus,
#page-seo-settings .rzpa-select:focus {
  outline:none;border-color:var(--neon);
}
</style>
