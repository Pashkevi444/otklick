<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\SandboxRecord;
use App\Repositories\Contracts\SandboxRepositoryInterface;

final class EloquentSandboxRepository implements SandboxRepositoryInterface
{
    public function channel(): Channel
    {
        // В активном TestContext scope отдаёт только тестовые строки — реальный
        // веб-канал сюда не попадёт, тестовый переиспользуется между прогонами.
        $channel = Channel::query()->where('type', ChannelType::Web)->first();

        if ($channel !== null) {
            return $channel;
        }

        return Channel::query()->create([
            'type' => ChannelType::Web,
            'external_id' => null,
            'credentials' => [],
            'is_active' => false,
            'settings' => ['sandbox' => true],
        ]);
    }

    public function resetChat(string $externalChatId): void
    {
        // withTest — снимаем scope явно: метод вызывают и вне TestContext.
        $conversations = Conversation::query()->withTest()
            ->where('external_chat_id', $externalChatId)
            ->get(['id', 'client_id']);

        $clientIds = $conversations->pluck('client_id')->filter()->unique()->values()->all();
        $conversationIds = $conversations->pluck('id')->all();

        // Диалоги первыми (каскад убирает сообщения и A/B), затем их клиентов
        // (каскад убирает идентичности). Тестовый канал общий — не трогаем.
        Conversation::query()->withTest()->whereIn('id', $conversationIds)->delete();
        Client::query()->withTest()->whereIn('id', $clientIds)->delete();

        SandboxRecord::query()
            ->whereIn('recordable_id', array_merge($conversationIds, $clientIds))
            ->delete();
    }

    public function purgeForCurrentTenant(): int
    {
        $records = SandboxRecord::query()->get(['recordable_type', 'recordable_id']);

        $ids = static fn (string $table): array => $records
            ->where('recordable_type', $table)
            ->pluck('recordable_id')
            ->all();

        // Удаляем «корни»; зависимые (сообщения, идентичности, A/B) уходят каскадом
        // по FK. Диалоги — до клиентов и каналов.
        $removed = Conversation::query()->withTest()->whereIn('id', $ids('conversations'))->delete();
        $removed += Client::query()->withTest()->whereIn('id', $ids('clients'))->delete();
        $removed += Channel::query()->withTest()->whereIn('id', $ids('channels'))->delete();

        SandboxRecord::query()->delete();

        return $removed;
    }
}
