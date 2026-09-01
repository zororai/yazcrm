<?php

return [

    'service_url' => env('ASR_SERVICE_URL', 'http://127.0.0.1:8000'),

    'service_key' => env('ASR_SERVICE_KEY'),

    'timeout' => env('ASR_TIMEOUT', 300),

    'models' => [
        'shona' => env('ASR_MODEL_SHONA', 'badrex/w2v-bert-2.0-shona-asr'),

        'english' => env('ASR_MODEL_ENGLISH'),

        'ndebele' => env('ASR_MODEL_NDEBELE'),
    ],

    // Automatically queue newly-synced Yeastar recordings for transcription.
    // Left OFF by default: on QUEUE_CONNECTION=sync (no worker running), a
    // dispatch executes inline, and a sync that imports N recordings would
    // block the request for N * (minutes per transcription) — see spec §21
    // ("do not make the caller wait for transcription"). Only enable this
    // once a real queue worker (database/redis) is running.
    'auto_transcribe' => [
        'enabled'          => env('ASR_AUTO_TRANSCRIBE', false),
        'default_language' => env('ASR_AUTO_TRANSCRIBE_LANGUAGE', 'shona'),
    ],

    // Phase 8 — optional AI case-intelligence summary (spec §18-19). Off by
    // default: this is explicitly an assistant recommendation requiring
    // human review before it touches a real case, never an automated
    // decision. Requires OPENAI_API_KEY to be configured.
    'ai_intelligence' => [
        'enabled' => env('ASR_AI_INTELLIGENCE', false),
    ],

];
