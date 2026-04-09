<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Database layer for Rezponz Live Quiz.
 */
class RZLQ_DB {

    const DB_VERSION_KEY = 'rzlq_db_ver';
    const DB_VERSION     = '2'; // bumped: added dept_id to games

    // ── Install / upgrade ─────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $games   = $wpdb->prefix . 'rezponz_games';
        $players = $wpdb->prefix . 'rezponz_players';
        $answers = $wpdb->prefix . 'rezponz_answers';

        dbDelta( "CREATE TABLE {$games} (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pin              CHAR(6)         NOT NULL,
            quiz_id          BIGINT UNSIGNED NOT NULL,
            dept_id          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status           ENUM('waiting','question_active','question_results','leaderboard','podium','finished')
                             NOT NULL DEFAULT 'waiting',
            current_question SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            question_started DATETIME DEFAULT NULL,
            state_hash       CHAR(8)  NOT NULL DEFAULT '',
            started_at       DATETIME DEFAULT NULL,
            finished_at      DATETIME DEFAULT NULL,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY pin (pin),
            KEY quiz_id (quiz_id),
            KEY dept_id (dept_id),
            KEY status (status)
        ) $c;" );

        dbDelta( "CREATE TABLE {$players} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            game_id     BIGINT UNSIGNED NOT NULL,
            nickname    VARCHAR(32)     NOT NULL,
            score       INT UNSIGNED    NOT NULL DEFAULT 0,
            streak      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            token       CHAR(32)        NOT NULL,
            last_active DATETIME DEFAULT NULL,
            joined_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY game_id (game_id),
            KEY game_score (game_id, score DESC)
        ) $c;" );

        dbDelta( "CREATE TABLE {$answers} (
            id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            game_id         BIGINT UNSIGNED  NOT NULL,
            player_id       BIGINT UNSIGNED  NOT NULL,
            question_index  SMALLINT UNSIGNED NOT NULL,
            answer_data     VARCHAR(512)     NOT NULL DEFAULT '',
            points          INT UNSIGNED     NOT NULL DEFAULT 0,
            is_correct      TINYINT(1)       DEFAULT 0,
            response_ms     MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
            answered_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY one_answer (game_id, player_id, question_index),
            KEY game_question (game_id, question_index)
        ) $c;" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    // ── PIN generation ────────────────────────────────────────────────────────

