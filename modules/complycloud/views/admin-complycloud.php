<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

$settings   = get_option( RZPZ_ComplyCloud::OPTION_SETTINGS, [] );
$doc_state  = get_option( RZPZ_ComplyCloud::OPTION_STATE, [] );
$log        = get_option( RZPZ_ComplyCloud::OPTION_LOG, [] );

$tc_id      = $settings['trust_center_id'] ?? '4562155b-e994-4899-884a-b4cc2a199d87';
$notify_email = $settings['notify_email']  ?? get_option( 'admin_email' );
$notify_name  = $settings['notify_name']   ?? 'Rezponz Analytics';
$frequency    = $settings['frequency']     ?? 'daily';
$extra_emails = $settings['extra_emails']  ?? '';
$trust_center_url = 'https://app.complycloud.com/public/trust-center?id=' . esc_attr( $tc_id );

$freq_labels  = [ 'hourly' => 'Hver time', 'twicedaily' => '2x dagligt', 'daily' => 'Dagligt', 'weekly' => 'Ugentligt' ];

// Flash messages
$saved    = isset( $_GET['saved'] );
$reset    = isset( $_GET['reset'] );
$check    = $_GET['check'] ?? '';
$test_to  = isset( $_GET['test_to'] ) ? rawurldecode( $_GET['test_to'] ) : '';
?>
<div class="wrap rzpz-cc-wrap">

  <style>
    .rzpz-cc-wrap { max-width: 900px; font-family: -apple-system,"Segoe UI",sans-serif; }
    .cc-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
    .cc-header h1 { font-size:22px; font-weight:700; color:#1d2327; margin:0; }
    .cc-header p  { margin:4px 0 0; font-size:13px; color:#666; }
    .cc-grid { display:grid; grid-template-columns:1fr 340px; gap:20px; align-items:start; }
    @media(max-width:780px){ .cc-grid { grid-template-columns:1fr; } }

    /* Cards */
    .cc-card { background:#fff; border:1px solid #ddd; border-radius:10px; overflow:hidden; margin-bottom:16px; }
    .cc-card-head { padding:14px 18px; border-bottom:1px solid #eee; background:#fafafa; }
    .cc-card-head h2 { font-size:14px; font-weight:700; margin:0; color:#1d2327; }
    .cc-card-body { padding:18px; }

    /* Form */
    .cc-field { margin-bottom:14px; }
    .cc-field label { display:block; font-size:12px; font-weight:600; color:#444; margin-bottom:5px; text-transform:uppercase; letter-spacing:.4px; }
    .cc-field input[type=text], .cc-field input[type=email], .cc-field select, .cc-field textarea {
      width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:13px; box-sizing:border-box;
    }
    .cc-field input:focus, .cc-field select:focus, .cc-field textarea:focus { outline:none; border-color:#0073aa; box-shadow:0 0 0 2px rgba(0,115,170,.15); }
    .cc-field .cc-hint { font-size:11px; color:#888; margin-top:4px; }
    .cc-btn-primary { background:#0073aa; color:#fff; border:none; padding:9px 18px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; }
    .cc-btn-primary:hover { background:#005a87; }
    .cc-btn-ghost { background:#fff; color:#444; border:1px solid #ccc; padding:7px 14px; border-radius:6px; font-size:12px; cursor:pointer; }
    .cc-btn-ghost:hover { border-color:#0073aa; color:#0073aa; }
    .cc-btn-danger { background:#dc3232; color:#fff; border:none; padding:7px 14px; border-radius:6px; font-size:12px; cursor:pointer; }

    /* Status badges */
    .cc-badge { display:inline-block; font-size:11px; font-weight:700; padding:2px 9px; border-radius:10px; }
    .cc-badge-ok      { background:#d4edda; color:#155724; }
    .cc-badge-changed { background:#cce5ff; color:#004085; }
    .cc-badge-error   { background:#f8d7da; color:#721c24; }
    .cc-badge-new     { background:#d4edda; color:#155724; }
    .cc-badge-removed { background:#f8d7da; color:#721c24; }

    /* Document list */
    .cc-doc-item { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f0f0f0; gap:10px; }
    .cc-doc-item:last-child { border-bottom:none; }
    .cc-doc-title { font-size:13px; font-weight:600; color:#1d2327; }
    .cc-doc-date  { font-size:11px; color:#888; margin-top:2px; }
    .cc-doc-link  { font-size:11px; color:#0073aa; text-decoration:none; white-space:nowrap; }
    .cc-doc-link:hover { text-decoration:underline; }

    /* Log */
    .cc-log-item { display:flex; align-items:center; gap:10px; padding:7px 0; border-bottom:1px solid #f5f5f5; font-size:12px; }
    .cc-log-item:last-child { border-bottom:none; }
    .cc-log-time { color:#888; white-space:nowrap; min-width:130px; }
    .cc-log-msg  { color:#444; }
    .cc-log-changed { font-weight:600; color:#0073aa; }

    /* Notice */
    .cc-notice { padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:16px; }
    .cc-notice-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .cc-notice-info    { background:#cce5ff; color:#004085; border:1px solid #b8daff; }
    .cc-notice-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

    /* KPI row */
    .cc-kpi-row { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
    .cc-kpi { background:#f9f9f9; border:1px solid #e5e5e5; border-radius:8px; padding:12px 16px; flex:1; min-width:100px; text-align:center; }
    .cc-kpi-val   { font-size:24px; font-weight:700; color:#1d2327; }
    .cc-kpi-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }

    .cc-next-run  { font-size:12px; color:#888; margin-top:8px; }
    .cc-separator { border:none; border-top:1px solid #eee; margin:14px 0; }
  </style>

  <!-- ── Header ─────────────────────────────────────────────────────── -->
  <div class="cc-header">
    <div>
      <h1>🔒 ComplyCloud Monitor</h1>
      <p>Automatisk overvågning af dokumentopdateringer i Rezponz' Trust Center</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <a href="<?= esc_url( $trust_center_url ) ?>" target="_blank" class="cc-btn-ghost">↗ Åbn Trust Center</a>
      <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" style="display:inline">
        <?php wp_nonce_field( 'rzpz_cc_test_email' ) ?>
        <input type="hidden" name="action" value="rzpz_cc_test_email">
        <button type="submit" class="cc-btn-ghost" style="color:#0073aa;border-color:#0073aa;">
          📧 Send testmail
        </button>
      </form>
      <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" style="display:inline">
        <?php wp_nonce_field( 'rzpz_cc_check_now' ) ?>
        <input type="hidden" name="action" value="rzpz_cc_check_now">
        <button type="submit" class="cc-btn-primary">⟳ Tjek nu</button>
      </form>
    </div>
  </div>

  <!-- ── Flash notices ──────────────────────────────────────────────── -->
  <?php if ( $saved ) : ?>
    <div class="cc-notice cc-notice-success">✓ Indstillinger gemt.</div>
  <?php endif; ?>
  <?php if ( $reset ) : ?>
    <div class="cc-notice cc-notice-info">↺ Dokument-tilstand nulstillet. Næste tjek vil gemme aktuel tilstand som baseline.</div>
  <?php endif; ?>
  <?php if ( $check === 'checked_changes' ) : ?>
    <div class="cc-notice cc-notice-info">✓ Tjek gennemført — ændringer fundet og notifikationsmail sendt.</div>
  <?php elseif ( $check === 'checked_no_changes' ) : ?>
    <div class="cc-notice cc-notice-success">✓ Tjek gennemført — ingen ændringer fundet.</div>
  <?php elseif ( $check === 'check_error' ) : ?>
    <div class="cc-notice cc-notice-error">✗ Tjek fejlede. Se loggen nedenfor.</div>
  <?php elseif ( $check === 'test_sent' ) : ?>
    <div class="cc-notice cc-notice-success">
      📧 Testmail sendt til <strong><?= esc_html( $test_to ) ?></strong> — tjek din indbakke (evt. spam).
    </div>
  <?php elseif ( $check === 'test_failed' ) : ?>
    <div class="cc-notice cc-notice-error">✗ Testmail kunne ikke sendes. Tjek SMTP-indstillinger under Indstillinger.</div>
  <?php endif; ?>

  <div class="cc-grid">

    <!-- ── Left column ────────────────────────────────────────────── -->
    <div>

      <!-- KPIs -->
      <?php
        $last_log     = $log[0] ?? null;
        $total_docs   = $last_log['total'] ?? count( $doc_state );
        $last_changed = $last_log['changed'] ?? 0;
        $last_time    = $last_log ? RZPZ_ComplyCloud::fmt_date( $last_log['time'] ) : '–';
        $last_status  = $last_log['status'] ?? 'ok';
      ?>
      <div class="cc-kpi-row">
        <div class="cc-kpi">
          <div class="cc-kpi-val"><?= esc_html( (string) $total_docs ) ?></div>
          <div class="cc-kpi-label">Dokumenter</div>
        </div>
        <div class="cc-kpi">
          <div class="cc-kpi-val" style="color:<?= $last_changed > 0 ? '#0073aa' : '#1d2327' ?>">
            <?= esc_html( (string) $last_changed ) ?>
          </div>
          <div class="cc-kpi-label">Seneste ændringer</div>
        </div>
        <div class="cc-kpi">
          <div class="cc-kpi-val" style="font-size:14px;padding-top:4px">
            <span class="cc-badge cc-badge-<?= esc_attr( $last_status ) ?>">
              <?= esc_html( match( $last_status ) { 'ok' => 'OK', 'changed' => 'Ændringer', 'error' => 'Fejl', default => $last_status } ) ?>
            </span>
          </div>
          <div class="cc-kpi-label">Sidst tjekket: <?= esc_html( $last_time ) ?></div>
        </div>
      </div>

      <!-- Documents -->
      <div class="cc-card">
        <div class="cc-card-head">
          <h2>📄 Dokumenter i Trust Center</h2>
        </div>
        <div class="cc-card-body">
          <?php if ( empty( $doc_state ) ) : ?>
            <p style="color:#888;font-size:13px;margin:0">
              Ingen dokumenter gemt endnu — klik "Tjek nu" for at hente den aktuelle tilstand.
            </p>
          <?php else : ?>
            <?php foreach ( $doc_state as $id => $doc ) :
              $link = $id ? 'https://app.complycloud.com/public/documents/' . esc_attr( $id ) : '';
            ?>
              <div class="cc-doc-item">
                <div>
                  <div class="cc-doc-title"><?= esc_html( $doc['title'] ?? 'Ukendt' ) ?></div>
                  <div class="cc-doc-date">Sidst ændret: <?= esc_html( RZPZ_ComplyCloud::fmt_date( $doc['lastModified'] ?? '' ) ) ?></div>
                </div>
                <?php if ( $link ) : ?>
                  <a href="<?= esc_url( $trust_center_url ) ?>" target="_blank" class="cc-doc-link">Se dokument ↗</a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Log -->
      <div class="cc-card">
        <div class="cc-card-head">
          <h2>📋 Tjek-log (seneste 10)</h2>
        </div>
        <div class="cc-card-body">
          <?php if ( empty( $log ) ) : ?>
            <p style="color:#888;font-size:13px;margin:0">Ingen log endnu.</p>
          <?php else :
            foreach ( array_slice( $log, 0, 10 ) as $entry ) :
              $badge = match( $entry['status'] ) {
                'changed' => '<span class="cc-badge cc-badge-changed">Ændringer</span>',
                'error'   => '<span class="cc-badge cc-badge-error">Fejl</span>',
                default   => '<span class="cc-badge cc-badge-ok">OK</span>',
              };
            ?>
            <div class="cc-log-item">
              <span class="cc-log-time"><?= esc_html( RZPZ_ComplyCloud::fmt_date( $entry['time'] ) ) ?></span>
              <?= $badge ?>
              <span class="cc-log-msg">
                <?= esc_html( $entry['message'] ?? '' ) ?>
                <?php if ( $entry['changed'] > 0 ) : ?>
                  &mdash; <span class="cc-log-changed"><?= (int) $entry['changed'] ?> ændret</span>
                <?php endif; ?>
              </span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>

    <!-- ── Right column: settings ─────────────────────────────────── -->
    <div>

      <div class="cc-card">
        <div class="cc-card-head">
          <h2>⚙️ Indstillinger</h2>
        </div>
        <div class="cc-card-body">
          <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <?php wp_nonce_field( 'rzpz_cc_save' ) ?>
            <input type="hidden" name="action" value="rzpz_cc_save">

            <div class="cc-field">
              <label>Trust Center ID</label>
              <input type="text" name="trust_center_id" value="<?= esc_attr( $tc_id ) ?>">
              <p class="cc-hint">Det unikke ID fra ComplyCloud-URL'en</p>
            </div>

            <div class="cc-field">
              <label>Notifikationsmail (primær)</label>
              <input type="email" name="notify_email" value="<?= esc_attr( $notify_email ) ?>" placeholder="din@email.dk">
            </div>

            <div class="cc-field">
              <label>Afsendernavn</label>
              <input type="text" name="notify_name" value="<?= esc_attr( $notify_name ) ?>">
            </div>

            <div class="cc-field">
              <label>Ekstra modtagere</label>
              <textarea name="extra_emails" rows="3" placeholder="en@email.dk&#10;to@email.dk"><?= esc_textarea( $extra_emails ) ?></textarea>
              <p class="cc-hint">Én email pr. linje (eller kommasepareret)</p>
            </div>

            <div class="cc-field">
              <label>Tjek-frekvens</label>
              <select name="frequency">
                <?php foreach ( $freq_labels as $val => $lbl ) : ?>
                  <option value="<?= esc_attr( $val ) ?>" <?php selected( $frequency, $val ) ?>><?= esc_html( $lbl ) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="cc-btn-primary" style="width:100%">Gem indstillinger</button>
          </form>

          <hr class="cc-separator">

          <p class="cc-next-run">⏱ Næste automatiske tjek: <strong><?= esc_html( RZPZ_ComplyCloud::get_next_run_label() ) ?></strong></p>

          <hr class="cc-separator">

          <p style="font-size:12px;color:#888;margin:0 0 8px">
            Nulstil gemt dokumenttilstand, hvis du vil starte overvågningen forfra (første tjek sender ingen mail).
          </p>
          <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <?php wp_nonce_field( 'rzpz_cc_reset_state' ) ?>
            <input type="hidden" name="action" value="rzpz_cc_reset_state">
            <button type="submit" class="cc-btn-danger"
              onclick="return confirm('Er du sikker? Gemt tilstand slettes og næste tjek sender ingen mail.')"
              style="width:100%">↺ Nulstil dokumenttilstand</button>
          </form>
        </div>
      </div>

      <!-- Info box -->
      <div class="cc-card">
        <div class="cc-card-head"><h2>ℹ️ Sådan virker det</h2></div>
        <div class="cc-card-body" style="font-size:12px;color:#555;line-height:1.7">
          <ol style="margin:0;padding-left:18px">
            <li>Plugin henter dokumentlisten fra ComplyCloud API</li>
            <li>Sammenlignes med sidst kendte tilstand</li>
            <li>Er et dokument opdateret (ny <code>lastModified</code>), tilføjet eller fjernet…</li>
            <li>…sendes en notifikationsmail med detaljer om hvad der ændrede sig</li>
          </ol>
          <hr class="cc-separator">
          <p style="margin:0"><strong>API endpoint:</strong><br>
          <code style="font-size:10px;word-break:break-all">
            https://api.prod.complycloud.com/public/trust-center/<?= esc_html( $tc_id ) ?>
          </code></p>
        </div>
      </div>

    </div>
  </div>

</div>
