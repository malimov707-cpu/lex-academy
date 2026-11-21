<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationCriterionResult extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'evaluation_result_id', 'evaluation_criterion_id', 'score', 'rationale',
        'evidence_action_ids', 'evidence_event_ids',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'evidence_action_ids' => 'array',
            'evidence_event_ids' => 'array',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }
}
