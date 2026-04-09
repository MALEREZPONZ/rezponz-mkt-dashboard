<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Quality Checker.
 *
 * Scores generated pSEO content against a set of weighted checks.
 * Returns a 0–100 score, a status label, and per-check results.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Quality {

    /**
     * List of all supported check names.
     *
     * @var string[]
     */
    const CHECKS = [
        'has_title',
        'title_length',
        'has_meta_desc',
        'meta_desc_length',
        'has_keyword_in_title',
        'has_keyword_in_content',
        'min_words',
        'min_h2',
        'has_faq',
        'has_cta',
        'has_slug',
        'slug_length',
        'no_duplicate_meta_title',
        'no_duplicate_slug',
    ];

    // ── Main check ────────────────────────────────────────────────────────────

    /**
     * Runs all quality checks on rendered content.
     *
     * @param array<string, mixed> $rendered         Output of RZPA_SEO_Template::render_template().
     * @param array<string, mixed> $template_config  Template config (for quality_rules, require_faq, etc.).
     * @param int                  $post_id          Existing post ID (used for duplicate checks; 0 = new).
     * @return array{
     *     score: int,
     *     status: string,
     *     results: array<string, array{passed: bool, message: string, weight: int}>
     * }
     */
    public static function check( array $rendered, array $template_config, int $post_id = 0 ) : array {
        $title       = $rendered['title']            ?? '';
        $meta_title  = $rendered['meta_title']       ?? '';
        $meta_desc   = $rendered['meta_description'] ?? '';
        $content     = $rendered['content_html']     ?? '';
        $slug        = $rendered['slug']             ?? '';
        $keyword     = $rendered['primary_keyword']  ?? '';

        // Fall back to title for display purposes.
        if ( ! $keyword ) {
            // Try to get keyword from the rendered intro or title.
            $keyword = $meta_title;
        }

        $quality_rules = $template_config['quality_rules'] ?? [];
        $min_words     = (int) ( $quality_rules['min_words'] ?? 300 );
        $min_h2        = (int) ( $quality_rules['min_h2']    ?? 2 );
        $require_faq   = isset( $template_config['sections'] ) && self::sections_require_faq( $template_config['sections'] );
        $require_cta   = isset( $template_config['sections'] ) && self::sections_require_cta( $template_config['sections'] );

        $results = [];

        // ── has_title (weight 10) ─────────────────────────────────────────────
        $passed            = '' !== trim( $title );
        $results['has_title'] = [
            'passed'  => $passed,
            'message' => $passed ? 'Titel er til stede.' : 'Titel mangler.',
            'weight'  => 10,
        ];

        // ── title_length (weight 5) ───────────────────────────────────────────
        $len               = mb_strlen( $title );
        $passed            = $len >= 30 && $len <= 70;
        $results['title_length'] = [
            'passed'  => $passed,
            'message' => $passed
                ? sprintf( 'Titellængde OK: %d tegn.', $len )
                : sprintf( 'Titellængde %d tegn. Anbefalet: 30–70 tegn.', $len ),
            'weight'  => 5,
        ];

        // ── has_meta_desc (weight 10) ─────────────────────────────────────────
        $passed            = '' !== trim( $meta_desc );
        $results['has_meta_desc'] = [
            'passed'  => $passed,
            'message' => $passed ? 'Meta description er til stede.' : 'Meta description mangler.',
            'weight'  => 10,
        ];

        // ── meta_desc_length (weight 5) ───────────────────────────────────────
        $meta_len          = mb_strlen( $meta_desc );
        $passed            = $meta_len >= 100 && $meta_len <= 160;
        $results['meta_desc_length'] = [
            'passed'  => $passed,
            'message' => $passed
                ? sprintf( 'Meta description-længde OK: %d tegn.', $meta_len )
                : sprintf( 'Meta description %d tegn. Anbefalet: 100–160 tegn.', $meta_len ),
            'weight'  => 5,
        ];

        // ── has_keyword_in_title (weight 10) ──────────────────────────────────
        $passed            = $keyword && false !== mb_stripos( $title, $keyword );
        $results['has_keyword_in_title'] = [
            'passed'  => $passed,
            'message' => $passed
                ? 'Primært keyword er i titlen.'
                : sprintf( 'Primært keyword "%s" ikke fundet i titel.', esc_html( $keyword ) ),
            'weight'  => 10,
        ];

        // ── has_keyword_in_content (weight 10) ───────────────────────────────
        $passed            = $keyword && false !== mb_stripos( $content, $keyword );
        $results['has_keyword_in_content'] = [
            'passed'  => $passed,
            'message' => $passed
                ? 'Primært keyword er i indholdet.'
                : sprintf( 'Primært keyword "%s" ikke fundet i indholdet.', esc_html( $keyword ) ),
            'weight'  => 10,
        ];

        // ── min_words (weight 15) ─────────────────────────────────────────────
        $word_count        = self::word_count( $content );
        $passed            = $word_count >= $min_words;
        $results['min_words'] = [
            'passed'  => $passed,
            'message' => $passed
                ? sprintf( 'Ordantal OK: %d ord.', $word_count )
                : sprintf( 'For få ord: %d. Minimum: %d.', $word_count, $min_words ),
            'weight'  => 15,
        ];

        // ── min_h2 (weight 10) ────────────────────────────────────────────────
        $h2_count          = self::count_h2( $content );
        $passed            = $h2_count >= $min_h2;
        $results['min_h2'] = [
            'passed'  => $passed,
            'message' => $passed
                ? sprintf( 'Antal H2-overskrifter OK: %d.', $h2_count )
                : sprintf( 'For få H2-overskrifter: %d. Minimum: %d.', $h2_count, $min_h2 ),
            'weight'  => 10,
        ];

        // ── has_faq (weight 10) ───────────────────────────────────────────────
        if ( $require_faq ) {
            $passed = false !== strpos( $content, 'class="rzpa-faq"' );
            $results['has_faq'] = [
                'passed'  => $passed,
                'message' => $passed ? 'FAQ-sektion til stede.' : 'FAQ-sektion mangler (kræves af template).',
                'weight'  => 10,
            ];
        } else {
            // Not required; pass by default with 0 weight.
            $results['has_faq'] = [
                'passed'  => true,
                'message' => 'FAQ ikke krævet af template.',
                'weight'  => 0,
            ];
        }

        // ── has_cta (weight 10) ───────────────────────────────────────────────
        if ( $require_cta ) {
            $passed = false !== strpos( $content, 'class="rzpa-cta"' );
            $results['has_cta'] = [
                'passed'  => $passed,
                'message' => $passed ? 'CTA-sektion til stede.' : 'CTA-sektion mangler (kræves af template).',
                'weight'  => 10,
            ];
        } else {
            $results['has_cta'] = [
                'passed'  => true,
                'message' => 'CTA ikke krævet af template.',
                'weight'  => 0,
            ];
        }

        // ── has_slug (weight 10) ──────────────────────────────────────────────
        $passed            = '' !== trim( $slug );
        $results['has_slug'] = [
            'passed'  => $passed,
            'message' => $passed ? 'Slug er til stede.' : 'Slug mangler.',
            'weight'  => 10,
        ];

        // ── slug_length (weight 5) ────────────────────────────────────────────
        $slug_len          = mb_strlen( $slug );
        $passed            = $slug_len < 75;
        $results['slug_length'] = [
            'passed'  => $passed,
            'message' => $passed
                ? sprintf( 'Slug-længde OK: %d tegn.', $slug_len )
                : sprintf( 'Slug for lang: %d tegn. Max anbefalet: 75.', $slug_len ),
            'weight'  => 5,
        ];

        // ── no_duplicate_meta_title (weight 0 – informational) ───────────────
        $dup_title = self::check_duplicate_meta_title( $meta_title, $post_id );
        $results['no_duplicate_meta_title'] = [
            'passed'  => ! $dup_title,
            'message' => $dup_title
                ? 'Duplikat meta title fundet på en anden side.'
                : 'Ingen duplikat meta title fundet.',
            'weight'  => 0,
        ];

        // ── no_duplicate_slug (weight 0 – informational) ─────────────────────
        // Slug collision is handled by build_slug; log as info only.
        $results['no_duplicate_slug'] = [
            'passed'  => true,
            'message' => 'Slug-uniqueness håndteres ved generering.',
            'weight'  => 0,
        ];

        // ── Score calculation ─────────────────────────────────────────────────
        $total_weight  = 0;
        $earned_weight = 0;
        foreach ( $results as $check_result ) {
            $w             = (int) $check_result['weight'];
            $total_weight += $w;
            if ( $check_result['passed'] ) {
                $earned_weight += $w;
            }
        }

        $score  = $total_weight > 0 ? (int) round( ( $earned_weight / $total_weight ) * 100 ) : 0;
        $status = self::score_to_status( $score );

        return [
            'score'   => $score,
            'status'  => $status,
            'results' => $results,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Counts words in an HTML string (strips tags first).
     *
     * Uses mb_str_split for Unicode-safe word segmentation.
     *
     * @param string $html
     * @return int
     */
    public static function word_count( string $html ) : int {
        $text = wp_strip_all_tags( $html );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        // str_word_count doesn't handle Unicode well; use preg_match_all.
        return (int) preg_match_all( '/\b\p{L}+\b/u', $text );
    }

    /**
     * Counts <h2 occurrences in HTML content.
     *
     * @param string $html
     * @return int
     */
    public static function count_h2( string $html ) : int {
        return (int) substr_count( strtolower( $html ), '<h2' );
    }

    /**
     * Returns actionable improvement suggestions for failing checks.
     *
     * @param array<string, array{passed: bool, message: string, weight: int}> $check_results
     * @return string[]  List of human-readable suggestions.
     */
    public static function get_improvement_suggestions( array $check_results ) : array {
        $suggestions = [];

        $messages = [
            'has_title'              => 'Tilføj en titel til siden.',
            'title_length'           => 'Justér titlen til 30–70 tegn for bedre SEO.',
            'has_meta_desc'          => 'Tilføj en meta description.',
            'meta_desc_length'       => 'Justér meta description til 100–160 tegn.',
            'has_keyword_in_title'   => 'Inkludér det primære keyword i titlen.',
            'has_keyword_in_content' => 'Sørg for at det primære keyword optræder i indholdet.',
            'min_words'              => 'Udvid indholdet – tilføj mere tekst, FAQ-svar eller detaljer.',
            'min_h2'                 => 'Tilføj mindst 2 H2-overskrifter for at strukturere indholdet.',
            'has_faq'                => 'Tilføj en FAQ-sektion for at forbedre synlighed i søgeresultaterne.',
            'has_cta'                => 'Tilføj et call-to-action for at øge konverteringer.',
            'has_slug'               => 'Angiv en slug for siden.',
            'slug_length'            => 'Afkort sluggen til under 75 tegn.',
            'no_duplicate_meta_title'=> 'Ændr meta title – en anden side bruger samme titel.',
        ];

        foreach ( $check_results as $check => $data ) {
            if ( ! $data['passed'] && $data['weight'] > 0 && isset( $messages[ $check ] ) ) {
                $suggestions[] = $messages[ $check ];
            }
        }

        return $suggestions;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Converts a numeric score to a status label.
     *
     * @param int $score  0–100
     * @return string  passed|needs_improvement|failed
     */
    private static function score_to_status( int $score ) : string {
        if ( $score >= 80 ) {
            return 'passed';
        }
        if ( $score >= 50 ) {
            return 'needs_improvement';
        }
        return 'failed';
    }

    /**
     * Checks if a sections array requires a FAQ section.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return bool
     */
    private static function sections_require_faq( array $sections ) : bool {
        foreach ( $sections as $s ) {
            if ( ( $s['type'] ?? '' ) === 'faq' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a sections array requires a CTA section.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return bool
     */
    private static function sections_require_cta( array $sections ) : bool {
        foreach ( $sections as $s ) {
            if ( ( $s['type'] ?? '' ) === 'cta' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks for duplicate meta titles across rzpa_pseo posts.
     *
     * @param string $meta_title
     * @param int    $exclude_post_id  Exclude this post from the check.
     * @return bool  True if a duplicate exists.
     */
    private static function check_duplicate_meta_title( string $meta_title, int $exclude_post_id = 0 ) : bool {
        if ( '' === trim( $meta_title ) ) {
            return false;
        }

        global $wpdb;

        $exclude_clause = $exclude_post_id > 0 ? $wpdb->prepare( ' AND post_id != %d', $exclude_post_id ) : '';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_rzpa_meta_title'
             AND pm.meta_value = %s
             AND p.post_type = 'rzpa_pseo'
             AND p.post_status != 'trash'" . $exclude_clause,
            $meta_title
        ) );

        return $count > 0;
    }
}
