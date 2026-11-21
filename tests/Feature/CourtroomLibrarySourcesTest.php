<?php

use App\Models\Course;
use App\Models\LegalSource;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Database\Seeders\SimulationScenarioSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

it('copies only a verified general source to a draft and makes it available for AI after scenario review', function () {
    $teacher = User::factory()->teacher()->create();
    $administrator = User::factory()->academyAdmin()->create();
    $student = User::factory()->student()->create();
    $this->seed(SimulationScenarioSeeder::class);
    $scenario = SimulationScenario::query()->where('slug', 'shartnoma-majburiyati-boyicha-oquv-sudi')->sole();
    $library = route('admin.courtroom.references.library', $scenario);
    $source = LegalSource::factory()->create([
        'title' => 'Umumiy normativ manba', 'url' => 'https://lex.uz/docs/test-example',
        'excerpt' => 'Huquqiy kutubxona sinovi uchun parcha.', 'status' => 'verified',
        'verified_by' => $administrator->id, 'verified_at' => now()->subDay(),
    ]);
    $restricted = LegalSource::factory()->create([
        'title' => 'Faqat kursga tegishli manba', 'course_id' => Course::factory()->create()->id,
        'status' => 'verified', 'verified_by' => $administrator->id, 'verified_at' => now(),
    ]);
    $draftSource = LegalSource::factory()->create(['title' => 'Tasdiqlanmagan manba']);
    $attach = route('admin.courtroom.references.attach', [$scenario, $source]);
    $preview = route('admin.courtroom.preview', $scenario);

    actingAs(User::factory()->teacher()->create())->post($attach)->assertForbidden();
    actingAs($teacher)->get($library)->assertOk()->assertSee($source->title)->assertDontSee($restricted->title)->assertDontSee($draftSource->title);
    actingAs($teacher)->get($library.'?q=mos-kelmaydigan')->assertOk()->assertDontSee($source->title);
    actingAs($teacher)->post(route('admin.courtroom.references.attach', [$scenario, $restricted]))->assertNotFound();
    actingAs($teacher)->post(route('admin.courtroom.references.attach', [$scenario, $draftSource]))->assertNotFound();
    actingAs($teacher)->post($attach)->assertRedirect($preview)->assertSessionHasNoErrors();
    actingAs($teacher)->post($attach)->assertRedirect($preview);

    $version = $scenario->versions()->sole();
    $reference = $version->legalReferences()->where('legal_source_id', $source->id)->sole();
    expect($reference->library_excerpt)->toBe($source->excerpt)->and($reference->verified_at)->toBeNull();
    actingAs($teacher)->patch(route('admin.courtroom.publish', $scenario))->assertSessionHasErrors('legal_reference');
    actingAs($teacher)->patch(route('admin.courtroom.references.verify', [$scenario, $reference]), [
        'confirmed' => true, 'citation_text' => 'Sinov tahriri bo‘yicha tekshiruv.',
    ])->assertRedirect($preview)->assertSessionHasNoErrors();
    actingAs($teacher)->patch(route('admin.courtroom.publish', $scenario))->assertRedirect()->assertSessionHasNoErrors();
    actingAs($student)->get(route('student.courtroom.scenarios.show', $scenario))->assertOk()->assertSee($source->title);

    config()->set(['ai_tutor.api_key' => 'test-key', 'ai_tutor.base_url' => 'https://library-ai.example.test/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://library-ai.example.test/v1/chat/completions' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => 'Ssenariy manbasi REF-'.$reference->id.'.']]]])
        ->push(['choices' => [['message' => ['content' => 'Ssenariy faktlarini muhokama qilamiz.']]]]),
    ]);
    $session = SimulationSession::factory()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id, 'student_id' => $student->id,
        'selected_role_key' => 'defense_attorney', 'status' => 'created',
    ]);
    actingAs($student)->postJson(route('student.courtroom.sessions.start', $session))->assertOk();
    $action = ['action_type' => 'make_statement', 'content' => 'Ish bo‘yicha fikrimni bayon qilaman.'];
    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        ...$action, 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();
    Http::assertSent(function (Request $request) use ($source): bool {
        $context = json_decode($request->data()['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return $context['LEGAL_REFERENCES'][0]['library_excerpt'] === $source->excerpt;
    });

    $source->update(['status' => 'archived']);
    actingAs($student)->get(route('student.courtroom.scenarios.show', $scenario))->assertOk()->assertDontSee($source->title);
    actingAs($student)->postJson(route('student.courtroom.sessions.actions', $session), [
        ...$action, 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();
    Http::assertSent(function (Request $request): bool {
        $context = json_decode($request->data()['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return $context['LEGAL_REFERENCES'] === [] && ! str_contains(json_encode($context['RECENT_TURNS']), 'REF-');
    });

    $source->update(['status' => 'verified', 'verified_at' => now(), 'excerpt' => 'Yangilangan kutubxona parchasi.']);
    actingAs($student)->get(route('student.courtroom.scenarios.show', $scenario))->assertOk()->assertDontSee($source->title);
    actingAs($teacher)->post($attach)->assertRedirect($preview);
    $newDraft = $scenario->versions()->where('status', 'draft')->sole();
    $newReference = $newDraft->legalReferences()->where('legal_source_id', $source->id)->sole();
    expect($newReference->verified_at)->toBeNull()
        ->and($newReference->library_excerpt)->toBe($source->excerpt)
        ->and($reference->fresh()->library_excerpt)->toBe('Huquqiy kutubxona sinovi uchun parcha.');
});
