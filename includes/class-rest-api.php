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

        // PDF-proxy: henter Meta faktura-PDF server-side og streamer til browser
        register_rest_route( self::NS, '/meta/invoice-pdf', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'meta_invoice_pdf' ],
            'permission_callback' => $cap,
        ] );

        // ── Rekruttering ─────────────────────────────────────────────────────
        register_rest_route( self::NS, '/rekruttering/stats', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rekruttering_stats' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/rekruttering/pipeline', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'rekruttering_pipeline_save' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/rekruttering/generate-job-ad', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'rekruttering_generate_job_ad' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/rekruttering/ai-report', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rekruttering_ai_report' ],
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

        // Snap AI analysis
        register_rest_route( self::NS, '/snap/ai-analysis', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'snap_ai_analysis' ],
            'permission_callback' => $cap,
        ] );

        // TikTok ads (ad-level)
        register_rest_route( self::NS, '/tiktok/ads', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'tiktok_ads' ],
            'permission_callback' => $cap,
        ] );

        // TikTok AI analysis
        register_rest_route( self::NS, '/tiktok/ai-analysis', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'tiktok_ai_analysis' ],
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

        // Blog Indsigt
        register_rest_route( self::NS, '/blog/insights', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'blog_insights' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/blog/ai-suggestions', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'blog_ai_suggestions' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/blog/request-indexing', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'blog_request_indexing' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/ai/fix-action', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ai_fix_action' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( self::NS, '/ai/fix-post', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ai_fix_post' ],
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

    public static function blog_insights( WP_REST_Request $r ) {
        $days = (int) ( $r->get_param( 'days' ) ?? 30 );
        $days = in_array( $days, [ 7, 30, 90 ], true ) ? $days : 30;
        return self::ok( RZPA_Database::get_blog_insights( $days ) );
    }

    /**
     * AI Blog Strategi: Analysér hvilke blogindlæg Rezponz bør skrive for at
     * ranke på jobrelevante søgeord. Bruger OpenAI gpt-4.1-mini.
     */
    public static function blog_request_indexing( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_client_id'] ) || empty( $opts['google_refresh_token'] ) ) {
            return new WP_Error( 'no_gsc', 'Google er ikke forbundet under Indstillinger.', [ 'status' => 400 ] );
        }

        $url = sanitize_url( $r->get_json_params()['url'] ?? '' );
        if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'bad_url', 'Ugyldig URL.', [ 'status' => 400 ] );
        }

        // Hent frisk access token
        $token_res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $opts['google_client_id'],
                'client_secret' => $opts['google_client_secret'] ?? '',
                'refresh_token' => $opts['google_refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 15,
        ] );
        if ( is_wp_error( $token_res ) ) {
            return new WP_Error( 'token_error', $token_res->get_error_message(), [ 'status' => 502 ] );
        }
        $token_body = json_decode( wp_remote_retrieve_body( $token_res ), true );
        $access_token = $token_body['access_token'] ?? '';
        if ( ! $access_token ) {
            $err = $token_body['error_description'] ?? ( $token_body['error'] ?? 'Kunne ikke hente access token' );
            // Hvis scope mangler, giv klar besked
            if ( str_contains( $err, 'scope' ) || str_contains( $err, 'insufficient' ) ) {
                return new WP_Error( 'scope_missing', 'Genopret Google-forbindelsen under Indstillinger (kræver opdateret tilladelse).', [ 'status' => 403 ] );
            }
            return new WP_Error( 'token_error', $err, [ 'status' => 502 ] );
        }

        // Kald Google Indexing API
        $index_res = wp_remote_post( 'https://indexing.googleapis.com/v3/urlNotifications:publish', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [ 'url' => $url, 'type' => 'URL_UPDATED' ] ),
            'timeout' => 20,
        ] );
        if ( is_wp_error( $index_res ) ) {
            return new WP_Error( 'indexing_error', $index_res->get_error_message(), [ 'status' => 502 ] );
        }
        $code = wp_remote_retrieve_response_code( $index_res );
        $body = json_decode( wp_remote_retrieve_body( $index_res ), true );

        if ( $code === 200 ) {
            return self::ok( [ 'queued' => true, 'url' => $url ] );
        }

        // Scope-fejl fra Indexing API
        if ( $code === 403 ) {
            return new WP_Error( 'scope_missing', 'Genopret Google-forbindelsen under Indstillinger (kræver opdateret tilladelse til Indexing API).', [ 'status' => 403 ] );
        }

        $err_msg = $body['error']['message'] ?? ( 'API fejl ' . $code );
        return new WP_Error( 'indexing_error', $err_msg, [ 'status' => $code ?: 502 ] );
    }

    // ── POST /ai/fix-action ───────────────────────────────────────────────────
    // Handlingsplan-niveau: finder eksisterende post for søgeord og opdaterer den,
    // eller opretter ny (kun faq_pages opretter altid ny).
    public static function ai_fix_action( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_openai', 'Tilføj en OpenAI API-nøgle under Indstillinger for at bruge auto-fix.', [ 'status' => 400 ] );
        }

        $params   = $r->get_json_params();
        $type     = sanitize_text_field( $params['type'] ?? '' );
        $keywords = array_filter( array_map( 'sanitize_text_field', (array) ( $params['keywords'] ?? [] ) ) );

        if ( ! $type ) {
            return new WP_Error( 'missing_type', 'Mangler type.', [ 'status' => 400 ] );
        }

        $top     = array_slice( $keywords, 0, 5 );
        $kw_list = implode( ', ', $top );

        // featured_snippet + paa_sections: opdatér eksisterende post hvis muligt
        if ( in_array( $type, [ 'featured_snippet', 'paa_sections' ], true ) ) {
            $post_id = self::find_post_for_keyword( $top[0] ?? '' );
            if ( $post_id ) {
                $fix_map = [
                    'featured_snippet' => 'fix_snippet',
                    'paa_sections'     => 'fix_paa',
                ];
                return self::apply_ai_fix_to_post( $post_id, $fix_map[ $type ], $top[0], $opts['openai_api_key'] );
            }
            // Ingen eksisterende post – fald igennem og opret ny
        }

        // faq_pages (og fallback): opret ny FAQ-post med AI-skrevet indhold
        $prompt = <<<PROMPT
