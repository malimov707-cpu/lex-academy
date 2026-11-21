<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Ustoz integratsiyasi
    |--------------------------------------------------------------------------
    |
    | Kalitlar bo'lmasa DisabledDriver ishlatiladi: tizim aniq "sozlanmagan"
    | holatini qaytaradi, soxta (mock) javob chiqarmaydi.
    |
    | OpenAI-mos har qanday servis mos: OpenAI, Anthropic-gateway, mahalliy
    | FastAPI/RAG xizmat (vLLM, Ollama va h.k.).
    |
    */

    'provider' => env('AI_CHAT_PROVIDER', 'openai'),
    'api_key' => env('OPENAI_API_KEY', env('AI_TUTOR_API_KEY')),
    'base_url' => env('AI_CHAT_BASE_URL', env('AI_TUTOR_BASE_URL', 'https://api.openai.com/v1')),
    'model' => env('AI_CHAT_MODEL', env('AI_TUTOR_MODEL', 'gpt-6.2-sol')),
    'reasoning_model' => env('AI_REASONING_MODEL', 'gpt-6.2-astra'),

    // Bir suhbatda yuboriladigan kontekst xabarlar soni (token xarajat nazorati)
    'max_context_messages' => env('AI_TUTOR_MAX_CONTEXT', 12),
];
