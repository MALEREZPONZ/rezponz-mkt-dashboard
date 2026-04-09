<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Blog Content Module.
 *
 * Generates WordPress blog posts from brief records, either via structural
 * HTML outlines or optionally via the AI layer (RZPA_SEO_AI).
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Blog {

    // ── Post generation ───────────────────────────────────────────────────────

    /**
     * Generates a WordPress blog post from a brief record.
     *
     * @param int  $brief_id
     * @param bool $use_ai  If true, attempts AI generation via RZPA_SEO_AI.
     * @return array{success: bool, post_id: int|null, errors: string[], warnings: string[]}
     */
    public static function generate_blog_post( int $brief_id, bool $use_ai = false ) : array {
        $result = [
            'success'  => false,
            'post_id'  => null,
            'errors'   => [],
            'warnings' => [],
        ];

        // 1. Load brief.
        $brief = RZPA_SEO_DB::get_brief( $brief_id );
        if ( ! $brief ) {
            $result['errors'][] = sprintf( 'Brief #%d ikke fundet.', $brief_id );
            return $result;
        }

        // 2. Validate.
        $validation = self::validate_brief( $brief );
        if ( ! $validation['valid'] ) {
            $result['errors'] = $validation['errors'];
            return $result;
        }
        $result['warnings'] = $validation['warnings'];

        // 3. Generate content.
        $ai_content = null;
        if ( $use_ai && class_exists( 'RZPA_SEO_AI' ) && RZPA_SEO_AI::is_configured() ) {
            $ai_content = RZPA_SEO_AI::generate_blog_content( $brief );
        }

        // Build content fields.
        $keyword      = $brief['primary_keyword'];
        $article_type = $brief['article_type'] ?? 'how-to';
        $site_name    = get_bloginfo( 'name' );

        if ( $ai_content && ! empty( $ai_content['title'] ) ) {
            $title        = sanitize_text_field( $ai_content['title'] );
            $intro        = isset( $ai_content['intro'] ) ? wp_kses_post( $ai_content['intro'] ) : '';
            $content_html = self::assemble_ai_content( $ai_content, $brief );
            $meta_title   = ! empty( $ai_content['meta_title'] )       ? sanitize_text_field( $ai_content['meta_title'] ) : $title;
            $meta_desc    = ! empty( $ai_content['meta_description'] )  ? sanitize_textarea_field( $ai_content['meta_description'] ) : '';
        } else {
            // Structural fallback.
            $type_label    = self::article_type_label( $article_type );
            $title         = sprintf( '%s – %s | %s', $keyword, $type_label, $site_name );
            $content_html  = self::build_structural_content( $brief );
            $intro         = '';
            $meta_title    = ! empty( $brief['meta_title'] ) ? sanitize_text_field( $brief['meta_title'] ) : $title;
            $meta_desc     = ! empty( $brief['meta_description'] ) ? sanitize_textarea_field( $brief['meta_description'] ) : '';
        }

        // 4. Build slug.
        $slug = ! empty( $brief['slug'] )
            ? sanitize_title( $brief['slug'] )
            : sanitize_title( $keyword );

        $slug = self::unique_blog_slug( $slug, (int) ( $brief['linked_post_id'] ?? 0 ) );

        // 5. Excerpt.
        $excerpt = ! empty( $brief['excerpt'] )
            ? sanitize_textarea_field( $brief['excerpt'] )
            : wp_trim_words( wp_strip_all_tags( $intro ?: $content_html ), 40, '' );

        // 6. Insert post.
        $existing_post_id = (int) ( $brief['linked_post_id'] ?? 0 );

        $post_data = [
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content_html,
            'post_excerpt' => $excerpt,
            'post_name'    => $slug,
        ];

        if ( $existing_post_id > 0 && get_post( $existing_post_id ) ) {
            $post_data['ID'] = $existing_post_id;
            $post_id         = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            $result['errors'][] = $post_id->get_error_message();
            RZPA_SEO_DB::log( 'blog', $brief_id, 'generate', $post_id->get_error_message(), 'error' );
            return $result;
        }

        $post_id = (int) $post_id;

        // 7. Save post meta.
        update_post_meta( $post_id, '_rzpa_brief_id',          absint( $brief_id ) );
        update_post_meta( $post_id, '_rzpa_meta_title',        $meta_title );
        update_post_meta( $post_id, '_rzpa_meta_description',  $meta_desc );

        // Yoast meta integration.
        if ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_title',   $meta_title );
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
        }

        // RankMath integration.
        if ( class_exists( 'RankMath' ) ) {
            update_post_meta( $post_id, 'rank_math_title',            $meta_title );
            update_post_meta( $post_id, 'rank_math_description',      $meta_desc );
            update_post_meta( $post_id, 'rank_math_focus_keyword',    $keyword );
        }

        // 8. Update brief status.
        RZPA_SEO_DB::update_brief( $brief_id, [
            'linked_post_id' => $post_id,
            'status'         => 'generated',
        ] );

        // 9. Log.
        RZPA_SEO_DB::log( 'blog', $brief_id, 'generate', sprintf(
            'Blog post #%d %s fra brief #%d (AI: %s).',
            $post_id,
            $existing_post_id > 0 ? 'opdateret' : 'oprettet',
            $brief_id,
            $use_ai && $ai_content ? 'ja' : 'nej'
        ), 'success' );

        $result['success'] = true;
        $result['post_id'] = $post_id;
        return $result;
    }

    // ── Structural content builder ────────────────────────────────────────────

    /**
     * Builds an HTML blog post without AI, based purely on brief fields.
     *
     * @param array<string, mixed> $brief
     * @return string  HTML content.
     */
    public static function build_structural_content( array $brief ) : string {
        $keyword    = sanitize_text_field( $brief['primary_keyword'] );
        $intent     = $brief['intent']        ?? 'informational';
        $audience   = $brief['audience']      ?? '';
        $tone       = $brief['tone_of_voice'] ?? 'professional';
        $type       = $brief['article_type']  ?? 'how-to';
        $target_len = (int) ( $brief['target_length'] ?? 1500 );
        $faq_req    = (bool) ( $brief['faq_required'] ?? false );
        $cta_type   = $brief['cta_type']      ?? '';
        $depth      = max( 1, min( 6, (int) ( $brief['heading_depth'] ?? 3 ) ) );

        // Parse secondary keywords.
        $secondary_raw = $brief['secondary_keywords'] ?? '';
        $secondary     = array_filter( array_map( 'trim', explode( ',', $secondary_raw ) ) );

        $html  = '';

        // Intro paragraph.
        $html .= '<p>';
        $html .= sprintf(
            'Er du på udkig efter information om <strong>%s</strong>? %s',
            esc_html( $keyword ),
            $audience ? sprintf( 'Denne guide er skrevet til %s.', esc_html( $audience ) ) : 'I denne guide gennemgår vi alt, hvad du behøver at vide.'
        );
        $html .= '</p>' . "\n\n";

        // H2 sections based on secondary keywords and intent.
        $sections_built = 0;
        if ( ! empty( $secondary ) ) {
            foreach ( $secondary as $sec_kw ) {
                if ( $sections_built >= $depth ) {
                    break;
                }
                $heading = sprintf( 'Hvad er %s?', esc_html( $sec_kw ) );
                $html   .= '<h2>' . $heading . '</h2>' . "\n";
                $html   .= '<p>' . sprintf(
                    '%s er et vigtigt emne inden for %s. %s',
                    esc_html( ucfirst( $sec_kw ) ),
                    esc_html( $keyword ),
                    'Her gennemgår vi de vigtigste aspekter og giver dig konkrete råd.'
                ) . '</p>' . "\n\n";
                $sections_built++;
            }
        }

        // Supplementary H2s if not enough secondary keywords.
        $default_headings = self::get_default_headings( $keyword, $type, $intent );
        foreach ( $default_headings as $h2 ) {
            if ( $sections_built >= max( $depth, 2 ) ) {
                break;
            }
            $html .= '<h2>' . esc_html( $h2 ) . '</h2>' . "\n";
            $html .= '<p>' . sprintf(
                'Dette afsnit handler om %s i relation til %s. Udfyld med relevant indhold.',
                esc_html( strtolower( $h2 ) ),
                esc_html( $keyword )
            ) . '</p>' . "\n\n";
            $sections_built++;
        }

        // Internal link placeholders.
        $link_targets_raw = $brief['internal_link_targets'] ?? '[]';
        $link_targets     = is_array( $link_targets_raw )
            ? $link_targets_raw
            : json_decode( $link_targets_raw, true );
        if ( ! empty( $link_targets ) && is_array( $link_targets ) ) {
            foreach ( $link_targets as $target ) {
                $html .= '<!-- INTERNAL_LINK: ' . esc_html( (string) $target ) . ' -->' . "\n";
            }
        }

        // FAQ section.
        if ( $faq_req ) {
            $html .= self::build_generic_faq( $keyword );
        }

        // CTA section.
        if ( $cta_type ) {
            $html .= self::build_cta_block( $keyword, $cta_type );
        }

        return $html;
    }

    /**
     * Assembles AI-generated content into full HTML.
     *
     * @param array<string, mixed> $ai_content
     * @param array<string, mixed> $brief
     * @return string
     */
    private static function assemble_ai_content( array $ai_content, array $brief ) : string {
        $html = '';

        if ( ! empty( $ai_content['intro'] ) ) {
            $html .= '<p>' . wp_kses_post( $ai_content['intro'] ) . '</p>' . "\n\n";
        }

        if ( ! empty( $ai_content['sections'] ) && is_array( $ai_content['sections'] ) ) {
            foreach ( $ai_content['sections'] as $section ) {
                $heading = $section['heading'] ?? $section['h2'] ?? '';
                $body    = $section['content'] ?? $section['body'] ?? '';
                if ( $heading ) {
                    $html .= '<h2>' . esc_html( $heading ) . '</h2>' . "\n";
                }
                if ( $body ) {
                    $html .= '<p>' . wp_kses_post( $body ) . '</p>' . "\n\n";
                }
            }
        }

        // FAQ.
        if ( ! empty( $ai_content['faq'] ) && is_array( $ai_content['faq'] ) ) {
            $faq_items_html = '';
            foreach ( $ai_content['faq'] as $item ) {
                $q = esc_html( $item['q'] ?? $item['question'] ?? '' );
                $a = wp_kses_post( $item['a'] ?? $item['answer'] ?? '' );
                if ( $q && $a ) {
                    $faq_items_html .= '<div class="faq-item"><h3 class="faq-question">' . $q . '</h3><div class="faq-answer"><p>' . $a . '</p></div></div>';
                }
            }
            if ( $faq_items_html ) {
                $html .= '<div class="rzpa-faq"><h2>' . __( 'Ofte stillede spørgsmål', 'rezponz-analytics' ) . '</h2><div class="faq-items">' . $faq_items_html . '</div></div>' . "\n\n";
            }
        }

        // CTA.
        if ( ! empty( $ai_content['cta'] ) ) {
            $cta_type = $brief['cta_type'] ?? '';
            $html    .= self::build_cta_block( $brief['primary_keyword'] ?? '', $cta_type, wp_kses_post( $ai_content['cta'] ) );
        }

        return $html;
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Validates a brief array.
     *
     * @param array<string, mixed> $brief
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public static function validate_brief( array $brief ) : array {
        $errors   = [];
        $warnings = [];

        if ( empty( $brief['primary_keyword'] ) ) {
            $errors[] = 'Primært keyword er påkrævet.';
        }

        if ( ! empty( $brief['meta_title'] ) && mb_strlen( $brief['meta_title'] ) > 70 ) {
            $warnings[] = sprintf( 'Meta title er %d tegn. Anbefalet max: 70.', mb_strlen( $brief['meta_title'] ) );
        }

        if ( ! empty( $brief['meta_description'] ) && mb_strlen( $brief['meta_description'] ) > 170 ) {
            $warnings[] = sprintf( 'Meta description er %d tegn. Anbefalet max: 170.', mb_strlen( $brief['meta_description'] ) );
        }

        $valid_types = [ 'how-to', 'listicle', 'guide', 'comparison', 'news', 'opinion', 'review' ];
        if ( ! empty( $brief['article_type'] ) && ! in_array( $brief['article_type'], $valid_types, true ) ) {
            $warnings[] = sprintf( 'Ukendt article_type: %s.', $brief['article_type'] );
        }

        return [
            'valid'    => empty( $errors ),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    // ── Blog templates / outline ──────────────────────────────────────────────

    /**
     * Returns all templates where type = 'blog'.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_blog_templates() : array {
        return RZPA_SEO_DB::get_templates( 'blog', 'active' );
    }

    /**
     * Builds a structured outline array for a brief (for preview purposes).
     *
     * @param int $brief_id
     * @return array{
     *     title: string,
     *     intro: string,
     *     sections: array<int, array{heading: string, notes: string}>,
     *     faq: bool,
     *     cta: string,
     *     meta_title: string,
     *     meta_description: string
     * }
     */
    public static function build_blog_outline( int $brief_id ) : array {
        $brief = RZPA_SEO_DB::get_brief( $brief_id );

        if ( ! $brief ) {
            return [
                'title'            => '',
                'intro'            => '',
                'sections'         => [],
                'faq'              => false,
                'cta'              => '',
                'meta_title'       => '',
                'meta_description' => '',
            ];
        }

        $keyword      = $brief['primary_keyword'];
        $type         = $brief['article_type'] ?? 'how-to';
        $site_name    = get_bloginfo( 'name' );
        $type_label   = self::article_type_label( $type );
        $title        = sprintf( '%s – %s | %s', $keyword, $type_label, $site_name );

        $secondary     = array_filter( array_map( 'trim', explode( ',', $brief['secondary_keywords'] ?? '' ) ) );
        $default_heads = self::get_default_headings( $keyword, $type, $brief['intent'] ?? 'informational' );
        $headings      = array_merge(
            array_map( fn( $k ) => sprintf( 'Hvad er %s?', $k ), array_slice( $secondary, 0, 3 ) ),
            $default_heads
        );

        $depth    = (int) ( $brief['heading_depth'] ?? 3 );
        $sections = [];
        foreach ( array_slice( $headings, 0, max( $depth, 2 ) ) as $h ) {
            $sections[] = [
                'heading' => $h,
                'notes'   => sprintf( 'Skriv 150–300 ord om %s.', $h ),
            ];
        }

        return [
            'title'            => $title,
            'intro'            => sprintf( 'Introduktion om %s for %s.', $keyword, $brief['audience'] ?? 'læseren' ),
            'sections'         => $sections,
            'faq'              => (bool) ( $brief['faq_required'] ?? false ),
            'cta'              => $brief['cta_type'] ?? '',
            'meta_title'       => ! empty( $brief['meta_title'] ) ? $brief['meta_title'] : $title,
            'meta_description' => $brief['meta_description'] ?? '',
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns default H2 headings based on article type and intent.
     *
     * @param string $keyword
     * @param string $type
     * @param string $intent
     * @return string[]
     */
    private static function get_default_headings( string $keyword, string $type, string $intent ) : array {
        switch ( $type ) {
            case 'how-to':
                return [
                    sprintf( 'Hvad er %s?', $keyword ),
                    sprintf( 'Fordele ved %s', $keyword ),
                    sprintf( 'Sådan kommer du i gang med %s', $keyword ),
                    sprintf( 'Tips og tricks til %s', $keyword ),
                ];
            case 'listicle':
                return [
                    sprintf( 'De bedste %s', $keyword ),
                    sprintf( 'Hvad bør du kigge efter i %s?', $keyword ),
                    sprintf( 'Sammenligning af %s', $keyword ),
                    'Konklusion',
                ];
            case 'guide':
                return [
                    sprintf( 'Introduktion til %s', $keyword ),
                    sprintf( 'Grundlæggende om %s', $keyword ),
                    sprintf( 'Avancerede teknikker til %s', $keyword ),
                    'Næste skridt',
                ];
            case 'comparison':
                return [
                    sprintf( 'Hvad sammenlignes i %s?', $keyword ),
                    'Fordele og ulemper',
                    'Vores anbefaling',
                    'Konklusion',
                ];
            default:
                return [
                    sprintf( 'Om %s', $keyword ),
                    sprintf( 'Fordele og egenskaber ved %s', $keyword ),
                    'Konklusion og anbefalinger',
                ];
        }
    }

    /**
     * Returns a human-readable label for an article type.
     *
     * @param string $type
     * @return string
     */
    private static function article_type_label( string $type ) : string {
        $labels = [
            'how-to'     => 'trin-for-trin guide',
            'listicle'   => 'komplet liste',
            'guide'      => 'komplet guide',
            'comparison' => 'sammenligning',
            'news'       => 'nyhed',
            'opinion'    => 'analyse',
            'review'     => 'anmeldelse',
        ];
        return $labels[ $type ] ?? 'guide';
    }

    /**
     * Builds a generic FAQ block for a given keyword.
     *
     * @param string $keyword
     * @return string  HTML.
     */
    private static function build_generic_faq( string $keyword ) : string {
        $kw    = esc_html( $keyword );
        $items = [
            [ 'q' => sprintf( 'Hvad er %s?', $kw ),      'a' => sprintf( 'Her kan du tilføje en kort forklaring af %s.', $kw ) ],
            [ 'q' => sprintf( 'Hvem kan bruge %s?', $kw ), 'a' => 'Tilføj information om målgruppen her.' ],
            [ 'q' => sprintf( 'Hvordan fungerer %s?', $kw ), 'a' => sprintf( 'Beskriv hvordan %s virker i praksis.', $kw ) ],
            [ 'q' => sprintf( 'Hvad koster %s?', $kw ),   'a' => 'Tilføj prisoplysninger her.' ],
            [ 'q' => sprintf( 'Er %s det rigtige for mig?', $kw ), 'a' => 'Hjælp læseren med at beslutte sig.' ],
        ];

        $inner = '';
        foreach ( $items as $item ) {
            $inner .= '<div class="faq-item"><h3 class="faq-question">' . $item['q'] . '</h3>'
                . '<div class="faq-answer"><p>' . $item['a'] . '</p></div></div>';
        }

        return '<div class="rzpa-faq"><h2>' . __( 'Ofte stillede spørgsmål', 'rezponz-analytics' ) . '</h2><div class="faq-items">' . $inner . '</div></div>' . "\n\n";
    }

    /**
     * Builds a CTA block.
     *
     * @param string $keyword
     * @param string $cta_type
     * @param string $custom_text  Override text (e.g. from AI).
     * @return string  HTML.
     */
    private static function build_cta_block( string $keyword, string $cta_type, string $custom_text = '' ) : string {
        if ( $custom_text ) {
            $text = $custom_text;
        } else {
            $cta_texts = [
                'contact'   => sprintf( 'Vil du vide mere om %s? Kontakt os i dag – vi hjælper dig gerne.', esc_html( $keyword ) ),
                'apply'     => sprintf( 'Søg stillingen som %s nu og hør fra os inden for 24 timer.', esc_html( $keyword ) ),
                'subscribe' => sprintf( 'Abonnér på vores nyhedsbrev og få de seneste nyheder om %s.', esc_html( $keyword ) ),
                'download'  => sprintf( 'Download vores gratis guide om %s.', esc_html( $keyword ) ),
                default     => sprintf( 'Interesseret i %s? Tag det næste skridt i dag.', esc_html( $keyword ) ),
            ];
            $text = $cta_texts[ $cta_type ] ?? $cta_texts['default'];
        }

        $link     = home_url( '/kontakt/' );
        $btn_text = 'Kontakt os';

        return '<div class="rzpa-cta"><p>' . $text . '</p><a href="' . esc_url( $link ) . '">' . esc_html( $btn_text ) . '</a></div>' . "\n\n";
    }

    /**
     * Ensures uniqueness of a blog post slug.
     *
     * @param string $slug
     * @param int    $exclude_id
     * @return string
     */
    private static function unique_blog_slug( string $slug, int $exclude_id = 0 ) : string {
        global $wpdb;

        $base    = $slug;
        $suffix  = 1;
        $current = $slug;

        while ( true ) {
            $sql   = "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status != 'trash'";
            $found = (int) $wpdb->get_var( $wpdb->prepare( $sql, $current ) );

            if ( ! $found || ( $exclude_id > 0 && $found === $exclude_id ) ) {
                break;
            }

            $suffix++;
            $current = $base . '-' . $suffix;
        }

        return $current;
    }
}
