<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_CRM_AON — AON Talent Assessment Integration
 *
 * Håndterer integration med AON's Talent Assessment API:
 *   - Opretter testinvitationer, når kandidater ansøger
 *   - Modtager webhook-callbacks, når kandidater gennemfører testen
 *   - Gemmer testresultater på ansøgningen i databasen
 *
 * Settings-nøgler (gemmes i rzpa_settings):
 *   aon_api_key          — Bearer API-nøgle fra AON
 *   aon_base_url         — Grundadresse til AON API (uden afsluttende skråstreg)
 *   aon_project_id       — Assessment/projekt-ID som kandidater skal tage
 *   aon_webhook_secret   — Valgfri shared secret til signaturvalidering
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VIGTIG NOTE TIL FREMTIDIG INTEGRATION:
 * ─────────────────────────────────────────────────────────────────────────────
 * Denne klasse er bygget med placeholder payload- og felt-navne, da AON's
 * faktiske API-dokumentation endnu ikke er tilgængelig.
 *
 * Der er to steder, der SKAL opdateres, når AON's dokumentation modtages:
 *   1. create_invitation()  — request payload og response felt-navne
 *   2. handle_webhook()     — webhook payload felt-navne
 *
 * Søg efter kommentarerne "TILPAS" i koden herunder.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class RZPZ_CRM_AON {

    // ── Public: Is AON configured? ───────────────────────────────────────────

    /**
     * Returnerer true hvis AON API-nøgle og base URL begge er konfigureret.
     * Bruges til guard-checks i resten af koden.
     */
    public static function is_configured(): bool {
        $opts = get_option( 'rzpa_settings', [] );
        return ! empty( $opts['aon_api_key'] ) && ! empty( $opts['aon_base_url'] );
    }

    // ── Public: Create invitation ────────────────────────────────────────────

    /**
     * Opretter en AON testinvitation for en kandidat.
     *
     * Kaldes fra api_applications_create() INDEN bekræftelsesmailen sendes,
     * så {{aon_test_link}} merge-taggen er tilgængelig i emailen.
     *
     * @param object $app            Ansøgnings-objekt (rækken fra DB inkl. ansøger-data)
     * @param int    $application_id Ansøgningens ID i rzpz_crm_applications
     * @return string|null           Invitations-URL hvis succesfuld, null ellers
     */
    public static function create_invitation( object $app, int $application_id ): ?string {
        if ( ! self::is_configured() ) return null;

        $opts       = get_option( 'rzpa_settings', [] );
        $api_key    = $opts['aon_api_key']    ?? '';
        $base_url   = rtrim( $opts['aon_base_url'] ?? '', '/' );
        $project_id = $opts['aon_project_id'] ?? '';

        // Callback-URL til AON, så vi modtager resultater når kandidaten er færdig
        $webhook_url = rest_url( 'rzpa/v1/crm/aon/webhook' );

        // ─────────────────────────────────────────────────────────────────────
        // TILPAS: Payload-struktur til faktisk AON API-format.
        //
        // Det nedenstående er et gætteri baseret på typiske assessment API'er.
        // Når AON's API-dokumentation foreligger, skal følgende muligvis ændres:
        //   - Felt-navne (project_id, reference, candidate, callback_url, osv.)
        //   - Nesting-struktur (evt. fladere eller dybere hierarki)
        //   - Ekstra påkrævede felter (sprog, testtype, udløbsdato, osv.)
        //   - HTTP-metode / endpoint-sti
        // ─────────────────────────────────────────────────────────────────────
        $payload = [
            'project_id'   => $project_id,
            'reference'    => 'rzpz-app-' . $application_id,
            'candidate'    => [
                'email'      => $app->email      ?? '',
                'first_name' => $app->first_name ?? '',
                'last_name'  => $app->last_name  ?? '',
            ],
            'callback_url' => $webhook_url,
        ];

        $response = wp_remote_post( $base_url . '/invitations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        // Fejlhåndtering: WP HTTP-lag fejl (netværk, timeout, osv.)
        if ( is_wp_error( $response ) ) {
            error_log( '[RezCRM AON] create_invitation WP_Error for application #' . $application_id . ': ' . $response->get_error_message() );
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $body_raw, true );

        // Fejlhåndtering: AON returnerede ikke 2xx
        if ( $status_code < 200 || $status_code >= 300 ) {
            error_log( sprintf(
                '[RezCRM AON] create_invitation HTTP %d for application #%d — response: %s',
                $status_code,
                $application_id,
                substr( $body_raw, 0, 500 )
            ) );
            return null;
        }

        // ─────────────────────────────────────────────────────────────────────
        // TILPAS: Response felt-navne til faktisk AON API-svar.
        //
        // Vi prøver flere mulige navne som fallback, da vi ikke kender det
        // præcise format. Tilpas rækkefølgen/navnene når dokumentationen foreligger:
        //   - invitation_id: typisk 'invitation_id', 'id', 'assessment_id'
        //   - invitation_url: typisk 'invitation_url', 'url', 'assessment_url', 'link'
        // ─────────────────────────────────────────────────────────────────────
        $invitation_id  = $body['invitation_id']  ?? $body['id']             ?? null;
        $invitation_url = $body['invitation_url'] ?? $body['url']            ?? $body['assessment_url'] ?? null;

        if ( ! $invitation_url ) {
            error_log( '[RezCRM AON] create_invitation: Kunne ikke finde invitation URL i svar for application #' . $application_id . ' — body: ' . substr( $body_raw, 0, 500 ) );
            return null;
        }

        // Gem AON-data på ansøgningen i databasen
        self::update_application_aon(
            $application_id,
            (string) ( $invitation_id ?? '' ),
            (string) $invitation_url,
            'pending'
        );

        return $invitation_url;
    }

    // ── Public: Handle webhook ───────────────────────────────────────────────

    /**
     * Modtager AON webhook-callback, når en kandidat gennemfører sin test.
     *
     * AON kalder denne endpoint (POST rzpa/v1/crm/aon/webhook) med testresultater.
     * Endpointet er offentligt (ingen WP-autentifikation), men kan sikres med
     * en shared secret via X-AON-Signature headeren.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function handle_webhook( WP_REST_Request $req ): WP_REST_Response {
        $opts   = get_option( 'rzpa_settings', [] );
        $secret = $opts['aon_webhook_secret'] ?? '';

        // Valgfri signaturvalidering — kun aktiv hvis aon_webhook_secret er konfigureret
        if ( ! empty( $secret ) ) {
            $signature = $req->get_header( 'X-AON-Signature' );
            $raw_body  = $req->get_body();

            // ─────────────────────────────────────────────────────────────────
            // TILPAS: Signaturalgoritme til faktisk AON webhook-format.
            //
            // De fleste webhook-systemer bruger HMAC-SHA256 af raw body.
            // AON's dokumentation kan kræve et andet format, fx:
            //   - sha256=<hex>  (GitHub-stil med præfix)
            //   - kun rå HMAC-hex (ingen præfix)
            //   - sha1 eller MD5 (mindre sandsynligt, men muligt)
            // ─────────────────────────────────────────────────────────────────
            $expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $secret );

            if ( ! hash_equals( $expected, (string) $signature ) ) {
                error_log( '[RezCRM AON] handle_webhook: Ugyldig signatur — muligvis forkert webhook secret' );
                return new WP_REST_Response( [ 'error' => 'Ugyldig signatur' ], 401 );
            }
        }

        $data = $req->get_json_params();

        // ─────────────────────────────────────────────────────────────────────
        // TILPAS: Felt-navne til faktisk AON webhook-format.
        //
        // Justér nedenstående nøglenavne til AON's faktiske payload-struktur:
        //   - reference:     Vores reference-ID (vi sender 'rzpz-app-{id}')
        //   - invitation_id: AON's interne invitation-ID
        //   - status:        Testens status ('completed', 'failed', osv.)
        //   - results:       Testresultater (evt. 'scores', 'report', 'outcome')
        // ─────────────────────────────────────────────────────────────────────
        $reference     = $data['reference']     ?? '';
        $invitation_id = $data['invitation_id'] ?? $data['id'] ?? '';
        $status        = sanitize_text_field( $data['status'] ?? '' );
        $results       = $data['results']       ?? $data['scores'] ?? null;

        // Find ansøgnings-ID fra reference-strengen 'rzpz-app-{id}'
        $application_id = 0;
        if ( preg_match( '/rzpz-app-(\d+)/', (string) $reference, $m ) ) {
            $application_id = (int) $m[1];
        }

        // Fallback: slå op via invitation_id i databasen
        if ( ! $application_id && $invitation_id ) {
            $application_id = self::find_application_by_invitation_id( (string) $invitation_id );
        }

        if ( ! $application_id ) {
            error_log( '[RezCRM AON] handle_webhook: Kunne ikke finde ansøgning for reference "' . esc_html( $reference ) . '" / invitation_id "' . esc_html( (string) $invitation_id ) . '"' );
            return new WP_REST_Response( [ 'error' => 'Ansøgning ikke fundet' ], 404 );
        }

        // Opdatér ansøgningen med testresultater
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_applications',
            [
                'aon_status'       => $status,
                'aon_completed_at' => current_time( 'mysql' ),
                'aon_result_json'  => $results !== null ? wp_json_encode( $results ) : null,
            ],
            [ 'id' => $application_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        error_log( '[RezCRM AON] Webhook modtaget for application #' . $application_id . ' — status: ' . $status );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── Public: Status label ─────────────────────────────────────────────────

    /**
     * Returnerer dansk label for en AON teststatus.
     *
     * @param string $status AON status-streng
     * @return string        Brugervenlig dansk label
     */
    public static function get_status_label( string $status ): string {
        return match( $status ) {
            'pending'   => 'Afventer svar',
            'completed' => 'Gennemført ✓',
            'failed'    => 'Fejlet',
            default     => $status,
        };
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Opdaterer AON-kolonner på en ansøgning i databasen.
     */
    private static function update_application_aon(
        int    $application_id,
        string $invitation_id,
        string $invitation_url,
        string $status
    ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_applications',
            [
                'aon_invitation_id'  => $invitation_id  ?: null,
                'aon_invitation_url' => $invitation_url ?: null,
                'aon_status'         => $status         ?: null,
            ],
            [ 'id' => $application_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Slår ansøgnings-ID op via aon_invitation_id i databasen.
     * Bruges som fallback i webhook-handler, hvis reference-feltet mangler.
     *
     * @param string $invitation_id AON's invitation ID
     * @return int                  Ansøgnings-ID, 0 hvis ikke fundet
     */
    private static function find_application_by_invitation_id( string $invitation_id ): int {
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rzpz_crm_applications WHERE aon_invitation_id = %s LIMIT 1",
            $invitation_id
        ) );
        return (int) ( $id ?? 0 );
    }
}
