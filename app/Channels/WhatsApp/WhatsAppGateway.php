<?php

declare(strict_types=1);

namespace App\Channels\WhatsApp;

use App\Channels\Contracts\ChannelGateway;
use App\Channels\Contracts\ReceivesVoice;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\ImageBytes;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Клиент WhatsApp через провайдера Green API. Бизнес привязывает реальный аккаунт
 * WhatsApp по QR в Green API и даёт две креды: idInstance + apiTokenInstance.
 * Отправка — POST sendMessage; приём — long polling receiveNotification (как
 * Telegram/VK/MAX, публичный вебхук не нужен), подтверждение — deleteNotification.
 *
 * URL Green API: {api_url}/waInstance{idInstance}/{method}/{apiToken}.
 */
final readonly class WhatsAppGateway implements ChannelGateway, ReceivesVoice
{
    public function __construct(
        private string $apiUrl,
        private ?string $proxy = null,
    ) {}

    public function provider(): ChannelType
    {
        return ChannelType::WhatsApp;
    }

    /**
     * Базовый HTTP-клиент Green API: через прокси (VPN, обход блокировки), если
     * задан. Все вызовы к Green API и его медиа идут отсюда.
     */
    private function http(): PendingRequest
    {
        $request = Http::connectTimeout(5);

        if ($this->proxy !== null && $this->proxy !== '') {
            $request->withOptions(['proxy' => $this->proxy]);
        }

        return $request;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null, array $images = []): void
    {
        $message = $text;

        // Интерактивных кнопок у WhatsApp/Green API в MVP нет — варианты
        // (кликабельный календарь и т.п.) дописываем простым списком в текст.
        if ($keyboard !== null && ! $keyboard->isEmpty()) {
            $labels = array_merge(...$keyboard->rows);
            $message .= "\n\n".implode("\n", array_map(static fn (string $l): string => '• '.$l, $labels));
        }

        if ($message !== '') {
            $this->http()->asJson()->timeout(15)->retry(2, 300, throw: false)
                ->post($this->url($channel, 'sendMessage'), ['chatId' => $chatId, 'message' => $message])
                ->throw();
        }

        // Картинки шлём ЗАГРУЗКОЙ БАЙТОВ (sendFileByUpload), а не URL: серверы Green
        // API (за рубежом) не всегда скачают URL с РФ-хостинга. Сбой не роняет
        // отправку — что не ушло, отдаём ссылкой.
        $failed = [];
        foreach ($images as $url) {
            try {
                $bytes = ImageBytes::fetch($url);
                if ($bytes === null) {
                    $failed[] = $url;

                    continue;
                }

                $this->http()->timeout(25)->retry(2, 300, throw: false)
                    ->attach('file', $bytes, basename((string) (parse_url($url, PHP_URL_PATH) ?: 'photo.jpg')))
                    ->post($this->url($channel, 'sendFileByUpload'), ['chatId' => $chatId])
                    ->throw();
            } catch (Throwable $e) {
                report($e);
                $failed[] = $url;
            }
        }

        if ($failed !== []) {
            $this->http()->asJson()->timeout(15)->retry(2, 300, throw: false)
                ->post($this->url($channel, 'sendMessage'), ['chatId' => $chatId, 'message' => 'Примеры работ: '.implode(' ', $failed)])
                ->throw();
        }
    }

    /**
     * Статус инстанса Green API: 'authorized' — аккаунт WhatsApp привязан (QR
     * отсканирован) и готов. Используется для валидации при подключении.
     */
    public function stateInstance(Channel $channel): ?string
    {
        $state = $this->http()->asJson()->timeout(10)
            ->get($this->url($channel, 'getStateInstance'))
            ->throw()
            ->json('stateInstance');

        return is_string($state) ? $state : null;
    }

    /**
     * Забирает одно входящее уведомление из очереди Green API (long poll).
     * null — очередь пуста за время ожидания.
     *
     * @return array{receiptId: int, body: array<string, mixed>}|null
     */
    public function receiveNotification(Channel $channel, int $timeout): ?array
    {
        $data = $this->http()->asJson()->timeout($timeout + 10)
            ->get($this->url($channel, 'receiveNotification'), ['receiveTimeout' => $timeout])
            ->throw()
            ->json();

        if (! is_array($data) || ! isset($data['receiptId']) || ! is_array($data['body'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = $data['body'];

        return ['receiptId' => (int) $data['receiptId'], 'body' => $body];
    }

    /**
     * Подтверждает обработку уведомления (убирает из очереди Green API). Звать
     * ВСЕГДА после receiveNotification — иначе очередь забьётся и приём встанет.
     */
    public function deleteNotification(Channel $channel, int $receiptId): void
    {
        $this->http()->asJson()->timeout(10)->retry(1, 300, throw: false)
            ->delete($this->url($channel, 'deleteNotification').'/'.$receiptId);
    }

    /**
     * Извлекает из уведомления Green API конверт входящего сообщения: chatId
     * (адресат ответа — в формате Green API «<номер>@c.us»), текст и id. Текст
     * может быть пустым (голосовое/вложение) — решение по нему принимает джоб.
     * null — если это не входящее сообщение.
     *
     * @param  array<string, mixed>  $body
     * @return array{chatId: string, text: string, id: string}|null
     */
    public function parseMessage(array $body): ?array
    {
        if (($body['typeWebhook'] ?? null) !== 'incomingMessageReceived') {
            return null;
        }

        $chatId = $body['senderData']['chatId'] ?? null;
        if (! is_string($chatId) || $chatId === '') {
            return null;
        }

        $messageData = $body['messageData'] ?? [];
        $text = $messageData['textMessageData']['textMessage']
            ?? $messageData['extendedTextMessageData']['text']
            ?? '';

        return [
            'chatId' => $chatId,
            'text' => is_string($text) ? trim($text) : '',
            'id' => (string) ($body['idMessage'] ?? ''),
        ];
    }

    /**
     * Скачивает голосовое (audioMessage) из уведомления Green API по downloadUrl.
     * WhatsApp-войсы — OGG/Opus, SpeechKit принимает нативно.
     *
     * @param  array<string, mixed>  $body
     */
    public function downloadVoice(Channel $channel, array $body): ?string
    {
        $messageData = $body['messageData'] ?? [];

        if (! in_array($messageData['typeMessage'] ?? null, ['audioMessage', 'voiceMessage'], true)) {
            return null;
        }

        $url = $messageData['fileMessageData']['downloadUrl'] ?? null;
        if (! is_string($url) || $url === '') {
            return null;
        }

        try {
            $audio = $this->http()->timeout(20)->get($url)->throw()->body();

            return $audio !== '' ? $audio : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function url(Channel $channel, string $method): string
    {
        $id = $channel->credential('id_instance');
        $token = $channel->credential('api_token');

        return "{$this->apiUrl}/waInstance{$id}/{$method}/{$token}";
    }
}
