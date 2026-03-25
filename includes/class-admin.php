<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'admin_post_rzpa_save_settings', [ __CLASS__, 'save_settings' ] );
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

        // Chart.js from CDN
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js', [], null, true );

        // Plugin CSS & JS
        wp_enqueue_style(  'rzpa-admin', RZPA_URL . 'admin/css/dashboard.css', [], RZPA_VERSION );
        wp_enqueue_script( 'rzpa-admin', RZPA_URL . 'admin/js/dashboard.js',   [ 'chartjs' ], RZPA_VERSION, true );

        wp_localize_script( 'rzpa-admin', 'RZPA', [
            'apiBase' => rest_url( 'rzpa/v1' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
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
