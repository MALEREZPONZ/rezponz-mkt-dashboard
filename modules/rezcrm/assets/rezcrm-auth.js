/* RezCRM Auth — Login AJAX flow v3.4.0 */
(function () {
    'use strict';

    const cfg      = window.RZCRM_AUTH || {};
    const ajaxUrl  = cfg.ajaxUrl  || '/wp-admin/admin-ajax.php';
    const nonce    = cfg.nonce    || '';
    const redirect = cfg.redirect || '/wp-admin/admin.php?page=rzpa-rezcrm';

    let mfaToken = '';  // temp token passed between step 1 and step 2/setup

    // ── Helpers ───────────────────────────────────────────────────────────────

    function qs(sel)  { return document.querySelector(sel); }
    function show(el) { if (el) el.hidden = false; }
    function hide(el) { if (el) el.hidden = true; }

    function showStep(id) {
        document.querySelectorAll('.rzcrm-login-step').forEach(s => hide(s));
        const el = qs(id);
        if (el) { show(el); }
    }

    function setLoading(btn, loading) {
        const text    = btn.querySelector('.rzcrm-btn-text');
        const spinner = btn.querySelector('.rzcrm-btn-spinner');
        btn.disabled = loading;
        if (text)    text.hidden    = loading;
        if (spinner) spinner.hidden = !loading;
    }

    function showError(id, msg) {
        const el = qs(id);
        if (!el) return;
        el.textContent = msg;
        show(el);
    }

    function hideError(id) {
        const el = qs(id);
        if (el) hide(el);
    }

    async function post(action, data) {
        const body = new URLSearchParams({ action, nonce, ...data });
        const res  = await fetch(ajaxUrl, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
            body    : body.toString(),
        });
        return res.json();
    }

    // ── Step 1: username + password ───────────────────────────────────────────

    const step1Btn = qs('#rzcrm-step1-btn');
    if (step1Btn) {
        step1Btn.addEventListener('click', handleStep1);
    }

    // Allow enter key on password field
    const pwdInput = qs('#rzcrm-password');
    if (pwdInput) {
        pwdInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') handleStep1();
        });
    }

    async function handleStep1() {
        hideError('#rzcrm-error-1');
        const username = (qs('#rzcrm-username')?.value || '').trim();
        const password = (qs('#rzcrm-password')?.value || ''); // no trim — passwords may have spaces

        if (!username || !password) {
            showError('#rzcrm-error-1', 'Udfyld brugernavn og adgangskode.');
            return;
        }

        setLoading(step1Btn, true);
        try {
            const r = await post('rzcrm_login_step1', { username, password });
            if (r.success) {
                mfaToken = r.data.token || '';
                if (r.data.step === 'setup') {
                    // First-time: load QR and show setup
                    showStep('#rzcrm-step-setup');
                    loadQrCode();
                } else {
                    // Returning user: show TOTP entry
                    showStep('#rzcrm-step-2');
                    qs('#rzcrm-otp')?.focus();
                }
            } else {
                showError('#rzcrm-error-1', r.data?.message || 'Forkert brugernavn eller adgangskode.');
            }
        } catch (err) {
            showError('#rzcrm-error-1', 'Netværksfejl. Prøv igen.');
        } finally {
            setLoading(step1Btn, false);
        }
    }

    // ── Step 2: TOTP verify ───────────────────────────────────────────────────

    const step2Btn = qs('#rzcrm-step2-btn');
    if (step2Btn) {
        step2Btn.addEventListener('click', handleStep2);
    }

    const otpInput = qs('#rzcrm-otp');
    if (otpInput) {
        otpInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') handleStep2();
        });
        // Auto-submit when 6 digits entered — 400ms debounce so user can correct typos
        let _otpTimer = null;
        otpInput.addEventListener('input', () => {
            clearTimeout(_otpTimer);
            if (/^\d{6}$/.test(otpInput.value)) {
                _otpTimer = setTimeout(handleStep2, 400);
            }
        });
    }

    async function handleStep2() {
        hideError('#rzcrm-error-2');
        const code = (qs('#rzcrm-otp')?.value || '').replace(/\D/g, '');

        if (code.length !== 6) {
            showError('#rzcrm-error-2', 'Koden skal være 6 cifre.');
            return;
        }

        setLoading(step2Btn, true);
        try {
            const r = await post('rzcrm_login_step2', { token: mfaToken, code });
            if (r.success) {
                window.location.href = r.data.redirect || redirect;
            } else {
                showError('#rzcrm-error-2', r.data?.message || 'Forkert kode. Prøv igen.');
                if (otpInput) { otpInput.value = ''; otpInput.focus(); }
            }
        } catch (err) {
            showError('#rzcrm-error-2', 'Netværksfejl. Prøv igen.');
        } finally {
            setLoading(step2Btn, false);
        }
    }

    // Back button
    const backBtn = qs('#rzcrm-back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            showStep('#rzcrm-step-1');
            mfaToken = '';
        });
    }

    // ── Setup: load QR ────────────────────────────────────────────────────────

    async function loadQrCode() {
        const loading    = qs('#rzcrm-qr-loading');
        const img        = qs('#rzcrm-qr-img');
        const secretWrap = qs('#rzcrm-secret-wrap');
        const secretCode = qs('#rzcrm-secret-code');

        try {
            const r = await post('rzcrm_mfa_setup', { token: mfaToken });
            if (r.success) {
                hide(loading);
                if (img) {
                    img.src = r.data.qr_url;
                    img.onload = () => {
                        show(img);
                        qs('#rzcrm-setup-otp')?.focus();
                    };
                }
                if (secretCode) secretCode.textContent = r.data.secret;
                if (secretWrap) show(secretWrap);
            } else {
                if (loading) loading.textContent = r.data?.message || 'Fejl ved hentning af QR-kode.';
            }
        } catch (err) {
            if (loading) loading.textContent = 'Netværksfejl. Genindlæs siden.';
        }
    }

    // Copy secret key
    const copyBtn = qs('#rzcrm-copy-secret');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const secret = qs('#rzcrm-secret-code')?.textContent || '';
            navigator.clipboard.writeText(secret).then(() => {
                copyBtn.textContent = 'Kopieret ✓';
                setTimeout(() => { copyBtn.textContent = 'Kopiér'; }, 2000);
            });
        });
    }

    // ── Setup: confirm OTP ────────────────────────────────────────────────────

    const setupConfirmBtn = qs('#rzcrm-setup-confirm-btn');
    if (setupConfirmBtn) {
        setupConfirmBtn.addEventListener('click', handleSetupConfirm);
    }

    const setupOtp = qs('#rzcrm-setup-otp');
    if (setupOtp) {
        let _setupTimer = null;
        setupOtp.addEventListener('input', () => {
            clearTimeout(_setupTimer);
            if (/^\d{6}$/.test(setupOtp.value)) {
                _setupTimer = setTimeout(handleSetupConfirm, 400);
            }
        });
    }

    // Back button on setup step (Step 3)
    const setupBackBtn = qs('#rzcrm-setup-back-btn');
    if (setupBackBtn) {
        setupBackBtn.addEventListener('click', () => {
            showStep('#rzcrm-step-1');
            mfaToken = '';
        });
    }

    async function handleSetupConfirm() {
        hideError('#rzcrm-error-setup');
        const code = (qs('#rzcrm-setup-otp')?.value || '').replace(/\D/g, '');

        if (code.length !== 6) {
            showError('#rzcrm-error-setup', 'Koden skal være 6 cifre.');
            return;
        }

        setLoading(setupConfirmBtn, true);
        try {
            const r = await post('rzcrm_mfa_confirm', { token: mfaToken, code });
            if (r.success) {
                window.location.href = r.data.redirect || redirect;
            } else {
                showError('#rzcrm-error-setup', r.data?.message || 'Forkert kode. Prøv igen fra din app.');
                if (setupOtp) { setupOtp.value = ''; setupOtp.focus(); }
            }
        } catch (err) {
            showError('#rzcrm-error-setup', 'Netværksfejl. Prøv igen.');
        } finally {
            setLoading(setupConfirmBtn, false);
        }
    }

})();
