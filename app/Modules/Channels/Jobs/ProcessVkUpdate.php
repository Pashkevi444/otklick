<?php

declare(strict_types=1);

namespace App\Modules\Channels\Jobs;

use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Services\ImageRecognitionService;
use App\Modules\Channels\Services\TelegramRelayService;
use App\Modules\Channels\Services\VoiceTranscriptionService;
use App\Modules\Channels\Vk\VkGateway;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Асинхронная обработка апдейта VK (Bots Long Poll): команда `vk:poll` только
 * кладёт задачу, вся работа — здесь, в воркере Horizon.
 *
 * Задача переустанавливает тенант-контекст из переданного tenant_id (исполняется
 * в отдельном процессе), парсит апдейт и передаёт в общую бизнес-логику
 * ({@see IncomingMessageService}). Операторского моста у VK нет — операторы
 * сидят в Telegram (см. {@see TelegramRelayService}).
 */
final class ProcessVkUpdate implements ShouldQueue
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
        ConversationsApi $messages,
        VkGateway $vk,
        VoiceTranscriptionService $voice,
        ImageRecognitionService $image,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $messages, $vk, $voice, $image): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null || ! $channel->is_active) {
                return;
            }

            // Бизнес заблокирован или истёк оплаченный доступ — бот не отвечает.
            if ($channel->tenant === null || ! $channel->tenant->hasActiveAccess()) {
                return;
            }

            $parsed = $vk->parseMessage($this->update);

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
                externalChatId: $parsed['peerId'],
                externalMessageId: $parsed['id'],
                text: $text,
                // Имя НЕ берём из аккаунта VK — его подставит ContactCapture из
                // того, как клиент представится. В contactRef — ссылка на профиль.
                contactName: null,
                contactRef: $this->profileLink($parsed['peerId']),
                raw: $this->update,
            ));
        });
    }

    /**
     * Ссылка на профиль VK — только для личных диалогов, где peer_id равен id
     * пользователя. У бесед peer_id ≥ 2_000_000_000 (id чата, не пользователя),
     * у сообщений от имени сообщества from_id отрицательный — публичной ссылки на
     * клиента нет, лучше null, чем заведомо битая ссылка в карточке оператора.
     */
    private function profileLink(string $peerId): ?string
    {
        return ctype_digit($peerId) && (int) $peerId < 2_000_000_000
            ? 'https://vk.com/id'.$peerId
            : null;
    }
}
