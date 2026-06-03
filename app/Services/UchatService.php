<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\UchatAnalyticsSnapshot;
use App\Models\UrgentCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UchatService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('uchat.base_url'), '/');
        $this->token   = config('uchat.token', '');
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

    /**
     * Fetch aggregated bot analytics from uChat, cached for 30 minutes.
     */
    public function fetchAnalytics(): array
    {
        $default = [
            'total_bot_users' => 0,
            'new_bot_users'   => 0,
            'active_today'    => 0,
            'channel_counts'  => [],
            'fetched_at'      => null,
            'error'           => 'Token not configured',
        ];

        if (! $this->token) return $default;

        return Cache::remember('uchat_analytics', 1800, function () use ($default) {
            try {
                // 1. Total bot users
                $countResp    = $this->get('/flow/bot-users-count');
                $totalBotUsers = collect($countResp['data'] ?? [])->sum('num');

                // 2. Active last 24 h
                $activeResp  = $this->get('/subscribers', ['is_interacted_in_last_24h' => 'yes', 'limit' => 1]);
                $activeToday = $activeResp['meta']['total'] ?? 0;

                // 3. New users last 30 days + channel breakdown
                // Subscribers are returned newest-first; paginate until we pass the 30-day cutoff.
                $cutoff       = now()->subDays(30);
                $newUsers     = 0;
                $channelCounts = [];
                $page         = 1;
                $done         = false;

                while (! $done && $page <= 20) {
                    $resp = $this->get('/subscribers', ['limit' => 100, 'page' => $page]);
                    $subs = $resp['data'] ?? [];
                    if (empty($subs)) break;

                    foreach ($subs as $sub) {
                        if (Carbon::parse($sub['subscribed'] ?? '')->lt($cutoff)) {
                            $done = true;
                            break;
                        }
                        $newUsers++;
                        $ch = $sub['channel'] ?? 'unknown';
                        $channelCounts[$ch] = ($channelCounts[$ch] ?? 0) + 1;
                    }
                    $page++;
                }

                arsort($channelCounts);

                $result = [
                    'total_bot_users' => (int) $totalBotUsers,
                    'new_bot_users'   => $newUsers,
                    'active_today'    => (int) $activeToday,
                    'channel_counts'  => $channelCounts,
                    'fetched_at'      => now()->format('d M Y, H:i'),
                    'error'           => null,
                ];

                // Persist today's snapshot (upsert so reruns in the same day just update)
                UchatAnalyticsSnapshot::updateOrCreate(
                    ['date' => today()->toDateString()],
                    [
                        'total_bot_users' => $result['total_bot_users'],
                        'new_bot_users'   => $result['new_bot_users'],
                        'active_today'    => $result['active_today'],
                        'channel_counts'  => $result['channel_counts'],
                    ]
                );

                return $result;
            } catch (\Exception $e) {
                Log::error('UchatService::fetchAnalytics: ' . $e->getMessage());
                return array_merge($default, ['error' => $e->getMessage()]);
            }
        });
    }

    // ── Messaging ─────────────────────────────────────────────────────────────

    /**
     * Send a file (e.g. PDF certificate) to a WhatsApp user via uChat.
     * $phone  — phone number as stored (e.g. 263771234567 or +263771234567)
     * $fileUrl — publicly accessible URL of the file
     * $caption — optional text message sent alongside the file
     */
    /**
     * Look up a subscriber by phone number and return their uChat user_ns.
     * uChat requires the internal user_ns (e.g. "f71403u765891021"), not a phone number.
     */
    public function findUserNsByPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $resp   = $this->get('/subscribers', ['user_id' => $digits, 'limit' => 1]);
        return $resp['data'][0]['user_ns'] ?? null;
    }

    /**
     * Send a file (e.g. PDF certificate) to a WhatsApp user via uChat.
     * Looks up the subscriber by phone to get their user_ns first.
     */
    public function sendFile(string $phone, string $fileUrl, string $caption = ''): bool
    {
        $userNs = $this->findUserNsByPhone($phone);

        if (! $userNs) {
            Log::warning('UchatService::sendFile: subscriber not found for phone', ['phone' => $phone]);
            return false;
        }

        $messages = [];

        if ($caption) {
            $messages[] = ['type' => 'text', 'text' => $caption];
        }

        $messages[] = [
            'type' => 'file',
            'url'  => $fileUrl,
        ];

        $result = $this->post('/subscriber/send-content', [
            'user_ns' => $userNs,
            'data'    => [
                'version' => 'v1',
                'content' => [
                    'messages' => $messages,
                ],
            ],
        ]);

        return $result !== null;
    }

    // ── Contacts ──────────────────────────────────────────────────────────────

    public function syncContact(string $phone, array $fields = []): ?string
    {
        $response = $this->post('/contacts', array_merge(['phone' => $phone], $fields));
        return $response ? ($response['id'] ?? $response['contact_id'] ?? null) : null;
    }

    // ── Tickets ───────────────────────────────────────────────────────────────

    public function syncTicket(Ticket $ticket): bool
    {
        return (bool) $this->post('/tickets', [
            'external_id'   => 'ticket-' . $ticket->id,
            'subject'       => $ticket->subject,
            'status'        => $ticket->status,
            'priority'      => $ticket->priority,
            'phone'         => $ticket->contact_number,
            'agent'         => $ticket->agent?->name,
            'project'       => $ticket->project,
            'purpose'       => $ticket->purpose_of_call,
            'notes'         => $ticket->description,
            'urgent'        => (bool) $ticket->immediate_action_required,
            'referral_date' => $ticket->referral_uptake_date,
            'created_at'    => $ticket->created_at?->toIso8601String(),
        ]);
    }

    public function syncUrgentCase(UrgentCase $case): bool
    {
        return (bool) $this->post('/urgent-cases', [
            'external_id' => 'urgent-' . $case->id,
            'subject'     => $case->subject,
            'status'      => $case->status,
            'phone'       => $case->contact_number,
            'agent'       => $case->agent?->name,
            'notes'       => $case->description,
            'created_at'  => $case->created_at?->toIso8601String(),
        ]);
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    private function get(string $endpoint, array $query = []): ?array
    {
        if (! $this->token) return null;
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->get($this->baseUrl . $endpoint, $query);
            return $response->successful() ? ($response->json() ?? []) : null;
        } catch (\Exception $e) {
            Log::error('UchatService GET ' . $endpoint . ': ' . $e->getMessage());
            return null;
        }
    }

    private function post(string $endpoint, array $payload): ?array
    {
        if (! $this->token) {
            Log::warning('UchatService: UCHAT_API_TOKEN is not set.');
            return null;
        }
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->post($this->baseUrl . $endpoint, $payload);
            if ($response->successful()) return $response->json() ?? [];
            Log::warning('UchatService POST failed', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('UchatService POST ' . $endpoint . ': ' . $e->getMessage());
            return null;
        }
    }
}
