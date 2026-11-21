<?php

use App\Models\Certificate;
use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\EducationGroup;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MentorAssignment;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/** @return array<string, mixed> */
function attendanceScenario(): array
{
    $schedule = ClassSchedule::factory()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $schedule->course_id, 'student_id' => $student->id]);

    return compact('schedule', 'student');
}

it('creates a class schedule and rejects invalid ending time', function () {
    $course = Course::factory()->published()->create();
    $data = ['course_id' => $course->id, 'title' => 'Dars', 'kind' => 'lesson', 'starts_at' => '2026-12-01 10:00', 'ends_at' => '2026-12-01 11:00'];
    actingAs($course->teacher)->post(route('schedule.store'), [...$data, 'ends_at' => '2026-12-01 09:00'])->assertSessionHasErrors('ends_at');

    actingAs($course->teacher)->post(route('schedule.store'), $data)->assertRedirect();

    assertDatabaseHas('class_schedules', ['title' => 'Dars', 'course_id' => $course->id, 'teacher_id' => $course->teacher_id]);
    actingAs($course->teacher)->get(route('schedule.index'))->assertOk()->assertSee('Dars');
});

it('records and updates attendance only for course students', function () {
    ['schedule' => $schedule, 'student' => $student] = attendanceScenario();
    actingAs($schedule->course->teacher)->get(route('schedule.attendance', $schedule))->assertOk()->assertSee($student->name);
    actingAs($schedule->course->teacher)->post(route('schedule.attendance.store', $schedule), ['records' => [$student->id => ['status' => 'absent', 'reason' => 'Kasallik']]])->assertRedirect();

    actingAs($schedule->course->teacher)->post(route('schedule.attendance.store', $schedule), ['records' => [$student->id => ['status' => 'excused', 'reason' => 'Ma’lumotnoma']]])->assertRedirect();

    assertDatabaseCount('attendance_records', 1);
    assertDatabaseHas('attendance_records', ['class_schedule_id' => $schedule->id, 'student_id' => $student->id, 'status' => 'excused']);
});

it('prevents students and unrelated teachers from marking attendance', function () {
    ['schedule' => $schedule, 'student' => $student] = attendanceScenario();
    actingAs($student)->post(route('schedule.attendance.store', $schedule), ['records' => [$student->id => ['status' => 'present']]])->assertForbidden();
    actingAs(User::factory()->teacher()->create())->get(route('schedule.attendance', $schedule))->assertForbidden();
    actingAs($schedule->course->teacher)->post(route('schedule.attendance.store', $schedule), ['records' => [User::factory()->student()->create()->id => ['status' => 'present']]])->assertSessionHasErrors('records');
    assertDatabaseCount('attendance_records', 0);
});

it('limits group schedule visibility to group members', function () {
    ['schedule' => $schedule, 'student' => $student] = attendanceScenario();
    $group = EducationGroup::factory()->create(['course_id' => $schedule->course_id]);
    $schedule->update(['group_id' => $group->id, 'title' => 'Maxsus guruh uchrashuvi']);
    actingAs($student)->get(route('schedule.index'))->assertOk()->assertDontSee('Maxsus guruh uchrashuvi');
    $group->members()->attach($student);

    actingAs($student)->get(route('schedule.index'))->assertOk()->assertSee('Maxsus guruh uchrashuvi');
});

it('rejects certificates when lessons have not been completed', function () {
    ['schedule' => $schedule, 'student' => $student] = attendanceScenario();
    $admin = User::factory()->academyAdmin()->create();

    actingAs($admin)->post(route('admin.certificates.store', $schedule->course), ['student_id' => $student->id])->assertSessionHasErrors('certificate');

    assertDatabaseCount('certificates', 0);
});

/** @return array<string, mixed> */
function certificateScenario(): array
{
    ['schedule' => $schedule, 'student' => $student] = attendanceScenario();
    $course = $schedule->course;
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Dars', 'status' => 'published']);
    LessonProgress::query()->create(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'completion_percent' => 100, 'completed_at' => now()]);
    $admin = User::factory()->academyAdmin()->create();

    return compact('course', 'student', 'admin');
}

