<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function administration_should_seed_demo_data(): bool
{
    return administration_env_bool('APP_SEED_DEMO_DATA', false);
}

function quotePostgresIdentifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function ensureTableColumn(PDO $pdo, string $dbName, string $tableName, string $columnName, string $columnDefinition): void
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_catalog = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name"
    );
    $statement->execute([
        'schema_name' => $dbName,
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE ' . quotePostgresIdentifier($tableName) . ' ADD COLUMN IF NOT EXISTS ' . $columnDefinition);
    }
}

function ensureMinimumVarcharLength(PDO $pdo, string $dbName, string $tableName, string $columnName, int $minimumLength, string $columnDefinition): void
{
    $statement = $pdo->prepare(
        'SELECT CHARACTER_MAXIMUM_LENGTH
         FROM information_schema.COLUMNS
         WHERE table_catalog = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name
         LIMIT 1'
    );
    $statement->execute([
        'schema_name' => $dbName,
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    $currentLength = $statement->fetchColumn();
    if ($currentLength !== false && (int) $currentLength < $minimumLength) {
        $pdo->exec('ALTER TABLE ' . quotePostgresIdentifier($tableName) . ' ALTER COLUMN ' . quotePostgresIdentifier($columnName) . ' TYPE VARCHAR(' . $minimumLength . ')');
    }
}

function synchronizePhpTimezoneWithDatabase(PDO $pdo): void
{
    static $configured = false;

    if ($configured) {
        return;
    }

    $configured = true;

    $appTimezone = administration_env('APP_TIMEZONE', null);
    if (is_string($appTimezone) && $appTimezone !== '' && in_array($appTimezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($appTimezone);
        return;
    }

    try {
        $timezone = $pdo->query('SELECT current_setting(\'timezone\') AS system_time_zone')->fetchColumn();
        if (is_string($timezone) && $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        }
    } catch (Throwable $throwable) {
        // Keep the current PHP timezone if the database timezone cannot be read.
    }
}

function logStudentIdActivity(PDO $pdo, array $activity): void
{
    $statement = $pdo->prepare(
        'INSERT INTO student_id_activity (user_id, account_number, student_name, pin_code, action_type, reason_text, batch_reference, performed_by)
         VALUES (:user_id, :account_number, :student_name, :pin_code, :action_type, :reason_text, :batch_reference, :performed_by)'
    );
    $statement->execute([
        'user_id' => $activity['user_id'] ?? null,
        'account_number' => $activity['account_number'],
        'student_name' => $activity['student_name'],
        'pin_code' => $activity['pin_code'] ?? null,
        'action_type' => $activity['action_type'],
        'reason_text' => $activity['reason_text'] ?? null,
        'batch_reference' => $activity['batch_reference'] ?? null,
        'performed_by' => $activity['performed_by'],
    ]);
}

function logStaffActivity(PDO $pdo, array $activity): void
{
    $statement = $pdo->prepare(
        'INSERT INTO staff_activity_logs (user_id, staff_name, activity_type, target_reference, details_text)
         VALUES (:user_id, :staff_name, :activity_type, :target_reference, :details_text)'
    );
    $statement->execute([
        'user_id' => $activity['user_id'] ?? null,
        'staff_name' => $activity['staff_name'],
        'activity_type' => $activity['activity_type'],
        'target_reference' => $activity['target_reference'] ?? null,
        'details_text' => $activity['details_text'],
    ]);
}

function createStudentAlert(PDO $pdo, int $studentId, string $title, string $message, string $alertType = 'info', ?string $subjectName = null): void
{
    $statement = $pdo->prepare(
        'INSERT INTO student_alerts (student_user_id, title_text, message_text, alert_type, subject_name)
         VALUES (:student_user_id, :title_text, :message_text, :alert_type, :subject_name)'
    );
    $statement->execute([
        'student_user_id' => $studentId,
        'title_text' => $title,
        'message_text' => $message,
        'alert_type' => $alertType,
        'subject_name' => $subjectName,
    ]);
}

function createAnnouncement(PDO $pdo, string $title, string $body, string $audience, string $createdBy, ?string $className = null, string $category = 'general'): void
{
    $statement = $pdo->prepare(
        'INSERT INTO announcements (title_text, body_text, audience, class_name, category, created_by)
         VALUES (:title_text, :body_text, :audience, :class_name, :category, :created_by)'
    );
    $statement->execute([
        'title_text' => $title,
        'body_text' => $body,
        'audience' => $audience,
        'class_name' => $className,
        'category' => $category,
        'created_by' => $createdBy,
    ]);
}

function initializeAdministrationDatabase(PDO $pdo, string $dbName): void
{
    $databaseIdentifier = quotePostgresIdentifier($dbName);

    // PostgreSQL does not support CREATE DATABASE IF NOT EXISTS.
    // The database is expected to be created on Render already.
    // We simply set the schema search path.

    $pdo->exec("CREATE SCHEMA IF NOT EXISTS public");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            account_number VARCHAR(50) NOT NULL UNIQUE,
            full_name VARCHAR(120) NOT NULL,
            role VARCHAR(20) NOT NULL CHECK (role IN ('student', 'staff', 'admin')),
            pin_code VARCHAR(20) NOT NULL,
            is_active SMALLINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    ensureTableColumn(
        $pdo,
        $dbName,
        'users',
        'updated_at',
        'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    );
    ensureTableColumn(
        $pdo,
        $dbName,
        'users',
        'deactivated_at',
        'deactivated_at TIMESTAMP NULL DEFAULT NULL'
    );
    ensureTableColumn(
        $pdo,
        $dbName,
        'users',
        'deactivation_reason',
        'deactivation_reason VARCHAR(255) NULL DEFAULT NULL'
    );
    ensureTableColumn(
        $pdo,
        $dbName,
        'users',
        'class_name',
        "class_name VARCHAR(50) NOT NULL DEFAULT 'Unassigned'"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_id_activity (
            id SERIAL PRIMARY KEY,
            user_id INT NULL,
            account_number VARCHAR(50) NOT NULL,
            student_name VARCHAR(120) NOT NULL,
            pin_code VARCHAR(20) NULL,
            action_type VARCHAR(20) NOT NULL CHECK (action_type IN ('generated', 'deactivated')),
            reason_text VARCHAR(255) NULL,
            batch_reference VARCHAR(40) NULL,
            performed_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activity_account_number ON student_id_activity (account_number)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activity_action_type ON student_id_activity (action_type)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activity_created_at ON student_id_activity (created_at)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS class_lists (
            id SERIAL PRIMARY KEY,
            class_name VARCHAR(50) NOT NULL,
            class_stream VARCHAR(50) NOT NULL DEFAULT '',
            display_name VARCHAR(120) NOT NULL,
            is_active SMALLINT NOT NULL DEFAULT 1,
            promoted_to_class_list_id INT NULL,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT unique_class_list_name_stream UNIQUE (class_name, class_stream),
            CONSTRAINT unique_class_list_display_name UNIQUE (display_name),
            FOREIGN KEY (promoted_to_class_list_id) REFERENCES class_lists(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_class_lists_active ON class_lists (is_active)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_class_lists_display_name ON class_lists (display_name)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS class_list_students (
            id SERIAL PRIMARY KEY,
            class_list_id INT NOT NULL,
            student_user_id INT NOT NULL,
            enrollment_status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (enrollment_status IN ('active', 'transferred', 'removed')),
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            removed_at TIMESTAMP NULL DEFAULT NULL,
            CONSTRAINT unique_class_list_student UNIQUE (class_list_id, student_user_id),
            FOREIGN KEY (class_list_id) REFERENCES class_lists(id) ON DELETE CASCADE,
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_class_list_students_class ON class_list_students (class_list_id, enrollment_status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_class_list_students_student ON class_list_students (student_user_id, enrollment_status)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS subjects (
            id SERIAL PRIMARY KEY,
            subject_code VARCHAR(20) NOT NULL UNIQUE,
            subject_name VARCHAR(100) NOT NULL UNIQUE,
            is_active SMALLINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_teaching_allocations (
            id SERIAL PRIMARY KEY,
            staff_user_id INT NOT NULL,
            class_list_id INT NOT NULL,
            subject_id INT NOT NULL,
            assigned_by_user_id INT NULL,
            assigned_by_name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT unique_staff_class_subject UNIQUE (staff_user_id, class_list_id, subject_id),
            FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_list_id) REFERENCES class_lists(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_staff_teaching_staff ON staff_teaching_allocations (staff_user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_staff_teaching_class ON staff_teaching_allocations (class_list_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_staff_teaching_subject ON staff_teaching_allocations (subject_id)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS exam_types (
            id SERIAL PRIMARY KEY,
            exam_name VARCHAR(100) NOT NULL UNIQUE,
            is_active SMALLINT NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_exam_types_active ON exam_types (is_active)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_exam_types_name ON exam_types (exam_name)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS academic_years (
            id SERIAL PRIMARY KEY,
            year_label VARCHAR(30) NOT NULL UNIQUE,
            is_active SMALLINT NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_academic_years_active ON academic_years (is_active)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_academic_years_label ON academic_years (year_label)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mark_entries (
            id SERIAL PRIMARY KEY,
            student_user_id INT NOT NULL,
            subject_id INT NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            term_label VARCHAR(150) NOT NULL,
            mark_value DECIMAL(5,2) NOT NULL,
            entered_by_user_id INT NULL,
            entered_by_name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT unique_student_subject_term UNIQUE (student_user_id, subject_id, term_label),
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (entered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_marks_class_term ON mark_entries (class_name, term_label)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_marks_student ON mark_entries (student_user_id)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS announcements (
            id SERIAL PRIMARY KEY,
            title_text VARCHAR(180) NOT NULL,
            body_text TEXT NOT NULL,
            audience VARCHAR(20) NOT NULL DEFAULT 'all' CHECK (audience IN ('all', 'student', 'staff', 'admin')),
            class_name VARCHAR(50) NULL,
            category VARCHAR(20) NOT NULL DEFAULT 'general' CHECK (category IN ('general', 'reports', 'marks')),
            created_by VARCHAR(120) NOT NULL,
            is_active SMALLINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_announcements_audience ON announcements (audience)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_announcements_class ON announcements (class_name)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_announcements_created_at ON announcements (created_at)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_alerts (
            id SERIAL PRIMARY KEY,
            student_user_id INT NOT NULL,
            title_text VARCHAR(180) NOT NULL,
            message_text TEXT NOT NULL,
            alert_type VARCHAR(20) NOT NULL DEFAULT 'info' CHECK (alert_type IN ('info', 'success', 'warning')),
            subject_name VARCHAR(100) NULL,
            is_read SMALLINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_student_alerts_student ON student_alerts (student_user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_student_alerts_created_at ON student_alerts (created_at)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS report_publications (
            id SERIAL PRIMARY KEY,
            class_name VARCHAR(50) NOT NULL,
            term_label VARCHAR(150) NOT NULL,
            publish_at TIMESTAMP NOT NULL,
            published_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT unique_class_term_report UNIQUE (class_name, term_label)
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_publish_at ON report_publications (publish_at)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_activity_logs (
            id SERIAL PRIMARY KEY,
            user_id INT NULL,
            staff_name VARCHAR(120) NOT NULL,
            activity_type VARCHAR(80) NOT NULL,
            target_reference VARCHAR(120) NULL,
            details_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_staff_activity_created_at ON staff_activity_logs (created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_staff_activity_user_id ON staff_activity_logs (user_id)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mark_scan_imports (
            id SERIAL PRIMARY KEY,
            class_list_id INT NOT NULL,
            subject_id INT NOT NULL,
            term_label VARCHAR(150) NOT NULL,
            provider_name VARCHAR(80) NULL,
            source_filename VARCHAR(255) NULL,
            stored_file_path VARCHAR(255) NULL,
            raw_text TEXT NOT NULL,
            parsed_matches_json TEXT NOT NULL,
            unmatched_lines_json TEXT NULL,
            warnings_json TEXT NULL,
            applied_marks_json TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'parsed' CHECK (status IN ('parsed', 'applied', 'failed')),
            created_by_user_id INT NULL,
            created_by_name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            applied_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (class_list_id) REFERENCES class_lists(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mark_scan_imports_context ON mark_scan_imports (class_list_id, subject_id, term_label)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mark_scan_imports_status ON mark_scan_imports (status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mark_scan_imports_created_at ON mark_scan_imports (created_at)');

    // Grading System Tables
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS grading_systems (
            id SERIAL PRIMARY KEY,
            system_name VARCHAR(120) NOT NULL UNIQUE,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_grading_systems_active (is_active),
            INDEX idx_grading_systems_name (system_name)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS grading_scales (
            id SERIAL PRIMARY KEY,
            grading_system_id INT NOT NULL,
            grade_label VARCHAR(20) NOT NULL,
            grade_name VARCHAR(100) NOT NULL,
            mark_from INT NOT NULL,
            mark_to INT NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_system_grade (grading_system_id, grade_label),
            FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE CASCADE,
            INDEX idx_grading_scales_system (grading_system_id),
            INDEX idx_grading_scales_mark_range (mark_from, mark_to)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS teacher_remark_templates (
            id SERIAL PRIMARY KEY,
            grading_system_id INT NOT NULL,
            grade_label VARCHAR(20) NOT NULL,
            remark_template TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_system_grade_template (grading_system_id, grade_label),
            FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE CASCADE,
            INDEX idx_templates_system (grading_system_id),
            INDEX idx_templates_active (is_active)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_remarks (
            id SERIAL PRIMARY KEY,
            student_user_id INT NOT NULL,
            subject_id INT NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            term_label VARCHAR(150) NOT NULL,
            grading_system_id INT NOT NULL,
            grade_label VARCHAR(20) NOT NULL,
            remark_text TEXT NOT NULL,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_subject_term (student_user_id, subject_id, class_name, term_label),
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE CASCADE,
            INDEX idx_remarks_student (student_user_id),
            INDEX idx_remarks_subject_term (subject_id, term_label),
            INDEX idx_remarks_class_term (class_name, term_label)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promotion_status_remarks (
            id SERIAL PRIMARY KEY,
            remark_label VARCHAR(100) NOT NULL UNIQUE,
            remark_description TEXT NULL,
            remark_category ENUM('promotion', 'academic_status', 'transfer') NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_promotion_remarks_active (is_active),
            INDEX idx_promotion_remarks_category (remark_category)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_promotion_records (
            id SERIAL PRIMARY KEY,
            student_user_id INT NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            term_label VARCHAR(150) NOT NULL,
            status_remark_id INT NOT NULL,
            promotion_note TEXT NULL,
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_class_term (student_user_id, class_name, term_label),
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (status_remark_id) REFERENCES promotion_status_remarks(id) ON DELETE CASCADE,
            INDEX idx_promotion_records_student (student_user_id),
            INDEX idx_promotion_records_class_term (class_name, term_label)
        )"
    );

    ensureMinimumVarcharLength($pdo, $dbName, 'mark_entries', 'term_label', 150, 'term_label VARCHAR(150)');
    ensureMinimumVarcharLength($pdo, $dbName, 'report_publications', 'term_label', 150, 'term_label VARCHAR(150)');
    ensureMinimumVarcharLength($pdo, $dbName, 'mark_scan_imports', 'term_label', 150, 'term_label VARCHAR(150)');

    // INSERT subjects with conflict handling
    $pdo->exec(
        "INSERT INTO subjects (subject_code, subject_name, is_active)
        VALUES
            ('ENG', 'English', 1),
            ('MAT', 'Mathematics', 1),
            ('SCI', 'Science', 1),
            ('HIS', 'History', 1),
            ('GEO', 'Geography', 1)
        ON CONFLICT (subject_code) DO UPDATE SET
            subject_name = EXCLUDED.subject_name,
            is_active = EXCLUDED.is_active"
    );

    $pdo->exec(
        "INSERT INTO exam_types (exam_name, is_active, created_by)
        VALUES
            ('Beginning of Term', 1, 'System Bootstrap'),
            ('Mid Term', 1, 'System Bootstrap'),
            ('End of Term', 1, 'System Bootstrap')
        ON CONFLICT (exam_name) DO UPDATE SET
            is_active = EXCLUDED.is_active"
    );

    $academicYearStatement = $pdo->prepare(
        'INSERT INTO academic_years (year_label, is_active, created_by)
         VALUES (:year_label, 1, :created_by)
         ON CONFLICT (year_label) DO UPDATE SET is_active = EXCLUDED.is_active'
    );
    $academicYearStatement->execute([
        'year_label' => (string) date('Y'),
        'created_by' => 'System Bootstrap',
    ]);

    synchronizeClassListsFromUsers($pdo, 'System Bootstrap');

    // Seed grading systems
    $pdo->exec(
        "INSERT INTO grading_systems (system_name, description, is_active, created_by)
        VALUES
            ('Uganda Lower Secondary (New Curriculum)', 'Uganda''s new curriculum grading system for lower secondary', 1, 'System Bootstrap')
        ON CONFLICT (system_name) DO UPDATE SET
            description = EXCLUDED.description,
            is_active = EXCLUDED.is_active"
    );

    // Get the grading system ID
    $gradingSystemStmt = $pdo->query("SELECT id FROM grading_systems WHERE system_name = 'Uganda Lower Secondary (New Curriculum)' LIMIT 1");
    $gradingSystemId = (int) $gradingSystemStmt->fetchColumn();

    if ($gradingSystemId > 0) {
        // Seed grading scales (grades A-F with mark ranges)
        $pdo->exec(
            "INSERT INTO grading_scales (grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order)
            VALUES
                ($gradingSystemId, 'A', 'Excellent', 80, 100, 'Exceptional understanding and mastery', 1),
                ($gradingSystemId, 'B', 'Very Good', 70, 79, 'Strong understanding with minor gaps', 2),
                ($gradingSystemId, 'C', 'Good', 60, 69, 'Satisfactory understanding of core concepts', 3),
                ($gradingSystemId, 'D', 'Satisfactory', 50, 59, 'Adequate understanding with noticeable gaps', 4),
                ($gradingSystemId, 'E', 'Weak', 40, 49, 'Limited understanding, significant support needed', 5),
                ($gradingSystemId, 'F', 'Poor', 0, 39, 'Insufficient understanding, intervention required', 6)
            ON CONFLICT (grading_system_id, grade_label) DO UPDATE SET
                grade_name = EXCLUDED.grade_name,
                mark_from = EXCLUDED.mark_from,
                mark_to = EXCLUDED.mark_to,
                description = EXCLUDED.description"
        );

        // Seed teacher remark templates
        $pdo->exec(
            "INSERT INTO teacher_remark_templates (grading_system_id, grade_label, remark_template, sort_order, is_active, created_by)
            VALUES
                ($gradingSystemId, 'A', 'Demonstrates exceptional mastery and understanding. Shows excellent problem-solving skills and engagement.', 1, 1, 'System Bootstrap'),
                ($gradingSystemId, 'B', 'Shows very good understanding of the subject matter. Responds well to instruction with minor gaps.', 2, 1, 'System Bootstrap'),
                ($gradingSystemId, 'C', 'Demonstrates good understanding of core concepts. May need some reinforcement in certain areas.', 3, 1, 'System Bootstrap'),
                ($gradingSystemId, 'D', 'Shows adequate understanding but with gaps. Requires additional practice and reinforcement.', 4, 1, 'System Bootstrap'),
                ($gradingSystemId, 'E', 'Limited understanding of the subject. Needs significant support and focused intervention.', 5, 1, 'System Bootstrap'),
                ($gradingSystemId, 'F', 'Insufficient understanding of the subject matter. Requires comprehensive support and remedial intervention.', 6, 1, 'System Bootstrap')
            ON CONFLICT (grading_system_id, grade_label) DO UPDATE SET
                remark_template = EXCLUDED.remark_template,
                is_active = EXCLUDED.is_active"
        );
    }

    // Seed promotion status remarks
    $pdo->exec(
        "INSERT INTO promotion_status_remarks (remark_label, remark_description, remark_category, is_active, created_by)
        VALUES
            ('Promoted', 'Student has met all requirements and is promoted to the next level', 'promotion', 1, 'System Bootstrap'),
            ('Repeat', 'Student did not meet promotion requirements and will repeat the current class', 'promotion', 1, 'System Bootstrap'),
            ('Change Station', 'Student is transferred to another school or stream', 'transfer', 1, 'System Bootstrap'),
            ('Passed', 'Student has achieved passing requirements', 'academic_status', 1, 'System Bootstrap'),
            ('Conditional Promotion', 'Student is promoted conditionally pending improvement in specific areas', 'academic_status', 1, 'System Bootstrap'),
            ('Academic Probation', 'Student is on academic probation and must improve performance', 'academic_status', 1, 'System Bootstrap')
        ON CONFLICT (remark_label) DO UPDATE SET
            remark_description = EXCLUDED.remark_description,
            remark_category = EXCLUDED.remark_category,
            is_active = EXCLUDED.is_active"
    );

    synchronizeClassListsFromUsers($pdo, 'System Bootstrap');

    if (administration_should_seed_demo_data()) {
        $pdo->exec(
            "INSERT INTO users (account_number, full_name, role, pin_code, is_active, class_name)
            VALUES
                ('STU-1001', 'Noah Student', 'student', '1234', 1, 'Senior 1A'),
                ('STF-2001', 'Alice Teacher', 'staff', '1234', 1, 'Unassigned'),
                ('ADM-3001', 'Grace Admin', 'admin', '1234', 1, 'Unassigned')
            ON CONFLICT (account_number) DO UPDATE SET
                full_name = EXCLUDED.full_name,
                role = EXCLUDED.role,
                pin_code = EXCLUDED.pin_code,
                is_active = EXCLUDED.is_active,
                class_name = COALESCE(EXCLUDED.class_name, users.class_name)"
        );

        bootstrapDefaultStaffTeachingAllocations($pdo);
    }

    migrateLegacyAcademicContextLabels($pdo);

    $announcementCount = (int) $pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    if ($announcementCount === 0) {
        createAnnouncement(
            $pdo,
            'Administration Portal Ready',
            'The school administration portal is active. Students, staff, and administrators can now access their assigned workspaces securely.',
            'all',
            'System Bootstrap',
            null,
            'general'
        );
    }
}

function getNextStudentSequence(PDO $pdo): int
{
    $statement = $pdo->query(
        "SELECT MAX(CAST(SUBSTRING(account_number FROM 5) AS INTEGER)) FROM users WHERE role = 'student' AND account_number ~ '^STU-[0-9]+$'"
    );

    return (int) ($statement->fetchColumn() ?: 1000);
}

function getNextStaffSequence(PDO $pdo): int
{
    $statement = $pdo->query(
        "SELECT MAX(CAST(SUBSTRING(account_number FROM 5) AS INTEGER)) FROM users WHERE role = 'staff' AND account_number ~ '^STF-[0-9]+$'"
    );

    return (int) ($statement->fetchColumn() ?: 2000);
}

function formatStudentAccountNumber(int $sequence): string
{
    return sprintf('STU-%04d', $sequence);
}

function formatStaffAccountNumber(int $sequence): string
{
    return sprintf('STF-%04d', $sequence);
}

function generateUniqueUserPin(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $candidate = (string) random_int(1000, 9999);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE pin_code = :pin_code');
        $statement->execute(['pin_code' => $candidate]);

        if ((int) $statement->fetchColumn() === 0) {
            return $candidate;
        }
    }

    return (string) random_int(100000, 999999);
}

function generateUniqueStudentPin(PDO $pdo): string
{
    return generateUniqueUserPin($pdo);
}

function generateUniqueStaffPin(PDO $pdo): string
{
    return generateUniqueUserPin($pdo);
}

function getAcademicTermOptions(): array
{
    return ['Term 1', 'Term 2', 'Term 3'];
}

function normalizeAcademicTerm(?string $termName): string
{
    $termName = preg_replace('/\s+/', ' ', trim((string) $termName));
    foreach (getAcademicTermOptions() as $availableTerm) {
        if (strcasecmp($availableTerm, $termName) === 0) {
            return $availableTerm;
        }
    }

    return getAcademicTermOptions()[0];
}

function normalizeAcademicYearLabel(?string $academicYearLabel): string
{
    $academicYearLabel = preg_replace('/\s+/', ' ', trim((string) $academicYearLabel));

    return $academicYearLabel !== '' ? $academicYearLabel : (string) date('Y');
}

function buildAcademicContextLabelFromNames(string $examTypeName, string $termName, string $academicYearLabel): string
{
    $examTypeName = preg_replace('/\s+/', ' ', trim($examTypeName));
    $examTypeName = $examTypeName !== '' ? $examTypeName : 'End of Term';

    return $examTypeName . ' / ' . normalizeAcademicTerm($termName) . ' / ' . normalizeAcademicYearLabel($academicYearLabel);
}

function canonicalizeStoredAcademicContextLabel(string $termLabel): string
{
    $termLabel = preg_replace('/\s+/', ' ', trim($termLabel));
    if ($termLabel === '') {
        return getDefaultTermLabel();
    }

    if (substr_count($termLabel, '/') >= 2) {
        return $termLabel;
    }

    if (preg_match('/^Term\s*([123])\s+([0-9]{4}(?:\/[0-9]{4})?)$/i', $termLabel, $matches) === 1) {
        return buildAcademicContextLabelFromNames('End of Term', 'Term ' . (int) $matches[1], $matches[2]);
    }

    return $termLabel;
}

function getDefaultAcademicContext(PDO $pdo): array
{
    $examTypes = getExamTypes($pdo);
    $selectedExamType = null;
    foreach ($examTypes as $examType) {
        if (strcasecmp((string) $examType['exam_name'], 'End of Term') === 0) {
            $selectedExamType = $examType;
            break;
        }
    }
    if ($selectedExamType === null) {
        $selectedExamType = $examTypes[0] ?? null;
    }

    $academicYears = getAcademicYears($pdo);
    $selectedAcademicYear = null;
    foreach ($academicYears as $academicYear) {
        if ((string) $academicYear['year_label'] === (string) date('Y')) {
            $selectedAcademicYear = $academicYear;
            break;
        }
    }
    if ($selectedAcademicYear === null) {
        $selectedAcademicYear = $academicYears[0] ?? null;
    }

    if ($selectedExamType === null || $selectedAcademicYear === null) {
        return [
            'exam_type' => null,
            'academic_year' => null,
            'term_name' => normalizeAcademicTerm(null),
            'term_label' => getDefaultTermLabel(),
        ];
    }

    return buildAcademicContext($pdo, (int) $selectedExamType['id'], 'Term 1', (int) $selectedAcademicYear['id']);
}

function getDefaultTermLabel(): string
{
    return buildAcademicContextLabelFromNames('End of Term', 'Term 1', (string) date('Y'));
}

function normalizeClassName(?string $className): string
{
    $className = preg_replace('/\s+/', ' ', trim((string) $className));

    return $className !== '' ? $className : 'Unassigned';
}

function normalizeTermLabel(?string $termLabel): string
{
    $termLabel = preg_replace('/\s+/', ' ', trim((string) $termLabel));

    return $termLabel !== '' ? canonicalizeStoredAcademicContextLabel($termLabel) : getDefaultTermLabel();
}

function normalizeClassStream(?string $classStream): string
{
    return preg_replace('/\s+/', ' ', trim((string) $classStream));
}

function formatClassListDisplayName(string $className, ?string $classStream = null): string
{
    $className = normalizeClassName($className);
    $classStream = normalizeClassStream($classStream);

    return $classStream !== '' ? $className . ' ' . $classStream : $className;
}

function suggestPromotedClassName(string $className): string
{
    $className = normalizeClassName($className);

    if (preg_match('/^(.*?)(\d+)([^\d]*)$/', $className, $matches) === 1) {
        return trim($matches[1] . ((int) $matches[2] + 1) . $matches[3]);
    }

    return $className;
}

function normalizeStudentNames(array $studentNames): array
{
    $normalizedNames = [];

    foreach ($studentNames as $studentName) {
        $cleanName = preg_replace('/\s+/', ' ', trim((string) $studentName));
        if ($cleanName !== '') {
            $normalizedNames[] = $cleanName;
        }
    }

    return array_values(array_unique($normalizedNames));
}

function createStudentAccounts(PDO $pdo, array $studentNames, string $performedBy, ?string $className = null): array
{
    $normalizedNames = normalizeStudentNames($studentNames);
    if ($normalizedNames === []) {
        return [];
    }

    $createdAccounts = [];
    $batchReference = 'BATCH-' . date('YmdHis');
    $className = normalizeClassName($className);

    if ($className === 'Unassigned') {
        throw new RuntimeException('Assign every new student to a class before generating IDs.');
    }

    $pdo->beginTransaction();

    try {
        $nextSequence = getNextStudentSequence($pdo) + 1;
        $insertStatement = $pdo->prepare(
              "INSERT INTO users (account_number, full_name, role, pin_code, is_active, deactivated_at, deactivation_reason, class_name)
               VALUES (:account_number, :full_name, 'student', :pin_code, 1, NULL, NULL, :class_name)"
        );

        foreach ($normalizedNames as $studentName) {
            $accountNumber = formatStudentAccountNumber($nextSequence);
            $pinCode = generateUniqueStudentPin($pdo);

            $insertStatement->execute([
                'account_number' => $accountNumber,
                'full_name' => $studentName,
                'pin_code' => $pinCode,
                'class_name' => $className,
            ]);

            $userId = (int) $pdo->lastInsertId();
            if ($className !== 'Unassigned') {
                $classList = ensureClassListForDisplayName($pdo, $className, $performedBy);
                if ($classList !== null) {
                    syncStudentToClassList($pdo, $userId, $classList);
                }
            }

            logStudentIdActivity($pdo, [
                'user_id' => $userId,
                'account_number' => $accountNumber,
                'student_name' => $studentName,
                'pin_code' => $pinCode,
                'action_type' => 'generated',
                'reason_text' => 'Student ID created from admin batch input.',
                'batch_reference' => $batchReference,
                'performed_by' => $performedBy,
            ]);

            $createdAccounts[] = [
                'id' => $userId,
                'account_number' => $accountNumber,
                'full_name' => $studentName,
                'pin_code' => $pinCode,
                'batch_reference' => $batchReference,
                'class_name' => $className,
            ];
            $nextSequence++;
        }

        $pdo->commit();

        return $createdAccounts;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}

function deactivateStudentAccount(PDO $pdo, int $userId, string $performedBy, string $reason = ''): bool
{
    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, pin_code, is_active FROM users WHERE id = :id AND role = 'student' LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $student = $statement->fetch();

    if (!$student) {
        return false;
    }

    if ((int) ($student['is_active'] ?? 1) === 0) {
        return true;
    }

    $reasonText = trim($reason) !== '' ? trim($reason) : 'Student left the school.';

    $updateStatement = $pdo->prepare(
        'UPDATE users SET is_active = 0, deactivated_at = NOW(), deactivation_reason = :reason_text WHERE id = :id'
    );
    $updateStatement->execute([
        'reason_text' => $reasonText,
        'id' => $userId,
    ]);

    logStudentIdActivity($pdo, [
        'user_id' => (int) $student['id'],
        'account_number' => $student['account_number'],
        'student_name' => $student['full_name'],
        'pin_code' => $student['pin_code'],
        'action_type' => 'deactivated',
        'reason_text' => $reasonText,
        'batch_reference' => null,
        'performed_by' => $performedBy,
    ]);

    return true;
}

function getStudentAccounts(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT id, account_number, full_name, pin_code, class_name, is_active, created_at, updated_at, deactivated_at, deactivation_reason
         FROM users
         WHERE role = 'student'
         ORDER BY is_active DESC, account_number ASC"
    );

    return $statement->fetchAll();
}

function getStudentAccountById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, pin_code, class_name, is_active, created_at, updated_at, deactivated_at, deactivation_reason
         FROM users
         WHERE id = :id AND role = 'student'
         LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $student = $statement->fetch();

    return $student ?: null;
}

function createStaffAccount(PDO $pdo, string $staffName, string $performedBy, ?string $pinCode = null): array
{
    $staffName = preg_replace('/\s+/', ' ', trim($staffName));
    if ($staffName === '') {
        throw new RuntimeException('Enter the staff member name before creating the account.');
    }

    $pinCode = preg_replace('/\s+/', '', trim((string) $pinCode));
    if ($pinCode === '') {
        $pinCode = generateUniqueStaffPin($pdo);
    } else {
        $pinCheckStatement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE pin_code = :pin_code');
        $pinCheckStatement->execute(['pin_code' => $pinCode]);
        if ((int) $pinCheckStatement->fetchColumn() > 0) {
            throw new RuntimeException('Choose a different staff PIN because that PIN is already in use.');
        }
    }

    $accountNumber = formatStaffAccountNumber(getNextStaffSequence($pdo) + 1);
    $insertStatement = $pdo->prepare(
        "INSERT INTO users (account_number, full_name, role, pin_code, is_active, deactivated_at, deactivation_reason, class_name)
         VALUES (:account_number, :full_name, 'staff', :pin_code, 1, NULL, NULL, 'Unassigned')"
    );
    $insertStatement->execute([
        'account_number' => $accountNumber,
        'full_name' => $staffName,
        'pin_code' => $pinCode,
    ]);

    $staffId = (int) $pdo->lastInsertId();
    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $performedBy,
        'activity_type' => 'staff_account_created',
        'target_reference' => $accountNumber,
        'details_text' => 'Created staff account ' . $accountNumber . ' for ' . $staffName . '.',
    ]);

    $staffAccount = getStaffAccountById($pdo, $staffId);
    if ($staffAccount === null) {
        throw new RuntimeException('The new staff account could not be loaded after creation.');
    }

    return $staffAccount;
}

function getStaffAccounts(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            users.id,
            users.account_number,
            users.full_name,
            users.pin_code,
            users.class_name,
            users.is_active,
            users.created_at,
            users.updated_at,
            users.deactivated_at,
            users.deactivation_reason,
            COUNT(DISTINCT staff_teaching_allocations.class_list_id) AS assigned_class_count,
            COUNT(staff_teaching_allocations.id) AS assigned_subject_count
         FROM users
         LEFT JOIN staff_teaching_allocations
           ON staff_teaching_allocations.staff_user_id = users.id
         WHERE users.role = 'staff'
         GROUP BY
            users.id,
            users.account_number,
            users.full_name,
            users.pin_code,
            users.class_name,
            users.is_active,
            users.created_at,
            users.updated_at,
            users.deactivated_at,
            users.deactivation_reason
         ORDER BY users.is_active DESC, users.account_number ASC"
    );

    return $statement->fetchAll();
}

function getStaffAccountById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, pin_code, class_name, is_active, created_at, updated_at, deactivated_at, deactivation_reason
         FROM users
         WHERE id = :id AND role = 'staff'
         LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $staff = $statement->fetch();

    return $staff ?: null;
}

function getPrimaryAdministratorAccount(PDO $pdo): ?array
{
    $statement = $pdo->query(
        "SELECT id, account_number, full_name, pin_code, is_active, created_at
         FROM users
         WHERE role = 'admin'
         ORDER BY is_active DESC, id ASC
         LIMIT 1"
    );
    $administrator = $statement->fetch();

    return $administrator ?: null;
}

function getAdministratorAccountById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, pin_code, is_active, created_at
         FROM users
         WHERE id = :id AND role = 'admin'
         LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $administrator = $statement->fetch();

    return $administrator ?: null;
}

function updateAdministratorPin(PDO $pdo, int $userId, string $pinCode): array
{
    $pinCode = preg_replace('/\s+/', '', trim($pinCode));

    if ($userId <= 0) {
        throw new RuntimeException('The signed-in administrator account could not be identified.');
    }

    if ($pinCode === '') {
        throw new RuntimeException('Enter the administrator PIN.');
    }

    $administrator = getAdministratorAccountById($pdo, $userId);
    if ($administrator === null) {
        throw new RuntimeException('The signed-in administrator account could not be found.');
    }

    $statement = $pdo->prepare(
        "UPDATE users
         SET pin_code = :pin_code,
             is_active = 1,
             deactivated_at = NULL,
             deactivation_reason = NULL
         WHERE id = :id AND role = 'admin'"
    );
    $statement->execute([
        'id' => $userId,
        'pin_code' => $pinCode,
    ]);

    $updatedAdministrator = getAdministratorAccountById($pdo, $userId);
    if ($updatedAdministrator === null) {
        throw new RuntimeException('The administrator PIN could not be updated.');
    }

    return $updatedAdministrator;
}

function upsertPrimaryAdministratorAccount(PDO $pdo, string $accountNumber, string $fullName, string $pinCode): array
{
    $accountNumber = strtoupper(preg_replace('/\s+/', '', trim($accountNumber)));
    $fullName = preg_replace('/\s+/', ' ', trim($fullName));
    $pinCode = preg_replace('/\s+/', '', trim($pinCode));

    if ($accountNumber === '') {
        throw new RuntimeException('Enter an administrator account number.');
    }

    if ($fullName === '') {
        throw new RuntimeException('Enter the administrator full name.');
    }

    if ($pinCode === '') {
        throw new RuntimeException('Enter the administrator PIN.');
    }

    $existingAccountStatement = $pdo->prepare(
        'SELECT id, role FROM users WHERE account_number = :account_number LIMIT 1'
    );
    $existingAccountStatement->execute(['account_number' => $accountNumber]);
    $existingAccount = $existingAccountStatement->fetch();

    if (is_array($existingAccount) && (string) $existingAccount['role'] !== 'admin') {
        throw new RuntimeException('That account number is already assigned to a non-administrator account.');
    }

    if (is_array($existingAccount) && (string) $existingAccount['role'] === 'admin') {
        $updateStatement = $pdo->prepare(
            "UPDATE users
             SET full_name = :full_name,
                 pin_code = :pin_code,
                 is_active = 1,
                 deactivated_at = NULL,
                 deactivation_reason = NULL,
                 class_name = 'Unassigned'
             WHERE id = :id"
        );
        $updateStatement->execute([
            'id' => (int) $existingAccount['id'],
            'full_name' => $fullName,
            'pin_code' => $pinCode,
        ]);
    } else {
        $primaryAdministrator = getPrimaryAdministratorAccount($pdo);

        if ($primaryAdministrator !== null) {
            $updateStatement = $pdo->prepare(
                "UPDATE users
                 SET account_number = :account_number,
                     full_name = :full_name,
                     pin_code = :pin_code,
                     is_active = 1,
                     deactivated_at = NULL,
                     deactivation_reason = NULL,
                     class_name = 'Unassigned'
                 WHERE id = :id"
            );
            $updateStatement->execute([
                'id' => (int) $primaryAdministrator['id'],
                'account_number' => $accountNumber,
                'full_name' => $fullName,
                'pin_code' => $pinCode,
            ]);
        } else {
            $insertStatement = $pdo->prepare(
                "INSERT INTO users (account_number, full_name, role, pin_code, is_active, class_name)
                 VALUES (:account_number, :full_name, 'admin', :pin_code, 1, 'Unassigned')"
            );
            $insertStatement->execute([
                'account_number' => $accountNumber,
                'full_name' => $fullName,
                'pin_code' => $pinCode,
            ]);
        }
    }

    $administratorStatement = $pdo->prepare(
        'SELECT id, account_number, full_name, pin_code, is_active, created_at
         FROM users
         WHERE account_number = :account_number AND role = :role
         LIMIT 1'
    );
    $administratorStatement->execute([
        'account_number' => $accountNumber,
        'role' => 'admin',
    ]);
    $administrator = $administratorStatement->fetch();

    if (!is_array($administrator)) {
        throw new RuntimeException('The administrator account could not be saved.');
    }

    return $administrator;
}

function getStudentIdActivity(PDO $pdo, int $limit = 20): array
{
    $statement = $pdo->prepare(
        'SELECT account_number, student_name, pin_code, action_type, reason_text, batch_reference, performed_by, created_at
         FROM student_id_activity
         ORDER BY created_at DESC, id DESC
         LIMIT :limit_count'
    );
    $statement->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getStudentAlerts(PDO $pdo, int $studentId, int $limit = 8): array
{
    $statement = $pdo->prepare(
        'SELECT title_text, message_text, alert_type, subject_name, is_read, created_at
         FROM student_alerts
         WHERE student_user_id = :student_user_id
         ORDER BY created_at DESC, id DESC
         LIMIT :limit_count'
    );
    $statement->bindValue(':student_user_id', $studentId, PDO::PARAM_INT);
    $statement->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getExamTypes(PDO $pdo, bool $includeInactive = false): array
{
    $whereClause = $includeInactive ? '' : 'WHERE is_active = 1';
    $statement = $pdo->query(
        "SELECT id, exam_name, is_active, created_by, created_at
         FROM exam_types
         {$whereClause}
         ORDER BY created_at ASC, exam_name ASC"
    );

    return $statement->fetchAll();
}

function getExamTypeById(PDO $pdo, int $examTypeId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, exam_name, is_active, created_by, created_at
         FROM exam_types
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $examTypeId]);
    $examType = $statement->fetch();

    return $examType ?: null;
}

function getExamTypeByName(PDO $pdo, string $examName): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, exam_name, is_active, created_by, created_at
         FROM exam_types
         WHERE LOWER(exam_name) = LOWER(:exam_name)
         LIMIT 1'
    );
    $statement->execute(['exam_name' => preg_replace('/\s+/', ' ', trim($examName))]);
    $examType = $statement->fetch();

    return $examType ?: null;
}

function createExamType(PDO $pdo, string $examName, string $createdBy): array
{
    $examName = preg_replace('/\s+/', ' ', trim($examName));
    if ($examName === '') {
        throw new RuntimeException('Enter the exam type name before saving it.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO exam_types (exam_name, is_active, created_by)
         VALUES (:exam_name, 1, :created_by)
         ON CONFLICT (exam_name) DO UPDATE SET is_active = EXCLUDED.is_active'
    );
    $statement->execute([
        'exam_name' => $examName,
        'created_by' => $createdBy,
    ]);

    $lookupStatement = $pdo->prepare(
        'SELECT id, exam_name, is_active, created_by, created_at
         FROM exam_types
         WHERE exam_name = :exam_name
         LIMIT 1'
    );
    $lookupStatement->execute(['exam_name' => $examName]);
    $examType = $lookupStatement->fetch();
    if (!$examType) {
        throw new RuntimeException('The exam type could not be loaded after saving.');
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $createdBy,
        'activity_type' => 'exam_type_created',
        'target_reference' => $examName,
        'details_text' => 'Created or reactivated the exam type ' . $examName . '.',
    ]);

    return $examType;
}

function getAcademicYears(PDO $pdo, bool $includeInactive = false): array
{
    $whereClause = $includeInactive ? '' : 'WHERE is_active = 1';
    $statement = $pdo->query(
        "SELECT id, year_label, is_active, created_by, created_at
         FROM academic_years
         {$whereClause}
         ORDER BY year_label DESC, id DESC"
    );

    return $statement->fetchAll();
}

function getAcademicYearById(PDO $pdo, int $academicYearId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, year_label, is_active, created_by, created_at
         FROM academic_years
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $academicYearId]);
    $academicYear = $statement->fetch();

    return $academicYear ?: null;
}

function getAcademicYearByLabel(PDO $pdo, string $yearLabel): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, year_label, is_active, created_by, created_at
         FROM academic_years
         WHERE year_label = :year_label
         LIMIT 1'
    );
    $statement->execute(['year_label' => normalizeAcademicYearLabel($yearLabel)]);
    $academicYear = $statement->fetch();

    return $academicYear ?: null;
}

function createAcademicYear(PDO $pdo, string $yearLabel, string $createdBy): array
{
    $yearLabel = normalizeAcademicYearLabel($yearLabel);
    if ($yearLabel === '') {
        throw new RuntimeException('Enter the academic year label before saving it.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO academic_years (year_label, is_active, created_by)
         VALUES (:year_label, 1, :created_by)
         ON CONFLICT (year_label) DO UPDATE SET is_active = EXCLUDED.is_active'
    );
    $statement->execute([
        'year_label' => $yearLabel,
        'created_by' => $createdBy,
    ]);

    $lookupStatement = $pdo->prepare(
        'SELECT id, year_label, is_active, created_by, created_at
         FROM academic_years
         WHERE year_label = :year_label
         LIMIT 1'
    );
    $lookupStatement->execute(['year_label' => $yearLabel]);
    $academicYear = $lookupStatement->fetch();
    if (!$academicYear) {
        throw new RuntimeException('The academic year could not be loaded after saving.');
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $createdBy,
        'activity_type' => 'academic_year_created',
        'target_reference' => $yearLabel,
        'details_text' => 'Created or reactivated the academic year ' . $yearLabel . '.',
    ]);

    return $academicYear;
}

function buildAcademicContext(PDO $pdo, int $examTypeId, ?string $termName, int $academicYearId): array
{
    $examType = $examTypeId > 0 ? getExamTypeById($pdo, $examTypeId) : null;
    if ($examType === null || (int) ($examType['is_active'] ?? 0) !== 1) {
        $defaultContext = getDefaultAcademicContext($pdo);
        $examType = $defaultContext['exam_type'];
    }

    $academicYear = $academicYearId > 0 ? getAcademicYearById($pdo, $academicYearId) : null;
    if ($academicYear === null || (int) ($academicYear['is_active'] ?? 0) !== 1) {
        $defaultContext = getDefaultAcademicContext($pdo);
        $academicYear = $defaultContext['academic_year'];
    }

    if ($examType === null || $academicYear === null) {
        throw new RuntimeException('Create at least one active exam type and one active academic year in administration first.');
    }

    $termName = normalizeAcademicTerm($termName);

    return [
        'exam_type' => $examType,
        'academic_year' => $academicYear,
        'term_name' => $termName,
        'term_label' => buildAcademicContextLabelFromNames(
            (string) $examType['exam_name'],
            $termName,
            (string) $academicYear['year_label']
        ),
    ];
}

function splitAcademicContextLabel(?string $termLabel): array
{
    $termLabel = canonicalizeStoredAcademicContextLabel((string) $termLabel);
    if (preg_match('/^(.*?)\s*\/\s*(Term [123])\s*\/\s*(.+)$/i', $termLabel, $matches) === 1) {
        return [
            'exam_name' => preg_replace('/\s+/', ' ', trim((string) $matches[1])),
            'term_name' => normalizeAcademicTerm((string) $matches[2]),
            'academic_year_label' => normalizeAcademicYearLabel((string) $matches[3]),
            'term_label' => $termLabel,
        ];
    }

    return [
        'exam_name' => 'End of Term',
        'term_name' => normalizeAcademicTerm(null),
        'academic_year_label' => (string) date('Y'),
        'term_label' => $termLabel,
    ];
}

function buildAcademicContextFromLabel(PDO $pdo, ?string $termLabel): array
{
    $parts = splitAcademicContextLabel($termLabel);
    $defaultContext = getDefaultAcademicContext($pdo);
    $examType = getExamTypeByName($pdo, (string) $parts['exam_name']) ?? $defaultContext['exam_type'];
    $academicYear = getAcademicYearByLabel($pdo, (string) $parts['academic_year_label']) ?? $defaultContext['academic_year'];

    if ($examType === null || $academicYear === null) {
        return $defaultContext;
    }

    return buildAcademicContext(
        $pdo,
        (int) $examType['id'],
        (string) $parts['term_name'],
        (int) $academicYear['id']
    );
}

function migrateLegacyAcademicContextLabels(PDO $pdo): void
{
    $migrationTargets = [
        ['table' => 'mark_entries', 'id_column' => 'id'],
        ['table' => 'report_publications', 'id_column' => 'id'],
        ['table' => 'mark_scan_imports', 'id_column' => 'id'],
    ];

    foreach ($migrationTargets as $target) {
        $selectStatement = $pdo->query(
            'SELECT ' . $target['id_column'] . ' AS record_id, term_label FROM ' . $target['table']
        );
        $records = $selectStatement->fetchAll();
        if ($records === []) {
            continue;
        }

        $updateStatement = $pdo->prepare(
            'UPDATE ' . $target['table'] . ' SET term_label = :term_label WHERE ' . $target['id_column'] . ' = :record_id'
        );

        foreach ($records as $record) {
            $updatedLabel = canonicalizeStoredAcademicContextLabel((string) ($record['term_label'] ?? ''));
            if ($updatedLabel === (string) ($record['term_label'] ?? '')) {
                continue;
            }

            $updateStatement->execute([
                'term_label' => $updatedLabel,
                'record_id' => (int) $record['record_id'],
            ]);
        }
    }
}

function getAnnouncementsForAudience(PDO $pdo, string $audience, ?string $className = null, int $limit = 6): array
{
        if ($className === null || trim($className) === '') {
                $statement = $pdo->prepare(
                        "SELECT title_text, body_text, audience, class_name, category, created_by, created_at
                         FROM announcements
                         WHERE is_active = 1
                             AND (audience = 'all' OR audience = :audience)
                         ORDER BY created_at DESC, id DESC
                         LIMIT :limit_count"
                );
                $statement->bindValue(':audience', $audience, PDO::PARAM_STR);
                $statement->bindValue(':limit_count', $limit, PDO::PARAM_INT);
                $statement->execute();

                return $statement->fetchAll();
        }

    $statement = $pdo->prepare(
                "SELECT title_text, body_text, audience, class_name, category, created_by, created_at
                 FROM announcements
                 WHERE is_active = 1
                     AND (audience = 'all' OR audience = :audience)
                     AND (class_name IS NULL OR class_name = :class_name)
                 ORDER BY created_at DESC, id DESC
                 LIMIT :limit_count"
        );
        $statement->bindValue(':audience', $audience, PDO::PARAM_STR);
        $statement->bindValue(':class_name', normalizeClassName($className), PDO::PARAM_STR);
        $statement->bindValue(':limit_count', $limit, PDO::PARAM_INT);
        $statement->execute();

    return $statement->fetchAll();
}

function getSubjects(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT id, subject_code, subject_name FROM subjects WHERE is_active = 1 ORDER BY subject_name ASC'
    );

    return $statement->fetchAll();
}

function getClassLists(PDO $pdo, bool $includeInactive = false): array
{
    $whereClause = $includeInactive ? '' : 'WHERE class_lists.is_active = 1';
    $statement = $pdo->query(
        "SELECT
            class_lists.id,
            class_lists.class_name,
            class_lists.class_stream,
            class_lists.display_name,
            class_lists.is_active,
            class_lists.promoted_to_class_list_id,
            class_lists.created_by,
            class_lists.created_at,
            class_lists.updated_at,
            COALESCE(SUM(CASE WHEN class_list_students.enrollment_status = 'active' AND users.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_student_count
         FROM class_lists
         LEFT JOIN class_list_students
           ON class_list_students.class_list_id = class_lists.id
         LEFT JOIN users
           ON users.id = class_list_students.student_user_id
          AND users.role = 'student'
         {$whereClause}
         GROUP BY
            class_lists.id,
            class_lists.class_name,
            class_lists.class_stream,
            class_lists.display_name,
            class_lists.is_active,
            class_lists.promoted_to_class_list_id,
            class_lists.created_by,
            class_lists.created_at,
            class_lists.updated_at
         ORDER BY class_lists.class_name ASC, class_lists.class_stream ASC, class_lists.display_name ASC"
    );

    return $statement->fetchAll();
}

function getClassListById(PDO $pdo, int $classListId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, class_name, class_stream, display_name, is_active, promoted_to_class_list_id, created_by, created_at, updated_at
         FROM class_lists
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $classListId]);
    $classList = $statement->fetch();

    return $classList ?: null;
}

function getClassListByDisplayName(PDO $pdo, string $displayName): ?array
{
    $displayName = normalizeClassName($displayName);
    if ($displayName === '' || $displayName === 'Unassigned') {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT id, class_name, class_stream, display_name, is_active, promoted_to_class_list_id, created_by, created_at, updated_at
         FROM class_lists
         WHERE display_name = :display_name
         LIMIT 1'
    );
    $statement->execute(['display_name' => $displayName]);
    $classList = $statement->fetch();

    return $classList ?: null;
}

function getClassListStudents(PDO $pdo, int $classListId, ?string $enrollmentStatus = 'active'): array
{
    $whereClause = $enrollmentStatus ? 'AND cls.enrollment_status = :enrollment_status' : '';
    $statement = $pdo->prepare(
        "SELECT cls.id, cls.student_user_id, u.account_number, u.full_name, u.is_active, cls.enrollment_status, cls.assigned_at, cls.removed_at
         FROM class_list_students cls
         INNER JOIN users u ON cls.student_user_id = u.id
         WHERE cls.class_list_id = :class_list_id
         {$whereClause}
         ORDER BY u.full_name ASC"
    );
    $params = ['class_list_id' => $classListId];
    if ($enrollmentStatus) {
        $params['enrollment_status'] = $enrollmentStatus;
    }
    $statement->execute($params);

    return $statement->fetchAll();
}

function normalizeStaffAllocationSelection(array $allocations): array
{
    $normalizedAllocations = [];

    foreach ($allocations as $classListId => $subjectIds) {
        $classListId = (int) $classListId;
        if ($classListId <= 0) {
            continue;
        }

        $subjectIdMap = [];
        foreach ((array) $subjectIds as $subjectId) {
            $subjectId = (int) $subjectId;
            if ($subjectId > 0) {
                $subjectIdMap[$subjectId] = true;
            }
        }

        if ($subjectIdMap !== []) {
            $normalizedAllocations[$classListId] = array_keys($subjectIdMap);
        }
    }

    ksort($normalizedAllocations);

    return $normalizedAllocations;
}

function getStaffTeachingAllocations(PDO $pdo, int $staffUserId): array
{
    $statement = $pdo->prepare(
        "SELECT
            staff_teaching_allocations.id,
            staff_teaching_allocations.staff_user_id,
            staff_teaching_allocations.class_list_id,
            staff_teaching_allocations.subject_id,
            staff_teaching_allocations.assigned_by_name,
            staff_teaching_allocations.created_at,
            class_lists.display_name AS class_list_display_name,
            class_lists.class_name,
            class_lists.class_stream,
            subjects.subject_code,
            subjects.subject_name
         FROM staff_teaching_allocations
         INNER JOIN class_lists
           ON class_lists.id = staff_teaching_allocations.class_list_id
         INNER JOIN subjects
           ON subjects.id = staff_teaching_allocations.subject_id
         WHERE staff_teaching_allocations.staff_user_id = :staff_user_id
           AND class_lists.is_active = 1
           AND subjects.is_active = 1
         ORDER BY class_lists.display_name ASC, subjects.subject_name ASC"
    );
    $statement->execute(['staff_user_id' => $staffUserId]);

    return $statement->fetchAll();
}

function getStaffAssignedClassLists(PDO $pdo, int $staffUserId): array
{
    $assignedClassListIds = [];
    foreach (getStaffTeachingAllocations($pdo, $staffUserId) as $allocation) {
        $assignedClassListIds[(int) $allocation['class_list_id']] = true;
    }

    if ($assignedClassListIds === []) {
        return [];
    }

    return array_values(array_filter(
        getClassLists($pdo),
        static fn (array $classList): bool => isset($assignedClassListIds[(int) $classList['id']])
    ));
}

function getStaffAssignedSubjects(PDO $pdo, int $staffUserId, ?int $classListId = null): array
{
    $assignedSubjectIds = [];
    foreach (getStaffTeachingAllocations($pdo, $staffUserId) as $allocation) {
        if ($classListId !== null && $classListId > 0 && (int) $allocation['class_list_id'] !== $classListId) {
            continue;
        }

        $assignedSubjectIds[(int) $allocation['subject_id']] = true;
    }

    if ($assignedSubjectIds === []) {
        return [];
    }

    return array_values(array_filter(
        getSubjects($pdo),
        static fn (array $subject): bool => isset($assignedSubjectIds[(int) $subject['id']])
    ));
}

function staffHasTeachingAssignment(PDO $pdo, int $staffUserId, int $classListId, ?int $subjectId = null): bool
{
    if ($staffUserId <= 0 || $classListId <= 0) {
        return false;
    }

    $query = 'SELECT COUNT(*) FROM staff_teaching_allocations WHERE staff_user_id = :staff_user_id AND class_list_id = :class_list_id';
    $parameters = [
        'staff_user_id' => $staffUserId,
        'class_list_id' => $classListId,
    ];

    if ($subjectId !== null && $subjectId > 0) {
        $query .= ' AND subject_id = :subject_id';
        $parameters['subject_id'] = $subjectId;
    }

    $statement = $pdo->prepare($query);
    $statement->execute($parameters);

    return (int) $statement->fetchColumn() > 0;
}

function requireStaffTeachingAssignment(PDO $pdo, array $staffUser, int $classListId, ?int $subjectId = null): void
{
    if (!staffHasTeachingAssignment($pdo, (int) ($staffUser['id'] ?? 0), $classListId, $subjectId)) {
        throw new RuntimeException(
            $subjectId !== null && $subjectId > 0
                ? 'You are not assigned to enter marks for that class and subject.'
                : 'You are not assigned to that class assessment sheet.'
        );
    }
}

function saveStaffTeachingAllocations(PDO $pdo, int $staffUserId, array $allocations, string $assignedBy, ?int $assignedByUserId = null): int
{
    $staffAccount = getStaffAccountById($pdo, $staffUserId);
    if ($staffAccount === null) {
        throw new RuntimeException('Select a valid staff account before saving allocations.');
    }

    $normalizedAllocations = normalizeStaffAllocationSelection($allocations);
    $availableSubjects = [];
    foreach (getSubjects($pdo) as $subject) {
        $availableSubjects[(int) $subject['id']] = $subject;
    }

    foreach ($normalizedAllocations as $classListId => $subjectIds) {
        $classList = getClassListById($pdo, $classListId);
        if ($classList === null || (int) $classList['is_active'] !== 1) {
            throw new RuntimeException('Every selected teaching allocation must point to an active class list.');
        }

        foreach ($subjectIds as $subjectId) {
            if (!isset($availableSubjects[(int) $subjectId])) {
                throw new RuntimeException('Every selected teaching allocation must point to an active subject.');
            }
        }
    }

    $savedCount = 0;
    $pdo->beginTransaction();

    try {
        $deleteStatement = $pdo->prepare('DELETE FROM staff_teaching_allocations WHERE staff_user_id = :staff_user_id');
        $deleteStatement->execute(['staff_user_id' => $staffUserId]);

        $insertStatement = $pdo->prepare(
            'INSERT INTO staff_teaching_allocations (staff_user_id, class_list_id, subject_id, assigned_by_user_id, assigned_by_name)
             VALUES (:staff_user_id, :class_list_id, :subject_id, :assigned_by_user_id, :assigned_by_name)'
        );

        foreach ($normalizedAllocations as $classListId => $subjectIds) {
            foreach ($subjectIds as $subjectId) {
                $insertStatement->execute([
                    'staff_user_id' => $staffUserId,
                    'class_list_id' => $classListId,
                    'subject_id' => $subjectId,
                    'assigned_by_user_id' => $assignedByUserId,
                    'assigned_by_name' => $assignedBy,
                ]);
                $savedCount++;
            }
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    logStaffActivity($pdo, [
        'user_id' => $assignedByUserId,
        'staff_name' => $assignedBy,
        'activity_type' => 'staff_assignment_updated',
        'target_reference' => (string) $staffAccount['account_number'],
        'details_text' => 'Saved ' . $savedCount . ' teaching allocation(s) for ' . (string) $staffAccount['full_name'] . '.',
    ]);

    return $savedCount;
}

function bootstrapDefaultStaffTeachingAllocations(PDO $pdo): void
{
    if (!administration_should_seed_demo_data()) {
        return;
    }

    $staffStatement = $pdo->prepare(
        "SELECT id FROM users WHERE account_number = :account_number AND role = 'staff' AND is_active = 1 LIMIT 1"
    );
    $staffStatement->execute(['account_number' => 'STF-2001']);
    $staffId = (int) ($staffStatement->fetchColumn() ?: 0);
    if ($staffId <= 0) {
        return;
    }

    $allocationCountStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM staff_teaching_allocations WHERE staff_user_id = :staff_user_id'
    );
    $allocationCountStatement->execute(['staff_user_id' => $staffId]);
    if ((int) $allocationCountStatement->fetchColumn() > 0) {
        return;
    }

    $classList = getClassListByDisplayName($pdo, 'Senior 1A');
    $subjects = getSubjects($pdo);
    if ($classList === null || $subjects === []) {
        return;
    }

        $insertStatement = $pdo->prepare(
        'INSERT INTO staff_teaching_allocations (staff_user_id, class_list_id, subject_id, assigned_by_user_id, assigned_by_name)
         VALUES (:staff_user_id, :class_list_id, :subject_id, NULL, :assigned_by_name)
         ON CONFLICT (staff_user_id, class_list_id, subject_id) DO UPDATE SET assigned_by_name = EXCLUDED.assigned_by_name'
    );

    foreach ($subjects as $subject) {
        $insertStatement->execute([
            'staff_user_id' => $staffId,
            'class_list_id' => (int) $classList['id'],
            'subject_id' => (int) $subject['id'],
            'assigned_by_name' => 'System Bootstrap',
        ]);
    }
}

function createClassList(PDO $pdo, string $className, string $classStream, string $createdBy): array
{
    $className = normalizeClassName($className);
    $classStream = normalizeClassStream($classStream);

    if ($className === '' || $className === 'Unassigned') {
        throw new RuntimeException('Provide a valid class name.');
    }

    if ($classStream === '') {
        throw new RuntimeException('Provide a class stream.');
    }

    $displayName = formatClassListDisplayName($className, $classStream);
    $statement = $pdo->prepare(
        'INSERT INTO class_lists (class_name, class_stream, display_name, created_by)
         VALUES (:class_name, :class_stream, :display_name, :created_by)
         ON CONFLICT (class_name, class_stream) DO UPDATE SET
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'class_name' => $className,
        'class_stream' => $classStream,
        'display_name' => $displayName,
        'created_by' => $createdBy,
    ]);

    $classList = getClassListByDisplayName($pdo, $displayName);
    if ($classList === null) {
        throw new RuntimeException('The class list could not be created.');
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $createdBy,
        'activity_type' => 'class_list_created',
        'target_reference' => $displayName,
        'details_text' => 'Created or reactivated the class list ' . $displayName . '.',
    ]);

    return $classList;
}

function ensureClassListForDisplayName(PDO $pdo, string $displayName, string $createdBy): ?array
{
    $displayName = normalizeClassName($displayName);
    if ($displayName === '' || $displayName === 'Unassigned') {
        return null;
    }

    $existing = getClassListByDisplayName($pdo, $displayName);
    if ($existing !== null) {
        return $existing;
    }

    $statement = $pdo->prepare(
        'INSERT INTO class_lists (class_name, class_stream, display_name, created_by)
         VALUES (:class_name, :class_stream, :display_name, :created_by)
         ON CONFLICT (class_name, class_stream) DO UPDATE SET
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'class_name' => $displayName,
        'class_stream' => '',
        'display_name' => $displayName,
        'created_by' => $createdBy,
    ]);

    return getClassListByDisplayName($pdo, $displayName);
}

function syncStudentToClassList(PDO $pdo, int $studentId, array $classList): void
{
    $currentMembershipStatement = $pdo->prepare(
        'SELECT class_list_id FROM class_list_students WHERE student_user_id = :student_user_id AND enrollment_status = :enrollment_status LIMIT 1'
    );
    $currentMembershipStatement->execute([
        'student_user_id' => $studentId,
        'enrollment_status' => 'active',
    ]);
    $currentMembership = $currentMembershipStatement->fetch();

    $userClassStatement = $pdo->prepare('SELECT class_name FROM users WHERE id = :id AND role = :role LIMIT 1');
    $userClassStatement->execute([
        'id' => $studentId,
        'role' => 'student',
    ]);
    $userRecord = $userClassStatement->fetch();

    if ($currentMembership && (int) $currentMembership['class_list_id'] === (int) $classList['id'] && (string) ($userRecord['class_name'] ?? '') === (string) $classList['display_name']) {
        return;
    }

    $deactivateMembershipStatement = $pdo->prepare(
        'UPDATE class_list_students
         SET enrollment_status = :transferred_status, removed_at = NOW()
         WHERE student_user_id = :student_user_id
           AND enrollment_status = :active_status
           AND class_list_id <> :class_list_id'
    );
    $deactivateMembershipStatement->execute([
        'transferred_status' => 'transferred',
        'student_user_id' => $studentId,
        'active_status' => 'active',
        'class_list_id' => (int) $classList['id'],
    ]);

        $upsertMembershipStatement = $pdo->prepare(
        'INSERT INTO class_list_students (class_list_id, student_user_id, enrollment_status, assigned_at, removed_at)
         VALUES (:class_list_id, :student_user_id, :active_status, CURRENT_TIMESTAMP, NULL)
         ON CONFLICT (class_list_id, student_user_id) DO UPDATE SET
            enrollment_status = EXCLUDED.enrollment_status,
            assigned_at = CURRENT_TIMESTAMP,
            removed_at = NULL'
    );
    $upsertMembershipStatement->execute([
        'class_list_id' => (int) $classList['id'],
        'student_user_id' => $studentId,
        'active_status' => 'active',
    ]);

    $updateUserStatement = $pdo->prepare(
        'UPDATE users SET class_name = :class_name WHERE id = :id AND role = :role'
    );
    $updateUserStatement->execute([
        'class_name' => (string) $classList['display_name'],
        'id' => $studentId,
        'role' => 'student',
    ]);
}

function synchronizeClassListsFromUsers(PDO $pdo, string $createdBy): void
{
    $classStatement = $pdo->query(
        "SELECT DISTINCT class_name
         FROM users
         WHERE role = 'student'
           AND class_name IS NOT NULL
           AND class_name <> ''
           AND class_name <> 'Unassigned'"
    );

    foreach ($classStatement->fetchAll() as $row) {
        ensureClassListForDisplayName($pdo, (string) $row['class_name'], $createdBy);
    }

    $studentStatement = $pdo->query(
        "SELECT id, class_name
         FROM users
         WHERE role = 'student'
           AND is_active = 1
           AND class_name IS NOT NULL
           AND class_name <> ''
           AND class_name <> 'Unassigned'"
    );

    foreach ($studentStatement->fetchAll() as $student) {
        $classList = getClassListByDisplayName($pdo, (string) $student['class_name']);
        if ($classList !== null) {
            syncStudentToClassList($pdo, (int) $student['id'], $classList);
        }
    }
}

function getClassListRoster(PDO $pdo, int $classListId): array
{
    $statement = $pdo->prepare(
        "SELECT users.id, users.account_number, users.full_name, users.class_name, class_list_students.assigned_at
         FROM class_list_students
         INNER JOIN users
           ON users.id = class_list_students.student_user_id
         WHERE class_list_students.class_list_id = :class_list_id
           AND class_list_students.enrollment_status = :enrollment_status
           AND users.role = 'student'
           AND users.is_active = 1
         ORDER BY users.full_name ASC"
    );
    $statement->execute([
        'class_list_id' => $classListId,
        'enrollment_status' => 'active',
    ]);

    return $statement->fetchAll();
}

function findStudentByIdentifier(PDO $pdo, string $identifier): ?array
{
    $identifier = preg_replace('/\s+/', ' ', trim($identifier));
    if ($identifier === '') {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, class_name, is_active
         FROM users
         WHERE role = 'student'
           AND is_active = 1
           AND (account_number = :lookup_account OR LOWER(full_name) = LOWER(:lookup_name))
         ORDER BY account_number ASC
         LIMIT 2"
    );
    $statement->execute([
        'lookup_account' => $identifier,
        'lookup_name' => $identifier,
    ]);
    $students = $statement->fetchAll();

    if ($students === []) {
        return null;
    }

    if (count($students) > 1) {
        throw new RuntimeException('More than one active student matches that name. Use the student ID instead.');
    }

    return $students[0];
}

function assignStudentsToClassList(PDO $pdo, int $classListId, array $studentIdentifiers, string $performedBy): array
{
    $classList = getClassListById($pdo, $classListId);
    if ($classList === null || (int) $classList['is_active'] !== 1) {
        throw new RuntimeException('Select a valid class list.');
    }

    $studentIdentifiers = normalizeStudentNames($studentIdentifiers);
    if ($studentIdentifiers === []) {
        throw new RuntimeException('Enter at least one student ID or exact student name.');
    }

    $assignedStudents = [];
    $unresolvedIdentifiers = [];
    $messages = [];

    foreach ($studentIdentifiers as $identifier) {
        try {
            $student = findStudentByIdentifier($pdo, $identifier);
            if ($student === null) {
                $unresolvedIdentifiers[] = $identifier;
                $messages[] = 'The student "' . $identifier . '" does not exist as an active student account.';
                continue;
            }

            syncStudentToClassList($pdo, (int) $student['id'], $classList);
            $student['class_name'] = (string) $classList['display_name'];
            $assignedStudents[] = $student;
        } catch (Throwable $throwable) {
            $unresolvedIdentifiers[] = $identifier;
            $messages[] = $throwable->getMessage();
        }
    }

    if ($assignedStudents !== []) {
        logStaffActivity($pdo, [
            'user_id' => null,
            'staff_name' => $performedBy,
            'activity_type' => 'class_list_updated',
            'target_reference' => (string) $classList['display_name'],
            'details_text' => 'Added or synchronized ' . count($assignedStudents) . ' student(s) into the class list ' . (string) $classList['display_name'] . '.',
        ]);
    }

    return [
        'assigned_students' => $assignedStudents,
        'assigned_count' => count($assignedStudents),
        'unresolved_identifiers' => array_values(array_unique($unresolvedIdentifiers)),
        'messages' => array_values(array_unique($messages)),
    ];
}

function removeStudentsFromClassList(PDO $pdo, int $classListId, array $studentIds, string $performedBy): int
{
    $classList = getClassListById($pdo, $classListId);
    if ($classList === null) {
        throw new RuntimeException('Select a valid class list.');
    }

    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $studentId): bool => $studentId > 0)));
    if ($studentIds === []) {
        throw new RuntimeException('Select at least one student to remove from the class list.');
    }

    $removedCount = 0;
    $removeStatement = $pdo->prepare(
        'UPDATE class_list_students
         SET enrollment_status = :removed_status, removed_at = NOW()
         WHERE class_list_id = :class_list_id
           AND student_user_id = :student_user_id
           AND enrollment_status = :active_status'
    );
    $resetUserClassStatement = $pdo->prepare(
        'UPDATE users
         SET class_name = :unassigned_class
         WHERE id = :student_user_id
           AND role = :role
           AND class_name = :current_class_name'
    );

    $pdo->beginTransaction();

    try {
        foreach ($studentIds as $studentId) {
            $removeStatement->execute([
                'removed_status' => 'removed',
                'class_list_id' => $classListId,
                'student_user_id' => $studentId,
                'active_status' => 'active',
            ]);

            if ($removeStatement->rowCount() > 0) {
                $resetUserClassStatement->execute([
                    'unassigned_class' => 'Unassigned',
                    'student_user_id' => $studentId,
                    'role' => 'student',
                    'current_class_name' => (string) $classList['display_name'],
                ]);
                $removedCount++;
            }
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $performedBy,
        'activity_type' => 'class_list_updated',
        'target_reference' => (string) $classList['display_name'],
        'details_text' => 'Removed ' . $removedCount . ' student(s) from the class list ' . (string) $classList['display_name'] . '.',
    ]);

    return $removedCount;
}

function promoteClassList(PDO $pdo, int $sourceClassListId, int $targetClassListId, string $performedBy): int
{
    if ($sourceClassListId === $targetClassListId) {
        throw new RuntimeException('Choose a different target class list for promotion.');
    }

    $sourceClassList = getClassListById($pdo, $sourceClassListId);
    $targetClassList = getClassListById($pdo, $targetClassListId);

    if ($sourceClassList === null || $targetClassList === null) {
        throw new RuntimeException('Select valid source and target class lists.');
    }

    $roster = getClassListRoster($pdo, $sourceClassListId);
    if ($roster === []) {
        throw new RuntimeException('The selected source class list has no active students to transfer.');
    }

    $pdo->beginTransaction();

    try {
        foreach ($roster as $student) {
            syncStudentToClassList($pdo, (int) $student['id'], $targetClassList);
        }

        $updateSourceStatement = $pdo->prepare(
            'UPDATE class_lists SET promoted_to_class_list_id = :target_class_list_id WHERE id = :source_class_list_id'
        );
        $updateSourceStatement->execute([
            'target_class_list_id' => $targetClassListId,
            'source_class_list_id' => $sourceClassListId,
        ]);

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $performedBy,
        'activity_type' => 'class_list_promoted',
        'target_reference' => (string) $targetClassList['display_name'],
        'details_text' => 'Transferred ' . count($roster) . ' student(s) from ' . (string) $sourceClassList['display_name'] . ' into ' . (string) $targetClassList['display_name'] . '.',
    ]);

    return count($roster);
}

function getClassListMarkGrid(PDO $pdo, int $classListId, int $subjectId, ?string $termLabel = null): array
{
    $classList = getClassListById($pdo, $classListId);
    if ($classList === null) {
        throw new RuntimeException('Select a valid class list.');
    }

    $subjectStatement = $pdo->prepare('SELECT id, subject_code, subject_name FROM subjects WHERE id = :id AND is_active = 1 LIMIT 1');
    $subjectStatement->execute(['id' => $subjectId]);
    $subject = $subjectStatement->fetch();

    if (!$subject) {
        throw new RuntimeException('Select a valid subject.');
    }

    $termLabel = normalizeTermLabel($termLabel);
    $roster = getClassListRoster($pdo, $classListId);
    $marksStatement = $pdo->prepare(
        'SELECT student_user_id, mark_value, updated_at
         FROM mark_entries
         WHERE subject_id = :subject_id AND term_label = :term_label'
    );
    $marksStatement->execute([
        'subject_id' => $subjectId,
        'term_label' => $termLabel,
    ]);

    $marksMap = [];
    foreach ($marksStatement->fetchAll() as $markRow) {
        $marksMap[(int) $markRow['student_user_id']] = [
            'mark_value' => (float) $markRow['mark_value'],
            'updated_at' => (string) $markRow['updated_at'],
        ];
    }

    $rows = [];
    foreach ($roster as $student) {
        $existingMark = $marksMap[(int) $student['id']] ?? null;
        $rows[] = [
            'student' => $student,
            'mark_value' => $existingMark['mark_value'] ?? null,
            'updated_at' => $existingMark['updated_at'] ?? null,
        ];
    }

    return [
        'class_list' => $classList,
        'subject' => $subject,
        'term_label' => $termLabel,
        'rows' => $rows,
    ];
}

function saveClassListMarks(PDO $pdo, int $classListId, int $subjectId, ?string $termLabel, array $markValues, array $staffUser): int
{
    requireStaffTeachingAssignment($pdo, $staffUser, $classListId, $subjectId);

    $grid = getClassListMarkGrid($pdo, $classListId, $subjectId, $termLabel);
    $termLabel = (string) $grid['term_label'];
    $savedCount = 0;

    $pdo->beginTransaction();

    try {
        foreach ($grid['rows'] as $row) {
            $studentId = (int) $row['student']['id'];
            $rawValue = trim((string) ($markValues[$studentId] ?? ''));
            if ($rawValue === '') {
                continue;
            }

            if (!is_numeric($rawValue)) {
                throw new RuntimeException('Every entered mark must be numeric.');
            }

            $markValue = (float) $rawValue;
            if ($markValue < 0 || $markValue > 100) {
                throw new RuntimeException('Marks must stay between 0 and 100.');
            }

            upsertStudentMark($pdo, $row['student'], $subjectId, $termLabel, $markValue, $staffUser, false);
            $savedCount++;
        }

        if ($savedCount === 0) {
            throw new RuntimeException('Enter at least one mark before saving the class list.');
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    logStaffActivity($pdo, [
        'user_id' => (int) ($staffUser['id'] ?? 0),
        'staff_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
        'activity_type' => 'class_marks_saved',
        'target_reference' => (string) $grid['class_list']['display_name'],
        'details_text' => 'Saved ' . $savedCount . ' ' . (string) $grid['subject']['subject_name'] . ' mark(s) for ' . (string) $grid['class_list']['display_name'] . ' (' . $termLabel . ').',
    ]);

    return $savedCount;
}

function encodeStoredJsonArray(array $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

    return $encoded === false ? '[]' : $encoded;
}

function decodeStoredJsonArray(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function normalizeMarkScanLine(string $line): string
{
    return preg_replace('/\s+/', ' ', trim($line));
}

function matchRosterStudentFromScanLine(array $roster, string $line): ?array
{
    $normalizedLine = strtolower(normalizeMarkScanLine($line));
    if ($normalizedLine === '') {
        return null;
    }

    foreach ($roster as $student) {
        $accountNumber = strtolower((string) ($student['account_number'] ?? ''));
        if ($accountNumber !== '' && strpos($normalizedLine, $accountNumber) !== false) {
            return $student;
        }
    }

    $nameMatches = [];
    foreach ($roster as $student) {
        $studentName = strtolower(normalizeMarkScanLine((string) ($student['full_name'] ?? '')));
        if ($studentName !== '' && strpos($normalizedLine, $studentName) !== false) {
            $nameMatches[] = [
                'student' => $student,
                'length' => strlen($studentName),
            ];
        }
    }

    if ($nameMatches === []) {
        return null;
    }

    usort(
        $nameMatches,
        static fn (array $left, array $right): int => $right['length'] <=> $left['length']
    );

    if (count($nameMatches) > 1 && (int) $nameMatches[0]['length'] === (int) $nameMatches[1]['length']) {
        return null;
    }

    return $nameMatches[0]['student'];
}

function extractMarkValueFromScanLine(string $line, ?string $accountNumber = null): ?float
{
    $line = normalizeMarkScanLine($line);
    if ($line === '') {
        return null;
    }

    if ($accountNumber !== null && trim($accountNumber) !== '') {
        $line = str_ireplace(trim($accountNumber), ' ', $line);
    }

    if (preg_match_all('/(?<!\d)(\d{1,3}(?:\.\d{1,2})?)(?!\d)/', $line, $matches) !== 1) {
        return null;
    }

    for ($index = count($matches[1]) - 1; $index >= 0; $index--) {
        $value = (float) $matches[1][$index];
        if ($value >= 0 && $value <= 100) {
            return round($value, 2);
        }
    }

    return null;
}

function parseMarkScanText(PDO $pdo, int $classListId, string $rawText): array
{
    $classList = getClassListById($pdo, $classListId);
    if ($classList === null) {
        throw new RuntimeException('Select a valid class list before scanning marks.');
    }

    $roster = getClassListRoster($pdo, $classListId);
    if ($roster === []) {
        throw new RuntimeException('The selected class list has no active students to match against the scanned sheet.');
    }

    $lines = preg_split('/\R/', $rawText) ?: [];
    $matchedRowsByStudentId = [];
    $unmatchedLines = [];
    $warnings = [];
    $lineCount = 0;

    foreach ($lines as $line) {
        $normalizedLine = normalizeMarkScanLine((string) $line);
        if ($normalizedLine === '') {
            continue;
        }

        $lineCount++;
        $student = matchRosterStudentFromScanLine($roster, $normalizedLine);
        if ($student === null) {
            $unmatchedLines[] = $normalizedLine;
            continue;
        }

        $markValue = extractMarkValueFromScanLine($normalizedLine, (string) ($student['account_number'] ?? ''));
        if ($markValue === null) {
            $unmatchedLines[] = $normalizedLine;
            continue;
        }

        $studentId = (int) $student['id'];
        if (isset($matchedRowsByStudentId[$studentId])) {
            $warnings[] = 'Multiple scan lines matched ' . (string) $student['full_name'] . '; the latest value was kept.';
        }

        $matchedRowsByStudentId[$studentId] = [
            'student_id' => $studentId,
            'account_number' => (string) $student['account_number'],
            'full_name' => (string) $student['full_name'],
            'mark_value' => $markValue,
            'source_line' => $normalizedLine,
        ];
    }

    return [
        'class_list' => $classList,
        'line_count' => $lineCount,
        'matched_rows' => array_values($matchedRowsByStudentId),
        'unmatched_lines' => $unmatchedLines,
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function normalizeMarkScanImportRecord(array $record): array
{
    $record['parsed_matches'] = decodeStoredJsonArray((string) ($record['parsed_matches_json'] ?? '[]'));
    $record['unmatched_lines'] = decodeStoredJsonArray((string) ($record['unmatched_lines_json'] ?? '[]'));
    $record['warnings'] = decodeStoredJsonArray((string) ($record['warnings_json'] ?? '[]'));
    $record['applied_marks'] = decodeStoredJsonArray((string) ($record['applied_marks_json'] ?? '[]'));
    $record['matched_count'] = count($record['parsed_matches']);
    $record['unmatched_count'] = count($record['unmatched_lines']);

    return $record;
}

function createMarkScanImport(
    PDO $pdo,
    int $classListId,
    int $subjectId,
    ?string $termLabel,
    ?string $providerName,
    ?string $sourceFilename,
    ?string $storedFilePath,
    string $rawText,
    array $parsedResult,
    array $staffUser
): array {
    requireStaffTeachingAssignment($pdo, $staffUser, $classListId, $subjectId);

    $statement = $pdo->prepare(
        'INSERT INTO mark_scan_imports (
            class_list_id,
            subject_id,
            term_label,
            provider_name,
            source_filename,
            stored_file_path,
            raw_text,
            parsed_matches_json,
            unmatched_lines_json,
            warnings_json,
            created_by_user_id,
            created_by_name
         ) VALUES (
            :class_list_id,
            :subject_id,
            :term_label,
            :provider_name,
            :source_filename,
            :stored_file_path,
            :raw_text,
            :parsed_matches_json,
            :unmatched_lines_json,
            :warnings_json,
            :created_by_user_id,
            :created_by_name
         )'
    );
    $statement->execute([
        'class_list_id' => $classListId,
        'subject_id' => $subjectId,
        'term_label' => normalizeTermLabel($termLabel),
        'provider_name' => $providerName,
        'source_filename' => $sourceFilename,
        'stored_file_path' => $storedFilePath,
        'raw_text' => $rawText,
        'parsed_matches_json' => encodeStoredJsonArray((array) ($parsedResult['matched_rows'] ?? [])),
        'unmatched_lines_json' => encodeStoredJsonArray((array) ($parsedResult['unmatched_lines'] ?? [])),
        'warnings_json' => encodeStoredJsonArray((array) ($parsedResult['warnings'] ?? [])),
        'created_by_user_id' => (int) ($staffUser['id'] ?? 0),
        'created_by_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
    ]);

    return getMarkScanImportById($pdo, (int) $pdo->lastInsertId()) ?? [];
}

function getMarkScanImportById(PDO $pdo, int $importId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            mark_scan_imports.*,
            class_lists.display_name AS class_list_display_name,
            subjects.subject_name,
            subjects.subject_code
         FROM mark_scan_imports
         INNER JOIN class_lists
           ON class_lists.id = mark_scan_imports.class_list_id
         INNER JOIN subjects
           ON subjects.id = mark_scan_imports.subject_id
         WHERE mark_scan_imports.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $importId]);
    $record = $statement->fetch();

    return $record ? normalizeMarkScanImportRecord($record) : null;
}

function getRecentMarkScanImports(PDO $pdo, int $limit = 8, ?int $classListId = null): array
{
    $whereClause = $classListId !== null ? 'WHERE mark_scan_imports.class_list_id = :class_list_id' : '';
    $statement = $pdo->prepare(
        "SELECT
            mark_scan_imports.*,
            class_lists.display_name AS class_list_display_name,
            subjects.subject_name,
            subjects.subject_code
         FROM mark_scan_imports
         INNER JOIN class_lists
           ON class_lists.id = mark_scan_imports.class_list_id
         INNER JOIN subjects
           ON subjects.id = mark_scan_imports.subject_id
         {$whereClause}
         ORDER BY mark_scan_imports.created_at DESC, mark_scan_imports.id DESC
         LIMIT :limit_count"
    );
    if ($classListId !== null) {
        $statement->bindValue(':class_list_id', $classListId, PDO::PARAM_INT);
    }
    $statement->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $statement->execute();

    return array_map(
        static fn (array $record): array => normalizeMarkScanImportRecord($record),
        $statement->fetchAll()
    );
}

function applyMarkScanImport(PDO $pdo, int $importId, array $reviewedMarks, array $staffUser): array
{
    $importRecord = getMarkScanImportById($pdo, $importId);
    if ($importRecord === null) {
        throw new RuntimeException('The selected scan import could not be found.');
    }

    requireStaffTeachingAssignment(
        $pdo,
        $staffUser,
        (int) $importRecord['class_list_id'],
        (int) $importRecord['subject_id']
    );

    if ((string) ($importRecord['status'] ?? '') === 'applied') {
        throw new RuntimeException('This scan import has already been applied to the marks register.');
    }

    $markValues = [];
    $appliedRows = [];

    foreach ((array) ($importRecord['parsed_matches'] ?? []) as $matchedRow) {
        $studentId = (int) ($matchedRow['student_id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }

        $reviewedValue = trim((string) ($reviewedMarks[$studentId] ?? ($matchedRow['mark_value'] ?? '')));
        if ($reviewedValue === '') {
            continue;
        }

        $markValues[$studentId] = $reviewedValue;
        $appliedRows[] = [
            'student_id' => $studentId,
            'account_number' => (string) ($matchedRow['account_number'] ?? ''),
            'full_name' => (string) ($matchedRow['full_name'] ?? ''),
            'mark_value' => (float) $reviewedValue,
        ];
    }

    $savedCount = saveClassListMarks(
        $pdo,
        (int) $importRecord['class_list_id'],
        (int) $importRecord['subject_id'],
        (string) $importRecord['term_label'],
        $markValues,
        $staffUser
    );

    $updateStatement = $pdo->prepare(
        'UPDATE mark_scan_imports
         SET status = :status,
             applied_marks_json = :applied_marks_json,
             applied_at = NOW()
         WHERE id = :id'
    );
    $updateStatement->execute([
        'status' => 'applied',
        'applied_marks_json' => encodeStoredJsonArray($appliedRows),
        'id' => $importId,
    ]);

    logStaffActivity($pdo, [
        'user_id' => (int) ($staffUser['id'] ?? 0),
        'staff_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
        'activity_type' => 'marks_scan_applied',
        'target_reference' => (string) ($importRecord['class_list_display_name'] ?? ''),
        'details_text' => 'Applied ' . $savedCount . ' scanned ' . (string) ($importRecord['subject_name'] ?? 'subject') . ' mark(s) for ' . (string) ($importRecord['class_list_display_name'] ?? 'the selected class list') . ' (' . (string) ($importRecord['term_label'] ?? '') . ').',
    ]);

    return [
        'saved_count' => $savedCount,
        'import' => getMarkScanImportById($pdo, $importId),
    ];
}

function getDistinctClassNames(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT DISTINCT class_name
         FROM (
            SELECT display_name AS class_name FROM class_lists WHERE is_active = 1
            UNION
            SELECT class_name FROM users WHERE role = 'student' AND class_name IS NOT NULL AND class_name <> ''
         ) AS class_names
         ORDER BY class_name ASC"
    );

    return array_map(
        static fn (array $row): string => (string) $row['class_name'],
        $statement->fetchAll()
    );
}

function searchStudentAccounts(PDO $pdo, string $query = ''): array
{
    $query = trim($query);

    if ($query === '') {
        return getStudentAccounts($pdo);
    }

    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, pin_code, class_name, is_active, created_at, updated_at, deactivated_at, deactivation_reason
         FROM users
         WHERE role = 'student'
           AND (account_number LIKE :lookup OR full_name LIKE :lookup)
         ORDER BY is_active DESC, full_name ASC"
    );
    $statement->execute(['lookup' => '%' . $query . '%']);

    return $statement->fetchAll();
}

function findStudentForMarks(PDO $pdo, string $lookupValue, ?string $className = null): ?array
{
    $lookupValue = preg_replace('/\s+/', ' ', trim($lookupValue));
    if ($lookupValue === '') {
        return null;
    }

    $className = normalizeClassName($className);

    $statement = $pdo->prepare(
        "SELECT id, account_number, full_name, class_name, is_active
         FROM users
         WHERE role = 'student'
           AND is_active = 1
           AND class_name = :class_name
                     AND (account_number = :lookup_account OR LOWER(full_name) = LOWER(:lookup_name))
         LIMIT 1"
    );
    $statement->execute([
        'class_name' => $className,
                'lookup_account' => $lookupValue,
                'lookup_name' => $lookupValue,
    ]);
    $student = $statement->fetch();

    return $student ?: null;
}

function getStudentMarksForTerm(PDO $pdo, int $studentId, string $termLabel): array
{
    $statement = $pdo->prepare(
        'SELECT subjects.subject_name, subjects.subject_code, mark_entries.mark_value, mark_entries.updated_at
         FROM subjects
         LEFT JOIN mark_entries
           ON mark_entries.subject_id = subjects.id
          AND mark_entries.student_user_id = :student_user_id
          AND mark_entries.term_label = :term_label
         WHERE subjects.is_active = 1
         ORDER BY subjects.subject_name ASC'
    );
    $statement->execute([
        'student_user_id' => $studentId,
        'term_label' => normalizeTermLabel($termLabel),
    ]);

    return $statement->fetchAll();
}

function upsertStudentMark(PDO $pdo, array $student, int $subjectId, string $termLabel, float $markValue, array $staffUser, bool $logActivity = true): void
{
    $termLabel = normalizeTermLabel($termLabel);
    $subjectStatement = $pdo->prepare('SELECT subject_name FROM subjects WHERE id = :id AND is_active = 1 LIMIT 1');
    $subjectStatement->execute(['id' => $subjectId]);
    $subject = $subjectStatement->fetch();

    if (!$subject) {
        throw new RuntimeException('Invalid subject selected.');
    }

    $statement = $pdo->prepare(
        "INSERT INTO mark_entries (student_user_id, subject_id, class_name, term_label, mark_value, entered_by_user_id, entered_by_name)
         VALUES (:student_user_id, :subject_id, :class_name, :term_label, :mark_value, :entered_by_user_id, :entered_by_name)
         ON CONFLICT (student_user_id, subject_id, term_label) DO UPDATE SET
            class_name = EXCLUDED.class_name,
            mark_value = EXCLUDED.mark_value,
            entered_by_user_id = EXCLUDED.entered_by_user_id,
            entered_by_name = EXCLUDED.entered_by_name,
            updated_at = CURRENT_TIMESTAMP"
    );
    $statement->execute([
        'student_user_id' => (int) $student['id'],
        'subject_id' => $subjectId,
        'class_name' => normalizeClassName((string) $student['class_name']),
        'term_label' => $termLabel,
        'mark_value' => $markValue,
        'entered_by_user_id' => (int) ($staffUser['id'] ?? 0),
        'entered_by_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
    ]);

    createStudentAlert(
        $pdo,
        (int) $student['id'],
        'Subject Marks Updated',
        (string) $subject['subject_name'] . ' marks for ' . $termLabel . ' have been entered into your academic record.',
        'info',
        (string) $subject['subject_name']
    );

    if ($logActivity) {
        logStaffActivity($pdo, [
            'user_id' => (int) ($staffUser['id'] ?? 0),
            'staff_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
            'activity_type' => 'marks_updated',
            'target_reference' => (string) $student['account_number'],
            'details_text' => 'Entered ' . (string) $subject['subject_name'] . ' mark ' . number_format($markValue, 2) . ' for ' . (string) $student['full_name'] . ' (' . $termLabel . ').',
        ]);
    }
}

function getAssessmentSheet(PDO $pdo, string $className, ?string $termLabel = null): array
{
    $className = normalizeClassName($className);
    $termLabel = normalizeTermLabel($termLabel);
    $subjects = getSubjects($pdo);

    $studentsStatement = $pdo->prepare(
        "SELECT id, account_number, full_name, class_name
         FROM users
         WHERE role = 'student' AND is_active = 1 AND class_name = :class_name
         ORDER BY full_name ASC"
    );
    $studentsStatement->execute(['class_name' => $className]);
    $students = $studentsStatement->fetchAll();

    $marksStatement = $pdo->prepare(
        'SELECT student_user_id, subject_id, mark_value FROM mark_entries WHERE class_name = :class_name AND term_label = :term_label'
    );
    $marksStatement->execute([
        'class_name' => $className,
        'term_label' => $termLabel,
    ]);

    $marksMap = [];
    foreach ($marksStatement->fetchAll() as $markEntry) {
        $marksMap[(int) $markEntry['student_user_id']][(int) $markEntry['subject_id']] = (float) $markEntry['mark_value'];
    }

    $rows = [];
    $bestStudent = null;
    foreach ($students as $student) {
        $subjectMarks = [];
        $total = 0.0;
        $enteredCount = 0;

        foreach ($subjects as $subject) {
            $markValue = $marksMap[(int) $student['id']][(int) $subject['id']] ?? null;
            $subjectMarks[(int) $subject['id']] = $markValue;

            if ($markValue !== null) {
                $total += (float) $markValue;
                $enteredCount++;
            }
        }

        $average = $enteredCount > 0 ? round($total / $enteredCount, 2) : null;
        $row = [
            'student' => $student,
            'marks' => $subjectMarks,
            'total' => $enteredCount > 0 ? round($total, 2) : null,
            'average' => $average,
            'missing_count' => count($subjects) - $enteredCount,
        ];
        $rows[] = $row;

        if ($average !== null && ($bestStudent === null || $average > (float) $bestStudent['average'])) {
            $bestStudent = [
                'full_name' => (string) $student['full_name'],
                'account_number' => (string) $student['account_number'],
                'average' => $average,
            ];
        }
    }

    return [
        'class_name' => $className,
        'term_label' => $termLabel,
        'subjects' => $subjects,
        'rows' => $rows,
        'best_student' => $bestStudent,
    ];
}

function getReportPublication(PDO $pdo, string $className, ?string $termLabel = null): ?array
{
    $statement = $pdo->prepare(
        'SELECT class_name, term_label, publish_at, published_by, created_at
         FROM report_publications
         WHERE class_name = :class_name AND term_label = :term_label
         LIMIT 1'
    );
    $statement->execute([
        'class_name' => normalizeClassName($className),
        'term_label' => normalizeTermLabel($termLabel),
    ]);
    $publication = $statement->fetch();

    return $publication ?: null;
}

function evaluateReportReadiness(PDO $pdo, string $className, ?string $termLabel = null): array
{
    $assessmentSheet = getAssessmentSheet($pdo, $className, $termLabel);
    $missingStudents = [];

    foreach ($assessmentSheet['rows'] as $row) {
        if ((int) $row['missing_count'] > 0) {
            $missingStudents[] = [
                'full_name' => (string) $row['student']['full_name'],
                'account_number' => (string) $row['student']['account_number'],
                'missing_count' => (int) $row['missing_count'],
            ];
        }
    }

    return [
        'assessment' => $assessmentSheet,
        'is_ready' => $missingStudents === [] && $assessmentSheet['rows'] !== [],
        'missing_students' => $missingStudents,
    ];
}

function scheduleReportPublication(PDO $pdo, string $className, ?string $termLabel, string $publishAt, string $publishedBy): array
{
    $className = normalizeClassName($className);
    $termLabel = normalizeTermLabel($termLabel);
        $readiness = evaluateReportReadiness($pdo, $className, $termLabel);

    if (!$readiness['is_ready']) {
        throw new RuntimeException('Report cannot be published until every subject mark is filled in for the class assessment sheet.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO report_publications (class_name, term_label, publish_at, published_by)
         VALUES (:class_name, :term_label, :publish_at, :published_by)
         ON CONFLICT (class_name, term_label) DO UPDATE SET publish_at = EXCLUDED.publish_at, published_by = EXCLUDED.published_by'
    );
    $statement->execute([
        'class_name' => $className,
        'term_label' => $termLabel,
        'publish_at' => $publishAt,
        'published_by' => $publishedBy,
    ]);

    createAnnouncement(
        $pdo,
        'Report Release Scheduled',
        'Reports for ' . $className . ' (' . $termLabel . ') will be available on ' . $publishAt . '.',
        'student',
        $publishedBy,
        $className,
        'reports'
    );

    foreach ($readiness['assessment']['rows'] as $row) {
        createStudentAlert(
            $pdo,
            (int) $row['student']['id'],
            'Reports Update',
            'Your ' . $termLabel . ' report for ' . $className . ' is scheduled for release on ' . $publishAt . '.',
            'success'
        );
    }

    return getReportPublication($pdo, $className, $termLabel) ?? [];
}

function getStudentReportCard(PDO $pdo, int $studentId, ?string $termLabel = null): array
{
    $termLabel = normalizeTermLabel($termLabel);
    $student = getStudentAccountById($pdo, $studentId);

    if (!$student) {
        return [
            'visible' => false,
            'reason' => 'Student account not found.',
            'marks' => [],
            'publication' => null,
            'summary' => null,
        ];
    }

    $marks = getStudentMarksForTerm($pdo, $studentId, $termLabel);
    $publication = getReportPublication($pdo, (string) $student['class_name'], $termLabel);

    $missingSubjects = [];
    $total = 0.0;
    $count = 0;
    foreach ($marks as $markRow) {
        if ($markRow['mark_value'] === null) {
            $missingSubjects[] = (string) $markRow['subject_name'];
            continue;
        }

        $total += (float) $markRow['mark_value'];
        $count++;
    }

    if ($missingSubjects !== []) {
        return [
            'visible' => false,
            'reason' => 'Report card is not available until every subject mark has been submitted.',
            'marks' => $marks,
            'publication' => $publication,
            'summary' => null,
        ];
    }

    if (!$publication) {
        return [
            'visible' => false,
            'reason' => 'Administration has not published this report yet.',
            'marks' => $marks,
            'publication' => null,
            'summary' => null,
        ];
    }

    if (strtotime((string) $publication['publish_at']) > time()) {
        return [
            'visible' => false,
            'reason' => 'Your report card will be available on ' . (string) $publication['publish_at'] . '.',
            'marks' => $marks,
            'publication' => $publication,
            'summary' => null,
        ];
    }

    return [
        'visible' => true,
        'reason' => null,
        'marks' => $marks,
        'publication' => $publication,
        'summary' => [
            'total' => round($total, 2),
            'average' => $count > 0 ? round($total / $count, 2) : null,
        ],
    ];
}

function getStaffActivityLogs(PDO $pdo, int $limit = 40): array
{
    $statement = $pdo->prepare(
        'SELECT staff_name, activity_type, target_reference, details_text, created_at
         FROM staff_activity_logs
         ORDER BY created_at DESC, id DESC
         LIMIT :limit_count'
    );
    $statement->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

// ===== GRADING SYSTEMS =====

function getGradingSystems(PDO $pdo, bool $includeInactive = false): array
{
    $whereClause = $includeInactive ? '' : 'WHERE is_active = 1';
    $statement = $pdo->query(
        "SELECT id, system_name, description, is_active, created_by, created_at, updated_at
         FROM grading_systems
         {$whereClause}
         ORDER BY created_at ASC, system_name ASC"
    );

    return $statement->fetchAll();
}

function getGradingSystemById(PDO $pdo, int $systemId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, system_name, description, is_active, created_by, created_at, updated_at
         FROM grading_systems
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $systemId]);
    $system = $statement->fetch();

    return $system ?: null;
}

function getGradingSystemByName(PDO $pdo, string $systemName): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, system_name, description, is_active, created_by, created_at, updated_at
         FROM grading_systems
         WHERE LOWER(system_name) = LOWER(:system_name)
         LIMIT 1'
    );
    $statement->execute(['system_name' => preg_replace('/\s+/', ' ', trim($systemName))]);
    $system = $statement->fetch();

    return $system ?: null;
}

function createGradingSystem(PDO $pdo, string $systemName, string $description, string $createdBy): array
{
    $systemName = preg_replace('/\s+/', ' ', trim($systemName));
    if ($systemName === '') {
        throw new RuntimeException('Enter the grading system name before saving it.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO grading_systems (system_name, description, is_active, created_by)
         VALUES (:system_name, :description, 1, :created_by)
         ON CONFLICT (system_name) DO UPDATE SET is_active = EXCLUDED.is_active, updated_at = NOW()'
    );
    $statement->execute([
        'system_name' => $systemName,
        'description' => $description,
        'created_by' => $createdBy,
    ]);

    $lookupStatement = $pdo->prepare(
        'SELECT id, system_name, description, is_active, created_by, created_at, updated_at
         FROM grading_systems
         WHERE system_name = :system_name
         LIMIT 1'
    );
    $lookupStatement->execute(['system_name' => $systemName]);
    $system = $lookupStatement->fetch();
    if (!$system) {
        throw new RuntimeException('The grading system could not be loaded after saving.');
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $createdBy,
        'activity_type' => 'grading_system_created',
        'target_reference' => $systemName,
        'details_text' => 'Created or reactivated the grading system: ' . $systemName . '.',
    ]);

    return $system;
}

function getGradingScalesBySystem(PDO $pdo, int $systemId): array
{
    $statement = $pdo->prepare(
        'SELECT id, grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order, created_at, updated_at
         FROM grading_scales
         WHERE grading_system_id = :system_id
         ORDER BY sort_order ASC, grade_label ASC'
    );
    $statement->execute(['system_id' => $systemId]);

    return $statement->fetchAll();
}

function getGradeForMark(PDO $pdo, int $systemId, float $markValue): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, grading_system_id, grade_label, grade_name, mark_from, mark_to, description, sort_order
         FROM grading_scales
         WHERE grading_system_id = :system_id
           AND :mark_value >= mark_from
           AND :mark_value <= mark_to
         LIMIT 1'
    );
    $statement->execute([
        'system_id' => $systemId,
        'mark_value' => $markValue,
    ]);
    $scale = $statement->fetch();

    return $scale ?: null;
}

function getTeacherRemarkTemplates(PDO $pdo, int $systemId): array
{
    $statement = $pdo->prepare(
        'SELECT id, grading_system_id, grade_label, remark_template, sort_order, is_active, created_by, created_at, updated_at
         FROM teacher_remark_templates
         WHERE grading_system_id = :system_id AND is_active = 1
         ORDER BY sort_order ASC, grade_label ASC'
    );
    $statement->execute(['system_id' => $systemId]);

    return $statement->fetchAll();
}

function getRemarkTemplateForGrade(PDO $pdo, int $systemId, string $gradeLabel): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, grading_system_id, grade_label, remark_template, sort_order, is_active, created_by, created_at, updated_at
         FROM teacher_remark_templates
         WHERE grading_system_id = :system_id
           AND grade_label = :grade_label
           AND is_active = 1
         LIMIT 1'
    );
    $statement->execute([
        'system_id' => $systemId,
        'grade_label' => trim((string) $gradeLabel),
    ]);
    $template = $statement->fetch();

    return $template ?: null;
}

function saveStudentRemark(PDO $pdo, int $studentId, int $subjectId, string $className, string $termLabel, int $systemId, string $gradeLabel, string $remarkText, array $staffUser): array
{
    $student = getStudentAccountById($pdo, $studentId);
    if (!$student) {
        throw new RuntimeException('Student account not found.');
    }

    $subject = getSubjectById($pdo, $subjectId);
    if (!$subject) {
        throw new RuntimeException('Subject not found.');
    }

    $gradingSystem = getGradingSystemById($pdo, $systemId);
    if (!$gradingSystem) {
        throw new RuntimeException('Grading system not found.');
    }

    $gradeScale = $pdo->prepare(
        'SELECT id FROM grading_scales
         WHERE grading_system_id = :system_id AND grade_label = :grade_label LIMIT 1'
    );
    $gradeScale->execute([
        'system_id' => $systemId,
        'grade_label' => trim((string) $gradeLabel),
    ]);
    if (!$gradeScale->fetch()) {
        throw new RuntimeException('Invalid grade label for the selected grading system.');
    }

    $termLabel = normalizeTermLabel($termLabel);
    $remarkText = trim((string) $remarkText);

    $statement = $pdo->prepare(
        'INSERT INTO student_remarks (student_user_id, subject_id, class_name, term_label, grading_system_id, grade_label, remark_text, entered_by_user_id, entered_by_name, created_at, updated_at)
         VALUES (:student_user_id, :subject_id, :class_name, :term_label, :grading_system_id, :grade_label, :remark_text, :entered_by_user_id, :entered_by_name, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
         grade_label = VALUES(grade_label),
         remark_text = VALUES(remark_text),
         entered_by_user_id = VALUES(entered_by_user_id),
         entered_by_name = VALUES(entered_by_name),
         updated_at = NOW()'
    );
    $statement->execute([
        'student_user_id' => $studentId,
        'subject_id' => $subjectId,
        'class_name' => $className,
        'term_label' => $termLabel,
        'grading_system_id' => $systemId,
        'grade_label' => trim((string) $gradeLabel),
        'remark_text' => $remarkText,
        'entered_by_user_id' => (int) ($staffUser['id'] ?? 0) ?: null,
        'entered_by_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
    ]);

    logStaffActivity($pdo, [
        'user_id' => (int) ($staffUser['id'] ?? 0) ?: null,
        'staff_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
        'activity_type' => 'student_remark_saved',
        'target_reference' => (string) ($student['full_name'] ?? '') . ' - ' . (string) ($subject['subject_name'] ?? ''),
        'details_text' => 'Saved teacher remark for ' . (string) ($student['full_name'] ?? 'student') . ' in ' . (string) ($subject['subject_name'] ?? 'subject') . ' (Grade: ' . trim((string) $gradeLabel) . ').',
    ]);

    $lookupStatement = $pdo->prepare(
        'SELECT id, student_user_id, subject_id, class_name, term_label, grading_system_id, grade_label, remark_text, entered_by_name, created_at, updated_at
         FROM student_remarks
         WHERE student_user_id = :student_user_id AND subject_id = :subject_id AND term_label = :term_label
         LIMIT 1'
    );
    $lookupStatement->execute([
        'student_user_id' => $studentId,
        'subject_id' => $subjectId,
        'term_label' => $termLabel,
    ]);
    $remark = $lookupStatement->fetch();

    return $remark ?: [];
}

function getStudentRemarks(PDO $pdo, int $studentId, ?string $termLabel = null): array
{
    $termLabel = normalizeTermLabel($termLabel);
    $statement = $pdo->prepare(
        'SELECT sr.id, sr.student_user_id, sr.subject_id, sr.class_name, sr.term_label, sr.grading_system_id, sr.grade_label, sr.remark_text, sr.entered_by_name, sr.created_at, sr.updated_at,
                s.subject_name, s.subject_code,
                gs.system_name
         FROM student_remarks sr
         LEFT JOIN subjects s ON sr.subject_id = s.id
         LEFT JOIN grading_systems gs ON sr.grading_system_id = gs.id
         WHERE sr.student_user_id = :student_user_id'
    );

    if ($termLabel) {
        $statement = $pdo->prepare(
            'SELECT sr.id, sr.student_user_id, sr.subject_id, sr.class_name, sr.term_label, sr.grading_system_id, sr.grade_label, sr.remark_text, sr.entered_by_name, sr.created_at, sr.updated_at,
                    s.subject_name, s.subject_code,
                    gs.system_name
             FROM student_remarks sr
             LEFT JOIN subjects s ON sr.subject_id = s.id
             LEFT JOIN grading_systems gs ON sr.grading_system_id = gs.id
             WHERE sr.student_user_id = :student_user_id AND sr.term_label = :term_label'
        );
        $statement->execute([
            'student_user_id' => $studentId,
            'term_label' => $termLabel,
        ]);
    } else {
        $statement->execute(['student_user_id' => $studentId]);
    }

    return $statement->fetchAll();
}

function getClassListRemarks(PDO $pdo, int $classListId, int $subjectId, string $termLabel): array
{
    $termLabel = normalizeTermLabel($termLabel);
    $statement = $pdo->prepare(
        'SELECT sr.id, sr.student_user_id, sr.subject_id, sr.class_name, sr.term_label, sr.grading_system_id, sr.grade_label, sr.remark_text, sr.entered_by_name, sr.created_at, sr.updated_at,
                u.account_number, u.full_name,
                s.subject_name, s.subject_code,
                gs.system_name
         FROM student_remarks sr
         INNER JOIN users u ON sr.student_user_id = u.id
         LEFT JOIN subjects s ON sr.subject_id = s.id
         LEFT JOIN grading_systems gs ON sr.grading_system_id = gs.id
         INNER JOIN class_list_students cls ON cls.student_user_id = u.id
         INNER JOIN class_lists cl ON cls.class_list_id = cl.id
         WHERE cl.id = :class_list_id
           AND sr.subject_id = :subject_id
           AND sr.term_label = :term_label
         ORDER BY u.full_name ASC'
    );
    $statement->execute([
        'class_list_id' => $classListId,
        'subject_id' => $subjectId,
        'term_label' => $termLabel,
    ]);

    return $statement->fetchAll();
}

function getDefaultGradingSystem(PDO $pdo): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, system_name, description, is_active, created_by, created_at, updated_at
         FROM grading_systems
         WHERE is_active = 1
         ORDER BY created_at ASC, system_name ASC
         LIMIT 1'
    );
    $statement->execute();
    $system = $statement->fetch();

    return $system ?: null;
}

function getSubjectById(PDO $pdo, int $subjectId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, subject_code, subject_name, is_active, created_at
         FROM subjects
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $subjectId]);
    $subject = $statement->fetch();

    return $subject ?: null;
}

// ===== PROMOTION STATUS REMARKS =====

function getPromotionStatusRemarks(PDO $pdo, ?string $category = null, bool $includeInactive = false): array
{
    $whereClause = $includeInactive ? '' : 'WHERE is_active = 1';
    if ($category) {
        $whereClause = $includeInactive ? "WHERE remark_category = :category" : "WHERE is_active = 1 AND remark_category = :category";
    }
    
    $statement = $pdo->query(
        "SELECT id, remark_label, remark_description, remark_category, sort_order, is_active, created_by, created_at, updated_at
         FROM promotion_status_remarks
         {$whereClause}
         ORDER BY sort_order ASC, remark_label ASC"
    );

    if ($category) {
        $statement->bindParam(':category', $category);
        $statement->execute();
    }

    return $statement->fetchAll();
}

function getPromotionStatusRemarkById(PDO $pdo, int $remarkId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, remark_label, remark_description, remark_category, sort_order, is_active, created_by, created_at, updated_at
         FROM promotion_status_remarks
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $remarkId]);
    $remark = $statement->fetch();

    return $remark ?: null;
}

function createPromotionStatusRemark(PDO $pdo, string $label, string $description, string $category, string $createdBy): array
{
    $label = preg_replace('/\s+/', ' ', trim($label));
    $description = trim($description);
    
    if ($label === '') {
        throw new RuntimeException('Enter the promotion status label before saving.');
    }
    
    if (!in_array($category, ['promotion', 'academic_status', 'transfer'], true)) {
        throw new RuntimeException('Invalid remark category selected.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO promotion_status_remarks (remark_label, remark_description, remark_category, sort_order, is_active, created_by)
         VALUES (:label, :description, :category, 
                 (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM promotion_status_remarks WHERE remark_category = :category),
                 1, :created_by)
         ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()'
    );
    $statement->execute([
        'label' => $label,
        'description' => $description,
        'category' => $category,
        'created_by' => $createdBy,
    ]);

    $lookupStatement = $pdo->prepare(
        'SELECT id, remark_label, remark_description, remark_category, sort_order, is_active, created_by, created_at, updated_at
         FROM promotion_status_remarks
         WHERE remark_label = :label
         LIMIT 1'
    );
    $lookupStatement->execute(['label' => $label]);
    $remark = $lookupStatement->fetch();
    if (!$remark) {
        throw new RuntimeException('The promotion status remark could not be loaded after saving.');
    }

    logStaffActivity($pdo, [
        'user_id' => null,
        'staff_name' => $createdBy,
        'activity_type' => 'promotion_remark_created',
        'target_reference' => $label,
        'details_text' => 'Created or reactivated the promotion status remark: ' . $label . '.',
    ]);

    return $remark;
}

function updatePromotionStatusRemark(PDO $pdo, int $remarkId, string $description): array
{
    $description = trim($description);
    
    $statement = $pdo->prepare(
        'UPDATE promotion_status_remarks
         SET remark_description = :description, updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $remarkId,
        'description' => $description,
    ]);

    $remark = getPromotionStatusRemarkById($pdo, $remarkId);
    if (!$remark) {
        throw new RuntimeException('The promotion status remark could not be found.');
    }

    return $remark;
}

function deletePromotionStatusRemark(PDO $pdo, int $remarkId): void
{
    $remark = getPromotionStatusRemarkById($pdo, $remarkId);
    if (!$remark) {
        throw new RuntimeException('The promotion status remark could not be found.');
    }

    $statement = $pdo->prepare(
        'UPDATE promotion_status_remarks SET is_active = 0, updated_at = NOW() WHERE id = :id'
    );
    $statement->execute(['id' => $remarkId]);
}

function saveStudentPromotionRecord(PDO $pdo, int $studentId, string $className, string $termLabel, int $statusRemarkId, ?string $promotionNote, array $staffUser): array
{
    $student = getStudentAccountById($pdo, $studentId);
    if (!$student) {
        throw new RuntimeException('Student account not found.');
    }

    $remark = getPromotionStatusRemarkById($pdo, $statusRemarkId);
    if (!$remark) {
        throw new RuntimeException('Invalid promotion status remark selected.');
    }

    $termLabel = normalizeTermLabel($termLabel);
    $promotionNote = $promotionNote ? trim($promotionNote) : null;

    $statement = $pdo->prepare(
        'INSERT INTO student_promotion_records (student_user_id, class_name, term_label, status_remark_id, promotion_note, recorded_by_user_id, recorded_by_name, created_at, updated_at)
         VALUES (:student_user_id, :class_name, :term_label, :status_remark_id, :promotion_note, :recorded_by_user_id, :recorded_by_name, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
         status_remark_id = VALUES(status_remark_id),
         promotion_note = VALUES(promotion_note),
         recorded_by_user_id = VALUES(recorded_by_user_id),
         recorded_by_name = VALUES(recorded_by_name),
         updated_at = NOW()'
    );
    $statement->execute([
        'student_user_id' => $studentId,
        'class_name' => $className,
        'term_label' => $termLabel,
        'status_remark_id' => $statusRemarkId,
        'promotion_note' => $promotionNote,
        'recorded_by_user_id' => (int) ($staffUser['id'] ?? 0) ?: null,
        'recorded_by_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
    ]);

    logStaffActivity($pdo, [
        'user_id' => (int) ($staffUser['id'] ?? 0) ?: null,
        'staff_name' => (string) ($staffUser['full_name'] ?? 'Staff Member'),
        'activity_type' => 'student_promotion_recorded',
        'target_reference' => (string) ($student['full_name'] ?? ''),
        'details_text' => 'Recorded promotion status "' . (string) $remark['remark_label'] . '" for ' . (string) ($student['full_name'] ?? 'student') . ' (' . $termLabel . ').',
    ]);

    $lookupStatement = $pdo->prepare(
        'SELECT id, student_user_id, class_name, term_label, status_remark_id, promotion_note, recorded_by_name, created_at, updated_at
         FROM student_promotion_records
         WHERE student_user_id = :student_user_id AND class_name = :class_name AND term_label = :term_label
         LIMIT 1'
    );
    $lookupStatement->execute([
        'student_user_id' => $studentId,
        'class_name' => $className,
        'term_label' => $termLabel,
    ]);
    $record = $lookupStatement->fetch();

    return $record ?: [];
}

function getStudentPromotionRecords(PDO $pdo, int $studentId): array
{
    $statement = $pdo->prepare(
        'SELECT spr.id, spr.student_user_id, spr.class_name, spr.term_label, spr.status_remark_id, spr.promotion_note, spr.recorded_by_name, spr.created_at, spr.updated_at,
                psr.remark_label, psr.remark_description, psr.remark_category
         FROM student_promotion_records spr
         INNER JOIN promotion_status_remarks psr ON spr.status_remark_id = psr.id
         WHERE spr.student_user_id = :student_user_id
         ORDER BY spr.term_label DESC, spr.created_at DESC'
    );
    $statement->execute(['student_user_id' => $studentId]);

    return $statement->fetchAll();
}

function getClassListPromotionRecords(PDO $pdo, int $classListId, string $termLabel): array
{
    $termLabel = normalizeTermLabel($termLabel);
    $statement = $pdo->prepare(
        'SELECT spr.id, spr.student_user_id, spr.class_name, spr.term_label, spr.status_remark_id, spr.promotion_note, spr.recorded_by_name, spr.created_at,
                u.account_number, u.full_name,
                psr.remark_label, psr.remark_description, psr.remark_category
         FROM student_promotion_records spr
         INNER JOIN users u ON spr.student_user_id = u.id
         INNER JOIN promotion_status_remarks psr ON spr.status_remark_id = psr.id
         INNER JOIN class_list_students cls ON cls.student_user_id = u.id
         INNER JOIN class_lists cl ON cls.class_list_id = cl.id
         WHERE cl.id = :class_list_id AND spr.term_label = :term_label
         ORDER BY u.full_name ASC'
    );
    $statement->execute([
        'class_list_id' => $classListId,
        'term_label' => $termLabel,
    ]);

    return $statement->fetchAll();
}

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Read credentials. System env vars (getenv) take absolute priority
    // Render provides DB_PASSWORD, local .env can use DB_PASS
    $host = getenv('DB_HOST') ?: administration_env('DB_HOST', 'localhost');
    $port = (int) (getenv('DB_PORT') ?: administration_env_int('DB_PORT', 5432));
    $dbName = getenv('DB_NAME') ?: administration_env('DB_NAME', 'administration_suite');
    $username = getenv('DB_USER') ?: administration_env('DB_USER', 'postgres');
    $password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: administration_env('DB_PASS', '') ?: administration_env('DB_PASSWORD', ''));

    // Render PostgreSQL requires SSL for external connections
    $sslmode = ($host !== 'localhost' && $host !== '127.0.0.1') ? 'sslmode=require;' : '';
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};{$sslmode}";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    synchronizePhpTimezoneWithDatabase($pdo);
    initializeAdministrationDatabase($pdo, $dbName);

    return $pdo;
}

