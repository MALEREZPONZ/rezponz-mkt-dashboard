<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rzpz-crew-wrap">
  <div class="rzpz-crew-header">
    <div>
      <h1>💰 Bonus & Belønning</h1>
      <p class="rzpz-crew-sub">Definer bonusregler og se beregninger per Crew Member</p>
    </div>
    <button class="rzpz-crew-btn rzpz-crew-btn-primary" id="rzpz-recalculate-bonus">🔄 Genberegn bonus</button>
  </div>

  <?php if ( isset( $_GET['saved'] ) ) : ?>
  <div class="rzpz-crew-notice rzpz-crew-notice-success">✓ Gemt.</div>
  <?php endif; ?>

  <!-- Bonus Rules -->
  <div class="rzpz-crew-card" style="margin-bottom:24px">
    <div class="rzpz-crew-card-title">⚙️ Bonusregler</div>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rzpz_crew_save_bonus_rule' ); ?>
      <input type="hidden" name="action" value="rzpz_crew_save_bonus_rule" />
      <div class="rzpz-crew-field-row" style="align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div class="rzpz-crew-field">
          <label>Regelnavn</label>
          <input type="text" name="rule_name" placeholder="Lead bonus" required />
        </div>
        <div class="rzpz-crew-field">
          <label>Type</label>
          <select name="rule_type">
            <option value="per_conversion">Per lead/konvertering</option>
            <option value="per_clicks">Per X klik</option>
          </select>
        </div>
        <div class="rzpz-crew-field">
          <label>Beløb (kr.)</label>
          <input type="number" name="amount_dkk" min="0" step="0.01" placeholder="500" required />
        </div>
        <div class="rzpz-crew-field" id="clicks-threshold-field" style="display:none">
          <label>Klik-tærskel</label>
          <input type="number" name="clicks_threshold" min="1" value="100" />
          <small>Antal klik der giver ét bonus-beløb</small>
        </div>
        <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary">+ Tilføj regel</button>
      </div>
    </form>

    <?php if ( ! empty( $rules ) ) : ?>
    <table class="rzpz-crew-table" style="margin-top:16px">
      <thead><tr><th>Regel</th><th>Type</th><th>Beløb</th><th>Tærskel</th><th>Aktiv</th></tr></thead>
      <tbody>
      <?php foreach ( $rules as $rule ) : ?>
      <tr>
        <td><?php echo esc_html( $rule['rule_name'] ); ?></td>
        <td><?php echo $rule['rule_type'] === 'per_conversion' ? '🎯 Per lead' : '🖱 Per klik'; ?></td>
        <td><?php echo number_format( (float) $rule['amount_dkk'], 2, ',', '.' ); ?> kr.</td>
        <td><?php echo $rule['rule_type'] === 'per_clicks' ? (int) $rule['clicks_threshold'] . ' klik' : '–'; ?></td>
        <td><span class="rzpz-crew-badge rzpz-crew-badge-<?php echo $rule['active'] ? 'active' : 'paused'; ?>"><?php echo $rule['active'] ? 'Aktiv' : 'Inaktiv'; ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else : ?>
    <div class="rzpz-crew-empty" style="margin-top:16px">Ingen bonusregler defineret endnu.</div>
    <?php endif; ?>
  </div>

  <!-- Bonus Overview -->
  <div class="rzpz-crew-card">
    <div class="rzpz-crew-card-title">📊 Bonus oversigt — denne måned</div>
    <?php if ( empty( $bonuses ) ) : ?>
    <div class="rzpz-crew-empty">Klik "Genberegn bonus" for at beregne bonus for alle aktive Crew Members.</div>
    <?php else : ?>
    <table class="rzpz-crew-table">
      <thead><tr>
        <th>Crew Member</th><th>Periode</th><th>Klik</th><th>Leads</th><th>Bonus</th><th>Status</th><th>Handling</th>
      </tr></thead>
      <tbody>
      <?php foreach ( $bonuses as $b ) :
        $status_labels = [ 'pending' => '⏳ Afventer', 'approved' => '✅ Godkendt', 'paid' => '💸 Betalt' ];
        $status_cls    = [ 'pending' => 'paused', 'approved' => 'active', 'paid' => 'paid' ];
      ?>
      <tr>
        <td><strong><?php echo esc_html( $b['display_name'] ); ?></strong></td>
        <td><?php echo esc_html( $b['period_start'] . ' – ' . $b['period_end'] ); ?></td>
        <td><?php echo number_format( (int) $b['total_clicks'], 0, ',', '.' ); ?></td>
        <td><?php echo (int) $b['total_conversions']; ?></td>
        <td><strong><?php echo number_format( (float) $b['amount_dkk'], 2, ',', '.' ); ?> kr.</strong></td>
        <td>
          <span class="rzpz-crew-badge rzpz-crew-badge-<?php echo $status_cls[ $b['status'] ] ?? 'paused'; ?>">
            <?php echo $status_labels[ $b['status'] ] ?? $b['status']; ?>
          </span>
        </td>
        <td>
          <button class="rzpz-crew-btn rzpz-crew-btn-sm rzpz-bonus-edit-btn"
                  data-id="<?php echo (int) $b['id']; ?>"
                  data-status="<?php echo esc_attr( $b['status'] ); ?>"
                  data-notes="<?php echo esc_attr( $b['admin_notes'] ?? '' ); ?>">
            Opdater
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Bonus Update Modal -->
<div id="rzpz-bonus-modal" class="rzpz-crew-modal" style="display:none">
  <div class="rzpz-crew-modal-inner">
    <h3>Opdater bonus status</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rzpz_crew_update_bonus' ); ?>
      <input type="hidden" name="action" value="rzpz_crew_update_bonus" />
      <input type="hidden" name="bonus_id" id="modal-bonus-id" value="" />
      <div class="rzpz-crew-field">
        <label>Status</label>
        <select name="status" id="modal-bonus-status">
          <option value="pending">⏳ Afventer</option>
          <option value="approved">✅ Godkendt</option>
          <option value="paid">💸 Betalt</option>
        </select>
      </div>
      <div class="rzpz-crew-field">
        <label>Admin noter</label>
        <textarea name="admin_notes" id="modal-bonus-notes" rows="3"></textarea>
      </div>
      <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="rzpz-crew-btn rzpz-crew-btn-primary">Gem</button>
        <button type="button" class="rzpz-crew-btn" id="rzpz-bonus-modal-close">Annuller</button>
      </div>
    </form>
  </div>
</div>
