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

// Handle grade save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = $_POST['action'] ?? '';
    $classId  = (int)$_POST['class_id'];
    $subjectId = (int)$_POST['subject_id'];

    if ($act === 'save_grades') {
        $studentIds = $_POST['student_id'] ?? [];
        $errors = 0;
        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            $prelim  = isset($_POST["prelim_$sid"])  && $_POST["prelim_$sid"]  !== '' ? (float)$_POST["prelim_$sid"]  : null;
            $midterm = isset($_POST["midterm_$sid"]) && $_POST["midterm_$sid"] !== '' ? (float)$_POST["midterm_$sid"] : null;
            $final   = isset($_POST["final_$sid"])   && $_POST["final_$sid"]   !== '' ? (float)$_POST["final_$sid"]   : null;
            $status  = isset($_POST["submit_$sid"]) ? 'submitted' : 'draft';

            // Compute average and remarks
            $grades_arr = array_filter([$prelim, $midterm, $final], fn($v) => $v !== null);
            $avg = count($grades_arr) > 0 ? array_sum($grades_arr) / count($grades_arr) : null;
            $remarks = null;
            if ($avg !== null) {
                $remarks = $avg >= 75 ? 'Passed' : 'Failed';
            }
            if ($prelim === null && $midterm === null && $final === null) {
                $avg = null; $remarks = 'Incomplete';
            }

            try {
                $db->prepare("INSERT INTO grades (student_id,subject_id,class_id,teacher_id,prelim_grade,midterm_grade,final_grade,final_average,remarks,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE prelim_grade=VALUES(prelim_grade), midterm_grade=VALUES(midterm_grade),
                    final_grade=VALUES(final_grade), final_average=VALUES(final_average), remarks=VALUES(remarks), status=VALUES(status)")
                    ->execute([$sid, $subjectId, $classId, $teacher['id'], $prelim, $midterm, $final, $avg, $remarks, $status]);
            } catch (Exception $e) { $errors++; }
        }
        setFlash($errors ? 'error' : 'success', $errors ? "Some grades failed to save." : 'Grades saved successfully.');
        header("Location: /grading-system/teacher/grades.php?class_id=$classId&subject_id=$subjectId");
        exit;
    }
}

// Load selections
$selClassId   = (int)($_GET['class_id'] ?? 0);
$selSubjectId = (int)($_GET['subject_id'] ?? 0);

// Get assigned class-subjects
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

$selectedAssignment = null;
$students = [];
if ($selClassId && $selSubjectId) {
    // Verify teacher owns this assignment
    foreach ($assignments as $a) {
        if ($a['class_id'] == $selClassId && $a['subject_id'] == $selSubjectId) {
            $selectedAssignment = $a;
            break;
        }
    }
    if ($selectedAssignment) {
        $stmt = $db->prepare("
            SELECT s.*,
                   g.id AS grade_id, g.prelim_grade, g.midterm_grade, g.final_grade, g.final_average, g.remarks, g.status AS grade_status
            FROM class_students cs
            JOIN students s ON cs.student_id = s.id
            LEFT JOIN grades g ON g.student_id=s.id AND g.subject_id=? AND g.class_id=?
            WHERE cs.class_id=?
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$selSubjectId, $selClassId, $selClassId]);
        $students = $stmt->fetchAll();
    }
}

