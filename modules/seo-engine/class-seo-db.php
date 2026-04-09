<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Database layer.
 *
 * Handles schema installation (dbDelta) and all CRUD operations
 * for the 5 SEO Engine custom tables.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_DB {

    const DB_VERSION_KEY = 'rzpa_seo_engine_db_version';
    const DB_VERSION     = '1';

    // ── Table name helpers ────────────────────────────────────────────────────

    /**
     * Returns fully-qualified table name.
     *
     * @param string $name  Short name: templates|datasets|blog_briefs|gen_logs|link_rules
     * @return string
     */
    public static function get_table( string $name ) : string {
        global $wpdb;
        return $wpdb->prefix . 'rzpa_seo_' . $name;
    }

    // ── Schema installation ───────────────────────────────────────────────────

    /**
     * Creates / upgrades all 5 SEO Engine tables via dbDelta.
     *
     * @return void
     */
    public static function install() : void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Templates
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_templates (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type            VARCHAR(20)  NOT NULL DEFAULT 'pseo',
            name            VARCHAR(255) NOT NULL,
            slug            VARCHAR(255) NOT NULL,
            description     TEXT,
            template_config LONGTEXT     NOT NULL DEFAULT '{}',
            status          VARCHAR(20)  NOT NULL DEFAULT 'active',
            version         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            UNIQUE KEY      uq_slug (slug),
            KEY             idx_type (type),
            KEY             idx_status (status)
        ) $c;" );

        // 2. Datasets
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_datasets (
            id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            dataset_group         VARCHAR(100) NOT NULL DEFAULT '',
            template_id           BIGINT UNSIGNED NOT NULL DEFAULT 0,
            city                  VARCHAR(100) NOT NULL DEFAULT '',
            region                VARCHAR(100) NOT NULL DEFAULT '',
            area                  VARCHAR(100) NOT NULL DEFAULT '',
            country               VARCHAR(10)  NOT NULL DEFAULT 'dk',
            keyword               VARCHAR(255) NOT NULL,
            primary_keyword       VARCHAR(255) NOT NULL,
            secondary_keywords    TEXT,
            job_type              VARCHAR(100) NOT NULL DEFAULT '',
            category              VARCHAR(100) NOT NULL DEFAULT '',
            employment_type       VARCHAR(50)  NOT NULL DEFAULT '',
            audience              VARCHAR(100) NOT NULL DEFAULT '',
            search_intent         VARCHAR(50)  NOT NULL DEFAULT 'informational',
            intro_text            TEXT,
            unique_value_points   LONGTEXT     NOT NULL DEFAULT '[]',
            faq_items             LONGTEXT     NOT NULL DEFAULT '[]',
            cta_text              TEXT,
            custom_sections       LONGTEXT     NOT NULL DEFAULT '[]',
            related_links         LONGTEXT     NOT NULL DEFAULT '[]',
            local_proof           TEXT,
            slug                  VARCHAR(255) NOT NULL DEFAULT '',
            meta_title            VARCHAR(255) NOT NULL DEFAULT '',
            meta_description      VARCHAR(500) NOT NULL DEFAULT '',
            canonical_url         VARCHAR(500) NOT NULL DEFAULT '',
            indexation_status     TINYINT(1)   NOT NULL DEFAULT 1,
            generation_status     VARCHAR(20)  NOT NULL DEFAULT 'pending',
            quality_status        VARCHAR(20)  NOT NULL DEFAULT 'unchecked',
            manual_override_flags LONGTEXT     NOT NULL DEFAULT '{}',
            linked_post_id        BIGINT UNSIGNED DEFAULT NULL,
            created_at            DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY           (id),
            KEY                   idx_template_id (template_id),
            KEY                   idx_dataset_group (dataset_group(50)),
            KEY                   idx_generation_status (generation_status),
            KEY                   idx_linked_post_id (linked_post_id),
            KEY                   idx_keyword (keyword(100))
        ) $c;" );

        // 3. Blog Briefs
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_blog_briefs (
            id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id           BIGINT UNSIGNED NOT NULL DEFAULT 0,
            primary_keyword       VARCHAR(255) NOT NULL,
            secondary_keywords    TEXT,
            intent                VARCHAR(50)  NOT NULL DEFAULT 'informational',
            audience              VARCHAR(100) NOT NULL DEFAULT '',
            tone_of_voice         VARCHAR(50)  NOT NULL DEFAULT 'professional',
            article_type          VARCHAR(50)  NOT NULL DEFAULT 'how-to',
            target_length         INT UNSIGNED NOT NULL DEFAULT 1500,
            heading_depth         TINYINT      NOT NULL DEFAULT 3,
            faq_required          TINYINT(1)   NOT NULL DEFAULT 0,
            cta_type              VARCHAR(50)  NOT NULL DEFAULT '',
            internal_link_targets LONGTEXT     NOT NULL DEFAULT '[]',
            pillar_reference      BIGINT UNSIGNED DEFAULT NULL,
            cluster_reference     VARCHAR(100) NOT NULL DEFAULT '',
            slug                  VARCHAR(255) NOT NULL DEFAULT '',
            meta_title            VARCHAR(255) NOT NULL DEFAULT '',
            meta_description      VARCHAR(500) NOT NULL DEFAULT '',
            excerpt               TEXT,
            status                VARCHAR(20)  NOT NULL DEFAULT 'draft',
            linked_post_id        BIGINT UNSIGNED DEFAULT NULL,
            created_at            DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY           (id),
            KEY                   idx_template_id (template_id),
            KEY                   idx_status (status),
            KEY                   idx_linked_post_id (linked_post_id),
            KEY                   idx_primary_keyword (primary_keyword(100))
        ) $c;" );

        // 4. Generation Logs
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_gen_logs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_type VARCHAR(20)  NOT NULL,
            object_id    BIGINT UNSIGNED DEFAULT NULL,
            action_type  VARCHAR(50)  NOT NULL,
            message      TEXT         NOT NULL,
            severity     VARCHAR(10)  NOT NULL DEFAULT 'info',
            context      LONGTEXT     NOT NULL DEFAULT '{}',
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            user_id      BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY          idx_content_type (content_type),
            KEY          idx_object_id (object_id),
            KEY          idx_severity (severity),
            KEY          idx_created_at (created_at)
        ) $c;" );

        // 5. Link Rules
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_link_rules (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_name   VARCHAR(255) NOT NULL,
            source_type VARCHAR(20)  NOT NULL,
            target_type VARCHAR(20)  NOT NULL,
            match_logic LONGTEXT     NOT NULL DEFAULT '{}',
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            priority    SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY         idx_is_active (is_active),
            KEY         idx_priority (priority),
            KEY         idx_source_type (source_type)
        ) $c;" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    // =========================================================================
    // TABLE 1 – seo_templates CRUD
    // =========================================================================

    /**
     * Returns all templates, optionally filtered by type and/or status.
     *
     * @param string|null $type   pseo|blog or null for all.
     * @param string|null $status active|inactive|draft or null for all.
     * @return array<int, array<string, mixed>>
     */
    public static function get_templates( ?string $type = null, ?string $status = null ) : array {
        global $wpdb;
        $t     = self::get_table( 'templates' );
        $where = [];
        $args  = [];

        if ( null !== $type ) {
            $where[] = 'type = %s';
            $args[]  = $type;
        }
        if ( null !== $status ) {
            $where[] = 'status = %s';
            $args[]  = $status;
        }

        $sql = "SELECT * FROM {$t}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY name ASC';

        if ( $args ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A ) ?: [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
    }

    /**
     * Returns a single template by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public static function get_template( int $id ) : ?array {
        global $wpdb;
        $t = self::get_table( 'templates' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ) ?: null;
    }

    /**
     * Returns a single template by slug.
     *
     * @param string $slug
     * @return array<string, mixed>|null
     */
    public static function get_template_by_slug( string $slug ) : ?array {
        global $wpdb;
        $t = self::get_table( 'templates' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", $slug ), ARRAY_A ) ?: null;
    }

    /**
     * Inserts a new template.
     *
     * @param array<string, mixed> $data
     * @return int|false  New row ID on success, false on failure.
     */
    public static function insert_template( array $data ) : int|false {
        global $wpdb;
        $row = [
            'type'            => sanitize_text_field( $data['type']            ?? 'pseo' ),
            'name'            => sanitize_text_field( $data['name']            ?? '' ),
            'slug'            => sanitize_title(      $data['slug']            ?? '' ),
            'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
            'template_config' => wp_json_encode( $data['template_config'] ?? [] ) ?: '{}',
            'status'          => sanitize_text_field( $data['status']          ?? 'active' ),
            'version'         => absint( $data['version'] ?? 1 ),
        ];

        if ( empty( $row['name'] ) ) {
            return false;
        }
        if ( empty( $row['slug'] ) ) {
            $row['slug'] = sanitize_title( $row['name'] );
        }

        $ok = $wpdb->insert( self::get_table( 'templates' ), $row );
        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Updates an existing template.
     *
     * @param int                  $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function update_template( int $id, array $data ) : bool {
        global $wpdb;
        $allowed = [ 'type', 'name', 'slug', 'description', 'template_config', 'status', 'version' ];
        $update  = [];

        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $data ) ) {
                continue;
            }
            $update[ $key ] = match ( $key ) {
                'template_config' => is_array( $data[ $key ] ) ? ( wp_json_encode( $data[ $key ] ) ?: '{}' ) : $data[ $key ],
                'version'         => absint( $data[ $key ] ),
                'slug'            => sanitize_title( $data[ $key ] ),
                default           => sanitize_text_field( $data[ $key ] ),
            };
        }

        if ( empty( $update ) ) {
            return false;
        }
        return (bool) $wpdb->update( self::get_table( 'templates' ), $update, [ 'id' => $id ] );
    }

    /**
     * Deletes a template by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_template( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::get_table( 'templates' ), [ 'id' => $id ] );
    }

    // =========================================================================
    // TABLE 2 – seo_datasets CRUD
    // =========================================================================

    /**
     * Returns paginated datasets with optional filters.
     *
     * @param int|null    $template_id  Filter by template.
     * @param string|null $status       Filter by generation_status.
     * @param string|null $group        Filter by dataset_group.
     * @param int         $per_page     Rows per page (default 50).
     * @param int         $offset       SQL offset.
     * @param int         $total        Passed by reference – total matching rows.
     * @return array<int, array<string, mixed>>
     */
    public static function get_datasets(
        ?int $template_id = null,
        ?string $status = null,
        ?string $group = null,
        int $per_page = 50,
        int $offset = 0,
        int &$total = 0
    ) : array {
        global $wpdb;
        $t     = self::get_table( 'datasets' );
        $where = [];
        $args  = [];

        if ( null !== $template_id ) {
            $where[] = 'template_id = %d';
            $args[]  = $template_id;
        }
        if ( null !== $status ) {
            $where[] = 'generation_status = %s';
            $args[]  = $status;
        }
        if ( null !== $group ) {
            $where[] = 'dataset_group = %s';
            $args[]  = $group;
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $count_sql = "SELECT COUNT(*) FROM {$t} {$where_sql}";
        $data_sql  = "SELECT * FROM {$t} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

        if ( $args ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
        }

        $total = (int) $wpdb->get_var( $count_sql );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ), ARRAY_A ) ?: [];
    }

    /**
     * Returns a single dataset by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public static function get_dataset( int $id ) : ?array {
        global $wpdb;
        $t = self::get_table( 'datasets' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ) ?: null;
    }

    /**
     * Inserts a new dataset row.
     *
     * @param array<string, mixed> $data
     * @return int|false
     */
    public static function insert_dataset( array $data ) : int|false {
        global $wpdb;

        $row = self::sanitize_dataset( $data );
        if ( empty( $row['keyword'] ) || empty( $row['primary_keyword'] ) ) {
            return false;
        }

        $ok = $wpdb->insert( self::get_table( 'datasets' ), $row );
        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Updates an existing dataset.
     *
     * @param int                  $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function update_dataset( int $id, array $data ) : bool {
        global $wpdb;
        $update = self::sanitize_dataset( $data );
        if ( empty( $update ) ) {
            return false;
        }
        return (bool) $wpdb->update( self::get_table( 'datasets' ), $update, [ 'id' => $id ] );
    }

    /**
     * Deletes a dataset by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_dataset( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::get_table( 'datasets' ), [ 'id' => $id ] );
    }

    /**
     * Updates generation_status for a single dataset.
     *
     * @param int    $id
     * @param string $status  pending|draft|review|approved|published|failed
     * @return bool
     */
    public static function update_dataset_status( int $id, string $status ) : bool {
        global $wpdb;
        $valid = [ 'pending', 'draft', 'review', 'approved', 'published', 'failed' ];
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }
        return (bool) $wpdb->update(
            self::get_table( 'datasets' ),
            [ 'generation_status' => $status ],
            [ 'id' => $id ]
        );
    }

    /**
     * Bulk-updates generation_status for multiple datasets.
     *
     * @param int[]  $ids
     * @param string $status
     * @return int  Number of rows updated.
     */
    public static function bulk_update_status( array $ids, string $status ) : int {
        global $wpdb;
        $valid = [ 'pending', 'draft', 'review', 'approved', 'published', 'failed' ];
        if ( empty( $ids ) || ! in_array( $status, $valid, true ) ) {
            return 0;
        }

        $ids       = array_map( 'absint', $ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $t         = self::get_table( 'datasets' );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$t} SET generation_status = %s WHERE id IN ({$placeholders})",
                array_merge( [ $status ], $ids )
            )
        );
    }

    /**
     * Returns all datasets belonging to a dataset_group.
     *
     * @param string $group
     * @return array<int, array<string, mixed>>
     */
    public static function get_datasets_by_group( string $group ) : array {
        global $wpdb;
        $t = self::get_table( 'datasets' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE dataset_group = %s ORDER BY id ASC", $group ), ARRAY_A ) ?: [];
    }

    /**
     * Returns count of datasets grouped by generation_status.
     *
     * @return array<string, int>  Keys are status values.
     */
    public static function count_by_status() : array {
        global $wpdb;
        $t    = self::get_table( 'datasets' );
        $rows = $wpdb->get_results( "SELECT generation_status, COUNT(*) AS cnt FROM {$t} GROUP BY generation_status", ARRAY_A ) ?: [];
        $out  = [];
        foreach ( $rows as $row ) {
            $out[ $row['generation_status'] ] = (int) $row['cnt'];
        }
        return $out;
    }

    /**
     * Sanitizes dataset fields before DB write.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitize_dataset( array $data ) : array {
        $json_fields  = [ 'unique_value_points', 'faq_items', 'custom_sections', 'related_links', 'manual_override_flags' ];
        $text_fields  = [ 'dataset_group', 'city', 'region', 'area', 'country', 'keyword', 'primary_keyword',
                          'job_type', 'category', 'employment_type', 'audience', 'search_intent',
                          'slug', 'meta_title', 'generation_status', 'quality_status' ];
        $textarea_fields = [ 'secondary_keywords', 'intro_text', 'cta_text', 'local_proof', 'meta_description', 'canonical_url' ];

        $row = [];

        foreach ( $text_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = sanitize_text_field( $data[ $f ] );
            }
        }
        foreach ( $textarea_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = sanitize_textarea_field( $data[ $f ] );
            }
        }
        foreach ( $json_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = is_array( $data[ $f ] ) ? ( wp_json_encode( $data[ $f ] ) ?: '[]' ) : $data[ $f ];
            }
        }

        if ( array_key_exists( 'template_id', $data ) ) {
            $row['template_id'] = absint( $data['template_id'] );
        }
        if ( array_key_exists( 'indexation_status', $data ) ) {
            $row['indexation_status'] = (int) (bool) $data['indexation_status'];
        }
        if ( array_key_exists( 'linked_post_id', $data ) ) {
            $row['linked_post_id'] = $data['linked_post_id'] ? absint( $data['linked_post_id'] ) : null;
        }

        return $row;
    }

    // =========================================================================
    // TABLE 3 – seo_blog_briefs CRUD
    // =========================================================================

    /**
     * Returns paginated blog briefs.
     *
     * @param string|null $status    draft|review|approved|generated|published or null.
     * @param int         $per_page
     * @param int         $offset
     * @param int         $total     By reference.
     * @return array<int, array<string, mixed>>
     */
    public static function get_briefs( ?string $status = null, int $per_page = 50, int $offset = 0, int &$total = 0 ) : array {
        global $wpdb;
        $t = self::get_table( 'blog_briefs' );

        if ( null !== $status ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = %s", $status ) );
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $per_page, $offset ), ARRAY_A ) ?: [];
        }

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ) ?: [];
    }

    /**
     * Returns a single brief by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public static function get_brief( int $id ) : ?array {
        global $wpdb;
        $t = self::get_table( 'blog_briefs' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ) ?: null;
    }

    /**
     * Inserts a new blog brief.
     *
     * @param array<string, mixed> $data
     * @return int|false
     */
    public static function insert_brief( array $data ) : int|false {
        global $wpdb;
        $row = self::sanitize_brief( $data );
        if ( empty( $row['primary_keyword'] ) ) {
            return false;
        }
        $ok = $wpdb->insert( self::get_table( 'blog_briefs' ), $row );
        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Updates an existing blog brief.
     *
     * @param int                  $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function update_brief( int $id, array $data ) : bool {
        global $wpdb;
        $update = self::sanitize_brief( $data );
        if ( empty( $update ) ) {
            return false;
        }
        return (bool) $wpdb->update( self::get_table( 'blog_briefs' ), $update, [ 'id' => $id ] );
    }

    /**
     * Deletes a brief by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_brief( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::get_table( 'blog_briefs' ), [ 'id' => $id ] );
    }

    /**
     * Updates status for a single brief.
     *
     * @param int    $id
     * @param string $status  draft|review|approved|generated|published
     * @return bool
     */
    public static function update_brief_status( int $id, string $status ) : bool {
        global $wpdb;
        $valid = [ 'draft', 'review', 'approved', 'generated', 'published' ];
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }
        return (bool) $wpdb->update( self::get_table( 'blog_briefs' ), [ 'status' => $status ], [ 'id' => $id ] );
    }

    /**
     * Returns count of briefs grouped by status.
     *
     * @return array<string, int>
     */
    public static function count_briefs_by_status() : array {
        global $wpdb;
        $t    = self::get_table( 'blog_briefs' );
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$t} GROUP BY status", ARRAY_A ) ?: [];
        $out  = [];
        foreach ( $rows as $row ) {
            $out[ $row['status'] ] = (int) $row['cnt'];
        }
        return $out;
    }

    /**
     * Sanitizes brief fields before DB write.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitize_brief( array $data ) : array {
        $text_fields     = [ 'primary_keyword', 'intent', 'audience', 'tone_of_voice', 'article_type', 'cta_type', 'cluster_reference', 'slug', 'meta_title', 'status' ];
        $textarea_fields = [ 'secondary_keywords', 'meta_description', 'excerpt' ];

        $row = [];

        foreach ( $text_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = sanitize_text_field( $data[ $f ] );
            }
        }
        foreach ( $textarea_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = sanitize_textarea_field( $data[ $f ] );
            }
        }

        if ( array_key_exists( 'internal_link_targets', $data ) ) {
            $row['internal_link_targets'] = is_array( $data['internal_link_targets'] ) ? ( wp_json_encode( $data['internal_link_targets'] ) ?: '[]' ) : $data['internal_link_targets'];
        }

        $int_fields = [ 'template_id', 'target_length', 'heading_depth', 'faq_required' ];
        foreach ( $int_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = absint( $data[ $f ] );
            }
        }

        if ( array_key_exists( 'pillar_reference', $data ) ) {
            $row['pillar_reference'] = $data['pillar_reference'] ? absint( $data['pillar_reference'] ) : null;
        }
        if ( array_key_exists( 'linked_post_id', $data ) ) {
            $row['linked_post_id'] = $data['linked_post_id'] ? absint( $data['linked_post_id'] ) : null;
        }

        return $row;
    }

    // =========================================================================
    // TABLE 4 – seo_gen_logs
    // =========================================================================

    /**
     * Inserts a generation log entry.
     *
     * @param string  $content_type  pseo|blog|linking|import
     * @param int|null $object_id
     * @param string  $action_type
     * @param string  $message
     * @param string  $severity      info|success|warning|error
     * @param array<string, mixed> $context  Extra JSON context.
     * @return int  New log ID (0 on failure).
     */
    public static function log(
        string $content_type,
        ?int $object_id,
        string $action_type,
        string $message,
        string $severity = 'info',
        array $context = []
    ) : int {
        global $wpdb;

        $severity_valid = [ 'info', 'success', 'warning', 'error' ];
        if ( ! in_array( $severity, $severity_valid, true ) ) {
            $severity = 'info';
        }

        $ok = $wpdb->insert( self::get_table( 'gen_logs' ), [
            'content_type' => sanitize_text_field( $content_type ),
            'object_id'    => $object_id,
            'action_type'  => sanitize_text_field( $action_type ),
            'message'      => sanitize_textarea_field( $message ),
            'severity'     => $severity,
            'context'      => wp_json_encode( $context ) ?: '{}',
            'user_id'      => get_current_user_id() ?: null,
        ] );

        return $ok ? $wpdb->insert_id : 0;
    }

    /**
     * Returns paginated log entries.
     *
     * @param string|null $content_type
     * @param string|null $severity
     * @param int         $per_page
     * @param int         $offset
     * @param int         $total  By reference.
     * @return array<int, array<string, mixed>>
     */
    public static function get_logs(
        ?string $content_type = null,
        ?string $severity = null,
        int $per_page = 50,
        int $offset = 0,
        int &$total = 0
    ) : array {
        global $wpdb;
        $t     = self::get_table( 'gen_logs' );
        $where = [];
        $args  = [];

        if ( null !== $content_type ) {
            $where[] = 'content_type = %s';
            $args[]  = $content_type;
        }
        if ( null !== $severity ) {
            $where[] = 'severity = %s';
            $args[]  = $severity;
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $count_sql = "SELECT COUNT(*) FROM {$t} {$where_sql}";
        $data_sql  = "SELECT * FROM {$t} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

        if ( $args ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
        }

        $total = (int) $wpdb->get_var( $count_sql );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ), ARRAY_A ) ?: [];
    }

    /**
     * Returns all logs for a specific object (e.g. a dataset).
     *
     * @param int    $object_id
     * @param string $content_type
     * @return array<int, array<string, mixed>>
     */
    public static function get_logs_for_object( int $object_id, string $content_type ) : array {
        global $wpdb;
        $t = self::get_table( 'gen_logs' );
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$t} WHERE object_id = %d AND content_type = %s ORDER BY id DESC", $object_id, $content_type ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Purges log entries older than $days days.
     *
     * @param int $days  Default 90.
     * @return int  Number of rows deleted.
     */
    public static function clear_old_logs( int $days = 90 ) : int {
        global $wpdb;
        $t = self::get_table( 'gen_logs' );
        return (int) $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$t} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days )
        );
    }

    // =========================================================================
    // TABLE 5 – seo_link_rules CRUD
    // =========================================================================

    /**
     * Returns link rules, optionally filtering to active-only.
     *
     * @param bool $active_only
     * @return array<int, array<string, mixed>>
     */
    public static function get_rules( bool $active_only = true ) : array {
        global $wpdb;
        $t = self::get_table( 'link_rules' );
        if ( $active_only ) {
            return $wpdb->get_results( "SELECT * FROM {$t} WHERE is_active = 1 ORDER BY priority ASC, id ASC", ARRAY_A ) ?: [];
        }
        return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY priority ASC, id ASC", ARRAY_A ) ?: [];
    }

    /**
     * Returns a single link rule by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public static function get_rule( int $id ) : ?array {
        global $wpdb;
        $t = self::get_table( 'link_rules' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ) ?: null;
    }

    /**
     * Inserts a new link rule.
     *
     * @param array<string, mixed> $data
     * @return int|false
     */
    public static function insert_rule( array $data ) : int|false {
        global $wpdb;
        $row = [
            'rule_name'   => sanitize_text_field( $data['rule_name']   ?? '' ),
            'source_type' => sanitize_text_field( $data['source_type'] ?? 'any' ),
            'target_type' => sanitize_text_field( $data['target_type'] ?? 'any' ),
            'match_logic' => is_array( $data['match_logic'] ?? null ) ? ( wp_json_encode( $data['match_logic'] ) ?: '{}' ) : ( $data['match_logic'] ?? '{}' ),
            'is_active'   => (int) (bool) ( $data['is_active'] ?? 1 ),
            'priority'    => absint( $data['priority'] ?? 10 ),
        ];

        if ( empty( $row['rule_name'] ) ) {
            return false;
        }

        $ok = $wpdb->insert( self::get_table( 'link_rules' ), $row );
        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Updates an existing link rule.
     *
     * @param int                  $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function update_rule( int $id, array $data ) : bool {
        global $wpdb;
        $allowed = [ 'rule_name', 'source_type', 'target_type', 'match_logic', 'is_active', 'priority' ];
        $update  = [];

        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $data ) ) {
                continue;
            }
            $update[ $key ] = match ( $key ) {
                'match_logic' => is_array( $data[ $key ] ) ? ( wp_json_encode( $data[ $key ] ) ?: '{}' ) : $data[ $key ],
                'is_active'   => (int) (bool) $data[ $key ],
                'priority'    => absint( $data[ $key ] ),
                default       => sanitize_text_field( $data[ $key ] ),
            };
        }

        if ( empty( $update ) ) {
            return false;
        }
        return (bool) $wpdb->update( self::get_table( 'link_rules' ), $update, [ 'id' => $id ] );
    }

    /**
     * Deletes a link rule by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_rule( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::get_table( 'link_rules' ), [ 'id' => $id ] );
    }

    /**
     * Toggles is_active on a link rule.
     *
     * @param int $id
     * @return bool  New is_active value.
     */
    public static function toggle_rule( int $id ) : bool {
        global $wpdb;
        $t       = self::get_table( 'link_rules' );
        $current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$t} WHERE id = %d", $id ) );
        $new     = $current ? 0 : 1;
        $wpdb->update( $t, [ 'is_active' => $new ], [ 'id' => $id ] );
        return (bool) $new;
    }
}
