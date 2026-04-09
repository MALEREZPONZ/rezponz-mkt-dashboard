<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main orchestrator for Rezponz Live Quiz module.
 * Registers CPT, shortcode, and asset loading.
 */
class RZLQ_Quiz {

    const CPT = 'rezponz_quiz';

    public static function init(): void {
        add_action( 'init',              [ __CLASS__, 'register_cpt' ] );
        add_shortcode( 'rezponz_player', [ __CLASS__, 'player_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_player' ] );
        // Save quiz meta
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_meta' ], 10, 2 );
    }

    // ── CPT ───────────────────────────────────────────────────────────────────

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'label'               => 'Live Quizzer',
            'labels'              => [
                'name'          => 'Live Quizzer',
                'singular_name' => 'Quiz',
                'add_new'       => 'Opret quiz',
                'add_new_item'  => 'Opret ny quiz',
                'edit_item'     => 'Rediger quiz',
                'view_item'     => 'Vis quiz',
                'search_items'  => 'Søg quizzer',
                'not_found'     => 'Ingen quizzer fundet',
            ],
            'public'              => false,
            'show_ui'             => false, // We use our own admin pages
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'supports'            => [ 'title' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ] );
    }

    // ── Quiz data helpers ─────────────────────────────────────────────────────

    /**
     * Returns questions array for a quiz (post_id).
     * Each item: {type, text, image_id, time_limit, points, options, ...}
     */
    public static function get_questions( int $quiz_id ): array {
        $raw = get_post_meta( $quiz_id, '_rzlq_questions', true );
        if ( ! $raw ) return [];
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    public static function save_questions( int $quiz_id, array $questions ): void {
        update_post_meta( $quiz_id, '_rzlq_questions', wp_json_encode( $questions ) );
    }

    public static function save_meta( int $post_id, WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( ! isset( $_POST['rzlq_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['rzlq_nonce'], 'rzlq_save_quiz_' . $post_id ) ) return;

        $raw_json = wp_unslash( $_POST['rzlq_questions'] ?? '[]' );
        $questions = json_decode( $raw_json, true );
        if ( is_array( $questions ) ) {
            // Sanitize each question
            $clean = array_map( [ __CLASS__, 'sanitize_question' ], $questions );
            self::save_questions( $post_id, array_values( $clean ) );
        }

        // Save cover image
        $cover_id = (int) ( $_POST['rzlq_cover_id'] ?? 0 );
        update_post_meta( $post_id, '_rzlq_cover_id', $cover_id );
    }

    public static function sanitize_question( array $q ): array {
        $type = in_array( $q['type'] ?? '', [ 'multiple_choice', 'true_false', 'yes_no', 'poll', 'slider' ] )
            ? $q['type'] : 'multiple_choice';

        $clean = [
            'type'       => $type,
            'text'       => sanitize_text_field( $q['text'] ?? '' ),
            'image_id'   => (int) ( $q['image_id'] ?? 0 ),
            'time_limit' => max( 5, min( 120, (int) ( $q['time_limit'] ?? 20 ) ) ),
            'points'     => in_array( (int) ( $q['points'] ?? 1000 ), [ 0, 500, 1000, 2000 ] )
                            ? (int) $q['points'] : 1000,
        ];

        if ( $type === 'slider' ) {
            $clean['min']       = (int) ( $q['min'] ?? 1 );
            $clean['max']       = (int) ( $q['max'] ?? 10 );
            $clean['correct']   = (float) ( $q['correct'] ?? 5 );
            $clean['tolerance'] = max( 0, (float) ( $q['tolerance'] ?? 0 ) );
        } else {
            $options = [];
            foreach ( (array) ( $q['options'] ?? [] ) as $i => $o ) {
                $options[] = [
                    'id'      => $i,
                    'text'    => sanitize_text_field( $o['text'] ?? '' ),
                    'correct' => ! empty( $o['correct'] ),
                ];
            }
            $clean['options'] = $options;
        }

        return $clean;
    }

    // ── Player shortcode ──────────────────────────────────────────────────────

    public static function player_shortcode( array $atts = [] ): string {
        self::enqueue_player_assets();

        $api_url = esc_attr( rest_url( 'rezponz/v1' ) );
        $nonce   = esc_attr( wp_create_nonce( 'wp_rest' ) );

        return '<div id="rzlq-player-root" data-api="' . $api_url . '" data-nonce="' . $nonce . '"></div>';
    }

    public static function maybe_enqueue_player(): void {
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'rezponz_player' ) ) {
            self::enqueue_player_assets();
        }
    }

    private static function enqueue_player_assets(): void {
        static $done = false;
        if ( $done ) return;
        $done = true;

        $base = RZPA_URL . 'modules/live-quiz/assets/';
        $ver  = RZPA_VERSION;

        wp_enqueue_style(  'rzlq-player', $base . 'player.css', [], $ver );
        wp_enqueue_script( 'rzlq-player', $base . 'player.js',  [], $ver, true );
    }

    // Enqueue host assets (called by admin page)
    public static function enqueue_host_assets(): void {
        $base = RZPA_URL . 'modules/live-quiz/assets/';
        $ver  = RZPA_VERSION;

        wp_enqueue_style(  'rzlq-host', $base . 'host.css', [], $ver );
        wp_enqueue_script( 'rzlq-host', $base . 'host.js',  [], $ver, true );
    }

    // Enqueue admin edit assets
    public static function enqueue_admin_edit_assets( int $quiz_id ): void {
        $base = RZPA_URL . 'modules/live-quiz/assets/';
        $ver  = RZPA_VERSION;

        wp_enqueue_media();
        wp_enqueue_style(  'rzlq-admin-edit', $base . 'admin-edit.css', [], $ver );
        wp_enqueue_script( 'rzlq-admin-edit', $base . 'admin-edit.js',  [], $ver, true );

        wp_localize_script( 'rzlq-admin-edit', 'rzlqEdit', [
            'quiz_id'   => $quiz_id,
            'questions' => self::get_questions( $quiz_id ),
            'nonce'     => wp_create_nonce( 'rzlq_save_quiz_' . $quiz_id ),
        ] );
    }
}
