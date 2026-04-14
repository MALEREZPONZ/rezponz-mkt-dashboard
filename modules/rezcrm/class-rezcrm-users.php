<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_CRM_Users
 *
 * User management for RezCRM: create/deactivate rezcrm_user accounts,
 * reset MFA, view audit log. Admin-only (manage_options).
 */
class RZPZ_CRM_Users {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    // ── REST routes ──────────────────────────────────────────────────────────

    public static function register_routes(): void {
        $ns  = 'rzpa/v1';
        $cap = fn() => current_user_can( 'manage_options' );

        register_rest_route( $ns, 'crm/users',                [ 'methods' => 'GET',    'callback' => [ __CLASS__, 'api_list' ],       'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/users',                [ 'methods' => 'POST',   'callback' => [ __CLASS__, 'api_create' ],     'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/users/(?P<id>\d+)',    [ 'methods' => 'PUT',    'callback' => [ __CLASS__, 'api_update' ],     'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/users/(?P<id>\d+)',    [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'api_deactivate' ], 'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/users/(?P<id>\d+)/reset-mfa', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'api_reset_mfa' ],
            'permission_callback' => $cap,
        ] );
        register_rest_route( $ns, 'crm/audit', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'api_audit' ], 'permission_callback' => $cap ] );
    }

    // ── REST: list users ─────────────────────────────────────────────────────

    public static function api_list(): WP_REST_Response {
        $users = get_users( [
            'role__in' => [ RZPZ_CRM_Auth::ROLE, 'administrator' ],
            'fields'   => 'all',
            'orderby'  => 'login',
        ] );

        $result = array_map( function( WP_User $u ) {
            $has_mfa    = (bool) get_user_meta( $u->ID, RZPZ_CRM_Auth::MFA_SECRET_KEY, true );
            $last_login = get_user_meta( $u->ID, 'rzcrm_mfa_session_at', true );
            $active     = (bool) get_user_meta( $u->ID, 'rzcrm_active', true );
            // New users don't have the meta yet — default to active
            if ( get_user_meta( $u->ID, 'rzcrm_active', false ) === [] ) $active = true;

            return [
                'id'            => $u->ID,
                'login'         => $u->user_login,
                'email'         => $u->user_email,
                'display_name'  => $u->display_name,
                'roles'         => $u->roles,
                'has_mfa'       => $has_mfa,
                'last_login'    => $last_login ? (int) $last_login : null,
                'active'        => $active,
                'is_admin'      => in_array( 'administrator', $u->roles, true ),
            ];
        }, $users );

        return new WP_REST_Response( $result, 200 );
    }

    // ── REST: create user ────────────────────────────────────────────────────

    public static function api_create( WP_REST_Request $req ): WP_REST_Response {
        $params       = $req->get_json_params();
        $login        = sanitize_user( $params['login']        ?? '' );
        $email        = sanitize_email( $params['email']       ?? '' );
        $display_name = sanitize_text_field( $params['display_name'] ?? $login );
        $password     = $params['password'] ?? wp_generate_password( 16, true, true );
        $role         = sanitize_key( $params['role'] ?? RZPZ_CRM_Auth::ROLE );

        // Only allow rezcrm_user role from this endpoint (admins are created normally)
        if ( ! in_array( $role, [ RZPZ_CRM_Auth::ROLE ], true ) ) {
            $role = RZPZ_CRM_Auth::ROLE;
        }

        if ( ! $login || ! $email ) {
            return new WP_REST_Response( [ 'message' => 'Login og email er påkrævet' ], 400 );
        }
        if ( ! is_email( $email ) ) {
            return new WP_REST_Response( [ 'message' => 'Ugyldig email-adresse' ], 400 );
        }
        if ( username_exists( $login ) ) {
            return new WP_REST_Response( [ 'message' => 'Brugernavnet er allerede i brug' ], 409 );
        }
        if ( email_exists( $email ) ) {
            return new WP_REST_Response( [ 'message' => 'Email-adressen er allerede i brug' ], 409 );
        }

        $user_id = wp_insert_user( [
            'user_login'   => $login,
            'user_email'   => $email,
            'display_name' => $display_name,
            'user_pass'    => $password,
            'role'         => $role,
        ] );

        if ( is_wp_error( $user_id ) ) {
            return new WP_REST_Response( [ 'message' => $user_id->get_error_message() ], 500 );
        }

        update_user_meta( $user_id, 'rzcrm_active', 1 );

        $creator = get_user_by( 'id', get_current_user_id() );
        RZPZ_CRM_Auth::log_audit( $user_id, $login, 'user_created',
            'Oprettet af ' . ( $creator ? $creator->user_login : 'system' ) . ' · rolle: ' . $role );

        // Send welcome email
        self::send_welcome_email( $user_id, $password );

        return new WP_REST_Response( [ 'id' => $user_id, 'password_sent' => true ], 201 );
    }

    // ── REST: update user ────────────────────────────────────────────────────

    public static function api_update( WP_REST_Request $req ): WP_REST_Response {
        $user_id = (int) $req->get_param( 'id' );
        $user    = get_user_by( 'id', $user_id );
        if ( ! $user ) return new WP_REST_Response( [ 'message' => 'Bruger ikke fundet' ], 404 );

        $params = $req->get_json_params();
        $data   = [ 'ID' => $user_id ];

        if ( ! empty( $params['email'] ) && is_email( $params['email'] ) ) {
            $data['user_email'] = sanitize_email( $params['email'] );
        }
        if ( ! empty( $params['display_name'] ) ) {
            $data['display_name'] = sanitize_text_field( $params['display_name'] );
        }
        if ( ! empty( $params['password'] ) && strlen( $params['password'] ) >= 8 ) {
            $data['user_pass'] = $params['password'];
        }

        $result = wp_update_user( $data );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 500 );
        }

        RZPZ_CRM_Auth::log_audit( $user_id, $user->user_login, 'user_updated',
            'Opdateret af bruger #' . get_current_user_id() );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── REST: deactivate user ────────────────────────────────────────────────

    public static function api_deactivate( WP_REST_Request $req ): WP_REST_Response {
        $user_id = (int) $req->get_param( 'id' );

        // Cannot deactivate self
        if ( $user_id === get_current_user_id() ) {
            return new WP_REST_Response( [ 'message' => 'Du kan ikke deaktivere din egen konto' ], 400 );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) return new WP_REST_Response( [ 'message' => 'Bruger ikke fundet' ], 404 );

        // Toggle active state
        $currently_active = get_user_meta( $user_id, 'rzcrm_active', true );
        $new_state        = $currently_active ? 0 : 1;
        update_user_meta( $user_id, 'rzcrm_active', $new_state );

        // Clear sessions when deactivating
        if ( ! $new_state ) {
            delete_user_meta( $user_id, 'rzcrm_mfa_session_at' );
            delete_user_meta( $user_id, 'rzcrm_mfa_temp_token' );
        }

        RZPZ_CRM_Auth::log_audit( $user_id, $user->user_login,
            $new_state ? 'user_activated' : 'user_deactivated',
            'Ændret af bruger #' . get_current_user_id() );

        return new WP_REST_Response( [ 'ok' => true, 'active' => (bool) $new_state ], 200 );
    }

    // ── REST: reset MFA ───────────────────────────────────────────────────────

    public static function api_reset_mfa( WP_REST_Request $req ): WP_REST_Response {
        $user_id = (int) $req->get_param( 'id' );
        $user    = get_user_by( 'id', $user_id );
        if ( ! $user ) return new WP_REST_Response( [ 'message' => 'Bruger ikke fundet' ], 404 );

        // Clear all MFA data — user must set up again on next login
        delete_user_meta( $user_id, RZPZ_CRM_Auth::MFA_SECRET_KEY );
        delete_user_meta( $user_id, RZPZ_CRM_Auth::MFA_ENABLED_KEY );
        delete_user_meta( $user_id, 'rzcrm_mfa_session' );
        delete_user_meta( $user_id, 'rzcrm_mfa_session_at' );

        RZPZ_CRM_Auth::log_audit( $user_id, $user->user_login, 'mfa_reset',
            'Nulstillet af bruger #' . get_current_user_id() );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── REST: audit log ──────────────────────────────────────────────────────

    public static function api_audit( WP_REST_Request $req ): WP_REST_Response {
        $user_id = (int) ( $req->get_param( 'user_id' ) ?? 0 );
        $action  = sanitize_key( $req->get_param( 'action' ) ?? '' );
        $limit   = min( (int) ( $req->get_param( 'limit' ) ?? 50 ), 200 );
        $offset  = (int) ( $req->get_param( 'offset' ) ?? 0 );

        $rows = RZPZ_CRM_Auth::get_audit_log( $user_id, $limit, $offset, $action );
        return new WP_REST_Response( $rows, 200 );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function send_welcome_email( int $user_id, string $password ): void {
        $user        = get_user_by( 'id', $user_id );
        $login_url   = home_url( '/rezcrm/' );  // assumes shortcode at /rezcrm/
        $site_name   = get_bloginfo( 'name' );

        $subject = "Velkomstkonto til RezCRM – {$site_name}";
        $body    = "Hej {$user->display_name},\n\n"
                 . "Din RezCRM-konto er nu oprettet.\n\n"
                 . "Log ind her: {$login_url}\n"
                 . "Brugernavn: {$user->user_login}\n"
                 . "Adgangskode: {$password}\n\n"
                 . "Du vil blive bedt om at opsætte to-faktor-godkendelse (2FA) første gang du logger ind.\n\n"
                 . "Med venlig hilsen\n{$site_name}";

        wp_mail( $user->user_email, $subject, $body );
    }
}
