# Grading System Implementation Guide

## Overview

A comprehensive grading system has been added to the Administration Suite, based on Uganda's new curriculum. This system allows administrators to create and manage grading systems with customizable grade scales and teacher remarks templates.

## What Was Implemented

### 1. Database Schema (4 New Tables)

#### `grading_systems` Table
- Stores different grading system definitions (e.g., "Uganda Lower Secondary (New Curriculum)")
- Fields: `system_name`, `description`, `is_active`, `created_by`, timestamps
- Allows multiple grading systems to coexist

#### `grading_scales` Table
- Defines individual grades (A, B, C, D, E, F) for each grading system
- Fields: `grade_label`, `grade_name`, `mark_from`, `mark_to`, `description`, `sort_order`
- Each scale defines the mark range that corresponds to a specific grade
- Pre-populated with Uganda's new curriculum standards:
  - **A (Excellent)**: 80-100 marks
  - **B (Very Good)**: 70-79 marks
  - **C (Good)**: 60-69 marks
  - **D (Satisfactory)**: 50-59 marks
  - **E (Weak)**: 40-49 marks
  - **F (Poor)**: 0-39 marks

#### `teacher_remark_templates` Table
- Stores standardized remark templates for each grade
- Teachers can use these as starting points for student remarks
- Pre-populated with 6 templates aligned with each grade level
- Includes examples like:
  - **Grade A**: "Demonstrates exceptional mastery and understanding..."
  - **Grade D**: "Shows adequate understanding but with gaps..."

#### `student_remarks` Table
- Stores the qualitative remarks entered by teachers for each student
- Links students, subjects, classes, terms, grades, and remark text
- Tracks who entered the remark and when
- Unique constraint ensures one remark per student per subject per term

#### `promotion_status_remarks` Table (NEW - Phase 2)
- Stores school-specific promotion status options (Promoted, Repeat, Change Station, etc.)
- Fields: `remark_label`, `remark_description`, `remark_category`, `is_active`, timestamps
- Categories: "promotion", "academic_status", "transfer"
- Pre-populated with 6 default statuses for end-of-term decisions

#### `student_promotion_records` Table (NEW - Phase 2)
- Records the promotion status assigned to each student at term end
- Links student, class, term, promotion status remark
- Includes optional promotion notes
- Unique constraint ensures one status per student per class per term

### 2. Database Functions (22 New Functions)

#### Grading System Management
- `getGradingSystems()` - Retrieve all grading systems
- `getGradingSystemById()` - Retrieve a specific grading system
- `getGradingSystemByName()` - Find grading system by name
- `createGradingSystem()` - Create new grading system
- `getDefaultGradingSystem()` - Get the first active grading system

#### Grade Scale Management
- `getGradingScalesBySystem()` - Get all grades for a system
- `getGradeForMark()` - Find grade based on mark value (e.g., 85 marks = Grade A)

#### Remark Templates
- `getTeacherRemarkTemplates()` - Get all templates for a system
- `getRemarkTemplateForGrade()` - Get template for specific grade

#### Student Remarks
- `saveStudentRemark()` - Save/update a student's remark
- `getStudentRemarks()` - Get all remarks for a student
- `getClassListRemarks()` - Get remarks for entire class in a subject/term

#### Promotion Status Management (NEW - Phase 2)
- `getPromotionStatusRemarks()` - Get all promotion status options, optionally filtered by category
- `getPromotionStatusRemarkById()` - Get specific promotion status remark
- `createPromotionStatusRemark()` - Add new promotion status (admin only)
- `updatePromotionStatusRemark()` - Update description of status remark
- `deletePromotionStatusRemark()` - Soft-delete a status remark
- `saveStudentPromotionRecord()` - Record end-of-term promotion status for student
- `getStudentPromotionRecords()` - Get all promotion records for a student
- `getClassListPromotionRecords()` - Get promotions for entire class in a term

#### Helper Functions
- `getStudentAccountById()` - Retrieve student account
- `getSubjectById()` - Retrieve subject details

### 3. Admin Interface: Grading Management

**Location**: `admin/grading-management.php`

**Features**:
1. **Create New Grading System**
   - Form to add new grading systems
   - Include name and description
   - Automatically logs creation activity

2. **Manage Grade Scales**
   - View existing grade scales for selected system
   - Add new grades by specifying:
     - Grade label (A, B, C, etc.)
     - Grade name (Excellent, Good, etc.)
     - Mark range (from/to)
     - Optional description
   - Table display showing all configured grades

3. **Manage Teacher Remark Templates**
   - Add standardized remarks for each grade
   - Templates help maintain consistency
   - Teachers can copy/adapt templates
   - Display templates in card format

