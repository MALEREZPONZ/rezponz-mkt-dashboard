<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – pSEO Page Generator.
 *
 * Generates, regenerates, and bulk-generates WordPress posts of type
 * 'rzpa_pseo' from dataset records.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_Generator {

    // ── Single page generation ────────────────────────────────────────────────

    /**
     * Generates (or updates) a pSEO post from a dataset record.
     *
     * @param int    $dataset_id
     * @param string $publish_status  draft|pending|publish
     * @return array{success: bool, post_id: int|null, errors: string[], warnings: string[], skipped: bool}
     */
    public static function generate_page( int $dataset_id, string $publish_status = 'draft' ) : array {
        $result = [
            'success'  => false,
            'post_id'  => null,
            'errors'   => [],
            'warnings' => [],
            'skipped'  => false,
        ];

        // 1. Load dataset.
        $dataset = RZPA_SEO_DB::get_dataset( $dataset_id );
        if ( ! $dataset ) {
            $result['errors'][] = sprintf( 'Dataset #%d ikke fundet.', $dataset_id );
            RZPA_SEO_DB::log( 'pseo', $dataset_id, 'generate', 'Dataset ikke fundet.', 'error' );
            return $result;
        }

        // 2. Validate dataset.
        $validation = self::validate_dataset( $dataset );
        if ( ! $validation['valid'] ) {
            $result['errors'] = $validation['errors'];
            RZPA_SEO_DB::update_dataset_status( $dataset_id, 'failed' );
            RZPA_SEO_DB::log( 'pseo', $dataset_id, 'generate', implode( '; ', $validation['errors'] ), 'error' );
            return $result;
        }
        $result['warnings'] = $validation['warnings'];

        // 3. Load template.
        $template = RZPA_SEO_DB::get_template( (int) $dataset['template_id'] );
        if ( ! $template ) {
            $result['errors'][] = sprintf( 'Template #%d ikke fundet.', $dataset['template_id'] );
            RZPA_SEO_DB::update_dataset_status( $dataset_id, 'failed' );
            RZPA_SEO_DB::log( 'pseo', $dataset_id, 'generate', 'Template ikke fundet.', 'error' );
            return $result;
        }

        $template_config = json_decode( $template['template_config'] ?? '{}', true );
        if ( ! is_array( $template_config ) ) {
            $template_config = [];
        }

        // 4. Render template fields.
        $rendered = RZPA_SEO_Template::render_template( (int) $dataset['template_id'], $dataset );

        // 5. Build slug.
        $existing_post_id  = (int) ( $dataset['linked_post_id'] ?? 0 );
        $slug_pattern      = $template_config['slug_pattern'] ?? '{primary_keyword}-{city}';
        $slug              = RZPA_SEO_Template::build_slug( $slug_pattern, $dataset, $existing_post_id );

        // 6. Quality check.
        $quality = RZPA_SEO_Quality::check( $rendered, $template_config, $existing_post_id );

        // 7. Build post array.
        $post_excerpt = wp_trim_words( strip_tags( $rendered['intro'] ), 50, '' );
        if ( mb_strlen( $post_excerpt ) > 300 ) {
            $post_excerpt = mb_substr( $post_excerpt, 0, 297 ) . '...';
        }

        $post_data = [
            'post_type'    => 'rzpa_pseo',
            'post_title'   => wp_strip_all_tags( $rendered['title'] ),
            'post_content' => $rendered['content_html'],
            'post_excerpt' => $post_excerpt,
            'post_status'  => in_array( $publish_status, [ 'draft', 'pending', 'publish' ], true ) ? $publish_status : 'draft',
            'post_name'    => $slug,
        ];

        // Schema JSON-LD appended to content if present.
        if ( ! empty( $rendered['schema_json'] ) ) {
            $post_data['post_content'] .= "\n" . '<script type="application/ld+json">' . "\n"
                . $rendered['schema_json'] . "\n" . '</script>';
        }

        // 8. Insert or update WP post.
        $wp_error = null;
        if ( $existing_post_id > 0 && get_post( $existing_post_id ) ) {
            $post_data['ID'] = $existing_post_id;
            $post_id         = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            $result['errors'][] = $post_id->get_error_message();
            RZPA_SEO_DB::update_dataset_status( $dataset_id, 'failed' );
            RZPA_SEO_DB::log( 'pseo', $dataset_id, 'generate', $post_id->get_error_message(), 'error' );
            return $result;
        }

        $post_id = (int) $post_id;

        // 9. Save post meta.
        self::save_post_meta( $post_id, $rendered, $dataset );

        // 10. Update dataset record.
        RZPA_SEO_DB::update_dataset( $dataset_id, [
            'linked_post_id'    => $post_id,
            'generation_status' => 'draft' === $publish_status ? 'draft' : ( 'publish' === $publish_status ? 'published' : 'review' ),
            'quality_status'    => $quality['status'],
        ] );

        // 11. Log success.
        $was_update = ( $existing_post_id === $post_id );
        $action     = $was_update ? 'regenerate' : 'generate';
        $message    = $was_update
            ? sprintf( 'Post #%d opdateret fra dataset #%d. Kvalitet: %s (%d/100).', $post_id, $dataset_id, $quality['status'], $quality['score'] )
            : sprintf( 'Ny post #%d oprettet fra dataset #%d. Kvalitet: %s (%d/100).', $post_id, $dataset_id, $quality['status'], $quality['score'] );

        $severity = 'failed' === $quality['status'] ? 'warning' : 'success';
        RZPA_SEO_DB::log( 'pseo', $dataset_id, $action, $message, $severity, [
            'post_id'      => $post_id,
            'quality'      => $quality,
            'publish_status' => $publish_status,
        ] );

        if ( ! empty( $quality['results'] ) ) {
            foreach ( $quality['results'] as $check => $check_result ) {
                if ( ! $check_result['passed'] ) {
                    $result['warnings'][] = $check_result['message'];
                }
            }
        }

        // 12. Trigger auto link suggestions if enabled.
        $settings = get_option( 'rzpa_seo_settings', [] );
        if ( ! empty( $settings['auto_link_suggestions'] ) ) {
            RZPA_SEO_Linking::auto_suggest( $post_id );
        }

        $result['success'] = true;
        $result['post_id'] = $post_id;
        return $result;
    }

    // ── Regeneration ─────────────────────────────────────────────────────────

    /**
     * Regenerates an existing pSEO post, respecting manual_override_flags.
     *
     * If the source data hash is unchanged, the generation is skipped.
     *
     * @param int $dataset_id
     * @return array{success: bool, post_id: int|null, errors: string[], warnings: string[], skipped: bool}
     */
    public static function regenerate_page( int $dataset_id ) : array {
        $result = [
            'success'  => false,
            'post_id'  => null,
            'errors'   => [],
            'warnings' => [],
            'skipped'  => false,
        ];

        $dataset = RZPA_SEO_DB::get_dataset( $dataset_id );
        if ( ! $dataset ) {
            $result['errors'][] = sprintf( 'Dataset #%d ikke fundet.', $dataset_id );
            return $result;
        }

        $post_id = (int) ( $dataset['linked_post_id'] ?? 0 );

        // Compare source hash to detect unchanged data.
        $hash_data    = array_diff_key( $dataset, array_flip( [ 'id', 'created_at', 'updated_at', 'generation_status', 'quality_status', 'linked_post_id' ] ) );
        $source_hash  = md5( serialize( $hash_data ) );
        $stored_hash  = $post_id > 0 ? get_post_meta( $post_id, '_rzpa_source_hash', true ) : '';

        if ( $stored_hash && $stored_hash === $source_hash ) {
            $result['skipped'] = true;
            $result['success'] = true;
            $result['post_id'] = $post_id ?: null;
            RZPA_SEO_DB::log( 'pseo', $dataset_id, 'regenerate', 'Sprunget over: kildedata uændret.', 'info' );
            return $result;
        }

        // Decode manual override flags.
        $override_flags = json_decode( $dataset['manual_override_flags'] ?? '{}', true );
        if ( ! is_array( $override_flags ) ) {
            $override_flags = [];
        }

        // Load existing post meta for locked fields.
        $locked_meta = [];
        if ( $post_id > 0 && ! empty( $override_flags ) ) {
            $lockable = [ 'meta_title' => '_rzpa_meta_title', 'meta_description' => '_rzpa_meta_description', 'canonical_url' => '_rzpa_canonical_url' ];
            foreach ( $lockable as $field => $meta_key ) {
                if ( ! empty( $override_flags[ $field ] ) ) {
                    $locked_meta[ $field ] = get_post_meta( $post_id, $meta_key, true );
                }
            }
            // Lock post_title?
            if ( ! empty( $override_flags['title'] ) ) {
                $post = get_post( $post_id );
                if ( $post ) {
                    $locked_meta['title'] = $post->post_title;
                }
            }
        }

        // Merge locked values back into dataset before render.
        $dataset_for_render = array_merge( $dataset, $locked_meta );

        // Delegate to generate_page for the actual work.
        $existing_status = $dataset['generation_status'] ?? 'draft';
        $publish_status  = ( 'published' === $existing_status ) ? 'publish' : 'draft';

        $gen_result = self::generate_page( $dataset_id, $publish_status );

        // If title was locked, restore it.
        if ( ! empty( $locked_meta['title'] ) && $gen_result['success'] && $gen_result['post_id'] ) {
            wp_update_post( [
                'ID'         => $gen_result['post_id'],
                'post_title' => wp_strip_all_tags( $locked_meta['title'] ),
            ] );
        }

        return $gen_result;
    }

    // ── Bulk generation ───────────────────────────────────────────────────────

    /**
     * Bulk generates pSEO pages for an array of dataset IDs.
     *
     * @param int[]  $dataset_ids
     * @param string $status  draft|pending|publish
     * @return array{generated: int, updated: int, skipped: int, failed: int, errors: array<string, string>}
     */
    public static function bulk_generate( array $dataset_ids, string $status = 'draft' ) : array {
        $summary = [
            'generated' => 0,
            'updated'   => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'errors'    => [],
        ];

        if ( empty( $dataset_ids ) ) {
            return $summary;
        }

        $batch_size = 10;
        $chunks     = array_chunk( $dataset_ids, $batch_size );

        foreach ( $chunks as $chunk ) {
            // Extend time limit per batch to avoid timeouts on large imports.
            set_time_limit( 120 );

            foreach ( $chunk as $dataset_id ) {
                $dataset = RZPA_SEO_DB::get_dataset( (int) $dataset_id );
                if ( ! $dataset ) {
                    $summary['failed']++;
                    $summary['errors'][ $dataset_id ] = 'Dataset ikke fundet.';
                    continue;
                }

                $is_update = ! empty( $dataset['linked_post_id'] ) && get_post( (int) $dataset['linked_post_id'] );

                $result = self::generate_page( (int) $dataset_id, $status );

                if ( $result['skipped'] ) {
                    $summary['skipped']++;
                } elseif ( $result['success'] ) {
                    if ( $is_update ) {
                        $summary['updated']++;
                    } else {
                        $summary['generated']++;
                    }
                } else {
                    $summary['failed']++;
                    if ( ! empty( $result['errors'] ) ) {
                        $summary['errors'][ $dataset_id ] = implode( '; ', $result['errors'] );
                    }
                }
            }
        }

        // Log bulk operation summary.
        RZPA_SEO_DB::log( 'pseo', null, 'bulk_generate', sprintf(
            'Bulk generering afsluttet: %d oprettet, %d opdateret, %d sprunget over, %d fejlet.',
            $summary['generated'],
            $summary['updated'],
            $summary['skipped'],
            $summary['failed']
        ), $summary['failed'] > 0 ? 'warning' : 'success', $summary );

        return $summary;
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Validates a dataset array before generation.
     *
     * @param array<string, mixed> $dataset
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public static function validate_dataset( array $dataset ) : array {
        $errors   = [];
        $warnings = [];

        if ( empty( $dataset['keyword'] ) ) {
            $errors[] = 'Keyword er påkrævet.';
        }

        if ( empty( $dataset['primary_keyword'] ) ) {
            $errors[] = 'Primary keyword er påkrævet.';
        }

        $template_id = (int) ( $dataset['template_id'] ?? 0 );
        if ( $template_id <= 0 ) {
            $errors[] = 'Template ID er påkrævet.';
        } elseif ( ! RZPA_SEO_DB::get_template( $template_id ) ) {
            $errors[] = sprintf( 'Template #%d eksisterer ikke.', $template_id );
        }

        if ( ! empty( $dataset['meta_title'] ) && mb_strlen( $dataset['meta_title'] ) > 70 ) {
            $warnings[] = sprintf( 'Meta title er %d tegn. Anbefalet max: 70.', mb_strlen( $dataset['meta_title'] ) );
        }

        if ( ! empty( $dataset['meta_description'] ) && mb_strlen( $dataset['meta_description'] ) > 170 ) {
            $warnings[] = sprintf( 'Meta description er %d tegn. Anbefalet max: 170.', mb_strlen( $dataset['meta_description'] ) );
        }

        return [
            'valid'    => empty( $errors ),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    /**
     * Returns dataset counts grouped by generation_status.
     *
     * @return array<string, int>
     */
    public static function get_status_counts() : array {
        return RZPA_SEO_DB::count_by_status();
    }

    // ── Deletion ─────────────────────────────────────────────────────────────

    /**
     * Permanently deletes the WP post linked to a dataset and resets the dataset.
     *
     * @param int $dataset_id
     * @return bool
     */
    public static function delete_generated_page( int $dataset_id ) : bool {
        $dataset = RZPA_SEO_DB::get_dataset( $dataset_id );
        if ( ! $dataset ) {
            return false;
        }

        $post_id = (int) ( $dataset['linked_post_id'] ?? 0 );

        if ( $post_id > 0 ) {
            $deleted = wp_delete_post( $post_id, true ); // force delete, bypass trash
            if ( ! $deleted ) {
                RZPA_SEO_DB::log( 'pseo', $dataset_id, 'generate', sprintf( 'Kunne ikke slette post #%d.', $post_id ), 'error' );
                return false;
            }
        }

        RZPA_SEO_DB::update_dataset( $dataset_id, [
            'linked_post_id'    => null,
            'generation_status' => 'pending',
        ] );

        RZPA_SEO_DB::log( 'pseo', $dataset_id, 'generate', sprintf( 'Post #%d slettet for dataset #%d.', $post_id, $dataset_id ), 'info' );

        return true;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Saves all SEO-related post meta after generation.
     *
     * @param int                  $post_id
     * @param array<string, mixed> $rendered  Output of RZPA_SEO_Template::render_template().
     * @param array<string, mixed> $dataset
     * @return void
     */
    private static function save_post_meta( int $post_id, array $rendered, array $dataset ) : void {
        $hash_data   = array_diff_key( $dataset, array_flip( [ 'id', 'created_at', 'updated_at', 'generation_status', 'quality_status', 'linked_post_id' ] ) );
        $source_hash = md5( serialize( $hash_data ) );

        update_post_meta( $post_id, '_rzpa_meta_title',       sanitize_text_field( $rendered['meta_title'] ) );
        update_post_meta( $post_id, '_rzpa_meta_description', sanitize_textarea_field( $rendered['meta_description'] ) );
        update_post_meta( $post_id, '_rzpa_dataset_id',       absint( $dataset['id'] ) );
        update_post_meta( $post_id, '_rzpa_template_id',      absint( $dataset['template_id'] ) );
        update_post_meta( $post_id, '_rzpa_source_hash',      $source_hash );

        // Canonical URL: prefer dataset value, otherwise use post permalink (set after insert).
        $canonical = ! empty( $dataset['canonical_url'] )
            ? esc_url_raw( $dataset['canonical_url'] )
            : '';
        update_post_meta( $post_id, '_rzpa_canonical_url', $canonical );

        // noindex: respect dataset indexation_status (0 = noindex).
        $noindex = isset( $dataset['indexation_status'] ) ? ( 0 === (int) $dataset['indexation_status'] ) : false;
        update_post_meta( $post_id, '_rzpa_noindex', $noindex ? 1 : 0 );

        // City/geo meta for linking engine queries.
        if ( ! empty( $dataset['city'] ) ) {
            update_post_meta( $post_id, '_rzpa_city',   sanitize_text_field( $dataset['city'] ) );
        }
        if ( ! empty( $dataset['region'] ) ) {
            update_post_meta( $post_id, '_rzpa_region', sanitize_text_field( $dataset['region'] ) );
        }
        if ( ! empty( $dataset['keyword'] ) ) {
            update_post_meta( $post_id, '_rzpa_keyword',         sanitize_text_field( $dataset['keyword'] ) );
            update_post_meta( $post_id, '_rzpa_primary_keyword', sanitize_text_field( $dataset['primary_keyword'] ?? $dataset['keyword'] ) );
        }
        if ( ! empty( $dataset['search_intent'] ) ) {
            update_post_meta( $post_id, '_rzpa_search_intent', sanitize_text_field( $dataset['search_intent'] ) );
        }
        if ( ! empty( $dataset['category'] ) ) {
            update_post_meta( $post_id, '_rzpa_category', sanitize_text_field( $dataset['category'] ) );
        }
    }
}
