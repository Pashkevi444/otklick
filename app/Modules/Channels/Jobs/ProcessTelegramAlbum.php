<?php

declare(strict_types=1);

namespace App\Modules\Channels\Jobs;

use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Services\ImageRecognitionService;
use App\Modules\Channels\Services\TelegramRelayService;
use App\Modules\Channels\Telegram\TelegramAlbumBuffer;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Отложенная склейка Telegram-альбома: {@see ProcessTelegramUpdate} буферизует
 * фото группы и планирует эту задачу с задержкой. Здесь забираем весь буфер,
 * скачиваем и распознаём все фото и отдаём ОДНО сообщение боту — чтобы на альбом
 * был один ответ, а не по реплике на каждое фото.
 */
final class ProcessTelegramAlbum implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $channelId,
        public readonly string $mediaGroupId,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        ChannelRepositoryInterface $channels,
        ConversationsApi $messages,
        TelegramRelayService $relay,
        ImageRecognitionService $image,
        TelegramAlbumBuffer $buffer,
        TelegramGateway $telegram,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $messages, $relay, $image, $buffer, $telegram): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null || ! $channel->is_active) {
                return;
            }

            if ($channel->tenant === null || ! $channel->tenant->hasActiveAccess()) {
                return;
            }

            $entries = $buffer->pull($this->channelId, $this->mediaGroupId);

            if ($entries === []) {
                return;
            }

            // Скачиваем все фото альбома (каждый буфер-entry — одно фото с размерами)
            // и собираем в единый список для распознавания.
            $chatId = null;
            $messageId = null;
            $from = [];
            $images = [];

            foreach ($entries as $entry) {
                $chatId ??= $entry['chat_id'] ?? null;
                $messageId ??= $entry['message_id'] ?? null;
                if ($from === [] && is_array($entry['from'] ?? null)) {
                    $from = $entry['from'];
                }

                $downloaded = $telegram->downloadImages($channel, ['message' => [
                    'photo' => $entry['photo'] ?? [],
                    'caption' => $entry['caption'] ?? '',
                ]]);

                foreach ($downloaded as $img) {
                    $images[] = $img;
                }
            }

            if ($chatId === null || $messageId === null) {
                return;
            }

            // Описание всех фото одним вводом; vision выключен/не распознал → альбом
            // игнорируем (как одиночное фото без распознавания).
            $text = $image->describeAll($channel, $images);

            if ($text === null || $text === '') {
                return;
            }

            $incoming = new IncomingMessage(
                externalChatId: (string) $chatId,
                externalMessageId: (string) $messageId,
                text: $text,
                contactName: null,
                contactRef: $this->accountLink($from),
                raw: ['media_group_id' => $this->mediaGroupId, 'photos' => count($images)],
            );

            // Диалог в режиме «нужен человек» — мост к оператору, ИИ молчит.
            if ($relay->relayClientIfNeedsHuman($channel, $incoming)) {
                return;
            }

            $messages->handle($channel, $incoming);
            $relay->forwardEscalation($channel, $incoming);
        });
    }

    /**
     * Ссылка на аккаунт клиента (как в {@see ProcessTelegramUpdate}): только при
     * заданном username (t.me/<username>); по числовому id публичной ссылки нет.
     *
     * @param  array<string, mixed>  $from
     */
    private function accountLink(array $from): ?string
    {
        $username = $from['username'] ?? null;

        return is_string($username) && $username !== '' ? 'https://t.me/'.$username : null;
    }
}
