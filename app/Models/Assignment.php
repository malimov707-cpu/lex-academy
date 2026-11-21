<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assignment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'course_id', 'lesson_id', 'teacher_id', 'title', 'description', 'instructions',
        'type', 'deadline', 'max_score', 'rubric', 'allowed_file_formats',
        'max_file_size_kb', 'submission_attempts', 'allow_late_submission',
    ];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'allowed_file_formats' => 'array',
            'deadline' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function legalCase(): HasOne
    {
        return $this->hasOne(LegalCase::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
