<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz Crew – Database layer.
 * Handles schema installation and all CRUD operations for crew data.
 */
class RZPZ_Crew_DB {

    const DB_VERSION_KEY = 'rzpz_crew_db_version';
    const DB_VERSION     = '2';

    // ── Schema ───────────────────────────────────────────────────────────────

    public static function install() : void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_members (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      BIGINT UNSIGNED DEFAULT NULL,
            crew_id      VARCHAR(50)  NOT NULL,
            display_name VARCHAR(150) NOT NULL,
            email        VARCHAR(200) NOT NULL,
            phone         VARCHAR(50)  DEFAULT '',
            bio           TEXT         DEFAULT NULL,
            avatar_url    VARCHAR(512) DEFAULT '',
            facebook_url  VARCHAR(512) DEFAULT '',
            instagram_url VARCHAR(512) DEFAULT '',
            tiktok_url    VARCHAR(512) DEFAULT '',
            snapchat_url  VARCHAR(512) DEFAULT '',
            status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   uq_crew_id (crew_id),
            KEY          idx_status (status)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_links (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            crew_member_id BIGINT UNSIGNED NOT NULL,
            campaign_name VARCHAR(150) NOT NULL DEFAULT 'default',
            destination_url VARCHAR(512) NOT NULL,
            utm_source    VARCHAR(100) NOT NULL DEFAULT 'rezponz_crew',
            utm_medium    VARCHAR(100) NOT NULL DEFAULT 'employee',
            utm_campaign  VARCHAR(150) NOT NULL,
            utm_content   VARCHAR(100) NOT NULL,
            full_url      TEXT         NOT NULL,
            clicks        INT UNSIGNED NOT NULL DEFAULT 0,
            created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY   (id),
            KEY           idx_crew (crew_member_id),
            KEY           idx_campaign (utm_campaign(50))
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_clicks (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id        BIGINT UNSIGNED NOT NULL,
            crew_member_id BIGINT UNSIGNED NOT NULL,
            ip_hash        VARCHAR(64) NOT NULL,
            referrer       VARCHAR(512) DEFAULT '',
            user_agent     VARCHAR(255) DEFAULT '',
            clicked_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY    (id),
            KEY            idx_link (link_id),
            KEY            idx_crew (crew_member_id),
            KEY            idx_date (clicked_at)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_conversions (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            crew_member_id BIGINT UNSIGNED NOT NULL,
            link_id        BIGINT UNSIGNED DEFAULT NULL,
            utm_campaign   VARCHAR(150) DEFAULT '',
            ip_hash        VARCHAR(64)  NOT NULL,
            converted_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY    (id),
            KEY            idx_crew (crew_member_id),
            KEY            idx_date (converted_at)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_bonus_rules (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_name        VARCHAR(150) NOT NULL,
            rule_type        ENUM('per_conversion','per_clicks') NOT NULL,
            amount_dkk       DECIMAL(10,2) NOT NULL DEFAULT 0,
            clicks_threshold INT UNSIGNED DEFAULT 100,
            active           TINYINT(1) NOT NULL DEFAULT 1,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY      (id)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_bonuses (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            crew_member_id   BIGINT UNSIGNED NOT NULL,
            period_start     DATE NOT NULL,
            period_end       DATE NOT NULL,
            total_clicks     INT UNSIGNED NOT NULL DEFAULT 0,
            total_conversions INT UNSIGNED NOT NULL DEFAULT 0,
            amount_dkk       DECIMAL(10,2) NOT NULL DEFAULT 0,
            status           ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
            admin_notes      TEXT DEFAULT NULL,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY      (id),
            KEY              idx_crew (crew_member_id),
            KEY              idx_status (status)
        ) $c;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crew_boosts (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id        BIGINT UNSIGNED NOT NULL,
            crew_member_id BIGINT UNSIGNED NOT NULL,
            boosted_by     BIGINT UNSIGNED DEFAULT NULL,
            status         ENUM('ready','in_progress','live','paused') NOT NULL DEFAULT 'ready',
            notes          TEXT DEFAULT NULL,
            ad_url         VARCHAR(512) DEFAULT '',
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY    (id),
            KEY            idx_link (link_id),
            KEY            idx_status (status)
        ) $c;" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    // ── Crew Members ─────────────────────────────────────────────────────────

    public static function get_members( string $status = '' ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_members';
        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY display_name ASC", $status ), ARRAY_A ) ?: [];
        }
        return $wpdb->get_results( "SELECT * FROM $t ORDER BY display_name ASC", ARRAY_A ) ?: [];
    }

    public static function get_member( int $id ) : ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A ) ?: null;
    }

    public static function get_member_by_crew_id( string $crew_id ) : ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE crew_id = %s", $crew_id ), ARRAY_A ) ?: null;
    }

    public static function get_member_by_user( int $user_id ) : ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d", $user_id ), ARRAY_A ) ?: null;
    }