Du er SEO-skribent for Rezponz – et dansk firma der tilbyder kundeservice outsourcing, medarbejderrekruttering og vikarer til virksomheder.

Skriv en komplet FAQ-artikel på dansk der svarer autoritativt på søgninger om: {$kw_list}

Regler:
- Brug <h1> til den overordnede sidetitel
- Brug <h2> til hvert spørgsmål (formulér som et konkret spørgsmål)
- Skriv svar i <p>-tags: præcist, faktabaseret, 50-80 ord pr. svar – ingen indledning, gå direkte til sagen
- Afslut med en kort <p> call-to-action der nævner Rezponz
- Skriv KUN HTML (h1, h2, p). Ingen markdown, ingen forklaringer, ingen kommentarer.
PROMPT;

        $html = self::openai_generate( $prompt, $opts['openai_api_key'], 3000 );
        if ( is_wp_error( $html ) ) return $html;

        $html  = self::strip_md_fences( $html );
        $title = 'FAQ: ' . implode( ' – ', array_slice( $top, 0, 3 ) );
        if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $m ) ) {
            $title = wp_strip_all_tags( $m[1] );
            $html  = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $html, 1 );
        }
        $html .= self::build_faq_schema( $html );

        $pid = wp_insert_post( [ 'post_title' => $title, 'post_content' => $html, 'post_status' => 'draft', 'post_type' => 'post' ] );
        if ( is_wp_error( $pid ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $pid->get_error_message() ], 500 );

        return new WP_REST_Response( [ 'ok' => true, 'post_id' => $pid, 'title' => $title, 'edit_url' => admin_url( "post.php?post={$pid}&action=edit" ), 'created' => true ], 200 );
    }

    // ── POST /ai/fix-post ─────────────────────────────────────────────────────
    // Opdaterer et specifikt WP-indlæg baseret på problemtype.
    public static function ai_fix_post( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_openai', 'Tilføj en OpenAI API-nøgle under Indstillinger for at bruge auto-fix.', [ 'status' => 400 ] );
        }

        $params   = $r->get_json_params();
        $post_id  = (int) ( $params['post_id'] ?? 0 );
        $fix_type = sanitize_text_field( $params['fix_type'] ?? '' );
        $keyword  = sanitize_text_field( $params['keyword'] ?? '' );

        if ( ! $post_id || ! $fix_type ) {
            return new WP_Error( 'missing_params', 'Mangler post_id eller fix_type.', [ 'status' => 400 ] );
        }

        $allowed_fix_types = [ 'fix_ctr', 'fix_ai_vis', 'fix_snippet', 'fix_paa', 'fix_content', 'fix_rewrite' ];
        if ( ! in_array( $fix_type, $allowed_fix_types, true ) ) {
            return new WP_Error( 'invalid_fix_type', 'Ugyldig fix_type.', [ 'status' => 400 ] );
        }

        return self::apply_ai_fix_to_post( $post_id, $fix_type, $keyword, $opts['openai_api_key'] );
    }

    // ── Hjælper: find WP-post der matcher et søgeord ──────────────────────────
    private static function find_post_for_keyword( string $keyword ): int {
        if ( ! $keyword ) return 0;
        $posts = get_posts( [
            's'           => $keyword,
            'post_status' => 'publish',
            'post_type'   => 'post',
            'numberposts' => 1,
        ] );
        return ! empty( $posts ) ? (int) $posts[0]->ID : 0;
    }

    // ── Hjælper: anvend AI-fix på specifik post ───────────────────────────────
    private static function apply_ai_fix_to_post( int $post_id, string $fix_type, string $keyword, string $api_key ): WP_REST_Response {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Indlæg ikke fundet.' ], 404 );
        }

        $title   = $post->post_title;
        $content = wp_strip_all_tags( $post->post_content );
        $excerpt = mb_substr( $content, 0, 1500 );

        // Fix 7: Brug Yoast focus keyword hvis tilgængeligt – ellers post title
        $focus_kw = sanitize_text_field( get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
        if ( ! $keyword && $focus_kw ) {
            $keyword = $focus_kw;
        } elseif ( ! $keyword ) {
            $keyword = $title;
        }

        // Tracking – udfyldes i hvert case og sendes tilbage til JS
        $changes   = [];
        $new_title = '';
        $new_meta  = '';

        // ① Gem revision FØR vi ændrer noget — kun hvis revisioner er slået til
        if ( wp_revisions_enabled( $post ) && current_user_can( 'edit_post', $post_id ) ) {
            wp_save_post_revision( $post_id );
        } elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Du har ikke rettigheder til at redigere dette indlæg.' ], 403 );
        }

        switch ( $fix_type ) {
            case 'fix_ctr':
                // Generer bedre title + meta description
                $prompt = <<<PROMPT
Du er SEO-specialist for Rezponz – et dansk kundeservice outsourcing firma.

Indlæg: "{$title}"
Primært søgeord: {$keyword}
Nuværende indhold (uddrag): {$excerpt}

Opgave: Skriv et optimeret SEO title tag og en meta description der øger CTR i Google.

Krav:
- Title: max 60 tegn, søgeordet tidligt, brug tal eller power words hvis relevant
- Meta: 140-155 tegn, klar CTA, søgeordet inkluderet
- Svar i dette JSON-format (ingen anden tekst):
{"title": "...", "meta": "..."}
PROMPT;
                $json = self::openai_generate( $prompt, $api_key, 300 );
                if ( is_wp_error( $json ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $json->get_error_message() ], 500 );

                $data = json_decode( self::strip_md_fences( $json ), true );
                if ( ! empty( $data['title'] ) ) {
                    $new_title = sanitize_text_field( $data['title'] );
                    wp_update_post( [ 'ID' => $post_id, 'post_title' => $new_title ] );
                    $changes[] = 'Titel opdateret';
                }
                if ( ! empty( $data['meta'] ) ) {
                    $new_meta = sanitize_text_field( $data['meta'] );
                    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $new_meta );
                    update_post_meta( $post_id, '_seopress_titles_desc', $new_meta );
                    $changes[] = 'Meta description opdateret (' . mb_strlen( $new_meta ) . ' tegn)';
                }
                // Fix 10: Returner fejl hvis AI ikke leverede noget brugbart
                if ( empty( $changes ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI returnerede ingen gyldige felter. Prøv igen.' ], 500 );
                }
                $label = 'Titel og meta opdateret';
                break;

            case 'fix_ai_vis':
                // Tilføj FAQ-sektion med schema for AI-synlighed
                $prompt = <<<PROMPT
Du er SEO-skribent for Rezponz – et dansk firma der tilbyder kundeservice outsourcing og rekruttering.

Eksisterende blogindlæg: "{$title}"
Søgeord: {$keyword}
Indhold (uddrag): {$excerpt}

Opgave: Skriv en FAQ-sektion der tilføjes nederst i indlægget for at øge AI-synlighed.

Krav:
- 4-5 H2-spørgsmål der er naturlige opfølgningsspørgsmål til indlæggets emne
- Svar på 45-65 ord pr. spørgsmål – direkte og faktabaseret
- Skriv KUN HTML (h2, p). Ingen markdown, ingen forklaringer.
PROMPT;
                $faq_html = self::openai_generate( $prompt, $api_key, 900 );
                if ( is_wp_error( $faq_html ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $faq_html->get_error_message() ], 500 );

                $faq_html = wp_kses_post( self::strip_md_fences( $faq_html ) );
                // Duplicate guard: tilføj ikke FAQ igen hvis den allerede er der
                if ( strpos( $post->post_content, '<!-- rzpa-faq -->' ) !== false ) {
                    $label   = 'FAQ allerede tilføjet';
                    $changes = [ 'FAQ-sektion er allerede i indlægget – ingen ændringer' ];
                    break;
                }
                $faq_count   = substr_count( $faq_html, '<h2' );
                $faq_html   .= self::build_faq_schema( $faq_html );
                $new_content = $post->post_content . "\n\n<!-- rzpa-faq -->\n<h2>Ofte stillede spørgsmål</h2>\n" . $faq_html;
                $result      = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ], true );
                if ( is_wp_error( $result ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
                $changes = [
                    "FAQ-sektion tilføjet ({$faq_count} spørgsmål og svar)",
                    'FAQ Schema markup (JSON-LD) tilføjet – øger AI-synlighed',
                ];
                $label = 'FAQ-sektion tilføjet';
                break;

            case 'fix_snippet':
                // Tilføj/omskriv intro-afsnit til Featured Snippet-format
                $prompt = <<<PROMPT
Du er SEO-skribent for Rezponz – et dansk firma der tilbyder kundeservice outsourcing og rekruttering.

Eksisterende blogindlæg: "{$title}"
Søgeord: {$keyword}
Indhold (uddrag): {$excerpt}

Opgave: Skriv et nyt indledningsafsnit optimeret til Featured Snippet.

Krav:
- Start med en <h2> der er søgeordets primære spørgsmål ("Hvad er {$keyword}?")
- Direkte svar i <p> på præcis 45-55 ord – ingen indledning, gå direkte til svaret
- Skriv KUN HTML (h2, p). Ingen markdown.
PROMPT;
                $snippet_html = self::openai_generate( $prompt, $api_key, 400 );
                if ( is_wp_error( $snippet_html ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $snippet_html->get_error_message() ], 500 );

                $snippet_html = wp_kses_post( self::strip_md_fences( $snippet_html ) );
                // Fix 2: Guard mod tom AI-svar
                if ( empty( trim( $snippet_html ) ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI returnerede intet indhold. Prøv igen.' ], 500 );
                }
                // Fix 3: Duplicate guard
                if ( strpos( $post->post_content, '<!-- rzpa-snippet -->' ) !== false ) {
                    $label   = 'Snippet allerede tilføjet';
                    $changes = [ 'Snippet-intro er allerede i indlægget – ingen ændringer' ];
                    break;
                }
                $new_content = "<!-- rzpa-snippet -->\n" . $snippet_html . "\n\n" . $post->post_content;
                $result      = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ], true );
                if ( is_wp_error( $result ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
                $changes = [
                    'Direkte svar-afsnit tilføjet øverst (45-55 ord)',
                    'Optimeret til Google Featured Snippet-format (H2 + P)',
                ];
                $label = 'Snippet-sektion tilføjet';
                break;

            case 'fix_paa':
                // Tilføj Q&A-sektion til "Folk spørger også"
                $prompt = <<<PROMPT
Du er SEO-skribent for Rezponz – et dansk firma der tilbyder kundeservice outsourcing og rekruttering.

Eksisterende blogindlæg: "{$title}"
Søgeord: {$keyword}
Indhold (uddrag): {$excerpt}

Opgave: Skriv 5 "Folk spørger også"-spørgsmål og svar der tilføjes til indlægget.

Krav:
- H2 med naturlige brugerspørgsmål folk stiller om dette emne
- Svar i <p> på 35-50 ord – direkte og præcist
- Skriv KUN HTML (h2, p). Ingen markdown.
PROMPT;
                $paa_html = self::openai_generate( $prompt, $api_key, 900 );
                if ( is_wp_error( $paa_html ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $paa_html->get_error_message() ], 500 );

                $paa_html = wp_kses_post( self::strip_md_fences( $paa_html ) );
                // Duplicate guard
                if ( strpos( $post->post_content, '<!-- rzpa-paa -->' ) !== false ) {
                    $label   = 'Q&A allerede tilføjet';
                    $changes = [ 'Q&A-sektion er allerede i indlægget – ingen ændringer' ];
                    break;
                }
                $paa_count   = substr_count( $paa_html, '<h2' );
                $paa_html   .= self::build_faq_schema( $paa_html );
                $new_content = $post->post_content . "\n\n<!-- rzpa-paa -->\n" . $paa_html;
                $result      = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ], true );
                if ( is_wp_error( $result ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
                $changes = [
                    "\"Folk spørger også\"-sektion tilføjet ({$paa_count} spørgsmål)",
                    'FAQ Schema markup (JSON-LD) tilføjet',
                ];
                $label = 'Q&A-sektion tilføjet';
                break;

            case 'fix_content':
                // Udvid og forbedre eksisterende indhold (brug op til 4000 tegn for fuld kontekst)
                $excerpt = mb_substr( $content, 0, 4000 );
                $prompt = <<<PROMPT
Du er SEO-skribent for Rezponz – et dansk firma der tilbyder kundeservice outsourcing og rekruttering.

Eksisterende blogindlæg: "{$title}"
Søgeord: {$keyword}
Nuværende indhold: {$excerpt}

Opgave: Genskriv og udvid dette indlæg til mindst 800 ord. Bevar tonen og emnet.

Krav:
- <h1> med optimeret titel (søgeord tidligt)
- Inddel med logiske <h2>-sektioner
- Mindst 800 ord med værdifuldt, faktabaseret indhold
- Afslut med en FAQ-sektion (3 spørgsmål) og call-to-action om Rezponz
- Skriv KUN HTML. Ingen markdown.
PROMPT;
                $new_html = self::openai_generate( $prompt, $api_key, 1800 );
                if ( is_wp_error( $new_html ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $new_html->get_error_message() ], 500 );

                $new_html = wp_kses_post( self::strip_md_fences( $new_html ) );
                // Fix 2: Guard mod tom AI-svar
                if ( empty( trim( $new_html ) ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI returnerede intet indhold. Prøv igen.' ], 500 );
                }
                if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $new_html, $m ) ) {
                    $new_title = wp_strip_all_tags( $m[1] );
                    wp_update_post( [ 'ID' => $post_id, 'post_title' => $new_title ] );
                    $new_html = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $new_html, 1 );
                }
                // Fix 4: Scop FAQPage schema kun til <!-- rzpa-faq --> blokken, ikke hele HTML
                $new_html  = preg_replace( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is', '', $new_html );
                $new_html .= "\n\n<!-- rzpa-faq -->\n" . self::build_faq_schema_from_section( $new_html );
                $result     = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_html ], true );
                if ( is_wp_error( $result ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
                $word_count = str_word_count( wp_strip_all_tags( $new_html ) );
                $changes    = array_filter( [
                    $new_title ? "Titel opdateret: \"{$new_title}\"" : '',
                    "Indhold udvidet til ~{$word_count} ord med H2-sektioner",
                    'FAQ-sektion (3 spørgsmål) og CTA tilføjet',
                    'FAQ Schema markup (JSON-LD) tilføjet',
                ] );
                $label = 'Indhold forbedret og udvidet';
                break;

            case 'fix_rewrite':
                // Komplet omskrivning (brug op til 4000 tegn for fuld kontekst)
                $excerpt = mb_substr( $content, 0, 4000 );
                // Fix 6: Send eksisterende indhold med som kontekst (tidligere manglede dette)
                $prompt = <<<PROMPT
Du er SEO-skribent for Rezponz – et dansk firma der tilbyder kundeservice outsourcing og rekruttering.

Omskriv dette blogindlæg komplet til en stærk SEO-artikel på mindst 1.000 ord.
Titel: "{$title}"
Søgeord: {$keyword}
Eksisterende indhold (bevar fakta og kernebudskab): {$excerpt}

Krav:
- <h1> med kraftfuld, søgeordsoptimeret titel
- 5-7 <h2>-sektioner med substans
- Fakta, fordele, og praktisk vejledning
- Afslut med en FAQ-sektion med præcis 5 spørgsmål markeret med kommentaren <!-- rzpa-faq-start --> OVER og <!-- rzpa-faq-end --> UNDER FAQ-afsnittet
- Tydelig call-to-action til Rezponz til sidst
- Skriv KUN HTML. Ingen markdown.
PROMPT;
                $rewrite = self::openai_generate( $prompt, $api_key, 2500 );
                if ( is_wp_error( $rewrite ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $rewrite->get_error_message() ], 500 );

                $rewrite = wp_kses_post( self::strip_md_fences( $rewrite ) );
                // Fix 2: Guard mod tom AI-svar
                if ( empty( trim( $rewrite ) ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI returnerede intet indhold. Prøv igen.' ], 500 );
                }
                if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $rewrite, $m ) ) {
                    $new_title = wp_strip_all_tags( $m[1] );
                    wp_update_post( [ 'ID' => $post_id, 'post_title' => $new_title ] );
                    $rewrite = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $rewrite, 1 );
                }
                // Fix 4: Fjern evt. eksisterende schema, tilføj nyt scopet til FAQ-blokken
                $rewrite = preg_replace( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is', '', $rewrite );
                $rewrite .= "\n\n" . self::build_faq_schema_from_section( $rewrite );
                $result   = wp_update_post( [ 'ID' => $post_id, 'post_content' => $rewrite ], true );
                if ( is_wp_error( $result ) ) return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
                $word_count = str_word_count( wp_strip_all_tags( $rewrite ) );
                $changes    = array_filter( [
                    $new_title ? "Ny titel: \"{$new_title}\"" : '',
                    "Indlæg komplet omskrevet (~{$word_count} ord)",
                    '5-7 H2-sektioner med fakta og praktisk vejledning',
                    'FAQ-sektion (5 spørgsmål) og CTA til Rezponz',
                    'FAQ Schema markup (JSON-LD) tilføjet',
                ] );
                $label = 'Indlæg omskrevet';
                break;

            default:
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Ukendt fix_type: ' . $fix_type ], 400 );
        }

        return new WP_REST_Response( [
            'ok'       => true,
            'post_id'   => $post_id,
            'label'     => $label,
            'edit_url'  => admin_url( "post.php?post={$post_id}&action=edit" ),
            'changes'   => array_values( $changes ),
            'new_title' => $new_title,
            'new_meta'  => $new_meta,
        ], 200 );
    }

    // ── Hjælpere ──────────────────────────────────────────────────────────────

    private static function openai_generate( string $prompt, string $api_key, int $max_tokens = 2000 ): string|\WP_Error {
        if ( function_exists( 'set_time_limit' ) ) set_time_limit( 120 ); // Forhindrer PHP timeout ved lange AI-svar
        $res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'model' => 'gpt-4.1-mini', 'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ], 'max_tokens' => $max_tokens, 'temperature' => 0.5 ] ),
            'timeout' => 90,
        ] );
        if ( is_wp_error( $res ) ) return $res;
        $code = wp_remote_retrieve_response_code( $res );
        if ( $code !== 200 ) {
            $err = json_decode( wp_remote_retrieve_body( $res ), true );
            return new WP_Error( 'openai_http', $err['error']['message'] ?? 'OpenAI fejl ' . $code );
        }
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        return trim( $body['choices'][0]['message']['content'] ?? '' );
    }

    private static function strip_md_fences( string $text ): string {
        $text = preg_replace( '/^```(?:html|json)?\s*/i', '', trim( $text ) );
        return preg_replace( '/\s*```$/', '', $text );
    }

    private static function build_faq_schema( string $html ): string {
        $items = [];
        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>\s*(?:<p[^>]*>(.*?)<\/p>)/is', $html, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $q = wp_strip_all_tags( $m[1] );
            $a = wp_strip_all_tags( $m[2] );
            if ( $q && $a ) {
                $items[] = [ '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a ] ];
            }
        }
        if ( empty( $items ) ) return '';
        $schema = wp_json_encode( [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        return "\n\n<script type=\"application/ld+json\">\n{$schema}\n</script>";
    }

    /**
     * Fix 4: Scop FAQPage schema til kun spørgsmål markeret med rzpa-faq-start/end kommentarer.
     * Falder tilbage til build_faq_schema() på hele HTML hvis markers ikke findes.
     */
    private static function build_faq_schema_from_section( string $html ): string {
        if ( preg_match( '/<!--\s*rzpa-faq-start\s*-->(.*?)<!--\s*rzpa-faq-end\s*-->/is', $html, $m ) ) {
            return self::build_faq_schema( $m[1] );
        }
        // Fallback: brug kun h2+p-par der ligner spørgsmål (indeholder "?" eller starter med "Hvad/Hvordan/Hvornår")
        $items = [];
        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>\s*(?:<p[^>]*>(.*?)<\/p>)/is', $html, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $q = wp_strip_all_tags( $m[1] );
            $a = wp_strip_all_tags( $m[2] );
            // Kun medtag hvis H2 ligner et spørgsmål
            if ( $q && $a && ( str_contains( $q, '?' ) || preg_match( '/^(Hvad|Hvordan|Hvornår|Hvorfor|Kan|Er |Skal |Hvilke)/iu', $q ) ) ) {
                $items[] = [ '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a ] ];
            }
        }
        if ( empty( $items ) ) return '';
        $schema = wp_json_encode( [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        return "\n\n<script type=\"application/ld+json\">\n{$schema}\n</script>";
    }

    public static function blog_ai_suggestions( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_api_key', 'OpenAI API key er ikke konfigureret under Indstillinger.', [ 'status' => 400 ] );
        }

        $days   = (int) ( $r->get_json_params()['days'] ?? 30 );
        $days   = in_array( $days, [ 7, 30, 90 ], true ) ? $days : 30;
        $posts  = RZPA_Database::get_blog_insights( $days );

        // Byg kontekstliste af eksisterende blogindlæg
        $existing_context = '';
        foreach ( $posts as $p ) {
            $pos   = $p['position'] !== null ? '#' . $p['position'] : 'ikke indekseret';
            $klik  = $p['clicks'] > 0 ? $p['clicks'] . ' klik' : '0 klik';
            $existing_context .= "- \"{$p['title']}\" ({$pos}, {$klik})\n";
        }
        if ( ! $existing_context ) {
            $existing_context = "- Ingen eksisterende blogindlæg fundet endnu.\n";
        }

        $prompt = <<<PROMPT
Du er en dansk SEO-strateg med speciale i jobmarkedet og rekruttering.

VIRKSOMHEDSKONTEKST:
Rezponz er et dansk salgshus (Aalborg) der rekrutterer sælgere og kundeservicemedarbejdere til bl.a. Telenor, Norlys, NRGI og CBB. De ønsker at rangere på Google når danskere aktivt søger job inden for salg, telekommunikation og energi.

EKSISTERENDE BLOGINDLÆG (med Google-placering og klik de seneste {$days} dage):
{$existing_context}

OPGAVE:
Analyser og identificér de 8 mest værdifulde blogindlæg Rezponz BØR skrive — blogs der endnu IKKE eksisterer eller er svagt dækket. Fokus på søgeord hvor jobsøgere aktivt leder efter muligheder, råd om jobsøgning, eller information om specifikke brancher/stillinger.

Prioritér efter: høj søgevolumen × lav/medium konkurrence × høj kommerciel relevans for Rezponz.

SVAR KUN med et JSON-array i dette format (ingen anden tekst):
[
  {
    "priority": 1,
    "title": "Den foreslåede blogtitel (klikvenlig og SEO-optimeret)",
    "keyword": "det primære target-søgeord på dansk",
    "search_volume": "lav/medium/høj",
    "competition": "lav/medium/høj",
    "value_score": 1-10,
    "search_intent": "Kort beskrivelse af hvad søgeren ønsker",
    "rezponz_value": "Konkret forklaring af hvorfor dette blogindlæg er værdifuldt for Rezponz",
    "content_angle": "Den specifikke vinkel/hook der differentierer dette indlæg",
    "estimated_monthly_searches": "fx 500-1.000"
  }
]
PROMPT;

        $res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_api_key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'model'       => 'gpt-4.1-mini',
                'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens'  => 2500,
                'temperature' => 0.6,
            ] ),
            'timeout' => 60,
        ] );

        if ( is_wp_error( $res ) ) {
            return new WP_Error( 'openai_error', $res->get_error_message(), [ 'status' => 500 ] );
        }

        $http = wp_remote_retrieve_response_code( $res );
        if ( $http !== 200 ) {
            $err = json_decode( wp_remote_retrieve_body( $res ), true );
            $msg = $err['error']['message'] ?? 'OpenAI fejl HTTP ' . $http;
            return new WP_Error( 'openai_http', $msg, [ 'status' => 500 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        $text = trim( $body['choices'][0]['message']['content'] ?? '' );

        // Ekstraher JSON-arrayet fra svaret (robust mod markdown-blokke)
        if ( preg_match( '/\[.*\]/s', $text, $m ) ) {
            $suggestions = json_decode( $m[0], true );
            if ( is_array( $suggestions ) && ! empty( $suggestions ) ) {
                // Sortér efter priority (lavest = højest prioritet)
                usort( $suggestions, fn( $a, $b ) => ( (int)( $a['priority'] ?? 99 ) ) <=> ( (int)( $b['priority'] ?? 99 ) ) );
                return self::ok( $suggestions );
            }
        }

        return new WP_Error( 'parse_error', 'Kunne ikke parse AI-svaret. Prøv igen.', [ 'status' => 500 ] );
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
                'model'    => 'gpt-4.1-mini',
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

    // ════════════════════════════════════════════════════════════════════════
    // REKRUTTERING
    // ════════════════════════════════════════════════════════════════════════

    public static function rekruttering_stats( WP_REST_Request $r ) {
        $days  = (int) ( $r->get_param( 'days' ) ?? 30 );
        $force = ! empty( $r->get_param( 'force' ) );
        if ( $force ) RZPA_Rekruttering::clear_cache( $days );
        $data  = RZPA_Rekruttering::get_stats( $days );
        return self::ok( $data );
    }

    public static function rekruttering_pipeline_save( WP_REST_Request $r ) {
        $body = $r->get_json_params();
        if ( empty( $body ) ) return new WP_Error( 'bad_request', 'Ingen data', [ 'status' => 400 ] );
        $ok = RZPA_Rekruttering::save_pipeline( $body );
        return self::ok( [ 'saved' => $ok ] );
    }

    // ── POST /rekruttering/generate-job-ad ────────────────────────────────────
    public static function rekruttering_generate_job_ad( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_openai', 'OpenAI API-nøgle mangler under Indstillinger.', [ 'status' => 400 ] );
        }

        $body      = $r->get_json_params();
        $role      = sanitize_text_field( $body['role']      ?? '' );
        $location  = sanitize_text_field( $body['location']  ?? 'Aalborg' );
        $tone      = sanitize_text_field( $body['tone']      ?? 'professionel' );
        $points    = array_map( 'sanitize_text_field', (array) ( $body['points'] ?? [] ) );
        $points    = array_filter( $points );

        if ( ! $role ) {
            return new WP_Error( 'missing_role', 'Angiv en stillingsbetegnelse.', [ 'status' => 400 ] );
        }

        $points_str = ! empty( $points ) ? implode( "\n- ", $points ) : 'Fleksible arbejdstider, godt fællesskab, mulighed for karriereudvikling';

        $prompt = <<<PROMPT
Du er en erfaren rekrutteringsspecialist og copywriter for Rezponz – et dansk salgshus i Aalborg der rekrutterer kundeservicemedarbejdere til bl.a. Telenor, Norlys og CBB.

Skriv to versioner af en jobopslag-annonce på dansk:

Stilling: {$role}
Lokation: {$location}
Tone: {$tone}
Fordele ved jobbet:
- {$points_str}

VERSION 1 – META ADS (Facebook/Instagram)
- Maks 125 tegn til primær tekst (det folk ser uden at klikke "Se mere")
- Én kort, fængende overskrift (maks 40 tegn)
- En beskrivelse på 2-3 sætninger der fremhæver fordele og skaber lyst til at søge
- Slut med en tydelig CTA

VERSION 2 – GOOGLE SEARCH (RSA-format)
- 3 overskrifter på maks 30 tegn hver (Headline 1, 2, 3)
- 2 beskrivelseslinjer på maks 90 tegn hver

Svar i dette JSON-format (ingen anden tekst):
{
  "meta": {
    "primary_text": "...",
    "headline": "...",
    "description": "..."
  },
  "google": {
    "headline1": "...",
    "headline2": "...",
    "headline3": "...",
    "desc1": "...",
    "desc2": "..."
  },
  "tips": ["...", "...", "..."]
}

"tips" skal indeholde 3 korte anbefalinger til targeting/opsætning baseret på stillingen og lokationen.
PROMPT;

        $json = self::openai_generate( $prompt, $opts['openai_api_key'], 800 );
        if ( is_wp_error( $json ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => $json->get_error_message() ], 500 );
        }

        $data = json_decode( self::strip_md_fences( $json ), true );
        if ( ! $data ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI returnerede ugyldigt format. Prøv igen.' ], 500 );
        }

        return self::ok( [ 'ad' => $data, 'role' => $role, 'location' => $location ] );
    }

    // ── GET /rekruttering/ai-report ───────────────────────────────────────────
    public static function rekruttering_ai_report( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_openai', 'OpenAI API-nøgle mangler under Indstillinger.', [ 'status' => 400 ] );
        }

        $days  = (int) ( $r->get_param( 'days' ) ?? 30 );
        $force = ! empty( $r->get_param( 'force' ) );

        // 24-timers cache — AI-rapport behøver ikke regenereres ved hvert klik
        $cache_key = 'rzpa_rekrut_ai_report_' . $days;
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return self::ok( $cached );
        }

        $stats = RZPA_Rekruttering::get_stats( $days );
        $t     = $stats['totals'] ?? [];
        $all   = array_merge( $stats['meta_campaigns'] ?? [], $stats['google_campaigns'] ?? [] );
        $pipe  = $stats['pipeline'] ?? [];

        // Byg kompakt dataoversigt til AI
        $camp_lines = '';
        foreach ( $all as $c ) {
            $cpl_str = $c['cpl'] > 0 ? number_format( $c['cpl'], 0, ',', '.' ) . ' kr.' : 'ingen data';
            $camp_lines .= "- {$c['campaign_name']} ({$c['channel']}): {$c['leads']} ansøgninger, CPL {$cpl_str}, CTR {$c['ctr']}%, spend " . number_format( $c['spend'], 0, ',', '.' ) . " kr.\n";
        }

        $pipe_hired  = ( $pipe['ansat']['aalborg']   ?? 0 ) + ( $pipe['ansat']['remote']   ?? 0 ) + ( $pipe['ansat']['uopfordret'] ?? 0 );
        $pipe_total  = ( $pipe['ansoegt']['aalborg'] ?? 0 ) + ( $pipe['ansoegt']['remote'] ?? 0 ) + ( $pipe['ansoegt']['uopfordret'] ?? 0 );
        $conv_rate   = $pipe_total > 0 ? round( $pipe_hired / $pipe_total * 100 ) : 0;

        $prompt = <<<PROMPT
