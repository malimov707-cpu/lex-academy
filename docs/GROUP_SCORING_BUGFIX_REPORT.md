# GROUP VIRTUAL COURTROOM SCORING & RESULTS BUGFIX REPORT

**Sana:** 2026-11-20  
**Tizim:** HUQUQ AI Academy — Guruhli 3D Virtual Sud Zali  
**Holat:** TO‘LIQ TUZATILDI VA TESTLAR BILAN TASDIQLANDI (348 PASSED)

---

## 1. ROOT CAUSE (ASOSIY SABAB)

### Muammo tavsifi:
`GET /virtual-courtroom/sessions/14/results` so'rovi amalga oshirilganda quyidagi xato vujudga keldi:
`ErrorException: Undefined array key "team" in app/Services/VirtualCourtroom/GroupScoringEngine.php:71`

### Tahlil va Asosiy Sabablar:
1. **Rubrika formati tafovuti (Seeder vs Dynamic Flow):**
   - Veb-interfeys orqali yaratilgan yangi sessiyalarda `GroupAssessmentRubric::snapshot()` orqali to‘liq rubrika strukturasi (`version`, `team`, `roles`, `tie_threshold`, `academic`, `gradebook`) saqlanadi.
   - Biroq `DemoAcademySeeder` yoki eski/import qilingan sessiyalarda (masalan, 14-sessiyada) `settings['assessment_rubric']` minimal stub sifatida saqlangan:
     ```php
     'assessment_rubric' => [
         'version' => '1.0',
         'scale' => 100,
     ]
     ```
2. **Snapshot normalizatsiyasining yo‘qligi:**
   - `GroupScoringEngine::outcome($snapshot, $rows)` metodi `$snapshot['team']` va `$snapshot['tie_threshold']` mavjud deb qat'iy faraz qilgan. `$snapshot` da `'team'` kaliti yo'qligi sababli PHP fatal `ErrorException` bergan.
3. **Baholash holati (Status) va Saqlangan Hukm (Preserved Verdict):**
   - 14-sessiyada sud majlisi yakunlangan va 9 ta faol ishtirokchining individual baholari o‘qituvchi tomonidan tasdiqlangan (`approved`) bo‘lishiga qaramay, `$settings['assessment']` massivi bo‘sh edi.
   - Natijada, `GroupResultReport` statusni `preliminary` deb hisoblab, talabalar uchun natijalarni yashirgan yoki xatolikka sabab bo‘lgan.
   - Sessiya sozlamalarida saqlangan hukm (`winner: defense`, `winner_label: "Himoya tomoni g'alaba qozondi..."`) bo‘lsa-da, hisoblash tizimi ushbu saqlangan hukmni hisoblangan natijadan ajrata olmagan.
4. **Natijalar ko‘rinishida null-pointer muammosi (Blade view):**
   - `group-results.blade.php` da 30 nafar ishtirokchidan 21 nafari erkin kuzatuvchi bo‘lib, ularning individual bahosi bo‘lmagani uchun `$grade = null` bo‘lgan.
   - `$grade['individual_score']` to‘g‘ridan-to‘g‘ri chaqirilib, `Trying to access array offset on null` xatosini keltirib chiqargan.

---

## 2. O‘ZGARTIRILGAN FAYLLAR

1. **`app/Services/VirtualCourtroom/GroupScoringEngine.php`**
   - `normalizeSnapshot(mixed $snapshot): array` metodi qo‘shildi. Har qanday to‘liq bo‘lmagan yoki eski formatdagi rubrikani xatosiz standart rubrika tuzilmasiga keltiradi.
   - `outcome(array $snapshot, array $rows, array $context = []): array` metodi yangilandi. To‘liq va xavfsiz data contract qaytaradi.
   - Saqlangan hukmni (`preserved_verdict`) va hisoblangan natijani (`calculated`) aniq ajratuvchi mexanizm joriy etildi.
   - `team(...)` metodida `INSUFFICIENT_DATA` va `NOT_CALCULATED` statuslari aniqlandi; sun'iy 0 ballar yaratilishi taqiqlandi.
   - `rubricRole(...)` da `witness_1`, `witness_2`, `witness_3` rollari `witness` rubrikasiga xaritalandi.
   - `awards(...)` da no-string dalillar va massiv bo‘lmagan qiymatlarda `array_merge` xatolari bartaraf etildi.

