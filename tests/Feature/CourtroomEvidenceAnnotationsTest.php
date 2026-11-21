<?php

use App\Models\CourtroomEvidenceAnnotation;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\SimulationEvaluator;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

it('persists Unicode highlights and private notes and shows them only to the session owner', function () {
    $student = User::factory()->student()->create();
    $teacher = User::factory()->teacher()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $version = $scenario->versions()->sole();
    $evidence = $version->evidence()->sole();
    $evidence->update(['body' => "⚖️\r\nDalil matni"]);
    $session = SimulationSession::factory()->active()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id, 'student_id' => $student->id,
    ]);
    $annotations = route('student.courtroom.evidence.annotations', [$session, $evidence]);
    $note = ['note' => 'Shaxsiy sud eslatmasi', 'start_offset' => 3, 'end_offset' => 8, 'idempotency_key' => (string) Str::uuid()];

    actingAs($student)->postJson($annotations, [...$note, 'end_offset' => 500])->assertUnprocessable()->assertJsonValidationErrors('start_offset');
    actingAs($student)->postJson($annotations, ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('note');
    $response = actingAs($student)->postJson($annotations, $note)->assertCreated()->assertJsonPath('annotation.note', $note['note']);
    $id = $response->json('annotation.id');
    actingAs($student)->postJson($annotations, $note)->assertOk()->assertJsonPath('annotation.id', $id);
    actingAs($student)->getJson($annotations)->assertOk()->assertJsonCount(1, 'annotations')->assertHeader('Cache-Control', 'no-store, private');
    expect(CourtroomEvidenceAnnotation::query()->findOrFail($id)->excerptFrom($evidence))->toBe('Dalil');

    $foreignStudent = User::factory()->student()->create();
    actingAs($foreignStudent)->getJson($annotations)->assertForbidden();
    actingAs($foreignStudent)->deleteJson($annotations.'/'.$id)->assertForbidden();
    $otherSession = SimulationSession::factory()->active()->create([
        'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id, 'student_id' => $student->id,
    ]);
    $otherNotes = route('student.courtroom.evidence.annotations', [$otherSession, $evidence]);
    actingAs($student)->deleteJson($otherNotes.'/'.$id)->assertNotFound();
    $unrelated = CourtroomEvidenceAnnotation::factory()->create();
    actingAs($student)->getJson(route('student.courtroom.evidence.annotations', [$session, $unrelated->evidence]))->assertNotFound();

    $restricted = $version->evidence()->create([
        'title' => 'Maxfiy', 'type' => 'document', 'summary' => 'Rolga tegishli', 'source_label' => 'Sinov',
        'visibility' => 'roles', 'allowed_role_keys' => ['judge'], 'status' => 'active',
    ]);
    actingAs($student)->postJson(route('student.courtroom.evidence.annotations', [$session, $restricted]), $note)->assertNotFound();
    $session->update(['status' => 'completed', 'completed_at' => now(), 'elapsed_seconds' => 60]);
    $evaluation = app(SimulationEvaluator::class)->generate($session);
    $report = route('student.courtroom.sessions.report', $session);
    actingAs($student)->get($report)->assertOk()->assertSee($note['note'])->assertSee('<q>Dalil</q>', false);
    actingAs($student)->postJson($annotations, [...$note, 'idempotency_key' => (string) Str::uuid()])->assertConflict();
    actingAs($teacher)->get(route('admin.courtroom.reviews.show', $evaluation))->assertOk()->assertDontSee($note['note']);
    actingAs($student)->delete($annotations.'/'.$id)->assertRedirect($report);
    actingAs($student)->get($report)->assertOk()->assertDontSee($note['note']);
});
