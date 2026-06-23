<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChannelType;
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
    public function __construct(
        private ClientRepositoryInterface $clients,
        private ConversationRepositoryInterface $conversations,
        private ClientIdentityRepositoryInterface $identities,
    ) {}

    /**
     * Гарантирует, что у лида есть карточка клиента: переиспользует привязанную или
     * найденную по нативной идентичности канала, иначе СОЗДАЁТ новую. Привязывает
     * лид и фиксирует идентичность. Контакты в карточку пишут record*-методы;
     * читаются через `Conversation::display*`. Вызывается ПЕРВЫМ на входящем.
     */
    /**
     * Узнаёт ВЕРНУВШЕГОСЯ клиента по нативной идентичности канала и привязывает к
     * диалогу. Новую карточку НЕ создаёт — клиент появляется только когда отданы
     * контакты (см. {@see ensureClient}, зовётся из record*-методов). Вызывается
     * первым на входящем.
     */
    public function attachClient(Conversation $conversation): void
    {
        $client = $this->findExisting($conversation);
        if ($client !== null) {
            $this->link($conversation, $client);
        }
    }

    /**
     * Существующая карточка клиента для диалога (привязанная или узнанная по
     * нативной идентичности / нику Telegram). Без создания — null, если не узнан.
     */
    private function findExisting(Conversation $conversation): ?Client
    {
        if ($conversation->client_id !== null) {
            $client = $this->clients->find($conversation->client_id);
            if ($client !== null) {
                return $client;
            }
        }

        $type = $conversation->channel?->type;
        $identity = $conversation->external_chat_id;
        if ($type !== null && $identity !== '') {
            $clientId = $this->identities->findClientId($type, $identity);
            if ($clientId !== null) {
                $client = $this->clients->find($clientId);
                if ($client !== null) {
                    return $client;
                }
            }
        }

        // Легаси-мост: chat_id ещё не записан, но есть карточка с этим ником Telegram.
        $telegram = $this->telegramUsername($conversation->contact_ref);
        if ($type === ChannelType::Telegram && $telegram !== null) {
            return $this->clients->findByTelegramUsername($telegram);
        }

        return null;
    }

    /**
     * Карточка клиента для диалога: узнанная/привязанная или СОЗДАННАЯ. Зовётся при
     * первом захваченном контакте (имя/телефон) — раньше карточки нет.
     */
    private function ensureClient(Conversation $conversation): Client
    {
        $client = $this->findExisting($conversation);
        if ($client === null) {
            $client = $this->clients->create([
                'first_channel_type' => $conversation->channel?->type?->value,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }
        $this->link($conversation, $client);

        return $client;
    }

    /** Привязывает карточку к диалогу + фиксирует нативную идентичность и ник. */
    private function link(Conversation $conversation, Client $client): void
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

    /** Записывает имя в карточку клиента (если ещё пустое). */
    public function recordName(Conversation $conversation, string $name): void
    {
        if ($name === '') {
            return;
        }
        // Имя — это контакт: карточка создаётся здесь, если её ещё нет.
        $client = $this->ensureClient($conversation);
        if ($client->name === null || $client->name === '') {
            $this->clients->update($client, ['name' => $name, 'last_seen_at' => now()]);
        }
    }

    /**
     * Записывает телефон в карточку клиента. Телефон совпал с ДРУГОЙ карточкой →
     * склейка (один человек на двух каналах): текущая карточка вливается в неё.
     */
    public function recordPhone(Conversation $conversation, string $phone): void
    {
        if ($phone === '') {
            return;
        }

        // Телефон уже есть у карточки → это ВЕРНУВШИЙСЯ клиент: узнаём её (склеивая
        // текущую, если успела появиться), не плодим дубль.
        $byPhone = $this->clients->findByPhone($phone);
        if ($byPhone !== null) {
            $current = $this->findExisting($conversation);
            if ($current !== null && $current->id !== $byPhone->id) {
                $this->mergeInto($current, $byPhone);
            }
            $this->link($conversation, $byPhone);
            $this->clients->update($byPhone, ['last_seen_at' => now()]);

            return;
        }

        // Телефон новый — это контакт: карточка создаётся здесь, если её ещё нет.
        $client = $this->ensureClient($conversation);
        $this->clients->update($client, ['phone' => $phone, 'last_seen_at' => now()]);
    }

    /** Записывает email в карточку клиента (если ещё пустой). */
    public function recordEmail(Conversation $conversation, string $email): void
    {
        if ($email === '') {
            return;
        }
        // Почта приходит вместе с именем/телефоном в контактной форме — карточка к
        // этому моменту обычно уже есть; на всякий случай гарантируем.
        $client = $this->ensureClient($conversation);
        if ($client->email === null || $client->email === '') {
            $this->clients->update($client, ['email' => $email]);
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
