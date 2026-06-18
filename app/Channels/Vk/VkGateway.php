<?php

declare(strict_types=1);

namespace App\Channels\Vk;

use App\Channels\Contracts\ChannelGateway;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Клиент VK (сообщения сообщества). Отправляет ответы (messages.send) и забирает
 * входящие через Bots Long Poll API (groups.getLongPollServer + a_check) — как и
 * Telegram, сервер сам тянет апдейты, публичный вебхук не нужен.
 *
 * Креды канала: access_token (токен сообщества) и group_id.
 */
final readonly class VkGateway implements ChannelGateway
{
    public function __construct(
        private string $apiUrl,
        private string $version,
    ) {}

    public function provider(): ChannelType
    {
        return ChannelType::Vk;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null): void
    {
        $params = [
            'peer_id' => $chatId,
            'message' => $text,
            // random_id у VK — знаковый int32 (используется для дедупа); шире — ошибка 100.
            'random_id' => random_int(1, 2_147_483_647),
            'group_id' => $channel->credential('group_id'),
        ];

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
     * Извлекает из апдейта VK входящее текстовое сообщение: peer_id (куда слать
     * ответ — messages.send требует peer_id), текст и id сообщения. null — если
     * это не новое текстовое сообщение клиента.
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
        $text = $message['text'] ?? null;

        if ($peerId === null || ! is_string($text) || trim($text) === '') {
            return null;
        }

        return [
            'peerId' => (string) $peerId,
            'text' => $text,
            'id' => (string) ($message['conversation_message_id'] ?? $message['id'] ?? ''),
        ];
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
