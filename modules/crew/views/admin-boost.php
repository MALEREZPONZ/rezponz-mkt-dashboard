<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rzpz-crew-wrap">
  <div class="rzpz-crew-header">
    <div>
      <h1>🚀 Boost til Advertising</h1>
      <p class="rzpz-crew-sub">Løft top-performende Crew-indhold til betalte annoncer</p>
    </div>
  </div>

  <?php if ( isset( $_GET['saved'] ) ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-success">✓ Gemt.</div>
  <?php endif; ?>

  <!-- Top performers not yet boosted -->
  <?php
  $unboosted = array_filter( $candidates, fn($c) => empty($c['boost_id']) );
  $boosted   = array_filter( $candidates, fn($c) => !empty($c['boost_id']) );
  ?>
  <?php if ( ! empty( $unboosted ) ) : ?>
  <div class="rzpz-crew-card" style="margin-bottom:24px">
    <div class="rzpz-crew-card-title">⭐ Top-performende content — klar til boost</div>
    <table class="rzpz-crew-table">
      <thead><tr>
        <th>Crew Member</th><th>Kampagne</th><th>Klik</th><th>Leads</th><th>Link</th><th>Handling</th>
      </tr></thead>
      <tbody>
      <?php foreach ( array_values( $unboosted ) as $c ) : ?>
      <tr class="<?php echo (int)$c['conversions'] > 0 ? 'rzpz-crew-row-highlight' : ''; ?>">
        <td><?php echo esc_html( $c['display_name'] ); ?></td>
        <td><?php echo esc_html( $c['campaign_name'] ); ?></td>
        <td><?php echo number_format( (int) $c['clicks'], 0, ',', '.' ); ?></td>
        <td>
          <?php if ( (int)$c['conversions'] > 0 ) : ?>
          <span style="color:#4ade80;font-weight:700"><?php echo (int)$c['conversions']; ?> 🎯</span>
          <?php else : ?>
          <?php echo (int)$c['conversions']; ?>
          <?php endif; ?>
        </td>
        <td><a href="<?php echo esc_url( $c['full_url'] ); ?>" target="_blank" style="color:#CCFF00;word-break:break-all;font-size:11px"><?php echo esc_html( wp_trim_words( $c['full_url'], 8, '...' ) ); ?></a></td>
        <td>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
            <?php wp_nonce_field( 'rzpz_crew_update_boost' ); ?>
            <input type="hidden" name="action" value="rzpz_crew_update_boost" />
            <input type="hidden" name="action_type" value="create" />
            <input type="hidden" name="link_id" value="<?php echo (int) $c['id']; ?>" />
            <input type="hidden" name="crew_member_id" value="<?php echo (int) $c['crew_member_id']; ?>" />
            <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary rzpz-crew-btn-sm">🚀 Boost</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Active boosts -->
  <div class="rzpz-crew-card">
    <div class="rzpz-crew-card-title">📋 Boost historik (<?php echo count( $boosts ); ?>)</div>
    <?php if ( empty( $boosts ) ) : ?>
    <div class="rzpz-crew-empty">Ingen boosts endnu. Klik "Boost" på et top-link ovenfor.</div>
    <?php else : ?>
    <table class="rzpz-crew-table">
      <thead><tr><th>Crew Member</th><th>Kampagne</th><th>Klik</th><th>Status</th><th>Ad-link</th><th>Handling</th></tr></thead>
      <tbody>
      <?php foreach ( $boosts as $b ) :
        $status_labels = [ 'ready' => '🟡 Klar', 'in_progress' => '🔵 I gang', 'live' => '🟢 Live', 'paused' => '⏸ Pauseret' ];
      ?>
      <tr>
        <td><?php echo esc_html( $b['display_name'] ); ?></td>
        <td><?php echo esc_html( $b['campaign_name'] ); ?></td>
        <td><?php echo number_format( (int) $b['clicks'], 0, ',', '.' ); ?></td>
        <td><?php echo $status_labels[ $b['status'] ] ?? esc_html( $b['status'] ); ?></td>
        <td><?php echo $b['ad_url'] ? '<a href="' . esc_url($b['ad_url']) . '" target="_blank" style="color:#CCFF00">Åbn annonce →</a>' : '–'; ?></td>
        <td>
          <button class="rzpz-crew-btn rzpz-crew-btn-sm rzpz-boost-edit-btn"
                  data-id="<?php echo (int) $b['id']; ?>"
                  data-status="<?php echo esc_attr( $b['status'] ); ?>"
                  data-notes="<?php echo esc_attr( $b['notes'] ?? '' ); ?>"
                  data-adurl="<?php echo esc_attr( $b['ad_url'] ?? '' ); ?>">
            Rediger
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Boost Update Modal -->
<div id="rzpz-boost-modal" class="rzpz-crew-modal" style="display:none">
  <div class="rzpz-crew-modal-inner">
    <h3>Opdater boost</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rzpz_crew_update_boost' ); ?>
      <input type="hidden" name="action" value="rzpz_crew_update_boost" />
      <input type="hidden" name="boost_id" id="modal-boost-id" value="" />
      <div class="rzpz-crew-field">
        <label>Status</label>
        <select name="status" id="modal-boost-status">
          <option value="ready">🟡 Klar</option>
          <option value="in_progress">🔵 I gang</option>
          <option value="live">🟢 Live</option>
          <option value="paused">⏸ Pauseret</option>
        </select>
      </div>
      <div class="rzpz-crew-field">
        <label>Annonce-link (valgfrit)</label>
        <input type="url" name="ad_url" id="modal-boost-adurl" placeholder="https://business.facebook.com/..." />
      </div>
      <div class="rzpz-crew-field">
        <label>Noter til ad-team</label>
        <textarea name="notes" id="modal-boost-notes" rows="3" placeholder="Brug dette billede, kør i 14 dage..."></textarea>
      </div>
      <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary">Gem</button>
        <button type="button" class="rzpz-crew-btn" id="rzpz-boost-modal-close">Annuller</button>
      </div>
    </form>
  </div>
</div>
