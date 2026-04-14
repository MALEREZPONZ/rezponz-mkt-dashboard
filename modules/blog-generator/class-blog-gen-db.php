<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPA_Blog_Gen_DB
 *
 * Database-lag for Blog Generator modulet.
 * Tabel: {prefix}rzpa_blog_topics
 */
class RZPA_Blog_Gen_DB {

    const DB_VERSION     = '2';
    const DB_VERSION_KEY = 'rzpa_blog_gen_db_ver';

    /** Foruddefinerede søjler til content-køen */
    const PILLARS = [
        'rekruttering' => 'Rekruttering & job',
        'faellesskab'  => 'Fællesskab & kultur',
        'events'       => 'Events & aktiviteter',
        'jobbet'       => 'Jobbet i praksis',
        'outsourcing'  => 'Outsourcing (B2B)',
        'custom'       => 'Andet',
    ];

    const ARTICLE_TYPES = [
        'explainer' => 'Forklaringsartikel',
        'listicle'  => 'Listicle (top X)',
        'how-to'    => 'Vejledning (how-to)',
    ];

    const TARGETS = [
        'unge' => 'Unge jobsøgende (19-25 år)',
        'b2b'  => 'Virksomheder (B2B)',
    ];

    const STATUSES = [ 'queued', 'generating', 'done', 'failed' ];

    /** Foruddefinerede startmner — 5 rekruttering + 5 B2B */
    const STARTER_TOPICS = [
        [ 'title' => 'Hvad laver en kundeservicemedarbejder egentlig?',             'pillar' => 'jobbet',        'article_type' => 'explainer', 'target' => 'unge' ],
        [ 'title' => 'Studiejob i Aalborg — hvad tjener du realistisk?',            'pillar' => 'rekruttering',  'article_type' => 'explainer', 'target' => 'unge' ],
        [ 'title' => 'Deltidsjob eller fuldtid: hvad passer til dig som ung?',      'pillar' => 'rekruttering',  'article_type' => 'explainer', 'target' => 'unge' ],
        [ 'title' => 'Sådan ser din første uge hos Rezponz ud',                     'pillar' => 'jobbet',        'article_type' => 'explainer', 'target' => 'unge' ],
        [ 'title' => 'Kundeservice som karrierespring — 4 kolleger fortæller',       'pillar' => 'faellesskab',   'article_type' => 'listicle',  'target' => 'unge' ],
        [ 'title' => 'Hvad er outsourcing af kundeservice — og hvornår giver det mening?', 'pillar' => 'outsourcing', 'article_type' => 'explainer', 'target' => 'b2b' ],
        [ 'title' => '5 tegn på at din virksomhed har brug for professionel kundeservice', 'pillar' => 'outsourcing', 'article_type' => 'listicle',  'target' => 'b2b' ],
        [ 'title' => 'Nordjyske virksomheder og kundeservice: derfor outsourcer flere',    'pillar' => 'outsourcing', 'article_type' => 'explainer', 'target' => 'b2b' ],
        [ 'title' => 'Rezponz Challenge 2026 — hvad gik der egentlig for sig?',    'pillar' => 'events',        'article_type' => 'explainer', 'target' => 'unge' ],
        [ 'title' => 'Jobbet bag headsettet: en dag i Aalborg-officet',             'pillar' => 'jobbet',        'article_type' => 'explainer', 'target' => 'unge' ],
    ];

