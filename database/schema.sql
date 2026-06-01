CREATE DATABASE IF NOT EXISTS administration_suite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE administration_suite;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_number VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('student', 'staff', 'admin') NOT NULL,
  pin_code VARCHAR(20) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at TIMESTAMP NULL DEFAULT NULL,
  deactivation_reason VARCHAR(255) NULL DEFAULT NULL,
  class_name VARCHAR(50) NOT NULL DEFAULT 'Unassigned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_id_activity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  account_number VARCHAR(50) NOT NULL,
  student_name VARCHAR(120) NOT NULL,
  pin_code VARCHAR(20) NULL,
  action_type ENUM('generated', 'deactivated') NOT NULL,
  reason_text VARCHAR(255) NULL,
  batch_reference VARCHAR(40) NULL,
  performed_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_account_number (account_number),
  INDEX idx_activity_action_type (action_type),
  INDEX idx_activity_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_lists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_name VARCHAR(50) NOT NULL,
  class_stream VARCHAR(50) NOT NULL DEFAULT '',
  display_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  promoted_to_class_list_id INT NULL,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_class_list_name_stream (class_name, class_stream),
  UNIQUE KEY unique_class_list_display_name (display_name),
  INDEX idx_class_lists_active (is_active),
  INDEX idx_class_lists_display_name (display_name),
  FOREIGN KEY (promoted_to_class_list_id) REFERENCES class_lists(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_list_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_list_id INT NOT NULL,
  student_user_id INT NOT NULL,
  enrollment_status ENUM('active', 'transferred', 'removed') NOT NULL DEFAULT 'active',
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  removed_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY unique_class_list_student (class_list_id, student_user_id),
  INDEX idx_class_list_students_class (class_list_id, enrollment_status),
  INDEX idx_class_list_students_student (student_user_id, enrollment_status),
  FOREIGN KEY (class_list_id) REFERENCES class_lists(id) ON DELETE CASCADE,
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_code VARCHAR(20) NOT NULL UNIQUE,
  subject_name VARCHAR(100) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_name VARCHAR(100) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_exam_types_active (is_active),
  INDEX idx_exam_types_name (exam_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_years (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year_label VARCHAR(30) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_academic_years_active (is_active),
  INDEX idx_academic_years_label (year_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_teaching_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_user_id INT NOT NULL,
  class_list_id INT NOT NULL,
  subject_id INT NOT NULL,
  assigned_by_user_id INT NULL,
  assigned_by_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_staff_class_subject (staff_user_id, class_list_id, subject_id),
  INDEX idx_staff_teaching_staff (staff_user_id),
  INDEX idx_staff_teaching_class (class_list_id),
  INDEX idx_staff_teaching_subject (subject_id),
  FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (class_list_id) REFERENCES class_lists(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mark_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT NOT NULL,
  subject_id INT NOT NULL,
  class_name VARCHAR(50) NOT NULL,
  term_label VARCHAR(150) NOT NULL,
  mark_value DECIMAL(5,2) NOT NULL,
  entered_by_user_id INT NULL,
  entered_by_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_student_subject_term (student_user_id, subject_id, term_label),
  INDEX idx_marks_class_term (class_name, term_label),
  INDEX idx_marks_student (student_user_id),
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (entered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title_text VARCHAR(180) NOT NULL,
  body_text TEXT NOT NULL,
  audience ENUM('all', 'student', 'staff', 'admin') NOT NULL DEFAULT 'all',
  class_name VARCHAR(50) NULL,
  category ENUM('general', 'reports', 'marks') NOT NULL DEFAULT 'general',
  created_by VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_announcements_audience (audience),
  INDEX idx_announcements_class (class_name),
  INDEX idx_announcements_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT NOT NULL,
  title_text VARCHAR(180) NOT NULL,
  message_text TEXT NOT NULL,
  alert_type ENUM('info', 'success', 'warning') NOT NULL DEFAULT 'info',
  subject_name VARCHAR(100) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_student_alerts_student (student_user_id),
  INDEX idx_student_alerts_created_at (created_at),
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_publications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_name VARCHAR(50) NOT NULL,
  term_label VARCHAR(150) NOT NULL,
  publish_at DATETIME NOT NULL,
  published_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_class_term_report (class_name, term_label),
  INDEX idx_report_publish_at (publish_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  staff_name VARCHAR(120) NOT NULL,
  activity_type VARCHAR(80) NOT NULL,
  target_reference VARCHAR(120) NULL,
  details_text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staff_activity_created_at (created_at),
  INDEX idx_staff_activity_user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mark_scan_imports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_list_id INT NOT NULL,
  subject_id INT NOT NULL,
  term_label VARCHAR(150) NOT NULL,
  provider_name VARCHAR(80) NULL,
  source_filename VARCHAR(255) NULL,
  stored_file_path VARCHAR(255) NULL,
  raw_text LONGTEXT NOT NULL,
  parsed_matches_json LONGTEXT NOT NULL,
  unmatched_lines_json LONGTEXT NULL,
  warnings_json LONGTEXT NULL,
  applied_marks_json LONGTEXT NULL,
  status ENUM('parsed', 'applied', 'failed') NOT NULL DEFAULT 'parsed',
  created_by_user_id INT NULL,
  created_by_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  applied_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_mark_scan_imports_context (class_list_id, subject_id, term_label),
  INDEX idx_mark_scan_imports_status (status),
  INDEX idx_mark_scan_imports_created_at (created_at),
  FOREIGN KEY (class_list_id) REFERENCES class_lists(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grading_systems (
  id INT AUTO_INCREMENT PRIMARY KEY,
  system_name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_grading_systems_active (is_active),
  INDEX idx_grading_systems_name (system_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grading_scales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grading_system_id INT NOT NULL,
  grade_label VARCHAR(10) NOT NULL,
  grade_name VARCHAR(50) NOT NULL,
  mark_from DECIMAL(5,2) NOT NULL,
  mark_to DECIMAL(5,2) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_system_grade_label (grading_system_id, grade_label),
  INDEX idx_grading_scales_system (grading_system_id),
  INDEX idx_grading_scales_range (mark_from, mark_to),
  FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_remark_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grading_system_id INT NOT NULL,
  grade_label VARCHAR(10) NOT NULL,
  remark_template TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_remark_templates_system (grading_system_id),
  INDEX idx_remark_templates_grade (grade_label),
  INDEX idx_remark_templates_active (is_active),
  FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_remarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT NOT NULL,
  subject_id INT NOT NULL,
  class_name VARCHAR(50) NOT NULL,
  term_label VARCHAR(150) NOT NULL,
  grading_system_id INT NOT NULL,
  grade_label VARCHAR(10) NOT NULL,
  remark_text TEXT NOT NULL,
  entered_by_user_id INT NULL,
  entered_by_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_student_subject_term_remark (student_user_id, subject_id, term_label),
  INDEX idx_student_remarks_class_term (class_name, term_label),
  INDEX idx_student_remarks_student (student_user_id),
  INDEX idx_student_remarks_grade (grade_label),
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE RESTRICT,
  FOREIGN KEY (entered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_status_remarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  remark_label VARCHAR(50) NOT NULL UNIQUE,
  remark_description TEXT NOT NULL,
  remark_category ENUM('promotion', 'academic_status', 'transfer') NOT NULL DEFAULT 'promotion',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_promotion_status_active (is_active),
  INDEX idx_promotion_status_category (remark_category),
  INDEX idx_promotion_status_label (remark_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_promotion_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT NOT NULL,
  class_name VARCHAR(50) NOT NULL,
  term_label VARCHAR(150) NOT NULL,
  status_remark_id INT NOT NULL,
  promotion_note TEXT NULL,
  recorded_by_user_id INT NULL,
  recorded_by_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_student_class_term_promotion (student_user_id, class_name, term_label),
  INDEX idx_promotion_student (student_user_id),
  INDEX idx_promotion_class_term (class_name, term_label),
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (status_remark_id) REFERENCES promotion_status_remarks(id) ON DELETE RESTRICT,
  FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subjects (subject_code, subject_name, is_active)
VALUES
  ('ENG', 'English', 1),
  ('MAT', 'Mathematics', 1),
  ('SCI', 'Science', 1),
  ('HIS', 'History', 1),
  ('GEO', 'Geography', 1)
ON DUPLICATE KEY UPDATE
  subject_name = VALUES(subject_name),
  is_active = VALUES(is_active);

INSERT INTO exam_types (exam_name, is_active, created_by)
VALUES
  ('Beginning of Term', 1, 'System Bootstrap'),
  ('Mid Term', 1, 'System Bootstrap'),
  ('End of Term', 1, 'System Bootstrap')
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO academic_years (year_label, is_active, created_by)
SELECT CAST(YEAR(CURDATE()) AS CHAR), 1, 'System Bootstrap'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO grading_systems (system_name, description, is_active, created_by)
VALUES
  ('Uganda Lower Secondary (New Curriculum)', 'Standard grading system for Uganda lower secondary education based on the new curriculum', 1, 'System Bootstrap')
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
SELECT 
  id,
  'A',
  'Excellent',
  80,
  100,
  'Exceptional understanding and mastery of the subject matter',
  1
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  grade_name = VALUES(grade_name),
  description = VALUES(description);

INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
SELECT 
  id,
  'B',
  'Very Good',
  70,
  79,
  'Strong understanding with minor gaps in knowledge',
  2
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  grade_name = VALUES(grade_name),
  description = VALUES(description);

INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
SELECT 
  id,
  'C',
  'Good',
  60,
  69,
  'Satisfactory understanding of core concepts',
  3
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  grade_name = VALUES(grade_name),
  description = VALUES(description);

INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
SELECT 
  id,
  'D',
  'Satisfactory',
  50,
  59,
  'Adequate understanding with noticeable gaps',
  4
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  grade_name = VALUES(grade_name),
  description = VALUES(description);

INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
SELECT 
  id,
  'E',
  'Weak',
  40,
  49,
  'Limited understanding, significant support needed',
  5
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  grade_name = VALUES(grade_name),
  description = VALUES(description);

INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
SELECT 
  id,
  'F',
  'Poor',
  0,
  39,
  'Insufficient understanding, intensive intervention required',
  6
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  grade_name = VALUES(grade_name),
  description = VALUES(description);

INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
SELECT 
  id,
  'A',
  'Demonstrates exceptional mastery and understanding. Shows excellent problem-solving skills and engagement.',
  1,
  1,
  'System Bootstrap'
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
SELECT 
  id,
  'B',
  'Shows very good understanding and competence. Completes most tasks accurately with minor errors.',
  2,
  1,
  'System Bootstrap'
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
SELECT 
  id,
  'C',
  'Demonstrates satisfactory understanding of core concepts. Needs to improve consistency in application.',
  3,
  1,
  'System Bootstrap'
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
SELECT 
  id,
  'D',
  'Shows adequate understanding but with gaps. Requires additional practice and reinforcement.',
  4,
  1,
  'System Bootstrap'
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
SELECT 
  id,
  'E',
  'Limited understanding with significant gaps. Requires intensive intervention and support.',
  5,
  1,
  'System Bootstrap'
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
SELECT 
  id,
  'F',
  'Insufficient understanding of the subject matter. Requires comprehensive support and remedial intervention.',
  6,
  1,
  'System Bootstrap'
FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)'
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO promotion_status_remarks (remark_label, remark_description, remark_category, sort_order, is_active, created_by)
VALUES
  ('Promoted', 'Student promoted to the next class', 'promotion', 1, 1, 'System Bootstrap'),
  ('Repeat', 'Student repeating the current class', 'promotion', 2, 1, 'System Bootstrap'),
  ('Change Station', 'Student transferred to a different stream or school', 'transfer', 3, 1, 'System Bootstrap'),
  ('Passed', 'Student passed with good performance', 'academic_status', 4, 1, 'System Bootstrap'),
  ('Conditional Promotion', 'Student promoted with conditions', 'promotion', 5, 1, 'System Bootstrap'),
  ('Academic Probation', 'Student placed on academic probation', 'academic_status', 6, 1, 'System Bootstrap')
ON DUPLICATE KEY UPDATE
  remark_description = VALUES(remark_description),
  is_active = VALUES(is_active);

INSERT INTO users (account_number, full_name, role, pin_code, is_active, class_name)
VALUES
  ('STU-1001', 'Noah Student', 'student', '1234', 1, 'Senior 1A'),
  ('STF-2001', 'Alice Teacher', 'staff', '1234', 1, 'Unassigned'),
  ('ADM-3001', 'Grace Admin', 'admin', '1234', 1, 'Unassigned')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  role = VALUES(role),
  pin_code = VALUES(pin_code),
  is_active = VALUES(is_active),
  class_name = COALESCE(VALUES(class_name), class_name);

INSERT INTO class_lists (class_name, class_stream, display_name, created_by)
VALUES
  ('Senior 1A', '', 'Senior 1A', 'System Bootstrap')
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO class_list_students (class_list_id, student_user_id, enrollment_status, assigned_at, removed_at)
SELECT class_lists.id, users.id, 'active', CURRENT_TIMESTAMP, NULL
FROM class_lists
INNER JOIN users
  ON users.account_number = 'STU-1001'
 AND users.role = 'student'
WHERE class_lists.display_name = 'Senior 1A'
ON DUPLICATE KEY UPDATE
  enrollment_status = VALUES(enrollment_status),
  assigned_at = CURRENT_TIMESTAMP,
  removed_at = NULL;

INSERT INTO staff_teaching_allocations (staff_user_id, class_list_id, subject_id, assigned_by_user_id, assigned_by_name)
SELECT users.id, class_lists.id, subjects.id, NULL, 'System Bootstrap'
FROM users
INNER JOIN class_lists
  ON class_lists.display_name = 'Senior 1A'
INNER JOIN subjects
WHERE users.account_number = 'STF-2001'
  AND users.role = 'staff'
ON DUPLICATE KEY UPDATE
  assigned_by_name = VALUES(assigned_by_name);

INSERT INTO announcements (title_text, body_text, audience, class_name, category, created_by)
SELECT
  'Administration Portal Ready',
  'The school administration portal is active. Students, staff, and administrators can now access their assigned workspaces securely.',
  'all',
  NULL,
  'general',
  'System Bootstrap'
WHERE NOT EXISTS (
  SELECT 1 FROM announcements
);
