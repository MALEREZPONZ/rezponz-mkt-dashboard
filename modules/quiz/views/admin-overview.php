<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var string $tab  set by RZPA_Quiz_Admin::page_main() */
$tab = $tab ?? 'submissions';
?>
<div class="wrap rzpa-quiz-wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:1060px;padding-top:16px">

  <!-- ── Page header ──────────────────────────────────────────────────────── -->
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px">
    <div style="background:linear-gradient(135deg,#e8590c,#d6336c);width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 14px rgba(232,89,12,.35)">🎯</div>
    <div>
      <h1 style="margin:0;font-size:22px;font-weight:800;color:#111827;line-height:1.2">Profil-Quiz</h1>
      <div style="font-size:13px;color:#6b7280;margin-top:2px">Customer Success DNA · <code style="background:#f3f4f6;padding:2px 7px;border-radius:4px;font-size:12px">[rezponz_quiz]</code></div>
    </div>
  </div>

  <!-- ── Tab navigation ───────────────────────────────────────────────────── -->
  <div style="display:flex;gap:2px;margin-bottom:28px;background:#f3f4f6;border-radius:10px;padding:4px;width:fit-content">
    <?php
    $tabs = [
        'submissions' => [ 'label' => '📋 Besvarelser',        'icon' => '📋' ],
        'questions'   => [ 'label' => '✏️ Spørgsmål',           'icon' => '✏️' ],
        'email'       => [ 'label' => '✉️ E-mail skabeloner',   'icon' => '✉️' ],
    ];
    foreach ( $tabs as $slug => $info ) :
        $active = $slug === $tab;
        $url    = admin_url( 'admin.php?page=rzpa-quiz-submissions&tab=' . $slug );
    ?>
    <a href="<?php echo esc_url( $url ); ?>"
       style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:<?php echo $active ? '700' : '500'; ?>;text-decoration:none;transition:all .15s;<?php echo $active ? 'background:#fff;color:#111;box-shadow:0 1px 5px rgba(0,0,0,.1)' : 'color:#6b7280;background:transparent'; ?>">
      <?php echo $info['label']; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════════
       TAB: BESVARELSER
  ══════════════════════════════════════════════════════════════════════ -->
  <?php
  // Vis PDF-fejl fra seneste email-forsøg (gemmes som transient)
  $pdf_err = get_transient( 'rzpa_quiz_pdf_error' );
  if ( $pdf_err ) :
      delete_transient( 'rzpa_quiz_pdf_error' );
  ?>
  <div style="background:#2d0a0a;color:#f87171;border:1px solid #f8717140;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-family:monospace">
    ⚠️ <strong>PDF-fejl (seneste besvarelse):</strong><br><br><?php echo esc_html( $pdf_err ); ?>
  </div>
  <?php endif; ?>

  <?php
  $mail_err = get_transient( 'rzpa_quiz_mail_error' );
  if ( $mail_err ) :
      delete_transient( 'rzpa_quiz_mail_error' );
  ?>
  <div style="background:#1a1a2e;color:#93c5fd;border:1px solid #3b82f640;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-family:monospace">
    📧 <strong>Mail-fejl (seneste besvarelse):</strong><br><br><?php echo esc_html( $mail_err ); ?><br><br>
    <span style="color:#6b7280">Konfigurér SMTP under <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="color:#60a5fa">Indstillinger → SMTP</a> for pålidelig email-afsendelse.</span>
  </div>
  <?php endif; ?>

  <?php if ( $tab === 'submissions' ) :

    $per_page    = 20;
    $current_pag = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $offset      = ( $current_pag - 1 ) * $per_page;
    $total       = RZPA_Quiz_DB::count_submissions();
    $submissions = RZPA_Quiz_DB::get_submissions( $per_page, $offset );
    $dist        = RZPA_Quiz_DB::get_profile_distribution();
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
  ?>

  <!-- KPI row -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <div style="font-size:30px;font-weight:900;color:#111827;letter-spacing:-1px"><?php echo number_format( $total ); ?></div>
      <div style="font-size:12px;color:#6b7280;margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Besvarelser i alt</div>
    </div>
    <?php foreach ( $dist as $d ) : ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,.04);border-left:4px solid <?php echo esc_attr( $d['color'] ); ?>">
      <div style="font-size:22px;font-weight:900;color:#111827"><?php echo esc_html( $d['icon_emoji'] ); ?> <?php echo (int) $d['total']; ?></div>
      <div style="font-size:11px;color:#6b7280;margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.4px"><?php echo esc_html( $d['title'] ); ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ( empty( $submissions ) ) : ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:60px;text-align:center;color:#9ca3af">
      <div style="font-size:48px;margin-bottom:16px">📭</div>
      <p style="font-size:16px;margin:0;font-weight:600">Ingen besvarelser endnu</p>
      <p style="font-size:13px;margin:8px 0 0">Del quizzen og vent på de første svar!</p>
    </div>
  <?php else : ?>

  <!-- Submissions table -->
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">#</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Navn</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Telefon</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Email</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Profil</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">GDPR</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Tidspunkt</th>
          <th style="padding:12px 16px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.5px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $submissions as $i => $s ) : ?>
        <tr style="border-bottom:1px solid #f3f4f6;<?php echo $i % 2 !== 0 ? 'background:#fafafa' : ''; ?>">
          <td style="padding:12px 16px;color:#9ca3af;font-size:11px;font-weight:600"><?php echo $offset + $i + 1; ?></td>
          <td style="padding:12px 16px;font-weight:700;color:#111827"><?php echo esc_html( $s['name'] ); ?></td>
          <td style="padding:12px 16px;color:#4b5563"><?php echo esc_html( $s['phone'] ); ?></td>
          <td style="padding:12px 16px;color:#4b5563"><?php echo $s['email'] ? esc_html( $s['email'] ) : '<span style="color:#d1d5db">—</span>'; ?></td>
          <td style="padding:12px 16px">
            <?php if ( $s['profile_title'] ) : ?>
            <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo esc_attr( $s['profile_color'] ); ?>18;color:<?php echo esc_attr( $s['profile_color'] ); ?>;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid <?php echo esc_attr( $s['profile_color'] ); ?>30">
              <?php echo esc_html( $s['icon_emoji'] . ' ' . $s['profile_title'] ); ?>
            </span>
            <?php else : ?>
            <span style="color:#d1d5db">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px">
            <?php if ( $s['consent'] ) : ?>
              <span style="background:#d1fae5;color:#065f46;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px">✓ Ja</span>
            <?php else : ?>
              <span style="background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px">✗ Nej</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;color:#9ca3af;font-size:12px">
            <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $s['created_at'] ) ) ); ?>
          </td>
          <td style="padding:10px 14px">
            <button onclick="rzpaToggleDetail(<?php echo (int) $s['id']; ?>)"
                    id="rzpa-btn-<?php echo (int) $s['id']; ?>"
                    style="background:#f3f4f6;border:none;cursor:pointer;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;color:#374151;white-space:nowrap">
              👁 Se svar
            </button>
          </td>
        </tr>
        <tr id="rzpa-detail-<?php echo (int) $s['id']; ?>" style="display:none">
          <td colspan="9" style="padding:0;border-bottom:2px solid rgba(232,89,12,.15)">
            <div id="rzpa-detail-body-<?php echo (int) $s['id']; ?>" style="padding:24px 28px;background:#fafbff">
              <div style="color:#9ca3af;font-size:13px">Henter data…</div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php /* REST nonce for JS */ ?>
    <script>
    var rzpaRestNonce    = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
    var rzpaAdminUrl     = '<?php echo esc_js( admin_url( 'admin.php' ) ); ?>';
    var rzpaAdminPostUrl = '<?php echo esc_js( admin_url( 'admin-post.php' ) ); ?>';
    var rzpaPdfNonce     = '<?php echo esc_js( wp_create_nonce( 'rzpa_quiz_download_pdf' ) ); ?>';

    function rzpaToggleDetail(id) {
      var row  = document.getElementById('rzpa-detail-' + id);
      var body = document.getElementById('rzpa-detail-body-' + id);
      var btn  = document.getElementById('rzpa-btn-' + id);
      if (row.style.display !== 'none') {
        row.style.display = 'none';
        btn.textContent = '👁 Se svar';
        return;
      }
      row.style.display = '';
      btn.textContent = '▲ Skjul';
      if (body.dataset.loaded) return;
      body.dataset.loaded = '1';
      fetch('/wp-json/rzpa/v1/quiz/submission/' + id, {
        headers: { 'X-WP-Nonce': rzpaRestNonce }
      })
      .then(function(r){ return r.json(); })
      .then(function(d){ body.innerHTML = rzpaBuildDetail(d); })
      .catch(function(){ body.innerHTML = '<div style="color:#dc2626;font-size:13px">Fejl ved hentning af data.</div>'; });
    }

    function rzpaBuildDetail(d) {
      var profileNames = { empatisk: 'Den Empatiske Lytter', energisk: 'Energibomben', analytisk: 'Problemknuseren', social: 'Netværksmesteren' };
      var scores = d.scores || {};
      var maxScore = Math.max.apply(null, Object.values(scores).map(Number)) || 1;

      var qaHtml = '';
      if (d.qa && d.qa.length) {
        d.qa.forEach(function(q, i) {
          qaHtml += '<div style="margin-bottom:14px">'
            + '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:4px">' + (i+1) + '. ' + rzpaEsc(q.question_text) + '</div>'
            + '<div style="font-size:13px;color:#111827;padding-left:12px;border-left:3px solid #e8590c;line-height:1.4">→ ' + rzpaEsc(q.answer_text) + '</div>'
            + '</div>';
        });
      } else {
        qaHtml = '<div style="color:#9ca3af;font-size:13px">Ingen svar gemt.</div>';
      }

      var scoresHtml = '';
      Object.keys(scores).forEach(function(k) {
        var v   = parseInt(scores[k]) || 0;
        var pct = Math.round((v / maxScore) * 100);
        var lbl = profileNames[k] || k;
        scoresHtml += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">'
          + '<div style="font-size:12px;color:#374151;width:140px;font-weight:600;flex-shrink:0">' + rzpaEsc(lbl) + '</div>'
          + '<div style="flex:1;background:#e5e7eb;border-radius:99px;height:8px">'
          +   '<div style="background:#e8590c;height:8px;border-radius:99px;width:' + pct + '%"></div>'
          + '</div>'
          + '<div style="font-size:12px;color:#6b7280;width:28px;text-align:right">' + v + '</div>'
          + '</div>';
      });

      var pdfUrl = rzpaAdminPostUrl + '?action=rzpa_quiz_download_pdf&submission_id=' + d.id + '&_wpnonce=' + rzpaPdfNonce;

      return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif">'

        // ── Left: contact + Q&A ──
        + '<div>'
        + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Kontaktoplysninger</div>'
        + '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;margin-bottom:20px">'
        +   '<div style="font-size:15px;font-weight:800;color:#111827;margin-bottom:4px">' + rzpaEsc(d.name) + '</div>'
        +   '<div style="font-size:13px;color:#4b5563">' + rzpaEsc(d.phone || '—') + '</div>'
        +   '<div style="font-size:13px;color:#4b5563">' + rzpaEsc(d.email || '—') + '</div>'
        + '</div>'
        + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Svar</div>'
        + qaHtml
        + '</div>'

        // ── Right: profile + scores + PDF ──
        + '<div>'
        + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Profil-resultat</div>'
        + '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;margin-bottom:20px">'
        +   '<div style="font-size:20px;font-weight:900;color:#111827;margin-bottom:6px">' + rzpaEsc((d.profile_icon||'') + ' ' + (d.profile_title||'—')) + '</div>'
        +   (d.secondary_title ? '<div style="font-size:12px;color:#6b7280">Sekundær: ' + rzpaEsc((d.secondary_icon||'') + ' ' + d.secondary_title) + '</div>' : '')
        + '</div>'
        + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:10px">Score-fordeling</div>'
        + '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;margin-bottom:20px">'
        + scoresHtml
        + '</div>'
        + '<a href="' + pdfUrl + '" target="_blank"'
        +  ' style="display:inline-flex;align-items:center;gap:8px;background:#111827;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-size:13px;font-weight:700">'
        +  '📄 Download PDF</a>'
        + '</div>'

        + '</div>';
    }

    function rzpaEsc(s) {
      if (!s) return '';
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    </script>
  </div>

  <!-- Pagination -->
  <?php if ( $total_pages > 1 ) : ?>
  <div style="margin-top:18px;display:flex;gap:6px;align-items:center;justify-content:center">
    <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
        $active_pg = $p === $current_pag;
        $purl      = add_query_arg( 'paged', $p );
    ?>
    <a href="<?php echo esc_url( $purl ); ?>"
       style="display:inline-block;padding:7px 13px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;<?php echo $active_pg ? 'background:#111827;color:#fff' : 'background:#f3f4f6;color:#374151'; ?>">
      <?php echo $p; ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>


  <!-- ══════════════════════════════════════════════════════════════════════
       TAB: SPØRGSMÅL
  ══════════════════════════════════════════════════════════════════════ -->
  <?php elseif ( $tab === 'questions' ) :
    $questions = RZPA_Quiz_Admin::get_all_questions();
    $saved     = ! empty( $_GET['saved'] );
    $deleted   = ! empty( $_GET['deleted'] );
  ?>

  <?php if ( $saved ) : ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#166534;font-size:13px;font-weight:600">✓ Spørgsmål gemt</div>
  <?php endif; ?>
  <?php if ( $deleted ) : ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#991b1b;font-size:13px;font-weight:600">🗑 Spørgsmål slettet</div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div style="font-size:14px;color:#6b7280"><?php echo count( $questions ); ?> spørgsmål i alt</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-quiz-edit-question' ) ); ?>"
       style="background:linear-gradient(135deg,#e8590c,#d6336c);color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px">
      + Nyt spørgsmål
    </a>
  </div>

  <!-- Question list -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ( $questions as $idx => $q ) :
        $is_active  = (bool) $q['is_active'];
        $ans_count  = count( $q['answers'] );
        $edit_url   = admin_url( 'admin.php?page=rzpa-quiz-edit-question&qid=' . $q['id'] );
    ?>
    <div style="background:#fff;border:1px solid <?php echo $is_active ? '#e5e7eb' : '#f3f4f6'; ?>;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.04);<?php echo $is_active ? '' : 'opacity:.65'; ?>">
      <div style="display:flex;align-items:flex-start;gap:14px">

        <!-- Sort number -->
        <div style="background:#f3f4f6;border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#374151;flex-shrink:0">
          <?php echo $idx + 1; ?>
        </div>

        <!-- Question body -->
        <div style="flex:1;min-width:0">
          <div style="font-size:15px;font-weight:700;color:#111827;margin-bottom:6px;line-height:1.4">
            <?php echo esc_html( $q['question_text'] ); ?>
          </div>
          <?php if ( $q['helper_text'] ) : ?>
          <div style="font-size:12px;color:#9ca3af;font-style:italic;margin-bottom:8px"><?php echo esc_html( $q['helper_text'] ); ?></div>
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php
            $is_active
              ? print '<span style="background:#d1fae5;color:#065f46;font-size:11px;font-weight:700;padding:3px 9px;border-radius:6px">● Aktiv</span>'
              : print '<span style="background:#f3f4f6;color:#9ca3af;font-size:11px;font-weight:700;padding:3px 9px;border-radius:6px">● Inaktiv</span>';
            ?>
            <span style="background:#f3f4f6;color:#6b7280;font-size:11px;font-weight:600;padding:3px 9px;border-radius:6px"><?php echo $ans_count; ?> svar</span>
          </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:8px;flex-shrink:0;align-items:center">

          <!-- Edit -->
          <a href="<?php echo esc_url( $edit_url ); ?>"
             style="background:#f3f4f6;color:#374151;text-decoration:none;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px">
            ✏️ Rediger
          </a>

          <!-- Toggle active -->
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
            <input type="hidden" name="action"              value="rzpa_quiz_toggle_question">
            <input type="hidden" name="qid"                 value="<?php echo $q['id']; ?>">
            <input type="hidden" name="is_active"           value="<?php echo $is_active ? 0 : 1; ?>">
            <?php wp_nonce_field( 'rzpa_quiz_toggle_question', 'rzpa_q_toggle_nonce' ); ?>
            <button type="submit"
                    style="background:<?php echo $is_active ? '#fef3c7' : '#d1fae5'; ?>;color:<?php echo $is_active ? '#92400e' : '#065f46'; ?>;border:none;cursor:pointer;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:700">
              <?php echo $is_active ? 'Deaktivér' : 'Aktivér'; ?>
            </button>
          </form>

          <!-- Delete -->
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"
                onsubmit="return confirm('Slet dette spørgsmål og alle dets svar? Handlingen kan ikke fortrydes.')">
            <input type="hidden" name="action"           value="rzpa_quiz_delete_question">
            <input type="hidden" name="qid"              value="<?php echo $q['id']; ?>">
            <?php wp_nonce_field( 'rzpa_quiz_delete_question', 'rzpa_q_del_nonce' ); ?>
            <button type="submit"
                    style="background:#fef2f2;color:#dc2626;border:none;cursor:pointer;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:700">
              🗑
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ( empty( $questions ) ) : ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:60px;text-align:center;color:#9ca3af">
      <div style="font-size:48px;margin-bottom:16px">📝</div>
      <p style="font-size:15px;margin:0;font-weight:600">Ingen spørgsmål endnu</p>
      <p style="font-size:13px;margin:10px 0 0">Klik "Nyt spørgsmål" for at komme i gang</p>
    </div>
    <?php endif; ?>
  </div>


  <!-- ══════════════════════════════════════════════════════════════════════
       TAB: E-MAIL SKABELONER
  ══════════════════════════════════════════════════════════════════════ -->
  <?php elseif ( $tab === 'email' ) :
    $cfg   = RZPA_Quiz_Admin::get_email_cfg();
    $saved = ! empty( $_GET['saved'] );
  ?>

  <?php if ( $saved ) : ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#166534;font-size:13px;font-weight:600">✓ E-mail indstillinger gemt</div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

    <!-- Settings form -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <h2 style="margin:0 0 20px;font-size:16px;font-weight:800;color:#111827">⚙️ Indstillinger</h2>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="rzpa_quiz_save_email_cfg">
        <?php wp_nonce_field( 'rzpa_quiz_save_email_cfg', 'rzpa_email_nonce' ); ?>

        <!-- Admin email -->
        <div style="margin-bottom:18px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin-bottom:6px">Modtager (HR / Admin)</label>
          <input type="email" name="admin_email" value="<?php echo esc_attr( $cfg['admin_email'] ); ?>"
                 style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;color:#111;outline:none;font-family:inherit"
                 onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'">
          <div style="font-size:11px;color:#9ca3af;margin-top:4px">Modtager admin-notifikation ved nye besvarelser</div>
        </div>

        <!-- CTA URL -->
        <div style="margin-bottom:18px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin-bottom:6px">Knap URL (bruger-mail)</label>
          <input type="text" name="cta_url" value="<?php echo esc_attr( $cfg['cta_url'] ); ?>"
                 style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;color:#111;outline:none;font-family:inherit"
                 onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'">
          <div style="font-size:11px;color:#9ca3af;margin-top:4px">Fx <code>/book-en-samtale</code> eller fuld URL</div>
        </div>

        <!-- CTA text -->
        <div style="margin-bottom:18px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin-bottom:6px">Knap tekst (bruger-mail)</label>
          <input type="text" name="cta_text" value="<?php echo esc_attr( $cfg['cta_text'] ); ?>"
                 style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;color:#111;outline:none;font-family:inherit"
                 onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'">
        </div>

        <!-- User subject override -->
        <div style="margin-bottom:24px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin-bottom:6px">Emne-override (bruger-mail) <span style="font-weight:400;text-transform:none">(valgfrit)</span></label>
          <input type="text" name="user_subject" value="<?php echo esc_attr( $cfg['user_subject'] ); ?>"
                 placeholder="Lades blank for standard: Din Rezponz profil: [profil]"
                 style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;color:#111;outline:none;font-family:inherit"
                 onfocus="this.style.borderColor='#e8590c'" onblur="this.style.borderColor='#e5e7eb'">
        </div>

        <button type="submit"
                style="background:linear-gradient(135deg,#e8590c,#d6336c);color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:14px;font-weight:700;cursor:pointer;width:100%">
          💾 Gem indstillinger
        </button>
      </form>
    </div>

    <!-- Preview / info panel -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <!-- Bruger-mail preview -->
      <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <h3 style="margin:0 0 16px;font-size:14px;font-weight:800;color:#111827">📧 Bruger-mail</h3>
        <div style="font-size:13px;color:#4b5563;line-height:1.6">
          Sendes automatisk til kandidaten når de afslutter quizzen og opgiver en e-mailadresse.<br><br>
          Indeholder:
          <ul style="margin:8px 0 0 18px;padding:0;line-height:1.8">
            <li>Profil-resultat + beskrivelse</li>
            <li>Styrker, trives-med og udviklingsområder</li>
            <li>Score-fordeling over de 4 profiler</li>
            <li>CTA-knap → <strong><?php echo esc_html( $cfg['cta_url'] ); ?></strong></li>
          </ul>
        </div>
      </div>

      <!-- Admin-mail preview -->
      <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <h3 style="margin:0 0 16px;font-size:14px;font-weight:800;color:#111827">🔔 Admin-notifikation</h3>
        <div style="font-size:13px;color:#4b5563;line-height:1.6">
          Sendes til <strong><?php echo esc_html( $cfg['admin_email'] ); ?></strong> ved hver ny besvarelse.<br><br>
          Indeholder:
          <ul style="margin:8px 0 0 18px;padding:0;line-height:1.8">
            <li>Navn, telefon og e-mail</li>
            <li>Vinder-profil og score-fordeling</li>
            <li>Link til admin-oversigten</li>
          </ul>
        </div>
      </div>

    </div>
  </div>

  <?php endif; ?>

</div>
