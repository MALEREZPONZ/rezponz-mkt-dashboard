<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var string $tab  set by RZPA_Quiz_Admin::page_main() */
$tab = $tab ?? 'submissions';
?>
<div class="wrap rzpa-quiz-wrap">

  <!-- ── Page header ──────────────────────────────────────────────────────── -->
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px">
    <div style="background:linear-gradient(135deg,#e8590c,#d6336c);width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 14px rgba(232,89,12,.35)">🎯</div>
    <div>
      <h1 style="margin:0;font-size:22px;font-weight:800;color:#f0f0f2;line-height:1.2">Profil-Quiz</h1>
      <div style="font-size:13px;color:#8888a0;margin-top:2px">Customer Success DNA · <code style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.07);padding:2px 7px;border-radius:6px;font-size:12px;color:#CCFF00">[rezponz_quiz]</code></div>
    </div>
  </div>

  <!-- ── Tab navigation ───────────────────────────────────────────────────── -->
  <div style="display:flex;gap:2px;margin-bottom:28px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:4px;width:fit-content;backdrop-filter:blur(10px)">
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
       style="padding:8px 18px;border-radius:8px;font-size:13px;font-weight:<?php echo $active ? '700' : '500'; ?>;text-decoration:none;transition:all .15s;<?php echo $active ? 'background:rgba(204,255,0,.12);color:#CCFF00;border:1px solid rgba(204,255,0,.25)' : 'color:#8888a0;background:transparent;border:1px solid transparent'; ?>">
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
  <div style="background:rgba(255,85,85,.06);color:#f87171;border:1px solid rgba(255,85,85,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px">
    ⚠️ <strong>PDF-fejl (seneste besvarelse):</strong><br><br><?php echo esc_html( $pdf_err ); ?>
  </div>
  <?php endif; ?>

  <?php
  $mail_err = get_transient( 'rzpa_quiz_mail_error' );
  if ( $mail_err ) :
      delete_transient( 'rzpa_quiz_mail_error' );
  ?>
  <div style="background:rgba(59,130,246,.06);color:#93c5fd;border:1px solid rgba(59,130,246,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px">
    📧 <strong>Mail-fejl (seneste besvarelse):</strong><br><br><?php echo esc_html( $mail_err ); ?><br><br>
    <span style="color:#8888a0">Konfigurér SMTP under <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-settings' ) ); ?>" style="color:#CCFF00">Indstillinger → SMTP</a> for pålidelig email-afsendelse.</span>
  </div>
  <?php endif; ?>

  <?php if ( ! empty( $_GET['sub_deleted'] ) ) : ?>
  <div style="background:rgba(204,255,0,.06);color:#CCFF00;border:1px solid rgba(204,255,0,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600">
    ✅ Besvarelsen er slettet.
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
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:32px">
    <div style="background:#161616;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:28px 32px;transition:border-color .2s">
      <div style="font-size:52px;font-weight:800;color:#ffffff;letter-spacing:-2px;line-height:1"><?php echo number_format( $total ); ?></div>
      <div style="font-size:13px;color:#888888;margin-top:10px;font-weight:500">Besvarelser i alt</div>
    </div>
    <?php foreach ( $dist as $d ) : ?>
    <div style="background:#161616;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:28px 32px;border-left:3px solid <?php echo esc_attr( $d['color'] ); ?>">
      <div style="font-size:52px;font-weight:800;color:#ffffff;line-height:1;letter-spacing:-2px"><?php echo (int) $d['total']; ?></div>
      <div style="font-size:16px;margin-top:6px"><?php echo esc_html( $d['icon_emoji'] ); ?></div>
      <div style="font-size:13px;color:#888888;margin-top:6px;font-weight:500"><?php echo esc_html( $d['title'] ); ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ( empty( $submissions ) ) : ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:60px;text-align:center;color:#8888a0;backdrop-filter:blur(16px)">
      <div style="font-size:48px;margin-bottom:16px">📭</div>
      <p style="font-size:16px;margin:0;font-weight:600;color:#f0f0f2">Ingen besvarelser endnu</p>
      <p style="font-size:13px;margin:8px 0 0">Del quizzen og vent på de første svar!</p>
    </div>
  <?php else : ?>

  <!-- Submissions table -->
  <div style="background:#161616;border:1px solid rgba(255,255,255,.08);border-radius:20px;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:#111111;border-bottom:1px solid rgba(255,255,255,.08)">
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">#</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">Navn</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">Telefon</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">Email</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">Profil</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">GDPR</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">Tidspunkt</th>
          <th style="padding:14px 16px;text-align:left;font-weight:500;color:#555555;font-size:12px">Status</th>
          <th style="padding:14px 16px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $submissions as $i => $s ) : ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);<?php echo $i % 2 !== 0 ? 'background:rgba(255,255,255,.015)' : ''; ?>">
          <td style="padding:12px 16px;color:#44445a;font-size:11px;font-weight:600"><?php echo $offset + $i + 1; ?></td>
          <td style="padding:12px 16px;font-weight:700;color:#f0f0f2"><?php echo esc_html( $s['name'] ); ?></td>
          <td style="padding:12px 16px;color:#8888a0"><?php echo esc_html( $s['phone'] ); ?></td>
          <td style="padding:12px 16px;color:#8888a0"><?php echo $s['email'] ? esc_html( $s['email'] ) : '<span style="color:#44445a">—</span>'; ?></td>
          <td style="padding:12px 16px">
            <?php if ( $s['profile_title'] ) : ?>
            <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo esc_attr( $s['profile_color'] ); ?>18;color:<?php echo esc_attr( $s['profile_color'] ); ?>;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid <?php echo esc_attr( $s['profile_color'] ); ?>30">
              <?php echo esc_html( $s['icon_emoji'] . ' ' . $s['profile_title'] ); ?>
            </span>
            <?php else : ?>
            <span style="color:#44445a">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px">
            <?php if ( $s['consent'] ) : ?>
              <span style="background:rgba(204,255,0,.1);color:#CCFF00;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid rgba(204,255,0,.22)">✓ Ja</span>
            <?php else : ?>
              <span style="background:rgba(255,85,85,.1);color:#ff5555;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid rgba(255,85,85,.22)">✗ Nej</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;color:#44445a;font-size:12px">
            <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $s['created_at'] ) ) ); ?>
          </td>
          <td style="padding:10px 14px;white-space:nowrap" id="rzpa-status-cell-<?php echo (int) $s['id']; ?>">
            <?php
            $cs = $s['candidate_status'] ?? null;
            $status_labels = [
                'interessant'     => [ 'label' => '⭐ Interessant',      'color' => '#CCFF00', 'bg' => 'rgba(204,255,0,.08)',  'border' => 'rgba(204,255,0,.25)' ],
                'maaske'          => [ 'label' => '🤔 Måske',            'color' => '#ffaa33', 'bg' => 'rgba(255,170,51,.08)', 'border' => 'rgba(255,170,51,.25)' ],
                'ikke_interessant'=> [ 'label' => '✗ Ikke interessant',  'color' => '#888888', 'bg' => 'rgba(255,255,255,.04)','border' => 'rgba(255,255,255,.1)' ],
            ];
            ?>
            <div class="rzpa-candidate-status" data-id="<?php echo (int) $s['id']; ?>" data-status="<?php echo esc_attr( $cs ?? '' ); ?>" data-name="<?php echo esc_attr( $s['name'] ); ?>" data-email="<?php echo esc_attr( $s['email'] ?? '' ); ?>">
              <?php if ( $cs && isset( $status_labels[ $cs ] ) ) : $sl = $status_labels[ $cs ]; ?>
                <button class="rzpa-status-pill rzpa-status-set" style="color:<?php echo $sl['color']; ?>;background:<?php echo $sl['bg']; ?>;border-color:<?php echo $sl['border']; ?>">
                  <?php echo $sl['label']; ?> ▾
                </button>
              <?php else : ?>
                <button class="rzpa-status-pill rzpa-status-unset">Sæt status ▾</button>
              <?php endif; ?>
              <?php if ( $cs === 'interessant' ) : ?>
                <button class="rzpa-send-mail-btn" data-id="<?php echo (int) $s['id']; ?>"
                  style="margin-top:4px;display:block;font-size:11px;font-weight:600;padding:4px 12px;border-radius:999px;border:1px solid rgba(204,255,0,.3);background:rgba(204,255,0,.06);color:#CCFF00;cursor:pointer;font-family:inherit;transition:all .15s">
                  <?php echo $s['mail_sent_at'] ? '✓ Mail sendt' : '✉ Send invitation'; ?>
                </button>
              <?php endif; ?>
            </div>
          </td>
          <td style="padding:10px 14px;white-space:nowrap">
            <button onclick="rzpaToggleDetail(<?php echo (int) $s['id']; ?>)"
                    id="rzpa-btn-<?php echo (int) $s['id']; ?>"
                    style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);cursor:pointer;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;color:#f0f0f2;white-space:nowrap;margin-right:6px;transition:all .2s;font-family:inherit">
              👁 Se svar
            </button>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                  style="display:inline"
                  onsubmit="return confirm('Slet denne besvarelse permanent?')">
              <?php wp_nonce_field( 'rzpa_quiz_delete_submission', 'rzpa_sub_del_nonce' ); ?>
              <input type="hidden" name="action"        value="rzpa_quiz_delete_submission">
              <input type="hidden" name="submission_id" value="<?php echo (int) $s['id']; ?>">
              <input type="hidden" name="paged"         value="<?php echo (int) ( $_GET['paged'] ?? 1 ); ?>">
              <button type="submit"
                      style="background:rgba(255,85,85,.08);border:1px solid rgba(255,85,85,.25);cursor:pointer;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;color:#f87171;white-space:nowrap;font-family:inherit;transition:all .2s">
                🗑 Slet
              </button>
            </form>
          </td>
        </tr>
        <tr id="rzpa-detail-<?php echo (int) $s['id']; ?>" style="display:none">
          <td colspan="9" style="padding:0;border-bottom:2px solid rgba(204,255,0,.12)">
            <div id="rzpa-detail-body-<?php echo (int) $s['id']; ?>" style="padding:24px 28px;background:#0d0d11">
              <div style="color:#8888a0;font-size:13px">Henter data…</div>
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
      .catch(function(){ body.innerHTML = '<div style="color:#f87171;font-size:13px">Fejl ved hentning af data.</div>'; });
    }

    function rzpaBuildDetail(d) {
      var profileNames = { empatisk: 'Den Empatiske Lytter', energisk: 'Energibomben', analytisk: 'Problemknuseren', social: 'Netværksmesteren' };
      var scores = d.scores || {};
      var maxScore = Math.max.apply(null, Object.values(scores).map(Number)) || 1;

      var qaHtml = '';
      if (d.qa && d.qa.length) {
        d.qa.forEach(function(q, i) {
          qaHtml += '<div style="margin-bottom:14px">'
            + '<div style="font-size:12px;color:#8888a0;font-weight:600;margin-bottom:4px">' + (i+1) + '. ' + rzpaEsc(q.question_text) + '</div>'
            + '<div style="font-size:13px;color:#f0f0f2;padding-left:12px;border-left:3px solid #CCFF00;line-height:1.4">→ ' + rzpaEsc(q.answer_text) + '</div>'
            + '</div>';
        });
      } else {
        qaHtml = '<div style="color:#8888a0;font-size:13px">Ingen svar gemt.</div>';
      }

      var scoresHtml = '';
      Object.keys(scores).forEach(function(k) {
        var v   = parseInt(scores[k]) || 0;
        var pct = Math.round((v / maxScore) * 100);
        var lbl = profileNames[k] || k;
        scoresHtml += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">'
          + '<div style="font-size:12px;color:#8888a0;width:140px;font-weight:600;flex-shrink:0">' + rzpaEsc(lbl) + '</div>'
          + '<div style="flex:1;background:rgba(255,255,255,.06);border-radius:999px;height:6px">'
          +   '<div style="background:#CCFF00;height:6px;border-radius:999px;width:' + pct + '%;box-shadow:0 0 8px rgba(204,255,0,.4)"></div>'
          + '</div>'
          + '<div style="font-size:12px;color:#8888a0;width:28px;text-align:right">' + v + '</div>'
          + '</div>';
      });

      var pdfUrl = rzpaAdminPostUrl + '?action=rzpa_quiz_download_pdf&submission_id=' + d.id + '&_wpnonce=' + rzpaPdfNonce;

      return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start;font-family:\'Inter\',-apple-system,\'Segoe UI\',sans-serif">'

        // ── Left: contact + Q&A ──
        + '<div>'
        + '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#44445a;margin-bottom:10px">Kontaktoplysninger</div>'
        + '<div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:14px 16px;margin-bottom:20px">'
        +   '<div style="font-size:15px;font-weight:800;color:#f0f0f2;margin-bottom:4px">' + rzpaEsc(d.name) + '</div>'
        +   '<div style="font-size:13px;color:#8888a0">' + rzpaEsc(d.phone || '—') + '</div>'
        +   '<div style="font-size:13px;color:#8888a0">' + rzpaEsc(d.email || '—') + '</div>'
        + '</div>'
        + '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#44445a;margin-bottom:10px">Svar</div>'
        + qaHtml
        + '</div>'

        // ── Right: profile + scores + PDF ──
        + '<div>'
        + '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#44445a;margin-bottom:10px">Profil-resultat</div>'
        + '<div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:16px 18px;margin-bottom:20px">'
        +   '<div style="font-size:20px;font-weight:900;color:#f0f0f2;margin-bottom:6px">' + rzpaEsc((d.profile_icon||'') + ' ' + (d.profile_title||'—')) + '</div>'
        +   (d.secondary_title ? '<div style="font-size:12px;color:#8888a0">Sekundær: ' + rzpaEsc((d.secondary_icon||'') + ' ' + d.secondary_title) + '</div>' : '')
        + '</div>'
        + '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#44445a;margin-bottom:10px">Score-fordeling</div>'
        + '<div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:16px 18px;margin-bottom:20px">'
        + scoresHtml
        + '</div>'
        + '<a href="' + pdfUrl + '" target="_blank"'
        +  ' style="display:inline-flex;align-items:center;gap:8px;background:#CCFF00;color:#000;text-decoration:none;padding:11px 22px;border-radius:999px;font-size:13px;font-weight:700;box-shadow:0 0 20px rgba(204,255,0,.2)">'
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
       style="display:inline-block;padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;transition:all .18s;<?php echo $active_pg ? 'background:#CCFF00;color:#000;box-shadow:0 0 16px rgba(204,255,0,.25)' : 'background:rgba(255,255,255,.05);color:#8888a0;border:1px solid rgba(255,255,255,.07)'; ?>">
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
  <div style="background:rgba(204,255,0,.06);border:1px solid rgba(204,255,0,.22);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#CCFF00;font-size:13px;font-weight:600">✓ Spørgsmål gemt</div>
  <?php endif; ?>
  <?php if ( $deleted ) : ?>
  <div style="background:rgba(255,85,85,.06);border:1px solid rgba(255,85,85,.22);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#f87171;font-size:13px;font-weight:600">🗑 Spørgsmål slettet</div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div style="font-size:14px;color:#8888a0"><?php echo count( $questions ); ?> spørgsmål i alt</div>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzpa-quiz-edit-question' ) ); ?>"
       style="background:#CCFF00;color:#000;text-decoration:none;padding:10px 22px;border-radius:999px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;box-shadow:0 0 20px rgba(204,255,0,.2);transition:all .2s">
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
    <div style="background:rgba(255,255,255,.03);border:1px solid <?php echo $is_active ? 'rgba(255,255,255,.07)' : 'rgba(255,255,255,.04)'; ?>;border-radius:14px;padding:18px 20px;backdrop-filter:blur(12px);<?php echo $is_active ? '' : 'opacity:.55'; ?>;transition:border-color .2s">
      <div style="display:flex;align-items:flex-start;gap:14px">

        <!-- Sort number -->
        <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.07);border-radius:10px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#f0f0f2;flex-shrink:0">
          <?php echo $idx + 1; ?>
        </div>

        <!-- Question body -->
        <div style="flex:1;min-width:0">
          <div style="font-size:15px;font-weight:700;color:#f0f0f2;margin-bottom:6px;line-height:1.4">
            <?php echo esc_html( $q['question_text'] ); ?>
          </div>
          <?php if ( $q['helper_text'] ) : ?>
          <div style="font-size:12px;color:#8888a0;font-style:italic;margin-bottom:8px"><?php echo esc_html( $q['helper_text'] ); ?></div>
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php
            $is_active
              ? print '<span style="background:rgba(204,255,0,.1);color:#CCFF00;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid rgba(204,255,0,.22)">● Aktiv</span>'
              : print '<span style="background:rgba(255,255,255,.04);color:#8888a0;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.07)">● Inaktiv</span>';
            ?>
            <span style="background:rgba(255,255,255,.04);color:#8888a0;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.07)"><?php echo $ans_count; ?> svar</span>
          </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:8px;flex-shrink:0;align-items:center">

          <!-- Edit -->
          <a href="<?php echo esc_url( $edit_url ); ?>"
             style="background:rgba(255,255,255,.05);color:#f0f0f2;text-decoration:none;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px;border:1px solid rgba(255,255,255,.12);transition:all .2s">
            ✏️ Rediger
          </a>

          <!-- Toggle active -->
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
            <input type="hidden" name="action"              value="rzpa_quiz_toggle_question">
            <input type="hidden" name="qid"                 value="<?php echo $q['id']; ?>">
            <input type="hidden" name="is_active"           value="<?php echo $is_active ? 0 : 1; ?>">
            <?php wp_nonce_field( 'rzpa_quiz_toggle_question', 'rzpa_q_toggle_nonce' ); ?>
            <button type="submit"
                    style="background:<?php echo $is_active ? 'rgba(255,170,51,.08)' : 'rgba(204,255,0,.08)'; ?>;color:<?php echo $is_active ? '#ffaa33' : '#CCFF00'; ?>;border:1px solid <?php echo $is_active ? 'rgba(255,170,51,.25)' : 'rgba(204,255,0,.25)'; ?>;cursor:pointer;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:700;font-family:inherit;transition:all .2s">
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
                    style="background:rgba(255,85,85,.08);color:#f87171;border:1px solid rgba(255,85,85,.22);cursor:pointer;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:700;font-family:inherit;transition:all .2s">
              🗑
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ( empty( $questions ) ) : ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:60px;text-align:center;color:#8888a0;backdrop-filter:blur(16px)">
      <div style="font-size:48px;margin-bottom:16px">📝</div>
      <p style="font-size:15px;margin:0;font-weight:600;color:#f0f0f2">Ingen spørgsmål endnu</p>
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
  <div style="background:rgba(204,255,0,.06);border:1px solid rgba(204,255,0,.22);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#CCFF00;font-size:13px;font-weight:600">✓ E-mail indstillinger gemt</div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

    <!-- Settings form -->
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:28px;backdrop-filter:blur(16px)">
      <h2 style="margin:0 0 20px;font-size:16px;font-weight:800;color:#f0f0f2">⚙️ Indstillinger</h2>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="rzpa_quiz_save_email_cfg">
        <?php wp_nonce_field( 'rzpa_quiz_save_email_cfg', 'rzpa_email_nonce' ); ?>

        <!-- Admin email -->
        <div style="margin-bottom:18px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#8888a0;margin-bottom:6px">Modtager (HR / Admin)</label>
          <input type="email" name="admin_email" value="<?php echo esc_attr( $cfg['admin_email'] ); ?>"
                 style="width:100%;background:#111116;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 14px;font-size:14px;color:#f0f0f2;outline:none;font-family:inherit;transition:border-color .18s"
                 onfocus="this.style.borderColor='#CCFF00';this.style.boxShadow='0 0 0 2px rgba(204,255,0,.08)'" onblur="this.style.borderColor='rgba(255,255,255,.07)';this.style.boxShadow='none'">
          <div style="font-size:11px;color:#8888a0;margin-top:4px">Modtager admin-notifikation ved nye besvarelser</div>
        </div>

        <!-- CTA URL -->
        <div style="margin-bottom:18px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#8888a0;margin-bottom:6px">Knap URL (bruger-mail)</label>
          <input type="text" name="cta_url" value="<?php echo esc_attr( $cfg['cta_url'] ); ?>"
                 style="width:100%;background:#111116;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 14px;font-size:14px;color:#f0f0f2;outline:none;font-family:inherit;transition:border-color .18s"
                 onfocus="this.style.borderColor='#CCFF00';this.style.boxShadow='0 0 0 2px rgba(204,255,0,.08)'" onblur="this.style.borderColor='rgba(255,255,255,.07)';this.style.boxShadow='none'">
          <div style="font-size:11px;color:#8888a0;margin-top:4px">Fx <code style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.07);padding:1px 6px;border-radius:5px;color:#CCFF00">/book-en-samtale</code> eller fuld URL</div>
        </div>

        <!-- CTA text -->
        <div style="margin-bottom:18px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#8888a0;margin-bottom:6px">Knap tekst (bruger-mail)</label>
          <input type="text" name="cta_text" value="<?php echo esc_attr( $cfg['cta_text'] ); ?>"
                 style="width:100%;background:#111116;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 14px;font-size:14px;color:#f0f0f2;outline:none;font-family:inherit;transition:border-color .18s"
                 onfocus="this.style.borderColor='#CCFF00';this.style.boxShadow='0 0 0 2px rgba(204,255,0,.08)'" onblur="this.style.borderColor='rgba(255,255,255,.07)';this.style.boxShadow='none'">
        </div>

        <!-- User subject override -->
        <div style="margin-bottom:24px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#8888a0;margin-bottom:6px">Emne-override (bruger-mail) <span style="font-weight:400;text-transform:none;color:#44445a">(valgfrit)</span></label>
          <input type="text" name="user_subject" value="<?php echo esc_attr( $cfg['user_subject'] ); ?>"
                 placeholder="Lades blank for standard: Din Rezponz profil: [profil]"
                 style="width:100%;background:#111116;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 14px;font-size:14px;color:#f0f0f2;outline:none;font-family:inherit;transition:border-color .18s"
                 onfocus="this.style.borderColor='#CCFF00';this.style.boxShadow='0 0 0 2px rgba(204,255,0,.08)'" onblur="this.style.borderColor='rgba(255,255,255,.07)';this.style.boxShadow='none'">
        </div>

        <button type="submit"
                style="background:#CCFF00;color:#000;border:none;border-radius:999px;padding:12px 28px;font-size:14px;font-weight:700;cursor:pointer;width:100%;box-shadow:0 0 24px rgba(204,255,0,.2);transition:all .2s;font-family:inherit">
          💾 Gem indstillinger
        </button>
      </form>
    </div>

    <!-- Preview / info panel -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <!-- Bruger-mail preview -->
      <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:24px;backdrop-filter:blur(16px)">
        <h3 style="margin:0 0 16px;font-size:14px;font-weight:800;color:#f0f0f2">📧 Bruger-mail</h3>
        <div style="font-size:13px;color:#8888a0;line-height:1.6">
          Sendes automatisk til kandidaten når de afslutter quizzen og opgiver en e-mailadresse.<br><br>
          Indeholder:
          <ul style="margin:8px 0 0 18px;padding:0;line-height:1.8;color:#8888a0">
            <li>Profil-resultat + beskrivelse</li>
            <li>Styrker, trives-med og udviklingsområder</li>
            <li>Score-fordeling over de 4 profiler</li>
            <li>CTA-knap → <strong style="color:#f0f0f2"><?php echo esc_html( $cfg['cta_url'] ); ?></strong></li>
          </ul>
        </div>
      </div>

      <!-- Admin-mail preview -->
      <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:24px;backdrop-filter:blur(16px)">
        <h3 style="margin:0 0 16px;font-size:14px;font-weight:800;color:#f0f0f2">🔔 Admin-notifikation</h3>
        <div style="font-size:13px;color:#8888a0;line-height:1.6">
          Sendes til <strong style="color:#f0f0f2"><?php echo esc_html( $cfg['admin_email'] ); ?></strong> ved hver ny besvarelse.<br><br>
          Indeholder:
          <ul style="margin:8px 0 0 18px;padding:0;line-height:1.8;color:#8888a0">
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

