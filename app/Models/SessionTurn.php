<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionTurn extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'simulation_session_id', 'sequence', 'speaker_role_key', 'speaker_type',
        'input_type', 'content', 'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }
}
