<?php
declare(strict_types=1);

/** Contact success content (#7b). Expects $reference (string). No PII shown. */
/** @var string $reference */
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card atv-enq__success">
      <span class="atv-enq__tick" aria-hidden="true"><?= icon('check-circle') ?></span>
      <h1>Thanks — your message has been received</h1>
      <p class="atv-enq__intro">Our team will read your message and get back to you shortly.</p>
      <p class="atv-enq__ref">Your reference<br><strong><?= e($reference) ?></strong></p>
      <p class="atv-enq__reassure">We've emailed you a copy for your records. Your details stay private —
        we never publish them.</p>
      <div class="atv-enq-nav atv-enq-nav--center">
        <a class="atv-btn" href="<?= e(base_url('venues')) ?>">Browse venues</a>
        <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('/')) ?>">Back to home</a>
      </div>
    </div>
  </div>
</section>
