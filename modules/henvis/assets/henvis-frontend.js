/* Henvis Din Ven – Frontend JS */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var captchaStep  = document.getElementById('rzpz-captcha-step');
        var formFields   = document.getElementById('rzpz-form-fields');
        var confirmBtn   = document.getElementById('rzpz-captcha-confirm');
        var answerInput  = document.getElementById('rzpz_captcha_answer');

        if (!captchaStep || !formFields || !confirmBtn) return;

        // If there's a form validation error (not captcha-related), skip captcha step
        var errorBanner = document.querySelector('.rzpz-henvis-error');
        if (errorBanner && errorBanner.textContent.indexOf('menneskeverifikation') === -1) {
            captchaStep.style.display = 'none';
            formFields.style.display = 'block';
        }

        // Confirm CAPTCHA
        confirmBtn.addEventListener('click', function () {
            var val = answerInput ? answerInput.value.trim() : '';
            if (!val) {
                answerInput.focus();
                answerInput.style.borderColor = '#f87171';
                return;
            }
            captchaStep.style.display = 'none';
            formFields.style.display  = 'block';
            // Focus first field
            var first = formFields.querySelector('input[name="referrer_name"]');
            if (first) first.focus();
        });

        // Allow pressing Enter in captcha input
        if (answerInput) {
            answerInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirmBtn.click();
                }
            });
            answerInput.addEventListener('input', function () {
                answerInput.style.borderColor = '#333';
            });
        }
    });
})();
