<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\ScenarioVersion;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulationSession>
 */
class SimulationSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $version = ScenarioVersion::factory()->published()->create();

        return [
            'scenario_id' => $version->scenario_id,
            'scenario_version_id' => $version->id,
            'student_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'selected_role_key' => 'defense_attorney',
            'status' => 'created',
            'settings' => ['captions' => true, 'auto_camera' => true],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'active',
            'current_stage_key' => 'PREPARATION',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
}
