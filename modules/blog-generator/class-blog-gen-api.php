<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPA_Blog_Gen_API
 *
 * REST-endpoints for Blog Generator modulet.
 * Namespace: rzpa/v1/blog-gen/…
 */
class RZPA_Blog_Gen_API {

    const NS = 'rzpa/v1';

    /** Standard brand voice-prompt — overrides med rzpa_settings['blog_gen_brand_voice'] */
    const DEFAULT_BRAND_VOICE = <<<'PROMPT'
Du skriver blogindlæg for Rezponz — et dansk kundeservicebureau i Aalborg.

TON & STIL:
- Varm, jordnært, direkte og nærværende
- Brug altid "du"-form (aldrig "man")
- Undgå corporate, stift, akademisk eller AI-agtig sprog
- Lyd som nogen man har lyst til at arbejde med eller hos
- Korte sætninger. Energi i sproget.

STRUKTUR (ALTID):
- Start med en kort, punch-agtig intro (MAX 3 linjer) — ingen lang indledning
- Brug mange H2-overskrifter — teksten skal være skanbar
- Gentag nøglebudskabet 2-3 gange i teksten på naturlig vis
- Placer CTA'er spredt ud i teksten (ikke kun i bunden)
- Afslut ALTID med en FAQ-sektion: <h2>Ofte stillede spørgsmål</h2> + mindst 3 <h3>spørgsmål</h3><p>svar</p>
- Afslut ALTID med en CTA-sektion der peger mod https://rezponz.dk

OUTPUT-FORMAT:
- KUN HTML: brug <h2>, <h3>, <p>, <ul>, <li>, <strong>
- INGEN <html>, <head>, <body> eller wrapper-tags
- INGEN markdown, INGEN kodeblokke
- FAQ JSON-LD schema til sidst som <script type="application/ld+json">{...}</script>
PROMPT;

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'rzpa_bg_generate_article', [ __CLASS__, 'bg_generate_article' ] );
    }

    public static function register_routes(): void {
        $cap = fn() => current_user_can( 'manage_options' );

        $routes = [
            [ 'GET',    'blog-gen/topics',                          'topics_list' ],
            [ 'POST',   'blog-gen/topics',                          'topics_add' ],
            [ 'DELETE', 'blog-gen/topics/(?P<id>\d+)',              'topics_delete' ],
            [ 'POST',   'blog-gen/topics/(?P<id>\d+)/generate',     'topics_generate' ],
            [ 'GET',    'blog-gen/topics/(?P<id>\d+)/status',       'topics_status' ],
            [ 'POST',   'blog-gen/suggest',                         'suggest_topics' ],
            [ 'GET',    'blog-gen/media',                           'media_list' ],
            [ 'GET',    'blog-gen/categories',                      'categories_list' ],
        ];

        foreach ( $routes as [ $method, $path, $cb ] ) {
            register_rest_route( self::NS, '/' . $path, [
                'methods'             => $method,
                'callback'            => [ __CLASS__, $cb ],
                'permission_callback' => $cap,
            ] );
        }
    }

    // ── Topics list ───────────────────────────────────────────────────────────

    public static function topics_list( WP_REST_Request $r ): WP_REST_Response {
        $status = sanitize_key( $r->get_param( 'status' ) ?? '' );
        $topics = RZPA_Blog_Gen_DB::get_topics( $status );

        // Berig med WP post URL hvis publiseret
        foreach ( $topics as $t ) {
            $t->post_url  = $t->wp_post_id ? get_permalink( (int) $t->wp_post_id ) : null;
            $t->post_edit = $t->wp_post_id ? get_edit_post_link( (int) $t->wp_post_id, 'raw' ) : null;
            $t->image_url = $t->image_id   ? wp_get_attachment_image_url( (int) $t->image_id, 'thumbnail' ) : null;
        }

        return new WP_REST_Response( $topics, 200 );
    }

    // ── Add topic ─────────────────────────────────────────────────────────────

    public static function topics_add( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $params = $r->get_json_params();
        $id     = RZPA_Blog_Gen_DB::insert_topic( $params );

        if ( ! $id ) {
            return new WP_Error( 'invalid_data', 'Mangler titel.', [ 'status' => 400 ] );
        }

        return new WP_REST_Response( [ 'id' => $id, 'ok' => true ], 201 );
    }

    // ── Delete topic ──────────────────────────────────────────────────────────

    public static function topics_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id    = (int) $r->get_param( 'id' );
        $topic = RZPA_Blog_Gen_DB::get_topic( $id );
        if ( ! $topic ) {
            return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );
        }
        RZPA_Blog_Gen_DB::delete_topic( $id );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── Trigger generation (async) ────────────────────────────────────────────

    public static function topics_generate( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id    = (int) $r->get_param( 'id' );
        $topic = RZPA_Blog_Gen_DB::get_topic( $id );

        if ( ! $topic ) {
            return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );
        }
        if ( $topic->status === 'generating' ) {
            return new WP_Error( 'already_generating', 'Generering er allerede i gang.', [ 'status' => 409 ] );
        }

        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_openai', 'Tilføj en OpenAI API-nøgle under Indstillinger.', [ 'status' => 400 ] );
        }

        // Opdater status → generating
        RZPA_Blog_Gen_DB::update_status( $id, 'generating', [ 'error_msg' => null ] );

        // Hent valgfrit image_id fra request (kan skifte ved re-generering)
        $image_id = (int) ( $r->get_json_params()['image_id'] ?? $topic->image_id ?? 0 );
        if ( $image_id ) {
            global $wpdb;
            $wpdb->update( $wpdb->prefix . 'rzpa_blog_topics', [ 'image_id' => $image_id ], [ 'id' => $id ] );
        }

        // Sæt async job i kø via WP Cron
        wp_schedule_single_event( time() + 1, 'rzpa_bg_generate_article', [ $id ] );
        spawn_cron();

        return new WP_REST_Response( [ 'ok' => true, 'status' => 'generating', 'topic_id' => $id ], 200 );
    }

    // ── Poll status ───────────────────────────────────────────────────────────

    public static function topics_status( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id    = (int) $r->get_param( 'id' );
        $topic = RZPA_Blog_Gen_DB::get_topic( $id );

        if ( ! $topic ) {
            return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( [
            'id'         => $id,
            'status'     => $topic->status,
            'wp_post_id' => $topic->wp_post_id,
            'post_edit'  => $topic->wp_post_id ? get_edit_post_link( (int) $topic->wp_post_id, 'raw' ) : null,
            'post_url'   => $topic->wp_post_id ? get_permalink( (int) $topic->wp_post_id ) : null,
            'error_msg'  => $topic->error_msg,
        ], 200 );
    }

    // ── AI Topic suggestions ──────────────────────────────────────────────────

    public static function suggest_topics( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) ) {
            return new WP_Error( 'no_openai', 'OpenAI API-nøgle mangler.', [ 'status' => 400 ] );
        }

        $keyword = sanitize_text_field( $r->get_json_params()['keyword'] ?? '' );
        $target  = sanitize_key( $r->get_json_params()['target'] ?? 'unge' );
        $count   = max( 3, min( 10, (int) ( $r->get_json_params()['count'] ?? 5 ) ) );

        $audience = $target === 'b2b'
            ? 'danske virksomheder der søger ekstern kundeservice-partner'
            : 'unge jobsøgende (19-25 år) der leder efter job i Aalborg';

        $prompt = <<<PROMPT
