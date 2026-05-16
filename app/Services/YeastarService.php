<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Extension;
use App\Models\Recording;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YeastarService
{
    private string $baseUrl;
    private string $appId;
    private string $appSecret;
    private string $tokenCacheKey = 'yeastar_access_token';

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('yeastar.base_url'), '/');
        $this->appId    = config('yeastar.app_id');
        $this->appSecret = config('yeastar.app_secret');
    }

    // ------------------------------------------------------------------ auth

    public function getAccessToken(): ?string
    {
        if ($token = Cache::get($this->tokenCacheKey)) {
            return $token;
        }

        try {
            $response = Http::post("{$this->baseUrl}/get_token", [
                'app_id'     => $this->appId,
                'app_secret' => $this->appSecret,
            ]);

            if ($response->successful() && isset($response['access_token'])) {
                $expiresIn = $response['expires_in'] ?? 1800;
                Cache::put($this->tokenCacheKey, $response['access_token'], $expiresIn - 60);
                return $response['access_token'];
            }
        } catch (\Exception $e) {
            Log::error('Yeastar auth failed: ' . $e->getMessage());
        }

        return null;
    }

    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$token}"])
                ->{$method}("{$this->baseUrl}/{$endpoint}", $data);

            if ($response->status() === 401) {
                Cache::forget($this->tokenCacheKey);
                $token = $this->getAccessToken();
                $response = Http::withHeaders(['Authorization' => "Bearer {$token}"])
                    ->{$method}("{$this->baseUrl}/{$endpoint}", $data);
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error("Yeastar API error [{$endpoint}]: " . $e->getMessage());
            return null;
        }
    }

    // --------------------------------------------------------------- extensions

    public function fetchExtensions(): array
    {
        $data = $this->request('get', 'extension/list');
        return $data['data']['extension_list'] ?? [];
    }

    public function syncExtensions(): void
    {
        $extensions = $this->fetchExtensions();

        foreach ($extensions as $ext) {
            Extension::updateOrCreate(
                ['extension_number' => $ext['number']],
                [
                    'name'            => $ext['name'] ?? $ext['number'],
                    'status'          => $this->mapExtensionStatus($ext['status'] ?? ''),
                    'caller_id_name'  => $ext['caller_id_name'] ?? null,
                    'email'           => $ext['email'] ?? null,
                    'voicemail_enabled' => ($ext['enable_voicemail'] ?? false) ? true : false,
                ]
            );
        }
    }

    private function mapExtensionStatus(string $status): string
    {
        return match (strtolower($status)) {
            'registered'   => 'registered',
            'unregistered' => 'unregistered',
            'ringing'      => 'ringing',
            'on call'      => 'on_call',
            default        => 'idle',
        };
    }

    // ------------------------------------------------------------------ CDR

    public function fetchCdr(array $filters = []): array
    {
        $params = array_merge([
            'page'      => 1,
            'page_size' => 100,
        ], $filters);

        $data = $this->request('get', 'cdr', $params);
        return $data['data']['cdr_list'] ?? [];
    }

    public function syncCalls(?string $startTime = null, ?string $endTime = null): int
    {
        $filters = [];
        if ($startTime) $filters['start_time'] = $startTime;
        if ($endTime)   $filters['end_time']   = $endTime;

        $cdrList = $this->fetchCdr($filters);
        $synced  = 0;

        foreach ($cdrList as $cdr) {
            $call = Call::updateOrCreate(
                ['call_id' => $cdr['call_id'] ?? $cdr['id']],
                [
                    'caller'           => $cdr['caller'] ?? $cdr['src'] ?? '',
                    'callee'           => $cdr['callee'] ?? $cdr['dst'] ?? '',
                    'direction'        => $this->mapDirection($cdr),
                    'status'           => $this->mapStatus($cdr['disposition'] ?? ''),
                    'duration'         => (int)($cdr['duration'] ?? 0),
                    'started_at'       => $cdr['start_time'] ?? null,
                    'ended_at'         => $cdr['end_time'] ?? null,
                    'extension_number' => $cdr['extension'] ?? null,
                    'recording_file'   => $cdr['recording_file'] ?? null,
                    'raw_data'         => $cdr,
                ]
            );

            if (!empty($cdr['recording_file'])) {
                Recording::firstOrCreate(
                    ['call_id'  => $call->id],
                    [
                        'file_name' => basename($cdr['recording_file']),
                        'file_path' => $cdr['recording_file'],
                        'duration'  => (int)($cdr['duration'] ?? 0),
                    ]
                );
            }

            $synced++;
        }

        return $synced;
    }

    private function mapDirection(array $cdr): string
    {
        $type = strtolower($cdr['call_type'] ?? $cdr['direction'] ?? '');
        if (str_contains($type, 'inbound') || $type === 'in')  return 'inbound';
        if (str_contains($type, 'outbound') || $type === 'out') return 'outbound';
        return 'internal';
    }

    private function mapStatus(string $disposition): string
    {
        return match (strtolower($disposition)) {
            'answered'   => 'answered',
            'no answer'  => 'missed',
            'busy'       => 'busy',
            'failed'     => 'failed',
            'voicemail'  => 'voicemail',
            default      => 'missed',
        };
    }

    // ----------------------------------------------------------- active calls

    public function getActiveCalls(): array
    {
        $data = $this->request('get', 'active_call/list');
        return $data['data']['active_call_list'] ?? [];
    }

    // ---------------------------------------------------------- recording URL

    public function getRecordingDownloadUrl(string $filename): ?string
    {
        $data = $this->request('get', 'recording/download', ['file_name' => $filename]);
        return $data['data']['url'] ?? null;
    }

    // --------------------------------------------------------- webhook register

    public function registerWebhook(string $url, array $events = []): bool
    {
        $payload = [
            'url'    => $url,
            'events' => empty($events) ? ['call_start', 'call_end', 'extension_status'] : $events,
        ];

        $result = $this->request('post', 'webhook/add', $payload);
        return isset($result['ret']) && $result['ret'] === 0;
    }
}
