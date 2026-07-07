<?php
declare(strict_types=1);

/** Delist-2 delist-request success. Expects $reference (string). No PII shown. */
/** @var string $reference */
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card atv-enq__success">
      <span class="atv-enq__tick" aria-hidden="true"><?= icon('check-circle') ?></span>
      <h1>Thanks — your delist request has been received</h1>
      <p class="atv-enq__intro">Our team will verify the request and follow up. The venue stays live until we&rsquo;ve confirmed and actioned it.</p>
      <p class="atv-enq__ref">Your reference<br><strong><?= e($reference) ?></strong></p>
      <p class="atv-enq__reassure">We&rsquo;ve emailed you a copy for your records. If you manage this venue in our provider portal, you can delist it yourself instantly.</p>
      <div class="atv-enq-nav atv-enq-nav--center">
        <a class="atv-btn" href="<?= e(base_url('/')) ?>">Back to home</a>
        <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/login')) ?>">Provider login</a>
      </div>
    </div>
  </div>
</section>
