<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!administration_runtime_config_exists()) {
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }

  session_destroy();
  header('Location: setup.php');
  exit;
}

$currentUser = $_SESSION['user'] ?? null;
if (is_array($currentUser)) {
    $role = (string) ($currentUser['role'] ?? '');
    $redirectMap = [
        'student' => 'student/dashboard.php',
        'staff' => 'staff/dashboard.php',
        'admin' => 'admin/dashboard.php',
    ];

    if (isset($redirectMap[$role])) {
        header('Location: ' . $redirectMap[$role]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars(administration_app_name(), ENT_QUOTES, 'UTF-8'); ?> | Login</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=20260501" />
</head>
<body class="login-page">
  <main class="login-wrap">
    <section class="login-shell">
      <section class="login-showcase">
        <span class="brand-badge"><?php echo htmlspecialchars(administration_login_badge(), ENT_QUOTES, 'UTF-8'); ?></span>
        <p class="login-kicker"><?php echo htmlspecialchars(administration_login_kicker(), ENT_QUOTES, 'UTF-8'); ?></p>
        <h1 class="login-title"><?php echo htmlspecialchars(administration_login_title(), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="login-copy"><?php echo htmlspecialchars(administration_login_copy(), ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="login-stat-grid">
          <article class="login-stat">
            <div class="login-stat-value">Student IDs</div>
            <div class="login-stat-label">Issued, tracked, and controlled centrally.</div>
          </article>
          <article class="login-stat">
            <div class="login-stat-value">Role Access</div>
            <div class="login-stat-label">Dedicated workflows for students, staff, and administrators.</div>
          </article>
          <article class="login-stat">
            <div class="login-stat-value">School Updates</div>
            <div class="login-stat-label">Announcements, events, and academic activity in one place.</div>
          </article>
        </div>
      </section>

      <aside class="login-card">
        <p class="login-form-kicker">Secure Sign In</p>
        <h2 class="login-form-title">Access your workspace</h2>
        <p class="login-form-copy">Use the account number and PIN issued by your school administration office.</p>

        <form id="loginForm" novalidate>
          <div class="mb-3">
            <label for="studentId" class="form-label">Account Number</label>
            <input type="text" class="form-control" id="studentId" name="accountNumber" placeholder="e.g. STU-1042" autocomplete="username" required />
          </div>
          <div class="mb-3">
            <label for="pin" class="form-label">Unique PIN</label>
            <input type="password" class="form-control" id="pin" name="pin" placeholder="Enter PIN" autocomplete="current-password" required />
          </div>
          <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select id="role" name="role" class="form-select" required>
              <option value="student" selected>Student</option>
              <option value="staff">Staff</option>
              <option value="administrator">Administrator</option>
            </select>
          </div>
          <div id="loginAlert" class="alert alert-danger d-none" role="alert"></div>
          <button type="submit" class="btn btn-primary w-100" id="loginButton">Sign In</button>
        </form>

        <p class="login-support mb-2"><?php echo htmlspecialchars(administration_support_copy(), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (administration_setup_is_available()): ?>
          <p class="login-support mb-0"><a href="setup.php">Run client setup</a> to configure this installation for a new school.</p>
        <?php endif; ?>
      </aside>
    </section>
  </main>

  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
