<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_Quiz_DB {

    const DB_VERSION_KEY = 'rzpa_quiz_db_ver';
    const DB_VERSION     = '1';

    // ── Install / upgrade ──────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $pt  = $wpdb->prefix . 'rzpa_quiz_profiles';
        $qt  = $wpdb->prefix . 'rzpa_quiz_questions';
        $at  = $wpdb->prefix . 'rzpa_quiz_answers';
        $st  = $wpdb->prefix . 'rzpa_quiz_submissions';

        dbDelta( "CREATE TABLE {$pt} (
            id           TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug         VARCHAR(60) NOT NULL,
            title        VARCHAR(200) NOT NULL,
            description  TEXT,
            icon_emoji   VARCHAR(20) DEFAULT '⭐',
            color        VARCHAR(20) DEFAULT '#f97316',
            strengths    LONGTEXT,
            thrives_with LONGTEXT,
            develop_areas LONGTEXT,
            sort_order   TINYINT DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $c;" );

        dbDelta( "CREATE TABLE {$qt} (
            id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_text TEXT NOT NULL,
            helper_text   TEXT,
            sort_order    TINYINT DEFAULT 0,
            is_active     TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id)
        ) $c;" );

        dbDelta( "CREATE TABLE {$at} (
            id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id   SMALLINT UNSIGNED NOT NULL,
            answer_text   TEXT NOT NULL,
            feedback_text TEXT,
            tagline       VARCHAR(255),
            sort_order    TINYINT DEFAULT 0,
            weights       LONGTEXT,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) $c;" );

        dbDelta( "CREATE TABLE {$st} (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name                 VARCHAR(255) NOT NULL,
            phone                VARCHAR(50),
            email                VARCHAR(255),
            winning_profile_id   TINYINT UNSIGNED,
            secondary_profile_id TINYINT UNSIGNED,
            scores               LONGTEXT,
            answers_data         LONGTEXT,
            consent              TINYINT(1) DEFAULT 0,
            withdraw_token       VARCHAR(64),
            ip_address           VARCHAR(45),
            created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY withdraw_token (withdraw_token(20)),
            KEY winning_profile_id (winning_profile_id)
        ) $c;" );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        // Seed only if profiles table is empty
        if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt}" ) ) {
            self::seed();
        }
    }

    // ── Seed default data ──────────────────────────────────────────────────────

    private static function seed(): void {
        global $wpdb;
        $pt = $wpdb->prefix . 'rzpa_quiz_profiles';
        $qt = $wpdb->prefix . 'rzpa_quiz_questions';
        $at = $wpdb->prefix . 'rzpa_quiz_answers';

        // ── Profiles ──────────────────────────────────────────────────────────
        $profiles = [
            [
                'slug'         => 'empatisk',
                'title'        => 'Den Empatiske Lytter',
                'description'  => 'Du er den, folk søger, når de har brug for at blive forstået. Din evne til at lytte og skabe ægte kontakt er din superpower som Customer Success Advisor.',
                'icon_emoji'   => '💛',
                'color'        => '#ec4899',
                'strengths'    => wp_json_encode(['Stærk lytteevne', 'Skaber tillid og tryghed', 'Empatisk kommunikation', 'Forstår de uskrevne behov', 'Holder folk i hånden']),
                'thrives_with' => wp_json_encode(['Dybe kundedialoge', 'Problemløsning med mennesker', 'Psykologisk trygge teams', 'Langvarige kunderelationer']),
                'develop_areas'=> wp_json_encode(['Assertivitet og tydelighed', 'Sige nej når det er nødvendigt', 'Drive samtalen fremad', 'Stå ved egne behov']),
                'sort_order'   => 1,
            ],
            [
                'slug'         => 'energisk',
                'title'        => 'Energibomben',
                'description'  => 'Du er drivkraften i rummet. Din entusiasme er smitsom, og du når mål andre bare drømmer om. Kunder køber din energi – og det er en sjælden gave.',
                'icon_emoji'   => '⚡',
                'color'        => '#f97316',
                'strengths'    => wp_json_encode(['Smitsom entusiasme', 'Drives af mål og resultater', 'Hurtig til handling', 'Motiverer de andre', 'Lukker aftaler']),
                'thrives_with' => wp_json_encode(['Klare mål og KPI\'er', 'Frihed til at agere selvstændigt', 'Konkurrencepræget og højt tempo', 'Synlige resultater']),
                'develop_areas'=> wp_json_encode(['Tålmodighed i processen', 'Lytte mere end du taler', 'Langsigtet planlægning', 'Fordybe dig i detaljer']),
                'sort_order'   => 2,
            ],
            [
                'slug'         => 'analytisk',
                'title'        => 'Problemknuseren',
                'description'  => 'Du ser mønstre ingen andre opdager. Din strukturerede tilgang og evne til at finde den smarteste løsning gør dig uundværlig når det virkelig gælder.',
                'icon_emoji'   => '🧩',
                'color'        => '#8b5cf6',
                'strengths'    => wp_json_encode(['Analytisk tilgang til udfordringer', 'Finder mønstre og sammenhænge', 'Struktureret problemløsning', 'Evidensbaseret kommunikation', 'Strategisk tænkning']),
                'thrives_with' => wp_json_encode(['Komplekse udfordringer', 'Data og indsigter', 'Systemer og processer', 'Klare frameworks og metoder']),
                'develop_areas'=> wp_json_encode(['Emotionel forbindelse med kunder', 'Kommunikere simpelt og direkte', 'Fleksibilitet og improvisation', 'Handling frem for analyse']),
                'sort_order'   => 3,
            ],
            [
                'slug'         => 'social',
                'title'        => 'Netværksmesteren',
                'description'  => 'Du er limet der holder folk sammen. Din naturlige evne til at skabe relationer og forbinde mennesker giver dig et netværk ingen kan konkurrere med.',
                'icon_emoji'   => '🌐',
                'color'        => '#10b981',
                'strengths'    => wp_json_encode(['Bygger relationer naturligt og hurtigt', 'Forbinder de rette mennesker', 'Skaber fællesskab og tilhørighed', 'Sælger gennem relationer', 'Husker alle og deres historier']),
                'thrives_with' => wp_json_encode(['Sociale events og netværk', 'Tværfagligt samarbejde', 'Dynamiske og skiftende miljøer', 'At repræsentere virksomheden udadtil']),
                'develop_areas'=> wp_json_encode(['Dybde frem for bredde i relationer', 'Fokus og prioritering', 'Gennemføre opgaver selvstændigt', 'Sige nej til nye ting']),
                'sort_order'   => 4,
            ],
        ];

        foreach ( $profiles as $p ) {
            $wpdb->insert( $pt, $p );
        }

        // ── Questions + Answers ───────────────────────────────────────────────
        // Weights use profile slugs as keys
        $questions_data = [
            [
                'q'    => 'Hvad er din første reaktion, når du møder et nyt menneske?',
                'help' => null,
                'answers' => [
                    ['text' => 'Jeg lytter nøje og prøver at forstå hvem de egentlig er', 'fb' => 'Du sætter folk i centrum – det skaber tillid med det samme 💛', 'tag' => 'Lytteren er i dig', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>1,'social'=>1]],
                    ['text' => 'Jeg er direkte og energisk – jeg griber samtalen med begge hænder', 'fb' => 'Din energi er smitsom og trækker folk mod dig ⚡', 'tag' => 'Energien taler', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>0,'social'=>1]],
                    ['text' => 'Jeg prøver at forstå deres behov og situation', 'fb' => 'Du analyserer situationen – du er allerede to skridt foran 🧩', 'tag' => 'Strategen vågner', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>3,'social'=>0]],
                    ['text' => 'Jeg sørger for god stemning og at alle føler sig velkomne', 'fb' => 'Du skaber magien – du er limet der holder folk sammen 🌐', 'tag' => 'Netværket starter her', 'w' => ['empatisk'=>1,'energisk'=>1,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Hvad motiverer dig allermest på arbejdet?',
                'help' => null,
                'answers' => [
                    ['text' => 'At vide at jeg har gjort en reel forskel for et andet menneske', 'fb' => 'Det er her du finder din dybeste motivation 💛', 'tag' => 'Formål over profit', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>0,'social'=>1]],
                    ['text' => 'At nå mine mål og overgå det alle forventede', 'fb' => 'Den drivkraft er guld – du leverer altid 🔥', 'tag' => 'Resultatmaskinen', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'At knække en kompleks udfordring som ingen andre kan', 'fb' => 'Du elsker at finde løsningen – det er din zone 🧠', 'tag' => 'Problemknuseren slår til', 'w' => ['empatisk'=>0,'energisk'=>1,'analytisk'=>3,'social'=>0]],
                    ['text' => 'At se folk blomstre pga. de forbindelser jeg skaber', 'fb' => 'Du bygger broer der holder – for altid 🌐', 'tag' => 'Forbindelsernes kraft', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'En kunde er frustreret og ringer til dig. Hvad gør du?',
                'help' => 'Tænk på din naturlige reaktion – ikke hvad du "burde" gøre',
                'answers' => [
                    ['text' => 'Jeg lytter fuldt ud og sørger for at de føler sig hørt, inden jeg handler', 'fb' => 'Den ro du udstråler smitter – kunden stoler på dig 💛', 'tag' => 'Tillid under pres', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>1,'social'=>0]],
                    ['text' => 'Jeg handler hurtigt og finder en løsning nu og her', 'fb' => 'Din handlekraft er præcis det kunden har brug for ⚡', 'tag' => 'Handling > ord', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'Jeg stiller spørgsmål, analyserer situationen og præsenterer en struktureret løsning', 'fb' => 'Din præcision giver kunden tro på at det nok skal gå 🧩', 'tag' => 'Systematisk redning', 'w' => ['empatisk'=>0,'energisk'=>0,'analytisk'=>3,'social'=>1]],
                    ['text' => 'Jeg bringer de rette kolleger ind og koordinerer en løsning i fællesskab', 'fb' => 'Du udnytter dit netværk – det er en kæmpe styrke 🌐', 'tag' => 'Teamplayer mode ON', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Dine venner vil beskrive dig som...',
                'help' => null,
                'answers' => [
                    ['text' => '"Den der altid lytter og aldrig dømmer"', 'fb' => 'Den gave er sjælden – og uendelig værdifuld 💛', 'tag' => 'Safe haven', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>0,'social'=>1]],
                    ['text' => '"Den mest energiske og drivende i gruppen"', 'fb' => 'Du trækker alle med dig – det er dit brand ⚡', 'tag' => 'Energicentrum', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>0,'social'=>1]],
                    ['text' => '"Den der altid har et smart svar og finder løsninger"', 'fb' => 'Den der altid har en plan – alle elsker dig for det 🧩', 'tag' => 'Go-to person', 'w' => ['empatisk'=>0,'energisk'=>1,'analytisk'=>3,'social'=>0]],
                    ['text' => '"Den der kender alle og forbinder folk med hinanden"', 'fb' => 'Dit netværk er dit superduperkraft 🌐', 'tag' => 'The connector', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Hvad er din naturlige kommunikationsstil?',
                'help' => null,
                'answers' => [
                    ['text' => 'Varm og åben – folk føler sig altid hørt og forstået', 'fb' => 'Den autentiske kommunikation er din superpower 💛', 'tag' => 'Hjertet taler', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>0,'social'=>1]],
                    ['text' => 'Direkte og overbevisende – fuld af energi og drive', 'fb' => 'Du ejer rummet med din tilstedeværelse ⚡', 'tag' => 'Rumejeren', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'Præcis og faktabaseret – folk kan stole på hvad du siger', 'fb' => 'Din troværdighed er uovertruffen 🧩', 'tag' => 'Præcisionens mester', 'w' => ['empatisk'=>0,'energisk'=>0,'analytisk'=>3,'social'=>1]],
                    ['text' => 'Charmerende og social – du får alle til at åbne sig', 'fb' => 'Den sociale magi er en ægte gave 🌐', 'tag' => 'Social magi', 'w' => ['empatisk'=>1,'energisk'=>1,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Hvad giver dig mest energi i løbet af en arbejdsdag?',
                'help' => null,
                'answers' => [
                    ['text' => 'En dyb one-on-one samtale der virkelig gør en forskel', 'fb' => 'Den forbindelsen er det der minder dig om hvorfor du gør det 💛', 'tag' => 'Deep connection', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>0,'social'=>1]],
                    ['text' => 'At præstere, se resultater og krydse ting af på listen', 'fb' => 'Den tilfredsstillelse er uovertruffen – du er en maskine ⚡', 'tag' => 'Achievement unlocked', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'At løse en kompleks udfordring på en elegant måde', 'fb' => 'Den følelse af at have knækket koden – ja, den er ægte 🧩', 'tag' => 'Eureka moment', 'w' => ['empatisk'=>0,'energisk'=>1,'analytisk'=>3,'social'=>0]],
                    ['text' => 'At være omgivet af engagerede mennesker og god energi', 'fb' => 'Du lader op af andres energi – og du giver den videre 🌐', 'tag' => 'Energi-feedback loop', 'w' => ['empatisk'=>1,'energisk'=>1,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'I et nyt job – hvad er du hurtigst til at lære?',
                'help' => null,
                'answers' => [
                    ['text' => 'Kulturen, menneskene og de uformelle spilleregler', 'fb' => 'Du aflæser rummet hurtigere end alle andre 💛', 'tag' => 'Kulturkoden knækket', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>1,'social'=>1]],
                    ['text' => 'Hvad der skal til for at levere resultater – og gøre det', 'fb' => 'Du er klar til at køre fra dag 1 ⚡', 'tag' => 'Klar fra dag 1', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'Systemerne, processerne og "hvad der virker og ikke virker"', 'fb' => 'Du bygger dit mentale landkort hurtigt og grundigt 🧩', 'tag' => 'System-master', 'w' => ['empatisk'=>0,'energisk'=>0,'analytisk'=>3,'social'=>1]],
                    ['text' => 'Navnene og historierne på alle de vigtige mennesker', 'fb' => 'Du investerer i relationer – og det betaler sig altid 🌐', 'tag' => 'First name basis', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Hvad er din største styrke, når du skal sælge noget?',
                'help' => 'Tænk på hvornår du naturligt er på dit bedste',
                'answers' => [
                    ['text' => 'Jeg forstår virkelig hvad kunden har brug for – og taler til det', 'fb' => 'Det er derfor kunder vælger dig igen og igen 💛', 'tag' => 'Behovsforståelse', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>1,'social'=>1]],
                    ['text' => 'Jeg driver samtalen fremad og lukker aftalen med overbevisning', 'fb' => 'Den close-energi er det mange ønsker de havde ⚡', 'tag' => 'The closer', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>0,'social'=>1]],
                    ['text' => 'Jeg finder præcis den rigtige løsning og argumenterer for den', 'fb' => 'Dine argumenter er uangribelige – det virker 🧩', 'tag' => 'Løsningsarkitekt', 'w' => ['empatisk'=>0,'energisk'=>1,'analytisk'=>3,'social'=>0]],
                    ['text' => 'Relationen med kunden gør at salget sker naturligt', 'fb' => 'Det er det ultimative salg – ingen salgspitch nødvendig 🌐', 'tag' => 'Trust = Sales', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'En ny kollega starter – hvad er din naturlige tilgang?',
                'help' => null,
                'answers' => [
                    ['text' => 'Jeg lytter til hvor de er og tilpasser min hjælp til dem', 'fb' => 'Den skræddersyede støtte er et gave 💛', 'tag' => 'Individuel støtte', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>1,'social'=>0]],
                    ['text' => 'Jeg coacher dem med entusiasme og pusher dem til at vokse', 'fb' => 'Din energi løfter dem hurtigere end de vidste var muligt ⚡', 'tag' => 'Accelerator', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>0,'social'=>1]],
                    ['text' => 'Jeg giver dem en klar plan og struktur så de ikke er i tvivl', 'fb' => 'Den klarhed er guld for en der er ny og usikker 🧩', 'tag' => 'Klarhedens gave', 'w' => ['empatisk'=>0,'energisk'=>0,'analytisk'=>3,'social'=>1]],
                    ['text' => 'Jeg introducerer dem til alle og sørger for de føler sig inkluderet', 'fb' => 'Den velkomst husker de resten af deres karriere 🌐', 'tag' => 'Welcome committee', 'w' => ['empatisk'=>1,'energisk'=>1,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Du har en dårlig dag på arbejdet. Hvad gør du?',
                'help' => null,
                'answers' => [
                    ['text' => 'Jeg finder nogen jeg stoler på og taler om hvad der skete', 'fb' => 'At søge forbindelsen i svære stunder – det er mod 💛', 'tag' => 'Sårbarhed er styrke', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>0,'social'=>1]],
                    ['text' => 'Jeg ryster det af mig og kæmper mig igennem med ny energi', 'fb' => 'Den resiliens er noget de fleste misunder dig ⚡', 'tag' => 'Bounce back king/queen', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'Jeg analyserer hvad der gik galt og laver en plan for næste gang', 'fb' => 'Du lærer af alt – det er en kæmpe konkurrencefordel 🧩', 'tag' => 'Fejl = data', 'w' => ['empatisk'=>0,'energisk'=>1,'analytisk'=>3,'social'=>0]],
                    ['text' => 'Jeg søger selskab – andres positive energi hjælper mig videre', 'fb' => 'At vide hvornår du trænger til folk – det er selvindsigt 🌐', 'tag' => 'Fællesskabet helbreder', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Hvad giver dig den dybeste tilfredsstillelse?',
                'help' => null,
                'answers' => [
                    ['text' => 'At se en kunde lykkes, og vide at jeg var en del af det', 'fb' => 'Det er det smukkeste ved dit job – og du mærker det hver gang 💛', 'tag' => 'Kundens sejr = din sejr', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>1,'social'=>0]],
                    ['text' => 'At overgå mine egne forventninger og sætte nye rekorder', 'fb' => 'Den personlige excellence driver dig fremad – altid ⚡', 'tag' => 'Personal best', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => 'At løse et problem som ingen andre kunne knække', 'fb' => 'Den unikke indsats er dit varemærke 🧩', 'tag' => 'Unik løsningsskaber', 'w' => ['empatisk'=>0,'energisk'=>0,'analytisk'=>3,'social'=>1]],
                    ['text' => 'At vide at jeg har skabt forbindelser der holder på lang sigt', 'fb' => 'Du bygger noget der varer – og det mærker alle 🌐', 'tag' => 'Legacy builder', 'w' => ['empatisk'=>1,'energisk'=>0,'analytisk'=>0,'social'=>3]],
                ],
            ],
            [
                'q'    => 'Ét ord der bedst beskriver dig i et professionelt miljø?',
                'help' => null,
                'answers' => [
                    ['text' => '💛 Empatisk – jeg mærker hvad andre har brug for', 'fb' => 'Den gave er din største styrke som Customer Success Advisor 💛', 'tag' => 'Empati som superpower', 'w' => ['empatisk'=>3,'energisk'=>0,'analytisk'=>0,'social'=>1]],
                    ['text' => '⚡ Driven – jeg sætter mål og når dem', 'fb' => 'Den drive er det der adskiller de gode fra de bedste ⚡', 'tag' => 'Drive uden stop', 'w' => ['empatisk'=>0,'energisk'=>3,'analytisk'=>1,'social'=>0]],
                    ['text' => '🧩 Analytisk – jeg finder mønstrene og løsningerne', 'fb' => 'Den skarphed giver dig en edge ingen kan kopiere 🧩', 'tag' => 'Skarphedens edge', 'w' => ['empatisk'=>0,'energisk'=>0,'analytisk'=>3,'social'=>1]],
                    ['text' => '🌐 Social – jeg er god til mennesker og relationer', 'fb' => 'Den naturlige charme åbner alle døre 🌐', 'tag' => 'Dørene åbner sig', 'w' => ['empatisk'=>1,'energisk'=>1,'analytisk'=>0,'social'=>3]],
                ],
            ],
        ];

        foreach ( $questions_data as $sort => $qd ) {
            $wpdb->insert( $qt, [
                'question_text' => $qd['q'],
                'helper_text'   => $qd['help'],
                'sort_order'    => $sort,
                'is_active'     => 1,
            ] );
            $qid = $wpdb->insert_id;

            foreach ( $qd['answers'] as $asort => $a ) {
                $wpdb->insert( $at, [
                    'question_id'   => $qid,
                    'answer_text'   => $a['text'],
                    'feedback_text' => $a['fb'],
                    'tagline'       => $a['tag'],
                    'sort_order'    => $asort,
                    'weights'       => wp_json_encode( $a['w'] ),
                ] );
            }
        }
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get_profiles(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rzpa_quiz_profiles ORDER BY sort_order ASC",
            ARRAY_A
        ) ?: [];
        foreach ( $rows as &$r ) {
            $r['strengths']     = json_decode( $r['strengths']     ?? '[]', true ) ?: [];
            $r['thrives_with']  = json_decode( $r['thrives_with']  ?? '[]', true ) ?: [];
            $r['develop_areas'] = json_decode( $r['develop_areas'] ?? '[]', true ) ?: [];
        }
        return $rows;
    }

    public static function get_quiz_data(): array {
        global $wpdb;
        $profiles  = self::get_profiles();
        $qt        = $wpdb->prefix . 'rzpa_quiz_questions';
        $at        = $wpdb->prefix . 'rzpa_quiz_answers';

        $questions = $wpdb->get_results(
            "SELECT * FROM {$qt} WHERE is_active = 1 ORDER BY sort_order ASC",
            ARRAY_A
        ) ?: [];

        foreach ( $questions as &$q ) {
            $qid     = (int) $q['id'];
            $answers = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$at} WHERE question_id = %d ORDER BY sort_order ASC", $qid ),
                ARRAY_A
            ) ?: [];
            foreach ( $answers as &$a ) {
                $a['weights'] = json_decode( $a['weights'] ?? '{}', true ) ?: [];
            }
            $q['answers'] = $answers;
        }

        return [ 'profiles' => $profiles, 'questions' => $questions ];
    }

    public static function get_profile_by_slug( string $slug ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rzpa_quiz_profiles WHERE slug = %s", $slug ),
            ARRAY_A
        );
        if ( ! $row ) return null;
        $row['strengths']     = json_decode( $row['strengths']     ?? '[]', true ) ?: [];
        $row['thrives_with']  = json_decode( $row['thrives_with']  ?? '[]', true ) ?: [];
        $row['develop_areas'] = json_decode( $row['develop_areas'] ?? '[]', true ) ?: [];
        return $row;
    }

    public static function get_profile_by_id( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rzpa_quiz_profiles WHERE id = %d", $id ),
            ARRAY_A
        );
        if ( ! $row ) return null;
        $row['strengths']     = json_decode( $row['strengths']     ?? '[]', true ) ?: [];
        $row['thrives_with']  = json_decode( $row['thrives_with']  ?? '[]', true ) ?: [];
        $row['develop_areas'] = json_decode( $row['develop_areas'] ?? '[]', true ) ?: [];
        return $row;
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public static function save_submission( array $d ): int|false {
        global $wpdb;
        $ok = $wpdb->insert( $wpdb->prefix . 'rzpa_quiz_submissions', [
            'name'                 => $d['name'],
            'phone'                => $d['phone'] ?? '',
            'email'                => $d['email'] ?? '',
            'winning_profile_id'   => $d['winning_profile_id'],
            'secondary_profile_id' => $d['secondary_profile_id'],
            'scores'               => wp_json_encode( $d['scores'] ),
            'answers_data'         => wp_json_encode( $d['answers'] ),
            'consent'              => $d['consent'] ? 1 : 0,
            'withdraw_token'       => $d['withdraw_token'],
            'ip_address'           => $d['ip'] ?? '',
        ] );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    // ── Admin reads ───────────────────────────────────────────────────────────

    public static function get_submissions( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $st = $wpdb->prefix . 'rzpa_quiz_submissions';
        $pt = $wpdb->prefix . 'rzpa_quiz_profiles';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.name, s.phone, s.email, s.consent, s.created_at,
                    s.scores, p.title AS profile_title, p.color AS profile_color, p.icon_emoji
             FROM {$st} s
             LEFT JOIN {$pt} p ON s.winning_profile_id = p.id
             ORDER BY s.created_at DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A ) ?: [];
    }

    public static function get_submission_detail( int $id ): ?array {
        global $wpdb;
        $st  = $wpdb->prefix . 'rzpa_quiz_submissions';
        $pt  = $wpdb->prefix . 'rzpa_quiz_profiles';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*,
                    p.title       AS profile_title,  p.color       AS profile_color,
                    p.icon_emoji  AS profile_icon,   p.description AS profile_desc,
                    p.strengths,  p.thrives_with,    p.develop_areas,
                    p2.title      AS secondary_title, p2.color     AS secondary_color,
                    p2.icon_emoji AS secondary_icon
             FROM {$st} s
             LEFT JOIN {$pt} p  ON s.winning_profile_id   = p.id
             LEFT JOIN {$pt} p2 ON s.secondary_profile_id = p2.id
             WHERE s.id = %d",
            $id
        ), ARRAY_A );
        if ( ! $row ) return null;

        $at  = $wpdb->prefix . 'rzpa_quiz_answers';
        $qt  = $wpdb->prefix . 'rzpa_quiz_questions';
        $raw = json_decode( $row['answers_data'] ?? '[]', true );
        $qa  = [];
        foreach ( (array) $raw as $item ) {
            $aid = (int) ( $item['answerId'] ?? 0 );
            if ( ! $aid ) continue;
            $ans = $wpdb->get_row( $wpdb->prepare(
                "SELECT a.answer_text, q.question_text, q.sort_order
                 FROM {$at} a
                 JOIN {$qt} q ON a.question_id = q.id
                 WHERE a.id = %d",
                $aid
            ), ARRAY_A );
            if ( $ans ) $qa[] = $ans;
        }
        usort( $qa, fn( $a, $b ) => (int) $a['sort_order'] <=> (int) $b['sort_order'] );
        $row['qa']     = $qa;
        $row['scores'] = json_decode( $row['scores'] ?? '{}', true );
        unset( $row['answers_data'], $row['withdraw_token'], $row['ip_address'] );
        return $row;
    }

    public static function count_submissions(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rzpa_quiz_submissions" );
    }

    public static function get_profile_distribution(): array {
        global $wpdb;
        $st = $wpdb->prefix . 'rzpa_quiz_submissions';
        $pt = $wpdb->prefix . 'rzpa_quiz_profiles';
        return $wpdb->get_results(
            "SELECT p.title, p.color, p.icon_emoji, COUNT(s.id) AS total
             FROM {$pt} p
             LEFT JOIN {$st} s ON s.winning_profile_id = p.id
             GROUP BY p.id
             ORDER BY p.sort_order ASC",
            ARRAY_A
        ) ?: [];
    }
}
