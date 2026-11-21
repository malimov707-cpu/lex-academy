<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    config()->set('virtual_courtroom.voice.server_speech_enabled', true);
    config()->set('virtual_courtroom.voice.api_key', 'server-only-test-key');
    config()->set('virtual_courtroom.voice.tts_provider', 'openai');
    config()->set('virtual_courtroom.voice.azure.key', null);
    $this->student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $this->session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $scenario->versions()->firstOrFail()->id,
        'student_id' => $this->student->id, 'selected_role_key' => 'judge', 'status' => 'active',
        'current_stage_key' => 'PREPARATION', 'started_at' => now(),
    ]);
    $this->voice = $this->session->voiceSessions()->create([
        'student_id' => $this->student->id, 'provider' => 'openai', 'status' => 'active',
        'consented_at' => now(), 'transcript_consent' => false, 'audio_recording' => false,
    ]);
});

it('transcribes validated audio through the backend without persisting an optional transcript', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Sud majlisini ochiq deb e’lon qilaman.'])]);
    $pcm = str_repeat("\0", 16000);
    $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    actingAs($this->student)->postJson(route('student.courtroom.voice.transcribe', [$this->session, $this->voice]), [
        'audio' => UploadedFile::fake()->createWithContent('speech.wav', $wav), 'locale' => 'uz-Latn',
    ])->assertOk()->assertJsonPath('content', 'Sud majlisini ochiq deb e’lon qilaman.')
        ->assertDontSee('server-only-test-key');
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer server-only-test-key') && $request->isMultipart());
    assertDatabaseCount('voice_transcript_segments', 0);
});

it('transcribes Uzbek speech through Azure when Azure Speech is enabled', function () {
    config()->set([
        'virtual_courtroom.voice.azure.enabled' => true,
        'virtual_courtroom.voice.azure.key' => 'azure-test-key-12345',
        'virtual_courtroom.voice.azure.region' => 'westus2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://westus2.api.cognitive.microsoft.com/speechtotext/transcriptions:transcribe?api-version=2025-10-15' => Http::response(['combinedPhrases' => [['text' => 'Sud majlisini ochiq deb e’lon qilaman.']]]),
    ]);
    $audio = UploadedFile::fake()->createWithContent('speech.webm', 'test audio');

    actingAs($this->student)->postJson(route('student.courtroom.voice.transcribe', [$this->session, $this->voice]), [
        'audio' => $audio, 'locale' => 'uz-Latn',
    ])->assertOk()->assertJsonPath('content', 'Sud majlisini ochiq deb e’lon qilaman.');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Ocp-Apim-Subscription-Key', 'azure-test-key-12345')
        && $request->isMultipart()
        && str_contains($request->body(), 'uz-UZ'));
});

it('speaks only a server recorded AI turn belonging to the authorized session', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/audio/speech' => Http::response('audio-bytes', 200, ['Content-Type' => 'audio/mpeg'])]);
    $turn = $this->session->turns()->create(['sequence' => 1, 'speaker_role_key' => 'prosecutor', 'speaker_type' => 'ai', 'content' => 'Dalillarni tekshirishni so‘rayman.']);
    actingAs($this->student)->post(route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]), ['content' => 'Klientning o‘zboshimcha matni'])
        ->assertOk()->assertHeader('Content-Type', 'audio/mpeg')->assertContent('audio-bytes');
    Http::assertSent(fn ($request): bool => $request['input'] === $turn->content && $request['response_format'] === 'mp3');
});

