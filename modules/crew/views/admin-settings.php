<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rzpz-crew-wrap">
  <div class="rzpz-crew-header">
    <div>
      <h1>⚙️ Crew Indstillinger</h1>
      <p class="rzpz-crew-sub">Konfigurér Rezponz Crew-modulet</p>
    </div>
  </div>
  <?php if ( $saved ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-success">✓ Indstillinger gemt.</div>
  <?php endif; ?>
  <div class="rzpz-crew-card" style="max-width:600px">
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rzpz_crew_save_settings' ); ?>
      <input type="hidden" name="action" value="rzpz_crew_save_settings" />

      <div class="rzpz-crew-field">
        <label>Standard destinations-URL</label>
        <input type="url" name="default_destination_url" value="<?php echo esc_attr( $opts['default_destination_url'] ?? 'https://rezponz.dk/jobs/' ); ?>" />
        <small>Standard URL der bruges, når der genereres links uden specifik destinations-URL</small>
      </div>

      <div class="rzpz-crew-field">
        <label>Konverterings-URL (thank-you page)</label>
        <input type="url" name="conversion_url" value="<?php echo esc_attr( $opts['conversion_url'] ?? 'https://rezponz.dk/tak-for-din-ansoegning/' ); ?>" />
        <small>Siden besøgende lander på efter en ansøgning — registrerer konverteringen</small>
      </div>

      <div class="rzpz-crew-field">
        <label>Cookie-varighed (dage)</label>
        <input type="number" name="cookie_days" value="<?php echo (int) ( $opts['cookie_days'] ?? 30 ); ?>" min="1" max="365" />
        <small>Hvor længe UTM-referencen huskes i besøgendes browser</small>
      </div>

      <div class="rzpz-crew-field-row" style="gap:16px">
        <div class="rzpz-crew-field">
          <label>Boost-tærskel: leads</label>
          <input type="number" name="boost_conversions_threshold" value="<?php echo (int) ( $opts['boost_conversions_threshold'] ?? 1 ); ?>" min="0" />
          <small>Minimum leads for at et link vises som boost-kandidat</small>
        </div>
        <div class="rzpz-crew-field">
          <label>Boost-tærskel: CTR %</label>
          <input type="number" name="boost_ctr_threshold" value="<?php echo (float) ( $opts['boost_ctr_threshold'] ?? 2.0 ); ?>" min="0" step="0.1" />
          <small>Minimum CTR% for boost-kandidat</small>
        </div>
      </div>

      <div class="rzpz-crew-field" style="margin-top:16px">
        <label>Shortcode til frontend dashboard</label>
        <code class="rzpz-crew-code">[rezponz_crew_dashboard]</code>
        <small>Indsæt på en WordPress-side for at give Crew Members adgang til deres personlige dashboard</small>
      </div>

      <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary" style="margin-top:16px">💾 Gem indstillinger</button>
    </form>
  </div>
</div>
