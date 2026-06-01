<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$staffName = (string) ($_SESSION['user']['full_name'] ?? 'Staff Member');
$alertType = null;
$alertMessage = null;
$pendingStudentIdentifiers = array_fill(0, 12, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = trim((string) ($_POST['action'] ?? ''));

  if ($action !== '') {
    try {
      if ($action === 'create_class_list') {
            $classList = createClassList(
                $pdo,
                (string) ($_POST['class_name'] ?? ''),
                (string) ($_POST['class_stream'] ?? ''),
                $staffName
            );

            $_SESSION['staff_class_list_flash'] = [
                'type' => 'success',
                'message' => 'Class list created successfully.',
            ];
            header('Location: class-lists.php?class_list_id=' . (int) $classList['id']);
            exit;
          }

          if ($action === 'assign_students') {
            $classListId = (int) ($_POST['class_list_id'] ?? 0);
            $rawStudentIdentifiers = $_POST['student_identifiers'] ?? [];
            $studentIdentifiers = is_array($rawStudentIdentifiers)
              ? array_map(static fn ($value): string => (string) $value, $rawStudentIdentifiers)
              : (preg_split('/\R/', (string) $rawStudentIdentifiers) ?: []);
            $assignmentResult = assignStudentsToClassList($pdo, $classListId, $studentIdentifiers, $staffName);
            $assignedCount = (int) ($assignmentResult['assigned_count'] ?? 0);
            $pendingStudentIdentifiers = (array) ($assignmentResult['unresolved_identifiers'] ?? []);

            if ($assignedCount > 0 && $pendingStudentIdentifiers === []) {
              $_SESSION['staff_class_list_flash'] = [
                'type' => 'success',
                'message' => $assignedCount . ' student(s) added or synchronized into the selected class list.',
              ];
              header('Location: class-lists.php?class_list_id=' . $classListId);
              exit;
            }

            if ($assignedCount > 0) {
              $alertType = 'warning';
              $alertMessage = $assignedCount . ' student(s) were added. Review the rows that still need correction.';
            } else {
              $alertType = 'danger';
              $messages = (array) ($assignmentResult['messages'] ?? []);
              $alertMessage = $messages !== []
                ? implode(' ', $messages)
                : 'No student rows could be assigned to this class list.';
            }
          }

          if ($action === 'remove_students') {
            $classListId = (int) ($_POST['class_list_id'] ?? 0);
            $removedCount = removeStudentsFromClassList($pdo, $classListId, (array) ($_POST['student_ids'] ?? []), $staffName);

            $_SESSION['staff_class_list_flash'] = [
              'type' => 'warning',
              'message' => $removedCount . ' student(s) removed from the selected class list.',
            ];
            header('Location: class-lists.php?class_list_id=' . $classListId);
            exit;
          }

          if ($action === 'promote_class_list') {
            $sourceClassListId = (int) ($_POST['source_class_list_id'] ?? 0);
            $targetClassList = createClassList(
              $pdo,
              (string) ($_POST['target_class_name'] ?? ''),
              (string) ($_POST['target_class_stream'] ?? ''),
              $staffName
            );
            $transferredCount = promoteClassList($pdo, $sourceClassListId, (int) $targetClassList['id'], $staffName);

            $_SESSION['staff_class_list_flash'] = [
              'type' => 'success',
              'message' => $transferredCount . ' student(s) transferred into ' . (string) $targetClassList['display_name'] . '.',
            ];
            header('Location: class-lists.php?class_list_id=' . (int) $targetClassList['id']);
            exit;
          }

          throw new RuntimeException('Unsupported class list action.');
        } catch (Throwable $throwable) {
          $alertType = 'danger';
          $alertMessage = $throwable->getMessage();
        }
    }
}

$flash = $_SESSION['staff_class_list_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    unset($_SESSION['staff_class_list_flash']);
}

$classLists = getClassLists($pdo, true);
$activeClassLists = array_values(array_filter($classLists, static fn (array $classList): bool => (int) $classList['is_active'] === 1));
$selectedClassListId = (int) ($_REQUEST['class_list_id'] ?? ($activeClassLists[0]['id'] ?? ($classLists[0]['id'] ?? 0)));
$selectedClassList = $selectedClassListId > 0 ? getClassListById($pdo, $selectedClassListId) : null;
$selectedRoster = $selectedClassList ? getClassListRoster($pdo, $selectedClassListId) : [];
$promotionClassName = $selectedClassList ? suggestPromotedClassName((string) $selectedClassList['class_name']) : '';
$promotionStream = $selectedClassList ? (string) $selectedClassList['class_stream'] : '';
$pendingStudentIdentifiers = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $pendingStudentIdentifiers), static fn (string $value): bool => $value !== ''));
$pendingStudentIdentifiers = array_pad($pendingStudentIdentifiers, max(12, count($pendingStudentIdentifiers) + 4), '');

