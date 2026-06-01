<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$pdo = getDatabaseConnection();
$adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrator');
$alertType = null;
$alertMessage = null;

$flash = $_SESSION['grading_management_flash'] ?? null;
if (is_array($flash)) {
    $alertType = (string) ($flash['type'] ?? 'info');
    $alertMessage = (string) ($flash['message'] ?? '');
    unset($_SESSION['grading_management_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_grading_system') {
            $systemName = (string) ($_POST['system_name'] ?? '');
            $description = (string) ($_POST['description'] ?? '');
            
            $system = createGradingSystem($pdo, $systemName, $description, $adminName);
            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Grading system created successfully.',
            ];
            header('Location: grading-management.php');
            exit;
        }

        if ($action === 'save_grading_scale') {
            $systemId = (int) ($_POST['system_id'] ?? 0);
            $gradeLabel = (string) ($_POST['grade_label'] ?? '');
            $gradeName = (string) ($_POST['grade_name'] ?? '');
            $markFrom = (float) ($_POST['mark_from'] ?? 0);
            $markTo = (float) ($_POST['mark_to'] ?? 0);
            $description = (string) ($_POST['grade_description'] ?? '');

            if ($systemId <= 0 || $gradeLabel === '' || $gradeName === '') {
                throw new RuntimeException('Please fill in all required fields.');
            }
            if ($markFrom > $markTo) {
                throw new RuntimeException('Mark "from" cannot be greater than "to".');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
                 VALUES (:system_id, :grade_label, :grade_name, :mark_from, :mark_to, :description, 
                         (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM grading_scales WHERE grading_system_id = :sys_id))
                 ON DUPLICATE KEY UPDATE
                 grade_name = VALUES(grade_name),
                 mark_from = VALUES(mark_from),
                 mark_to = VALUES(mark_to),
                 description = VALUES(description),
                 updated_at = NOW()'
            );
            $stmt->execute([
                'system_id' => $systemId,
                'sys_id' => $systemId,
                'grade_label' => trim($gradeLabel),
                'grade_name' => trim($gradeName),
                'mark_from' => $markFrom,
                'mark_to' => $markTo,
                'description' => $description,
            ]);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Grade scale saved successfully.',
            ];
            header('Location: grading-management.php?system_id=' . $systemId . '#grade-scales');
            exit;
        }

        if ($action === 'update_grading_scale') {
            $scaleId = (int) ($_POST['scale_id'] ?? 0);
            $systemId = (int) ($_POST['system_id'] ?? 0);
            $gradeName = (string) ($_POST['grade_name'] ?? '');
            $markFrom = (float) ($_POST['mark_from'] ?? 0);
            $markTo = (float) ($_POST['mark_to'] ?? 0);
            $description = (string) ($_POST['grade_description'] ?? '');

            if ($scaleId <= 0 || $systemId <= 0) {
                throw new RuntimeException('Invalid grade scale or system.');
            }

            $stmt = $pdo->prepare(
                'UPDATE grading_scales
                 SET grade_name = :grade_name, mark_from = :mark_from, mark_to = :mark_to, description = :description, updated_at = NOW()
                 WHERE id = :id AND grading_system_id = :system_id'
            );
            $stmt->execute([
                'id' => $scaleId,
                'system_id' => $systemId,
                'grade_name' => trim($gradeName),
                'mark_from' => $markFrom,
                'mark_to' => $markTo,
                'description' => $description,
            ]);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Grade scale updated successfully.',
            ];
            header('Location: grading-management.php?system_id=' . $systemId . '#grade-scales');
            exit;
        }

        if ($action === 'delete_grading_scale') {
            $scaleId = (int) ($_POST['scale_id'] ?? 0);
            $systemId = (int) ($_POST['system_id'] ?? 0);

            if ($scaleId <= 0) {
                throw new RuntimeException('Invalid grade scale.');
            }

            $pdo->prepare('DELETE FROM grading_scales WHERE id = :id AND grading_system_id = :system_id')
                ->execute(['id' => $scaleId, 'system_id' => $systemId]);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Grade scale deleted successfully.',
            ];
            header('Location: grading-management.php?system_id=' . $systemId . '#grade-scales');
            exit;
        }

        if ($action === 'save_remark_template') {
            $systemId = (int) ($_POST['system_id'] ?? 0);
            $gradeLabel = (string) ($_POST['grade_label'] ?? '');
            $remarkTemplate = (string) ($_POST['remark_template'] ?? '');

            if ($systemId <= 0 || $gradeLabel === '' || $remarkTemplate === '') {
                throw new RuntimeException('Please fill in all required fields.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
                 VALUES (:system_id, :grade_label, :remark_template, 
                         (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM teacher_remark_templates WHERE grading_system_id = :sys_id),
                         1, :created_by)
                 ON DUPLICATE KEY UPDATE
                 remark_template = VALUES(remark_template),
                 is_active = 1,
                 updated_at = NOW()'
            );
            $stmt->execute([
                'system_id' => $systemId,
                'sys_id' => $systemId,
                'grade_label' => trim($gradeLabel),
                'remark_template' => trim($remarkTemplate),
                'created_by' => $adminName,
            ]);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Remark template saved successfully.',
            ];
            header('Location: grading-management.php?system_id=' . $systemId . '#remark-templates');
            exit;
        }

        if ($action === 'update_remark_template') {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $systemId = (int) ($_POST['system_id'] ?? 0);
            $remarkTemplate = (string) ($_POST['remark_template'] ?? '');

            if ($templateId <= 0 || $systemId <= 0) {
                throw new RuntimeException('Invalid remark template or system.');
            }

            $stmt = $pdo->prepare(
                'UPDATE teacher_remark_templates
                 SET remark_template = :remark_template, updated_at = NOW()
                 WHERE id = :id AND grading_system_id = :system_id'
            );
            $stmt->execute([
                'id' => $templateId,
                'system_id' => $systemId,
                'remark_template' => trim($remarkTemplate),
            ]);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Remark template updated successfully.',
            ];
            header('Location: grading-management.php?system_id=' . $systemId . '#remark-templates');
            exit;
        }

        if ($action === 'delete_remark_template') {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $systemId = (int) ($_POST['system_id'] ?? 0);

            if ($templateId <= 0) {
                throw new RuntimeException('Invalid remark template.');
            }

            $pdo->prepare('DELETE FROM teacher_remark_templates WHERE id = :id AND grading_system_id = :system_id')
                ->execute(['id' => $templateId, 'system_id' => $systemId]);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Remark template deleted successfully.',
            ];
            header('Location: grading-management.php?system_id=' . $systemId . '#remark-templates');
            exit;
        }

        if ($action === 'create_promotion_remark') {
            $label = (string) ($_POST['remark_label'] ?? '');
            $description = (string) ($_POST['remark_description'] ?? '');
            $category = (string) ($_POST['remark_category'] ?? 'promotion');

            createPromotionStatusRemark($pdo, $label, $description, $category, $adminName);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Promotion status remark created successfully.',
            ];
            header('Location: grading-management.php#promotion-remarks');
            exit;
        }

        if ($action === 'update_promotion_remark') {
            $remarkId = (int) ($_POST['remark_id'] ?? 0);
            $description = (string) ($_POST['remark_description'] ?? '');

            if ($remarkId <= 0) {
                throw new RuntimeException('Invalid promotion remark.');
            }

            updatePromotionStatusRemark($pdo, $remarkId, $description);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Promotion status remark updated successfully.',
            ];
            header('Location: grading-management.php#promotion-remarks');
            exit;
        }

        if ($action === 'delete_promotion_remark') {
            $remarkId = (int) ($_POST['remark_id'] ?? 0);

            if ($remarkId <= 0) {
                throw new RuntimeException('Invalid promotion remark.');
            }

            deletePromotionStatusRemark($pdo, $remarkId);

            $_SESSION['grading_management_flash'] = [
                'type' => 'success',
                'message' => 'Promotion status remark deleted successfully.',
            ];
            header('Location: grading-management.php#promotion-remarks');
            exit;
        }

        throw new RuntimeException('Unsupported action.');
    } catch (Throwable $e) {
        $alertType = 'danger';
        $alertMessage = $e->getMessage();
    }
}

