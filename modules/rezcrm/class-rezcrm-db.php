<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPZ_CRM_DB
 *
 * Database-lag for RezCRM modulet.
 *
 * Tabeller:
 *   rzpz_crm_positions     – stillinger / job-opslag
 *   rzpz_crm_applicants    – ansøger-profiler (GDPR-enhed)
 *   rzpz_crm_applications  – ansøgning (N:M: ansøger ↔ stilling)
 *   rzpz_crm_history       – status-historik (audit trail, 5 år)
 *   rzpz_crm_communications – emails + SMS sendt til ansøger
 *   rzpz_crm_templates     – email/SMS skabeloner
 *   rzpz_crm_scheduled     – planlagte forsinkede afslag-emails
 */
class RZPZ_CRM_DB {

    const DB_VERSION     = '2'; // bumped: added photo_url, cv_url, address, city, cover_letter, notes to applicants
    const DB_VERSION_KEY = 'rzpz_crm_db_ver';

    // Pipeline-trin i rækkefølge
    const PIPELINE_STAGES = [
        'ny'             => 'Ny',
        'screening'      => 'Screening',
        'samtale'        => 'Samtale',
        'tilbud'         => 'Tilbud',
        'ansat'          => 'Ansat',
        'job_pabegyndt'  => 'Job påbegyndt',
        'afslag'         => 'Afslag',
    ];

    // Kanalkilder
    const SOURCES = [
        'jobindex'  => 'Jobindex',
        'linkedin'  => 'LinkedIn',
        'meta'      => 'Meta / Facebook',
        'snapchat'  => 'Snapchat',
        'tiktok'    => 'TikTok',
        'website'   => 'Hjemmeside',
        'referral'  => 'Henvis en ven',
        'other'     => 'Andet',
    ];

    const COMM_TYPES = [ 'email', 'sms' ];

