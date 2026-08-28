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

];
