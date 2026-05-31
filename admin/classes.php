<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create' || $act === 'update') {
        $cn  = trim($_POST['class_name'] ?? '');
        $gl  = trim($_POST['grade_level'] ?? '');
        $sy  = trim($_POST['school_year'] ?? '');
        $sem = trim($_POST['semester'] ?? '') ?: null;
        if (!$cn || !$gl || !$sy) { setFlash('error', 'Class name, grade level, and school year are required.'); }
        elseif ($act === 'create') {
            $db->prepare("INSERT INTO classes (class_name,grade_level,school_year,semester) VALUES (?,?,?,?)")->execute([$cn,$gl,$sy,$sem]);
            setFlash('success', 'Class created.');
        } else {
            $db->prepare("UPDATE classes SET class_name=?,grade_level=?,school_year=?,semester=? WHERE id=?")->execute([$cn,$gl,$sy,$sem,(int)$_POST['class_id']]);
            setFlash('success', 'Class updated.');
        }
    } elseif ($act === 'delete') {
        $db->prepare("DELETE FROM classes WHERE id=?")->execute([(int)$_POST['class_id']]);
        setFlash('success', 'Class deleted.');
    } elseif ($act === 'enroll_student') {
        $cid = (int)$_POST['class_id'];
        $sid = (int)$_POST['student_id'];
        try {
            $db->prepare("INSERT IGNORE INTO class_students (class_id,student_id) VALUES (?,?)")->execute([$cid,$sid]);
            setFlash('success', 'Student enrolled in class.');
        } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    } elseif ($act === 'unenroll_student') {
        $db->prepare("DELETE FROM class_students WHERE class_id=? AND student_id=?")->execute([(int)$_POST['class_id'],(int)$_POST['student_id']]);
        setFlash('success', 'Student removed from class.');
    }
    header('Location: /grading-system/admin/classes.php' . (isset($_POST['class_id']) && in_array($act,['enroll_student','unenroll_student']) ? '?view=' . (int)$_POST['class_id'] : ''));
    exit;
}

$classes = $db->query("SELECT * FROM classes ORDER BY school_year DESC, class_name")->fetchAll();
$allStudents = $db->query("SELECT s.id, s.student_number, s.first_name, s.last_name FROM students s ORDER BY s.last_name, s.first_name")->fetchAll();

$viewClassId = (int)($_GET['view'] ?? 0);
$viewClass = null; $enrolledStudents = []; $notEnrolled = [];
if ($viewClassId) {
    $stmt = $db->prepare("SELECT * FROM classes WHERE id=?"); $stmt->execute([$viewClassId]);
    $viewClass = $stmt->fetch();
    if ($viewClass) {
        $enrolledStudents = $db->prepare("SELECT s.*, cs.id as cs_id FROM students s JOIN class_students cs ON s.id=cs.student_id WHERE cs.class_id=? ORDER BY s.last_name, s.first_name");
        $enrolledStudents->execute([$viewClassId]); $enrolledStudents = $enrolledStudents->fetchAll();
        $enrolledIds = array_column($enrolledStudents, 'id');
        $notEnrolled = array_filter($allStudents, fn($s) => !in_array($s['id'], $enrolledIds));
    }
}

