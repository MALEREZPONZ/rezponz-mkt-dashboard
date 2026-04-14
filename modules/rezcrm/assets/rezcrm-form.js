/* =========================================================
   RezCRM Frontend Form — rezcrm-form.js  v3.3.0
   ========================================================= */
(function () {
  'use strict';

  const API   = RZPZ_FORM.apiBase;
  const NONCE = RZPZ_FORM.nonce;

  document.querySelectorAll('.rzcrm-form-wrap').forEach(initForm);

  function initForm(wrap) {
    const formId      = +wrap.dataset.formId;
    const positionId  = +wrap.dataset.positionId || 0;
    const totalSteps  = +wrap.dataset.totalSteps || 1;
    const formEl      = wrap.querySelector('.rzcrm-form');
    const uid         = wrap.id;
    let currentStep   = 1;
    let sessionToken  = null;

    // ── Session start (UTM + referrer tracking) ─────────────────────────────
    const utm = new URLSearchParams(location.search);
    fetch(API + 'crm/form-session', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body:    JSON.stringify({
        form_id:      formId,
        utm_source:   utm.get('utm_source')   || '',
        utm_medium:   utm.get('utm_medium')   || '',
        utm_campaign: utm.get('utm_campaign') || '',
        utm_content:  utm.get('utm_content')  || '',
        referrer:     document.referrer || '',
      }),
    }).then(r => r.json()).then(d => { sessionToken = d.token; });

    // ── Photo preview ────────────────────────────────────────────────────────
    const photoInput = wrap.querySelector('.rzcrm-photo-btn input[type="file"]');
    if (photoInput) {
      photoInput.addEventListener('change', () => {
        const file = photoInput.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
          const preview = wrap.querySelector('.rzcrm-photo-preview');
          preview.innerHTML = `<img src="${e.target.result}" alt="Profilbillede">`;
        };
        reader.readAsDataURL(file);
      });
    }

    // ── File drag-drop ───────────────────────────────────────────────────────
    wrap.querySelectorAll('.rzcrm-file-zone').forEach(zone => {
      zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-active'); });
      zone.addEventListener('dragleave', () => zone.classList.remove('drag-active'));
      zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-active');
        const input = zone.querySelector('.rzcrm-file-input');
        if (input && e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          showFilePreview(zone, input.files[0]);
        }
      });
      const input = zone.querySelector('.rzcrm-file-input');
      if (input) {
        input.addEventListener('change', () => { if (input.files[0]) showFilePreview(zone, input.files[0]); });
      }
    });

    function showFilePreview(zone, file) {
      const preview = zone.querySelector('.rzcrm-file-preview');
      if (preview) preview.textContent = '✓ ' + file.name;
    }

    // ── Date fields ──────────────────────────────────────────────────────────
    wrap.querySelectorAll('.rzcrm-date-group').forEach(group => {
      const parts   = group.querySelectorAll('.rzcrm-date-part');
      const hidden  = group.nextElementSibling; // .rzcrm-date-hidden
      const update  = () => {
        const vals = [...parts].map(s => s.value);
        if (vals.every(v => v)) {
          hidden.value = vals[2] + '-' + String(vals[1]).padStart(2,'0') + '-' + String(vals[0]).padStart(2,'0');
        }
      };
      parts.forEach(s => s.addEventListener('change', update));
    });

    // ── Step navigation ──────────────────────────────────────────────────────
    function showStep(n) {
      wrap.querySelectorAll('.rzcrm-step').forEach(step => {
        step.style.display = +step.dataset.step === n ? '' : 'none';
      });
      currentStep = n;

      // Update progress bar
      const fill  = wrap.querySelector('[id$="-progress"]');
      const label = wrap.querySelector('.rzcrm-step-current');
      if (fill)  fill.style.width = Math.round(n / totalSteps * 100) + '%';
      if (label) label.textContent = n;

      // Track step via API
      if (sessionToken) {
        fetch(API + 'crm/form-session/step', {
          method:  'PATCH',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body:    JSON.stringify({ token: sessionToken, step: n }),
        }).catch(() => {});
      }

      // Scroll to top of form
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Next button
    wrap.querySelectorAll('.rzcrm-btn-next').forEach(btn => {
      btn.addEventListener('click', () => {
        if (validateStep(currentStep)) showStep(currentStep + 1);
      });
    });

    // Back button
    wrap.querySelectorAll('.rzcrm-btn-back').forEach(btn => {
      btn.addEventListener('click', () => showStep(currentStep - 1));
    });

    // ── Validation ───────────────────────────────────────────────────────────
    function validateStep(step) {
      const stepEl = wrap.querySelector(`.rzcrm-step[data-step="${step}"]`);
      if (!stepEl) return true;
      let valid = true;

      // Clear previous errors
      stepEl.querySelectorAll('.rzcrm-field-error').forEach(e => e.remove());
      stepEl.querySelectorAll('.error').forEach(e => e.classList.remove('error'));

      // Check required fields
      stepEl.querySelectorAll('[required]').forEach(field => {
        let fieldValid = true;
        if (field.type === 'checkbox' && !field.checked) fieldValid = false;
        else if (field.type === 'radio') {
          const name = field.name;
          fieldValid = !!stepEl.querySelector(`[name="${name}"]:checked`);
        } else if (!field.value.trim()) {
          fieldValid = false;
        }

        if (!fieldValid) {
          field.classList.add('error');
          const err = document.createElement('div');
          err.className   = 'rzcrm-field-error';
          err.textContent = 'Dette felt er påkrævet';
          field.parentNode.insertBefore(err, field.nextSibling);
          valid = false;
        }
      });

      // Email format check
      const emailInput = stepEl.querySelector('input[type="email"]');
      if (emailInput && emailInput.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
        emailInput.classList.add('error');
        const err = document.createElement('div');
        err.className   = 'rzcrm-field-error';
        err.textContent = 'Ugyldig email-adresse';
        emailInput.parentNode.insertBefore(err, emailInput.nextSibling);
        valid = false;
      }

      if (!valid) {
        // Scroll to first error
        const firstErr = stepEl.querySelector('.error');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      return valid;
    }

    // ── Form submission ──────────────────────────────────────────────────────
    formEl.addEventListener('submit', async e => {
      e.preventDefault();
      if (!validateStep(currentStep)) return;

      // Check GDPR
      const gdpr = formEl.querySelector('[name="gdpr_consent"]');
      if (gdpr && !gdpr.checked) {
        gdpr.classList.add('error');
        showError('Du skal acceptere betingelserne for databehandling');
        return;
      }

      const submitBtn = formEl.querySelector('.rzcrm-btn-submit');
      submitBtn.disabled  = true;
      submitBtn.classList.add('loading');
      submitBtn.textContent = 'Sender ansøgning';

      // Collect all field values
      const fieldData = {};
      formEl.querySelectorAll('[name]').forEach(input => {
        const name = input.name;
        if (!name || name === 'gdpr_consent') return;

        if (input.type === 'checkbox') {
          if (!fieldData[name]) fieldData[name] = [];
          if (input.checked) fieldData[name].push(input.value);
        } else if (input.type === 'radio') {
          if (input.checked) fieldData[name] = input.value;
        } else if (input.type === 'file') {
          // File upload handled separately (skip for now — URL stored after upload)
        } else {
          if (input.value) fieldData[name] = input.value;
        }
      });

      // Convert checkbox arrays to comma-separated strings
      Object.keys(fieldData).forEach(k => {
        if (Array.isArray(fieldData[k])) fieldData[k] = fieldData[k].join(', ');
      });

      try {
        const res = await fetch(API + 'crm/form-submit', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body:    JSON.stringify({
            form_id:        formId,
            position_id:    positionId,
            session_token:  sessionToken,
            fields:         fieldData,
          }),
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.message || 'Indsendelse fejlede');

        // Show success
        formEl.style.display                          = 'none';
        wrap.querySelector('[id$="-success"]').style.display = '';
        wrap.querySelector('[id$="-progress"]') && (wrap.querySelector('.rzcrm-progress-wrap').style.display = 'none');

        // Redirect if configured
        if (data.redirect_url) {
          setTimeout(() => { window.location.href = data.redirect_url; }, 2000);
        }

      } catch (err) {
        showError(err.message || 'Der opstod en fejl. Prøv igen.');
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        submitBtn.textContent = 'Send ansøgning';
      }
    });

    function showError(msg) {
      const errEl = document.getElementById(uid + '-error');
      if (!errEl) return;
      errEl.textContent = msg;
      errEl.style.display = '';
      errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
})();
