<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = ['class_schedule_id', 'student_id', 'recorded_by', 'status', 'reason'];
}
