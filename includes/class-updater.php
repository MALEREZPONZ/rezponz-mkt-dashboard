<?php
/**
 * RZPA_Updater
 *
 * Integrerer med WordPress' native plugin-opdateringssystem via GitHub Releases.
 *
 * Workflow:
 *  1. Du opretter et GitHub-repo og uploader en ny ZIP som release-asset.
 *  2. WordPress tjekker automatisk (og via "Søg efter opdateringer") om der
 *     er en ny version.
 *  3. Opdateringen installeres med ét klik præcis som et officielt plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Updater {

    private string $plugin_file;   // fuld sti til rezponz-analytics.php
    private string $plugin_slug;   // rezponz-analytics/rezponz-analytics.php
    private string $plugin_basename;

    private string $github_owner;
    private string $github_repo;
    private string $github_token;  // valgfri – til private repos

    private ?object $release_cache = null; // in-memory cache pr. request

    const TRANSIENT_KEY     = 'rzpa_github_release';
    const TRANSIENT_TTL     = 6 * HOUR_IN_SECONDS;
    const TRANSIENT_TTL_ERR = 30 * MINUTE_IN_SECONDS; // kortere TTL ved fejl

    public function __construct(
        string $plugin_file,
        string $github_owner,
        string $github_repo,
        string $github_token = ''
    ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->plugin_basename = dirname( $this->plugin_slug );
        $this->github_owner    = $github_owner;
        $this->github_repo     = $github_repo;
        $this->github_token    = $github_token;
    }

    /** Registrér alle hooks. */
    public function init() : void {
        // Injicer opdateringsinfo i WP's update-transient
        add_filter( 'pre_set_site_transient_update_plugins',
                    [ $this, 'filter_update_transient' ] );

        // Levér plugin-info til "Vis version X.X detaljer"-popup
        add_filter( 'plugins_api',
                    [ $this, 'filter_plugins_api' ], 20, 3 );

        // Ryd cache efter en opdatering er installeret
        add_action( 'upgrader_process_complete',
                    [ $this, 'clear_cache_after_update' ], 10, 2 );

        // Vis GitHub-konfigurationsstatus på plugins-siden
        add_filter( 'plugin_row_meta',
                    [ $this, 'plugin_row_meta' ], 10, 2 );
    }

    // ── Hent seneste GitHub-release ──────────────────────────────────────────

    private function get_latest_release() : ?object {
        // 1. In-memory cache
        if ( $this->release_cache !== null ) {
            return $this->release_cache === false ? null : $this->release_cache;
        }

        // 2. WordPress transient cache
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) {
            $this->release_cache = $cached;
            return $cached === 'error' ? null : $cached;
        }

        // 3. GitHub API-kald
        if ( ! $this->github_owner || ! $this->github_repo ) {
            set_transient( self::TRANSIENT_KEY, 'error', self::TRANSIENT_TTL_ERR );
            $this->release_cache = false;
            return null;
        }

        $url = "https://api.github.com/repos/{$this->github_owner}/{$this->github_repo}/releases/latest";

        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Rezponz-Analytics-Updater/' . RZPA_VERSION,
            ],
        ];
        if ( $this->github_token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->github_token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( self::TRANSIENT_KEY, 'error', self::TRANSIENT_TTL_ERR );
            $this->release_cache = false;
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $release->tag_name ) ) {
            set_transient( self::TRANSIENT_KEY, 'error', self::TRANSIENT_TTL_ERR );
            $this->release_cache = false;
            return null;
        }

        set_transient( self::TRANSIENT_KEY, $release, self::TRANSIENT_TTL );
        $this->release_cache = $release;
        return $release;
    }

    /** Returner den rene versionstring uden evt. "v"-præfiks. */
    private function clean_version( string $tag ) : string {
        return ltrim( $tag, 'v' );
    }

    /** Find download-URL: brug første .zip-asset, ellers zipball_url. */
    private function get_download_url( object $release ) : string {
        foreach ( ( $release->assets ?? [] ) as $asset ) {
            if ( str_ends_with( strtolower( $asset->name ), '.zip' ) ) {
                return $asset->browser_download_url;
            }
        }
        return $release->zipball_url ?? '';
    }

    // ── WordPress-hooks ───────────────────────────────────────────────────────

    /**
     * Injicer opdateringsdata i WP's update-transient så plugin-siden
     * og dashboardet viser opdateringsbanneret.
     */
    public function filter_update_transient( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_latest_release();
        if ( ! $release ) return $transient;

        $latest  = $this->clean_version( $release->tag_name );
        $current = $transient->checked[ $this->plugin_slug ] ?? RZPA_VERSION;

        if ( version_compare( $latest, $current, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'          => $this->plugin_slug,
                'slug'        => $this->plugin_basename,
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest,
                'url'         => "https://github.com/{$this->github_owner}/{$this->github_repo}",
                'package'     => $this->get_download_url( $release ),
                'icons'       => [],
                'banners'     => [],
                'tested'      => '6.6',
                'requires_php'=> '8.0',
            ];
        } else {
            // Fortæl WP eksplicit at ingen opdatering er tilgængelig
            $transient->no_update[ $this->plugin_slug ] = (object) [
                'id'          => $this->plugin_slug,
                'slug'        => $this->plugin_basename,
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest,
                'url'         => "https://github.com/{$this->github_owner}/{$this->github_repo}",
            ];
        }

        return $transient;
    }

    /**
     * Levér plugin-metadata til "Vis detaljer"-popup i WP admin.
     */
    public function filter_plugins_api( $result, string $action, object $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ( $args->slug ?? '' ) !== $this->plugin_basename ) return $result;

        $release = $this->get_latest_release();
        if ( ! $release ) return $result;

        $latest   = $this->clean_version( $release->tag_name );
        $body     = $release->body ?? '';
        $changelog = $this->markdown_to_html( $body );

        return (object) [
            'name'          => 'Rezponz Analytics',
            'slug'          => $this->plugin_basename,
            'version'       => $latest,
            'author'        => '<a href="https://rezponz.dk">Rezponz</a>',
            'homepage'      => "https://github.com/{$this->github_owner}/{$this->github_repo}",
            'short_description' => 'Marketing Intelligence Dashboard – SEO, Meta, Snapchat, TikTok Ads & AI-synlighed.',
            'sections'      => [
                'description' => '<p>Komplet marketing intelligence dashboard til Rezponz med SEO, AI-søgesynlighed, Meta, Snapchat og TikTok Ads.</p>',
                'changelog'   => $changelog ?: '<p>Se <a href="' . esc_url( $release->html_url ?? '' ) . '" target="_blank">GitHub release notes</a> for detaljer.</p>',
            ],
            'download_link' => $this->get_download_url( $release ),
            'last_updated'  => $release->published_at ?? '',
            'requires'      => '6.0',
            'tested'        => '6.6',
            'requires_php'  => '8.0',
            'banners'       => [],
        ];
    }

    /** Ryd transient-cache efter opdatering er gennemført. */
    public function clear_cache_after_update( $upgrader, array $hook_extra ) : void {
        if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) return;
        if ( in_array( $this->plugin_slug, (array) ( $hook_extra['plugins'] ?? [] ), true ) ) {
            delete_transient( self::TRANSIENT_KEY );
            $this->release_cache = null;
        }
    }

    /** Tilføj GitHub-link og versionstjek til plugin-rækken. */
    public function plugin_row_meta( array $links, string $file ) : array {
        if ( $file !== $this->plugin_slug ) return $links;

        $repo_url = "https://github.com/{$this->github_owner}/{$this->github_repo}";
        $links[]  = '<a href="' . esc_url( $repo_url ) . '" target="_blank">GitHub</a>';

        if ( ! $this->github_owner || ! $this->github_repo ) {
            $links[] = '<span style="color:#ffaa44">⚠ GitHub-repo ikke konfigureret</span>';
        }

        return $links;
    }

    // ── Hjælpefunktioner ─────────────────────────────────────────────────────

    /** Konvertér simpel Markdown til HTML til changelog-visning. */
    private function markdown_to_html( string $md ) : string {
        if ( ! $md ) return '';
        $html = esc_html( $md );
        // Headers
        $html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^## (.+)$/m',  '<h3>$1</h3>', $html );
        $html = preg_replace( '/^# (.+)$/m',   '<h2>$1</h2>', $html );
        // Bullet points
        $html = preg_replace( '/^\* (.+)$/m',  '<li>$1</li>', $html );
        $html = preg_replace( '/^- (.+)$/m',   '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
        // Bold / italic
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        $html = preg_replace( '/\*(.+?)\*/',     '<em>$1</em>',         $html );
        // Line breaks
        $html = nl2br( $html );
        return $html;
    }

    // ── Manuel cache-ryd ──────────────────────────────────────────────────────

    public static function flush_cache() : void {
        delete_transient( self::TRANSIENT_KEY );
    }
}
