<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Conversation;
use App\Repositories\Contracts\ClientIdentityRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * База клиентов: связывает лиды (диалоги) с единой карточкой клиента. Телефон —
 * кросс-канальный ключ идентичности (дедуп клиента между каналами). Узнавание
 * вернувшегося в КОНКРЕТНОМ канале — по нативной идентичности канала
 * (Telegram chat_id, WhatsApp phone@c.us, VK/MAX user id) через client_identities,
 * без переспроса контактов. Память якорится на карточке клиента: удалили клиента —
 * идентичности уходят каскадом, ссылки лидов обнуляются, бот «забывает».
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
        private ClientIdentityRepositoryInterface $identities,
    ) {}

    /**
     * Узнаёт вернувшегося клиента по нативной идентичности канала и заполняет
     * контакты лида из карточки (источник правды), чтобы бот поздоровался по имени
     * и не переспрашивал. Если идентичность не найдена (новый или клиент удалён) —
     * ничего не делает (лид остаётся новым, показывается форма).
     */
    public function recognizeReturning(Conversation $conversation): void
    {
        if ($conversation->client_id !== null) {
            return; // уже привязан
        }

        $type = $conversation->channel?->type;
        $identity = $conversation->external_chat_id;

        if ($type === null || $identity === '') {
            return;
        }

        $clientId = $this->identities->findClientId($type, $identity);
        $client = $clientId !== null ? $this->clients->find($clientId) : null;

        if ($client === null) {
            return;
        }

        $this->conversations->setClientId($conversation, $client->id);

        if ($client->name !== null && $client->name !== '') {
            $this->conversations->setContactName($conversation, $client->name);
        }
        if ($client->phone !== null && $client->phone !== '') {
            $this->conversations->setContactPhone($conversation, $client->phone);
        }
        if ($client->email !== null && $client->email !== '') {
            $this->conversations->setContactEmail($conversation, $client->email);
        }

        $this->clients->update($client, ['last_seen_at' => now()]);
    }

    public function linkConversation(Conversation $conversation): void
    {
        $phone = $conversation->contact_phone;
        if ($phone === null || $phone === '') {
            return; // без телефона карточку не заводим
        }

        $name = in_array($conversation->contact_name, self::PLACEHOLDER_NAMES, true) ? null : $conversation->contact_name;
        $email = $conversation->contact_email;
        $telegram = $this->telegramUsername($conversation->contact_ref);
        $channelType = $conversation->channel?->type;

        $client = $this->clients->findByPhone($phone) ?? $this->clients->create([
            'phone' => $phone,
            'name' => $name,
            'email' => $email,
            'telegram_username' => $telegram,
            'first_channel_type' => $channelType?->value,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->backfill($client, $name, $telegram, $email);

        if ($conversation->client_id !== $client->id) {
            $this->conversations->setClientId($conversation, $client->id);
        }

        // Запоминаем нативную идентичность канала → узнаем этот чат в будущем без телефона.
        if ($channelType !== null && $conversation->external_chat_id !== '') {
            $this->identities->link($client->id, $channelType, $conversation->external_chat_id);
        }
    }

    /**
     * Удаляет клиента и нормализует связи: лиды отвязываются (client_id → null,
     * история лида сохраняется), идентичности каналов уходят каскадом (FK) — бот
     * «забывает» человека. В транзакции, чтобы не осталось частичного состояния.
     */
    public function delete(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            $this->conversations->clearClientLinks($client->id);
            $this->clients->delete($client);
        });
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
