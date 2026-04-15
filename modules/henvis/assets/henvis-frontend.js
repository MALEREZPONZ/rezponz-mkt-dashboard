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

        // Send-knappen trykkes: validér form → vis CAPTCHA inline
        triggerBtn.addEventListener('click', function () {
            // Brug native HTML5 form validation
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Vis CAPTCHA-boksen med smooth scroll
            captchaStep.style.display = 'block';
            captchaStep.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (answerInput) {
                answerInput.value = '';
                answerInput.style.borderColor = '';
                answerInput.focus();
            }

            // Skift knap-tekst til "Ændre svar"
            triggerBtn.textContent = '✏ Ret svar inden afsendelse';
            triggerBtn.style.opacity = '0.6';
        });

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
