<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$staffUser = $_SESSION['user'] ?? [];
$staffName = (string) ($staffUser['full_name'] ?? 'Staff Member');
$staffId = (int) ($staffUser['id'] ?? 0);

$alertType = null;
$alertMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    try {
        if ($action === 'save_student_promotions') {
            $classListId = (int) ($_POST['class_list_id'] ?? 0);
            $termLabel = (string) ($_POST['term_label'] ?? '');
            $promotions = $_POST['promotions'] ?? [];

            if ($classListId <= 0 || $termLabel === '') {
                throw new RuntimeException('Please select both class and term.');
            }

            if (!is_array($promotions)) {
                throw new RuntimeException('Invalid promotion data.');
            }

            foreach ($promotions as $studentId => $promotion) {
                $studentId = (int) $studentId;
                $statusRemarkId = (int) ($promotion['status_remark_id'] ?? 0);
                $promotionNote = (string) ($promotion['promotion_note'] ?? '');

                if ($studentId <= 0) {
                    continue;
                }

                if ($statusRemarkId > 0) {
                    // Get class name from class_list
                    $stmt = $pdo->prepare('SELECT class_name FROM class_lists WHERE id = :id');
                    $stmt->execute(['id' => $classListId]);
                    $className = (string) ($stmt->fetchColumn() ?: '');

                    saveStudentPromotionRecord($pdo, $studentId, $className, $termLabel, $statusRemarkId, $promotionNote, $staffUser);
                }
            }

            $_SESSION['student_promotion_flash'] = [
                'type' => 'success',
                'message' => 'Student promotion records saved successfully.',
            ];
            header('Location: student-promotion.php?class_list_id=' . $classListId . '&term_label=' . urlencode($termLabel));
            exit;
        }

        throw new RuntimeException('Unsupported action.');
    } catch (Throwable $e) {
        $alertType = 'danger';
        $alertMessage = $e->getMessage();
    }
}

$flash = $_SESSION['student_promotion_flash'] ?? null;
if (is_array($flash)) {
    if ($alertType === null) {
        $alertType = (string) ($flash['type'] ?? 'info');
        $alertMessage = (string) ($flash['message'] ?? '');
    }
    unset($_SESSION['student_promotion_flash']);
}

// Get staff assigned class lists
$staffClassLists = getStaffAssignedClassLists($pdo, $staffId);

// Get selected class list and term
$selectedClassListId = (int) ($_REQUEST['class_list_id'] ?? ($staffClassLists[0]['id'] ?? 0));
$selectedTermLabel = (string) ($_REQUEST['term_label'] ?? 'Term 1');

$selectedClass = null;
$classStudents = [];
$existingPromotions = [];
$statusRemarks = [];

if ($selectedClassListId > 0) {
    $selectedClass = getClassListById($pdo, $selectedClassListId);
    $classStudents = getClassListStudents($pdo, $selectedClassListId, 'active');
    $existingPromotions = getClassListPromotionRecords($pdo, $selectedClassListId, $selectedTermLabel);
    $statusRemarks = getPromotionStatusRemarks($pdo, null, true);
}

// Map existing promotions for quick lookup
$existingPromotionMap = [];
foreach ($existingPromotions as $record) {
    $key = (int) $record['student_user_id'];
    $existingPromotionMap[$key] = $record;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Student Promotion Recording</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260601" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'student-promotion', $staffName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Student Progression</div>
      <h1 class="h3 mb-2">Record end-of-term student promotion status.</h1>
      <p class="subtle-copy mb-0">Assign promotion status (Promoted, Repeat, Change Station, etc.) for students at the end of the term or academic year.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header bg-light border-0">
        <h5 class="card-title mb-0">Select Class and Term</h5>
      </div>
      <div class="card-body">
        <form method="GET" action="student-promotion.php" class="row g-3">
          <div class="col-md-6">
            <label for="class_list_id" class="form-label">Class</label>
            <select class="form-select" id="class_list_id" name="class_list_id" onchange="this.form.submit()" required>
              <option value="">Select a class...</option>
              <?php foreach ($staffClassLists as $classList): ?>
                <option value="<?php echo (int) $classList['id']; ?>" <?php echo (int) $classList['id'] === $selectedClassListId ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="term_label" class="form-label">Term</label>
            <select class="form-select" id="term_label" name="term_label" onchange="this.form.submit()" required>
              <option value="Term 1" <?php echo $selectedTermLabel === 'Term 1' ? 'selected' : ''; ?>>Term 1</option>
              <option value="Term 2" <?php echo $selectedTermLabel === 'Term 2' ? 'selected' : ''; ?>>Term 2</option>
              <option value="Term 3" <?php echo $selectedTermLabel === 'Term 3' ? 'selected' : ''; ?>>Term 3</option>
              <option value="Year End" <?php echo $selectedTermLabel === 'Year End' ? 'selected' : ''; ?>>Year End</option>
            </select>
          </div>
        </form>
      </div>
    </div>

    <?php if ($selectedClass && $classStudents): ?>
      <form method="POST" action="student-promotion.php">
        <input type="hidden" name="action" value="save_student_promotions" />
        <input type="hidden" name="class_list_id" value="<?php echo (int) $selectedClassListId; ?>" />
        <input type="hidden" name="term_label" value="<?php echo htmlspecialchars($selectedTermLabel, ENT_QUOTES, 'UTF-8'); ?>" />

        <div class="card">
          <div class="card-header bg-light border-0">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="card-title mb-0">Student Promotion Records - <?php echo htmlspecialchars((string) $selectedClass['display_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($selectedTermLabel, ENT_QUOTES, 'UTF-8'); ?>)</h5>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Account #</th>
                    <th>Full Name</th>
                    <th>Promotion Status</th>
                    <th>Notes / Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($classStudents as $student): ?>
                    <?php
                    $studentId = (int) $student['student_user_id'];
                    $existing = $existingPromotionMap[$studentId] ?? null;
                    $existingRemarkId = $existing ? (int) $existing['status_remark_id'] : 0;
                    $existingNote = $existing ? (string) $existing['promotion_note'] : '';
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars((string) $student['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string) $student['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td>
                        <select class="form-select form-select-sm" name="promotions[<?php echo (int) $studentId; ?>][status_remark_id]">
                          <option value="">-- No status --</option>
                          <?php foreach ($statusRemarks as $remark): ?>
                            <option value="<?php echo (int) $remark['id']; ?>" <?php echo $existingRemarkId === (int) $remark['id'] ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars((string) $remark['remark_label'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <textarea class="form-control form-control-sm" name="promotions[<?php echo (int) $studentId; ?>][promotion_note]" placeholder="Optional notes..." rows="1"><?php echo htmlspecialchars($existingNote, ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="mt-4">
              <button type="submit" class="btn btn-primary">Save Promotion Records</button>
              <a href="student-promotion.php" class="btn btn-secondary">Reset</a>
            </div>
          </div>
        </div>
      </form>
    <?php elseif ($selectedClassListId > 0): ?>
      <div class="alert alert-warning">
        No students found in this class, or the class does not exist.
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        Select a class and term to record student promotion status.
      </div>
    <?php endif; ?>
  </main>

  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
