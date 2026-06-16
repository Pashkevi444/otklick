<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Telegram\TelegramGateway;
use App\DTO\NewChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Бизнес-логика подключения каналов к тенанту. Работает с БД через репозиторий,
 * с Telegram — через gateway.
 */
final readonly class ChannelService
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private TelegramGateway $telegram,
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

        $this->channels->update($channel, [
            'settings' => ['allowed_origins' => $normalized],
        ]);
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
