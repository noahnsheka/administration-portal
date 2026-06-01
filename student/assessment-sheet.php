<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);
$studentAccount = getStudentAccountById($pdo, $studentId);
$studentName = (string) ($studentAccount['full_name'] ?? ($_SESSION['user']['full_name'] ?? 'Student'));
$className = normalizeClassName((string) ($studentAccount['class_name'] ?? 'Unassigned'));
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
$sheet = $className !== 'Unassigned' ? getAssessmentSheet($pdo, $className, $termLabel) : [
    'class_name' => $className,
    'term_label' => $termLabel,
    'subjects' => [],
    'rows' => [],
    'best_student' => null,
];

$currentStudentRow = null;
foreach ($sheet['rows'] as $row) {
    if ((int) ($row['student']['id'] ?? 0) === $studentId) {
        $currentStudentRow = $row;
        break;
    }
}
$promotionRecords = getStudentPromotionRecords($pdo, $studentId);
$currentTermPromotion = null;
foreach ($promotionRecords as $record) {
    if ((string) $record['term_label'] === $termLabel) {
        $currentTermPromotion = $record;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student | Assessment Sheet</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('student', 'assessment-sheet', $studentName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Class Assessment Sheet</div>
      <h1 class="h3 mb-2">Assessment sheet for <?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="subtle-copy mb-0">Students see the assessment sheet for their own class, and your row is highlighted so you can compare your progress with the class summary.</p>
    </section>

    <section class="data-panel mb-4">
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
          <button type="submit" class="btn btn-primary">Load Assessment Sheet</button>
        </div>
        <?php if ($className !== 'Unassigned'): ?>
          <div>
            <button type="button" class="btn btn-outline-primary print-hidden" onclick="window.print()">Print Sheet</button>
          </div>
        <?php endif; ?>
      </form>
    </section>

    <?php if ($className === 'Unassigned'): ?>
      <section class="section-card mb-4">
        <h5>No class assigned yet</h5>
        <p class="mb-0 text-secondary">Your assessment sheet will appear once your student account has been placed in a class list.</p>
      </section>
    <?php else: ?>
      <section class="content-grid-two mb-4">
        <div class="summary-card">
          <span class="summary-label">Students in class</span>
          <div class="summary-value"><?php echo count($sheet['rows']); ?></div>
          <p class="summary-subtext"><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?> for <?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="summary-card">
          <span class="summary-label">Your average</span>
          <div class="summary-value"><?php echo $currentStudentRow !== null && $currentStudentRow['average'] !== null ? htmlspecialchars(number_format((float) $currentStudentRow['average'], 2), ENT_QUOTES, 'UTF-8') : 'Pending'; ?></div>
          <p class="summary-subtext"><?php echo htmlspecialchars((string) ($academicContext['exam_type']['exam_name'] ?? $termLabel), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </section>

      <?php if ($currentTermPromotion): ?>
        <section class="table-card mb-4" style="background: #e8f5e9; border-left: 4px solid #4caf50;">
          <div class="table-card-header section-heading">
            <h5 style="color: #2e7d32;">Promotion Status - <?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></h5>
          </div>
          <div style="padding: 1.5rem;">
            <div style="font-weight: 600; color: #1b5e20; font-size: 1.1em; margin-bottom: 0.5rem;">
              Status: <?php echo htmlspecialchars((string) $currentTermPromotion['remark_label'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div style="color: #558b2f; margin-bottom: 0.5rem;">
              <?php echo htmlspecialchars((string) $currentTermPromotion['remark_description'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php if ($currentTermPromotion['promotion_note']): ?>
              <div style="color: #6c757d; font-size: 0.95em; margin-top: 0.75rem;">
                <strong>Notes:</strong> <?php echo htmlspecialchars((string) $currentTermPromotion['promotion_note'], ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>
            <div style="color: #795548; font-size: 0.9em; margin-top: 0.75rem;">
              Updated: <?php echo formatPortalDateTime((string) $currentTermPromotion['updated_at']); ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <section class="table-card">
        <div class="table-card-header section-heading">
          <h5><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?> Assessment Sheet</h5>
          <span class="section-note">Your row is highlighted. Blank cells mean marks have not been entered yet.</span>
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
                <tr><td colspan="<?php echo count($sheet['subjects']) + 4; ?>" class="empty-state">No assessment data is available for your class yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($sheet['rows'] as $row): ?>
                <tr class="<?php echo (int) ($row['student']['id'] ?? 0) === $studentId ? 'table-primary' : ''; ?>">
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
    <?php endif; ?>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
