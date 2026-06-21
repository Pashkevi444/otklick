<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Telegram\TelegramGateway;
use App\Enums\ChannelType;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Support\SecretScrubber;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
            $channels = $this->activeChannels();

            foreach ($channels as $channel) {
                $this->pollChannel($telegram, $channel);
            }

            // Нет активных каналов — пустой foreach не блокируется на long poll,
            // поэтому ждём сами, иначе цикл крутит CPU на 100%.
            if ($channels->isEmpty() && ! $this->option('once')) {
                sleep(5);
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
        } catch (ConnectionException $e) {
            // Транзиентный сетевой сбой до api.telegram.org (таймаут/блокировка
            // маршрута из РФ) — обычное дело, поллер ретраит на следующем цикле.
            // Не шумим в трекер; секрет (токен в URL) вырезаем из лога.
            Log::warning('telegram.poll_connection', ['channel_id' => $channel->id, 'error' => SecretScrubber::scrub($e->getMessage())]);
            usleep(500_000);
        } catch (Throwable $e) {
            Log::warning('telegram.poll_failed', ['channel_id' => $channel->id, 'error' => SecretScrubber::scrub($e->getMessage())]);
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
