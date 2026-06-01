<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$userName = htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8');
$studentAccounts = getStudentAccounts($pdo);
$classNames = getDistinctClassNames($pdo);
$staffActivity = getStaffActivityLogs($pdo, 4);
$announcements = getAnnouncementsForAudience($pdo, 'admin', null, 4);
$scheduledReports = (int) $pdo->query('SELECT COUNT(*) FROM report_publications')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Administration Dashboard</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260425-navfix" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'dashboard', $adminName); ?>

  <main class="page-shell dashboard-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Administration Overview</div>
      <h1 class="h3 mb-2">Welcome, <?php echo $userName; ?></h1>
      <p class="subtle-copy mb-0">Manage student records, staff oversight, exam setup, report release, and school communication from one administrative workspace.</p>
      <div class="hero-stats">
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($studentAccounts); ?></div><div class="hero-stat-label">Student accounts</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($classNames); ?></div><div class="hero-stat-label">Tracked classes</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo $scheduledReports; ?></div><div class="hero-stat-label">Scheduled reports</div></div>
      </div>
    </section>

    <section class="metrics-grid mb-4">
      <div class="metric-card">
        <div class="metric-label">👤 Active Students</div>
        <div class="metric-value"><?php echo count(array_filter($studentAccounts, static fn (array $student): bool => (int) $student['is_active'] === 1)); ?></div>
        <p class="metric-meta">Currently active in the school database.</p>
      </div>
      <div class="metric-card">
        <div class="metric-label">🔍 Recent Staff Actions</div>
        <div class="metric-value"><?php echo count($staffActivity); ?></div>
        <p class="metric-meta">Operational activity captured for review.</p>
      </div>
      <div class="metric-card">
        <div class="metric-label">📢 Recent Announcements</div>
        <div class="metric-value"><?php echo count($announcements); ?></div>
        <p class="metric-meta">Latest communication prepared for leadership.</p>
      </div>
    </section>

    <section class="workspace-section mb-4">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Primary Modules</div>
          <h2>Administrative Modules</h2>
        </div>
      </div>
      <div class="action-grid">
        <a class="dashboard-card-link" href="user-management.php"><div class="section-card action-card"><h5>Access and IDs</h5><p>Update the admin PIN, issue student IDs, assign classes, and deactivate records when required.</p></div></a>
        <a class="dashboard-card-link" href="student-directory.php"><div class="section-card action-card"><h5>Student Accounts</h5><p>Search any student profile and review marks, alerts, and report status.</p></div></a>
        <a class="dashboard-card-link" href="staff-management.php"><div class="section-card action-card"><h5>Staff Accounts</h5><p>Create staff access and define who is allowed to manage each class and subject.</p></div></a>
        <a class="dashboard-card-link" href="staff-activity.php"><div class="section-card action-card"><h5>Staff Activity</h5><p>Review recent staff actions with timestamps for accountability and follow-up.</p></div></a>
        <a class="dashboard-card-link" href="exam-management.php"><div class="section-card action-card"><h5>Exam Setup</h5><p>Maintain official exam types and academic years for clean marks entry.</p></div></a>
        <a class="dashboard-card-link" href="report-control.php"><div class="section-card action-card"><h5>Assessment and Reports</h5><p>Check readiness, identify missing marks, and publish reports only when complete.</p></div></a>
        <a class="dashboard-card-link" href="announcements.php"><div class="section-card action-card"><h5>Announcements</h5><p>Prepare school notices for students, staff, or the whole institution.</p></div></a>
        <a class="dashboard-card-link" href="server-control.php"><div class="section-card action-card"><h5>School Server Access</h5><p>Turn this computer into the shared school server and copy the URL other computers should open.</p></div></a>
      </div>
    </section>

    <section class="workspace-section">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Monitoring</div>
          <h2>Operational Activity</h2>
        </div>
      </div>
      <div class="content-grid-two">
        <div class="feed-card">
          <div class="section-heading"><h5>Latest Staff Activity</h5><span class="section-note">Operational traceability</span></div>
          <div class="feed-list">
            <?php if ($staffActivity === []): ?>
              <div class="empty-state">Staff activity will appear here once marks and records are updated.</div>
            <?php endif; ?>
            <?php foreach ($staffActivity as $activity): ?>
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars((string) $activity['staff_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="section-note"><?php echo htmlspecialchars((string) $activity['details_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta"><?php echo formatPortalDateTime((string) $activity['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="feed-card">
          <div class="section-heading"><h5>Recent Announcements</h5><span class="section-note">Communication feed</span></div>
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
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
