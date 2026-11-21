<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\AcademyNotification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\actingAs;

it('renders all academy administrative screens', function () {
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Muharrir', 'status' => 'draft']);
    actingAs(User::factory()->academyAdmin()->create());

    foreach (['admin.courses.index', 'admin.users.index', 'admin.groups.index', 'admin.reports.index', 'schedule.index', 'certificates.index', 'announcements.index', 'notifications.index'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('admin.lessons.edit', $lesson))->assertOk();
    $this->get(route('admin.courses.enrollments.index', $course))->assertOk();
    $this->get(route('admin.courses.quizzes.index', $course))->assertOk();
    $this->get(route('admin.courses.assignments.index', $course))->assertOk();
});

it('preserves the old lesson content when the teacher saves a new version', function () {
    $course = Course::factory()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Oldingi nom', 'body' => 'Oldingi kontent']);

    actingAs($course->teacher)->put(route('admin.lessons.update', $lesson), ['title' => 'Yangi nom', 'body' => 'Yangi kontent'])->assertRedirect();

    expect($lesson->fresh()->body)->toBe('Yangi kontent');
    expect($lesson->versions()->firstOrFail()->snapshot['body'])->toBe('Oldingi kontent');
    actingAs($course->teacher)->get(route('admin.lessons.edit', $lesson))->assertOk()->assertSee('Oldingi kontent');
});

it('notifies only enrolled students when a lesson is published', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Yangi dars']);
    $before = $student->notifications()->count();

    actingAs($course->teacher)->patch(route('admin.lessons.publish', $lesson))->assertRedirect();

    expect($student->notifications()->count())->toBe($before + 1);
    expect($other->notifications()->count())->toBe(0);
    actingAs($student)->get(route('notifications.index'))->assertOk()->assertSee('Yangi dars');
});

it('isolates notification read status between students', function () {
    $student = User::factory()->student()->create();
    $student->notify(new AcademyNotification('Shaxsiy xabar', '/learn'));
    $notification = $student->notifications()->firstOrFail();
    actingAs(User::factory()->student()->create())->patch(route('notifications.update', $notification->id))->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();

    actingAs($student)->patch(route('notifications.update', $notification->id))->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('rate limits repeated invalid login attempts', function () {
    RateLimiter::clear('login-test');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => 'throttled-unique@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => 'throttled-unique@example.test', 'password' => 'wrong'])->assertTooManyRequests();
});

it('returns health status with security headers', function () {
    $this->get('/up')->assertOk();
    actingAs(User::factory()->student()->create())->get(route('student.dashboard'))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
