<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rzpz-crew-wrap">
  <div class="rzpz-crew-header">
    <div>
      <h1>👥 Rezponz Crew</h1>
      <p class="rzpz-crew-sub">Administrer dine Crew Members og se deres performance</p>
    </div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew&action=add' ) ); ?>" class="rzpz-crew-btn rzpz-crew-btn-primary">+ Tilføj Crew Member</a>
  </div>

  <?php if ( isset( $_GET['deleted'] ) ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-success">✓ Crew Member slettet.</div>
  <?php endif; ?>

  <!-- Leaderboard -->
  <?php if ( ! empty( $leaderboard ) ) : ?>
  <div class="rzpz-crew-card" style="margin-bottom:24px">
    <div class="rzpz-crew-card-title">🏆 Leaderboard — seneste 30 dage</div>
    <div class="rzpz-crew-leaderboard">
      <?php foreach ( array_slice( $leaderboard, 0, 3 ) as $i => $row ) :
        $medals = ['🥇','🥈','🥉'];
      ?>
      <div class="rzpz-crew-lb-card">
        <div class="rzpz-crew-lb-medal"><?php echo $medals[$i] ?? '#' . ($i+1); ?></div>
        <div class="rzpz-crew-lb-name"><?php echo esc_html( $row['display_name'] ); ?></div>
        <div class="rzpz-crew-lb-stats">
          <span><?php echo (int) $row['total_conversions']; ?> leads</span>
          <span><?php echo (int) $row['total_clicks']; ?> klik</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Members Table -->
  <div class="rzpz-crew-card">
    <div class="rzpz-crew-card-title">Alle Crew Members (<?php echo count( $members ); ?>)</div>
    <?php if ( empty( $members ) ) : ?>
    <div class="rzpz-crew-empty">Ingen Crew Members endnu. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew&action=add' ) ); ?>">Tilføj den første →</a></div>
    <?php else : ?>
    <table class="rzpz-crew-table">
      <thead><tr>
        <th>Navn</th><th>Crew ID</th><th>Email</th><th>Status</th><th>Total klik</th><th>Total leads</th><th>Handling</th>
      </tr></thead>
      <tbody>
      <?php foreach ( $members as $m ) :
        $lb_row = array_values( array_filter( $leaderboard, fn($r) => $r['id'] === $m['id'] ) )[0] ?? null;
      ?>
      <tr>
        <td><strong><?php echo esc_html( $m['display_name'] ); ?></strong></td>
        <td><code style="background:#1a1a1a;padding:2px 6px;border-radius:4px"><?php echo esc_html( $m['crew_id'] ); ?></code></td>
        <td><?php echo esc_html( $m['email'] ); ?></td>
        <td><span class="rzpz-crew-badge rzpz-crew-badge-<?php echo $m['status'] === 'active' ? 'active' : 'paused'; ?>"><?php echo $m['status'] === 'active' ? 'Aktiv' : 'Inaktiv'; ?></span></td>
        <td><?php echo $lb_row ? number_format( (int) $lb_row['total_clicks'], 0, ',', '.' ) : '–'; ?></td>
        <td><?php echo $lb_row ? (int) $lb_row['total_conversions'] : '–'; ?></td>
        <td class="rzpz-crew-actions">
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew&member_id=' . $m['id'] ) ); ?>">Se detaljer</a>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew&action=edit&member_id=' . $m['id'] ) ); ?>">Rediger</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
