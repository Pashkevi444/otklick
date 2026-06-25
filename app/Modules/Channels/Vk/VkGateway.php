<?php

declare(strict_types=1);

namespace App\Modules\Channels\Vk;

use App\Modules\Channels\Contracts\ChannelGateway;
use App\Modules\Channels\Contracts\ReceivesImage;
use App\Modules\Channels\Contracts\ReceivesVoice;
use App\Modules\Channels\Data\IncomingImage;
use App\Modules\Channels\Models\Channel;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Enums\ChannelType;
use App\Shared\Support\ImageBytes;
use App\Shared\Support\ImageMime;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Клиент VK (сообщения сообщества). Отправляет ответы (messages.send) и забирает
 * входящие через Bots Long Poll API (groups.getLongPollServer + a_check) — как и
 * Telegram, сервер сам тянет апдейты, публичный вебхук не нужен.
 *
 * Креды канала: access_token (токен сообщества) и group_id.
 */
final readonly class VkGateway implements ChannelGateway, ReceivesImage, ReceivesVoice
{
    public function __construct(
        private string $apiUrl,
        private string $version,
    ) {}

    public function provider(): ChannelType
    {
        return ChannelType::Vk;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null, array $images = []): void
    {
        // Картинки грузим на серверы VK (photos.getMessagesUploadServer → upload →
        // saveMessagesPhoto) и прикрепляем как НАСТОЯЩИЕ фото. Если загрузка не
        // удалась — шлём ссылкой в тексте (чтобы клиент всё же увидел фото).
        $attachment = '';
        if ($images !== []) {
            $uploaded = [];
            foreach ($images as $url) {
                try {
                    $photo = $this->uploadPhoto($channel, $chatId, $url);
                    if ($photo !== null) {
                        $uploaded[] = $photo;
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }

            if ($uploaded !== []) {
                $attachment = implode(',', $uploaded);
            } else {
                $text = trim($text."\n".implode("\n", $images));
            }
        }

        $params = [
            'peer_id' => $chatId,
            'message' => $text,
            // random_id у VK — знаковый int32 (используется для дедупа); шире — ошибка 100.
            'random_id' => random_int(1, 2_147_483_647),
            'group_id' => $channel->credential('group_id'),
        ];

        if ($attachment !== '') {
            $params['attachment'] = $attachment;
        }

        $markup = $this->keyboard($keyboard);
        if ($markup !== null) {
            $params['keyboard'] = $markup;
        }

        $this->ensureOk(
            $this->method($channel, 'messages.send', $params),
            'messages.send',
            ['channel_id' => $channel->id, 'peer_id' => $chatId],
        );
    }

    /**
     * Рендер клавиатуры-подсказки в параметр keyboard VK (JSON-строка): text-кнопки
     * (нажатие шлёт подпись обычным сообщением). null — параметр не передаём
     * (висящую клавиатуру VK снимать не нужно: one_time скрывает её после нажатия).
     */
    private function keyboard(?ReplyKeyboard $keyboard): ?string
    {
        if ($keyboard === null || $keyboard->isEmpty()) {
            return null;
        }

        $buttons = array_map(
            fn (array $row): array => array_map(
                fn (string $label): array => ['action' => ['type' => 'text', 'label' => $label]],
                $row,
            ),
            $keyboard->rows,
        );

        return (string) json_encode(['one_time' => true, 'buttons' => $buttons], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Сведения о сообществе (groups.getById) — проверка токена/group_id при
     * подключении. Возвращает название или null.
     */
    public function groupName(Channel $channel): ?string
    {
        $data = $this->ensureOk($this->method($channel, 'groups.getById', [
            'group_id' => $channel->credential('group_id'),
        ]), 'groups.getById', ['channel_id' => $channel->id]);

        // VK менял форму ответа между версиями API: новые — response.groups[],
        // старые — response[]. Берём имя из той, что присутствует. Ошибки токена/
        // сети не глушим (их различает контроллер по типу исключения).
        $name = data_get($data, 'response.groups.0.name') ?? data_get($data, 'response.0.name');

        return is_string($name) ? $name : null;
    }

    /**
     * Адрес Long Poll сервера сообщества: {server, key, ts}. null при ошибке.
     *
     * @return array{server: string, key: string, ts: string}|null
     */
    public function longPollServer(Channel $channel): ?array
    {
        $data = $this->ensureOk($this->method($channel, 'groups.getLongPollServer', [
            'group_id' => $channel->credential('group_id'),
        ]), 'groups.getLongPollServer', ['channel_id' => $channel->id]);

        $r = data_get($data, 'response');

        if (! is_array($r) || ! isset($r['server'], $r['key'], $r['ts'])) {
            return null;
        }

        return ['server' => (string) $r['server'], 'key' => (string) $r['key'], 'ts' => (string) $r['ts']];
    }

    /**
     * Один проход long poll. Возвращает новый ts и «сырые» апдейты VK; либо
     * ['failed' => N] — тогда нужно перезапросить сервер/ts.
     *
     * @return array{ts?: string, updates?: list<array<string, mixed>>, failed?: int}
     */
    public function getUpdates(string $server, string $key, string $ts, int $wait = 25): array
    {
        /** @var array{ts?: string, updates?: list<array<string, mixed>>, failed?: int} $data */
        $data = Http::asJson()
            ->connectTimeout(5)
            ->timeout($wait + 10)
            ->get($server, ['act' => 'a_check', 'key' => $key, 'ts' => $ts, 'wait' => $wait])
            ->throw()
            ->json() ?? [];

        return $data;
    }

    /**
     * Извлекает из апдейта VK конверт входящего сообщения: peer_id (адресат
     * ответа — messages.send требует peer_id), текст и id. Текст может быть пустым
     * (голосовое/вложение без текста) — решение по нему принимает джоб (расшифровка
     * голоса). null — если это не новое сообщение клиента.
     *
     * @param  array<string, mixed>  $update
     * @return array{peerId: string, text: string, id: string}|null
     */
    public function parseMessage(array $update): ?array
    {
        if (($update['type'] ?? null) !== 'message_new') {
            return null;
        }

        $message = $update['object']['message'] ?? $update['object'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        // peer_id — адресат ответа; в личном диалоге с сообществом он равен
        // from_id (id пользователя). from_id берём как запасной вариант.
        $peerId = $message['peer_id'] ?? $message['from_id'] ?? null;
        if ($peerId === null) {
            return null;
        }

        $text = is_string($message['text'] ?? null) ? trim($message['text']) : '';

        return [
            'peerId' => (string) $peerId,
            'text' => $text,
            'id' => (string) ($message['conversation_message_id'] ?? $message['id'] ?? ''),
        ];
    }

    /**
     * Скачивает голосовое (audio_message) из апдейта VK — у VK прямая ссылка
     * link_ogg (без токена). Возвращает байты OGG/Opus или null.
     *
     * @param  array<string, mixed>  $update
     */
    public function downloadVoice(Channel $channel, array $update): ?string
    {
        $message = $update['object']['message'] ?? $update['object'] ?? null;
        $attachments = is_array($message) ? ($message['attachments'] ?? []) : [];

        if (! is_array($attachments)) {
            return null;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment) || ($attachment['type'] ?? null) !== 'audio_message') {
                continue;
            }

            $link = $attachment['audio_message']['link_ogg'] ?? null;
            if (! is_string($link) || $link === '') {
                continue;
            }

            try {
                $body = Http::connectTimeout(5)->timeout(20)->get($link)->throw()->body();

                return $body !== '' ? $body : null;
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }

        return null;
    }

    /**
     * Скачивает ВСЕ фото из апдейта VK. Одно сообщение может нести несколько
     * вложений `type=photo` (каждое — массив размеров, берём самый крупный по
     * ширине). Подпись — текст сообщения (кладём к первому фото). Тип — по байтам.
     *
     * @param  array<string, mixed>  $update
     * @return list<IncomingImage>
     */
    public function downloadImages(Channel $channel, array $update): array
    {
        $message = $update['object']['message'] ?? $update['object'] ?? null;
        $attachments = is_array($message) ? ($message['attachments'] ?? []) : [];

        if (! is_array($attachments)) {
            return [];
        }

        $caption = is_string($message['text'] ?? null) ? trim($message['text']) : '';
        $images = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment) || ($attachment['type'] ?? null) !== 'photo') {
                continue;
            }

            $url = $this->largestPhotoUrl($attachment['photo']['sizes'] ?? null);
            if ($url === null) {
                continue;
            }

            try {
                $bytes = Http::connectTimeout(5)->timeout(20)->get($url)->throw()->body();
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            if ($bytes === '') {
                continue;
            }

            // Подпись — только к первому фото, чтобы не дублировать в описании.
            $images[] = new IncomingImage($bytes, ImageMime::sniff($bytes), $images === [] ? $caption : '');
        }

        return $images;
    }

    /**
     * URL самого крупного размера фото VK (`sizes[].url`, максимум по `width`).
     *
     * @param  mixed  $sizes
     */
    private function largestPhotoUrl($sizes): ?string
    {
        if (! is_array($sizes) || $sizes === []) {
            return null;
        }

        $best = null;
        $bestWidth = -1;

        foreach ($sizes as $size) {
            if (! is_array($size) || ! is_string($size['url'] ?? null) || $size['url'] === '') {
                continue;
            }

            $width = (int) ($size['width'] ?? 0);
            if ($width >= $bestWidth) {
                $bestWidth = $width;
                $best = $size['url'];
            }
        }

        return $best;
    }

    /**
     * Загружает картинку (по URL) на серверы VK и возвращает строку вложения
     * `photo{owner}_{id}` для messages.send. null — если загрузить не удалось.
     */
    private function uploadPhoto(Channel $channel, string $peerId, string $url): ?string
    {
        $server = $this->ensureOk(
            $this->method($channel, 'photos.getMessagesUploadServer', [
                'peer_id' => $peerId,
                'group_id' => $channel->credential('group_id'),
            ]),
            'photos.getMessagesUploadServer',
        );
        $uploadUrl = data_get($server, 'response.upload_url');
        if (! is_string($uploadUrl)) {
            return null;
        }

        $bytes = ImageBytes::fetch($url);
        if ($bytes === null) {
            return null;
        }

        /** @var array<string, mixed> $uploaded */
        $uploaded = Http::connectTimeout(5)->timeout(20)
            ->attach('photo', $bytes, 'photo.jpg')
            ->post($uploadUrl)
            ->json() ?? [];
        if (! isset($uploaded['photo'], $uploaded['server'], $uploaded['hash'])) {
            return null;
        }

        $saved = $this->ensureOk(
            $this->method($channel, 'photos.saveMessagesPhoto', [
                'photo' => (string) $uploaded['photo'],
                'server' => (string) $uploaded['server'],
                'hash' => (string) $uploaded['hash'],
            ]),
            'photos.saveMessagesPhoto',
        );
        $owner = data_get($saved, 'response.0.owner_id');
        $id = data_get($saved, 'response.0.id');
        if ($owner === null || $id === null) {
            return null;
        }

        return "photo{$owner}_{$id}";
    }

    /**
     * Проверяет ответ VK и возвращает декодированное тело. VK отдаёт ошибки
     * HTTP 200 с телом `{"error": {...}}`, поэтому `->throw()` их НЕ ловит —
     * проверяем поле `error` явно и бросаем. Логируем эндпоинт/код/сообщение
     * (без токена), чтобы по логам было видно причину у конкретного канала.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function ensureOk(Response $response, string $method, array $context = []): array
    {
        $response->throw(); // HTTP не-2xx (сеть/5xx) — типизированное исключение клиента

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        if (isset($data['error'])) {
            $code = data_get($data, 'error.error_code', 0);
            $message = data_get($data, 'error.error_msg', 'unknown');
            Log::warning('vk.api_error', ['method' => $method, 'code' => $code, 'message' => $message] + $context);

            throw new RuntimeException("VK {$method}: ошибка {$code} — {$message}");
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function method(Channel $channel, string $method, array $params): Response
    {
        return Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post("{$this->apiUrl}/{$method}", [
                ...array_filter($params, static fn ($v): bool => $v !== null),
                'access_token' => $channel->credential('access_token'),
                'v' => $this->version,
            ]);
    }
}
