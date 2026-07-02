/* All The Venues — small progressive-enhancement helpers. Self-hosted
   (CSP script-src 'self'; no inline handlers). */
(function () {
  'use strict';

  // Mobile nav toggle.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-nav-toggle]');
    if (!btn) return;
    var id = btn.getAttribute('aria-controls') || 'mainNav';
    var nav = document.getElementById(id);
    if (!nav) return;
    var open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
