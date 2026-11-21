<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceTranscriptSegment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'voice_session_id', 'sequence', 'speaker_role_key', 'content',
        'is_final', 'started_at_ms', 'ended_at_ms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_final' => 'boolean'];
    }

    public function voiceSession(): BelongsTo
    {
        return $this->belongsTo(VoiceSession::class);
    }
}
