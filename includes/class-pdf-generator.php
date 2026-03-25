<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_PDF_Generator {

    public static function generate( int $days = 30, string $title = '' ) {
        $seo_kw    = RZPA_Database::get_top_keywords( $days, 10 );
        $seo_sum   = RZPA_Database::get_seo_summary( $days );
        $meta_c    = RZPA_Database::get_meta_campaigns( $days );
        $meta_sum  = RZPA_Database::get_meta_summary( $days );
        $snap_c    = RZPA_Database::get_snap_campaigns( $days );
        $snap_sum  = RZPA_Database::get_snap_summary( $days );
        $tt_c      = RZPA_Database::get_tiktok_campaigns( $days );
        $tt_sum    = RZPA_Database::get_tiktok_summary( $days );
        $ai_sum    = RZPA_Database::get_ai_summary( $days );

        $total_spend = (float) ( $meta_sum['total_spend'] ?? 0 )
                     + (float) ( $snap_sum['total_spend'] ?? 0 )
                     + (float) ( $tt_sum['total_spend'] ?? 0 );

        $recs = self::get_recommendations( compact(
            'days', 'seo_sum', 'meta_sum', 'snap_sum', 'tt_sum', 'ai_sum', 'total_spend'
        ) );

        if ( ! $title ) {
            $title = 'Rezponz Marketing Rapport – ' . date_i18n( 'F Y' );
        }

        $html = self::render_html( [
            'title'       => $title,
            'period'      => "Seneste {$days} dage",
            'generated'   => date_i18n( 'd. F Y H:i' ),
            'seo_kw'      => $seo_kw,
            'seo_sum'     => $seo_sum,
            'meta_c'      => array_slice( $meta_c, 0, 5 ),
            'meta_sum'    => $meta_sum,
            'snap_c'      => array_slice( $snap_c, 0, 5 ),
            'snap_sum'    => $snap_sum,
            'tt_c'        => array_slice( $tt_c, 0, 5 ),
            'tt_sum'      => $tt_sum,
            'ai_sum'      => $ai_sum,
            'recs'        => $recs,
            'total_spend' => $total_spend,
        ] );

        // Return HTML with print-to-PDF instructions
        return new WP_REST_Response( [
            'success' => true,
            'html'    => $html,
            'title'   => $title,
        ] );
    }

    private static function get_recommendations( array $data ) : array {
        $opts = get_option( 'rzpa_settings', [] );

        if ( ! empty( $opts['openai_api_key'] ) ) {
            $prompt = "Du er marketing analytiker for Rezponz, et dansk marketing automation firma.\n"
                . "Baseret på følgende performance data, generér 5 konkrete anbefalinger på dansk:\n\n"
                . wp_json_encode( $data, JSON_PRETTY_PRINT ) . "\n\n"
                . "Formatér som en nummereret liste. Vær specifik og datadrevet.";

            $res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $opts['openai_api_key'],
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'model'      => 'gpt-4o-mini',
                    'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                    'max_tokens' => 600,
                ] ),
                'timeout' => 30,
            ] );

            if ( ! is_wp_error( $res ) ) {
                $body = json_decode( wp_remote_retrieve_body( $res ), true );
                $text = $body['choices'][0]['message']['content'] ?? '';
                $lines = array_filter( explode( "\n", $text ), fn( $l ) => preg_match( '/^\d+[.)]\s/', $l ) );
                if ( count( $lines ) >= 3 ) {
                    return array_values( array_map( fn( $l ) => preg_replace( '/^\d+[.)]\s*/', '', $l ), $lines ) );
                }
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
        return number_format( (float) $v, $d, ',', '.' );
    }

    private static function render_html( array $d ) : string {
        $css = self::get_css();
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $d['title'] ); ?></title>
<style><?php echo $css; ?></style>
</head>
<body>

<!-- COVER -->
<div class="page cover">
  <div class="cover-logo">REZPONZ</div>
  <div class="cover-tagline">Marketing Intelligence Platform</div>
  <div class="cover-title"><?php echo esc_html( $d['title'] ); ?></div>
  <div class="cover-badge"><?php echo esc_html( $d['period'] ); ?></div>
  <div class="cover-date">Genereret: <?php echo esc_html( $d['generated'] ); ?></div>
  <div class="cover-bar"></div>
