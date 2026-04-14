<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Blog_Gen_Admin {

    public static function init(): void {
        // Menu registreres i RZPA_Admin::add_menu() for korrekt placering efter "Blog Indsigt"
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( string $hook ): void {
        // Hook-suffix varierer med menu-hierarki — brug strpos for robusthed
        if ( strpos( $hook, 'rzpa-blog-generator' ) === false ) return;

        wp_enqueue_media(); // WP Media Library picker

        wp_enqueue_script(
            'rzpa-blog-generator',
            plugin_dir_url( dirname( __DIR__ ) . '/rezponz-analytics.php' ) . 'modules/blog-generator/assets/blog-generator.js',
            [ 'jquery' ],
            RZPA_VERSION,
            true
        );

        $opts = get_option( 'rzpa_settings', [] );
        wp_localize_script( 'rzpa-blog-generator', 'RZPA_BG', [
            'apiBase'  => rest_url( 'rzpa/v1/blog-gen/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'pillars'  => RZPA_Blog_Gen_DB::PILLARS,
            'types'    => RZPA_Blog_Gen_DB::ARTICLE_TYPES,
            'targets'  => RZPA_Blog_Gen_DB::TARGETS,
            'settings' => [
                'blog_gen_elementor_template_id' => $opts['blog_gen_elementor_template_id'] ?? '',
                'blog_gen_default_author'        => $opts['blog_gen_default_author']        ?? '',
                'blog_gen_brand_voice'           => $opts['blog_gen_brand_voice']           ?? '',
            ],
        ] );
    }

    public static function render_page(): void {
        require_once __DIR__ . '/views/blog-generator.php';
    }
}
