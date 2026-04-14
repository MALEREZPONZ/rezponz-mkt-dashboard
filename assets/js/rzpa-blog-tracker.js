/* Rezponz Blog Tracker — rzpa-blog-tracker.js */
(function () {
  'use strict';

  var cfg = window.RZPA_TRACKER;
  if (!cfg || !cfg.postId || !cfg.apiUrl) return;

  // Prevent double-counting on same page load (e.g. hot-reload in dev)
  var sessionKey = 'rzpa_tracked_' + cfg.postId;
  if (sessionStorage.getItem(sessionKey)) return;
  sessionStorage.setItem(sessionKey, '1');

  var startTime = Date.now();
  var exitType  = 'unknown'; // 'internal' | 'external' | 'unknown'
  var sent      = false;

  // Intercept all link clicks to detect exit destination
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a') : null;
    if (!a || !a.href) return;
    try {
      var url = new URL(a.href, window.location.href);
      if (url.hostname === window.location.hostname) {
        exitType = 'internal';
      } else if (url.protocol === 'http:' || url.protocol === 'https:') {
        exitType = 'external';
      }
    } catch (err) {}
  }, true);

  function sendBeacon() {
    if (sent) return;
    sent = true;
    var duration = Math.max(1, Math.round((Date.now() - startTime) / 1000));
    // Cap at 1 hour to filter bot/idle sessions
    if (duration > 3600) duration = 3600;
    var payload = JSON.stringify({
      post_id:   cfg.postId,
      duration:  duration,
      exit_type: exitType,
    });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(cfg.apiUrl, new Blob([payload], { type: 'application/json' }));
    } else {
      try {
        fetch(cfg.apiUrl, {
          method:    'POST',
          body:      payload,
          headers:   { 'Content-Type': 'application/json' },
          keepalive: true,
        });
      } catch (err) {}
    }
  }

  // Send on tab hide / page unload
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') sendBeacon();
  });
  window.addEventListener('pagehide', sendBeacon);

})();
