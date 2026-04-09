<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Quiz_Mailer {

    const ADMIN_EMAIL = 'lie@rezponz.dk';
    const FROM_NAME   = 'Rezponz';
    const FROM_EMAIL  = 'noreply@rezponz.dk';

    // ── Read saved email config (with fallbacks) ──────────────────────────────

    private static function get_cfg(): array {
        $defaults = [
            'admin_email'  => self::ADMIN_EMAIL,
            'cta_url'      => '/book-en-samtale',
            'cta_text'     => 'Book samtale →',
            'user_subject' => '',
        ];
        return array_merge( $defaults, (array) get_option( 'rzpa_quiz_email_cfg', [] ) );
    }

    // ── Email til brugeren ────────────────────────────────────────────────────

    public static function send_user_email(
        string $name,
        string $email,
        array  $winner,
        array  $secondary,
        array  $all_profiles,
        array  $scores
    ): void {
        if ( ! $email || ! is_email( $email ) ) return;

        $cfg     = self::get_cfg();
        $subject = $cfg['user_subject'] ?: "Din Rezponz profil: {$winner['title']} {$winner['icon_emoji']}";
        $html    = self::build_user_html( $name, $winner, $secondary, $all_profiles, $scores );

        self::send( $email, $subject, $html );
    }

    // ── Email til admin ───────────────────────────────────────────────────────

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
        $html    = self::build_admin_html( $name, $phone, $email, $winner, $scores, $sub_id );

        self::send( $cfg['admin_email'], $subject, $html );
    }

    // ── HTML builder: bruger-mail ─────────────────────────────────────────────

    private static function build_user_html(
        string $name,
        array  $winner,
        array  $secondary,
        array  $all_profiles,
        array  $scores
    ): string {
        $cfg           = self::get_cfg();
        $cta_url       = esc_url( $cfg['cta_url'] );
        $cta_text      = esc_html( $cfg['cta_text'] );
        $first_name    = explode( ' ', $name )[0];
        $profile_color = esc_attr( $winner['color'] );
        $profile_icon  = esc_html( $winner['icon_emoji'] );
        $profile_title = esc_html( $winner['title'] );
        $profile_desc  = esc_html( $winner['description'] );

        $strengths_html = '';
        foreach ( $winner['strengths'] ?? [] as $s ) {
            $strengths_html .= '<li style="margin-bottom:8px;padding-left:8px">✅ ' . esc_html( $s ) . '</li>';
        }

        $thrives_html = '';
        foreach ( $winner['thrives_with'] ?? [] as $t ) {
            $thrives_html .= '<li style="margin-bottom:8px;padding-left:8px">🚀 ' . esc_html( $t ) . '</li>';
        }

        $develop_html = '';
        foreach ( $winner['develop_areas'] ?? [] as $d ) {
            $develop_html .= '<li style="margin-bottom:8px;padding-left:8px">💡 ' . esc_html( $d ) . '</li>';
        }

        $score_bars_html = '';
        $max_score = max( array_values( $scores ) ) ?: 1;
        foreach ( $all_profiles as $p ) {
            $pct   = round( ( $scores[ $p['slug'] ] ?? 0 ) / $max_score * 100 );
            $color = esc_attr( $p['color'] );
            $score_bars_html .= "
            <tr>
              <td style='padding:6px 0;font-size:13px;color:#555;width:160px'>{$p['icon_emoji']} {$p['title']}</td>
              <td style='padding:6px 0'>
                <div style='background:#f0f0f0;border-radius:20px;height:10px;overflow:hidden'>
                  <div style='background:{$color};width:{$pct}%;height:10px;border-radius:20px'></div>
                </div>
              </td>
              <td style='padding:6px 0 6px 12px;font-size:13px;color:#555;width:30px'>{$pct}%</td>
            </tr>";
        }

        $secondary_title = esc_html( $secondary['title'] );
        $secondary_icon  = esc_html( $secondary['icon_emoji'] );

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif'>
  <div style='max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)'>

    <!-- Header -->
    <div style='background:linear-gradient(135deg,#0a0a14,#1a1a2e);padding:48px 40px;text-align:center'>
      <div style='font-size:64px;line-height:1;margin-bottom:16px'>{$profile_icon}</div>
      <div style='display:inline-block;background:{$profile_color};color:#fff;border-radius:999px;padding:6px 18px;font-size:13px;font-weight:700;letter-spacing:1px;margin-bottom:16px'>DIN PROFIL</div>
      <h1 style='color:#fff;margin:0 0 12px;font-size:28px;font-weight:800'>{$profile_title}</h1>
      <p style='color:rgba(255,255,255,.7);margin:0;font-size:15px;line-height:1.6'>{$profile_desc}</p>
    </div>

    <!-- Body -->
    <div style='padding:40px'>
      <p style='font-size:17px;color:#333;line-height:1.6;margin:0 0 32px'>
        Hej {$first_name}! 👋<br><br>
        Tak for at tage vores Customer Success profil-quiz. Resultaterne herunder viser præcis hvad der gør dig unik – og hvad vi hos Rezponz kan hjælpe dig med at booste.
      </p>

      <!-- Styrker -->
      <div style='background:#f9fafb;border-radius:12px;padding:24px;margin-bottom:20px'>
        <h2 style='margin:0 0 16px;font-size:16px;font-weight:700;color:#111'>💪 Dine styrker</h2>
        <ul style='margin:0;padding:0;list-style:none;color:#333;font-size:14px;line-height:1.7'>
          {$strengths_html}
        </ul>
      </div>

      <!-- Trives med -->
      <div style='background:#f9fafb;border-radius:12px;padding:24px;margin-bottom:20px'>
        <h2 style='margin:0 0 16px;font-size:16px;font-weight:700;color:#111'>🌟 Du trives med</h2>
        <ul style='margin:0;padding:0;list-style:none;color:#333;font-size:14px;line-height:1.7'>
          {$thrives_html}
        </ul>
      </div>

      <!-- Vi udvikler dig -->
      <div style='background:#fff8f0;border:1px solid #ffe0cc;border-radius:12px;padding:24px;margin-bottom:32px'>
        <h2 style='margin:0 0 16px;font-size:16px;font-weight:700;color:#111'>📈 Vi udvikler dig i</h2>
        <ul style='margin:0;padding:0;list-style:none;color:#333;font-size:14px;line-height:1.7'>
          {$develop_html}
        </ul>
      </div>

      <!-- Score breakdown -->
      <div style='margin-bottom:32px'>
        <h2 style='margin:0 0 16px;font-size:16px;font-weight:700;color:#111'>📊 Din profil-fordeling</h2>
        <table style='width:100%;border-collapse:collapse'>
          {$score_bars_html}
        </table>
      </div>

      <!-- Secondary profile -->
      <div style='background:#f0f4ff;border-radius:12px;padding:20px;margin-bottom:32px;text-align:center'>
        <p style='margin:0;font-size:13px;color:#666'>Din næststørste profil er</p>
        <p style='margin:8px 0 0;font-size:18px;font-weight:700;color:#333'>{$secondary_icon} {$secondary_title}</p>
      </div>

      <!-- CTA -->
      <div style='text-align:center;background:linear-gradient(135deg,#ff6b35,#d63384);border-radius:14px;padding:32px'>
        <h2 style='color:#fff;margin:0 0 12px;font-size:20px'>Klar til at booste dine styrker?</h2>
        <p style='color:rgba(255,255,255,.85);margin:0 0 24px;font-size:14px'>Book en gratis samtale med os og find ud af hvordan Rezponz kan accelerere din vækst</p>
        <a href='{$cta_url}' style='display:inline-block;background:#fff;color:#ff6b35;font-weight:700;font-size:15px;text-decoration:none;padding:14px 32px;border-radius:999px'>
          {$cta_text}
        </a>
      </div>
    </div>

    <!-- Footer -->
    <div style='padding:24px 40px;border-top:1px solid #f0f0f0;text-align:center'>
      <p style='color:#999;font-size:12px;margin:0;line-height:1.8'>
        © Rezponz · rezponz.dk<br>
        Du modtager denne mail fordi du tog vores profil-quiz.
      </p>
    </div>
  </div>
</body>
</html>";
    }

    // ── HTML builder: admin-mail ──────────────────────────────────────────────

    private static function build_admin_html(
        string $name,
        string $phone,
        string $email,
        array  $winner,
        array  $scores,
        int    $sub_id
    ): string {
        $profile_icon  = esc_html( $winner['icon_emoji'] );
        $profile_title = esc_html( $winner['title'] );
        $profile_color = esc_attr( $winner['color'] );
        $score_rows    = '';

        foreach ( $scores as $slug => $score ) {
            $score_rows .= "<tr><td style='padding:6px 12px;font-size:13px;color:#555'>{$slug}</td><td style='padding:6px 12px;font-size:13px;font-weight:700;color:#111'>{$score} point</td></tr>";
        }

        $email_display = $email ?: '<em style="color:#999">Ikke angivet</em>';
        $admin_url     = admin_url( 'admin.php?page=rzpa-quiz-submissions' );
        $date          = wp_date( 'd.m.Y H:i' );

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif'>
  <div style='max-width:560px;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)'>
    <div style='background:{$profile_color};padding:28px 32px;text-align:center'>
      <span style='font-size:48px'>{$profile_icon}</span>
      <h1 style='color:#fff;margin:12px 0 0;font-size:20px;font-weight:800'>Ny quiz-besvarelse #{$sub_id}</h1>
    </div>
    <div style='padding:32px'>
      <table style='width:100%;border-collapse:collapse;margin-bottom:24px'>
        <tr style='background:#f9fafb'><td style='padding:10px 14px;font-weight:700;font-size:13px;color:#555;width:140px'>Navn</td><td style='padding:10px 14px;font-size:14px;color:#111'>" . esc_html( $name ) . "</td></tr>
        <tr><td style='padding:10px 14px;font-weight:700;font-size:13px;color:#555'>Telefon</td><td style='padding:10px 14px;font-size:14px;color:#111'>" . esc_html( $phone ) . "</td></tr>
        <tr style='background:#f9fafb'><td style='padding:10px 14px;font-weight:700;font-size:13px;color:#555'>Email</td><td style='padding:10px 14px;font-size:14px;color:#111'>{$email_display}</td></tr>
        <tr><td style='padding:10px 14px;font-weight:700;font-size:13px;color:#555'>Profil</td><td style='padding:10px 14px;font-size:14px;font-weight:700;color:{$profile_color}'>{$profile_icon} {$profile_title}</td></tr>
        <tr style='background:#f9fafb'><td style='padding:10px 14px;font-weight:700;font-size:13px;color:#555'>Tidspunkt</td><td style='padding:10px 14px;font-size:14px;color:#111'>{$date}</td></tr>
      </table>

      <h3 style='font-size:14px;font-weight:700;color:#333;margin:0 0 12px'>Score-fordeling</h3>
      <table style='width:100%;border-collapse:collapse;margin-bottom:24px;background:#f9fafb;border-radius:8px;overflow:hidden'>
        {$score_rows}
      </table>

      <div style='text-align:center'>
        <a href='{$admin_url}' style='display:inline-block;background:#0a0a14;color:#fff;text-decoration:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:700'>
          Se alle besvarelser →
        </a>
      </div>
    </div>
  </div>
</body>
</html>";
    }

    // ── Sender ─────────────────────────────────────────────────────────────────

    private static function send( string $to, string $subject, string $html ): void {
        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        add_filter( 'wp_mail_from',         fn() => self::FROM_EMAIL );
        add_filter( 'wp_mail_from_name',    fn() => self::FROM_NAME );

        wp_mail( $to, $subject, $html );

        remove_all_filters( 'wp_mail_content_type' );
        remove_all_filters( 'wp_mail_from' );
        remove_all_filters( 'wp_mail_from_name' );
    }
}
