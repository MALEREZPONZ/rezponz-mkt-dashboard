<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'admin_post_rzpa_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_init',            [ __CLASS__, 'handle_google_oauth' ] );
    }

    /**
     * Håndterer Google OAuth callback.
     * Når Google redirecter brugeren tilbage med en "code" parameter,
     * udveksler vi den til et refresh token og gemmer det i indstillingerne.
     */
    public static function handle_google_oauth() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;
        if ( empty( $_GET['page'] ) || $_GET['page'] !== 'rzpa-settings' ) return;
        if ( empty( $_GET['rzpa_google_oauth'] ) || empty( $_GET['code'] ) ) return;

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

    public static function add_menu() {
        add_menu_page(
            'Rezponz Analytics',
            'Rezponz Analytics',
            'manage_options',
            'rzpa-dashboard',
            [ __CLASS__, 'page_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#CCFF00"><rect x="2" y="2" width="9" height="9" rx="1"/><rect x="13" y="2" width="9" height="9" rx="1"/><rect x="2" y="13" width="9" height="9" rx="1"/><rect x="13" y="13" width="9" height="9" rx="1"/></svg>' ),
            3
        );

        $pages = [
            'rzpa-seo'       => [ 'SEO',              [ __CLASS__, 'page_seo' ] ],
            'rzpa-ai'        => [ 'AI-synlighed',     [ __CLASS__, 'page_ai' ] ],
            'rzpa-meta'      => [ 'Meta Ads',          [ __CLASS__, 'page_meta' ] ],
            'rzpa-snapchat'  => [ 'Snapchat Ads',      [ __CLASS__, 'page_snap' ] ],
            'rzpa-tiktok'    => [ 'TikTok Ads',        [ __CLASS__, 'page_tiktok' ] ],
            'rzpa-rapport'   => [ 'PDF Rapport',       [ __CLASS__, 'page_rapport' ] ],
            'rzpa-settings'  => [ 'Indstillinger',     [ __CLASS__, 'page_settings' ] ],
        ];

        foreach ( $pages as $slug => [$label, $cb] ) {
            add_submenu_page( 'rzpa-dashboard', $label . ' – Rezponz Analytics', $label, 'manage_options', $slug, $cb );
        }
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

        return [];
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpa_settings' );

        $fields = [
            'github_owner', 'github_repo', 'github_token',
            'google_client_id', 'google_client_secret', 'google_refresh_token', 'google_site_url',
            'serp_api_key',
            'meta_access_token', 'meta_ad_account_id',
            'snap_client_id', 'snap_client_secret', 'snap_access_token', 'snap_ad_account_id',
            'tiktok_access_token', 'tiktok_app_id', 'tiktok_advertiser_id',
            'openai_api_key',
        ];

        $opts = [];
        foreach ( $fields as $f ) {
            $opts[ $f ] = sanitize_text_field( $_POST[ $f ] ?? '' );
        }
        update_option( 'rzpa_settings', $opts );

        wp_redirect( admin_url( 'admin.php?page=rzpa-settings&saved=1' ) );
        exit;
    }

    // ── Page renderers ────────────────────────────────────────────────────────

    public static function page_dashboard() { include RZPA_DIR . 'admin/views/dashboard.php'; }
    public static function page_seo()       { include RZPA_DIR . 'admin/views/seo.php'; }
    public static function page_ai()        { include RZPA_DIR . 'admin/views/ai-search.php'; }
    public static function page_meta()      { include RZPA_DIR . 'admin/views/meta-ads.php'; }
    public static function page_snap()      { include RZPA_DIR . 'admin/views/snap-ads.php'; }
    public static function page_tiktok()    { include RZPA_DIR . 'admin/views/tiktok-ads.php'; }
    public static function page_rapport()   { include RZPA_DIR . 'admin/views/rapport.php'; }
    public static function page_settings()  { include RZPA_DIR . 'admin/views/settings.php'; }
}
