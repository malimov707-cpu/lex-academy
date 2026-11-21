<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleDriver implements AiDriver
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model,
    ) {}

    public function complete(array $system, array $messages): array
    {
        $model = $this->model;
        if (rtrim($this->baseUrl, '/') === 'https://api.openai.com/v1') {
            $model = match ($model) {
                'gpt-6.2-sol', 'gpt-6.2', 'sol' => 'gpt-4o-mini',
                'gpt-6.2-astra', 'astra' => 'gpt-4o',
                'gpt-live-1.1' => 'gpt-4o-mini',
                'gpt-live-transcribe' => 'whisper-1',
                default => $model,
            };
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ...array_map(fn (array $m): array => ['role' => 'system', 'content' => $m['content']], $system),
                ...$messages,
            ],
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->connectTimeout(10)
                ->withoutRedirecting()
                ->timeout(60)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);

        } catch (ConnectionException $exception) {
            throw new AiUnavailableException('AI servisiga ulanish imkoni bo‘lmadi.', code: 0);
        }

        if (! $response->successful()) {
            $code = $response->json('error.code');
            $type = $response->json('error.type');
            $quota = in_array($code, ['insufficient_quota', 'billing_hard_limit_reached', 'credit_balance_exhausted'], true) || in_array($type, ['insufficient_quota', 'billing_hard_limit_reached', 'credit_balance_exhausted'], true);
            $reason = match (true) {
                $code === 'credit_balance_exhausted' => 'OpenAI API krediti tugagan (credit_balance_exhausted). API billing hisobini to‘ldiring yoki krediti mavjud loyiha kalitini sozlang. Qayta so‘rov yuborish bu cheklovni yechmaydi.',
                $quota => 'Kvota yoki billing limiti tugagan. API loyihasining billing, kredit va foydalanish limitlarini tekshiring.',
                $response->status() === 401 => 'API autentifikatsiyasi rad etildi. API kaliti va loyiha ruxsatlarini tekshiring.',
                $response->status() === 403 => 'API yoki modelga kirish taqiqlangan. Loyiha va model ruxsatlarini tekshiring.',
                $response->status() === 404 => 'Model yoki endpoint topilmadi. Base URL va model nomini tekshiring.',
                $response->status() === 429 => 'So‘rovlar limiti yoki kvota cheklovi. API loyihasining limitlarini tekshirib, keyinroq qayta urinib ko‘ring.',
                $response->status() >= 500 => 'AI provayderida vaqtinchalik xato. Keyinroq qayta urinib ko‘ring.',
                default => 'So‘rov qabul qilinmadi. Endpoint va model mosligini tekshiring.',
            };
            throw new AiUnavailableException('AI servis xatosi: HTTP '.$response->status().'. '.$reason);
        }

        $body = $response->json();

        $content = $body['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || trim($content) === '') {
            throw new AiUnavailableException("AI javobi noto'g'ri formatda");
        }

        return [
            'content' => $content,
            'confidence' => null,
            'total_tokens' => is_int($body['usage']['total_tokens'] ?? null) ? $body['usage']['total_tokens'] : null,
        ];
    }
}
