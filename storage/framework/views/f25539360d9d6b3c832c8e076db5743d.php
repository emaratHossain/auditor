
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo e($audit->page->name); ?> — landing page audit</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Georgia, 'Times New Roman', serif; color: #1c1917; font-size: 11pt; line-height: 1.5; margin: 0; }
  h1 { font-size: 21pt; margin: 0 0 4px; }
  h2 { font-size: 13pt; margin: 26px 0 10px; border-bottom: 1px solid #d6d3d1; padding-bottom: 4px; }
  .muted { color: #78716c; font-size: 9.5pt; }
  .score { display: flex; align-items: baseline; gap: 12px; margin: 14px 0 6px; }
  .score b { font-size: 40pt; line-height: 1; }
  .cats { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9.5pt; }
  .cats td { padding: 4px 6px; border-bottom: 1px solid #e7e5e4; }
  .fix { border-left: 3px solid #a8a29e; padding: 6px 0 6px 12px; margin-bottom: 14px; page-break-inside: avoid; }
  .fix.high { border-left-color: #b91c1c; }
  .fix.medium { border-left-color: #b45309; }
  .fix.low { border-left-color: #15803d; }
  .fix h3 { font-size: 11.5pt; margin: 0 0 5px; }
  .fix p { margin: 0 0 3px; }
  .tag { font-size: 8pt; text-transform: uppercase; letter-spacing: .06em; color: #57534e; }
  .label { font-weight: bold; color: #57534e; }
  .shot { page-break-inside: avoid; margin-bottom: 16px; }
  .shot img { width: 100%; border: 1px solid #d6d3d1; }
</style>
</head>
<body>

<h1><?php echo e($audit->page->name); ?></h1>
<p class="muted"><?php echo e($audit->page->url); ?> · audited <?php echo e($audit->created_at->format('j M Y, H:i')); ?></p>

<div class="score">
  <b><?php echo e($audit->overall_score ?? '—'); ?></b>
  <span class="muted">out of 100 · six weighted categories</span>
</div>

<table class="cats">
  <?php $__currentLoopData = $audit->category_scores ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(is_array($c) && isset($c['label'])): ?>
      <tr>
        <td><?php echo e($c['label']); ?> <span class="muted">· <?php echo e($c['weight']); ?>%</span></td>
        <td align="right"><?php echo e($c['score'] ?? 'not measured'); ?></td>
        <td class="muted"><?php echo e($c['caveat'] ?? ''); ?></td>
      </tr>
    <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</table>

<h2>What to fix, in order</h2>
<?php $__empty_1 = true; $__currentLoopData = $audit->recommendations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $rec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <div class="fix <?php echo e($rec->priority); ?>">
    <span class="tag"><?php echo e($rec->priority); ?> · <?php echo e($rec->section_name); ?></span>
    <h3><?php echo e($i + 1); ?>. <?php echo e($rec->title); ?></h3>
    <p><span class="label">Evidence:</span> <?php echo e($rec->evidence); ?></p>
    <p><span class="label">Do this:</span> <?php echo e($rec->suggested_fix); ?></p>
    <p><span class="label">If it works:</span> <?php echo e($rec->expected_impact); ?></p>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <p>Nothing on this page could be proven against a number, so nothing is claimed here.</p>
<?php endif; ?>

<h2>The page, section by section</h2>
<?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="shot">
    <p>
      <b><?php echo e($section['name']); ?></b>
      <span class="muted">
        · <?php echo e($section['position_percent']); ?>% down the page
        <?php if($section['score'] !== null): ?> · scored <?php echo e($section['score']); ?>/100 <?php endif; ?>
      </span>
    </p>
    <?php if($section['image']): ?>
      <img src="<?php echo e($section['image']); ?>" alt="<?php echo e($section['name']); ?>">
    <?php endif; ?>
    <?php $__currentLoopData = $section['problems']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <p style="font-size:10pt">— <?php echo e($p['what']); ?> <span class="muted"><?php echo e($p['fix']); ?></span></p>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<p class="muted" style="margin-top:24px">
  Every recommendation above names a real metric, a real number and a real section.
  Anything that could not do so was discarded rather than shown.
</p>

</body>
</html>
<?php /**PATH /Users/globalarraytics/Projects/auditor/resources/views/pdf/report.blade.php ENDPATH**/ ?>