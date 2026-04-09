<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array $data  set by RZPA_Quiz_Admin::page_pdf() */

$scores       = $data['scores'] ?? [];
$qa           = $data['qa']     ?? [];
$strengths    = json_decode( $data['strengths']    ?? '[]', true ) ?: [];
$thrives_with = json_decode( $data['thrives_with'] ?? '[]', true ) ?: [];
$develop      = json_decode( $data['develop_areas']?? '[]', true ) ?: [];
$maxScore     = $scores ? max( array_values( $scores ) ) : 1;
$profileNames = [
    'empatisk'  => 'Den Empatiske Lytter',
    'energisk'  => 'Energibomben',
    'analytisk' => 'Problemknuseren',
    'social'    => 'Netværksmesteren',
];

// Output a standalone HTML page (not wrapped in WP admin)
?><!DOCTYPE html>
<html lang="da">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profil-Rapport – <?php echo esc_html( $data['name'] ); ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
      background: #fff;
      color: #111827;
      font-size: 13px;
      line-height: 1.5;
    }

    .page {
      max-width: 780px;
      margin: 0 auto;
      padding: 40px 48px;
    }

    /* ── Header ── */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 32px;
      padding-bottom: 20px;
      border-bottom: 2px solid #f3f4f6;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logo-icon {
      width: 44px; height: 44px;
      background: linear-gradient(135deg,#e8590c,#d6336c);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px;
      flex-shrink: 0;
    }
    .logo-name  { font-size: 18px; font-weight: 900; color: #111827; }
    .logo-sub   { font-size: 11px; color: #9ca3af; }
    .report-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: #9ca3af;
      text-align: right;
    }
    .report-label span {
      display: block;
      font-size: 16px;
      font-weight: 800;
      color: #111827;
      letter-spacing: 0;
      text-transform: none;
      margin-top: 2px;
    }

    /* ── Contact block ── */
    .contact-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 12px;
      margin-bottom: 28px;
    }
    .contact-cell {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 12px 14px;
    }
    .contact-cell .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 4px; }
    .contact-cell .value { font-size: 14px; font-weight: 700; color: #111827; }

    /* ── Profile hero ── */
    .profile-hero {
      border-radius: 14px;
      padding: 24px 28px;
      margin-bottom: 28px;
      display: flex;
      align-items: flex-start;
      gap: 20px;
    }
    .profile-emoji { font-size: 52px; line-height: 1; flex-shrink: 0; }
    .profile-name  { font-size: 24px; font-weight: 900; color: #fff; line-height: 1.1; margin-bottom: 6px; }
    .profile-desc  { font-size: 13px; color: rgba(255,255,255,.85); line-height: 1.55; }
    .secondary-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.2);
      border-radius: 99px;
      padding: 4px 12px;
      font-size: 11px; font-weight: 700; color: #fff;
      margin-top: 10px;
    }

    /* ── Two-column layout ── */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }

    /* ── Cards ── */
    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 18px 20px;
    }
    .card-title {
      font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .6px; color: #9ca3af; margin-bottom: 12px;
    }

    /* ── Bullet list ── */
    .bullet-list { list-style: none; }
    .bullet-list li {
      font-size: 13px; color: #374151; line-height: 1.45;
      padding: 5px 0; border-bottom: 1px solid #f3f4f6;
      display: flex; gap: 8px;
    }
    .bullet-list li:last-child { border-bottom: none; }
    .bullet-list li::before { content: '→'; color: #e8590c; flex-shrink: 0; font-weight: 700; }

    /* ── Score bars ── */
    .score-row {
      display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
    }
    .score-label { font-size: 12px; color: #374151; font-weight: 600; width: 150px; flex-shrink: 0; }
    .score-track { flex: 1; background: #f3f4f6; border-radius: 99px; height: 9px; }
    .score-fill  { height: 9px; border-radius: 99px; background: #e8590c; }
    .score-num   { font-size: 12px; color: #9ca3af; width: 26px; text-align: right; }

    /* ── Q&A list ── */
    .qa-section { margin-bottom: 28px; }
    .qa-item { margin-bottom: 16px; page-break-inside: avoid; }
    .qa-question { font-size: 12px; font-weight: 700; color: #6b7280; margin-bottom: 4px; }
    .qa-answer   {
      font-size: 13px; color: #111827;
      padding: 8px 12px;
      border-left: 3px solid #e8590c;
      background: #fff8f5;
      border-radius: 0 8px 8px 0;
    }

    /* ── Footer ── */
    .footer {
      margin-top: 36px;
      padding-top: 20px;
      border-top: 1px solid #f3f4f6;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      color: #d1d5db;
    }

    /* ── Print overrides ── */
    @media print {
      body { background: #fff; font-size: 12px; }
      .page { padding: 20px 28px; max-width: 100%; }
      .no-print { display: none !important; }
      .two-col { grid-template-columns: 1fr 1fr; }
      .profile-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .score-fill   { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
<div class="page">

  <!-- ── Header ── -->
  <div class="header">
    <div class="logo">
      <div class="logo-icon">🎯</div>
      <div>
        <div class="logo-name">Rezponz</div>
        <div class="logo-sub">Customer Success DNA</div>
      </div>
    </div>
    <div class="report-label">
      Profil-Rapport
      <span><?php echo esc_html( wp_date( 'd. F Y', strtotime( $data['created_at'] ) ) ); ?></span>
    </div>
  </div>

  <!-- ── Contact ── -->
  <div class="contact-row">
    <div class="contact-cell">
      <div class="label">Navn</div>
      <div class="value"><?php echo esc_html( $data['name'] ); ?></div>
    </div>
    <div class="contact-cell">
      <div class="label">Telefon</div>
      <div class="value"><?php echo esc_html( $data['phone'] ?: '—' ); ?></div>
    </div>
    <div class="contact-cell">
      <div class="label">E-mail</div>
      <div class="value" style="font-size:13px"><?php echo esc_html( $data['email'] ?: '—' ); ?></div>
    </div>
  </div>

  <!-- ── Profile hero ── -->
  <?php $heroColor = $data['profile_color'] ?: '#e8590c'; ?>
  <div class="profile-hero" style="background:linear-gradient(135deg,<?php echo esc_attr($heroColor); ?>,<?php echo esc_attr($heroColor); ?>bb)">
    <div class="profile-emoji"><?php echo esc_html( $data['profile_icon'] ?? '' ); ?></div>
    <div>
      <div class="profile-name"><?php echo esc_html( $data['profile_title'] ?? '—' ); ?></div>
      <div class="profile-desc"><?php echo esc_html( $data['profile_desc'] ?? '' ); ?></div>
      <?php if ( $data['secondary_title'] ) : ?>
      <div class="secondary-badge">
        <?php echo esc_html( ( $data['secondary_icon'] ?? '' ) . ' ' . $data['secondary_title'] ); ?> (sekundær)
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── CTA ── -->
  <div style="background:#111827;border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#6b7280;margin-bottom:4px">Klar til næste skridt?</div>
      <div style="font-size:15px;font-weight:800;color:#fff;line-height:1.3">Din profil passer perfekt til et job hos Rezponz</div>
    </div>
    <a href="https://rezponz.dk/karriere-stillinger/"
       style="background:#f97316;color:#fff;text-decoration:none;border-radius:8px;padding:10px 22px;font-size:13px;font-weight:800;white-space:nowrap;letter-spacing:.2px;display:inline-block">
      Søg jobbet hos Rezponz →
    </a>
  </div>

  <!-- ── Strengths / Trives med / Udviklingsområder ── -->
  <div class="two-col">
    <div>
      <?php if ( $strengths ) : ?>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Styrker – og hvorfor de passer til Rezponz</div>
        <ul class="bullet-list">
          <?php foreach ( $strengths as $s ) : ?>
          <li><?php echo esc_html( $s ); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ( $thrives_with ) : ?>
      <div class="card">
        <div class="card-title">Du trives med – det har vi hos Rezponz</div>
        <ul class="bullet-list">
          <?php foreach ( $thrives_with as $t ) : ?>
          <li><?php echo esc_html( $t ); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <?php if ( $develop ) : ?>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Det kan udfordre dig – men det hjælper vi dig med</div>
        <ul class="bullet-list">
          <?php foreach ( $develop as $d2 ) : ?>
          <li><?php echo esc_html( $d2 ); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Score breakdown -->
      <div class="card">
        <div class="card-title">Score-fordeling</div>
        <?php foreach ( $scores as $key => $val ) :
          $pct   = $maxScore ? round( ( $val / $maxScore ) * 100 ) : 0;
          $label = $profileNames[ $key ] ?? $key;
        ?>
        <div class="score-row">
          <div class="score-label"><?php echo esc_html( $label ); ?></div>
          <div class="score-track"><div class="score-fill" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="score-num"><?php echo (int) $val; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── Q&A ── -->
  <?php if ( $qa ) : ?>
  <div class="qa-section">
    <div class="card-title" style="margin-bottom:14px">Svar på spørgsmål</div>
    <?php foreach ( $qa as $i => $item ) : ?>
    <div class="qa-item">
      <div class="qa-question"><?php echo esc_html( ( $i + 1 ) . '. ' . $item['question_text'] ); ?></div>
      <div class="qa-answer"><?php echo esc_html( $item['answer_text'] ); ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Footer ── -->
  <div class="footer">
    <div>Profil-Quiz · Rezponz · [rezponz_quiz]</div>
    <div>GDPR: <?php echo $data['consent'] ? 'Samtykke givet' : 'Intet samtykke'; ?></div>
  </div>

  <!-- Download button (hidden in print) -->
  <div class="no-print" style="text-align:center;margin-top:32px">
    <?php
    $download_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=rzpa_quiz_download_pdf&submission_id=' . (int) $data['id'] ),
        'rzpa_quiz_download_pdf'
    );
    ?>
    <a href="<?php echo esc_url( $download_url ); ?>"
       style="display:inline-block;background:#111827;color:#fff;text-decoration:none;border-radius:8px;padding:12px 28px;font-size:14px;font-weight:700">
      📄 Download PDF
    </a>
    <button onclick="window.close()"
            style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:12px 20px;font-size:14px;font-weight:600;cursor:pointer;margin-left:10px">
      Luk
    </button>
  </div>

</div>
</body>
</html>
