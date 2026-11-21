<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtroomAudioRequest;
use App\Models\SessionTurn;
use App\Models\SimulationSession;
use App\Models\VoiceSession;
use App\Services\Ai\AiUnavailableException;
use App\Services\VirtualCourtroom\CourtroomSpeechProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourtroomSpeechController extends Controller
{
    public function transcribe(StoreCourtroomAudioRequest $request, SimulationSession $session, VoiceSession $voiceSession, CourtroomSpeechProvider $speech): JsonResponse
    {
        $this->authorizeVoice($session, $voiceSession);
        try {
            $content = $speech->transcribe($request->file('audio'), $request->validated('locale'));
        } catch (AiUnavailableException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503);
        }

        return response()->json(['content' => $content], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function speak(Request $request, SimulationSession $session, VoiceSession $voiceSession, SessionTurn $turn, CourtroomSpeechProvider $speech): Response|JsonResponse
    {
        $this->authorizeVoice($session, $voiceSession);
        abort_unless($turn->simulation_session_id === $session->id && $turn->speaker_type === 'ai', 404);
        try {
            $speechAudio = $speech->synthesize(
                $turn->content,
                $turn->metadata['locale'] ?? app()->getLocale(),
                $turn->speaker_role_key
            );
        } catch (AiUnavailableException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503);
        }

        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Vary' => 'Accept'];
        if ($request->wantsJson() && $speechAudio['visemes'] !== []) {
            return response()->json([
                'audio_base64' => base64_encode($speechAudio['audio']), 'mime' => $speechAudio['mime'], 'visemes' => $speechAudio['visemes'],
            ], 200, $headers);
        }

        return response($speechAudio['audio'], 200, [...$headers, 'Content-Type' => $speechAudio['mime']]);
    }

    private function authorizeVoice(SimulationSession $session, VoiceSession $voiceSession): void
    {
        Gate::authorize('update', $session);
        abort_unless($session->status === 'active', 409);
        $userId = auth()->id() ?? request()->user()?->id;
        abort_unless($voiceSession->simulation_session_id === $session->id
            && $userId && $voiceSession->student_id === $userId, 404);
        abort_unless($voiceSession->status === 'active' && $voiceSession->consented_at && in_array($voiceSession->provider, ['openai', 'azure', 'server'], true), 409);
    }
}
