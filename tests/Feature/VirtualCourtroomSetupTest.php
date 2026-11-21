<?php

use App\Models\SimulationAsset;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Database\Seeders\CourtroomAssetSeeder;
use Database\Seeders\SimulationScenarioSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

it('installs each practice case as an unapproved teacher-owned draft with its own witness and evidence', function (string $slug, string $witness, string $exhibit) {
    User::factory()->academyAdmin()->create();
    $teacher = User::factory()->teacher()->create();

    $this->seed(SimulationScenarioSeeder::class);

    $scenario = SimulationScenario::query()->where('slug', $slug)->sole();
    $version = $scenario->versions()->sole();
    expect($scenario->created_by)->toBe($teacher->id)
        ->and($scenario->status)->toBe('draft')
        ->and($version->status)->toBe('draft')
        ->and($version->approved_by)->toBeNull()
        ->and($version->approved_at)->toBeNull()
        ->and($version->published_at)->toBeNull()
        ->and($version->legalReferences()->whereNotNull('verified_at')->count())->toBe(0)
        ->and($version->roles()->where('key', 'witness')->sole()->name)->toBe($witness)
        ->and($version->evidence()->pluck('title'))->toContain($exhibit)
        ->and($version->roles()->count())->toBe(4)
        ->and($version->roles()->where('is_student_selectable', true)->pluck('key')->all())->toBe(['judge', 'prosecutor', 'defense_attorney'])
        ->and($version->stages()->count())->toBe(11)
        ->and($version->evidence()->count())->toBe(3)
        ->and($version->rubric->criteria()->count())->toBe(9);

    actingAs(User::factory()->student()->create())->get(route('student.courtroom.scenarios.show', $scenario))->assertForbidden();
})->with([
    ['shartnoma-majburiyati-boyicha-oquv-sudi', 'Ombor mudiri — AI rol', 'Yetkazib berish shartnomasi'],
    ['ijara-taminot-puli', 'Eshik ustasi', 'Eshikni ta’mirlash smetasi'],
    ['velosiped-tamiri-buyurtmasi', 'Ustaxona mexanigi', 'Mexanikning ish jurnali'],
]);

it('installs the criminal scenario for the separate group courtroom section', function () {
    User::factory()->teacher()->create();
    $this->seed(SimulationScenarioSeeder::class);

    $scenario = SimulationScenario::query()->where('slug', 'uyga-kiralish-bilan-oqrilik')->sole();
    $version = $scenario->versions()->sole();

    expect($scenario->legal_area)->toBe('criminal')
        ->and($scenario->status)->toBe('published')
        ->and($version->status)->toBe('published')
        ->and($version->roles()->count())->toBe(4)
        ->and($version->evidence()->count())->toBe(3)
        ->and($version->evidence()->pluck('title')->all())->toContain('Yo‘lak kamera yozuvi');

    $student = User::factory()->student()->create();
    actingAs($student)->get(route('student.courtroom.index'))
        ->assertOk()
        ->assertDontSee($scenario->title);
    actingAs($student)->get(route('courtroom.groups.index'))
        ->assertOk()
        ->assertSee($scenario->title);
});

it('preserves teacher changes and published snapshots when the seeder runs again', function () {
    $teacher = User::factory()->teacher()->create();
    $this->seed(SimulationScenarioSeeder::class);
    $scenario = SimulationScenario::query()->where('slug', 'shartnoma-majburiyati-boyicha-oquv-sudi')->sole();
    $scenario->update(['title' => 'O‘qituvchi tahrirlagan ssenariy', 'status' => 'published']);
    $version = $scenario->versions()->sole();
    $version->update(['case_facts' => 'O‘qituvchi tekshirgan faktlar.', 'status' => 'published', 'approved_by' => $teacher->id, 'approved_at' => now(), 'published_at' => now()]);
    $before = $version->fresh()->getAttributes();

    $this->seed(SimulationScenarioSeeder::class);

    expect(SimulationScenario::query()->count())->toBe(4)
        ->and($scenario->fresh()->title)->toBe('O‘qituvchi tahrirlagan ssenariy')
        ->and($version->fresh()->getAttributes())->toBe($before);
});

