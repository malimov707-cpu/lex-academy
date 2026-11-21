<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'module_id', 'title', 'description', 'objectives', 'body',
        'completion_criteria', 'completion_rules', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'completion_criteria' => 'array', 'completion_rules' => 'array',
        ];
    }

    public function scopeInLearningOrder(Builder $query): Builder
    {
        return $query->join('course_modules', 'lessons.module_id', '=', 'course_modules.id')
            ->select('lessons.*')
            ->orderBy('course_modules.course_id')->orderBy('course_modules.sort_order')->orderBy('course_modules.id')
            ->orderBy('lessons.sort_order')->orderBy('lessons.id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LessonVersion::class)->latest('id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
