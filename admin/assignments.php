<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'assign') {
        $tid = (int)$_POST['teacher_id'];
        $sid = (int)$_POST['subject_id'];
        $cid = (int)$_POST['class_id'];
        if (!$tid || !$sid || !$cid) { setFlash('error', 'All fields are required.'); }
        else {
            try {
                $db->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id,subject_id,class_id) VALUES (?,?,?)")->execute([$tid,$sid,$cid]);
                setFlash('success', 'Assignment created.');
            } catch (Exception $e) { setFlash('error', $e->getMessage()); }
        }
    } elseif ($act === 'remove') {
        $db->prepare("DELETE FROM teacher_subjects WHERE id=?")->execute([(int)$_POST['assignment_id']]);
        setFlash('success', 'Assignment removed.');
    }
    header('Location: /grading-system/admin/assignments.php'); exit;
}

$assignments = $db->query("
    SELECT ts.id, t.first_name AS tfn, t.last_name AS tln, t.employee_number,
           s.subject_code, s.subject_name,
           c.class_name, c.grade_level, c.school_year, c.semester
    FROM teacher_subjects ts
    JOIN teachers t ON ts.teacher_id = t.id
    JOIN subjects s ON ts.subject_id = s.id
    JOIN classes c ON ts.class_id = c.id
    ORDER BY tln, tfn, c.class_name
")->fetchAll();

$teachers = $db->query("SELECT id, employee_number, first_name, last_name FROM teachers ORDER BY last_name, first_name")->fetchAll();
$subjects = $db->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code")->fetchAll();
$classes  = $db->query("SELECT id, class_name, grade_level, school_year, semester FROM classes ORDER BY school_year DESC, class_name")->fetchAll();

$pageTitle = 'Teacher Assignments';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Teacher Assignments</h1>
      <p class="text-muted text-sm">Assign teachers to subjects and class sections.</p>
    </div>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Assignment Form -->
    <div class="bg-white rounded-xl border border-gray-100 p-6">
      <h2 class="font-semibold text-gray-800 mb-4">New Assignment</h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="assign">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
          <select name="teacher_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select teacher --</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name'] . ' (' . $t['employee_number'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
          <select name="subject_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select subject --</option>
            <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_code'] . ' — ' . $s['subject_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Class / Section</label>
          <select name="class_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select class --</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'] . ' · ' . $c['school_year'] . ($c['semester'] ? ' · ' . $c['semester'] : '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition flex items-center justify-center gap-2">
          <i class="fa fa-plus"></i> Assign
        </button>
      </form>
    </div>

    <!-- Assignments List -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">Current Assignments</h2>
        <span class="text-xs text-muted"><?= count($assignments) ?> assignment(s)</span>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Teacher</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Subject</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Class</th>
            <th class="text-right px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($assignments)): ?>
          <tr><td colspan="4" class="text-center py-12 text-muted"><i class="fa fa-link text-2xl text-gray-200 block mb-2"></i>No assignments yet.</td></tr>
          <?php else: foreach ($assignments as $a): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($a['tln'] . ', ' . $a['tfn']) ?></td>
            <td class="px-5 py-3">
              <span class="font-mono text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded mr-1"><?= htmlspecialchars($a['subject_code']) ?></span>
              <span class="text-muted"><?= htmlspecialchars($a['subject_name']) ?></span>
            </td>
            <td class="px-5 py-3 text-muted"><?= htmlspecialchars($a['class_name']) ?><br><span class="text-xs"><?= htmlspecialchars($a['school_year'] . ($a['semester'] ? ' · ' . $a['semester'] : '')) ?></span></td>
            <td class="px-5 py-3 text-right">
              <form method="POST" class="inline" onsubmit="return confirm('Remove this assignment?')">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                <button type="submit" class="text-red-500 hover:text-red-700 px-2 py-1 rounded transition"><i class="fa fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div></body></html>