</div>

<!-- EXECUTIVE SUMMARY -->
<div class="page content-page">
  <div class="section-header">
    <div class="section-title">Executive Summary</div>
    <div class="section-sub"><?php echo esc_html( $d['period'] ); ?> – nøgletal på tværs af alle kanaler</div>
  </div>
  <div class="kpi-grid">
    <?php foreach ( [
        ['Samlet Ad Spend',    self::n($d['total_spend'],0) . ' DKK', ''],
        ['Meta ROAS',         self::n($d['meta_sum']['avg_roas'] ?? 0, 2) . 'x', 'Gennemsnit'],
        ['Organiske klik',    self::n($d['seo_sum']['total_clicks'] ?? 0), 'SEO'],
        ['TikTok Video Views',self::n($d['tt_sum']['total_video_views'] ?? 0), ''],
    ] as [$label,$val,$sub] ): ?>
    <div class="kpi-card">
      <div class="kpi-label"><?php echo esc_html($label); ?></div>
      <div class="kpi-value"><?php echo esc_html($val); ?></div>
      <?php if($sub): ?><div class="kpi-sub"><?php echo esc_html($sub); ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <ul class="bullet-list">
    <li>Meta Ads: Spend <?php echo self::n($d['meta_sum']['total_spend']??0,0); ?> DKK · ROAS <?php echo self::n($d['meta_sum']['avg_roas']??0,2); ?>x gennemsnit</li>
    <li>TikTok: <?php echo self::n($d['tt_sum']['total_video_views']??0); ?> videovisninger · ROAS <?php echo self::n($d['tt_sum']['avg_roas']??0,2); ?>x</li>
    <li>Snapchat: <?php echo self::n($d['snap_sum']['total_swipe_ups']??0); ?> swipe-ups · <?php echo self::n($d['snap_sum']['avg_engagement_rate']??0,2); ?>% engagement</li>
    <li>SEO: <?php echo self::n($d['seo_sum']['total_clicks']??0); ?> organiske klik · <?php echo self::n($d['seo_sum']['avg_ctr']??0,2); ?>% CTR</li>
    <li>AI-synlighed: <?php echo (int)($d['ai_sum']['ai_overview_count']??0); ?> AI Overviews · <?php echo (int)($d['ai_sum']['featured_snippet_count']??0); ?> Featured Snippets</li>
  </ul>
</div>

<!-- SEO -->
<div class="page content-page">
  <div class="section-header">
    <div class="section-title">SEO Performance</div>
    <div class="section-sub">Google Search Console · rezponz.dk</div>
  </div>
  <div class="kpi-grid">
    <?php foreach ( [
        ['Organiske klik',    self::n($d['seo_sum']['total_clicks']??0)],
        ['Impressions',       self::n($d['seo_sum']['total_impressions']??0)],
        ['Gns. CTR',          self::n($d['seo_sum']['avg_ctr']??0,2).'%'],
        ['Søgeord i top 10',  self::n($d['seo_sum']['keywords_top10']??0)],
    ] as [$label,$val] ): ?>
    <div class="kpi-card"><div class="kpi-label"><?php echo esc_html($label); ?></div><div class="kpi-value"><?php echo esc_html($val); ?></div></div>
    <?php endforeach; ?>
  </div>
  <table>
    <thead><tr><th>Søgeord</th><th>Position</th><th>Klik</th><th>Impressions</th><th>CTR</th></tr></thead>
    <tbody>
    <?php foreach ( $d['seo_kw'] as $k ):
        $pc = (float)$k['avg_position'] <= 3 ? '#CCFF00' : ((float)$k['avg_position'] <= 10 ? '#88aaff' : '#888'); ?>
      <tr>
        <td><?php echo esc_html($k['keyword']); ?></td>
        <td style="color:<?php echo $pc; ?>;font-weight:700">#<?php echo self::n($k['avg_position'],1); ?></td>
        <td><?php echo self::n($k['total_clicks']); ?></td>
        <td><?php echo self::n($k['total_impressions']); ?></td>
        <td><?php echo self::n($k['avg_ctr'],2); ?>%</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- META ADS -->
