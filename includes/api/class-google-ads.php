<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Google Ads API integration.
 * Bruger Google Ads Query Language (GAQL) via REST API.
 * Auth: OAuth 2.0 med scope https://www.googleapis.com/auth/adwords
 *
 * API-version opdateres automatisk: prøver v19, v18, v17 indtil én virker.
 */
class RZPA_Google_Ads {

    const API_VERSIONS = [ 'v19', 'v18', 'v17' ];

    public static $last_error = null;

    /**
     * Finder den aktive API-version (cached i transient).
     */
    private static function api_base() : string {
        $cached = get_transient( 'rzpa_gads_api_version' );
        if ( $cached ) return 'https://googleads.googleapis.com/' . $cached;

        // Prøv hver version med en simpel OPTIONS/HEAD – vi bruger den første der ikke giver 404
        $opts  = get_option( 'rzpa_settings', [] );
        $token = self::get_access_token( $opts );
        $cid   = self::customer_id( $opts );

        if ( $token && $cid ) {
            foreach ( self::API_VERSIONS as $ver ) {
                $url = "https://googleads.googleapis.com/{$ver}/customers/{$cid}/googleAds:searchStream";
                $res = wp_remote_post( $url, [
                    'timeout' => 10,
                    'headers' => self::headers( $token, $opts ),
                    'body'    => wp_json_encode( [ 'query' => "SELECT campaign.id FROM campaign LIMIT 1" ] ),
                ] );
                $http = wp_remote_retrieve_response_code( $res );
                // 200 = OK, 400 = bad query (men version virker), 403 = auth issue (version virker)
                if ( $http && $http !== 404 ) {
                    set_transient( 'rzpa_gads_api_version', $ver, DAY_IN_SECONDS );
                    return "https://googleads.googleapis.com/{$ver}";
                }
            }
        }

        // Fallback til nyeste
        return 'https://googleads.googleapis.com/' . self::API_VERSIONS[0];
    }

    // ── Token ────────────────────────────────────────────────────────────────