Foreslå {$count} konkrete dansksproget blogemner for Rezponz (kundeservicebureau i Aalborg).
Målgruppe: {$audience}.
{$keyword ? "Relatér til søgeordet: {$keyword}" : ""}

Krav til hvert emne:
- Titlen skal ligne et reelt Google-søgeord
- Skal fokusere på Rezponz' styrker: fællesskab, karriere, events, kundeservice-kvalitet
- Variér artikeltyper: forklaring, listicle, how-to

Svar KUN med en JSON-array — ingen forklaring, ingen markdown:
[
  {"title": "...", "article_type": "explainer|listicle|how-to", "pillar": "rekruttering|faellesskab|events|jobbet|outsourcing"},
  ...
]
PROMPT;

        $result = RZPA_AI_Helper::generate( $prompt, $opts['openai_api_key'], 600, 0.8, RZPA_AI_Helper::MODEL_FAST, 30 );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'ai_error', $result->get_error_message(), [ 'status' => 500 ] );
        }

        $clean    = RZPA_AI_Helper::strip_fences( $result );
        $topics   = json_decode( $clean, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $topics ) ) {
            return new WP_Error( 'parse_error', 'AI returnerede ugyldigt format.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( $topics, 200 );
    }

    // ── Media library ─────────────────────────────────────────────────────────

    public static function media_list(): WP_REST_Response {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $attachments = get_posts( $args );
        $images      = [];

        foreach ( $attachments as $att ) {
            $src = wp_get_attachment_image_url( $att->ID, 'medium' );
            if ( ! $src ) continue;
            $images[] = [
                'id'    => $att->ID,
                'url'   => $src,
                'thumb' => wp_get_attachment_image_url( $att->ID, 'thumbnail' ),
                'title' => $att->post_title,
                'alt'   => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
            ];
        }

        return new WP_REST_Response( $images, 200 );
    }

    // ── WordPress categories ──────────────────────────────────────────────────

    public static function categories_list(): WP_REST_Response {
        $cats = get_categories( [ 'hide_empty' => false ] );
        $list = array_map( fn( $c ) => [ 'id' => $c->term_id, 'name' => $c->name ], $cats );
        return new WP_REST_Response( $list, 200 );
    }

    // ── Background: generer artikel (kørt af WP Cron) ────────────────────────

    public static function bg_generate_article( int $topic_id ): void {
        $topic = RZPA_Blog_Gen_DB::get_topic( $topic_id );
        if ( ! $topic || $topic->status !== 'generating' ) return;

        $opts = get_option( 'rzpa_settings', [] );
        $key  = $opts['openai_api_key'] ?? '';
        if ( ! $key ) {
            RZPA_Blog_Gen_DB::update_status( $topic_id, 'failed', [ 'error_msg' => 'OpenAI API-nøgle mangler.' ] );
            return;
        }

        // Byg prompt
        $prompt = self::build_article_prompt( $topic );

        // Kald OpenAI — lang artikel, høj timeout
        $html = RZPA_AI_Helper::generate(
            $prompt,
            $key,
            3800,                          // ~2800-3500 ord output-tokens
            0.7,                           // kreativ men kontrolleret
            RZPA_AI_Helper::MODEL_ARTICLE, // gpt-4o
            90                             // 90s timeout
        );

        if ( is_wp_error( $html ) ) {
            RZPA_Blog_Gen_DB::update_status( $topic_id, 'failed', [
                'error_msg' => $html->get_error_message(),
            ] );
            return;
        }

        $html = RZPA_AI_Helper::strip_fences( $html );

        // Udtræk og valider FAQ JSON-LD
        $faq_schema = RZPA_AI_Helper::build_validated_faq_schema( $html );
        // Fjern <script> fra post_content (wp_kses_post ville fjerne den alligevel)
        $clean_html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );

        // Opret WP-udkast
        $cat_id   = (int) ( $opts['blog_gen_category'] ?? 0 );
        $post_arr = [
            'post_title'   => wp_strip_all_tags( $topic->title ),
            'post_content' => wp_kses_post( $clean_html ),
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => 1,
        ];
        if ( $cat_id && term_exists( $cat_id, 'category' ) ) {
            $post_arr['post_category'] = [ $cat_id ];
        }

        $post_id = wp_insert_post( $post_arr, true );

        if ( is_wp_error( $post_id ) ) {
            RZPA_Blog_Gen_DB::update_status( $topic_id, 'failed', [
                'error_msg' => 'WP post-oprettelse fejlede: ' . $post_id->get_error_message(),
            ] );
            return;
        }

        // Gem FAQ schema i post meta (outputtes via wp_head-hook i main plugin)
        if ( $faq_schema ) {
            update_post_meta( $post_id, '_rzpa_faq_schema', $faq_schema );
        }

        // Featured image fra Mediebiblioteket
        if ( $topic->image_id ) {
            set_post_thumbnail( $post_id, (int) $topic->image_id );
        }

        // Gem Yoast focus keyword hvis tilgængeligt
        update_post_meta( $post_id, '_yoast_wpseo_focuskw', wp_strip_all_tags( $topic->keywords ?: $topic->title ) );

        RZPA_Blog_Gen_DB::update_status( $topic_id, 'done', [ 'wp_post_id' => $post_id ] );
    }

    // ── Private: byg artikel-prompt ───────────────────────────────────────────

    private static function build_article_prompt( object $topic ): string {
        $opts       = get_option( 'rzpa_settings', [] );
        $brand      = trim( $opts['blog_gen_brand_voice'] ?? '' ) ?: self::DEFAULT_BRAND_VOICE;
        $type_label = RZPA_Blog_Gen_DB::ARTICLE_TYPES[ $topic->article_type ] ?? 'Forklaringsartikel';
        $target_lbl = RZPA_Blog_Gen_DB::TARGETS[ $topic->target ] ?? 'Unge jobsøgende';
        $words      = (int) $topic->word_count;
        $faq_note   = $topic->include_faq ? 'HUSK: Inkluder FAQ-sektion og JSON-LD schema.' : '';

        return <<<PROMPT
{$brand}

---

OPGAVE:
Skriv en {$type_label} på dansk med titlen: "{$topic->title}"

Målgruppe: {$target_lbl}
Søgeord/nøgleord: {$topic->keywords}
Ønsket længde: ca. {$words} ord
{$faq_note}

Følg brand voice og struktur-kravene nøjagtigt som beskrevet ovenfor.
PROMPT;
    }
}
