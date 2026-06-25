<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо с кодом подтверждения смены e-mail. Отправляется на НОВЫЙ адрес.
 */
final class EmailChangeCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Код подтверждения новой почты — Отклик');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-change-code',
            with: ['code' => $this->code, 'ttlMinutes' => $this->ttlMinutes],
        );
    }
}
