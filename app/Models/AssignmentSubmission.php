<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentSubmission extends Model
{
    /** @var list<string> */
    protected $fillable = ['assignment_id', 'student_id', 'attempt', 'text', 'status', 'score', 'submitted_at', 'ai_analysis'];

    protected $casts = [
        'ai_analysis' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MentorReview::class, 'submission_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'submission_id');
    }
}