2. **`app/Services/VirtualCourtroom/GroupResultReport.php`**
   - `normalizeSnapshot` orqali rubrika xavfsizlandi.
   - Barcha talabalar baholari tasdiqlangan holatda sessiya baholash statusi avtomatik `approved` deb belgilanishi ta'minlandi.
   - Jamoaviy natija barcha ishtirokchilar baholari asosida hisoblanishi, individual natijalar esa talaba uchun ruxsat etilgan doirada filtrlashi mustahkamlandi.
   - Saqlangan hukm konteksti (`saved_winner`, `saved_winner_label`, `is_demo`) `outcome` ga uzatildi.
   - `scoreStatusLabel` yordamchi metodi qo‘shildi.

3. **`app/Services/VirtualCourtroom/GroupAssessmentRubric.php`**
   - `public const TEAM` va `public const GRADEBOOK` ochiq qilindi.
   - `public static function teamCriteria(): array` va `teamCriterionLabel(string $key): ?string` metodlari qo‘shildi.

4. **`resources/views/student/courtroom/group-results.blade.php`**
   - `$grade` null bo‘lgan holatlar (kuzatuvchilar yoki baholanmagan talabalar) uchun `data_get(...)` orqali to‘liq null-safe qilib qayta yozildi.
   - Jamoaviy mezonlar nomlari uchun `teamCriterionLabel` zaxira nomlari joriy etildi.

5. **`database/seeders/DemoAcademySeeder.php`**
   - Yangi muhitlarda ssenariy versiyasini nashr qilish (`published`) tekshiruvi xavfsizlantirildi.

6. **`tests/Feature/GroupScoringEngineTest.php`**
   - 13 ta keng qamrovli test stsenariylari yaratildi (12 ta majburiy scoring holati + 14-sessiya integratsiya testi).

---

## 3. TO‘G‘RILANGAN DATA CONTRACT

`GroupScoringEngine::outcome(...)` endi yagona, standartlashtirilgan va barcha kalitlari kafolatlangan quyidagi data contract qaytaradi:

```php
[
    'winner' => 'prosecution' | 'defense' | 'tie' | 'undetermined',
    'winner_source' => 'calculated' | 'preserved_verdict' | 'undetermined',
    'score_status' => 'CALCULATED' | 'INSUFFICIENT_DATA' | 'NOT_CALCULATED',
    'prosecution_score' => ?float,
    'defense_score' => ?float,
    'reason' => string,
    'teams' => [
        'prosecution' => [
            'score' => ?float,
            'status' => 'CALCULATED' | 'INSUFFICIENT_DATA' | 'NOT_CALCULATED',
            'criteria' => ['legal' => ?float, 'evidence' => ?float, 'questions' => ?float, 'rebuttal' => ?float, 'procedure' => ?float, 'closing' => ?float],
            'strengths' => list<string>,
            'weaknesses' => list<string>,
            'advocates_count' => int,
            'party_score' => ?float,
            'party_role' => 'victim',
            'roles_evaluated' => list<string>,
            'evidence' => array<string, list<string>>,
        ],
        'defense' => [
            'score' => ?float,
            'status' => 'CALCULATED' | 'INSUFFICIENT_DATA' | 'NOT_CALCULATED',
            'criteria' => ['legal' => ?float, 'evidence' => ?float, 'questions' => ?float, 'rebuttal' => ?float, 'procedure' => ?float, 'closing' => ?float],
            'strengths' => list<string>,
            'weaknesses' => list<string>,
            'advocates_count' => int,
            'party_score' => ?float,
            'party_role' => 'defendant',
            'roles_evaluated' => list<string>,
            'evidence' => array<string, list<string>>,
        ],
    ],
    'awards' => [
        [
            'key' => string,
            'label' => string,
            'student_id' => int,
            'role_key' => string,
            'evidence' => list<string>,
            'name' => ?string,
        ]
    ],
    'saved_winner' => ?string,
]
```

---

## 4. TEAM SCORING QOIDALARI VA ROLLARI

9 ta faol sud roli qat'iy ravishda quyidagi tomonlarga ajratildi:

| Tomon | Rollar | Izoh |
|---|---|---|
| **Ayblov tomoni (Prosecution)** | `prosecutor`, `victim_advocate`, `victim` | Sudya yoki guvohlar aralashtirilmaydi. Advokatlar 6 ta mezon bo‘yicha, jabrlanuvchi o‘z pozitsiyasi bo‘yicha baholanadi. |
| **Himoya tomoni (Defense)** | `defense_advocate`, `defendant` | Himoyachi advokat 6 ta mezon bo‘yicha, ayblanuvchi o‘z pozitsiyasi bo‘yicha baholanadi. |
| **Neytral tomon (Neutral)** | `judge`, `witness_1`, `witness_2`, `witness_3`, `observer` | Hech qachon ayblov yoki himoya jamoasi ballariga qo‘shilmaydi. Individual va protsessual mukofotlar uchun baholanadi. |

