<?php
/**
 * Plugin Name:  Rezponz Analytics
 * Plugin URI:   https://rezponz.dk
 * Description:  Marketing Intelligence Dashboard – SEO, AI-synlighed, Meta, Snapchat og TikTok Ads.
 * Version:      1.4.1
 * Author:       Rezponz
 * Author URI:   https://rezponz.dk
 * License:      GPL-2.0+
 * Text Domain:  rezponz-analytics
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RZPA_VERSION',     '1.4.1' );
define( 'RZPA_PLUGIN_FILE', __FILE__ );
define( 'RZPA_DIR',         plugin_dir_path( __FILE__ ) );
define( 'RZPA_URL',         plugin_dir_url( __FILE__ ) );
define( 'RZPA_DB_VER',      '4' );

require_once RZPA_DIR . 'includes/class-database.php';
require_once RZPA_DIR . 'includes/class-scheduler.php';
require_once RZPA_DIR . 'includes/class-pdf-generator.php';
require_once RZPA_DIR . 'includes/class-updater.php';
require_once RZPA_DIR . 'includes/api/class-google-seo.php';
require_once RZPA_DIR . 'includes/api/class-meta-ads.php';
require_once RZPA_DIR . 'includes/api/class-snapchat-ads.php';
require_once RZPA_DIR . 'includes/api/class-tiktok-ads.php';
require_once RZPA_DIR . 'includes/api/class-google-ads.php';
require_once RZPA_DIR . 'includes/class-rest-api.php';
require_once RZPA_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, [ 'RZPA_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'RZPA_Scheduler', 'clear_crons' ] );

add_action( 'plugins_loaded', function () {
    // Auto-upgrade DB schema when plugin version changes
    if ( get_option( 'rzpa_db_version' ) !== RZPA_DB_VER ) {
        RZPA_Database::install();
    }

    RZPA_Admin::init();
    RZPA_REST_API::init();
    RZPA_Scheduler::init();

    // ── Auto-opdatering via GitHub ──────────────────────────────────────────
    $opts  = get_option( 'rzpa_settings', [] );
    $owner = sanitize_text_field( $opts['github_owner'] ?? '' );
    $repo  = sanitize_text_field( $opts['github_repo']  ?? '' );
    $token = sanitize_text_field( $opts['github_token'] ?? '' );

    if ( $owner && $repo ) {
        ( new RZPA_Updater( RZPA_PLUGIN_FILE, $owner, $repo, $token ) )->init();
    }
} );
