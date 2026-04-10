<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz Crew – Main module class.
 * Registers admin menus, asset enqueuing, form handlers and REST endpoints.
 */
class RZPZ_Crew {

    public static function init() : void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_post_rzpz_crew_save_member',   [ __CLASS__, 'handle_save_member' ] );
        add_action( 'admin_post_rzpz_crew_delete_member', [ __CLASS__, 'handle_delete_member' ] );
        add_action( 'admin_post_rzpz_crew_generate_link', [ __CLASS__, 'handle_generate_link' ] );
        add_action( 'admin_post_rzpz_crew_save_bonus_rule',  [ __CLASS__, 'handle_save_bonus_rule' ] );
        add_action( 'admin_post_rzpz_crew_update_bonus',     [ __CLASS__, 'handle_update_bonus' ] );
        add_action( 'admin_post_rzpz_crew_update_boost',     [ __CLASS__, 'handle_update_boost' ] );
        add_action( 'admin_post_rzpz_crew_save_settings',    [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_rzpz_crew_delete_link',      [ __CLASS__, 'handle_delete_link' ] );
        add_action( 'rest_api_init',         [ __CLASS__, 'register_rest' ] );
    }

    // ── Admin Menu ───────────────────────────────────────────────────────────

    public static function register_menu() : void {
        $cap = 'manage_options';
        $crew_pages = [
            'rezponz-crew'          => __( 'Crew Oversigt',      'rezponz-analytics' ),
            'rezponz-crew-bonus'    => __( 'Bonus',               'rezponz-analytics' ),
            'rezponz-crew-boost'    => __( 'Boost til Ads',       'rezponz-analytics' ),
            'rezponz-crew-settings' => __( 'Crew Indstillinger',  'rezponz-analytics' ),
        ];
        $callbacks = [
            'rezponz-crew'          => [ __CLASS__, 'page_crew_list' ],
            'rezponz-crew-bonus'    => [ __CLASS__, 'page_bonus' ],
            'rezponz-crew-boost'    => [ __CLASS__, 'page_boost' ],
            'rezponz-crew-settings' => [ __CLASS__, 'page_settings' ],
        ];
        foreach ( $crew_pages as $slug => $label ) {
            add_submenu_page( 'rzpa-dashboard', $label . ' – Rezponz Crew', $label, $cap, $slug, $callbacks[ $slug ] );
        }
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ) : void {
        if ( strpos( $hook, 'rezponz-crew' ) === false ) return;
        wp_enqueue_style(  'rzpz-crew-admin', RZPA_URL . 'modules/crew/assets/crew-admin.css', [], RZPA_VERSION );
        wp_enqueue_script( 'rzpz-crew-admin', RZPA_URL . 'modules/crew/assets/crew-admin.js',  [], RZPA_VERSION, true );
        wp_localize_script( 'rzpz-crew-admin', 'RZPZ_Crew_Admin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rzpz_crew_admin' ),
            'restBase'=> rest_url( 'rzpz-crew/v1' ),
            'wpNonce' => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    // ── Page Renderers ────────────────────────────────────────────────────────

    public static function page_crew_list() : void {
        // Single member view
        if ( isset( $_GET['member_id'] ) ) {
            $member = RZPZ_Crew_DB::get_member( (int) $_GET['member_id'] );
            if ( $member ) {
                $links       = RZPZ_Crew_DB::get_links( (int) $member['id'] );
                $clicks      = RZPZ_Crew_DB::get_clicks_count( (int) $member['id'] );
                $conversions = RZPZ_Crew_DB::get_conversions_count( (int) $member['id'] );
                $top_links   = RZPZ_Crew_DB::get_top_links( (int) $member['id'] );
                include __DIR__ . '/views/admin-crew-member.php';
                return;
            }
        }
        // Add/edit form
        if ( isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'add', 'edit' ] ) ) {
            $member = [];
            if ( $_GET['action'] === 'edit' && isset( $_GET['member_id'] ) ) {
                $member = RZPZ_Crew_DB::get_member( (int) $_GET['member_id'] ) ?: [];
            }
            $wp_users = get_users( [ 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
            include __DIR__ . '/views/admin-crew-form.php';
            return;
        }
        // List
        $members     = RZPZ_Crew_DB::get_members();
        $leaderboard = RZPZ_Crew_DB::get_leaderboard( 30 );
        include __DIR__ . '/views/admin-crew-list.php';
    }

    public static function page_bonus() : void {
        $members = RZPZ_Crew_DB::get_members( 'active' );
        $bonuses = RZPZ_Crew_DB::get_bonuses();
        $rules   = RZPZ_Crew_DB::get_bonus_rules();
        include __DIR__ . '/views/admin-bonus.php';
    }

    public static function page_boost() : void {
        $boosts  = RZPZ_Crew_DB::get_boosts();
        // Top performing links not yet boosted
        global $wpdb;
        $opts       = get_option( 'rzpz_crew_settings', [] );
        $conv_thresh = (int) ( $opts['boost_conversions_threshold'] ?? 1 );
        $ctr_thresh  = (float) ( $opts['boost_ctr_threshold'] ?? 2.0 );
        $candidates  = $wpdb->get_results(
            "SELECT l.*, m.display_name,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_conversions c WHERE c.link_id = l.id) AS conversions,
                    b.id AS boost_id
             FROM {$wpdb->prefix}rzpz_crew_links l
             LEFT JOIN {$wpdb->prefix}rzpz_crew_members m ON l.crew_member_id = m.id
             LEFT JOIN {$wpdb->prefix}rzpz_crew_boosts b ON b.link_id = l.id
             WHERE l.clicks > 0
             ORDER BY conversions DESC, l.clicks DESC
             LIMIT 50",
            ARRAY_A
        ) ?: [];
        include __DIR__ . '/views/admin-boost.php';
    }

    public static function page_settings() : void {
        $opts    = get_option( 'rzpz_crew_settings', [] );
        $saved   = isset( $_GET['saved'] );
        include __DIR__ . '/views/admin-settings.php';
    }

    // ── Form Handlers ─────────────────────────────────────────────────────────

    public static function handle_save_member() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_save_member' );

        $id = (int) ( $_POST['member_id'] ?? 0 );
        $data = [
            'display_name'  => sanitize_text_field( $_POST['display_name']  ?? '' ),
            'email'         => sanitize_email(      $_POST['email']         ?? '' ),
            'phone'         => sanitize_text_field( $_POST['phone']         ?? '' ),
            'bio'           => sanitize_textarea_field( $_POST['bio']       ?? '' ),
            'facebook_url'  => esc_url_raw( $_POST['facebook_url']  ?? '' ),
            'instagram_url' => esc_url_raw( $_POST['instagram_url'] ?? '' ),
            'tiktok_url'    => esc_url_raw( $_POST['tiktok_url']    ?? '' ),
            'snapchat_url'  => esc_url_raw( $_POST['snapchat_url']  ?? '' ),
            'status'        => in_array( $_POST['status'] ?? '', [ 'active', 'inactive' ] ) ? $_POST['status'] : 'active',
            'user_id'       => (int) ( $_POST['user_id'] ?? 0 ) ?: null,
        ];
        if ( ! $data['display_name'] || ! $data['email'] ) {
            wp_redirect( add_query_arg( 'error', 'required', admin_url( 'admin.php?page=rezponz-crew&action=' . ( $id ? 'edit&member_id=' . $id : 'add' ) ) ) );
            exit;
        }

        if ( $id ) {
            RZPZ_Crew_DB::update_member( $id, $data );
        } else {
            $id = RZPZ_Crew_DB::insert_member( $data );
        }
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew&member_id=' . $id . '&saved=1' ) );
        exit;
    }

    public static function handle_delete_member() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_delete_member' );
        RZPZ_Crew_DB::delete_member( (int) ( $_POST['member_id'] ?? 0 ) );
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew&deleted=1' ) );
        exit;
    }

