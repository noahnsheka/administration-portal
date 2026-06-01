<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$studentAccount = getStudentAccountById($pdo, (int) ($_SESSION['user']['id'] ?? 0));
$studentName = (string) ($studentAccount['full_name'] ?? ($_SESSION['user']['full_name'] ?? 'Student'));
$userName = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
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
$reportCard = getStudentReportCard($pdo, (int) ($_SESSION['user']['id'] ?? 0), $termLabel);
$studentAlerts = getStudentAlerts($pdo, (int) ($_SESSION['user']['id'] ?? 0), 8);
$promotionRecords = getStudentPromotionRecords($pdo, (int) ($_SESSION['user']['id'] ?? 0));
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
  <title>Student | Academics</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('student', 'academics', $studentName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Academic Section</h1>
      <p class="mb-0 text-secondary">Academic overview for <?php echo $userName; ?>.</p>
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
          <button type="submit" class="btn btn-primary">Load Report View</button>
        </div>
        <div>
          <a class="btn btn-outline-primary" href="assessment-sheet.php?exam_type_id=<?php echo (int) $selectedExamTypeId; ?>&term_name=<?php echo urlencode($selectedTermName); ?>&academic_year_id=<?php echo (int) $selectedAcademicYearId; ?>">Open Assessment Sheet</a>
        </div>
        <?php if ($reportCard['visible']): ?>
          <div>
            <button type="button" class="btn btn-outline-primary print-hidden" onclick="window.print()">Print Report</button>
          </div>
        <?php endif; ?>
      </form>
    </section>

    <section class="content-grid-two mb-4">
      <div class="report-card-box">
        <h5 class="text-primary">Report Card</h5>
        <p class="section-note"><?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($reportCard['visible']): ?>
          <p class="text-secondary mb-2">Your report is published and available.</p>
          <div class="row g-3">
            <div class="col-sm-6"><div class="summary-card"><span class="summary-label">Total</span><div class="summary-value"><?php echo htmlspecialchars(number_format((float) $reportCard['summary']['total'], 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
            <div class="col-sm-6"><div class="summary-card"><span class="summary-label">Average</span><div class="summary-value"><?php echo htmlspecialchars(number_format((float) $reportCard['summary']['average'], 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
          </div>
          <p class="section-note mt-3 mb-0">Use print view for a paper copy of this report card.</p>
        <?php else: ?>
          <p class="text-secondary mb-0"><?php echo htmlspecialchars((string) $reportCard['reason'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>

      <div class="feed-card">
        <div class="section-heading"><h5><?php echo $currentTermPromotion ? 'Promotion Status' : 'Academic Alerts'; ?></h5></div>
        <div class="alert-list">
          <?php if ($currentTermPromotion): ?>
            <div class="alert-item" data-tone="success">
              <div class="alert-title">Status: <?php echo htmlspecialchars((string) $currentTermPromotion['remark_label'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="section-note"><?php echo htmlspecialchars((string) $currentTermPromotion['remark_description'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php if ($currentTermPromotion['promotion_note']): ?>
                <div class="section-note mt-2" style="font-size: 0.9em; color: #6c757d;">Notes: <?php echo htmlspecialchars((string) $currentTermPromotion['promotion_note'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
              <div class="alert-meta mt-2">Updated: <?php echo formatPortalDateTime((string) $currentTermPromotion['updated_at']); ?></div>
            </div>
          <?php else: ?>
            <?php if ($studentAlerts === []): ?>
              <div class="empty-state">Academic alerts will appear here when marks are entered or reports are released.</div>
            <?php endif; ?>
            <?php foreach ($studentAlerts as $alertItem): ?>
              <div class="alert-item" data-tone="<?php echo htmlspecialchars((string) $alertItem['alert_type'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="alert-title"><?php echo htmlspecialchars((string) $alertItem['title_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="section-note"><?php echo htmlspecialchars((string) $alertItem['message_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="alert-meta mt-2"><?php echo formatPortalDateTime((string) $alertItem['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="table-card">
      <div class="table-card-header section-heading"><h5>Subject Marks</h5><span class="section-note"><?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Code</th>
              <th>Mark</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reportCard['marks'] as $markRow): ?>
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
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

