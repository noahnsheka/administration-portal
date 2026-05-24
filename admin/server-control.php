<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../includes/portal.php';

$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$userName = htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8');
$errors = [];
$successMessage = null;
$configuredServerPublicHost = administration_configured_server_public_host();
$refreshVisibleIpRequested = false;

$formData = [
    'server_mode' => administration_server_mode(),
  'server_public_host' => administration_is_loopback_host($configuredServerPublicHost) ? '' : $configuredServerPublicHost,
    'server_port' => (string) administration_server_port(),
    'server_open_firewall' => administration_server_open_firewall() ? '1' : '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refreshVisibleIpRequested = isset($_POST['refresh_visible_ip']);

    foreach ($formData as $key => $defaultValue) {
        $formData[$key] = trim((string) ($_POST[$key] ?? $defaultValue));
    }

    $formData['server_open_firewall'] = isset($_POST['server_open_firewall']) ? '1' : '0';

  if ($refreshVisibleIpRequested) {
    $formData['server_public_host'] = '';
  }

  if (administration_is_loopback_host($formData['server_public_host'])) {
    $formData['server_public_host'] = '';
  }

    if (!in_array($formData['server_mode'], ['desktop', 'school-server'], true)) {
        $errors[] = 'Choose a valid server access mode.';
    }

    if ($formData['server_port'] === '' || !ctype_digit($formData['server_port']) || (int) $formData['server_port'] < 1 || (int) $formData['server_port'] > 65535) {
        $errors[] = 'Enter a valid HTTP port between 1 and 65535.';
    }

    $formData['server_open_firewall'] = $formData['server_mode'] === 'school-server' ? '1' : '0';

    if ($errors === []) {
        administration_update_runtime_env([
            'APP_SERVER_MODE' => $formData['server_mode'],
            'APP_SERVER_PUBLIC_HOST' => $formData['server_public_host'],
            'APP_SERVER_PORT' => $formData['server_port'],
            'APP_SERVER_OPEN_FIREWALL' => $formData['server_open_firewall'],
        ]);

      $successMessage = $refreshVisibleIpRequested
        ? 'Visible IP refreshed. The shared URL and QR code now use the current detected LAN address. Restart the launcher on the main computer if clients still need Apache or firewall access refreshed.'
        : 'Server access settings saved. Restart the launcher on the main computer. In school-server mode, the launcher will ask Windows for permission automatically so Apache and Firewall access are refreshed, then other computers can use the shared URL.';
    }
}

$detectedAddresses = administration_detect_local_ipv4_addresses();
  $configuredServerPublicHost = administration_configured_server_public_host();
  $activeServerHost = administration_server_public_host();
  $automaticHostDetectionEnabled = $configuredServerPublicHost === '' || administration_is_loopback_host($configuredServerPublicHost);
