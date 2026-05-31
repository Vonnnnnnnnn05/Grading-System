<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: /grading-system/{$role}/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Build display name
            $name = $user['username'];
            if ($user['role'] === 'student') {
                $s = $db->prepare("SELECT first_name, last_name FROM students WHERE user_id = ?");
                $s->execute([$user['id']]);
                $p = $s->fetch();
                if ($p) $name = $p['first_name'] . ' ' . $p['last_name'];
            } elseif ($user['role'] === 'teacher') {
                $s = $db->prepare("SELECT first_name, last_name FROM teachers WHERE user_id = ?");
                $s->execute([$user['id']]);
                $p = $s->fetch();
                if ($p) $name = $p['first_name'] . ' ' . $p['last_name'];
            }

            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['name']     = $name;

            header("Location: /grading-system/{$user['role']}/dashboard.php");
            exit;
        } else {
            $error = 'Invalid credentials or account is inactive.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-blue-100 px-4">
  <!-- Decorative blobs -->
  <div class="absolute top-0 left-0 w-96 h-96 bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-x-1/2 -translate-y-1/2"></div>
  <div class="absolute bottom-0 right-0 w-96 h-96 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 translate-x-1/2 translate-y-1/2"></div>

  <div class="relative w-full max-w-md fade-in">
    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl shadow-blue-100 p-8 border border-blue-50">
      <!-- Logo -->
      <div class="flex flex-col items-center mb-8">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl flex items-center justify-center mb-4 shadow-lg shadow-blue-200">
          <i class="fa fa-graduation-cap text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">GradeSync</h1>
        <p class="text-muted text-sm mt-1">Online Grading System</p>
      </div>

      <?php if ($error): ?>
      <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-5">
        <i class="fa fa-exclamation-circle text-red-500"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">Username or Email</label>
          <div class="relative">
            <i class="fa fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-muted text-sm"></i>
            <input id="username" name="username" type="text" required
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              placeholder="Enter your username or email"
              class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition" />
          </div>
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
          <div class="relative">
            <i class="fa fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-muted text-sm"></i>
            <input id="password" name="password" type="password" required
              placeholder="Enter your password"
              class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition" />
            <button type="button" onclick="togglePw()" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-gray-700 transition">
              <i id="pw-eye" class="fa fa-eye text-sm"></i>
            </button>
          </div>
        </div>
        <button type="submit" id="login-btn"
          class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold py-2.5 rounded-lg transition-all duration-200 shadow-md shadow-blue-200 flex items-center justify-center gap-2">
          <i class="fa fa-right-to-bracket"></i> Sign In
        </button>
      </form>

      <p class="text-center text-xs text-muted mt-6">
        Contact your administrator if you don't have an account.
      </p>
    </div>
    <p class="text-center text-xs text-muted mt-4">&copy; <?= date('Y') ?> GradeSync. All rights reserved.</p>
  </div>
</div>
<script>
function togglePw() {
  const inp = document.getElementById('password');
  const eye = document.getElementById('pw-eye');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  eye.className = inp.type === 'password' ? 'fa fa-eye text-sm' : 'fa fa-eye-slash text-sm';
}
</script>
</body>
</html>
