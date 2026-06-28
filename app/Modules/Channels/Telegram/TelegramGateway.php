<?php

declare(strict_types=1);

namespace App\Modules\Channels\Telegram;

use App\Modules\Channels\Contracts\ChannelGateway;
use App\Modules\Channels\Contracts\ReceivesImage;
use App\Modules\Channels\Contracts\ReceivesVoice;
use App\Modules\Channels\Data\IncomingImage;
use App\Modules\Channels\Models\Channel;
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
 * Клиент Telegram Bot API. Отправляет ответы и забирает апдейты.
 * Токен бота берётся из кред канала (per-tenant).
 *
 * В РФ вебхуки Telegram не работают (входящий путь Telegram→РФ-IPv4 заблокирован,
 * IPv6 для вебхука Telegram не принимает), поэтому апдейты тянем long polling'ом
 * (getUpdates) — это исходящий запрос, который ходит по IPv6.
 */
final readonly class TelegramGateway implements ChannelGateway, ReceivesImage, ReceivesVoice
{
    public function __construct(
        private string $apiUrl,
        private bool $forceIpv6 = false,
        private ?string $proxy = null,
    ) {}

    /**
     * Сетевые опции для запросов к Telegram: через прокси (VPN, обход блокировки)
     * — приоритетно; иначе форсим IPv6. Прокси сам решает маршрут, IPv6-форс с ним
     * не нужен.
     *
     * @return array<string, mixed>
     */
    private function netOptions(): array
    {
        if ($this->proxy !== null && $this->proxy !== '') {
            return ['proxy' => $this->proxy];
        }

        if ($this->forceIpv6) {
            return ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]];
        }

        return [];
    }

    public function provider(): ChannelType
    {
        return ChannelType::Telegram;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null, array $images = []): void
    {
        if ($text !== '') {
            $this->call($channel->botToken(), 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                // Reply-кнопки шлют свой текст обычным сообщением; при отсутствии
                // клавиатуры снимаем прошлую, чтобы не висела на не-мастер-ответах.
                'reply_markup' => $this->replyMarkup($keyboard),
            ]);
        }

        // Картинки шлём ЗАГРУЗКОЙ БАЙТОВ (multipart), а НЕ ссылкой: Telegram с
        // зарубежных серверов не может скачать URL с РФ-хостинга («wrong type of
        // the web page content»). Приложение само читает файл с локального диска и
        // отдаёт его Telegram. Сбой не роняет отправку — что не ушло, даём ссылкой.
        $failed = [];
        foreach ($images as $url) {
            try {
                $bytes = ImageBytes::fetch($url);
                if ($bytes === null) {
                    $failed[] = $url;

                    continue;
                }

                $this->photoRequest()
                    ->attach('photo', $bytes, 'photo.jpg')
                    ->post("{$this->apiUrl}/bot{$channel->botToken()}/sendPhoto", ['chat_id' => $chatId])
                    ->throw();
            } catch (Throwable $e) {
                report($e);
                $failed[] = $url;
            }
        }

        if ($failed !== []) {
            $this->call($channel->botToken(), 'sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Примеры работ: '.implode(' ', $failed),
            ]);
        }
    }

    /** HTTP-клиент для multipart-загрузки фото (с форсингом IPv6 в РФ). */
    private function photoRequest(): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(20)->retry(2, 300, throw: false)
            ->withOptions($this->netOptions());
    }

    /**
     * Рендер клавиатуры-подсказки в reply_markup Telegram (JSON-строка):
     * ReplyKeyboardMarkup (кнопки шлют свой текст) либо снятие клавиатуры.
     */
    private function replyMarkup(?ReplyKeyboard $keyboard): string
    {
        if ($keyboard === null || $keyboard->isEmpty()) {
            return (string) json_encode(['remove_keyboard' => true]);
        }

        $rows = array_map(
            fn (array $row): array => array_map(fn (string $label): array => ['text' => $label], $row),
            $keyboard->rows,
        );

        return (string) json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    /**
     * Снимает вебхук — обязательно перед long polling (иначе getUpdates вернёт
     * 409 Conflict). Заодно валидирует токен (битый → 401).
     */
    public function deleteWebhook(Channel $channel): void
    {
        $this->call($channel->botToken(), 'deleteWebhook', [
            'drop_pending_updates' => false,
        ]);
    }

    /**
     * Забирает апдейты бота через long polling. Возвращает «сырые» апдейты
     * Telegram (как в вебхуке) — их обрабатывает ProcessTelegramUpdate.
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(string $botToken, int $offset, int $longPollSeconds = 25): array
    {
        $response = $this->request()
            ->connectTimeout(5)
            ->timeout($longPollSeconds + 10)
            ->get("{$this->apiUrl}/bot{$botToken}/getUpdates", [
                'offset' => $offset,
                'timeout' => $longPollSeconds,
                'allowed_updates' => json_encode(['message']),
            ])
            ->throw();

        /** @var list<array<string, mixed>> $result */
        $result = $response->json('result', []);

        return $result;
    }

    /**
     * КОНКУРЕНТНЫЙ long-poll: опрашивает getUpdates у ВСЕХ каналов ОДНОВРЕМЕННО
     * (Http::pool), а не по очереди. Иначе один простаивающий бот держит свой long-poll
     * и задерживает доставку сообщений остальным — задержка росла линейно с числом
     * каналов. Канал со сбоем (исключение/не-2xx) просто отсутствует в результате —
     * повторит на следующем круге, не роняя остальных.
     *
     * @param  Collection<int, Channel>  $channels
     * @param  array<string, int>  $offsets  channelId => offset
     * @return array<string, list<array<string, mixed>>> channelId => «сырые» апдейты
     */
    public function getUpdatesPool(Collection $channels, array $offsets, int $longPollSeconds = 3): array
    {
        $responses = Http::pool(function (Pool $pool) use ($channels, $offsets, $longPollSeconds): array {
            $requests = [];
            foreach ($channels as $channel) {
                $requests[] = $pool->as((string) $channel->id)
                    ->asJson()
                    ->withOptions($this->netOptions())
                    ->connectTimeout(10)
                    ->timeout($longPollSeconds + 10)
                    ->get("{$this->apiUrl}/bot{$channel->botToken()}/getUpdates", [
                        'offset' => $offsets[(string) $channel->id] ?? 0,
                        'timeout' => $longPollSeconds,
                        'allowed_updates' => json_encode(['message']),
                    ]);
            }

            return $requests;
        });

        $out = [];
        foreach ($responses as $channelId => $response) {
            if ($response instanceof Response && $response->successful()) {
                /** @var list<array<string, mixed>> $result */
                $result = $response->json('result', []);
                $out[(string) $channelId] = $result;
            }
        }

        return $out;
    }

    /**
     * Отправляет сообщение и возвращает его telegram message_id (нужен для
     * reply-маппинга в операторском мосте). null — если id не пришёл.
     */
    public function sendReturningId(Channel $channel, string $chatId, string $text): ?int
    {
        $id = $this->request()
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false)
            ->post("{$this->apiUrl}/bot{$channel->botToken()}/sendMessage", ['chat_id' => $chatId, 'text' => $text])
            ->throw()
            ->json('result.message_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Данные о боте (getMe) — нужен username для диплинка подключения уведомлений.
     *
     * @return array<string, mixed>
     */
    public function getMe(Channel $channel): array
    {
        $response = $this->request()
            ->connectTimeout(5)
            ->timeout(8)
            ->get("{$this->apiUrl}/bot{$channel->botToken()}/getMe")
            ->throw();

        /** @var array<string, mixed> $result */
        $result = $response->json('result', []);

        return $result;
    }

    /**
     * Скачивает голосовое сообщение Telegram (getFile → download). Войс приходит
     * как OGG/Opus — отдаём байты как есть (SpeechKit принимает нативно).
     *
     * @param  array<string, mixed>  $update
     */
    public function downloadVoice(Channel $channel, array $update): ?string
    {
        $message = $update['message'] ?? null;
        $fileId = is_array($message) ? ($message['voice']['file_id'] ?? $message['audio']['file_id'] ?? null) : null;

        if (! is_string($fileId) || $fileId === '') {
            return null;
        }

        $token = $channel->botToken();

        try {
            $filePath = $this->request()
                ->connectTimeout(5)->timeout(8)
                ->get("{$this->apiUrl}/bot{$token}/getFile", ['file_id' => $fileId])
                ->throw()
                ->json('result.file_path');

            if (! is_string($filePath) || $filePath === '') {
                return null;
            }

            $body = $this->request()
                ->connectTimeout(5)->timeout(20)
                ->get("{$this->apiUrl}/file/bot{$token}/{$filePath}")
                ->throw()
                ->body();

            return $body !== '' ? $body : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Скачивает фото из апдейта Telegram (getFile → download). Одно сообщение несёт
     * одно фото массивом размеров `message.photo` — берём самый крупный (альбом
     * приходит ОТДЕЛЬНЫМИ апдейтами, склейку делает дебаунс по media_group_id).
     * Подпись — из `message.caption`. Тип уточняем по байтам.
     *
     * @param  array<string, mixed>  $update
     * @return list<IncomingImage>
     */
    public function downloadImages(Channel $channel, array $update): array
    {
        $message = $update['message'] ?? null;
        $photo = is_array($message) ? ($message['photo'] ?? null) : null;

        if (! is_array($photo) || $photo === []) {
            return [];
        }

        $largest = end($photo);
        $fileId = is_array($largest) ? ($largest['file_id'] ?? null) : null;

        if (! is_string($fileId) || $fileId === '') {
            return [];
        }

        $caption = is_string($message['caption'] ?? null) ? trim($message['caption']) : '';
        $bytes = $this->downloadFile($channel, $fileId);

        return $bytes !== null ? [new IncomingImage($bytes, ImageMime::sniff($bytes), $caption)] : [];
    }

    /**
     * Скачивает файл Telegram по file_id (getFile → download). null — ошибка.
     */
    public function downloadFile(Channel $channel, string $fileId): ?string
    {
        $token = $channel->botToken();

        try {
            $filePath = $this->request()
                ->connectTimeout(5)->timeout(8)
                ->get("{$this->apiUrl}/bot{$token}/getFile", ['file_id' => $fileId])
                ->throw()
                ->json('result.file_path');

            if (! is_string($filePath) || $filePath === '') {
                return null;
            }

            $body = $this->request()
                ->connectTimeout(5)->timeout(20)
                ->get("{$this->apiUrl}/file/bot{$token}/{$filePath}")
                ->throw()
                ->body();

            return $body !== '' ? $body : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(?string $botToken, string $method, array $params): void
    {
        // Короткие таймауты: недоступность Telegram должна падать быстро с
        // понятной ошибкой, а не висеть и ловить таймаут шлюза.
        $this->request()
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false)
            ->post("{$this->apiUrl}/bot{$botToken}/{$method}", $params)
            ->throw();
    }

    /**
     * Базовый HTTP-клиент. В РФ форсируем IPv6 — иначе Guzzle сперва висит на
     * заблокированном IPv4-таймауте перед переходом на рабочий IPv6.
     */
    private function request(): PendingRequest
    {
        return Http::asJson()->withOptions($this->netOptions());
    }
}