**Navigation**: Added "Grading Systems" link to Admin menu

### 4. Staff Interface: Teacher Remarks

**Location**: `staff/teacher-remarks.php`

**Features**:
1. **Context Selection**
   - Select class, subject, term, and grading system
   - Dropdowns pre-filtered to staff's assigned classes/subjects

2. **Student Remarks Entry**
   - Grid showing all students in the class
   - For each student, enter:
     - Grade (dropdown from selected grading system)
     - Qualitative remark (textarea)
   - Quick-access buttons to pre-fill remarks from templates

3. **Batch Operations**
   - Save all remarks for a class/subject/term at once
   - Success feedback showing number of remarks saved
   - Activity logging for all changes

**Navigation**: Added "Teacher Remarks" link to Staff menu

### 5. Admin Interface: Promotion Status & School Remarks (NEW - Phase 2)

**Location**: `admin/grading-management.php` (bottom section)

**Features**:
1. **Create Promotion Status Remarks**
   - Add school-specific status options beyond the default set
   - Specify label (e.g., "Promoted", "Repeat", "Change Station")
   - Select category: Promotion Status, Academic Status, or Transfer/Change Station
   - Add description to explain the status

2. **View & Edit Statuses**
   - Table showing all configured promotion statuses
   - Inline edit capability for status descriptions
   - Active/Inactive badge to show status
   - Delete buttons to remove unused statuses

3. **Default Pre-populated Statuses**:
   - **Promoted** (Promotion Status)
   - **Repeat** (Promotion Status)
   - **Change Station** (Transfer/Change Station)
   - **Passed** (Academic Status)
   - **Conditional Promotion** (Academic Status)
   - **Academic Probation** (Academic Status)

### 6. Staff Interface: Student Promotion Recording (NEW - Phase 2)

**Location**: `staff/student-promotion.php`

