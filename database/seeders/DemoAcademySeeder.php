<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\CourtroomParticipantGrade;
use App\Models\EducationCategory;
use App\Models\EducationGroup;
use App\Models\GradebookEntry;
use App\Models\LegalDocumentDraft;
use App\Models\LegalSource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MentorAssignment;
use App\Models\MentorReview;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ScenarioRole;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo ma'lumotlari — lokal/QA muhiti uchun.
 * Productionda `academy:readiness` orqali bloklangan (AcademySeeder bilan bir xil qoida).
 */
class DemoAcademySeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@huquq.ai')->first();
        $mentor = User::query()->where('email', 'mentor@huquq.ai')->first();
        $teacher = User::query()->where('email', 'teacher@huquq.ai')->first();

        if (! $student || ! $mentor || ! $teacher) {
            $this->command?->warn('Demo foydalanuvchilar topilmadi — AcademySeeder avval ishlashi kerak.');

            return;
        }

        // Asosiy seederdan kelgan kursga ham mazmunli darslar qo'shamiz
        $existing = Course::query()->where('slug', 'konstitutsiyaviy-huquq-asoslari')->first();
        if ($existing) {
            // AcademySeeder zaten "Konstitutsiya tushunchasi" darsini yaratgan — uni takrorlamaymiz
            $existingLessons = $this->seedCourseStructure($existing, [
                ['Konstitutsiyaviy tuzum asoslari', 'Davlat tuzilishi, suverenitet va hokimiyat bo‘linishi.'],
                ['Inson huquqlari kafolatlari', 'Konstitutsiyada mustahkamlangan asosiy huquq va erkinliklar tizimi.'],
            ]);

            // Birinchi darsni tugallangan qilib belgilaymiz (progress ko‘rinsin)
            if ($first = $existingLessons->first()) {
                LessonProgress::query()->updateOrCreate(
                    ['lesson_id' => $first->id, 'student_id' => $student->id],
                    ['status' => 'completed', 'completion_percent' => 100, 'completed_at' => now()->subDays(5)],
                );
            }
        }

        // Qoralama kurs — nashr qilish oqimini ko'rsatish uchun
        $labor = EducationCategory::query()->firstOrCreate(['slug' => 'mehnat-huquqi'], ['name' => 'Mehnat huquqi']);
        Course::query()->firstOrCreate(
            ['slug' => 'mehnat-huquqi-asoslari'],
            [
                'category_id' => $labor->id,
                'teacher_id' => $teacher->id,
                'title' => 'Mehnat huquqi asoslari',
                'description' => 'Mehnat shartnomasi, ish vaqti va ta’til huquqlari.',
                'difficulty' => 'beginner',
                'language' => 'uz-latin',
                'status' => 'draft',
            ],
        );

        $civil = EducationCategory::query()->firstOrCreate(['slug' => 'fuqarolik-huquqi'], ['name' => 'Fuqarolik huquqi']);
        $criminal = EducationCategory::query()->firstOrCreate(['slug' => 'jinoyat-huquqi'], ['name' => 'Jinoyat huquqi']);

        // ===== Kurs 1: Fuqarolik huquqi (davom etayotgan) =====
        $civilCourse = Course::query()->firstOrCreate(
            ['slug' => 'fuqarolik-huquqi-asoslari'],
            [
                'category_id' => $civil->id,
                'teacher_id' => $teacher->id,
                'title' => 'Fuqarolik huquqi asoslari',
                'description' => 'Shartnoma, mulk va majburiyatlar — amaliy misollar bilan.',
                'difficulty' => 'intermediate',
                'language' => 'uz-latin',
                'status' => 'published',
                'published_at' => now()->subWeeks(3),
            ],
        );

        $civilLessons = $this->seedCourseStructure($civilCourse, [
            ['Shartnoma tushunchasi', 'Shartnoma — ikki yoki undan ortiq tomonning kelishuvi.'],
            ['Bitim tuzilishi va shakli', 'Yozma va og‘zaki bitimlar, notarial tasdiqlash talablari.'],
            ['Mulk huquqi asoslari', 'Egalik, foydalanish va tasarruf etish huquqlari.'],
            ['Majburiyat va javobgarlik', 'Majburiyatning bajarilmasligi oqibatlari.'],
            ['Zar qoplami', 'Yetkazilgan zararni qoplash tartibi va hajmi.'],
        ]);

        // 5 tadan 2 tasi tugallangan
        foreach ($civilLessons->take(2) as $lesson) {
            LessonProgress::query()->updateOrCreate(
                ['lesson_id' => $lesson->id, 'student_id' => $student->id],
                ['status' => 'completed', 'completion_percent' => 100, 'completed_at' => now()->subDays(4)],
            );
        }

        // ===== Kurs 2: Jinoyat huquqi (yangi) =====
        $criminalCourse = Course::query()->firstOrCreate(
            ['slug' => 'jinoyat-huquqi-umumiy-qism'],
            [
                'category_id' => $criminal->id,
                'teacher_id' => $teacher->id,
                'title' => 'Jinoyat huquqi — umumiy qism',
                'description' => 'Jinoyat tarkibi, aybdorlik va jazo tayinlash asoslari.',
                'difficulty' => 'beginner',
                'language' => 'uz-latin',
                'status' => 'published',
                'published_at' => now()->subWeek(),
            ],
        );

        $criminalLessons = $this->seedCourseStructure($criminalCourse, [
            ['Jinoyat tushunchasi', 'Jinoyat — qonunda nazarda tutilgan ijtimoiy xavfli qilmish.'],
            ['Jinoyat tarkibi', 'Subyekt, obyekt, subyektiv va obyektiv tomon.'],
            ['Aybdorlik shakllari', 'Qasd va ehtiyotsizlik — farqlari va oqibatlari.'],
        ]);

        foreach ([$civilCourse, $criminalCourse] as $course) {
            CourseEnrollment::query()->firstOrCreate(
                ['course_id' => $course->id, 'student_id' => $student->id],
                ['status' => 'active', 'enrolled_at' => now()->subWeeks(2)],
            );

            MentorAssignment::query()->firstOrCreate([
                'mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $course->id,
            ]);
        }

        // ===== Topshiriqlar =====
        $assignment1 = Assignment::query()->firstOrCreate(
            ['title' => 'Shartnoma tuzilishi bo‘yicha kazus'],
            [
                'course_id' => $civilCourse->id,
                'lesson_id' => $civilLessons[1]->id,
                'teacher_id' => $teacher->id,
                'description' => 'Berilgan vaziyatda shartnoma shaklini aniqlang.',
                'instructions' => 'Vaziyatni o‘qing, tegishli moddalarni ko‘rsating va xulosa yozing.',
                'type' => 'legal_case',
                'deadline' => now()->addDays(3),
                'max_score' => 100,
                'submission_attempts' => 3,
                'allow_late_submission' => true,
            ],
        );

        if (! $assignment1->legalCase()->exists()) {
            $assignment1->legalCase()->create([
                'category' => 'civil',
                'situation' => 'Fuqaro A. va do‘kon egasi B. o‘rtasida og‘zaki kelishuv asosida 15 mln so‘mlik qurilish mollari yetkazib berish bo‘yicha nizo kelib chiqqan.',
                'facts' => "1. 2026-yil 1-mart kuni fuqaro A. qurilish mollari do‘koni egasi B. bilan og‘zaki ravishda 15 million so‘mlik sement va g‘isht yetkazib berishga kelishgan.\n2. Fuqaro A. bank kartasi orqali B.ning hisobiga 7 million so‘m bo‘nak (avans) o‘tkazgan va to‘lov maqsadi qismida 'sement uchun' deb ko‘rsatgan.\n3. Do‘kon egasi B. mahsulotni 10 kun ichida yetkazib berishni va’da qilgan, ammo 20 kun o‘tsa ham mahsulot yetkazilmagan va pul qaytarilmagan.\n4. B. sudda og‘zaki bitim yuridik kuchga ega emasligini va da’voni tan olmasligini bildirgan.",
                'legal_issue' => "1. Fuqarolar o‘rtasidagi bitimning qonuniy shakliga rioya qilinganmi?\n2. Bank to‘lovidagi izoh yozma shaklga tenglashtiriladimi?\n3. Majburiyatni bajarmaganlik uchun qanday fuqarolik-huquqiy oqibatlar kelib chiqadi?",
                'legislation' => 'O‘zbekiston Respublikasi Fuqarolik kodeksining 106, 107, 108, 109-moddalari (Bitimning shakli va unga rioya qilmaslik oqibatlari); 333-modda (Majburiyatni buzganlik uchun javobgarlik); 236-modda (Majburiyatlarni lozim darajada bajarish).',
                'analysis_questions' => "1. Ushbu vaziyatda Fuqarolik kodeksining 108-moddasi talabiga ko‘ra yozma shakl majburiymi?\n2. Yozma shaklga rioya qilmaslik bitimning haqiqiy emasligiga olib keladimi yoki guvohlarning ko‘rsatmalariga tayanish huquqidan mahrum qiladimi?\n3. Da’vogar A. o‘z talabini isbotlash uchun qanday yozma dalillarni taqdim etishi mumkin?",
                'status' => 'published',
            ]);
        }

        $criminalAssignment = Assignment::query()->firstOrCreate(
            ['title' => 'Zaruriy mudofaa va uning chegarasidan chetga chiqish kazusi'],
            [
                'course_id' => $criminalCourse->id,
                'lesson_id' => $criminalLessons[2]->id,
                'teacher_id' => $teacher->id,
                'description' => 'Hujumni qaytarish paytidagi harakatlarning qonuniyligini tahlil qiling.',
                'instructions' => 'Faktlarni Jinoyat kodeksining 37-moddasi bilan solishtiring va huquqiy baho bering.',
                'type' => 'legal_case',
                'deadline' => now()->addDays(5),
                'max_score' => 100,
                'submission_attempts' => 3,
                'allow_late_submission' => true,
            ],
        );

        if (! $criminalAssignment->legalCase()->exists()) {
            $criminalAssignment->legalCase()->create([
                'category' => 'criminal',
                'situation' => 'Tungi vaqtda ko‘chada sodir bo‘lgan hujum natijasida tajovuzkorga og‘ir tan jarohati yetkazilgan.',
                'facts' => "1. 2026-yil 12-oktabr kuni soat 23:30 da fuqaro K. uyiga qaytayotganida, mast holatdagi fuqaro S. uning yo‘lini to‘sib, qo‘lidagi pichoq bilan pul va telefonini talab qilgan.\n2. K. qochishga uringan, biroq S. uni quvib yetib, yelkasidan ushlagan va pichoq bilan zarba berishga uringan.\n3. K. yerda yotgan temir bo‘lagini olib, S.ning boshiga zarba bergan. Natijada S. hushidan ketgan va unga 'og‘ir tan jarohati' yetgan.\n4. Tergov organi K.ning harakatlarini JKning 104-moddasi 1-qismi bilan tavsiflagan.",
                'legal_issue' => "1. K.ning harakatlarida zaruriy mudofaa holati mavjudmi?\n2. Zaruriy mudofaa chegarasidan chetga chiqish alomatlari bormi yoki qilmish qonuniy mudofaami?\n3. Tergov organining ayblov malakalanishi to‘g‘rimi?",
                'legislation' => 'O‘zbekiston Respublikasi Jinoyat kodeksining 37-moddasi (Zaruriy mudofaa), 38-modda, 104-modda (Qasddan badanga og‘ir shikast yetkazish) va 107-modda (Zaruriy mudofaa chegarasidan chetga chiqib, badanga og‘ir shikast yetkazish); Oliy sud Plenumi qarorlari.',
                'analysis_questions' => "1. Tajovuzning kutilmaganligi va hayot uchun xavfliligini hisobga olganda, K. teng qurol ishlatishga majbur edimi?\n2. JK 37-moddasi 3-qismi talablari ushbu vaziyatga tatbiq etiladimi?\n3. Himoyachi sifatida tergovchiga qanday iltimosnoma kiritgan bo‘lardingiz?",
                'status' => 'published',
            ]);
        }

        Assignment::query()->firstOrCreate(
            ['title' => 'Mulk huquqi bo‘yicha tadqiqot'],
            [
                'course_id' => $civilCourse->id,
                'teacher_id' => $teacher->id,
                'description' => 'Mulk huquqini himoya qilish usullarini tahlil qiling.',
                'type' => 'research',
                'deadline' => now()->addDays(9),
                'max_score' => 100,
                'submission_attempts' => 2,
            ],
        );

        // Bitta topshiriq allaqachon baholangan (mentor feedback ko'rinsin)
        $graded = AssignmentSubmission::query()->firstOrCreate(
            ['assignment_id' => $assignment1->id, 'student_id' => $student->id, 'attempt' => 1],
            [
                'text' => 'Ushbu vaziyatda shartnoma yozma shaklda tuzilishi lozim, chunki summa qonunda belgilangan chegaradan oshadi. Tegishli modda — Fuqarolik kodeksining 354-moddasi.',
                'status' => 'approved',
                'score' => 88,
                'submitted_at' => now()->subDays(2),
            ],
        );

        MentorReview::query()->firstOrCreate(
            ['submission_id' => $graded->id, 'mentor_id' => $mentor->id],
            [
                'score' => 88,
                'comment' => 'Modda to‘g‘ri ko‘rsatilgan. Xulosani yanada mustahkamlash uchun sud amaliyotidan misol keltiring.',
                'decision' => 'approved',
            ],
        );

        // ===== Test / imtihon =====
        $quiz = Quiz::query()->firstOrCreate(
            ['title' => 'Fuqarolik huquqi — 1-modul testi'],
            [
                'course_id' => $civilCourse->id,
                'lesson_id' => $civilLessons[1]->id,
                'kind' => 'quiz',
                'max_attempts' => 3,
                'pass_score_percent' => 60,
                'time_limit_minutes' => 15,
            ],
        );

        if ($quiz->questions()->count() === 0) {
            foreach ([
                ['Shartnoma qanday shaklda tuziladi?', ['Yozma', 'Og‘zaki', 'Faqat notarial'], 0],
                ['Mulk huquqi nimani o‘z ichiga oladi?', ['Egalik', 'Foydalanish', 'Tasarruf etish'], null],
            ] as [$text, $options, $correct]) {
                $question = $civilCourse->questions()->create([
                    'type' => $correct === null ? 'multiple' : 'single',
                    'text' => $text,
                    'points' => 1,
                ]);

                foreach ($options as $index => $option) {
                    $isCorrect = $correct === null ? $index !== 1 : $index === $correct;
                    $question->options()->create(['text' => $option, 'is_correct' => $isCorrect]);
                }

                $quiz->questions()->attach($question->id, ['sort_order' => $quiz->questions()->count() + 1]);
            }
        }

        QuizAttempt::query()->firstOrCreate(
            ['quiz_id' => $quiz->id, 'student_id' => $student->id, 'attempt' => 1],
            ['score' => 75, 'status' => 'submitted', 'started_at' => now()->subDays(3), 'submitted_at' => now()->subDays(3)],
        );

        $exam = Quiz::query()->firstOrCreate(
            ['title' => 'Yakuniy imtihon — Fuqarolik huquqi'],
            [
                'course_id' => $civilCourse->id,
                'kind' => 'exam',
                'max_attempts' => 1,
                'pass_score_percent' => 70,
                'time_limit_minutes' => 60,
                'available_from' => now()->addDays(6),
                'available_until' => now()->addDays(8),
            ],
        );

        if ($student->hasRole(UserRole::Student)) {
            // student roli allaqachon mavjud
        }

        // ===== Jadval (bugungi darslar) =====
        ClassSchedule::query()->firstOrCreate(
            ['course_id' => $civilCourse->id, 'title' => 'Amaliy seminar: shartnoma tahlili', 'starts_at' => today()->setTime(15, 0)],
            [
                'teacher_id' => $teacher->id,
                'ends_at' => today()->setTime(16, 30),
                'location' => 'Asosiy auditoriya',
            ],
        );

        ClassSchedule::query()->firstOrCreate(
            ['course_id' => $criminalCourse->id, 'title' => 'Nazariy dars: jinoyat tarkibi', 'starts_at' => today()->setTime(18, 0)],
            [
                'teacher_id' => $teacher->id,
                'ends_at' => today()->setTime(19, 30),
                'location' => 'Online',
            ],
        );

        // ===== 30 TA DEMO TALABA VA DEMO GURUH =====
        $demoPassword = env('DEMO_USER_PASSWORD', 'HuquqDemo2026!');
        $hashedPassword = Hash::make($demoPassword);

        // Maxfiy demo hisob ma'lumotlarini git-ignored .local faylda xavfsiz saqlash
        $localDir = base_path('.local');
        if (! is_dir($localDir)) {
            @mkdir($localDir, 0700, true);
        }
        $credsFile = $localDir.'/demo-credentials.txt';
        $credsContent = "# HUQUQ AI Academy — Demo Talabalar Kirish Ma'lumotlari\n";
        $credsContent .= "# Faqat lokal va xakaton namoyishi uchun. Ushbu fayl git nazoratidan chiqarilgan.\n\n";
        $credsContent .= "Umumiy demo paroli: {$demoPassword}\n\n";

        $demoStudents = collect();
        for ($i = 1; $i <= 30; $i++) {
            $padded = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $email = "demo.student.{$padded}@example.test";
            $name = "Demo Talaba {$padded}";

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $hashedPassword,
                    'role' => UserRole::Student,
                    'locale' => 'uz-latin',
                    'is_demo' => true,
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->is_demo) {
                $user->update(['is_demo' => true]);
            }

            $demoStudents->push($user);
            $credsContent .= "Talaba {$padded}: {$email} | Parol: {$demoPassword}\n";
        }

        @file_put_contents($credsFile, $credsContent);
        @chmod($credsFile, 0600);

        // Demo guruh yaratish
        $demoGroup = EducationGroup::query()->firstOrCreate(
            ['name' => "DEMO — HUQUQIY TA'LIM GURUHI A"],
            [
                'course_id' => $civilCourse->id,
                'is_demo' => true,
            ]
        );

        // 30 demo talabani guruhga biriktirish va kursga a'zo qilish
        $demoGroup->members()->syncWithoutDetaching($demoStudents->pluck('id')->all());

        foreach ($demoStudents as $ds) {
            CourseEnrollment::query()->firstOrCreate(
                ['course_id' => $civilCourse->id, 'student_id' => $ds->id],
                ['status' => 'active', 'enrolled_at' => now()->subWeeks(2)]
            );
        }

        // ===== VIRTUAL COURTROOM: 30 KISHILIK DEMO SESSIYALAR =====
        $scenario = SimulationScenario::query()->where('legal_area', 'civil')->first()
            ?? SimulationScenario::query()->first();
        $version = $scenario?->versions()->where('status', 'published')->first()
            ?? $scenario?->versions()->first();
        if ($version && $version->status !== 'published') {
            $version->update(['status' => 'published']);
        }

        if ($version && $demoStudents->count() >= 30) {
            // Ssenariy versiyasiga to'liq 9 ta rol va 3 ta guvoh faktlarini kiritish
            $nineRoles = [
                ['key' => 'judge', 'name' => 'Sudya', 'description' => 'Sud majlisini boshqaradi, protsessual qonun talablarini ta’minlaydi va xolis adolatni o‘rnatadi.', 'instructions' => 'Majlis tartibini saqlang, dalillarni xolis tekshiring va har ikki tarafga teng so‘z bering.', 'private_context' => 'Taraflardan hech biriga yon bosmang. Qaror faqat tekshirilgan dalillarga asoslanishi shart.', 'is_student_selectable' => true, 'sort_order' => 1],
                ['key' => 'prosecutor', 'name' => 'Da’vogar vakili (Prokuror)', 'description' => 'Da’vogar da’vosini asoslaydi, dalillarni taqdim etadi va qonun talablarini ilgari suradi.', 'instructions' => 'FK 236, 324-moddalari asosida yetkazilgan zararni undirishni talab qiling.', 'private_context' => 'Shartnoma bo‘yicha tovarlar 10 kun kechiktirib keltirilgan, natijada korxona 25 000 000 so‘m zarar ko‘rgan.', 'is_student_selectable' => true, 'sort_order' => 2],
                ['key' => 'victim_advocate', 'name' => 'Jabrlanuvchi advokati', 'description' => 'Jabrlanuvchi va da’vogar taraf manfaatlarini sudda himoya qiladi.', 'instructions' => 'Yetkazilgan moddiy va ma’naviy zararning haqiqiy hajmini isbotlang.', 'private_context' => 'Buyurtmachi mahsulotlarni o‘z vaqtida ololmagani sababli boshqa hamkorlar bilan tuzilgan shartnomalari bekor bo‘lgan.', 'is_student_selectable' => true, 'sort_order' => 3],
                ['key' => 'victim', 'name' => 'Jabrlanuvchi / Da’vogar', 'description' => 'Huquqi buzilgan jabrlanuvchi va shartnoma bo‘yicha buyurtmachi taraf.', 'instructions' => 'Faktik voqealarni, tuzilgan shartnoma shartlarini va ko‘rgan zararingizni so‘zlab bering.', 'private_context' => 'Javobgar tomon bilan bir necha bor muzokara o‘tkazilgan, ammo ular to‘lovni qaytarishdan bosh tortgan.', 'is_student_selectable' => true, 'sort_order' => 4],
                ['key' => 'defense_advocate', 'name' => 'Ayblanuvchi advokati', 'description' => 'Javobgar tomonning huquqlarini himoya qiladi va da’vo asossizligini isbotlaydi.', 'instructions' => 'Da’vogar keltirgan dalillarning nomaqbulligini va fors-major holatlarni ko‘rsating.', 'private_context' => 'Da’vogar tovarlarni qabul qilishda tegishli dalolatnoma tuzmagan, qolaversa to‘lovning yarmini to‘lamagan.', 'is_student_selectable' => true, 'sort_order' => 5],
                ['key' => 'defendant', 'name' => 'Ayblanuvchi / Javobgar', 'description' => 'Da’vo qilingan qarz va zararga e’tiroz bildiruvchi javobgar tadbirkor.', 'instructions' => 'O‘z pozitsiyangizni tushuntiring, tovarlar nima sababdan kechiktirilganini bayon qiling.', 'private_context' => 'Toshqin va yo‘l yopilishi tufayli yuk tashish 5 kunga majburiy kechikkan. Bu haqda xat yuborilgan.', 'is_student_selectable' => true, 'sort_order' => 6],
                ['key' => 'witness_1', 'name' => '1-Guvoh (Omborxona mudiri)', 'description' => 'Tovarlarni qabul qilish va yuk xatlari bo‘yicha faktik holatlarni tasdiqlovchi guvoh.', 'instructions' => 'Tovarlar omborga qachon kelganligi va qadoq holati haqida guvohlik bering.', 'private_context' => '2026-yil 12-fevralda tovarlar omborga kelgan, 15 ta qutining qadoqlari yirtilgan bo‘lgan, biroq yuk xatiga imzo qo‘yilgan.', 'is_student_selectable' => true, 'sort_order' => 7],
                ['key' => 'witness_2', 'name' => '2-Guvoh (Bosh hisobchi)', 'description' => 'Moliyaviy hisob-kitoblar, bank o‘tkazmalari va qarz hisoboti bo‘yicha guvoh.', 'instructions' => 'Shartnoma bo‘yicha amalga oshirilgan to‘lovlar va qolgan qarz summasini aniq ayting.', 'private_context' => 'Shartnomaning 50 foiz avans to‘lovi 2026-yil 15-yanvarda to‘langan. Qolgan summa hisob-faktura kutilgani uchun to‘lanmagan.', 'is_student_selectable' => true, 'sort_order' => 8],
                ['key' => 'witness_3', 'name' => '3-Guvoh (Mustaqil ekspert)', 'description' => 'Mahsulot sifati va zararni baholash bo‘yicha rasmiy ekspertiza xulosasini beruvchi guvoh.', 'instructions' => 'O‘tkazilgan texnik va tovarshunoslik ekspertizasi xulosasini sudda e’lon qiling.', 'private_context' => 'Ekspertiza xulosasiga ko‘ra, mahsulotdagi nuqsonlar transport vositasidagi noqulay harorat sababli yuzaga kelgan.', 'is_student_selectable' => true, 'sort_order' => 9],
            ];

            foreach ($nineRoles as $rData) {
                ScenarioRole::query()->updateOrCreate(
                    ['scenario_version_id' => $version->id, 'key' => $rData['key']],
                    $rData
                );
            }

            $rolesMap = [
                'judge' => $demoStudents[0],
                'prosecutor' => $demoStudents[1],
                'victim_advocate' => $demoStudents[2],
                'victim' => $demoStudents[3],
                'defense_advocate' => $demoStudents[4],
                'defendant' => $demoStudents[5],
                'witness_1' => $demoStudents[6],
                'witness_2' => $demoStudents[7],
                'witness_3' => $demoStudents[8],
            ];

            // 1. Yakunlangan (Completed) 30 kishilik namoyish sessiyasi
            $completedSession = SimulationSession::query()->firstOrCreate(
                ['group_creation_key' => 'demo-completed-courtroom-session-30'],
                [
                    'facilitator_id' => $teacher->id,
                    'education_group_id' => $demoGroup->id,
                    'course_id' => $civilCourse->id,
                    'scenario_id' => $scenario->id,
                    'scenario_version_id' => $version->id,
                    'selected_role_key' => 'group',
                    'status' => 'completed',
                    'started_at' => now()->subHours(2),
                    'completed_at' => now()->subHours(1),
                    'elapsed_seconds' => 1500,
                    'current_stage_key' => 'VERDICT',
                    'settings' => [
                        'winner' => 'defense',
                        'winner_label' => "Himoya tomoni g'alaba qozondi (Da'vo dalillari yetarli emas deb topildi)",
                        'is_demo' => true,
                        'assessment_rubric' => [
                            'version' => '1.0',
                            'scale' => 100,
                        ],
                    ],
                ]
            );

            // 9 ta faol ishtirokchini biriktirish va baholarini shakllantirish
            foreach ($rolesMap as $roleKey => $studentUser) {
                $completedSession->participants()->firstOrCreate(
                    ['user_id' => $studentUser->id],
                    [
                        'role_key' => $roleKey,
                        'actor_type' => 'student',
                        'display_name' => $studentUser->name,
                        'status' => 'present',
                        'metadata' => ['attended_at' => now()->subHours(2)->toIso8601String()],
                    ]
                );

                CourtroomParticipantGrade::query()->firstOrCreate(
                    [
                        'simulation_session_id' => $completedSession->id,
                        'student_id' => $studentUser->id,
                    ],
                    [
                        'course_id' => $civilCourse->id,
                        'role_key' => $roleKey,
                        'rubric_version' => '1.0',
                        'ai_recommendation' => [
                            'status' => 'preliminary',
                            'score' => 88,
                            'criteria' => ['procedural_compliance' => 90, 'legal_argumentation' => 86],
                            'evidence' => ['presented' => 2, 'valid' => 2],
                        ],
                        'criteria' => ['procedural_compliance' => 90, 'legal_argumentation' => 86],
                        'evidence' => ['presented' => 2, 'valid' => 2],
                        'individual_score' => 88.5,
                        'activity_score' => 9.5,
                        'simulation_performance' => 90.0,
                        'teacher_final_grade' => 89.0,
                        'status' => 'approved',
                        'approved_by' => $teacher->id,
                        'approved_at' => now()->subHour(),
                        'attendance' => 'present',
                        'activity' => [
                            'davomat' => 2,
                            'tayyorgarlik' => 2,
                            'faollik' => 2,
                            'huquqiy_fikrlash' => 1.8,
                            'jamoaviy_intizom' => 1.7,
                        ],
                        'gradebook_columns' => ['individual_role_score', 'classroom_activity', 'teacher_final_grade'],
                        'comment' => 'Faol ishtirok etdi. Huquqiy asoslar to‘g‘ri keltirildi.',
                    ]
                );

                GradebookEntry::query()->firstOrCreate(
                    [
                        'course_id' => $civilCourse->id,
                        'student_id' => $studentUser->id,
                        'simulation_session_id' => $completedSession->id,
                        'column_key' => 'courtroom_simulation',
                    ],
                    [
                        'score' => 89.0,
                        'evaluator_id' => $teacher->id,
                        'approved_at' => now()->subHour(),
                    ]
                );
            }

            // 21 ta kuzatuvchini biriktirish
            for ($obs = 9; $obs < 30; $obs++) {
                $obsUser = $demoStudents[$obs];
                $completedSession->participants()->firstOrCreate(
                    ['user_id' => $obsUser->id],
                    [
                        'role_key' => 'observer_'.$obsUser->id,
                        'actor_type' => 'observer',
                        'display_name' => $obsUser->name,
                        'status' => 'present',
                        'metadata' => ['attended_at' => now()->subHours(2)->toIso8601String()],
                    ]
                );
            }

            // 2. Rejalashtirilgan / Faol Jonli Demo sessiya
            $activeSession = SimulationSession::query()->firstOrCreate(
                ['group_creation_key' => 'demo-scheduled-courtroom-session-30'],
                [
                    'facilitator_id' => $teacher->id,
                    'education_group_id' => $demoGroup->id,
                    'course_id' => $civilCourse->id,
                    'scenario_id' => $scenario->id,
                    'scenario_version_id' => $version->id,
                    'selected_role_key' => 'group',
                    'status' => 'active',
                    'started_at' => now(),
                    'current_stage_key' => $version->stages()->first()?->key ?? 'OPENING',
                    'settings' => [
                        'is_demo' => true,
                    ],
                ]
            );

            // Faol sessiyaga ham barcha 30 ta talabani biriktirish (9 ta faol rol + 21 kuzatuvchi)
            foreach ($rolesMap as $roleKey => $studentUser) {
                $activeSession->participants()->firstOrCreate(
                    ['user_id' => $studentUser->id],
                    [
                        'role_key' => $roleKey,
                        'actor_type' => 'student',
                        'display_name' => $studentUser->name,
                        'status' => 'present',
                        'metadata' => ['attended_at' => now()->toIso8601String()],
                    ]
                );
            }

            for ($obs = 9; $obs < 30; $obs++) {
                $obsUser = $demoStudents[$obs];
                $activeSession->participants()->firstOrCreate(
                    ['user_id' => $obsUser->id],
                    [
                        'role_key' => 'observer_'.$obsUser->id,
                        'actor_type' => 'observer',
                        'display_name' => $obsUser->name,
                        'status' => 'present',
                        'metadata' => ['attended_at' => now()->toIso8601String()],
                    ]
                );
            }
        }

        // ===== RASMIY LEX.UZ HUQUQIY MANBALARI =====
        $adminUser = User::query()->where('role', UserRole::SuperAdmin)->first()
            ?? User::query()->where('role', UserRole::AcademyAdmin)->first();

        if ($adminUser) {
            LegalSource::query()->firstOrCreate(
                ['url' => 'https://lex.uz/docs/-6445145'],
                [
                    'title' => 'O‘zbekiston Respublikasi Konstitutsiyasi (Yangi tahrir)',
                    'excerpt' => 'O‘zbekiston — suveren, demokratik, huquqiy, ijtimoiy va dunyoviy davlat. Inson, uning hayoti, erkinligi, sha’ni, qadr-qimmati va boshqa ajralmas huquqlari oliy qadriyatdir.',
                    'course_id' => $civilCourse->id,
                    'created_by' => $adminUser->id,
                    'verified_by' => $adminUser->id,
                    'status' => 'verified',
                    'verified_at' => now()->subDays(10),
                ]
            );

            LegalSource::query()->firstOrCreate(
                ['url' => 'https://lex.uz/docs/-111189'],
                [
                    'title' => 'O‘zbekiston Respublikasining Fuqarolik kodeksi',
                    'excerpt' => 'Fuqarolik qonunchiligi fuqarolik muomalasi ishtirokchilarining huquqiy holatini, mulk huquqi va boshqa ashyoviy huquqlarning vujudga kelishi hamda ularni amalga oshirish tartibini belgilaydi.',
                    'course_id' => $civilCourse->id,
                    'created_by' => $adminUser->id,
                    'verified_by' => $adminUser->id,
                    'status' => 'verified',
                    'verified_at' => now()->subDays(10),
                ]
            );

            LegalSource::query()->firstOrCreate(
                ['url' => 'https://lex.uz/docs/-111453'],
                [
                    'title' => 'O‘zbekiston Respublikasining Jinoyat kodeksi',
                    'excerpt' => 'Ushbu Kodeksning vazifalari shaxsni, uning huquq va erkinliklarini, jamiyat va davlat manfaatlarini, mulkni, tabiiy muhitni, tinchlikni, insoniyat xavfsizligini jinoiy tajovuzlardan qo‘riqlashdir.',
                    'course_id' => $criminalCourse->id,
                    'created_by' => $adminUser->id,
                    'verified_by' => $adminUser->id,
                    'status' => 'verified',
                    'verified_at' => now()->subDays(10),
                ]
            );

            // ===== DEMO SERTIFIKAT (SAMPLE WATERMARK) =====
            $firstDemoStudent = $demoStudents->first();
            if ($firstDemoStudent) {
                Certificate::query()->firstOrCreate(
                    [
                        'course_id' => $civilCourse->id,
                        'student_id' => $firstDemoStudent->id,
                    ],
                    [
                        'issued_by' => $adminUser->id,
                        'verification_code' => 'DEMO-CERT-2026-HQ-001-VAL',
                        'student_name' => $firstDemoStudent->name,
                        'course_title' => $civilCourse->title,
                        'issuer' => 'HUQUQ AI Academy',
                        'final_result' => 92.5,
                        'completed_at' => now()->subDays(2),
                        'issued_at' => now()->subDays(2),
                    ]
                );

                LegalDocumentDraft::query()->firstOrCreate(
                    [
                        'user_id' => $firstDemoStudent->id,
                        'title' => 'Qarzni undirish to‘g‘risida da’vo arizasi',
                    ],
                    [
                        'document_type' => 'claim',
                        'content' => "Fuqarolik ishlari bo‘yicha Mirzo Ulug‘bek tumanlararo sudiga\n\nDa’vogar: Aliyev Rustam (Toshkent sh., Buyuk Ipak Yo‘li 12)\nJavobgar: Karimov Olim (Toshkent sh., Parkent 45)\n\nDA’VO ARIZASI\n(Qarz summasini undirish to‘g‘risida)\n\n1. Ish holatlari: 2026-yil 10-yanvarda taraflar o‘rtasida qarz shartnomasi tuzilgan. Javobgar 15 000 000 so‘m qarz olgan va 2026-yil 1-martga qadar qaytarish majburiyatini olgan, biroq majburiyat bajarilmagan.\n\n2. Huquqiy asoslar: O‘zR Fuqarolik kodeksining 236, 732, 735-moddalari, FPK 189, 191-moddalari.\n\n3. Talablar: Suddan javobgar Karimov Olimdan 15 000 000 so‘m asosiy qarz va davlat boji xarajatlarini undirib berishingizni so‘rayman.\n\nIlovalar: 1. Qarz tilxati nusxasi; 2. Davlat boji to‘langanligi kvitansiyasi; 3. Talabnoma nusxasi.",
                        'version' => 1,
                        'analysis' => [
                            'score' => 92,
                            'overall_score' => 92,
                            'structure_score' => 95,
                            'legal_score' => 95,
                            'style_score' => 85,
                            'breakdown' => [
                                'structure' => 24,
                                'legal_basis' => 33,
                                'style' => 18,
                                'completeness' => 17,
                            ],
                            'strengths' => [
                                'FPK 189-moddasi talablariga muvofiq barcha rekvizitlar to‘liq ko‘rsatilgan.',
                                'FK 236 va 735-moddalariga aniq havolalar mavjud.',
                                'Ilovalar va davlat boji to‘lovi ko‘zda tutilgan.',
                            ],
                            'missing_elements' => [],
                            'detected_articles' => ['FK 236', 'FK 732', 'FK 735', 'FPK 189', 'FPK 191'],
                            'recommendations' => [
                                'Da’vogarning telefon raqami va elektron pochta manzilini ham kiritish tavsiya etiladi.',
                            ],
                            'summary' => 'Ajoyib natija! Hujjat tuzilishi va qonuniy asoslanishi professional darajada.',
                        ],
                        'status' => 'analyzed',
                    ]
                );
            }
        }

        $this->command?->info(sprintf(
            'Demo: %s dars, %s topshiriq, %s test, %s jadval yozuvi',
            Lesson::query()->count(),
            Assignment::query()->count(),
            Quiz::query()->count(),
            ClassSchedule::query()->count(),
        ));

        unset($criminalLessons, $exam);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $lessons
     * @return Collection<int, Lesson>
     */
    private function seedCourseStructure(Course $course, array $lessons): Collection
    {
        $module = CourseModule::query()->firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Kirish moduli'],
            ['description' => 'Asosiy tushunchalar', 'sort_order' => 1],
        );

        // Mavjud darslarni qayta yozmaslik uchun tartibni mavjud sonidan boshlaymiz
        $offset = $module->lessons()->count();

        $collection = collect();

        foreach ($lessons as $index => [$title, $body]) {
            $collection->push(Lesson::query()->firstOrCreate(
                ['module_id' => $module->id, 'title' => $title],
                [
                    'description' => Str::limit($body, 80),
                    'body' => $body,
                    'objectives' => ['Asosiy tushunchani bilish', 'Amaliy misolda qo‘llay olish'],
                    'completion_criteria' => ['Darsni o‘qib chiqish'],
                    'status' => 'published',
                    'sort_order' => $offset + $index + 1,
                ],
            ));
        }

        return $collection;
    }
}