$pageTitle = 'Classes & Sections';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Classes & Sections</h1>
      <p class="text-muted text-sm">Create class sections and manage student enrollment.</p>
    </div>
    <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-plus"></i> Create Class
    </button>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Classes list -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">All Classes</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Class Name</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Year / Sem</th>
            <th class="text-right px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($classes)): ?>
          <tr><td colspan="3" class="text-center py-10 text-muted"><i class="fa fa-school text-2xl text-gray-200 block mb-2"></i>No classes yet.</td></tr>
          <?php else: foreach ($classes as $c): ?>
          <tr class="hover:bg-gray-50 transition <?= $c['id']==$viewClassId?'bg-blue-50':'' ?>">
            <td class="px-5 py-3">
              <div class="font-medium text-gray-800"><?= htmlspecialchars($c['class_name']) ?></div>
              <div class="text-xs text-muted"><?= htmlspecialchars($c['grade_level']) ?></div>
            </td>
            <td class="px-5 py-3 text-muted text-xs"><?= htmlspecialchars($c['school_year']) ?><?= $c['semester'] ? ' · ' . $c['semester'] : '' ?></td>
            <td class="px-5 py-3 text-right flex justify-end gap-1">
              <a href="?view=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition" title="Manage students"><i class="fa fa-users"></i></a>
              <button onclick='openEditModal(<?= json_encode($c) ?>)' class="text-gray-500 hover:text-gray-700 px-2 py-1 rounded transition" title="Edit"><i class="fa fa-pen"></i></button>
              <form method="POST" class="inline" onsubmit="return confirm('Delete this class?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                <button type="submit" class="text-red-500 hover:text-red-700 px-2 py-1 rounded transition"><i class="fa fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Student enrollment panel -->
    <?php if ($viewClass): ?>
    <div class="bg-white rounded-xl border border-blue-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-blue-100 bg-blue-50">
        <h2 class="font-semibold text-blue-800">
          <i class="fa fa-users mr-2"></i><?= htmlspecialchars($viewClass['class_name']) ?> — Students
        </h2>
        <p class="text-xs text-blue-600 mt-0.5"><?= htmlspecialchars($viewClass['school_year']) ?><?= $viewClass['semester'] ? ' · ' . $viewClass['semester'] : '' ?></p>
      </div>
      <!-- Add student -->
      <form method="POST" class="p-4 border-b border-gray-100 flex gap-3">
        <input type="hidden" name="action" value="enroll_student">
        <input type="hidden" name="class_id" value="<?= $viewClass['id'] ?>">
        <select name="student_id" required class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Select student to enroll --</option>
          <?php foreach ($notEnrolled as $ns): ?>
          <option value="<?= $ns['id'] ?>"><?= htmlspecialchars($ns['student_number'] . ' — ' . $ns['last_name'] . ', ' . $ns['first_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition flex items-center gap-2"><i class="fa fa-plus"></i>Enroll</button>
      </form>
      <!-- Enrolled list -->
      <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
        <?php if (empty($enrolledStudents)): ?>
        <div class="text-center py-8 text-muted"><i class="fa fa-users text-2xl text-gray-200 block mb-2"></i><p class="text-sm">No students enrolled yet.</p></div>
        <?php else: foreach ($enrolledStudents as $es): ?>
        <div class="flex items-center px-5 py-3 hover:bg-gray-50 transition">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-xs mr-3"><?= strtoupper(substr($es['first_name'],0,1)) ?></div>
          <div class="flex-1">
            <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($es['last_name'] . ', ' . $es['first_name']) ?></div>
            <div class="text-xs text-muted"><?= htmlspecialchars($es['student_number']) ?></div>
          </div>
          <form method="POST" onsubmit="return confirm('Remove student from class?')">
            <input type="hidden" name="action" value="unenroll_student">
            <input type="hidden" name="class_id" value="<?= $viewClass['id'] ?>">
            <input type="hidden" name="student_id" value="<?= $es['id'] ?>">
            <button type="submit" class="text-red-400 hover:text-red-600 transition text-sm"><i class="fa fa-xmark"></i></button>
          </form>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="bg-gray-50 rounded-xl border border-dashed border-gray-200 flex items-center justify-center">
      <div class="text-center text-muted py-16">
        <i class="fa fa-arrow-left text-3xl text-gray-200 block mb-3"></i>
        <p class="text-sm">Select a class to manage students.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal -->
<div id="class-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 id="modal-title" class="font-semibold text-gray-800">Create Class</h2>
      <button onclick="closeModal()" class="text-muted hover:text-gray-700"><i class="fa fa-xmark text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" id="f-action" value="create">
      <input type="hidden" name="class_id" id="f-cid" value="">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class Name / Section</label>
        <input name="class_name" id="f-cn" required placeholder="e.g. Grade 11 - STEM A" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Grade / Year Level</label>
        <input name="grade_level" id="f-gl" required placeholder="e.g. Grade 11" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
          <input name="school_year" id="f-sy" required placeholder="e.g. 2026-2027" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Semester / Term</label>
          <input name="semester" id="f-sem" placeholder="e.g. 1st Semester" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition">Save</button>
        <button type="button" onclick="closeModal()" class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium py-2.5 rounded-lg text-sm transition">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openCreateModal() {
  document.getElementById('modal-title').textContent = 'Create Class';
  document.getElementById('f-action').value = 'create';
  document.getElementById('f-cid').value = '';
  ['f-cn','f-gl','f-sy','f-sem'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('class-modal').classList.remove('hidden');
}
function openEditModal(c) {
  document.getElementById('modal-title').textContent = 'Edit Class';
  document.getElementById('f-action').value = 'update';
  document.getElementById('f-cid').value = c.id;
  document.getElementById('f-cn').value = c.class_name;
  document.getElementById('f-gl').value = c.grade_level;
  document.getElementById('f-sy').value = c.school_year;
  document.getElementById('f-sem').value = c.semester || '';
  document.getElementById('class-modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('class-modal').classList.add('hidden'); }
document.getElementById('class-modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</div></body></html>
