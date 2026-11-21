<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourtroomEvidenceAnnotation;
use App\Models\ScenarioEvidence;
use App\Models\SimulationSession;
use App\Services\VirtualCourtroom\SimulationStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourtroomEvidenceController extends Controller
{
    public function index(SimulationSession $session): JsonResponse
    {
        Gate::authorize('view', $session);
        $items = ScenarioEvidence::query()
            ->where('scenario_version_id', $session->scenario_version_id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ScenarioEvidence $evidence): bool => $evidence->isVisibleTo($session->selected_role_key))
            ->map(fn (ScenarioEvidence $evidence): array => $this->serialize($evidence))
            ->values();

        return response()->json(['evidence' => $items]);
    }

    public function show(Request $request, SimulationSession $session, ScenarioEvidence $evidence): JsonResponse|StreamedResponse
    {
        Gate::authorize('view', $session);
        $this->ensureVisible($session, $evidence);

        if (! $evidence->storage_path || $request->expectsJson()) {
            return response()->json(['evidence' => $this->serialize($evidence, true)]);
        }

        abort_unless(Storage::disk('local')->exists($evidence->storage_path), 404);
        $mime = Storage::disk('local')->mimeType($evidence->storage_path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'video/mp4', 'video/webm'], true), 415);

        return Storage::disk('local')->response($evidence->storage_path, $evidence->original_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function annotations(SimulationSession $session, ScenarioEvidence $evidence): JsonResponse
    {
        Gate::authorize('update', $session);
        $this->ensureVisible($session, $evidence);
        abort_unless(in_array($session->status, ['active', 'completed'], true), 409);
        $annotations = $session->evidenceAnnotations()->where('scenario_evidence_id', $evidence->id)
            ->orderBy('id')->get()->map(fn (CourtroomEvidenceAnnotation $annotation): array => $this->annotationData($annotation));

        return response()->json(['annotations' => $annotations], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function annotate(Request $request, SimulationSession $session, ScenarioEvidence $evidence): JsonResponse
    {
        Gate::authorize('update', $session);
        $this->ensureVisible($session, $evidence);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'note' => ['nullable', 'required_without:start_offset', 'string', 'max:1000'],
            'start_offset' => ['nullable', 'required_with:end_offset', 'integer', 'min:0'],
            'end_offset' => ['nullable', 'required_with:start_offset', 'integer', 'gt:start_offset'],
        ]);
        $start = $data['start_offset'] ?? null;
        $end = $data['end_offset'] ?? null;
        if ($start !== null) {
            $body = $evidence->annotatableText();
            if ($end > mb_strlen($body) || $end - $start > 2000 || trim(mb_substr($body, $start, $end - $start)) === '') {
                throw ValidationException::withMessages(['start_offset' => __('Dalil matnidan 2000 belgigacha bo‘lgan matnni tanlang.')]);
            }
        }

        $annotation = DB::transaction(function () use ($session, $evidence, $data, $start, $end): CourtroomEvidenceAnnotation {
            $session = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($session->status === 'active', 409, __('Mashg‘ulot yakunlangan.'));
            $notes = $session->evidenceAnnotations()->where('scenario_evidence_id', $evidence->id);
            $existing = (clone $notes)->firstWhere('idempotency_key', $data['idempotency_key']);
            if ($existing) {
                return $existing;
            }
            if ($notes->count() >= 100) {
                throw ValidationException::withMessages(['note' => __('Bu dalilda 100 ta qayd saqlandi. Yangi qayd uchun eskisini o‘chiring.')]);
            }

            return $session->evidenceAnnotations()->create([
                'scenario_evidence_id' => $evidence->id, 'idempotency_key' => $data['idempotency_key'],
                'start_offset' => $start, 'end_offset' => $end, 'note' => $data['note'] ?? null,
            ]);
        }, 3);

        return response()->json(['annotation' => $this->annotationData($annotation)], $annotation->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteAnnotation(Request $request, SimulationSession $session, ScenarioEvidence $evidence, int $annotation): Response|RedirectResponse
    {
        Gate::authorize('update', $session);
        $this->ensureVisible($session, $evidence);
        $session->evidenceAnnotations()->where('scenario_evidence_id', $evidence->id)->findOrFail($annotation)->delete();

        return $request->expectsJson() ? response()->noContent() : redirect()->route('student.courtroom.sessions.report', $session);
    }

    private function ensureVisible(SimulationSession $session, ScenarioEvidence $evidence): void
    {
        abort_unless(
            $evidence->scenario_version_id === $session->scenario_version_id
            && $evidence->status === 'active'
            && $evidence->isVisibleTo($session->selected_role_key),
            404,
        );
    }

    /** @return array{id: int, start_offset: int|null, end_offset: int|null, note: string|null} */
    private function annotationData(CourtroomEvidenceAnnotation $annotation): array
    {
        return ['id' => $annotation->id, 'start_offset' => $annotation->start_offset, 'end_offset' => $annotation->end_offset, 'note' => $annotation->note];
    }

    public function present(
        Request $request,
        SimulationSession $session,
        ScenarioEvidence $evidence,
        SimulationStateMachine $stateMachine,
    ): JsonResponse {
        Gate::authorize('update', $session);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $action = $stateMachine->act($session, $request->user(), [
            'action_type' => 'present_evidence',
            'content' => $data['content'],
            'evidence_id' => $evidence->id,
            'idempotency_key' => $data['idempotency_key'],
            'input_type' => 'text',
        ]);

        return response()->json(['action_id' => $action->id, 'status' => $action->validation_status]);
    }

    /** @return array<string, mixed> */
    private function serialize(ScenarioEvidence $evidence, bool $withBody = false): array
    {
        return [
            'id' => $evidence->id,
            'title' => $evidence->title,
            'type' => $evidence->type,
            'summary' => $evidence->summary,
            'body' => $withBody ? $evidence->body : null,
            'source' => $evidence->source_label,
            'version' => $evidence->version,
            'has_file' => filled($evidence->storage_path),
        ];
    }
}
