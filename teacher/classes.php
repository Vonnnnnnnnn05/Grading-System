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

$assignments = $db->prepare("
    SELECT ts.id, s.id AS subject_id, s.subject_code, s.subject_name,
           c.id AS class_id, c.class_name, c.grade_level, c.school_year, c.semester,
           (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=c.id) AS student_count,
           (SELECT COUNT(*) FROM grades g WHERE g.teacher_id=? AND g.subject_id=s.id AND g.class_id=c.id) AS graded_count,
           (SELECT COUNT(*) FROM grades g WHERE g.teacher_id=? AND g.subject_id=s.id AND g.class_id=c.id AND g.status='submitted') AS submitted_count
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id=s.id
    JOIN classes c ON ts.class_id=c.id
    WHERE ts.teacher_id=?
    ORDER BY c.school_year DESC, c.class_name, s.subject_code
");
$assignments->execute([$teacher['id'], $teacher['id'], $teacher['id']]);
$assignments = $assignments->fetchAll();

$pageTitle = 'My Classes';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_teacher.php';
?>
<div class="p-6 fade-in">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">My Classes</h1>
    <p class="text-muted text-sm">Subjects and classes assigned to you.</p>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <?php if (empty($assignments)): ?>
  <div class="bg-white rounded-xl border border-gray-100 py-16 text-center text-muted">
    <i class="fa fa-chalkboard text-3xl text-gray-200 block mb-3"></i>
    <p>No classes assigned yet. Contact your administrator.</p>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php foreach ($assignments as $a):
      $pct = $a['student_count'] > 0 ? round(($a['graded_count'] / $a['student_count']) * 100) : 0;
    ?>
    <div class="bg-white rounded-xl border border-gray-100 p-5 card-hover">
      <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center shrink-0">
          <i class="fa fa-book text-blue-600"></i>
        </div>
        <?php if ($a['submitted_count'] == $a['student_count'] && $a['student_count'] > 0): ?>
        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Complete</span>
        <?php elseif ($a['graded_count'] > 0): ?>
        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-medium">In Progress</span>
        <?php else: ?>
        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">Not Started</span>
        <?php endif; ?>
      </div>
      <div class="font-mono text-xs text-blue-600 mb-0.5"><?= htmlspecialchars($a['subject_code']) ?></div>
      <div class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($a['subject_name']) ?></div>
      <div class="text-sm text-muted mb-3">
        <?= htmlspecialchars($a['class_name']) ?> &middot; <?= htmlspecialchars($a['grade_level']) ?><br>
        <span class="text-xs"><?= htmlspecialchars($a['school_year'] . ($a['semester'] ? ' · ' . $a['semester'] : '')) ?></span>
      </div>
      <!-- Progress bar -->
      <div class="mb-3">
        <div class="flex justify-between text-xs text-muted mb-1">
          <span><?= $a['graded_count'] ?>/<?= $a['student_count'] ?> students graded</span>
          <span><?= $pct ?>%</span>
        </div>
        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
          <div class="h-full bg-blue-500 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <a href="/grading-system/teacher/grades.php?class_id=<?= $a['class_id'] ?>&subject_id=<?= $a['subject_id'] ?>"
         class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 rounded-lg transition">
        <i class="fa fa-pen"></i> Encode Grades
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div></body></html>
