# PHASE 7 — CLASS MODE TEST SPECIFICATIONS
**Automated & Manual Test Matrix for Instructor Facilitation & Cohort Orchestration**
*Date: November 20, 2026*

---

## 1. AUTOMATED TEST SUITE MAPPING

The following automated Pest feature tests verify Class Mode functionality:

| Test File | Key Assertions & Scenarios Tested |
|---|---|
| `tests/Feature/GroupCourtroomParticipationTest.php` | - Student sees assigned scenario in group courtroom directory.<br>- 3D and 2D courtroom shells render with correct student name and role.<br>- Role boundaries prohibit students from performing actions assigned to other roles. |
| `tests/Feature/GroupAssessmentWorkflowTest.php` | - Teacher initializes assessment rubric for active cohort.<br>- Individual scores compile from evidence presentation, objections, and statements.<br>- Teacher manual grades override automatic draft calculations. |
| `tests/Feature/GroupScoringEngineTest.php` | - Evaluates team outcomes (Prosecution vs. Defense) based on admissible evidence scores.<br>- Handles tie outcomes, team margins, and undetermined states when evidence is missing. |
| `tests/Feature/GroupCourtroomResultsTest.php` | - Teacher approves, revises, and returns courtroom evaluation results.<br>- Gradebook entries write to student learning profiles. |

---

## 2. MANUAL VERIFICATION PROTOCOL (SEMINAR REHEARSAL)

To execute an end-to-end classroom verification rehearsal with an instructor and students:

1. **Step 1: Setup Class Group**
   - Log in as `teacher@huquq.ai`.
   - Ensure an active group exists under the teacher's course with at least 3 students.
2. **Step 2: Initialize Courtroom Session**
   - Navigate to `/admin/virtual-courtroom/groups/create`.
   - Select group, choose *"Shartnoma majburiyati bo‘yicha o‘quv sudi"*, assign Student A as Judge, Student B as Prosecutor, Student C as Defense.
   - Click **Yaratish** (Create).
3. **Step 3: Student Joins**
   - In separate browser windows, log in as Student A, Student B, and Student C.
   - Each student opens `/virtual-courtroom/groups` and clicks **Qatnashish**.
4. **Step 4: Trial Advancement**
   - Teacher advances stage from `PREPARATION` to `COURT_OPENING`.
   - Verify that all 3 student screens display stage change within 2.5 seconds.
5. **Step 5: Completion & Scoring**
   - Teacher clicks **Sessiyani yakunlash**.
   - Verify that teacher is redirected to `/sessions/{id}/results` and student results are properly tabulated.
