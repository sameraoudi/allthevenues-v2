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