    public static function insert_member( array $data ) : int|false {
        global $wpdb;
        // Generate unique crew_id if not provided
        if ( empty( $data['crew_id'] ) ) {
            $base = sanitize_title( $data['display_name'] ?? 'crew' );
            $data['crew_id'] = self::unique_crew_id( $base );
        }
        $ok = $wpdb->insert( $wpdb->prefix . 'rzpz_crew_members', [
            'user_id'       => $data['user_id']       ?? null,
            'crew_id'       => $data['crew_id'],
            'display_name'  => $data['display_name']  ?? '',
            'email'         => $data['email']         ?? '',
            'phone'         => $data['phone']         ?? '',
            'bio'           => $data['bio']           ?? '',
            'avatar_url'    => $data['avatar_url']    ?? '',
            'facebook_url'  => $data['facebook_url']  ?? '',
            'instagram_url' => $data['instagram_url'] ?? '',
            'tiktok_url'    => $data['tiktok_url']    ?? '',
            'snapchat_url'  => $data['snapchat_url']  ?? '',
            'status'        => $data['status']        ?? 'active',
        ] );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function update_member( int $id, array $data ) : bool {
        global $wpdb;
        $allowed = [ 'display_name', 'email', 'phone', 'bio', 'avatar_url', 'facebook_url', 'instagram_url', 'tiktok_url', 'snapchat_url', 'status', 'user_id' ];
        $update  = array_intersect_key( $data, array_flip( $allowed ) );
        if ( empty( $update ) ) return false;
        return (bool) $wpdb->update( $wpdb->prefix . 'rzpz_crew_members', $update, [ 'id' => $id ] );
    }

    public static function delete_member( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'rzpz_crew_members', [ 'id' => $id ] );
    }

    private static function unique_crew_id( string $base ) : string {
        global $wpdb;
        $t    = $wpdb->prefix . 'rzpz_crew_members';
        $slug = $base;
        $i    = 1;
        while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE crew_id = %s", $slug ) ) ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // ── Links ─────────────────────────────────────────────────────────────────

    public static function get_links( int $crew_member_id = 0 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_links';
        if ( $crew_member_id ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE crew_member_id = %d ORDER BY created_at DESC", $crew_member_id ), ARRAY_A ) ?: [];
        }
        return $wpdb->get_results( "SELECT l.*, m.display_name FROM $t l LEFT JOIN {$wpdb->prefix}rzpz_crew_members m ON l.crew_member_id = m.id ORDER BY l.created_at DESC", ARRAY_A ) ?: [];
    }

    public static function get_link( int $id ) : ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_links';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A ) ?: null;
    }

    public static function insert_link( array $data ) : int|false {
        global $wpdb;
        $opts        = get_option( 'rzpz_crew_settings', [] );
        $dest_url    = $data['destination_url'] ?? ( $opts['default_destination_url'] ?? 'https://rezponz.dk/jobs/' );
        $campaign    = sanitize_text_field( $data['campaign_name'] ?? 'default' );
        $crew_id_str = $data['utm_content'] ?? '';

        $full_url = add_query_arg( [
            'utm_source'   => 'rezponz_crew',
            'utm_medium'   => 'employee',
            'utm_campaign' => $campaign,
            'utm_content'  => $crew_id_str,
        ], $dest_url );

        $ok = $wpdb->insert( $wpdb->prefix . 'rzpz_crew_links', [
            'crew_member_id' => (int) $data['crew_member_id'],
            'campaign_name'  => $campaign,
            'destination_url'=> $dest_url,
            'utm_source'     => 'rezponz_crew',
            'utm_medium'     => 'employee',
            'utm_campaign'   => $campaign,
            'utm_content'    => $crew_id_str,
            'full_url'       => $full_url,
            'clicks'         => 0,
        ] );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function increment_link_clicks( int $link_id ) : void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rzpz_crew_links SET clicks = clicks + 1 WHERE id = %d", $link_id ) );
    }

    // ── Clicks ───────────────────────────────────────────────────────────────

