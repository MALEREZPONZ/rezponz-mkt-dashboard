<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Google_SEO {

    public static function fetch( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['google_client_id'] ) || empty( $opts['google_refresh_token'] ) ) {
            return self::mock_data( $days );
        }

        // Get access token via refresh token
        $token_res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $opts['google_client_id'],
                'client_secret' => $opts['google_client_secret'],
                'refresh_token' => $opts['google_refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ] );

        if ( is_wp_error( $token_res ) ) {
            return self::mock_data( $days );
        }

        $token_data   = json_decode( wp_remote_retrieve_body( $token_res ), true );
        $access_token = $token_data['access_token'] ?? '';

        if ( ! $access_token ) {
            return self::mock_data( $days );
        }

        $site_url  = $opts['google_site_url'] ?? 'https://rezponz.dk';
        $end_date  = gmdate( 'Y-m-d' );
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
            return self::mock_data( $days );
        }

        $body = json_decode( wp_remote_retrieve_body( $api_res ), true );
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

        return $rows ?: self::mock_data( $days );
    }

    public static function fetch_pages( int $days = 30 ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( empty( $opts['google_client_id'] ) || empty( $opts['google_refresh_token'] ) ) {
            return self::mock_pages( $days );
        }

        $token_res = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $opts['google_client_id'],
                'client_secret' => $opts['google_client_secret'],
                'refresh_token' => $opts['google_refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ] );
        if ( is_wp_error( $token_res ) ) return self::mock_pages( $days );

        $token_data   = json_decode( wp_remote_retrieve_body( $token_res ), true );
        $access_token = $token_data['access_token'] ?? '';
        if ( ! $access_token ) return self::mock_pages( $days );

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
        if ( is_wp_error( $api_res ) ) return self::mock_pages( $days );

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
        return $rows ?: self::mock_pages( $days );
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
