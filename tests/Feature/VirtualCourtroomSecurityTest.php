<?php

use App\Models\ScenarioEvidence;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('blocks IDOR access to another students session actions evidence and timeline', function () {
    $owner = User::factory()->student()->create();
    $attacker = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->firstOrFail();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'student_id' => $owner->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'current_stage_key' => 'PREPARATION',
        'started_at' => now(),
    ]);
    $evidence = ScenarioEvidence::query()->where('scenario_version_id', $version->id)->firstOrFail();

    actingAs($attacker)->get(route('student.courtroom.sessions.show', $session))->assertForbidden();
    actingAs($attacker)->getJson(route('student.courtroom.sessions.timeline', $session))->assertForbidden();
    actingAs($attacker)->getJson(route('student.courtroom.evidence.show', [$session, $evidence]))->assertForbidden();
    actingAs($attacker)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'make_statement',
        'content' => 'Begona harakat.',
        'idempotency_key' => fake()->uuid(),
    ])->assertForbidden();

    assertDatabaseCount('session_actions', 0);
});

it('rejects roles and evidence that do not belong to the published scenario version', function () {
    $student = User::factory()->student()->create();
    $first = SimulationScenario::factory()->courtroomReady()->create();
    $second = SimulationScenario::factory()->courtroomReady()->create();
    $version = $first->versions()->firstOrFail();
    $foreignEvidence = ScenarioEvidence::query()->where('scenario_version_id', $second->versions()->firstOrFail()->id)->firstOrFail();

    actingAs($student)->post(route('student.courtroom.sessions.store'), [
        'scenario_id' => $first->id,
        'role_key' => 'witness',
    ])->assertStatus(422);

    $session = SimulationSession::query()->create([
        'scenario_id' => $first->id,
        'scenario_version_id' => $version->id,
        'student_id' => $student->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'current_stage_key' => 'EVIDENCE_PRESENTATION',
        'started_at' => now(),
    ]);
    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'present_evidence',
        'content' => 'Begona dalilni taqdim etaman.',
        'evidence_id' => $foreignEvidence->id,
        'idempotency_key' => fake()->uuid(),
    ])->assertForbidden();
    assertDatabaseCount('session_actions', 0);
});

it('requires explicit voice consent never accepts recording and respects transcript consent', function () {
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
        'started_at' => now(),
    ]);

    actingAs($student)->postJson(route('student.courtroom.voice.store', $session), [
        'transcript_consent' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('consent');
    actingAs($student)->postJson(route('student.courtroom.voice.store', $session), [
        'consent' => true,
        'transcript_consent' => true,
        'audio_recording' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('audio_recording');

    $response = actingAs($student)->postJson(route('student.courtroom.voice.store', $session), [
        'consent' => true,
        'transcript_consent' => false,
        'capabilities' => ['speech_recognition' => true, 'speech_synthesis' => true, 'locale' => 'uz-Latn'],
    ])->assertCreated()->assertJsonPath('privacy.raw_audio_stored', false);
    $voiceId = $response->json('voice_session.id');
    assertDatabaseHas('voice_sessions', ['id' => $voiceId, 'audio_recording' => false, 'transcript_consent' => false]);

    actingAs($student)->postJson(route('student.courtroom.voice.segments.store', [$session, $voiceId]), [
        'content' => 'Ovozdan olingan transcript.',
        'is_final' => true,
    ])->assertStatus(409);
    assertDatabaseCount('voice_transcript_segments', 0);
});

it('escapes scenario content and keeps teacher ownership separate from admin power', function () {
    $owner = User::factory()->teacher()->create();
    $otherTeacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create([
        'created_by' => $owner->id,
        'title' => '<script>alert(1)</script>',
    ]);

    actingAs($student)->get(route('student.courtroom.scenarios.show', $scenario))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
    actingAs($otherTeacher)->get(route('admin.courtroom.edit', $scenario))->assertForbidden();
    actingAs($owner)->get(route('admin.courtroom.edit', $scenario))->assertOk();
    actingAs(User::factory()->academyAdmin()->create())->get(route('admin.courtroom.edit', $scenario))->assertOk();
});
