 <?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

use function Pest\Laravel\actingAs;

function publishedCourseWithLessons(): Course
{
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'L1', 'status' => 'published', 'sort_order' => 1]);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'L2', 'status' => 'published', 'sort_order' => 2]);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'Draft', 'status' => 'draft', 'sort_order' => 3]);

    return $course->refresh();
}

it('admin enrolls student and student sees course in dashboard', function () {
    $course = Course::factory()->published()->create();
    $admin = User::factory()->academyAdmin()->create();
    $student = User::factory()->student()->create();

    actingAs($admin)->post("/admin/courses/{$course->id}/enrollments", ['student_id' => $student->id, 'status' => 'active'])->assertRedirect();

    actingAs($student)->get('/learn')
        ->assertOk()
        ->assertSee($course->title);
});

it('student cannot open course they are not enrolled in', function () {
    $course = publishedCourseWithLessons();

    actingAs(User::factory()->student()->create())
        ->get("/learn/courses/{$course->id}")
        ->assertForbidden();
});

it('student views published lessons only and completes with criteria', function () {
    $course = publishedCourseWithLessons();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    actingAs($student)->get("/learn/courses/{$course->id}")
        ->assertOk()
        ->assertSee('L1')
        ->assertDontSee('Draft');

    $lesson = Lesson::query()->where('title', 'L1')->firstOrFail();
    actingAs($student)->post("/learn/courses/{$course->id}/lessons/{$lesson->id}/complete")->assertRedirect();

    $progress = LessonProgress::query()->where('student_id', $student->id)->where('lesson_id', $lesson->id)->firstOrFail();
    expect($progress->status)->toBe('completed')
        ->and($progress->completion_percent)->toBe(100);
});

it('draft lesson returns 404 for student', function () {
    $course = publishedCourseWithLessons();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    $draft = Lesson::query()->where('title', 'Draft')->firstOrFail();

    actingAs($student)->get("/learn/courses/{$course->id}/lessons/{$draft->id}")->assertNotFound();
});

it('mentor cannot access student learn pages', function () {
    actingAs(User::factory()->mentor()->create())->get('/learn')->assertForbidden();
});

it('admin removes enrollment', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $enrollment = CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete("/admin/enrollments/{$enrollment->id}")->assertRedirect();

    expect(CourseEnrollment::query()->find($enrollment->id))->toBeNull();
});
