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
            period_days   TINYINT UNSIGNED DEFAULT 30,
            fetched_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_period (period_days)
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

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rzpa_google_ads_campaigns (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id   VARCHAR(100) NOT NULL,
            campaign_name VARCHAR(255),
            status        VARCHAR(50),
            spend         FLOAT DEFAULT 0,
            impressions   BIGINT DEFAULT 0,
            clicks        INT DEFAULT 0,
            conversions   FLOAT DEFAULT 0,
            cpm           FLOAT DEFAULT 0,
            cpc           FLOAT DEFAULT 0,
            ctr           FLOAT DEFAULT 0,
            date_start    DATE,
            date_stop     DATE,
            period_days   TINYINT UNSIGNED DEFAULT 30,
            fetched_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_period (period_days)
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

        // ── Tilpassede sitemaps ────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_sitemaps (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100) NOT NULL,
            slug        VARCHAR(100) NOT NULL,
            description TEXT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpa_sitemap_urls (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sitemap_id  BIGINT UNSIGNED NOT NULL,
            url         TEXT NOT NULL,
            priority    DECIMAL(2,1) DEFAULT 0.5,
            changefreq  VARCHAR(20) DEFAULT 'weekly',
            lastmod     DATE NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sitemap (sitemap_id)
        ) $c;" );

        update_option( 'rzpa_db_version', RZPA_DB_VER );
    }

    // ── SEO ─────────────────────────────────────────────────────────────────

    public static function insert_seo_rows( array $rows ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        // Ryd al gammel data (inkl. mock-data) — GSC giver altid det fulde billede
        $wpdb->query( "TRUNCATE TABLE `{$t}`" ); // phpcs:ignore
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
        $wpdb->query( "TRUNCATE TABLE `{$t}`" ); // phpcs:ignore
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

    /**
     * Henter alle publicerede blogindlæg og beriger dem med GSC-data + AI-synlighed.
     */
    public static function get_blog_insights( int $days = 30 ) : array {
        global $wpdb;

        // Hent kun blogindlæg (post_type = 'post')
        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( empty( $posts ) ) return [];

        // GSC sidedata
        $pages_t    = $wpdb->prefix . 'rzpa_seo_pages';
        $pages_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url,
                    AVG(position)    AS avg_position,
                    SUM(clicks)      AS total_clicks,
                    SUM(impressions) AS total_impressions,
                    AVG(ctr)         AS avg_ctr
             FROM {$pages_t}
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY page_url",
            $days
        ), ARRAY_A ) ?: [];

        $pages_map = [];
        foreach ( $pages_rows as $p ) {
            $norm = self::normalize_url_for_match( $p['page_url'] );
            $pages_map[ $norm ] = $p;
        }

        // AI søgeords-synlighed (seneste pr. keyword)
        $ai_t    = $wpdb->prefix . 'rzpa_ai_overview';
        $ai_rows = $wpdb->get_results(
            "SELECT k.keyword, k.has_ai_overview, k.has_featured_snippet, k.has_paa
             FROM {$ai_t} k
             INNER JOIN (SELECT MAX(id) AS max_id FROM {$ai_t} GROUP BY keyword) latest
                ON k.id = latest.max_id",
            ARRAY_A
        ) ?: [];

        // Byg resultat
        $result = [];
        foreach ( $posts as $post ) {
            $url      = get_permalink( $post );
            $norm_url = self::normalize_url_for_match( $url );
            $gsc      = $pages_map[ $norm_url ] ?? null;

            $position    = $gsc ? round( (float) $gsc['avg_position'], 1 ) : null;
            $clicks      = $gsc ? (int) $gsc['total_clicks'] : 0;
            $impressions = $gsc ? (int) $gsc['total_impressions'] : 0;
            $ctr         = $gsc ? round( (float) $gsc['avg_ctr'] * 100, 2 ) : 0.0;

            // Simpel AI-match: tjek om et sporet keyword matcher bloggens slug/titel
            $ai_visible = false;
            $ai_keyword = '';
            $title_lower = mb_strtolower( $post->post_title );
            $slug        = $post->post_name;
            foreach ( $ai_rows as $ak ) {
                if ( ! $ak['has_ai_overview'] ) continue;
                $kw = mb_strtolower( $ak['keyword'] );
                if (
                    mb_strpos( $title_lower, $kw ) !== false ||
                    mb_strpos( $slug, str_replace( ' ', '-', $kw ) ) !== false
                ) {
                    $ai_visible = true;
                    $ai_keyword = $ak['keyword'];
                    break;
                }
            }

            // Decode AI-fix historik (JSON keyed by fix_type → timestamp)
            $fixed_raw  = get_post_meta( $post->ID, '_rzpa_ai_fixed', true );
            $fixed_data = ( $fixed_raw && str_starts_with( trim( $fixed_raw ), '{' ) )
                ? ( json_decode( $fixed_raw, true ) ?: [] )
                : [];   // backward compat

            [ $rec_label, $rec_detail, $priority ] = self::blog_recommendation(
                $position, $clicks, $impressions, $ctr, $ai_visible, $fixed_data
            );

            // Indekserings-state: afventer → bekræftet indekseret (men ingen GSC-data endnu) → GSC har data
            $indexing_requested  = get_post_meta( $post->ID, '_rzpa_indexing_requested', true ) ?: null;
            $indexing_confirmed  = get_post_meta( $post->ID, '_rzpa_indexing_confirmed', true ) ?: null;
            if ( $rec_label === 'Ikke i GSC' ) {
                if ( $indexing_confirmed ) {
                    $rec_label = '✓ Indekseret';
                    $priority  = 'low';
                } elseif ( $indexing_requested ) {
                    $rec_label = '⏳ Indeksering afventer';
                    $priority  = 'pending';
                }
            }

            $result[] = [
                'post_id'             => $post->ID,
                'title'               => $post->post_title,
                'url'                 => $url,
                'slug'                => $slug,
                'date'                => $post->post_date,
                'thumbnail'           => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
                'position'            => $position,
                'clicks'              => $clicks,
                'impressions'         => $impressions,
                'ctr'                 => $ctr,
                'has_gsc'             => $gsc !== null,
                'ai_visible'          => $ai_visible,
                'ai_keyword'          => $ai_keyword,
                'rec_label'           => $rec_label,
                'rec_detail'          => $rec_detail,
                'priority'            => $priority,
                'fixed_at'            => ! empty( $fixed_data ) ? max( $fixed_data ) : null,
                'fixed_types'         => array_keys( $fixed_data ),
                'indexing_requested'  => $indexing_requested,
                'indexing_confirmed'  => $indexing_confirmed,
            ];
        }

        return $result;
    }

    private static function normalize_url_for_match( string $url ) : string {
        $url = preg_replace( '#^https?://#i', '', $url );
        $url = preg_replace( '#\?.*#', '', $url );
        return rtrim( $url, '/' );
    }

    /**
     * Beregn anbefaling for et blogindlæg.
     * $fixed_data: array keyed by fix_type → timestamp, fx ['fix_ai_vis' => '2026-04-12 21:00:00']
     * Returnerer ['label', 'detail', 'priority'] — priority kan være 'resolved' for løste problemer.
     */
    private static function blog_recommendation(
        ?float $pos, int $clicks, int $impressions, float $ctr, bool $ai,
        array $fixed_data = []
    ) : array {
        // Hvilke rec_labels ophæves af hvilke fix_types?
        static $fix_resolves = [
            'fix_ai_vis'  => [ 'Øg AI-synlighed', 'Mangler AI-synlighed' ],
            'fix_ctr'     => [ 'Optimer title & CTR' ],
            'fix_content' => [ 'Tæt på side 1', 'Svag placering', 'Meget lav synlighed' ],
            'fix_rewrite' => [ 'Tæt på side 1', 'Svag placering', 'Meget lav synlighed' ],
        ];

        // Helper: er labelen dækket af et gemt fix?
        $is_fixed = static function( string $label ) use ( $fixed_data, $fix_resolves ) : bool {
            foreach ( $fixed_data as $ft => $ts ) {
                if ( in_array( $label, $fix_resolves[ $ft ] ?? [], true ) ) return true;
            }
            return false;
        };

        // "Ikke i GSC" løses via indexering (ikke AI-fix) — håndteres i get_blog_insights()
        if ( $pos === null ) {
            return [
                'Ikke i GSC',
                'Siden er ikke fundet i Google Search Console. Indsend URL i GSC og tjek at den er indekseret.',
                'high',
            ];
        }

        if ( $pos <= 3 ) {
            if ( ! $ai ) {
                $label  = 'Mangler AI-synlighed';
                $detail = 'Fremragende Google-placering! Men siden nævnes ikke i AI-søgninger. Tilføj en FAQ-sektion og brug schema.org/FAQPage markup.';
                $prio   = 'medium';
                return $is_fixed( $label ) ? [ '✅ AI-synlighed fikset', $detail, 'resolved' ] : [ $label, $detail, $prio ];
            }
            return [ 'Top performer 🏆', 'Siden rangerer suverænt på Google og er synlig i AI-søgninger. Vedligehold indholdet og byg backlinks for at fastholde positionen.', 'low' ];
        }

        if ( $pos <= 10 ) {
            if ( $impressions > 300 && $ctr < 2.0 ) {
                $label  = 'Optimer title & CTR';
                $detail = 'Siden er på side 1 men har lav CTR. Forbedr title tag: tilføj tal, power words og søgeordet tidligt. Opdater meta description med en klar CTA.';
                $prio   = 'high';
                return $is_fixed( $label ) ? [ '✅ Title & CTR fikset', $detail, 'resolved' ] : [ $label, $detail, $prio ];
            }
            if ( ! $ai ) {
                $label  = 'Øg AI-synlighed';
                $detail = 'God Google-placering. For at blive nævnt af ChatGPT og Gemini: tilføj "Hvad er ...?"-afsnit, bullet points og FAQ med structured data.';
                $prio   = 'medium';
                return $is_fixed( $label ) ? [ '✅ AI-synlighed fikset', $detail, 'resolved' ] : [ $label, $detail, $prio ];
            }
            return [ 'Side 1 ✅', 'Stærk placering og AI-synlighed. Tilføj interne links fra nyere blogindlæg og opdater med frisk data for at holde positionen.', 'low' ];
        }

        if ( $pos <= 20 ) {
            $label  = 'Tæt på side 1';
            $detail = 'Siden er på side 2 — kun få forbedringer fra side 1. Opdater indholdet (min. 800 ord), optimer H2-struktur og tilføj 2-3 interne links.';
            $prio   = 'high';
            return $is_fixed( $label ) ? [ '✅ Indhold fikset', $detail, 'resolved' ] : [ $label, $detail, $prio ];
        }

        if ( $pos <= 50 ) {
            $label  = 'Svag placering';
            $detail = 'Siden rangerer svagt. Genskriv med fokus på primært søgeord i H1/intro, tilføj mere unikt indhold og byg interne + eksterne links.';
            $prio   = 'high';
            return $is_fixed( $label ) ? [ '✅ Indhold fikset', $detail, 'resolved' ] : [ $label, $detail, $prio ];
        }

        $label  = 'Meget lav synlighed';
        $detail = 'Siden er næsten usynlig på Google. Overvej en komplet omskrivning: research søgeord, skriv mindst 1.000 ord og tilføj billeder med alt-tekst.';
        $prio   = 'high';
        return $is_fixed( $label ) ? [ '✅ Indhold omskrevet', $detail, 'resolved' ] : [ $label, $detail, $prio ];
    }

    /** Månedlig SEO-statistik – bruges til trend-graf */
    public static function get_seo_monthly( int $months = 6 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE_FORMAT(date, '%%Y-%%m') AS month,
                    SUM(clicks)      AS total_clicks,
                    SUM(impressions) AS total_impressions,
                    AVG(ctr)         AS avg_ctr,
                    COUNT(DISTINCT keyword) AS keyword_count
             FROM $t
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
             GROUP BY month
             ORDER BY month ASC",
            $months
        ), ARRAY_A ) ?: [];
    }

    /** WordPress indholdstype-kort: { '/path/' => 'page'|'post' } */
    public static function get_wp_content_map() : array {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT ID, post_type FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('page','post')
             ORDER BY post_type ASC",
            ARRAY_A
        );
        $map = [];
        foreach ( $results as $row ) {
            $link = get_permalink( (int) $row['ID'] );
            if ( ! $link ) continue;
            $path = rtrim( parse_url( $link, PHP_URL_PATH ), '/' ) . '/';
            $map[ $path ] = $row['post_type'];
        }
        return $map;
    }

    /** SEO-sammenligning: forrige periode vs nuværende */
    public static function get_seo_comparison( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_seo_data';
        $cur  = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(ctr) AS ctr
             FROM $t WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)", $days
        ), ARRAY_A );
        $prev = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(ctr) AS ctr
             FROM $t WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                       AND date <  DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days * 2, $days
        ), ARRAY_A );
        return [ 'current' => $cur ?: [], 'previous' => $prev ?: [] ];
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

    /** Seneste status pr. søgeord — bruges til keyword-statusoversigt.
     *  Bruger MAX(id) pr. keyword, så hvert søgeord kun vises én gang
     *  (ingen dubletter selv hvis samme dato er syncet to gange). */
    public static function get_ai_keyword_status() : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_ai_overview';
        return $wpdb->get_results(
            "SELECT k.keyword, k.has_ai_overview, k.has_featured_snippet, k.has_paa,
                    k.ai_overview_text, k.date, k.source
             FROM $t k
             INNER JOIN (
                 SELECT MAX(id) AS max_id FROM $t GROUP BY keyword
             ) latest ON k.id = latest.max_id
             ORDER BY k.keyword ASC",
            ARRAY_A
        ) ?: [];
    }

    /** Slet alle AI-overview rækker for søgeord der IKKE er i $current_keywords-listen.
     *  Bruges ved sync for at rydde op i gamle/fjernede søgeord (fx CRM-nøgleord). */
    public static function purge_old_ai_keywords( array $current_keywords ) : void {
        global $wpdb;
        if ( empty( $current_keywords ) ) return;
        $t            = $wpdb->prefix . 'rzpa_ai_overview';
        $placeholders = implode( ',', array_fill( 0, count( $current_keywords ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$t}` WHERE keyword NOT IN ($placeholders)",
            $current_keywords
        ) );
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

    public static function insert_meta_campaigns( array $rows, int $days = 30 ) {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_meta_campaigns';
        // Ryd kun den specifikke periode – bevarer data for andre perioder
        $wpdb->delete( $t, [ 'period_days' => $days ], [ '%d' ] );
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
                'period_days'   => $days,
            ] );
        }
    }

    public static function get_meta_campaigns( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_meta_campaigns';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT *, CASE WHEN impressions > 0 THEN ROUND(clicks/impressions*100,2) ELSE 0 END AS ctr
             FROM $t WHERE period_days = %d ORDER BY spend DESC",
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
                    CASE WHEN SUM(impressions) > 0
                         THEN ROUND(SUM(clicks)/SUM(impressions)*100, 2)
                         ELSE 0 END AS avg_ctr,
                    COUNT(*) AS campaign_count
             FROM $t WHERE period_days = %d",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    public static function has_meta_data( int $days = 30 ) : bool {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_meta_campaigns';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t WHERE period_days = %d", $days
        ) );
    }

    // ── Google Ads ────────────────────────────────────────────────────────────

    public static function insert_google_ads_campaigns( array $rows, int $days = 30 ) : void {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_google_ads_campaigns';
        $wpdb->delete( $t, [ 'period_days' => $days ], [ '%d' ] );
        foreach ( $rows as $r ) {
            $wpdb->insert( $t, [
                'campaign_id'   => sanitize_text_field( $r['campaign_id'] ),
                'campaign_name' => sanitize_text_field( $r['campaign_name'] ),
                'status'        => sanitize_text_field( $r['status'] ),
                'spend'         => (float) $r['spend'],
                'impressions'   => (int)   $r['impressions'],
                'clicks'        => (int)   $r['clicks'],
                'conversions'   => (float) ( $r['conversions'] ?? 0 ),
                'cpm'           => (float) $r['cpm'],
                'cpc'           => (float) $r['cpc'],
                'ctr'           => (float) $r['ctr'],
                'date_start'    => sanitize_text_field( $r['date_start'] ),
                'date_stop'     => sanitize_text_field( $r['date_stop'] ),
                'period_days'   => $days,
            ] );
        }
    }

    public static function get_google_ads_campaigns( int $days = 30 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_google_ads_campaigns';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE period_days = %d ORDER BY spend DESC",
            $days
        ), ARRAY_A ) ?: [];
    }

    public static function get_google_ads_summary( int $days = 30 ) : array {
        global $wpdb;
        $t   = $wpdb->prefix . 'rzpa_google_ads_campaigns';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(spend) AS total_spend, SUM(impressions) AS total_impressions,
                    SUM(clicks) AS total_clicks, SUM(conversions) AS total_conversions,
                    CASE WHEN SUM(impressions) > 0 THEN ROUND(SUM(clicks)/SUM(impressions)*100,2) ELSE 0 END AS avg_ctr,
                    CASE WHEN SUM(clicks) > 0 THEN ROUND(SUM(spend)/SUM(clicks),2) ELSE 0 END AS avg_cpc,
                    COUNT(*) AS campaign_count
             FROM $t WHERE period_days = %d",
            $days
        ), ARRAY_A );
        return $row ?: [];
    }

    public static function has_google_ads_data( int $days = 30 ) : bool {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_google_ads_campaigns';
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE period_days = %d", $days ) );
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

    // ── Sitemaps ─────────────────────────────────────────────────────────────

    /** Hent alle sitemaps med url-tæller. */
    public static function get_sitemaps(): array {
        global $wpdb;
        $s = $wpdb->prefix . 'rzpa_sitemaps';
        $u = $wpdb->prefix . 'rzpa_sitemap_urls';
        return $wpdb->get_results(
            "SELECT s.*, COUNT(u.id) AS url_count
             FROM {$s} s
             LEFT JOIN {$u} u ON u.sitemap_id = s.id
             GROUP BY s.id
             ORDER BY s.created_at DESC"
        ) ?: [];
    }

    /** Hent ét sitemap på ID. */
    public static function get_sitemap( int $id ): ?object {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sitemaps';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) ?: null;
    }

    /** Hent ét sitemap på slug. */
    public static function get_sitemap_by_slug( string $slug ): ?object {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sitemaps';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", $slug ) ) ?: null;
    }

    /** Opret nyt sitemap – returnerer nyt ID eller false. */
    public static function create_sitemap( string $name, string $slug, string $description = '' ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sitemaps';
        $ok = $wpdb->insert( $t, [
            'name'        => sanitize_text_field( $name ),
            'slug'        => sanitize_key( $slug ),
            'description' => sanitize_textarea_field( $description ),
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    /** Opdater eksisterende sitemap. */
    public static function update_sitemap( int $id, string $name, string $slug, string $description = '' ): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sitemaps';
        return (bool) $wpdb->update( $t, [
            'name'        => sanitize_text_field( $name ),
            'slug'        => sanitize_key( $slug ),
            'description' => sanitize_textarea_field( $description ),
            'updated_at'  => current_time( 'mysql' ),
        ], [ 'id' => $id ] );
    }

    /** Slet sitemap og alle tilhørende URLs (ON DELETE CASCADE). */
    public static function delete_sitemap( int $id ): bool {
        global $wpdb;
        // Slet URLs manuelt (kræver ikke FK-support)
        $wpdb->delete( $wpdb->prefix . 'rzpa_sitemap_urls', [ 'sitemap_id' => $id ] );
        return (bool) $wpdb->delete( $wpdb->prefix . 'rzpa_sitemaps', [ 'id' => $id ] );
    }

    // ── Sitemap URLs ─────────────────────────────────────────────────────────

    /** Hent alle URLs for et sitemap, sorteret efter prioritet. */
    public static function get_sitemap_urls( int $sitemap_id ): array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sitemap_urls';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE sitemap_id = %d ORDER BY priority DESC, id ASC",
            $sitemap_id
        ) ) ?: [];
    }

    /** Tilføj én URL til et sitemap. */
    public static function add_sitemap_url(
        int $sitemap_id,
        string $url,
        float $priority   = 0.5,
        string $changefreq = 'weekly',
        ?string $lastmod  = null
    ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_sitemap_urls';

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) return false;

        $data = [
            'sitemap_id' => $sitemap_id,
            'url'        => esc_url_raw( $url ),
            'priority'   => round( min( 1.0, max( 0.0, $priority ) ), 1 ),
            'changefreq' => in_array( $changefreq, RZPA_Sitemap_Manager::CHANGEFREQ_OPTIONS, true ) ? $changefreq : 'weekly',
            'created_at' => current_time( 'mysql' ),
        ];

        if ( $lastmod ) {
            $data['lastmod'] = sanitize_text_field( $lastmod );
        }

        $ok = $wpdb->insert( $t, $data );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    /** Slet én URL. */
    public static function delete_sitemap_url( int $url_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'rzpa_sitemap_urls', [ 'id' => $url_id ] );
    }

    /** Bulk-tilføj URLs fra en newline-separeret streng. Returnerer antal tilføjede. */
    public static function bulk_add_sitemap_urls( int $sitemap_id, string $raw, float $priority = 0.5, string $changefreq = 'weekly' ): int {
        $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        $count = 0;
        foreach ( $lines as $url ) {
            if ( self::add_sitemap_url( $sitemap_id, $url, $priority, $changefreq ) ) {
                $count++;
            }
        }
        return $count;
    }
}
