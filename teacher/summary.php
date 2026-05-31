<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('teacher');

$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();
if (!$teacher) { die('Teacher profile not found.'); }

$selClassId   = (int)($_GET['class_id'] ?? 0);
$selSubjectId = (int)($_GET['subject_id'] ?? 0);

$assignments = $db->prepare("
    SELECT ts.id, s.id AS subject_id, s.subject_code, s.subject_name,
           c.id AS class_id, c.class_name, c.grade_level, c.school_year, c.semester
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id=s.id
    JOIN classes c ON ts.class_id=c.id
    WHERE ts.teacher_id=?
    ORDER BY c.class_name, s.subject_code
");
$assignments->execute([$teacher['id']]);
$assignments = $assignments->fetchAll();

$grades = [];
$selectedAssignment = null;
if ($selClassId && $selSubjectId) {
    foreach ($assignments as $a) {
        if ($a['class_id'] == $selClassId && $a['subject_id'] == $selSubjectId) {
            $selectedAssignment = $a; break;
        }
    }
    if ($selectedAssignment) {
        $stmt = $db->prepare("
            SELECT s.student_number, s.first_name, s.last_name,
                   g.prelim_grade, g.midterm_grade, g.final_grade, g.final_average, g.remarks, g.status
            FROM class_students cs
            JOIN students s ON cs.student_id=s.id
            LEFT JOIN grades g ON g.student_id=s.id AND g.subject_id=? AND g.class_id=?
            WHERE cs.class_id=?
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$selSubjectId, $selClassId, $selClassId]);
        $grades = $stmt->fetchAll();
    }
}

// Stats
$passed = count(array_filter($grades, fn($g) => $g['remarks']==='Passed'));
$failed = count(array_filter($grades, fn($g) => $g['remarks']==='Failed'));
$avg    = count($grades) ? array_sum(array_map(fn($g) => $g['final_average'] ?? 0, $grades)) / count($grades) : 0;

$pageTitle = 'Grade Summary';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_teacher.php';
?>
<div class="p-6 fade-in">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Grade Summary</h1>
    <p class="text-muted text-sm">View a read-only summary of grades for any of your assigned classes.</p>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <form method="GET" class="bg-white rounded-xl border border-gray-100 p-5 mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-48">
      <label class="block text-sm font-medium text-gray-700 mb-1">Class & Subject</label>
      <select name="_a" id="asel" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">-- Choose --</option>
        <?php foreach ($assignments as $a): ?>
        <option value="<?= $a['class_id'] ?>_<?= $a['subject_id'] ?>" <?= ($selClassId==$a['class_id']&&$selSubjectId==$a['subject_id'])?'selected':''?>>
          <?= htmlspecialchars($a['class_name'] . ' — ' . $a['subject_code'] . ': ' . $a['subject_name'] . ' (' . $a['school_year'] . ')') ?>
        </option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="class_id" id="hc">
      <input type="hidden" name="subject_id" id="hs">
    </div>
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">View</button>
    <button type="button" onclick="window.print()" class="border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg transition flex items-center gap-2">
      <i class="fa fa-print"></i> Print
    </button>
  </form>

  <?php if ($selectedAssignment): ?>
  <div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
      <div class="text-2xl font-bold text-gray-800"><?= count($grades) ?></div>
      <div class="text-xs text-muted">Total Students</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
      <div class="text-2xl font-bold text-green-600"><?= $passed ?></div>
      <div class="text-xs text-muted">Passed</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
      <div class="text-2xl font-bold text-red-500"><?= $failed ?></div>
      <div class="text-xs text-muted">Failed</div>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-blue-50">
      <h2 class="font-semibold text-blue-800"><?= htmlspecialchars($selectedAssignment['class_name'] . ' — ' . $selectedAssignment['subject_code'] . ': ' . $selectedAssignment['subject_name']) ?></h2>
      <p class="text-xs text-blue-600"><?= htmlspecialchars($selectedAssignment['school_year'] . ($selectedAssignment['semester'] ? ' · ' . $selectedAssignment['semester'] : '')) ?></p>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Student</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Prelim</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Midterm</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Final</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Average</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Remarks</th>
          <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($grades)): ?>
        <tr><td colspan="7" class="text-center py-10 text-muted">No grades yet.</td></tr>
        <?php else: foreach ($grades as $g): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($g['last_name'] . ', ' . $g['first_name']) ?><br><span class="text-xs text-muted"><?= htmlspecialchars($g['student_number']) ?></span></td>
          <td class="px-4 py-3 text-center"><?= $g['prelim_grade']  !== null ? number_format($g['prelim_grade'],2)  : '—' ?></td>
          <td class="px-4 py-3 text-center"><?= $g['midterm_grade'] !== null ? number_format($g['midterm_grade'],2) : '—' ?></td>
          <td class="px-4 py-3 text-center"><?= $g['final_grade']   !== null ? number_format($g['final_grade'],2)   : '—' ?></td>
          <td class="px-4 py-3 text-center font-semibold <?= $g['final_average']>=75?'text-green-600':'text-red-500' ?>">
            <?= $g['final_average'] !== null ? number_format($g['final_average'],2) : '—' ?>
          </td>
          <td class="px-4 py-3 text-center">
            <?php if ($g['remarks']==='Passed'): ?><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Passed</span>
            <?php elseif ($g['remarks']==='Failed'): ?><span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">Failed</span>
            <?php elseif ($g['remarks']==='Incomplete'): ?><span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Incomplete</span>
            <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-center">
            <?php if ($g['status']==='submitted'): ?><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">Submitted</span>
            <?php elseif ($g['status']==='draft'): ?><span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Draft</span>
            <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<script>
document.getElementById('asel').addEventListener('change', function() {
  const v = this.value.split('_');
  document.getElementById('hc').value = v[0]||'';
  document.getElementById('hs').value = v[1]||'';
});
(function() {
  const sel = document.getElementById('asel');
  if (sel.value) { const v=sel.value.split('_'); document.getElementById('hc').value=v[0]||''; document.getElementById('hs').value=v[1]||''; }
})();
</script>
</div></body></html>
