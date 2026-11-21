# HUQUQ AI ACADEMY — AI USTOZ AUDIT HISOBOTI (PHASE 0)

**Sana:** 2026-11-20  
**Tizim:** HUQUQ AI Academy — AI Ustoz (Intelligent Teaching Agent)  
**Holat:** Audit yakunlandi, kamchiliklar va yechimlar aniqlandi  

---

## 1. MAVJUD HOLATNING UMUMIY AUDITI

AI Ustoz moduli dastlab faqat bitta dars ichida (`/courses/{course}/lessons/{lesson}/tutor`) sodda savol-javob ko'rinishida loyihalashtirilgan. U mustaqil AI yuridik o'qituvchi sifatida ishlamayotgan edi.

### 1.1. Frontend kamchiliklari
1. **Mustaqil AI ish maydoni yo'qligi:** `/ai` manzili faqat darslar ro'yxatiga yo'naltiruvchi statik sahifa edi yoki alohida chat oynasi mavjud emas edi.
2. **Suhbatlar tarixi (Multi-thread sessions) yo'qligi:** Foydalanuvchi bir nechta mavzuda parallel suhbat ocha olmasdi. Har bir dars uchun faqat bitta seans mavjud bo'lib, yangi suhbat yaratish, nomini o'zgartirish yoki o'chirish imkoni yo'q edi.
3. **8 xil ta'lim rejimi (Study Modes) UI tanlovi yo'qligi:** Tushuntirish, Test, Amaliyot, Hujjat tahlili, Konspekt, Flashcards, Imtihonga tayyorgarlik, Shaxsiy o'quv rejasi rejimlari ajratilmagan edi.
4. **Interaktiv topshiriq kartochkalari yo'qligi:** Test savollari (A, B, C, D variantlari), flesh-kartalar va amaliy topshiriqlar oddiy matn (markdown) ko'rinishida chiqib, foydalanuvchi to'g'ridan-to'g'ri interaktiv variant bosib javob bera olmas edi.
5. **Ovozli kiritish (Voice input) va yordamchi vositalar:** Mikrofondan ovozli so'rov yuborish, xabardan nusxa olish, qayta generatsiya qilish va topshiriqlarni yuklab olish tugmalari mavjud emas edi.

### 1.2. Backend kamchiliklari
1. **Vazifalarni bajarish mexanizmi (Task Execution Engine) yo'qligi:** Foydalanuvchi "Menga Jinoyat kodeksi 168-moddasi bo'yicha 5 ta test tuz", "O'quv rejasi tuzib ber" yoki "Flesh-kartalar yarat" deganda, tizim buni strukturalangan ta'lim vazifasi sifatida tahlil qilmas va faqat umumiy matn qaytarar edi.
2. **RAG va Milliy Qonunchilik (Lex.uz) bog'lanishi to'liq emasligi:** Bazadagi mavjud `LegalSource` va `LibraryResource` hujjatlari bilan darsdan tashqari umumiy chatda semantik qidiruv va iqtibos (citation) mexanizmi cheklangan edi.
3. **Xavfsizlik va Guardrails:** AI o'qituvchisiga yuridik ta'limdan tashqari provokatsion, xakerlik yoki noqonuniy yo'l-yo'riqlar so'ralganda professional huquqiy etika asosida rad javobini berish mexanizmi tizimlashtirilmagan edi.
4. **AI Health Checker:** Admin va o'qituvchilar uchun AI provayder holatini (`CONNECTED`, `INVALID_API_KEY`, `RATE_LIMITED`, `PROVIDER_ERROR`) tekshiruvchi monitoring moduli mavjud emas edi.

### 1.3. Ma'lumotlar bazasi cheklovlari
1. `ai_tutor_sessions` jadvalida `lesson_id` maydoni `NOT NULL` va `unique(['student_id', 'lesson_id'])` chekloviga ega bo'lgani sababli:
   - Darsga bog'lanmagan erkin (standalone) AI suhbatlari ochib bo'lmas edi.
   - Bitta dars doirasida yoki umumiy mavzuda bir nechta alohida seans ochish imkonsiz edi.
