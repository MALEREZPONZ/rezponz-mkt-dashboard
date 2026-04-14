<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<script>
window.RZPZ_CRM = window.RZPZ_CRM || {};
window.RZPZ_CRM.apiBase = <?php echo wp_json_encode( rest_url( 'rzpa/v1/' ) ); ?>;
window.RZPZ_CRM.nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
</script>
<div class="wrap rzpa-wrap" id="rzcrm-users-page">

    <div class="rzpa-page-header">
        <div>
            <h1 class="rzpa-page-title">Brugere & Sikkerhed</h1>
            <p class="rzpa-page-sub">Administrér RezCRM-brugere og overvåg adgang</p>
        </div>
        <div class="rzpa-header-actions">
            <button class="rzpa-btn rzpa-btn-primary" id="crm-create-user-btn">+ Opret bruger</button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="rzcrm-tab-nav">
        <button class="rzcrm-tab-btn rzcrm-tab-active" data-tab="users">👥 Brugere</button>
        <button class="rzcrm-tab-btn" data-tab="audit">🔍 Audit Log</button>
    </div>

    <!-- Users tab -->
    <div id="tab-users" class="rzcrm-tab-content">
        <div class="rzcrm-users-table-wrap rzpa-card">
            <table class="rzcrm-users-table" id="crm-users-table">
                <thead>
                    <tr>
                        <th>Bruger</th>
                        <th>Rolle</th>
                        <th>2FA</th>
                        <th>Sidst set</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="crm-users-body">
                    <tr><td colspan="6" class="rzcrm-loading">Henter brugere…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit log tab -->
    <div id="tab-audit" class="rzcrm-tab-content" style="display:none">
        <div class="rzpa-card">
            <div class="rzcrm-audit-filters" style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
                <select id="audit-user-filter" style="background:#0e0e0e;color:#e5e5e5;border:1px solid #2a2a2a;border-radius:6px;padding:6px 10px;font-size:13px">
                    <option value="">Alle brugere</option>
                </select>
                <select id="audit-action-filter" style="background:#0e0e0e;color:#e5e5e5;border:1px solid #2a2a2a;border-radius:6px;padding:6px 10px;font-size:13px">
                    <option value="">Alle handlinger</option>
                    <option value="login_ok">Login OK</option>
                    <option value="login_fail">Login fejl</option>
                    <option value="mfa_ok">MFA godkendt</option>
                    <option value="mfa_fail">MFA fejl</option>
                    <option value="mfa_setup">MFA opsat</option>
                    <option value="mfa_reset">MFA nulstillet</option>
                    <option value="logout">Log ud</option>
                    <option value="user_created">Bruger oprettet</option>
                    <option value="user_updated">Bruger opdateret</option>
                    <option value="user_activated">Bruger aktiveret</option>
                    <option value="user_deactivated">Bruger deaktiveret</option>
                </select>
                <button class="rzpa-btn rzpa-btn-sm" id="audit-refresh-btn" style="margin-left:auto">↻ Opdatér</button>
            </div>
            <table class="rzcrm-users-table" id="crm-audit-table">
                <thead>
                    <tr>
                        <th>Tidspunkt</th>
                        <th>Bruger</th>
                        <th>Handling</th>
                        <th>IP</th>
                        <th>Detaljer</th>
                    </tr>
                </thead>
                <tbody id="crm-audit-body">
                    <tr><td colspan="5" class="rzcrm-loading">Henter log…</td></tr>
                </tbody>
            </table>
            <div id="crm-audit-pagination" style="display:flex;gap:8px;margin-top:14px;align-items:center;justify-content:flex-end">
                <button class="rzpa-btn rzpa-btn-sm" id="audit-prev-btn" disabled>← Forrige</button>
                <span id="audit-page-info" style="font-size:13px;color:#666">Side 1</span>
                <button class="rzpa-btn rzpa-btn-sm" id="audit-next-btn">Næste →</button>
            </div>
        </div>
    </div>

</div>

