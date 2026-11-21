<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioLegalReference extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_version_id', 'legal_source_id', 'title', 'source_url',
        'citation_text', 'verified_at', 'verified_by', 'library_verified_at', 'library_excerpt',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'library_verified_at' => 'datetime'];
    }

    /** @param Builder<ScenarioLegalReference> $query */
    public function scopeCurrentSource(Builder $query): void
    {
        $query->where(fn (Builder $references): Builder => $references
            ->where(fn (Builder $manual): Builder => $manual->whereNull('library_verified_at')->whereNull('legal_source_id'))
            ->orWhereHas('legalSource', fn (Builder $library): Builder => $library->courtroomLibrary()
                ->whereColumn('legal_sources.verified_at', 'scenario_legal_references.library_verified_at')
                ->whereColumn('legal_sources.url', 'scenario_legal_references.source_url')));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    public function legalSource(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