    public static function record_click( int $link_id, int $crew_member_id ) : void {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'rzpz_crew_clicks', [
            'link_id'        => $link_id,
            'crew_member_id' => $crew_member_id,
            'ip_hash'        => self::ip_hash(),
            'referrer'       => sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ),
            'user_agent'     => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
        ] );
        self::increment_link_clicks( $link_id );
    }

    public static function get_clicks_count( int $crew_member_id, ?int $days = null ) : int {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_clicks';
        if ( $days ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE crew_member_id = %d AND clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $crew_member_id, $days ) );
        }
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE crew_member_id = %d", $crew_member_id ) );
    }

    // ── Conversions ──────────────────────────────────────────────────────────

    public static function record_conversion( int $crew_member_id, ?int $link_id, string $campaign ) : void {
        global $wpdb;
        $ip = self::ip_hash();
        // Prevent duplicate conversions from same IP within 24 hours
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_conversions WHERE crew_member_id = %d AND ip_hash = %s AND converted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $crew_member_id, $ip
        ) );
        if ( $recent ) return;

        $wpdb->insert( $wpdb->prefix . 'rzpz_crew_conversions', [
            'crew_member_id' => $crew_member_id,
            'link_id'        => $link_id,
            'utm_campaign'   => $campaign,
            'ip_hash'        => $ip,
        ] );
    }

    public static function get_conversions_count( int $crew_member_id, ?int $days = null ) : int {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_conversions';
        if ( $days ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE crew_member_id = %d AND converted_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $crew_member_id, $days ) );
        }
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE crew_member_id = %d", $crew_member_id ) );
    }

    // ── Bonus Rules ───────────────────────────────────────────────────────────

    public static function get_bonus_rules( bool $active_only = false ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_bonus_rules';
        if ( $active_only ) {
            return $wpdb->get_results( "SELECT * FROM $t WHERE active = 1 ORDER BY id ASC", ARRAY_A ) ?: [];
        }
        return $wpdb->get_results( "SELECT * FROM $t ORDER BY id ASC", ARRAY_A ) ?: [];
    }

    public static function save_bonus_rule( array $data ) : int|false {
        global $wpdb;
        $fields = [
            'rule_name'        => sanitize_text_field( $data['rule_name'] ),
            'rule_type'        => in_array( $data['rule_type'], [ 'per_conversion', 'per_clicks' ] ) ? $data['rule_type'] : 'per_conversion',
            'amount_dkk'       => (float) $data['amount_dkk'],
            'clicks_threshold' => (int) ( $data['clicks_threshold'] ?? 100 ),
            'active'           => (int) ( $data['active'] ?? 1 ),
        ];
        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $t = $wpdb->prefix . 'rzpz_crew_bonus_rules', $fields, [ 'id' => (int) $data['id'] ] );
            return (int) $data['id'];
        }
        return $wpdb->insert( $wpdb->prefix . 'rzpz_crew_bonus_rules', $fields ) ? $wpdb->insert_id : false;
    }

    /**
     * Calculate bonus for a crew member based on active rules.
     */
    public static function calculate_bonus( int $crew_member_id, string $period_start, string $period_end ) : float {
        global $wpdb;
        $rules = self::get_bonus_rules( true );
        if ( empty( $rules ) ) return 0.0;

        $clicks = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_clicks WHERE crew_member_id = %d AND clicked_at BETWEEN %s AND %s",
            $crew_member_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59'
        ) );
        $conversions = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_conversions WHERE crew_member_id = %d AND converted_at BETWEEN %s AND %s",
            $crew_member_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59'
        ) );

        $total = 0.0;
        foreach ( $rules as $rule ) {
            if ( $rule['rule_type'] === 'per_conversion' ) {
                $total += $conversions * (float) $rule['amount_dkk'];
            } elseif ( $rule['rule_type'] === 'per_clicks' && $rule['clicks_threshold'] > 0 ) {
                $total += floor( $clicks / $rule['clicks_threshold'] ) * (float) $rule['amount_dkk'];
            }
        }
        return round( $total, 2 );
    }

    // ── Bonuses ───────────────────────────────────────────────────────────────

    public static function get_bonuses( int $crew_member_id = 0 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_bonuses';
        $m = $wpdb->prefix . 'rzpz_crew_members';
        if ( $crew_member_id ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT b.*, m.display_name FROM $t b LEFT JOIN $m m ON b.crew_member_id = m.id WHERE b.crew_member_id = %d ORDER BY b.period_start DESC", $crew_member_id ), ARRAY_A ) ?: [];
        }
        return $wpdb->get_results( "SELECT b.*, m.display_name FROM $t b LEFT JOIN $m m ON b.crew_member_id = m.id ORDER BY b.period_start DESC", ARRAY_A ) ?: [];
    }

    public static function upsert_bonus( int $crew_member_id, string $period_start, string $period_end ) : void {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crew_bonuses';

        $clicks = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_clicks WHERE crew_member_id = %d AND clicked_at BETWEEN %s AND %s",
            $crew_member_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59'
        ) );
        $conversions = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_conversions WHERE crew_member_id = %d AND converted_at BETWEEN %s AND %s",
            $crew_member_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59'
        ) );
        $amount = self::calculate_bonus( $crew_member_id, $period_start, $period_end );

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE crew_member_id = %d AND period_start = %s AND period_end = %s", $crew_member_id, $period_start, $period_end ) );
        if ( $existing ) {
            $wpdb->update( $t, [ 'total_clicks' => $clicks, 'total_conversions' => $conversions, 'amount_dkk' => $amount ], [ 'id' => $existing ] );
        } else {
            $wpdb->insert( $t, compact( 'crew_member_id', 'period_start', 'period_end' ) + [ 'total_clicks' => $clicks, 'total_conversions' => $conversions, 'amount_dkk' => $amount, 'status' => 'pending' ] );
        }
    }

    public static function update_bonus_status( int $id, string $status, string $notes = '' ) : void {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'rzpz_crew_bonuses', [ 'status' => $status, 'admin_notes' => $notes ], [ 'id' => $id ] );
    }

    // ── Boosts ────────────────────────────────────────────────────────────────

    public static function get_boosts( string $status = '' ) : array {
        global $wpdb;
        $b = $wpdb->prefix . 'rzpz_crew_boosts';
        $l = $wpdb->prefix . 'rzpz_crew_links';
        $m = $wpdb->prefix . 'rzpz_crew_members';
        $sql = "SELECT bo.*, l.full_url, l.campaign_name, l.clicks, l.utm_content, m.display_name
                FROM $b bo
                LEFT JOIN $l l ON bo.link_id = l.id
                LEFT JOIN $m m ON bo.crew_member_id = m.id";
        if ( $status ) {
            $sql .= $wpdb->prepare( ' WHERE bo.status = %s', $status );
        }
        $sql .= ' ORDER BY bo.created_at DESC';
        return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
    }

    public static function insert_boost( int $link_id, int $crew_member_id, string $notes = '' ) : int|false {
        global $wpdb;
        // Check not already boosted
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rzpz_crew_boosts WHERE link_id = %d", $link_id ) );
        if ( $exists ) return (int) $exists;

        $ok = $wpdb->insert( $wpdb->prefix . 'rzpz_crew_boosts', [
            'link_id'        => $link_id,
            'crew_member_id' => $crew_member_id,
            'boosted_by'     => get_current_user_id(),
            'status'         => 'ready',
            'notes'          => sanitize_textarea_field( $notes ),
        ] );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function update_boost( int $id, array $data ) : void {
        global $wpdb;
        $allowed = [ 'status', 'notes', 'ad_url' ];
        $update  = array_intersect_key( $data, array_flip( $allowed ) );
        if ( $update ) {
            $wpdb->update( $wpdb->prefix . 'rzpz_crew_boosts', $update, [ 'id' => $id ] );
        }
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public static function get_top_links( int $crew_member_id, int $limit = 5 ) : array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, (SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crew_conversions c WHERE c.link_id = l.id) AS conversions
             FROM {$wpdb->prefix}rzpz_crew_links l
             WHERE l.crew_member_id = %d
             ORDER BY l.clicks DESC
             LIMIT %d",
            $crew_member_id, $limit
        ), ARRAY_A ) ?: [];
    }

    public static function get_leaderboard( int $days = 30 ) : array {
        global $wpdb;
        $m = $wpdb->prefix . 'rzpz_crew_members';
        $l = $wpdb->prefix . 'rzpz_crew_links';
        $c = $wpdb->prefix . 'rzpz_crew_clicks';
        $cv = $wpdb->prefix . 'rzpz_crew_conversions';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT m.id, m.crew_id, m.display_name, m.status,
                    COALESCE(SUM(l.clicks),0) AS total_clicks,
                    (SELECT COUNT(*) FROM $cv WHERE $cv.crew_member_id = m.id AND converted_at >= DATE_SUB(NOW(), INTERVAL %d DAY)) AS total_conversions
             FROM $m m
             LEFT JOIN $l l ON l.crew_member_id = m.id
             WHERE m.status = 'active'
             GROUP BY m.id
             ORDER BY total_conversions DESC, total_clicks DESC",
            $days
        ), ARRAY_A ) ?: [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function ip_hash() : string {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
        return hash( 'sha256', $ip . wp_salt( 'auth' ) );
    }
}
