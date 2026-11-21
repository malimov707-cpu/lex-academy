<?php

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LegalCase;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/** @return array<string, mixed> */
function legalCasePayload(): array
{
    return [
        'title' => 'Mehnat shartnomasi bo‘yicha nizo', 'category' => 'labor',
        'situation' => 'Xodim bilan mehnat shartnomasi bekor qilindi.',
        'facts' => 'Xodimga yozma ogohlantirish berilmagan.',
        'legal_issue' => 'Bekor qilish tartibi qanday baholanadi?',
        'legislation' => 'O‘qituvchi tekshirgan Mehnat kodeksi manbalari.',
        'analysis_questions' => 'Qanday fakt va hujjatlar kerak?',
        'rubric' => 'Faktlar, manba va asoslantirish.',
        'status' => 'draft', 'max_score' => 100, 'submission_attempts' => 3,
    ];
}

/** @return array{legalCase: LegalCase, student: User} */
function enrolledCase(): array
{
    $legalCase = LegalCase::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $legalCase->assignment->course_id, 'student_id' => $student->id]);

    return compact('legalCase', 'student');
}

it('creates a draft case and assignment then publishes edited content', function () {
    $course = Course::factory()->create();
    $teacher = $course->teacher;

    actingAs($teacher)->post(route('admin.courses.cases.store', $course), legalCasePayload())->assertRedirect();

    $legalCase = LegalCase::query()->firstOrFail();
    expect($legalCase->status)->toBe('draft');
    expect($legalCase->assignment->type)->toBe('legal_case');
    expect($legalCase->assignment->rubric)->toBe('Faktlar, manba va asoslantirish.');
    actingAs($teacher)->get(route('admin.cases.edit', $legalCase))->assertOk()->assertSee('Kazusni tahrirlash');
    actingAs($teacher)->put(route('admin.cases.update', $legalCase), [...legalCasePayload(), 'status' => 'published'])->assertRedirect();
    assertDatabaseHas('legal_cases', ['id' => $legalCase->id, 'status' => 'published']);
    actingAs($teacher)->get(route('admin.courses.cases.index', $course))->assertOk()->assertSee('Mehnat shartnomasi');
});

it('rejects missing case content without creating an assignment', function () {
    $course = Course::factory()->create();

    actingAs($course->teacher)->post(route('admin.courses.cases.store', $course), [])
        ->assertSessionHasErrors(['title', 'situation', 'facts', 'legislation', 'analysis_questions']);

    assertDatabaseCount('assignments', 0);
    assertDatabaseCount('legal_cases', 0);
});

it('blocks another teacher from managing a case', function () {
    $legalCase = LegalCase::factory()->create();
    $teacher = User::factory()->teacher()->create();

    actingAs($teacher)->get(route('admin.courses.cases.index', $legalCase->assignment->course))->assertForbidden();
    actingAs($teacher)->post(route('admin.courses.cases.store', $legalCase->assignment->course), legalCasePayload())->assertForbidden();
    actingAs($teacher)->get(route('admin.cases.edit', $legalCase))->assertForbidden();
    actingAs($teacher)->put(route('admin.cases.update', $legalCase), legalCasePayload())->assertForbidden();

    expect($legalCase->fresh()->status)->toBe('draft');
    assertDatabaseCount('legal_cases', 1);
});

it('requires authentication and restricts staff routes to staff', function () {
    $legalCase = LegalCase::factory()->create();
    $this->get(route('student.cases.index'))->assertRedirect(route('login'));
    actingAs(User::factory()->student()->create())->post(route('admin.courses.cases.store', $legalCase->assignment->course), legalCasePayload())->assertForbidden();
});

