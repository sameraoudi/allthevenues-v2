<?php
declare(strict_types=1);

/**
 * Admin "Add venue" create form. Shares the field panels with the edit form via
 * _venue-fields.php. Creating INSERTs a draft then redirects to the edit page
 * (where the image manager lives — a venue id must exist first).
 * Expects $old, $errors, $venueTypes, $emirates, $partners in scope.
 */
/** @var array $old @var array $errors @var array $venueTypes @var array $emirates @var array $partners */
?>
<p><a class="lead-back" href="<?= e(base_url('admin/venues')) ?>">&larr; Back to venues</a></p>

<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('admin/venues/new')) ?>" novalidate>
  <?php csrf_field(); ?>

  <?php require __DIR__ . '/_venue-fields.php'; ?>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn">Create venue</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/venues')) ?>">Cancel</a>
  </div>
  <p class="lead-hint">Save the venue first, then you can upload images and set the primary on the edit screen.</p>
</form>
