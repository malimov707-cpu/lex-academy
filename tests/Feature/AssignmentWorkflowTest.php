<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

/** @return array<string, mixed> */
function homeworkWorkflow(): array
{
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $mentor = User::factory()->mentor()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    MentorAssignment::query()->create(['course_id' => $course->id, 'student_id' => $student->id, 'mentor_id' => $mentor->id]);
    $assignment = $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'Yozma ish', 'submission_attempts' => 1]);

    return compact('course', 'student', 'mentor', 'assignment');
}

it('keeps editable drafts private and does not spend an attempt until submission', function () {
    ['assignment' => $assignment, 'student' => $student, 'mentor' => $mentor] = homeworkWorkflow();
    $notifications = $mentor->notifications()->count();
    actingAs($student)->post(route('student.assignments.draft', $assignment), ['text' => 'Boshlang‘ich qoralama'])->assertRedirect();
    actingAs($student)->post(route('student.assignments.draft', $assignment), ['text' => 'Tahrirlangan qoralama'])->assertRedirect();
    $draft = $assignment->submissions()->firstOrFail();
    assertDatabaseCount('assignment_submissions', 1);
    expect($draft->text)->toBe('Tahrirlangan qoralama');
    expect($mentor->notifications()->count())->toBe($notifications);
    actingAs($mentor)->get(route('mentor.reviews.show', $draft))->assertNotFound();

    actingAs($student)->post(route('student.assignments.submit', $assignment), ['text' => 'Yakuniy mustaqil tahlil matni.'])->assertRedirect();

    expect($draft->fresh()->status)->toBe('submitted')->and($draft->fresh()->attempt)->toBe(1);
    assertDatabaseCount('assignment_submissions', 1);
    expect($mentor->notifications()->count())->toBe($notifications + 1);
});

it('stores mentor AI feedback without changing the official status or grade', function () {
    ['assignment' => $assignment, 'student' => $student, 'mentor' => $mentor] = homeworkWorkflow();
    $submission = $assignment->submissions()->create(['student_id' => $student->id, 'text' => 'Mustaqil tahlil matni', 'status' => 'submitted']);
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Dalillarni tekshiring.']]]])]);

    actingAs($mentor)->post(route('mentor.submissions.guidance', $submission))->assertRedirect();

    expect($submission->fresh()->ai_analysis['content'])->toBe('Dalillarni tekshiring.');
    expect($submission->fresh()->score)->toBeNull();
    expect($submission->fresh()->status)->toBe('submitted');
    Http::assertSentCount(1);
    actingAs(User::factory()->mentor()->create())->post(route('mentor.submissions.guidance', $submission))->assertForbidden();
});

it('allows assignment updates but does not reduce maximum score below an existing grade', function () {
    ['assignment' => $assignment, 'student' => $student, 'course' => $course] = homeworkWorkflow();
    $assignment->submissions()->create(['student_id' => $student->id, 'text' => 'Javob', 'score' => 85]);
    $data = ['title' => 'Yangilangan ish', 'max_score' => 80, 'submission_attempts' => 2];
    actingAs($course->teacher)->put(route('admin.assignments.update', $assignment), $data)->assertSessionHasErrors('max_score');

    actingAs($course->teacher)->put(route('admin.assignments.update', $assignment), [...$data, 'max_score' => 100])->assertRedirect();

    expect($assignment->fresh()->title)->toBe('Yangilangan ish');
    actingAs(User::factory()->teacher()->create())->put(route('admin.assignments.update', $assignment), [...$data, 'max_score' => 100])->assertForbidden();
});
