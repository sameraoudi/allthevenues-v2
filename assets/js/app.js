/* All The Venues — small progressive-enhancement helpers. Self-hosted
   (CSP script-src 'self'; no inline handlers). */
(function () {
  'use strict';

  // Delegated click handlers.
  document.addEventListener('click', function (ev) {
    // Mobile nav toggle.
    var navBtn = ev.target.closest('[data-nav-toggle]');
    if (navBtn) {
      var id = navBtn.getAttribute('aria-controls') || 'mainNav';
      var nav = document.getElementById(id);
      if (nav) {
        var open = nav.classList.toggle('open');
        navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
      return;
    }

    // Listing: mobile filters toggle.
    var fBtn = ev.target.closest('[data-filters-toggle]');
    if (fBtn) {
      var fs = document.getElementById('venuesFilters');
      if (fs) {
        var fopen = fs.classList.toggle('is-open');
        fBtn.setAttribute('aria-expanded', fopen ? 'true' : 'false');
      }
      return;
    }

    // Detail: gallery thumbnail → swap main image (and keep the hero's
    // lightbox index in sync so clicking it opens the shown image).
    var thumb = ev.target.closest('[data-full]');
    if (thumb) {
      var main = document.getElementById('vdMain');
      var full = thumb.getAttribute('data-full');
      if (main && full) {
        main.setAttribute('src', full);
        var ti = thumb.getAttribute('data-index');
        if (ti !== null) main.setAttribute('data-index', ti);
      }
      return;
    }

    // Detail: sticky tabs — show the matching panel.
    var tab = ev.target.closest('[data-tab]');
    if (tab) {
      ev.preventDefault();
      var key = tab.getAttribute('data-tab');
      var wrap = tab.closest('.atv-wrap') || document;
      var tabs = wrap.querySelectorAll('[data-tab]');
      for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.toggle('is-active', tabs[i] === tab);
      }
      var panels = wrap.querySelectorAll('[data-tab-panel]');
      for (var j = 0; j < panels.length; j++) {
        panels[j].classList.toggle('is-active', panels[j].getAttribute('data-tab-panel') === key);
      }
      return;
    }
  });

  // Listing: auto-submit the sort form on change (progressive enhancement;
  // a visible Sort button remains for no-JS).
  document.addEventListener('change', function (ev) {
    var sel = ev.target.closest('[data-autosubmit]');
    if (sel && sel.form) { sel.form.submit(); }
  });

  // Detail: image lightbox (self-hosted; no CDN). Opens from the hero image
  // and the "+N more" thumbnail; shows every venue image with prev/next,
  // Escape to close, and click-outside-the-image to close.
  (function () {
    var gallery = document.querySelector('[data-gallery][data-images]');
    var box = document.querySelector('[data-lightbox]');
    if (!gallery || !box) return;

    var images = [];
    try { images = JSON.parse(gallery.getAttribute('data-images') || '[]'); } catch (e) { images = []; }
    if (!images.length) return;

    var imgEl = box.querySelector('[data-lightbox-img]');
    var countEl = box.querySelector('[data-lightbox-count]');
    var cur = 0;

    function render() {
      var im = images[cur];
      if (!im || !imgEl) return;
      imgEl.setAttribute('src', im.src || '');
      imgEl.setAttribute('alt', im.alt || '');
      if (countEl) countEl.textContent = (cur + 1) + ' / ' + images.length;
    }
    function open(i) {
      cur = isNaN(i) ? 0 : Math.max(0, Math.min(images.length - 1, i));
      render();
      box.hidden = false;
      box.setAttribute('aria-hidden', 'false');
      box.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }
    function close() {
      box.classList.remove('is-open');
      box.hidden = true;
      box.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }
    function step(d) {
      cur = (cur + d + images.length) % images.length;
      render();
    }
    function isOpen() { return box.classList.contains('is-open'); }

    document.addEventListener('click', function (ev) {
      var opener = ev.target.closest('[data-lightbox-open]');
      if (opener && gallery.contains(opener)) {
        ev.preventDefault();
        open(parseInt(opener.getAttribute('data-index') || '0', 10));
        return;
      }
      if (!isOpen()) return;
      if (ev.target.closest('[data-lightbox-close]')) { close(); return; }
      if (ev.target.closest('[data-lightbox-prev]')) { step(-1); return; }
      if (ev.target.closest('[data-lightbox-next]')) { step(1); return; }
      // Click on the backdrop (not the image or a control) → close.
      if (ev.target === box) { close(); }
    });

    document.addEventListener('keydown', function (ev) {
      if (!isOpen()) return;
      if (ev.key === 'Escape') { close(); }
      else if (ev.key === 'ArrowLeft') { step(-1); }
      else if (ev.key === 'ArrowRight') { step(1); }
    });
  })();

  // Detail: enable JS tab mode (hide inactive panels) only when tabs exist.
  (function () {
    var tabsNav = document.querySelector('[data-tabs]');
    if (tabsNav) {
      var body = tabsNav.parentNode;
      if (body) body.classList.add('tabs-js');
    }
  })();

  // Enquiry form stepper (progressive enhancement).
  //
  // BASE (no JS / this fails): the form is one scrollable page — every step
  // visible, single Submit — because nothing is hidden until the .is-stepper
  // class is added below. If anything here throws, we remove .is-stepper so
  // the full form is restored. The stepper is skipped entirely when the
  // server round-tripped with validation errors (so all messages stay shown).
  function initStepper() {
    var form = document.querySelector('[data-enq-form]');
    if (!form || form.hasAttribute('data-enq-errors')) return;

    try {
      var steps = Array.prototype.slice.call(form.querySelectorAll('.atv-enq-step'));
      var progress = document.querySelectorAll('[data-enq-progress] li');
      var btnNext = form.querySelector('[data-enq-next]');
      var btnBack = form.querySelector('[data-enq-back]');
      var btnSubmit = form.querySelector('.step-submit');
      // Not enough to enhance — leave the plain scrollable form as-is.
      if (steps.length < 2 || !btnNext || !btnBack || !btnSubmit) return;

      var current = 0;

      function render() {
        for (var i = 0; i < steps.length; i++) {
          steps[i].classList.toggle('is-active', i === current);
        }
        for (var j = 0; j < progress.length; j++) {
          progress[j].classList.toggle('is-active', j === current);
          progress[j].classList.toggle('is-done', j < current);
        }
        // Explicit values — CSS defaults Next/Back to display:none.
        var last = current === steps.length - 1;
        btnBack.style.display = current === 0 ? 'none' : 'inline-block';
        btnNext.style.display = last ? 'none' : 'inline-block';
        btnSubmit.style.display = last ? 'inline-block' : 'none';
      }

      function validStep() {
        var fields = steps[current].querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
          if (typeof fields[i].checkValidity === 'function' && !fields[i].checkValidity()) {
            if (typeof fields[i].reportValidity === 'function') fields[i].reportValidity();
            return false;
          }
        }
        return true;
      }

      btnNext.addEventListener('click', function () {
        if (!validStep()) return;
        if (current < steps.length - 1) { current++; render(); scrollTop(form); }
      });
      btnBack.addEventListener('click', function () {
        if (current > 0) { current--; render(); scrollTop(form); }
      });

      // Enhancement is wired — switch on stepper mode and show step 1.
      form.classList.add('is-stepper');
      render();
    } catch (err) {
      // Any failure → revert to the full, submittable form.
      form.classList.remove('is-stepper');
      if (window.console && console.error) console.error('enquiry stepper disabled:', err);
    }
  }

  function scrollTop(el) {
    try { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* older browsers */ }
  }

  if (document.readyState !== 'loading') initStepper();
  else document.addEventListener('DOMContentLoaded', initStepper);
})();