    // ── Install ──────────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Stillinger
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_positions (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title          VARCHAR(255)    NOT NULL,
            department     VARCHAR(100)    DEFAULT NULL,
            location       VARCHAR(100)    DEFAULT NULL,
            description    LONGTEXT        DEFAULT NULL,
            status         VARCHAR(20)     NOT NULL DEFAULT 'open',
            source_url     VARCHAR(500)    DEFAULT NULL,
            created_at     DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status)
        ) {$c};" );

        // 2. Ansøger-profiler (GDPR-enhed — slettes 6 måneder efter afslutning)
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_applicants (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name       VARCHAR(100)    NOT NULL,
            last_name        VARCHAR(100)    NOT NULL,
            email            VARCHAR(255)    NOT NULL,
            phone            VARCHAR(50)     DEFAULT NULL,
            address          VARCHAR(255)    DEFAULT NULL,
            city             VARCHAR(100)    DEFAULT NULL,
            birthdate        DATE            DEFAULT NULL,
            photo_url        VARCHAR(500)    DEFAULT NULL,
            cv_url           VARCHAR(500)    DEFAULT NULL,
            cover_letter     LONGTEXT        DEFAULT NULL,
            notes            TEXT            DEFAULT NULL,
            gdpr_consent     TINYINT(1)      NOT NULL DEFAULT 0,
            gdpr_consent_at  DATETIME        DEFAULT NULL,
            delete_after     DATETIME        DEFAULT NULL,
            token            VARCHAR(64)     NOT NULL DEFAULT '',
            created_at       DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_email (email),
            KEY idx_token (token),
            KEY idx_delete_after (delete_after)
        ) {$c};" );

        // 3. Ansøgninger (ansøger ↔ stilling med pipeline-status)
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_applications (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            applicant_id    BIGINT UNSIGNED NOT NULL,
            position_id     BIGINT UNSIGNED NOT NULL,
            stage           VARCHAR(20)     NOT NULL DEFAULT 'ny',
            source          VARCHAR(30)     NOT NULL DEFAULT 'other',
            rating          TINYINT         DEFAULT NULL,
            salary_request  INT UNSIGNED    DEFAULT NULL,
            availability    DATE            DEFAULT NULL,
            rubix_synced    TINYINT(1)      NOT NULL DEFAULT 0,
            rubix_employee_id VARCHAR(50)   DEFAULT NULL,
            aon_invitation_id   VARCHAR(255)    DEFAULT NULL,
            aon_invitation_url  TEXT            DEFAULT NULL,
            aon_status          VARCHAR(30)     DEFAULT NULL,
            aon_completed_at    DATETIME        DEFAULT NULL,
            aon_result_json     LONGTEXT        DEFAULT NULL,
            rejection_scheduled_at DATETIME DEFAULT NULL,
            rejection_sent_at      DATETIME DEFAULT NULL,
            ended_at        DATETIME        DEFAULT NULL,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_stage (stage),
            KEY idx_position (position_id),
            KEY idx_applicant (applicant_id),
            KEY idx_rejection (rejection_scheduled_at)
        ) {$c};" );

        // 4. Status-historik (audit trail — bevares 5 år jf. GDPR artikel 5)
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_history (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id BIGINT UNSIGNED NOT NULL,
            from_stage     VARCHAR(20)     DEFAULT NULL,
            to_stage       VARCHAR(20)     NOT NULL,
            changed_by     BIGINT UNSIGNED DEFAULT NULL,
            note           TEXT            DEFAULT NULL,
            created_at     DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_application (application_id),
            KEY idx_created (created_at)
        ) {$c};" );

        // 5. Kommunikationslog (emails + SMS)
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_communications (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id BIGINT UNSIGNED NOT NULL,
            type           VARCHAR(10)     NOT NULL DEFAULT 'email',
            subject        VARCHAR(255)    DEFAULT NULL,
            body           LONGTEXT        NOT NULL,
            status         VARCHAR(20)     NOT NULL DEFAULT 'sent',
            error_msg      TEXT            DEFAULT NULL,
            sent_at        DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_application (application_id)
        ) {$c};" );

        // 6. Email/SMS skabeloner
        // NOTE: 'trigger' er et MySQL reserveret ord — brug `trigger` med backticks i raw SQL
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_templates (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(255)    NOT NULL,
            type       VARCHAR(10)     NOT NULL DEFAULT 'email',
            `trigger`  VARCHAR(50)     NOT NULL DEFAULT 'manual',
            subject    VARCHAR(255)    DEFAULT NULL,
            body       LONGTEXT        NOT NULL,
            is_default TINYINT(1)      NOT NULL DEFAULT 0,
            created_at DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_trigger (`trigger`),
            KEY idx_type (type)
        ) {$c};" );

        // 7. Planlagte forsinkede afslag
        dbDelta( "CREATE TABLE {$wpdb->prefix}rzpz_crm_scheduled (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id BIGINT UNSIGNED NOT NULL,
            template_id    BIGINT UNSIGNED DEFAULT NULL,
            send_after     DATETIME        NOT NULL,
            status         VARCHAR(20)     NOT NULL DEFAULT 'pending',
            sent_at        DATETIME        DEFAULT NULL,
            cancelled_at   DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_send_after (send_after, status)
        ) {$c};" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        // Seed standard-skabeloner
        self::seed_default_templates();
    }

    // ── Default template seed ────────────────────────────────────────────────

    private static function seed_default_templates(): void {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_templates';
        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ) > 0 ) return;

        $templates = [
            [
                'name'       => 'Bekræftelse af modtaget ansøgning',
                'type'       => 'email',
                'trigger'    => 'stage_ny',
                'subject'    => 'Vi har modtaget din ansøgning til {{position_title}}',
                'body'       => "<p>Hej {{first_name}},</p>\n<p>Tak fordi du søgte stillingen som <strong>{{position_title}}</strong> hos Rezponz. Vi har modtaget din ansøgning og glæder os til at læse den igennem.</p>\n<p>Du kan følge din ansøgningsstatus her: <a href=\"{{status_url}}\">Se din status</a></p>\n<p>Vi vil vende tilbage til dig hurtigst muligt.</p>\n<p>Med venlig hilsen<br>Rekrutteringsteamet<br>Rezponz</p>",
                'is_default' => 1,
            ],
            [
                'name'       => 'Invitation til samtale',
                'type'       => 'email',
                'trigger'    => 'stage_samtale',
                'subject'    => 'Vi vil gerne møde dig — samtale om {{position_title}}',
                'body'       => "<p>Hej {{first_name}},</p>\n<p>Vi har gennemgået din ansøgning og vil gerne invitere dig til en samtale om stillingen som <strong>{{position_title}}</strong>.</p>\n<p>Vi vender tilbage med tidspunkter for samtalen. Du er naturligvis altid velkommen til at kontakte os på rekruttering@rezponz.dk.</p>\n<p>Glæder os til at møde dig!</p>\n<p>Med venlig hilsen<br>Rekrutteringsteamet<br>Rezponz</p>",
                'is_default' => 1,
            ],
            [
                'name'       => 'Tilbud om ansættelse',
                'type'       => 'email',
                'trigger'    => 'stage_tilbud',
                'subject'    => 'Tillykke — vi vil gerne ansætte dig!',
                'body'       => "<p>Hej {{first_name}},</p>\n<p>Det er med stor glæde, at vi hermed giver dig et tilbud om ansættelse som <strong>{{position_title}}</strong> hos Rezponz.</p>\n<p>Vi vil kontakte dig snarest med de nærmere detaljer om din startdato og kontrakt.</p>\n<p>Velkommen til holdet!</p>\n<p>Med venlig hilsen<br>Rekrutteringsteamet<br>Rezponz</p>",
                'is_default' => 1,
            ],
            [
                'name'       => 'Afslag (forsinket)',
                'type'       => 'email',
                'trigger'    => 'stage_afslag',
                'subject'    => 'Vedr. din ansøgning til {{position_title}}',
                'body'       => "<p>Hej {{first_name}},</p>\n<p>Tak fordi du søgte stillingen som <strong>{{position_title}}</strong> hos Rezponz. Vi sætter stor pris på din interesse og den tid, du har lagt i din ansøgning.</p>\n<p>Vi har gennemgået alle ansøgninger grundigt, og vi har desværre valgt at gå videre med andre kandidater denne gang.</p>\n<p>Vi håber, at du vil søge hos os igen, hvis der opstår en stilling, der matcher dine ønsker. Du kan altid holde dig opdateret på vores ledige stillinger på <a href=\"https://rezponz.dk/jobs\">rezponz.dk/jobs</a>.</p>\n<p>Tak igen og held og lykke fremover.</p>\n<p>Med venlig hilsen<br>Rekrutteringsteamet<br>Rezponz</p>",
                'is_default' => 1,
            ],
            [
                'name'       => 'SMS — Invitation til samtale',
                'type'       => 'sms',
                'trigger'    => 'stage_samtale',
                'subject'    => null,
                'body'       => "Hej {{first_name}} 👋 Vi har læst din ansøgning til {{position_title}} og vil gerne møde dig! Vi sender dig snart et link med tidspunkter. / Rezponz",
                'is_default' => 1,
            ],
        ];

        foreach ( $templates as $tpl ) {
            $wpdb->insert( $t, $tpl );
        }
    }

    // ── Positions ────────────────────────────────────────────────────────────

    public static function get_positions( string $status = '' ): array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_positions';
        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY created_at DESC", $status ) ) ?: [];
        }
        return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY status ASC, created_at DESC" ) ?: [];
    }

    public static function get_position( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rzpz_crm_positions WHERE id = %d", $id ) ) ?: null;
    }

    public static function get_positions_with_stats(): array {
        global $wpdb;
        $p = $wpdb->prefix . 'rzpz_crm_positions';
        $a = $wpdb->prefix . 'rzpz_crm_applications';

        $rows = $wpdb->get_results(
            "SELECT pos.*,
                COUNT(app.id)                                                AS total_applications,
                SUM(CASE WHEN app.stage = 'ny'            THEN 1 ELSE 0 END) AS count_ny,
                SUM(CASE WHEN app.stage = 'screening'     THEN 1 ELSE 0 END) AS count_screening,
                SUM(CASE WHEN app.stage = 'samtale'       THEN 1 ELSE 0 END) AS count_samtale,
                SUM(CASE WHEN app.stage = 'tilbud'        THEN 1 ELSE 0 END) AS count_tilbud,
                SUM(CASE WHEN app.stage = 'ansat'         THEN 1 ELSE 0 END) AS count_ansat,
                SUM(CASE WHEN app.stage = 'job_pabegyndt' THEN 1 ELSE 0 END) AS count_job_pabegyndt,
                SUM(CASE WHEN app.stage = 'afslag'        THEN 1 ELSE 0 END) AS count_afslag
            FROM {$p} pos
            LEFT JOIN {$a} app ON app.position_id = pos.id
            GROUP BY pos.id
            ORDER BY pos.status ASC, pos.created_at DESC"
        ) ?: [];

        foreach ( $rows as $row ) {
            $row->total_applications  = (int) $row->total_applications;
            $row->count_ny            = (int) $row->count_ny;
            $row->count_screening     = (int) $row->count_screening;
            $row->count_samtale       = (int) $row->count_samtale;
            $row->count_tilbud        = (int) $row->count_tilbud;
            $row->count_ansat         = (int) $row->count_ansat;
            $row->count_job_pabegyndt = (int) $row->count_job_pabegyndt;
            $row->count_afslag        = (int) $row->count_afslag;
        }

        return $rows;
    }

    public static function upsert_position( array $data ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_positions';
        $row = [
            'title'       => sanitize_text_field( $data['title'] ?? '' ),
            'department'  => sanitize_text_field( $data['department'] ?? '' ),
            'location'    => sanitize_text_field( $data['location'] ?? '' ),
            'description' => wp_kses_post( $data['description'] ?? '' ),
            'status'      => in_array( $data['status'] ?? '', [ 'open', 'closed', 'draft' ], true ) ? $data['status'] : 'open',
            'source_url'  => esc_url_raw( $data['source_url'] ?? '' ),
        ];
        if ( empty( $row['title'] ) ) return false;
        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $t, $row, [ 'id' => (int) $data['id'] ] );
            return (int) $data['id'];
        }
        $wpdb->insert( $t, $row );
        return $wpdb->insert_id ?: false;
    }

    // ── Applicants ───────────────────────────────────────────────────────────

    public static function get_applicant( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rzpz_crm_applicants WHERE id = %d", $id ) ) ?: null;
    }

    public static function get_applicant_by_token( string $token ): ?object {
        global $wpdb;
        if ( empty( $token ) ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rzpz_crm_applicants WHERE token = %s", $token ) ) ?: null;
    }

    public static function upsert_applicant( array $data ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_applicants';

        $row = [
            'first_name'    => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'     => sanitize_text_field( $data['last_name']  ?? '' ),
            'email'         => sanitize_email( $data['email'] ?? '' ),
            'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
            'address'       => sanitize_text_field( $data['address'] ?? '' ),
            'city'          => sanitize_text_field( $data['city'] ?? '' ),
            'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
            'gdpr_consent'  => ! empty( $data['gdpr_consent'] ) ? 1 : 0,
            'photo_url'     => esc_url_raw( $data['photo_url'] ?? '' ) ?: null,
            'cv_url'        => esc_url_raw( $data['cv_url']    ?? '' ) ?: null,
        ];

        if ( $row['gdpr_consent'] && empty( $data['id'] ) ) {
            $row['gdpr_consent_at'] = current_time( 'mysql' );
        }

        if ( empty( $row['email'] ) || empty( $row['first_name'] ) ) return false;

        // Eksplicit ID → opdater
        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $t, $row, [ 'id' => (int) $data['id'] ] );
            return (int) $data['id'];
        }

        // Slå op på email — undgå UNIQUE-fejl og returner eksisterende ansøger
        $existing_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE email = %s LIMIT 1",
            $row['email']
        ) );

        if ( $existing_id > 0 ) {
            // Opdater eksisterende ansøger med eventuel ny info (bevar token)
            unset( $row['token'] );
            // Bevar GDPR-dato hvis allerede sat
            unset( $row['gdpr_consent_at'] );
            $wpdb->update( $t, $row, [ 'id' => $existing_id ] );
            // Opdater GDPR-dato kun hvis ny samtykke
            if ( $row['gdpr_consent'] ) {
                $wpdb->update( $t, [ 'gdpr_consent_at' => current_time( 'mysql' ) ], [ 'id' => $existing_id, 'gdpr_consent_at' => null ] );
            }
            return $existing_id;
        }

        // Ny ansøger — generer unik token til status-side
        $row['token'] = wp_generate_password( 32, false );
        $wpdb->insert( $t, $row );
        return $wpdb->insert_id ?: false;
    }

    // ── Applications ─────────────────────────────────────────────────────────

    public static function get_applications( array $filters = [] ): array {
        global $wpdb;
        $a   = $wpdb->prefix . 'rzpz_crm_applications';
        $ap  = $wpdb->prefix . 'rzpz_crm_applicants';
        $pos = $wpdb->prefix . 'rzpz_crm_positions';

        $where = [ '1=1' ];
        $args  = [];

        if ( ! empty( $filters['stage'] ) ) {
            $where[] = "a.stage = %s";
            $args[]  = $filters['stage'];
        }
        if ( ! empty( $filters['position_id'] ) ) {
            $where[] = "a.position_id = %d";
            $args[]  = (int) $filters['position_id'];
        }
        if ( ! empty( $filters['search'] ) ) {
            $where[] = "(ap.first_name LIKE %s OR ap.last_name LIKE %s OR ap.email LIKE %s)";
            $s = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $args = array_merge( $args, [ $s, $s, $s ] );
        }

        $limit  = isset( $filters['limit']  ) ? (int) $filters['limit']  : 200;
        $offset = isset( $filters['offset'] ) ? (int) $filters['offset'] : 0;

        $sql = "SELECT a.*, ap.first_name, ap.last_name, ap.email, ap.phone, ap.token,
                       COALESCE(pos.title, 'Generel ansøgning') AS position_title
                FROM {$a} a
                JOIN {$ap}      ap  ON a.applicant_id = ap.id
                LEFT JOIN {$pos} pos ON a.position_id  = pos.id AND a.position_id > 0
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY a.created_at DESC
                LIMIT %d OFFSET %d";
        $args[] = $limit;
        $args[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) ?: [];
    }

    /** Hent én ansøgning med fulde joins */
    public static function get_application( int $id ): ?object {
        global $wpdb;
        $a   = $wpdb->prefix . 'rzpz_crm_applications';
        $ap  = $wpdb->prefix . 'rzpz_crm_applicants';
        $pos = $wpdb->prefix . 'rzpz_crm_positions';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, ap.first_name, ap.last_name, ap.email, ap.phone, ap.address, ap.city,
                    ap.cover_letter, ap.photo_url, ap.cv_url, ap.notes, ap.token,
                    COALESCE(pos.title, 'Generel ansøgning') AS position_title,
                    COALESCE(pos.department, '')              AS department
             FROM {$a} a
             JOIN {$ap}      ap  ON a.applicant_id = ap.id
             LEFT JOIN {$pos} pos ON a.position_id  = pos.id AND a.position_id > 0
             WHERE a.id = %d",
            $id
        ) ) ?: null;
    }

    public static function insert_application( int $applicant_id, int $position_id, string $source = 'other' ): int|false {
        global $wpdb;
        // Forhindre dubletter (samme ansøger til samme specifikke stilling)
        // position_id = 0 = generel ansøgning — tillad flere fra samme person
        if ( $position_id > 0 ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rzpz_crm_applications
                 WHERE applicant_id = %d AND position_id = %d LIMIT 1",
                $applicant_id, $position_id
            ) );
            if ( $existing ) return (int) $existing; // returnér eksisterende
        }

        $wpdb->insert( $wpdb->prefix . 'rzpz_crm_applications', [
            'applicant_id' => $applicant_id,
            'position_id'  => $position_id,
            'source'       => in_array( $source, array_keys( self::SOURCES ), true ) ? $source : 'other',
            'stage'        => 'ny',
        ] );
        return $wpdb->insert_id ?: false;
    }

    /**
     * Flyt ansøgning til ny stage — gem historik + håndter afslag-timing.
     */
    public static function move_stage( int $application_id, string $new_stage, int $user_id = 0, string $note = '' ): bool {
        global $wpdb;
        $a = $wpdb->prefix . 'rzpz_crm_applications';

        $app = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$a} WHERE id = %d", $application_id ) );
        if ( ! $app ) return false;
        if ( ! array_key_exists( $new_stage, self::PIPELINE_STAGES ) ) return false;

        $extra = [ 'stage' => $new_stage, 'updated_at' => current_time( 'mysql' ) ];

        // Gem ended_at for alle afsluttede stages
        if ( in_array( $new_stage, [ 'ansat', 'job_pabegyndt', 'afslag' ], true ) ) {
            $extra['ended_at'] = current_time( 'mysql' );
        }

        // GDPR: sæt delete_after KUN ved afslag (ikke ansat — aktive medarbejdere slettes ikke)
        if ( $new_stage === 'afslag' ) {
            $delete_after = gmdate( 'Y-m-d H:i:s', strtotime( '+6 months' ) );
            $wpdb->update( $wpdb->prefix . 'rzpz_crm_applicants',
                [ 'delete_after' => $delete_after ],
                [ 'id' => (int) $app->applicant_id ]
            );
        }

        // Afslag → planlæg forsinket email (3-5 dage tilfældig forsinkelse)
        if ( $new_stage === 'afslag' ) {
            $delay_days = rand( 3, 5 );
            $send_after = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay_days} days" ) );
            $extra['rejection_scheduled_at'] = $send_after;

            // Tjek om der allerede er en pending/processing-scheduled rejection
            $already = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rzpz_crm_scheduled
                 WHERE application_id = %d AND status IN ('pending','processing') LIMIT 1",
                $application_id
            ) );

            if ( ! $already ) {
                $tpl = $wpdb->get_row( "SELECT id FROM {$wpdb->prefix}rzpz_crm_templates WHERE `trigger` = 'stage_afslag' AND is_default = 1 LIMIT 1" );
                $wpdb->insert( $wpdb->prefix . 'rzpz_crm_scheduled', [
                    'application_id' => $application_id,
                    'template_id'    => $tpl ? (int) $tpl->id : null,
                    'send_after'     => $send_after,
                    'status'         => 'pending',
                ] );
            }
        }

        $wpdb->update( $a, $extra, [ 'id' => $application_id ] );

        // Gem i historik
        $wpdb->insert( $wpdb->prefix . 'rzpz_crm_history', [
            'application_id' => $application_id,
            'from_stage'     => $app->stage,
            'to_stage'       => $new_stage,
            'changed_by'     => $user_id ?: null,
            'note'           => sanitize_textarea_field( $note ),
        ] );

        return true;
    }

    public static function update_application( int $id, array $data ): bool {
        global $wpdb;
        $row = [];

        if ( array_key_exists( 'rating', $data ) ) {
            $rating = (int) $data['rating'];
            if ( $rating >= 0 && $rating <= 5 ) $row['rating'] = $rating;
        }
        if ( array_key_exists( 'salary_request', $data ) ) {
            $row['salary_request'] = max( 0, (int) $data['salary_request'] );
        }
        if ( array_key_exists( 'availability', $data ) ) {
            $av = sanitize_text_field( $data['availability'] );
            $row['availability'] = $av ? date( 'Y-m-d', strtotime( $av ) ) : null;
        }
        if ( array_key_exists( 'source', $data ) ) {
            $src = sanitize_key( $data['source'] );
            $row['source'] = array_key_exists( $src, self::SOURCES ) ? $src : 'other';
        }

        if ( empty( $row ) ) return false;
        return (bool) $wpdb->update( $wpdb->prefix . 'rzpz_crm_applications', $row, [ 'id' => $id ] );
    }

    /** Opdater foto og CV URL for ansøgeren bag en ansøgning */
    public static function update_applicant_attachments( int $application_id, string $photo_url, string $cv_url ): bool {
        global $wpdb;
        $a  = $wpdb->prefix . 'rzpz_crm_applications';
        $ap = $wpdb->prefix . 'rzpz_crm_applicants';
        $applicant_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT applicant_id FROM {$a} WHERE id = %d", $application_id
        ) );
        if ( ! $applicant_id ) return false;
        return false !== $wpdb->update( $ap, [
            'photo_url' => esc_url_raw( $photo_url ) ?: null,
            'cv_url'    => esc_url_raw( $cv_url )    ?: null,
        ], [ 'id' => $applicant_id ] );
    }

    // ── History ──────────────────────────────────────────────────────────────

    public static function get_history( int $application_id ): array {
        global $wpdb;
        $h = $wpdb->prefix . 'rzpz_crm_history';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT h.*, u.display_name FROM {$h} h
             LEFT JOIN {$wpdb->users} u ON h.changed_by = u.ID
             WHERE h.application_id = %d ORDER BY h.created_at ASC",
            $application_id
        ) ) ?: [];
    }

    // ── Communications ───────────────────────────────────────────────────────

    public static function log_communication( int $application_id, string $type, string $body, string $subject = '', string $status = 'sent', string $error = '' ): void {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'rzpz_crm_communications', [
            'application_id' => $application_id,
            'type'           => in_array( $type, self::COMM_TYPES, true ) ? $type : 'email',
            'subject'        => sanitize_text_field( $subject ),
            'body'           => $body,
            'status'         => $status,
            'error_msg'      => $error,
        ] );
    }

    public static function get_communications( int $application_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rzpz_crm_communications WHERE application_id = %d ORDER BY sent_at DESC",
            $application_id
        ) ) ?: [];
    }

    // ── Templates ────────────────────────────────────────────────────────────

    public static function get_templates( string $type = '' ): array {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_templates';
        if ( $type && in_array( $type, self::COMM_TYPES, true ) ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE type = %s ORDER BY name ASC", $type ) ) ?: [];
        }
        return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY type ASC, name ASC" ) ?: [];
    }

    public static function get_template( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rzpz_crm_templates WHERE id = %d", $id ) ) ?: null;
    }

    public static function upsert_template( array $data ): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'rzpz_crm_templates';
        $row = [
            'name'       => sanitize_text_field( $data['name'] ?? '' ),
            'type'       => in_array( $data['type'] ?? '', self::COMM_TYPES, true ) ? $data['type'] : 'email',
            'trigger'    => sanitize_text_field( $data['trigger'] ?? 'manual' ),
            'subject'    => sanitize_text_field( $data['subject'] ?? '' ),
            'body'       => wp_kses_post( $data['body'] ?? '' ),
            'is_default' => ! empty( $data['is_default'] ) ? 1 : 0,
        ];
        if ( empty( $row['name'] ) || empty( $row['body'] ) ) return false;
        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $t, $row, [ 'id' => (int) $data['id'] ] );
            return (int) $data['id'];
        }
        $wpdb->insert( $t, $row );
        return $wpdb->insert_id ?: false;
    }

    // ── Scheduled rejections ─────────────────────────────────────────────────

    public static function get_due_rejections(): array {
        global $wpdb;
        $s   = $wpdb->prefix . 'rzpz_crm_scheduled';
        $now = current_time( 'mysql' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$s} WHERE status = 'pending' AND send_after <= %s ORDER BY send_after ASC LIMIT 20",
            $now
        ) ) ?: [];
    }

    public static function cancel_rejection( int $application_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_scheduled',
            [ 'status' => 'cancelled', 'cancelled_at' => current_time( 'mysql' ) ],
            [ 'application_id' => $application_id, 'status' => 'pending' ]
        );
    }

    public static function mark_rejection_sent( int $scheduled_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_scheduled',
            [ 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ],
            [ 'id' => $scheduled_id ]
        );
    }

    // ── GDPR auto-delete ─────────────────────────────────────────────────────

    /**
     * Slet ansøgere der har passeret delete_after (6 mdr. efter afslutning).
     * Bevarer historik i 5 år (gemmes separat uden PII).
     * Køres dagligt via WP Cron.
     */
    public static function gdpr_auto_delete(): int {
        global $wpdb;
        $ap   = $wpdb->prefix . 'rzpz_crm_applicants';
        $a    = $wpdb->prefix . 'rzpz_crm_applications';
        $comm = $wpdb->prefix . 'rzpz_crm_communications';
        // Brug UTC (gmdate) konsistent med delete_after kolonnens write-sti
        $now  = gmdate( 'Y-m-d H:i:s' );

        // Behandl max 100 rækker per kron-kørsel for at undgå timeout
        $due = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM {$ap} WHERE delete_after IS NOT NULL AND delete_after <= %s LIMIT 100",
            $now
        ) );

        $count = 0;
        foreach ( $due as $row ) {
            $applicant_id = (int) $row->id;

            // Anonymisér ansøger — bevarer rækken som audit trail (ingen PII)
            $wpdb->update( $ap, [
                'first_name'    => 'Slettet',
                'last_name'     => 'Bruger',
                'email'         => 'gdpr_' . $applicant_id . '_' . substr( md5( (string) $applicant_id . AUTH_KEY ), 0, 8 ) . '@deleted.local',
                'phone'         => null,
                'address'       => null,
                'city'          => null,
                'birthdate'     => null,
                'cover_letter'  => null,
                'photo_url'     => null,
            'cv_url'        => null,
                'notes'         => '[GDPR anonymiseret ' . gmdate( 'Y-m-d' ) . ']',
                'gdpr_consent'  => 0,
                'delete_after'  => null,
            ], [ 'id' => $applicant_id ] );

            // Scrub kommunikationslog — fjern PII fra email-bodies
            $app_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$a} WHERE applicant_id = %d", $applicant_id
            ) );
            foreach ( $app_ids as $app_id ) {
                $wpdb->update(
                    $comm,
                    [ 'body' => '[GDPR anonymiseret]', 'subject' => '[GDPR anonymiseret]' ],
                    [ 'application_id' => (int) $app_id ]
                );
            }

            $count++;
        }

        return $count;
    }

    // ── Rubix sync ───────────────────────────────────────────────────────────

    /**
     * Send ansat-ansøger til Rubix HR via webhook.
     * Returnerer true ved success.
     */
    public static function sync_to_rubix( int $application_id ): bool {
        $opts = get_option( 'rzpa_settings', [] );
        $url  = $opts['rubix_webhook_url'] ?? '';
        if ( empty( $url ) ) return false;

        $app = self::get_application( $application_id );
        if ( ! $app ) return false;

        $payload = [
            'event'        => 'employee_hired',
            'first_name'   => $app->first_name,
            'last_name'    => $app->last_name,
            'email'        => $app->email,
            'phone'        => $app->phone,
            'position'     => $app->position_title,
            'department'   => $app->department,
            'source'       => $app->source,
            'application_id' => $application_id,
            'timestamp'    => gmdate( 'c' ),
        ];

        $res = wp_remote_post( $url, [
            'body'    => wp_json_encode( $payload ),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . ( $opts['rubix_api_token'] ?? '' ),
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 400 ) {
            error_log( '[RezCRM] Rubix sync fejl for application #' . $application_id . ': ' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ) ) );
            return false;
        }

        global $wpdb;
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        $wpdb->update(
            $wpdb->prefix . 'rzpz_crm_applications',
            [
                'rubix_synced'      => 1,
                'rubix_employee_id' => sanitize_text_field( $body['employee_id'] ?? '' ),
            ],
            [ 'id' => $application_id ]
        );

        return true;
    }

    // ── Stats (KPI dashboard) ────────────────────────────────────────────────

    public static function get_pipeline_counts(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT stage, COUNT(*) AS cnt FROM {$wpdb->prefix}rzpz_crm_applications GROUP BY stage"
        );
        $counts = array_fill_keys( array_keys( self::PIPELINE_STAGES ), 0 );
        foreach ( $rows as $row ) {
            $counts[ $row->stage ] = (int) $row->cnt;
        }
        return $counts;
    }

    public static function get_source_stats( int $days = 30 ): array {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT source, COUNT(*) AS cnt FROM {$wpdb->prefix}rzpz_crm_applications
             WHERE created_at >= %s GROUP BY source ORDER BY cnt DESC",
            $cutoff
        ) ) ?: [];
    }

    public static function get_conversion_rate( int $days = 30 ): array {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        // Tæl alle ansøgninger oprettet i perioden
        $total  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crm_applications WHERE created_at >= %s", $cutoff
        ) );
        // Tæl ansatte afsluttet i perioden (brug ended_at ikke created_at)
        $hired  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rzpz_crm_applications
             WHERE stage = 'ansat' AND ended_at >= %s AND ended_at IS NOT NULL", $cutoff
        ) );
        return [
            'total'           => $total,
            'hired'           => $hired,
            'conversion_rate' => $total > 0 ? round( $hired / $total * 100, 1 ) : 0,
        ];
    }
}
