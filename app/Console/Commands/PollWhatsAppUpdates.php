<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\WhatsApp\WhatsAppGateway;
use App\Enums\ChannelType;
use App\Jobs\ProcessWhatsAppUpdate;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Long polling для WhatsApp (Green API): сервер сам забирает входящие
 * (receiveNotification) и кладёт их в очередь Horizon (ProcessWhatsAppUpdate).
 * Аналог `telegram:poll`/`vk:poll`/`max:poll`.
 *
 * Позицию НЕ храним: очередь ведёт Green API на своей стороне, обработанное
 * подтверждаем deleteNotification (звать всегда — иначе очередь забьётся).
 */
final class PollWhatsAppUpdates extends Command
{
    /** Максимум уведомлений за один проход по каналу (чтобы не голодали другие). */
    private const int DRAIN_LIMIT = 25;

    protected $signature = 'whatsapp:poll {--once : Один проход и выход (для тестов/отладки)}';

    protected $description = 'Забирает входящие WhatsApp (Green API) через long polling (whatsapp:poll).';

    public function handle(WhatsAppGateway $whatsapp): int
    {
        do {
            $channels = $this->activeChannels();

            foreach ($channels as $channel) {
                $this->pollChannel($whatsapp, $channel);
            }

            // Нет активных WhatsApp-каналов — long poll не блокирует, ждём сами.
            if ($channels->isEmpty() && ! $this->option('once')) {
                sleep(5);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function pollChannel(WhatsAppGateway $whatsapp, Channel $channel): void
    {
        try {
            $timeout = $this->option('once') ? 0 : 5;

            for ($drained = 0; $drained < self::DRAIN_LIMIT; $drained++) {
                $note = $whatsapp->receiveNotification($channel, $timeout);

                if ($note === null) {
                    break;
                }

                $timeout = 0; // последующие в этом проходе — без ожидания

                if (($note['body']['typeWebhook'] ?? null) === 'incomingMessageReceived') {
                    ProcessWhatsAppUpdate::dispatch((string) $channel->tenant_id, (string) $channel->id, $note['body']);
                }

                // Подтверждаем ВСЕГДА (в т.ч. служебные/исходящие события), иначе
                // очередь Green API забьётся и приём остановится.
                $whatsapp->deleteNotification($channel, $note['receiptId']);
            }
        } catch (Throwable $e) {
            Log::warning('whatsapp.poll_failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            report($e);
            usleep(500_000); // не молотим Green API при ошибке
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
