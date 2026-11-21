<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonVersion extends Model
{
    protected $fillable = ['lesson_id', 'edited_by', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
