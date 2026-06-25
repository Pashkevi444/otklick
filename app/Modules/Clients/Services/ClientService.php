<?php

declare(strict_types=1);

namespace App\Modules\Clients\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Repositories\Contracts\ClientIdentityRepositoryInterface;
use App\Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\UserNotificationType;
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
    public function __construct(
        private ClientRepositoryInterface $clients,
        private ConversationsApi $conversations,
        private ClientIdentityRepositoryInterface $identities,
        private NotificationsApi $notifications,
    ) {}

    /**
     * Узнаёт ВЕРНУВШЕГОСЯ клиента (по привязке/нативной идентичности канала/нику
     * Telegram) и привязывает к лиду. Новому клиенту карточку НЕ создаём — пустых
     * «Без имени» не плодим: карточка появится лениво ({@see ensureClient}), когда
     * клиент оставит контакт (имя/телефон/email) через record*. Вызывается ПЕРВЫМ.
     */
    public function attachClient(Conversation $conversation): void
    {
        $client = $this->findExisting($conversation);

        if ($client !== null) {
            $this->bind($conversation, $client);
        }
    }

    /**
     * Карточка клиента для лида: найденная/привязанная или СОЗДАННАЯ (с уведомлением
     * сотрудникам). Зовётся из record*, когда есть что сохранить, — тогда и заводим
     * клиента, а не на пустом «здравствуйте».
     */
    private function ensureClient(Conversation $conversation): Client
    {
        $client = $this->clientOf($conversation) ?? $this->findExisting($conversation);

        if ($client === null) {
            $client = $this->clients->create([
                'first_channel_type' => $conversation->channel?->type?->value,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            // Новый контакт — уведомляем сотрудников с доступом к базе клиентов.
            $this->notifications->notify(
                UserNotificationType::NewClient,
                'Новый клиент',
                null,
                route('cabinet.clients.show', $client->id, false),
                'client',
                (string) $client->id,
            );
        }

        $this->bind($conversation, $client);

        return $client;
    }

    /** Находит карточку вернувшегося (привязка → идентичность канала → ник Telegram). */
    private function findExisting(Conversation $conversation): ?Client
    {
        $type = $conversation->channel?->type;
        $identity = $conversation->external_chat_id;
        $telegram = $this->telegramUsername($conversation->contact_ref);

        if ($conversation->client_id !== null) {
            $client = $this->clients->find($conversation->client_id);
            if ($client !== null) {
                return $client;
            }
        }
        if ($type !== null && $identity !== '') {
            $clientId = $this->identities->findClientId($type, $identity);
            if ($clientId !== null && ($client = $this->clients->find($clientId)) !== null) {
                return $client;
            }
        }
        // Легаси-мост: chat_id ещё не записан, но есть карточка с этим ником Telegram.
        if ($type === ChannelType::Telegram && $telegram !== null) {
            return $this->clients->findByTelegramUsername($telegram);
        }

        return null;
    }

    /** Привязывает карточку к лиду + фиксирует идентичность канала и ник Telegram. */
    private function bind(Conversation $conversation, Client $client): void
    {
        if ($conversation->client_id !== $client->id) {
            $this->conversations->setClientId($conversation, $client->id);
        }
        // Держим связь загруженной: display*/record* читают свежую карточку, не stale.
        $conversation->setRelation('client', $client);

        $type = $conversation->channel?->type;
        $identity = $conversation->external_chat_id;
        if ($type !== null && $identity !== '') {
            $this->identities->link($client->id, $type, $identity);
        }

        // Ник Telegram из ссылки на аккаунт (приходит из канала без спроса).
        $telegram = $this->telegramUsername($conversation->contact_ref);
        if ($telegram !== null && ($client->telegram_username === null || $client->telegram_username === '')) {
            $this->clients->update($client, ['telegram_username' => $telegram, 'last_seen_at' => now()]);
        }
    }

    /** Записывает имя в карточку клиента (создаёт карточку, если ещё нет). */
    public function recordName(Conversation $conversation, string $name): void
    {
        if ($name === '') {
            return;
        }

        $client = $this->ensureClient($conversation);
        if ($client->name === null || $client->name === '') {
            $this->clients->update($client, ['name' => $name, 'last_seen_at' => now()]);
        }
    }

    /**
     * Записывает телефон в карточку клиента (создаёт карточку, если ещё нет). Телефон
     * совпал с ДРУГОЙ карточкой → склейка (один человек на двух каналах): текущая
     * карточка вливается в неё.
     */
    public function recordPhone(Conversation $conversation, string $phone): void
    {
        if ($phone === '') {
            return;
        }

        $client = $this->ensureClient($conversation);

        $byPhone = $this->clients->findByPhone($phone);
        if ($byPhone !== null && $byPhone->id !== $client->id) {
            $this->mergeInto($client, $byPhone); // телефон у другой карточки → склейка
            $this->conversations->setClientId($conversation, $byPhone->id);
            $conversation->setRelation('client', $byPhone);
        } else {
            // Ставим/обновляем телефон (капча зовёт только при пустом; подтверждение
            // записи — при смене номера вернувшимся клиентом, тут нужна перезапись).
            $this->clients->update($client, ['phone' => $phone, 'last_seen_at' => now()]);
        }
    }

    /** Записывает email в карточку клиента (создаёт карточку, если ещё нет). */
    public function recordEmail(Conversation $conversation, string $email): void
    {
        if ($email === '') {
            return;
        }

        $client = $this->ensureClient($conversation);
        if ($client->email === null || $client->email === '') {
            $this->clients->update($client, ['email' => $email]);
        }
    }

    private function clientOf(Conversation $conversation): ?Client
    {
        if ($conversation->client_id === null) {
            return null;
        }

        // Переиспользуем уже загруженную карточку (тот же инстанс, что и display*),
        // чтобы запись record* была видна сразу, без stale-relation.
        if ($conversation->relationLoaded('client')) {
            $loaded = $conversation->getRelation('client');
            if ($loaded instanceof Client && $loaded->getKey() === $conversation->client_id) {
                return $loaded;
            }
        }

        $client = $this->clients->find($conversation->client_id);
        if ($client !== null) {
            $conversation->setRelation('client', $client);
        }

        return $client;
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
