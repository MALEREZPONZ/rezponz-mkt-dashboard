<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Admin view: Game history
 *
 * @var array $history    rows from rzlq_game_history
 * @var int   $total      total row count
 * @var int   $pages      total pages
 * @var int   $paged      current page
 * @var int   $dept_id    0 = all (admin), >0 = dept
 */

$is_admin = current_user_can( 'manage_options' );

$dept_map = [];
if ( $is_admin ) {
    foreach ( RZLQ_Dept::get_departments() as $d ) {
        $dept_map[ $d['id'] ] = $d;
    }
}
?>
<div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:980px">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
    <h1 style="display:flex;align-items:center;gap:10px;margin:0">
      📋 Quiz Historik
    </h1>
    <span style="color:#aaa;font-size:13px"><?php echo $total; ?> spil i alt</span>
  </div>

  <?php if ( empty( $history ) ) : ?>
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:56px;text-align:center">
    <div style="font-size:56px;margin-bottom:16px">🗂</div>
    <h2 style="font-size:18px;color:#111;margin:0 0 8px">Ingen spilhistorik endnu</h2>
    <p style="color:#888;font-size:14px;margin:0">Afsluttede spil vises her automatisk</p>
  </div>
  <?php else : ?>

  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:14px">
      <thead>
        <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
          <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888">Quiz</th>
          <?php if ( $is_admin ) : ?>
          <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888">Afdeling</th>
          <?php endif; ?>
          <th style="text-align:center;padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888">Spillere</th>
          <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888">Vinder 🏆</th>
          <th style="text-align:right;padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888">Afsluttet</th>
          <th style="padding:12px 16px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( $history as $row ) :
        $lb     = json_decode( $row['leaderboard'], true ) ?: [];
        $dept   = ( $is_admin && $row['dept_id'] && isset( $dept_map[ $row['dept_id'] ] ) ) ? $dept_map[ $row['dept_id'] ] : null;
      ?>
      <tr style="border-bottom:1px solid #f3f4f6" class="rzlq-hist-row" data-id="<?php echo (int) $row['id']; ?>">
        <td style="padding:14px 16px">
          <div style="font-weight:700;color:#111"><?php echo esc_html( $row['quiz_title'] ?: '(slettet quiz)' ); ?></div>
          <div style="font-size:12px;color:#aaa;margin-top:2px">
            PIN <?php echo esc_html( sprintf( '%06d', $row['game_id'] ) ); ?>
          </div>
        </td>
        <?php if ( $is_admin ) : ?>
        <td style="padding:14px 16px">
          <?php if ( $dept ) : ?>
          <span style="background:<?php echo esc_attr( $dept['color'] ); ?>;color:#fff;font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700">
            <?php echo esc_html( $dept['name'] ); ?>
          </span>
          <?php else : ?>
          <span style="color:#aaa;font-size:12px"><?php echo esc_html( $row['dept_name'] ?: '—' ); ?></span>
          <?php endif; ?>
        </td>
        <?php endif; ?>
        <td style="padding:14px 16px;text-align:center">
          <strong><?php echo (int) $row['player_count']; ?></strong>
        </td>
        <td style="padding:14px 16px">
          <?php if ( $row['winner_nickname'] ) : ?>
          <span style="font-weight:700">🥇 <?php echo esc_html( $row['winner_nickname'] ); ?></span>
          <span style="color:#738991;font-size:13px;margin-left:6px"><?php echo number_format( (int) $row['winner_score'], 0, ',', '.' ); ?> pt</span>
          <?php else : ?>
          <span style="color:#aaa">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:14px 16px;text-align:right;color:#888;font-size:13px">
          <?php echo $row['finished_at'] ? esc_html( wp_date( 'd.m.Y H:i', strtotime( $row['finished_at'] ) ) ) : '—'; ?>
        </td>
        <td style="padding:14px 16px;text-align:right">
          <button type="button"
                  onclick="rzlqToggleLb(<?php echo (int) $row['id']; ?>)"
                  style="background:none;border:1px solid #e5e7eb;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer;color:#555">
            Vis resultater ▾
          </button>
        </td>
      </tr>
      <!-- Expandable leaderboard row -->
      <tr id="rzlq-lb-<?php echo (int) $row['id']; ?>" style="display:none">
        <td colspan="<?php echo $is_admin ? 6 : 5; ?>" style="padding:0 16px 16px;background:#f9fafb">
          <div style="border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;margin-top:2px">
            <?php if ( $lb ) :
              $medals = ['🥇','🥈','🥉'];
              foreach ( array_slice( $lb, 0, 10 ) as $i => $p ) : ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid #f3f4f6;background:<?php echo $i < 3 ? 'rgba(250,204,21,.05)' : '#fff'; ?>">
              <span style="width:28px;font-size:16px;text-align:center"><?php echo $medals[$i] ?? ( $i + 1 ); ?></span>
              <span style="flex:1;font-size:14px;font-weight:600"><?php echo esc_html( $p['nickname'] ); ?></span>
              <span style="font-family:'Outfit',sans-serif;font-size:15px;font-weight:900;color:#738991">
                <?php echo number_format( (int) $p['score'], 0, ',', '.' ); ?> pt
              </span>
            </div>
            <?php endforeach;
            else : ?>
            <div style="padding:20px;color:#aaa;text-align:center;font-size:13px">Ingen spillere</div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ( $pages > 1 ) : ?>
  <div style="display:flex;gap:6px;justify-content:center;margin-top:20px">
    <?php for ( $p = 1; $p <= $pages; $p++ ) :
      $url = admin_url( 'admin.php?page=rzlq-history&paged=' . $p );
    ?>
    <a href="<?php echo esc_url( $url ); ?>"
       style="padding:7px 12px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;
              background:<?php echo $p == $paged ? '#738991' : '#fff'; ?>;
              color:<?php echo $p == $paged ? '#fff' : '#555'; ?>;
              border:1px solid <?php echo $p == $paged ? '#738991' : '#e5e7eb'; ?>">
      <?php echo $p; ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<script>
function rzlqToggleLb(id) {
  var row = document.getElementById('rzlq-lb-' + id);
  if (!row) return;
  var visible = row.style.display !== 'none';
  row.style.display = visible ? 'none' : 'table-row';
}
</script>
