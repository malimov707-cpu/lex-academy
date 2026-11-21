<?php

use App\Models\Course;
use App\Models\CourtroomParticipantGrade;
use App\Models\EducationGroup;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\GroupAssessmentRubric;

use function Pest\Laravel\actingAs;

function courtroomResultFixture(): array
{
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id, 'name' => 'Guruh 1']);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $rubric = app(GroupAssessmentRubric::class)->snapshot();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $scenario->versions()->sole()->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'completed',
        'completed_at' => now(),
        'elapsed_seconds' => 2400,
        'settings' => [
            'assessment_rubric' => $rubric,
            'assessment' => [
                'status' => 'approved',
                'outcome' => [
                    'winner' => 'tie',
                    'reason' => 'Sinov natijasi.',
                    'teams' => [
                        'prosecution' => ['score' => 75, 'criteria' => [], 'strengths' => [], 'weaknesses' => [], 'advocates_count' => 1, 'party_score' => null],
                        'defense' => ['score' => 75, 'criteria' => [], 'strengths' => [], 'weaknesses' => [], 'advocates_count' => 1, 'party_score' => null],
                    ],
                    'awards' => [],
                ],
                'conclusion' => ['summary' => 'Guruh ishi qoniqarli.'],
            ],
        ],
    ]);
    $session->participants()->create([
        'user_id' => $student->id,
        'role_key' => 'prosecutor',
        'actor_type' => 'student',
        'display_name' => $student->name,
        'status' => 'completed',
        'metadata' => ['attended_at' => now()->toIso8601String()],
    ]);
    $grade = CourtroomParticipantGrade::query()->create([
        'simulation_session_id' => $session->id,
        'course_id' => $course->id,
        'student_id' => $student->id,
        'role_key' => 'prosecutor',
        'rubric_version' => $rubric['version'],
        'ai_recommendation' => ['status' => 'preliminary', 'score' => 80, 'criteria' => [], 'evidence' => []],
        'criteria' => [],
        'activity' => [],
        'evidence' => [],
        'attendance' => 'present',
        'individual_score' => 82,
        'activity_score' => 9,
        'teacher_final_grade' => 85,
        'gradebook_columns' => ['attendance', 'classroom_activity', 'individual_role_score', 'teacher_final_grade'],
        'status' => 'approved',
        'approved_at' => now(),
    ]);

    return [$teacher, $student, $course, $group, $session, $grade];
}

it('shows approved results to the student and hides another student private comments', function () {
    [$teacher, $student, $course, $group, $session, $grade] = courtroomResultFixture();

    actingAs($student)
        ->get(route('courtroom.results.show', $session))
        ->assertOk()
        ->assertSee($session->scenario->title)
        ->assertSee('O‘quv bahsi natijasi')
        ->assertSee('Real ayblov');

    actingAs(User::factory()->student()->create())
        ->get(route('courtroom.results.show', $session))
        ->assertForbidden();

    actingAs($teacher)
        ->get(route('courtroom.results.individual', $session))
        ->assertOk()
        ->assertJsonPath('status', 'approved')
        ->assertJsonCount(1, 'scores');

    expect($grade->fresh()->status)->toBe('approved');
});

it('returns CSV only to teachers and publishes approved student history', function () {
    [$teacher, $student, $course, $group, $session] = courtroomResultFixture();

    actingAs($student)
        ->get(route('courtroom.results.show', ['session' => $session, 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    actingAs($teacher)
        ->get(route('courtroom.results.show', ['session' => $session, 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    actingAs($student)
        ->get(route('courtroom.results.student-history', $student))
        ->assertOk();
});
