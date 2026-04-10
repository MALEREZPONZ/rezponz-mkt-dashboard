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

  <!-- ── Tips-sektion ───────────────────────────────────────────── -->
  <div class="rzpa-card">
    <div class="rzpa-card-header">
      <span class="rzpa-card-title">💡 Anbefalinger</span>
    </div>
    <div id="rekrut-tips" style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">
      <div class="rzpa-loading">⏳ Analyserer…</div>
    </div>
  </div>

</div>

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
