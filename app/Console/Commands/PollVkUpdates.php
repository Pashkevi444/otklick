<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Vk\VkGateway;
use App\Enums\ChannelType;
use App\Jobs\ProcessVkUpdate;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bots Long Poll для сообществ ВКонтакте: сервер сам забирает апдейты у VK.
 * Аналог `telegram:poll`, но протокол VK двухшаговый — сперва берём адрес
 * Long Poll сервера (groups.getLongPollServer), затем опрашиваем его (a_check).
 *
 * Состояние опроса {server, key, ts} хранится в кэше. VK отдаёт «failed»-коды:
 *  - failed=1 — устарел ts (VK присылает новый), просто продолжаем с ним;
 *  - failed=2/3 — истёк key или потеряна история, переинициализируем сервер.
 * Дубли (при сбросе ts) отсекаются идемпотентностью recordInbound.
 */
final class PollVkUpdates extends Command
{
    protected $signature = 'vk:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает апдейты сообществ ВКонтакте через Bots Long Poll (vk:poll).';

    public function handle(VkGateway $vk): int
    {
        do {
            $channels = $this->activeChannels();

            foreach ($channels as $channel) {
                $this->pollChannel($vk, $channel);
            }

            // Нет активных VK-каналов — пустой foreach не блокируется на long poll,
            // поэтому ждём сами, иначе цикл крутит CPU на 100% (на свежем проде
            // контейнер vk стартует до того, как кто-то подключит сообщество).
            if ($channels->isEmpty() && ! $this->option('once')) {
                sleep(5);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function pollChannel(VkGateway $vk, Channel $channel): void
    {
        try {
            $state = $this->longPollState($vk, $channel);
            if ($state === null) {
                usleep(500_000); // адрес сервера не получен — не долбим VK тугим циклом

                return;
            }

            $wait = $this->option('once') ? 0 : 25;
            $result = $vk->getUpdates($state['server'], $state['key'], $state['ts'], $wait);

            if (isset($result['failed'])) {
                $this->handleFailed($channel, $state, $result);

                return;
            }

            foreach ($result['updates'] ?? [] as $update) {
                ProcessVkUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $update);
            }

            $state['ts'] = (string) ($result['ts'] ?? $state['ts']);
            Cache::forever($this->stateKey($channel->id), $state);
        } catch (Throwable $e) {
            Log::warning('vk.poll_failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            report($e);
            usleep(500_000); // не молотим VK при ошибке
        }
    }

    /**
     * Текущее состояние опроса из кэша либо свежий адрес сервера у VK.
     *
     * @return array{server: string, key: string, ts: string}|null
     */
    private function longPollState(VkGateway $vk, Channel $channel): ?array
    {
        $state = Cache::get($this->stateKey($channel->id));

        if (is_array($state) && isset($state['server'], $state['key'], $state['ts'])) {
            /** @var array{server: string, key: string, ts: string} $state */
            return $state;
        }

        $fresh = $vk->longPollServer($channel);

        if ($fresh !== null) {
            Cache::forever($this->stateKey($channel->id), $fresh);
        }

        return $fresh;
    }

    /**
     * Реакция на failed-код VK: при failed=1 двигаем ts, иначе переинициализируем
     * сервер (сбрасываем кэш — следующий проход возьмёт свежий адрес).
     *
     * @param  array{server: string, key: string, ts: string}  $state
     * @param  array{ts?: string, failed?: int}  $result
     */
    private function handleFailed(Channel $channel, array $state, array $result): void
    {
        if (($result['failed'] ?? null) === 1 && isset($result['ts'])) {
            $state['ts'] = (string) $result['ts'];
            Cache::forever($this->stateKey($channel->id), $state);

            return;
        }

        Cache::forget($this->stateKey($channel->id));
    }

    /**
     * @return Collection<int, Channel>
     */
    private function activeChannels(): Collection
    {
        return Channel::query()
            ->withoutGlobalScopes()
            ->where('type', ChannelType::Vk)
            ->where('is_active', true)
            ->get();
    }

    private function stateKey(string $channelId): string
    {
        return "vk:poll:state:{$channelId}";
    }
}
