<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->student = User::factory()->student()->create();
    $this->teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $this->teacher->id]);
    $this->session = SimulationSession::factory()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $scenario->versions()->sole()->id,
        'student_id' => $this->student->id, 'selected_role_key' => 'judge', 'status' => 'created',
    ]);
    config()->set('ai_tutor.api_key', null);
    actingAs($this->student)->postJson(route('student.courtroom.sessions.start', $this->session))->assertOk();
    $actions = route('student.courtroom.sessions.actions', $this->session);
    actingAs($this->student)->postJson($actions, [
        'action_type' => 'make_statement', 'content' => 'Dalolatnoma sanasini aniqlayman.', 'idempotency_key' => (string) Str::uuid(),
    ])->assertAccepted();
    foreach (['EVIDENCE_PRESENTATION', 'EVALUATION'] as $stage) {
        actingAs($this->student)->postJson($actions, ['action_type' => 'advance_stage', 'idempotency_key' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('session.current_stage', $stage);
    }
    $this->reportUrl = route('student.courtroom.sessions.report', $this->session);
    actingAs($this->student)->post(route('student.courtroom.sessions.complete', $this->session))->assertRedirect($this->reportUrl);
    $this->evaluation = $this->session->evaluation()->sole();
    $this->feedbackUrl = route('student.courtroom.sessions.report.feedback', $this->session);
    $this->item = [
        'criterion_key' => 'procedure', 'action_ids' => [$this->session->actions()->where('action_type', 'make_statement')->sole()->id],
        'observation' => 'Dalolatnoma sanasini aniqlashni taklif qildingiz.', 'next_exercise' => 'Qaysi hujjatdan sanani tekshirishni mashq qiling.',
    ];
    config()->set(['ai_tutor.api_key' => 'test-feedback-key', 'ai_tutor.base_url' => 'https://feedback.example.test/v1']);
    Http::preventStrayRequests();
});

it('stores a grounded recommendation once and displays it to the student and teacher', function () {
    Http::fake(['https://feedback.example.test/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['items' => [$this->item]], JSON_THROW_ON_ERROR)]]],
    ])]);
    actingAs(User::factory()->student()->create())->post($this->feedbackUrl)->assertForbidden();
    actingAs($this->student)->get($this->reportUrl)->assertOk()->assertSee('AI tavsiyasini olish');
    $lock = Cache::lock('courtroom-learning-feedback:'.$this->evaluation->id, 30);
    $lock->get();
    try {
        actingAs($this->student)->post($this->feedbackUrl)->assertRedirect($this->reportUrl)->assertSessionHasErrors('ai_feedback');
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
    actingAs($this->student)->post($this->feedbackUrl)->assertRedirect($this->reportUrl)->assertSessionHasNoErrors();
    actingAs($this->student)->post($this->feedbackUrl)->assertRedirect($this->reportUrl);
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $context = json_decode($request->data()['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return $context['actions'][0]['id'] === $this->item['action_ids'][0]
            && $context['actions'][0]['content'] === 'Dalolatnoma sanasini aniqlayman.'
            && ! isset($context['student_id'])
            && ! isset($context['email']);
    });
    expect($this->evaluation->fresh()->ai_feedback)->toBe([$this->item])
        ->and($this->evaluation->fresh()->overall_score)->toBe($this->evaluation->overall_score)
        ->and($this->evaluation->fresh()->generated_by)->toBe('system');
    actingAs($this->student)->get($this->reportUrl)
        ->assertOk()->assertSee($this->item['observation'])->assertSee($this->item['next_exercise'])->assertDontSee('AI tavsiyasini olish');
    actingAs($this->teacher)->get(route('admin.courtroom.reviews.show', $this->evaluation))
        ->assertOk()->assertSee($this->item['next_exercise']);
});

it('retains the original assessment when advice cites an unrelated action or unverified source', function (array $override) {
    Http::fake(['https://feedback.example.test/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['items' => [[...$this->item, ...$override]]], JSON_THROW_ON_ERROR)]]],
    ])]);

    actingAs($this->student)->post($this->feedbackUrl)->assertRedirect($this->reportUrl)->assertSessionHasErrors('ai_feedback');
    expect($this->evaluation->fresh()->ai_feedback)->toBeNull()
        ->and($this->evaluation->fresh()->overall_score)->toBe($this->evaluation->overall_score);
    Http::assertSentCount(1);
})->with([
    'unrelated action' => [['action_ids' => [987654]]],
    'unverified reference' => [['observation' => 'REF-987654 havolasiga qarang.']],
    'extra score' => [['score' => 100]],
]);
