<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\SimulationSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SimulationSession extends Model
{
    /** @use HasFactory<SimulationSessionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scenario_id', 'scenario_version_id', 'student_id', 'selected_role_key',
        'education_group_id', 'course_id', 'facilitator_id', 'group_creation_key',
        'status', 'current_stage_key', 'started_at', 'completed_at', 'last_activity_at',
        'elapsed_seconds', 'lock_version', 'settings',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function educationGroup(): BelongsTo
    {
        return $this->belongsTo(EducationGroup::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function isManagedGroupBy(User $user): bool
    {
        return $this->education_group_id !== null && ($user->isAdmin() ||
            ($this->facilitator_id === $user->id && $this->course?->isManagedBy($user)));
    }

    public function scopeGroupVisibleTo(Builder $query, User $user): Builder
    {
        $query->whereNotNull('education_group_id');
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->hasRole(UserRole::Teacher)) {
            return $query->where('facilitator_id', $user->id)
                ->whereHas('course', fn (Builder $courses): Builder => $courses->managedBy($user));
        }

        if ($user->hasRole(UserRole::Student)) {
            return $query->whereHas('participants', fn (Builder $members): Builder => $members->where('user_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(SimulationScenario::class, 'scenario_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(SessionTurn::class)->orderBy('sequence');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SessionAction::class);
    }

    public function evidenceAnnotations(): HasMany
    {
        return $this->hasMany(CourtroomEvidenceAnnotation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SessionEvent::class)->orderBy('sequence');
    }

    public function voiceSessions(): HasMany
    {
        return $this->hasMany(VoiceSession::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(EvaluationResult::class);
    }

    /** @param Builder<SimulationSession> $query */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('student_id', $user->id);
    }
}
