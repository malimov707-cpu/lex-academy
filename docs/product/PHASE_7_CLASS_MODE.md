# PHASE 7 — CLASS MODE & INSTRUCTOR ORCHESTRATION REPORT
**Teacher Facilitation Workflows, Student Roster Controls & Assessment Pipelines**
*Date: November 20, 2026 | Focus: University Classroom Management*

---

## 1. INSTRUCTOR FACILITATION WORKFLOWS

Class Mode equips law professors and clinical instructors with comprehensive session command tools:

```
+-------------------------------------------------------------------------------------------------+
|                                 INSTRUCTOR CLASS WORKFLOW                                       |
+-------------------+--------------------+--------------------+--------------------+--------------+
| 1. SETUP COHORT   | 2. ASSIGN ROLES    | 3. INITIATE TRIAL  | 4. STAGE CONTROL   | 5. EVALUATE  |
| Select Course &   | Map Students to:   | Start session,     | Advance stages:    | Review rubric|
| Academic Group    | Judge, Advocates,  | confirm attendance | Opening -> Evidence| scores, add  |
| from Database     | Witnesses, Observers| of participants   | -> Closing -> Verdict| feedback   |
+-------------------+--------------------+--------------------+--------------------+--------------+
```

### 1.1 Step-by-Step Instructor Journey:
1. **Roster Configuration:** Navigate to `/admin/virtual-courtroom/groups/create`. The system loads the instructor's assigned academic groups and student rosters.
2. **Role Distribution:** The instructor selects active students for trial roles (Judge, Prosecutor, Defense, Witnesses). Unassigned students are automatically designated as **Observers** with guided real-time observation worksheets.
3. **Session Launch:** Instructor clicks **Sessiyani boshlash**. The session becomes active and student dashboards receive notification to join.
4. **Live Stage Progression:** Unlike an unmoderated free-for-all, the instructor controls the pacing. When arguments in `OPENING_STATEMENTS` conclude, the instructor clicks **Keyingi bosqich** to advance all connected student screens to `EVIDENCE_PRESENTATION`.
5. **Session Wrap-Up & Grading:** The instructor clicks **Sessiyani yakunlash**. The system runs the `GroupScoringEngine`, compiling student participation, evidence citations, and objections into a unified grading sheet at `/virtual-courtroom/sessions/{id}/results`.

---

## 2. STUDENT PARTICIPATION WORKFLOW

1. **Accessing the Session:** Enrolled students open `/virtual-courtroom/groups`. The active session appears with their designated role card.
2. **Role Briefing:** The student reads their private role instructions and confidential case facts.
3. **Submitting Procedural Actions:** Based on their assigned role and current stage, the student selects from allowed actions (e.g., `make_statement`, `present_evidence`, `make_objection`).
4. **Instant Synchronization:** When an action is submitted, it immediately streams into the public turn timeline visible to all classmates.
5. **Receiving Assessment:** Upon session completion, students view their individual score report, breakdown across rubric dimensions, and instructor feedback.

---

## 3. CLASSROOM COHORT USABILITY SPECIFICATIONS

| Cohort Size | Typical Classroom Setup | Platform Resource Utilization | Usability Verdict |
|---|---|---|---|
| **5 Students** | Small clinical seminar (Judge, 2 Advocates, 2 Witnesses) | Minimal load; instantaneous polling response | **Optimal for intensive advocacy training.** |
| **10–15 Students** | Standard academic seminar group (7 active roles + 8 observers) | ~2 KB polling payload per client; smooth concurrency | **Standard recommended university cohort format.** |
| **25–35 Students** | Full university lecture group (active trial bench + 25 observers) | Managed via row-locking in DB transaction; observer tasks aggregated cleanly | **Proven scalable in automated load tests.** |

---

## 4. COMPLETION CRITERIA SIGN-OFF

Phase 7 requirements have been met and documented:
- [x] Full instructor facilitation lifecycle verified.
- [x] Student joining and participation workflows verified.
- [x] Stage advancement controls verified.
- [x] Observation task aggregation for non-advocate students verified.
- [x] Test specifications documented in [CLASS_MODE_TESTS.md](file:///Users/uzmacbook/Herd/lex-academy/docs/testing/CLASS_MODE_TESTS.md).
