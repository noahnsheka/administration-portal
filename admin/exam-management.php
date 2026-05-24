<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$alertType = null;
$alertMessage = null;

$flash = $_SESSION['exam_management_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    unset($_SESSION['exam_management_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_exam_type') {
            $examType = createExamType($pdo, (string) ($_POST['exam_name'] ?? ''), $adminName);
            $_SESSION['exam_management_flash'] = [
                'type' => 'success',
                'message' => 'Exam type ' . (string) $examType['exam_name'] . ' saved successfully.',
            ];
            header('Location: exam-management.php');
            exit;
        }

        if ($action === 'create_academic_year') {
            $academicYear = createAcademicYear($pdo, (string) ($_POST['year_label'] ?? ''), $adminName);
            $_SESSION['exam_management_flash'] = [
                'type' => 'success',
                'message' => 'Academic year ' . (string) $academicYear['year_label'] . ' saved successfully.',
            ];
            header('Location: exam-management.php');
            exit;
        }

        throw new RuntimeException('Unsupported exam setup action.');
    } catch (Throwable $throwable) {
        $alertType = 'danger';
        $alertMessage = $throwable->getMessage();
    }
}

$examTypes = getExamTypes($pdo, true);
$academicYears = getAcademicYears($pdo, true);
$termOptions = getAcademicTermOptions();
$defaultContext = getDefaultAcademicContext($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Exam Setup</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260425-navfix" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'exam-management', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Exam Configuration</div>
      <h1 class="h3 mb-2">Create the exam names and academic years staff must select when entering marks.</h1>
      <p class="subtle-copy mb-0">This removes spelling errors from marks entry, keeps assessment sheets aligned with reports, and ensures every class uses the same exam naming system.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="metrics-grid mb-4">
      <div class="metric-card">
        <div class="metric-label">📋 Exam Types</div>
        <div class="metric-value"><?php echo count(array_filter($examTypes, static fn (array $row): bool => (int) $row['is_active'] === 1)); ?></div>
        <p class="metric-meta">Beginning of term, mid term, end of term, and any other approved exam labels.</p>
      </div>
      <div class="metric-card">
        <div class="metric-label">📅 Academic Years</div>
        <div class="metric-value"><?php echo count(array_filter($academicYears, static fn (array $row): bool => (int) $row['is_active'] === 1)); ?></div>
        <p class="metric-meta">Teachers choose from these stored year values when entering marks.</p>
      </div>
      <div class="metric-card">
        <div class="metric-label">🎯 Default Context</div>
        <div class="metric-value"><?php echo htmlspecialchars(explode('/', (string) ($defaultContext['term_label'] ?? 'Unavailable'))[0] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
        <p class="metric-meta">This is the canonical format used in the marks, assessment, and report pipeline. <strong><?php echo htmlspecialchars((string) ($defaultContext['term_label'] ?? 'Unavailable'), ENT_QUOTES, 'UTF-8'); ?></strong></p>
      </div>
    </section>

    <section class="content-grid-two mb-4">
      <div class="data-panel">
        <div class="section-heading"><h5>Create Exam Type</h5><span class="section-note">Exam names are stored once here and reused everywhere else.</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="create_exam_type" />
          <div>
            <label class="form-label" for="examName">Exam Type Name</label>
            <input id="examName" type="text" name="exam_name" class="form-control" placeholder="e.g. Beginning of Term" />
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Save Exam Type</button>
          </div>
        </form>
      </div>

      <div class="data-panel">
        <div class="section-heading"><h5>Create Academic Year</h5><span class="section-note">Years are also stored here so teachers do not type them differently.</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="create_academic_year" />
          <div>
            <label class="form-label" for="yearLabel">Academic Year</label>
            <input id="yearLabel" type="text" name="year_label" class="form-control" placeholder="e.g. 2026" />
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Save Academic Year</button>
          </div>
        </form>
      </div>
    </section>

    <section class="content-grid-two mb-4">
      <div class="table-card">
        <div class="table-card-header section-heading">
          <h5>Stored Exam Types</h5>
          <span class="section-note">Only database-backed exam names should be used by staff.</span>
        </div>
        <div class="table-responsive">
          <table class="table soft-table align-middle mb-0">
            <thead>
              <tr>
                <th>Exam Type</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($examTypes === []): ?>
                <tr><td colspan="3" class="empty-state">No exam types have been created yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($examTypes as $examType): ?>
                <tr>
                  <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $examType['exam_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <span class="status-pill <?php echo (int) $examType['is_active'] === 1 ? 'status-pill-ready' : 'status-pill-pending'; ?>">
                      <?php echo (int) $examType['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                    </span>
                  </td>
                  <td><?php echo formatPortalDateTime((string) $examType['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card">
        <div class="table-card-header section-heading">
          <h5>Stored Academic Years</h5>
          <span class="section-note">Teachers pick one of these year values during marks entry.</span>
        </div>
        <div class="table-responsive">
          <table class="table soft-table align-middle mb-0">
            <thead>
              <tr>
                <th>Academic Year</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($academicYears === []): ?>
                <tr><td colspan="3" class="empty-state">No academic years have been created yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($academicYears as $academicYear): ?>
                <tr>
                  <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $academicYear['year_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <span class="status-pill <?php echo (int) $academicYear['is_active'] === 1 ? 'status-pill-ready' : 'status-pill-pending'; ?>">
                      <?php echo (int) $academicYear['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                    </span>
                  </td>
                  <td><?php echo formatPortalDateTime((string) $academicYear['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="feed-card">
      <div class="section-heading"><h5>Fixed Term Options</h5><span class="section-note">Terms are controlled so teachers also stop typing them differently.</span></div>
      <div class="feed-list">
        <?php foreach ($termOptions as $termOption): ?>
          <div class="feed-item">
            <div class="feed-title"><?php echo htmlspecialchars($termOption, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="feed-meta">Used with one stored exam type and one stored academic year to build the final marks context label.</div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>