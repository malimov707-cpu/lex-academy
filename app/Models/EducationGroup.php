<?php

namespace App\Models;

use Database\Factories\EducationGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EducationGroup extends Model
{
    /** @use HasFactory<EducationGroupFactory> */
    use HasFactory;

    protected $fillable = ['name', 'course_id', 'is_demo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')->withTimestamps();
    }
}
