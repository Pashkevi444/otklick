<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Channels\Max\MaxGateway;
use App\DTO\IncomingMessage;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\IncomingMessageService;
use App\Services\TelegramRelayService;
use App\Services\VoiceTranscriptionService;
use App\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Асинхронная обработка апдейта MAX (long polling): команда `max:poll` только
 * кладёт задачу, вся работа — здесь, в воркере Horizon.
 *
 * Задача переустанавливает тенант-контекст из переданного tenant_id, парсит
 * апдейт и передаёт в общую бизнес-логику ({@see IncomingMessageService}).
 * Операторского моста у MAX нет — операторы сидят в Telegram (см.
 * {@see TelegramRelayService}).
 */
final class ProcessMaxUpdate implements ShouldQueue
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
        MaxGateway $max,
        VoiceTranscriptionService $voice,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $messages, $max, $voice): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null || ! $channel->is_active) {
                return;
            }

            // Бизнес заблокирован или истёк оплаченный доступ — бот не отвечает.
            if ($channel->tenant === null || ! $channel->tenant->hasActiveAccess()) {
                return;
            }

            // Нажатие inline-кнопки (кликабельный календарь мастера записи)
            // приходит как message_callback: payload = выбор клиента, подаём его
            // как обычный ввод и гасим «часики» (answerCallback).
            $callback = $max->parseCallback($this->update);
            if ($callback !== null) {
                $messages->handle($channel, new IncomingMessage(
                    externalChatId: $callback['chatId'],
                    externalMessageId: $callback['id'],
                    text: $callback['text'],
                    contactName: null,
                    contactRef: null,
                    raw: $this->update,
                ));
                $max->answerCallback($channel, $callback['callbackId']);

                return;
            }

            $parsed = $max->parseMessage($this->update);

            if ($parsed === null) {
                return;
            }

            $text = $parsed['text'];

            // Пустой текст — возможно голосовое: распознаём (STT) и подставляем.
            if ($text === '') {
                $text = $voice->transcribe($channel, $this->update);

                if ($text === null || $text === '') {
                    return;
                }
            }

            $messages->handle($channel, new IncomingMessage(
                externalChatId: $parsed['chatId'],
                externalMessageId: $parsed['id'],
                text: $text,
                // Имя НЕ берём из аккаунта MAX — его подставит ContactCapture из
                // того, как клиент представится. Публичной ссылки на профиль нет.
                contactName: null,
                contactRef: null,
                raw: $this->update,
            ));
        });
    }
}
