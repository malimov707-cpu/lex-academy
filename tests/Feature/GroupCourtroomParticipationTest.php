<?php

use App\Models\Course;
use App\Models\EducationGroup;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

it('shows published scenarios in the separate student group courtroom section', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create([
        'created_by' => $teacher->id,
        'title' => 'Guruh uchun jinoiy o‘quv ishi',
    ]);
    $scenario->versions()->sole()->update(['status' => 'published']);

    actingAs($student)
        ->get(route('courtroom.groups.index'))
        ->assertOk()
        ->assertSee('Guruhli sud mashg‘ulotlari')
        ->assertSee('Guruhli sud ssenariylari')
        ->assertSee('Guruh uchun jinoiy o‘quv ishi')
        ->assertSee('O‘qituvchi guruh mashg‘ulotini tayinlagach');
});

it('renders the same 3D courtroom shell for an active group session', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id]);
    $group->members()->attach($student);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $scenario->versions()->sole()->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'active',
        'started_at' => now(),
        'current_stage_key' => 'PREPARATION',
        'settings' => [],
    ]);
    $session->participants()->create([
        'user_id' => $student->id,
        'role_key' => 'judge',
        'actor_type' => 'student',
        'display_name' => $student->name,
        'status' => 'present',
        'metadata' => ['attended_at' => now()->toIso8601String()],
    ]);

    actingAs($student)
        ->get(route('courtroom.groups.show', $session))
        ->assertOk()
        ->assertSee('courtroom-canvas', false)
        ->assertSee('GURUHLI VIRTUAL SUD')
        ->assertSee($student->name)
        ->assertSee('data-avatar-male', false)
        ->assertSee('data-camera="wide"', false)
        ->assertSee('data-scene-quality', false);
});

it('supports teacher pause, resume, and evidence presentation in group courtroom', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id]);
    $group->members()->attach($student);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $version = $scenario->versions()->sole();
    $evidence = $version->evidence()->create([
        'title' => 'Shartnoma nusxasi',
        'type' => 'document',
        'summary' => 'Yetkazib berish shartnomasi',
        'body' => 'Tomonlar o‘rtasidagi shartnoma matni',
        'source_label' => 'O‘quv hujjati',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'active',
        'started_at' => now(),
        'current_stage_key' => 'PREPARATION',
        'settings' => [],
    ]);
    $session->participants()->create([
        'user_id' => $student->id,
        'role_key' => 'judge',
        'actor_type' => 'student',
        'display_name' => $student->name,
        'status' => 'present',
    ]);

    // Teacher pauses session
    actingAs($teacher)
        ->post(route('courtroom.groups.pause', $session))
        ->assertRedirect(route('courtroom.groups.show', $session));

    expect($session->fresh()->status)->toBe('paused');

    // Teacher resumes session
    actingAs($teacher)
        ->post(route('courtroom.groups.resume', $session))
        ->assertRedirect(route('courtroom.groups.show', $session));

    expect($session->fresh()->status)->toBe('active');

    // Teacher presents evidence to 3D board
    actingAs($teacher)
        ->post(route('courtroom.groups.evidence', $session), ['evidence_id' => $evidence->id])
        ->assertRedirect(route('courtroom.groups.show', $session));

    expect(data_get($session->fresh()->settings, 'board_evidence_id'))->toBe($evidence->id);
});

it('synchronizes real-time feed with active speaker, stage, and presented evidence', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id]);
    $group->members()->attach($student);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $version = $scenario->versions()->sole();

    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'active',
        'started_at' => now(),
        'current_stage_key' => 'PREPARATION',
        'settings' => ['board_evidence_id' => 42],
    ]);
    $session->participants()->create([
        'user_id' => $student->id,
        'role_key' => 'judge',
        'actor_type' => 'student',
        'display_name' => $student->name,
        'status' => 'present',
    ]);
    $session->turns()->create([
        'sequence' => 1,
        'speaker_role_key' => 'judge',
        'speaker_type' => 'student',
        'input_type' => 'text',
        'content' => 'Sud majlisi ochiq deb e’lon qilinadi.',
    ]);

    actingAs($student)
        ->getJson(route('courtroom.groups.feed', $session))
        ->assertOk()
        ->assertJson([
            'status' => 'active',
            'stage' => 'PREPARATION',
            'board_evidence_id' => 42,
            'active_speaker' => 'judge',
        ])
        ->assertJsonCount(1, 'turns');
});

it('renders lobby screen with all 9 active roles and allows student pre-flight attendance', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id]);
    $group->members()->attach($student);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $version = $scenario->versions()->sole();

    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'created',
        'current_stage_key' => 'PREPARATION',
        'settings' => [],
    ]);

    $all9Roles = [
        'judge', 'prosecutor', 'victim_advocate', 'victim',
        'defense_advocate', 'defendant', 'witness_1', 'witness_2', 'witness_3',
    ];

    foreach ($all9Roles as $index => $roleKey) {
        $user = $index === 0 ? $student : User::factory()->student()->create();
        $session->participants()->create([
            'user_id' => $user->id,
            'role_key' => $roleKey,
            'actor_type' => 'student',
            'display_name' => $user->name,
            'status' => 'assigned',
        ]);
    }

    actingAs($student)
        ->get(route('courtroom.groups.show', $session))
        ->assertOk()
        ->assertSee('GURUHLI SUD KUTISH XONASI')
        ->assertSee('Qurilma va Mikrofon tekshiruvi (Pre-flight)')
        ->assertSee('Sinash')
        ->assertSee('Mashg‘ulotga hozirman (Tasdiqlash)');

    // Student confirms attendance
    actingAs($student)
        ->postJson(route('courtroom.groups.attend', $session))
        ->assertOk()
        ->assertJson(['status' => 'present']);

    expect($session->participants()->where('user_id', $student->id)->value('status'))->toBe('present');
});

it('supports ajax actions for student turn and teacher controls without page reload', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
    $group = EducationGroup::factory()->create(['course_id' => $course->id]);
    $group->members()->attach($student);
    $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
    $version = $scenario->versions()->sole();

    $session = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $version->id,
        'education_group_id' => $group->id,
        'course_id' => $course->id,
        'facilitator_id' => $teacher->id,
        'selected_role_key' => 'group',
        'status' => 'active',
        'started_at' => now(),
        'current_stage_key' => 'PREPARATION',
        'settings' => [],
    ]);
    $session->participants()->create([
        'user_id' => $student->id,
        'role_key' => 'judge',
        'actor_type' => 'student',
        'display_name' => $student->name,
        'status' => 'present',
    ]);

    // Student posts action via AJAX
    actingAs($student)
        ->postJson(route('courtroom.groups.act', $session), [
            'stage_key' => 'PREPARATION',
            'action_type' => 'make_statement',
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Sud jarayoni rasman boshlandi.',
        ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    // Teacher pauses via AJAX
    actingAs($teacher)
        ->postJson(route('courtroom.groups.pause', $session))
        ->assertOk()
        ->assertJson(['status' => 'paused']);

    expect($session->fresh()->status)->toBe('paused');

    // Teacher resumes via AJAX
    actingAs($teacher)
        ->postJson(route('courtroom.groups.resume', $session))
        ->assertOk()
        ->assertJson(['status' => 'active']);

    expect($session->fresh()->status)->toBe('active');
});
