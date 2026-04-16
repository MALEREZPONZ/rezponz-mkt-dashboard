<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
?>
<div class="wrap rzpz-crm-wrap" id="rzpz-crm-app">

  <!-- Toast -->
  <div id="crm-toast" class="crm-toast"></div>

  <!-- ── Header ──────────────────────────────────────────────────────────── -->
  <div class="crm-header">
    <div class="crm-header-left">
      <h1>🎯 RezCRM</h1>
      <span class="crm-version">Rekrutteringssystem</span>
    </div>
    <div class="crm-header-right">
      <select id="crm-position-filter" class="crm-select">
        <option value="">Alle stillinger</option>
      </select>
      <input type="search" id="crm-search" class="crm-search" placeholder="Søg ansøger…">
      <button class="crm-btn crm-btn-primary" id="crm-new-app-btn">+ Ny ansøgning</button>
      <button class="crm-btn crm-btn-ghost" id="crm-positions-btn" style="display:none">Stillinger</button>
      <button class="crm-btn crm-btn-ghost" id="crm-templates-btn">Skabeloner</button>
    </div>
  </div>

  <!-- ── KPI Strip ────────────────────────────────────────────────────────── -->
  <div class="crm-kpi-strip" id="crm-kpi-strip">
    <div class="crm-kpi">
      <span class="crm-kpi-val" id="kpi-total">–</span>
      <span class="crm-kpi-label">Ansøgninger</span>
    </div>
    <div class="crm-kpi">
      <span class="crm-kpi-val" id="kpi-samtale">–</span>
      <span class="crm-kpi-label">Til samtale</span>
    </div>
    <div class="crm-kpi">
      <span class="crm-kpi-val" id="kpi-ansat">–</span>
      <span class="crm-kpi-label">Ansat</span>
    </div>
    <div class="crm-kpi crm-kpi-neon">
      <span class="crm-kpi-val" id="kpi-conversion">–%</span>
      <span class="crm-kpi-label">Konvertering</span>
    </div>
  </div>

  <!-- ── Tab bar ──────────────────────────────────────────────────────────── -->
  <div class="crm-tabs">
    <button class="crm-tab crm-tab-active" data-tab="kanban">🗂 Kanban</button>
    <button class="crm-tab" data-tab="list">📋 Liste</button>
    <button class="crm-tab" data-tab="calendar">📅 Kalender</button>
    <button class="crm-tab" data-tab="stillinger">💼 Stillinger</button>
  </div>

  <!-- ── Kanban Board ─────────────────────────────────────────────────────── -->
  <div id="tab-kanban" class="crm-tab-panel crm-tab-panel-active">
    <div class="crm-kanban" id="crm-kanban">
      <!-- Kolonner genereres af JS -->
    </div>
  </div>

  <!-- ── List View ────────────────────────────────────────────────────────── -->
  <div id="tab-list" class="crm-tab-panel" style="display:none">
    <div class="crm-list-toolbar">
      <select id="list-stage-filter" class="crm-select">
        <option value="">Alle faser</option>
        <option value="ny">Ny</option>
        <option value="screening">Screening</option>
        <option value="samtale">Samtale</option>
        <option value="tilbud">Tilbud</option>
        <option value="ansat">Ansat</option>
        <option value="afslag">Afslag</option>
      </select>
    </div>
    <table class="crm-table" id="crm-list-table">
      <thead>
        <tr>
          <th>Navn</th><th>Email</th><th>Stilling</th><th>Fase</th><th>Kilde</th><th>Dato</th><th>Rating</th><th></th>
        </tr>
      </thead>
      <tbody id="crm-list-tbody">
        <tr><td colspan="8" style="text-align:center;color:var(--crm-muted)">Indlæser…</td></tr>
      </tbody>
    </table>
  </div>

  <!-- ── Calendar ─────────────────────────────────────────────────────────── -->
  <div id="tab-calendar" class="crm-tab-panel" style="display:none">
    <div class="crm-calendar-wrap">
      <div class="crm-cal-header">
        <button id="crm-cal-prev" class="crm-btn-icon">‹</button>
        <span id="crm-cal-month-label"></span>
        <button id="crm-cal-next" class="crm-btn-icon">›</button>
      </div>
      <div class="crm-cal-grid" id="crm-cal-grid"></div>
      <div class="crm-cal-detail" id="crm-cal-detail" style="display:none">
        <h4 id="crm-cal-detail-title"></h4>
        <div id="crm-cal-detail-list"></div>
      </div>
    </div>
  </div>

  <!-- ── Stillinger Tab ────────────────────────────────────────────────────── -->
  <div id="tab-stillinger" class="crm-tab-panel" style="display:none">

    <!-- Positions list view -->
    <div id="crm-pos-tab-list">
      <div class="crm-pos-tab-header">
        <div>
          <h2 class="crm-pos-tab-title">Stillinger</h2>
          <p class="crm-pos-tab-sub">Overblik over alle jobopslag og ansøgninger</p>
        </div>
        <button class="crm-btn crm-btn-primary" id="crm-pos-tab-new-btn">+ Opret stilling</button>
      </div>
      <div class="crm-pos-tab-filters">
        <div class="crm-pos-filter-group">
          <button class="crm-pos-filter-btn crm-pos-filter-active" data-pos-status="">Alle</button>
          <button class="crm-pos-filter-btn" data-pos-status="open">Åbne</button>
          <button class="crm-pos-filter-btn" data-pos-status="draft">Kladder</button>
          <button class="crm-pos-filter-btn" data-pos-status="closed">Lukkede</button>
        </div>
        <div class="crm-pos-filter-sep"></div>
        <div class="crm-pos-filter-group">
          <button class="crm-pos-type-btn crm-pos-type-active" data-pos-type="">Alle typer</button>
          <?php foreach ( RZPZ_CRM_DB::JOB_TYPES as $key => $label ) : ?>
            <button class="crm-pos-type-btn" data-pos-type="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div id="crm-pos-tab-grid"></div>
    </div>

    <!-- Position detail view (hidden initially) -->
    <div id="crm-pos-tab-detail" style="display:none">
      <div class="crm-pos-detail-header">
        <button class="crm-btn crm-btn-ghost crm-pos-back-btn" id="crm-pos-back-btn">← Alle stillinger</button>
        <div class="crm-pos-detail-title-wrap" id="crm-pos-detail-title-wrap"></div>
        <div class="crm-pos-detail-actions" id="crm-pos-detail-actions"></div>
      </div>
      <div class="crm-pos-detail-layout">
        <div class="crm-pos-sidebar" id="crm-pos-sidebar"></div>
        <div class="crm-pos-candidates" id="crm-pos-candidates"></div>
      </div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════
       MODALS
  ════════════════════════════════════════════════════════════════════════ -->

  <!-- ── Ansøgning-modal (opret + detaljer) ─────────────────────────────── -->
  <div id="crm-app-modal" class="crm-modal" style="display:none">
    <div class="crm-modal-inner crm-modal-wide">
      <div class="crm-modal-header">
        <h2 id="crm-app-modal-title">Ny ansøgning</h2>
        <button class="crm-modal-close" data-close="crm-app-modal">✕</button>
      </div>
      <div class="crm-modal-body" id="crm-app-modal-body">
        <!-- Opret-form -->
        <div id="crm-app-create-form">
          <div class="crm-form-grid">
            <div class="crm-field">
              <label>Fornavn *</label>
              <input type="text" id="app-first-name" class="crm-input" placeholder="Maria">
            </div>
            <div class="crm-field">
              <label>Efternavn *</label>
              <input type="text" id="app-last-name" class="crm-input" placeholder="Hansen">
            </div>
            <div class="crm-field">
              <label>Email *</label>
              <input type="email" id="app-email" class="crm-input" placeholder="maria@example.com">
            </div>
            <div class="crm-field">
              <label>Telefon</label>
              <input type="tel" id="app-phone" class="crm-input" placeholder="+45 12 34 56 78">
            </div>
            <div class="crm-field">
              <label>Stilling</label>
              <select id="app-position" class="crm-select"></select>
            </div>
            <div class="crm-field">
              <label>Kilde</label>
              <select id="app-source" class="crm-select"></select>
            </div>
          </div>
          <div class="crm-field crm-field-full">
            <label>Ansøgningstekst</label>
            <textarea id="app-cover" class="crm-textarea" rows="4" placeholder="Ansøgningens indhold…"></textarea>
          </div>
          <div class="crm-field crm-field-full">
            <label class="crm-checkbox-label">
              <input type="checkbox" id="app-gdpr"> GDPR-samtykke er indhentet
            </label>
          </div>
          <div class="crm-modal-footer">
            <button class="crm-btn crm-btn-ghost" data-close="crm-app-modal">Annuller</button>
            <button class="crm-btn crm-btn-primary" id="crm-app-save-btn">Gem ansøgning</button>
          </div>
        </div>

        <!-- Detail-view (fyldes via JS) -->
        <div id="crm-app-detail" style="display:none">

          <!-- Tab navigation -->
          <div class="crm-detail-tabs">
            <button class="crm-detail-tab active" data-dtab="overblik">👤 Overblik</button>
            <button class="crm-detail-tab" data-dtab="kommunikation">✉ Kommunikation</button>
            <button class="crm-detail-tab" data-dtab="aktivitet">📋 Aktivitet</button>
            <button class="crm-detail-tab" data-dtab="noter">📝 Noter</button>
          </div>

          <!-- Tab: Overblik -->
          <div class="crm-detail-tabpanel" id="crm-dtab-overblik">
            <div class="crm-detail-grid crm-detail-grid--overblik">
              <div class="crm-detail-left">
                <div class="crm-detail-meta" id="crm-detail-meta"></div>
              </div>
              <div class="crm-detail-right">
                <div class="crm-detail-section">
                  <h3>Pipeline</h3>
                  <div class="crm-stage-buttons" id="crm-stage-btns"></div>
                  <textarea id="crm-stage-note" class="crm-textarea" rows="2" placeholder="Note til flytning (valgfri)…"></textarea>
                </div>
                <div class="crm-detail-section">
                  <h3>Rating</h3>
                  <div class="crm-stars" id="crm-stars"></div>
                </div>
                <div class="crm-detail-section" id="crm-rubix-section" style="display:none">
                  <h3>Rubix HR</h3>
                  <p style="font-size:12px;color:var(--crm-muted);margin:0 0 10px">Ansøger er ansat — klar til overførsel til Rubix HR-system.</p>
                  <button class="crm-btn crm-btn-primary" id="crm-rubix-btn" style="width:100%;justify-content:center">🔄 Overfør data til Rubix</button>
                </div>
                <div class="crm-detail-section" id="crm-rejection-section" style="display:none">
                  <div class="crm-rejection-info">
                    <span class="crm-icon">⏳</span>
                    <span id="crm-rejection-text">Afslag-email planlagt</span>
                    <button class="crm-btn crm-btn-danger-sm" id="crm-cancel-rejection-btn">Annuller</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: Kommunikation -->
          <div class="crm-detail-tabpanel" id="crm-dtab-kommunikation" style="display:none">
            <div class="crm-detail-grid">
              <div class="crm-detail-left">
                <div class="crm-detail-section">
                  <h3>Send besked</h3>
                  <div class="crm-send-form">
                    <select id="crm-comm-type" class="crm-select">
                      <option value="email">📧 Email</option>
                      <option value="sms">💬 SMS</option>
                    </select>
                    <select id="crm-template-select" class="crm-select">
                      <option value="">— Vælg skabelon eller skriv manuelt —</option>
                    </select>
                    <input type="text" id="crm-comm-subject" class="crm-input" placeholder="Emne (kun email)">
                    <textarea id="crm-comm-body" class="crm-textarea" rows="5" placeholder="Besked…"></textarea>
                    <button class="crm-btn crm-btn-primary" id="crm-send-btn">Send</button>
                  </div>
                </div>
              </div>
              <div class="crm-detail-right">
                <div class="crm-detail-section">
                  <h3>Sendte beskeder</h3>
                  <div id="crm-comms-list" class="crm-comms"></div>
                </div>
                <!-- Historik (skjult, bruges stadig af renderHistory) -->
                <div class="crm-detail-section" style="display:none">
                  <div id="crm-history-list" class="crm-history"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: Aktivitet -->
          <div class="crm-detail-tabpanel" id="crm-dtab-aktivitet" style="display:none">
            <div class="crm-activity-feed" id="crm-activity-feed">
              <div class="crm-activity-loading">Henter aktivitet…</div>
            </div>
          </div>

          <!-- Tab: Noter -->
          <div class="crm-detail-tabpanel" id="crm-dtab-noter" style="display:none">
            <div class="crm-notes-panel">
              <div class="crm-notes-add">
                <textarea id="crm-note-input" class="crm-textarea" rows="3" placeholder="Skriv intern note om denne ansøger…"></textarea>
                <button class="crm-btn crm-btn-primary" id="crm-note-save-btn">Gem note</button>
              </div>
              <div class="crm-notes-list" id="crm-notes-list">
                <div class="crm-activity-loading">Henter noter…</div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- ── Stillinger-modal ─────────────────────────────────────────────────── -->
  <div id="crm-positions-modal" class="crm-modal" style="display:none">
    <div class="crm-modal-inner">
      <div class="crm-modal-header">
        <h2>Stillinger</h2>
        <button class="crm-modal-close" data-close="crm-positions-modal">✕</button>
      </div>
      <div class="crm-modal-body">
        <button class="crm-btn crm-btn-primary crm-mb" id="crm-new-position-btn">+ Ny stilling</button>
        <div id="crm-positions-list"></div>

        <div id="crm-position-form" style="display:none;margin-top:20px">
          <h3 id="crm-pos-form-title">Ny stilling</h3>
          <div class="crm-form-grid">
            <div class="crm-field crm-field-full">
              <label>Stillingstype</label>
              <select id="pos-job-type" class="crm-select">
                <option value="">— Ingen specifik type —</option>
                <?php foreach ( RZPZ_CRM_DB::JOB_TYPES as $key => $label ) : ?>
                  <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="crm-field crm-field-full">
              <label>Jobtitel *</label>
              <input type="text" id="pos-title" class="crm-input" placeholder="Kundeservicemedarbejder">
            </div>
            <div class="crm-field">
              <label>Afdeling</label>
              <input type="text" id="pos-dept" class="crm-input" placeholder="Kundeservice">
            </div>
            <div class="crm-field">
              <label>Lokation</label>
              <input type="text" id="pos-location" class="crm-input" placeholder="Aalborg">
            </div>
            <div class="crm-field">
              <label>Status</label>
              <select id="pos-status" class="crm-select">
                <option value="open">Åben</option>
                <option value="draft">Kladde</option>
                <option value="closed">Lukket</option>
              </select>
            </div>
            <div class="crm-field">
              <label>Link til job-opslag</label>
              <input type="url" id="pos-url" class="crm-input" placeholder="https://rezponz.dk/jobs/...">
            </div>
          </div>
          <div class="crm-field crm-field-full">
            <label>Beskrivelse</label>
            <textarea id="pos-desc" class="crm-textarea" rows="3"></textarea>
          </div>
          <div class="crm-modal-footer">
            <button class="crm-btn crm-btn-ghost" id="crm-pos-cancel-btn">Annuller</button>
            <button class="crm-btn crm-btn-primary" id="crm-pos-save-btn">Gem stilling</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Skabeloner-modal ─────────────────────────────────────────────────── -->
  <div id="crm-templates-modal" class="crm-modal" style="display:none">
    <div class="crm-modal-inner crm-modal-wide">
      <div class="crm-modal-header">
        <h2>Email & SMS Skabeloner</h2>
        <button class="crm-modal-close" data-close="crm-templates-modal">✕</button>
      </div>
      <div class="crm-modal-body">
        <div class="crm-templates-layout">
          <div class="crm-templates-list" id="crm-templates-list"></div>
          <div class="crm-template-editor" id="crm-template-editor">
            <div style="margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.08)">
              <h3 id="crm-tpl-form-title" style="margin:0;font-size:15px;font-weight:700;color:#fff">Ny skabelon</h3>
              <p style="margin:4px 0 0;font-size:12px;color:#555">Udfyld felterne til højre og gem</p>
            </div>
            <div class="crm-field">
              <label>Navn</label>
              <input type="text" id="tpl-name" class="crm-input" placeholder="fx Bekræftelse">
            </div>
            <div class="crm-form-grid">
              <div class="crm-field">
                <label>Type</label>
                <select id="tpl-type" class="crm-select">
                  <option value="email">📧 Email</option>
                  <option value="sms">💬 SMS</option>
                </select>
              </div>
              <div class="crm-field">
                <label>Trigger (auto-send)</label>
                <select id="tpl-trigger" class="crm-select">
                  <option value="manual">Manuel</option>
                  <option value="stage_ny">Ved modtagelse (Ny)</option>
                  <option value="stage_screening">Screening</option>
                  <option value="stage_samtale">Samtale-invitation</option>
                  <option value="stage_tilbud">Tilbud</option>
                  <option value="stage_ansat">Ansat</option>
                  <option value="stage_afslag">Afslag (forsinket 3-5 dage)</option>
                </select>
              </div>
            </div>
            <div class="crm-field" id="tpl-subject-field">
              <label>Emne</label>
              <input type="text" id="tpl-subject" class="crm-input" placeholder="Emne…">
            </div>
            <div class="crm-field">
              <label>Indhold</label>
              <div class="crm-tags-hint">Merge tags: <code>{{first_name}}</code> <code>{{last_name}}</code> <code>{{position_title}}</code> <code>{{status_url}}</code> <code>{{stage_label}}</code></div>
              <textarea id="tpl-body" class="crm-textarea" rows="8"></textarea>
            </div>
            <div class="crm-modal-footer" style="border-top:0;padding-top:0">
              <button class="crm-btn crm-btn-ghost" id="crm-tpl-new-btn">+ Ny</button>
              <button class="crm-btn crm-btn-primary" id="crm-tpl-save-btn">Gem skabelon</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Backdrop ─────────────────────────────────────────────────────────── -->
  <div id="crm-backdrop" class="crm-backdrop" style="display:none"></div>

</div><!-- /.rzpz-crm-wrap -->