<div class="page content-page">
  <div class="section-header">
    <div class="section-title">Meta Ads</div>
    <div class="section-sub">Facebook + Instagram · Marketing API v19</div>
  </div>
  <div class="kpi-grid">
    <?php foreach ( [
        ['Samlet Spend',  self::n($d['meta_sum']['total_spend']??0,0).' DKK'],
        ['Gns. ROAS',     self::n($d['meta_sum']['avg_roas']??0,2).'x'],
        ['Impressions',   self::n($d['meta_sum']['total_impressions']??0)],
        ['Klik',          self::n($d['meta_sum']['total_clicks']??0)],
    ] as [$label,$val] ): ?>
    <div class="kpi-card"><div class="kpi-label"><?php echo esc_html($label); ?></div><div class="kpi-value"><?php echo esc_html($val); ?></div></div>
    <?php endforeach; ?>
  </div>
  <table>
    <thead><tr><th>Kampagne</th><th>Status</th><th>Spend</th><th>Impressions</th><th>Klik</th><th>ROAS</th></tr></thead>
    <tbody>
    <?php foreach ( $d['meta_c'] as $c ):
        $rc = (float)$c['roas'] >= 2.5 ? '#CCFF00' : ((float)$c['roas'] >= 1.5 ? '#88cc00' : '#cc4400');
        $badge = $c['status'] === 'ACTIVE' ? 'badge-active' : 'badge-paused'; ?>
      <tr>
        <td><?php echo esc_html($c['campaign_name']); ?></td>
        <td><span class="<?php echo $badge; ?>"><?php echo esc_html($c['status']); ?></span></td>
        <td><?php echo self::n($c['spend'],0); ?> kr</td>
        <td><?php echo self::n($c['impressions']); ?></td>
        <td><?php echo self::n($c['clicks']); ?></td>
        <td style="color:<?php echo $rc; ?>;font-weight:700"><?php echo self::n($c['roas'],2); ?>x</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- SNAP + TIKTOK -->
<div class="page content-page">
  <div class="section-header">
    <div class="section-title">Snapchat &amp; TikTok Ads</div>
  </div>
  <h3 class="platform-label">Snapchat</h3>
  <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="kpi-card"><div class="kpi-label">Spend</div><div class="kpi-value"><?php echo self::n($d['snap_sum']['total_spend']??0,0); ?> DKK</div></div>
    <div class="kpi-card"><div class="kpi-label">Swipe-ups</div><div class="kpi-value"><?php echo self::n($d['snap_sum']['total_swipe_ups']??0); ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Engagement Rate</div><div class="kpi-value"><?php echo self::n($d['snap_sum']['avg_engagement_rate']??0,2); ?>%</div></div>
  </div>
  <h3 class="platform-label">TikTok</h3>
  <div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Spend</div><div class="kpi-value"><?php echo self::n($d['tt_sum']['total_spend']??0,0); ?> DKK</div></div>
    <div class="kpi-card"><div class="kpi-label">Video Views</div><div class="kpi-value"><?php echo self::n($d['tt_sum']['total_video_views']??0); ?></div></div>
    <div class="kpi-card"><div class="kpi-label">ROAS</div><div class="kpi-value"><?php echo self::n($d['tt_sum']['avg_roas']??0,2); ?>x</div></div>
    <div class="kpi-card"><div class="kpi-label">Konverteringer</div><div class="kpi-value"><?php echo self::n($d['tt_sum']['total_conversions']??0); ?></div></div>
  </div>
</div>

<!-- RECOMMENDATIONS -->
<div class="page content-page">
  <div class="section-header">
    <div class="section-title">Anbefalinger</div>
    <div class="section-sub">AI-genererede anbefalinger baseret på performance data</div>
  </div>
  <?php foreach ( $d['recs'] as $i => $rec ): ?>
  <div class="rec-item"><span class="rec-num"><?php echo $i+1; ?></span><?php echo esc_html($rec); ?></div>
  <?php endforeach; ?>
