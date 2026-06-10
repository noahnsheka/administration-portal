# Timetable Sample Data & Implementation Examples

## Sample Timetable Data Structure

### Example Schedule for a Teacher

```
Teacher: John Smith
Week: June 10-14, 2026

Monday:
├─ 08:00-08:45: Mathematics Class 10A (Room: A101, 28 students)
├─ 08:45-09:30: Physics Class 11B (Room: Lab-1, 22 students)
├─ 09:30-10:15: BREAK
├─ 10:15-11:00: Chemistry Class 10C (Room: Lab-2, 25 students)
├─ 11:00-11:45: Mathematics Class 9A (Room: A102, 30 students)
├─ 11:45-12:30: LUNCH
└─ 13:00-14:00: Staff Meeting (Room: Hall, N/A)

Tuesday:
├─ 08:00-08:45: Physics Class 12A (Room: Lab-1, 24 students)
├─ 08:45-09:30: Mathematics Class 10A (Room: A101, 28 students)
├─ 09:30-10:15: BREAK
├─ 10:15-11:00: Chemistry Class 11C (Room: Lab-2, 23 students)
└─ ... (pattern continues)
```

---

## Database Table Schema

### Main Table: `timetable_entries`

```sql
CREATE TABLE timetable_entries (
  id INT PRIMARY KEY AUTO_INCREMENT,
  teacher_id INT NOT NULL,
  class_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room_id INT,
  room_name VARCHAR(50),
  student_count INT DEFAULT 0,
  notes TEXT,
  is_substitute BOOLEAN DEFAULT FALSE,
  substitute_teacher_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES users(id),
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id),
  FOREIGN KEY (substitute_teacher_id) REFERENCES users(id)
);

CREATE TABLE rooms (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(20) UNIQUE NOT NULL,
  room_name VARCHAR(100) NOT NULL,
  capacity INT NOT NULL,
  room_type ENUM('Classroom', 'Laboratory', 'Auditorium', 'Hall', 'Office'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE classes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  class_name VARCHAR(50) NOT NULL,
  grade_level INT,
  total_students INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Sample Data Insertion

```sql
-- Insert sample rooms
INSERT INTO rooms (room_code, room_name, capacity, room_type) VALUES
('A101', 'Classroom A101', 35, 'Classroom'),
('A102', 'Classroom A102', 35, 'Classroom'),
('Lab-1', 'Science Lab 1', 25, 'Laboratory'),
('Lab-2', 'Chemistry Lab', 25, 'Laboratory'),
('H100', 'Main Hall', 200, 'Hall');

-- Insert sample classes
INSERT INTO classes (class_name, grade_level, total_students) VALUES
('Mathematics 10A', 10, 28),
('Physics 11B', 11, 22),
('Chemistry 10C', 10, 25),
('Mathematics 9A', 9, 30),
('Chemistry 11C', 11, 23),
('Physics 12A', 12, 24);

-- Insert sample timetable entries
INSERT INTO timetable_entries 
(teacher_id, class_id, subject, day_of_week, start_time, end_time, room_id, room_name, student_count)
VALUES
(1, 1, 'Mathematics', 'Monday', '08:00:00', '08:45:00', 1, 'A101', 28),
(1, 2, 'Physics', 'Monday', '08:45:00', '09:30:00', 3, 'Lab-1', 22),
(1, 3, 'Chemistry', 'Monday', '10:15:00', '11:00:00', 4, 'Lab-2', 25),
(1, 4, 'Mathematics', 'Monday', '11:00:00', '11:45:00', 2, 'A102', 30),
(1, 5, 'Physics', 'Tuesday', '08:00:00', '08:45:00', 3, 'Lab-1', 24),
(1, 1, 'Mathematics', 'Tuesday', '08:45:00', '09:30:00', 1, 'A101', 28),
(1, 6, 'Chemistry', 'Tuesday', '10:15:00', '11:00:00', 4, 'Lab-2', 23);
```

---

## PHP Implementation Example

### Function: Load Teacher's Weekly Schedule

```php
<?php

