<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

function courtroomDefinition(): array
{
    return [
        'roles' => [
            ['key' => 'judge', 'name' => 'Sudya', 'description' => 'Jarayonni boshqaradi.', 'instructions' => 'Xolis bo‘ling.', 'private_context' => 'Faqat sudya uchun ko‘rsatma.', 'is_student_selectable' => true],
            ['key' => 'prosecutor', 'name' => 'Da’vogar vakili', 'description' => 'Da’voni asoslaydi.', 'instructions' => 'Faktlardan foydalaning.', 'private_context' => 'Da’vogar uchun yo‘riqnoma.', 'is_student_selectable' => false],
        ],
        'stages' => [
            ['key' => 'OPENING', 'name' => 'Majlis', 'description' => 'Kirish.', 'is_terminal' => false, 'allowed_next_stages' => ['REPORT'], 'actions' => [
                ['role_key' => 'judge', 'action_type' => 'advance_stage', 'label' => 'Yakunlash', 'requires_content' => true, 'requires_evidence' => false, 'advances_to_stage_key' => 'REPORT'],
            ]],
            ['key' => 'REPORT', 'name' => 'Hisobot', 'description' => 'Natijalar.', 'is_terminal' => true, 'allowed_next_stages' => [], 'actions' => []],
        ],
        'criteria' => [['key' => 'procedure', 'name' => 'Protsessual tartib', 'description' => 'Tartibni saqlash.', 'weight' => 100, 'guidance' => 'Harakatlarga qarang.']],
    ];
}

it('edits roles stages actions and rubric in a new draft while preserving a running sessions snapshot', function () {
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $source = $scenario->versions()->firstOrFail();
    $oldInstructions = $source->roles()->where('key', 'judge')->firstOrFail()->instructions;
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $source->id,
        'student_id' => User::factory()->student()->create()->id,
        'selected_role_key' => 'judge', 'status' => 'active', 'current_stage_key' => 'PREPARATION',
    ]);
    actingAs($teacher)->get(route('admin.courtroom.definition', $scenario))->assertOk()->assertSee('Baholash rubrikasi');
    actingAs($teacher)->putJson(route('admin.courtroom.definition.update', $scenario), courtroomDefinition())->assertRedirect();
    $draft = $scenario->versions()->latest('version')->firstOrFail();
    expect($draft->status)->toBe('draft')->and($draft->version)->toBe($source->version + 1)
        ->and($draft->roles()->where('key', 'judge')->firstOrFail()->instructions)->toBe('Xolis bo‘ling.')
        ->and($draft->stages()->count())->toBe(2)
        ->and($draft->rubric->criteria()->sole()->weight)->toBe(100)
        ->and($draft->evidence()->count())->toBe($source->evidence()->count())
        ->and($draft->legalReferences()->count())->toBe($source->legalReferences()->count())
        ->and($source->roles()->where('key', 'judge')->firstOrFail()->instructions)->toBe($oldInstructions)
        ->and($source->fresh()->status)->toBe('published')
        ->and($session->fresh()->scenario_version_id)->toBe($source->id);
});

it('rejects disconnected stages invalid transitions duplicate actions and invalid rubric totals', function (string $defect) {
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $data = courtroomDefinition();
    if ($defect === 'unreachable') {
        $data['stages'][1]['key'] = 'HIDDEN';
    } elseif ($defect === 'cycle') {
        $data['stages'][1]['is_terminal'] = false;
        $data['stages'][1]['allowed_next_stages'] = ['OPENING'];
    } elseif ($defect === 'duplicate') {
        $data['stages'][0]['actions'][] = $data['stages'][0]['actions'][0];
    } elseif ($defect === 'role') {
        $data['stages'][0]['actions'][0]['role_key'] = 'unknown';
    } else {
        $data['criteria'][0]['weight'] = 25;
    }
    actingAs($teacher)->putJson(route('admin.courtroom.definition.update', $scenario), $data)->assertUnprocessable();
    expect($scenario->versions()->count())->toBe(1);
})->with(['unreachable', 'cycle', 'duplicate', 'role', 'weights']);

it('refuses another teachers attempt to alter a scenario definition', function () {
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => User::factory()->teacher()->create()->id]);
    actingAs(User::factory()->teacher()->create())
        ->putJson(route('admin.courtroom.definition.update', $scenario), courtroomDefinition())->assertForbidden();
    expect($scenario->versions()->count())->toBe(1);
});

it('validates and stores the responders on the draft action without changing its published version', function () {
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $published = $scenario->versions()->sole();
    $definition = courtroomDefinition();
    $definition['stages'][0]['actions'][] = [
        'role_key' => 'judge', 'action_type' => 'ask_question', 'label' => 'Savol berish',
        'requires_content' => true, 'requires_evidence' => false, 'response_role_keys' => ['unknown'],
    ];
    $update = route('admin.courtroom.definition.update', $scenario);
    actingAs($teacher)->putJson($update, $definition)->assertUnprocessable()->assertJsonValidationErrors('stages.0.actions.1.response_role_keys');
    $definition['stages'][0]['actions'][1]['response_role_keys'] = ['prosecutor'];
    actingAs($teacher)->putJson($update, $definition)->assertRedirect();

    $draft = $scenario->versions()->where('status', 'draft')->sole();
    $rule = $draft->stages()->where('key', 'OPENING')->sole()->actionRules()->where('action_type', 'ask_question')->sole();
    expect($rule->response_role_keys)->toBe(['prosecutor'])
        ->and($published->stages()->where('key', 'OPENING')->exists())->toBeFalse();
    actingAs($teacher)->get(route('admin.courtroom.definition', $scenario))->assertOk()->assertSee('Javob berishi mumkin bo‘lgan rollar');
});

it('rehearses the real stage and evidence rules without creating a student session', function () {
    Http::preventStrayRequests();
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $version = $scenario->versions()->sole();
    $evidence = $version->evidence()->sole();
    $evidence->update(['visibility' => 'roles', 'allowed_role_keys' => ['judge']]);
    $url = route('admin.courtroom.rehearsal', [$scenario, $version->version]);

    actingAs(User::factory()->teacher()->create())->get($url)->assertForbidden();
    actingAs(User::factory()->student()->create())->post($url)->assertForbidden();
    actingAs($teacher)->get($url)->assertOk()->assertSee('Ssenariy qoidalarini sinash');
    $initial = ['role_key' => 'judge', 'stage_key' => 'PREPARATION'];
    actingAs($teacher)->post($url, [...$initial, 'action_type' => 'present_evidence'])
        ->assertOk()->assertSee('Bu bosqichda ushbu harakatga ruxsat berilmagan.');
    actingAs($teacher)->post($url, [...$initial, 'action_type' => 'advance_stage'])
        ->assertOk()->assertSee('Harakat qabul qilindi.')->assertSee('stage_key=EVIDENCE_PRESENTATION');

    $presentation = [
        'stage_key' => 'EVIDENCE_PRESENTATION', 'action_type' => 'present_evidence',
        'content' => 'Dalilni taqdim etaman.', 'evidence_id' => $evidence->id,
    ];
    actingAs($teacher)->post($url, [...$presentation, 'role_key' => 'defense_attorney'])
        ->assertOk()->assertSee('Dalil topilmadi yoki bu rol uchun ochiq emas.');
    actingAs($teacher)->post($url, [...$presentation, 'role_key' => 'judge'])
        ->assertOk()->assertSee('Harakat qabul qilindi.');
    expect(SimulationSession::query()->count())->toBe(0);
    Http::assertNothingSent();
});
