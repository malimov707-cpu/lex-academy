<?php

namespace Database\Factories;

use App\Models\CourtroomEvidenceAnnotation;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourtroomEvidenceAnnotation>
 */
class CourtroomEvidenceAnnotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'simulation_session_id' => function (): int {
                $scenario = SimulationScenario::factory()->courtroomReady()->create();

                return SimulationSession::factory()->active()->create([
                    'scenario_id' => $scenario->id, 'scenario_version_id' => $scenario->versions()->sole()->id,
                ])->id;
            },
            'scenario_evidence_id' => fn (array $attributes): int => SimulationSession::query()->findOrFail($attributes['simulation_session_id'])
                ->version->evidence()->firstOrFail()->id,
            'idempotency_key' => (string) Str::uuid(),
            'note' => fake()->sentence(),
        ];
    }
}
