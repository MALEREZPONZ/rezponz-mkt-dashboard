<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_RezCRM — Hoved-controller
 *
 * Ansvar:
 * - Init REST API + admin
 * - Cron: forsinkede afslag + GDPR auto-slet
 * - Email/SMS afsendelse med merge tags
 * - Rubix-integration ved ansættelse
 * - GDPR data-eksport/sletning (WP Privacy API)
 */
class RZPZ_RezCRM {

    public static function init(): void {
        add_action( 'rest_api_init',          [ __CLASS__, 'register_routes' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue' ] );

        // Cron hooks
        add_action( 'rzpz_crm_dispatch_rejections', [ __CLASS__, 'dispatch_rejections' ] );
        add_action( 'rzpz_crm_gdpr_cleanup',        [ __CLASS__, 'gdpr_cleanup' ] );

        // Registrér cron-events hvis de ikke eksisterer
        if ( ! wp_next_scheduled( 'rzpz_crm_dispatch_rejections' ) ) {
            wp_schedule_event( time(), 'hourly', 'rzpz_crm_dispatch_rejections' );
        }
        if ( ! wp_next_scheduled( 'rzpz_crm_gdpr_cleanup' ) ) {
            // Planlæg til i morgen 03:00 UTC hvis 03:00 i dag allerede er passeret
            $next = strtotime( 'today 03:00:00 UTC' );
            if ( $next <= time() ) $next = strtotime( 'tomorrow 03:00:00 UTC' );
            wp_schedule_event( $next, 'daily', 'rzpz_crm_gdpr_cleanup' );
        }

        // GDPR Privacy API
        add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers',   [ __CLASS__, 'register_eraser' ] );
    }

    // ── Admin scripts/styles ─────────────────────────────────────────────────

    public static function enqueue( string $hook ): void {
        // Kun indlæs på den principale CRM-side — ikke sub-sider (users, forms)
        if ( strpos( $hook, 'rzpa-rezcrm' ) === false ) return;
        if ( strpos( $hook, 'rzpa-rezcrm-' ) !== false ) return;

        wp_enqueue_style(
            'rzpz-rezcrm',
            RZPA_URL . 'modules/rezcrm/assets/rezcrm.css',
            [],
            RZPA_VERSION
        );
        wp_enqueue_script(
            'rzpz-rezcrm',
            RZPA_URL . 'modules/rezcrm/assets/rezcrm.js',
            [ 'jquery' ],
            RZPA_VERSION,
            true
        );
        wp_localize_script( 'rzpz-rezcrm', 'RZPZ_CRM', [
            'apiBase'  => rest_url( 'rzpa/v1/crm/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'stages'   => RZPZ_CRM_DB::PIPELINE_STAGES,
            'sources'  => RZPZ_CRM_DB::SOURCES,
            'siteUrl'  => site_url(),
        ] );
    }

    // ── REST API routes ──────────────────────────────────────────────────────

    public static function register_routes(): void {
        $ns  = 'rzpa/v1';
        // CRM staff use rezcrm_access cap; super-admins (manage_options) also allowed
        $cap = fn() => current_user_can( 'manage_options' ) || current_user_can( RZPZ_CRM_Auth::CAP );

        $routes = [
            // Positions
            [ 'GET',    'crm/positions',                       'api_positions_list' ],
            [ 'POST',   'crm/positions',                       'api_positions_create' ],
            [ 'PUT',    'crm/positions/(?P<id>\d+)',           'api_positions_update' ],

            // Applications (Kanban data)
            [ 'GET',    'crm/applications',                    'api_applications_list' ],
            [ 'POST',   'crm/applications',                    'api_applications_create' ],
            [ 'GET',    'crm/applications/(?P<id>\d+)',        'api_applications_get' ],
            [ 'PATCH',  'crm/applications/(?P<id>\d+)/stage', 'api_applications_move_stage' ],
            [ 'PATCH',  'crm/applications/(?P<id>\d+)',        'api_applications_update' ],
            [ 'DELETE', 'crm/applications/(?P<id>\d+)',        'api_applications_delete' ],

            // History
            [ 'GET',    'crm/applications/(?P<id>\d+)/history',   'api_history' ],
            [ 'GET',    'crm/applications/(?P<id>\d+)/comms',     'api_communications' ],

            // Email/SMS
            [ 'POST',   'crm/applications/(?P<id>\d+)/send',  'api_send_comm' ],

            // Templates
            [ 'GET',    'crm/templates',                       'api_templates_list' ],
            [ 'POST',   'crm/templates',                       'api_templates_save' ],
            [ 'PUT',    'crm/templates/(?P<id>\d+)',           'api_templates_update' ],
            [ 'DELETE', 'crm/templates/(?P<id>\d+)',           'api_templates_delete' ],

            // Stats
            [ 'GET',    'crm/stats',                           'api_stats' ],

            // Cancel scheduled rejection
            [ 'DELETE', 'crm/applications/(?P<id>\d+)/rejection', 'api_cancel_rejection' ],

            // Applicant status (offentlig — token-beskyttet)
            [ 'GET',    'crm/status/(?P<token>[a-zA-Z0-9]+)', 'api_applicant_status', false ],
        ];

        foreach ( $routes as $r ) {
            $public_permission = $r[3] ?? null;
            register_rest_route( $ns, $r[1], [
                'methods'             => $r[0],
                'callback'            => [ __CLASS__, $r[2] ],
                'permission_callback' => $public_permission === false ? '__return_true' : $cap,
            ] );
        }
    }

    // ── REST: Positions ──────────────────────────────────────────────────────

    public static function api_positions_list(): WP_REST_Response {
        return new WP_REST_Response( RZPZ_CRM_DB::get_positions(), 200 );
    }

    public static function api_positions_create( WP_REST_Request $req ): WP_REST_Response {
        $id = RZPZ_CRM_DB::upsert_position( $req->get_json_params() );
        if ( ! $id ) return new WP_REST_Response( [ 'message' => 'Ugyldig data' ], 400 );
        return new WP_REST_Response( [ 'id' => $id ], 201 );
    }

    public static function api_positions_update( WP_REST_Request $req ): WP_REST_Response {
        $data       = $req->get_json_params();
        $data['id'] = (int) $req->get_param( 'id' );
        $id = RZPZ_CRM_DB::upsert_position( $data );
        if ( ! $id ) return new WP_REST_Response( [ 'message' => 'Fejl' ], 400 );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── REST: Applications ───────────────────────────────────────────────────

    public static function api_applications_list( WP_REST_Request $req ): WP_REST_Response {
        $filters = [
            'stage'       => $req->get_param( 'stage' )       ?? '',
            'position_id' => (int) ( $req->get_param( 'position_id' ) ?? 0 ),
            'search'      => $req->get_param( 'search' )      ?? '',
            'limit'       => min( (int) ( $req->get_param( 'limit' )  ?? 200 ), 500 ),
            'offset'      => (int) ( $req->get_param( 'offset' ) ?? 0 ),
        ];
        return new WP_REST_Response( RZPZ_CRM_DB::get_applications( array_filter( $filters, fn($v) => $v !== '' && $v !== 0 || is_numeric( $v ) ) ), 200 );
    }

    public static function api_applications_get( WP_REST_Request $req ): WP_REST_Response {
        $app = RZPZ_CRM_DB::get_application( (int) $req->get_param( 'id' ) );
        if ( ! $app ) return new WP_REST_Response( [ 'message' => 'Ikke fundet' ], 404 );
        return new WP_REST_Response( $app, 200 );
    }

    public static function api_applications_create( WP_REST_Request $req ): WP_REST_Response {
        $data = $req->get_json_params();

        // Opret eller find ansøger
        $applicant_id = RZPZ_CRM_DB::upsert_applicant( $data );
        if ( ! $applicant_id ) return new WP_REST_Response( [ 'message' => 'Ansøger-data mangler (navn + email påkrævet)' ], 400 );

        $position_id = (int) ( $data['position_id'] ?? 0 );
        if ( ! $position_id ) return new WP_REST_Response( [ 'message' => 'position_id mangler' ], 400 );

        $app_id = RZPZ_CRM_DB::insert_application( $applicant_id, $position_id, $data['source'] ?? 'other' );
        if ( ! $app_id ) return new WP_REST_Response( [ 'message' => 'Kunne ikke oprette ansøgning' ], 500 );

        // Send bekræftelses-email automatisk
        self::send_stage_email( $app_id, 'stage_ny' );

        return new WP_REST_Response( [ 'id' => $app_id ], 201 );
    }

    public static function api_applications_move_stage( WP_REST_Request $req ): WP_REST_Response {
        $id    = (int) $req->get_param( 'id' );
        $stage = sanitize_text_field( $req->get_json_params()['stage'] ?? '' );
        $note  = sanitize_textarea_field( $req->get_json_params()['note'] ?? '' );

        if ( ! array_key_exists( $stage, RZPZ_CRM_DB::PIPELINE_STAGES ) ) {
            return new WP_REST_Response( [ 'message' => 'Ugyldig stage' ], 400 );
        }

        $ok = RZPZ_CRM_DB::move_stage( $id, $stage, get_current_user_id(), $note );
        if ( ! $ok ) return new WP_REST_Response( [ 'message' => 'Ansøgning ikke fundet' ], 404 );

        // Send automatisk stage-email
        self::send_stage_email( $id, 'stage_' . $stage );

        // Rubix-integration: synk hvis ansat
        if ( $stage === 'ansat' ) {
            RZPZ_CRM_DB::sync_to_rubix( $id );
            // Opret evt. i Crew-modulet (hvis tilgængeligt)
            self::maybe_create_crew_member( $id );
        }

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    public static function api_applications_update( WP_REST_Request $req ): WP_REST_Response {
        $id  = (int) $req->get_param( 'id' );
        $ok  = RZPZ_CRM_DB::update_application( $id, $req->get_json_params() );
        return new WP_REST_Response( [ 'ok' => $ok ], $ok ? 200 : 400 );
    }

    public static function api_applications_delete( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $id  = (int) $req->get_param( 'id' );
        $app = RZPZ_CRM_DB::get_application( $id );
        if ( ! $app ) return new WP_REST_Response( [ 'message' => 'Ikke fundet' ], 404 );

        // Annullér eventuel planlagt afvisningsemail
        RZPZ_CRM_DB::cancel_rejection( $id );

        // Slet afhængige rækker (historik + kommunikationslog bevares som audit trail)
        $wpdb->delete( $wpdb->prefix . 'rzpz_crm_scheduled', [ 'application_id' => $id ] );
        $wpdb->delete( $wpdb->prefix . 'rzpz_crm_applications', [ 'id' => $id ] );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── REST: History + Comms ────────────────────────────────────────────────

    public static function api_history( WP_REST_Request $req ): WP_REST_Response {
        return new WP_REST_Response( RZPZ_CRM_DB::get_history( (int) $req->get_param( 'id' ) ), 200 );
    }

    public static function api_communications( WP_REST_Request $req ): WP_REST_Response {
        return new WP_REST_Response( RZPZ_CRM_DB::get_communications( (int) $req->get_param( 'id' ) ), 200 );
    }

    // ── REST: Send email/SMS ─────────────────────────────────────────────────

    public static function api_send_comm( WP_REST_Request $req ): WP_REST_Response {
        $id          = (int) $req->get_param( 'id' );
        $params      = $req->get_json_params();
        $type        = sanitize_text_field( $params['type'] ?? 'email' );
        $template_id = (int) ( $params['template_id'] ?? 0 );
        $custom_body = wp_kses_post( $params['body'] ?? '' );
        $custom_subj = sanitize_text_field( $params['subject'] ?? '' );

        $app = RZPZ_CRM_DB::get_application( $id );
        if ( ! $app ) return new WP_REST_Response( [ 'message' => 'Ansøgning ikke fundet' ], 404 );

        if ( $template_id ) {
            $tpl = RZPZ_CRM_DB::get_template( $template_id );
            if ( ! $tpl ) return new WP_REST_Response( [ 'message' => 'Skabelon ikke fundet' ], 404 );
            $body = self::render_template( $tpl->body, $app );
            $subj = self::render_template( $tpl->subject ?? '', $app );
        } else {
            $body = self::render_template( $custom_body, $app );
            $subj = self::render_template( $custom_subj, $app );
        }

        if ( $type === 'sms' ) {
            $ok = self::send_sms( $app->phone, $body );
        } else {
            $ok = wp_mail( $app->email, $subj, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
        }

        RZPZ_CRM_DB::log_communication( $id, $type, $body, $subj, $ok ? 'sent' : 'failed' );
        return new WP_REST_Response( [ 'ok' => $ok ], $ok ? 200 : 500 );
    }

    // ── REST: Templates ──────────────────────────────────────────────────────

    public static function api_templates_list(): WP_REST_Response {
        return new WP_REST_Response( RZPZ_CRM_DB::get_templates(), 200 );
    }

    public static function api_templates_save( WP_REST_Request $req ): WP_REST_Response {
        $id = RZPZ_CRM_DB::upsert_template( $req->get_json_params() );
        if ( ! $id ) return new WP_REST_Response( [ 'message' => 'Ugyldig data' ], 400 );
        return new WP_REST_Response( [ 'id' => $id ], 200 );
    }

    public static function api_templates_update( WP_REST_Request $req ): WP_REST_Response {
        $data       = $req->get_json_params();
        $data['id'] = (int) $req->get_param( 'id' ); // ensure URL id takes precedence
        $id         = RZPZ_CRM_DB::upsert_template( $data );
        if ( ! $id ) return new WP_REST_Response( [ 'message' => 'Ugyldig data' ], 400 );
        return new WP_REST_Response( [ 'id' => $id ], 200 );
    }

    public static function api_templates_delete( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'rzpz_crm_templates', [ 'id' => (int) $req->get_param( 'id' ) ] );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── REST: Stats ──────────────────────────────────────────────────────────

    public static function api_stats(): WP_REST_Response {
        return new WP_REST_Response( [
            'pipeline'   => RZPZ_CRM_DB::get_pipeline_counts(),
            'sources'    => RZPZ_CRM_DB::get_source_stats( 30 ),
            'conversion' => RZPZ_CRM_DB::get_conversion_rate( 30 ),
        ], 200 );
    }

    // ── REST: Cancel rejection ───────────────────────────────────────────────

    public static function api_cancel_rejection( WP_REST_Request $req ): WP_REST_Response {
        RZPZ_CRM_DB::cancel_rejection( (int) $req->get_param( 'id' ) );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── REST: Public applicant status (token) ────────────────────────────────

    public static function api_applicant_status( WP_REST_Request $req ): WP_REST_Response {
        $token = sanitize_text_field( $req->get_param( 'token' ) );
        $applicant = RZPZ_CRM_DB::get_applicant_by_token( $token );
        if ( ! $applicant ) return new WP_REST_Response( [ 'message' => 'Ugyldig link' ], 404 );

        global $wpdb;
        $apps = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.stage, a.created_at, pos.title AS position_title
             FROM {$wpdb->prefix}rzpz_crm_applications a
             JOIN {$wpdb->prefix}rzpz_crm_positions pos ON a.position_id = pos.id
             WHERE a.applicant_id = %d
             ORDER BY a.created_at DESC",
            $applicant->id
        ) );

        return new WP_REST_Response( [
            'name'         => $applicant->first_name,
            'applications' => $apps,
        ], 200 );
    }

    // ── Email helpers ────────────────────────────────────────────────────────

    /**
     * Renderer merge-tags i skabelon.
     * Understøttede tags: {{first_name}}, {{last_name}}, {{position_title}},
     *                     {{status_url}}, {{stage_label}}
     */
    public static function render_template( string $tpl, object $app ): string {
        $stages = RZPZ_CRM_DB::PIPELINE_STAGES;
        $status_url = site_url( '/ansogningsstatus/?token=' . ( $app->token ?? '' ) );

        $pairs = [
            '{{first_name}}'     => esc_html( $app->first_name ?? '' ),
            '{{last_name}}'      => esc_html( $app->last_name  ?? '' ),
            '{{position_title}}' => esc_html( $app->position_title ?? '' ),
            '{{stage_label}}'    => esc_html( $stages[ $app->stage ?? '' ] ?? '' ),
            '{{status_url}}'     => esc_url( $status_url ),
        ];

        return str_replace( array_keys( $pairs ), array_values( $pairs ), $tpl );
    }

    /** Find og send standard-email for stage-trigger. */
    private static function send_stage_email( int $application_id, string $trigger ): void {
        global $wpdb;
        $tpl = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crm_templates WHERE `trigger` = %s AND type = 'email' AND is_default = 1 LIMIT 1",
            $trigger
        ) );
        if ( ! $tpl ) return;

        $app  = RZPZ_CRM_DB::get_application( $application_id );
        if ( ! $app || empty( $app->email ) ) return;

        // Afslag-emails sendes ikke her — de er planlagt via scheduled-tabel
        if ( $trigger === 'stage_afslag' ) return;

        $body = self::render_template( $tpl->body, $app );
        $subj = self::render_template( $tpl->subject ?? '', $app );

        $sent = wp_mail( $app->email, $subj, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
        RZPZ_CRM_DB::log_communication( $application_id, 'email', $body, $subj, $sent ? 'sent' : 'failed' );
    }

    /** Send SMS via GatewayAPI (konfigureret i Indstillinger) */
    private static function send_sms( string $phone, string $message ): bool {
        $opts  = get_option( 'rzpa_settings', [] );
        $token = $opts['gatewayapi_token'] ?? '';
        if ( empty( $token ) || empty( $phone ) ) return false;

        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = '+45' . ltrim( $phone, '0' );
        }

        $sender = sanitize_text_field( $opts['sms_sender'] ?? 'Rezponz' );
        $sender = substr( preg_replace( '/[^A-Za-z0-9 ]/', '', $sender ), 0, 11 ) ?: 'Rezponz';

        $res = wp_remote_post( 'https://gatewayapi.com/rest/mtsms', [
            'headers' => [
                'Authorization' => 'Token ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'sender'     => $sender,
                'message'    => $message,
                'recipients' => [ [ 'msisdn' => ltrim( $phone, '+' ) ] ],
            ] ),
            'timeout' => 10,
        ] );

        return ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) < 300;
    }

    // ── Cron: afsend planlagte afslag ────────────────────────────────────────

    public static function dispatch_rejections(): void {
        global $wpdb;
        $due = RZPZ_CRM_DB::get_due_rejections();

        foreach ( $due as $scheduled ) {
            // Atomically claim the row: mark 'processing' only if still 'pending'
            $claimed = $wpdb->update(
                $wpdb->prefix . 'rzpz_crm_scheduled',
                [ 'status' => 'processing' ],
                [ 'id' => (int) $scheduled->id, 'status' => 'pending' ]
            );
            if ( ! $claimed ) continue; // another process got it

            $app = RZPZ_CRM_DB::get_application( (int) $scheduled->application_id );
            if ( ! $app ) {
                RZPZ_CRM_DB::mark_rejection_sent( (int) $scheduled->id );
                continue;
            }

            // Hent afslag-skabelon
            $tpl = $scheduled->template_id ? RZPZ_CRM_DB::get_template( (int) $scheduled->template_id ) : null;
            if ( ! $tpl ) {
                $tpl = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}rzpz_crm_templates WHERE `trigger` = 'stage_afslag' AND is_default = 1 LIMIT 1" );
            }

            if ( ! $tpl || empty( $app->email ) ) {
                // Kan ikke sende — marker som fejlet så den ikke forsøges igen
                $wpdb->update(
                    $wpdb->prefix . 'rzpz_crm_scheduled',
                    [ 'status' => 'failed', 'sent_at' => current_time( 'mysql' ) ],
                    [ 'id' => (int) $scheduled->id ]
                );
                continue;
            }

            $body = self::render_template( $tpl->body, $app );
            $subj = self::render_template( $tpl->subject ?? '', $app );
            $sent = wp_mail( $app->email, $subj, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
            RZPZ_CRM_DB::log_communication( (int) $scheduled->application_id, 'email', $body, $subj, $sent ? 'sent' : 'failed' );

            if ( $sent ) {
                RZPZ_CRM_DB::mark_rejection_sent( (int) $scheduled->id );
                $wpdb->update(
                    $wpdb->prefix . 'rzpz_crm_applications',
                    [ 'rejection_sent_at' => current_time( 'mysql' ) ],
                    [ 'id' => (int) $scheduled->application_id ]
                );
            } else {
                // Email fejlede — sæt tilbage til pending så den kan genprøves
                $wpdb->update(
                    $wpdb->prefix . 'rzpz_crm_scheduled',
                    [ 'status' => 'pending' ],
                    [ 'id' => (int) $scheduled->id ]
                );
                error_log( '[RezCRM] Afvisningsemail fejlede for application #' . (int) $scheduled->application_id );
            }
        }
    }

    // ── Cron: GDPR auto-sletning ─────────────────────────────────────────────

    public static function gdpr_cleanup(): void {
        $count = RZPZ_CRM_DB::gdpr_auto_delete();
        if ( $count > 0 ) {
            error_log( "[RezCRM] GDPR auto-delete: {$count} ansøger(e) anonymiseret" );
        }
    }

    // ── Crew-integration ─────────────────────────────────────────────────────

    private static function maybe_create_crew_member( int $application_id ): void {
        if ( ! class_exists( 'RZPZ_Crew_DB' ) ) return;
        $app = RZPZ_CRM_DB::get_application( $application_id );
        if ( ! $app ) return;

        // Tjek om medarbejder allerede eksisterer (email-match)
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rzpz_crew_members WHERE email = %s LIMIT 1",
            $app->email
        ) );
        if ( $exists ) return;

        $wpdb->insert( $wpdb->prefix . 'rzpz_crew_members', [
            'first_name' => $app->first_name,
            'last_name'  => $app->last_name,
            'email'      => $app->email,
            'phone'      => $app->phone ?? '',
            'department' => $app->department ?? '',
            'status'     => 'active',
            'start_date' => current_time( 'Y-m-d' ),
        ] );
    }

