<?php
/**
 * Frontend ansøgningsformular — renderes via [rezcrm_form] shortcode
 * Variabler tilgængelige fra shortcode: $form, $fields, $position_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$sections = [];
$current_section = 'Generelt';
$section_idx = 0;

// Gruppér felter i sektioner
foreach ( $fields as $field ) {
    if ( $field->field_type === 'section' ) {
        $current_section = $field->label;
        $section_idx++;
        continue;
    }
    $sections[ $section_idx ]['title']  = $current_section;
    $sections[ $section_idx ]['fields'][] = $field;
}

$total_steps = max( 1, count( $sections ) );
$form_uid    = 'rzcrm-form-' . $form->id;
?>
<div class="rzcrm-form-wrap" id="<?php echo esc_attr( $form_uid ); ?>"
     data-form-id="<?php echo (int) $form->id; ?>"
     data-position-id="<?php echo (int) $position_id; ?>"
     data-total-steps="<?php echo (int) $total_steps; ?>">

  <?php if ( $form->intro_text ) : ?>
    <div class="rzcrm-intro"><?php echo wp_kses_post( $form->intro_text ); ?></div>
  <?php endif; ?>

  <?php if ( $form->show_progress && $total_steps > 1 ) : ?>
    <div class="rzcrm-progress-wrap">
      <div class="rzcrm-progress-bar">
        <div class="rzcrm-progress-fill" id="<?php echo esc_attr( $form_uid ); ?>-progress" style="width:<?php echo round( 100 / $total_steps ); ?>%"></div>
      </div>
      <div class="rzcrm-progress-label">
        Trin <span class="rzcrm-step-current">1</span> af <?php echo (int) $total_steps; ?>
      </div>
    </div>
  <?php endif; ?>

  <form class="rzcrm-form" novalidate>
    <div class="rzcrm-steps">

      <?php foreach ( array_values( $sections ) as $step_idx => $section ) :
        $step_num = $step_idx + 1;
        $is_first = $step_num === 1;
      ?>
      <div class="rzcrm-step" data-step="<?php echo (int) $step_num; ?>" style="<?php echo $is_first ? '' : 'display:none'; ?>">
        <?php if ( $section['title'] && $total_steps > 1 ) : ?>
          <h2 class="rzcrm-section-title"><?php echo esc_html( $section['title'] ); ?></h2>
        <?php endif; ?>

        <div class="rzcrm-fields">
          <?php foreach ( $section['fields'] as $field ) :
            $fid  = esc_attr( $form_uid . '-' . $field->field_key );
            $fkey = esc_attr( $field->field_key );
            $req  = $field->required ? 'required' : '';
            $req_label = $field->required ? '<span class="rzcrm-required">*</span>' : '';
            $options = $field->options ? json_decode( $field->options, true ) : [];
          ?>
          <div class="rzcrm-field rzcrm-field-<?php echo esc_attr( $field->field_type ); ?>"
               data-key="<?php echo $fkey; ?>">
            <?php if ( $field->field_type !== 'profile_photo' ) : ?>
              <label class="rzcrm-label" for="<?php echo $fid; ?>">
                <?php echo esc_html( $field->label ); ?> <?php echo $req_label; ?>
              </label>
            <?php endif; ?>

            <?php if ( $field->help_text ) : ?>
              <p class="rzcrm-help"><?php echo esc_html( $field->help_text ); ?></p>
            <?php endif; ?>

            <?php switch ( $field->field_type ) :
              case 'url':
              case 'text':
              case 'email':
              case 'phone': ?>
                <input type="<?php echo esc_attr( $field->field_type ); ?>"
                       id="<?php echo $fid; ?>"
                       name="<?php echo $fkey; ?>"
                       class="rzcrm-input"
                       placeholder="<?php echo esc_attr( $field->placeholder ?? '' ); ?>"
                       <?php echo $req; ?>>
                <?php break;

              case 'birthdate': ?>
                <div class="rzcrm-date-group">
                  <select name="<?php echo $fkey; ?>_day" class="rzcrm-select rzcrm-date-part" aria-label="Dag">
                    <option value="">Dag</option>
                    <?php for($d=1;$d<=31;$d++) echo "<option value='{$d}'>{$d}</option>"; ?>
                  </select>
                  <select name="<?php echo $fkey; ?>_month" class="rzcrm-select rzcrm-date-part" aria-label="Måned">
                    <option value="">Måned</option>
                    <?php $months=['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
                    foreach($months as $mi=>$m) echo "<option value='".($mi+1)."'>{$m}</option>"; ?>
                  </select>
                  <select name="<?php echo $fkey; ?>_year" class="rzcrm-select rzcrm-date-part" aria-label="År">
                    <option value="">År</option>
                    <?php $yr=date('Y'); for($y=$yr-16;$y>=$yr-80;$y--) echo "<option value='{$y}'>{$y}</option>"; ?>
                  </select>
                </div>
                <input type="hidden" name="<?php echo $fkey; ?>" class="rzcrm-date-hidden">
                <?php break;

              case 'date': ?>
                <div class="rzcrm-date-group">
                  <select name="<?php echo $fkey; ?>_day" class="rzcrm-select rzcrm-date-part" aria-label="Dag">
                    <option value="">Dag</option>
                    <?php for($d=1;$d<=31;$d++) echo "<option value='{$d}'>{$d}</option>"; ?>
                  </select>
                  <select name="<?php echo $fkey; ?>_month" class="rzcrm-select rzcrm-date-part" aria-label="Måned">
                    <option value="">Måned</option>
                    <?php $months=['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
                    foreach($months as $mi=>$m) echo "<option value='".($mi+1)."'>{$m}</option>"; ?>
                  </select>
                  <select name="<?php echo $fkey; ?>_year" class="rzcrm-select rzcrm-date-part" aria-label="År">
                    <option value="">År</option>
                    <?php $yr=date('Y'); for($y=$yr+5;$y>=$yr-2;$y--) echo "<option value='{$y}'>{$y}</option>"; ?>
                  </select>
                </div>
                <input type="hidden" name="<?php echo $fkey; ?>" class="rzcrm-date-hidden">
                <?php break;

              case 'textarea': ?>
                <textarea id="<?php echo $fid; ?>"
                          name="<?php echo $fkey; ?>"
                          class="rzcrm-textarea"
                          rows="4"
                          placeholder="<?php echo esc_attr( $field->placeholder ?? '' ); ?>"
                          <?php echo $req; ?>></textarea>
                <?php break;

              case 'radio': ?>
                <div class="rzcrm-radio-group" role="radiogroup">
                  <?php foreach ( $options as $opt ) : ?>
                    <label class="rzcrm-radio-label">
                      <input type="radio" name="<?php echo $fkey; ?>" value="<?php echo esc_attr($opt); ?>" <?php echo $req; ?>>
                      <?php echo esc_html($opt); ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <?php break;

              case 'yes_no': ?>
                <div class="rzcrm-radio-group rzcrm-yes-no" role="radiogroup">
                  <label class="rzcrm-radio-label">
                    <input type="radio" name="<?php echo $fkey; ?>" value="ja" <?php echo $req; ?>> Ja
                  </label>
                  <label class="rzcrm-radio-label">
                    <input type="radio" name="<?php echo $fkey; ?>" value="nej"> Nej
                  </label>
                </div>
                <?php break;

              case 'checkbox': ?>
                <div class="rzcrm-checkbox-group">
                  <?php foreach ( $options as $opt ) : ?>
                    <label class="rzcrm-checkbox-label">
                      <input type="checkbox" name="<?php echo $fkey; ?>[]" value="<?php echo esc_attr($opt); ?>">
                      <?php echo esc_html($opt); ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <?php break;

              case 'select': ?>
                <select id="<?php echo $fid; ?>" name="<?php echo $fkey; ?>" class="rzcrm-select" <?php echo $req; ?>>
                  <option value="">– Vælg –</option>
                  <?php foreach ( $options as $opt ) : ?>
                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php break;

              case 'file': ?>
                <div class="rzcrm-file-zone" id="<?php echo $fid; ?>-zone">
                  <input type="file" id="<?php echo $fid; ?>" name="<?php echo $fkey; ?>"
                         class="rzcrm-file-input"
                         accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                         <?php echo $req; ?>>
                  <label for="<?php echo $fid; ?>" class="rzcrm-file-label">
                    <span class="rzcrm-file-icon">📎</span>
                    <span class="rzcrm-file-text">Vælg fil eller træk hertil</span>
                    <span class="rzcrm-file-hint">PDF, Word, JPG (max 10MB)</span>
                  </label>
                  <div class="rzcrm-file-preview"></div>
                </div>
                <?php break;

              case 'profile_photo': ?>
                <div class="rzcrm-photo-wrap">
                  <div class="rzcrm-photo-preview" id="<?php echo $fid; ?>-preview">
                    <svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" width="80" height="80">
                      <circle cx="40" cy="40" r="40" fill="#ddd"/>
                      <circle cx="40" cy="32" r="14" fill="#bbb"/>
                      <ellipse cx="40" cy="68" rx="22" ry="14" fill="#bbb"/>
                    </svg>
                  </div>
                  <label class="rzcrm-photo-btn">
                    Upload profilbillede
                    <input type="file" name="profile_photo" accept="image/*" class="rzcrm-file-input" hidden>
                  </label>
                </div>
                <?php break;

              case 'hidden': ?>
                <input type="hidden" name="<?php echo $fkey; ?>" value="<?php echo esc_attr( $field->placeholder ?? '' ); ?>">
                <?php break;
            endswitch; ?>
          </div>
          <?php endforeach; ?>
        </div><!-- /.rzcrm-fields -->

        <!-- GDPR på sidste step -->
        <?php if ( $step_num === $total_steps ) :
          $privacy_url = get_option('rzpa_settings', [])['crm_privacy_url'] ?? '';
          if (!$privacy_url) $privacy_url = 'https://rezponz.dk/privatliv-cookiepolitik-rezponz/';
        ?>
          <div class="rzcrm-gdpr">
            <label class="rzcrm-checkbox-label rzcrm-gdpr-label">
              <input type="checkbox" name="gdpr_consent" id="rzcrm-gdpr-consent" required>
              Jeg accepterer <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank">betingelserne for databehandling</a>
            </label>
          </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="rzcrm-nav">
          <?php if ( $step_num > 1 ) : ?>
            <button type="button" class="rzcrm-btn rzcrm-btn-back">← Tilbage</button>
          <?php else : ?>
            <span></span>
          <?php endif; ?>

          <?php if ( $step_num < $total_steps ) : ?>
            <button type="button" class="rzcrm-btn rzcrm-btn-next">Næste →</button>
          <?php else : ?>
            <button type="submit" class="rzcrm-btn rzcrm-btn-submit">
              Send ansøgning
            </button>
          <?php endif; ?>
        </div>
      </div><!-- /.rzcrm-step -->
      <?php endforeach; ?>

    </div><!-- /.rzcrm-steps -->
  </form>

  <!-- Success state (skjult, vises af JS) -->
  <div class="rzcrm-success" id="<?php echo esc_attr( $form_uid ); ?>-success" style="display:none">
    <?php echo wp_kses_post( $form->success_message ?: '<h3>Tak for din ansøgning! 🎉</h3><p>Vi vender tilbage hurtigst muligt.</p>' ); ?>
  </div>

  <!-- Fejl-besked -->
  <div class="rzcrm-error-banner" id="<?php echo esc_attr( $form_uid ); ?>-error" style="display:none"></div>

</div><!-- /.rzcrm-form-wrap -->
