<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$staffUser = $_SESSION['user'];
$staffUserId = (int) ($staffUser['id'] ?? 0);
$staffName = (string) ($_SESSION['user']['full_name'] ?? 'Staff Member');
$classLists = getStaffAssignedClassLists($pdo, $staffUserId);
$allowedClassListIds = [];
foreach ($classLists as $classList) {
  $allowedClassListIds[(int) $classList['id']] = true;
}

$selectedClassListId = (int) ($_GET['class_list_id'] ?? ($classLists[0]['id'] ?? 0));
if ($selectedClassListId <= 0 || !isset($allowedClassListIds[$selectedClassListId])) {
  $selectedClassListId = (int) ($classLists[0]['id'] ?? 0);
}

$selectedClassList = $selectedClassListId > 0 ? getClassListById($pdo, $selectedClassListId) : null;
$selectedClass = (string) ($selectedClassList['display_name'] ?? 'Unassigned');
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
$sheet = $selectedClassList !== null ? getAssessmentSheet($pdo, $selectedClass, $termLabel) : [
  'class_name' => $selectedClass,
  'term_label' => $termLabel,
  'subjects' => [],
  'rows' => [],
  'best_student' => null,
];

function renderStaffAssessmentSheetWorkspace(
    array $classLists,
    ?array $selectedClassList,
    string $selectedClass,
    string $termLabel,
    array $academicContext,
    array $sheet
): void {
    if ($classLists === []) {
        ?>
        <section class="section-card mb-4">
          <h5>No assigned classes</h5>
          <p class="mb-0 text-secondary">Administration has not assigned any class assessment sheet to your account yet.</p>
        </section>
        <?php
        return;
    }
    ?>
    <section class="section-card mb-4 roster-workspace-shell">
      <div class="section-kicker">Opened Assessment Sheet</div>
      <h5 class="mb-2"><?php echo htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8'); ?> Assessment Sheet</h5>
      <p class="mb-0 text-secondary">You are viewing the saved-mark sheet for <?php echo htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8'); ?> in <?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?>.</p>
    </section>

    <section class="content-grid-two mb-4">
      <div class="summary-card">
        <span class="summary-label">Students in class</span>
        <div class="summary-value"><?php echo count($sheet['rows']); ?></div>
        <p class="summary-subtext">Class: <?php echo htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8'); ?>.</p>
      </div>
      <div class="summary-card">
        <span class="summary-label">Selected exam</span>
        <div class="summary-value"><?php echo htmlspecialchars((string) (($academicContext['exam_type']['exam_name'] ?? 'Pending')), ENT_QUOTES, 'UTF-8'); ?></div>
        <p class="summary-subtext"><?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    </section>

    <section class="table-card">
      <div class="table-card-header section-heading">
        <h5><?php echo htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8'); ?> Assessment Sheet</h5>
        <span class="section-note">Blank cells mean that subject marks have not yet been saved in marks input.</span>
      </div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Student</th>
              <th>Student ID</th>
              <?php foreach ($sheet['subjects'] as $subject): ?>
                <th><?php echo htmlspecialchars((string) $subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?></th>
              <?php endforeach; ?>
              <th>Total</th>
              <th>Average</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($sheet['rows'] === []): ?>
              <tr><td colspan="<?php echo count($sheet['subjects']) + 4; ?>" class="empty-state">No students or marks found for this class.</td></tr>
            <?php endif; ?>
            <?php foreach ($sheet['rows'] as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $row['student']['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $row['student']['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                <?php foreach ($sheet['subjects'] as $subject): ?>
                  <?php $markValue = $row['marks'][(int) $subject['id']] ?? null; ?>
                  <td><?php echo $markValue === null ? '<span class="text-secondary">Blank</span>' : htmlspecialchars(number_format((float) $markValue, 2), ENT_QUOTES, 'UTF-8'); ?></td>
                <?php endforeach; ?>
                <td><?php echo $row['total'] === null ? '<span class="text-secondary">-</span>' : htmlspecialchars(number_format((float) $row['total'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $row['average'] === null ? '<span class="text-secondary">-</span>' : htmlspecialchars(number_format((float) $row['average'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php
}

if (portalIsFragmentRequest('staff-assessment-workspace')) {
    portalRenderFragment(static function () use ($classLists, $selectedClassList, $selectedClass, $termLabel, $academicContext, $sheet): void {
        renderStaffAssessmentSheetWorkspace($classLists, $selectedClassList, $selectedClass, $termLabel, $academicContext, $sheet);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Assessment Sheet</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'assessment-sheet', $staffName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Assessment Sheet</div>
      <h1 class="h3 mb-2">View the compiled class sheet from the marks already saved by teachers.</h1>
      <p class="subtle-copy mb-0">Only classes assigned to your account are available here, and the sheet is generated directly from saved subject marks for this exam context.</p>
    </section>

    <section class="data-panel mb-4">
      <form method="get" class="toolbar-form" data-async-form data-async-target="staffAssessmentSheetContainer" data-async-fragment="staff-assessment-workspace" data-async-push-url="true" data-async-scroll="true">
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
          <label class="form-label" for="classListId">Class</label>
          <select id="classListId" name="class_list_id" class="form-select">
            <?php foreach ($classLists as $classList): ?>
              <option value="<?php echo (int) $classList['id']; ?>" <?php echo (int) $classList['id'] === $selectedClassListId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">View Sheet</button>
        </div>
        <?php if ($selectedClassList !== null): ?>
          <div>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print Sheet</button>
          </div>
        <?php endif; ?>
      </form>
    </section>

    <div id="staffAssessmentSheetContainer" data-async-region="staff-assessment-workspace">
      <?php renderStaffAssessmentSheetWorkspace($classLists, $selectedClassList, $selectedClass, $termLabel, $academicContext, $sheet); ?>
    </div>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
