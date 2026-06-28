<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console;

use App\Modules\Channels\Jobs\ProcessVkUpdate;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Support\PollFailureLog;
use App\Modules\Channels\Vk\VkGateway;
use App\Shared\Enums\ChannelType;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Bots Long Poll для сообществ ВКонтакте: сервер сам забирает апдейты у VK.
 * Аналог `telegram:poll`, но протокол VK двухшаговый — сперва берём адрес
 * Long Poll сервера (groups.getLongPollServer), затем опрашиваем его (a_check).
 *
 * Все активные каналы опрашиваются КОНКУРЕНТНО (Http::pool в `VkGateway::getUpdatesPool`),
 * а НЕ по очереди: иначе одно простаивающее сообщество держало бы свой long-poll и
 * задерживало доставку остальным — задержка росла линейно с числом каналов. Резолв
 * адреса сервера (шаг 1) остаётся per-channel перед пулом: он нужен редко (только
 * когда нет кэша). Конкурентно пуляем только a_check (шаг 2).
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

    /**
     * Длительность long-poll, сек. Короткий — круг ждёт ВСЕ каналы (барьер пула),
     * значит задержка доставки ≈ этому порогу (см. PollTelegramUpdates).
     */
    private const int LONG_POLL_SECONDS = 3;

    public function handle(VkGateway $vk): int
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

            $this->pollRound($vk, $channels);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * Один круг: резолвим state {server,key,ts} per-channel (адрес сервера берётся
     * редко — только при отсутствии кэша), затем ОДНИМ пулом бьём a_check у всех
     * каналов разом и раскидываем результат (failed-коды / апдейты + новый ts).
     *
     * @param  Collection<int, Channel>  $channels
     */
    private function pollRound(VkGateway $vk, Collection $channels): void
    {
        $states = [];
        foreach ($channels as $channel) {
            try {
                $state = $this->longPollState($vk, $channel);
            } catch (Throwable $e) {
                // Резолв адреса сервера у одного канала упал — не валит остальных.
                PollFailureLog::record('vk', (string) $channel->id, $e);

                continue;
            }

            if ($state !== null) {
                $states[(string) $channel->id] = $state;
            }
        }

        if ($states === []) {
            return;
        }

        $wait = $this->option('once') ? 0 : self::LONG_POLL_SECONDS;
        $resultByChannel = $vk->getUpdatesPool($channels, $states, $wait);

        foreach ($channels as $channel) {
            $state = $states[(string) $channel->id] ?? null;
            $result = $resultByChannel[(string) $channel->id] ?? null;
            if ($state === null || $result === null) {
                continue;
            }

            if (isset($result['failed'])) {
                $this->handleFailed($channel, $state, $result);

                continue;
            }

            foreach ($result['updates'] ?? [] as $update) {
                ProcessVkUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $update);
            }

            $state['ts'] = (string) ($result['ts'] ?? $state['ts']);
            Cache::forever($this->stateKey($channel->id), $state);
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
