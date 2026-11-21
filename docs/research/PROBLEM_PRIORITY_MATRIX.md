# PHASE 1 — PROBLEM PRIORITIZATION MATRIX
**Systematic Scoring & Ranking of Legal Education Hypotheses**
*Date: November 20, 2026*

---

## 1. SCORING METHODOLOGY

Each candidate problem is evaluated on a 1 to 5 scale across seven weighted dimensions:

1. **Frequency (F):** How often does the user encounter this problem? (1 = Rare, 5 = Daily/Weekly during academic periods).
2. **Severity (S):** What is the pain level or professional consequence? (1 = Minor inconvenience, 5 = Failure to pass bar/inability to practice).
3. **Affected User Volume (V):** What proportion of our market experiences this? (1 = Niche subset, 5 = >80% of all students/instructors).
4. **Lack of Good Alternatives (A):** How inadequate are current solutions? (1 = Great alternatives exist, 5 = Completely unserved).
5. **Willingness to Pay (WTP):** Likelihood of budget allocation (student personal or university institutional)? (1 = None, 5 = High urgency).
6. **Educational Impact (EI):** Potential to transform learning outcomes? (1 = Marginal, 5 = Foundational practical competence).
7. **Technical Feasibility (TF):** Feasibility within our existing Laravel/Babylon architecture? (1 = Extremely high complexity/R&D, 5 = High feasibility/already architected).

$$\text{Composite Score} = (F \times 1.0) + (S \times 1.5) + (V \times 1.0) + (A \times 1.2) + (\text{WTP} \times 1.3) + (\text{EI} \times 1.5) + (\text{TF} \times 1.0)$$

---

## 2. EVALUATION MATRIX

| ID | Hypothesis / Problem Statement | F (1-5) | S (1-5) | V (1-5) | A (1-5) | WTP (1-5) | EI (1-5) | TF (1-5) | Total Score | Priority Tier |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **P1** | **Physical Courtroom & Simulation Bottleneck:** Universities have only 1–2 physical moot rooms for thousands of students; mock trials happen 1–2 times per student career. | 4 | 5 | 5 | 5 | 5 | 5 | 5 | **42.0** | **P0 (Critical)** |
| **P2** | **Instructor Grading & Session Management Overhead:** Managing 30 students in practical sessions and evaluating evidence/arguments manually takes hours, making exercises unscalable. | 5 | 4 | 5 | 4 | 4 | 5 | 5 | **39.0** | **P0 (Critical)** |
| **P3** | **Inability to Analyze Documentary Evidence & Facts:** Students know statutory articles by heart but fail when given messy, contradictory contracts, invoices, and forensic records. | 4 | 5 | 5 | 4 | 4 | 5 | 4 | **38.5** | **P0 (Critical)** |
| **P4** | **Lack of Objective Progress Evaluation:** Students and teachers cannot measure whether a student's practical advocacy and reasoning skills are improving over successive attempts. | 4 | 4 | 5 | 5 | 4 | 4 | 4 | **35.2** | **P1 (High)** |
| **P5** | **Limited Real-World Legal Task Practice During Degree:** Academic seminars focus on theoretical recitation rather than drafting, objecting, cross-examining, or negotiating. | 5 | 4 | 5 | 3 | 3 | 5 | 4 | **35.1** | **P1 (High)** |
| **P6** | **Passive, Observational University Internships:** Third- and fourth-year internships at courts and firms relegate students to clerical filing rather than practical case handling. | 3 | 4 | 5 | 4 | 4 | 4 | 4 | **33.0** | **P1 (High)** |
| **P7** | **Inconsistent Individual Student Feedback:** Students submit papers or argue cases but receive only a simple grade number without granular rubric-based feedback. | 4 | 4 | 4 | 4 | 3 | 4 | 4 | **32.7** | **P1 (High)** |
| **P8** | **Lack of Practice with Client Interviews & Intakes:** Students do not practice eliciting facts from emotional, confusing, or uncooperative clients. | 3 | 3 | 4 | 4 | 3 | 4 | 3 | **27.6** | **P2 (Medium)** |
| **P9** | **Insufficient Exposure to Commercial Negotiation & ADR:** Students graduate without exposure to pre-trial settlement, mediation, and counterparty bargaining. | 3 | 3 | 4 | 4 | 3 | 4 | 3 | **27.6** | **P2 (Medium)** |
| **P10**| **Disconnect Between Employer Expectations & Graduate Skills:** Employers spend months retraining fresh law hires on basic procedural workflows and contract review. | 4 | 4 | 4 | 3 | 3 | 4 | 3 | **30.4** | **P1 (High)** |

---

## 3. PRIORITIZED PROBLEM CLUSTERS

### Tier 1: The Core Foundation (Address Immediately in Phase 4–7)
1. **The Scalability & Access Crisis (P1 & P2):** Replace the physical room bottleneck with virtual solo and multiplayer courtrooms; automate role assignments and provide automated draft scoring to relieve instructor workload.
2. **Evidence Analysis & Procedural Reasoning (P3 & P5):** Integrate dynamic evidence inspection (transcripts, contracts, photos, forensic reports) directly into every case exercise.

### Tier 2: Systematic Evaluation & Transparency (Phase 6–8)
3. **Objective Competency Tracking (P4 & P7):** Enable attempt-over-attempt comparison rubrics so students and faculty track skill growth in quantifiable terms.
4. **Bridging the Graduate Employment Gap (P10 & P6):** Deliver recognized verified practical certificates based on simulation performance.

### Tier 3: Future Modular Expansion (Post-MVP / Phase 15)
5. **Client Intake & Commercial Negotiation (P8 & P9):** Build dedicated Client Interview and Commercial Negotiation modules once courtroom and core case analysis are validated with university cohorts.
