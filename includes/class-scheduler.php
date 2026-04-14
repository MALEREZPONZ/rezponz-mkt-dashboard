<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Scheduler {

    public static function init() {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_schedules' ] );
        add_action( 'rzpa_daily_seo_sync',       [ __CLASS__, 'run_seo_sync' ] );
        add_action( 'rzpa_sixhour_ads_sync',     [ __CLASS__, 'run_ads_sync' ] );
        add_action( 'rzpa_blog_calendar_tick',   [ __CLASS__, 'run_blog_calendar' ] );

        if ( ! wp_next_scheduled( 'rzpa_daily_seo_sync' ) ) {
            wp_schedule_event( strtotime( 'today 06:00:00' ), 'daily', 'rzpa_daily_seo_sync' );
        }
        if ( ! wp_next_scheduled( 'rzpa_sixhour_ads_sync' ) ) {
            wp_schedule_event( time(), 'every_6_hours', 'rzpa_sixhour_ads_sync' );
        }
        if ( ! wp_next_scheduled( 'rzpa_blog_calendar_tick' ) ) {
            wp_schedule_event( time(), 'hourly', 'rzpa_blog_calendar_tick' );
        }
    }

    public static function add_schedules( array $schedules ) : array {
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Every 6 Hours',
        ];
        return $schedules;
    }

    public static function run_seo_sync() {
        $rows = RZPA_Google_SEO::fetch( 90 );
        RZPA_Database::insert_seo_rows( $rows );
        RZPA_Database::log_sync( 'google_search_console', 'success', count( $rows ) . ' rows' );
    }

    public static function run_ads_sync() {
        foreach ( [
            [ 'RZPA_Meta_Ads',      'insert_meta_campaigns',        'meta_ads'    ],
            [ 'RZPA_Snapchat_Ads',  'insert_snap_campaigns',        'snapchat_ads'],
            [ 'RZPA_TikTok_Ads',    'insert_tiktok_campaigns',      'tiktok_ads'  ],
            [ 'RZPA_Google_Ads',    'insert_google_ads_campaigns',  'google_ads'  ],
        ] as [ $class, $db_method, $source ] ) {
            try {
                $rows = $class::fetch( 30 );
                RZPA_Database::$db_method( $rows );
                RZPA_Database::log_sync( $source, 'success', count( $rows ) . ' campaigns' );
            } catch ( Exception $e ) {
                RZPA_Database::log_sync( $source, 'error', $e->getMessage() );
            }
        }
        self::check_roi_threshold();
    }

    public static function run_full_sync() {
        self::run_seo_sync();
        self::run_ads_sync();
    }

    private static function check_roi_threshold() {
        $meta = RZPA_Database::get_meta_summary( 7 );
        if ( ! empty( $meta['avg_roas'] ) && (float) $meta['avg_roas'] < 1.5 ) {
            error_log( '[Rezponz Analytics] ALERT: Meta ROAS below 1.5x – current: ' . $meta['avg_roas'] );
        }
    }

    /**
     * Hourly Blog Calendar tick
     *
     * 1. Nulstil stuck-generating topics (ældre end 30 min)
     * 2. Dispatch planlagte topics der er klar til generering
     */
    public static function run_blog_calendar(): void {
        if ( ! class_exists( 'RZPA_Blog_Gen_DB' ) ) return;

        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) return;

        // 1. Ryd stuck-generating topics
        RZPA_Blog_Gen_DB::reset_stuck_generating();

        // 2. Hent planlagte topics der er klar (scheduled_for <= now)
        $due = RZPA_Blog_Gen_DB::get_due_scheduled();
        foreach ( $due as $topic ) {
            $id = (int) $topic->id;
            // Atomisk lock — kun dispatch hvis vi vinder racen (ingen duplikat-posts)
            if ( ! RZPA_Blog_Gen_DB::try_lock_generating( $id ) ) continue;
            RZPA_Blog_Gen_DB::update_status( $id, 'generating', [
                'error_msg'   => null,
                'retry_count' => 0,
            ] );
            wp_schedule_single_event( time() + 2, 'rzpa_bg_generate_article', [ $id ] );
        }
    }

    public static function clear_crons() {
        wp_clear_scheduled_hook( 'rzpa_daily_seo_sync' );
        wp_clear_scheduled_hook( 'rzpa_sixhour_ads_sync' );
        wp_clear_scheduled_hook( 'rzpa_blog_calendar_tick' );
    }
}
