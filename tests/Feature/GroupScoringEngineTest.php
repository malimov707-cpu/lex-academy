<?php

use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\GroupAssessmentRubric;
use App\Services\VirtualCourtroom\GroupScoringEngine;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAcademySeeder;

use function Pest\Laravel\actingAs;

it('handles team scoring when team key is present in snapshot', function () {
    $rubric = app(GroupAssessmentRubric::class);
    $snapshot = $rubric->snapshot(['tie_threshold' => 2]);
    $engine = app(GroupScoringEngine::class);

    $rows = [
        [
            'student_id' => 1,
            'role_key' => 'prosecutor',
            'status' => 'approved',
            'criteria' => collect($snapshot['roles']['prosecutor'])->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => collect($snapshot['roles']['prosecutor'])->mapWithKeys(fn ($c, $k) => [$k => ['DOC-1']])->all(),
        ],
        [
            'student_id' => 2,
            'role_key' => 'defense_advocate',
            'status' => 'approved',
            'criteria' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => ['DOC-2']])->all(),
        ],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('CALCULATED')
        ->and($outcome['winner'])->toBe('tie')
        ->and($outcome['winner_source'])->toBe('calculated')
        ->and($outcome['prosecution_score'])->toBe(100.0)
        ->and($outcome['defense_score'])->toBe(100.0)
        ->and($outcome['teams']['prosecution']['status'])->toBe('CALCULATED')
        ->and($outcome['teams']['defense']['status'])->toBe('CALCULATED');
});

it('handles team scoring when team key is missing without throwing exceptions', function () {
    $engine = app(GroupScoringEngine::class);

    // Snapshot missing 'team' and 'roles'
    $legacySnapshot = ['version' => '1.0', 'scale' => 100];
    $rows = [];

    $outcome = $engine->outcome($legacySnapshot, $rows);

    expect($outcome)->toBeArray()
        ->and($outcome['score_status'])->toBe('NOT_CALCULATED')
        ->and($outcome['winner'])->toBe('undetermined')
        ->and($outcome['winner_source'])->toBe('undetermined')
        ->and($outcome['prosecution_score'])->toBeNull()
        ->and($outcome['defense_score'])->toBeNull()
        ->and($outcome['teams']['prosecution']['score'])->toBeNull()
        ->and($outcome['teams']['defense']['score'])->toBeNull();
});

it('handles scenario when prosecution score is missing', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    $rows = [
        [
            'student_id' => 2,
            'role_key' => 'defense_advocate',
            'status' => 'approved',
            'criteria' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => ['DOC-2']])->all(),
        ],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('INSUFFICIENT_DATA')
        ->and($outcome['prosecution_score'])->toBeNull()
        ->and($outcome['defense_score'])->toBe(100.0)
        ->and($outcome['winner'])->toBe('undetermined')
        ->and($outcome['teams']['prosecution']['status'])->toBe('NOT_CALCULATED');
});

it('handles scenario when defense score is missing', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    $rows = [
        [
            'student_id' => 1,
            'role_key' => 'prosecutor',
            'status' => 'approved',
            'criteria' => collect($snapshot['roles']['prosecutor'])->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => collect($snapshot['roles']['prosecutor'])->mapWithKeys(fn ($c, $k) => [$k => ['DOC-1']])->all(),
        ],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('INSUFFICIENT_DATA')
        ->and($outcome['defense_score'])->toBeNull()
        ->and($outcome['prosecution_score'])->toBe(100.0)
        ->and($outcome['winner'])->toBe('undetermined')
        ->and($outcome['teams']['defense']['status'])->toBe('NOT_CALCULATED');
});

it('calculates clear winner when individual advocate grades are complete with distinct scores', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot(['tie_threshold' => 2]);
    $engine = app(GroupScoringEngine::class);

    $rows = [
        [
            'student_id' => 1,
            'role_key' => 'prosecutor',
            'status' => 'approved',
            'criteria' => collect($snapshot['roles']['prosecutor'])->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => collect($snapshot['roles']['prosecutor'])->mapWithKeys(fn ($c, $k) => [$k => ['DOC-1']])->all(),
        ],
        [
            'student_id' => 2,
            'role_key' => 'defense_advocate',
            'status' => 'approved',
            // Half scores for defense
            'criteria' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => round($c['max'] * 0.6, 2)])->all(),
            'evidence' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => ['DOC-2']])->all(),
        ],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('CALCULATED')
        ->and($outcome['winner'])->toBe('prosecution')
        ->and($outcome['winner_source'])->toBe('calculated')
        ->and($outcome['prosecution_score'])->toBe(100.0)
        ->and($outcome['defense_score'])->toBe(60.0);
});

