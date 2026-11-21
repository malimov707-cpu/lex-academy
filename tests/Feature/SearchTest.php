<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LibraryResource;
use App\Models\MentorAssignment;
use App\Models\User;

use function Pest\Laravel\actingAs;

function createSearchAuditLesson(Course $course, string $title): Lesson
{
    return CourseModule::factory()->create(['course_id' => $course->id])->lessons()->create(['title' => $title, 'status' => 'published']);
}

it('searches only the students active published courses before limiting lessons', function () {
    $student = User::factory()->student()->create();
    $foreign = Course::factory()->published()->create(['title' => 'Audit foreign course']);
    for ($index = 0; $index < 12; $index++) {
        createSearchAuditLesson($foreign, 'Audit foreign lesson '.$index);
    }
    $own = Course::factory()->published()->create(['title' => 'Audit active course']);
    $inactive = Course::factory()->published()->create(['title' => 'Audit inactive course']);
    $draft = Course::factory()->create(['title' => 'Audit draft course']);
    foreach ([[$own, 'active'], [$inactive, 'completed'], [$draft, 'active']] as [$course, $status]) {
        CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id, 'status' => $status]);
    }
    $lesson = createSearchAuditLesson($own, 'Audit accessible lesson');
    createSearchAuditLesson($inactive, 'Audit inactive lesson');
    createSearchAuditLesson($draft, 'Audit draft course lesson');

    actingAs($student)->get(route('search', ['q' => 'Audit']))->assertOk()
        ->assertSee('Audit active course')->assertSee('Audit accessible lesson')
        ->assertDontSee('Audit foreign')->assertDontSee('Audit inactive')->assertDontSee('Audit draft');
    $this->get(route('student.lesson', [$own, $lesson]))->assertOk();
});

it('shows teachers owned and assigned course lessons without exposing other teachers content', function () {
    $teacher = User::factory()->teacher()->create();
    $own = Course::factory()->published()->create(['teacher_id' => $teacher->id, 'title' => 'Audit owned course']);
    $assigned = Course::factory()->published()->create(['title' => 'Audit assigned course']);
    $assigned->teacherAssignments()->create(['teacher_id' => $teacher->id]);
    $foreign = Course::factory()->published()->create(['title' => 'Audit private course']);
    createSearchAuditLesson($foreign, 'Audit private lesson');
    createSearchAuditLesson($own, 'Audit owned lesson');
    $assignedLesson = createSearchAuditLesson($assigned, 'Audit assigned lesson');

    actingAs($teacher)->get(route('search', ['q' => 'Audit']))->assertOk()
        ->assertSee('Audit owned course')->assertSee('Audit owned lesson')
        ->assertSee('Audit assigned course')->assertSee('Audit assigned lesson')
        ->assertDontSee('Audit private');
    $this->get(route('admin.lessons.edit', $assignedLesson))->assertOk();
});

it('gives mentors accessible course previews and student progress links instead of forbidden lesson links', function () {
    $mentor = User::factory()->mentor()->create();
    $student = User::factory()->student()->create(['name' => 'Audit learner']);
    $course = Course::factory()->published()->create(['title' => 'Audit mentored course']);
    CourseEnrollment::query()->create(['student_id' => $student->id, 'course_id' => $course->id]);
    $assignment = MentorAssignment::query()->create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $course->id]);
    Course::factory()->published()->create(['title' => 'Audit unrelated course']);
    createSearchAuditLesson($course, 'Audit restricted lesson');

    actingAs($mentor)->get(route('search', ['q' => 'Audit']))->assertOk()
        ->assertSee('Audit mentored course')->assertSee('Audit learner')
        ->assertSee(route('public.courses.show', $course->slug), false)
        ->assertSee(route('mentor.students.show', $assignment), false)
        ->assertDontSee('Audit unrelated')->assertDontSee('Audit restricted');
    $this->get(route('public.courses.show', $course->slug))->assertOk();
    $this->get(route('mentor.students.show', $assignment))->assertOk();
});

it('respects administrator user visibility and uses the user screens search parameter', function () {
    $admin = User::factory()->academyAdmin()->create();
    User::factory()->superAdmin()->create(['name' => 'Audit privileged administrator']);
    $student = User::factory()->student()->create(['name' => 'Audit learner']);

    actingAs($admin)->get(route('search', ['q' => 'Audit']))->assertOk()
        ->assertSee('Audit learner')->assertDontSee('Audit privileged administrator')
        ->assertSee(route('admin.users.index', ['search' => $student->email]), false);
});

it('treats wildcard characters in search as literal text', function (string $term, string $matching, string $other) {
    LibraryResource::factory()->create(['title' => $matching]);
    LibraryResource::factory()->create(['title' => $other]);

    actingAs(User::factory()->student()->create())->get(route('search', ['q' => $term]))
        ->assertOk()->assertSee($matching)->assertDontSee($other);
})->with([
    'percent' => ['100%', 'Audit 100% complete', 'Audit 100x complete'],
    'underscore' => ['case_1', 'Audit case_1', 'Audit caseX1'],
    'escape character' => ['case!1', 'Audit case!1', 'Audit case1'],
]);

it('rejects malformed search input instead of causing a server error', function (mixed $query) {
    actingAs(User::factory()->student()->create())->getJson(route('search', ['q' => $query]))
        ->assertUnprocessable()->assertJsonValidationErrors('q');
})->with([
    'array' => [['unexpected']],
    'oversized query' => [str_repeat('x', 101)],
]);

it('requires authentication for global search', function () {
    $this->get(route('search', ['q' => 'Audit']))->assertRedirect(route('login'));
});
