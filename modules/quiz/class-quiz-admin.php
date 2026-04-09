<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Quiz_Admin {

    public static function init(): void {
        add_action( 'admin_menu',                           [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_rzpa_quiz_save_question',   [ __CLASS__, 'handle_save_question' ] );
        add_action( 'admin_post_rzpa_quiz_delete_question', [ __CLASS__, 'handle_delete_question' ] );
        add_action( 'admin_post_rzpa_quiz_toggle_question', [ __CLASS__, 'handle_toggle_question' ] );
        add_action( 'admin_post_rzpa_quiz_save_email_cfg',  [ __CLASS__, 'handle_save_email_cfg' ] );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'rzpa-dashboard',
            'Profil-Quiz',
            '🎯 Profil-Quiz',
            'manage_options',
            'rzpa-quiz-submissions',
            [ __CLASS__, 'page_main' ]
        );
        // Hidden page – edit a single question
        add_submenu_page( null, 'Rediger Spørgsmål', '', 'manage_options', 'rzpa-quiz-edit-question', [ __CLASS__, 'page_edit_question' ] );
        // Hidden page – PDF report for a single submission
        add_submenu_page( null, 'Quiz PDF', '', 'manage_options', 'rzpa-quiz-pdf', [ __CLASS__, 'page_pdf' ] );
    }

    // ── Main tabbed page ──────────────────────────────────────────────────────

    public static function page_main(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        $tab     = sanitize_key( $_GET['tab'] ?? 'submissions' );
        $allowed = [ 'submissions', 'questions', 'email' ];
        if ( ! in_array( $tab, $allowed, true ) ) $tab = 'submissions';
        require_once __DIR__ . '/views/admin-overview.php';
    }

    // ── PDF report page ───────────────────────────────────────────────────────

    public static function page_pdf(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        $id   = (int) ( $_GET['submission_id'] ?? 0 );
        $data = $id ? RZPA_Quiz_DB::get_submission_detail( $id ) : null;
        if ( ! $data ) wp_die( 'Besvarelse ikke fundet.' );
        require_once __DIR__ . '/views/admin-pdf.php';
    }

    // ── Edit question page ────────────────────────────────────────────────────

    public static function page_edit_question(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        require_once __DIR__ . '/views/admin-edit-question.php';
    }

    // ── Save question + answers ───────────────────────────────────────────────

    public static function handle_save_question(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzpa_q_nonce'] ?? '', 'rzpa_quiz_save_question' ) ) wp_die( 'Ugyldig nonce' );

        global $wpdb;
        $qt = $wpdb->prefix . 'rzpa_quiz_questions';
        $at = $wpdb->prefix . 'rzpa_quiz_answers';

        $qid           = (int) ( $_POST['qid'] ?? 0 );
        $question_text = sanitize_textarea_field( $_POST['question_text'] ?? '' );
        $helper_text   = sanitize_textarea_field( $_POST['helper_text']   ?? '' );
        $is_active     = isset( $_POST['is_active'] ) ? 1 : 0;

        if ( ! $question_text ) {
            wp_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=questions&error=empty' ) );
            exit;
        }

        if ( $qid ) {
            $wpdb->update( $qt, [
                'question_text' => $question_text,
                'helper_text'   => $helper_text ?: null,
                'is_active'     => $is_active,
            ], [ 'id' => $qid ] );
        } else {
            $max_order = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$qt}" );
            $wpdb->insert( $qt, [
                'question_text' => $question_text,
                'helper_text'   => $helper_text ?: null,
                'is_active'     => $is_active,
                'sort_order'    => $max_order + 1,
            ] );
            $qid = (int) $wpdb->insert_id;
        }

        // Delete old answers, then re-insert from form
        $wpdb->delete( $at, [ 'question_id' => $qid ] );

        $answer_texts   = (array) ( $_POST['answer_text']    ?? [] );
        $answer_fbs     = (array) ( $_POST['answer_fb']      ?? [] );
        $answer_tags    = (array) ( $_POST['answer_tag']      ?? [] );
        $answer_weights = (array) ( $_POST['answer_weights']  ?? [] );

        foreach ( $answer_texts as $sort => $atext ) {
            $atext = sanitize_text_field( $atext );
            if ( ! $atext ) continue;
            $afb  = sanitize_text_field( $answer_fbs[ $sort ] ?? '' );
            $atag = sanitize_text_field( $answer_tags[ $sort ] ?? '' );
            $raw  = (array) ( $answer_weights[ $sort ] ?? [] );
            $weights = [];
            foreach ( $raw as $slug => $val ) {
                $weights[ sanitize_key( $slug ) ] = max( 0, (int) $val );
            }
            $wpdb->insert( $at, [
                'question_id'   => $qid,
                'answer_text'   => $atext,
                'feedback_text' => $afb,
                'tagline'       => $atag,
                'sort_order'    => (int) $sort,
                'weights'       => wp_json_encode( $weights ),
            ] );
        }

        wp_redirect( admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=questions&saved=1' ) );
        exit;
    }

    // ── Delete question ───────────────────────────────────────────────────────

    public static function handle_delete_question(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzpa_q_del_nonce'] ?? '', 'rzpa_quiz_delete_question' ) ) wp_die( 'Ugyldig nonce' );

        global $wpdb;
        $qid = (int) ( $_POST['qid'] ?? 0 );
        if ( $qid ) {
            $wpdb->delete( $wpdb->prefix . 'rzpa_quiz_answers',   [ 'question_id' => $qid ] );
            $wpdb->delete( $wpdb->prefix . 'rzpa_quiz_questions', [ 'id'          => $qid ] );
        }

        wp_redirect( admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=questions&deleted=1' ) );
        exit;
    }

    // ── Toggle active ─────────────────────────────────────────────────────────

    public static function handle_toggle_question(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzpa_q_toggle_nonce'] ?? '', 'rzpa_quiz_toggle_question' ) ) wp_die( 'Ugyldig nonce' );

        global $wpdb;
        $qid    = (int) ( $_POST['qid']       ?? 0 );
        $active = (int) ( $_POST['is_active']  ?? 0 );
        if ( $qid ) {
            $wpdb->update( $wpdb->prefix . 'rzpa_quiz_questions', [ 'is_active' => $active ], [ 'id' => $qid ] );
        }

        wp_redirect( admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=questions' ) );
        exit;
    }

    // ── Save email config ─────────────────────────────────────────────────────

    public static function handle_save_email_cfg(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Adgang nægtet' );
        if ( ! wp_verify_nonce( $_POST['rzpa_email_nonce'] ?? '', 'rzpa_quiz_save_email_cfg' ) ) wp_die( 'Ugyldig nonce' );

        $cfg = [
            'admin_email'  => sanitize_email( $_POST['admin_email']  ?? '' ) ?: 'lie@rezponz.dk',
            'cta_url'      => esc_url_raw( $_POST['cta_url']         ?? '/book-en-samtale' ),
            'cta_text'     => sanitize_text_field( $_POST['cta_text']    ?? 'Book samtale →' ),
            'user_subject' => sanitize_text_field( $_POST['user_subject'] ?? '' ),
        ];
        update_option( 'rzpa_quiz_email_cfg', $cfg );

        wp_redirect( admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=email&saved=1' ) );
        exit;
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    public static function get_all_questions(): array {
        global $wpdb;
        $qt = $wpdb->prefix . 'rzpa_quiz_questions';
        $at = $wpdb->prefix . 'rzpa_quiz_answers';

        $questions = $wpdb->get_results(
            "SELECT * FROM {$qt} ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        ) ?: [];

        foreach ( $questions as &$q ) {
            $q['answers'] = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$at} WHERE question_id = %d ORDER BY sort_order ASC", (int) $q['id'] ),
                ARRAY_A
            ) ?: [];
            foreach ( $q['answers'] as &$a ) {
                $a['weights'] = json_decode( $a['weights'] ?? '{}', true ) ?: [];
            }
        }

        return $questions;
    }

    public static function get_email_cfg(): array {
        $defaults = [
            'admin_email'  => 'lie@rezponz.dk',
            'cta_url'      => '/book-en-samtale',
            'cta_text'     => 'Book samtale →',
            'user_subject' => '',
        ];
        return array_merge( $defaults, (array) get_option( 'rzpa_quiz_email_cfg', [] ) );
    }
}
