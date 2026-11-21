<?php

namespace App\Services\Ai;

class DisabledDriver implements AiDriver
{
    public function complete(array $system, array $messages): never
    {
        throw new AiUnavailableException(
            "AI Ustoz hozircha sozlanmagan. AI_TUTOR_API_KEY va AI_TUTOR_BASE_URL muhit o'zgaruvchilarini belgilang."
        );
    }
}
