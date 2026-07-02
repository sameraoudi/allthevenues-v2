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
  // Without JS the form is one scrollable page; with JS it steps through
  // fieldsets. Skipped when the server round-tripped with errors so all
  // messages stay visible.
  function initStepper() {
    var form = document.querySelector('[data-enq-form]');
    if (!form || form.hasAttribute('data-enq-errors')) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('.atv-enq-step'));
    var progress = document.querySelectorAll('[data-enq-progress] li');
    var btnNext = form.querySelector('[data-enq-next]');
    var btnBack = form.querySelector('[data-enq-back]');
    var btnSubmit = form.querySelector('.step-submit');
    if (steps.length < 2 || !btnNext || !btnBack || !btnSubmit) return;

    form.classList.add('js-stepper');
    var current = 0;

    function render() {
      steps.forEach(function (s, i) { s.classList.toggle('is-active', i === current); });
      for (var i = 0; i < progress.length; i++) {
        progress[i].classList.toggle('is-active', i === current);
        progress[i].classList.toggle('is-done', i < current);
      }
      // Use explicit values — the CSS defaults these to display:none.
      btnBack.style.display = current === 0 ? 'none' : 'inline-block';
      var last = current === steps.length - 1;
      btnNext.style.display = last ? 'none' : 'inline-block';
      btnSubmit.style.display = last ? 'inline-block' : 'none';
    }

    function validStep() {
      var fields = steps[current].querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i++) {
        if (!fields[i].checkValidity()) { fields[i].reportValidity(); return false; }
      }
      return true;
    }

    btnNext.addEventListener('click', function () {
      if (!validStep()) return;
      if (current < steps.length - 1) { current++; render(); form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
    btnBack.addEventListener('click', function () {
      if (current > 0) { current--; render(); form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    render();
  }

  if (document.readyState !== 'loading') initStepper();
  else document.addEventListener('DOMContentLoaded', initStepper);
})();
