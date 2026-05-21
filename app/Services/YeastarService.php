<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Extension;
use App\Models\Recording;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $this->baseUrl   = rtrim(Setting::get('yeastar_base_url', config('yeastar.base_url')), '/');
        $this->appId     = Setting::get('yeastar_app_id', config('yeastar.app_id'));
        $this->appSecret = Setting::get('yeastar_app_secret', config('yeastar.app_secret'));
    }

    private static function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withoutVerifying()
            ->asJson()
            ->withUserAgent('CRM-PBX/1.0 OpenAPI');
    }

    public static function testCredentials(string $baseUrl, string $appId, string $appSecret): array
    {
        try {
            $response = static::client()->timeout(8)
                ->post(rtrim($baseUrl, '/') . '/get_token', [
                    'username' => $appId,
                    'password' => $appSecret,
                ]);

            $body = $response->json();
            if ($response->successful() && isset($body['access_token'])) {
                return ['ok' => true, 'message' => 'Connection successful — token received.'];
            }

            $err = $body['errmsg'] ?? ('HTTP ' . $response->status());
            return ['ok' => false, 'message' => "Authentication failed: {$err}"];
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => 'Could not reach PBX: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ auth

    public function getAccessToken(): ?string
    {
        if ($token = Cache::get($this->tokenCacheKey)) {
            return $token;
        }

        try {
            $response = static::client()->post("{$this->baseUrl}/get_token", [
                'username' => $this->appId,
                'password' => $this->appSecret,
            ]);

            $body = $response->json();
            if ($response->successful() && isset($body['access_token'])) {
                $expiresIn = $body['access_token_expire_time'] ?? 1800;
                Cache::put($this->tokenCacheKey, $body['access_token'], $expiresIn - 60);
                return $body['access_token'];
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
            $response = static::client()
                ->withHeaders(['Authorization' => $token])
                ->{$method}("{$this->baseUrl}/{$endpoint}", $data);

            // Yeastar returns 200 with errcode 10004 for expired token
            $body = $response->json();
            if ($response->status() === 401 || ($body['errcode'] ?? 0) === 10004) {
                Cache::forget($this->tokenCacheKey);
                $token = $this->getAccessToken();
                $response = static::client()
                    ->withHeaders(['Authorization' => $token])
                    ->{$method}("{$this->baseUrl}/{$endpoint}", $data);
                $body = $response->json();
            }

            if (($body['errcode'] ?? -1) !== 0) {
                Log::warning("Yeastar API [{$endpoint}]: " . ($body['errmsg'] ?? 'unknown error'));
                return null;
            }

            return $body;
        } catch (\Exception $e) {
            Log::error("Yeastar API error [{$endpoint}]: " . $e->getMessage());
            return null;
        }
    }

    // --------------------------------------------------------------- extensions

    public function fetchExtensions(): array
    {
        $data = $this->request('get', 'extension/list');
        return $data['data'] ?? [];
    }

    public function syncExtensions(): int
    {
        $extensions = $this->fetchExtensions();
        $synced = 0;

        foreach ($extensions as $ext) {
            Extension::updateOrCreate(
                ['extension_number' => $ext['number']],
                [
                    'name'           => $ext['caller_id_name'] ?? $ext['number'],
                    'status'         => $this->mapExtensionStatus($ext),
                    'caller_id_name' => $ext['caller_id_name'] ?? null,
                    'email'          => $ext['email'] ?? null,
                    'voicemail_enabled' => false,
                ]
            );
            $synced++;
        }

        return $synced;
    }

    private function mapExtensionStatus(array $ext): string
    {
        $presence = strtolower($ext['presence_status'] ?? 'available');
        if ($presence === 'on a call' || $presence === 'busy') return 'on_call';
        if ($presence === 'ringing') return 'ringing';

        // Check if any SIP device is registered (status=1)
        $sipStatus = $ext['online_status']['sip_phone']['status'] ?? 0;
        return $sipStatus === 1 ? 'registered' : 'idle';
    }

    // ------------------------------------------------------------------ CDR

    private function hasDirectDb(): bool
    {
        return !empty(env('DB_YEASTAR_HOST'));
    }

    public function syncCalls(?string $startTime = null, ?string $endTime = null): int
    {
        return $this->hasDirectDb()
            ? $this->syncCallsFromDb($startTime, $endTime)
            : $this->syncCallsFromApi($startTime, $endTime);
    }

    private function syncCallsFromDb(?string $startTime, ?string $endTime): int
    {
        $start  = $startTime ?? now()->subDays(30)->format('Y-m-d H:i:s');
        $end    = $endTime   ?? now()->format('Y-m-d H:i:s');
        $synced = 0;

        DB::connection('yeastar')
            ->table('cdr')
            ->whereBetween('datetime', [$start, $end])
            ->orderBy('datetime')
            ->chunk(200, function ($rows) use (&$synced) {
                foreach ($rows as $row) {
                    $row = (array) $row;
                    $call = Call::updateOrCreate(
                        ['call_id' => (string)($row['uniqueid'] ?? $row['id'] ?? md5(json_encode($row)))],
                        [
                            'caller'           => $row['src']         ?? '',
                            'callee'           => $row['dst']         ?? '',
                            'direction'        => $this->mapDirectionFromDb($row['calltype'] ?? ''),
                            'status'           => $this->mapStatus($row['disposition'] ?? ''),
                            'duration'         => (int)($row['duration'] ?? 0),
                            'started_at'       => $row['datetime']    ?? null,
                            'ended_at'         => null,
                            'extension_number' => $row['src']         ?? null,
                            'recording_file'   => $row['recordfile']  ?? null,
                            'raw_data'         => $row,
                        ]
                    );

                    if (!empty($row['recordfile'])) {
                        Recording::firstOrCreate(
                            ['call_id' => $call->id],
                            [
                                'file_name' => basename($row['recordfile']),
                                'file_path' => $row['recordfile'],
                                'duration'  => (int)($row['duration'] ?? 0),
                            ]
                        );
                    }

                    $synced++;
                }
            });

        return $synced;
    }

    private function syncCallsFromApi(?string $startTime, ?string $endTime): int
    {
        $start  = $startTime ?? now()->subDays(30)->format('Y-m-d H:i:s');
        $end    = $endTime   ?? now()->format('Y-m-d H:i:s');
        $synced = 0;
        $page   = 1;

        do {
            $data    = $this->request('get', 'cdr/search', ['start_time' => $start, 'end_time' => $end, 'page' => $page, 'page_size' => 100]);
            $cdrList = $data['data'] ?? [];
            if (empty($cdrList)) break;

            foreach ($cdrList as $cdr) {
                $call = Call::updateOrCreate(
                    ['call_id' => (string)($cdr['call_id'] ?? $cdr['id'] ?? uniqid())],
                    [
                        'caller'           => $cdr['caller'] ?? $cdr['src'] ?? '',
                        'callee'           => $cdr['callee'] ?? $cdr['dst'] ?? '',
                        'direction'        => $this->mapDirection($cdr),
                        'status'           => $this->mapStatus($cdr['status'] ?? $cdr['disposition'] ?? ''),
                        'duration'         => (int)($cdr['duration'] ?? $cdr['talk_duration'] ?? 0),
                        'started_at'       => $cdr['start_time'] ?? null,
                        'ended_at'         => $cdr['end_time']   ?? null,
                        'extension_number' => $cdr['extension']  ?? null,
                        'recording_file'   => $cdr['recording_file'] ?? null,
                        'raw_data'         => $cdr,
                    ]
                );

                if (!empty($cdr['recording_file'])) {
                    Recording::firstOrCreate(
                        ['call_id' => $call->id],
                        ['file_name' => basename($cdr['recording_file']), 'file_path' => $cdr['recording_file'], 'duration' => (int)($cdr['duration'] ?? 0)]
                    );
                }

                $synced++;
            }
            $page++;
        } while (count($cdrList) === 100);

        return $synced;
    }

    private function mapDirectionFromDb(string $calltype): string
    {
        $t = strtolower($calltype);
        if (str_contains($t, 'inbound')  || $t === 'in')  return 'inbound';
        if (str_contains($t, 'outbound') || $t === 'out') return 'outbound';
        return 'internal';
    }

    private function mapDirection(array $cdr): string
    {
        $type = strtolower($cdr['call_type'] ?? $cdr['direction'] ?? '');
        if (str_contains($type, 'inbound')  || $type === 'in')  return 'inbound';
        if (str_contains($type, 'outbound') || $type === 'out') return 'outbound';
        return 'internal';
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'answered', 'answer'  => 'answered',
            'no answer', 'noanswer', 'missed' => 'missed',
            'busy'    => 'busy',
            'failed'  => 'failed',
            'voicemail' => 'voicemail',
            default   => 'missed',
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

    public function registerWebhook(string $url): array
    {
        // Try known Yeastar P-Series event subscription endpoints
        $candidates = [
            ['endpoint' => 'subscribe',        'payload' => ['url' => $url, 'event_list'  => ['call.status', 'extension.status']]],
            ['endpoint' => 'event/subscribe',  'payload' => ['url' => $url, 'event_list'  => ['call.status', 'extension.status']]],
            ['endpoint' => 'webhook/add',      'payload' => ['url' => $url, 'events'      => ['call_start', 'call_end', 'extension_status']]],
            ['endpoint' => 'webhook/subscribe','payload' => ['url' => $url, 'events'      => ['call_start', 'call_end', 'extension_status']]],
        ];

        foreach ($candidates as ['endpoint' => $ep, 'payload' => $payload]) {
            try {
                $result = $this->request('post', $ep, $payload);
                if ($result !== null) {
                    Log::info("Yeastar webhook registered via [{$ep}]");
                    return ['ok' => true, 'message' => "Webhook registered successfully via {$ep}."];
                }
            } catch (\Exception $e) {
                Log::warning("Yeastar webhook [{$ep}] failed: " . $e->getMessage());
            }
        }

        return ['ok' => false, 'message' => 'Could not register webhook — no supported endpoint found on this PBX firmware.'];
    }
}
