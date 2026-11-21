<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoiceSessionRequest;
use App\Http\Requests\StoreVoiceTranscriptRequest;
use App\Models\SimulationSession;
use App\Models\VoiceSession;
use App\Models\VoiceTranscriptSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CourtroomVoiceController extends Controller
{
    public function store(StoreVoiceSessionRequest $request, SimulationSession $session): JsonResponse
    {
        Gate::authorize('update', $session);
        abort_unless($session->status === 'active', 409, 'Simulyatsiya faol emas.');
        abort_unless(config('virtual_courtroom.voice.browser_speech_enabled') || config('virtual_courtroom.voice.server_speech_enabled'), 503, 'Voice rejimi serverda yoqilmagan. Text rejimidan foydalaning.');

        $voiceSession = DB::transaction(function () use ($request, $session): VoiceSession {
            $lockedSession = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === 'active', 409, 'Simulyatsiya faol emas.');
            $lockedSession->voiceSessions()->where('status', 'active')->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);

            return $lockedSession->voiceSessions()->create([
                'student_id' => $request->user()->id,
                'provider' => config('virtual_courtroom.voice.server_speech_enabled') ? 'openai' : 'browser',
                'status' => 'active',
                'transcript_consent' => $request->boolean('transcript_consent'),
                'audio_recording' => false,
                'consented_at' => now(),
                'metadata' => ['capabilities' => $request->validated('capabilities', [])],
            ]);
        }, 3);

        return response()->json([
            'voice_session' => [
                'id' => $voiceSession->id,
                'status' => $voiceSession->status,
                'provider' => $voiceSession->provider,
                'transport' => $voiceSession->provider === 'openai' ? 'server' : 'browser',
                'transcript_consent' => $voiceSession->transcript_consent,
                'audio_recording' => false,
            ],
            'privacy' => [
                'raw_audio_stored' => false,
                'retention_days' => config('virtual_courtroom.voice.transcript_retention_days'),
            ],
        ], 201);
    }

    public function segment(
        StoreVoiceTranscriptRequest $request,
        SimulationSession $session,
        VoiceSession $voiceSession,
    ): JsonResponse {
        Gate::authorize('update', $session);
        $this->authorizeVoiceSession($request->user()->id, $session, $voiceSession);
        abort_unless($voiceSession->transcript_consent, 409, 'Transcriptni saqlash uchun rozilik berilmagan.');

        $segment = DB::transaction(function () use ($request, $session, $voiceSession): VoiceTranscriptSegment {
            $lockedSession = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            $lockedVoice = VoiceSession::query()->lockForUpdate()->findOrFail($voiceSession->id);
            abort_unless($lockedSession->status === 'active', 409, 'Simulyatsiya faol emas.');
            abort_unless($lockedVoice->status === 'active', 409, 'Ovozli sessiya yakunlangan.');
            $this->authorizeVoiceSession($request->user()->id, $lockedSession, $lockedVoice);
            abort_unless($lockedVoice->transcript_consent, 409, 'Transcriptni saqlash uchun rozilik berilmagan.');

            return $lockedVoice->segments()->create([
                'sequence' => ((int) $lockedVoice->segments()->max('sequence')) + 1,
                'speaker_role_key' => $lockedSession->selected_role_key,
                ...$request->safe()->only(['content', 'is_final', 'started_at_ms', 'ended_at_ms']),
            ]);
        }, 3);

        return response()->json(['segment' => ['id' => $segment->id, 'sequence' => $segment->sequence]], 201);
    }

    public function destroy(SimulationSession $session, VoiceSession $voiceSession): JsonResponse
    {
        Gate::authorize('update', $session);
        $this->authorizeVoiceSession(request()->user()->id, $session, $voiceSession);
        VoiceSession::query()->whereKey($voiceSession->id)->where('status', 'active')
            ->update(['status' => 'ended', 'ended_at' => now()]);

        return response()->json(['status' => 'ended']);
    }

    private function authorizeVoiceSession(int $userId, SimulationSession $session, VoiceSession $voiceSession): void
    {
        abort_unless(
            $voiceSession->simulation_session_id === $session->id
            && $voiceSession->student_id === $userId,
            404,
        );
    }
}
