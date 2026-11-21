<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AiTutorMessage;
use App\Models\AiTutorSession;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\AiDriverManager;
use App\Services\Ai\AiHealthChecker;
use App\Services\Ai\AITaskExecutor;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TutorContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiTutorController extends Controller
{
    public function __construct(
        private AiDriverManager $drivers,
        private TutorContextBuilder $context,
        private AITaskExecutor $taskExecutor,
    ) {}

    public function history(Request $request, Course $course, Lesson $lesson): View
    {
        $session = $this->findOrCreateSession($request->user(), $lesson);

        return view('student.tutor', [
            'course' => $course,
            'lesson' => $lesson,
            'session' => $session->load('messages'),
            'modes' => $this->modes(),
        ]);
    }

    public function send(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'mode' => ['nullable', 'in:explain,practice,question,quiz_mode,case_study,exam_prep,revision,document,summary,flashcards,study_plan'],
        ]);

        $student = $request->user();
        $session = $this->findOrCreateSession($student, $lesson, $data['mode'] ?? 'explain');

        return $this->processMessage($student, $session, $data['message'], $session->mode, $lesson);
    }

    /**
     * Foydalanuvchi seanslari ro'yxati (Sidebar uchun).
     */
    public function listSessions(Request $request): JsonResponse
    {
        $sessions = AiTutorSession::query()
            ->where('student_id', $request->user()->id)
            ->with(['lesson:id,title', 'course:id,title'])
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (AiTutorSession $s): array => [
                'id' => $s->id,
                'title' => $s->title ?? ($s->lesson ? $s->lesson->title : 'Yuridik Suhbat #'.$s->id),
                'mode' => $s->mode,
                'is_standalone' => $s->isStandalone(),
                'lesson_title' => $s->lesson?->title,
                'course_title' => $s->course?->title,
                'messages_count' => $s->messages_count,
                'updated_at' => $s->updated_at->diffForHumans(),
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Yangi mustaqil AI seansi yaratish.
     */
    public function storeSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'in:explain,practice,question,quiz_mode,case_study,exam_prep,revision,document,summary,flashcards,study_plan'],
            'course_id' => ['nullable', 'exists:courses,id'],
        ]);

        $mode = $data['mode'] ?? 'explain';
        $title = $data['title'] ?? ('Yangi Suhbat ('.$this->modes()[$mode].')');

        $session = AiTutorSession::query()->create([
            'student_id' => $request->user()->id,
            'title' => $title,
            'mode' => $mode,
            'course_id' => $data['course_id'] ?? null,
            'metadata' => [],
        ]);

        return response()->json([
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'mode' => $session->mode,
                'created_at' => $session->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Muayyan seans tafsilotlari va xabarlarini olish.
     */
    public function showSession(Request $request, AiTutorSession $session): JsonResponse
    {
        abort_unless($session->student_id === $request->user()->id, 403);

        $messages = $session->messages()
            ->oldest('id')
            ->get()
            ->map(fn (AiTutorMessage $m): array => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'confidence' => $m->confidence,
                'metadata' => $m->metadata,
                'created_at' => $m->created_at->format('H:i'),
            ]);
        $sessionMetadata = $session->metadata ?? [];
        unset($sessionMetadata['active_quiz']);

        return response()->json([
            'session' => [
                'id' => $session->id,
                'title' => $session->title ?? 'Yuridik Suhbat #'.$session->id,
                'mode' => $session->mode,
                'is_standalone' => $session->isStandalone(),
                'metadata' => $sessionMetadata,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Seans nomi yoki rejimini tahrirlash.
     */
    public function updateSession(Request $request, AiTutorSession $session): JsonResponse
    {
        abort_unless($session->student_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'in:explain,practice,question,quiz_mode,case_study,exam_prep,revision,document,summary,flashcards,study_plan'],
        ]);

        $session->update(array_filter($data, fn ($val) => $val !== null));
        $sessionMetadata = $session->metadata ?? [];
        unset($sessionMetadata['active_quiz']);

        return response()->json(['session' => [...$session->toArray(), 'metadata' => $sessionMetadata]]);
    }

    /**
     * Seansni o'chirish.
     */
    public function destroySession(Request $request, AiTutorSession $session): JsonResponse
    {
        abort_unless($session->student_id === $request->user()->id, 403);

        $session->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Seans ichida yangi xabar yuborish (mustaqil yoki darsga bog'langan).
     */
    public function sendMessage(Request $request, ?AiTutorSession $session = null): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'mode' => ['nullable', 'in:explain,practice,question,quiz_mode,case_study,exam_prep,revision,document,summary,flashcards,study_plan'],
            'session_id' => ['nullable', 'exists:ai_tutor_sessions,id'],
        ]);

        $student = $request->user();

        if (! $session && ! empty($data['session_id'])) {
            $session = AiTutorSession::query()->findOrFail($data['session_id']);
        }

        if (! $session) {
            $mode = $data['mode'] ?? 'explain';
            $session = AiTutorSession::query()->create([
                'student_id' => $student->id,
                'title' => mb_substr($data['message'], 0, 40),
                'mode' => $mode,
            ]);
        }

        abort_unless($session->student_id === $student->id, 403);

        if (! empty($data['mode']) && $session->mode !== $data['mode']) {
            $session->update(['mode' => $data['mode']]);
        }

        return $this->processMessage($student, $session, $data['message'], $session->mode, $session->lesson);
    }

    /**
     * Maxsus ta'limiy vazifani bajarish (Task Executor Action).
     */
    public function executeAction(Request $request, ?AiTutorSession $session = null): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string'],
            'params' => ['nullable', 'array'],
            'session_id' => ['nullable', 'exists:ai_tutor_sessions,id'],
        ]);

        $student = $request->user();

        if (! $session && ! empty($data['session_id'])) {
            $session = AiTutorSession::query()->findOrFail($data['session_id']);
        }

        if ($session) {
            abort_unless($session->student_id === $student->id, 403);
        }

        try {
            $result = $this->taskExecutor->execute($student, $data['action'], $data['params'] ?? [], $session);
        } catch (AiUnavailableException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503);
        }

        if ($session) {
            AiTutorMessage::query()->create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $result['content'],
                'confidence' => $result['confidence'],
                'metadata' => $result['metadata'] ?? [],
            ]);
        }

        return response()->json($result);
    }

    /**
     * AI tizimi ulanish holatini tekshirish (Health Check).
     */
    public function health(Request $request, AiHealthChecker $healthChecker): JsonResponse
    {
        return response()->json($healthChecker->check());
    }

    private function processMessage(
        User $student,
        AiTutorSession $session,
        string $message,
        string $mode,
        ?Lesson $lesson = null,
    ): JsonResponse {
        AiTutorMessage::query()->create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $message,
        ]);

        // Ta'lim vazifasini aniqlash
        $detectedAction = $this->taskExecutor->detectAction($message, $mode);

        // Agar maxsus action aniqlansa (masalan, test, flashcard, study plan, article)
        if (in_array($detectedAction, [
            AITaskExecutor::ACTION_CREATE_QUIZ,
            AITaskExecutor::ACTION_CREATE_FLASHCARDS,
            AITaskExecutor::ACTION_CREATE_STUDY_PLAN,
            AITaskExecutor::ACTION_CREATE_PRACTICE,
            AITaskExecutor::ACTION_CHECK_PROGRESS,
        ], true)) {
            try {
                $taskResult = $this->taskExecutor->execute($student, $detectedAction, [
                    'message' => $message,
                    'topic' => $message,
                    'lesson_title' => $lesson?->title,
                ], $session);
            } catch (AiUnavailableException $exception) {
                return response()->json(['error' => $exception->getMessage()], 503);
            }

            AiTutorMessage::query()->create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $taskResult['content'],
                'confidence' => $taskResult['confidence'],
                'metadata' => $taskResult['metadata'] ?? [],
            ]);

            return response()->json([
                'reply' => $taskResult['content'],
                'confidence' => $taskResult['confidence'],
                'mode' => $session->mode,
                'action' => $detectedAction,
                'metadata' => $taskResult['metadata'] ?? [],
            ]);
        }

        // Oddiy muloqot va tushuntirish rejimi: AI Driver chaqiruvi
        $system = $this->context->buildSystemMessages($student, $lesson, $session->mode, $message);

        $history = AiTutorMessage::query()
            ->where('session_id', $session->id)
            ->orderByDesc('id')
            ->limit(max(1, min(24, (int) config('ai_tutor.max_context_messages', 12))))
            ->get()
            ->reverse()
            ->map(fn (AiTutorMessage $m): array => ['role' => $m->role, 'content' => $m->content])
            ->all();

        try {
            $reply = $this->drivers->driver()->complete($system, $history);
            $sourceIds = $this->context->verifiedSourceIds($student, $lesson);
            if (! $this->context->hasOnlyAvailableReferences($reply['content'], $sourceIds)) {
                throw new AiUnavailableException('AI javobida taqdim etilmagan manba havolasi bor. Iltimos, qayta urinib ko‘ring.');
            }
            preg_match_all('/SOURCE-(\d+)/', $reply['content'], $sourceMatches);
            $citedSourceIds = array_map('intval', $sourceMatches[1] ?? []);
        } catch (AiUnavailableException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 503);
        }

        $assistantMessage = AiTutorMessage::query()->create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'confidence' => $reply['confidence'],
            'metadata' => [
                'action' => $detectedAction,
                'sources' => $this->context->sourceDetails(array_values(array_unique($citedSourceIds))),
                'source_status' => $citedSourceIds === [] ? 'insufficient_or_not_cited' : 'references_validated',
                'legal_review_status' => 'not_verified',
            ],
        ]);

        return response()->json([
            'reply' => $reply['content'],
            'confidence' => $reply['confidence'],
            'mode' => $session->mode,
            'action' => $detectedAction,
            'metadata' => $assistantMessage->metadata,
        ]);
    }

    /**
     * Foydalanuvchi faqat o'z dars sessiyasini oladi (data izolyatsiyasi).
     */
    private function findOrCreateSession(User $student, Lesson $lesson, ?string $mode = null): AiTutorSession
    {
        $session = AiTutorSession::query()->firstOrCreate(
            ['student_id' => $student->id, 'lesson_id' => $lesson->id],
            [
                'mode' => $mode ?? 'explain',
                'title' => $lesson->title,
                'course_id' => $lesson->module?->course_id,
            ],
        );

        if ($mode !== null && $session->mode !== $mode) {
            $session->update(['mode' => $mode]);
        }

        return $session;
    }

    /**
     * @return array<string, string>
     */
    public function modes(): array
    {
        return [
            'explain' => 'Tushuntirish',
            'quiz_mode' => 'Test',
            'practice' => 'Amaliyot',
            'document' => 'Hujjat tahlili',
            'summary' => 'Konspekt',
            'flashcards' => 'Flashcards',
            'exam_prep' => 'Imtihonga tayyorgarlik',
            'study_plan' => 'Shaxsiy reja',
            'question' => 'Savol-javob',
            'case_study' => 'Kazus',
            'revision' => 'Takrorlash',
        ];
    }
}
