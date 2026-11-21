<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTutorMessage extends Model
{
    /** @var list<string> */
    protected $fillable = ['session_id', 'role', 'content', 'confidence', 'metadata'];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'confidence' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiTutorSession::class, 'session_id');
    }
}