/**
 * Get timetable for specific teacher
 * 
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher's ID
 * @param string $week_start Date of week start (Y-m-d)
 * @return array Structured timetable data
 */
function getTeacherWeeklySchedule($pdo, $teacher_id, $week_start) {
  $stmt = $pdo->prepare("
    SELECT 
      te.id,
      te.day_of_week,
      te.start_time,
      te.end_time,
      c.class_name,
      te.subject,
      te.room_name,
      te.student_count,
      te.notes,
      te.is_substitute,
      u.full_name as substitute_name
    FROM timetable_entries te
    JOIN classes c ON te.class_id = c.id
    LEFT JOIN users u ON te.substitute_teacher_id = u.id
    WHERE te.teacher_id = ? 
    AND WEEK(DATE_ADD(te.day_of_week, INTERVAL DAYOFWEEK(:week_start)-2 DAY)) 
        = WEEK(:week_start)
    ORDER BY FIELD(te.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
             te.start_time
  ");

  $stmt->execute([
    ':teacher_id' => $teacher_id,
    ':week_start' => $week_start
  ]);

  $schedule = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $day = $row['day_of_week'];
    if (!isset($schedule[$day])) {
      $schedule[$day] = [];
    }
    $schedule[$day][] = $row;
  }

  return $schedule;
}

/**
 * Get color assignment for class
 * 
 * @param int $class_id Class identifier
 * @param array $colors Available colors
 * @return string Hex color code
 */
function getClassColor($class_id, $colors) {
  return $colors[$class_id % count($colors)];
}

/**
 * Check for scheduling conflicts
 * 
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher's ID
 * @param string $day Day of week
 * @param string $start_time Start time
 * @param string $end_time End time
 * @return array Conflicting entries
 */
function checkScheduleConflicts($pdo, $teacher_id, $day, $start_time, $end_time) {
  $stmt = $pdo->prepare("
    SELECT * FROM timetable_entries
    WHERE teacher_id = ?
    AND day_of_week = ?
    AND (
      (start_time < ? AND end_time > ?)
      OR (start_time >= ? AND start_time < ?)
    )
  ");

  $stmt->execute([$teacher_id, $day, $end_time, $start_time, $start_time, $end_time]);
  
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add timetable entry
 * 
 * @param PDO $pdo Database connection
 * @param array $data Entry data
 * @return int ID of new entry
 */
function addTimetableEntry($pdo, $data) {
  // Validate no conflicts
  $conflicts = checkScheduleConflicts(
    $pdo,
    $data['teacher_id'],
    $data['day_of_week'],
    $data['start_time'],
    $data['end_time']
  );

  if (!empty($conflicts)) {
    throw new Exception('Schedule conflict detected. Please choose different time.');
  }

  $stmt = $pdo->prepare("
    INSERT INTO timetable_entries 
    (teacher_id, class_id, subject, day_of_week, start_time, end_time, 
     room_id, room_name, student_count, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stmt->execute([
    $data['teacher_id'],
    $data['class_id'],
    $data['subject'],
    $data['day_of_week'],
    $data['start_time'],
    $data['end_time'],
    $data['room_id'] ?? NULL,
    $data['room_name'] ?? NULL,
    $data['student_count'] ?? 0,
    $data['notes'] ?? NULL
  ]);

  return $pdo->lastInsertId();
}

?>
```

---

## HTML Template Integration

### Example: Populating Timetable Grid

```php
<?php
// In timetable.php

$teacherId = $_SESSION['user']['id'];
$weekStart = $_GET['week'] ?? date('Y-m-d', strtotime('Monday this week'));
$schedule = getTeacherWeeklySchedule($pdo, $teacherId, $weekStart);

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c'];

?>

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
            <?php
            if (isset($schedule[$day])) {
              $classFound = false;
              foreach ($schedule[$day] as $entry) {
                if ($entry['start_time'] === $time) {
                  $classFound = true;
                  $color = $colors[$entry['class_id'] % count($colors)];
                  echo sprintf(
                    '<div class="class-slot" style="--slot-color: %s" data-id="%d">
                      <div class="class-slot-title">%s</div>
                      <div class="class-slot-room">%s</div>
                      <div class="class-slot-students">%d 👥</div>
                    </div>',
                    htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
                    (int)$entry['id'],
                    htmlspecialchars($entry['class_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($entry['room_name'] ?? 'TBA', ENT_QUOTES, 'UTF-8'),
                    (int)$entry['student_count']
                  );
                  break;
                }
              }
              if (!$classFound) {
                echo '<div class="empty-state-inline"></div>';
              }
            }
            ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
```

---

## Sample Output

When populated with data, the timetable would display:

```
┌──────────┬──────────────────┬──────────────────┬──────────────────┐
│  Time    │     Monday       │    Tuesday       │   Wednesday      │
├──────────┼──────────────────┼──────────────────┼──────────────────┤
│ 08:00    │ Math Class 10A   │ Physics Class 12 │ Chemistry 10C    │
│          │ Room: A101       │ Room: Lab-1      │ Room: Lab-2      │
│          │ 28 students      │ 24 students      │ 25 students      │
├──────────┼──────────────────┼──────────────────┼──────────────────┤
│ 08:45    │ Physics Class 11 │ Mathematics 10A  │ [No class]       │
│          │ Room: Lab-1      │ Room: A101       │                  │
│          │ 22 students      │ 28 students      │                  │
├──────────┼──────────────────┼──────────────────┼──────────────────┤
│ 09:30    │  [BREAK TIME]    │  [BREAK TIME]    │  [BREAK TIME]    │
├──────────┼──────────────────┼──────────────────┼──────────────────┤
│ 10:15    │ Chemistry 10C    │ Chemistry 11C    │ Physics Lab 11B  │
│          │ Room: Lab-2      │ Room: Lab-2      │ Room: Lab-1      │
│          │ 25 students      │ 23 students      │ 20 students      │
└──────────┴──────────────────┴──────────────────┴──────────────────┘
```

---

## Testing the Implementation

### Unit Test Example

```php
<?php

class TimetableTest {
  
  public function testGetTeacherWeeklySchedule() {
    $pdo = new PDO('sqlite::memory:');
    // Setup schema...
    
    $schedule = getTeacherWeeklySchedule($pdo, 1, '2026-06-10');
    
    assert(isset($schedule['Monday']));
    assert(count($schedule['Monday']) > 0);
    assert($schedule['Monday'][0]['class_name'] === 'Mathematics 10A');
    
    echo "✓ Weekly schedule loads correctly\n";
  }

  public function testConflictDetection() {
    // Test that overlapping times are detected
    $conflicts = checkScheduleConflicts($pdo, 1, 'Monday', '08:00', '08:45');
    assert(count($conflicts) > 0);
    echo "✓ Conflicts detected properly\n";
  }

  public function testColorAssignment() {
    $colors = ['#3498db', '#e74c3c', '#2ecc71'];
    $color1 = getClassColor(1, $colors);
    $color2 = getClassColor(2, $colors);
    assert($color1 !== $color2);
    echo "✓ Colors assigned uniquely\n";
  }

}

$test = new TimetableTest();
$test->testGetTeacherWeeklySchedule();
$test->testConflictDetection();
$test->testColorAssignment();

?>
```

---

## Notes for Integration

1. **Week Navigation**: Modify `previousWeek()` and `nextWeek()` to calculate week boundaries
2. **Dynamic Data**: Replace hardcoded time slots with database queries
3. **User Permissions**: Ensure only the teacher can edit their own schedule
4. **Notifications**: Alert teachers of schedule changes via email
5. **Export**: Generate PDF or iCal format for calendar apps

