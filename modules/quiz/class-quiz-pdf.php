<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Quiz PDF generator — uses DomPDF (bundled in vendor/).
 * Produces output matching admin-pdf.php exactly.
 */
class RZPA_Quiz_PDF_Generator {

    public static function generate( array $data, string $writable_dir = '' ): string {
        if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
            throw new \RuntimeException( 'DomPDF mangler – vendor/autoload.php ikke indlæst' );
        }

        @ini_set( 'memory_limit', '512M' );
        @set_time_limit( 120 );

        $options = new \Dompdf\Options();
        $options->set( 'isHtml5ParserEnabled',  false );
        $options->set( 'isRemoteEnabled',        false );
        $options->set( 'defaultFont',            'Helvetica' );
        $options->set( 'isFontSubsettingEnabled', false );

        // Prefer the caller-supplied writable dir (wp-uploads); fall back to sys tmp
        if ( $writable_dir && is_dir( $writable_dir ) && is_writable( $writable_dir ) ) {
            $font_cache = rtrim( $writable_dir, '/\\' ) . DIRECTORY_SEPARATOR . 'rzpa-dompdf-fonts';
        } else {
            $font_cache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rzpa_dompdf';
        }
        if ( ! file_exists( $font_cache ) ) { @mkdir( $font_cache, 0755, true ); }
        $options->set( 'fontDir',   $font_cache );
        $options->set( 'fontCache', $font_cache );
        $options->set( 'tempDir',   dirname( $font_cache ) );

