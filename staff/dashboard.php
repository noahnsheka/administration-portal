<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$staffUserId = (int) ($_SESSION['user']['id'] ?? 0);
$staffName = (string) ($_SESSION['user']['full_name'] ?? 'Staff Member');
$userName = htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8');
$assignedAllocations = getStaffTeachingAllocations($pdo, $staffUserId);
$subjects = getStaffAssignedSubjects($pdo, $staffUserId);
$classLists = getStaffAssignedClassLists($pdo, $staffUserId);
$activityLogs = array_values(array_filter(
  getStaffActivityLogs($pdo, 10),
  static fn (array $log): bool => (string) $log['staff_name'] === $staffName
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff Dashboard</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'dashboard', $staffName); ?>

  <main class="page-shell dashboard-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Staff Overview</div>
      <h1 class="h3 mb-2">Welcome, <?php echo $userName; ?></h1>
      <p class="subtle-copy mb-0">Work within your assigned classes and subjects, record marks, and follow how those updates affect assessment and reporting.</p>
      <div class="hero-stats">
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($subjects); ?></div><div class="hero-stat-label">Assigned subjects</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($classLists); ?></div><div class="hero-stat-label">Assigned class lists</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?php echo count($activityLogs); ?></div><div class="hero-stat-label">Your recent activity items</div></div>
      </div>
    </section>

    <section class="workspace-section mb-4">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Teaching Workflow</div>
          <h2>Daily Teaching Modules</h2>
        </div>
      </div>
      <div class="action-grid">
        <a class="dashboard-card-link" href="class-lists.php"><div class="section-card action-card"><h5>Class Lists</h5><p>Manage rosters, student membership, and class promotions from one place.</p></div></a>
        <a class="dashboard-card-link" href="marks-input.php"><div class="section-card action-card"><h5>Marks Input</h5><p>Load an assigned class, select the exam context, and save student marks clearly.</p></div></a>
        <a class="dashboard-card-link" href="assessment-sheet.php"><div class="section-card action-card"><h5>Assessment Sheet</h5><p>Review totals, averages, and blank marks before reports are released.</p></div></a>
        <a class="dashboard-card-link" href="timetable.php"><div class="section-card action-card"><h5>Timetable</h5><p>Check current teaching schedule information and operational notes.</p></div></a>
      </div>
    </section>

    <section class="workspace-section">
      <div class="workspace-section-header">
        <div>
          <div class="section-kicker">Current Operations</div>
          <h2>Assignments and Activity</h2>
        </div>
      </div>
      <div class="content-grid-two">
        <div class="feed-card">
          <div class="section-heading"><h5>Your Teaching Assignments</h5><span class="section-note">Class-subject combinations approved by administration</span></div>
          <div class="feed-list">
            <?php if ($assignedAllocations === []): ?>
              <div class="empty-state">No teaching allocations have been assigned to your account yet.</div>
            <?php endif; ?>
            <?php foreach ($assignedAllocations as $allocation): ?>
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars((string) $allocation['class_list_display_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta"><?php echo htmlspecialchars((string) $allocation['subject_name'] . ' (' . (string) $allocation['subject_code'] . ')', ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="feed-card">
          <div class="section-heading"><h5>Your Recent Activity</h5><span class="section-note">Most recent staff actions</span></div>
          <div class="feed-list">
            <?php if ($activityLogs === []): ?>
              <div class="empty-state">Your mark-entry activity will appear here once you submit or update results.</div>
            <?php endif; ?>
            <?php foreach ($activityLogs as $activity): ?>
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars((string) $activity['activity_type'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="section-note"><?php echo htmlspecialchars((string) $activity['details_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta"><?php echo formatPortalDateTime((string) $activity['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

