<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Admin view: Department management
 *
 * @var array      $departments  all departments
 * @var bool       $saved
 * @var bool       $deleted
 * @var int        $edit_id      dept currently being edited (0 = none / new)
 * @var array|null $edit_dept
 * @var WP_User[]  $dept_users
 */

$user_error  = $_GET['user_error']  ?? '';
$user_saved  = ! empty( $_GET['user_saved'] );
$user_deleted= ! empty( $_GET['user_deleted'] );
$pw_saved    = ! empty( $_GET['pw_saved'] );
$pw_error    = ! empty( $_GET['pw_error'] );

$s = 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;';
?>
<div class="wrap" style="<?php echo $s; ?>max-width:980px">

  <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
    🏢 Afdelinger
  </h1>

  <?php if ( $saved )        : ?><div class="notice notice-success inline" style="margin:0 0 20px"><p>✓ Gemt</p></div><?php endif; ?>
  <?php if ( $deleted )      : ?><div class="notice notice-success inline" style="margin:0 0 20px"><p>✓ Slettet</p></div><?php endif; ?>
  <?php if ( $user_saved )   : ?><div class="notice notice-success inline" style="margin:0 0 20px"><p>✓ Bruger oprettet</p></div><?php endif; ?>
  <?php if ( $user_deleted ) : ?><div class="notice notice-success inline" style="margin:0 0 20px"><p>✓ Bruger slettet</p></div><?php endif; ?>
  <?php if ( $pw_saved )     : ?><div class="notice notice-success inline" style="margin:0 0 20px"><p>✓ Adgangskode nulstillet</p></div><?php endif; ?>
  <?php if ( $user_error )   : ?><div class="notice notice-error inline"   style="margin:0 0 20px"><p>⚠ <?php echo esc_html( is_string($user_error) && strlen($user_error) > 1 ? $user_error : 'Brugernavn/adgangskode ugyldig (min. 8 tegn)' ); ?></p></div><?php endif; ?>
  <?php if ( $pw_error )     : ?><div class="notice notice-error inline"   style="margin:0 0 20px"><p>⚠ Adgangskode skal være mindst 8 tegn</p></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start">

    <!-- Left: dept list -->
    <div>
      <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;margin:0 0 12px">Afdelinger</h3>

      <?php foreach ( $departments as $d ) : ?>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzlq-departments&edit_dept=' . $d['id'] ) ); ?>"
         style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;text-decoration:none;color:#111;margin-bottom:6px;border:1.5px solid <?php echo $edit_id == $d['id'] ? '#738991' : '#e5e7eb'; ?>;background:<?php echo $edit_id == $d['id'] ? '#f0f7f9' : '#fff'; ?>">
        <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?php echo esc_attr( $d['color'] ); ?>;flex-shrink:0"></span>
        <span style="flex:1;font-size:14px;font-weight:600"><?php echo esc_html( $d['name'] ); ?></span>
        <span style="font-size:12px;color:#aaa"><?php echo count( RZLQ_Dept::get_dept_users( (int) $d['id'] ) ); ?> brugere</span>
      </a>
      <?php endforeach; ?>

      <a href="<?php echo esc_url( admin_url( 'admin.php?page=rzlq-departments' ) ); ?>"
         style="display:block;padding:10px 14px;border:1.5px dashed #d1d5db;border-radius:10px;text-align:center;font-size:13px;color:#738991;text-decoration:none;font-weight:700;margin-top:8px">
        + Opret ny afdeling
      </a>
    </div>

    <!-- Right: edit panel -->
    <div>

      <!-- Create / edit dept -->
      <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;margin-bottom:24px">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 20px">
          <?php echo $edit_id ? 'Rediger afdeling' : 'Opret afdeling'; ?>
        </h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action"   value="rzlq_save_dept">
          <input type="hidden" name="dept_id"  value="<?php echo $edit_id; ?>">
          <?php wp_nonce_field( 'rzlq_save_dept', 'rzlq_dept_nonce' ); ?>

          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
            <div style="flex:1;min-width:180px">
              <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;display:block;margin-bottom:6px">Navn</label>
              <input type="text" name="dept_name" required
                     value="<?php echo esc_attr( $edit_dept['name'] ?? '' ); ?>"
                     placeholder="f.eks. CBB Mobil"
                     style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:9px 12px;font-size:14px;font-family:inherit;outline:none"
                     onfocus="this.style.borderColor='#738991'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
            <div>
              <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;display:block;margin-bottom:6px">Farve</label>
              <input type="color" name="dept_color"
                     value="<?php echo esc_attr( $edit_dept['color'] ?? '#738991' ); ?>"
                     style="width:60px;height:40px;border:1.5px solid #e5e7eb;border-radius:8px;padding:2px;cursor:pointer">
            </div>
          </div>

          <div style="display:flex;gap:10px;align-items:center">
            <button type="submit"
                    style="background:#738991;color:#fff;border:none;border-radius:8px;padding:10px 22px;font-size:14px;font-weight:700;cursor:pointer">
              💾 Gem afdeling
            </button>
            <?php if ( $edit_id ) : ?>
            <button type="button" onclick="document.getElementById('rzlq-del-dept-form').submit()"
                    style="background:none;border:none;color:#ef4444;font-size:13px;cursor:pointer;padding:8px"
                    onmousedown="return confirm('Slet afdeling og alle dens brugere?')">
              🗑 Slet afdeling
            </button>
            <?php endif; ?>
          </div>
        </form>

        <?php if ( $edit_id ) : ?>
        <!-- Delete dept form — OUTSIDE the save form to avoid invalid nesting -->
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rzlq-del-dept-form">
          <input type="hidden" name="action"  value="rzlq_delete_dept">
          <input type="hidden" name="dept_id" value="<?php echo $edit_id; ?>">
          <?php wp_nonce_field( 'rzlq_delete_dept', 'rzlq_dept_del_nonce' ); ?>
        </form>
        <?php endif; ?>
      </div>

      <?php if ( $edit_id ) : ?>
      <!-- Users section -->
      <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;margin-bottom:24px">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 16px">Brugere i <?php echo esc_html( $edit_dept['name'] ); ?></h3>

        <?php if ( $dept_users ) : ?>
        <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px">
          <thead>
            <tr style="border-bottom:2px solid #f3f4f6">
              <th style="text-align:left;padding:8px 10px;color:#888;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Brugernavn</th>
              <th style="text-align:left;padding:8px 10px;color:#888;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Displaynavn</th>
              <th style="text-align:left;padding:8px 10px;color:#888;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Senest aktiv</th>
              <th style="text-align:right;padding:8px 10px"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $dept_users as $u ) : ?>
          <tr style="border-bottom:1px solid #f3f4f6">
            <td style="padding:10px;font-weight:600"><?php echo esc_html( $u->user_login ); ?></td>
            <td style="padding:10px;color:#555"><?php echo esc_html( $u->display_name ); ?></td>
            <td style="padding:10px;color:#aaa;font-size:12px">
              <?php
              $last = get_user_meta( $u->ID, 'session_tokens', true );
              if ( $last && is_array( $last ) ) {
                  $latest = max( array_column( array_values( $last ), 'login' ) );
                  echo esc_html( human_time_diff( $latest ) . ' siden' );
              } else {
                  echo '—';
              }
              ?>
            </td>
            <td style="padding:10px;text-align:right">
              <!-- Reset password -->
              <button type="button"
                      onclick="rzlqShowPwReset(<?php echo $u->ID; ?>,'<?php echo esc_js( $u->user_login ); ?>')"
                      style="background:none;border:1px solid #e5e7eb;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer;color:#555;margin-right:6px">
                🔑 Nyt kodeord
              </button>
              <!-- Delete user -->
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"
                    onsubmit="return confirm('Slet bruger <?php echo esc_js( $u->user_login ); ?>?')">
                <input type="hidden" name="action"  value="rzlq_delete_user">
                <input type="hidden" name="user_id" value="<?php echo $u->ID; ?>">
                <input type="hidden" name="dept_id" value="<?php echo $edit_id; ?>">
                <?php wp_nonce_field( 'rzlq_delete_user', 'rzlq_user_del_nonce' ); ?>
                <button type="submit" style="background:none;border:1px solid #fca5a5;border-radius:6px;padding:5px 10px;font-size:12px;cursor:pointer;color:#ef4444">✕ Slet</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else : ?>
        <p style="color:#aaa;font-size:14px;margin:0 0 20px">Ingen brugere endnu. Opret den første nedenfor.</p>
        <?php endif; ?>

        <!-- Password reset modal (inline) -->
        <div id="rzlq-pw-panel" style="display:none;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:16px">
          <p style="font-size:13px;font-weight:600;margin:0 0 10px" id="rzlq-pw-label">Nyt kodeord til bruger</p>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action"   value="rzlq_reset_pw">
            <input type="hidden" name="user_id"  id="rzlq-pw-uid" value="">
            <input type="hidden" name="dept_id"  value="<?php echo $edit_id; ?>">
            <?php wp_nonce_field( 'rzlq_reset_pw', 'rzlq_pw_nonce' ); ?>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="password" name="new_password" minlength="8" required
                     placeholder="Minimum 8 tegn"
                     style="flex:1;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:14px;font-family:inherit;outline:none">
              <button type="submit" style="background:#738991;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer">Gem</button>
              <button type="button" onclick="document.getElementById('rzlq-pw-panel').style.display='none'"
                      style="background:none;border:1px solid #e5e7eb;border-radius:8px;padding:9px 14px;font-size:13px;cursor:pointer">Annuller</button>
            </div>
          </form>
        </div>

        <!-- Add new user -->
        <h4 style="font-size:13px;font-weight:700;color:#555;margin:0 0 12px">Tilføj bruger</h4>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action"  value="rzlq_save_user">
          <input type="hidden" name="dept_id" value="<?php echo $edit_id; ?>">
          <?php wp_nonce_field( 'rzlq_save_user', 'rzlq_user_nonce' ); ?>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:160px">
              <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;margin-bottom:5px">Brugernavn</label>
              <input type="text" name="username" required
                     placeholder="cbb_sælger1"
                     style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:14px;font-family:inherit;outline:none"
                     onfocus="this.style.borderColor='#738991'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
            <div style="flex:1;min-width:160px">
              <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;margin-bottom:5px">Displaynavn</label>
              <input type="text" name="display_name"
                     placeholder="CBB Sælger 1"
                     style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:14px;font-family:inherit;outline:none"
                     onfocus="this.style.borderColor='#738991'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
            <div style="flex:1;min-width:160px">
              <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;margin-bottom:5px">Adgangskode</label>
              <input type="password" name="password" required minlength="8"
                     placeholder="Min. 8 tegn"
                     style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:14px;font-family:inherit;outline:none"
                     onfocus="this.style.borderColor='#738991'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
          </div>
          <button type="submit"
                  style="margin-top:12px;background:#738991;color:#fff;border:none;border-radius:8px;padding:10px 22px;font-size:14px;font-weight:700;cursor:pointer">
            + Opret bruger
          </button>
        </form>
      </div>

      <!-- Dept info note -->
      <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:14px 18px;font-size:13px;color:#0369a1">
        🔒 <strong>Låste konti:</strong> Afdelingsbrugere kan kun tilgå Live Quiz og ser udelukkende deres afdelings quizzer og historik. De kan ikke ændre adgangskode selv.
      </div>
      <?php endif; ?>

    </div><!-- /right -->
  </div><!-- /grid -->
</div>

<script>
function rzlqShowPwReset(userId, username) {
  document.getElementById('rzlq-pw-uid').value = userId;
  document.getElementById('rzlq-pw-label').textContent = 'Nyt kodeord til ' + username;
  document.getElementById('rzlq-pw-panel').style.display = 'block';
  document.getElementById('rzlq-pw-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
