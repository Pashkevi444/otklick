<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Conversation;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;

/**
 * База клиентов: связывает лиды (диалоги) с единой карточкой клиента по телефону.
 * Телефон — ключ идентичности; без него связать нельзя (карточка не создаётся).
 * Недостающие поля карточки дозаполняются из диалога (имя, ник Telegram, первый
 * канал), но уже заполненные значения не затираются.
 */
/**
 * Не final намеренно — мокается в юнит-тестах вызывающих сервисов (ContactCapture).
 */
class ClientService
{
    private const array PLACEHOLDER_NAMES = [null, '', 'Гость', 'Гость сайта'];

    public function __construct(
        private ClientRepositoryInterface $clients,
        private ConversationRepositoryInterface $conversations,
    ) {}

    public function linkConversation(Conversation $conversation): void
    {
        $phone = $conversation->contact_phone;
        if ($phone === null || $phone === '') {
            return; // без телефона карточку не заводим
        }

        $name = in_array($conversation->contact_name, self::PLACEHOLDER_NAMES, true) ? null : $conversation->contact_name;
        $email = $conversation->contact_email;
        $telegram = $this->telegramUsername($conversation->contact_ref);
        $channelType = $conversation->channel?->type->value;

        $client = $this->clients->findByPhone($phone) ?? $this->clients->create([
            'phone' => $phone,
            'name' => $name,
            'email' => $email,
            'telegram_username' => $telegram,
            'first_channel_type' => $channelType,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->backfill($client, $name, $telegram, $email);

        if ($conversation->client_id !== $client->id) {
            $this->conversations->setClientId($conversation, $client->id);
        }
    }

    /** Дозаполняет пустые поля карточки и двигает «последний контакт». */
    private function backfill(Client $client, ?string $name, ?string $telegram, ?string $email = null): void
    {
        $updates = ['last_seen_at' => now()];

        if (($client->name === null || $client->name === '') && $name !== null) {
            $updates['name'] = $name;
        }

        if (($client->telegram_username === null || $client->telegram_username === '') && $telegram !== null) {
            $updates['telegram_username'] = $telegram;
        }

        if (($client->email === null || $client->email === '') && $email !== null) {
            $updates['email'] = $email;
        }

        $this->clients->update($client, $updates);
    }

    /** Ник Telegram из ссылки на аккаунт (contact_ref = https://t.me/<username>). */
    private function telegramUsername(?string $contactRef): ?string
    {
        if ($contactRef === null || ! str_starts_with($contactRef, 'https://t.me/')) {
            return null;
        }

        $username = trim(str_replace('https://t.me/', '', $contactRef), '/');

        return $username !== '' ? $username : null;
    }
}
