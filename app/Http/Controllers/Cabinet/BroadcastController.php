<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\BroadcastRecurrence;
use App\Enums\BroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\StoreBroadcastRequest;
use App\Models\Broadcast;
use App\Models\BroadcastDelivery;
use App\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\BroadcastService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Таб «Рассылки» в кабинете: список рассылок + создание (запуск сейчас или по
 * расписанию). Доступ — возможность тарифа `broadcasts` (+ раздел сотрудника).
 */
final class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastRepositoryInterface $broadcasts,
        private readonly ClientRepositoryInterface $clients,
        private readonly BroadcastService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Cabinet/Broadcasts/Index', [
            'broadcasts' => $this->broadcasts->forCurrentTenant()
                ->map(fn (Broadcast $b): array => $this->present($b))
                ->all(),
            'audienceCount' => $this->clients->marketingAudienceCountForCurrentTenant(),
            'clients' => $this->clients->pickerListForCurrentTenant(),
            'channelOptions' => [
                ['value' => 'telegram', 'label' => 'Telegram'],
                ['value' => 'vk', 'label' => 'ВКонтакте'],
                ['value' => 'max', 'label' => 'MAX'],
                ['value' => 'email', 'label' => 'Email'],
            ],
            'recurrenceOptions' => array_map(
                fn (BroadcastRecurrence $r): array => ['value' => $r->value, 'label' => $r->label()],
                BroadcastRecurrence::cases(),
            ),
        ]);
    }

    public function store(StoreBroadcastRequest $request): RedirectResponse
    {
        $data = $request->toData();

        $broadcast = $this->service->create(
            (string) $request->user()->tenant_id,
            $data,
            (int) $request->user()->id,
        );

        if ($request->isScheduled()) {
            $this->service->schedule($broadcast, $data);
            $message = 'Рассылка запланирована.';
        } else {
            $this->service->launchNow($broadcast);
            $message = 'Рассылка запущена — сообщения уходят клиентам.';
        }

        return redirect()->route('cabinet.broadcasts.index')->with('success', $message);
    }

    public function show(string $broadcast): Response
    {
        $model = $this->findOrFail($broadcast);

        return Inertia::render('Cabinet/Broadcasts/Show', [
            'broadcast' => $this->present($model),
            'deliveries' => $this->broadcasts->deliveriesForCurrentTenant($model->id)
                ->map(fn (BroadcastDelivery $d): array => [
                    'id' => $d->id,
                    'recipient' => $d->client?->name ?: ($d->client?->phone ?: ($d->target ?: '—')),
                    'contact' => $d->channel === 'email' ? $d->target : ($d->client?->phone ?: $d->target),
                    'channel' => $d->channel,
                    'channel_label' => $this->channelLabel($d->channel),
                    'status' => $d->status,
                    'error' => $d->error,
                    'at' => $d->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function run(string $broadcast): RedirectResponse
    {
        $this->service->launchNow($this->findOrFail($broadcast));

        return redirect()->route('cabinet.broadcasts.index')->with('success', 'Рассылка запущена.');
    }

    public function cancel(string $broadcast): RedirectResponse
    {
        $this->service->cancel($this->findOrFail($broadcast));

        return redirect()->route('cabinet.broadcasts.index')->with('success', 'Рассылка снята с расписания.');
    }

    public function destroy(string $broadcast): RedirectResponse
    {
        $this->broadcasts->delete($this->findOrFail($broadcast));

        return redirect()->route('cabinet.broadcasts.index')->with('success', 'Рассылка удалена.');
    }

    private function channelLabel(string $value): string
    {
        return match ($value) {
            'telegram' => 'Telegram',
            'vk' => 'ВКонтакте',
            'max' => 'MAX',
            'email' => 'Email',
            'skipped' => '—',
            default => $value,
        };
    }

    private function findOrFail(string $id): Broadcast
    {
        $broadcast = $this->broadcasts->find($id);

        abort_if($broadcast === null, 404);

        return $broadcast;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Broadcast $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'body' => $b->body,
            'channels' => $b->channels,
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'recurrence' => $b->recurrence->value,
            'recurrence_label' => $b->recurrence->label(),
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'next_run_at' => $b->next_run_at?->toIso8601String(),
            'last_run_at' => $b->last_run_at?->toIso8601String(),
            'sent_count' => $b->sent_count,
            'failed_count' => $b->failed_count,
            'is_scheduled' => $b->status === BroadcastStatus::Scheduled,
        ];
    }
}
