<?php
/**
 * RZPA_ESG — ESG Module for Rezponz Analytics
 *
 * Renders a full, SEO-friendly ESG page via the [rezponz_esg] shortcode.
 * Content is automatically synced daily from the Rezponz ESG PDF report
 * using OpenAI gpt-4.1 to extract structured data.
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │  SHORTCODE USAGE                                             │
 * │  Add  [rezponz_esg]  to any WordPress page or post.         │
 * │                                                              │
 * │  Content is pulled from PDF via OpenAI — edit the PDF and   │
 * │  click "Synk PDF nu" in the admin panel (🌱 ESG menu).       │
 * └──────────────────────────────────────────────────────────────┘
 *
 * @package    RezponzAnalytics
 * @subpackage ESG
 * @since      3.5.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_ESG {

    /** @var bool Whether the shortcode has been found on the current page. */
    private static bool $enqueue_flag = false;

    const OPTION_CONTENT  = 'rzpa_esg_content';
    const OPTION_SETTINGS = 'rzpa_esg_settings';
    const OPTION_SYNC_LOG = 'rzpa_esg_sync_log';
    const CRON_HOOK       = 'rzpa_daily_esg_sync';

    // ─────────────────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────────────────

    public static function init(): void {
        add_shortcode( 'rezponz_esg', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );

        // Admin
        add_action( 'admin_post_rzpa_sync_esg',         [ __CLASS__, 'handle_manual_sync' ] );
        add_action( 'admin_post_rzpa_save_esg_settings', [ __CLASS__, 'handle_save_settings' ] );

        // WP-Cron daily sync
        add_action( self::CRON_HOOK, [ __CLASS__, 'sync_pdf' ] );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Asset enqueueing (only when shortcode is on the page)
    // ─────────────────────────────────────────────────────────────────────────

    public static function enqueue_assets(): void {
        global $post;

        $has_shortcode = (
            is_a( $post, 'WP_Post' )
            && has_shortcode( $post->post_content, 'rezponz_esg' )
        );

        if ( ! $has_shortcode ) return;

        $ver  = defined( 'RZPA_VERSION' ) ? RZPA_VERSION : '3.5.20';
        $base = rtrim( defined( 'RZPA_URL' ) ? RZPA_URL : plugin_dir_url( __FILE__ ), '/' ) . '/modules/esg/assets/';

        wp_enqueue_style(  'rzpa-esg', $base . 'esg.css', [], $ver );
        wp_enqueue_script( 'rzpa-esg', $base . 'esg.js',  [], $ver, true );
        wp_localize_script( 'rzpa-esg', 'RZ_ESG_DATA', self::get_js_data() );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shortcode renderer
    // ─────────────────────────────────────────────────────────────────────────

    public static function render_shortcode(): string {
        $data = self::get_data();
        ob_start();
        require __DIR__ . '/views/esg-frontend.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JS data subset
    // ─────────────────────────────────────────────────────────────────────────

    private static function get_js_data(): array {
        $data = self::get_data();
        return [
            'version'    => defined( 'RZPA_VERSION' ) ? RZPA_VERSION : '3.5.20',
            'action_ids' => array_map( fn( $c ) => $c['id'], $data['action_cards'] ),
            'faq_ids'    => array_map( fn( $q ) => $q['id'], $data['faq'] ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data: live option → defaults fallback
    // ─────────────────────────────────────────────────────────────────────────

    public static function get_data(): array {
        $saved = get_option( self::OPTION_CONTENT, [] );
        if ( empty( $saved ) || ! is_array( $saved ) ) {
            return self::get_defaults();
        }
        // Deep-merge: saved values win, defaults fill any missing keys
        return self::deep_merge( self::get_defaults(), $saved );
    }

    /**
     * Deep merge: $base is overwritten by $override recursively.
     * Indexed arrays (numeric keys) from $override fully replace those in $base.
     */
    private static function deep_merge( array $base, array $override ): array {
        foreach ( $override as $k => $v ) {
            if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) ) {
                // If both are indexed arrays (lists), replace fully
                if ( array_values( $v ) === $v ) { // PHP 8.0-compatible indexed-array check
                    $base[ $k ] = $v;
                } else {
                    $base[ $k ] = self::deep_merge( $base[ $k ], $v );
                }
            } else {
                $base[ $k ] = $v;
            }
        }
        return $base;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF Sync — WP-Cron + manual
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Manual sync triggered from admin page.
     */
    public static function handle_manual_sync(): void {
        check_admin_referer( 'rzpa_sync_esg' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Ingen adgang', 403 );

        $result = self::sync_pdf();
        $status = $result === true ? 'synced' : ( $result === false ? 'unchanged' : 'error' );
        wp_redirect( admin_url( 'admin.php?page=rzpa-esg&sync=' . $status ) );
        exit;
    }

    /**
     * Save ESG settings (PDF URL etc.) from admin page.
     */
    public static function handle_save_settings(): void {
        check_admin_referer( 'rzpa_save_esg_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Ingen adgang', 403 );

        $settings = get_option( self::OPTION_SETTINGS, [] );
        $settings['pdf_url'] = esc_url_raw( wp_unslash( $_POST['esg_pdf_url'] ?? '' ) );
        update_option( self::OPTION_SETTINGS, $settings );

        wp_redirect( admin_url( 'admin.php?page=rzpa-esg&saved=1' ) );
        exit;
    }

    /**
     * Core sync method: checks if PDF changed → re-extracts via OpenAI → saves.
     *
     * @return true   Content was updated (PDF changed & parsed OK)
     * @return false  No update needed (PDF unchanged)
     * @return string Error message string on failure
     */
    public static function sync_pdf(): bool|string {
        @set_time_limit( 180 );

        $settings = get_option( self::OPTION_SETTINGS, [] );
        $pdf_url  = $settings['pdf_url'] ?? 'https://rezponz.dk/wp-content/uploads/2026/03/Rezponz-ESG-rapport.pdf';

        $opts        = get_option( 'rzpa_settings', [] );
        $api_key     = $opts['openai_api_key'] ?? '';
        if ( empty( $api_key ) ) {
            return self::log_sync( 'error', 'OpenAI API-nøgle mangler i Indstillinger' );
        }

        // ── 1. Check Last-Modified / ETag for change detection ──────────────
        $log       = get_option( self::OPTION_SYNC_LOG, [] );
        $last_etag = $log['last_etag'] ?? '';
        $last_lmod = $log['last_modified'] ?? '';

        $head_res = wp_remote_head( $pdf_url, [ 'timeout' => 15, 'redirection' => 5 ] );
        if ( is_wp_error( $head_res ) ) {
            return self::log_sync( 'error', 'Kunne ikke nå PDF: ' . $head_res->get_error_message() );
        }

        $etag     = wp_remote_retrieve_header( $head_res, 'etag' );
        $lmod     = wp_remote_retrieve_header( $head_res, 'last-modified' );

        // If we already have content and nothing changed, skip
        $has_content = ! empty( get_option( self::OPTION_CONTENT, [] ) );
        if ( $has_content && $etag && $etag === $last_etag ) {
            self::log_sync( 'unchanged', 'PDF uændret (ETag match)' );
            return false;
        }
        if ( $has_content && ! $etag && $lmod && $lmod === $last_lmod ) {
            self::log_sync( 'unchanged', 'PDF uændret (Last-Modified match)' );
            return false;
        }

        // ── 2. Download PDF ──────────────────────────────────────────────────
        $pdf_res = wp_remote_get( $pdf_url, [ 'timeout' => 60, 'redirection' => 5 ] );
        if ( is_wp_error( $pdf_res ) ) {
            return self::log_sync( 'error', 'Download fejlede: ' . $pdf_res->get_error_message() );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $pdf_res );
        if ( $http_code !== 200 ) {
            return self::log_sync( 'error', "Download fejlede med HTTP $http_code" );
        }

        $pdf_binary = wp_remote_retrieve_body( $pdf_res );
        $pdf_size   = strlen( $pdf_binary );

        if ( $pdf_size < 1000 ) {
            return self::log_sync( 'error', 'PDF for lille — muligvis ikke en gyldig fil' );
        }

        // ── 3. Send to OpenAI for extraction ─────────────────────────────────
        $b64    = base64_encode( $pdf_binary );
        $prompt = self::build_extraction_prompt();

        $ai_body = wp_json_encode( [
            'model'      => 'gpt-4.1',
            'max_tokens' => 4000,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'file',
                            'file' => [
                                'filename'  => 'rezponz-esg-rapport.pdf',
                                'file_data' => 'data:application/pdf;base64,' . $b64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ] );

        $ai_res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => $ai_body,
        ] );

        if ( is_wp_error( $ai_res ) ) {
            return self::log_sync( 'error', 'OpenAI fejl: ' . $ai_res->get_error_message() );
        }

        $ai_http = (int) wp_remote_retrieve_response_code( $ai_res );
        $ai_data = json_decode( wp_remote_retrieve_body( $ai_res ), true );

        if ( $ai_http !== 200 || empty( $ai_data['choices'][0]['message']['content'] ) ) {
            $err_msg = $ai_data['error']['message'] ?? "HTTP $ai_http";
            return self::log_sync( 'error', 'OpenAI returnerede fejl: ' . $err_msg );
        }

        $raw_content = $ai_data['choices'][0]['message']['content'];

        // ── 4. Parse JSON from response ───────────────────────────────────────
        // Strip markdown code fences if present
        $json_str = preg_replace( '/^```(?:json)?\s*|\s*```$/s', '', trim( $raw_content ) );
        $parsed   = json_decode( $json_str, true );

        if ( ! is_array( $parsed ) || empty( $parsed ) ) {
            return self::log_sync( 'error', 'OpenAI returnerede ugyldigt JSON. Rå svar: ' . substr( $raw_content, 0, 300 ) );
        }

        // ── 5. Validate & save ────────────────────────────────────────────────
        $required_sections = [ 'hero', 'tracks', 'roadmap', 'action_cards', 'faq', 'cta' ];
        $missing = array_diff( $required_sections, array_keys( $parsed ) );
        if ( count( $missing ) > 2 ) {
            return self::log_sync( 'error', 'For mange sektioner mangler i JSON: ' . implode( ', ', $missing ) );
        }

        update_option( self::OPTION_CONTENT, $parsed );

        // Update etag/lmod for next check
        $log['last_etag']     = $etag;
        $log['last_modified'] = $lmod;
        update_option( self::OPTION_SYNC_LOG, $log );

        return self::log_sync( 'ok', sprintf(
            'PDF synket (%s KB, gpt-4.1)',
            number_format( $pdf_size / 1024, 0 )
        ) );
    }

    /**
     * Log sync result and return true on success, error string on failure.
     */
    private static function log_sync( string $status, string $message ): bool|string {
        $log = get_option( self::OPTION_SYNC_LOG, [] );
        $log['last_run']    = current_time( 'mysql' );
        $log['last_status'] = $status;
        $log['last_message'] = $message;

        // Keep a rolling history of last 10 syncs
        if ( ! isset( $log['history'] ) ) $log['history'] = [];
        array_unshift( $log['history'], [
            'time'    => current_time( 'mysql' ),
            'status'  => $status,
            'message' => $message,
        ] );
        $log['history'] = array_slice( $log['history'], 0, 10 );

        update_option( self::OPTION_SYNC_LOG, $log );

        if ( $status === 'ok' )        return true;
        if ( $status === 'unchanged' ) return false;
        return $message; // error string
    }

    /**
     * Build the structured extraction prompt sent to OpenAI.
     */
    private static function build_extraction_prompt(): string {
        return <<<'PROMPT'
Du er en ESG-dataudtræks-assistent. Læs den vedhæftede Rezponz ESG-rapport PDF og udtruk data til et præcist JSON-objekt.

VIGTIGT:
- Returner KUN valid JSON — ingen forklaringer, ingen markdown-code-fences
- Bevar dansk tekst præcis som i rapporten
- Udfyld alle felter — brug "–" hvis information mangler
- Bevar eksisterende ID-navne (fx "solenergi", "faq-1" osv.) hvis de passer med indholdet

Returner JSON med denne struktur:

{
  "hero": {
    "heading": "Fra mål til handling",
    "intro": "...",
    "kpi_number": 42,
    "kpi_suffix": " %",
    "kpi_label": "reduktion i scope 1+2 inden 2030",
    "kpi_duration": 1500,
    "tagline": "...",
    "chips": [
      {"value": "294 FTE", "label": "medarbejdere i [år]"},
      {"value": "0", "label": "arbejdsulykker i [år]"},
      {"value": "57,1 t CO₂e", "label": "scope 1 emission [år]"},
      {"value": "0", "label": "korruptionshændelser i [år]"}
    ],
    "trust_note": "Data baseret på ESG-rapportering [år]. Opdateres løbende."
  },
  "tracks": [
    {
      "id": "miljo",
      "icon": "🌿",
      "title": "Miljø",
      "status": "ongoing",
      "status_label": "I gang",
      "intro": "...",
      "kpis": [
        {"value": "57,1 t CO₂e", "label": "scope 1"},
        {"value": "65,5 t CO₂e", "label": "scope 2"},
        {"value": "1.285.153 MJ", "label": "energiforbrug"},
        {"value": "3.664 m³", "label": "vandforbrug"}
      ],
      "practice": "...",
      "meaning": "..."
    },
    {
      "id": "mennesker",
      "icon": "🤝",
      "title": "Mennesker",
      "status": "ongoing",
      "status_label": "I gang",
      "intro": "...",
      "kpis": [
        {"value": "294 FTE", "label": "medarbejdere"},
        {"value": "0", "label": "arbejdsulykker"},
        {"value": "72,5 t", "label": "efteruddannelse kvinder"},
        {"value": "68,5 t", "label": "efteruddannelse mænd"}
      ],
      "practice": "...",
      "meaning": "..."
    },
    {
      "id": "governance",
      "icon": "⚖️",
      "title": "Governance",
      "status": "done",
      "status_label": "På plads",
      "intro": "...",
      "kpis": [
        {"value": "0", "label": "korruptionshændelser"},
        {"value": "0", "label": "bestikkelseshændelser"},
        {"value": "✓", "label": "whistleblowerordning aktiv"},
        {"value": "✓", "label": "etiktræning for medarbejdere"}
      ],
      "practice": "...",
      "meaning": "..."
    }
  ],
  "roadmap": [
    {"year": "2023", "title": "...", "body": "...", "status": "done", "tag": "Gennemført", "icon": "📊"},
    {"year": "2024", "title": "...", "body": "...", "status": "done", "tag": "Gennemført", "icon": "✅"},
    {"year": "2026", "title": "...", "body": "...", "status": "upcoming", "tag": "Planlagt", "icon": "🗺️"},
    {"year": "2030", "title": "...", "body": "...", "status": "target", "tag": "Mål", "icon": "🎯"}
  ],
  "action_cards": [
    {"id": "solenergi", "icon": "☀️", "title": "Solenergi", "category": "Miljø", "summary": "...", "why": "...", "how": "...", "next": "..."},
    {"id": "groenn-stroem", "icon": "⚡", "title": "Grøn strøm", "category": "Miljø", "summary": "...", "why": "...", "how": "...", "next": "..."},
    {"id": "it-genbrug", "icon": "💻", "title": "IT-genbrug og reparation", "category": "Miljø", "summary": "...", "why": "...", "how": "...", "next": "..."},
    {"id": "inkluderende-beskaeftigelse", "icon": "🙌", "title": "Inkluderende beskæftigelse", "category": "Mennesker", "summary": "...", "why": "...", "how": "...", "next": "..."},
    {"id": "whistleblower", "icon": "🔒", "title": "Whistleblower og etisk adfærd", "category": "Governance", "summary": "...", "why": "...", "how": "...", "next": "..."}
  ],
  "metrics": {
    "toggle_label_open": "Sådan måler vi",
    "toggle_label_close": "Skjul forklaringer",
    "definitions": [
      {"term": "Scope 1", "def": "..."},
      {"term": "Scope 2", "def": "..."},
      {"term": "Scope 3", "def": "..."},
      {"term": "Efteruddannelsestimer", "def": "..."},
      {"term": "Governance-data", "def": "..."}
    ]
  },
  "cases": [
    {"tag": "Mennesker", "title": "...", "intro": "...", "body": "..."},
    {"tag": "Miljø", "title": "...", "intro": "...", "body": "..."}
  ],
  "faq": [
    {"id": "faq-1", "question": "...", "answer": "..."},
    {"id": "faq-2", "question": "...", "answer": "..."},
    {"id": "faq-3", "question": "...", "answer": "..."},
    {"id": "faq-4", "question": "...", "answer": "..."},
    {"id": "faq-5", "question": "...", "answer": "..."}
  ],
  "cta": {
    "heading": "Vil du vide mere?",
    "body": "...",
    "btn_primary_label": "Læs ESG-rapporten",
    "btn_primary_url": "#esg-rapport",
    "btn_primary_track_event": "esg_cta_report",
    "btn_secondary_label": "Se vores konkrete indsatser",
    "btn_secondary_url": "#indsatser",
    "btn_secondary_track_event": "esg_cta_actions"
  }
}

Udtruk alle tal, procentsatser og målinger præcis som de fremgår i rapporten.
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin page
    // ─────────────────────────────────────────────────────────────────────────

    public static function render_admin_page(): void {
        require __DIR__ . '/views/admin-esg.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //
    //  DEFAULT DATA (fallback when no synced content exists)
    //  ─────────────────────────────────────────────────────
    //
    // ─────────────────────────────────────────────────────────────────────────

    public static function get_defaults(): array {
        return [

            // ──────────────────────────────────────────────────────────────────
            // SECTION 1 — Hero
            // ──────────────────────────────────────────────────────────────────
            'hero' => [
                'heading'      => 'Fra mål til handling',
                'intro'        => 'Hos Rezponz arbejder vi med ESG, fordi det er det rigtige at gøre — for vores medarbejdere, for miljøet og for dem, vi samarbejder med. Her er en åben og ærlig status på, hvor vi er, og hvad vi arbejder henimod.',
                'kpi_number'   => 42,
                'kpi_suffix'   => ' %',
                'kpi_label'    => 'reduktion i scope 1+2 inden 2030',
                'kpi_duration' => 1500,
                'tagline'      => 'Dokumenterede indsatser. Tydelige næste skridt.',
                'chips' => [
                    [ 'value' => '294 FTE',     'label' => 'medarbejdere i 2024' ],
                    [ 'value' => '0',            'label' => 'arbejdsulykker i 2024' ],
                    [ 'value' => '57,1 t CO₂e', 'label' => 'scope 1 emission 2024' ],
                    [ 'value' => '0',            'label' => 'korruptionshændelser i 2024' ],
                ],
                'trust_note' => 'Data baseret på ESG-rapportering 2024. Opdateres løbende.',
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 2 — ESG Spor
            // ──────────────────────────────────────────────────────────────────
            'tracks' => [
                [
                    'id'           => 'miljo',
                    'icon'         => '🌿',
                    'title'        => 'Miljø',
                    'status'       => 'ongoing',
                    'status_label' => 'I gang',
                    'intro'        => 'Vi reducerer vores klimaaftryk systematisk — med konkrete indsatser i energi, forbrug og transport.',
                    'kpis' => [
                        [ 'value' => '57,1 t CO₂e',  'label' => 'scope 1' ],
                        [ 'value' => '65,5 t CO₂e',  'label' => 'scope 2' ],
                        [ 'value' => '1.285.153 MJ',  'label' => 'energiforbrug' ],
                        [ 'value' => '3.664 m³',      'label' => 'vandforbrug' ],
                    ],
                    'practice' => 'Vi bruger solenergi og grøn strøm, reparerer og genbruger IT-udstyr, og kortlægger scope 3-emissioner fra 2026.',
                    'meaning'  => 'Hvert ton CO₂ vi undgår er et skridt tættere på de 42 % vi har lovet.',
                ],
                [
                    'id'           => 'mennesker',
                    'icon'         => '🤝',
                    'title'        => 'Mennesker',
                    'status'       => 'ongoing',
                    'status_label' => 'I gang',
                    'intro'        => 'Vi er en arbejdsplads for rigtige mennesker — og vi tager ansvar for at inkludere dem, der har svært ved at komme ind på arbejdsmarkedet.',
                    'kpis' => [
                        [ 'value' => '294 FTE', 'label' => 'medarbejdere' ],
                        [ 'value' => '0',        'label' => 'arbejdsulykker' ],
                        [ 'value' => '72,5 t',   'label' => 'efteruddannelse kvinder' ],
                        [ 'value' => '68,5 t',   'label' => 'efteruddannelse mænd' ],
                    ],
                    'practice' => 'Vi samarbejder med initiativer som »Små Job med Mening« og prioriterer rummelig ansættelse. Alle medarbejdere gennemgår løbende træning.',
                    'meaning'  => 'Et sikkert arbejdsmiljø og reel mulighed for at lære er ikke pynt — det er noget vi måler på.',
                ],
                [
                    'id'           => 'governance',
                    'icon'         => '⚖️',
                    'title'        => 'Governance',
                    'status'       => 'done',
                    'status_label' => 'På plads',
                    'intro'        => 'Ansvarlig virksomhedsdrift handler om at gøre det rigtige — også når ingen kigger.',
                    'kpis' => [
                        [ 'value' => '0',   'label' => 'korruptionshændelser' ],
                        [ 'value' => '0',   'label' => 'bestikkelseshændelser' ],
                        [ 'value' => '✓',   'label' => 'whistleblowerordning aktiv' ],
                        [ 'value' => '✓',   'label' => 'etiktræning for medarbejdere' ],
                    ],
                    'practice' => 'Vi har en aktiv whistleblowerordning og træner løbende vores medarbejdere i etisk adfærd og ansvarlig virksomhedsdrift.',
                    'meaning'  => 'Nul hændelser er ikke tilfældigt — det er resultatet af klare spilleregler og en kultur, der belønner ærlighed.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 3 — Roadmap
            // ──────────────────────────────────────────────────────────────────
            'roadmap' => [
                [ 'year' => '2023', 'title' => 'Baseline og første ESG-rapport', 'body' => 'Første formelle ESG-rapport offentliggjort. Baseline etableret for scope 1 og 2.', 'status' => 'done',     'tag' => 'Gennemført', 'icon' => '📊' ],
                [ 'year' => '2024', 'title' => 'Indsatser i gang',               'body' => 'Solenergi, grøn strøm, IT-genbrug og inkluderende beskæftigelse er aktive indsatser.',  'status' => 'done',     'tag' => 'Gennemført', 'icon' => '✅' ],
                [ 'year' => '2026', 'title' => 'Scope 3-kortlægning',            'body' => 'Vi kortlægger vores scope 3-emissioner — herunder leverandørkæde og medarbejdertransport.', 'status' => 'upcoming', 'tag' => 'Planlagt',    'icon' => '🗺️' ],
                [ 'year' => '2030', 'title' => '42 % reduktion i scope 1+2',     'body' => 'Vores langsigtede klimamål: 42 % reduktion i scope 1 og scope 2 emissioner målt fra 2023-baseline.', 'status' => 'target', 'tag' => 'Mål', 'icon' => '🎯' ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 4 — Indsatskort
            // ──────────────────────────────────────────────────────────────────
            'action_cards' => [
                [
                    'id' => 'solenergi', 'icon' => '☀️', 'title' => 'Solenergi', 'category' => 'Miljø',
                    'summary' => 'Vi anvender solenergi som en del af vores energiforsyning.',
                    'why'     => 'Vedvarende energi er en direkte vej til at reducere vores scope 2-emissioner og mindske afhængighed af fossil energi.',
                    'how'     => 'Solceller bidrager til at dække en del af energiforbruget i vores lokaler. Det er en investering i en lavere CO₂-profil på sigt.',
                    'next'    => 'Løbende evaluering af udvidelsesmuligheder i takt med bygnings- og kapacitetsændringer.',
                ],
                [
                    'id' => 'groenn-stroem', 'icon' => '⚡', 'title' => 'Grøn strøm', 'category' => 'Miljø',
                    'summary' => 'Øvrig el er grøn eller CO₂-kompenseret.',
                    'why'     => 'Elforbruget i et contact center er markant. Grøn strøm er en af de mest direkte måder at reducere scope 2 på.',
                    'how'     => 'Vi bruger el fra vedvarende energikilder eller el, der CO₂-kompenseres. Det er ikke nok i sig selv, men det tæller med.',
                    'next'    => 'Øget fokus på energieffektivitet for at reducere det samlede forbrug — ikke kun emissionsintensiteten.',
                ],
                [
                    'id' => 'it-genbrug', 'icon' => '💻', 'title' => 'IT-genbrug og reparation', 'category' => 'Miljø',
                    'summary' => 'IT-udstyr repareres, genbruges eller genanvendes miljømæssigt forsvarligt.',
                    'why'     => 'Elektronik har et stort CO₂-aftryk i produktion. Hvert stykke udstyr vi forlænger levetiden på, er CO₂ vi ikke udleder.',
                    'how'     => 'Når udstyr er udtjent, prioriterer vi reparation og genbrug frem for bortskaffelse. Det gælder computere, skærme og telefoner.',
                    'next'    => 'Kortlægning som en del af scope 3-analysen i 2026.',
                ],
                [
                    'id' => 'inkluderende-beskaeftigelse', 'icon' => '🙌', 'title' => 'Inkluderende beskæftigelse', 'category' => 'Mennesker',
                    'summary' => 'Vi samarbejder aktivt for at skabe jobs til dem, der ellers har svært ved at komme ind på arbejdsmarkedet.',
                    'why'     => 'Et stærkt arbejdsmarked er et, der har plads til alle. Rummelighed er ikke kun en etisk forpligtelse — det skaber bedre virksomheder.',
                    'how'     => 'Via samarbejde med initiativer som »Små Job med Mening« og tilsvarende programmer skaber vi konkrete muligheder for sårbare grupper.',
                    'next'    => 'Fortsat styrke samarbejder og dokumentere effekten af indsatserne.',
                ],
                [
                    'id' => 'whistleblower', 'icon' => '🔒', 'title' => 'Whistleblower og etisk adfærd', 'category' => 'Governance',
                    'summary' => 'Vi har en aktiv whistleblowerordning og løbende etisk træning for alle medarbejdere.',
                    'why'     => 'Åbenhed og etisk adfærd er grundstenen i en troværdig virksomhed. Det handler om at give medarbejdere en tryg måde at sige fra på.',
                    'how'     => 'Alle medarbejdere kan anonymt indberette bekymringer. Træning i etisk adfærd er en del af vores onboarding og løbende udvikling.',
                    'next'    => 'Øget dokumentation og transparens om hvad ordningen bruges til og hvad den fører til.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 5 — Metrics
            // ──────────────────────────────────────────────────────────────────
            'metrics' => [
                'toggle_label_open'  => 'Sådan måler vi',
                'toggle_label_close' => 'Skjul forklaringer',
                'definitions' => [
                    [ 'term' => 'Scope 1',               'def' => 'Direkte emissioner fra Rezponz\' egne aktiviteter — fx kørsel i egne køretøjer og brændstofforbrug i bygninger.' ],
                    [ 'term' => 'Scope 2',               'def' => 'Indirekte emissioner fra den el og varme vi køber — dvs. hvad kraftværkerne udleder for at levere vores energi.' ],
                    [ 'term' => 'Scope 3',               'def' => 'Emissioner i vores værdikæde — medarbejdertransport, leverandører, indkøb. Vi kortlægger dette fra 2026.' ],
                    [ 'term' => 'Efteruddannelsestimer', 'def' => 'Gennemsnitlige timer pr. medarbejder brugt på kurser, intern træning og certificeringer i løbet af et år.' ],
                    [ 'term' => 'Governance-data',       'def' => 'Tal og indikatorer for ansvarlig virksomhedsdrift — herunder korruption, whistleblower-sager og etiktræning.' ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 6 — Cases
            // ──────────────────────────────────────────────────────────────────
            'cases' => [
                [
                    'tag'   => 'Mennesker',
                    'title' => 'Adgang til arbejdsmarkedet — på ordentlige vilkår',
                    'intro' => 'En konkret indsats, der skaber reel forskel',
                    'body'  => 'Gennem samarbejdet med »Små Job med Mening« har vi skabt muligheder for mennesker, der normalt holder sig uden for fuldtidsbeskæftigelse. Det er ikke et PR-stunt — det er en del af vores forpligtelse over for det samfund, vi er en del af.',
                ],
                [
                    'tag'   => 'Miljø',
                    'title' => 'Når en gammel computer ikke smides ud',
                    'intro' => 'IT-genbrug i praksis',
                    'body'  => 'I stedet for at udskifte computere efter fast cyklus reparerer og forlænger vi levetiden. Det sparer penge, men vigtigst af alt reducerer det det elektronikaffald, vi sender videre i verden.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 7 — FAQ
            // ──────────────────────────────────────────────────────────────────
            'faq' => [
                [ 'id' => 'faq-1', 'question' => 'Hvad er Rezponz\' vigtigste klimamål?',                   'answer' => 'Vi arbejder mod en 42 % reduktion af vores scope 1 og scope 2 CO₂-emissioner inden udgangen af 2030, målt fra vores 2023-baseline. Det er et konkret, tidsafgrænset mål — ikke et løfte om at »blive mere bæredygtige«.' ],
                [ 'id' => 'faq-2', 'question' => 'Hvordan arbejder Rezponz med ansvarlig drift?',           'answer' => 'Vi har en aktiv whistleblowerordning, gennemfører løbende etiktræning og har i 2024 haft nul korruptions- eller bestikkelseshændelser. Vi rapporterer åbent om det, vi gør — og det, vi endnu ikke har gjort.' ],
                [ 'id' => 'faq-3', 'question' => 'Hvordan omsætter Rezponz ESG til handling i hverdagen?', 'answer' => 'Konkret: vi bruger solenergi og grøn strøm, reparerer IT-udstyr fremfor at kassere det, og samarbejder aktivt for at skabe jobs til mennesker, der er svære at nå via normale ansættelseskanaler.' ],
                [ 'id' => 'faq-4', 'question' => 'Hvornår kortlægger Rezponz scope 3-emissioner?',         'answer' => 'Vi sætter scope 3-kortlægningen i gang i 2026. Det inkluderer emissioner fra leverandørkæde, medarbejdertransport og indirekte aktiviteter — en del, vi endnu ikke har fuld indsigt i.' ],
                [ 'id' => 'faq-5', 'question' => 'Hvem har ansvar for ESG hos Rezponz?',                   'answer' => 'ESG er et ledelsesansvar hos Rezponz. Data og indsatser forankres løbende i organisationen og rapporteres åbent i vores ESG-rapport.' ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 8 — CTA
            // ──────────────────────────────────────────────────────────────────
            'cta' => [
                'heading'                   => 'Vil du vide mere?',
                'body'                      => 'ESG er ikke et projekt med en slutdato — det er en løbende forpligtelse. Vi deler vores fremdrift åbent, fordi vi tror på, at transparens skaber bedre resultater.',
                'btn_primary_label'         => 'Læs ESG-rapporten',
                'btn_primary_url'           => '#esg-rapport',
                'btn_primary_track_event'   => 'esg_cta_report',
                'btn_secondary_label'       => 'Se vores konkrete indsatser',
                'btn_secondary_url'         => '#indsatser',
                'btn_secondary_track_event' => 'esg_cta_actions',
            ],

        ];
    }

} // end class RZPA_ESG
