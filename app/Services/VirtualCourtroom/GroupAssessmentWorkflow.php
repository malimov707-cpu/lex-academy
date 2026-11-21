<?php

namespace App\Services\VirtualCourtroom;

use App\Enums\UserRole;
use App\Models\ClassSchedule;
use App\Models\CourtroomParticipantGrade;
use App\Models\EducationAuditLog;
use App\Models\GradebookEntry;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupAssessmentWorkflow
{
    public function __construct(private readonly GroupScoringEngine $engine) {}

    public function prepare(SimulationSession $session): void
    {
        $session = $session->fresh();
        abort_unless($session?->education_group_id && $session->course_id && $session->status === 'completed', 409);
        $rubric = $session->settings['assessment_rubric'] ?? null;
        abort_unless(isset($rubric['roles'], $rubric['academic'], $rubric['version']), 409);
        if (isset($session->settings['assessment'])) {
            return;
        }

        $attendance = collect();
        if ($scheduleId = ($session->settings['class_schedule_id'] ?? null)) {
            $schedule = ClassSchedule::query()->where('course_id', $session->course_id)
                ->where('teacher_id', $session->facilitator_id)
                ->where(fn ($query) => $query->whereNull('group_id')->orWhere('group_id', $session->education_group_id))
                ->find($scheduleId);
            $attendance = $schedule?->attendance()->pluck('status', 'student_id') ?? collect();
        }

        $participants = $session->participants()->whereIn('actor_type', ['student', 'observer'])->whereNotNull('user_id')->get();
        abort_if($participants->isEmpty() || $participants->pluck('user_id')->duplicates()->isNotEmpty(), 409);
        $rows = [];
        foreach ($participants as $participant) {
            $rows[] = [
                'student_id' => $participant->user_id, 'role_key' => $participant->role_key,
                'rubric_version' => $rubric['version'], 'course_id' => $session->course_id,
                'ai_recommendation' => $this->engine->recommend($session, $participant),
                'gradebook_columns' => $rubric['gradebook'],
                'attendance' => $attendance->get($participant->user_id) ?? (isset($participant->metadata['attended_at']) ? 'present' : 'unmarked'),
            ];
        }
        DB::transaction(function () use ($session, $rows): void {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            if (isset($locked->settings['assessment'])) {
                return;
            }
            foreach ($rows as $row) {
                CourtroomParticipantGrade::query()->create(['simulation_session_id' => $locked->id, ...$row]);
            }
            $this->saveState($locked, ['status' => 'preliminary', 'outcome' => null, 'conclusion' => null]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function review(SimulationSession $session, User $teacher, array $data): void
    {
        $input = Validator::make($data, [
            'version' => ['required', 'integer', 'min:1'], 'grades' => ['required', 'array', 'min:1'],
            'grades.*' => ['required', 'array:student_id,criteria,activity,evidence,attendance,teacher_final_grade,comment,strengths,improvements,recommendation,reason,reassessment_response'],
            'grades.*.student_id' => ['required', 'integer', 'distinct'],
            'grades.*.attendance' => ['required', Rule::in(['present', 'absent', 'late', 'excused', 'unmarked'])],
            'grades.*.teacher_final_grade' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'grades.*.criteria' => ['sometimes', 'array'], 'grades.*.activity' => ['sometimes', 'array'],
            'grades.*.evidence' => ['sometimes', 'array'], 'grades.*.evidence.*' => ['array'],
            'grades.*.evidence.*.*' => ['string', 'max:100'],
            ...collect(['comment', 'strengths', 'improvements', 'recommendation', 'reason', 'reassessment_response'])
                ->mapWithKeys(fn (string $field): array => ['grades.*.'.$field => ['nullable', 'string', 'max:3000']])->all(),
        ])->validate();

        DB::transaction(function () use ($session, $teacher, $input): void {
            $locked = $this->lockForTeacher($session, $teacher);
            $this->checkVersion($locked, $input['version']);
            $state = $locked->settings['assessment'];
            abort_if($state['status'] === 'approved', 409, __('Avval natijani qayta ko‘rib chiqishga qaytaring.'));
            $rubric = $locked->settings['assessment_rubric'];
            $grades = $this->grades($locked);
            foreach ($input['grades'] as $row) {
                $grade = $grades->get($row['student_id']);
                if (! $grade) {
                    throw ValidationException::withMessages(['grades' => __('Talaba ushbu sessiyaga biriktirilmagan.')]);
                }
                $this->reviewStudent($grade, $row, $rubric, $teacher);
            }
            $state['status'] = $grades->every(fn ($grade): bool => $grade->status === 'teacher_reviewed') ? 'teacher_reviewed' : 'preliminary';
            $state['outcome'] = $this->outcome($rubric, $grades);
            $this->saveState($locked, $state);
        }, 3);
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $rubric */
    private function reviewStudent(CourtroomParticipantGrade $grade, array $row, array $rubric, User $teacher): void
    {
        $observer = str_starts_with($grade->role_key, 'observer_');
        $criteria = $this->criterionPoints($row['criteria'] ?? [], $rubric['roles'][$observer ? 'observer' : $grade->role_key], 'criteria');
        $activity = $this->criterionPoints($row['activity'] ?? [], $rubric['academic'][$observer ? 'observer' : 'active'], 'activity');
        $reason = trim((string) ($row['reason'] ?? ''));
        $suggestions = $grade->ai_recommendation['criteria'] ?? [];
        $evidence = $row['evidence'] ?? [];
        $knownRefs = array_keys($grade->ai_recommendation['evidence'] ?? []);
        if (array_diff_key($evidence, $criteria) !== []) {
            throw ValidationException::withMessages(['evidence' => __('Dalil baholanayotgan mezonga tegishli bo‘lishi kerak.')]);
        }
        $overridesAi = false;
        foreach ($criteria as $key => $score) {
            $refs = $evidence[$key] ?? ($suggestions[$key]['evidence'] ?? []);
            if (array_diff($refs, $knownRefs) !== []) {
                throw ValidationException::withMessages(['evidence' => __('Dalil talabaning ushbu sessiyadagi ishiga tegishli bo‘lishi kerak.')]);
            }
            $evidence[$key] = array_values(array_unique($refs));
            if (isset($suggestions[$key]['score']) && (float) $suggestions[$key]['score'] !== $score) {
                $overridesAi = true;
            }
        }
        $before = $this->revisionSnapshot($grade);
        $grade->fill([
            'criteria' => $criteria, 'activity' => $activity, 'evidence' => $evidence, 'attendance' => $row['attendance'],
            'individual_score' => $criteria === [] ? null : round(array_sum($criteria), 2),
            'activity_score' => $activity === [] ? null : round(array_sum($activity), 2),
            'teacher_final_grade' => $row['teacher_final_grade'] ?? null, 'comment' => $row['comment'] ?? null,
            'strengths' => $row['strengths'] ?? null, 'improvements' => $row['improvements'] ?? null,
            'recommendation' => $row['recommendation'] ?? null,
        ]);
        $changedPrevious = $grade->evaluator_id !== null && $grade->isDirty(['criteria', 'activity', 'attendance', 'teacher_final_grade', 'evidence']);
        $attendanceChanged = $grade->isDirty('attendance') && ($before['attendance'] ?? 'unmarked') !== 'unmarked';
        if (($criteria === [] || $activity === [] || $changedPrevious || $overridesAi || $attendanceChanged) && $reason === '') {
            throw ValidationException::withMessages(['reason' => __('O‘zgartirish yoki baho qo‘yilmaganligi sababini yozing.')]);
        }
        $grade->fill(['status' => 'teacher_reviewed', 'evaluator_id' => $teacher->id, 'reason' => $reason === '' ? ($grade->reason ?? null) : $reason]);
        $response = trim((string) ($row['reassessment_response'] ?? ''));
        if ($response !== '') {
            if (($grade->reassessment['status'] ?? null) !== 'pending') {
                throw ValidationException::withMessages(['reassessment_response' => __('Ochiq qayta ko‘rib chiqish so‘rovi mavjud emas.')]);
            }
            $grade->reassessment = [...$grade->reassessment, 'status' => 'resolved', 'response' => $response, 'responded_by' => $teacher->id, 'responded_at' => now()->toIso8601String()];
        }
        $grade->save();
        $this->audit($teacher, 'courtroom.grade.review', $grade->simulation_session_id, [
            'student_id' => $grade->student_id, 'rubric_version' => $grade->rubric_version,
            'before' => $before, 'after' => $this->revisionSnapshot($grade),
        ]);
    }

    /** @param array<string, mixed> $input @param array<string, array{label:string,max:int}> $definitions @return array<string, float> */
    private function criterionPoints(array $input, array $definitions, string $field): array
    {
        $provided = array_filter($input, fn ($value): bool => $value !== null && $value !== '');
        if (array_diff_key($input, $definitions) || ($provided !== [] && array_diff_key($definitions, $provided))) {
            throw ValidationException::withMessages([$field => __('Tasdiqlangan rubrikaning har bir mezoniga ball kiriting yoki bo‘limni bahosiz qoldiring.')]);
        }
        $points = [];
        foreach ($provided as $key => $value) {
            $max = $definitions[$key]['max'];
            Validator::make([$field => $value], [$field => ['required', 'numeric', "between:0,$max", 'decimal:0,2']])->validate();
            $points[$key] = (float) $value;
        }

        return $points;
    }

    /** @param array<string, mixed> $data */
    public function approve(SimulationSession $session, User $teacher, array $data): void
    {
        $fields = ['summary', 'strengths', 'mistakes', 'knowledge_gaps', 'next_steps'];
        $input = Validator::make($data, [
            'version' => ['required', 'integer', 'min:1'], 'conclusion' => ['required', 'array:'.implode(',', $fields)],
            'conclusion.summary' => ['required', 'string', 'max:5000'],
            ...collect(array_slice($fields, 1))->mapWithKeys(fn (string $key): array => ['conclusion.'.$key => ['nullable', 'string', 'max:5000']])->all(),
        ])->validate();
        DB::transaction(function () use ($session, $teacher, $input): void {
            $locked = $this->lockForTeacher($session, $teacher);
            $state = $locked->settings['assessment'];
            if ($state['status'] === 'approved' && $state['conclusion'] == $input['conclusion'] &&
                in_array((int) $input['version'], [(int) $locked->lock_version, $state['approval_from_version'] ?? null], true)) {
                return;
            }
            $this->checkVersion($locked, $input['version']);
            abort_unless($state['status'] === 'teacher_reviewed', 409, __('Barcha ishtirokchilar bahosini tekshiring.'));
            $grades = $this->grades($locked);
            abort_if($grades->isEmpty() || $grades->contains(fn ($grade): bool => $grade->status !== 'teacher_reviewed'), 409);
            if ($grades->contains(fn ($grade): bool => ($grade->reassessment['status'] ?? null) === 'pending')) {
                throw ValidationException::withMessages(['reassessment_response' => __('Yakunlashdan oldin ochiq so‘rovga javob yozing.')]);
            }
            $outcome = $this->outcome($locked->settings['assessment_rubric'], $grades);
            foreach ($grades as $grade) {
                $side = match ($grade->role_key) {
                    'prosecutor', 'victim_advocate', 'victim' => 'prosecution',
                    'defense_advocate', 'defendant' => 'defense',
                    default => null,
                };
                $grade->update([
                    'status' => 'approved', 'approved_by' => $teacher->id, 'approved_at' => now(),
                    'simulation_performance' => $side !== null && $outcome['winner'] !== 'undetermined'
                        ? $outcome['teams'][$side]['score'] : null,
                ]);
                $this->syncGradebookEntries($locked, $grade, $teacher);
                $this->audit($teacher, 'courtroom.grade.approve', $locked->id, [
                    'student_id' => $grade->student_id, 'rubric_version' => $grade->rubric_version, 'grade' => $this->revisionSnapshot($grade),
                ]);
            }
            $this->saveState($locked, [...$state, 'status' => 'approved', 'outcome' => $outcome, 'conclusion' => $input['conclusion'],
                'approved_by' => $teacher->id, 'approved_at' => now()->toIso8601String(), 'approval_from_version' => $locked->lock_version]);
            $this->audit($teacher, 'courtroom.results.approve', $locked->id, [
                'rubric_version' => $locked->settings['assessment_rubric']['version'], 'scenario_version_id' => $locked->scenario_version_id,
                'outcome' => $outcome, 'conclusion' => $input['conclusion'],
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function returnForRevision(SimulationSession $session, User $teacher, array $data): void
    {
        $input = Validator::make($data, [
            'version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:1', 'max:3000'],
            'student_id' => ['nullable', 'integer'],
        ])->validate();
        DB::transaction(function () use ($session, $teacher, $input): void {
            $locked = $this->lockForTeacher($session, $teacher);
            $this->checkVersion($locked, $input['version']);
            $grades = $this->grades($locked);
            $target = isset($input['student_id']) ? (int) $input['student_id'] : null;
            if ($target !== null && ! $grades->has($target)) {
                throw ValidationException::withMessages(['student_id' => __('Talaba ushbu sessiyaga biriktirilmagan.')]);
            }
            foreach ($grades as $grade) {
                $status = $target === null || $target === $grade->student_id ? 'returned_for_revision' : ($grade->status === 'approved' ? 'teacher_reviewed' : $grade->status);
                $grade->update(['status' => $status, 'approved_at' => null, 'approved_by' => null, 'simulation_performance' => null]);
                if ($target === null || $target === $grade->student_id) {
                    GradebookEntry::query()
                        ->where('simulation_session_id', $locked->id)
                        ->where('student_id', $grade->student_id)
                        ->delete();
                }
            }
            $this->saveState($locked, [...$locked->settings['assessment'], 'status' => 'returned_for_revision',
                'reason' => $input['reason'], 'approved_at' => null, 'approved_by' => null, 'outcome' => null]);
            $this->audit($teacher, 'courtroom.results.return', $locked->id, ['reason' => $input['reason'], 'student_id' => $target]);
        }, 3);
    }

    public function requestReassessment(SimulationSession $session, User $student, string $message): void
    {
        abort_unless($student->hasRole(UserRole::Student), 403);
        $message = trim($message);
        Validator::make(['message' => $message], ['message' => ['required', 'string', 'max:3000']])->validate();
        DB::transaction(function () use ($session, $student, $message): void {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            $grade = CourtroomParticipantGrade::query()->where('simulation_session_id', $locked->id)->where('student_id', $student->id)->first();
            abort_unless($grade && $locked->participants()->where('user_id', $student->id)->exists(), 403);
            abort_unless($grade->status === 'approved', 409);
            if (($grade->reassessment['status'] ?? null) === 'pending') {
                abort_unless($grade->reassessment['message'] === $message, 409);

                return;
            }
            $grade->update(['reassessment' => ['message' => $message, 'requested_at' => now()->toIso8601String(), 'status' => 'pending']]);
            $this->audit($student, 'courtroom.grade.reassessment', $locked->id, ['student_id' => $student->id, 'message' => $message]);
            $locked->increment('lock_version');
        }, 3);
    }

    private function lockForTeacher(SimulationSession $session, User $teacher): SimulationSession
    {
        $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
        abort_unless($locked->isManagedGroupBy($teacher), 403);
        abort_unless($locked->status === 'completed' && isset($locked->settings['assessment']), 409);

        return $locked;
    }

    private function checkVersion(SimulationSession $session, int $version): void
    {
        abort_unless((int) $session->lock_version === $version, 409, __('Natijalar yangilangan. Sahifani qayta oching.'));
    }

    /** @return Collection<int, CourtroomParticipantGrade> */
    private function grades(SimulationSession $session): Collection
    {
        return CourtroomParticipantGrade::query()->where('simulation_session_id', $session->id)->orderBy('student_id')->get()->keyBy('student_id');
    }

    private function syncGradebookEntries(SimulationSession $session, CourtroomParticipantGrade $grade, User $teacher): void
    {
        $components = [
            'attendance' => match ($grade->attendance) {
                'present', 'late' => 100.0,
                'absent' => 0.0,
                default => null,
            },
            'classroom_activity' => $grade->activity_score === null ? null : (float) $grade->activity_score * 10,
            'simulation_performance' => $grade->simulation_performance,
            'individual_role_score' => $grade->individual_score,
            'teacher_final_grade' => $grade->teacher_final_grade,
        ];

        foreach ($grade->gradebook_columns as $column) {
            $score = $components[$column] ?? null;
            if ($score === null) {
                GradebookEntry::query()
                    ->where('simulation_session_id', $session->id)
                    ->where('student_id', $grade->student_id)
                    ->where('column_key', $column)
                    ->delete();

                continue;
            }

            GradebookEntry::query()->updateOrCreate([
                'course_id' => $grade->course_id,
                'student_id' => $grade->student_id,
                'simulation_session_id' => $session->id,
                'column_key' => $column,
            ], [
                'score' => round((float) $score, 2),
                'evaluator_id' => $teacher->id,
                'approved_at' => now(),
            ]);
        }

        GradebookEntry::query()
            ->where('simulation_session_id', $session->id)
            ->where('student_id', $grade->student_id)
            ->whereNotIn('column_key', $grade->gradebook_columns)
            ->delete();
    }

    /** @param array<string,mixed> $rubric @param Collection<int,CourtroomParticipantGrade> $grades @return array<string,mixed> */
    private function outcome(array $rubric, Collection $grades): array
    {
        return $this->engine->outcome($rubric, $grades->map(fn ($grade): array => $grade->only(['student_id', 'role_key', 'criteria', 'evidence', 'status']))->values()->all());
    }

    /** @return array<string,mixed> */
    private function revisionSnapshot(CourtroomParticipantGrade $grade): array
    {
        return $grade->only(['criteria', 'activity', 'evidence', 'attendance', 'individual_score', 'activity_score', 'teacher_final_grade', 'simulation_performance',
            'gradebook_columns', 'status', 'evaluator_id', 'approved_by', 'approved_at', 'comment', 'recommendation', 'strengths', 'improvements', 'reason', 'reassessment']);
    }

    /** @param array<string, mixed> $state */
    private function saveState(SimulationSession $session, array $state): void
    {
        $session->update(['settings' => [...$session->settings, 'assessment' => $state], 'lock_version' => $session->lock_version + 1]);
    }

    /** @param array<string, mixed> $properties */
    private function audit(User $user, string $action, int $sessionId, array $properties): void
    {
        EducationAuditLog::query()->create(['user_id' => $user->id, 'action' => $action,
            'subject_type' => 'simulation_session', 'subject_id' => $sessionId, 'properties' => $properties]);
    }
}
