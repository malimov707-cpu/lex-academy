# PHASE 5 — MULTI-USER SIMULATION TEST CASES & PROTOCOLS
**Standardized Test Specifications for Classroom Multi-Role Trial Sessions**
*Date: November 20, 2026*

---

## 1. TEST CASE SPECIFICATIONS

### TC-MP-01: Two-Participant Baseline (Facilitator + 1 Student)
- **Objective:** Verify session initialization, role mapping, and basic action ingestion.
- **Participants:**
  - Facilitator (`teacher@huquq.ai` on Teacher Laptop)
  - Student (`student@huquq.ai` assigned as `judge` on Client Laptop)
- **Steps:**
  1. Facilitator creates group session for Cohort Group A with Civil Contract scenario.
  2. Student navigates to `/virtual-courtroom/groups`, sees active session, clicks **Qatnashish** (Attend).
  3. Facilitator initiates stage `COURT_OPENING`.
  4. Student submits procedural statement (*"Sud majlisi ochiq deb e'lon qilinadi"*).
- **Expected Outcome:**
  - Action recorded with unique UUID idempotency key; turn created in `session_turns`; facilitator and student views update feed within 2 seconds.

---

### TC-MP-02: Three-Participant Adversarial Flow (Teacher + Prosecutor + Defense)
- **Objective:** Verify turn-taking, evidence presentation, and stage boundary enforcement.
- **Participants:**
  - Facilitator (`teacher@huquq.ai`)
  - Prosecutor (`student@huquq.ai`)
  - Defense Advocate (`salom@gmail.com`)
- **Steps:**
  1. Facilitator advances session stage to `EVIDENCE_PRESENTATION`.
  2. Prosecutor opens Evidence Board, selects Contract Exhibit `A-1`, adds annotation on Clause 4.2, and clicks **Dalilni taqdim etish**.
  3. Defense Advocate views the newly admitted exhibit in their live feed.
  4. Defense Advocate submits an objection: **E'tiroz bildirish** (*"Ushbu dalil asl nusxa emas, fotonusxa"*).
- **Expected Outcome:**
  - Both statements appear in sequential chronological order with speaker role tags (`prosecutor`, `defense_advocate`); no race condition or data overwriting occurs.

---

### TC-MP-03: Five-to-Ten Participant Cohort (Judge + 2 Advocates + 2 Witnesses + Observers)
- **Objective:** Verify high-concurrency feed polling and observer task completion.
- **Participants:**
  - Judge, Prosecutor, Defense Advocate, Witness 1, Witness 2, 5 Observers.
- **Steps:**
  1. Facilitator advances stage to `WITNESS_EXAMINATION`.
  2. Prosecutor executes action `ask_question` directed to Witness 1.
  3. Witness 1 executes action `answer_question`.
  4. Simultaneously, all 5 Observers execute `identify_error` and `observe_stage` tasks.
- **Expected Outcome:**
  - Server processes all 7 concurrent HTTP requests cleanly via `DB::transaction()` and row locking; all client feeds receive new turns without dropped packets or database lock deadlocks.

---

### TC-MP-04: Network Disconnection & Mid-Trial Reconnection Recovery
- **Objective:** Verify session state persistence upon unexpected Wi-Fi drop.
- **Participants:**
  - Defense Advocate on Client Laptop.
- **Steps:**
  1. Defense Advocate actively participates in `CROSS_EXAMINATION`.
  2. Disable Wi-Fi adapter on the Defense laptop for 45 seconds while session continues.
  3. Re-enable Wi-Fi and refresh browser at `/virtual-courtroom/group-sessions/{id}`.
- **Expected Outcome:**
  - Participant status remains authenticated; browser retrieves full chronological history from `/feed?after=0`; client state machine smoothly resynchronizes with the facilitator's current stage.

---

### TC-MP-05: Security & Out-of-Turn Action Rejection
- **Objective:** Prevent students from executing unauthorized actions or acting during the wrong stage.
- **Steps:**
  1. While session is in `OPENING_STATEMENTS`, a student attempts to submit a `final_decision` or execute an action with `stage_key: 'VERDICT'`.
  2. A student attempts to submit an action with an empty or duplicate `idempotency_key`.
- **Expected Outcome:**
  - Server intercepts invalid stage mismatch and aborts with `HTTP 409 Conflict` (*"Sessiya bosqichi o‘zgardi. Sahifani yangilang"*).
  - Duplicate idempotency keys are safely deduplicated without creating duplicate turns.

---

### TC-MP-06: Session Conclusion & Automated Group Scoring
- **Objective:** Verify transition from active trial to group score generation.
- **Steps:**
  1. Facilitator triggers `complete` stage transition via `/group-sessions/{id}/complete`.
  2. `GroupAssessmentWorkflow` aggregates all individual student actions, evidence citations, and objections.
  3. Facilitator reviews rubric scores at `/virtual-courtroom/sessions/{id}/results`.
- **Expected Outcome:**
  - Session status updates to `completed`; individual criterion scores are computed; team scores (Prosecution vs. Defense) calculate outcome (e.g., `tie`, `prosecution`, or `defense`); grades write to `courtroom_participant_grades`.

---

## 2. AUTOMATED REGRESSION SUITE REFERENCE

All core mechanics in these multi-user specifications are continuously verified in the project test suite:
- `tests/Feature/GroupCourtroomParticipationTest.php`
- `tests/Feature/GroupAssessmentWorkflowTest.php`
- `tests/Feature/GroupScoringEngineTest.php`
- `tests/Feature/GroupCourtroomResultsTest.php`
