<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'create' || $act === 'update') {
        $en  = trim($_POST['employee_number'] ?? '');
        $fn  = trim($_POST['first_name'] ?? '');
        $mn  = trim($_POST['middle_name'] ?? '') ?: null;
        $ln  = trim($_POST['last_name'] ?? '');
        $cn  = trim($_POST['contact_number'] ?? '') ?: null;
        $uid = (int)$_POST['user_id'];

        if (!$en || !$fn || !$ln || !$uid) {
            setFlash('error', 'Employee number, name, and linked user are required.');
        } elseif ($act === 'create') {
            try {
                $db->prepare("INSERT INTO teachers (user_id,employee_number,first_name,middle_name,last_name,contact_number) VALUES (?,?,?,?,?,?)")
                   ->execute([$uid,$en,$fn,$mn,$ln,$cn]);
                setFlash('success', 'Teacher added successfully.');
            } catch (Exception $e) { setFlash('error', $e->getMessage()); }
        } else {
            $tid = (int)$_POST['teacher_id'];
            try {
                $db->prepare("UPDATE teachers SET user_id=?,employee_number=?,first_name=?,middle_name=?,last_name=?,contact_number=? WHERE id=?")
                   ->execute([$uid,$en,$fn,$mn,$ln,$cn,$tid]);
                setFlash('success', 'Teacher updated.');
            } catch (Exception $e) { setFlash('error', $e->getMessage()); }
        }
        header('Location: /grading-system/admin/teachers.php'); exit;
    }

    if ($act === 'delete') {
        $db->prepare("DELETE FROM teachers WHERE id=?")->execute([(int)$_POST['teacher_id']]);
        setFlash('success', 'Teacher removed.');
        header('Location: /grading-system/admin/teachers.php'); exit;
    }
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT t.*, u.username, u.email FROM teachers t JOIN users u ON t.user_id=u.id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.employee_number LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY t.last_name, t.first_name";
$stmt = $db->prepare($sql); $stmt->execute($params);
$teachers = $stmt->fetchAll();

$availableUsers = $db->query("SELECT u.id, u.username, u.email FROM users u WHERE u.role='teacher' AND u.id NOT IN (SELECT user_id FROM teachers)")->fetchAll();
$allTeacherUsers = $db->query("SELECT u.id, u.username, u.email FROM users u WHERE u.role='teacher'")->fetchAll();

$pageTitle = 'Teachers';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Teachers</h1>
      <p class="text-muted text-sm">Manage teacher profiles.</p>
    </div>
    <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-plus"></i> Add Teacher
    </button>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <div class="bg-white rounded-xl border border-gray-100 p-4 mb-5 flex flex-wrap gap-3 items-center">
    <form method="GET" class="flex gap-3 flex-1">
      <div class="relative flex-1 min-w-48">
        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm"></i>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or employee number…"
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Search</button>
      <a href="/grading-system/admin/teachers.php" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-muted hover:bg-gray-50 transition">Reset</a>
    </form>
    <span class="text-xs text-muted"><?= count($teachers) ?> teacher(s)</span>
  </div>

  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Employee No.</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Full Name</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Contact</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Account</th>
          <th class="text-right px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($teachers)): ?>
        <tr><td colspan="5" class="text-center py-12 text-muted"><i class="fa fa-chalkboard-teacher text-2xl text-gray-200 block mb-2"></i>No teachers found.</td></tr>
        <?php else: foreach ($teachers as $t): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3 font-mono text-gray-600"><?= htmlspecialchars($t['employee_number']) ?></td>
          <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name'] . ($t['middle_name'] ? ' ' . $t['middle_name'] : '')) ?></td>
          <td class="px-5 py-3 text-muted"><?= $t['contact_number'] ? htmlspecialchars($t['contact_number']) : '—' ?></td>
          <td class="px-5 py-3">
            <div class="text-xs text-gray-700"><?= htmlspecialchars($t['username']) ?></div>
            <div class="text-xs text-muted"><?= htmlspecialchars($t['email']) ?></div>
          </td>
          <td class="px-5 py-3 text-right">
            <button onclick='openEditModal(<?= json_encode($t) ?>)' class="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition mr-1"><i class="fa fa-pen"></i></button>
            <form method="POST" class="inline" onsubmit="return confirm('Remove this teacher?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
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
<div id="teacher-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 id="modal-title" class="font-semibold text-gray-800">Add Teacher</h2>
      <button onclick="closeModal()" class="text-muted hover:text-gray-700"><i class="fa fa-xmark text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" id="f-action" value="create">
      <input type="hidden" name="teacher_id" id="f-tid" value="">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Linked User Account</label>
          <select name="user_id" id="f-user" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select teacher user --</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employee Number</label>
          <input name="employee_number" id="f-en" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
          <input name="contact_number" id="f-cn" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
          <input name="first_name" id="f-fn" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
          <input name="middle_name" id="f-mn" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
          <input name="last_name" id="f-ln" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
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
const availableUsers = <?= json_encode($availableUsers) ?>;
const allTeacherUsers = <?= json_encode($allTeacherUsers) ?>;
function populateSelect(users, selectedId='') {
  const sel = document.getElementById('f-user');
  sel.innerHTML = '<option value="">-- Select teacher user --</option>';
  users.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.id; opt.textContent = u.username + ' (' + u.email + ')';
    if (u.id == selectedId) opt.selected = true;
    sel.appendChild(opt);
  });
}
function openCreateModal() {
  document.getElementById('modal-title').textContent = 'Add Teacher';
  document.getElementById('f-action').value = 'create';
  document.getElementById('f-tid').value = '';
  populateSelect(availableUsers);
  ['f-en','f-fn','f-mn','f-ln','f-cn'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('teacher-modal').classList.remove('hidden');
}
function openEditModal(t) {
  document.getElementById('modal-title').textContent = 'Edit Teacher';
  document.getElementById('f-action').value = 'update';
  document.getElementById('f-tid').value = t.id;
  populateSelect(allTeacherUsers, t.user_id);
  document.getElementById('f-en').value = t.employee_number;
  document.getElementById('f-fn').value = t.first_name;
  document.getElementById('f-mn').value = t.middle_name || '';
  document.getElementById('f-ln').value = t.last_name;
  document.getElementById('f-cn').value = t.contact_number || '';
  document.getElementById('teacher-modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('teacher-modal').classList.add('hidden'); }
document.getElementById('teacher-modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</div></body></html>
