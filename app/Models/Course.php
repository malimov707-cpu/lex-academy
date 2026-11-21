<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'category_id', 'teacher_id', 'title', 'slug', 'description', 'cover_image',
        'education_level', 'difficulty', 'language', 'duration_weeks',
        'learning_objectives', 'prerequisites', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'learning_objectives' => 'array',
            'prerequisites' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EducationCategory::class, 'category_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function scopeManagedBy(Builder $query, User $user): Builder
    {
        return $query->where(fn ($query) => $query->where('teacher_id', $user->id)->orWhereHas('teacherAssignments', fn ($query) => $query->where('teacher_id', $user->id)));
    }

    public function isManagedBy(User $user): bool
    {
        return $user->role->value === 'teacher' && ($this->teacher_id === $user->id || $this->teacherAssignments()->where('teacher_id', $user->id)->exists());
    }
}
