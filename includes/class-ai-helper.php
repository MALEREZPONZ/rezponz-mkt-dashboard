<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPA_AI_Helper
 *
 * Delt OpenAI-wrapper brugt af alle moduler.
 * Centraliserer API-kald, fejlhåndtering og token-logik.
 */
class RZPA_AI_Helper {

    /** Standard model til kort/medium indhold */
    const MODEL_FAST = 'gpt-4.1-mini';

    /** Model til lange blogartikler */
    const MODEL_ARTICLE = 'gpt-4o';

    const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Kald OpenAI med en prompt og returnér tekst-svaret.
     *
     * @param string $prompt       Brugerprompt
     * @param string $api_key      OpenAI API-nøgle
     * @param int    $max_tokens   Maks output-tokens
     * @param float  $temperature  0.0–1.0 (0.5 = balanceret, 0.7 = kreativ)
     * @param string $model        Model-ID (brug klasse-konstanterne)
     * @param int    $timeout      HTTP timeout i sekunder
     *
     * @return string|WP_Error     Tekst ved succes, WP_Error ved fejl
     */
    public static function generate(
        string $prompt,
        string $api_key,
        int    $max_tokens  = 2000,
        float  $temperature = 0.5,
        string $model       = self::MODEL_FAST,
        int    $timeout     = 120
    ): string|WP_Error {

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'OpenAI API-nøgle mangler under Indstillinger.' );
        }

        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( max( 180, $timeout + 60 ) );
        }

        $res = wp_remote_post( self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'model'       => $model,
                'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens'  => $max_tokens,
                'temperature' => $temperature,
            ] ),
            'timeout' => $timeout,
        ] );

        if ( is_wp_error( $res ) ) {
            return new WP_Error( 'openai_http', $res->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? ( 'OpenAI fejl HTTP ' . $code );
            return new WP_Error( 'openai_http_' . $code, $msg );
        }

        $finish = $body['choices'][0]['finish_reason'] ?? 'stop';
        $text   = trim( $body['choices'][0]['message']['content'] ?? '' );

        if ( $finish === 'length' ) {
            return new WP_Error(
                'openai_truncated',
                'Svar afskåret (token-grænse). Prøv kortere artikel eller øg max_tokens. (finish_reason=length, max_tokens=' . $max_tokens . ')'
            );
        }

        return $text;
    }

    /**
     * Fjern markdown-kodeblokke (```html … ```) fra AI-output.
     */
    public static function strip_fences( string $text ): string {
        $text = preg_replace( '/^```(?:html|json)?\s*/i', '', trim( $text ) );
        $text = preg_replace( '/\s*```\s*$/i', '', $text );
        return trim( $text );
    }

    /**
     * Udtræk JSON-LD FAQ-schema fra HTML-indhold.
     * Returnerer <script type="application/ld+json">…</script> eller tom streng.
     */
    public static function extract_faq_schema( string $html ): string {
        if ( preg_match( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m ) ) {
            $json = trim( $m[1] );
            $decoded = json_decode( $json, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return '<script type="application/ld+json">' . wp_json_encode( $decoded ) . '</script>';
            }
        }
        return '';
    }

    /**
     * Udtræk og valider FAQ-schema til post meta.
     * Returnerer valideret JSON-streng klar til update_post_meta, eller null.
     */
    public static function build_validated_faq_schema( string $html ): ?string {
        $schema = self::extract_faq_schema( $html );
        if ( ! $schema ) return null;

        // Ekstra validering: JSON skal have @type = FAQPage
        if ( preg_match( '/<script[^>]+>(.*?)<\/script>/is', $schema, $m ) ) {
            $data = json_decode( trim( $m[1] ), true );
            if ( json_last_error() !== JSON_ERROR_NONE ) return null;
        }

        return $schema;
    }
}
