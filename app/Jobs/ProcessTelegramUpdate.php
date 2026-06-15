<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTO\IncomingMessage;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\IncomingMessageService;
use App\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Асинхронная обработка апдейта Telegram: вебхук только кладёт задачу
 * (ack < 100 мс), вся работа — здесь, в воркере Horizon.
 *
 * Задача переустанавливает тенант-контекст из переданного tenant_id (она
 * исполняется в отдельном процессе), затем парсит апдейт и передаёт его в
 * бизнес-логику.
 */
final class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * @param  array<string, mixed>  $update
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $channelId,
        public readonly array $update,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        ChannelRepositoryInterface $channels,
        IncomingMessageService $messages,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $messages): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null || ! $channel->is_active) {
                return;
            }

            // Бизнес заблокирован или истёк оплаченный доступ — бот не отвечает.
            if ($channel->tenant === null || ! $channel->tenant->hasActiveAccess()) {
                return;
            }

            $incoming = $this->parse();

            if ($incoming === null) {
                return;
            }

            $messages->handle($channel, $incoming);
        });
    }

    /**
     * Извлекает текстовое сообщение из апдейта. Возвращает null для не-текстовых
     * и служебных апдейтов (Фаза 1 отвечает только на текст).
     */
    private function parse(): ?IncomingMessage
    {
        $message = $this->update['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $text = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if (! is_string($text) || $text === '' || $chatId === null || $messageId === null) {
            return null;
        }

        return new IncomingMessage(
            externalChatId: (string) $chatId,
            externalMessageId: (string) $messageId,
            text: $text,
            contactName: $this->contactName($message['from'] ?? []),
            raw: $this->update,
        );
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function contactName(array $from): ?string
    {
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $username = $from['username'] ?? null;

        return is_string($username) && $username !== '' ? $username : null;
    }
}
