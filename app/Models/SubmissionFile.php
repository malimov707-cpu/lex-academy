<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionFile extends Model
{
    /** @var list<string> */
    protected $fillable = ['submission_id', 'file_path', 'original_name', 'mime_type', 'size_kb'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    public function assignmentSubmission(): BelongsTo
    {
        return $this->submission();
    }
}
