<?php

return [
    'enabled' => env('VIRTUAL_COURTROOM_ENABLED', true),

    'session' => [
        'max_minutes' => (int) env('VIRTUAL_COURTROOM_MAX_SESSION_MINUTES', 90),
        'context_turns' => (int) env('VIRTUAL_COURTROOM_CONTEXT_TURNS', 12),
    ],

    'voice' => [
        /*
         * Browser mode uses feature-detected SpeechRecognition and speechSynthesis.
         * It never exposes the server AI key and never stores raw audio.
         */
        'mode' => env('AI_VOICE_MODE', env('VIRTUAL_COURTROOM_VOICE_MODE', 'browser')),
        'provider' => env('AI_VOICE_PROVIDER', 'openai'),
        'browser_speech_enabled' => env('VIRTUAL_COURTROOM_BROWSER_SPEECH_ENABLED', true),
        'server_speech_enabled' => env('VIRTUAL_COURTROOM_SERVER_SPEECH_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY', env('AI_VOICE_API_KEY')),
        'model' => env('AI_VOICE_MODEL', 'gpt-live-1.1'),
        'stt_model' => env('AI_TRANSCRIPTION_MODEL', env('AI_STT_MODEL', 'gpt-live-transcribe')),
        'realtime_model' => env('AI_VOICE_MODEL', env('AI_REALTIME_MODEL', 'gpt-live-1.1')),
        'tts_model' => env('AI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_voice' => env('AI_TTS_VOICE', 'coral'),
        'tts_provider' => env('AI_TTS_PROVIDER', 'openai'),
        'azure' => [
            'enabled' => (bool) env('AZURE_SPEECH_ENABLED', false),
            'key' => env('AZURE_SPEECH_KEY'),
            'region' => env('AZURE_SPEECH_REGION', 'westus2'),
            'endpoint' => env('AZURE_SPEECH_ENDPOINT'),
            'male_voice' => env('AZURE_SPEECH_MALE_VOICE', 'uz-UZ-SardorNeural'),
            'female_voice' => env('AZURE_SPEECH_FEMALE_VOICE', 'uz-UZ-MadinaNeural'),
            'narrator_voice' => env('AZURE_SPEECH_NARRATOR_VOICE', 'uz-UZ-MadinaNeural'),
        ],
        'inworld_api_key' => env('AI_TTS_INWORLD_API_KEY', env('INWORLD_API_KEY')),
        'inworld_voice' => env('AI_TTS_INWORLD_VOICE'),
        'audio_recording_enabled' => false,
        'transcript_retention_days' => (int) env('VIRTUAL_COURTROOM_TRANSCRIPT_RETENTION_DAYS', 30),
    ],

    'group' => [
        'audio_provider' => env('COURTROOM_AUDIO_PROVIDER', 'livekit'),
        'ai_evaluation_model' => env('COURTROOM_AI_EVALUATION_MODEL', 'gpt-6.2-sol'),
    ],

    'scene' => [
        'mode' => env('VIRTUAL_COURTROOM_SCENE_MODE', 'webgl'),
        'default_quality' => env('VIRTUAL_COURTROOM_SCENE_QUALITY', 'auto'),
    ],
];
