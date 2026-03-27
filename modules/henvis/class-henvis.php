<?php
/**
 * Henvis Din Ven – Module
 * Handles referral form (shortcode), DB storage, emails and admin view.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPZ_Henvis {

    const DB_VERSION_KEY = 'rzpz_henvis_db_version';
    const DB_VERSION     = '1';

    const MANAGERS = [
        'kasper_telenor'    => [ 'label' => 'Kasper – Telenor',   'email' => 'kapj@rezponz.dk',  'name' => 'Kasper' ],
        'ahmad_telenor'     => [ 'label' => 'Ahmad – Telenor',    'email' => 'ahre@rezponz.dk',  'name' => 'Ahmad' ],
        'dana_norlys'       => [ 'label' => 'Dana – Norlys',      'email' => 'dham@rezponz.dk',  'name' => 'Dana' ],
        'jonas_norlys'      => [ 'label' => 'Jonas – Norlys',     'email' => 'joli@rezponz.dk',  'name' => 'Jonas' ],
        'rasmus_norlys'     => [ 'label' => 'Rasmus – Norlys',    'email' => 'roj@rezponz.dk',   'name' => 'Rasmus' ],
        'nicklas_nrgi'      => [ 'label' => 'Nicklas – NRGI',     'email' => 'nli@rezponz.dk',   'name' => 'Nicklas' ],
        'alexander_cbb'     => [ 'label' => 'Alexander – CBB',    'email' => 'alww@rezponz.dk',  'name' => 'Alexander' ],
    ];

    // ── Boot ────────────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'init', [ __CLASS__, 'maybe_install_db' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_shortcode( 'rezponz_henvis_ven', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
    }

    public static function maybe_install_db() {
        if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
            self::install_db();
        }
    }

    // ── Database ────────────────────────────────────────────────────────────────

    public static function install_db() {
        global $wpdb;
        $table   = $wpdb->prefix . 'rzpz_referrals';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referrer_name   VARCHAR(200) NOT NULL,
            referrer_email  VARCHAR(200) NOT NULL,
            referrer_phone  VARCHAR(50)  NOT NULL DEFAULT '',
            friend_name     VARCHAR(200) NOT NULL,
            friend_email    VARCHAR(200) NOT NULL,
            friend_phone    VARCHAR(50)  NOT NULL DEFAULT '',
            manager_key     VARCHAR(50)  NOT NULL DEFAULT '',
            submitted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status          VARCHAR(50)  NOT NULL DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY referrer_email (referrer_email),
            KEY manager_key (manager_key),
            KEY submitted_at (submitted_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    // ── Admin menu ──────────────────────────────────────────────────────────────

    public static function add_admin_menu() {
        add_submenu_page(
            'rzpa-dashboard',
            __( 'Henvisninger', 'rezponz-analytics' ),
            __( 'Henvisninger', 'rezponz-analytics' ),
            'manage_options',
            'rzpz-henvis',
            [ __CLASS__, 'render_admin' ]
        );
    }

    public static function render_admin() {
        $view = RZPA_DIR . 'modules/henvis/views/admin-henvis-list.php';
        if ( file_exists( $view ) ) include $view;
    }

    // ── Frontend assets ─────────────────────────────────────────────────────────

    public static function enqueue_frontend() {
        if ( ! is_page() ) return;
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'rezponz_henvis_ven' ) ) {
            wp_enqueue_style(
                'rzpz-henvis-frontend',
                RZPA_URL . 'modules/henvis/assets/henvis-frontend.css',
                [],
                RZPA_VERSION
            );
            wp_enqueue_script(
                'rzpz-henvis-frontend',
                RZPA_URL . 'modules/henvis/assets/henvis-frontend.js',
                [ 'jquery' ],
                RZPA_VERSION,
                true
            );
        }
    }

    // ── Shortcode ───────────────────────────────────────────────────────────────

    public static function shortcode( $atts ) {
        ob_start();

        $result  = null;
        $captcha_ok = false;

        // ── Handle form submission ──────────────────────────────────────────────
        if ( isset( $_POST['rzpz_henvis_submit'] ) ) {
            if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'rzpz_henvis_submit' ) ) {
                $result = [ 'error' => __( 'Sikkerhedstjek fejlede. Prøv igen.', 'rezponz-analytics' ) ];
            } else {
                // Verify captcha
                $expected = intval( $_POST['rzpz_captcha_expected'] ?? 0 );
                $provided = intval( $_POST['rzpz_captcha_answer']   ?? -999 );
                $hash_in  = hash_hmac( 'sha256', (string) $expected, wp_salt( 'auth' ) );
                $hash_exp = sanitize_text_field( $_POST['rzpz_captcha_hash'] ?? '' );

                if ( ! hash_equals( $hash_in, $hash_exp ) || $provided !== $expected ) {
                    $result = [ 'error' => __( 'Forkert svar på menneskeverifikation. Prøv igen.', 'rezponz-analytics' ) ];
                } else {
                    $captcha_ok = true;
                    $result = self::process_form( $_POST );
                }
            }
        }

        // ── Generate CAPTCHA numbers ────────────────────────────────────────────
        $a        = wp_rand( 2, 9 );
        $b        = wp_rand( 2, 9 );
        $expected = $a + $b;
        $hash     = hash_hmac( 'sha256', (string) $expected, wp_salt( 'auth' ) );

        include RZPA_DIR . 'modules/henvis/views/frontend-form.php';

        return ob_get_clean();
    }

    // ── Process form ────────────────────────────────────────────────────────────

    private static function process_form( $post ) {
        $referrer_name  = sanitize_text_field( $post['referrer_name']  ?? '' );
        $referrer_email = sanitize_email(      $post['referrer_email'] ?? '' );
        $referrer_phone = sanitize_text_field( $post['referrer_phone'] ?? '' );
        $friend_name    = sanitize_text_field( $post['friend_name']    ?? '' );
        $friend_email   = sanitize_email(      $post['friend_email']   ?? '' );
        $friend_phone   = sanitize_text_field( $post['friend_phone']   ?? '' );
        $manager_key    = sanitize_key(        $post['manager_key']    ?? '' );
        $consent        = ! empty( $post['rzpz_consent'] );

        // Validate
        if ( ! $referrer_name || ! $referrer_email || ! $friend_name || ! $friend_email ) {
            return [ 'error' => __( 'Udfyld venligst alle påkrævede felter.', 'rezponz-analytics' ) ];
        }
        if ( ! is_email( $referrer_email ) || ! is_email( $friend_email ) ) {
            return [ 'error' => __( 'En eller begge email-adresser er ugyldige.', 'rezponz-analytics' ) ];
        }
        if ( ! isset( self::MANAGERS[ $manager_key ] ) ) {
            return [ 'error' => __( 'Vælg venligst en Senior Manager.', 'rezponz-analytics' ) ];
        }
        if ( ! $consent ) {
            return [ 'error' => __( 'Du skal bekræfte at din ven er okay med at blive kontaktet.', 'rezponz-analytics' ) ];
        }

        $manager = self::MANAGERS[ $manager_key ];

        // Store in DB
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'rzpz_referrals',
            [
                'referrer_name'  => $referrer_name,
                'referrer_email' => $referrer_email,
                'referrer_phone' => $referrer_phone,
                'friend_name'    => $friend_name,
                'friend_email'   => $friend_email,
                'friend_phone'   => $friend_phone,
                'manager_key'    => $manager_key,
                'submitted_at'   => current_time( 'mysql' ),
                'status'         => 'pending',
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        // Send emails
        self::send_emails( $referrer_name, $referrer_email, $friend_name, $friend_email, $manager );

        return [ 'success' => true ];
    }

    // ── Emails ──────────────────────────────────────────────────────────────────

    private static function send_emails( $referrer_name, $referrer_email, $friend_name, $friend_email, $manager ) {
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $from    = 'Rezponz Marketing Platform <no-reply@rezponz.dk>';
        $headers[] = 'From: ' . $from;

        // Email 1 – To Senior Manager
        $subject1 = sprintf( '🙌 %s har lige henvist en ven til Rezponz!', $referrer_name );
        $body1 = self::email_wrap( "
            <p>Hej {$manager['name']}!</p>
            <p>Gode nyheder – <strong>{$referrer_name}</strong> har netop henvist <strong>{$friend_name}</strong> til at søge en stilling hos Rezponz. Det er præcis den slags engagement, der gør vores team stærkt! 💪</p>
            <p><strong>{$friend_name}</strong> er nu informeret og har fået et link til at søge. Din opgave er nu at tage kontakten til vores medarbejder og max motivér dem. 🚀</p>
            <p>Bedste hilsner,<br>Rezponz Marketing Platform</p>
        " );
        wp_mail( $manager['email'], $subject1, $body1, $headers );

        // Email 2 – To the friend
        $subject2 = sprintf( '👋 %s tror på dig – søg en stilling hos Rezponz!', $referrer_name );
        $body2 = self::email_wrap( "
            <p>Hej {$friend_name}!</p>
            <p>Du er lige blevet henvist af <strong>{$referrer_name}</strong>, som mener at du ville passe perfekt ind hos os hos <strong>Rezponz</strong>.</p>
            <p>Det er ikke tilfældigt – når en af vores egne peger på dig, så betyder det noget. Og vi vil rigtig gerne lære dig at kende.</p>
            <p>Hos Rezponz arbejder vi med salg på vegne af nogle af Danmarks største brands. Vi er et ungt, ambitiøst team, der tror på at de rigtige mennesker kan nå langt – med den rette støtte og de rette muligheder.</p>
            <p><strong>Er du klar til at tage skridtet?</strong></p>
            <p>👉 <a href=\"https://rezponz.dk/karriere-stillinger/\" style=\"color:#CCFF00;\">Søg en stilling her</a></p>
            <p>Vi glæder os til at høre fra dig!</p>
            <p>Bedste hilsner,<br>Rezponz – Karriere &amp; Rekruttering</p>
        " );
        wp_mail( $friend_email, $subject2, $body2, $headers );

        // Email 3 – To the referrer
        $subject3 = '✅ Tak for din henvisning – vi tager det herfra!';
        $body3 = self::email_wrap( "
            <p>Hej {$referrer_name}!</p>
            <p>Tusind tak for at du henviste <strong>{$friend_name}</strong> til Rezponz. Det sætter vi stor pris på! 🙌</p>
            <p>Vi har nu sendt <strong>{$friend_name}</strong> en mail med et link til vores ansøgningsskema, og din Senior Manager <strong>{$manager['name']}</strong> er også blevet notificeret og klar til at følge op.</p>
            <p>Du behøver ikke gøre mere – men hvis du vil give din ven et ekstra personligt skub, er det aldrig forkert. 😊</p>
            <p>Husk: Hvis din ven får tilbudt en stilling hos Rezponz, udløser det en <strong>bonus til dig på 500 kr.</strong></p>
            <p>Godt gået – og tak fordi du tror på Rezponz!</p>
            <p>Bedste hilsner,<br>Rezponz Marketing Platform</p>
        " );
        wp_mail( $referrer_email, $subject3, $body3, $headers );
    }

    private static function email_wrap( string $inner ): string {
        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0d0d0d;color:#e0e0e0;padding:24px;">
            <div style="max-width:600px;margin:0 auto;background:#1a1a1a;border-radius:12px;padding:32px;border:1px solid #333;">
                <div style="margin-bottom:24px;">
                    <span style="font-size:22px;font-weight:bold;color:#CCFF00;">rezponz</span>
                    <span style="color:#888;font-size:12px;margin-left:8px;">Marketing Platform</span>
                </div>
                ' . $inner . '
            </div>
        </body></html>';
    }
}
