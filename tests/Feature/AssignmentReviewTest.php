<?php

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function assignmentScenario(): array
{
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $mentor = User::factory()->mentor()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    MentorAssignment::query()->create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $course->id]);

    $assignment = Assignment::query()->create([
        'course_id' => $course->id, 'teacher_id' => $course->teacher_id,
        'title' => 'Kazus tahlili', 'type' => 'legal_case',
        'max_score' => 100, 'submission_attempts' => 1, 'allow_late_submission' => false,
    ]);

    return compact('course', 'student', 'mentor', 'assignment');
}

it('student submits assignment with file', function () {
    Storage::fake('local');
    ['assignment' => $assignment, 'student' => $student] = assignmentScenario();

    actingAs($student)->post("/learn/assignments/{$assignment->id}/submit", [
        'text' => 'Talaba javobi matni yetarli uzunlikda.',
        'files' => [UploadedFile::fake()->create('javob.pdf', 100, 'application/pdf')],
    ])->assertRedirect();

    $submission = AssignmentSubmission::query()->where('student_id', $student->id)->firstOrFail();
    expect($submission->status)->toBe('submitted')
        ->and($submission->files)->toHaveCount(1);
});

it('blocks submission over attempt limit', function () {
    ['assignment' => $assignment, 'student' => $student] = assignmentScenario();

    AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'text' => 'Birinchi urinish', 'status' => 'submitted',
    ]);

    actingAs($student)->post("/learn/assignments/{$assignment->id}/submit", ['text' => 'Ikkinchi urinish matni'])
        ->assertForbidden();
});

it('blocks submission after deadline unless allowed', function () {
    ['assignment' => $assignment, 'student' => $student] = assignmentScenario();
    $assignment->update(['deadline' => now()->subDay()]);

    actingAs($student)->post("/learn/assignments/{$assignment->id}/submit", ['text' => 'Kech yuborish matni'])
        ->assertForbidden();

    $assignment->update(['allow_late_submission' => true]);
    actingAs($student)->post("/learn/assignments/{$assignment->id}/submit", ['text' => 'Kech yuborish matni'])
        ->assertRedirect();
});

it('assigned mentor reviews and approves submission', function () {
    ['assignment' => $assignment, 'student' => $student, 'mentor' => $mentor] = assignmentScenario();
    $submission = AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'text' => 'Javob', 'status' => 'submitted',
    ]);

    actingAs($mentor)->get("/mentor/reviews/{$submission->id}")->assertOk();

    actingAs($mentor)->post("/mentor/reviews/{$submission->id}", [
        'score' => 85, 'comment' => 'Yaxshi tahlil', 'decision' => 'approved',
    ])->assertRedirect();

    expect($submission->fresh()->status)->toBe('approved')
        ->and((float) $submission->fresh()->score)->toBe(85.0)
        ->and($submission->reviews()->count())->toBe(1);
});

it('mentor cannot review unassigned student submission', function () {
    ['assignment' => $assignment, 'student' => $student] = assignmentScenario();
    $otherMentor = User::factory()->mentor()->create();
    $submission = AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'text' => 'Javob', 'status' => 'submitted',
    ]);

    actingAs($otherMentor)->get("/mentor/reviews/{$submission->id}")->assertForbidden();
});

it('mentor can request revision and student resubmits', function () {
    ['assignment' => $assignment, 'student' => $student, 'mentor' => $mentor] = assignmentScenario();
    $submission = AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'text' => 'Javob', 'status' => 'submitted',
    ]);

    actingAs($mentor)->post("/mentor/reviews/{$submission->id}", [
        'score' => 40, 'decision' => 'revision_requested',
    ])->assertRedirect();

    expect($submission->fresh()->status)->toBe('revision_requested');

    // revision urinish limitdan sanoqda hisoblanadi, shuning uchun yangi attempt uchun limitni oshiramiz
    $assignment->update(['submission_attempts' => 2]);

    actingAs($student)->post("/learn/assignments/{$assignment->id}/submit", ['text' => 'Qayta ishlangan javob matni'])
        ->assertRedirect();

    $resubmitted = AssignmentSubmission::query()->where('student_id', $student->id)->orderByDesc('attempt')->first();
    expect($resubmitted->attempt)->toBe(2)
        ->and($resubmitted->status)->toBe('submitted');
});

it('rejects score above max', function () {
    ['assignment' => $assignment, 'student' => $student, 'mentor' => $mentor] = assignmentScenario();
    $submission = AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'text' => 'Javob', 'status' => 'submitted',
    ]);

    actingAs($mentor)->post("/mentor/reviews/{$submission->id}", [
        'score' => 150, 'decision' => 'approved',
    ])->assertStatus(422);

    expect($submission->fresh()->status)->toBe('submitted');
});
