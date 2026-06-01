<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

function buildTeacherRemarksUrl(array $params): string
{
    return 'teacher-remarks.php?' . http_build_query($params);
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

$flash = $_SESSION['staff_remarks_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    unset($_SESSION['staff_remarks_flash']);
}

$gradingSystems = getGradingSystems($pdo);
$defaultGradingSystem = getDefaultGradingSystem($pdo);
$selectedSystemId = (int) ($_REQUEST['grading_system_id'] ?? ($defaultGradingSystem['id'] ?? ($gradingSystems[0]['id'] ?? 0)));
$selectedSystem = $selectedSystemId > 0 ? getGradingSystemById($pdo, $selectedSystemId) : null;
$gradingScales = $selectedSystemId > 0 ? getGradingScalesBySystem($pdo, $selectedSystemId) : [];
$remarkTemplates = $selectedSystemId > 0 ? getTeacherRemarkTemplates($pdo, $selectedSystemId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_student_remarks') {
            $selectedClassListId = (int) ($_POST['class_list_id'] ?? 0);
            $selectedSubjectId = (int) ($_POST['subject_id'] ?? 0);
            $selectedSystemId = (int) ($_POST['grading_system_id'] ?? 0);
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
            
            $selectedClass = getClassListById($pdo, $selectedClassListId);
            $className = $selectedClass ? (string) $selectedClass['display_name'] : 'Unknown';
            
            $savedCount = 0;
            $remarks = (array) ($_POST['remarks'] ?? []);
            
            foreach ($remarks as $studentId => $remarkData) {
                $studentId = (int) $studentId;
                $gradeLabel = trim((string) ($remarkData['grade_label'] ?? ''));
                $remarkText = trim((string) ($remarkData['remark_text'] ?? ''));
                
                if ($studentId <= 0 || $gradeLabel === '' || $remarkText === '') {
                    continue;
                }
                
                try {
                    saveStudentRemark(
                        $pdo,
                        $studentId,
                        $selectedSubjectId,
                        $className,
                        $termLabel,
                        $selectedSystemId,
                        $gradeLabel,
                        $remarkText,
                        $staffUser
                    );
                    $savedCount++;
                } catch (Throwable $e) {
                    // Log but continue with other remarks
                }
            }

            $_SESSION['staff_remarks_flash'] = [
                'type' => 'success',
                'message' => $savedCount . ' remark(s) saved successfully.',
            ];

            header('Location: ' . buildTeacherRemarksUrl([
                'class_list_id' => $selectedClassListId,
                'subject_id' => $selectedSubjectId,
                'grading_system_id' => $selectedSystemId,
                'exam_type_id' => $selectedExamTypeId,
                'term_name' => $selectedTermName,
                'academic_year_id' => $selectedAcademicYearId,
            ]));
            exit;
        }

        throw new RuntimeException('Unsupported remarks action submitted.');
    } catch (Throwable $throwable) {
        $alertType = 'danger';
        $alertMessage = $throwable->getMessage();
    }
}

$selectedClassList = $selectedClassListId > 0 ? getClassListById($pdo, $selectedClassListId) : null;
$studentRemarks = null;
$classStudents = null;

