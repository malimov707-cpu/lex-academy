<?php

use App\Models\Course;
use App\Models\CourseDiscussion;
use App\Models\CourseEnrollment;
use App\Models\MentorAssignment;
use App\Models\MentorConversation;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

function communicationAssignment(): MentorAssignment
{
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    return MentorAssignment::query()->create(['course_id' => $course->id, 'student_id' => $student->id, 'mentor_id' => User::factory()->mentor()->create()->id]);
}

it('allows assigned mentor and student to exchange escaped private messages with read status', function () {
    $assignment = communicationAssignment();
    actingAs($assignment->student)->get(route('conversations.index'))->assertOk()->assertSee($assignment->mentor->name);
    actingAs($assignment->student)->post(route('conversations.store', $assignment), ['body' => '<script>alert(1)</script>'])->assertRedirect();
    $conversation = MentorConversation::query()->firstOrFail();
    $message = $conversation->messages()->firstOrFail();
    expect($message->read_at)->toBeNull();

    actingAs($assignment->mentor)->get(route('conversations.show', $assignment))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

    expect($message->fresh()->read_at)->not->toBeNull();
    expect($assignment->mentor->notifications()->count())->toBe(1);
    actingAs($assignment->mentor)->post(route('conversations.store', $assignment), ['body' => 'Faktlarni qayta ko‘rib chiqing.'])->assertRedirect();
    assertDatabaseCount('conversation_messages', 2);
    actingAs($assignment->student)->get(route('conversations.show', $assignment))->assertOk()->assertSee('Faktlarni qayta ko‘rib chiqing.');
});

it('blocks private messages from unrelated students mentors and suspended enrollments', function () {
    $assignment = communicationAssignment();
    actingAs(User::factory()->student()->create())->get(route('conversations.show', $assignment))->assertForbidden();
    actingAs(User::factory()->mentor()->create())->post(route('conversations.store', $assignment), ['body' => 'Begona xabar'])->assertForbidden();
    CourseEnrollment::query()->where('course_id', $assignment->course_id)->update(['status' => 'suspended']);
    actingAs($assignment->student)->post(route('conversations.store', $assignment), ['body' => 'Xabar'])->assertForbidden();
    assertDatabaseCount('conversation_messages', 0);
});

it('revokes access when a mentor assignment is removed', function () {
    $assignment = communicationAssignment();
    actingAs($assignment->student)->post(route('conversations.store', $assignment), ['body' => 'Saqlangan xabar'])->assertRedirect();
    $id = $assignment->id;
    $assignment->delete();

    actingAs($assignment->student)->get(route('conversations.show', $id))->assertNotFound();

    assertDatabaseCount('mentor_conversations', 0);
    assertDatabaseCount('conversation_messages', 0);
});

it('creates course discussion and replies only for authorized participants', function () {
    $assignment = communicationAssignment();
    $course = $assignment->course;
    actingAs($assignment->student)->post(route('discussions.store', $course), ['title' => 'Huquqiy savol', 'body' => 'Dalillar qanday baholanadi?'])->assertRedirect();
    $discussion = CourseDiscussion::query()->firstOrFail();
    actingAs($course->teacher)->get(route('discussions.index', $course))->assertOk()->assertSee('Huquqiy savol');
    actingAs(User::factory()->student()->create())->get(route('discussions.show', $discussion))->assertForbidden();

    actingAs($course->teacher)->post(route('discussions.reply', $discussion), ['body' => 'Avval faktlarni ajrating.'])->assertRedirect();

    assertDatabaseHas('discussion_replies', ['discussion_id' => $discussion->id, 'author_id' => $course->teacher_id, 'body' => 'Avval faktlarni ajrating.']);
    actingAs($assignment->student)->get(route('discussions.show', $discussion))->assertOk()->assertSee('Avval faktlarni ajrating.');
});

it('allows the teacher to lock discussions and rejects student moderation and further replies', function () {
    $assignment = communicationAssignment();
    $discussion = CourseDiscussion::query()->create(['course_id' => $assignment->course_id, 'author_id' => $assignment->student_id, 'title' => 'Savol', 'body' => 'Matn']);
    actingAs($assignment->student)->patch(route('discussions.update', $discussion), ['is_locked' => 1])->assertForbidden();
    actingAs($assignment->course->teacher)->patch(route('discussions.update', $discussion), ['is_locked' => 1])->assertRedirect();

    actingAs($assignment->student)->post(route('discussions.reply', $discussion), ['body' => 'Yangi javob'])->assertConflict();

    assertDatabaseCount('discussion_replies', 0);
    expect($discussion->fresh()->is_locked)->toBeTrue();
});