    // ── GDPR Privacy API ────────────────────────────────────────────────────

    public static function register_exporter( array $exporters ): array {
        $exporters['rzpz-crm'] = [
            'exporter_friendly_name' => 'RezCRM Ansøgerdata',
            'callback'               => [ __CLASS__, 'gdpr_export' ],
        ];
        return $exporters;
    }

    public static function register_eraser( array $erasers ): array {
        $erasers['rzpz-crm'] = [
            'eraser_friendly_name' => 'RezCRM Ansøgerdata',
            'callback'             => [ __CLASS__, 'gdpr_erase' ],
        ];
        return $erasers;
    }

    public static function gdpr_export( string $email ): array {
        global $wpdb;
        $applicant = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crm_applicants WHERE email = %s", $email
        ) );
        if ( ! $applicant ) return [ 'data' => [], 'done' => true ];

        $data = [];
        $data[] = [
            'group_id'    => 'rezcrm-applicant',
            'group_label' => 'RezCRM Ansøgerprofil',
            'item_id'     => 'applicant-' . $applicant->id,
            'data'        => [
                [ 'name' => 'Navn',    'value' => $applicant->first_name . ' ' . $applicant->last_name ],
                [ 'name' => 'Email',   'value' => $applicant->email ],
                [ 'name' => 'Telefon', 'value' => $applicant->phone ],
                [ 'name' => 'By',      'value' => $applicant->city ],
            ],
        ];

        return [ 'data' => $data, 'done' => true ];
    }

    public static function gdpr_erase( string $email ): array {
        global $wpdb;
        $applicant = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rzpz_crm_applicants WHERE email = %s", $email
        ) );
        if ( ! $applicant ) return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];

        $id = (int) $applicant->id;
        $wpdb->update( $wpdb->prefix . 'rzpz_crm_applicants', [
            'first_name'   => 'Slettet',
            'last_name'    => 'Bruger',
            'email'        => 'deleted_' . $id . '@rezponz.dk',
            'phone'        => null,
            'address'      => null,
            'city'         => null,
            'cover_letter' => null,
            'cv_url'       => null,
            'notes'        => '[Slettet via GDPR Privacy Tool]',
            'gdpr_consent' => 0,
            'delete_after' => null,
        ], [ 'id' => $id ] );

        // Anonymisér kommunikationslog — fjern email-bodies der indeholder PII
        $app_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rzpz_crm_applications WHERE applicant_id = %d", $id
        ) );
        foreach ( $app_ids as $app_id ) {
            $wpdb->update(
                $wpdb->prefix . 'rzpz_crm_communications',
                [ 'body' => '[Slettet via GDPR Privacy Tool]', 'subject' => '[Slettet]' ],
                [ 'application_id' => (int) $app_id ]
            );
        }

        return [ 'items_removed' => true, 'items_retained' => false, 'messages' => [], 'done' => true ];
    }
}
