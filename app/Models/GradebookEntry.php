<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradebookEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'course_id', 'student_id', 'simulation_session_id', 'column_key',
        'score', 'evaluator_id', 'approved_at',
    ];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
