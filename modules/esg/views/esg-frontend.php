<?php
/**
 * ESG Module — Frontend Template
 *
 * This template is intentionally "dumb": it only loops over the $data array
 * provided by RZPA_ESG::render_shortcode() and escapes all output.
 * Do NOT put editorial content here — edit class-esg.php :: get_data() instead.
 *
 * @var array $data  Full content array from RZPA_ESG::get_data()
 *
 * @package    RezponzAnalytics
 * @subpackage ESG
 * @since      3.5.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$hero    = $data['hero'];
$tracks  = $data['tracks'];
$roadmap = $data['roadmap'];
$cards   = $data['action_cards'];
$metrics = $data['metrics'];
$cases   = $data['cases'];
$faq     = $data['faq'];
$cta     = $data['cta'];
?>

<div class="rz-esg" id="rz-esg">

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 1 — Hero
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-hero" aria-label="ESG overblik">
        <div class="rz-esg-container">
            <div class="rz-esg-hero__inner">

                <div class="rz-esg-hero__content rz-esg-animate" data-delay="0">
                    <h1 class="rz-esg-hero__heading">
                        <?php echo esc_html( $hero['heading'] ); ?>
                    </h1>
                    <p class="rz-esg-hero__intro">
                        <?php echo esc_html( $hero['intro'] ); ?>
                    </p>
                </div>

                <div class="rz-esg-hero__kpi rz-esg-animate" data-delay="100" aria-label="<?php echo esc_attr( $hero['kpi_label'] ); ?>">
                    <div class="rz-esg-hero__kpi-number" aria-hidden="true">
                        <span
                            class="rz-esg-counter"
                            data-counter="<?php echo esc_attr( $hero['kpi_number'] ); ?>"
                            data-counter-suffix="<?php echo esc_attr( $hero['kpi_suffix'] ); ?>"
                            data-counter-duration="<?php echo esc_attr( $hero['kpi_duration'] ); ?>"
                        >0<?php echo esc_html( $hero['kpi_suffix'] ); ?></span>
                    </div>
                    <p class="rz-esg-hero__kpi-label">
                        <?php echo esc_html( $hero['kpi_label'] ); ?>
                    </p>
                    <p class="rz-esg-hero__tagline">
                        <?php echo esc_html( $hero['tagline'] ); ?>
                    </p>
                </div>

            </div>

            <?php if ( ! empty( $hero['chips'] ) ) : ?>
            <ul class="rz-esg-hero__chips rz-esg-animate" data-delay="200" role="list" aria-label="Nøgletal 2024">
                <?php foreach ( $hero['chips'] as $chip ) : ?>
                <li class="rz-esg-chip">
                    <span class="rz-esg-chip__value"><?php echo esc_html( $chip['value'] ); ?></span>
                    <span class="rz-esg-chip__label"><?php echo esc_html( $chip['label'] ); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <p class="rz-esg-hero__trust rz-esg-animate" data-delay="300">
                <?php echo esc_html( $hero['trust_note'] ); ?>
            </p>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 2 — ESG Spor (3 track cards)
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-tracks" id="esg-spor" aria-labelledby="rz-esg-tracks-heading">
        <div class="rz-esg-container">
            <h2 class="rz-esg-section-heading rz-esg-animate" id="rz-esg-tracks-heading">
                ESG Spor
            </h2>
            <div class="rz-esg-tracks__grid">
                <?php foreach ( $tracks as $i => $track ) : ?>
                <article
                    class="rz-esg-track-card rz-esg-track-card--<?php echo esc_attr( $track['status'] ); ?> rz-esg-animate"
                    data-delay="<?php echo esc_attr( $i * 100 ); ?>"
                    id="track-<?php echo esc_attr( $track['id'] ); ?>"
                    aria-label="<?php echo esc_attr( $track['title'] ); ?> — <?php echo esc_attr( $track['status_label'] ); ?>"
                >
                    <header class="rz-esg-track-card__header">
                        <div class="rz-esg-track-card__icon" aria-hidden="true">
                            <?php echo esc_html( $track['icon'] ); ?>
                        </div>
                        <div class="rz-esg-track-card__title-wrap">
                            <h3 class="rz-esg-track-card__title">
                                <?php echo esc_html( $track['title'] ); ?>
                            </h3>
                            <span class="rz-esg-status rz-esg-status--<?php echo esc_attr( $track['status'] ); ?>">
                                <?php echo esc_html( $track['status_label'] ); ?>
                            </span>
                        </div>
                    </header>

                    <p class="rz-esg-track-card__intro">
                        <?php echo esc_html( $track['intro'] ); ?>
                    </p>

                    <?php if ( ! empty( $track['kpis'] ) ) : ?>
                    <ul class="rz-esg-track-card__kpis" role="list" aria-label="Nøgletal for <?php echo esc_attr( $track['title'] ); ?>">
                        <?php foreach ( $track['kpis'] as $kpi ) : ?>
                        <li class="rz-esg-track-kpi">
                            <span class="rz-esg-track-kpi__value"><?php echo esc_html( $kpi['value'] ); ?></span>
                            <span class="rz-esg-track-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="rz-esg-track-card__practice">
                        <strong>I praksis: </strong><?php echo esc_html( $track['practice'] ); ?>
                    </div>
                    <div class="rz-esg-track-card__meaning">
                        <?php echo esc_html( $track['meaning'] ); ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 3 — Roadmap / Milepæle
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-roadmap" id="milepæle" aria-labelledby="rz-esg-roadmap-heading">
        <div class="rz-esg-container">
            <h2 class="rz-esg-section-heading rz-esg-animate" id="rz-esg-roadmap-heading">
                Milepæle
            </h2>
            <p class="rz-esg-section-intro rz-esg-animate" data-delay="50">
                Vores vej fra baseline til klimamål.
            </p>
            <div class="rz-esg-roadmap__track rz-esg-animate" data-delay="100" role="list" aria-label="ESG tidslinje">
                <?php foreach ( $roadmap as $i => $milestone ) : ?>
                <article
                    class="rz-esg-milestone rz-esg-milestone--<?php echo esc_attr( $milestone['status'] ); ?>"
                    role="listitem"
                    aria-label="<?php echo esc_attr( $milestone['year'] . ': ' . $milestone['title'] ); ?>"
                >
                    <div class="rz-esg-milestone__dot" aria-hidden="true">
                        <span class="rz-esg-milestone__dot-icon"><?php echo esc_html( $milestone['icon'] ); ?></span>
                    </div>
                    <div class="rz-esg-milestone__content">
                        <div class="rz-esg-milestone__meta">
                            <span class="rz-esg-milestone__year"><?php echo esc_html( $milestone['year'] ); ?></span>
                            <span class="rz-esg-milestone__tag rz-esg-milestone__tag--<?php echo esc_attr( $milestone['status'] ); ?>">
                                <?php echo esc_html( $milestone['tag'] ); ?>
                            </span>
                        </div>
                        <h3 class="rz-esg-milestone__title">
                            <?php echo esc_html( $milestone['title'] ); ?>
                        </h3>
                        <p class="rz-esg-milestone__body">
                            <?php echo esc_html( $milestone['body'] ); ?>
                        </p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 4 — Indsatskort (expandable action cards)
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-actions" id="indsatser" aria-labelledby="rz-esg-actions-heading">
        <div class="rz-esg-container">
            <h2 class="rz-esg-section-heading rz-esg-animate" id="rz-esg-actions-heading">
                Konkrete indsatser
            </h2>
            <p class="rz-esg-section-intro rz-esg-animate" data-delay="50">
                Det vi rent faktisk gør — og hvorfor det er vigtigt.
            </p>
            <div class="rz-esg-actions__grid">
                <?php foreach ( $cards as $i => $card ) : ?>
                <article
                    class="rz-esg-action-card rz-esg-action-card--cat-<?php echo esc_attr( strtolower( $card['category'] ) ); ?> rz-esg-animate"
                    data-delay="<?php echo esc_attr( ( $i % 3 ) * 80 ); ?>"
                    id="action-<?php echo esc_attr( $card['id'] ); ?>"
                    data-track-event="esg_action_view"
                >
                    <div class="rz-esg-action-card__top">
                        <div class="rz-esg-action-card__icon" aria-hidden="true">
                            <?php echo esc_html( $card['icon'] ); ?>
                        </div>
                        <span class="rz-esg-action-card__category rz-esg-badge rz-esg-badge--<?php echo esc_attr( strtolower( $card['category'] ) ); ?>">
                            <?php echo esc_html( $card['category'] ); ?>
                        </span>
                    </div>
                    <h3 class="rz-esg-action-card__title">
                        <?php echo esc_html( $card['title'] ); ?>
                    </h3>
                    <p class="rz-esg-action-card__summary">
                        <?php echo esc_html( $card['summary'] ); ?>
                    </p>

                    <?php
                    $panel_id  = 'rz-esg-action-panel-' . esc_attr( $card['id'] );
                    $toggle_id = 'rz-esg-action-toggle-' . esc_attr( $card['id'] );
                    ?>
                    <button
                        class="rz-esg-action-card__toggle rz-esg-btn-text"
                        id="<?php echo $toggle_id; ?>"
                        aria-expanded="false"
                        aria-controls="<?php echo $panel_id; ?>"
                        data-action-id="<?php echo esc_attr( $card['id'] ); ?>"
                        data-track-event="esg_action_open"
                    >
                        <span class="rz-esg-action-card__toggle-label">Læs mere</span>
                        <span class="rz-esg-action-card__toggle-icon" aria-hidden="true">+</span>
                    </button>

                    <div
                        class="rz-esg-action-card__panel"
                        id="<?php echo $panel_id; ?>"
                        role="region"
                        aria-labelledby="<?php echo $toggle_id; ?>"
                        hidden
                    >
                        <dl class="rz-esg-action-card__details">
                            <dt><?php esc_html_e( 'Hvorfor det er vigtigt', 'rezponz-analytics' ); ?></dt>
                            <dd><?php echo esc_html( $card['why'] ); ?></dd>

                            <dt><?php esc_html_e( 'Det gør vi i praksis', 'rezponz-analytics' ); ?></dt>
                            <dd><?php echo esc_html( $card['how'] ); ?></dd>

                            <dt><?php esc_html_e( 'Næste skridt', 'rezponz-analytics' ); ?></dt>
                            <dd><?php echo esc_html( $card['next'] ); ?></dd>
                        </dl>
                        <button
                            class="rz-esg-action-card__close rz-esg-btn-text"
                            aria-controls="<?php echo $panel_id; ?>"
                            data-closes-action="<?php echo esc_attr( $card['id'] ); ?>"
                        >
                            Luk
                        </button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 5 — Sådan måler vi (collapsible)
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-metrics" id="maalemetode" aria-labelledby="rz-esg-metrics-heading">
        <div class="rz-esg-container">
            <div class="rz-esg-metrics__toggle-wrap rz-esg-animate" id="rz-esg-metrics-toggle">
                <h2 class="rz-esg-section-heading" id="rz-esg-metrics-heading">
                    <?php echo esc_html( $metrics['toggle_label_open'] ); ?>
                </h2>
                <button
                    class="rz-esg-metrics__toggle rz-esg-btn-outline"
                    aria-expanded="false"
                    aria-controls="rz-esg-metrics-panel"
                    data-track-event="esg_metrics_toggle"
                    data-label-open="<?php echo esc_attr( $metrics['toggle_label_open'] ); ?>"
                    data-label-close="<?php echo esc_attr( $metrics['toggle_label_close'] ); ?>"
                >
                    <?php echo esc_html( $metrics['toggle_label_open'] ); ?>
                    <span class="rz-esg-metrics__toggle-arrow" aria-hidden="true">▾</span>
                </button>
            </div>

            <div
                class="rz-esg-metrics__panel"
                id="rz-esg-metrics-panel"
                role="region"
                aria-labelledby="rz-esg-metrics-heading"
                hidden
            >
                <dl class="rz-esg-metrics__grid">
                    <?php foreach ( $metrics['definitions'] as $def ) : ?>
                    <div class="rz-esg-metrics__item">
                        <dt class="rz-esg-metrics__term"><?php echo esc_html( $def['term'] ); ?></dt>
                        <dd class="rz-esg-metrics__def"><?php echo esc_html( $def['def'] ); ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 6 — Cases / Hverdagsnær ESG
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-cases" aria-labelledby="rz-esg-cases-heading">
        <div class="rz-esg-container">
            <h2 class="rz-esg-section-heading rz-esg-animate" id="rz-esg-cases-heading">
                ESG i hverdagen
            </h2>
            <p class="rz-esg-section-intro rz-esg-animate" data-delay="50">
                Konkrete eksempler på hvad vores indsatser betyder i praksis.
            </p>
            <div class="rz-esg-cases__grid">
                <?php foreach ( $cases as $i => $case ) : ?>
                <article
                    class="rz-esg-case-card rz-esg-animate"
                    data-delay="<?php echo esc_attr( $i * 100 ); ?>"
                    aria-label="<?php echo esc_attr( $case['title'] ); ?>"
                >
                    <span class="rz-esg-badge rz-esg-badge--<?php echo esc_attr( strtolower( $case['tag'] ) ); ?>">
                        <?php echo esc_html( $case['tag'] ); ?>
                    </span>
                    <h3 class="rz-esg-case-card__title">
                        <?php echo esc_html( $case['title'] ); ?>
                    </h3>
                    <p class="rz-esg-case-card__intro">
                        <em><?php echo esc_html( $case['intro'] ); ?></em>
                    </p>
                    <p class="rz-esg-case-card__body">
                        <?php echo esc_html( $case['body'] ); ?>
                    </p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 7 — FAQ Accordion
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-faq" id="faq" aria-labelledby="rz-esg-faq-heading">
        <div class="rz-esg-container">
            <h2 class="rz-esg-section-heading rz-esg-animate" id="rz-esg-faq-heading">
                Ofte stillede spørgsmål
            </h2>
            <dl class="rz-esg-faq__list rz-esg-animate" data-delay="50">
                <?php foreach ( $faq as $idx => $item ) :
                    $q_id = 'rz-esg-faq-q-' . esc_attr( $item['id'] );
                    $a_id = 'rz-esg-faq-a-' . esc_attr( $item['id'] );
                ?>
                <div class="rz-esg-faq__item" id="<?php echo $q_id; ?>">
                    <dt class="rz-esg-faq__question">
                        <button
                            class="rz-esg-faq__question-btn"
                            aria-expanded="false"
                            aria-controls="<?php echo $a_id; ?>"
                            data-track-event="esg_faq_open"
                            data-question-id="<?php echo esc_attr( $item['id'] ); ?>"
                            data-question-index="<?php echo esc_attr( $idx ); ?>"
                        >
                            <span><?php echo esc_html( $item['question'] ); ?></span>
                            <span class="rz-esg-faq__arrow" aria-hidden="true">▾</span>
                        </button>
                    </dt>
                    <dd
                        class="rz-esg-faq__answer"
                        id="<?php echo $a_id; ?>"
                        role="region"
                        aria-labelledby="<?php echo $q_id; ?>"
                        hidden
                    >
                        <p><?php echo esc_html( $item['answer'] ); ?></p>
                    </dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <?php /* ══════════════════════════════════════════════════════════════════
     SECTION 8 — CTA Banner
    ══════════════════════════════════════════════════════════════════ */ ?>
    <section class="rz-esg-cta" aria-labelledby="rz-esg-cta-heading">
        <div class="rz-esg-container">
            <div class="rz-esg-cta__inner rz-esg-animate">
                <h2 class="rz-esg-cta__heading" id="rz-esg-cta-heading">
                    <?php echo esc_html( $cta['heading'] ); ?>
                </h2>
                <p class="rz-esg-cta__body">
                    <?php echo esc_html( $cta['body'] ); ?>
                </p>
                <div class="rz-esg-cta__actions">
                    <a
                        href="<?php echo esc_url( $cta['btn_primary_url'] ); ?>"
                        class="rz-esg-btn rz-esg-btn--primary"
                        data-track-event="<?php echo esc_attr( $cta['btn_primary_track_event'] ); ?>"
                    >
                        <?php echo esc_html( $cta['btn_primary_label'] ); ?>
                    </a>
                    <a
                        href="<?php echo esc_url( $cta['btn_secondary_url'] ); ?>"
                        class="rz-esg-btn rz-esg-btn--secondary"
                        data-track-event="<?php echo esc_attr( $cta['btn_secondary_track_event'] ); ?>"
                    >
                        <?php echo esc_html( $cta['btn_secondary_label'] ); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

</div><!-- /.rz-esg -->
