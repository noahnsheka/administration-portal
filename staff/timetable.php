<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$staffName = (string) ($_SESSION['user']['full_name'] ?? 'Staff Member');
$pdo = getDatabaseConnection();
$classNames = getDistinctClassNames($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Timetable</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'timetable', $staffName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Staff Timetable</h1>
      <p class="mb-0 text-secondary">Class schedules, room allocation, and substitutions will appear here.</p>
    </section>

    <section class="row g-3">
      <div class="col-lg-6">
        <div class="section-card h-100">
          <h5>Timetable Workspace</h5>
          <p class="mb-0 text-secondary">This page is prepared for room allocations, period schedules, and class-by-class planning once timetable data is connected.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="section-card h-100">
          <h5>Classes in System</h5>
          <p class="mb-2 text-secondary">Current classes available for staff operations:</p>
          <div class="feed-list">
            <?php foreach ($classNames as $className): ?>
              <div class="feed-item"><div class="feed-title"><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></div></div>
            <?php endforeach; ?>
            <?php if ($classNames === []): ?>
              <div class="empty-state">No classes have been recorded yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

