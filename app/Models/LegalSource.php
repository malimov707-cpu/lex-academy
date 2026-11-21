<?php

namespace App\Models;

use Database\Factories\LegalSourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalSource extends Model
{
    /** @use HasFactory<LegalSourceFactory> */
    use HasFactory;

    protected $fillable = ['course_id', 'created_by', 'verified_by', 'title', 'url', 'excerpt', 'topic', 'status', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(LegalSourceCheck::class, 'legal_source_id');
    }

    public function latestCheck(): HasOne
    {
        return $this->hasOne(LegalSourceCheck::class, 'legal_source_id')->latestOfMany('checked_at');
    }

    /** @param Builder<LegalSource> $query */
    public function scopeCourtroomLibrary(Builder $query): void
    {
        $query->whereNull('course_id')->where('status', 'verified')->whereNotNull('verified_at')->whereNotNull('verified_by');
    }
}
