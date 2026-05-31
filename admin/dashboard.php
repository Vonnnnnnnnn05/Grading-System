<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();

// Stats
$totalStudents  = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers  = $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$totalSubjects  = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$totalClasses   = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalUsers     = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$gradedCount    = $db->query("SELECT COUNT(*) FROM grades WHERE status='submitted'")->fetchColumn();

// Recent grades
$recentGrades = $db->query("
    SELECT s.first_name, s.last_name, sub.subject_name, g.final_average, g.remarks, g.updated_at
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN subjects sub ON g.subject_id = sub.id
    ORDER BY g.updated_at DESC LIMIT 6
")->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <!-- Top bar -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
      <p class="text-muted text-sm mt-0.5">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?> &mdash; <?= date('l, F j, Y') ?></p>
    </div>
    <a href="/grading-system/admin/users.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-plus"></i> Add User
    </a>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
    <?php
    $stats = [
      ['label'=>'Total Users',    'value'=>$totalUsers,    'icon'=>'fa-users',              'color'=>'blue'],
      ['label'=>'Students',       'value'=>$totalStudents, 'icon'=>'fa-user-graduate',      'color'=>'indigo'],
      ['label'=>'Teachers',       'value'=>$totalTeachers, 'icon'=>'fa-chalkboard-teacher', 'color'=>'violet'],
      ['label'=>'Subjects',       'value'=>$totalSubjects, 'icon'=>'fa-book',               'color'=>'sky'],
      ['label'=>'Classes',        'value'=>$totalClasses,  'icon'=>'fa-school',             'color'=>'cyan'],
      ['label'=>'Grades Encoded', 'value'=>$gradedCount,   'icon'=>'fa-star',               'color'=>'emerald'],
    ];
    foreach ($stats as $s):
    $bg  = "bg-{$s['color']}-50";
    $txt = "text-{$s['color']}-600";
    $ico = "text-{$s['color']}-500";
    ?>
    <div class="bg-white rounded-xl border border-gray-100 p-5 card-hover">
      <div class="flex items-center justify-between mb-3">
        <div class="w-10 h-10 <?= $bg ?> rounded-lg flex items-center justify-center">
          <i class="fa <?= $s['icon'] ?> <?= $ico ?>"></i>
        </div>
      </div>
      <div class="text-2xl font-bold text-gray-800"><?= $s['value'] ?></div>
      <div class="text-xs text-muted mt-0.5"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick Actions & Recent Grades -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl border border-gray-100 p-6">
      <h2 class="font-semibold text-gray-800 mb-4">Quick Actions</h2>
      <div class="space-y-2">
        <?php
        $actions = [
          ['/grading-system/admin/users.php?action=create',    'fa-user-plus',           'Add New User',          'blue'],
          ['/grading-system/admin/students.php?action=create', 'fa-user-graduate',       'Enroll Student',        'indigo'],
          ['/grading-system/admin/teachers.php?action=create', 'fa-chalkboard-teacher',  'Add Teacher',           'violet'],
          ['/grading-system/admin/subjects.php?action=create', 'fa-book-open',           'Create Subject',        'sky'],
          ['/grading-system/admin/classes.php?action=create',  'fa-school',              'Create Class',          'cyan'],
          ['/grading-system/admin/assignments.php',            'fa-link',                'Manage Assignments',    'emerald'],
          ['/grading-system/admin/reports.php',                'fa-chart-bar',           'View Grade Reports',    'orange'],
        ];
        foreach ($actions as [$href,$icon,$label,$color]): ?>
        <a href="<?= $href ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 transition group">
          <div class="w-8 h-8 bg-<?= $color ?>-50 rounded-lg flex items-center justify-center group-hover:bg-<?= $color ?>-100 transition">
            <i class="fa <?= $icon ?> text-<?= $color ?>-600 text-sm"></i>
          </div>
          <span class="text-sm text-gray-700"><?= $label ?></span>
          <i class="fa fa-chevron-right text-gray-300 text-xs ml-auto"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Recent Grades -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-gray-800">Recent Grade Activity</h2>
        <a href="/grading-system/admin/reports.php" class="text-xs text-blue-600 hover:underline">View all</a>
      </div>
      <?php if (empty($recentGrades)): ?>
        <div class="flex flex-col items-center justify-center py-12 text-muted">
          <i class="fa fa-inbox text-3xl mb-3 text-gray-300"></i>
          <p class="text-sm">No grades encoded yet.</p>
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider pb-2">Student</th>
              <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider pb-2">Subject</th>
              <th class="text-center text-xs font-semibold text-muted uppercase tracking-wider pb-2">Average</th>
              <th class="text-center text-xs font-semibold text-muted uppercase tracking-wider pb-2">Remarks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($recentGrades as $g): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="py-2.5 font-medium text-gray-800"><?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?></td>
              <td class="py-2.5 text-muted"><?= htmlspecialchars($g['subject_name']) ?></td>
              <td class="py-2.5 text-center font-semibold <?= $g['final_average'] >= 75 ? 'text-green-600' : 'text-red-500' ?>">
                <?= $g['final_average'] !== null ? number_format($g['final_average'], 2) : '—' ?>
              </td>
              <td class="py-2.5 text-center">
                <?php if ($g['remarks'] === 'Passed'): ?>
                  <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Passed</span>
                <?php elseif ($g['remarks'] === 'Failed'): ?>
                  <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">Failed</span>
                <?php elseif ($g['remarks'] === 'Incomplete'): ?>
                  <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Incomplete</span>
                <?php else: ?>
                  <span class="text-muted text-xs">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div><!-- close ml-64 -->
</body>
</html>
