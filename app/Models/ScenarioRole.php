<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioRole extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_version_id', 'key', 'name', 'description', 'instructions',
        'private_context', 'is_student_selectable', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_student_selectable' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }
}
