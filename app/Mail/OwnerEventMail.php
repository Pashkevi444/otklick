<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTO\OwnerNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо-уведомление владельцу о событии в диалоге.
 */
final class OwnerEventMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly OwnerNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-event',
            with: ['body' => $this->notification->body],
        );
    }
}
