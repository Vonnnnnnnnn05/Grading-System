<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('student');

$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT * FROM students WHERE user_id=?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();
if (!$student) {
    echo '<div class="p-6 text-center text-red-600"><p>Student profile not found. Contact admin.</p><a href="/grading-system/logout.php" class="text-blue-600 underline">Logout</a></div>'; exit;
}

// Current grades (most recent class enrollment)
$grades = $db->prepare("
    SELECT g.*, sub.subject_code, sub.subject_name, c.class_name, c.school_year, c.semester
    FROM grades g
    JOIN subjects sub ON g.subject_id=sub.id
    JOIN classes c ON g.class_id=c.id
    WHERE g.student_id=?
    ORDER BY c.school_year DESC, sub.subject_code
    LIMIT 20
");
$grades->execute([$student['id']]);
$grades = $grades->fetchAll();

$passed = count(array_filter($grades, fn($g)=>$g['remarks']==='Passed'));
$failed = count(array_filter($grades, fn($g)=>$g['remarks']==='Failed'));
$avgArr = array_filter($grades, fn($g)=>$g['final_average']!==null);
$gpa    = count($avgArr) ? array_sum(array_column($avgArr,'final_average')) / count($avgArr) : null;

$pageTitle = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">My Dashboard</h1>
      <p class="text-muted text-sm mt-0.5">Welcome, <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> &mdash; <?= date('l, F j, Y') ?></p>
    </div>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Profile & Stats -->
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 mb-6">
    <!-- Profile Card -->
    <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-5 text-white flex flex-col justify-between">
      <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center text-white font-bold text-2xl mb-3">
        <?= strtoupper(substr($student['first_name'],0,1)) ?>
      </div>
      <div>
        <div class="font-bold text-lg leading-tight"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
        <div class="text-blue-200 text-sm"><?= htmlspecialchars($student['student_number']) ?></div>
      </div>
    </div>
    <!-- Stats -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 text-center card-hover">
      <div class="text-3xl font-bold text-gray-800"><?= count($grades) ?></div>
      <div class="text-xs text-muted mt-1">Subjects</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-5 text-center card-hover">
      <div class="text-3xl font-bold text-green-600"><?= $passed ?></div>
      <div class="text-xs text-muted mt-1">Passed</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-5 text-center card-hover">
      <div class="text-3xl font-bold <?= $gpa!==null?($gpa>=75?'text-blue-600':'text-red-500'):'text-gray-300' ?>">
        <?= $gpa !== null ? number_format($gpa, 2) : '—' ?>
      </div>
      <div class="text-xs text-muted mt-1">GPA (Average)</div>
    </div>
  </div>

  <!-- Grades Table -->
  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-semibold text-gray-800">Recent Grades</h2>
      <a href="/grading-system/student/grades.php" class="text-xs text-blue-600 hover:underline">View all</a>
    </div>
    <?php if (empty($grades)): ?>
    <div class="text-center py-16 text-muted"><i class="fa fa-star text-3xl text-gray-200 block mb-3"></i><p class="text-sm">No grades recorded yet.</p></div>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Subject</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Class</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Prelim</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Midterm</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Final</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Average</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Remarks</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($grades as $g): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3">
            <span class="font-mono text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded mr-1"><?= htmlspecialchars($g['subject_code']) ?></span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars($g['subject_name']) ?></span>
          </td>
          <td class="px-5 py-3 text-muted text-xs"><?= htmlspecialchars($g['class_name']) ?><br><?= htmlspecialchars($g['school_year'] . ($g['semester'] ? ' · ' . $g['semester'] : '')) ?></td>
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
    <?php endif; ?>
  </div>
</div>
</div></body></html>
