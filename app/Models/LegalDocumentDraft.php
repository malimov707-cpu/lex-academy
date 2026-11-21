<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocumentDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_type',
        'title',
        'content',
        'version',
        'analysis',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'analysis' => 'array',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function typeLabels(): array
    {
        return [
            'claim' => 'Da’vo arizasi (Claim)',
            'application' => 'Ariza (Application)',
            'complaint' => 'Shikoyat (Complaint)',
            'contract' => 'Shartnoma (Contract)',
            'demand' => 'Talabnoma (Demand)',
        ];
    }
}
