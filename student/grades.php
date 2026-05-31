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

// Filters
$fSY  = trim($_GET['school_year'] ?? '');
$fSem = trim($_GET['semester'] ?? '');
$fCls = (int)($_GET['class_id'] ?? 0);

$sql = "SELECT g.*, sub.subject_code, sub.subject_name, c.class_name, c.school_year, c.semester, c.grade_level, c.id AS class_id
        FROM grades g
        JOIN subjects sub ON g.subject_id=sub.id
        JOIN classes c ON g.class_id=c.id
        WHERE g.student_id=?";
$params = [$student['id']];
if ($fSY)  { $sql .= " AND c.school_year=?";  $params[] = $fSY; }
if ($fSem) { $sql .= " AND c.semester=?";     $params[] = $fSem; }
if ($fCls) { $sql .= " AND c.id=?";           $params[] = $fCls; }
$sql .= " ORDER BY c.school_year DESC, sub.subject_code";
$stmt = $db->prepare($sql); $stmt->execute($params);
$grades = $stmt->fetchAll();

// Filter options
$myClasses   = $db->prepare("SELECT DISTINCT c.id, c.class_name, c.school_year, c.semester FROM grades g JOIN classes c ON g.class_id=c.id WHERE g.student_id=? ORDER BY c.school_year DESC, c.class_name");
$myClasses->execute([$student['id']]); $myClasses = $myClasses->fetchAll();
$schoolYears = array_unique(array_column($myClasses,'school_year'));
$semesters   = array_unique(array_filter(array_column($myClasses,'semester')));

$passed = count(array_filter($grades, fn($g)=>$g['remarks']==='Passed'));
$failed = count(array_filter($grades, fn($g)=>$g['remarks']==='Failed'));
$avgArr = array_filter($grades, fn($g)=>$g['final_average']!==null);
$avg    = count($avgArr) ? array_sum(array_column($avgArr,'final_average')) / count($avgArr) : null;

$pageTitle = 'My Grades';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>
<div class="p-6 fade-in">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">My Grades</h1>
    <p class="text-muted text-sm">View your grades per subject and grading period.</p>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Filter -->
  <form method="GET" class="bg-white rounded-xl border border-gray-100 p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs font-medium text-muted mb-1">School Year</label>
      <select name="school_year" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">All</option>
        <?php foreach ($schoolYears as $sy): ?><option value="<?= $sy ?>" <?= $fSY===$sy?'selected':''?>><?= $sy ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-muted mb-1">Semester</label>
      <select name="semester" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">All</option>
        <?php foreach ($semesters as $sem): ?><option value="<?= $sem ?>" <?= $fSem===$sem?'selected':''?>><?= $sem ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-muted mb-1">Class</label>
      <select name="class_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">All</option>
        <?php foreach ($myClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $fCls==$c['id']?'selected':''?>><?= htmlspecialchars($c['class_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Filter</button>
    <a href="/grading-system/student/grades.php" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-muted hover:bg-gray-50 transition">Reset</a>
  </form>

  <!-- Stats row -->
  <div class="grid grid-cols-4 gap-4 mb-5">
    <?php
    $statItems = [
      ['label'=>'Total','value'=>count($grades),'color'=>'blue'],
      ['label'=>'Passed','value'=>$passed,'color'=>'green'],
      ['label'=>'Failed','value'=>$failed,'color'=>'red'],
      ['label'=>'Average','value'=>$avg!==null?number_format($avg,2):'—','color'=>$avg!==null?($avg>=75?'blue':'red'):'gray'],
    ];
    foreach ($statItems as $st): ?>
    <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
      <div class="text-2xl font-bold text-<?= $st['color'] ?>-600"><?= $st['value'] ?></div>
      <div class="text-xs text-muted"><?= $st['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Grade Table -->
  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
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
        <?php if (empty($grades)): ?>
        <tr><td colspan="7" class="text-center py-14 text-muted"><i class="fa fa-star text-2xl text-gray-200 block mb-2"></i>No grades found.</td></tr>
        <?php else: foreach ($grades as $g): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3">
            <span class="font-mono text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded mr-1"><?= htmlspecialchars($g['subject_code']) ?></span>
            <span class="font-medium text-gray-800"><?= htmlspecialchars($g['subject_name']) ?></span>
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
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div></body></html>
