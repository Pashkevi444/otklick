<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Channels\Telegram\TelegramAlbumBuffer;
use App\DTO\IncomingMessage;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\ImageRecognitionService;
use App\Services\IncomingMessageService;
use App\Services\TelegramLinkService;
use App\Services\TelegramRelayService;
use App\Services\VoiceTranscriptionService;
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

    /** Окно ожидания остальных фото альбома перед склейкой (сек). */
    private const int ALBUM_DELAY_SECONDS = 2;

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
        TelegramLinkService $linker,
        TelegramRelayService $relay,
        VoiceTranscriptionService $voice,
        ImageRecognitionService $image,
        TelegramAlbumBuffer $albums,
    ): void {
        $tenancy->run($this->tenantId, function () use ($channels, $messages, $linker, $relay, $voice, $image, $albums): void {
            $channel = $channels->find($this->channelId);

            if ($channel === null || ! $channel->is_active) {
                return;
            }

            // Бизнес заблокирован или истёк оплаченный доступ — бот не отвечает.
            if ($channel->tenant === null || ! $channel->tenant->hasActiveAccess()) {
                return;
            }

            $message = $this->update['message'] ?? null;

            // Подключение уведомлений по диплинку «/start notify_<token>».
            if (is_array($message) && $linker->tryLink($channel, $message)) {
                return;
            }

            // Сообщение от оператора (его chat_id — среди telegram-получателей):
            // команда или ответ клиенту через мост.
            $chatId = is_array($message) ? ($message['chat']['id'] ?? null) : null;
            if ($chatId !== null && $relay->isOperator((string) $chatId)) {
                $relay->handleOperatorMessage($channel, $message);

                return;
            }

            // Фото-альбом (несколько фото общим media_group_id) приходит отдельными
            // апдейтами — буферизуем и отдаём одним сообщением (дебаунс), чтобы бот
            // ответил на альбом один раз.
            if (is_array($message) && $this->bufferAlbumPhoto($message, $albums)) {
                return;
            }

            $incoming = $this->parse($voice, $image, $channel);

            if ($incoming === null) {
                return;
            }

            // Диалог в режиме «нужен человек» — мост к оператору, ИИ молчит.
            if ($relay->relayClientIfNeedsHuman($channel, $incoming)) {
                return;
            }

            $messages->handle($channel, $incoming);

            // Если этим сообщением диалог эскалировался — перешлём его операторам.
            $relay->forwardEscalation($channel, $incoming);
        });
    }

    /**
     * Фото в составе альбома (есть `media_group_id`) — кладём в буфер и один раз
     * планируем отложенную склейку. Возвращает true, если это фото альбома (и оно
     * забуферизовано — обычный разбор пропускаем). false — не альбомное фото.
     *
     * @param  array<string, mixed>  $message
     */
    private function bufferAlbumPhoto(array $message, TelegramAlbumBuffer $albums): bool
    {
        $groupId = $message['media_group_id'] ?? null;
        $photo = $message['photo'] ?? null;

        if (! is_string($groupId) || $groupId === '' || ! is_array($photo) || $photo === []) {
            return false;
        }

        $albums->add($this->channelId, $groupId, [
            'photo' => $photo,
            'caption' => is_string($message['caption'] ?? null) ? $message['caption'] : '',
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $message['chat']['id'] ?? null,
            'from' => is_array($message['from'] ?? null) ? $message['from'] : [],
        ]);

        if ($albums->shouldSchedule($this->channelId, $groupId)) {
            ProcessTelegramAlbum::dispatch($this->tenantId, $this->channelId, $groupId)
                ->delay(now()->addSeconds(self::ALBUM_DELAY_SECONDS));
        }

        return true;
    }

    /**
     * Извлекает текстовое сообщение из апдейта. Возвращает null для не-текстовых
     * и служебных апдейтов (Фаза 1 отвечает только на текст).
     */
    private function parse(VoiceTranscriptionService $voice, ImageRecognitionService $image, Channel $channel): ?IncomingMessage
    {
        $message = $this->update['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if ($chatId === null || $messageId === null) {
            return null;
        }

        $text = is_string($message['text'] ?? null) ? $message['text'] : '';

        // Нет текста — возможно голосовое: расшифровываем в текст (STT).
        if ($text === '') {
            $text = $voice->transcribe($channel, $this->update) ?? '';
        }

        // Фото в сообщении — распознаём (vision) и приклеиваем к тексту/подписи:
        // подпись клиента обрабатывается ВМЕСТЕ с фото. Нет фото — шаг пропускаем.
        $text = $image->augment($channel, $this->update, $text);

        if ($text === '') {
            return null;
        }

        return new IncomingMessage(
            externalChatId: (string) $chatId,
            externalMessageId: (string) $messageId,
            text: $text,
            // Имя НЕ берём из аккаунта Telegram — его подставит ContactCapture из
            // того, как клиент представится сам. В contactRef — ссылка на аккаунт.
            contactName: null,
            contactRef: $this->accountLink($message['from'] ?? []),
            raw: $this->update,
        );
    }

    /**
     * Ссылка на аккаунт клиента для деталей диалога. Публичная ссылка возможна
     * только при заданном username (t.me/<username>); по числовому id ссылки нет.
     *
     * @param  array<string, mixed>  $from
     */
    private function accountLink(array $from): ?string
    {
        $username = $from['username'] ?? null;

        return is_string($username) && $username !== '' ? 'https://t.me/'.$username : null;
    }
}
