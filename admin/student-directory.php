<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$searchQuery = trim((string) ($_GET['search'] ?? ''));
$defaultAcademicContext = getDefaultAcademicContext($pdo);
$examTypes = getExamTypes($pdo);
$academicYears = getAcademicYears($pdo);
$termOptions = getAcademicTermOptions();
$selectedExamTypeId = (int) ($_GET['exam_type_id'] ?? ($defaultAcademicContext['exam_type']['id'] ?? 0));
$selectedAcademicYearId = (int) ($_GET['academic_year_id'] ?? ($defaultAcademicContext['academic_year']['id'] ?? 0));
$selectedTermName = normalizeAcademicTerm((string) ($_GET['term_name'] ?? ($defaultAcademicContext['term_name'] ?? '')));
$academicContext = buildAcademicContext($pdo, $selectedExamTypeId, $selectedTermName, $selectedAcademicYearId);
$selectedExamTypeId = (int) ($academicContext['exam_type']['id'] ?? 0);
$selectedAcademicYearId = (int) ($academicContext['academic_year']['id'] ?? 0);
$selectedTermName = (string) ($academicContext['term_name'] ?? normalizeAcademicTerm(null));
$termLabel = (string) ($academicContext['term_label'] ?? getDefaultTermLabel());
$students = searchStudentAccounts($pdo, $searchQuery);
$selectedStudentId = (int) ($_GET['student_id'] ?? ($students[0]['id'] ?? 0));
$selectedStudent = $selectedStudentId > 0 ? getStudentAccountById($pdo, $selectedStudentId) : null;
$studentAlerts = $selectedStudent ? getStudentAlerts($pdo, (int) $selectedStudent['id'], 8) : [];
$studentReport = $selectedStudent ? getStudentReportCard($pdo, (int) $selectedStudent['id'], $termLabel) : null;
$studentMarks = $selectedStudent ? getStudentMarksForTerm($pdo, (int) $selectedStudent['id'], $termLabel) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Student Accounts</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260425-navfix" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'student-directory', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Student Account Lookup</div>
      <h1 class="h3 mb-2">Search students by name or student ID and inspect their full account record.</h1>
      <p class="subtle-copy mb-0">Administrators can review status, class placement, alerts, marks, and report-card availability from one directory.</p>
    </section>

    <section class="data-card mb-4">
      <div class="section-heading">
        <h5>Search Directory</h5>
        <span class="section-note">Search by full student name or account number.</span>
      </div>
      <form method="get" class="search-bar">
        <input type="text" name="search" class="form-control" placeholder="e.g. Noah Student or STU-1001" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" />
        <select name="exam_type_id" class="form-select">
          <?php foreach ($examTypes as $examType): ?>
            <option value="<?php echo (int) $examType['id']; ?>" <?php echo (int) $examType['id'] === $selectedExamTypeId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $examType['exam_name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="term_name" class="form-select">
          <?php foreach ($termOptions as $termOption): ?>
            <option value="<?php echo htmlspecialchars($termOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $termOption === $selectedTermName ? 'selected' : ''; ?>><?php echo htmlspecialchars($termOption, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="academic_year_id" class="form-select">
          <?php foreach ($academicYears as $academicYear): ?>
            <option value="<?php echo (int) $academicYear['id']; ?>" <?php echo (int) $academicYear['id'] === $selectedAcademicYearId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $academicYear['year_label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
      </form>
    </section>

    <section class="content-grid-two">
      <div class="table-card">
        <div class="table-card-header section-heading">
          <h5>Student Directory</h5>
          <span class="section-note"><?php echo count($students); ?> result(s)</span>
        </div>
        <div class="table-responsive">
          <table class="table soft-table align-middle mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Student ID</th>
                <th>Class</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($students === []): ?>
                <tr><td colspan="5" class="empty-state">No students matched your search.</td></tr>
              <?php endif; ?>
              <?php foreach ($students as $student): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) $student['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="feed-meta">Created <?php echo formatPortalDateTime((string) $student['created_at']); ?></div>
                  </td>
                  <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $student['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $student['class_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <span class="status-pill <?php echo (int) $student['is_active'] === 1 ? 'status-pill-ready' : 'status-pill-pending'; ?>">
                      <?php echo (int) $student['is_active'] === 1 ? 'Active' : 'Deactivated'; ?>
                    </span>
                  </td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="?search=<?php echo urlencode($searchQuery); ?>&exam_type_id=<?php echo (int) $selectedExamTypeId; ?>&term_name=<?php echo urlencode($selectedTermName); ?>&academic_year_id=<?php echo (int) $selectedAcademicYearId; ?>&student_id=<?php echo (int) $student['id']; ?>">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="data-card">
          <div class="section-heading">
            <h5>Account Details</h5>
          </div>
          <?php if (!$selectedStudent): ?>
            <div class="empty-state">Select a student account from the directory to inspect it.</div>
          <?php else: ?>
            <div class="feed-list">
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars((string) $selectedStudent['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta">Student ID: <?php echo htmlspecialchars((string) $selectedStudent['account_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta">Class: <?php echo htmlspecialchars((string) $selectedStudent['class_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta">PIN: <?php echo htmlspecialchars((string) $selectedStudent['pin_code'], ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
              <div class="feed-item">
                <div class="feed-title">Report Status</div>
                <?php if ($studentReport !== null && $studentReport['visible']): ?>
                  <div class="feed-meta">Report is currently visible to the student.</div>
                  <div class="feed-meta">Average: <?php echo htmlspecialchars(number_format((float) $studentReport['summary']['average'], 2), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php elseif ($studentReport !== null): ?>
                  <div class="feed-meta"><?php echo htmlspecialchars((string) $studentReport['reason'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="feed-meta">No report information available yet.</div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="feed-card">
          <div class="section-heading">
            <h5>Student Alerts</h5>
          </div>
          <div class="alert-list">
            <?php if ($studentAlerts === []): ?>
              <div class="empty-state">No alerts are stored for this student yet.</div>
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
      </div>
    </section>

    <?php if ($selectedStudent): ?>
      <section class="table-card mt-4">
        <div class="table-card-header section-heading">
          <h5>Academic Marks Snapshot</h5>
          <span class="section-note"><?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="table-responsive">
          <table class="table soft-table align-middle mb-0">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Code</th>
                <th>Mark</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($studentMarks as $markRow): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) $markRow['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $markRow['subject_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo $markRow['mark_value'] === null ? '<span class="text-secondary">Blank</span>' : htmlspecialchars(number_format((float) $markRow['mark_value'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo formatPortalDateTime((string) ($markRow['updated_at'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>