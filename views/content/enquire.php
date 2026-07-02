<?php
declare(strict_types=1);

/** Enquiry stub content. Expects $venueName (?string), $venueId (int). */
/** @var ?string $venueName @var int $venueId */
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7 text-center">
      <div class="card shadow-sm">
        <div class="card-body p-5">
          <h1 class="h3 mb-3">Enquiry form — coming in the next update</h1>
          <?php if ($venueName !== null): ?>
            <p class="lead mb-2">You're enquiring about:</p>
            <p class="h5 mb-4"><?= e($venueName) ?></p>
          <?php endif; ?>
          <p class="text-muted mb-4">
            Our online enquiry form is on the way. In the meantime, keep exploring
            our venues.
          </p>
          <a href="<?= e(base_url('venues')) ?>" class="btn btn-primary">Back to venues</a>
        </div>
      </div>
    </div>
  </div>
</div>
