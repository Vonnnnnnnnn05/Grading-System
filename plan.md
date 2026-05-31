# Online Grading System Plan

## Summary
Develop a PHP and MySQL-based Online Grading System for three user roles: administrator, teacher, and student. The system will centralize student grade records, support secure login, allow teachers to encode and manage grades, allow students to view grades in real time, and allow administrators to manage users, subjects, classes, and reports.

The interface will use a clean academic layout with a **70% white base**, **20% blue accent**, and **10% gray/green support colors**.

## Core Modules

### 1. Secure Login and Authentication
- Provide one login page for all users.
- Authenticate users by email/username and password.
- Use password hashing with PHP `password_hash()` and `password_verify()`.
- Redirect users based on role:
  - Admin: dashboard and management tools
  - Teacher: class and grade encoding tools
  - Student: grade viewing page
- Add logout and session timeout handling.

### 2. Admin User Management
- Admin can create, update, deactivate, and view user accounts.
- User roles:
  - Administrator
  - Teacher
  - Student
- Admin can reset passwords if needed.
- Deactivated accounts cannot log in.

### 3. Student Management
- Admin can add, update, and view student profiles.
- Store student number, name, grade level/year level, section/class, and contact details.
- Students are linked to a user account for login access.

### 4. Teacher Management
- Admin can add and update teacher profiles.
- Teachers are linked to subjects and class sections.
- Teachers can only encode grades for assigned subjects/classes.

### 5. Subject and Class Management
- Admin can create subjects.
- Admin can create class sections.
- Admin can assign:
  - Teachers to subjects
  - Subjects to class sections
  - Students to class sections

### 6. Grade Encoding
- Teachers can encode grades for assigned students.
- Teachers can update existing grades before final submission.
- Grade fields may include:
  - Preliminary grade
  - Midterm grade
  - Final grade
  - Final average
  - Remarks: Passed, Failed, Incomplete
- System automatically computes final average and remarks.

### 7. Grade Viewing
- Students can view their grades by subject and grading period.
- Students cannot edit grade records.
- Students can view historical academic records if previous terms exist.

### 8. Grade Reports
- Admin and teachers can generate grade summaries.
- Reports can be filtered by:
  - School year
  - Semester/term
  - Class section
  - Subject
  - Teacher
  - Student
- Reports should show student names, grades, final average, and remarks.
- Optional future export: PDF or Excel.

## Database Structure

### `users`
Stores login credentials and role information.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | User ID |
| `username` | VARCHAR(100), UNIQUE | Login username |
| `email` | VARCHAR(150), UNIQUE | User email |
| `password` | VARCHAR(255) | Hashed password |
| `role` | ENUM('admin','teacher','student') | User role |
| `status` | ENUM('active','inactive') | Account status |
| `created_at` | TIMESTAMP | Date created |
| `updated_at` | TIMESTAMP | Date updated |

### `students`
Stores student profile information.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Student ID |
| `user_id` | INT, FK | Linked user account |
| `student_number` | VARCHAR(50), UNIQUE | Student number |
| `first_name` | VARCHAR(100) | First name |
| `middle_name` | VARCHAR(100), NULL | Middle name |
| `last_name` | VARCHAR(100) | Last name |
| `gender` | ENUM('male','female','other') | Gender |
| `birthdate` | DATE, NULL | Birthdate |
| `contact_number` | VARCHAR(30), NULL | Contact number |
| `address` | TEXT, NULL | Address |
| `created_at` | TIMESTAMP | Date created |

### `teachers`
Stores teacher profile information.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Teacher ID |
| `user_id` | INT, FK | Linked user account |
| `employee_number` | VARCHAR(50), UNIQUE | Teacher/employee number |
| `first_name` | VARCHAR(100) | First name |
| `middle_name` | VARCHAR(100), NULL | Middle name |
| `last_name` | VARCHAR(100) | Last name |
| `contact_number` | VARCHAR(30), NULL | Contact number |
| `created_at` | TIMESTAMP | Date created |

### `subjects`
Stores subject records.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Subject ID |
| `subject_code` | VARCHAR(50), UNIQUE | Subject code |
| `subject_name` | VARCHAR(150) | Subject name |
| `description` | TEXT, NULL | Subject description |
| `created_at` | TIMESTAMP | Date created |

### `classes`
Stores class or section information.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Class ID |
| `class_name` | VARCHAR(100) | Class or section name |
| `grade_level` | VARCHAR(50) | Grade/year level |
| `school_year` | VARCHAR(20) | Example: 2026-2027 |
| `semester` | VARCHAR(50), NULL | Semester or term |
| `created_at` | TIMESTAMP | Date created |

### `class_students`
Links students to classes.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Record ID |
| `class_id` | INT, FK | Class ID |
| `student_id` | INT, FK | Student ID |

### `teacher_subjects`
Assigns teachers to subjects and classes.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Assignment ID |
| `teacher_id` | INT, FK | Teacher ID |
| `subject_id` | INT, FK | Subject ID |
| `class_id` | INT, FK | Class ID |

### `grades`
Stores student grades.

| Field | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Grade ID |
| `student_id` | INT, FK | Student ID |
| `subject_id` | INT, FK | Subject ID |
| `class_id` | INT, FK | Class ID |
| `teacher_id` | INT, FK | Teacher who encoded grade |
| `prelim_grade` | DECIMAL(5,2), NULL | Preliminary grade |
| `midterm_grade` | DECIMAL(5,2), NULL | Midterm grade |
| `final_grade` | DECIMAL(5,2), NULL | Final grade |
| `final_average` | DECIMAL(5,2), NULL | Computed average |
| `remarks` | ENUM('Passed','Failed','Incomplete') | Grade result |
| `status` | ENUM('draft','submitted') | Grade status |
| `created_at` | TIMESTAMP | Date created |
| `updated_at` | TIMESTAMP | Date updated |

## Suggested UI Color Plan

### Color Ratio
- **70% White:** main background, forms, tables, cards
- **20% Blue:** sidebar, headers, primary buttons, active states
- **10% Gray/Green:** borders, secondary buttons, success badges, report highlights

### Suggested Palette
- Main background: `#FFFFFF`
- Secondary background: `#F8FAFC`
- Primary blue: `#2563EB`
- Dark text: `#1F2937`
- Muted gray: `#6B7280`
- Border gray: `#E5E7EB`
- Success green: `#16A34A`
- Warning red: `#DC2626`

## Suggested Pages

### Admin Pages
- Login
- Admin dashboard
- Manage users
- Manage students
- Manage teachers
- Manage subjects
- Manage classes/sections
- Assign teachers and subjects
- View grade reports

### Teacher Pages
- Login
- Teacher dashboard
- Assigned classes
- Grade encoding page
- Grade update page
- Subject grade summary

### Student Pages
- Login
- Student dashboard
- View grades
- Academic record/history

## Test Plan

- Verify login works for admin, teacher, and student roles.
- Verify inactive users cannot log in.
- Verify admin can create and update users, students, teachers, subjects, and classes.
- Verify teachers can only access assigned classes and subjects.
- Verify grade computation is correct.
- Verify students can only view their own grades.
- Verify reports filter correctly by class, subject, teacher, student, school year, and term.
- Verify required fields and invalid grade values are properly validated.

## Assumptions
- The system will be built as a web application using plain PHP or PHP with a simple MVC structure.
- MySQL will be used as the centralized database.
- Grades use a numeric scale where a passing grade can be configured, with `75` as the default passing grade.
- PDF or Excel export is optional unless required later.
- The first version focuses on core grading workflows, not payment, enrollment, messaging, or learning management features.
