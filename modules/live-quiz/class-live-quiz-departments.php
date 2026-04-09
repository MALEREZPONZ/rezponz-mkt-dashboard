<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz Live Quiz — Department system.
 *
 * Manages departments, department WP users, and game-history archiving.
 *
 * Role hierarchy:
 *   - WP admin (manage_options) → sees ALL departments, quizzes, history
 *   - rzlq_dept user            → sees ONLY their own dept's quizzes + history
 */
class RZLQ_Dept {

    const ROLE            = 'rzlq_dept';
    const CAP             = 'rzlq_manage';
    const DB_VERSION_KEY  = 'rzlq_dept_db_ver';
    const DB_VERSION      = '1';

    // ── Install ───────────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Departments
        $depts   = $wpdb->prefix . 'rzlq_departments';
        $history = $wpdb->prefix . 'rzlq_game_history';

        dbDelta( "CREATE TABLE {$depts} (
            id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(100) NOT NULL,
            slug       VARCHAR(50)  NOT NULL,
            color      CHAR(7)      NOT NULL DEFAULT '#738991',
            created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $c;" );

        // Game history archive — snapshot of every finished game
        dbDelta( "CREATE TABLE {$history} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            game_id         BIGINT UNSIGNED NOT NULL,
            quiz_id         BIGINT UNSIGNED NOT NULL,
            dept_id         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            quiz_title      VARCHAR(200) NOT NULL DEFAULT '',
            dept_name       VARCHAR(100) NOT NULL DEFAULT '',
            player_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            winner_nickname VARCHAR(32)  NOT NULL DEFAULT '',
            winner_score    INT UNSIGNED NOT NULL DEFAULT 0,
            leaderboard     LONGTEXT     DEFAULT NULL,
            started_at      DATETIME     DEFAULT NULL,
            finished_at     DATETIME     DEFAULT NULL,
            created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY game_id (game_id),
            KEY dept_id (dept_id),
            KEY finished_at (finished_at)
        ) $c;" );

        // Create dept role
        if ( ! get_role( self::ROLE ) ) {
            add_role( self::ROLE, 'Rezponz Afdeling', [
                'read'         => true,
                self::CAP      => true,
            ] );
        }

        // Ensure administrators also have the custom cap
        $admin_role = get_role( 'administrator' );
        if ( $admin_role && ! $admin_role->has_cap( self::CAP ) ) {
            $admin_role->add_cap( self::CAP );
        }

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    // ── Department CRUD ───────────────────────────────────────────────────────

    public static function create_department( string $name, string $color = '#738991' ): int {
        global $wpdb;
        $slug = self::unique_slug( sanitize_title( $name ) );
        $wpdb->insert( $wpdb->prefix . 'rzlq_departments', [
            'name'  => sanitize_text_field( $name ),
            'slug'  => $slug,
            'color' => sanitize_hex_color( $color ) ?: '#738991',
        ] );
        return (int) $wpdb->insert_id;
    }

    public static function update_department( int $id, string $name, string $color ): void {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'rzlq_departments', [
            'name'  => sanitize_text_field( $name ),
            'color' => sanitize_hex_color( $color ) ?: '#738991',
        ], [ 'id' => $id ] );
    }

    public static function delete_department( int $id ): void {
        global $wpdb;
        // Remove dept users first
        $users = self::get_dept_users( $id );
        foreach ( $users as $u ) {
            wp_delete_user( (int) $u->ID );
        }
        $wpdb->delete( $wpdb->prefix . 'rzlq_departments', [ 'id' => $id ] );
    }

