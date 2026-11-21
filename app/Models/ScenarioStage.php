<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioStage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_version_id', 'key', 'name', 'description', 'sort_order',
        'time_limit_seconds', 'allowed_next_stages', 'is_terminal',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'allowed_next_stages' => 'array',
            'is_terminal' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    public function actionRules(): HasMany
    {
        return $this->hasMany(ScenarioActionRule::class);
    }
}
