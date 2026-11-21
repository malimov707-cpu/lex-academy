<?php

namespace App\Models;

use Database\Factories\CourtroomEvidenceAnnotationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtroomEvidenceAnnotation extends Model
{
    /** @use HasFactory<CourtroomEvidenceAnnotationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['simulation_session_id', 'scenario_evidence_id', 'idempotency_key', 'start_offset', 'end_offset', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_offset' => 'integer', 'end_offset' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SimulationSession::class, 'simulation_session_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ScenarioEvidence::class, 'scenario_evidence_id');
    }

    public function excerptFrom(ScenarioEvidence $evidence): ?string
    {
        return $this->start_offset === null ? null : mb_substr($evidence->annotatableText(), $this->start_offset, $this->end_offset - $this->start_offset);
    }
}
