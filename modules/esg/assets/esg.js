/**
 * ESG Module — Frontend JavaScript  v3.5.46 (Polish)
 *
 * IIFE — no global scope pollution.
 *
 * Features:
 *  - Scroll progress bar
 *  - Counter animation with expo easing      data-counter, data-counter-suffix, data-counter-duration
 *  - Chip value count-up                     .rz-esg-chip__value
 *  - Scroll-reveal via IntersectionObserver  .rz-esg-animate → .rz-esg-visible
 *  - Section heading word-split reveal       .rz-esg-section-heading
 *  - Roadmap line draw-in                    .rz-esg-roadmap__track
 *  - Milestone staggered entrance            .rz-esg-milestone
 *  - Action card expand / collapse
 *  - 3D perspective tilt on case cards
 *  - FAQ accordion (one open at a time) + open class
 *  - Metrics toggle
 *  - CTA & general data-track-event clicks
 *  - Roadmap viewport entry (fire once)
 *
 * @package    RezponzAnalytics
 * @subpackage ESG
 * @since      3.5.9 / polished 3.5.46
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

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function getDelay(el) {
        return parseInt(el.dataset.delay || '0', 10);
    }

    function easeOutExpo(t) {
        return t >= 1 ? 1 : 1 - Math.pow(2, -10 * t);
    }

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tracking dispatcher
    // ──────────────────────────────────────────────────────────────────────────

    function track(event, payload) {
        payload = payload || {};
        if (typeof window.gtag === 'function') {
            window.gtag('event', event, payload);
        }
        if (Array.isArray(window.dataLayer)) {
            window.dataLayer.push(Object.assign({ event: event }, payload));
        }
        document.dispatchEvent(new CustomEvent('rz_esg', {
            bubbles: true,
            detail: Object.assign({ event: event }, payload)
        }));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scroll progress bar
    // ──────────────────────────────────────────────────────────────────────────

    function initProgressBar() {
        var bar = document.createElement('div');
        bar.className = 'rz-esg-progress-bar';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        function update() {
            var scrollTop  = window.scrollY || window.pageYOffset;
            var docHeight  = document.documentElement.scrollHeight - window.innerHeight;
            var pct        = docHeight > 0 ? (scrollTop / docHeight * 100) : 0;
            bar.style.width = Math.min(pct, 100).toFixed(2) + '%';
        }
        window.addEventListener('scroll', update, { passive: true });
        update();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Counter animation (expo easing, spring finish)
    // ──────────────────────────────────────────────────────────────────────────

    function runCounter(el) {
        if (el._counterDone) return;
        el._counterDone = true;

        var raw      = el.dataset.counter || '0';
        var target   = parseFloat(raw);
        var suffix   = el.dataset.counterSuffix || '';
        var duration = parseInt(el.dataset.counterDuration || '1800', 10);
        var isFloat  = raw.indexOf('.') !== -1;

        if (prefersReducedMotion()) {
            el.textContent = (isFloat ? target.toFixed(1) : target) + suffix;
            return;
        }

        var start = null;

        function step(ts) {
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var eased    = easeOutExpo(progress);
            var current  = target * eased;
            el.textContent = (isFloat ? current.toFixed(1) : Math.round(current)) + suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = (isFloat ? target.toFixed(1) : target) + suffix;
                el.classList.add('rz-esg-counter--done');
                setTimeout(function () { el.classList.remove('rz-esg-counter--done'); }, 400);
            }
        }
        requestAnimationFrame(step);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Chip value count-up (runs when chip scrolls into view)
    // ──────────────────────────────────────────────────────────────────────────

    function initChipCounters() {
        if (prefersReducedMotion()) return;

        var chips = document.querySelectorAll('.rz-esg-chip__value');
        if (!chips.length || !('IntersectionObserver' in window)) return;

        chips.forEach(function (el) {
            var text  = el.textContent.trim();
            var match = text.match(/^([\d.,]+)(.*)/);
            if (!match) return;
            var numStr  = match[1].replace(',', '.');
            var num     = parseFloat(numStr);
            var rest    = match[2] || '';
            var isFloat = numStr.indexOf('.') !== -1;
            if (isNaN(num) || num === 0) return;

            el.dataset.chipTarget  = num;
            el.dataset.chipSuffix  = rest;
            el.dataset.chipFloat   = isFloat ? '1' : '0';
            el.dataset.chipDone    = 'false';

            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting || el.dataset.chipDone === 'true') return;
                    el.dataset.chipDone = 'true';
                    obs.unobserve(el);

                    var t       = parseFloat(el.dataset.chipTarget);
                    var s       = el.dataset.chipSuffix;
                    var float   = el.dataset.chipFloat === '1';
                    var dur     = 1500;
                    var started = null;

                    function step(ts) {
                        if (!started) started = ts;
                        var p = Math.min((ts - started) / dur, 1);
                        var v = t * easeOutCubic(p);
                        el.textContent = (float ? v.toFixed(1) : Math.round(v)) + s;
                        if (p < 1) requestAnimationFrame(step);
                        else el.textContent = (float ? t.toFixed(1) : t) + s;
                    }
                    requestAnimationFrame(step);
                });
            }, { threshold: 0.6 });
            obs.observe(el);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Section heading word-split reveal
    // ──────────────────────────────────────────────────────────────────────────

    function initWordReveal() {
        if (prefersReducedMotion()) return;

        var selectors = [
            '.rz-esg-tracks .rz-esg-section-heading',
            '.rz-esg-roadmap .rz-esg-section-heading',
            '.rz-esg-actions .rz-esg-section-heading',
            '.rz-esg-cases .rz-esg-section-heading',
            '.rz-esg-faq .rz-esg-section-heading',
            '.rz-esg-metrics .rz-esg-section-heading'
        ];

        document.querySelectorAll(selectors.join(',')).forEach(function (h) {
            var words = h.textContent.trim().split(/\s+/);
            h.innerHTML = words.map(function (w, i) {
                return '<span class="rz-word"><span class="rz-word-inner" style="transition-delay:' + (i * 70) + 'ms">' +
                    w.replace(/&/g, '&amp;').replace(/</g, '&lt;') +
                    '</span></span>';
            }).join(' ');
            // Preserve the ::before pseudo-element (accent bar) by keeping heading structure
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IntersectionObserver — scroll reveal + counter trigger
    // ──────────────────────────────────────────────────────────────────────────

    function initScrollReveal() {
        if (!('IntersectionObserver' in window)) {
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
                    el.querySelectorAll('[data-counter]').forEach(runCounter);
                    if (el.dataset.counter !== undefined) runCounter(el);
                }, delay);
                observer.unobserve(el);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -32px 0px' });

        document.querySelectorAll('.rz-esg-animate').forEach(function (el) {
            observer.observe(el);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Roadmap — line draw-in on scroll
    // ──────────────────────────────────────────────────────────────────────────

    function initRoadmapLine() {
        var track = document.querySelector('.rz-esg-roadmap__track');
        if (!track || !('IntersectionObserver' in window)) return;

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                track.classList.add('rz-esg-line-drawn');
                obs.unobserve(track);
            });
        }, { threshold: 0.1 });
        obs.observe(track);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Milestone staggered entrance
    // ──────────────────────────────────────────────────────────────────────────

    function initMilestones() {
        if (prefersReducedMotion()) return;

        var milestones = document.querySelectorAll('.rz-esg-milestone');
        if (!milestones.length || !('IntersectionObserver' in window)) return;

        milestones.forEach(function (m) { m.classList.add('rz-esg-ms-hidden'); });

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                var m   = e.target;
                var idx = Array.prototype.indexOf.call(milestones, m);
                setTimeout(function () {
                    m.classList.remove('rz-esg-ms-hidden');
                }, idx * 160);
                obs.unobserve(m);
            });
        }, { threshold: 0.15 });

        milestones.forEach(function (m) { obs.observe(m); });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3D perspective tilt on case cards
    // ──────────────────────────────────────────────────────────────────────────

    function initTilt() {
        if (prefersReducedMotion()) return;

        var cards = document.querySelectorAll('.rz-esg-case-card');
        cards.forEach(function (card) {
            var raf = null;
            var tx = 0, ty = 0;

            card.addEventListener('mousemove', function (e) {
                if (raf) cancelAnimationFrame(raf);
                raf = requestAnimationFrame(function () {
                    var rect  = card.getBoundingClientRect();
                    var x     = (e.clientX - rect.left) / rect.width  - .5;
                    var y     = (e.clientY - rect.top)  / rect.height - .5;
                    tx = x * 9;
                    ty = -y * 6;
                    card.style.transform =
                        'perspective(900px) rotateY(' + tx + 'deg) rotateX(' + ty + 'deg) translateY(-6px) scale(1.01)';
                    card.style.boxShadow =
                        (20 + tx * 2) + 'px ' + (24 + ty * 2) + 'px 60px rgba(15,28,46,.18)';
                });
            });

            card.addEventListener('mouseleave', function () {
                if (raf) cancelAnimationFrame(raf);
                card.style.transform  = '';
                card.style.boxShadow  = '';
            });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Action card expand / collapse
    // ──────────────────────────────────────────────────────────────────────────

    function initActionCards() {
        var toggles   = document.querySelectorAll('.rz-esg-action-card__toggle');
        var closeBtns = document.querySelectorAll('.rz-esg-action-card__close');

        function openCard(toggle) {
            var panel = document.getElementById(toggle.getAttribute('aria-controls'));
            if (!panel) return;
            toggle.setAttribute('aria-expanded', 'true');
            toggle.querySelector('.rz-esg-action-card__toggle-label').textContent = 'Skjul';
            panel.removeAttribute('hidden');
            track('esg_action_open', { action_id: toggle.dataset.actionId || '' });
        }

        function closeCard(toggle) {
            var panel = document.getElementById(toggle.getAttribute('aria-controls'));
            if (!panel) return;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.querySelector('.rz-esg-action-card__toggle-label').textContent = 'Læs mere';
            panel.addEventListener('transitionend', function h() {
                panel.setAttribute('hidden', '');
                panel.removeEventListener('transitionend', h);
            });
            panel.setAttribute('hidden', '');
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                isExpanded ? closeCard(toggle) : openCard(toggle);
            });
        });

        closeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var actionId = btn.dataset.closesAction;
                var toggle   = document.querySelector('[data-action-id="' + actionId + '"].rz-esg-action-card__toggle');
                if (toggle) closeCard(toggle);
            });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FAQ accordion (one open at a time) + open class for border accent
    // ──────────────────────────────────────────────────────────────────────────

    function initFaq() {
        var buttons = document.querySelectorAll('.rz-esg-faq__question-btn');

        function closeAnswer(btn) {
            var answer = document.getElementById(btn.getAttribute('aria-controls'));
            if (!answer) return;
            btn.setAttribute('aria-expanded', 'false');
            answer.setAttribute('hidden', '');
            var item = btn.closest('.rz-esg-faq__item');
            if (item) item.classList.remove('rz-esg-faq-open');
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var isExpanded = btn.getAttribute('aria-expanded') === 'true';
                buttons.forEach(function (other) { if (other !== btn) closeAnswer(other); });

                if (isExpanded) {
                    closeAnswer(btn);
                } else {
                    var answer = document.getElementById(btn.getAttribute('aria-controls'));
                    if (!answer) return;
                    btn.setAttribute('aria-expanded', 'true');
                    answer.removeAttribute('hidden');
                    var item = btn.closest('.rz-esg-faq__item');
                    if (item) item.classList.add('rz-esg-faq-open');
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
        var panel      = document.getElementById(toggleBtn.getAttribute('aria-controls'));
        if (!panel) return;
        var labelOpen  = toggleBtn.dataset.labelOpen  || 'Sådan måler vi';
        var labelClose = toggleBtn.dataset.labelClose || 'Skjul forklaringer';

        toggleBtn.addEventListener('click', function () {
            var isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            if (isExpanded) {
                toggleBtn.setAttribute('aria-expanded', 'false');
                panel.setAttribute('hidden', '');
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
    // ──────────────────────────────────────────────────────────────────────────

    function initTrackingClicks() {
        var root = document.getElementById('rz-esg');
        if (!root) return;
        root.addEventListener('click', function (e) {
            var el = e.target.closest('[data-track-event]');
            if (!el) return;
            var eventName = el.dataset.trackEvent;
            if (eventName === 'esg_action_open' || eventName === 'esg_faq_open' || eventName === 'esg_metrics_toggle') return;
            track(eventName, { label: el.textContent.trim().slice(0, 80) });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Roadmap visibility tracking (fire once on section enter)
    // ──────────────────────────────────────────────────────────────────────────

    function initRoadmapTracking() {
        var section = document.getElementById('milepæle');
        if (!section || !('IntersectionObserver' in window)) return;
        var fired = false;
        var obs   = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting && !fired) {
                    fired = true;
                    track('esg_roadmap_viewed', {});
                    obs.unobserve(section);
                }
            });
        }, { threshold: 0.2 });
        obs.observe(section);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Milestone progress rings (animated SVG, injected right side)
    // ──────────────────────────────────────────────────────────────────────────

    function initMilestoneRings() {
        if (!('IntersectionObserver' in window)) return;

        var PROGRESS = { done: 100, ongoing: 65, next: 15, target: 0 };
        var COLOR    = {
            done:    '#70C898',
            ongoing: '#E8A84A',
            next:    '#70A0D0',
            target:  '#C070C8'
        };
        var GLOW = {
            done:    'rgba(112,200,152,.35)',
            ongoing: 'rgba(232,168,74,.35)',
            next:    'rgba(112,160,208,.35)',
            target:  'rgba(192,112,200,.35)'
        };

        var R    = 22;
        var CX   = 28;
        var CY   = 28;
        var SIZE = 56;
        var CIRC = 2 * Math.PI * R; // ≈ 138.23

        var milestones = document.querySelectorAll('.rz-esg-milestone');
        if (!milestones.length) return;

        milestones.forEach(function (el) {
            // Determine status from class list
            var status = 'next';
            el.classList.forEach(function (c) {
                if (c.startsWith('rz-esg-milestone--')) {
                    status = c.replace('rz-esg-milestone--', '');
                }
            });

            var pct    = PROGRESS[status] !== undefined ? PROGRESS[status] : 0;
            var color  = COLOR[status]  || '#888';
            var glow   = GLOW[status]   || 'rgba(136,136,136,.3)';
            var offset = CIRC - (pct / 100) * CIRC;

            // Build SVG ring
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'rz-esg-ring');
            svg.setAttribute('width', SIZE);
            svg.setAttribute('height', SIZE);
            svg.setAttribute('viewBox', '0 0 ' + SIZE + ' ' + SIZE);
            svg.setAttribute('aria-hidden', 'true');
            svg.dataset.status = status;

            // Track circle
            var track = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            track.setAttribute('cx', CX);
            track.setAttribute('cy', CY);
            track.setAttribute('r',  R);
            track.setAttribute('fill', 'none');
            track.setAttribute('stroke', 'rgba(255,255,255,.07)');
            track.setAttribute('stroke-width', '2.5');

            // Progress circle
            var prog = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            prog.setAttribute('class', 'rz-esg-ring__arc');
            prog.setAttribute('cx', CX);
            prog.setAttribute('cy', CY);
            prog.setAttribute('r',  R);
            prog.setAttribute('fill', 'none');
            prog.setAttribute('stroke', color);
            prog.setAttribute('stroke-width', '2.5');
            prog.setAttribute('stroke-linecap', 'round');
            prog.setAttribute('stroke-dasharray', CIRC.toFixed(3));
            prog.setAttribute('stroke-dashoffset', CIRC.toFixed(3)); // start hidden
            prog.setAttribute('transform', 'rotate(-90 ' + CX + ' ' + CY + ')');
            prog.dataset.targetOffset = offset.toFixed(3);
            prog.dataset.color = color;

            // Percentage label
            var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', CX);
            text.setAttribute('y', CY);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dominant-baseline', 'middle');
            text.setAttribute('fill', color);
            text.setAttribute('font-family', "'JetBrains Mono', monospace");
            text.setAttribute('font-size', '9.5');
            text.setAttribute('font-weight', '500');
            text.setAttribute('letter-spacing', '-0.03em');
            text.setAttribute('opacity', '0');
            text.setAttribute('class', 'rz-esg-ring__text');
            text.textContent = pct + '%';

            svg.appendChild(track);
            svg.appendChild(prog);
            svg.appendChild(text);
            el.appendChild(svg);

            // Glow filter (inline, unique id per milestone)
            var filterId = 'rz-glow-' + status + '-' + Math.random().toString(36).slice(2, 7);
            var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            var filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
            filter.setAttribute('id', filterId);
            filter.setAttribute('x', '-50%');
            filter.setAttribute('y', '-50%');
            filter.setAttribute('width', '200%');
            filter.setAttribute('height', '200%');
            var feGaussian = document.createElementNS('http://www.w3.org/2000/svg', 'feGaussianBlur');
            feGaussian.setAttribute('stdDeviation', '2.5');
            feGaussian.setAttribute('result', 'blur');
            filter.appendChild(feGaussian);
            defs.appendChild(filter);
            svg.insertBefore(defs, svg.firstChild);

            // Glow duplicate arc (behind)
            var glow_arc = prog.cloneNode(false);
            glow_arc.setAttribute('stroke', color);
            glow_arc.setAttribute('stroke-width', '4');
            glow_arc.setAttribute('opacity', '0.4');
            glow_arc.setAttribute('filter', 'url(#' + filterId + ')');
            glow_arc.setAttribute('class', 'rz-esg-ring__glow');
            glow_arc.removeAttribute('class');
            glow_arc.setAttribute('class', 'rz-esg-ring__glow');
            svg.insertBefore(glow_arc, prog);
        });

        // Animate rings as milestones enter viewport
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;

                var ring = e.target.querySelector('.rz-esg-ring');
                if (!ring || ring.dataset.animated) return;
                ring.dataset.animated = '1';

                var arc   = ring.querySelector('.rz-esg-ring__arc');
                var glow  = ring.querySelector('.rz-esg-ring__glow');
                var label = ring.querySelector('.rz-esg-ring__text');
                if (!arc) return;

                var targetOffset = parseFloat(arc.dataset.targetOffset);
                var DURATION     = prefersReducedMotion() ? 0 : 1100;
                var startTime    = null;
                var startOffset  = CIRC;

                function animateArc(ts) {
                    if (!startTime) startTime = ts;
                    var progress = Math.min((ts - startTime) / DURATION, 1);
                    var eased    = easeOutExpo(progress);
                    var current  = startOffset + (targetOffset - startOffset) * eased;

                    arc.setAttribute('stroke-dashoffset', current.toFixed(3));
                    if (glow) glow.setAttribute('stroke-dashoffset', current.toFixed(3));

                    // Fade label in at 60% through animation
                    if (label && progress > 0.6) {
                        var labelOpacity = Math.min((progress - 0.6) / 0.4, 1);
                        label.setAttribute('opacity', labelOpacity.toFixed(3));
                    }

                    if (progress < 1) {
                        requestAnimationFrame(animateArc);
                    }
                }

                // Small delay per milestone stagger (uses position in list)
                var allMilestones = Array.from(document.querySelectorAll('.rz-esg-milestone'));
                var idx           = allMilestones.indexOf(e.target);
                var delay         = idx * 120;

                setTimeout(function () {
                    requestAnimationFrame(animateArc);
                }, delay);

                obs.unobserve(e.target);
            });
        }, { threshold: 0.25 });

        milestones.forEach(function (el) { obs.observe(el); });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    function boot() {
        if (!document.getElementById('rz-esg')) return;

        // Run word-reveal BEFORE scroll-reveal so words are already split
        // when the IntersectionObserver fires
        initWordReveal();

        initProgressBar();
        initScrollReveal();
        initChipCounters();
        initRoadmapLine();
        initMilestones();
        initMilestoneRings();
        initActionCards();
        initTilt();
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