    public static function handle_delete_link() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_delete_link' );
        $link_id   = (int) ( $_POST['link_id']   ?? 0 );
        $member_id = (int) ( $_POST['member_id'] ?? 0 );
        RZPZ_Crew_DB::delete_link( $link_id );
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew&member_id=' . $member_id . '&link_deleted=1' ) );
        exit;
    }

    public static function handle_generate_link() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_generate_link' );

        $member_id    = (int) ( $_POST['crew_member_id'] ?? 0 );
        $campaign     = sanitize_text_field( $_POST['campaign_name'] ?? 'default' );
        $dest_url     = esc_url_raw( $_POST['destination_url'] ?? '' );
        $member       = RZPZ_Crew_DB::get_member( $member_id );

        if ( ! $member ) wp_die( 'Invalid member' );

        RZPZ_Crew_DB::insert_link( [
            'crew_member_id' => $member_id,
            'campaign_name'  => $campaign,
            'destination_url'=> $dest_url,
            'utm_content'    => $member['crew_id'],
        ] );

        wp_redirect( admin_url( 'admin.php?page=rezponz-crew&member_id=' . $member_id . '&link_created=1' ) );
        exit;
    }

    public static function handle_save_bonus_rule() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_save_bonus_rule' );
        RZPZ_Crew_DB::save_bonus_rule( $_POST );
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew-bonus&saved=1' ) );
        exit;
    }

    public static function handle_update_bonus() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_update_bonus' );
        $id     = (int) ( $_POST['bonus_id'] ?? 0 );
        $status = in_array( $_POST['status'] ?? '', [ 'pending', 'approved', 'paid' ] ) ? $_POST['status'] : 'pending';
        $notes  = sanitize_textarea_field( $_POST['admin_notes'] ?? '' );
        RZPZ_Crew_DB::update_bonus_status( $id, $status, $notes );
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew-bonus&saved=1' ) );
        exit;
    }

    public static function handle_update_boost() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_update_boost' );

        $boost_id = (int) ( $_POST['boost_id'] ?? 0 );
        if ( ( $_POST['action_type'] ?? '' ) === 'create' ) {
            $link_id   = (int) ( $_POST['link_id']        ?? 0 );
            $member_id = (int) ( $_POST['crew_member_id'] ?? 0 );
            $notes     = sanitize_textarea_field( $_POST['notes'] ?? '' );
            RZPZ_Crew_DB::insert_boost( $link_id, $member_id, $notes );
        } else {
            RZPZ_Crew_DB::update_boost( $boost_id, [
                'status'  => sanitize_text_field( $_POST['status']  ?? 'ready' ),
                'notes'   => sanitize_textarea_field( $_POST['notes']   ?? '' ),
                'ad_url'  => esc_url_raw( $_POST['ad_url'] ?? '' ),
            ] );
        }
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew-boost&saved=1' ) );
        exit;
    }

    public static function handle_save_settings() : void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_crew_save_settings' );
        $opts = [
            'default_destination_url'    => esc_url_raw( $_POST['default_destination_url'] ?? 'https://rezponz.dk/jobs/' ),
            'conversion_url'             => esc_url_raw( $_POST['conversion_url'] ?? 'https://rezponz.dk/tak-for-din-ansoegning/' ),
            'cookie_days'                => max( 1, (int) ( $_POST['cookie_days'] ?? 30 ) ),
            'boost_conversions_threshold'=> max( 1, (int) ( $_POST['boost_conversions_threshold'] ?? 1 ) ),
            'boost_ctr_threshold'        => max( 0, (float) ( $_POST['boost_ctr_threshold'] ?? 2.0 ) ),
        ];
        update_option( 'rzpz_crew_settings', $opts );
        wp_redirect( admin_url( 'admin.php?page=rezponz-crew-settings&saved=1' ) );
        exit;
    }

    // ── REST API ──────────────────────────────────────────────────────────────

    public static function register_rest() : void {
        $cap = fn() => current_user_can( 'manage_options' );
        $ns  = 'rzpz-crew/v1';

        register_rest_route( $ns, '/members', [
            'methods'             => 'GET',
            'callback'            => fn() => rest_ensure_response( RZPZ_Crew_DB::get_members() ),
            'permission_callback' => $cap,
        ] );

        register_rest_route( $ns, '/leaderboard', [
            'methods'             => 'GET',
            'callback'            => fn( $r ) => rest_ensure_response( RZPZ_Crew_DB::get_leaderboard( (int) ( $r->get_param( 'days' ) ?: 30 ) ) ),
            'permission_callback' => $cap,
        ] );

        register_rest_route( $ns, '/member/(?P<id>\d+)/stats', [
            'methods'             => 'GET',
            'callback'            => function ( WP_REST_Request $r ) {
                $id   = (int) $r->get_param( 'id' );
                $days = (int) ( $r->get_param( 'days' ) ?: 30 );
                return rest_ensure_response( [
                    'clicks'      => RZPZ_Crew_DB::get_clicks_count( $id, $days ),
                    'conversions' => RZPZ_Crew_DB::get_conversions_count( $id, $days ),
                    'links'       => RZPZ_Crew_DB::get_links( $id ),
                    'top_links'   => RZPZ_Crew_DB::get_top_links( $id ),
                ] );
            },
            'permission_callback' => $cap,
        ] );

        register_rest_route( $ns, '/recalculate-bonus', [
            'methods'             => 'POST',
            'callback'            => function ( WP_REST_Request $r ) {
                $members = RZPZ_Crew_DB::get_members( 'active' );
                $month   = gmdate( 'Y-m-01' );
                $today   = gmdate( 'Y-m-d' );
                foreach ( $members as $m ) {
                    RZPZ_Crew_DB::upsert_bonus( (int) $m['id'], $month, $today );
                }
                return rest_ensure_response( [ 'updated' => count( $members ) ] );
            },
            'permission_callback' => $cap,
        ] );

        register_rest_route( $ns, '/boost', [
            'methods'             => 'POST',
            'callback'            => function ( WP_REST_Request $r ) {
                $params    = $r->get_json_params();
                $link_id   = (int) ( $params['link_id']        ?? 0 );
                $member_id = (int) ( $params['crew_member_id'] ?? 0 );
                $notes     = sanitize_textarea_field( $params['notes'] ?? '' );
                if ( ! $link_id || ! $member_id ) {
                    return new WP_Error( 'missing_params', 'link_id and crew_member_id are required', [ 'status' => 400 ] );
                }
                $id = RZPZ_Crew_DB::insert_boost( $link_id, $member_id, $notes );
                return rest_ensure_response( [ 'boost_id' => $id ] );
            },
            'permission_callback' => $cap,
        ] );
    }
}
