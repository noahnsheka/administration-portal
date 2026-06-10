<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireRole('staff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/portal.php';

$staffName = (string) ($_SESSION['user']['full_name'] ?? 'Staff Member');
$pdo = getDatabaseConnection();
$classNames = getDistinctClassNames($pdo);

// Default time slots for timetable (8:00 AM to 4:00 PM)
$timeSlots = [
  '08:00', '08:45', '09:30', '10:15', '11:00', '11:45', 
  '12:30', '13:15', '14:00', '14:45', '15:30', '16:15'
];

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Color palette for different classes
$colors = [
  '#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', 
  '#1abc9c', '#34495e', '#e67e22', '#c0392b', '#27ae60'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff | Timetable</title>
  <link href="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(administration_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260501" />
  <style>
    .timetable-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 2rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
    }

    .timetable-wrapper {
      overflow-x: auto;
      background: white;
      border-radius: 12px;
      border: 1px solid #e0e0e0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .timetable {
      width: 100%;
      border-collapse: collapse;
      min-width: 800px;
    }

    .timetable th {
      background-color: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
      padding: 1rem;
      text-align: center;
      font-weight: 600;
      color: #495057;
      font-size: 0.95rem;
    }

    .timetable th.time-column {
      background-color: #e9ecef;
      width: 80px;
    }

    .timetable td {
      border: 1px solid #dee2e6;
      padding: 0.75rem;
      text-align: center;
      vertical-align: middle;
      height: 80px;
      position: relative;
    }

    .timetable td.time-column {
      background-color: #f8f9fa;
      font-weight: 500;
      color: #495057;
      font-size: 0.9rem;
      width: 80px;
    }

    .class-slot {
      background: linear-gradient(135deg, var(--slot-color, #3498db), rgba(52, 152, 219, 0.3));
      color: white;
      padding: 0.5rem;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      text-decoration: none;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      height: 100%;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .class-slot:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
      text-decoration: none;
      color: white;
    }

    .class-slot-title {
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .class-slot-room {
      font-size: 0.75rem;
      opacity: 0.9;
      margin-bottom: 0.15rem;
    }

    .class-slot-students {
      font-size: 0.7rem;
      opacity: 0.85;
    }

    .break-slot {
      background-color: #f0f0f0;
      color: #666;
      font-weight: 500;
    }

    .empty-slot {
      background-color: #fafafa;
    }

    .sidebar-filters {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      border: 1px solid #e0e0e0;
    }

    .filter-section h6 {
      font-weight: 600;
      color: #333;
      margin-bottom: 1rem;
      font-size: 0.95rem;
    }

    .filter-checkbox {
      margin-bottom: 0.75rem;
    }

    .filter-checkbox input[type="checkbox"] {
      margin-right: 0.5rem;
    }

    .filter-checkbox label {
      margin-bottom: 0;
      cursor: pointer;
      font-size: 0.95rem;
    }

    .color-legend {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .legend-color {
      width: 24px;
      height: 24px;
      border-radius: 4px;
      flex-shrink: 0;
    }

    .legend-label {
      font-size: 0.9rem;
      color: #555;
    }

    .timetable-actions {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      margin-bottom: 1.5rem;
    }

    .btn-timetable {
      padding: 0.5rem 1rem;
      font-size: 0.9rem;
      border-radius: 6px;
      transition: all 0.2s;
    }

    .empty-state-message {
      padding: 2rem;
      text-align: center;
      color: #999;
      background-color: #f9f9f9;
      border-radius: 8px;
      border: 2px dashed #ddd;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .timetable {
        font-size: 0.8rem;
      }

      .timetable td {
        height: 60px;
        padding: 0.5rem;
      }

      .class-slot {
        font-size: 0.7rem;
      }

      .timetable-header {
        padding: 1.5rem 1rem;
      }

      .color-legend {
        grid-template-columns: repeat(2, 1fr);
      }
    }
  </style>
</head>
<body>
  <?php renderPortalNavigation('staff', 'timetable', $staffName); ?>

  <main class="page-shell">
    <!-- Header Section -->
    <div class="timetable-header">
      <h1 class="h3 mb-2">Weekly Timetable</h1>
      <p class="mb-0">View and manage your class schedule, room allocation, and student attendance</p>
    </div>

    <div class="row g-3">
      <!-- Sidebar Filters -->
      <div class="col-lg-3">
        <div class="sidebar-filters">
          <div class="filter-section">
            <h6>Filter by Class</h6>
            <?php if (!empty($classNames)): ?>
              <?php foreach ($classNames as $index => $className): ?>
                <div class="filter-checkbox">
                  <input 
                    type="checkbox" 
                    id="class-<?php echo $index; ?>" 
                    name="class-filter" 
                    value="<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>"
                    checked
                  />
                  <label for="class-<?php echo $index; ?>">
                    <?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>
                  </label>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-secondary">No classes available</p>
            <?php endif; ?>
          </div>

          <hr class="my-3" />

          <div class="filter-section">
            <h6>Quick Actions</h6>
            <div class="d-grid gap-2">
              <button class="btn btn-primary btn-timetable" onclick="alert('Edit Timetable feature coming soon')">
                + Add Class Slot
              </button>
              <button class="btn btn-outline-secondary btn-timetable" onclick="alert('Bulk Edit feature coming soon')">
                ⚙️ Bulk Edit
              </button>
              <button class="btn btn-outline-secondary btn-timetable" onclick="printTimetable()">
                🖨️ Print Schedule
              </button>
            </div>
          </div>
        </div>

        <!-- Color Legend -->
        <div class="sidebar-filters">
          <h6 class="mb-3">Class Color Legend</h6>
          <div class="color-legend">
            <?php foreach ($classNames as $index => $className): ?>
              <div class="legend-item">
                <div 
                  class="legend-color" 
                  style="background-color: <?php echo htmlspecialchars($colors[$index % count($colors)], ENT_QUOTES, 'UTF-8'); ?>"
                ></div>
                <span class="legend-label">
                  <?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Main Timetable Section -->
      <div class="col-lg-9">
        <!-- Timetable Actions -->
        <div class="timetable-actions">
          <button class="btn btn-sm btn-outline-secondary" onclick="previousWeek()">← Previous Week</button>
          <button class="btn btn-sm btn-outline-primary" onclick="currentWeek()">Current Week</button>
          <button class="btn btn-sm btn-outline-secondary" onclick="nextWeek()">Next Week →</button>
        </div>

        <!-- Timetable Grid -->
        <div class="timetable-wrapper">
          <table class="timetable">
            <thead>
              <tr>
                <th class="time-column">Time</th>
                <?php foreach ($daysOfWeek as $day): ?>
                  <th><?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($timeSlots as $time): ?>
                <tr>
                  <td class="time-column"><?php echo htmlspecialchars($time, ENT_QUOTES, 'UTF-8'); ?></td>
                  <?php foreach ($daysOfWeek as $day): ?>
                    <td class="empty-slot">
                      <!-- Sample class slot template -->
                      <div 
                        class="class-slot"
                        style="--slot-color: <?php echo htmlspecialchars($colors[array_search($day, $daysOfWeek) % count($colors)], ENT_QUOTES, 'UTF-8'); ?>"
                        title="Click to view/edit class details"
                      >
                        <!-- Class slot content will be populated here dynamically -->
                        <div class="empty-state-inline" style="opacity: 0.3; font-size: 0.7rem;">
                          [No class]
                        </div>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Timetable Information Section -->
        <div class="section-card mt-4">
          <h5 class="mb-3">Schedule Template Guide</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <h6 class="text-muted">Timetable Features</h6>
              <ul class="list-unstyled text-secondary small">
                <li>✓ Color-coded classes for easy identification</li>
                <li>✓ Room allocation visible at a glance</li>
                <li>✓ Student count per class displayed</li>
                <li>✓ Break and lunch periods highlighted</li>
                <li>✓ Quick edit and substitution management</li>
              </ul>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted">Class Slot Information</h6>
              <ul class="list-unstyled text-secondary small">
                <li><strong>Class Name:</strong> Subject and grade level</li>
                <li><strong>Room:</strong> Location of the class</li>
                <li><strong>Students:</strong> Number of enrolled students</li>
                <li><strong>Time:</strong> Period duration shown in header</li>
                <li><strong>Status:</strong> Hover to see more options</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php renderAdministrationFooter(); ?>

  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

  <script>
    // Timetable functionality
    function previousWeek() {
      alert('Navigate to previous week - feature coming soon');
    }

    function nextWeek() {
      alert('Navigate to next week - feature coming soon');
    }

    function currentWeek() {
      alert('Show current week - feature coming soon');
    }

    function printTimetable() {
      window.print();
    }

    // Class filter functionality
    document.querySelectorAll('input[name="class-filter"]').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        // Filter functionality to be implemented
        console.log('Filter by class:', this.value, 'Checked:', this.checked);
      });
    });
  </script>
</body>
</html>

