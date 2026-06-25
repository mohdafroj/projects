<?php

namespace App\Modules\Notifications\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsappNotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array
    {
        if (! Config::boolean('services.delivery_channels.send_whatsapp')) {
            throw new RuntimeException('WhatsApp delivery is disabled.');
        }

        $apiUrl = (string) config('services.whatsapp_gateway.api_url');
        $token = (string) config('services.whatsapp_gateway.token');

        if ($apiUrl === '' || $token === '') {
            throw new RuntimeException('WhatsApp gateway is not configured.');
        }

        $requestPayload = [
            'to' => (string) $payload['recipient'],
            'type' => isset($payload['template_id']) ? 'template' : 'text',
            'template_id' => $payload['template_id'] ?? null,
            'body' => (string) ($payload['body'] ?? $payload['message'] ?? ''),
            'data' => $payload['data'] ?? [],
        ];

        Log::channel(config('logging.default'))->info('notification.whatsapp.requested', [
            'recipient' => $requestPayload['to'],
            'template_id' => $requestPayload['template_id'],
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.whatsapp_gateway.timeout', 10))
            ->post($apiUrl, array_filter($requestPayload, static fn (mixed $value): bool => $value !== null));

        return [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->body(),
            'gateway' => $apiUrl,
        ];
    }
}
