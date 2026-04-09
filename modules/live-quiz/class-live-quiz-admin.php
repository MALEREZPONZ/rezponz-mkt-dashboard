<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZLQ_Admin {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_rzlq_save_quiz',    [ __CLASS__, 'handle_save_quiz' ] );
        add_action( 'admin_post_rzlq_delete_quiz',  [ __CLASS__, 'handle_delete_quiz' ] );
        add_action( 'admin_post_rzlq_start_game',   [ __CLASS__, 'handle_start_game' ] );
        add_action( 'admin_post_rzlq_save_dept',    [ __CLASS__, 'handle_save_dept' ] );
        add_action( 'admin_post_rzlq_delete_dept',  [ __CLASS__, 'handle_delete_dept' ] );
        add_action( 'admin_post_rzlq_save_user',    [ __CLASS__, 'handle_save_user' ] );
        add_action( 'admin_post_rzlq_delete_user',  [ __CLASS__, 'handle_delete_user' ] );
        add_action( 'admin_post_rzlq_reset_pw',     [ __CLASS__, 'handle_reset_pw' ] );

        // Auto-finish stale games daily
        add_action( 'rzlq_cleanup', [ 'RZLQ_DB', 'finish_old_games' ] );
        if ( ! wp_next_scheduled( 'rzlq_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'rzlq_cleanup' );
        }
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public static function add_menu(): void {
        $cap = RZLQ_Dept::CAP;

        if ( current_user_can( 'manage_options' ) ) {
            // Admins: hidden pages (null parent) — accessible by URL but not shown in sidebar
            add_submenu_page( null, 'Live Quiz',    '🎮 Live Quiz',    'manage_options', 'rzlq-quizzes',     [ __CLASS__, 'page_quiz_list' ] );
            add_submenu_page( null, 'Quiz Historik','📋 Quiz Historik','manage_options', 'rzlq-history',     [ __CLASS__, 'page_history' ] );
            add_submenu_page( null, 'Afdelinger',   '🏢 Afdelinger',   'manage_options', 'rzlq-departments', [ __CLASS__, 'page_departments' ] );
        } else {
            // Dept users: standalone top-level menu (they can't see rzpa-dashboard)
            add_menu_page( 'Live Quiz', '🎮 Live Quiz', $cap, 'rzlq-quizzes', [ __CLASS__, 'page_quiz_list' ], 'dashicons-awards', 25 );
            add_submenu_page( 'rzlq-quizzes', 'Live Quiz',    'Quizzer',        $cap, 'rzlq-quizzes',  [ __CLASS__, 'page_quiz_list' ] );
            add_submenu_page( 'rzlq-quizzes', 'Quiz Historik','📋 Historik',    $cap, 'rzlq-history',  [ __CLASS__, 'page_history' ] );
        }

        // Hidden pages (no parent — accessible by URL for both roles)
        add_submenu_page( null, 'Rediger Quiz', '', $cap, 'rzlq-edit-quiz',   [ __CLASS__, 'page_edit_quiz' ] );
        add_submenu_page( null, 'Host Screen',  '', $cap, 'rzlq-host',        [ __CLASS__, 'page_host' ] );
    }

    // ── Quiz list ─────────────────────────────────────────────────────────────

    public static function page_quiz_list(): void {
        RZLQ_Dept::require_access();
        $dept_id = RZLQ_Dept::current_dept_id();
        $quizzes = self::get_all_quizzes( $dept_id );
        $active  = RZLQ_DB::get_active_games( $dept_id );
        require __DIR__ . '/views/admin-quiz-list.php';
    }

    // ── Edit quiz ─────────────────────────────────────────────────────────────

    public static function page_edit_quiz(): void {
        RZLQ_Dept::require_access();

        $quiz_id = (int) ( $_GET['quiz_id'] ?? 0 );

        if ( ! $quiz_id ) {
            // Create new quiz post
            $dept_id = RZLQ_Dept::current_dept_id();
            $quiz_id = wp_insert_post( [
                'post_type'   => RZLQ_Quiz::CPT,
                'post_status' => 'publish',
                'post_title'  => 'Ny Quiz',
            ] );
            if ( $dept_id > 0 ) {
                update_post_meta( $quiz_id, '_rzlq_dept_id', $dept_id );
            }
            wp_redirect( admin_url( 'admin.php?page=rzlq-edit-quiz&quiz_id=' . $quiz_id ) );
            exit;
        }

        if ( ! RZLQ_Dept::can_access_quiz( $quiz_id ) ) wp_die( 'Adgang nægtet' );

        $post = get_post( $quiz_id );
        if ( ! $post || $post->post_type !== RZLQ_Quiz::CPT ) wp_die( 'Quiz ikke fundet' );

        RZLQ_Quiz::enqueue_admin_edit_assets( $quiz_id );
        require __DIR__ . '/views/admin-quiz-edit.php';
    }

    // ── Host screen ───────────────────────────────────────────────────────────

    public static function page_host(): void {
        RZLQ_Dept::require_access();

        $game_id = (int) ( $_GET['game_id'] ?? 0 );
        if ( ! $game_id ) wp_die( 'game_id mangler' );

        if ( ! RZLQ_Dept::can_access_game( $game_id ) ) wp_die( 'Adgang nægtet' );

        $game = RZLQ_DB::get_game_by_id( $game_id );
        if ( ! $game ) wp_die( 'Spil ikke fundet' );

        RZLQ_Quiz::enqueue_host_assets();

        $api_url = esc_attr( rest_url( 'rezponz/v1' ) );
        $nonce   = esc_attr( wp_create_nonce( 'wp_rest' ) );
        $quiz    = get_post( $game['quiz_id'] );
        ?><!DOCTYPE html>
<html lang="da">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Rezponz Live · Host</title>
  <?php wp_head(); ?>
  <style>html,body{margin:0;padding:0;height:100%;overflow:hidden}</style>
</head>
<body style="background:#111">
  <div id="rzlq-host-root"
       data-game-id="<?php echo $game_id; ?>"
       data-api="<?php echo $api_url; ?>"
       data-nonce="<?php echo $nonce; ?>"
       data-quiz-title="<?php echo esc_attr( $quiz ? $quiz->post_title : '' ); ?>"
       data-player-url="<?php echo esc_attr( self::player_url() ); ?>">
  </div>
  <?php wp_footer(); ?>
</body>
</html><?php
        exit;
    }

    // ── Game history ──────────────────────────────────────────────────────────

    public static function page_history(): void {
        RZLQ_Dept::require_access();
        $dept_id  = RZLQ_Dept::current_dept_id();
        $per_page = 20;
        $paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;
        $history  = RZLQ_Dept::get_game_history( $dept_id, $per_page, $offset );
        $total    = RZLQ_Dept::get_history_count( $dept_id );
        $pages    = (int) ceil( $total / $per_page );
        require __DIR__ . '/views/admin-game-history.php';
    }

    // ── Department management (admin only) ────────────────────────────────────

    public static function page_departments(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        $departments = RZLQ_Dept::get_departments();
        $saved       = ! empty( $_GET['saved'] );
        $deleted     = ! empty( $_GET['deleted'] );
        $edit_id     = (int) ( $_GET['edit_dept'] ?? 0 );
        $edit_dept   = $edit_id ? RZLQ_Dept::get_department( $edit_id ) : null;
        $dept_users  = $edit_id ? RZLQ_Dept::get_dept_users( $edit_id ) : [];
        require __DIR__ . '/views/admin-departments.php';
    }

    // ── Form handlers ─────────────────────────────────────────────────────────

    public static function handle_save_quiz(): void {
        RZLQ_Dept::require_access();

        $quiz_id = (int) ( $_POST['quiz_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['rzlq_nonce'] ?? '', 'rzlq_save_quiz_' . $quiz_id ) ) wp_die( 'Ugyldig nonce' );
        if ( ! RZLQ_Dept::can_access_quiz( $quiz_id ) ) wp_die( 'Adgang nægtet' );

        $title = sanitize_text_field( $_POST['quiz_title'] ?? '' );
        if ( $title ) wp_update_post( [ 'ID' => $quiz_id, 'post_title' => $title ] );

        $raw_json  = wp_unslash( $_POST['rzlq_questions'] ?? '[]' );
        $questions = json_decode( $raw_json, true );
        if ( is_array( $questions ) ) {
            $clean = array_map( [ 'RZLQ_Quiz', 'sanitize_question' ], $questions );
            RZLQ_Quiz::save_questions( $quiz_id, array_values( $clean ) );
        }

        // Cover image
        $cover_id = (int) ( $_POST['rzlq_cover_id'] ?? 0 );
        update_post_meta( $quiz_id, '_rzlq_cover_id', $cover_id );

        // Ensure dept_id is stamped (in case it was missed at creation)
        $dept_id = RZLQ_Dept::current_dept_id();
        if ( $dept_id > 0 && ! get_post_meta( $quiz_id, '_rzlq_dept_id', true ) ) {
            update_post_meta( $quiz_id, '_rzlq_dept_id', $dept_id );
        }

        // "Save + Start" shortcut: auto-start game after saving
        if ( ! empty( $_POST['rzlq_after_save'] ) && $_POST['rzlq_after_save'] === 'start' ) {
            $game = RZLQ_DB::create_game( $quiz_id, $dept_id );
            wp_redirect( admin_url( 'admin.php?page=rzlq-host&game_id=' . $game['id'] ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=rzlq-edit-quiz&quiz_id=' . $quiz_id . '&saved=1' ) );
        exit;
    }

    public static function handle_delete_quiz(): void {
        RZLQ_Dept::require_access();
        $quiz_id = (int) ( $_POST['quiz_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['rzlq_del_nonce'] ?? '', 'rzlq_delete_' . $quiz_id ) ) wp_die( 'Ugyldig nonce' );
        if ( ! RZLQ_Dept::can_access_quiz( $quiz_id ) ) wp_die( 'Adgang nægtet' );

        wp_delete_post( $quiz_id, true );
        wp_redirect( admin_url( 'admin.php?page=rzlq-quizzes&deleted=1' ) );
        exit;
    }

    public static function handle_start_game(): void {
        RZLQ_Dept::require_access();
        $quiz_id = (int) ( $_POST['quiz_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['rzlq_start_nonce'] ?? '', 'rzlq_start_' . $quiz_id ) ) wp_die( 'Ugyldig nonce' );
        if ( ! RZLQ_Dept::can_access_quiz( $quiz_id ) ) wp_die( 'Adgang nægtet' );

        $dept_id = RZLQ_Dept::current_dept_id();
        $game    = RZLQ_DB::create_game( $quiz_id, $dept_id );

        wp_redirect( admin_url( 'admin.php?page=rzlq-host&game_id=' . $game['id'] ) );
        exit;
    }

    // ── Department form handlers (admin only) ─────────────────────────────────

    public static function handle_save_dept(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzlq_dept_nonce'] ?? '', 'rzlq_save_dept' ) ) wp_die( 'Ugyldig nonce' );

        $dept_id = (int) ( $_POST['dept_id'] ?? 0 );
        $name    = sanitize_text_field( $_POST['dept_name'] ?? '' );
        $color   = sanitize_hex_color( $_POST['dept_color'] ?? '' ) ?: '#738991';

        if ( ! $name ) {
            wp_redirect( admin_url( 'admin.php?page=rzlq-departments&error=name' ) );
            exit;
        }

        if ( $dept_id ) {
            RZLQ_Dept::update_department( $dept_id, $name, $color );
        } else {
            $dept_id = RZLQ_Dept::create_department( $name, $color );
        }

        wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&saved=1' ) );
        exit;
    }

    public static function handle_delete_dept(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzlq_dept_del_nonce'] ?? '', 'rzlq_delete_dept' ) ) wp_die( 'Ugyldig nonce' );

        $dept_id = (int) ( $_POST['dept_id'] ?? 0 );
        RZLQ_Dept::delete_department( $dept_id );

        wp_redirect( admin_url( 'admin.php?page=rzlq-departments&deleted=1' ) );
        exit;
    }

    public static function handle_save_user(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzlq_user_nonce'] ?? '', 'rzlq_save_user' ) ) wp_die( 'Ugyldig nonce' );

        $dept_id      = (int) ( $_POST['dept_id']      ?? 0 );
        $username     = sanitize_user( $_POST['username']     ?? '' );
        $password     = $_POST['password']    ?? '';
        $display_name = sanitize_text_field( $_POST['display_name'] ?? '' );

        if ( ! $dept_id || ! $username || strlen( $password ) < 8 ) {
            wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&user_error=1' ) );
            exit;
        }

        $result = RZLQ_Dept::create_dept_user( $dept_id, $username, $password, $display_name );

        if ( is_wp_error( $result ) ) {
            wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&user_error=' . urlencode( $result->get_error_message() ) ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&user_saved=1' ) );
        exit;
    }

    public static function handle_delete_user(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzlq_user_del_nonce'] ?? '', 'rzlq_delete_user' ) ) wp_die( 'Ugyldig nonce' );

        $user_id = (int) ( $_POST['user_id'] ?? 0 );
        $dept_id = (int) ( $_POST['dept_id'] ?? 0 );
        RZLQ_Dept::delete_dept_user( $user_id );

        wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&user_deleted=1' ) );
        exit;
    }

    public static function handle_reset_pw(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzlq_pw_nonce'] ?? '', 'rzlq_reset_pw' ) ) wp_die( 'Ugyldig nonce' );

        $user_id  = (int) ( $_POST['user_id']     ?? 0 );
        $dept_id  = (int) ( $_POST['dept_id']     ?? 0 );
        $password = $_POST['new_password'] ?? '';

        if ( strlen( $password ) < 8 ) {
            wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&pw_error=1' ) );
            exit;
        }

        RZLQ_Dept::reset_dept_user_password( $user_id, $password );
        wp_redirect( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $dept_id . '&pw_saved=1' ) );
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function player_url(): string {
        $pages = get_posts( [
            'post_type'   => 'page',
            'post_status' => 'publish',
            's'           => 'rezponz_player',
            'numberposts' => 1,
        ] );
        return $pages ? get_permalink( $pages[0] ) : home_url( '/spil' );
    }

    /**
     * Fetch quizzes. dept_id=0 → all (admin), >0 → filter by dept.
     */
    public static function get_all_quizzes( int $dept_id = 0 ): array {
        $args = [
            'post_type'      => RZLQ_Quiz::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $dept_id > 0 ) {
            $args['meta_query'] = [ [
                'key'   => '_rzlq_dept_id',
                'value' => $dept_id,
            ] ];
        }

        return get_posts( $args ) ?: [];
    }
}
