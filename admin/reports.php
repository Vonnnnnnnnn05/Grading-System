<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();

// Filters
$fClass   = (int)($_GET['class_id'] ?? 0);
$fSubject = (int)($_GET['subject_id'] ?? 0);
$fTeacher = (int)($_GET['teacher_id'] ?? 0);
$fStudent = trim($_GET['student'] ?? '');
$fSY      = trim($_GET['school_year'] ?? '');
$fSem     = trim($_GET['semester'] ?? '');
$fRemarks = trim($_GET['remarks'] ?? '');

$sql = "
    SELECT g.*,
           s.first_name AS sfn, s.last_name AS sln, s.student_number,
           sub.subject_code, sub.subject_name,
           c.class_name, c.school_year, c.semester,
           t.first_name AS tfn, t.last_name AS tln
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN subjects sub ON g.subject_id = sub.id
    JOIN classes c ON g.class_id = c.id
    JOIN teachers t ON g.teacher_id = t.id
    WHERE 1=1
";
$params = [];
if ($fClass)   { $sql .= " AND g.class_id=?";   $params[] = $fClass; }
if ($fSubject) { $sql .= " AND g.subject_id=?";  $params[] = $fSubject; }
if ($fTeacher) { $sql .= " AND g.teacher_id=?";  $params[] = $fTeacher; }
if ($fStudent) { $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ?)"; $params = array_merge($params, ["%$fStudent%","%$fStudent%","%$fStudent%"]); }
if ($fSY)      { $sql .= " AND c.school_year=?";  $params[] = $fSY; }
if ($fSem)     { $sql .= " AND c.semester=?";     $params[] = $fSem; }
if ($fRemarks) { $sql .= " AND g.remarks=?";      $params[] = $fRemarks; }
$sql .= " ORDER BY sln, sfn, sub.subject_code";

$stmt = $db->prepare($sql); $stmt->execute($params);
$grades = $stmt->fetchAll();

