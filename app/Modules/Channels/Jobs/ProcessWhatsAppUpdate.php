<?php

declare(strict_types=1);

namespace App\Modules\Channels\Jobs;

use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Services\ImageRecognitionService;
use App\Modules\Channels\Services\TelegramRelayService;
use App\Modules\Channels\Services\VoiceTranscriptionService;
use App\Modules\Channels\WhatsApp\WhatsAppGateway;
use App\Modules\Conversations\Services\IncomingMessageService;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Асинхронная обработка входящего WhatsApp (Green API): команда `whatsapp:poll`
 * только кладёт задачу, вся работа — здесь, в воркере Horizon.
 *
 * Переустанавливает тенант-контекст, парсит уведомление и передаёт в общую
 * бизнес-логику ({@see IncomingMessageService}). Операторского моста у WhatsApp
 * нет — операторы сидят в Telegram (см. {@see TelegramRelayService}).
 */
final class ProcessWhatsAppUpdate implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * @param  array<string, mixed>  $update  тело уведомления Green API
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
        WhatsAppGateway $whatsapp,
        VoiceTranscriptionService $voice,
        ImageRecognitionService $image,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $messages, $whatsapp, $voice, $image): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null || ! $channel->is_active) {
                return;
            }

            // Бизнес заблокирован или истёк оплаченный доступ — бот не отвечает.
            if ($channel->tenant === null || ! $channel->tenant->hasActiveAccess()) {
                return;
            }

            $parsed = $whatsapp->parseMessage($this->update);

            if ($parsed === null) {
                return;
            }

            $text = $parsed['text'];

            // Пустой текст — возможно голосовое: расшифровываем в текст (STT).
            if ($text === '') {
                $text = $voice->transcribe($channel, $this->update) ?? '';
            }

            // Фото в апдейте — распознаём (vision) и приклеиваем к тексту/подписи:
            // подпись клиента обрабатывается ВМЕСТЕ с фото. Нет фото — шаг пропускаем.
            $text = $image->augment($channel, $this->update, $text);

            if ($text === '') {
                return;
            }

            $messages->handle($channel, new IncomingMessage(
                externalChatId: $parsed['chatId'],
                externalMessageId: $parsed['id'],
                text: $text,
                contactName: null,
                contactRef: null,
                raw: $this->update,
            ));
        });
    }
}
