<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonBlock extends Model
{
    protected $fillable = ['lesson_id', 'type', 'content', 'sort_order'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return array<string, string> */
    public static function types(): array
    {
        return ['text' => 'Matn', 'heading' => 'Sarlavha', 'quote' => 'Iqtibos', 'legal_citation' => 'Huquqiy manba',
            'image' => 'Rasm', 'video' => 'Video', 'audio' => 'Audio', 'document' => 'Hujjat',
            'interactive_question' => 'Interaktiv savol', 'quiz' => 'Test', 'assignment' => 'Topshiriq'];
    }

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