$classes  = $db->query("SELECT id, class_name, school_year, semester FROM classes ORDER BY school_year DESC, class_name")->fetchAll();
$subjects = $db->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code")->fetchAll();
$teachers = $db->query("SELECT id, first_name, last_name FROM teachers ORDER BY last_name, first_name")->fetchAll();
$schoolYears = $db->query("SELECT DISTINCT school_year FROM classes ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$semesters   = $db->query("SELECT DISTINCT semester FROM classes WHERE semester IS NOT NULL ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$total    = count($grades);
$passed   = count(array_filter($grades, fn($g) => $g['remarks'] === 'Passed'));
$failed   = count(array_filter($grades, fn($g) => $g['remarks'] === 'Failed'));
$inc      = count(array_filter($grades, fn($g) => $g['remarks'] === 'Incomplete'));
$avgGrade = $total ? array_sum(array_column(array_filter($grades, fn($g)=>$g['final_average']!==null), 'final_average')) / max(1, count(array_filter($grades, fn($g)=>$g['final_average']!==null))) : 0;

$pageTitle = 'Grade Reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Grade Reports</h1>
      <p class="text-muted text-sm">Filter and view grade summaries across all classes and subjects.</p>
    </div>
    <button onclick="window.print()" class="border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition">
      <i class="fa fa-print"></i> Print
    </button>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Filter Bar -->
  <form method="GET" class="bg-white rounded-xl border border-gray-100 p-4 mb-5">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-3">
      <div>
        <label class="block text-xs font-medium text-muted mb-1">School Year</label>
        <select name="school_year" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All</option>
          <?php foreach ($schoolYears as $sy): ?><option value="<?= $sy ?>" <?= $fSY===$sy?'selected':''?>><?= $sy ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-muted mb-1">Semester</label>
        <select name="semester" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All</option>
          <?php foreach ($semesters as $sem): ?><option value="<?= $sem ?>" <?= $fSem===$sem?'selected':''?>><?= $sem ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-muted mb-1">Class</label>
        <select name="class_id" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All</option>
          <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $fClass==$c['id']?'selected':''?>><?= htmlspecialchars($c['class_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-muted mb-1">Subject</label>
        <select name="subject_id" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All</option>
          <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>" <?= $fSubject==$s['id']?'selected':''?>><?= htmlspecialchars($s['subject_code']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-muted mb-1">Teacher</label>
        <select name="teacher_id" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All</option>
          <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>" <?= $fTeacher==$t['id']?'selected':''?>><?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-muted mb-1">Remarks</label>
        <select name="remarks" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All</option>
          <option value="Passed" <?= $fRemarks==='Passed'?'selected':''?>>Passed</option>
          <option value="Failed" <?= $fRemarks==='Failed'?'selected':''?>>Failed</option>
          <option value="Incomplete" <?= $fRemarks==='Incomplete'?'selected':''?>>Incomplete</option>
        </select>
      </div>
    </div>
    <div class="flex gap-3 items-end">
      <div class="flex-1 relative">
        <label class="block text-xs font-medium text-muted mb-1">Student Name / Number</label>
        <i class="fa fa-search absolute left-3 bottom-2.5 text-muted text-sm"></i>
        <input name="student" value="<?= htmlspecialchars($fStudent) ?>" placeholder="Search student…" class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Apply Filter</button>
      <a href="/grading-system/admin/reports.php" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-muted hover:bg-gray-50 transition">Reset</a>
    </div>
  </form>

  <!-- Summary Stats -->
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
    <?php
    $summaryStats = [
      ['label'=>'Total Records','value'=>$total,'color'=>'blue','icon'=>'fa-list'],
      ['label'=>'Passed','value'=>$passed,'color'=>'green','icon'=>'fa-check-circle'],
      ['label'=>'Failed','value'=>$failed,'color'=>'red','icon'=>'fa-times-circle'],
      ['label'=>'Incomplete','value'=>$inc,'color'=>'yellow','icon'=>'fa-exclamation-circle'],
      ['label'=>'Class Average','value'=>$total?number_format($avgGrade,2):'—','color'=>'indigo','icon'=>'fa-chart-line'],
    ];
    foreach ($summaryStats as $st):
    ?>
    <div class="bg-white rounded-xl border border-gray-100 px-5 py-4">
      <div class="flex items-center gap-2 mb-1">
        <i class="fa <?= $st['icon'] ?> text-<?= $st['color'] ?>-500 text-sm"></i>
        <span class="text-xs text-muted"><?= $st['label'] ?></span>
      </div>
      <div class="text-2xl font-bold text-<?= $st['color'] ?>-600"><?= $st['value'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Student</th>
          <th class="text-left px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Subject</th>
          <th class="text-left px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Class</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Prelim</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Midterm</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Final</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Average</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Remarks</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($grades)): ?>
        <tr><td colspan="8" class="text-center py-12 text-muted"><i class="fa fa-chart-bar text-2xl text-gray-200 block mb-2"></i>No grades match the selected filters.</td></tr>
        <?php else: foreach ($grades as $g): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3">
            <div class="font-medium text-gray-800"><?= htmlspecialchars($g['sln'] . ', ' . $g['sfn']) ?></div>
            <div class="text-xs text-muted"><?= htmlspecialchars($g['student_number']) ?></div>
          </td>
          <td class="px-4 py-3">
            <span class="font-mono text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded mr-1"><?= htmlspecialchars($g['subject_code']) ?></span>
            <span class="text-muted"><?= htmlspecialchars($g['subject_name']) ?></span>
          </td>
          <td class="px-4 py-3 text-muted text-xs"><?= htmlspecialchars($g['class_name']) ?><br><?= htmlspecialchars($g['school_year'] . ($g['semester'] ? ' · ' . $g['semester'] : '')) ?></td>
          <td class="px-4 py-3 text-center"><?= $g['prelim_grade'] !== null ? number_format($g['prelim_grade'],2) : '—' ?></td>
          <td class="px-4 py-3 text-center"><?= $g['midterm_grade'] !== null ? number_format($g['midterm_grade'],2) : '—' ?></td>
          <td class="px-4 py-3 text-center"><?= $g['final_grade'] !== null ? number_format($g['final_grade'],2) : '—' ?></td>
          <td class="px-4 py-3 text-center font-semibold <?= $g['final_average']>=75?'text-green-600':'text-red-500' ?>">
            <?= $g['final_average'] !== null ? number_format($g['final_average'],2) : '—' ?>
          </td>
          <td class="px-4 py-3 text-center">
            <?php if ($g['remarks']==='Passed'): ?>
              <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Passed</span>
            <?php elseif ($g['remarks']==='Failed'): ?>
              <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">Failed</span>
            <?php elseif ($g['remarks']==='Incomplete'): ?>
              <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Incomplete</span>
            <?php else: ?>
              <span class="text-muted text-xs">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div></body></html>
