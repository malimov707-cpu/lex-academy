<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'chat_provider' => env('AI_CHAT_PROVIDER', 'openai'),
        'chat_base_url' => env('AI_CHAT_BASE_URL', 'https://api.openai.com/v1'),
        'chat_model' => env('AI_CHAT_MODEL', 'gpt-6.2-sol'),
        'reasoning_model' => env('AI_REASONING_MODEL', 'gpt-6.2-astra'),
        'voice_provider' => env('AI_VOICE_PROVIDER', 'openai'),
        'voice_model' => env('AI_VOICE_MODEL', 'gpt-live-1.1'),
        'voice_mode' => env('AI_VOICE_MODE', 'webrtc'),
        'transcription_model' => env('AI_TRANSCRIPTION_MODEL', 'gpt-live-transcribe'),
    ],

    'livekit' => [
        'audio_provider' => env('COURTROOM_AUDIO_PROVIDER', 'livekit'),
    ],

    'legal_source_monitor' => [
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('LEGAL_SOURCE_MONITOR_HOSTS', 'lex.uz,www.lex.uz')),
        ))),
    ],

];