$pageTitle = 'Encode Grades';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_teacher.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Encode Grades</h1>
      <p class="text-muted text-sm">Select a class and subject to encode student grades.</p>
    </div>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Class/Subject Selector -->
  <form method="GET" class="bg-white rounded-xl border border-gray-100 p-5 mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-48">
      <label class="block text-sm font-medium text-gray-700 mb-1">Select Class & Subject</label>
      <select name="_assignment" id="assignment-sel" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">-- Choose --</option>
        <?php foreach ($assignments as $a): ?>
        <option value="<?= $a['class_id'] ?>_<?= $a['subject_id'] ?>"
          <?= ($selClassId==$a['class_id']&&$selSubjectId==$a['subject_id'])?'selected':''?>>
          <?= htmlspecialchars($a['class_name'] . ' — ' . $a['subject_code'] . ': ' . $a['subject_name'] . ' (' . $a['school_year'] . ')') ?>
        </option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="class_id" id="hidden-class">
      <input type="hidden" name="subject_id" id="hidden-subject">
    </div>
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">Load Students</button>
  </form>

  <?php if ($selectedAssignment && !empty($students)): ?>
  <!-- Grade Table -->
  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-gray-100 bg-blue-50 flex items-center justify-between">
      <div>
        <h2 class="font-semibold text-blue-800">
          <?= htmlspecialchars($selectedAssignment['class_name']) ?> —
          <span class="font-mono text-sm"><?= htmlspecialchars($selectedAssignment['subject_code']) ?></span>
          <?= htmlspecialchars($selectedAssignment['subject_name']) ?>
        </h2>
        <p class="text-xs text-blue-600"><?= htmlspecialchars($selectedAssignment['school_year'] . ($selectedAssignment['semester'] ? ' · ' . $selectedAssignment['semester'] : '')) ?></p>
      </div>
      <span class="text-xs text-blue-600 bg-blue-100 px-3 py-1 rounded-full"><?= count($students) ?> student(s)</span>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="save_grades">
      <input type="hidden" name="class_id" value="<?= $selClassId ?>">
      <input type="hidden" name="subject_id" value="<?= $selSubjectId ?>">

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Student</th>
              <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Prelim</th>
              <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Midterm</th>
              <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Final</th>
              <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Average</th>
              <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Remarks</th>
              <th class="text-center px-4 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Submit</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50" id="grade-body">
            <?php foreach ($students as $s):
              $isSubmitted = $s['grade_status'] === 'submitted';
            ?>
            <tr class="hover:bg-gray-50 transition" data-row="<?= $s['id'] ?>">
              <input type="hidden" name="student_id[]" value="<?= $s['id'] ?>">
              <td class="px-5 py-3">
                <div class="font-medium text-gray-800"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></div>
                <div class="text-xs text-muted"><?= htmlspecialchars($s['student_number']) ?></div>
              </td>
              <?php foreach (['prelim','midterm','final'] as $period): ?>
              <td class="px-4 py-3 text-center">
                <input type="number" name="<?= $period ?>_<?= $s['id'] ?>"
                  value="<?= $s[$period.'_grade'] !== null ? $s[$period.'_grade'] : '' ?>"
                  min="0" max="100" step="0.01"
                  <?= $isSubmitted ? 'disabled' : '' ?>
                  oninput="computeAvg(<?= $s['id'] ?>)"
                  class="w-20 text-center border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 <?= $isSubmitted ? 'bg-gray-50 text-muted' : '' ?>">
              </td>
              <?php endforeach; ?>
              <td class="px-4 py-3 text-center">
                <span id="avg-<?= $s['id'] ?>" class="font-semibold text-sm <?= $s['final_average']!==null?($s['final_average']>=75?'text-green-600':'text-red-500'):'text-gray-400' ?>">
                  <?= $s['final_average'] !== null ? number_format($s['final_average'],2) : '—' ?>
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span id="rem-<?= $s['id'] ?>" class="text-xs px-2 py-0.5 rounded-full font-medium
                  <?= $s['remarks']==='Passed'?'bg-green-100 text-green-700':($s['remarks']==='Failed'?'bg-red-100 text-red-700':($s['remarks']==='Incomplete'?'bg-yellow-100 text-yellow-700':'text-gray-400')) ?>">
                  <?= $s['remarks'] ?? '—' ?>
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if ($isSubmitted): ?>
                  <span class="text-xs text-green-600 flex items-center justify-center gap-1"><i class="fa fa-check-circle"></i> Done</span>
                <?php else: ?>
                  <input type="checkbox" name="submit_<?= $s['id'] ?>" id="sub-<?= $s['id'] ?>" class="w-4 h-4 rounded accent-blue-600">
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="px-5 py-4 border-t border-gray-100 flex gap-3">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2.5 rounded-lg text-sm transition flex items-center gap-2">
          <i class="fa fa-floppy-disk"></i> Save Grades
        </button>
        <p class="text-xs text-muted self-center">Check "Submit" to finalize a student's grade. Submitted grades cannot be changed.</p>
      </div>
    </form>
  </div>
  <?php elseif ($selectedAssignment && empty($students)): ?>
  <div class="bg-white rounded-xl border border-gray-100 py-14 text-center text-muted">
    <i class="fa fa-users text-3xl text-gray-200 block mb-3"></i>
    <p>No students enrolled in this class yet.</p>
  </div>
  <?php elseif ($selClassId || $selSubjectId): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
    <i class="fa fa-triangle-exclamation mr-2"></i> You are not assigned to this class/subject combination.
  </div>
  <?php endif; ?>
</div>

<script>
// Handle selector change
document.getElementById('assignment-sel').addEventListener('change', function() {
  const val = this.value.split('_');
  document.getElementById('hidden-class').value = val[0] || '';
  document.getElementById('hidden-subject').value = val[1] || '';
});
// Pre-set hidden values on load
(function() {
  const sel = document.getElementById('assignment-sel');
  if (sel.value) {
    const val = sel.value.split('_');
    document.getElementById('hidden-class').value = val[0] || '';
    document.getElementById('hidden-subject').value = val[1] || '';
  }
})();

function computeAvg(sid) {
  const prelim  = parseFloat(document.querySelector(`[name="prelim_${sid}"]`)?.value);
  const midterm = parseFloat(document.querySelector(`[name="midterm_${sid}"]`)?.value);
  const final   = parseFloat(document.querySelector(`[name="final_${sid}"]`)?.value);
  const vals = [prelim,midterm,final].filter(v => !isNaN(v));
  const avgEl  = document.getElementById('avg-'+sid);
  const remEl  = document.getElementById('rem-'+sid);
  if (vals.length === 0) {
    avgEl.textContent = '—'; avgEl.className = 'font-semibold text-sm text-gray-400';
    remEl.textContent = 'Incomplete'; remEl.className = 'text-xs px-2 py-0.5 rounded-full font-medium bg-yellow-100 text-yellow-700';
    return;
  }
  const avg = vals.reduce((a,b)=>a+b,0) / vals.length;
  avgEl.textContent = avg.toFixed(2);
  avgEl.className = 'font-semibold text-sm ' + (avg>=75?'text-green-600':'text-red-500');
  const passed = avg >= 75;
  remEl.textContent = passed ? 'Passed' : 'Failed';
  remEl.className = 'text-xs px-2 py-0.5 rounded-full font-medium ' + (passed?'bg-green-100 text-green-700':'bg-red-100 text-red-700');
}
</script>
</div></body></html>
