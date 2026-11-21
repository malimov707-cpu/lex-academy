<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorReview extends Model
{
    /** @var list<string> */
    protected $fillable = ['submission_id', 'mentor_id', 'score', 'comment', 'annotations', 'decision'];

    protected function casts(): array
    {
        return [
            'annotations' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
