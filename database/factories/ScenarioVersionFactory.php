<?php

namespace Database\Factories;

use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScenarioVersion>
 */
class ScenarioVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scenario_id' => SimulationScenario::factory(),
            'version' => 1,
            'procedure_type' => 'civil_claim_training',
            'learning_objectives' => ['Dalillarni tahlil qilish', 'Protsessual tartibga rioya qilish'],
            'case_brief' => fake()->paragraphs(2, true),
            'case_facts' => fake()->paragraphs(3, true),
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'approved_at' => now(),
            'published_at' => now(),
            'snapshot_hash' => hash('sha256', (string) fake()->uuid()),
        ]);
    }
}
