<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();
$action = $_GET['action'] ?? 'list';

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'create' || $act === 'update') {
        $sn     = trim($_POST['student_number'] ?? '');
        $fn     = trim($_POST['first_name'] ?? '');
        $mn     = trim($_POST['middle_name'] ?? '') ?: null;
        $ln     = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? 'male';
        $bd     = $_POST['birthdate'] ?: null;
        $cn     = trim($_POST['contact_number'] ?? '') ?: null;
        $addr   = trim($_POST['address'] ?? '') ?: null;
        $uid    = (int)$_POST['user_id'];

        if (!$sn || !$fn || !$ln || !$uid) {
            setFlash('error', 'Student number, name, and linked user are required.');
            header('Location: /grading-system/admin/students.php');
            exit;
        }

        if ($act === 'create') {
            try {
                $stmt = $db->prepare("INSERT INTO students (user_id,student_number,first_name,middle_name,last_name,gender,birthdate,contact_number,address) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$uid,$sn,$fn,$mn,$ln,$gender,$bd,$cn,$addr]);
                setFlash('success', 'Student enrolled successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Error: ' . $e->getMessage());
            }
        } else {
            $sid = (int)$_POST['student_id'];
            try {
                $stmt = $db->prepare("UPDATE students SET user_id=?,student_number=?,first_name=?,middle_name=?,last_name=?,gender=?,birthdate=?,contact_number=?,address=? WHERE id=?");
                $stmt->execute([$uid,$sn,$fn,$mn,$ln,$gender,$bd,$cn,$addr,$sid]);
                setFlash('success', 'Student updated.');
            } catch (Exception $e) {
                setFlash('error', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: /grading-system/admin/students.php');
        exit;
    }

    if ($act === 'delete') {
        $db->prepare("DELETE FROM students WHERE id=?")->execute([(int)$_POST['student_id']]);
        setFlash('success', 'Student removed.');
        header('Location: /grading-system/admin/students.php');
        exit;
    }
}

// Load students
$search = trim($_GET['q'] ?? '');
$sql = "SELECT s.*, u.username, u.email, u.status FROM students s JOIN users u ON s.user_id=u.id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY s.last_name, s.first_name";
$stmt = $db->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();

// Student-role users not yet linked to a student profile
$availableUsers = $db->query("SELECT u.id, u.username, u.email FROM users u WHERE u.role='student' AND u.id NOT IN (SELECT user_id FROM students)")->fetchAll();
// All student users for edit
$allStudentUsers = $db->query("SELECT u.id, u.username, u.email FROM users u WHERE u.role='student'")->fetchAll();

$pageTitle = 'Students';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Students</h1>
      <p class="text-muted text-sm">Manage student profiles and enrollment.</p>
    </div>
    <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-plus"></i> Enroll Student
    </button>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Search -->
  <div class="bg-white rounded-xl border border-gray-100 p-4 mb-5 flex flex-wrap gap-3 items-center">
    <form method="GET" class="flex gap-3 flex-1">
      <div class="relative flex-1 min-w-48">
        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm"></i>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or student number…"
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Search</button>
      <a href="/grading-system/admin/students.php" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-muted hover:bg-gray-50 transition">Reset</a>
    </form>
    <span class="text-xs text-muted"><?= count($students) ?> student(s)</span>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Student No.</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Full Name</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Gender</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Contact</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Account</th>
          <th class="text-right px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($students)): ?>
        <tr><td colspan="6" class="text-center py-12 text-muted"><i class="fa fa-user-graduate text-2xl text-gray-200 block mb-2"></i>No students found.</td></tr>
        <?php else: foreach ($students as $s): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3 font-mono text-gray-600"><?= htmlspecialchars($s['student_number']) ?></td>
          <td class="px-5 py-3 font-medium text-gray-800">
            <?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ($s['middle_name'] ? ' ' . $s['middle_name'] : '')) ?>
          </td>
          <td class="px-5 py-3 text-muted capitalize"><?= htmlspecialchars($s['gender']) ?></td>
          <td class="px-5 py-3 text-muted"><?= $s['contact_number'] ? htmlspecialchars($s['contact_number']) : '—' ?></td>
          <td class="px-5 py-3">
            <div class="text-xs text-gray-700"><?= htmlspecialchars($s['username']) ?></div>
            <div class="text-xs text-muted"><?= htmlspecialchars($s['email']) ?></div>
          </td>
          <td class="px-5 py-3 text-right">
            <button onclick='openEditModal(<?= json_encode($s) ?>)' class="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition mr-1">
              <i class="fa fa-pen"></i>
            </button>
            <form method="POST" class="inline" onsubmit="return confirm('Remove this student?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
              <button type="submit" class="text-red-500 hover:text-red-700 px-2 py-1 rounded transition"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="student-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 id="modal-title" class="font-semibold text-gray-800">Enroll Student</h2>
      <button onclick="closeModal()" class="text-muted hover:text-gray-700 transition"><i class="fa fa-xmark text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" id="f-action" value="create">
      <input type="hidden" name="student_id" id="f-student-id" value="">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Linked User Account</label>
        <select name="user_id" id="f-user" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Select student user --</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Student Number</label>
        <input name="student_number" id="f-sn" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
          <input name="first_name" id="f-fn" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
          <input name="middle_name" id="f-mn" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
          <input name="last_name" id="f-ln" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
          <select name="gender" id="f-gender" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
          <input name="birthdate" id="f-bd" type="date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
        <input name="contact_number" id="f-cn" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <textarea name="address" id="f-addr" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition">Save</button>
        <button type="button" onclick="closeModal()" class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium py-2.5 rounded-lg text-sm transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const availableUsers = <?= json_encode($availableUsers) ?>;
const allStudentUsers = <?= json_encode($allStudentUsers) ?>;

function populateSelect(users, selectedId='') {
  const sel = document.getElementById('f-user');
  sel.innerHTML = '<option value="">-- Select student user --</option>';
  users.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.id;
    opt.textContent = u.username + ' (' + u.email + ')';
    if (u.id == selectedId) opt.selected = true;
    sel.appendChild(opt);
  });
}

function openCreateModal() {
  document.getElementById('modal-title').textContent = 'Enroll Student';
  document.getElementById('f-action').value = 'create';
  document.getElementById('f-student-id').value = '';
  populateSelect(availableUsers);
  ['f-sn','f-fn','f-mn','f-ln','f-cn','f-addr'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f-gender').value = 'male';
  document.getElementById('f-bd').value = '';
  document.getElementById('student-modal').classList.remove('hidden');
}

function openEditModal(s) {
  document.getElementById('modal-title').textContent = 'Edit Student';
  document.getElementById('f-action').value = 'update';
  document.getElementById('f-student-id').value = s.id;
  populateSelect(allStudentUsers, s.user_id);
  document.getElementById('f-sn').value = s.student_number;
  document.getElementById('f-fn').value = s.first_name;
  document.getElementById('f-mn').value = s.middle_name || '';
  document.getElementById('f-ln').value = s.last_name;
  document.getElementById('f-gender').value = s.gender;
  document.getElementById('f-bd').value = s.birthdate || '';
  document.getElementById('f-cn').value = s.contact_number || '';
  document.getElementById('f-addr').value = s.address || '';
  document.getElementById('student-modal').classList.remove('hidden');
}

function closeModal() { document.getElementById('student-modal').classList.add('hidden'); }
document.getElementById('student-modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
<?php if ($action === 'create'): ?>openCreateModal();<?php endif; ?>
</script>
</div></body></html>
