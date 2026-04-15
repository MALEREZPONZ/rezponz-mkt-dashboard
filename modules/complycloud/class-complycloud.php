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
        add_action( 'admin_menu',                      [ __CLASS__, 'admin_menu' ] );
        add_action( self::CRON_HOOK,                   [ __CLASS__, 'run_check' ] );
        add_action( 'admin_post_rzpz_cc_save',         [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_rzpz_cc_check_now',    [ __CLASS__, 'handle_check_now' ] );
        add_action( 'admin_post_rzpz_cc_reset_state',  [ __CLASS__, 'handle_reset_state' ] );

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

        // Reschedule cron if frequency changed
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
    // Returns: true  = changes found + email sent
    //          false = no changes
    //          string = error message

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
                // First time seeing this document
                if ( ! empty( $old_state ) ) {
                    // We already had a state — this is truly new
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

        // Detect deleted documents
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

        // Save updated state
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
        $tc_id       = $settings['trust_center_id'] ?? '4562155b-e994-4899-884a-b4cc2a199d87';

        // Build recipient list
        $recipients = array_filter( array_map( 'trim', [ $primary ] ) );
        if ( ! empty( $settings['extra_emails'] ) ) {
            $extras = array_filter( array_map( 'sanitize_email', preg_split( '/[\n,;]+/', $settings['extra_emails'] ) ) );
            $recipients = array_merge( $recipients, $extras );
        }
        $recipients = array_unique( array_filter( $recipients ) );
        if ( empty( $recipients ) ) return;

        $account = $trust_center_data['accountName'] ?? 'Rezponz A/S';
        $count   = count( $changed );
        $subject = "🔒 ComplyCloud: {$count} dokument" . ( $count !== 1 ? 'er' : '' ) . " opdateret hos {$account}";

        // Build plain text body
        $lines = [];
        foreach ( $changed as $c ) {
            $type_dk = match ( $c['type'] ) {
                'new'     => '🆕 NYT',
                'removed' => '🗑 FJERNET',
                default   => '✏️ OPDATERET',
            };
            $new_date = $c['lastModified'] ? self::fmt_date( $c['lastModified'] ) : '–';
            $old_date = $c['was']          ? self::fmt_date( $c['was'] )          : '–';

            $line = "{$type_dk}: {$c['title']}";
            if ( $c['type'] === 'updated' ) {
                $line .= "\n   Tidligere: {$old_date}\n   Nu:        {$new_date}";
            } elseif ( $c['type'] === 'new' ) {
                $line .= "\n   Tilføjet:  {$new_date}";
            }
            $lines[] = $line;
        }

        $trust_center_url = "https://app.complycloud.com/public/trust-center?id={$tc_id}";

        $body_text = "Hej,\n\n"
            . "Følgende CompyCloud-dokumenter er blevet ændret hos {$account}:\n\n"
            . implode( "\n\n", $lines )
            . "\n\n──────────────────────────────\n"
            . "Se det fulde trust center:\n{$trust_center_url}\n\n"
            . "Denne mail er sendt automatisk af Rezponz Analytics.\n"
            . "Tidspunkt: " . current_time( 'd-m-Y H:i:s' );

        // Build HTML body
        $rows_html = '';
        foreach ( $changed as $c ) {
            $badge_bg = match ( $c['type'] ) {
                'new'     => '#22c55e',
                'removed' => '#ef4444',
                default   => '#3b82f6',
            };
            $badge_dk = match ( $c['type'] ) {
                'new'     => 'Nyt',
                'removed' => 'Fjernet',
                default   => 'Opdateret',
            };
            $new_date = $c['lastModified'] ? self::fmt_date( $c['lastModified'] ) : '–';
            $old_date = $c['was']          ? self::fmt_date( $c['was'] )          : '–';

            $meta = '';
            if ( $c['type'] === 'updated' ) {
                $meta = "<br><small style='color:#9ca3af'>Tidligere: {$old_date} &rarr; Nu: {$new_date}</small>";
            } elseif ( $c['type'] === 'new' ) {
                $meta = "<br><small style='color:#9ca3af'>Tilføjet: {$new_date}</small>";
            }

            $rows_html .= "
            <tr>
              <td style='padding:12px 16px;border-bottom:1px solid #2d2d2d;'>
                <span style='display:inline-block;background:{$badge_bg};color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;margin-right:8px;'>{$badge_dk}</span>
                <strong style='color:#fff;'>" . esc_html( $c['title'] ) . "</strong>
                {$meta}
              </td>
            </tr>";
        }

        $body_html = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#0e0e0e;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;'>
  <div style='max-width:560px;margin:40px auto;background:#1a1a1a;border-radius:12px;overflow:hidden;border:1px solid #2a2a2a;'>

    <div style='background:#111;padding:24px 28px;border-bottom:1px solid #2a2a2a;'>
      <p style='margin:0;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:1px;'>ComplyCloud Monitor</p>
      <h1 style='margin:6px 0 0;font-size:20px;color:#CCFF00;'>
        🔒 " . esc_html( (string) $count ) . " dokument" . ( $count !== 1 ? 'er' : '' ) . " opdateret
      </h1>
      <p style='margin:4px 0 0;font-size:13px;color:#888;'>" . esc_html( $account ) . " &mdash; Trust Center</p>
    </div>

    <div style='padding:0;'>
      <table style='width:100%;border-collapse:collapse;'>
        {$rows_html}
      </table>
    </div>

    <div style='padding:20px 28px;border-top:1px solid #2a2a2a;'>
      <a href='" . esc_url( $trust_center_url ) . "'
         style='display:inline-block;background:#CCFF00;color:#000;font-weight:700;font-size:13px;padding:10px 20px;border-radius:6px;text-decoration:none;'>
        Se Trust Center &rarr;
      </a>
    </div>

    <div style='padding:16px 28px;background:#111;border-top:1px solid #2a2a2a;'>
      <p style='margin:0;font-size:11px;color:#555;'>
        Sendt automatisk af Rezponz Analytics &bull; " . current_time( 'd-m-Y H:i' ) . "
      </p>
    </div>

  </div>
</body>
</html>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $notify_name . ' <' . $primary . '>',
        ];

        foreach ( $recipients as $to ) {
            wp_mail( $to, $subject, $body_html, $headers );
        }
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    private static function log_check( string $status, string $message, int $total, int $changed ): void {
        $log = get_option( self::OPTION_LOG, [] );

        // Keep last 50 entries
        array_unshift( $log, [
            'time'    => current_time( 'mysql' ),
            'status'  => $status,
            'message' => $message,
            'total'   => $total,
            'changed' => $changed,
        ] );
        $log = array_slice( $log, 0, 50 );

        update_option( self::OPTION_LOG, $log );
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
        if ( $diff < 60 )     return 'Om < 1 minut';
        if ( $diff < 3600 )   return 'Om ca. ' . round( $diff / 60 ) . ' min';
        if ( $diff < 86400 )  return 'Om ca. ' . round( $diff / 3600 ) . ' timer';
        return 'Om ca. ' . round( $diff / 86400 ) . ' dag(e)';
    }
}
