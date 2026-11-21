<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAiDraft extends Model
{
    protected $fillable = ['course_id', 'teacher_id', 'kind', 'topic', 'sources', 'content', 'status', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return array<string, string> */
    public static function kinds(): array
    {
        return ['plan' => 'Dars rejasi', 'notes' => 'Dars konspekti', 'objectives' => 'O‘quv maqsadlari',
            'quiz' => 'Test savollari', 'case' => 'Kazuslar', 'assignment' => 'Uy vazifalari',
            'revision' => 'Takrorlash savollari', 'rubric' => 'Baholash mezonlari', 'summary' => 'Dars xulosasi'];
    }
}
