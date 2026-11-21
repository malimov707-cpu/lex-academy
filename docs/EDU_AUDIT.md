# HUQUQ AI ACADEMY — PHASE 0: EDU AUDIT

Sana: 2026-11-15

## 1. Repository haqiqiy holati

Promptda keltirilgan "mavjud stack" (FastAPI backend, Python RAG pipeline, TypeScript + Vite + native DOM frontend, Admin Control Center, legal corpus API) **bu repositoryda MAVJUD EMAS**.

Repo audit natijasi:

| Component | Prompt claim | Real holat |
|---|---|---|
| Backend | FastAPI (Python) | ❌ Mavjud emas — repo **Laravel 13** (PHP 8.4) skeleton |
| Frontend | TypeScript + Vite + native DOM | ❌ Blade + Vite + Tailwind 4 (default skeleton) |
| Database | "Mavjud database" | ❌ Bo'sh — faqat default Laravel migratsiyalari (users, cache, jobs), SQLite |
| AI / RAG | Python RAG pipeline | ❌ Mavjud emas. `config/services.php` da AI kalitlari yo'q |
| Admin Control Center | Mavjud | ❌ Mavjud emas |
| Auth | Mavjud | Laravel 13 default — auth scaffolding hali o'rnatilmagan (`routes/web.php` da faqat `/`) |
| Tests | Mavjud | Faqat default Pest example test |
| Legal corpus / API | Mavjud | ❌ Mavjud emas |

**Xulosa:** bu toza Laravel 13 skeleton. Shuning uchun "mavjud HUQUQ AI funksiyalarini buzmaslik" talabasi amalda qo'llanmaydi — hech narsa buzilishi mumkin emas. Akademiya tizimi Laravel stack asosida noldan quriladi; FastAPI qatlami keyinchalik parallel integratsiya nuqtasi sifatida ulanishi mumkin (chuqurlikka `AI_INTEGRATION` bo'limiga qarang).

## 2. Aniqlangan stack (amaldagi)

- PHP 8.4, Laravel Framework 13.42.0
- SQLite (DB_CONNECTION=sqlite), migratsiyalanmagan holatda emas — default schema
- Vite 8, Tailwind CSS 4, Blade
- Pest 5 test framework
- Laravel Boost dev tooling
- Auth: Laravel session-based (Heimdal/Laravel 13 default auth controllerlar hali install qilinmagan)

## 3. Integratsiya nuqtalari (ta'lim moduli uchun)

1. **Auth** — `users` jadvalini kengaytirish (role enum, phone, avatar) + Laravel session auth.
2. **AI** — AI Ustoz uchun adapter pattern: `App\Services\Ai\AiDriver` interfeysi. Kalit bo'lmasa, driver `disabled` holatda aniq xato qaytaradi (fake javob qaytarmaydi — prompt talabi). Env: `AI_TUTOR_DRIVER`, `AI_TUTOR_API_KEY`. RAG/legal corpus FastAPI xizmati keyinroq shu adapter orqali ulanadi.
3. **Storage** — Laravel `Storage` disk (assignment fayllari, dars materiallari).
4. **Notifications** — Laravel `Notification` + in-app `notifications` jadvali.

## 4. Ma'lumotlar modeli (qo'shiladigan)

Promptdagi entity ro'yxati Laravel konvensiyasiga moslashtirildi:

- `education_categories`, `courses`, `course_modules`, `lessons`, `lesson_blocks`, `lesson_materials`
- `course_enrollments`, `education_groups`, `group_members`, `teacher_assignments`, `mentor_assignments`
- `lesson_progress`
- `assignments`, `assignment_submissions`, `submission_files`, `mentor_reviews`
- `questions`, `question_options`, `quizzes`, `quiz_questions`, `quiz_attempts`, `quiz_answers`
- `exams`, `exam_attempts`, `exam_answers` (quiz engine bilan umumiy infrastruktura)
- `legal_cases`
- `learning_profiles`, `ai_tutor_sessions`, `ai_tutor_messages`
- `attendance_records`, `class_schedules`
- `certificates`, `certificate_verifications`
- `announcements`, `notifications` (Laravel default)
- `education_audit_logs`

Kurs/lesson ga `language` (uz-latin, uz-cyr, ru) maydoni — i18n talabi.

## 5. Role & permission matritsasi

Rollar: `super_admin`, `academy_admin`, `teacher`, `mentor`, `student` (`users.role` enum + Gate'lar).

| Amal | super_admin | academy_admin | teacher | mentor | student |
|---|---|---|---|---|---|
| Kurs CRUD (barchasi) | ✅ | ✅ | ❌ | ❌ | ❌ |
| O'z kursini CRUD / publish | ✅ | ✅ | ✅ (assigned) | ❌ | ❌ |
| Enrollment boshqarish | ✅ | ✅ | ❌ | ❌ | ❌ |
| Topshiriq tekshirish / baho | ✅ | ✅ (view) | ❌ | ✅ (assigned students) | ❌ |
| Topshiriq yuborish | ❌ | ❌ | ❌ | ❌ | ✅ |
| Foydalanuvchi boshqarish | ✅ | ✅ (staff/student) | ❌ | ❌ | ❌ |
| Audit logs / platform settings | ✅ | qisman | ❌ | ❌ | ❌ |
| Attendance belgilash | ✅ | ✅ | ✅ (o'z guruhlari) | ❌ | ❌ |

Backend tekshiruv: Gate classes (`App\Gateways\...` / `App\Policies`) + middleware `role:`. Frontendda faqat yashirish yetarli emas.

## 6. Risklar

1. **Prompt vs repo nomuvozligi** — eng katta risk. FastAPI/RAG qatlamini "ko'rsatib" qo'ymaslik kerak; AI integratsiya kaliti yo'q bo'lsa ochiq xato beradi.
2. **Scope kattaligi** — 10 faza, ~40 jadval. Bir javobda hammasi emas; fazalar bo'yicha ketma-ket ishlanadi, progress fayl yuritiladi.
3. **SQLite** — production uchun MySQL/Postgres tavsiya; migratsiyalar portable yoziladi.
4. **Laravel 13 yangi** — API lar hujjatlariga moslash kerak (search-docs ishlatiladi).
5. **Fayl yuklash xavfsizligi** — mime/size validatsiya, storage disk izolyatsiyasi.

## 7. Implementation plan

- **PHASE 1** (bu javobda boshlanadi): role tizimi, users migratsiyasi, ta'lim asos migratsiyalari (categories, courses, modules, lessons, enrollments, groups, assignments), Gate/middleware, rol dashboardlari, seeders, Pest testlar.
- Keyingi fazalar `docs/EDU_IMPLEMENTATION_PROGRESS.md` da kuzatiladi.
