<?php
/**
 * Plugin Name:  Rezponz Analytics
 * Plugin URI:   https://rezponz.dk
 * Description:  Marketing Intelligence Dashboard – SEO, AI-synlighed, Meta, Snapchat og TikTok Ads.
 * Version:      1.8.0
 * Author:       Rezponz
 * Author URI:   https://rezponz.dk
 * License:      GPL-2.0+
 * Text Domain:  rezponz-analytics
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RZPA_VERSION',     '1.8.0' );
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

// ── Crew Module ─────────────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/crew/class-crew-db.php';
require_once RZPA_DIR . 'modules/crew/class-crew-tracking.php';
require_once RZPA_DIR . 'modules/crew/class-crew.php';

// ── Henvis Din Ven Module ────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/henvis/class-henvis.php';

register_activation_hook( __FILE__, function () {
    RZPA_Database::install();
    RZPZ_Crew_DB::install();
    RZPZ_Henvis::install_db();
} );
register_deactivation_hook( __FILE__, [ 'RZPA_Scheduler', 'clear_crons' ] );

add_action( 'plugins_loaded', function () {
    // Auto-upgrade DB schema when plugin version changes
    if ( get_option( 'rzpa_db_version' ) !== RZPA_DB_VER ) {
        RZPA_Database::install();
        RZPZ_Crew_DB::install();
    }

    // Ryd Meta top-ads transients ved version-skift (undgår stale cached data)
    if ( get_option( 'rzpa_plugin_version' ) !== RZPA_VERSION ) {
        delete_transient( 'rzpa_meta_top_ads_7' );
        delete_transient( 'rzpa_meta_top_ads_30' );
        delete_transient( 'rzpa_meta_top_ads_90' );
        update_option( 'rzpa_plugin_version', RZPA_VERSION );
    }

    RZPA_Admin::init();
    RZPA_REST_API::init();
    RZPA_Scheduler::init();

    // Crew module – auto-install tables if missing or outdated
    if ( get_option( RZPZ_Crew_DB::DB_VERSION_KEY ) !== RZPZ_Crew_DB::DB_VERSION ) {
        RZPZ_Crew_DB::install();
    }
    RZPZ_Crew::init();
    RZPZ_Crew_Tracking::init();
    RZPZ_Henvis::init();

    // ── Auto-opdatering via GitHub ──────────────────────────────────────────
    $opts  = get_option( 'rzpa_settings', [] );
    $owner = sanitize_text_field( $opts['github_owner'] ?? '' );
    $repo  = sanitize_text_field( $opts['github_repo']  ?? '' );
    $token = sanitize_text_field( $opts['github_token'] ?? '' );

    if ( $owner && $repo ) {
        ( new RZPA_Updater( RZPA_PLUGIN_FILE, $owner, $repo, $token ) )->init();
    }
} );
