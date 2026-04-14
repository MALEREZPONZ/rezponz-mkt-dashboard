<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_CRM_Forms
 *
 * Form Builder + Frontend renderer + REST API for forms
 * Shortcode: [rezcrm_form id="1"] eller [rezcrm_form slug="ansoegning"]
 */
class RZPZ_CRM_Forms {

    public static function init(): void {
        add_shortcode( 'rezcrm_form',  [ __CLASS__, 'render_shortcode' ] );
        add_action( 'rest_api_init',   [ __CLASS__, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
    }

    // ── Frontend enqueue ─────────────────────────────────────────────────────

    public static function enqueue_frontend(): void {
        global $post;
        // Kun enqueue hvis shortcode er på siden
        if ( ! $post || ! has_shortcode( $post->post_content, 'rezcrm_form' ) ) return;
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );

        wp_enqueue_style(  'rzpz-crm-form', RZPA_URL . 'modules/rezcrm/assets/rezcrm-form.css', [], RZPA_VERSION );
        wp_enqueue_script( 'rzpz-crm-form', RZPA_URL . 'modules/rezcrm/assets/rezcrm-form.js',  [ 'jquery' ], RZPA_VERSION, true );
        wp_localize_script( 'rzpz-crm-form', 'RZPZ_FORM', [
            'apiBase' => rest_url( 'rzpa/v1/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    // ── Shortcode ────────────────────────────────────────────────────────────

    public static function render_shortcode( array $atts ): string {
        $atts = shortcode_atts( [
            'id'       => 0,
            'slug'     => '',
            'position' => 0,
        ], $atts, 'rezcrm_form' );

        if ( $atts['slug'] ) {
            $form = RZPZ_CRM_Forms_DB::get_form_by_slug( sanitize_title( $atts['slug'] ) );
        } elseif ( $atts['id'] ) {
            $form = RZPZ_CRM_Forms_DB::get_form( (int) $atts['id'] );
        } else {
            return '<p>Ugyldig formular-konfiguration.</p>';
        }

        if ( ! $form || ! $form->is_active ) {
            return '<p>Formularen er ikke tilgængelig lige nu.</p>';
        }

        $fields = RZPZ_CRM_Forms_DB::get_fields( (int) $form->id );
        if ( empty( $fields ) ) {
            return '<p>Formularen er ikke konfigureret endnu.</p>';
        }

        $position_id = ! empty( $atts['position'] ) ? (int) $atts['position'] : (int) ( $form->position_id ?? 0 );

        ob_start();
        include __DIR__ . '/views/form-frontend.php';
        return ob_get_clean();
    }

    // ── REST routes ──────────────────────────────────────────────────────────

    public static function register_routes(): void {
        $ns  = 'rzpa/v1';
        $cap = fn() => current_user_can( 'manage_options' );

        // Admin: formularer
        register_rest_route( $ns, 'crm/forms',            [ 'methods' => 'GET',    'callback' => [ __CLASS__, 'api_forms_list' ],   'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms',            [ 'methods' => 'POST',   'callback' => [ __CLASS__, 'api_forms_save' ],   'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms/(?P<id>\d+)','methods' => 'GET',      'callback' => [ __CLASS__, 'api_form_get' ],     'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms/(?P<id>\d+)','methods' => 'PUT',      'callback' => [ __CLASS__, 'api_form_update' ],  'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms/(?P<id>\d+)/fields', [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'api_fields_get' ],  'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms/(?P<id>\d+)/fields', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'api_fields_save' ], 'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms/(?P<id>\d+)/stats',  [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'api_form_stats' ],  'permission_callback' => $cap ] );
        register_rest_route( $ns, 'crm/forms/(?P<id>\d+)', [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'api_form_delete' ], 'permission_callback' => $cap ] );

        // Frontend: start session + submit form (offentlig, rate-limited ved IP)
        register_rest_route( $ns, 'crm/form-session',     [ 'methods' => 'POST',   'callback' => [ __CLASS__, 'api_start_session' ],  'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, 'crm/form-session/step',[ 'methods' => 'PATCH',  'callback' => [ __CLASS__, 'api_update_step' ],    'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, 'crm/form-submit',      [ 'methods' => 'POST',   'callback' => [ __CLASS__, 'api_submit' ],          'permission_callback' => '__return_true' ] );
    }

    // ── REST: Admin ──────────────────────────────────────────────────────────

    public static function api_forms_list(): WP_REST_Response {
        return new WP_REST_Response( RZPZ_CRM_Forms_DB::get_forms(), 200 );
    }

    public static function api_forms_save( WP_REST_Request $req ): WP_REST_Response {
        $id = RZPZ_CRM_Forms_DB::upsert_form( $req->get_json_params() );
        if ( ! $id ) return new WP_REST_Response( [ 'message' => 'Ugyldig data' ], 400 );
        return new WP_REST_Response( [ 'id' => $id ], 201 );
    }

    public static function api_form_get( WP_REST_Request $req ): WP_REST_Response {
        $form = RZPZ_CRM_Forms_DB::get_form( (int) $req->get_param( 'id' ) );
        if ( ! $form ) return new WP_REST_Response( [ 'message' => 'Ikke fundet' ], 404 );
        return new WP_REST_Response( $form, 200 );
    }

    public static function api_form_update( WP_REST_Request $req ): WP_REST_Response {
        $data       = $req->get_json_params();
        $data['id'] = (int) $req->get_param( 'id' );
        RZPZ_CRM_Forms_DB::upsert_form( $data );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    public static function api_form_delete( WP_REST_Request $req ): WP_REST_Response {
        RZPZ_CRM_Forms_DB::delete_form( (int) $req->get_param( 'id' ) );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    public static function api_fields_get( WP_REST_Request $req ): WP_REST_Response {
        return new WP_REST_Response( RZPZ_CRM_Forms_DB::get_fields( (int) $req->get_param( 'id' ) ), 200 );
    }

    public static function api_fields_save( WP_REST_Request $req ): WP_REST_Response {
        $id     = (int) $req->get_param( 'id' );
        $fields = $req->get_json_params()['fields'] ?? [];
        RZPZ_CRM_Forms_DB::save_fields( $id, $fields );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    public static function api_form_stats( WP_REST_Request $req ): WP_REST_Response {
        $days = (int) ( $req->get_param( 'days' ) ?? 30 );
        return new WP_REST_Response( RZPZ_CRM_Forms_DB::get_form_stats( (int) $req->get_param( 'id' ), $days ), 200 );
    }

    // ── REST: Frontend ───────────────────────────────────────────────────────

    /** Opretter en ny tracking-session (kaldt ved form-load) */
    public static function api_start_session( WP_REST_Request $req ): WP_REST_Response {
        $params  = $req->get_json_params();
        $form_id = (int) ( $params['form_id'] ?? 0 );
        if ( ! $form_id ) return new WP_REST_Response( [ 'message' => 'form_id mangler' ], 400 );

        $utm = [
            'utm_source'   => $params['utm_source']   ?? '',
            'utm_medium'   => $params['utm_medium']   ?? '',
            'utm_campaign' => $params['utm_campaign'] ?? '',
            'utm_content'  => $params['utm_content']  ?? '',
            'referrer'     => $params['referrer']     ?? '',
        ];

        $token = RZPZ_CRM_Forms_DB::create_session( $form_id, $utm );
        return new WP_REST_Response( [ 'token' => $token ], 200 );
    }

    /** Opdaterer det aktuelle skridt (progress tracking) */
    public static function api_update_step( WP_REST_Request $req ): WP_REST_Response {
        $params = $req->get_json_params();
        $token  = sanitize_text_field( $params['token'] ?? '' );
        $step   = (int) ( $params['step'] ?? 1 );
        if ( $token ) RZPZ_CRM_Forms_DB::update_session_step( $token, $step );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * Modtager og gemmer den komplette formular-indsendelse.
     * Opretter ansøger + ansøgning i CRM og markerer session som fuldført.
     */
    public static function api_submit( WP_REST_Request $req ): WP_REST_Response {
        // Basic rate limiting: max 5 indsendelser per IP per time
        $ip_key = 'rzpz_form_rl_' . hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );
        $count  = (int) get_transient( $ip_key );
        if ( $count >= 5 ) {
            return new WP_REST_Response( [ 'message' => 'For mange indsendelser. Prøv igen om en time.' ], 429 );
        }
        set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

        $params   = $req->get_json_params();
        $form_id  = (int) ( $params['form_id']    ?? 0 );
        $pos_id   = (int) ( $params['position_id'] ?? 0 );
        $token    = sanitize_text_field( $params['session_token'] ?? '' );
        $data     = $params['fields'] ?? [];

        if ( ! $form_id ) return new WP_REST_Response( [ 'message' => 'form_id mangler' ], 400 );

        $form = RZPZ_CRM_Forms_DB::get_form( $form_id );
        if ( ! $form ) return new WP_REST_Response( [ 'message' => 'Formular ikke fundet' ], 404 );

        $fields = RZPZ_CRM_Forms_DB::get_fields( $form_id );

        // Byg ansøger-data fra core_map felter
        $applicant_data = [ 'gdpr_consent' => 1 ];
        $extra_data     = [];

        foreach ( $fields as $field ) {
            if ( empty( $field->field_key ) || $field->field_type === 'section' ) continue;
            $val = sanitize_textarea_field( $data[ $field->field_key ] ?? '' );
            if ( $field->core_map && $val !== '' ) {
                $applicant_data[ $field->core_map ] = $val;
            } else {
                $extra_data[ $field->label ?? $field->field_key ] = $val;
            }
        }

        // Validation: kræv email + fornavn
        if ( empty( $applicant_data['email'] ) || empty( $applicant_data['first_name'] ) ) {
            return new WP_REST_Response( [ 'message' => 'Email og fornavn er påkrævet' ], 422 );
        }

        // Gem ekstra felter som noter
        if ( $extra_data ) {
            $notes = '';
            foreach ( $extra_data as $k => $v ) {
                if ( $v !== '' ) $notes .= $k . ': ' . $v . "\n";
            }
            $applicant_data['notes'] = trim( ( $applicant_data['notes'] ?? '' ) . "\n\n" . $notes );
        }

        // Opret ansøger
        $applicant_id = RZPZ_CRM_DB::upsert_applicant( $applicant_data );
        if ( ! $applicant_id ) {
            return new WP_REST_Response( [ 'message' => 'Kunne ikke oprette ansøger' ], 500 );
        }

        // Opret ansøgning hvis position_id er angivet
        $application_id = null;
        if ( $pos_id ) {
            $source = sanitize_text_field( $data['heard_from'] ?? 'website' );
            $application_id = RZPZ_CRM_DB::insert_application( $applicant_id, $pos_id, $source );
        }

        // Marker session som færdig
        if ( $token ) {
            RZPZ_CRM_Forms_DB::complete_session( $token, $application_id ?? 0 );
        }

        // Send bekræftelses-email
        if ( $application_id ) {
            // Send via RZPZ_RezCRM (bruger stage_ny skabelon)
            global $wpdb;
            $tpl = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}rzpz_crm_templates WHERE `trigger` = 'stage_ny' AND is_default = 1 LIMIT 1" );
            if ( $tpl ) {
                $app  = RZPZ_CRM_DB::get_application( (int) $application_id );
                if ( $app ) {
                    $body = RZPZ_RezCRM::render_template( $tpl->body, $app );
                    $subj = RZPZ_RezCRM::render_template( $tpl->subject ?? '', $app );
                    $sent = wp_mail( $app->email, $subj, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
                    RZPZ_CRM_DB::log_communication( (int) $application_id, 'email', $body, $subj, $sent ? 'sent' : 'failed' );
                }
            }

            // Notify admin
            if ( ! empty( $form->notify_email ) ) {
                wp_mail( $form->notify_email,
                    'Ny ansøgning modtaget — ' . ( $applicant_data['first_name'] . ' ' . ( $applicant_data['last_name'] ?? '' ) ),
                    'Ny ansøgning i RezCRM. Åbn dashboardet for at se detaljer.',
                    [ 'Content-Type: text/plain; charset=UTF-8' ]
                );
            }
        }

        // Validér redirect_url mod eget domæne — forhindrer open redirect
        $raw_redirect   = $form->redirect_url ?: '';
        $safe_redirect  = $raw_redirect ? wp_validate_redirect( $raw_redirect, '' ) : '';

        return new WP_REST_Response( [
            'ok'              => true,
            'application_id'  => $application_id,
            'success_message' => $form->success_message ?: '<h3>Tak! 🎉</h3><p>Vi vender tilbage hurtigst muligt.</p>',
            'redirect_url'    => $safe_redirect ?: null,
        ], 200 );
    }
}
