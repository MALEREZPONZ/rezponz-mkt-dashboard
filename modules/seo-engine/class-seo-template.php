<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Template Engine.
 *
 * Renders {placeholder} templates, builds slugs, validates template configs,
 * and generates preview/structural content HTML.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Template {

    /** Regex pattern for {placeholder} tokens. */
    const PLACEHOLDER_PATTERN = '/\{([a-z0-9_]+)\}/i';

    /**
     * All supported placeholder names.
     *
     * @var string[]
     */
    const PLACEHOLDERS = [
        'city', 'region', 'area', 'country',
        'keyword', 'primary_keyword', 'secondary_keywords',
        'job_type', 'category', 'employment_type',
        'audience', 'search_intent',
        'intro_text', 'cta_text', 'local_proof',
        'meta_title', 'meta_description',
        'site_name', 'site_url',
        'current_year', 'current_month',
    ];

    // ── Core render ───────────────────────────────────────────────────────────

    /**
     * Renders a template string by replacing {placeholder} tokens.
     *
     * Dynamic tokens resolved before $data lookup:
     *  - {current_year}  → current 4-digit year
     *  - {current_month} → current month number (1–12)
     *  - {site_name}     → get_bloginfo('name')
     *  - {site_url}      → home_url()
     *
     * @param string               $template_str  Template with {placeholder} tokens.
     * @param array<string, mixed> $data          Primary replacement values.
     * @param array<string, mixed> $fallbacks     Fallback values if key missing in $data.
     * @return string
     */
    public static function render( string $template_str, array $data, array $fallbacks = [] ) : string {
        // Inject dynamic system values.
        $system = [
            'current_year'  => (string) gmdate( 'Y' ),
            'current_month' => (string) gmdate( 'n' ),
            'site_name'     => get_bloginfo( 'name' ),
            'site_url'      => home_url(),
        ];

        return preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static function ( array $matches ) use ( $data, $fallbacks, $system ) : string {
                $key = strtolower( $matches[1] );

                // Resolution order: system → data → fallbacks → empty string.
                if ( isset( $system[ $key ] ) ) {
                    return esc_html( $system[ $key ] );
                }
                if ( isset( $data[ $key ] ) && '' !== (string) $data[ $key ] ) {
                    return esc_html( (string) $data[ $key ] );
                }
                if ( isset( $fallbacks[ $key ] ) && '' !== (string) $fallbacks[ $key ] ) {
                    return esc_html( (string) $fallbacks[ $key ] );
                }
                return '';
            },
            $template_str
        ) ?? $template_str;
    }

    /**
     * Renders a full template for a dataset, returning all content fields.
     *
     * @param int                  $template_id
     * @param array<string, mixed> $dataset_data  Associative array of dataset fields.
     * @return array{
     *     title: string,
     *     h1: string,
     *     intro: string,
     *     content_html: string,
     *     meta_title: string,
     *     meta_description: string,
     *     slug: string,
     *     sections: array,
     *     faq_html: string,
     *     cta_html: string,
     *     schema_json: string
     * }
     */
    public static function render_template( int $template_id, array $dataset_data ) : array {
        $template = RZPA_SEO_DB::get_template( $template_id );

        $empty = [
            'title'            => '',
            'h1'               => '',
            'intro'            => '',
            'content_html'     => '',
            'meta_title'       => '',
            'meta_description' => '',
            'slug'             => '',
            'sections'         => [],
            'faq_html'         => '',
            'cta_html'         => '',
            'schema_json'      => '',
        ];

        if ( ! $template ) {
            return $empty;
        }

        $config = json_decode( $template['template_config'] ?? '{}', true );
        if ( ! is_array( $config ) ) {
            $config = [];
        }

        // Build fallback values from site globals.
        $fallbacks = [
            'site_name' => get_bloginfo( 'name' ),
            'site_url'  => home_url(),
            'country'   => 'dk',
        ];

        // Use dataset's own meta_title/meta_description as data, but also allow
        // template pattern to override via config.
        $title_pattern    = $config['title_pattern']     ?? $config['h1_pattern']       ?? '{primary_keyword} i {city}';
        $h1_pattern       = $config['h1_pattern']        ?? $title_pattern;
        $meta_title_pat   = $config['meta_title_pattern'] ?? $title_pattern;
        $meta_desc_pat    = $config['meta_desc_pattern']  ?? $config['meta_description_pattern'] ?? '';
        $slug_pattern     = $config['slug_pattern']       ?? '{primary_keyword}-{city}';

        // If dataset provides explicit meta_title, prefer it over the pattern.
        $title       = ! empty( $dataset_data['meta_title'] )
            ? sanitize_text_field( $dataset_data['meta_title'] )
            : self::render( $title_pattern, $dataset_data, $fallbacks );

        $h1          = self::render( $h1_pattern, $dataset_data, $fallbacks );
        $meta_title  = ! empty( $dataset_data['meta_title'] )
            ? sanitize_text_field( $dataset_data['meta_title'] )
            : self::render( $meta_title_pat, $dataset_data, $fallbacks );
        $meta_desc   = ! empty( $dataset_data['meta_description'] )
            ? sanitize_textarea_field( $dataset_data['meta_description'] )
            : self::render( $meta_desc_pat, $dataset_data, $fallbacks );

        // Intro – allow raw HTML from dataset intro_text, wp_kses_post sanitised.
        $intro_pattern = $config['intro_pattern'] ?? '{intro_text}';
        $intro         = ! empty( $dataset_data['intro_text'] )
            ? wp_kses_post( $dataset_data['intro_text'] )
            : wp_kses_post( self::render( $intro_pattern, $dataset_data, $fallbacks ) );

        // Build full content HTML from sections.
        $content_html = self::build_content_html( $config, $dataset_data );

        // FAQ HTML (standalone for reference).
        $faq_html = '';
        $faq_items = [];
        if ( ! empty( $dataset_data['faq_items'] ) ) {
            $decoded = is_array( $dataset_data['faq_items'] )
                ? $dataset_data['faq_items']
                : json_decode( $dataset_data['faq_items'], true );
            $faq_items = is_array( $decoded ) ? $decoded : [];
        }
        if ( $faq_items ) {
            $faq_html = self::render_faq_html( $faq_items );
        }

        // CTA HTML.
        $cta_html    = '';
        $cta_text    = ! empty( $dataset_data['cta_text'] ) ? wp_kses_post( $dataset_data['cta_text'] ) : '';
        $cta_link    = $config['cta_link'] ?? home_url();
        $cta_btn_txt = $config['cta_button_text'] ?? 'Kontakt os';
        if ( $cta_text ) {
            $cta_html = '<div class="rzpa-cta"><p>' . $cta_text . '</p><a href="' . esc_url( $cta_link ) . '">' . esc_html( $cta_btn_txt ) . '</a></div>';
        }

        // Slug.
        $slug = ! empty( $dataset_data['slug'] )
            ? sanitize_title( $dataset_data['slug'] )
            : self::build_slug( $slug_pattern, $dataset_data );

        // Schema (WebPage / JobPosting JSON-LD placeholder).
        $schema_json = self::build_schema_json( $config, $dataset_data, $title, $meta_desc );

        $sections = isset( $config['sections'] ) && is_array( $config['sections'] ) ? $config['sections'] : [];

        return [
            'title'            => $title,
            'h1'               => $h1,
            'intro'            => $intro,
            'content_html'     => $content_html,
            'meta_title'       => $meta_title,
            'meta_description' => $meta_desc,
            'slug'             => $slug,
            'sections'         => $sections,
            'faq_html'         => $faq_html,
            'cta_html'         => $cta_html,
            'schema_json'      => $schema_json,
        ];
    }

    // ── Placeholder utilities ─────────────────────────────────────────────────

    /**
     * Returns the list of placeholder names found in a template string.
     *
     * @param string $str
     * @return string[]
     */
    public static function get_placeholders_in_string( string $str ) : array {
        preg_match_all( self::PLACEHOLDER_PATTERN, $str, $matches );
        return array_unique( array_map( 'strtolower', $matches[1] ?? [] ) );
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Validates a template config array.
     *
     * @param array<string, mixed> $config
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public static function validate_template_config( array $config ) : array {
        $errors   = [];
        $warnings = [];

        $required = [ 'title_pattern', 'slug_pattern', 'meta_title_pattern', 'meta_desc_pattern' ];
        foreach ( $required as $field ) {
            if ( empty( $config[ $field ] ) ) {
                $errors[] = sprintf( 'Påkrævet felt mangler: %s', $field );
            }
        }

        // Check all placeholders used in patterns are valid.
        $all_patterns = array_filter( [
            $config['title_pattern']      ?? '',
            $config['slug_pattern']       ?? '',
            $config['meta_title_pattern'] ?? '',
            $config['meta_desc_pattern']  ?? '',
            $config['h1_pattern']         ?? '',
            $config['intro_pattern']      ?? '',
        ] );

        foreach ( $all_patterns as $pattern ) {
            $used = self::get_placeholders_in_string( $pattern );
            foreach ( $used as $ph ) {
                if ( ! in_array( $ph, self::PLACEHOLDERS, true ) ) {
                    $errors[] = sprintf( 'Ukendt placeholder {%s} i mønster: "%s"', $ph, $pattern );
                }
            }
        }

        // Warnings for missing optional but recommended elements.
        $has_faq = false;
        $has_cta = false;
        if ( isset( $config['sections'] ) && is_array( $config['sections'] ) ) {
            foreach ( $config['sections'] as $section ) {
                if ( ( $section['type'] ?? '' ) === 'faq' ) {
                    $has_faq = true;
                }
                if ( ( $section['type'] ?? '' ) === 'cta' ) {
                    $has_cta = true;
                }
            }
        }
        if ( ! $has_faq ) {
            $warnings[] = 'Ingen FAQ-sektion konfigureret. Anbefales til SEO.';
        }
        if ( ! $has_cta ) {
            $warnings[] = 'Ingen CTA-sektion konfigureret.';
        }

        return [
            'valid'    => empty( $errors ),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    /**
     * Renders a template with sample data for preview purposes.
     *
     * @param int                  $template_id
     * @param array<string, mixed> $sample_data  Override sample values.
     * @return array  Same shape as render_template().
     */
    public static function preview( int $template_id, array $sample_data = [] ) : array {
        $defaults = self::get_sample_data();
        $merged   = array_merge( $defaults, $sample_data );
        return self::render_template( $template_id, $merged );
    }

    // ── Slug builder ─────────────────────────────────────────────────────────

    /**
     * Builds a URL slug from a pattern and dataset data.
     *
     * Handles collision detection: appends -2, -3, etc. as needed.
     *
     * @param string               $pattern
     * @param array<string, mixed> $data
     * @param int                  $post_id  Exclude this post from collision check (for updates).
     * @return string
     */
    public static function build_slug( string $pattern, array $data, int $post_id = 0 ) : string {
        $rendered = self::render( $pattern, $data );

        // Normalise to lowercase ASCII slug.
        $slug = strtolower( $rendered );
        $slug = html_entity_decode( $slug, ENT_QUOTES, 'UTF-8' );
        // Replace Danish chars.
        $slug = str_replace( [ 'æ', 'ø', 'å', 'ü', 'ö', 'ä' ], [ 'ae', 'o', 'aa', 'u', 'o', 'a' ], $slug );
        $slug = preg_replace( '/[^a-z0-9\-]/', '-', $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        $slug = trim( $slug, '-' );

        if ( '' === $slug ) {
            $slug = 'pseo-' . time();
        }

        // Collision detection.
        $slug = self::unique_slug( $slug, $post_id );

        return $slug;
    }

    /**
     * Ensures slug uniqueness among wp_posts with post_type='rzpa_pseo'.
     *
     * @param string $slug
     * @param int    $exclude_post_id  Post to exclude from collision check.
     * @return string
     */
    private static function unique_slug( string $slug, int $exclude_post_id = 0 ) : string {
        global $wpdb;

        $base    = $slug;
        $suffix  = 1;
        $current = $slug;

        while ( true ) {
            $query  = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'rzpa_pseo' AND post_status != 'trash'",
                $current
            );
            $found = (int) $wpdb->get_var( $query );

            if ( ! $found || ( $exclude_post_id > 0 && $found === $exclude_post_id ) ) {
                break;
            }

            $suffix++;
            $current = $base . '-' . $suffix;
        }

        return $current;
    }

    // ── Content HTML builder ──────────────────────────────────────────────────

    /**
     * Builds the full post_content HTML from a template config's sections array.
     *
     * Section types supported: content, faq, cta, link_block, custom.
     *
     * @param array<string, mixed> $template_config
     * @param array<string, mixed> $dataset_data
     * @return string  Sanitized HTML.
     */
    public static function build_content_html( array $template_config, array $dataset_data ) : string {
        $html     = '';
        $sections = $template_config['sections'] ?? [];

        if ( ! is_array( $sections ) || empty( $sections ) ) {
            // Minimal fallback: intro + FAQ + CTA if data available.
            $sections = self::build_default_sections( $template_config, $dataset_data );
        }

        foreach ( $sections as $section ) {
            $type = $section['type'] ?? 'content';

            switch ( $type ) {

                case 'content':
                    $content_tpl = $section['content'] ?? $section['template'] ?? '';
                    if ( $content_tpl ) {
                        $rendered = self::render( $content_tpl, $dataset_data );
                        $html    .= '<div class="rzpa-section rzpa-content">' . wp_kses_post( $rendered ) . '</div>' . "\n";
                    } elseif ( ! empty( $dataset_data['intro_text'] ) ) {
                        $html .= '<div class="rzpa-section rzpa-intro"><p>' . wp_kses_post( $dataset_data['intro_text'] ) . '</p></div>' . "\n";
                    }
                    break;

                case 'faq':
                    $faq_items = [];
                    if ( ! empty( $dataset_data['faq_items'] ) ) {
                        $decoded = is_array( $dataset_data['faq_items'] )
                            ? $dataset_data['faq_items']
                            : json_decode( $dataset_data['faq_items'], true );
                        $faq_items = is_array( $decoded ) ? $decoded : [];
                    }
                    if ( $faq_items ) {
                        $html .= self::render_faq_html( $faq_items ) . "\n";
                    }
                    break;

                case 'cta':
                    $cta_text = ! empty( $dataset_data['cta_text'] )
                        ? $dataset_data['cta_text']
                        : ( $section['default_text'] ?? '' );
                    $cta_link = $section['link'] ?? home_url();
                    $btn_text = $section['button_text'] ?? 'Kontakt os';
                    if ( $cta_text ) {
                        $html .= '<div class="rzpa-cta">'
                            . '<p>' . wp_kses_post( self::render( $cta_text, $dataset_data ) ) . '</p>'
                            . '<a href="' . esc_url( self::render( $cta_link, $dataset_data ) ) . '">'
                            . esc_html( $btn_text )
                            . '</a></div>' . "\n";
                    }
                    break;

                case 'link_block':
                    $heading = esc_html( self::render( $section['heading'] ?? 'Relaterede stillinger', $dataset_data ) );
                    $links   = [];
                    if ( ! empty( $dataset_data['related_links'] ) ) {
                        $decoded = is_array( $dataset_data['related_links'] )
                            ? $dataset_data['related_links']
                            : json_decode( $dataset_data['related_links'], true );
                        $links = is_array( $decoded ) ? $decoded : [];
                    }
                    if ( $links ) {
                        $items = '';
                        foreach ( $links as $link ) {
                            $url   = esc_url( $link['url']   ?? '' );
                            $label = esc_html( $link['label'] ?? $link['text'] ?? $url );
                            if ( $url ) {
                                $items .= "<li><a href=\"{$url}\">{$label}</a></li>";
                            }
                        }
                        if ( $items ) {
                            $html .= '<div class="rzpa-link-block"><h3>' . $heading . '</h3><ul>' . $items . '</ul></div>' . "\n";
                        }
                    }
                    break;

                case 'custom':
                    $custom_tpl = $section['content'] ?? $section['html'] ?? '';
                    if ( $custom_tpl ) {
                        $rendered = self::render( $custom_tpl, $dataset_data );
                        $html    .= '<div class="rzpa-section rzpa-custom">' . wp_kses_post( $rendered ) . '</div>' . "\n";
                    }
                    break;
            }
        }

        return $html;
    }

    /**
     * Renders FAQ items as HTML.
     *
     * @param array<int, array{q: string, a: string}> $faq_items
     * @return string
     */
    private static function render_faq_html( array $faq_items ) : string {
        $items_html = '';
        foreach ( $faq_items as $item ) {
            $q = esc_html( $item['q'] ?? $item['question'] ?? '' );
            $a = wp_kses_post( $item['a'] ?? $item['answer'] ?? '' );
            if ( $q && $a ) {
                $items_html .= '<div class="faq-item"><h3 class="faq-question">' . $q . '</h3><div class="faq-answer"><p>' . $a . '</p></div></div>';
            }
        }
        if ( ! $items_html ) {
            return '';
        }
        return '<div class="rzpa-faq"><h2>' . __( 'Ofte stillede spørgsmål', 'rezponz-analytics' ) . '</h2><div class="faq-items">' . $items_html . '</div></div>';
    }

    /**
     * Builds a minimal default sections array when template_config has none.
     *
     * @param array<string, mixed> $template_config
     * @param array<string, mixed> $dataset_data
     * @return array<int, array<string, mixed>>
     */
    private static function build_default_sections( array $template_config, array $dataset_data ) : array {
        $sections = [];

        if ( ! empty( $dataset_data['intro_text'] ) ) {
            $sections[] = [ 'type' => 'content', 'content' => '{intro_text}' ];
        }

        $uv = is_array( $dataset_data['unique_value_points'] ?? null )
            ? $dataset_data['unique_value_points']
            : json_decode( $dataset_data['unique_value_points'] ?? '[]', true );
        if ( ! empty( $uv ) && is_array( $uv ) ) {
            $li  = implode( '', array_map( fn( $v ) => '<li>' . esc_html( $v ) . '</li>', $uv ) );
            $sections[] = [ 'type' => 'content', 'content' => '<h2>Hvorfor vælge os?</h2><ul>' . $li . '</ul>' ];
        }

        if ( ! empty( $dataset_data['faq_items'] ) ) {
            $sections[] = [ 'type' => 'faq' ];
        }

        if ( ! empty( $dataset_data['cta_text'] ) ) {
            $sections[] = [ 'type' => 'cta' ];
        }

        return $sections;
    }

    /**
     * Builds a JSON-LD schema block (WebPage or JobPosting).
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $data
     * @param string               $title
     * @param string               $description
     * @return string  JSON string or empty.
     */
    private static function build_schema_json( array $config, array $data, string $title, string $description ) : string {
        $schema_type = $config['schema_type'] ?? 'WebPage';

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => $schema_type,
            'name'        => $title,
            'description' => $description,
            'url'         => home_url( '/' . ( $data['slug'] ?? '' ) ),
        ];

        if ( 'JobPosting' === $schema_type ) {
            $schema['title']              = $title;
            $schema['datePosted']         = gmdate( 'Y-m-d' );
            $schema['employmentType']     = strtoupper( $data['employment_type'] ?? 'FULL_TIME' );
            $schema['jobLocation']        = [
                '@type'   => 'Place',
                'address' => [
                    '@type'           => 'PostalAddress',
                    'addressLocality' => $data['city']    ?? '',
                    'addressRegion'   => $data['region']  ?? '',
                    'addressCountry'  => strtoupper( $data['country'] ?? 'DK' ),
                ],
            ];
            $schema['hiringOrganization'] = [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'sameAs'=> home_url(),
            ];
        }

        $encoded = wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return $encoded ?: '';
    }

    // ── Sample / default data ─────────────────────────────────────────────────

    /**
     * Returns sample placeholder values for template preview and testing.
     *
     * @return array<string, string>
     */
    public static function get_sample_data() : array {
        return [
            'city'               => 'København',
            'region'             => 'Hovedstaden',
            'area'               => 'Indre By',
            'country'            => 'dk',
            'keyword'            => 'salgsrådgiver job',
            'primary_keyword'    => 'salgsrådgiver job København',
            'secondary_keywords' => 'salgsjob, kundeservice job, commercial job',
            'job_type'           => 'Salgsrådgiver',
            'category'           => 'Salg',
            'employment_type'    => 'fuldtid',
            'audience'           => 'Erfarne sælgere',
            'search_intent'      => 'transactional',
            'intro_text'         => '<p>Er du på udkig efter et spændende salgsrådgiver job i København? Rezponz hjælper dig med at finde de bedste muligheder inden for salg og kundeservice i Hovedstaden.</p>',
            'cta_text'           => 'Send din ansøgning i dag og bliv kontaktet af vores rekrutteringseksperter.',
            'local_proof'        => 'Vi har hjulpet over 500 kandidater i København-området.',
            'meta_title'         => 'Salgsrådgiver Job i København | Rezponz',
            'meta_description'   => 'Find de bedste salgsrådgiver jobs i København. Rezponz matcher dig med topvirksomheder. Se ledige stillinger og søg i dag.',
            'faq_items'          => json_encode( [
                [ 'q' => 'Hvad laver en salgsrådgiver?', 'a' => 'En salgsrådgiver hjælper kunder med at finde det rigtige produkt eller den rigtige service og sikrer en god kundeoplevelse.' ],
                [ 'q' => 'Hvad tjener en salgsrådgiver i København?', 'a' => 'Lønnen varierer, men ligger typisk mellem 25.000 og 40.000 kr. om måneden afhængigt af erfaring og branche.' ],
            ] ),
        ];
    }

    /**
     * Returns a sensible default template_config for new templates.
     *
     * @param string $type  'pseo' or 'blog'.
     * @return array<string, mixed>
     */
    public static function get_default_template_config( string $type = 'pseo' ) : array {
        if ( 'blog' === $type ) {
            return [
                'title_pattern'       => '{primary_keyword} – Guide {current_year} | {site_name}',
                'slug_pattern'        => '{primary_keyword}',
                'meta_title_pattern'  => '{primary_keyword} – Guide {current_year} | {site_name}',
                'meta_desc_pattern'   => 'Læs vores komplette guide om {primary_keyword}. Få de bedste tips og tricks fra eksperterne hos {site_name}.',
                'schema_type'         => 'Article',
                'quality_rules'       => [
                    'min_words' => 800,
                    'min_h2'    => 3,
                ],
                'sections'            => [
                    [ 'type' => 'content', 'content' => '{intro_text}' ],
                    [ 'type' => 'faq' ],
                    [ 'type' => 'cta', 'button_text' => 'Kontakt os' ],
                ],
            ];
        }

        // Default pseo config.
        return [
            'title_pattern'       => '{primary_keyword} i {city} | {site_name}',
            'h1_pattern'          => '{primary_keyword} i {city}',
            'slug_pattern'        => '{job_type}-job-{city}',
            'meta_title_pattern'  => '{primary_keyword} i {city} {current_year} | {site_name}',
            'meta_desc_pattern'   => 'Find {primary_keyword} i {city}. Se ledige stillinger og søg i dag via {site_name}. {local_proof}',
            'intro_pattern'       => '{intro_text}',
            'schema_type'         => 'JobPosting',
            'cta_link'            => '{site_url}/kontakt/',
            'cta_button_text'     => 'Søg stillingen',
            'quality_rules'       => [
                'min_words' => 300,
                'min_h2'    => 2,
            ],
            'sections'            => [
                [ 'type' => 'content', 'content' => '{intro_text}' ],
                [ 'type' => 'faq' ],
                [ 'type' => 'cta', 'button_text' => 'Søg stillingen' ],
                [ 'type' => 'link_block', 'heading' => 'Relaterede {job_type} jobs' ],
            ],
        ];
    }
}
