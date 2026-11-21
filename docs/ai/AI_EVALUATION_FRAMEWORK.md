# PHASE 6 — AI EVALUATION FRAMEWORK
**Multi-Dimensional Competency Rubric, Scoring Criteria & Human Authority**
*Date: November 20, 2026*

---

## 1. MULTI-DIMENSIONAL EVALUATION RUBRIC

Student simulation actions and case analyses are evaluated across seven core competencies:

| Dimension | Description & Evaluative Standard | Weight (%) | Scoring Scale |
|---|---|:---:|:---:|
| **1. Fact Identification** | Ability to extract material facts from case exhibits and distinguish admissible facts from hearsay. | 15% | 0 – 100 pts |
| **2. Statutory Relevance & Accuracy** | Correct identification and citation of governing Uzbek statutory articles (e.g., Civil Code, Criminal Code). | 20% | 0 – 100 pts |
| **3. Evidentiary Application** | Tying factual claims directly to documentary exhibits or witness testimony with precise annotations. | 20% | 0 – 100 pts |
| **4. Argument Structure & Logic** | Clear, coherent IRAC structure (Issue $\rightarrow$ Rule $\rightarrow$ Application $\rightarrow$ Conclusion) avoiding logical fallacies. | 15% | 0 – 100 pts |
| **5. Procedural Fluency** | Observance of procedural trial stages, timely objections, and proper courtroom advocacy decorum. | 15% | 0 – 100 pts |
| **6. Professional Communication** | Professional vocabulary, precision of speech, lack of colloquialisms, and persuasive oral/written delivery. | 10% | 0 – 100 pts |
| **7. Legal Ethics & Decorum** | Honesty to the tribunal, respect toward the counterparty, and avoidance of misleading statements. | 5% | 0 – 100 pts |

---

## 2. ATTEMPT-OVER-ATTEMPT PROGRESSION TRACKING

To fulfill the core requirement of demonstrating measurable student competency growth:
- Every simulation record stores the student's **Attempt 1 vs. Attempt 2** delta.
- The reporting engine (`CourtroomPilotReport`) calculates individual and cohort-wide improvements across each of the seven dimensions:
  $$\Delta_{\text{Competency}} = \text{Score}_{\text{Attempt 2}} - \text{Score}_{\text{Attempt 1}}$$
- Students with $\Delta > +15\%$ earn the *Demonstrated Practical Competency* badge on their course certificate.

---

## 3. HUMAN OVERRIDE & TRANSPARENCY PRINCIPLE

1. **AI As Assistant, Not Arbiter:** Any AI-generated diagnostic score is explicitly presented as an *Advisory Preliminary Evaluation* (*Tavsiyaviy dastlabki baho*).
2. **Instructor Finality:** The human instructor retains the absolute capability to adjust, override, or replace any score in the gradebook.
3. **Audit Trail:** In accordance with university standards, whenever an instructor modifies an AI diagnostic score, the delta and instructor comment are recorded in `education_audit_logs`.
