<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Internal Linking Engine.
 *
 * Finds, scores, caches and inserts internal link suggestions
 * between rzpa_pseo pages and regular blog posts.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Linking {

    /** Meta key used to cache suggestions on a post. */
    const CACHE_META_KEY    = '_rzpa_link_suggestions';
    /** Cache TTL in seconds (7 days). */
    const CACHE_TTL         = 604800;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Finds internal link suggestions for a given post.
     *
     * @param int $post_id
     * @param int $limit   Maximum number of suggestions to return.
     * @return array<int, array{post_id: int, post_title: string, post_type: string, url: string, relevance_score: float, match_reason: string}>
     */
    public static function find_suggestions( int $post_id, int $limit = 10 ) : array {
        $source_post = get_post( $post_id );
        if ( ! $source_post ) {
            return [];
        }

        // 1. Determine source type for rule matching.
        $source_type = 'rzpa_pseo' === $source_post->post_type ? 'pseo' : 'blog';

        // 2. Load active link rules.
        $rules = RZPA_SEO_DB::get_rules( true );

        $candidates = [];

        // 3. Apply each matching rule.
        foreach ( $rules as $rule ) {
            $rule_source = $rule['source_type'];
            if ( 'any' !== $rule_source && $rule_source !== $source_type ) {
                continue;
            }

            $rule_candidates = self::apply_rule( $rule, $source_post );
            foreach ( $rule_candidates as $candidate ) {
                $pid = (int) $candidate['post_id'];
                if ( $pid === $post_id ) {
                    continue; // Skip self-links.
                }
                // Merge: keep highest score.
                if ( ! isset( $candidates[ $pid ] ) || $candidates[ $pid ]['relevance_score'] < $candidate['relevance_score'] ) {
                    $candidates[ $pid ] = $candidate;
                }
            }
        }

        // 4. Fallback: keyword-based search if no rules or no results.
        if ( empty( $candidates ) ) {
            $candidates = self::keyword_fallback_search( $source_post );
        }

        // 5. Re-score all candidates against the source post.
        foreach ( $candidates as $pid => $candidate ) {
            $target_post = get_post( $pid );
            if ( ! $target_post || 'publish' !== $target_post->post_status ) {
                unset( $candidates[ $pid ] );
                continue;
            }
            $score                                = self::score_relevance( $source_post, $target_post );
            $candidates[ $pid ]['relevance_score'] = $score;
            $candidates[ $pid ]['post_title']      = $target_post->post_title;
            $candidates[ $pid ]['post_type']       = $target_post->post_type;
            $candidates[ $pid ]['url']             = get_permalink( $target_post );
        }

        // 6. Sort by score descending.
        uasort( $candidates, fn( $a, $b ) => $b['relevance_score'] <=> $a['relevance_score'] );

        return array_values( array_slice( $candidates, 0, $limit ) );
    }

    /**
     * Applies a single link rule to find matching target posts.
     *
     * @param array<string, mixed> $rule
     * @param WP_Post              $source_post
     * @return array<int, array{post_id: int, relevance_score: float, match_reason: string}>
     */
    public static function apply_rule( array $rule, WP_Post $source_post ) : array {
        $match_logic = json_decode( $rule['match_logic'] ?? '{}', true );
        if ( ! is_array( $match_logic ) ) {
            return [];
        }

        $type       = $match_logic['type']   ?? 'keyword';
        $config     = $match_logic['config'] ?? [];
        $target_type = $rule['target_type']  ?? 'any';
        $results    = [];

        switch ( $type ) {
            case 'keyword':
                $keywords = $config['keywords'] ?? [];
                if ( empty( $keywords ) ) {
                    // Use source post's own keywords.
                    $keywords = self::extract_post_keywords( $source_post );
                }
                if ( $keywords ) {
                    $posts = self::search_posts_by_keywords( $keywords, $target_type, $source_post->ID );
                    foreach ( $posts as $p ) {
                        $results[ $p->ID ] = [
                            'post_id'         => $p->ID,
                            'relevance_score' => 0.5,
                            'match_reason'    => 'keyword_match',
                        ];
                    }
                }
                break;

            case 'geo':
                $city   = get_post_meta( $source_post->ID, '_rzpa_city', true );
                $region = get_post_meta( $source_post->ID, '_rzpa_region', true );
                if ( $city || $region ) {
                    $posts = self::search_posts_by_geo( $city, $region, $target_type, $source_post->ID );
                    foreach ( $posts as $p ) {
                        $results[ $p->ID ] = [
                            'post_id'         => $p->ID,
                            'relevance_score' => 0.7,
                            'match_reason'    => 'geo_match',
                        ];
                    }
                }
                break;

            case 'category':
                $terms = wp_get_post_terms( $source_post->ID, 'category', [ 'fields' => 'ids' ] );
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    $args  = [
                        'post_type'      => self::resolve_post_types( $target_type ),
                        'post_status'    => 'publish',
                        'posts_per_page' => 20,
                        'post__not_in'   => [ $source_post->ID ],
                        'tax_query'      => [ [ 'taxonomy' => 'category', 'field' => 'id', 'terms' => $terms ] ],
                    ];
                    $query = new WP_Query( $args );
                    foreach ( $query->posts as $p ) {
                        $results[ $p->ID ] = [
                            'post_id'         => $p->ID,
                            'relevance_score' => 0.6,
                            'match_reason'    => 'category_match',
                        ];
                    }
                }
                break;

            case 'intent':
                $intent = get_post_meta( $source_post->ID, '_rzpa_search_intent', true );
                if ( $intent ) {
                    global $wpdb;
                    $post_types = self::resolve_post_types( $target_type );
                    $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $ids = $wpdb->get_col( $wpdb->prepare(
                        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE pm.meta_key = '_rzpa_search_intent'
                         AND pm.meta_value = %s
                         AND p.post_type IN ({$placeholders})
                         AND p.post_status = 'publish'
                         AND p.ID != %d
                         LIMIT 20",
                        array_merge( [ $intent ], $post_types, [ $source_post->ID ] )
                    ) );
                    foreach ( (array) $ids as $id ) {
                        $results[ (int) $id ] = [
                            'post_id'         => (int) $id,
                            'relevance_score' => 0.5,
                            'match_reason'    => 'intent_match',
                        ];
                    }
                }
                break;
        }

        return $results;
    }

    /**
     * Scores the relevance between a source and target post.
     *
     * @param WP_Post $source
     * @param WP_Post $target
     * @return float  0.0–1.0
     */
    public static function score_relevance( WP_Post $source, WP_Post $target ) : float {
        $score = 0.0;

        // 50 %: Keyword overlap between titles.
        $source_words = self::title_words( $source->post_title );
        $target_words = self::title_words( $target->post_title );
        if ( $source_words && $target_words ) {
            $common      = array_intersect( $source_words, $target_words );
            $overlap     = count( $common ) / max( count( $source_words ), 1 );
            $score      += 0.50 * min( 1.0, $overlap * 2 );
        }

        // 20 %: Shared categories/tags.
        $source_terms = self::get_term_ids( $source->ID );
        $target_terms = self::get_term_ids( $target->ID );
        if ( $source_terms && $target_terms ) {
            $shared  = count( array_intersect( $source_terms, $target_terms ) );
            $score  += 0.20 * min( 1.0, $shared / max( count( $source_terms ), 1 ) );
        }

        // 20 %: Same geo data.
        $s_city   = (string) get_post_meta( $source->ID, '_rzpa_city',   true );
        $t_city   = (string) get_post_meta( $target->ID, '_rzpa_city',   true );
        $s_region = (string) get_post_meta( $source->ID, '_rzpa_region', true );
        $t_region = (string) get_post_meta( $target->ID, '_rzpa_region', true );
        $geo_match = 0;
        if ( $s_city && $s_city === $t_city ) {
            $geo_match = 1;
        } elseif ( $s_region && $s_region === $t_region ) {
            $geo_match = 0.5;
        }
        $score += 0.20 * $geo_match;

        // 10 %: Same search intent.
        $s_intent = (string) get_post_meta( $source->ID, '_rzpa_search_intent', true );
        $t_intent = (string) get_post_meta( $target->ID, '_rzpa_search_intent', true );
        if ( $s_intent && $s_intent === $t_intent ) {
            $score += 0.10;
        }

        return round( min( 1.0, $score ), 4 );
    }

    /**
     * Finds link suggestions for a dataset record.
     *
     * @param int $dataset_id
     * @return array
     */
    public static function get_suggestions_for_dataset( int $dataset_id ) : array {
        $dataset = RZPA_SEO_DB::get_dataset( $dataset_id );
        if ( ! $dataset ) {
            return [];
        }

        $linked_post_id = (int) ( $dataset['linked_post_id'] ?? 0 );
        if ( $linked_post_id ) {
            return self::find_suggestions( $linked_post_id );
        }

        // Post not yet generated — do a keyword-based search.
        $keyword = $dataset['primary_keyword'] ?? $dataset['keyword'] ?? '';
        if ( ! $keyword ) {
            return [];
        }

        $results = [];
        foreach ( [ 'rzpa_pseo', 'post' ] as $type ) {
            $posts = self::search_posts_by_keywords( [ $keyword ], $type, 0 );
            foreach ( $posts as $p ) {
                $results[] = [
                    'post_id'         => $p->ID,
                    'post_title'      => $p->post_title,
                    'post_type'       => $p->post_type,
                    'url'             => get_permalink( $p ),
                    'relevance_score' => 0.3,
                    'match_reason'    => 'keyword_pre_generate',
                ];
            }
        }

        return array_slice( $results, 0, 10 );
    }

    /**
     * Generates and caches link suggestions for a post.
     *
     * @param int $post_id
     * @return array
     */
    public static function auto_suggest( int $post_id ) : array {
        $suggestions = self::find_suggestions( $post_id );

        if ( ! empty( $suggestions ) ) {
            update_post_meta( $post_id, self::CACHE_META_KEY, wp_json_encode( [
                'generated_at' => time(),
                'suggestions'  => $suggestions,
            ] ) );
        }

        RZPA_SEO_DB::log( 'linking', $post_id, 'link_suggest', sprintf(
            '%d linkforslag genereret for post #%d.',
            count( $suggestions ),
            $post_id
        ), 'info' );

        return $suggestions;
    }

    /**
     * Returns cached link suggestions, re-generating if stale.
     *
     * @param int $post_id
     * @return array
     */
    public static function get_cached_suggestions( int $post_id ) : array {
        $cached = get_post_meta( $post_id, self::CACHE_META_KEY, true );

        if ( $cached ) {
            $data = json_decode( $cached, true );
            if ( is_array( $data ) && isset( $data['generated_at'], $data['suggestions'] ) ) {
                $age = time() - (int) $data['generated_at'];
                if ( $age < self::CACHE_TTL ) {
                    return $data['suggestions'];
                }
            }
        }

        return self::auto_suggest( $post_id );
    }

    /**
     * Inserts or replaces a link block in a post's content.
     *
     * @param int                  $post_id
     * @param array<int, array{url: string, label: string}> $links
     * @return bool
     */
    public static function insert_link_block( int $post_id, array $links ) : bool {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $supported_types = [ 'rzpa_pseo', 'post', 'page' ];
        if ( ! in_array( $post->post_type, $supported_types, true ) ) {
            return false;
        }

        if ( empty( $links ) ) {
            return false;
        }

        $items = '';
        foreach ( $links as $link ) {
            $url   = esc_url( $link['url']   ?? '' );
            $label = esc_html( $link['label'] ?? $url );
            if ( $url ) {
                $items .= "<li><a href=\"{$url}\">{$label}</a></li>";
            }
        }

        if ( ! $items ) {
            return false;
        }

        $block   = '<div class="rzpa-link-block"><h3>' . __( 'Relaterede sider', 'rezponz-analytics' ) . '</h3><ul>' . $items . '</ul></div>';
        $content = $post->post_content;

        // Replace existing block, or append.
        if ( false !== strpos( $content, 'class="rzpa-link-block"' ) ) {
            $content = preg_replace( '/<div class="rzpa-link-block">.*?<\/div>/s', $block, $content );
        } else {
            $content .= "\n" . $block;
        }

        $result = wp_update_post( [
            'ID'           => $post_id,
            'post_content' => $content,
        ] );

        if ( is_wp_error( $result ) ) {
            RZPA_SEO_DB::log( 'linking', $post_id, 'link_suggest', 'Kunne ikke indsætte link-blok: ' . $result->get_error_message(), 'error' );
            return false;
        }

        RZPA_SEO_DB::log( 'linking', $post_id, 'link_suggest', sprintf( '%d links indsat i post #%d.', count( $links ), $post_id ), 'success' );
        return true;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Extracts keywords from a post's meta data.
     *
     * @param WP_Post $post
     * @return string[]
     */
    private static function extract_post_keywords( WP_Post $post ) : array {
        $keywords = [];

        $primary = get_post_meta( $post->ID, '_rzpa_primary_keyword', true );
        $kw      = get_post_meta( $post->ID, '_rzpa_keyword', true );
        if ( $primary ) {
            $keywords[] = (string) $primary;
        }
        if ( $kw && $kw !== $primary ) {
            $keywords[] = (string) $kw;
        }

        // Fallback: extract significant words from post title.
        if ( empty( $keywords ) ) {
            $title_words = self::title_words( $post->post_title );
            $keywords    = array_slice( $title_words, 0, 3 );
        }

        return $keywords;
    }

    /**
     * Searches posts by keyword match in title or meta.
     *
     * @param string[] $keywords
     * @param string   $type       'pseo'|'blog'|'page'|'any'
     * @param int      $exclude_id
     * @return WP_Post[]
     */
    private static function search_posts_by_keywords( array $keywords, string $type, int $exclude_id ) : array {
        if ( empty( $keywords ) ) {
            return [];
        }

        $post_types  = self::resolve_post_types( $type );
        $all_results = [];

        foreach ( $keywords as $kw ) {
            $kw = sanitize_text_field( $kw );
            if ( ! $kw ) {
                continue;
            }
            $args = [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                's'              => $kw,
                'post__not_in'   => $exclude_id ? [ $exclude_id ] : [],
            ];
            $query = new WP_Query( $args );
            foreach ( $query->posts as $p ) {
                $all_results[ $p->ID ] = $p;
            }
        }

        return array_values( $all_results );
    }

    /**
     * Searches rzpa_pseo posts sharing the same city or region.
     *
     * @param string $city
     * @param string $region
     * @param string $type
     * @param int    $exclude_id
     * @return WP_Post[]
     */
    private static function search_posts_by_geo( string $city, string $region, string $type, int $exclude_id ) : array {
        $meta_queries = [];
        if ( $city ) {
            $meta_queries[] = [ 'key' => '_rzpa_city', 'value' => $city, 'compare' => '=' ];
        }
        if ( $region ) {
            $meta_queries[] = [ 'key' => '_rzpa_region', 'value' => $region, 'compare' => '=' ];
        }
        if ( empty( $meta_queries ) ) {
            return [];
        }
        if ( count( $meta_queries ) > 1 ) {
            $meta_queries['relation'] = 'OR';
        }

        $args = [
            'post_type'      => self::resolve_post_types( $type ),
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'post__not_in'   => $exclude_id ? [ $exclude_id ] : [],
            'meta_query'     => $meta_queries,
        ];

        $query = new WP_Query( $args );
        return $query->posts;
    }

    /**
     * Fallback keyword search when no rules are configured.
     *
     * @param WP_Post $source
     * @return array<int, array{post_id: int, relevance_score: float, match_reason: string}>
     */
    private static function keyword_fallback_search( WP_Post $source ) : array {
        $keywords = self::extract_post_keywords( $source );
        $posts    = self::search_posts_by_keywords( $keywords, 'any', $source->ID );
        $results  = [];
        foreach ( $posts as $p ) {
            $results[ $p->ID ] = [
                'post_id'         => $p->ID,
                'relevance_score' => 0.3,
                'match_reason'    => 'keyword_fallback',
            ];
        }
        return $results;
    }

    /**
     * Resolves a type string to an array of WP post_type values.
     *
     * @param string $type  pseo|blog|page|any
     * @return string[]
     */
    private static function resolve_post_types( string $type ) : array {
        return match ( $type ) {
            'pseo'  => [ 'rzpa_pseo' ],
            'blog'  => [ 'post' ],
            'page'  => [ 'page' ],
            default => [ 'rzpa_pseo', 'post', 'page' ],
        };
    }

    /**
     * Returns significant lowercase words from a post title.
     *
     * @param string $title
     * @return string[]
     */
    private static function title_words( string $title ) : array {
        // Strip short stop-words (< 3 chars) for cleaner overlap scoring.
        preg_match_all( '/\p{L}{3,}/u', mb_strtolower( $title ), $matches );
        return $matches[0] ?? [];
    }

    /**
     * Returns all taxonomy term IDs for a post (categories + tags).
     *
     * @param int $post_id
     * @return int[]
     */
    private static function get_term_ids( int $post_id ) : array {
        $ids   = [];
        $taxos = [ 'category', 'post_tag' ];
        foreach ( $taxos as $tax ) {
            $terms = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $terms ) ) {
                $ids = array_merge( $ids, (array) $terms );
            }
        }
        return array_map( 'intval', $ids );
    }
}