Du er en senior rekrutteringsrådgiver og performance marketing specialist med erfaring fra dansk arbejdsmarked.

Analyser disse rekrutteringstal for Rezponz (seneste {$days} dage) og giv 4 konkrete anbefalinger:

TOTALER:
- Ansøgninger i alt: {$t['leads']}
- Samlet CPL: {$t['cpl']} kr.
- Samlet spend: {$t['spend']} kr.
- Meta ansøgninger: {$t['meta_leads']} (spend: {$t['meta_spend']} kr.)
- Google ansøgninger: {$t['google_leads']} (spend: {$t['google_spend']} kr.)

KAMPAGNER:
{$camp_lines}
PIPELINE:
- Ansøgt i alt: {$pipe_total}
- Ansat: {$pipe_hired}
- Konverteringsrate: {$conv_rate}%

Svar i dette JSON-format (ingen anden tekst):
{
  "headline": "Kort overskrift på dansk (maks 60 tegn)",
  "summary": "2-3 sætninger opsummering af rekrutteringssituationen",
  "recommendations": [
    {
      "priority": "høj|middel|lav",
      "icon": "emoji",
      "title": "Kort titel (maks 50 tegn)",
      "text": "Konkret anbefaling med tal hvor muligt (maks 120 tegn)",
      "action": "Hvad skal gøres nu (maks 60 tegn)"
    }
  ]
}

