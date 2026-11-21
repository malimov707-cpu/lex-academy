<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherReview extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'evaluation_result_id', 'reviewer_id', 'decision', 'adjusted_score',
        'comment', 'criterion_overrides', 'has_explicit_criterion_scores',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['adjusted_score' => 'decimal:2', 'criterion_overrides' => 'array', 'has_explicit_criterion_scores' => 'boolean'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
