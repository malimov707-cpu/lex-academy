# PHASE 10 — UX IMPROVEMENT SPECIFICATION & DESIGN PATTERNS
**Design Standards, Microinteraction Rules & Accessibility Guidelines**
*Date: November 20, 2026*

---

## 1. ACCESSIBILITY & WCAG 2.1 AA COMPLIANCE

1. **Contrast Ratio Standards:** All text elements adhere to minimum 4.5:1 contrast against surface backgrounds (verified across both Light and Dark themes).
2. **Keyboard Navigation & Focus Rings:**
   - Every interactive element (buttons, links, inputs, dialog triggers) displays an explicit `:focus-visible` golden ring.
   - Global `.edu-skip-link` enables keyboard users to bypass navigation and jump straight to main content.
3. **Screen Reader Semantic Tags:**
   - Alerts and error summaries use `role="alert"`.
   - Polling feeds and live courtroom turn updates use `role="status" aria-live="polite"`.
   - Decorative icons carry `aria-hidden="true"`.
4. **Motion Sensitivity:**
   - Respects user OS preference `@media (prefers-reduced-motion: reduce)`, disabling non-essential transition animations.

---

## 2. COMPONENT DESIGN PATTERNS

- **`.edu-card` / `.edu-panel`:** Rounded container (radius: 12px) with subtle border and crisp elevation shadow.
- **`.edu-btn-hero`:** High-contrast CTA button guaranteed legible against both navy gradient banners and white backgrounds.
- **`.edu-badge`:** Five semantic status states (`success`, `warning`, `info`, `danger`, `neutral`) with dedicated dark mode color shifts.