Giv præcis 4 anbefalinger. Vær konkret med beløb og procenter. Fokus på hvad der øger antallet af ansøgninger.
PROMPT;

        $json = self::openai_generate( $prompt, $opts['openai_api_key'], 700 );
        if ( is_wp_error( $json ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => $json->get_error_message() ], 500 );
        }

        $report = json_decode( self::strip_md_fences( $json ), true );
        if ( ! $report ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI svar kunne ikke parses.' ], 500 );
        }

        $report['generated_at'] = current_time( 'mysql' );
        $report['days']         = $days;
        set_transient( $cache_key, $report, DAY_IN_SECONDS );

        return self::ok( $report );
    }

    /**
     * PDF-proxy: henter Meta faktura-PDF server-side med access token
     * og streamer den direkte til brugerens browser som download.
     * Kræver ?invoice_id=FBADS-105-XXXXXX
     */
    public static function meta_invoice_pdf( WP_REST_Request $r ) {
        $invoice_id = sanitize_text_field( $r->get_param( 'invoice_id' ) ?? '' );
        if ( ! $invoice_id || ! preg_match( '/^[A-Z0-9_\-]+$/i', $invoice_id ) ) {
            return new WP_Error( 'invalid_invoice', 'Ugyldigt faktura-ID', [ 'status' => 400 ] );
        }

        $opts       = get_option( 'rzpa_settings', [] );
        $token      = $opts['meta_access_token'] ?? '';
        $account_id = $opts['meta_ad_account_id'] ?? '';
        if ( ! $token || ! $account_id ) {
            return new WP_Error( 'not_configured', 'Meta Ads ikke konfigureret', [ 'status' => 400 ] );
        }

        $pdf_url = 'https://business.facebook.com/ads/ads_invoice/download/?'
            . 'account_id=' . rawurlencode( $account_id )
            . '&invoice_id=' . rawurlencode( $invoice_id )
            . '&access_token=' . rawurlencode( $token );

        $res = wp_remote_get( $pdf_url, [ 'timeout' => 30, 'redirection' => 5 ] );
        if ( is_wp_error( $res ) ) {
            return new WP_Error( 'fetch_error', $res->get_error_message(), [ 'status' => 502 ] );
        }

        $http_code   = wp_remote_retrieve_response_code( $res );
        $body        = wp_remote_retrieve_body( $res );
        $content_type = wp_remote_retrieve_header( $res, 'content-type' ) ?: 'application/pdf';

        if ( $http_code !== 200 || strlen( $body ) < 100 ) {
            return new WP_Error( 'pdf_unavailable', 'PDF ikke tilgængelig fra Meta (kræver muligvis login)', [ 'status' => 502 ] );
        }

        // Stream PDF direkte til browser
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="Meta-Faktura-' . sanitize_file_name( $invoice_id ) . '.pdf"' );
        header( 'Content-Length: ' . strlen( $body ) );
        header( 'Cache-Control: private, no-cache' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $body;
        exit;
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
                'model'      => 'gpt-4.1-mini',
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
                'model'      => 'gpt-4.1-mini',
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
            'body'    => wp_json_encode( [ 'model' => 'gpt-4.1-mini', 'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ], 'max_tokens' => 1200 ] ),
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

    public static function snap_ai_analysis( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI API-nøgle — tilføj den i Indstillinger' ] );

        $days      = self::days( $r );
        $summary   = RZPA_Database::get_snap_summary( $days );
        $campaigns = RZPA_Database::get_snap_campaigns( $days );

        if ( empty( $summary['total_spend'] ) || (float) $summary['total_spend'] === 0.0 ) {
            return self::ok( [ 'error' => 'Ingen Snapchat-data — klik "Hent data" først' ] );
        }

        $spend   = round( (float) ( $summary['total_spend']      ?? 0 ) );
        $impr    = (int)          ( $summary['total_impressions'] ?? 0 );
        $swipes  = (int)          ( $summary['total_swipe_ups']   ?? 0 );
        $eng_rate = $impr > 0 ? round( $swipes / $impr * 100, 2 ) : 0;

        $campText = implode( "\n", array_map( fn($c) =>
            sprintf( '- %s [%s]: %s kr, %s vist, %s swipe-ups, %.2f%% eng.',
                $c['campaign_name'], $c['status'] ?? 'UNKNOWN',
                number_format( (float) $c['spend'], 0, ',', '.' ),
                number_format( (int) ( $c['impressions'] ?? 0 ), 0, ',', '.' ),
                number_format( (int) ( $c['swipe_ups'] ?? 0 ), 0, ',', '.' ),
                $impr > 0 ? ( (int) ( $c['swipe_ups'] ?? 0 ) / max( 1, (int) ( $c['impressions'] ?? 1 ) ) * 100 ) : 0
            ),
            array_slice( $campaigns, 0, 15 )
        ) );

        $cache_key = 'rzpa_snap_ai_' . md5( $days . $spend . $swipes . $eng_rate );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return self::ok( [ 'analysis' => $cached, 'cached' => true ] );

        $prompt = "Du er en erfaren Snapchat Ads-specialist. Analyser disse annonce-data for Rezponz.dk — en dansk B2B kundeservice-virksomhed.\n\n"
            . "PERIODE: Seneste {$days} dage\n"
            . "TOTAL: {$spend} kr brugt · {$impr} visninger · {$swipes} swipe-ups · {$eng_rate}% engagement rate\n"
            . "BENCHMARK: Snapchat engagement rate over 1% er godt for B2B, under 0,3% kræver handling\n\n"
            . "KAMPAGNER:\n{$campText}\n\n"
            . "Giv en struktureret analyse med disse 5 sektioner:\n\n"
            . "1. OVERORDNET VURDERING\n"
            . "Vurder samlet performance. Er engagement rate tilfredsstillende? Hvad koster en swipe-up?\n\n"
            . "2. TOP PRIORITET NU\n"
            . "Ét konkret tiltag med størst effekt lige nu. Vær meget specifik.\n\n"
            . "3. KAMPAGNE-ANBEFALINGER\n"
            . "Gennemgå aktive kampagner: skalér, optimér eller pausér? Konkrete årsager.\n\n"
            . "4. KREATIVT OG FORMAT\n"
            . "Konkrete forslag til Snap-annoncer der virker: format (Collection, Single Image, Story), hook-tekst, CTA-knap, varighed.\n\n"
            . "5. MÅLGRUPPE OG BUDGET\n"
            . "Snapchats målgruppe er typisk 18-34 år — passer det til Rezponz? Forslag til bedre targeting og budget-fordeling.\n\n"
            . "Skriv på dansk. Brug tal fra data. Max 600 ord.";

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => 'gpt-4.1-mini',
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

    public static function tiktok_ai_analysis( WP_REST_Request $r ) {
        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) return self::ok( [ 'error' => 'Ingen OpenAI API-nøgle — tilføj den i Indstillinger' ] );

        $days      = self::days( $r );
        $summary   = RZPA_Database::get_tiktok_summary( $days );
        $campaigns = RZPA_Database::get_tiktok_campaigns( $days );

        if ( empty( $summary['total_spend'] ) || (float) $summary['total_spend'] === 0.0 ) {
            return self::ok( [ 'error' => 'Ingen TikTok-data — klik "Hent data" først' ] );
        }

        $spend     = round( (float) ( $summary['total_spend']       ?? 0 ) );
        $views     = (int)          ( $summary['total_video_views']  ?? 0 );
        $clicks    = (int)          ( $summary['total_clicks']       ?? 0 );
        $roas      = round( (float) ( $summary['avg_roas']           ?? 0 ), 2 );
        $hook_rate = $views > 0 && isset( $summary['total_three_sec_views'] )
            ? round( (int) $summary['total_three_sec_views'] / $views * 100, 1 )
            : 0;

        $campText = implode( "\n", array_map( fn($c) =>
            sprintf( '- %s [%s]: %s kr, %s views, %s klik, %.2fx ROAS',
                $c['campaign_name'], $c['status'] ?? 'UNKNOWN',
                number_format( (float) $c['spend'], 0, ',', '.' ),
                number_format( (int) ( $c['video_views'] ?? 0 ), 0, ',', '.' ),
                number_format( (int) ( $c['clicks'] ?? 0 ), 0, ',', '.' ),
                (float) ( $c['roas'] ?? 0 )
            ),
            array_slice( $campaigns, 0, 15 )
        ) );

        $cache_key = 'rzpa_tiktok_ai_' . md5( $days . $spend . $views . $roas );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return self::ok( [ 'analysis' => $cached, 'cached' => true ] );

        $prompt = "Du er en erfaren TikTok Ads-specialist. Analyser disse annonce-data for Rezponz.dk — en dansk B2B kundeservice-virksomhed.\n\n"
            . "PERIODE: Seneste {$days} dage\n"
            . "TOTAL: {$spend} kr brugt · {$views} video views · {$clicks} klik · {$roas}x ROAS · {$hook_rate}% hook rate\n"
            . "BENCHMARK: TikTok hook rate (3s) over 25% er godt · ROAS over 2,5x er stærkt for e-com/B2B\n\n"
            . "KAMPAGNER:\n{$campText}\n\n"
            . "Giv en struktureret analyse med disse 5 sektioner:\n\n"
            . "1. OVERORDNET VURDERING\n"
            . "Vurder samlet performance. Er hook rate og ROAS tilfredsstillende? Hvad koster et view?\n\n"
            . "2. TOP PRIORITET NU\n"
            . "Ét konkret tiltag med størst effekt lige nu. Vær meget specifik.\n\n"
            . "3. KAMPAGNE-ANBEFALINGER\n"
            . "Gennemgå aktive kampagner: skalér, optimér eller pausér? Konkrete årsager baseret på ROAS.\n\n"
            . "4. VIDEO-KREATIVT\n"
            . "Konkrete forslag til TikTok-videoer der fanger: hook-tekst i første 3 sekunder, format (UGC, talking head, product demo), musik, CTA. Hvad virker for B2B rekruttering på TikTok?\n\n"
            . "5. MÅLGRUPPE OG SKALERING\n"
            . "TikTok-målgrupper for B2B: Custom Audiences, Lookalike, interesser. Hvornår skal man skalere budget?\n\n"
            . "Skriv på dansk. Brug tal fra data. Max 600 ord.";

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => 'gpt-4.1-mini',
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

        // Kun Meta/Facebook CDN-urls tilladt (fbcdn.net dækker alle subdomæner inkl. video.*.fna.fbcdn.net)
        if ( ! $url || ! preg_match( '#^https?://[^\s]*(facebook\.com|fbcdn\.net|cdninstagram\.com)#i', $url ) ) {
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
        $force     = (bool) ( $r->get_param( 'force' ) );
        if ( $force ) delete_transient( $cache_key );

        $cached = get_transient( $cache_key );
        // Only use cache if it's a non-empty array (never cache error arrays or empty results)
        if ( $cached !== false && is_array( $cached ) && ! empty( $cached ) && ! isset( $cached['error'] ) ) {
            return self::ok( $cached );
        }

        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) ) {
            return self::ok( [] );
        }

        $data = RZPA_Google_Ads::fetch_ads();
        // Only cache successful non-empty results
        if ( is_array( $data ) && ! empty( $data ) && ! isset( $data['error'] ) ) {
            set_transient( $cache_key, $data, HOUR_IN_SECONDS );
        }
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
                'model'       => 'gpt-4.1-mini',
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
                'model'      => 'gpt-4.1-mini',
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
