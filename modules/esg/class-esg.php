<?php
/**
 * RZPA_ESG — ESG Module for Rezponz Analytics
 *
 * Renders a full, SEO-friendly ESG page via the [rezponz_esg] shortcode.
 * All editorial content (text, numbers, labels) lives in self::get_data().
 * The HTML template (views/esg-frontend.php) is intentionally "dumb" —
 * it only iterates over the data array and escapes output.
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │  SHORTCODE USAGE                                             │
 * │  Add  [rezponz_esg]  to any WordPress page or post.         │
 * │                                                              │
 * │  CSS and JS are only enqueued when the shortcode is present. │
 * │                                                              │
 * │  To update content:                                          │
 * │    • Open class-esg.php                                      │
 * │    • Find self::get_data() below                             │
 * │    • Edit the PHP arrays — no HTML or JS changes needed      │
 * └──────────────────────────────────────────────────────────────┘
 *
 * @package    RezponzAnalytics
 * @subpackage ESG
 * @since      3.5.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RZPA_ESG {

    /** @var bool Whether the shortcode has been found on the current page. */
    private static bool $enqueue_flag = false;

    // ─────────────────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Register hooks. Called once from the main plugin file.
     */
    public static function init(): void {
        add_shortcode( 'rezponz_esg', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Asset enqueueing (only when shortcode is on the page)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enqueue CSS and JS for the ESG module.
     *
     * Assets are only loaded when [rezponz_esg] is detected on the current
     * page — detected via has_shortcode() on the global $post content.
     */
    public static function enqueue_assets(): void {
        global $post;

        $has_shortcode = (
            is_a( $post, 'WP_Post' )
            && has_shortcode( $post->post_content, 'rezponz_esg' )
        );

        if ( ! $has_shortcode ) {
            return;
        }

        $ver = defined( 'RZPA_VERSION' ) ? RZPA_VERSION : '3.5.9';
        $url = defined( 'RZPA_URL' ) ? RZPA_URL : plugin_dir_url( __FILE__ );
        $base = rtrim( $url, '/' ) . '/modules/esg/assets/';

        wp_enqueue_style(
            'rzpa-esg',
            $base . 'esg.css',
            [],
            $ver
        );

        wp_enqueue_script(
            'rzpa-esg',
            $base . 'esg.js',
            [],
            $ver,
            true  // footer
        );

        // Pass PHP data to JS (used by tracking dispatcher).
        wp_localize_script( 'rzpa-esg', 'RZ_ESG_DATA', self::get_js_data() );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shortcode renderer
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render the [rezponz_esg] shortcode.
     *
     * @return string  Full HTML of the ESG module.
     */
    public static function render_shortcode(): string {
        $data = self::get_data();
        ob_start();
        require __DIR__ . '/views/esg-frontend.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JS data subset (only what JS needs for tracking)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns a lean array passed to window.RZ_ESG_DATA via wp_localize_script.
     * Keep this small — only tracking labels and IDs.
     *
     * @return array<string,mixed>
     */
    private static function get_js_data(): array {
        $data = self::get_data();

        $action_ids = array_map( fn( $c ) => $c['id'], $data['action_cards'] );
        $faq_ids    = array_map( fn( $q ) => $q['id'], $data['faq'] );

        return [
            'version'    => defined( 'RZPA_VERSION' ) ? RZPA_VERSION : '3.5.9',
            'action_ids' => $action_ids,
            'faq_ids'    => $faq_ids,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //
    //  ██████╗ ███████╗████████╗     ██████╗  █████╗ ████████╗ █████╗
    //  ██╔════╝ ██╔════╝╚══██╔══╝    ██╔══██╗██╔══██╗╚══██╔══╝██╔══██╗
    //  ██║  ███╗█████╗     ██║       ██║  ██║███████║   ██║   ███████║
    //  ██║   ██║██╔══╝     ██║       ██║  ██║██╔══██║   ██║   ██╔══██║
    //  ╚██████╔╝███████╗   ██║       ██████╔╝██║  ██║   ██║   ██║  ██║
    //   ╚═════╝ ╚══════╝   ╚═╝       ╚═════╝ ╚═╝  ╚═╝   ╚═╝   ╚═╝  ╚═╝
    //
    //  EDITORIAL SOURCE OF TRUTH
    //  ─────────────────────────
    //  All visible text, numbers, labels and configuration live here.
    //  Do NOT edit the HTML template (views/esg-frontend.php) for content
    //  changes — only edit the arrays returned below.
    //
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns all ESG content as a structured PHP array.
     *
     * Sections:
     *   hero          — Section 1: big KPI + intro + chips
     *   tracks        — Section 2: 3 ESG track cards (Miljø, Mennesker, Governance)
     *   roadmap       — Section 3: horizontal/vertical timeline milestones
     *   action_cards  — Section 4: expandable indsatskort (5 cards)
     *   metrics       — Section 5: "Sådan måler vi" collapsible definitions
     *   cases         — Section 6: 2 hverdagsnær ESG story cards
     *   faq           — Section 7: FAQ accordion (5 items)
     *   cta           — Section 8: call-to-action banner
     *
     * @return array<string,mixed>
     */
    public static function get_data(): array {
        return [

            // ──────────────────────────────────────────────────────────────────
            // SECTION 1 — Hero
            // ──────────────────────────────────────────────────────────────────
            'hero' => [
                // Main heading
                'heading'  => 'Fra mål til handling',

                // Introductory paragraph
                'intro'    => 'Hos Rezponz arbejder vi med ESG, fordi det er det rigtige at gøre — for vores medarbejdere, for miljøet og for dem, vi samarbejder med. Her er en åben og ærlig status på, hvor vi er, og hvad vi arbejder henimod.',

                // Big animated KPI — number only (suffix shown separately)
                'kpi_number'   => 42,
                'kpi_suffix'   => ' %',
                'kpi_label'    => 'reduktion i scope 1+2 inden 2030',
                // Duration of count-up animation in ms (respects prefers-reduced-motion)
                'kpi_duration' => 1500,

                // Short tagline below KPI
                'tagline'  => 'Dokumenterede indsatser. Tydelige næste skridt.',

                // Four smaller KPI chips in a row
                // Format: [ 'value' => string, 'label' => string ]
                'chips' => [
                    [ 'value' => '294 FTE',     'label' => 'medarbejdere i 2024' ],
                    [ 'value' => '0',            'label' => 'arbejdsulykker i 2024' ],
                    [ 'value' => '57,1 t CO₂e', 'label' => 'scope 1 emission 2024' ],
                    [ 'value' => '0',            'label' => 'korruptionshændelser i 2024' ],
                ],

                // Small trust / data-source note
                'trust_note' => 'Data baseret på ESG-rapportering 2024. Opdateres løbende.',
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 2 — ESG Spor (3 track cards)
            // status types: 'ongoing' (amber) | 'done' (green) | 'upcoming' (blue)
            // ──────────────────────────────────────────────────────────────────
            'tracks' => [
                [
                    'id'      => 'miljo',
                    'icon'    => '🌿',
                    'title'   => 'Miljø',
                    // status: 'ongoing' | 'done' | 'upcoming'
                    'status'      => 'ongoing',
                    'status_label' => 'I gang',
                    'intro'   => 'Vi reducerer vores klimaaftryk systematisk — med konkrete indsatser i energi, forbrug og transport.',
                    // KPI rows: [ 'value', 'label' ]
                    'kpis'    => [
                        [ 'value' => '57,1 t CO₂e',    'label' => 'scope 1' ],
                        [ 'value' => '65,5 t CO₂e',    'label' => 'scope 2' ],
                        [ 'value' => '1.285.153 MJ',   'label' => 'energiforbrug' ],
                        [ 'value' => '3.664 m³',       'label' => 'vandforbrug' ],
                    ],
                    'practice' => 'Vi bruger solenergi og grøn strøm, reparerer og genbruger IT-udstyr, og kortlægger scope 3-emissioner fra 2026.',
                    'meaning'  => 'Hvert ton CO₂ vi undgår er et skridt tættere på de 42 % vi har lovet.',
                ],
                [
                    'id'      => 'mennesker',
                    'icon'    => '🤝',
                    'title'   => 'Mennesker',
                    'status'      => 'ongoing',
                    'status_label' => 'I gang',
                    'intro'   => 'Vi er en arbejdsplads for rigtige mennesker — og vi tager ansvar for at inkludere dem, der har svært ved at komme ind på arbejdsmarkedet.',
                    'kpis'    => [
                        [ 'value' => '294 FTE',  'label' => 'medarbejdere' ],
                        [ 'value' => '0',         'label' => 'arbejdsulykker' ],
                        [ 'value' => '72,5 t',   'label' => 'efteruddannelse kvinder' ],
                        [ 'value' => '68,5 t',   'label' => 'efteruddannelse mænd' ],
                    ],
                    'practice' => 'Vi samarbejder med initiativer som »Små Job med Mening« og prioriterer rummelig ansættelse. Alle medarbejdere gennemgår løbende træning.',
                    'meaning'  => 'Et sikkert arbejdsmiljø og reel mulighed for at lære er ikke pynt — det er noget vi måler på.',
                ],
                [
                    'id'      => 'governance',
                    'icon'    => '⚖️',
                    'title'   => 'Governance',
                    'status'      => 'done',
                    'status_label' => 'På plads',
                    'intro'   => 'Ansvarlig virksomhedsdrift handler om at gøre det rigtige — også når ingen kigger.',
                    'kpis'    => [
                        [ 'value' => '0',   'label' => 'korruptionshændelser' ],
                        [ 'value' => '0',   'label' => 'bestikkelseshændelser' ],
                        [ 'value' => '✓',   'label' => 'whistleblowerordning aktiv' ],
                        [ 'value' => '✓',   'label' => 'etiktræning for medarbejdere' ],
                    ],
                    'practice' => 'Vi har en aktiv whistleblowerordning og træner løbende vores medarbejdere i etisk adfærd og ansvarlig virksomhedsdrift.',
                    'meaning'  => 'Nul hændelser er ikke tilfældigt — det er resultatet af klare spilleregler og en kultur, der belønner ærlighed.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 3 — Roadmap / Milepæle
            // status: 'done' | 'upcoming' | 'target'
            // Add more milestones here — the HTML renders them dynamically.
            // ──────────────────────────────────────────────────────────────────
            'roadmap' => [
                [
                    'year'    => '2023',
                    'title'   => 'Baseline og første ESG-rapport',
                    'body'    => 'Første formelle ESG-rapport offentliggjort. Baseline etableret for scope 1 og 2.',
                    'status'  => 'done',
                    'tag'     => 'Gennemført',
                    'icon'    => '📊',
                ],
                [
                    'year'    => '2024',
                    'title'   => 'Indsatser i gang',
                    'body'    => 'Solenergi, grøn strøm, IT-genbrug og inkluderende beskæftigelse er aktive indsatser.',
                    'status'  => 'done',
                    'tag'     => 'Gennemført',
                    'icon'    => '✅',
                ],
                [
                    'year'    => '2026',
                    'title'   => 'Scope 3-kortlægning',
                    'body'    => 'Vi kortlægger vores scope 3-emissioner — herunder leverandørkæde og medarbejdertransport.',
                    'status'  => 'upcoming',
                    'tag'     => 'Planlagt',
                    'icon'    => '🗺️',
                ],
                [
                    'year'    => '2030',
                    'title'   => '42 % reduktion i scope 1+2',
                    'body'    => 'Vores langsigtede klimamål: 42 % reduktion i scope 1 og scope 2 emissioner målt fra 2023-baseline.',
                    'status'  => 'target',
                    'tag'     => 'Mål',
                    'icon'    => '🎯',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 4 — Indsatskort (5 expandable action cards)
            // category: 'Miljø' | 'Mennesker' | 'Governance'
            // ──────────────────────────────────────────────────────────────────
            'action_cards' => [
                [
                    'id'       => 'solenergi',
                    'icon'     => '☀️',
                    'title'    => 'Solenergi',
                    'category' => 'Miljø',
                    'summary'  => 'Vi anvender solenergi som en del af vores energiforsyning.',
                    'why'      => 'Vedvarende energi er en direkte vej til at reducere vores scope 2-emissioner og mindske afhængighed af fossil energi.',
                    'how'      => 'Solceller bidrager til at dække en del af energiforbruget i vores lokaler. Det er en investering i en lavere CO₂-profil på sigt.',
                    'next'     => 'Løbende evaluering af udvidelsesmuligheder i takt med bygnings- og kapacitetsændringer.',
                ],
                [
                    'id'       => 'groenn-stroem',
                    'icon'     => '⚡',
                    'title'    => 'Grøn strøm',
                    'category' => 'Miljø',
                    'summary'  => 'Øvrig el er grøn eller CO₂-kompenseret.',
                    'why'      => 'Elforbruget i et contact center er markant. Grøn strøm er en af de mest direkte måder at reducere scope 2 på.',
                    'how'      => 'Vi bruger el fra vedvarende energikilder eller el, der CO₂-kompenseres. Det er ikke nok i sig selv, men det tæller med.',
                    'next'     => 'Øget fokus på energieffektivitet for at reducere det samlede forbrug — ikke kun emissionsintensiteten.',
                ],
                [
                    'id'       => 'it-genbrug',
                    'icon'     => '💻',
                    'title'    => 'IT-genbrug og reparation',
                    'category' => 'Miljø',
                    'summary'  => 'IT-udstyr repareres, genbruges eller genanvendes miljømæssigt forsvarligt.',
                    'why'      => 'Elektronik har et stort CO₂-aftryk i produktion. Hvert stykke udstyr vi forlænger levetiden på, er CO₂ vi ikke udleder.',
                    'how'      => 'Når udstyr er udtjent, prioriterer vi reparation og genbrug frem for bortskaffelse. Det gælder computere, skærme og telefoner.',
                    'next'     => 'Kortlægning som en del af scope 3-analysen i 2026.',
                ],
                [
                    'id'       => 'inkluderende-beskaeftigelse',
                    'icon'     => '🙌',
                    'title'    => 'Inkluderende beskæftigelse',
                    'category' => 'Mennesker',
                    'summary'  => 'Vi samarbejder aktivt for at skabe jobs til dem, der ellers har svært ved at komme ind på arbejdsmarkedet.',
                    'why'      => 'Et stærkt arbejdsmarked er et, der har plads til alle. Rummelighed er ikke kun en etisk forpligtelse — det skaber bedre virksomheder.',
                    'how'      => 'Via samarbejde med initiativer som »Små Job med Mening« og tilsvarende programmer skaber vi konkrete muligheder for sårbare grupper.',
                    'next'     => 'Fortsat styrke samarbejder og dokumentere effekten af indsatserne.',
                ],
                [
                    'id'       => 'whistleblower',
                    'icon'     => '🔒',
                    'title'    => 'Whistleblower og etisk adfærd',
                    'category' => 'Governance',
                    'summary'  => 'Vi har en aktiv whistleblowerordning og løbende etisk træning for alle medarbejdere.',
                    'why'      => 'Åbenhed og etisk adfærd er grundstenen i en troværdig virksomhed. Det handler om at give medarbejdere en tryg måde at sige fra på.',
                    'how'      => 'Alle medarbejdere kan anonymt indberette bekymringer. Træning i etisk adfærd er en del af vores onboarding og løbende udvikling.',
                    'next'     => 'Øget dokumentation og transparens om hvad ordningen bruges til og hvad den fører til.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 5 — Sådan måler vi (collapsible definitions grid)
            // ──────────────────────────────────────────────────────────────────
            'metrics' => [
                'toggle_label_open'  => 'Sådan måler vi',
                'toggle_label_close' => 'Skjul forklaringer',
                // Definition pairs: [ 'term', 'definition' ]
                'definitions' => [
                    [
                        'term' => 'Scope 1',
                        'def'  => 'Direkte emissioner fra Rezponz\' egne aktiviteter — fx kørsel i egne køretøjer og brændstofforbrug i bygninger.',
                    ],
                    [
                        'term' => 'Scope 2',
                        'def'  => 'Indirekte emissioner fra den el og varme vi køber — dvs. hvad kraftværkerne udleder for at levere vores energi.',
                    ],
                    [
                        'term' => 'Scope 3',
                        'def'  => 'Emissioner i vores værdikæde — medarbejdertransport, leverandører, indkøb. Vi kortlægger dette fra 2026.',
                    ],
                    [
                        'term' => 'Efteruddannelsestimer',
                        'def'  => 'Gennemsnitlige timer pr. medarbejder brugt på kurser, intern træning og certificeringer i løbet af et år.',
                    ],
                    [
                        'term' => 'Governance-data',
                        'def'  => 'Tal og indikatorer for ansvarlig virksomhedsdrift — herunder korruption, whistleblower-sager og etiktræning.',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 6 — Cases / Hverdagsnær ESG (2 story cards)
            // ──────────────────────────────────────────────────────────────────
            'cases' => [
                [
                    'tag'   => 'Mennesker',
                    'title' => 'Adgang til arbejdsmarkedet — på ordentlige vilkår',
                    'intro' => 'En konkret indsats, der skaber reel forskel',
                    'body'  => 'Gennem samarbejdet med »Små Job med Mening« har vi skabt muligheder for mennesker, der normalt holder sig uden for fuldtidsbeskæftigelse. Det er ikke et PR-stunt — det er en del af vores forpligtelse over for det samfund, vi er en del af.',
                ],
                [
                    'tag'   => 'Miljø',
                    'title' => 'Når en gammel computer ikke smides ud',
                    'intro' => 'IT-genbrug i praksis',
                    'body'  => 'I stedet for at udskifte computere efter fast cyklus reparerer og forlænger vi levetiden. Det sparer penge, men vigtigst af alt reducerer det det elektronikaffald, vi sender videre i verden.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 7 — FAQ accordion (5 items)
            // ──────────────────────────────────────────────────────────────────
            'faq' => [
                [
                    'id'       => 'faq-1',
                    'question' => 'Hvad er Rezponz\' vigtigste klimamål?',
                    'answer'   => 'Vi arbejder mod en 42 % reduktion af vores scope 1 og scope 2 CO₂-emissioner inden udgangen af 2030, målt fra vores 2023-baseline. Det er et konkret, tidsafgrænset mål — ikke et løfte om at »blive mere bæredygtige«.',
                ],
                [
                    'id'       => 'faq-2',
                    'question' => 'Hvordan arbejder Rezponz med ansvarlig drift?',
                    'answer'   => 'Vi har en aktiv whistleblowerordning, gennemfører løbende etiktræning og har i 2024 haft nul korruptions- eller bestikkelseshændelser. Vi rapporterer åbent om det, vi gør — og det, vi endnu ikke har gjort.',
                ],
                [
                    'id'       => 'faq-3',
                    'question' => 'Hvordan omsætter Rezponz ESG til handling i hverdagen?',
                    'answer'   => 'Konkret: vi bruger solenergi og grøn strøm, reparerer IT-udstyr fremfor at kassere det, og samarbejder aktivt for at skabe jobs til mennesker, der er svære at nå via normale ansættelseskanaler.',
                ],
                [
                    'id'       => 'faq-4',
                    'question' => 'Hvornår kortlægger Rezponz scope 3-emissioner?',
                    'answer'   => 'Vi sætter scope 3-kortlægningen i gang i 2026. Det inkluderer emissioner fra leverandørkæde, medarbejdertransport og indirekte aktiviteter — en del, vi endnu ikke har fuld indsigt i.',
                ],
                [
                    'id'       => 'faq-5',
                    'question' => 'Hvem har ansvar for ESG hos Rezponz?',
                    'answer'   => 'ESG er et ledelsesansvar hos Rezponz. Data og indsatser forankres løbende i organisationen og rapporteres åbent i vores ESG-rapport.',
                ],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SECTION 8 — CTA (call-to-action banner)
            // ──────────────────────────────────────────────────────────────────
            'cta' => [
                'heading' => 'Vil du vide mere?',
                'body'    => 'ESG er ikke et projekt med en slutdato — det er en løbende forpligtelse. Vi deler vores fremdrift åbent, fordi vi tror på, at transparens skaber bedre resultater.',
                // Primary button
                'btn_primary_label'       => 'Læs ESG-rapporten',
                'btn_primary_url'         => '#esg-rapport',
                'btn_primary_track_event' => 'esg_cta_report',
                // Secondary button
                'btn_secondary_label'       => 'Se vores konkrete indsatser',
                'btn_secondary_url'         => '#indsatser',
                'btn_secondary_track_event' => 'esg_cta_actions',
            ],

        ]; // end get_data()
    }

} // end class RZPA_ESG
