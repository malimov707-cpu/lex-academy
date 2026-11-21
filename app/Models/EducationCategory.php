<?php

namespace App\Models;

use Database\Factories\EducationCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationCategory extends Model
{
    /** @use HasFactory<EducationCategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'description'];
}
