<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz Crew – Frontend tracking.
 * - Stores UTM cookie when visitor arrives via a Crew Member link
 * - Records conversions when visitor hits the thank-you page
 * - Renders the [rezponz_crew_dashboard] shortcode
 */
class RZPZ_Crew_Tracking {

    const COOKIE_NAME = 'rzpz_crew_ref';

    public static function init() : void {
        // Store UTM cookie on every front-end request (early, before headers sent)
        add_action( 'init', [ __CLASS__, 'maybe_store_cookie' ], 1 );

        // Detect conversion on thank-you page
        add_action( 'wp', [ __CLASS__, 'maybe_record_conversion' ] );

        // Shortcode for personal dashboard
        add_shortcode( 'rezponz_crew_dashboard', [ __CLASS__, 'shortcode_dashboard' ] );

        // Enqueue frontend assets when shortcode page is loaded
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
    }

    // ── UTM Cookie ───────────────────────────────────────────────────────────

    public static function maybe_store_cookie() : void {
        // Only store if utm_source=rezponz_crew is present in URL
        $source = sanitize_text_field( wp_unslash( $_GET['utm_source'] ?? '' ) );
        if ( $source !== 'rezponz_crew' ) return;

        $content  = sanitize_text_field( wp_unslash( $_GET['utm_content']  ?? '' ) );
        $campaign = sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ?? '' ) );
        if ( ! $content ) return;

        // Verify crew member exists and is active
        $member = RZPZ_Crew_DB::get_member_by_crew_id( $content );
        if ( ! $member || $member['status'] !== 'active' ) return;

        $opts    = get_option( 'rzpz_crew_settings', [] );
        $days    = (int) ( $opts['cookie_days'] ?? 30 );
        $expires = time() + ( $days * DAY_IN_SECONDS );

        $cookie_val = wp_json_encode( [
            'crew_id'  => $content,
            'campaign' => $campaign,
            'ts'       => time(),
        ] );

        if ( ! headers_sent() ) {
            setcookie( self::COOKIE_NAME, $cookie_val, $expires, '/', '', is_ssl(), true );
            $_COOKIE[ self::COOKIE_NAME ] = $cookie_val;
        }

        // Also record the click
        self::record_click_for( $member, $campaign );
    }

    private static function record_click_for( array $member, string $campaign ) : void {
        // Find matching link (or the latest link for this campaign)
        global $wpdb;
        $link = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crew_links
             WHERE crew_member_id = %d AND utm_campaign = %s
             ORDER BY created_at DESC LIMIT 1",
            $member['id'], $campaign
        ), ARRAY_A );

        if ( $link ) {
            RZPZ_Crew_DB::record_click( (int) $link['id'], (int) $member['id'] );
        }
    }

    // ── Conversion detection ─────────────────────────────────────────────────

    public static function maybe_record_conversion() : void {
        if ( ! is_singular() ) return;

        $opts      = get_option( 'rzpz_crew_settings', [] );
        $thank_you = trailingslashit( $opts['conversion_url'] ?? 'https://rezponz.dk/tak-for-din-ansoegning/' );
        $current   = trailingslashit( home_url( add_query_arg( null, null ) ) );

        // Match on path only (ignore domain)
        $ty_path  = parse_url( $thank_you, PHP_URL_PATH );
        $cur_path = parse_url( $current,   PHP_URL_PATH );
        if ( $ty_path !== $cur_path ) return;

        // Check for cookie
        $raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ?? '' ) );
        if ( ! $raw ) return;

        $data = json_decode( $raw, true );
        if ( empty( $data['crew_id'] ) ) return;

        $member = RZPZ_Crew_DB::get_member_by_crew_id( $data['crew_id'] );
        if ( ! $member ) return;

        // Find link_id
        global $wpdb;
        $link_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rzpz_crew_links WHERE crew_member_id = %d AND utm_campaign = %s ORDER BY created_at DESC LIMIT 1",
            $member['id'], $data['campaign'] ?? ''
        ) );

        RZPZ_Crew_DB::record_conversion( (int) $member['id'], $link_id ? (int) $link_id : null, $data['campaign'] ?? '' );
    }

    // ── Frontend Assets ───────────────────────────────────────────────────────

    public static function maybe_enqueue_assets() : void {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'rezponz_crew_dashboard' ) ) {
            wp_enqueue_style( 'rzpz-crew-frontend', RZPA_URL . 'modules/crew/assets/crew-frontend.css', [], RZPA_VERSION );
            wp_enqueue_script( 'rzpz-crew-frontend', RZPA_URL . 'modules/crew/assets/crew-frontend.js', [], RZPA_VERSION, true );
            wp_localize_script( 'rzpz-crew-frontend', 'RZPZ_Crew', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'rzpz_crew_frontend' ),
            ] );
        }
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────

    public static function shortcode_dashboard( $atts ) : string {
        if ( ! is_user_logged_in() ) {
            return '<div class="rzpz-crew-login-notice"><p>' . __( 'Du skal være logget ind for at se dit Crew-dashboard.', 'rezponz-analytics' ) . '</p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="rzpz-crew-btn">' . __( 'Log ind', 'rezponz-analytics' ) . '</a></div>';
        }

        $member = RZPZ_Crew_DB::get_member_by_user( get_current_user_id() );
        if ( ! $member ) {
            return '<div class="rzpz-crew-login-notice"><p>' . __( 'Din konto er endnu ikke oprettet som Crew Member. Kontakt din administrator.', 'rezponz-analytics' ) . '</p></div>';
        }

        if ( $member['status'] !== 'active' ) {
            return '<div class="rzpz-crew-login-notice"><p>' . __( 'Din Crew Member-konto er ikke aktiv. Kontakt din administrator.', 'rezponz-analytics' ) . '</p></div>';
        }

        $days        = (int) ( $atts['days'] ?? 30 );
        $clicks      = RZPZ_Crew_DB::get_clicks_count( (int) $member['id'], $days );
        $conversions = RZPZ_Crew_DB::get_conversions_count( (int) $member['id'], $days );
        $links       = RZPZ_Crew_DB::get_links( (int) $member['id'] );
        $top_links   = RZPZ_Crew_DB::get_top_links( (int) $member['id'], 5 );
        $bonuses     = RZPZ_Crew_DB::get_bonuses( (int) $member['id'] );
        $rules       = RZPZ_Crew_DB::get_bonus_rules( true );

        // Estimated bonus this month
        $month_start = gmdate( 'Y-m-01' );
        $month_end   = gmdate( 'Y-m-d' );
        $est_bonus   = RZPZ_Crew_DB::calculate_bonus( (int) $member['id'], $month_start, $month_end );

        ob_start();
        include __DIR__ . '/views/frontend-dashboard.php';
        return ob_get_clean();
    }
}