$localUrl = administration_local_application_url();
$clientUrl = administration_client_application_url();
$clientSetupUrl = administration_client_application_url('setup.php');
$accessModeLabel = $formData['server_mode'] === 'school-server' ? 'School server enabled' : 'Single computer only';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>School Server Access</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'server-control', $adminName); ?>

  <main class="page-shell dashboard-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">School Server</div>
      <h1 class="h3 mb-2">Welcome, <?php echo $userName; ?></h1>
      <p class="subtle-copy mb-0">Use this page on the main computer to decide whether the application stays local or serves the rest of the school over the network.</p>
    </section>

    <?php if ($successMessage !== null): ?>
      <div class="alert alert-success mb-4"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
      <div class="alert alert-danger mb-4">
        <div class="fw-semibold mb-1">Server access settings could not be saved.</div>
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <section class="metrics-grid mb-4">
      <div class="metric-card">
        <div class="metric-label">🖥️ Current Mode</div>
        <div class="metric-value"><?php echo htmlspecialchars($accessModeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <p class="metric-meta">Switch to school-server mode when this machine should be the shared host.</p>
      </div>
      <div class="metric-card">
        <div class="metric-label">💻 Main Computer</div>
        <div class="metric-value" style="font-size: 1.35rem; word-break: break-all;"><?php echo htmlspecialchars($localUrl, ENT_QUOTES, 'UTF-8'); ?></div>
        <p class="metric-meta">Always works on the main computer itself.</p>
      </div>
      <div class="metric-card">
        <div class="metric-label">📱 Client URL</div>
        <div class="metric-value" style="font-size: 1.35rem; word-break: break-all;"><?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?></div>
        <p class="metric-meta">Give this address to other school computers when shared access is enabled.</p>
      </div>
    </section>

    <?php if ($formData['server_mode'] === 'school-server'): ?>
      <section class="workspace-section mb-4">
        <div class="workspace-section-header">
          <div>
            <div class="section-kicker">Client Connection</div>
            <h2>Share this URL with other devices</h2>
          </div>
        </div>
        <div class="server-share-grid">
          <article class="report-card-box server-share-card">
            <div class="section-heading">
              <h5>Client login link</h5>
              <span class="status-pill status-pill-ready">Server active</span>
            </div>
            <div class="server-share-url"><?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="server-share-actions print-hidden">
              <button type="button" class="btn btn-primary" data-copy-text="<?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?>" data-copy-label="Copy client URL">Copy client URL</button>
              <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open client page</a>
            </div>
            <ul class="server-share-steps">
              <li>Send this URL to student, staff, or admin client computers on the same network.</li>
              <li>On a phone or tablet, point the camera at the QR code to open the shared page directly.</li>
              <li>If a device cannot scan, type the URL manually into the browser.</li>
            </ul>
          </article>

          <article class="report-card-box server-qr-card">
            <div class="section-heading">
              <h5>Scan QR code</h5>
              <span class="section-note">Quick connect for mobile and camera-enabled devices</span>
            </div>
            <div class="server-qr-shell">
              <div class="server-qr-code" data-qr-code="<?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?>" data-qr-size="220" aria-label="QR code for the client login link"></div>
            </div>
            <p class="section-note mb-0 text-center">Scan this code to open the client login page at <?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?>.</p>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="workspace-section mb-4">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Detected Network</div>
          <h2>Connection Details</h2>
        </div>
      </div>
      <div class="content-grid-two">
        <div class="feed-card">
          <div class="section-heading"><h5>Detected Server Addresses</h5><span class="section-note">Use one of these if you want an explicit IP address</span></div>
          <div class="feed-list">
            <?php if ($detectedAddresses === []): ?>
              <div class="empty-state">No non-local IPv4 address was detected automatically. You can still enter the main computer hostname or IP manually below.</div>
            <?php endif; ?>
            <?php foreach ($detectedAddresses as $address): ?>
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="section-note"><?php echo htmlspecialchars(administration_format_application_url($address), ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="feed-card">
          <div class="section-heading"><h5>Client Instructions</h5><span class="section-note">What other school computers should do</span></div>
          <div class="feed-list">
            <div class="feed-item">
              <div class="feed-title">Daily access</div>
              <div class="section-note"><?php echo htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="feed-item">
              <div class="feed-title">First-run setup page</div>
              <div class="section-note"><?php echo htmlspecialchars($clientSetupUrl, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="feed-item">
              <div class="feed-title">Restart required after changes</div>
              <div class="section-note">After saving server access settings, restart the launcher on the main computer. In school-server mode, the launcher will request Windows permission automatically and then clients can use the LAN URL shown above.</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="workspace-section">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Configuration</div>
          <h2>Server Access Settings</h2>
        </div>
      </div>
      <form method="post" class="row g-3">
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
              Automatic LAN IP detection is active. The shared URL and QR code currently use <?php echo htmlspecialchars($activeServerHost, ENT_QUOTES, 'UTF-8'); ?>.
            <?php else: ?>
              Manual host/IP mode is active. Clear this field and save if you want the shared URL and QR code to follow the current LAN IP automatically.
            <?php endif; ?>
          </div>
          <div class="section-note">When school-server mode is enabled, the launcher automatically refreshes the Windows Firewall rule for this server port on the main computer.</div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary">Save server settings</button>
          <button type="submit" class="btn btn-outline-primary" name="refresh_visible_ip" value="1">Refresh visible IP</button>
          <a class="btn btn-outline-primary" href="dashboard.php">Back to dashboard</a>
        </div>
      </form>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/qrcode/qrcode.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>