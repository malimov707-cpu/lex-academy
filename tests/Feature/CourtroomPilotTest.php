<?php

use App\Enums\UserRole;
use App\Models\EvaluationResult;
use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\SimulationEvaluator;

use function Pest\Laravel\actingAs;

it('limits the pilot selector to scenarios the reviewer manages', function () {
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['title' => 'Ijara bo‘yicha sinov darsi']);
    $teacher = $scenario->creator;
    $foreign = SimulationScenario::factory()->courtroomReady()->create(['title' => 'Boshqa ustozning darsi']);
    $foreignUrl = route('admin.courtroom.pilot', ['scenario' => $foreign->slug]);

    actingAs($teacher)->get(route('admin.courtroom.index'))->assertOk()->assertSee(route('admin.courtroom.pilot'));
    actingAs($teacher)->get(route('admin.courtroom.pilot'))->assertOk()->assertSeeText($scenario->title)->assertDontSeeText($foreign->title);
    actingAs($teacher)->get($foreignUrl)->assertNotFound();
    actingAs(User::factory()->academyAdmin()->create())->get($foreignUrl)->assertOk()->assertSeeText($foreign->title);
});

it('requires staff access to read pilot results', function (UserRole $role) {
    actingAs(User::factory()->create(['role' => $role]))->get(route('admin.courtroom.pilot'))->assertForbidden();
})->with([UserRole::Student, UserRole::Mentor]);

it('requires a signed-in reviewer to open the pilot', function () {
    $this->get(route('admin.courtroom.pilot'))->assertRedirect(route('login'));
});

it('rejects unpublished versions and roles that a student cannot select', function () {
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $draft = ScenarioVersion::factory()->create(['scenario_id' => $scenario->id, 'version' => 2]);
    $filters = ['scenario' => $scenario->slug];
    actingAs($scenario->creator);

    $this->get(route('admin.courtroom.pilot', [...$filters, 'version' => $draft->version]))->assertNotFound();
    $this->get(route('admin.courtroom.pilot', [...$filters, 'role' => 'witness']))->assertNotFound();
});

it('measures first-to-last changes from explicitly reviewed criteria in comparable attempts', function () {
    $scenario = SimulationScenario::factory()->courtroomReady()->create();
    $teacher = $scenario->creator;
    $version = $scenario->versions()->sole();
    $evidence = $version->rubric->criteria()->where('key', 'evidence_analysis')->sole();
    $reasoning = $version->rubric->criteria()->create([
        'key' => 'legal_reasoning', 'name' => 'Huquqiy asoslash', 'description' => 'Pozitsiyani asoslash',
        'weight' => 15, 'max_score' => 15,
    ]);
    $makeAttempt = function (User $student, array $attributes = []) use ($version, $scenario, $evidence, $reasoning): EvaluationResult {
        $attempt = SimulationSession::factory()->create([
            'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id,
            'student_id' => $student->id, 'selected_role_key' => 'judge',
            'status' => 'completed', 'completed_at' => '2026-01-10 10:00:00',
            'settings' => ['scoring_version' => SimulationEvaluator::SCORING_VERSION], ...$attributes,
        ]);
        $evaluation = $attempt->evaluation()->create(['overall_score' => 60, 'generated_at' => now()]);
        foreach ([$evidence, $reasoning] as $criterion) {
            $evaluation->criterionResults()->create([
                'evaluation_criterion_id' => $criterion->id, 'score' => 3, 'rationale' => 'Dastlabki baho.',
            ]);
        }

        return $evaluation;
    };
    $mark = function (EvaluationResult $evaluation, array $marks) use ($teacher): void {
        actingAs($teacher)->post(route('admin.courtroom.reviews.store', $evaluation), [
            'decision' => 'approved', 'comment' => 'Dalillarni bog‘lash tekshirildi.',
            'criterion_overrides' => $marks,
        ])->assertSessionHasNoErrors()->assertRedirect();
    };
    $student = User::factory()->student()->create();
    $partialStudent = User::factory()->student()->create();
    $legacyStudent = User::factory()->student()->create();
    $anotherRole = User::factory()->student()->create();
    $oldMethod = $makeAttempt($student, ['settings' => []]);
    $mark($oldMethod, [$evidence->id => 22, $reasoning->id => 14]);
    $first = $makeAttempt($student);
    $mark($first, [$evidence->id => 0, $reasoning->id => 10]);
    $middle = $makeAttempt($student);
    $mark($middle, [$evidence->id => 20, $reasoning->id => 8]);
    $last = $makeAttempt($student);
    $mark($last, [$evidence->id => 5, $reasoning->id => 6]);
    $makeAttempt($student, ['completed_at' => null, 'status' => 'active']);
    $makeAttempt($anotherRole, ['selected_role_key' => 'defense_attorney']);
    $partialFirst = $makeAttempt($partialStudent);
    $mark($partialFirst, [$evidence->id => 12, $reasoning->id => 10]);
    $partialLast = $makeAttempt($partialStudent);
    $mark($partialLast, [$evidence->id => 9, $reasoning->id => 11]);
    $mark($partialLast, [$evidence->id => 9, $reasoning->id => '']);
    $legacyFirst = $makeAttempt($legacyStudent);
    $legacyFirst->reviews()->create([
        'reviewer_id' => $teacher->id, 'decision' => 'approved', 'comment' => 'Eski hisobot.',
        'criterion_overrides' => [$evidence->id => 3, $reasoning->id => 3],
    ]);
    $legacyLast = $makeAttempt($legacyStudent);
    $mark($legacyLast, [$evidence->id => 25, $reasoning->id => 15]);

    $response = actingAs($teacher)->get(route('admin.courtroom.pilot', [
        'scenario' => $scenario->slug, 'version' => $version->version, 'role' => 'judge',
    ]))->assertOk()->assertSeeText($student->name)
        ->assertSee(route('admin.courtroom.reviews.show', $first))->assertSee(route('admin.courtroom.reviews.show', $last))
        ->assertDontSeeText($anotherRole->name);

    $report = $response->viewData('report');
    $pending = collect($report['pending_pairs'])->keyBy('first_evaluation_id');
    expect($pending->get($legacyFirst->id)['missing_criteria'])->toContain('legal_reasoning', 'evidence_analysis')
        ->and($pending->get($partialFirst->id)['missing_criteria'])->toBe(['legal_reasoning']);

    expect($report['completed_students'])->toBe(3)
        ->and($report['repeat_students'])->toBe(3)
        ->and($report['criteria']['evidence_analysis']['count'])->toBe(2)
        ->and((float) $report['criteria']['evidence_analysis']['average_delta'])->toBe(1.0)
        ->and($report['criteria']['legal_reasoning']['count'])->toBe(1)
        ->and((float) $report['criteria']['legal_reasoning']['average_delta'])->toBe(-4.0);
});
