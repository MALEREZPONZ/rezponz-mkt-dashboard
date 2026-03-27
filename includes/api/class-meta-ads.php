<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Meta_Ads {

    const API_BASE = 'https://graph.facebook.com/v19.0';

    public static $last_error = null;

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
            'fields'       => 'id,name,effective_status,creative{id,name,thumbnail_url,image_url,video_id,body,title,call_to_action_type,link_url,object_story_spec},insights.summary{reach,impressions,spend,clicks}',
            'effective_status' => '["ACTIVE"]',
            'limit'        => 50,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $res ) ) return [];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return [];

        $ads = [];
        foreach ( $body['data'] ?? [] as $ad ) {
            $creative = $ad['creative'] ?? [];
            $insights = $ad['insights']['summary'] ?? [];

            // Detect ad format
            $format = 'image';
            if ( ! empty( $creative['video_id'] ) ) {
                $format = 'video';
            } elseif ( ! empty( $creative['object_story_spec']['link_data']['child_attachments'] ) ) {
                $format = 'carousel';
            }

            $link_url = $creative['link_url'] ?? '';
            if ( ! $link_url ) {
                $link_url = $creative['object_story_spec']['link_data']['link'] ?? '';
            }

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
                'link_url'      => $link_url,
                'format'        => $format,
                'reach'         => (int) ( $insights['reach'] ?? 0 ),
                'impressions'   => (int) ( $insights['impressions'] ?? 0 ),
                'spend'         => (float) ( $insights['spend'] ?? 0 ),
                'clicks'        => (int) ( $insights['clicks'] ?? 0 ),
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

    /**
     * Henter betalingshistorik fra Meta Ads.
     * Bruger /insights med monthly breakdown – virker for alle annoncekonto-typer.
     */
    public static function fetch_invoices() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) return [];

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];

        // Hent månedligt forbrug via insights — op til 36 måneder bagud og frem til i dag
        $since = gmdate( 'Y-m-d', strtotime( '-36 months' ) );
        $until = gmdate( 'Y-m-d' ); // altid dags dato

        $url = self::API_BASE . '/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token'   => $token,
            'fields'         => 'spend,impressions,clicks,account_currency',
            'time_increment' => 'monthly',
            'time_range'     => wp_json_encode( [ 'since' => $since, 'until' => $until ] ),
            'limit'          => 100,
        ] );

        $res  = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $res ) ) return [ 'error' => $res->get_error_message() ];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return [ 'error' => $body['error']['message'] ?? 'API fejl' ];

        $rows = [];
        foreach ( $body['data'] ?? [] as $t ) {
            $start = $t['date_start'] ?? '';
            $month = substr( $start, 0, 7 ); // YYYY-MM
            // Link til Meta Business billing-side for den specifikke måned
            $end_of_month = gmdate( 'Y-m-d', strtotime( 'last day of ' . $month ) );
            $billing_url  = 'https://business.facebook.com/billing_hub/payment_activity'
                . '?start_date=' . $month . '-01&end_date=' . $end_of_month;

            $rows[] = [
                'month'       => $month,
                'date'        => $start,
                'amount'      => round( (float) ( $t['spend'] ?? 0 ), 2 ),
                'currency'    => strtoupper( $t['account_currency'] ?? 'DKK' ),
                'impressions' => (int) ( $t['impressions'] ?? 0 ),
                'clicks'      => (int) ( $t['clicks'] ?? 0 ),
                'status'      => 'SETTLED',
                'billing_url' => $billing_url,
            ];
        }
        // Sortér nyeste først
        usort( $rows, fn($a,$b) => strcmp($b['date'], $a['date']) );
        return $rows;
    }

    /**
     * Henter ALLE aktive annoncer med creative-data og performance metrics.
     * Bruges til "Top annoncer" og "Alle aktive annoncer" sektionerne.
     */
    public static function fetch_top_ads( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            self::$last_error = 'Meta Ads er ikke konfigureret (mangler access token eller konto-ID)';
            return [];
        }

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];
        $since      = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $until      = gmdate( 'Y-m-d' );

        // ── Trin 1: Hent performance-metrics via /insights endpoint ──────────
        // Bruger dedikeret insights-endpoint i stedet for field expansion (mere pålidelig)
        $insights_url = self::API_BASE . '/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token' => $token,
            'level'        => 'ad',
            'time_range'   => wp_json_encode( [ 'since' => $since, 'until' => $until ] ),
            'fields'       => 'ad_id,ad_name,reach,impressions,spend,clicks,cpc,cpm',
            'sort'         => 'reach_descending',
            'limit'        => 25,
        ] );

        $res = wp_remote_get( $insights_url, [ 'timeout' => 30 ] );
        if ( is_wp_error( $res ) ) {
            self::$last_error = $res->get_error_message();
            return [];
        }

        $http = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( ! empty( $body['error'] ) ) {
            self::$last_error = $body['error']['message'] ?? "Meta API fejl (HTTP {$http})";
            return [];
        }

        if ( empty( $body['data'] ) ) {
            // Fallback: Hent annoncer direkte fra /ads endpoint (viser paused/arkiverede med 0-metrics)
            $fallback_url = self::API_BASE . '/act_' . $account_id . '/ads?' . http_build_query( [
                'access_token'     => $token,
                'fields'           => 'id,name,effective_status,creative{id,name,thumbnail_url,image_url,video_id,object_story_spec{link_data{picture,image_url,child_attachments{picture}},video_data{image_url}}}',
                'effective_status' => '["ACTIVE","PAUSED"]',
                'limit'            => 25,
            ] );
            $fb_res = wp_remote_get( $fallback_url, [ 'timeout' => 30 ] );
            if ( ! is_wp_error( $fb_res ) ) {
                $fb_body = json_decode( wp_remote_retrieve_body( $fb_res ), true );
                if ( ! empty( $fb_body['data'] ) ) {
                    $rows = [];
                    foreach ( $fb_body['data'] as $ad ) {
                        $creative = $ad['creative'] ?? [];
                        $spec     = $creative['object_story_spec'] ?? [];
                        $format   = 'image';
                        if ( ! empty( $creative['video_id'] ) || isset( $spec['video_data'] ) ) $format = 'video';
                        elseif ( isset( $spec['link_data']['child_attachments'] ) ) $format = 'carousel';
                        $img = $spec['link_data']['picture'] ?? ''
                            ?: ( $spec['link_data']['image_url'] ?? '' )
                            ?: ( $spec['video_data']['image_url'] ?? '' )
                            ?: ( $creative['image_url'] ?? '' )
                            ?: ( $creative['thumbnail_url'] ?? '' );
                        $rows[] = [
                            'ad_id'         => $ad['id'],
                            'ad_name'       => $ad['name'] ?? '',
                            'status'        => $ad['effective_status'] ?? 'PAUSED',
                            'creative_id'   => $creative['id'] ?? '',
                            'thumbnail_url' => $img,
                            'image_url'     => $img,
                            'has_video'     => $format === 'video',
                            'format'        => $format,
                            'reach'         => 0,
                            'impressions'   => 0,
                            'spend'         => 0.0,
                            'clicks'        => 0,
                            'cpc'           => 0.0,
                            'cpm'           => 0.0,
                            'ctr'           => 0.0,
                        ];
                    }
                    return $rows;
                }
            }
            return [];
        }

        // Byg metrics-map: ad_id → row
        $metrics = [];
        foreach ( $body['data'] as $row ) {
            if ( ! empty( $row['ad_id'] ) ) {
                $metrics[ $row['ad_id'] ] = $row;
            }
        }
        if ( empty( $metrics ) ) return [];

        // ── Trin 2: Hent creative-data for disse annoncer ────────────────────
        $ad_ids    = array_keys( $metrics );
        $ads_data  = [];
        $batch_url = self::API_BASE . '?' . http_build_query( [
            'access_token' => $token,
            'ids'          => implode( ',', $ad_ids ),
            'fields'       => 'id,name,effective_status,creative{id,name,thumbnail_url,image_url,video_id,object_story_spec{link_data{picture,image_url,child_attachments{picture}},video_data{image_url},photo_data{images{original{uri}}}}}',
        ] );

        $res2 = wp_remote_get( $batch_url, [ 'timeout' => 30 ] );
        if ( ! is_wp_error( $res2 ) ) {
            $body2 = json_decode( wp_remote_retrieve_body( $res2 ), true );
            if ( ! empty( $body2 ) && empty( $body2['error'] ) ) {
                $ads_data = $body2;
            }
        }

        // ── Trin 3: Join metrics + creative og byg output ────────────────────
        $rows = [];
        foreach ( $ad_ids as $ad_id ) {
            $m        = $metrics[ $ad_id ];
            $ad       = $ads_data[ $ad_id ] ?? [];
            $creative = $ad['creative'] ?? [];
            $spec     = $creative['object_story_spec'] ?? [];

            $format = 'image';
            if ( ! empty( $creative['video_id'] ) || isset( $spec['video_data'] ) ) {
                $format = 'video';
            } elseif ( isset( $spec['link_data']['child_attachments'] ) ) {
                $format = 'carousel';
            }

            $img = $spec['link_data']['picture']                  ?? ''
                ?: ( $spec['link_data']['image_url']              ?? '' )
                ?: ( $spec['video_data']['image_url']             ?? '' )
                ?: ( $spec['photo_data']['images']['original']['uri'] ?? '' )
                ?: ( $creative['image_url']                       ?? '' )
                ?: ( $creative['thumbnail_url']                   ?? '' );

            $reach       = (int)   ( $m['reach']       ?? 0 );
            $impressions = (int)   ( $m['impressions'] ?? 0 );
            $clicks      = (int)   ( $m['clicks']      ?? 0 );
            $spend       = round( (float) ( $m['spend'] ?? 0 ), 2 );

            $rows[] = [
                'ad_id'         => $ad_id,
                'ad_name'       => $m['ad_name']              ?? ( $ad['name'] ?? '' ),
                'status'        => $ad['effective_status']    ?? 'ACTIVE',
                'creative_id'   => $creative['id']            ?? '',
                'thumbnail_url' => $img,
                'image_url'     => $img,
                'has_video'     => $format === 'video',
                'format'        => $format,
                'reach'         => $reach,
                'impressions'   => $impressions,
                'spend'         => $spend,
                'clicks'        => $clicks,
                'cpc'           => round( (float) ( $m['cpc'] ?? 0 ), 2 ),
                'cpm'           => round( (float) ( $m['cpm'] ?? 0 ), 2 ),
                'ctr'           => $impressions > 0 ? round( $clicks / $impressions * 100, 2 ) : 0,
            ];
        }

        return $rows;
    }

    /** Alias for backward compatibility. */
    public static function fetch_ad_insights( int $days = 30 ) : array {
        return self::fetch_top_ads( $days );
    }

    /**
     * Henter unikke landing pages fra aktive annoncer.
     * Bruges til "Landing Pages" sektionen i dashboard.
     */
    public static function fetch_landing_pages() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            return [];
        }

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];

        $url = self::API_BASE . '/act_' . $account_id . '/ads?' . http_build_query( [
            'access_token'     => $token,
            'fields'           => 'creative{link_url,object_story_spec{link_data{link}}},effective_status',
            'effective_status' => '["ACTIVE","PAUSED","ARCHIVED"]',
            'limit'            => 200,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 30 ] );
        if ( is_wp_error( $res ) ) return [];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) return [];

        $url_data = [];
        foreach ( $body['data'] ?? [] as $ad ) {
            $creative = $ad['creative'] ?? [];
            $link     = $creative['link_url'] ?? '';
            if ( ! $link ) {
                $link = $creative['object_story_spec']['link_data']['link'] ?? '';
            }
            if ( ! $link ) continue;
            // Strip query params for grouping, but keep the original URL
            $clean = strtok( $link, '?' );
            if ( ! isset( $url_data[ $clean ] ) ) {
                $url_data[ $clean ] = [ 'url' => $clean, 'ad_count' => 0, 'active' => 0 ];
            }
            $url_data[ $clean ]['ad_count']++;
            if ( ( $ad['effective_status'] ?? '' ) === 'ACTIVE' ) {
                $url_data[ $clean ]['active']++;
            }
        }

        $rows = [];
        foreach ( $url_data as $clean => $info ) {
            $parsed = wp_parse_url( $clean );
            $rows[] = [
                'url'        => $clean,
                'ad_count'   => $info['ad_count'],
                'active_ads' => $info['active'],
                'domain'     => $parsed['host'] ?? '',
            ];
        }

        // Sortér efter antal annoncer (flest først)
        usort( $rows, fn( $a, $b ) => $b['ad_count'] - $a['ad_count'] );

        return $rows;
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
