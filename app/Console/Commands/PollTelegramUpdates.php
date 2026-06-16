<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Telegram\TelegramGateway;
use App\Enums\ChannelType;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Long polling для Telegram-ботов: сервер сам забирает апдейты у Telegram.
 * Нужно в РФ, где вебхуки не доставляются (входящий путь Telegram→IPv4 блокирован).
 *
 * Для каждого активного Telegram-канала снимает вебхук (иначе getUpdates даёт
 * 409), тянет апдейты и кладёт их в ту же очередь, что и вебхук
 * (ProcessTelegramUpdate → Horizon). Offset (last update_id) хранится в кэше;
 * при его потере дубли отсекаются идемпотентностью recordInbound.
 */
final class PollTelegramUpdates extends Command
{
    protected $signature = 'telegram:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает апдейты Telegram-ботов через long polling (вебхуки в РФ недоступны).';

    /** @var array<string, bool> */
    private array $webhookCleared = [];

    public function handle(TelegramGateway $telegram): int
    {
        do {
            foreach ($this->activeChannels() as $channel) {
                $this->pollChannel($telegram, $channel);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function pollChannel(TelegramGateway $telegram, Channel $channel): void
    {
        try {
            // Вебхук и getUpdates взаимоисключающи — снимаем вебхук один раз.
            if (! isset($this->webhookCleared[$channel->id])) {
                $telegram->deleteWebhook($channel);
                $this->webhookCleared[$channel->id] = true;
            }

            $offset = (int) Cache::get($this->offsetKey($channel->id), 0);
            $longPoll = $this->option('once') ? 0 : 25;
            $updates = $telegram->getUpdates((string) $channel->botToken(), $offset, $longPoll);

            foreach ($updates as $update) {
                ProcessTelegramUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $update);
                $offset = max($offset, ((int) ($update['update_id'] ?? 0)) + 1);
            }

            if ($updates !== []) {
                Cache::forever($this->offsetKey($channel->id), $offset);
            }
        } catch (Throwable $e) {
            report($e);
            usleep(500_000); // не молотим Telegram при ошибке
        }
    }

    /**
     * @return Collection<int, Channel>
     */
    private function activeChannels(): Collection
    {
        return Channel::query()
            ->withoutGlobalScopes()
            ->where('type', ChannelType::Telegram)
            ->where('is_active', true)
            ->get();
    }

    private function offsetKey(string $channelId): string
    {
        return "telegram:poll:offset:{$channelId}";
    }
}
