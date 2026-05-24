<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$adminUserId = (int) ($_SESSION['user']['id'] ?? 0);
$alertType = null;
$alertMessage = null;

$flash = $_SESSION['staff_management_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    unset($_SESSION['staff_management_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_staff_account') {
            $staffAccount = createStaffAccount(
                $pdo,
                (string) ($_POST['staff_name'] ?? ''),
                $adminName,
                (string) ($_POST['pin_code'] ?? '')
            );

            $_SESSION['staff_management_flash'] = [
                'type' => 'success',
                'message' => 'Staff account ' . (string) $staffAccount['account_number'] . ' was created. PIN: ' . (string) $staffAccount['pin_code'] . '.',
            ];

            header('Location: staff-management.php?staff_id=' . (int) $staffAccount['id']);
            exit;
        }

        if ($action === 'save_staff_allocations') {
            $staffUserId = (int) ($_POST['staff_user_id'] ?? 0);
            $savedCount = saveStaffTeachingAllocations(
                $pdo,
                $staffUserId,
                (array) ($_POST['allocations'] ?? []),
                $adminName,
                $adminUserId > 0 ? $adminUserId : null
            );
            $staffAccount = getStaffAccountById($pdo, $staffUserId);

            $_SESSION['staff_management_flash'] = [
                'type' => 'success',
                'message' => ($staffAccount['full_name'] ?? 'Staff member') . ' now has ' . $savedCount . ' saved class-subject assignment(s).',
            ];

            header('Location: staff-management.php?staff_id=' . $staffUserId);
            exit;
        }

        throw new RuntimeException('Unsupported staff management action.');
    } catch (Throwable $throwable) {
        $alertType = 'danger';
        $alertMessage = $throwable->getMessage();
    }
}

$staffAccounts = getStaffAccounts($pdo);
$classLists = getClassLists($pdo);
$subjects = getSubjects($pdo);
$selectedStaffId = (int) ($_REQUEST['staff_id'] ?? ($staffAccounts[0]['id'] ?? 0));
$selectedStaff = $selectedStaffId > 0 ? getStaffAccountById($pdo, $selectedStaffId) : null;
$selectedAllocations = $selectedStaff !== null ? getStaffTeachingAllocations($pdo, $selectedStaffId) : [];
$allocationLookup = [];
foreach ($selectedAllocations as $allocation) {
    $allocationLookup[(int) $allocation['class_list_id']][(int) $allocation['subject_id']] = true;
}

