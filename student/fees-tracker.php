<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$studentAccount = getStudentAccountById($pdo, (int) ($_SESSION['user']['id'] ?? 0));
$studentName = (string) ($studentAccount['full_name'] ?? ($_SESSION['user']['full_name'] ?? 'Student'));
$userName = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
$accountNumber = htmlspecialchars((string) ($studentAccount['account_number'] ?? ($_SESSION['user']['account_number'] ?? 'Pending assignment')), ENT_QUOTES, 'UTF-8');
$className = htmlspecialchars((string) ($studentAccount['class_name'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student | Fees Tracker</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('student', 'fees', $studentName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Fees Tracker</h1>
      <p class="mb-0 text-secondary">Fee statements, payment status, and reminder workflows for <?php echo $userName; ?>.</p>
    </section>

    <section class="row g-3">
      <div class="col-md-4">
        <div class="section-card">
          <h5>Student ID</h5>
          <p class="mb-0 text-secondary"><?php echo $accountNumber; ?></p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="section-card">
          <h5>Class</h5>
          <p class="mb-0 text-secondary"><?php echo $className; ?></p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="section-card">
          <h5>Current Balance</h5>
          <p class="mb-0 text-secondary">Ready for live finance data connection.</p>
        </div>
      </div>
      <div class="col-12">
        <div class="section-card">
          <h5>Payment Timeline</h5>
          <p class="mb-0 text-secondary">Once fee records are added, this page will show invoices, payments received, due dates, and overdue reminders in a clean parent/student timeline.</p>
        </div>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

