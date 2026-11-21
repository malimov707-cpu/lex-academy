<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionEvent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'simulation_session_id', 'sequence', 'type', 'actor_role_key',
        'stage_key', 'payload', 'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }
}
