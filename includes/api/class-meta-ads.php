<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Meta_Ads {

    const API_BASE = 'https://graph.facebook.com/v21.0';

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
            'fields'       => 'id,name,effective_status,created_time,creative{id,name,thumbnail_url,image_url,video_id,body,title,call_to_action_type,link_url,object_story_spec},insights.date_preset(last_30d){reach,impressions,spend,clicks}',
            'limit'        => 50,
        ] );

        $res = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $res ) ) return [ '__error' => $res->get_error_message() ];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['error'] ) ) {
            return [ '__error' => $body['error']['message'] ?? 'Meta API fejl' ];
        }

        $ads = [];
        foreach ( $body['data'] ?? [] as $ad ) {
            $creative = $ad['creative'] ?? [];
            $insights = $ad['insights']['data'][0] ?? ( $ad['insights']['summary'] ?? [] );

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

            // Beregn antal dage annoncen har kørt
            $days_active = 0;
            if ( ! empty( $ad['created_time'] ) ) {
                $created = strtotime( $ad['created_time'] );
                $days_active = $created ? (int) floor( ( time() - $created ) / DAY_IN_SECONDS ) : 0;
            }

            $ads[] = [
                'ad_id'         => $ad['id'],
                'ad_name'       => $ad['name'] ?? '',
                'status'        => $ad['effective_status'] ?? 'UNKNOWN',
                'days_active'   => $days_active,
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
    /**
     * Henter rigtige transaktioner fra Meta Ads API (ikke insights-aggregater).
     * Returnerer transaction_id, dato, beløb, betalingsstatus, momsfaktura-ID og download-link.
     *
     * @param string $since  Dato format YYYY-MM-DD (default: 1 år bagud)
     * @param string $until  Dato format YYYY-MM-DD (default: i dag)
     */
    public static function fetch_invoices( string $since = '', string $until = '' ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) return [];

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];

        if ( ! $since ) $since = gmdate( 'Y-m-d', strtotime( '-12 months' ) );
        if ( ! $until ) $until = gmdate( 'Y-m-d' );

        // ── Trin 1: Hent transaktioner fra /transactions endpoint ─────────────
        $tx_fields = 'id,created_time,amount,currency,status,vat_invoice_id,payment_option,billing_reason';

        // Forsøg A: med time_range filter
        $url_a = self::API_BASE . '/act_' . $account_id . '/transactions?'
            . 'access_token=' . rawurlencode( $token )
            . '&fields=' . $tx_fields
            . '&time_range=' . rawurlencode( wp_json_encode( [ 'since' => $since, 'until' => $until ] ) )
            . '&limit=200';

        $res  = wp_remote_get( $url_a, [ 'timeout' => 20 ] );
        $body = is_wp_error( $res ) ? [] : json_decode( wp_remote_retrieve_body( $res ), true );

        // Forsøg B: uden time_range (filtrer PHP-side) – virker på konti der ikke støtter time_range
        if ( empty( $body['data'] ) ) {
            $url_b = self::API_BASE . '/act_' . $account_id . '/transactions?'
                . 'access_token=' . rawurlencode( $token )
                . '&fields=' . $tx_fields
                . '&limit=200';
            $res2  = wp_remote_get( $url_b, [ 'timeout' => 20 ] );
            if ( ! is_wp_error( $res2 ) ) {
                $body2 = json_decode( wp_remote_retrieve_body( $res2 ), true );
                if ( ! empty( $body2['data'] ) ) {
                    $body = $body2;
                }
            }
        }

        // Fallback til insights hvis begge transactions-kald fejler
        if ( empty( $body['data'] ) ) {
            $rows = self::fetch_invoices_from_insights( $token, $account_id, $since, $until );
            if ( is_array( $rows ) && ! isset( $rows['error'] ) ) {
                foreach ( $rows as &$row ) { $row['_source'] = 'spend_fallback'; }
                unset( $row );
            }
            return $rows;
        }

        $since_ts = strtotime( $since );
        $until_ts = strtotime( $until ) + 86399; // inkluder hele slutdagen

        $rows = [];
        foreach ( $body['data'] as $t ) {
            $created = substr( $t['created_time'] ?? '', 0, 10 );
            $ts      = strtotime( $created );
            // Filtrer dato PHP-side hvis time_range ikke blev brugt
            if ( $ts < $since_ts || $ts > $until_ts ) continue;

            $invoice_id  = $t['vat_invoice_id'] ?? '';
            $download_url = $invoice_id
                ? 'https://business.facebook.com/ads/ads_invoice/download/?account_id=' . $account_id
                    . '&invoice_id=' . rawurlencode( $invoice_id )
                : 'https://business.facebook.com/billing_hub/payment_activity';

            $rows[] = [
                'transaction_id' => $t['id'] ?? '',
                'date'           => $created,
                'month'          => substr( $created, 0, 7 ),
                'amount'         => round( (float) ( $t['amount'] ?? 0 ), 2 ),
                'currency'       => strtoupper( $t['currency'] ?? 'DKK' ),
                'status'         => $t['status'] ?? 'SETTLED',
                'invoice_id'     => $invoice_id,
                'payment_option' => $t['payment_option'] ?? '',
                'billing_reason' => $t['billing_reason'] ?? '',
                'download_url'   => $download_url,
            ];
        }

        // Sortér nyeste først
        usort( $rows, fn($a,$b) => strcmp($b['date'], $a['date']) );
        return $rows;
    }

    /** Fallback: månedligt forbrug fra insights hvis transactions-endpoint fejler */
    private static function fetch_invoices_from_insights( string $token, string $account_id, string $since, string $until ) : array {
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
            $month = substr( $t['date_start'] ?? '', 0, 7 );
            $rows[] = [
                'transaction_id' => '',
                'date'           => $t['date_start'] ?? '',
                'month'          => $month,
                'amount'         => round( (float) ( $t['spend'] ?? 0 ), 2 ),
                'currency'       => strtoupper( $t['account_currency'] ?? 'DKK' ),
                'status'         => 'SETTLED',
                'invoice_id'     => '',
                'payment_option' => '',
                'billing_reason' => '',
                'impressions'    => (int) ( $t['impressions'] ?? 0 ),
                'clicks'         => (int) ( $t['clicks'] ?? 0 ),
                'download_url'   => 'https://business.facebook.com/billing_hub/payment_activity',
            ];
        }
        usort( $rows, fn($a,$b) => strcmp($b['date'], $a['date']) );
        return $rows;
    }

    /**
     * Henter ALLE aktive annoncer med creative-data og performance metrics.
     * Bruges til "Top annoncer" og "Alle aktive annoncer" sektionerne.
     */
    public static function fetch_top_ads( int $days = 30 ) : array {
        // Udvid PHP-timeout så de to API-kald kan nå at gennemføres
        @set_time_limit( 60 );

        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['meta_access_token'] ) || empty( $opts['meta_ad_account_id'] ) ) {
            self::$last_error = 'Meta Ads er ikke konfigureret (mangler access token eller konto-ID)';
            return [];
        }

        $token      = $opts['meta_access_token'];
        $account_id = $opts['meta_ad_account_id'];

        // Brug date_preset – Meta accepterer denne format pålideligt.
        // time_range med JSON-encoded streng URL-encodes forkert af http_build_query.
        $preset_map = [ 7 => 'last_7d', 30 => 'last_30d', 90 => 'last_90d' ];
        $date_preset = $preset_map[ $days ] ?? 'last_30d';

        // ── Trin 1: Hent performance-metrics via /insights endpoint ──────────
        $insights_url = self::API_BASE . '/act_' . $account_id . '/insights?' . http_build_query( [
            'access_token' => $token,
            'level'        => 'ad',
            'date_preset'  => $date_preset,
            'fields'       => 'ad_id,ad_name,reach,impressions,spend,clicks,cpc,cpm',
            'sort'         => 'reach_descending',
            'limit'        => 25,
        ] );

        $res = wp_remote_get( $insights_url, [ 'timeout' => 12 ] );
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
                'access_token' => $token,
                'fields'       => 'id,name,effective_status,creative{id,name,thumbnail_url,image_url,video_id,object_story_spec{link_data{picture,image_url,child_attachments{picture}},video_data{image_url}}}',
                'limit'        => 25,
            ] );
            $fb_res = wp_remote_get( $fallback_url, [ 'timeout' => 10 ] );
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
        // Inkluder adlabels og picture til video-thumbnails
        $batch_url = self::API_BASE . '?' . http_build_query( [
            'access_token' => $token,
            'ids'          => implode( ',', $ad_ids ),
            'fields'       => 'id,name,effective_status,created_time,creative{id,name,thumbnail_url,image_url,video_id,body,title,picture,object_story_spec{link_data{picture,image_url,message,child_attachments{picture}},video_data{image_url,message},photo_data{images{original{uri}},caption}}}',
        ] );

        $res2 = wp_remote_get( $batch_url, [ 'timeout' => 12 ] );
        if ( ! is_wp_error( $res2 ) ) {
            $body2 = json_decode( wp_remote_retrieve_body( $res2 ), true );
            if ( ! empty( $body2 ) && empty( $body2['error'] ) ) {
                $ads_data = $body2;
            }
        }

        // ── Trin 2b: Video-thumbnail fallback – hent thumbnail via video_id ──
        // Meta returnerer ikke altid thumbnail_url for video-creatives i batch
        $video_thumbs = [];
        foreach ( $ad_ids as $ad_id ) {
            $creative = $ads_data[ $ad_id ]['creative'] ?? [];
            $video_id = $creative['video_id'] ?? '';
            $has_img  = ! empty( $creative['thumbnail_url'] )
                     || ! empty( $creative['image_url'] )
                     || ! empty( $creative['picture'] )
                     || ! empty( $creative['object_story_spec']['link_data']['picture'] )
                     || ! empty( $creative['object_story_spec']['video_data']['image_url'] );
            if ( $video_id && ! $has_img ) {
                $video_thumbs[ $ad_id ] = $video_id;
            }
        }
        if ( $video_thumbs ) {
            // Batch-hent thumbnails for video-IDs via /{video_id}?fields=thumbnails
            $vid_ids = array_unique( array_values( $video_thumbs ) );
            $vid_url = self::API_BASE . '?' . http_build_query( [
                'access_token' => $token,
                'ids'          => implode( ',', $vid_ids ),
                'fields'       => 'thumbnails{uri,width}',
            ] );
            $vres = wp_remote_get( $vid_url, [ 'timeout' => 8 ] );
            if ( ! is_wp_error( $vres ) ) {
                $vbody = json_decode( wp_remote_retrieve_body( $vres ), true );
                foreach ( $video_thumbs as $ad_id => $vid_id ) {
                    $thumbs = $vbody[ $vid_id ]['thumbnails']['data'] ?? [];
                    if ( $thumbs ) {
                        // Vælg den bredeste thumbnail
                        usort( $thumbs, fn($a,$b) => ( $b['width'] ?? 0 ) - ( $a['width'] ?? 0 ) );
                        $ads_data[ $ad_id ]['_video_thumb'] = $thumbs[0]['uri'] ?? '';
                    }
                }
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
                ?: ( $creative['thumbnail_url']                   ?? '' )
                ?: ( $creative['picture']                         ?? '' )
                ?: ( $creative['image_url']                       ?? '' )
                ?: ( $ad['_video_thumb']                          ?? '' );

            // Ad body copy (til kortbeskrivelse)
            $body_copy = $spec['link_data']['message']   ?? ''
                      ?: ( $spec['video_data']['message']    ?? '' )
                      ?: ( $spec['photo_data']['caption']    ?? '' )
                      ?: ( $creative['body']                 ?? '' );

            $reach       = (int)   ( $m['reach']       ?? 0 );
            $impressions = (int)   ( $m['impressions'] ?? 0 );
            $clicks      = (int)   ( $m['clicks']      ?? 0 );
            $spend       = round( (float) ( $m['spend'] ?? 0 ), 2 );

            $days_active = 0;
            if ( ! empty( $ad['created_time'] ) ) {
                $created = strtotime( $ad['created_time'] );
                $days_active = $created ? (int) floor( ( time() - $created ) / DAY_IN_SECONDS ) : 0;
            }

            $rows[] = [
                'ad_id'         => $ad_id,
                'account_id'    => $account_id,
                'ad_name'       => $m['ad_name']              ?? ( $ad['name'] ?? '' ),
                'status'        => $ad['effective_status']    ?? 'ACTIVE',
                'creative_id'   => $creative['id']            ?? '',
                'thumbnail_url' => $img,
                'image_url'     => $img,
                'has_video'     => $format === 'video',
                'format'        => $format,
                'body_copy'     => $body_copy,
                'days_active'   => $days_active,
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
