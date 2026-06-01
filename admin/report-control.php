<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$classes = getDistinctClassNames($pdo);
$selectedClass = normalizeClassName((string) ($_REQUEST['class_name'] ?? ($classes[0] ?? '')));
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
$termLabel = (string) ($academicContext['term_label'] ?? getDefaultTermLabel());
$alertType = null;
$alertMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'publish_report') {
    try {
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

        $publishAt = (string) ($_POST['publish_at'] ?? '');
        if ($publishAt === '') {
            throw new RuntimeException('Choose a report publication date and time.');
        }

        scheduleReportPublication($pdo, $selectedClass, $termLabel, date('Y-m-d H:i:s', strtotime($publishAt)), $adminName);
        $alertType = 'success';
        $alertMessage = 'Report publication was scheduled successfully.';
    } catch (Throwable $throwable) {
        $alertType = 'danger';
        $alertMessage = $throwable->getMessage();
    }
}

$assessment = $selectedClass !== 'Unassigned' || in_array('Unassigned', $classes, true)
    ? evaluateReportReadiness($pdo, $selectedClass, $termLabel)
    : [
        'assessment' => [
            'subjects' => [],
            'rows' => [],
            'best_student' => null,
            'class_name' => $selectedClass,
            'term_label' => $termLabel,
        ],
        'is_ready' => false,
        'missing_students' => [],
    ];
$publication = getReportPublication($pdo, $selectedClass, $termLabel);

function renderReportControlWorkspace(
    string $selectedClass,
    string $termLabel,
    array $assessment,
    ?array $publication,
    int $selectedExamTypeId,
    string $selectedTermName,
    int $selectedAcademicYearId,
    ?string $alertType = null,
    ?string $alertMessage = null
): void {
    if ($alertMessage !== null && $alertMessage !== '') {
        ?>
        <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php
    }
    ?>
    <section class="section-card mb-4 roster-workspace-shell">
      <div class="section-kicker">Opened Class Report View</div>
      <h5 class="mb-2"><?php echo htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8'); ?> Assessment and Report View</h5>
      <p class="mb-0 text-secondary">This view confirms the class you selected and shows whether its marks are complete enough for report release.</p>
    </section>

    <section class="content-grid-two mb-4">
      <div class="data-card">
        <div class="section-heading">
          <h5>Publication Readiness</h5>
          <span class="status-pill <?php echo $assessment['is_ready'] ? 'status-pill-ready' : 'status-pill-pending'; ?>"><?php echo $assessment['is_ready'] ? 'Ready to publish' : 'Pending marks'; ?></span>
        </div>
        <?php if ($publication): ?>
          <div class="callout-note mb-3">Current publication: <?php echo formatPortalDateTime((string) $publication['publish_at']); ?> by <?php echo htmlspecialchars((string) $publication['published_by'], ENT_QUOTES, 'UTF-8'); ?>.</div>
        <?php endif; ?>
        <?php if ($assessment['is_ready']): ?>
          <form method="post" class="toolbar-form" data-async-form data-async-target="reportControlContainer" data-async-fragment="report-control-workspace">
            <input type="hidden" name="action" value="publish_report" />
            <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="exam_type_id" value="<?php echo (int) $selectedExamTypeId; ?>" />
            <input type="hidden" name="term_name" value="<?php echo htmlspecialchars($selectedTermName, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="academic_year_id" value="<?php echo (int) $selectedAcademicYearId; ?>" />
            <div>
              <label class="form-label" for="publishAt">Publish At</label>
              <input id="publishAt" type="datetime-local" name="publish_at" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" />
            </div>
            <div>
              <button type="submit" class="btn btn-primary">Publish Report Access</button>
            </div>
          </form>
        <?php else: ?>
          <div class="feed-list">
            <?php foreach ($assessment['missing_students'] as $missing): ?>
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars((string) $missing['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta"><?php echo htmlspecialchars((string) $missing['account_number'], ENT_QUOTES, 'UTF-8'); ?> is still missing <?php echo (int) $missing['missing_count']; ?> subject mark(s).</div>
              </div>
            <?php endforeach; ?>
            <?php if ($assessment['missing_students'] === []): ?>
              <div class="empty-state">No active students were found for this class yet.</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="data-card">
        <div class="section-heading">
          <h5>Class Summary</h5>
        </div>
        <div class="callout-note mb-3"><?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if ($assessment['assessment']['best_student']): ?>
          <div class="assessment-highlight">
            <div class="section-note">Best student overall</div>
            <div class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $assessment['assessment']['best_student']['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="feed-meta">Average: <?php echo htmlspecialchars(number_format((float) $assessment['assessment']['best_student']['average'], 2), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        <?php else: ?>
          <div class="empty-state">Assessment data will appear once marks are entered.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="table-card">
      <div class="table-card-header section-heading">
        <h5>Assessment Sheet</h5>
        <span class="section-note">Blank cells indicate marks that have not been filled in yet.</span>
      </div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Student</th>
              <th>Student ID</th>
              <?php foreach ($assessment['assessment']['subjects'] as $subject): ?>
                <th><?php echo htmlspecialchars((string) $subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?></th>
              <?php endforeach; ?>
              <th>Total</th>
              <th>Average</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($assessment['assessment']['rows'] === []): ?>
              <tr><td colspan="<?php echo count($assessment['assessment']['subjects']) + 5; ?>" class="empty-state">No student data is available for this class yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($assessment['assessment']['rows'] as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $row['student']['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $row['student']['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                <?php foreach ($assessment['assessment']['subjects'] as $subject): ?>
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

if (portalIsFragmentRequest('report-control-workspace')) {
    portalRenderFragment(static function () use ($selectedClass, $termLabel, $assessment, $publication, $selectedExamTypeId, $selectedTermName, $selectedAcademicYearId, $alertType, $alertMessage): void {
        renderReportControlWorkspace(
            $selectedClass,
            $termLabel,
            $assessment,
            $publication,
            $selectedExamTypeId,
            $selectedTermName,
            $selectedAcademicYearId,
            $alertType,
            $alertMessage
        );
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Report Control</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'reports', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Assessment and Publication</div>
      <h1 class="h3 mb-2">Control when report cards become visible and review the assessment sheet before release.</h1>
      <p class="subtle-copy mb-0">Reports remain hidden until every subject mark is present for the class and administration sets the publication date.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="data-card mb-4">
      <form method="get" class="toolbar-form" data-async-form data-async-target="reportControlContainer" data-async-fragment="report-control-workspace" data-async-push-url="true" data-async-scroll="true">
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
          <label class="form-label" for="className">Class</label>
          <select id="className" name="class_name" class="form-select">
            <?php foreach ($classes as $className): ?>
              <option value="<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $className === $selectedClass ? 'selected' : ''; ?>><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">Load Assessment Sheet</button>
        </div>
        <?php if ($selectedClass !== 'Unassigned'): ?>
          <div>
            <button type="button" class="btn btn-outline-primary print-hidden" onclick="window.print()">Print Assessment / Report View</button>
          </div>
        <?php endif; ?>
      </form>
    </section>

    <div id="reportControlContainer" data-async-region="report-control-workspace">
      <?php renderReportControlWorkspace($selectedClass, $termLabel, $assessment, $publication, $selectedExamTypeId, $selectedTermName, $selectedAcademicYearId); ?>
    </div>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>