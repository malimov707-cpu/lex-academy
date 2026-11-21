<?php

use App\Models\Course;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('computes dynamic trends and accurate activity status on control center', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $scenario = SimulationScenario::factory()->courtroomReady()->create();

    $sessionActive = SimulationSession::query()->create([
        'scenario_id' => $scenario->id,
        'scenario_version_id' => $scenario->versions()->sole()->id,
        'selected_role_key' => 'judge',
        'status' => 'active',
        'started_at' => now(),
        'current_stage_key' => 'COURT_OPENING',
        'settings' => [],
    ]);

    actingAs($admin)
        ->get(route('admin.control-center'))
        ->assertOk()
        ->assertSee(__('Jami foydalanuvchilar'))
        ->assertSee(__('Virtual sud sessiyalari'))
        ->assertSee(__('Virtual sud sessiyasi boshlandi'))
        ->assertDontSee(__('Virtual sud sessiyasi yakunlandi'));
});
