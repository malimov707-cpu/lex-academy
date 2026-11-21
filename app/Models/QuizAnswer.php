<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    /** @var list<string> */
    protected $fillable = ['attempt_id', 'question_id', 'selected_option_ids', 'text_answer', 'is_correct', 'points_earned', 'ai_guidance'];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
