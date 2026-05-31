<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'create' || $act === 'update') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'student';
        $status   = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        if ($act === 'create') {
            if (!$username || !$email || !$password) {
                setFlash('error', 'All fields including password are required.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $db->prepare("INSERT INTO users (username,email,password,role,status) VALUES (?,?,?,?,?)");
                    $stmt->execute([$username, $email, $hash, $role, $status]);
                    setFlash('success', 'User created successfully.');
                } catch (Exception $e) {
                    setFlash('error', 'Error: ' . $e->getMessage());
                }
            }
        } else {
            $uid = (int)$_POST['user_id'];
            $params = [$username, $email, $role, $status, $uid];
            $sql = "UPDATE users SET username=?,email=?,role=?,status=? WHERE id=?";
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username=?,email=?,role=?,status=?,password=? WHERE id=?";
                $params = [$username, $email, $role, $status, $hash, $uid];
            }
            try {
                $db->prepare($sql)->execute($params);
                setFlash('success', 'User updated successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: /grading-system/admin/users.php');
        exit;
    }

    if ($act === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            setFlash('success', 'User deleted.');
        }
        header('Location: /grading-system/admin/users.php');
        exit;
    }
}

// Load data
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (username LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($roleFilter) { $sql .= " AND role=?"; $params[] = $roleFilter; }
$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

$editUser = null;
if ($action === 'edit' && $editId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

$pageTitle = 'User Accounts';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">User Accounts</h1>
      <p class="text-muted text-sm">Manage all system users and their roles.</p>
    </div>
    <button onclick="openModal('user-modal')" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-plus"></i> Add User
    </button>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <!-- Filters -->
  <div class="bg-white rounded-xl border border-gray-100 p-4 mb-5 flex flex-wrap gap-3 items-center">
    <form method="GET" class="flex flex-wrap gap-3 flex-1">
      <input type="hidden" name="action" value="list">
      <div class="relative flex-1 min-w-48">
        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm"></i>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search username or email…"
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <select name="role" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">All Roles</option>
        <option value="admin"   <?= $roleFilter==='admin'?'selected':''?>>Admin</option>
        <option value="teacher" <?= $roleFilter==='teacher'?'selected':''?>>Teacher</option>
        <option value="student" <?= $roleFilter==='student'?'selected':''?>>Student</option>
      </select>
      <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Filter</button>
      <a href="/grading-system/admin/users.php" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-muted hover:bg-gray-50 transition">Reset</a>
    </form>
    <span class="text-xs text-muted"><?= count($users) ?> user(s)</span>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">#</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Username</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Email</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Role</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Status</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Created</th>
          <th class="text-right px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($users)): ?>
        <tr><td colspan="7" class="text-center py-12 text-muted"><i class="fa fa-users text-2xl text-gray-200 block mb-2"></i>No users found.</td></tr>
        <?php else: foreach ($users as $i => $u): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3 text-muted"><?= $i+1 ?></td>
          <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($u['username']) ?></td>
          <td class="px-5 py-3 text-muted"><?= htmlspecialchars($u['email']) ?></td>
          <td class="px-5 py-3">
            <?php
            $roleColors = ['admin'=>'purple','teacher'=>'blue','student'=>'green'];
            $c = $roleColors[$u['role']] ?? 'gray';
            ?>
            <span class="px-2 py-0.5 bg-<?= $c ?>-100 text-<?= $c ?>-700 text-xs font-medium rounded-full capitalize"><?= $u['role'] ?></span>
          </td>
          <td class="px-5 py-3">
            <?php if ($u['status']==='active'): ?>
              <span class="flex items-center gap-1.5 text-green-600 text-xs"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Active</span>
            <?php else: ?>
              <span class="flex items-center gap-1.5 text-gray-400 text-xs"><span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>Inactive</span>
            <?php endif; ?>
          </td>
          <td class="px-5 py-3 text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
          <td class="px-5 py-3 text-right">
            <button onclick='openEditModal(<?= json_encode($u) ?>)' class="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition mr-1">
              <i class="fa fa-pen"></i>
            </button>
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <form method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="text-red-500 hover:text-red-700 px-2 py-1 rounded transition"><i class="fa fa-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create/Edit Modal -->
<div id="user-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 id="modal-title" class="font-semibold text-gray-800">Add User</h2>
      <button onclick="closeModal('user-modal')" class="text-muted hover:text-gray-700 transition"><i class="fa fa-xmark text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" id="form-action" value="create">
      <input type="hidden" name="user_id" id="form-user-id" value="">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input name="username" id="f-username" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input name="email" id="f-email" type="email" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password <span id="pw-hint" class="text-muted font-normal">(required)</span></label>
        <input name="password" id="f-password" type="password" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
          <select name="role" id="f-role" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="admin">Admin</option>
            <option value="teacher">Teacher</option>
            <option value="student" selected>Student</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select name="status" id="f-status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition">Save</button>
        <button type="button" onclick="closeModal('user-modal')" class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium py-2.5 rounded-lg text-sm transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) {
  document.getElementById('modal-title').textContent = 'Add User';
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-user-id').value = '';
  document.getElementById('f-username').value = '';
  document.getElementById('f-email').value = '';
  document.getElementById('f-password').value = '';
  document.getElementById('f-role').value = 'student';
  document.getElementById('f-status').value = 'active';
  document.getElementById('pw-hint').textContent = '(required)';
  document.getElementById(id).classList.remove('hidden');
}
function openEditModal(u) {
  document.getElementById('modal-title').textContent = 'Edit User';
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-user-id').value = u.id;
  document.getElementById('f-username').value = u.username;
  document.getElementById('f-email').value = u.email;
  document.getElementById('f-password').value = '';
  document.getElementById('f-role').value = u.role;
  document.getElementById('f-status').value = u.status;
  document.getElementById('pw-hint').textContent = '(leave blank to keep current)';
  document.getElementById('user-modal').classList.remove('hidden');
}
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
document.getElementById('user-modal').addEventListener('click', function(e) { if(e.target===this) closeModal('user-modal'); });
<?php if ($action === 'create'): ?>openModal('user-modal');<?php endif; ?>
</script>
</div></body></html>
