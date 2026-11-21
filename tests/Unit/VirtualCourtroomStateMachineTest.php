<?php

use App\Exceptions\InvalidSimulationAction;
use App\Models\ScenarioEvidence;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\SimulationStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rejects an action that is not allowed in the current stage', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->firstOrFail();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'student_id' => $student->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'current_stage_key' => 'PREPARATION',
    ]);

    app(SimulationStateMachine::class)->act($session, $student, [
        'action_type' => 'present_evidence',
        'content' => 'Bu bosqichda mumkin emas.',
        'idempotency_key' => fake()->uuid(),
    ]);
})->throws(InvalidSimulationAction::class, 'Bu bosqichda ushbu harakatga ruxsat berilmagan.');

it('enforces role-scoped evidence visibility', function () {
    $evidence = new ScenarioEvidence([
        'visibility' => 'role',
        'allowed_role_keys' => ['judge', 'prosecutor'],
    ]);

    expect($evidence->isVisibleTo('judge'))->toBeTrue()
        ->and($evidence->isVisibleTo('defense_attorney'))->toBeFalse();
});

it('requires the terminal stage before completion', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->firstOrFail();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'student_id' => $student->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'current_stage_key' => 'PREPARATION',
    ]);

    app(SimulationStateMachine::class)->complete($session);
})->throws(InvalidSimulationAction::class, 'Avval sud jarayonining barcha bosqichlarini yakunlang.');
