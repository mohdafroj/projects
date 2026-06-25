<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\NotificationEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SDS\CommonCore\Notifications\NotificationChannel;
use SDS\CommonCore\Notifications\NotificationDispatchRequest;

class NotificationDispatchController extends Controller
{
    public function __construct(private readonly NotificationEngineService $notificationEngine)
    {
    }

    public function dispatch(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'channel' => ['required', 'string', Rule::in(NotificationChannel::all())],
            'producer' => ['nullable', 'string'],
            'to' => ['nullable'],
            'recipient' => ['nullable'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.address' => ['nullable', 'string'],
            'recipients.*.email' => ['nullable', 'string'],
            'recipients.*.phone' => ['nullable', 'string'],
            'recipients.*.name' => ['nullable', 'string'],
            'subject' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'template_id' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['nullable', 'string'],
            'attachments.*.mime_type' => ['nullable', 'string'],
            'attachments.*.content' => ['nullable', 'string'],
            'attachments.*.content_base64' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $result = $this->notificationEngine->dispatch(NotificationDispatchRequest::fromArray($payload));

        return response()->json([
            'message' => 'Notification dispatched.',
            'data' => $result,
        ], $result['status'] === 'sent' ? 202 : 502);
    }

    public function sendEmail(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'string', Rule::in(['G', 'O'])],
            'to' => ['required'],
            'subject' => ['required', 'string'],
            'title' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'attachment' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['nullable', 'string'],
            'attachments.*.mime_type' => ['nullable', 'string'],
            'attachments.*.content' => ['nullable', 'string'],
            'attachments.*.content_base64' => ['nullable', 'string'],
        ]);

        $result = $this->notificationEngine->dispatch(NotificationDispatchRequest::fromArray([
            'channel' => NotificationChannel::EMAIL,
            'to' => $payload['to'],
            'subject' => $payload['subject'],
            'title' => $payload['title'] ?? null,
            'content' => $payload['content'],
            'attachments' => $payload['attachments'] ?? [],
            'metadata' => ['email_type' => $payload['type']],
        ]));

        return response()->json([
            'message' => 'Mail sent successfully.',
            'data' => $result['provider_response'] ?? $result,
        ], $result['status'] === 'sent' ? 200 : 502);
    }

    public function sendSms(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mnumber' => ['required_without:recipient', 'nullable', 'digits:10'],
            'recipient' => ['required_without:mnumber', 'nullable', 'digits:10'],
            'message' => ['required', 'string'],
            'template_id' => ['required', 'string'],
            'signature' => ['nullable', 'string'],
            'dlt_entity_id' => ['nullable'],
        ]);

        $result = $this->notificationEngine->dispatch(NotificationDispatchRequest::fromArray([
            'channel' => NotificationChannel::SMS,
            'recipient' => $payload['recipient'] ?? $payload['mnumber'],
            'message' => $payload['message'],
            'template_id' => $payload['template_id'],
            'metadata' => array_filter([
                'signature' => $payload['signature'] ?? null,
                'dlt_entity_id' => $payload['dlt_entity_id'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        ]));

        $providerResponse = $result['provider_response']['responses'][0] ?? $result;

        return response()->json([
            'message' => 'SMS sent successfully.',
            'data' => $providerResponse,
        ], $result['status'] === 'sent' ? 200 : 502);
    }
}
