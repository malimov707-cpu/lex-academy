# L1 + L2 — Dizayn tizimi poydevori va Navigation qayta qurish

Sana: 2026-11-16 · Holat: **bajarildi va tekshirildi**

## L1 — Dizayn tizimi (`resources/css/app.css`)

### Tokenlar (`:root` + `[data-theme=dark]`)
| Guruh | Tokenlar |
|---|---|
| Rang | `--edu-navy`, `--edu-navy-deep`, `--edu-royal`, `--edu-royal-soft`, `--edu-ivory`, `--edu-gray`, `--edu-gold` |
| Sirt | `--edu-surface`, `--edu-surface-muted`, `--edu-line`, `--edu-line-strong`, `--edu-text`, `--edu-text-muted` |
| Radius | `--edu-radius-sm`, `--edu-radius`, `--edu-radius-lg`, `--edu-radius-pill` |
| Soya | `--edu-shadow-xs`, `--edu-shadow`, `--edu-shadow-lg` |
| Sidebar | `--edu-sidebar-w` (264px), `--edu-sidebar-bg`, `--edu-sidebar-text`, `--edu-sidebar-active` |
| Motion | `--edu-ease`, `--edu-dur` (160ms) |

Dark theme **token darajasida** ishlaydi — view'larda override kerak emas.

### Komponent klasslari
`.edu-card` (+`-hover`), `.edu-panel`, `.edu-btn` (`primary`/`ghost`/`danger`/`sm`), `.edu-input`,
`.edu-table`, `.edu-badge` (`success`/`warning`/`info`/`danger`/`neutral`), `.edu-empty`,
`.edu-feedback` (`success`/`error`), `.edu-progress`, `.edu-skeleton`, `.edu-skip-link`, `.edu-icon-button`.

### Mavjud view'lar bilan moslik
`.dark .bg-white`, `.dark input/select/textarea`, `.dark .bg-\[\#f7f5f0\]` — eski inline Tailwind
view'lari buzilmaydi; migratsiya bosqichma-bosqich amalga oshiriladi.

## L2 — Navigation

### Guruhlangan sidebar (`layouts/navigation.blade.php`)
Guruhlar rol bo'yicha filtrlanadi va `Route::has()` bilan himoyalanadi:

| Guruh | Student | Mentor | Teacher | Admin |
|---|---|---|---|---|
| Asosiy | Boshqaruv, O'qish, Kazuslar | Boshqaruv | Boshqaruv | Boshqaruv |
| Ta'lim boshqaruvi | — | — | Kurslar | Kurslar, Kategoriyalar, Huquqiy manbalar |
| O'quv jarayoni | Topshiriqlar | Tekshirish, Talabalar | — | Tekshirish, Talabalar |
| Muloqot | Shaxsiy muloqot | Shaxsiy muloqot | — | — |
| Tizim | Bildirishnomalar, Jadval, E'lonlar, Sertifikatlar | + | + | + Foydalanuvchilar, Guruhlar, Hisobotlar, Sozlamalar |

### `x-nav-icon` komponenti
27 ta stroke SVG ikonka (24×24, `currentColor`): `scale`, `law`, `paragraph`, `cases`, `courses`,
`lesson`, `review`, `certificate`, `notification`, `settings`, `sources`, `tutor`, `chat`, …
Noma'lum nom → `grid` fallback. Huquqiy tematik belgilar (tarozi, §, hujjat, sud) — LegalTech identifikatsiyasi uchun.

### Topbar
- Mobil menyu tugmasi (ARIA `aria-controls`/`aria-expanded`)
- Breadcrumb (`HUQUQ AI Academy / <sahifa>`)
- Til tanlash (UZ / ЎЗ / RU) — `onchange` avtomatik submit
- Theme toggle
- **Bildirishnoma badge** — unread count, `99+` cap, `aria-label`
- **Profile `<details>` popover** — ism, email, rol badge, chiqish

### Mobil drawer
- `-translate-x-full` → `translate-x-0`, 200ms
- Scrim (`edu-drawer-scrim`): blur + `hidden` toggle
- `aria-expanded` yangilanadi, `body.overflow: hidden`
- **Escape** yopadi, **focus trap** (Tab/Shift+Tab), yopilganda fokus qaytadi
- `resize` ≥1024px → avtomatik tozalash

### a11y
`edu-skip-link` (fokusda ko'rinadi), `aria-current="page"`, `aria-label`, `aria-hidden` dekorativ SVG'larda,
`:focus-visible` outline barcha interaktiv elementlarda, `prefers-reduced-motion`.

### Feedback
`layouts/partials/feedback.blade.php` — validatsiya xatolari va sessiya xabari, `role="alert"` / `role="status"`,
yopish tugmasi. `@guest` va `@auth` layoutlarida bir xil.

## Tekshiruv natijalari

| Tekshiruv | Natija |
|---|---|
| Pest suite | **174 test / 730 assertion — barchasi o'tdi** |
| Pint | `passed` |
| Vite build | ✅ |
| Blade view cache / route cache | ✅ |
| Brauzer (desktop 1440×900) | 0 konsol xatosi, 0 muvaffaqiyatsiz so'rov |
| Brauzer (mobil 390×844) | drawer ochilish/yopilish, scrim, Escape — ✅ o'lchandi |
| Dark theme (admin) | token'lar qo'llanadi, kontrast yaxshi |
| Sidebar o'lchovlari | 720px balandlik, nav 519/709 scroll, footer 592–720 (ko'rinadi) |

## Qolgan ishlar (keyingi bosqichlar)
- **L3** — global search (kurs/dars/talaba)
- **L4** — rolga mos KPI dashboard
- **L5** — 60 view'ni `edu-*` komponentlarga migratsiya (hozir dashboard va layout o'tkazilgan)
- **L6** — mikrointeraksiyalar (hover lift, transition)
