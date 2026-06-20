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
 * База клиентов: КАЖДЫЙ лид (диалог) привязан к карточке клиента (нормализация —
 * имя/телефон принадлежат человеку, а не треду). Клиент заводится при первом
 * контакте и опознаётся по нативной идентичности канала (Telegram chat_id,
 * WhatsApp phone@c.us, VK/MAX user id) через client_identities — без переспроса.
 * Телефон — кросс-канальный ключ СКЛЕЙКИ: совпал у разных карточек → один человек,
 * карточки сливаются. Память якорится на карточке: удалили клиента — идентичности
 * каскадом, ссылки лидов обнуляются, бот «забывает».
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
     * Гарантирует, что у лида есть карточка клиента: переиспользует привязанную или
     * найденную по нативной идентичности канала, иначе СОЗДАЁТ новую. Привязывает
     * лид, фиксирует идентичность и заполняет буфер лида из карточки (узнавание —
     * бот здоровается по имени и не переспрашивает). Вызывается ПЕРВЫМ на входящем.
     */
    public function attachClient(Conversation $conversation): void
    {
        $type = $conversation->channel?->type;
        $identity = $conversation->external_chat_id;

        $client = null;
        if ($conversation->client_id !== null) {
            $client = $this->clients->find($conversation->client_id);
        }
        if ($client === null && $type !== null && $identity !== '') {
            $clientId = $this->identities->findClientId($type, $identity);
            $client = $clientId !== null ? $this->clients->find($clientId) : null;
        }
        if ($client === null) {
            $client = $this->clients->create([
                'first_channel_type' => $type?->value,
                'telegram_username' => $this->telegramUsername($conversation->contact_ref),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        if ($conversation->client_id !== $client->id) {
            $this->conversations->setClientId($conversation, $client->id);
        }
        if ($type !== null && $identity !== '') {
            $this->identities->link($client->id, $type, $identity);
        }

        // Карточка — источник правды: подставляем известные контакты в буфер лида.
        if ($client->name !== null && $client->name !== '') {
            $this->conversations->setContactName($conversation, $client->name);
        }
        if ($client->phone !== null && $client->phone !== '') {
            $this->conversations->setContactPhone($conversation, $client->phone);
        }
        if ($client->email !== null && $client->email !== '') {
            $this->conversations->setContactEmail($conversation, $client->email);
        }
    }

    /**
     * Переносит захваченные в буфере лида контакты в карточку клиента (источник
     * правды для грида/уведомлений). Телефон: совпал с ДРУГОЙ карточкой → склейка
     * (один человек на двух каналах). Вызывается ПОСЛЕ разбора сообщения.
     */
    public function pushToClient(Conversation $conversation): void
    {
        $client = $conversation->client_id !== null ? $this->clients->find($conversation->client_id) : null;
        if ($client === null) {
            return; // attachClient гарантирует клиента; защита
        }

        $phone = $conversation->contact_phone;
        if ($phone !== null && $phone !== '') {
            $byPhone = $this->clients->findByPhone($phone);
            if ($byPhone !== null && $byPhone->id !== $client->id) {
                $this->mergeInto($client, $byPhone); // телефон уже у другого → склейка
                $client = $byPhone;
                $this->conversations->setClientId($conversation, $client->id);
            } elseif ($client->phone === null || $client->phone === '') {
                $this->clients->update($client, ['phone' => $phone]);
            }
        }

        $name = in_array($conversation->contact_name, self::PLACEHOLDER_NAMES, true) ? null : $conversation->contact_name;
        $this->backfill($client, $name, $this->telegramUsername($conversation->contact_ref), $conversation->contact_email);

        if ($conversation->channel?->type !== null && $conversation->external_chat_id !== '') {
            $this->identities->link($client->id, $conversation->channel->type, $conversation->external_chat_id);
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

    /**
     * Сливает карточку $from в $to (один человек, опознанный по телефону между
     * каналами): диалоги и идентичности переезжают на $to, пустые поля $to
     * дозаполняются из $from, $from удаляется. В транзакции.
     */
    private function mergeInto(Client $from, Client $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            $this->backfill($to, $from->name, $from->telegram_username, $from->email);
            $this->conversations->reassignClient($from->id, $to->id);
            $this->identities->reassignClient($from->id, $to->id);
            $this->clients->delete($from);
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
