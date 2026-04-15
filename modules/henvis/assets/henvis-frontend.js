/* Henvis Din Ven – Frontend JS */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form        = document.getElementById('rzpz-henvis-form');
        var triggerBtn  = document.getElementById('rzpz-submit-trigger');
        var captchaStep = document.getElementById('rzpz-captcha-step');
        var confirmBtn  = document.getElementById('rzpz-captcha-confirm');
        var answerInput = document.getElementById('rzpz_captcha_answer');

        // Ingen CAPTCHA konfigureret — almindelig form, ingen handling nødvendig
        if (!triggerBtn || !captchaStep) return;

        // Hvis siden genindlæser med en fejl om menneskeverifikation —
        // vis CAPTCHA-boksen direkte (brugeren svarede forkert)
        var errorBanner = document.querySelector('.rzpz-henvis-error');
        if (errorBanner && errorBanner.textContent.indexOf('menneskeverifikation') !== -1) {
            captchaStep.style.display = 'block';
            if (answerInput) answerInput.focus();
        }

        var originalBtnText = triggerBtn.textContent;

        function openCaptcha() {
            captchaStep.style.display = 'block';
            captchaStep.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (answerInput) {
                answerInput.value = '';
                answerInput.style.borderColor = '';
                answerInput.style.boxShadow = '';
                answerInput.focus();
            }
            triggerBtn.textContent = '✏ Ret inden afsendelse';
            triggerBtn.style.opacity = '0.55';
        }

        function closeCaptcha() {
            captchaStep.style.display = 'none';
            triggerBtn.textContent = originalBtnText;
            triggerBtn.style.opacity = '1';
            // Scroll back up to form
            var firstField = form ? form.querySelector('input[name="referrer_name"]') : null;
            if (firstField) firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Send-knappen trykkes: validér form → vis CAPTCHA inline
        triggerBtn.addEventListener('click', function () {
            // Hvis CAPTCHA allerede er åben — luk den
            if (captchaStep.style.display === 'block') {
                closeCaptcha();
                return;
            }
            // Brug native HTML5 form validation
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }
            openCaptcha();
        });

        // Luk-knap inde i CAPTCHA-boksen
        var closeBtn = document.getElementById('rzpz-captcha-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeCaptcha);
        }

        // Valider CAPTCHA-input visuelt (fejlfarve fjernes ved input)
        if (answerInput) {
            answerInput.addEventListener('input', function () {
                answerInput.style.borderColor = '';
            });

            // Enter i CAPTCHA-feltet = submit
            answerInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (confirmBtn) confirmBtn.click();
                }
            });
        }

        // "Send nu"-knappen: tjek at svaret er udfyldt inden submit
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function (e) {
                var val = answerInput ? answerInput.value.trim() : '';
                if (!val) {
                    e.preventDefault();
                    answerInput.focus();
                    answerInput.style.borderColor = '#f87171';
                    answerInput.style.boxShadow   = '0 0 0 3px rgba(248,113,113,.25)';
                    return false;
                }
                // Lad form'en submitte normalt (type="submit" på confirm-knappen)
            });
        }
    });
})();
