<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\SimulationEvaluator;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config()->set('ai_tutor.api_key', null);
    $this->student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $this->version = $scenario->versions()->sole();
    $rubric = $this->version->rubric;
    $rubric->criteria()->where('key', 'legal_knowledge')->update(['weight' => 5, 'max_score' => 5]);
    foreach (['legal_reasoning' => 'Asoslash', 'speech' => 'Nutq'] as $key => $name) {
        $rubric->criteria()->create([
            'key' => $key, 'name' => $name, 'description' => $name, 'weight' => 10, 'max_score' => 10,
        ]);
    }
    $this->session = SimulationSession::factory()->active()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $this->version->id,
        'student_id' => $this->student->id, 'selected_role_key' => 'judge',
    ]);
    $this->submit = fn (string $type, array $input = []): int => actingAs($this->student)
        ->postJson(route('student.courtroom.sessions.actions', $this->session), [
            'action_type' => $type, 'idempotency_key' => fake()->uuid(), ...$input,
        ])->assertSuccessful()->json('action.id');
});

it('credits a concise position once across repeated, padded and procedural submissions', function () {
    $first = ($this->submit)('make_statement', ['content' => 'Imzo yo‘q.']);
    ($this->submit)('make_statement', ['content' => " IMZO YO'Q! Imzo yo‘q... "]);
    ($this->submit)('make_statement', ['content' => str_repeat('ha! ', 40)]);
    ($this->submit)('advance_stage', ['content' => 'Bosqich o‘tishini so‘rayman.']);
    ($this->submit)('advance_stage');
    actingAs($this->student)->postJson(route('student.courtroom.sessions.complete', $this->session))->assertOk();

    $results = $this->session->evaluation->criterionResults->keyBy('criterion.key');
    expect($results['legal_reasoning']->score)->toBe('2.00')
        ->and($results['speech']->score)->toBe('2.50')
        ->and($results['speech']->evidence_action_ids)->toBe([$first])
        ->and($results['legal_knowledge']->evidence_action_ids)->toBe([$first])
        ->and($results['procedure']->score)->toBe('25.00')
        ->and($this->session->fresh()->settings)->toMatchArray(['scoring_version' => SimulationEvaluator::SCORING_VERSION, 'captions' => true]);
});

it('distinguishes presenting a document from explaining it', function (string $commentary, bool $explained) {
    $evidence = $this->version->evidence()->sole();
    $this->version->evidence()->create([
        'title' => 'Yopiq hujjat', 'type' => 'witness_statement', 'visibility' => 'role',
        'summary' => 'Guvoh uchun qayd.', 'source_label' => 'O‘quv materiali',
        'allowed_role_keys' => ['witness'], 'status' => 'active',
    ]);
    ($this->submit)('advance_stage');
    $quoted = ($this->submit)('present_evidence', ['evidence_id' => $evidence->id, 'content' => $evidence->body]);
    $response = ($this->submit)('present_evidence', [
        'evidence_id' => $evidence->id, 'content' => '“'.$evidence->body.'” ('.$evidence->title.') '.$commentary,
    ]);
    ($this->submit)('advance_stage');
    actingAs($this->student)->postJson(route('student.courtroom.sessions.complete', $this->session))->assertOk();

    $results = $this->session->evaluation->criterionResults->keyBy('criterion.key');
    expect($results['legal_reasoning']->score)->toBe($explained ? '7.00' : '0.00')
        ->and($results['legal_reasoning']->evidence_action_ids)->toBe($explained ? [$response] : [])
        ->and($results['evidence_analysis']->score)->toBe($explained ? '25.00' : '12.50')
        ->and($results['evidence_analysis']->evidence_action_ids)->toBe($explained ? [$quoted, $response] : [$quoted]);
})->with([
    'quotation without comment' => ['', false],
    'quotation and student comment' => ['Yetkazish sanasi ko‘rsatilmagan.', true],
]);
