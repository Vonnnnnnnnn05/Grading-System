<?php
$user = currentUser();
$basePath = '/grading-system';
function teacherNav($href, $icon, $label) {
    $active = (basename($_SERVER['PHP_SELF']) === basename($href)) ? 'active' : '';
    return "<a href=\"$href\" class=\"sidebar-link $active flex items-center gap-3 px-4 py-3 rounded-lg text-blue-100 hover:text-white\">
        <i class=\"fa $icon w-5 text-center\"></i><span>$label</span></a>";
}
?>
<aside class="w-64 h-screen bg-gradient-to-b from-blue-700 to-blue-900 flex flex-col shadow-xl fixed top-0 left-0 z-30 overflow-hidden">
  <div class="flex items-center gap-3 px-6 py-5 border-b border-blue-600">
    <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center">
      <i class="fa fa-graduation-cap text-blue-700 text-lg"></i>
    </div>
    <div>
      <div class="text-white font-bold text-lg leading-none">GradeSync</div>
      <div class="text-blue-200 text-xs">Teacher Portal</div>
    </div>
  </div>
  <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
    <div class="px-4 py-1 text-xs font-semibold text-blue-300 uppercase tracking-widest mb-2">Main</div>
    <?= teacherNav("$basePath/teacher/dashboard.php", 'fa-tachometer-alt', 'Dashboard') ?>
    <div class="px-4 py-1 text-xs font-semibold text-blue-300 uppercase tracking-widest mt-4 mb-2">Grading</div>
    <?= teacherNav("$basePath/teacher/classes.php", 'fa-chalkboard', 'My Classes') ?>
    <?= teacherNav("$basePath/teacher/grades.php", 'fa-pen-to-square', 'Encode Grades') ?>
    <?= teacherNav("$basePath/teacher/summary.php", 'fa-list-check', 'Grade Summary') ?>
  </nav>
  <div class="px-4 py-4 border-t border-blue-600 shrink-0">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-sm">
        <?= strtoupper(substr($user['name'] ?: $user['username'], 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-white text-sm font-medium truncate"><?= htmlspecialchars($user['name'] ?: $user['username']) ?></div>
        <div class="text-blue-300 text-xs">Teacher</div>
      </div>
    </div>
    <a href="<?= $basePath ?>/logout.php" class="flex items-center gap-2 text-blue-200 hover:text-white text-sm transition-colors">
      <i class="fa fa-sign-out-alt"></i> Logout
    </a>
  </div>
</aside>
<div class="ml-64">
