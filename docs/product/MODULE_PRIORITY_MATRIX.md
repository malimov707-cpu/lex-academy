# PHASE 3 — MODULE PRIORITIZATION MATRIX
**Strategic Sequencing of Future Legal Competency Modules**
*Date: November 20, 2026*

---

## 1. EVALUATION CRITERIA

Each candidate competency module is scored from 1 (Low) to 5 (High) across five strategic dimensions:

1. **User & Employer Demand (UD):** How urgently do students, professors, and employers ask for this practical skill?
2. **Educational Impact (EI):** To what extent does this transform the student's transition to real legal practice?
3. **MVP Feasibility (MF):** How quickly and safely can this be built without destabilizing core code?
4. **Commercial / Business Value (BV):** Does this feature unlock institutional B2B licenses or B2C conversion?
5. **Existing Codebase Readiness (ER):** How much foundation already exists in our models, routes, and services?

$$\text{Priority Score} = (\text{UD} \times 1.2) + (\text{EI} \times 1.4) + (\text{MF} \times 1.0) + (\text{BV} \times 1.3) + (\text{ER} \times 1.1)$$

---

## 2. MODULE SCORING MATRIX

| Code | Module Candidate | UD (1-5) | EI (1-5) | MF (1-5) | BV (1-5) | ER (1-5) | Total Score | Implementation Phase |
|:---:|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Mod A** | **Legal Case Analysis (*Kazus Tahlili*)** | 5 | 5 | 5 | 5 | 5 | **30.0** | **Phase 4 (Immediate Focus)** |
| **Mod G** | **Procedural Legal Writing (*Protsessual Hujjatlar*)** | 5 | 5 | 4 | 5 | 4 | **27.9** | **Phase 4–6 (Immediate Focus)** |
| **Mod E** | **Legal Research & Statutory Search (*Qidiruv*)** | 4 | 4 | 4 | 4 | 5 | **25.1** | **Phase 6–8 (Core Integration)** |
| **Mod B** | **Client Interview Simulation (*Mijoz Suhbat*)** | 4 | 5 | 3 | 4 | 3 | **23.3** | **Phase 15 (Post-Pilot v1.2)** |
| **Mod C** | **Contract Drafting & Clause Review (*Shartnoma*)** | 5 | 4 | 3 | 4 | 2 | **23.0** | **Phase 15 (Post-Pilot v1.2)** |
| **Mod F** | **Legal Ethics & Dilemmas (*Kasb Etikasi*)** | 3 | 4 | 4 | 3 | 3 | **20.4** | **Phase 15 (Post-Pilot v1.3)** |
| **Mod D** | **Commercial Negotiation & Mediation (*Muzokara*)** | 3 | 4 | 2 | 3 | 2 | **17.7** | **Future Expansion (v2.0)** |

---

## 3. STRATEGIC SEQUENCING & DECISIONS

### Tier 1: Build & Polish Now (MVP & University Pilot Scope)
1. **Module A (Legal Case Analysis):** Leverage existing `legal_cases`, `LegalCaseController`, and `LegalCaseFeedback` to offer rich, structured case briefs.
2. **Module G (Procedural Legal Writing):** Connect `assignments` directly to Court Mode, enabling students to submit pre-trial claims (*da'vo ariza*) as required prerequisites before entering the courtroom simulation.
3. **Module E (Legal Research):** Fully activate the 51 local library resources (`/library`) as searchable references inside scenario briefs and courtroom evidence tabs.

### Tier 2: Post-Pilot Horizon (Next Product Version)
- **Module B (Client Interview):** Introduce single-player conversational client intake exercises using constrained AI roleplay agents.
- **Module C (Contract Drafting):** Introduce redline comparison tools for corporate law courses.
- **Module F (Legal Ethics):** Introduce decision-tree ethical dilemma drills for bar candidates.