if ($selectedClassList !== null && $selectedSubjectId > 0 && $selectedSystemId > 0) {
    $classStudents = getClassListStudents($pdo, $selectedClassListId, 'active');
    $studentRemarks = getClassListRemarks($pdo, $selectedClassListId, $selectedSubjectId, $termLabel);
    
    // Index remarks by student ID for easier lookup
    $remarksIndex = [];
    foreach ($studentRemarks as $remark) {
        $remarksIndex[(int) $remark['student_user_id']] = $remark;
    }
    $studentRemarks = $remarksIndex;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Teacher Remarks</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'teacher-remarks', $staffName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <h1 class="h3 mb-2">Teacher Remarks</h1>
      <p class="subtle-copy mb-0">Enter qualitative remarks for each student based on the grading system. Select the class, subject, grading scale, and term to begin adding remarks.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- Context Selection -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="GET" action="teacher-remarks.php">
          <div class="row g-3">
            <div class="col-md-3">
              <label for="class_list_id" class="form-label">Select Class</label>
              <select class="form-select" id="class_list_id" name="class_list_id" onchange="this.form.submit()">
                <option value="">-- Select Class --</option>
                <?php foreach ($classLists as $classList): ?>
                  <option value="<?php echo (int) $classList['id']; ?>" <?php echo (int) $classList['id'] === $selectedClassListId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label for="subject_id" class="form-label">Select Subject</label>
              <select class="form-select" id="subject_id" name="subject_id" onchange="this.form.submit()">
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $subject): ?>
                  <option value="<?php echo (int) $subject['id']; ?>" <?php echo (int) $subject['id'] === $selectedSubjectId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label for="grading_system_id" class="form-label">Grading System</label>
              <select class="form-select" id="grading_system_id" name="grading_system_id" onchange="this.form.submit()">
                <option value="">-- Select Grading System --</option>
                <?php foreach ($gradingSystems as $system): ?>
                  <option value="<?php echo (int) $system['id']; ?>" <?php echo (int) $system['id'] === $selectedSystemId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $system['system_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label for="term_name" class="form-label">Term</label>
              <select class="form-select" id="term_name" name="term_name" onchange="this.form.submit()">
                <?php foreach ($termOptions as $option): ?>
                  <option value="<?php echo htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) $option['value'] === $selectedTermName ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Remarks Entry Form -->
    <?php if ($selectedClassList && $selectedSubjectId > 0 && $selectedSystemId > 0): ?>
      <?php if ($classStudents): ?>
        <form method="POST" action="teacher-remarks.php">
          <input type="hidden" name="action" value="save_student_remarks" />
          <input type="hidden" name="class_list_id" value="<?php echo (int) $selectedClassListId; ?>" />
          <input type="hidden" name="subject_id" value="<?php echo (int) $selectedSubjectId; ?>" />
          <input type="hidden" name="grading_system_id" value="<?php echo (int) $selectedSystemId; ?>" />
          <input type="hidden" name="exam_type_id" value="<?php echo (int) $selectedExamTypeId; ?>" />
          <input type="hidden" name="term_name" value="<?php echo htmlspecialchars((string) $selectedTermName, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="academic_year_id" value="<?php echo (int) $selectedAcademicYearId; ?>" />

          <div class="card">
            <div class="card-header bg-light border-0">
              <h5 class="card-title mb-0">Student Remarks for <?php echo htmlspecialchars((string) $selectedSystem['system_name'] ?? 'Selected System', ENT_QUOTES, 'UTF-8'); ?></h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead class="table-light">
                    <tr>
                      <th width="25%">Student Name</th>
                      <th width="15%">Grade</th>
                      <th width="60%">Remark</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($classStudents as $student): ?>
                      <?php 
                        $studentId = (int) $student['student_user_id'];
                        $existingRemark = $studentRemarks[$studentId] ?? null;
                        $currentGrade = $existingRemark ? (string) $existingRemark['grade_label'] : '';
                        $currentRemarkText = $existingRemark ? (string) $existingRemark['remark_text'] : '';
                      ?>
                      <tr>
                        <td>
                          <strong><?php echo htmlspecialchars((string) $student['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                          <br><small class="text-muted"><?php echo htmlspecialchars((string) $student['account_number'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                          <select class="form-select form-select-sm" name="remarks[<?php echo (int) $studentId; ?>][grade_label]" data-grade-select>
                            <option value="">Select...</option>
                            <?php foreach ($gradingScales as $scale): ?>
                              <option value="<?php echo htmlspecialchars((string) $scale['grade_label'], ENT_QUOTES, 'UTF-8'); ?>" 
                                <?php echo (string) $scale['grade_label'] === $currentGrade ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $scale['grade_label'] . ' - ' . $scale['grade_name'], ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td>
                          <textarea class="form-control form-control-sm" name="remarks[<?php echo (int) $studentId; ?>][remark_text]" rows="2" placeholder="Enter remark..."><?php echo htmlspecialchars($currentRemarkText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                          <?php if ($remarkTemplates): ?>
                            <div class="mt-2">
                              <small class="text-muted d-block mb-1">Quick templates:</small>
                              <div class="btn-group-vertical btn-group-sm w-100">
                                <?php foreach ($remarkTemplates as $template): ?>
                                  <button type="button" class="btn btn-outline-secondary text-start" data-remark-template="<?php echo htmlspecialchars((string) $template['remark_template'], ENT_QUOTES, 'UTF-8'); ?>" data-student-id="<?php echo (int) $studentId; ?>">
                                    <small><?php echo htmlspecialchars((string) $template['grade_label'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars(substr((string) $template['remark_template'], 0, 50), ENT_QUOTES, 'UTF-8'); ?>...</small>
                                  </button>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer bg-light border-top">
              <button type="submit" class="btn btn-primary">Save All Remarks</button>
              <a href="teacher-remarks.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      <?php else: ?>
        <div class="alert alert-info">No students found in the selected class.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-info">Select a class, subject, and grading system to enter remarks.</div>
    <?php endif; ?>
  </main>

  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script>
    document.querySelectorAll('[data-remark-template]').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        const template = this.getAttribute('data-remark-template');
        const studentId = this.getAttribute('data-student-id');
        const textarea = document.querySelector(`textarea[name="remarks[${studentId}][remark_text]"]`);
        if (textarea) {
          textarea.value = template;
          textarea.focus();
        }
      });
    });
  </script>
</body>
</html>
