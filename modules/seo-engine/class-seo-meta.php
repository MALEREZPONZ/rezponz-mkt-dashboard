<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – SEO Meta Output & Plugin Integration.
 *
 * Outputs meta tags for rzpa_pseo posts and integrates with Yoast SEO
 * and RankMath via filters so they can serve their own meta when active.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Meta {

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    /**
     * Registers all meta-related hooks.
     *
     * @return void
     */
    public static function init() : void {
        add_action( 'wp_head',    [ __CLASS__, 'output_meta' ], 1 );
        add_filter( 'document_title_parts', [ __CLASS__, 'filter_title' ] );

        // Yoast SEO integration.
        add_filter( 'wpseo_title',    [ __CLASS__, 'yoast_title' ] );
        add_filter( 'wpseo_metadesc', [ __CLASS__, 'yoast_metadesc' ] );

        // RankMath integration.
        add_filter( 'rank_math/frontend/title',       [ __CLASS__, 'rankmath_title' ] );
        add_filter( 'rank_math/frontend/description', [ __CLASS__, 'rankmath_desc' ] );

        // Ensure rzpa_pseo posts appear in WordPress core sitemaps.
        add_filter( 'wp_sitemaps_post_types', [ __CLASS__, 'add_to_sitemap' ] );
    }

    // ── Meta output ───────────────────────────────────────────────────────────

    /**
     * Outputs SEO meta tags in wp_head for singular rzpa_pseo posts.
     *
     * Skips output when Yoast SEO or RankMath is active—those plugins
     * handle output themselves via the filters wired in init().
     *
     * @return void
     */
    public static function output_meta() : void {
        if ( ! is_singular( 'rzpa_pseo' ) ) {
            return;
        }

        // Delegate to active SEO plugin if present.
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
            return;
        }

        $post_id     = (int) get_the_ID();
        $meta        = self::get_meta( $post_id );
        $description = $meta['meta_description'] ?? '';
        $canonical   = $meta['canonical_url']    ?? '';
        $noindex     = ! empty( $meta['noindex'] );

        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        } else {
            echo '<link rel="canonical" href="' . esc_url( get_permalink( $post_id ) ) . '">' . "\n";
        }

        if ( $noindex ) {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
        }
    }

    // ── Title filter ──────────────────────────────────────────────────────────

    /**
     * Overrides the document <title> for rzpa_pseo posts.
     *
     * @param array<string, string> $title  WordPress title parts array.
     * @return array<string, string>
     */
    public static function filter_title( array $title ) : array {
        if ( ! is_singular( 'rzpa_pseo' ) ) {
            return $title;
        }

        $post_id    = (int) get_the_ID();
        $meta_title = get_post_meta( $post_id, '_rzpa_meta_title', true );

        if ( $meta_title ) {
            $title['title'] = sanitize_text_field( $meta_title );
            // Remove site tagline to avoid doubling when meta_title already contains the brand.
            unset( $title['tagline'] );
        }

        return $title;
    }

    // ── Yoast SEO integration ─────────────────────────────────────────────────

    /**
     * Provides the custom meta title to Yoast SEO for rzpa_pseo posts.
     *
     * @param string $title  Yoast-generated title.
     * @return string
     */
    public static function yoast_title( string $title ) : string {
        if ( ! is_singular( 'rzpa_pseo' ) ) {
            return $title;
        }
        $post_id    = (int) get_the_ID();
        $meta_title = get_post_meta( $post_id, '_rzpa_meta_title', true );
        return $meta_title ? sanitize_text_field( $meta_title ) : $title;
    }

    /**
     * Provides the custom meta description to Yoast SEO for rzpa_pseo posts.
     *
     * @param string $desc  Yoast-generated description.
     * @return string
     */
    public static function yoast_metadesc( string $desc ) : string {
        if ( ! is_singular( 'rzpa_pseo' ) ) {
            return $desc;
        }
        $post_id = (int) get_the_ID();
        $custom  = get_post_meta( $post_id, '_rzpa_meta_description', true );
        return $custom ? sanitize_textarea_field( $custom ) : $desc;
    }

    // ── RankMath integration ──────────────────────────────────────────────────

    /**
     * Provides the custom meta title to RankMath for rzpa_pseo posts.
     *
     * @param string $title  RankMath-generated title.
     * @return string
     */
    public static function rankmath_title( string $title ) : string {
        if ( ! is_singular( 'rzpa_pseo' ) ) {
            return $title;
        }
        $post_id    = (int) get_the_ID();
        $meta_title = get_post_meta( $post_id, '_rzpa_meta_title', true );
        return $meta_title ? sanitize_text_field( $meta_title ) : $title;
    }

    /**
     * Provides the custom meta description to RankMath for rzpa_pseo posts.
     *
     * @param string $desc  RankMath-generated description.
     * @return string
     */
    public static function rankmath_desc( string $desc ) : string {
        if ( ! is_singular( 'rzpa_pseo' ) ) {
            return $desc;
        }
        $post_id = (int) get_the_ID();
        $custom  = get_post_meta( $post_id, '_rzpa_meta_description', true );
        return $custom ? sanitize_textarea_field( $custom ) : $desc;
    }

    // ── Meta persistence ──────────────────────────────────────────────────────

    /**
     * Saves SEO meta values for a post.
     *
     * @param int                  $post_id
     * @param array<string, mixed> $meta  Keys: meta_title, meta_description, canonical_url, noindex.
     * @return void
     */
    public static function save_meta( int $post_id, array $meta ) : void {
        if ( isset( $meta['meta_title'] ) ) {
            update_post_meta( $post_id, '_rzpa_meta_title', sanitize_text_field( $meta['meta_title'] ) );
        }
        if ( isset( $meta['meta_description'] ) ) {
            update_post_meta( $post_id, '_rzpa_meta_description', sanitize_textarea_field( $meta['meta_description'] ) );
        }
        if ( isset( $meta['canonical_url'] ) ) {
            update_post_meta( $post_id, '_rzpa_canonical_url', esc_url_raw( $meta['canonical_url'] ) );
        }
        if ( isset( $meta['noindex'] ) ) {
            update_post_meta( $post_id, '_rzpa_noindex', (int) (bool) $meta['noindex'] );
        }
    }

    /**
     * Retrieves all SEO meta values for a post.
     *
     * @param int $post_id
     * @return array{meta_title: string, meta_description: string, canonical_url: string, noindex: bool}
     */
    public static function get_meta( int $post_id ) : array {
        return [
            'meta_title'       => (string) get_post_meta( $post_id, '_rzpa_meta_title',       true ),
            'meta_description' => (string) get_post_meta( $post_id, '_rzpa_meta_description', true ),
            'canonical_url'    => (string) get_post_meta( $post_id, '_rzpa_canonical_url',    true ),
            'noindex'          => (bool)   get_post_meta( $post_id, '_rzpa_noindex',           true ),
        ];
    }

    // ── Sitemap ───────────────────────────────────────────────────────────────

    /**
     * Ensures rzpa_pseo posts appear in the WordPress core XML sitemap,
     * while filtering out posts marked noindex.
     *
     * Hooks into wp_sitemaps_post_types to register the CPT,
     * and wp_sitemaps_posts_query_args to exclude noindex posts.
     *
     * @param WP_Post_Type[] $post_types  Array of post type objects.
     * @return WP_Post_Type[]
     */
    public static function add_to_sitemap( array $post_types ) : array {
        // Ensure rzpa_pseo is included in sitemaps.
        if ( ! isset( $post_types['rzpa_pseo'] ) ) {
            $cpt = get_post_type_object( 'rzpa_pseo' );
            if ( $cpt ) {
                $post_types['rzpa_pseo'] = $cpt;
            }
        }

        // Exclude noindex posts via a separate filter (attached here for cohesion).
        if ( ! has_filter( 'wp_sitemaps_posts_query_args', [ __CLASS__, 'sitemap_exclude_noindex' ] ) ) {
            add_filter( 'wp_sitemaps_posts_query_args', [ __CLASS__, 'sitemap_exclude_noindex' ], 10, 2 );
        }

        return $post_types;
    }

    /**
     * Excludes noindex rzpa_pseo posts from the sitemap query.
     *
     * @param array<string, mixed> $args       WP_Query args.
     * @param string               $post_type  The post type being queried.
     * @return array<string, mixed>
     */
    public static function sitemap_exclude_noindex( array $args, string $post_type ) : array {
        if ( 'rzpa_pseo' !== $post_type ) {
            return $args;
        }

        // meta_query: exclude posts where _rzpa_noindex = 1.
        $existing_meta = $args['meta_query'] ?? [];
        $args['meta_query'] = array_merge(
            (array) $existing_meta,
            [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_rzpa_noindex',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_rzpa_noindex',
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ]
        );

        return $args;
    }
}
