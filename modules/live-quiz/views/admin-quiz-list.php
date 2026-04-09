<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array $quizzes   WP_Post[] */
/** @var array $active    active games */
/** @var int   $dept_id   0 = all, >0 = filtered */

$del_notice  = ! empty( $_GET['deleted'] );
$new_quiz_url = admin_url( 'admin.php?page=rzlq-edit-quiz' );
$is_admin    = current_user_can( 'manage_options' );

// Dept map for badges (only fetched once for admin view)
$dept_map = [];
if ( $is_admin ) {
    foreach ( RZLQ_Dept::get_departments() as $d ) {
        $dept_map[ $d['id'] ] = $d;
    }
}
?>
<div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:960px">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
    <h1 style="display:flex;align-items:center;gap:10px;margin:0">
      <span style="font-size:26px">🎮</span> Live Quiz
    </h1>
    <a href="<?php echo esc_url( $new_quiz_url ); ?>"
       style="display:inline-flex;align-items:center;gap:6px;background:#738991;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px">
      + Opret ny quiz
    </a>
  </div>

  <?php if ( $del_notice ) : ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:20px;color:#166534;font-size:14px">✓ Quiz slettet</div>
  <?php endif; ?>

  <!-- Active games -->
  <?php if ( $active ) : ?>
  <div style="background:#fff8f0;border:1px solid #fed7aa;border-radius:12px;padding:20px 24px;margin-bottom:28px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:700;color:#c2410c">⚡ Aktive spil</h3>
    <?php foreach ( $active as $g ) : ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #fed7aa">
      <div>
        <strong style="font-size:15px">PIN: <?php echo esc_html( $g['pin'] ); ?></strong>
        <span style="color:#888;font-size:13px;margin-left:12px"><?php echo esc_html( $g['quiz_title'] ); ?></span>
        <span style="background:#f97316;color:#fff;font-size:11px;padding:2px 8px;border-radius:999px;margin-left:8px">
          <?php echo (int) $g['player_count']; ?> spillere
        </span>
        <?php
        if ( $is_admin && ! empty( $g['dept_id'] ) && isset( $dept_map[ $g['dept_id'] ] ) ) {
            $d = $dept_map[ $g['dept_id'] ];
            echo '<span style="background:' . esc_attr( $d['color'] ) . ';color:#fff;font-size:11px;padding:2px 8px;border-radius:999px;margin-left:6px">' . esc_html( $d['name'] ) . '</span>';
        }
        ?>
      </div>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzlq-host&game_id=' . $g['id'] ) ); ?>"
         style="background:#e8590c;color:#fff;padding:7px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:700">
        Genåbn →
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Quiz list -->
  <?php if ( empty( $quizzes ) ) : ?>
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:56px;text-align:center">
    <div style="font-size:56px;margin-bottom:16px">🎯</div>
    <h2 style="font-size:18px;color:#111;margin:0 0 8px">Ingen quizzer endnu</h2>
    <p style="color:#888;font-size:14px;margin:0 0 20px">Opret din første quiz og kør den live</p>
    <a href="<?php echo esc_url( $new_quiz_url ); ?>"
       style="display:inline-block;background:#738991;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700">
      Opret quiz nu →
    </a>
  </div>
  <?php else : ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ( $quizzes as $q ) :
      $questions  = RZLQ_Quiz::get_questions( $q->ID );
      $q_count    = count( $questions );
      $cover      = get_post_meta( $q->ID, '_rzlq_cover_id', true );
      $thumb      = $cover ? wp_get_attachment_image_url( (int) $cover, 'thumbnail' ) : '';
      $q_dept_id  = (int) get_post_meta( $q->ID, '_rzlq_dept_id', true );
      $q_dept     = ( $is_admin && $q_dept_id && isset( $dept_map[ $q_dept_id ] ) ) ? $dept_map[ $q_dept_id ] : null;
    ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <!-- Thumbnail -->
      <div style="width:52px;height:52px;border-radius:10px;background:<?php echo $thumb ? 'transparent' : '#f3f4f6'; ?>;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:24px">
        <?php echo $thumb ? '<img src="' . esc_url( $thumb ) . '" style="width:100%;height:100%;object-fit:cover">' : '🎯'; ?>
      </div>

      <!-- Info -->
      <div style="flex:1;min-width:0">
        <div style="font-size:16px;font-weight:700;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?php echo esc_html( $q->post_title ); ?>
          <?php if ( $q_dept ) : ?>
          <span style="background:<?php echo esc_attr( $q_dept['color'] ); ?>;color:#fff;font-size:11px;padding:2px 9px;border-radius:999px;font-weight:700;margin-left:8px;vertical-align:middle">
            <?php echo esc_html( $q_dept['name'] ); ?>
          </span>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:#888;margin-top:3px">
          <?php echo $q_count; ?> spørgsmål · Oprettet <?php echo esc_html( wp_date( 'd.m.Y', strtotime( $q->post_date ) ) ); ?>
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:8px;flex-shrink:0">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzlq-edit-quiz&quiz_id=' . $q->ID ) ); ?>"
           style="padding:8px 14px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;color:#555">
          ✏️ Rediger
        </a>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action"          value="rzlq_start_game">
          <input type="hidden" name="quiz_id"         value="<?php echo $q->ID; ?>">
          <?php wp_nonce_field( 'rzlq_start_' . $q->ID, 'rzlq_start_nonce' ); ?>
          <button type="submit"
                  style="padding:8px 16px;background:#738991;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700"
                  <?php echo $q_count < 1 ? 'disabled title="Tilføj spørgsmål først"' : ''; ?>>
            ▶ Start spil
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <p style="color:#aaa;font-size:12px;margin-top:16px">
    Shortcode til spillersiden: <code>[rezponz_player]</code>
  </p>
  <?php endif; ?>
</div>
