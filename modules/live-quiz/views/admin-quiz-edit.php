<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var WP_Post $post */
/** @var int $quiz_id */
$saved    = ! empty( $_GET['saved'] );
$cover_id  = (int) get_post_meta( $quiz_id, '_rzlq_cover_id', true );
$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : '';

$dept_id   = (int) ( get_post_meta( $quiz_id, '_rzlq_dept_id', true ) ?: 0 );
$dept      = $dept_id ? RZLQ_Dept::get_department( $dept_id ) : null;
$q_count   = count( RZLQ_Quiz::get_questions( $quiz_id ) );
?>
<div class="wrap" id="rzlq-edit-wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:860px">

  <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzlq-quizzes' ) ); ?>"
       style="color:#888;text-decoration:none;font-size:14px">← Alle quizzer</a>
    <?php if ( $dept ) : ?>
    <span style="background:<?php echo esc_attr( $dept['color'] ); ?>;color:#fff;font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700">
      <?php echo esc_html( $dept['name'] ); ?>
    </span>
    <?php endif; ?>
  </div>

  <?php if ( $saved ) : ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:20px;color:#166534;font-size:14px">
    ✓ Quiz gemt
  </div>
  <?php endif; ?>

  <!-- Main edit form -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rzlq-edit-form">
    <input type="hidden" name="action"          value="rzlq_save_quiz">
    <input type="hidden" name="quiz_id"         value="<?php echo $quiz_id; ?>">
    <input type="hidden" name="rzlq_questions"  id="rzlq-questions-json" value="">
    <?php wp_nonce_field( 'rzlq_save_quiz_' . $quiz_id, 'rzlq_nonce' ); ?>

    <!-- Header card -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <div style="display:flex;gap:20px;align-items:flex-start">

        <!-- Cover image -->
        <div id="rzlq-cover-wrap" style="flex-shrink:0;cursor:pointer" onclick="rzlqPickCover()">
          <div style="width:100px;height:100px;border-radius:12px;background:#f3f4f6;border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:32px" id="rzlq-cover-preview">
            <?php echo $cover_url
              ? '<img src="' . esc_url( $cover_url ) . '" style="width:100%;height:100%;object-fit:cover">'
              : '🖼️'; ?>
          </div>
          <p style="font-size:11px;color:#aaa;margin:5px 0 0;text-align:center">Klik for billede</p>
        </div>
        <input type="hidden" name="rzlq_cover_id" id="rzlq-cover-id" value="<?php echo $cover_id; ?>">

        <div style="flex:1">
          <label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;display:block;margin-bottom:6px">Quiztitel</label>
          <input type="text" name="quiz_title" value="<?php echo esc_attr( $post->post_title ); ?>"
                 style="width:100%;font-size:20px;font-weight:700;border:1.5px solid #e5e7eb;border-radius:8px;padding:12px 14px;outline:none;font-family:inherit"
                 placeholder="Giv din quiz et navn…"
                 onfocus="this.style.borderColor='#738991'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
      </div>
    </div>

    <!-- Question builder (JS-driven) -->
    <div id="rzlq-qbuilder">
      <div style="text-align:center;padding:40px;color:#aaa;font-size:14px">Indlæser spørgsmålsbygger…</div>
    </div>

    <!-- Save bar — NOTE: delete form is OUTSIDE this form to avoid nesting -->
    <div style="position:sticky;bottom:0;background:#fff;border-top:1px solid #e5e7eb;padding:14px 0;margin-top:24px;display:flex;justify-content:space-between;align-items:center;z-index:100">
      <div>
        <button type="button" id="rzlq-delete-btn"
                style="background:none;border:none;color:#ef4444;font-size:13px;cursor:pointer;padding:8px">
          🗑 Slet quiz
        </button>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="rzlqStartGame()"
                style="background:#f97316;color:#fff;border:none;border-radius:8px;padding:11px 22px;font-size:14px;font-weight:700;cursor:pointer"
                <?php echo $q_count < 1 ? 'disabled title="Tilføj spørgsmål først"' : ''; ?>>
          ▶ Start spil
        </button>
        <button type="button" onclick="rzlqSave()"
                style="background:#738991;color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:14px;font-weight:700;cursor:pointer">
          💾 Gem quiz
        </button>
      </div>
    </div>
  </form>

  <!-- Delete form — OUTSIDE main form to avoid invalid nested <form> -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rzlq-delete-form">
    <input type="hidden" name="action"   value="rzlq_delete_quiz">
    <input type="hidden" name="quiz_id"  value="<?php echo $quiz_id; ?>">
    <?php wp_nonce_field( 'rzlq_delete_' . $quiz_id, 'rzlq_del_nonce' ); ?>
  </form>

  <!-- Start game form — also outside main form -->
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rzlq-start-form">
    <input type="hidden" name="action"   value="rzlq_start_game">
    <input type="hidden" name="quiz_id"  value="<?php echo $quiz_id; ?>">
    <?php wp_nonce_field( 'rzlq_start_' . $quiz_id, 'rzlq_start_nonce' ); ?>
  </form>
</div>

<script>
function rzlqPickCover() {
  var frame = wp.media({ title: 'Vælg coverbillede', button: { text: 'Vælg' }, multiple: false });
  frame.on('select', function() {
    var att = frame.state().get('selection').first().toJSON();
    document.getElementById('rzlq-cover-id').value = att.id;
    document.getElementById('rzlq-cover-preview').innerHTML =
      '<img src="' + att.url + '" style="width:100%;height:100%;object-fit:cover">';
  });
  frame.open();
}

function rzlqSave() {
  if (typeof rzlqSerialize === 'function') rzlqSerialize();
  document.getElementById('rzlq-edit-form').submit();
}

function rzlqStartGame() {
  if (!confirm('Gem og start spil? Dine ændringer gemmes automatisk.')) return;
  if (typeof rzlqSerialize === 'function') rzlqSerialize();
  // Inject a flag so handle_save_quiz redirects to host after save
  var flag = document.createElement('input');
  flag.type = 'hidden'; flag.name = 'rzlq_after_save'; flag.value = 'start';
  document.getElementById('rzlq-edit-form').appendChild(flag);
  document.getElementById('rzlq-edit-form').submit();
}

document.getElementById('rzlq-delete-btn').addEventListener('click', function() {
  if (confirm('Er du sikker på, at du vil slette denne quiz? Handlingen kan ikke fortrydes.')) {
    document.getElementById('rzlq-delete-form').submit();
  }
});
</script>
