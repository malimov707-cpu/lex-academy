<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SessionTurn;
use App\Models\SimulationAsset;
use App\Models\VoiceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourtroomAssetController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole(UserRole::AcademyAdmin, UserRole::SuperAdmin), 403);
        $metrics = SessionTurn::query()->where('speaker_type', 'ai')->latest('id')->limit(500)->get(['metadata']);
        $reportedTokens = $metrics->pluck('metadata.total_tokens')->filter(fn ($value): bool => is_int($value));
        $voiceSessions = VoiceSession::query()->where('created_at', '>=', now()->subDays(30))
            ->select('provider')->selectRaw('COUNT(*) AS sessions')->groupBy('provider')->pluck('sessions', 'provider');

        return view('admin.courtroom.assets', [
            'assets' => SimulationAsset::query()->orderBy('id')->get(),
            'health' => [
                'text_configured' => filled(config('ai_tutor.api_key')),
                'server_voice_enabled' => (bool) config('virtual_courtroom.voice.server_speech_enabled'),
                'voice_mode' => config('virtual_courtroom.voice.mode'),
                'stt_model' => config('virtual_courtroom.voice.stt_model'),
                'tts_model' => config('virtual_courtroom.voice.tts_model'),
                'retention_days' => config('virtual_courtroom.voice.transcript_retention_days'),
                'raw_audio_stored' => false,
                'sample_count' => $metrics->count(),
                'average_latency_ms' => $metrics->pluck('metadata.duration_ms')->filter()->avg(),
                'total_tokens' => $reportedTokens->isNotEmpty() ? $reportedTokens->sum() : null,
                'token_reported_responses' => $reportedTokens->count(),
                'browser_voice_sessions' => (int) $voiceSessions->get('browser', 0),
                'server_voice_sessions' => (int) $voiceSessions->get('openai', 0),
            ],
        ]);
    }

    public function update(Request $request, SimulationAsset $asset): RedirectResponse
    {
        abort_unless($request->user()->hasRole(UserRole::AcademyAdmin, UserRole::SuperAdmin), 403);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        if ($request->boolean('enabled')) {
            abort_unless(str_starts_with($asset->path, 'courtroom/') && ! str_contains($asset->path, '..'), 422);
            $path = public_path($asset->path);
            abort_unless(is_file($path) && hash_equals($asset->checksum, hash_file('sha256', $path)), 422);
        }
        $asset->update($data);

        return back()->with('status', __('3D asset holati saqlandi.'));
    }
}
