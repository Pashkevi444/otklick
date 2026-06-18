<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Channels\ChannelGatewayResolver;
use App\DTO\ReplyKeyboard;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Tenancy\TenantInitializer;
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
        MessageRepositoryInterface $messages,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $gateways, $messages): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null) {
                return;
            }

            // Бросит при сбое — очередь повторит с бэкоффом (для того и job).
            $gateways->for($channel->type)->send($channel, $this->chatId, $this->text, $this->keyboard);

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
            app(MessageRepositoryInterface::class)->markStatusById($this->messageId, MessageStatus::Failed);

            $conversations = app(ConversationRepositoryInterface::class);
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
