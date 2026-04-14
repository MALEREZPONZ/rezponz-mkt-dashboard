<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_CRM_Forms_DB
 *
 * Database-lag for RezCRM Form Builder
 *
 * Tabeller:
 *   rzpz_crm_forms         – formular-definitioner
 *   rzpz_crm_form_fields   – felter i hver formular (drag-sort, konfigurerbare)
 *   rzpz_crm_form_sessions – konverteringsTracking (start → skridt → færdig)
 */
class RZPZ_CRM_Forms_DB {

    const DB_VERSION     = '1';
    const DB_VERSION_KEY = 'rzpz_crm_forms_db_ver';

    /** Felttyper understøttet i form builder */
    const FIELD_TYPES = [
        'section'   => 'Sektion-overskrift',
        'text'      => 'Tekstfelt',
        'textarea'  => 'Tekstboks (lang)',
        'email'     => 'Email',
        'phone'     => 'Telefonnummer',
        'date'      => 'Dato',
        'birthdate' => 'Fødselsdato',
        'radio'     => 'Enkeltvalg (radio)',
        'checkbox'  => 'Flervalg (checkbox)',
        'select'    => 'Dropdown',
        'yes_no'    => 'Ja / Nej',
        'file'      => 'Filupload (CV, certifikater)',
        'profile_photo' => 'Profilbillede',
        'hidden'    => 'Skjult felt',
    ];

    /** Kerne-felter der mappes direkte til rzpz_crm_applicants */
    const CORE_FIELD_MAP = [
        'first_name'   => 'Fornavn',
        'last_name'    => 'Efternavn',
        'email'        => 'Email',
        'phone'        => 'Telefon',
        'address'      => 'Adresse',
        'city'         => 'By',
        'birthdate'    => 'Fødselsdato',
        'cover_letter' => 'Ansøgningstekst',
        'cv_url'       => 'CV-link',
        'notes'        => 'Interne noter',
    ];

    // ── Install ──────────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Formularer
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_forms (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title            VARCHAR(255)    NOT NULL,
            slug             VARCHAR(100)    NOT NULL DEFAULT '',
            position_id      BIGINT UNSIGNED DEFAULT NULL,
            intro_text       LONGTEXT        DEFAULT NULL,
            success_message  LONGTEXT        DEFAULT NULL,
            redirect_url     VARCHAR(500)    DEFAULT NULL,
            is_active        TINYINT(1)      NOT NULL DEFAULT 1,
            show_progress    TINYINT(1)      NOT NULL DEFAULT 1,
            multi_step       TINYINT(1)      NOT NULL DEFAULT 1,
            notify_email     VARCHAR(255)    DEFAULT NULL,
            created_at       DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) {$c};" );

        // 2. Felter
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_form_fields (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id      BIGINT UNSIGNED NOT NULL,
            sort_order   SMALLINT        NOT NULL DEFAULT 0,
            section_name VARCHAR(100)    DEFAULT NULL,
            field_type   VARCHAR(30)     NOT NULL DEFAULT 'text',
            field_key    VARCHAR(100)    NOT NULL DEFAULT '',
            label        VARCHAR(255)    NOT NULL DEFAULT '',
            placeholder  VARCHAR(255)    DEFAULT NULL,
            help_text    TEXT            DEFAULT NULL,
            options      TEXT            DEFAULT NULL,
            required     TINYINT(1)      NOT NULL DEFAULT 0,
            core_map     VARCHAR(50)     DEFAULT NULL,
            conditions   TEXT            DEFAULT NULL,
            created_at   DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_form (form_id),
            KEY idx_sort (form_id, sort_order)
        ) {$c};" );

