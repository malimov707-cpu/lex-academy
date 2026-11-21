# HUQUQ AI Academy — ishga tushirish va xizmat ko‘rsatish

## Amaldagi holat

Laravel 13 / PHP 8.4 / Blade / Tailwind 4. Yangi paket qo‘shilmadi. PHASE 6–9 ishlari mavjud Laravel ilovasi va bazasiga ulandi. Parallel backend yoki mobil ilova yaratilmagan.

Bu hujjat deploy bajarilganini bildirmaydi. Production server, domen, HTTPS, offsite backup, monitoring servisi va jonli AI provayderi bu sessiyada sozlanmagan. Lokal, ajratilgan fixture bazasida WebKit brauzer tekshiruvlari bajarildi; 2026-11-17 davom ettirishda dashboard/kurs/dars/analitika va mobil menyu bo‘yicha 14 tekshiruv xatosiz o‘tdi. Production yuklama testi bajarilmadi. HTTP feature testlari, frontend build hamda lokal guest sahifalari uchun 60 so‘rovli concurrency=4 smoke tekshiruvi bajarildi.

## Production konfiguratsiyasi

- PHP 8.4 va `composer.lock` bilan mos PHP extensionlar, persistent database va persistent private storage kerak.
- Web server document root faqat `public/` bo‘lsin. `.env`, `database/`, `storage/app/private/` va `storage/app/backups/` internetga ochilmasin.
- `APP_ENV=production`, `APP_DEBUG=false`, haqiqiy `APP_URL`, kuchli `APP_KEY`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` o‘rnating. HTTPS va reverse proxy ishonch sozlamalarini serverga moslang.
- Demo hisoblar parollarini almashtiring. `AcademySeeder` productionda ishlashni rad etadi; default seeder ham demo uchun, productionda seed ishlatmang.
- Sana/vaqtlar `config/app.php` dagi ilova timezone’ida talqin qilinadi (hozir UTC). Serverga chiqishda yagona vaqt siyosatini tanlang; mavjud timestamp ma’lumotlarini rejasiz boshqa timezone’ga o‘tkazmang.
- `AI_TUTOR_API_KEY`, `AI_TUTOR_BASE_URL`, `AI_TUTOR_MODEL` ni konfiguratsiya qiling. Kalitsiz holat mahsulotda haqiqiy xato bilan ko‘rsatiladi. AI providerga dars/kazus konteksti yoki tekshirilayotgan javob yuborilishi tashkilotning ma’lumotlar siyosatiga mos bo‘lishi kerak.
- Bu repoda tashqi legal corpus yoki semantik RAG pipeline yo‘q. `legal_sources` orqali faqat inson tasdiqlagan global/joriy kurs parchalari cheklangan miqdorda AI kontekstiga olinadi. AI huquqiy manbalarni mustaqil tekshirmaydi. Jonli model sifati va promptga amal qilishi alohida tekshirilishi kerak.

## Release tartibi

1. O‘zgarishlardan oldin tekshirilgan backup oling. Database va private fayllar uchun yetarli disk joyini tekshiring.
2. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
3. Build muhitida `npm ci` va `npm run build` bajaring; `public/build` artefaktlarini release bilan yetkazing.
4. Zarur maintenance oynasida `php artisan migrate --force --no-interaction` bajaring. Hech qachon productionda `migrate:fresh` ishlatmang.
5. `php artisan optimize --no-interaction` va PHP workerlarni deployment platformangiz tartibida yangilang.
6. Har daqiqada `php artisan schedule:run --no-interaction` bajariladigan scheduler o‘rnating. `academy:remind-deadlines` soatlik, SQLite `academy:backup` esa productionda ilova timezone’i bo‘yicha 02:00 da ishlaydi. Backup buyruği SQLite uchun; boshqa DB ishlatilsa bu vazifani provider backupiga almashtiring.
7. Bir nechta serverda scheduler ishlasa `onOneServer` uchun umumiy, atomik lock qo‘llaydigan cache talab etiladi. Private fayllar ham barcha kerakli workerlar uchun bir xil storage’da saqlansin.
8. `/up`, login/logout, har bir rol, ruxsatsiz kirish, fayl yuklash/yuklab olish, quiz autosave/final submit, mentor review, jadval va sertifikat tekshiruvini stagingda sinang. Maintenance ishlatilgan bo‘lsa smoke tekshiruvlaridan so‘ng ilovani oching.

## Backup va tiklash

`php artisan academy:backup --no-interaction` SQLite bazasining `VACUUM INTO` snapshotini hamda `submissions` va `lesson-materials` kataloglarini `storage/app/backups/<timestamp>-<random>/` ichiga yozadi. Snapshot `PRAGMA integrity_check` bilan tekshiriladi. Database fayli 0600, backup katalogi 0700 huquqlari bilan yaratiladi.

Database snapshot izchil, ammo fayllarni nusxalash database bilan bitta atomik operatsiya emas. Database va fayllarning bir nuqtadagi nusxasi zarur bo‘lsa, maintenance oynasida yozuvlarni to‘xtating. Backup `.env` va `APP_KEY` ni saqlamaydi: ularni alohida, xavfsiz secret boshqaruvida saqlang.

- Lokal backup server yo‘qolishidan himoya qilmaydi. Nusxalarni shifrlangan offsite storage’ga yetkazish, retention, disk monitoring va backup muvaffaqiyatsizligi alertlarini hostingda o‘rnating.
- Tiklashdan oldin joriy holatning alohida backupini oling, web/queue/scheduler yozuvlarini to‘xtating. Tanlangan nusxadagi `database.sqlite`ni configured DB pathga va private fayllarni configured local diskka qaytaring. SQLite’ning mavjud WAL/SHM holatini faol connection bilan aralashtirmang: barcha workerlar to‘xtagan bo‘lishi kerak.
- Tiklangan bazani avval ajratilgan staging muhitida oching, kerakli migrationlarni tekshiring, fayllar va permissionlarni sinang. `optimize:clear`/`optimize` orqali cache’larni yangi releasega moslang, keyin workerlarni qayta boshlang.
- Ushbu sessiyada lokal backup olish va yaxlitlik tekshiruvi o‘tdi. Ajratilgan lokal restore nusxasi `academy:restore-check <backup-directory>` orqali tekshirildi. Production restore drill, retention va offsite yetkazish tekshirilmagan.

## Monitoring va xavfsizlik tekshiruvi

- `/up` ilovaning ishga tushishi, DB connection va cache o‘qilishini tekshiradi. Bu AI provider yoki storage yozishning to‘liq health testi emas.
- Laravel loglari (`storage/logs`), 5xx, login 429, AI 503, scheduler, disk joyi va backup natijalarini kuzating. Log rotation va retentionni o‘rnating.
- Audit sahifasi ruxsatli adminlarga oxirgi 50 hodisani ko‘rsatadi. Auditda parollar, topshiriq matni yoki AI transcriptlari yozilmaydi. Bu compliance darajasidagi o‘zgarmas tashqi audit arxivi emas.
- Login rate limit: email+IP uchun 5/min, IP uchun 30/min. AI endpointlari 10/min. Fayllar private diskda; yuklab olishda backend ruxsati tekshiriladi.
- Mentor ruxsatlari talaba+kurs bo‘yicha, o‘qituvchilar esa o‘z kurslari bo‘yicha cheklangan. Academy admin super admin yaratolmaydi yoki uning hisobini o‘zgartirolmaydi.
- `composer audit` va `npm audit` sessiyada 0 advisory qaytardi; releaselar bilan qayta tekshiring.

## Funksional chegaralar

- Sertifikatlar barcha published darslar, mentor tasdiqlagan topshiriqlar va yakuniy baholangan testlardan o‘tishni talab qiladi. Draft/arxiv kazuslari sertifikat mezoniga kiritilmaydi. Imtihonning manual bahosi kutilsa sertifikat berilmaydi.
- Sertifikat HTML ko‘rinishida chop etiladi va brauzerning “Save as PDF” funksiyasi orqali saqlanadi; alohida server PDF generatori qo‘shilmagan. Public verification sahifasi kodni bilgan kishiga sertifikatdagi ism va kursni ko‘rsatadi. Qaytarib olingan sertifikat yaroqsiz deb ko‘rsatiladi.
- Test manual bahosi kutilayotganda hisoblangan foiz dastlabki natija sifatida saqlanadi; yakuniy natijaga qo‘lda baholar qo‘shiladi. Matching/ordering javoblari savolda belgilangan JSON formatida kiritiladi.
- Jadval oylik kalendar va ro‘yxat ko‘rinishida. Davomat avtomatik belgilanmaydi. Mentor uchrashuviga faqat shu kursga biriktirilgan mentor olib boruvchi qilib tanlanadi.
- Bildirishnomalar database/in-app orqali ishlaydi, email/SMS yuborilmaydi. Deadline eslatmalari scheduler ishlashiga bog‘liq.
- Hisobotlar database agregatsiyalaridir. Talaba jadvali dastlabki 50 talabani, audit paneli oxirgi 50 hodisani ko‘rsatadi; eksport asosiy agregat ko‘rsatkichlarni beradi.
- 2026-11-16: uch tilli UI kataloglari va til tanlash, 11 blokli muharrir/drag-and-drop, teacher AI qoralamalarining 9 turi, shaxsiy yozishmalar va kurs muhokamalari qo‘shildi. Kurs/darsning o‘qituvchi yozgan kontenti avtomatik tarjima qilinmaydi. Yozishmalarda yangi xabarlar sahifani yangilash bilan olinadi; WebSocket infratuzilmasi qo‘shilmagan.
- AI yaratgan test/kazus/rubric materiallari tahrirlanadigan matn qoralamasidir; tasdiqdan keyin matn bloki sifatida darsga qo‘shiladi. Savollar bankiga avtomatik import yo‘q.
- OpenAI-mos servis sozlangan; model metadata tekshiruvi o‘tdi, chat `credit_balance_exhausted` sabab bloklangan. `.env.example`dagi qiymatlar namunadir. Sozlangandan keyin `php artisan config:clear --no-interaction` va `php artisan academy:check-ai --no-interaction` bajaring. Ulanish muvaffaqiyati huquqiy javoblar sifatini tasdiqlamaydi.

## Release readiness va birinchi administrator

`php artisan academy:readiness --json --no-interaction` lokal release shartlarini tekshiradi va bajarilmagan tekshiruv bo‘lsa exit code 1 qaytaradi. Bu buyruq sozlamalarni o‘zgartirmaydi yoki AI servisiga so‘rov yubormaydi. Muhit/debug, HTTPS URL, cookie xavfsizligi, persistent session, storage/build fayllari, DB/migratsiyalar, administrator mavjudligi va `password` demo parolini tekshiradi. Bu tekshiruv tashqi HTTPS, cron, offsite backup yoki production capacity ishlashini tasdiqlamaydi.

Yangi muhitda birinchi admin uchun kuchli `ACADEMY_BOOTSTRAP_PASSWORD` ni server secret konfiguratsiyasiga vaqtincha kiriting (12+ belgi, katta/kichik harflar va raqam). Config cache mavjud bo‘lsa yangilang. So‘ng `php artisan academy:create-admin admin@example.com --name="Academy Admin" --super --no-interaction` buyrug‘ini o‘zingizning email/ismingiz bilan bajaring. Parol buyruq argumenti yoki natijasiga kiritilmaydi; mavjud hisob o‘zgartirilmaydi. Keyin bootstrap secretni o‘chiring va config cacheni qayta yarating. Ushbu sessiyada haqiqiy administrator hisobi yaratilmagan.

2026-11-16 lokal readiness natijasi: production environment, debug, HTTPS URL, secure cookie, admin mavjudligi va demo parollar tekshiruvlari o‘tmadi. Bu lokal muhitga tegishli natija; lokal `.env` production qilib o‘zgartirilmagan.

Safari native WebDriver sinovi urinib ko‘rildi. Sessiya `Allow remote automation` o‘chirilgani sabab yaratilmagan. Brauzer sinovini davom ettirish uchun Safari Settings → Developer → Allow remote automation foydalanuvchi tomonidan yoqilishi kerak. Tizim sozlamasi bu sessiyada o‘zgartirilmadi va brauzer testi o‘tdi deb belgilanmadi.


## Huquqiy kutubxona katalogi

Migratsiyalardan so‘ng tekshirilgan boshlang‘ich katalogni import qilish:

```sh
php artisan db:seed --class=LegalLibrarySeeder --no-interaction
```

Bu demo seeder emas: foydalanuvchi, parol, kurs yoki baho yaratmaydi. `database/seeders/legal-library.json`dagi 49 resursni `catalog_key` bo‘yicha bir marta qo‘shadi; qayta ishlatish adminning mavjud tahrir/arxiv holatini o‘zgartirmaydi. `AcademySeeder` va umumiy demo seederlarni productionda ishlatmaslik qoidasi saqlanadi.

Kutubxona `/library`, boshqaruv `/admin/library`. Nashr qilingan katalog barcha autentifikatsiyalangan rollarga ochiq; shaxsiy konspekt va o‘qish ro‘yxati faqat egasiga ko‘rinadi. Kutubxona o‘qilgan holati kurs completion yoki sertifikat shartlarini o‘zgartirmaydi.

Manba havolalari 2026-11-17 kuni tekshirildi. Kodeks/qonun matnlari LexUZda, TDYU kitoblari nashriyot platformasida ochiladi; ayrim kitoblar uchun tashqi hisob zarur. Qonunchilik matni avtomatik yangilanmaydi yoki mahalliy RAG corpusga import qilinmaydi. Administrator URL, tahrir va foydalanish shartlarini davriy ko‘rib chiqishi kerak.


## Mahalliy to‘liq matn va ochiq darsliklar

```sh
php artisan migrate --no-interaction
php artisan db:seed --class=LegalLibrarySeeder --no-interaction
php artisan library:import-content --no-interaction
```

`library:import-content` ochiq LexUZ hujjatlari va `config/library_content.php`dagi aniq CC BY 4.0 darsliklarini private local storage’ga oladi. `--laws-only`, `--books-only`, `--key=lex-6445145` bilan toraytirish mumkin. Mavjud nusxa saqlanadi; qayta olish uchun `--refresh` kerak. Xatoda exit 1 va eski nusxa saqlanadi. Arxivlangan kitob qayta nashr qilinmaydi. Buyruq pullik AIga murojaat qilmaydi.

Persist qilinadigan qo‘shimcha katalog: `storage/app/private/library`. U DB, backup va offsite nusxaga birgalikda kiritilishi kerak. `academy:backup` shu katalogni qamrab oladi. Katalog/fayllarni `public/`ga symlink qilmang; fayl berish auth route orqali amalga oshadi.

Normativ matn HTMLdan oddiy matnga ajratiladi; original format va jadvallar ko‘rinishi farq qilishi mumkin. Import sanasi amaldagi yuridik kuch tasdig‘i emas. Nusxalar avtomatik yangilanmaydi; kerakli sana/tahrir rasmiy manbada solishtiriladi. Kitoblar original PDF holida tarqatiladi, litsenziya va attribution saqlanadi; ingliz tilidagi xorijiy huquq misollari mahalliy qonun normalari sifatida ko‘rsatilmaydi.
