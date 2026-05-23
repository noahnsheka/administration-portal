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
  <title>Student | School Highlights</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <?php renderPortalNavigation('student', 'history', $studentName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">School Highlights</h1>
      <p class="mb-0 text-secondary">Important milestones, achievements, and memorable school moments will be listed here.</p>
    </section>

    <section class="section-card">
      <h5>Historical Updates</h5>
      <p class="mb-0 text-secondary">This module is ready for a database-backed archive of school highlights and announcements.</p>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="../assets/js/app.js"></script>
</body>
</html>
