<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API for Rezponz Live Quiz.
 *
 * Player endpoints (public):
 *   POST /rezponz/v1/join          — two-step: step1={pin}, step2={game_id,nickname}
 *   GET  /rezponz/v1/state         — poll game state (player view, token-based)
 *   POST /rezponz/v1/answer        — submit answer ({token, option_index|slider_value})
 *
 * Host endpoints (nonce + rzlq_manage cap):
 *   GET  /rezponz/v1/host/state    — full state with correct answers
 *   POST /rezponz/v1/host/advance  — advance state machine
 *   POST /rezponz/v1/host/kick     — remove player from lobby
 */
class RZLQ_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        $ns = 'rezponz/v1';

        register_rest_route( $ns, '/join',   [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'join' ],         'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, '/state',  [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'player_state' ], 'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, '/answer', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'answer' ],       'permission_callback' => '__return_true' ] );

        register_rest_route( $ns, '/host/state',   [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'host_state' ],   'permission_callback' => [ __CLASS__, 'host_auth' ] ] );
        register_rest_route( $ns, '/host/advance', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'host_advance' ], 'permission_callback' => [ __CLASS__, 'host_auth' ] ] );
        register_rest_route( $ns, '/host/kick',    [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'host_kick' ],    'permission_callback' => [ __CLASS__, 'host_auth' ] ] );
    }

    public static function host_auth(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( RZLQ_Dept::CAP );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /join  — two-step join
    //
    // Step 1: { pin }              → { success, game_id }
    // Step 2: { game_id, nickname } → { success, token, nickname }
    // ══════════════════════════════════════════════════════════════════════════
    public static function join( WP_REST_Request $req ): WP_REST_Response {
        $pin      = sanitize_text_field( $req->get_param( 'pin' )      ?? '' );
        $game_id  = (int) ( $req->get_param( 'game_id' )               ?? 0 );
        $nickname = sanitize_text_field( $req->get_param( 'nickname' )  ?? '' );

        // ── Step 1: validate PIN ───────────────────────────────────────────
        if ( $pin && ! $game_id ) {
            $pin = preg_replace( '/\D/', '', $pin );
            if ( strlen( $pin ) !== 6 ) return self::err( 'Ugyldig PIN – skal være 6 cifre' );

            $game = RZLQ_DB::get_game_by_pin( $pin );
            if ( ! $game )                       return self::err( 'Spillet blev ikke fundet. Tjek PIN-koden.' );
            if ( $game['status'] !== 'waiting' ) return self::err( 'Spillet er allerede i gang – du kan ikke deltage nu.' );

            return self::ok( [ 'game_id' => (int) $game['id'] ] );
        }

        // ── Step 2: join with nickname ─────────────────────────────────────
        $nickname = mb_substr( trim( $nickname ), 0, 24 );
        if ( ! $game_id )              return self::err( 'game_id mangler' );
        if ( mb_strlen( $nickname ) < 2 ) return self::err( 'Kaldenavn skal være mindst 2 tegn' );

        $game = RZLQ_DB::get_game_by_id( $game_id );
        if ( ! $game )                       return self::err( 'Spillet blev ikke fundet.' );
        if ( $game['status'] !== 'waiting' ) return self::err( 'Spillet er allerede startet – du kan ikke deltage nu.' );

        $result = RZLQ_DB::join_game( $game_id, $nickname );
        if ( $result === false )        return self::err( 'Kaldenavnet er allerede i brug i dette spil' );

        return self::ok( [
            'player_id' => $result['player_id'],
            'token'     => $result['token'],
            'game_id'   => $game_id,
            'nickname'  => $nickname,
        ] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /state?token=&hash=
    // ══════════════════════════════════════════════════════════════════════════
    public static function player_state( WP_REST_Request $req ): WP_REST_Response {
        $token       = sanitize_text_field( $req->get_param( 'token' ) ?? '' );
        $client_hash = sanitize_text_field( $req->get_param( 'hash' )  ?? '' );

        if ( ! $token ) return self::err( 'Token mangler', 401 );

        $player = RZLQ_DB::get_player_by_token( $token );
        if ( ! $player ) return self::err( 'Ugyldigt token', 401 );

        $game = RZLQ_DB::get_game_by_id( (int) $player['game_id'] );
        if ( ! $game ) return self::err( 'Spil ikke fundet', 404 );

        // Throttled heartbeat — only write to DB every 5 s
        if ( ! wp_cache_get( 'rzlq_touch_' . $player['id'] ) ) {
            RZLQ_DB::touch_player( (int) $player['id'] );
            wp_cache_set( 'rzlq_touch_' . $player['id'], 1, '', 5 );
        }

        // Fast path — nothing changed
        if ( $client_hash && $client_hash === $game['state_hash'] ) {
            return self::ok( [ 'changed' => false, 'hash' => $game['state_hash'] ] );
        }

        return self::ok( self::build_player_state( $game, $player ) );
    }

    private static function build_player_state( array $game, array $player ): array {
        $status    = $game['status'];
        $q_index   = (int) $game['current_question'];
        $quiz_data = RZLQ_Quiz::get_questions( (int) $game['quiz_id'] );
        $q_total   = count( $quiz_data );

        $base = [
            'changed'  => true,
            'hash'     => $game['state_hash'],
            'status'   => $status,
            'nickname' => $player['nickname'],
            'score'    => (int) $player['score'],
            'streak'   => (int) $player['streak'],
        ];

        switch ( $status ) {
            case 'waiting':
                $base['player_count'] = RZLQ_DB::get_player_count( (int) $game['id'] );
                break;

            case 'question_active':
                $q = $quiz_data[ $q_index ] ?? null;
                if ( ! $q ) break;

                $time_limit        = (int) ( $q['time_limit'] ?? 20 );
                $elapsed_ms        = self::elapsed_ms( $game['question_started'] );
                $time_remaining_ms = max( 0, $time_limit * 1000 - $elapsed_ms );

                // Auto-advance on timer expiry
                if ( $time_remaining_ms === 0 ) {
                    RZLQ_DB::update_game_state( (int) $game['id'], [ 'status' => 'question_results' ] );
                    $game['status'] = 'question_results';
                    return self::build_player_state( $game, $player );
                }

                $my_answer = RZLQ_DB::get_answer_for_player( (int) $game['id'], (int) $player['id'], $q_index );

                $base['question_index']    = $q_index;
                $base['question_total']    = $q_total;
                $base['question']          = [
                    'type'   => $q['type'],
                    'text'   => $q['text'],
                    'image'  => $q['image_id'] ? wp_get_attachment_url( $q['image_id'] ) : null,
                    'options'=> self::strip_correct( $q ),
                    'min'    => $q['min']  ?? null,
                    'max'    => $q['max']  ?? null,
                ];
                $base['time_limit_ms']     = $time_limit * 1000;
                $base['time_remaining_ms'] = $time_remaining_ms;
                $base['has_answered']      = $my_answer !== null;
                break;

            case 'question_results':
                $q         = $quiz_data[ $q_index ] ?? null;
                $my_answer = $q ? RZLQ_DB::get_answer_for_player( (int) $game['id'], (int) $player['id'], $q_index ) : null;

                $base['question_index'] = $q_index;
                $base['question_total'] = $q_total;
                $base['player_result']  = [
                    'correct'      => $my_answer ? (bool) $my_answer['is_correct'] : false,
                    'points_gained'=> $my_answer ? (int)  $my_answer['points']     : 0,
                    'total_score'  => (int) $player['score'],
                    'streak'       => (int) $player['streak'],
                ];
                break;

            case 'leaderboard':
            case 'podium':
                $lb   = RZLQ_DB::get_leaderboard( (int) $game['id'], 10 );
                $my_rank = 0;
                foreach ( $lb as $i => $entry ) {
                    $entry['is_me'] = ( (int) $entry['id'] === (int) $player['id'] );
                    $lb[ $i ]       = $entry;
                    if ( $entry['is_me'] ) $my_rank = $i + 1;
                }
                $base['leaderboard'] = $lb;
                $base['my_rank']     = $my_rank ?: RZLQ_DB::get_player_count( (int) $game['id'] );
                break;

            case 'finished':
                $base['is_finished'] = true;
                break;
        }

        return $base;
    }

    // Strip correct-answer info so players can't cheat
    private static function strip_correct( array $q ): array {
        if ( empty( $q['options'] ) ) return [];
        return array_map( fn( $o ) => [ 'id' => $o['id'], 'text' => $o['text'] ], $q['options'] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /answer
    //
    // Body: { token, option_index: N }   for MC / TF / YN / poll
    //       { token, slider_value: N }   for slider
    // ══════════════════════════════════════════════════════════════════════════
    public static function answer( WP_REST_Request $req ): WP_REST_Response {
        $token = sanitize_text_field( $req->get_param( 'token' ) ?? '' );
        if ( ! $token ) return self::err( 'Token mangler', 401 );

        $player = RZLQ_DB::get_player_by_token( $token );
        if ( ! $player ) return self::err( 'Ugyldigt token', 401 );

        $game = RZLQ_DB::get_game_by_id( (int) $player['game_id'] );
        if ( ! $game || $game['status'] !== 'question_active' ) {
            return self::err( 'Spørgsmålet er ikke aktivt' );
        }

        $q_index = (int) $game['current_question'];
        $quiz_qs = RZLQ_Quiz::get_questions( (int) $game['quiz_id'] );
        $q       = $quiz_qs[ $q_index ] ?? null;
        if ( ! $q ) return self::err( 'Spørgsmål ikke fundet' );

        // Prevent double-answering
        if ( RZLQ_DB::get_answer_for_player( (int) $game['id'], (int) $player['id'], $q_index ) ) {
            return self::ok( [ 'already_answered' => true ] );
        }

        // Build answer_data JSON from posted params
        $q_type      = $q['type'] ?? 'multiple_choice';
        $answer_data = self::build_answer_data( $req, $q_type );
        if ( $answer_data === null ) return self::err( 'Ugyldigt svar' );

        // Timing
        $elapsed_ms    = self::elapsed_ms( $game['question_started'] );
        $time_limit_ms = ( (int) ( $q['time_limit'] ?? 20 ) ) * 1000;
        if ( $elapsed_ms > $time_limit_ms ) return self::err( 'Tiden er udløbet' );

        // Score
        $is_correct = self::check_correct( $q, $answer_data );
        $max_points = (int) ( $q['points'] ?? 1000 );
        $points     = $is_correct ? RZLQ_DB::calc_points( $max_points, $elapsed_ms, $time_limit_ms ) : 0;

        RZLQ_DB::save_answer(
            (int) $game['id'],
            (int) $player['id'],
            $q_index,
            $answer_data,
            $points,
            $is_correct,
            $elapsed_ms
        );
        RZLQ_DB::add_points_to_player( (int) $player['id'], $points, $is_correct );
        RZLQ_DB::refresh_hash( (int) $game['id'] );

        // Auto-advance if everyone answered
        $answer_count = RZLQ_DB::get_answer_count( (int) $game['id'], $q_index );
        $player_count = RZLQ_DB::get_player_count( (int) $game['id'] );
        if ( $answer_count >= $player_count ) {
            RZLQ_DB::update_game_state( (int) $game['id'], [ 'status' => 'question_results' ] );
        }

        // Reload player for updated streak
        $player = RZLQ_DB::get_player_by_token( $token );

        return self::ok( [
            'points'     => $points,
            'is_correct' => $is_correct,
            'streak'     => (int) ( $player['streak'] ?? 0 ),
        ] );
    }

    /**
     * Build the JSON string stored in the answers table,
     * matching the format check_correct() expects.
     */
    private static function build_answer_data( WP_REST_Request $req, string $q_type ): ?string {
        if ( $q_type === 'slider' ) {
            $val = $req->get_param( 'slider_value' );
            if ( $val === null ) return null;
            return wp_json_encode( [ 'value' => (float) $val ] );
        }

        $idx = $req->get_param( 'option_index' );
        if ( $idx === null ) return null;
        $idx = (int) $idx;

        if ( $q_type === 'multiple_choice' || $q_type === 'poll' ) {
            return wp_json_encode( [ 'selected' => [ $idx ] ] );
        }

        // true_false, yes_no — store as string to match option id
        return wp_json_encode( [ 'selected' => (string) $idx ] );
    }

    /**
     * Determine if answer_data is correct for the question.
     */
    private static function check_correct( array $q, string $answer_data ): bool {
        $type = $q['type'] ?? 'multiple_choice';
        $data = json_decode( $answer_data, true );

        switch ( $type ) {
            case 'multiple_choice':
                $selected = (array) ( $data['selected'] ?? [] );
                $correct  = array_keys( array_filter(
                    $q['options'] ?? [],
                    fn( $o ) => ! empty( $o['correct'] )
                ) );
                sort( $selected );
                sort( $correct );
                return $selected === $correct;

            case 'true_false':
            case 'yes_no':
                $correct_opts = array_filter( $q['options'] ?? [], fn( $o ) => ! empty( $o['correct'] ) );
                $correct_val  = (string) ( array_values( $correct_opts )[0]['id'] ?? '' );
                return isset( $data['selected'] ) && (string) $data['selected'] === $correct_val;

            case 'slider':
                $val       = (float) ( $data['value']     ?? 0 );
                $correct   = (float) ( $q['correct']      ?? 0 );
                $tolerance = (float) ( $q['tolerance']    ?? 0 );
                return abs( $val - $correct ) <= $tolerance;

            case 'poll':
                return true; // participation always scores
        }

        return false;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HOST: GET /host/state?game_id=&hash=
    // ══════════════════════════════════════════════════════════════════════════
    public static function host_state( WP_REST_Request $req ): WP_REST_Response {
        $game_id     = (int) ( $req->get_param( 'game_id' ) ?? 0 );
        $client_hash = sanitize_text_field( $req->get_param( 'hash' ) ?? '' );

        $game = RZLQ_DB::get_game_by_id( $game_id );
        if ( ! $game ) return self::err( 'Spil ikke fundet', 404 );

        // Dept isolation check
        if ( ! self::can_host_game( $game_id ) ) return self::err( 'Adgang nægtet', 403 );

        if ( $client_hash && $client_hash === $game['state_hash'] ) {
            return self::ok( [ 'changed' => false, 'hash' => $game['state_hash'] ] );
        }

        return self::ok( self::build_host_state( $game ) );
    }

    private static function build_host_state( array $game ): array {
        $status  = $game['status'];
        $q_index = (int) $game['current_question'];
        $quiz_qs = RZLQ_Quiz::get_questions( (int) $game['quiz_id'] );
        $q_total = count( $quiz_qs );

        $base = [
            'changed'        => true,
            'hash'           => $game['state_hash'],
            'status'         => $status,
            'pin'            => $game['pin'],
            'question_index' => $q_index,
            'question_total' => $q_total,
            'player_count'   => RZLQ_DB::get_player_count( (int) $game['id'] ),
        ];

        switch ( $status ) {
            case 'waiting':
                $base['players'] = RZLQ_DB::get_players( (int) $game['id'] );
                break;

            case 'question_active':
                $q = $quiz_qs[ $q_index ] ?? null;
                if ( ! $q ) break;

                $time_limit        = (int) ( $q['time_limit'] ?? 20 );
                $elapsed_ms        = self::elapsed_ms( $game['question_started'] );
                $time_remaining_ms = max( 0, $time_limit * 1000 - $elapsed_ms );

                // Auto-advance on expiry
                if ( $time_remaining_ms === 0 ) {
                    RZLQ_DB::update_game_state( (int) $game['id'], [ 'status' => 'question_results' ] );
                    $game['status'] = 'question_results';
                    return self::build_host_state( $game );
                }

                // Resolve image URL server-side so host.js gets a ready URL
                $q['image_url']            = $q['image_id'] ? wp_get_attachment_url( $q['image_id'] ) : null;
                $base['question']          = $q; // host sees correct answers + image_url
                $base['time_remaining_ms'] = $time_remaining_ms;
                $base['time_limit_ms']     = $time_limit * 1000;
                $base['answer_count']      = RZLQ_DB::get_answer_count( (int) $game['id'], $q_index );
                break;

            case 'question_results':
                $q = $quiz_qs[ $q_index ] ?? null;
                if ( $q ) {
                    $q['image_url']   = $q['image_id'] ? wp_get_attachment_url( $q['image_id'] ) : null;
                    $base['question'] = $q;
                    $base['distribution'] = RZLQ_DB::get_answer_distribution( (int) $game['id'], $q_index );
                    $base['answer_count'] = RZLQ_DB::get_answer_count( (int) $game['id'], $q_index );
                    $base['has_next']     = isset( $quiz_qs[ $q_index + 1 ] );
                }
                break;

            case 'leaderboard':
            case 'podium':
                $base['leaderboard'] = RZLQ_DB::get_leaderboard( (int) $game['id'], 10 );
                $base['has_next']    = $status === 'leaderboard' && isset( $quiz_qs[ $q_index + 1 ] );
                break;
        }

        return $base;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HOST: POST /host/advance
    // ══════════════════════════════════════════════════════════════════════════
    public static function host_advance( WP_REST_Request $req ): WP_REST_Response {
        $game_id = (int) ( $req->get_param( 'game_id' ) ?? 0 );
        $game    = RZLQ_DB::get_game_by_id( $game_id );
        if ( ! $game ) return self::err( 'Spil ikke fundet', 404 );
        if ( ! self::can_host_game( $game_id ) ) return self::err( 'Adgang nægtet', 403 );

        $quiz_qs = RZLQ_Quiz::get_questions( (int) $game['quiz_id'] );
        $q_index = (int) $game['current_question'];
        $updates = [];

        switch ( $game['status'] ) {
            case 'waiting':
                $updates = [
                    'status'           => 'question_active',
                    'current_question' => 0,
                    'question_started' => current_time( 'mysql' ),
                    'started_at'       => current_time( 'mysql' ),
                ];
                break;

            case 'question_active':
                $updates = [ 'status' => 'question_results' ];
                break;

            case 'question_results':
                $updates = [ 'status' => 'leaderboard' ];
                break;

            case 'leaderboard':
                if ( isset( $quiz_qs[ $q_index + 1 ] ) ) {
                    $updates = [
                        'status'           => 'question_active',
                        'current_question' => $q_index + 1,
                        'question_started' => current_time( 'mysql' ),
                    ];
                } else {
                    $updates = [ 'status' => 'podium' ];
                }
                break;

            case 'podium':
                $updates = [ 'status' => 'finished', 'finished_at' => current_time( 'mysql' ) ];
                break;

            default:
                return self::err( 'Ugyldigt spiltilstand' );
        }

        RZLQ_DB::update_game_state( $game_id, $updates );

        // Archive game when it finishes
        if ( ( $updates['status'] ?? '' ) === 'finished' ) {
            RZLQ_Dept::archive_game( $game_id );
        }

        return self::ok( [ 'new_status' => $updates['status'] ] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HOST: POST /host/kick
    // ══════════════════════════════════════════════════════════════════════════
    public static function host_kick( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $game_id   = (int) ( $req->get_param( 'game_id' )   ?? 0 );
        $player_id = (int) ( $req->get_param( 'player_id' ) ?? 0 );

        if ( ! self::can_host_game( $game_id ) ) return self::err( 'Adgang nægtet', 403 );

        $wpdb->delete( $wpdb->prefix . 'rezponz_players', [
            'id'      => $player_id,
            'game_id' => $game_id,
        ] );
        RZLQ_DB::refresh_hash( $game_id );

        return self::ok( [] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function can_host_game( int $game_id ): bool {
        if ( current_user_can( 'manage_options' ) ) return true;
        return RZLQ_Dept::can_access_game( $game_id );
    }

    private static function elapsed_ms( ?string $question_started ): int {
        if ( ! $question_started ) return 0;
        return (int) ( ( microtime( true ) - strtotime( $question_started ) ) * 1000 );
    }

    private static function ok( array $data, int $status = 200 ): WP_REST_Response {
        return new WP_REST_Response( array_merge( [ 'success' => true ], $data ), $status );
    }

    private static function err( string $msg, int $status = 400 ): WP_REST_Response {
        return new WP_REST_Response( [ 'success' => false, 'message' => $msg ], $status );
    }
}
