<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$userName = htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8');
$alertType = null;
$alertMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_announcement') {
  try {
    $title = trim((string) ($_POST['title_text'] ?? ''));
    $body = trim((string) ($_POST['body_text'] ?? ''));
    $audience = (string) ($_POST['audience'] ?? 'all');
    $className = trim((string) ($_POST['class_name'] ?? ''));
    $category = (string) ($_POST['category'] ?? 'general');

    if ($title === '' || $body === '') {
      throw new RuntimeException('Provide both an announcement title and message body.');
    }

    createAnnouncement($pdo, $title, $body, $audience, $adminName, $className !== '' ? normalizeClassName($className) : null, $category);
    $alertType = 'success';
    $alertMessage = 'Announcement published successfully.';
  } catch (Throwable $throwable) {
    $alertType = 'danger';
    $alertMessage = $throwable->getMessage();
  }
}

$announcements = getAnnouncementsForAudience($pdo, 'admin', null, 20);
$classes = getDistinctClassNames($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Announcements</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'announcements', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Announcements Center</h1>
      <p class="mb-0 text-secondary">Announcement drafting and publishing workspace for <?php echo $userName; ?>.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="content-grid-two mb-4">
      <div class="data-panel">
        <div class="section-heading"><h5>Create Announcement</h5><span class="section-note">Role-based and class-aware delivery</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="create_announcement" />
          <div>
            <label class="form-label" for="titleText">Title</label>
            <input id="titleText" type="text" name="title_text" class="form-control" placeholder="e.g. Reports ready for release" />
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="audience">Audience</label>
              <select id="audience" name="audience" class="form-select">
                <option value="all">All</option>
                <option value="student">Students</option>
                <option value="staff">Staff</option>
                <option value="admin">Administrators</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="className">Class</label>
              <select id="className" name="class_name" class="form-select">
                <option value="">All classes</option>
                <?php foreach ($classes as $className): ?>
                  <option value="<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="category">Category</label>
              <select id="category" name="category" class="form-select">
                <option value="general">General</option>
                <option value="reports">Reports</option>
                <option value="marks">Marks</option>
              </select>
            </div>
          </div>
          <div>
            <label class="form-label" for="bodyText">Message</label>
            <textarea id="bodyText" name="body_text" class="form-control" rows="6" placeholder="Enter the school notice here."></textarea>
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Publish Announcement</button>
          </div>
        </form>
      </div>

      <div class="feed-card">
        <div class="section-heading"><h5>Publishing Notes</h5></div>
        <div class="feed-list">
          <div class="feed-item"><div class="feed-title">Reports</div><div class="feed-meta">Use report-related notices to prepare students for scheduled report release dates.</div></div>
          <div class="feed-item"><div class="feed-title">Marks Alerts</div><div class="feed-meta">Subject mark notifications can complement the automatic alerts created when teachers submit marks.</div></div>
          <div class="feed-item"><div class="feed-title">Class Targeting</div><div class="feed-meta">Pick a class when a message should only appear to one stream or year group.</div></div>
        </div>
      </div>
    </section>

    <section class="table-card">
      <div class="table-card-header section-heading"><h5>Recent Announcements</h5></div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Title</th>
              <th>Audience</th>
              <th>Class</th>
              <th>Category</th>
              <th>Created By</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($announcements as $announcement): ?>
              <tr>
                <td><?php echo formatPortalDateTime((string) $announcement['created_at']); ?></td>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars((string) $announcement['title_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="feed-meta"><?php echo htmlspecialchars((string) $announcement['body_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                </td>
                <td><?php echo htmlspecialchars((string) ucfirst((string) $announcement['audience']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($announcement['class_name'] ?? 'All classes'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="<?php echo portalBadgeClass((string) $announcement['category']); ?>"><?php echo htmlspecialchars((string) ucfirst((string) $announcement['category']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo htmlspecialchars((string) $announcement['created_by'], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