it('lets only the owner and administrators preview a draft without creating a student session', function () {
    $owner = User::factory()->teacher()->create();
    $this->seed(SimulationScenarioSeeder::class);
    $scenario = SimulationScenario::query()->where('slug', 'shartnoma-majburiyati-boyicha-oquv-sudi')->sole();
    $url = route('admin.courtroom.preview', $scenario);

    actingAs($owner)->get($url)->assertOk()
        ->assertSee('O‘qituvchi uchun oldindan ko‘rish')
        ->assertSee('O‘qituvchi tekshiruvi kutilmoqda')
        ->assertSee('Yetkazib berish shartnomasi')
        ->assertSee('Baholash mezonlari')
        ->assertDontSee('Rolni tanlash va briefingga o‘tish');
    actingAs(User::factory()->academyAdmin()->create())->get($url)->assertOk();
    actingAs(User::factory()->teacher()->create())->get($url)->assertForbidden();
    actingAs(User::factory()->student()->create())->get($url)->assertForbidden();
    expect(SimulationSession::query()->count())->toBe(0);
});

it('records source verification only with explicit confirmation on the latest draft', function () {
    $owner = User::factory()->teacher()->create();
    $this->seed(SimulationScenarioSeeder::class);
    $scenario = SimulationScenario::query()->where('slug', 'shartnoma-majburiyati-boyicha-oquv-sudi')->sole();
    $version = $scenario->versions()->sole();
    $reference = $version->legalReferences()->oldest('id')->firstOrFail();
    $preview = route('admin.courtroom.preview', $scenario);
    $verify = route('admin.courtroom.references.verify', [$scenario, $reference]);
    $review = ['confirmed' => '1', 'citation_text' => 'Sinov uchun norma va hujjat tahriri izohi.'];

    actingAs(User::factory()->student()->create())->patchJson($verify, $review)->assertForbidden();
    actingAs(User::factory()->teacher()->create())->patchJson($verify, $review)->assertForbidden();
    actingAs($owner)->get($preview)->assertOk()->assertSee($verify)->assertSee('Manbani tasdiqlash');
    actingAs($owner)->patchJson($verify)->assertUnprocessable()->assertJsonValidationErrors(['confirmed', 'citation_text']);
    expect($reference->fresh()->verified_at)->toBeNull();

    $originalUrl = $reference->source_url;
    $reference->update(['source_url' => 'https://']);
    actingAs($owner)->patchJson($verify, $review)->assertUnprocessable()->assertJsonValidationErrors('legal_reference');
    $reference->update(['source_url' => $originalUrl]);

    actingAs($owner)->patch($verify, $review)->assertRedirect($preview)->assertSessionHasNoErrors();
    actingAs(User::factory()->academyAdmin()->create())
        ->patch($verify, [...$review, 'citation_text' => 'Takroriy so‘rovdagi izoh.'])->assertRedirect($preview);
    expect($reference->fresh()->verified_by)->toBe($owner->id)
        ->and($reference->fresh()->verified_at)->not->toBeNull()
        ->and($reference->fresh()->citation_text)->toBe($review['citation_text'])
        ->and($version->fresh()->status)->toBe('draft')
        ->and($version->legalReferences()->whereNotNull('verified_at')->count())->toBe(1);
    actingAs($owner)->get($preview)->assertOk()->assertSee($review['citation_text'])->assertSee('Tekshirilgan sana')->assertDontSee($verify);

    actingAs($owner)->patch(route('admin.courtroom.publish', $scenario))->assertRedirect()->assertSessionHasNoErrors();
    actingAs($owner)->patchJson($verify, $review)->assertConflict();
    $draft = $version->replicate();
    $draft->fill(['version' => 2, 'status' => 'draft'])->save();
    actingAs($owner)->patchJson($verify, $review)->assertNotFound();
    actingAs($owner)->get(route('admin.courtroom.preview', ['scenario' => $scenario, 'version' => 1]))
        ->assertOk()->assertDontSee('Manbani tasdiqlash');
});

