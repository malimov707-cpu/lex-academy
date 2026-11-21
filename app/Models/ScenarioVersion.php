<?php

namespace App\Models;

use Database\Factories\ScenarioVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScenarioVersion extends Model
{
    /** @use HasFactory<ScenarioVersionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scenario_id', 'version', 'procedure_type', 'learning_objectives', 'case_brief',
        'case_facts', 'teacher_notes', 'status', 'approved_by', 'approved_at',
        'published_at', 'snapshot_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'learning_objectives' => 'array',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(SimulationScenario::class, 'scenario_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ScenarioRole::class)->orderBy('sort_order');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ScenarioStage::class)->orderBy('sort_order');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ScenarioEvidence::class)->orderBy('sort_order');
    }

    public function legalReferences(): HasMany
    {
        return $this->hasMany(ScenarioLegalReference::class);
    }

    public function verifiedLegalReferences(): HasMany
    {
        return $this->legalReferences()->whereNotNull('verified_at')->currentSource();
    }

    public function rubric(): HasOne
    {
        return $this->hasOne(EvaluationRubric::class);
    }
}
