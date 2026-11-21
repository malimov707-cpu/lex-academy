<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    /** @var list<string> */
    protected $fillable = ['course_id', 'student_id', 'status', 'enrolled_at'];

    /** @param Builder<CourseEnrollment> $query */
    public function scopeForTeacher(Builder $query, User $teacher): Builder
    {
        return $query->whereIn('course_id', Course::query()->managedBy($teacher)->select('id'));
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