        $dompdf = new \Dompdf\Dompdf( $options );
        $dompdf->loadHtml( self::build_html( $data ), 'UTF-8' );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        return $dompdf->output();
    }

    // ── HTML builder ──────────────────────────────────────────────────────────

    private static function build_html( array $data ): string {

        $color     = $data['profile_color']   ?? '#e8590c';
        $title     = self::e( $data['profile_title']   ?? '' );
        $desc      = self::e( $data['profile_desc']    ?? '' );
        $sec_title = self::e( $data['secondary_title'] ?? '' );
        $name      = self::e( $data['name']  ?? '' );
        $phone     = self::e( $data['phone'] ?: '&mdash;' );
        $email     = self::e( $data['email'] ?: '&mdash;' );
        $date      = function_exists( 'wp_date' )
            ? wp_date( 'd. F Y', strtotime( $data['created_at'] ?? 'now' ) )
            : date( 'd. F Y', strtotime( $data['created_at'] ?? 'now' ) );

        $strengths = json_decode( $data['strengths']    ?? '[]', true ) ?: [];
        $thrives   = json_decode( $data['thrives_with'] ?? '[]', true ) ?: [];
        $develop   = json_decode( $data['develop_areas'] ?? '[]', true ) ?: [];
        $scores    = is_array( $data['scores'] ) ? $data['scores'] : ( json_decode( $data['scores'] ?? '{}', true ) ?: [] );
        $qa        = $data['qa'] ?? [];
        $max_score = $scores ? max( array_values( $scores ) ) : 1;

        $profile_names = [
            'empatisk'  => 'Den Empatiske Lytter',
            'energisk'  => 'Energibomben',
            'analytisk' => 'Problemknuseren',
            'social'    => 'Netværksmesteren',
        ];

        // ── Logo ──
        $logo_path = defined( 'RZPA_DIR' ) ? RZPA_DIR . 'assets/Rezponz-logo.png' : '';
        if ( $logo_path && file_exists( $logo_path ) ) {
            $b64      = base64_encode( file_get_contents( $logo_path ) );
            $logo_img = '<img src="data:image/png;base64,' . $b64 . '" style="height:34px;width:auto;display:block" alt="rezponz">';
        } else {
            $logo_img = '<span style="font-size:18px;font-weight:900;color:#111827">rezponz</span>';
        }

        // ── Helper: bullet list ──
        $bullets = function ( array $items ) use ( $color ): string {
            $out = '<table cellpadding="0" cellspacing="0" style="width:100%">';
            foreach ( $items as $item ) {
                $out .= '<tr><td style="padding:5px 0 5px 0;border-bottom:1px solid #f3f4f6;color:#374151;font-size:11px;line-height:1.45">'
                      . '<span style="color:' . $color . ';font-weight:700">&#8226;</span>&nbsp;&nbsp;'
                      . self::e( $item )
                      . '</td></tr>';
            }
            $out .= '</table>';
            return $out;
        };

        // ── Score bars ──
        $scores_html = '';
        foreach ( $scores as $slug => $val ) {
            $label  = $profile_names[ $slug ] ?? $slug;
            $pct    = $max_score > 0 ? round( ( $val / $max_score ) * 100 ) : 0;
            $fill_w = max( 2, $pct );
            $scores_html .= '
            <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:8px"><tr>
              <td style="width:130px;font-size:11px;color:#374151;font-weight:600;vertical-align:middle;white-space:nowrap">' . self::e( $label ) . '</td>
              <td style="padding:0 8px;vertical-align:middle">
                <table cellpadding="0" cellspacing="0" style="width:100%;height:8px;background:#f3f4f6;border-radius:4px"><tr>
                  <td style="width:' . $fill_w . '%;background:' . $color . ';border-radius:4px;height:8px"></td>
                  <td></td>
                </tr></table>
              </td>
              <td style="width:24px;font-size:11px;color:#9ca3af;text-align:right;vertical-align:middle">' . (int) $val . '</td>
            </tr></table>';
        }

        // ── Q&A ──
        $qa_html = '';
        foreach ( $qa as $i => $item ) {
            $q = self::e( $item['question_text'] ?? '' );
            $a = self::e( $item['answer_text']   ?? '' );
            $qa_html .= '
            <div style="margin-bottom:14px;page-break-inside:avoid">
              <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">' . ( $i + 1 ) . '. ' . $q . '</div>
              <table cellpadding="0" cellspacing="0" style="width:100%"><tr>
                <td style="width:3px;background:' . $color . ';border-radius:2px 0 0 2px">&nbsp;</td>
                <td style="background:#fff8f5;padding:8px 12px;font-size:12px;color:#111827;border-radius:0 6px 6px 0">' . $a . '</td>
              </tr></table>
            </div>';
        }

        // ── Secondary badge ──
        $secondary_html = $sec_title
            ? '<div style="display:inline-block;background:rgba(255,255,255,0.25);border-radius:99px;padding:3px 12px;font-size:10px;font-weight:700;color:#fff;margin-top:8px">Sekundær: ' . $sec_title . '</div>'
            : '';

        // ── Left column ──
        $left_col = '';
        if ( $strengths ) {
            $left_col .= '
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;margin-bottom:14px">
              <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Styrker – og hvorfor de passer til Rezponz</div>
              ' . $bullets( $strengths ) . '
            </div>';
        }
        if ( $thrives ) {
            $left_col .= '
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px">
              <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Du trives med – det har vi hos Rezponz</div>
              ' . $bullets( $thrives ) . '
            </div>';
        }

        // ── Right column ──
        $right_col = '';
        if ( $develop ) {
            $right_col .= '
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;margin-bottom:14px">
              <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Det kan udfordre dig – men det hjælper vi dig med</div>
              ' . $bullets( $develop ) . '
            </div>';
        }
        $right_col .= '
        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px">
          <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Score-fordeling</div>
          ' . $scores_html . '
        </div>';

        // ── Assemble ──────────────────────────────────────────────────────────

        return '<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<title>Profil-Rapport – ' . $name . '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Helvetica, Arial, sans-serif; background: #fff; color: #111827; font-size: 12px; line-height: 1.5; }
  .page { padding: 36px 44px; }
  table { border-collapse: collapse; }
</style>
</head>
<body>
<div class="page">

<!-- HEADER -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #f3f4f6">
  <tr>
    <td style="vertical-align:middle">
      ' . $logo_img . '
      <div style="font-size:10px;color:#9ca3af;margin-top:2px">Customer Success DNA</div>
    </td>
    <td style="text-align:right;vertical-align:top">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9ca3af">Profil-Rapport</div>
      <div style="font-size:15px;font-weight:800;color:#111827;margin-top:2px">' . self::e( $date ) . '</div>
    </td>
  </tr>
</table>

<!-- CONTACT -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:22px">
  <tr>
    <td style="width:33%;padding-right:6px">
      <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:3px">Navn</div>
        <div style="font-size:13px;font-weight:700;color:#111827">' . $name . '</div>
      </div>
    </td>
    <td style="width:33%;padding:0 3px">
      <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:3px">Telefon</div>
        <div style="font-size:13px;font-weight:700;color:#111827">' . $phone . '</div>
      </div>
    </td>
    <td style="width:33%;padding-left:6px">
      <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:3px">E-mail</div>
        <div style="font-size:11px;font-weight:700;color:#111827">' . $email . '</div>
      </div>
    </td>
  </tr>
</table>

<!-- PROFILE HERO -->
<div style="background:' . $color . ';border-radius:12px;padding:22px 24px;margin-bottom:20px">
  <div style="font-size:22px;font-weight:900;color:#fff;line-height:1.1;margin-bottom:7px">' . $title . '</div>
  <div style="font-size:12px;color:rgba(255,255,255,0.88);line-height:1.55">' . $desc . '</div>
  ' . $secondary_html . '
</div>

<!-- CTA -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:20px;background:#111827;border-radius:10px">
  <tr>
    <td style="padding:16px 20px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#6b7280;margin-bottom:3px">Klar til næste skridt?</div>
      <div style="font-size:14px;font-weight:800;color:#fff;line-height:1.3">Din profil passer perfekt til et job hos Rezponz</div>
    </td>
    <td style="padding:16px 20px;text-align:right;vertical-align:middle;white-space:nowrap">
      <a href="https://rezponz.dk/karriere-stillinger/" style="background:#f97316;color:#fff;border-radius:6px;padding:9px 18px;font-size:12px;font-weight:800;text-decoration:none;display:inline-block">Søg jobbet hos Rezponz &gt;&gt;</a>
    </td>
  </tr>
</table>

<!-- TWO COLUMNS -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:22px">
  <tr valign="top">
    <td style="width:50%;padding-right:10px">' . $left_col . '</td>
    <td style="width:50%;padding-left:10px">' . $right_col . '</td>
  </tr>
</table>

' . ( $qa_html ? '
<!-- Q&A -->
<div style="margin-bottom:24px">
  <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:14px">Svar på spørgsmålene</div>
  ' . $qa_html . '
</div>' : '' ) . '

<!-- FOOTER -->
<table cellpadding="0" cellspacing="0" style="width:100%;border-top:1px solid #f3f4f6;padding-top:12px;margin-top:4px">
  <tr>
    <td style="font-size:10px;color:#d1d5db">Profil-Quiz · Rezponz · rezponz.dk</td>
    <td style="font-size:10px;color:#d1d5db;text-align:right">GDPR: ' . ( $data['consent'] ? 'Samtykke givet' : 'Intet samtykke' ) . '</td>
  </tr>
</table>

</div>
</body>
</html>';
    }

    /** HTML-escape */
    private static function e( string $s ): string {
        return htmlspecialchars( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}
