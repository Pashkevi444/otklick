<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Max\MaxGateway;
use App\Enums\ChannelType;
use App\Jobs\ProcessMaxUpdate;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Long polling для ботов MAX: сервер сам забирает апдейты (GET /updates) и кладёт
 * их в очередь Horizon (ProcessMaxUpdate). Аналог `telegram:poll`/`vk:poll`.
 *
 * Позиция чтения — marker: каждый ответ MAX отдаёт новый marker, его храним в
 * кэше и передаём в следующий запрос, чтобы не получать события повторно. При
 * потере marker дубли отсекаются идемпотентностью recordInbound.
 */
final class PollMaxUpdates extends Command
{
    protected $signature = 'max:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает апдейты ботов MAX через long polling (max:poll).';

    public function handle(MaxGateway $max): int
    {
        do {
            $channels = $this->activeChannels();

            foreach ($channels as $channel) {
                $this->pollChannel($max, $channel);
            }

            // Нет активных MAX-каналов — пустой foreach не блокируется на long poll,
            // поэтому ждём сами, иначе цикл крутит CPU на 100%.
            if ($channels->isEmpty() && ! $this->option('once')) {
                sleep(5);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function pollChannel(MaxGateway $max, Channel $channel): void
    {
        try {
            $stored = Cache::get($this->markerKey($channel->id));
            $marker = is_numeric($stored) ? (int) $stored : null;
            $longPoll = $this->option('once') ? 0 : 30;

            $result = $max->getUpdates($channel, $marker, $longPoll);

            foreach ($result['updates'] ?? [] as $update) {
                ProcessMaxUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $update);
            }

            // marker обновляем только когда MAX его прислал (при отсутствии новых
            // событий он может не приходить — тогда продолжаем с прежним).
            if (isset($result['marker'])) {
                Cache::forever($this->markerKey($channel->id), (int) $result['marker']);
            }
        } catch (Throwable $e) {
            Log::warning('max.poll_failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            report($e);
            usleep(500_000); // не молотим MAX при ошибке
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
