<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPA_Blog_Gen_API
 * Namespace: rzpa/v1/blog-gen/…
 */
class RZPA_Blog_Gen_API {

    const NS = 'rzpa/v1';

    // ── Brand Voice Default ───────────────────────────────────────────────────
    // Overrides med rzpa_settings['blog_gen_brand_voice']
    const DEFAULT_BRAND_VOICE = <<<'PROMPT'
Du er en professionel dansk content-skribent, der skriver for Rezponz.
Rezponz er et dansk kundeservicebureau baseret i Aalborg — grundlagt på fællesskab, faglighed og energi.

━━━ OM REZPONZ (brug disse fakta aktivt i teksten) ━━━
- Vi er et vækstorienteret kundeservicebureau i Aalborg, Nordjylland
- Vores medarbejdere er primært unge (19-25 år), og vi er stolte af det
- Rezponz Academy: vores interne uddannelsesprogram der løfter alle medarbejdere fagligt
- Rezponz Challenge: vores årlige team-event (Functional Fitness Racing) — alle kan være med, du behøver ikke være i form
- Rezponz Futsal Cup: vores interne fodboldturnering — del af kulturen
- Vi betjener kendte brands som Telenor, CBB Mobil og NRGi
- Vi tilbyder outsourcing af kundeservice til virksomheder i hele Danmark
- Vores kultur er bygget på: teamwork, ærlig feedback, personlig udvikling og god energi

━━━ MÅLGRUPPE & TON ━━━
- Tal altid direkte til ÉN person med "du" — aldrig "man" eller "I"
- Sproget er varmt, jordnært og nærværende — som en besked fra en kollega der holder af dig
- Lyd ægte. Lyd dansk. Lyd som et menneske — IKKE som en AI
- Aldrig stift, corporate, akademisk eller distanceret
- Brug korte sætninger. Og indimellem én linje alene. Det giver rytme.

━━━ FORBUDTE FRASER (brug ALDRIG disse) ━━━
- "I en verden der konstant forandrer sig..."
- "Det er værd at bemærke..."
- "Som vi alle ved..."
- "I konklusion..." / "Afslutningsvis..."
- "Det er ingen hemmelighed at..."
- "Navigere i..." (som metafor)
- "Løfte i flok" (kliché)
- Unødvendige tillægsord: "utrolig", "fantastisk", "ekstraordinær"
- Lange opstartssætninger der ikke siger noget

━━━ SØGEORD & SEO ━━━
- Placer fokussøgeordet i H1/titlen, i de første 100 ord og i mindst 2 H2-overskrifter
- Gentag søgeordet naturligt 3-4 gange — aldrig stumt indsat
- Brug LSI-søgeord (relaterede begreber) naturligt igennem teksten

━━━ STRUKTUR (ALTID DENNE RÆKKEFØLGE) ━━━
1. Kort punch-intro: MAX 2-3 linjer. Fang med et spørgsmål, en kendsgerning eller en situation
2. [Evt. TL;DR-boks hvis anmodet — se opgave nedenfor]
3. [Evt. Indholdsfortegnelse hvis anmodet — se opgave nedenfor]
4. Body: mange H2-sektioner, skanbar og informativ
5. Mindst én CTA midt i teksten — peg mod https://rezponz.dk/jobs eller relevant side
6. [Evt. interne links til relaterede Rezponz-sider hvis anmodet]
7. FAQ-sektion: <h2>Ofte stillede spørgsmål</h2> med mindst 3 <h3>+<p>
8. Afslutnings-CTA med konkret opfordring

━━━ GEO & AI-SYNLIGHED (ChatGPT, Perplexity, Gemini) ━━━
- Skriv MINDST 2-3 citerbare, definitive sætninger som AI-assistenter kan citere direkte
  Eksempel: "Rezponz er Nordjyllands førende kundeservicebureau med speciale i telefon- og chat-support."
- Inkluder konkrete tal, navne og fakta hvor relevant — AI-modeller stoler på entiteter
- Skriv én "ekspert-sætning" per sektion — en klar, autoritativ påstand

━━━ INTERNE LINKS (inkluder naturligt i teksten) ━━━
- Ledige stillinger: https://rezponz.dk/jobs
- Om Rezponz: https://rezponz.dk/om-rezponz
- Rezponz Academy: https://rezponz.dk/rezponz-academy
- Blog: https://rezponz.dk/rezponz-blog-insights
- Outsourcing-info: https://rezponz.dk/kundeservice-outsourcing

━━━ OUTPUT-FORMAT ━━━
Start ALTID med en JSON-blok på første linje (ingen tekst før):
{"seo_title":"<SEO-titel max 60 tegn>","seo_desc":"<Meta description 120-155 tegn med søgeord>","focus_kw":"<primært søgeord>"}

Derefter KUN HTML: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <a href="...">
INGEN <html>, <head>, <body>, INGEN markdown, INGEN kodeblokke
FAQ JSON-LD schema til sidst: <script type="application/ld+json">{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[...]}</script>
PROMPT;

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'rzpa_bg_generate_article', [ __CLASS__, 'bg_generate_article' ] );
    }

    public static function register_routes(): void {
        $cap = fn() => current_user_can( 'manage_options' );

        $routes = [
            [ 'GET',    'blog-gen/topics',                        'topics_list' ],
            [ 'POST',   'blog-gen/topics',                        'topics_add' ],
            [ 'PATCH',  'blog-gen/topics/(?P<id>\d+)',            'topics_update' ],
            [ 'DELETE', 'blog-gen/topics/(?P<id>\d+)',            'topics_delete' ],
            [ 'POST',   'blog-gen/topics/(?P<id>\d+)/generate',   'topics_generate' ],
            [ 'GET',    'blog-gen/topics/(?P<id>\d+)/status',     'topics_status' ],
            [ 'POST',   'blog-gen/suggest',                       'suggest_topics' ],
            [ 'GET',    'blog-gen/media',                         'media_list' ],
            [ 'GET',    'blog-gen/categories',                    'categories_list' ],
            [ 'GET',    'blog-gen/calendar/(?P<ym>[0-9]{4}-[0-9]{2})', 'calendar_get' ],
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

    public static function topics_list(): WP_REST_Response {
        $topics = RZPA_Blog_Gen_DB::get_topics();
        foreach ( $topics as $t ) {
            $t->post_url  = $t->wp_post_id ? get_permalink( (int) $t->wp_post_id ) : null;
            $t->post_edit = $t->wp_post_id ? get_edit_post_link( (int) $t->wp_post_id, 'raw' ) : null;
            $t->image_url = $t->image_id   ? wp_get_attachment_image_url( (int) $t->image_id, 'thumbnail' ) : null;
        }
        return new WP_REST_Response( $topics, 200 );
    }

    // ── Add topic ─────────────────────────────────────────────────────────────

    public static function topics_add( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = RZPA_Blog_Gen_DB::insert_topic( $r->get_json_params() );
        if ( ! $id ) return new WP_Error( 'invalid_data', 'Mangler titel.', [ 'status' => 400 ] );
        return new WP_REST_Response( [ 'id' => $id, 'ok' => true ], 201 );
    }

    // ── Delete topic ──────────────────────────────────────────────────────────

    public static function topics_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = (int) $r->get_param( 'id' );
        if ( ! RZPA_Blog_Gen_DB::get_topic( $id ) )
            return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );
        RZPA_Blog_Gen_DB::delete_topic( $id );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── Update topic fields (PATCH) ───────────────────────────────────────────

    public static function topics_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id    = (int) $r->get_param( 'id' );
        $topic = RZPA_Blog_Gen_DB::get_topic( $id );
        if ( ! $topic ) return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );

        $params  = $r->get_json_params();
        $allowed = [ 'image_id', 'scheduled_for', 'post_date', 'publish_immediately',
                     'include_faq', 'include_toc', 'include_tldr', 'include_internal_links',
                     'title', 'keywords', 'pillar', 'article_type', 'target', 'word_count' ];
        $update  = [];
        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $params ) ) continue;
            if ( in_array( $key, [ 'image_id', 'publish_immediately', 'include_faq', 'include_toc', 'include_tldr', 'include_internal_links', 'word_count' ], true ) ) {
                $update[ $key ] = (int) $params[ $key ];
            } elseif ( in_array( $key, [ 'scheduled_for', 'post_date' ], true ) ) {
                $ts = $params[ $key ] ? strtotime( $params[ $key ] ) : null;
                $update[ $key ] = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
            } else {
                $update[ $key ] = sanitize_text_field( $params[ $key ] );
            }
        }

        if ( empty( $update ) ) return new WP_Error( 'no_fields', 'Ingen gyldige felter.', [ 'status' => 400 ] );

        global $wpdb;
        $t = $wpdb->prefix . 'rzpa_blog_topics';
        $wpdb->update( $t, $update, [ 'id' => $id ] );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── Calendar: topics for a given month ───────────────────────────────────

    public static function calendar_get( WP_REST_Request $r ): WP_REST_Response {
        $ym     = sanitize_text_field( $r->get_param( 'ym' ) ); // YYYY-MM
        $topics = RZPA_Blog_Gen_DB::get_calendar_topics( $ym );

        foreach ( $topics as $t ) {
            $t->post_url = $t->wp_post_id ? get_permalink( (int) $t->wp_post_id ) : null;
        }
        return new WP_REST_Response( $topics, 200 );
    }

    // ── Trigger generation (async) ────────────────────────────────────────────

    public static function topics_generate( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id    = (int) $r->get_param( 'id' );
        $topic = RZPA_Blog_Gen_DB::get_topic( $id );

        if ( ! $topic ) return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );
        if ( $topic->status === 'generating' )
            return new WP_Error( 'already_generating', 'Generering er allerede i gang.', [ 'status' => 409 ] );

        $opts = get_option( 'rzpa_settings', [] );
        if ( empty( $opts['openai_api_key'] ) )
            return new WP_Error( 'no_openai', 'Tilføj en OpenAI API-nøgle under Indstillinger.', [ 'status' => 400 ] );

        $extra = [ 'error_msg' => null, 'retry_count' => 0 ];
        if ( ! empty( $r->get_json_params()['image_id'] ) )
            $extra['image_id'] = (int) $r->get_json_params()['image_id'];
        if ( ! empty( $r->get_json_params()['scheduled_for'] ) )
            $extra['scheduled_for'] = sanitize_text_field( $r->get_json_params()['scheduled_for'] );

        // Atomisk lock — forhindrer race condition ved dobbelt-klik
        if ( ! RZPA_Blog_Gen_DB::try_lock_generating( $id ) ) {
            return new WP_Error( 'already_generating', 'Generering er allerede i gang.', [ 'status' => 409 ] );
        }
        // Gem evt. ekstra felter oven på lock
        if ( ! empty( $extra ) ) {
            RZPA_Blog_Gen_DB::update_status( $id, 'generating', $extra );
        }

        wp_schedule_single_event( time() + 1, 'rzpa_bg_generate_article', [ $id ] );

        // Trigger cron uden loopback (virker på Curanet shared hosting)
        self::trigger_cron_safe();

        return new WP_REST_Response( [ 'ok' => true, 'status' => 'generating', 'topic_id' => $id ], 200 );
    }

    // ── Poll status ───────────────────────────────────────────────────────────

    public static function topics_status( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id    = (int) $r->get_param( 'id' );
        $topic = RZPA_Blog_Gen_DB::get_topic( $id );
        if ( ! $topic ) return new WP_Error( 'not_found', 'Emne ikke fundet.', [ 'status' => 404 ] );

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
        if ( empty( $opts['openai_api_key'] ) )
            return new WP_Error( 'no_openai', 'OpenAI API-nøgle mangler.', [ 'status' => 400 ] );

        $keyword = sanitize_text_field( $r->get_json_params()['keyword'] ?? '' );
        $target  = sanitize_key( $r->get_json_params()['target'] ?? 'unge' );
        $count   = max( 3, min( 10, (int) ( $r->get_json_params()['count'] ?? 6 ) ) );

        $audience = $target === 'b2b'
            ? 'danske virksomheder der søger ekstern kundeservice-partner (B2B-beslutningstagere)'
            : 'unge jobsøgende (19-25 år) der leder efter job i kundeservice i Nordjylland/Danmark';

        $prompt = "Foreslå {$count} konkrete, dansksproget blogemner for Rezponz (kundeservicebureau i Aalborg).\n"
            . "Målgruppe: {$audience}.\n"
            . ( $keyword ? "Relater til søgeordet: {$keyword}\n" : '' )
            . "\nKrav til hvert emne:\n"
            . "- Titlen skal ligne et reelt dansk Google-søgeord folk søger efter\n"
            . "- Variér artikeltyper: explainer, listicle, how-to\n"
            . "- Fokuser på Rezponz' styrker: fællesskab, karriere, events, kundeservice-kvalitet\n"
            . "- Estimer SEO-sværhedsgrad 1-10 og søgevolumen (1=lavt, 10=højt)\n"
            . "\nSvar KUN med JSON-array, ingen forklaring:\n"
            . '[{"title":"...","article_type":"explainer|listicle|how-to","pillar":"rekruttering|faellesskab|events|jobbet|outsourcing","difficulty":3,"search_volume":5}]';

        $result = RZPA_AI_Helper::generate( $prompt, $opts['openai_api_key'], 700, 0.8, RZPA_AI_Helper::MODEL_FAST, 30 );
        if ( is_wp_error( $result ) )
            return new WP_Error( 'ai_error', $result->get_error_message(), [ 'status' => 500 ] );

        $topics = json_decode( RZPA_AI_Helper::strip_fences( $result ), true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $topics ) )
            return new WP_Error( 'parse_error', 'AI returnerede ugyldigt format.', [ 'status' => 500 ] );

        return new WP_REST_Response( $topics, 200 );
    }

    // ── Media library ─────────────────────────────────────────────────────────

    public static function media_list(): WP_REST_Response {
        $attachments = get_posts( [
            'post_type' => 'attachment', 'post_mime_type' => 'image',
            'post_status' => 'inherit', 'posts_per_page' => 60,
            'orderby' => 'date', 'order' => 'DESC',
        ] );
        $images = [];
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
        return new WP_REST_Response(
            array_map( fn( $c ) => [ 'id' => $c->term_id, 'name' => $c->name ], $cats ),
            200
        );
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

        // Dynamisk max_tokens baseret på word_count (~1.5 tokens per ord + overhead)
        $words      = max( 600, (int) $topic->word_count );
        $max_tokens = min( 4096, (int) round( $words * 1.6 + 600 ) );

        $raw = RZPA_AI_Helper::generate(
            self::build_article_prompt( $topic ),
            $key,
            $max_tokens,
            0.7,
            RZPA_AI_Helper::MODEL_ARTICLE,
            90
        );

        if ( is_wp_error( $raw ) ) {
            $retry = (int) ( $topic->retry_count ?? 0 );
            if ( $retry < 2 ) {
                // Auto-retry op til 2 gange
                RZPA_Blog_Gen_DB::update_status( $topic_id, 'generating', [
                    'retry_count' => $retry + 1,
                    'error_msg'   => 'Forsøg ' . ( $retry + 1 ) . ': ' . $raw->get_error_message(),
                ] );
                wp_schedule_single_event( time() + 30 + ( $retry * 60 ), 'rzpa_bg_generate_article', [ $topic_id ] );
            } else {
                RZPA_Blog_Gen_DB::update_status( $topic_id, 'failed', [
                    'error_msg' => $raw->get_error_message(),
                ] );
            }
            return;
        }

        $raw = RZPA_AI_Helper::strip_fences( $raw );

        // ── Parse SEO-meta JSON fra første linje ──────────────────────────────
        $seo_title = '';
        $seo_desc  = '';
        $focus_kw  = wp_strip_all_tags( $topic->keywords ?: $topic->title );
        $html      = $raw;

        if ( preg_match( '/^\s*(\{[^\n]{20,500}\})\s*\n/s', $raw, $jm ) ) {
            $meta = json_decode( trim( $jm[1] ), true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $seo_title = sanitize_text_field( $meta['seo_title'] ?? '' );
                $seo_desc  = sanitize_text_field( $meta['seo_desc']  ?? '' );
                $focus_kw  = sanitize_text_field( $meta['focus_kw']  ?? $focus_kw );
                $html      = substr( $raw, strlen( $jm[0] ) );
            }
        }

        // ── Udtræk FAQ JSON-LD ────────────────────────────────────────────────
        $faq_schema = self::extract_faq_schema( $html );
        $clean_html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
        $clean_html = wp_kses_post( $clean_html );

        // ── Bestem post_status (draft / publish / future) ─────────────────────
        $publish_mode = (int) ( $topic->publish_immediately ?? 0 );
        $post_date    = ! empty( $topic->post_date ) ? $topic->post_date : null;
        $post_ts      = $post_date ? strtotime( $post_date ) : 0;
        $future       = $post_ts && $post_ts > time();

        if ( $publish_mode ) {
            if ( $future ) {
                $post_status = 'future';   // Planlagt til fremtidig publicering
            } elseif ( $post_ts && $post_ts < ( time() - 60 ) ) {
                // Bagdateret dato — opret som udkast for at undgå lydløs publicering
                $post_status = 'draft';
                error_log( '[Rezponz Blog Gen] post_date i fortiden — opretter som udkast (topic_id=' . $topic_id . ')' );
            } else {
                $post_status = 'publish';
            }
        } else {
            $post_status = 'draft';
        }

        // ── Bestem post_author ────────────────────────────────────────────────
        // I cron-kontekst er get_current_user_id() = 0; brug konfigureret default-author
        $author_id = get_current_user_id();
        if ( ! $author_id ) {
            $author_id = (int) ( $opts['blog_gen_default_author'] ?? 0 );
        }
        if ( ! $author_id ) $author_id = 1; // Absolut fallback: admin

        // ── Opret WP-post ─────────────────────────────────────────────────────
        $cat_id   = (int) ( $opts['blog_gen_category'] ?? 0 );
        $post_arr = [
            'post_title'   => wp_strip_all_tags( $topic->title ),
            'post_name'    => sanitize_title( $focus_kw ?: $topic->title ),
            'post_content' => $clean_html,
            'post_status'  => $post_status,
            'post_type'    => 'post',
            'post_author'  => $author_id,
        ];
        if ( $post_date ) {
            $post_arr['post_date']     = $post_date;
            $post_arr['post_date_gmt'] = get_gmt_from_date( $post_date ); // Fix GMT-fejl
        }
        if ( $cat_id && term_exists( $cat_id, 'category' ) )
            $post_arr['post_category'] = [ $cat_id ];

        $post_id = wp_insert_post( $post_arr, true );
        if ( is_wp_error( $post_id ) ) {
            RZPA_Blog_Gen_DB::update_status( $topic_id, 'failed', [
                'error_msg' => 'WP post-fejl: ' . $post_id->get_error_message(),
            ] );
            return;
        }

        // ── SEO title + desc (skal defineres her — bruges både til meta og JSON-LD) ──
        $yoast_title = $seo_title ?: ( $topic->title . ' | Rezponz' );
        $yoast_desc  = $seo_desc  ?: '';

        // ── FAQ schema ────────────────────────────────────────────────────────
        if ( $faq_schema ) update_post_meta( $post_id, '_rzpa_faq_schema', $faq_schema );

        // ── Article JSON-LD ───────────────────────────────────────────────────
        $article_schema = self::build_article_schema( $post_id, $topic->title, $focus_kw, $yoast_desc, (int) $topic->word_count );
        update_post_meta( $post_id, '_rzpa_article_schema', $article_schema );

        // ── Featured image ────────────────────────────────────────────────────
        if ( $topic->image_id ) set_post_thumbnail( $post_id, (int) $topic->image_id );

        // ── Elementor Single Post Template ────────────────────────────────────
        // Elementor Theme Builder bruger '_elementor_conditions' på TEMPLATE-posten
        // til at afgøre hvilke posts det skal rendere. Vi tilføjer en betingelse:
        // "inkluder singulær post med ID $post_id" → korrekt Elementor API.
        $elementor_template_id = (int) ( $opts['blog_gen_elementor_template_id'] ?? 0 );
        if ( $elementor_template_id ) {
            $conditions = get_post_meta( $elementor_template_id, '_elementor_conditions', true );
            if ( ! is_array( $conditions ) ) $conditions = [];
            $condition = 'include/singular/post/' . $post_id;
            if ( ! in_array( $condition, $conditions, true ) ) {
                $conditions[] = $condition;
                update_post_meta( $elementor_template_id, '_elementor_conditions', $conditions );
                // Ryd Elementors betingelses-cache så ændringen træder i kraft med det samme
                delete_option( 'elementor_conditions_cache' );
                delete_transient( 'elementor_conditions_cache' );
            }
        }
        // Aktivér Elementor's renderer for dette post (kræves for at content renderes korrekt)
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

        // ── Yoast SEO ─────────────────────────────────────────────────────────
        update_post_meta( $post_id, '_yoast_wpseo_focuskw',                 $focus_kw );
        update_post_meta( $post_id, '_yoast_wpseo_title',                   $yoast_title );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc',                $yoast_desc );
        update_post_meta( $post_id, '_yoast_wpseo_opengraph-title',         $yoast_title );
        update_post_meta( $post_id, '_yoast_wpseo_opengraph-description',   $yoast_desc );
        update_post_meta( $post_id, '_yoast_wpseo_twitter-title',           $yoast_title );
        update_post_meta( $post_id, '_yoast_wpseo_twitter-description',     $yoast_desc );

        // ── RankMath ──────────────────────────────────────────────────────────
        update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_kw );
        update_post_meta( $post_id, 'rank_math_description',   $yoast_desc );
        update_post_meta( $post_id, 'rank_math_title',         $yoast_title );

        // ── SEOPress ─────────────────────────────────────────────────────────
        update_post_meta( $post_id, '_seopress_titles_title', $yoast_title );
        update_post_meta( $post_id, '_seopress_titles_desc',  $yoast_desc );

        RZPA_Blog_Gen_DB::update_status( $topic_id, 'done', [ 'wp_post_id' => $post_id ] );
    }

    // ── Cron trigger (loopback-safe) ─────────────────────────────────────────

    /**
     * Trigger WP Cron uden at kræve HTTP loopback.
     * Prøver spawn_cron() (kræver loopback) — hvis det fejler,
     * kører cron-eventet direkte i denne request (sync fallback).
     * På Curanet shared hosting er loopback typisk blokeret.
     */
    private static function trigger_cron_safe(): void {
        // Forhindre dobbelt-trigger hvis cron allerede kører
        $doing_cron_transient = get_transient( 'doing_cron' );
        if ( $doing_cron_transient && ( floatval( $doing_cron_transient ) + WP_CRON_LOCK_TIMEOUT ) > microtime( true ) ) {
            return; // Cron kører allerede — skip
        }

        // Fire-and-forget ping til wp-cron.php (timeout=0.01s = non-blocking).
        // Virker på alle hosting-platforme med loopback; fejler lydløst hvis blokeret.
        // Den tidligere loopback-test (/?rzpa_cron_test=1) returnerede altid HTTP 200
        // fra WordPress og var derfor ubrugelig — fjernet.
        @wp_remote_post( site_url( '/wp-cron.php?doing_wp_cron' ), [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
            'cookies'   => [],
        ] );
    }

    // ── Build article prompt ──────────────────────────────────────────────────

    private static function build_article_prompt( object $topic ): string {
        $opts       = get_option( 'rzpa_settings', [] );
        $brand      = trim( $opts['blog_gen_brand_voice'] ?? '' ) ?: self::DEFAULT_BRAND_VOICE;
        $type_label = RZPA_Blog_Gen_DB::ARTICLE_TYPES[ $topic->article_type ] ?? 'Forklaringsartikel';
        $target_lbl = RZPA_Blog_Gen_DB::TARGETS[ $topic->target ] ?? 'Unge jobsøgende';
        $words      = (int) $topic->word_count;

        // Opbyg feature-noter
        $features = [];
        if ( ! empty( $topic->include_tldr ) )
            $features[] = 'Start med en TL;DR-boks: <div class="rzpa-tldr"><strong>TL;DR:</strong> [2-3 linjers resume]</div>';
        if ( ! empty( $topic->include_toc ) )
            $features[] = 'Tilføj en indholdsfortegnelse <nav class="rzpa-toc"><h2>Indhold</h2><ul><li><a href="#...">...</a></li></ul></nav> efter intro-afsnittet';
        if ( ! empty( $topic->include_faq ) )
            $features[] = 'Afslut med FAQ-sektion + FAQ JSON-LD schema (type FAQPage)';
        if ( ! empty( $topic->include_internal_links ) )
            $features[] = 'Indsæt 2-4 interne links til relevante Rezponz-sider fra listen i brand voice (ankerteksten skal flyde naturligt)';

        $feature_str = $features ? "\nEKSTRA FUNKTIONER:\n- " . implode( "\n- ", $features ) : '';

        return <<<PROMPT
{$brand}

━━━ OPGAVE ━━━
Artikeltype: {$type_label}
Titel: "{$topic->title}"
Målgruppe: {$target_lbl}
Fokussøgeord: {$topic->keywords}
Ønsket længde: præcis ca. {$words} ord (tæl nøje — hverken meget kortere eller meget længere){$feature_str}

VIGTIGT:
- Placer fokussøgeordet "{$topic->keywords}" i intro (første 100 ord) og i mindst 2 H2'er
- Skriv mindst 2 citerbare ekspert-sætninger der kan bruges som direkte citat af AI-assistenter
- Lyd som et ægte Rezponz-teammedlem der taler til en kollega eller potentiel medarbejder
- Start output med JSON-meta-linjen som beskrevet i OUTPUT-FORMAT ovenfor
PROMPT;
    }

    // ── Extract & validate FAQ schema ─────────────────────────────────────────

    private static function extract_faq_schema( string $html ): string {
        if ( ! preg_match( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m ) )
            return '';

        $decoded = json_decode( trim( $m[1] ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) return '';
        if ( ( $decoded['@type'] ?? '' ) !== 'FAQPage' ) return '';

        // Valider at mainEntity eksisterer og ikke er tom
        if ( empty( $decoded['mainEntity'] ) || ! is_array( $decoded['mainEntity'] ) ) return '';
        if ( count( $decoded['mainEntity'] ) < 1 ) return '';

        // Sanitér streng-værdier i spørgsmål/svar for at undgå injiceret indhold
        $clean_entities = [];
        foreach ( $decoded['mainEntity'] as $item ) {
            if ( empty( $item['@type'] ) || $item['@type'] !== 'Question' ) continue;
            $clean_entities[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags( $item['name'] ?? '' ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $item['acceptedAnswer']['text'] ?? '' ),
                ],
            ];
        }
        if ( empty( $clean_entities ) ) return '';

        $safe = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $clean_entities,
        ];

        return '<script type="application/ld+json">' . wp_json_encode( $safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
    }

    // ── Build Article JSON-LD ─────────────────────────────────────────────────

    private static function build_article_schema( int $post_id, string $title, string $keyword, string $description = '', int $word_count = 0 ): string {
        $post = get_post( $post_id );

        // Navngiven Person-forfatter (E-E-A-T) — hent fra WP-bruger
        $author_id   = $post ? (int) $post->post_author : 1;
        $author_name = get_the_author_meta( 'display_name', $author_id ) ?: 'Rezponz';
        $author_url  = get_author_posts_url( $author_id );

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BlogPosting',
            'headline'        => $title,
            'description'     => $description ?: $title,
            'keywords'        => $keyword,
            'url'             => get_permalink( $post_id ),
            'datePublished'   => get_the_date( 'c', $post_id ),
            'dateModified'    => get_the_modified_date( 'c', $post_id ),
            'inLanguage'      => 'da-DK',
            'author'          => [
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => $author_url,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => 'Rezponz',
                'url'   => 'https://rezponz.dk',
                'logo'  => [ '@type' => 'ImageObject', 'url' => 'https://rezponz.dk/wp-content/uploads/rezponz-logo.png' ],
            ],
            'mainEntityOfPage' => [ '@type' => 'WebPage', '@id' => get_permalink( $post_id ) ],
        ];

        if ( $word_count > 0 ) $schema['wordCount'] = $word_count;

        $thumb = get_the_post_thumbnail_url( $post_id, 'large' );
        if ( $thumb ) $schema['image'] = [ '@type' => 'ImageObject', 'url' => $thumb ];

        return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
    }
}
