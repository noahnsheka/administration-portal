<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

function buildMarksInputUrl(array $params): string
{
    return 'marks-input.php?' . http_build_query($params);
}

$pdo = getDatabaseConnection();
$staffUser = $_SESSION['user'];
$staffUserId = (int) ($staffUser['id'] ?? 0);
$staffName = (string) ($_SESSION['user']['full_name'] ?? 'Staff Member');
$userName = htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8');
$defaultAcademicContext = getDefaultAcademicContext($pdo);
$examTypes = getExamTypes($pdo);
$academicYears = getAcademicYears($pdo);
$termOptions = getAcademicTermOptions();
$selectedExamTypeId = (int) ($_REQUEST['exam_type_id'] ?? ($defaultAcademicContext['exam_type']['id'] ?? 0));
$selectedAcademicYearId = (int) ($_REQUEST['academic_year_id'] ?? ($defaultAcademicContext['academic_year']['id'] ?? 0));
$selectedTermName = normalizeAcademicTerm((string) ($_REQUEST['term_name'] ?? ($defaultAcademicContext['term_name'] ?? '')));
$academicContext = buildAcademicContext($pdo, $selectedExamTypeId, $selectedTermName, $selectedAcademicYearId);
$selectedExamTypeId = (int) ($academicContext['exam_type']['id'] ?? 0);
$selectedAcademicYearId = (int) ($academicContext['academic_year']['id'] ?? 0);
$selectedTermName = (string) ($academicContext['term_name'] ?? normalizeAcademicTerm(null));
$classLists = getStaffAssignedClassLists($pdo, $staffUserId);
$allowedClassListIds = [];
foreach ($classLists as $classList) {
  $allowedClassListIds[(int) $classList['id']] = true;
}

$selectedClassListId = (int) ($_REQUEST['class_list_id'] ?? ($classLists[0]['id'] ?? 0));
if ($selectedClassListId <= 0 || !isset($allowedClassListIds[$selectedClassListId])) {
  $selectedClassListId = (int) ($classLists[0]['id'] ?? 0);
}

$subjects = $selectedClassListId > 0 ? getStaffAssignedSubjects($pdo, $staffUserId, $selectedClassListId) : [];
$allowedSubjectIds = [];
foreach ($subjects as $subject) {
  $allowedSubjectIds[(int) $subject['id']] = true;
}

$selectedSubjectId = (int) ($_REQUEST['subject_id'] ?? ($subjects[0]['id'] ?? 0));
if ($selectedSubjectId <= 0 || !isset($allowedSubjectIds[$selectedSubjectId])) {
  $selectedSubjectId = (int) ($subjects[0]['id'] ?? 0);
}

$termLabel = (string) ($academicContext['term_label'] ?? getDefaultTermLabel());
$alertType = null;
$alertMessage = null;

$flash = $_SESSION['staff_marks_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    unset($_SESSION['staff_marks_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_class_marks') {
            $selectedClassListId = (int) ($_POST['class_list_id'] ?? 0);
            $selectedSubjectId = (int) ($_POST['subject_id'] ?? 0);
            $academicContext = buildAcademicContext(
              $pdo,
              (int) ($_POST['exam_type_id'] ?? 0),
              (string) ($_POST['term_name'] ?? ''),
              (int) ($_POST['academic_year_id'] ?? 0)
            );
            $selectedExamTypeId = (int) ($academicContext['exam_type']['id'] ?? $selectedExamTypeId);
            $selectedAcademicYearId = (int) ($academicContext['academic_year']['id'] ?? $selectedAcademicYearId);
            $selectedTermName = (string) ($academicContext['term_name'] ?? $selectedTermName);
            $termLabel = (string) ($academicContext['term_label'] ?? $termLabel);
            requireStaffTeachingAssignment($pdo, $staffUser, $selectedClassListId, $selectedSubjectId);
            $savedCount = saveClassListMarks(
                $pdo,
                $selectedClassListId,
                $selectedSubjectId,
                $termLabel,
                (array) ($_POST['marks'] ?? []),
                $staffUser
            );

            $_SESSION['staff_marks_flash'] = [
                'type' => 'success',
                'message' => $savedCount . ' mark(s) saved successfully for the selected class and subject.',
            ];

            header('Location: ' . buildMarksInputUrl([
                'class_list_id' => $selectedClassListId,
                'subject_id' => $selectedSubjectId,
                'exam_type_id' => $selectedExamTypeId,
                'term_name' => $selectedTermName,
                'academic_year_id' => $selectedAcademicYearId,
            ]));
            exit;
        }

        throw new RuntimeException('Unsupported marks action submitted.');
    } catch (Throwable $throwable) {
        $alertType = 'danger';
        $alertMessage = $throwable->getMessage();
    }
}

