<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$opts  = get_option( 'rzpa_settings', [] );
$saved = isset( $_GET['saved'] );

// Manuel "Tjek opdateringer" – ryd transient og redirect til WP's update-side
if ( isset( $_GET['rzpa_check_updates'] ) && check_admin_referer( 'rzpa_check_updates' ) ) {
    RZPA_Updater::flush_cache();
    delete_site_transient( 'update_plugins' );
    wp_redirect( admin_url( 'update-core.php' ) );
    exit;
}

// Hent opdateringsstatus til visning
$update_info = null;
$update_transient = get_site_transient( 'update_plugins' );
$plugin_slug      = plugin_basename( RZPA_PLUGIN_FILE );
if ( isset( $update_transient->response[ $plugin_slug ] ) ) {
    $update_info = $update_transient->response[ $plugin_slug ];
}
?>
<div id="rzpa-app">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/logo.svg' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">Indstillinger</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>Indstillinger</h1>
      <p class="page-sub">API-nøgler, GitHub auto-opdatering og konfiguration</p>
    </div>
  </div>

  <?php if ( $saved ) : ?>
  <div class="rzpa-notice success" style="margin-bottom:20px">✓ Indstillinger gemt.</div>
  <?php endif; ?>

  <?php if ( $update_info ) : ?>
  <div class="rzpa-notice" style="margin-bottom:20px;background:rgba(204,255,0,0.08);border:1px solid rgba(204,255,0,0.3);color:var(--neon);display:flex;align-items:center;justify-content:space-between">
    <span>🔔 Ny version tilgængelig: <strong><?php echo esc_html( $update_info->new_version ); ?></strong></span>
    <a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="btn-primary" style="text-decoration:none">Opdater nu →</a>
  </div>
  <?php else : ?>
  <div style="margin-bottom:20px;padding:12px 16px;background:var(--bg-200);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:space-between">
    <span style="font-size:13px;color:#666">✓ Plugin er opdateret &nbsp;·&nbsp; <span style="color:#444">Version <?php echo esc_html( RZPA_VERSION ); ?></span></span>
    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rzpa_check_updates', '1' ), 'rzpa_check_updates' ) ); ?>"
       style="font-size:12px;color:var(--text-muted);text-decoration:none;border:1px solid var(--border);padding:4px 12px;border-radius:6px"
       onmouseover="this.style.color='var(--neon)';this.style.borderColor='var(--neon)'"
       onmouseout="this.style.color='var(--text-muted)';this.style.borderColor='var(--border)'">
      ⟳ Tjek for opdateringer
    </a>
  </div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'rzpa_settings' ); ?>
    <input type="hidden" name="action" value="rzpa_save_settings" />

    <!-- ══ AUTO-OPDATERING ══════════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>🔄 Auto-opdatering via GitHub</h2>
      <p style="font-size:13px;color:#666;margin-bottom:16px;line-height:1.6">
        Når du udgiver en ny version på GitHub, vises opdateringen automatisk i WordPress (Kontrolpanel → Opdateringer).
        Du opdaterer med ét klik — præcis som et officielt plugin.
      </p>

      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>GitHub Brugernavn / Organisation</label>
          <input type="text" name="github_owner"
                 value="<?php echo esc_attr( $opts['github_owner'] ?? '' ); ?>"
                 placeholder="f.eks. rezponz" />
        </div>
        <div class="rzpa-field">
          <label>GitHub Repository navn</label>
          <input type="text" name="github_repo"
                 value="<?php echo esc_attr( $opts['github_repo'] ?? '' ); ?>"
                 placeholder="f.eks. rezponz-analytics-wp" />
        </div>
        <div class="rzpa-field">
          <label>GitHub Token <span style="color:#555;font-weight:normal">(kun nødvendig for private repos)</span></label>
          <input type="password" name="github_token"
                 value="<?php echo esc_attr( $opts['github_token'] ?? '' ); ?>"
                 placeholder="ghp_xxxxxxxxxxxx" />
        </div>
      </div>

      <!-- Vejledning -->
      <div style="margin-top:16px;background:var(--bg-100);border:1px solid var(--border);border-radius:8px;padding:16px">
        <p style="font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">Sådan udgiver du en opdatering</p>
        <ol style="font-size:12px;color:#666;line-height:2;padding-left:16px">
          <li>Opdatér <code style="color:#aaa">Version</code>-feltet øverst i <code style="color:#aaa">rezponz-analytics.php</code> (f.eks. <code style="color:#aaa">1.1.0</code>)</li>
          <li>Pak pluginmappen som ZIP: <code style="color:#aaa">rezponz-analytics-wp.zip</code></li>
          <li>Gå til dit GitHub-repo → <strong style="color:#aaa">Releases → Draft a new release</strong></li>
          <li>Sæt tag til versionsnummeret: <code style="color:#aaa">v1.1.0</code></li>
          <li>Upload ZIP'en som release-asset under <em>"Attach binaries"</em></li>
          <li>Klik <strong style="color:#aaa">Publish release</strong></li>
          <li>WordPress registrerer opdateringen automatisk inden for 6 timer<br>
              — eller brug knappen <em>"Tjek for opdateringer"</em> ovenfor for øjeblikkelig tjek</li>
        </ol>
      </div>

      <?php
      $owner = $opts['github_owner'] ?? '';
      $repo  = $opts['github_repo']  ?? '';
      if ( $owner && $repo ) : ?>
      <div style="margin-top:12px;font-size:12px;color:#555">
        📦 Repo: <a href="<?php echo esc_url( "https://github.com/{$owner}/{$repo}" ); ?>" target="_blank"
                    style="color:var(--neon)">github.com/<?php echo esc_html("{$owner}/{$repo}"); ?></a>
        &nbsp;·&nbsp;
        <a href="<?php echo esc_url( "https://github.com/{$owner}/{$repo}/releases/new" ); ?>" target="_blank"
           style="color:var(--muted)">Opret ny release →</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ GOOGLE SEARCH CONSOLE ══════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>🔍 Google Search Console</h2>
      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>Client ID</label>
          <input type="text" name="google_client_id"
                 value="<?php echo esc_attr( $opts['google_client_id'] ?? '' ); ?>"
                 placeholder="xxx.apps.googleusercontent.com" />
        </div>
        <div class="rzpa-field">
          <label>Client Secret</label>
          <input type="password" name="google_client_secret"
                 value="<?php echo esc_attr( $opts['google_client_secret'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>Refresh Token</label>
          <input type="password" name="google_refresh_token"
                 value="<?php echo esc_attr( $opts['google_refresh_token'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>Site URL</label>
          <input type="text" name="google_site_url"
                 value="<?php echo esc_attr( $opts['google_site_url'] ?? 'https://rezponz.dk' ); ?>" />
        </div>
      </div>
      <p style="font-size:12px;color:#444;margin-top:8px">
        Opret OAuth 2.0 credentials i Google Cloud Console → Search Console API.
        Brug <a href="https://developers.google.com/oauthplayground" target="_blank" style="color:var(--neon)">OAuth Playground</a> til at generere refresh token.
      </p>
    </div>

    <!-- ══ SERPAPI ═══════════════════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>🤖 SerpAPI (AI Overview tracking)</h2>
      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>SerpAPI Key</label>
          <input type="password" name="serp_api_key"
                 value="<?php echo esc_attr( $opts['serp_api_key'] ?? '' ); ?>"
                 placeholder="Din SerpAPI nøgle" />
        </div>
      </div>
      <p style="font-size:12px;color:#444;margin-top:8px">
        Opret konto på <a href="https://serpapi.com" target="_blank" style="color:var(--neon)">serpapi.com</a> for at tracke Google AI Overviews.
      </p>
    </div>

    <!-- ══ META ══════════════════════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>📘 Meta Ads (Facebook + Instagram)</h2>
      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>Access Token</label>
          <input type="password" name="meta_access_token"
                 value="<?php echo esc_attr( $opts['meta_access_token'] ?? '' ); ?>"
                 placeholder="EAAxxxxxxx" />
        </div>
        <div class="rzpa-field">
          <label>Ad Account ID</label>
          <input type="text" name="meta_ad_account_id"
                 value="<?php echo esc_attr( $opts['meta_ad_account_id'] ?? '' ); ?>"
                 placeholder="123456789" />
        </div>
      </div>
      <p style="font-size:12px;color:#444;margin-top:8px">
        Opret System User Access Token i Meta Business Manager med tilladelserne: <code style="color:#888">ads_read, ads_management</code>
      </p>
    </div>

    <!-- ══ SNAPCHAT ═══════════════════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>👻 Snapchat Ads</h2>
      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>Client ID</label>
          <input type="text" name="snap_client_id"
                 value="<?php echo esc_attr( $opts['snap_client_id'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>Client Secret</label>
          <input type="password" name="snap_client_secret"
                 value="<?php echo esc_attr( $opts['snap_client_secret'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>Access Token</label>
          <input type="password" name="snap_access_token"
                 value="<?php echo esc_attr( $opts['snap_access_token'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>Ad Account ID</label>
          <input type="text" name="snap_ad_account_id"
                 value="<?php echo esc_attr( $opts['snap_ad_account_id'] ?? '' ); ?>" />
        </div>
      </div>
      <p style="font-size:12px;color:#444;margin-top:8px">
        Opret app via <a href="https://kit.snapchat.com" target="_blank" style="color:var(--neon)">Snap Kit Developer Portal</a>.
      </p>
    </div>

    <!-- ══ TIKTOK ══════════════════════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>🎵 TikTok for Business</h2>
      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>Access Token</label>
          <input type="password" name="tiktok_access_token"
                 value="<?php echo esc_attr( $opts['tiktok_access_token'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>App ID</label>
          <input type="text" name="tiktok_app_id"
                 value="<?php echo esc_attr( $opts['tiktok_app_id'] ?? '' ); ?>" />
        </div>
        <div class="rzpa-field">
          <label>Advertiser ID</label>
          <input type="text" name="tiktok_advertiser_id"
                 value="<?php echo esc_attr( $opts['tiktok_advertiser_id'] ?? '' ); ?>" />
        </div>
      </div>
      <p style="font-size:12px;color:#444;margin-top:8px">
        Ansøg om adgang via <a href="https://ads.tiktok.com/marketing_api/" target="_blank" style="color:var(--neon)">TikTok for Business Developer</a>.
      </p>
    </div>

    <!-- ══ OPENAI ══════════════════════════════════════════════════════════════ -->
    <div class="rzpa-card rzpa-settings-section">
      <h2>✨ OpenAI (PDF anbefalinger)</h2>
      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>OpenAI API Key</label>
          <input type="password" name="openai_api_key"
                 value="<?php echo esc_attr( $opts['openai_api_key'] ?? '' ); ?>"
                 placeholder="sk-xxx" />
        </div>
      </div>
      <p style="font-size:12px;color:#444;margin-top:8px">
        Bruges til at generere AI-anbefalinger i PDF-rapporten. Opret nøgle på
        <a href="https://platform.openai.com" target="_blank" style="color:var(--neon)">platform.openai.com</a>.
      </p>
    </div>

    <button type="submit" class="btn-primary" style="font-size:14px;padding:10px 24px">
      💾 Gem indstillinger
    </button>

  </form>

</div>
