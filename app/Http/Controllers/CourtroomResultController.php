<?php

namespace App\Http\Controllers;

use App\Models\SimulationSession;
use App\Services\VirtualCourtroom\GroupAssessmentWorkflow;
use App\Services\VirtualCourtroom\GroupResultReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourtroomResultController extends Controller
{
    public function __construct(private GroupResultReport $reports, private GroupAssessmentWorkflow $workflow) {}

    public function show(Request $request, SimulationSession $session): Response
    {
        $report = $this->reports->forViewer($session, $request->user());
        if ($request->expectsJson()) {
            return response()->json($report);
        }
        $format = $request->validate(['format' => ['nullable', 'in:csv,pdf']])['format'] ?? null;
        if ($format) {
            abort_unless($report['teacher'] || $report['published'], 403);

            return $this->export($report, $format);
        }

        return response()->view('student.courtroom.group-results', ['report' => $report]);
    }

    public function individual(Request $request, SimulationSession $session): Response
    {
        $report = $this->reports->forViewer($session, $request->user());

        return response()->json(['status' => $report['status'], 'scores' => $report['people']]);
    }

    public function teams(Request $request, SimulationSession $session): Response
    {
        $report = $this->reports->forViewer($session, $request->user());

        return response()->json(['status' => $report['status'], 'outcome' => $report['outcome']]);
    }

    public function grade(Request $request, SimulationSession $session): Response
    {
        $data = $request->only(['version', 'grades']);
        if (! $request->isJson() && is_array($data['grades'] ?? null)) {
            foreach ($data['grades'] as &$row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['activity', 'criteria'] as $field) {
                    if (is_array($row[$field] ?? [])) {
                        $row[$field] = array_filter($row[$field] ?? [], fn ($score) => $score !== null && $score !== '');
                    }
                }
                if (is_array($row['reference_text'] ?? null)) {
                    $row['evidence'] = [];
                    foreach ($row['reference_text'] as $criterion => $refs) {
                        if (is_string($refs) && trim($refs) !== '') {
                            $row['evidence'][$criterion] = preg_split('/[\s,]+/', trim($refs), flags: PREG_SPLIT_NO_EMPTY);
                        }
                    }
                    unset($row['reference_text']);
                }
            }
            unset($row);
        }
        $this->workflow->review($session, $request->user(), $data);

        return $this->saved($request, $session);
    }

    public function approve(Request $request, SimulationSession $session): Response
    {
        $this->workflow->approve($session, $request->user(), $request->only(['version', 'conclusion']));

        return $this->saved($request, $session);
    }

    public function revise(Request $request, SimulationSession $session): Response
    {
        $this->workflow->returnForRevision($session, $request->user(), $request->only(['version', 'reason']));

        return $this->saved($request, $session);
    }

    public function reassess(Request $request, SimulationSession $session): Response
    {
        $message = $request->validate(['message' => ['required', 'string', 'max:2000']])['message'];
        $this->workflow->requestReassessment($session, $request->user(), $message);

        return $this->saved($request, $session);
    }

    private function saved(Request $request, SimulationSession $session): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'version' => $session->fresh()->lock_version]);
        }

        return redirect()->route('courtroom.results.show', $session)->with('status', __('Saqlandi.'));
    }

    /** @param array<string, mixed> $report */
    private function export(array $report, string $format): Response
    {
        if ($format === 'pdf') {
            return response()->view('student.courtroom.results-print', ['report' => $report]);
        }

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $write = function (array $cells) use ($stream): void {
                $safe = array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value, $cells);
                fputcsv($stream, $safe, escape: '');
            };
            $write([__('Ssenariy'), $report['session']['scenario'], __('Guruh'), $report['session']['group']]);
            $write([__('Holat'), GroupResultReport::statusLabel($report['status']), __('Bahs natijasi'), GroupResultReport::winnerLabel($report['outcome']['winner'] ?? 'undetermined')]);
            $write([__('Talaba'), __('Rol'), __('Individual ball'), __('Akademik faollik'), __('AI tavsiyasi'), __('O‘qituvchi bahosi'), __('Izoh'), __('Holat')]);
            foreach ($report['people'] as $person) {
                $grade = $person['grade'];
                $write([$person['name'], $person['role'], $grade['individual_score'] ?? null, $grade['activity_score'] ?? null,
                    $grade['ai_recommendation']['score'] ?? null, $grade['teacher_final_grade'] ?? null, $grade['comment'] ?? null,
                    GroupResultReport::statusLabel($grade['status'] ?? 'preliminary')]);
            }
            fclose($stream);
        }, 'sud-natijalari-'.$report['session']['id'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
