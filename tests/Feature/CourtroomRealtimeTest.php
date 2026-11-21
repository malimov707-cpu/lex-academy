<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config()->set([
        'virtual_courtroom.voice.mode' => 'realtime',
        'virtual_courtroom.voice.server_speech_enabled' => true,
        'virtual_courtroom.voice.api_key' => 'server-only-realtime-key',
    ]);
    $this->student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $this->session = SimulationSession::factory()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $scenario->versions()->sole()->id,
        'student_id' => $this->student->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $this->voice = $this->session->voiceSessions()->create([
        'student_id' => $this->student->id,
        'provider' => 'openai',
        'status' => 'active',
        'consented_at' => now(),
    ]);
    $this->url = route('student.courtroom.voice.realtime', [$this->session, $this->voice]);
    $this->offer = ['sdp' => "v=0\r\ns=WebRTC offer\r\n", 'locale' => 'uz-Latn'];
});

it('exchanges a WebRTC offer with the server configuration and keeps API credentials private', function () {
    Http::preventStrayRequests();
    $answer = "v=0\r\ns=WebRTC answer\r\n";
    Http::fake(['https://api.openai.com/v1/realtime/calls' => Http::response($answer, 201)]);

    actingAs($this->student)->postJson($this->url, [
        ...$this->offer, 'model' => 'untrusted-model', 'instructions' => 'untrusted-instructions',
    ])->assertOk()->assertExactJson(['sdp' => $answer])->assertHeader('Cache-Control', 'no-store, private');

    Http::assertSent(function (Request $request): bool {
        $parts = collect($request->data())->keyBy('name');
        $configuration = json_decode($parts['session']['contents'], true);

        return $request->hasHeader('Authorization', 'Bearer server-only-realtime-key')
            && $request->hasHeader('OpenAI-Safety-Identifier')
            && $parts['sdp']['contents'] === $this->offer['sdp']
            && $configuration['model'] === config('virtual_courtroom.voice.realtime_model')
            && $configuration['output_modalities'] === ['text']
            && $configuration['audio']['input']['turn_detection'] === null
            && $configuration['audio']['input']['transcription']['language'] === 'uz'
            && ! isset($configuration['instructions']);
    });
});

it('requires the session owner and current microphone consent before contacting OpenAI', function () {
    Http::preventStrayRequests();

    actingAs(User::factory()->student()->create())->postJson($this->url, $this->offer)->assertForbidden();
    $this->voice->update(['status' => 'ended']);
    actingAs($this->student)->postJson($this->url, $this->offer)->assertConflict();
    Http::assertNothingSent();
});

it('rejects invalid offers and expired court sessions', function () {
    Http::preventStrayRequests();

    actingAs($this->student)->postJson($this->url, ['sdp' => 'invalid', 'locale' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors(['sdp', 'locale']);
    $this->session->update(['started_at' => now()->subMinutes(91)]);
    actingAs($this->student)->postJson($this->url, $this->offer)->assertConflict();
    Http::assertNothingSent();
});

it('returns a safe fallback when the provider refuses the connection', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/realtime/calls' => Http::response(['internal' => 'provider error details'], 429)]);

    actingAs($this->student)->postJson($this->url, $this->offer)->assertServiceUnavailable()
        ->assertJsonPath('error', 'Jonli ovoz xizmatiga ulanish muvaffaqiyatsiz (HTTP 429).')
        ->assertDontSee('provider error details');
    Http::assertSentCount(1);
});