<!-- ── Kandidat-status dropdown ────────────────────────────────── -->
<div id="rzpa-status-dropdown" style="display:none;position:fixed;z-index:9999;background:#1a1a1a;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:6px;min-width:180px;box-shadow:0 8px 32px rgba(0,0,0,.6)">
  <button class="rzpa-sd-item" data-val="">⊘ Ingen status</button>
  <button class="rzpa-sd-item" data-val="interessant">⭐ Interessant</button>
  <button class="rzpa-sd-item" data-val="maaske">🤔 Måske</button>
  <button class="rzpa-sd-item" data-val="ikke_interessant">✗ Ikke interessant</button>
</div>

<!-- ── Mail modal ───────────────────────────────────────────────── -->
<div id="rzpa-mail-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center">
  <div style="background:#161616;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:32px;width:600px;max-width:95vw;max-height:90vh;overflow-y:auto;position:relative">
    <h2 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#fff">✉ Send invitation</h2>
    <div id="rzpa-mail-to-label" style="font-size:13px;color:#888;margin-bottom:24px"></div>

    <label style="display:block;font-size:12px;color:#888;margin-bottom:6px">Emne</label>
    <input id="rzpa-mail-subject" type="text" style="width:100%;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:10px 14px;color:#fff;font-size:14px;font-family:inherit;margin-bottom:18px;box-sizing:border-box" />

    <label style="display:block;font-size:12px;color:#888;margin-bottom:6px">Besked</label>
    <textarea id="rzpa-mail-body" rows="12" style="width:100%;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 14px;color:#fff;font-size:14px;font-family:inherit;resize:vertical;box-sizing:border-box;line-height:1.6"></textarea>

    <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
      <button id="rzpa-mail-cancel" style="padding:10px 22px;border-radius:999px;border:1px solid rgba(255,255,255,.15);background:transparent;color:#888;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">Annuller</button>
      <button id="rzpa-mail-send" style="padding:10px 28px;border-radius:999px;border:none;background:#CCFF00;color:#000;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">✉ Send</button>
    </div>
    <div id="rzpa-mail-feedback" style="margin-top:12px;font-size:13px;display:none"></div>
  </div>
