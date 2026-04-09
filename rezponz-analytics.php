<?php
/**
 * Plugin Name:  Rezponz Analytics
 * Plugin URI:   https://rezponz.dk
 * Description:  Marketing Intelligence Dashboard – SEO, AI-synlighed, Meta, Snapchat og TikTok Ads.
 * Version:      2.2.8
 * Author:       Rezponz
 * Author URI:   https://rezponz.dk
 * License:      GPL-2.0+
 * Text Domain:  rezponz-analytics
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RZPA_VERSION',     '2.2.8' );
define( 'RZPA_PLUGIN_FILE', __FILE__ );
define( 'RZPA_DIR',         plugin_dir_path( __FILE__ ) );
define( 'RZPA_URL',         plugin_dir_url( __FILE__ ) );
define( 'RZPA_DB_VER',      '4' );

// Composer / vendor autoloader (DomPDF m.fl.)
if ( file_exists( RZPA_DIR . 'vendor/autoload.php' ) ) {
    require_once RZPA_DIR . 'vendor/autoload.php';
}

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

// ── Rekruttering Module ──────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/rekruttering/class-rekruttering.php';
RZPA_Rekruttering::init();

// ── Crew Module ─────────────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/crew/class-crew-db.php';
require_once RZPA_DIR . 'modules/crew/class-crew-tracking.php';
require_once RZPA_DIR . 'modules/crew/class-crew.php';

// ── Henvis Din Ven Module ────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/henvis/class-henvis.php';

// ── Live Quiz Module ─────────────────────────────────────────────────────────
// Load RZLQ_Dept first — other classes depend on it
require_once RZPA_DIR . 'modules/live-quiz/class-live-quiz-departments.php';
require_once RZPA_DIR . 'modules/live-quiz/class-live-quiz-db.php';
require_once RZPA_DIR . 'modules/live-quiz/class-live-quiz-api.php';
require_once RZPA_DIR . 'modules/live-quiz/class-live-quiz-admin.php';
require_once RZPA_DIR . 'modules/live-quiz/class-live-quiz.php';

// ── Profil-Quiz Module ───────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/quiz/class-quiz-db.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-pdf.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-mailer.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-api.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-admin.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz.php';

register_activation_hook( __FILE__, function () {
    RZPA_Database::install();
    RZPZ_Crew_DB::install();
    RZPZ_Henvis::install_db();
    RZPA_Quiz_DB::install();
    RZLQ_DB::install();
    RZLQ_Dept::install();
} );
register_deactivation_hook( __FILE__, [ 'RZPA_Scheduler', 'clear_crons' ] );

// Forhindre caching af sider med Profil-Quiz shortcode
add_action( 'template_redirect', function () {
    global $post;
    if ( $post && has_shortcode( $post->post_content, 'rezponz_quiz' ) ) {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
    }
} );

// ── SMTP-konfiguration via phpmailer_init (kører for alle wp_mail()-kald) ──────
add_action( 'phpmailer_init', function ( $phpmailer ) {
    $opts = get_option( 'rzpa_settings', [] );
    if ( empty( $opts['smtp_enabled'] ) ) return;

    $phpmailer->isSMTP();
    $phpmailer->Host     = $opts['smtp_host']     ?? '';
    $phpmailer->Port     = (int) ( $opts['smtp_port'] ?? 587 );
    $phpmailer->SMTPAuth = ! empty( $opts['smtp_username'] );
    $phpmailer->Username = $opts['smtp_username'] ?? '';
    $phpmailer->Password = $opts['smtp_password'] ?? '';

    $enc = $opts['smtp_encryption'] ?? 'tls';
    if ( $enc === 'ssl' )      $phpmailer->SMTPSecure = 'ssl';
    elseif ( $enc === 'tls' ) $phpmailer->SMTPSecure = 'tls';
    else                       $phpmailer->SMTPSecure = '';

    if ( ! empty( $opts['smtp_from_email'] ) ) {
        $phpmailer->From     = $opts['smtp_from_email'];
        $phpmailer->FromName = $opts['smtp_from_name'] ?? 'Rezponz';
    }
} );

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
        // Ryd WP Fastest Cache ved version-skift så opdaterede JS/CSS-filer leveres
        if ( function_exists( 'wpfc_clear_all_cache' ) ) {
            wpfc_clear_all_cache( true );
        }
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

    // Quiz module
    if ( get_option( RZPA_Quiz_DB::DB_VERSION_KEY ) !== RZPA_Quiz_DB::DB_VERSION ) {
        RZPA_Quiz_DB::install();
    }
    RZPA_Quiz_API::init();
    RZPA_Quiz_Admin::init();
    RZPA_Quiz::init();

    // Live Quiz module
    if ( get_option( RZLQ_DB::DB_VERSION_KEY ) !== RZLQ_DB::DB_VERSION ) {
        RZLQ_DB::install();
    }
    if ( get_option( RZLQ_Dept::DB_VERSION_KEY ) !== RZLQ_Dept::DB_VERSION ) {
        RZLQ_Dept::install();
    }
    RZLQ_Dept::init(); // hooks: restrict admin for dept users, clean admin bar
    RZLQ_API::init();
    RZLQ_Admin::init();
    RZLQ_Quiz::init();

    // ── Auto-opdatering via GitHub ──────────────────────────────────────────
    $opts  = get_option( 'rzpa_settings', [] );
    $owner = sanitize_text_field( $opts['github_owner'] ?? '' );
    $repo  = sanitize_text_field( $opts['github_repo']  ?? '' );
    $token = sanitize_text_field( $opts['github_token'] ?? '' );

    if ( $owner && $repo ) {
        ( new RZPA_Updater( RZPA_PLUGIN_FILE, $owner, $repo, $token ) )->init();
    }
} );
