<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalSourceCheck extends Model
{
    protected $fillable = [
        'legal_source_id',
        'reviewed_by',
        'status',
        'http_status',
        'content_sha256',
        'etag',
        'source_last_modified',
        'checked_at',
        'reviewed_at',
        'impact_status',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class, 'legal_source_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
