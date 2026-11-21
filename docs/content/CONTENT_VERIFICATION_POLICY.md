# PHASE 8 — CONTENT VERIFICATION & LEGAL INTEGRITY POLICY
**Standards for Fact Verification, Legal Accuracy & Curriculum Governance**
*Date: November 20, 2026*

---

## 1. MANDATORY VERIFICATION CRITERIA

Before any legal scenario, case analysis, or course module can transition from `draft` to `published` in the production catalog, it must satisfy five mandatory verification gates:

```
[1. Jurisdictional Accuracy] -> [2. LexUZ Validation] -> [3. Evidentiary Balance] -> [4. Ethical Review] -> [5. Approval & Sign-Off]
```

1. **Jurisdictional Accuracy Gate:** The scenario must apply exclusively to the valid laws of Uzbekistan. Common law procedural terms or foreign court structures are prohibited.
2. **LexUZ Statutory Validation Gate:** Every statutory reference must correspond to an active, unrepealed article on LexUZ. If an article was amended, the scenario must reflect the amended text or explicitly state the historical period under study.
3. **Evidentiary Balance Gate:** The case facts and exhibits must provide viable legal arguments for **both** opposing sides. A scenario where one side has a 100% indisputable victory lacks pedagogical and adversarial training value.
4. **Ethical & Fictional Disclaimer Gate:** The scenario must not use real names of living individuals, active commercial enterprises, or pending real-world court cases without explicit written authorization.
5. **Approval Sign-Off Gate:** The scenario record must store the reviewing instructor's user ID (`approved_by`), timestamp (`approved_at`), and a cryptographic SHA-256 hash snapshot of the approved text (`snapshot_hash`).

---

## 2. INSTRUCTOR CUSTOMIZATION & ACADEMIC FREEDOM

- University professors have full authority to clone existing published scenarios (`AdminCourtroomController@duplicate`) to adjust fact patterns, introduce customized local evidentiary exhibits, or align with specific seminar exam questions.
- Cloned scenarios remain in private `draft` status until the professor reviews and publishes them for their own student groups.