function renderClassListWorkspace(
    ?array $selectedClassList,
    array $selectedRoster,
    array $pendingStudentIdentifiers,
    string $promotionClassName,
    string $promotionStream
): void {
    if ($selectedClassList === null) {
        ?>
        <section class="section-card mb-4">
          <div class="section-kicker">Roster Workspace</div>
          <h5 class="mb-2">Select a class list to open its roster workspace</h5>
          <p class="mb-0 text-secondary">Once you choose a class list, the builder, active roster, and transfer tools will appear here without leaving the page.</p>
        </section>
        <?php
        return;
    }

    $displayName = (string) ($selectedClassList['display_name'] ?? 'Selected Class List');
    $className = trim((string) ($selectedClassList['class_name'] ?? ''));
    $classStream = trim((string) ($selectedClassList['class_stream'] ?? ''));
    $streamLabel = $classStream !== '' ? 'Stream ' . $classStream : 'No stream';
    $workspaceTitle = $className !== ''
        ? $className . ($classStream !== '' ? ' | ' . $streamLabel : ' | No stream')
        : $displayName;
    ?>
    <section class="section-card mb-4 roster-workspace-shell">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <div class="section-kicker">Opened Roster</div>
          <h5 class="mb-2"><?php echo htmlspecialchars($workspaceTitle, ENT_QUOTES, 'UTF-8'); ?> Roster Workspace</h5>
          <p class="mb-0 text-secondary">You are working inside class <?php echo htmlspecialchars($className !== '' ? $className : $displayName, ENT_QUOTES, 'UTF-8'); ?><?php echo $classStream !== '' ? ' in ' . htmlspecialchars($streamLabel, ENT_QUOTES, 'UTF-8') : ''; ?>. Add students, review the live roster, or transfer this class cleanly from this workspace.</p>
        </div>
        <div class="text-end">
          <div class="summary-value"><?php echo count($selectedRoster); ?></div>
          <div class="summary-label">Active student(s)</div>
        </div>
      </div>
    </section>

    <section class="content-grid-two mb-4">
      <div class="data-panel">
        <div class="section-heading"><h5><?php echo htmlspecialchars($workspaceTitle, ENT_QUOTES, 'UTF-8'); ?> Roster Builder</h5><span class="section-note">Type one student ID or exact student name per row for this class and stream.</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="assign_students" />
          <input type="hidden" name="class_list_id" value="<?php echo (int) $selectedClassList['id']; ?>" />
          <div class="table-responsive">
            <table class="table soft-table spreadsheet-table align-middle mb-0">
              <thead>
                <tr>
                  <th style="width: 72px;">Row</th>
                  <th>Student ID or Exact Name</th>
                </tr>
              </thead>
              <tbody id="rosterBuilderBody">
                <?php foreach ($pendingStudentIdentifiers as $index => $studentIdentifier): ?>
                  <tr>
                    <td class="text-secondary fw-semibold"><?php echo $index + 1; ?></td>
                    <td>
                      <input
                        type="text"
                        name="student_identifiers[]"
                        class="form-control spreadsheet-input"
                        value="<?php echo htmlspecialchars($studentIdentifier, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="e.g. STU-1001 or Amina Yusuf"
                      />
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary" data-add-roster-rows="5" data-target-body="rosterBuilderBody">Add 5 More Rows</button>
            <button type="submit" class="btn btn-primary">Save Roster Rows</button>
          </div>
        </form>
      </div>

      <div class="data-panel">
        <div class="section-heading"><h5>Promote / Transfer Roster</h5><span class="section-note">Move the active roster into the next class list.</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="promote_class_list" />
          <input type="hidden" name="source_class_list_id" value="<?php echo (int) $selectedClassList['id']; ?>" />
          <div>
            <label class="form-label" for="targetClassName">Next Class Name</label>
            <input id="targetClassName" type="text" name="target_class_name" class="form-control" value="<?php echo htmlspecialchars($promotionClassName, ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
          <div>
            <label class="form-label" for="targetClassStream">Next Class Stream</label>
            <input id="targetClassStream" type="text" name="target_class_stream" class="form-control" value="<?php echo htmlspecialchars($promotionStream, ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
          <div>
            <button type="submit" class="btn btn-primary" <?php echo $selectedRoster === [] ? 'disabled' : ''; ?>>Transfer Active Roster</button>
          </div>
        </form>
      </div>
    </section>

    <section class="table-card mb-4">
      <div class="table-card-header section-heading">
        <h5><?php echo htmlspecialchars($workspaceTitle, ENT_QUOTES, 'UTF-8'); ?> Roster</h5>
        <span class="section-note">Removing a student clears the live class-list assignment until they are placed elsewhere.</span>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="remove_students" />
        <input type="hidden" name="class_list_id" value="<?php echo (int) $selectedClassList['id']; ?>" />
        <div class="table-responsive">
          <table class="table soft-table align-middle mb-0">
            <thead>
              <tr>
                <th style="width: 60px;">Remove</th>
                <th>Student</th>
                <th>Student ID</th>
                <th>Assigned At</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($selectedRoster === []): ?>
                <tr><td colspan="4" class="empty-state">No active students are assigned to this class list yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($selectedRoster as $student): ?>
                <tr>
                  <td><input type="checkbox" name="student_ids[]" value="<?php echo (int) $student['id']; ?>" /></td>
                  <td><?php echo htmlspecialchars((string) $student['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="fw-semibold text-primary"><?php echo htmlspecialchars((string) $student['account_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo formatPortalDateTime((string) $student['assigned_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="p-3 border-top">
          <button type="submit" class="btn btn-outline-danger" <?php echo $selectedRoster === [] ? 'disabled' : ''; ?>>Remove Selected Students</button>
        </div>
      </form>
    </section>
    <?php
}

if (portalIsFragmentRequest('roster-workspace')) {
    portalRenderFragment(static function () use ($selectedClassList, $selectedRoster, $pendingStudentIdentifiers, $promotionClassName, $promotionStream): void {
        renderClassListWorkspace($selectedClassList, $selectedRoster, $pendingStudentIdentifiers, $promotionClassName, $promotionStream);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Class Lists</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
</head>
<body>
  <?php renderPortalNavigation('staff', 'class-lists', $staffName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Class List Management</div>
      <h1 class="h3 mb-2">Create a class list, open its roster sheet, and keep every student tied to the correct class.</h1>
      <p class="subtle-copy mb-0">The roster sheet below behaves like a simple spreadsheet so teachers can build a class cleanly and keep marks restricted to the right students.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="content-grid-two mb-4">
      <div class="data-panel">
        <div class="section-heading"><h5>Create Class List</h5><span class="section-note">Class name and stream are stored separately.</span></div>
        <form method="post" class="dashboard-grid">
          <input type="hidden" name="action" value="create_class_list" />
          <div>
            <label class="form-label" for="className">Class Name</label>
            <input id="className" type="text" name="class_name" class="form-control" placeholder="e.g. Senior 1" />
          </div>
          <div>
            <label class="form-label" for="classStream">Class Stream</label>
            <input id="classStream" type="text" name="class_stream" class="form-control" placeholder="e.g. A" />
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Create Class List</button>
          </div>
        </form>
      </div>

      <div class="data-card">
        <div class="section-heading"><h5>Select Class List</h5><span class="section-note">Use one roster at a time.</span></div>
        <form method="get" class="toolbar-form" data-async-form data-async-target="rosterWorkspaceContainer" data-async-fragment="roster-workspace" data-async-push-url="true" data-async-scroll="true">
          <div>
            <label class="form-label" for="selectedClassList">Class List</label>
            <select id="selectedClassList" name="class_list_id" class="form-select">
              <?php foreach ($classLists as $classList): ?>
                <option value="<?php echo (int) $classList['id']; ?>" <?php echo (int) $classList['id'] === $selectedClassListId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button type="submit" class="btn btn-primary">Open Roster</button>
          </div>
        </form>
        <div class="feed-list mt-3">
          <div class="feed-item"><div class="feed-title">Managed class lists</div><div class="feed-meta"><?php echo count($activeClassLists); ?> active list(s) in the database.</div></div>
          <?php if ($selectedClassList): ?>
            <div class="feed-item"><div class="feed-title">Current roster</div><div class="feed-meta"><?php echo count($selectedRoster); ?> active student(s) in <?php echo htmlspecialchars((string) $selectedClassList['display_name'], ENT_QUOTES, 'UTF-8'); ?>.</div></div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div id="rosterWorkspaceContainer" data-async-region="roster-workspace">
      <?php renderClassListWorkspace($selectedClassList, $selectedRoster, $pendingStudentIdentifiers, $promotionClassName, $promotionStream); ?>
    </div>

    <section class="table-card">
      <div class="table-card-header section-heading">
        <h5>All Class Lists</h5>
        <span class="section-note">Only stored class lists appear here.</span>
      </div>
      <div class="table-responsive">
        <table class="table soft-table align-middle mb-0">
          <thead>
            <tr>
              <th>Class List</th>
              <th>Stream</th>
              <th>Active Students</th>
              <th>Status</th>
              <th>Promoted To</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($classLists === []): ?>
              <tr><td colspan="5" class="empty-state">No class lists have been created yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($classLists as $classList): ?>
              <?php $promotedTarget = !empty($classList['promoted_to_class_list_id']) ? getClassListById($pdo, (int) $classList['promoted_to_class_list_id']) : null; ?>
              <tr>
                <td><a class="fw-semibold text-primary text-decoration-none" href="?class_list_id=<?php echo (int) $classList['id']; ?>" data-async-link data-async-target="rosterWorkspaceContainer" data-async-fragment="roster-workspace" data-async-push-url="true" data-async-scroll="true"><?php echo htmlspecialchars((string) $classList['display_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars((string) ($classList['class_stream'] !== '' ? $classList['class_stream'] : 'Legacy / none'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $classList['active_student_count']; ?></td>
                <td>
                  <span class="status-pill <?php echo (int) $classList['is_active'] === 1 ? 'status-pill-ready' : 'status-pill-pending'; ?>">
                    <?php echo (int) $classList['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars((string) ($promotedTarget['display_name'] ?? 'Not linked'), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
