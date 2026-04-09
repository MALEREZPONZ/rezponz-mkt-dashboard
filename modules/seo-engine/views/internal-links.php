<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

global $wpdb;
$rules_table = RZPA_SEO_DB::get_table( 'link_rules' );
$rules       = $wpdb->get_results( "SELECT * FROM {$rules_table} ORDER BY priority ASC, id DESC", ARRAY_A ) ?: [];

$log_total = 0;
$link_logs = RZPA_SEO_DB::get_logs( 'linking', null, 20, 0, $log_total );
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">🔗 Intern Linking</h1>
      <p class="rzpa-page-sub">Administrer automatiske interne linkeregler</p>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Regel gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:3fr 2fr;gap:24px;align-items:start;">

    <!-- LEFT: Link Regler -->
    <div>
      <div class="rzpa-card" style="margin-bottom:20px;">
        <div class="rzpa-card-header">
          <h3 class="rzpa-card-title">Link Regler</h3>
          <button type="button" id="show-rule-form" class="rzpa-btn rzpa-btn-primary" style="font-size:12px;">➕ Ny Regel</button>
        </div>

        <!-- New rule form (hidden) -->
        <div id="new-rule-form" style="display:none;border-top:1px solid var(--border);padding:20px;background:var(--bg-200);">
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
            <input type="hidden" name="action" value="rzpa_seo_save_link_rule">
            <input type="hidden" name="id" id="edit-rule-id" value="0">

            <div style="display:flex;flex-direction:column;gap:14px;">
              <div>
                <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Regelnavn <span style="color:var(--neon);">*</span></label>
                <input type="text" name="rule_name" required id="rule-name"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                  <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Kildetype</label>
                  <select name="source_type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                    <option value="any">Alle</option>
                    <option value="pseo">pSEO</option>
                    <option value="blog">Blog</option>
                    <option value="page">Side</option>
                  </select>
                </div>
                <div>
                  <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Måltype</label>
                  <select name="target_type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                    <option value="any">Alle</option>
                    <option value="pseo">pSEO</option>
                    <option value="blog">Blog</option>
                    <option value="page">Side</option>
                  </select>
                </div>
              </div>

              <div>
                <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Match Type</label>
                <select name="match_type" id="match-type" style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                  <option value="keyword">Søgeord</option>
                  <option value="geo">Geo (by/region)</option>
                  <option value="category">Kategori</option>
                  <option value="intent">Intent</option>
                </select>
              </div>

              <div id="keyword-fields">
                <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Søgeord <span style="font-size:11px;">(kommasepareret)</span></label>
                <input type="text" name="keywords" id="rule-keywords"
                       style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
              </div>

              <div id="geo-fields" style="display:none;">
                <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Geo Felter</label>
                <div style="display:flex;gap:16px;">
                  <?php foreach ( [ 'city' => 'By', 'region' => 'Region', 'area' => 'Område' ] as $gv => $gl ) : ?>
                    <label style="display:flex;align-items:center;gap:5px;font-size:13px;">
                      <input type="checkbox" name="geo_fields[]" value="<?php echo esc_attr( $gv ); ?>">
                      <?php echo esc_html( $gl ); ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                  <label style="display:block;margin-bottom:5px;font-size:13px;color:var(--text-muted);">Prioritet (1–100)</label>
                  <input type="number" name="priority" value="10" min="1" max="100"
                         style="width:100%;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
                </div>
                <div style="display:flex;align-items:flex-end;padding-bottom:4px;">
                  <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                    <input type="checkbox" name="is_active" value="1" checked> Aktiv
                  </label>
                </div>
              </div>

              <div style="display:flex;gap:8px;">
                <button type="submit" class="rzpa-btn rzpa-btn-primary">Gem Regel</button>
                <button type="button" id="cancel-rule-form" class="rzpa-btn">Annuller</button>
              </div>
            </div>
          </form>
        </div>

        <?php if ( empty( $rules ) ) : ?>
          <div class="rzpa-empty" style="padding:20px;">Ingen regler endnu. Opret din første linkreg.</div>
        <?php else : ?>
          <table class="rzpa-table">
            <thead>
              <tr>
                <th>Navn</th>
                <th>Kilde</th>
                <th>Mål</th>
                <th>Match</th>
                <th>Prio.</th>
                <th>Aktiv</th>
                <th>Handlinger</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $rules as $rule ) :
                $ml  = json_decode( $rule['match_logic'], true ) ?: [];
                $match_summary = $ml['match_type'] ?? '—';
                if ( ! empty( $ml['keywords'] ) ) $match_summary .= ': ' . wp_trim_words( $ml['keywords'], 4 );
              ?>
                <tr>
                  <td><strong><?php echo esc_html( $rule['rule_name'] ); ?></strong></td>
                  <td style="font-size:12px;"><?php echo esc_html( $rule['source_type'] ); ?></td>
                  <td style="font-size:12px;"><?php echo esc_html( $rule['target_type'] ); ?></td>
                  <td style="font-size:12px;"><?php echo esc_html( $match_summary ); ?></td>
                  <td style="font-size:12px;"><?php echo absint( $rule['priority'] ); ?></td>
                  <td>
                    <span class="badge <?php echo $rule['is_active'] ? 'badge-active' : 'badge-paused'; ?>">
                      <?php echo $rule['is_active'] ? 'Ja' : 'Nej'; ?>
                    </span>
                  </td>
                  <td>
                    <button type="button" class="rzpa-btn edit-rule" style="font-size:11px;"
                            data-rule="<?php echo esc_attr( wp_json_encode( $rule ) ); ?>">Rediger</button>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                          style="display:inline;" onsubmit="return confirm('Slet regel?');">
                      <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                      <input type="hidden" name="action" value="rzpa_seo_delete_link_rule">
                      <input type="hidden" name="id" value="<?php echo absint( $rule['id'] ); ?>">
                      <button type="submit" class="rzpa-btn" style="font-size:11px;color:var(--text-muted);">Slet</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Recent Logs -->
      <div class="rzpa-card">
        <div class="rzpa-card-header">
          <h3 class="rzpa-card-title">Seneste Linking Logs</h3>
        </div>
        <?php if ( empty( $link_logs ) ) : ?>
          <div class="rzpa-empty" style="padding:16px;">Ingen linking logs endnu.</div>
        <?php else : ?>
          <table class="rzpa-table" style="font-size:12px;">
            <thead><tr><th>Tid</th><th>Handling</th><th>Besked</th></tr></thead>
            <tbody>
            <?php foreach ( $link_logs as $log ) : ?>
              <tr>
                <td style="white-space:nowrap;color:var(--text-muted);"><?php echo esc_html( wp_date( 'd/m H:i', strtotime( $log['created_at'] ) ) ); ?></td>
                <td><?php echo esc_html( $log['action_type'] ); ?></td>
                <td><?php echo esc_html( wp_trim_words( $log['message'], 10 ) ); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: Link Forslag -->
    <div class="rzpa-card">
      <div class="rzpa-card-header"><h3 class="rzpa-card-title">Link Forslag</h3></div>
      <div style="padding:20px;">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Find relevante interne links for et specifikt indlæg.</p>

        <div style="display:flex;gap:8px;margin-bottom:16px;">
          <input type="text" id="suggestion-post-id" placeholder="Indlægs-ID..."
                 style="flex:1;background:var(--bg-300);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:var(--radius);font-size:13px;">
          <button type="button" id="find-suggestions" class="rzpa-btn rzpa-btn-primary">Find forslag</button>
        </div>

        <div id="suggestions-result" style="display:none;">
          <div id="suggestions-list" style="display:flex;flex-direction:column;gap:12px;"></div>
        </div>

        <div id="suggestions-loading" style="display:none;text-align:center;padding:20px;color:var(--text-muted);">
          Søger efter forslag...
        </div>

        <div id="suggestions-empty" style="display:none;text-align:center;padding:20px;color:var(--text-muted);">
          Ingen forslag fundet for dette indlæg.
        </div>
      </div>
    </div>

  </div>

