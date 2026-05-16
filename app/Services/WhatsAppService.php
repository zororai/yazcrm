<?php

namespace App\Services;

use App\Models\Client;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class WhatsAppService
{
    private TwilioClient $twilio;
    private string $from;

    public function __construct()
    {
        $this->twilio = new TwilioClient(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
        $this->from = 'whatsapp:' . config('services.twilio.whatsapp_number');
    }

    public function sendMessage(string $toNumber, string $body, ?int $agentId = null): ?WhatsappMessage
    {
        $to = 'whatsapp:' . $this->normalizeNumber($toNumber);

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => $body,
            ]);

            $client = Client::where('whatsapp_number', $toNumber)
                ->orWhere('phone', $toNumber)
                ->first();

            return WhatsappMessage::create([
                'client_id'   => $client?->id,
                'agent_id'    => $agentId,
                'from_number' => config('services.twilio.whatsapp_number'),
                'to_number'   => $toNumber,
                'body'        => $body,
                'direction'   => 'outbound',
                'message_sid' => $message->sid,
                'status'      => 'sent',
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed: ' . $e->getMessage());
            return null;
        }
    }

    public function handleIncoming(array $payload): WhatsappMessage
    {
        $fromNumber = str_replace('whatsapp:', '', $payload['From'] ?? '');
        $body       = $payload['Body'] ?? '';

        $client = Client::firstOrCreate(
            ['phone' => $fromNumber],
            ['name'  => $fromNumber, 'whatsapp_number' => $fromNumber]
        );

        return WhatsappMessage::create([
            'client_id'   => $client->id,
            'from_number' => $fromNumber,
            'to_number'   => str_replace('whatsapp:', '', $payload['To'] ?? ''),
            'body'        => $body,
            'direction'   => 'inbound',
            'message_sid' => $payload['MessageSid'] ?? null,
            'status'      => 'delivered',
            'media_url'   => $payload['MediaUrl0'] ?? null,
        ]);
    }

    private function normalizeNumber(string $number): string
    {
        $number = preg_replace('/[^0-9+]/', '', $number);
        if (!str_starts_with($number, '+')) {
            $number = '+' . $number;
        }
        return $number;
    }
}