        // 3. Sessions (konverteringstracking)
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_form_sessions (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id         BIGINT UNSIGNED NOT NULL,
            session_token   VARCHAR(64)     NOT NULL,
            ip_hash         VARCHAR(64)     DEFAULT NULL,
            utm_source      VARCHAR(100)    DEFAULT NULL,
            utm_medium      VARCHAR(100)    DEFAULT NULL,
            utm_campaign    VARCHAR(100)    DEFAULT NULL,
            utm_content     VARCHAR(100)    DEFAULT NULL,
            referrer        VARCHAR(500)    DEFAULT NULL,
            current_step    TINYINT         NOT NULL DEFAULT 1,
            started_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            last_active_at  DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at    DATETIME        DEFAULT NULL,
            application_id  BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_token (session_token),
            KEY idx_form (form_id),
            KEY idx_completed (completed_at)
        ) {$c};" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        // Seed standard formular
        self::seed_default_form();
    }

    // ── Seed ─────────────────────────────────────────────────────────────────

    private static function seed_default_form(): void {
        global $wpdb;
        $ft = $wpdb->prefix . 'rzpz_crm_forms';
        $ff = $wpdb->prefix . 'rzpz_crm_form_fields';
        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ft}" ) > 0 ) return;

        $wpdb->insert( $ft, [
            'title'           => 'Standard ansøgningsformular',
            'slug'            => 'ansoegning',
            'intro_text'      => '<p>Tak for din interesse i et job hos Rezponz. Udfyld formularen herunder — det tager ca. 5 minutter.</p>',
            'success_message' => '<h3>Tak for din ansøgning! 🎉</h3><p>Vi vil vende tilbage til dig hurtigst muligt.</p>',
            'is_active'       => 1,
            'show_progress'   => 1,
            'multi_step'      => 1,
        ] );

        $form_id = $wpdb->insert_id;

        $fields = [
            [ 'section_name'=>'Dine stamdata', 'field_type'=>'section',   'label'=>'Dine stamdata',          'sort_order'=>1  ],
            [ 'field_type'=>'profile_photo',  'label'=>'Profilbillede',   'field_key'=>'profile_photo',      'sort_order'=>2  ],
            [ 'field_type'=>'text',    'label'=>'Fornavn',     'field_key'=>'first_name', 'core_map'=>'first_name', 'required'=>1, 'sort_order'=>3 ],
            [ 'field_type'=>'text',    'label'=>'Efternavn',   'field_key'=>'last_name',  'core_map'=>'last_name',  'required'=>1, 'sort_order'=>4 ],
            [ 'field_type'=>'email',   'label'=>'Email',       'field_key'=>'email',      'core_map'=>'email',      'required'=>1, 'sort_order'=>5 ],
            [ 'field_type'=>'phone',   'label'=>'Telefonnummer','field_key'=>'phone',     'core_map'=>'phone',      'required'=>1, 'sort_order'=>6 ],
            [ 'field_type'=>'text',    'label'=>'Adresse',     'field_key'=>'address',    'core_map'=>'address',    'sort_order'=>7 ],
            [ 'field_type'=>'text',    'label'=>'Postnummer',  'field_key'=>'postcode',   'sort_order'=>8 ],
            [ 'field_type'=>'text',    'label'=>'By',          'field_key'=>'city',       'core_map'=>'city',       'sort_order'=>9 ],
            [ 'field_type'=>'birthdate','label'=>'Fødselsdato','field_key'=>'birthdate',  'core_map'=>'birthdate',  'sort_order'=>10 ],
            [ 'field_type'=>'yes_no',  'label'=>'Har du mulighed for at have skiftende arbejdstider og arbejde på fuld tid, mellem 75-45 timer/ugen (dag, aften, weekend)?', 'field_key'=>'shift_work', 'required'=>1, 'sort_order'=>11 ],
            [ 'field_type'=>'yes_no',  'label'=>'Taler du flydende dansk?', 'field_key'=>'speaks_danish', 'required'=>1, 'sort_order'=>12 ],
            [ 'section_name'=>'Din ansøgning', 'field_type'=>'section', 'label'=>'Din ansøgning', 'sort_order'=>13 ],
            [ 'field_type'=>'radio',   'label'=>'Hvad er din nuværende arbejdssituation?', 'field_key'=>'job_situation',
              'options'=>wp_json_encode(['Ledig','Fuldtidsjob','I deltidsjob','Studerende']), 'sort_order'=>14 ],
            [ 'field_type'=>'textarea','label'=>'Hvad er din uddannelsesmæssige baggrund?','field_key'=>'education', 'sort_order'=>15 ],
            [ 'field_type'=>'yes_no',  'label'=>'Har du børn?', 'field_key'=>'has_children', 'sort_order'=>16 ],
            [ 'field_type'=>'yes_no',  'label'=>'Har du planlagt ferie indenfor de næste 3 måneder?', 'field_key'=>'planned_vacation', 'sort_order'=>17 ],
            [ 'field_type'=>'date',    'label'=>'Hvornår kan du starte i jobbet?', 'field_key'=>'availability', 'sort_order'=>18 ],
            [ 'section_name'=>'Om din motivation og erfaring', 'field_type'=>'section', 'label'=>'Om din motivation og erfaring', 'sort_order'=>19 ],
            [ 'field_type'=>'textarea','label'=>'Hvad er din motivation for at søge netop dette job?', 'field_key'=>'motivation', 'core_map'=>'cover_letter', 'required'=>1, 'sort_order'=>20 ],
            [ 'field_type'=>'textarea','label'=>'Din øvrige erhvervserfaring', 'placeholder'=>'Beskriv hvilken virksomhed, hvilken stilling og hvilken periode du var ansat', 'field_key'=>'work_experience', 'sort_order'=>21 ],
            [ 'section_name'=>'Om dig som person', 'field_type'=>'section', 'label'=>'Om dig som person', 'sort_order'=>22 ],
            [ 'field_type'=>'textarea','label'=>'Fortæl om dine fremtidsplaner', 'field_key'=>'future_plans', 'sort_order'=>23 ],
            [ 'field_type'=>'textarea','label'=>'Beskriv dine fritidsinteresser', 'field_key'=>'hobbies', 'sort_order'=>24 ],
            [ 'field_type'=>'file',    'label'=>'Her kan du uploade filer, såsom CV, eksamensbevis, lign.', 'field_key'=>'cv_upload', 'core_map'=>'cv_url', 'sort_order'=>25 ],
            [ 'field_type'=>'select',  'label'=>'Hvor har du hørt om os?', 'field_key'=>'heard_from',
              'options'=>wp_json_encode(['Jobindex','LinkedIn','Facebook','Instagram','TikTok','Snapchat','En ven','Andet']), 'sort_order'=>26 ],
        ];

        foreach ( $fields as $f ) {
            $wpdb->insert( $ff, array_merge( [
                'form_id'    => $form_id,
                'field_key'  => '',
                'label'      => '',
                'required'   => 0,
                'sort_order' => 0,
            ], $f ) );
        }
    }

    // ── Forms CRUD ────────────────────────────────────────────────────────────

    public static function get_forms(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT f.*, COUNT(s.id) AS total_sessions,
                    SUM(CASE WHEN s.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS total_completed
             FROM {$wpdb->prefix}rzpz_crm_forms f
             LEFT JOIN {$wpdb->prefix}rzpz_crm_form_sessions s ON f.id = s.form_id
             GROUP BY f.id ORDER BY f.created_at DESC"
        ) ?: [];
    }

    public static function get_form( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crm_forms WHERE id = %d", $id
        ) ) ?: null;
    }

    public static function get_form_by_slug( string $slug ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crm_forms WHERE slug = %s AND is_active = 1", $slug
        ) ) ?: null;
    }

    public static function upsert_form( array $data ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_forms';

        $slug = sanitize_title( $data['slug'] ?? $data['title'] ?? '' );
        $row = [
            'title'           => sanitize_text_field( $data['title'] ?? '' ),
            'slug'            => $slug,
            'position_id'     => ! empty( $data['position_id'] ) ? (int) $data['position_id'] : null,
            'intro_text'      => wp_kses_post( $data['intro_text'] ?? '' ),
            'success_message' => wp_kses_post( $data['success_message'] ?? '' ),
            'redirect_url'    => esc_url_raw( $data['redirect_url'] ?? '' ),
            'is_active'       => ! empty( $data['is_active'] ) ? 1 : 0,
            'show_progress'   => ! empty( $data['show_progress'] ) ? 1 : 0,
            'multi_step'      => ! empty( $data['multi_step'] ) ? 1 : 0,
            'notify_email'    => sanitize_email( $data['notify_email'] ?? '' ),
        ];
        if ( empty( $row['title'] ) ) return false;

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $t, $row, [ 'id' => (int) $data['id'] ] );
            return (int) $data['id'];
        }
        $wpdb->insert( $t, $row );
        return $wpdb->insert_id ?: false;
    }

    public static function delete_form( int $id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'rzpz_crm_form_fields',  [ 'form_id' => $id ] );
        $wpdb->delete( $wpdb->prefix . 'rzpz_crm_form_sessions', [ 'form_id' => $id ] );
        $wpdb->delete( $wpdb->prefix . 'rzpz_crm_forms',        [ 'id'      => $id ] );
    }

    public static function duplicate_form( int $id, object $form ): int|false {
        global $wpdb;
        $tf = $wpdb->prefix . 'rzpz_crm_forms';
        $tff = $wpdb->prefix . 'rzpz_crm_form_fields';

        // Generate unique slug (max 20 iterations to prevent infinite loop)
        $base_slug = rtrim( $form->slug, '-' );
        $new_slug  = $base_slug . '-kopi';
        $i = 2;
        while ( $i <= 20 && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tf} WHERE slug = %s", $new_slug ) ) ) {
            $new_slug = $base_slug . '-kopi-' . $i++;
        }
        // Final fallback: append timestamp if still taken
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tf} WHERE slug = %s", $new_slug ) ) ) {
            $new_slug = $base_slug . '-kopi-' . time();
        }

        $ok = $wpdb->insert( $tf, [
            'title'          => $form->title . ' (kopi)',
            'slug'           => $new_slug,
            'is_active'      => 0,   // start as draft
            'show_progress'  => $form->show_progress,
            'multi_step'     => $form->multi_step,
            'intro_text'     => $form->intro_text,
            'success_message'=> $form->success_message,
            'redirect_url'   => $form->redirect_url,
            'notify_email'   => $form->notify_email,
            'position_id'    => $form->position_id,
        ] );
        if ( ! $ok ) return false;

        $new_id = $wpdb->insert_id;

        // Copy all fields
        $fields = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tff} WHERE form_id = %d ORDER BY sort_order ASC", $id
        ) );
        foreach ( $fields as $f ) {
            $wpdb->insert( $tff, [
                'form_id'    => $new_id,
                'sort_order' => $f->sort_order,
                'section_name'=> $f->section_name,
                'field_type' => $f->field_type,
                'field_key'  => $f->field_key,
                'label'      => $f->label,
                'placeholder'=> $f->placeholder,
                'help_text'  => $f->help_text,
                'options'    => $f->options,
                'required'   => $f->required,
                'core_map'   => $f->core_map,
                'conditions' => $f->conditions,
            ] );
        }

        return $new_id;
    }

    // ── Fields CRUD ──────────────────────────────────────────────────────────

    public static function get_fields( int $form_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crm_form_fields WHERE form_id = %d ORDER BY sort_order ASC",
            $form_id
        ) ) ?: [];
    }

    public static function save_fields( int $form_id, array $fields ): void {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_form_fields';
        $wpdb->delete( $t, [ 'form_id' => $form_id ] );

        foreach ( $fields as $i => $f ) {
            $field_type = in_array( $f['field_type'] ?? '', array_keys( self::FIELD_TYPES ), true )
                ? $f['field_type'] : 'text';

            $options = null;
            if ( ! empty( $f['options'] ) ) {
                $opts = is_array( $f['options'] ) ? $f['options'] : json_decode( $f['options'], true );
                $options = wp_json_encode( array_map( 'sanitize_text_field', (array) $opts ) );
            }

            $core_map = ! empty( $f['core_map'] ) && array_key_exists( $f['core_map'], self::CORE_FIELD_MAP )
                ? $f['core_map'] : null;

            $wpdb->insert( $t, [
                'form_id'      => $form_id,
                'sort_order'   => $i,
                'section_name' => sanitize_text_field( $f['section_name'] ?? '' ) ?: null,
                'field_type'   => $field_type,
                'field_key'    => sanitize_key( $f['field_key'] ?? 'field_' . $i ),
                'label'        => sanitize_text_field( $f['label'] ?? '' ),
                'placeholder'  => sanitize_text_field( $f['placeholder'] ?? '' ) ?: null,
                'help_text'    => sanitize_textarea_field( $f['help_text'] ?? '' ) ?: null,
                'options'      => $options,
                'required'     => ! empty( $f['required'] ) ? 1 : 0,
                'core_map'     => $core_map,
                'conditions'   => ! empty( $f['conditions'] ) ? wp_json_encode( $f['conditions'] ) : null,
            ] );
        }
    }

    // ── Sessions (konverteringstracking) ─────────────────────────────────────

    public static function create_session( int $form_id, array $utm = [] ): string {
        global $wpdb;
        $token = bin2hex( random_bytes( 24 ) );
        $wpdb->insert( $wpdb->prefix . 'rzpz_crm_form_sessions', [
            'form_id'       => $form_id,
            'session_token' => $token,
            'ip_hash'       => hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' ),
            'utm_source'    => sanitize_text_field( $utm['utm_source']   ?? '' ) ?: null,
            'utm_medium'    => sanitize_text_field( $utm['utm_medium']   ?? '' ) ?: null,
            'utm_campaign'  => sanitize_text_field( $utm['utm_campaign'] ?? '' ) ?: null,
            'utm_content'   => sanitize_text_field( $utm['utm_content']  ?? '' ) ?: null,
            'referrer'      => sanitize_url( $utm['referrer'] ?? '' ) ?: null,
            'current_step'  => 1,
        ] );
        return $token;
    }

    public static function update_session_step( string $token, int $step ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_form_sessions',
            [ 'current_step' => $step ],
            [ 'session_token' => $token ]
        );
    }

    public static function complete_session( string $token, int $application_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_form_sessions',
            [ 'completed_at' => current_time( 'mysql' ), 'application_id' => $application_id ],
            [ 'session_token' => $token ]
        );
    }

    // ── Conversion stats ─────────────────────────────────────────────────────

    public static function get_form_stats( int $form_id, int $days = 30 ): array {
        global $wpdb;
        $t      = $wpdb->prefix . 'rzpz_crm_form_sessions';
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE form_id=%d AND started_at >= %s", $form_id, $cutoff ) );
        $completed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE form_id=%d AND completed_at IS NOT NULL AND started_at >= %s", $form_id, $cutoff ) );

        // Skridt-distribution (drop-off analyse)
        $steps = $wpdb->get_results( $wpdb->prepare(
            "SELECT current_step, COUNT(*) AS cnt FROM {$t} WHERE form_id=%d AND completed_at IS NULL AND started_at >= %s GROUP BY current_step ORDER BY current_step",
            $form_id, $cutoff
        ) );

        // UTM kilde-fordeling
        $sources = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(utm_source, 'direkte') AS source, COUNT(*) AS cnt FROM {$t} WHERE form_id=%d AND started_at >= %s GROUP BY utm_source ORDER BY cnt DESC",
            $form_id, $cutoff
        ) );

        return [
            'total'           => $total,
            'completed'       => $completed,
            'conversion_rate' => $total > 0 ? round( $completed / $total * 100, 1 ) : 0,
            'step_dropoff'    => $steps,
            'utm_sources'     => $sources,
        ];
    }
}
