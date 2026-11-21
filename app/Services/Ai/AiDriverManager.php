<?php

namespace App\Services\Ai;

class AiDriverManager
{
    public function driver(): AiDriver
    {
        $config = config('ai_tutor', []);

        if (! empty($config['api_key']) && ! empty($config['base_url'])) {
            return new OpenAiCompatibleDriver(
                $config['base_url'],
                $config['api_key'],
                $config['model'] ?? 'gpt-4o-mini',
            );
        }

        return new DisabledDriver;
    }
}