$activeStaffCount = count(array_filter($staffAccounts, static fn (array $staff): bool => (int) $staff['is_active'] === 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Staff Accounts</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260425-navfix" />
</head>
<body>
  <?php renderPortalNavigation('admin', 'staff-management', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Staff Accounts and Access</div>
      <h1 class="h3 mb-2">Create staff logins and decide exactly which classes and subjects each teacher can update.</h1>
      <p class="subtle-copy mb-0">These allocations control the staff marks page, the staff assessment-sheet view, and the data students eventually see in their assessment and report views.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="metrics-grid mb-4">
      <div class="metric-card"><div class="metric-label">Active staff accounts</div><div class="metric-value"><?php echo $activeStaffCount; ?></div><p class="metric-meta">Teachers with active login access.</p></div>
      <div class="metric-card"><div class="metric-label">Class lists available</div><div class="metric-value"><?php echo count($classLists); ?></div><p class="metric-meta">Assignments are tied to these roster-backed classes.</p></div>
      <div class="metric-card"><div class="metric-label">Active subjects</div><div class="metric-value"><?php echo count($subjects); ?></div><p class="metric-meta">Each staff assignment is saved as a class-subject pair.</p></div>
    </section>

    <section class="content-grid-two mb-4">
      <div class="data-panel">
        <div class="section-heading"><h5>Create Staff Account</h5><span class="section-note">The generated account number and PIN can be handed directly to the teacher.</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="create_staff_account" />
          <div>
            <label class="form-label" for="staffName">Staff Name</label>
            <input id="staffName" type="text" name="staff_name" class="form-control" placeholder="e.g. Maxwell Kato" />
          </div>
          <div>
            <label class="form-label" for="pinCode">PIN (optional)</label>
            <input id="pinCode" type="text" name="pin_code" class="form-control" placeholder="Leave blank to auto-generate" />
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Create Staff Login</button>
          </div>
        </form>
      </div>

      <div class="data-card">
        <div class="section-heading"><h5>Select Staff Member</h5><span class="section-note">Load one teacher at a time for assignment editing.</span></div>
        <form method="get" class="toolbar-form">
          <div>
            <label class="form-label" for="staffId">Staff Account</label>
            <select id="staffId" name="staff_id" class="form-select">
              <?php foreach ($staffAccounts as $staffAccount): ?>
                <option value="<?php echo (int) $staffAccount['id']; ?>" <?php echo (int) $staffAccount['id'] === $selectedStaffId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $staffAccount['account_number'] . ' - ' . (string) $staffAccount['full_name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Load Staff</button>
          </div>
        </form>

        <?php if ($selectedStaff !== null): ?>
          <div class="feed-list mt-3">
            <div class="feed-item"><div class="feed-title">Login</div><div class="feed-meta"><?php echo htmlspecialchars((string) $selectedStaff['account_number'], ENT_QUOTES, 'UTF-8'); ?> / PIN <?php echo htmlspecialchars((string) $selectedStaff['pin_code'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div class="feed-item"><div class="feed-title">Stored assignments</div><div class="feed-meta"><?php echo count($selectedAllocations); ?> class-subject pair(s) currently allocated.</div></div>
          </div>
        <?php else: ?>
          <div class="empty-state mt-3">Create or select a staff account to begin allocating classes and subjects.</div>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($selectedStaff !== null): ?>
      <section class="table-card mb-4">
        <div class="table-card-header section-heading">
          <h5>Teaching Allocation Matrix</h5>
          <span class="section-note">Tick the subjects this staff member is allowed to update for each class list.</span>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="save_staff_allocations" />
          <input type="hidden" name="staff_user_id" value="<?php echo (int) $selectedStaff['id']; ?>" />
          <div class="table-responsive">
            <table class="table soft-table align-middle mb-0">
              <thead>
                <tr>
                  <th>Class List</th>
                  <?php foreach ($subjects as $subject): ?>
                    <th><?php echo htmlspecialchars((string) $subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($classLists === [] || $subjects === []): ?>
                  <tr><td colspan="<?php echo count($subjects) + 1; ?>" class="empty-state">Create class lists and subjects before assigning staff access.</td></tr>
                <?php endif; ?>
                <?php foreach ($classLists as $classList): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="feed-meta"><?php echo (int) $classList['active_student_count']; ?> active student(s)</div>
                    </td>
                    <?php foreach ($subjects as $subject): ?>
                      <?php $isChecked = isset($allocationLookup[(int) $classList['id']][(int) $subject['id']]); ?>
                      <td class="text-center">
                        <input type="checkbox" name="allocations[<?php echo (int) $classList['id']; ?>][]" value="<?php echo (int) $subject['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?> />
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="p-3 border-top d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary">Save Teaching Allocations</button>
            <span class="section-note">Unticked cells remove access for that class-subject combination.</span>
          </div>
        </form>
      </section>

      <section class="content-grid-two mb-4">
        <div class="feed-card">
          <div class="section-heading"><h5>Current Assignment List</h5><span class="section-note">Saved in the database</span></div>
          <div class="feed-list">
            <?php if ($selectedAllocations === []): ?>
              <div class="empty-state">This staff account has not been assigned any class-subject pair yet.</div>
            <?php endif; ?>
            <?php foreach ($selectedAllocations as $allocation): ?>
              <div class="feed-item">
                <div class="feed-title"><?php echo htmlspecialchars((string) $allocation['class_list_display_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="feed-meta"><?php echo htmlspecialchars((string) $allocation['subject_name'] . ' (' . (string) $allocation['subject_code'] . ')', ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="feed-card">
          <div class="section-heading"><h5>Why This Matters</h5></div>
          <div class="feed-list">
            <div class="feed-item"><div class="feed-title">Marks entry</div><div class="feed-meta">Teachers only see the class-subject combinations they were assigned here.</div></div>
            <div class="feed-item"><div class="feed-title">Assessment visibility</div><div class="feed-meta">Staff can still review the assigned class assessment sheet, while students see the class sheet for their own class.</div></div>
            <div class="feed-item"><div class="feed-title">Report pipeline</div><div class="feed-meta">The same stored marks continue to drive report readiness and student report publication.</div></div>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="table-card">
      <div class="table-card-header section-heading">
        <h5>Staff Account Register</h5>
        <span class="section-note">Only real staff accounts stored in the database are shown.</span>
      </div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Staff Member</th>
              <th>Staff ID</th>
              <th>PIN</th>
              <th>Assigned Classes</th>
              <th>Assigned Subjects</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($staffAccounts === []): ?>
              <tr><td colspan="6" class="empty-state">No staff accounts have been created yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($staffAccounts as $staffAccount): ?>
              <tr>
                <td><a class="fw-semibold text-primary text-decoration-none" href="?staff_id=<?php echo (int) $staffAccount['id']; ?>"><?php echo htmlspecialchars((string) $staffAccount['full_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars((string) $staffAccount['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $staffAccount['pin_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $staffAccount['assigned_class_count']; ?></td>
                <td><?php echo (int) $staffAccount['assigned_subject_count']; ?></td>
                <td>
                  <span class="status-pill <?php echo (int) $staffAccount['is_active'] === 1 ? 'status-pill-ready' : 'status-pill-pending'; ?>">
                    <?php echo (int) $staffAccount['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                  </span>
                </td>
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