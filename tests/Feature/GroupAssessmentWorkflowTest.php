<?php

use App\Models\Course;
use App\Models\CourtroomParticipantGrade;
use App\Models\EducationGroup;
use App\Models\GradebookEntry;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\GroupAssessmentRubric;

use function Pest\Laravel\actingAs;

it('approves results once and synchronizes selected gradebook columns without duplicates', function () {
    $teacher = User::factory()->teacher()->create();
    $prosecutor = User::factory()->student()->create();
    $defender = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id]);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $rubric = app(GroupAssessmentRubric::class)->snapshot([
        'gradebook' => ['attendance', 'classroom_activity', 'simulation_performance', 'individual_role_score', 'teacher_final_grade'],
    ]);
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $scenario->versions()->sole()->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'completed',
        'completed_at' => now(),
        'lock_version' => 1,
        'settings' => [
            'assessment_rubric' => $rubric,
            'assessment' => ['status' => 'teacher_reviewed', 'outcome' => null, 'conclusion' => null],
        ],
    ]);

    foreach ([[$prosecutor, 'prosecutor'], [$defender, 'defense_advocate']] as [$student, $role]) {
        $session->participants()->create([
            'user_id' => $student->id,
            'role_key' => $role,
            'actor_type' => 'student',
            'display_name' => $student->name,
            'status' => 'completed',
        ]);
        CourtroomParticipantGrade::query()->create([
            'simulation_session_id' => $session->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'role_key' => $role,
            'rubric_version' => $rubric['version'],
            'ai_recommendation' => ['status' => 'insufficient_evidence', 'score' => null, 'criteria' => [], 'evidence' => []],
            'criteria' => collect($rubric['roles'][$role])->mapWithKeys(fn (array $criterion, string $key): array => [$key => $criterion['max']])->all(),
            'activity' => collect($rubric['academic']['active'])->mapWithKeys(fn (array $criterion, string $key): array => [$key => $criterion['max']])->all(),
            'evidence' => collect($rubric['roles'][$role])->mapWithKeys(fn (array $criterion, string $key): array => [$key => ['A-'.$student->id]])->all(),
            'attendance' => 'present',
            'individual_score' => collect($rubric['roles'][$role])->sum('max'),
            'activity_score' => 10,
            'teacher_final_grade' => 90,
            'gradebook_columns' => ['attendance', 'classroom_activity', 'simulation_performance', 'individual_role_score', 'teacher_final_grade'],
            'status' => 'teacher_reviewed',
            'evaluator_id' => $teacher->id,
        ]);
    }

    $conclusion = [
        'summary' => 'Guruhga yakuniy baho berildi.',
        'strengths' => 'Izchil argumentlar.',
        'mistakes' => null,
        'knowledge_gaps' => null,
        'next_steps' => 'Dalillarni mustahkamlash.',
    ];

    actingAs($teacher)
        ->postJson(route('courtroom.results.approve', $session), ['version' => 1, 'conclusion' => $conclusion])
        ->assertOk();

    expect(GradebookEntry::query()->where('simulation_session_id', $session->id)->count())->toBe(10)
        ->and(CourtroomParticipantGrade::query()->where('simulation_session_id', $session->id)->where('status', 'approved')->count())->toBe(2);

    $approvedVersion = $session->fresh()->lock_version;
    actingAs($teacher)
        ->postJson(route('courtroom.results.approve', $session), ['version' => $approvedVersion, 'conclusion' => $conclusion])
        ->assertOk();
    expect(GradebookEntry::query()->where('simulation_session_id', $session->id)->count())->toBe(10);
});
