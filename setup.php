<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$runtimeConfigExists = administration_runtime_config_exists();
$setupAllowed = administration_setup_is_available();
$errors = [];
$successMessage = null;
$createdAdministrator = null;
$sharedAccessUrl = administration_server_mode() === 'school-server' ? administration_client_application_url() : null;
$configuredServerPublicHost = administration_configured_server_public_host();

$formData = [
    'app_name' => administration_app_name(),
    'organization_name' => administration_organization_name(),
  'login_badge' => administration_login_badge(),
  'login_kicker' => administration_login_kicker(),
  'login_title' => administration_login_title(),
  'login_copy' => administration_login_copy(),
  'support_copy' => administration_support_copy(),
  'admin_brand' => administration_portal_brand('admin'),
  'staff_brand' => administration_portal_brand('staff'),
  'student_brand' => administration_portal_brand('student'),
  'footer_name' => administration_footer_name(),
    'db_host' => (string) administration_env('DB_HOST', 'localhost'),
    'db_port' => (string) administration_env('DB_PORT', '5432'),
    'db_name' => (string) administration_env('DB_NAME', 'administration_suite'),
    'db_user' => (string) administration_env('DB_USER', 'postgres'),
    'db_pass' => '',
    'app_timezone' => (string) administration_env('APP_TIMEZONE', ''),
    'seed_demo_data' => administration_env_bool('APP_SEED_DEMO_DATA', false) ? '1' : '0',
  'xampp_root' => administration_default_xampp_root(),
  'app_folder' => administration_default_app_folder(),
  'app_entry_file' => administration_default_app_entry_file(),
  'apache_sync_target' => administration_default_apache_sync_target(),
    'server_mode' => administration_server_mode(),
  'server_public_host' => administration_is_loopback_host($configuredServerPublicHost) ? '' : $configuredServerPublicHost,
    'server_port' => (string) administration_server_port(),
    'server_open_firewall' => administration_server_open_firewall() ? '1' : '0',
    'admin_account_number' => 'ADM-3001',
    'admin_full_name' => 'System Administrator',
    'admin_pin' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $defaultValue) {
        $formData[$key] = trim((string) ($_POST[$key] ?? $defaultValue));
    }

    $formData['seed_demo_data'] = isset($_POST['seed_demo_data']) ? '1' : '0';
    $formData['server_open_firewall'] = isset($_POST['server_open_firewall']) ? '1' : '0';

    if (administration_is_loopback_host($formData['server_public_host'])) {
        $formData['server_public_host'] = '';
    }

  if ($formData['xampp_root'] === '') {
    $formData['xampp_root'] = administration_default_xampp_root();
  }

  if ($formData['app_folder'] === '') {
    $formData['app_folder'] = administration_default_app_folder();
  }

  if ($formData['app_entry_file'] === '') {
    $formData['app_entry_file'] = administration_default_app_entry_file();
  }

  if ($formData['apache_sync_target'] === '') {
    $formData['apache_sync_target'] = rtrim($formData['xampp_root'], "\\/")
      . DIRECTORY_SEPARATOR
      . 'htdocs'
      . DIRECTORY_SEPARATOR
      . $formData['app_folder'];
  }

    if ($formData['server_mode'] === '') {
      $formData['server_mode'] = administration_server_mode();
    }

    if ($formData['server_port'] === '') {
      $formData['server_port'] = (string) administration_server_port();
    }

    if (!$setupAllowed) {
        $errors[] = 'Setup is locked for this installation. Delete .env.runtime or set APP_ALLOW_SETUP=1 to run setup again.';
    }

    if ($formData['app_name'] === '') {
        $errors[] = 'Enter the application name for this client.';
    }

    if ($formData['organization_name'] === '') {
        $errors[] = 'Enter the organization or school name.';
    }

    if ($formData['login_badge'] === '') {
      $errors[] = 'Enter the login badge text.';
    }

    if ($formData['login_title'] === '') {
      $errors[] = 'Enter the login title copy.';
    }

    if ($formData['login_copy'] === '') {
      $errors[] = 'Enter the login supporting copy.';
    }

    if ($formData['support_copy'] === '') {
      $errors[] = 'Enter the sign-in support message.';
    }

    if ($formData['db_host'] === '') {
        $errors[] = 'Enter the database host.';
    }

    if ($formData['db_port'] === '' || !ctype_digit($formData['db_port'])) {
        $errors[] = 'Enter a valid database port.';
    }

    if ($formData['db_name'] === '') {
        $errors[] = 'Enter the database name.';
    }

    if ($formData['db_user'] === '') {
        $errors[] = 'Enter the database username.';
    }

    if ($formData['admin_account_number'] === '') {
        $errors[] = 'Enter the administrator account number.';
    }

    if ($formData['admin_full_name'] === '') {
        $errors[] = 'Enter the administrator full name.';
    }

    if ($formData['admin_pin'] === '') {
        $errors[] = 'Enter the administrator PIN.';
    }

    if ($formData['app_timezone'] !== '' && !in_array($formData['app_timezone'], timezone_identifiers_list(), true)) {
        $errors[] = 'Choose a valid PHP timezone identifier.';
    }

    if (!in_array($formData['server_mode'], ['desktop', 'school-server'], true)) {
      $errors[] = 'Choose a valid access mode for this installation.';
    }

    if ($formData['server_port'] === '' || !ctype_digit($formData['server_port']) || (int) $formData['server_port'] < 1 || (int) $formData['server_port'] > 65535) {
      $errors[] = 'Enter a valid HTTP port between 1 and 65535.';
    }

    $formData['server_open_firewall'] = $formData['server_mode'] === 'school-server' ? '1' : '0';

    if ($errors === []) {
        $runtimeValues = [
            'APP_NAME' => $formData['app_name'],
            'APP_ORGANIZATION' => $formData['organization_name'],
        'APP_LOGIN_BADGE' => $formData['login_badge'],
        'APP_LOGIN_KICKER' => $formData['login_kicker'],
        'APP_LOGIN_TITLE' => $formData['login_title'],
        'APP_LOGIN_COPY' => $formData['login_copy'],
        'APP_SUPPORT_COPY' => $formData['support_copy'],
        'APP_FOOTER_NAME' => $formData['footer_name'],
        'APP_ADMIN_BRAND' => $formData['admin_brand'],
        'APP_STAFF_BRAND' => $formData['staff_brand'],
        'APP_STUDENT_BRAND' => $formData['student_brand'],
            'APP_TIMEZONE' => $formData['app_timezone'],
            'APP_SEED_DEMO_DATA' => $formData['seed_demo_data'],
            'APP_ALLOW_SETUP' => '0',
            'DB_HOST' => $formData['db_host'],
            'DB_PORT' => $formData['db_port'],
            'DB_NAME' => $formData['db_name'],
            'DB_USER' => $formData['db_user'],
            'DB_PASS' => $formData['db_pass'],
        'XAMPP_ROOT' => $formData['xampp_root'],
        'APP_FOLDER' => $formData['app_folder'],
        'APP_ENTRY_FILE' => $formData['app_entry_file'],
        'APACHE_SYNC_TARGET' => $formData['apache_sync_target'],
          'APP_SERVER_MODE' => $formData['server_mode'],
          'APP_SERVER_PUBLIC_HOST' => $formData['server_public_host'],
          'APP_SERVER_PORT' => $formData['server_port'],
          'APP_SERVER_OPEN_FIREWALL' => $formData['server_open_firewall'],
        ];

        foreach ($runtimeValues as $name => $value) {
            administration_put_env($name, (string) $value);
        }

        try {
            $pdo = getDatabaseConnection();
            $createdAdministrator = upsertPrimaryAdministratorAccount(
                $pdo,
                $formData['admin_account_number'],
                $formData['admin_full_name'],
                $formData['admin_pin']
            );
            administration_write_env_file($runtimeValues);
            $successMessage = 'Client setup completed. The runtime configuration file was written and the primary administrator account is ready.';
              $sharedAccessUrl = $formData['server_mode'] === 'school-server' ? administration_client_application_url() : null;
            $runtimeConfigExists = true;
            $setupAllowed = administration_setup_is_available();
            $formData['db_pass'] = '';
            $formData['admin_pin'] = '';
        } catch (Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        }
    }
}

  $configuredServerPublicHost = administration_configured_server_public_host();
  $activeServerHost = administration_server_public_host();
  $automaticHostDetectionEnabled = $configuredServerPublicHost === '' || administration_is_loopback_host($configuredServerPublicHost);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars(administration_app_name(), ENT_QUOTES, 'UTF-8'); ?> | Client Setup</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <main class="page-shell" style="max-width: 980px;">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Portable Deployment</div>
      <h1 class="h3 mb-2">Configure this installation for a new client</h1>
      <p class="subtle-copy mb-0">This setup writes a client-specific <code>.env.runtime</code>, initializes the database, and creates the first administrator account without hardcoding the school into the source code.</p>
    </section>

    <?php if ($runtimeConfigExists && !$setupAllowed): ?>
      <div class="alert alert-warning">Setup is currently locked for this installation. To run it again, set <strong>APP_ALLOW_SETUP=1</strong> or delete <code>.env.runtime</code> before reopening this page.</div>
    <?php endif; ?>

    <?php if ($successMessage !== null): ?>
      <div class="alert alert-success">
        <div class="fw-semibold mb-1"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if (is_array($createdAdministrator)): ?>
          <div>Administrator account: <strong><?php echo htmlspecialchars((string) $createdAdministrator['account_number'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php endif; ?>
        <?php if ($sharedAccessUrl !== null): ?>
          <div class="mt-2">School client URL: <strong><?php echo htmlspecialchars($sharedAccessUrl, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php endif; ?>
        <div class="mt-2"><a href="index.php">Open the login page</a></div>
      </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
      <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Setup could not be completed.</div>
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <section class="data-panel">
      <form method="post" class="dashboard-shell">
        <section class="workspace-section">
          <div class="workspace-section-header">
            <div>
              <div class="section-kicker">Client Identity</div>
              <h2>Branding</h2>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="app_name">Application Name</label>
              <input class="form-control" id="app_name" name="app_name" value="<?php echo htmlspecialchars($formData['app_name'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="organization_name">Organization or School Name</label>
              <input class="form-control" id="organization_name" name="organization_name" value="<?php echo htmlspecialchars($formData['organization_name'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="login_badge">Login Badge</label>
              <input class="form-control" id="login_badge" name="login_badge" value="<?php echo htmlspecialchars($formData['login_badge'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="login_kicker">Login Kicker</label>
              <input class="form-control" id="login_kicker" name="login_kicker" value="<?php echo htmlspecialchars($formData['login_kicker'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="footer_name">Footer Name</label>
              <input class="form-control" id="footer_name" name="footer_name" value="<?php echo htmlspecialchars($formData['footer_name'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-12">
              <label class="form-label" for="login_title">Login Title</label>
              <input class="form-control" id="login_title" name="login_title" value="<?php echo htmlspecialchars($formData['login_title'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-12">
              <label class="form-label" for="login_copy">Login Supporting Copy</label>
              <textarea class="form-control" id="login_copy" name="login_copy" rows="3" required><?php echo htmlspecialchars($formData['login_copy'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="support_copy">Support Message</label>
              <textarea class="form-control" id="support_copy" name="support_copy" rows="2" required><?php echo htmlspecialchars($formData['support_copy'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="admin_brand">Admin Portal Brand</label>
              <input class="form-control" id="admin_brand" name="admin_brand" value="<?php echo htmlspecialchars($formData['admin_brand'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="staff_brand">Staff Portal Brand</label>
              <input class="form-control" id="staff_brand" name="staff_brand" value="<?php echo htmlspecialchars($formData['staff_brand'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="student_brand">Student Portal Brand</label>
              <input class="form-control" id="student_brand" name="student_brand" value="<?php echo htmlspecialchars($formData['student_brand'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
          </div>
        </section>

        <section class="workspace-section">
          <div class="workspace-section-header">
            <div>
              <div class="section-kicker">Database</div>
              <h2>Connection Settings</h2>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="db_host">Database Host</label>
              <input class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($formData['db_host'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-2">
              <label class="form-label" for="db_port">Port</label>
              <input class="form-control" id="db_port" name="db_port" value="<?php echo htmlspecialchars($formData['db_port'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="db_name">Database Name</label>
              <input class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($formData['db_name'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="db_user">Database User</label>
              <input class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($formData['db_user'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="db_pass">Database Password</label>
              <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($formData['db_pass'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="app_timezone">Timezone</label>
              <input class="form-control" id="app_timezone" name="app_timezone" placeholder="e.g. Africa/Nairobi" value="<?php echo htmlspecialchars($formData['app_timezone'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-12">
              <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" value="1" id="seed_demo_data" name="seed_demo_data" <?php echo $formData['seed_demo_data'] === '1' ? 'checked' : ''; ?> />
                <label class="form-check-label" for="seed_demo_data">Include demo users and sample staff allocations for testing</label>
              </div>
            </div>
          </div>
        </section>

        <section class="workspace-section">
          <div class="workspace-section-header">
            <div>
              <div class="section-kicker">Windows Helpers</div>
              <h2>Launch and Sync Paths</h2>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="xampp_root">XAMPP Root</label>
              <input class="form-control" id="xampp_root" name="xampp_root" value="<?php echo htmlspecialchars($formData['xampp_root'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="app_folder">htdocs Folder Name</label>
              <input class="form-control" id="app_folder" name="app_folder" value="<?php echo htmlspecialchars($formData['app_folder'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="app_entry_file">Entry File</label>
              <input class="form-control" id="app_entry_file" name="app_entry_file" value="<?php echo htmlspecialchars($formData['app_entry_file'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-12">
              <label class="form-label" for="apache_sync_target">Apache Sync Target</label>
              <input class="form-control" id="apache_sync_target" name="apache_sync_target" value="<?php echo htmlspecialchars($formData['apache_sync_target'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional. Leave blank to use XAMPP_ROOT\\htdocs\\APP_FOLDER" />
            </div>
          </div>
        </section>

        <section class="workspace-section">
          <div class="workspace-section-header">
            <div>
              <div class="section-kicker">School Network</div>
              <h2>Main Computer Server Mode</h2>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="server_mode">Access Mode</label>
              <select class="form-select" id="server_mode" name="server_mode">
                <option value="desktop" <?php echo $formData['server_mode'] === 'desktop' ? 'selected' : ''; ?>>Single computer only</option>
                <option value="school-server" <?php echo $formData['server_mode'] === 'school-server' ? 'selected' : ''; ?>>Main computer serves other school computers</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="server_public_host">Server Host or IP</label>
              <input class="form-control" id="server_public_host" name="server_public_host" value="<?php echo htmlspecialchars($formData['server_public_host'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Leave blank to detect your LAN IP automatically" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="server_port">HTTP Port</label>
              <input class="form-control" id="server_port" name="server_port" value="<?php echo htmlspecialchars($formData['server_port'], ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-12">
              <div class="section-note mb-1">
                <?php if ($automaticHostDetectionEnabled): ?>
                  Automatic LAN IP detection is active. The shared school URL currently uses <?php echo htmlspecialchars($activeServerHost, ENT_QUOTES, 'UTF-8'); ?>.
                <?php else: ?>
                  Manual host/IP mode is active. Clear this field if the school server should follow the current LAN IP automatically.
                <?php endif; ?>
              </div>
              <div class="section-note">Use school-server mode on the main computer only. Other computers in the school will connect with a browser using the server URL shown after setup. When the launcher restarts, it will ask Windows for permission automatically so the shared server port is opened and ready to share.</div>
            </div>
          </div>
        </section>

        <section class="workspace-section">
          <div class="workspace-section-header">
            <div>
              <div class="section-kicker">Access</div>
              <h2>Primary Administrator</h2>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="admin_account_number">Administrator Account Number</label>
              <input class="form-control" id="admin_account_number" name="admin_account_number" value="<?php echo htmlspecialchars($formData['admin_account_number'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="admin_full_name">Administrator Full Name</label>
              <input class="form-control" id="admin_full_name" name="admin_full_name" value="<?php echo htmlspecialchars($formData['admin_full_name'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="admin_pin">Administrator PIN</label>
              <input type="password" class="form-control" id="admin_pin" name="admin_pin" value="<?php echo htmlspecialchars($formData['admin_pin'], ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
          </div>
        </section>

        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary">Save client setup</button>
          <a class="btn btn-outline-primary" href="index.php">Back to login</a>
        </div>
      </form>
    </section>

    <?php renderAdministrationFooter(); ?>
  </main>

  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