</div>

<!-- BACKSIDE -->
<div class="page backside">
  <div class="back-logo">REZPONZ</div>
  <div class="back-tag">Marketing Intelligence – Powered by data</div>
  <div class="back-url">rezponz.dk</div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    private static function get_css() : string {
        return '
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, Helvetica, sans-serif; background:#0a0a0a; color:#fff; }
.page { width:210mm; min-height:297mm; page-break-after:always; }

/* Cover */
.cover { background:#0a0a0a; display:flex; flex-direction:column; justify-content:center; align-items:center; min-height:297mm; text-align:center; position:relative; }
.cover-logo { font-size:52px; font-weight:900; letter-spacing:-3px; color:#CCFF00; margin-bottom:12px; }
.cover-tagline { color:#555; font-size:15px; margin-bottom:72px; }
.cover-title { font-size:28px; font-weight:700; color:#fff; margin-bottom:16px; max-width:480px; line-height:1.3; }
.cover-badge { background:#CCFF00; color:#000; padding:7px 22px; border-radius:24px; font-weight:700; font-size:14px; margin-bottom:32px; display:inline-block; }
.cover-date { color:#444; font-size:12px; }
.cover-bar { position:absolute; bottom:0; left:0; right:0; height:5px; background:linear-gradient(90deg,#CCFF00,#88ff00,#CCFF00); }

/* Content */
.content-page { background:#111; min-height:297mm; padding:36px 44px; }
.section-header { margin-bottom:28px; border-bottom:1px solid #222; padding-bottom:14px; }
.section-title { font-size:20px; font-weight:700; color:#fff; }
.section-sub { font-size:12px; color:#555; margin-top:3px; }

/* KPIs */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
.kpi-card { background:#1a1a1a; border:1px solid #222; border-radius:10px; padding:18px; }
.kpi-label { font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#555; margin-bottom:8px; }
.kpi-value { font-size:26px; font-weight:900; color:#CCFF00; line-height:1; }
.kpi-sub { font-size:11px; color:#666; margin-top:4px; }

/* Table */
table { width:100%; border-collapse:collapse; font-size:11px; margin-bottom:20px; }
th { background:#1a1a1a; color:#666; text-transform:uppercase; font-size:9px; letter-spacing:0.5px; padding:9px 10px; text-align:left; border-bottom:1px solid #222; }
td { padding:9px 10px; border-bottom:1px solid #1a1a1a; color:#bbb; }
.badge-active { background:#0d3320; color:#CCFF00; padding:2px 7px; border-radius:10px; font-size:9px; font-weight:700; }
.badge-paused { background:#222; color:#666; padding:2px 7px; border-radius:10px; font-size:9px; font-weight:700; }

/* Bullets */
.bullet-list { list-style:none; margin-top:8px; }
.bullet-list li { padding:10px 0; border-bottom:1px solid #1a1a1a; font-size:13px; color:#bbb; line-height:1.5; padding-left:20px; position:relative; }
.bullet-list li::before { content:"▶"; color:#CCFF00; font-size:8px; position:absolute; left:0; top:13px; }

/* Platform label */
.platform-label { color:#555; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:14px; }

/* Recommendations */
.rec-item { background:#1a1a1a; border-left:3px solid #CCFF00; padding:14px 18px; margin-bottom:10px; border-radius:0 8px 8px 0; font-size:13px; color:#bbb; line-height:1.6; }
.rec-num { display:inline-block; background:#CCFF00; color:#000; width:20px; height:20px; border-radius:50%; text-align:center; line-height:20px; font-size:10px; font-weight:800; margin-right:10px; }

/* Backside */
.backside { background:#0a0a0a; min-height:297mm; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; }
.back-logo { font-size:72px; font-weight:900; color:#CCFF00; letter-spacing:-4px; margin-bottom:16px; }
.back-tag { color:#333; font-size:18px; margin-bottom:40px; }
.back-url { color:#CCFF00; font-size:16px; font-weight:700; }

@media print { .page { page-break-after: always; } }
';
    }
}
