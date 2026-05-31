<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');
    $desc = trim($_POST['description'] ?? '') ?: null;

    if ($act === 'create') {
        if (!$code || !$name) { setFlash('error', 'Code and name are required.'); }
        else {
            try {
                $db->prepare("INSERT INTO subjects (subject_code,subject_name,description) VALUES (?,?,?)")->execute([$code,$name,$desc]);
                setFlash('success', 'Subject created.');
            } catch (Exception $e) { setFlash('error', $e->getMessage()); }
        }
    } elseif ($act === 'update') {
        $sid = (int)$_POST['subject_id'];
        try {
            $db->prepare("UPDATE subjects SET subject_code=?,subject_name=?,description=? WHERE id=?")->execute([$code,$name,$desc,$sid]);
            setFlash('success', 'Subject updated.');
        } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    } elseif ($act === 'delete') {
        $db->prepare("DELETE FROM subjects WHERE id=?")->execute([(int)$_POST['subject_id']]);
        setFlash('success', 'Subject deleted.');
    }
    header('Location: /grading-system/admin/subjects.php'); exit;
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM subjects WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (subject_code LIKE ? OR subject_name LIKE ?)"; $params = ["%$search%", "%$search%"]; }
$sql .= " ORDER BY subject_code";
$stmt = $db->prepare($sql); $stmt->execute($params);
$subjects = $stmt->fetchAll();

$pageTitle = 'Subjects';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-6 fade-in">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Subjects</h1>
      <p class="text-muted text-sm">Manage the school subject catalog.</p>
    </div>
    <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
      <i class="fa fa-plus"></i> Create Subject
    </button>
  </div>

  <?php include __DIR__ . '/../includes/flash.php'; ?>

  <div class="bg-white rounded-xl border border-gray-100 p-4 mb-5 flex flex-wrap gap-3 items-center">
    <form method="GET" class="flex gap-3 flex-1">
      <div class="relative flex-1 min-w-48">
        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm"></i>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search subject code or name…"
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Search</button>
      <a href="/grading-system/admin/subjects.php" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-muted hover:bg-gray-50 transition">Reset</a>
    </form>
    <span class="text-xs text-muted"><?= count($subjects) ?> subject(s)</span>
  </div>

  <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Code</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Subject Name</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Description</th>
          <th class="text-right px-5 py-3 text-xs font-semibold text-muted uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($subjects)): ?>
        <tr><td colspan="4" class="text-center py-12 text-muted"><i class="fa fa-book text-2xl text-gray-200 block mb-2"></i>No subjects found.</td></tr>
        <?php else: foreach ($subjects as $sub): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-5 py-3"><span class="font-mono text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded"><?= htmlspecialchars($sub['subject_code']) ?></span></td>
          <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($sub['subject_name']) ?></td>
          <td class="px-5 py-3 text-muted"><?= $sub['description'] ? htmlspecialchars(substr($sub['description'], 0, 60)) . (strlen($sub['description']) > 60 ? '…' : '') : '—' ?></td>
          <td class="px-5 py-3 text-right">
            <button onclick='openEditModal(<?= json_encode($sub) ?>)' class="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition mr-1"><i class="fa fa-pen"></i></button>
            <form method="POST" class="inline" onsubmit="return confirm('Delete this subject?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>">
              <button type="submit" class="text-red-500 hover:text-red-700 px-2 py-1 rounded transition"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="subject-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 id="modal-title" class="font-semibold text-gray-800">Create Subject</h2>
      <button onclick="closeModal()" class="text-muted hover:text-gray-700"><i class="fa fa-xmark text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" id="f-action" value="create">
      <input type="hidden" name="subject_id" id="f-sid" value="">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code</label>
        <input name="subject_code" id="f-code" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. MATH101">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name</label>
        <input name="subject_name" id="f-name" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. College Algebra">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-muted font-normal">(optional)</span></label>
        <textarea name="description" id="f-desc" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
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
  document.getElementById('modal-title').textContent = 'Create Subject';
  document.getElementById('f-action').value = 'create';
  document.getElementById('f-sid').value = '';
  document.getElementById('f-code').value = '';
  document.getElementById('f-name').value = '';
  document.getElementById('f-desc').value = '';
  document.getElementById('subject-modal').classList.remove('hidden');
}
function openEditModal(s) {
  document.getElementById('modal-title').textContent = 'Edit Subject';
  document.getElementById('f-action').value = 'update';
  document.getElementById('f-sid').value = s.id;
  document.getElementById('f-code').value = s.subject_code;
  document.getElementById('f-name').value = s.subject_name;
  document.getElementById('f-desc').value = s.description || '';
  document.getElementById('subject-modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('subject-modal').classList.add('hidden'); }
document.getElementById('subject-modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</div></body></html>