it('returns provider-timed visemes with the audio they describe and preserves Uzbek Cyrillic input', function () {
    config()->set([
        'virtual_courtroom.voice.tts_provider' => 'inworld',
        'virtual_courtroom.voice.inworld_api_key' => 'timed-server-key',
        'virtual_courtroom.voice.inworld_voice' => 'court-test-voice',
    ]);
    Http::preventStrayRequests();
    $pcm = str_repeat("\0", 16000);
    $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    $alignment = ['visemes' => ['sil', 'PP', 'ou'], 'visemeStartTimeSeconds' => [0, .1, .5], 'visemeEndTimeSeconds' => [.1, .5, 1]];
    Http::fake(['https://api.inworld.ai/tts/v1/voice' => Http::sequence()
        ->push(['audioContent' => base64_encode($wav), 'timestampInfo' => ['lipsyncAlignment' => $alignment]])
        ->push(['audioContent' => base64_encode($wav), 'timestampInfo' => ['lipsyncAlignment' => [...$alignment, 'visemeStartTimeSeconds' => [0, .6, .2]]]])]);
    $turn = $this->session->turns()->create([
        'sequence' => 1, 'speaker_role_key' => 'judge', 'speaker_type' => 'ai', 'content' => 'Суд мажлисини очаман.', 'metadata' => ['locale' => 'uz-cyr'],
    ]);
    $url = route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]);
    actingAs($this->student)->postJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('mime', 'audio/wav')->assertJsonPath('audio_base64', base64_encode($wav))
        ->assertJsonPath('visemes.1.shape', 'PP')->assertJsonPath('visemes.1.start', .1)->assertJsonPath('visemes.2.shape', 'U')
        ->assertDontSee('timed-server-key');
    actingAs($this->student)->postJson($url)->assertOk()->assertHeader('Content-Type', 'audio/wav')->assertContent($wav);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Basic timed-server-key')
        && $request['text'] === $turn->content && $request['language'] === 'uz-UZ' && $request['lipsyncConfig']['visemeSet'] === 'OPENXR');
    Http::assertSentCount(2);
});

it('rejects another students access and refuses student turns as AI audio', function () {
    Http::preventStrayRequests();
    $turn = $this->session->turns()->create(['sequence' => 1, 'speaker_role_key' => 'judge', 'speaker_type' => 'student', 'content' => 'Talaba nutqi.']);
    $url = route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]);
    actingAs(User::factory()->student()->create())->post($url)->assertForbidden();
    actingAs($this->student)->post($url)->assertNotFound();
    Http::assertNothingSent();
});

it('keeps provider error bodies and secrets out of voice responses', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/audio/speech' => Http::response(['error' => 'provider-secret-error'], 429)]);
    $turn = $this->session->turns()->create(['sequence' => 1, 'speaker_role_key' => 'prosecutor', 'speaker_type' => 'ai', 'content' => 'Javob.']);
    actingAs($this->student)->postJson(route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]))
        ->assertStatus(503)->assertDontSee('provider-secret-error')->assertDontSee('server-only-test-key');
    Http::assertSentCount(1);
});

it('rejects audio when voice consent has ended or the server provider is disabled', function () {
    Http::preventStrayRequests();
    $turn = $this->session->turns()->create(['sequence' => 1, 'speaker_role_key' => 'prosecutor', 'speaker_type' => 'ai', 'content' => 'Javob.']);
    $url = route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]);
    config()->set('virtual_courtroom.voice.server_speech_enabled', false);
    actingAs($this->student)->postJson($url)->assertStatus(503);
    $this->voice->update(['status' => 'ended']);
    actingAs($this->student)->postJson($url)->assertConflict();
    Http::assertNothingSent();
});

it('prunes expired optional transcripts while retaining current transcripts and court turns', function () {
    $this->freezeTime();
    config()->set('virtual_courtroom.voice.transcript_retention_days', 30);
    $this->travel(-31)->days();
    $old = $this->voice->segments()->create(['sequence' => 1, 'speaker_role_key' => 'judge', 'content' => 'Eski transcript.']);
    $this->travel(31)->days();
    $current = $this->voice->segments()->create(['sequence' => 2, 'speaker_role_key' => 'judge', 'content' => 'Yangi transcript.']);
    $this->session->turns()->create(['sequence' => 1, 'speaker_role_key' => 'judge', 'speaker_type' => 'student', 'content' => 'Suddagi harakat.']);
    $this->artisan('courtroom:prune-transcripts')->assertSuccessful();
    expect($old->fresh())->toBeNull()->and($current->fresh())->not->toBeNull();
    assertDatabaseCount('session_turns', 1);
});

