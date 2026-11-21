<?php

namespace App\Services\VirtualCourtroom;

use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScenarioDefinitionEditor
{
    public function draft(SimulationScenario $scenario): ScenarioVersion
    {
        $source = $scenario->versions()->latest('version')->firstOrFail();
        if ($source->status === 'draft' && ! SimulationSession::query()->where('scenario_version_id', $source->id)->exists()) {
            return $source;
        }

        return $this->copyVersion($source, $scenario->id, $source->version + 1);
    }

    private function copyVersion(ScenarioVersion $source, int $scenarioId, int $number): ScenarioVersion
    {
        $source->load(['roles', 'stages.actionRules', 'evidence', 'legalReferences', 'rubric.criteria']);
        $draft = $source->replicate();
        $draft->fill(['scenario_id' => $scenarioId, 'version' => $number, 'status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'published_at' => null, 'snapshot_hash' => null]);
        $draft->save();
        foreach (['roles', 'evidence', 'legalReferences'] as $relation) {
            foreach ($source->{$relation} as $item) {
                $copy = $item->replicate();
                $copy->scenario_version_id = $draft->id;
                $copy->save();
            }
        }
        foreach ($source->stages as $stage) {
            $copy = $stage->replicate();
            $copy->scenario_version_id = $draft->id;
            $copy->save();
            foreach ($stage->actionRules as $rule) {
                $ruleCopy = $rule->replicate();
                $ruleCopy->scenario_stage_id = $copy->id;
                $ruleCopy->save();
            }
        }
        if ($source->rubric) {
            $rubric = $source->rubric->replicate();
            $rubric->scenario_version_id = $draft->id;
            $rubric->status = 'draft';
            $rubric->save();
            foreach ($source->rubric->criteria as $criterion) {
                $copy = $criterion->replicate();
                $copy->evaluation_rubric_id = $rubric->id;
                $copy->save();
            }
        }

        return $draft->fresh();
    }

    public function duplicate(SimulationScenario $source, User $owner): SimulationScenario
    {
        return DB::transaction(function () use ($source, $owner): SimulationScenario {
            $source = SimulationScenario::query()->lockForUpdate()->findOrFail($source->id);
            $copy = $source->replicate();
            $copy->fill([
                'created_by' => $owner->id,
                'title' => Str::limit($source->title.' — nusxa', 255, ''),
                'slug' => Str::limit($source->slug, 180, '').'-'.Str::uuid(),
                'status' => 'draft',
            ]);
            $copy->save();
            $this->copyVersion($source->versions()->latest('version')->firstOrFail(), $copy->id, 1);

            return $copy->fresh();
        }, 3);
    }

    /** @param array{roles: list<array<string, mixed>>, stages: list<array<string, mixed>>, criteria: list<array<string, mixed>>} $data */
    public function save(SimulationScenario $scenario, array $data): ScenarioVersion
    {
        return DB::transaction(function () use ($scenario, $data): ScenarioVersion {
            $locked = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $version = $this->draft($locked);
            $previousStages = $version->stages()->with('actionRules')->get()->keyBy('key');
            $version->roles()->delete();
            foreach ($data['roles'] as $index => $role) {
                $version->roles()->create([...$role, 'sort_order' => $index + 1]);
            }
            $version->stages()->delete();
            foreach ($data['stages'] as $index => $stage) {
                $actions = $stage['actions'] ?? [];
                unset($stage['actions']);
                $stageModel = $version->stages()->create([...$stage, 'allowed_next_stages' => $stage['allowed_next_stages'] ?? [], 'sort_order' => $index + 1]);
                foreach ($actions as $action) {
                    $previousRule = $previousStages->get($stageModel->key)?->actionRules
                        ->where('role_key', $action['role_key'])->firstWhere('action_type', $action['action_type']);
                    $stageModel->actionRules()->create([
                        ...$action,
                        'response_role_keys' => ($action['response_role_keys'] ?? []) ?: null,
                        'scoring_metadata' => $previousRule?->scoring_metadata,
                    ]);
                }
            }
            $rubric = $version->rubric()->firstOrCreate([], ['title' => 'Virtual sud rubrikasi', 'total_score' => 100, 'status' => 'draft']);
            $rubric->criteria()->delete();
            foreach ($data['criteria'] as $index => $criterion) {
                $rubric->criteria()->create([...$criterion, 'max_score' => $criterion['weight'], 'sort_order' => $index + 1]);
            }
            $version->update(['snapshot_hash' => null]);

            return $version->fresh(['roles', 'stages.actionRules', 'rubric.criteria']);
        }, 3);
    }
}