it('runs the installed scenario through teacher approval student completion and teacher review', function () {
    config()->set('ai_tutor.api_key', null);
    Http::preventStrayRequests();
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $this->seed(SimulationScenarioSeeder::class);
    $scenario = SimulationScenario::query()->where('slug', 'shartnoma-majburiyati-boyicha-oquv-sudi')->sole();
    $version = $scenario->versions()->sole();
    actingAs($teacher)->patch(route('admin.courtroom.publish', $scenario))->assertSessionHasErrors('legal_reference');

    $evidence = $version->evidence()->firstOrFail();
    $reference = $version->legalReferences()->firstOrFail();
    actingAs($teacher)->put(route('admin.courtroom.update', $scenario), [
        'title' => $scenario->title, 'description' => $scenario->description,
        'legal_area' => $scenario->legal_area, 'case_type' => $scenario->case_type,
        'difficulty' => $scenario->difficulty, 'estimated_duration_minutes' => $scenario->estimated_duration_minutes,
        'procedure_type' => $version->procedure_type, 'case_brief' => $version->case_brief,
        'case_facts' => $version->case_facts, 'learning_objectives' => $version->learning_objectives,
        'evidence_title' => $evidence->title, 'evidence_summary' => $evidence->summary,
        'evidence_body' => $evidence->body, 'evidence_source_label' => $evidence->source_label,
        'legal_reference_title' => $reference->title, 'legal_reference_url' => $reference->source_url,
        'legal_reference_note' => $reference->citation_text, 'legal_reference_confirmed' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    actingAs($teacher)->patch(route('admin.courtroom.publish', $scenario))->assertRedirect()->assertSessionHasNoErrors();
    expect($version->fresh()->approved_by)->toBe($teacher->id);

    actingAs($student)->get(route('student.courtroom.scenarios.show', $scenario))->assertOk();
    actingAs($student)->post(route('student.courtroom.sessions.store'), ['scenario_id' => $scenario->id, 'role_key' => 'defense_attorney'])->assertRedirect();
    $session = SimulationSession::query()->sole();
    actingAs($student)->post(route('student.courtroom.sessions.start', $session))->assertRedirect();
    actingAs($student)->get(route('student.courtroom.sessions.show', $session))->assertOk()->assertSee('courtroom-canvas', false);
    foreach ($version->stages()->orderBy('sort_order')->get() as $stage) {
        if ($stage->is_terminal) {
            break;
        }
        if ($stage->key === 'EVIDENCE_PRESENTATION') {
            actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
                'action_type' => 'present_evidence', 'evidence_id' => $evidence->id,
                'content' => 'Yetkazib berish shartnomasini taqdim etaman.',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertStatus(202)->assertJsonPath('action.status', 'accepted');
        }
        if ($stage->key === 'WITNESS_EXAMINATION') {
            actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
                'action_type' => 'ask_question', 'content' => 'Qabul hujjati qanday rasmiylashtirilgan?',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertStatus(202)->assertJsonPath('action.status', 'accepted');
        }
        actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
            'action_type' => 'advance_stage', 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
    }
    actingAs($student)->post(route('student.courtroom.sessions.complete', $session))->assertRedirect(route('student.courtroom.sessions.report', $session));
    actingAs($student)->get(route('student.courtroom.sessions.report', $session))->assertOk();
    $evaluation = $session->fresh()->evaluation;
    actingAs($teacher)->get(route('admin.courtroom.reviews.show', $evaluation))->assertOk();
    actingAs($teacher)->post(route('admin.courtroom.reviews.store', $evaluation), [
        'decision' => 'approved', 'comment' => 'O‘quv jarayoni ko‘rib chiqildi.',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($evaluation->fresh()->reviewed_by)->toBe($teacher->id);
    actingAs($student)->post(route('student.courtroom.sessions.complete', $session))->assertRedirect();
    expect($evaluation->fresh()->status)->toBe('approved')
        ->and($evaluation->fresh()->reviewed_by)->toBe($teacher->id);
    Http::assertNothingSent();
});

it('limits teacher management lists and statistics to their own scenarios and sessions', function () {
    $teacher = User::factory()->teacher()->create();
    $other = User::factory()->teacher()->create();
    $own = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id, 'title' => 'Birinchi ustoz ssenariysi']);
    $foreign = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $other->id, 'title' => 'Ikkinchi ustoz ssenariysi']);
    foreach ([$own, $foreign] as $scenario) {
        SimulationSession::query()->create([
            'scenario_id' => $scenario->id, 'scenario_version_id' => $scenario->versions()->firstOrFail()->id,
            'student_id' => User::factory()->student()->create()->id,
            'selected_role_key' => 'judge', 'status' => 'active',
        ]);
    }

    actingAs($teacher)->get(route('admin.courtroom.index'))->assertOk()
        ->assertSee($own->title)->assertDontSee($foreign->title)
        ->assertViewHas('stats', fn (array $stats): bool => $stats['active_sessions'] === 1 && $stats['published_scenarios'] === 1);
    actingAs(User::factory()->academyAdmin()->create())->get(route('admin.courtroom.index'))->assertOk()
        ->assertSee($own->title)->assertSee($foreign->title)
        ->assertViewHas('stats', fn (array $stats): bool => $stats['active_sessions'] === 2 && $stats['published_scenarios'] === 2);
});

