<?php

declare(strict_types=1);

namespace App\Modules\Channels\WhatsApp;

use App\Modules\Channels\Contracts\ChannelGateway;
use App\Modules\Channels\Contracts\ReceivesImage;
use App\Modules\Channels\Contracts\ReceivesVoice;
use App\Modules\Channels\Data\IncomingImage;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Support\PollFailureLog;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Enums\ChannelType;
use App\Shared\Support\ImageBytes;
use App\Shared\Support\ImageMime;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
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
final readonly class WhatsAppGateway implements ChannelGateway, ReceivesImage, ReceivesVoice
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
        return Http::connectTimeout(5)->withOptions($this->proxyOptions());
    }

    /**
     * Сетевые опции Green API: через прокси (VPN, обход блокировки), если задан.
     * Иначе пусто — `withOptions([])` это no-op.
     *
     * @return array<string, mixed>
     */
    private function proxyOptions(): array
    {
        return ($this->proxy !== null && $this->proxy !== '') ? ['proxy' => $this->proxy] : [];
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

        return $this->parseNotification($data);
    }

    /**
     * КОНКУРЕНТНЫЙ приём: ПЕРВЫЙ receiveNotification у ВСЕХ каналов ОДНОВРЕМЕННО
     * (Http::pool), чтобы простаивающий канал не держал общий круг блокирующим
     * long-poll'ом (задержка росла линейно с числом каналов). Дальнейший дренаж
     * очереди (timeout=0) поллер делает per-channel уже после — он быстрый. Канал со
     * сбоем (исключение/не-2xx) отсутствует в результате (сбой залогирован) — повторит
     * на следующем круге, не роняя остальных. Ключ результата = id канала; значение —
     * уведомление или null (очередь пуста за время ожидания).
     *
     * @param  Collection<int, Channel>  $channels
     * @return array<string, array{receiptId: int, body: array<string, mixed>}|null>
     */
    public function receiveNotificationPool(Collection $channels, int $timeout): array
    {
        $responses = Http::pool(function (Pool $pool) use ($channels, $timeout): array {
            $requests = [];
            foreach ($channels as $channel) {
                $requests[] = $pool->as((string) $channel->id)
                    ->asJson()
                    ->withOptions($this->proxyOptions())
                    ->connectTimeout(10)
                    ->timeout($timeout + 10)
                    ->get($this->url($channel, 'receiveNotification'), ['receiveTimeout' => $timeout]);
            }

            return $requests;
        });

        $out = [];
        foreach ($responses as $channelId => $response) {
            if ($response instanceof Response && $response->successful()) {
                $out[(string) $channelId] = $this->parseNotification($response->json());

                continue;
            }

            PollFailureLog::record('whatsapp', (string) $channelId, $response);
        }

        return $out;
    }

    /**
     * Разбирает ответ receiveNotification Green API в конверт уведомления.
     * null — очередь пуста (Green API отдаёт тело null) либо ответ без receiptId/body.
     *
     * @return array{receiptId: int, body: array<string, mixed>}|null
     */
    private function parseNotification(mixed $data): ?array
    {
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

    /**
     * Скачивает фото из уведомления Green API (`imageMessage`). Одно уведомление =
     * одна картинка (`fileMessageData.downloadUrl`, подпись — `caption`); группировки
     * нескольких фото в Green API нет. Тип уточняем по байтам.
     *
     * @param  array<string, mixed>  $body
     * @return list<IncomingImage>
     */
    public function downloadImages(Channel $channel, array $body): array
    {
        $messageData = $body['messageData'] ?? [];

        if (($messageData['typeMessage'] ?? null) !== 'imageMessage') {
            return [];
        }

        $fileData = $messageData['fileMessageData'] ?? [];
        $url = $fileData['downloadUrl'] ?? null;
        if (! is_string($url) || $url === '') {
            return [];
        }

        try {
            $bytes = $this->http()->timeout(20)->get($url)->throw()->body();

            if ($bytes === '') {
                return [];
            }

            $caption = is_string($fileData['caption'] ?? null) ? trim($fileData['caption']) : '';

            return [new IncomingImage($bytes, ImageMime::sniff($bytes), $caption)];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    private function url(Channel $channel, string $method): string
    {
        $id = $channel->credential('id_instance');
        $token = $channel->credential('api_token');

        return "{$this->apiUrl}/waInstance{$id}/{$method}/{$token}";
    }
}
