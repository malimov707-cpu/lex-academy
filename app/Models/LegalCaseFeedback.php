<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalCaseFeedback extends Model
{
    protected $table = 'legal_case_feedback';

    /** @var list<string> */
    protected $fillable = ['legal_case_id', 'student_id', 'analysis', 'feedback', 'confidence'];
}
