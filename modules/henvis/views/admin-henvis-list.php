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

$query    = "SELECT * FROM {$table} WHERE {$where} ORDER BY submitted_at DESC";
$referrals = $params
    ? $wpdb->get_results( $wpdb->prepare( $query, ...$params ) )
    : $wpdb->get_results( $query );

$total = count( $referrals );
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
#wpbody-content { background:#0d0d0d !important; }
#wpcontent { background:#0d0d0d !important; }
.rzpz-henvis-page { padding:20px; background:#0d0d0d; min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; color:#e0e0e0; }
.rzpz-ha-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
.rzpz-ha-title  { font-size:22px; font-weight:700; margin:0; color:#fff; }
.rzpz-ha-badge  { background:#CCFF00; color:#0d0d0d; border-radius:20px; padding:2px 12px; font-size:13px; font-weight:700; }
.rzpz-ha-kpi-row { display:flex; gap:14px; margin-bottom:24px; flex-wrap:wrap; }
.rzpz-ha-kpi { background:#1a1a1a; border:1px solid #2a2a2a; border-radius:10px; padding:16px 22px; flex:1; min-width:140px; }
.rzpz-ha-kpi .val { font-size:28px; font-weight:700; color:#CCFF00; }
.rzpz-ha-kpi .lbl { font-size:12px; color:#888; margin-top:2px; }
.rzpz-ha-shortcode-box { background:#111; border:1px solid #CCFF0040; border-radius:10px; padding:14px 20px; margin-bottom:24px; display:flex; align-items:center; gap:14px; }
.rzpz-ha-shortcode-box code { background:#1e1e1e; color:#CCFF00; padding:6px 14px; border-radius:6px; font-size:15px; font-weight:700; letter-spacing:.5px; border:1px solid #333; }
.rzpz-ha-shortcode-box .lbl { color:#888; font-size:13px; }
.rzpz-ha-filters { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
.rzpz-ha-filters select, .rzpz-ha-filters input[type=search] {
    background:#1e1e1e; border:1px solid #333; color:#e0e0e0; padding:6px 10px; border-radius:6px; font-size:13px;
}
.rzpz-ha-filters .rzpz-btn { background:#CCFF00; color:#0d0d0d; border:none; border-radius:6px; padding:6px 16px; font-weight:700; cursor:pointer; font-size:13px; text-decoration:none; display:inline-block; }
.rzpz-ha-filters .rzpz-btn-ghost { background:transparent; color:#888; border:1px solid #333; border-radius:6px; padding:6px 16px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-block; }
table.rzpz-ha-table { width:100%; border-collapse:collapse; background:#1a1a1a; border-radius:10px; overflow:hidden; }
.rzpz-ha-table th { background:#111; color:#aaa; font-size:12px; text-transform:uppercase; padding:10px 14px; text-align:left; border-bottom:1px solid #2a2a2a; }
.rzpz-ha-table td { padding:10px 14px; border-bottom:1px solid #1f1f1f; color:#e0e0e0; font-size:13px; vertical-align:middle; }
.rzpz-ha-table tr:last-child td { border-bottom:none; }
.rzpz-ha-table tr:hover td { background:#222; }
.rzpz-status { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
.rzpz-status.pending   { background:#2d2d00; color:#f59e0b; }
.rzpz-status.hired     { background:#0a2e0a; color:#4ade80; }
.rzpz-status.rejected  { background:#2d0a0a; color:#f87171; }
.rzpz-status.contacted { background:#0a1a2e; color:#60a5fa; }
.rzpz-ha-form-inline select { background:#1e1e1e; border:1px solid #333; color:#e0e0e0; border-radius:4px; padding:3px 6px; font-size:12px; }
.rzpz-ha-form-inline button { background:#CCFF00; color:#0d0d0d; border:none; border-radius:4px; padding:3px 8px; font-size:12px; cursor:pointer; font-weight:600; }
.rzpz-ha-empty { text-align:center; padding:40px; color:#666; }
.rzpz-tab-nav { display:flex; gap:4px; margin-bottom:24px; border-bottom:1px solid #2a2a2a; padding-bottom:0; }
.rzpz-tab-nav a { padding:8px 18px; font-size:13px; font-weight:600; color:#888; text-decoration:none; border-radius:6px 6px 0 0; }
.rzpz-tab-nav a.active { background:#1a1a1a; color:#CCFF00; border:1px solid #2a2a2a; border-bottom:1px solid #1a1a1a; }
.rzpz-tab-nav a:hover { color:#fff; }
</style>

<div class="rzpz-henvis-page">

  <div class="rzpz-ha-header">
    <h1 class="rzpz-ha-title">🤝 Henvisninger</h1>
    <span class="rzpz-ha-badge"><?php echo $total_all; ?> total</span>
    <a href="<?php echo esc_url( admin_url('admin.php?page=rzpz-henvis-settings') ); ?>" style="margin-left:auto;background:#1e1e1e;border:1px solid #333;color:#aaa;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;">⚙️ Indstillinger & Managers</a>
  </div>

  <!-- Shortcode info -->
  <div class="rzpz-ha-shortcode-box">
    <div>
      <div class="lbl" style="margin-bottom:6px;">📋 Indsæt formularen på en side med denne shortcode:</div>
      <code>[rezponz_henvis_ven]</code>
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

  <!-- Table -->
  <table class="rzpz-ha-table">
    <thead>
      <tr>
        <th>#</th><th>Dato</th><th>Fra (medarbejder)</th><th>Til (ven)</th><th>Manager</th><th>Status</th><th>Skift status</th>
      </tr>
    </thead>
    <tbody>
    <?php if ( empty( $referrals ) ) : ?>
      <tr><td colspan="7" class="rzpz-ha-empty">Ingen henvisninger fundet.</td></tr>
    <?php else : foreach ( $referrals as $r ) :
      $mgr   = $managers[ $r->manager_key ] ?? null;
      $label = $status_labels[ $r->status ] ?? $r->status;
      $cls   = esc_attr( $r->status );
    ?>
      <tr>
        <td><?php echo $r->id; ?></td>
        <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $r->submitted_at ) ) ); ?></td>
        <td>
          <strong><?php echo esc_html( $r->referrer_name ); ?></strong><br>
          <small style="color:#888"><?php echo esc_html( $r->referrer_email ); ?></small><br>
          <small style="color:#666"><?php echo esc_html( $r->referrer_phone ); ?></small>
        </td>
        <td>
          <strong><?php echo esc_html( $r->friend_name ); ?></strong><br>
          <small style="color:#888"><?php echo esc_html( $r->friend_email ); ?></small><br>
          <small style="color:#666"><?php echo esc_html( $r->friend_phone ); ?></small>
        </td>
        <td><?php echo $mgr ? esc_html( $mgr['label'] ) : esc_html( $r->manager_key ); ?></td>
        <td><span class="rzpz-status <?php echo $cls; ?>"><?php echo esc_html( $label ); ?></span></td>
        <td>
          <form method="post" class="rzpz-ha-form-inline" style="display:flex;gap:4px;align-items:center;">
            <?php wp_nonce_field( 'rzpz_henvis_status', 'rzpz_henvis_status_nonce' ); ?>
            <input type="hidden" name="referral_id" value="<?php echo $r->id; ?>">
            <select name="new_status">
              <?php foreach ( $status_labels as $k => $l ) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($r->status,$k); ?>><?php echo esc_html($l); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Gem</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

</div>
