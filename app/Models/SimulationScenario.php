<?php

namespace App\Models;

use Database\Factories\SimulationScenarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationScenario extends Model
{
    /** @use HasFactory<SimulationScenarioFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'created_by', 'title', 'slug', 'description', 'legal_area', 'case_type',
        'difficulty', 'estimated_duration_minutes', 'status',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScenarioVersion::class, 'scenario_id');
    }

    /**
     * @return list<string>
     */
    public function getRequiredRolesAttribute(): array
    {
        return [
            'judge', 'prosecutor', 'victim_advocate', 'victim',
            'defense_advocate', 'defendant', 'witness_1', 'witness_2', 'witness_3',
        ];
    }

    public function getRoleCapacityAttribute(): int
    {
        return 9;
    }

    public function getWitnessCountAttribute(): int
    {
        return 3;
    }

    public function getObserverPolicyAttribute(): string
    {
        return 'unlimited';
    }

    public function getDurationMinutesAttribute(): int
    {
        return (int) ($this->estimated_duration_minutes ?: 25);
    }

    public function getCourtroomSceneIdAttribute(): string
    {
        return 'main_courtroom_3d';
    }

    /**
     * @return list<string>
     */
    public function getAvailableAnimationsAttribute(): array
    {
        return [
            'idle', 'sitting', 'standing', 'walking', 'speaking',
            'listening', 'thinking', 'hand_gestures', 'asking_question',
            'presenting_evidence', 'reaction', 'court_opening', 'court_conclusion',
        ];
    }

    public function getEvaluationRubricIdAttribute(): int
    {
        return 1;
    }

    /** @param Builder<SimulationScenario> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')->whereHas(
            'versions',
            fn (Builder $versionQuery): Builder => $versionQuery->where('status', 'published'),
        );
    }
}
