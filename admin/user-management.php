<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$adminUserId = (int) ($_SESSION['user']['id'] ?? 0);
$adminAccountNumber = (string) ($_SESSION['user']['account_number'] ?? '');
$displayAdminName = htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8');
$displayAdminAccountNumber = htmlspecialchars($adminAccountNumber, ENT_QUOTES, 'UTF-8');
$classOptions = getDistinctClassNames($pdo);

$alertType = null;
$alertMessage = null;
$generatedAccounts = [];
$generationForm = [
  'class_name' => trim((string) ($_POST['class_name'] ?? '')),
  'student_names' => (string) ($_POST['student_names'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
    if ($action === 'update_admin_pin') {
      $administrator = updateAdministratorPin($pdo, $adminUserId, (string) ($_POST['pin_code'] ?? ''));

      $_SESSION['user']['account_number'] = (string) $administrator['account_number'];
      $_SESSION['user']['full_name'] = (string) $administrator['full_name'];
      $_SESSION['user']['role'] = 'admin';
      $_SESSION['user_management_flash'] = [
        'type' => 'success',
        'message' => 'Administrator PIN updated. New PIN: ' . (string) $administrator['pin_code'] . '.',
        'generated_accounts' => [],
      ];

      header('Location: user-management.php');
      exit;
    }

        if ($action === 'generate_ids') {
            $studentNames = preg_split('/\R/', (string) ($_POST['student_names'] ?? '')) ?: [];
          $generationForm['class_name'] = trim((string) ($_POST['class_name'] ?? ''));
          $className = normalizeClassName($generationForm['class_name']);
          $generationForm['student_names'] = (string) ($_POST['student_names'] ?? '');
          $generatedAccounts = createStudentAccounts($pdo, $studentNames, $adminName, $className);

            if ($generatedAccounts === []) {
                throw new RuntimeException('Enter at least one student name to generate IDs.');
            }

          $alertType = 'success';
          $alertMessage = count($generatedAccounts) . ' student ID(s) generated successfully.';

          if (portalIsFragmentRequest('student-id-generation')) {
            $generationForm = [
              'class_name' => '',
              'student_names' => '',
            ];
          }

          if (!portalIsFragmentRequest('student-id-generation')) {
            $_SESSION['user_management_flash'] = [
              'type' => 'success',
              'message' => $alertMessage,
              'generated_accounts' => $generatedAccounts,
            ];

            header('Location: user-management.php');
            exit;
          }
        }

        if ($action === 'deactivate_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));

            if ($studentId <= 0) {
                throw new RuntimeException('Select a valid student account to deactivate.');
            }

            if (!deactivateStudentAccount($pdo, $studentId, $adminName, $reason)) {
                throw new RuntimeException('The selected student account could not be found.');
            }

      $_SESSION['user_management_flash'] = [
                'type' => 'warning',
                'message' => 'Student ID was deactivated and recorded in the activity log.',
                'generated_accounts' => [],
            ];

            header('Location: user-management.php');
            exit;
        }

        throw new RuntimeException('Unsupported action submitted.');
    } catch (Throwable $throwable) {
        $alertType = 'danger';
        $alertMessage = $throwable->getMessage();
    }
}

$flash = $_SESSION['user_management_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    $generatedAccounts = is_array($flash['generated_accounts'] ?? null) ? $flash['generated_accounts'] : [];
  unset($_SESSION['user_management_flash']);
}

$studentAccounts = getStudentAccounts($pdo);
$studentActivity = getStudentIdActivity($pdo, 15);
$nextAccountNumber = formatStudentAccountNumber(getNextStudentSequence($pdo) + 1);
$activeStudentCount = count(array_filter($studentAccounts, static fn (array $student): bool => (int) $student['is_active'] === 1));
$inactiveStudentCount = count($studentAccounts) - $activeStudentCount;

function renderStudentIdGenerationWorkspace(
    array $classOptions,
    array $generationForm,
    int $activeStudentCount,
    int $inactiveStudentCount,
    string $nextAccountNumber,
    array $generatedAccounts,
    ?string $alertType = null,
    ?string $alertMessage = null
): void {
    if ($alertMessage !== null && $alertMessage !== '') {
        ?>
        <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4" role="alert">
          <?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php
    }
    ?>
    <section class="row g-3 mb-4">
      <div class="col-lg-7">
        <div class="section-card roster-workspace-shell">
          <div class="section-kicker">Generate Student IDs</div>
          <h5 class="mb-3">Student ID Generator</h5>
          <p class="text-secondary">Paste one student name per line and assign the class now. The system will generate the next IDs in order from the last existing student account and assign a unique PIN for each student.</p>
          <form method="post" data-async-form data-async-target="studentIdGenerationContainer" data-async-fragment="student-id-generation">
            <input type="hidden" name="action" value="generate_ids" />
            <div class="mb-3">
              <label for="className" class="form-label">Class / Stream</label>
              <input id="className" name="class_name" class="form-control" list="classOptions" placeholder="e.g. Senior 1A" value="<?php echo htmlspecialchars((string) $generationForm['class_name'], ENT_QUOTES, 'UTF-8'); ?>" required />
              <div class="form-text">A new student cannot be created without a class assignment.</div>
              <datalist id="classOptions">
                <?php foreach ($classOptions as $classOption): ?>
                  <option value="<?php echo htmlspecialchars($classOption, ENT_QUOTES, 'UTF-8'); ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="mb-3">
              <label for="studentNames" class="form-label">New Students or Students Without IDs</label>
              <textarea id="studentNames" name="student_names" class="form-control" rows="8" placeholder="Amina Yusuf&#10;Brian Ochieng&#10;Faith Njeri"><?php echo htmlspecialchars((string) $generationForm['student_names'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Generate Student IDs</button>
          </form>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="section-card h-100">
          <h5 class="mb-3">Registry Summary</h5>
          <div class="row g-3">
            <div class="col-6">
              <div class="border rounded-4 p-3 h-100">
                <div class="text-secondary small">Active Students</div>
                <div class="display-6 fw-semibold text-primary"><?php echo $activeStudentCount; ?></div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded-4 p-3 h-100">
                <div class="text-secondary small">Deactivated IDs</div>
                <div class="display-6 fw-semibold text-primary"><?php echo $inactiveStudentCount; ?></div>
              </div>
            </div>
            <div class="col-12">
              <div class="border rounded-4 p-3 h-100">
                <div class="text-secondary small">Next Generated ID</div>
                <div class="h4 mb-1 text-primary"><?php echo htmlspecialchars($nextAccountNumber, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small text-secondary">Every generated ID is stored with the student name, PIN, admin user, and action history.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <?php if ($generatedAccounts !== []): ?>
      <section class="section-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <h5 class="mb-0">Latest Generated Accounts</h5>
          <span class="text-secondary small">Share the account number and PIN with the student so they can log in immediately.</span>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Student ID</th>
                <th>Class</th>
                <th>PIN</th>
                <th>Batch</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($generatedAccounts as $generatedAccount): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) $generatedAccount['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $generatedAccount['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $generatedAccount['class_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $generatedAccount['pin_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $generatedAccount['batch_reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
    <?php
}

if (portalIsFragmentRequest('student-id-generation')) {
    portalRenderFragment(static function () use ($classOptions, $generationForm, $activeStudentCount, $inactiveStudentCount, $nextAccountNumber, $generatedAccounts, $alertType, $alertMessage): void {
        renderStudentIdGenerationWorkspace($classOptions, $generationForm, $activeStudentCount, $inactiveStudentCount, $nextAccountNumber, $generatedAccounts, $alertType, $alertMessage);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Access and IDs</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css?v=20260425-navfix" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'student-ids', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <span class="brand-badge">Section 4</span>
          <h1 class="h3 mb-2">Access and Student IDs</h1>
          <p class="mb-0 text-secondary">Update your admin PIN, create student IDs in sequence, assign classes, deactivate access for leavers, and keep the full activity trail in the database.</p>
        </div>
        <div class="text-end">
          <div class="fw-semibold text-primary">Signed in as <?php echo $displayAdminName; ?></div>
          <div class="text-secondary small">Next student ID: <?php echo htmlspecialchars($nextAccountNumber, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>
    </section>

    <?php if ($alertMessage && !portalIsFragmentRequest('student-id-generation')): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4" role="alert">
        <?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <section class="section-card mb-4">
      <div class="row g-4 align-items-end">
        <div class="col-lg-7">
          <div class="section-kicker">Administrator Access</div>
          <h5 class="mb-3">Change Your Admin PIN</h5>
          <p class="text-secondary mb-0">This page now includes administrator credential management. Update the PIN for the signed-in admin account here whenever you need to reset it.</p>
        </div>
        <div class="col-lg-5">
          <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="update_admin_pin" />
            <div class="col-12">
              <div class="border rounded-4 p-3 h-100">
                <div class="text-secondary small">Administrator login</div>
                <div class="fw-semibold text-primary"><?php echo $displayAdminAccountNumber; ?></div>
                <div class="small text-secondary"><?php echo $displayAdminName; ?></div>
              </div>
            </div>
            <div class="col-sm-8">
              <label for="adminPinCode" class="form-label">New admin PIN</label>
              <input id="adminPinCode" type="text" name="pin_code" class="form-control" inputmode="numeric" autocomplete="off" placeholder="e.g. 1234" required />
            </div>
            <div class="col-sm-4 d-grid">
              <button type="submit" class="btn btn-primary">Save PIN</button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <div id="studentIdGenerationContainer" data-async-region="student-id-generation">
      <?php renderStudentIdGenerationWorkspace($classOptions, $generationForm, $activeStudentCount, $inactiveStudentCount, $nextAccountNumber, $generatedAccounts); ?>
    </div>

    <section class="section-card mb-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0">Student Account Register</h5>
        <span class="text-secondary small">Active students can log in with the generated student ID and PIN.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Student ID</th>
              <th>Class</th>
              <th>PIN</th>
              <th>Status</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($studentAccounts === []): ?>
              <tr>
                <td colspan="7" class="text-center text-secondary py-4">No student IDs have been created yet.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($studentAccounts as $studentAccount): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars((string) $studentAccount['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php if (!empty($studentAccount['deactivation_reason'])): ?>
                    <div class="small text-secondary"><?php echo htmlspecialchars((string) $studentAccount['deactivation_reason'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                </td>
                <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $studentAccount['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $studentAccount['class_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $studentAccount['pin_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php if ((int) $studentAccount['is_active'] === 1): ?>
                    <span class="badge text-bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Deactivated</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars((string) $studentAccount['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php if ((int) $studentAccount['is_active'] === 1): ?>
                    <form method="post" class="d-flex flex-column gap-2">
                      <input type="hidden" name="action" value="deactivate_student" />
                      <input type="hidden" name="student_id" value="<?php echo (int) $studentAccount['id']; ?>" />
                      <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for leaving" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate ID</button>
                    </form>
                  <?php else: ?>
                    <span class="small text-secondary">Removed from active login access</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="section-card">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0">Student ID Activity Log</h5>
        <span class="text-secondary small">The database keeps a record of created and deactivated student IDs.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Time</th>
              <th>Action</th>
              <th>Student</th>
              <th>Student ID</th>
              <th>Class</th>
              <th>PIN</th>
              <th>Batch</th>
              <th>Performed By</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($studentActivity === []): ?>
              <tr>
                <td colspan="8" class="text-center text-secondary py-4">No activity has been recorded yet.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($studentActivity as $activityItem): ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $activityItem['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <span class="badge <?php echo $activityItem['action_type'] === 'generated' ? 'text-bg-primary' : 'text-bg-warning'; ?>">
                    <?php echo htmlspecialchars(ucfirst((string) $activityItem['action_type']), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                  <?php if (!empty($activityItem['reason_text'])): ?>
                    <div class="small text-secondary mt-1"><?php echo htmlspecialchars((string) $activityItem['reason_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars((string) $activityItem['student_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $activityItem['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>-</td>
                <td><?php echo htmlspecialchars((string) ($activityItem['pin_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($activityItem['batch_reference'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $activityItem['performed_by'], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="../assets/js/app.js"></script>
</body>
</html>