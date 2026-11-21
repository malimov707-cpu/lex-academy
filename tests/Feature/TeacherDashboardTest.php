<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('includes assigned courses and their submissions in the teacher dashboard without showing foreign courses', function () {
    $teacher = User::factory()->teacher()->create();
    Course::factory()->published()->create(['teacher_id' => $teacher->id, 'title' => 'Own dashboard course']);
    $assigned = Course::factory()->published()->create(['title' => 'Assigned dashboard course']);
    $assigned->teacherAssignments()->create(['teacher_id' => $teacher->id]);
    $foreign = Course::factory()->published()->create(['title' => 'Foreign dashboard course']);
    $module = CourseModule::factory()->create(['course_id' => $assigned->id]);
    $module->lessons()->create(['title' => 'Assigned lesson', 'status' => 'published']);
    $assignment = $assigned->assignments()->create(['teacher_id' => $assigned->teacher_id, 'title' => 'Assigned submission task']);
    $assignment->submissions()->create([
        'student_id' => User::factory()->student()->create()->id, 'status' => 'submitted',
        'attempt' => 1, 'text' => 'Dashboard review submission.', 'submitted_at' => now(),
    ]);
    $foreignAssignment = $foreign->assignments()->create(['teacher_id' => $foreign->teacher_id, 'title' => 'Foreign submission task']);
    $foreignAssignment->submissions()->create([
        'student_id' => User::factory()->student()->create()->id, 'status' => 'submitted',
        'attempt' => 1, 'text' => 'Foreign private submission.', 'submitted_at' => now(),
    ]);

    actingAs($teacher)->get(route('teacher.dashboard'))->assertOk()
        ->assertSee('Own dashboard course')->assertSee('Assigned dashboard course')
        ->assertSee('Assigned submission task')->assertDontSee('Foreign dashboard course')->assertDontSee('Foreign submission task')
        ->assertViewHas('stats', fn (array $stats): bool => $stats['courses'] === 2 && $stats['published'] === 2 && $stats['pending'] === 1)
        ->assertViewHas('lessonsCount', 1);
});

it('shows empty activity and distinguishes unavailable scores from zero', function () {
    $teacher = User::factory()->teacher()->create();

    $response = actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();

    expect($response['stats'])->toMatchArray(['courses' => 0, 'active_students' => 0, 'average_score' => null, 'best_score' => null]);
    expect($response['metricDetails'])->toBe(['new_courses' => 0, 'new_students' => 0, 'score_change' => null, 'best_student' => null]);
    expect($response['courses'])->toBeEmpty();
    expect($response['recentActivity'])->toBeEmpty();
    expect($response['activityDays'])->toHaveCount(90);
    expect(collect($response['activityDays'])->sum('quizzes'))->toBe(0);
});

it('counts distinct active students and computes course progress against published lessons', function () {
    $this->travelTo(now()->setDateTime(2026, 7, 15, 12, 0));
    $teacher = User::factory()->teacher()->create();
    $own = Course::factory()->published()->create(['teacher_id' => $teacher->id, 'created_at' => now()->subDays(45)]);
    $assigned = Course::factory()->create();
    $assigned->teacherAssignments()->create(['teacher_id' => $teacher->id]);
    $foreign = Course::factory()->published()->create();
    [$returning, $new, $suspended, $outside] = User::factory()->student()->count(4)->create();
    $assigned->enrollments()->create(['student_id' => $returning->id, 'enrolled_at' => now()->subDays(35), 'status' => 'completed']);
    $own->enrollments()->create(['student_id' => $returning->id, 'enrolled_at' => now()->subDays(4)]);
    $assigned->enrollments()->create(['student_id' => $new->id, 'enrolled_at' => now()->subDays(4)]);
    $own->enrollments()->create(['student_id' => $new->id, 'enrolled_at' => now()->subDays(3)]);
    $own->enrollments()->create(['student_id' => $suspended->id, 'enrolled_at' => now(), 'status' => 'suspended']);
    $foreign->enrollments()->create(['student_id' => $outside->id, 'enrolled_at' => now()]);
    $module = CourseModule::factory()->for($own)->create();
    $completed = $module->lessons()->create(['title' => 'Published lesson', 'status' => 'published']);
    $module->lessons()->create(['title' => 'Other published lesson', 'status' => 'published']);
    $draft = $module->lessons()->create(['title' => 'Draft lesson']);
    foreach ([$new, $suspended, $outside] as $student) {
        $student->lessonProgress()->create(['lesson_id' => $completed->id, 'status' => 'completed']);
    }
    $new->lessonProgress()->create(['lesson_id' => $draft->id, 'status' => 'completed']);

    $response = actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();

    expect($response['stats'])->toMatchArray(['courses' => 2, 'students' => 5, 'active_students' => 2]);
    expect($response['metricDetails'])->toMatchArray(['new_courses' => 1, 'new_students' => 1]);
    $course = $response['courses']->find($own->id);
    expect($course->lessons_count)->toBe(3);
    expect($course->progress_percent)->toBe(25);
    expect($response['courses']->find($assigned->id)->progress_percent)->toBeNull();
});