$gradingSystems = getGradingSystems($pdo, true);
$selectedSystemId = (int) ($_REQUEST['system_id'] ?? ($gradingSystems[0]['id'] ?? 0));
$selectedSystem = $selectedSystemId > 0 ? getGradingSystemById($pdo, $selectedSystemId) : null;
$gradingScales = $selectedSystemId > 0 ? getGradingScalesBySystem($pdo, $selectedSystemId) : [];
$remarkTemplates = $selectedSystemId > 0 ? getTeacherRemarkTemplates($pdo, $selectedSystemId) : [];
$promotionRemarks = getPromotionStatusRemarks($pdo, null, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Grading System Management</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260601" />
  <style>
    .edit-row { display: none; }
    .edit-row.show { display: table-row; }
    .view-row.editing { display: none; }
  </style>
</head>
<body>
  <?php renderPortalNavigation('admin', 'grading-management', $adminName); ?>

  <main class="page-shell">
    <section class="hero-card hero-card-rich mb-4">
      <div class="section-kicker">Grading Configuration</div>
      <h1 class="h3 mb-2">Create and customize grading systems for your school.</h1>
      <p class="subtle-copy mb-0">Define grades, mark ranges, teacher remarks, and promotion status for your institution. All settings are fully editable.</p>
    </section>

    <?php if ($alertMessage): ?>
      <div class="alert alert-<?php echo htmlspecialchars($alertType ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-4"><?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header bg-light border-0">
            <h5 class="card-title mb-0">Create New Grading System</h5>
          </div>
          <div class="card-body">
            <form method="POST" action="grading-management.php">
              <input type="hidden" name="action" value="create_grading_system" />
              <div class="mb-3">
                <label for="system_name" class="form-label">System Name</label>
                <input type="text" class="form-control" id="system_name" name="system_name" placeholder="e.g., Main Grading System" required />
              </div>
              <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe this grading system..."></textarea>
              </div>
              <button type="submit" class="btn btn-primary w-100">Create System</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <?php if ($gradingSystems): ?>
          <div class="card mb-4">
            <div class="card-header bg-light border-0">
              <h5 class="card-title mb-0">Available Grading Systems</h5>
            </div>
            <div class="card-body">
              <div class="list-group">
                <?php foreach ($gradingSystems as $system): ?>
                  <a href="grading-management.php?system_id=<?php echo (int) $system['id']; ?>" 
                     class="list-group-item list-group-item-action <?php echo (int) $system['id'] === $selectedSystemId ? 'active' : ''; ?>">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                      <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars((string) $system['system_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                        <?php if ($system['description']): ?>
                          <p class="mb-0 small text-muted"><?php echo htmlspecialchars((string) $system['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                      </div>
                      <span class="badge <?php echo $system['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $system['is_active'] ? 'Active' : 'Inactive'; ?>
                      </span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <?php if ($selectedSystem): ?>
            <!-- Grade Scales Section -->
            <div class="card mb-4" id="grade-scales">
              <div class="card-header bg-light border-0">
                <h5 class="card-title mb-0">Grade Scales - <?php echo htmlspecialchars((string) $selectedSystem['system_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
              </div>
              <div class="card-body">
                <form method="POST" action="grading-management.php" class="mb-4">
                  <input type="hidden" name="action" value="save_grading_scale" />
                  <input type="hidden" name="system_id" value="<?php echo (int) $selectedSystemId; ?>" />
                  <h6>Add New Grade</h6>
                  <div class="row g-3">
                    <div class="col-md-2">
                      <label for="grade_label" class="form-label">Label</label>
                      <input type="text" class="form-control" id="grade_label" name="grade_label" placeholder="A" maxlength="10" required />
                    </div>
                    <div class="col-md-2">
                      <label for="grade_name" class="form-label">Name</label>
                      <input type="text" class="form-control" id="grade_name" name="grade_name" placeholder="Excellent" required />
                    </div>
                    <div class="col-md-2">
                      <label for="mark_from" class="form-label">From</label>
                      <input type="number" class="form-control" id="mark_from" name="mark_from" placeholder="80" step="0.01" min="0" max="100" required />
                    </div>
                    <div class="col-md-2">
                      <label for="mark_to" class="form-label">To</label>
                      <input type="number" class="form-control" id="mark_to" name="mark_to" placeholder="100" step="0.01" min="0" max="100" required />
                    </div>
                    <div class="col-md-4">
                      <label for="grade_description" class="form-label">Description</label>
                      <input type="text" class="form-control" id="grade_description" name="grade_description" placeholder="Grade description..." />
                    </div>
                  </div>
                  <div class="mt-3">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Add Grade</button>
                  </div>
                </form>

                <?php if ($gradingScales): ?>
                  <h6>Existing Grades</h6>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Label</th>
                          <th>Name</th>
                          <th>Mark Range</th>
                          <th>Description</th>
                          <th class="text-center">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($gradingScales as $scale): ?>
                          <tr class="view-row" data-scale-id="<?php echo (int) $scale['id']; ?>">
                            <td><strong><?php echo htmlspecialchars((string) $scale['grade_label'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $scale['grade_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (float) $scale['mark_from']; ?> - <?php echo (float) $scale['mark_to']; ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars((string) ($scale['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center">
                              <button type="button" class="btn btn-sm btn-outline-secondary edit-btn" data-scale-id="<?php echo (int) $scale['id']; ?>">Edit</button>
                              <form method="POST" action="grading-management.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete_grading_scale" />
                                <input type="hidden" name="system_id" value="<?php echo (int) $selectedSystemId; ?>" />
                                <input type="hidden" name="scale_id" value="<?php echo (int) $scale['id']; ?>" />
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this grade scale?')">Delete</button>
                              </form>
                            </td>
                          </tr>
                          <tr class="edit-row" data-scale-id="<?php echo (int) $scale['id']; ?>">
                            <td colspan="5">
                              <form method="POST" action="grading-management.php" class="row g-2">
                                <input type="hidden" name="action" value="update_grading_scale" />
                                <input type="hidden" name="system_id" value="<?php echo (int) $selectedSystemId; ?>" />
                                <input type="hidden" name="scale_id" value="<?php echo (int) $scale['id']; ?>" />
                                <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="grade_name" value="<?php echo htmlspecialchars((string) $scale['grade_name'], ENT_QUOTES, 'UTF-8'); ?>" required /></div>
                                <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="mark_from" value="<?php echo (float) $scale['mark_from']; ?>" step="0.01" required /></div>
                                <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="mark_to" value="<?php echo (float) $scale['mark_to']; ?>" step="0.01" required /></div>
                                <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="grade_description" value="<?php echo htmlspecialchars((string) ($scale['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></div>
                                <div class="col-md-3"><button type="submit" class="btn btn-sm btn-outline-success">Save</button> <button type="button" class="btn btn-sm btn-outline-secondary cancel-edit-btn">Cancel</button></div>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="text-muted mb-0">No grades defined yet.</p>
                <?php endif; ?>
              </div>
            </div>

            <!-- Remark Templates Section -->
            <div class="card mb-4" id="remark-templates">
              <div class="card-header bg-light border-0">
                <h5 class="card-title mb-0">Teacher Remark Templates</h5>
              </div>
              <div class="card-body">
                <form method="POST" action="grading-management.php" class="mb-4">
                  <input type="hidden" name="action" value="save_remark_template" />
                  <input type="hidden" name="system_id" value="<?php echo (int) $selectedSystemId; ?>" />
                  <h6>Add Remark Template</h6>
                  <div class="row g-3 mb-3">
                    <div class="col-md-3">
                      <label for="remark_grade_label" class="form-label">Grade</label>
                      <select class="form-select" id="remark_grade_label" name="grade_label" required>
                        <option value="">Select a grade...</option>
                        <?php foreach ($gradingScales as $scale): ?>
                          <option value="<?php echo htmlspecialchars((string) $scale['grade_label'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) $scale['grade_label'] . ' - ' . $scale['grade_name'], ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-9">
                      <label for="remark_template" class="form-label">Remark Template</label>
                      <textarea class="form-control" id="remark_template" name="remark_template" rows="2" placeholder="Enter a standard remark for this grade..." required></textarea>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-sm btn-outline-primary">Add Template</button>
                </form>

                <?php if ($remarkTemplates): ?>
                  <h6>Existing Templates</h6>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Grade</th>
                          <th>Remark Template</th>
                          <th class="text-center">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($remarkTemplates as $template): ?>
                          <tr class="view-row" data-template-id="<?php echo (int) $template['id']; ?>">
                            <td><span class="badge bg-info"><?php echo htmlspecialchars((string) $template['grade_label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) $template['remark_template'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center">
                              <button type="button" class="btn btn-sm btn-outline-secondary template-edit-btn" data-template-id="<?php echo (int) $template['id']; ?>">Edit</button>
                              <form method="POST" action="grading-management.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete_remark_template" />
                                <input type="hidden" name="system_id" value="<?php echo (int) $selectedSystemId; ?>" />
                                <input type="hidden" name="template_id" value="<?php echo (int) $template['id']; ?>" />
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this template?')">Delete</button>
                              </form>
                            </td>
                          </tr>
                          <tr class="edit-row" data-template-id="<?php echo (int) $template['id']; ?>">
                            <td colspan="3">
                              <form method="POST" action="grading-management.php" class="row g-2">
                                <input type="hidden" name="action" value="update_remark_template" />
                                <input type="hidden" name="system_id" value="<?php echo (int) $selectedSystemId; ?>" />
                                <input type="hidden" name="template_id" value="<?php echo (int) $template['id']; ?>" />
                                <div class="col-12">
                                  <textarea class="form-control form-control-sm" name="remark_template" rows="2" required><?php echo htmlspecialchars((string) $template['remark_template'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <div class="col-12">
                                  <button type="submit" class="btn btn-sm btn-outline-success">Save</button>
                                  <button type="button" class="btn btn-sm btn-outline-secondary cancel-template-edit-btn">Cancel</button>
                                </div>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="text-muted mb-0">No remark templates defined yet.</p>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Promotion Status Remarks Section -->
    <div class="card mt-4" id="promotion-remarks">
      <div class="card-header bg-light border-0">
        <h5 class="card-title mb-0">Promotion Status & School Remarks</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Manage school-specific status remarks like Promoted, Repeat, Change Station, etc.</p>
        
        <form method="POST" action="grading-management.php" class="mb-4">
          <input type="hidden" name="action" value="create_promotion_remark" />
          <h6>Add New Status Remark</h6>
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label for="remark_label" class="form-label">Remark Label</label>
              <input type="text" class="form-control" id="remark_label" name="remark_label" placeholder="e.g., Promoted" required />
            </div>
            <div class="col-md-3">
              <label for="remark_category" class="form-label">Category</label>
              <select class="form-select" id="remark_category" name="remark_category" required>
                <option value="promotion">Promotion Status</option>
                <option value="academic_status">Academic Status</option>
                <option value="transfer">Transfer/Change Station</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="remark_description" class="form-label">Description</label>
              <input type="text" class="form-control" id="remark_description" name="remark_description" placeholder="Describe this status..." required />
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-outline-primary">Add Status Remark</button>
        </form>

        <?php if ($promotionRemarks): ?>
          <h6>Existing Status Remarks</h6>
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead class="table-light">
                <tr>
                  <th>Label</th>
                  <th>Category</th>
                  <th>Description</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($promotionRemarks as $remark): ?>
                  <tr class="view-row" data-remark-id="<?php echo (int) $remark['id']; ?>">
                    <td><strong><?php echo htmlspecialchars((string) $remark['remark_label'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string) ucfirst(str_replace('_', ' ', (string) $remark['remark_category'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?php echo htmlspecialchars((string) $remark['remark_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-center"><span class="badge <?php echo $remark['is_active'] ? 'bg-success' : 'bg-danger'; ?>"><?php echo $remark['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td class="text-center">
                      <button type="button" class="btn btn-sm btn-outline-secondary promo-edit-btn" data-remark-id="<?php echo (int) $remark['id']; ?>">Edit</button>
                      <form method="POST" action="grading-management.php" style="display:inline;">
                        <input type="hidden" name="action" value="delete_promotion_remark" />
                        <input type="hidden" name="remark_id" value="<?php echo (int) $remark['id']; ?>" />
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this status remark?')">Delete</button>
                      </form>
                    </td>
                  </tr>
                  <tr class="edit-row" data-remark-id="<?php echo (int) $remark['id']; ?>">
                    <td colspan="5">
                      <form method="POST" action="grading-management.php" class="row g-2">
                        <input type="hidden" name="action" value="update_promotion_remark" />
                        <input type="hidden" name="remark_id" value="<?php echo (int) $remark['id']; ?>" />
                        <div class="col-12">
                          <label class="form-label">Description</label>
                          <input type="text" class="form-control form-control-sm" name="remark_description" value="<?php echo htmlspecialchars((string) $remark['remark_description'], ENT_QUOTES, 'UTF-8'); ?>" required />
                        </div>
                        <div class="col-12">
                          <button type="submit" class="btn btn-sm btn-outline-success">Save</button>
                          <button type="button" class="btn btn-sm btn-outline-secondary cancel-promo-edit-btn">Cancel</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">No promotion status remarks configured yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script>
    // Grade Scale Edit
    document.querySelectorAll('.edit-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const scaleId = this.getAttribute('data-scale-id');
        document.querySelector(`.view-row[data-scale-id="${scaleId}"]`).classList.add('editing');
        document.querySelector(`.edit-row[data-scale-id="${scaleId}"]`).classList.add('show');
      });
    });

    document.querySelectorAll('.cancel-edit-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const row = this.closest('.edit-row');
        const scaleId = row.getAttribute('data-scale-id');
        document.querySelector(`.view-row[data-scale-id="${scaleId}"]`).classList.remove('editing');
        row.classList.remove('show');
      });
    });

    // Template Edit
    document.querySelectorAll('.template-edit-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const templateId = this.getAttribute('data-template-id');
        document.querySelector(`.view-row[data-template-id="${templateId}"]`).classList.add('editing');
        document.querySelector(`.edit-row[data-template-id="${templateId}"]`).classList.add('show');
      });
    });

    document.querySelectorAll('.cancel-template-edit-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const row = this.closest('.edit-row');
        const templateId = row.getAttribute('data-template-id');
        document.querySelector(`.view-row[data-template-id="${templateId}"]`).classList.remove('editing');
        row.classList.remove('show');
      });
    });

    // Promotion Remark Edit
    document.querySelectorAll('.promo-edit-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const remarkId = this.getAttribute('data-remark-id');
        document.querySelector(`.view-row[data-remark-id="${remarkId}"]`).classList.add('editing');
        document.querySelector(`.edit-row[data-remark-id="${remarkId}"]`).classList.add('show');
      });
    });

    document.querySelectorAll('.cancel-promo-edit-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const row = this.closest('.edit-row');
        const remarkId = row.getAttribute('data-remark-id');
        document.querySelector(`.view-row[data-remark-id="${remarkId}"]`).classList.remove('editing');
        row.classList.remove('show');
      });
    });
  </script>
</body>
</html>
