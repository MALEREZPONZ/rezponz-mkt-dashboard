<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="rzcrm-login-wrap">

    <div class="rzcrm-login-card" id="rzcrm-login-card">

        <div class="rzcrm-login-logo">
            <span class="rzcrm-logo-icon">⬡</span>
            <span class="rzcrm-logo-text">Rez<strong>CRM</strong></span>
        </div>

        <!-- Step 1: Username + Password -->
        <div class="rzcrm-login-step" id="rzcrm-step-1">
            <h2>Log ind</h2>
            <p class="rzcrm-login-sub">Rekrutteringsplatform · Sikker adgang</p>

            <div class="rzcrm-field">
                <label for="rzcrm-username">Brugernavn</label>
                <input type="text" id="rzcrm-username" autocomplete="username" placeholder="dit brugernavn" autofocus>
            </div>
            <div class="rzcrm-field">
                <label for="rzcrm-password">Adgangskode</label>
                <input type="password" id="rzcrm-password" autocomplete="current-password" placeholder="••••••••••">
            </div>

            <button class="rzcrm-btn-primary" id="rzcrm-step1-btn">
                <span class="rzcrm-btn-text">Fortsæt</span>
                <span class="rzcrm-btn-spinner" hidden>↻</span>
            </button>

            <div class="rzcrm-error" id="rzcrm-error-1" hidden></div>
        </div>

        <!-- Step 2: TOTP Code (existing users) -->
        <div class="rzcrm-login-step" id="rzcrm-step-2" hidden>
            <h2>To-faktor godkendelse</h2>
            <p class="rzcrm-login-sub">Åbn din authenticator-app og indtast den 6-cifrede kode</p>

            <div class="rzcrm-field rzcrm-otp-wrap">
                <label for="rzcrm-otp">Engangskode</label>
                <input type="text" id="rzcrm-otp" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" autocomplete="one-time-code" placeholder="000000">
            </div>

            <button class="rzcrm-btn-primary" id="rzcrm-step2-btn">
                <span class="rzcrm-btn-text">Godkend</span>
                <span class="rzcrm-btn-spinner" hidden>↻</span>
            </button>

            <button class="rzcrm-btn-ghost" id="rzcrm-back-btn">← Tilbage</button>

            <div class="rzcrm-error" id="rzcrm-error-2" hidden></div>
        </div>

        <!-- Step 3: First-time MFA setup -->
        <div class="rzcrm-login-step" id="rzcrm-step-setup" hidden>
            <h2>Opsæt to-faktor</h2>
            <p class="rzcrm-login-sub">Første gang du logger ind skal du aktivere 2FA</p>

            <div class="rzcrm-setup-steps">
                <div class="rzcrm-setup-step">
                    <span class="rzcrm-setup-num">1</span>
                    <span>Download <strong>Google Authenticator</strong> eller <strong>Authy</strong></span>
                </div>
                <div class="rzcrm-setup-step">
                    <span class="rzcrm-setup-num">2</span>
                    <span>Scan QR-koden herunder</span>
                </div>
                <div class="rzcrm-setup-step">
                    <span class="rzcrm-setup-num">3</span>
                    <span>Bekræft med din 6-cifrede kode</span>
                </div>
            </div>

            <div class="rzcrm-qr-box">
                <div class="rzcrm-qr-loading" id="rzcrm-qr-loading">Henter QR-kode…</div>
                <img id="rzcrm-qr-img" src="" alt="QR-kode" hidden>
                <div class="rzcrm-secret-wrap" id="rzcrm-secret-wrap" hidden>
                    <span class="rzcrm-secret-label">Manuel nøgle</span>
                    <code id="rzcrm-secret-code"></code>
                    <button class="rzcrm-copy-btn" id="rzcrm-copy-secret" type="button">Kopiér</button>
                </div>
            </div>

            <div class="rzcrm-field rzcrm-otp-wrap">
                <label for="rzcrm-setup-otp">Bekræft kode</label>
                <input type="text" id="rzcrm-setup-otp" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" placeholder="000000">
            </div>

            <button class="rzcrm-btn-primary" id="rzcrm-setup-confirm-btn">
                <span class="rzcrm-btn-text">Aktiver 2FA og log ind</span>
                <span class="rzcrm-btn-spinner" hidden>↻</span>
            </button>

            <button class="rzcrm-btn-ghost" id="rzcrm-setup-back-btn">← Tilbage</button>

            <div class="rzcrm-error" id="rzcrm-error-setup" hidden></div>
        </div>

    </div><!-- /.rzcrm-login-card -->

</div><!-- /.rzcrm-login-wrap -->


