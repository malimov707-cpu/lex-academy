# LEGAL EDU (lex-academy) — UX/UI AUDIT VA QUR'ON AI MASTER BILAN TAQQOSLASH

Sana: 2026-11-16 · Holat: **tasdiqlash kutilmoqda** (kod o'zgartirilmagan)

---

## 1. QUR'ON AI MASTER — AUDIT

**Joylashuv:** `~/Herd/quran-master` (Laravel, Blade + custom CSS, 43+ view)
**Rol:** SUPER_ADMIN, CENTER_ADMIN, MENTOR, PARENT, STUDENT

### 1.1 Dizayn tizimi (asosiy kuch)

| Element | Qiymat / yechim |
|---|---|
| Sidebar | `242px` fixed, `linear-gradient(145deg,#064e3b,#052f28)`, oltin `#c6a15b88` border |
| Topbar | `app-topbar` — menu toggle, global search, locale `UZ/ЎЗ/RU`, theme toggle, notification badge, profile `<details>` popover |
| Brend zona | `brand` + `brand-copy` (nom + `small` shior) |
| **Nav guruhlash** | `nav-group-label` — «Asosiy / Ta'lim boshqaruvi / O'quv jarayoni / Tizim» bo'yicha ajratilgan |
| Nav link | icon + label, `is-active` + `aria-current="page"` |
| Sidebar footer | dekorativ nota + logout |
| Mobile ≤767px | sidebar `translateX(-100%)` → `.menu-open` bilan chiqadi, `drawer-scrim` (blur) |
| **a11y** | `skip-link`, `aria-label`, `aria-expanded`, `aria-controls`, `role="progressbar"` |
| Theme | `localStorage` + `prefers-color-scheme`, `[data-theme=dark]` selector |
| Feedback | `feedback feedback-error` / `feedback-success` + yopish tugmasi |
| Breadcrumb | `page-context` — «Qur'on AI Ustoz / Sahifa» + sana |
| Konfirmatsiya | native `<dialog>` + `data-confirm` (deklarativ) |

### 1.2 Dashboard tarkibi

1. **hero-banner** — gradient + oltin SVG naqsh, badge, `clamp()` sarlavha, CTA
2. **hero-goal glass panel** — kunlik maqsad + progress bar
3. **KPI kartalar** — 2×2 → 1024px da 4×1, 3D ikonkalar
4. **metric-card** — icon, trend `↗`, value, label, note
5. Asosiy grid: `1.55fr / .9fr` (kontent + yon panel)
6. **ai-mentor-card** — gradient karta, checklist, CTA
7. Vizual: glassmorphism (`glass-panel`), radius 18–26px, shadow tokenlari

### 1.3 UX yechimlari (ko'chirishga arziydigan)

- Guruhlangan nav (17 linkni 5 guruhga bo'lish)
- Global search topbar'da
- Notification badge (99+ cap)
- Profile popover (rol, email, logout)
- Skip-link va to'liq ARIA
- Breadcrumb + sana
- Deklarativ `data-confirm` dialog
- **Mobile drawer** to'g'ri yechimi (scrim + `overflow: hidden`)
- Lokalizatsiya dropdown'i topbar'da
- Klaviatura fokusi uchun `:focus` uslublari

### 1.4 Ko'chirilmaydigan (diniy/g'arbiy emas)

- Yashil-oltin palitra, arabcha shrift, ayah kartasi, prayer-times, «Ilm va amal sari» ohangi
- Ikonkalar (mushaf, oy), ornament belgilar

---

## 2. LEGAL EDU — AUDIT

**Joylashuv:** `~/Herd/lex-academy` (Laravel 13, Blade + Tailwind 4, **60 view**, **125+ route**)
**Rol:** SuperAdmin, AcademyAdmin, Teacher, Mentor, Student

### 2.1 Dizayn tizimi (mavjud)

| Element | Mavjud holat |
|---|---|
| Sidebar | `w-60`, oq fon, `lg:static` — **faqat 10 link, guruhsiz**, mobil versiyada toggle JS bilan |
| Topbar | Logo, locale select, theme tugmasi, ism+rol, logout |
| CSS | `app.css` — 37 qator; **hech qanday komponent klass yo'q, faqat inline Tailwind** |
| Palitra | Navy `#0f1e3d`, Royal Blue `#1d4ed8`, Ivory `#f7f5f0`, Gold yo'q |
| Radius | Tailwind default (8/12/16px), **`rounded-2xl` izchil ishlatilmaydi** |
| Dark theme | ✅ qo'shilgan (cookie + `dark:` variant), 13 view'da tuzatilgan |
| a11y | `sr-only` bir necha joyda; **skip-link YO'Q**, `aria-current` YO'Q |
| Feedback | `session('status')` banner — yopish tugmasi yo'q |
| Breadcrumb | ❌ yo'q |
| Global search | ❌ yo'q |
| Notification badge | ❌ yo'q (sahifa bor, badge yo'q) |
| Profile menu | ❌ yo'q (faqat text + logout) |
| Mobile | Sidebar toggle + `data-navigation-toggle` bor, scrim/trap yo'q |

### 2.2 Sahifalar inventari (60 view)

**Mavjud va to'liq:** student dashboard, course, lesson, tutor, quiz, assignments, cases, analytics · mentor reviews/students · admin courses/modules/lessons/assignments/quizzes/cases/enrollments/users/groups/reports/settings/legal-sources · certificates (index/show/verify) · schedule (index/attendance) · communication (4 sahifa) · announcements · notifications · auth (login/register) · 8 xato sahifasi

**Yo'q / zaif:**
| Bo'shliq | Ta'sir |
|---|---|
| O'qituvchi uchun alohida dashboard | Teacher `dashboard`da admin/student statistikasini ko'radi |
| Mentor uchun KPI dashboard | `mentor/reviews` bor, umumiy dashboard yo'q |
| Academy Admin Analytics sahifasi | `/admin/reports` bor lekin vizual grafik yo'q |
| Bildirishnoma markazi (filter/tab) | Oddiy ro'yxat |
| Sertifikat dizayni preview | `show` bor, lekin chiroyli emas |
| Onboarding / empty-state dizayni | Ba'zi joyda bor, izchil emas |

### 2.3 UX muammolari (aniqlangan)

1. **Navigation — eng katta zaiflik.** 10+ link yassi ro'yxat, guruhsiz, rol bo'yicha ajratilmagan. 23 bo'limli admin panelda bu chalkashlikka olib keladi.
2. **Sidebar balandligi:** `top-20` — sticky topbar balandligiga qattiq bog'langan, topbar o'zgarsa buziladi.
3. **Mobil drawer:** `data-navigation-toggle` bor, lekin scrim, `aria-expanded` yangilash, `Escape` yopish va focus trap yo'q.
4. **Topbar:** qidiruv yo'q, profil menyusi yo'q, bildirishnoma badge yo'q — asosiy harakatlar 2 klik uzoqda.
5. **Dizayn tokenlari yo'q:** har bir view o'z inline Tailwind'i — `bg-white`, `rounded-2xl`, `border-[#0f1e3d]/10` qayta-qayta yozilgan; global o'zgarish qiyin.
6. **Kartalar bir xil emas:** ba'zi `rounded-2xl p-6`, ba'zilari `rounded-xl px-4 py-3`.
7. **Breadcrumb yo'q** — chuqur sahifalarda (course → module → lesson → editor) orientatsiya yo'q.
8. **Feedback/error uslubi** sessiya banneriga tayanadi; maydon xatolari ko'rsatilmagan joylar bor.
9. **Jadval uslubi** har sahifada qayta yozilgan — mobil moslashuvi yo'q.

### 2.4 Funksional jihatdan Legal EDU Qur'on Master'dan KUCHLI

Auditda aniqlanishicha, Legal EDU'da Qur'on Master'da **yo'q** funksiyalar bor:

- To'liq test/imtihon engine (savol turlari, autosave, grading service)
- AI Ustoz (7 pedagogik rejim, kontekst builder, driver abstraktsiyasi)
- Huquqiy kazus tizimi
- Mentoring review workspace + revision workflow
- Dars bloklari (11 tur), versiya snapshotlari
- Sertifikat public verification + bekor qilish
- Audit loglar, CSV eksport, i18n (uz-latin/uz-cyr/ru) to'liq

**Xulosa: Legal EDU funksional jihatdan to'liq, vizual/UX jihatdan orqada.**

---

## 3. TAQQOSLASH JADVALI

| Element | Qur'on AI Master | Legal EDU | Baho |
|---|---|---|---|
| Guruhlangan sidebar | ✅ 5 guruh | ❌ yassi | 🔴 Eng muhim farq |
| Nav iconlar | ✅ SVG component | ❌ faqat text | 🟠 |
| Global search | ✅ | ❌ | 🟠 |
| Notification badge | ✅ | ❌ | 🟠 |
| Profile popover | ✅ | ❌ | 🟠 |
| Breadcrumb (`page-context`) | ✅ | ❌ | 🟠 |
| Skip-link / a11y | ✅ to'liq | ⚠️ qisman | 🟡 |
| Mobile drawer + scrim | ✅ | ⚠️ qisman | 🟠 |
| Dizayn tokenlari | ✅ CSS var tizimi | ❌ inline | 🟠 |
| Hero banner | ✅ | ❌ | 🟡 |
| KPI kartalar | ✅ | ⚠️ oddiy | 🟡 |
| Dark theme | ✅ localStorage | ✅ cookie | ⚖️ teng |
| Progress bar/radial | ✅ | ⚠️ oddiy | 🟡 |
| Konfirmatsiya dialog | ✅ | ❌ native confirm | 🟡 |
| Feedback (yopiladigan) | ✅ | ❌ | 🟡 |
| i18n | ✅ 3 til | ✅ 3 til | ⚖️ teng |
| Test engine | ❌ | ✅ | 🟢 Legal kuchli |
| AI tutor | ⚠️ assistant | ✅ pedagogik | 🟢 Legal kuchli |

---

## 4. ADAPTATSIYA REJASI (taklif qilinmoqda)

### L1 — Dizayn tizimi poydevori *(risk: past)*
1. `resources/css/app.css` — **token layer**: `--edu-navy`, `--edu-royal`, `--edu-ivory`, `--edu-gold`, `--edu-radius-*`, `--edu-shadow-*`
2. Komponent klasslari: `.edu-card`, `.edu-panel`, `.edu-btn` (primary/ghost/danger), `.edu-input`, `.edu-table`, `.edu-badge` (4 holat), `.edu-empty`, `.edu-skeleton`, `.edu-feedback`
3. To'liq `[data-theme=dark]` qo'llab-quvvatlash (token darajasida, override'larsiz)
4. Space/typography shkalasi

### L2 — Navigation qayta qurish *(risk: o'rta)*
5. **Guruhlangan sidebar**: Asosiy · Ta'lim · O'quv jarayoni · Muloqot · Tizim — Qur'on Master'dagi `nav-group-label` pattern, lekin Legal palitrasida
6. **SVG nav-icon komponenti** (`<x-nav-icon>`) — 20+ ikonka (huquq tematikasi: tarozi, qonun, §, hujjat, sud, guvohnoma)
7. `aria-current`, `is-active`, sidebar footer (versiya + logout)
8. To'g'ri **mobile drawer**: scrim + `aria-expanded` yangilash + Escape + focus trap + `overflow:hidden`

### L3 — Topbar *(risk: o'rta)*
9. Global search (route'lardan o'tadi: kurs, dars, talaba, foydalanuvchi)
10. Notification badge (unread count, 99+ cap)
11. Profile `<details>` popover — ism, email, rol, til, tema, chiqish
12. Breadcrumb `page-context` + sana

### L4 — Dashboard qayta dizayn *(risk: o'rta)*
13. **Hero banner** — Legal palette (deep navy + royal blue gradient + subtle gold accent), rolga mos subtitle + bitta asosiy CTA
14. **KPI kartalar** — icon + value + label + trend, to'rtta asosiy ko'rsatkich
15. Rolga mos dashboard: Teacher (kurslar/tekshirish/publish), Mentor (pending reviews/assigned), Student (continue learning), Admin (academy overview)
16. Radial progress / progress bar komponenti

### L5 — Sahifa darajasidagi yaxshilanishlar *(risk: past)*
17. Barcha 60 view'da inline klasslar → `edu-*` komponent klasslariga migratsiya (bosqichma-bosqich)
18. Jadvallar: responsive (mobilda kartaga aylanish), filter, pagination uslubi
19. Bo'sh holatlar (empty state) va skeleton'lar izchil
20. Feedback banner — yopiladigan + ARIA live
21. Konfirmatsiya dialog komponenti (native `<dialog>`)

### L6 — Mikrointeraksiyalar *(risk: past)*
22. `:hover`/`:active` transitionlar (120–180ms), focus ring, kartalar hover lift
23. Silliq sahifa o'tishlari (faqat CSS)
24. `prefers-reduced-motion` hurmat

### L7 — Tekshiruv
25. Har bosqichdan keyin: Pest suite (174 test), Vite build, **brauzer skrinshot** (mobile 390px + desktop 1440px), dark/light, 3 til
26. Regressiya: barcha route 200/302 javob berishi

---

## 5. CHEGARALAR (buzilmaydi)

- ✅ Backend, API, permission, DB — **tegilmaydi**
- ✅ Mavjud route nomlari va controllerlar saqlanadi
- ✅ Barcha testlar yashil qoladi
- ✅ Qur'on Master'dagi yashil-oltin, arabcha, diniy elementlar **ko'chirilmaydi**
- ✅ Legal EDU o'z vizual identifikatsiyasini saqlaydi: **Deep Navy + Royal Blue + Ivory + subtle Gold** (LegalTech profesional ohang)
