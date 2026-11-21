<?php

namespace App\Models;

use Database\Factories\LegalCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalCase extends Model
{
    /** @use HasFactory<LegalCaseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['assignment_id', 'category', 'situation', 'facts', 'legal_issue', 'legislation', 'analysis_questions', 'status'];

    /** @return array<string, string> */
    public static function categories(): array
    {
        return [
            'constitutional' => 'Konstitutsiyaviy huquq', 'civil' => 'Fuqarolik huquqi',
            'criminal' => 'Jinoyat huquqi', 'administrative' => 'Ma’muriy huquq',
            'labor' => 'Mehnat huquqi', 'family' => 'Oila huquqi',
            'business' => 'Tadbirkorlik huquqi', 'tax' => 'Soliq huquqi',
            'international' => 'Xalqaro huquq',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(LegalCaseFeedback::class);
    }
}
