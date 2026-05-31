<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('teacher');

$db = getDB();
$user = currentUser();

// Get teacher profile
$stmt = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    echo '<div class="p-6 text-center text-red-600"><p>Teacher profile not found. Contact admin.</p><a href="/grading-system/logout.php" class="text-blue-600 underline">Logout</a></div>';
    exit;
}

// Assigned classes
$assignments = $db->prepare("
    SELECT ts.id, s.subject_code, s.subject_name, s.id AS subject_id,
           c.class_name, c.grade_level, c.school_year, c.semester, c.id AS class_id,
           (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=c.id) AS student_count,
           (SELECT COUNT(*) FROM grades g WHERE g.teacher_id=? AND g.subject_id=s.id AND g.class_id=c.id AND g.status='submitted') AS submitted_count
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id=s.id
    JOIN classes c ON ts.class_id=c.id
    WHERE ts.teacher_id=?
    ORDER BY c.school_year DESC, c.class_name, s.subject_code
");
$assignments->execute([$teacher['id'], $teacher['id']]);
$assignments = $assignments->fetchAll();

// Recent grades
$recent = $db->prepare("
    SELECT g.final_average, g.remarks, g.updated_at,
           st.first_name, st.last_name,
           sub.subject_name, c.class_name
    FROM grades g
    JOIN students st ON g.student_id=st.id
    JOIN subjects sub ON g.subject_id=sub.id
    JOIN classes c ON g.class_id=c.id
    WHERE g.teacher_id=?
    ORDER BY g.updated_at DESC LIMIT 5
");
$recent->execute([$teacher['id']]);
$recent = $recent->fetchAll();

$totalEncoded  = $db->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id=?")->execute([$teacher['id']]) ? $db->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id=?")->execute([$teacher['id']]) : 0;
$stmt2 = $db->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id=?"); $stmt2->execute([$teacher['id']]); $totalEncoded = $stmt2->fetchColumn();
$stmt3 = $db->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id=? AND status='submitted'"); $stmt3->execute([$teacher['id']]); $totalSubmitted = $stmt3->fetchColumn();

$pageTitle = 'Teacher Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_teacher.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
      <p class="text-muted text-sm mt-0.5">Welcome, <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?> &mdash; <?= date('l, F j, Y') ?></p>
    </div>
    <a href="/grading-system/teacher/grades.php" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-pen"></i> Encode Grades
    </a>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 p-5 card-hover">
      <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center mb-3"><i class="fa fa-chalkboard text-blue-600"></i></div>
      <div class="text-2xl font-bold text-gray-800"><?= count($assignments) ?></div>
      <div class="text-xs text-muted">Assigned Classes</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-5 card-hover">
      <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center mb-3"><i class="fa fa-star text-green-600"></i></div>
      <div class="text-2xl font-bold text-gray-800"><?= $totalSubmitted ?></div>
      <div class="text-xs text-muted">Grades Submitted</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-5 card-hover">
      <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center mb-3"><i class="fa fa-clock text-orange-600"></i></div>
      <div class="text-2xl font-bold text-gray-800"><?= $totalEncoded - $totalSubmitted ?></div>
      <div class="text-xs text-muted">Drafts Pending</div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Assignments -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">My Assigned Classes</h2>
        <a href="/grading-system/teacher/classes.php" class="text-xs text-blue-600 hover:underline">View all</a>
      </div>
      <div class="divide-y divide-gray-50">
        <?php if (empty($assignments)): ?>
        <div class="text-center py-10 text-muted"><i class="fa fa-chalkboard text-2xl text-gray-200 block mb-2"></i><p class="text-sm">No assignments yet.</p></div>
        <?php else: foreach (array_slice($assignments,0,5) as $a): ?>
        <div class="px-5 py-4 hover:bg-gray-50 transition">
          <div class="flex items-start justify-between">
            <div>
              <div class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($a['class_name']) ?></div>
              <div class="text-xs text-muted mt-0.5">
                <span class="font-mono bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded mr-1"><?= htmlspecialchars($a['subject_code']) ?></span>
                <?= htmlspecialchars($a['subject_name']) ?>
              </div>
              <div class="text-xs text-muted mt-1"><?= htmlspecialchars($a['school_year'] . ($a['semester'] ? ' · ' . $a['semester'] : '')) ?></div>
            </div>
            <div class="text-right">
              <div class="text-xs text-muted"><?= $a['submitted_count'] ?>/<?= $a['student_count'] ?> graded</div>
              <a href="/grading-system/teacher/grades.php?class_id=<?= $a['class_id'] ?>&subject_id=<?= $a['subject_id'] ?>"
                 class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 transition">
                <i class="fa fa-pen text-xs"></i> Grade
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Recent Grade Activity</h2>
      </div>
      <div class="divide-y divide-gray-50">
        <?php if (empty($recent)): ?>
        <div class="text-center py-10 text-muted"><i class="fa fa-inbox text-2xl text-gray-200 block mb-2"></i><p class="text-sm">No grades encoded yet.</p></div>
        <?php else: foreach ($recent as $r): ?>
        <div class="px-5 py-3 hover:bg-gray-50 transition flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 text-xs font-bold shrink-0">
            <?= strtoupper(substr($r['first_name'],0,1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></div>
            <div class="text-xs text-muted truncate"><?= htmlspecialchars($r['subject_name']) ?> · <?= htmlspecialchars($r['class_name']) ?></div>
          </div>
          <div class="text-right shrink-0">
            <div class="text-sm font-semibold <?= $r['final_average']>=75?'text-green-600':'text-red-500' ?>">
              <?= $r['final_average']!==null?number_format($r['final_average'],2):'—' ?>
            </div>
            <?php if ($r['remarks']): ?>
            <span class="text-xs px-1.5 py-0.5 rounded-full <?= $r['remarks']==='Passed'?'bg-green-100 text-green-700':($r['remarks']==='Failed'?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700') ?>">
              <?= $r['remarks'] ?>
            </span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
</div></body></html>
