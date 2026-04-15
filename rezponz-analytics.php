<?php
/**
 * Plugin Name:  Rezponz Analytics
 * Plugin URI:   https://rezponz.dk
 * Description:  Marketing Intelligence Dashboard – SEO, AI-synlighed, Meta, Snapchat og TikTok Ads.
 * Version:      3.5.35
 * Author:       Rezponz
 * Author URI:   https://rezponz.dk
 * License:      GPL-2.0+
 * Text Domain:  rezponz-analytics
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RZPA_VERSION',     '3.5.35' );
define( 'RZPA_PLUGIN_FILE', __FILE__ );
define( 'RZPA_DIR',         plugin_dir_path( __FILE__ ) );
define( 'RZPA_URL',         plugin_dir_url( __FILE__ ) );
define( 'RZPA_DB_VER',      '6' );

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
require_once RZPA_DIR . 'includes/class-sitemap-manager.php';
require_once RZPA_DIR . 'includes/class-rest-api.php';
require_once RZPA_DIR . 'includes/class-admin.php';

// ── ESG Module ──────────────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/esg/class-esg.php';

// ── Rekruttering Module ──────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/rekruttering/class-rekruttering.php';
// NOTE: init() called inside plugins_loaded below to ensure correct hook order

// ── RezCRM Module ────────────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-db.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-forms-db.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-aon.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-forms.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-auth.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-users.php';
require_once RZPA_DIR . 'modules/rezcrm/class-rezcrm-admin.php';

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

// ── SEO Engine Module ────────────────────────────────────────────────────────
$rzpa_seo_load_error = null;
$rzpa_seo_files = [
    'class-seo-db.php', 'class-seo-template.php', 'class-seo-meta.php',
    'class-seo-quality.php', 'class-seo-ai.php', 'class-seo-blog.php',
    'class-seo-generator.php', 'class-seo-linking.php', 'class-seo-csv.php',
    'class-seo-engine.php', 'class-seo-admin.php',
];
foreach ( $rzpa_seo_files as $_rzpa_f ) {
    try {
        require_once RZPA_DIR . 'modules/seo-engine/' . $_rzpa_f;
    } catch ( \Throwable $e ) {
        $rzpa_seo_load_error = 'SEO Engine load fejl i ' . $_rzpa_f . ': ' . $e->getMessage() . ' (linje ' . $e->getLine() . ')';
        error_log( '[Rezponz] ' . $rzpa_seo_load_error );
        break;
    }
}
// Definer kun konstanten hvis alle filer loadede OK (define() kan ikke overskrives)
if ( ! $rzpa_seo_load_error ) {
    define( 'RZPA_SEO_ENGINE_ENABLED', true );
} else {
    define( 'RZPA_SEO_ENGINE_ENABLED', false );
}

// ── Blog Generator Module ────────────────────────────────────────────────────
require_once RZPA_DIR . 'includes/class-ai-helper.php';
require_once RZPA_DIR . 'modules/blog-generator/class-blog-gen-db.php';
require_once RZPA_DIR . 'modules/blog-generator/class-blog-gen-api.php';
require_once RZPA_DIR . 'modules/blog-generator/class-blog-gen-admin.php';
require_once RZPA_DIR . 'modules/blog-generator/class-blog-gen.php';

// ── Profil-Quiz Module ───────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/quiz/class-quiz-db.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-pdf.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-mailer.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-api.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz-admin.php';
require_once RZPA_DIR . 'modules/quiz/class-quiz.php';

// ── ESG Module ───────────────────────────────────────────────────────────────
require_once RZPA_DIR . 'modules/esg/class-esg.php';

// ── ComplyCloud Monitor Module ───────────────────────────────────────────────
require_once RZPA_DIR . 'modules/complycloud/class-complycloud.php';

register_activation_hook( __FILE__, function () {
    RZPA_Database::install();
    RZPZ_Crew_DB::install();
    RZPZ_Henvis::install_db();
    RZPA_Quiz_DB::install();
    RZLQ_DB::install();
    RZLQ_Dept::install();
    RZPA_SEO_DB::install();
    RZPA_Blog_Gen_DB::install();
    RZPZ_CRM_DB::install();
    RZPZ_CRM_Forms_DB::install();
    RZPZ_CRM_Auth::install_audit_table();
    RZPZ_CRM_Auth::register_role();
    // Registrér sitemap rewrite-regel og flush ved aktivering
    RZPA_Sitemap_Manager::flush_rules();
} );
register_deactivation_hook( __FILE__, [ 'RZPA_Scheduler', 'clear_crons' ] );
register_deactivation_hook( __FILE__, [ 'RZPZ_CRM_Auth', 'remove_role' ] );
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'rzpz_crm_dispatch_rejections' );
    wp_clear_scheduled_hook( 'rzpz_crm_gdpr_cleanup' );
    RZPZ_ComplyCloud::deactivate();
} );

// ── Blog Generator: gem indstilling via AJAX ─────────────────────────────────
add_action( 'wp_ajax_rzpa_save_blog_gen_setting', function () {
    check_ajax_referer( 'wp_rest', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );
    $key   = sanitize_key( $_POST['key'] ?? '' );
    $value = wp_unslash( $_POST['value'] ?? '' );
    $allowed = [ 'blog_gen_brand_voice', 'blog_gen_category', 'blog_gen_default_type', 'blog_gen_default_words',
                 'blog_gen_elementor_template_id', 'blog_gen_default_author' ];
    if ( ! in_array( $key, $allowed, true ) ) wp_die( 'Invalid key', 400 );
    $opts         = get_option( 'rzpa_settings', [] );
    $opts[ $key ] = sanitize_textarea_field( $value );
    update_option( 'rzpa_settings', $opts );
    wp_send_json_success();
} );

// ── Structured data + GEO meta output i <head> ───────────────────────────────
add_action( 'wp_head', function () {
    if ( ! is_singular() ) return;
    $post_id = get_the_ID();

    // FAQ JSON-LD (FAQPage schema) — decode+re-encode to guarantee valid JSON output
    $faq_schema = get_post_meta( $post_id, '_rzpa_faq_schema', true );
    if ( $faq_schema ) {
        $decoded = json_decode( $faq_schema, true );
        if ( $decoded ) {
            echo "\n" . wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
        }
    }

    // Article JSON-LD (BlogPosting schema)
    $article_schema = get_post_meta( $post_id, '_rzpa_article_schema', true );
    if ( $article_schema ) {
        $decoded = json_decode( $article_schema, true );
        if ( $decoded ) {
            echo "\n" . wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
        }
    }

    // GEO meta-tags — kun på AI-genererede blogindlæg (lokal SEO + AI-synlighed Nordjylland)
    if ( get_post_meta( $post_id, '_rzpa_article_schema', true ) ) {
        echo '<meta name="geo.region"    content="DK-81">' . "\n";   // DK-81 = Nordjylland
        echo '<meta name="geo.placename" content="Aalborg, Danmark">' . "\n";
        echo '<meta name="geo.position"  content="57.0488;9.9217">' . "\n";
        echo '<meta name="ICBM"          content="57.0488, 9.9217">' . "\n";
    }
} );

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
    RZPA_Rekruttering::init();
    RZPA_Sitemap_Manager::init();
    RZPA_ESG::init();
    RZPZ_ComplyCloud::init();

    // RezCRM module
    if ( get_option( RZPZ_CRM_DB::DB_VERSION_KEY ) !== RZPZ_CRM_DB::DB_VERSION ) {
        RZPZ_CRM_DB::install();
    }
    if ( get_option( RZPZ_CRM_Forms_DB::DB_VERSION_KEY ) !== RZPZ_CRM_Forms_DB::DB_VERSION ) {
        RZPZ_CRM_Forms_DB::install();
    }
    RZPZ_RezCRM::init();
    RZPZ_CRM_Forms::init();
    RZPZ_CRM_Auth::init();
    RZPZ_CRM_Users::init();

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

    // SEO Engine module
    global $rzpa_seo_load_error;
    if ( $rzpa_seo_load_error ) {
        // Vis fejl i WP admin så vi kan se præcis hvad der fejler
        add_action( 'admin_notices', function() use ( $rzpa_seo_load_error ) {
            echo '<div class="notice notice-error"><p><strong>Rezponz SEO Engine fejl:</strong> '
                . esc_html( $rzpa_seo_load_error ) . '</p></div>';
        } );
    } elseif ( defined( 'RZPA_SEO_ENGINE_ENABLED' ) && RZPA_SEO_ENGINE_ENABLED ) {
        try {
            if ( get_option( RZPA_SEO_DB::DB_VERSION_KEY ) !== RZPA_SEO_DB::DB_VERSION ) {
                RZPA_SEO_DB::install();
            }
            RZPA_SEO_Engine::init();
            RZPA_SEO_Meta::init();
            RZPA_SEO_Admin::init();
        } catch ( \Throwable $e ) {
            $msg = 'SEO Engine init fejl: ' . $e->getMessage() . ' i ' . basename( $e->getFile() ) . ':' . $e->getLine();
            error_log( '[Rezponz] ' . $msg );
            add_action( 'admin_notices', function() use ( $msg ) {
                echo '<div class="notice notice-error"><p><strong>Rezponz SEO Engine:</strong> ' . esc_html( $msg ) . '</p></div>';
            } );
        }
    }

    // Blog Generator module
    if ( get_option( RZPA_Blog_Gen_DB::DB_VERSION_KEY ) !== RZPA_Blog_Gen_DB::DB_VERSION ) {
        RZPA_Blog_Gen_DB::install();
    }
    RZPA_Blog_Gen::init();

    // ESG module
    RZPA_ESG::init();

    // ── Auto-opdatering via GitHub ──────────────────────────────────────────
    $opts  = get_option( 'rzpa_settings', [] );
    $owner = sanitize_text_field( $opts['github_owner'] ?? '' );
    $repo  = sanitize_text_field( $opts['github_repo']  ?? '' );
    $token = sanitize_text_field( $opts['github_token'] ?? '' );

    if ( $owner && $repo ) {
        ( new RZPA_Updater( RZPA_PLUGIN_FILE, $owner, $repo, $token ) )->init();
    }
} );

// Enqueue blog visitor tracking on single blog posts (frontend)
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_single() || get_post_type() !== 'post' ) return;
    wp_enqueue_script(
        'rzpa-blog-tracker',
        RZPA_URL . 'assets/js/rzpa-blog-tracker.js',
        [],
        RZPA_VERSION,
        true
    );
    wp_localize_script( 'rzpa-blog-tracker', 'RZPA_TRACKER', [
        'postId' => get_the_ID(),
        'apiUrl' => rest_url( 'rzpa/v1/blog/visit' ),
    ] );
} );
