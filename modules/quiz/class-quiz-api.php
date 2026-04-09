<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Quiz_API {

    const ADMIN_EMAIL = 'lie@rezponz.dk';

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'rzpa/v1', '/quiz', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_quiz' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'rzpa/v1', '/quiz/submit', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'submit' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'rzpa/v1', '/quiz/submission/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_submission' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            ],
        ] );
    }

    // ── GET /quiz/submission/{id} ─────────────────────────────────────────────

    public static function get_submission( WP_REST_Request $req ): WP_REST_Response {
        $data = RZPA_Quiz_DB::get_submission_detail( (int) $req['id'] );
        if ( ! $data ) {
            return new WP_REST_Response( [ 'error' => 'Ikke fundet' ], 404 );
        }
        return new WP_REST_Response( $data, 200 );
    }

    // ── GET /quiz ─────────────────────────────────────────────────────────────

    public static function get_quiz( WP_REST_Request $req ): WP_REST_Response {
        $data = RZPA_Quiz_DB::get_quiz_data();
        return new WP_REST_Response( [ 'success' => true, 'data' => $data ], 200 );
    }

    // ── POST /quiz/submit ─────────────────────────────────────────────────────

    public static function submit( WP_REST_Request $req ): WP_REST_Response {
        // Rate limit: max 5 per IP per 60 seconds
        $ip      = self::client_ip();
        $rl_key  = 'rzpa_quiz_rl_' . md5( $ip );
        $rl_hits = (int) get_transient( $rl_key );
        if ( $rl_hits >= 5 ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'For mange forsøg. Prøv igen om lidt.' ], 429 );
        }
        set_transient( $rl_key, $rl_hits + 1, 60 );

        $body = $req->get_json_params() ?: $req->get_params();

        // Honeypot check
        if ( ! empty( $body['website'] ) || ! empty( $body['company'] ) ) {
            return new WP_REST_Response( [ 'success' => true, 'honeypot' => true ], 200 );
        }

        // Validate required fields
        $name            = sanitize_text_field( $body['name'] ?? '' );
        $phone           = sanitize_text_field( $body['phone'] ?? '' );
        $email           = sanitize_email( $body['email'] ?? '' );
        $consent         = ! empty( $body['consent'] );
        $contact_consent = ! empty( $body['contact_consent'] );
        $answers         = $body['answers'] ?? [];

        if ( ! $name )            return self::err( 'Navn er påkrævet' );
        if ( ! $email )           return self::err( 'Email er påkrævet' );
        if ( ! $phone )           return self::err( 'Telefonnummer er påkrævet' );
        if ( ! $consent )         return self::err( 'Du skal acceptere GDPR-vilkårene' );
        if ( ! $contact_consent ) return self::err( 'Du skal give tilladelse til at vi må kontakte dig' );
        if ( ! is_array( $answers ) || count( $answers ) < 1 ) return self::err( 'Ingen svar registreret' );

        // Normalize phone → +45XXXXXXXX
        $phone = self::normalize_phone( $phone );

        // Load profiles for scoring
        $profiles = RZPA_Quiz_DB::get_profiles();
        if ( empty( $profiles ) ) return self::err( 'Quiz er ikke konfigureret' );

        // Calculate scores
        $scores = [];
        foreach ( $profiles as $p ) {
            $scores[ $p['slug'] ] = 0;
        }

        $quiz_data = RZPA_Quiz_DB::get_quiz_data();
        $answers_map = [];
        foreach ( $quiz_data['questions'] as $q ) {
            foreach ( $q['answers'] as $a ) {
                $answers_map[ (int) $a['id'] ] = $a['weights'];
            }
        }

        foreach ( $answers as $ans ) {
            $aid = (int) ( $ans['answerId'] ?? 0 );
            if ( isset( $answers_map[ $aid ] ) ) {
                foreach ( $answers_map[ $aid ] as $slug => $weight ) {
                    if ( isset( $scores[ $slug ] ) ) {
                        $scores[ $slug ] += (int) $weight;
                    }
                }
            }
        }

        // Rank profiles by score
        $ranked = $profiles;
        usort( $ranked, fn( $a, $b ) => ( $scores[ $b['slug'] ] ?? 0 ) <=> ( $scores[ $a['slug'] ] ?? 0 ) );

        $winner    = $ranked[0];
        $secondary = $ranked[1] ?? $ranked[0];

        // Enrich with scores
        foreach ( $ranked as &$r ) {
            $r['score']    = $scores[ $r['slug'] ] ?? 0;
            $max_possible  = count( $quiz_data['questions'] ) * 3;
            $r['pct']      = $max_possible > 0 ? round( $r['score'] / $max_possible * 100 ) : 0;
        }
        unset( $r );

        $winner['score']    = $scores[ $winner['slug'] ]    ?? 0;
        $secondary['score'] = $scores[ $secondary['slug'] ] ?? 0;

        $withdraw_token = wp_generate_password( 32, false );

        // Save to DB
        $sub_id = RZPA_Quiz_DB::save_submission( [
            'name'                 => $name,
            'phone'                => $phone,
            'email'                => $email,
            'winning_profile_id'   => (int) $winner['id'],
            'secondary_profile_id' => (int) $secondary['id'],
            'scores'               => $scores,
            'answers'              => $answers,
            'consent'              => $consent,
            'contact_consent'      => $contact_consent,
            'withdraw_token'       => $withdraw_token,
            'ip'                   => $ip,
        ] );

        // Send emails
        if ( $sub_id ) {
            RZPA_Quiz_Mailer::send_user_email( $name, $email, $winner, $secondary, $ranked, $scores, (int) $sub_id );
            RZPA_Quiz_Mailer::send_admin_email( $name, $phone, $email, $winner, $scores, (int) $sub_id );
        }

        return new WP_REST_Response( [
            'success'          => true,
            'submissionId'     => $sub_id,
            'winningProfile'   => $winner,
            'secondaryProfile' => $secondary,
            'allProfiles'      => $ranked,
            'withdrawToken'    => $withdraw_token,
        ], 200 );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function err( string $msg, int $status = 400 ): WP_REST_Response {
        return new WP_REST_Response( [ 'success' => false, 'error' => $msg ], $status );
    }

    private static function normalize_phone( string $phone ): string {
        $digits = preg_replace( '/[^0-9]/', '', $phone );
        if ( strlen( $digits ) === 8 ) return '+45' . $digits;
        if ( str_starts_with( $digits, '45' ) && strlen( $digits ) === 10 ) return '+' . $digits;
        return $phone;
    }

    private static function client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                return sanitize_text_field( explode( ',', $_SERVER[ $key ] )[0] );
            }
        }
        return '0.0.0.0';
    }
}
