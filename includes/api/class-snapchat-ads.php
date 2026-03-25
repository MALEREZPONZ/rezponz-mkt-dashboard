<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Snapchat_Ads {

    const API_BASE = 'https://adsapi.snapchat.com/v1';

    public static function fetch( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['snap_access_token'] ) || empty( $opts['snap_ad_account_id'] ) ) {
            return []; // Ikke konfigureret
        }

        $token      = $opts['snap_access_token'];
        $account_id = $opts['snap_ad_account_id'];
        $start      = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end        = gmdate( 'Y-m-d' );

        $res = wp_remote_get( self::API_BASE . '/adaccounts/' . $account_id . '/campaigns', [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $res ) ) return self::mock_data( $days );

        $body      = json_decode( wp_remote_retrieve_body( $res ), true );
        $campaigns = $body['campaigns'] ?? [];
        $rows      = [];

        foreach ( $campaigns as $c ) {
            $cid    = $c['campaign']['id']     ?? '';
            $cname  = $c['campaign']['name']   ?? '';
            $status = $c['campaign']['status'] ?? '';

            $stats_res = wp_remote_get(
                self::API_BASE . '/campaigns/' . $cid . '/stats?' . http_build_query( [
                    'granularity' => 'TOTAL',
                    'start_time'  => $start . 'T00:00:00.000Z',
                    'end_time'    => $end . 'T23:59:59.999Z',
                    'fields'      => 'spend,impressions,swipes,conversion_purchases',
                ] ),
                [
                    'headers' => [ 'Authorization' => 'Bearer ' . $token ],
                    'timeout' => 15,
                ]
            );

            $stats       = [];
            if ( ! is_wp_error( $stats_res ) ) {
                $sbody = json_decode( wp_remote_retrieve_body( $stats_res ), true );
                $stats = $sbody['timeseries_stats'][0]['timeseries'][0] ?? [];
            }

            $spend           = ( $stats['spend'] ?? 0 ) / 1000000;
            $impressions     = (int) ( $stats['impressions'] ?? 0 );
            $swipe_ups       = (int) ( $stats['swipes'] ?? 0 );
            $conversions     = (int) ( $stats['conversion_purchases'] ?? 0 );
            $cpm             = $impressions > 0 ? round( $spend / $impressions * 1000, 2 ) : 0;
            $engagement_rate = $impressions > 0 ? round( $swipe_ups / $impressions * 100, 2 ) : 0;

            $rows[] = compact( 'cid', 'status', 'spend', 'impressions', 'swipe_ups',
                'conversions', 'cpm', 'engagement_rate', 'start', 'end' );
            $rows[ array_key_last( $rows ) ]['campaign_id']   = $cid;
            $rows[ array_key_last( $rows ) ]['campaign_name'] = $cname;
            $rows[ array_key_last( $rows ) ]['date_start']    = $start;
            $rows[ array_key_last( $rows ) ]['date_stop']     = $end;
        }

        return $rows ?: self::mock_data( $days );
    }

    private static function mock_data( int $days ) : array {
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end   = gmdate( 'Y-m-d' );

        $campaigns = [
            [ 'id' => 'snap_001', 'name' => 'Rezponz – Gen Z Awareness',   'status' => 'ACTIVE' ],
            [ 'id' => 'snap_002', 'name' => 'Rezponz – AR Filter Campaign', 'status' => 'ACTIVE' ],
            [ 'id' => 'snap_003', 'name' => 'Rezponz – Snap Retargeting',  'status' => 'PAUSED' ],
        ];

        $rows = [];
        foreach ( $campaigns as $c ) {
            $spend           = round( wp_rand( 500, 3500 ) + wp_rand( 0, 99 ) / 100, 2 );
            $impressions     = wp_rand( 30000, 330000 );
            $swipe_ups       = (int) ( $impressions * ( wp_rand( 10, 30 ) / 1000 ) );
            $conversions     = (int) ( $swipe_ups * ( wp_rand( 5, 15 ) / 100 ) );
            $cpm             = $impressions > 0 ? round( $spend / $impressions * 1000, 2 ) : 0;
            $engagement_rate = $impressions > 0 ? round( $swipe_ups / $impressions * 100, 2 ) : 0;

            $rows[] = [
                'campaign_id'     => $c['id'],
                'campaign_name'   => $c['name'],
                'status'          => $c['status'],
                'spend'           => $spend,
                'impressions'     => $impressions,
                'swipe_ups'       => $swipe_ups,
                'conversions'     => $conversions,
                'cpm'             => $cpm,
                'engagement_rate' => $engagement_rate,
                'date_start'      => $start,
                'date_stop'       => $end,
            ];
        }
        return $rows;
    }
}