it('issues a unique certificate only after course requirements and verifies its snapshot', function () {
    ['course' => $course, 'student' => $student, 'admin' => $admin] = certificateScenario();
    actingAs($admin)->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertRedirect();
    actingAs($admin)->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertRedirect();
    assertDatabaseCount('certificates', 1);
    $certificate = Certificate::query()->firstOrFail();
    $original = $student->name;
    $student->update(['name' => 'Yangilangan ism']);
    actingAs($student)->get(route('certificates.show', $certificate))->assertOk()->assertSee($original)->assertSee('PDF saqlash');

    $this->get(route('certificates.verify', $certificate->verification_code))->assertOk()->assertSee('Sertifikat haqiqiy')->assertSee($original);

    assertDatabaseCount('certificate_verifications', 1);
});

it('blocks certification while assignments or manual exam answers are pending', function () {
    ['course' => $course, 'student' => $student, 'admin' => $admin] = certificateScenario();
    $assignment = $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'Ish']);
    actingAs($admin)->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertSessionHasErrors('certificate');
    $submission = $assignment->submissions()->create(['student_id' => $student->id, 'status' => 'approved', 'score' => 90]);
    $submission->reviews()->create(['mentor_id' => User::factory()->mentor()->create()->id, 'score' => 90, 'decision' => 'approved']);
    $quiz = $course->quizzes()->create(['title' => 'Imtihon', 'pass_score_percent' => 60]);
    $question = $course->questions()->create(['text' => 'Kazus', 'type' => 'scenario', 'points' => 2]);
    $quiz->questions()->attach($question);
    $attempt = $quiz->attempts()->create(['student_id' => $student->id, 'status' => 'submitted', 'score' => 80]);
    $attempt->answers()->create(['question_id' => $question->id, 'text_answer' => 'Javob']);

    actingAs($admin)->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertSessionHasErrors('certificate');

    assertDatabaseCount('certificates', 0);
});

it('prevents another student from seeing certificates and supports revocation', function () {
    ['course' => $course, 'student' => $student, 'admin' => $admin] = certificateScenario();
    actingAs($admin)->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertRedirect();
    $certificate = Certificate::query()->firstOrFail();
    actingAs(User::factory()->student()->create())->get(route('certificates.show', $certificate))->assertForbidden();
    actingAs($student)->patch(route('certificates.revoke', $certificate))->assertForbidden();

    actingAs($admin)->patch(route('certificates.revoke', $certificate))->assertRedirect();

    expect($certificate->fresh()->revoked_at)->not->toBeNull();
    $this->get(route('certificates.verify', $certificate->verification_code))->assertOk()->assertSee('Sertifikat bekor qilingan');
    $this->get(route('certificates.verify', 'invalid-code'))->assertNotFound();
});

it('schedules mentor meetings only with a mentor assigned to the course', function () {
    ['schedule' => $schedule, 'student' => $student] = attendanceScenario();
    $mentor = User::factory()->mentor()->create();
    $data = ['course_id' => $schedule->course_id, 'title' => 'Individual uchrashuv', 'host_id' => $mentor->id, 'kind' => 'mentor_meeting', 'starts_at' => '2026-12-02 10:00', 'ends_at' => '2026-12-02 11:00'];
    actingAs($schedule->course->teacher)->post(route('schedule.store'), $data)->assertUnprocessable();
    MentorAssignment::query()->create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $schedule->course_id]);

    actingAs($schedule->course->teacher)->post(route('schedule.store'), $data)->assertRedirect();

    assertDatabaseHas('class_schedules', ['title' => 'Individual uchrashuv', 'teacher_id' => $mentor->id]);
    actingAs($mentor)->get(route('schedule.index', ['month' => '2026-12']))->assertOk()->assertSee('Individual uchrashuv');
});
