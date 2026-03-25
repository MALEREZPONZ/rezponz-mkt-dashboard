<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_TikTok_Ads {

    const API_BASE = 'https://business-api.tiktok.com/open_api/v1.3';

    public static function fetch( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['tiktok_access_token'] ) || empty( $opts['tiktok_advertiser_id'] ) ) {
            return self::mock_data( $days );
        }

        $token         = $opts['tiktok_access_token'];
        $advertiser_id = $opts['tiktok_advertiser_id'];
        $start         = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end           = gmdate( 'Y-m-d' );

        $campaigns_res = wp_remote_get(
            self::API_BASE . '/campaign/get/?' . http_build_query( [
                'advertiser_id' => $advertiser_id,
                'page_size'     => 100,
            ] ),
            [ 'headers' => [ 'Access-Token' => $token ], 'timeout' => 30 ]
        );

        if ( is_wp_error( $campaigns_res ) ) return self::mock_data( $days );

        $camp_body = json_decode( wp_remote_retrieve_body( $campaigns_res ), true );
        $campaigns = $camp_body['data']['list'] ?? [];

        $stats_res = wp_remote_get(
            self::API_BASE . '/report/integrated/get/?' . http_build_query( [
                'advertiser_id' => $advertiser_id,
                'report_type'   => 'BASIC',
                'dimensions'    => wp_json_encode( [ 'campaign_id' ] ),
                'metrics'       => wp_json_encode( [ 'spend', 'video_play_actions', 'click_cnt', 'conversion', 'value_per_conversion' ] ),
                'start_date'    => $start,
                'end_date'      => $end,
                'page_size'     => 100,
            ] ),
            [ 'headers' => [ 'Access-Token' => $token ], 'timeout' => 30 ]
        );

        $stats_map = [];
        if ( ! is_wp_error( $stats_res ) ) {
            $stats_body = json_decode( wp_remote_retrieve_body( $stats_res ), true );
            foreach ( ( $stats_body['data']['list'] ?? [] ) as $row ) {
                $stats_map[ $row['dimensions']['campaign_id'] ] = $row['metrics'];
            }
        }

        $rows = [];
        foreach ( $campaigns as $c ) {
            $m            = $stats_map[ $c['campaign_id'] ] ?? [];
            $spend        = (float) ( $m['spend'] ?? 0 );
            $video_views  = (int)   ( $m['video_play_actions'] ?? 0 );
            $clicks       = (int)   ( $m['click_cnt'] ?? 0 );
            $conversions  = (int)   ( $m['conversion'] ?? 0 );
            $rev          = (float) ( $m['value_per_conversion'] ?? 0 ) * $conversions;
            $roas         = $spend > 0 ? round( $rev / $spend, 2 ) : 0;
            $cost_per_view = $video_views > 0 ? round( $spend / $video_views, 4 ) : 0;

            $rows[] = [
                'campaign_id'   => $c['campaign_id'],
                'campaign_name' => $c['campaign_name'],
                'status'        => $c['operation_status'],
                'spend'         => $spend,
                'video_views'   => $video_views,
                'clicks'        => $clicks,
                'conversions'   => $conversions,
                'roas'          => $roas,
                'cost_per_view' => $cost_per_view,
                'date_start'    => $start,
                'date_stop'     => $end,
            ];
        }

        return $rows ?: self::mock_data( $days );
    }

    private static function mock_data( int $days ) : array {
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end   = gmdate( 'Y-m-d' );

        $campaigns = [
            [ 'id' => 'tt_001', 'name' => 'Rezponz – Viral Challenge #RezponzIt', 'status' => 'ENABLE' ],
            [ 'id' => 'tt_002', 'name' => 'Rezponz – TopView Takeover',            'status' => 'ENABLE' ],
            [ 'id' => 'tt_003', 'name' => 'Rezponz – InFeed B2B Demo',             'status' => 'ENABLE' ],
            [ 'id' => 'tt_004', 'name' => 'Rezponz – Spark Ads UGC',               'status' => 'DISABLE' ],
        ];

        $rows = [];
        foreach ( $campaigns as $c ) {
            $spend         = round( wp_rand( 800, 5800 ) + wp_rand( 0, 99 ) / 100, 2 );
            $video_views   = wp_rand( 100000, 2100000 );
            $clicks        = (int) ( $video_views * ( wp_rand( 5, 20 ) / 1000 ) );
            $conversions   = (int) ( $clicks * ( wp_rand( 2, 8 ) / 100 ) );
            $roas          = round( wp_rand( 120, 380 ) / 100, 2 );
            $cost_per_view = $video_views > 0 ? round( $spend / $video_views, 4 ) : 0;

            $rows[] = [
                'campaign_id'   => $c['id'],
                'campaign_name' => $c['name'],
                'status'        => $c['status'],
                'spend'         => $spend,
                'video_views'   => $video_views,
                'clicks'        => $clicks,
                'conversions'   => $conversions,
                'roas'          => $roas,
                'cost_per_view' => $cost_per_view,
                'date_start'    => $start,
                'date_stop'     => $end,
            ];
        }
        return $rows;
    }
}
