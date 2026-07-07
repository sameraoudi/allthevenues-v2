<?php
declare(strict_types=1);

/**
 * #3 U-P9b — shared "Event types" fieldset for the portal Submit + Edit venue
 * forms. Built to docs/atv-portal-eventtypes-preview.html. Two groups (Primary /
 * Additional) of checkboxes name="event_types[]". On a PUBLISHED venue the tags
 * are READ-ONLY (governed — changes go through review). Expects in scope: $pdo,
 * int[] $etChecked, bool $etPublished, int $etVid. Escapes everything.
 */
/** @var PDO $pdo @var array $etChecked @var bool $etPublished @var int $etVid */
$etChecked   = array_map('intval', $etChecked ?? []);
$etPublished = !empty($etPublished);
$etVid       = (int)($etVid ?? 0);

$all     = venue_event_types($pdo);   // active types (id, name, slug)
$primary = array_flip(portal_event_type_primary_slugs());
$groups  = ['primary' => [], 'additional' => []];
foreach ($all as $et) {
    $groups[isset($primary[(string)$et['slug']]) ? 'primary' : 'additional'][] = $et;
}
$isChecked = static fn(int $id): bool => in_array($id, $etChecked, true);
?>
<div class="admin-panel">
  <h2 class="admin-panel__title">Event types</h2>

  <?php if ($etPublished): ?>
    <p class="lead-hint mb-2">These event types are live on your public listing.</p>
    <?php
      $names = [];
      foreach ($all as $et) { if ($isChecked((int)$et['id'])) { $names[] = (string)$et['name']; } }
    ?>
    <?php if ($names): ?>
      <div class="atv-card__chips mb-2">
        <?php foreach ($names as $n): ?><span class="atv-chip"><?= e($n) ?></span><?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="lead-hint mb-2"><span class="text-muted">No event types set.</span></p>
    <?php endif; ?>
    <p class="lead-hint">Event types on a published venue are reviewed before changes go live.
      <a href="<?= e(base_url('portal/venues/' . $etVid . '/request')) ?>">Request a change</a> and our team will update them.</p>

  <?php else: ?>
    <label class="et-lbl">What kinds of events does this venue host? <span class="et-req">needed to publish</span></label>
    <p class="lead-hint">Choose all that genuinely apply. These tags help planners find your venue through search, event-type pages, and enquiry matching. At least one event type is required before All The Venues can publish the listing.</p>
    <p class="lead-hint et-recommend">Choose the event types this venue is genuinely suitable for, not every possible use. <strong>Recommended: 3&ndash;6 event types.</strong></p>

    <div class="et-field" data-et-field>
      <?php foreach (['primary' => 'Primary event types', 'additional' => 'Additional suitable uses'] as $key => $heading): ?>
        <?php if ($groups[$key]): ?>
          <div class="et-grp-h"><?= e($heading) ?></div>
          <div class="et-grid">
            <?php foreach ($groups[$key] as $et): $eid = (int)$et['id']; ?>
              <label class="et-chk"><input type="checkbox" name="event_types[]" value="<?= $eid ?>"<?= $isChecked($eid) ? ' checked' : '' ?> data-et-box> <?= e((string)$et['name']) ?></label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <p class="et-selrow" data-et-selrow
         data-et-some="{n} selected. You can save changes now. All The Venues will review event-type tags before publishing."
         data-et-none="No event types selected yet. You can save as draft, but at least one event type is required before publishing."></p>
    </div>
  <?php endif; ?>
</div>