    public static function get_access_token( array $opts ) : string {
        $res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $opts['google_ads_client_id'] ?? $opts['google_client_id'] ?? '',
                'client_secret' => $opts['google_ads_client_secret'] ?? $opts['google_client_secret'] ?? '',
                'refresh_token' => $opts['google_ads_refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ] );
        if ( is_wp_error( $res ) ) {
            self::$last_error = 'Netværksfejl: ' . $res->get_error_message();
            return '';
        }
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $body['access_token'] ) ) {
            self::$last_error = $body['error_description'] ?? 'Kunne ikke hente access token';
            return '';
        }
        return $body['access_token'];
    }

    public static function headers( string $token, array $opts ) : array {
        $h = [
            'Authorization'   => 'Bearer ' . $token,
            'developer-token' => $opts['google_ads_developer_token'] ?? '',
            'Content-Type'    => 'application/json',
        ];
        // MCC: login-customer-id er påkrævet når man tilgår kundekonti via en managerkonto
        $mcc = preg_replace( '/[^0-9]/', '', $opts['google_ads_manager_id'] ?? '' );
        if ( $mcc ) {
            $h['login-customer-id'] = $mcc;
        }
        return $h;
    }

    private static function customer_id( array $opts ) : string {
        // Fjern bindestreger: 123-456-7890 → 1234567890
        return preg_replace( '/[^0-9]/', '', $opts['google_ads_customer_id'] ?? '' );
    }

    // ── Kampagner ─────────────────────────────────────────────────────────────

    public static function fetch( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) || empty( $opts['google_ads_developer_token'] ) ) {
            self::$last_error = 'Google Ads er ikke konfigureret';
            return [];
        }

        $token = self::get_access_token( $opts );
        if ( ! $token ) return [];

        $cid   = self::customer_id( $opts );
        $end   = gmdate( 'Y-m-d' );
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $query = "SELECT campaign.id, campaign.name, campaign.status,
                    metrics.cost_micros, metrics.impressions, metrics.clicks,
                    metrics.conversions, metrics.ctr, metrics.average_cpc
                  FROM campaign
                  WHERE segments.date BETWEEN '{$start}' AND '{$end}'
                    AND campaign.status != 'REMOVED'
                  ORDER BY metrics.cost_micros DESC
                  LIMIT 100";

        $url     = self::api_base() . "/customers/{$cid}/googleAds:searchStream";
        $headers = self::headers( $token, $opts );
        $payload = wp_json_encode( [ 'query' => $query ] );

        $res = wp_remote_post( $url, [ 'timeout' => 30, 'headers' => $headers, 'body' => $payload ] );

        if ( is_wp_error( $res ) ) {
            self::$last_error = $res->get_error_message();
            return [];
        }

        $http = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        // ── Fallback: hvis 404 med MCC, prøv uden login-customer-id ────────────
        $mcc = preg_replace( '/[^0-9]/', '', $opts['google_ads_manager_id'] ?? '' );
        if ( $http === 404 && $mcc ) {
            $headers_direct = $headers;
            unset( $headers_direct['login-customer-id'] );
            $res2  = wp_remote_post( $url, [ 'timeout' => 30, 'headers' => $headers_direct, 'body' => $payload ] );
            $http2 = wp_remote_retrieve_response_code( $res2 );
            if ( ! is_wp_error( $res2 ) && $http2 === 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $res2 ), true );
                $http = 200;
            }
        }

        if ( $http !== 200 ) {
            $details = $body[0]['error']['details'][0]['errors'][0]['message']
                    ?? $body[0]['error']['message']
                    ?? $body['error']['message']
                    ?? 'HTTP ' . $http;
            $extra = " [CID:{$cid}" . ($mcc ? " MCC:{$mcc}" : '') . " HTTP:{$http}]";

            // ── Ved 404: hent tilgængelige konti og vis dem i fejlbeskeden ──────
            if ( $http === 404 ) {
                delete_transient( 'rzpa_gads_api_version' );
                $api_ver    = get_transient( 'rzpa_gads_api_version' ) ?: self::API_VERSIONS[0];
                $list_res   = wp_remote_get(
                    "https://googleads.googleapis.com/{$api_ver}/customers:listAccessibleCustomers",
                    [ 'timeout' => 10, 'headers' => [
                        'Authorization'   => 'Bearer ' . $token,
                        'developer-token' => $opts['google_ads_developer_token'] ?? '',
                    ] ]
                );
                if ( ! is_wp_error( $list_res ) ) {
                    $list_body   = json_decode( wp_remote_retrieve_body( $list_res ), true );
                    $accessible  = array_map(
                        fn($rn) => preg_replace( '/^customers\//', '', $rn ),
                        $list_body['resourceNames'] ?? []
                    );
                    if ( ! empty( $accessible ) ) {
                        $extra .= ' | Tilgængelige konti: ' . implode( ', ', $accessible );
                        $extra .= ' | Prøv at sætte Customer ID til en af disse';
                    } else {
                        $extra .= ' | Ingen tilgængelige konti fundet – tjek OAuth scopes';
                    }
                }
            }

            self::$last_error = $details . $extra;
            return [];
        }

        $rows = [];
        foreach ( $body as $chunk ) {
            foreach ( $chunk['results'] ?? [] as $r ) {
                $c   = $r['campaign'];
                $m   = $r['metrics'];
                $spend       = round( (float) ( $m['costMicros'] ?? 0 ) / 1_000_000, 2 );
                $impressions = (int) ( $m['impressions'] ?? 0 );
                $clicks      = (int) ( $m['clicks'] ?? 0 );
                $rows[] = [
                    'campaign_id'   => $c['id'] ?? '',
                    'campaign_name' => $c['name'] ?? '',
                    'status'        => self::status_label( $c['status'] ?? '' ),
                    'spend'         => $spend,
                    'impressions'   => $impressions,
                    'clicks'        => $clicks,
                    'conversions'   => round( (float) ( $m['conversions'] ?? 0 ), 2 ),
                    'cpm'           => $impressions > 0 ? round( $spend / $impressions * 1000, 2 ) : 0,
                    'cpc'           => round( (float) ( $m['averageCpc'] ?? 0 ) / 1_000_000, 2 ),
                    'ctr'           => round( (float) ( $m['ctr'] ?? 0 ) * 100, 2 ),
                    'date_start'    => $start,
                    'date_stop'     => $end,
                ];
            }
        }
        return $rows;
    }

    // ── Månedlig data ────────────────────────────────────────────────────────

    public static function fetch_monthly( int $months = 6 ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) || empty( $opts['google_ads_developer_token'] ) ) return [];

        $token = self::get_access_token( $opts );
        if ( ! $token ) return [];

        $cid   = self::customer_id( $opts );
        $start = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );
        $end   = gmdate( 'Y-m-d' );

        $query = "SELECT segments.month, metrics.cost_micros, metrics.impressions, metrics.clicks
                  FROM campaign
                  WHERE segments.date BETWEEN '{$start}' AND '{$end}'
                  ORDER BY segments.month";

        $url = self::api_base() . "/customers/{$cid}/googleAds:searchStream";
        $res = wp_remote_post( $url, [
            'timeout' => 20,
            'headers' => self::headers( $token, $opts ),
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );

        if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) return [];

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        $agg  = []; // month → [spend, impressions, clicks]
        foreach ( $body as $chunk ) {
            foreach ( $chunk['results'] ?? [] as $r ) {
                $month = substr( $r['segments']['month'] ?? '', 0, 7 );
                if ( ! $month ) continue;
                if ( ! isset( $agg[$month] ) ) $agg[$month] = [ 'month' => $month, 'spend' => 0, 'impressions' => 0, 'clicks' => 0 ];
                $agg[$month]['spend']       += round( (float) ( $r['metrics']['costMicros'] ?? 0 ) / 1_000_000, 2 );
                $agg[$month]['impressions'] += (int) ( $r['metrics']['impressions'] ?? 0 );
                $agg[$month]['clicks']      += (int) ( $r['metrics']['clicks'] ?? 0 );
            }
        }
        ksort( $agg );
        return array_values( $agg );
    }

    // ── Aktive annoncer (RSA/ETA) ─────────────────────────────────────────────

    public static function fetch_ads() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) || empty( $opts['google_ads_developer_token'] ) ) {
            return [];
        }

        $token = self::get_access_token( $opts );
        if ( ! $token ) return [ 'error' => self::$last_error ?: 'Token fejl' ];

        $cid = self::customer_id( $opts );
        $url = self::api_base() . "/customers/{$cid}/googleAds:searchStream";

        $query = "SELECT
            ad_group_ad.ad.id,
            ad_group_ad.ad.type,
            ad_group_ad.ad.name,
            ad_group_ad.ad.responsive_search_ad.headlines,
            ad_group_ad.ad.responsive_search_ad.descriptions,
            ad_group_ad.ad.expanded_text_ad.headline_part1,
            ad_group_ad.ad.expanded_text_ad.headline_part2,
            ad_group_ad.ad.expanded_text_ad.description,
            ad_group_ad.ad.final_urls,
            ad_group_ad.status,
            campaign.name,
            ad_group.name,
            metrics.impressions,
            metrics.clicks,
            metrics.cost_micros,
            metrics.ctr
        FROM ad_group_ad
        WHERE ad_group_ad.status = 'ENABLED'
          AND campaign.status = 'ENABLED'
          AND ad_group.status = 'ENABLED'
          AND segments.date DURING LAST_30_DAYS
        ORDER BY metrics.impressions DESC
        LIMIT 50";

        $res = wp_remote_post( $url, [
            'timeout' => 20,
            'headers' => self::headers( $token, $opts ),
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );

        if ( is_wp_error( $res ) ) return [ 'error' => $res->get_error_message() ];
        $http = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $http !== 200 ) return [ 'error' => $body[0]['error']['message'] ?? 'HTTP ' . $http ];

        $ads = [];
        foreach ( $body as $chunk ) {
            foreach ( $chunk['results'] ?? [] as $result ) {
                $ad      = $result['ad_group_ad']['ad'] ?? [];
                $metrics = $result['metrics'] ?? [];
                $rsa     = $ad['responsiveSearchAd'] ?? [];
                $eta     = $ad['expandedTextAd'] ?? [];

                $headlines = array_values( array_filter( array_map(
                    fn( $h ) => $h['text'] ?? '',
                    array_slice( $rsa['headlines'] ?? [], 0, 3 )
                ) ) );
                if ( empty( $headlines ) ) {
                    $headlines = array_filter( [ $eta['headlinePart1'] ?? '', $eta['headlinePart2'] ?? '' ] );
                }

                $descriptions = array_values( array_filter( array_map(
                    fn( $d ) => $d['text'] ?? '',
                    array_slice( $rsa['descriptions'] ?? [], 0, 2 )
                ) ) );
                if ( empty( $descriptions ) && ! empty( $eta['description'] ) ) {
                    $descriptions = [ $eta['description'] ];
                }

                $ads[] = [
                    'ad_id'        => $ad['id'] ?? '',
                    'type'         => $ad['type'] ?? 'RESPONSIVE_SEARCH_AD',
                    'campaign'     => $result['campaign']['name'] ?? '',
                    'ad_group'     => $result['adGroup']['name'] ?? '',
                    'headlines'    => array_values( $headlines ),
                    'descriptions' => array_values( $descriptions ),
                    'final_url'    => $ad['finalUrls'][0] ?? '',
                    'impressions'  => (int) ( $metrics['impressions'] ?? 0 ),
                    'clicks'       => (int) ( $metrics['clicks'] ?? 0 ),
                    'spend'        => round( (float) ( $metrics['costMicros'] ?? 0 ) / 1_000_000, 2 ),
                    'ctr'          => round( (float) ( $metrics['ctr'] ?? 0 ) * 100, 2 ),
                ];
            }
        }
        return $ads;
    }

    // ── Fakturaer / Billing ───────────────────────────────────────────────────

    public static function fetch_invoices() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) || empty( $opts['google_ads_developer_token'] ) ) return [];

        $token = self::get_access_token( $opts );
        if ( ! $token ) return [ 'error' => self::$last_error ?: 'Token fejl' ];

        $cid = self::customer_id( $opts );

        // Hent månedlige forbrug de seneste 36 måneder op til dags dato
        $since = gmdate( 'Y-m-d', strtotime( '-36 months' ) );
        $until = gmdate( 'Y-m-d' );
        $query = "SELECT segments.month, metrics.cost_micros
                  FROM customer
                  WHERE segments.date >= '{$since}'
                    AND segments.date <= '{$until}'
                  ORDER BY segments.month DESC";

        $url = self::api_base() . "/customers/{$cid}/googleAds:searchStream";
        $res = wp_remote_post( $url, [
            'timeout' => 20,
            'headers' => self::headers( $token, $opts ),
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );

        if ( is_wp_error( $res ) ) return [ 'error' => $res->get_error_message() ];
        $http = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $http !== 200 ) {
            return [ 'error' => $body[0]['error']['message'] ?? 'HTTP ' . $http ];
        }

        $rows = [];
        foreach ( $body as $chunk ) {
            foreach ( $chunk['results'] ?? [] as $r ) {
                $month = substr( $r['segments']['month'] ?? '', 0, 7 );
                if ( ! $month ) continue;
                $end_of_month = gmdate( 'Y-m-d', strtotime( 'last day of ' . $month ) );
                $rows[$month] = [
                    'month'       => $month,
                    'amount'      => round( (float) ( $r['metrics']['costMicros'] ?? 0 ) / 1_000_000, 2 ),
                    'currency'    => 'DKK',
                    'impressions' => 0,
                    'clicks'      => 0,
                    'status'      => 'SETTLED',
                    'billing_url' => 'https://ads.google.com/aw/billing/invoices?__e=' . $month . '-01',
                ];
            }
        }
        krsort( $rows );
        return array_values( $rows );
    }

    // ── Debug ────────────────────────────────────────────────────────────────

    public static function test_connection() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) )    return [ 'step' => 'config', 'error' => 'Mangler refresh token' ];
        if ( empty( $opts['google_ads_customer_id'] ) )       return [ 'step' => 'config', 'error' => 'Mangler Customer ID' ];
        if ( empty( $opts['google_ads_developer_token'] ) )   return [ 'step' => 'config', 'error' => 'Mangler Developer Token' ];

        $token = self::get_access_token( $opts );
        if ( ! $token ) return [ 'step' => 'token', 'error' => self::$last_error ];

        $cid     = self::customer_id( $opts );
        $mcc     = preg_replace( '/[^0-9]/', '', $opts['google_ads_manager_id'] ?? '' );
        $api_ver = get_transient( 'rzpa_gads_api_version' ) ?: self::API_VERSIONS[0];

        // ── Trin 1: List accessible customers ───────────────────────────────
        $accessible = [];
        $list_url  = "https://googleads.googleapis.com/{$api_ver}/customers:listAccessibleCustomers";
        $list_res  = wp_remote_get( $list_url, [
            'timeout' => 10,
            'headers' => [
                'Authorization'   => 'Bearer ' . $token,
                'developer-token' => $opts['google_ads_developer_token'] ?? '',
            ],
        ] );
        if ( ! is_wp_error( $list_res ) ) {
            $list_body = json_decode( wp_remote_retrieve_body( $list_res ), true );
            foreach ( $list_body['resourceNames'] ?? [] as $rn ) {
                // resourceName format: "customers/1234567890"
                $accessible[] = preg_replace( '/^customers\//', '', $rn );
            }
        }

        // ── Trin 2: Test med MCC ─────────────────────────────────────────────
        $query = "SELECT campaign.id, campaign.name FROM campaign LIMIT 3";
        $url   = "https://googleads.googleapis.com/{$api_ver}/customers/{$cid}/googleAds:searchStream";

        $headers = self::headers( $token, $opts );
        $res     = wp_remote_post( $url, [
            'timeout' => 15,
            'headers' => $headers,
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );
        $http = wp_remote_retrieve_response_code( $res );
        $raw  = wp_remote_retrieve_body( $res );
        $body = json_decode( $raw, true );

        // ── Trin 3: Fallback uden MCC ────────────────────────────────────────
        $used_mcc     = true;
        $http_no_mcc  = null;
        if ( $http !== 200 && $mcc ) {
            $hdrs_direct = $headers;
            unset( $hdrs_direct['login-customer-id'] );
            $res2  = wp_remote_post( $url, [
                'timeout' => 15,
                'headers' => $hdrs_direct,
                'body'    => wp_json_encode( [ 'query' => $query ] ),
            ] );
            $http_no_mcc = wp_remote_retrieve_response_code( $res2 );
            if ( $http_no_mcc === 200 ) {
                $body     = json_decode( wp_remote_retrieve_body( $res2 ), true );
                $http     = 200;
                $used_mcc = false;
            }
        }

        $google_msg    = $body[0]['error']['details'][0]['errors'][0]['message']
                      ?? $body[0]['error']['message']
                      ?? $body['error']['message']
                      ?? null;
        $google_status = $body[0]['error']['status'] ?? $body['error']['status'] ?? null;
        $err           = $google_msg ?? ( $http !== 200 ? "HTTP {$http}" : null );

        $rows = 0;
        foreach ( (array) $body as $chunk ) { $rows += count( $chunk['results'] ?? [] ); }

        return [
            'step'              => 'api',
            'http_code'         => $http,
            'http_no_mcc'       => $http_no_mcc,
            'customer_id'       => $cid,
            'manager_id'        => $mcc ?: null,
            'used_mcc'          => $used_mcc,
            'api_version'       => $api_ver,
            'campaigns_found'   => $rows,
            'error'             => $err,
            'google_status'     => $google_status,
            'accessible_accounts' => $accessible,
            'raw_snippet'       => substr( $raw, 0, 600 ),
        ];
    }

    // ── Hjælpefunktioner ─────────────────────────────────────────────────────

    private static function status_label( string $s ) : string {
        return match ( $s ) {
            'ENABLED'  => 'ACTIVE',
            'PAUSED'   => 'PAUSED',
            'REMOVED'  => 'REMOVED',
            default    => $s,
        };
    }
}