</div>

<script>
(function($){
  // Show/hide rule form
  $('#show-rule-form').on('click', function(){
    $('#new-rule-form').slideDown();
    $('#edit-rule-id').val('0');
    $('[name="rule_name"]').val('');
    $('[name="keywords"]').val('');
  });
  $('#cancel-rule-form').on('click', function(){
    $('#new-rule-form').slideUp();
  });

  // Match type toggle
  $('#match-type').on('change', function(){
    if ($(this).val() === 'geo') {
      $('#geo-fields').show();
      $('#keyword-fields').hide();
    } else if ($(this).val() === 'keyword') {
      $('#keyword-fields').show();
      $('#geo-fields').hide();
    } else {
      $('#keyword-fields').hide();
      $('#geo-fields').hide();
    }
  });

  // Edit existing rule
  $(document).on('click', '.edit-rule', function(){
    var rule = JSON.parse($(this).data('rule'));
    var ml   = JSON.parse(rule.match_logic || '{}');
    $('#edit-rule-id').val(rule.id);
    $('[name="rule_name"]').val(rule.rule_name);
    $('[name="source_type"]').val(rule.source_type);
    $('[name="target_type"]').val(rule.target_type);
    $('[name="match_type"]').val(ml.match_type || 'keyword').trigger('change');
    $('[name="keywords"]').val(ml.keywords || '');
    $('[name="priority"]').val(rule.priority);
    $('[name="is_active"]').prop('checked', rule.is_active == 1);
    $('#new-rule-form').slideDown();
    $('html,body').animate({scrollTop: $('#new-rule-form').offset().top - 100}, 300);
  });

  // Link Suggestions
  $('#find-suggestions').on('click', function(){
    var postId = $('#suggestion-post-id').val();
    if (!postId) { alert('Angiv et indlægs-ID'); return; }

    $('#suggestions-result, #suggestions-empty').hide();
    $('#suggestions-loading').show();

    $.post(RZPA_SEO.ajaxUrl, {
      action: 'rzpa_seo_get_link_suggestions',
      nonce: RZPA_SEO.nonce,
      post_id: postId
    }, function(res){
      $('#suggestions-loading').hide();
      if (res.success && res.data && res.data.length) {
        var $list = $('#suggestions-list').empty();
        $.each(res.data, function(i, s){
          var item = $('<div style="background:var(--bg-300);border:1px solid var(--border);border-radius:var(--radius);padding:12px;"></div>');
          item.append('<div style="font-size:13px;font-weight:600;margin-bottom:4px;">'+$('<span>').text(s.title || s.url).html()+'</div>');
          if (s.reason) item.append('<div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">'+$('<span>').text(s.reason).html()+'</div>');
          if (s.score) item.append('<div style="font-size:11px;color:var(--neon);">Score: '+s.score+'</div>');
          item.append('<a href="'+s.url+'" target="_blank" class="rzpa-btn" style="font-size:11px;margin-top:8px;display:inline-block;">Åbn side ↗</a>');
          $list.append(item);
        });
        $('#suggestions-result').show();
      } else {
        $('#suggestions-empty').show();
      }
    }).fail(function(){
      $('#suggestions-loading').hide();
      $('#suggestions-empty').show();
    });
  });
})(jQuery);
</script>
