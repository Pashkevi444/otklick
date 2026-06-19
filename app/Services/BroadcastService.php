<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\ChannelGatewayResolver;
use App\DTO\BroadcastData;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcast;
use App\Mail\BroadcastMail;
use App\Models\Broadcast;
use App\Models\BroadcastDelivery;
use App\Models\Tenant;
use App\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Бизнес-логика рассылок по базе клиентов: создание/планирование/запуск и сама
 * доставка по каналам (мессенджеры + почта). Доставка идёт через порт каналов
 * ({@see ChannelGatewayResolver}) — сервис провайдер-агностичен.
 */
final readonly class BroadcastService
{
    public function __construct(
        private BroadcastRepositoryInterface $broadcasts,
        private ClientRepositoryInterface $clients,
        private ChannelGatewayResolver $channels,
    ) {}

    public function create(string $tenantId, BroadcastData $data, ?int $createdBy): Broadcast
    {
        return $this->broadcasts->create([
            'tenant_id' => $tenantId,
            'title' => $data->title,
            'body' => $data->body,
            'channels' => $data->channels,
            'status' => BroadcastStatus::Draft,
            'recurrence' => $data->recurrence,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Запуск рассылки прямо сейчас: помечаем «отправляется» и ставим доставку в
     * очередь (Horizon). Реальная отправка — в {@see deliver()}.
     */
    public function launchNow(Broadcast $broadcast): void
    {
        $this->broadcasts->update($broadcast, ['status' => BroadcastStatus::Sending]);

        SendBroadcast::dispatch($broadcast->tenant_id, $broadcast->id);

        Log::info('broadcast.launched', ['broadcast_id' => $broadcast->id, 'tenant_id' => $broadcast->tenant_id]);
    }

    /**
     * Планирование рассылки на время $at с периодичностью $recurrence.
     */
    public function schedule(Broadcast $broadcast, BroadcastData $data): void
    {
        $this->broadcasts->update($broadcast, [
            'status' => BroadcastStatus::Scheduled,
            'recurrence' => $data->recurrence,
            'scheduled_at' => $data->scheduledAt,
            'next_run_at' => $data->scheduledAt,
        ]);

        Log::info('broadcast.scheduled', [
            'broadcast_id' => $broadcast->id,
            'next_run_at' => $data->scheduledAt?->toIso8601String(),
            'recurrence' => $data->recurrence->value,
        ]);
    }

    /**
     * Снятие рассылки с расписания / отмена (повторы не сработают).
     */
    public function cancel(Broadcast $broadcast): void
    {
        $this->broadcasts->update($broadcast, [
            'status' => BroadcastStatus::Canceled,
            'next_run_at' => null,
        ]);
    }

    /**
     * Доставка рассылки. Вызывается из очереди в контексте тенанта. Идёт по базе
     * клиентов без отписки, шлёт в выбранные каналы (мессенджеры + email),
     * считает успехи/ошибки. Для периодичной рассылки переносит next_run_at.
     */
    public function deliver(Broadcast $broadcast): void
    {
        // Право на рассылки могли снять (даунгрейд) — жёсткий рубеж.
        $tenant = Tenant::query()->find($broadcast->tenant_id);
        if ($tenant === null || ! $tenant->features()->broadcasts) {
            $this->broadcasts->update($broadcast, ['status' => BroadcastStatus::Canceled, 'next_run_at' => null]);
            Log::warning('broadcast.skipped_no_feature', ['broadcast_id' => $broadcast->id, 'tenant_id' => $broadcast->tenant_id]);

            return;
        }

        $selected = $broadcast->channels;
        $messengerTypes = array_values(array_filter($selected, static fn (string $c): bool => $c !== 'email'));
        $withEmail = in_array('email', $selected, true);

        $sent = 0;
        $failed = 0;
        /** @var list<array<string, mixed>> $deliveries */
        $deliveries = [];

        foreach ($this->clients->marketingAudienceForCurrentTenant() as $client) {
            $seen = [];

            foreach ($client->conversations as $conversation) {
                $channel = $conversation->channel;
                $chatId = (string) ($conversation->external_chat_id ?? '');

                if ($channel === null || ! $channel->is_active || $chatId === '') {
                    continue;
                }
                if (! in_array($channel->type->value, $messengerTypes, true) || ! $this->channels->has($channel->type)) {
                    continue;
                }

                $key = $channel->type->value.':'.$chatId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $error = null;
                try {
                    $this->channels->for($channel->type)->send($channel, $chatId, $broadcast->body);
                    $sent++;
                } catch (Throwable $e) {
                    $failed++;
                    $error = $e->getMessage();
                    Log::warning('broadcast.delivery_failed', [
                        'broadcast_id' => $broadcast->id,
                        'channel' => $channel->type->value,
                        'error' => $error,
                    ]);
                }

                $deliveries[] = $this->deliveryRow($broadcast, $client->id, $channel->type->value, $chatId, $error);
            }

            if ($withEmail && $client->email !== null && $client->email !== '') {
                $error = null;
                try {
                    Mail::to($client->email)->send(new BroadcastMail($broadcast->title, $broadcast->body));
                    $sent++;
                } catch (Throwable $e) {
                    $failed++;
                    $error = $e->getMessage();
                    Log::warning('broadcast.email_failed', ['broadcast_id' => $broadcast->id, 'error' => $error]);
                }

                $deliveries[] = $this->deliveryRow($broadcast, $client->id, 'email', $client->email, $error);
            }
        }

        $this->broadcasts->recordDeliveries($deliveries);

        $next = $broadcast->recurrence->nextRunFrom(now());

        $this->broadcasts->update($broadcast, [
            'sent_count' => $broadcast->sent_count + $sent,
            'failed_count' => $broadcast->failed_count + $failed,
            'last_run_at' => now(),
            'status' => $next !== null ? BroadcastStatus::Scheduled : BroadcastStatus::Sent,
            'next_run_at' => $next,
        ]);

        Log::info('broadcast.delivered', [
            'broadcast_id' => $broadcast->id,
            'tenant_id' => $broadcast->tenant_id,
            'sent' => $sent,
            'failed' => $failed,
            'recurring' => $next !== null,
        ]);
    }

    /**
     * Строка журнала доставки одному получателю. $error=null → успех.
     *
     * @return array<string, mixed>
     */
    private function deliveryRow(Broadcast $broadcast, string $clientId, string $channel, ?string $target, ?string $error): array
    {
        return [
            'tenant_id' => $broadcast->tenant_id,
            'broadcast_id' => $broadcast->id,
            'client_id' => $clientId,
            'channel' => $channel,
            'target' => $target,
            'status' => $error === null ? BroadcastDelivery::STATUS_SENT : BroadcastDelivery::STATUS_FAILED,
            'error' => $error,
        ];
    }
}
