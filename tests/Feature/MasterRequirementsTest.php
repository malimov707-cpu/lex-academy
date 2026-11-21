<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\EducationCategory;
use App\Models\LegalSource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Ai\TutorContextBuilder;
use App\Services\LearningAnalytics;
use Database\Seeders\EducationCategorySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

it('allows additional assigned teachers to manage a course without changing the primary teacher', function () {
    $course = Course::factory()->create();
    $primary = $course->teacher_id;
    $teacher = User::factory()->teacher()->create();
    TeacherAssignment::query()->create(['course_id' => $course->id, 'teacher_id' => $teacher->id]);
    actingAs($teacher)->get(route('admin.course-settings.edit', $course))->assertOk();
    actingAs($teacher)->put(route('admin.courses.update', $course), ['title' => 'Yangilangan kurs', 'category_id' => $course->category_id,
        'teacher_id' => $primary, 'difficulty' => 'beginner', 'language' => 'uz-latin', 'status' => 'draft'])->assertRedirect();
    expect($course->fresh()->teacher_id)->toBe($primary);
    actingAs(User::factory()->teacher()->create())->get(route('admin.course-settings.edit', $course))->assertForbidden();
});

it('saves all course objectives prerequisites and publication metadata', function () {
    $course = Course::factory()->create();

    actingAs($course->teacher)->put(route('admin.course-settings.update', $course), ['title' => $course->title, 'slug' => 'new-course-slug',
        'category_id' => $course->category_id, 'language' => 'ru', 'difficulty' => 'advanced', 'education_level' => 'Magistratura',
        'duration_weeks' => 12, 'learning_objectives' => "Birinchi maqsad\nIkkinchi maqsad", 'prerequisites' => 'Asosiy bilimlar', 'published_at' => '2027-01-01 10:00'])->assertRedirect();

    expect($course->fresh()->learning_objectives)->toBe(['Birinchi maqsad', 'Ikkinchi maqsad']);
    expect($course->fresh()->prerequisites)->toBe(['Asosiy bilimlar']);
    expect($course->fresh()->slug)->toBe('new-course-slug');
});

it('enforces assignment-specific upload format and size policies', function () {
    Storage::fake('local');
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    $assignment = $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'Fayl siyosati', 'allowed_file_formats' => ['pdf'], 'max_file_size_kb' => 10]);
    actingAs($student)->post(route('student.assignments.submit', $assignment), ['text' => 'Yetarlicha uzun tahlil', 'files' => [UploadedFile::fake()->create('large.pdf', 20, 'application/pdf')]])->assertSessionHasErrors('files.0');
    actingAs($student)->post(route('student.assignments.submit', $assignment), ['text' => 'Yetarlicha uzun tahlil', 'files' => [UploadedFile::fake()->create('answer.txt', 1, 'text/plain')]])->assertSessionHasErrors('files.0');
    assertDatabaseCount('assignment_submissions', 0);
    actingAs($student)->post(route('student.assignments.submit', $assignment), ['files' => [UploadedFile::fake()->create('valid.pdf', 5, 'application/pdf')]])->assertRedirect();
    assertDatabaseCount('submission_files', 1);
});

it('includes only verified global and current-course legal sources in tutor context', function () {
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Huquq darsi', 'status' => 'published']);
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    $admin = User::factory()->academyAdmin()->create();
    LegalSource::factory()->create(['course_id' => $course->id, 'title' => 'Tekshirilgan manba', 'status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now()]);
    LegalSource::factory()->create(['title' => 'Qoralama maxfiy manba']);
    LegalSource::factory()->create(['course_id' => Course::factory()->create()->id, 'title' => 'Begona kurs manbasi', 'status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now()]);

    $context = app(TutorContextBuilder::class)->buildSystemMessages($student, $lesson, 'explain')[0]['content'];

    expect($context)->toContain('Tekshirilgan manba')
        ->toContain('bu normaning hozir amalda ekanini tasdiqlamaydi')
        ->toContain('modda raqamini to‘qimang')
        ->not->toContain('Qoralama maxfiy manba')
        ->not->toContain('Begona kurs manbasi');
});

it('calculates course-specific progress without counting draft or foreign-course lessons', function () {
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $one = Lesson::query()->create(['module_id' => $module->id, 'title' => 'L1', 'status' => 'published']);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'L2', 'status' => 'published']);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'Draft', 'status' => 'draft']);
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    LessonProgress::query()->create(['student_id' => $student->id, 'lesson_id' => $one->id, 'status' => 'completed']);

    $analytics = app(LearningAnalytics::class)->forCourse($course, $student);

    expect($analytics['percent'])->toBe(50.0)->and($analytics['total'])->toBe(2)->and($analytics['next_lesson']->title)->toBe('L2');
    actingAs($student)->get(route('student.analytics', $course))->assertOk()->assertSee('50%');
    actingAs(User::factory()->student()->create())->get(route('student.analytics', $course))->assertForbidden();
});

it('renders the category management screen and seeds fourteen categories idempotently', function () {
    $this->seed(EducationCategorySeeder::class);
    $this->seed(EducationCategorySeeder::class);
    assertDatabaseCount('education_categories', 14);
    actingAs(User::factory()->academyAdmin()->create())->get(route('admin.categories.index'))->assertOk()->assertSee('Ekologiya huquqi');
});

it('preserves existing categories and admin edits when seeding defaults', function () {
    $category = EducationCategory::factory()->create(['slug' => 'fuqarolik-huquqi', 'name' => 'Fuqarolik huquqi']);
    $edits = ['name' => 'Fuqarolik huquqi asoslari', 'description' => 'Tahrirlangan tavsif'];
    actingAs(User::factory()->academyAdmin()->create())
        ->put(route('admin.categories.update', $category), $edits)->assertRedirect();
    $this->post(route('admin.categories.store'), ['name' => 'Mehnat huquqi'])->assertRedirect();
    $custom = EducationCategory::query()->where('name', 'Mehnat huquqi')->firstOrFail();

    $this->seed(EducationCategorySeeder::class);

    assertDatabaseCount('education_categories', 14);
    $this->assertDatabaseHas('education_categories', ['id' => $category->id, 'slug' => 'fuqarolik-huquqi', ...$edits]);
    $this->assertDatabaseHas('education_categories', ['id' => $custom->id, 'slug' => $custom->slug, 'name' => 'Mehnat huquqi']);
});

it('does not count draft submissions as late submissions', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $assignment = $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'Deadline', 'deadline' => now()->subDay()]);
    $assignment->submissions()->create(['student_id' => $student->id, 'status' => 'in_progress', 'text' => 'Qoralama']);

    $analytics = app(LearningAnalytics::class)->forCourse($course, $student);

    expect($analytics['on_time_percent'])->toBeNull();
});

it('protects issued certificates when a course or its category is deleted', function () {
    $course = Course::factory()->create();

    $admin = User::factory()->academyAdmin()->create();
    $student = User::factory()->student()->create();
    $course->certificates()->create(['student_id' => $student->id, 'issued_by' => $admin->id, 'verification_code' => 'test-code',
        'student_name' => $student->name, 'course_title' => $course->title, 'issuer' => 'Academy', 'final_result' => 100, 'completed_at' => now(), 'issued_at' => now()]);
    actingAs($admin)->delete(route('admin.categories.destroy', $course->category_id))->assertConflict();
    actingAs($admin)->delete(route('admin.courses.destroy', $course))->assertConflict();

    expect($course->fresh())->not->toBeNull();
});
