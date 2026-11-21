<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    /** @var list<string> */
    protected $fillable = ['course_id', 'category_id', 'type', 'text', 'explanation', 'difficulty', 'points'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
}
