<?php

declare(strict_types=1);

namespace App\Modules\Channels\Jobs;

use App\Modules\Channels\ChannelGatewayResolver;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Повторная доставка ответа бота клиенту, когда синхронная отправка сорвалась
 * (канал недоступен/таймаут). Ретраится с нарастающим бэкоффом, чтобы сообщение
 * ТОЧНО дошло, когда канал восстановится; при успехе помечает сообщение
 * отправленным. Если ретраи исчерпаны — переводит диалог на человека (`failed`),
 * чтобы он не «завис» молча с недоставленной репликой.
 */
final class DeliverBotReply implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** Всего попыток (1 синхронная уже была — это фоновые добивания). */
    public int $tries = 5;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $channelId,
        public readonly string $chatId,
        public readonly string $text,
        public readonly ?ReplyKeyboard $keyboard,
        public readonly string $messageId,
        public readonly string $conversationId,
        /** @var list<string> */
        public readonly array $images = [],
    ) {}

    /**
     * Нарастающий бэкофф между попытками (сек): даём каналу время восстановиться.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(
        TenantInitializer $tenancy,
        ChannelRepositoryInterface $channels,
        ChannelGatewayResolver $gateways,
        ConversationsApi $messages,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $gateways, $messages): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null) {
                return;
            }

            // Бросит при сбое — очередь повторит с бэкоффом (для того и job).
            $gateways->for($channel->type)->send($channel, $this->chatId, $this->text, $this->keyboard, $this->images);

            $messages->markStatusById($this->messageId, MessageStatus::Sent);
            Log::info('delivery.retry_succeeded', ['conversation_id' => $this->conversationId, 'message_id' => $this->messageId]);
        });
    }

    /**
     * Все попытки исчерпаны — сообщение так и не доставлено. Помечаем его как
     * ошибку и переводим диалог на человека, чтобы оператор увидел зависший лид.
     */
    public function failed(Throwable $e): void
    {
        app(TenantInitializer::class)->run($this->tenantId, function () use ($e): void {
            app(ConversationsApi::class)->markStatusById($this->messageId, MessageStatus::Failed);

            $conversations = app(ConversationsApi::class);
            $conversation = $conversations->findForCurrentTenant($this->conversationId);

            if ($conversation !== null) {
                $conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
            }

            Log::error('delivery.failed', [
                'conversation_id' => $this->conversationId,
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        });
    }
}
