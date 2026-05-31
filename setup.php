<?php
/**
 * Setup script — creates the default admin account.
 * DELETE or restrict this file after first run!
 */
require_once __DIR__ . '/config/database.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $email    = trim($_POST['email'] ?? 'admin@school.edu');
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6) {
        $msg = '<span class="text-red-600">Password must be at least 6 characters.</span>';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db = getDB();
        try {
            $stmt = $db->prepare("INSERT INTO users (username,email,password,role,status) VALUES (?,?,?,'admin','active')
                ON DUPLICATE KEY UPDATE password=VALUES(password), email=VALUES(email), status='active'");
            $stmt->execute([$username, $email, $hash]);
            $msg = '<span class="text-green-600">✅ Admin account created! <a href="/grading-system/index.php" class="underline">Go to Login</a></span>';
        } catch (Exception $e) {
            $msg = '<span class="text-red-600">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Setup – GradeSync</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="min-h-screen flex items-center justify-center bg-blue-50">
<div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
  <h1 class="text-xl font-bold text-gray-800 mb-1">GradeSync Setup</h1>
  <p class="text-sm text-gray-500 mb-6">Create the default admin account. <strong>Delete this file after setup!</strong></p>
  <?php if ($msg): ?><p class="mb-4 text-sm"><?= $msg ?></p><?php endif; ?>
  <form method="POST" class="space-y-4">
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
    <input name="username" value="admin" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
    <input name="email" type="email" value="admin@school.edu" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
    <input name="password" type="password" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg text-sm">Create Admin</button>
  </form>
</div>
</body></html>
