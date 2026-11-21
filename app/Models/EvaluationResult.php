<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationResult extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'simulation_session_id', 'status', 'overall_score', 'generated_by',
        'strengths', 'improvements', 'next_exercises', 'ai_feedback', 'generated_at',
        'reviewed_by', 'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'strengths' => 'array',
            'improvements' => 'array',
            'next_exercises' => 'array',
            'ai_feedback' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }

    public function criterionResults(): HasMany
    {
        return $this->hasMany(EvaluationCriterionResult::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TeacherReview::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
