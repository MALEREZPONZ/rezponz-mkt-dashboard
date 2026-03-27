<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rzpz-henvis-wrap">

<?php if ( ! empty( $result['success'] ) ) : ?>

    <div class="rzpz-henvis-success">
        <div class="rzpz-success-icon">🎉</div>
        <h2><?php _e( 'Tak for din henvisning!', 'rezponz-analytics' ); ?></h2>
        <p><?php _e( 'Vi har sendt en bekræftelse til dig og din ven, og notificeret din Senior Manager. Godt gået!', 'rezponz-analytics' ); ?></p>
    </div>

<?php else : ?>

<?php if ( ! empty( $result['error'] ) ) : ?>
    <div class="rzpz-henvis-error">
        <span class="rzpz-error-icon">⚠️</span>
        <?php echo esc_html( $result['error'] ); ?>
    </div>
<?php endif; ?>

<form class="rzpz-henvis-form" method="post" id="rzpz-henvis-form">
    <?php wp_nonce_field( 'rzpz_henvis_submit' ); ?>
    <input type="hidden" name="rzpz_captcha_expected" value="<?php echo esc_attr( $expected ); ?>">
    <input type="hidden" name="rzpz_captcha_hash"     value="<?php echo esc_attr( $hash ); ?>">

    <!-- ── CAPTCHA ──────────────────────────────────────────────────────────── -->
    <div class="rzpz-captcha-card" id="rzpz-captcha-step">
        <div class="rzpz-captcha-icon">🛡️</div>
        <h3><?php _e( 'Bekræft at du er et menneske', 'rezponz-analytics' ); ?></h3>
        <p class="rzpz-captcha-question">
            <?php printf( __( 'Hvad er %d + %d?', 'rezponz-analytics' ), $a, $b ); ?>
        </p>
        <input
            type="number"
            name="rzpz_captcha_answer"
            id="rzpz_captcha_answer"
            class="rzpz-captcha-input"
            placeholder="Dit svar…"
            autocomplete="off"
            <?php if ( ! empty( $result['error'] ) && strpos( $result['error'], 'menneskeverifikation' ) !== false ) : ?>
                autofocus
            <?php endif; ?>
        >
        <button type="button" class="rzpz-captcha-btn" id="rzpz-captcha-confirm">
            ✅ <?php _e( 'Bekræft', 'rezponz-analytics' ); ?>
        </button>
    </div>

    <!-- ── Referral form (hidden until CAPTCHA passed) ──────────────────────── -->
    <div class="rzpz-form-fields" id="rzpz-form-fields" style="display:none;">

        <div class="rzpz-form-section">
            <h3 class="rzpz-section-title">👤 <?php _e( 'Dine oplysninger', 'rezponz-analytics' ); ?></h3>

            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="referrer_name"><?php _e( 'Dit navn', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <input type="text" name="referrer_name" id="referrer_name" required
                        value="<?php echo esc_attr( $_POST['referrer_name'] ?? '' ); ?>"
                        placeholder="<?php esc_attr_e( 'Dit fulde navn', 'rezponz-analytics' ); ?>">
                </div>
                <div class="rzpz-field">
                    <label for="referrer_phone"><?php _e( 'Telefon', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <input type="tel" name="referrer_phone" id="referrer_phone" required
                        value="<?php echo esc_attr( $_POST['referrer_phone'] ?? '' ); ?>"
                        placeholder="+45 12 34 56 78">
                </div>
            </div>

            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="referrer_email"><?php _e( 'Email', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <input type="email" name="referrer_email" id="referrer_email" required
                        value="<?php echo esc_attr( $_POST['referrer_email'] ?? '' ); ?>"
                        placeholder="din@email.dk">
                </div>
                <div class="rzpz-field">
                    <label for="manager_key"><?php _e( 'Hvilken Senior Manager arbejder du for?', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <select name="manager_key" id="manager_key" required>
                        <option value=""><?php _e( '– Vælg Senior Manager –', 'rezponz-analytics' ); ?></option>
                        <?php foreach ( RZPZ_Henvis::get_managers() as $key => $mgr ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"
                                <?php selected( $_POST['manager_key'] ?? '', $key ); ?>>
                                <?php echo esc_html( $mgr['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="rzpz-form-section">
            <h3 class="rzpz-section-title">🤝 <?php _e( 'Din ven', 'rezponz-analytics' ); ?></h3>

            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="friend_name"><?php _e( 'Vennens navn', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <input type="text" name="friend_name" id="friend_name" required
                        value="<?php echo esc_attr( $_POST['friend_name'] ?? '' ); ?>"
                        placeholder="<?php esc_attr_e( 'Vennens fulde navn', 'rezponz-analytics' ); ?>">
                </div>
                <div class="rzpz-field">
                    <label for="friend_phone"><?php _e( 'Telefon', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <input type="tel" name="friend_phone" id="friend_phone" required
                        value="<?php echo esc_attr( $_POST['friend_phone'] ?? '' ); ?>"
                        placeholder="+45 12 34 56 78">
                </div>
            </div>

            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="friend_email"><?php _e( 'Email', 'rezponz-analytics' ); ?> <span class="req">*</span></label>
                    <input type="email" name="friend_email" id="friend_email" required
                        value="<?php echo esc_attr( $_POST['friend_email'] ?? '' ); ?>"
                        placeholder="vennens@email.dk">
                </div>
            </div>
        </div>

        <div class="rzpz-field rzpz-consent-field">
            <label class="rzpz-checkbox-label">
                <input type="checkbox" name="rzpz_consent" value="1"
                    <?php checked( ! empty( $_POST['rzpz_consent'] ) ); ?> required>
                <span><?php _e( 'Jeg bekræfter at min ven er okay med at blive kontaktet af Rezponz.', 'rezponz-analytics' ); ?></span>
            </label>
        </div>

        <div class="rzpz-submit-row">
            <button type="submit" name="rzpz_henvis_submit" value="1" class="rzpz-submit-btn">
                🚀 <?php _e( 'Send henvisning', 'rezponz-analytics' ); ?>
            </button>
        </div>

    </div><!-- /.rzpz-form-fields -->
</form>

<?php endif; ?>
</div><!-- /.rzpz-henvis-wrap -->
