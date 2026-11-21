<?php

use App\Models\EvaluationResult;
use App\Models\ScenarioEvidence;
use App\Models\SessionAction;
use App\Models\SessionEvent;
use App\Models\SessionTurn;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('shows the parties beside their representatives without adding AI roles to the published version', function (string $legalArea, array $parties) {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['legal_area' => $legalArea]);
    $version = $scenario->versions()->sole();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id,
        'student_id' => $student->id, 'selected_role_key' => 'judge',
        'status' => 'active', 'current_stage_key' => 'PREPARATION', 'started_at' => now(),
    ]);

    $response = actingAs($student)->get(route('student.courtroom.sessions.show', $session));

    $response->assertOk()->assertViewHas('sceneParties', $parties)
        ->assertSee('data-scene-participant="defendant"', false)
        ->assertSee('Dalillar doskasi');
    expect($version->roles()->count())->toBe(4);
})->with([
    'civil parties' => ['civil', ['defendant' => 'Javobgar', 'claimant' => 'Da’vogar']],
    'criminal defendant' => ['criminal', ['defendant' => 'Ayblanuvchi']],
]);

it('does not duplicate a defendant already defined by the scenario', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->sole();
    $version->roles()->create([
        'key' => 'defendant', 'name' => 'Javobgar', 'description' => 'Ish tarafi.',
        'instructions' => 'Faqat ish faktlariga tayaning.', 'private_context' => '',
        'is_student_selectable' => false, 'sort_order' => 5,
    ]);
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id,
        'student_id' => $student->id, 'selected_role_key' => 'judge',
        'status' => 'active', 'current_stage_key' => 'PREPARATION', 'started_at' => now(),
    ]);

    actingAs($student)->get(route('student.courtroom.sessions.show', $session))
        ->assertOk()->assertViewHas('sceneParties', ['claimant' => 'Da’vogar'])
        ->assertSee('data-participant="defendant"', false)
        ->assertDontSee('data-scene-participant="defendant"', false);
});

it('shows only published courtroom scenarios to students', function () {
    $student = User::factory()->student()->create();
    $published = SimulationScenario::factory()->courtroomReady()->create(['title' => 'Nashr qilingan virtual sud']);
    SimulationScenario::factory()->create(['title' => 'Maxfiy qoralama']);

    $this->get(route('student.courtroom.index'))->assertRedirect(route('login'));
    actingAs($student)->get(route('student.courtroom.index'))
        ->assertOk()
        ->assertSee($published->title)
        ->assertDontSee('Maxfiy qoralama');
    actingAs($student)->get(route('student.courtroom.scenarios.show', $published))
        ->assertOk()
        ->assertSee('Sudda kim bo‘lasiz?')
        ->assertSee('Ta’lim uchun to‘qima ish');
});

it('creates a role-bound session and starts the authoritative first stage', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();

    actingAs($student)->post(route('student.courtroom.sessions.store'), [
        'scenario_id' => $scenario->id,
        'role_key' => 'defense_attorney',
        'captions' => true,
        'auto_camera' => true,
    ])->assertRedirect();

    $session = SimulationSession::query()->firstOrFail();
    expect($session->status)->toBe('created')
        ->and($session->selected_role_key)->toBe('defense_attorney')
        ->and($session->scenario_version_id)->toBe($scenario->versions()->firstOrFail()->id);

    actingAs($student)->post(route('student.courtroom.sessions.start', $session))->assertRedirect();

    expect($session->fresh()->status)->toBe('active')
        ->and($session->fresh()->current_stage_key)->toBe('PREPARATION');
    assertDatabaseCount('session_participants', 4);
    assertDatabaseHas('session_participants', [
        'simulation_session_id' => $session->id,
        'role_key' => 'defense_attorney',
        'user_id' => $student->id,
        'actor_type' => 'student',
    ]);
    assertDatabaseHas('session_events', ['simulation_session_id' => $session->id, 'type' => 'session.started']);
});

