<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$studentAccount = getStudentAccountById($pdo, (int) ($_SESSION['user']['id'] ?? 0));
$studentAlerts = getStudentAlerts($pdo, (int) ($_SESSION['user']['id'] ?? 0), 6);
$announcements = getAnnouncementsForAudience($pdo, 'student', (string) ($studentAccount['class_name'] ?? ''), 6);
$reportCard = getStudentReportCard($pdo, (int) ($_SESSION['user']['id'] ?? 0), getDefaultTermLabel());

$userName = htmlspecialchars((string) ($studentAccount['full_name'] ?? ($_SESSION['user']['full_name'] ?? 'Student')), ENT_QUOTES, 'UTF-8');
$studentId = htmlspecialchars((string) ($studentAccount['account_number'] ?? ($_SESSION['user']['account_number'] ?? 'Pending assignment')), ENT_QUOTES, 'UTF-8');
$className = htmlspecialchars((string) ($studentAccount['class_name'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8');
$accountStatus = (int) ($studentAccount['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive';
$accountCreatedAt = htmlspecialchars((string) ($studentAccount['created_at'] ?? 'Available after account creation'), ENT_QUOTES, 'UTF-8');
$reportStatus = $reportCard['visible'] ? 'Report available' : ((string) ($reportCard['reason'] ?? 'Awaiting report update'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <?php renderPortalNavigation('student', 'dashboard', (string) ($studentAccount['full_name'] ?? 'Student')); ?>

  <main class="page-shell dashboard-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Student Overview</div>
      <h1 class="h3 mb-2">Welcome, <?php echo $userName; ?></h1>
      <p class="subtle-copy mb-0">Track your academic status, school notices, fee updates, and student services from one student workspace.</p>
      <div class="hero-stats">
        <div class="hero-stat"><div class="hero-stat-value"><?php echo $studentId; ?></div><div class="hero-stat-label">Student ID</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($studentAlerts); ?></div><div class="hero-stat-label">Recent alerts</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></div><div class="hero-stat-label">Current class</div></div>
      </div>
    </section>

    <section class="workspace-section mb-4">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Profile</div>
          <h2>Account and Academic Status</h2>
        </div>
      </div>
      <div class="info-grid">
        <div class="section-card h-100">
          <div class="section-heading"><h5>Account Profile</h5><span class="section-note">Core student record</span></div>
          <div class="profile-list">
            <div class="profile-row"><div class="profile-label">Student ID</div><div class="profile-value"><?php echo $studentId; ?></div></div>
            <div class="profile-row"><div class="profile-label">Class</div><div class="profile-value"><?php echo $className; ?></div></div>
            <div class="profile-row"><div class="profile-label">Account Status</div><div class="profile-value status-text"><?php echo htmlspecialchars($accountStatus, ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div class="profile-row"><div class="profile-label">Created In Database</div><div class="profile-value"><?php echo $accountCreatedAt; ?></div></div>
          </div>
        </div>

        <div class="report-card-box h-100">
          <div class="section-heading"><h5>Academic Status</h5><span class="section-note">Reports and marks</span></div>
          <span class="summary-label">Current report status</span>
          <div class="summary-value"><?php echo htmlspecialchars($reportCard['visible'] ? 'Available' : 'Pending', ENT_QUOTES, 'UTF-8'); ?></div>
          <p class="summary-subtext"><?php echo htmlspecialchars($reportStatus, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php if ($reportCard['visible']): ?>
            <p class="subtle-copy mb-0">Your report is available in the academics section where you can review subject marks, totals, and average performance.</p>
          <?php else: ?>
            <p class="subtle-copy mb-0">Report access remains locked until all subject marks are complete and administration publishes the release date.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="workspace-section mb-4">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Student Services</div>
          <h2>Available Modules</h2>
        </div>
      </div>
      <div class="action-grid">
        <a class="dashboard-card-link" href="fees-tracker.php"><div class="section-card action-card"><h5>Fees Tracker</h5><p>Track balances, payment history, and fee reminders in one place.</p></div></a>
        <a class="dashboard-card-link" href="academics.php"><div class="section-card action-card"><h5>Academic Section</h5><p>Open report cards, subject marks, and academic release updates.</p></div></a>
        <a class="dashboard-card-link" href="assessment-sheet.php"><div class="section-card action-card"><h5>Assessment Sheet</h5><p>Review the class assessment sheet with your own row highlighted.</p></div></a>
        <a class="dashboard-card-link" href="event.php"><div class="section-card action-card"><h5>Upcoming Events</h5><p>See assemblies, sports, and important school activities.</p></div></a>
        <a class="dashboard-card-link" href="history.php"><div class="section-card action-card"><h5>School Highlights</h5><p>Review notable achievements and moments from the school community.</p></div></a>
      </div>
    </section>

    <section class="workspace-section">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Latest Updates</div>
          <h2>Alerts and Announcements</h2>
        </div>
      </div>
      <div class="content-grid-two">
        <div class="feed-card">
          <div class="section-heading"><h5>Recent Alerts</h5><span class="section-note">Marks and report notices</span></div>
          <div class="alert-list">
            <?php if ($studentAlerts === []): ?>
              <div class="empty-state">Alerts will appear here when marks are entered or reports are scheduled.</div>
            <?php endif; ?>
            <?php foreach ($studentAlerts as $alertItem): ?>
              <div class="alert-item" data-tone="<?php echo htmlspecialchars((string) $alertItem['alert_type'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="alert-title"><?php echo htmlspecialchars((string) $alertItem['title_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="section-note"><?php echo htmlspecialchars((string) $alertItem['message_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="alert-meta mt-2"><?php echo formatPortalDateTime((string) $alertItem['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="feed-card">
          <div class="section-heading"><h5>Announcements</h5><span class="section-note">School and class notices</span></div>
          <div class="feed-list">
            <?php foreach ($announcements as $announcement): ?>
              <div class="feed-item">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                  <div class="feed-title"><?php echo htmlspecialchars((string) $announcement['title_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <span class="<?php echo portalBadgeClass((string) $announcement['category']); ?>"><?php echo htmlspecialchars((string) ucfirst((string) $announcement['category']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="section-note"><?php echo htmlspecialchars((string) $announcement['body_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta"><?php echo formatPortalDateTime((string) $announcement['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
