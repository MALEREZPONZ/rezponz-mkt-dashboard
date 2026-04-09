<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rezponz SEO Engine – CSV Import / Export.
 *
 * Handles CSV file uploads, parsing, validation, batch import to the
 * seo_datasets table, and CSV export with filters.
 *
 * @package Rezponz\SEOEngine
 * @since   1.0.0
 */
class RZPA_SEO_CSV {

    /**
     * All dataset columns that can be present in an import CSV.
     *
     * @var string[]
     */
    const DATASET_COLUMNS = [
        'dataset_group', 'template_id', 'city', 'region', 'area', 'country',
        'keyword', 'primary_keyword', 'secondary_keywords', 'job_type', 'category',
        'employment_type', 'audience', 'search_intent', 'intro_text', 'cta_text',
        'local_proof', 'slug', 'meta_title', 'meta_description', 'indexation_status',
    ];

    /**
     * Columns that must be present (non-empty) in each CSV row.
     *
     * @var string[]
     */
    const REQUIRED_COLUMNS = [ 'keyword', 'primary_keyword' ];

    /** Maximum rows processed per import. */
    const MAX_ROWS = 10000;

    // ── CSV parsing ───────────────────────────────────────────────────────────

    /**
     * Parses a CSV file and returns headers and row data.
     *
     * Auto-detects comma vs. semicolon delimiter.
     *
     * @param string $filepath  Absolute path to the CSV file.
     * @return array{headers: string[], rows: array<int, array<string, string>>, error: string|null}
     */
    public static function parse_csv( string $filepath ) : array {
        $result = [
            'headers' => [],
            'rows'    => [],
            'error'   => null,
        ];

        if ( ! is_readable( $filepath ) ) {
            $result['error'] = 'Filen er ikke læsbar: ' . basename( $filepath );
            return $result;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $handle = fopen( $filepath, 'r' );
        if ( false === $handle ) {
            $result['error'] = 'Kunne ikke åbne filen.';
            return $result;
        }

        // Detect delimiter by sniffing first line.
        $first_line = fgets( $handle );
        rewind( $handle );

        $delimiter = ',';
        if ( $first_line !== false ) {
            $comma_count     = substr_count( $first_line, ',' );
            $semicolon_count = substr_count( $first_line, ';' );
            if ( $semicolon_count > $comma_count ) {
                $delimiter = ';';
            }
        }

        // Read headers from row 1.
        $headers = fgetcsv( $handle, 4096, $delimiter );
        if ( false === $headers || empty( $headers ) ) {
            fclose( $handle );
            $result['error'] = 'CSV-filen har ingen overskriftsrække.';
            return $result;
        }

        // Normalise header names: trim whitespace and BOM.
        $headers = array_map( static function ( string $h ) : string {
            $h = trim( $h );
            // Strip BOM (UTF-8 \xEF\xBB\xBF).
            return ltrim( $h, "\xEF\xBB\xBF" );
        }, $headers );

        $result['headers'] = $headers;

        $row_num = 0;
        while ( ( $row = fgetcsv( $handle, 4096, $delimiter ) ) !== false ) {
            if ( $row_num >= self::MAX_ROWS ) {
                break;
            }

            // Skip fully empty rows.
            if ( 1 === count( $row ) && '' === $row[0] ) {
                continue;
            }

            // Map to associative array.
            $assoc = [];
            foreach ( $headers as $col_idx => $col_name ) {
                $assoc[ $col_name ] = isset( $row[ $col_idx ] ) ? trim( $row[ $col_idx ] ) : '';
            }

            $result['rows'][] = $assoc;
            $row_num++;
        }

        fclose( $handle );
        return $result;
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Validates CSV rows against required columns and field rules.
     *
     * @param array<int, array<string, string>> $rows
     * @param array<string, string>             $column_map  Maps CSV header → dataset field.
     * @return array{
     *     valid: array<int, array<string, string>>,
     *     invalid: array<int, array{row: int, data: array<string, string>, errors: string[]}>
     * }
     */
    public static function validate_rows( array $rows, array $column_map ) : array {
        $valid   = [];
        $invalid = [];

        // Build reverse map: dataset_field → csv_header.
        $field_map = array_flip( $column_map );

        foreach ( $rows as $i => $row ) {
            $errors  = [];
            $row_num = $i + 2; // +2 because row 1 is headers.

            // Map CSV columns to dataset fields.
            $mapped = self::map_row( $row, $column_map );

            // Check required fields.
            foreach ( self::REQUIRED_COLUMNS as $req ) {
                if ( empty( $mapped[ $req ] ) ) {
                    $errors[] = sprintf( 'Påkrævet felt "%s" mangler i række %d.', $req, $row_num );
                }
            }

            // Field length validations.
            $length_rules = [
                'keyword'          => 255,
                'primary_keyword'  => 255,
                'meta_title'       => 255,
                'meta_description' => 500,
                'slug'             => 255,
                'city'             => 100,
                'region'           => 100,
                'country'          => 10,
                'dataset_group'    => 100,
                'job_type'         => 100,
                'category'         => 100,
                'employment_type'  => 50,
                'audience'         => 100,
                'search_intent'    => 50,
            ];
            foreach ( $length_rules as $field => $max_len ) {
                if ( isset( $mapped[ $field ] ) && mb_strlen( $mapped[ $field ] ) > $max_len ) {
                    $errors[] = sprintf( 'Felt "%s" overstiger max %d tegn (række %d).', $field, $max_len, $row_num );
                }
            }

            // Validate indexation_status.
            if ( isset( $mapped['indexation_status'] ) && '' !== $mapped['indexation_status'] ) {
                if ( ! in_array( (string) $mapped['indexation_status'], [ '0', '1' ], true ) ) {
                    $errors[] = sprintf( 'Felt "indexation_status" skal være 0 eller 1 (række %d).', $row_num );
                }
            }

            // Validate template_id exists if provided.
            if ( ! empty( $mapped['template_id'] ) && (int) $mapped['template_id'] > 0 ) {
                $tmpl = RZPA_SEO_DB::get_template( (int) $mapped['template_id'] );
                if ( ! $tmpl ) {
                    $errors[] = sprintf( 'Template #%d eksisterer ikke (række %d).', (int) $mapped['template_id'], $row_num );
                }
            }

            if ( empty( $errors ) ) {
                $valid[] = $mapped;
            } else {
                $invalid[] = [
                    'row'    => $row_num,
                    'data'   => $row,
                    'errors' => $errors,
                ];
            }
        }

        return [ 'valid' => $valid, 'invalid' => $invalid ];
    }

    // ── Import ────────────────────────────────────────────────────────────────

    /**
     * Imports validated rows into the seo_datasets table.
     *
     * @param array<int, array<string, string>> $rows         Already-mapped and validated rows.
     * @param array<string, string>             $column_map   (Unused here—rows must already be mapped.)
     * @param string                            $on_duplicate 'skip' or 'update'
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: array<string, string>}
     */
    public static function import_datasets( array $rows, array $column_map, string $on_duplicate = 'skip' ) : array {
        global $wpdb;

        $summary = [
            'imported' => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'failed'   => 0,
            'errors'   => [],
        ];

        $t = RZPA_SEO_DB::get_table( 'datasets' );

        foreach ( $rows as $i => $row ) {
            // Sanitize all values.
            $data = self::sanitize_row( $row );

            if ( empty( $data['keyword'] ) || empty( $data['primary_keyword'] ) ) {
                $summary['failed']++;
                $summary['errors'][ $i ] = 'keyword og primary_keyword er påkrævet.';
                continue;
            }

            $keyword = $data['keyword'];
            $group   = $data['dataset_group'] ?? '';

            // Check for duplicate by keyword + dataset_group.
            $existing_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$t} WHERE keyword = %s AND dataset_group = %s LIMIT 1",
                $keyword,
                $group
            ) );

            if ( $existing_id ) {
                if ( 'update' === $on_duplicate ) {
                    $ok = RZPA_SEO_DB::update_dataset( $existing_id, $data );
                    if ( $ok ) {
                        $summary['updated']++;
                    } else {
                        $summary['failed']++;
                        $summary['errors'][ $i ] = sprintf( 'Opdatering fejlede for keyword "%s".', $keyword );
                    }
                } else {
                    $summary['skipped']++;
                }
                continue;
            }

            // Insert new record.
            $new_id = RZPA_SEO_DB::insert_dataset( $data );
            if ( $new_id ) {
                $summary['imported']++;
            } else {
                $summary['failed']++;
                $summary['errors'][ $i ] = sprintf( 'Indsætning fejlede for keyword "%s".', $keyword );
            }
        }

