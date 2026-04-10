<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'admin_head',            [ __CLASS__, 'menu_styles' ] );
        add_action( 'admin_post_rzpa_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_rzpa_pdf_download',  [ __CLASS__, 'handle_pdf_download' ] );
        add_action( 'admin_init',            [ __CLASS__, 'handle_google_oauth' ] );
    }

    /**
     * Stream a PDF download directly to the browser.
     * Hooked on admin_post_rzpa_pdf_download — no JSON wrapping.
     */
    public static function handle_pdf_download(): void {
        check_admin_referer( 'rzpa_pdf_download' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Ingen adgang', 403 );
        }
        $days  = (int) ( $_GET['days'] ?? 30 );
        $title = sanitize_text_field( wp_unslash( $_GET['title'] ?? '' ) );
        RZPA_PDF_Generator::download( $days, $title );
    }

    /**
     * Håndterer Google OAuth callback.
     * Når Google redirecter brugeren tilbage med en "code" parameter,
     * udveksler vi den til et refresh token og gemmer det i indstillingerne.
     */
    public static function handle_google_oauth() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;
        if ( empty( $_GET['page'] ) || $_GET['page'] !== 'rzpa-settings' ) return;

        if ( isset( $_GET['rzpa_google_oauth'] ) && isset( $_GET['code'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
            $opts = get_option( 'rzpa_settings', [] );
            $redirect_uri = admin_url( 'admin.php?page=rzpa-settings&rzpa_google_oauth=1' );

            $res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
                'timeout' => 15,
                'body'    => [
                    'code'          => $code,
                    'client_id'     => $opts['google_client_id']     ?? '',
                    'client_secret' => $opts['google_client_secret'] ?? '',
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                ],
            ] );

            if ( ! is_wp_error( $res ) ) {
                $data = json_decode( wp_remote_retrieve_body( $res ), true );
                if ( ! empty( $data['refresh_token'] ) ) {
                    $opts['google_refresh_token'] = $data['refresh_token'];
                    update_option( 'rzpa_settings', $opts );
                    wp_redirect( admin_url( 'admin.php?page=rzpa-settings&google_connected=1' ) );
                    exit;
                }
            }

            wp_redirect( admin_url( 'admin.php?page=rzpa-settings&google_error=1' ) );
            exit;
        }

        // Google Ads OAuth callback
        if ( isset( $_GET['rzpa_google_ads_oauth'] ) && isset( $_GET['code'] ) ) {
            $opts         = get_option( 'rzpa_settings', [] );
            $redirect_uri = admin_url( 'admin.php?page=rzpa-settings&rzpa_google_ads_oauth=1' );
            $res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'code'          => sanitize_text_field( $_GET['code'] ),
                    'client_id'     => $opts['google_ads_client_id'] ?? $opts['google_client_id'] ?? '',
                    'client_secret' => $opts['google_ads_client_secret'] ?? $opts['google_client_secret'] ?? '',
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                ],
            ] );
            $body = json_decode( wp_remote_retrieve_body( $res ), true );
            if ( ! empty( $body['refresh_token'] ) ) {
                $opts['google_ads_refresh_token'] = $body['refresh_token'];
                update_option( 'rzpa_settings', $opts );
                wp_redirect( admin_url( 'admin.php?page=rzpa-settings&gads_connected=1' ) );
            } else {
                wp_redirect( admin_url( 'admin.php?page=rzpa-settings&gads_error=1' ) );
            }
            exit;
        }
    }

    public static function add_menu() {
        add_menu_page(
            'Rezponz Marketing Platform',
            'Rezponz',
            'manage_options',
            'rzpa-dashboard',
            [ __CLASS__, 'page_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#CCFF00"><rect x="2" y="2" width="9" height="9" rx="1"/><rect x="13" y="2" width="9" height="9" rx="1"/><rect x="2" y="13" width="9" height="9" rx="1"/><rect x="13" y="13" width="9" height="9" rx="1"/></svg>' ),
            3
        );

        $cap = 'manage_options';

        // ── Sektion: Analyse & Ads ───────────────────────────────────────────
        add_submenu_page( 'rzpa-dashboard', '', '📊 Analyse & Ads', $cap, 'rzpa-section-ads', [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'rzpa-dashboard', 'SEO – Rezponz',              'SEO',              $cap, 'rzpa-seo',        [ __CLASS__, 'page_seo' ] );
        add_submenu_page( 'rzpa-dashboard', 'AI-synlighed – Rezponz',     'AI-synlighed',     $cap, 'rzpa-ai',         [ __CLASS__, 'page_ai' ] );
        add_submenu_page( 'rzpa-dashboard', 'Blog Indsigt – Rezponz',     'Blog Indsigt',     $cap, 'rzpa-blog',       [ __CLASS__, 'page_blog' ] );
        add_submenu_page( 'rzpa-dashboard', 'Meta Ads – Rezponz',         'Meta Ads',         $cap, 'rzpa-meta',       [ __CLASS__, 'page_meta' ] );
        add_submenu_page( 'rzpa-dashboard', 'Google Ads – Rezponz',       'Google Ads',       $cap, 'rzpa-google-ads', [ __CLASS__, 'page_google_ads' ] );
        add_submenu_page( 'rzpa-dashboard', 'Snapchat Ads – Rezponz',     'Snapchat Ads',     $cap, 'rzpa-snapchat',   [ __CLASS__, 'page_snap' ] );
        add_submenu_page( 'rzpa-dashboard', 'TikTok Ads – Rezponz',       'TikTok Ads',       $cap, 'rzpa-tiktok',     [ __CLASS__, 'page_tiktok' ] );
        add_submenu_page( 'rzpa-dashboard', 'PDF Rapport – Rezponz',      'PDF Rapport',      $cap, 'rzpa-rapport',    [ __CLASS__, 'page_rapport' ] );

        // ── Sektion: System ──────────────────────────────────────────────────
        add_submenu_page( 'rzpa-dashboard', '', '⚙️ System', $cap, 'rzpa-section-system', [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'rzpa-dashboard', 'Indstillinger – Rezponz',    'Indstillinger',    $cap, 'rzpa-settings',   [ __CLASS__, 'page_settings' ] );
    }

    /**
     * Inline CSS der styles WordPress-sidemenuen for Rezponz.
     * Vises på alle admin-sider så sektions-headers altid er pæne.
     */
    public static function menu_styles() : void {
        ?>
        <style id="rzpa-menu-styles">
        /* Sektions-headers i Rezponz-menuen */
        #adminmenu a[href$="rzpa-section-ads"],
        #adminmenu a[href$="rzpa-section-crew"],
        #adminmenu a[href$="rzpa-section-refs"],
        #adminmenu a[href$="rzpa-section-seo"],
        #adminmenu a[href$="rzpa-section-system"] {
            color: #CCFF00 !important;
            font-size: 9px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 1.2px !important;
            pointer-events: none !important;
            cursor: default !important;
            padding-top: 14px !important;
            padding-bottom: 2px !important;
            opacity: 1 !important;
            line-height: 1.2 !important;
        }
        #adminmenu li:has(a[href$="rzpa-section-ads"]),
        #adminmenu li:has(a[href$="rzpa-section-crew"]),
        #adminmenu li:has(a[href$="rzpa-section-refs"]),
        #adminmenu li:has(a[href$="rzpa-section-seo"]),
        #adminmenu li:has(a[href$="rzpa-section-system"]) {
            border-top: 1px solid rgba(204,255,0,.15) !important;
            margin-top: 6px !important;
        }
        #adminmenu li:has(a[href$="rzpa-section-ads"]) {
            border-top: none !important;
            margin-top: 0 !important;
        }
        </style>
        <?php
    }

    public static function enqueue( string $hook ) {
        if ( strpos( $hook, 'rzpa' ) === false ) return;

        // Chart.js – lokalt bundlet (ingen CDN-anmodning)
        wp_enqueue_script( 'chartjs', RZPA_URL . 'admin/js/chart.umd.min.js', [], '4.4.2', true );

        // Plugin CSS & JS
        wp_enqueue_style(  'rzpa-admin', RZPA_URL . 'admin/css/dashboard.css', [], RZPA_VERSION );
        wp_enqueue_script( 'rzpa-admin', RZPA_URL . 'admin/js/dashboard.js',   [ 'chartjs' ], RZPA_VERSION, true );

        // Gør plugin-scriptet defer så WP-admin-UI renderes med det samme
        wp_script_add_data( 'rzpa-admin', 'defer', true );

        wp_localize_script( 'rzpa-admin', 'RZPA', [
            'apiBase'         => rest_url( 'rzpa/v1' ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'preload'         => self::get_page_preload( $hook ),
            'meta_account_id' => sanitize_text_field( get_option( 'rzpa_settings', [] )['meta_ad_account_id'] ?? '' ),
            'settingsUrl'     => admin_url( 'admin.php?page=rzpa-settings' ),
            'adminPostUrl'    => admin_url( 'admin-post.php' ),
            'pdfNonce'        => wp_create_nonce( 'rzpa_pdf_download' ),
        ] );
    }

    /**
     * Indlejrer relevante DB-data direkte i siden ved PHP-rendering.
     * Resultatet sendes med i wp_localize_script som RZPA.preload,
     * så JS ikke behøver et separat REST-kald på første pageload.
     */
    private static function get_page_preload( string $hook ) : array {
        $days = 30; // Default – JS opdaterer ved datofilter-ændring

        // Dashboard (toplevel)
        if ( $hook === 'toplevel_page_rzpa-dashboard' ) {
            $cached = get_transient( 'rzpa_dash_overview_' . $days );
            if ( $cached ) return [ 'dashboard_overview' => $cached ];

            // Byg det live og gem i transient til næste besøg
            $opts    = get_option( 'rzpa_settings', [] );
            $meta_ok = ! empty( $opts['meta_access_token'] ) && ! empty( $opts['meta_ad_account_id'] );
            $snap_ok = ! empty( $opts['snap_access_token'] );
            $tt_ok   = ! empty( $opts['tiktok_access_token'] );

            $meta_sum = $meta_ok ? array_merge( RZPA_Database::get_meta_summary( $days ), [ 'configured' => true ] ) : [ 'configured' => false ];
            $snap_sum = $snap_ok ? array_merge( RZPA_Database::get_snap_summary( $days ), [ 'configured' => true ] ) : [ 'configured' => false ];
            $tt_sum   = $tt_ok   ? array_merge( RZPA_Database::get_tiktok_summary( $days ), [ 'configured' => true ] ) : [ 'configured' => false ];

            $data = [
                'seo'            => RZPA_Database::get_seo_summary( $days ),
                'meta'           => $meta_sum,
                'snap'           => $snap_sum,
                'tiktok'         => $tt_sum,
                'ai'             => RZPA_Database::get_ai_summary( $days ),
                'keywords'       => RZPA_Database::get_top_keywords( $days, 8 ),
                'meta_campaigns' => $meta_ok ? RZPA_Database::get_meta_campaigns( $days ) : [],
                'snap_campaigns' => $snap_ok ? RZPA_Database::get_snap_campaigns( $days ) : [],
                'tt_campaigns'   => $tt_ok   ? RZPA_Database::get_tiktok_campaigns( $days ) : [],
                'trends'         => RZPA_Database::get_ads_daily_trends( $days ),
            ];
            set_transient( 'rzpa_dash_overview_' . $days, $data, 5 * MINUTE_IN_SECONDS );
            return [ 'dashboard_overview' => $data ];
        }

        // Meta Ads
        if ( strpos( $hook, 'rzpa-meta' ) !== false ) {
            $opts    = get_option( 'rzpa_settings', [] );
            $meta_ok = ! empty( $opts['meta_access_token'] ) && ! empty( $opts['meta_ad_account_id'] );
            return [
                'meta_summary'   => $meta_ok
                    ? array_merge( RZPA_Database::get_meta_summary( $days ), [ 'configured' => true ] )
                    : [ 'configured' => false ],
                'meta_campaigns' => $meta_ok ? RZPA_Database::get_meta_campaigns( $days ) : [],
                'meta_has_data'  => RZPA_Database::has_meta_data( $days ),
            ];
        }

        // SEO
        if ( strpos( $hook, 'rzpa-seo' ) !== false ) {
            $opts   = get_option( 'rzpa_settings', [] );
            $configured = ! empty( $opts['google_client_id'] ) && ! empty( $opts['google_refresh_token'] );
            return [
                'seo_configured' => $configured,
                'seo_summary'    => RZPA_Database::get_seo_summary( $days ),
                'seo_keywords'   => RZPA_Database::get_top_keywords( $days, 20 ),
                'seo_pages'      => RZPA_Database::get_top_pages( $days, 20 ),
            ];
        }

        // Blog Indsigt
        if ( strpos( $hook, 'rzpa-blog' ) !== false ) {
            $opts       = get_option( 'rzpa_settings', [] );
            $seo_ok     = ! empty( $opts['google_client_id'] ) && ! empty( $opts['google_refresh_token'] );
            return [
                'blog_seo_configured' => $seo_ok,
                'blog_insights'       => RZPA_Database::get_blog_insights( 30 ),
            ];
        }

        return [];
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpa_settings' );

        // Felter der vises i formularen og kan ændres af brugeren
        $form_fields = [
            'github_owner', 'github_repo', 'github_token',
            'google_client_id', 'google_client_secret', 'google_site_url',
            'serp_api_key',
            'meta_access_token', 'meta_ad_account_id',
            'snap_client_id', 'snap_client_secret', 'snap_access_token', 'snap_ad_account_id',
            'tiktok_access_token', 'tiktok_app_id', 'tiktok_advertiser_id',
            'openai_api_key',
            'google_ads_developer_token', 'google_ads_customer_id', 'google_ads_manager_id',
            'google_ads_client_id', 'google_ads_client_secret',
            // SMTP
            'smtp_host', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
        ];

        // Textarea-felter der kræver sanitize_textarea_field
        $textarea_fields = [ 'serp_tracked_keywords' ];

        // Start med eksisterende indstillinger så OAuth-tokens (refresh tokens) bevares
        $opts = get_option( 'rzpa_settings', [] );
        foreach ( $form_fields as $f ) {
            $opts[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) );
        }
        foreach ( $textarea_fields as $f ) {
            $opts[ $f ] = sanitize_textarea_field( wp_unslash( $_POST[ $f ] ?? '' ) );
        }

        // SMTP — særlige felter
        $opts['smtp_enabled']    = isset( $_POST['smtp_enabled'] ) ? '1' : '';
        $opts['smtp_port']       = absint( $_POST['smtp_port'] ?? 587 );
        $opts['smtp_encryption'] = in_array( $_POST['smtp_encryption'] ?? 'tls', [ 'tls', 'ssl', 'none' ], true )
                                    ? sanitize_text_field( $_POST['smtp_encryption'] ) : 'tls';

        update_option( 'rzpa_settings', $opts );

        // Gem + redirect til OAuth hvis brugeren klikkede på forbind-knappen
        if ( ! empty( $_POST['rzpa_redirect_oauth'] ) && ! empty( $_POST['rzpa_gads_oauth_url'] ) ) {
            $oauth_url = esc_url_raw( wp_unslash( $_POST['rzpa_gads_oauth_url'] ) );
            // Valider at URL'en peger på Google OAuth
            if ( str_starts_with( $oauth_url, 'https://accounts.google.com/o/oauth2/' ) ) {
                wp_redirect( $oauth_url );
                exit;
            }
        }

        wp_redirect( admin_url( 'admin.php?page=rzpa-settings&saved=1' ) );
        exit;
    }

    // ── Page renderers ────────────────────────────────────────────────────────

    public static function page_dashboard() { include RZPA_DIR . 'admin/views/dashboard.php'; }
    public static function page_seo()       { include RZPA_DIR . 'admin/views/seo.php'; }
    public static function page_ai()        { include RZPA_DIR . 'admin/views/ai-search.php'; }
    public static function page_blog()      { include RZPA_DIR . 'admin/views/blog-insights.php'; }
    public static function page_meta()      { include RZPA_DIR . 'admin/views/meta-ads.php'; }
    public static function page_google_ads() {
        $f = RZPA_DIR . 'admin/views/google-ads.php';
        if ( file_exists( $f ) ) {
            include $f;
        } else {
            echo '<div style="padding:40px;color:#fff;background:#1a1a1a;border-radius:12px;margin:20px">';
            echo '<h2 style="color:#f87171">⚠️ Fejl: google-ads.php mangler</h2>';
            echo '<p style="color:#888">Forventet sti: <code style="color:#CCFF00">' . esc_html( $f ) . '</code></p>';
            echo '<p style="color:#888">Upload filen via FTP til denne placering og genindlæs siden.</p>';
            echo '</div>';
        }
    }
    public static function page_snap()      { include RZPA_DIR . 'admin/views/snap-ads.php'; }
    public static function page_tiktok()    { include RZPA_DIR . 'admin/views/tiktok-ads.php'; }
    public static function page_rapport()   { include RZPA_DIR . 'admin/views/rapport.php'; }
    public static function page_settings()  { include RZPA_DIR . 'admin/views/settings.php'; }
}
