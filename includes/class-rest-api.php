<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_REST_API {

    const NS = 'rzpa/v1';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $auth = [ '__return_true' ]; // handled per-route below
        $cap  = function() { return current_user_can( 'manage_options' ); };

        // Status
        register_rest_route( self::NS, '/status', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_status' ],
            'permission_callback' => $cap,
        ] );

        // Full sync
        register_rest_route( self::NS, '/sync', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'run_sync' ],
            'permission_callback' => $cap,
        ] );

        // SEO
        foreach ( [
            [ 'seo/keywords',     'GET',  'seo_keywords' ],
            [ 'seo/summary',      'GET',  'seo_summary' ],
            [ 'seo/keyword-trend','GET',  'seo_keyword_trend' ],
            [ 'seo/pages',        'GET',  'seo_pages' ],
            [ 'seo/sync',         'POST', 'seo_sync' ],
        ] as [ $path, $method, $cb ] ) {
            register_rest_route( self::NS, '/' . $path, [
                'methods'             => $method,
                'callback'            => [ __CLASS__, $cb ],
                'permission_callback' => $cap,
            ] );
        }

        // AI
        foreach ( [
            [ 'ai/overview',     'GET',    'ai_overview' ],
            [ 'ai/summary',      'GET',    'ai_summary' ],
            [ 'ai/manual-logs',  'GET',    'ai_manual_logs_get' ],
            [ 'ai/manual-logs',  'POST',   'ai_manual_logs_post' ],
            [ 'ai/manual-logs/(?P<id>\d+)', 'DELETE', 'ai_manual_log_delete' ],
            [ 'ai/sync',         'POST',   'ai_sync' ],
        ] as [ $path, $method, $cb ] ) {
            register_rest_route( self::NS, '/' . $path, [
                'methods'             => $method,
                'callback'            => [ __CLASS__, $cb ],
                'permission_callback' => $cap,
            ] );
        }

        // Ads platforms
        foreach ( [ 'meta', 'snap', 'tiktok' ] as $platform ) {
            register_rest_route( self::NS, "/{$platform}/campaigns", [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, "{$platform}_campaigns" ],
                'permission_callback' => $cap,
            ] );
            register_rest_route( self::NS, "/{$platform}/summary", [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, "{$platform}_summary" ],
                'permission_callback' => $cap,
            ] );
            register_rest_route( self::NS, "/{$platform}/sync", [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, "{$platform}_sync" ],
                'permission_callback' => $cap,
            ] );
        }

        // Trends (time-series for dashboard)
        register_rest_route( self::NS, '/ads/trends', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'ads_trends' ],
            'permission_callback' => $cap,
        ] );

        // Månedligt forbrug
        register_rest_route( self::NS, '/meta/monthly', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_monthly' ],
            'permission_callback' => $cap,
        ] );

        // Har vi data for en given periode?
        register_rest_route( self::NS, '/meta/has-data', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_has_data' ],
            'permission_callback' => $cap,
        ] );

        // Ad creative viewer – alle ads i én kampagne
        register_rest_route( self::NS, '/meta/campaign-ads', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_campaign_ads' ],
            'permission_callback' => $cap,
        ] );

        // Ad preview – iframe HTML for én enkelt annonce
        register_rest_route( self::NS, '/meta/ad-preview', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_ad_preview' ],
            'permission_callback' => $cap,
        ] );

        // Kombineret dashboard endpoint – ét kald i stedet for 10
        register_rest_route( self::NS, '/dashboard/overview', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'dashboard_overview' ],
            'permission_callback' => $cap,
        ] );

        // PDF
        register_rest_route( self::NS, '/pdf/generate', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'pdf_generate' ],
            'permission_callback' => $cap,
        ] );

        // Ryd al data (bruges til at fjerne mock-data fra DB)
        register_rest_route( self::NS, '/clear-data', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'clear_data' ],
            'permission_callback' => $cap,
        ] );
    }

    private static function days( WP_REST_Request $r ) : int {
        return (int) ( $r->get_param( 'days' ) ?: 30 );
    }

    private static function ok( $data ) {
        return rest_ensure_response( [ 'success' => true, 'data' => $data ] );
    }

    // ── Handlers ─────────────────────────────────────────────────────────────

    public static function get_status() {
        return self::ok( [
            'version'   => RZPA_VERSION,
            'timestamp' => gmdate( 'c' ),
            'syncs'     => RZPA_Database::get_last_syncs(),
        ] );
    }

    public static function run_sync() {
        RZPA_Scheduler::run_full_sync();
        // Ryd alle cache-transients
        foreach ( [ 7, 30, 90 ] as $d ) {
            delete_transient( 'rzpa_dash_overview_' . $d );
        }
        foreach ( [ 3, 6, 12 ] as $m ) {
            delete_transient( 'rzpa_meta_monthly_' . $m );
        }
        return self::ok( 'Full sync completed' );
    }

    // SEO
    public static function seo_keywords( $r ) {
        return self::ok( RZPA_Database::get_top_keywords( self::days( $r ), 20 ) );
    }
    public static function seo_summary( $r ) {
        return self::ok( RZPA_Database::get_seo_summary( self::days( $r ) ) );
    }
    public static function seo_keyword_trend( $r ) {
        $kw = sanitize_text_field( $r->get_param( 'keyword' ) ?? '' );
        return self::ok( RZPA_Database::get_keyword_trend( $kw, self::days( $r ) ) );
    }
    public static function seo_pages( $r ) {
        return self::ok( RZPA_Database::get_top_pages( self::days( $r ), 30 ) );
    }
    public static function seo_sync( $r ) {
        $rows = RZPA_Google_SEO::fetch( 90 );
        RZPA_Database::insert_seo_rows( $rows );
        $page_rows = RZPA_Google_SEO::fetch_pages( 90 );
        RZPA_Database::insert_seo_page_rows( $page_rows );
        RZPA_Database::log_sync( 'google_search_console', 'success', count( $rows ) . ' keywords, ' . count( $page_rows ) . ' pages' );
        return self::ok( [ 'keywords' => count( $rows ), 'pages' => count( $page_rows ) ] );
    }

    // AI
    public static function ai_overview( $r ) {
        return self::ok( RZPA_Database::get_ai_overview( self::days( $r ) ) );
    }
    public static function ai_summary( $r ) {
        return self::ok( RZPA_Database::get_ai_summary( self::days( $r ) ) );
    }
    public static function ai_manual_logs_get( $r ) {
        return self::ok( RZPA_Database::get_ai_manual_logs( self::days( $r ) ) );
    }
    public static function ai_manual_logs_post( WP_REST_Request $r ) {
        $data = $r->get_json_params();
        if ( empty( $data['platform'] ) || empty( $data['query'] ) ) {
            return new WP_Error( 'missing_fields', 'platform and query required', [ 'status' => 400 ] );
        }
        $data['date'] = $data['date'] ?? gmdate( 'Y-m-d' );
        $id = RZPA_Database::insert_ai_manual_log( $data );
        return self::ok( [ 'id' => $id ] );
    }
    public static function ai_manual_log_delete( WP_REST_Request $r ) {
        RZPA_Database::delete_ai_manual_log( (int) $r['id'] );
        return self::ok( true );
    }
    public static function ai_sync() {
        $keywords = [ 'rezponz', 'marketing automation platform', 'lead generation software',
                      'crm tool for agencies', 'digital marketing dashboard' ];
        $rows = [];
        $opts = get_option( 'rzpa_settings', [] );

        foreach ( $keywords as $kw ) {
            if ( ! empty( $opts['serp_api_key'] ) ) {
                $res = wp_remote_get( 'https://serpapi.com/search.json?' . http_build_query( [
                    'q'       => $kw,
                    'api_key' => $opts['serp_api_key'],
                    'gl'      => 'dk',
                    'hl'      => 'da',
                ] ), [ 'timeout' => 15 ] );
                if ( ! is_wp_error( $res ) ) {
                    $data = json_decode( wp_remote_retrieve_body( $res ), true );
                    $rows[] = [
                        'date'                 => gmdate( 'Y-m-d' ),
                        'keyword'              => $kw,
                        'has_ai_overview'      => ! empty( $data['ai_overview'] ) ? 1 : 0,
                        'has_featured_snippet' => ! empty( $data['answer_box'] ) ? 1 : 0,
                        'has_paa'              => ! empty( $data['related_questions'] ) ? 1 : 0,
                        'ai_overview_text'     => $data['ai_overview']['text_blocks'][0]['snippet'] ?? '',
                        'source'               => 'serpapi',
                    ];
                    continue;
                }
            }
            // Mock
            $rows[] = [
                'date'                 => gmdate( 'Y-m-d' ),
                'keyword'              => $kw,
                'has_ai_overview'      => wp_rand( 0, 9 ) > 5 ? 1 : 0,
                'has_featured_snippet' => wp_rand( 0, 9 ) > 6 ? 1 : 0,
                'has_paa'              => wp_rand( 0, 9 ) > 4 ? 1 : 0,
                'ai_overview_text'     => '',
                'source'               => 'mock',
            ];
        }

        if ( $rows ) RZPA_Database::insert_ai_overview_rows( $rows );
        RZPA_Database::log_sync( 'ai_overview', 'success', count( $rows ) . ' keywords' );
        return self::ok( [ 'count' => count( $rows ) ] );
    }

    // Meta
    public static function meta_campaigns( $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            return self::ok( [] );
        }
        return self::ok( RZPA_Database::get_meta_campaigns( self::days( $r ) ) );
    }
    public static function meta_summary( $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            return self::ok( [ 'configured' => false ] );
        }
        $data = RZPA_Database::get_meta_summary( self::days( $r ) );
        $data['configured'] = true;
        return self::ok( $data );
    }
    public static function meta_sync( $r ) {
        $days = self::days( $r );
        $rows = RZPA_Meta_Ads::fetch( $days );
        if ( isset( $rows['__error'] ) ) {
            RZPA_Database::log_sync( 'meta_ads', 'error', $rows['__error'] );
            return new WP_Error( 'meta_token_error', $rows['__error'], [ 'status' => 400 ] );
        }
        // insert_meta_campaigns rydder kun den specifikke periode og indsætter ny data
        RZPA_Database::insert_meta_campaigns( $rows, $days );
        RZPA_Database::log_sync( 'meta_ads', 'success', count( $rows ) . ' campaigns for ' . $days . ' days' );
        // Ryd dashboard cache for denne periode
        delete_transient( 'rzpa_dash_overview_' . $days );
        return self::ok( [ 'count' => count( $rows ), 'days' => $days ] );
    }

    public static function meta_monthly( $r ) {
        $months = (int) ( $r->get_param( 'months' ) ?: 6 );
        $key    = 'rzpa_meta_monthly_' . $months;
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $data = RZPA_Meta_Ads::fetch_monthly( $months );
        if ( $data ) set_transient( $key, $data, 6 * HOUR_IN_SECONDS );
        return self::ok( $data );
    }

    public static function meta_has_data( $r ) {
        $days = self::days( $r );
        return self::ok( [ 'has_data' => RZPA_Database::has_meta_data( $days ), 'days' => $days ] );
    }

    public static function meta_campaign_ads( WP_REST_Request $r ) {
        $campaign_id = sanitize_text_field( $r->get_param( 'campaign_id' ) ?? '' );
        if ( ! $campaign_id ) {
            return new WP_Error( 'missing_param', 'campaign_id required', [ 'status' => 400 ] );
        }
        $key    = 'rzpa_camp_ads_' . md5( $campaign_id );
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $ads = RZPA_Meta_Ads::fetch_campaign_ads( $campaign_id );
        if ( $ads ) set_transient( $key, $ads, HOUR_IN_SECONDS );
        return self::ok( $ads );
    }

    public static function meta_ad_preview( WP_REST_Request $r ) {
        $ad_id = sanitize_text_field( $r->get_param( 'ad_id' ) ?? '' );
        if ( ! $ad_id ) {
            return new WP_Error( 'missing_param', 'ad_id required', [ 'status' => 400 ] );
        }
        $key    = 'rzpa_adprev_' . md5( $ad_id );
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( [ 'iframe_html' => $cached ] );
        $html = RZPA_Meta_Ads::fetch_ad_preview( $ad_id );
        if ( $html ) set_transient( $key, $html, 30 * MINUTE_IN_SECONDS );
        return self::ok( [ 'iframe_html' => $html ] );
    }

    // Snap
    public static function snap_campaigns( $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['snap_access_token'] ) ) return self::ok( [] );
        return self::ok( RZPA_Database::get_snap_campaigns( self::days( $r ) ) );
    }
    public static function snap_summary( $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['snap_access_token'] ) ) {
            return self::ok( [ 'configured' => false ] );
        }
        $data = RZPA_Database::get_snap_summary( self::days( $r ) );
        $data['configured'] = true;
        return self::ok( $data );
    }
    public static function snap_sync( $r ) {
        $rows = RZPA_Snapchat_Ads::fetch( self::days( $r ) );
        RZPA_Database::insert_snap_campaigns( $rows );
        RZPA_Database::log_sync( 'snapchat_ads', 'success', count( $rows ) . ' campaigns' );
        return self::ok( [ 'count' => count( $rows ) ] );
    }

    // TikTok
    public static function tiktok_campaigns( $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['tiktok_access_token'] ) ) return self::ok( [] );
        return self::ok( RZPA_Database::get_tiktok_campaigns( self::days( $r ) ) );
    }
    public static function tiktok_summary( $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['tiktok_access_token'] ) ) {
            return self::ok( [ 'configured' => false ] );
        }
        $data = RZPA_Database::get_tiktok_summary( self::days( $r ) );
        $data['configured'] = true;
        return self::ok( $data );
    }
    public static function tiktok_sync( $r ) {
        $rows = RZPA_TikTok_Ads::fetch( self::days( $r ) );
        RZPA_Database::insert_tiktok_campaigns( $rows );
        RZPA_Database::log_sync( 'tiktok_ads', 'success', count( $rows ) . ' campaigns' );
        return self::ok( [ 'count' => count( $rows ) ] );
    }

    // Trends
    public static function ads_trends( $r ) {
        return self::ok( RZPA_Database::get_ads_daily_trends( self::days( $r ) ) );
    }

    // Kombineret dashboard overview – erstatter 10 separate kald med ét
    public static function dashboard_overview( $r ) {
        $days = self::days( $r );
        $key  = 'rzpa_dash_overview_' . $days;

        $cached = get_transient( $key );
        if ( $cached !== false ) {
            return self::ok( $cached );
        }

        $opts          = get_option( 'rzpa_settings', [] );
        $meta_ok       = ! empty( $opts['meta_access_token'] ) && ! empty( $opts['meta_ad_account_id'] );
        $snap_ok       = ! empty( $opts['snap_access_token'] );
        $tt_ok         = ! empty( $opts['tiktok_access_token'] );

        $meta_sum  = $meta_ok ? RZPA_Database::get_meta_summary( $days )   : [ 'configured' => false ];
        $snap_sum  = $snap_ok ? RZPA_Database::get_snap_summary( $days )   : [ 'configured' => false ];
        $tt_sum    = $tt_ok   ? RZPA_Database::get_tiktok_summary( $days ) : [ 'configured' => false ];
        if ( $meta_ok ) $meta_sum['configured'] = true;
        if ( $snap_ok ) $snap_sum['configured'] = true;
        if ( $tt_ok )   $tt_sum['configured']   = true;

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

        set_transient( $key, $data, 5 * MINUTE_IN_SECONDS ); // 5 min cache
        return self::ok( $data );
    }

    // Ryd data
    public static function clear_data( WP_REST_Request $r ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'rzpa_';
        $tables = [ 'seo_keywords', 'seo_pages', 'meta_campaigns', 'snap_campaigns', 'tiktok_campaigns', 'ai_overview', 'ai_manual_logs', 'ads_daily', 'sync_log' ];
        $body   = $r->get_json_params();
        $only   = $body['only'] ?? 'all'; // 'all', 'meta', 'seo', 'snap', 'tiktok'

        if ( $only === 'all' ) {
            foreach ( $tables as $t ) {
                $wpdb->query( "TRUNCATE TABLE `{$prefix}{$t}`" ); // phpcs:ignore
            }
        } else {
            $map = [
                'meta'   => [ 'meta_campaigns' ],
                'seo'    => [ 'seo_keywords', 'seo_pages' ],
                'snap'   => [ 'snap_campaigns' ],
                'tiktok' => [ 'tiktok_campaigns' ],
            ];
            foreach ( ( $map[ $only ] ?? [] ) as $t ) {
                $wpdb->query( "TRUNCATE TABLE `{$prefix}{$t}`" ); // phpcs:ignore
            }
        }
        return self::ok( 'Data ryddet: ' . $only );
    }

    // PDF
    public static function pdf_generate( WP_REST_Request $r ) {
        $days  = (int) ( $r->get_json_params()['days'] ?? 30 );
        $title = sanitize_text_field( $r->get_json_params()['title'] ?? '' );
        return RZPA_PDF_Generator::generate( $days, $title );
    }
}
