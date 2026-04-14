<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_CRM_Auth
 *
 * Sikkerhedslag for RezCRM:
 * - Custom WP-rolle med minimale rettigheder
 * - TOTP MFA (RFC 6238 — Google Authenticator kompatibel)
 * - Branded login-side via shortcode [rezcrm_login]
 * - Rate limiting (3 forsøg → 15 min lockout)
 * - Audit log (alle login-events)
 * - Admin-bar + WP-menu skjules for CRM-rolle
 * - MFA-session udløber efter 8 timer
 */
class RZPZ_CRM_Auth {

    const ROLE           = 'rezcrm_user';
    const CAP            = 'rezcrm_access';
    const MFA_SECRET_KEY = 'rzcrm_mfa_secret';
    const MFA_ENABLED_KEY= 'rzcrm_mfa_enabled';
    const SESSION_HOURS  = 8;        // MFA-session levetid i timer
    const MAX_ATTEMPTS   = 3;        // Max forkerte MFA-forsøg
    const LOCKOUT_MINS   = 15;       // Lockout-varighed i minutter

    // ── Init ─────────────────────────────────────────────────────────────────

    public static function init(): void {
        // register_role() is called only on activation hook — not on every request
        add_shortcode( 'rezcrm_login', [ __CLASS__, 'render_login_shortcode' ] );

        // Intercept WP login — kræv MFA
        add_action( 'wp_login', [ __CLASS__, 'on_wp_login' ], 10, 2 );

        // Blok direkte adgang til WP admin for CRM-brugere uden gyldig MFA-session
        add_action( 'admin_init',     [ __CLASS__, 'gate_admin_access' ] );

        // Skjul WP-admin-bar og irrelevante menupunkter for CRM-brugere
        add_action( 'after_setup_theme', [ __CLASS__, 'hide_admin_bar' ] );
        add_action( 'admin_menu',        [ __CLASS__, 'restrict_admin_menu' ], 999 );

        // AJAX handlers (login flow)
        add_action( 'wp_ajax_nopriv_rzcrm_login_step1', [ __CLASS__, 'ajax_login_step1' ] );
        add_action( 'wp_ajax_nopriv_rzcrm_login_step2', [ __CLASS__, 'ajax_login_step2' ] );
        add_action( 'wp_ajax_rzcrm_mfa_setup',          [ __CLASS__, 'ajax_mfa_setup'   ] );
        add_action( 'wp_ajax_rzcrm_mfa_confirm',        [ __CLASS__, 'ajax_mfa_confirm' ] );
        add_action( 'wp_ajax_rzcrm_logout',             [ __CLASS__, 'ajax_logout'      ] );

        // Frontend enqueue (kun på sider med shortcode)
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
    }

    // ── DB: audit log tabel ──────────────────────────────────────────────────

