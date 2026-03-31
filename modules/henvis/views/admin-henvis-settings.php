<?php if ( ! defined( 'ABSPATH' ) ) exit;

$managers    = RZPZ_Henvis::get_managers();
$form_config = RZPZ_Henvis::get_form_config();
$extra_recip = RZPZ_Henvis::get_extra_recipients();
$smtp        = RZPZ_Henvis::get_smtp();
$tab         = sanitize_key( $_GET['tab'] ?? 'managers' );

// Notice flags
$saved       = ! empty( $_GET['saved'] );
$deleted     = ! empty( $_GET['deleted'] );
$smtp_saved  = ! empty( $_GET['smtp_saved'] );
$recip_saved = ! empty( $_GET['recip_saved'] );
$recip_del   = ! empty( $_GET['recip_deleted'] );
$test_sent   = isset( $_GET['test_sent'] ) ? intval( $_GET['test_sent'] ) : null;
$error       = sanitize_text_field( $_GET['error'] ?? '' );

$base_url = admin_url( 'admin.php?page=rzpz-henvis-settings' );

// WordPress pages for QR dropdown
$pages = get_pages( [ 'post_status' => 'publish', 'number' => 50 ] );
?>
<style>
#wpbody-content, #wpcontent { background:#0d0d0d !important; }
.rzpz-hs-page { padding:20px; background:#0d0d0d; min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; color:#e0e0e0; }
.rzpz-hs-header { display:flex; align-items:center; gap:12px; margin-bottom:8px; flex-wrap:wrap; }
.rzpz-hs-title  { font-size:22px; font-weight:700; margin:0; color:#fff; }
/* Tabs */
.rzpz-tabs { display:flex; gap:0; margin-bottom:24px; border-bottom:2px solid #2a2a2a; }
.rzpz-tabs a { padding:10px 20px; font-size:13px; font-weight:600; color:#666; text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; }
.rzpz-tabs a.active { color:#CCFF00; border-bottom-color:#CCFF00; }
.rzpz-tabs a:hover  { color:#e0e0e0; }
/* Card */
.rzpz-hs-card { background:#1a1a1a; border:1px solid #2a2a2a; border-radius:12px; padding:24px; margin-bottom:20px; }
.rzpz-hs-card h2 { font-size:15px; font-weight:700; color:#fff; margin:0 0 16px 0; }
.rzpz-hs-card p  { color:#888; font-size:13px; margin:0 0 14px 0; line-height:1.6; }
/* Notices */
.rzpz-notice { padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }
.rzpz-notice.success { background:#0a2e0a; color:#4ade80; border:1px solid #4ade8040; }
.rzpz-notice.info    { background:#0a1a2e; color:#60a5fa; border:1px solid #60a5fa40; }
.rzpz-notice.error   { background:#2d0a0a; color:#f87171; border:1px solid #f8717140; }
/* Form elements */
.rzpz-field-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; align-items:flex-end; }
.rzpz-field-row input[type=text],
.rzpz-field-row input[type=email],
.rzpz-field-row input[type=password],
.rzpz-field-row input[type=number],
.rzpz-field-row input[type=url],
.rzpz-field-row select,
.rzpz-field     input,
.rzpz-field     select,
.rzpz-field     textarea {
    background:#111; border:1px solid #333; color:#e0e0e0; padding:8px 12px; border-radius:6px; font-size:13px; min-width:160px; box-sizing:border-box;
}
.rzpz-field { display:flex; flex-direction:column; gap:5px; flex:1; min-width:160px; }
.rzpz-field label { font-size:12px; color:#aaa; font-weight:600; }
.rzpz-field input:focus, .rzpz-field select:focus, .rzpz-field textarea:focus,
.rzpz-field-row input:focus, .rzpz-field-row select:focus { outline:none; border-color:#CCFF00; }
.rzpz-field textarea { resize:vertical; min-height:80px; width:100%; }
/* Buttons */
.rzpz-btn-primary { background:#CCFF00; color:#0d0d0d; border:none; border-radius:6px; padding:8px 18px; font-weight:700; cursor:pointer; font-size:13px; white-space:nowrap; }
.rzpz-btn-primary:hover { background:#bbee00; }
.rzpz-btn-ghost  { background:transparent; color:#888; border:1px solid #333; border-radius:6px; padding:8px 18px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-block; white-space:nowrap; }
.rzpz-btn-ghost:hover { color:#e0e0e0; border-color:#555; }
.rzpz-btn-danger { background:#ef444420; color:#ef4444; border:1px solid #ef444440; border-radius:6px; padding:5px 12px; font-size:12px; cursor:pointer; font-weight:600; }
/* Manager table */
table.rzpz-mgr-table { width:100%; border-collapse:collapse; }
.rzpz-mgr-table th { background:#111; color:#aaa; font-size:11px; text-transform:uppercase; padding:8px 12px; text-align:left; border-bottom:1px solid #2a2a2a; letter-spacing:.5px; }
.rzpz-mgr-table td { padding:10px 12px; border-bottom:1px solid #1f1f1f; font-size:13px; color:#e0e0e0; vertical-align:middle; }
.rzpz-mgr-table tr:last-child td { border-bottom:none; }
.rzpz-mgr-table tr:hover td { background:#222; }
/* Form config table */
.rzpz-fc-table { width:100%; border-collapse:collapse; }
.rzpz-fc-table th { background:#111; color:#aaa; font-size:11px; text-transform:uppercase; padding:8px 12px; text-align:left; border-bottom:1px solid #2a2a2a; letter-spacing:.5px; }
.rzpz-fc-table td { padding:8px 12px; border-bottom:1px solid #1f1f1f; font-size:13px; vertical-align:middle; }
.rzpz-fc-table input[type=text] { background:#111; border:1px solid #333; color:#e0e0e0; padding:5px 8px; border-radius:4px; font-size:12px; width:100%; box-sizing:border-box; }
.rzpz-fc-table input[type=checkbox] { width:16px; height:16px; accent-color:#CCFF00; cursor:pointer; }
.rzpz-fc-table .core-badge { background:#1a2a1a; color:#4ade80; font-size:10px; padding:2px 6px; border-radius:10px; }
/* Shortcode box */
.rzpz-shortcode-box { background:#111; border:2px dashed #CCFF0060; border-radius:10px; padding:20px; text-align:center; }
.rzpz-shortcode-box code { background:#1e1e1e; color:#CCFF00; padding:10px 24px; border-radius:8px; font-size:18px; font-weight:700; border:1px solid #333; display:inline-block; margin:10px 0; cursor:pointer; letter-spacing:1px; }
/* SMTP badge */
.smtp-status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
.smtp-status-badge.active   { background:#0a2e0a; color:#4ade80; }
.smtp-status-badge.inactive { background:#2a2a2a; color:#888; }
/* QR output */
#rzpz-qr-result { margin-top:20px; text-align:center; display:none; }
#rzpz-qr-result img { border-radius:8px; border:4px solid #CCFF00; width:220px; height:220px; }
.rzpz-section-header { font-size:11px; text-transform:uppercase; color:#555; letter-spacing:.8px; font-weight:700; margin:16px 0 8px 0; }
</style>

<div class="rzpz-hs-page">

  <div class="rzpz-hs-header">
    <a href="<?php echo esc_url( admin_url('admin.php?page=rzpz-henvis') ); ?>" style="color:#666;text-decoration:none;font-size:13px">← Tilbage til Henvisninger</a>
    <h1 class="rzpz-hs-title" style="margin-left:4px">⚙️ Henvis Din Ven – Indstillinger</h1>
  </div>

  <!-- Tabs -->
  <div class="rzpz-tabs">
    <a href="<?php echo esc_url( $base_url . '&tab=managers' ); ?>" class="<?php echo $tab === 'managers' ? 'active' : ''; ?>">👥 Managers</a>
    <a href="<?php echo esc_url( $base_url . '&tab=emails' ); ?>"   class="<?php echo $tab === 'emails'   ? 'active' : ''; ?>">📧 Email</a>
    <a href="<?php echo esc_url( $base_url . '&tab=form' ); ?>"     class="<?php echo $tab === 'form'     ? 'active' : ''; ?>">📝 Formular</a>
    <a href="<?php echo esc_url( $base_url . '&tab=qr' ); ?>"       class="<?php echo $tab === 'qr'       ? 'active' : ''; ?>">📱 QR Kode</a>
  </div>

  <?php
  // ── TAB: MANAGERS ─────────────────────────────────────────────────────────
  if ( $tab === 'managers' ) :
  ?>

  <?php if ( $saved )   : ?><div class="rzpz-notice success">✅ Manager gemt.</div><?php endif; ?>
  <?php if ( $deleted ) : ?><div class="rzpz-notice success">🗑 Manager slettet.</div><?php endif; ?>

  <!-- Shortcode reminder -->
  <div class="rzpz-hs-card">
    <h2>📋 Shortcode til formularen</h2>
    <div class="rzpz-shortcode-box">
      <div style="color:#888;font-size:13px;margin-bottom:6px">Indsæt på den side hvor formularen skal vises:</div>
      <code onclick="navigator.clipboard.writeText('[rezponz_henvis_ven]');this.textContent='✅ Kopieret!';setTimeout(()=>this.textContent='[rezponz_henvis_ven]',2000)">[rezponz_henvis_ven]</code>
      <div style="color:#555;font-size:12px;margin-top:6px">Klik for at kopiere</div>
    </div>
  </div>

  <!-- Add manager -->
  <div class="rzpz-hs-card">
    <h2>➕ Tilføj Senior Manager</h2>
    <p>Managers vises i dropdown-menuen på henvis-formularen. De modtager email når en medarbejder sender en henvisning.</p>
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <input type="hidden" name="action" value="rzpz_henvis_save_manager">
      <?php wp_nonce_field( 'rzpz_henvis_save_manager' ); ?>
      <div class="rzpz-field-row">
        <input type="text"  name="mgr_name"  placeholder="Fornavn (fx Kasper)"                    required style="flex:1">
        <input type="text"  name="mgr_label" placeholder="Dropdown-tekst (fx Kasper – Telenor)"   required style="flex:2">
        <input type="email" name="mgr_email" placeholder="Email (fx kapj@rezponz.dk)"             required style="flex:2">
        <button type="submit" class="rzpz-btn-primary">+ Tilføj</button>
      </div>
    </form>
  </div>

  <!-- Manager list -->
  <div class="rzpz-hs-card">
    <h2>👥 Nuværende Senior Managers (<?php echo count($managers); ?>)</h2>
    <?php if ( empty($managers) ) : ?>
      <p>Ingen managers endnu. Tilføj en ovenfor.</p>
    <?php else : ?>
    <table class="rzpz-mgr-table">
      <thead><tr><th>Navn</th><th>Dropdown-tekst</th><th>Email</th><th></th></tr></thead>
      <tbody>
      <?php foreach ( $managers as $key => $m ) : ?>
        <tr>
          <td><?php echo esc_html( $m['name'] ); ?></td>
          <td><?php echo esc_html( $m['label'] ); ?></td>
          <td><a href="mailto:<?php echo esc_attr($m['email']); ?>" style="color:#60a5fa;text-decoration:none"><?php echo esc_html( $m['email'] ); ?></a></td>
          <td>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Slet denne manager?')">
              <input type="hidden" name="action"  value="rzpz_henvis_delete_manager">
              <input type="hidden" name="mgr_key" value="<?php echo esc_attr($key); ?>">
              <?php wp_nonce_field( 'rzpz_henvis_delete_manager' ); ?>
              <button type="submit" class="rzpz-btn-danger">🗑 Slet</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php
  // ── TAB: EMAIL ────────────────────────────────────────────────────────────
  elseif ( $tab === 'emails' ) :
  ?>

  <?php if ( $smtp_saved )  : ?><div class="rzpz-notice success">✅ SMTP-indstillinger gemt.</div><?php endif; ?>
  <?php if ( $recip_saved ) : ?><div class="rzpz-notice success">✅ Email-modtager tilføjet.</div><?php endif; ?>
  <?php if ( $recip_del )   : ?><div class="rzpz-notice success">🗑 Email-modtager slettet.</div><?php endif; ?>
  <?php if ( $test_sent === 1 ) : ?><div class="rzpz-notice success">📬 Test-email sendt! Tjek din indbakke.</div><?php endif; ?>
  <?php if ( $test_sent === 0 ) : ?><div class="rzpz-notice error">❌ Test-email fejlede. Tjek SMTP-indstillingerne nedenfor og prøv igen.</div><?php endif; ?>
  <?php if ( $error === 'recip' ) : ?><div class="rzpz-notice error">❌ Ugyldigt navn eller email-adresse.</div><?php endif; ?>

  <!-- SMTP Configuration -->
  <div class="rzpz-hs-card">
    <h2>🔌 SMTP-konfiguration
      <span class="smtp-status-badge <?php echo $smtp['enabled'] && $smtp['host'] ? 'active' : 'inactive'; ?>" style="margin-left:10px;font-size:11px">
        <?php echo $smtp['enabled'] && $smtp['host'] ? '● Aktiv' : '○ Ikke aktiv (bruger standard PHP mail)'; ?>
      </span>
    </h2>
    <p>Konfigurer SMTP for pålidelig email-afsendelse. Uden SMTP bruger WordPress standard PHP mail(), som ofte blokeres af hosting-udbydere.</p>
    <p style="color:#f59e0b">💡 <strong>Anbefalede SMTP-udbydere:</strong> Brevo (gratis op til 300/dag), Mailgun, SendGrid eller din virksomheds Exchange/Gmail.</p>

    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <input type="hidden" name="action" value="rzpz_henvis_save_smtp">
      <?php wp_nonce_field( 'rzpz_henvis_save_smtp' ); ?>

      <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;padding:12px 16px;background:#111;border-radius:8px;border:1px solid #2a2a2a">
        <input type="checkbox" name="smtp_enabled" id="smtp_enabled" value="1" style="width:18px;height:18px;accent-color:#CCFF00;cursor:pointer" <?php checked( $smtp['enabled'] ); ?>>
        <label for="smtp_enabled" style="font-size:14px;font-weight:700;color:#e0e0e0;cursor:pointer">Aktivér SMTP</label>
      </div>

      <div class="rzpz-section-header">SMTP Server</div>
      <div class="rzpz-field-row">
        <div class="rzpz-field" style="flex:3">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" value="<?php echo esc_attr( $smtp['host'] ); ?>" placeholder="smtp.brevo.com · smtp.mailgun.org · smtp.gmail.com">
        </div>
        <div class="rzpz-field" style="flex:1;min-width:100px">
          <label>Port</label>
          <input type="number" name="smtp_port" value="<?php echo esc_attr( $smtp['port'] ?: '587' ); ?>" placeholder="587">
        </div>
        <div class="rzpz-field" style="flex:1;min-width:120px">
          <label>Kryptering</label>
          <select name="smtp_secure">
            <option value="tls"  <?php selected( $smtp['secure'], 'tls' ); ?>>TLS (port 587)</option>
            <option value="ssl"  <?php selected( $smtp['secure'], 'ssl' ); ?>>SSL (port 465)</option>
            <option value=""     <?php selected( $smtp['secure'], '' );    ?>>Ingen</option>
          </select>
        </div>
      </div>

      <div class="rzpz-section-header">Login</div>
      <div class="rzpz-field-row">
        <div class="rzpz-field" style="flex:2">
          <label>Brugernavn / Email</label>
          <input type="text" name="smtp_user" value="<?php echo esc_attr( $smtp['user'] ); ?>" placeholder="din@email.dk eller API-nøgle" autocomplete="off">
        </div>
        <div class="rzpz-field" style="flex:2">
          <label>Adgangskode / API-nøgle <?php echo $smtp['pass'] ? '<span style="color:#4ade80;font-size:11px">(gemt)</span>' : ''; ?></label>
          <input type="password" name="smtp_pass" value="" placeholder="<?php echo $smtp['pass'] ? '••••••••••• (lad stå tom for at beholde)' : 'Adgangskode eller API-nøgle'; ?>" autocomplete="new-password">
        </div>
      </div>

      <div class="rzpz-section-header">Afsender</div>
      <div class="rzpz-field-row">
        <div class="rzpz-field" style="flex:2">
          <label>Fra-email</label>
          <input type="text" name="smtp_from_email" value="<?php echo esc_attr( $smtp['from_email'] ); ?>" placeholder="no-reply@rezponz.dk">
        </div>
        <div class="rzpz-field" style="flex:2">
          <label>Fra-navn</label>
          <input type="text" name="smtp_from_name" value="<?php echo esc_attr( $smtp['from_name'] ); ?>" placeholder="Rezponz Marketing Platform">
        </div>
      </div>

      <div style="display:flex;gap:12px;align-items:center;margin-top:8px;flex-wrap:wrap">
        <button type="submit" class="rzpz-btn-primary">💾 Gem SMTP-indstillinger</button>
        <div style="color:#555;font-size:12px">Brug "Send test-email" nedenfor for at bekræfte at det virker.</div>
      </div>
    </form>
  </div>

  <!-- Test email -->
  <div class="rzpz-hs-card">
    <h2>🧪 Send test-email</h2>
    <p>Send en test for at bekræfte at email-konfigurationen fungerer korrekt.</p>
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <input type="hidden" name="action" value="rzpz_henvis_test_email">
      <?php wp_nonce_field( 'rzpz_henvis_test_email' ); ?>
      <div class="rzpz-field-row" style="align-items:flex-end">
        <div class="rzpz-field" style="flex:2">
          <label>Send test til</label>
          <input type="email" name="test_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" placeholder="din@email.dk" required>
        </div>
        <button type="submit" class="rzpz-btn-primary">📤 Send test-email</button>
      </div>
    </form>
  </div>

  <!-- Extra email recipients -->
  <div class="rzpz-hs-card">
    <h2>📬 Extra email-modtagere (CC)</h2>
    <p>Disse modtager automatisk en kopi (CC) af alle 3 emails der sendes ved en ny henvisning: til manager, til vennen og til medarbejderen.</p>

    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin-bottom:20px">
      <input type="hidden" name="action" value="rzpz_henvis_save_extra_recip">
      <?php wp_nonce_field( 'rzpz_henvis_save_extra_recip' ); ?>
      <div class="rzpz-field-row" style="align-items:flex-end">
        <div class="rzpz-field">
          <label>Navn</label>
          <input type="text" name="recip_name" placeholder="Fx Lie" required>
        </div>
        <div class="rzpz-field" style="flex:2">
          <label>Email</label>
          <input type="email" name="recip_email" placeholder="lie@rezponz.dk" required>
        </div>
        <button type="submit" class="rzpz-btn-primary">+ Tilføj</button>
      </div>
    </form>

    <?php if ( empty($extra_recip) ) : ?>
      <p style="color:#555;text-align:center;padding:20px 0">Ingen ekstra modtagere tilføjet endnu.</p>
    <?php else : ?>
    <table class="rzpz-mgr-table">
      <thead><tr><th>Navn</th><th>Email</th><th>Modtager</th><th></th></tr></thead>
      <tbody>
      <?php foreach ( $extra_recip as $i => $r ) : ?>
        <tr>
          <td><?php echo esc_html( $r['name'] ); ?></td>
          <td><a href="mailto:<?php echo esc_attr($r['email']); ?>" style="color:#60a5fa;text-decoration:none"><?php echo esc_html( $r['email'] ); ?></a></td>
          <td>
            <span style="font-size:12px;color:#888">CC på alle 3 mails</span>
          </td>
          <td>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Fjern denne modtager?')">
              <input type="hidden" name="action"    value="rzpz_henvis_delete_extra_recip">
              <input type="hidden" name="recip_idx" value="<?php echo $i; ?>">
              <?php wp_nonce_field( 'rzpz_henvis_delete_extra_recip' ); ?>
              <button type="submit" class="rzpz-btn-danger">🗑 Fjern</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php
  // ── TAB: FORM ─────────────────────────────────────────────────────────────
  elseif ( $tab === 'form' ) :
    $fc = $form_config;
    $fd = $fc['fields'];
    $core_fields = [ 'referrer_name', 'referrer_email', 'friend_name', 'friend_email' ];
    $field_labels_static = [
        'referrer_name'  => 'Dit navn',
        'referrer_phone' => 'Din telefon',
        'referrer_email' => 'Din email',
        'friend_name'    => 'Vennens navn',
        'friend_phone'   => 'Vennens telefon',
        'friend_email'   => 'Vennens email',
    ];
  ?>

  <?php if ( $saved ) : ?><div class="rzpz-notice success">✅ Formular-indstillinger gemt.</div><?php endif; ?>

  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
    <input type="hidden" name="action" value="rzpz_henvis_save_form_config">
    <?php wp_nonce_field( 'rzpz_henvis_save_form_config' ); ?>

    <!-- Texts & titles -->
    <div class="rzpz-hs-card">
      <h2>📝 Tekster &amp; Overskrifter</h2>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="rzpz-field">
          <label>Formular titel</label>
          <input type="text" name="form_title" value="<?php echo esc_attr( $fc['form_title'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Formular undertekst</label>
          <input type="text" name="form_subtitle" value="<?php echo esc_attr( $fc['form_subtitle'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Sektion 1 titel ("Dine oplysninger")</label>
          <input type="text" name="section_referrer" value="<?php echo esc_attr( $fc['section_referrer'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Sektion 2 titel ("Din ven")</label>
          <input type="text" name="section_friend" value="<?php echo esc_attr( $fc['section_friend'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Manager dropdown label</label>
          <input type="text" name="manager_label" value="<?php echo esc_attr( $fc['manager_label'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Manager dropdown placeholder</label>
          <input type="text" name="manager_placeholder" value="<?php echo esc_attr( $fc['manager_placeholder'] ); ?>">
        </div>
        <div class="rzpz-field" style="grid-column:1/-1">
          <label>Samtykke-tekst (checkbox)</label>
          <input type="text" name="consent_text" value="<?php echo esc_attr( $fc['consent_text'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Send-knap tekst</label>
          <input type="text" name="submit_text" value="<?php echo esc_attr( $fc['submit_text'] ); ?>">
        </div>
      </div>
    </div>

    <!-- Success message -->
    <div class="rzpz-hs-card">
      <h2>✅ Succesbesked (vises efter indsendelse)</h2>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="rzpz-field">
          <label>Succesbesked titel</label>
          <input type="text" name="success_title" value="<?php echo esc_attr( $fc['success_title'] ); ?>">
        </div>
        <div class="rzpz-field">
          <label>Succesbesked tekst</label>
          <textarea name="success_message"><?php echo esc_textarea( $fc['success_message'] ); ?></textarea>
        </div>
      </div>
    </div>

    <!-- CAPTCHA & fields -->
    <div class="rzpz-hs-card">
      <h2>🛡️ CAPTCHA &amp; Felter</h2>

      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding:12px 16px;background:#111;border-radius:8px;border:1px solid #2a2a2a">
        <input type="checkbox" name="show_captcha" id="show_captcha" value="1" style="width:16px;height:16px;accent-color:#CCFF00" <?php checked( $fc['show_captcha'] ); ?>>
        <label for="show_captcha" style="font-size:13px;color:#e0e0e0;cursor:pointer">Vis menneskeverifikation (matematik-CAPTCHA) inden formularen vises</label>
      </div>

      <table class="rzpz-fc-table">
        <thead>
          <tr>
            <th>Felt</th>
            <th style="text-align:center;width:70px">Aktiv</th>
            <th style="text-align:center;width:80px">Påkrævet</th>
            <th>Label</th>
            <th>Placeholder</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ( $field_labels_static as $fk => $static_label ) :
          $f    = $fd[ $fk ] ?? [];
          $core = in_array( $fk, $core_fields, true );
        ?>
          <tr>
            <td>
              <?php echo esc_html( $static_label ); ?>
              <?php if ( $core ) : ?><span class="core-badge">påkrævet</span><?php endif; ?>
            </td>
            <td style="text-align:center">
              <?php if ( $core ) : ?>
                <input type="hidden" name="field_<?php echo $fk; ?>_enabled" value="1">
                <span style="color:#4ade80;font-size:16px">✓</span>
              <?php else : ?>
                <input type="checkbox" name="field_<?php echo $fk; ?>_enabled" value="1" style="accent-color:#CCFF00" <?php checked( $f['enabled'] ?? true ); ?>>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <?php if ( $core ) : ?>
                <input type="hidden" name="field_<?php echo $fk; ?>_required" value="1">
                <span style="color:#4ade80;font-size:16px">✓</span>
              <?php else : ?>
                <input type="checkbox" name="field_<?php echo $fk; ?>_required" value="1" style="accent-color:#CCFF00" <?php checked( $f['required'] ?? false ); ?>>
              <?php endif; ?>
            </td>
            <td><input type="text" name="field_<?php echo $fk; ?>_label" value="<?php echo esc_attr( $f['label'] ?? $static_label ); ?>" placeholder="Label…"></td>
            <td><input type="text" name="field_<?php echo $fk; ?>_placeholder" value="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" placeholder="Placeholder…"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:12px;align-items:center">
      <button type="submit" class="rzpz-btn-primary">💾 Gem formular-indstillinger</button>
      <a href="<?php echo esc_url( $base_url . '&tab=form' ); ?>" class="rzpz-btn-ghost">Annuller</a>
    </div>
  </form>

  <?php
  // ── TAB: QR ───────────────────────────────────────────────────────────────
  elseif ( $tab === 'qr' ) :
  ?>

  <div class="rzpz-hs-card">
    <h2>📱 QR Kode Generator</h2>
    <p>Generer en QR-kode til den side hvor henvis-formularen er placeret. Medarbejdere kan scanne koden og gå direkte til siden.</p>

    <div class="rzpz-field-row" style="align-items:flex-end;margin-bottom:16px">
      <div class="rzpz-field" style="flex:3">
        <label>URL til formularsiden</label>
        <select id="rzpz-qr-page" style="background:#111;border:1px solid #333;color:#e0e0e0;padding:8px 12px;border-radius:6px;font-size:13px;width:100%">
          <option value="">– Vælg side –</option>
          <?php foreach ( $pages as $page ) : ?>
            <option value="<?php echo esc_attr( get_permalink( $page ) ); ?>"><?php echo esc_html( $page->post_title ); ?> — <?php echo esc_html( get_permalink( $page ) ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rzpz-field" style="flex:3">
        <label>Eller indtast URL manuelt</label>
        <input type="url" id="rzpz-qr-url" placeholder="https://dit-site.dk/henvis-din-ven/" value="">
      </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
      <div class="rzpz-field" style="min-width:120px">
        <label>Størrelse</label>
        <select id="rzpz-qr-size" style="background:#111;border:1px solid #333;color:#e0e0e0;padding:8px 12px;border-radius:6px;font-size:13px">
          <option value="200">200×200 px (lille)</option>
          <option value="300" selected>300×300 px (standard)</option>
          <option value="500">500×500 px (stor)</option>
          <option value="800">800×800 px (print)</option>
        </select>
      </div>
      <div class="rzpz-field" style="min-width:140px">
        <label>Baggrund</label>
        <select id="rzpz-qr-bg" style="background:#111;border:1px solid #333;color:#e0e0e0;padding:8px 12px;border-radius:6px;font-size:13px">
          <option value="ffffff">Hvid</option>
          <option value="0d0d0d">Sort (mørk)</option>
          <option value="f0f0f0">Lysegrå</option>
        </select>
      </div>
      <div class="rzpz-field" style="min-width:140px">
        <label>QR farve</label>
        <select id="rzpz-qr-color" style="background:#111;border:1px solid #333;color:#e0e0e0;padding:8px 12px;border-radius:6px;font-size:13px">
          <option value="000000">Sort</option>
          <option value="CCFF00">Neon grøn (Rezponz)</option>
          <option value="1a1a1a">Mørk grå</option>
        </select>
      </div>
      <div style="align-self:flex-end">
        <button onclick="rzpzGenerateQR()" class="rzpz-btn-primary">⚡ Generer QR Kode</button>
      </div>
    </div>

    <div id="rzpz-qr-result">
      <img id="rzpz-qr-img" src="" alt="QR Kode">
      <div style="margin-top:14px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a id="rzpz-qr-download" href="#" download="qr-henvis-din-ven.png" class="rzpz-btn-primary" style="text-decoration:none">⬇ Download PNG</a>
        <button onclick="rzpzCopyQRUrl()" class="rzpz-btn-ghost">📋 Kopiér URL</button>
        <button onclick="rzpzPrintQR()" class="rzpz-btn-ghost">🖨 Print QR Kode</button>
      </div>
      <div id="rzpz-qr-url-display" style="margin-top:12px;font-size:12px;color:#555;text-align:center"></div>
    </div>
  </div>

  <!-- Tips -->
  <div class="rzpz-hs-card" style="border-color:#CCFF0030">
    <h2>💡 Tips til brug af QR-koden</h2>
    <ul style="color:#888;font-size:13px;line-height:2;padding-left:20px;margin:0">
      <li>Print QR-koden og hæng den op på kontoret</li>
      <li>Indsæt den i onboarding-materialer til nye medarbejdere</li>
      <li>Del den på interne Slack/Teams-kanaler</li>
      <li>Brug stor størrelse (500px+) til print for bedst kvalitet</li>
      <li>Hvid baggrund anbefales til print — sort baggrund til digitale skærme</li>
    </ul>
  </div>

  <script>
  function rzpzGenerateQR() {
    const page = document.getElementById('rzpz-qr-page').value;
    const manual = document.getElementById('rzpz-qr-url').value.trim();
    const url = manual || page;
    if (!url) { alert('Vælg en side eller indtast en URL.'); return; }

    const size  = document.getElementById('rzpz-qr-size').value;
    const bg    = document.getElementById('rzpz-qr-bg').value;
    const color = document.getElementById('rzpz-qr-color').value;

    const apiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(url)}&bgcolor=${bg}&color=${color}&margin=12&format=png`;

    const img  = document.getElementById('rzpz-qr-img');
    const link = document.getElementById('rzpz-qr-download');
    const result = document.getElementById('rzpz-qr-result');
    const urlDisplay = document.getElementById('rzpz-qr-url-display');

    img.src = apiUrl;
    img.style.width  = Math.min(parseInt(size), 300) + 'px';
    img.style.height = Math.min(parseInt(size), 300) + 'px';
    link.href = apiUrl;
    link.download = 'qr-henvis-' + size + 'px.png';
    urlDisplay.textContent = '🔗 ' + url;
    result.style.display = 'block';
    result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function rzpzCopyQRUrl() {
    const page = document.getElementById('rzpz-qr-page').value;
    const manual = document.getElementById('rzpz-qr-url').value.trim();
    const url = manual || page;
    navigator.clipboard.writeText(url).then(() => alert('URL kopieret!'));
  }
  function rzpzPrintQR() {
    const img = document.getElementById('rzpz-qr-img');
    if (!img.src) { alert('Generer QR-kode først.'); return; }
    const url = document.getElementById('rzpz-qr-url-display').textContent.replace('🔗 ','');
    const w = window.open('','_blank','width=500,height=600');
    w.document.write(`<html><body style="text-align:center;font-family:sans-serif;padding:40px">
      <h2 style="font-size:18px">Henvis Din Ven – Rezponz</h2>
      <img src="${img.src}" style="width:300px;height:300px;margin:20px 0;border:2px solid #eee"><br>
      <p style="font-size:12px;color:#666;word-break:break-all">${url}</p>
      <script>window.print();window.close()<\/script></body></html>`);
  }
  // Auto-populate URL field when page is selected
  document.getElementById('rzpz-qr-page').addEventListener('change', function() {
    if (this.value) document.getElementById('rzpz-qr-url').value = '';
  });
  </script>

  <?php endif; ?>

</div>