it('lists only published cases for active enrolled courses and filters by category and title', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    $legalCase->assignment->update(['title' => 'Ochiq kazus']);
    $draft = LegalCase::factory()->create();
    $draft->assignment->update(['course_id' => $legalCase->assignment->course_id, 'title' => 'Maxfiy qoralama']);
    $other = LegalCase::factory()->published()->create();
    $other->assignment->update(['title' => 'Boshqa kurs kazusi']);

    actingAs($student)->get(route('student.cases.index', ['search' => 'Ochiq', 'category' => 'civil']))
        ->assertOk()->assertSee('Ochiq kazus')->assertDontSee('Maxfiy qoralama')->assertDontSee('Boshqa kurs kazusi');
    actingAs($student)->get(route('student.cases.index', ['category' => 'tax']))->assertOk()->assertDontSee('Ochiq kazus');
    actingAs($student)->get(route('student.cases.show', $draft))->assertNotFound();
    actingAs($student)->get(route('student.cases.show', $other))->assertForbidden();
});

it('blocks inactive enrollment archived courses and draft assignment submission', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    CourseEnrollment::query()->where('student_id', $student->id)->update(['status' => 'completed']);
    actingAs($student)->get(route('student.cases.show', $legalCase))->assertForbidden();
    actingAs($student)->post(route('student.assignments.submit', $legalCase->assignment), ['text' => 'Tahlil yetarlicha uzun.'])->assertForbidden();
    CourseEnrollment::query()->where('student_id', $student->id)->update(['status' => 'active']);
    $legalCase->assignment->course->update(['status' => 'archived']);
    actingAs($student)->get(route('student.cases.show', $legalCase))->assertNotFound();
    $legalCase->update(['status' => 'draft']);
    actingAs($student)->post(route('student.assignments.submit', $legalCase->assignment), ['text' => 'Tahlil yetarlicha uzun.'])->assertNotFound();
    assertDatabaseCount('assignment_submissions', 0);
});

it('stores AI guidance with only the selected case and current analysis without grading', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Qaysi faktlarni dalillar bilan tasdiqlaysiz?']]]])]);
    $legalCase->feedback()->create(['student_id' => User::factory()->student()->create()->id, 'analysis' => 'Boshqa talabaning maxfiy tahlili', 'feedback' => 'Maxfiy feedback']);

    actingAs($student)->postJson(route('student.cases.feedback', $legalCase), ['analysis' => 'Avval faktlarni ajratib ko‘rsataman.'])
        ->assertOk()->assertJsonPath('reply', 'Qaysi faktlarni dalillar bilan tasdiqlaysiz?');

    Http::assertSent(function ($request) use ($legalCase): bool {
        $messages = $request['messages'];

        return count($messages) === 3
            && str_contains($messages[0]['content'], 'Yakuniy yechimni yoki tayyor javobni bermang')
            && str_contains($messages[1]['content'], $legalCase->situation)
            && ! str_contains(json_encode($messages), 'Maxfiy feedback');
    });
    assertDatabaseHas('legal_case_feedback', ['legal_case_id' => $legalCase->id, 'student_id' => $student->id, 'analysis' => 'Avval faktlarni ajratib ko‘rsataman.']);
    assertDatabaseCount('mentor_reviews', 0);
    actingAs($student)->get(route('student.cases.show', $legalCase))->assertOk()->assertSee('Qaysi faktlarni')->assertDontSee('Maxfiy feedback');
});

it('returns 503 and saves no fabricated guidance when AI is unavailable', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    config()->set('ai_tutor.api_key', null);

    actingAs($student)->postJson(route('student.cases.feedback', $legalCase), ['analysis' => 'Mustaqil huquqiy tahlilim.'])->assertStatus(503)->assertJsonStructure(['error']);

    assertDatabaseCount('legal_case_feedback', 0);
});

it('handles AI connection loss without saving a false response', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::failedConnection()]);

    actingAs($student)->postJson(route('student.cases.feedback', $legalCase), ['analysis' => 'Mustaqil huquqiy tahlilim.'])->assertStatus(503);

    Http::assertSentCount(1);
    assertDatabaseCount('legal_case_feedback', 0);
});

