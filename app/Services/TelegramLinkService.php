<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Contracts\MessengerGateway;
use App\Models\Channel;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use Throwable;

/**
 * Привязка Telegram-получателя уведомлений по диплинку. Владелец открывает
 * t.me/<bot>?start=notify_<token>, бот получает «/start notify_<token>» —
 * привязываем chat_id к ожидающему получателю. Вызывается до обычной обработки
 * сообщения (в тенант-контексте).
 */
final readonly class TelegramLinkService
{
    public function __construct(
        private NotificationRecipientRepositoryInterface $recipients,
        private MessengerGateway $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $message
     * @return bool true — апдейт был командой привязки и обработан (не диалог)
     */
    public function tryLink(Channel $channel, array $message): bool
    {
        $text = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;

        if (! is_string($text) || $chatId === null) {
            return false;
        }

        if (preg_match('/^\/start\s+notify_([A-Za-z0-9]+)$/', trim($text), $m) !== 1) {
            return false;
        }

        $recipient = $this->recipients->findByLinkToken($m[1]);

        if ($recipient !== null) {
            $this->recipients->update($recipient, [
                'value' => (string) $chatId,
                'is_active' => true,
                'verified_at' => now(),
                'link_token' => null,
            ]);

            try {
                $this->gateway->send($channel, (string) $chatId, '✅ Уведомления «Отклик» подключены к этому чату.');
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Команда привязки (даже если токен не найден) — не передаём боту как обычный текст.
        return true;
    }
}
