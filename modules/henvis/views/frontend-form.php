<?php if ( ! defined( 'ABSPATH' ) ) exit;

$custom_fields_all = RZPZ_Henvis::get_custom_fields();
$cf_referrer       = array_filter( $custom_fields_all, fn($f) => ($f['section']??'referrer') === 'referrer' );
$cf_friend         = array_filter( $custom_fields_all, fn($f) => ($f['section']??'referrer') === 'friend' );

/**
 * Render a single custom field input.
 */
if ( ! function_exists( 'rzpz_render_custom_field' ) ) :
function rzpz_render_custom_field( array $cf, array $post ) : void {
    $id    = esc_attr( $cf['id'] );
    $label = esc_html( $cf['label'] );
    $ph    = esc_attr( $cf['placeholder'] ?? '' );
    $req   = ! empty( $cf['required'] );
    $val   = esc_attr( $post[ $cf['id'] ] ?? '' );
    echo '<div class="rzpz-field">';
    echo '<label for="' . $id . '">' . $label . ( $req ? ' <span class="req">*</span>' : '' ) . '</label>';
    switch ( $cf['type'] ?? 'text' ) {
        case 'textarea':
            echo '<textarea name="' . $id . '" id="' . $id . '" placeholder="' . $ph . '"' . ( $req ? ' required' : '' ) . '>' . $val . '</textarea>';
            break;
        case 'select':
            echo '<select name="' . $id . '" id="' . $id . '"' . ( $req ? ' required' : '' ) . '>';
            echo '<option value="">' . esc_html( $cf['placeholder'] ?: '– Vælg –' ) . '</option>';
            foreach ( $cf['options'] ?? [] as $opt ) {
                $o = esc_html( $opt );
                $s = ( $post[ $cf['id'] ] ?? '' ) === $opt ? ' selected' : '';
                echo '<option value="' . $o . '"' . $s . '>' . $o . '</option>';
            }
            echo '</select>';
            break;
        case 'checkbox':
            $checked = ! empty( $post[ $cf['id'] ] ) ? ' checked' : '';
            echo '<label class="rzpz-checkbox-label" style="font-weight:400"><input type="checkbox" name="' . $id . '" value="1"' . $checked . ( $req ? ' required' : '' ) . '> ' . $label . '</label>';
            break;
        default:
            $type = in_array( $cf['type'], ['text','tel','email','number'], true ) ? $cf['type'] : 'text';
            echo '<input type="' . $type . '" name="' . $id . '" id="' . $id . '" placeholder="' . $ph . '" value="' . $val . '"' . ( $req ? ' required' : '' ) . '>';
    }
    echo '</div>';
}
endif; // function_exists rzpz_render_custom_field
?>
<div class="rzpz-henvis-wrap">

<?php if ( ! empty( $result['success'] ) ) : ?>

    <div class="rzpz-henvis-success">
        <div class="rzpz-success-icon">🎉</div>
        <h2><?php echo esc_html( $cfg['success_title'] ); ?></h2>
        <p><?php echo esc_html( $cfg['success_message'] ); ?></p>
    </div>

<?php else : ?>

    <!-- Hero -->
    <div class="rzpz-hero">
        <div class="rzpz-hero-bonus">& få 500 kr. hvis din ven bliver ansat</div>
        <h2><?php echo esc_html( $cfg['form_title'] ); ?><br><em>til et fedt job</em></h2>
        <p class="rzpz-hero-sub"><?php echo esc_html( $cfg['form_subtitle'] ); ?></p>
    </div>

<?php if ( ! empty( $result['error'] ) ) : ?>
    <div class="rzpz-henvis-error">
        <span>⚠️</span>
        <?php echo esc_html( $result['error'] ); ?>
    </div>
<?php endif; ?>

