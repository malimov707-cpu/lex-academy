<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

it('refuses actions after the configured session timeout', function () {
    $this->freezeTime();
    config()->set('virtual_courtroom.session.max_minutes', 90);
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $scenario->versions()->sole()->id,
        'student_id' => $student->id, 'selected_role_key' => 'judge', 'status' => 'active',
        'current_stage_key' => 'PREPARATION', 'started_at' => now()->subMinutes(91),
    ]);
    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'make_statement', 'content' => 'Vaqti tugagan harakat.', 'idempotency_key' => (string) Str::uuid(),
    ])->assertConflict();
    assertDatabaseCount('session_actions', 0);
});

it('discards a late provider reply after the authoritative court stage has changed', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $scenario->versions()->sole()->id,
        'student_id' => $student->id, 'selected_role_key' => 'judge', 'status' => 'active',
        'current_stage_key' => 'PREPARATION', 'started_at' => now(),
    ]);
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => function () use ($session) {
        $session->update(['current_stage_key' => 'EVIDENCE_PRESENTATION']);

        return Http::response(['choices' => [['message' => ['content' => 'Kechikkan javob.']]]]);
    }]);
    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'make_statement', 'content' => 'Bosqich o‘zgarishidan oldingi nutq.', 'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(202)->assertJsonPath('ai', null);
    expect($session->turns()->where('speaker_type', 'ai')->count())->toBe(0)
        ->and($session->actions()->sole()->ai_response)->toBeNull();
    Http::assertSentCount(1);
});
