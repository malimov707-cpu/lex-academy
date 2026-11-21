# HUQUQ AI ACADEMY — DATABASE AUDIT
**Date:** 2026-11-20  
**Storage Driver:** SQLite (`database/database.sqlite`)  
**Safety Protocol:** Non-destructive migrations, zero data loss, backup created at `database/backups/`.

---

## 1. Baseline Schema & Existing Tables

The database currently manages 64 application models and corresponding tables:
1. `users`: 6 accounts (`super_admin`, `academy_admin`, `teacher`, `mentor`, `student`).
2. `courses`, `course_modules`, `lessons`, `lesson_blocks`, `lesson_materials`: Structured legal curriculum.
3. `quizzes`, `questions`, `question_options`, `quiz_attempts`, `quiz_answers`: Test examination subsystem.
4. `assignments`, `assignment_submissions`, `submission_files`: Written homework and grading.
5. `simulation_scenarios`, `scenario_versions`, `scenario_roles`, `scenario_stages`, `scenario_evidence`, `scenario_action_rules`, `scenario_legal_references`: Simulation scenario authoring.
6. `simulation_sessions`, `session_participants`, `session_turns`, `session_events`, `session_actions`: Virtual courtroom execution engine.
7. `courtroom_participant_grades`, `gradebook_entries`: Assessment rubrics and teacher gradebook.
8. `library_resources`, `education_categories`, `library_bookmarks`: Legal reference repository.
9. `education_groups`, `class_schedules`: Academic cohort scheduling and management.
10. `education_audit_logs`: Detailed compliance audit trail.

---

## 2. Integrity Verification

- **Foreign Keys & Constraints:** Checked and verified for courses, lessons, simulation sessions, and enrollments.
- **Data Preservation:** Zero deletion of existing records. Demo data will be flagged using deterministic identifiers (`demo.student.XXX@example.test`) and `is_demo` flags.
- **Idempotency Requirement:** `DemoAcademySeeder` must be strictly idempotent to avoid duplicate records upon multiple runs.
