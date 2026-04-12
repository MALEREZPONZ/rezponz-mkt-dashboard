<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="ai">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/Rezponz-logo.png' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">AI Synlighed</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>AI-søgemaskine Synlighed</h1>
      <p class="page-sub">Spor din synlighed i Google AI Overviews og få konkrete trin til at blive fundet oftere</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
      <button id="rzpa-ai-sync" class="btn-primary" style="gap:6px">⟳ Sync SerpAPI</button>
    </div>
  </div>

  <!-- STATUS BANNER -->
  <div id="ai-status-banner" class="rzpa-card" style="padding:0;overflow:hidden;margin-bottom:16px">
    <div style="display:grid;grid-template-columns:auto 1fr auto;gap:0;align-items:stretch">
      <!-- Score søjle -->
      <div id="ai-score-col" style="padding:24px 28px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:140px;background:rgba(255,255,255,.03);border-right:1px solid var(--border)">
        <div id="ai-score-ring" style="position:relative;width:90px;height:90px;margin-bottom:8px">
          <svg viewBox="0 0 90 90" style="transform:rotate(-90deg);width:90px;height:90px">
            <circle cx="45" cy="45" r="38" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="8"/>
            <circle id="ai-score-arc" cx="45" cy="45" r="38" fill="none" stroke="#CCFF00" stroke-width="8"
                    stroke-dasharray="239" stroke-dashoffset="239" stroke-linecap="round"
                    style="transition:stroke-dashoffset .8s ease"/>
          </svg>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
            <div id="ai-score-pct" style="font-size:22px;font-weight:800;line-height:1;color:#fff">–</div>
            <div style="font-size:9px;color:#666;text-transform:uppercase;letter-spacing:.05em">score</div>
          </div>
        </div>
        <div id="ai-score-verdict" style="font-size:11px;font-weight:600;text-align:center;padding:3px 8px;border-radius:12px;background:rgba(255,255,255,.06);color:#aaa">Indlæser…</div>
      </div>
      <!-- Status tekst -->
      <div style="padding:20px 24px;display:flex;flex-direction:column;justify-content:center;gap:6px">
        <div id="ai-status-headline" style="font-size:16px;font-weight:700;color:#fff">Analyserer data…</div>
        <div id="ai-status-sub" style="font-size:13px;color:#888;line-height:1.5"></div>
        <div id="ai-status-pills" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px"></div>
      </div>
      <!-- KPI mini-grid -->
      <div id="ai-mini-kpis" style="display:grid;grid-template-rows:1fr 1fr;border-left:1px solid var(--border);min-width:280px">
        <div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--border)">
          <div style="padding:14px 16px;border-right:1px solid var(--border)">
            <div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px">AI Overview</div>
            <div id="kpi_ai_ov" style="font-size:20px;font-weight:700;color:#CCFF00">–</div>
          </div>
          <div style="padding:14px 16px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px">Snippets</div>
            <div id="kpi_snippets" style="font-size:20px;font-weight:700;color:#60a5fa">–</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr">
          <div style="padding:14px 16px;border-right:1px solid var(--border)">
            <div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px">PAA</div>
            <div id="kpi_paa" style="font-size:20px;font-weight:700;color:#a78bfa">–</div>
          </div>
          <div style="padding:14px 16px">
            <div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px">AI nævnelser</div>
            <div id="kpi_mentioned" style="font-size:20px;font-weight:700;color:#fb923c">–</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- HANDLINGSPLAN -->
  <div class="rzpa-card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <div>
        <h2 style="margin:0;font-size:15px;font-weight:700">🎯 Din prioriterede handlingsplan</h2>
        <div class="rzpa-card-sub" style="margin-top:2px">Konkrete trin rangeret efter forventet effekt</div>
      </div>
      <span id="ai-plan-count" style="font-size:12px;color:#555"></span>
    </div>
    <div id="ai-action-plan" style="display:flex;flex-direction:column;gap:12px">
      <div class="rzpa-loading">Genererer handlingsplan…</div>
    </div>
  </div>

  <!-- SØGEORD ANALYSE -->
  <div class="rzpa-card" style="padding:0;overflow:hidden;margin-bottom:16px">
    <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <h2 style="margin:0;font-size:15px;font-weight:700">📊 Søgeord analyse</h2>
        <div class="rzpa-card-sub" style="margin-top:2px">Status og næste skridt pr. søgeord</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <span id="ai-kw-count" style="font-size:12px;color:#555"></span>
        <div id="ai-kw-filter" style="display:flex;gap:4px">
          <button onclick="rzpaAIFilter('all',this)" class="ai-filter-btn active" style="font-size:11px;padding:3px 8px;border-radius:4px;border:1px solid #333;background:#1a1a1a;color:#aaa;cursor:pointer">Alle</button>
          <button onclick="rzpaAIFilter('missing',this)" class="ai-filter-btn" style="font-size:11px;padding:3px 8px;border-radius:4px;border:1px solid #333;background:#1a1a1a;color:#aaa;cursor:pointer">Mangler</button>
          <button onclick="rzpaAIFilter('has_ai',this)" class="ai-filter-btn" style="font-size:11px;padding:3px 8px;border-radius:4px;border:1px solid #333;background:#1a1a1a;color:#aaa;cursor:pointer">Synlig</button>
        </div>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table class="rzpa-table" id="ai-kw-table" style="margin:0">
        <thead><tr>
          <th style="padding-left:20px">Søgeord</th>
          <th style="text-align:center;width:90px">AI Overview</th>
          <th style="text-align:center;width:80px">Snippet</th>
          <th style="text-align:center;width:80px">PAA</th>
          <th style="width:180px">Næste skridt</th>
          <th style="width:80px;text-align:center">Prioritet</th>
        </tr></thead>
        <tbody id="ai-kw-tbody">
          <tr><td colspan="6" class="rzpa-loading" style="padding:24px 20px">Indlæser søgeord…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 2-kolonne: AI tekst + trend -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

    <div class="rzpa-card" id="ai-overview-texts" style="display:none">
      <h2 style="margin:0 0 14px;font-size:14px;font-weight:700">📄 Hvad siger Google AI om disse søgeord?</h2>
      <div style="font-size:12px;color:#666;margin-bottom:12px">Tekst Google AI viser — er Rezponz nævnt?</div>
      <div id="ai-overview-texts-inner" style="display:flex;flex-direction:column;gap:10px;max-height:320px;overflow-y:auto"></div>
    </div>

    <div class="rzpa-card">
      <h2 style="margin:0 0 4px;font-size:14px;font-weight:700">📈 Synlighed over tid</h2>
      <div style="font-size:12px;color:#666;margin-bottom:12px">Antal søgeord med AI-dækning pr. sync</div>
      <div style="height:200px"><canvas id="chart_ai_ov"></canvas></div>
    </div>

  </div>

  <!-- MANUEL LOG -->
  <div class="rzpa-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div>
        <h2 style="margin:0;font-size:14px;font-weight:700">🤖 Manuel AI-svar log</h2>
        <div class="rzpa-card-sub">Test selv: spørg ChatGPT/Perplexity hvem der tilbyder jobs i kundeservice og log om Rezponz nævnes</div>
      </div>
      <button id="rzpa-log-toggle" class="btn-primary">+ Tilføj log</button>
    </div>
    <div id="rzpa-log-form" class="rzpa-form" style="display:none">
      <div class="rzpa-form-grid">
        <div class="rzpa-field">
          <label>Platform</label>
          <select id="log_platform">
            <option>ChatGPT</option><option>Perplexity</option><option>Gemini</option>
            <option>Claude</option><option>Copilot</option>
          </select>
        </div>
        <div class="rzpa-field">
          <label>Søgeforespørgsel *</label>
          <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px">
            <button type="button" class="rzpa-log-suggest">Deltidsjob kundeservice Danmark</button>
            <button type="button" class="rzpa-log-suggest">Kundeservice job hjemmefra</button>
            <button type="button" class="rzpa-log-suggest">Studiejob kundeservice København</button>
            <button type="button" class="rzpa-log-suggest">Hvem tilbyder jobs i kundeservice?</button>
            <button type="button" class="rzpa-log-suggest">Kundeservice outsourcing leverandører Danmark</button>
          </div>
          <input type="text" id="log_query" placeholder="fx: deltidsjob kundeservice hjemmefra Danmark" />
        </div>
      </div>
      <div class="rzpa-field" style="margin-bottom:12px">
        <label>AI-svar (uddrag)</label>
        <textarea id="log_response" placeholder="Indsæt den del af svaret der er relevant…"></textarea>
      </div>
      <div style="display:flex;gap:20px;align-items:center;margin-bottom:14px">
        <label class="rzpa-checkbox">
          <input type="checkbox" id="log_mentioned" />
          Rezponz nævnt i svaret
        </label>
        <div class="rzpa-field" style="flex:1;margin:0">
          <input type="text" id="log_notes" placeholder="Noter…" />
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button id="rzpa-log-submit" class="btn-primary">Gem log</button>
        <button onclick="document.getElementById('rzpa-log-form').style.display='none'" class="btn-ghost">Annuller</button>
      </div>
    </div>
    <div class="rzpa-table-wrap">
      <table class="rzpa-table">
        <thead><tr>
          <th>Dato</th><th>Platform</th><th>Forespørgsel</th><th>Nævnt</th><th>Noter</th><th></th>
        </tr></thead>
        <tbody id="ai_log_tbody"><tr><td colspan="6" class="rzpa-loading">Indlæser…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>
<style>
.ai-filter-btn.active { background:#1877F2 !important; border-color:#1877F2 !important; color:#fff !important; }
.ai-kw-row[data-hidden="1"] { display:none; }
.rzpa-log-suggest { font-size:10px; padding:2px 9px; border-radius:20px; border:1px solid rgba(204,255,0,.2); background:rgba(204,255,0,.05); color:#888; cursor:pointer; white-space:nowrap; transition:.15s; }
.rzpa-log-suggest:hover { border-color:rgba(204,255,0,.5); color:#CCFF00; }
</style>
<script>
function rzpaAIFilter(type, btn) {
  document.querySelectorAll('.ai-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#ai-kw-tbody tr[data-filter]').forEach(row => {
    const f = row.getAttribute('data-filter');
    row.style.display = (type === 'all' || f === type || (type === 'missing' && f !== 'has_ai')) ? '' : 'none';
  });
}
</script>