    // ── Install ──────────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $t  = $wpdb->prefix . 'rzpa_blog_topics';
        $c  = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$t} (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title                VARCHAR(500)    NOT NULL,
            keywords             TEXT,
            pillar               VARCHAR(30)     NOT NULL DEFAULT 'custom',
            article_type         VARCHAR(20)     NOT NULL DEFAULT 'explainer',
            target               VARCHAR(10)     NOT NULL DEFAULT 'unge',
            word_count           SMALLINT UNSIGNED NOT NULL DEFAULT 1200,
            status               VARCHAR(20)     NOT NULL DEFAULT 'queued',
            wp_post_id           BIGINT UNSIGNED DEFAULT NULL,
            image_id             BIGINT UNSIGNED DEFAULT NULL,
            include_faq          TINYINT(1)      NOT NULL DEFAULT 1,
            include_toc          TINYINT(1)      NOT NULL DEFAULT 0,
            include_tldr         TINYINT(1)      NOT NULL DEFAULT 0,
            include_internal_links TINYINT(1)    NOT NULL DEFAULT 1,
            publish_immediately  TINYINT(1)      NOT NULL DEFAULT 0,
            post_date            DATETIME        DEFAULT NULL,
            scheduled_for        DATETIME        DEFAULT NULL,
            retry_count          TINYINT         NOT NULL DEFAULT 0,
            error_msg            TEXT            DEFAULT NULL,
            created_at           DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_pillar (pillar),
            KEY idx_scheduled (scheduled_for, status)
        ) {$c};" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        // Seed startmner hvis tabellen er ny og tom
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
        if ( $count === 0 ) {
            foreach ( self::STARTER_TOPICS as $topic ) {
                $wpdb->insert( $t, [
                    'title'        => $topic['title'],
                    'pillar'       => $topic['pillar'],
                    'article_type' => $topic['article_type'],
                    'target'       => $topic['target'],
                    'word_count'   => 1200,
                    'status'       => 'queued',
                    'include_faq'  => 1,
                ] );
            }
        }
    }

    // ── Read ─────────────────────────────────────────────────────────────────

    public static function get_topics( string $status = '' ): array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_blog_topics';

        if ( $status && in_array( $status, self::STATUSES, true ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$t} WHERE status = %s ORDER BY id ASC", $status
            ) ) ?: [];
        }

        return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY status ASC, id ASC" ) ?: [];
    }

    /** Topics planlagt til fremtidig generering (scheduled_for <= now, status = queued) */
    public static function get_due_scheduled( ): array {
        global $wpdb;
        $t   = $wpdb->prefix . 'rzpa_blog_topics';
        $now = current_time( 'mysql' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE status = 'queued' AND scheduled_for IS NOT NULL AND scheduled_for <= %s ORDER BY scheduled_for ASC LIMIT 5",
            $now
        ) ) ?: [];
    }

    /** Hent alle topics med scheduled_for i et givet måneds-interval (til kalender-visning) */
    public static function get_calendar_topics( string $year_month ): array {
        global $wpdb;
        $t     = $wpdb->prefix . 'rzpa_blog_topics';
        $start = $year_month . '-01 00:00:00';
        $end   = date( 'Y-m-t 23:59:59', strtotime( $year_month . '-01' ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, pillar, status, scheduled_for, wp_post_id
             FROM {$t}
             WHERE scheduled_for BETWEEN %s AND %s
             ORDER BY scheduled_for ASC",
            $start, $end
        ) ) ?: [];
    }

    /** Nulstil stuck-generating topics (ældre end 30 min) */
    public static function reset_stuck_generating(): void {
        global $wpdb;
        $t      = $wpdb->prefix . 'rzpa_blog_topics';
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$t} SET status = 'queued', error_msg = 'Auto-reset: stuck i generating tilstand' WHERE status = 'generating' AND updated_at < %s",
            $cutoff
        ) );
    }

    public static function get_topic( int $id ): ?object {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_blog_topics';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) ?: null;
    }

    // ── Write ────────────────────────────────────────────────────────────────

    public static function insert_topic( array $data ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_blog_topics';

        $scheduled_for = null;
        if ( ! empty( $data['scheduled_for'] ) ) {
            $ts = strtotime( $data['scheduled_for'] );
            if ( $ts && $ts > time() ) {
                $scheduled_for = gmdate( 'Y-m-d H:i:s', $ts );
            }
        }
        $post_date = null;
        if ( ! empty( $data['post_date'] ) ) {
            $ts = strtotime( $data['post_date'] );
            if ( $ts ) $post_date = gmdate( 'Y-m-d H:i:s', $ts );
        }

        $row = [
            'title'                => sanitize_text_field( $data['title'] ?? '' ),
            'keywords'             => sanitize_text_field( $data['keywords'] ?? '' ),
            'pillar'               => in_array( $data['pillar'] ?? '', array_keys( self::PILLARS ), true ) ? $data['pillar'] : 'custom',
            'article_type'         => in_array( $data['article_type'] ?? '', array_keys( self::ARTICLE_TYPES ), true ) ? $data['article_type'] : 'explainer',
            'target'               => in_array( $data['target'] ?? '', array_keys( self::TARGETS ), true ) ? $data['target'] : 'unge',
            'word_count'           => max( 600, min( 2500, (int) ( $data['word_count'] ?? 1200 ) ) ),
            'status'               => $scheduled_for ? 'queued' : 'queued',
            'image_id'             => ! empty( $data['image_id'] ) ? (int) $data['image_id'] : null,
            'include_faq'          => ! empty( $data['include_faq'] ) ? 1 : 0,
            'include_toc'          => ! empty( $data['include_toc'] ) ? 1 : 0,
            'include_tldr'         => ! empty( $data['include_tldr'] ) ? 1 : 0,
            'include_internal_links' => isset( $data['include_internal_links'] ) ? ( (int) $data['include_internal_links'] ? 1 : 0 ) : 1,
            'publish_immediately'  => ! empty( $data['publish_immediately'] ) ? 1 : 0,
            'post_date'            => $post_date,
            'scheduled_for'        => $scheduled_for,
        ];

        if ( empty( $row['title'] ) ) return false;

        $wpdb->insert( $t, $row );
        return $wpdb->insert_id ?: false;
    }

    public static function update_status( int $id, string $status, array $extra = [] ): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_blog_topics';

        $data = array_merge( [ 'status' => $status ], $extra );
        return (bool) $wpdb->update( $t, $data, [ 'id' => $id ] );
    }

    public static function delete_topic( int $id ): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_blog_topics';
        return (bool) $wpdb->delete( $t, [ 'id' => $id ] );
    }
}
