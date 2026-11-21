<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceSession extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'simulation_session_id', 'student_id', 'provider', 'status',
        'transcript_consent', 'audio_recording', 'consented_at', 'ended_at', 'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transcript_consent' => 'boolean',
            'audio_recording' => 'boolean',
            'consented_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(VoiceTranscriptSegment::class)->orderBy('sequence');
    }
}
