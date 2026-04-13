<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Blog_Gen_Admin {

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'rzpa-dashboard',
            'Blog Generator',
            '✍️ Blog Generator',
            'manage_options',
            'rzpa-blog-generator',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue( string $hook ): void {
        if ( $hook !== 'rezponz-analytics_page_rzpa-blog-generator' ) return;

        wp_enqueue_media(); // WP Media Library picker

        wp_enqueue_script(
            'rzpa-blog-generator',
            plugin_dir_url( dirname( __DIR__ ) . '/rezponz-analytics.php' ) . 'modules/blog-generator/assets/blog-generator.js',
            [ 'jquery' ],
            RZPA_VERSION,
            true
        );

        wp_localize_script( 'rzpa-blog-generator', 'RZPA_BG', [
            'apiBase'  => rest_url( 'rzpa/v1/blog-gen/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'pillars'  => RZPA_Blog_Gen_DB::PILLARS,
            'types'    => RZPA_Blog_Gen_DB::ARTICLE_TYPES,
            'targets'  => RZPA_Blog_Gen_DB::TARGETS,
        ] );
    }

    public static function render_page(): void {
        require_once __DIR__ . '/views/blog-generator.php';
    }
}
