<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_PDF_Generator {

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Generate a server-side PDF and stream it directly to the browser as a download.
     * Uses DomPDF (vendor/autoload.php must be loaded).
     */
    public static function download( int $days = 30, string $title = '' ): void {
        // Bump limits for heavy PDF generation
        @ini_set( 'memory_limit', '512M' );
        @set_time_limit( 120 );

        if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
            http_response_code( 500 );
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [ 'error' => 'PDF-bibliotek (DomPDF) mangler – vendor/autoload.php ikke indlæst.' ] );
            exit();
        }

        try {
            $html = self::get_html( $days, $title );

            $options = new \Dompdf\Options();
            $options->set( 'isHtml5ParserEnabled', false );
            $options->set( 'isRemoteEnabled', false );
            $options->set( 'defaultFont', 'Helvetica' );
            $options->set( 'isFontSubsettingEnabled', true );

            // Writable font/temp dirs — avoids crash on read-only plugin dir
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rzpa_dompdf';
            if ( ! file_exists( $tmp ) ) {
                @mkdir( $tmp, 0755, true );
            }
            $options->set( 'fontDir',   $tmp );
            $options->set( 'fontCache', $tmp );
            $options->set( 'tempDir',   sys_get_temp_dir() );
            // Allow reading files from plugin dir (for data-URI images)
            if ( defined( 'RZPA_DIR' ) ) {
                $options->set( 'chroot', RZPA_DIR );
            }

            $dompdf = new \Dompdf\Dompdf( $options );
            $dompdf->loadHtml( $html, 'UTF-8' );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();

            $filename = 'rezponz-rapport-' . date( 'Y-m-d' ) . '.pdf';
            header( 'Content-Type: application/pdf' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
            echo $dompdf->output(); // phpcs:ignore WordPress.Security.EscapeOutput
            exit();

        } catch ( \Throwable $e ) {
            http_response_code( 500 );
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [
                'error' => $e->getMessage(),
                'in'    => basename( $e->getFile() ) . ':' . $e->getLine(),
            ] );
            exit();
        }
    }

    /**
     * Legacy: return HTML wrapped in WP_REST_Response (keeps existing REST route working).
     */
    public static function generate( int $days = 30, string $title = '' ) {
        return new WP_REST_Response( [ 'success' => true, 'html' => self::get_html( $days, $title ), 'title' => $title ] );
    }

    /**
     * Build and return the complete HTML report string.
     */
    public static function get_html( int $days = 30, string $title = '' ): string {
        $seo_kw    = RZPA_Database::get_top_keywords( $days, 10 );
        $seo_sum   = RZPA_Database::get_seo_summary( $days );
        $meta_c    = RZPA_Database::get_meta_campaigns( $days );
        $meta_sum  = RZPA_Database::get_meta_summary( $days );
        $snap_c    = RZPA_Database::get_snap_campaigns( $days );
        $snap_sum  = RZPA_Database::get_snap_summary( $days );
        $tt_c      = RZPA_Database::get_tiktok_campaigns( $days );
        $tt_sum    = RZPA_Database::get_tiktok_summary( $days );
        $ai_sum    = RZPA_Database::get_ai_summary( $days );
        $gads_sum  = method_exists( 'RZPA_Database', 'get_google_ads_summary' )
                     ? RZPA_Database::get_google_ads_summary( $days ) : [];
        $gads_c    = method_exists( 'RZPA_Database', 'get_google_ads_campaigns' )
                     ? array_slice( RZPA_Database::get_google_ads_campaigns( $days ), 0, 5 ) : [];

        $total_spend = (float)( $meta_sum['total_spend'] ?? 0 )
                     + (float)( $snap_sum['total_spend'] ?? 0 )
                     + (float)( $tt_sum['total_spend']   ?? 0 )
                     + (float)( $gads_sum['total_spend'] ?? 0 );

        $recs = self::get_recommendations( compact(
            'days','seo_sum','meta_sum','snap_sum','tt_sum','ai_sum','total_spend','gads_sum'
        ) );

        if ( ! $title ) {
            $title = 'Marketing Rapport – ' . date_i18n( 'F Y' );
        }

        return self::render_html( [
            'title'       => $title,
            'period'      => "Seneste {$days} dage",
            'generated'   => date_i18n( 'd. F Y' ),
            'seo_kw'      => $seo_kw,
            'seo_sum'     => $seo_sum,
            'meta_c'      => array_slice( $meta_c, 0, 5 ),
            'meta_sum'    => $meta_sum,
            'snap_c'      => array_slice( $snap_c, 0, 5 ),
            'snap_sum'    => $snap_sum,
            'tt_c'        => array_slice( $tt_c, 0, 5 ),
            'tt_sum'      => $tt_sum,
            'ai_sum'      => $ai_sum,
            'gads_sum'    => $gads_sum,
            'gads_c'      => $gads_c,
            'recs'        => $recs,
            'total_spend' => $total_spend,
        ] );
    }

    // ── AI recommendations ────────────────────────────────────────────────────

    private static function get_recommendations( array $data ) : array {
        $opts = get_option( 'rzpa_settings', [] );
        if ( ! empty( $opts['openai_api_key'] ) ) {
            $prompt = "Du er marketing analytiker for Rezponz. Baseret på følgende performance data, generér 5 konkrete anbefalinger på dansk:\n\n"
                    . wp_json_encode( $data, JSON_PRETTY_PRINT )
                    . "\n\nFormatér som en nummereret liste. Vær specifik og datadrevet.";
            $res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'headers' => [ 'Authorization' => 'Bearer ' . $opts['openai_api_key'], 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [ 'model' => 'gpt-4.1-mini', 'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ], 'max_tokens' => 600 ] ),
                'timeout' => 30,
            ] );
            if ( ! is_wp_error( $res ) ) {
                $body  = json_decode( wp_remote_retrieve_body( $res ), true );
                $text  = $body['choices'][0]['message']['content'] ?? '';
                $lines = array_filter( explode( "\n", $text ), fn($l) => preg_match( '/^\d+[.)]\s/', $l ) );
                if ( count( $lines ) >= 3 ) return array_values( array_map( fn($l) => preg_replace( '/^\d+[.)]\s*/', '', $l ), $lines ) );
            }
        }
        return [
            'Øg budgettet på Meta kampagner med ROAS over 3x – disse har kapacitet til at skalere med højt afkast.',
            'Fokusér TikTok-indhold på 18–34-årige segmenter, da video engagement er markant højere her.',
            'Optimer top 5 SEO-søgeord på position 4–10 med on-page forbedringer for at rykke til side 1.',
            'Implementer AI Overview-targeting: strukturér content som direkte svar på spørgsmål.',
            'Test Snapchat AR-filter kampagner med produkt-demos – engagement er typisk 2–3x højere.',
        ];
    }

    private static function n( $v, int $d = 0 ) : string {
        if ( $v === null || $v === '' ) return '–';
        return number_format( (float)$v, $d, ',', '.' );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    private static function render_html( array $d ) : string {
        ob_start(); ?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $d['title'] ); ?></title>
<style><?php echo self::get_css(); ?></style>
</head>
<body>

<!-- ══════════════════════════════════════
     FORSIDE
══════════════════════════════════════ -->
<div class="page cover">
    <div class="cover-accent-bar"></div>

    <table class="cover-inner" cellpadding="0" cellspacing="0">
        <tr>
            <td class="cover-left" valign="top">
                <div class="cover-logo"><?php echo self::logo_img(48); ?></div>
                <div class="cover-logo-sub">Marketing Intelligence Platform</div>

                <br><br><br>

                <div class="cover-label">PERFORMANCE RAPPORT</div>
                <div class="cover-title"><?php echo esc_html( $d['title'] ); ?></div>

                <div class="cover-divider"></div>

                <div class="cover-badge"><?php echo esc_html( $d['period'] ); ?></div>
                <div class="cover-date">Genereret <?php echo esc_html( $d['generated'] ); ?></div>
            </td>
            <td class="cover-right" valign="middle" align="center">
                <div class="cover-circle-outer">
                    <div class="cover-circle-inner">
                        <div class="cover-circle-dot">&#9679;</div>
                        <div class="cover-circle-label">DATA<br/>DREVET</div>
                    </div>
                </div>
                <br>
                <?php foreach([
                    ['Ad Spend', self::n($d['total_spend'],0).' kr'],
                    ['Meta ROAS', self::n($d['meta_sum']['avg_roas']??0,2).'x'],
                    ['SEO Klik', self::n($d['seo_sum']['total_clicks']??0)],
                ] as [$l,$v]): ?>
                <div class="cover-mini-kpi">
                    <div class="cover-mini-label"><?php echo esc_html($l); ?></div>
                    <div class="cover-mini-val"><?php echo esc_html($v); ?></div>
                </div>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <div class="cover-footer">
        <?php echo self::logo_img(18); ?>
        &nbsp;&#183;&nbsp;
        Hasseris Bymidte 6 · 9000 Aalborg · info@rezponz.dk · rezponz.dk
    </div>
</div>


<!-- ══════════════════════════════════════
     EXECUTIVE SUMMARY
══════════════════════════════════════ -->
<div class="page content-page">
    <?php self::ph('Executive Summary'); ?>

    <?php self::sh('Executive Summary', "Nøgletal på tværs af alle kanaler · {$d['period']}"); ?>

    <?php self::kpi_start(); ?>
        <?php self::kpi('Samlet Ad Spend', self::n($d['total_spend'],0).' kr', 'Alle kanaler'); ?>
        <?php self::kpi('Meta ROAS', self::n($d['meta_sum']['avg_roas']??0,2).'x', 'Gennemsnit'); ?>
        <?php self::kpi('Organiske klik', self::n($d['seo_sum']['total_clicks']??0), 'SEO'); ?>
        <?php self::kpi('Google Ads CTR', self::n($d['gads_sum']['avg_ctr']??0,2).'%', 'Søgekampagner'); ?>
    <?php self::kpi_end(); ?>

    <div class="channel-list">
        <?php
        $channels = [
            ['Meta Ads',     '#1877F2', 'Spend '.self::n($d['meta_sum']['total_spend']??0,0).' kr · '.self::n($d['meta_sum']['total_impressions']??0).' vis. · ROAS '.self::n($d['meta_sum']['avg_roas']??0,2).'x'],
            ['Google Ads',   '#4285F4', 'Spend '.self::n($d['gads_sum']['total_spend']??0,0).' kr · '.self::n($d['gads_sum']['total_clicks']??0).' klik · CTR '.self::n($d['gads_sum']['avg_ctr']??0,2).'%'],
            ['TikTok Ads',   '#fe2c55', 'Spend '.self::n($d['tt_sum']['total_spend']??0,0).' kr · '.self::n($d['tt_sum']['total_video_views']??0).' videovisninger · ROAS '.self::n($d['tt_sum']['avg_roas']??0,2).'x'],
            ['Snapchat Ads', '#f5a623', 'Spend '.self::n($d['snap_sum']['total_spend']??0,0).' kr · '.self::n($d['snap_sum']['total_swipe_ups']??0).' swipe-ups · '.self::n($d['snap_sum']['avg_engagement_rate']??0,2).'% eng.'],
            ['SEO',          '#16a34a', self::n($d['seo_sum']['total_clicks']??0).' klik · '.self::n($d['seo_sum']['total_impressions']??0).' imp. · CTR '.self::n($d['seo_sum']['avg_ctr']??0,2).'%'],
            ['AI-synlighed', '#7c3aed', ($d['ai_sum']['ai_overview_count']??0).' AI Overviews · '.($d['ai_sum']['featured_snippet_count']??0).' Featured Snippets'],
        ];
        foreach ($channels as [$name,$color,$stats]): ?>
        <table class="channel-row" cellpadding="0" cellspacing="0">
            <tr>
                <td width="12" valign="middle"><span class="channel-dot" style="background:<?php echo $color; ?>">&#160;</span></td>
                <td width="120" valign="middle" class="channel-name"><?php echo esc_html($name); ?></td>
                <td valign="middle" class="channel-stats"><?php echo esc_html($stats); ?></td>
            </tr>
        </table>
        <?php endforeach; ?>
    </div>

    <?php self::pf($d['period'], $d['generated']); ?>
</div>


<!-- ══════════════════════════════════════
     SEO
══════════════════════════════════════ -->
<div class="page content-page">
    <?php self::ph('SEO Performance'); ?>
    <?php self::sh('SEO Performance', 'Google Search Console · rezponz.dk'); ?>

    <?php self::kpi_start(); ?>
        <?php self::kpi('Organiske klik',  self::n($d['seo_sum']['total_clicks']??0)); ?>
        <?php self::kpi('Impressions',     self::n($d['seo_sum']['total_impressions']??0)); ?>
        <?php self::kpi('Gns. CTR',        self::n($d['seo_sum']['avg_ctr']??0,2).'%'); ?>
        <?php self::kpi('Top-10 søgeord',  self::n($d['seo_sum']['keywords_top10']??0)); ?>
    <?php self::kpi_end(); ?>

    <div class="tbl-title">Top søgeord</div>
    <table class="data-tbl">
        <thead><tr><th>Søgeord</th><th>Placering</th><th>Klik</th><th>Impressions</th><th>CTR</th></tr></thead>
        <tbody>
        <?php foreach ($d['seo_kw'] as $k):
            $pos = (float)$k['avg_position'];
            $cls = $pos <= 3 ? 'pos-top' : ($pos <= 10 ? 'pos-mid' : 'pos-low');
        ?>
        <tr>
            <td class="td-main"><?php echo esc_html($k['keyword']); ?></td>
            <td><span class="pos-badge <?php echo $cls; ?>">#<?php echo self::n($pos,1); ?></span></td>
            <td><?php echo self::n($k['total_clicks']); ?></td>
            <td><?php echo self::n($k['total_impressions']); ?></td>
            <td><?php echo self::n($k['avg_ctr'],2); ?>%</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php self::pf($d['period'], $d['generated']); ?>
</div>


<!-- ══════════════════════════════════════
     META ADS
══════════════════════════════════════ -->
<div class="page content-page">
    <?php self::ph('Meta Ads'); ?>
    <?php self::sh('Meta Ads', 'Facebook + Instagram · Marketing API'); ?>

    <?php self::kpi_start(); ?>
        <?php self::kpi('Samlet Spend',  self::n($d['meta_sum']['total_spend']??0,0).' kr'); ?>
        <?php self::kpi('Gns. ROAS',     self::n($d['meta_sum']['avg_roas']??0,2).'x'); ?>
        <?php self::kpi('Impressions',   self::n($d['meta_sum']['total_impressions']??0)); ?>
        <?php self::kpi('Klik',          self::n($d['meta_sum']['total_clicks']??0)); ?>
    <?php self::kpi_end(); ?>

    <div class="tbl-title">Kampagneoversigt</div>
    <table class="data-tbl">
        <thead><tr><th>Kampagne</th><th>Status</th><th>Spend</th><th>Impressions</th><th>Klik</th><th>ROAS</th></tr></thead>
        <tbody>
        <?php foreach ($d['meta_c'] as $c):
            $roas = (float)$c['roas'];
            $rc   = $roas >= 2.5 ? 'roas-good' : ($roas >= 1.5 ? 'roas-mid' : 'roas-low');
            $bs   = $c['status'] === 'ACTIVE' ? 'badge-active' : 'badge-paused';
        ?>
        <tr>
            <td class="td-main"><?php echo esc_html($c['campaign_name']); ?></td>
            <td><span class="<?php echo $bs; ?>"><?php echo esc_html($c['status']); ?></span></td>
            <td><?php echo self::n($c['spend'],0); ?> kr</td>
            <td><?php echo self::n($c['impressions']); ?></td>
            <td><?php echo self::n($c['clicks']); ?></td>
            <td><span class="roas-badge <?php echo $rc; ?>"><?php echo self::n($roas,2); ?>x</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php self::pf($d['period'], $d['generated']); ?>
</div>


<!-- ══════════════════════════════════════
     GOOGLE ADS
══════════════════════════════════════ -->
<div class="page content-page">
    <?php self::ph('Google Ads'); ?>
    <?php self::sh('Google Ads', 'Search & Display · Google Ads API'); ?>

    <?php self::kpi_start(); ?>
        <?php self::kpi('Samlet Spend',   self::n($d['gads_sum']['total_spend']??0,0).' kr'); ?>
        <?php self::kpi('Klik',           self::n($d['gads_sum']['total_clicks']??0)); ?>
        <?php self::kpi('CTR',            self::n($d['gads_sum']['avg_ctr']??0,2).'%'); ?>
        <?php self::kpi('Konverteringer', self::n($d['gads_sum']['total_conversions']??0)); ?>
    <?php self::kpi_end(); ?>

    <?php if (!empty($d['gads_c'])): ?>
    <div class="tbl-title">Kampagneoversigt</div>
    <table class="data-tbl">
        <thead><tr><th>Kampagne</th><th>Status</th><th>Spend</th><th>Visninger</th><th>Klik</th><th>CTR</th></tr></thead>
        <tbody>
        <?php foreach ($d['gads_c'] as $c):
            $bs  = strtoupper($c['status']??'') === 'ACTIVE' ? 'badge-active' : 'badge-paused';
            $ctr = (float)($c['ctr']??0);
            $cc  = $ctr >= 2 ? 'roas-good' : ($ctr >= 1 ? 'roas-mid' : 'roas-low');
        ?>
        <tr>
            <td class="td-main"><?php echo esc_html($c['campaign_name']??''); ?></td>
            <td><span class="<?php echo $bs; ?>"><?php echo esc_html($c['status']??''); ?></span></td>
            <td><?php echo self::n($c['spend']??0,0); ?> kr</td>
            <td><?php echo self::n($c['impressions']??0); ?></td>
            <td><?php echo self::n($c['clicks']??0); ?></td>
            <td><span class="roas-badge <?php echo $cc; ?>"><?php echo self::n($ctr,2); ?>%</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="no-data">Ingen Google Ads data tilgængelig.</p>
    <?php endif; ?>

    <?php self::pf($d['period'], $d['generated']); ?>
</div>


<!-- ══════════════════════════════════════
     SNAPCHAT & TIKTOK
══════════════════════════════════════ -->
<div class="page content-page">
    <?php self::ph('Snapchat & TikTok'); ?>
    <?php self::sh('Snapchat Ads', 'Snapchat Marketing API'); ?>

    <?php self::kpi_start(3); ?>
        <?php self::kpi('Spend',           self::n($d['snap_sum']['total_spend']??0,0).' kr'); ?>
        <?php self::kpi('Swipe-ups',       self::n($d['snap_sum']['total_swipe_ups']??0)); ?>
        <?php self::kpi('Engagement Rate', self::n($d['snap_sum']['avg_engagement_rate']??0,2).'%'); ?>
    <?php self::kpi_end(); ?>

    <?php self::sh('TikTok Ads', 'TikTok for Business API'); ?>

    <?php self::kpi_start(); ?>
        <?php self::kpi('Spend',          self::n($d['tt_sum']['total_spend']??0,0).' kr'); ?>
        <?php self::kpi('Video Views',    self::n($d['tt_sum']['total_video_views']??0)); ?>
        <?php self::kpi('ROAS',           self::n($d['tt_sum']['avg_roas']??0,2).'x'); ?>
        <?php self::kpi('Konverteringer', self::n($d['tt_sum']['total_conversions']??0)); ?>
    <?php self::kpi_end(); ?>

    <?php self::pf($d['period'], $d['generated']); ?>
</div>


<!-- ══════════════════════════════════════
     ANBEFALINGER
══════════════════════════════════════ -->
<div class="page content-page">
    <?php self::ph('Anbefalinger'); ?>
    <?php self::sh('AI-drevne anbefalinger', 'Genereret på baggrund af din performance data'); ?>

    <?php foreach ($d['recs'] as $i => $rec): ?>
    <div class="rec-item">
        <table cellpadding="0" cellspacing="0" width="100%"><tr>
            <td class="rec-num" width="28" valign="top"><?php echo $i+1; ?></td>
            <td class="rec-text" valign="top"><?php echo esc_html($rec); ?></td>
        </tr></table>
    </div>
    <?php endforeach; ?>

    <p class="rec-note">Anbefalingerne er baseret på data fra de seneste <?php echo esc_html($d['period']); ?> og genereret med AI. Kontakt din marketing manager for implementering.</p>

    <?php self::pf($d['period'], $d['generated']); ?>
</div>


<!-- ══════════════════════════════════════
     BAGSIDE
══════════════════════════════════════ -->
<div class="page back-page">
    <div class="back-accent"></div>
    <div class="back-center">
        <div class="back-logo"><?php echo self::logo_img(72); ?></div>
        <div class="back-tag">Marketing Intelligence – Powered by data</div>
        <div class="back-line"></div>
        <div class="back-url">rezponz.dk</div>
        <div class="back-addr">Hasseris Bymidte 6 · 9000 Aalborg · info@rezponz.dk</div>
    </div>
    <div class="back-bar"></div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    // ── Shorthand HTML helpers ────────────────────────────────────────────────

    /** Logo as base64 data-URI img (DomPDF-compatible) */
    private static function logo_img( int $height = 36 ) : string {
        $path = defined('RZPA_DIR') ? RZPA_DIR . 'assets/Rezponz-logo.png' : '';
        if ( $path && file_exists( $path ) ) {
            $b64 = base64_encode( file_get_contents( $path ) );
            return '<img src="data:image/png;base64,' . $b64 . '" height="' . $height . '" alt="rezponz" style="height:' . $height . 'px;width:auto;display:inline-block;vertical-align:middle;border:0">';
        }
        return '<span style="font-weight:900;letter-spacing:-1px">rezponz</span>';
    }

    /** Page header */
    private static function ph( string $section ) : void { ?>
    <div class="page-hdr">
        <table cellpadding="0" cellspacing="0"><tr>
            <td class="page-hdr-logo"><?php echo self::logo_img(22); ?></td>
            <td class="page-hdr-section"><?php echo esc_html($section); ?></td>
        </tr></table>
    </div>
    <?php }

    /** Section heading */
    private static function sh( string $title, string $sub = '' ) : void { ?>
    <div class="section-hdr">
        <span class="section-dot">&#160;</span><span class="section-title"><?php echo esc_html($title); ?></span>
        <?php if ($sub): ?><div class="section-sub"><?php echo esc_html($sub); ?></div><?php endif; ?>
    </div>
    <?php }

    /**
     * Open a KPI row (call kpi() for each card, then kpi_end()).
     * Uses a table so DomPDF can render columns reliably.
     */
    private static function kpi_start( int $cols = 4 ) : void { ?>
    <div class="kpi-row"><table cellpadding="0" cellspacing="8" width="100%"><tr>
    <?php }

    /** KPI card — must be called inside kpi_start()/kpi_end() */
    private static function kpi( string $label, string $value, string $sub = '' ) : void { ?>
    <td><div class="kpi-card">
        <div class="kpi-label"><?php echo esc_html($label); ?></div>
        <div class="kpi-value"><?php echo esc_html($value); ?></div>
        <?php if ($sub): ?><div class="kpi-sub"><?php echo esc_html($sub); ?></div><?php endif; ?>
    </div></td>
    <?php }

    private static function kpi_end() : void { ?>
    </tr></table></div>
    <?php }

    /** Page footer */
    private static function pf( string $period, string $generated ) : void { ?>
    <div class="page-ftr">
        <table cellpadding="0" cellspacing="0" width="100%"><tr>
            <td>rezponz.dk</td>
            <td style="text-align:right"><?php echo esc_html($period); ?> &middot; <?php echo esc_html($generated); ?></td>
        </tr></table>
    </div>
    <?php }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private static function get_css() : string { return '
/* ── RESET & BASE ──────────────────────────────── */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,Helvetica,sans-serif; background:#fff; color:#1a1a1a; font-size:11pt; }

/* ── PAGE ──────────────────────────────────────── */
@page { size:A4 portrait; margin:0; }
.page { width:210mm; page-break-after:always; background:#fff; overflow:hidden; }

/* ══ COVER ══════════════════════════════════════ */
.cover { background:#fff; }
.cover-accent-bar { height:6px; background:#CCFF00; }
.cover-inner { width:100%; padding:48px 52px 32px; }
.cover-left { }
.cover-logo { line-height:1; margin-bottom:2px; }
.cover-logo-sub { font-size:9pt; color:#999; letter-spacing:2px; text-transform:uppercase; margin-top:4px; }
.cover-label { font-size:8pt; letter-spacing:3px; text-transform:uppercase; color:#aaa; margin-top:60px; margin-bottom:10px; }
.cover-title { font-size:24pt; font-weight:900; color:#1a1a1a; line-height:1.15; margin-bottom:20px; }
.cover-divider { width:48px; height:4px; background:#CCFF00; margin-bottom:18px; }
.cover-badge { background:#1a1a1a; color:#CCFF00; padding:6px 18px; font-weight:800; font-size:10pt; margin-bottom:10px; display:inline-block; }
.cover-date { font-size:9pt; color:#999; margin-top:4px; }
.cover-right { width:200px; text-align:center; }
.cover-circle-outer { width:140px; height:140px; border:3px solid #CCFF00; margin:0 auto; padding:10px; }
.cover-circle-inner { width:100%; height:100%; background:#CCFF00; text-align:center; padding:20px 10px; }
.cover-circle-dot { font-size:18pt; color:#1a1a1a; }
.cover-circle-label { font-size:7pt; font-weight:900; letter-spacing:1px; color:#1a1a1a; margin-top:4px; }
.cover-mini-kpi { background:#f8f8f8; border:1px solid #eee; padding:8px 10px; margin-bottom:6px; text-align:left; }
.cover-mini-label { font-size:7.5pt; color:#999; text-transform:uppercase; }
.cover-mini-val { font-size:12pt; font-weight:900; color:#1a1a1a; }
.cover-footer { padding:14px 52px; border-top:1px solid #f0f0f0; font-size:8.5pt; color:#aaa; }
.cover-footer-logo { display:inline-block; vertical-align:middle; }

/* ══ CONTENT PAGE ════════════════════════════════ */
.content-page { background:#fff; }

/* Running header */
.page-hdr { padding:10px 44px; border-bottom:2px solid #CCFF00; background:#fafafa; }
.page-hdr table { width:100%; margin:0; border:none; }
.page-hdr td { border:none; padding:0; }
.page-hdr-logo { vertical-align:middle; }
.page-hdr-section { font-size:8pt; color:#999; text-transform:uppercase; letter-spacing:1.5px; text-align:right; }

/* Section heading */
.section-hdr { padding:18px 44px 12px; border-bottom:1px solid #f0f0f0; margin-bottom:16px; }
.section-dot { display:inline-block; width:10px; height:10px; background:#CCFF00; margin-right:8px; }
.section-title { font-size:16pt; font-weight:900; color:#1a1a1a; vertical-align:middle; }
.section-sub { font-size:8.5pt; color:#999; margin-top:5px; padding-left:18px; }

/* ── KPI ROW ────────────────────────────────────── */
.kpi-row { padding:0 44px 16px; }
.kpi-row table { width:100%; margin:0; border:none; border-collapse:separate; border-spacing:8px; }
.kpi-row td { border:none; padding:0; vertical-align:top; }
.kpi-card { border:1px solid #e5e5e5; border-top:3px solid #CCFF00; padding:12px 14px; background:#fafafa; }
.kpi-label { font-size:7.5pt; text-transform:uppercase; letter-spacing:.8px; color:#999; margin-bottom:6px; }
.kpi-value { font-size:18pt; font-weight:900; color:#1a1a1a; line-height:1; }
.kpi-sub { font-size:7.5pt; color:#bbb; margin-top:4px; }

/* ── DATA TABLE ─────────────────────────────────── */
.tbl-title { font-size:7.5pt; text-transform:uppercase; letter-spacing:1.5px; color:#aaa; padding:0 44px 6px; }
table.data-tbl { width:calc(100% - 88px); margin:0 44px 18px; border-collapse:collapse; font-size:9pt; }
table.data-tbl th { background:#f5f5f5; color:#888; text-transform:uppercase; font-size:7pt; letter-spacing:.8px; padding:8px 10px; text-align:left; border-bottom:2px solid #e5e5e5; }
table.data-tbl td { padding:8px 10px; border-bottom:1px solid #f0f0f0; color:#333; }
table.data-tbl tr:last-child td { border-bottom:none; }
.td-main { font-weight:600; color:#1a1a1a; }

.pos-badge { font-size:8.5pt; font-weight:700; padding:2px 6px; }
.pos-top { background:#eeffcc; color:#3a7a00; }
.pos-mid { background:#dbeafe; color:#1e40af; }
.pos-low { background:#f3f4f6; color:#6b7280; }

.badge-active { background:#dcfce7; color:#16a34a; padding:2px 8px; font-size:7.5pt; font-weight:700; text-transform:uppercase; }
.badge-paused { background:#f3f4f6; color:#9ca3af; padding:2px 8px; font-size:7.5pt; font-weight:700; text-transform:uppercase; }

.roas-badge { font-size:9.5pt; font-weight:800; padding:2px 7px; }
.roas-good { background:#eeffcc; color:#3a7a00; }
.roas-mid  { background:#fef9c3; color:#a16207; }
.roas-low  { background:#fee2e2; color:#dc2626; }

/* ── CHANNEL LIST ───────────────────────────────── */
.channel-list { padding:0 44px 14px; }
.channel-row { width:calc(100% - 88px); margin:0 0 5px; border-collapse:collapse; border:1px solid #f0f0f0; background:#fafafa; }
.channel-row td { padding:8px 10px; border:none; }
.channel-dot { display:inline-block; width:10px; height:10px; }
.channel-name { font-weight:700; font-size:10pt; color:#1a1a1a; }
.channel-stats { font-size:9pt; color:#666; }

/* ── RECOMMENDATIONS ────────────────────────────── */
.rec-item { margin:0 44px 8px; padding:12px 16px; background:#fafafa; border:1px solid #e5e5e5; border-left:4px solid #CCFF00; }
.rec-item table { width:100%; margin:0; border:none; }
.rec-item td { border:none; padding:0; }
.rec-num { width:26px; height:26px; background:#CCFF00; color:#1a1a1a; text-align:center; font-size:9pt; font-weight:900; vertical-align:middle; padding:4px; }
.rec-text { font-size:9.5pt; color:#333; line-height:1.6; padding-left:10px; vertical-align:top; padding-top:3px; }
.rec-note { margin:12px 44px 0; font-size:8pt; color:#bbb; font-style:italic; }

/* ── MISC ────────────────────────────────────────── */
.no-data { margin:0 44px; padding:18px; background:#fafafa; border:1px dashed #e5e5e5; color:#aaa; font-size:10pt; text-align:center; }

/* Running footer */
.page-ftr { padding:8px 44px; border-top:1px solid #f0f0f0; font-size:7.5pt; color:#bbb; background:#fafafa; }
.page-ftr table { width:100%; margin:0; border:none; }
.page-ftr td { border:none; padding:0; }

/* ══ BACKSIDE ════════════════════════════════════ */
.back-page { background:#1a1a1a; min-height:297mm; }
.back-accent { height:6px; background:#CCFF00; }
.back-center { text-align:center; padding:120px 40px 40px; }
.back-logo { line-height:1; margin-bottom:16px; }
.back-tag { font-size:12pt; color:#555; margin-bottom:24px; }
.back-line { width:40px; height:3px; background:#CCFF00; margin:0 auto 24px; }
.back-url { font-size:14pt; font-weight:800; color:#CCFF00; margin-bottom:8px; }
.back-addr { font-size:9pt; color:#444; }
.back-bar { height:6px; background:#CCFF00; margin-top:80px; }
'; }
}
