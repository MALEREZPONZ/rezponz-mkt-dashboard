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
            [ 'seo/test',         'GET',  'seo_test' ],
            [ 'seo/content-map',  'GET',  'seo_content_map' ],
            [ 'seo/monthly',      'GET',  'seo_monthly' ],
            [ 'seo/comparison',   'GET',  'seo_comparison' ],
            [ 'seo/ai-analysis',  'POST', 'seo_ai_analysis' ],
        ] as [ $path, $method, $cb ] ) {
            register_rest_route( self::NS, '/' . $path, [
                'methods'             => $method,
                'callback'            => [ __CLASS__, $cb ],
                'permission_callback' => $cap,
            ] );
        }

        // AI
        foreach ( [
            [ 'ai/overview',        'GET',    'ai_overview' ],
            [ 'ai/summary',         'GET',    'ai_summary' ],
            [ 'ai/keyword-status',  'GET',    'ai_keyword_status' ],
            [ 'ai/manual-logs',     'GET',    'ai_manual_logs_get' ],
            [ 'ai/manual-logs',     'POST',   'ai_manual_logs_post' ],
            [ 'ai/manual-logs/(?P<id>\d+)', 'DELETE', 'ai_manual_log_delete' ],
            [ 'ai/sync',            'POST',   'ai_sync' ],
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

        // Google Ads
        foreach ( [
            [ 'google-ads/campaigns', 'GET',  'google_ads_campaigns' ],
            [ 'google-ads/summary',   'GET',  'google_ads_summary'   ],
            [ 'google-ads/sync',      'POST', 'google_ads_sync'      ],
            [ 'google-ads/has-data',  'GET',  'google_ads_has_data'  ],
            [ 'google-ads/monthly',   'GET',  'google_ads_monthly'   ],
            [ 'google-ads/invoices',  'GET',  'google_ads_invoices'  ],
            [ 'google-ads/test',      'GET',  'google_ads_test'      ],
            [ 'google-ads/ai-analysis','POST','google_ads_ai_analysis'],
        ] as [ $path, $method, $cb ] ) {
            register_rest_route( self::NS, '/' . $path, [
                'methods'             => $method,
                'callback'            => [ __CLASS__, $cb ],
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

        register_rest_route( self::NS, '/meta/invoices', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_invoices' ],
            'permission_callback' => $cap,
        ] );

        register_rest_route( self::NS, '/meta/ai-analysis', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'meta_ai_analysis' ],
            'permission_callback' => $cap,
        ] );

        register_rest_route( self::NS, '/meta/ai-copy', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'meta_ai_copy' ],
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

        // Top ads – ad-level insights sorteret efter rækkevidde
        register_rest_route( self::NS, '/meta/top-ads', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_top_ads' ],
            'permission_callback' => $cap,
        ] );

        // Diagnostik – viser rå Meta API-svar (kun admins)
        register_rest_route( self::NS, '/meta/debug', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_debug' ],
            'permission_callback' => $cap,
        ] );

        // Landing pages – unikke URLs fra aktive annoncer
        register_rest_route( self::NS, '/meta/landing-pages', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_landing_pages' ],
            'permission_callback' => $cap,
        ] );

        // Snap ads (ad-level)
        register_rest_route( self::NS, '/snap/ads', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'snap_ads' ],
            'permission_callback' => $cap,
        ] );

        // TikTok ads (ad-level)
        register_rest_route( self::NS, '/tiktok/ads', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'tiktok_ads' ],
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

        // Meta image proxy (server-side fetch for auth-protected thumbnails)
        register_rest_route( self::NS, '/meta/image-proxy', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_image_proxy' ],
            'permission_callback' => $cap,
        ] );

        // Google Ads – alle aktive annoncer
        register_rest_route( self::NS, '/google-ads/ads', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'google_ads_ads' ],
            'permission_callback' => $cap,
        ] );

        // SEO keyword suggestions (AI)
        register_rest_route( self::NS, '/seo/keyword-suggestions', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'seo_keyword_suggestions' ],
            'permission_callback' => $cap,
        ] );

        // Budget recommendations (AI)
        register_rest_route( self::NS, '/ads/budget-recommendations', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ads_budget_recommendations' ],
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
        $rows  = RZPA_Google_SEO::fetch( 90 );
        $error = RZPA_Google_SEO::$last_error;

        if ( $error && empty( $rows ) ) {
            RZPA_Database::log_sync( 'google_search_console', 'error', $error );
            return self::ok( [ 'success' => false, 'error' => $error, 'keywords' => 0, 'pages' => 0 ] );
        }

        RZPA_Database::insert_seo_rows( $rows );
        $page_rows = RZPA_Google_SEO::fetch_pages( 90 );
        RZPA_Database::insert_seo_page_rows( $page_rows );
        RZPA_Database::log_sync( 'google_search_console', 'success', count( $rows ) . ' keywords, ' . count( $page_rows ) . ' pages' );
        return self::ok( [ 'success' => true, 'keywords' => count( $rows ), 'pages' => count( $page_rows ) ] );
    }

    public static function seo_test() {
        return self::ok( RZPA_Google_SEO::test_connection() );
    }
    public static function seo_content_map() {
        return self::ok( RZPA_Database::get_wp_content_map() );
    }
    public static function seo_monthly() {
        return self::ok( RZPA_Database::get_seo_monthly( 6 ) );
    }
    public static function seo_comparison( $r ) {
        return self::ok( RZPA_Database::get_seo_comparison( self::days( $r ) ) );
    }
    public static function seo_ai_analysis( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI nøgle — tilføj den i Indstillinger' ] );

        $body = $r->get_json_params();
        $kw   = array_slice( $body['keywords'] ?? [], 0, 20 );
        $pages = array_slice( $body['pages'] ?? [], 0, 15 );

        $kwText    = implode( "\n", array_map( fn($k) => "- {$k['keyword']}: pos {$k['avg_position']}, {$k['total_clicks']} klik, {$k['avg_ctr']}% CTR", $kw ) );
        $pagesText = implode( "\n", array_map( fn($p) => "- {$p['page_url']}: pos {$p['avg_position']}, {$p['total_clicks']} klik, {$p['avg_ctr']}% CTR", $pages ) );

        $prompt = "Du er SEO-ekspert. Analyser disse Google Search Console data for rezponz.dk og giv 4 konkrete, handlingsrettede anbefalinger på dansk. Vær specifik – nævn konkrete søgeord og sider.\n\nTop søgeord:\n{$kwText}\n\nTop sider:\n{$pagesText}\n\nGiv 4 anbefalinger i dette format:\n1. [Hvad man skal gøre]: [Hvorfor og hvordan – konkret]\n2. ...\n\nSvar KUN med de 4 anbefalinger, ingen intro eller outro.";

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'model'    => 'gpt-4o-mini',
                'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => 600,
            ] ),
        ] );

        if ( is_wp_error( $ai_res ) ) return self::ok( [ 'error' => $ai_res->get_error_message() ] );
        $ai_body = json_decode( wp_remote_retrieve_body( $ai_res ), true );
        $text = $ai_body['choices'][0]['message']['content'] ?? '';
        if ( ! $text ) return self::ok( [ 'error' => $ai_body['error']['message'] ?? 'Ingen svar fra OpenAI' ] );

        // Cache i 24 timer
        set_transient( 'rzpa_seo_ai_analysis', $text, DAY_IN_SECONDS );
        return self::ok( [ 'analysis' => $text ] );
    }

    // AI
    public static function ai_overview( $r ) {
        return self::ok( RZPA_Database::get_ai_overview( self::days( $r ) ) );
    }
    public static function ai_summary( $r ) {
        return self::ok( RZPA_Database::get_ai_summary( self::days( $r ) ) );
    }
    public static function ai_keyword_status() {
        $all_kw   = RZPA_Database::get_ai_keyword_status();
        $opts     = get_option( 'rzpa_settings', [] );

        $configured_kw = array_values( array_filter(
            array_map( 'trim', explode( "\n", $opts['serp_tracked_keywords'] ?? '' ) ),
            fn( $k ) => $k !== ''
        ) );

        // Filtrer til kun aktuelt sporede søgeord – fjerner gamle CRM-nøgleord fra visningen
        if ( ! empty( $configured_kw ) ) {
            $tracked_lower = array_map( 'mb_strtolower', $configured_kw );
            $all_kw = array_values( array_filter(
                $all_kw,
                fn( $row ) => in_array( mb_strtolower( $row['keyword'] ), $tracked_lower, true )
            ) );
        }

        return self::ok( [
            'keywords'    => $all_kw,
            'tracked'     => $configured_kw,
            'has_api_key' => ! empty( $opts['serp_api_key'] ),
        ] );
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
        $opts = get_option( 'rzpa_settings', [] );

        // Brug brugerens egne søgeord fra indstillinger – fald tilbage på Rezponz-defaults
        $raw_kw = $opts['serp_tracked_keywords'] ?? '';
        $keywords = array_values( array_filter(
            array_map( 'trim', explode( "\n", $raw_kw ) ),
            fn( $k ) => $k !== ''
        ) );
        if ( empty( $keywords ) ) {
            $keywords = [ 'rezponz', 'kundeservice software', 'marketing dashboard', 'lead generation', 'crm software' ];
        }

        // Ryd gamle søgeord fra DB der ikke længere er i den aktuelle liste (fx CRM-nøgleord)
        RZPA_Database::purge_old_ai_keywords( $keywords );

        $rows     = [];
        $has_key  = ! empty( $opts['serp_api_key'] );
        $errors   = [];

        foreach ( $keywords as $kw ) {
            if ( $has_key ) {
                $res = wp_remote_get( 'https://serpapi.com/search.json?' . http_build_query( [
                    'q'       => $kw,
                    'api_key' => $opts['serp_api_key'],
                    'gl'      => 'dk',
                    'hl'      => 'da',
                    'num'     => 10,
                ] ), [ 'timeout' => 15 ] );

                if ( ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) === 200 ) {
                    $data = json_decode( wp_remote_retrieve_body( $res ), true );
                    if ( ! empty( $data['error'] ) ) {
                        $err_msg = $data['error'];
                        // "No results" er ikke en API-fejl – gem søgeordet med 0-værdier
                        if ( stripos( $err_msg, 'no results' ) !== false || stripos( $err_msg, "hasn't returned" ) !== false ) {
                            $rows[] = [
                                'date'                 => gmdate( 'Y-m-d' ),
                                'keyword'              => $kw,
                                'has_ai_overview'      => 0,
                                'has_featured_snippet' => 0,
                                'has_paa'              => 0,
                                'ai_overview_text'     => '',
                                'source'               => 'serpapi',
                            ];
                        } else {
                            // Reel API-fejl (ugyldig nøgle, kvote overskredet osv.)
                            $errors[] = $err_msg;
                        }
                        continue;
                    }
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
                } else {
                    $errors[] = is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res );
                }
            }
            // Ingen API-nøgle → mock (spring over hvis der allerede er en API-fejl)
            if ( ! $has_key ) {
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
        }

        if ( $rows ) RZPA_Database::insert_ai_overview_rows( $rows );
        RZPA_Database::log_sync( 'ai_overview', 'success', count( $rows ) . ' keywords' );

        $resp = [ 'count' => count( $rows ), 'has_api_key' => $has_key ];
        if ( $errors ) $resp['errors'] = array_unique( $errors );
        return self::ok( $resp );
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

    public static function meta_invoices( WP_REST_Request $r ) {
        $since = sanitize_text_field( $r->get_param( 'since' ) ?: '' );
        $until = sanitize_text_field( $r->get_param( 'until' ) ?: '' );
        $force = (bool) $r->get_param( 'force' );

        // Cache-nøgle inkluderer datofilter
        $key = 'rzpa_meta_invoices_' . md5( $since . '|' . $until );
        if ( ! $force ) {
            $cached = get_transient( $key );
            if ( $cached !== false ) return self::ok( $cached );
        } else {
            delete_transient( $key );
        }

        $data = RZPA_Meta_Ads::fetch_invoices( $since, $until );
        if ( ! isset( $data['error'] ) ) set_transient( $key, $data, 30 * MINUTE_IN_SECONDS );
        return self::ok( $data );
    }

    public static function meta_ai_copy( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI API-nøgle' ] );

        $body  = $r->get_json_params() ?? [];
        $title = sanitize_text_field( $body['title'] ?? '' );
        $text  = sanitize_textarea_field( $body['body'] ?? '' );
        $cta   = sanitize_text_field( $body['cta'] ?? '' );

        if ( ! $title && ! $text ) return self::ok( [ 'error' => 'Ingen annoncetekst at forbedre' ] );

        $current = trim( ( $title ? "Overskrift: {$title}\n" : '' ) . ( $text ? "Brødtekst: {$text}\n" : '' ) . ( $cta ? "CTA: {$cta}" : '' ) );

        $prompt = "Du er en ekspert i Meta Ads-annoncetekster for B2B-virksomheder i Danmark.\n\n"
            . "Denne annonce er for Rezponz.dk — et dansk B2B-firma der rekrutterer og outsourcer kundeservicemedarbejdere.\n\n"
            . "NUVÆRENDE ANNONCETEKST:\n{$current}\n\n"
            . "Lav 3 forbedrede versioner. Fokusér på:\n"
            . "- Stærk hook i første linje (stop-scroll effekt)\n"
            . "- Konkret benefit fremfor generelle påstande\n"
            . "- Tydelig CTA med urgency\n"
            . "- Maks 150 ord per version\n"
            . "- Tal dansk og direkte til beslutningstagere\n\n"
            . "Format PRÆCIST sådan (brug disse overskrifter):\n"
            . "VERSION 1:\nOverskrift: [tekst]\nBrødtekst: [tekst]\nHvorfor: [1-2 sætninger om hvad der er forbedret]\n\n"
            . "VERSION 2:\nOverskrift: [tekst]\nBrødtekst: [tekst]\nHvorfor: [1-2 sætninger]\n\n"
            . "VERSION 3:\nOverskrift: [tekst]\nBrødtekst: [tekst]\nHvorfor: [1-2 sætninger]";

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'model'      => 'gpt-4o-mini',
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => 800,
            ] ),
        ] );

        if ( is_wp_error( $ai_res ) ) return self::ok( [ 'error' => $ai_res->get_error_message() ] );
        $ai_body = json_decode( wp_remote_retrieve_body( $ai_res ), true );
        $text    = $ai_body['choices'][0]['message']['content'] ?? '';
        if ( ! $text ) return self::ok( [ 'error' => $ai_body['error']['message'] ?? 'Ingen svar fra OpenAI' ] );

        return self::ok( [ 'suggestions' => $text ] );
    }

    public static function meta_ai_analysis( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI API-nøgle — tilføj den i Indstillinger' ] );

        $days      = self::days( $r );
        $summary   = RZPA_Database::get_meta_summary( $days );
        $campaigns = RZPA_Database::get_meta_campaigns( $days );

        if ( empty( $summary['total_spend'] ) || (float) $summary['total_spend'] === 0.0 ) {
            return self::ok( [ 'error' => 'Ingen Meta-data — klik "Hent data" først' ] );
        }

        $spend   = round( (float) ( $summary['total_spend']       ?? 0 ) );
        $clicks  = (int)          ( $summary['total_clicks']       ?? 0 );
        $impr    = (int)          ( $summary['total_impressions']  ?? 0 );
        $ctr     = round( (float) ( $summary['avg_ctr']            ?? 0 ), 2 );
        $cpc     = round( (float) ( $summary['avg_cpc']            ?? 0 ), 2 );

        $active = array_filter( $campaigns, fn($c) => ( $c['status'] ?? '' ) === 'ACTIVE' );
        $campText = implode( "\n", array_map( fn($c) =>
            sprintf( '- %s [%s]: %s kr, %s visninger, %s klik, %.2f%% CTR, %.2f kr/klik',
                $c['campaign_name'], $c['status'],
                number_format( (float) $c['spend'], 0, ',', '.' ),
                number_format( (int) $c['impressions'], 0, ',', '.' ),
                number_format( (int) $c['clicks'], 0, ',', '.' ),
                (float) $c['ctr'],
                (float) $c['cpc']
            ),
            array_slice( $campaigns, 0, 15 )
        ) );

        $prompt = "Du er en erfaren Meta Ads-specialist. Analyser disse Facebook/Instagram annonce-data for Rezponz.dk — en dansk B2B kundeservice-virksomhed der rekrutterer og outsourcer kundeservicemedarbejdere.\n\n"
            . "PERIODE: Seneste {$days} dage\n"
            . "TOTAL: {$spend} kr brugt · {$impr} visninger · {$clicks} klik · {$ctr}% CTR · {$cpc} kr/klik\n"
            . "AKTIVE KAMPAGNER: " . count( $active ) . " ud af " . count( $campaigns ) . "\n\n"
            . "KAMPAGNER:\n{$campText}\n\n"
            . "Giv en struktureret analyse med præcis disse 5 sektioner (brug sektionstitlerne som overskrifter):\n\n"
            . "1. OVERORDNET VURDERING\n"
            . "Vurder performance samlet. Sammenlign CTR og CPC med branchestandarder for B2B (benchmark: CTR >1% er godt, CPC <5 kr er godt for DK B2B). Vær ærlig.\n\n"
            . "2. TOP PRIORITET NU\n"
            . "Ét konkret tiltag der vil have størst effekt med det samme. Vær meget specifik.\n\n"
            . "3. KAMPAGNE-ANBEFALINGER\n"
            . "Gennemgå HVER aktiv kampagne: skal den skaleres, optimeres eller pauseres? Konkrete årsager.\n\n"
            . "4. CONTENT OG KREATIVT\n"
            . "Konkrete forslag til annoncetekst, billedtype og format der virker for B2B rekruttering i Danmark. Nævn specifikke eksempler.\n\n"
            . "5. TEKNISK OPTIMERING\n"
            . "Budget-fordeling, budstrategi, målgrupper og retargeting-setup der vil forbedre resultaterne.\n\n"
            . "Skriv på dansk. Brug præcise tal fra dataene. Max 600 ord total.";

        // Cache i 4 timer baseret på data-hash
        $cache_key = 'rzpa_meta_ai_' . md5( $days . $spend . $clicks . $ctr );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return self::ok( [ 'analysis' => $cached, 'cached' => true ] );

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => 'gpt-4o-mini',
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => 1200,
            ] ),
        ] );

        if ( is_wp_error( $ai_res ) ) return self::ok( [ 'error' => $ai_res->get_error_message() ] );

        $ai_body = json_decode( wp_remote_retrieve_body( $ai_res ), true );
        $text    = $ai_body['choices'][0]['message']['content'] ?? '';
        if ( ! $text ) return self::ok( [ 'error' => $ai_body['error']['message'] ?? 'Ingen svar fra OpenAI' ] );

        set_transient( $cache_key, $text, 4 * HOUR_IN_SECONDS );
        return self::ok( [ 'analysis' => $text ] );
    }

    // ── Google Ads ─────────────────────────────────────────────────────────

    public static function google_ads_sync( WP_REST_Request $r ) {
        $days = self::days( $r );
        $rows = RZPA_Google_Ads::fetch( $days );
        $err  = RZPA_Google_Ads::$last_error;
        if ( $err && empty( $rows ) ) return self::ok( [ 'success' => false, 'error' => $err ] );
        RZPA_Database::insert_google_ads_campaigns( $rows, $days );
        return self::ok( [ 'success' => true, 'count' => count( $rows ) ] );
    }

    public static function google_ads_summary( WP_REST_Request $r ) {
        $s = RZPA_Database::get_google_ads_summary( self::days( $r ) );
        return self::ok( array_merge( $s, [ 'configured' => true ] ) );
    }

    public static function google_ads_campaigns( WP_REST_Request $r ) {
        return self::ok( RZPA_Database::get_google_ads_campaigns( self::days( $r ) ) );
    }

    public static function google_ads_has_data( WP_REST_Request $r ) {
        $days = self::days( $r );
        return self::ok( [ 'has_data' => RZPA_Database::has_google_ads_data( $days ), 'days' => $days ] );
    }

    public static function google_ads_monthly( WP_REST_Request $r ) {
        $months = (int) ( $r->get_param( 'months' ) ?: 6 );
        $key    = 'rzpa_gads_monthly_' . $months;
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $data = RZPA_Google_Ads::fetch_monthly( $months );
        if ( $data ) set_transient( $key, $data, 6 * HOUR_IN_SECONDS );
        return self::ok( $data );
    }

    public static function google_ads_invoices() {
        $key    = 'rzpa_gads_invoices';
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $data = RZPA_Google_Ads::fetch_invoices();
        if ( ! isset( $data['error'] ) ) set_transient( $key, $data, HOUR_IN_SECONDS );
        return self::ok( $data );
    }

    public static function google_ads_test() {
        return self::ok( RZPA_Google_Ads::test_connection() );
    }

    public static function google_ads_ai_analysis( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI API-nøgle — tilføj den i Indstillinger' ] );

        $days      = self::days( $r );
        $summary   = RZPA_Database::get_google_ads_summary( $days );
        $campaigns = RZPA_Database::get_google_ads_campaigns( $days );

        if ( empty( $summary['total_spend'] ) || (float) $summary['total_spend'] === 0.0 ) {
            return self::ok( [ 'error' => 'Ingen Google Ads-data — klik "Hent data" først' ] );
        }

        $spend  = round( (float) ( $summary['total_spend']      ?? 0 ) );
        $clicks = (int)          ( $summary['total_clicks']      ?? 0 );
        $impr   = (int)          ( $summary['total_impressions'] ?? 0 );
        $ctr    = round( (float) ( $summary['avg_ctr']           ?? 0 ), 2 );
        $cpc    = round( (float) ( $summary['avg_cpc']           ?? 0 ), 2 );
        $conv   = round( (float) ( $summary['total_conversions'] ?? 0 ), 1 );

        $campText = implode( "\n", array_map( fn($c) =>
            sprintf( '- %s [%s]: %.0f kr, %d vis, %d klik, %.2f%% CTR, %.2f kr/klik, %.1f konv.',
                $c['campaign_name'], $c['status'], $c['spend'],
                $c['impressions'], $c['clicks'], $c['ctr'], $c['cpc'], $c['conversions'] ?? 0
            ),
            array_slice( $campaigns, 0, 15 )
        ) );

        $prompt = "Du er en certificeret Google Ads-specialist. Analyser disse Google Ads-data for Rezponz.dk — en dansk B2B-virksomhed der rekrutterer og outsourcer kundeservicemedarbejdere.\n\n"
            . "PERIODE: Seneste {$days} dage\n"
            . "TOTAL: {$spend} kr brugt · {$impr} visninger · {$clicks} klik · {$ctr}% CTR · {$cpc} kr/klik · {$conv} konverteringer\n\n"
            . "KAMPAGNER:\n{$campText}\n\n"
            . "Giv en struktureret analyse med præcis disse 5 sektioner:\n\n"
            . "1. OVERORDNET VURDERING\nVurder samlet performance. Benchmarks for B2B Google Ads: CTR >2% søgenetværk, CPC <20 kr, konverteringsrate >3%.\n\n"
            . "2. TOP PRIORITET NU\nÉt konkret tiltag der giver størst effekt straks. Vær meget specifik.\n\n"
            . "3. KAMPAGNE-ANBEFALINGER\nGennemgå HVER aktiv kampagne: skalér, optimér eller pausér? Konkrete årsager med tal.\n\n"
            . "4. SØGEORD & ANNONCER\nForslag til negative søgeord, nye søgeord og bedre annoncetekster for B2B rekruttering.\n\n"
            . "5. TEKNISK OPTIMERING\nBudstrategi, Quality Score, målgrupper, remarketing og konverteringssporing.\n\n"
            . "Skriv på dansk. Brug præcise tal. Max 700 ord.";

        $cache_key = 'rzpa_gads_ai_' . md5( $days . $spend . $clicks . $ctr );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return self::ok( [ 'analysis' => $cached, 'cached' => true ] );

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'model' => 'gpt-4o-mini', 'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ], 'max_tokens' => 1200 ] ),
        ] );
        if ( is_wp_error( $ai_res ) ) return self::ok( [ 'error' => $ai_res->get_error_message() ] );
        $ai_body = json_decode( wp_remote_retrieve_body( $ai_res ), true );
        $text    = $ai_body['choices'][0]['message']['content'] ?? '';
        if ( ! $text ) return self::ok( [ 'error' => $ai_body['error']['message'] ?? 'Ingen svar fra OpenAI' ] );
        set_transient( $cache_key, $text, 4 * HOUR_IN_SECONDS );
        return self::ok( [ 'analysis' => $text ] );
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
        $force  = (bool) $r->get_param( 'force' );
        if ( $force ) delete_transient( $key );
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $ads = RZPA_Meta_Ads::fetch_campaign_ads( $campaign_id );
        // Gem kun hvis det er et array uden fejl
        if ( is_array( $ads ) && ! isset( $ads['__error'] ) && ! empty( $ads ) ) {
            set_transient( $key, $ads, HOUR_IN_SECONDS );
        }
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

    /**
     * Debug: Returnerer rå Meta API-svar for hurtigt at diagnosticere fejl.
     * Kalder /insights?level=ad og /{campaign_id}/ads direkte og viser resultatet.
     */
    public static function meta_debug( WP_REST_Request $r ) {
        $opts       = get_option( 'rzpa_settings', [] );
        $token      = $opts['meta_access_token']   ?? '';
        $account_id = $opts['meta_ad_account_id']  ?? '';

        if ( ! $token || ! $account_id ) {
            return self::ok( [ 'error' => 'Token eller konto-ID mangler i indstillinger' ] );
        }

        $base = 'https://graph.facebook.com/v21.0';
        $out  = [];

        // Test 1: Token-info
        $me_res  = wp_remote_get( $base . '/me?access_token=' . $token . '&fields=id,name', [ 'timeout' => 10 ] );
        $out['token_info'] = is_wp_error( $me_res )
            ? [ 'error' => $me_res->get_error_message() ]
            : json_decode( wp_remote_retrieve_body( $me_res ), true );

        // Test 2: Token permissions
        $perm_res = wp_remote_get( $base . '/me/permissions?access_token=' . $token, [ 'timeout' => 10 ] );
        $perm_body = is_wp_error( $perm_res ) ? [] : json_decode( wp_remote_retrieve_body( $perm_res ), true );
        $out['permissions'] = array_column( $perm_body['data'] ?? [], 'status', 'permission' );

        // Test 3: Adgang til kontoen
        $acc_res  = wp_remote_get( $base . '/act_' . $account_id . '?access_token=' . $token . '&fields=id,name,account_status', [ 'timeout' => 10 ] );
        $out['account'] = is_wp_error( $acc_res )
            ? [ 'error' => $acc_res->get_error_message() ]
            : json_decode( wp_remote_retrieve_body( $acc_res ), true );

        // Test 4: Annoncer med minimal fields
        $ads_url = $base . '/act_' . $account_id . '/ads?' . http_build_query( [
            'access_token' => $token,
            'fields'       => 'id,name,effective_status',
            'limit'        => 5,
        ] );
        $ads_res  = wp_remote_get( $ads_url, [ 'timeout' => 15 ] );
        $out['ads_simple'] = is_wp_error( $ads_res )
            ? [ 'error' => $ads_res->get_error_message() ]
            : json_decode( wp_remote_retrieve_body( $ads_res ), true );

        // Test 5: Insights level=ad
        $ins_url  = $base . '/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token' => $token,
            'level'        => 'ad',
            'date_preset'  => 'last_30_days',
            'fields'       => 'ad_id,ad_name,reach,impressions,spend',
            'limit'        => 5,
        ] );
        $ins_res  = wp_remote_get( $ins_url, [ 'timeout' => 20 ] );
        $out['insights_ad_level'] = is_wp_error( $ins_res )
            ? [ 'error' => $ins_res->get_error_message() ]
            : json_decode( wp_remote_retrieve_body( $ins_res ), true );

        return self::ok( $out );
    }

    public static function meta_top_ads( WP_REST_Request $r ) {
        $days  = self::days( $r );
        $force = (bool) $r->get_param( 'force' );
        $check = (bool) $r->get_param( 'check' ); // Kun tjek om cache findes
        $key   = 'rzpa_meta_top_ads_' . $days;

        // check=1 returnerer hurtigt uden at kalde Meta API
        if ( $check ) {
            $cached = get_transient( $key );
            if ( $cached !== false ) return self::ok( $cached ); // Har cache → returner den
            return self::ok( [ '__no_cache' => true ] );         // Ingen cache → JS viser Hent-prompt
        }

        if ( ! $force ) {
            $cached = get_transient( $key );
            if ( $cached !== false ) return self::ok( $cached );
        } else {
            delete_transient( $key );
        }
        RZPA_Meta_Ads::$last_error = null;
        $data = RZPA_Meta_Ads::fetch_ad_insights( $days );
        $err  = RZPA_Meta_Ads::$last_error;
        if ( $err && empty( $data ) ) {
            return self::ok( [ '__error' => $err ] );
        }
        if ( $data ) set_transient( $key, $data, HOUR_IN_SECONDS );
        return self::ok( $data );
    }

    public static function meta_landing_pages( WP_REST_Request $r ) {
        $force  = (bool) ( $r->get_param( 'force' ) );
        $key    = 'rzpa_meta_landing_pages';
        if ( ! $force ) {
            $cached = get_transient( $key );
            if ( $cached !== false ) return self::ok( $cached );
        }
        $data = RZPA_Meta_Ads::fetch_landing_pages();
        if ( $data ) set_transient( $key, $data, 2 * HOUR_IN_SECONDS );
        return self::ok( $data );
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

    public static function snap_ads( WP_REST_Request $r ) {
        $key    = 'rzpa_snap_ads';
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $data = RZPA_Snapchat_Ads::fetch_ads( self::days( $r ) );
        if ( $data ) set_transient( $key, $data, HOUR_IN_SECONDS );
        return self::ok( $data );
    }

    public static function tiktok_ads( WP_REST_Request $r ) {
        $key    = 'rzpa_tiktok_ads';
        $cached = get_transient( $key );
        if ( $cached !== false ) return self::ok( $cached );
        $data = RZPA_TikTok_Ads::fetch_ads( self::days( $r ) );
        if ( $data ) set_transient( $key, $data, HOUR_IN_SECONDS );
        return self::ok( $data );
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

    /**
     * Meta image proxy — henter Meta CDN-billeder server-side med access token.
     * Løser problemet med at thumbnail_url/image_url kræver Facebook-auth.
     */
    public static function meta_image_proxy( WP_REST_Request $r ) {
        $raw_url = $r->get_param( 'url' ) ?? '';
        $url     = filter_var( $raw_url, FILTER_SANITIZE_URL );

        // Kun Meta/Facebook CDN-urls tilladt
        if ( ! $url || ! preg_match( '#^https?://([\w-]+\.)?(facebook\.com|fbcdn\.net|scontent\.[a-z0-9-]+\.fna\.fbcdn\.net|cdninstagram\.com)#', $url ) ) {
            return new WP_Error( 'invalid_url', 'Only Meta CDN URLs allowed', [ 'status' => 400 ] );
        }

        $cache_key = 'rzpa_img_' . md5( $url );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            header( 'Content-Type: ' . sanitize_mime_type( $cached['ct'] ) );
            header( 'Cache-Control: public, max-age=3600' );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo base64_decode( $cached['data'] );
            exit;
        }

        $opts  = get_option( 'rzpa_settings', [] );
        $token = $opts['meta_access_token'] ?? '';
        $img_url = $url;
        if ( $token && strpos( $url, 'access_token' ) === false ) {
            $img_url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . 'access_token=' . rawurlencode( $token );
        }

        $res = wp_remote_get( $img_url, [ 'timeout' => 15, 'redirection' => 5 ] );
        if ( is_wp_error( $res ) ) {
            return new WP_Error( 'fetch_error', 'Image unavailable', [ 'status' => 502 ] );
        }

        $body = wp_remote_retrieve_body( $res );
        $ct   = wp_remote_retrieve_header( $res, 'content-type' ) ?: 'image/jpeg';
        // Strip charset suffix
        $ct = preg_replace( '/;.*/', '', $ct );

        if ( strlen( $body ) > 100 ) {
            set_transient( $cache_key, [ 'ct' => $ct, 'data' => base64_encode( $body ) ], HOUR_IN_SECONDS );
        }

        header( 'Content-Type: ' . sanitize_mime_type( $ct ) );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'Content-Length: ' . strlen( $body ) );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $body;
        exit;
    }

    /**
     * Google Ads – alle aktive annoncer (RSA / text ads).
     */
    public static function google_ads_ads( WP_REST_Request $r ) {
        $cache_key = 'rzpa_gads_ads';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return self::ok( $cached );

        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) ) {
            return self::ok( [] );
        }

        $data = RZPA_Google_Ads::fetch_ads();
        if ( $data ) set_transient( $cache_key, $data, HOUR_IN_SECONDS );
        return self::ok( $data );
    }

    /**
     * SEO keyword suggestions — AI-genererede søgeords-anbefalinger.
     * Bruger eksisterende GSC-data + OpenAI til at finde 30 vigtige søgeord.
     */
    public static function seo_keyword_suggestions( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI nøgle — tilføj den i Indstillinger' ] );

        $force  = (bool) ( $r->get_json_params()['force'] ?? false );
        $ck     = 'rzpa_seo_kw_suggestions';
        $cached = get_transient( $ck );
        if ( $cached !== false && ! $force ) return self::ok( [ 'keywords' => $cached, 'cached' => true ] );

        // Nuværende GSC-søgeord som kontekst
        $existing_kw = RZPA_Database::get_top_keywords( 30, 30 );
        $existing    = implode( ', ', array_column( $existing_kw ?? [], 'keyword' ) );

        $prompt = "Du er en erfaren dansk SEO-ekspert. Rezponz A/S i Aalborg er et professionelt kundeservicecenter og callcenter — de udfører udliciteret kundeservice og telemarketing for andre virksomheder i Danmark.

De ranker allerede på: {$existing}

Lav en liste med præcis 30 ANDRE søgeord/sætninger som Rezponz bør forsøge at rangere på Google for. Prioritér:
- Sætninger folk bruger når de søger outsourcing af kundeservice
- Brancherelaterede termer (callcenter, telemarketing, kundeservice, BPO)
- Geografiske kombinationer (Aalborg, Nordjylland, Danmark)
- Jobsøgere (kundeservice job, callcenter medarbejder)

Svar KUN med et JSON-array — ingen tekst rundt om. Hvert element skal have præcis disse felter:
{\"keyword\": \"sætningen her\", \"monthly_searches\": \"Lav|Medium|Høj\", \"difficulty\": \"Lav|Medium|Høj\", \"intent\": \"kommerciel|informativ|lokal|rekruttering\", \"priority\": 1, \"action\": \"Kort konkret anbefaling (max 80 tegn)\"}";

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'model'       => 'gpt-4o-mini',
                'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens'  => 2500,
            ] ),
        ] );

        if ( is_wp_error( $ai_res ) ) return self::ok( [ 'error' => $ai_res->get_error_message() ] );
        $ai_body = json_decode( wp_remote_retrieve_body( $ai_res ), true );
        $text    = $ai_body['choices'][0]['message']['content'] ?? '';
        if ( ! $text ) return self::ok( [ 'error' => $ai_body['error']['message'] ?? 'Ingen svar fra OpenAI' ] );

        // Udtræk JSON-array fra svaret
        preg_match( '/\[[\s\S]+\]/u', $text, $matches );
        $keywords = json_decode( $matches[0] ?? '[]', true );
        if ( ! is_array( $keywords ) || empty( $keywords ) ) {
            return self::ok( [ 'error' => 'Kunne ikke parse søgeords-data fra AI' ] );
        }

        // Sortér efter priority
        usort( $keywords, fn( $a, $b ) => ( $b['priority'] ?? 5 ) <=> ( $a['priority'] ?? 5 ) );

        // Hent aktuelle GSC-placeringer og tilknyt til hvert søgeord
        $gsc_raw      = RZPA_Database::get_top_keywords( 90, 500 );
        $position_map = [];
        foreach ( $gsc_raw as $row ) {
            $k = strtolower( trim( $row['keyword'] ?? '' ) );
            if ( $k ) $position_map[ $k ] = round( (float) ( $row['avg_position'] ?? $row['position'] ?? 0 ), 1 );
        }
        foreach ( $keywords as &$kw_item ) {
            $lookup = strtolower( trim( $kw_item['keyword'] ?? '' ) );
            // 1) Exact match
            if ( isset( $position_map[ $lookup ] ) ) {
                $kw_item['current_position'] = $position_map[ $lookup ];
                continue;
            }
            // 2) Fuzzy: find GSC keyword that contains all words from suggestion (or vice versa)
            $words  = array_filter( explode( ' ', $lookup ) );
            $best   = null;
            foreach ( $position_map as $gsc_kw => $pos ) {
                // All words of the suggestion appear in the GSC keyword
                $all_match = true;
                foreach ( $words as $w ) {
                    if ( strpos( $gsc_kw, $w ) === false ) { $all_match = false; break; }
                }
                if ( $all_match ) { $best = $pos; break; }
                // Or: GSC keyword is a substring of the suggestion
                if ( strpos( $lookup, $gsc_kw ) !== false ) { $best = $pos; break; }
            }
            $kw_item['current_position'] = $best;
        }
        unset( $kw_item );

        set_transient( $ck, $keywords, 12 * HOUR_IN_SECONDS );
        return self::ok( [ 'keywords' => $keywords ] );
    }

    /**
     * AI budget-anbefalinger baseret på performance på tværs af kanaler.
     */
    public static function ads_budget_recommendations( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI nøgle — tilføj den i Indstillinger' ] );

        $force  = (bool) ( $r->get_json_params()['force'] ?? false );
        $ck     = 'rzpa_budget_recs';
        $cached = get_transient( $ck );
        if ( $cached !== false && ! $force ) return self::ok( [ 'analysis' => $cached, 'cached' => true ] );

        $days   = 30;
        $meta   = RZPA_Database::get_meta_summary( $days );
        $gads   = method_exists( 'RZPA_Database', 'get_google_ads_summary' ) ? RZPA_Database::get_google_ads_summary( $days ) : [];
        $snap   = RZPA_Database::get_snap_summary( $days );
        $tiktok = RZPA_Database::get_tiktok_summary( $days );

        $lines = [];
        if ( ! empty( $meta['spend'] ) && $meta['spend'] > 0 ) {
            $cpc = $meta['clicks'] > 0 ? round( $meta['spend'] / $meta['clicks'], 2 ) : 0;
            $ctr = $meta['impressions'] > 0 ? round( $meta['clicks'] / $meta['impressions'] * 100, 2 ) : 0;
            $lines[] = "Meta (Facebook/Instagram): {$meta['spend']} kr brugt · {$meta['clicks']} klik · CPC {$cpc} kr · CTR {$ctr}%";
        }
        if ( ! empty( $gads['spend'] ) && $gads['spend'] > 0 ) {
            $cpc = $gads['clicks'] > 0 ? round( $gads['spend'] / $gads['clicks'], 2 ) : 0;
            $lines[] = "Google Ads: {$gads['spend']} kr brugt · {$gads['clicks']} klik · CPC {$cpc} kr";
        }
        if ( ! empty( $snap['spend'] ) && $snap['spend'] > 0 ) {
            $lines[] = "Snapchat: {$snap['spend']} kr brugt · {$snap['clicks']} klik";
        }
        if ( ! empty( $tiktok['spend'] ) && $tiktok['spend'] > 0 ) {
            $lines[] = "TikTok: {$tiktok['spend']} kr brugt · {$tiktok['clicks']} klik";
        }

        if ( empty( $lines ) ) return self::ok( [ 'error' => 'Ingen annoncedata tilgængelig endnu — synkronisér dine platforme først' ] );

        $data_text = implode( "\n", $lines );
        $prompt    = "Du er en senior dansk digital marketing-strateg. Analyser dette annonceforbrug de seneste 30 dage for Rezponz A/S (B2B kundeservice bureau i Aalborg):\n\n{$data_text}\n\nGiv 4 konkrete, handlingsrettede anbefalinger på dansk:\n1. Hvilken kanal performer bedst og bør skaleres\n2. Hvad der bør optimeres eller pauseres\n3. Konkret anbefalet budget-fordeling i procent\n4. Én specifik ting du skal gøre i dag\n\nVær direkte, brug tal, og svar på dansk. Max 300 ord.";

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'model'      => 'gpt-4o-mini',
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => 600,
            ] ),
        ] );

        if ( is_wp_error( $ai_res ) ) return self::ok( [ 'error' => $ai_res->get_error_message() ] );
        $ai_body = json_decode( wp_remote_retrieve_body( $ai_res ), true );
        $text    = $ai_body['choices'][0]['message']['content'] ?? '';
        if ( ! $text ) return self::ok( [ 'error' => $ai_body['error']['message'] ?? 'Ingen svar fra OpenAI' ] );

        set_transient( $ck, $text, 4 * HOUR_IN_SECONDS );
        return self::ok( [ 'analysis' => $text ] );
    }
}
