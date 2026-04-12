<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rzpa-wrap" id="page-rekruttering">

  <!-- ── Header ─────────────────────────────────────────────────── -->
  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">👥 Rekruttering</h1>
      <p class="rzpa-page-sub">Ansøgninger, kanalperformance og pipeline — samlet overblik</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <select id="rekrut-days" class="rzpa-select">
        <option value="7">Seneste 7 dage</option>
        <option value="30" selected>Seneste 30 dage</option>
        <option value="90">Seneste 90 dage</option>
      </select>
      <button class="rzpa-btn" id="rekrut-refresh">⟳ Opdater</button>
    </div>
  </div>

  <!-- ── KPI bar ────────────────────────────────────────────────── -->
  <div class="rzpa-kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="rzpa-kpi-card">
      <div class="kpi-label">Ansøgninger i alt</div>
      <div class="kpi-value" id="rekrut-kpi-leads">–</div>
      <div class="kpi-sub" id="rekrut-kpi-leads-sub">Meta + Google</div>
    </div>
    <div class="rzpa-kpi-card">
      <div class="kpi-label">Kostpris pr. ansøgning</div>
      <div class="kpi-value" id="rekrut-kpi-cpl">–</div>
      <div class="kpi-sub">Samlet CPL</div>
    </div>
    <div class="rzpa-kpi-card">
      <div class="kpi-label">Rekrutteringsspend</div>
      <div class="kpi-value" id="rekrut-kpi-spend">–</div>
      <div class="kpi-sub" id="rekrut-kpi-spend-sub">Meta + Google</div>
    </div>
  </div>

  <!-- ── Kanalfordeling ───────────────────────────────────────── -->
  <div class="rzpa-card" style="margin-bottom:24px">
    <div class="rzpa-card-header">
      <span class="rzpa-card-title">📊 Kanalfordeling</span>
    </div>
    <div id="rekrut-channels" style="padding:20px 28px">
      <div class="rzpa-loading">⏳ Henter data…</div>
    </div>
  </div>

  <!-- ── Kampagnetabel ──────────────────────────────────────────── -->
  <div class="rzpa-card" style="margin-bottom:24px">
    <div class="rzpa-card-header" style="justify-content:space-between">
      <span class="rzpa-card-title">📋 Rekrutteringskampagner</span>
      <div style="display:flex;gap:8px">
        <button class="rzpa-filter-btn active" data-rekrut-filter="all">Alle</button>
        <button class="rzpa-filter-btn" data-rekrut-filter="meta">Meta</button>
        <button class="rzpa-filter-btn" data-rekrut-filter="google">Google</button>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table class="rzpa-table">
        <thead>
          <tr>
            <th>Kampagne</th>
            <th>Kanal</th>
            <th style="text-align:right">Spend</th>
            <th style="text-align:right">Ansøgninger</th>
            <th style="text-align:right">CPL</th>
            <th style="text-align:right">Klik</th>
            <th style="text-align:right">CTR</th>
            <th>Vurdering</th>
          </tr>
        </thead>
        <tbody id="rekrut-campaigns-tbody">
          <tr><td colspan="8" class="rzpa-empty">⏳ Henter kampagner…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── AI Rapport ─────────────────────────────────────────────── -->
  <div class="rzpa-card" style="margin-bottom:24px">
    <div class="rzpa-card-header" style="justify-content:space-between">
      <span class="rzpa-card-title">🤖 AI Rekrutteringsanalyse</span>
      <button class="rzpa-btn rzpa-btn-sm" id="rekrut-ai-report-btn" style="font-size:12px">✨ Generer rapport</button>
    </div>
    <div id="rekrut-ai-report" style="padding:16px 20px">
      <div style="color:#555;font-size:13px">Klik "Generer rapport" for at få AI-baserede anbefalinger til din rekruttering.</div>
    </div>
  </div>

  <!-- ── Jobopslag Generator ──────────────────────────────────────── -->
  <div class="rzpa-card" style="margin-bottom:24px">
    <div class="rzpa-card-header">
      <span class="rzpa-card-title">✍️ Jobopslag Generator</span>
      <span style="font-size:11px;color:#555;margin-left:8px">Generer Meta + Google-annoncer med AI</span>
    </div>
    <div style="padding:20px 24px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="rzpa-label">Stillingsbetegnelse</label>
          <input type="text" id="jobad-role" class="rzpa-input" placeholder="fx Kundeservicemedarbejder" />
        </div>
        <div>
          <label class="rzpa-label">Lokation</label>
          <select id="jobad-location" class="rzpa-select" style="width:100%">
            <option value="Aalborg">Aalborg</option>
            <option value="Remote">Remote / Hjemmearbejde</option>
            <option value="Aalborg eller Remote">Aalborg eller Remote</option>
            <option value="Hele Danmark">Hele Danmark</option>
          </select>
        </div>
        <div>
          <label class="rzpa-label">Tone</label>
          <select id="jobad-tone" class="rzpa-select" style="width:100%">
            <option value="professionel og imødekommende">Professionel og imødekommende</option>
            <option value="energisk og uformel">Energisk og uformel</option>
            <option value="direkte og faktuel">Direkte og faktuel</option>
          </select>
        </div>
        <div>
          <label class="rzpa-label">Fordele (én per linje)</label>
          <textarea id="jobad-points" class="rzpa-input" rows="3" placeholder="Fleksible arbejdstider&#10;Godt fællesskab&#10;Karrieremuligheder" style="resize:vertical"></textarea>
        </div>
      </div>
      <button class="rzpa-btn" id="jobad-generate-btn">✨ Generer jobopslag</button>
    </div>
    <div id="jobad-result" style="display:none;border-top:1px solid var(--border);padding:20px 24px">
    </div>
  </div>

  <!-- ── Tips-sektion (statisk fallback) ─────────────────────────── -->
  <div class="rzpa-card">
    <div class="rzpa-card-header">
      <span class="rzpa-card-title">💡 Hurtige observationer</span>
    </div>
    <div id="rekrut-tips" style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">
      <div class="rzpa-loading">⏳ Analyserer…</div>
    </div>
  </div>

