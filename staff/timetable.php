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

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Minimal color palette
$colors = ['#5a6c7d', '#4a5f73', '#3d5264', '#2e445a', '#1f3650'];

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
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .timetable-header-left h1 {
      margin-bottom: 0.5rem;
    }

    .style-toggle-btn {
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.4);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9rem;
    }

    .style-toggle-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      color: white;
      text-decoration: none;
    }

    .style-toggle-btn.active {
      background: rgba(255, 255, 255, 0.4);
      border-color: white;
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
      width: 90px;
    }

    .timetable td {
      border: 1px solid #dee2e6;
      padding: 0.5rem;
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
      width: 90px;
      cursor: pointer;
      padding: 0.75rem 0.5rem;
    }

    .timetable td.time-column:hover {
      background-color: #e9ecef;
    }

    .time-edit-input {
      width: 70px;
      padding: 0.25rem;
      border: 1px solid #dee2e6;
      border-radius: 4px;
      font-size: 0.85rem;
    }

    .class-slot {
      background: rgba(90, 108, 125, 0.1);
      color: #5a6c7d;
      padding: 0.5rem;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid transparent;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      height: 100%;
      font-size: 0.85rem;
      font-weight: 500;
      position: relative;
    }

    .class-slot:hover {
      border-color: #667eea;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    }

    .class-slot.colored {
      background: linear-gradient(135deg, var(--slot-color, #5a6c7d), rgba(90, 108, 125, 0.3));
      color: white;
      border-color: var(--slot-color, #5a6c7d);
    }

    .class-slot.colored:hover {
      border-color: var(--slot-color, #5a6c7d);
      box-shadow: 0 4px 8px rgba(90, 108, 125, 0.25);
    }

    .class-slot-title {
      font-weight: 600;
      margin-bottom: 0.25rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      width: 90%;
    }

    .class-slot-room {
      font-size: 0.7rem;
      opacity: 0.8;
      margin-bottom: 0.1rem;
    }

    .class-slot-students {
      font-size: 0.7rem;
      opacity: 0.8;
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

    .timetable-actions {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    .btn-timetable {
      padding: 0.5rem 1rem;
      font-size: 0.9rem;
      border-radius: 6px;
      transition: all 0.2s;
    }

    .modal-backdrop {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }

    .modal-backdrop.active {
      display: block;
    }

    .modal-dialog {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      border-radius: 12px;
      padding: 2rem;
      z-index: 1000;
      min-width: 400px;
      max-width: 600px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      display: none;
    }

    .modal-dialog.active {
      display: block;
    }

    .modal-header {
      margin-bottom: 1.5rem;
      border-bottom: 2px solid #f0f0f0;
      padding-bottom: 1rem;
    }

    .modal-header h5 {
      margin: 0;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      font-size: 0.95rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      font-size: 0.95rem;
      box-sizing: border-box;
    }

    .form-group textarea {
      resize: vertical;
      min-height: 60px;
    }

    .modal-footer {
      display: flex;
      gap: 0.75rem;
      justify-content: flex-end;
      margin-top: 1.5rem;
      padding-top: 1rem;
      border-top: 2px solid #f0f0f0;
    }

    .modal-footer button {
      padding: 0.6rem 1.5rem;
      font-size: 0.9rem;
      border-radius: 6px;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }

    .btn-primary {
      background: #667eea;
      color: white;
    }

    .btn-primary:hover {
      background: #5568d3;
    }

    .btn-secondary {
      background: #e0e0e0;
      color: #333;
    }

    .btn-secondary:hover {
      background: #d0d0d0;
    }

    .btn-danger {
      background: #e74c3c;
      color: white;
    }

    .btn-danger:hover {
      background: #c0392b;
    }

    .week-display {
      text-align: center;
      font-weight: 500;
      color: #667eea;
      margin: 0 1rem;
      min-width: 200px;
    }

    .alert {
      padding: 1rem;
      border-radius: 6px;
      margin-bottom: 1rem;
    }

    .alert-info {
      background: #e3f2fd;
      color: #1565c0;
      border: 1px solid #90caf9;
    }

    .alert-success {
      background: #e8f5e9;
      color: #2e7d32;
      border: 1px solid #81c784;
    }

    .alert-error {
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ef5350;
    }

    .edit-indicator {
      position: absolute;
      top: 2px;
      right: 2px;
      width: 6px;
      height: 6px;
      background: #667eea;
      border-radius: 50%;
    }

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
        flex-direction: column;
        align-items: flex-start;
      }

      .timetable-header-left {
        width: 100%;
      }

      .modal-dialog {
        min-width: 90%;
        max-width: 90%;
        padding: 1.5rem;
      }
    }

    @media print {
      body {
        background: white;
      }

      .page-shell > .row,
      .page-shell > .timetable-actions,
      nav,
      footer,
      .sidebar-filters,
      .col-lg-3,
      .style-toggle-btn,
      .btn-timetable,
      #alertContainer,
      .modal-backdrop,
      .modal-dialog {
        display: none !important;
      }

      .timetable-header {
        background: white !important;
        color: #333 !important;
        border: 1px solid #ddd;
        padding: 1rem;
        margin-bottom: 1rem;
      }

      .timetable-header p {
        color: #666 !important;
        opacity: 1 !important;
      }

      .timetable-wrapper {
        box-shadow: none;
        border: 1px solid #ddd;
      }

      .timetable {
        page-break-inside: avoid;
      }

      .timetable th,
      .timetable td {
        border: 1px solid #999;
        padding: 0.5rem;
      }

      .timetable th {
        background: #f5f5f5 !important;
      }

      .timetable td.time-column {
        background: #f5f5f5 !important;
      }

      .class-slot {
        background: white !important;
        color: #333 !important;
        border: 1px solid #ccc !important;
        break-inside: avoid;
      }

      .class-slot.colored {
        background: white !important;
      }

      .edit-indicator {
        display: none !important;
      }
    }
  </style>
</head>
<body>
  <?php renderPortalNavigation('staff', 'timetable', $staffName); ?>

  <main class="page-shell">
    <!-- Header Section -->
    <div class="timetable-header">
      <div class="timetable-header-left">
        <h1 class="h3 mb-1">Weekly Timetable</h1>
        <p class="mb-0 text-white" style="opacity: 0.9;">Click on subjects to edit • Click times to modify • Click empty slots to add classes</p>
      </div>
      <div>
        <button class="style-toggle-btn active" id="coloredBtn" onclick="switchStyle('colored')">🎨 Colored</button>
        <button class="style-toggle-btn" id="plainBtn" onclick="switchStyle('plain')">⚪ Plain</button>
      </div>
    </div>

    <div class="row g-3">
      <!-- Sidebar -->
      <div class="col-lg-3">
        <div class="sidebar-filters">
          <div class="filter-section">
            <h6>Quick Actions</h6>
            <div class="d-grid gap-2">
              <button class="btn btn-primary btn-timetable" onclick="openAddClassModal()">
                + Add Class
              </button>
              <button class="btn btn-outline-secondary btn-timetable" onclick="clearAllClasses()">
                🗑️ Clear All
              </button>
              <button class="btn btn-outline-secondary btn-timetable" onclick="exportData()">
                💾 Save/Export
              </button>
              <button class="btn btn-outline-secondary btn-timetable" onclick="printTimetable()">
                🖨️ Print
              </button>
            </div>
          </div>

          <hr class="my-3" />

          <div class="filter-section">
            <h6>Classes in System</h6>
            <?php if (!empty($classNames)): ?>
              <?php foreach ($classNames as $className): ?>
                <div class="filter-checkbox">
                  <input type="checkbox" id="class-<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>" checked />
                  <label for="class-<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>
                  </label>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-secondary small">No classes available</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Main Timetable -->
      <div class="col-lg-9">
        <!-- Week Navigation -->
        <div class="timetable-actions">
          <button class="btn btn-outline-secondary btn-timetable" onclick="previousWeek()">← Previous Week</button>
          <div class="week-display">
            <span id="weekDisplay">Week of June 10, 2026</span>
          </div>
          <button class="btn btn-outline-secondary btn-timetable" onclick="nextWeek()">Next Week →</button>
          <button class="btn btn-outline-primary btn-timetable" onclick="currentWeek()">↻ Today</button>
        </div>

        <!-- Alerts -->
        <div id="alertContainer"></div>

        <!-- Timetable Grid -->
        <div class="timetable-wrapper">
          <table class="timetable" id="timetableGrid">
            <thead>
              <tr>
                <th class="time-column">Time</th>
                <?php foreach ($daysOfWeek as $day): ?>
                  <th><?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody id="timetableBody">
              <!-- Populated by JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- Add/Edit Class Modal -->
  <div class="modal-backdrop" id="modalBackdrop" onclick="closeModal()"></div>
  <div class="modal-dialog" id="classModal">
    <div class="modal-header">
      <h5 id="modalTitle">Add Class</h5>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label for="classSubject">Subject Name *</label>
        <input type="text" id="classSubject" placeholder="e.g., Mathematics, English, Science" />
      </div>
      <div class="form-group">
        <label for="classDay">Day *</label>
        <select id="classDay">
          <option value="">-- Select Day --</option>
          <?php foreach ($daysOfWeek as $day): ?>
            <option value="<?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="classTime">Time *</label>
        <select id="classTime">
          <option value="">-- Select Time --</option>
          <?php foreach ($timeSlots as $time): ?>
            <option value="<?php echo htmlspecialchars($time, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($time, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="classRoom">Room (Optional)</label>
        <input type="text" id="classRoom" placeholder="e.g., A101, Lab-1" />
      </div>
      <div class="form-group">
        <label for="classStudents">Number of Students</label>
        <input type="number" id="classStudents" placeholder="e.g., 28" min="0" />
      </div>
      <div class="form-group">
        <label for="classNotes">Notes (Optional)</label>
        <textarea id="classNotes" placeholder="Add any notes about this class..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn-danger" id="deleteBtn" style="display: none;" onclick="deleteClass()">Delete</button>
      <button class="btn-primary" onclick="saveClass()">Save Class</button>
    </div>
  </div>

  <?php renderAdministrationFooter(); ?>
  <script src="<?php echo htmlspecialchars(administration_asset_url('vendor/bootstrap/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(administration_asset_url('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

  <script>
    // Timetable Data Management
    const daysOfWeek = <?php echo json_encode($daysOfWeek); ?>;
    const timeSlots = <?php echo json_encode($timeSlots); ?>;
    const colors = <?php echo json_encode($colors); ?>;
    
    let currentStyle = 'colored';
    let currentWeekOffset = 0;
    let timetableData = {};
    let editingSlot = null;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
      loadTimetableData();
      renderTimetable();
      updateWeekDisplay();
    });

    // Load data from localStorage
    function loadTimetableData() {
      const stored = localStorage.getItem('timetableData');
      if (stored) {
        try {
          timetableData = JSON.parse(stored);
        } catch (e) {
          console.error('Error loading timetable data:', e);
          timetableData = {};
        }
      }
    }

    // Save data to localStorage
    function saveTimetableData() {
      localStorage.setItem('timetableData', JSON.stringify(timetableData));
      showAlert('Timetable saved!', 'success');
    }

    // Generate unique key for a slot
    function getSlotKey(day, time) {
      return day + '-' + time;
    }

    // Render the complete timetable
    function renderTimetable() {
      const tbody = document.getElementById('timetableBody');
      tbody.innerHTML = '';

      timeSlots.forEach((time, index) => {
        const row = document.createElement('tr');
        
        // Time cell (editable)
        const timeCell = document.createElement('td');
        timeCell.className = 'time-column';
        timeCell.onclick = () => editTime(time, index);
        timeCell.textContent = time;
        timeCell.title = 'Click to edit time';
        row.appendChild(timeCell);

        // Class cells for each day
        daysOfWeek.forEach(day => {
          const cell = document.createElement('td');
          const key = getSlotKey(day, time);
          const classData = timetableData[key];

          if (classData) {
            const slot = document.createElement('div');
            slot.className = 'class-slot ' + (currentStyle === 'colored' ? 'colored' : '');
            
            if (currentStyle === 'colored') {
              const colorIndex = Object.keys(timetableData).indexOf(key) % colors.length;
              slot.style.setProperty('--slot-color', colors[colorIndex]);
            }

            const subjectHtml = escapeHtml(classData.subject);
            const roomHtml = classData.room ? '<div class="class-slot-room">' + escapeHtml(classData.room) + '</div>' : '';
            const studentsHtml = classData.students ? '<div class="class-slot-students">' + classData.students + ' 👥</div>' : '';
            
            slot.innerHTML = '<div class="class-slot-title" onclick="editClass(\'' + escapeHtml(key) + '\', event)">' + subjectHtml + '</div>' + roomHtml + studentsHtml + '<div class="edit-indicator"></div>';

            slot.onclick = () => editClass(key);
            cell.appendChild(slot);
          } else {
            cell.className = 'empty-slot';
            cell.onclick = () => openAddClassModal(day, time);
            cell.title = 'Click to add class';
          }

          row.appendChild(cell);
        });

        tbody.appendChild(row);
      });
    }

    // Switch between colored and plain styles
    function switchStyle(style) {
      currentStyle = style;
      document.getElementById('coloredBtn').classList.toggle('active', style === 'colored');
      document.getElementById('plainBtn').classList.toggle('active', style === 'plain');
      renderTimetable();
    }

    // Edit time slot
    function editTime(time, index) {
      const cell = event.target;
      const originalText = cell.textContent;
      
      const input = document.createElement('input');
      input.type = 'text';
      input.className = 'time-edit-input';
      input.value = time;
      input.placeholder = 'HH:MM';
      
      cell.textContent = '';
      cell.appendChild(input);
      input.focus();
      input.select();

      function saveTime() {
        const newTime = input.value.trim();
        if (newTime && newTime !== time) {
          if (timeSlots.includes(newTime)) {
            showAlert('Time slot ' + newTime + ' already exists!', 'error');
            cell.textContent = originalText;
          } else {
            // Update all entries with old time to new time
            const keysToUpdate = Object.keys(timetableData).filter(k => k.endsWith('-' + time));
            keysToUpdate.forEach(oldKey => {
              const data = timetableData[oldKey];
              const day = oldKey.split('-')[0];
              delete timetableData[oldKey];
              timetableData[getSlotKey(day, newTime)] = data;
            });
            
            timeSlots[index] = newTime;
            saveTimetableData();
            renderTimetable();
          }
        } else {
          cell.textContent = originalText;
        }
      }

      input.onblur = saveTime;
      input.onkeypress = (e) => {
        if (e.key === 'Enter') saveTime();
        if (e.key === 'Escape') { cell.textContent = originalText; }
      };
    }

    // Open add class modal
    function openAddClassModal(day = null, time = null) {
      editingSlot = null;
      document.getElementById('modalTitle').textContent = 'Add New Class';
      document.getElementById('classSubject').value = '';
      document.getElementById('classDay').value = day || '';
      document.getElementById('classTime').value = time || '';
      document.getElementById('classRoom').value = '';
      document.getElementById('classStudents').value = '';
      document.getElementById('classNotes').value = '';
      document.getElementById('deleteBtn').style.display = 'none';
      
      document.getElementById('modalBackdrop').classList.add('active');
      document.getElementById('classModal').classList.add('active');
    }

    // Edit existing class
    function editClass(key, event) {
      if (event) event.stopPropagation();
      
      editingSlot = key;
      const parts = key.split('-');
      const day = parts[0];
      const time = parts[1];
      const classData = timetableData[key];
      
      document.getElementById('modalTitle').textContent = 'Edit Class';
      document.getElementById('classSubject').value = classData.subject || '';
      document.getElementById('classDay').value = day;
      document.getElementById('classTime').value = time;
      document.getElementById('classRoom').value = classData.room || '';
      document.getElementById('classStudents').value = classData.students || '';
      document.getElementById('classNotes').value = classData.notes || '';
      document.getElementById('deleteBtn').style.display = 'block';
      
      document.getElementById('modalBackdrop').classList.add('active');
      document.getElementById('classModal').classList.add('active');
    }

    // Save class
    function saveClass() {
      const subject = document.getElementById('classSubject').value.trim();
      const day = document.getElementById('classDay').value;
      const time = document.getElementById('classTime').value;
      const room = document.getElementById('classRoom').value.trim();
      const students = parseInt(document.getElementById('classStudents').value) || 0;
      const notes = document.getElementById('classNotes').value.trim();

      if (!subject) {
        showAlert('Please enter a subject name', 'error');
        return;
      }
      if (!day || !time) {
        showAlert('Please select day and time', 'error');
        return;
      }

      const key = getSlotKey(day, time);

      // Check for conflicts
      if (editingSlot !== key && timetableData[key]) {
        showAlert('A class already exists at this time!', 'error');
        return;
      }

      // Delete old slot if editing
      if (editingSlot && editingSlot !== key) {
        delete timetableData[editingSlot];
      }

      timetableData[key] = {
        subject: subject,
        room: room || null,
        students: students,
        notes: notes || null
      };

      saveTimetableData();
      closeModal();
      renderTimetable();
      showAlert('Class "' + subject + '" saved successfully!', 'success');
    }

    // Delete class
    function deleteClass() {
      if (editingSlot && confirm('Are you sure you want to delete this class?')) {
        delete timetableData[editingSlot];
        saveTimetableData();
        closeModal();
        renderTimetable();
        showAlert('Class deleted successfully', 'success');
      }
    }

    // Close modal
    function closeModal() {
      document.getElementById('modalBackdrop').classList.remove('active');
      document.getElementById('classModal').classList.remove('active');
      editingSlot = null;
    }

    // Week navigation
    function getWeekStart(offset) {
      const date = new Date();
      const currentDay = date.getDay();
      const diff = date.getDate() - currentDay + (currentDay === 0 ? -6 : 1) + (offset * 7);
      return new Date(date.setDate(diff));
    }

    function updateWeekDisplay() {
      const weekStart = getWeekStart(currentWeekOffset);
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekEnd.getDate() + 6);
      
      const options = { month: 'short', day: 'numeric', year: 'numeric' };
      const startStr = weekStart.toLocaleDateString('en-US', options);
      const endStr = weekEnd.toLocaleDateString('en-US', options);
      
      document.getElementById('weekDisplay').textContent = startStr + ' - ' + endStr;
    }

    function previousWeek() {
      currentWeekOffset--;
      updateWeekDisplay();
    }

    function nextWeek() {
      currentWeekOffset++;
      updateWeekDisplay();
    }

    function currentWeek() {
      currentWeekOffset = 0;
      updateWeekDisplay();
    }

    // Clear all classes
    function clearAllClasses() {
      if (confirm('Are you sure you want to clear all classes? This cannot be undone.')) {
        timetableData = {};
        saveTimetableData();
        renderTimetable();
        showAlert('All classes cleared', 'success');
      }
    }

    // Export/Save data
    function exportData() {
      const dataStr = JSON.stringify(timetableData, null, 2);
      const dataBlob = new Blob([dataStr], { type: 'application/json' });
      const url = URL.createObjectURL(dataBlob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'timetable-' + new Date().toISOString().slice(0, 10) + '.json';
      link.click();
      showAlert('Timetable exported!', 'success');
    }

    // Print timetable
    function printTimetable() {
      window.print();
    }

    // Show alert
    function showAlert(message, type) {
      if (!type) type = 'info';
      const container = document.getElementById('alertContainer');
      const alert = document.createElement('div');
      alert.className = 'alert alert-' + type;
      alert.textContent = message;
      container.appendChild(alert);
      
      setTimeout(() => alert.remove(), 3000);
    }

    // Escape HTML
    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return String(text).replace(/[&<>"']/g, m => map[m]);
    }
  </script>
</body>
</html>