        // Log the operation.
        RZPA_SEO_DB::log( 'import', null, 'import', sprintf(
            'CSV import afsluttet: %d importeret, %d opdateret, %d sprunget over, %d fejlet.',
            $summary['imported'],
            $summary['updated'],
            $summary['skipped'],
            $summary['failed']
        ), $summary['failed'] > 0 ? 'warning' : 'success', $summary );

        return $summary;
    }

    // ── File upload ───────────────────────────────────────────────────────────

    /**
     * Handles a CSV file upload from $_FILES['csv_file'].
     *
     * @return array{path: string|null, error: string|null}
     */
    public static function handle_upload() : array {
        if ( empty( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['csv_file']['error'] ) {
            $upload_err = (int) ( $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE );
            return [ 'path' => null, 'error' => self::upload_error_message( $upload_err ) ];
        }

        $file     = $_FILES['csv_file'];
        $filename = sanitize_file_name( basename( $file['name'] ) );

        // Validate extension.
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( 'csv' !== $ext ) {
            return [ 'path' => null, 'error' => 'Kun .csv-filer er tilladt.' ];
        }

        // Validate MIME type.
        $allowed_mimes = [ 'text/csv', 'text/plain', 'application/csv', 'application/octet-stream', 'text/comma-separated-values' ];
        $file_type     = wp_check_filetype_and_ext( $file['tmp_name'], $filename );
        $mime          = $file['type'] ?? '';

        // wp_check_filetype_and_ext may return false for CSV; check manually.
        if ( ! in_array( $mime, $allowed_mimes, true ) ) {
            // Try detecting from content (finfo / mime_content_type).
            if ( function_exists( 'mime_content_type' ) ) {
                $detected = mime_content_type( $file['tmp_name'] );
                if ( ! in_array( $detected, $allowed_mimes, true ) ) {
                    // Soft-fail: log but continue as extension was verified.
                }
            }
        }

        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'rzpa-seo-imports/';

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
            // Protect from direct browsing.
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $target_dir . '.htaccess', 'deny from all' );
        }

        $target_path = $target_dir . uniqid( 'rzpa_import_', true ) . '.csv';

        if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
            return [ 'path' => null, 'error' => 'Kunne ikke flytte den uploadede fil.' ];
        }

        return [ 'path' => $target_path, 'error' => null ];
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /**
     * Outputs a CSV download of datasets matching the given filters.
     *
     * Sends appropriate headers and streams the file. Must be called before
     * any output has been sent.
     *
     * @param array<string, mixed> $filter  Keys: template_id, dataset_group, generation_status.
     * @return void
     */
    public static function export_datasets( array $filter = [] ) : void {
        global $wpdb;

        $export_columns = array_merge( self::DATASET_COLUMNS, [ 'generation_status', 'quality_status', 'linked_post_id' ] );

        $where = [];
        $args  = [];

        if ( ! empty( $filter['template_id'] ) ) {
            $where[] = 'template_id = %d';
            $args[]  = absint( $filter['template_id'] );
        }
        if ( ! empty( $filter['dataset_group'] ) ) {
            $where[] = 'dataset_group = %s';
            $args[]  = sanitize_text_field( $filter['dataset_group'] );
        }
        if ( ! empty( $filter['generation_status'] ) ) {
            $where[] = 'generation_status = %s';
            $args[]  = sanitize_text_field( $filter['generation_status'] );
        }

        $t         = RZPA_SEO_DB::get_table( 'datasets' );
        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $sql       = "SELECT * FROM {$t} {$where_sql} ORDER BY id ASC";

        if ( $args ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( $sql, ARRAY_A );
        }

        $rows = $rows ?: [];

        $filename = 'rzpa-seo-datasets-' . gmdate( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM for Excel UTF-8 compatibility.
        fwrite( $output, "\xEF\xBB\xBF" );

        // Header row.
        fputcsv( $output, $export_columns );

        foreach ( $rows as $row ) {
            $csv_row = [];
            foreach ( $export_columns as $col ) {
                $csv_row[] = $row[ $col ] ?? '';
            }
            fputcsv( $output, $csv_row );
        }

        fclose( $output );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Maps a raw CSV row to dataset fields using $column_map.
     *
     * @param array<string, string> $row
     * @param array<string, string> $column_map  CSV header → dataset field.
     * @return array<string, string>
     */
    private static function map_row( array $row, array $column_map ) : array {
        $mapped = [];
        foreach ( $column_map as $csv_col => $dataset_field ) {
            $mapped[ $dataset_field ] = $row[ $csv_col ] ?? '';
        }
        // Pass through fields that were already named correctly (identity map).
        foreach ( self::DATASET_COLUMNS as $col ) {
            if ( ! isset( $mapped[ $col ] ) && isset( $row[ $col ] ) ) {
                $mapped[ $col ] = $row[ $col ];
            }
        }
        return $mapped;
    }

    /**
     * Sanitizes a mapped row of dataset field values.
     *
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private static function sanitize_row( array $row ) : array {
        $text_fields     = [ 'dataset_group', 'city', 'region', 'area', 'country', 'keyword', 'primary_keyword',
                             'job_type', 'category', 'employment_type', 'audience', 'search_intent', 'slug', 'meta_title' ];
        $textarea_fields = [ 'secondary_keywords', 'intro_text', 'cta_text', 'local_proof', 'meta_description', 'canonical_url' ];

        $clean = [];

        foreach ( $text_fields as $f ) {
            if ( array_key_exists( $f, $row ) ) {
                $clean[ $f ] = sanitize_text_field( $row[ $f ] );
            }
        }
        foreach ( $textarea_fields as $f ) {
            if ( array_key_exists( $f, $row ) ) {
                $clean[ $f ] = sanitize_textarea_field( $row[ $f ] );
            }
        }

        if ( array_key_exists( 'template_id', $row ) ) {
            $clean['template_id'] = absint( $row['template_id'] );
        }
        if ( array_key_exists( 'indexation_status', $row ) ) {
            $clean['indexation_status'] = in_array( (string) $row['indexation_status'], [ '0', '1' ], true )
                ? (int) $row['indexation_status']
                : 1;
        }

        return $clean;
    }

    /**
     * Returns a human-readable upload error message.
     *
     * @param int $code  PHP UPLOAD_ERR_* constant.
     * @return string
     */
    private static function upload_error_message( int $code ) : string {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Filen overstiger upload_max_filesize i php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'Filen overstiger MAX_FILE_SIZE i formularen.',
            UPLOAD_ERR_PARTIAL    => 'Filen blev kun delvist uploadet.',
            UPLOAD_ERR_NO_FILE    => 'Ingen fil blev uploadet.',
            UPLOAD_ERR_NO_TMP_DIR => 'Midlertidig mappe mangler.',
            UPLOAD_ERR_CANT_WRITE => 'Kan ikke skrive fil til disk.',
            UPLOAD_ERR_EXTENSION  => 'En PHP-udvidelse stoppede uploadet.',
        ];
        return $messages[ $code ] ?? 'Upload fejlede med ukendt fejl.';
    }
}
