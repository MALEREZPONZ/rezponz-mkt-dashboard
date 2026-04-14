/**
 * ESG Module — Frontend JavaScript
 *
 * IIFE — no global scope pollution. All interactions are driven by
 * data-* attributes set in the PHP template; no IDs or classes are
 * hard-coded here beyond what the template guarantees.
 *
 * Features:
 *  - Counter animation (hero KPI)            data-counter, data-counter-suffix, data-counter-duration
 *  - Scroll-reveal via IntersectionObserver  .rz-esg-animate → .rz-esg-visible
 *  - Action card expand / collapse           .rz-esg-action-card__toggle
 *  - FAQ accordion (one open at a time)      .rz-esg-faq__question-btn
 *  - Metrics toggle (Sådan måler vi)         .rz-esg-metrics__toggle
 *  - CTA & general data-track-event clicks
 *  - Roadmap viewport entry (fire once)
 *
 * Tracking dispatcher:
 *  - GA4    (window.gtag)
 *  - GTM    (window.dataLayer)
 *  - Custom DOM event 'rz_esg' on document
 *
 * @package    RezponzAnalytics
 * @subpackage ESG
 * @since      3.5.9
 */

(function () {
    'use strict';

    // ──────────────────────────────────────────────────────────────────────────
    // Data reference from PHP (wp_localize_script)
    // ──────────────────────────────────────────────────────────────────────────
    var DATA = window.RZ_ESG_DATA || {};

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the user prefers reduced motion.
     * @returns {boolean}
     */
    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Returns a numeric element's data-delay attribute (in ms) or 0.
     * @param {Element} el
     * @returns {number}
     */
    function getDelay(el) {
        return parseInt(el.dataset.delay || '0', 10);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tracking dispatcher
    // Easy to extend: add GA4, GTM, custom endpoint here.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fire a tracking event through all available channels.
     * @param {string} event   - Event name, e.g. 'esg_action_open'
     * @param {Object} payload - Extra key/value pairs
     */
    function track(event, payload) {
        payload = payload || {};

        // Google Analytics 4
        if (typeof window.gtag === 'function') {
            window.gtag('event', event, payload);
        }

        // Google Tag Manager dataLayer
        if (Array.isArray(window.dataLayer)) {
            var gtmPayload = Object.assign({ event: event }, payload);
            window.dataLayer.push(gtmPayload);
        }

        // Custom DOM event — catch with: document.addEventListener('rz_esg', fn)
        document.dispatchEvent(new CustomEvent('rz_esg', {
            bubbles: true,
            detail: Object.assign({ event: event }, payload)
        }));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Counter animation (hero KPI)
    // Reads: data-counter, data-counter-suffix, data-counter-duration
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Animate a numeric counter from 0 to its target value.
     * Skips animation entirely when prefers-reduced-motion is set.
     * @param {Element} el
     */
    function runCounter(el) {
        var target   = parseInt(el.dataset.counter || '0', 10);
        var suffix   = el.dataset.counterSuffix || '';
        var duration = parseInt(el.dataset.counterDuration || '1500', 10);

        // Instant display if motion is reduced
        if (prefersReducedMotion()) {
            el.textContent = target + suffix;
            return;
        }

        var start     = null;
        var startVal  = 0;

        function step(timestamp) {
            if (!start) start = timestamp;
            var progress = Math.min((timestamp - start) / duration, 1);
            // Ease-out cubic
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = Math.round(startVal + (target - startVal) * eased);
            el.textContent = current + suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target + suffix;
                // Subtle pop animation via CSS class
                el.classList.add('rz-esg-counter--done');
                setTimeout(function () {
                    el.classList.remove('rz-esg-counter--done');
                }, 350);
            }
        }

        requestAnimationFrame(step);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IntersectionObserver — scroll reveal + counter trigger
    // ──────────────────────────────────────────────────────────────────────────

    function initScrollReveal() {
        if (!('IntersectionObserver' in window)) {
            // Fallback: make everything visible immediately
            document.querySelectorAll('.rz-esg-animate').forEach(function (el) {
                el.classList.add('rz-esg-visible');
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;

                var el    = entry.target;
                var delay = getDelay(el);

                setTimeout(function () {
                    el.classList.add('rz-esg-visible');

                    // Trigger counter inside this element (if any)
                    el.querySelectorAll('[data-counter]').forEach(runCounter);

                    // Also trigger if this element itself is the counter
                    if (el.dataset.counter !== undefined) {
                        runCounter(el);
                    }
                }, delay);

                observer.unobserve(el);
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -40px 0px'
        });

        document.querySelectorAll('.rz-esg-animate').forEach(function (el) {
            observer.observe(el);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Action card expand / collapse
    // ──────────────────────────────────────────────────────────────────────────

    function initActionCards() {
        var toggles = document.querySelectorAll('.rz-esg-action-card__toggle');
        var closeBtns = document.querySelectorAll('.rz-esg-action-card__close');

        /**
         * Open a card's panel.
         * @param {Element} toggle
         */
        function openCard(toggle) {
            var panelId = toggle.getAttribute('aria-controls');
            var panel   = document.getElementById(panelId);
            if (!panel) return;

            toggle.setAttribute('aria-expanded', 'true');
            toggle.querySelector('.rz-esg-action-card__toggle-label').textContent = 'Skjul';
            panel.removeAttribute('hidden');

            track('esg_action_open', {
                action_id: toggle.dataset.actionId || ''
            });
        }

        /**
         * Close a card's panel.
         * @param {Element} toggle
         */
        function closeCard(toggle) {
            var panelId = toggle.getAttribute('aria-controls');
            var panel   = document.getElementById(panelId);
            if (!panel) return;

            toggle.setAttribute('aria-expanded', 'false');
            toggle.querySelector('.rz-esg-action-card__toggle-label').textContent = 'Læs mere';

            // Wait for CSS transition before hiding
            panel.addEventListener('transitionend', function handler() {
                panel.setAttribute('hidden', '');
                panel.removeEventListener('transitionend', handler);
            });
            // Trigger transition by removing max-height source
            panel.setAttribute('hidden', '');
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                if (isExpanded) {
                    closeCard(toggle);
                } else {
                    openCard(toggle);
                }
            });
        });

        closeBtns.forEach(function (closeBtn) {
            closeBtn.addEventListener('click', function () {
                var panelId  = closeBtn.getAttribute('aria-controls');
                var actionId = closeBtn.dataset.closesAction;
                var toggle   = document.querySelector(
                    '[data-action-id="' + actionId + '"].rz-esg-action-card__toggle'
                );
                if (toggle) closeCard(toggle);
            });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FAQ accordion (one item open at a time)
    // ──────────────────────────────────────────────────────────────────────────

    function initFaq() {
        var buttons = document.querySelectorAll('.rz-esg-faq__question-btn');

        /**
         * Close a single FAQ answer by its button.
         * @param {Element} btn
         */
        function closeAnswer(btn) {
            var answerId = btn.getAttribute('aria-controls');
            var answer   = document.getElementById(answerId);
            if (!answer) return;

            btn.setAttribute('aria-expanded', 'false');
            answer.setAttribute('hidden', '');
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var isExpanded = btn.getAttribute('aria-expanded') === 'true';

                // Close all others first (one-open-at-a-time)
                buttons.forEach(function (other) {
                    if (other !== btn) closeAnswer(other);
                });

                if (isExpanded) {
                    closeAnswer(btn);
                } else {
                    // Open
                    var answerId = btn.getAttribute('aria-controls');
                    var answer   = document.getElementById(answerId);
                    if (!answer) return;

                    btn.setAttribute('aria-expanded', 'true');
                    answer.removeAttribute('hidden');

                    track('esg_faq_open', {
                        question_id:    btn.dataset.questionId    || '',
                        question_index: btn.dataset.questionIndex || ''
                    });
                }
            });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Metrics toggle (Sådan måler vi)
    // ──────────────────────────────────────────────────────────────────────────

    function initMetricsToggle() {
        var toggleBtn = document.querySelector('.rz-esg-metrics__toggle');
        if (!toggleBtn) return;

        var panelId = toggleBtn.getAttribute('aria-controls');
        var panel   = document.getElementById(panelId);
        if (!panel) return;

        var labelOpen  = toggleBtn.dataset.labelOpen  || 'Sådan måler vi';
        var labelClose = toggleBtn.dataset.labelClose || 'Skjul forklaringer';

        toggleBtn.addEventListener('click', function () {
            var isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                toggleBtn.setAttribute('aria-expanded', 'false');
                panel.setAttribute('hidden', '');
                // Restore button text (keep arrow span)
                toggleBtn.childNodes[0].textContent = labelOpen + ' ';
                track('esg_metrics_toggle', { state: 'close' });
            } else {
                toggleBtn.setAttribute('aria-expanded', 'true');
                panel.removeAttribute('hidden');
                toggleBtn.childNodes[0].textContent = labelClose + ' ';
                track('esg_metrics_toggle', { state: 'open' });
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generic data-track-event click tracking
    // Covers CTA buttons, action card views, etc.
    // ──────────────────────────────────────────────────────────────────────────

    function initTrackingClicks() {
        var root = document.getElementById('rz-esg');
        if (!root) return;

        root.addEventListener('click', function (e) {
            var el = e.target.closest('[data-track-event]');
            if (!el) return;

            var eventName = el.dataset.trackEvent;
            // Skip events handled by dedicated init functions above
            if (
                eventName === 'esg_action_open'  ||
                eventName === 'esg_faq_open'      ||
                eventName === 'esg_metrics_toggle'
            ) return;

            track(eventName, {
                label: el.textContent.trim().slice(0, 80)
            });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Roadmap visibility tracking (fire once on section enter)
    // ──────────────────────────────────────────────────────────────────────────

    function initRoadmapTracking() {
        var section = document.getElementById('milepæle');
        if (!section || !('IntersectionObserver' in window)) return;

        var fired = false;
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !fired) {
                    fired = true;
                    track('esg_roadmap_viewed', {});
                    obs.unobserve(section);
                }
            });
        }, { threshold: 0.2 });

        obs.observe(section);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Boot — run after DOM is ready
    // ──────────────────────────────────────────────────────────────────────────

    function boot() {
        // Only run if the ESG module is on this page
        if (!document.getElementById('rz-esg')) return;

        initScrollReveal();
        initActionCards();
        initFaq();
        initMetricsToggle();
        initTrackingClicks();
        initRoadmapTracking();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
