<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\LegalSource;
use App\Models\Lesson;
use App\Models\User;

test('authenticated users can view legal update center', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('legal-updates.index'));

    $response->assertOk();
    $response->assertSee('Qonunchilik monitoringi markazi');
    $response->assertSee('Avtomatik tekshiruv hali ishga tushmagan');
    $response->assertSee('huquqshunos tekshiruvi zarur');
});

test('legal updates displays tracked lex uz sources', function (): void {
    $user = User::factory()->create();
    $verifier = User::factory()->academyAdmin()->create();
    LegalSource::factory()->create([
        'title' => 'O‘zbekiston Respublikasi Mehnat kodeksi',
        'url' => 'https://lex.uz/docs/-6257288',
        'status' => 'verified',
        'verified_by' => $verifier->id,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('legal-updates.index'));

    $response->assertOk();
    $response->assertSee('Mehnat kodeksi');
    $response->assertSee('https://lex.uz/docs/-6257288');
});

test('course-linked source changes show lessons as potential review targets for their teacher', function (): void {
    $teacher = User::factory()->teacher()->create();
    $course = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Mehnat huquqi kursi']);
    $module = CourseModule::factory()->create(['course_id' => $course->id, 'title' => 'Mehnat shartnomasi']);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'Shartnoma tuzish', 'status' => 'published']);
    $source = LegalSource::factory()->create(['course_id' => $course->id, 'status' => 'verified', 'verified_by' => User::factory()->academyAdmin()->create()->id, 'verified_at' => now()]);
    $source->checks()->create(['status' => 'changed', 'checked_at' => now()]);

    $this->actingAs($teacher)->get(route('legal-updates.index'))
        ->assertOk()
        ->assertSee('Mehnat huquqi kursi')
        ->assertSee('Shartnoma tuzish')
        ->assertSee('O‘quv materialiga ta’sir')
        ->assertSee('Ta’sirni qayd etish');
});
