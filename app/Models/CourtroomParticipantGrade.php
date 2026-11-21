<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtroomParticipantGrade extends Model
{
    protected $fillable = [
        'simulation_session_id', 'course_id', 'student_id', 'role_key', 'rubric_version',
        'ai_recommendation', 'criteria', 'activity', 'evidence', 'attendance', 'gradebook_columns',
        'individual_score', 'activity_score', 'simulation_performance', 'teacher_final_grade',
        'comment', 'strengths', 'improvements', 'recommendation', 'reason', 'status',
        'evaluator_id', 'approved_by', 'approved_at', 'reassessment',
    ];

    protected function casts(): array
    {
        return [
            'ai_recommendation' => 'array', 'criteria' => 'array', 'activity' => 'array',
            'evidence' => 'array', 'gradebook_columns' => 'array', 'reassessment' => 'array',
            'individual_score' => 'float', 'activity_score' => 'float', 'simulation_performance' => 'float',
            'teacher_final_grade' => 'float', 'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved')->whereNotNull('approved_at');
    }

    public function courseContribution(): ?float
    {
        if ($this->status !== 'approved') {
            return null;
        }
        $components = [
            'attendance' => match ($this->attendance) {
                'present', 'late' => 100,
                'absent' => 0,
                default => null,
            },
            'classroom_activity' => $this->activity_score === null ? null : $this->activity_score * 10,
            'individual_role_score' => $this->individual_score,
            'simulation_performance' => $this->simulation_performance,
            'teacher_final_grade' => $this->teacher_final_grade,
        ];
        $selected = collect($components)->only($this->gradebook_columns)->filter(fn (?float $score): bool => $score !== null);

        return $selected->isEmpty() ? null : round($selected->avg(), 2);
    }
}
