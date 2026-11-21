# PHASE 3 — PRODUCT ARCHITECTURE & MODULE STRATEGY
**Coherent Platform Architecture, Core Modes & Future Competency Modules**
*Date: November 20, 2026 | Framework: Laravel 13 on PHP 8.4*

---

## 1. ARCHITECTURAL FOUNDATION & SYSTEM TOPOLOGY

Lex Academy operates on a modular, service-oriented Laravel architecture designed to preserve existing implementations while establishing clean extension points for future legal competency modules.

```
+---------------------------------------------------------------------------------------------------------+
|                                           PRESENTATION LAYER                                            |
|   Blade Templates  *  Tailwind CSS v4 (edu-*)  *  Babylon.js 9.37 3D Canvas  *  Responsive 2D DOM Shell  |
+----------------------------------------------------+----------------------------------------------------+
                                                     |
                                                     v
+----------------------------------------------------+----------------------------------------------------+
|                                      HTTP & ROUTING GATEWAYS                                            |
|   Web Authenticated Routes  *  Session Security  *  CSRF  *  Rate Limiters  *  EnsureRole Middleware   |
+----------------------------------------------------+----------------------------------------------------+
                                                     |
                                                     v
+---------------------------------------------------------------------------------------------------------+
|                                         CORE ENGINE & MODES                                             |
|                                                                                                         |
|  [AI MODE]                              [CLASS MODE]                           [COURT MODE]             |
|  - Socratic Tutor Service               - Course & Curriculum Manager          - Simulation State Machine|
|  - AiDriverManager (OpenAI / vLLM)      - Group Session Facilitator            - Scenario & Version Tree|
|  - TutorContextBuilder                  - 10-Role Roster Engine                - Evidence & Annotation  |
|  - Guided Case Reasoning Engine         - Gradebook & Assessment Rubrics       - Speech STT/TTS Provider|
|                                         - Attendance & Schedule Tracker        - Multi-Attempt Evaluator|
+----------------------------------------------------+----------------------------------------------------+
                                                     |
                                                     v
+---------------------------------------------------------------------------------------------------------+
|                                    DOMAIN SERVICES & DATA ACCESS                                        |
|  - GroupCourtroomSession                - QuizGradingService                   - LibraryContentImporter |
|  - GroupScoringEngine                   - CertificateEligibility               - LegalDocumentExtractor |
|  - SessionEventRecorder (Append-Only)   - LearningAnalytics                    - EducationAccess Policy |
+----------------------------------------------------+----------------------------------------------------+
                                                     |
                                                     v
+---------------------------------------------------------------------------------------------------------+
|                                          PERSISTENCE LAYER                                              |
|            SQLite (Development / Testing)  -->  PostgreSQL 16+ / MySQL 8.0+ (Production)                |
|            Private Local File Storage (Evidence PDFs, Audio Transcripts, Student Submissions)           |
+---------------------------------------------------------------------------------------------------------+
```

---

## 2. EXISTING CORE MODES: ARCHITECTURE & IMPROVEMENTS

### 2.1 AI MODE
- **Current State:** Implemented via `App\Services\Ai\AiDriverManager`, `OpenAiCompatibleDriver`, and `TutorContextBuilder`. Routes exist at `/ai` and `/learn/courses/{course}/lessons/{lesson}/tutor`.
- **Pedagogical Workflow:** ASSESS $\rightarrow$ EXPLAIN $\rightarrow$ ASK $\rightarrow$ PRACTICE $\rightarrow$ EVALUATE $\rightarrow$ FEEDBACK.
- **Quota Resilience:** Gracefully catches HTTP 429 quota exhaustion and surfaces informative 503 guidance without generating fabricated answers.
- **Planned Enhancements:**
  - Add offline scenario-guided prompts when external AI services are disabled or offline.
  - Integrate statutory citations directly from the platform's local legal library into the AI system context.

### 2.2 CLASS MODE
- **Current State:** Comprehensive university management hub. Enables professors to build courses, organize modules, craft 11 types of lesson blocks, schedule class meetings, record attendance, deploy quizzes/exams, and manage groups.
- **Group Courtroom Integration:** Via `GroupCourtroomController` and `GroupCourtroomSession`, instructors can spin up classroom simulation sessions linked to student cohorts, assign 10 distinct roles, and monitor progress.
- **Planned Enhancements:**
  - Facilitator live broadcast panel (pause trial, issue judicial warnings, trigger emergency recess).
  - Bulk gradebook export formatted for university HEMIS compliance.

