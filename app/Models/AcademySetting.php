<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademySetting extends Model
{
    /** @var list<string> */
    protected $fillable = ['id', 'name', 'certificate_issuer', 'support_email', 'support_phone'];
}
