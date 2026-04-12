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

        register_rest_route( 'rzpa/v1', '/quiz/submission/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'set_status' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            ],
        ] );

        register_rest_route( 'rzpa/v1', '/quiz/submission/(?P<id>\d+)/send-mail', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'send_invitation' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            ],
        ] );

        register_rest_route( 'rzpa/v1', '/quiz/email-template', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_email_template' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'rzpa/v1', '/quiz/email-template', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'save_email_template' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );
    }

    // ── Standardskabelon ─────────────────────────────────────────────────────

    private static function default_template(): array {
        return [
            'subject' => 'Vi vil gerne invitere dig til Rezponz 👋',
            'body'    => "Hej {navn},\n\nTak fordi du har udfyldt vores profil-quiz! Vi kunne rigtig godt tænke os at lære dig bedre at kende.\n\nVi vil gerne invitere dig til at komme forbi vores kontor i Aalborg, så du kan se, hvad vi laver, møde teamet og stille alle de spørgsmål, du måtte have. Der er ingen forpligtelser — det er blot en uformel snak over en kop kaffe ☕\n\nHar du lyst, så svar blot på denne mail med et tidspunkt, der passer dig — eller ring til os.\n\nVi glæder os til at høre fra dig!",
        ];
    }

    // ── GET /quiz/email-template ──────────────────────────────────────────────

    public static function get_email_template( WP_REST_Request $req ): WP_REST_Response {
        $saved = get_option( 'rzpa_quiz_invite_tpl', [] );
        $tpl   = array_merge( self::default_template(), $saved );
        return new WP_REST_Response( [ 'ok' => true, 'template' => $tpl ], 200 );
    }

    // ── POST /quiz/email-template ─────────────────────────────────────────────

    public static function save_email_template( WP_REST_Request $req ): WP_REST_Response {
        $p       = $req->get_json_params();
        $subject = sanitize_text_field( $p['subject'] ?? '' );
        $body    = sanitize_textarea_field( $p['body'] ?? '' );

        if ( ! $subject || ! $body ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Emne og besked er påkrævet.' ], 400 );
        }

        update_option( 'rzpa_quiz_invite_tpl', [ 'subject' => $subject, 'body' => $body ] );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── GET /quiz/submission/{id} ─────────────────────────────────────────────

    public static function get_submission( WP_REST_Request $req ): WP_REST_Response {
        $data = RZPA_Quiz_DB::get_submission_detail( (int) $req['id'] );
        if ( ! $data ) {
            return new WP_REST_Response( [ 'error' => 'Ikke fundet' ], 404 );
        }
        return new WP_REST_Response( $data, 200 );
    }

    // ── POST /quiz/submission/{id}/status ────────────────────────────────────

    public static function set_status( WP_REST_Request $req ): WP_REST_Response {
        $id     = (int) $req['id'];
        $status = sanitize_text_field( $req->get_json_params()['status'] ?? '' ) ?: null;
        $ok     = RZPA_Quiz_DB::set_candidate_status( $id, $status ?: null );
        return new WP_REST_Response( [ 'ok' => $ok ], $ok ? 200 : 400 );
    }

    // ── POST /quiz/submission/{id}/send-mail ──────────────────────────────────

    public static function send_invitation( WP_REST_Request $req ): WP_REST_Response {
        $id      = (int) $req['id'];
        $params  = $req->get_json_params();
        $to      = sanitize_email( $params['to'] ?? '' );
        $subject = sanitize_text_field( $params['subject'] ?? '' );
        $body    = sanitize_textarea_field( $params['body'] ?? '' );

        if ( ! $to || ! is_email( $to ) ) {
            return new WP_REST_Response( [ 'error' => 'Kandidaten har ingen e-mailadresse.' ], 400 );
        }
        if ( ! $subject || ! $body ) {
            return new WP_REST_Response( [ 'error' => 'Emne og besked er påkrævet.' ], 400 );
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lie Svenningsen <' . self::ADMIN_EMAIL . '>',
            'Reply-To: ' . self::ADMIN_EMAIL,
        ];

        $logo_url   = esc_url( RZPA_URL . 'assets/Rezponz-logo.png' );
        $body_html  = nl2br( esc_html( $body ) );
        $html_email = self::build_html_email( $body_html, $logo_url );

        $sent = wp_mail( $to, $subject, $html_email, $headers );

        if ( $sent ) {
            RZPA_Quiz_DB::mark_mail_sent( $id );
            RZPA_Quiz_DB::set_candidate_status( $id, 'interessant' );
        }

        return new WP_REST_Response( [ 'sent' => $sent ], $sent ? 200 : 500 );
    }

    private static function build_html_email( string $body_html, string $logo_url ): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="da">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 0">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%">

        <!-- Header med logo -->
        <tr>
          <td style="background:#0a0a0a;padding:24px 32px">
            <img src="{$logo_url}" alt="Rezponz" style="height:36px;display:block">
          </td>
        </tr>

        <!-- Brødtekst -->
        <tr>
          <td style="padding:36px 32px 24px;color:#222222;font-size:15px;line-height:1.7">
            {$body_html}
          </td>
        </tr>

        <!-- Signatur -->
        <tr>
          <td style="padding:0 32px 32px">
            <table cellpadding="0" cellspacing="0" style="border-top:1px solid #e8e8e8;padding-top:20px;width:100%">
              <tr>
                <td style="vertical-align:middle">
                  <img src="{$logo_url}" alt="Rezponz" style="height:28px;display:block;margin-bottom:8px">
                  <span style="font-size:14px;font-weight:700;color:#111">Lie Svenningsen</span><br>
                  <span style="font-size:13px;color:#666">Rezponz</span><br>
                  <a href="mailto:lie@rezponz.dk" style="font-size:13px;color:#5d8089;text-decoration:none">lie@rezponz.dk</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f9f9f9;padding:16px 32px;border-top:1px solid #eeeeee;font-size:12px;color:#aaaaaa;text-align:center">
            Rezponz · Aalborg · <a href="https://rezponz.dk" style="color:#aaa;text-decoration:none">rezponz.dk</a>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
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
