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
    <?php
    $g_id      = $opts['google_client_id']     ?? '';
    $g_secret  = $opts['google_client_secret'] ?? '';
    $g_token   = $opts['google_refresh_token'] ?? '';
    $g_url     = $opts['google_site_url']      ?? 'https://rezponz.dk';
    $g_has_creds = $g_id && $g_secret;
    $g_connected = $g_has_creds && $g_token;

    // OAuth authorize URL (bruges når creds er gemt men token mangler)
    $redirect_uri = admin_url( 'admin.php?page=rzpa-settings&rzpa_google_oauth=1' );
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( [
        'client_id'     => $g_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
    ] );

    // Success / error beskeder
    if ( isset( $_GET['google_connected'] ) ) :
    ?>
    <div class="rzpa-notice success" style="margin-bottom:20px">✅ Google Search Console er nu forbundet! Gå til SEO-siden og klik "Hent data".</div>
    <?php elseif ( isset( $_GET['google_error'] ) ) : ?>
    <div class="rzpa-notice error" style="margin-bottom:20px">❌ Noget gik galt. Sørg for at Client ID og Client Secret er gemt korrekt, og prøv igen.</div>
    <?php endif; ?>

    <div class="rzpa-card rzpa-settings-section">
      <h2>🔍 Google Search Console</h2>
      <p style="font-size:13px;color:#888;margin-bottom:20px;line-height:1.7">
        Google Search Console viser hvilke søgeord der bringer folk til <strong style="color:#ccc">rezponz.dk</strong>,
        og hvilke sider der rangerer bedst på Google — helt gratis data direkte fra Google.
      </p>

      <?php if ( $g_connected ) : ?>
      <!-- ✅ Forbundet -->
      <div style="background:rgba(204,255,0,.06);border:1px solid rgba(204,255,0,.2);border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:13px;font-weight:700;color:var(--neon);margin-bottom:4px">✅ Forbundet til Google Search Console</div>
          <div style="font-size:12px;color:#666">Henter data for: <strong style="color:#aaa"><?php echo esc_html($g_url); ?></strong></div>
        </div>
        <div style="display:flex;gap:8px">
          <a href="<?php echo esc_url($auth_url); ?>"
             style="font-size:12px;color:var(--text-muted);text-decoration:none;border:1px solid var(--border);padding:6px 12px;border-radius:6px">
            🔄 Genautoriser
          </a>
        </div>
      </div>
      <?php elseif ( $g_has_creds ) : ?>
      <!-- Creds gemt men ingen token endnu -->
      <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:16px 20px;margin-bottom:20px">
        <div style="font-size:13px;font-weight:700;color:var(--warn);margin-bottom:8px">⚡ Client ID og Secret er gemt — klik nu for at forbinde</div>
        <p style="font-size:12px;color:#888;margin-bottom:12px">Sørg for at <code style="color:#aaa"><?php echo esc_html($redirect_uri); ?></code> er tilføjet som godkendt redirect URI i dit Google Cloud projekt, gem derefter indstillingerne og klik:</p>
        <a href="<?php echo esc_url($auth_url); ?>" class="btn-primary" style="text-decoration:none;display:inline-block">
          🔑 Forbind til Google Search Console →
        </a>
      </div>
      <?php else : ?>
      <!-- Ikke konfigureret — vis trin-for-trin guide -->
      <div style="background:var(--bg-100);border:1px solid var(--border);border-radius:10px;padding:20px;margin-bottom:20px">
        <div style="font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:16px">Sådan forbinder du Google Search Console — 3 trin</div>
        <div style="display:flex;flex-direction:column;gap:16px">
          <div style="display:flex;gap:14px;align-items:flex-start">
            <div style="width:28px;height:28px;border-radius:50%;background:rgba(204,255,0,.12);border:1px solid rgba(204,255,0,.3);color:var(--neon);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">1</div>
            <div>
              <div style="font-size:13px;font-weight:700;color:#ccc;margin-bottom:4px">Opret Google Cloud credentials</div>
              <div style="font-size:12px;color:#666;line-height:1.7">
                Gå til <a href="https://console.cloud.google.com/" target="_blank" style="color:var(--neon)">console.cloud.google.com</a> →
                Opret projekt (eller vælg eksisterende) →
                API &amp; Services → Library → søg efter <strong style="color:#aaa">Search Console API</strong> → Aktivér →
                Credentials → Create Credentials → <strong style="color:#aaa">OAuth 2.0 Client ID</strong> →
                Application type: <strong style="color:#aaa">Web application</strong> →
                Tilføj Authorized redirect URI:<br>
                <code style="background:var(--bg-300);color:#CCFF00;padding:4px 8px;border-radius:4px;font-size:11px;word-break:break-all;display:inline-block;margin-top:4px"><?php echo esc_html($redirect_uri); ?></code>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:14px;align-items:flex-start">
            <div style="width:28px;height:28px;border-radius:50%;background:rgba(204,255,0,.12);border:1px solid rgba(204,255,0,.3);color:var(--neon);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">2</div>
            <div>
              <div style="font-size:13px;font-weight:700;color:#ccc;margin-bottom:4px">Udfyld Client ID og Secret herunder + gem</div>
              <div style="font-size:12px;color:#666">Du finder dem under Credentials → dit OAuth 2.0 Client ID.</div>
            </div>
          </div>
          <div style="display:flex;gap:14px;align-items:flex-start">
            <div style="width:28px;height:28px;border-radius:50%;background:rgba(204,255,0,.12);border:1px solid rgba(204,255,0,.3);color:var(--neon);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">3</div>
            <div>
              <div style="font-size:13px;font-weight:700;color:#ccc;margin-bottom:4px">Klik "Forbind til Google" — klar! ✅</div>
              <div style="font-size:12px;color:#666">Du bliver sendt til Google, godkender adgang, og sendes automatisk tilbage.</div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="rzpa-settings-grid">
        <div class="rzpa-field">
          <label>Google OAuth Client ID</label>
          <input type="text" name="google_client_id"
                 value="<?php echo esc_attr( $g_id ); ?>"
                 placeholder="xxxxx.apps.googleusercontent.com" />
        </div>
        <div class="rzpa-field">
          <label>Google OAuth Client Secret</label>
          <input type="password" name="google_client_secret"
                 value="<?php echo esc_attr( $g_secret ); ?>"
                 placeholder="GOCSPX-xxxxxxxxx" />
        </div>
        <div class="rzpa-field">
          <label>Site URL <span style="color:#555;font-weight:normal">(præcis URL fra Search Console)</span></label>
          <input type="text" name="google_site_url"
                 value="<?php echo esc_attr( $g_url ); ?>"
                 placeholder="https://www.rezponz.dk" />
          <small style="color:#555;font-size:11px;display:block;margin-top:4px">Kan være <code>https://rezponz.dk</code> eller <code>sc-domain:rezponz.dk</code> — tjek din Search Console</small>
        </div>
        <?php if ( $g_connected ) : ?>
        <div class="rzpa-field">
          <label>Refresh Token <span style="color:#555;font-weight:normal">(auto-gemt ved forbindelse)</span></label>
          <input type="password" name="google_refresh_token" value="<?php echo esc_attr( $g_token ); ?>" />
        </div>
        <?php else : ?>
        <input type="hidden" name="google_refresh_token" value="<?php echo esc_attr( $g_token ); ?>" />
        <?php endif; ?>
      </div>

      <?php if ( $g_has_creds && ! $g_connected ) : ?>
      <div style="margin-top:16px">
        <a href="<?php echo esc_url($auth_url); ?>" class="btn-primary" style="text-decoration:none;display:inline-block">
          🔑 Forbind til Google Search Console →
        </a>
        <span style="font-size:12px;color:#555;margin-left:12px">Husk at gemme indstillinger først!</span>
      </div>
      <?php endif; ?>
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
