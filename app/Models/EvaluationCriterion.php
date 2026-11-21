<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationCriterion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'evaluation_rubric_id', 'key', 'name', 'description', 'weight',
        'max_score', 'guidance', 'sort_order',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(EvaluationRubric::class, 'evaluation_rubric_id');
    }
}
