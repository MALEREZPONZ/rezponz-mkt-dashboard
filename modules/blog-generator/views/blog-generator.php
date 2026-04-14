<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rzpa-wrap" id="rzpa-blog-gen-app">
<style>
#rzpa-blog-gen-app {
  --neon: #CCFF00;
  --bg: #111;
  --card: #1a1a1a;
  --border: rgba(255,255,255,.08);
  --text: #e0e0e0;
  --muted: #666;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: var(--text);
  padding: 24px 20px;
  max-width: 1200px;
}
.bg-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
.bg-header h1 { font-size:22px; font-weight:700; margin:0; color:#fff; }
.bg-header-actions { display:flex; gap:8px; flex-wrap:wrap; }

/* Kort-grid */
.bg-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
@media(max-width:900px){ .bg-grid { grid-template-columns:1fr; } }
.bg-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:20px; }
.bg-card h3 { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin:0 0 16px; }

/* Knapper */
.bg-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; transition:.15s; }
.bg-btn-primary { background:var(--neon); color:#111; }
.bg-btn-primary:hover { background:#d4ff1a; }
.bg-btn-ghost { background:transparent; color:var(--neon); border:1px solid rgba(204,255,0,.3); }
.bg-btn-ghost:hover { border-color:rgba(204,255,0,.7); }
.bg-btn-danger { background:transparent; color:#ff5555; border:1px solid rgba(255,85,85,.3); }
.bg-btn-danger:hover { border-color:#ff5555; }
.bg-btn-sm { padding:4px 10px; font-size:12px; }
.bg-btn:disabled { opacity:.4; cursor:not-allowed; }
.bg-btn-surprise { background:linear-gradient(135deg,var(--neon),#00e5ff); color:#111; font-size:14px; padding:10px 20px; }

/* Form */
.bg-form-row { display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.bg-form-row input, .bg-form-row select, .bg-form-row textarea {
  background:#222; border:1px solid var(--border); border-radius:8px;
  color:var(--text); padding:8px 12px; font-size:13px; outline:none; flex:1; min-width:0;
}
.bg-form-row input:focus, .bg-form-row select:focus, .bg-form-row textarea:focus {
  border-color:rgba(204,255,0,.4);
}
.bg-form-row select option { background:#222; }
.bg-label { font-size:11px; color:var(--muted); margin-bottom:4px; }

/* Emne-tabel */
.bg-table { width:100%; border-collapse:collapse; font-size:13px; }
.bg-table th { color:var(--muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; padding:8px 10px; text-align:left; border-bottom:1px solid var(--border); }
.bg-table td { padding:10px 10px; border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; }
.bg-table tr:last-child td { border-bottom:none; }
.bg-table tr:hover td { background:rgba(255,255,255,.02); }

/* Status badges */
.bg-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
.bg-badge-queued    { background:rgba(255,255,255,.08); color:#aaa; }
.bg-badge-generating{ background:rgba(204,255,0,.15); color:var(--neon); }
.bg-badge-done      { background:rgba(74,222,128,.15); color:#4ade80; }
.bg-badge-failed    { background:rgba(255,85,85,.15); color:#ff5555; }

/* Søjle-tags */
.bg-pillar { font-size:11px; padding:2px 7px; border-radius:20px; background:rgba(255,255,255,.07); color:#bbb; }

/* Billede-picker */
.bg-image-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:6px; max-height:240px; overflow-y:auto; margin-top:8px; }
.bg-image-item { cursor:pointer; border-radius:6px; overflow:hidden; border:2px solid transparent; transition:.15s; aspect-ratio:1; }
.bg-image-item img { width:100%; height:100%; object-fit:cover; display:block; }
.bg-image-item.selected { border-color:var(--neon); }
.bg-image-item:hover { border-color:rgba(204,255,0,.5); }

/* Suggest chips */
.bg-suggest-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.bg-suggest-chip { font-size:12px; padding:4px 10px; border-radius:20px; border:1px solid rgba(204,255,0,.2); background:rgba(204,255,0,.05); color:#aaa; cursor:pointer; transition:.15s; }
.bg-suggest-chip:hover { border-color:rgba(204,255,0,.5); color:var(--neon); }

/* Progress spinner */
.bg-spinner { display:inline-block; width:14px; height:14px; border:2px solid rgba(204,255,0,.2); border-top-color:var(--neon); border-radius:50%; animation:bg-spin .7s linear infinite; vertical-align:middle; }
@keyframes bg-spin { to { transform:rotate(360deg); } }

/* Tomt state */
.bg-empty { text-align:center; padding:40px 20px; color:var(--muted); }

/* Tabs */
.bg-tabs { display:flex; gap:0; border-bottom:1px solid var(--border); margin-bottom:20px; }
.bg-tab { padding:8px 18px; font-size:13px; font-weight:600; color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; transition:.15s; }
.bg-tab.active { color:var(--neon); border-bottom-color:var(--neon); }

/* Kalender */
.bg-calendar { user-select:none; }
.bg-cal-nav { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.bg-cal-nav h2 { font-size:16px; font-weight:700; color:#fff; margin:0; min-width:160px; text-align:center; }
.bg-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
.bg-cal-day-name { text-align:center; font-size:11px; color:var(--muted); font-weight:600; padding:6px 0; text-transform:uppercase; letter-spacing:.05em; }
.bg-cal-cell { min-height:88px; background:rgba(255,255,255,.03); border:1px solid var(--border); border-radius:8px; padding:6px; font-size:11px; }
.bg-cal-cell.today { border-color:rgba(204,255,0,.3); background:rgba(204,255,0,.04); }
.bg-cal-cell.other-month { opacity:.35; }
.bg-cal-num { font-size:12px; font-weight:600; color:var(--muted); margin-bottom:4px; }
.bg-cal-event { background:rgba(204,255,0,.12); color:var(--neon); border-radius:4px; padding:2px 5px; margin-bottom:2px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:11px; border-left:2px solid var(--neon); }
.bg-cal-event.done { background:rgba(74,222,128,.1); color:#4ade80; border-left-color:#4ade80; }
.bg-cal-event.failed { background:rgba(255,85,85,.1); color:#ff5555; border-left-color:#ff5555; }
.bg-cal-event.generating { background:rgba(204,255,0,.2); animation:bg-pulse 1.5s ease-in-out infinite; }
@keyframes bg-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

/* Toggles */
.bg-toggles { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
.bg-toggle { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text); cursor:pointer; }
.bg-toggle input[type=checkbox] { accent-color:var(--neon); width:14px; height:14px; }

/* Datetime input */
input[type="datetime-local"] { background:#222; border:1px solid var(--border); border-radius:8px; color:var(--text); padding:8px 12px; font-size:13px; outline:none; }
input[type="datetime-local"]:focus { border-color:rgba(204,255,0,.4); }
input[type="datetime-local"]::-webkit-calendar-picker-indicator { filter:invert(.6); cursor:pointer; }

/* Notifikation */
.bg-toast { position:fixed; bottom:24px; right:24px; background:#1e1e1e; border:1px solid var(--border); border-radius:10px; padding:12px 18px; font-size:13px; color:var(--text); z-index:99999; box-shadow:0 4px 24px rgba(0,0,0,.5); opacity:0; transform:translateY(10px); transition:.2s; pointer-events:none; }
.bg-toast.show { opacity:1; transform:translateY(0); }
.bg-toast.ok   { border-color:rgba(74,222,128,.4); }
.bg-toast.err  { border-color:rgba(255,85,85,.4); }
</style>

<!-- Toast -->
<div class="bg-toast" id="bg-toast"></div>

<!-- Header -->
<div class="bg-header">
  <div>
    <h1>✍️ Blog Generator</h1>
    <div style="font-size:13px;color:var(--muted);margin-top:4px">Generer Rezponz-fokuserede blogindlæg med AI — publiker direkte som udkast</div>
  </div>
  <div class="bg-header-actions">
    <button class="bg-btn bg-btn-surprise" id="bg-surprise-btn">🎲 Overrask mig</button>
    <button class="bg-btn bg-btn-primary" id="bg-add-btn">+ Nyt emne</button>
  </div>
</div>

<!-- Tabs -->
<div class="bg-tabs">
  <div class="bg-tab active" data-tab="queue">Kø <span id="bg-queue-count" style="color:var(--muted);font-weight:400"></span></div>
  <div class="bg-tab" data-tab="done">Publiceret <span id="bg-done-count" style="color:var(--muted);font-weight:400"></span></div>
  <div class="bg-tab" data-tab="calendar">📅 Kalender</div>
  <div class="bg-tab" data-tab="settings">⚙️ Indstillinger</div>
</div>

<!-- === TAB: KØ === -->
<div id="tab-queue">

  <!-- Tilføj nyt emne -->
  <div class="bg-card" id="bg-add-form" style="display:none;margin-bottom:20px">
    <h3>Nyt emne</h3>
    <div class="bg-form-row">
      <input type="text" id="bg-new-title" placeholder="Titel / emne — fx: Studiejob i Aalborg hvad tjener du?" style="flex:3">
    </div>
    <div class="bg-form-row">
      <input type="text" id="bg-new-keywords" placeholder="Søgeord (valgfrit)" style="flex:2">
      <select id="bg-new-pillar">
        <?php foreach ( RZPA_Blog_Gen_DB::PILLARS as $k => $v ) : ?>
          <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
      </select>
      <select id="bg-new-type">
        <?php foreach ( RZPA_Blog_Gen_DB::ARTICLE_TYPES as $k => $v ) : ?>
          <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
      </select>
      <select id="bg-new-target">
        <?php foreach ( RZPA_Blog_Gen_DB::TARGETS as $k => $v ) : ?>
          <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="bg-form-row">
      <select id="bg-new-words">
        <option value="800">~800 ord (kort)</option>
        <option value="1200" selected>~1.200 ord (standard)</option>
        <option value="1600">~1.600 ord (lang)</option>
        <option value="2000">~2.000 ord (dyb)</option>
      </select>
    </div>

    <!-- Indholdsvalg + publicering -->
    <div style="margin:8px 0 4px">
      <div class="bg-label">Indhold &amp; funktioner</div>
      <div class="bg-toggles">
        <label class="bg-toggle"><input type="checkbox" id="bg-new-faq" checked> FAQ-sektion</label>
        <label class="bg-toggle"><input type="checkbox" id="bg-new-toc"> Indholdsfortegnelse (TOC)</label>
        <label class="bg-toggle"><input type="checkbox" id="bg-new-tldr"> TL;DR-boks</label>
        <label class="bg-toggle"><input type="checkbox" id="bg-new-internal-links" checked> Interne links</label>
      </div>
    </div>

    <div style="margin:8px 0 4px">
      <div class="bg-label">Publiceringstilstand</div>
      <div class="bg-toggles" style="margin-bottom:6px">
        <label class="bg-toggle"><input type="checkbox" id="bg-new-publish-now"> Publiker direkte (ellers udkast)</label>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <div class="bg-label" style="margin:0;white-space:nowrap">⏰ Planlagt dato/tid:</div>
        <input type="datetime-local" id="bg-new-scheduled" title="Lad stå tomt for at generere nu">
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:4px">Planlagter du en dato, genereres artiklen automatisk på det tidspunkt.</div>
    </div>

    <div style="display:flex;gap:8px;margin-top:10px">
      <button class="bg-btn bg-btn-primary" id="bg-save-new">Tilføj til kø</button>
      <button class="bg-btn bg-btn-ghost" id="bg-cancel-add">Annuller</button>
    </div>
  </div>

  <!-- AI Forslagsmotor -->
  <div class="bg-card" style="margin-bottom:20px">
    <h3>🤖 AI Forslagsmotor</h3>
    <div class="bg-form-row">
      <input type="text" id="bg-suggest-kw" placeholder="Søgeord (valgfrit) — fx: deltidsjob kundeservice" style="flex:3">
      <select id="bg-suggest-target">
        <?php foreach ( RZPA_Blog_Gen_DB::TARGETS as $k => $v ) : ?>
          <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="bg-btn bg-btn-ghost" id="bg-suggest-btn">✨ Foreslå emner</button>
    </div>
    <div class="bg-suggest-chips" id="bg-suggest-results"></div>
  </div>

  <!-- Emne-tabel -->
  <div class="bg-card" style="padding:0;overflow:hidden">
    <div id="bg-topics-wrap" style="padding:0">
      <div class="bg-empty" id="bg-topics-loading">
        <div class="bg-spinner" style="width:24px;height:24px;border-width:3px"></div>
      </div>
      <table class="bg-table" id="bg-topics-table" style="display:none">
        <thead>
          <tr>
            <th style="width:36px"></th>
            <th>Emne</th>
            <th>Søjle</th>
            <th>Type</th>
            <th>Målgruppe</th>
            <th>Billede</th>
            <th>Planlagt</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="bg-topics-body"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- === TAB: PUBLICERET === -->
<div id="tab-done" style="display:none">
  <div class="bg-card" style="padding:0;overflow:hidden">
    <table class="bg-table" id="bg-done-table">
      <thead>
        <tr>
          <th>Emne</th>
          <th>Søjle</th>
          <th>WP Udkast</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="bg-done-body">
        <tr><td colspan="4"><div class="bg-empty">Ingen artikler genereret endnu</div></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- === TAB: INDSTILLINGER === -->
<div id="tab-settings" style="display:none">
  <div class="bg-grid">

    <div class="bg-card">
      <h3>Brand Voice Prompt</h3>
      <p style="font-size:12px;color:var(--muted);margin:0 0 10px">Denne tekst sendes til AI ved hvert blogindlæg. Tilpas tone, stil og fokusemner.</p>
      <textarea id="bg-brand-voice" rows="14" style="width:100%;font-size:12px;font-family:monospace;background:#111;border:1px solid var(--border);border-radius:8px;color:#ccc;padding:10px;resize:vertical"></textarea>
      <button class="bg-btn bg-btn-primary" id="bg-save-brand-voice" style="margin-top:10px">Gem brand voice</button>
    </div>

    <div class="bg-card">
      <h3>Standard indstillinger</h3>

      <div style="margin-bottom:16px">
        <div class="bg-label">Standard WordPress-kategori</div>
        <select id="bg-default-cat" style="width:100%;background:#222;border:1px solid var(--border);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px"></select>
      </div>

      <div style="margin-bottom:16px">
        <div class="bg-label">Standard artikel-type</div>
        <select id="bg-default-type" style="width:100%;background:#222;border:1px solid var(--border);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px">
          <?php foreach ( RZPA_Blog_Gen_DB::ARTICLE_TYPES as $k => $v ) : ?>
            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-bottom:16px">
        <div class="bg-label">Standard ord-antal</div>
        <select id="bg-default-words" style="width:100%;background:#222;border:1px solid var(--border);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px">
          <option value="800">~800 ord</option>
          <option value="1200" selected>~1.200 ord</option>
          <option value="1600">~1.600 ord</option>
          <option value="2000">~2.000 ord</option>
        </select>
      </div>

      <button class="bg-btn bg-btn-primary" id="bg-save-settings">Gem indstillinger</button>
    </div>

    <div class="bg-card" style="grid-column:1/-1">
      <h3>📸 Foretrukne billeder til artikler</h3>
      <p style="font-size:12px;color:var(--muted);margin:0 0 10px">Vælg billeder fra dit Mediebibliotek — disse bruges som standard featured image. Klik for at vælge/fravælge.</p>
      <div class="bg-image-grid" id="bg-settings-media-grid">
        <div style="text-align:center;padding:20px;color:var(--muted)"><div class="bg-spinner"></div></div>
      </div>
    </div>

  </div>
</div>

<!-- === TAB: KALENDER === -->
<div id="tab-calendar" style="display:none">
  <div class="bg-card bg-calendar">
    <div class="bg-cal-nav">
      <button class="bg-btn bg-btn-ghost bg-btn-sm" id="bg-cal-prev">◀</button>
      <h2 id="bg-cal-title">— —</h2>
      <button class="bg-btn bg-btn-ghost bg-btn-sm" id="bg-cal-next">▶</button>
      <button class="bg-btn bg-btn-ghost bg-btn-sm" id="bg-cal-today" style="margin-left:8px">I dag</button>
    </div>
    <div class="bg-cal-grid" id="bg-cal-grid">
      <!-- ugedage headers -->
      <?php foreach (['Man','Tir','Ons','Tor','Fre','Lør','Søn'] as $d): ?>
        <div class="bg-cal-day-name"><?php echo $d; ?></div>
      <?php endforeach; ?>
      <!-- dage udfyldes af JS -->
    </div>
    <div style="margin-top:14px;font-size:12px;color:var(--muted);display:flex;gap:16px;flex-wrap:wrap">
      <span><span style="color:var(--neon)">▌</span> Planlagt / Kø</span>
      <span><span style="color:#4ade80">▌</span> Publiceret</span>
      <span><span style="color:#ff5555">▌</span> Fejlet</span>
      <span><span style="color:var(--neon);opacity:.6">▌ ◌</span> Genererer</span>
    </div>
  </div>
</div>

<!-- Billede-picker modal (inline i tabel-rækker) -->
<div id="bg-image-picker-wrap" style="display:none;position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,.8);display:flex;align-items:center;justify-content:center">
  <div style="background:#1a1a1a;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:24px;width:580px;max-width:95vw;max-height:80vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0;color:#fff;font-size:16px">Vælg billede</h3>
      <button id="bg-image-picker-close" class="bg-btn bg-btn-ghost bg-btn-sm">✕ Luk</button>
    </div>
    <div class="bg-image-grid" id="bg-picker-grid" style="max-height:none;grid-template-columns:repeat(4,1fr)"></div>
    <div style="margin-top:16px;display:flex;gap:8px">
      <button class="bg-btn bg-btn-primary" id="bg-image-picker-confirm">Vælg billede</button>
      <button class="bg-btn bg-btn-ghost" id="bg-image-picker-clear">Intet billede</button>
    </div>
  </div>
</div>

</div><!-- /#rzpa-blog-gen-app -->
