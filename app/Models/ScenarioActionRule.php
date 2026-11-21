<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioActionRule extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_stage_id', 'role_key', 'action_type', 'label', 'requires_content',
        'requires_evidence', 'advances_to_stage_key', 'scoring_metadata', 'response_role_keys',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_content' => 'boolean',
            'requires_evidence' => 'boolean',
            'scoring_metadata' => 'array',
            'response_role_keys' => 'array',
        ];
    }

    /**
     * @param  Collection<int, ScenarioRole>  $roles
     * @return Collection<int, ScenarioRole>
     */
    public function responseRoles(Collection $roles, string $actorRoleKey, string $stageKey): Collection
    {
        if ($this->advances_to_stage_key !== null || $this->action_type === 'advance_stage') {
            return new Collection;
        }

        $allowed = $this->response_role_keys;
        if ($allowed === null && ! in_array($this->action_type, ['ask_question', 'request_clarification'], true)) {
            return new Collection;
        }
        if ($allowed === null && $this->action_type === 'ask_question' && in_array($stageKey, ['WITNESS_EXAMINATION', 'CROSS_EXAMINATION'], true)) {
            $allowed = ['witness', 'expert', 'expert_witness'];
        }

        return $roles->filter(fn (ScenarioRole $role): bool => $role->key !== $actorRoleKey
            && ($allowed === null || in_array($role->key, $allowed, true)))->values();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ScenarioStage::class, 'scenario_stage_id');
    }
}
