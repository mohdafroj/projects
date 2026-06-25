<?php

namespace App\Modules\Notifications\Services;

use App\Mail\GenericNotificationMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailNotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array
    {
        if (! Config::boolean('services.delivery_channels.send_email')) {
            throw new RuntimeException('Email delivery is disabled.');
        }

        $mailer = ($payload['type'] ?? 'G') === 'O' ? 'smtp_otp' : 'smtp';
        $recipients = $this->normaliseRecipients($payload['to']);
        $attachments = $this->normaliseAttachments($payload);

        Log::channel(config('logging.default'))->info('notification.email.requested', [
            'mailer' => $mailer,
            'recipient_count' => count($recipients),
            'subject' => $payload['subject'],
        ]);

        Mail::mailer($mailer)->to($recipients)->send(new GenericNotificationMail(
            subjectLine: (string) $payload['subject'],
            title: (string) ($payload['title'] ?? ''),
            contentText: (string) $payload['content'],
            notificationAttachments: $attachments,
        ));

        return [
            'mailer' => $mailer,
            'recipients' => $recipients,
            'attachment_count' => count($attachments),
        ];
    }

    /**
     * @param  mixed  $rawRecipients
     * @return array<int, string>
     */
    private function normaliseRecipients(mixed $rawRecipients): array
    {
        if (is_string($rawRecipients)) {
            $decoded = json_decode($rawRecipients, true);
            if (is_array($decoded)) {
                return array_values(array_map('strval', $decoded));
            }

            return [trim($rawRecipients)];
        }

        if (is_array($rawRecipients)) {
            return array_values(array_map('strval', $rawRecipients));
        }

        throw new RuntimeException('Invalid recipient payload.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name?: string, mime_type?: string, content?: string, content_base64?: string}>
     */
    private function normaliseAttachments(array $payload): array
    {
        $attachments = $payload['attachments'] ?? [];

        if (is_string($payload['attachment'] ?? null) && $payload['attachment'] !== '') {
            $decoded = base64_decode($payload['attachment'], true);
            if ($decoded !== false) {
                $legacyPayload = json_decode($decoded, true);
                if (is_array($legacyPayload)) {
                    $attachments = is_list($legacyPayload) ? $legacyPayload : [$legacyPayload];
                }
            }
        }

        if (! is_array($attachments)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $attachment): ?array {
            if (! is_array($attachment)) {
                return null;
            }

            return [
                'name' => isset($attachment['name']) ? (string) $attachment['name'] : null,
                'mime_type' => isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null,
                'content' => isset($attachment['content']) ? (string) $attachment['content'] : null,
                'content_base64' => isset($attachment['content_base64']) ? (string) $attachment['content_base64'] : null,
            ];
        }, $attachments)));
    }
}