</div>

<style>
.rzpa-status-pill {
  font-size:11px;font-weight:600;padding:4px 12px;border-radius:999px;
  border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);
  color:#666;cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap;
}
.rzpa-status-pill:hover { border-color:rgba(255,255,255,.2);color:#aaa; }
.rzpa-sd-item {
  display:block;width:100%;text-align:left;padding:8px 14px;font-size:13px;
  background:none;border:none;color:#ccc;cursor:pointer;border-radius:8px;
  font-family:inherit;transition:background .12s;
}
.rzpa-sd-item:hover { background:rgba(255,255,255,.07); }
</style>

<script>
(function() {
  var nonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
  var dropdown = document.getElementById('rzpa-status-dropdown');
  var mailModal = document.getElementById('rzpa-mail-modal');
  var currentStatusTarget = null;
  var currentMailId = null;

  var defaultSubject = 'Vi vil gerne invitere dig til Rezponz 👋';
  var defaultBody = function(name) {
    return 'Hej ' + name + ',\n\n'
      + 'Tak fordi du har udfyldt vores profil-quiz! Vi kunne rigtig godt tænke os at lære dig bedre at kende.\n\n'
      + 'Vi vil gerne invitere dig til at komme forbi vores kontor i Aalborg, så du kan se, hvad vi laver, '
      + 'møde teamet og stille alle de spørgsmål, du måtte have. Der er ingen forpligtelser — det er blot en '
      + 'uformel snak over en kop kaffe ☕\n\n'
      + 'Har du lyst, så svar blot på denne mail med et tidspunkt, der passer dig — eller ring til os.\n\n'
      + 'Vi glæder os til at høre fra dig!\n\n'
      + 'Med venlig hilsen\n'
      + 'Lie Svenningsen\n'
      + 'Rezponz · lie@rezponz.dk';
  };

  // ── Status dropdown ──────────────────────────────────────────────
  document.querySelectorAll('.rzpa-candidate-status .rzpa-status-pill').forEach(function(pill) {
    pill.addEventListener('click', function(e) {
      e.stopPropagation();
      currentStatusTarget = pill.closest('.rzpa-candidate-status');
      var rect = pill.getBoundingClientRect();
      dropdown.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
      dropdown.style.left = rect.left + 'px';
      dropdown.style.display = 'block';
    });
  });

  document.querySelectorAll('.rzpa-sd-item').forEach(function(item) {
    item.addEventListener('click', function() {
      if (!currentStatusTarget) return;
      var id  = parseInt(currentStatusTarget.dataset.id);
      var val = item.dataset.val;
      dropdown.style.display = 'none';
      fetch('/wp-json/rzpa/v1/quiz/submission/' + id + '/status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({ status: val || null })
      }).then(function(r) { return r.json(); }).then(function() {
        // Reload the page to reflect new status
        window.location.reload();
      });
    });
  });

  document.addEventListener('click', function(e) {
    if (!dropdown.contains(e.target)) dropdown.style.display = 'none';
  });

  // ── Send mail ────────────────────────────────────────────────────
  document.querySelectorAll('.rzpa-send-mail-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var cell = btn.closest('[id^="rzpa-status-cell-"]');
      var wrap = cell ? cell.querySelector('.rzpa-candidate-status') : null;
      if (!wrap) return;
      currentMailId   = parseInt(wrap.dataset.id);
      var name        = wrap.dataset.name;
      var email       = wrap.dataset.email;
      document.getElementById('rzpa-mail-to-label').textContent = 'Til: ' + name + ' <' + email + '>';
      document.getElementById('rzpa-mail-subject').value = defaultSubject;
      document.getElementById('rzpa-mail-body').value    = defaultBody(name);
      document.getElementById('rzpa-mail-feedback').style.display = 'none';
      document.getElementById('rzpa-mail-send').textContent = '✉ Send';
      document.getElementById('rzpa-mail-send').disabled = false;
      mailModal.style.display = 'flex';
    });
  });

  document.getElementById('rzpa-mail-cancel').addEventListener('click', function() {
    mailModal.style.display = 'none';
  });

  document.getElementById('rzpa-mail-send').addEventListener('click', function() {
    var sendBtn  = this;
    var cell     = document.getElementById('rzpa-status-cell-' + currentMailId);
    var wrap     = cell ? cell.querySelector('.rzpa-candidate-status') : null;
    var email    = wrap ? wrap.dataset.email : '';
    var subject  = document.getElementById('rzpa-mail-subject').value.trim();
    var body     = document.getElementById('rzpa-mail-body').value.trim();
    var feedback = document.getElementById('rzpa-mail-feedback');

    if (!subject || !body) { feedback.style.display='block'; feedback.style.color='#ff5555'; feedback.textContent='Udfyld emne og besked.'; return; }

    sendBtn.textContent = '⏳ Sender…';
    sendBtn.disabled = true;
    fetch('/wp-json/rzpa/v1/quiz/submission/' + currentMailId + '/send-mail', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({ to: email, subject: subject, body: body })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.sent) {
        feedback.style.display = 'block';
        feedback.style.color = '#CCFF00';
        feedback.textContent = '✓ Mail sendt til ' + email;
        sendBtn.textContent = '✓ Sendt';
        setTimeout(function() { mailModal.style.display = 'none'; window.location.reload(); }, 1500);
      } else {
        feedback.style.display = 'block';
        feedback.style.color = '#ff5555';
        feedback.textContent = d.error || 'Afsendelse fejlede – tjek SMTP-indstillinger.';
        sendBtn.textContent = '✉ Send';
        sendBtn.disabled = false;
      }
    })
    .catch(function(err) {
      feedback.style.display = 'block';
      feedback.style.color = '#ff5555';
      feedback.textContent = 'Netværksfejl: ' + err.message;
      sendBtn.textContent = '✉ Send';
      sendBtn.disabled = false;
    });
  });
}());
</script>
