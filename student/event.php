<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('student');

require_once __DIR__ . '/../includes/portal.php';

$studentName = (string) ($_SESSION['user']['full_name'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student | Events</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('student', 'events', $studentName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Upcoming School Events</h1>
      <p class="mb-0 text-secondary">Assemblies, sports days, club events, and calendar updates will be presented here.</p>
    </section>

    <section class="section-card">
      <h5>Event Calendar</h5>
      <p class="mb-0 text-secondary">This page now runs through PHP and is ready for calendar records from the administration database.</p>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

