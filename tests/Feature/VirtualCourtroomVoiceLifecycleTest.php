<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\SimulationStateMachine;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    config()->set('virtual_courtroom.voice.browser_speech_enabled', true);
    $this->student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $this->session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $scenario->versions()->firstOrFail()->id,
        'student_id' => $this->student->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'current_stage_key' => 'PREPARATION',
        'started_at' => now(),
    ]);
    $this->voice = $this->session->voiceSessions()->create([
        'student_id' => $this->student->id,
        'provider' => 'browser',
        'status' => 'active',
        'transcript_consent' => true,
        'audio_recording' => false,
        'consented_at' => now(),
    ]);
});

it('stores ordered transcript segments and refuses more after voice ends', function () {
    $segmentUrl = route('student.courtroom.voice.segments.store', [$this->session, $this->voice]);
    foreach ([1, 2] as $sequence) {
        actingAs($this->student)->postJson($segmentUrl, [
            'content' => 'Sud majlisidagi nutq.',
            'is_final' => true,
        ])->assertCreated()->assertJsonPath('segment.sequence', $sequence);
    }

    actingAs($this->student)->postJson(route('student.courtroom.voice.destroy', [$this->session, $this->voice]))
        ->assertOk()->assertJsonPath('status', 'ended');
    actingAs($this->student)->postJson($segmentUrl, ['content' => 'Yakunlangan sessiya.', 'is_final' => true])
        ->assertConflict();
    assertDatabaseCount('voice_transcript_segments', 2);
});

it('allows retrying voice termination without changing its original end time', function () {
    $this->freezeTime();
    $url = route('student.courtroom.voice.destroy', [$this->session, $this->voice]);
    actingAs($this->student)->postJson($url)->assertOk();
    $endedAt = $this->voice->fresh()->ended_at;
    $this->travel(1)->minutes();
    actingAs($this->student)->postJson($url)->assertOk();
    expect($this->voice->fresh()->ended_at->equalTo($endedAt))->toBeTrue();
});

it('ends active voice sessions when the court simulation completes', function () {
    $terminal = $this->session->version->stages()->where('is_terminal', true)->firstOrFail();
    $this->session->update(['current_stage_key' => $terminal->key]);

    app(SimulationStateMachine::class)->complete($this->session);

    expect($this->voice->fresh()->status)->toBe('ended')
        ->and($this->voice->fresh()->ended_at)->not->toBeNull();
    actingAs($this->student)->postJson(route('student.courtroom.voice.segments.store', [$this->session, $this->voice]), [
        'content' => 'Sud yakunlangan.', 'is_final' => true,
    ])->assertConflict();
    assertDatabaseCount('voice_transcript_segments', 0);
});

it('does not let another student end a voice session', function () {
    actingAs(User::factory()->student()->create())
        ->postJson(route('student.courtroom.voice.destroy', [$this->session, $this->voice]))
        ->assertForbidden();
    expect($this->voice->fresh()->status)->toBe('active');
});

it('closes the previous voice session when reconnecting with new consent', function () {
    $response = actingAs($this->student)->postJson(route('student.courtroom.voice.store', $this->session), [
        'consent' => true,
        'transcript_consent' => false,
    ])->assertCreated();

    expect($this->voice->fresh()->status)->toBe('ended')
        ->and($this->session->voiceSessions()->where('status', 'active')->count())->toBe(1);
    $newVoice = $this->session->voiceSessions()->findOrFail($response->json('voice_session.id'));
    expect($newVoice->transcript_consent)->toBeFalse()
        ->and($newVoice->audio_recording)->toBeFalse();
});

it('rejects transcript writes after the parent session is no longer active', function () {
    $this->session->update(['status' => 'completed']);

    actingAs($this->student)->postJson(route('student.courtroom.voice.segments.store', [$this->session, $this->voice]), [
        'content' => 'Kechikkan transcript.',
        'is_final' => true,
    ])->assertConflict();

    assertDatabaseCount('voice_transcript_segments', 0);
});
