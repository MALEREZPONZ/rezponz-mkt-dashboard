<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – Optional AI Content Layer.
 *
 * Provides optional AI-assisted content generation via OpenAI (GPT-4o) or
 * Anthropic Claude. Falls back gracefully when no API key is configured
 * or the request fails.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_AI {

    /** Option key where settings are stored. */
    const SETTINGS_OPTION = 'rzpa_seo_settings';

    // ── Configuration ─────────────────────────────────────────────────────────

    /**
     * Returns whether an AI provider is fully configured.
     *
     * @return bool
     */
    public static function is_configured() : bool {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        return ! empty( $settings['rzpa_ai_api_key'] );
    }

    /**
     * Returns the active provider identifier.
     *
     * @return string  'openai' | 'claude' | 'none'
     */
    public static function get_provider() : string {
        if ( ! self::is_configured() ) {
            return 'none';
        }
        $settings = get_option( self::SETTINGS_OPTION, [] );
        $provider = $settings['rzpa_ai_provider'] ?? 'openai';
        return in_array( $provider, [ 'openai', 'claude' ], true ) ? $provider : 'openai';
    }

    // ── Blog content generation ───────────────────────────────────────────────

    /**
     * Generates structured blog content for a brief using AI.
     *
     * Falls back to RZPA_SEO_Blog::build_structural_content() if AI is not
     * configured or if the API call fails.
     *
     * @param array<string, mixed> $brief  Brief record from seo_blog_briefs.
     * @return array{
     *     title: string,
     *     intro: string,
     *     sections: array<int, array{heading: string, content: string}>,
     *     faq: array<int, array{q: string, a: string}>,
     *     cta: string,
     *     meta_title: string,
     *     meta_description: string
     * }
     */
    public static function generate_blog_content( array $brief ) : array {
        $empty = [
            'title'            => '',
            'intro'            => '',
            'sections'         => [],
            'faq'              => [],
            'cta'              => '',
            'meta_title'       => '',
            'meta_description' => '',
        ];

        if ( ! self::is_configured() ) {
            // Structural fallback.
            $html = RZPA_SEO_Blog::build_structural_content( $brief );
            return array_merge( $empty, [ 'intro' => $html ] );
        }

        $prompt   = self::build_blog_prompt( $brief );
        $response = self::call_api( $prompt, 2000 );

        if ( is_wp_error( $response ) ) {
            RZPA_SEO_DB::log( 'blog', $brief['id'] ?? null, 'generate', 'AI-fejl: ' . $response->get_error_message(), 'error' );
            $html = RZPA_SEO_Blog::build_structural_content( $brief );
            return array_merge( $empty, [ 'intro' => $html ] );
        }

        $parsed = self::parse_json_response( $response );

        if ( ! $parsed ) {
            RZPA_SEO_DB::log( 'blog', $brief['id'] ?? null, 'generate', 'AI-svar kunne ikke fortolkes som JSON.', 'warning' );
            $html = RZPA_SEO_Blog::build_structural_content( $brief );
            return array_merge( $empty, [ 'intro' => $html ] );
        }

        RZPA_SEO_DB::log( 'blog', $brief['id'] ?? null, 'generate', sprintf(
            'AI indhold genereret for keyword: %s.',
            sanitize_text_field( $brief['primary_keyword'] ?? '' )
        ), 'success', [ 'provider' => self::get_provider(), 'prompt_length' => strlen( $prompt ) ] );

        return [
            'title'            => sanitize_text_field( $parsed['title']            ?? '' ),
            'intro'            => wp_kses_post( $parsed['intro']            ?? '' ),
            'sections'         => self::sanitize_sections( $parsed['sections']         ?? [] ),
            'faq'              => self::sanitize_faq( $parsed['faq']                    ?? [] ),
            'cta'              => wp_kses_post( $parsed['cta']              ?? '' ),
            'meta_title'       => sanitize_text_field( $parsed['meta_title']       ?? '' ),
            'meta_description' => sanitize_textarea_field( $parsed['meta_description'] ?? '' ),
        ];
    }

    // ── Meta generation ───────────────────────────────────────────────────────

    /**
     * Generates an optimised meta title and description from content.
     *
     * @param string $content  Post content (HTML or plain text).
     * @param string $keyword  Target keyword.
     * @return array{meta_title: string, meta_description: string}
     */
    public static function generate_meta( string $content, string $keyword ) : array {
        $empty = [ 'meta_title' => '', 'meta_description' => '' ];

        if ( ! self::is_configured() ) {
            return $empty;
        }

        $excerpt = mb_substr( wp_strip_all_tags( $content ), 0, 1000 );
        $prompt  = sprintf(
            'Du er en dansk SEO-ekspert. Generer en SEO-optimeret meta title (50-60 tegn) og meta description (120-160 tegn) '
            . 'for følgende indhold med focus-keyword: "%s".' . "\n\n"
            . 'Indhold:\n%s' . "\n\n"
            . 'Returner KUN JSON i dette format: {"meta_title": "...", "meta_description": "..."}',
            sanitize_text_field( $keyword ),
            $excerpt
        );

        $response = self::call_api( $prompt, 200 );
        if ( is_wp_error( $response ) ) {
            return $empty;
        }

        $parsed = self::parse_json_response( $response );
        if ( ! $parsed ) {
            return $empty;
        }

        return [
            'meta_title'       => sanitize_text_field( $parsed['meta_title']       ?? '' ),
            'meta_description' => sanitize_textarea_field( $parsed['meta_description'] ?? '' ),
        ];
    }

    // ── FAQ generation ────────────────────────────────────────────────────────

    /**
     * Generates FAQ items for a given keyword.
     *
     * @param string $keyword
     * @param int    $count  Number of Q&A pairs to generate.
     * @return array<int, array{q: string, a: string}>
     */
    public static function generate_faq( string $keyword, int $count = 5 ) : array {
        if ( ! self::is_configured() ) {
            return [];
        }

        $prompt = sprintf(
            'Generer %d hyppigt stillede spørgsmål (FAQ) på dansk om emnet: "%s". '
            . 'Hvert svar skal være 2–4 sætninger, faktuelle og SEO-venlige.' . "\n\n"
            . 'Returner KUN JSON-array i dette format: [{"q":"...","a":"..."}, ...]',
            $count,
            sanitize_text_field( $keyword )
        );

        $response = self::call_api( $prompt, 600 );
        if ( is_wp_error( $response ) ) {
            return [];
        }

        $parsed = self::parse_json_response( $response );
        if ( ! is_array( $parsed ) ) {
            return [];
        }

        return self::sanitize_faq( $parsed );
    }

    // ── Intro generation ──────────────────────────────────────────────────────

    /**
     * Generates an intro paragraph for a pSEO page.
     *
     * @param string $keyword
     * @param string $city      Optional city for geo-targeting.
     * @param string $job_type  Optional job type for context.
     * @return string  HTML paragraph.
     */
    public static function generate_intro( string $keyword, string $city = '', string $job_type = '' ) : string {
        if ( ! self::is_configured() ) {
            return '';
        }

        $geo_context = $city ? sprintf( 'i %s', $city ) : '';
        $job_context = $job_type ? sprintf( 'som %s', $job_type ) : '';

        $prompt = sprintf(
            'Skriv et SEO-optimeret intro-afsnit på 3–5 sætninger på dansk om job %s %s. '
            . 'Focus keyword: "%s". Tonen skal være professionel og informerende. '
            . 'Inkludér keywordet naturligt i første sætning. '
            . 'Returner KUN HTML-afsnit (brug <p>-tags).',
            $geo_context,
            $job_context,
            sanitize_text_field( $keyword )
        );

        $response = self::call_api( $prompt, 300 );
        if ( is_wp_error( $response ) ) {
            return '';
        }

        return wp_kses_post( trim( $response ) );
    }

    // ── Core API caller ───────────────────────────────────────────────────────

    /**
     * Sends a prompt to the configured AI provider and returns the raw text response.
     *
     * @param string $prompt
     * @param int    $max_tokens  Maximum response tokens.
     * @return string|WP_Error  Raw response text, or WP_Error on failure.
     */
    public static function call_api( string $prompt, int $max_tokens = 1000 ) : string|WP_Error {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        $api_key  = $settings['rzpa_ai_api_key'] ?? '';

        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'AI API-nøgle er ikke konfigureret.' );
        }

        $provider = self::get_provider();

        try {
            if ( 'claude' === $provider ) {
                return self::call_claude( $prompt, $api_key, $max_tokens );
            }
            return self::call_openai( $prompt, $api_key, $max_tokens );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'api_exception', $e->getMessage() );
        }
    }

    // ── Prompt builder ────────────────────────────────────────────────────────

    /**
     * Builds a structured Danish prompt for blog generation.
     *
     * @param array<string, mixed> $brief
     * @return string
     */
    public static function build_blog_prompt( array $brief ) : string {
        $keyword     = sanitize_text_field( $brief['primary_keyword']   ?? '' );
        $secondary   = sanitize_text_field( $brief['secondary_keywords'] ?? '' );
        $intent      = sanitize_text_field( $brief['intent']            ?? 'informational' );
        $audience    = sanitize_text_field( $brief['audience']          ?? 'generel læserskare' );
        $tone        = sanitize_text_field( $brief['tone_of_voice']     ?? 'professionel' );
        $type        = sanitize_text_field( $brief['article_type']      ?? 'guide' );
        $length      = absint( $brief['target_length']                   ?? 1500 );
        $depth       = absint( $brief['heading_depth']                   ?? 3 );
        $faq_req     = (bool) ( $brief['faq_required']                   ?? false );
        $cta_type    = sanitize_text_field( $brief['cta_type']           ?? '' );

        $prompt  = "Du er en ekspert i dansk SEO-indholdsproduktion.\n\n";
        $prompt .= "Skriv et SEO-optimeret {$type}-blogindlæg på dansk med følgende specifikationer:\n\n";
        $prompt .= "Focus keyword: {$keyword}\n";
        if ( $secondary ) {
            $prompt .= "Sekundære keywords: {$secondary}\n";
        }
        $prompt .= "Søgeintention: {$intent}\n";
        $prompt .= "Målgruppe: {$audience}\n";
        $prompt .= "Tone of voice: {$tone}\n";
        $prompt .= "Artikel-type: {$type}\n";
        $prompt .= "Omtrent antal ord: {$length}\n";
        $prompt .= "Antal H2-overskrifter: mindst {$depth}\n";
        if ( $faq_req ) {
            $prompt .= "Medtag FAQ-sektion med 4–5 spørgsmål.\n";
        }
        if ( $cta_type ) {
            $prompt .= "Afslut med en CTA af typen: {$cta_type}.\n";
        }

        $prompt .= "\nReturner KUN JSON i dette præcise format:\n";
        $prompt .= "{\n";
        $prompt .= '  "title": "Artikel-titel",';
        $prompt .= "\n";
        $prompt .= '  "meta_title": "SEO meta title (50-60 tegn)",';
        $prompt .= "\n";
        $prompt .= '  "meta_description": "SEO meta description (120-160 tegn)",';
        $prompt .= "\n";
        $prompt .= '  "intro": "<p>Intro-tekst</p>",';
        $prompt .= "\n";
        $prompt .= '  "sections": [{"heading": "H2-overskrift", "content": "Brødtekst"}],';
        $prompt .= "\n";
        $prompt .= '  "faq": [{"q": "Spørgsmål", "a": "Svar"}],';
        $prompt .= "\n";
        $prompt .= '  "cta": "CTA-tekst"';
        $prompt .= "\n}\n";

        return $prompt;
    }

    // ── Provider-specific callers ─────────────────────────────────────────────

    /**
     * Calls the OpenAI Chat Completions API.
     *
     * @param string $prompt
     * @param string $api_key
     * @param int    $max_tokens
     * @return string|WP_Error
     */
    private static function call_openai( string $prompt, string $api_key, int $max_tokens ) : string|WP_Error {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        $model    = $settings['rzpa_ai_model'] ?? 'gpt-4o-mini';

        $body = wp_json_encode( [
            'model'      => $model,
            'messages'   => [
                [ 'role' => 'system', 'content' => 'Du er en hjælpsom dansk SEO-ekspert. Returner altid velformet JSON.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
            'max_tokens'  => $max_tokens,
            'temperature' => 0.7,
        ] );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => $body,
        ] );

        return self::extract_response_text( $response, 'openai' );
    }

    /**
     * Calls the Anthropic Claude Messages API.
     *
     * @param string $prompt
     * @param string $api_key
     * @param int    $max_tokens
     * @return string|WP_Error
     */
    private static function call_claude( string $prompt, string $api_key, int $max_tokens ) : string|WP_Error {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        $model    = $settings['rzpa_ai_model'] ?? 'claude-3-5-haiku-20241022';

        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 15,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => $body,
        ] );

        return self::extract_response_text( $response, 'claude' );
    }

    /**
     * Extracts the text content from a wp_remote_post response.
     *
     * @param array<string, mixed>|WP_Error $response
     * @param string                        $provider  'openai' | 'claude'
     * @return string|WP_Error
     */
    private static function extract_response_text( $response, string $provider ) : string|WP_Error {
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'http_error', 'HTTP-fejl: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );

        if ( (int) $code !== 200 ) {
            $body = json_decode( $raw, true );
            $msg  = $body['error']['message'] ?? $body['error']['type'] ?? ( 'HTTP ' . $code );
            return new WP_Error( 'api_error', 'AI API fejl: ' . $msg );
        }

        $body = json_decode( $raw, true );
        if ( ! is_array( $body ) ) {
            return new WP_Error( 'parse_error', 'Ugyldigt JSON-svar fra AI.' );
        }

        if ( 'openai' === $provider ) {
            return trim( $body['choices'][0]['message']['content'] ?? '' );
        }

        // Claude.
        return trim( $body['content'][0]['text'] ?? '' );
    }

    // ── JSON parsing ──────────────────────────────────────────────────────────

    /**
     * Attempts to parse a JSON string from an AI response.
     *
     * Handles cases where the AI wraps JSON in markdown code fences.
     *
     * @param string $text
     * @return array<string, mixed>|null  Decoded array, or null on failure.
     */
    private static function parse_json_response( string $text ) : ?array {
        // Strip markdown code fences: ```json ... ``` or ``` ... ```
        $text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
        $text = preg_replace( '/\s*```$/i', '', $text );
        $text = trim( $text );

        // Try direct decode.
        $decoded = json_decode( $text, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        // Attempt to extract first JSON object or array via regex.
        if ( preg_match( '/(\{[\s\S]*\}|\[[\s\S]*\])/u', $text, $m ) ) {
            $decoded = json_decode( $m[1], true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Sanitizes AI-returned sections array.
     *
     * @param array<int, array<string, string>> $sections
     * @return array<int, array{heading: string, content: string}>
     */
    private static function sanitize_sections( array $sections ) : array {
        $clean = [];
        foreach ( $sections as $s ) {
            $heading = sanitize_text_field( $s['heading'] ?? $s['h2'] ?? '' );
            $content = wp_kses_post( $s['content'] ?? $s['body'] ?? '' );
            if ( $heading || $content ) {
                $clean[] = [ 'heading' => $heading, 'content' => $content ];
            }
        }
        return $clean;
    }

    /**
     * Sanitizes AI-returned FAQ array.
     *
     * @param array<int, array<string, string>> $faq
     * @return array<int, array{q: string, a: string}>
     */
    private static function sanitize_faq( array $faq ) : array {
        $clean = [];
        foreach ( $faq as $item ) {
            $q = sanitize_text_field( $item['q'] ?? $item['question'] ?? '' );
            $a = wp_kses_post( $item['a'] ?? $item['answer'] ?? '' );
            if ( $q && $a ) {
                $clean[] = [ 'q' => $q, 'a' => $a ];
            }
        }
        return $clean;
    }
}
