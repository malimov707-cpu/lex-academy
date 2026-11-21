<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\CourtroomParticipantGrade;
use App\Models\EducationGroup;
use App\Models\SimulationSession;
use App\Models\User;
use App\Services\VirtualCourtroom\GroupResultReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class CourtroomResultHistoryController extends Controller
{
    public function group(Request $request, EducationGroup $group): Response
    {
        $viewer = $request->user();
        abort_unless($viewer->isAdmin() || ($viewer->hasRole(UserRole::Teacher) && $group->course?->isManagedBy($viewer)), 403);
        $sessions = SimulationSession::query()->groupVisibleTo($viewer)->where('education_group_id', $group->id)
            ->where('status', 'completed')->with('scenario:id,title')->latest('completed_at')->orderByDesc('id');
        $page = $request->has('format') ? null : $sessions->paginate(25)->withQueryString();
        $items = $page?->getCollection() ?? $sessions->get();
        $averages = CourtroomParticipantGrade::query()->whereIn('simulation_session_id', $items->modelKeys())
            ->where('status', 'approved')->groupBy('simulation_session_id')
            ->selectRaw('simulation_session_id, AVG(individual_score) AS individual_average, AVG(activity_score) AS activity_average')
            ->get()->keyBy('simulation_session_id');
        $rows = $items->map(function ($session) use ($averages) {
            $assessment = $session->settings['assessment'] ?? [];
            $official = ($assessment['status'] ?? null) === 'approved';
            $average = $official ? $averages->get($session->id) : null;

            return ['session_id' => $session->id, 'date' => $session->completed_at?->toDateString(), 'scenario' => $session->scenario->title,
                'status' => $assessment['status'] ?? 'preliminary', 'role' => null,
                'winner' => $official ? ($assessment['outcome']['winner'] ?? 'undetermined') : null,
                'individual' => $average?->individual_average, 'activity' => $average?->activity_average, 'final' => null];
        });

        return $this->respond($request, $group->name, $rows, $page, true, true);
    }

    public function student(Request $request, User $student): Response
    {
        $viewer = $request->user();
        $own = $viewer->hasRole(UserRole::Student) && $viewer->is($student);
        abort_unless($student->hasRole(UserRole::Student), 404);
        $scope = SimulationSession::query()->groupVisibleTo($viewer);
        abort_unless($own || $viewer->isAdmin() || ($viewer->hasRole(UserRole::Teacher)
            && (clone $scope)->whereHas('participants', fn (Builder $query) => $query->where('user_id', $student->id))->exists()), 403);
        $grades = CourtroomParticipantGrade::query()->where('student_id', $student->id)->where('status', 'approved')
            ->whereIn('simulation_session_id', $scope->select('id'))->with(['session.scenario:id,title'])
            ->orderByDesc(SimulationSession::select('completed_at')->whereColumn('simulation_sessions.id', 'courtroom_participant_grades.simulation_session_id'))
            ->orderByDesc('id');
        $page = $request->has('format') ? null : $grades->paginate(25)->withQueryString();
        $items = $page?->getCollection() ?? $grades->get();
        $rows = $items->map(fn ($grade) => ['session_id' => $grade->simulation_session_id, 'date' => $grade->session->completed_at?->toDateString(),
            'scenario' => $grade->session->scenario->title, 'status' => $grade->status, 'role' => GroupResultReport::roleLabel($grade->role_key),
            'winner' => $grade->session->settings['assessment']['outcome']['winner'] ?? 'undetermined',
            'individual' => $grade->individual_score, 'activity' => $grade->activity_score, 'final' => $grade->teacher_final_grade]);

        return $this->respond($request, $student->name, $rows, $page, ! $own, false);
    }

    private function respond(Request $request, string $title, Collection $rows, ?LengthAwarePaginator $page, bool $teacher, bool $group): Response
    {
        $format = $request->validate(['format' => ['nullable', 'in:csv,pdf']])['format'] ?? null;
        if ($request->expectsJson()) {
            return response()->json(['title' => $title, 'data' => $rows, 'next_page_url' => $page?->nextPageUrl()]);
        }
        $columns = [__('Sana'), __('Ssenariy'), __('Bahs natijasi'), __('Holat'), __('Rol'),
            $group ? __('O‘rtacha individual ball') : __('Individual ball'), __('Akademik faollik'), __('O‘qituvchi bahosi')];
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($columns, $rows): void {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, $columns, escape: '');
                foreach ($rows as $row) {
                    $cells = [$row['date'], $row['scenario'], $row['winner'] ? GroupResultReport::winnerLabel($row['winner']) : '',
                        GroupResultReport::statusLabel($row['status']), $row['role'], $row['individual'], $row['activity'], $row['final']];
                    fputcsv($file, array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value, $cells), escape: '');
                }
                fclose($file);
            }, 'sud-mashgulotlari-tarixi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->view('student.courtroom.result-history', compact('title', 'rows', 'page', 'teacher', 'group', 'format'));
    }
}