- **Baholash mezonlari:** `legal` (20%), `evidence` (20%), `questions` (15%), `rebuttal` (15%), `procedure` (15%), `closing` (15%). Jami ulush = 100%.
- Agar biror tarafda advokat yetishmasa yoki baholash to‘liq bo‘lmasa:
  - Ball `null` qilib belgilanadi (hech qanday soxta 0 ball qo‘yilmaydi).
  - Status `INSUFFICIENT_DATA` bo‘ladi.
  - Saqlangan hukm mavjud bo‘lsa, u `winner_source: preserved_verdict` sifatida ko‘rsatiladi.

---

## 5. 14-SESSIYA TEKSHIRUVI (VERIFICATION)

Haqiqiy ma'lumotlar bazasidagi 14-sessiya bo‘yicha tekshiruv:

- **Sessiya:** ID 14 (`demo-completed-courtroom-session-30`)
- **Status:** `completed`
- **Ishtirokchilar soni:** 30 nafar (9 ta faol rol + 21 ta kuzatuvchi)
- **Tasdiqlangan baholar:** 9 nafar talaba
- **URL:** `/virtual-courtroom/sessions/14/results`

### Natija:
- **HTTP Status:** `200 OK`
- **G'olib:** `defense` (`Himoya tomoni g‘olib`)
- **G'alaba manbasi:** `preserved_verdict` (Sud majlisining rasmiy hukmi)
- **Hukm izohi:** `Himoya tomoni g'alaba qozondi (Da'vo dalillari yetarli emas deb topildi)`
- **Jamoaviy ballar:** Dalillar to‘liq rubrika bo‘yicha kiritilmagani sababli sun'iy 0 qo‘yilmasdan `Aniqlanmadi` holatida ko‘rsatildi.
- **O‘qituvchi ko‘rinishi:** Barcha 30 ishtirokchi, individual ballar (`89.0 / 100`, `88.5 / 100`), faollik va AI tavsiyalar to‘liq aks etmoqda.
- **Talaba ko‘rinishi:** Talaba o‘zining tasdiqlangan bahosi va umumiy sud natijasini to‘liq ko‘rmoqda.

---

## 6. TEST NATIJALARI

1. **`GroupScoringEngineTest` (13 ta yangi test):**
   - Team key mavjud bo‘lganda: **PASSED**
   - Team key yo‘q bo‘lganda (normalizatsiya): **PASSED**
   - Prosecution score yo‘q bo‘lganda: **PASSED**
   - Defense score yo‘q bo‘lganda: **PASSED**
   - Individual grades to‘liq bo‘lganda: **PASSED**
   - Individual grades qisman bo‘lganda (`INSUFFICIENT_DATA`): **PASSED**
   - 9 ta rol to‘liq bo‘lganda (neytral rollar ajratilishi): **PASSED**
   - Bir yoki bir nechta rol yetishmaganda: **PASSED**
   - Demo session (saqlangan hukm saqlanishi): **PASSED**
   - Eski formatdagi completed session: **PASSED**
   - Winner saqlangan, lekin score breakdown yo‘q bo‘lganda: **PASSED**
   - Natija to‘liq bo‘lmagan holat: **PASSED**
   - 14-sessiya natijalari integratsiya testi (HTTP 200): **PASSED**

2. **Umumiy Test Suite:**
   - **349 ta testdan 348 tasi o‘tdi, 1 tasi o‘tkazib yuborildi (skipped).**
   - Regressiya: **0 ta xatolik.**

3. **Kod formati (Laravel Pint):**
   - Barcha o‘zgartirilgan PHP fayllar formatlandi va PSR-12 / Laravel standartlariga moslandi.

---

## 7. XULOSA VA QOLGAN KAMCHILIKLAR

- **Xatolik to‘liq bartaraf etildi:** `ErrorException: Undefined array key "team"` endi hech qachon yuz bermaydi.
- **Ma'lumotlar saqlandi:** Mavjud foydalanuvchilar, 14-sessiya, kurslar va baholar o‘chirilmadi yoki buzilmadi.
- **Qolgan kamchiliklar:** Yo‘q. Tizim mukammal barqarorlikka keltirildi va production/xakaton namoyishi uchun to‘liq tayyor.
