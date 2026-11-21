<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AiHealthChecker
{
    /**
     * @return array{
     *     status: 'CONNECTED'|'DISCONNECTED'|'INVALID_API_KEY'|'MODEL_UNAVAILABLE'|'RATE_LIMITED'|'PROVIDER_ERROR'|'TIMEOUT',
     *     provider: string,
     *     model: ?string,
     *     latency_ms: ?int,
     *     message: string,
     *     checked_at: string
     * }
     */
    public function check(): array
    {
        $config = config('ai_tutor', []);
        $apiKey = $config['api_key'] ?? null;
        $baseUrl = $config['base_url'] ?? null;
        $model = $config['model'] ?? null;
        $provider = $config['provider'] ?? 'none';
        $now = now()->toIso8601String();

        if (empty($apiKey) || empty($baseUrl)) {
            return [
                'status' => 'DISCONNECTED',
                'provider' => $provider,
                'model' => $model,
                'latency_ms' => null,
                'message' => 'AI API kaliti yoki URL manzili sozlanmagan.',
                'checked_at' => $now,
            ];
        }

        $startTime = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(5)
                ->timeout(10)
                ->acceptJson()
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'salom'],
                    ],
                    'max_tokens' => 5,
                ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'status' => 'CONNECTED',
                    'provider' => $provider,
                    'model' => $model,
                    'latency_ms' => $latencyMs,
                    'message' => 'AI provayderi faol va so‘rovlarga muvaffaqiyatli javob bermoqda.',
                    'checked_at' => $now,
                ];
            }

            $status = match ($response->status()) {
                401 => 'INVALID_API_KEY',
                404 => 'MODEL_UNAVAILABLE',
                429 => 'RATE_LIMITED',
                default => 'PROVIDER_ERROR',
            };

            $errorMessage = $response->json('error.message')
                ?? ('HTTP '.$response->status().' - '.$response->reason());

            return [
                'status' => $status,
                'provider' => $provider,
                'model' => $model,
                'latency_ms' => $latencyMs,
                'message' => $errorMessage,
                'checked_at' => $now,
            ];
        } catch (ConnectionException $e) {
            return [
                'status' => 'TIMEOUT',
                'provider' => $provider,
                'model' => $model,
                'latency_ms' => null,
                'message' => 'AI serveriga ulanish vaqti tugadi (Connection Timeout).',
                'checked_at' => $now,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'PROVIDER_ERROR',
                'provider' => $provider,
                'model' => $model,
                'latency_ms' => null,
                'message' => $e->getMessage(),
                'checked_at' => $now,
            ];
        }
    }
}