it('sets score status to INSUFFICIENT_DATA when individual grades are partial or lack evidence', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    $rows = [
        [
            'student_id' => 1,
            'role_key' => 'prosecutor',
            'status' => 'approved',
            // Missing some criteria
            'criteria' => ['legal' => 15],
            'evidence' => ['legal' => ['DOC-1']],
        ],
        [
            'student_id' => 2,
            'role_key' => 'defense_advocate',
            'status' => 'approved',
            // Complete criteria but empty evidence
            'criteria' => collect($snapshot['roles']['defense_advocate'])->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => [],
        ],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('INSUFFICIENT_DATA')
        ->and($outcome['prosecution_score'])->toBeNull()
        ->and($outcome['defense_score'])->toBeNull()
        ->and($outcome['winner'])->toBe('undetermined');
});

it('correctly isolates neutral roles from team scores when all 9 roles are present', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    $roles = [
        'judge', 'prosecutor', 'victim_advocate', 'victim',
        'defense_advocate', 'defendant', 'witness_1', 'witness_2', 'witness_3',
    ];

    $rows = [];
    foreach ($roles as $idx => $roleKey) {
        $rubricRole = in_array($roleKey, ['witness_1', 'witness_2', 'witness_3'], true) ? 'witness' : $roleKey;
        $roleCriteria = $snapshot['roles'][$rubricRole] ?? [];

        $rows[] = [
            'student_id' => $idx + 10,
            'role_key' => $roleKey,
            'status' => 'approved',
            'criteria' => collect($roleCriteria)->mapWithKeys(fn ($c, $k) => [$k => $c['max']])->all(),
            'evidence' => collect($roleCriteria)->mapWithKeys(fn ($c, $k) => [$k => ['REF-'.$roleKey]])->all(),
        ];
    }

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('CALCULATED')
        ->and($outcome['teams']['prosecution']['advocates_count'])->toBe(2) // prosecutor + victim_advocate
        ->and($outcome['teams']['defense']['advocates_count'])->toBe(1) // defense_advocate
        ->and($outcome['teams']['prosecution']['party_role'])->toBe('victim')
        ->and($outcome['teams']['defense']['party_role'])->toBe('defendant')
        ->and($outcome['winner'])->toBe('tie');
});

it('handles session when one or more active roles are missing', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    // Session only has judge and witnesses (no advocates)
    $rows = [
        ['student_id' => 1, 'role_key' => 'judge', 'status' => 'approved', 'criteria' => [], 'evidence' => []],
        ['student_id' => 2, 'role_key' => 'witness_1', 'status' => 'approved', 'criteria' => [], 'evidence' => []],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('NOT_CALCULATED')
        ->and($outcome['prosecution_score'])->toBeNull()
        ->and($outcome['defense_score'])->toBeNull()
        ->and($outcome['winner'])->toBe('undetermined');
});

it('preserves verdict and reports INSUFFICIENT_DATA for demo session with partial criteria', function () {
    $engine = app(GroupScoringEngine::class);

    // Minimal snapshot from demo session
    $snapshot = ['version' => '1.0', 'scale' => 100];
    $rows = [
        [
            'student_id' => 8,
            'role_key' => 'prosecutor',
            'status' => 'approved',
            'criteria' => ['procedural_compliance' => 90, 'legal_argumentation' => 86],
            'evidence' => ['presented' => 2, 'valid' => 2],
        ],
        [
            'student_id' => 11,
            'role_key' => 'defense_advocate',
            'status' => 'approved',
            'criteria' => ['procedural_compliance' => 90, 'legal_argumentation' => 86],
            'evidence' => ['presented' => 2, 'valid' => 2],
        ],
    ];

    $context = [
        'saved_winner' => 'defense',
        'saved_winner_label' => "Himoya tomoni g'alaba qozondi (Da'vo dalillari yetarli emas deb topildi)",
        'is_demo' => true,
    ];

    $outcome = $engine->outcome($snapshot, $rows, $context);

    expect($outcome['winner'])->toBe('defense')
        ->and($outcome['winner_source'])->toBe('preserved_verdict')
        ->and($outcome['score_status'])->toBe('INSUFFICIENT_DATA')
        ->and($outcome['prosecution_score'])->toBeNull()
        ->and($outcome['defense_score'])->toBeNull()
        ->and($outcome['reason'])->toContain("Himoya tomoni g'alaba qozondi");
});

