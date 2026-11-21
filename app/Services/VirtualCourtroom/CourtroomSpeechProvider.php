<?php

namespace App\Services\VirtualCourtroom;

use App\Services\Ai\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CourtroomSpeechProvider
{
    public function __construct(private CourtroomTimedSpeechProvider $timedSpeech) {}

    public function connectRealtime(string $offer, string $locale, int $studentId): string
    {
        $session = [
            'type' => 'realtime',
            'model' => config('virtual_courtroom.voice.realtime_model'),
            'output_modalities' => ['text'],
            'audio' => [
                'input' => [
                    'transcription' => ['model' => 'gpt-realtime-whisper', 'language' => str_starts_with($locale, 'ru') ? 'ru' : 'uz'],
                    'turn_detection' => null,
                ],
            ],
        ];

        try {
            $client = $this->client()->withHeaders([
                'OpenAI-Safety-Identifier' => hash_hmac('sha256', (string) $studentId, config('app.key')),
            ]);

            if (app()->environment('testing')) {
                $response = $client
                    ->attach('sdp', rtrim($offer, "\r\n")."\r\n", null, ['Content-Type' => 'application/sdp'])
                    ->attach('session', json_encode($session, JSON_THROW_ON_ERROR), null, ['Content-Type' => 'application/json'])
                    ->post('https://api.openai.com/v1/realtime/calls');
            } else {
                $response = $client
                    ->withHeaders(['Content-Type' => 'application/sdp'])
                    ->withBody(rtrim($offer, "\r\n")."\r\n", 'application/sdp')
                    ->post('https://api.openai.com/v1/realtime/calls?model='.($session['model'] ?? 'gpt-4o-realtime-preview'));
            }
        } catch (ConnectionException) {
            throw new AiUnavailableException('Jonli ovozga ulanib bo‘lmadi. Matnli rejim mavjud.');
        }

        if (! $response->successful() || ! str_starts_with($response->body(), 'v=0') || strlen($response->body()) > 65536) {
            throw new AiUnavailableException('Jonli ovoz xizmatiga ulanish muvaffaqiyatsiz (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    public function transcribe(UploadedFile $audio, string $locale): string
    {
        $azure = config('virtual_courtroom.voice.azure', []);
        if (! empty($azure['enabled']) && ! empty($azure['key'])) {
            return $this->transcribeAzure($audio, $locale, $azure);
        }

        $extension = match ($audio->getMimeType()) {
            'audio/ogg', 'application/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'video/mp4' => 'mp4',
            default => 'webm',
        };
        $sttModel = config('virtual_courtroom.voice.stt_model');
        if (empty($sttModel) || in_array($sttModel, ['gpt-live-transcribe', 'default', 'whisper'], true)) {
            $sttModel = 'whisper-1';
        }

        try {
            $response = $this->client()->attach('file', $audio->getContent(), 'speech.'.$extension)
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $sttModel,
                    'language' => str_starts_with($locale, 'ru') ? 'ru' : 'uz',
                    'response_format' => 'json',
                ]);
        } catch (ConnectionException) {
            throw new AiUnavailableException('Nutqni aniqlash xizmatiga ulanib bo‘lmadi. Matnli rejimdan foydalaning.');
        }
        if (! $response->successful()) {
            throw new AiUnavailableException('Nutqni aniqlash xizmati javob bermadi (HTTP '.$response->status().').');
        }
        $text = $response->json('text');
        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 4000) {
            throw new AiUnavailableException('Nutqni aniqlash xizmati yaroqli transcript qaytarmadi.');
        }

        return trim($text);
    }

    /** @param array<string, mixed> $azure */
    private function transcribeAzure(UploadedFile $audio, string $locale, array $azure): string
    {
        $region = $azure['region'] ?: 'westus2';
        $endpoint = ! empty($azure['endpoint'])
            ? rtrim($azure['endpoint'], '/').'/speechtotext/transcriptions:transcribe'
            : "https://{$region}.api.cognitive.microsoft.com/speechtotext/transcriptions:transcribe";
        $endpoint .= '?api-version=2025-10-15';

        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $azure['key'],
                'Accept' => 'application/json',
            ])->connectTimeout(5)->timeout(40)->withoutRedirecting()
                ->attach('audio', $audio->getContent(), $audio->getClientOriginalName(), [
                    'Content-Type' => $audio->getMimeType() ?: 'application/octet-stream',
                ])
                ->attach('definition', json_encode([
                    'locales' => [str_starts_with($locale, 'ru') ? 'ru-RU' : 'uz-UZ'],
                ], JSON_THROW_ON_ERROR), null, ['Content-Type' => 'application/json'])
                ->post($endpoint);
        } catch (ConnectionException) {
            throw new AiUnavailableException('Azure Speech nutqni aniqlash xizmatiga ulanib bo‘lmadi. Matnli javob yuboring.');
        }

        if (! $response->successful()) {
            throw new AiUnavailableException('Azure Speech nutqni aniqlash xizmati javob bermadi (HTTP '.$response->status().').');
        }

        $text = collect($response->json('combinedPhrases', []))
            ->pluck('text')->filter(fn ($phrase): bool => is_string($phrase))->implode(' ');
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 4000) {
            throw new AiUnavailableException('Azure Speech yaroqli transcript qaytarmadi. Matnli javob yuboring.');
        }

        return $text;
    }

    /** @return array{audio: string, mime: string, visemes: list<array{start: float, end: float, shape: string}>} */
    public function synthesize(string $content, string $locale, ?string $roleKey = null): array
    {
        if (! config('virtual_courtroom.voice.server_speech_enabled')) {
            throw new AiUnavailableException('Server ovoz xizmati sozlanmagan. Matnli rejimdan foydalaning.');
        }

        $ttsProvider = config('virtual_courtroom.voice.tts_provider');
        $azure = config('virtual_courtroom.voice.azure', []);
        $azureActive = ($ttsProvider === 'azure' || ! empty($azure['enabled'])) && ! empty($azure['key']);

        if ($azureActive) {
            return $this->synthesizeAzure($content, $locale, $roleKey);
        }

        if ($ttsProvider === 'inworld') {
            return $this->timedSpeech->synthesize($content, $locale);
        }

        if (! in_array($ttsProvider, ['openai', 'azure', 'default'], true)) {
            throw new AiUnavailableException('Ovoz sintezi provayderi yaroqsiz sozlangan.');
        }

        return $this->synthesizeOpenAi($content, $locale);
    }

    /** @return array{audio: string, mime: string, visemes: list<array{start: float, end: float, shape: string}>} */
    public function synthesizeAzure(string $content, string $locale, ?string $roleKey = null): array
    {
        $azure = config('virtual_courtroom.voice.azure', []);
        $key = $azure['key'] ?? null;
        if (! $key) {
            throw new AiUnavailableException('Azure Speech API kaliti sozlanmagan.');
        }

        if (mb_strlen($content) > 4096) {
            throw new AiUnavailableException('Javob ovozga aylantirish uchun juda uzun. Matnni o‘qing.');
        }

        $region = $azure['region'] ?: 'westus2';
        $endpoint = ! empty($azure['endpoint'])
            ? rtrim($azure['endpoint'], '/').'/cognitiveservices/v1'
            : "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1";

        $isRussian = str_starts_with($locale, 'ru');
        $voice = $this->resolveAzureVoice($roleKey, $isRussian, $azure);
        $lang = $isRussian ? 'ru-RU' : 'uz-UZ';
        $escaped = htmlspecialchars($content, ENT_XML1, 'UTF-8');

        [$rate, $pitch] = $this->resolveAzureProsody($roleKey);
        $ssml = "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='{$lang}'>"
            ."<voice name='{$voice}'><prosody rate='{$rate}' pitch='{$pitch}'>"
            ."{$escaped}"
            .'</prosody></voice>'
            .'</speak>';

        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'Content-Type' => 'application/ssml+xml',
                'X-Microsoft-OutputFormat' => 'audio-16khz-128kbitrate-mono-mp3',
                'User-Agent' => 'HUQUQ-AI-Academy',
            ])->connectTimeout(5)->timeout(40)->withoutRedirecting()
                ->withBody($ssml, 'application/ssml+xml')
                ->post($endpoint);
        } catch (ConnectionException) {
            throw new AiUnavailableException('Azure Speech xizmatiga ulanib bo‘lmadi. Matnli javob saqlangan.');
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'audio/')) {
            throw new AiUnavailableException('Azure Speech xizmati yaroqli audio qaytarmadi (HTTP '.$response->status().').');
        }

        return ['audio' => $response->body(), 'mime' => 'audio/mpeg', 'visemes' => []];
    }

    private function resolveAzureVoice(?string $roleKey, bool $isRussian, array $azure): string
    {
        if ($isRussian) {
            $femaleRoles = ['victim', 'witness_1', 'witness_2', 'witness_3', 'claimant', 'clerk', 'secretary'];

            return in_array($roleKey, $femaleRoles, true)
                ? 'ru-RU-SvetlanaNeural'
                : 'ru-RU-DmitryNeural';
        }

        $maleVoice = $azure['male_voice'] ?: 'uz-UZ-SardorNeural';
        $femaleVoice = $azure['female_voice'] ?: 'uz-UZ-MadinaNeural';

        $femaleRoles = ['victim', 'witness_1', 'witness_2', 'witness_3', 'claimant', 'clerk', 'secretary'];
        if ($roleKey && in_array($roleKey, $femaleRoles, true)) {
            return $femaleVoice;
        }

        return $maleVoice;
    }

    /** @return array{string,string} */
    private function resolveAzureProsody(?string $roleKey): array
    {
        return match ($roleKey) {
            'judge' => ['-6%', '-2st'],
            'prosecutor' => ['+3%', '+1st'],
            'victim_advocate' => ['+1%', '+2st'],
            'victim' => ['-3%', '+1st'],
            'defense_advocate' => ['-1%', '-1st'],
            'defendant' => ['+4%', '+2st'],
            'witness_1' => ['-2%', '+2st'],
            'witness_2' => ['+2%', '-2st'],
            'witness_3' => ['+5%', '+3st'],
            default => ['+0%', '+0st'],
        };
    }

    /** @return array{audio: string, mime: string, visemes: list<array{start: float, end: float, shape: string}>} */
    private function synthesizeOpenAi(string $content, string $locale): array
    {
        if (mb_strlen($content) > 4096) {
            throw new AiUnavailableException('Javob ovozga aylantirish uchun juda uzun. Matnni o‘qing.');
        }

        $ttsModel = config('virtual_courtroom.voice.tts_model');
        if (empty($ttsModel) || in_array($ttsModel, ['gpt-4o-mini-tts', 'default'], true)) {
            $ttsModel = 'tts-1';
        }

        $ttsVoice = config('virtual_courtroom.voice.tts_voice', 'alloy');
        if (! in_array($ttsVoice, ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'], true)) {
            $ttsVoice = 'alloy';
        }

        try {
            $response = $this->client()->post('https://api.openai.com/v1/audio/speech', [
                'model' => $ttsModel,
                'voice' => $ttsVoice,
                'input' => $content,
                'response_format' => 'mp3',
            ]);
        } catch (ConnectionException) {
            throw new AiUnavailableException('Ovoz xizmatiga ulanib bo‘lmadi. Matnli javob saqlangan.');
        }
        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'audio/')) {
            throw new AiUnavailableException('Ovoz xizmati yaroqli audio qaytarmadi (HTTP '.$response->status().').');
        }

        return ['audio' => $response->body(), 'mime' => 'audio/mpeg', 'visemes' => []];
    }

    private function client(): PendingRequest
    {
        $key = config('virtual_courtroom.voice.api_key');
        if (! $key && rtrim((string) config('ai_tutor.base_url'), '/') === 'https://api.openai.com/v1') {
            $key = config('ai_tutor.api_key');
        }
        if (! config('virtual_courtroom.voice.server_speech_enabled') || ! $key) {
            throw new AiUnavailableException('Server ovoz xizmati sozlanmagan. Matnli rejimdan foydalaning.');
        }

        return Http::withToken($key)->connectTimeout(5)->timeout(40)->withoutRedirecting();
    }
}
