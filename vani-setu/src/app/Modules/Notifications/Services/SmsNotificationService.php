<?php

namespace App\Modules\Notifications\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsNotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array
    {
        if (! Config::boolean('services.delivery_channels.send_sms')) {
            throw new RuntimeException('SMS delivery is disabled.');
        }

        $apiUrl = (string) config('services.sms_gateway.api_url');
        $username = (string) config('services.sms_gateway.username');
        $pin = (string) config('services.sms_gateway.pin');

        if ($apiUrl === '' || $username === '' || $pin === '') {
            throw new RuntimeException('SMS gateway is not configured.');
        }

        $requestPayload = [
            'username' => $username,
            'pin' => $pin,
            'mnumber' => (string) ($payload['recipient'] ?? $payload['mnumber']),
            'message' => (string) $payload['message'],
            'signature' => (string) ($payload['signature'] ?? config('services.sms_gateway.signature')),
            'dlt_entity_id' => (string) ($payload['dlt_entity_id'] ?? config('services.sms_gateway.dlt_entity_id')),
            'dlt_template_id' => (string) $payload['template_id'],
        ];

        Log::channel(config('logging.default'))->info('notification.sms.requested', [
            'recipient' => $requestPayload['mnumber'],
            'template_id' => $requestPayload['dlt_template_id'],
        ]);

        $response = Http::asForm()
            ->timeout((int) config('services.sms_gateway.timeout', 10))
            ->post($apiUrl, $requestPayload);

        return [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->body(),
            'gateway' => $apiUrl,
        ];
    }
}
