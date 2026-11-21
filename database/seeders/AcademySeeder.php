<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\EducationCategory;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AcademySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Demo hisoblar production muhitida yaratilmaydi.');
        }

        $superAdmin = User::query()->create([
            'name' => 'Super Admin', 'email' => 'super@huquq.ai', 'password' => 'password', 'role' => UserRole::SuperAdmin,
        ]);
        $admin = User::query()->create([
            'name' => 'Academy Admin', 'email' => 'admin@huquq.ai', 'password' => 'password', 'role' => UserRole::AcademyAdmin,
        ]);
        $teacher = User::query()->create([
            'name' => 'Teacher One', 'email' => 'teacher@huquq.ai', 'password' => 'password', 'role' => UserRole::Teacher,
        ]);
        $mentor = User::query()->create([
            'name' => 'Mentor One', 'email' => 'mentor@huquq.ai', 'password' => 'password', 'role' => UserRole::Mentor,
        ]);
        $student = User::query()->create([
            'name' => 'Student One', 'email' => 'student@huquq.ai', 'password' => 'password', 'role' => UserRole::Student,
        ]);

        $categories = collect([
            'Konstitutsiyaviy huquq', 'Fuqarolik huquqi', 'Jinoyat huquqi', "Ma'muriy huquq",
            'Mehnat huquqi', 'Oila huquqi', 'Moliya huquqi', 'Soliq huquqi',
        ])->map(fn (string $name): EducationCategory => EducationCategory::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        ));

        $course = Course::query()->create([
            'category_id' => $categories[0]->id,
            'teacher_id' => $teacher->id,
            'title' => 'Konstitutsiyaviy huquq asoslari',
            'slug' => 'konstitutsiyaviy-huquq-asoslari',
            'description' => 'Konstitutsiyaviy huquqning asosiy tushunchalari.',
            'difficulty' => 'beginner',
            'language' => 'uz-latin',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'title' => 'Kirish moduli',
            'sort_order' => 1,
        ]);

        Lesson::query()->create([
            'module_id' => $module->id,
            'title' => 'Konstitutsiya tushunchasi',
            'objectives' => ['Konstitutsiyaning huquqiy tabiatini tushunish', 'Konstitutsiyaning yuridik kuchini aniqlash', 'Asosiy qonun va oddiy qonun farqini izohlash'],
            'completion_criteria' => ['Dars matnini o‘qib chiqish'],
            'body' => 'Konstitutsiya — davlatning asosiy qonuni bo‘lib, unda davlat tuzilishi, hokimiyat organlari tizimi, inson va fuqaroning asosiy huquq va erkinliklari mustahkamlanadi.

Konstitutsiya yuridik jihatdan alohida o‘rin tutadi: uning normalari boshqa barcha qonun va normativ hujjatlardan ustun turadi. Hech bir qonun Konstitutsiyaga zid bo‘lishi mumkin emas — zid bo‘lsa, u qo‘llanilmaydi.

Asosiy qonun oddiy qonundan uch jihati bilan farqlanadi: qabul qilish tartibi (referendum yoki oliy vakillik organi orqali), yuridik kuchi (barcha normativ hujjatlar undan kelib chiqadi) va o‘zgartirishning murakkabligi (maxsus tartib talab qilinadi).',
            'status' => 'published',
            'sort_order' => 1,
        ]);

        $course->enrollments()->create(['student_id' => $student->id, 'status' => 'active']);
    }
}
