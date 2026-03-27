<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rzpz-crew-frontend">

  <!-- Header -->
  <div class="rzpz-crew-fe-header">
    <div class="rzpz-crew-fe-avatar">
      <?php if ( $member['avatar_url'] ) : ?>
      <img src="<?php echo esc_url( $member['avatar_url'] ); ?>" alt="" />
      <?php else : ?>
      <div class="rzpz-crew-fe-avatar-initials"><?php echo esc_html( mb_substr( $member['display_name'], 0, 1 ) ); ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="rzpz-crew-fe-hello"><?php _e( 'Hey,', 'rezponz-analytics' ); ?> <strong><?php echo esc_html( explode( ' ', $member['display_name'] )[0] ); ?></strong> 👋</div>
      <div class="rzpz-crew-fe-subtitle"><?php _e( 'Crew Member siden', 'rezponz-analytics' ); ?> <?php echo esc_html( substr( $member['created_at'], 0, 10 ) ); ?></div>
    </div>
  </div>

  <!-- Period selector -->
  <div class="rzpz-crew-fe-period">
    <?php foreach ( [ 7 => '7 dage', 30 => '30 dage', 90 => '90 dage' ] as $d => $label ) : ?>
    <button class="rzpz-crew-fe-period-btn <?php echo $days === $d ? 'active' : ''; ?>" data-days="<?php echo $d; ?>"><?php echo esc_html( $label ); ?></button>
    <?php endforeach; ?>
  </div>

  <!-- KPIs -->
  <div class="rzpz-crew-fe-kpis">
    <div class="rzpz-crew-fe-kpi">
      <div class="rzpz-crew-fe-kpi-icon">🖱</div>
      <div class="rzpz-crew-fe-kpi-val"><?php echo number_format( $clicks, 0, ',', '.' ); ?></div>
      <div class="rzpz-crew-fe-kpi-label"><?php _e( 'Klik på dine links', 'rezponz-analytics' ); ?></div>
    </div>
    <div class="rzpz-crew-fe-kpi rzpz-crew-fe-kpi-highlight">
      <div class="rzpz-crew-fe-kpi-icon">🎯</div>
      <div class="rzpz-crew-fe-kpi-val"><?php echo (int) $conversions; ?></div>
      <div class="rzpz-crew-fe-kpi-label"><?php _e( 'Leads du har genereret', 'rezponz-analytics' ); ?></div>
    </div>
    <div class="rzpz-crew-fe-kpi">
      <div class="rzpz-crew-fe-kpi-icon">📊</div>
      <div class="rzpz-crew-fe-kpi-val"><?php echo $clicks > 0 ? number_format( $conversions / $clicks * 100, 1 ) . '%' : '–'; ?></div>
      <div class="rzpz-crew-fe-kpi-label"><?php _e( 'Konverteringsrate', 'rezponz-analytics' ); ?></div>
    </div>
    <div class="rzpz-crew-fe-kpi rzpz-crew-fe-kpi-bonus">
      <div class="rzpz-crew-fe-kpi-icon">💰</div>
      <div class="rzpz-crew-fe-kpi-val"><?php echo number_format( $est_bonus, 0, ',', '.' ); ?> kr.</div>
      <div class="rzpz-crew-fe-kpi-label"><?php _e( 'Estimeret bonus denne måned', 'rezponz-analytics' ); ?></div>
    </div>
  </div>

  <!-- Top links -->
  <?php if ( ! empty( $top_links ) ) : ?>
  <div class="rzpz-crew-fe-card">
    <div class="rzpz-crew-fe-card-title">🏆 <?php _e( 'Dine bedste links', 'rezponz-analytics' ); ?></div>
    <div class="rzpz-crew-fe-links">
      <?php foreach ( $top_links as $i => $tl ) : ?>
      <div class="rzpz-crew-fe-link-row">
        <div class="rzpz-crew-fe-link-rank">#<?php echo $i + 1; ?></div>
        <div class="rzpz-crew-fe-link-info">
          <div class="rzpz-crew-fe-link-campaign"><?php echo esc_html( $tl['campaign_name'] ); ?></div>
          <div class="rzpz-crew-fe-link-stats">
            <?php echo number_format( (int) $tl['clicks'], 0, ',', '.' ); ?> klik
            <?php if ( (int) $tl['conversions'] > 0 ) : ?>
            · <span style="color:#CCFF00;font-weight:700"><?php echo (int) $tl['conversions']; ?> leads 🎯</span>
            <?php endif; ?>
          </div>
        </div>
        <button class="rzpz-crew-fe-copy-btn" data-url="<?php echo esc_attr( $tl['full_url'] ); ?>">📋 Kopiér</button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- All links -->
  <?php if ( ! empty( $links ) ) : ?>
  <div class="rzpz-crew-fe-card">
    <div class="rzpz-crew-fe-card-title">🔗 <?php _e( 'Alle dine links', 'rezponz-analytics' ); ?></div>
    <?php foreach ( $links as $lnk ) : ?>
    <div class="rzpz-crew-fe-link-copy">
      <span class="rzpz-crew-fe-link-tag"><?php echo esc_html( $lnk['campaign_name'] ); ?></span>
      <input type="text" value="<?php echo esc_attr( $lnk['full_url'] ); ?>" readonly onclick="this.select()" class="rzpz-crew-fe-link-input" />
      <button class="rzpz-crew-fe-copy-btn" data-url="<?php echo esc_attr( $lnk['full_url'] ); ?>">📋</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <div class="rzpz-crew-fe-card rzpz-crew-fe-empty">
    <p><?php _e( 'Du har ingen trackinglinks endnu. Kontakt din administrator for at få oprettet dine links.', 'rezponz-analytics' ); ?></p>
  </div>
  <?php endif; ?>

  <!-- Bonus history -->
  <?php if ( ! empty( $bonuses ) ) : ?>
  <div class="rzpz-crew-fe-card">
    <div class="rzpz-crew-fe-card-title">💰 <?php _e( 'Bonus historik', 'rezponz-analytics' ); ?></div>
    <div class="rzpz-crew-fe-bonus-list">
      <?php foreach ( $bonuses as $b ) :
        $status_txt = [ 'pending' => 'Afventer', 'approved' => 'Godkendt ✅', 'paid' => 'Udbetalt 💸' ];
        $status_cls = [ 'pending' => 'rzpz-crew-fe-bonus-pending', 'approved' => 'rzpz-crew-fe-bonus-approved', 'paid' => 'rzpz-crew-fe-bonus-paid' ];
      ?>
      <div class="rzpz-crew-fe-bonus-row <?php echo $status_cls[ $b['status'] ] ?? ''; ?>">
        <div>
          <div class="rzpz-crew-fe-bonus-period"><?php echo esc_html( $b['period_start'] . ' – ' . $b['period_end'] ); ?></div>
          <div class="rzpz-crew-fe-bonus-stats"><?php echo (int)$b['total_clicks']; ?> klik · <?php echo (int)$b['total_conversions']; ?> leads</div>
        </div>
        <div class="rzpz-crew-fe-bonus-amount"><?php echo number_format( (float)$b['amount_dkk'], 0, ',', '.' ); ?> kr.</div>
        <div class="rzpz-crew-fe-bonus-status"><?php echo $status_txt[ $b['status'] ] ?? $b['status']; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- How it works -->
  <?php if ( ! empty( $rules ) ) : ?>
  <div class="rzpz-crew-fe-card rzpz-crew-fe-info">
    <div class="rzpz-crew-fe-card-title">📖 <?php _e( 'Sådan optjener du bonus', 'rezponz-analytics' ); ?></div>
    <ul class="rzpz-crew-fe-rules">
      <?php foreach ( $rules as $rule ) : ?>
      <li>
        <?php if ( $rule['rule_type'] === 'per_conversion' ) : ?>
        <strong><?php echo number_format( (float)$rule['amount_dkk'], 0, ',', '.' ); ?> kr.</strong> <?php _e( 'per lead du genererer', 'rezponz-analytics' ); ?>
        <?php else : ?>
        <strong><?php echo number_format( (float)$rule['amount_dkk'], 0, ',', '.' ); ?> kr.</strong> <?php printf( __( 'per %d klik', 'rezponz-analytics' ), (int)$rule['clicks_threshold'] ); ?>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</div>