it('runs a complete text fallback simulation and creates an auditable report', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->firstOrFail();
    $evidence = ScenarioEvidence::query()->where('scenario_version_id', $version->id)->firstOrFail();
    config()->set('ai_tutor.api_key', null);

    actingAs($student)->post(route('student.courtroom.sessions.store'), [
        'scenario_id' => $scenario->id,
        'role_key' => 'prosecutor',
    ])->assertRedirect();
    $session = SimulationSession::query()->firstOrFail();
    actingAs($student)->post(route('student.courtroom.sessions.start', $session))->assertRedirect();

    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'make_statement',
        'content' => 'Shartnoma va qabul dalolatnomasidagi sanalarni taqqoslayman.',
        'idempotency_key' => (string) Str::uuid(),
        'input_type' => 'text',
    ])->assertStatus(202)->assertJsonPath('action.status', 'accepted')->assertJsonPath('ai', null)->assertJsonStructure(['ai_error']);
    expect(SessionAction::query()->firstOrFail()->ai_response)->toBeNull();
    assertDatabaseCount('session_turns', 1);

    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'advance_stage',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->assertJsonPath('session.current_stage', 'EVIDENCE_PRESENTATION');

    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'present_evidence',
        'content' => 'Ushbu shartnoma taraflar kelishgan muddatni ko‘rsatadi.',
        'evidence_id' => $evidence->id,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(202)->assertJsonPath('action.status', 'accepted');

    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'advance_stage',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->assertJsonPath('session.current_stage', 'EVALUATION');
    actingAs($student)->post(route('student.courtroom.sessions.complete', $session))->assertRedirect(route('student.courtroom.sessions.report', $session));

    $evaluation = EvaluationResult::query()->firstOrFail();
    expect($session->fresh()->status)->toBe('completed')
        ->and($evaluation->generated_by)->toBe('system')
        ->and($evaluation->status)->toBe('draft')
        ->and($evaluation->criterionResults()->count())->toBe(4);
    expect($evaluation->criterionResults()->whereNotNull('rationale')->count())->toBe(4);
    assertDatabaseHas('session_events', ['simulation_session_id' => $session->id, 'type' => 'session.completed']);
    actingAs($student)->get(route('student.courtroom.sessions.report', $session))
        ->assertOk()->assertSee('Bu yakuniy yuridik baho emas')->assertSee('Dalil:');
});

it('uses real provider output and isolates the selected student role private context', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'Sud ushbu fikrni qayd etdi. Faqat scenario faktlari ko‘rib chiqiladi.']]],
    ])]);
    $version = $scenario->versions()->firstOrFail();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'student_id' => $student->id,
        'selected_role_key' => 'defense_attorney',
        'status' => 'created',
    ]);
    actingAs($student)->post(route('student.courtroom.sessions.start', $session));

    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        'action_type' => 'make_statement',
        'content' => 'Dalolatnoma sanasi bo‘yicha o‘z pozitsiyamni bayon qilaman.',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->assertJsonPath('ai.role_key', 'judge')->assertJsonPath('ai.content', 'Sud ushbu fikrni qayd etdi. Faqat scenario faktlari ko‘rib chiqiladi.');

    Http::assertSent(function ($request): bool {
        $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($payload, 'judge uchun maxfiy kontekst')
            && ! str_contains($payload, 'defense_attorney uchun maxfiy kontekst')
            && ! array_key_exists('api_key', $request->data());
    });
    assertDatabaseHas('session_actions', ['simulation_session_id' => $session->id, 'ai_response' => 'Sud ushbu fikrni qayd etdi. Faqat scenario faktlari ko‘rib chiqiladi.']);
    assertDatabaseHas('session_turns', ['simulation_session_id' => $session->id, 'speaker_role_key' => 'judge', 'speaker_type' => 'ai']);
});

