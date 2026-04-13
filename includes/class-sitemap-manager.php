<?php
/**
 * RZPA Sitemap Manager
 *
 * Håndterer oprettelse, redigering og servering af tilpassede XML-sitemaps.
 * Sitemaps tilgås via: /sitemap-{slug}.xml
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Sitemap_Manager {

    /** Valid changefreq-værdier jf. sitemap-protokollen */
    public const CHANGEFREQ_OPTIONS = [
        'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never',
    ];

    /** ── Bootstrap ──────────────────────────────────────────────────────── */
    public static function init(): void {
        add_action( 'init',              [ __CLASS__, 'register_rewrite_rule' ] );
        add_filter( 'query_vars',        [ __CLASS__, 'register_query_var' ] );
        add_action( 'template_redirect', [ __CLASS__, 'serve_sitemap_xml' ] );

        // Auto-flush hvis rewrite-reglen ikke er registreret endnu (fx efter ny upload)
        add_action( 'init', [ __CLASS__, 'maybe_flush_rules' ], 99 );
    }

    /**
     * Flush rewrite-regler én gang hvis rzpa_sitemap query var mangler i de gemte regler.
     * Sætter en transient så vi ikke flusher ved hvert request.
     */
    public static function maybe_flush_rules(): void {
        if ( get_transient( 'rzpa_sitemap_rules_flushed' ) ) return;
        $rules = get_option( 'rewrite_rules', [] );
        $found = false;
        foreach ( array_keys( $rules ?: [] ) as $pattern ) {
            if ( str_contains( $pattern, 'sitemap-' ) && str_contains( $pattern, 'rzpa_sitemap' ) ) {
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            flush_rewrite_rules( false );
        }
        set_transient( 'rzpa_sitemap_rules_flushed', 1, DAY_IN_SECONDS );
    }

    // ── Rewrite ──────────────────────────────────────────────────────────────

    public static function register_rewrite_rule(): void {
        add_rewrite_rule(
            '^sitemap-([^/]+)\.xml$',
            'index.php?rzpa_sitemap=$matches[1]',
            'top'
        );
    }

    /**
     * @param  string[] $vars
     * @return string[]
     */
    public static function register_query_var( array $vars ): array {
        $vars[] = 'rzpa_sitemap';
        return $vars;
    }

    /** Kald ved plugin-aktivering for at registrere rewrite-reglen. */
    public static function flush_rules(): void {
        self::register_rewrite_rule();
        flush_rewrite_rules();
    }

    // ── XML Output ───────────────────────────────────────────────────────────

    public static function serve_sitemap_xml(): void {
        $slug = get_query_var( 'rzpa_sitemap' );
        if ( ! $slug ) return;

        $sitemap = RZPA_Database::get_sitemap_by_slug( sanitize_key( $slug ) );

        if ( ! $sitemap ) {
            status_header( 404 );
            nocache_headers();
            wp_die( 'Sitemap ikke fundet.', 404 );
        }

        $urls = RZPA_Database::get_sitemap_urls( (int) $sitemap->id );

        header( 'Content-Type: application/xml; charset=UTF-8' );
        nocache_headers();

        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<!-- Genereret af Rezponz Analytics – rzpa_sitemap_id=' . (int) $sitemap->id . ' -->';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ( $urls as $row ) {
            $loc = esc_url( $row->url );
            if ( ! $loc ) continue;

            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $loc . '</loc>';

            if ( ! empty( $row->lastmod ) ) {
                $lines[] = '    <lastmod>' . esc_html( $row->lastmod ) . '</lastmod>';
            }
            if ( ! empty( $row->changefreq ) && in_array( $row->changefreq, self::CHANGEFREQ_OPTIONS, true ) ) {
                $lines[] = '    <changefreq>' . esc_html( $row->changefreq ) . '</changefreq>';
            }
            if ( isset( $row->priority ) && $row->priority !== null ) {
                $lines[] = '    <priority>' . number_format( (float) $row->priority, 1 ) . '</priority>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        echo implode( "\n", $lines );
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Generér et unikt slug fra et navn.
     */
    public static function generate_slug( string $name ): string {
        $slug = sanitize_title( $name );
        if ( ! $slug ) {
            $slug = 'sitemap-' . time();
        }
        return $slug;
    }

    /**
     * Returnér den offentlige URL for et sitemap.
     */
    public static function sitemap_url( string $slug ): string {
        return home_url( '/sitemap-' . $slug . '.xml' );
    }
}
