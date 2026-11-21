<?php

use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('compares only like-for-like automatic indicators and keeps the same role for a retry', function (?int $scoringVersion) {
    $student = User::factory()->student()->create();
    $otherStudent = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->sole();
    $criterion = $version->rubric->criteria->firstWhere('key', 'evidence_analysis');
    $completedAt = now()->startOfSecond();
    $settings = $scoringVersion === null ? [] : ['scoring_version' => $scoringVersion];
    $sessionData = [
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id,
        'student_id' => $student->id, 'selected_role_key' => 'defense_attorney',
        'status' => 'completed', 'completed_at' => $completedAt, 'settings' => $settings,
    ];
    $makeAttempt = function (float $score, array $overrides = []) use ($sessionData, $criterion): SimulationSession {
        $attempt = SimulationSession::factory()->create([...$sessionData, ...$overrides]);
        $evaluation = $attempt->evaluation()->create(['overall_score' => $score, 'generated_at' => now()]);
        $evaluation->criterionResults()->create(['evaluation_criterion_id' => $criterion->id, 'score' => $score, 'rationale' => 'Mashq natijasi']);

        return $attempt;
    };
    $makeAttempt(1, ['completed_at' => $completedAt->copy()->subHour()]);
    $previous = $makeAttempt(8.25);
    $previous->evaluation->reviews()->create([
        'reviewer_id' => $scenario->created_by, 'decision' => 'approved', 'adjusted_score' => 99,
        'criterion_overrides' => [$criterion->id => 20], 'comment' => 'Tekshirildi.',
    ]);
    $makeAttempt(2, ['student_id' => $otherStudent->id]);
    $makeAttempt(3, ['selected_role_key' => 'judge']);
    $otherVersion = ScenarioVersion::factory()->create(['scenario_id' => $scenario->id, 'version' => 2]);
    $makeAttempt(4, ['scenario_version_id' => $otherVersion->id]);
    $makeAttempt(5, ['status' => 'active']);
    $makeAttempt(6, ['settings' => $scoringVersion === null ? ['scoring_version' => 2] : []]);
    SimulationSession::factory()->create($sessionData);
    $current = $makeAttempt(10.75);
    $makeAttempt(7, ['completed_at' => $completedAt->copy()->addHour()]);

    $retryUrl = route('student.courtroom.scenarios.show', [$scenario, 'role' => 'defense_attorney']);
    actingAs($student)->get(route('student.courtroom.sessions.report', $current))->assertOk()
        ->assertSee(route('student.courtroom.sessions.report', $previous))
        ->assertSeeTextInOrder(['Oldingi urinish bilan taqqoslash', $criterion->name, '8.25', '10.75', '+2.50'])
        ->assertSee($retryUrl.'#rol-tanlash');
    actingAs($student)->get($retryUrl)->assertOk()->assertSee('value="defense_attorney" checked', false);
    actingAs($otherStudent)->get(route('student.courtroom.sessions.report', $current))->assertForbidden();
})->with(['old scoring' => [null], 'current scoring' => [2]]);