it('gracefully handles legacy format completed session', function () {
    $engine = app(GroupScoringEngine::class);

    // Old format session with minimal rubric and no context
    $snapshot = ['version' => 'legacy-v0'];
    $rows = [];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['winner'])->toBe('undetermined')
        ->and($outcome['winner_source'])->toBe('undetermined')
        ->and($outcome['score_status'])->toBe('NOT_CALCULATED')
        ->and($outcome['teams']['prosecution']['criteria'])->toBeArray()
        ->and($outcome['teams']['defense']['criteria'])->toBeArray();
});

it('displays preserved winner when score breakdown is not yet available', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    $context = [
        'saved_winner' => 'prosecution',
        'saved_winner_label' => 'Ayblov tomoni dastlabki xulosaga ko‘ra g‘olib deb topildi.',
    ];

    $outcome = $engine->outcome($snapshot, [], $context);

    expect($outcome['winner'])->toBe('prosecution')
        ->and($outcome['winner_source'])->toBe('preserved_verdict')
        ->and($outcome['score_status'])->toBe('NOT_CALCULATED')
        ->and($outcome['prosecution_score'])->toBeNull()
        ->and($outcome['defense_score'])->toBeNull()
        ->and($outcome['reason'])->toBe('Ayblov tomoni dastlabki xulosaga ko‘ra g‘olib deb topildi.');
});

it('correctly handles incomplete evaluation state', function () {
    $snapshot = app(GroupAssessmentRubric::class)->snapshot();
    $engine = app(GroupScoringEngine::class);

    // Roster has advocates but none are teacher_reviewed or approved
    $rows = [
        ['student_id' => 1, 'role_key' => 'prosecutor', 'status' => 'pending', 'criteria' => ['legal' => 10], 'evidence' => ['legal' => ['DOC-1']]],
        ['student_id' => 2, 'role_key' => 'defense_advocate', 'status' => 'pending', 'criteria' => ['legal' => 10], 'evidence' => ['legal' => ['DOC-2']]],
    ];

    $outcome = $engine->outcome($snapshot, $rows);

    expect($outcome['score_status'])->toBe('INSUFFICIENT_DATA')
        ->and($outcome['winner'])->toBe('undetermined')
        ->and($outcome['teams']['prosecution']['score'])->toBeNull()
        ->and($outcome['teams']['defense']['score'])->toBeNull();
});

it('renders HTTP 200 and meaningful content for real database session 14 results page', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoAcademySeeder::class);

    $session = SimulationSession::query()->where('group_creation_key', 'demo-completed-courtroom-session-30')->first();
    expect($session)->not->toBeNull();

    $teacher = User::find($session->facilitator_id);
    expect($teacher)->not->toBeNull();

    actingAs($teacher)
        ->get("/virtual-courtroom/sessions/{$session->id}/results")
        ->assertOk()
        ->assertSee('Virtual sud natijalari')
        ->assertSee('Himoya tomoni g‘olib')
        ->assertSee("Himoya tomoni g'alaba qozondi (Da'vo dalillari yetarli emas deb topildi)")
        ->assertSee('Ayblov tomoni')
        ->assertSee('Himoya tomoni');

    // Also verify as a student participant
    $prosecutorParticipant = $session->participants()->where('role_key', 'prosecutor')->first();
    expect($prosecutorParticipant)->not->toBeNull();
    $student = User::find($prosecutorParticipant->user_id);
    expect($student)->not->toBeNull();

    actingAs($student)
        ->get("/virtual-courtroom/sessions/{$session->id}/results")
        ->assertOk()
        ->assertSee('Virtual sud natijalari')
        ->assertSee('Himoya tomoni g‘olib');
});
