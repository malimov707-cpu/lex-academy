<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\EducationAuditLog;
use App\Models\EducationGroup;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('allows academy admins to create staff but blocks privilege escalation', function () {
    $admin = User::factory()->academyAdmin()->create();
    $data = ['name' => 'Yangi ustoz', 'email' => 'teacher-new@example.test', 'role' => 'teacher', 'password' => 'StrongPassword123'];
    actingAs($admin)->post(route('admin.users.store'), [...$data, 'role' => 'super_admin'])->assertForbidden();
    actingAs($admin)->post(route('admin.users.store'), $data)->assertRedirect();
    $teacher = User::query()->where('email', $data['email'])->firstOrFail();
    expect(password_verify($data['password'], $teacher->password))->toBeTrue();
    actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Yangi ustoz');
    assertDatabaseHas('education_audit_logs', ['user_id' => $admin->id, 'action' => 'admin.users.store']);
    actingAs($teacher)->post(route('admin.users.store'), [...$data, 'email' => 'other@example.test'])->assertForbidden();
});

it('prevents academy admin from modifying super admin accounts', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->academyAdmin()->create();
    actingAs($admin)->put(route('admin.users.update', $super), ['name' => 'Changed', 'email' => $super->email, 'role' => 'teacher'])->assertForbidden();
    expect($super->fresh()->role->value)->toBe('super_admin');
});

it('shows database aggregates and exports reports only to administrators', function () {
    $course = Course::factory()->published()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => User::factory()->student()->create()->id]);
    $admin = User::factory()->academyAdmin()->create();
    actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee($course->title)->assertSee('Aktiv talabalar');
    actingAs($admin)->get(route('admin.reports.index', ['format' => 'csv']))->assertOk()->assertDownload('academy-report.csv');
    actingAs($course->teacher)->get(route('admin.reports.index'))->assertForbidden();
});

it('creates course groups and enrolls only active course students as members', function () {
    $course = Course::factory()->create();
    $student = User::factory()->student()->create();
    $admin = User::factory()->academyAdmin()->create();
    actingAs($admin)->post(route('admin.groups.store'), ['name' => 'A guruh', 'course_id' => $course->id])->assertRedirect();
    $group = EducationGroup::query()->firstOrFail();
    actingAs($admin)->post(route('admin.groups.members', $group), ['student_id' => $student->id])->assertUnprocessable();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    actingAs($admin)->post(route('admin.groups.members', $group), ['student_id' => $student->id])->assertRedirect();

    assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $student->id]);
    actingAs($admin)->get(route('admin.groups.index'))->assertOk()->assertSee($student->name);
});

it('isolates course and group announcements', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    actingAs($course->teacher)->post(route('announcements.store'), ['course_id' => $course->id, 'title' => 'Dars e’loni', 'body' => 'Ertaga uchrashamiz.'])->assertRedirect();
    actingAs($student)->get(route('announcements.index'))->assertOk()->assertSee('Ertaga uchrashamiz.');
    actingAs(User::factory()->student()->create())->get(route('announcements.index'))->assertOk()->assertDontSee('Ertaga uchrashamiz.');
    actingAs(User::factory()->teacher()->create())->post(route('announcements.store'), ['course_id' => $course->id, 'title' => 'Begona', 'body' => 'Xabar'])->assertForbidden();
});

it('blocks unauthorized lesson completion and cross course teacher edits', function () {
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Dars', 'status' => 'published']);
    actingAs(User::factory()->student()->create())->post(route('student.lesson.complete', [$course, $lesson]))->assertForbidden();
    actingAs(User::factory()->teacher()->create())->put(route('admin.lessons.update', $lesson), ['title' => 'Changed'])->assertForbidden();
    assertDatabaseCount('lesson_progress', 0);
    expect($lesson->fresh()->title)->toBe('Dars');
});

it('protects private materials and rejects executable uploads', function () {
    Storage::fake('local');
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Dars', 'status' => 'published']);
    Storage::disk('local')->put('lesson-materials/legal.pdf', 'private file');
    $material = LessonMaterial::query()->create(['lesson_id' => $lesson->id, 'title' => 'Legal', 'type' => 'pdf', 'file_path' => 'lesson-materials/legal.pdf']);
    $student = User::factory()->student()->create();
    actingAs($student)->get(route('materials.download', $material))->assertForbidden();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    actingAs($student)->get(route('materials.download', $material))->assertOk()->assertDownload();

    actingAs($course->teacher)->post(route('admin.lessons.materials.store', $lesson), ['title' => 'Bad', 'type' => 'pdf', 'file' => UploadedFile::fake()->create('bad.php', 1, 'text/x-php')])->assertSessionHasErrors('file');

    assertDatabaseCount('lesson_materials', 1);
});

it('keeps audit logs free from credentials and assignment text', function () {
    $admin = User::factory()->academyAdmin()->create();
    actingAs($admin)->post(route('admin.users.store'), ['name' => 'Person', 'email' => 'secret-test@example.test', 'role' => 'student', 'password' => 'VerySecret12345'])->assertRedirect();
    $log = EducationAuditLog::query()->where('action', 'admin.users.store')->firstOrFail();
    expect(json_encode($log->properties))->not->toContain('VerySecret12345')->not->toContain('secret-test@example.test');
});

it('updates an existing enrollment status without duplicating it', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    actingAs(User::factory()->academyAdmin()->create())->post(route('admin.courses.enrollments.store', $course), ['student_id' => $student->id, 'status' => 'suspended'])->assertRedirect();

    assertDatabaseCount('course_enrollments', 1);
    assertDatabaseHas('course_enrollments', ['course_id' => $course->id, 'student_id' => $student->id, 'status' => 'suspended']);
    actingAs($student)->get(route('student.course', $course))->assertForbidden();
});
