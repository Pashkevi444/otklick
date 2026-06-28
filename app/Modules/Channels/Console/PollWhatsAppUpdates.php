<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console;

use App\Modules\Channels\Jobs\ProcessWhatsAppUpdate;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Support\PollFailureLog;
use App\Modules\Channels\WhatsApp\WhatsAppGateway;
use App\Shared\Enums\ChannelType;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Long polling для WhatsApp (Green API): сервер сам забирает входящие
 * (receiveNotification) и кладёт их в очередь Horizon (ProcessWhatsAppUpdate).
 * Аналог `telegram:poll`/`vk:poll`/`max:poll`.
 *
 * ПЕРВОЕ уведомление у всех каналов тянем КОНКУРЕНТНО (Http::pool в
 * `WhatsAppGateway::receiveNotificationPool`), а НЕ по очереди: иначе один
 * простаивающий канал держал бы блокирующий long-poll и задерживал остальных
 * (задержка росла линейно с числом каналов). Остаток очереди канала дренажим уже
 * per-channel быстрыми (timeout=0) запросами — это не блокирует.
 *
 * Позицию НЕ храним: очередь ведёт Green API на своей стороне, обработанное
 * подтверждаем deleteNotification (звать всегда — иначе очередь забьётся).
 */
final class PollWhatsAppUpdates extends Command
{
    /** Максимум уведомлений за один проход по каналу (чтобы не голодали другие). */
    private const int DRAIN_LIMIT = 25;

    /**
     * Ожидание ПЕРВОГО receiveNotification в круге, сек. Короткий блокирующий
     * long-poll — круг ждёт ВСЕ каналы (барьер пула); дренаж дальше идёт без ожидания.
     */
    private const int LONG_POLL_SECONDS = 5;

    protected $signature = 'whatsapp:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает входящие WhatsApp (Green API) через long polling (whatsapp:poll).';

    public function handle(WhatsAppGateway $whatsapp): int
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

            $this->pollRound($whatsapp, $channels);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * Один круг: ПЕРВОЕ уведомление у всех каналов тянем КОНКУРЕНТНО (пул), затем
     * per-channel дренажим остаток очереди до DRAIN_LIMIT.
     *
     * @param  Collection<int, Channel>  $channels
     */
    private function pollRound(WhatsAppGateway $whatsapp, Collection $channels): void
    {
        $timeout = $this->option('once') ? 0 : self::LONG_POLL_SECONDS;
        $firstByChannel = $whatsapp->receiveNotificationPool($channels, $timeout);

        foreach ($channels as $channel) {
            // Канал отсутствует в результате — сбой пула (залогирован), пропускаем.
            if (! array_key_exists((string) $channel->id, $firstByChannel)) {
                continue;
            }

            try {
                $this->drainChannel($whatsapp, $channel, $firstByChannel[(string) $channel->id]);
            } catch (Throwable $e) {
                // Сбой дренажа одного канала не валит остальных; ретрай следующим кругом.
                PollFailureLog::record('whatsapp', (string) $channel->id, $e);
            }
        }
    }

    /**
     * Обрабатывает первое уведомление (полученное конкурентно из пула) и дренажит
     * остаток очереди канала быстрыми (timeout=0) запросами до DRAIN_LIMIT.
     * Подтверждаем (deleteNotification) ВСЕГДА — иначе очередь Green API забьётся и
     * приём остановится.
     *
     * @param  array{receiptId: int, body: array<string, mixed>}|null  $first
     */
    private function drainChannel(WhatsAppGateway $whatsapp, Channel $channel, ?array $first): void
    {
        $note = $first;

        for ($processed = 0; $note !== null && $processed < self::DRAIN_LIMIT; $processed++) {
            if (($note['body']['typeWebhook'] ?? null) === 'incomingMessageReceived') {
                ProcessWhatsAppUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $note['body']);
            }

            $whatsapp->deleteNotification($channel, $note['receiptId']);

            // Дренаж быстрыми запросами; на лимите больше не тянем (без лишнего запроса).
            $note = $processed + 1 < self::DRAIN_LIMIT
                ? $whatsapp->receiveNotification($channel, 0)
                : null;
        }
    }

    /**
     * @return Collection<int, Channel>
     */
    private function activeChannels(): Collection
    {
        return Channel::query()
            ->withoutGlobalScopes()
            ->where('type', ChannelType::WhatsApp)
            ->where('is_active', true)
            ->get();
    }
}