it('forbids unauthorized AI requests before contacting the provider', function () {
    $legalCase = LegalCase::factory()->published()->create();
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/*' => Http::response([])]);

    actingAs(User::factory()->student()->create())->postJson(route('student.cases.feedback', $legalCase), ['analysis' => 'Begona talaba tahlili.'])->assertForbidden();

    Http::assertNothingSent();
    assertDatabaseCount('legal_case_feedback', 0);
});

it('submits a case for mentor evaluation and keeps AI separate from the final score', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    $mentor = User::factory()->mentor()->create();
    MentorAssignment::query()->create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $legalCase->assignment->course_id]);
    $legalCase->feedback()->create(['student_id' => $student->id, 'analysis' => 'Dastlabki tahlil', 'feedback' => 'Dalillarni tekshiring.']);
    actingAs($student)->post(route('student.assignments.submit', $legalCase->assignment), ['text' => 'Mustaqil asoslantirilgan tahlilim.'])->assertRedirect();
    $submission = AssignmentSubmission::query()->firstOrFail();
    expect($submission->score)->toBeNull();
    actingAs($mentor)->get(route('mentor.reviews.show', $submission))->assertOk()->assertSee($legalCase->situation)->assertSee('Dalillarni tekshiring.');

    actingAs($mentor)->post(route('mentor.reviews.store', $submission), ['score' => 85, 'decision' => 'approved', 'comment' => 'Asoslantirish yaxshi.'])->assertRedirect();

    assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'approved', 'score' => 85]);
    actingAs($student)->get(route('student.cases.show', $legalCase))->assertOk()->assertSee('Asoslantirish yaxshi.');
});

it('blocks mentor access to the same student in another course', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    $mentor = User::factory()->mentor()->create();
    $otherCase = LegalCase::factory()->published()->create();
    MentorAssignment::query()->create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $otherCase->assignment->course_id]);
    $legalCase->assignment->update(['title' => 'Yashirin kazus']);
    $submission = AssignmentSubmission::query()->create(['assignment_id' => $legalCase->assignment_id, 'student_id' => $student->id, 'text' => 'Maxfiy javob', 'status' => 'submitted', 'submitted_at' => now()]);

    actingAs($mentor)->get(route('mentor.reviews'))->assertOk()->assertDontSee('Yashirin kazus');
    actingAs($mentor)->get(route('mentor.reviews.show', $submission))->assertForbidden();
    actingAs($mentor)->post(route('mentor.reviews.store', $submission), ['score' => 80, 'decision' => 'approved'])->assertForbidden();

    assertDatabaseCount('mentor_reviews', 0);
});

it('assigns the selected mentor to current active course students', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    $mentor = User::factory()->mentor()->create();
    $course = $legalCase->assignment->course;

    actingAs($course->teacher)->post(route('admin.courses.cases.store', $course), [...legalCasePayload(), 'mentor_id' => $mentor->id])->assertRedirect();

    assertDatabaseHas('mentor_assignments', ['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $course->id]);
});

it('rejects a student selected as mentor without creating a case', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    $course = $legalCase->assignment->course;

    actingAs($course->teacher)->post(route('admin.courses.cases.store', $course), [...legalCasePayload(), 'mentor_id' => $student->id])->assertSessionHasErrors('mentor_id');

    assertDatabaseCount('legal_cases', 1);
    assertDatabaseCount('assignments', 1);
});

it('prevents another teacher from deleting the assignment underlying a case', function () {
    $legalCase = LegalCase::factory()->create();

    actingAs(User::factory()->teacher()->create())->delete(route('admin.courses.assignments.destroy', $legalCase->assignment))->assertForbidden();

    assertDatabaseHas('legal_cases', ['id' => $legalCase->id]);
    assertDatabaseHas('assignments', ['id' => $legalCase->assignment_id]);
});

it('rejects short analysis before using AI and escapes case content', function () {
    ['legalCase' => $legalCase, 'student' => $student] = enrolledCase();
    $legalCase->update(['situation' => '<script>alert(1)</script>']);
    actingAs($student)->get(route('student.cases.show', $legalCase))->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

    actingAs($student)->postJson(route('student.cases.feedback', $legalCase), ['analysis' => 'qisqa'])->assertUnprocessable()->assertJsonValidationErrors('analysis');

    assertDatabaseCount('legal_case_feedback', 0);
});
