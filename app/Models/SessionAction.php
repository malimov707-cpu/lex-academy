<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAction extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'simulation_session_id', 'actor_id', 'scenario_evidence_id', 'actor_role_key',
        'stage_key', 'action_type', 'target_role_key', 'content', 'validation_status', 'validation_message',
        'ai_response', 'evaluation_metadata', 'idempotency_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['evaluation_metadata' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ScenarioEvidence::class, 'scenario_evidence_id');
    }
}
