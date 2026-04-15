<?php
/**
 * ESG Admin Page — 🌱 ESG
 *
 * Shows PDF sync status, allows manual sync trigger and PDF URL config.
 *
 * @package RezponzAnalytics
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$settings  = get_option( RZPA_ESG::OPTION_SETTINGS, [] );
$log       = get_option( RZPA_ESG::OPTION_SYNC_LOG, [] );
$pdf_url   = $settings['pdf_url'] ?? 'https://rezponz.dk/wp-content/uploads/2026/03/Rezponz-ESG-rapport.pdf';
$has_data  = ! empty( get_option( RZPA_ESG::OPTION_CONTENT, [] ) );

$status    = $log['last_status']  ?? null;
$last_run  = $log['last_run']     ?? null;
$last_msg  = $log['last_message'] ?? '';
$history   = $log['history']      ?? [];

// Flash messages
$sync_param = $_GET['sync'] ?? '';
$saved      = isset( $_GET['saved'] );
?>
<div class="wrap" id="rzpa-esg-admin">
<style>
#rzpa-esg-admin { max-width: 900px; }
#rzpa-esg-admin h1 { display:flex; align-items:center; gap:10px; margin-bottom:24px; }
.rzpa-esg-cards { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }
.rzpa-esg-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.rzpa-esg-card h2 { font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:#888; margin-bottom:14px; font-weight:600; }
.rzpa-esg-status-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.rzpa-esg-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.rzpa-esg-dot--ok      { background:#22c55e; }
.rzpa-esg-dot--error   { background:#ef4444; }
.rzpa-esg-dot--unchanged { background:#f59e0b; }
.rzpa-esg-dot--never   { background:#d1d5db; }
.rzpa-esg-status-text  { font-size:14px; font-weight:600; color:#1a202c; }
.rzpa-esg-meta         { font-size:12px; color:#888; margin-top:4px; }
.rzpa-esg-msg          { font-size:12px; color:#555; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; margin-top:12px; word-break:break-word; }
.rzpa-esg-btn-sync { background:#5d8089; color:#fff; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; margin-top:4px; }
.rzpa-esg-btn-sync:hover { background:#4a6a72; }
.rzpa-esg-btn-sync:disabled { opacity:.6; cursor:not-allowed; }
.rzpa-esg-field { margin-bottom:0; }
.rzpa-esg-field label { display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#888; margin-bottom:8px; }
.rzpa-esg-field input[type=url] { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:9px 12px; font-size:13px; background:#f8fafc; }
.rzpa-esg-field input:focus { outline:2px solid #5d8089; outline-offset:1px; border-color:#5d8089; background:#fff; }
.rzpa-esg-field-note { font-size:11px; color:#aaa; margin-top:6px; }
.rzpa-esg-save-btn { margin-top:14px; background:#1a202c; color:#fff; border:none; border-radius:8px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; }
.rzpa-esg-save-btn:hover { background:#2d3748; }
.rzpa-esg-alert { border-radius:8px; padding:12px 16px; font-size:13px; font-weight:600; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
.rzpa-esg-alert--ok       { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.rzpa-esg-alert--error     { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; }
.rzpa-esg-alert--unchanged { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.rzpa-esg-alert--saved     { background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; }
.rzpa-esg-history { margin-top:28px; }
.rzpa-esg-history h2 { font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:#888; margin-bottom:12px; font-weight:600; }
.rzpa-esg-history table { width:100%; border-collapse:collapse; font-size:12px; }
.rzpa-esg-history th { text-align:left; padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; color:#555; font-weight:600; }
.rzpa-esg-history td { padding:8px 12px; border:1px solid #e2e8f0; vertical-align:top; }
.rzpa-esg-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
.rzpa-esg-badge--ok        { background:#f0fdf4; color:#15803d; }
.rzpa-esg-badge--error      { background:#fef2f2; color:#dc2626; }
.rzpa-esg-badge--unchanged  { background:#fffbeb; color:#92400e; }
.rzpa-esg-info-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px 20px; font-size:13px; color:#0369a1; margin-bottom:28px; }
.rzpa-esg-info-box strong { display:block; margin-bottom:4px; font-size:14px; }
@media(max-width:700px){ .rzpa-esg-cards { grid-template-columns:1fr; } }
</style>

<h1>🌱 ESG – PDF Auto-Sync</h1>

<?php if ( $sync_param === 'synced' ) : ?>
<div class="rzpa-esg-alert rzpa-esg-alert--ok">✅ PDF synket — indholdet på ESG-siden er nu opdateret.</div>
<?php elseif ( $sync_param === 'unchanged' ) : ?>
<div class="rzpa-esg-alert rzpa-esg-alert--unchanged">⚡ PDF er uændret siden sidst — ingen opdatering nødvendig.</div>
<?php elseif ( $sync_param === 'error' ) : ?>
<div class="rzpa-esg-alert rzpa-esg-alert--error">❌ Synk fejlede. Se detaljer herunder.</div>
<?php endif; ?>

<?php if ( $saved ) : ?>
<div class="rzpa-esg-alert rzpa-esg-alert--saved">💾 PDF-URL gemt.</div>
<?php endif; ?>

<div class="rzpa-esg-info-box">
    <strong>🤖 Automatisk daglig synkronisering</strong>
    ESG-sidens indhold hentes automatisk fra rapport-URL'en én gang i døgnet. Systemet registrerer automatisk om det er en PDF eller HTML-rapport og udtrækker struktureret data via GPT-4o. Klik "Synk nu" for at tvinge en opdatering med det samme.
</div>

<div class="rzpa-esg-cards">

    <!-- Status card -->
    <div class="rzpa-esg-card">
        <h2>Seneste synk-status</h2>
        <?php if ( $last_run ) :
            $dot_class = 'rzpa-esg-dot--' . ( $status ?: 'never' );
            $status_label = match( $status ) {
                'ok'        => '✅ Synket OK',
                'unchanged' => '⏸ Uændret',
                'error'     => '❌ Fejl',
                default     => 'Ukendt',
            };
        ?>
        <div class="rzpa-esg-status-row">
            <span class="rzpa-esg-dot <?php echo esc_attr( $dot_class ); ?>"></span>
            <span class="rzpa-esg-status-text"><?php echo esc_html( $status_label ); ?></span>
        </div>
        <div class="rzpa-esg-meta">Sidst kørt: <?php echo esc_html( $last_run ); ?></div>
        <?php if ( $last_msg ) : ?>
        <div class="rzpa-esg-msg"><?php echo esc_html( $last_msg ); ?></div>
        <?php endif; ?>
        <?php else : ?>
        <div class="rzpa-esg-status-row">
            <span class="rzpa-esg-dot rzpa-esg-dot--never"></span>
            <span class="rzpa-esg-status-text">Aldrig synket</span>
        </div>
        <div class="rzpa-esg-meta">Klik "Synk nu" for at hente data fra PDF'en.</div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
            <?php wp_nonce_field( 'rzpa_sync_esg' ); ?>
            <input type="hidden" name="action" value="rzpa_sync_esg">
            <button type="submit" class="rzpa-esg-btn-sync" id="rzpa-esg-sync-btn">
                🔄 Synk PDF nu
            </button>
        </form>
        <div class="rzpa-esg-meta" style="margin-top:8px">
            <?php echo $has_data ? '✓ Indhold fra PDF er aktiv på hjemmesiden' : '⚠ Ingen synket data endnu — bruger standard fallback-tekst'; ?>
        </div>
    </div>

    <!-- Settings card -->
    <div class="rzpa-esg-card">
        <h2>PDF-kilde</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'rzpa_save_esg_settings' ); ?>
            <input type="hidden" name="action" value="rzpa_save_esg_settings">
            <div class="rzpa-esg-field">
                <label for="esg_pdf_url">Rapport URL</label>
                <input
                    type="url"
                    id="esg_pdf_url"
                    name="esg_pdf_url"
                    value="<?php echo esc_attr( $pdf_url ); ?>"
                    placeholder="https://rezponz.dk/.../esg-rapport.pdf"
                >
                <div class="rzpa-esg-field-note">
                    Systemet tjekker dagligt om denne PDF er ændret (via HTTP ETag/Last-Modified).
                    Kun hvis den er ændret sendes den til OpenAI.
                </div>
            </div>
            <button type="submit" class="rzpa-esg-save-btn">💾 Gem URL</button>
        </form>
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0;">
            <div class="rzpa-esg-meta" style="font-size:12px; color:#888">
                📅 Næste automatiske tjek: <?php
                    $next = wp_next_scheduled( RZPA_ESG::CRON_HOOK );
                    echo $next ? esc_html( date( 'd/m/Y H:i', $next ) ) : 'Ikke planlagt';
                ?>
            </div>
            <div class="rzpa-esg-meta" style="font-size:12px; color:#888; margin-top:4px">
                🤖 Model: GPT-4o · Kræver OpenAI API-nøgle i <a href="<?php echo admin_url('admin.php?page=rzpa-settings'); ?>">Indstillinger</a>
            </div>
        </div>
    </div>

</div>

<?php if ( ! empty( $history ) ) : ?>
<div class="rzpa-esg-history">
    <h2>Synk-historik (seneste 10)</h2>
    <table>
        <thead>
            <tr>
                <th>Tidspunkt</th>
                <th>Status</th>
                <th>Besked</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $history as $entry ) : ?>
            <tr>
                <td><?php echo esc_html( $entry['time'] ); ?></td>
                <td>
                    <span class="rzpa-esg-badge rzpa-esg-badge--<?php echo esc_attr( $entry['status'] ); ?>">
                        <?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( $entry['message'] ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
document.querySelector('#rzpa-esg-sync-btn')?.closest('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('rzpa-esg-sync-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Synkroniserer… (kan tage 30-60 sek.)';
});
</script>

</div>