    public static function install_audit_table(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_audit_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED DEFAULT NULL,
            username    VARCHAR(100)    DEFAULT NULL,
            event       VARCHAR(50)     NOT NULL,
            ip          VARCHAR(45)     DEFAULT NULL,
            user_agent  VARCHAR(255)    DEFAULT NULL,
            detail      TEXT            DEFAULT NULL,
            created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_event (event),
            KEY idx_created (created_at)
        ) {$c};" );
    }

    // ── Role / capability ─────────────────────────────────────────────────────

    public static function register_role(): void {
        if ( get_role( self::ROLE ) ) return;

        add_role( self::ROLE, 'CRM Bruger', [
            self::CAP      => true,
            'read'         => true,   // krævet for WP admin-adgang
            'upload_files' => true,   // nødvendig for filupload i CRM
        ] );
    }

    public static function remove_role(): void {
        remove_role( self::ROLE );
    }

    // ── TOTP: pure PHP RFC 6238 ───────────────────────────────────────────────

    /** Generer et random base32-kodet TOTP-hemmelighed */
    public static function generate_secret( int $length = 20 ): string {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $bytes  = random_bytes( $length );
        for ( $i = 0; $i < $length; $i++ ) {
            $secret .= $chars[ ord( $bytes[ $i ] ) & 31 ];
        }
        return $secret;
    }

    /** Base32 decode (RFC 4648) */
    private static function base32_decode( string $secret ): string {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper( preg_replace( '/\s/', '', $secret ) );
        $output = '';
        $v      = 0;
        $vbits  = 0;
        for ( $i = 0, $l = strlen( $secret ); $i < $l; $i++ ) {
            $pos = strpos( $chars, $secret[ $i ] );
            if ( $pos === false ) continue;
            $v      = ( $v << 5 ) | $pos;
            $vbits += 5;
            if ( $vbits >= 8 ) {
                $vbits  -= 8;
                $output .= chr( $v >> $vbits );
                $v      &= ( 1 << $vbits ) - 1;
            }
        }
        return $output;
    }

    /** Generer TOTP-kode for et givet tidstrin (0 = nu, ±1 = tolerance) */
    public static function generate_totp( string $secret, int $offset = 0 ): string {
        $time = floor( time() / 30 ) + $offset;
        // Pack som 8-byte big-endian (simulerer pack('J', $time) der kræver 64-bit)
        $time_bytes = pack( 'N', 0 ) . pack( 'N', $time & 0xFFFFFFFF );
        $key        = self::base32_decode( $secret );
        if ( empty( $key ) ) return '000000';
        $hash   = hash_hmac( 'sha1', $time_bytes, $key, true );
        $offset_byte = ord( $hash[19] ) & 0x0F;
        $code   = (
            ( ( ord( $hash[ $offset_byte ] )     & 0x7F ) << 24 ) |
            ( ( ord( $hash[ $offset_byte + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hash[ $offset_byte + 2 ] ) & 0xFF ) << 8  ) |
              ( ord( $hash[ $offset_byte + 3 ] ) & 0xFF )
        ) % 1_000_000;
        return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
    }

    /** Verificer kode med ±1 vindue (90 sekunders tolerance mod klok-drift) */
    public static function verify_totp( string $secret, string $code ): bool {
        $code = preg_replace( '/\s/', '', $code );
        if ( strlen( $code ) !== 6 || ! ctype_digit( $code ) ) return false;
        for ( $i = -1; $i <= 1; $i++ ) {
            if ( hash_equals( self::generate_totp( $secret, $i ), $code ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificer TOTP med replay-beskyttelse.
     * Gemmer den accepterede kode i et transient (90s) og afviser genbrug.
     */
    public static function verify_totp_once( int $user_id, string $secret, string $code ): bool {
        if ( ! self::verify_totp( $secret, $code ) ) return false;

        // Koden er gyldig — tjek om den allerede er brugt
        $replay_key = 'rzcrm_totp_used_' . $user_id . '_' . $code;
        if ( get_transient( $replay_key ) ) return false; // replay afvist

        // Markér som brugt i 90 sekunder (±1 vindue)
        set_transient( $replay_key, 1, 90 );
        return true;
    }

    /** Byg otpauth URI til QR-kode */
    public static function build_otp_uri( string $secret, string $email, string $issuer = 'RezCRM' ): string {
        return 'otpauth://totp/' . rawurlencode( $issuer . ':' . $email )
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode( $issuer )
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /** QR-kode URL via qrserver.com (gratis, GDPR-neutral da det kun er URL) */
    public static function qr_code_url( string $otp_uri ): string {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode( $otp_uri );
    }

    // ── Rate limiting ─────────────────────────────────────────────────────────

    private static function get_attempt_key( string $identifier ): string {
        return 'rzcrm_mfa_fail_' . hash( 'sha256', $identifier );
    }

    private static function record_failed_attempt( string $identifier ): int {
        $key      = self::get_attempt_key( $identifier );
        $attempts = (int) get_transient( $key ) + 1;
        set_transient( $key, $attempts, self::LOCKOUT_MINS * MINUTE_IN_SECONDS );
        return $attempts;
    }

    private static function is_locked_out( string $identifier ): bool {
        return (int) get_transient( self::get_attempt_key( $identifier ) ) >= self::MAX_ATTEMPTS;
    }

    private static function clear_attempts( string $identifier ): void {
        delete_transient( self::get_attempt_key( $identifier ) );
    }

    // ── MFA session ───────────────────────────────────────────────────────────

    /** Gem at denne bruger har gennemført MFA-verificering */
    public static function set_mfa_session( int $user_id ): void {
        $token = bin2hex( random_bytes( 32 ) );
        update_user_meta( $user_id, 'rzcrm_mfa_session', $token );
        update_user_meta( $user_id, 'rzcrm_mfa_session_at', time() );
    }

    /** Er den aktuelle session gyldig (< SESSION_HOURS gammel)? */
    public static function has_valid_mfa_session( int $user_id ): bool {
        $at = (int) get_user_meta( $user_id, 'rzcrm_mfa_session_at', true );
        if ( ! $at ) return false;
        return ( time() - $at ) < ( self::SESSION_HOURS * HOUR_IN_SECONDS );
    }

    // ── Login hooks ───────────────────────────────────────────────────────────

    /**
     * Køres ved standard WP-login.
     * Hvis brugeren har CRM-rollen og MFA er aktiveret:
     * Log dem UD igen, gem "første faktor OK" i transient, redirect til MFA-trin.
     */
    public static function on_wp_login( string $user_login, WP_User $user ): void {
        if ( ! $user->has_cap( self::CAP ) ) return;

        $mfa_enabled = get_user_meta( $user->ID, self::MFA_ENABLED_KEY, true );

        // Hvis MFA ikke er sat op endnu → omdirigér til setup
        if ( ! $mfa_enabled ) {
            // Giv adgang til setup-siden (MFA er ikke tvunget før det er konfigureret)
            self::set_mfa_session( $user->ID );
            self::log_audit( $user->ID, $user_login, 'login_no_mfa', 'MFA ikke konfigureret endnu' );
            return;
        }

        // MFA er aktiveret — gem "step 1 OK" i transient og log ud
        $token = bin2hex( random_bytes( 24 ) );
        set_transient( 'rzcrm_mfa_pending_' . $token, $user->ID, 5 * MINUTE_IN_SECONDS );

        wp_logout();
        self::log_audit( $user->ID, $user_login, 'login_step1_ok', 'Omdirigerer til MFA' );

        $login_url = self::get_login_url();
        wp_safe_redirect( add_query_arg( [ 'mfa' => 1, 'tok' => $token ], $login_url ) );
        exit;
    }

    /** Blokér adgang til WP admin hvis MFA ikke er gennemført eller konto er deaktiveret */
    public static function gate_admin_access(): void {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->ID ) return;
        if ( ! $user->has_cap( self::CAP ) ) return;

        // Tillad altid AJAX
        if ( wp_doing_ajax() ) return;

        // Blokér deaktiverede konti
        $meta_exists = get_user_meta( $user->ID, 'rzcrm_active', false );
        $is_active   = ! empty( $meta_exists ) ? (bool) get_user_meta( $user->ID, 'rzcrm_active', true ) : true;
        if ( ! $is_active ) {
            wp_logout();
            self::log_audit( $user->ID, $user->user_login, 'login_blocked', 'Konto deaktiveret' );
            wp_safe_redirect( add_query_arg( 'reason', 'deactivated', self::get_login_url() ) );
            exit;
        }

        $mfa_enabled = get_user_meta( $user->ID, self::MFA_ENABLED_KEY, true );

        if ( $mfa_enabled && ! self::has_valid_mfa_session( $user->ID ) ) {
            wp_logout();
            self::log_audit( $user->ID, $user->user_login, 'session_expired', 'MFA-session udløbet' );
            wp_safe_redirect( add_query_arg( 'reason', 'session_expired', self::get_login_url() ) );
            exit;
        }
    }

    /** Find URL til CRM login-siden (første side med [rezcrm_login] shortcode) */
    public static function get_login_url(): string {
        $cached = wp_cache_get( 'rzcrm_login_page_id', 'rzpz_crm' );
        if ( $cached !== false ) {
            return $cached ? get_permalink( (int) $cached ) : wp_login_url();
        }
        global $wpdb;
        $page_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_content LIKE %s
             AND post_status = 'publish' LIMIT 1",
            '%[rezcrm_login%'
        ) );
        wp_cache_set( 'rzcrm_login_page_id', $page_id ?: 0, 'rzpz_crm', HOUR_IN_SECONDS );
        return $page_id ? get_permalink( (int) $page_id ) : wp_login_url();
    }

    // ── Admin menu restriction ────────────────────────────────────────────────

    public static function hide_admin_bar(): void {
        $user = wp_get_current_user();
        if ( $user && $user->has_cap( self::CAP ) && ! $user->has_cap( 'manage_options' ) ) {
            show_admin_bar( false );
        }
    }

    public static function restrict_admin_menu(): void {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->has_cap( self::CAP ) ) return;
        if ( $user->has_cap( 'manage_options' ) ) return; // admins ser alt

        // Fjern ALT undtagen CRM-sider
        global $menu, $submenu;
        $allowed_slugs = [ 'rzpa-dashboard', 'rzpa-rezcrm', 'rzpa-rezcrm-forms', 'rzpa-section-crew' ];

        foreach ( $menu as $key => $item ) {
            if ( ! in_array( $item[2] ?? '', $allowed_slugs, true ) ) {
                remove_menu_page( $item[2] ?? '' );
            }
        }

        // Fjern alle submenuer under rzpa-dashboard undtagen CRM-sider
        if ( isset( $submenu['rzpa-dashboard'] ) ) {
            foreach ( $submenu['rzpa-dashboard'] as $k => $sub ) {
                if ( ! in_array( $sub[2] ?? '', $allowed_slugs, true ) ) {
                    unset( $submenu['rzpa-dashboard'][ $k ] );
                }
            }
        }
    }

    // ── AJAX: Login step 1 (brugernavn + kodeord) ────────────────────────────

    public static function ajax_login_step1(): void {
        check_ajax_referer( 'rzcrm_login_nonce', 'nonce' );

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $password = $_POST['password'] ?? '';
        $ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

        // Rate limit på IP
        if ( self::is_locked_out( 'ip_' . $ip ) ) {
            wp_send_json_error( [ 'message' => 'For mange forsøg. Vent ' . self::LOCKOUT_MINS . ' minutter.' ], 429 );
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            $attempts = self::record_failed_attempt( 'ip_' . $ip );
            self::log_audit( null, $username, 'login_failed', 'Forkert brugernavn/kodeord. Forsøg: ' . $attempts );
            $remaining = self::MAX_ATTEMPTS - $attempts;
            $msg = $remaining > 0
                ? 'Forkert brugernavn eller kodeord. ' . $remaining . ' forsøg tilbage.'
                : 'Kontoen er låst i ' . self::LOCKOUT_MINS . ' minutter.';
            wp_send_json_error( [ 'message' => $msg ], 401 );
        }

        if ( ! $user->has_cap( self::CAP ) ) {
            self::log_audit( $user->ID, $username, 'login_no_access', 'Bruger har ikke CRM-adgang' );
            wp_send_json_error( [ 'message' => 'Du har ikke adgang til RezCRM.' ], 403 );
        }

        // Blokér deaktiverede konti
        $meta_exists = get_user_meta( $user->ID, 'rzcrm_active', false );
        $is_active   = ! empty( $meta_exists ) ? (bool) get_user_meta( $user->ID, 'rzcrm_active', true ) : true;
        if ( ! $is_active ) {
            self::log_audit( $user->ID, $username, 'login_blocked', 'Konto deaktiveret' );
            wp_send_json_error( [ 'message' => 'Din konto er deaktiveret. Kontakt en administrator.' ], 403 );
        }

        self::clear_attempts( 'ip_' . $ip );

        $mfa_enabled = get_user_meta( $user->ID, self::MFA_ENABLED_KEY, true );
        $token       = bin2hex( random_bytes( 24 ) );
        set_transient( 'rzcrm_mfa_pending_' . $token, $user->ID, 5 * MINUTE_IN_SECONDS );

        self::log_audit( $user->ID, $username, 'login_step1_ok', 'Kodeord OK — afventer MFA' );

        if ( ! $mfa_enabled ) {
            // MFA ikke sat op — send til setup
            wp_send_json_success( [ 'step' => 'setup', 'token' => $token ] );
        }

        wp_send_json_success( [ 'step' => 'mfa', 'token' => $token ] );
    }

    // ── AJAX: Login step 2 (MFA-kode) ────────────────────────────────────────

    public static function ajax_login_step2(): void {
        check_ajax_referer( 'rzcrm_login_nonce', 'nonce' );

        $token = sanitize_text_field( $_POST['token'] ?? '' );
        $code  = preg_replace( '/\s/', '', $_POST['code'] ?? '' );
        $ip    = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

        if ( self::is_locked_out( 'mfa_' . $token ) ) {
            wp_send_json_error( [ 'message' => 'For mange forkerte koder. Vent ' . self::LOCKOUT_MINS . ' minutter.' ], 429 );
        }

        $user_id = (int) get_transient( 'rzcrm_mfa_pending_' . $token );
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Session udløbet. Log ind igen.' ], 401 );
        }

        $secret = get_user_meta( $user_id, self::MFA_SECRET_KEY, true );
        if ( ! $secret || ! self::verify_totp_once( $user_id, $secret, $code ) ) {
            $attempts = self::record_failed_attempt( 'mfa_' . $token );
            $user     = get_userdata( $user_id );
            self::log_audit( $user_id, $user->user_login ?? '', 'mfa_failed', 'Forkert MFA-kode. Forsøg: ' . $attempts );
            $remaining = self::MAX_ATTEMPTS - $attempts;
            $msg = $remaining > 0
                ? 'Forkert kode. ' . $remaining . ' forsøg tilbage.'
                : 'Kontoen er låst. Prøv igen om ' . self::LOCKOUT_MINS . ' minutter.';
            wp_send_json_error( [ 'message' => $msg ], 401 );
        }

        // ✅ MFA godkendt
        delete_transient( 'rzcrm_mfa_pending_' . $token );
        self::clear_attempts( 'mfa_' . $token );
        self::clear_attempts( 'ip_' . $ip );

        // Opret WP auth session
        wp_set_auth_cookie( $user_id, false );
        self::set_mfa_session( $user_id );

        $user = get_userdata( $user_id );
        self::log_audit( $user_id, $user->user_login ?? '', 'login_success', 'Login + MFA godkendt' );

        // Redirect til CRM
        $redirect = admin_url( 'admin.php?page=rzpa-rezcrm' );
        wp_send_json_success( [ 'redirect' => $redirect ] );
    }

    // ── AJAX: MFA setup (generer hemmelighed) ─────────────────────────────────

    public static function ajax_mfa_setup(): void {
        check_ajax_referer( 'rzcrm_login_nonce', 'nonce' );

        $token   = sanitize_text_field( $_POST['token'] ?? '' );
        $user_id = (int) get_transient( 'rzcrm_mfa_pending_' . $token );

        if ( ! $user_id ) {
            // Tjek om bruger er logget ind (allerede MFA-godkendt admin der sætter det op)
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                wp_send_json_error( [ 'message' => 'Session ugyldig' ], 401 );
            }
        }

        $user   = get_userdata( $user_id );
        $secret = get_user_meta( $user_id, self::MFA_SECRET_KEY, true );

        // Generer ny hemmelighed hvis ingen eksisterer
        if ( ! $secret ) {
            $secret = self::generate_secret();
            update_user_meta( $user_id, self::MFA_SECRET_KEY, $secret );
        }

        $otp_uri = self::build_otp_uri( $secret, $user->user_email );
        $qr_url  = self::qr_code_url( $otp_uri );

        wp_send_json_success( [
            'secret'  => $secret,
            'qr_url'  => $qr_url,
            'otp_uri' => $otp_uri,
        ] );
    }

    // ── AJAX: MFA bekræft (første gang — aktivér MFA) ────────────────────────

    public static function ajax_mfa_confirm(): void {
        check_ajax_referer( 'rzcrm_login_nonce', 'nonce' );

        $token   = sanitize_text_field( $_POST['token'] ?? '' );
        $code    = preg_replace( '/\s/', '', $_POST['code'] ?? '' );
        $user_id = (int) get_transient( 'rzcrm_mfa_pending_' . $token );

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                wp_send_json_error( [ 'message' => 'Session ugyldig' ], 401 );
            }
        }

        // Rate-limit setup-bekræftelsen (brute-force beskyttelse)
        $rate_key = 'setup_' . $user_id;
        if ( self::is_locked_out( $rate_key ) ) {
            wp_send_json_error( [ 'message' => 'For mange forsøg. Vent ' . self::LOCKOUT_MINS . ' minutter.' ], 429 );
        }

        $secret = get_user_meta( $user_id, self::MFA_SECRET_KEY, true );
        if ( ! $secret || ! self::verify_totp_once( $user_id, $secret, $code ) ) {
            self::record_failed_attempt( $rate_key );
            wp_send_json_error( [ 'message' => 'Forkert kode — prøv igen.' ], 401 );
        }
        self::clear_attempts( $rate_key );

        // Aktivér MFA permanent for denne bruger
        update_user_meta( $user_id, self::MFA_ENABLED_KEY, '1' );

        if ( get_transient( 'rzcrm_mfa_pending_' . $token ) ) {
            delete_transient( 'rzcrm_mfa_pending_' . $token );
            wp_set_auth_cookie( $user_id, false );
            self::set_mfa_session( $user_id );
        }

        $user = get_userdata( $user_id );
        self::log_audit( $user_id, $user->user_login ?? '', 'mfa_activated', 'MFA aktiveret og bekræftet' );

        wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=rzpa-rezcrm' ) ] );
    }

    // ── AJAX: Logout ─────────────────────────────────────────────────────────

    public static function ajax_logout(): void {
        check_ajax_referer( 'rzcrm_login_nonce', 'nonce' );
        $user = wp_get_current_user();
        if ( $user && $user->ID ) {
            self::log_audit( $user->ID, $user->user_login, 'logout', 'Manuel logout' );
            delete_user_meta( $user->ID, 'rzcrm_mfa_session' );
            delete_user_meta( $user->ID, 'rzcrm_mfa_session_at' );
        }
        wp_logout();
        wp_send_json_success( [ 'redirect' => self::get_login_url() ] );
    }

    // ── Audit log ─────────────────────────────────────────────────────────────

    public static function log_audit( ?int $user_id, string $username, string $event, string $detail = '' ): void {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'rzpz_crm_audit_log', [
            'user_id'    => $user_id,
            'username'   => sanitize_text_field( $username ),
            'event'      => sanitize_key( $event ),
            'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'user_agent' => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ),
            'detail'     => sanitize_textarea_field( $detail ),
        ] );
    }

    public static function get_audit_log( int $user_id = 0, int $limit = 50, int $offset = 0, string $action = '' ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'rzpz_crm_audit_log';
        $select = "SELECT id, user_id, username AS user_login, event AS action, ip, user_agent, detail AS context, created_at FROM {$table}";
        $where  = [];
        $args   = [];

        if ( $user_id ) { $where[] = 'user_id = %d';  $args[] = $user_id; }
        if ( $action  ) { $where[] = 'event = %s';    $args[] = $action;  }

        $sql  = $select . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' );
        $sql .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $args[] = $limit;
        $args[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) ?: [];
    }

    // ── Frontend: enqueue ─────────────────────────────────────────────────────

    public static function enqueue_frontend(): void {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'rezcrm_login' ) ) return;
        // Definer DONOTCACHEPAGE FØR vi enqueuer noget — så cache-plugins ikke cacher nonce-output
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );

        wp_enqueue_style( 'rzpz-crm-auth', RZPA_URL . 'modules/rezcrm/assets/rezcrm-auth.css', [], RZPA_VERSION );
        wp_enqueue_script( 'rzpz-crm-auth', RZPA_URL . 'modules/rezcrm/assets/rezcrm-auth.js', [], RZPA_VERSION, true );
        // Leverer konfiguration under det navn JS-filen forventer (RZCRM_AUTH)
        wp_localize_script( 'rzpz-crm-auth', 'RZCRM_AUTH', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rzcrm_login_nonce' ),
            'redirect' => admin_url( 'admin.php?page=rzpa-rezcrm' ),
        ] );
    }

    // ── Shortcode: [rezcrm_login] ─────────────────────────────────────────────

    public static function render_login_shortcode(): string {
        // Allerede logget ind med gyldig MFA → redirect direkte
        $user = wp_get_current_user();
        if ( $user && $user->ID && $user->has_cap( self::CAP ) ) {
            $mfa_enabled = get_user_meta( $user->ID, self::MFA_ENABLED_KEY, true );
            if ( ! $mfa_enabled || self::has_valid_mfa_session( $user->ID ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=rzpa-rezcrm' ) );
                exit;
            }
        }

        $reason   = sanitize_key( $_GET['reason'] ?? '' );
        $mfa_step = isset( $_GET['mfa'] );
        $tok      = sanitize_text_field( $_GET['tok'] ?? '' );

        ob_start();
        include __DIR__ . '/views/crm-login.php';
        return ob_get_clean();
    }
}
