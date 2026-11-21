# HUQUQ AI ACADEMY — Implementation Progress

## Bajarilgan
- [x] PHASE 0 — Audit (`docs/EDU_AUDIT.md`). Muhim: promptdagi FastAPI/RAG stack bu repoda yo'q; Laravel 13 skeleton asosida qurilyapti.
- [x] PHASE 1 — Education foundation:
  - `users` jadvaliga role/phone/locale qo'shildi, `App\Enums\UserRole`
  - 17 ta akademiya migratsiyasi + modellar + factory'lar, `EnsureRole` middleware (`role:` alias)
  - Login/logout, rol dashboardlari (real DB agregatsiyalar), Blade layout + Tailwind, AcademySeeder
  - Seed loginlar: super@huquq.ai / admin@huquq.ai / teacher@huquq.ai / mentor@huquq.ai / student@huquq.ai (parol: password)
- [x] PHASE 2 — Course management:
  - Kategoriya CRUD (`/admin/categories`), Kurs CRUD + publish/archive (`/admin/courses`)
  - Modullar CRUD + tartiblash, darslar CRUD + muharrir (`/admin/lessons/{lesson}/edit`) + nashr
  - Materiallar (video/audio/pdf/docx/image/havola) validatsiya bilan
- [x] PHASE 3 — Student experience:
  - `/learn` dashboard (kurslar, progress bar, deadline'lar), `/learn/courses/{course}` kurs sahifasi
  - `/learn/courses/{course}/lessons/{lesson}` dars sahifasi (navigatsiya, materiallar, previous/next)
  - `POST .../complete` — completion_criteria tasdiqlash bilan progress; admin enrollment boshqaruvi
- [x] PHASE 5 — AI Ustoz:
  - `App\Services\Ai\` — AiDriver interfeys, OpenAiCompatibleDriver (OpenAI-mos servislar: OpenAI, vLLM, Ollama, mahalliy FastAPI/RAG), DisabledDriver, AiDriverManager, TutorContextBuilder
  - `config/ai_tutor.php` — AI_TUTOR_API_KEY / BASE_URL / MODEL, kontekst limiti; kalit yo'q bo'lsa 503 + yo'riqnoma (soxta javob YO'Q)
  - Pedagogik prompt: ASSESS→EXPLAIN→ASK→PRACTICE→EVALUATE→FEEDBACK→REPEAT, 7 rejim, talaba o'rniga vazifa bajarmaslik qoidalari
  - Kontekst: kurs+modul+dars+talaba holati, token nazorati (max 12 xabar)
  - ai_tutor_sessions/messages, sessiya izolyatsiyasi talaba bo'yicha; chat UI ishonch % bilan
  - 6 yangi test; jami 44 test / 96 assertion o'tadi
- [x] PHASE 4 — Assignments & mentor review:
  - `/admin/courses/{course}/assignments` — topshiriq CRUD (type, deadline, max_score, rubric, attempts, late policy, mentor biriktirish → mentor_assignments avtomatik)
  - `/learn/assignments` — talaba yuborish (matn + fayllar, mime/size validatsiya), attempt limit, deadline nazorati
  - `/mentor/reviews` — Review Workspace: faqat biriktirilgan talabalar; ball (max nazorat), izoh, approved/rejected/revision_requested, review tarixi
- [x] PHASE 6 — Quiz & Exam Engine (dastlabki amalga oshirish; quyidagi davom ettirishda to‘ldirildi):
  - Jadvallar: questions, question_options, quizzes (quiz|exam), quiz_questions, quiz_attempts, quiz_answers
  - Savol turlari: single, multiple, true_false, short_answer, long_answer, scenario
  - `QuizGradingService`: single/multiple/short_answer avtomatik; long_answer/scenario javoblari dastlab null qolgan; manual baholash endpointi keyingi davom ettirishda qo‘shildi.
  - O'qituvchi builder: `/admin/courses/{course}/quizzes` — dinamik savol/variant formasi, urinish, vaqt, o'tish %, mavjudlik oynasi
  - Talaba: `/learn/courses/{course}/quizzes/{quiz}` — start → autosave (10s interval, uzilishdan keyin tiklanadi) → submit → natija %
  - Mavjudlik oynasi, urinish limiti, enrollment nazorati, course-quiz mosligi tekshiriladi
  - Jami 49 test / 111 assertion o'tadi

- [x] PHASE 7 — Legal case studies (2026-11-15):
  - `legal_cases` va `legal_case_feedback` migratsiyasi lokal bazaga qo‘llandi. Kazus mavjud `assignments` bilan 1:1 bog‘langan; yuborish va mentor baholash jadvallari qayta ishlatildi.
  - O‘qituvchi/admin: kurslar ro‘yxatidagi “Kazuslar” havolasi orqali yaratish, tahrirlash, draft/published/archived boshqaruvi, 9 huquqiy yo‘nalish, faktlar, muammo, manbalar, savollar, rubric, deadline, urinishlar.
  - Kazus yaratishda tanlangan mentor kursning joriy aktiv talabalariga biriktiriladi. Keyin qo‘shilgan talabalar uchun mentorni alohida biriktirish kerak.
  - Talaba: `/learn/cases` — faqat aktiv yozilgan, published kurslardagi published kazuslar; nom/kategoriya filtri va pagination. Kazus sahifasida AI yo‘naltirishi, javob yuborish va baholash tarixi.
  - AI: mavjud `AiDriverManager` ishlatiladi; faqat tanlangan kazus va talabaning joriy tahlili yuboriladi. Yo‘naltiruvchi savollar, tayyor yechim bermaslik va tekshirilmagan manbalarni fakt sifatida ko‘rsatmaslik prompt qoidalari qo‘shildi. Model bu qoidalarga har doim amal qilishi kafolatlanmaydi.
  - AI tavsiyalari talabaga tegishli holda saqlanadi; mentor ularni o‘z kursiga biriktirilgan talaba ishi ichida ko‘radi. AI rasmiy ball yozmaydi. Kalit bo‘lmasa JSON 503 yoki brauzerda tushunarli xabar; ulanish uzilishi ham qayta ishlanadi.
  - Xavfsizlik: mentor ruxsati talaba + kurs bo‘yicha tekshiriladi; boshqa o‘qituvchi kazusning assignmentini o‘chira olmaydi; draft/arxivlangan kazusga javob yuborish bloklangan; AI endpointida rate limit.
  - Tekshiruv: yangi 16 feature test; umumiy 65 test / 198 assertion o‘tdi. `npm run build` muvaffaqiyatli.
  - `pint --dirty --format agent` Git metama’lumoti yo‘qligi sabab ishlamadi; o‘zgargan PHP fayllar aniq ko‘rsatilgan holda `pint --format agent` bajarildi.
  - Jonli AI provayder tekshirilmadi: `AI_TUTOR_API_KEY`, `AI_TUTOR_BASE_URL`, `AI_TUTOR_MODEL` sozlamalari kerak. Testlarda HTTP fake ishlatildi, mahsulotda soxta javob yo‘q. RAG/legal corpus bu repoda mavjud emas; o‘qituvchi kiritgan manbalar kontekst sifatida uzatiladi.

## Keyingi davom ettirish — 2026-11-15

- [x] PHASE 6 — qolgan asosiy jarayonlar:
  - `Course::quizzes()` va `questions()` munosabatlari, savollar bankidan qayta foydalanish, nested builder validatsiyasi.
  - Autosave va final submit uchun enrollment/course/question/option izolyatsiyasi, vaqt limitini serverda tekshirish, aktiv urinishni tiklash, submitdagi oxirgi javoblarni saqlash, bo‘shatilgan javobni ham saqlash.
  - Ekranda saqlangan tanlovlar va matn tiklanadi; countdown, ulanish/saqlash holati va urinish natijalari ko‘rsatiladi.
  - Manual grading workspace, ball chegaralari, yakuniy foizni qayta hisoblash. AI yordamchi tahlili rasmiy ballni o‘zgartirmaydi.
  - Matching/ordering/huquqiy kazus turlari; JSON asosidagi structured grading. Manual bahosi kutilayotgan foiz dastlabki deb ko‘rsatiladi.
- [x] PHASE 8 — Schedule, attendance, certificates:
  - Kurs/guruh bo‘yicha jadval, oylik kalendar, sana filtri, tahrirlash, o‘qituvchi yoki kursga biriktirilgan mentor olib boradigan uchrashuvlar.
  - Present/absent/late/excused, sabab, belgilagan xodim va kurs/guruh student scope nazorati. Talaba o‘z davomatini ko‘radi.
  - Sertifikat eligibility: published darslar tugashi, topshiriqlar mentor tomonidan tasdiqlanishi, testlardan yakuniy o‘tish balli. Bo‘sh kurs yoki baholanmagan manual javobga sertifikat berilmaydi.
  - Bir kurs/talaba uchun bitta sertifikat, ism/kurs snapshoti, public verification, bekor qilish, verification tarixi. Chop etish / brauzerda PDF saqlash.
- [x] PHASE 9 — Admin va asosiy analytics:
  - Xodim/talaba yaratish va tahrirlash, rol filtri/qidirish, kuchli parol, academy admin/super admin chegaralari.
  - Guruhlar, kursning aktiv talabalarini qo‘shish, mentorni kurs/talabaga biriktirish.
  - Sana filtri, real DB agregatsiyalar, topshiriq faolligi grafigi, kurs/talaba ko‘rsatkichlari, AI rejimlari statistikasi, CSV eksport, audit paneli.
  - E’lonlar, in-app bildirishnomalar va o‘qilgan holati, idempotent deadline eslatmalari.
- [x] PHASE 10 — lokal tekshiruv va operatsion tayyorgarlik:
  - Markaziy kurs/dars ruxsat tekshiruvi, dars completion himoyasi, o‘qituvchi ro‘yxatini o‘z kurslari bilan cheklash, mentor dashboard izolyatsiyasi.
  - Private material/submission download, upload MIME va URL protokoli validatsiyasi, login/AI rate limit, xavfsizlik headerlari.
  - Dars versiyasi snapshotlari, mavjud edit ekranidagi yetishmagan `blocks` relationi tuzatildi. AI Ustoz zaif savollar kontekstini joriy kursdagi real test xatolaridan oladi; rejimni almashtirish ishlaydi.
  - Quiz urinishlari, submission attemptlari va review/sertifikat yozuvlarida tegishli transactionlar.
  - SQLite backup buyrug‘i va integrity check, scheduler, DB/cache health check, production demo seeder blokirovkasi.
  - `docs/EDU_DEPLOYMENT.md` — deploy, backup/restore, monitoring va funksional chegaralar.

## Yakuniy tekshiruvlar
- To‘liq Pest suite: **99 test / 386 assertion** — barchasi o‘tdi (2026-11-15).
- Frontend build, Blade view cache va route cache kompilyatsiyasi muvaffaqiyatli.
- Composer va npm audit: 0 ta advisory.
- Lokal bazaga yangi migratsiyalar qo‘llandi; `academy:backup` haqiqiy snapshot yaratdi va yaxlitlik tekshiruvidan o‘tdi.
- Git metama’lumoti yo‘q: Pint `--dirty` o‘rniga PHP katalog/fayllari ko‘rsatilgan holda ishlatildi.

## Operatsion va kengaytirilgan qolgan ishlar
- [ ] Production hosting/domen/HTTPS, offsite backup/retention/restore drill, tashqi monitoring va staging yuklama/brauzer sinovlari.
- [ ] Jonli AI/RAG konfiguratsiyasi va model sifatini real manbalar bilan tekshirish.
- [x] 2026-11-16 davom ettirish: til tanlash va saqlash, o‘zbek lotin/kirill/rus kataloglari, validatsiya/pagination tarjimalari va recipient locale bildirishnomalari.
- [x] 11 turdagi dars bloki, native drag-and-drop va klaviatura tugmalari, preview, versiya snapshotlari, interaktiv javoblar; modul va darslar tartibini drag-and-drop orqali saqlash.
- [x] O‘qituvchi AI vositalarining 9 turi, manbali qoralama, tahrirlash, majburiy manba tasdig‘i va faqat tasdiqlangandan keyin darsga qo‘shish.
- [x] Mentor–talaba shaxsiy yozishmalari, o‘qilgan holati, kurs biriktirishi bilan izolyatsiya; kurs muhokamalari, javoblar va o‘qituvchi moderatsiyasi.
- [x] `academy:restore-check` bilan ajratilgan lokal restore tekshiruvi, checksum va DB integrity. Production bazaga restore bajarilmadi.
- [x] Lokal HTTP smoke: 60 so‘rov, concurrency 4, 60 muvaffaqiyat, P50 7.07ms / P95 12.42ms. Bu production yuklama yoki real brauzer testi emas.
- [x] `academy:check-ai`: sozlangan OpenAI-mos servisga talaba ma’lumoti yubormasdan ulanishni tekshiradi. `.env.example` konfiguratsiyasi tayyor.
- [ ] OpenAI URL/model/key sozlangan; chat uchun API krediti tiklanishi va jonli sifat testi kutilmoqda.

## 2026-11-16 tekshiruvlari
- Kengaytmalar bilan yakuniy to‘liq suite: **130 test / 545 assertion** — barchasi o‘tdi.
- Yangi migratsiya lokal bazaga qo‘llandi; backup va ajratilgan restore tekshiruvi muvaffaqiyatli.
- Frontend JS sintaksisi, Vite build, Blade va route cache tekshirildi. Git yo‘qligi sabab Pint aniq katalog/fayllarga ishlatildi.

## OpenAI-mos jonli ulanish tekshiruvi — 2026-11-16
- `.env`da BASE_URL / MODEL / API_KEY mavjud. Qiymatlar va kalit chiqarilmadi. Config cache tozalandi.
- Model metadata endpointi haqiqiy so‘rovda muvaffaqiyatli javob berdi.
- Haqiqiy chat so‘rovi HTTP 429 bilan rad etildi. Muvaffaqiyatli AI Ustoz javobi tasdiqlanmadi; provayder rate-limit yoki quota cheklovini yechish kerak. 429 umumiy javobidan aniq billing sababi taxmin qilinmadi.
- Driver: 10s connection / 60s response timeout, redirectlar o‘chiq, xom provider xatosi va transport exceptionlari tashqariga chiqarilmaydi. 401/403/404/429/5xx va ma’lum quota kodlari uchun tushunarli diagnostika.
- Frontend (`public`, `resources`) va `storage/logs` ichida amaldagi kalit bilan moslik topilmadi. `.env` gitignore bilan himoyalangan; Git metama’lumoti mavjud emas, commit bajarilmadi.
- Yakuniy suite: 136 test / 566 assertion — barchasi o‘tdi.

## OpenAI 429 aniq tashxisi — 2026-11-16
- Haqiqiy API javobidan faqat xavfsiz diagnostika ajratildi: `error.code=credit_balance_exhausted`, `error.type=insufficient_quota`, HTTP 429. Sabab API krediti tugagan; oddiy rate-limit emas.
- Driver ushbu kodni alohida tushunarli xabar bilan qaytaradi. Kalit va xom provider matni yozilmadi.
- Tegishli AI connection/tutor testlari: 15 test / 43 assertion o‘tdi; Pint bajarildi.
- Keyingi zarur tashqi amal: API tashkiloti billing balansini tiklash yoki krediti bor loyiha kalitini muhitda sozlash. Kredit xaridi bajarilmadi.

## Production tekshiruvini davom ettirish — 2026-11-16
- `academy:readiness --json`: release shartlari, build artefaktlari, DB/migration, xavfsiz cookie, admin/demo parol tekshiruvlari. Noto‘g‘ri sozlamalarda exit 1; credential qiymatlari chiqarilmaydi.
- `academy:create-admin`: secret konfiguratsiyadan kuchli parol bilan birinchi admin yaratish; mavjud emailni qayta yaratmaydi, parolni argument/outputga chiqarmaydi. Haqiqiy admin avtomatik yaratilmagan.
- 3 yangi feature test / 18 assertion o‘tdi. Lokal .env productionga majburan o‘zgartirilmagan.
- Safari WebDriver o‘rnatilgan, lekin OS Remote Automation o‘chirilgani uchun real browser sessiyasi ochilmadi. Sozlama foydalanuvchi tomonidan yoqilishi kerak.
- Yakuniy umumiy suite: 140 test / 587 assertion — barchasi o‘tdi.

## Dark theme — 2026-11-16
- `POST /theme` (`ThemeController`), cookie `academy_theme` (1 yil, server-rendered, JS shart emas), `throttle:30,1`.
- `<html data-theme>` + inline FOUC-prevention script `<head>`da; `<html class="dark">` birinchi renderdan oldin qo'llanadi.
- Tailwind `@custom-variant dark` + sirt/input/border dark stillari (`resources/css/app.css`).
- Navigatsiyada 🌙/☀️ almashtirish tugmasi; tanlangan mavzu keyingi sahifalarda saqlanadi.
- 4 feature test (`ThemeTest`). Yakuniy suite: **167 test / 709 assertion** — barchasi o'tdi. View cache, route cache va Vite build muvaffaqiyatli.
- Chegara: ichki sahifa kartalari hali asosan `bg-white` utility ishlatadi; dark rejimda bu `.dark .bg-white` override orqali qoplanadi, lekin yangi view'lar uchun `dark:` utilitlardan foydalanish tavsiya etiladi.

## Ro'yxatdan o'tish (register) — 2026-11-16
- `/register` (GET/POST) — `RegisteredUserController`; maydonlar: ism, email, telefon (ixtiyoriy), parol + tasdiqlash.
- **Xavfsizlik**: ochiq ro'yxatdan o'tish faqat `student` roli bilan yaratadi — yuborilgan `role` maydoni e'tiborsiz qoldiriladi (mass-assignment yo'q). Xodim rollari faqat admin orqali.
- Parol siyosati: `Password::min(8)->letters()->numbers()` + `confirmed`; parol hash qilinadi (`hashed` cast).
- `throttle:register` rate limiter (20/daq, 50/soat, IP bo'yicha). Email unikal tekshiruvi. `DB::transaction`.
- Ro'yxatdan o'tgach avtomatik login → `/learn` (student dashboard) + administrator bilan bog'lanish xabari.
- Login sahifasida «Hisobingiz yo'qmi? Ro'yxatdan o'tish» havolasi, register sahifasida «Kirish» havolasi.
- 7 feature test (`RegistrationTest`): sahifa, muvaffaqiyatli ro'yxat, rol inject himoyasi, dublikat email, zaif/mos kelmagan parol, authenticated redirect, parol hash.
- Real brauzer QA: ro'yxatdan o'tish → avtomatik login → student dashboard, 0 konsol xatosi.
- Yakuniy suite: **174 test / 728 assertion** — barchasi o'tdi.

## L1 + L2 — Dizayn tizimi va navigatsiya qayta qurildi — 2026-11-16

### L1 — Dizayn tokenlari va komponentlar (`resources/css/app.css`)
- **Tokenlar**: `--edu-navy/deep`, `--edu-royal/soft`, `--edu-ivory`, `--edu-gray`, `--edu-gold`, `--edu-surface(-muted)`, `--edu-line(-strong)`, `--edu-text(-muted)`, radius (`sm`/base/`lg`/`pill`), shadow (`xs`/base/`lg`), `--edu-ease`, `--edu-dur`.
- **Dark theme token darajasida** — `[data-theme=dark]` va `.dark` selektorlari ranglarni qayta belgilaydi (ilgari view'da `!` override).
- **Komponent klasslari**: `.edu-card(+hover)`, `.edu-panel`, `.edu-btn` (`primary`/`ghost`/`danger`/`sm`), `.edu-input`, `.edu-table`, `.edu-badge` (5 holat, dark variant bilan), `.edu-empty`, `.edu-feedback` (success/error), `.edu-progress`, `.edu-skeleton` (shimmer), `.edu-skip-link`.
- `prefers-reduced-motion` qo'llab-quvvatlanadi; forma elementlari iOS/WebKit uchun normalizatsiya qilindi (`-webkit-autofill` ham).

### L2 — Navigatsiya
- **`<x-nav-icon>`** komponenti — 28 ta 24×24 stroke ikonka (tarozi, qonun, §, kazus, sertifikat, jadval, davomat va h.k.), `currentColor` meros oladi, dekorativ (`aria-hidden`).
- **Guruhlangan sidebar** (`layouts/navigation.blade.php`): Asosiy · Ta'lim boshqaruvi · O'quv jarayoni · Muloqot · Tizim. Rol bo'yicha filtr (`isAdmin`/`isTeacher`/`isMentor`/`isStudent`), `Route::has()` himoyasi.
- Faol holat: `.is-active` + `aria-current="page"` + oltin chap indikator.
- **Sidebar**: navy gradient, brend markasi, guruh sarlavhalari (oltin, uppercase), footer note + logout.
- **Topbar**: breadcrumb (brend / sahifa nomi), locale switcher, theme toggle, **bildirishnoma badge** (unread, 99+ cap), **profil `<details>` popover** (ism, email, rol badge, logout).
- **Mobil drawer**: scrim + `translate-x` animatsiya, `aria-expanded` yangilash, **Escape** yopish, **focus trap** (Tab/Shift+Tab), `body overflow` lock, `resize` da tozalash, fokus qaytarish.
- **Guest shell** — login/register uchun alohida soddalashtirilgan topbar.

### Dashboard qayta dizayn
- **Hero banner** — navy→royal gradient, rol badge, vaqtga mos salomlashuv, rolga mos subtitle va bitta asosiy CTA, dekorativ gold/blue radial glow.
- **KPI kartalar** — ikonka + qiymat + label, kartalar soniga qarab grid moslashadi (`xl:grid-cols-4/3/2`).
- **Keyingi qadamlar** — rolga mos 3–4 tezkor havola.
- **AI Ustoz paneli** — pedagogik model tavsifi.
- Student statistikasi kengaytirildi: `learning_progress` (real %), `upcoming_deadlines`.

### Yagona feedback partial
- `layouts/partials/feedback.blade.php` — `role="alert"` (xatolar ro'yxati) va `role="status" aria-live="polite"` (muvaffaqiyat), yopish tugmasi.

### Tekshiruv
- **174 test / 730 assertion** — hammasi o'tadi.
- Real brauzer QA: **0 konsol xatosi, 0 muvaffaqiyatsiz so'rov**.
- Mobil drawer: 390px da yopiq → ochiq → Escape bilan yopish, `aria-expanded`/scrim/body-lock to'g'ri.
- Skrinshotlar: desktop 1440px va mobil 390px, light/dark.
- Pint, Vite build, Blade view cache — barchasi muvaffaqiyatli.

### Keyingi (L3–L7 qolgan)
- L3: global qidiruv (kurs/dars/talaba), breadcrumb kengaytirish.
- L4: rol dashboardlari (teacher/mentor alohida KPI).
- L5: qolgan ~40 view'ni `edu-*` klasslariga migratsiya (hozircha 2 ta: layout + dashboard + feedback).
- L6: mikrointeraksiyalar to'liq.

## L3 + L4 + L5 + L6 — 2026-11-16

### L3 — Global qidiruv
- `SearchController` (`GET /search`, `throttle:60,1`) — kurs / dars / odamlar bo'yicha, **rol doirasida**:
  - Student: faqat published + o'zi yozilgan kurslar; darslar faqat enrollment bo'lsa
  - Teacher: faqat o'z kurslari; talabalar faqat o'z kurslaridan
  - Mentor: published kurslar; talabalar faqat biriktirilganlar
  - Admin: hammasi
- `LIKE` uchun `%`/`_` escape, kamida 2 belgi, natijalar guruhlangan.
- Topbar'da inline qidiruv (desktop, `md+`), mobil uchun ikonka → `/search`.
- `search/index.blade.php` — guruhlangan natijalar, empty state, live-region hisoblagich.

### L4 — Teacher va Mentor alohida dashboard
- `TeacherDashboardController` (`/teacher`) — kurslar soni, nashr/qoralama, darslar, talabalar, tekshirish navbati, so'nggi ishlar.
- `MentorDashboardController` (`/mentor`) — biriktirilgan talabalar, navbat, haftalik baholangan, qayta ishlashga yuborilgan, so'nggi baholar.
- `DashboardController` teacher/mentorni o'z paneliga redirect qiladi; navigation'da «Boshqaruv» dublikati olib tashlandi.
- Yangi qayta ishlatiladigan partiallar: `partials/dash-hero.blade.php`, `partials/kpi-card.blade.php`.

### L5 — View migratsiyasi (`edu-*` klasslar)
- **15 fayl** avtomatik migratsiya qilindi: `edu-panel`, `edu-card`, `edu-card-hover`, `edu-table`, `edu-input`, `edu-btn` (primary/ghost/sm).
- Status badge'lar `edu-badge` (success/warning/info/neutral) ga o'tkazildi.
- QA: tekshirilgan 6 sahifada **0 ta legacy klass** (`bg-white p-6 shadow-sm`) qoldi.

### L6 — Mikrointeraksiyalar
- `edu-rise` kartalar chiqishi (KPI uchun 0/40/80/120ms stagger).
- Tugma `:active` bosilish effekti, hover'da ikonka scale/slide.
- Jadval qatorida chap chegara aksenti (`inset box-shadow`).
- Progress bar `edu-grow` animatsiyasi, `details[open]` popover `edu-pop`.
- Global `:focus-visible` halqasi, `prefers-reduced-motion` hurmat qilinadi.
- `.edu-btn-hero` — dark mode'da hero CTA kontrastini kafolatlaydi (`!important` bilan override'dan himoyalangan).

### QA davomida tuzatilgan nuqsonlar
1. KPI ikonkalari yumaloq fon orqasida kesilgan → aniq `19×19` o'lcham.
2. Sidebar'da ikki link bir vaqtda faol → teacher/mentor uchun «Boshqaruv» olib tashlandi.
3. Qidiruv kartasida `→` matn tagiga tushgan → `</span>` + `shrink-0`.
4. SVG `m20 20-3.5` yaroqsiz path (3 joyda) → `M20 20l-4.2 -4.2`.
5. **Dark modeda hero CTA ko'rinmas** → `.edu-btn-hero` (oq fon, navy matn).

### Yakuniy tekshiruv
- **174 test / 730 assertion** — barchasi o'tadi.
- Real brauzer QA: teacher, mentor, student sahifalari — **0 konsol xatosi, 0 muvaffaqiyatsiz so'rov**.
- Dark + light, desktop 1440px + mobil 390px — skrinshotlar bilan tasdiqlandi.
- Pint (tuzatishlar qo'llandi), route cache, view cache, Vite build — muvaffaqiyatli.

## Kurs sahifalari to'liq qayta dizayn — 2026-11-16

### Student course sahifasi
- Hero banner (gradient, progress bar, CTA), modullar ro'yxati (tugallangan ✓/raqam), yon panel (testlar/imtihonlar, kurs bo'limlari, kurs haqida + maqsadlar).
- Har bir darsda materiallar soni badge'i, hover'da strelka.

### Student lesson sahifasi
- 3 ustunli layout: kurs tarkibi navigatsiyasi (faqat joriy dars aktiv) · dars kontenti · AI Ustoz + holat + topshiriqlar.
- Maqsadlar, matn (`nl2br`+`e` XSS himoyasi), materiallar grid, oldingi/keyingi + tugatish bir qatorda.
- `confirm_criteria` yashirin input bilan avtomatik yuboriladi (mexanizm buzilmagan).

### Admin kurslar sahifasi
- Jadval → **kartalar ro'yxati**: ikonka, nom + status badge, meta, amallar (Tuzilma/Topshiriqlar/Testlar/Talabalar/AI/Kazuslar), `⋯` menyuda Arxiv/O'chirish.
- "Yangi kurs" formasi: label'lar, placeholder'lar, ikki ustunli qiyinlik/til.
- Demo'ga qoralama kurs qo'shildi (nashr oqimi ko'rinadi).

### Texnik tuzatishlar
- **Breakpoint utilitalari**: `.edu-only-mobile` (≤767px), `.edu-only-mobile-wide` (≤1023px) — Tailwind `hidden md:flex` konfliktlari hal qilindi (qidiruv ikonkasi, menyu tugmasi).
- **`.edu-topbar-search`** — maxsus klass, `display:none → flex` 768px da.
- **Sidebar**: `align-self: stretch` + ichki nav `flex:1` scroll bilan, footer pastga yopishadi; dekorativ nota desktopda yashirin (nav uchun joy).
- `⋯` menyu SVG nuqtalar bilan, `z-30`, confirm dialog bilan.
- Ikonkalar tuzatildi: tarozi, kazus (fayl sumkasi), e'lonlar (megafon).

### Tekshiruv
- **174 test / 730 assertion** yashil; 0 konsol xato / 0 muvaffaqiyatsiz so'rov.
- Sidebar to'liq qoplaydi (docH = sidebarH), scroll ishlaydi, Sozlamalar ko'rinadi.
- Skrinshotlar: student dashboard, kurs, dars, admin kurslar, mentor/teacher panellari.

## Auth sahifalarining asosiy ko'rinishi (Qur'on AI Master uslubida) — 2026-11-17
### Yangi `auth.blade.php` layout
- Login/register sahifalari login'dan oldin ko'rinadigan yakka "hero" ko'rinishga o'tdi.
- To'liq ekranli orqa fon rasmi (`public/images/auth-bg.jpg`) + ustiga Deep Navy gradient overlay — matn o'qilishi saqlanadi.
- Markazda logo + sarlavha, ostida shisha (glassmorphism) kartochka ichida forma.
- Legal EDU rang palitrasi (Deep Navy, Royal Blue, Ivory, Gold) saqlandi.
### Tekshiruv
- 174 test / 730 assertion yashil.
- Desktop (1440px) va mobil (390px) skrinshotlarida fon, logo va forma to'g'ri render bo'ldi.


## To‘xtagan ishni davom ettirish — 2026-11-17

### Aniqlangan holat
- Dashboardning bugungi jadval, yaqin imtihon va natijalar so‘rovlari yozilgan, ammo `StudentDashboardOverviewTest.php` faqat generatorning namunaviy testidan iborat qolgan edi.
- Keyingi dizayn, dark theme, qidiruv, teacher/mentor dashboard va auth sahifalari mavjud; ular qayta yozilmadi.
- Davom ettirish oldidagi to‘liq suite: **174 test / 730 assertion**.

### Yakunlangan ishlar
- Dashboard uchun 4 ta haqiqiy integratsion test: o‘qituvchi belgilagan modul tartibida davom ettirish; bugungi jadvalni kurs/guruh bilan cheklash; imtihon va natijalarni talaba bo‘yicha ajratish; darslar tugashining o‘zi sertifikatga yetarli emasligini ko‘rsatish.
- `Lesson::inLearningOrder()` dashboard, kursdagi darslar, oldingi/keyingi dars navigatsiyasi va analitikada bir xil modul → dars tartibini qo‘llaydi. Modul identifikatori yoki boshqa modulning dars tartibi keyingi darsni noto‘g‘ri tanlamaydi.
- Dashboarddagi sertifikat va’dasi kursning barcha talablarini bajarish haqidagi aniq matnga almashtirildi; uch til katalogi yangilandi.
- Mobil drawer uchun eski JS handler olib tashlandi. Menyu tugmasi, scrim va Escape yagona boshqaruvdan foydalanadi; desktopga o‘tishda overlay, body lock va aria-expanded tiklanadi.

### Avval yozilgan funksiyalarning holati
- Kurs metadata/muqova/qo‘shimcha o‘qituvchilar; 14 huquq kategoriyasi; administratorning akademiya nomi, aloqa va sertifikat beruvchi sozlamalari mavjud.
- Tasdiqlangan global/joriy kurs manbalarini AI kontekstiga olish mavjud. Bu avtomatik yangilanadigan tashqi legal corpus yoki to‘liq semantik RAG emas.
- Topshiriq qoralamasi, fayl formati/hajmi, yuborish tarixi, review navbati, mentor annotatsiyasi va rasmiy bahoni o‘zgartirmaydigan AI tahlili mavjud.
- Test savollarini tasodifiy tanlash va variantlar tartibini urinish uchun saqlash, tanlanmagan savollarga javobni rad qilish, tanlangan savollar bo‘yicha baholash mavjud.
- Dars completion qoidalari interaktiv javob/test/mentor tasdig‘iga tayanishi mumkin. Sertifikat oldidan amaldagi qoidalar qayta tekshiriladi.

### Tekshiruv dalillari
- **177 test / 751 assertion** — barchasi o‘tdi.
- Alohida fixture bazasida WebKit: **14 tekshiruv, 0 xato**. Dashboard/kurs/dars/analitika 390, 768 va 1440px; scrim bilan yopish → qayta ochish → Escape; desktopga o‘tishda menyu holatini tiklash.
- `npm run build`, JS sintaksis va Blade cache kompilyatsiyasi muvaffaqiyatli. Builddagi public rasm URLlari runtime uchun qoldiriladi; optional fontaine optimizatsiyasi haqida ogohlantirish bor, yangi paket o‘rnatilmadi.
- Git metama’lumoti yo‘qligi sabab `pint --dirty` ishlamaydi; o‘zgargan PHP fayllar aniq ko‘rsatilgan holda Pint bajarildi.

### Production va tashqi xizmatlar
- `academy:readiness --json` hali **ready=false**: production environment, debug=false, HTTPS, secure cookie va demo parollarni almashtirish bajarilmagan. Administrator mavjud; DB/migratsiyalar/build tekshiruvlari o‘tdi.
- AI bo‘yicha oxirgi jonli tekshiruv: HTTP 429, `credit_balance_exhausted` / `insufficient_quota`. Ushbu davom ettirishda pullik API qayta chaqirilmadi, modelning haqiqiy javobi tasdiqlanmadi.
- Production server/domen, offsite backup, tashqi monitoring va jonli AI sifat sinovi tashqi konfiguratsiya talab qiladi. Bular bajarildi deb belgilanmadi.


## Barcha rollar uchun AI bo‘limining yangi ko‘rinishi — 2026-11-17
- Berilgan vizualga mos alohida AI layout: ko‘k/oq gradient, gorizontal pill navigatsiya, robot tasviri, yumaloq tezkor kartalar va katta composer. Robot rasmi loyiha ichida saqlanadi (`public/images/ai-tutor-mascot.png`).
- `/ai` umumiy kirish sahifasi barcha 5 rol uchun mavjud. Talaba faqat aktiv kursning published darslarini, o‘qituvchi asosiy/qo‘shimcha biriktirilgan kurslarini, mentor faqat talaba+kurs biriktirishiga mos yuborilgan ishlarni ko‘radi. Administrator kurslarni boshqarish vositasiga o‘tadi.
- Talaba chatining mavjud API/sessiya/7 rejimi saqlangan. Tezkor kartalar savol va rejimni tanlaydi, yuborish explicit submit bilan; Enter yuboradi, Shift+Enter yangi satr. Loading, timeout, xavfsiz matn renderi va xatoda promptni saqlash mavjud.
- O‘qituvchi AI sahifasida shu dizayn, 4 tezkor material turi; barcha 9 tur, majburiy manbalar va inson tasdig‘i oqimi saqlangan.
- Mentor review, kazus tahlili va testning yordamchi AI panellari bir xil rang va kartochka tizimiga moslashtirildi. Rasmiy baholash vakolatlari o‘zgarmadi.
- Uch til va dark theme qo‘llab-quvvatlandi. Mavjud backend qo‘llamaydigan mikrofon/fayl tugmalari soxta funksiya sifatida qo‘shilmadi.
- To‘liq suite: **186 test / 779 assertion**. Vite build, Blade kompilyatsiyasi va JS sintaksisi o‘tdi.
- WebKit: **24 tekshiruv / 0 xato**; 5 rol, 390/768/1440px, tezkor prompt, material turi, kalitsiz test muhitida AI xatosini ko‘rsatish va matn/tugma holatini tiklash. Desktop/mobil screenshotlar ko‘rib chiqildi. Pullik jonli API chaqirilmadi.


## O‘quvchi paneli: oq-ko‘k learning desk — 2026-11-17
- Yuborilgan reference asosida uch qismli dashboard: chap navigatsiya, markazda qidiruv/banner/kurslar/mentor jadvali, o‘ngda profil/progress/haftalik faollik/mentorlar/deadline.
- Oq-ko‘k rang tizimi, yumaloq kartalar, haqiqiy kurs muqovalari uchun private download, muqovasiz kursga huquqiy dekor, har bir kursda progress, natija va davom ettirish havolasi.
- AI Ustoz sidebar, profil actioni va robot tasvirli alohida kartada mavjud; mavjud `/ai` bo‘limiga ulanadi.
- Mentorlar talaba + aktiv published kurslar bilan cheklangan va eager load qilingan. Soxta mentor/avatar/kurs yoki statistika qo‘shilmadi. Suhbat havolalari mavjud private conversation route’lariga boradi.
- Haftalik grafik oxirgi 7 kunning haqiqiy lesson_progress.completed_at yozuvlaridan olinadi; boshqa talabalar, draft darslar va eski kunlar grafik hisobiga qo‘shilmaydi.
- Jadval, imtihonlar, mentorlar, deadline, so‘nggi test natijalari saqlandi. Manual baho kutilishini ko‘rsatish oldindan olingan count orqali ishlaydi; Blade ichidagi ortiqcha query olib tashlandi.
- Mobil drawer: scrim/Escape/focus trap va desktopga o‘tganda reset. Kurs filtri va reset ishlaydi; dark theme va uch til saqlangan.
- QA’da asosiy kontent uchun kirish animatsiyasi olib tashlandi; mobil mentor jadvalining ko‘rinmas accessibility matni sahifani kengaytirishi table scroll containerini position qilish orqali tuzatildi.
- **188 test / 787 assertion** — barchasi o‘tdi. Build, JS sintaksisi va Blade kompilyatsiyasi muvaffaqiyatli.
- Ajratilgan fixture bazasida WebKit: **10 tekshiruv / 0 xato**. 390/768/1280/1440/1800px, AI kirishlari, kurs filtri/reset, mobil menyu, resize va dark surface. Desktop va mobil screenshotlar ko‘rib chiqildi.


## Talabalar uchun huquqiy kutubxona — 2026-11-17
- Oldin student uchun alohida o‘qish/izlanish katalogi bo‘lmagan; AI uchun `legal_sources` boshqa vazifaga ega va kutubxona sifatida ko‘rsatilmagan edi.
- `/library`: qidiruv, material turi/huquq sohasi filtrlari, pagination, manba sahifasi, shaxsiy o‘qish ro‘yxati, saved/reading/completed va konspekt.
- Haqiqiy bazaga **49 material**: 1 Konstitutsiya, 18 kodeks, 11 qonun, 8 TDYU darslik katalogi, 5 izlanish portali, 6 original o‘quv metodik qo‘llanmasi qo‘shildi.
- LexUZdagi sarlavha/URLlar hamda TDYU kataloglari internetda tekshirildi; eski Mehnat/Fuqarolik protsessual kodeksi bilan yangisi adashtirilmadi. `checked_at` faqat havola tekshiruvi sanasi, hujjatning yuridik kuchi haqidagi tasdiq emas.
- Normativ hujjat va kitoblarning to‘liq nusxalari platformaga yuklab olinmagan; ular rasmiy yoki nashriyot sahifasida ochiladi. TDYU kitoblarida manba hisobi/ruxsati kerakligi ko‘rsatilgan. Mahalliy 6 qo‘llanma izlanish metodikasi, rasmiy huquqiy sharh emas.
- AI kontekstidagi tasdiqlangan manbalar bazasi bilan katalog aralashtirilmadi; katalog tavsifi huquq normasining o‘zi sifatida AIga uzatilmaydi.
- `library_resources` va `library_bookmarks` jadvallari; user/resource FK, unique bookmark, private notes, URL:https validatsiyasi, admin-only boshqaruv, draft/archive yashirish va XSS escaping.
- Admin material qo‘shadi/tahrirlaydi, draft/published/archived holatini boshqaradi. URL o‘zgarsa eski tekshiruv sanasi o‘chiriladi. `LegalLibrarySeeder` idempotent va admin tahrirlarini ustidan yozmaydi; foydalanuvchi yoki demo kurs yaratmaydi.
- Student paneli, barcha rollar navigatsiyasi, AI headeri va global search kutubxonaga ulandi. Uch til UI va dark mode mavjud; manba kontenti asl tilida qoladi.
- **201 test / 848 assertion** — barchasi o‘tdi. 13 yangi feature test import/filter/pagination/access/private notes/unsafe URL/admin/XSS/global qidiruvni tekshiradi.
- WebKit **24 tekshiruv / 0 xato**: student/admin, 390/768/1440px, katalog, filtr, Konstitutsiya, darslik, qo‘llanma, o‘qish ro‘yxati, konspektni saqlash/tiklash va admin chegarasi. Screenshot ko‘rib chiqildi.
- Vite build, Blade va route cache kompilyatsiyasi, Pint muvaffaqiyatli. Git metama’lumoti yo‘qligi sabab Pint aniq fayllarga ishlatildi.
- Lokal migratsiya/import bajarildi, bazadagi tur bo‘yicha sonlar tekshirildi. Yangi backup va ajratilgan restore yaxlitlik tekshiruvi o‘tdi; production deploy bajarilmadi.


## Tashqi katalogdan saytning o‘z kutubxonasiga o‘tish — 2026-11-17
- Foydalanuvchi talabi aniqlandi: tashqi havolalar yetarli emas. O‘qiladigan mahalliy nusxalar bazaga/private storage’ga qo‘shildi.
- Haqiqiy import **30 rasmiy hujjat** (Konstitutsiya + 18 kodeks + 11 qonun) va **2 to‘liq PDF darslik** uchun muvaffaqiyatli tugadi. Importni qayta bajarish 32 nusxani qayta yuklamasdan tekshirdi; failures=0.
- Oldingi 6 mahalliy o‘quv qo‘llanma bilan **38 material sayt ichida o‘qiladi**. Katalog jami 51 ta; yopiq 8 TDYU kitobi va 5 izlanish portali hanuz tashqi manba sifatida aniq belgilangan, ularning fayllari ko‘chirilmagan.
- Kitoblar: David Kline va David Kappos, *Introduction to Intellectual Property* (OpenStax / Michelson 20MM, 2021, CC BY 4.0); Gitman va hammualliflar, *Introduction to Business* (OpenStax / Rice University, 2018, CC BY 4.0). Litsenziya va attribution asl PDF ichida tekshirildi; PDFlar o‘zgartirilmagan. Ingliz tilida, AQSh kontekstidagi o‘quv materiallari, O‘zbekiston qonunchiligining sharhi emas. Ular AI/RAGga uzatilmadi.
- `/library/{resource}/read`: mahalliy oddiy matn, sahifalash, mundarija, to‘liq matn bo‘yicha qidiruv va sahifaga o‘tish; mahalliy PDFni browser viewer orqali ochish. PDFni ko‘rsata olmaydigan mobil brauzerda shu saytdan download mavjud.
- `/library/{resource}/file`: autentifikatsiya, published/admin ruxsati, private library path allowlist, same-origin file response, PDF range va download. Reader tashqi viewer yoki CDNga bog‘liq emas.
- Fayl SHA-256, hajm, nusxa olingan sana, asl URL, litsenziya va attribution saqlanadi. LexUZ HTMLdan rasmiy paragraf/sarlavhalar ajratilib, interfeys, script va tahririy sharhlar chiqarildi. Jadval/format matnga o‘zgartirilgani va nusxa yuridik tahrirni kafolatlamasligi ko‘rsatiladi. Hujjatlar avtomatik yangilanmaydi.
- Metadata tahririda import qilingan body alohida o‘zgartirilmaydi; matn/fayl/checksum mosligi saqlanadi. Yangilash faqat explicit `--refresh` import bilan. Import xatosi eski nusxani saqlaydi; allowlist, timeout va hajm limitlari mavjud.
- Backup endi `library` private fayllarini ham qamrab oladi; ajratilgan restore tekshiruvida **34 fayl checksum** tasdiqlandi, jonli baza almashtirilmadi.
- **216 test / 911 assertion** o‘tdi. 15 yangi feature test: extractor, manba cheklovi, import, PDF/truncated response rad etish, idempotency, source protection, pagination, qidiruv, XSS, private fayl ruxsati va local filter.
- WebKit **20 tekshiruv / 0 xato**: 390/768/1440px, local katalog, hujjat sahifalari, matn qidiruvi, same-origin PDF, haqiqiy PDF range/signature, to‘liq matn download, tashqi resurs so‘rovlari yo‘qligi. Reading sahifasi uchun kontentni yashiruvchi kirish animatsiyasi o‘chirildi.
- Migratsiya/import haqiqiy lokal bazada bajarildi. Pint, Vite build, Blade/route cache tekshiruvi o‘tdi. Yangi dependency qo‘shilmadi.

## Master Product, Engineering & Validation Directive — 2026-11-20
- **PHASE 0 dan PHASE 15 gacha** to‘liq tadqiqot, tahlil va ishlab chiqish rejalari yakunlandi va `docs/` ostida standartlashtirildi.
- **Boshqaruv markazi ko‘rsatkichlari (Phase 4):** `DashboardController::controlCenterKpis()` dagi qotirilgan soxta trendlar o‘rniga real 30 kunlik bazaviy deltalarni hisoblash joriy etildi; faollik lentasida faqat yakunlangan sessiyalar *"Virtual sud sessiyasi yakunlandi"* deb chiqishi, faol sessiyalar esa *"Virtual sud sessiyasi boshlandi"* deb ko‘rsatilishi ta’minlandi. Yangi integratsion test: `tests/Feature/ControlCenterMetricsTest.php`.
- **Amaliy kazuslar (Module A):** `DemoAcademySeeder` to‘ldirilib, Fuqarolik huquqi (og‘zaki shartnoma, avans to‘lovi va javobgarlik) hamda Jinoyat huquqi (zaruriy mudofaa va uning chegarasi, JK 37-moddasi) bo‘yicha 2 ta boy faktli, chop etilgan kazus bazaga qo‘shildi (`/learn/cases`).
- **Virtual sud ssenariylari:** Barcha 4 ta sud ssenariysi (2 ta fuqarolik shartnoma/ijara nizosi, 1 ta xizmat/iste’molchi ta’miri nizosi, 1 ta jinoiy o‘g‘rilik ishi) to‘liq tasdiqlanib, `published` holatiga o‘tkazildi.
- **LAN ko‘p foydalanuvchili sinov (Phase 5):** `docs/testing/LAN_SETUP_GUIDE.md` va `MULTIPLAYER_TEST_CASES.md` tayyorlandi.
- **Yakuniy test to‘plami:** **325 test / 324 o‘tdi, 1 ta o‘tkazib yuborildi (Playwright sharti), 1 821 tasdiq** — 0 xatolik. Pint muvaffaqiyatli.
