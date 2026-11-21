<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = ['course_id', 'student_id', 'issued_by', 'verification_code', 'student_name', 'course_title', 'issuer', 'final_result', 'completed_at', 'issued_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
