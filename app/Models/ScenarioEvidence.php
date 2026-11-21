<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioEvidence extends Model
{
    protected $table = 'scenario_evidence';

    /** @var list<string> */
    protected $fillable = [
        'scenario_version_id', 'title', 'type', 'summary', 'body', 'storage_path',
        'original_name', 'mime_type', 'source_label', 'version', 'visibility',
        'allowed_role_keys', 'status', 'checksum', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['allowed_role_keys' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    public function isVisibleTo(string $roleKey): bool
    {
        return $this->visibility === 'all'
            || (in_array($this->visibility, ['role', 'roles'], true) && in_array($roleKey, $this->allowed_role_keys ?? [], true));
    }

    public function annotatableText(): string
    {
        return str_replace(["\r\n", "\r"], "\n", (string) $this->body);
    }
}