it('makes action idempotency keys prevent duplicate domain events and turns', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->firstOrFail();
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'student_id' => $student->id,
        'selected_role_key' => 'judge',
    ]);
    config()->set('ai_tutor.api_key', null);
    actingAs($student)->post(route('student.courtroom.sessions.start', $session));
    $key = (string) Str::uuid();
    $payload = ['action_type' => 'make_statement', 'content' => 'Sud majlisi tartibini tushuntiraman.', 'idempotency_key' => $key];

    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), $payload)->assertStatus(202);
    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), $payload)->assertOk();

    expect(SessionAction::query()->count())->toBe(1)
        ->and(SessionTurn::query()->where('speaker_type', 'student')->count())->toBe(1)
        ->and(SessionEvent::query()->where('type', 'action.accepted')->count())->toBe(1);
});

it('routes voice and text questions to permitted participants and preserves the original recipient on retries', function () {
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $version = $scenario->versions()->sole();
    $version->stages()->where('key', 'PREPARATION')->firstOrFail()->actionRules()->create([
        'role_key' => '*', 'action_type' => 'ask_question', 'label' => 'Savol berish', 'requires_content' => true,
        'response_role_keys' => ['prosecutor', 'witness'],
    ]);
    $session = SimulationSession::factory()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id, 'student_id' => $student->id,
        'selected_role_key' => 'defense_attorney', 'status' => 'created',
    ]);
    config()->set(['ai_tutor.api_key' => 'test-key', 'ai_tutor.base_url' => 'https://ai.example.test/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'Далолатнома ҳақида жавоб.']]],
    ])]);
    actingAs($student)->postJson(route('student.courtroom.sessions.start', $session))->assertOk();
    $actions = route('student.courtroom.sessions.actions', $session);
    $question = [
        'action_type' => 'ask_question', 'content' => 'Dalolatnomani kim tuzdi?',
        'input_type' => 'voice', 'idempotency_key' => (string) Str::uuid(),
    ];

    foreach (['judge', 'defense_attorney', 'unknown'] as $invalidTarget) {
        actingAs($student)->postJson($actions, [...$question, 'target_role_key' => $invalidTarget])
            ->assertUnprocessable()->assertJsonPath('error', 'Bu harakat uchun tanlangan ishtirokchiga murojaat qilishga ruxsat berilmagan.');
    }
    actingAs($student)->postJson($actions, [...$question, 'action_type' => 'advance_stage', 'target_role_key' => 'witness'])->assertUnprocessable();
    expect($session->actions()->count())->toBe(0);
    Http::assertNothingSent();

    $voiceQuestion = [...$question, 'target_role_key' => 'witness'];
    actingAs($student)->withSession(['locale' => 'uz-cyr'])->postJson($actions, $voiceQuestion)
        ->assertOk()->assertJsonPath('action.target_role_key', 'witness')->assertJsonPath('ai.role_key', 'witness');
    actingAs($student)->postJson($actions, [...$voiceQuestion, 'target_role_key' => 'prosecutor'])
        ->assertOk()->assertJsonPath('action.target_role_key', 'witness')->assertJsonPath('ai.role_key', 'witness');
    expect($session->events()->where('type', 'action.accepted')->sole()->payload['target_role_key'])->toBe('witness')
        ->and($session->turns()->where('speaker_type', 'student')->sole()->input_type)->toBe('voice');

    actingAs($student)->withSession(['locale' => 'ru'])->postJson($actions, [
        ...$question, 'input_type' => 'text', 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->assertJsonPath('action.target_role_key', 'prosecutor')->assertJsonPath('ai.role_key', 'prosecutor');
    Http::assertSent(function (Request $request): bool {
        $messages = $request->data()['messages'];
        $context = json_decode($messages[1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return str_contains($messages[0]['content'], 'kirill yozuvida')
            && $context['ROLE_CONTEXT']['key'] === 'witness'
            && str_contains($context['ROLE_CONTEXT']['private_context'], 'witness uchun')
            && ! str_contains($messages[1]['content'], 'judge uchun maxfiy kontekst');
    });
    Http::assertSent(fn (Request $request): bool => str_contains($request->data()['messages'][0]['content'], 'rus tilida'));
    Http::assertSentCount(2);
});
