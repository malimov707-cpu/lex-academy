<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\EvaluationCriterion;
use App\Models\ScenarioActionRule;
use App\Models\ScenarioEvidence;
use App\Models\ScenarioRole;
use App\Models\ScenarioStage;
use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SimulationScenario>
 */
class SimulationScenarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'created_by' => User::factory()->create(['role' => UserRole::Teacher])->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 99999),
            'description' => fake()->paragraph(),
            'legal_area' => 'civil',
            'case_type' => 'claim',
            'difficulty' => 'intermediate',
            'estimated_duration_minutes' => 45,
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'published']);
    }

    public function courtroomReady(): static
    {
        return $this->published()->afterCreating(function (SimulationScenario $scenario): void {
            $version = ScenarioVersion::factory()->published()->create([
                'scenario_id' => $scenario->id,
                'case_brief' => 'Bu test uchun to‘qima fuqarolik ishi.',
                'case_facts' => 'Yozma shartnoma va qabul dalolatnomasi mavjud.',
            ]);
            foreach ([
                ['judge', 'Sudya', true],
                ['prosecutor', 'Da’vogar vakili', true],
                ['defense_attorney', 'Javobgar vakili', true],
                ['witness', 'Guvoh', false],
            ] as $index => [$key, $name, $selectable]) {
                ScenarioRole::query()->create([
                    'scenario_version_id' => $version->id,
                    'key' => $key,
                    'name' => $name,
                    'description' => $name.' roli.',
                    'instructions' => 'Faqat scenario faktlariga tayaning.',
                    'private_context' => $key.' uchun maxfiy kontekst.',
                    'is_student_selectable' => $selectable,
                    'sort_order' => $index + 1,
                ]);
            }
            $preparation = ScenarioStage::query()->create([
                'scenario_version_id' => $version->id,
                'key' => 'PREPARATION',
                'name' => 'Tayyorgarlik',
                'sort_order' => 1,
                'allowed_next_stages' => ['EVIDENCE_PRESENTATION'],
            ]);
            $evidenceStage = ScenarioStage::query()->create([
                'scenario_version_id' => $version->id,
                'key' => 'EVIDENCE_PRESENTATION',
                'name' => 'Dalillar',
                'sort_order' => 2,
                'allowed_next_stages' => ['EVALUATION'],
            ]);
            ScenarioStage::query()->create([
                'scenario_version_id' => $version->id,
                'key' => 'EVALUATION',
                'name' => 'Baholash',
                'sort_order' => 3,
                'allowed_next_stages' => [],
                'is_terminal' => true,
            ]);
            ScenarioActionRule::query()->create([
                'scenario_stage_id' => $preparation->id,
                'role_key' => '*',
                'action_type' => 'make_statement',
                'label' => 'Fikr bildirish',
                'requires_content' => true,
            ]);
            ScenarioActionRule::query()->create([
                'scenario_stage_id' => $preparation->id,
                'role_key' => '*',
                'action_type' => 'advance_stage',
                'label' => 'Keyingi bosqich',
                'advances_to_stage_key' => 'EVIDENCE_PRESENTATION',
            ]);
            ScenarioActionRule::query()->create([
                'scenario_stage_id' => $evidenceStage->id,
                'role_key' => '*',
                'action_type' => 'present_evidence',
                'label' => 'Dalilni taqdim etish',
                'requires_content' => true,
                'requires_evidence' => true,
            ]);
            ScenarioActionRule::query()->create([
                'scenario_stage_id' => $evidenceStage->id,
                'role_key' => '*',
                'action_type' => 'advance_stage',
                'label' => 'Keyingi bosqich',
                'advances_to_stage_key' => 'EVALUATION',
            ]);
            ScenarioEvidence::query()->create([
                'scenario_version_id' => $version->id,
                'title' => 'Test shartnomasi',
                'type' => 'contract',
                'summary' => 'Test uchun dalil.',
                'body' => 'O‘quv hujjati matni.',
                'source_label' => 'Test o‘quv materiali',
                'visibility' => 'all',
                'status' => 'active',
            ]);
            $rubric = $version->rubric()->create(['title' => 'Test rubrikasi', 'total_score' => 100, 'status' => 'published']);
            foreach ([
                ['legal_knowledge', 'Huquqiy bilim', 25],
                ['evidence_analysis', 'Dalillar tahlili', 25],
                ['procedure', 'Protsessual bilim', 25],
                ['ethics', 'Etika', 25],
            ] as $index => [$key, $name, $weight]) {
                EvaluationCriterion::query()->create([
                    'evaluation_rubric_id' => $rubric->id,
                    'key' => $key,
                    'name' => $name,
                    'description' => $name,
                    'weight' => $weight,
                    'max_score' => $weight,
                    'sort_order' => $index + 1,
                ]);
            }
        });
    }
}
