<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Max\MaxGateway;
use App\Channels\Telegram\TelegramGateway;
use App\Channels\Vk\VkGateway;
use App\Channels\WhatsApp\WhatsAppGateway;
use App\DTO\NewChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Бизнес-логика подключения каналов к тенанту. Работает с БД через репозиторий,
 * с Telegram — через gateway.
 */
final readonly class ChannelService
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private TelegramGateway $telegram,
        private VkGateway $vk,
        private MaxGateway $max,
        private WhatsAppGateway $whatsapp,
    ) {}

    /**
     * Подключает Telegram-бота к тенанту: создаёт канал с зашифрованными кредами.
     * Бот работает через long polling (`telegram:poll`), поэтому вебхук не
     * ставится, а снимается (иначе getUpdates вернёт 409). deleteWebhook заодно
     * валидирует токен — битый отклонится с 401.
     *
     * $webhookBaseUrl сохранён в сигнатуре для совместимости (не-РФ окружения).
     */
    public function connectTelegram(string $tenantId, string $botToken, string $webhookBaseUrl = ''): Channel
    {
        $secretToken = Str::random(40);

        // Транзакция: если запрос к Telegram упадёт (битый токен/сеть), канал не
        // останется полу-подключённым.
        return DB::transaction(function () use ($tenantId, $botToken, $secretToken): Channel {
            $channel = $this->channels->create(new NewChannelData(
                tenantId: $tenantId,
                type: ChannelType::Telegram,
                externalId: $this->botId($botToken),
                botToken: $botToken,
                secretToken: $secretToken,
            ));

            $this->telegram->deleteWebhook($channel);

            return $channel;
        });
    }

    /**
     * Подключает сообщество ВКонтакте к тенанту: создаёт канал с зашифрованными
     * кредами (токен сообщества + group_id). Бот работает через Bots Long Poll
     * (`vk:poll`), публичный вебхук не нужен.
     *
     * Валидация токена/группы — запрос groups.getById внутри транзакции: если
     * VK не подтверждает сообщество (битый токен/нет прав/неверный group_id),
     * канал не остаётся полу-подключённым.
     */
    public function connectVk(string $tenantId, string $accessToken, string $groupId): Channel
    {
        return DB::transaction(function () use ($tenantId, $accessToken, $groupId): Channel {
            $channel = $this->channels->create(new NewChannelData(
                tenantId: $tenantId,
                type: ChannelType::Vk,
                externalId: $groupId,
                credentials: ['access_token' => $accessToken, 'group_id' => $groupId],
            ));

            if ($this->vk->groupName($channel) === null) {
                throw new RuntimeException('VK не подтвердил сообщество: проверьте токен сообщества и его id.');
            }

            return $channel;
        });
    }

    /**
     * Подключает бота MAX к тенанту: создаёт канал с зашифрованным токеном. Бот
     * работает через long polling (`max:poll`), публичный вебхук не нужен.
     *
     * Валидация токена — запрос GET /me внутри транзакции: битый токен ответит
     * 401 (исключение), и канал не останется полу-подключённым.
     */
    public function connectMax(string $tenantId, string $token): Channel
    {
        return DB::transaction(function () use ($tenantId, $token): Channel {
            $channel = $this->channels->create(new NewChannelData(
                tenantId: $tenantId,
                type: ChannelType::Max,
                externalId: null,
                credentials: ['access_token' => $token],
            ));

            $this->max->getMe($channel);

            return $channel;
        });
    }

    /**
     * Подключает WhatsApp к тенанту через провайдера Green API: создаёт канал с
     * зашифрованными кредами инстанса (idInstance + apiTokenInstance). Бот
     * работает через long polling (`whatsapp:poll`), публичный вебхук не нужен.
     *
     * Валидация — getStateInstance внутри транзакции: аккаунт должен быть привязан
     * (QR отсканирован, состояние «authorized»), иначе канал не остаётся
     * полу-подключённым.
     */
    public function connectWhatsApp(string $tenantId, string $idInstance, string $apiToken): Channel
    {
        return DB::transaction(function () use ($tenantId, $idInstance, $apiToken): Channel {
            $channel = $this->channels->create(new NewChannelData(
                tenantId: $tenantId,
                type: ChannelType::WhatsApp,
                externalId: $idInstance,
                credentials: ['id_instance' => $idInstance, 'api_token' => $apiToken],
            ));

            if ($this->whatsapp->stateInstance($channel) !== 'authorized') {
                throw new RuntimeException('WhatsApp не привязан: отсканируйте QR в Green API (статус инстанса должен быть «authorized»).');
            }

            return $channel;
        });
    }

    /**
     * Подключает веб-виджет (чат на сайт) к тенанту. Создаёт канал типа web
     * без внешних кред — доступ к чату изолируется подписанной сессией и
     * списком разрешённых доменов (origin allow-list).
     */
    public function connectWeb(string $tenantId): Channel
    {
        return $this->channels->create(new NewChannelData(
            tenantId: $tenantId,
            type: ChannelType::Web,
            externalId: null,
            settings: ['allowed_origins' => []],
        ));
    }

    /**
     * Обновляет список разрешённых доменов виджета (с каких сайтов можно
     * открывать чат). Пустой список = разрешено везде (не рекомендуется).
     *
     * @param  list<string>  $origins
     */
    public function setWidgetOrigins(Channel $channel, array $origins): void
    {
        // Нормализуем: убираем хвостовой слэш и пробелы, приводим к нижнему
        // регистру — чтобы origin из браузера совпадал со списком.
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (string $o): string => rtrim(mb_strtolower(trim($o)), '/'),
            $origins,
        ))));

        // Мерджим в существующие настройки, чтобы не затереть цвет виджета и
        // прочие ключи (settings — единый JSON-столбец).
        $this->channels->update($channel, [
            'settings' => [...$channel->settings, 'allowed_origins' => $normalized],
        ]);
    }

    /**
     * Сохраняет цвет фирменного оформления веб-виджета (акцент кнопки/шапки).
     * `null` — сбросить на брендовый цвет «Отклик». Цвет читает рантайм виджета
     * через /widget/v1/{tenant}/{channel}/config и красит интерфейс под бизнес.
     */
    public function setWidgetColor(Channel $channel, ?string $color): void
    {
        $settings = $channel->settings;

        if ($color === null) {
            unset($settings['widget_color']);
        } else {
            $settings['widget_color'] = mb_strtolower($color);
        }

        $this->channels->update($channel, ['settings' => $settings]);
    }

    /**
     * Числовой id бота — префикс токена до двоеточия (бот <id>:<secret>).
     */
    private function botId(string $botToken): ?string
    {
        $id = Str::before($botToken, ':');

        return $id !== '' ? $id : null;
    }
}
