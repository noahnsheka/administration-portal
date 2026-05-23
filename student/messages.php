<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$studentAccount = getStudentAccountById($pdo, (int) ($_SESSION['user']['id'] ?? 0));
$studentName = (string) ($studentAccount['full_name'] ?? ($_SESSION['user']['full_name'] ?? 'Student'));
$announcements = getAnnouncementsForAudience($pdo, 'student', (string) ($studentAccount['class_name'] ?? ''), 20);
$studentAlerts = getStudentAlerts($pdo, (int) ($_SESSION['user']['id'] ?? 0), 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student | Messages</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('student', 'messages', $studentName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Messages from School</h1>
      <p class="mb-0 text-secondary">Official notices, circulars, and student-targeted communication will be shown here.</p>
    </section>

    <section class="content-grid-two">
      <div class="feed-card">
        <div class="section-heading"><h5>School Announcements</h5><span class="section-note">Class and school-wide feed</span></div>
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

      <div class="feed-card">
        <div class="section-heading"><h5>Your Alerts</h5><span class="section-note">Direct account notifications</span></div>
        <div class="alert-list">
          <?php if ($studentAlerts === []): ?>
            <div class="empty-state">Your direct alerts will appear here once marks or reports are updated.</div>
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
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="../assets/js/app.js"></script>
</body>
</html>

