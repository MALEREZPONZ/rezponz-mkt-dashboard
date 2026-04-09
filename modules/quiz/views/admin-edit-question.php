<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$qid      = (int) ( $_GET['qid'] ?? 0 );
$profiles = RZPA_Quiz_DB::get_profiles();
$q        = null;
$answers  = [];

if ( $qid ) {
    global $wpdb;
    $qt = $wpdb->prefix . 'rzpa_quiz_questions';
    $at = $wpdb->prefix . 'rzpa_quiz_answers';
    $q  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$qt} WHERE id = %d", $qid ), ARRAY_A );
    if ( $q ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$at} WHERE question_id = %d ORDER BY sort_order ASC", $qid ),
            ARRAY_A
        ) ?: [];
        foreach ( $rows as $r ) {
            $r['weights'] = json_decode( $r['weights'] ?? '{}', true ) ?: [];
            $answers[]    = $r;
        }
    }
}

// Pad to 4 answers
while ( count( $answers ) < 4 ) {
    $answers[] = [ 'id' => 0, 'answer_text' => '', 'feedback_text' => '', 'tagline' => '', 'weights' => [] ];
}

$is_new   = ! $q;
$back_url = admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=questions' );
?>
<div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:860px;padding-top:16px">

  <!-- Header -->
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:28px">
    <a href="<?php echo esc_url( $back_url ); ?>"
       style="color:#6b7280;text-decoration:none;font-size:13px;display:inline-flex;align-items:center;gap:5px">
      ← Alle spørgsmål
    </a>
  </div>

  <div style="display:flex;align-items:center;gap:14px;margin-bottom:28px">
    <div style="background:linear-gradient(135deg,#e8590c,#d6336c);width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">✏️</div>
    <div>
      <h1 style="margin:0;font-size:20px;font-weight:800;color:#111827">
        <?php echo $is_new ? 'Nyt spørgsmål' : 'Rediger spørgsmål'; ?>
      </h1>
      <?php if ( ! $is_new ) : ?>
      <div style="font-size:12px;color:#9ca3af;margin-top:2px">ID #<?php echo $qid; ?></div>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="rzpa_quiz_save_question">
    <input type="hidden" name="qid"    value="<?php echo $qid; ?>">
    <?php wp_nonce_field( 'rzpa_quiz_save_question', 'rzpa_q_nonce' ); ?>

    <!-- Question card -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <h2 style="margin:0 0 20px;font-size:14px;font-weight:800;color:#111827;text-transform:uppercase;letter-spacing:.5px">Spørgsmål</h2>

      <div style="margin-bottom:16px">
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin-bottom:6px">Spørgsmålstekst <span style="color:#ef4444">*</span></label>
        <textarea name="question_text" rows="2" required
                  style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:11px 14px;font-size:15px;font-weight:600;color:#111;outline:none;resize:vertical;font-family:inherit;line-height:1.4"
                  onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'"
                  placeholder="Hvad er din første reaktion, når…"><?php echo $q ? esc_textarea( $q['question_text'] ) : ''; ?></textarea>
      </div>

      <div style="margin-bottom:16px">
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin-bottom:6px">Hjælpetekst <span style="font-weight:400;text-transform:none">(valgfrit — vises under spørgsmålet)</span></label>
        <input type="text" name="helper_text" value="<?php echo $q ? esc_attr( $q['helper_text'] ) : ''; ?>"
               style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 14px;font-size:14px;color:#111;outline:none;font-family:inherit"
               onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'"
               placeholder="Tænk på din naturlige reaktion – ikke hvad du "burde" gøre">
      </div>

      <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151">
        <input type="checkbox" name="is_active" value="1" <?php checked( $q ? $q['is_active'] : 1, 1 ); ?>
               style="width:16px;height:16px;accent-color:#e8590c">
        Spørgsmål er aktivt (vises i quizzen)
      </label>
    </div>

    <!-- Answers -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <h2 style="margin:0 0 4px;font-size:14px;font-weight:800;color:#111827;text-transform:uppercase;letter-spacing:.5px">Svarmuligheder</h2>
      <p style="font-size:12px;color:#9ca3af;margin:0 0 24px">Hvert svar tildeler point til én eller flere profiler via vægtning (0–3)</p>

      <?php foreach ( $answers as $idx => $a ) :
        $colors = ['#ec4899','#f97316','#8b5cf6','#10b981']; // match profile colors for visual hint
      ?>
      <div style="border:1.5px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px;position:relative;background:#fafafa">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:14px">Svar <?php echo $idx + 1; ?></div>

        <!-- Answer text -->
        <div style="margin-bottom:12px">
          <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">Svartekst</label>
          <input type="text" name="answer_text[<?php echo $idx; ?>]"
                 value="<?php echo esc_attr( $a['answer_text'] ); ?>"
                 style="width:100%;border:1.5px solid #e5e7eb;border-radius:7px;padding:9px 12px;font-size:14px;color:#111;outline:none;font-family:inherit;background:#fff"
                 onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'"
                 placeholder="Jeg lytter nøje og prøver at forstå…">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
          <!-- Feedback -->
          <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">Feedback-tekst <span style="font-weight:400">(vises efter valg)</span></label>
            <input type="text" name="answer_fb[<?php echo $idx; ?>]"
                   value="<?php echo esc_attr( $a['feedback_text'] ); ?>"
                   style="width:100%;border:1.5px solid #e5e7eb;border-radius:7px;padding:9px 12px;font-size:13px;color:#111;outline:none;font-family:inherit;background:#fff"
                   onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'"
                   placeholder="Du sætter folk i centrum…">
          </div>
          <!-- Tagline -->
          <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">Tagline <span style="font-weight:400">(kort label)</span></label>
            <input type="text" name="answer_tag[<?php echo $idx; ?>]"
                   value="<?php echo esc_attr( $a['tagline'] ); ?>"
                   style="width:100%;border:1.5px solid #e5e7eb;border-radius:7px;padding:9px 12px;font-size:13px;color:#111;outline:none;font-family:inherit;background:#fff"
                   onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'"
                   placeholder="Lytteren er i dig">
          </div>
        </div>

        <!-- Weights -->
        <div>
          <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:8px">Profil-vægte (0 = ingen, 3 = stærkt match)</label>
          <div style="display:grid;grid-template-columns:repeat(<?php echo count($profiles); ?>,1fr);gap:8px">
            <?php foreach ( $profiles as $p ) :
              $w = $a['weights'][ $p['slug'] ] ?? 0;
            ?>
            <div style="text-align:center">
              <div style="font-size:18px;margin-bottom:4px"><?php echo esc_html( $p['icon_emoji'] ); ?></div>
              <div style="font-size:10px;color:#6b7280;margin-bottom:5px;font-weight:600"><?php echo esc_html( mb_substr( $p['title'], 4 ) ?: $p['title'] ); ?></div>
              <input type="number" name="answer_weights[<?php echo $idx; ?>][<?php echo esc_attr( $p['slug'] ); ?>]"
                     value="<?php echo (int) $w; ?>" min="0" max="3"
                     style="width:100%;border:1.5px solid <?php echo esc_attr( $p['color'] ); ?>40;border-radius:7px;padding:7px 4px;font-size:15px;font-weight:700;text-align:center;color:<?php echo esc_attr( $p['color'] ); ?>;background:<?php echo esc_attr( $p['color'] ); ?>0d;outline:none;font-family:inherit">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Save bar -->
    <div style="position:sticky;bottom:0;background:#fff;border-top:1px solid #e5e7eb;padding:14px 0;display:flex;justify-content:space-between;align-items:center;z-index:100;margin-top:4px">
      <a href="<?php echo esc_url( $back_url ); ?>"
         style="color:#6b7280;text-decoration:none;font-size:14px;padding:10px 0">
        Annuller
      </a>
      <button type="submit"
              style="background:linear-gradient(135deg,#e8590c,#d6336c);color:#fff;border:none;border-radius:10px;padding:12px 28px;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px">
        💾 Gem spørgsmål
      </button>
    </div>
  </form>
</div>