2. `title`, `course_id`, `metadata` ustunlari yo'qligi sababli suhbat mavzusi va yaratilgan interaktiv topshiriqlar holatini saqlab bo'lmas edi.
3. `ai_tutor_messages` jadvalida `metadata` ustuni yo'qligi sababli interaktiv test kartochkalari, flesh-kartalar va qonun moddalari havolalari JSON ko'rinishida saqlanmas edi.

---

## 2. REJALASHTIRILGAN ARXITEKTURA VA TUZATISHLAR

### 2.1. Ma'lumotlar bazasi migratsiyasi
- `database/migrations/2026_11_20_230000_upgrade_ai_tutor_sessions_and_messages_table.php` yaratish:
  - `ai_tutor_sessions` jadvalidagi `unique(['student_id', 'lesson_id'])` indeksini olib tashlash.
  - `lesson_id` ustunini `nullable` qilish.
  - `course_id` (foreignId, nullable), `title` (string, nullable), `metadata` (json, nullable) qo'shish.
  - `ai_tutor_messages` jadvaliga `metadata` (json, nullable) qo'shish.

### 2.2. AITaskExecutor va 8 ta o'quv rejimi
- `app/Services/Ai/AITaskExecutor.php` yaratish:
  - 12 ta tizim amallarini (actions) aniqlash va strukturalangan JSON/kartochka formatida generatsiya qilish:
    - `EXPLAIN_LEGAL_TOPIC` (mavzu tushuntirish + Lex.uz havolalari)
    - `EXPLAIN_LEGAL_ARTICLE` (aniq modda tahlili va amaliyot)
    - `CREATE_STUDY_PLAN` (shaxsiy o'quv rejasi yaratish)
    - `CREATE_QUIZ` (4 variantli interaktiv test yaratish)
    - `START_QUIZ` / `GRADE_QUIZ` (talaba javobini tekshirish va izoh berish)
    - `CREATE_FLASHCARDS` (yuridik atama va qoidalar flesh-kartalari)
    - `CREATE_PRACTICE_TASK` (amaliy kazus/topshiriq)
    - `ANALYZE_LEGAL_CASE` (sud ishi/faktlar tahlili)
    - `ANALYZE_LEGAL_DOCUMENT` (shartnoma yoki da'vo arizasini tekshirish)
    - `SUMMARIZE_LESSON` (dars konspektini chiqarish)
    - `CHECK_LEARNING_PROGRESS` (talaba darajasini baholash)

### 2.3. RAG & Qonunchilik manbalari (Lex.uz)
- `TutorContextBuilder` va `AITaskExecutor` integratsiyasi:
  - `LegalSource` va `LibraryResource` modellaridagi O'zbekiston Respublikasi kodekslari (JK, Fuqarolik kodeksi, MPK, JPK, Mehnat kodeksi) va qonunlari bo'yicha qidiruv.
  - AI javobida modda nomi va Lex.uz rasmiy manba havolasini avtomatik biriktirish.

### 2.4. AI Health Checker & Admin Monitoring
- `app/Services/Ai/AiHealthChecker.php`:
  - `checkHealth()` metodi: Model ulanishi, javob qaytishi, API kalit to'g'riligi va kechikish vaqtini (ms) tekshirish.
  - Statuslar: `CONNECTED`, `INVALID_API_KEY`, `RATE_LIMITED`, `MODEL_UNAVAILABLE`, `PROVIDER_ERROR`, `TIMEOUT`.

### 2.5. Frontend UI/UX
- `/ai` sahifasini to'liq AI Ustoz ish maydoniga aylantirish:
  - Chap panel: Suhbatlar tarixi (qidiruv, yangi suhbat, nomini o'zgartirish, o'chirish).
  - Markaziy panel: Rejim tanlash (8 ta rejim), xabarlar lentasi, interaktiv test kartochkalari, flesh-kartalar slayderi, huquqiy manbalar teglari, audio kiritish va xabar harakatlari.
  - O'ng panel: Dars/Mavzu konteksti, shaxsiy o'quv rejasi va tezkor huquqiy yordamchilar.
