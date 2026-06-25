<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{name?: string, mime_type?: string, content?: string, content_base64?: string}>  $attachments
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $title,
        public readonly string $contentText,
        public readonly array $notificationAttachments = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.generic-notification',
            with: [
                'title' => $this->title,
                'contentText' => $this->contentText,
            ],
        );
    }

    public function build(): static
    {
        $mail = $this->view('mail.generic-notification', [
            'title' => $this->title,
            'contentText' => $this->contentText,
        ])->subject($this->subjectLine);

        foreach ($this->notificationAttachments as $attachment) {
            $rawContent = $attachment['content_base64'] ?? null;
            if ($rawContent === null && isset($attachment['content'])) {
                $rawContent = base64_encode((string) $attachment['content']);
            }

            if ($rawContent === null) {
                continue;
            }

            $decoded = base64_decode($rawContent, true);
            if ($decoded === false) {
                continue;
            }

            $mail->attachData(
                $decoded,
                $attachment['name'] ?? 'attachment.bin',
                array_filter([
                    'mime' => $attachment['mime_type'] ?? null,
                ]),
            );
        }

        return $mail;
    }
}
