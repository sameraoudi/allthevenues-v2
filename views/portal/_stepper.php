<?php
declare(strict_types=1);

/**
 * #15 — Three-step Add-Venue stepper (Details · Photos · Submit). Expects:
 *   string $stepActive       'details' | 'photos' | 'submit'
 *   bool   $stepDetailsDone   (default false)
 *   bool   $stepPhotosDone    (default false)
 *   bool   $stepReady         (default true) — #3: Submit is greyed/'locked'
 *                             until details+photos are done, even when active.
 * A step is 'done' (green ✓), 'on' (current, blue), 'locked' (greyed, not yet
 * reachable) or upcoming 'todo' (grey). CSS-only (brand.css .pstep*); no inline styles.
 */
$stepActive      = $stepActive ?? 'details';
$stepDetailsDone = !empty($stepDetailsDone);
$stepPhotosDone  = !empty($stepPhotosDone);
$stepReady       = $stepReady ?? true;

$state = static function (string $key, bool $done) use ($stepActive): string {
    if ($done && $stepActive !== $key) { return 'done'; }
    return $stepActive === $key ? 'on' : 'todo';
};
// #3 — Submit stays 'locked' (greyed) until Steps 1 & 2 are complete, even when
// it is the active step, so it never looks reachable before it is.
$submitState = ($stepActive === 'submit')
    ? ($stepReady ? 'on' : 'locked')
    : $state('submit', false);
$steps = [
    ['key' => 'details', 'n' => '1', 'label' => 'Details', 'state' => $state('details', $stepDetailsDone)],
    ['key' => 'photos',  'n' => '2', 'label' => 'Photos',  'state' => $state('photos', $stepPhotosDone)],
    ['key' => 'submit',  'n' => '3', 'label' => 'Submit',  'state' => $submitState],
];
?>
<div class="pstepper">
  <?php foreach ($steps as $s): ?>
    <div class="pstep pstep--<?= e($s['state']) ?>">
      <span class="pstep__n"><?= $s['state'] === 'done' ? '&#10003;' : e($s['n']) ?></span> <?= e($s['label']) ?>
    </div>
  <?php endforeach; ?>
</div>
