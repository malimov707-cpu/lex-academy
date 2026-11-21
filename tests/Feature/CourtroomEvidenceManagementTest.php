<?php

use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('uploads private evidence into a draft while preserving published evidence', function () {
    Storage::fake('local');
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $source = $scenario->versions()->sole();
    $sourceCount = $source->evidence()->count();
    actingAs($teacher)->post(route('admin.courtroom.evidence.store', $scenario), [
        'title' => 'O‘quv PDF', 'summary' => 'Tekshiruv uchun hujjat.', 'source_label' => 'O‘qituvchi tayyorlagan material',
        'visibility' => 'roles', 'allowed_role_keys' => ['judge'], 'educational_use_confirmed' => true,
        'file' => UploadedFile::fake()->createWithContent('material.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $draft = $scenario->versions()->latest('version')->firstOrFail();
    $evidence = $draft->evidence()->where('title', 'O‘quv PDF')->sole();
    expect($draft->status)->toBe('draft')->and($draft->id)->not->toBe($source->id)
        ->and($source->evidence()->count())->toBe($sourceCount)
        ->and($evidence->allowed_role_keys)->toBe(['judge']);
    Storage::disk('local')->assertExists($evidence->storage_path);
    actingAs($teacher)->get(route('admin.courtroom.evidence.show', [$scenario, $evidence]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    actingAs(User::factory()->teacher()->create())->get(route('admin.courtroom.evidence.show', [$scenario, $evidence]))->assertForbidden();
    actingAs($teacher)->get(route('admin.courtroom.evidence.index', $scenario))->assertOk()->assertSee('O‘quv PDF');
    $student = User::factory()->student()->create();
    $session = SimulationSession::factory()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $draft->id,
        'student_id' => $student->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'current_stage_key' => 'PREPARATION',
    ]);
    $evidenceUrl = route('student.courtroom.evidence.show', [$session, $evidence]);
    actingAs($student)->get(route('student.courtroom.sessions.show', $session))
        ->assertOk()->assertSee($evidenceUrl);
    actingAs($student)->get($evidenceUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $session->update(['selected_role_key' => 'defense_attorney']);
    actingAs($student)->get($evidenceUrl)->assertNotFound();
    actingAs($student)->get(route('student.courtroom.sessions.show', $session))
        ->assertOk()->assertDontSee('O‘quv PDF');
});

it('rejects executable file extensions and unknown evidence roles', function () {
    Storage::fake('local');
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    actingAs($teacher)->postJson(route('admin.courtroom.evidence.store', $scenario), [
        'title' => 'Dalil', 'summary' => 'Tavsif', 'source_label' => 'Manba', 'visibility' => 'all', 'educational_use_confirmed' => true,
        'file' => UploadedFile::fake()->createWithContent('document.php', '%PDF-1.4 fake executable extension'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
    actingAs($teacher)->postJson(route('admin.courtroom.evidence.store', $scenario), [
        'title' => 'Dalil', 'summary' => 'Tavsif', 'body' => 'Matn', 'source_label' => 'Manba',
        'visibility' => 'roles', 'allowed_role_keys' => ['foreign_role'], 'educational_use_confirmed' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('allowed_role_keys');
    expect($scenario->versions()->count())->toBe(1);
    Storage::disk('local')->assertDirectoryEmpty('courtroom-evidence');
});

it('archives evidence only in a new draft and keeps the published version available', function () {
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $source = $scenario->versions()->sole();
    $evidence = $source->evidence()->firstOrFail();
    actingAs($teacher)->delete(route('admin.courtroom.evidence.destroy', [$scenario, $evidence]))->assertRedirect();
    expect($evidence->fresh()->status)->toBe('active')
        ->and($scenario->versions()->latest('version')->firstOrFail()->evidence()->where('sort_order', $evidence->sort_order)->sole()->status)->toBe('archived');
});

it('duplicates scenario content as a separate unapproved draft and displays version history', function () {
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $source = $scenario->versions()->sole();
    actingAs($teacher)->post(route('admin.courtroom.duplicate', $scenario))->assertRedirect();
    $copy = SimulationScenario::query()->where('id', '!=', $scenario->id)->sole();
    $version = $copy->versions()->sole();
    expect($copy->created_by)->toBe($teacher->id)->and($copy->status)->toBe('draft')
        ->and($version->approved_at)->toBeNull()->and($version->case_facts)->toBe($source->case_facts)
        ->and($version->roles()->count())->toBe($source->roles()->count())
        ->and($version->evidence()->count())->toBe($source->evidence()->count());
    actingAs($teacher)->get(route('admin.courtroom.history', $scenario))->assertOk()->assertSee('Versiyalar tarixi');
    actingAs($teacher)->get(route('admin.courtroom.preview', ['scenario' => $scenario, 'version' => 1]))->assertOk();
    actingAs(User::factory()->teacher()->create())->post(route('admin.courtroom.duplicate', $scenario))->assertForbidden();
});