it('synthesizes speech with Azure Neural Voices using role-based voices for Uzbek', function () {
    config()->set([
        'virtual_courtroom.voice.tts_provider' => 'azure',
        'virtual_courtroom.voice.azure.enabled' => true,
        'virtual_courtroom.voice.azure.key' => 'azure-test-key-12345',
        'virtual_courtroom.voice.azure.region' => 'westus2',
        'virtual_courtroom.voice.azure.male_voice' => 'uz-UZ-SardorNeural',
        'virtual_courtroom.voice.azure.female_voice' => 'uz-UZ-MadinaNeural',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://westus2.tts.speech.microsoft.com/cognitiveservices/v1' => Http::response('azure-audio-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    $turn = $this->session->turns()->create([
        'sequence' => 1,
        'speaker_role_key' => 'judge',
        'speaker_type' => 'ai',
        'content' => 'Sud majlisi davom etadi.',
    ]);

    actingAs($this->student)->post(route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertContent('azure-audio-bytes');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Ocp-Apim-Subscription-Key', 'azure-test-key-12345')
            && str_contains($request->body(), "voice name='uz-UZ-SardorNeural'")
            && str_contains($request->body(), 'Sud majlisi davom etadi.');
    });
});

it('synthesizes female roles with MadinaNeural in Azure Speech', function () {
    config()->set([
        'virtual_courtroom.voice.tts_provider' => 'azure',
        'virtual_courtroom.voice.azure.enabled' => true,
        'virtual_courtroom.voice.azure.key' => 'azure-test-key-12345',
        'virtual_courtroom.voice.azure.region' => 'westus2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://westus2.tts.speech.microsoft.com/cognitiveservices/v1' => Http::response('azure-madina-audio', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    $turn = $this->session->turns()->create([
        'sequence' => 2,
        'speaker_role_key' => 'witness_1',
        'speaker_type' => 'ai',
        'content' => 'Men voqeani ko‘rgan edim.',
    ]);

    actingAs($this->student)->post(route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]))
        ->assertOk()
        ->assertContent('azure-madina-audio');

    Http::assertSent(function ($request): bool {
        return str_contains($request->body(), "voice name='uz-UZ-MadinaNeural'");
    });
});

it('changes Azure speech prosody by courtroom role', function () {
    config()->set([
        'virtual_courtroom.voice.tts_provider' => 'azure',
        'virtual_courtroom.voice.azure.enabled' => true,
        'virtual_courtroom.voice.azure.key' => 'azure-test-key-12345',
        'virtual_courtroom.voice.azure.region' => 'westus2',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://westus2.tts.speech.microsoft.com/cognitiveservices/v1' => Http::response('azure-audio-bytes', 200, ['Content-Type' => 'audio/mpeg'])]);

    foreach (['prosecutor', 'defense_advocate'] as $role) {
        $turn = $this->session->turns()->create([
            'sequence' => $this->session->turns()->count() + 1, 'speaker_role_key' => $role,
            'speaker_type' => 'ai', 'content' => 'Huquqiy pozitsiyamni bayon qilaman.',
        ]);
        actingAs($this->student)->post(route('student.courtroom.voice.speak', [$this->session, $this->voice, $turn]))
            ->assertOk()->assertContent('azure-audio-bytes');
    }

    $requests = Http::recorded()->map(fn ($recorded) => $recorded[0]->body());
    expect($requests)->toHaveCount(2)
        ->and($requests[0])->not->toBe($requests[1])
        ->and($requests->every(fn (string $body) => str_contains($body, '<prosody rate=')))->toBeTrue();
});
