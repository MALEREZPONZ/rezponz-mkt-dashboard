<?php if ( ! defined( 'ABSPATH' ) ) exit;
$is_edit = ! empty( $member['id'] );
$title   = $is_edit ? __( 'Rediger Crew Member', 'rezponz-analytics' ) : __( 'Tilføj Crew Member', 'rezponz-analytics' );
?>
<div class="wrap rzpz-crew-wrap">
  <div class="rzpz-crew-header">
    <div>
      <h1><?php echo esc_html( $title ); ?></h1>
      <p class="rzpz-crew-sub"><a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew' ) ); ?>">← Tilbage til oversigt</a></p>
    </div>
  </div>

  <?php if ( isset( $_GET['error'] ) && $_GET['error'] === 'required' ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-error">⚠️ Navn og email er påkrævet.</div>
  <?php endif; ?>

  <div class="rzpz-crew-card" style="max-width:680px">
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rzpz_crew_save_member' ); ?>
      <input type="hidden" name="action" value="rzpz_crew_save_member" />
      <?php if ( $is_edit ) : ?>
      <input type="hidden" name="member_id" value="<?php echo (int) $member['id']; ?>" />
      <?php endif; ?>

      <div class="rzpz-crew-field">
        <label><?php _e( 'Fulde navn', 'rezponz-analytics' ); ?> <span class="required">*</span></label>
        <input type="text" name="display_name" value="<?php echo esc_attr( $member['display_name'] ?? '' ); ?>" required />
      </div>

      <div class="rzpz-crew-field-row">
        <div class="rzpz-crew-field">
          <label><?php _e( 'Email', 'rezponz-analytics' ); ?> <span class="required">*</span></label>
          <input type="email" name="email" value="<?php echo esc_attr( $member['email'] ?? '' ); ?>" required />
        </div>
        <div class="rzpz-crew-field">
          <label><?php _e( 'Telefon', 'rezponz-analytics' ); ?></label>
          <input type="text" name="phone" value="<?php echo esc_attr( $member['phone'] ?? '' ); ?>" />
        </div>
      </div>

      <div class="rzpz-crew-field">
        <label><?php _e( 'Tilknyt WordPress-bruger', 'rezponz-analytics' ); ?></label>
        <select name="user_id">
          <option value=""><?php _e( '— Ingen WP-bruger —', 'rezponz-analytics' ); ?></option>
          <?php foreach ( $wp_users as $u ) : ?>
          <option value="<?php echo (int) $u->ID; ?>" <?php selected( (int) ( $member['user_id'] ?? 0 ), $u->ID ); ?>>
            <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
          </option>
          <?php endforeach; ?>
        </select>
        <small>Bruges til at give adgang til personligt frontend-dashboard via [rezponz_crew_dashboard]</small>
      </div>

      <div class="rzpz-crew-field">
        <label><?php _e( 'Bio / beskrivelse', 'rezponz-analytics' ); ?></label>
        <textarea name="bio" rows="3"><?php echo esc_textarea( $member['bio'] ?? '' ); ?></textarea>
      </div>

      <div class="rzpz-crew-card-title" style="margin-top:20px">📱 <?php _e( 'Sociale profiler', 'rezponz-analytics' ); ?></div>
      <div class="rzpz-crew-field-row">
        <div class="rzpz-crew-field">
          <label>🔵 Facebook</label>
          <input type="url" name="facebook_url" placeholder="https://facebook.com/..." value="<?php echo esc_attr( $member['facebook_url'] ?? '' ); ?>" />
        </div>
        <div class="rzpz-crew-field">
          <label>📸 Instagram</label>
          <input type="url" name="instagram_url" placeholder="https://instagram.com/..." value="<?php echo esc_attr( $member['instagram_url'] ?? '' ); ?>" />
        </div>
      </div>
      <div class="rzpz-crew-field-row">
        <div class="rzpz-crew-field">
          <label>🎵 TikTok</label>
          <input type="url" name="tiktok_url" placeholder="https://tiktok.com/@..." value="<?php echo esc_attr( $member['tiktok_url'] ?? '' ); ?>" />
        </div>
        <div class="rzpz-crew-field">
          <label>👻 Snapchat</label>
          <input type="url" name="snapchat_url" placeholder="https://snapchat.com/add/..." value="<?php echo esc_attr( $member['snapchat_url'] ?? '' ); ?>" />
        </div>
      </div>

      <div class="rzpz-crew-field">
        <label><?php _e( 'Status', 'rezponz-analytics' ); ?></label>
        <select name="status">
          <option value="active" <?php selected( $member['status'] ?? 'active', 'active' ); ?>>✅ Aktiv</option>
          <option value="inactive" <?php selected( $member['status'] ?? '', 'inactive' ); ?>>⏸ Inaktiv</option>
        </select>
      </div>

      <div style="margin-top:24px;display:flex;gap:12px">
        <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary">
          <?php echo $is_edit ? '💾 Gem ændringer' : '➕ Opret Crew Member'; ?>
        </button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rezponz-crew' ) ); ?>" class="rzpz-crew-btn">Annuller</a>
      </div>
    </form>
  </div>
</div>
