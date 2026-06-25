<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\NotificationDispatch;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SDS\CommonCore\Notifications\NotificationChannel;
use SDS\CommonCore\Notifications\NotificationDispatchRequest;
use Throwable;

class NotificationEngineService
{
    public function __construct(
        private readonly EmailNotificationService $emailService,
        private readonly SmsNotificationService $smsService,
        private readonly WhatsappNotificationService $whatsappService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dispatch(NotificationDispatchRequest $request): array
    {
        $existing = $this->findExistingRecord($request);
        if ($existing !== null) {
            return [
                'notification_id' => (string) $existing->getKey(),
                'status' => (string) $existing->status,
                'channel' => (string) $existing->channel,
                'provider_response' => $existing->provider_response ?? [],
                'idempotent_replay' => true,
            ];
        }

        $notificationId = (string) Str::uuid();
        $record = $this->createRecord($notificationId, $request);

        try {
            $providerResponse = match ($request->channel) {
                NotificationChannel::EMAIL => $this->dispatchEmail($request),
                NotificationChannel::SMS => $this->dispatchSms($request),
                NotificationChannel::WHATSAPP => $this->dispatchWhatsapp($request),
                default => throw new \InvalidArgumentException('Unsupported notification channel.'),
            };

            $status = $this->allProviderCallsSucceeded($providerResponse) ? 'sent' : 'failed';
            $this->updateRecord($record, [
                'status' => $status,
                'provider_response' => $providerResponse,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);

            return [
                'notification_id' => $notificationId,
                'status' => $status,
                'channel' => $request->channel,
                'provider_response' => $providerResponse,
            ];
        } catch (Throwable $exception) {
            $this->updateRecord($record, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            return [
                'notification_id' => $notificationId,
                'status' => 'failed',
                'channel' => $request->channel,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchEmail(NotificationDispatchRequest $request): array
    {
        return $this->emailService->send([
            'type' => (string) ($request->metadata['email_type'] ?? 'G'),
            'to' => array_map(static fn ($recipient): string => $recipient->address, $request->recipients),
            'subject' => $request->subject ?? $request->title ?? 'Notification',
            'title' => $request->title ?? $request->subject ?? '',
            'content' => $request->body,
            'attachments' => array_map(static fn ($attachment): array => $attachment->toArray(), $request->attachments),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchSms(NotificationDispatchRequest $request): array
    {
        $responses = [];
        foreach ($request->recipients as $recipient) {
            $responses[] = $this->smsService->send([
                'recipient' => $recipient->address,
                'message' => $request->body,
                'template_id' => $request->templateId,
                'signature' => $request->metadata['signature'] ?? null,
                'dlt_entity_id' => $request->metadata['dlt_entity_id'] ?? null,
            ]);
        }

        return ['responses' => $responses];
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchWhatsapp(NotificationDispatchRequest $request): array
    {
        $responses = [];
        foreach ($request->recipients as $recipient) {
            $responses[] = $this->whatsappService->send([
                'recipient' => $recipient->address,
                'body' => $request->body,
                'template_id' => $request->templateId,
                'data' => $request->data,
            ]);
        }

        return ['responses' => $responses];
    }

    private function findExistingRecord(NotificationDispatchRequest $request): ?NotificationDispatch
    {
        if ($request->idempotencyKey === null || $request->idempotencyKey === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('notification_dispatches')) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return NotificationDispatch::query()
            ->where('idempotency_key', $request->idempotencyKey)
            ->first();
    }

    private function createRecord(string $notificationId, NotificationDispatchRequest $request): ?NotificationDispatch
    {
        try {
            if (! Schema::hasTable('notification_dispatches')) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return NotificationDispatch::create([
            'id' => $notificationId,
            'channel' => $request->channel,
            'status' => 'pending',
            'producer' => $request->producer,
            'recipients' => array_map(static fn ($recipient): array => $recipient->toArray(), $request->recipients),
            'subject' => $request->subject,
            'body' => $request->body,
            'template_id' => $request->templateId,
            'idempotency_key' => $request->idempotencyKey,
            'metadata' => $request->metadata,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function updateRecord(?NotificationDispatch $record, array $attributes): void
    {
        if ($record === null) {
            return;
        }

        $record->forceFill($attributes)->save();
    }

    /**
     * @param array<string, mixed> $providerResponse
     */
    private function allProviderCallsSucceeded(array $providerResponse): bool
    {
        if (array_key_exists('successful', $providerResponse)) {
            return (bool) $providerResponse['successful'];
        }

        foreach (($providerResponse['responses'] ?? []) as $response) {
            if (! is_array($response) || ! (bool) ($response['successful'] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
