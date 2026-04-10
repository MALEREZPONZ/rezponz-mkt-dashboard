<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Admin Controller.
 *
 * Registers menus, enqueues assets, dispatches page renderers,
 * handles all form submissions and AJAX endpoints for the SEO Engine module.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Admin {

    const MENU_SLUG   = 'rzpa-seo-engine';
    const NONCE_ACTION = 'rzpa_seo_engine';

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    public static function init() : void {

        // Menu + assets
        add_action( 'admin_menu',             [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue' ] );

        // Form handlers
        add_action( 'admin_post_rzpa_seo_save_template',   [ __CLASS__, 'handle_save_template' ] );
        add_action( 'admin_post_rzpa_seo_delete_template', [ __CLASS__, 'handle_delete_template' ] );
        add_action( 'admin_post_rzpa_seo_save_dataset',    [ __CLASS__, 'handle_save_dataset' ] );
        add_action( 'admin_post_rzpa_seo_delete_dataset',  [ __CLASS__, 'handle_delete_dataset' ] );
        add_action( 'admin_post_rzpa_seo_generate_page',   [ __CLASS__, 'handle_generate_page' ] );
        add_action( 'admin_post_rzpa_seo_bulk_generate',   [ __CLASS__, 'handle_bulk_generate' ] );
        add_action( 'admin_post_rzpa_seo_delete_page',     [ __CLASS__, 'handle_delete_page' ] );
        add_action( 'admin_post_rzpa_seo_save_brief',      [ __CLASS__, 'handle_save_brief' ] );
        add_action( 'admin_post_rzpa_seo_delete_brief',    [ __CLASS__, 'handle_delete_brief' ] );
        add_action( 'admin_post_rzpa_seo_generate_blog',   [ __CLASS__, 'handle_generate_blog' ] );
        add_action( 'admin_post_rzpa_seo_save_link_rule',  [ __CLASS__, 'handle_save_link_rule' ] );
        add_action( 'admin_post_rzpa_seo_delete_link_rule',[ __CLASS__, 'handle_delete_link_rule' ] );
        add_action( 'admin_post_rzpa_seo_csv_import',           [ __CLASS__, 'handle_csv_import' ] );
        add_action( 'admin_post_rzpa_seo_csv_export',           [ __CLASS__, 'handle_csv_export' ] );
        add_action( 'admin_post_rzpa_seo_download_csv_template',[ __CLASS__, 'handle_download_csv_template' ] );
        add_action( 'admin_post_rzpa_seo_save_settings',        [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_rzpa_seo_flush_permalinks',     [ __CLASS__, 'handle_flush_permalinks' ] );
        add_action( 'admin_post_rzpa_seo_clear_cache',          [ __CLASS__, 'handle_clear_cache' ] );
        add_action( 'admin_post_rzpa_seo_delete_all_pseo',      [ __CLASS__, 'handle_delete_all_pseo' ] );
        add_action( 'admin_post_rzpa_seo_clear_logs',           [ __CLASS__, 'handle_clear_logs' ] );

        // AJAX handlers
        add_action( 'wp_ajax_rzpa_seo_preview_template',     [ __CLASS__, 'ajax_preview_template' ] );
        add_action( 'wp_ajax_rzpa_seo_get_link_suggestions', [ __CLASS__, 'ajax_link_suggestions' ] );
        add_action( 'wp_ajax_rzpa_seo_generate_page_ajax',   [ __CLASS__, 'ajax_generate_page' ] );
        add_action( 'wp_ajax_rzpa_seo_generate_blog_ajax',   [ __CLASS__, 'ajax_generate_blog' ] );
    }

    // ── Menu registration ─────────────────────────────────────────────────────

    public static function add_menu() : void {
        $cap = 'manage_options';

        add_submenu_page( 'rzpa-dashboard', '', '🔍 SEO Engine', $cap, 'rzpa-section-seo', [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page(
            'rzpa-dashboard',
            'SEO Engine',
            'SEO Engine',
            $cap,
            'rzpa-seo-engine',
            [ __CLASS__, 'page_dashboard' ]
        );
        add_submenu_page( 'rzpa-dashboard', 'pSEO Templates',      'pSEO Templates',      $cap, 'rzpa-seo-templates',       [ __CLASS__, 'page_templates' ] );
        add_submenu_page( 'rzpa-dashboard', 'pSEO Datasæt',        'pSEO Datasæt',        $cap, 'rzpa-seo-datasets',        [ __CLASS__, 'page_datasets' ] );
        add_submenu_page( 'rzpa-dashboard', 'Generér Sider',       'Generér Sider',       $cap, 'rzpa-seo-generate',        [ __CLASS__, 'page_generate' ] );
        add_submenu_page( 'rzpa-dashboard', 'Blog Templates',      'Blog Templates',      $cap, 'rzpa-seo-blog-templates',  [ __CLASS__, 'page_blog_templates' ] );
        add_submenu_page( 'rzpa-dashboard', 'Blog Briefs',         'Blog Briefs',         $cap, 'rzpa-seo-briefs',          [ __CLASS__, 'page_briefs' ] );
        add_submenu_page( 'rzpa-dashboard', 'Generér Blogs',       'Generér Blogs',       $cap, 'rzpa-seo-blog-generate',   [ __CLASS__, 'page_blog_generate' ] );
        add_submenu_page( 'rzpa-dashboard', 'Intern Linking',      'Intern Linking',      $cap, 'rzpa-seo-links',           [ __CLASS__, 'page_linking' ] );
        add_submenu_page( 'rzpa-dashboard', 'CSV Import',          'CSV Import',          $cap, 'rzpa-seo-csv-import',      [ __CLASS__, 'page_csv_import' ] );
        add_submenu_page( 'rzpa-dashboard', 'Logs',                'Logs',                $cap, 'rzpa-seo-logs',            [ __CLASS__, 'page_logs' ] );
        add_submenu_page( 'rzpa-dashboard', 'SEO Indstillinger',   'SEO Indstillinger',   $cap, 'rzpa-seo-settings',        [ __CLASS__, 'page_seo_settings' ] );
    }

    // ── Asset enqueue ─────────────────────────────────────────────────────────

    public static function enqueue( string $hook ) : void {
        if ( strpos( $hook, 'rzpa-seo' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'rzpa-seo-engine',
            RZPA_URL . 'modules/seo-engine/assets/seo-engine-admin.css',
            [],
            RZPA_VERSION
        );

        wp_enqueue_script(
            'rzpa-seo-engine',
            RZPA_URL . 'modules/seo-engine/assets/seo-engine-admin.js',
            [ 'jquery' ],
            RZPA_VERSION,
            true
        );

        wp_localize_script( 'rzpa-seo-engine', 'RZPA_SEO', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'rzpa_seo_engine' ),
            'adminUrl'     => admin_url( 'admin.php' ),
            'placeholders' => RZPA_SEO_Template::PLACEHOLDERS,
            'aiEnabled'    => RZPA_SEO_AI::is_configured(),
        ] );
    }

    // ── Page renderers ────────────────────────────────────────────────────────

    public static function page_dashboard() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/dashboard.php';
    }

    public static function page_templates() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        $action = sanitize_key( $_GET['action'] ?? '' );
        if ( 'edit' === $action || 'new' === $action ) {
            include __DIR__ . '/views/pseo-templates-edit.php';
        } else {
            include __DIR__ . '/views/pseo-templates.php';
        }
    }

    public static function page_datasets() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        $action = sanitize_key( $_GET['action'] ?? '' );
        if ( 'edit' === $action || 'new' === $action ) {
            include __DIR__ . '/views/pseo-datasets-edit.php';
        } else {
            include __DIR__ . '/views/pseo-datasets.php';
        }
    }

    public static function page_generate() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/pseo-generate.php';
    }

    public static function page_blog_templates() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        $action = sanitize_key( $_GET['action'] ?? '' );
        if ( 'edit' === $action || 'new' === $action ) {
            include __DIR__ . '/views/pseo-templates-edit.php';
        } else {
            include __DIR__ . '/views/blog-templates.php';
        }
    }

    public static function page_briefs() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        $action = sanitize_key( $_GET['action'] ?? '' );
        if ( 'edit' === $action || 'new' === $action ) {
            include __DIR__ . '/views/blog-briefs-edit.php';
        } else {
            include __DIR__ . '/views/blog-briefs.php';
        }
    }

    public static function page_blog_generate() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/blog-generate.php';
    }

    public static function page_linking() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/internal-links.php';
    }

    public static function page_csv_import() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/csv-import.php';
    }

    public static function page_logs() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/logs.php';
    }

    public static function page_seo_settings() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Adgang nægtet.', 'rzpa' ) );
        }
        include __DIR__ . '/views/settings.php';
    }

    // ── Form handlers ─────────────────────────────────────────────────────────

    public static function handle_save_template() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id   = absint( $_POST['id'] ?? 0 );
        $data = [
            'name'            => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'slug'            => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
            'description'     => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'type'            => sanitize_text_field( wp_unslash( $_POST['type'] ?? 'pseo' ) ),
            'status'          => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
        ];

        $raw_config = wp_unslash( $_POST['template_config'] ?? '{}' );
        $decoded    = json_decode( $raw_config, true );
        $data['template_config'] = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) )
            ? $decoded
            : [];

        if ( $id > 0 ) {
            $ok = RZPA_SEO_DB::update_template( $id, $data );
            self::redirect_back( 'rzpa-seo-templates', $ok ? [ 'updated' => 1 ] : [ 'error' => 'update_failed' ] );
        } else {
            $new_id = RZPA_SEO_DB::insert_template( $data );
            self::redirect_back( 'rzpa-seo-templates', $new_id ? [ 'updated' => 1 ] : [ 'error' => 'insert_failed' ] );
        }
    }

    public static function handle_delete_template() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id = absint( $_POST['id'] ?? $_GET['id'] ?? 0 );
        RZPA_SEO_DB::delete_template( $id );
        self::redirect_back( 'rzpa-seo-templates', [ 'updated' => 1 ] );
    }

    public static function handle_save_dataset() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id   = absint( $_POST['id'] ?? 0 );
        $post = wp_unslash( $_POST );

        $data = [
            'dataset_group'      => sanitize_text_field( $post['dataset_group']      ?? '' ),
            'template_id'        => absint( $post['template_id']                     ?? 0 ),
            'keyword'            => sanitize_text_field( $post['keyword']             ?? '' ),
            'primary_keyword'    => sanitize_text_field( $post['primary_keyword']     ?? '' ),
            'secondary_keywords' => sanitize_textarea_field( $post['secondary_keywords'] ?? '' ),
            'city'               => sanitize_text_field( $post['city']                ?? '' ),
            'region'             => sanitize_text_field( $post['region']              ?? '' ),
            'area'               => sanitize_text_field( $post['area']                ?? '' ),
            'country'            => sanitize_text_field( $post['country']             ?? 'dk' ),
            'job_type'           => sanitize_text_field( $post['job_type']            ?? '' ),
            'category'           => sanitize_text_field( $post['category']            ?? '' ),
            'employment_type'    => sanitize_text_field( $post['employment_type']     ?? '' ),
            'audience'           => sanitize_text_field( $post['audience']            ?? '' ),
            'search_intent'      => sanitize_text_field( $post['search_intent']       ?? 'informational' ),
            'intro_text'         => sanitize_textarea_field( $post['intro_text']       ?? '' ),
            'cta_text'           => sanitize_textarea_field( $post['cta_text']         ?? '' ),
            'local_proof'        => sanitize_textarea_field( $post['local_proof']      ?? '' ),
            'slug'               => sanitize_title( $post['slug']                     ?? '' ),
            'meta_title'         => sanitize_text_field( $post['meta_title']           ?? '' ),
            'meta_description'   => sanitize_textarea_field( $post['meta_description'] ?? '' ),
            'canonical_url'      => esc_url_raw( $post['canonical_url']               ?? '' ),
            'indexation_status'  => isset( $post['indexation_status'] ) ? 1 : 0,
            'generation_status'  => sanitize_text_field( $post['generation_status']   ?? 'pending' ),
            'quality_status'     => sanitize_text_field( $post['quality_status']       ?? 'unchecked' ),
            'custom_sections'    => sanitize_textarea_field( $post['custom_sections']  ?? '[]' ),
        ];

        // JSON array fields
        foreach ( [ 'faq_items', 'unique_value_points', 'related_links' ] as $f ) {
            $raw = $post[ $f ] ?? '[]';
            $data[ $f ] = is_array( $raw ) ? $raw : ( json_decode( $raw, true ) ?: [] );
        }

        // Manual override flags
        $override_keys = [ 'title', 'intro', 'faq', 'cta', 'meta_title', 'meta_desc' ];
        $flags = [];
        foreach ( $override_keys as $k ) {
            $flags[ $k ] = ! empty( $post['override_' . $k ] );
        }
        $data['manual_override_flags'] = $flags;

        if ( $id > 0 ) {
            $ok = RZPA_SEO_DB::update_dataset( $id, $data );
            self::redirect_back( 'rzpa-seo-datasets', $ok ? [ 'updated' => 1 ] : [ 'error' => 'update_failed' ] );
        } else {
            $new_id = RZPA_SEO_DB::insert_dataset( $data );
            self::redirect_back( 'rzpa-seo-datasets', $new_id ? [ 'updated' => 1 ] : [ 'error' => 'insert_failed' ] );
        }
    }

    public static function handle_delete_dataset() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id = absint( $_POST['id'] ?? $_GET['id'] ?? 0 );
        RZPA_SEO_DB::delete_dataset( $id );
        self::redirect_back( 'rzpa-seo-datasets', [ 'updated' => 1 ] );
    }

    public static function handle_generate_page() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $dataset_id     = absint( $_POST['dataset_id'] ?? 0 );
        $publish_status = sanitize_text_field( $_POST['publish_status'] ?? 'draft' );

        $result = RZPA_SEO_Generator::generate_page( $dataset_id, $publish_status );
        if ( ! empty( $result['success'] ) || ! empty( $result['skipped'] ) ) {
            self::redirect_back( 'rzpa-seo-generate', [ 'updated' => 1 ] );
        } else {
            $err = ! empty( $result['errors'] ) ? implode( ' ', $result['errors'] ) : 'generate_failed';
            self::redirect_back( 'rzpa-seo-generate', [ 'error' => rawurlencode( $err ) ] );
        }
    }

    public static function handle_bulk_generate() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $raw_ids        = array_map( 'absint', (array) ( $_POST['dataset_ids'] ?? [] ) );
        $publish_status = sanitize_text_field( $_POST['publish_status'] ?? 'draft' );

        $result = RZPA_SEO_Generator::bulk_generate( $raw_ids, $publish_status );
        $bulk   = sprintf(
            'generated:%d,updated:%d,failed:%d',
            (int) ( $result['generated'] ?? 0 ),
            (int) ( $result['updated']   ?? 0 ),
            (int) ( $result['failed']    ?? 0 )
        );
        self::redirect_back( 'rzpa-seo-generate', [ 'bulk_result' => $bulk ] );
    }

    public static function handle_delete_page() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $dataset_id = absint( $_POST['dataset_id'] ?? 0 );
        RZPA_SEO_Generator::delete_generated_page( $dataset_id );
        self::redirect_back( 'rzpa-seo-generate', [ 'updated' => 1 ] );
    }

    public static function handle_save_brief() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id   = absint( $_POST['id'] ?? 0 );
        $post = wp_unslash( $_POST );

        $data = [
            'template_id'       => absint( $post['template_id']          ?? 0 ),
            'primary_keyword'   => sanitize_text_field( $post['primary_keyword']  ?? '' ),
            'secondary_keywords'=> sanitize_textarea_field( $post['secondary_keywords'] ?? '' ),
            'intent'            => sanitize_text_field( $post['intent']            ?? 'informational' ),
            'audience'          => sanitize_text_field( $post['audience']          ?? '' ),
            'tone_of_voice'     => sanitize_text_field( $post['tone_of_voice']     ?? 'professional' ),
            'article_type'      => sanitize_text_field( $post['article_type']      ?? 'how-to' ),
            'target_length'     => absint( $post['target_length']                  ?? 1500 ),
            'heading_depth'     => absint( $post['heading_depth']                  ?? 3 ),
            'faq_required'      => isset( $post['faq_required'] ) ? 1 : 0,
            'cta_type'          => sanitize_text_field( $post['cta_type']          ?? '' ),
            'cluster_reference' => sanitize_text_field( $post['cluster_reference'] ?? '' ),
            'slug'              => sanitize_title( $post['slug']                   ?? '' ),
            'meta_title'        => sanitize_text_field( $post['meta_title']         ?? '' ),
            'meta_description'  => sanitize_textarea_field( $post['meta_description'] ?? '' ),
            'excerpt'           => sanitize_textarea_field( $post['excerpt']         ?? '' ),
            'pillar_reference'  => $post['pillar_reference'] ? absint( $post['pillar_reference'] ) : null,
            'status'            => sanitize_text_field( $post['status']             ?? 'draft' ),
        ];

        $raw_links = $post['internal_link_targets'] ?? '[]';
        $data['internal_link_targets'] = is_array( $raw_links ) ? $raw_links : ( json_decode( $raw_links, true ) ?: [] );

        if ( $id > 0 ) {
            $ok = RZPA_SEO_DB::update_brief( $id, $data );
            self::redirect_back( 'rzpa-seo-briefs', $ok ? [ 'updated' => 1 ] : [ 'error' => 'update_failed' ] );
        } else {
            $new_id = RZPA_SEO_DB::insert_brief( $data );
            self::redirect_back( 'rzpa-seo-briefs', $new_id ? [ 'updated' => 1 ] : [ 'error' => 'insert_failed' ] );
        }
    }

    public static function handle_delete_brief() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id = absint( $_POST['id'] ?? $_GET['id'] ?? 0 );
        RZPA_SEO_DB::delete_brief( $id );
        self::redirect_back( 'rzpa-seo-briefs', [ 'updated' => 1 ] );
    }

    public static function handle_generate_blog() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $brief_id = absint( $_POST['brief_id'] ?? 0 );
        $use_ai   = ! empty( $_POST['use_ai'] );

        $result = RZPA_SEO_Blog::generate_blog_post( $brief_id, $use_ai );
        self::redirect_back( 'rzpa-seo-blog-generate', $result ? [ 'updated' => 1 ] : [ 'error' => 'generate_failed' ] );
    }

    public static function handle_save_link_rule() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id   = absint( $_POST['id'] ?? 0 );
        $post = wp_unslash( $_POST );

        $match_logic = [
            'match_type' => sanitize_text_field( $post['match_type'] ?? 'keyword' ),
            'keywords'   => sanitize_text_field( $post['keywords']   ?? '' ),
            'geo_fields' => array_map( 'sanitize_text_field', (array) ( $post['geo_fields'] ?? [] ) ),
        ];

        $data = [
            'rule_name'   => sanitize_text_field( $post['rule_name']   ?? '' ),
            'source_type' => sanitize_text_field( $post['source_type'] ?? 'any' ),
            'target_type' => sanitize_text_field( $post['target_type'] ?? 'any' ),
            'match_logic' => $match_logic,
            'priority'    => absint( $post['priority'] ?? 10 ),
            'is_active'   => isset( $post['is_active'] ) ? 1 : 0,
        ];

        global $wpdb;
        $table = RZPA_SEO_DB::get_table( 'link_rules' );
        $row   = [
            'rule_name'   => $data['rule_name'],
            'source_type' => $data['source_type'],
            'target_type' => $data['target_type'],
            'match_logic' => wp_json_encode( $data['match_logic'] ),
            'priority'    => $data['priority'],
            'is_active'   => $data['is_active'],
        ];

        if ( $id > 0 ) {
            $ok = (bool) $wpdb->update( $table, $row, [ 'id' => $id ] );
        } else {
            $ok = (bool) $wpdb->insert( $table, $row );
        }

        self::redirect_back( 'rzpa-seo-links', $ok ? [ 'updated' => 1 ] : [ 'error' => 'save_failed' ] );
    }

    public static function handle_delete_link_rule() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $id = absint( $_POST['id'] ?? $_GET['id'] ?? 0 );
        global $wpdb;
        $wpdb->delete( RZPA_SEO_DB::get_table( 'link_rules' ), [ 'id' => $id ] );
        self::redirect_back( 'rzpa-seo-links', [ 'updated' => 1 ] );
    }

    public static function handle_csv_import() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $upload = RZPA_SEO_CSV::handle_upload();
        if ( ! empty( $upload['error'] ) ) {
            self::redirect_back( 'rzpa-seo-csv-import', [ 'error' => rawurlencode( $upload['error'] ) ] );
            return;
        }

        $on_duplicate = sanitize_text_field( $_POST['on_duplicate'] ?? 'skip' );

        $parsed = RZPA_SEO_CSV::parse_csv( $upload['path'] );
        if ( ! empty( $parsed['error'] ) ) {
            @unlink( $upload['path'] );
            self::redirect_back( 'rzpa-seo-csv-import', [ 'error' => rawurlencode( $parsed['error'] ) ] );
            return;
        }

        // Auto column_map: identity mapping (CSV header → same field name)
        $column_map = [];
        foreach ( $parsed['headers'] as $h ) {
            $column_map[ $h ] = $h;
        }

        $result = RZPA_SEO_CSV::import_datasets( $parsed['rows'], $column_map, $on_duplicate );
        @unlink( $upload['path'] );

        $import = sprintf(
            'imported:%d,updated:%d,failed:%d',
            (int) ( $result['imported'] ?? 0 ),
            (int) ( $result['updated']  ?? 0 ),
            (int) ( $result['failed']   ?? 0 )
        );
        self::redirect_back( 'rzpa-seo-csv-import', [ 'import_result' => $import ] );
    }

    public static function handle_csv_export() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $filter = [
            'template_id'       => absint( $_POST['export_template_id'] ?? 0 ) ?: null,
            'dataset_group'     => sanitize_text_field( $_POST['export_group'] ?? '' ) ?: null,
            'generation_status' => sanitize_text_field( $_POST['export_status'] ?? '' ) ?: null,
        ];
        $filter = array_filter( $filter );

        RZPA_SEO_CSV::export_datasets( $filter );
        exit;
    }

    public static function handle_download_csv_template() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $headers = implode( ',', RZPA_SEO_CSV::DATASET_COLUMNS );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="rzpa-datasaet-skabelon.csv"' );
        header( 'Pragma: no-cache' );
        echo $headers . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    public static function handle_save_settings() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        // Settings view wraps fields in seo_settings[...] — unwrap if present
        $raw      = wp_unslash( $_POST );
        $post     = ! empty( $raw['seo_settings'] ) && is_array( $raw['seo_settings'] )
                    ? $raw['seo_settings']
                    : $raw;

        $settings = get_option( 'rzpa_seo_settings', [] );

        $old_base = $settings['rewrite_base'] ?? 'job';
        $new_base = sanitize_title( $post['rewrite_base'] ?? 'job' );

        $settings['rewrite_base']                   = $new_base;
        $settings['auto_link_on_generate']          = isset( $post['auto_link_on_generate'] ) ? 1 : 0;
        $settings['default_publish_status']         = sanitize_text_field( $post['default_publish_status'] ?? 'draft' );
        $settings['rzpa_ai_provider']               = sanitize_text_field( $post['ai_provider']            ?? 'none' );
        $settings['quality_min_words']              = absint( $post['quality_min_words']                   ?? 300 );
        $settings['quality_min_h2']                 = absint( $post['quality_min_h2']                      ?? 2 );
        $settings['sitemap_include_pseo']           = isset( $post['sitemap_include_pseo'] ) ? 1 : 0;
        $settings['regenerate_incomplete']          = isset( $post['regenerate_incomplete'] ) ? 1 : 0;
        $settings['quality_require_faq']            = isset( $post['quality_require_faq'] ) ? 1 : 0;
        $settings['quality_require_cta']            = isset( $post['quality_require_cta'] ) ? 1 : 0;
        $settings['sitemap_exclude_noindex']        = isset( $post['sitemap_exclude_noindex'] ) ? 1 : 0;

        // AI API key stored in main settings
        if ( isset( $post['ai_api_key'] ) && '' !== $post['ai_api_key'] ) {
            $settings['rzpa_ai_api_key'] = sanitize_text_field( $post['ai_api_key'] );
        }
        if ( isset( $post['ai_model'] ) ) {
            $settings['rzpa_ai_model'] = sanitize_text_field( $post['ai_model'] );
        }

        update_option( 'rzpa_seo_settings', $settings );

        if ( $old_base !== $new_base ) {
            update_option( 'rzpa_seo_flush_rewrite', 1 );
        }

        self::redirect_back( 'rzpa-seo-settings', [ 'updated' => 1 ] );
    }

    // ── AJAX handlers ─────────────────────────────────────────────────────────

    public static function ajax_preview_template() : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $template_id = absint( $_POST['template_id'] ?? 0 );
        $sample_data = (array) ( $_POST['sample_data'] ?? [] );
        $sample_data = array_map( 'sanitize_text_field', $sample_data );

        $result = RZPA_SEO_Template::preview( $template_id, $sample_data );
        wp_send_json_success( $result );
    }

    public static function ajax_link_suggestions() : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $post_id     = absint( $_POST['post_id'] ?? 0 );
        $suggestions = RZPA_SEO_Linking::find_suggestions( $post_id );
        wp_send_json_success( $suggestions );
    }

    public static function ajax_generate_page() : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $dataset_id = absint( $_POST['dataset_id'] ?? 0 );
        $status     = sanitize_text_field( $_POST['status'] ?? 'draft' );

        $result = RZPA_SEO_Generator::generate_page( $dataset_id, $status );
        if ( ! empty( $result['success'] ) || ! empty( $result['skipped'] ) ) {
            wp_send_json_success( $result );
        } else {
            $err = ! empty( $result['errors'] ) ? implode( ', ', $result['errors'] ) : 'Generering fejlede';
            wp_send_json_error( $err );
        }
    }

    public static function ajax_generate_blog() : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $brief_id = absint( $_POST['brief_id'] ?? 0 );
        $use_ai   = ! empty( $_POST['use_ai'] );

        $result = RZPA_SEO_Blog::generate_blog_post( $brief_id, $use_ai );
        if ( $result ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( 'Blog-generering fejlede' );
        }
    }

    public static function handle_flush_permalinks() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        flush_rewrite_rules();
        self::redirect_back( 'rzpa-seo-settings', [ 'updated' => 'permalinks' ] );
    }

    public static function handle_clear_cache() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        // Clear any generation-related transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rzpa_seo_%' OR option_name LIKE '_transient_timeout_rzpa_seo_%'" );
        if ( function_exists( 'wpfc_clear_all_cache' ) ) wpfc_clear_all_cache( true );
        self::redirect_back( 'rzpa-seo-settings', [ 'updated' => 'cache' ] );
    }

    public static function handle_delete_all_pseo() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        // Require confirmation text
        $confirm = sanitize_text_field( $_POST['confirm_delete'] ?? '' );
        if ( 'SLET ALT' !== $confirm ) {
            self::redirect_back( 'rzpa-seo-settings', [ 'error' => rawurlencode( 'Skriv SLET ALT for at bekræfte.' ) ] );
            return;
        }

        $posts = get_posts( [ 'post_type' => 'rzpa_pseo', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any' ] );
        foreach ( $posts as $id ) {
            wp_delete_post( $id, true );
        }
        // Reset generation_status in datasets table
        global $wpdb;
        $t = RZPA_SEO_DB::get_table( 'datasets' );
        $wpdb->query( "UPDATE {$t} SET generation_status='pending', linked_post_id=NULL, quality_status='unchecked'" ); // phpcs:ignore
        self::redirect_back( 'rzpa-seo-settings', [ 'updated' => 'deleted_pseo', 'count' => count( $posts ) ] );
    }

    public static function handle_clear_logs() : void {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $days = absint( $_POST['older_than_days'] ?? 90 );
        global $wpdb;
        $t = RZPA_SEO_DB::get_table( 'gen_logs' );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) ); // phpcs:ignore
        self::redirect_back( 'rzpa-seo-logs', [ 'updated' => 1 ] );
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    /**
     * Returns admin page URL.
     */
    public static function admin_url( string $page = '', array $args = [] ) : string {
        $url = 'admin.php?page=' . $page;
        if ( $args ) {
            $url .= '&' . http_build_query( $args );
        }
        return admin_url( $url );
    }

    /**
     * Redirects to an admin page and exits.
     */
    public static function redirect_back( string $page, array $args = [] ) : void {
        wp_safe_redirect( self::admin_url( $page, $args ) );
        exit;
    }

    /**
     * Verifies the SEO Engine nonce (dies on failure).
     */
    private static function verify_nonce() : void {
        if ( ! isset( $_REQUEST['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), self::NONCE_ACTION ) ) {
            wp_die( esc_html__( 'Sikkerhedstjek fejlede. Prøv igen.', 'rzpa' ) );
        }
    }
}
