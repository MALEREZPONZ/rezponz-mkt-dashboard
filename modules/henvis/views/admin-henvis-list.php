<?php if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table = $wpdb->prefix . 'rzpz_referrals';

// Status update
if ( isset( $_POST['rzpz_henvis_status_nonce'], $_POST['referral_id'], $_POST['new_status'] )
    && wp_verify_nonce( $_POST['rzpz_henvis_status_nonce'], 'rzpz_henvis_status' ) ) {
    $wpdb->update(
        $table,
        [ 'status' => sanitize_key( $_POST['new_status'] ) ],
        [ 'id'     => intval( $_POST['referral_id'] ) ],
        [ '%s' ],
        [ '%d' ]
    );
}

// Filters
$filter_mgr    = sanitize_key( $_GET['mgr'] ?? '' );
$filter_status = sanitize_key( $_GET['status'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );

$where  = '1=1';
$params = [];
if ( $filter_mgr ) {
    $where   .= ' AND manager_key = %s';
    $params[] = $filter_mgr;
}
if ( $filter_status ) {
    $where   .= ' AND status = %s';
    $params[] = $filter_status;
}
if ( $search ) {
    $where   .= ' AND (referrer_name LIKE %s OR friend_name LIKE %s OR referrer_email LIKE %s)';
    $params[] = '%' . $wpdb->esc_like( $search ) . '%';
    $params[] = '%' . $wpdb->esc_like( $search ) . '%';
    $params[] = '%' . $wpdb->esc_like( $search ) . '%';
}

$query     = "SELECT * FROM {$table} WHERE {$where} ORDER BY CASE WHEN status='pending' THEN 0 ELSE 1 END ASC, submitted_at DESC";
$referrals = $params
    ? $wpdb->get_results( $wpdb->prepare( $query, ...$params ) )
    : $wpdb->get_results( $query );

$total    = count( $referrals );
$managers = RZPZ_Henvis::get_managers();

$status_labels = [
    'pending'   => '⏳ Afventer',
    'hired'     => '✅ Ansat',
    'rejected'  => '❌ Afvist',
    'contacted' => '📞 Kontaktet',
];

$total_all  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
$hired      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='hired'" );
$pending    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='pending'" );
$this_month = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE submitted_at >= DATE_FORMAT(NOW(),'%Y-%m-01')" );
?>
<style>
#wpbody-content { background:#08080b !important; }
#wpcontent { background:#08080b !important; }
.rzpz-henvis-page { padding:20px; background:#08080b; min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; color:#f0f0f2; }
.rzpz-ha-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
.rzpz-ha-title  { font-size:22px; font-weight:700; margin:0; color:#f0f0f2; }
.rzpz-ha-badge  { background:#CCFF00; color:#0d0d0d; border-radius:20px; padding:2px 12px; font-size:13px; font-weight:700; }
.rzpz-ha-kpi-row { display:flex; gap:14px; margin-bottom:24px; flex-wrap:wrap; }
.rzpz-ha-kpi { background:rgba(255,255,255,.03); backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.07); border-radius:18px; padding:16px 22px; flex:1; min-width:140px; }
.rzpz-ha-kpi .val { font-size:28px; font-weight:700; color:#CCFF00; }
.rzpz-ha-kpi .lbl { font-size:12px; color:#8888a0; margin-top:2px; }
.rzpz-ha-shortcode-box { background:rgba(255,255,255,.03); backdrop-filter:blur(24px); border:1px solid rgba(204,255,0,.25); border-radius:18px; padding:14px 20px; margin-bottom:24px; display:flex; align-items:center; gap:14px; }
.rzpz-ha-shortcode-box code { background:rgba(255,255,255,.05); color:#CCFF00; padding:6px 14px; border-radius:6px; font-size:15px; font-weight:700; letter-spacing:.5px; border:1px solid rgba(255,255,255,.07); }
.rzpz-ha-shortcode-box .lbl { color:#8888a0; font-size:13px; }
.rzpz-ha-filters { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
.rzpz-ha-filters select, .rzpz-ha-filters input[type=search] {
    background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); color:#f0f0f2; padding:6px 10px; border-radius:6px; font-size:13px;
}
.rzpz-ha-filters .rzpz-btn { background:rgba(255,255,255,.06); color:#CCFF00; border:1px solid rgba(255,255,255,.07); border-radius:999px; padding:6px 18px; font-weight:700; cursor:pointer; font-size:13px; text-decoration:none; display:inline-block; transition:background .15s,border-color .15s; }
.rzpz-ha-filters .rzpz-btn:hover, .rzpz-ha-filters .rzpz-btn:active { background:#CCFF00; color:#0d0d0d; border-color:#CCFF00; }
.rzpz-ha-filters .rzpz-btn-ghost { background:rgba(255,255,255,.03); color:#8888a0; border:1px solid rgba(255,255,255,.07); border-radius:999px; padding:6px 18px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-block; transition:color .15s,border-color .15s,background .15s; }
.rzpz-ha-filters .rzpz-btn-ghost:hover { color:#f0f0f2; border-color:rgba(255,255,255,.2); background:rgba(255,255,255,.06); }
table.rzpz-ha-table { width:100%; border-collapse:collapse; background:rgba(255,255,255,.03); backdrop-filter:blur(24px); border-radius:18px; overflow:hidden; border:1px solid rgba(255,255,255,.07); }
.rzpz-ha-table th { background:rgba(255,255,255,.02); color:#8888a0; font-size:12px; text-transform:uppercase; padding:10px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,.07); }
.rzpz-ha-table td { padding:10px 14px; border-bottom:1px solid rgba(255,255,255,.05); color:#f0f0f2; font-size:13px; vertical-align:middle; }
.rzpz-ha-table tr:last-child td { border-bottom:none; }
.rzpz-ha-table tr.rzpz-data-row:hover td { background:rgba(255,255,255,.04); }
.rzpz-status { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
.rzpz-status.pending   { background:#2d2d00; color:#ffaa33; }
.rzpz-status.hired     { background:#0a2e0a; color:#4ade80; }
.rzpz-status.rejected  { background:#2d0a0a; color:#ff5555; }
.rzpz-status.contacted { background:#0a1a2e; color:#60a5fa; }
.rzpz-ha-form-inline select { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); color:#f0f0f2; border-radius:4px; padding:3px 6px; font-size:12px; }
.rzpz-ha-form-inline button { background:rgba(255,255,255,.06); color:#CCFF00; border:1px solid rgba(255,255,255,.07); border-radius:999px; padding:3px 10px; font-size:12px; cursor:pointer; font-weight:600; transition:background .15s; }
.rzpz-ha-form-inline button:hover { background:#CCFF00; color:#0d0d0d; }
.rzpz-ha-empty { text-align:center; padding:40px; color:#8888a0; }
/* Action buttons per row */
.rzpz-row-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.rzpz-btn-xs { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); color:#8888a0; border-radius:999px; padding:3px 10px; font-size:11px; cursor:pointer; white-space:nowrap; transition:border-color .15s,color .15s,background .15s; }
.rzpz-btn-xs:hover { border-color:rgba(255,255,255,.2); color:#f0f0f2; background:rgba(255,255,255,.06); }
.rzpz-btn-xs.active { border-color:#CCFF00; color:#CCFF00; background:rgba(204,255,0,.07); }
/* Email log accordion */
.rzpz-email-log-row { display:none; background:#08080b; }
.rzpz-email-log-row.open { display:table-row; }
.rzpz-email-log-row td { padding:0 !important; border-bottom:2px solid #CCFF0030 !important; }
.rzpz-email-log-inner { padding:16px 20px; display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
.rzpz-email-entry { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); border-radius:8px; padding:12px 14px; font-size:12px; }
.rzpz-email-entry .ee-label { font-size:10px; text-transform:uppercase; color:#8888a0; letter-spacing:.5px; margin-bottom:4px; }
.rzpz-email-entry .ee-to { color:#f0f0f2; font-weight:600; margin-bottom:3px; word-break:break-all; }
.rzpz-email-entry .ee-time { color:#8888a0; font-size:11px; }
.rzpz-email-entry .ee-status { display:inline-block; margin-top:4px; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
.rzpz-email-entry .ee-status.ok  { background:#0a2e0a; color:#4ade80; }
.rzpz-email-entry .ee-status.err { background:#2d0a0a; color:#ff5555; }
.rzpz-email-entry .ee-status.cc  { background:#0a1a2e; color:#60a5fa; }
/* Approve / Reject buttons */
.rzpz-btn-approve { background:#0a2e0a; color:#4ade80; border:1px solid #4ade8040; border-radius:999px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .15s,border-color .15s; }
.rzpz-btn-approve:hover { background:#0f3d0f; border-color:#4ade8080; }
.rzpz-btn-reject  { background:#2d0a0a; color:#ff5555; border:1px solid #ff555540; border-radius:999px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .15s,border-color .15s; }
.rzpz-btn-reject:hover  { background:#3d0f0f; border-color:#ff555580; }
.rzpz-btn-bonus   { background:#2a1f00; color:#ffaa33; border:1px solid #ffaa3350; border-radius:999px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .15s,border-color .15s; }
.rzpz-btn-bonus:hover   { background:#3a2a00; border-color:#ffaa3390; }
.rzpz-bonus-sent  { display:inline-block; background:#0a1a2e; color:#60a5fa; border:1px solid #60a5fa30; border-radius:999px; padding:4px 12px; font-size:11px; white-space:nowrap; }
.rzpz-status.hired-bonus { background:#0a1a2e; color:#60a5fa; }
.rzpz-section-sep td { background:rgba(255,255,255,.02) !important; padding:6px 14px !important; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#8888a0; border-bottom:2px solid rgba(255,255,255,.07) !important; }
/* Note row */
.rzpz-note-row { display:none; background:#08080b; }
.rzpz-note-row.open { display:table-row; }
.rzpz-note-row td { padding:0 !important; border-bottom:2px solid #60a5fa30 !important; }
.rzpz-note-inner { padding:14px 20px; }
.rzpz-note-inner textarea { width:100%; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); color:#f0f0f2; border-radius:6px; padding:8px 12px; font-size:13px; resize:vertical; min-height:60px; box-sizing:border-box; font-family:inherit; }
.rzpz-note-inner textarea:focus { outline:none; border-color:#60a5fa; }
.rzpz-note-inner .note-actions { display:flex; gap:8px; margin-top:8px; align-items:center; }
.rzpz-note-inner .note-save { background:rgba(255,255,255,.06); color:#CCFF00; border:1px solid rgba(255,255,255,.07); border-radius:999px; padding:5px 16px; font-size:12px; cursor:pointer; font-weight:700; transition:background .15s,border-color .15s; }
.rzpz-note-inner .note-save:hover { background:#CCFF00; color:#0d0d0d; border-color:#CCFF00; }
.rzpz-note-inner .note-cancel { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); color:#8888a0; border-radius:999px; padding:5px 14px; font-size:12px; cursor:pointer; transition:color .15s,border-color .15s; }
.rzpz-note-inner .note-cancel:hover { color:#f0f0f2; border-color:rgba(255,255,255,.2); }
.rzpz-note-preview { font-size:11px; color:#60a5fa; max-width:160px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
</style>

<div class="rzpz-henvis-page">

  <?php if ( ! empty( $_GET['approved'] ) ) : ?>
    <div style="background:#0a2e0a;color:#4ade80;border:1px solid #4ade8040;border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">✅ Henvisning godkendt og markeret som ansat. <strong>Husk:</strong> Klik "💰 Kandidat startet" den dag kandidaten møder ind — så udløses bonusmailen til medarbejder og løn.</div>
  <?php elseif ( ! empty( $_GET['rejected'] ) ) : ?>
    <div style="background:#2d0a0a;color:#ff5555;border:1px solid #ff555540;border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">❌ Henvisning afvist – afvisningsmail sendt til medarbejder.</div>
  <?php elseif ( ! empty( $_GET['bonus_sent'] ) ) : ?>
    <div style="background:#0a1a2e;color:#60a5fa;border:1px solid #60a5fa40;border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">💰 Bonus udløst – email sendt til medarbejder og loen@rezponz.dk.</div>
  <?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
    <div style="background:rgba(255,255,255,.03);color:#8888a0;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">🗑️ Henvisning slettet.</div>
  <?php elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'bonus_already_sent' ) : ?>
    <div style="background:#2a1f00;color:#ffaa33;border:1px solid #ffaa3340;border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">⚠️ Bonusmail er allerede sendt for denne henvisning.</div>
  <?php elseif ( ! empty( $_GET['batch_sent'] ) ) : ?>
    <div style="background:#052e16;color:#4ade80;border:1px solid #16653440;border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">💰 Batch bonus sendt: <strong><?php echo (int) $_GET['batch_sent']; ?> medarbejder-email(s)</strong> + 1 samlet løn-mail til loen@rezponz.dk.</div>
  <?php elseif ( isset( $_GET['batch_error'] ) && $_GET['batch_error'] === 'empty' ) : ?>
    <div style="background:#2a1f00;color:#ffaa33;border:1px solid #ffaa3340;border-radius:8px;padding:10px 18px;margin-bottom:16px;font-size:13px">⚠️ Ingen henvisninger valgt — sæt mindst ét kryds.</div>
  <?php endif; ?>

  <div class="rzpz-ha-header">
    <h1 class="rzpz-ha-title">🤝 Henvisninger</h1>
    <span class="rzpz-ha-badge"><?php echo $total_all; ?> total</span>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=rzpz_henvis_export_csv&_wpnonce=' . wp_create_nonce('rzpz_henvis_export_csv') . ( $filter_mgr ? '&mgr=' . urlencode($filter_mgr) : '' ) . ( $filter_status ? '&status=' . urlencode($filter_status) : '' ) . ( $search ? '&s=' . urlencode($search) : '' ) ) ); ?>"
         style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);color:#8888a0;padding:6px 16px;border-radius:999px;font-size:12px;text-decoration:none;">⬇ Eksportér CSV</a>
      <a href="<?php echo esc_url( admin_url('admin.php?page=rzpz-henvis-settings') ); ?>"
         style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);color:#8888a0;padding:6px 16px;border-radius:999px;font-size:12px;text-decoration:none;">⚙️ Indstillinger</a>
    </div>
  </div>

  <!-- Shortcode info -->
  <div class="rzpz-ha-shortcode-box">
    <div>
      <div class="lbl" style="margin-bottom:6px;">📋 Indsæt formularen på en side med denne shortcode:</div>
      <code onclick="navigator.clipboard.writeText('[rezponz_henvis_ven]');this.textContent='✅ Kopieret!';setTimeout(()=>this.textContent='[rezponz_henvis_ven]',2000)" style="cursor:pointer">[rezponz_henvis_ven]</code>
    </div>
    <div style="margin-left:auto;font-size:12px;color:#8888a0;text-align:right;line-height:1.8">
      <a href="<?php echo esc_url( admin_url('admin.php?page=rzpz-henvis-settings&tab=form') ); ?>" style="color:#CCFF00;text-decoration:none;display:block">📝 Tilpas formular-felter</a>
      <a href="<?php echo esc_url( admin_url('admin.php?page=rzpz-henvis-settings&tab=emails') ); ?>" style="color:#CCFF00;text-decoration:none;display:block">📧 Email-modtagere</a>
      <a href="<?php echo esc_url( admin_url('admin.php?page=rzpz-henvis-settings&tab=qr') ); ?>" style="color:#CCFF00;text-decoration:none;display:block">📱 QR Kode</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="rzpz-ha-kpi-row">
    <div class="rzpz-ha-kpi"><div class="val"><?php echo $total_all; ?></div><div class="lbl">Samlede henvisninger</div></div>
    <div class="rzpz-ha-kpi"><div class="val"><?php echo $this_month; ?></div><div class="lbl">Denne måned</div></div>
    <div class="rzpz-ha-kpi"><div class="val"><?php echo $hired; ?></div><div class="lbl">Ansatte</div></div>
    <div class="rzpz-ha-kpi"><div class="val"><?php echo $pending; ?></div><div class="lbl">Afventer</div></div>
  </div>

  <!-- Filters -->
  <form method="get" class="rzpz-ha-filters">
    <input type="hidden" name="page" value="rzpz-henvis">
    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Søg navn eller email…">
    <select name="mgr">
      <option value="">Alle managers</option>
      <?php foreach ( $managers as $k => $m ) : ?>
        <option value="<?php echo esc_attr($k); ?>" <?php selected($filter_mgr,$k); ?>><?php echo esc_html($m['label']); ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Alle statusser</option>
      <?php foreach ( $status_labels as $k => $l ) : ?>
        <option value="<?php echo esc_attr($k); ?>" <?php selected($filter_status,$k); ?>><?php echo esc_html($l); ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="rzpz-btn">Filtrer</button>
    <?php if ( $filter_mgr || $filter_status || $search ) : ?>
      <a href="?page=rzpz-henvis" class="rzpz-btn-ghost">Nulstil</a>
    <?php endif; ?>
  </form>

  <!-- Batch bonus-form (separat fra tabellen for at undgå nested forms) -->
  <form id="rzpz-batch-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="rzpz_henvis_batch_bonus">
    <?php wp_nonce_field( 'rzpz_henvis_batch_bonus' ); ?>
    <input type="hidden" id="rzpz-batch-ids" name="referral_ids" value="">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
      <button type="button" id="rzpz-batch-btn" disabled onclick="rzpzSubmitBatch()"
              style="background:rgba(255,170,51,.15);color:#ffaa33;border:1px solid #ffaa3350;border-radius:999px;padding:9px 22px;font-size:13px;font-weight:700;cursor:pointer;opacity:.35;transition:opacity .15s,background .15s">
        💰 Send batch bonus-mail til løn (<span id="rzpz-batch-count">0</span> valgte)
      </button>
      <span style="font-size:12px;color:#8888a0">Markér "Ansat – bonus afventer"-rækker og send individuelle medarbejder-mails + én samlet løn-mail</span>
    </div>
  </form>

  <!-- Table -->
  <table class="rzpz-ha-table">
    <thead>
      <tr>
        <th style="width:36px;text-align:center">
          <input type="checkbox" id="rzpz-check-all" title="Vælg alle" style="cursor:pointer;width:15px;height:15px;accent-color:#f59e0b">
        </th>
        <th>#</th>
        <th>Dato</th>
        <th>Fra (medarbejder)</th>
        <th>Til (ven)</th>
        <th>Manager</th>
        <th>Status</th>
        <th>Handlinger</th>
      </tr>
    </thead>
    <tbody>
    <?php if ( empty( $referrals ) ) : ?>
      <tr><td colspan="8" class="rzpz-ha-empty">Ingen henvisninger fundet.</td></tr>
    <?php else :
      $pending_count   = count( array_filter( $referrals, fn($x) => $x->status === 'pending' ) );
      $in_pending_sec  = false;
      $in_handled_sec  = false;
      foreach ( $referrals as $r ) :
      $mgr        = $managers[ $r->manager_key ] ?? null;
      $label      = $status_labels[ $r->status ] ?? $r->status;
      $cls        = esc_attr( $r->status );
      $elog       = json_decode( $r->emails_log ?: '{}', true );
      $has_log    = ! empty( $elog['sent_at'] ) || ! empty( $elog['manager'] );
      $has_note   = ! empty( $r->notes );
      $row_id     = (int) $r->id;

      // Section separators
      if ( $r->status === 'pending' && ! $in_pending_sec ) {
          $in_pending_sec = true;
          echo '<tr class="rzpz-section-sep"><td colspan="8">⏳ Afventer behandling (' . $pending_count . ')</td></tr>';
      } elseif ( $r->status !== 'pending' && ! $in_handled_sec ) {
          $in_handled_sec = true;
          echo '<tr class="rzpz-section-sep"><td colspan="8">📋 Behandlede</td></tr>';
      }
    ?>
      <!-- Data row -->
      <tr class="rzpz-data-row">
        <td style="text-align:center">
          <?php if ( $r->status === 'hired' && ! $bonus_sent ) : ?>
            <input type="checkbox" class="rzpz-batch-cb" value="<?php echo $row_id; ?>"
                   style="cursor:pointer;width:15px;height:15px;accent-color:#f59e0b">
          <?php endif; ?>
        </td>
        <td><?php echo $row_id; ?></td>
        <td style="white-space:nowrap"><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $r->submitted_at ) ) ); ?></td>
        <td>
          <strong><?php echo esc_html( $r->referrer_name ); ?></strong><br>
          <small style="color:#8888a0"><?php echo esc_html( $r->referrer_email ); ?></small>
          <?php if ( $r->referrer_phone ) : ?><br><small style="color:#8888a0"><?php echo esc_html( $r->referrer_phone ); ?></small><?php endif; ?>
        </td>
        <td>
          <strong><?php echo esc_html( $r->friend_name ); ?></strong>
          <?php if ( $r->friend_email ) : ?><br><small style="color:#8888a0"><?php echo esc_html( $r->friend_email ); ?></small><?php endif; ?>
          <?php if ( $r->friend_phone ) : ?><br><small style="color:#8888a0"><?php echo esc_html( $r->friend_phone ); ?></small><?php endif; ?>
        </td>
        <td><?php echo $mgr ? esc_html( $mgr['label'] ) : esc_html( $r->manager_key ); ?></td>
        <td>
          <?php
          $bonus_sent = ! empty( $elog['bonus_log']['sent_at'] );
          if ( $r->status === 'pending' ) : ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="rzpz_henvis_approve">
                <input type="hidden" name="referral_id" value="<?php echo $row_id; ?>">
                <?php wp_nonce_field( 'rzpz_henvis_approve' ); ?>
                <button type="submit" class="rzpz-btn-approve">✅ Godkend</button>
              </form>
              <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="rzpz_henvis_reject">
                <input type="hidden" name="referral_id" value="<?php echo $row_id; ?>">
                <?php wp_nonce_field( 'rzpz_henvis_reject' ); ?>
                <button type="submit" class="rzpz-btn-reject">❌ Afvis</button>
              </form>
            </div>
          <?php elseif ( $r->status === 'hired' ) : ?>
            <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-start">
              <span class="rzpz-status <?php echo $bonus_sent ? 'hired-bonus' : 'hired'; ?>">
                <?php echo $bonus_sent ? '💰 Bonus sendt' : '✅ Ansat'; ?>
              </span>
              <?php if ( ! $bonus_sent ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:0"
                      onsubmit="return confirm('Send bonusmail til medarbejder og løn nu?\n\nDette bekræfter at kandidaten er mødt på arbejde.')">
                  <input type="hidden" name="action" value="rzpz_henvis_bonus">
                  <input type="hidden" name="referral_id" value="<?php echo $row_id; ?>">
                  <?php wp_nonce_field( 'rzpz_henvis_bonus' ); ?>
                  <button type="submit" class="rzpz-btn-bonus">💰 Kandidat startet</button>
                </form>
              <?php else : ?>
                <span class="rzpz-bonus-sent">✓ <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $elog['bonus_log']['sent_at'] ) ) ); ?></span>
              <?php endif; ?>
            </div>
          <?php else : ?>
            <span class="rzpz-status <?php echo $cls; ?>"><?php echo esc_html( $label ); ?></span>
          <?php endif; ?>
        </td>
        <td>
          <div class="rzpz-row-actions">
            <?php if ( $has_log ) : ?>
              <button class="rzpz-btn-xs" onclick="rzpzToggleRow('email-<?php echo $row_id; ?>', this)">📧 Emails</button>
            <?php else : ?>
              <span style="font-size:11px;color:#8888a0">📧 —</span>
            <?php endif; ?>
            <button class="rzpz-btn-xs<?php echo $has_note ? ' active' : ''; ?>" onclick="rzpzToggleRow('note-<?php echo $row_id; ?>', this)">
              📝 <?php echo $has_note ? 'Note' : 'Tilføj note'; ?>
            </button>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;margin:0"
                  onsubmit="return confirm('Slet denne henvisning permanent?\n\nDette kan ikke fortrydes.')">
              <input type="hidden" name="action"      value="rzpz_henvis_delete">
              <input type="hidden" name="referral_id" value="<?php echo $row_id; ?>">
              <?php wp_nonce_field( 'rzpz_henvis_delete' ); ?>
              <button type="submit" class="rzpz-btn-xs" style="color:#f87171;border-color:#f8717140" title="Slet henvisning">🗑️</button>
            </form>
          </div>
          <?php if ( $has_note ) : ?>
            <div class="rzpz-note-preview" style="margin-top:4px" title="<?php echo esc_attr( $r->notes ); ?>"><?php echo esc_html( $r->notes ); ?></div>
          <?php endif; ?>
        </td>
      </tr>

      <!-- Email log accordion -->
      <?php if ( $has_log ) : ?>
      <tr class="rzpz-email-log-row" id="email-<?php echo $row_id; ?>">
        <td colspan="8">
          <div class="rzpz-email-log-inner">
            <?php
            // Manager email
            $em = $elog['manager'] ?? null;
            if ( $em ) :
            ?>
            <div class="rzpz-email-entry">
              <div class="ee-label">Manager</div>
              <div class="ee-to"><?php echo esc_html( $em['name'] ?? '' ); ?><br><?php echo esc_html( $em['to'] ?? '' ); ?></div>
              <?php if ( ! empty( $em['time'] ) ) : ?><div class="ee-time">⏰ <?php echo esc_html( $elog['sent_at'] ?? '' ); ?> – <?php echo esc_html( $em['time'] ); ?></div><?php endif; ?>
              <div class="ee-status <?php echo empty($em['sent']) ? 'err' : 'ok'; ?>">
                <?php echo empty($em['sent']) ? '❌ Ikke sendt' : '✅ Sendt'; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php
            // Referrer email
            $er = $elog['referrer'] ?? null;
            if ( $er ) :
            ?>
            <div class="rzpz-email-entry">
              <div class="ee-label">Medarbejder (afsender)</div>
              <div class="ee-to"><?php echo esc_html( $er['name'] ?? '' ); ?><br><?php echo esc_html( $er['to'] ?? '' ); ?></div>
              <?php if ( ! empty( $er['time'] ) ) : ?><div class="ee-time">⏰ <?php echo esc_html( $er['time'] ); ?></div><?php endif; ?>
              <div class="ee-status <?php echo empty($er['sent']) ? 'err' : 'ok'; ?>">
                <?php echo empty($er['sent']) ? '❌ Ikke sendt' : '✅ Sendt'; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php
            // Friend email
            $ef = $elog['friend'] ?? null;
            if ( $ef ) :
            ?>
            <div class="rzpz-email-entry">
              <div class="ee-label">Ven (modtager)</div>
              <div class="ee-to"><?php echo esc_html( $ef['name'] ?? '' ); ?><br><?php echo esc_html( $ef['to'] ?? '' ); ?></div>
              <?php if ( ! empty( $ef['time'] ) ) : ?><div class="ee-time">⏰ <?php echo esc_html( $ef['time'] ); ?></div><?php endif; ?>
              <div class="ee-status <?php echo empty($ef['sent']) ? 'err' : 'ok'; ?>">
                <?php echo empty($ef['sent']) ? '❌ Ikke sendt' : '✅ Sendt'; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php
            // CC recipients
            $cc_list = $elog['extra'] ?? [];
            if ( ! empty( $cc_list ) ) :
            ?>
            <div class="rzpz-email-entry">
              <div class="ee-label">CC Modtagere</div>
              <?php foreach ( $cc_list as $cc ) : ?>
                <div class="ee-to" style="margin-bottom:4px">
                  <?php echo esc_html( $cc['name'] ?? '' ); ?><br>
                  <span style="font-weight:400;color:#8888a0"><?php echo esc_html( $cc['email'] ?? '' ); ?></span>
                </div>
              <?php endforeach; ?>
              <div class="ee-status cc">CC – alle mails</div>
            </div>
            <?php endif; ?>

          </div>
        </td>
      </tr>
      <?php endif; ?>

      <!-- Note row -->
      <tr class="rzpz-note-row" id="note-<?php echo $row_id; ?>">
        <td colspan="8">
          <div class="rzpz-note-inner">
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
              <input type="hidden" name="action"      value="rzpz_henvis_save_note">
              <input type="hidden" name="referral_id" value="<?php echo $row_id; ?>">
              <?php wp_nonce_field( 'rzpz_henvis_save_note' ); ?>
              <textarea name="note" placeholder="Skriv en intern note om denne henvisning…"><?php echo esc_textarea( $r->notes ); ?></textarea>
              <div class="note-actions">
                <button type="submit" class="note-save">💾 Gem note</button>
                <button type="button" class="note-cancel" onclick="rzpzToggleRow('note-<?php echo $row_id; ?>', null, true)">Annuller</button>
              </div>
            </form>
          </div>
        </td>
      </tr>

    <?php endforeach; endif; ?>
    </tbody>
  </table>

</div>

<script>
function rzpzToggleRow(id, btn, forceClose) {
    const row = document.getElementById(id);
    if (!row) return;
    const isOpen = row.classList.contains('open');
    if (forceClose || isOpen) {
        row.classList.remove('open');
        if (btn) btn.classList.remove('active');
    } else {
        row.classList.add('open');
        if (btn) btn.classList.add('active');
    }
}

// ── Batch bonus checkboxe ──────────────────────────────────────────────────
(function () {
    var allCb    = document.querySelectorAll('.rzpz-batch-cb');
    var checkAll = document.getElementById('rzpz-check-all');
    var countEl  = document.getElementById('rzpz-batch-count');
    var btn      = document.getElementById('rzpz-batch-btn');

    function updateBtn() {
        var n = document.querySelectorAll('.rzpz-batch-cb:checked').length;
        countEl.textContent = n;
        btn.disabled        = n === 0;
        btn.style.opacity   = n > 0 ? '1' : '.35';
        btn.style.cursor    = n > 0 ? 'pointer' : 'not-allowed';
    }

    allCb.forEach(function (cb) { cb.addEventListener('change', updateBtn); });

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            allCb.forEach(function (cb) { cb.checked = checkAll.checked; });
            updateBtn();
        });
    }

    window.rzpzSubmitBatch = function () {
        var checked = Array.from(document.querySelectorAll('.rzpz-batch-cb:checked'));
        if (!checked.length) return;
        var ids = checked.map(function (cb) { return cb.value; }).join(',');
        if (!confirm('Send bonusmail til ' + checked.length + ' medarbejder(e) og én samlet løn-mail til loen@rezponz.dk?\n\nDette kan ikke fortrydes.')) return;
        document.getElementById('rzpz-batch-ids').value = ids;
        document.getElementById('rzpz-batch-form').submit();
    };
}());
</script>
