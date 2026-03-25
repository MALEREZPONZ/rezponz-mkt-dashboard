<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Database {

    public static function install() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_data (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date        DATE NOT NULL,
            keyword     VARCHAR(255) NOT NULL,
            position    FLOAT DEFAULT NULL,
            clicks      INT DEFAULT 0,
            impressions INT DEFAULT 0,
            ctr         FLOAT DEFAULT 0,
            fetched_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date (date),
            KEY idx_keyword (keyword(100))
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_seo_pages (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date        DATE NOT NULL,
            page_url    VARCHAR(512) NOT NULL,
            position    FLOAT DEFAULT NULL,
            clicks      INT DEFAULT 0,
            impressions INT DEFAULT 0,
            ctr         FLOAT DEFAULT 0,
            fetched_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date (date),
            KEY idx_url (page_url(100))
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_ai_overview (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date                 DATE NOT NULL,
            keyword              VARCHAR(255),
            has_ai_overview      TINYINT(1) DEFAULT 0,
            has_featured_snippet TINYINT(1) DEFAULT 0,
            has_paa              TINYINT(1) DEFAULT 0,
            ai_overview_text     TEXT,
            source               VARCHAR(50) DEFAULT 'serpapi',
            fetched_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date (date)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_ai_manual_logs (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date              DATE NOT NULL,
            platform          VARCHAR(50) NOT NULL,
            query             TEXT NOT NULL,
            response_text     TEXT,
            rezponz_mentioned TINYINT(1) DEFAULT 0,
            notes             TEXT,
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_meta_campaigns (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id   VARCHAR(100) NOT NULL,
            campaign_name VARCHAR(255),
            status        VARCHAR(50),
            spend         FLOAT DEFAULT 0,
            impressions   BIGINT DEFAULT 0,
            reach         BIGINT DEFAULT 0,
            clicks        INT DEFAULT 0,
            cpm           FLOAT DEFAULT 0,
            cpc           FLOAT DEFAULT 0,
            roas          FLOAT DEFAULT 0,
            date_start    DATE,
            date_stop     DATE,
            fetched_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date_start (date_start)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_snap_campaigns (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id     VARCHAR(100) NOT NULL,
            campaign_name   VARCHAR(255),
            status          VARCHAR(50),
            spend           FLOAT DEFAULT 0,
            impressions     BIGINT DEFAULT 0,
            swipe_ups       INT DEFAULT 0,
            conversions     INT DEFAULT 0,
            cpm             FLOAT DEFAULT 0,
            engagement_rate FLOAT DEFAULT 0,
            date_start      DATE,
            date_stop       DATE,
            fetched_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date_start (date_start)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_tiktok_campaigns (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id   VARCHAR(100) NOT NULL,
            campaign_name VARCHAR(255),
            status        VARCHAR(50),
            spend         FLOAT DEFAULT 0,
            video_views   BIGINT DEFAULT 0,
            clicks        INT DEFAULT 0,
            conversions   INT DEFAULT 0,
            roas          FLOAT DEFAULT 0,
            cost_per_view FLOAT DEFAULT 0,
            date_start    DATE,
            date_stop     DATE,
            fetched_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date_start (date_start)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_sync_log (
            id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source    VARCHAR(100) NOT NULL,
            status    VARCHAR(20) NOT NULL,
            message   TEXT,
            synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source (source)
        ) $c;" );

        update_option( 'rzpa_db_version', RZPA_DB_VER );
    }

    // ── SEO ─────────────────────────────────────────────────────────────────

    public static function insert_seo_rows( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'date'        => sanitize_text_field( $r['date'] ),
                'keyword'     => sanitize_text_field( $r['keyword'] ),
                'position'    => (float) $r['position'],
                'clicks'      => (int)   $r['clicks'],
                'impressions' => (int)   $r['impressions'],
                'ctr'         => (float) $r['ctr'],
            ] );
        }
    }

    public static function get_top_keywords( int $days = 30, int $limit = 20 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT keyword,
                    AVG(position)    AS avg_position,
                    SUM(clicks)      AS total_clicks,
                    SUM(impressions) AS total_impressions,
                    AVG(ctr)         AS avg_ctr
             FROM $t
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY keyword
             ORDER BY total_clicks DESC
             LIMIT %d",
            $days, $limit
        ), ARRAY_A );
    }

    public static function get_seo_summary( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(clicks)      AS total_clicks,
                    SUM(impressions) AS total_impressions,
                    AVG(ctr)         AS avg_ctr,
                    COUNT(DISTINCT keyword) AS keyword_count,
                    COUNT(DISTINCT CASE WHEN position <= 10 THEN keyword END) AS keywords_top10,
                    COUNT(DISTINCT CASE WHEN position <= 3  THEN keyword END) AS keywords_top3
             FROM $t
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    public static function get_keyword_trend( string $keyword, int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT date, AVG(position) AS position, SUM(clicks) AS clicks, SUM(impressions) AS impressions
             FROM $t
             WHERE keyword = %s AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY date ORDER BY date ASC",
            $keyword, $days
        ), ARRAY_A );
    }

    public static function insert_seo_page_rows( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_pages';
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'date'        => sanitize_text_field( $r['date'] ),
                'page_url'    => esc_url_raw( $r['page_url'] ),
                'position'    => (float) $r['position'],
                'clicks'      => (int)   $r['clicks'],
                'impressions' => (int)   $r['impressions'],
                'ctr'         => (float) $r['ctr'],
            ] );
        }
    }

    public static function get_top_pages( int $days = 30, int $limit = 20 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_pages';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url,
                    AVG(position)    AS avg_position,
                    SUM(clicks)      AS total_clicks,
                    SUM(impressions) AS total_impressions,
                    AVG(ctr)         AS avg_ctr
             FROM $t
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY page_url
             ORDER BY total_clicks DESC
             LIMIT %d",
            $days, $limit
        ), ARRAY_A ) ?: [];
    }

    // ── AI Overview ─────────────────────────────────────────────────────────

    public static function insert_ai_overview_rows( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_ai_overview';
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'date'                 => sanitize_text_field( $r['date'] ),
                'keyword'              => sanitize_text_field( $r['keyword'] ),
                'has_ai_overview'      => (int) $r['has_ai_overview'],
                'has_featured_snippet' => (int) $r['has_featured_snippet'],
                'has_paa'              => (int) $r['has_paa'],
                'ai_overview_text'     => sanitize_textarea_field( $r['ai_overview_text'] ?? '' ),
                'source'               => sanitize_text_field( $r['source'] ?? 'serpapi' ),
            ] );
        }
    }

    public static function get_ai_overview( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_ai_overview';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ORDER BY date DESC",
            $days
        ), ARRAY_A );
    }

    public static function get_ai_summary( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_ai_overview';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(has_ai_overview) AS ai_overview_count,
                    SUM(has_featured_snippet) AS featured_snippet_count,
                    SUM(has_paa) AS paa_count,
                    COUNT(*) AS total_checks,
                    COUNT(DISTINCT keyword) AS keywords_tracked
             FROM $t WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    public static function get_ai_manual_logs( int $days = 90 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_ai_manual_logs';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ORDER BY date DESC",
            $days
        ), ARRAY_A );
    }

    public static function insert_ai_manual_log( array $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'rzpa_ai_manual_logs', [
            'date'              => sanitize_text_field( $data['date'] ),
            'platform'          => sanitize_text_field( $data['platform'] ),
            'query'             => sanitize_textarea_field( $data['query'] ),
            'response_text'     => sanitize_textarea_field( $data['response_text'] ?? '' ),
            'rezponz_mentioned' => (int) ( $data['rezponz_mentioned'] ?? 0 ),
            'notes'             => sanitize_textarea_field( $data['notes'] ?? '' ),
        ] );
        return $wpdb->insert_id;
    }

    public static function delete_ai_manual_log( int $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'rzpa_ai_manual_logs', [ 'id' => $id ] );
    }

    // ── Meta ────────────────────────────────────────────────────────────────

    public static function insert_meta_campaigns( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_meta_campaigns';
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'campaign_id'   => sanitize_text_field( $r['campaign_id'] ),
                'campaign_name' => sanitize_text_field( $r['campaign_name'] ),
                'status'        => sanitize_text_field( $r['status'] ),
                'spend'         => (float) $r['spend'],
                'impressions'   => (int)   $r['impressions'],
                'reach'         => (int)   $r['reach'],
                'clicks'        => (int)   $r['clicks'],
                'cpm'           => (float) $r['cpm'],
                'cpc'           => (float) $r['cpc'],
                'roas'          => (float) $r['roas'],
                'date_start'    => sanitize_text_field( $r['date_start'] ),
                'date_stop'     => sanitize_text_field( $r['date_stop'] ),
            ] );
        }
    }

    public static function get_meta_campaigns( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_meta_campaigns';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE date_start >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ORDER BY fetched_at DESC",
            $days
        ), ARRAY_A );
    }

    public static function get_meta_summary( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_meta_campaigns';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(spend) AS total_spend, SUM(impressions) AS total_impressions,
                    SUM(reach) AS total_reach, SUM(clicks) AS total_clicks,
                    AVG(cpm) AS avg_cpm, AVG(cpc) AS avg_cpc,
                    AVG(roas) AS avg_roas, COUNT(*) AS campaign_count
             FROM $t WHERE date_start >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    // ── Snapchat ─────────────────────────────────────────────────────────────

    public static function insert_snap_campaigns( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_snap_campaigns';
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'campaign_id'     => sanitize_text_field( $r['campaign_id'] ),
                'campaign_name'   => sanitize_text_field( $r['campaign_name'] ),
                'status'          => sanitize_text_field( $r['status'] ),
                'spend'           => (float) $r['spend'],
                'impressions'     => (int)   $r['impressions'],
                'swipe_ups'       => (int)   $r['swipe_ups'],
                'conversions'     => (int)   $r['conversions'],
                'cpm'             => (float) $r['cpm'],
                'engagement_rate' => (float) $r['engagement_rate'],
                'date_start'      => sanitize_text_field( $r['date_start'] ),
                'date_stop'       => sanitize_text_field( $r['date_stop'] ),
            ] );
        }
    }

    public static function get_snap_campaigns( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_snap_campaigns';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE date_start >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ORDER BY fetched_at DESC",
            $days
        ), ARRAY_A );
    }

    public static function get_snap_summary( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_snap_campaigns';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(spend) AS total_spend, SUM(impressions) AS total_impressions,
                    SUM(swipe_ups) AS total_swipe_ups, SUM(conversions) AS total_conversions,
                    AVG(cpm) AS avg_cpm, AVG(engagement_rate) AS avg_engagement_rate,
                    COUNT(*) AS campaign_count
             FROM $t WHERE date_start >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    // ── TikTok ───────────────────────────────────────────────────────────────

    public static function insert_tiktok_campaigns( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_tiktok_campaigns';
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'campaign_id'   => sanitize_text_field( $r['campaign_id'] ),
                'campaign_name' => sanitize_text_field( $r['campaign_name'] ),
                'status'        => sanitize_text_field( $r['status'] ),
                'spend'         => (float) $r['spend'],
                'video_views'   => (int)   $r['video_views'],
                'clicks'        => (int)   $r['clicks'],
                'conversions'   => (int)   $r['conversions'],
                'roas'          => (float) $r['roas'],
                'cost_per_view' => (float) $r['cost_per_view'],
                'date_start'    => sanitize_text_field( $r['date_start'] ),
                'date_stop'     => sanitize_text_field( $r['date_stop'] ),
            ] );
        }
    }

    public static function get_tiktok_campaigns( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_tiktok_campaigns';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE date_start >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ORDER BY fetched_at DESC",
            $days
        ), ARRAY_A );
    }

    public static function get_tiktok_summary( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_tiktok_campaigns';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(spend) AS total_spend, SUM(video_views) AS total_video_views,
                    SUM(clicks) AS total_clicks, SUM(conversions) AS total_conversions,
                    AVG(roas) AS avg_roas, AVG(cost_per_view) AS avg_cost_per_view,
                    COUNT(*) AS campaign_count
             FROM $t WHERE date_start >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    // ── Ads daily trends ─────────────────────────────────────────────────────

    public static function get_ads_daily_trends( int $days = 30 ) : array {
        global $wpdb;
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        // Build empty daily buckets
        $buckets = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $d = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $buckets[ $d ] = [ 'meta' => 0.0, 'snap' => 0.0, 'tiktok' => 0.0, 'roas_num' => 0.0, 'roas_w' => 0.0 ];
        }

        $meta   = $wpdb->get_results( $wpdb->prepare(
            "SELECT date_start, date_stop, spend, roas FROM {$wpdb->prefix}rzpa_meta_campaigns WHERE date_start >= %s", $start ), ARRAY_A );
        $snap   = $wpdb->get_results( $wpdb->prepare(
            "SELECT date_start, date_stop, spend FROM {$wpdb->prefix}rzpa_snap_campaigns WHERE date_start >= %s", $start ), ARRAY_A );
        $tiktok = $wpdb->get_results( $wpdb->prepare(
            "SELECT date_start, date_stop, spend, roas FROM {$wpdb->prefix}rzpa_tiktok_campaigns WHERE date_start >= %s", $start ), ARRAY_A );

        $has_real = ( count( $meta ) + count( $snap ) + count( $tiktok ) ) > 0;

        if ( $has_real ) {
            foreach ( $meta as $c ) {
                self::_distribute_spend( $buckets, $c['date_start'], $c['date_stop'], (float) $c['spend'], 'meta', (float) $c['roas'] );
            }
            foreach ( $snap as $c ) {
                self::_distribute_spend( $buckets, $c['date_start'], $c['date_stop'], (float) $c['spend'], 'snap', 0 );
            }
            foreach ( $tiktok as $c ) {
                self::_distribute_spend( $buckets, $c['date_start'], $c['date_stop'], (float) $c['spend'], 'tiktok', (float) $c['roas'] );
            }
        } else {
            // Realistic mock daily data with gentle upward trend
            $base  = [ 'meta' => 3500, 'snap' => 1100, 'tiktok' => 1800 ];
            $i = 0;
            foreach ( $buckets as $d => &$b ) {
                $trend = 1.0 + ( $i / $days ) * 0.15;
                foreach ( [ 'meta', 'snap', 'tiktok' ] as $p ) {
                    $b[ $p ] = round( ( $base[ $p ] / $days ) * $trend * ( 0.75 + wp_rand( 0, 50 ) / 100 ) );
                }
                $b['roas_w']   = $b['meta'] + $b['tiktok'];
                $b['roas_num'] = $b['roas_w'] * ( 1.8 + wp_rand( 0, 30 ) / 20 );
                $i++;
            }
            unset( $b );
        }

        $result = [];
        foreach ( $buckets as $date => $b ) {
            $total = $b['meta'] + $b['snap'] + $b['tiktok'];
            $roas  = $b['roas_w'] > 0 ? round( $b['roas_num'] / $b['roas_w'], 2 ) : 0;
            $result[] = [
                'date'         => $date,
                'meta_spend'   => round( $b['meta'] ),
                'snap_spend'   => round( $b['snap'] ),
                'tiktok_spend' => round( $b['tiktok'] ),
                'total_spend'  => round( $total ),
                'avg_roas'     => $roas,
            ];
        }
        return $result;
    }

    private static function _distribute_spend( array &$buckets, string $start, string $stop, float $spend, string $platform, float $roas ) : void {
        $s    = new DateTime( $start );
        $e    = new DateTime( $stop ?: $start );
        $days = max( 1, $s->diff( $e )->days + 1 );
        $daily_spend = $spend / $days;
        $cur  = clone $s;
        while ( $cur <= $e ) {
            $d = $cur->format( 'Y-m-d' );
            if ( isset( $buckets[ $d ] ) ) {
                $buckets[ $d ][ $platform ] += $daily_spend;
                if ( $roas > 0 ) {
                    $buckets[ $d ]['roas_num'] += $daily_spend * $roas;
                    $buckets[ $d ]['roas_w']   += $daily_spend;
                }
            }
            $cur->modify( '+1 day' );
        }
    }

    // ── Sync log ─────────────────────────────────────────────────────────────

    public static function log_sync( string $source, string $status, string $message = '' ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'rzpa_sync_log', [
            'source'  => sanitize_text_field( $source ),
            'status'  => sanitize_text_field( $status ),
            'message' => sanitize_textarea_field( $message ),
        ] );
    }

    public static function get_last_syncs() : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sync_log';
        return $wpdb->get_results(
            "SELECT source, status, message, MAX(synced_at) AS synced_at FROM $t GROUP BY source",
            ARRAY_A
        ) ?: [];
    }
}
