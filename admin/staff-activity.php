<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$filter = trim((string) ($_GET['staff'] ?? ''));
$logs = getStaffActivityLogs($pdo, 60);

if ($filter !== '') {
    $logs = array_values(array_filter(
        $logs,
        static fn (array $log): bool => stripos((string) $log['staff_name'], $filter) !== false || stripos((string) ($log['target_reference'] ?? ''), $filter) !== false
    ));
}

$distinctStaff = [];
foreach ($logs as $log) {
    $distinctStaff[(string) $log['staff_name']] = true;
}
$latestLogTime = $logs[0]['created_at'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Staff Activity</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'staff-activity', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Operational Oversight</div>
      <h1 class="h3 mb-2">Track what staff members are doing across the academic workflow.</h1>
      <p class="subtle-copy mb-0">Every recorded action is timestamped so administration can see who entered marks and when work was done.</p>
      <div class="hero-stats">
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($logs); ?></div><div class="hero-stat-label">Activity records shown</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($distinctStaff); ?></div><div class="hero-stat-label">Staff represented</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo htmlspecialchars($latestLogTime ? date('H:i', strtotime((string) $latestLogTime)) : '--', ENT_QUOTES, 'UTF-8'); ?></div><div class="hero-stat-label">Latest captured time</div></div>
      </div>
    </section>

    <section class="data-card mb-4">
      <form method="get" class="search-bar">
        <input type="text" name="staff" class="form-control" placeholder="Filter by staff name or target reference" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" />
        <button type="submit" class="btn btn-primary">Filter</button>
      </form>
    </section>

    <section class="table-card">
      <div class="table-card-header section-heading">
        <h5>Recent Staff Activity</h5>
        <span class="section-note">Current timestamps are recorded automatically by the database.</span>
      </div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Staff Member</th>
              <th>Activity</th>
              <th>Target</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($logs === []): ?>
              <tr><td colspan="5" class="empty-state">No staff activity has been recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td><?php echo formatPortalDateTime((string) $log['created_at']); ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars((string) $log['staff_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $log['activity_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($log['target_reference'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $log['details_text'], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>