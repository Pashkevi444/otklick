<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console;

use App\Modules\Channels\Jobs\ProcessTelegramUpdate;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Shared\Enums\ChannelType;
use App\Shared\Support\SecretScrubber;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Long polling для Telegram-ботов: сервер сам забирает апдейты у Telegram.
 * Нужно в РФ, где вебхуки не доставляются (входящий путь Telegram→IPv4 блокирован).
 *
 * Все активные каналы опрашиваются КОНКУРЕНТНО (Http::pool в `TelegramGateway::getUpdatesPool`),
 * а НЕ по очереди: иначе один простаивающий бот держал бы свой long-poll и задерживал
 * доставку сообщений остальным — задержка росла линейно с числом каналов (N×long-poll).
 * Апдейты кладутся в ту же очередь, что и вебхук (ProcessTelegramUpdate → Horizon).
 * Offset (last update_id) хранится в кэше; при его потере дубли отсекаются
 * идемпотентностью recordInbound.
 */
final class PollTelegramUpdates extends Command
{
    protected $signature = 'telegram:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает апдейты Telegram-ботов через long polling (вебхуки в РФ недоступны).';

    /**
     * Длительность long-poll, сек. Короткий — потому что круг ждёт ВСЕ каналы (барьер
     * пула), значит задержка доставки ≈ этому порогу. 3с — баланс «снапко» vs частота
     * запросов. При сотнях каналов на воркер — шардировать поллеры (см. docs).
     */
    private const int LONG_POLL_SECONDS = 3;

    /** @var array<string, bool> */
    private array $webhookCleared = [];

    public function handle(TelegramGateway $telegram): int
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

            $this->pollRound($telegram, $channels);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * Один круг: снимаем вебхуки (раз на канал), ОДНИМ пулом тянем апдейты у всех
     * каналов разом, раскидываем по очереди и двигаем offset'ы.
     *
     * @param  Collection<int, Channel>  $channels
     */
    private function pollRound(TelegramGateway $telegram, Collection $channels): void
    {
        foreach ($channels as $channel) {
            if (! isset($this->webhookCleared[$channel->id])) {
                try {
                    // Вебхук и getUpdates взаимоисключающи — снимаем вебхук один раз.
                    $telegram->deleteWebhook($channel);
                    $this->webhookCleared[$channel->id] = true;
                } catch (Throwable $e) {
                    // Сбой снятия вебхука у одного канала не валит остальных — повторим.
                    Log::warning('telegram.poll_connection', ['channel_id' => $channel->id, 'error' => SecretScrubber::scrub($e->getMessage())]);
                }
            }
        }

        $offsets = [];
        foreach ($channels as $channel) {
            $offsets[(string) $channel->id] = (int) Cache::get($this->offsetKey($channel->id), 0);
        }

        $longPoll = $this->option('once') ? 0 : self::LONG_POLL_SECONDS;
        $updatesByChannel = $telegram->getUpdatesPool($channels, $offsets, $longPoll);

        foreach ($channels as $channel) {
            $updates = $updatesByChannel[(string) $channel->id] ?? [];
            if ($updates === []) {
                continue;
            }

            $offset = $offsets[(string) $channel->id];
            foreach ($updates as $update) {
                ProcessTelegramUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $update);
                $offset = max($offset, ((int) ($update['update_id'] ?? 0)) + 1);
            }

            Cache::forever($this->offsetKey($channel->id), $offset);
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