    public static function get_departments(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rzlq_departments ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    public static function get_department( int $id ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzlq_departments WHERE id = %d",
            $id
        ), ARRAY_A ) ?: null;
    }

    // ── Department users ──────────────────────────────────────────────────────

    /**
     * Create a locked WP user for a department.
     * "Locked" = no profile editing, no password reset by self.
     */
    public static function create_dept_user(
        int    $dept_id,
        string $username,
        string $password,
        string $display_name = ''
    ): int|WP_Error {
        if ( username_exists( $username ) ) {
            return new WP_Error( 'user_exists', 'Brugernavnet er allerede i brug' );
        }

        $user_id = wp_insert_user( [
            'user_login'   => sanitize_user( $username ),
            'user_pass'    => $password,
            'display_name' => $display_name ?: $username,
            'role'         => self::ROLE,
        ] );

        if ( is_wp_error( $user_id ) ) return $user_id;

        update_user_meta( $user_id, '_rzlq_dept_id',    $dept_id );
        update_user_meta( $user_id, '_rzlq_locked',     '1' );   // flag: locked account
        update_user_meta( $user_id, 'show_welcome_panel', 0 );

        return $user_id;
    }

    public static function reset_dept_user_password( int $user_id, string $new_password ): void {
        wp_set_password( $new_password, $user_id );
    }

    public static function delete_dept_user( int $user_id ): void {
        wp_delete_user( $user_id );
    }

    public static function get_dept_users( int $dept_id ): array {
        return get_users( [
            'meta_key'   => '_rzlq_dept_id',
            'meta_value' => $dept_id,
            'role'       => self::ROLE,
        ] ) ?: [];
    }

    // ── Access helpers ────────────────────────────────────────────────────────

    /**
     * Returns true if the current user can access Live Quiz admin pages.
     */
    public static function has_access(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( self::CAP );
    }

    /**
     * Die if no access.
     */
    public static function require_access(): void {
        if ( ! self::has_access() ) wp_die( 'Adgang nægtet' );
    }

    /**
     * Returns 0 for super admin (sees all), or the dept_id for a dept user.
     */
    public static function current_dept_id(): int {
        if ( current_user_can( 'manage_options' ) ) return 0;
        return (int) get_user_meta( get_current_user_id(), '_rzlq_dept_id', true );
    }

    /**
     * Check if the current user can access a specific quiz.
     */
    public static function can_access_quiz( int $quiz_id ): bool {
        $dept_id = self::current_dept_id();
        if ( $dept_id === 0 ) return true; // admin sees all
        $quiz_dept = (int) get_post_meta( $quiz_id, '_rzlq_dept_id', true );
        return $quiz_dept === $dept_id;
    }

    /**
     * Check if the current user can access a specific game.
     */
    public static function can_access_game( int $game_id ): bool {
        global $wpdb;
        $dept_id = self::current_dept_id();
        if ( $dept_id === 0 ) return true;

        $game_dept = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT dept_id FROM {$wpdb->prefix}rezponz_games WHERE id = %d",
            $game_id
        ) );
        return $game_dept === $dept_id;
    }

    // ── Game history ──────────────────────────────────────────────────────────

    /**
     * Archive a finished game into the history table.
     * Called automatically when a game transitions to 'finished'.
     */
    public static function archive_game( int $game_id ): void {
        global $wpdb;

        $game = RZLQ_DB::get_game_by_id( $game_id );
        if ( ! $game ) return;

        // Skip if already archived
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rzlq_game_history WHERE game_id = %d",
            $game_id
        ) );
        if ( $exists ) return;

        $lb    = RZLQ_DB::get_leaderboard( $game_id, 100 );
        $quiz  = get_post( (int) $game['quiz_id'] );
        $dept  = self::get_department( (int) $game['dept_id'] );

        $winner_nickname = '';
        $winner_score    = 0;
        if ( ! empty( $lb ) ) {
            $winner_nickname = $lb[0]['nickname'];
            $winner_score    = (int) $lb[0]['score'];
        }

        $wpdb->insert( $wpdb->prefix . 'rzlq_game_history', [
            'game_id'         => $game_id,
            'quiz_id'         => (int) $game['quiz_id'],
            'dept_id'         => (int) $game['dept_id'],
            'quiz_title'      => $quiz ? $quiz->post_title : '',
            'dept_name'       => $dept ? $dept['name'] : '',
            'player_count'    => RZLQ_DB::get_player_count( $game_id ),
            'winner_nickname' => $winner_nickname,
            'winner_score'    => $winner_score,
            'leaderboard'     => wp_json_encode( $lb ),
            'started_at'      => $game['started_at'],
            'finished_at'     => $game['finished_at'] ?: current_time( 'mysql' ),
        ] );
    }

    /**
     * Get game history, optionally filtered by dept_id.
     * dept_id = 0 → all (admin only).
     */
    public static function get_game_history( int $dept_id = 0, int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rzlq_game_history';

        if ( $dept_id > 0 ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE dept_id = %d ORDER BY finished_at DESC LIMIT %d OFFSET %d",
                $dept_id, $limit, $offset
            ), ARRAY_A ) ?: [];
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY finished_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A ) ?: [];
    }

    public static function get_history_count( int $dept_id = 0 ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rzlq_game_history';
        if ( $dept_id > 0 ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE dept_id = %d", $dept_id
            ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    // ── Slug helper ───────────────────────────────────────────────────────────

    private static function unique_slug( string $base ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'rzlq_departments';
        $slug  = $base;
        $n     = 1;
        while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) ) ) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    // ── Locked account: restrict admin access for dept users ─────────────────

    public static function init(): void {
        add_action( 'admin_init',          [ __CLASS__, 'restrict_admin_for_dept_users' ] );
        add_action( 'admin_bar_menu',      [ __CLASS__, 'clean_admin_bar' ], 999 );
        add_action( 'show_user_profile',   [ __CLASS__, 'hide_password_fields' ] );
        add_action( 'personal_options',    [ __CLASS__, 'lock_profile_notice' ] );
        add_filter( 'admin_footer_text',   [ __CLASS__, 'dept_footer' ] );
        add_action( 'load-profile.php',    [ __CLASS__, 'prevent_password_change' ] );
    }

    /**
     * Redirect dept users away from all WP admin pages except rzlq ones.
     */
    public static function restrict_admin_for_dept_users(): void {
        if ( current_user_can( 'manage_options' ) ) return;
        if ( ! current_user_can( self::CAP ) ) return;

        $page    = $_GET['page'] ?? '';
        $allowed = [ 'rzlq-quizzes', 'rzlq-edit-quiz', 'rzlq-host', 'rzlq-history' ];

        // Redirect index.php (dashboard) and any non-rzlq page
        if ( ! in_array( $page, $allowed, true ) && basename( $_SERVER['PHP_SELF'] ) !== 'admin-post.php' ) {
            wp_safe_redirect( admin_url( 'admin.php?page=rzlq-quizzes' ) );
            exit;
        }
    }

    public static function clean_admin_bar( WP_Admin_Bar $bar ): void {
        if ( current_user_can( 'manage_options' ) ) return;
        if ( ! current_user_can( self::CAP ) ) return;

        // Remove most admin bar items for dept users
        foreach ( [ 'wp-logo','updates','comments','new-content','edit','site-name' ] as $node ) {
            $bar->remove_node( $node );
        }
    }

    public static function hide_password_fields(): void {
        $user_id = get_current_user_id();
        if ( get_user_meta( $user_id, '_rzlq_locked', true ) ) {
            echo '<style>#password, .user-pass1-wrap, .user-pass2-wrap, .pw-weak-text, #pass-strength-result, .button.wp-generate-pw { display:none !important; }</style>';
        }
    }

    public static function lock_profile_notice(): void {
        $user_id = get_current_user_id();
        if ( get_user_meta( $user_id, '_rzlq_locked', true ) ) {
            echo '<tr><th></th><td><div class="notice notice-info inline"><p>🔒 Din konto administreres af Rezponz. Kontakt en administrator for at ændre adgangskoden.</p></div></td></tr>';
        }
    }

    public static function prevent_password_change(): void {
        $user_id = get_current_user_id();
        if ( ! get_user_meta( $user_id, '_rzlq_locked', true ) ) return;
        if ( isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
            wp_die( '🔒 Adgangskodeændring er ikke tilladt for denne konto.' );
        }
    }

    public static function dept_footer(): string {
        if ( ! current_user_can( self::CAP ) || current_user_can( 'manage_options' ) ) return '';
        $dept_id   = self::current_dept_id();
        $dept      = $dept_id ? self::get_department( $dept_id ) : null;
        $dept_name = $dept ? esc_html( $dept['name'] ) : 'Ukendt afdeling';
        return "Logget ind som: <strong>{$dept_name}</strong> · Rezponz Live Quiz";
    }
}