<!-- Create/Edit User Modal -->
<div class="rzpa-modal-overlay" id="crm-user-modal" style="display:none">
    <div class="rzpa-modal" style="max-width:480px">
        <div class="rzpa-modal-header">
            <h3 id="crm-user-modal-title">Opret ny bruger</h3>
            <button class="rzpa-modal-close" data-modal="crm-user-modal">×</button>
        </div>
        <div class="rzpa-modal-body">
            <input type="hidden" id="cu-user-id" value="">

            <div class="rzcrm-form-row">
                <label>Brugernavn *</label>
                <input type="text" id="cu-login" placeholder="fx jensdk" autocomplete="off">
            </div>
            <div class="rzcrm-form-row">
                <label>Email *</label>
                <input type="email" id="cu-email" placeholder="navn@firma.dk">
            </div>
            <div class="rzcrm-form-row">
                <label>Navn</label>
                <input type="text" id="cu-display" placeholder="Fulde navn">
            </div>
            <div class="rzcrm-form-row" id="cu-password-row">
                <label>Adgangskode</label>
                <input type="password" id="cu-password" placeholder="Lad stå tom for auto-genereret" autocomplete="new-password">
                <small style="color:#555;font-size:12px;margin-top:4px;display:block">Sendes automatisk til brugerens email</small>
            </div>

            <div id="cu-error" class="rzcrm-form-error" hidden></div>
        </div>
        <div class="rzpa-modal-footer">
            <button class="rzpa-btn rzpa-btn-ghost" data-modal="crm-user-modal">Annullér</button>
            <button class="rzpa-btn rzpa-btn-primary" id="cu-save-btn">Opret bruger</button>
        </div>
    </div>
</div>

<!-- Confirm Toggle Active Modal -->
<div class="rzpa-modal-overlay" id="crm-toggle-active-modal" style="display:none">
    <div class="rzpa-modal" style="max-width:400px">
        <div class="rzpa-modal-header">
            <h3 id="toggle-active-title">Deaktivér bruger?</h3>
            <button class="rzpa-modal-close" data-modal="crm-toggle-active-modal">×</button>
        </div>
        <div class="rzpa-modal-body">
            <p id="toggle-active-msg" style="color:#aaa;font-size:14px;line-height:1.6"></p>
            <input type="hidden" id="toggle-active-user-id">
        </div>
        <div class="rzpa-modal-footer">
            <button class="rzpa-btn rzpa-btn-ghost" data-modal="crm-toggle-active-modal">Annullér</button>
            <button class="rzpa-btn" id="confirm-toggle-active-btn" style="background:#ff6b6b;color:#fff;border:none">Bekræft</button>
        </div>
    </div>
</div>

<!-- Confirm Reset MFA Modal -->
<div class="rzpa-modal-overlay" id="crm-reset-mfa-modal" style="display:none">
    <div class="rzpa-modal" style="max-width:400px">
        <div class="rzpa-modal-header">
            <h3>Nulstil 2FA?</h3>
            <button class="rzpa-modal-close" data-modal="crm-reset-mfa-modal">×</button>
        </div>
        <div class="rzpa-modal-body">
            <p style="color:#aaa;font-size:14px;line-height:1.6">
                Brugeren <strong id="reset-mfa-name"></strong> skal opsætte 2FA igen næste gang de logger ind.
            </p>
            <input type="hidden" id="reset-mfa-user-id">
        </div>
        <div class="rzpa-modal-footer">
            <button class="rzpa-btn rzpa-btn-ghost" data-modal="crm-reset-mfa-modal">Annullér</button>
            <button class="rzpa-btn" style="background:#ff6b6b;color:#fff;border:none" id="confirm-reset-mfa-btn">Nulstil 2FA</button>
        </div>
    </div>
</div>

