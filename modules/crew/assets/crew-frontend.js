/* Rezponz Crew – Frontend JavaScript */
(function () {
  'use strict';

  // Copy-to-clipboard
  document.querySelectorAll('.rzpz-crew-fe-copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const url = btn.dataset.url || btn.closest('.rzpz-crew-fe-link-copy')?.querySelector('input')?.value;
      if (!url) return;
      navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Kopieret!';
        setTimeout(() => btn.textContent = orig, 2000);
      }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        const orig = btn.textContent;
        btn.textContent = '✓ Kopieret!';
        setTimeout(() => btn.textContent = orig, 2000);
      });
    });
  });

  // Period selector
  const periodBtns = document.querySelectorAll('.rzpz-crew-fe-period-btn');
  periodBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const days = btn.dataset.days;
      if (!days) return;
      const url = new URL(window.location.href);
      url.searchParams.set('crew_days', days);
      window.location.href = url.toString();
    });
  });
})();