    public static function generate_pin(): string {
        global $wpdb;
        $games = $wpdb->prefix . 'rezponz_games';
        do {
            $pin = str_pad( (string) rand( 100000, 999999 ), 6, '0', STR_PAD_LEFT );
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$games} WHERE pin = %s AND status != 'finished'",
                $pin
            ) );
        } while ( $exists );
        return $pin;
    }

    // ── Game CRUD ─────────────────────────────────────────────────────────────

    public static function create_game( int $quiz_id, int $dept_id = 0 ): array {
        global $wpdb;
        $pin = self::generate_pin();
        $wpdb->insert( $wpdb->prefix . 'rezponz_games', [
            'pin'     => $pin,
            'quiz_id' => $quiz_id,
            'dept_id' => $dept_id,
            'status'  => 'waiting',
        ] );
        return [ 'id' => (int) $wpdb->insert_id, 'pin' => $pin ];
    }

    public static function get_game_by_pin( string $pin ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rezponz_games WHERE pin = %s",
            $pin
        ), ARRAY_A ) ?: null;
    }

    public static function get_game_by_id( int $id ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rezponz_games WHERE id = %d",
            $id
        ), ARRAY_A ) ?: null;
    }

    public static function update_game_state( int $game_id, array $data ): void {
        global $wpdb;
        $data['state_hash'] = substr( md5( serialize( $data ) . microtime() ), 0, 8 );
        $wpdb->update( $wpdb->prefix . 'rezponz_games', $data, [ 'id' => $game_id ] );
    }

    // ── Player CRUD ───────────────────────────────────────────────────────────

    public static function join_game( int $game_id, string $nickname ): array|false {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rezponz_players WHERE game_id = %d AND nickname = %s",
            $game_id, $nickname
        ) );
        if ( $exists ) return false;

        $token = wp_generate_password( 32, false );
        $wpdb->insert( $wpdb->prefix . 'rezponz_players', [
            'game_id'     => $game_id,
            'nickname'    => $nickname,
            'token'       => $token,
            'last_active' => current_time( 'mysql' ),
        ] );
        $player_id = (int) $wpdb->insert_id;
        self::refresh_hash( $game_id );

        return [ 'player_id' => $player_id, 'token' => $token ];
    }

    public static function get_player_by_token( string $token ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rezponz_players WHERE token = %s",
            $token
        ), ARRAY_A ) ?: null;
    }

    public static function get_players( int $game_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, nickname, score, streak FROM {$wpdb->prefix}rezponz_players
             WHERE game_id = %d ORDER BY score DESC, joined_at ASC",
            $game_id
        ), ARRAY_A ) ?: [];
    }

    public static function get_player_count( int $game_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rezponz_players WHERE game_id = %d",
            $game_id
        ) );
    }

    public static function touch_player( int $player_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rezponz_players',
            [ 'last_active' => current_time( 'mysql' ) ],
            [ 'id' => $player_id ]
        );
    }

    // ── Answer handling ───────────────────────────────────────────────────────

    public static function save_answer(
        int    $game_id,
        int    $player_id,
        int    $question_index,
        string $answer_data,
        int    $points,
        bool   $is_correct,
        int    $response_ms
    ): bool {
        global $wpdb;
        $ok = $wpdb->replace( $wpdb->prefix . 'rezponz_answers', [
            'game_id'        => $game_id,
            'player_id'      => $player_id,
            'question_index' => $question_index,
            'answer_data'    => $answer_data,
            'points'         => $points,
            'is_correct'     => $is_correct ? 1 : 0,
            'response_ms'    => $response_ms,
        ] );
        return (bool) $ok;
    }

    public static function add_points_to_player( int $player_id, int $points, bool $is_correct ): void {
        global $wpdb;
        if ( $is_correct ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}rezponz_players
                 SET score = score + %d, streak = streak + 1
                 WHERE id = %d",
                $points, $player_id
            ) );
        } else {
            $wpdb->update(
                $wpdb->prefix . 'rezponz_players',
                [ 'streak' => 0 ],
                [ 'id' => $player_id ]
            );
        }
    }

    public static function get_answer_for_player( int $game_id, int $player_id, int $question_index ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rezponz_answers
             WHERE game_id = %d AND player_id = %d AND question_index = %d",
            $game_id, $player_id, $question_index
        ), ARRAY_A ) ?: null;
    }

    public static function get_answer_count( int $game_id, int $question_index ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rezponz_answers
             WHERE game_id = %d AND question_index = %d",
            $game_id, $question_index
        ) );
    }

    public static function get_answer_distribution( int $game_id, int $question_index ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT answer_data, COUNT(*) as cnt
             FROM {$wpdb->prefix}rezponz_answers
             WHERE game_id = %d AND question_index = %d
             GROUP BY answer_data",
            $game_id, $question_index
        ), ARRAY_A ) ?: [];

        // Decode JSON answer_data → extract the selected index for bar chart
        $dist = [];
        foreach ( $rows as $row ) {
            $decoded = json_decode( $row['answer_data'], true );
            if ( isset( $decoded['selected'] ) ) {
                $key = is_array( $decoded['selected'] )
                    ? (string) $decoded['selected'][0]
                    : (string) $decoded['selected'];
            } elseif ( isset( $decoded['value'] ) ) {
                $key = (string) $decoded['value'];
            } else {
                $key = $row['answer_data'];
            }
            $dist[ $key ] = ( $dist[ $key ] ?? 0 ) + (int) $row['cnt'];
        }
        return $dist;
    }

    // ── Scoring ───────────────────────────────────────────────────────────────

    /**
     * Speed-based scoring: 50% base + 50% speed bonus.
     */
    public static function calc_points( int $max_points, int $response_ms, int $time_limit_ms ): int {
        if ( $max_points === 0 ) return 0;
        $base  = (int) round( $max_points * 0.5 );
        $ratio = max( 0.0, 1.0 - ( $response_ms / max( 1, $time_limit_ms ) ) );
        $bonus = (int) round( $max_points * 0.5 * $ratio );
        return $base + $bonus;
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────

    public static function get_leaderboard( int $game_id, int $limit = 10 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, nickname, score, streak
             FROM {$wpdb->prefix}rezponz_players
             WHERE game_id = %d
             ORDER BY score DESC, joined_at ASC
             LIMIT %d",
            $game_id, $limit
        ), ARRAY_A ) ?: [];
    }

    // ── Hash ──────────────────────────────────────────────────────────────────

    public static function refresh_hash( int $game_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rezponz_games',
            [ 'state_hash' => substr( md5( $game_id . microtime() ), 0, 8 ) ],
            [ 'id' => $game_id ]
        );
    }

    // ── Admin helpers ─────────────────────────────────────────────────────────

    /**
     * Returns active (non-finished) games.
     * dept_id = 0 → all (admin), >0 → filter by dept.
     */
    public static function get_active_games( int $dept_id = 0 ): array {
        global $wpdb;
        $where = $dept_id > 0
            ? $wpdb->prepare( "AND g.dept_id = %d", $dept_id )
            : '';

        return $wpdb->get_results(
            "SELECT g.*, p.post_title as quiz_title,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}rezponz_players pl WHERE pl.game_id = g.id) as player_count
             FROM {$wpdb->prefix}rezponz_games g
             LEFT JOIN {$wpdb->posts} p ON g.quiz_id = p.ID
             WHERE g.status != 'finished'
             {$where}
             ORDER BY g.created_at DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function finish_old_games(): void {
        global $wpdb;
        $old_games = $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}rezponz_games
             WHERE status != 'finished'
             AND created_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)"
        );
        if ( $old_games ) {
            $wpdb->query(
                "UPDATE {$wpdb->prefix}rezponz_games
                 SET status = 'finished', finished_at = NOW()
                 WHERE id IN (" . implode( ',', array_map( 'intval', $old_games ) ) . ")"
            );
            // Archive them all
            foreach ( $old_games as $gid ) {
                RZLQ_Dept::archive_game( (int) $gid );
            }
        }
    }
}
