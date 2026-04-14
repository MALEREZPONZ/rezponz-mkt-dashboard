<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rekrutteringsmodul — samler Meta + Google Ads rekrutteringsdata
 * og en simpel ansøgningspipeline.
 */
class RZPA_Rekruttering {

    const PIPELINE_OPTION = 'rzpa_rekruttering_pipeline';
    const STATS_TRANSIENT  = 'rzpa_rekrut_stats_';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
    }

    public static function add_menu() {
        $cap = 'manage_options';
        add_submenu_page( 'rzpa-dashboard', '', '👥 HR & Crew', $cap, 'rzpa-section-crew', [ __CLASS__, 'page_rekruttering' ] );
        add_submenu_page( 'rzpa-dashboard', 'Rekruttering – Rezponz',   'Rekruttering',         $cap, 'rzpa-rekruttering',   [ __CLASS__, 'page_rekruttering' ] );
        add_submenu_page( 'rzpa-dashboard', 'RezCRM – Rezponz',         '🎯 RezCRM',            $cap, 'rzpa-rezcrm',         [ 'RZPZ_CRM_Admin', 'render_page' ] );
        add_submenu_page( 'rzpa-dashboard', 'Ansøgningsformularer – Rezponz', '📋 Formularer',  $cap, 'rzpa-rezcrm-forms',   [ __CLASS__, 'page_forms' ] );
        add_submenu_page( 'rzpa-dashboard', 'RezCRM Brugere – Rezponz', '🔐 Brugere & Sikkerhed', $cap, 'rzpa-rezcrm-users', [ __CLASS__, 'page_users' ] );
    }

    public static function page_forms() {
        if ( class_exists( 'RZPZ_CRM_Forms_DB' ) ) {
            require_once RZPA_DIR . 'modules/rezcrm/views/admin-rezcrm-forms.php';
        }
    }

    public static function page_users() {
        require_once RZPA_DIR . 'modules/rezcrm/views/admin-rezcrm-users.php';
    }

    public static function page_rekruttering() {
        require_once __DIR__ . '/views/admin-rekruttering.php';
    }

    // ── Meta: kampagner med Lead-actions ────────────────────────────────────

    public static function fetch_meta_stats( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            return [ 'error' => 'Meta Ads ikke konfigureret' ];
        }

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];
        $preset_map = [ 7 => 'last_7d', 30 => 'last_30d', 90 => 'last_90d' ];
        $preset     = $preset_map[ $days ] ?? 'last_30d';

        $url = 'https://graph.facebook.com/v21.0/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token' => $token,
            'level'        => 'campaign',
            'date_preset'  => $preset,
            'fields'       => 'campaign_id,campaign_name,spend,reach,impressions,clicks,actions',
            'limit'        => 50,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $res ) ) return [ 'error' => $res->get_error_message() ];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return [ 'error' => $body['error']['message'] ?? 'Meta API fejl' ];

        $campaigns = [];
        foreach ( $body['data'] ?? [] as $row ) {
            // Udtræk Lead-actions fra actions-array
            $leads = 0;
            foreach ( $row['actions'] ?? [] as $action ) {
                if ( in_array( $action['action_type'], [ 'lead', 'offsite_conversion.fb_pixel_lead', 'onsite_conversion.lead_grouped' ], true ) ) {
                    $leads += (int) $action['value'];
                }
            }

            $spend       = round( (float) ( $row['spend'] ?? 0 ), 2 );
            $impressions = (int) ( $row['impressions'] ?? 0 );
            $clicks      = (int) ( $row['clicks'] ?? 0 );

            $campaigns[] = [
                'campaign_id'   => $row['campaign_id']   ?? '',
                'campaign_name' => $row['campaign_name'] ?? '',
                'spend'         => $spend,
                'reach'         => (int) ( $row['reach'] ?? 0 ),
                'impressions'   => $impressions,
                'clicks'        => $clicks,
                'leads'         => $leads,
                'cpl'           => $leads > 0 ? round( $spend / $leads, 2 ) : 0,
                'ctr'           => $impressions > 0 ? round( $clicks / $impressions * 100, 2 ) : 0,
                'channel'       => 'meta',
            ];
        }

        // Sorter efter spend desc
        usort( $campaigns, fn( $a, $b ) => $b['spend'] <=> $a['spend'] );
        return $campaigns;
    }

    // ── Google Ads: kampagner med konverteringer ─────────────────────────────

    public static function fetch_google_stats( int $days = 30 ) : array {
        // Genbruge den eksisterende Google Ads fetch — den har allerede conversions-feltet
        $campaigns = RZPA_Google_Ads::fetch( $days );
        if ( empty( $campaigns ) ) return [];

        return array_map( function( $c ) {
            return array_merge( $c, [
                'leads' => (int) round( $c['conversions'] ?? 0 ),
                'cpl'   => ( isset( $c['conversions'] ) && $c['conversions'] > 0 && isset( $c['spend'] ) )
                           ? round( $c['spend'] / $c['conversions'], 2 )
                           : 0,
                'channel' => 'google',
            ] );
        }, $campaigns );
    }

    // ── Samlet statistik ─────────────────────────────────────────────────────

    public static function get_stats( int $days = 30 ) : array {
        $cache_key = self::STATS_TRANSIENT . $days;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $meta   = self::fetch_meta_stats( $days );
        $google = self::fetch_google_stats( $days );

        // Aggreger totaler
        $meta_campaigns   = is_array( $meta ) && ! isset( $meta['error'] ) ? $meta : [];
        $google_campaigns = $google ?: [];

        $all_campaigns = array_merge( $meta_campaigns, $google_campaigns );

        $total_spend  = array_sum( array_column( $all_campaigns, 'spend' ) );
        $total_leads  = array_sum( array_column( $all_campaigns, 'leads' ) );
        $meta_spend   = array_sum( array_column( $meta_campaigns, 'spend' ) );
        $meta_leads   = array_sum( array_column( $meta_campaigns, 'leads' ) );
        $google_spend = array_sum( array_column( $google_campaigns, 'spend' ) );
        $google_leads = array_sum( array_column( $google_campaigns, 'leads' ) );

        $result = [
            'totals' => [
                'spend'      => round( $total_spend, 2 ),
                'leads'      => (int) $total_leads,
                'cpl'        => $total_leads > 0 ? round( $total_spend / $total_leads, 2 ) : 0,
                'meta_spend' => round( $meta_spend, 2 ),
                'meta_leads' => (int) $meta_leads,
                'google_spend'=> round( $google_spend, 2 ),
                'google_leads'=> (int) $google_leads,
            ],
            'meta_campaigns'   => $meta_campaigns,
            'google_campaigns' => $google_campaigns,
            'meta_error'       => is_array( $meta ) && isset( $meta['error'] ) ? $meta['error'] : null,
            'pipeline'         => self::get_pipeline(),
        ];

        set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
        return $result;
    }

    // ── Pipeline CRUD ────────────────────────────────────────────────────────

    public static function get_pipeline() : array {
        $default = [
            'ansoegt'  => [ 'label' => 'Ansøgt',   'aalborg' => 0, 'remote' => 0, 'uopfordret' => 0 ],
            'screenet' => [ 'label' => 'Screenet',  'aalborg' => 0, 'remote' => 0, 'uopfordret' => 0 ],
            'samtale'  => [ 'label' => 'Til samtale','aalborg'=> 0, 'remote' => 0, 'uopfordret' => 0 ],
            'tilbudt'  => [ 'label' => 'Tilbudt',   'aalborg' => 0, 'remote' => 0, 'uopfordret' => 0 ],
            'ansat'    => [ 'label' => 'Ansat',     'aalborg' => 0, 'remote' => 0, 'uopfordret' => 0 ],
        ];
        $saved = get_option( self::PIPELINE_OPTION, [] );
        if ( empty( $saved ) ) return $default;

        // Merge for at sikre alle stages altid er til stede
        foreach ( $default as $key => $stage ) {
            if ( ! isset( $saved[ $key ] ) ) $saved[ $key ] = $stage;
        }
        return $saved;
    }

    public static function save_pipeline( array $data ) : bool {
        $pipeline = self::get_pipeline();
        foreach ( $pipeline as $stage => $vals ) {
            foreach ( [ 'aalborg', 'remote', 'uopfordret' ] as $col ) {
                if ( isset( $data[ $stage ][ $col ] ) ) {
                    $pipeline[ $stage ][ $col ] = max( 0, (int) $data[ $stage ][ $col ] );
                }
            }
        }
        return update_option( self::PIPELINE_OPTION, $pipeline );
    }

    // ── Ryd stats-transient (bruges ved force-refresh) ───────────────────────

    public static function clear_cache( int $days = 0 ) {
        if ( $days ) {
            delete_transient( self::STATS_TRANSIENT . $days );
        } else {
            foreach ( [ 7, 30, 90 ] as $d ) delete_transient( self::STATS_TRANSIENT . $d );
        }
    }
}
