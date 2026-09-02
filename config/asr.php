<?php

return [

    // Transcription now goes through OpenAI Whisper (app/Services/SpeechToTextService.php)
    // instead of a self-hosted Python ASR service. Whisper is one universal
    // multilingual model — there's no per-language model to configure or
    // activate here.

    // Automatically queue newly-synced Yeastar recordings for transcription.
    // Left OFF by default: on QUEUE_CONNECTION=sync (no worker running), a
    // dispatch executes inline, and a sync that imports N recordings would
    // block the request for N * (time per transcription) — see spec §21
    // ("do not make the caller wait for transcription"). Only enable this
    // once a real queue worker (database/redis) is running.
    'auto_transcribe' => [
        'enabled'          => env('ASR_AUTO_TRANSCRIBE', false),
        'default_language' => env('ASR_AUTO_TRANSCRIBE_LANGUAGE', 'english'),
    ],

    // Phase 8 — optional AI case-intelligence summary (spec §18-19). Off by
    // default: this is explicitly an assistant recommendation requiring
    // human review before it touches a real case, never an automated
    // decision. Requires OPENAI_API_KEY to be configured.
    'ai_intelligence' => [
        'enabled' => env('ASR_AI_INTELLIGENCE', false),
    ],

];
