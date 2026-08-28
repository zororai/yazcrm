<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SpeechToTextService
{
    /**
     * Send an audio file to the Python ASR service and return a normalized
     * transcription result.
     *
     * @throws RuntimeException on timeout, connection failure, or an error response.
     */
    public function transcribe(string $audioPath, ?string $language = null): array
    {
        if (! is_file($audioPath)) {
            throw new RuntimeException("Audio file not found: {$audioPath}");
        }

        $language ??= 'shona';
        $baseUrl = rtrim((string) config('asr.service_url'), '/');
        $key = config('asr.service_key');

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('asr.timeout', 300))
                ->attach('audio', file_get_contents($audioPath), basename($audioPath))
                ->post("{$baseUrl}/transcribe", [
                    'language' => $language,
                ]);
        } catch (ConnectionException $e) {
            Log::error('ASR service unreachable', ['language' => $language, 'error' => $e->getMessage()]);
            throw new RuntimeException('ASR service unavailable.', previous: $e);
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? "ASR service returned HTTP {$response->status()}.";
            Log::error('ASR transcription failed', ['language' => $language, 'status' => $response->status(), 'error' => $error]);
            throw new RuntimeException($error);
        }

        $data = $response->json();

        if (! is_array($data) || ($data['success'] ?? false) !== true) {
            $error = $data['error'] ?? 'ASR service returned an unexpected response.';
            Log::error('ASR transcription unsuccessful', ['language' => $language, 'error' => $error]);
            throw new RuntimeException($error);
        }

        foreach (['language', 'transcript', 'model', 'processing_time_ms'] as $field) {
            if (! array_key_exists($field, $data)) {
                Log::error('ASR response missing expected field', ['field' => $field]);
                throw new RuntimeException('ASR service returned a malformed response.');
            }
        }

        return [
            'language'           => $data['language'],
            'transcript'         => $data['transcript'],
            'confidence'         => $data['confidence'] ?? null,
            'model'              => $data['model'],
            'processing_time_ms' => $data['processing_time_ms'],
        ];
    }
}
