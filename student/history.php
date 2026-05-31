<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('student');

$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT * FROM students WHERE user_id=?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();
if (!$student) { die('Profile not found.'); }

// All grades grouped by school year + semester
$allGrades = $db->prepare("
    SELECT g.*, sub.subject_code, sub.subject_name,
           c.class_name, c.school_year, c.semester, c.grade_level
    FROM grades g
    JOIN subjects sub ON g.subject_id=sub.id
    JOIN classes c ON g.class_id=c.id
    WHERE g.student_id=?
    ORDER BY c.school_year DESC, c.semester, sub.subject_code
");
$allGrades->execute([$student['id']]);
$allGrades = $allGrades->fetchAll();

// Group by school year > semester
$grouped = [];
foreach ($allGrades as $g) {
    $key  = $g['school_year'];
    $sem  = $g['semester'] ?? 'N/A';
    $grouped[$key][$sem][] = $g;
}

$pageTitle = 'Academic History';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>
<div class="p-6 fade-in">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Academic History</h1>
    <p class="text-muted text-sm">Complete academic record grouped by school year and semester.</p>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <?php if (empty($grouped)): ?>
  <div class="bg-white rounded-xl border border-gray-100 py-16 text-center text-muted">
    <i class="fa fa-clock-rotate-left text-3xl text-gray-200 block mb-3"></i>
    <p class="text-sm">No academic records found yet.</p>
  </div>
  <?php else: ?>
  <div class="space-y-6">
    <?php foreach ($grouped as $sy => $semesters): ?>
    <div>
      <div class="flex items-center gap-3 mb-3">
        <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
        <h2 class="font-bold text-lg text-gray-800">School Year <?= htmlspecialchars($sy) ?></h2>
      </div>
      <?php foreach ($semesters as $sem => $semGrades):
        $spassed = count(array_filter($semGrades, fn($g)=>$g['remarks']==='Passed'));
        $sfailed = count(array_filter($semGrades, fn($g)=>$g['remarks']==='Failed'));
        $savg    = count($semGrades) ? array_sum(array_column(array_filter($semGrades,fn($g)=>$g['final_average']!==null),'final_average')) / max(1, count(array_filter($semGrades,fn($g)=>$g['final_average']!==null))) : null;
      ?>
      <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-4 ml-5">
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
          <div>
            <span class="font-semibold text-gray-700"><?= htmlspecialchars($sem) ?></span>
            <span class="text-xs text-muted ml-2"><?= count($semGrades) ?> subject(s)</span>
          </div>
          <div class="flex gap-4 text-xs">
            <span class="text-green-600 font-medium"><i class="fa fa-check-circle mr-1"></i><?= $spassed ?> Passed</span>
            <span class="text-red-500 font-medium"><i class="fa fa-times-circle mr-1"></i><?= $sfailed ?> Failed</span>
            <?php if ($savg !== null): ?>
            <span class="text-blue-600 font-medium">Avg: <?= number_format($savg,2) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50/50 border-b border-gray-100">
            <tr>
              <th class="text-left px-5 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Subject</th>
              <th class="text-left px-4 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Class</th>
              <th class="text-center px-4 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Prelim</th>
              <th class="text-center px-4 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Midterm</th>
              <th class="text-center px-4 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Final</th>
              <th class="text-center px-4 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Average</th>
              <th class="text-center px-4 py-2.5 text-xs font-semibold text-muted uppercase tracking-wider">Remarks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($semGrades as $g): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-5 py-3">
                <span class="font-mono text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded mr-1"><?= htmlspecialchars($g['subject_code']) ?></span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($g['subject_name']) ?></span>
              </td>
              <td class="px-4 py-3 text-muted text-xs"><?= htmlspecialchars($g['class_name']) ?></td>
              <td class="px-4 py-3 text-center"><?= $g['prelim_grade']  !== null ? number_format($g['prelim_grade'],2)  : '—' ?></td>
              <td class="px-4 py-3 text-center"><?= $g['midterm_grade'] !== null ? number_format($g['midterm_grade'],2) : '—' ?></td>
              <td class="px-4 py-3 text-center"><?= $g['final_grade']   !== null ? number_format($g['final_grade'],2)   : '—' ?></td>
              <td class="px-4 py-3 text-center font-bold <?= $g['final_average']>=75?'text-green-600':'text-red-500' ?>">
                <?= $g['final_average'] !== null ? number_format($g['final_average'],2) : '—' ?>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if ($g['remarks']==='Passed'): ?><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Passed</span>
                <?php elseif ($g['remarks']==='Failed'): ?><span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">Failed</span>
                <?php elseif ($g['remarks']==='Incomplete'): ?><span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Incomplete</span>
                <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div></body></html>
