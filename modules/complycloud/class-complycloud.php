<?php
/**
 * ComplyCloud Monitor
 *
 * Overvåger Rezponz' public trust center via ComplyCloud API og sender
 * notifikationsmail, når dokumenter opdateres.
 *
 * API-endpoint: https://api.prod.complycloud.com/public/trust-center/{publicId}
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RZPZ_ComplyCloud {

    const CRON_HOOK       = 'rzpz_complycloud_check';
    const OPTION_STATE    = 'rzpz_complycloud_doc_state';
    const OPTION_SETTINGS = 'rzpz_complycloud_settings';
    const OPTION_LOG      = 'rzpz_complycloud_log';

    // ── Boot ─────────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'admin_menu',                        [ __CLASS__, 'admin_menu' ] );
        add_action( self::CRON_HOOK,                     [ __CLASS__, 'run_check' ] );
        add_action( 'admin_post_rzpz_cc_save',           [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_rzpz_cc_check_now',      [ __CLASS__, 'handle_check_now' ] );
        add_action( 'admin_post_rzpz_cc_reset_state',    [ __CLASS__, 'handle_reset_state' ] );
        add_action( 'admin_post_rzpz_cc_test_email',     [ __CLASS__, 'handle_send_test_email' ] );

        self::maybe_schedule();
    }

    private static function maybe_schedule(): void {
        $settings  = get_option( self::OPTION_SETTINGS, [] );
        $frequency = $settings['frequency'] ?? 'daily';

        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( ! $next ) {
            wp_schedule_event( time(), $frequency, self::CRON_HOOK );
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    // ── Admin menu ────────────────────────────────────────────────────────────

    public static function admin_menu(): void {
        add_submenu_page(
            'rzpa-dashboard',
            'ComplyCloud Monitor',
            '🔒 ComplyCloud',
            'manage_options',
            'rzpa-complycloud',
            [ __CLASS__, 'render_admin' ]
        );
    }

    public static function render_admin(): void {
        include __DIR__ . '/views/admin-complycloud.php';
    }

    // ── Settings handler ──────────────────────────────────────────────────────

    public static function handle_save_settings(): void {
        check_admin_referer( 'rzpz_cc_save' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        $old_settings  = get_option( self::OPTION_SETTINGS, [] );
        $old_frequency = $old_settings['frequency'] ?? 'daily';

        $settings = [
            'trust_center_id' => sanitize_text_field( $_POST['trust_center_id'] ?? '4562155b-e994-4899-884a-b4cc2a199d87' ),
            'notify_email'    => sanitize_email( $_POST['notify_email'] ?? '' ),
            'frequency'       => in_array( $_POST['frequency'] ?? '', [ 'hourly', 'twicedaily', 'daily', 'weekly' ], true )
                                    ? $_POST['frequency']
                                    : 'daily',
            'notify_name'     => sanitize_text_field( $_POST['notify_name'] ?? 'Rezponz Analytics' ),
            'extra_emails'    => sanitize_textarea_field( $_POST['extra_emails'] ?? '' ),
        ];

        update_option( self::OPTION_SETTINGS, $settings );

        if ( $settings['frequency'] !== $old_frequency ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            wp_schedule_event( time(), $settings['frequency'], self::CRON_HOOK );
        }

        wp_redirect( admin_url( 'admin.php?page=rzpa-complycloud&saved=1' ) );
        exit;
    }

    // ── Manual check handler ──────────────────────────────────────────────────

    public static function handle_check_now(): void {
        check_admin_referer( 'rzpz_cc_check_now' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        $result = self::run_check();
        $status = ( $result === true ) ? 'checked_changes' : ( ( $result === false ) ? 'checked_no_changes' : 'check_error' );

        wp_redirect( admin_url( 'admin.php?page=rzpa-complycloud&check=' . $status ) );
        exit;
    }

    // ── Reset state handler ───────────────────────────────────────────────────

    public static function handle_reset_state(): void {
        check_admin_referer( 'rzpz_cc_reset_state' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        delete_option( self::OPTION_STATE );
        wp_redirect( admin_url( 'admin.php?page=rzpa-complycloud&reset=1' ) );
        exit;
    }

    // ── Test email handler ────────────────────────────────────────────────────

    public static function handle_send_test_email(): void {
        check_admin_referer( 'rzpz_cc_test_email' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        $settings = get_option( self::OPTION_SETTINGS, [] );
        $to       = $settings['notify_email'] ?? get_option( 'admin_email' );

        // Sample changed documents for preview
        $sample_changed = [
            [
                'type'         => 'updated',
                'title'        => 'IT-Beredskabsplan',
                'lastModified' => current_time( 'c' ),
                'was'          => gmdate( 'c', strtotime( '-14 days' ) ),
            ],
            [
                'type'         => 'new',
                'title'        => 'Politik for adgangsstyring v2',
                'lastModified' => current_time( 'c' ),
                'was'          => null,
            ],
        ];

        $sample_tc_data = [
            'accountName' => 'Rezponz A/S',
        ];

        $subject  = '📧 [TESTMAIL] ComplyCloud notifikation — sådan ser den ud';
        $html     = self::build_email_html( $sample_changed, $sample_tc_data, true );
        $headers  = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . ( $settings['notify_name'] ?? 'Rezponz Analytics' ) . ' <' . $to . '>',
        ];

        $ok = wp_mail( $to, $subject, $html, $headers );
        $status = $ok ? 'test_sent' : 'test_failed';

        wp_redirect( admin_url( 'admin.php?page=rzpa-complycloud&check=' . $status . '&test_to=' . rawurlencode( $to ) ) );
        exit;
    }

    // ── Core: fetch trust center ──────────────────────────────────────────────

    public static function fetch_trust_center(): array|string {
        $settings = get_option( self::OPTION_SETTINGS, [] );
        $tc_id    = $settings['trust_center_id'] ?? '4562155b-e994-4899-884a-b4cc2a199d87';
        $url      = 'https://api.prod.complycloud.com/public/trust-center/' . $tc_id;

        $res = wp_remote_get( $url, [
            'timeout'    => 20,
            'user-agent' => 'Rezponz-Analytics-Monitor/1.0',
        ] );

        if ( is_wp_error( $res ) ) {
            return 'Netværksfejl: ' . $res->get_error_message();
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        if ( $code !== 200 ) {
            return "API returnerede HTTP $code";
        }

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $body ) ) {
            return 'Ugyldigt API-svar (ikke JSON)';
        }

        return $body;
    }

    // ── Core: run check ───────────────────────────────────────────────────────

    public static function run_check(): bool|string {
        $data = self::fetch_trust_center();

        if ( is_string( $data ) ) {
            self::log_check( 'error', $data, 0, 0 );
            return $data;
        }

        $documents = $data['documents'] ?? [];
        $old_state = get_option( self::OPTION_STATE, [] );
        $new_state = $old_state;
        $changed   = [];

        foreach ( $documents as $doc ) {
            $id  = $doc['id'] ?? '';
            $mod = $doc['lastModified'] ?? '';
            if ( ! $id ) continue;

            if ( ! isset( $old_state[ $id ] ) ) {
                if ( ! empty( $old_state ) ) {
                    $changed[] = [
                        'type'         => 'new',
                        'title'        => $doc['title'] ?? 'Ukendt dokument',
                        'lastModified' => $mod,
                        'was'          => null,
                    ];
                }
            } elseif ( $old_state[ $id ]['lastModified'] !== $mod ) {
                $changed[] = [
                    'type'         => 'updated',
                    'title'        => $doc['title'] ?? 'Ukendt dokument',
                    'lastModified' => $mod,
                    'was'          => $old_state[ $id ]['lastModified'],
                ];
            }

            $new_state[ $id ] = [
                'title'        => $doc['title'] ?? '',
                'lastModified' => $mod,
            ];
        }

        $current_ids = array_column( $documents, 'id' );
        foreach ( array_keys( $old_state ) as $old_id ) {
            if ( ! in_array( $old_id, $current_ids, true ) ) {
                $changed[] = [
                    'type'         => 'removed',
                    'title'        => $old_state[ $old_id ]['title'] ?? 'Ukendt dokument',
                    'lastModified' => null,
                    'was'          => $old_state[ $old_id ]['lastModified'],
                ];
                unset( $new_state[ $old_id ] );
            }
        }

        update_option( self::OPTION_STATE, $new_state );

        self::log_check(
            empty( $changed ) ? 'ok' : 'changed',
            empty( $changed ) ? 'Ingen ændringer' : count( $changed ) . ' dokument(er) ændret',
            count( $documents ),
            count( $changed )
        );

        if ( ! empty( $changed ) && ! empty( $old_state ) ) {
            self::send_notification( $changed, $data );
            return true;
        }

        return false;
    }

    // ── Email notification ────────────────────────────────────────────────────

    private static function send_notification( array $changed, array $trust_center_data ): void {
        $settings    = get_option( self::OPTION_SETTINGS, [] );
        $primary     = $settings['notify_email'] ?? get_option( 'admin_email' );
        $notify_name = $settings['notify_name']  ?? 'Rezponz Analytics';

        $recipients = [ $primary ];
        if ( ! empty( $settings['extra_emails'] ) ) {
            $extras     = array_filter( array_map( 'sanitize_email', preg_split( '/[\n,;]+/', $settings['extra_emails'] ) ) );
            $recipients = array_merge( $recipients, $extras );
        }
        $recipients = array_unique( array_filter( $recipients ) );
        if ( empty( $recipients ) ) return;

        $account = $trust_center_data['accountName'] ?? 'Rezponz A/S';
        $count   = count( $changed );
        $subject = "🔒 ComplyCloud: {$count} dokument" . ( $count !== 1 ? 'er' : '' ) . " opdateret hos {$account}";

        $html    = self::build_email_html( $changed, $trust_center_data, false );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $notify_name . ' <' . $primary . '>',
        ];

        foreach ( $recipients as $to ) {
            wp_mail( $to, $subject, $html, $headers );
        }
    }

    // ── Shared: build HTML email ──────────────────────────────────────────────

    public static function build_email_html( array $changed, array $trust_center_data, bool $is_test = false ): string {
        $settings         = get_option( self::OPTION_SETTINGS, [] );
        $tc_id            = $settings['trust_center_id'] ?? '4562155b-e994-4899-884a-b4cc2a199d87';
        $account          = $trust_center_data['accountName'] ?? 'Rezponz A/S';
        $count            = count( $changed );
        $trust_center_url = "https://app.complycloud.com/public/trust-center?id={$tc_id}";
        $logo_url         = defined( 'RZPA_URL' ) ? RZPA_URL . 'assets/Rezponz-logo.png' : 'https://rezponz.dk/wp-content/plugins/rezponz-mkt-dashboard/assets/Rezponz-logo.png';
        $now              = current_time( 'd-m-Y H:i' );

        // ── Document rows ─────────────────────────────────────────────────────
        $rows_html = '';
        foreach ( $changed as $c ) {
            $badge_bg = match ( $c['type'] ) {
                'new'     => '#22c55e',
                'removed' => '#ef4444',
                default   => '#3b82f6',
            };
            $badge_dk = match ( $c['type'] ) {
                'new'     => 'Nyt dokument',
                'removed' => 'Fjernet',
                default   => 'Opdateret',
            };
            $new_date = ! empty( $c['lastModified'] ) ? self::fmt_date( $c['lastModified'] ) : '–';
            $old_date = ! empty( $c['was'] )          ? self::fmt_date( $c['was'] )          : '–';

            $meta = '';
            if ( $c['type'] === 'updated' ) {
                $meta = "<div style='margin-top:5px;font-size:12px;color:#9ca3af;'>
                           Tidligere: <span style='color:#6b7280;'>{$old_date}</span>
                           &nbsp;→&nbsp;
                           Nu: <span style='color:#d1d5db;font-weight:600;'>{$new_date}</span>
                         </div>";
            } elseif ( $c['type'] === 'new' ) {
                $meta = "<div style='margin-top:5px;font-size:12px;color:#9ca3af;'>
                           Tilføjet: <span style='color:#d1d5db;font-weight:600;'>{$new_date}</span>
                         </div>";
            }

            $rows_html .= "
            <tr>
              <td style='padding:14px 20px;border-bottom:1px solid #252525;vertical-align:top;'>
                <div>
                  <span style='display:inline-block;background:{$badge_bg}22;color:{$badge_bg};border:1px solid {$badge_bg}55;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;margin-right:8px;letter-spacing:.3px;'>{$badge_dk}</span>
                </div>
                <div style='margin-top:8px;font-size:14px;font-weight:600;color:#f3f4f6;'>" . esc_html( $c['title'] ) . "</div>
                {$meta}
              </td>
            </tr>";
        }

        // ── Test banner ───────────────────────────────────────────────────────
        $test_banner = $is_test ? "
        <div style='background:#f59e0b22;border:1px solid #f59e0b55;border-radius:6px;padding:10px 16px;margin:0 20px 20px;font-size:12px;color:#fcd34d;text-align:center;font-weight:600;letter-spacing:.3px;'>
          📧 TESTMAIL — Sådan vil notifikationen se ud ved næste dokumentopdatering
        </div>" : '';

        // ── Full HTML ─────────────────────────────────────────────────────────
        return "<!DOCTYPE html>
<html lang='da'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>ComplyCloud Notifikation</title>
</head>
<body style='margin:0;padding:0;background:#0a0a0a;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Helvetica,Arial,sans-serif;'>

  <div style='max-width:580px;margin:0 auto;padding:32px 16px 48px;'>

    <!-- ── Logo bar ─────────────────────────────────────────────────── -->
    <div style='text-align:center;margin-bottom:24px;'>
      <img src='" . esc_url( $logo_url ) . "' alt='Rezponz' width='140' style='max-width:140px;height:auto;display:inline-block;'>
    </div>

    <!-- ── Card ─────────────────────────────────────────────────────── -->
    <div style='background:#141414;border:1px solid #222;border-radius:14px;overflow:hidden;'>

      <!-- Header -->
      <div style='background:#0e0e0e;padding:24px 20px 20px;border-bottom:1px solid #222;'>
        <div style='font-size:11px;color:#555;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:8px;'>
          🔒 ComplyCloud Monitor &bull; " . esc_html( $account ) . "
        </div>
        <h1 style='margin:0;font-size:22px;font-weight:700;color:#CCFF00;line-height:1.2;'>
          " . esc_html( (string) $count ) . " dokument" . ( $count !== 1 ? 'er' : '' ) . " " . ( $is_test ? 'er ændret (eksempel)' : 'er blevet opdateret' ) . "
        </h1>
        <p style='margin:8px 0 0;font-size:13px;color:#666;'>
          " . $now . "
        </p>
      </div>

      {$test_banner}

      <!-- Document list -->
      <table style='width:100%;border-collapse:collapse;'>
        {$rows_html}
      </table>

      <!-- CTA button -->
      <div style='padding:20px 20px 24px;'>
        <a href='" . esc_url( $trust_center_url ) . "'
           style='display:inline-block;background:#CCFF00;color:#000;font-weight:700;font-size:13px;padding:12px 24px;border-radius:7px;text-decoration:none;letter-spacing:.2px;'>
          Se Trust Center &rarr;
        </a>
      </div>

      <!-- Divider -->
      <div style='height:1px;background:#1e1e1e;margin:0 20px;'></div>

      <!-- ── Signatur ────────────────────────────────────────────────── -->
      <div style='padding:20px 20px 24px;'>
        <table style='width:100%;border-collapse:collapse;'>
          <tr>
            <td style='vertical-align:top;padding-right:16px;width:64px;'>
              <img src='" . esc_url( $logo_url ) . "' alt='Rezponz' width='56'
                   style='max-width:56px;height:auto;border-radius:6px;display:block;'>
            </td>
            <td style='vertical-align:top;'>
              <div style='font-size:14px;font-weight:700;color:#e5e7eb;'>Rezponz A/S</div>
              <div style='font-size:12px;color:#6b7280;margin-top:2px;line-height:1.6;'>
                Kristinevej 2, 9000 Aalborg<br>
                Tlf: <a href='tel:+4596301930' style='color:#6b7280;text-decoration:none;'>+45 9630 1930</a><br>
                <a href='mailto:info@rezponz.dk' style='color:#9ca3af;text-decoration:none;'>info@rezponz.dk</a>
                &nbsp;&bull;&nbsp;
                <a href='https://rezponz.dk' style='color:#9ca3af;text-decoration:none;'>rezponz.dk</a><br>
                CVR: DK26394090
              </div>
            </td>
          </tr>
        </table>
      </div>

    </div>

    <!-- ── Footer ───────────────────────────────────────────────────── -->
    <div style='text-align:center;margin-top:20px;'>
      <p style='font-size:11px;color:#3a3a3a;margin:0;'>
        Denne mail er sendt automatisk via Rezponz Analytics &bull; ComplyCloud Monitor<br>
        &copy; " . date('Y') . " Rezponz A/S &bull; Alle rettigheder forbeholdes
      </p>
    </div>

  </div>
</body>
</html>";
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    private static function log_check( string $status, string $message, int $total, int $changed ): void {
        $log = get_option( self::OPTION_LOG, [] );
        array_unshift( $log, [
            'time'    => current_time( 'mysql' ),
            'status'  => $status,
            'message' => $message,
            'total'   => $total,
            'changed' => $changed,
        ] );
        update_option( self::OPTION_LOG, array_slice( $log, 0, 50 ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function fmt_date( string $iso ): string {
        $ts = strtotime( $iso );
        if ( ! $ts ) return $iso;
        return wp_date( 'd-m-Y H:i', $ts );
    }

    public static function get_next_run_label(): string {
        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( ! $next ) return 'Ikke planlagt';
        $diff = $next - time();
        if ( $diff < 60 )    return 'Om < 1 minut';
        if ( $diff < 3600 )  return 'Om ca. ' . round( $diff / 60 ) . ' min';
        if ( $diff < 86400 ) return 'Om ca. ' . round( $diff / 3600 ) . ' timer';
        return 'Om ca. ' . round( $diff / 86400 ) . ' dag(e)';
    }
}
