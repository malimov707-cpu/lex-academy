<?php

namespace App\Console\Commands;

use App\Services\Ai\AiDriverManager;
use App\Services\Ai\AiUnavailableException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CheckAiConnection extends Command
{
    protected $signature = 'academy:check-ai';

    protected $description = 'Verify the configured OpenAI-compatible provider without sending student data';

    public function handle(AiDriverManager $drivers): int
    {
        foreach (['api_key', 'base_url', 'model'] as $field) {
            if (! config('ai_tutor.'.$field)) {
                $this->error('Missing AI_TUTOR_'.strtoupper($field).'. Configure the environment and clear the config cache.');

                return self::FAILURE;
            }
        }
        try {
            $metadata = Http::withToken(config('ai_tutor.api_key'))
                ->connectTimeout(10)->timeout(20)->withoutRedirecting()->acceptJson()
                ->get(rtrim(config('ai_tutor.base_url'), '/').'/models/'.rawurlencode(config('ai_tutor.model')));
            if ($metadata->successful() && $metadata->json('id') === config('ai_tutor.model')) {
                $this->info('Authentication and model metadata: OK.');
            } else {
                $this->warn('Model metadata check: HTTP '.$metadata->status().'. Chat request will verify actual availability.');
            }

            $reply = $drivers->driver()->complete(
                [['role' => 'system', 'content' => 'This is a connection check. Reply with OK only.']],
                [['role' => 'user', 'content' => 'Connection check.']],
            );
            if (trim($reply['content']) === '') {
                $this->error('The provider returned an empty response.');

                return self::FAILURE;
            }
        } catch (ConnectionException $exception) {
            $this->error('AI connection or timeout failure. Check endpoint and network access.');

            return self::FAILURE;
        } catch (AiUnavailableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('AI connection successful. No student data was sent.');

        return self::SUCCESS;
    }
}
