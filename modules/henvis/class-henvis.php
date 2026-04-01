<?php
/**
 * Henvis Din Ven – Module
 * Handles referral form (shortcode), DB storage, emails, SMTP and admin views.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPZ_Henvis {

    const DB_VERSION_KEY       = 'rzpz_henvis_db_version';
    const DB_VERSION           = '3';
    const MANAGERS_OPTION      = 'rzpz_henvis_managers';
    const FORM_CONFIG_OPTION   = 'rzpz_henvis_form_config';
    const EXTRA_RECIP_OPTION   = 'rzpz_henvis_extra_recipients';
    const SMTP_OPTION          = 'rzpz_henvis_smtp';
    const EMAIL_TEMPLATES_OPT  = 'rzpz_henvis_email_templates';
    const CUSTOM_FIELDS_OPT    = 'rzpz_henvis_custom_fields';

    const DEFAULT_MANAGERS = [
        'kasper_telenor'  => [ 'label' => 'Kasper – Telenor',   'email' => 'kapj@rezponz.dk',  'name' => 'Kasper' ],
        'ahmad_telenor'   => [ 'label' => 'Ahmad – Telenor',    'email' => 'ahre@rezponz.dk',  'name' => 'Ahmad' ],
        'dana_norlys'     => [ 'label' => 'Dana – Norlys',      'email' => 'dham@rezponz.dk',  'name' => 'Dana' ],
        'jonas_norlys'    => [ 'label' => 'Jonas – Norlys',     'email' => 'joli@rezponz.dk',  'name' => 'Jonas' ],
        'rasmus_norlys'   => [ 'label' => 'Rasmus – Norlys',    'email' => 'roj@rezponz.dk',   'name' => 'Rasmus' ],
        'nicklas_nrgi'    => [ 'label' => 'Nicklas – NRGI',     'email' => 'nli@rezponz.dk',   'name' => 'Nicklas' ],
        'alexander_cbb'   => [ 'label' => 'Alexander – CBB',    'email' => 'alww@rezponz.dk',  'name' => 'Alexander' ],
    ];

    // ── Boot ────────────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'init',                  [ __CLASS__, 'maybe_install_db' ] );
        add_action( 'admin_menu',            [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_css' ] );
        add_shortcode( 'rezponz_henvis_ven', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'enqueue_frontend' ] );
        add_action( 'phpmailer_init',        [ __CLASS__, 'configure_smtp' ] );

        // Admin-post handlers
        add_action( 'admin_post_rzpz_henvis_save_manager',         [ __CLASS__, 'handle_save_manager' ] );
        add_action( 'admin_post_rzpz_henvis_delete_manager',       [ __CLASS__, 'handle_delete_manager' ] );
        add_action( 'admin_post_rzpz_henvis_test_email',           [ __CLASS__, 'handle_test_email' ] );
        add_action( 'admin_post_rzpz_henvis_save_form_config',     [ __CLASS__, 'handle_save_form_config' ] );
        add_action( 'admin_post_rzpz_henvis_save_extra_recip',     [ __CLASS__, 'handle_save_extra_recipients' ] );
        add_action( 'admin_post_rzpz_henvis_delete_extra_recip',   [ __CLASS__, 'handle_delete_extra_recipient' ] );
        add_action( 'admin_post_rzpz_henvis_save_smtp',            [ __CLASS__, 'handle_save_smtp' ] );
        add_action( 'admin_post_rzpz_henvis_save_note',            [ __CLASS__, 'handle_save_note' ] );
        add_action( 'admin_post_rzpz_henvis_export_csv',           [ __CLASS__, 'handle_export_csv' ] );
        add_action( 'admin_post_rzpz_henvis_save_email_templates', [ __CLASS__, 'handle_save_email_templates' ] );
        add_action( 'admin_post_rzpz_henvis_add_custom_field',     [ __CLASS__, 'handle_add_custom_field' ] );
        add_action( 'admin_post_rzpz_henvis_delete_custom_field',  [ __CLASS__, 'handle_delete_custom_field' ] );
    }

    public static function enqueue_admin_css( string $hook ) : void {
        if ( strpos( $hook, 'rzpz-henvis' ) === false ) return;
        wp_enqueue_style(
            'rzpz-henvis-admin',
            RZPA_URL . 'modules/henvis/assets/henvis-admin.css',
            [],
            RZPA_VERSION
        );
    }

    public static function maybe_install_db() {
        if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
            self::install_db();
        }
    }

    // ── SMTP ────────────────────────────────────────────────────────────────────

    public static function get_smtp() : array {
        return wp_parse_args( get_option( self::SMTP_OPTION, [] ), [
            'enabled'    => false,
            'host'       => '',
            'port'       => '587',
            'secure'     => 'tls',
            'user'       => '',
            'pass'       => '',
            'from_email' => 'no-reply@rezponz.dk',
            'from_name'  => 'Rezponz Marketing Platform',
        ] );
    }

    public static function configure_smtp( $phpmailer ) : void {
        $smtp = self::get_smtp();
        if ( empty( $smtp['enabled'] ) || empty( $smtp['host'] ) ) return;

        $phpmailer->isSMTP();
        $phpmailer->Host       = $smtp['host'];
        $phpmailer->Port       = (int) ( $smtp['port'] ?: 587 );
        $phpmailer->SMTPAuth   = ! empty( $smtp['user'] );
        $phpmailer->Username   = $smtp['user'];
        $phpmailer->Password   = $smtp['pass'];
        $phpmailer->SMTPSecure = $smtp['secure'] ?: 'tls';
        $phpmailer->From       = $smtp['from_email'] ?: 'no-reply@rezponz.dk';
        $phpmailer->FromName   = $smtp['from_name']  ?: 'Rezponz Marketing Platform';
        $phpmailer->SMTPDebug  = 0;
    }

    // ── Form config ─────────────────────────────────────────────────────────────

    public static function default_form_config() : array {
        return [
            'form_title'          => 'Henvis Din Ven',
            'form_subtitle'       => 'Del din glæde og giv din ven en chance for at blive en del af vores team',
            'section_referrer'    => '👤 Dine oplysninger',
            'section_friend'      => '🤝 Din ven',
            'manager_label'       => 'Hvilken Senior Manager arbejder du for?',
            'manager_placeholder' => '– Vælg Senior Manager –',
            'consent_text'        => 'Jeg bekræfter at min ven er okay med at blive kontaktet af Rezponz.',
            'submit_text'         => '🚀 Send henvisning',
            'success_title'       => 'Tak for din henvisning!',
            'success_message'     => 'Vi har sendt en bekræftelse til dig og din ven, og notificeret din Senior Manager. Godt gået!',
            'show_captcha'        => true,
            'fields'              => [
                'referrer_name'  => [ 'enabled' => true,  'required' => true,  'label' => 'Dit navn',        'placeholder' => 'Dit fulde navn' ],
                'referrer_phone' => [ 'enabled' => true,  'required' => true,  'label' => 'Telefon',         'placeholder' => '+45 12 34 56 78' ],
                'referrer_email' => [ 'enabled' => true,  'required' => true,  'label' => 'Email',           'placeholder' => 'din@email.dk' ],
                'friend_name'    => [ 'enabled' => true,  'required' => true,  'label' => 'Vennens navn',    'placeholder' => 'Vennens fulde navn' ],
                'friend_phone'   => [ 'enabled' => true,  'required' => false, 'label' => 'Vennens telefon', 'placeholder' => '+45 12 34 56 78' ],
                'friend_email'   => [ 'enabled' => true,  'required' => true,  'label' => 'Vennens email',   'placeholder' => 'vennens@email.dk' ],
            ],
        ];
    }

    public static function get_form_config() : array {
        $saved   = get_option( self::FORM_CONFIG_OPTION, null );
        $default = self::default_form_config();
        if ( ! is_array( $saved ) ) return $default;

        $result = $default;
        foreach ( $saved as $k => $v ) {
            if ( $k === 'fields' && is_array( $v ) ) {
                foreach ( $v as $fk => $fv ) {
                    if ( isset( $result['fields'][ $fk ] ) && is_array( $fv ) ) {
                        $result['fields'][ $fk ] = array_merge( $result['fields'][ $fk ], $fv );
                    }
                }
            } else {
                $result[ $k ] = $v;
            }
        }
        return $result;
    }

    // ── Custom fields ────────────────────────────────────────────────────────────

    public static function get_custom_fields() : array {
        $saved = get_option( self::CUSTOM_FIELDS_OPT, null );
        return is_array( $saved ) ? $saved : [];
    }

    public static function handle_add_custom_field() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_add_custom_field' );

        $label       = sanitize_text_field( $_POST['cf_label']       ?? '' );
        $type        = sanitize_key(        $_POST['cf_type']        ?? 'text' );
        $placeholder = sanitize_text_field( $_POST['cf_placeholder'] ?? '' );
        $section     = sanitize_key(        $_POST['cf_section']     ?? 'referrer' );
        $required    = ! empty( $_POST['cf_required'] );
        $options_raw = sanitize_textarea_field( $_POST['cf_options'] ?? '' );

        $allowed_types = [ 'text', 'tel', 'email', 'number', 'textarea', 'select', 'checkbox' ];
        if ( ! $label || ! in_array( $type, $allowed_types, true ) ) {
            wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=form&cf_error=invalid' ) );
            exit;
        }

        $options = [];
        if ( $type === 'select' && $options_raw ) {
            foreach ( explode( "\n", $options_raw ) as $line ) {
                $line = trim( $line );
                if ( $line ) $options[] = $line;
            }
        }

        $fields   = self::get_custom_fields();
        $id       = 'cf_' . sanitize_key( $label ) . '_' . time();
        $fields[] = [
            'id'          => $id,
            'label'       => $label,
            'type'        => $type,
            'placeholder' => $placeholder,
            'section'     => $section,
            'required'    => $required,
            'options'     => $options,
        ];
        update_option( self::CUSTOM_FIELDS_OPT, $fields );

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=form&cf_saved=1' ) );
        exit;
    }

    public static function handle_delete_custom_field() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_delete_custom_field' );

        $id     = sanitize_text_field( $_POST['cf_id'] ?? '' );
        $fields = array_filter( self::get_custom_fields(), fn( $f ) => ( $f['id'] ?? '' ) !== $id );
        update_option( self::CUSTOM_FIELDS_OPT, array_values( $fields ) );

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=form&cf_deleted=1' ) );
        exit;
    }

    // ── Email templates ──────────────────────────────────────────────────────────

    public static function get_default_email_templates() : array {
        return [
            'manager' => [
                'subject' => '🙌 {{medarbejder_navn}} har henvist en ven til Rezponz!',
                'body'    => "<p>Hej {{manager_navn}}!</p>
<p>Gode nyheder – <strong>{{medarbejder_navn}}</strong> har netop henvist <strong>{{ven_navn}}</strong> til at søge en stilling hos Rezponz. 💪</p>
<p>Kontaktoplysninger på vennen:</p>
<ul>
  <li><strong>Navn:</strong> {{ven_navn}}</li>
  <li><strong>Email:</strong> {{ven_email}}</li>
  <li><strong>Telefon:</strong> {{ven_tlf}}</li>
</ul>
<p><strong>{{ven_navn}}</strong> har fået en invitationsmail. Din opgave er at følge op og motivere! 🚀</p>
<p>Bedste hilsner,<br>Rezponz Marketing Platform</p>",
            ],
            'ven' => [
                'subject' => '👋 {{medarbejder_navn}} tror på dig – søg en stilling hos Rezponz!',
                'body'    => "<p>Hej {{ven_navn}}!</p>
<p>Du er lige blevet henvist af <strong>{{medarbejder_navn}}</strong>, som mener du ville passe perfekt hos Rezponz.</p>
<p>Hos Rezponz arbejder vi med salg på vegne af Danmarks største brands. Vi er et ungt, ambitiøst team – og vi vil gerne lære dig at kende.</p>
<p><strong>Er du klar til at tage skridtet?</strong></p>
<p>👉 <a href='{{karriere_link}}' style='background:#CCFF00;color:#0d0d0d;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;margin:8px 0'>Søg en stilling her</a></p>
<p>Vi glæder os til at høre fra dig!</p>
<p>Bedste hilsner,<br>Rezponz – Karriere &amp; Rekruttering</p>",
            ],
            'medarbejder' => [
                'subject' => '✅ Tak for din henvisning – vi tager det herfra!',
                'body'    => "<p>Hej {{medarbejder_navn}}!</p>
<p>Tusind tak for at du henviste <strong>{{ven_navn}}</strong> til Rezponz. Det sætter vi stor pris på! 🙌</p>
<p>Vi har nu sendt <strong>{{ven_navn}}</strong> en invitationsmail, og din Senior Manager <strong>{{manager_navn}}</strong> er notificeret.</p>
<p>💡 Husk: Hvis din ven får tilbudt en stilling hos Rezponz, udløser det en <strong>bonus til dig på 500 kr.</strong></p>
<p>Godt gået – og tak fordi du tror på Rezponz!</p>
<p>Bedste hilsner,<br>Rezponz Marketing Platform</p>",
            ],
        ];
    }

    public static function get_email_templates() : array {
        $saved    = get_option( self::EMAIL_TEMPLATES_OPT, null );
        $defaults = self::get_default_email_templates();
        if ( ! is_array( $saved ) ) return $defaults;

        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $saved[ $key ] ) ) $saved[ $key ] = $default;
            else $saved[ $key ] = array_merge( $default, $saved[ $key ] );
        }
        return $saved;
    }

    public static function render_email_template( string $body, array $vars ) : string {
        foreach ( $vars as $k => $v ) {
            $body = str_replace( '{{' . $k . '}}', esc_html( $v ), $body );
        }
        // Remove any remaining unresolved tokens
        $body = preg_replace( '/\{\{[^}]+\}\}/', '', $body );
        return $body;
    }

    public static function handle_save_email_templates() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_save_email_templates' );

        $keys      = [ 'manager', 'ven', 'medarbejder' ];
        $templates = [];
        foreach ( $keys as $k ) {
            $templates[ $k ] = [
                'subject' => sanitize_text_field( $_POST[ "tpl_{$k}_subject" ] ?? '' ),
                'body'    => wp_kses_post( $_POST[ "tpl_{$k}_body" ] ?? '' ),
            ];
        }
        update_option( self::EMAIL_TEMPLATES_OPT, $templates );

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&tpl_saved=1' ) );
        exit;
    }

    // ── Extra recipients ─────────────────────────────────────────────────────────

    public static function get_extra_recipients() : array {
        $saved = get_option( self::EXTRA_RECIP_OPTION, null );
        if ( is_array( $saved ) ) return $saved;
        return [
            [ 'name' => 'Lie', 'email' => 'lie@rezponz.dk' ],
        ];
    }

    // ── Managers ────────────────────────────────────────────────────────────────

    public static function get_managers() : array {
        $saved = get_option( self::MANAGERS_OPTION, null );
        if ( is_array( $saved ) && ! empty( $saved ) ) {
            return $saved;
        }
        return self::DEFAULT_MANAGERS;
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
            emails_log      LONGTEXT     NOT NULL DEFAULT (''),
            notes           TEXT         NOT NULL DEFAULT (''),
            extra_data      LONGTEXT     NOT NULL DEFAULT (''),
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
        add_submenu_page(
            'rzpa-dashboard',
            __( 'Henvis – Indstillinger', 'rezponz-analytics' ),
            null,
            'manage_options',
            'rzpz-henvis-settings',
            [ __CLASS__, 'render_settings' ]
        );
    }

    public static function render_admin() {
        $view = RZPA_DIR . 'modules/henvis/views/admin-henvis-list.php';
        if ( file_exists( $view ) ) include $view;
    }

    public static function render_settings() {
        $view = RZPA_DIR . 'modules/henvis/views/admin-henvis-settings.php';
        if ( file_exists( $view ) ) include $view;
    }

    // ── Handlers ────────────────────────────────────────────────────────────────

    public static function handle_save_manager() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_save_manager' );

        $name  = sanitize_text_field( $_POST['mgr_name']  ?? '' );
        $label = sanitize_text_field( $_POST['mgr_label'] ?? '' );
        $email = sanitize_email(      $_POST['mgr_email'] ?? '' );

        if ( ! $name || ! $label || ! is_email( $email ) ) {
            wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=managers&error=invalid' ) );
            exit;
        }

        $managers         = self::get_managers();
        $key              = sanitize_key( $name . '_' . time() );
        $managers[ $key ] = [ 'name' => $name, 'label' => $label, 'email' => $email ];
        update_option( self::MANAGERS_OPTION, $managers );

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=managers&saved=1' ) );
        exit;
    }

    public static function handle_delete_manager() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_delete_manager' );

        $key      = sanitize_key( $_POST['mgr_key'] ?? '' );
        $managers = self::get_managers();
        unset( $managers[ $key ] );
        update_option( self::MANAGERS_OPTION, $managers );

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=managers&deleted=1' ) );
        exit;
    }

    public static function handle_test_email() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_test_email' );

        $to = sanitize_email( $_POST['test_email'] ?? '' );
        if ( ! is_email( $to ) ) {
            wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&error=email' ) );
            exit;
        }

        $smtp    = self::get_smtp();
        $subject = '✅ Test-email fra Rezponz Marketing Platform';
        $body    = self::email_wrap( "
            <p>Hej!</p>
            <p>Dette er en test-email fra <strong>Rezponz Marketing Platform</strong>.</p>
            <p>Konfiguration: " . ( $smtp['enabled'] && $smtp['host'] ? 'SMTP (' . esc_html( $smtp['host'] ) . ':' . esc_html( $smtp['port'] ) . ')' : 'Standard WordPress mail (PHP mail)' ) . "</p>
            <p>Tidspunkt: " . current_time( 'Y-m-d H:i:s' ) . "</p>
            <p>Hvis du modtager denne email, fungerer email-udsendelsen korrekt. 🎉</p>
        " );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . ( $smtp['from_name'] ?: 'Rezponz' ) . ' <' . ( $smtp['from_email'] ?: 'no-reply@rezponz.dk' ) . '>',
        ];

        // Fang præcis fejlbesked fra WordPress mailer
        $mail_error = '';
        $mail_error_handler = function( WP_Error $err ) use ( &$mail_error ) {
            $mail_error = $err->get_error_message();
        };
        add_action( 'wp_mail_failed', $mail_error_handler );

        $sent = wp_mail( $to, $subject, $body, $headers );

        remove_action( 'wp_mail_failed', $mail_error_handler );

        if ( $sent ) {
            wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&test_sent=1' ) );
        } else {
            $err_param = $mail_error ? '&mail_error=' . urlencode( $mail_error ) : '';
            wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&test_sent=0' . $err_param ) );
        }
        exit;
    }

    public static function handle_save_form_config() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_save_form_config' );

        $default = self::default_form_config();
        $config  = [];

        $text_keys = [ 'form_title', 'form_subtitle', 'section_referrer', 'section_friend',
                       'manager_label', 'manager_placeholder', 'consent_text', 'submit_text',
                       'success_title' ];
        foreach ( $text_keys as $k ) {
            $config[ $k ] = sanitize_text_field( $_POST[ $k ] ?? $default[ $k ] );
        }
        $config['success_message'] = sanitize_textarea_field( $_POST['success_message'] ?? $default['success_message'] );
        $config['show_captcha']    = ! empty( $_POST['show_captcha'] );

        $config['fields'] = [];
        foreach ( $default['fields'] as $fk => $fd ) {
            $config['fields'][ $fk ] = [
                'enabled'     => ! empty( $_POST[ "field_{$fk}_enabled" ] ),
                'required'    => ! empty( $_POST[ "field_{$fk}_required" ] ),
                'label'       => sanitize_text_field( $_POST[ "field_{$fk}_label" ]       ?? $fd['label'] ),
                'placeholder' => sanitize_text_field( $_POST[ "field_{$fk}_placeholder" ] ?? $fd['placeholder'] ),
            ];
        }

        foreach ( [ 'referrer_name', 'referrer_email', 'friend_name', 'friend_email' ] as $core ) {
            $config['fields'][ $core ]['enabled']  = true;
            $config['fields'][ $core ]['required'] = true;
        }

        update_option( self::FORM_CONFIG_OPTION, $config );
        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=form&saved=1' ) );
        exit;
    }

    public static function handle_save_extra_recipients() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_save_extra_recip' );

        $name  = sanitize_text_field( $_POST['recip_name']  ?? '' );
        $email = sanitize_email(      $_POST['recip_email'] ?? '' );

        if ( ! $name || ! is_email( $email ) ) {
            wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&error=recip' ) );
            exit;
        }

        $recipients   = self::get_extra_recipients();
        $recipients[] = [ 'name' => $name, 'email' => $email ];
        update_option( self::EXTRA_RECIP_OPTION, $recipients );

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&recip_saved=1' ) );
        exit;
    }

    public static function handle_delete_extra_recipient() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_delete_extra_recip' );

        $idx        = intval( $_POST['recip_idx'] ?? -1 );
        $recipients = self::get_extra_recipients();
        if ( isset( $recipients[ $idx ] ) ) {
            array_splice( $recipients, $idx, 1 );
            update_option( self::EXTRA_RECIP_OPTION, $recipients );
        }

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&recip_deleted=1' ) );
        exit;
    }

    public static function handle_save_smtp() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_save_smtp' );

        $smtp = [
            'enabled'    => ! empty( $_POST['smtp_enabled'] ),
            'host'       => sanitize_text_field( $_POST['smtp_host']       ?? '' ),
            'port'       => sanitize_text_field( $_POST['smtp_port']       ?? '587' ),
            'secure'     => sanitize_key(        $_POST['smtp_secure']     ?? 'tls' ),
            'user'       => sanitize_text_field( $_POST['smtp_user']       ?? '' ),
            'pass'       => sanitize_text_field( $_POST['smtp_pass']       ?? '' ),
            'from_email' => sanitize_email(      $_POST['smtp_from_email'] ?? 'no-reply@rezponz.dk' ),
            'from_name'  => sanitize_text_field( $_POST['smtp_from_name']  ?? 'Rezponz Marketing Platform' ),
        ];

        if ( empty( $smtp['pass'] ) ) {
            $existing     = self::get_smtp();
            $smtp['pass'] = $existing['pass'] ?? '';
        }

        update_option( self::SMTP_OPTION, $smtp );
        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis-settings&tab=emails&smtp_saved=1' ) );
        exit;
    }

    public static function handle_save_note() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_save_note' );

        $id   = intval( $_POST['referral_id'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( $id > 0 ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'rzpz_referrals',
                [ 'notes' => $note ],
                [ 'id'    => $id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        wp_redirect( admin_url( 'admin.php?page=rzpz-henvis&note_saved=1' ) );
        exit;
    }

    public static function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'rzpz_henvis_export_csv' );

        global $wpdb;
        $table = $wpdb->prefix . 'rzpz_referrals';

        $filter_mgr    = sanitize_key( $_GET['mgr']    ?? '' );
        $filter_status = sanitize_key( $_GET['status'] ?? '' );
        $search        = sanitize_text_field( $_GET['s'] ?? '' );

        $where  = '1=1';
        $params = [];
        if ( $filter_mgr )    { $where .= ' AND manager_key = %s'; $params[] = $filter_mgr; }
        if ( $filter_status ) { $where .= ' AND status = %s';      $params[] = $filter_status; }
        if ( $search ) {
            $where   .= ' AND (referrer_name LIKE %s OR friend_name LIKE %s OR referrer_email LIKE %s)';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $query     = "SELECT * FROM {$table} WHERE {$where} ORDER BY submitted_at DESC";
        $referrals = $params
            ? $wpdb->get_results( $wpdb->prepare( $query, ...$params ) )
            : $wpdb->get_results( $query );

        $managers      = self::get_managers();
        $custom_fields = self::get_custom_fields();

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="henvisninger-' . gmdate('Y-m-d') . '.csv"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF";

        $out      = fopen( 'php://output', 'w' );
        $cf_labels = array_column( $custom_fields, 'label' );
        fputcsv( $out, array_merge(
            [ 'ID', 'Dato', 'Medarbejder navn', 'Medarbejder email', 'Medarbejder tlf', 'Ven navn', 'Ven email', 'Ven tlf', 'Manager', 'Status', 'Note' ],
            $cf_labels
        ), ';' );

        foreach ( $referrals as $r ) {
            $mgr        = $managers[ $r->manager_key ] ?? null;
            $extra_data = json_decode( $r->extra_data ?: '{}', true );
            $cf_values  = array_map( fn( $f ) => $extra_data[ $f['id'] ] ?? '', $custom_fields );
            fputcsv( $out, array_merge( [
                $r->id,
                wp_date( 'd/m/Y H:i', strtotime( $r->submitted_at ) ),
                $r->referrer_name,
                $r->referrer_email,
                $r->referrer_phone,
                $r->friend_name,
                $r->friend_email,
                $r->friend_phone,
                $mgr ? $mgr['label'] : $r->manager_key,
                $r->status,
                $r->notes,
            ], $cf_values ), ';' );
        }

        fclose( $out );
        exit;
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

        $result     = null;
        $captcha_ok = false;
        $cfg        = self::get_form_config();

        if ( isset( $_POST['rzpz_henvis_submit'] ) ) {
            if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'rzpz_henvis_submit' ) ) {
                $result = [ 'error' => __( 'Sikkerhedstjek fejlede. Prøv igen.', 'rezponz-analytics' ) ];
            } elseif ( $cfg['show_captcha'] ) {
                $expected = intval( $_POST['rzpz_captcha_expected'] ?? 0 );
                $provided = intval( $_POST['rzpz_captcha_answer']   ?? -999 );
                $hash_in  = hash_hmac( 'sha256', (string) $expected, wp_salt( 'auth' ) );
                $hash_exp = sanitize_text_field( $_POST['rzpz_captcha_hash'] ?? '' );

                if ( ! hash_equals( $hash_in, $hash_exp ) || $provided !== $expected ) {
                    $result = [ 'error' => __( 'Forkert svar på menneskeverifikation. Prøv igen.', 'rezponz-analytics' ) ];
                } else {
                    $captcha_ok = true;
                    $result     = self::process_form( $_POST, $cfg );
                }
            } else {
                $result = self::process_form( $_POST, $cfg );
            }
        }

        $a        = wp_rand( 2, 9 );
        $b        = wp_rand( 2, 9 );
        $expected = $a + $b;
        $hash     = hash_hmac( 'sha256', (string) $expected, wp_salt( 'auth' ) );

        include RZPA_DIR . 'modules/henvis/views/frontend-form.php';
        return ob_get_clean();
    }

    // ── Process form ────────────────────────────────────────────────────────────

    private static function process_form( $post, $cfg ) {
        $referrer_name  = sanitize_text_field( $post['referrer_name']  ?? '' );
        $referrer_email = sanitize_email(      $post['referrer_email'] ?? '' );
        $referrer_phone = sanitize_text_field( $post['referrer_phone'] ?? '' );
        $friend_name    = sanitize_text_field( $post['friend_name']    ?? '' );
        $friend_email   = sanitize_email(      $post['friend_email']   ?? '' );
        $friend_phone   = sanitize_text_field( $post['friend_phone']   ?? '' );
        $manager_key    = sanitize_key(        $post['manager_key']    ?? '' );
        $consent        = ! empty( $post['rzpz_consent'] );
        $managers       = self::get_managers();

        if ( ! $referrer_name || ! $referrer_email || ! $friend_name || ! $friend_email ) {
            return [ 'error' => __( 'Udfyld venligst alle påkrævede felter.', 'rezponz-analytics' ) ];
        }
        if ( ! is_email( $referrer_email ) || ! is_email( $friend_email ) ) {
            return [ 'error' => __( 'En eller begge email-adresser er ugyldige.', 'rezponz-analytics' ) ];
        }
        if ( ! isset( $managers[ $manager_key ] ) ) {
            return [ 'error' => __( 'Vælg venligst en Senior Manager.', 'rezponz-analytics' ) ];
        }
        if ( ! $consent ) {
            return [ 'error' => __( 'Du skal bekræfte at din ven er okay med at blive kontaktet.', 'rezponz-analytics' ) ];
        }

        // Collect custom field values
        $custom_fields = self::get_custom_fields();
        $extra_data    = [];
        foreach ( $custom_fields as $cf ) {
            $fid = $cf['id'];
            if ( $cf['type'] === 'checkbox' ) {
                $extra_data[ $fid ] = ! empty( $post[ $fid ] ) ? 'Ja' : 'Nej';
            } else {
                $extra_data[ $fid ] = sanitize_text_field( $post[ $fid ] ?? '' );
            }
            if ( ! empty( $cf['required'] ) && empty( $extra_data[ $fid ] ) ) {
                return [ 'error' => sprintf( __( 'Feltet "%s" er påkrævet.', 'rezponz-analytics' ), $cf['label'] ) ];
            }
        }

        $manager    = $managers[ $manager_key ];
        $emails_log = self::send_emails( $referrer_name, $referrer_email, $referrer_phone, $friend_name, $friend_email, $friend_phone, $manager, $extra_data );

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
                'emails_log'     => wp_json_encode( $emails_log ),
                'notes'          => '',
                'extra_data'     => wp_json_encode( $extra_data ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return [ 'success' => true ];
    }

    // ── Emails ──────────────────────────────────────────────────────────────────

    private static function send_emails( $referrer_name, $referrer_email, $referrer_phone, $friend_name, $friend_email, $friend_phone, $manager, $extra_data = [] ) : array {
        $extra_recipients = self::get_extra_recipients();
        $smtp             = self::get_smtp();
        $templates        = self::get_email_templates();
        $custom_fields    = self::get_custom_fields();
        $from_email       = $smtp['from_email'] ?: 'no-reply@rezponz.dk';
        $from_name        = $smtp['from_name']  ?: 'Rezponz Marketing Platform';

        // Build custom fields string for emails
        $cf_lines = '';
        foreach ( $custom_fields as $cf ) {
            $val = $extra_data[ $cf['id'] ] ?? '';
            if ( $val ) $cf_lines .= '<li><strong>' . esc_html( $cf['label'] ) . ':</strong> ' . esc_html( $val ) . '</li>';
        }

        // Template variables
        $vars = [
            'medarbejder_navn'  => $referrer_name,
            'medarbejder_email' => $referrer_email,
            'medarbejder_tlf'   => $referrer_phone,
            'ven_navn'          => $friend_name,
            'ven_email'         => $friend_email,
            'ven_tlf'           => $friend_phone,
            'manager_navn'      => $manager['name'] ?? '',
            'manager_email'     => $manager['email'] ?? '',
            'karriere_link'     => 'https://rezponz.dk/karriere-stillinger/',
            'dato'              => current_time( 'd/m/Y H:i' ),
            'site_navn'         => get_bloginfo( 'name' ),
            'ekstra_felter'     => $cf_lines ? '<ul>' . $cf_lines . '</ul>' : '',
        ];
        // Add individual custom field variables
        foreach ( $custom_fields as $cf ) {
            $vars[ 'felt_' . $cf['id'] ] = $extra_data[ $cf['id'] ] ?? '';
        }

        $log = [
            'manager'  => [],
            'friend'   => [],
            'referrer' => [],
            'extra'    => [],
            'sent_at'  => current_time( 'Y-m-d H:i:s' ),
        ];

        $headers = [ "Content-Type: text/html; charset=UTF-8", "From: {$from_name} <{$from_email}>" ];
        foreach ( $extra_recipients as $r ) {
            $rn = sanitize_text_field( $r['name'] );
            $re = sanitize_email( $r['email'] );
            if ( $re ) {
                $headers[]      = "CC: {$rn} <{$re}>";
                $log['extra'][] = [ 'name' => $rn, 'email' => $re ];
            }
        }

        // 1 – To Manager
        $tpl_mgr = $templates['manager'];
        $sent1   = wp_mail(
            $manager['email'],
            self::render_email_template( $tpl_mgr['subject'], $vars ),
            self::email_wrap( self::render_email_template( $tpl_mgr['body'], $vars ) ),
            $headers
        );
        $log['manager'] = [ 'to' => $manager['email'], 'name' => $manager['name'], 'sent' => (bool) $sent1, 'time' => current_time( 'H:i:s' ) ];

        // 2 – To Friend
        $tpl_ven = $templates['ven'];
        $sent2   = wp_mail(
            $friend_email,
            self::render_email_template( $tpl_ven['subject'], $vars ),
            self::email_wrap( self::render_email_template( $tpl_ven['body'], $vars ) ),
            $headers
        );
        $log['friend'] = [ 'to' => $friend_email, 'name' => $friend_name, 'sent' => (bool) $sent2, 'time' => current_time( 'H:i:s' ) ];

        // 3 – To Referrer
        $tpl_med = $templates['medarbejder'];
        $sent3   = wp_mail(
            $referrer_email,
            self::render_email_template( $tpl_med['subject'], $vars ),
            self::email_wrap( self::render_email_template( $tpl_med['body'], $vars ) ),
            $headers
        );
        $log['referrer'] = [ 'to' => $referrer_email, 'name' => $referrer_name, 'sent' => (bool) $sent3, 'time' => current_time( 'H:i:s' ) ];

        return $log;
    }

    public static function email_wrap( string $inner ) : string {
        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0d0d0d;color:#e0e0e0;padding:24px;margin:0">
            <div style="max-width:600px;margin:0 auto;background:#1a1a1a;border-radius:12px;padding:32px;border:1px solid #333">
                <div style="margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #2a2a2a">
                    <span style="font-size:22px;font-weight:bold;color:#CCFF00;letter-spacing:-0.5px">rezponz</span>
                    <span style="color:#666;font-size:12px;margin-left:8px">Marketing Platform</span>
                </div>
                ' . $inner . '
                <div style="margin-top:24px;padding-top:16px;border-top:1px solid #2a2a2a;font-size:11px;color:#555">
                    Denne email er sendt via Rezponz Marketing Platform · <a href="https://rezponz.dk" style="color:#555">rezponz.dk</a>
                </div>
            </div>
        </body></html>';
    }
}
