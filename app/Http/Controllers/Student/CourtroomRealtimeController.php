<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SimulationSession;
use App\Models\VoiceSession;
use App\Services\Ai\AiUnavailableException;
use App\Services\VirtualCourtroom\CourtroomSpeechProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourtroomRealtimeController extends Controller
{
    public function __invoke(Request $request, SimulationSession $session, VoiceSession $voiceSession, CourtroomSpeechProvider $speech): JsonResponse
    {
        Gate::authorize('update', $session);
        abort_unless($voiceSession->simulation_session_id === $session->id
            && $voiceSession->student_id === $request->user()->id, 404);
        abort_unless($session->status === 'active' && $session->started_at
            && $session->started_at->gt(now()->subMinutes(max(1, (int) config('virtual_courtroom.session.max_minutes')))), 409);
        abort_unless($voiceSession->status === 'active' && $voiceSession->consented_at && $voiceSession->provider === 'openai', 409);
        abort_unless(config('virtual_courtroom.voice.mode') === 'realtime' && config('virtual_courtroom.voice.server_speech_enabled'), 503, 'Jonli ovoz rejimi o‘chiq.');

        $data = $request->validate([
            'sdp' => ['required', 'string', 'max:32768', 'starts_with:v=0'],
            'locale' => ['required', 'in:uz,uz-Latn,uz-Cyrl,ru,ru-RU'],
        ]);

        try {
            $answer = $speech->connectRealtime($data['sdp'], $data['locale'], $request->user()->id);
        } catch (AiUnavailableException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503, ['Cache-Control' => 'private, no-store']);
        }

        return response()->json(['sdp' => $answer], 200, ['Cache-Control' => 'private, no-store']);
    }
}