<form class="rzpz-henvis-form" method="post" id="rzpz-henvis-form">
    <?php wp_nonce_field( 'rzpz_henvis_submit' ); ?>

    <!-- ── Form fields ─────────────────────────────────────────────────────── -->
    <div class="rzpz-form-fields" id="rzpz-form-fields">

        <!-- Section: Referrer -->
        <div class="rzpz-form-section">
            <h3 class="rzpz-section-title"><?php echo esc_html( $cfg['section_referrer'] ); ?></h3>

            <?php
            $f = $cfg['fields'];
            // Row: name + phone
            $show_phone = ! empty( $f['referrer_phone']['enabled'] );
            ?>
            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="referrer_name"><?php echo esc_html( $f['referrer_name']['label'] ); ?> <span class="req">*</span></label>
                    <input type="text" name="referrer_name" id="referrer_name" required
                        value="<?php echo esc_attr( $_POST['referrer_name'] ?? '' ); ?>"
                        placeholder="<?php echo esc_attr( $f['referrer_name']['placeholder'] ); ?>">
                </div>
                <?php if ( $show_phone ) : ?>
                <div class="rzpz-field">
                    <label for="referrer_phone"><?php echo esc_html( $f['referrer_phone']['label'] ); ?><?php echo ! empty($f['referrer_phone']['required']) ? ' <span class="req">*</span>' : ''; ?></label>
                    <input type="tel" name="referrer_phone" id="referrer_phone"
                        <?php echo ! empty($f['referrer_phone']['required']) ? 'required' : ''; ?>
                        value="<?php echo esc_attr( $_POST['referrer_phone'] ?? '' ); ?>"
                        placeholder="<?php echo esc_attr( $f['referrer_phone']['placeholder'] ); ?>">
                </div>
                <?php endif; ?>
            </div>

            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="referrer_email"><?php echo esc_html( $f['referrer_email']['label'] ); ?> <span class="req">*</span></label>
                    <input type="email" name="referrer_email" id="referrer_email" required
                        value="<?php echo esc_attr( $_POST['referrer_email'] ?? '' ); ?>"
                        placeholder="<?php echo esc_attr( $f['referrer_email']['placeholder'] ); ?>">
                </div>
                <div class="rzpz-field">
                    <label for="manager_key"><?php echo esc_html( $cfg['manager_label'] ); ?> <span class="req">*</span></label>
                    <select name="manager_key" id="manager_key" required>
                        <option value=""><?php echo esc_html( $cfg['manager_placeholder'] ); ?></option>
                        <?php foreach ( RZPZ_Henvis::get_managers() as $key => $mgr ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $_POST['manager_key'] ?? '', $key ); ?>>
                                <?php echo esc_html( $mgr['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ( ! empty( $cf_referrer ) ) : ?>
            <div class="rzpz-field-row rzpz-cf-row">
                <?php foreach ( $cf_referrer as $cf ) : rzpz_render_custom_field( $cf, $_POST ); endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Section: Friend -->
        <div class="rzpz-form-section">
            <h3 class="rzpz-section-title"><?php echo esc_html( $cfg['section_friend'] ); ?></h3>

            <div class="rzpz-field-row">
                <div class="rzpz-field">
                    <label for="friend_name"><?php echo esc_html( $f['friend_name']['label'] ); ?> <span class="req">*</span></label>
                    <input type="text" name="friend_name" id="friend_name" required
                        value="<?php echo esc_attr( $_POST['friend_name'] ?? '' ); ?>"
                        placeholder="<?php echo esc_attr( $f['friend_name']['placeholder'] ); ?>">
                </div>
            </div>

            <?php if ( ! empty( $cf_friend ) ) : ?>
            <div class="rzpz-field-row rzpz-cf-row">
                <?php foreach ( $cf_friend as $cf ) : rzpz_render_custom_field( $cf, $_POST ); endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Consent -->
        <div class="rzpz-field rzpz-consent-field">
            <label class="rzpz-checkbox-label">
                <input type="checkbox" name="rzpz_consent" value="1"
                    <?php checked( ! empty( $_POST['rzpz_consent'] ) ); ?> required>
                <span><?php echo esc_html( $cfg['consent_text'] ); ?></span>
            </label>
        </div>

        <div class="rzpz-submit-row">
            <?php if ( $cfg['show_captcha'] ) : ?>
                <!-- Trigger-knap der viser CAPTCHA inline -->
                <button type="button" class="rzpz-submit-btn" id="rzpz-submit-trigger">
                    <?php echo esc_html( $cfg['submit_text'] ); ?>
                </button>
            <?php else : ?>
                <button type="submit" name="rzpz_henvis_submit" value="1" class="rzpz-submit-btn">
                    <?php echo esc_html( $cfg['submit_text'] ); ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if ( $cfg['show_captcha'] ) : ?>
        <!-- ── Inline CAPTCHA — vises kun når Send-knappen trykkes ───────────── -->
        <div class="rzpz-captcha-inline" id="rzpz-captcha-step" style="display:none;">
            <div class="rzpz-captcha-inner">
                <div class="rzpz-captcha-icon">🛡️</div>
                <p class="rzpz-captcha-label"><?php _e( 'Bekræft at du er et menneske', 'rezponz-analytics' ); ?></p>
                <p class="rzpz-captcha-question">
                    <?php printf( __( 'Hvad er %d + %d?', 'rezponz-analytics' ), $a, $b ); ?>
                </p>
                <input type="number" name="rzpz_captcha_answer" id="rzpz_captcha_answer"
                       class="rzpz-captcha-input" placeholder="Dit svar…" autocomplete="off">
                <button type="submit" name="rzpz_henvis_submit" value="1" class="rzpz-captcha-btn" id="rzpz-captcha-confirm">
                    ✅ <?php _e( 'Send nu', 'rezponz-analytics' ); ?>
                </button>
            </div>
        </div>
        <input type="hidden" name="rzpz_captcha_expected" value="<?php echo esc_attr( $expected ); ?>">
        <input type="hidden" name="rzpz_captcha_hash"     value="<?php echo esc_attr( $hash ); ?>">
        <?php endif; ?>

    </div><!-- /.rzpz-form-fields -->
</form>

<?php endif; ?>
</div><!-- /.rzpz-henvis-wrap -->