</div>

<style>
.rzpa-label { display:block;font-size:11px;color:#888;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px }
.rzpa-input { width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:#e5e5e5;font-size:13px;padding:8px 12px;box-sizing:border-box }
.rzpa-input:focus { outline:none;border-color:var(--neon) }
.rzpa-btn-sm { padding:4px 12px;font-size:11px }
.jobad-copy-btn { background:transparent;border:1px solid var(--border);border-radius:6px;color:#888;font-size:11px;padding:3px 10px;cursor:pointer;transition:all .15s }
.jobad-copy-btn:hover { border-color:var(--neon);color:var(--neon) }
.jobad-field { background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:10px }
.jobad-field-label { font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center }
.jobad-field-value { font-size:13px;color:#e5e5e5;line-height:1.5 }
.ai-report-card { background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:14px 16px;display:flex;gap:12px;align-items:flex-start }
.ai-report-card.high { border-left:3px solid #ef4444 }
.ai-report-card.middel { border-left:3px solid #f59e0b }
.ai-report-card.lav { border-left:3px solid #4ade80 }
</style>

<style>
.rzpa-filter-btn {
  font-size:11px;padding:3px 10px;border-radius:10px;border:1px solid var(--border);
  background:transparent;color:#666;cursor:pointer;transition:all .15s;
}
.rzpa-filter-btn.active, .rzpa-filter-btn:hover {
  background:var(--neon);color:#000;border-color:var(--neon);
}
.pipeline-input {
  width:56px;text-align:center;background:rgba(255,255,255,.05);border:1px solid var(--border);
  border-radius:6px;color:#e5e5e5;font-size:13px;padding:4px 6px;
}
.pipeline-input:focus { outline:none;border-color:var(--neon); }
.channel-bar-wrap { display:flex;align-items:center;gap:10px;margin-bottom:12px }
.channel-bar-bg { flex:1;height:8px;background:rgba(255,255,255,.06);border-radius:4px;overflow:hidden }
.channel-bar-fill { height:100%;border-radius:4px;transition:width .6s ease }
</style>