it('averages finalized attempts and reports dated activity only for managed courses and owned courtrooms', function () {
    $this->travelTo(now()->setDateTime(2026, 7, 15, 12, 0));
    $teacher = User::factory()->teacher()->create();
    $course = Course::factory()->published()->create();
    $course->teacherAssignments()->create(['teacher_id' => $teacher->id]);
    $foreignCourse = Course::factory()->published()->create();
    [$student, $other] = User::factory()->student()->count(2)->create();
    $quiz = $course->quizzes()->create(['title' => 'Managed assessment']);
    $quiz->attempts()->create(['student_id' => $student->id, 'score' => 90, 'status' => 'submitted', 'submitted_at' => now()->subHour()]);
    $quiz->attempts()->create(['student_id' => $student->id, 'score' => 0, 'status' => 'graded', 'submitted_at' => now()->subDays(30)]);
    $quiz->attempts()->create(['student_id' => $other->id, 'score' => 75, 'status' => 'graded', 'submitted_at' => now()->subDays(7)]);
    $quiz->attempts()->create(['student_id' => $other->id, 'score' => 100, 'status' => 'in_progress']);
    $partial = $quiz->attempts()->create(['student_id' => $other->id, 'score' => 100, 'status' => 'submitted', 'submitted_at' => now()]);
    $question = $course->questions()->create(['type' => 'long_answer', 'text' => 'Explain']);
    $partial->answers()->create(['question_id' => $question->id, 'text_answer' => 'Pending manual review']);
    $foreignQuiz = $foreignCourse->quizzes()->create(['title' => 'Other teacher’s assessment']);
    $foreignQuiz->attempts()->create(['student_id' => $student->id, 'score' => 100, 'status' => 'graded', 'submitted_at' => now()]);
    $course->enrollments()->create(['student_id' => $student->id, 'enrolled_at' => now()->subMinutes(30)]);
    $foreignCourse->enrollments()->create(['student_id' => $other->id, 'enrolled_at' => now()]);

    $owned = SimulationScenario::factory()->create(['created_by' => $teacher->id]);
    $version = ScenarioVersion::factory()->create(['scenario_id' => $owned->id]);
    $session = [
        'student_id' => $student->id, 'scenario_id' => $owned->id, 'scenario_version_id' => $version->id,
        'status' => 'completed', 'completed_at' => now()->subMinutes(10),
    ];
    SimulationSession::factory()->create($session);
    SimulationSession::factory()->create([...$session, 'status' => 'active']);
    SimulationSession::factory()->create([...$session, 'completed_at' => now()->subDays(89)]);
    SimulationSession::factory()->create([...$session, 'completed_at' => now()->subDays(90)]);
    SimulationSession::factory()->create(['status' => 'completed', 'completed_at' => now()]);

    $response = actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();

    expect($response['stats'])->toMatchArray(['average_score' => 55.0, 'best_score' => 75.0]);
    expect($response['metricDetails'])->toMatchArray(['best_student' => $other->name, 'score_change' => 82.5]);
    $days = collect($response['activityDays']);
    expect($days->first())->toBe(['date' => '2026-04-17', 'courtroom' => 1, 'courses' => 0, 'quizzes' => 0]);
    expect($days->last())->toBe(['date' => '2026-07-15', 'courtroom' => 1, 'courses' => 1, 'quizzes' => 1]);
    expect($days->slice(-7)->sum('quizzes'))->toBe(1);
    expect($days->slice(-30)->sum('quizzes'))->toBe(2);
    expect($days->sum('quizzes'))->toBe(3);
    expect($response['recentActivity']->pluck('type')->all())->toBe(['courtroom', 'enrollment', 'quiz', 'quiz', 'quiz', 'courtroom', 'courtroom']);
    expect($response['recentActivity']->pluck('score')->all())->toBe([null, null, 90.0, 75.0, 0.0, null, null]);
    expect($response['recentActivity']->first())->toMatchArray(['title' => $owned->title, 'student' => $student->name]);
    $response->assertSeeText('90%')->assertSee('>0%</span>', false);
});
