<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Google_SEO {

    /** Seneste fejlbesked fra fetch() – bruges af seo_sync til at vise fejl i UI */
    public static $last_error = null;

    /** Henter access token – returnerer token-streng eller sætter $last_error */
    private static function get_access_token( array $opts ) : string {
        $token_res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $opts['google_client_id'],
                'client_secret' => $opts['google_client_secret'] ?? '',
                'refresh_token' => $opts['google_refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ] );
        if ( is_wp_error( $token_res ) ) {
            self::$last_error = 'Netværksfejl: ' . $token_res->get_error_message();
            return '';
        }
        $body = json_decode( wp_remote_retrieve_body( $token_res ), true );
        if ( empty( $body['access_token'] ) ) {
            self::$last_error = $body['error_description'] ?? ( $body['error'] ?? 'Kunne ikke hente access token' );
            return '';
        }
        return $body['access_token'];
    }

    /** Debug: test forbindelsen og returnér rå API-respons */
    public static function test_connection() : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['google_client_id'] ) || empty( $opts['google_refresh_token'] ) ) {
            return [ 'step' => 'config', 'error' => 'Mangler client_id eller refresh_token i indstillinger' ];
        }
        $token_res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $opts['google_client_id'],
                'client_secret' => $opts['google_client_secret'] ?? '',
                'refresh_token' => $opts['google_refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ] );
        if ( is_wp_error( $token_res ) ) {
            return [ 'step' => 'token', 'error' => $token_res->get_error_message() ];
        }
        $token_body = json_decode( wp_remote_retrieve_body( $token_res ), true );
        if ( empty( $token_body['access_token'] ) ) {
            return [ 'step' => 'token', 'error' => $token_body['error_description'] ?? 'Ingen access_token', 'raw' => $token_body ];
        }
        $site_url = $opts['google_site_url'] ?? 'https://rezponz.dk';
        $api_res = wp_remote_post(
            'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
            [
                'headers' => [ 'Authorization' => 'Bearer ' . $token_body['access_token'], 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [
                    'startDate'  => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
                    'endDate'    => gmdate( 'Y-m-d' ),
                    'dimensions' => [ 'query' ],
                    'rowLimit'   => 5,
                ] ),
            ]
        );
        $api_body  = json_decode( wp_remote_retrieve_body( $api_res ), true );
        $http_code = wp_remote_retrieve_response_code( $api_res );
        return [
            'step'      => 'api',
            'http_code' => $http_code,
            'site_url'  => $site_url,
            'rows'      => count( $api_body['rows'] ?? [] ),
            'error'     => $api_body['error']['message'] ?? null,
            'raw'       => $api_body,
        ];
    }

    public static function fetch( int $days = 30 ) : array {
        self::$last_error = null;
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['google_client_id'] ) || empty( $opts['google_refresh_token'] ) ) {
            self::$last_error = 'Google Search Console er ikke forbundet — gå til Indstillinger og klik "Forbind".';
            return [];
        }

        $access_token = self::get_access_token( $opts );
        if ( ! $access_token ) {
            return []; // $last_error sat af get_access_token()
        }

        $site_url   = $opts['google_site_url'] ?? 'https://rezponz.dk';
        $end_date   = gmdate( 'Y-m-d' );
        $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $api_res = wp_remote_post(
            'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'startDate'  => $start_date,
                    'endDate'    => $end_date,
                    'dimensions' => [ 'query', 'date' ],
                    'rowLimit'   => 500,
                ] ),
            ]
        );

        if ( is_wp_error( $api_res ) ) {
            self::$last_error = 'Netværksfejl mod Google API: ' . $api_res->get_error_message();
            return [];
        }

        $body      = json_decode( wp_remote_retrieve_body( $api_res ), true );
        $http_code = wp_remote_retrieve_response_code( $api_res );

        if ( ! empty( $body['error'] ) ) {
            $msg = $body['error']['message'] ?? 'Ukendt API-fejl';
            self::$last_error = "Google API fejl ({$http_code}): {$msg} — Site URL: {$site_url}";
            return [];
        }

        $rows = [];
        foreach ( ( $body['rows'] ?? [] ) as $row ) {
            $rows[] = [
                'date'        => $row['keys'][1],
                'keyword'     => $row['keys'][0],
                'position'    => round( $row['position'], 1 ),
                'clicks'      => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
                'ctr'         => round( $row['ctr'] * 100, 2 ),
            ];
        }

        return $rows;
    }

    public static function fetch_pages( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['google_client_id'] ) || empty( $opts['google_refresh_token'] ) ) {
            return [];
        }

        $access_token = self::get_access_token( $opts );
        if ( ! $access_token ) return [];

        $site_url   = $opts['google_site_url'] ?? 'https://rezponz.dk';
        $end_date   = gmdate( 'Y-m-d' );
        $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $api_res = wp_remote_post(
            'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'startDate'  => $start_date,
                    'endDate'    => $end_date,
                    'dimensions' => [ 'page', 'date' ],
                    'rowLimit'   => 500,
                ] ),
            ]
        );
        if ( is_wp_error( $api_res ) ) return [];

        $body = json_decode( wp_remote_retrieve_body( $api_res ), true );
        $rows = [];
        foreach ( ( $body['rows'] ?? [] ) as $row ) {
            $rows[] = [
                'date'        => $row['keys'][1],
                'page_url'    => $row['keys'][0],
                'position'    => round( $row['position'], 1 ),
                'clicks'      => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
                'ctr'         => round( $row['ctr'] * 100, 2 ),
            ];
        }
        return $rows;
    }

    private static function mock_pages( int $days ) : array {
        $pages = [
            '/','forside' => '/',
            '/om-os',
            '/services',
            '/services/kundeservice',
            '/services/email-marketing',
            '/services/social-media',
            '/kontakt',
            '/blog',
            '/blog/marketing-automation',
            '/blog/crm-guide',
            '/priser',
            '/case-studies',
        ];
        $pages = array_values( $pages );

        $rows = [];
        for ( $d = 0; $d < min( $days, 30 ); $d++ ) {
            $date = gmdate( 'Y-m-d', strtotime( "-{$d} days" ) );
            foreach ( $pages as $i => $url ) {
                if ( wp_rand( 0, 9 ) > 3 ) {
                    $rows[] = [
                        'date'        => $date,
                        'page_url'    => 'https://rezponz.dk' . $url,
                        'position'    => round( max( 1, 2 + $i * 0.7 + ( wp_rand( 0, 30 ) / 10 ) ), 1 ),
                        'clicks'      => wp_rand( 10, 400 ) - $i * 15,
                        'impressions' => wp_rand( 200, 3000 ) - $i * 100,
                        'ctr'         => round( wp_rand( 50, 1200 ) / 100, 2 ),
                    ];
                }
            }
        }
        return $rows;
    }

    private static function mock_data( int $days ) : array {
        $keywords = [
            'rezponz', 'marketing automation', 'lead generation', 'crm software',
            'email marketing', 'digital marketing', 'marketing platform', 'automation tool',
            'b2b marketing', 'sales automation', 'customer engagement', 'rezponz.dk',
            'marketing dashboard', 'ad performance', 'roi tracking', 'campaign management',
            'social media ads', 'google ads', 'facebook ads', 'tiktok marketing',
        ];

        $rows = [];
        for ( $d = 0; $d < min( $days, 30 ); $d++ ) {
            $date = gmdate( 'Y-m-d', strtotime( "-{$d} days" ) );
            foreach ( $keywords as $i => $kw ) {
                if ( wp_rand( 0, 9 ) > 3 ) {
                    $rows[] = [
                        'date'        => $date,
                        'keyword'     => $kw,
                        'position'    => round( max( 1, $i * 0.8 + ( wp_rand( 0, 50 ) / 10 ) ), 1 ),
                        'clicks'      => wp_rand( 5, 150 ) + ( $i < 5 ? 50 : 0 ),
                        'impressions' => wp_rand( 100, 2000 ) + ( $i < 5 ? 500 : 0 ),
                        'ctr'         => round( wp_rand( 0, 1500 ) / 100, 2 ),
                    ];
                }
            }
        }
        return $rows;
    }
}
