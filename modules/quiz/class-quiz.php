<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Quiz {

    public static function init(): void {
        add_shortcode( 'rezponz_quiz', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
    }

    // ── Shortcode [rezponz_quiz] ───────────────────────────────────────────────

    public static function shortcode( array $atts = [] ): string {
        // Enqueue assets
        self::enqueue_assets();

        // Load quiz data inline so no extra HTTP request is needed on page load
        $quiz_data = RZPA_Quiz_DB::get_quiz_data();
        $json      = esc_attr( wp_json_encode( $quiz_data ) );
        $nonce     = wp_create_nonce( 'wp_rest' );
        $api_url   = esc_attr( rest_url( 'rzpa/v1/quiz/submit' ) );

        ob_start();
        ?>
        <div class="rzq-page-outer">
          <div id="rzq-root"
               class="rzq-wrapper"
               data-quiz="<?php echo $json; ?>"
               data-submit-url="<?php echo $api_url; ?>"
               data-nonce="<?php echo esc_attr( $nonce ); ?>">
              <div class="rzq-skeleton">
                  <div class="rzq-skeleton-icon"></div>
                  <div class="rzq-skeleton-line wide"></div>
                  <div class="rzq-skeleton-line medium"></div>
                  <div class="rzq-skeleton-line narrow"></div>
              </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Asset enqueue ─────────────────────────────────────────────────────────

    public static function maybe_enqueue(): void {
        // Only enqueue on pages that have the shortcode
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'rezponz_quiz' ) ) {
            self::enqueue_assets();
        }
    }

    private static function enqueue_assets(): void {
        static $done = false;
        if ( $done ) return;
        $done = true;

        $base    = RZPA_URL . 'modules/quiz/assets/';
        $js_file = RZPA_DIR . 'modules/quiz/assets/quiz.js';
        $ver     = file_exists( $js_file ) ? filemtime( $js_file ) : RZPA_VERSION;

        wp_enqueue_style(
            'rzpa-quiz-css',
            $base . 'quiz.css',
            [],
            $ver
        );

        wp_enqueue_script(
            'rzpa-quiz-js',
            $base . 'quiz.js',
            [],
            $ver,
            true  // load in footer
        );
    }
}
