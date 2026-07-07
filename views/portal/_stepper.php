<?php
declare(strict_types=1);

/**
 * #15 — Three-step Add-Venue stepper (Details · Photos · Submit). Expects:
 *   string $stepActive       'details' | 'photos' | 'submit'
 *   bool   $stepDetailsDone   (default false)
 *   bool   $stepPhotosDone    (default false)
 * A step is 'done' (green ✓), 'on' (current, blue), or upcoming (grey). CSS-only
 * (brand.css .pstep*); no inline styles.
 */
$stepActive      = $stepActive ?? 'details';
$stepDetailsDone = !empty($stepDetailsDone);
$stepPhotosDone  = !empty($stepPhotosDone);

$state = static function (string $key, bool $done) use ($stepActive): string {
    if ($done && $stepActive !== $key) { return 'done'; }
    return $stepActive === $key ? 'on' : 'todo';
};
$steps = [
    ['key' => 'details', 'n' => '1', 'label' => 'Details', 'state' => $state('details', $stepDetailsDone)],
    ['key' => 'photos',  'n' => '2', 'label' => 'Photos',  'state' => $state('photos', $stepPhotosDone)],
    ['key' => 'submit',  'n' => '3', 'label' => 'Submit',  'state' => $state('submit', false)],
];
?>
<div class="pstepper">
  <?php foreach ($steps as $s): ?>
    <div class="pstep pstep--<?= e($s['state']) ?>">
      <span class="pstep__n"><?= $s['state'] === 'done' ? '&#10003;' : e($s['n']) ?></span> <?= e($s['label']) ?>
    </div>
  <?php endforeach; ?>
</div>