$recentEntries = array_values(array_filter(
    getStaffActivityLogs($pdo, 15),
    static fn (array $log): bool => (string) $log['staff_name'] === $staffName
));
$selectedClassList = $selectedClassListId > 0 ? getClassListById($pdo, $selectedClassListId) : null;
$markGrid = null;
if ($selectedClassList !== null && $selectedSubjectId > 0) {
    $markGrid = getClassListMarkGrid($pdo, $selectedClassListId, $selectedSubjectId, $termLabel);
}
$assessmentSheetUrl = $selectedClassList !== null
    ? 'assessment-sheet.php?' . http_build_query([
        'class_list_id' => $selectedClassListId,
        'exam_type_id' => $selectedExamTypeId,
        'term_name' => $selectedTermName,
        'academic_year_id' => $selectedAcademicYearId,
    ])
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Marks Input</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'marks-input', $staffName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Manual Marks Input</h1>
      <p class="subtle-copy mb-0">Choose the class and subject you are authorized to teach, type marks directly into the register, and save them for reports and the view-only assessment sheet.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="data-panel mb-4">
      <div class="section-heading"><h5>Open Marks Register</h5><span class="section-note">Only the class lists and subjects assigned to your account appear here.</span></div>
      <form method="get" class="toolbar-form">
        <div>
          <label class="form-label" for="examTypeId">Exam</label>
          <select id="examTypeId" name="exam_type_id" class="form-select">
            <?php foreach ($examTypes as $examType): ?>
              <option value="<?php echo (int) $examType['id']; ?>" <?php echo (int) $examType['id'] === $selectedExamTypeId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $examType['exam_name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="termName">Term</label>
          <select id="termName" name="term_name" class="form-select">
            <?php foreach ($termOptions as $termOption): ?>
              <option value="<?php echo htmlspecialchars($termOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $termOption === $selectedTermName ? 'selected' : ''; ?>><?php echo htmlspecialchars($termOption, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="academicYearId">Year</label>
          <select id="academicYearId" name="academic_year_id" class="form-select">
            <?php foreach ($academicYears as $academicYear): ?>
              <option value="<?php echo (int) $academicYear['id']; ?>" <?php echo (int) $academicYear['id'] === $selectedAcademicYearId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $academicYear['year_label'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="classListId">Class List</label>
          <select id="classListId" name="class_list_id" class="form-select">
            <?php foreach ($classLists as $classList): ?>
              <option value="<?php echo (int) $classList['id']; ?>" <?php echo (int) $classList['id'] === $selectedClassListId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="subjectId">Subject</label>
          <select id="subjectId" name="subject_id" class="form-select">
            <?php foreach ($subjects as $subject): ?>
              <option value="<?php echo (int) $subject['id']; ?>" <?php echo (int) $subject['id'] === $selectedSubjectId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">Open Register</button>
        </div>
      </form>
    </section>

    <?php if ($classLists === []): ?>
      <section class="section-card mb-4">
        <h5>No teaching allocations assigned yet</h5>
        <p class="mb-3 text-secondary">Administration has not assigned any class-subject marks work to your account yet.</p>
      </section>
    <?php elseif ($subjects === []): ?>
      <section class="section-card mb-4">
        <h5>No assigned subjects for this class</h5>
        <p class="mb-0 text-secondary">Choose a different assigned class list or ask administration to allocate a subject for this class.</p>
      </section>
    <?php elseif ($markGrid !== null): ?>
      <section class="content-grid-two mb-4">
        <div class="summary-card">
          <span class="summary-label">Loaded class list</span>
          <div class="summary-value"><?php echo htmlspecialchars((string) $markGrid['class_list']['display_name'], ENT_QUOTES, 'UTF-8'); ?></div>
          <p class="summary-subtext"><?php echo count($markGrid['rows']); ?> active student(s) in this register.</p>
        </div>
        <div class="summary-card">
          <span class="summary-label">Selected subject and context</span>
          <div class="summary-value"><?php echo htmlspecialchars((string) $markGrid['subject']['subject_code'], ENT_QUOTES, 'UTF-8'); ?></div>
          <p class="summary-subtext"><?php echo htmlspecialchars((string) $markGrid['subject']['subject_name'], ENT_QUOTES, 'UTF-8'); ?> for <?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?>.</p>
        </div>
      </section>

      <section class="table-card mb-4">
        <div class="table-card-header section-heading">
          <h5>Manual Marks Register</h5>
          <span class="section-note">Only students inside this class list appear here. Saved marks feed reports and the assessment sheet automatically.</span>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="save_class_marks" />
          <input type="hidden" name="class_list_id" value="<?php echo (int) $selectedClassListId; ?>" />
          <input type="hidden" name="subject_id" value="<?php echo (int) $selectedSubjectId; ?>" />
          <input type="hidden" name="exam_type_id" value="<?php echo (int) $selectedExamTypeId; ?>" />
          <input type="hidden" name="term_name" value="<?php echo htmlspecialchars($selectedTermName, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="academic_year_id" value="<?php echo (int) $selectedAcademicYearId; ?>" />
          <div class="table-responsive">
            <table class="table soft-table align-middle mb-0 spreadsheet-table">
              <thead>
                <tr>
                  <th style="width: 72px;">Row</th>
                  <th>Student</th>
                  <th>Student ID</th>
                  <th>Mark</th>
                  <th>Last Updated</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($markGrid['rows'] === []): ?>
                  <tr><td colspan="5" class="empty-state">No active students are assigned to this class list yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($markGrid['rows'] as $index => $row): ?>
                  <tr>
                    <td class="text-secondary fw-semibold"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars((string) $row['student']['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $row['student']['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="width: 180px;">
                      <input type="number" min="0" max="100" step="0.01" name="marks[<?php echo (int) $row['student']['id']; ?>]" class="form-control spreadsheet-input" value="<?php echo $row['mark_value'] === null ? '' : htmlspecialchars(number_format((float) $row['mark_value'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    </td>
                    <td><?php echo $row['updated_at'] ? formatPortalDateTime((string) $row['updated_at']) : '<span class="text-secondary">Not entered</span>'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="p-3 border-top d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary" <?php echo $markGrid['rows'] === [] ? 'disabled' : ''; ?>>Save Class Marks</button>
            <?php if ($assessmentSheetUrl !== null): ?>
              <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($assessmentSheetUrl, ENT_QUOTES, 'UTF-8'); ?>">View Assessment Sheet</a>
            <?php endif; ?>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <section class="content-grid-two">
      <div class="feed-card">
        <div class="section-heading"><h5>Assessment Sheet</h5><span class="section-note">View only</span></div>
        <div class="feed-list">
          <div class="feed-item">
            <div class="feed-title">Built from saved marks</div>
            <div class="feed-meta">The assessment sheet is compiled from the marks already saved in this register. It is for viewing and printing, not separate loading.</div>
          </div>
          <div class="feed-item">
            <div class="feed-title">Full class view</div>
            <div class="feed-meta">As different teachers save Geography, Mathematics, and other subjects, the database combines those marks into the full class sheet and final report flow.</div>
          </div>
        </div>
      </div>

      <div class="feed-card">
        <div class="section-heading"><h5>Your Recent Marks Activity</h5></div>
        <div class="feed-list">
          <?php if ($recentEntries === []): ?>
            <div class="empty-state">Your saved marks activity will appear here with timestamps.</div>
          <?php endif; ?>
          <?php foreach ($recentEntries as $entry): ?>
            <div class="feed-item">
              <div class="feed-title"><?php echo htmlspecialchars((string) $entry['activity_type'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="section-note"><?php echo htmlspecialchars((string) $entry['details_text'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="feed-meta"><?php echo formatPortalDateTime((string) $entry['created_at']); ?></div>
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
