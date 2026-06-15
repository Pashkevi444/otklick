<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо с одноразовым кодом восстановления пароля. Отправляется через очередь.
 */
final class PasswordResetCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Код восстановления пароля — Отклик');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
            with: ['code' => $this->code, 'ttlMinutes' => $this->ttlMinutes],
        );
    }
}
