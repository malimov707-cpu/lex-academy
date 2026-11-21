<?php

namespace App\Services\VirtualCourtroom;

use App\Services\Ai\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CourtroomTimedSpeechProvider
{
    private const RIG_SHAPES = ['sil' => 'sil', 'PP' => 'PP', 'FF' => 'FF', 'TH' => 'TH', 'DD' => 'DD', 'kk' => 'kk', 'CH' => 'CH', 'SS' => 'SS', 'nn' => 'nn', 'RR' => 'RR', 'aa' => 'aa', 'E' => 'E', 'ih' => 'I', 'oh' => 'O', 'ou' => 'U'];

    /** @return array{audio: string, mime: string, visemes: list<array{start: float, end: float, shape: string}>} */
    public function synthesize(string $content, string $locale): array
    {
        $key = config('virtual_courtroom.voice.inworld_api_key');
        $voice = config('virtual_courtroom.voice.inworld_voice');
        if (! is_string($key) || trim($key) === '' || ! is_string($voice) || trim($voice) === '') {
            throw new AiUnavailableException('Inworld ovoz kaliti yoki ovoz identifikatori sozlanmagan.');
        }
        if (strlen(mb_convert_encoding($content, 'UTF-16LE', 'UTF-8')) > 4000) {
            throw new AiUnavailableException('Javob ovozga aylantirish uchun juda uzun. Matnni o‘qing.');
        }
        try {
            $response = Http::withToken($key, 'Basic')->acceptJson()->connectTimeout(5)->timeout(40)->withoutRedirecting()
                ->post('https://api.inworld.ai/tts/v1/voice', [
                    'text' => $content,
                    'voiceId' => $voice,
                    'modelId' => 'inworld-tts-2',
                    'language' => str_starts_with($locale, 'ru') ? 'ru-RU' : 'uz-UZ',
                    'audioConfig' => ['audioEncoding' => 'LINEAR16', 'sampleRateHertz' => 24000],
                    'lipsyncConfig' => ['visemeSet' => 'OPENXR'],
                ]);
        } catch (ConnectionException) {
            throw new AiUnavailableException('Inworld ovoz xizmatiga ulanib bo‘lmadi. Matnli javob mavjud.');
        }
        if (! $response->successful()) {
            throw new AiUnavailableException('Inworld ovoz xizmati javob bermadi (HTTP '.$response->status().').');
        }

        $encoded = $response->json('audioContent');
        $audio = is_string($encoded) && strlen($encoded) <= 16 * 1024 * 1024 ? base64_decode($encoded, true) : false;
        if ($audio === false || strlen($audio) < 44 || ! str_starts_with($audio, 'RIFF') || substr($audio, 8, 4) !== 'WAVE') {
            throw new AiUnavailableException('Inworld ovoz xizmati yaroqli WAV qaytarmadi.');
        }

        return ['audio' => $audio, 'mime' => 'audio/wav', 'visemes' => $this->timings($response->json('timestampInfo.lipsyncAlignment'))];
    }

    /** @return list<array{start: float, end: float, shape: string}> */
    private function timings(mixed $alignment): array
    {
        $shapes = $alignment['visemes'] ?? null;
        $starts = $alignment['visemeStartTimeSeconds'] ?? null;
        $ends = $alignment['visemeEndTimeSeconds'] ?? null;
        if (! is_array($shapes) || ! is_array($starts) || ! is_array($ends)
            || ! array_is_list($shapes) || ! array_is_list($starts) || ! array_is_list($ends)
            || count($shapes) !== count($starts) || count($shapes) !== count($ends) || count($shapes) > 4000) {
            return [];
        }

        $spans = [];
        $previousEnd = 0.0;
        foreach ($shapes as $index => $shape) {
            $start = $starts[$index];
            $end = $ends[$index];
            if (! is_string($shape) || ! isset(self::RIG_SHAPES[$shape]) || (! is_int($start) && ! is_float($start))
                || (! is_int($end) && ! is_float($end)) || ! is_finite($start) || ! is_finite($end)
                || $start < 0 || $start < $previousEnd - .001 || $end <= max($start, $previousEnd) || $end > 300) {
                return [];
            }
            $spans[] = ['start' => (float) max($start, $previousEnd), 'end' => (float) $end, 'shape' => self::RIG_SHAPES[$shape]];
            $previousEnd = $end;
        }

        return $spans;
    }
}