**Features**:
1. **Class & Term Selection**
   - Dropdown to select class (filtered to staff's assigned classes)
   - Dropdown to select academic term (Term 1, Term 2, Term 3, Year End)
   - Auto-loads students for selected class

2. **Batch Promotion Recording**
   - Grid showing all active students in the class
   - For each student:
     - Select promotion status (dropdown with all available statuses)
     - Add optional notes/remarks
   - Save all records at once

3. **Activity Tracking**
   - All promotion decisions logged with timestamp
   - Records who made the decision
   - Can track promotion history per student

**Navigation**: Added "Student Promotions" link to Staff menu

### 7. Student Portal: Promotion Status Display (NEW - Phase 2)

**Locations**: `student/academics.php` and `student/assessment-sheet.php`

**Features**:
1. **Academics View**
   - Shows promotion status in the "Promotion Status" card
   - Displays status label, description, and notes
   - Shows when status was updated
   - Replaces academic alerts when status is available

2. **Assessment Sheet View**
   - Displays promotion status at top of assessment sheet
   - Green-highlighted section for easy visibility
   - Shows status, description, and optional notes

**Visibility**: 
- Only displays if a promotion status has been recorded for current term
- Otherwise shows academic alerts as normal

The default grading system includes the standard Uganda lower secondary curriculum grades:

| Grade | Name | Mark Range | Description |
|-------|------|-----------|-------------|
| A | Excellent | 80-100 | Exceptional understanding and mastery |
| B | Very Good | 70-79 | Strong understanding with minor gaps |
| C | Good | 60-69 | Satisfactory understanding of core concepts |
| D | Satisfactory | 50-59 | Adequate understanding with noticeable gaps |
| E | Weak | 40-49 | Limited understanding, significant support needed |
| F | Poor | 0-39 | Insufficient understanding, intervention required |

**Pre-populated Remarks Examples**:
- **A**: "Demonstrates exceptional mastery and understanding. Shows excellent problem-solving skills and engagement."
- **D**: "Shows adequate understanding but with gaps. Requires additional practice and reinforcement."
- **F**: "Insufficient understanding of the subject matter. Requires comprehensive support and remedial intervention."

## How to Use

### For Administrators

1. Navigate to **Admin Portal** → **Grading Systems**

**Grading Management**:
2. Create new grading systems (optional - default system already exists)
3. For each system:
   - Add/modify grade scales (mark ranges for A, B, C, etc.)
   - Add/modify teacher remark templates

**Promotion Status Remarks** (Phase 2):
4. Scroll to "Promotion Status & School Remarks" section
5. Add custom promotion statuses for your school:
   - Click "Add New Status Remark"
   - Enter label (e.g., "Promoted")
   - Select category (Promotion Status, Academic Status, Transfer/Change Station)
   - Add description
6. Edit existing statuses by clicking "Edit" button
7. Delete statuses by clicking "Delete" button

### For Teachers

**Entering Student Remarks**:
1. Navigate to **Staff Portal** → **Teacher Remarks**
2. Select class, subject, term, and grading system
3. For each student:
   - Select a grade (A, B, C, D, E, F)
   - Enter a remark (or use template as starting point)
4. Click "Save All Remarks"
5. Remarks are now saved and can be viewed in reports

**Recording Promotion Status** (Phase 2):
1. Navigate to **Staff Portal** → **Student Promotions**
2. Select class and academic term
3. For each student:
   - Select promotion status from dropdown (Promoted, Repeat, etc.)
   - Optionally add notes explaining the decision
4. Click "Save Promotion Records"
5. Statuses are recorded and visible to students immediately

### For Students

Students can view their information in the **Student Portal**:

**Academics View**:
- Navigate to **Student Portal** → **Academics**
- Select term to view
- Under "Promotion Status" card (if available), see:
  - Your promotion status (Promoted, Repeat, etc.)
  - Status description
  - Any notes added by staff
  - Date status was assigned

**Assessment Sheet View**:
- Navigate to **Student Portal** → **Assessment Sheet**
- Select term and exam type
- If a promotion status exists for the term, it appears at top in green section
- Shows same information as academics view

## Key Design Features

1. **Flexibility**: Multiple grading systems can coexist
2. **Consistency**: Pre-built templates help standardize remarks
3. **Compliance**: Based on Uganda's new curriculum standards
4. **Customizable**: Schools can create custom promotion statuses
5. **Activity Tracking**: All actions logged for audit purposes
6. **Data Integrity**: Unique constraints prevent duplicate remarks/statuses
7. **User-Friendly**: Intuitive interfaces for admin, staff, and students
8. **Multi-Status Support**: Track promotions with custom categories

## Database Migration

The new tables are automatically created when:
1. Database schema is executed
2. Default grading system and scales are seeded
3. Default remark templates are seeded

To apply this to existing installations, run the database schema file.

## Future Enhancements

Possible additions:
- Bulk upload of custom grading systems
- Import/export remarks and promotion records
- Remark templates by subject
- Comparative analytics (grades distribution, promotion rates)
- Parent portal remarks and promotion status notification
- Approval workflow for promotion decisions
- Automatic grade calculation from marks
- Custom remark validation/suggestions
- Multi-language support for remarks and statuses

## CRUD Operations Testing Checklist

### Admin Grading Management

**Grade Scales CRUD**:
- [ ] Create new grade scale (label, name, mark range)
- [ ] Edit existing grade scale (update mark range or name)
- [ ] View all grades for selected system in table
- [ ] Delete grade scale and verify removal

**Teacher Remark Templates CRUD**:
- [ ] Create new remark template for selected grade
- [ ] Edit remark template text inline
- [ ] View all templates for system
- [ ] Delete remark template

**Promotion Status Remarks CRUD** (Phase 2):
- [ ] Create new promotion status with category
- [ ] Edit description of existing status
- [ ] View all statuses with categories
- [ ] Delete status and verify removal
- [ ] Verify categories display correctly (Promotion/Academic/Transfer)

### Staff Operations

**Teacher Remarks**:
- [ ] Select class, subject, term
- [ ] Enter grade and remark for student
- [ ] Use quick-access template button
- [ ] Save remarks batch
- [ ] Verify activity log entry

**Student Promotions** (Phase 2):
- [ ] Select class and term
- [ ] Assign promotion status to multiple students
- [ ] Add optional notes for decisions
- [ ] Save promotion records
- [ ] Verify activity log entry

### Student Portal Verification

**Academics Page**:
- [ ] View promotion status when assigned
- [ ] See status description and notes
- [ ] Verify status appears only for selected term
- [ ] Check that alerts hide when status is present

**Assessment Sheet**:
- [ ] View promotion status at top when assigned
- [ ] Verify green highlight renders correctly
- [ ] Check all information displays (status, description, notes)
- [ ] Test term filtering shows correct status

## Technical Details

- **Framework**: PHP with PDO
- **Database**: MySQL/MariaDB or PostgreSQL compatible
- **Transactions**: Atomic operations for data consistency
- **Security**: SQL prepared statements, CSRF protection
- **Logging**: Activity logs for all operations
- **Error Handling**: User-friendly error messages

---

**Last Updated**: June 1, 2026
**Version**: 2.0
**Phase 2 Features Added**: Promotion Status Remarks System, Staff Promotion Recording Interface, Student Promotion Status Display
