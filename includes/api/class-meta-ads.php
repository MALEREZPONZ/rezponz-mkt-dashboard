<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Meta_Ads {

    const API_BASE = 'https://graph.facebook.com/v19.0';

    public static function fetch( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            return []; // Ikke konfigureret – vis ingen data
        }

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];
        $end        = gmdate( 'Y-m-d' );
        $start      = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        // Brug /insights endpoint med level=campaign — time_range virker IKKE på nested insights{} felter
        $url = self::API_BASE . '/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token'   => $token,
            'fields'         => 'campaign_id,campaign_name,spend,impressions,reach,clicks,cpm,cpc',
            'time_range'     => wp_json_encode( [ 'since' => $start, 'until' => $end ] ),
            'level'          => 'campaign',
            'limit'          => 100,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 30 ] );

        if ( is_wp_error( $res ) ) {
            return []; // API fejl – vis ingen data
        }

        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        // Token udløbet eller API-fejl
        if ( ! empty( $body['error'] ) ) {
            return [ '__error' => $body['error']['message'] ?? 'Token fejl' ];
        }

        // Hent kampagne-status separat (insights API returnerer ikke status)
        $status_map = self::fetch_campaign_statuses( $token, $account_id );

        $rows = [];
        foreach ( $body['data'] ?? [] as $ins ) {
            $cid = $ins['campaign_id'] ?? '';
            $rows[] = [
                'campaign_id'   => $cid,
                'campaign_name' => $ins['campaign_name'] ?? '',
                'status'        => $status_map[ $cid ] ?? 'UNKNOWN',
                'spend'         => (float) ( $ins['spend']       ?? 0 ),
                'impressions'   => (int)   ( $ins['impressions'] ?? 0 ),
                'reach'         => (int)   ( $ins['reach']       ?? 0 ),
                'clicks'        => (int)   ( $ins['clicks']      ?? 0 ),
                'cpm'           => (float) ( $ins['cpm']         ?? 0 ),
                'cpc'           => (float) ( $ins['cpc']         ?? 0 ),
                'roas'          => 0.0,
                'date_start'    => $start,
                'date_stop'     => $end,
            ];
        }

        return $rows;
    }

    /**
     * Henter kampagne-status (ACTIVE/PAUSED) som et id→status map.
     * Bruges fordi /insights endpoint ikke returnerer status.
     */
    private static function fetch_campaign_statuses( string $token, string $account_id ) : array {
        $url = self::API_BASE . '/act_' . $account_id . '/campaigns?' . http_build_query( [
            'access_token' => $token,
            'fields'       => 'id,status',
            'limit'        => 200,
        ] );
        $res  = wp_remote_get( $url, [ 'timeout' => 15 ] );
        $body = is_wp_error( $res ) ? [] : json_decode( wp_remote_retrieve_body( $res ), true );
        $map  = [];
        foreach ( $body['data'] ?? [] as $c ) {
            $map[ $c['id'] ] = $c['status'] ?? 'UNKNOWN';
        }
        return $map;
    }

    /**
     * Henter månedlig forbrug for de sidste X måneder.
     * Bruger Meta Insights API med time_increment=monthly.
     */
    public static function fetch_monthly( int $months = 6 ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            return [];
        }

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];
        $end        = gmdate( 'Y-m-d' );
        $start      = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );

        $url = self::API_BASE . '/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token'   => $token,
            'fields'         => 'spend,impressions,clicks',
            'time_increment' => 'monthly',
            'time_range'     => wp_json_encode( [ 'since' => $start, 'until' => $end ] ),
            'level'          => 'account',
            'limit'          => 50,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $res ) ) return [];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return [];

        $rows = [];
        foreach ( $body['data'] ?? [] as $item ) {
            $rows[] = [
                'month'       => substr( $item['date_start'] ?? '', 0, 7 ), // YYYY-MM
                'spend'       => (float) ( $item['spend']       ?? 0 ),
                'impressions' => (int)   ( $item['impressions'] ?? 0 ),
                'clicks'      => (int)   ( $item['clicks']      ?? 0 ),
            ];
        }
        return $rows;
    }

    /**
     * Henter alle annoncer (ads) i én kampagne med kreativ information.
     * Bruges til annonce-preview-modal i dashboard.
     */
    public static function fetch_campaign_ads( string $campaign_id ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) ) return [];

        $token = $opts['meta_access_token'];

        $url = self::API_BASE . '/' . $campaign_id . '/ads?' . http_build_query( [
            'access_token' => $token,
            'fields'       => 'id,name,creative{id,name,thumbnail_url,image_url,video_id,body,title,call_to_action_type}',
            'limit'        => 20,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $res ) ) return [];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return [];

        $ads = [];
        foreach ( $body['data'] ?? [] as $ad ) {
            $creative = $ad['creative'] ?? [];
            $ads[] = [
                'ad_id'         => $ad['id'],
                'ad_name'       => $ad['name'] ?? '',
                'creative_id'   => $creative['id'] ?? '',
                'title'         => $creative['title'] ?? '',
                'body'          => $creative['body'] ?? '',
                'thumbnail_url' => $creative['thumbnail_url'] ?? '',
                'image_url'     => $creative['image_url'] ?? '',
                'video_id'      => $creative['video_id'] ?? '',
                'has_video'     => ! empty( $creative['video_id'] ),
                'cta'           => $creative['call_to_action_type'] ?? '',
            ];
        }
        return $ads;
    }

    /**
     * Henter HTML-preview (iframe) for én enkelt annonce via Meta Ad Preview API.
     * Returnerer rå iframe HTML-streng klar til at vise i modal.
     */
    public static function fetch_ad_preview( string $ad_id ) : string {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) ) return '';

        $token = $opts['meta_access_token'];

        $url = self::API_BASE . '/' . $ad_id . '/previews?' . http_build_query( [
            'access_token' => $token,
            'ad_format'    => 'MOBILE_FEED_STANDARD',
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $res ) ) return '';

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return '';

        // Meta returnerer HTML-entiteter — decode dem til rå HTML
        return html_entity_decode( $body['data'][0]['body'] ?? '', ENT_QUOTES, 'UTF-8' );
    }

    private static function mock_data( int $days ) : array {
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end   = gmdate( 'Y-m-d' );

        $campaigns = [
            [ 'id' => 'meta_001', 'name' => 'Rezponz – Brand Awareness Q1',         'status' => 'ACTIVE' ],
            [ 'id' => 'meta_002', 'name' => 'Rezponz – Lead Gen Retargeting',        'status' => 'ACTIVE' ],
            [ 'id' => 'meta_003', 'name' => 'Rezponz – Product Launch Feb',          'status' => 'PAUSED' ],
            [ 'id' => 'meta_004', 'name' => 'Rezponz – B2B Decision Makers',         'status' => 'ACTIVE' ],
            [ 'id' => 'meta_005', 'name' => 'Rezponz – Lookalike Converters',        'status' => 'ACTIVE' ],
        ];

        $rows = [];
        foreach ( $campaigns as $c ) {
            $spend       = round( wp_rand( 1000, 9000 ) + wp_rand( 0, 99 ) / 100, 2 );
            $impressions = wp_rand( 50000, 550000 );
            $reach       = (int) ( $impressions * 0.7 );
            $clicks      = (int) ( $impressions * ( wp_rand( 5, 20 ) / 1000 ) );
            $cpm         = $impressions > 0 ? round( $spend / $impressions * 1000, 2 ) : 0;
            $cpc         = $clicks > 0 ? round( $spend / $clicks, 2 ) : 0;
            $roas        = round( wp_rand( 150, 450 ) / 100, 2 );

            $rows[] = [
                'campaign_id'   => $c['id'],
                'campaign_name' => $c['name'],
                'status'        => $c['status'],
                'spend'         => $spend,
                'impressions'   => $impressions,
                'reach'         => $reach,
                'clicks'        => $clicks,
                'cpm'           => $cpm,
                'cpc'           => $cpc,
                'roas'          => $roas,
                'date_start'    => $start,
                'date_stop'     => $end,
            ];
        }
        return $rows;
    }
}