<style>
/* ── Tab navigation ──────────────────────────────────── */
.rzcrm-tab-nav {
    display: flex;
    gap: 2px;
    margin-bottom: 24px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.rzcrm-tab-btn {
    padding: 10px 22px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: #666;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    transition: color .15s, border-color .15s;
    margin-bottom: -1px;
    letter-spacing: .01em;
}
.rzcrm-tab-btn:hover { color: #ccc; }
.rzcrm-tab-btn.rzcrm-tab-active { color: #CCFF00; border-bottom-color: #CCFF00; }

/* ── Header ──────────────────────────────────────────── */
.rzpa-header-actions { display: flex; align-items: center; gap: 8px; }

/* ── Users table ─────────────────────────────────────── */
.rzcrm-users-table-wrap { padding: 0 !important; overflow: hidden; }
.rzcrm-users-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rzcrm-users-table th {
    padding: 12px 20px;
    text-align: left;
    font-size: 10px;
    font-weight: 700;
    color: #444;
    text-transform: uppercase;
    letter-spacing: .08em;
    border-bottom: 1px solid rgba(255,255,255,.06);
    background: rgba(255,255,255,.02);
}
.rzcrm-users-table td {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,.04);
    vertical-align: middle;
}
.rzcrm-users-table tr:last-child td { border-bottom: none; }
.rzcrm-users-table tbody tr:hover td { background: rgba(255,255,255,.02); }
.rzcrm-loading { color: #444; text-align: center; padding: 36px !important; font-size: 13px; }

/* ── User cell ───────────────────────────────────────── */
.rzcrm-user-info { display: flex; align-items: center; gap: 12px; }
.rzcrm-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(204,255,0,.08); border: 1px solid rgba(204,255,0,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #CCFF00;
    flex-shrink: 0; letter-spacing: .02em;
}
.rzcrm-user-login { font-weight: 600; color: #e5e5e5; font-size: 13px; line-height: 1.3; }
.rzcrm-user-email { font-size: 11px; color: #555; margin-top: 2px; }

/* ── Badges ──────────────────────────────────────────── */
.rzcrm-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; padding: 3px 9px; border-radius: 999px;
    font-weight: 700; letter-spacing: .03em;
}
.rzcrm-badge-admin   { background: rgba(204,255,0,.08);  color: #CCFF00; border: 1px solid rgba(204,255,0,.2); }
.rzcrm-badge-user    { background: rgba(130,130,255,.08); color: #9090ff; border: 1px solid rgba(130,130,255,.2); }
.rzcrm-badge-active  { background: rgba(60,200,60,.08);  color: #4cd964; border: 1px solid rgba(60,200,60,.2); }
.rzcrm-badge-inactive{ background: rgba(255,60,60,.08);  color: #ff6b6b; border: 1px solid rgba(255,60,60,.2); }
.rzcrm-badge-mfa-on  { background: rgba(0,200,150,.08);  color: #00c896; border: 1px solid rgba(0,200,150,.2); }
.rzcrm-badge-mfa-off { background: rgba(255,150,0,.08);  color: #ff9500; border: 1px solid rgba(255,150,0,.2); }

/* ── Row actions ─────────────────────────────────────── */
.rzcrm-actions-cell { display: flex; gap: 6px; justify-content: flex-end; }
.rzcrm-action-btn {
    font-size: 11px; padding: 5px 12px;
    border-radius: 999px; border: 1px solid rgba(255,255,255,.1);
    background: transparent; color: #666; cursor: pointer; transition: .15s;
    white-space: nowrap; font-family: inherit; font-weight: 600;
}
.rzcrm-action-btn:hover        { border-color: rgba(255,255,255,.25); color: #ccc; }
.rzcrm-action-btn.danger:hover { border-color: rgba(255,80,80,.4); color: #ff6b6b; }
.rzcrm-action-btn.warn:hover   { border-color: rgba(255,150,0,.4); color: #ff9500; }

/* ── Modal shell ─────────────────────────────────────── */
.rzpa-modal {
    background: #141414;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 20px;
    box-shadow: 0 32px 80px rgba(0,0,0,.8);
    overflow: hidden;
}
.rzpa-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 20px 24px 18px;
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.rzpa-modal-header h3 {
    margin: 0; font-size: 16px; font-weight: 700; color: #fff;
}
.rzpa-modal-body   { padding: 22px 24px; }
.rzpa-modal-footer {
    display: flex; align-items: center; justify-content: flex-end; gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid rgba(255,255,255,.07);
    background: rgba(255,255,255,.02);
}
.rzpa-modal-close {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
    color: #888; font-size: 16px; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: .15s;
}
.rzpa-modal-close:hover { background: rgba(255,255,255,.12); color: #fff; border-color: rgba(255,255,255,.25); }

/* ── Modal form rows ─────────────────────────────────── */
.rzcrm-form-row { margin-bottom: 16px; }
.rzcrm-form-row label {
    display: block; font-size: 11px; font-weight: 700; color: #555;
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: .06em;
}
.rzcrm-form-row input, .rzcrm-form-row select {
    width: 100%; background: #0e0e0e; border: 1px solid rgba(255,255,255,.1);
    border-radius: 10px; padding: 10px 14px; font-size: 14px; color: #e5e5e5;
    outline: none; transition: border-color .15s, box-shadow .15s; font-family: inherit;
    box-sizing: border-box;
}
.rzcrm-form-row input:focus {
    border-color: #CCFF00;
    box-shadow: 0 0 0 3px rgba(204,255,0,.08);
}
.rzcrm-form-error {
    padding: 12px 16px; background: rgba(255,60,60,.06);
    border: 1px solid rgba(255,60,60,.2); border-radius: 10px;
    font-size: 13px; color: #ff6b6b; margin-top: 12px;
}

/* ── Audit table / pagination ────────────────────────── */
.rzcrm-audit-filters select {
    background: #111; color: #e5e5e5;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px; padding: 7px 12px; font-size: 13px;
    font-family: inherit; outline: none; cursor: pointer;
}
.audit-action {
    font-size: 10px; padding: 3px 9px; border-radius: 999px;
    font-weight: 700; display: inline-block; letter-spacing: .03em;
}
.audit-ok   { background: rgba(60,200,60,.08);  color: #4cd964; }
.audit-fail { background: rgba(255,60,60,.08);  color: #ff6b6b; }
.audit-info { background: rgba(130,130,255,.08); color: #9090ff; }
.audit-warn { background: rgba(255,150,0,.08);  color: #ff9500; }
</style>

<script>
(function(){
'use strict';

const apiBase = (window.RZPZ_CRM?.apiBase || '/wp-json/rzpa/v1/crm/').replace(/crm\/?$/, '');
const nonce   = window.RZPZ_CRM?.nonce || '';

async function api(path, opts = {}) {
    const res = await fetch(apiBase + path, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json', ...opts.headers },
        ...opts,
    });
    return res.json();
}

// ── Tabs ─────────────────────────────────────────────────────────────────────
document.querySelectorAll('.rzcrm-tab-btn').forEach(t => {
    t.addEventListener('click', () => {
        document.querySelectorAll('.rzcrm-tab-btn').forEach(x => x.classList.remove('rzcrm-tab-active'));
        t.classList.add('rzcrm-tab-active');
        document.querySelectorAll('.rzcrm-tab-content').forEach(c => c.style.display = 'none');
        const tab = document.getElementById('tab-' + t.dataset.tab);
        if (tab) tab.style.display = '';
        if (t.dataset.tab === 'audit') loadAudit();
    });
});

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id)  { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
document.querySelectorAll('[data-modal]').forEach(b => {
    b.addEventListener('click', () => closeModal(b.dataset.modal));
});

// ── Load users ────────────────────────────────────────────────────────────────
let allUsers = [];

async function loadUsers() {
    const tbody = document.getElementById('crm-users-body');
    tbody.innerHTML = '<tr><td colspan="6" class="rzcrm-loading">Henter brugere…</td></tr>';
    allUsers = await api('crm/users');
    renderUsers();
    populateAuditUserFilter();
}

function renderUsers() {
    const tbody = document.getElementById('crm-users-body');
    if (!allUsers.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="rzcrm-loading">Ingen brugere fundet</td></tr>';
        return;
    }
    tbody.innerHTML = allUsers.map(u => {
        const initials   = (u.display_name || u.login).substring(0,2).toUpperCase();
        const roleBadge  = u.is_admin
            ? `<span class="rzcrm-badge rzcrm-badge-admin">Admin</span>`
            : `<span class="rzcrm-badge rzcrm-badge-user">CRM Bruger</span>`;
        const mfaBadge   = u.has_mfa
            ? `<span class="rzcrm-badge rzcrm-badge-mfa-on">✓ 2FA aktiv</span>`
            : `<span class="rzcrm-badge rzcrm-badge-mfa-off">⚠ Ingen 2FA</span>`;
        const statusBadge = u.active
            ? `<span class="rzcrm-badge rzcrm-badge-active">Aktiv</span>`
            : `<span class="rzcrm-badge rzcrm-badge-inactive">Inaktiv</span>`;
        const lastLogin = u.last_login
            ? new Date(u.last_login * 1000).toLocaleString('da-DK', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
            : '—';

        return `<tr>
            <td>
                <div class="rzcrm-user-info">
                    <div class="rzcrm-avatar">${initials}</div>
                    <div>
                        <div class="rzcrm-user-login">${esc(u.login)}</div>
                        <div class="rzcrm-user-email">${esc(u.email)}</div>
                    </div>
                </div>
            </td>
            <td>${roleBadge}</td>
            <td>${mfaBadge}</td>
            <td style="color:#555;font-size:12px">${lastLogin}</td>
            <td>${statusBadge}</td>
            <td>
                <div class="rzcrm-actions-cell">
                    ${!u.is_admin ? `<button class="rzcrm-action-btn" onclick="editUser(${u.id})">Redigér</button>` : ''}
                    ${u.has_mfa ? `<button class="rzcrm-action-btn warn" onclick="promptResetMfa(${u.id},'${esc(u.login)}')">Nulstil 2FA</button>` : ''}
                    ${!u.is_admin ? `<button class="rzcrm-action-btn ${u.active ? 'danger' : ''}" onclick="promptToggleActive(${u.id},'${esc(u.login)}',${u.active})">${u.active ? 'Deaktivér' : 'Aktivér'}</button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Create / Edit user ────────────────────────────────────────────────────────
document.getElementById('crm-create-user-btn').addEventListener('click', () => {
    document.getElementById('cu-user-id').value  = '';
    document.getElementById('cu-login').value    = '';
    document.getElementById('cu-email').value    = '';
    document.getElementById('cu-display').value  = '';
    document.getElementById('cu-password').value = '';
    document.getElementById('crm-user-modal-title').textContent = 'Opret ny bruger';
    document.getElementById('cu-save-btn').textContent = 'Opret bruger';
    document.getElementById('cu-login').disabled = false;
    document.getElementById('cu-password-row').hidden = false;
    hideEl('cu-error');
    openModal('crm-user-modal');
    setTimeout(() => document.getElementById('cu-login').focus(), 80);
});

window.editUser = function(id) {
    const u = allUsers.find(x => x.id === id);
    if (!u) return;
    document.getElementById('cu-user-id').value  = u.id;
    document.getElementById('cu-login').value    = u.login;
    document.getElementById('cu-email').value    = u.email;
    document.getElementById('cu-display').value  = u.display_name;
    document.getElementById('cu-password').value = '';
    document.getElementById('crm-user-modal-title').textContent = 'Redigér bruger';
    document.getElementById('cu-save-btn').textContent = 'Gem ændringer';
    document.getElementById('cu-login').disabled = true;
    document.getElementById('cu-password-row').hidden = false;
    hideEl('cu-error');
    openModal('crm-user-modal');
};

document.getElementById('cu-save-btn').addEventListener('click', async () => {
    const userId = document.getElementById('cu-user-id').value;
    const login  = document.getElementById('cu-login').value.trim();
    const email  = document.getElementById('cu-email').value.trim();
    const name   = document.getElementById('cu-display').value.trim();
    const pass   = document.getElementById('cu-password').value;

    hideEl('cu-error');
    if (!email) { showFormError('cu-error', 'Email er påkrævet'); return; }

    const btn = document.getElementById('cu-save-btn');
    btn.disabled = true; btn.textContent = '…';

    try {
        let res;
        if (userId) {
            res = await api(`crm/users/${userId}`, { method: 'PUT', body: JSON.stringify({ email, display_name: name, password: pass || undefined }) });
        } else {
            if (!login) { showFormError('cu-error', 'Brugernavn er påkrævet'); return; }
            res = await api('crm/users', { method: 'POST', body: JSON.stringify({ login, email, display_name: name, password: pass || undefined }) });
        }

        if (res.message) {
            showFormError('cu-error', res.message);
        } else {
            closeModal('crm-user-modal');
            loadUsers();
        }
    } catch (e) {
        showFormError('cu-error', 'Netværksfejl. Prøv igen.');
    } finally {
        btn.disabled = false;
        btn.textContent = userId ? 'Gem ændringer' : 'Opret bruger';
    }
});

// ── Toggle active (with confirmation) ─────────────────────────────────────────
window.promptToggleActive = function(id, login, currentlyActive) {
    document.getElementById('toggle-active-user-id').value = id;
    const action = currentlyActive ? 'Deaktivér' : 'Aktivér';
    document.getElementById('toggle-active-title').textContent = action + ' bruger?';
    document.getElementById('toggle-active-msg').innerHTML =
        currentlyActive
            ? `Brugeren <strong>${esc(login)}</strong> vil ikke længere kunne logge ind i RezCRM.`
            : `Brugeren <strong>${esc(login)}</strong> vil igen kunne logge ind i RezCRM.`;
    const confirmBtn = document.getElementById('confirm-toggle-active-btn');
    confirmBtn.textContent = action;
    confirmBtn.style.background = currentlyActive ? '#ff6b6b' : '#CCFF00';
    confirmBtn.style.color      = currentlyActive ? '#fff'    : '#0a0a0a';
    openModal('crm-toggle-active-modal');
};

document.getElementById('confirm-toggle-active-btn').addEventListener('click', async () => {
    const id = document.getElementById('toggle-active-user-id').value;
    if (!id) return;
    await api(`crm/users/${id}`, { method: 'DELETE' });
    closeModal('crm-toggle-active-modal');
    loadUsers();
});

// ── Reset MFA ─────────────────────────────────────────────────────────────────
window.promptResetMfa = function(id, name) {
    document.getElementById('reset-mfa-user-id').value = id;
    document.getElementById('reset-mfa-name').textContent = name;
    openModal('crm-reset-mfa-modal');
};

document.getElementById('confirm-reset-mfa-btn').addEventListener('click', async () => {
    const id = document.getElementById('reset-mfa-user-id').value;
    if (!id) return;
    await api(`crm/users/${id}/reset-mfa`, { method: 'POST' });
    closeModal('crm-reset-mfa-modal');
    loadUsers();
});

// ── Audit log ─────────────────────────────────────────────────────────────────
let auditPage   = 0;
const auditLimit = 50;

function populateAuditUserFilter() {
    const sel = document.getElementById('audit-user-filter');
    const existing = new Set([...sel.options].map(o => o.value));
    allUsers.forEach(u => {
        if (!existing.has(String(u.id))) {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.login;
            sel.appendChild(opt);
        }
    });
}

async function loadAudit() {
    const tbody  = document.getElementById('crm-audit-body');
    const userId = document.getElementById('audit-user-filter').value;
    tbody.innerHTML = '<tr><td colspan="5" class="rzcrm-loading">Henter log…</td></tr>';

    const actionFilter = document.getElementById('audit-action-filter').value;
    const params = new URLSearchParams({ limit: auditLimit, offset: auditPage * auditLimit });
    if (userId)       params.append('user_id', userId);
    if (actionFilter) params.append('action', actionFilter);

    const rows = await api(`crm/audit?${params}`);

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="rzcrm-loading">Ingen log-poster</td></tr>';
        document.getElementById('audit-next-btn').disabled = true;
        return;
    }

    const actionClass = a => {
        if (a.includes('ok') || a.includes('setup') || a.includes('created') || a.includes('activated')) return 'audit-ok';
        if (a.includes('fail'))    return 'audit-fail';
        if (a.includes('deact') || a.includes('reset')) return 'audit-warn';
        return 'audit-info';
    };

    tbody.innerHTML = rows.map(r => {
        const dt = new Date(r.created_at).toLocaleString('da-DK',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
        const details = r.context ? JSON.parse(r.context) : {};
        const detailStr = Object.entries(details).map(([k,v]) => `${k}: ${v}`).join(', ');
        return `<tr>
            <td style="color:#555;font-size:12px;white-space:nowrap">${dt}</td>
            <td style="color:#aaa;font-size:13px">${esc(r.user_login || String(r.user_id))}</td>
            <td><span class="audit-action ${actionClass(r.action)}">${esc(r.action)}</span></td>
            <td style="color:#444;font-size:12px;font-family:monospace">${esc(r.ip || '')}</td>
            <td style="color:#444;font-size:12px">${esc(detailStr)}</td>
        </tr>`;
    }).join('');

    document.getElementById('audit-page-info').textContent = `Side ${auditPage + 1}`;
    document.getElementById('audit-prev-btn').disabled = auditPage === 0;
    document.getElementById('audit-next-btn').disabled = rows.length < auditLimit;
}

document.getElementById('audit-refresh-btn').addEventListener('click', () => { auditPage = 0; loadAudit(); });
document.getElementById('audit-user-filter').addEventListener('change', () => { auditPage = 0; loadAudit(); });
document.getElementById('audit-action-filter').addEventListener('change', () => { auditPage = 0; loadAudit(); });
document.getElementById('audit-prev-btn').addEventListener('click', () => { auditPage = Math.max(0, auditPage-1); loadAudit(); });
document.getElementById('audit-next-btn').addEventListener('click', () => { auditPage++; loadAudit(); });

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function hideEl(id) { const el = document.getElementById(id); if (el) el.hidden = true; }
function showFormError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.hidden = false;
}

// ── Init ──────────────────────────────────────────────────────────────────────
loadUsers();

})();
</script>
