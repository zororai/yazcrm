<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class SpeechToTextService
{
    private const MODEL = 'whisper-1';

    // ISO-639-1 hints passed to Whisper. Any language name not in this map
    // is passed through as-is (Whisper accepts a wide range of codes) —
    // there's no per-language allow-list here: Whisper is one universal
    // model, so nothing is being "activated" per language like the old
    // per-language local-model setup. Confirm real output quality per
    // language before relying on it for a given language in production.
    private const LANGUAGE_CODES = [
        'shona'   => 'sn',
        'english' => 'en',
        'ndebele' => 'nd',
    ];

    /**
     * Send an audio file to OpenAI's Whisper API and return a normalized
     * transcription result.
     *
     * @throws RuntimeException on missing file or an API failure.
     */
    public function transcribe(string $audioPath, ?string $language = null): array
    {
        if (! is_file($audioPath)) {
            throw new RuntimeException("Audio file not found: {$audioPath}");
        }

        $language ??= 'english';
        $languageCode = self::LANGUAGE_CODES[strtolower($language)] ?? $language;

        $started = microtime(true);
        $detectedLanguage = $language;

        try {
            $response = $this->call($audioPath, $languageCode);
        } catch (Throwable $e) {
            // Whisper's API hard-rejects some language codes as unsupported
            // (observed for Shona's 'sn', not for Ndebele's 'nd') rather than
            // just degrading quality. Retry once letting it auto-detect
            // instead of failing the whole transcription outright.
            if (str_contains($e->getMessage(), 'not supported')) {
                try {
                    $response = $this->call($audioPath, null);
                    $detectedLanguage = $response->language ?? $language;
                } catch (Throwable $e2) {
                    throw new RuntimeException('Whisper transcription failed: ' . $e2->getMessage(), previous: $e2);
                }
            } else {
                throw new RuntimeException('Whisper transcription failed: ' . $e->getMessage(), previous: $e);
            }
        }

        $transcript = trim($response->text ?? '');

        if ($transcript === '') {
            throw new RuntimeException('Transcription produced empty output.');
        }

        return [
            'language'           => $detectedLanguage,
            'transcript'         => $transcript,
            // Whisper's standard API doesn't return a confidence score.
            'confidence'         => null,
            'model'              => self::MODEL,
            'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function call(string $audioPath, ?string $languageCode)
    {
        $params = [
            'model'           => self::MODEL,
            'file'            => fopen($audioPath, 'rb'),
            'response_format' => 'verbose_json',
        ];

        if ($languageCode) {
            $params['language'] = $languageCode;
        }

        return OpenAI::audio()->transcribe($params);
    }
}
