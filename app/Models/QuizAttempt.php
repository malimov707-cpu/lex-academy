<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    /** @var list<string> */
    protected $fillable = ['quiz_id', 'student_id', 'attempt', 'score', 'status', 'started_at', 'submitted_at', 'question_order', 'option_order'];

    protected function casts(): array
    {
        return [
            'question_order' => 'array', 'option_order' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return Collection<int, Question> */
    public function selectedQuestions(): Collection
    {
        $questions = $this->quiz->questions()->with('options')->get()->keyBy('id');
        $ids = $this->question_order ?? $questions->keys()->all();
        $selected = new Collection;
        foreach ($ids as $id) {
            $question = $questions->get($id);
            if (! $question) {
                continue;
            }
            $options = $question->options->keyBy('id');
            $order = $this->option_order[$id] ?? $options->keys()->all();
            $question->setRelation('options', new Collection(collect($order)->map(fn ($id) => $options->get($id))->filter()->all()));
            $selected->push($question);
        }

        return $selected;
    }

    public function hasExpired(): bool
    {
        return ($this->quiz->time_limit_minutes && $this->started_at->addMinutes($this->quiz->time_limit_minutes)->lte(now()))
            || ($this->quiz->available_until && $this->quiz->available_until->lte(now()));
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }
}
