# PHASE 4 — BUG TRACKER & DEFECT LOG
**Comprehensive Issue Tracking, Severity Tiers, Root Causes & Verification**
*Date: November 20, 2026*

---

## 1. DEFECT SEVERITY DEFINITIONS

- **P0 (Critical):** Application crash, data loss, security bypass, or primary user workflow completely blocked.
- **P1 (High):** Significant workflow impairment affecting key educational features (e.g., grading, session transitions).
- **P2 (Medium):** Usability, reporting inaccuracies, or layout defects under specific conditions.
- **P3 (Low):** Minor cosmetic blemishes, static text discrepancies, or edge-case inconsistencies.

---

## 2. DEFECT LOG & RESOLUTION MATRIX

| Issue ID | Severity | Component | Defect Description | Root Cause | Status | Verification Evidence |
|---|:---:|---|---|---|:---:|---|
| **BUG-001** | **P1** | Admin Control Center | Session activity log always labeled sessions as *"Virtual sud sessiyasi yakunlandi"*, even when newly created or currently active. | Hardcoded string in `DashboardController::controlCenterActivity()` iterating through `SimulationSession::latest()`. | **RESOLVED & VERIFIED** | `match($session->status)` implemented; tested via `tests/Feature/ControlCenterMetricsTest.php`. |
| **BUG-002** | **P2** | Admin Control Center | KPI trend percentages were hardcoded (`+12.5%`, `+15.3%`, `+8.1%`, `+22.7%`) rather than calculated from real historical DB records. | Static trend float values in `controlCenterKpis()` array. | **RESOLVED & VERIFIED** | Dynamic 30-day vs. previous 30-day period delta calculation implemented with real DB counts. |
| **BUG-003** | **P2** | Admin Control Center | Sparkline SVG paths were generated using pseudo-random modulo formula rather than actual historical time-bucketed counts. | Synthetic `$spark = fn ($seed) => ...` formula. | **RESOLVED & VERIFIED** | 12-interval rolling time-window count aggregator implemented (`sparkFor()`). |
| **BUG-004** | **P2** | Admin Control Center | AI Mentor module health was hardcoded to `96%` regardless of whether AI tutor sessions existed or had messages. | Hardcoded `$aiMentor = 96` in `controlCenterModules()`. | **RESOLVED & VERIFIED** | Replaced with query assessing percentage of active student sessions utilizing AI tutor messages. |
| **BUG-005** | **P1** | Translations (i18n) | Missing JSON bracket in Cyrillic locale caused occasional HTTP 500 on admin page render in previous iteration. | Unescaped replacement placeholder in `lang/uz-cyr.json`. | **RESOLVED & VERIFIED** | All 3 translation catalogs verified; tests passing across all routes. |
| **BUG-006** | **P2** | Mobile Viewports | Courtroom administration table overflowed 116px on narrow mobile viewports (390px). | Missing overflow-x containment wrapper on admin tables. | **RESOLVED & VERIFIED** | Fixed via `.edu-table` responsive wrapper styling in `resources/css/app.css`. |
| **BUG-007** | **P2** | AI Driver Integration | External OpenAI API calls return HTTP 429 quota exhaustion when credits expire. | Third-party billing constraint on OpenAI organization key. | **RESOLVED WITH GRACEFUL FALLBACK** | Handled gracefully with explicit 503 status code and human-readable diagnostics; fake outputs prohibited. |
