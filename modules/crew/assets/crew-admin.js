/* Rezponz Crew – Admin JavaScript */
(function () {
  'use strict';

  // Copy-to-clipboard
  document.querySelectorAll('.rzpz-crew-copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const url = btn.dataset.copy || btn.closest('.rzpz-crew-link-copy-wrap')?.querySelector('input')?.value;
      if (!url) return;
      navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Kopieret!';
        setTimeout(() => btn.textContent = orig, 2000);
      });
    });
  });

  // Bonus rule type toggle
  const ruleTypeSelect = document.querySelector('[name="rule_type"]');
  const thresholdField = document.getElementById('clicks-threshold-field');
  if (ruleTypeSelect && thresholdField) {
    ruleTypeSelect.addEventListener('change', () => {
      thresholdField.style.display = ruleTypeSelect.value === 'per_clicks' ? 'flex' : 'none';
    });
  }

  // Bonus update modal
  const bonusModal     = document.getElementById('rzpz-bonus-modal');
  const bonusModalClose = document.getElementById('rzpz-bonus-modal-close');
  document.querySelectorAll('.rzpz-bonus-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('modal-bonus-id').value     = btn.dataset.id;
      document.getElementById('modal-bonus-status').value = btn.dataset.status;
      document.getElementById('modal-bonus-notes').value  = btn.dataset.notes;
      if (bonusModal) bonusModal.style.display = 'flex';
    });
  });
  bonusModalClose?.addEventListener('click', () => { if (bonusModal) bonusModal.style.display = 'none'; });
  bonusModal?.addEventListener('click', e => { if (e.target === bonusModal) bonusModal.style.display = 'none'; });

  // Boost update modal
  const boostModal     = document.getElementById('rzpz-boost-modal');
  const boostModalClose = document.getElementById('rzpz-boost-modal-close');
  document.querySelectorAll('.rzpz-boost-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('modal-boost-id').value     = btn.dataset.id;
      document.getElementById('modal-boost-status').value = btn.dataset.status;
      document.getElementById('modal-boost-notes').value  = btn.dataset.notes;
      document.getElementById('modal-boost-adurl').value  = btn.dataset.adurl;
      if (boostModal) boostModal.style.display = 'flex';
    });
  });
  boostModalClose?.addEventListener('click', () => { if (boostModal) boostModal.style.display = 'none'; });
  boostModal?.addEventListener('click', e => { if (e.target === boostModal) boostModal.style.display = 'none'; });

  // Recalculate bonus button
  const recalcBtn = document.getElementById('rzpz-recalculate-bonus');
  if (recalcBtn && window.RZPZ_Crew_Admin) {
    recalcBtn.addEventListener('click', async () => {
      recalcBtn.disabled = true;
      recalcBtn.textContent = '⏳ Beregner…';
      try {
        const res = await fetch(RZPZ_Crew_Admin.restBase + '/recalculate-bonus', {
          method: 'POST',
          headers: { 'X-WP-Nonce': RZPZ_Crew_Admin.wpNonce, 'Content-Type': 'application/json' },
          body: '{}',
        });
        const data = await res.json();
        recalcBtn.textContent = `✓ ${data?.data?.updated ?? 0} opdateret`;
        setTimeout(() => location.reload(), 1200);
      } catch (e) {
        recalcBtn.textContent = '⚠️ Fejl';
        recalcBtn.disabled = false;
      }
    });
  }
})();
