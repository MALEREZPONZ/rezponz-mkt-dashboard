<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Quiz_Mailer {

    const FROM_NAME  = 'Rezponz';
    const FROM_EMAIL = 'noreply@rezponz.dk';

    // ── Read saved config ─────────────────────────────────────────────────────

    private static function get_cfg(): array {
        $defaults = [
            'admin_email'  => 'lie@rezponz.dk',
            'cta_url'      => '/book-en-samtale',
            'cta_text'     => 'Book samtale →',
            'user_subject' => '',
        ];
        return array_merge( $defaults, (array) get_option( 'rzpa_quiz_email_cfg', [] ) );
    }

    // ── Public: send user email ───────────────────────────────────────────────

    public static function send_user_email(
        string $name,
        string $email,
        array  $winner,
        array  $secondary,
        array  $all_profiles,
        array  $scores,
        int    $sub_id = 0
    ): void {
        if ( ! $email || ! is_email( $email ) ) return;

        $cfg        = self::get_cfg();
        $first_name = explode( ' ', trim( $name ) )[0];
        $subject    = $cfg['user_subject']
            ?: "Din Rezponz profil: {$winner['title']} {$winner['icon_emoji']}";

        $html = self::build_user_html( $first_name, $winner, $secondary, $cfg );
        $pdf  = $sub_id ? self::make_pdf_attachment( $sub_id, $name ) : null;

        self::send( $email, $subject, $html, $pdf );

        if ( $pdf && file_exists( $pdf ) ) {
            @unlink( $pdf );
        }
    }

    // ── Public: send admin email ──────────────────────────────────────────────

    public static function send_admin_email(
        string $name,
        string $phone,
        string $email,
        array  $winner,
        array  $scores,
        int    $sub_id
    ): void {
        $cfg     = self::get_cfg();
        $subject = "Ny quiz-besvarelse #{$sub_id} – {$name} er {$winner['title']}";

        $html = self::build_admin_html( $name, $phone, $email, $winner, $sub_id );
        $pdf  = self::make_pdf_attachment( $sub_id, $name );

        self::send( $cfg['admin_email'], $subject, $html, $pdf );

        if ( $pdf && file_exists( $pdf ) ) {
            @unlink( $pdf );
        }
    }

    // ── Generate PDF and save to temp file ───────────────────────────────────

    private static function make_pdf_attachment( int $sub_id, string $name ): ?string {
        if ( ! $sub_id ) return null;

        $data = RZPA_Quiz_DB::get_submission_detail( $sub_id );
        if ( ! $data ) return null;

        $upload_dir = wp_upload_dir();
        $tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'rzpa-pdf-tmp/';
        if ( ! file_exists( $tmp_dir ) ) {
            wp_mkdir_p( $tmp_dir );
            // Prevent direct browser access
            file_put_contents( $tmp_dir . '.htaccess', 'Deny from all' );
        }

        try {
            $pdf_bytes = RZPA_Quiz_PDF_Generator::generate( $data, $tmp_dir );
        } catch ( \Throwable $e ) {
            $err_msg = $e->getMessage() . ' (' . get_class( $e ) . ') i ' . $e->getFile() . ':' . $e->getLine();
            error_log( '[RZPA Quiz PDF] generate() fejlede for submission #' . $sub_id . ': ' . $err_msg );
            // Gem fejlen i transient så admin kan se den i dashboardet
            set_transient( 'rzpa_quiz_pdf_error', $err_msg, HOUR_IN_SECONDS );
            return null;
        }

        $safe_name = preg_replace( '/[^a-z0-9]/i', '-', $name );
        $filename  = "profil-rapport-{$safe_name}-{$sub_id}.pdf";
        $filepath  = $tmp_dir . $filename;

        if ( file_put_contents( $filepath, $pdf_bytes ) === false ) {
            error_log( '[RZPA Quiz PDF] file_put_contents fejlede: ' . $filepath );
            return null;
        }

        return $filepath;
    }

    // ── HTML: bruger-mail (kort og personlig) ─────────────────────────────────

    private static function build_user_html(
        string $first_name,
        array  $winner,
        array  $secondary,
        array  $cfg
    ): string {

        $icon   = esc_html( $winner['icon_emoji'] ?? '' );
        $title  = esc_html( $winner['title']      ?? '' );
        $color  = esc_attr( $winner['color']       ?? '#e8590c' );
        $sec    = esc_html( ( $secondary['icon_emoji'] ?? '' ) . ' ' . ( $secondary['title'] ?? '' ) );
        $cta_url  = esc_url( $cfg['cta_url'] );
        $cta_text = esc_html( $cfg['cta_text'] );

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif'>
  <div style='max-width:580px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)'>

    <!-- Header -->
    <div style='background:linear-gradient(135deg,#111827,#1f2937);padding:44px 40px;text-align:center'>
      <div style='font-size:60px;line-height:1;margin-bottom:14px'>{$icon}</div>
      <div style='display:inline-block;background:{$color};color:#fff;border-radius:999px;padding:5px 16px;font-size:12px;font-weight:700;letter-spacing:1px;margin-bottom:12px'>DIN PROFIL</div>
      <h1 style='color:#fff;margin:0;font-size:26px;font-weight:800'>{$title}</h1>
    </div>

    <!-- Body -->
    <div style='padding:36px 40px'>

      <p style='font-size:16px;color:#111827;line-height:1.65;margin:0 0 20px'>
        Hej {$first_name}! 👋
      </p>

      <p style='font-size:15px;color:#374151;line-height:1.65;margin:0 0 20px'>
        Tak for at tage Rezponz profil-quizzen. Dit resultat er vedhæftet som PDF – du finder din fulde profil, dine styrker, hvad du trives med og din score-fordeling i dokumentet.
      </p>

      <p style='font-size:15px;color:#374151;line-height:1.65;margin:0 0 28px'>
        Din næststørste profil er <strong>{$sec}</strong>.
      </p>

      <!-- CTA -->
      <div style='text-align:center;background:linear-gradient(135deg,#e8590c,#d6336c);border-radius:14px;padding:30px 24px'>
        <p style='color:#fff;margin:0 0 8px;font-size:16px;font-weight:700'>Klar til at tage det næste skridt?</p>
        <p style='color:rgba(255,255,255,.85);margin:0 0 20px;font-size:13px'>Book en gratis samtale og find ud af, hvordan Rezponz kan booste dine styrker</p>
        <a href='{$cta_url}' style='display:inline-block;background:#fff;color:#e8590c;font-weight:700;font-size:14px;text-decoration:none;padding:13px 30px;border-radius:999px'>
          {$cta_text}
        </a>
      </div>
    </div>

    <!-- Footer -->
    <div style='padding:20px 40px;border-top:1px solid #f0f0f0;text-align:center'>
      <p style='color:#9ca3af;font-size:12px;margin:0;line-height:1.8'>
        © Rezponz · rezponz.dk<br>
        Du modtager denne mail fordi du tog vores profil-quiz.
      </p>
    </div>
  </div>
</body>
</html>";
    }

    // ── HTML: admin-mail (kort notifikation) ──────────────────────────────────

    private static function build_admin_html(
        string $name,
        string $phone,
        string $email,
        array  $winner,
        int    $sub_id
    ): string {

        $icon  = esc_html( $winner['icon_emoji'] ?? '' );
        $title = esc_html( $winner['title']      ?? '' );
        $color = esc_attr( $winner['color']       ?? '#e8590c' );
        $date  = wp_date( 'd.m.Y H:i' );
        $admin_url = esc_url( admin_url( 'admin.php?page=rzpa-quiz-submissions' ) );
        $pdf_url   = esc_url( admin_url( "admin.php?page=rzpa-quiz-pdf&submission_id={$sub_id}" ) );

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif'>
  <div style='max-width:540px;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)'>

    <!-- Header -->
    <div style='background:{$color};padding:24px 28px;text-align:center'>
      <span style='font-size:44px'>{$icon}</span>
      <h1 style='color:#fff;margin:10px 0 0;font-size:18px;font-weight:800'>
        Ny quiz-besvarelse #{$sub_id}
      </h1>
    </div>

    <!-- Body -->
    <div style='padding:28px 32px'>

      <p style='font-size:15px;color:#111827;margin:0 0 20px;line-height:1.6'>
        <strong>" . esc_html( $name ) . "</strong> har gennemført profil-quizzen og er landed som <strong>{$icon} {$title}</strong>.<br>
        Den fulde rapport er vedhæftet som PDF.
      </p>

      <!-- Contact table -->
      <table style='width:100%;border-collapse:collapse;margin-bottom:24px;font-size:13px'>
        <tr style='background:#f9fafb'>
          <td style='padding:9px 14px;font-weight:700;color:#6b7280;width:110px'>Navn</td>
          <td style='padding:9px 14px;color:#111827'>" . esc_html( $name ) . "</td>
        </tr>
        <tr>
          <td style='padding:9px 14px;font-weight:700;color:#6b7280'>Telefon</td>
          <td style='padding:9px 14px;color:#111827'>" . esc_html( $phone ?: '—' ) . "</td>
        </tr>
        <tr style='background:#f9fafb'>
          <td style='padding:9px 14px;font-weight:700;color:#6b7280'>Email</td>
          <td style='padding:9px 14px;color:#111827'>" . esc_html( $email ?: '—' ) . "</td>
        </tr>
        <tr>
          <td style='padding:9px 14px;font-weight:700;color:#6b7280'>Tidspunkt</td>
          <td style='padding:9px 14px;color:#111827'>{$date}</td>
        </tr>
      </table>

      <!-- Actions -->
      <div style='display:flex;gap:10px;justify-content:center;flex-wrap:wrap'>
        <a href='{$admin_url}' style='display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-size:13px;font-weight:700'>
          Se alle besvarelser
        </a>
        <a href='{$pdf_url}' style='display:inline-block;background:#f3f4f6;color:#374151;text-decoration:none;padding:11px 22px;border-radius:8px;font-size:13px;font-weight:700'>
          Åbn rapport i browser
        </a>
      </div>
    </div>
  </div>
</body>
</html>";
    }

    // ── Core send ─────────────────────────────────────────────────────────────

    private static function send(
        string  $to,
        string  $subject,
        string  $html,
        ?string $attachment = null
    ): void {
        // Fang wp_mail_failed events for at give synlighed i admin
        $mail_error      = null;
        $failed_listener = function ( \WP_Error $error ) use ( &$mail_error ) {
            $mail_error = $error->get_error_message();
        };
        add_action( 'wp_mail_failed', $failed_listener );

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        add_filter( 'wp_mail_from',         fn() => self::FROM_EMAIL );
        add_filter( 'wp_mail_from_name',    fn() => self::FROM_NAME );

        $attachments = $attachment ? [ $attachment ] : [];
        $result      = wp_mail( $to, $subject, $html, [], $attachments );

        remove_all_filters( 'wp_mail_content_type' );
        remove_all_filters( 'wp_mail_from' );
        remove_all_filters( 'wp_mail_from_name' );
        remove_action( 'wp_mail_failed', $failed_listener );

        if ( ! $result || $mail_error ) {
            $msg = $mail_error ?: 'wp_mail() returnerede false (ingen specifik fejl fra mailer)';
            error_log( '[RZPA Quiz Mail] Afsendelse til ' . $to . ' fejlede: ' . $msg );
            set_transient( 'rzpa_quiz_mail_error', 'Til: ' . $to . ' — ' . $msg, HOUR_IN_SECONDS );
        }
    }
}