### 2.3 COURT MODE
- **Current State:** High-fidelity simulation engine supporting both solo runs against automated/AI actors and multi-user group sessions. Features 3D Babylon.js visuals with complete 2D responsive fallback, document evidence inspection, highlight annotations, voice STT/TTS bridges, and a 10-stage state machine:
  $$\text{PREPARATION} \rightarrow \text{OPENING} \rightarrow \text{ID} \rightarrow \text{STATEMENTS} \rightarrow \text{EVIDENCE} \rightarrow \text{WITNESS} \rightarrow \text{CROSS} \rightarrow \text{CLOSING} \rightarrow \text{VERDICT} \rightarrow \text{EVALUATION}$$
- **Architectural Mandate:** **PRESERVE AND IMPROVE. DO NOT REBUILD.** Maintain existing Babylon.js models, camera controls, and state transitions while refining multiplayer LAN synchronization and multi-device usability.

---

## 3. FUTURE PRACTICAL COMPETENCY MODULES: ROADMAP & SCOPE

To evolve from a courtroom simulator into a comprehensive **Legal Practice Learning and Simulation Platform**, seven potential modules are evaluated. Each module will be layered onto existing models without breaking architectural backward compatibility.

### Module A: Legal Case Analysis (*Kazus Tahlili*)
- **Status:** **Partially Implemented & Ready for Expansion** (`legal_cases`, `LegalCaseController`).
- **Core Workflow:** Students receive a raw case file, identify material facts, isolate legal issues, link governing statutes from the local library, develop IRAC (Issue, Rule, Application, Conclusion) arguments, and receive rubric-based scoring.
- **Architectural Fit:** Seamlessly utilizes existing `legal_cases`, `assignments`, and `mentor_reviews`.

### Module B: Client Interview & Fact-Gathering (*Mijoz Bilan Dastlabki Suhbat*)
- **Status:** **High Priority for Phase 2 Expansion**.
- **Core Workflow:** Simulated client consultation where the student must ask relevant procedural questions to extract disorganized facts, identify client objectives, recognize red flags, and manage difficult emotions within a time limit.
- **Architectural Fit:** Leverages existing `AiDriver` conversation models with constrained hidden personas.

### Module C: Contract Drafting & Clause Review (*Shartnoma Tuzish va Ekspertiza*)
- **Status:** **High Priority for Commercial Law Cohorts**.
- **Core Workflow:** Students receive a business term sheet and must draft clauses (e.g., limitation of liability, dispute resolution, liquidated damages) or review an adverse contract to highlight high-risk clauses and propose protective redlines.
- **Architectural Fit:** Builds on top of `assignments` and `library_resources` with side-by-side diff viewers.

### Module D: Commercial Negotiation & Mediation (*Muzokaralar va Mediasiya*)
- **Status:** **Medium Priority (Post-Pilot)**.
- **Core Workflow:** Multi-party negotiation exercise where two student teams (or student vs. AI) negotiate settlement terms based on confidential instructions and target reserve prices.

### Module E: Legal Research & Statutory Interpretation (*Huquqiy Axborot Qidiruvi*)
- **Status:** **Partially Implemented** (`LibraryController`, `LegalDocumentExtractor`).
- **Core Workflow:** Research tasks requiring students to resolve complex legal inquiries by querying the 51 local codes and laws, identifying applicable provisions, and explaining potential statutory conflicts.

### Module F: Legal Ethics & Professional Responsibility (*Kasb Etikasi va Mas'uliyat*)
- **Status:** **Medium Priority**.
- **Core Workflow:** Scenario-based dilemma drills covering advocate-client confidentiality, conflicts of interest, bribery solicitation resistance, and judicial decorum.

### Module G: Procedural Legal Writing (*Sud Hujjatlarini Yozish*)
- **Status:** **Partially Implemented via Assignments**.
- **Core Workflow:** Drafting formal procedural filings conforming to court standards: Statements of Claim (*Da'vo ariza*), Objections (*E'tiroznoma*), Complaints (*Shikoyat*), and Interlocutory Petitions (*Iltimosnoma*).
