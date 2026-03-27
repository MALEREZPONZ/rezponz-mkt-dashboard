<?php if ( ! defined( 'ABSPATH' ) ) exit;
$opts     = get_option( 'rzpz_crew_settings', [] );
$dest_url = $opts['default_destination_url'] ?? 'https://rezponz.dk/jobs/';
?>
<div class="wrap rzpz-crew-wrap">
  <div class="rzpz-crew-header">
    <div>
      <h1><?php echo esc_html( $member['display_name'] ); ?></h1>
      <p class="rzpz-crew-sub">
        Crew ID: <code><?php echo esc_html( $member['crew_id'] ); ?></code> &nbsp;·&nbsp;
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew' ) ); ?>">← Alle Crew Members</a>
      </p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew&action=edit&member_id=' . $member['id'] ) ); ?>" class="rzpz-crew-btn">✏️ Rediger</a>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Er du sikker på du vil slette denne Crew Member?')">
        <?php wp_nonce_field( 'rzpz_crew_delete_member' ); ?>
        <input type="hidden" name="action" value="rzpz_crew_delete_member" />
        <input type="hidden" name="member_id" value="<?php echo (int) $member['id']; ?>" />
        <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-danger">🗑 Slet</button>
      </form>
    </div>
  </div>

  <?php if ( isset( $_GET['saved'] ) ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-success">✓ Gemt.</div>
  <?php endif; ?>
  <?php if ( isset( $_GET['link_created'] ) ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-success">✓ Link oprettet.</div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="rzpz-crew-kpi-row">
    <div class="rzpz-crew-kpi">
      <div class="rzpz-crew-kpi-label">Total klik</div>
      <div class="rzpz-crew-kpi-val"><?php echo number_format( $clicks, 0, ',', '.' ); ?></div>
    </div>
    <div class="rzpz-crew-kpi">
      <div class="rzpz-crew-kpi-label">Leads / konverteringer</div>
      <div class="rzpz-crew-kpi-val"><?php echo (int) $conversions; ?></div>
    </div>
    <div class="rzpz-crew-kpi">
      <div class="rzpz-crew-kpi-label">Konverteringsrate</div>
      <div class="rzpz-crew-kpi-val"><?php echo $clicks > 0 ? number_format( $conversions / $clicks * 100, 1 ) . '%' : '–'; ?></div>
    </div>
    <div class="rzpz-crew-kpi">
      <div class="rzpz-crew-kpi-label">Status</div>
      <div class="rzpz-crew-kpi-val">
        <span class="rzpz-crew-badge rzpz-crew-badge-<?php echo $member['status'] === 'active' ? 'active' : 'paused'; ?>">
          <?php echo $member['status'] === 'active' ? 'Aktiv' : 'Inaktiv'; ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Generate Link -->
  <div class="rzpz-crew-card">
    <div class="rzpz-crew-card-title">🔗 Generér UTM-trackinglink</div>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rzpz-crew-link-form">
      <?php wp_nonce_field( 'rzpz_crew_generate_link' ); ?>
      <input type="hidden" name="action" value="rzpz_crew_generate_link" />
      <input type="hidden" name="crew_member_id" value="<?php echo (int) $member['id']; ?>" />
      <div class="rzpz-crew-field-row" style="align-items:flex-end;gap:12px">
        <div class="rzpz-crew-field">
          <label>Kampagnenavn <small style="color:#555">(utm_campaign)</small></label>
          <input type="text" name="campaign_name" placeholder="rekruttering-foraaret-2025" required />
        </div>
        <div class="rzpz-crew-field">
          <label>Destinations-URL</label>
          <input type="url" name="destination_url" value="<?php echo esc_attr( $dest_url ); ?>" required />
        </div>
        <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary" style="white-space:nowrap">✨ Generér link</button>
      </div>
    </form>
  </div>

  <!-- Links Table -->
  <?php if ( ! empty( $links ) ) : ?>
  <div class="rzpz-crew-card">
    <div class="rzpz-crew-card-title">Alle trackinglinks (<?php echo count( $links ); ?>)</div>
    <table class="rzpz-crew-table">
      <thead><tr><th>Kampagne</th><th>Klik</th><th>Oprettet</th><th>Link</th><th></th></tr></thead>
      <tbody>
      <?php foreach ( $links as $lnk ) : ?>
      <tr>
        <td><?php echo esc_html( $lnk['campaign_name'] ); ?></td>
        <td><?php echo number_format( (int) $lnk['clicks'], 0, ',', '.' ); ?></td>
        <td><?php echo esc_html( substr( $lnk['created_at'], 0, 10 ) ); ?></td>
        <td>
          <div class="rzpz-crew-link-copy-wrap">
            <input type="text" class="rzpz-crew-link-input" value="<?php echo esc_attr( $lnk['full_url'] ); ?>" readonly onclick="this.select()" />
            <button class="rzpz-crew-btn rzpz-crew-copy-btn" data-copy="<?php echo esc_attr( $lnk['full_url'] ); ?>">📋 Kopiér</button>
          </div>
        </td>
        <td>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Slet dette trackinglink?')">
            <input type="hidden" name="action" value="rzpz_crew_delete_link" />
            <input type="hidden" name="link_id" value="<?php echo (int) $lnk['id']; ?>" />
            <input type="hidden" name="member_id" value="<?php echo (int) $member['id']; ?>" />
            <?php wp_nonce_field( 'rzpz_crew_delete_link' ); ?>
            <button type="submit" class="rzpz-crew-btn" style="background:#ef444420;color:#ef4444;border-color:#ef444440;">🗑 Slet</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Top Links -->
  <?php if ( ! empty( $top_links ) ) : ?>
  <div class="rzpz-crew-card">
    <div class="rzpz-crew-card-title">🏆 Top 5 links</div>
    <table class="rzpz-crew-table">
      <thead><tr><th>#</th><th>Kampagne</th><th>Klik</th><th>Leads</th></tr></thead>
      <tbody>
      <?php foreach ( $top_links as $i => $tl ) : ?>
      <tr>
        <td><?php echo $i + 1; ?></td>
        <td><?php echo esc_html( $tl['campaign_name'] ); ?></td>
        <td><?php echo number_format( (int) $tl['clicks'], 0, ',', '.' ); ?></td>
        <td><?php echo (int) $tl['conversions']; ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