it('lets administrators manage verified local avatar assets and preserves their choice after seeding', function () {
    $this->seed(CourtroomAssetSeeder::class);
    $asset = SimulationAsset::query()->where('slug', 'rocketbox-male')->sole();
    $admin = User::factory()->academyAdmin()->create();
    $teacher = User::factory()->teacher()->create();
    $session = SimulationSession::factory()->active()->create();
    $session->voiceSessions()->create(['student_id' => $session->student_id, 'provider' => 'browser', 'status' => 'active']);
    $session->voiceSessions()->create(['student_id' => $session->student_id, 'provider' => 'openai', 'status' => 'ended'])
        ->forceFill(['created_at' => now()->subDays(31)])->save();
    $session->turns()->create([
        'sequence' => 1, 'speaker_role_key' => 'judge', 'speaker_type' => 'ai', 'input_type' => 'text',
        'content' => 'Sinovdagi javob', 'metadata' => ['total_tokens' => 42, 'duration_ms' => 150],
    ]);

    actingAs($teacher)->get(route('admin.courtroom.assets.index'))->assertForbidden();
    actingAs($teacher)->patchJson(route('admin.courtroom.assets.update', $asset), ['enabled' => false])->assertForbidden();
    actingAs($admin)->get(route('admin.courtroom.assets.index'))->assertOk()
        ->assertSee($asset->name)->assertSee($asset->checksum)
        ->assertViewHas('health', fn (array $health): bool => $health['total_tokens'] === 42
            && $health['token_reported_responses'] === 1 && $health['browser_voice_sessions'] === 1 && $health['server_voice_sessions'] === 0);
    actingAs($admin)->patch(route('admin.courtroom.assets.update', $asset), ['enabled' => false])->assertRedirect();

    $this->seed(CourtroomAssetSeeder::class);
    expect($asset->fresh()->enabled)->toBeFalse();

    $asset->update(['checksum' => str_repeat('0', 64)]);
    actingAs($admin)->patchJson(route('admin.courtroom.assets.update', $asset), ['enabled' => true])->assertUnprocessable();
    expect($asset->fresh()->enabled)->toBeFalse();
});
