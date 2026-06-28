<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console;

use App\Modules\Channels\Jobs\ProcessMaxUpdate;
use App\Modules\Channels\Max\MaxGateway;
use App\Modules\Channels\Models\Channel;
use App\Shared\Enums\ChannelType;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Long polling для ботов MAX: сервер сам забирает апдейты (GET /updates) и кладёт
 * их в очередь Horizon (ProcessMaxUpdate). Аналог `telegram:poll`/`vk:poll`.
 *
 * Все активные каналы опрашиваются КОНКУРЕНТНО (Http::pool в `MaxGateway::getUpdatesPool`),
 * а НЕ по очереди: иначе один простаивающий бот держал бы свой long-poll и задерживал
 * доставку остальным — задержка росла линейно с числом каналов (N×long-poll).
 * Позиция чтения — marker: каждый ответ MAX отдаёт новый marker, его храним в кэше и
 * передаём в следующий запрос. При потере marker дубли отсекаются идемпотентностью
 * recordInbound.
 */
final class PollMaxUpdates extends Command
{
    protected $signature = 'max:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает апдейты ботов MAX через long polling (max:poll).';

    /**
     * Длительность long-poll, сек. Короткий — круг ждёт ВСЕ каналы (барьер пула),
     * значит задержка доставки ≈ этому порогу (см. PollTelegramUpdates).
     */
    private const int LONG_POLL_SECONDS = 3;

    public function handle(MaxGateway $max): int
    {
        do {
            $channels = $this->activeChannels();

            if ($channels->isEmpty()) {
                // Нет активных каналов — пулу не на чём блокироваться, иначе цикл крутит CPU.
                if (! $this->option('once')) {
                    sleep(5);
                }

                continue;
            }

            $this->pollRound($max, $channels);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * Один круг: ОДНИМ пулом тянем апдейты у всех каналов разом, раскидываем по
     * очереди и двигаем marker'ы. marker сохраняем только когда MAX его прислал (при
     * отсутствии новых событий он может не приходить — тогда продолжаем с прежним).
     *
     * @param  Collection<int, Channel>  $channels
     */
    private function pollRound(MaxGateway $max, Collection $channels): void
    {
        $markers = [];
        foreach ($channels as $channel) {
            $stored = Cache::get($this->markerKey($channel->id));
            $markers[(string) $channel->id] = is_numeric($stored) ? (int) $stored : null;
        }

        $longPoll = $this->option('once') ? 0 : self::LONG_POLL_SECONDS;
        $resultByChannel = $max->getUpdatesPool($channels, $markers, $longPoll);

        foreach ($channels as $channel) {
            $result = $resultByChannel[(string) $channel->id] ?? null;
            if ($result === null) {
                continue;
            }

            foreach ($result['updates'] ?? [] as $update) {
                ProcessMaxUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $update);
            }

            if (isset($result['marker'])) {
                Cache::forever($this->markerKey($channel->id), (int) $result['marker']);
            }
        }
    }

    /**
     * @return Collection<int, Channel>
     */
    private function activeChannels(): Collection
    {
        return Channel::query()
            ->withoutGlobalScopes()
            ->where('type', ChannelType::Max)
            ->where('is_active', true)
            ->get();
    }

    private function markerKey(string $channelId): string
    {
        return "max:poll:marker:{$channelId}";
    }
}
