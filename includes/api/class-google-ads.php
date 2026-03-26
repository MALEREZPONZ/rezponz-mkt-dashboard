<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Google Ads API integration.
 * Bruger Google Ads Query Language (GAQL) via REST API v18.
 * Auth: OAuth 2.0 med scope https://www.googleapis.com/auth/adwords
 */
class RZPA_Google_Ads {

    const API_BASE = 'https://googleads.googleapis.com/v18';

    public static $last_error = null;

    // ── Token ────────────────────────────────────────────────────────────────

    private static function get_access_token( array $opts ) : string {
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

    private static function headers( string $token, array $opts ) : array {
        return [
            'Authorization'   => 'Bearer ' . $token,
            'developer-token' => $opts['google_ads_developer_token'] ?? '',
            'Content-Type'    => 'application/json',
        ];
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

        $url = self::API_BASE . "/customers/{$cid}/googleAds:searchStream";
        $res = wp_remote_post( $url, [
            'timeout' => 30,
            'headers' => self::headers( $token, $opts ),
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );

        if ( is_wp_error( $res ) ) {
            self::$last_error = $res->get_error_message();
            return [];
        }

        $http = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $http !== 200 ) {
            $details = $body[0]['error']['details'][0]['errors'][0]['message']
                    ?? $body[0]['error']['message']
                    ?? 'HTTP ' . $http;
            self::$last_error = $details;
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

        $url = self::API_BASE . "/customers/{$cid}/googleAds:searchStream";
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

    // ── Fakturaer / Billing ───────────────────────────────────────────────────

    public static function fetch_invoices() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_ads_refresh_token'] ) || empty( $opts['google_ads_customer_id'] ) || empty( $opts['google_ads_developer_token'] ) ) return [];

        $token = self::get_access_token( $opts );
        if ( ! $token ) return [ 'error' => self::$last_error ?: 'Token fejl' ];

        $cid = self::customer_id( $opts );

        // Hent månedlige forbrug som billing-erstatning (invoice API kræver billing setup ID)
        $query = "SELECT segments.month, metrics.cost_micros
                  FROM customer
                  WHERE segments.date DURING LAST_12_MONTHS
                  ORDER BY segments.month DESC";

        $url = self::API_BASE . "/customers/{$cid}/googleAds:searchStream";
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
                $rows[$month] = [
                    'month'    => $month,
                    'amount'   => round( (float) ( $r['metrics']['costMicros'] ?? 0 ) / 1_000_000, 2 ),
                    'currency' => 'DKK',
                    'status'   => 'SETTLED',
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

        $cid   = self::customer_id( $opts );
        $query = "SELECT campaign.id, campaign.name FROM campaign WHERE campaign.status = 'ENABLED' LIMIT 3";
        $url   = self::API_BASE . "/customers/{$cid}/googleAds:searchStream";

        $res  = wp_remote_post( $url, [
            'timeout' => 15,
            'headers' => self::headers( $token, $opts ),
            'body'    => wp_json_encode( [ 'query' => $query ] ),
        ] );

        $http = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        $err  = $body[0]['error']['message'] ?? null;
        $rows = 0;
        foreach ( (array) $body as $chunk ) { $rows += count( $chunk['results'] ?? [] ); }

        return [ 'step' => 'api', 'http_code' => $http, 'customer_id' => $cid, 'campaigns_found' => $rows, 'error' => $err ];
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
