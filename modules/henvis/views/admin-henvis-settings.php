<?php if ( ! defined( 'ABSPATH' ) ) exit;

$managers = RZPZ_Henvis::get_managers();
$test_sent = ! empty( $_GET['test_sent'] );
$saved     = ! empty( $_GET['saved'] );
$deleted   = ! empty( $_GET['deleted'] );
?>
<style>
#wpbody-content { background:#0d0d0d !important; }
#wpcontent { background:#0d0d0d !important; }
.rzpz-hs-page { padding:20px; background:#0d0d0d; min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; color:#e0e0e0; }
.rzpz-hs-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
.rzpz-hs-title { font-size:22px; font-weight:700; margin:0; color:#fff; }
.rzpz-hs-card { background:#1a1a1a; border:1px solid #2a2a2a; border-radius:12px; padding:24px; margin-bottom:20px; }
.rzpz-hs-card h2 { font-size:15px; font-weight:700; color:#fff; margin:0 0 16px 0; }
.rzpz-hs-notice { padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }
.rzpz-hs-notice.success { background:#0a2e0a; color:#4ade80; border:1px solid #4ade8040; }
.rzpz-hs-notice.info    { background:#0a1a2e; color:#60a5fa; border:1px solid #60a5fa40; }
.rzpz-field-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.rzpz-field-row input, .rzpz-field-row select {
    background:#111; border:1px solid #333; color:#e0e0e0; padding:8px 12px; border-radius:6px; font-size:13px; flex:1; min-width:140px;
}
.rzpz-field-row input:focus { outline:none; border-color:#CCFF00; }
.rzpz-btn-primary { background:#CCFF00; color:#0d0d0d; border:none; border-radius:6px; padding:8px 18px; font-weight:700; cursor:pointer; font-size:13px; }
.rzpz-btn-danger  { background:#ef444420; color:#ef4444; border:1px solid #ef444440; border-radius:6px; padding:4px 12px; font-size:12px; cursor:pointer; font-weight:600; }
.rzpz-btn-ghost   { background:transparent; color:#888; border:1px solid #333; border-radius:6px; padding:8px 18px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-block; }
table.rzpz-mgr-table { width:100%; border-collapse:collapse; }
.rzpz-mgr-table th { background:#111; color:#aaa; font-size:11px; text-transform:uppercase; padding:8px 12px; text-align:left; border-bottom:1px solid #2a2a2a; }
.rzpz-mgr-table td { padding:10px 12px; border-bottom:1px solid #1f1f1f; font-size:13px; color:#e0e0e0; vertical-align:middle; }
.rzpz-mgr-table tr:last-child td { border-bottom:none; }
.rzpz-mgr-table tr:hover td { background:#222; }
.rzpz-shortcode-box { background:#111; border:2px dashed #CCFF0060; border-radius:10px; padding:20px; text-align:center; margin-bottom:0; }
.rzpz-shortcode-box code { background:#1e1e1e; color:#CCFF00; padding:10px 24px; border-radius:8px; font-size:20px; font-weight:700; border:1px solid #333; display:inline-block; margin:10px 0; cursor:pointer; }
.rzpz-shortcode-box .hint { color:#666; font-size:12px; margin-top:8px; }
.rzpz-test-email-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.rzpz-test-email-row input { background:#111; border:1px solid #333; color:#e0e0e0; padding:8px 12px; border-radius:6px; font-size:13px; width:280px; }
</style>

<div class="rzpz-hs-page">

  <div class="rzpz-hs-header">
    <a href="<?php echo esc_url(admin_url('admin.php?page=rzpz-henvis')); ?>" style="color:#888;text-decoration:none;font-size:13px;">← Tilbage til Henvisninger</a>
    <h1 class="rzpz-hs-title" style="margin-left:8px;">⚙️ Henvis Din Ven – Indstillinger</h1>
  </div>

  <?php if ( $saved ) : ?>
    <div class="rzpz-hs-notice success">✅ Manager gemt.</div>
  <?php endif; ?>
  <?php if ( $deleted ) : ?>
    <div class="rzpz-hs-notice success">🗑 Manager slettet.</div>
  <?php endif; ?>
  <?php if ( $test_sent ) : ?>
    <div class="rzpz-hs-notice info">📧 Test-email afsendt. Tjek din indbakke.</div>
  <?php endif; ?>

  <!-- Shortcode -->
  <div class="rzpz-hs-card">
    <h2>📋 Shortcode til formularen</h2>
    <div class="rzpz-shortcode-box">
      <div style="color:#aaa;font-size:13px;margin-bottom:6px;">Indsæt denne shortcode på den WordPress-side hvor formularen skal vises:</div>
      <code id="rzpz-sc-copy" onclick="navigator.clipboard.writeText('[rezponz_henvis_ven]');this.textContent='✅ Kopieret!';setTimeout(()=>this.textContent='[rezponz_henvis_ven]',2000)">[rezponz_henvis_ven]</code>
      <div class="hint">Klik på koden for at kopiere den</div>
    </div>
  </div>

  <!-- Test email -->
  <div class="rzpz-hs-card">
    <h2>📧 Test email-udsendelse</h2>
    <p style="color:#888;font-size:13px;margin-bottom:14px;">Send en test-email for at bekræfte at dit WordPress-site kan sende mails korrekt.</p>
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <input type="hidden" name="action" value="rzpz_henvis_test_email">
      <?php wp_nonce_field( 'rzpz_henvis_test_email' ); ?>
      <div class="rzpz-test-email-row">
        <input type="email" name="test_email" placeholder="din@email.dk" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
        <button type="submit" class="rzpz-btn-primary">📤 Send test-email</button>
      </div>
    </form>
  </div>

  <!-- Add manager -->
  <div class="rzpz-hs-card">
    <h2>➕ Tilføj Senior Manager</h2>
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <input type="hidden" name="action" value="rzpz_henvis_save_manager">
      <?php wp_nonce_field( 'rzpz_henvis_save_manager' ); ?>
      <div class="rzpz-field-row">
        <input type="text"  name="mgr_name"  placeholder="Fornavn (fx Kasper)"        required>
        <input type="text"  name="mgr_label" placeholder="Label i dropdown (fx Kasper – Telenor)" required>
        <input type="email" name="mgr_email" placeholder="Email (fx kapj@rezponz.dk)" required>
        <button type="submit" class="rzpz-btn-primary">Tilføj</button>
      </div>
    </form>
  </div>

  <!-- Manager list -->
  <div class="rzpz-hs-card">
    <h2>👥 Nuværende Senior Managers (<?php echo count($managers); ?>)</h2>
    <?php if ( empty($managers) ) : ?>
      <p style="color:#666;font-size:13px;">Ingen managers endnu. Tilføj en ovenfor.</p>
    <?php else : ?>
    <table class="rzpz-mgr-table">
      <thead>
        <tr><th>Navn</th><th>Label i dropdown</th><th>Email</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ( $managers as $key => $m ) : ?>
        <tr>
          <td><?php echo esc_html( $m['name'] ); ?></td>
          <td><?php echo esc_html( $m['label'] ); ?></td>
          <td><a href="mailto:<?php echo esc_attr($m['email']); ?>" style="color:#60a5fa;"><?php echo esc_html( $m['email'] ); ?></a></td>
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

</div>
