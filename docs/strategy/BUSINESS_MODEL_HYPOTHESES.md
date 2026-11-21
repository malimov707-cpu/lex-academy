# PHASE 2 — BUSINESS MODEL HYPOTHESES & COMMERCIAL ARCHITECTURE
**Revenue Mechanics, Pricing Hypotheses, Customer Acquisition & Cost Structures**
*Date: November 20, 2026*

---

## 1. REVENUE HYPOTHESES OVERVIEW

Lex Academy operates a **Hybrid B2B / B2C Model**, prioritizing institutional B2B enterprise contracts with law faculties as the primary revenue engine, supplemented by high-margin B2C premium student subscriptions.

```
+------------------------------------------------------------------------------------+
|                                REVENUE STREAMS                                     |
+------------------------------------------+-----------------------------------------+
|        B2B INSTITUTIONAL (80%)           |           B2C INDIVIDUAL (20%)          |
|  - Annual University Site License        |  - Student Self-Paced Solo Subscription |
|  - Per-Student Departmental Fee          |  - Bar Exam Candidate Prep Track        |
|  - Custom Scenario Development Contracts |  - Verified Professional Certificates   |
+------------------------------------------+-----------------------------------------+
```

---

## 2. B2B INSTITUTIONAL PRICING HYPOTHESES

### Tier 1: Departmental / Cohort Pilot License
- **Target:** Single law faculty department (e.g., Department of Civil Procedure or Criminal Procedure) or an experimental cohort of 200–500 students.
- **Hypothetical Pricing:** 30,000,000 – 50,000,000 UZS/year (~$2,400 – $4,000 USD/year).
- **Inclusions:**
  - Up to 500 active student accounts.
  - Unlimited Solo Courtroom & Classroom Group Sessions.
  - Core scenario library (Contract, Property, Labor, Criminal).
  - Teacher facilitation dashboard, auto-rubric grading, and CSV export.
  - On-premise / Local LAN deployment support.

### Tier 2: Full University Institutional License (*Fakultet Litsenziyasi*)
- **Target:** Whole law universities or law faculties (TSUL, UWED, SamSU Law Faculty) covering 2,000 – 6,000 students.
- **Hypothetical Pricing:** 120,000,000 – 250,000,000 UZS/year (~$9,500 – $20,000 USD/year).
- **Inclusions:**
  - Campus-wide student and instructor access.
  - HEMIS integration / bulk user synchronization.
  - Institutional learning analytics and accreditation reporting suite.
  - Custom university branding and scenario authoring workshops.
  - Dedicated pedagogical customer success manager.

---

## 3. B2C INDIVIDUAL SUBSCRIPTION HYPOTHESES

### Freemium Model:
- **Free Tier:** Access to 1 intro course, 2 standard case scenarios, the legal library, and community discussions.
- **Pro Student Tier:** 89,000 UZS/month (~$7 USD/mo) or 690,000 UZS/year:
  - Unlimited solo AI-assisted courtroom trial sessions.
  - Full scenario catalog (advanced economic disputes, forensic criminal cases).
  - Multi-attempt progress analytics and skill progression reports.
  - Official cryptographic competency certificate issuance.
- **Bar Candidate Track (*Advokatlikka Tayyorgarlik*):** 199,000 UZS/month:
  - Specialized qualification exam simulation scenarios.
  - In-depth evidentiary objection drills.
  - Chamber of Advocates rubric standards.

---

## 4. UNIT ECONOMICS & COST STRUCTURE HYPOTHESES

### Variable Costs per Active Simulation Session:
1. **AI API Usage:** For OpenAI-compatible LLM orchestration (Socratic prompting, opponent turns), token usage is bounded at ~1,500 tokens per full trial turn. Using cost-effective models (e.g., GPT-4o-mini or self-hosted Ollama/vLLM), cost per completed student trial is approximately **$0.01 – $0.03 (120 – 380 UZS)**.
2. **Bandwidth & Storage:** Private file streaming for exhibits and database polling generates negligible bandwidth (~2–5 MB per session).
3. **Gross Margin Estimate:** **85% – 92%** on institutional software licenses.

### Fixed Operational Costs:
- Core engineering & DevOps maintenance.
- Legal scenario authoring and academic verification (stipends for practicing advocates and professors).
- University customer success & institutional sales overhead.
- Local data center hosting compliance (Tashkent-based cloud server or on-premise university server).

---

## 5. REVENUE RISKS & MITIGATION STRATEGIES

| Risk | Likelihood | Impact | Mitigation Strategy |
|---|:---:|:---:|---|
| **Slow university procurement cycles (budget bureaucracy)** | High | High | Offer free 60-day semester pilots for 1–2 seminar groups; enable departments to fund via departmental innovation budgets before university-wide tenders. |
| **Student reluctance to pay B2C fees out-of-pocket** | Medium | Medium | Focus primary sales strategy on B2B institutional licenses (university pays on behalf of students). B2C acts as secondary upsell for career certifications. |
| **Currency fluctuation (UZS / USD)** | Medium | Low | Price all domestic institutional contracts in Uzbek Soums (UZS) with annual indexation clauses. |
