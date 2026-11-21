<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRubric extends Model
{
    /** @var list<string> */
    protected $fillable = ['scenario_version_id', 'title', 'total_score', 'status'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class)->orderBy('sort_order');
    }
}
